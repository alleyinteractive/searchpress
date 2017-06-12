<?php

/**
 * Miscellaneous Constants
 */

// The map version. Represents the last time the mappings were changed.
define( 'SP_MAP_VERSION', 2017061001 );

/**
 * Error Codes
 */

// SP_Config()->flush() didn't respond with 200
define( 'SP_ERROR_FLUSH_FAIL', '100' );

// SP_Heartbeat()->check_beat() failed
define( 'SP_ERROR_NO_BEAT',    '101' );
