<?php
/**
 * Trait file for Singletons.
 *
 * @package SearchPress
 */

namespace SearchPress;

/**
 * Make a class into a singleton.
 */
trait Singleton {
	/**
	 * Existing instance.
	 *
	 * @var static|null
	 */
	protected static $instance;

	/**
	 * A do-nothing constructor.
	 */
	public function __construct() {
		// Do nothing.
	}

	/**
	 * Get class instance.
	 *
	 * @return static
	 */
	public static function instance() {
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static(); // @phpstan-ignore-line
			static::$instance->setup();
		}
		return static::$instance;
	}

	/**
	 * Set up the singleton.
	 */
	public function setup() {
		// Silence.
	}
}
