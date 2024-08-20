<?php

WP_CLI::add_command( 'searchpress', 'SearchPress_CLI_Command' );

/**
 * Manage SearchPress through the command-line.
 *
 * ## EXAMPLES
 *
 *      $ wp searchpress activate
 *      Successfully activated SearchPress!
 *
 *      $ wp searchpress deactivate
 *      Successfully deactivated SearchPress!
 *
 *      $ wp searchpress put-mapping
 *      Successfully added mapping
 *
 *      # Flush the current document index, add the mapping, and index the whole site
 *      $ wp searchpress index --flush --put-mapping
 *
 *      # Index the first 10 posts in the database
 *      $ wp searchpress index --bulk=10 --limit=10
 *
 *      # Index the whole site starting on page 145
 *      $ wp searchpress index --page=145
 *
 *      # Index posts from specific post type(s)
 *      $ wp searchpress index --post-types=post,page
 *
 *      # Index a single post (post ID 12345)
 *      $ wp searchpress index 12345
 *
 *      # Index six specific posts
 *      $ wp searchpress index 12340 12341 12342 12343 12344 12345
 *
 *      # Index posts published between 11-1-2015 and 12-30-2015 (inclusive)
 *      $ wp searchpress index --after-date=2015-11-01 --before-date=2015-12-30
 */
class SearchPress_CLI_Command extends WP_CLI_Command {

	/**
	 * Query arguments.
	 *
	 * @var array
	 */
	public $query_args;

