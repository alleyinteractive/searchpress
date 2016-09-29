<?php

/**
 * @group indexing
 */
class Tests_Indexing extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		sp_index_flush_data();
		wp_clear_scheduled_hook( 'sp_reindex' );
		SP_Cron()->setup();
	}

	function tearDown() {
		SP_Config()->update_settings( array( 'host' => 'http://localhost:9200', 'active' => true ) );
		SP_Sync_Meta()->reset( 'save' );
		SP_Sync_Manager()->published_posts = false;
		sp_index_flush_data();
		wp_clear_scheduled_hook( 'sp_reindex' );

		parent::tearDown();
	}

	function search_and_get_field( $args, $field = 'post_name' ) {
		$args = wp_parse_args( $args, array(
			'fields' => $field
		) );
		$posts = sp_wp_search( $args, true );
		return sp_results_pluck( $posts, $field );
	}

	function test_new_post() {
		$this->factory->post->create( array( 'post_title' => 'test post' ) );
		SP_API()->post( '_refresh' );

		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
	}

	function test_updated_post() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post' ) );
		$post = array(
			'ID' => $post_id,
			'post_title' => 'lorem ipsum'
		);
		wp_update_post( $post );
		SP_API()->post( '_refresh' );

		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);

		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'lorem ipsum' ) )
		);
	}

	function test_trashed_post() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post' ) );
		SP_API()->post( '_refresh' );

		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);

		wp_trash_post( $post_id );
		SP_API()->post( '_refresh' );

		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
	}

	function test_deleted_post() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post' ) );
		SP_API()->post( '_refresh' );

		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);

		wp_delete_post( $post_id, true );
		SP_API()->post( '_refresh' );

		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
	}

	function test_publishing_and_unpublishing_posts() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'draft' ) );
		SP_API()->post( '_refresh' );
		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);

		wp_publish_post( $post_id );
		SP_API()->post( '_refresh' );
		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);

		$post = array(
			'ID' => $post_id,
			'post_status' => 'draft'
		);
		wp_update_post( $post );
		SP_API()->post( '_refresh' );
		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
	}

	function test_cron_indexing() {
		$posts = array(
			$this->factory->post->create( array( 'post_title' => 'test one' ) ),
			$this->factory->post->create( array( 'post_title' => 'test two' ) ),
			$this->factory->post->create( array( 'post_title' => 'test three' ) ),
			$this->factory->post->create( array( 'post_title' => 'test four' ) ),
			$this->factory->post->create( array( 'post_title' => 'test five' ) ),
			$this->factory->post->create( array( 'post_title' => 'test six' ) ),
			$this->factory->post->create( array( 'post_title' => 'test seven' ) ),
			$this->factory->post->create( array( 'post_title' => 'test eight' ) ),
			$this->factory->post->create( array( 'post_title' => 'searchpress' ) ),
		);

		SP_API()->post( '_refresh' );
		$this->assertEquals(
			array( 'searchpress' ),
			$this->search_and_get_field( array( 'query' => 'searchpress' ) )
		);

		sp_index_flush_data();
		SP_API()->post( '_refresh' );
		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'searchpress' ) )
		);

		SP_Config()->update_settings( array( 'active' => false ) );
		SP_Sync_Manager()->do_cron_reindex();
		SP_Sync_Meta()->bulk = 3;
		SP_Sync_Meta()->save();

		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		sp_tests_fake_cron();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		sp_tests_fake_cron();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		sp_tests_fake_cron();
		$this->assertEmpty( wp_next_scheduled( 'sp_reindex' ) );

		SP_API()->post( '_refresh' );
		$this->assertEquals(
			array( 'searchpress' ),
			$this->search_and_get_field( array( 'query' => 'searchpress' ) )
		);
	}

	function test_cron_index_invalid_response() {
		$posts = array(
			$this->factory->post->create( array( 'post_title' => 'test one' ) ),
			$this->factory->post->create( array( 'post_title' => 'test two' ) ),
			$this->factory->post->create( array( 'post_title' => 'test three' ) ),
			$this->factory->post->create( array( 'post_title' => 'test four' ) ),
			$this->factory->post->create( array( 'post_title' => 'test five' ) ),
			$this->factory->post->create( array( 'post_title' => 'test six' ) ),
			$this->factory->post->create( array( 'post_title' => 'test seven' ) ),
			$this->factory->post->create( array( 'post_title' => 'test eight' ) ),
			$this->factory->post->create( array( 'post_title' => 'searchpress' ) ),
		);

		sp_index_flush_data();

		SP_Config()->update_settings( array( 'host' => 'http://localhost', 'active' => false ) );

		SP_Sync_Manager()->do_cron_reindex();
		SP_Sync_Meta()->bulk = 3;
		SP_Sync_Meta()->save();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );
		$this->assertTrue( empty( SP_Sync_Meta()->messages['error'] ) );
		sp_tests_fake_cron();

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
	}

	function test_cron_index_non_200() {
		$posts = array(
			$this->factory->post->create( array( 'post_title' => 'test one' ) ),
			$this->factory->post->create( array( 'post_title' => 'test two' ) ),
			$this->factory->post->create( array( 'post_title' => 'test three' ) ),
			$this->factory->post->create( array( 'post_title' => 'test four' ) ),
			$this->factory->post->create( array( 'post_title' => 'test five' ) ),
			$this->factory->post->create( array( 'post_title' => 'test six' ) ),
			$this->factory->post->create( array( 'post_title' => 'test seven' ) ),
			$this->factory->post->create( array( 'post_title' => 'test eight' ) ),
			$this->factory->post->create( array( 'post_title' => 'searchpress' ) ),
		);

		sp_index_flush_data();

		// This domain is used in unit tests, and we'll get a 404 from trying to use it with ES
		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com', 'active' => false ) );

		SP_Sync_Manager()->do_cron_reindex();
		SP_Sync_Meta()->bulk = 3;
		SP_Sync_Meta()->save();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );
		sp_tests_fake_cron();

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
	}

	function test_singular_index_invalid_response() {
		SP_Config()->update_settings( array( 'host' => 'http://localhost', 'active' => true ) );

		$posts = array(
			$this->factory->post->create( array( 'post_title' => 'searchpress' ) ),
		);

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	function test_singular_index_non_200() {
		// This domain is used in unit tests, and we'll get a 404 from trying to use it with ES
		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com', 'active' => true ) );

		$posts = array(
			$this->factory->post->create( array( 'post_title' => 'searchpress' ) ),
		);

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	public function test_invalid_data() {
		// Elasticsearch will fail to index a post if the date is in the wrong
		// format.
		add_filter( 'sp_post_pre_index', function( $data ) {
			$data['post_date']['date'] = rand_str();
			return $data;
		} );
		$this->assertFalse( SP_Sync_Meta()->has_errors() );
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'publish' ) );
		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	public function test_empty_data() {
		// Send ES empty data
		add_filter( 'sp_post_pre_index', '__return_empty_array' );
		$this->assertFalse( SP_Sync_Meta()->has_errors() );
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'publish' ) );
		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	public function test_oembed_meta_keys() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'publish' ) );
		add_post_meta( $post_id, '_oembed_test', rand_str() );
		SP_Sync_Manager()->sync_post( $post_id );
		SP_API()->post( '_refresh' );

		$posts = sp_wp_search( array( 'fields' => array( 'post_meta._oembed_test.raw' ) ), true );
		$results = sp_results_pluck( $posts, 'post_meta._oembed_test.raw' );

		$this->assertEmpty( $results );
	}

	// @todo Test updating terms
	// @todo Test deleting terms

}