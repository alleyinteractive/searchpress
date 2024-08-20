<?php

/**
 * @group mapping
 */
class Tests_Mapping extends SearchPress_UnitTestCase {
	/**
	 * User attributes.
	 *
	 * @var array
	 */
	protected static $demo_user;

	/**
	 * ID of the user created using Tests_Mapping::$demo_user.
	 *
	 * @var int
	 */
	protected static $demo_user_id;

	/**
	 * Term attributes.
	 *
	 * @var array
	 */
	protected static $demo_term;

	/**
	 * ID of the term created using Tests_Mapping::$demo_term.
	 *
	 * @var int
	 */
	protected static $demo_term_id;

	/**
	 * Post attributes.
	 *
	 * @var array
	 */
	protected static $demo_post;

	/**
	 * ID of the post created using Tests_Mapping::$demo_post.
	 *
	 * @var int
	 */
	protected static $demo_post_id;

	/**
	 * Array of dates used in the demo post.
	 *
	 * @var array
	 */
	protected static $demo_dates = array();

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		add_filter(
			'sp_post_allowed_meta',
			function() {
				return array(
					'test_string'        => array( 'value' ),
					'test_long'          => array( 'long' ),
					'test_double'        => array( 'double' ),
					'test_boolean_true'  => array( 'boolean' ),
					'test_boolean_false' => array( 'boolean' ),
					'test_date'          => array( 'date', 'datetime', 'time' ),
				);
			}
		);

		self::$demo_user = array(
			'user_login'    => 'author1',
			'user_nicename' => 'author-nicename',
			'user_pass'     => rand_str(),
			'role'          => 'author',
			'display_name'  => 'Michael Scott',
		);
		self::$demo_user_id = self::factory()->user->create( self::$demo_user );

		self::$demo_term = array(
			'taxonomy' => 'category',
			'name'     => 'cat-a',
			'slug'     => 'cat-a',
		);
		self::$demo_term_id = self::factory()->term->create( self::$demo_term );

		self::$demo_dates = array(
			'post_date'         => array( 'date' => '2013-02-28 01:23:45' ),
			'post_date_gmt'     => array( 'date' => '2013-02-28 05:23:45' ),
			'post_modified'     => array( 'date' => '2013-02-28 01:23:45' ),
			'post_modified_gmt' => array( 'date' => '2013-02-28 05:23:45' ),
		);
		foreach ( self::$demo_dates as &$date ) {
			$ts = strtotime( $date['date'] );
			$date = array(
				'date'              => strval( $date['date'] ),
				'year'              => intval( gmdate( 'Y', $ts ) ),
				'month'             => intval( gmdate( 'm', $ts ) ),
				'day'               => intval( gmdate( 'd', $ts ) ),
				'hour'              => intval( gmdate( 'H', $ts ) ),
				'minute'            => intval( gmdate( 'i', $ts ) ),
				'second'            => intval( gmdate( 's', $ts ) ),
				'week'              => intval( gmdate( 'W', $ts ) ),
				'day_of_week'       => intval( gmdate( 'N', $ts ) ),
				'day_of_year'       => intval( gmdate( 'z', $ts ) ),
				'seconds_from_day'  => intval( mktime( gmdate( 'H', $ts ), gmdate( 'i', $ts ), gmdate( 's', $ts ), 1, 1, 1970 ) ),
				'seconds_from_hour' => intval( mktime( 0, gmdate( 'i', $ts ), gmdate( 's', $ts ), 1, 1, 1970 ) ),
			);
		}

