<?php
/**
 * SearchPress library: SP_Sync_Meta class
 *
 * @package SearchPress
 */

/**
 * Simple class for working with the meta information associated with ES Syncing.
 * Many of the methods are disabled if we're in the WP-CLI environment, but this
 * class is still used to track the meta data (for consistency)
 */
class SP_Sync_Meta extends SP_Singleton {

	/**
	 * Stores information about the current or most recent bulk sync.
	 *
	 * @access protected
	 * @var array $data {
	 *     @type bool  $running   Is the sync currently running? Default false.
	 *     @type int   $started   What time the sync started. Default 0.
	 *     @type int   $finished  What time the sync finished. Default 0.
	 *     @type int   $bulk      How many posts to process at a time. Default 500.
	 *     @type int   $page      What "page" the sync is on. Default 0.
	 *     @type int   $total     The total number of posts to be synced. Default 0.
	 *     @type int   $processed Total number of posts processed. Default 0.
	 *     @type int   $success   Number of posts successfully synced. Default 0.
	 *     @type array $messages  Messages from the sync. Default array().
	 * }
	 */
	protected $data = array();

	/**
	 * Store errors from routine syncing.
	 *
	 * @access protected
	 * @var string
	 */
	protected $error_transient = 'sp_sync_meta_error';

	/**
	 * Setup the singleton; initialize and load the data.
	 */
	public function setup() {
		$this->init();

		if ( $this->is_cli() ) {
			return;
		}

		$sync_meta = get_option( 'sp_sync_meta' );
		if ( ! empty( $sync_meta ) && is_array( $sync_meta ) ) {
			foreach ( $sync_meta as $key => $value ) {
				$this->data[ $key ] = $value;
			}
		}
	}

	/**
	 * Set the data defaults.
	 */
	private function init() {
		$this->data = array(
			'running'   => false,   // Is the sync currently running?
			'started'   => 0,
			'finished'  => 0,
			'bulk'      => 500,
			'page'      => 0,
			'total'     => 0,
			'processed' => 0,
			'success'   => 0,
			'messages'  => array(),
		);
	}

	/**
	 * Trigger that the sync has started.
	 *
	 * @param  string $save Optional. Should we immediately save the meta?
	 *                       Defaults to false.
	 */
	public function start( $save = null ) {
		$this->init();
		$this->data['running'] = true;
		$this->data['started'] = time();
		if ( 'save' === $save ) {
			$this->save();
		}
	}

	/**
	 * Trigger that the sync has stopped.
	 *
	 * @param  string $save Optional. Should we immediately save the meta?
	 *                       Defaults to false.
	 */
	public function stop( $save = null ) {
		$this->data['running']  = false;
		$this->data['finished'] = time();
		if ( 'save' === $save ) {
			$this->save();
		}
	}

	/**
	 * Save the options.
	 */
	public function save() {
		if ( $this->is_cli() ) {
			return;
		}

		update_option( 'sp_sync_meta', $this->data, false );
	}

	/**
	 * Delete the options.
	 */
	public function delete() {
		delete_option( 'sp_sync_meta' );
		$this->init();
	}

	/**
	 * Reload the options and be sure it's not from cache.
	 */
	public function reload() {
		if ( $this->is_cli() ) {
			return;
		}

		wp_cache_delete( 'sp_sync_meta', 'options' );
		$this->setup();
	}

	/**
	 * Reset the sync meta back to defaults.
	 *
	 * @param string $save Whether we should save the reset data.
	 */
	public function reset( $save = null ) {
		$this->init();
		if ( 'save' === $save ) {
			$this->save();
		}
	}

	/**
	 * Log a message about the syncing process.
	 *
	 * @param  WP_Error $error While the message may not be an "error" per se,
	 *                         this uses WP_Error to keep organized.
	 */
	public function log( WP_Error $error ) {
		if ( ! wp_doing_cron() && defined( 'WP_CLI' ) && WP_CLI ) {
			$method = $error->get_error_code();
			if ( ! in_array( $method, array( 'success', 'warning', 'error' ), true ) ) {
				$method = 'line';
			}
			$message = $error->get_error_data() ? $error->get_error_message() . '; Data: ' . wp_json_encode( $error->get_error_data() ) : $error->get_error_message();
			call_user_func( array( 'WP_CLI', $method ), $message );
			$this->data['messages'][ $error->get_error_code() ][] = $message;
		} else {
			/**
			 * Filter the log message for the error.
			 *
			 * @param string   $message Log message
			 * @param WP_Error $error Error instance.
			 */
			$this->data['messages'][ $error->get_error_code() ][] = apply_filters( 'sp_log_message', $error->get_error_message(), $error );

			set_transient( $this->error_transient, true );
			if ( ! $this->data['running'] ) {
				$this->save();
			}
		}
	}

	/**
	 * Clear the messages log.
	 */
	public function clear_log() {
		$this->data['messages'] = array();
		$this->save();
	}

	/**
	 * Get one of the sync meta properties.
	 *
	 * @param  string $name Sync meta key.
	 * @return mixed
	 */
	public function __get( $name ) {
		if ( isset( $this->data[ $name ] ) ) {
			return $this->data[ $name ];
		} else {
			return new WP_Error( 'invalid', __( 'Invalid property', 'searchpress' ) );
		}
	}

	/**
	 * Set one of the sync meta properties.
	 *
	 * @param string $name Sync meta key.
	 * @param mixed  $value Sync meta value.
	 */
	public function __set( $name, $value ) {
		if ( isset( $this->data[ $name ] ) ) {
			$this->data[ $name ] = $value;
		}
	}

	/**
	 * Overloaded isset.
	 *
	 * @param  string $name Sync meta key.
	 * @return boolean If the key exists or not.
	 */
	public function __isset( $name ) {
		return isset( $this->data[ $name ] );
	}

	/**
	 * Are we using WP_CLI right now?
	 *
	 * @access protected
	 *
	 * @return bool
	 */
	protected function is_cli() {
		return ( defined( 'WP_CLI' ) && WP_CLI && ! wp_doing_cron() );
	}

	/**
	 * Do we have non-full-sync errors?
	 *
	 * @return boolean True if we do, false if we don't.
	 */
	public function has_errors() {
		return (bool) get_transient( $this->error_transient );
	}

	/**
	 * Clear the "We have errors" flag.
	 */
	public function clear_error_notice() {
		delete_transient( $this->error_transient );
	}
}

/**
 * Returns an initialized instance of the SP_Sync_Meta class.
 *
 * @return SP_Sync_Meta An initialized instance of the SP_Sync_Meta class.
 */
function SP_Sync_Meta() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return SP_Sync_Meta::instance();
}
