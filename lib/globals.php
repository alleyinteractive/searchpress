<?php
/**
 * SearchPress library: Miscellaneous constants
 *
 * @package SearchPress
 */

// Core plugin version.
const SP_VERSION = '0.4.1';

// The map version. Represents the last time the mappings were changed.
const SP_MAP_VERSION = 2020040401;

/**
 * Error Codes
 */

// The function SP_Config->flush didn't respond with 200.
const SP_ERROR_FLUSH_FAIL = 100;

// The function SP_Heartbeat->check_beat failed.
const SP_ERROR_NO_BEAT = 101;
