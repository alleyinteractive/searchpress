<?php

/**
* A simple class for debugging SearchPress
*/
class SP_Debug {
	public static $is_cli;

	public static $timer_start;

	public static function init() {
		self::$timer_start = microtime( true );
	}

	public static function debug( $action, $value = null ) {
		if ( is_array( $value ) || is_object( $value ) )
			$value = json_encode( $value );
		elseif ( is_bool( $value ) )
			$value = true === $value ? '(bool) true' : '(bool) false';
		elseif ( is_null( $value ) )
			$value = '(null)';

		SP_Sync_Meta()->log( new WP_Error( 'debug', "SP_Debug.$action (@" . self::split() . ") : $value" ) );
	}

	public static function is_cli() {
		if ( null === self::$is_cli )
			self::$is_cli = ( defined( 'WP_CLI' ) && WP_CLI );
		return self::$is_cli;
	}

	public static function split() {
		return number_format( microtime( true ) - self::$timer_start, 2 ) . 's';
	}

	public static function debug_sp_post_pre_index( $data ) {
		do_action( 'sp_debug', '[SP_Post] Post JSON', json_encode( $data ) );
		return $data;
	}
}
add_action( 'sp_debug', array( 'SP_Debug', 'debug' ), 10, 2 );
add_action( 'sp_post_pre_index', array( 'SP_Debug', 'debug_sp_post_pre_index' ), 999 );

SP_Debug::init();