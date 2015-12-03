<?php

WP_CLI::add_command( 'searchpress', 'Searchpress_CLI_Command' );

/**
 * CLI Commands for SearchPress
 */
class Searchpress_CLI_Command extends WP_CLI_Command {

	/**
	 * Prevent memory leaks from growing out of control
	 */
	private function contain_memory_leaks() {
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


	/**
	 * Replacing the site's default search with SearchPress; this should only be done after indexing.
	 *
	 */
	public function activate() {
		WP_CLI::line( "Replacing the default search with SearchPress..." );
		SP_Config()->update_settings( array( 'active' => true, 'must_init' => false ) );
		SP_Sync_Meta()->delete();
		WP_CLI::success( "Successfully activated SearchPress!\n" );
	}


	/**
	 * Deactivate SearchPress
	 *
	 */
	public function deactivate() {
		WP_CLI::line( "Deactivating SearchPress..." );
		SP_Config()->update_settings( array( 'active' => false, 'must_init' => true ) );
		SP_Sync_Meta()->delete();
		WP_CLI::success( "Successfully deactivated SearchPress!\n" );
	}


	/**
	 * Add the document mapping
	 *
	 * @subcommand put-mapping
	 */
	public function put_mapping() {
		WP_CLI::line( "Adding mapping..." );
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
	 * Flush the current index. !!Warning!! This empties your elasticsearch index for the entire site.
	 *
	 */
	public function flush() {
		WP_CLI::line( "Flushing current index..." );
		$result = SP_Config()->flush();
		if ( '200' == SP_API()->last_request['response_code'] || '404' == SP_API()->last_request['response_code'] ) {
			WP_CLI::success( "Successfully flushed Post index\n" );
		} else {
			print_r( SP_API()->last_request );
			print_r( $result );
			WP_CLI::error( "Could not flush existing data!" );
		}
	}

	/**
	 * Index the current site or individual posts in elasticsearch, optionally flushing any existing data and adding the document mapping.
	 *
	 * ## OPTIONS
	 *
	 * [--flush]
	 * : Flushes out the current data
	 *
	 * [--put-mapping]
	 * : Adds the document mapping in SP_Config()
	 *
	 * [--bulk=<num>]
	 * : Process this many posts as a time. Defaults to 2,000, which seems to
	 * be the fastest on average.
	 *
	 * [--limit=<num>]
	 * : How many posts to process. Defaults to all posts.
	 *
	 * [--page=<num>]
	 * : Which page to start on. This is helpful if you encountered an error on
	 * page 145/150 or if you want to have multiple processes running at once
	 *
	 * [<post-id>]
	 * : By default, this subcommand will query posts based on ID and pagination.
	 * Instead, you can specify one or more individual post IDs to process. Multiple
	 * post IDs should be space-delimited (see examples)
	 * If present, the --bulk, --limit, and --page arguments are ignored.
	 *
	 * ## EXAMPLES
	 *
	 *      # Flush the current document index, add the mapping, and index the whole site
	 *      wp searchpress index --flush --put-mapping
	 *
	 *      # Index the first 10 posts in the database
	 *      wp searchpress index --bulk=10 --limit=10
	 *
	 *      # Index the whole site starting on page 145
	 *      wp searchpress index --page=145
	 *
	 *      # Index a single post (post ID 12345)
	 *      wp searchpress index 12345
	 *
	 *      # Index six specific posts
	 *      wp searchpress index 12340 12341 12342 12343 12344 12345
	 *
	 *      # Index posts published between 11/1/2015 and 12/15/2015 inclusive
	 *      wp searchpress index --from=11012015 --to=12152015
	 *
	 *      # Index posts published after 11/1/2015
	 *      wp searchpress index --from=11012015
	 *
	 * @synopsis [--flush] [--put-mapping] [--bulk=<num>] [--limit=<num>] [--page=<num>] [--from=<mmddyyyy>] [--to=<mmddyyyy>] [<post-id>]
	 */
	public function index( $args, $assoc_args ) {
		ob_end_clean();

		$timestamp_start = microtime( true );

		if ( $assoc_args['flush'] ) {
			$this->flush();
		}

		if ( $assoc_args['put-mapping'] ) {
			$this->put_mapping();
		}

		if ( ! empty( $args ) ) {
			# Individual post indexing
			$num_posts = count( $args );
			WP_CLI::line( sprintf( _n( "Indexing %d post", "Indexing %d posts", $num_posts ), $num_posts ) );

			foreach ( $args as $post_id ) {
				$post_id = intval( $post_id );
				if ( ! $post_id )
					continue;

				WP_CLI::line( "Indexing post {$post_id}" );
				SP_Sync_Manager()->sync_post( $post_id );
			}
			WP_CLI::success( "Index complete!" );

		} else {
			# Bulk indexing

			$assoc_args = array_merge( array(
				'bulk'  => 2000,
				'limit' => 0,
				'page'  => 1
			), $assoc_args );

			if ( $assoc_args['limit'] && $assoc_args['limit'] < $assoc_args['bulk'] )
				$assoc_args['bulk'] = $assoc_args['limit'];

			if ( isset( $assoc_args['from'] ) || isset( $assoc_args['to'] ) ) {
				global $searchpress_cli_date_range;
				$searchpress_cli_date_range = array();
				if ( isset( $assoc_args['from'] ) ) {
					$searchpress_cli_date_range['from'] = $assoc_args['from'];
				}
				if ( isset( $assoc_args['to'] ) ) {
					$searchpress_cli_date_range['to'] = $assoc_args['to'];
				}
				add_filter( 'searchpress_index_loop_args', 'searchpress_cli_apply_date_range' );
			}

			$limit_number = $assoc_args['limit'] > 0 ? $assoc_args['limit'] : SP_Sync_Manager()->count_posts();
			$limit_text = sprintf( _n( '%s post', '%s posts', $limit_number ), number_format( $limit_number ) );
			WP_CLI::line( "Indexing {$limit_text}, " . number_format( $assoc_args['bulk'] ) . " at a time, starting on page {$assoc_args['page']}" );

			# Keep tabs on where we are and what we've done
			$sync_meta = SP_Sync_Meta();
			$sync_meta->page = intval( $assoc_args['page'] ) - 1;
			$sync_meta->bulk = $assoc_args['bulk'];
			$sync_meta->running = true;

			$total_pages = $limit_number / $sync_meta->bulk;
			$total_pages_ceil = ceil( $total_pages );
			$start_page = $sync_meta->page;

			do {
				$lap = microtime( true );
				SP_Sync_Manager()->do_index_loop();

				if ( 0 < ( $sync_meta->page - $start_page ) ) {
					$seconds_per_page = ( microtime( true ) - $timestamp_start ) / ( $sync_meta->page - $start_page );
					WP_CLI::line( "Completed page {$sync_meta->page}/{$total_pages_ceil} (" . number_format( ( microtime( true ) - $lap), 2 ) . 's / ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'M current / ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . 'M max), ' . $this->time_format( ( $total_pages - $sync_meta->page ) * $seconds_per_page ) . ' remaining' );
				}

				$this->contain_memory_leaks();

				if ( ( $assoc_args['limit'] > 0 && $sync_meta->processed >= $assoc_args['limit'] ) || 0 == $sync_meta->total ) {
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
	 * Index a single post in elasticsearch and output debugging information. You should enable SP_DEBUG and SAVEQUERIES before running this.
	 *
	 * @synopsis <post_id>
	 */
	public function debug( $args ) {
		if ( empty( $args[0] ) )
			WP_CLI::error( "Invalid post ID" );
		$post_id = intval( $args[0] );
		if ( ! $post_id )
			WP_CLI::error( "Invalid post ID" );

		global $wpdb;
		$timestamp_start = microtime( true );

		WP_CLI::line( "Indexing post {$post_id}" );

		SP_Sync_Manager()->sync_post( $post_id );

		WP_CLI::success( "Index complete!" );
		print_r( $wpdb->queries );

		$this->finish( $timestamp_start );
	}

	private function finish( $timestamp_start ) {
		WP_CLI::line( "Process completed in " . $this->time_format( microtime( true ) - $timestamp_start ) );
		WP_CLI::line( "Max memory usage was " . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "M" );
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

/**
 * Add date range when retrieving posts in bulk.
 * Dates need to be passed as mmddyyy. See synopsis for index function.
 * Written outside of the class so it's not listed as an executable command w/ 'wp help searchpress'
 *
 * @param $args array
 * @return $args array
 */
function searchpress_cli_apply_date_range( $args ) {
	global $searchpress_cli_date_range;
	$args['date_query'] = array(
		0 => array(
			'after' => array(
				'year'  => substr( $searchpress_cli_date_range['from'], - 4),
				'month' => substr( $searchpress_cli_date_range['from'], 0, 2 ),
				'day'   => substr( $searchpress_cli_date_range['from'], 2, 2 ),
			),
			'inclusive' => true,
		),
	);
	if ( isset ( $searchpress_cli_date_range['to'] ) ) {
		$args['date_query'][0]['before'] = array(
			'year'  => substr( $searchpress_cli_date_range['to'], - 4),
			'month' => substr( $searchpress_cli_date_range['to'], 0, 2 ),
			'day'   => substr( $searchpress_cli_date_range['to'], 2, 2 ),
		);
	}
	return $args;
}
