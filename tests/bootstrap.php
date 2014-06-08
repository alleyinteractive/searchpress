<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../searchpress.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Load up SearchPress essentials
// remove_action( 'save_post',       array( SP_Sync_Manager(), 'sync_post' ) );
// remove_action( 'delete_post',     array( SP_Sync_Manager(), 'delete_post' ) );
// remove_action( 'trashed_post',    array( SP_Sync_Manager(), 'delete_post' ) );

function sp_index_test_data() {
	// If your ES server is not at localhost:9200, you need to set $_ENV['searchpress_host'].
	$host = ! empty( $_ENV['searchpress_host'] ) ? $_ENV['searchpress_host'] : 'http://localhost:9200';

	SP_Config()->update_settings( array( 'active' => false, 'host' => $host ) );
	SP_API()->index = 'searchpress-tests';

	SP_Config()->flush();
	SP_Config()->create_mapping();

	$posts = get_posts( 'posts_per_page=-1&post_type=any&post_status=any&orderby=ID&order=ASC' );

	$sp_posts = array();
	foreach ( $posts as $post ) {
		$sp_posts[] = new SP_Post( $post );
	}

	$response = SP_API()->index_posts( $sp_posts );
	if ( '200' != SP_API()->last_request['response_code'] ) {
		echo( "ES response not 200!\n" . print_r( $response, 1 ) );
	} elseif ( ! is_object( $response ) || ! is_array( $response->items ) ) {
		echo( "Error indexing data! Response:\n" . print_r( $response, 1 ) );
	}

	SP_Config()->update_settings( array( 'active' => true, 'must_init' => false ) );

	SP_API()->post( '_refresh' );
}

require $_tests_dir . '/includes/bootstrap.php';
