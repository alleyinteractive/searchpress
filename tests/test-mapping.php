<?php

/**
 * @group mapping
 */
class Tests_Mapping extends WP_UnitTestCase {
	var $demo_user;
	var $demo_user_id;
	var $demo_term;
	var $demo_term_id;
	var $demo_post;
	var $demo_post_id;
	var $demo_dates = array();

	function setUp() {
		parent::setUp();
		sp_index_flush_data();

		$this->demo_user = array(
			'user_login' => 'author1',
			'user_nicename' => 'author-nicename',
			'user_pass' => rand_str(),
			'role' => 'author',
			'display_name' => 'Michael Scott',
		);
		$this->demo_user_id = $this->factory->user->create( $this->demo_user );

		$this->demo_term = array(
			'taxonomy' => 'category',
			'name' => 'cat-a',
			'slug' => 'cat-a',
		);
		$this->demo_term_id = $this->factory->term->create( $this->demo_term );

		$post_date = '';
		$this->demo_dates = array(
			'post_date' => array( 'date' => '2013-02-28 01:23:45' ),
			'post_date_gmt' => array( 'date' => '2013-02-28 05:23:45' ),
			'post_modified' => array( 'date' => '2013-02-28 01:23:45' ),
			'post_modified_gmt' => array( 'date' => '2013-02-28 05:23:45' ),
		);
		foreach ( $this->demo_dates as &$date ) {
			$ts = strtotime( $date['date'] );
			$date = array(
				'date'              => strval( $date['date'] ),
				'year'              => intval( date( 'Y', $ts ) ),
				'month'             => intval( date( 'm', $ts ) ),
				'day'               => intval( date( 'd', $ts ) ),
				'hour'              => intval( date( 'H', $ts ) ),
				'minute'            => intval( date( 'i', $ts ) ),
				'second'            => intval( date( 's', $ts ) ),
				'week'              => intval( date( 'W', $ts ) ),
				'day_of_week'       => intval( date( 'N', $ts ) ),
				'day_of_year'       => intval( date( 'z', $ts ) ),
				'seconds_from_day'  => intval( mktime( date( 'H', $ts ), date( 'i', $ts ), date( 's', $ts ), 1, 1, 1970 ) ),
				'seconds_from_hour' => intval( mktime( 0, date( 'i', $ts ), date( 's', $ts ), 1, 1, 1970 ) ),
			);
		}

		$this->demo_post = array(
			'post_author'       => $this->demo_user_id,
			'post_date'         => $this->demo_dates['post_date']['date'],
			'post_date_gmt'     => $this->demo_dates['post_date_gmt']['date'],
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
			'post_category'     => array( $this->demo_term_id ),
		);
		$this->demo_post_id = $this->factory->post->create( $this->demo_post );
		add_post_meta( $this->demo_post_id, 'test_string', 'foo' );
		add_post_meta( $this->demo_post_id, 'test_long', '123' );
		add_post_meta( $this->demo_post_id, 'test_double', '123.456' );
		add_post_meta( $this->demo_post_id, 'test_boolean_true', 'true' );
		add_post_meta( $this->demo_post_id, 'test_boolean_false', 'false' );
		add_post_meta( $this->demo_post_id, 'test_date', '2012-03-14 03:14:15' );
		SP_Sync_Manager()->sync_post( $this->demo_post_id );

		// Force refresh the index so the data is available immediately
		SP_API()->post( '_refresh' );
	}

	function _search_and_get_field( $args, $field = 'post_name' ) {
		$args = wp_parse_args( $args, array(
			'fields' => $field
		) );
		$posts = sp_wp_search( $args, true );
		return sp_results_pluck( $posts, $field );
	}

	function _field_mapping_test( $field ) {
		$this->assertSame(
			array( $this->demo_post[ $field ] ),
			$this->_search_and_get_field( array(), $field )
		);
	}

