<?php

/**
 *
 */

if ( !class_exists( 'SP_Cron' ) ) :

class SP_Cron {

	private static $instance;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __clone() { wp_die( "Please don't __clone SP_Cron" ); }

	/**
	 * @codeCoverageIgnore
	 */
	public function __wakeup() { wp_die( "Please don't __wakeup SP_Cron" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Cron;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		add_action( 'sp_reindex', array( $this, 'reindex' ) );
	}

	public function reindex() {
		// error_log( 'cron reindexing!' );
		SP_Sync_Manager()->do_index_loop();
		if ( SP_Sync_Meta()->running )
			$this->schedule_reindex();
	}

	public function schedule_reindex() {
		if ( ! wp_next_scheduled( 'sp_reindex' ) ) {
			wp_schedule_single_event( time(), 'sp_reindex' );
		}
	}

	public function cancel_reindex() {
		wp_clear_scheduled_hook( 'sp_reindex' );
		SP_Sync_Meta()->delete( 'init' );
	}
}

function SP_Cron() {
	return SP_Cron::instance();
}
if ( defined( 'DOING_CRON' ) && DOING_CRON )
	SP_Cron();

endif;