<?php

WP_CLI::add_command( 'searchpress', 'Searchpress_CLI_Command' );

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
	 * Replacing the site's default search with SearchPress; this hsould only be done after indexing.
	 *
	 * @subcommand activate
	 */
	public function activate() {
		WP_CLI::line( "Replacing the default search with SearchPress..." );
		SP_Config()->update_settings( array( 'active' => true, 'must_init' => false ) );
		SP_Sync_Meta()->delete( '', 'force' );
		WP_CLI::success( "Successfully activated SearchPress!\n" );
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
	 * Flush the current index
	 *
	 * @subcommand flush
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
	 * Index the site in elasticsearch, optionally flushing any existing data and adding the document mapping
	 *
	 * @subcommand index
	 * @synopsis [--flush] [--put-mapping] [--bulk=<num>] [--limit=<num>] [--page=<num>]
	 */
	public function index( $args, $assoc_args ) {
		$timestamp_start = microtime( true );

		if ( $assoc_args['flush'] ) {
			$this->flush();
		}

		if ( $assoc_args['put-mapping'] ) {
			$this->put_mapping();
		}

		$assoc_args = array_merge( array(
			'bulk'  => 2000,
			'limit' => 0,
			'page'  => 1
		), $assoc_args );

		if ( $assoc_args['limit'] && $assoc_args['limit'] < $assoc_args['bulk'] )
			$assoc_args['bulk'] = $assoc_args['limit'];

		$limit_number = $assoc_args['limit'] > 0 ? $assoc_args['limit'] : SP_Sync_Manager()->count_posts();
		$limit_text = sprintf( _n( '%s post', '%s posts', $limit_number ), number_format( $limit_number ) );
		WP_CLI::line( "Indexing {$limit_text}, " . number_format( $assoc_args['bulk'] ) . " at a time, starting on page {$assoc_args['page']}" );

		# Keep tabs on where we are and what we've done
		$sync_meta = SP_Sync_Meta();
		$sync_meta->page = intval( $assoc_args['page'] ) - 1;
		$sync_meta->bulk = $assoc_args['bulk'];
		$sync_meta->limit = $assoc_args['limit'];
		$sync_meta->running = true;

		$total_pages = $limit_number / $sync_meta->bulk;
		$total_pages_ceil = ceil( $total_pages );
		$start_page = $sync_meta->page;

		do {
			$lap = microtime( true );
			SP_Sync_Manager()->do_index_loop();

			$seconds_per_page = ( microtime( true ) - $timestamp_start ) / ( $sync_meta->page - $start_page );
			WP_CLI::line( "Completed page {$sync_meta->page}/{$total_pages_ceil} (" . number_format( ( microtime( true ) - $lap), 2 ) . 's / ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'M current / ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . 'M max), ' . $this->time_format( ( $total_pages - $sync_meta->page ) * $seconds_per_page ) . ' remaining' );

			$this->contain_memory_leaks();

			if ( $assoc_args['limit'] > 0 && $sync_meta->processed >= $assoc_args['limit'] )
				break;
		} while ( $sync_meta->page < $total_pages_ceil );


		WP_CLI::success( "Index complete!\n{$sync_meta->processed}\tposts processed\n{$sync_meta->success}\tposts added\n{$sync_meta->error}\tposts skipped" );

		$this->activate();

		$this->finish( $timestamp_start );
	}

	private function finish( $timestamp_start ) {
		WP_CLI::line( "Process completed in " . $this->time_format( microtime( true ) - $timestamp_start ) . "\nMax memory usage was " . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "M" );
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
		return $ret . ceil( $seconds ) . 's';
	}
}