	/**
	 * Prevent memory leaks from growing out of control
	 */
	private function contain_memory_leaks() {
		global $wpdb, $wp_object_cache;
		$wpdb->queries = array();

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();
		if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset();
		}
	}

	/**
	 * Replacing the site's default search with SearchPress; this should only be done after indexing.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp searchpress activate
	 *     Successfully activated SearchPress!
	 */
	public function activate() {
		WP_CLI::log( 'Replacing the default search with SearchPress...' );
		SP_Config()->update_settings( array( 'active' => true, 'must_init' => false ) );
		SP_Sync_Meta()->delete();
		WP_CLI::success( "Successfully activated SearchPress!\n" );
	}

	/**
	 * Deactivate SearchPress.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp searchpress deactivate
	 *     Successfully deactivated SearchPress!
	 */
	public function deactivate() {
		WP_CLI::log( 'Deactivating SearchPress...' );
		SP_Config()->update_settings( array( 'active' => false, 'must_init' => true ) );
		SP_Sync_Meta()->delete();
		WP_CLI::success( "Successfully deactivated SearchPress!\n" );
	}

	/**
	 * Add the document mapping.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp searchpress put-mapping
	 *     Successfully added mapping
	 *
	 * @subcommand put-mapping
	 */
	public function put_mapping() {
		WP_CLI::log( 'Adding mapping...' );
		$result = SP_Config()->create_mapping();
		if ( '200' == SP_API()->last_request['response_code'] ) {
			WP_CLI::success( "Successfully added mapping\n" );
		} else {
			print_r( SP_API()->last_request );
			print_r( $result );
			WP_CLI::error( "Could not add post mapping!" );
		}
	}

	/**
	 * Flush the current index. !!Warning!! This empties your Elasticsearch index for the entire site.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp searchpress flush
	 *     Successfully flushed Post index
	 */
	public function flush() {
		WP_CLI::log( 'Flushing current index...' );
		$result = SP_Config()->flush();
		if ( '200' == SP_API()->last_request['response_code'] || '404' == SP_API()->last_request['response_code'] ) {
			WP_CLI::success( "Successfully flushed Post index\n" );
		} else {
			print_r( SP_API()->last_request );
			print_r( $result );
			WP_CLI::error( 'Could not flush existing data!' );
		}
	}

	/**
	 * Index the current site or individual posts in Elasticsearch, optionally flushing any existing data and adding the document mapping.
	 *
	 * ## OPTIONS
	 *
	 * [--flush]
	 * : Flushes out the current data.
	 *
	 * [--put-mapping]
	 * : Adds the document mapping in SP_Config().
	 *
	 * [--bulk=<num>]
	 * : Process this many posts as a time. Defaults to 2,000, which seems to
	 * be the fastest on average.
	 * ---
	 * default: 2000
	 * ---
	 *
	 * [--limit=<num>]
	 * : How many posts to process. Defaults to all posts.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--page=<num>]
	 * : Which page to start on. This is helpful if you encountered an error on
	 * page 145/150 or if you want to have multiple processes running at once
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--post-types=<types>]
	 * : Post types to index. Ex.: post,page.
	 *
	 * [--after-date=<date>]
	 * : Index posts published on or after this date. Date format: YYYY-MM-DD.
	 *
	 * [--before-date=<date>]
	 * : Index posts published on or before this date. Date format: YYYY-MM-DD.
	 *
	 * [<post-id>...]
	 * : By default, this subcommand will query posts based on ID and pagination.
	 * Instead, you can specify one or more individual post IDs to process. Multiple
	 * post IDs should be space-delimited (see examples)
	 * If present, the --bulk, --limit, and --page arguments are ignored.
	 *
	 * ## EXAMPLES
	 *
	 *      # Flush the current document index, add the mapping, and index the whole site
	 *      $ wp searchpress index --flush --put-mapping
	 *
	 *      # Index the first 10 posts in the database
	 *      $ wp searchpress index --bulk=10 --limit=10
	 *
	 *      # Index the whole site starting on page 145
	 *      $ wp searchpress index --page=145
	 *
	 *      # Index posts from specific post type(s)
	 *      $ wp searchpress index --post-types=post,page
	 *
	 *      # Index a single post (post ID 12345)
	 *      $ wp searchpress index 12345
	 *
	 *      # Index six specific posts
	 *      $ wp searchpress index 12340 12341 12342 12343 12344 12345
	 *
	 *      # Index posts published between 11-1-2015 and 12-30-2015 (inclusive)
	 *      $ wp searchpress index --after-date=2015-11-01 --before-date=2015-12-30
	 *
	 *      # Index posts published after 11-1-2015 (inclusive)
	 *      $ wp searchpress index --after-date=2015-11-01
	 *
	 * @synopsis [--flush] [--put-mapping] [--bulk=<num>] [--limit=<num>] [--page=<num>] [--post-types=<types>] [--after-date=<date>] [--before-date=<date>] [<post-id>...]
	 */
	public function index( $args, $assoc_args ) {
		if ( false !== ob_get_length() ) {
			ob_end_clean();
		}

		$timestamp_start = microtime( true );

		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'flush' ) ) {
            WP_CLI::confirm( 'Are you sure you want to delete the Elasticsearch index?' );
			$this->flush();
		}

		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'put-mapping' ) ) {
			$this->put_mapping();
		}

		// Individual post indexing.
		if ( ! empty( $args ) ) {
			$num_posts = count( $args );

			WP_CLI::log( sprintf( _n( 'Indexing %d post', 'Indexing %d posts', $num_posts ), number_format( $num_posts ) ) );

			foreach ( $args as $post_id ) {
				$post_id = intval( $post_id );

				if ( empty( $post_id ) ) {
					continue;
				}

				$post = get_post( $post_id );

				if ( ! $post instanceof WP_Post || empty( $post->ID ) ) {
					WP_CLI::log( "Post {$post_id} does not exist." );
					continue;
				}

				WP_CLI::log( "Indexing post {$post_id}" );

				SP_Sync_Manager()->sync_post( $post_id );
			}

			WP_CLI::success( 'Index complete!' );

		} else {

			// Bulk indexing.

			if ( $assoc_args['limit'] && $assoc_args['limit'] < $assoc_args['bulk'] ) {
				$assoc_args['bulk'] = $assoc_args['limit'];
			}

			if ( isset( $assoc_args['after-date'] ) || isset( $assoc_args['before-date'] ) || isset( $assoc_args['post-types'] ) ) {
				$this->query_args = array();

				if ( ! empty( $assoc_args['after-date'] ) ) {
					$this->query_args['after'] = $assoc_args['after-date'];
				}

				if ( ! empty( $assoc_args['before-date'] ) ) {
					$this->query_args['before'] = $assoc_args['before-date'];
				}

				if ( ! empty( $assoc_args['post-types'] ) ) {
					$this->query_args['types'] = explode( ',', $assoc_args['post-types'] );
				}

				add_filter( 'searchpress_index_loop_args', array( $this, '__apply_searchpress_query_args' ) );
				add_filter( 'searchpress_index_count_args', array( $this, '__apply_searchpress_query_args' ) );
			}

			$limit_number = $assoc_args['limit'] > 0 ? $assoc_args['limit'] : SP_Sync_Manager()->count_posts();
			$limit_text   = sprintf( _n( '%s post', '%s posts', $limit_number ), number_format( $limit_number ) );

			WP_CLI::log(
				sprintf(
					'Indexing %1$s, %2$d at a time, starting on page %3$d',
					$limit_text,
					number_format( $assoc_args['bulk'] ),
					absint( $assoc_args['page'] )
				)
			);

			// Keep tabs on where we are and what we've done.
			$sync_meta          = SP_Sync_Meta();
			$sync_meta->page    = intval( $assoc_args['page'] ) - 1;
			$sync_meta->bulk    = $assoc_args['bulk'];
			$sync_meta->running = true;

			$total_pages      = $limit_number / $sync_meta->bulk;
			$total_pages_ceil = ceil( $total_pages );
			$start_page       = $sync_meta->page;

			do {
				$lap = microtime( true );
				SP_Sync_Manager()->do_index_loop();

				if ( 0 < ( $sync_meta->page - $start_page ) ) {
					$seconds_per_page = ( microtime( true ) - $timestamp_start ) / ( $sync_meta->page - $start_page );
					WP_CLI::log( "Completed page {$sync_meta->page}/{$total_pages_ceil} (" . number_format( ( microtime( true ) - $lap), 2 ) . 's / ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'M current / ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . 'M max), ' . $this->time_format( ( $total_pages - $sync_meta->page ) * $seconds_per_page ) . ' remaining' );
				}

				$this->contain_memory_leaks();

				if ( $assoc_args['limit'] > 0 && $sync_meta->processed >= $assoc_args['limit'] ) {
					break;
				}
			} while ( $sync_meta->page < $total_pages_ceil );

			$errors = ! empty( $sync_meta->messages['error'] ) ? count( $sync_meta->messages['error'] ) : 0;
			$errors += ! empty( $sync_meta->messages['warning'] ) ? count( $sync_meta->messages['warning'] ) : 0;

			WP_CLI::success( sprintf(
				__( "Index Complete!\n%d\tposts processed\n%d\tposts indexed\n%d\terrors/warnings", 'searchpress' ),
				$sync_meta->processed,
				$sync_meta->success,
				$errors
			) );

			$this->activate();
		}

		$this->finish( $timestamp_start );
	}

	/**
	 * Index a single post in Elasticsearch and output debugging information. You should enable `SP_DEBUG` and `SAVEQUERIES` before running this.
	 *
	 * <post_id>
	 * : Post ID to index.
	 *
	 * ## EXAMPLE
	 *
	 *     $ wp searchpress debug 123
	 *     Index complete!
	 */
	public function debug( $args ) {
		global $wpdb;

		$post_id = intval( $args[0] );

		if ( empty( $post_id ) ) {
			WP_CLI::error( 'Invalid post ID.' );
		}

		if ( ! defined( 'SP_DEBUG') ) {
			WP_CLI::warning( 'SP_DEBUG should be enabled before running this.' );
		}

		if ( ! defined( 'SAVEQUERIES') ) {
			WP_CLI::warning( 'SAVEQUERIES should be enabled before running this.' );
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || empty( $post->ID ) ) {
			WP_CLI::error( 'Post does not exist.' );
		}

		$timestamp_start = microtime( true );

		WP_CLI::log( "Indexing post {$post_id}" );

		SP_Sync_Manager()->sync_post( $post_id );

		WP_CLI::success( 'Index complete!' );

		print_r( $wpdb->queries );

		$this->finish( $timestamp_start );
	}

	/**
	 * Set custom SearchPress query args.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function __apply_searchpress_query_args( $args ) {

		// Set date query.
		if ( isset( $this->query_args['after'] ) || isset( $this->query_args['before'] ) ) {
			$args['date_query'] = array(
				0 => array(
					'inclusive' => true,
				),
			);

			if ( isset( $this->query_args['after'] ) ) {
				$from                           = strtotime( $this->query_args['after'] );
				$args['date_query'][0]['after'] = array(
					'year'  => date( 'Y', $from ),
					'month' => date( 'm', $from ),
					'day'   => date( 'd', $from ),
				);
			}

			if ( isset( $this->query_args['before'] ) ) {
				$to                              = strtotime( $this->query_args['before'] );
				$args['date_query'][0]['before'] = array(
					'year'  => date( 'Y', $to ),
					'month' => date( 'm', $to ),
					'day'   => date( 'd', $to ),
				);
			}
		}

		// Set post types.
		if ( isset( $this->query_args['types'] ) ) {
			$args['post_type'] = $this->query_args['types'];
		}

		return $args;
	}

	/**
	 * Finish helper.
	 *
	 * @param int $timestamp_start Start timestamp.
	 */
	private function finish( $timestamp_start ) {
		WP_CLI::log( "Process completed in " . $this->time_format( microtime( true ) - $timestamp_start ) );
		WP_CLI::log( "Max memory usage was " . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "M" );
	}

	private function time_format( $seconds ) {
		$ret = '';
		if ( $seconds > DAY_IN_SECONDS ) {
			$days = floor( $seconds / DAY_IN_SECONDS );
			$ret .= $days . 'd';
			$seconds -= $days * DAY_IN_SECONDS;
		}
		if ( $seconds > HOUR_IN_SECONDS ) {
			$hours = floor( $seconds / HOUR_IN_SECONDS );
			$ret .= $hours . 'h';
			$seconds -= $hours * HOUR_IN_SECONDS;
		}
		if ( $seconds > MINUTE_IN_SECONDS ) {
			$minutes = floor( $seconds / MINUTE_IN_SECONDS );
			$ret .= $minutes . 'm';
			$seconds -= $minutes * MINUTE_IN_SECONDS;
		}
		return $ret . absint( ceil( $seconds ) ) . 's';
	}
}
