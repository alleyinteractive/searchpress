<?php

/**
 * Elasticsearch Sync Manager
 *
 * Controls the data sync from WordPress to elasticsearch
 *
 * @todo add user data
 * @author Matthew Boynes
 */

if ( !class_exists( 'ES_Sync_Manager' ) ) :

class ES_Sync_Manager {

	private static $instance;

	const BATCH_SIZE = 25;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone ES_Sync_Manager" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup ES_Sync_Manager" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new ES_Sync_Manager;
			self::$instance->setup();
		}
		return self::$instance;
	}


	public function setup() {
		# We're set.
	}


	/**
	 * Run the sync process
	 *
	 * @param int $start
	 * @param int $limit
	 * @return void
	 */
	public function sync( $start, $limit ) {
		if ( false !== ( $previous_sync = get_transient( 'es_sync' ) ) ) {
			# Sync is running, or died. Do something about it.
			return $previous_sync;
		}

		set_transient( 'es_sync', array( 'start' => $start, 'limit' => $limit ), HOUR_IN_SECONDS );

		$data = $this->get_json_range( $start, $limit );
		# Do something with $data

		delete_transient( 'es_sync' );
	}


	/**
	 * Get all the posts in a given range as JSON data
	 *
	 * @param int $start
	 * @param int $limit
	 * @return string JSON array
	 */
	public function get_json_range( $start, $limit ) {
		$posts = array();

		# Run the loop in batches to contain memory leaks
		while ( $limit > 0 ) {
			$ceil = ( $limit >= self::BATCH_SIZE ) ? self::BATCH_SIZE : $limit;
			$limit -= $ceil;
			$posts = $posts + $this->get_posts( array(
				'offset'         => $start,
				'posts_per_page' => $ceil
			) );
			$start += self::BATCH_SIZE;
			$this->contain_memory_leaks();
		}
		$data = json_encode( $posts );
	}


	/**
	 * Get a single post as a JSON object
	 *
	 * @param int $post_id
	 * @return string JSON object
	 */
	function get_post_json( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			$post->meta  = $this->get_meta( $post->ID );
			$post->terms = $this->get_terms( $post );
			return json_encode( $post );
		}
		return '{}';
	}


	/**
	 * Get posts to loop through
	 *
	 * @param array $args arguments passed to get_posts
	 * @return array
	 */
	public function get_posts( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'post_status'      => 'publish',
			'post_type'        => get_post_types( array( 'exclude_from_search' => false ) ),
			'suppress_filters' => false
		) );

		$posts = get_posts( $args );
		$indexed_posts = array();

		foreach ( $posts as $post ) {
			$post->meta  = $this->get_meta( $post->ID );
			$post->terms = $this->get_terms( $post );
			$indexed_posts[ $post->ID ] = $post;
		}
		return $indexed_posts;
	}


	/**
	 * Get post meta for a given post ID.
	 * Some post meta is removed (you can filter it), and serialized data gets unserialized
	 *
	 * @param int $post_id
	 * @return array 'meta_key' => array( value 1, value 2... )
	 */
	public function get_meta( $post_id ) {
		$meta = (array) get_post_meta( $post_id );

		# Remove a filtered set of meta that we don't want indexed
		$ignored_meta = apply_filters( 'es_sync_ignored_postmeta', array(
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

		# If post meta is serialized, unserialize it
		foreach ( $meta as &$values ) {
			$values = array_map( 'maybe_unserialize', $values );
		}

		return $meta;
	}


	/**
	 * Get all terms across all taxonomies for a given post
	 *
	 * @param object $post
	 * @return array
	 */
	public function get_terms( $post ) {
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
				'description' => $term->description,
				'parent'      => $term->parent
			);
		}
		return $terms;
	}


	/**
	 * Prevent memory leaks from growing out of control
	 *
	 * @return void
	 */
	public function contain_memory_leaks() {
		global $wpdb, $wp_object_cache;
		$wpdb->queries = array();
		if ( !is_object( $wp_object_cache ) )
			return;
		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();
		if ( method_exists( $wp_object_cache, '__remoteset' ) )
			$wp_object_cache->__remoteset();
	}
}

function ES_Sync_Manager() {
	return ES_Sync_Manager::instance();
}
add_action( 'after_setup_theme', 'ES_Sync_Manager' );

endif;