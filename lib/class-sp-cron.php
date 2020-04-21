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

	protected $queue_index_event = 'sp_index';

	protected $reindex_event = 'sp_reindex';

	/**
	 * Setup the actions for this singleton.
	 */
	public function setup() {
		add_action( $this->queue_index_event, array( $this, 'queue_index' ) );
		add_action( $this->reindex_event, array( $this, 'reindex' ) );
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
		if ( ! wp_next_scheduled( $this->reindex_event ) ) {
			wp_schedule_single_event( time() + 5, $this->reindex_event );
		}
	}

	/**
	 * Cancel the indexing process.
	 */
	public function cancel_reindex() {
		wp_clear_scheduled_hook( $this->reindex_event );
		SP_Sync_Meta()->stop( 'save' );
	}

	/**
	 * Do an indexing iteration. This fires through the cron.
	 */
	public function queue_index() {
		do_action( 'sp_debug', '[SP Cron] Running index queue' );
		SP_Sync_Manager()->update_index_from_queue();
	}

	/**
	 * Schedule the next indexing iteration.
	 */
	public function schedule_queue_index() {
		if ( ! wp_next_scheduled( $this->queue_index_event ) ) {
			wp_schedule_single_event( time() + 5, $this->queue_index_event );
		}
	}

	/**
	 * Cancel the indexing process.
	 */
	public function cancel_queue_index() {
		wp_clear_scheduled_hook( $this->queue_index_event );
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