	function _date_field_mapping_test( $field ) {
		$this->assertSame(
			array( $this->demo_dates[ $field ]['date'] ),
			$this->_search_and_get_field( array(), $field . '.date' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['year'] ),
			$this->_search_and_get_field( array(), $field . '.year' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['month'] ),
			$this->_search_and_get_field( array(), $field . '.month' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['day'] ),
			$this->_search_and_get_field( array(), $field . '.day' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['hour'] ),
			$this->_search_and_get_field( array(), $field . '.hour' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['minute'] ),
			$this->_search_and_get_field( array(), $field . '.minute' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['second'] ),
			$this->_search_and_get_field( array(), $field . '.second' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['week'] ),
			$this->_search_and_get_field( array(), $field . '.week' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['day_of_week'] ),
			$this->_search_and_get_field( array(), $field . '.day_of_week' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['day_of_year'] ),
			$this->_search_and_get_field( array(), $field . '.day_of_year' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['seconds_from_day'] ),
			$this->_search_and_get_field( array(), $field . '.seconds_from_day' )
		);

		$this->assertSame(
			array( $this->demo_dates[ $field ]['seconds_from_hour'] ),
			$this->_search_and_get_field( array(), $field . '.seconds_from_hour' )
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
			array( get_permalink( $this->demo_post_id ) ),
			$this->_search_and_get_field( array(), 'permalink' )
		);
	}

	function test_mapped_field_post_id() {
		$this->assertSame(
			array( $this->demo_post_id ),
			$this->_search_and_get_field( array(), 'post_id' )
		);
	}

	function test_mapping_field_post_author() {
		$this->assertSame(
			array( $this->demo_user_id ),
			$this->_search_and_get_field( array(), 'post_author.user_id' )
		);

		$this->assertSame(
			array( $this->demo_user['user_login'] ),
			$this->_search_and_get_field( array(), 'post_author.login' )
		);

		$this->assertSame(
			array( $this->demo_user['display_name'] ),
			$this->_search_and_get_field( array(), 'post_author.display_name' )
		);

		$this->assertSame(
			array( $this->demo_user['user_nicename'] ),
			$this->_search_and_get_field( array(), 'post_author.user_nicename' )
		);
	}


	function test_mapping_field_post_meta() {
		$this->assertSame(
			array( 'foo' ),
			$this->_search_and_get_field( array(), 'post_meta.test_string.raw' )
		);

		$this->assertSame(
			array( 123 ),
			$this->_search_and_get_field( array(), 'post_meta.test_long.long' )
		);

		$this->assertSame(
			array( 123.456 ),
			$this->_search_and_get_field( array(), 'post_meta.test_double.double' )
		);

		$this->assertSame(
			array( true ),
			$this->_search_and_get_field( array(), 'post_meta.test_boolean_true.boolean' )
		);

		$this->assertSame(
			array( false ),
			$this->_search_and_get_field( array(), 'post_meta.test_boolean_false.boolean' )
		);

		$this->assertSame(
			array( '2012-03-14' ),
			$this->_search_and_get_field( array(), 'post_meta.test_date.date' )
		);

		$this->assertSame(
			array( '2012-03-14 03:14:15' ),
			$this->_search_and_get_field( array(), 'post_meta.test_date.datetime' )
		);

		$this->assertSame(
			array( '03:14:15' ),
			$this->_search_and_get_field( array(), 'post_meta.test_date.time' )
		);
	}

	function test_mapping_field_terms() {
		$this->assertSame(
			array( $this->demo_term['name'] ),
			$this->_search_and_get_field( array(), 'terms.category.name' )
		);

		$this->assertSame(
			array( $this->demo_term['slug'] ),
			$this->_search_and_get_field( array(), 'terms.category.slug' )
		);

		$this->assertSame(
			array( 0 ),
			$this->_search_and_get_field( array(), 'terms.category.parent' )
		);

		$this->assertSame(
			array( $this->demo_term_id ),
			$this->_search_and_get_field( array(), 'terms.category.term_id' )
		);
	}

}