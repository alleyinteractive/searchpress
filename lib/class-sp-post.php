<?php

/**
* An object for posts in the ES index
*
* @todo should we index paginated posts differently? Would be nice to click a search result and go to correct page
*/
class SP_Post {

	# Core post fields
	public $post_id;
	public $post_author;
	public $post_date;
	public $post_date_gmt;
	public $post_content;
	public $post_title;
	public $post_excerpt;
	public $post_status;
	public $post_name;
	public $post_modified;
	public $post_modified_gmt;
	public $post_parent;
	public $post_type;
	public $post_mime_type;

	# Linked objects
	public $terms;
	public $post_meta;

	# Additional attributes
	public $permalink;


	/**
	 * Instantiate the class
	 *
	 * @param int|object $post Can either be a WP_Post object or a post ID
	 * @return void
	 */
	function __construct( $post ) {
		if ( is_numeric( $post ) && 0 != intval( $post ) )
			$post = get_post( intval( $post ) );
		if ( ! is_object( $post ) )
			return;

		$this->fill( $post );
	}


	/**
	 * Populate this object with all of the post's properties
	 *
	 * @param object $post
	 * @return void
	 */
	public function fill( $post ) {
		$apply_filters = apply_filters( 'sp_post_index_filtered_data', false );

		$this->post_id           = $post->ID;
		# We're storing the login here instead of user ID, as that's more flexible
		$this->post_author       = $this->get_user( $post->post_author );
		$this->post_date         = $post->post_date;
		$this->post_date_gmt     = $post->post_date_gmt;
		$this->post_content      = $apply_filters ? str_replace( ']]>', ']]&gt;', apply_filters( 'the_content', $post->post_content ) ) : $post->post_content;
		$this->post_title        = $apply_filters ? get_the_title( $post->ID ) : $post->post_title;
		$this->post_excerpt      = $post->post_excerpt;
		$this->post_status       = $post->post_status;
		$this->post_name         = $post->post_name;
		$this->post_modified     = $post->post_modified;
		$this->post_modified_gmt = $post->post_modified_gmt;
		$this->post_parent       = $post->post_parent;
		$this->post_type         = $post->post_type;
		$this->post_mime_type    = $post->post_mime_type;
		$this->permalink         = get_permalink( $post->ID );

		$this->terms             = $this->get_terms( $post );
		$this->post_meta         = $this->get_meta( $post->ID );
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

		if ( SP_Config()->unserialize_meta() ) {
			# If post meta is serialized, unserialize it
			foreach ( $meta as &$values ) {
				$values = array_map( 'maybe_unserialize', $values );
			}
		}

		return $meta;
	}


	/**
	 * Get all terms across all taxonomies for a given post
	 *
	 * @param object $post
	 * @return array
	 */
	public static function get_terms( $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type );
		$object_terms = get_the_terms( $post->ID, $taxonomies );
		if ( !$object_terms || is_wp_error( $object_terms ) )
			return array();

		$terms = array();
		foreach ( (array) $object_terms as $term ) {
			$terms[ $term->taxonomy ][] = array(
				'term_id'     => $term->term_id,
				'slug'        => $term->slug,
				'name'        => $term->name,
				'parent'      => $term->parent
			);
		}
		return $terms;
	}


	/**
	 * Get information about a post author
	 *
	 * @param int $user_id
	 * @return array
	 */
	public function get_user( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User ) {
			return array(
				'login'        => $user->user_login,
				'display_name' => $user->display_name
			);
		}
		return array(
			'login'        => '',
			'display_name' => ''
		);
	}


	/**
	 * Return this object as JSON
	 *
	 * @return string
	 */
	public function to_json() {
		return json_encode( apply_filters( 'sp_post_pre_index', $this ) );
	}


	public function should_be_indexed() {
		# Check post type
		if ( ! in_array( $this->post_type, SP_Config()->sync_post_types() ) )
			return false;

		# Check post status
		if ( ! in_array( $this->post_status, SP_Config()->sync_statuses() ) )
			return false;

		return apply_filters( 'sp_post_should_be_indexed', true, $this );
	}

}