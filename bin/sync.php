<?php

define( 'SP_CLI', true );

$options = getopt( '', array(
	'flush::',
	'put-mapping::',
	'index::',
	'bulk:',
	'limit:',
	'page:',
	'sp-host:'
) );
$options = array_merge( array(
	'bulk' => 1000,
	'limit' => 0,
	'page' => 0
), $options );

if ( $options['limit'] && $options['limit'] < $options['bulk'] )
	$options['bulk'] = $options['limit'];

# Run the script longer than the max execution time (may need to adjust for final file based on measured execution time)
if( !ini_get('safe_mode') )
	@ini_set( 'max_execution_time', 100000 );

# Extend the memory limit for the script to be on the safe side
@ini_set( 'memory_limit', '1024M' );
define( 'WP_MAX_MEMORY_LIMIT', '1024M' );
define( 'WP_DEBUG', true );

$_SERVER = array_merge( $_SERVER, array(
    "HTTP_HOST"       => "kff.wp.alley.boyn.es",
    "SERVER_NAME"     => "kff.wp.alley.boyn.es",
    "REQUEST_URI"     => "/",
    "REQUEST_METHOD"  => "GET",
    "SERVER_PROTOCOL" => "HTTP/1.1"
) );

if ( isset( $_SERVER['PWD'] ) && strpos( $_SERVER['PWD'], '/wp-content/' ) ) {
	$wp_path = $_SERVER['PWD'];
} else {
	$wp_path = __DIR__;
}
$wp_path = preg_replace( '#wp-content/.*$#', 'wp-load.php', $wp_path );

# Load the WordPress environment
if ( file_exists( $wp_path ) )
	require_once( $wp_path );
elseif ( file_exists( 'wp-load.php' ) )
	require_once( 'wp-load.php' );
else
	die( "I couldn't find WordPress!\n" );


# Output everything as it happens rather than waiting until the script finishes
@ob_end_flush();
@ob_implicit_flush( true );

if ( isset( $options['sp-host'] ) )
	SP_API()->host = $options['sp-host'];

# Run the script
exit( main() );


/**
 * Prevent memory leaks from growing out of control
 */
function contain_memory_leaks() {
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
 * The Script(TM)
 */
function main() {
	# Uncomment this if your script has arguments:
	global $options;

	# Note the start time and keep track of how many fields have been converted for script output
	$timestamp_start = microtime( true );

	if ( isset( $options['flush'] ) ) {
		$result = SP_Config()->flush();
		if ( '200' == SP_API()->last_request['response_code'] ) {
			echo "Successfully flushed Post index\n\n";
		} else {
			print_r( SP_API()->last_request );
			print_r( $result );
		}
	}

	if ( isset( $options['put-mapping'] ) ) {
		$result = SP_Config()->create_mapping();
		if ( '200' == SP_API()->last_request['response_code'] ) {
			echo "Successfully added Post mapping\n\n";
		} else {
			print_r( SP_API()->last_request );
			print_r( $result );
		}
	}

	if ( isset( $options['index'] ) ) {
		# Keep tabs on where we are and what we've done
		$sync_meta = SP_Sync_Meta();
		$sync_meta->page = $options['page'];
		$sync_meta->bulk = $options['bulk'];
		$sync_meta->limit = $options['limit'];
		do {

			SP_Sync_Manager()->do_index_loop();

			echo "\nCompleted page {$sync_meta->page}\nCurrent memory usage is " . round( memory_get_usage() / 1024 / 1024, 2 ) . "M / " . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "\n";

			if ( $options['limit'] > 0 && $sync_meta->processed >= $options['limit'] )
				break;

		} while ( $options['bulk'] == $sync_meta->current_count );

		echo "Process complete!\n{$sync_meta->processed}\tposts processed\n{$sync_meta->success}\tposts added\n{$sync_meta->error}\tposts skipped\n";
	}
	echo "Finished update in " . number_format( (microtime( true ) - $timestamp_start), 2 ) . " seconds\nMax memory usage was " . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "M\n\n";
}