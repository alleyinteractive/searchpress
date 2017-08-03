<?php

/**
* An object for posts in the ES index
*
* @todo should we index paginated posts differently? Would be nice to click a search result and go to correct page
*/
class SP_Post extends SP_Indexable {

	/**
	 * This stores what will eventually become our JSON
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Instantiate the class
	 *
	 * @param int|object $post Can either be a WP_Post object or a post ID
	 * @return void
	 */
	function __construct( $post ) {
		if ( is_numeric( $post ) && 0 != intval( $post ) ) {
			$post = get_post( intval( $post ) );
		}
		if ( ! is_object( $post ) ) {
			return;
		}

		$this->id = $post->ID;
		$this->fill( $post );
	}


	/**
	 * Use magic methods to make the normal post properties available in
	 * OOP style accessing
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	public function __set( $property, $value ) {
		$this->data[ $property ] = $value;
	}

	/**
	 * Use magic methods to make the normal post properties available in
	 * OOP style accessing
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	public function __get( $property ) {
		// let the post ID be accessed either way
		if ( 'ID' == $property ) {
			$property = 'post_id';
		}

		return isset( $this->data[ $property ] ) ? $this->data[ $property ] : null;
	}


	/**
	 * Populate this object with all of the post's properties
	 *
	 * @param object $post
	 * @return void
	 */
	public function fill( $post ) {
		do_action( 'sp_debug', '[SP_Post] Populating Post' );
		$apply_filters = apply_filters( 'sp_post_index_filtered_data', false );

		$this->data['post_id']           = intval( $post->ID );
		// We're storing the login here instead of user ID, as that's more flexible
		$this->data['post_author']       = $this->get_user( $post->post_author );
		$this->data['post_date']         = $this->get_date( $post->post_date );
		$this->data['post_date_gmt']     = $this->get_date( $post->post_date_gmt );
		$this->data['post_modified']     = $this->get_date( $post->post_modified );
		$this->data['post_modified_gmt'] = $this->get_date( $post->post_modified_gmt );
		$this->data['post_title']        = self::limit_string( $apply_filters ? strval( get_the_title( $post->ID ) ) : strval( $post->post_title ) );
		$this->data['post_excerpt']      = self::limit_word_length( strval( $post->post_excerpt ) );
		$this->data['post_content']      = self::limit_word_length( $apply_filters ? strval( str_replace( ']]>', ']]&gt;', apply_filters( 'the_content', $post->post_content ) ) ) : strval( $post->post_content ) );
		$this->data['post_status']       = self::limit_string( strval( $post->post_status ) );
		$this->data['post_name']         = self::limit_string( strval( $post->post_name ) );
		$this->data['post_parent']       = intval( $post->post_parent );
		$this->data['parent_status']     = $post->post_parent ? get_post_status( $post->post_parent ) : '';
		$this->data['post_type']         = self::limit_string( strval( $post->post_type ) );
		$this->data['post_mime_type']    = self::limit_string( strval( $post->post_mime_type ) );
		$this->data['post_password']     = self::limit_string( strval( $post->post_password ) );
		$this->data['menu_order']        = intval( $post->menu_order );
		$this->data['permalink']         = self::limit_string( strval( esc_url_raw( get_permalink( $post->ID ) ) ) );

		$this->data['terms']             = $this->get_terms( $post );
		$this->data['post_meta']         = $this->get_meta( $post->ID );

		// If a date field is empty, kill it to avoid indexing errors
		foreach ( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $field ) {
			if ( empty( $this->data[ $field ] ) ) {
				unset( $this->data[ $field ] );
			}
		}

		// If post status is inherit, but there's no parent status, index the
		// parent status as 'publish'. This is a bit hacky, but required for
		// proper indexing and searching.
		if ( 'inherit' === $this->data['post_status'] && empty( $this->data['parent_status'] ) ) {
			$this->data['parent_status'] = 'publish';
		}
	}

	/**
	 * Get post meta for a given post ID.
	 * Some post meta is removed (you can filter it), and serialized data gets unserialized
	 *
	 * @param int $post_id
	 * @return array 'meta_key' => array( value 1, value 2... )
	 */
	public static function get_meta( $post_id ) {
		$meta = (array) get_post_meta( $post_id );

		// Remove a filtered set of meta that we don't want indexed
		$ignored_meta = apply_filters( 'sp_post_ignored_postmeta', array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_trash_meta_time',
			'_wp_trash_meta_status',
			'_previous_revision',
			'_wpas_done_all',
			'_encloseme',
			'_pingme',
		) );
		foreach ( $ignored_meta as $key ) {
			unset( $meta[ $key ] );
		}

		/**
		 * Filter the post meta to be indexed before type casting.
		 *
		 * @param array $meta The meta to be indexed.
		 * @param int $post_id The post ID.
		 */
		$meta = apply_filters( 'sp_post_indexable_meta', $meta, $post_id );

		foreach ( $meta as $key => &$values ) {
			// Ignore oembed meta, which continuously expands the mapping
			if ( '_oembed_' === substr( $key, 0, 8 ) ) {
				unset( $meta[ $key ] );
				continue;
			}
			$values = array_map( array( 'SP_Post', 'cast_meta_types' ), $values );
		}

