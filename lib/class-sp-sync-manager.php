<?php

/**
 * Elasticsearch Sync Manager
 *
 * Controls the data sync from WordPress to elasticsearch
 *
 * Reminders and considerations while building this:
 * @todo Trigger massive reindex (potentially) when indexed usermeta is edited
 * @todo Trigger massive reindex when term data is edited
 * @todo Changing permalinks should trigger full reindex?
 *
 * @author Matthew Boynes
 */

if ( !class_exists( 'SP_Sync_Manager' ) ) :

class SP_Sync_Manager {

	private static $instance;

	public $sync_meta;

	public $published_posts = false;

	public $batch_size = 25;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone SP_Sync_Manager" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup SP_Sync_Manager" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Sync_Manager;
			self::$instance->setup();
		}
		return self::$instance;
	}


	public function setup() {
		# Nothing happening right now
	}


	/**
	 * Sync a single post (on creation or update)
	 *
	 * @todo if post should not be added, it's deleted (to account for unpublishing, etc). Make that more elegant.
	 * @todo remove error_log calls
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function sync_post( $post_id ) {
		$post = new SP_Post( get_post( $post_id ) );
		if ( $post->should_be_indexed() ) {
			$response = SP_API()->index_post( $post );

			if ( ! in_array( SP_API()->last_request['response_code'], array( 200, 201 ) ) ) {
				# Should probably throw an error here or something
				error_log( 'ES response failed' );
				error_log( print_r( SP_API()->last_request, 1 ) );
			} elseif ( ! is_object( $response ) || ! isset( $response->ok ) ) {
				error_log( 'ES response not OK' );
				error_log( print_r( $response, 1 ) );
			} else {
				# We're good
			}
		} else {
			# This is excessive, figure out a better way around it
			$this->delete_post( $post_id );
		}
	}


	public function delete_post( $post_id ) {
		$response = SP_API()->delete_post( $post_id );

		# We're OK with 404 responses here because a post might not be in the index
		if ( ! in_array( SP_API()->last_request['response_code'], array( 200, 404 ) ) ) {
			# Should probably throw an error here or something
			error_log( 'ES response failed' );
			error_log( print_r( SP_API()->last_request, 1 ) );
		} elseif ( ! is_object( $response ) || ! isset( $response->ok ) ) {
			error_log( 'ES response not OK' );
			error_log( print_r( $response, 1 ) );
		} else {
			# We're good
		}
	}


	/**
	 * Run the sync process
	 *
	 * @param int $start
	 * @param int $limit
	 * @return void
	 */
	public function sync( $start, $limit ) {
		if ( false !== ( $previous_sync = get_transient( 'sp_sync' ) ) ) {
			# Sync is running, or died. Do something about it.
			return $previous_sync;
		}

		set_transient( 'sp_sync', array( 'start' => $start, 'limit' => $limit ), HOUR_IN_SECONDS );

		$data = $this->get_range( $start, $limit );
		# Do something with $data

		delete_transient( 'sp_sync' );
	}



	/**
	 * Get all the posts in a given range and control for memory leaks
	 *
	 * @param int $start
	 * @param int $limit
	 * @return string JSON array
	 */
	public function get_range( $start, $limit ) {
		error_log( "Getting $limit posts starting at $start" );
		$posts = array();

		# Run the loop in batches to contain memory leaks
		while ( $limit > 0 ) {
			# Run at most $this->batch_size
			$ceil = ( $limit >= $this->batch_size ) ? $this->batch_size : $limit;
			// error_log( "Current batch: $ceil posts starting at $start" );
			$posts = $posts + $this->get_posts( array(
				'offset'         => $start,
				'posts_per_page' => $ceil
			) );
			$start += $ceil;
			$limit -= $ceil;
			$this->contain_memory_leaks();
		}
		return $posts;
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

		$query = new WP_Query;
		$posts = $query->query( $args );

		$this->published_posts = $query->found_posts;

		$indexed_posts = array();
		foreach ( $posts as $post ) {
			$indexed_posts[ $post->ID ] = new SP_Post( $post );
		}
		return $indexed_posts;
	}


	public function do_index_loop() {
		error_log( 'Looping!' );
		$sync_meta = SP_Sync_Meta();
		error_log( "Loaded sync_meta, page is {$sync_meta->page}" );

		$start = $sync_meta->page++ * $sync_meta->bulk;
		$posts = $this->get_range( $start, $sync_meta->bulk );

		if ( !$posts || is_wp_error( $posts ) )
			return false;

		$response = SP_API()->index_posts( $posts );
		// error_log( print_r( $response, 1 ) );
		$sync_meta->current_count = count( $posts );
		$sync_meta->processed += $sync_meta->current_count;

		if ( '200' != SP_API()->last_request['response_code'] ) {
			# Should probably throw an error here or something
			error_log( 'ES response failed' );
			$sync_meta->save();
			return false;
		} elseif ( ! is_object( $response ) || ! is_array( $response->items ) ) {
			error_log( "!!! Error! Response:\n" . print_r( $response, 1 ) );
		} else {
			foreach ( $response->items as $post ) {
				if ( ! isset( $post->index->ok ) || 1 != $post->index->ok ) {
					$error = "Error indexing post {$post->index->_id}: {$post->index->error}";
					error_log( $error );
					$sync_meta->messages[] = $error;
					$sync_meta->error++;
				} else {
					$sync_meta->success++;
				}
			}
		}

		error_log( "Saving sync_meta, page is {$sync_meta->page}" );

		if ( $sync_meta->processed >= $sync_meta->total ) {
			$sync_meta->running = false;
		}

		$sync_meta->save();
		return true;
	}


	public function do_cron_reindex() {
		SP_Sync_Meta()->total = $this->count_posts();
		SP_Sync_Meta()->start();
		SP_Cron()->schedule_reindex();
	}


	public function cancel_reindex() {
		SP_Cron()->cancel_reindex();
		SP_Sync_Meta()->delete();
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


	public function count_posts( $args = array() ) {
		if ( false === $this->published_posts ) {
			$args = wp_parse_args( $args, array(
				'post_type' => get_post_types( array( 'exclude_from_search' => false ) ),
				'post_status' => 'publish',
				'posts_per_page' => 1
			) );
			$query = new WP_Query( $args );
			$this->published_posts = $query->found_posts;
		}
		return $this->published_posts;
	}

}

function SP_Sync_Manager() {
	return SP_Sync_Manager::instance();
}


/**
 * SP_Sync_Manager only gets instantiated when necessary, so we register these hooks outside of the class
 */
add_action( 'save_post',       array( SP_Sync_Manager(), 'sync_post' ) );
add_action( 'delete_post',     array( SP_Sync_Manager(), 'delete_post' ) );
add_action( 'trashed_post',    array( SP_Sync_Manager(), 'delete_post' ) );


endif;