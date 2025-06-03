<?php

const WP_TESTS_PHPUNIT_POLYFILLS_PATH = __DIR__ . '/../vendor/yoast/phpunit-polyfills';

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function sp_manually_load_plugin() {
	// If your ES server is not at localhost:9200, you need to set $_ENV['SEARCHPRESS_HOST'].
	$host = getenv( 'SEARCHPRESS_HOST' );
	if ( empty( $host ) ) {
		$host = 'http://localhost:9200';
	}

	update_option( 'sp_settings', array(
		'host'      => $host,
		'must_init' => false,
		'active'    => true,
	) );
	require dirname( __FILE__ ) . '/../searchpress.php';

	SP_API()->index = 'searchpress-tests';

	// Make sure ES is running and responding
	$tries = 5;
	$sleep = 3;
	do {
		$response = wp_remote_get( $host );
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['version']['number'] ) ) {
				printf( "Elasticsearch is up and running, using version %s.\n", $body['version']['number'] );
			}
			break;
		} else {
			printf( "\nInvalid response from ES (%s), sleeping %d seconds and trying again...\n", wp_remote_retrieve_response_code( $response ), $sleep );
			sleep( $sleep );
		}
	} while ( --$tries );

	// If we didn't end with a 200 status code, exit
	sp_tests_verify_response_code( $response );

	sp_index_flush_data();

	$i = 0;
	while ( ! ( $beat = SP_Heartbeat()->check_beat( true ) ) && $i++ < 5 ) {
		echo "\nHeartbeat failed, sleeping 2 seconds and trying again...\n";
		sleep( 2 );
	}
	if ( ! $beat && ! SP_Heartbeat()->check_beat( true ) ) {
		echo "\nCould not find a heartbeat!";
		exit( 1 );
	}
}
tests_add_filter( 'muplugins_loaded', 'sp_manually_load_plugin' );

function sp_remove_index() {
	SP_Config()->flush();
}
tests_add_filter( 'shutdown', 'sp_remove_index' );

function sp_index_flush_data() {
	SP_Config()->flush();

	// Attempt to create the mapping.
	$response = SP_Config()->create_mapping();

	if ( ! empty( $response->error ) ) {
		echo "Could not create the mapping!\n";

		// Output error data.
		if ( ! empty( $response->error->code ) ) {
			printf( "Error code `%d`\n", $response->error->code );
		} elseif ( ! empty( $response->status ) ) {
			printf( "Error code `%d`\n", $response->status );
		}
		if ( ! empty( $response->error->message ) ) {
			printf( "Error message `%s`\n", $response->error->message );
		} elseif ( ! empty( $response->error->reason ) && ! empty( $response->error->type ) ) {
			printf( "Error: %s\n%s\n", $response->error->type, $response->error->reason );
		}
		exit( 1 );
	}

	SP_API()->post( '_refresh' );
}

function sp_tests_verify_response_code( $response ) {
	if ( '200' != wp_remote_retrieve_response_code( $response ) ) {
		printf( "Could not index posts!\nResponse code %s\n", wp_remote_retrieve_response_code( $response ) );
		if ( is_wp_error( $response ) ) {
			printf( "Message: %s\n", $response->get_error_message() );
		}
		exit( 1 );
	}
}

require $_tests_dir . '/includes/bootstrap.php';

// Load a reusable test case.
require_once( dirname( __FILE__ ) . '/class-searchpress-unit-test-case.php' );
