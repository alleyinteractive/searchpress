<?php
/**
 * Test assorted functions not dependent on Elasticsearch or even necessarily on
 * WordPress itself. Note that this extends `PHPUnit_Framework_TestCase` instead
 * of WP_UnitTestCase, because it doesn't need to interact with WordPress.
 *
 * @group  functions
 * @group  misc
 */

class Tests_Functions extends PHPUnit_Framework_TestCase {
	public function data_sp_get_array_value_by_path() {
		return array(
			// Basic architecture
			array(
				array( 'grand' => array( 'parent' => array( 'child' => 1 ) ) ),
				array( 'grand', 'parent', 'child' ),
				1,
			),
			array(
				array( 'grand' => array( 'parent' => array( 'child' => 1 ) ) ),
				array( 'grand', 'parent' ),
				array( 'child' => 1 ),
			),
			array(
				array( 'grand' => array( 'parent' => array( 'child' => 1 ) ) ),
				array( 'grand' ),
				array( 'parent' => array( 'child' => 1 ) ),
			),
			array(
				array( 'grand' => array( 'parent' => array( 'child' => 1 ) ) ),
				array(),
				array( 'grand' => array( 'parent' => array( 'child' => 1 ) ) ),
			),

			// Introduce numeric arrays
			array(
				array( 'child' => array( 7, 8, 9 ) ),
				array( 'child' ),
				array( 7, 8, 9 ),
			),
			array(
				array(
					'parent' => array(
						array( 'child' => 7 ),
						array( 'child' => 8 ),
						array( 'child' => 9 ),
					),
				),
				array( 'parent', 'child' ),
				array( 7, 8, 9 ),
			),
			array(
				array(
					array( 'parent' => array( 'child' => 7 ) ),
					array( 'parent' => array( 'child' => 8 ) ),
					array( 'parent' => array( 'child' => 9 ) ),
				),
				array( 'parent', 'child' ),
				array( 7, 8, 9 ),
			),
		);
	}

	/**
	 * @dataProvider data_sp_get_array_value_by_path
	 *
	 * @param  array $array    Array to crawl.
	 * @param  array $path     Path to take when crawling.
	 * @param  mixed $expected Expected results.
	 */
	public function test_sp_get_array_value_by_path( $array, $path, $expected ) {
		$this->assertSame( $expected, sp_get_array_value_by_path( $array, $path ) );
	}
}
