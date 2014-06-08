<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../searchpress.php';

	// If your ES server is not at localhost:9200, you need to set $_ENV['searchpress_host'].
	$host = ! empty( $_ENV['searchpress_host'] ) ? $_ENV['searchpress_host'] : 'http://localhost:9200';
	SP_Config()->update_settings( array( 'active' => true, 'must_init' => false, 'host' => $host ) );
	SP_API()->index = 'searchpress-tests';

	// These were not added because SP wasn't active when SP_Sync_Manager was loaded
	add_action( 'save_post',    array( SP_Sync_Manager(), 'sync_post' ) );
	add_action( 'delete_post',  array( SP_Sync_Manager(), 'delete_post' ) );
	add_action( 'trashed_post', array( SP_Sync_Manager(), 'delete_post' ) );

	sp_index_flush_data();
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

function sp_index_flush_data() {
	SP_Config()->flush();
	SP_Config()->create_mapping();
}


require $_tests_dir . '/includes/bootstrap.php';