		do_action( 'sp_debug', '[SP_Post] Compiled Meta', $meta );

		return $meta;
	}


	/**
	 * Get all terms across all taxonomies for a given post
	 *
	 * @param object $post
	 * @return array
	 */
	public static function get_terms( $post ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return self::get_terms_efficiently( $post );
		}

		$object_terms = array();
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$these_terms = get_the_terms( $post->ID, $taxonomy );
			if ( $these_terms && ! is_wp_error( $these_terms ) ) {
				$object_terms = array_merge( $object_terms, $these_terms );
			}
		}

		if ( empty( $object_terms ) ) {
			return;
		}

		$terms = array();
		foreach ( (array) $object_terms as $term ) {
			$terms[ $term->taxonomy ][] = array(
				'term_id' => intval( $term->term_id ),
				'slug'    => self::limit_string( strval( $term->slug ) ),
				'name'    => self::limit_string( strval( $term->name ) ),
				'parent'  => intval( $term->parent ),
			);
		}

		do_action( 'sp_debug', '[SP_Post] Compiled Terms', $terms );
		return $terms;
	}


	/**
	 * Does the same thing as get_terms but in 1 query instead of
	 * <num of taxonomies> + 1. Only used in WP-CLI commands.
	 *
	 * @codeCoverageIgnore
	 *
	 * @param object $post
	 * @return array Terms to index.
	 */
	private static function get_terms_efficiently( $post ) {
		global $wpdb;

		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( empty( $taxonomies ) ) {
			return array();
		}

		$query = "SELECT t.*, tt.* FROM {$wpdb->terms} AS t INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = t.term_id INNER JOIN {$wpdb->term_relationships} AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy IN (" . implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) ) . ') AND tr.object_id = %d ORDER BY t.term_id';

		$params = array_merge( array( $query ), $taxonomies, array( $post->ID ) );

		$object_terms = $wpdb->get_results( call_user_func_array( array( $wpdb, 'prepare' ), $params ) ); // WPCS: db call ok. WPCS: cache ok. WPCS: unprepared SQL ok.

		if ( ! $object_terms || is_wp_error( $object_terms ) ) {
			return array();
		}

		$terms = array();
		foreach ( (array) $object_terms as $term ) {
			$terms[ $term->taxonomy ][] = array(
				'term_id' => intval( $term->term_id ),
				'slug'    => strval( $term->slug ),
				'name'    => strval( $term->name ),
				'parent'  => intval( $term->parent ),
			);
		}

		do_action( 'sp_debug', '[SP_Post] Compiled Terms Efficiently', $terms );
		return $terms;
	}


	/**
	 * Get information about a post author
	 *
	 * @param int $user_id
	 * @return array
	 */
	public function get_user( $user_id ) {
		if ( ! empty( SP_Sync_Manager()->users[ $user_id ] ) ) {
			return SP_Sync_Manager()->users[ $user_id ];
		}

		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User ) {
			$data = array(
				'user_id'       => intval( $user_id ),
				'login'         => self::limit_string( strval( $user->user_login ) ),
				'display_name'  => self::limit_string( strval( $user->display_name ) ),
				'user_nicename' => self::limit_string( strval( $user->user_nicename ) ),
			);
		} else {
			$data = array(
				'user_id'       => intval( $user_id ),
				'login'         => '',
				'display_name'  => '',
				'user_nicename' => '',
			);
		}
		SP_Sync_Manager()->users[ $user_id ] = $data;
		do_action( 'sp_debug', '[SP_Post] Compiled User', $data );

		return $data;
	}

	/**
	 * Return this object as JSON
	 *
	 * @return string
	 */
	public function to_json() {
		/**
		 * Filter the data prior to indexing. If you need to modify the data sent
		 * to Elasticsearch, this is likely the best filter to use.
		 *
		 * @param array    $data Data to be sent to Elasticsearch.
		 * @param \SP_Post $this This object.
		 */
		return wp_json_encode( apply_filters( 'sp_post_pre_index', $this->data, $this ) );
	}

	public function should_be_indexed() {
		// Check post type
		if ( ! in_array( $this->data['post_type'], SP_Config()->sync_post_types() ) ) {
			return false;
		}

		// Check post status
		if ( 'inherit' === $this->data['post_status'] && ! empty( $this->data['parent_status'] ) ) {
			$post_status = $this->data['parent_status'];
		} else {
			$post_status = $this->data['post_status'];
		}
		if ( ! in_array( $post_status, SP_Config()->sync_statuses() ) ) {
			return false;
		}

		return apply_filters( 'sp_post_should_be_indexed', true, $this );
	}

	/**
	 * Helper to determine if this post is "searchable". That is, is its
	 * `post_type` in `sp_searchable_post_types()` and is its `post_status` in
	 * `sp_searchable_post_statuses()`.
	 *
	 * @return boolean true if yes, false if no.
	 */
	public function is_searchable() {
		return (
			in_array( $this->data['post_type'], sp_searchable_post_types(), true )
			&& in_array( $this->data['post_status'], sp_searchable_post_statuses(), true )
		);
	}
}
