<?php

/**
 * Simple class for working with the meta information associated with ES Syncing.
 * Many of the methods are disabled if we're in the WP-CLI environment, but this
 * class is still used to track the meta data (for consistency)
 *
 * @author Matthew Boynes
 */
if ( !class_exists( 'SP_Sync_Meta' ) ) :

class SP_Sync_Meta {

	private static $instance;
	public $running;
	public $start;
	public $bulk;
	public $limit;
	public $page;
	public $total;
	public $processed;
	public $success;
	public $error;
	public $current_count;
	public $messages;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __clone() { wp_die( "Please don't __clone SP_Sync_Meta" ); }

	/**
	 * @codeCoverageIgnore
	 */
	public function __wakeup() { wp_die( "Please don't __wakeup SP_Sync_Meta" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Sync_Meta;
			self::$instance->setup();
		}
		return self::$instance;
	}


	public function setup() {
		$this->init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( false != ( $sync_meta = get_option( 'sp_sync_meta' ) ) ) {
			foreach ( $sync_meta as $key => $value ) {
				$this->$key = $value;
			}
		}
	}


	private function init() {
		$this->running       = false;
		$this->start         = 0;
		$this->bulk          = 500;
		$this->limit         = false;
		$this->page          = 0;
		$this->total         = 0;
		$this->processed     = 0;
		$this->success       = 0;
		$this->error         = 0;
		$this->current_count = 0;
		$this->messages      = array();
	}


	public function start() {
		$this->running = true;
		$this->save();
	}


	public function save( $force = false ) {
		if ( 'force' != $force && defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		$this->delete();
		add_option( 'sp_sync_meta', array(
			'running'   => $this->running,
			'start'     => $this->start,
			'bulk'      => $this->bulk,
			'limit'     => $this->limit,
			'page'      => $this->page,
			'total'     => $this->total,
			'processed' => $this->processed,
			'success'   => $this->success,
			'error'     => $this->error
		), '', 'no' );
	}


	public function delete( $do_init = false, $force = false ) {
		if ( ! 'force' == $force && defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		delete_option( 'sp_sync_meta' );
		if ( 'init' == $do_init ) {
			$this->init();
		}
	}


	public function reload() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		wp_cache_delete( 'sp_sync_meta', 'options' );
		$this->setup();
	}

}

function SP_Sync_Meta() {
	return SP_Sync_Meta::instance();
}

endif;