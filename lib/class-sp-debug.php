<?php
/**
 * SearchPress library: SP_Debug class
 *
 * @package SearchPress
 */

/**
 * A simple class for debugging SearchPress
 */
class SP_Debug {

	/**
	 * Whether this request is being run in a CLI context or not.
	 *
	 * @var bool
	 */
	public static $is_cli;

	/**
	 * The start time of the operation as a float with microseconds.
	 *
	 * @var float
	 */
	public static $timer_start;

	/**
	 * Initializes the debugger.
	 *
	 * @access public
	 */
	public static function init() {
		self::$timer_start = microtime( true );
	}

	/**
	 * Logs a debug message.
	 *
	 * @param string                 $action The action that was being undertaken.
	 * @param array|bool|string|null $value  The value to log.
	 * @access public
	 */
	public static function debug( $action, $value = null ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
		} elseif ( is_bool( $value ) ) {
			$value = true === $value ? '(bool) true' : '(bool) false';
		} elseif ( is_null( $value ) ) {
			$value = '(null)';
		}

		SP_Sync_Meta()->log( new WP_Error( 'debug', "SP_Debug.$action (@" . self::split() . ") : $value" ) );
	}

	/**
	 * Determines if the current request is running in a CLI context.
	 *
	 * @access public
	 * @return bool True if the current request is CLI, false if not.
	 */
	public static function is_cli() {
		if ( null === self::$is_cli ) {
			self::$is_cli = ( defined( 'WP_CLI' ) && WP_CLI && ! wp_doing_cron() );
		}
		return self::$is_cli;
	}

	/**
	 * Gets a split (number of seconds between now and when the script started).
	 *
	 * @return string The split value.
	 */
	public static function split() {
		return number_format( microtime( true ) - self::$timer_start, 2 ) . 's';
	}

	/**
	 * Triggers the sp_debug filter to include the data before indexing.
	 *
	 * @param object $data The data passed to sp_post_pre_index.
	 */
	public static function debug_sp_post_pre_index( $data ) {
		do_action( 'sp_debug', '[SP_Post] Post JSON', wp_json_encode( $data ) );
		return $data;
	}
}
add_action( 'sp_debug', array( 'SP_Debug', 'debug' ), 10, 2 );
add_filter( 'sp_post_pre_index', array( 'SP_Debug', 'debug_sp_post_pre_index' ), 999 );

SP_Debug::init();
