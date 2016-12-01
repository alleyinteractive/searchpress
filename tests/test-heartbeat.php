<?php

/**
 * @group heartbeat
 */
class Tests_Heartbeat extends SearchPress_UnitTestCase {

	function test_heartbeat_runs_automatically() {
		$beat_result = SP_Heartbeat()->check_beat( true );
		$this->assertTrue( $beat_result );
		$this->assertTrue( wp_next_scheduled( 'sp_heartbeat' ) > 0 );

		wp_clear_scheduled_hook( 'sp_heartbeat' );
		$this->assertFalse( wp_next_scheduled( 'sp_heartbeat' ) );
		wp_schedule_single_event( time(), 'sp_heartbeat', array( true ) );

		// Run the cron
		$pre_cron = time();
		sp_tests_fake_cron();
		$cron_time = time() - $pre_cron;

		$this->assertTrue( wp_next_scheduled( 'sp_heartbeat' ) > 0 );

		// The beat should have run and scheduled the next checkup. Here we make
		// sure that the next event is scheduled in one heartbeat interval from
		// now, while also accounting for however long the cron took.
		$this->assertGreaterThanOrEqual(
			time() + SP_Heartbeat()->intervals['heartbeat'] - $cron_time,
			wp_next_scheduled( 'sp_heartbeat' )
		);
	}

	function test_heartbeat_reschedules_after_query() {
		wp_schedule_single_event( time(), 'sp_heartbeat' );
		$next_scheduled = wp_next_scheduled( 'sp_heartbeat' );
		SP_Heartbeat()->record_pulse();
		$this->assertGreaterThan( $next_scheduled, wp_next_scheduled( 'sp_heartbeat' ) );
	}

	function test_heartbeat_increases_frequency_after_error() {
		SP_Heartbeat()->record_pulse();
		$was_scheduled = wp_next_scheduled( 'sp_heartbeat' );

		// Change the host, re-check the heartbeat, and set the host back
		$host = SP_Config()->get_setting( 'host' );
		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com' ) );
		$beat_result = SP_Heartbeat()->check_beat( true );
		SP_Config()->update_settings( array( 'host' => $host ) );

		$this->assertFalse( $beat_result );
		$this->assertLessThan( $was_scheduled, wp_next_scheduled( 'sp_heartbeat' ) );
	}

	function test_heartbeat_decreases_frequency_after_error_resolved() {
		SP_Heartbeat()->record_pulse();
		$was_scheduled = wp_next_scheduled( 'sp_heartbeat' );

		// Change the host, re-check the heartbeat, and set the host back
		$host = SP_Config()->get_setting( 'host' );

		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com' ) );
		$beat_result = SP_Heartbeat()->check_beat( true );
		SP_Config()->update_settings( array( 'host' => $host ) );

		$this->assertFalse( $beat_result );
		$increase_scheduled = wp_next_scheduled( 'sp_heartbeat' );
		$this->assertLessThan( $was_scheduled, $increase_scheduled );

		// The heartbeat should be working again, so we'll make sure that
		// checking the beat will reschedule the next check normally.
		$beat_result = SP_Heartbeat()->check_beat( true );
		$this->assertTrue( $beat_result );
		$this->assertGreaterThanOrEqual(
			$increase_scheduled + SP_Heartbeat()->intervals['heartbeat'] - SP_Heartbeat()->intervals['increase'],
			wp_next_scheduled( 'sp_heartbeat' )
		);
	}

	function test_heartbeat_status_escalation() {
		// Change the host, re-check the heartbeat, and set the host back
		$host = SP_Config()->get_setting( 'host' );
		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com' ) );

		update_option( 'sp_heartbeat', time() - SP_Heartbeat()->thresholds['alert'] );
		SP_Heartbeat()->get_last_beat( true );
		$alert_status = SP_Heartbeat()->get_status();
		$has_pulse_alert = SP_Heartbeat()->has_pulse();

		update_option( 'sp_heartbeat', time() - SP_Heartbeat()->thresholds['shutdown'] );
		SP_Heartbeat()->get_last_beat( true );
		$shutdown_status = SP_Heartbeat()->get_status();
		$has_pulse_shutdown = SP_Heartbeat()->has_pulse();

		SP_Config()->update_settings( array( 'host' => $host ) );

		$beat_result = SP_Heartbeat()->check_beat( true );
		SP_Heartbeat()->get_last_beat( true );
		$ok_status = SP_Heartbeat()->get_status();
		$has_pulse_ok = SP_Heartbeat()->has_pulse();

		$this->assertEquals( 'alert', $alert_status );
		$this->assertTrue( $has_pulse_alert );

		$this->assertEquals( 'shutdown', $shutdown_status );
		$this->assertFalse( $has_pulse_shutdown );

		$this->assertEquals( 'ok', $ok_status );
		$this->assertTrue( $has_pulse_ok );

		$this->assertTrue( $beat_result );
	}

	function test_searchpress_readiness() {
		$host = SP_Config()->get_setting( 'host' );
		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com' ) );

		update_option( 'sp_heartbeat', time() - SP_Heartbeat()->thresholds['shutdown'] );
		SP_Heartbeat()->get_last_beat( true );
		$shutdown_ready = apply_filters( 'sp_ready', null );

		SP_Config()->update_settings( array( 'host' => $host ) );

		SP_Heartbeat()->check_beat( true );
		SP_Heartbeat()->get_last_beat( true );
		$ok_ready = apply_filters( 'sp_ready', null );

		$this->assertFalse( $shutdown_ready );
		$this->assertTrue( $ok_ready );
	}

	public function test_ready_override() {
		$this->assertTrue( SP_Heartbeat()->is_ready( null ) );
		$this->assertFalse( SP_Heartbeat()->is_ready( false ) );
	}
}
