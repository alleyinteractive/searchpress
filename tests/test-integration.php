<?php

/**
 * Test the auto integration. It's important to note that SP only integrates
 * with the main query.
 *
 * @group integration
 */
class Tests_Integration extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		sp_index_flush_data();

		$cat = $this->factory->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-demo' ) );
		$tag = $this->factory->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'tag-demo' ) );

		$this->factory->post->create( array( 'post_title' => 'lorem-ipsum', 'post_date' => '2009-07-01 00:00:00', 'post_category' => array( $cat ) ) );
		$this->factory->post->create( array( 'post_title' => 'comment-test', 'post_date' => '2009-08-01 00:00:00', 'post_category' => array( $cat ) ) );
		$this->factory->post->create( array( 'post_title' => 'one-trackback', 'post_date' => '2009-09-01 00:00:00', 'post_category' => array( $cat ) ) );
		$this->factory->post->create( array( 'post_title' => 'many-trackbacks', 'post_date' => '2009-10-01 00:00:00', 'post_category' => array( $cat ) ) );
		$this->factory->post->create( array( 'post_title' => 'no-comments', 'post_date' => '2009-10-02 00:00:00' ) );

		$this->factory->post->create( array( 'post_title' => 'one-comment', 'post_date' => '2009-11-01 00:00:00', 'tags_input' => array( $tag ) ) );
		$this->factory->post->create( array( 'post_title' => 'contributor-post-approved', 'post_date' => '2009-12-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'many-comments', 'post_date' => '2010-01-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'simple-markup-test', 'post_date' => '2010-02-01 00:00:00', 'tags_input' => array( $tag ) ) );
		$this->factory->post->create( array( 'post_title' => 'raw-html-code', 'post_date' => '2010-03-01 00:00:00', 'tags_input' => array( $tag ) ) );

		register_post_type( 'cpt', array( 'public' => true ) );
		sp_searchable_post_types( true );
		$this->factory->post->create( array( 'post_title' => 'cpt', 'post_date' => '2010-01-01 00:00:00', 'post_type' => 'cpt' ) );
		$this->factory->post->create( array( 'post_title' => 'lorem-cpt', 'post_date' => '2010-01-01 00:00:00', 'post_type' => 'cpt' ) );

		// Force refresh the index so the data is available immediately
		SP_API()->post( '_refresh' );
	}

	function test_search_auto_integration() {
		$this->go_to( '/?s=trackback&orderby=date' );
		$this->assertEquals( get_query_var( 's' ), 'trackback' );
		$this->assertTrue( is_search() );

		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals(
			array(
				'many-trackbacks',
				'one-trackback',
			),
			wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_name' )
		);
	}

	function test_sp_query_arg() {
		$this->go_to( '/?sp[force]=1' );

		$this->assertEquals( get_query_var( 'sp' ), array( 'force' => '1' ) );
		$this->assertTrue( is_search() );
		$this->assertFalse( is_home() );
	}

	function test_no_results() {
		$this->go_to( '/?s=cucumbers' );
		$this->assertEquals( get_query_var( 's' ), 'cucumbers' );
		$this->assertTrue( is_search() );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals( 0, $GLOBALS['wp_query']->found_posts );
	}

	function test_date_results() {
		$this->go_to( '/?s=test&year=2010' );
		$this->assertEquals( get_query_var( 'year' ), '2010' );
		$this->assertEmpty( get_query_var( 'monthnum' ) );
		$this->assertEmpty( get_query_var( 'day' ) );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals(
			array( 'simple-markup-test' ),
			wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_name' )
		);

		$this->go_to( '/?s=test&year=2009&monthnum=8' );
		$this->assertEquals( get_query_var( 'year' ), '2009' );
		$this->assertEquals( get_query_var( 'monthnum' ), '8' );
		$this->assertEmpty( get_query_var( 'day' ) );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals(
			array( 'comment-test' ),
			wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_name' )
		);

		$this->go_to( '/?s=comment&year=2009&monthnum=11&day=1' );
		$this->assertEquals( get_query_var( 'year' ), '2009' );
		$this->assertEquals( get_query_var( 'monthnum' ), '11' );
		$this->assertEquals( get_query_var( 'day' ), '1' );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals(
			array( 'one-comment' ),
			wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_name' )
		);
	}

	function test_sp_date_range() {
		$this->go_to( '/?s=comment&sp[f]=2009-10-14&sp[t]=2009-12-31' );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals(
			array( 'one-comment' ),
			wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_name' )
		);
	}

	function test_terms() {
		$this->go_to( '/?s=comment&category_name=cat-demo' );
		$this->assertEquals( get_query_var( 'category_name' ), 'cat-demo' );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals(
			array( 'comment-test' ),
			wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_name' )
		);

		$this->go_to( '/?s=comment&tag=tag-demo' );
		$this->assertEquals( get_query_var( 'tag' ), 'tag-demo' );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals(
			array( 'one-comment' ),
			wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_name' )
		);
	}

	function test_post_types() {
		$this->go_to( '/?s=lorem&post_type=cpt' );
		$this->assertEquals( get_query_var( 'post_type' ), 'cpt' );
		$this->assertContains( 'SearchPress', $GLOBALS['wp_query']->request );
		$this->assertEquals(
			array( 'lorem-cpt' ),
			wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_name' )
		);
	}
}