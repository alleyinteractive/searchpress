<?php

/**
 *
 */

class SP_Cron {

	private static $instance;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Cron;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Setup the actions for this singleton.
	 */
	public function setup() {
		add_action( 'sp_reindex', array( $this, 'reindex' ) );
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

if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
	SP_Cron();
}
