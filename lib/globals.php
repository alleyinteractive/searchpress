<?php
/**
 * SearchPress library: Miscellaneous constants
 *
 * @package SearchPress
 */

// Core plugin version.
define( 'SP_VERSION', '0.5' );

// The map version. Represents the last time the mappings were changed.
define( 'SP_MAP_VERSION', 2021012601 );

/**
 * Error Codes
 */

// The function SP_Config->flush didn't respond with 200.
define( 'SP_ERROR_FLUSH_FAIL', '100' );

// The function SP_Heartbeat->check_beat failed.
define( 'SP_ERROR_NO_BEAT', '101' );
