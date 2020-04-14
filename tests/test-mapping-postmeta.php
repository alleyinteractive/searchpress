<?php

/**
 * Test the various intricacies of the post meta mapping.
 *
 * @group mapping
 */
class Tests_Mapping_Postmeta extends SearchPress_UnitTestCase {
	protected static $demo_post_id;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		self::$demo_post_id = self::factory()->post->create();
	}

	public function tearDown() {
		delete_post_meta( self::$demo_post_id, 'mapping_postmeta_test' );
		delete_post_meta( self::$demo_post_id, 'long_string_test' );

		parent::tearDown();
	}

	function meta_sample_data() {
		// $value, $boolean, $long, $double, $datetime
		return array(
			array( 'mnducnrvnfh', true, null, null, null ), // Randomish string.
			array( 'To be or not to be', true, null, null, null ), // Only stopwords.
			array( 1, true, 1, 1, '1970-01-01 00:00:01' ),
			array( -123, true, -123, -123, '1969-12-31 23:57:57' ),
			array( 0, false, 0, 0, '1970-01-01 00:00:00' ),
			array( '1', true, 1, 1, '1970-01-01 00:00:01' ),
			array( '0', false, 0, 0, '1970-01-01 00:00:00' ),
			array( 1.1, true, 1, 1.1, null ),
			array( -1.1, true, -1, -1.1, null ),
			array( 0.0, false, 0, 0, '1970-01-01 00:00:00' ),
			array( 0.01, true, 0, 0.01, null ),
			array( 0.9999999, true, 0, 0.9999999, null ),
			array( '', false, null, null, null ),
			array( null, false, null, null, null ),
			array( array( 'foo' => array( 'bar' => array( 'bat' => true ) ) ), true, null, null, null ),
			array( '2015-01-01', true, null, null, '2015-01-01 00:00:00' ),
			array( '1/2/2015', true, null, null, '2015-01-02 00:00:00' ),
			array( 'Jan 3rd 2030', true, null, null, '2030-01-03 00:00:00' ),
			array( 1442600000, true, 1442600000, 1442600000, '2015-09-18 18:13:20' ),
			array( 1234567, true, 1234567, 1234567, '1970-01-15 06:56:07' ),
			array( '1442600000', true, 1442600000, 1442600000, '2015-09-18 18:13:20' ),
			array( 1442600000.0001, true, 1442600000, 1442600000.0001, null ),
			array( '2015-01-04T15:19:21-05:00', true, null, null, '2015-01-04 20:19:21' ), // Note the timezone.
			array( '18:13:20', true, null, null, gmdate( 'Y-m-d' ) . ' 18:13:20' ),
			array( '14e7647469', true, null, null, null ), // Hash that is technically a (huge) number.
		);
	}

	/**
	 * @dataProvider meta_sample_data
	 */
	function test_mapping_post_meta( $value, $boolean, $long, $double, $datetime ) {
		update_post_meta( self::$demo_post_id, 'mapping_postmeta_test', $value );
		self::index( self::$demo_post_id );

		if ( null === $value ) {
			$string = array( null );
		} elseif ( is_array( $value ) ) {
			$string = array( serialize( $value ) );
		} else {
			$string = array( strval( $value ) );
		}

		// Test the various meta mappings. Ideally, these each would be their
		// own test, but this is considerably faster.
		$this->assertSame( $string, $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.value' ), 'Checking meta.value' );
		$this->assertSame( $string, $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.raw' ), 'Checking meta.raw' );
		$this->assertSame( array( $boolean ), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.boolean' ), 'Checking meta.boolean' );

		if ( isset( $long ) ) {
			$this->assertSame( array( $long ), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.long' ), 'Checking meta.long' );
		} else {
			$this->assertSame( array(), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.long' ), 'Checking that meta.long is missing' );
		}

		if ( isset( $double ) ) {
			$this->assertSame( array( $double ), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.double' ), 'Checking meta.double' );
		} else {
			$this->assertSame( array(), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.double' ), 'Checking that meta.double is missing' );
		}

		if ( isset( $datetime ) ) {
			list( $date, $time ) = explode( ' ', $datetime );
			$this->assertSame( array( $datetime ), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.datetime' ), 'Checking meta.datetime' );
			$this->assertSame( array( $date ), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.date' ), 'Checking meta.date' );
			$this->assertSame( array( $time ), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.time' ), 'Checking meta.time' );
		} else {
			$this->assertSame( array(), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.datetime' ), 'Checking that meta.datetime is missing' );
			$this->assertSame( array(), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.date' ), 'Checking that meta.date is missing' );
			$this->assertSame( array(), $this->search_and_get_field( array(), 'post_meta.mapping_postmeta_test.time' ), 'Checking that meta.time is missing' );
		}
	}

	public function long_string_data() {
		return array(
			// $string, $should_truncate_indexed, $should_truncate_raw
			array( str_repeat( 'a', 1000 ), false, false ),
			array( str_repeat( 'a', 50000 ), true, true ),
			array( trim( str_repeat( 'test ', 200 ) ), false, false ),
			array( trim( str_repeat( 'test ', 10000 ) ), false, true ),
		);
	}

	/**
	 * @dataProvider long_string_data
	 */
	public function test_long_strings( $string, $should_truncate_indexed, $should_truncate_raw ) {
		self::factory()->post->update_object( self::$demo_post_id, array( 'post_content' => $string ) );
		update_post_meta( self::$demo_post_id, 'long_string_test', $string );
		self::index( self::$demo_post_id );

		// These fields are not analyzed
		if ( $should_truncate_raw ) {
			$meta_raw = $this->search_and_get_field( array(), 'post_meta.long_string_test.raw' );
			$this->assertNotSame( array( $string ), $meta_raw, 'Checking meta.raw' );
			$this->assertContains( $meta_raw[0], $string );
		} else {
			$this->assertSame( array( $string ), $this->search_and_get_field( array(), 'post_meta.long_string_test.raw' ), 'Checking meta.raw' );
		}

		// These fields are analyzed
		if ( $should_truncate_indexed ) {
			$this->assertNotSame( array( $string ), $this->search_and_get_field( array(), 'post_content' ), 'Checking post_content' );
			$this->assertNotSame( array( $string ), $this->search_and_get_field( array(), 'post_meta.long_string_test.value' ), 'Checking meta.value' );
		} else {
			$this->assertSame( array( $string ), $this->search_and_get_field( array(), 'post_content' ), 'Checking post_content' );
			$this->assertSame( array( $string ), $this->search_and_get_field( array(), 'post_meta.long_string_test.value' ), 'Checking meta.value' );
		}
	}
}