		self::$demo_post = array(
			'post_author'       => self::$demo_user_id,
			'post_date'         => self::$demo_dates['post_date']['date'],
			'post_date_gmt'     => self::$demo_dates['post_date_gmt']['date'],
			'post_content'      => 'Welcome to <a href="http://wp.dev/">Local WordPress Dev Sites</a>. This is your first post. Edit or delete it, then start blogging!',
			'post_title'        => 'Hello world!',
			'post_excerpt'      => 'Lorem ipsum dolor sit amet',
			'post_status'       => 'publish',
			'post_password'     => 'foobar',
			'post_name'         => 'hello-world',
			'post_parent'       => 123,
			'menu_order'        => 456,
			'post_type'         => 'post',
			'post_mime_type'    => 'image/jpeg',
			'post_category'     => array( self::$demo_term_id ),
		);
		self::$demo_post_id = self::factory()->post->create( self::$demo_post );
		add_post_meta( self::$demo_post_id, 'test_string', 'foo' );
		add_post_meta( self::$demo_post_id, 'test_long', '123' );
		add_post_meta( self::$demo_post_id, 'test_double', '123.456' );
		add_post_meta( self::$demo_post_id, 'test_boolean_true', 'true' );
		add_post_meta( self::$demo_post_id, 'test_boolean_false', 'false' );
		add_post_meta( self::$demo_post_id, 'test_date', '2012-03-14 03:14:15' );
		self::index( self::$demo_post_id );
	}

	function _field_mapping_test( $field ) {
		$this->assertSame(
			array( self::$demo_post[ $field ] ),
			$this->search_and_get_field( array(), $field )
		);
	}

	function _date_field_mapping_test( $field ) {
		$this->assertSame(
			array( self::$demo_dates[ $field ]['date'] ),
			$this->search_and_get_field( array(), $field . '.date' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['year'] ),
			$this->search_and_get_field( array(), $field . '.year' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['month'] ),
			$this->search_and_get_field( array(), $field . '.month' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['day'] ),
			$this->search_and_get_field( array(), $field . '.day' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['hour'] ),
			$this->search_and_get_field( array(), $field . '.hour' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['minute'] ),
			$this->search_and_get_field( array(), $field . '.minute' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['second'] ),
			$this->search_and_get_field( array(), $field . '.second' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['week'] ),
			$this->search_and_get_field( array(), $field . '.week' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['day_of_week'] ),
			$this->search_and_get_field( array(), $field . '.day_of_week' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['day_of_year'] ),
			$this->search_and_get_field( array(), $field . '.day_of_year' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['seconds_from_day'] ),
			$this->search_and_get_field( array(), $field . '.seconds_from_day' )
		);

		$this->assertSame(
			array( self::$demo_dates[ $field ]['seconds_from_hour'] ),
			$this->search_and_get_field( array(), $field . '.seconds_from_hour' )
		);
	}


	function test_mapping_field_post_content() {
		$this->_field_mapping_test( 'post_content' );
	}

	function test_mapping_field_post_excerpt() {
		$this->_field_mapping_test( 'post_excerpt' );
	}

	function test_mapping_field_post_mime_type() {
		$this->_field_mapping_test( 'post_mime_type' );
	}

	function test_mapping_field_post_name() {
		$this->_field_mapping_test( 'post_name' );
	}

	function test_mapping_field_post_parent() {
		$this->_field_mapping_test( 'post_parent' );
	}

	function test_mapping_field_post_password() {
		$this->_field_mapping_test( 'post_password' );
	}

	function test_mapping_field_menu_order() {
		$this->_field_mapping_test( 'menu_order' );
	}

	function test_mapping_field_post_status() {
		$this->_field_mapping_test( 'post_status' );
	}

	function test_mapping_field_post_title() {
		$this->_field_mapping_test( 'post_title' );
	}

	function test_mapping_field_post_type() {
		$this->_field_mapping_test( 'post_type' );
	}


	function test_mapping_field_post_date() {
		$this->_date_field_mapping_test( 'post_date' );
	}

	function test_mapping_field_post_date_gmt() {
		$this->_date_field_mapping_test( 'post_date_gmt' );
	}

	function test_mapping_field_post_modified() {
		$this->_date_field_mapping_test( 'post_modified' );
	}

	function test_mapping_field_post_modified_gmt() {
		$this->_date_field_mapping_test( 'post_modified_gmt' );
	}


	function test_mapped_field_permalink() {
		$this->assertSame(
			array( get_permalink( self::$demo_post_id ) ),
			$this->search_and_get_field( array(), 'permalink' )
		);
	}

	function test_mapped_field_post_id() {
		$this->assertSame(
			array( self::$demo_post_id ),
			$this->search_and_get_field( array(), 'post_id' )
		);
	}

	function test_mapping_field_post_author() {
		$this->assertSame(
			array( self::$demo_user_id ),
			$this->search_and_get_field( array(), 'post_author.user_id' )
		);

		$this->assertSame(
			array( self::$demo_user['user_login'] ),
			$this->search_and_get_field( array(), 'post_author.login' )
		);

		$this->assertSame(
			array( self::$demo_user['display_name'] ),
			$this->search_and_get_field( array(), 'post_author.display_name' )
		);

		$this->assertSame(
			array( self::$demo_user['user_nicename'] ),
			$this->search_and_get_field( array(), 'post_author.user_nicename' )
		);
	}


	function test_mapping_field_post_meta() {
		$this->assertSame(
			array( 'foo' ),
			$this->search_and_get_field( array(), 'post_meta.test_string.raw' )
		);

		$this->assertSame(
			array( 123 ),
			$this->search_and_get_field( array(), 'post_meta.test_long.long' )
		);

		$this->assertSame(
			array( 123.456 ),
			$this->search_and_get_field( array(), 'post_meta.test_double.double' )
		);

		$this->assertSame(
			array( true ),
			$this->search_and_get_field( array(), 'post_meta.test_boolean_true.boolean' )
		);

		$this->assertSame(
			array( false ),
			$this->search_and_get_field( array(), 'post_meta.test_boolean_false.boolean' )
		);

		$this->assertSame(
			array( '2012-03-14 03:14:15' ),
			$this->search_and_get_field( array(), 'post_meta.test_date.datetime' )
		);
	}

	function test_mapping_field_terms() {
		$this->assertSame(
			array( self::$demo_term['name'] ),
			$this->search_and_get_field( array(), 'terms.category.name' )
		);

		$this->assertSame(
			array( self::$demo_term['slug'] ),
			$this->search_and_get_field( array(), 'terms.category.slug' )
		);

		$this->assertSame(
			array( 0 ),
			$this->search_and_get_field( array(), 'terms.category.parent' )
		);

		$this->assertSame(
			array( self::$demo_term_id ),
			$this->search_and_get_field( array(), 'terms.category.term_id' )
		);
	}

}
