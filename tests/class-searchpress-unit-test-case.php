<?php

class SearchPress_UnitTestCase extends WP_UnitTestCase {
	protected $sp_settings;

	public function setUp() {
		parent::setUp();

		sp_index_flush_data();
		SP_Cron()->setup();
		wp_clear_scheduled_hook( 'sp_heartbeat' );
		$this->sp_settings = SP_Config()->get_settings();
	}

	public function tearDown() {
		SP_Config()->update_settings( $this->sp_settings );
		SP_Sync_Meta()->reset( 'save' );
		SP_Sync_Manager()->published_posts = false;
		sp_index_flush_data();

		$this->reset_post_types();
		$this->reset_taxonomies();
		$this->reset_post_statuses();
		SP_Config()->post_types = null;
		SP_Config()->post_statuses = null;
		sp_searchable_post_types( true );
		sp_searchable_post_statuses( true );

		SP_Heartbeat()->record_pulse();
		wp_clear_scheduled_hook( 'sp_reindex' );
		wp_clear_scheduled_hook( 'sp_heartbeat' );

		parent::tearDown();
	}

	function search_and_get_field( $args, $field = 'post_name' ) {
		$args = wp_parse_args( $args, array(
			'fields' => $field
		) );
		$posts = sp_wp_search( $args, true );
		return sp_results_pluck( $posts, $field );
	}

	/**
	 * Fakes a cron job
	 */
	protected function fake_cron() {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return;
		}

		foreach ( $crons as $timestamp => $cronhooks ) {
			if ( ! is_array( $cronhooks ) ) {
				continue;
			}

			foreach ( $cronhooks as $hook => $keys ) {
				if ( substr( $hook, 0, 3 ) !== 'sp_' ) {
					continue; // only run our own jobs.
				}

				foreach ( $keys as $k => $v ) {
					$schedule = $v['schedule'];

					if ( $schedule != false ) {
						$new_args = array( $timestamp, $schedule, $hook, $v['args'] );
						call_user_func_array( 'wp_reschedule_event', $new_args );
					}

					wp_unschedule_event( $timestamp, $hook, $v['args'] );
					do_action_ref_array( $hook, $v['args'] );
				}
			}
		}
	}

	/**
	 * Is the current version of WordPress at least ... ?
	 *
	 * @param  float $min_version Minimum version required, e.g. 3.9.
	 * @return bool True if it is, false if it isn't.
	 */
	protected function is_wp_at_least( $min_version ) {
		global $wp_version;
		return floatval( $wp_version ) >= $min_version;
	}
}
