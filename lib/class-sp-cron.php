<?php

/**
 *
 */

class SP_Cron extends SP_Singleton {

	/**
	 * Setup the actions for this singleton.
	 */
	public function setup() {
		$this->schedule_misc_jobs();
		add_action( 'sp_reindex', array( $this, 'reindex' ) );
		add_action( 'sp_check_es_version', array( SP_Config(), 'update_version' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
	}

	/**
	 * Do an indexing iteration. This fires through the cron.
	 */
	public function reindex() {
		do_action( 'sp_debug', '[SP Cron] Reindexing' );
		SP_Sync_Manager()->do_index_loop();
		if ( SP_Sync_Meta()->running ) {
			$this->schedule_reindex();
		}
	}

	/**
	 * Schedule assorted cron jobs if they aren't already scheduled.
	 */
	protected function schedule_misc_jobs() {
		if ( ! wp_next_scheduled( 'sp_check_es_version' ) ) {
			wp_schedule_event( strtotime( 'sunday 3am' ), 'weekly', 'sp_check_es_version' );
		}
	}

	/**
	 * Add custom cron schedule intervals.
	 *
	 * @param  array $schedules Cron schedules.
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		if ( empty( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => 7 * DAY_IN_SECONDS,
				'display' => __( 'Weekly', 'searchpress' ),
			);
		}

		return $schedules;
	}

	/**
	 * Schedule the next indexing iteration.
	 */
	public function schedule_reindex() {
		if ( ! wp_next_scheduled( 'sp_reindex' ) ) {
			wp_schedule_single_event( time() + 5, 'sp_reindex' );
		}
	}

	/**
	 * Cancel the indexing process.
	 */
	public function cancel_reindex() {
		wp_clear_scheduled_hook( 'sp_reindex' );
		SP_Sync_Meta()->stop( 'save' );
	}
}

function SP_Cron() {
	return SP_Cron::instance();
}
add_action( 'after_setup_theme', 'SP_Cron' );
