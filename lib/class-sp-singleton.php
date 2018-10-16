<?php
/**
 * SearchPress library: SP_Singleton abstract class
 *
 * @package SearchPress
 */

/**
 * Base Singleton Class
 */
abstract class SP_Singleton {
	/**
	 * Holds references to the singleton instances.
	 *
	 * @var array
	 */
	private static $instances;

	/**
	 * Ensure singletons can't be instantiated outside the `instance()` method.
	 */
	private function __construct() {
		// Don't do anything, needs to be initialized via instance() method.
	}

	/**
	 * Get an instance of the class.
	 *
	 * @return SP_Singleton
	 */
	public static function instance() {
		$class_name = get_called_class();
		if ( ! isset( self::$instances[ $class_name ] ) ) {
			self::$instances[ $class_name ] = new $class_name();
			self::$instances[ $class_name ]->setup();
		}
		return self::$instances[ $class_name ];
	}

	/**
	 * Sets up the singleton.
	 */
	public function setup() {
		// Silence is golden.
	}
}
