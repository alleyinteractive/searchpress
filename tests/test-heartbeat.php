<?php

/**
 * @group heartbeat
 */
class Tests_Heartbeat extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
	}

	function test_heartbeat_runs_automatically() {
		$this->assertTrue( wp_next_scheduled( 'sp_heartbeat' ) > 0 );
	}

	function test_heartbeat_reschedules_after_query() {
		wp_clear_scheduled_hook( 'sp_heartbeat' );
		wp_schedule_single_event( time(), 'sp_heartbeat' );
		$next_scheduled = wp_next_scheduled( 'sp_heartbeat' );
		SP_Heartbeat()->record_pulse();
		$this->assertGreaterThan( $next_scheduled, wp_next_scheduled( 'sp_heartbeat' ) );
	}

	function test_heartbeat_checks_post_count_automatically() {
	}

	function test_heartbeat_increases_frequency_after_error() {
		wp_clear_scheduled_hook( 'sp_heartbeat' );
		SP_Heartbeat()->record_pulse();
		$was_scheduled = wp_next_scheduled( 'sp_heartbeat' );

		// Change the host, re-check the heartbeat, and set the host back
		$host = SP_Config()->get_setting( 'host' );
		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com' ) );
		SP_API()->setup();
		$beat_result = SP_Heartbeat()->check_beat( true );
		SP_Config()->update_settings( array( 'host' => $host ) );
		SP_API()->setup();

		$this->assertFalse( $beat_result );
		$this->assertLessThan( $was_scheduled, wp_next_scheduled( 'sp_heartbeat' ) );
	}

	function test_heartbeat_decreases_frequency_after_error() {
	}

	function test_heartbeat_auto_deactivation() {
	}

	function test_searchpress_readiness() {
	}

}