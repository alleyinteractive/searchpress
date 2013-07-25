<?php

/**
 * Simple class for working with the meta information associated with ES Syncing
 *
 * @author Matthew Boynes
 */
if ( !class_exists( 'ES_Sync_Meta' ) ) :

class ES_Sync_Meta {

	private static $instance;
	public $running       = false;
	public $start         = 0;
	public $bulk          = 500;
	public $limit         = false;
	public $page          = 0;
	public $total         = 0;
	public $processed     = 0;
	public $success       = 0;
	public $error         = 0;
	public $current_count = 0;
	public $messages      = array();

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone ES_Sync_Meta" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup ES_Sync_Meta" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new ES_Sync_Meta;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		if ( defined( 'ES_SYNC_CLI' ) && ES_SYNC_CLI )
			return;

		if ( false != ( $sync_meta = get_option( 'es_sync_meta' ) ) ) {
			foreach ( $sync_meta as $key => $value ) {
				$this->$key = $value;
			}
		}
	}


	public function start() {
		$this->running = true;
		$this->save();
	}


	public function save() {
		update_option( 'es_sync_meta', array(
			'running'   => $this->running,
			'start'     => $this->start,
			'bulk'      => $this->bulk,
			'limit'     => $this->limit,
			'page'      => $this->page,
			'total'     => $this->total,
			'processed' => $this->processed,
			'success'   => $this->success,
			'error'     => $this->error
		) );
	}


	public function delete() {
		delete_option( 'es_sync_meta' );
	}


	public function reload() {
		$this->setup();
	}

}

function ES_Sync_Meta() {
	return ES_Sync_Meta::instance();
}

endif;