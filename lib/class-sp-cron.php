<?php
/**
 * SearchPress library: SP_Cron class
 *
 * @package SearchPress
 */

/**
 * SP_Cron class. Handles cron actions for SearchPress.
 */
class SP_Cron extends SP_Singleton {

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

/**
 * Initializes and returns the SP_Cron instance.
 *
 * @return SP_Singleton The initialized SP_Cron instance.
 */
function SP_Cron() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return SP_Cron::instance();
}
add_action( 'after_setup_theme', 'SP_Cron', 20 );
