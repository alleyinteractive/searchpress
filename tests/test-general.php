<?php

/**
 * @group general
 */
class Tests_General extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		sp_index_flush_data();

		$this->factory->post->create( array( 'post_title' => 'lorem-ipsum', 'post_date' => '2009-07-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'comment-test', 'post_date' => '2009-08-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'one-trackback', 'post_date' => '2009-09-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'many-trackbacks', 'post_date' => '2009-10-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'no-comments', 'post_date' => '2009-10-02 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'one-comment', 'post_date' => '2009-11-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'contributor-post-approved', 'post_date' => '2009-12-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'embedded-video', 'post_date' => '2010-01-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'simple-markup-test', 'post_date' => '2010-02-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'raw-html-code', 'post_date' => '2010-03-01 00:00:00' ) );

		// Force refresh the index so the data is available immediately
		SP_API()->post( '_refresh' );
	}

	function test_search_auto_integration() {
		global $wp_the_query;
		$wp_the_query = new WP_Query;

		SP_Config()->update_settings( array( 'active' => true ) );
		SP_Search()->init_hooks();

		// SearchPress currently only auto integrates into the main query
		$wp_the_query->query( 's=trackback&orderby=date' );

		$this->assertContains( 'SearchPress', $wp_the_query->request );
		$this->assertEquals(
			array(
				'many-trackbacks',
				'one-trackback',
			),
			wp_list_pluck( $wp_the_query->posts, 'post_name' )
		);
		SP_Search()->remove_hooks();
	}

	function test_search_activation() {
		global $wp_the_query;
		$wp_the_query = new WP_Query;

		SP_Config()->update_settings( array( 'active' => false ) );
		$wp_the_query->query( 's=trackback&orderby=date' );
		$this->assertEquals( false, strpos( $wp_the_query->request, 'SearchPress' ) );

		SP_Config()->update_settings( array( 'active' => true ) );
		SP_Search()->init_hooks();
		$wp_the_query->get_posts();
		$this->assertContains( 'SearchPress', $wp_the_query->request );
		SP_Search()->remove_hooks();
	}

}