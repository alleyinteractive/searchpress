<?php

/**
 *
 */

if ( !class_exists( 'ES_Cron' ) ) :

class ES_Cron {

	private static $instance;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone ES_Cron" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup ES_Cron" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new ES_Cron;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		add_action( 'es_reindex', array( $this, 'reindex' ) );
	}

	public function reindex() {
		error_log( 'cron reindexing!' );
		ES_Sync_Manager()->do_index_loop();
		if ( ES_Sync_Meta()->running )
			$this->schedule_reindex();
	}

	public function schedule_reindex() {
		if ( ! wp_next_scheduled( 'es_reindex' ) ) {
			wp_schedule_single_event( time(), 'es_reindex' );
		}
	}

	public function cancel_reindex() {
		wp_clear_scheduled_hook( 'es_reindex' );
		ES_Sync_Meta()->delete();
	}
}

function ES_Cron() {
	return ES_Cron::instance();
}
if ( defined( 'DOING_CRON' ) && DOING_CRON )
	ES_Cron();

endif;