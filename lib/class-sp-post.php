<?php

/**
* An object for posts in the ES index
*
* @todo should we index paginated posts differently? Would be nice to click a search result and go to correct page
*/
class SP_Post {

	# This stores what will eventually become our JSON
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
		# let the post ID be accessed either way
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
		# We're storing the login here instead of user ID, as that's more flexible
		$this->data['post_author']       = $this->get_user( $post->post_author );
		$this->data['post_date']         = $this->get_date( $post->post_date, 'post_date' );
		$this->data['post_date_gmt']     = $this->get_date( $post->post_date_gmt, 'post_date_gmt' );
		$this->data['post_modified']     = $this->get_date( $post->post_modified, 'post_modified' );
		$this->data['post_modified_gmt'] = $this->get_date( $post->post_modified_gmt, 'post_modified_gmt' );
		$this->data['post_title']        = $apply_filters ? strval( get_the_title( $post->ID ) ) : strval( $post->post_title );
		$this->data['post_excerpt']      = strval( $post->post_excerpt );
		$this->data['post_content']      = $apply_filters ? strval( str_replace( ']]>', ']]&gt;', apply_filters( 'the_content', $post->post_content ) ) ) : strval( $post->post_content );
		$this->data['post_status']       = strval( $post->post_status );
		$this->data['post_name']         = strval( $post->post_name );
		$this->data['post_parent']       = intval( $post->post_parent );
		$this->data['post_type']         = strval( $post->post_type );
		$this->data['post_mime_type']    = strval( $post->post_mime_type );
		$this->data['post_password']     = strval( $post->post_password );
		$this->data['menu_order']        = intval( $post->menu_order );
		$this->data['permalink']         = strval( esc_url_raw( get_permalink( $post->ID ) ) );

		$this->data['terms']             = $this->get_terms( $post );
		$this->data['post_meta']         = $this->get_meta( $post->ID );

		// If a date field is empty, kill it to avoid indexing errors
		foreach ( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $field ) {
			if ( empty( $this->data[ $field ] ) ) {
				unset( $this->data[ $field ] );
			}
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

		# Remove a filtered set of meta that we don't want indexed
		$ignored_meta = apply_filters( 'sp_post_ignored_postmeta', array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_trash_meta_time',
			'_wp_trash_meta_status',
			'_previous_revision',
			'_wpas_done_all',
			'_encloseme'
		) );
		foreach ( $ignored_meta as $key ) {
			unset( $meta[ $key ] );
		}

		foreach ( $meta as &$values ) {
			$values = array_map( array( 'SP_Post', 'cast_meta_types' ), $values );
		}

		do_action( 'sp_debug', '[SP_Post] Compiled Meta', $meta );
		return $meta;
	}


	/**
	 * Split the meta values into different types for meta query casting.
	 *
	 * @param  string $value Meta value.
	 * @return array
	 */
	public static function cast_meta_types( $value ) {
		$return = array(
			'value'   => $value,
			'raw'     => $value,
			'boolean' => (bool) $value,
		);

		$time = false;
		if ( is_numeric( $value ) ) {
			$int = intval( $value );
			$return['long']   = $int;
			$return['double'] = floatval( $value );

			// If this is an integer (represented as a string), check to see if
			// it is a valid timestamp
			if ( (string) $int === (string) $value ) {
				$year = intval( date( 'Y', $int ) );
				// Ensure that the year is between 1-2038. Technically, the year
				// range ES allows is 1-292278993, but PHP ints limit us to 2038.
				if ( $year > 0 && $year < 2039 ) {
					$time = $int;
				}
			}
		} elseif ( is_string( $value ) ) {
			// correct boolean values
			if ( 'false' === strtolower( $value ) ) {
				$return['boolean'] = false;
			} elseif ( 'true' === strtolower( $value ) ) {
				$return['boolean'] = true;
			}

			// add date/time if we have it.
			$time = strtotime( $value );
		}

		if ( false !== $time ) {
			$return['date']     = date( 'Y-m-d', $time );
			$return['datetime'] = date( 'Y-m-d H:i:s', $time );
			$return['time']     = date( 'H:i:s', $time );
		}

		return $return;
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
				'slug'    => strval( $term->slug ),
				'name'    => strval( $term->name ),
				'parent'  => intval( $term->parent )
			);
		}

		do_action( 'sp_debug', '[SP_Post] Compiled Terms', $terms );
		return $terms;
	}


	/**
	 * Does the same thing as get_terms but in 1 query instead of <num of taxonomies> + 1
	 *
	 * @codeCoverageIgnore
	 *
	 * @param object $post
	 * @return object
	 */
	private static function get_terms_efficiently( $post ) {
		global $wpdb;

		$taxonomies = get_object_taxonomies( $post->post_type );
		$taxonomies = "'" . implode("', '", $taxonomies) . "'";
		$post_id = intval( $post->ID );
		$query = "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy IN ($taxonomies) AND tr.object_id = $post_id ORDER BY t.term_id";

		$object_terms = $wpdb->get_results( $query );
		if ( !$object_terms || is_wp_error( $object_terms ) ) {
			return array();
		}

		$terms = array();
		foreach ( (array) $object_terms as $term ) {
			$terms[ $term->taxonomy ][] = array(
				'term_id' => intval( $term->term_id ),
				'slug'    => strval( $term->slug ),
				'name'    => strval( $term->name ),
				'parent'  => intval( $term->parent )
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
				'login'         => strval( $user->user_login ),
				'display_name'  => strval( $user->display_name ),
				'user_nicename' => strval( $user->user_nicename ),
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
	 * Parse out the properties of a date.
	 *
	 * @param  string $date  A date, expected to be in mysql format.
	 * @param  string $field The field for which we're pulling this information.
	 * @return array The parsed date.
	 */
	public function get_date( $date, $field ) {
		if ( empty( $date ) || '0000-00-00 00:00:00' == $date ) {
			return null;
		}

		$ts = strtotime( $date );
		return array(
			'date'              => strval( $date ),
			'year'              => intval( date( 'Y', $ts ) ),
			'month'             => intval( date( 'm', $ts ) ),
			'day'               => intval( date( 'd', $ts ) ),
			'hour'              => intval( date( 'H', $ts ) ),
			'minute'            => intval( date( 'i', $ts ) ),
			'second'            => intval( date( 's', $ts ) ),
			'week'              => intval( date( 'W', $ts ) ),
			'day_of_week'       => intval( date( 'N', $ts ) ),
			'day_of_year'       => intval( date( 'z', $ts ) ),
			'seconds_from_day'  => intval( mktime( date( 'H', $ts ), date( 'i', $ts ), date( 's', $ts ), 1, 1, 1970 ) ),
			'seconds_from_hour' => intval( mktime( 0, date( 'i', $ts ), date( 's', $ts ), 1, 1, 1970 ) ),
		);
	}


	/**
	 * Return this object as JSON
	 *
	 * @return string
	 */
	public function to_json() {
		return json_encode( apply_filters( 'sp_post_pre_index', $this->data ) );
	}


	public function should_be_indexed() {
		# Check post type
		if ( ! in_array( $this->data['post_type'], SP_Config()->sync_post_types() ) ) {
			return false;
		}

		# Check post status
		if ( ! in_array( $this->data['post_status'], SP_Config()->sync_statuses() ) ) {
			return false;
		}

		return apply_filters( 'sp_post_should_be_indexed', true, $this );
	}
}