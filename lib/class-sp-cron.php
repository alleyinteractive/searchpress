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
	 * WordPress Cron event for the indexing queue.
	 */
	const QUEUE_INDEX_EVENT = 'sp_index';

	/**
	 * WordPress Cron event for reindexing.
	 */
	const REINDEX_EVENT = 'sp_reindex';

	/**
	 * Setup the actions for this singleton.
	 */
	public function setup() {
		add_action( self::QUEUE_INDEX_EVENT, array( $this, 'queue_index' ) );
		add_action( self::REINDEX_EVENT, array( $this, 'reindex' ) );
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
		if ( ! wp_next_scheduled( self::REINDEX_EVENT ) ) {
			wp_schedule_single_event( time() + 5, self::REINDEX_EVENT );
		}
	}

	/**
	 * Cancel the indexing process.
	 */
	public function cancel_reindex() {
		wp_clear_scheduled_hook( self::REINDEX_EVENT );
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
		if ( ! wp_next_scheduled( self::QUEUE_INDEX_EVENT ) ) {
			wp_schedule_single_event( time() + 5, self::QUEUE_INDEX_EVENT );
		}
	}

	/**
	 * Cancel the indexing process.
	 */
	public function cancel_queue_index() {
		wp_clear_scheduled_hook( self::QUEUE_INDEX_EVENT );
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
