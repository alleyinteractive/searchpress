<?php

# Idiot-check your arguments in $argv here, if applicable

# Run the script longer than the max execution time (may need to adjust for final file based on measured execution time)
if( !ini_get('safe_mode') )
	@ini_set( 'max_execution_time', 100000 );

# Extend the memory limit for the script to be on the safe side
@ini_set( 'memory_limit', '1024M' );
define( 'WP_MAX_MEMORY_LIMIT', '1024M' );
define( 'WP_DEBUG', true );

# Output everything as it happens rather than waiting until the script finishes
@ob_end_flush();

$_SERVER = array(
    "HTTP_HOST" => "http://your-hostname.com",
    "SERVER_NAME" => "http://your-hostname.com",
    "REQUEST_URI" => "/",
    "REQUEST_METHOD" => "GET"
);

# Load the WordPress environment
if ( file_exists( '../../../../wp-load.php' ) )
	require_once( '../../../../wp-load.php' );
elseif ( file_exists( 'wp-load.php' ) )
	require_once( 'wp-load.php' );
else
	die( "I couldn't find WordPress!\n" );

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
	# global $argv;

	# Note the start time and keep track of how many fields have been converted for script output
	$timestamp_start = microtime( true );

	# Keep tabs on where we are and what we've done
	$processed = $page = 0;
	do {
		$posts = get_posts( array(
			'post_type'      => 'any',
			'posts_per_page' => 1000,
			'post_status'    => 'publish',
			'offset'         => 1000 * $page++
		) );
		if ( !$posts || is_wp_error( $posts ) )
			break;

		foreach ( $posts as $post ) {
			# Do something to the post
			echo "Something about what I did to the post\n";
			$processed++;
		}

		contain_memory_leaks();
		echo "\nCompleted page $page\nCurrent memory usage is " . round( memory_get_usage() / 1024 / 1024, 2 ) . "M\n";
	} while ( 1000 == count( $posts ) );

	echo "Process complete!\n{$processed}\tposts updated\n";
	echo "Finished update in " . number_format( (microtime( true ) - $timestamp_start), 2 ) . " seconds\n\n";
}