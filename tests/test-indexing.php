<?php

/**
 * @group indexing
 */
class Tests_Indexing extends SearchPress_UnitTestCase {

	function test_new_post() {
		$this->factory->post->create( array( 'post_title' => 'test post' ) );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
	}

	public function post_statuses_data() {
		return array(
			//      status       index  search  ...register_post_status args
			// -----------------+------+-------+--------------------------------
			// Core post statuses
			array( 'publish',    true,  true ),
			array( 'future',     true,  false ),
			array( 'draft',      true,  false ),
			array( 'pending',    true,  false ),
			array( 'private',    true,  false ),
			array( 'trash',      false, false ),
			array( 'auto-draft', false, false ),
			array( 'inherit',    true,  true ), // 'inherit' without a parent

			// custom post statuses
			array( 'cps-1',      false, false, array() ), // Assumed to be internal
			array( 'cps-2',      true,  true,  array( 'internal' => false ) ),
			array( 'cps-3',      false, false, array( 'internal' => true ) ),
			array( 'cps-4',      true,  true,  array( 'public' => false ) ),
			array( 'cps-5',      true,  true,  array( 'public' => true ) ),
			array( 'cps-6',      true,  true,  array( 'public' => true, 'exclude_from_search' => false ) ),
			array( 'cps-7',      true,  false, array( 'public' => true, 'exclude_from_search' => true ) ),
			array( 'cps-8',      true,  false, array( 'public' => true, 'private' => true ) ),
			array( 'cps-9',      true,  false, array( 'public' => true, 'protected' => true ) ),
			array( 'cps-10',     false, false, array( 'public' => true, 'internal' => true ) ),
			array( 'cps-11',     true,  false, array( 'private' => true ) ),
			array( 'cps-12',     true,  false, array( 'protected' => true ) ),
			array( 'cps-13',     false, false, array( 'exclude_from_search' => false ) ), // Assumed to be internal
			array( 'cps-14',     false, false, array( 'exclude_from_search' => true ) ), // Assumed to be internal
			array( 'cps-15',     true,  true,  array( 'internal' => false, 'exclude_from_search' => false ) ),
			array( 'cps-16',     true,  false, array( 'internal' => false, 'exclude_from_search' => true ) ),
			array( 'cps-17',     true,  false, array( 'private' => true, 'exclude_from_search' => false ) ),
			array( 'cps-18',     true,  false, array( 'private' => true, 'exclude_from_search' => true ) ),
			array( 'cps-19',     true,  false, array( 'protected' => true, 'exclude_from_search' => false ) ),
			array( 'cps-20',     true,  false, array( 'protected' => true, 'exclude_from_search' => true ) ),
		);
	}

	/**
	 * @dataProvider post_statuses_data
	 * @param  string $status Post status.
	 * @param  bool $index  Should this be indexed?
	 * @param  bool $search Should this be searchable by default?
	 * @param  array $cs_args Optional. If present, $status is assumed to be a
	 *                        custom post status and will be registered.
	 */
	public function test_post_statuses( $status, $index, $search, $cs_args = false ) {
		if ( $cs_args ) {
			register_post_status( $status, $cs_args );

			// Reload the searchable post status list.
			SP_Config()->post_statuses = null;
			sp_searchable_post_statuses( true );
		}

		// Build the post.
		$args = array( 'post_title' => 'test post', 'post_status' => $status );
		if ( 'future' === $status ) {
			$args['post_date'] = date( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS );
		}
		$post_id = $this->factory->post->create( $args );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		// Test the indexability of this status
		$results = $this->search_and_get_field( array( 'query' => 'test post', 'post_status' => $status ) );
		$this->assertSame(
			$index,
			! empty( $results ),
			'Post status should' . ( $index ? ' ' : ' not ' ) . 'be indexed'
		);

		// Test the searchability of this status
		$results = $this->search_and_get_field( array( 'query' => 'test post' ) );
		$this->assertSame(
			$search,
			! empty( $results ),
			'Post status should' . ( $search ? ' ' : ' not ' ) . 'be searchable'
		);
	}

	public function test_post_status_inherit_publish() {
		$post_id = $this->factory->post->create();
		$attachment_id = $this->factory->attachment->create_object( 'image.jpg', $post_id, array(
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_title'     => 'test attachment',
			'post_name'      => 'test-attachment-1',
		) );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		// Test the searchability (and inherent indexability) of this status
		$this->assertSame(
			array( 'test-attachment-1' ),
			$this->search_and_get_field( array( 'query' => 'test attachment' ) ),
			'Inherit publish status should be searchable'
		);
	}

	public function test_post_status_inherit_draft() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$attachment_id = $this->factory->attachment->create_object( 'image.jpg', $post_id, array(
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_title'     => 'test attachment',
			'post_name'      => 'test-attachment-2',
		) );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		// Test the searchability (and inherent indexability) of this status
		$this->assertSame(
			array(),
			$this->search_and_get_field( array( 'query' => 'test attachment' ) ),
			'Inherit draft status should not be searchable'
		);
	}

	public function test_orphan_post_status_inherit() {
		$attachment_id = $this->factory->attachment->create_object( 'image.jpg', 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_title'     => 'test attachment',
			'post_name'      => 'test-attachment-3',
		) );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		// Test the searchability (and inherent indexability) of this status
		$this->assertSame(
			array( 'test-attachment-3' ),
			$this->search_and_get_field( array( 'query' => 'test attachment' ) ),
			'Inherit status without parent should be searchable'
		);
	}

	public function post_types_data() {
		return array(
			//      post type       index  search  ...register_post_type args
			// --------------------+------+-------+-----------------------------
			// Core post types
			array( 'post',          true,  true ),
			array( 'page',          true,  true ),
			array( 'attachment',    true,  true ),
			array( 'revision',      false, false ),
			array( 'nav_menu_item', false, false ),

			// Custom post types
			array( 'cpt-1',         false, false, array() ),
			array( 'cpt-2',         true,  true,  array( 'public' => true ) ),
			array( 'cpt-3',         true,  false, array( 'show_ui' => true ) ),
			array( 'cpt-4',         true,  true,  array( 'exclude_from_search' => false ) ),
			array( 'cpt-5',         true,  true,  array( 'public' => true, 'exclude_from_search' => false ) ),
			array( 'cpt-6',         true,  false, array( 'public' => true, 'exclude_from_search' => true ) ),
			array( 'cpt-7',         true,  true,  array( 'show_ui' => true, 'exclude_from_search' => false ) ),
			array( 'cpt-8',         true,  false, array( 'show_ui' => true, 'exclude_from_search' => true ) ),
			array( 'cpt-9',         true,  true,  array( 'public' => true, 'show_ui' => false ) ),
			array( 'cpt-10',        true,  true,  array( 'public' => true, 'show_ui' => true ) ),
			array( 'cpt-11',        true,  true,  array( 'public' => true, 'show_ui' => false, 'exclude_from_search' => false ) ),
			array( 'cpt-12',        true,  false, array( 'public' => true, 'show_ui' => true, 'exclude_from_search' => true ) ),
		);
	}

	/**
	 * @dataProvider post_types_data
	 * @param  string $type Post type.
	 * @param  bool $index  Should this be indexed?
	 * @param  bool $search Should this be searchable by default?
	 * @param  array $cpt_args Optional. If present, $type is assumed to be a
	 *                         custom post type and will be registered.
	 */
	public function test_post_types( $type, $index, $search, $cpt_args = false ) {
		if ( $cpt_args ) {
			register_post_type( $type, $cpt_args );

			// Reload the searchable post status list.
			SP_Config()->post_types = null;
			sp_searchable_post_types( true );
		}

		// Build the post.
		if ( 'attachment' === $type ) {
			$parent_id = $this->factory->post->create();
			$post_id = $this->factory->attachment->create_object( 'image.jpg', $parent_id, array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'test post',
			) );
		} else {
			$post_id = $this->factory->post->create( array( 'post_title' => 'test post', 'post_type' => $type ) );
		}

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		// Test the indexability of this type
		$results = $this->search_and_get_field( array( 'query' => 'test post', 'post_type' => $type ) );
		$this->assertSame(
			$index,
			! empty( $results ),
			'Post type should' . ( $index ? ' ' : ' not ' ) . 'be indexed'
		);

		// Test the searchability of this type
		$results = $this->search_and_get_field( array( 'query' => 'test post' ) );
		$this->assertSame(
			$search,
			! empty( $results ),
			'Post type should' . ( $search ? ' ' : ' not ' ) . 'be searchable'
		);

	}

	function test_updated_post() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post' ) );
		$post = array(
			'ID' => $post_id,
			'post_title' => 'lorem ipsum'
		);
		wp_update_post( $post );

		// Trigger post index.
		$this->fake_cron();

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

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);

		wp_trash_post( $post_id );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
	}

	function test_deleted_post() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post' ) );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);

		wp_delete_post( $post_id, true );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
	}

	function test_publishing_and_unpublishing_posts() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'draft' ) );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );
		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
		$this->assertSame(
			array( 'draft' ),
			$this->search_and_get_field( array( 'query' => 'test post', 'post_status' => array_values( get_post_stati() ) ), 'post_status' )
		);

		wp_publish_post( $post_id );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );
		$this->assertNotEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
		$this->assertSame(
			array( 'publish' ),
			$this->search_and_get_field( array( 'query' => 'test post' ), 'post_status' )
		);

		$post = array(
			'ID' => $post_id,
			'post_status' => 'draft'
		);
		wp_update_post( $post );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );
		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
		$this->assertSame(
			array( 'draft' ),
			$this->search_and_get_field( array( 'query' => 'test post', 'post_status' => array_values( get_post_stati() ) ), 'post_status' )
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

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );
		$this->assertEquals(
			array( 'searchpress' ),
			$this->search_and_get_field( array( 'query' => 'searchpress' ) )
		);

		sp_index_flush_data();

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );
		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'searchpress' ) )
		);

		SP_Config()->update_settings( array( 'active' => false ) );
		SP_Sync_Manager()->do_cron_reindex();
		SP_Sync_Meta()->bulk = 3;
		SP_Sync_Meta()->save();

		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		$this->fake_cron();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		$this->fake_cron();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		$this->fake_cron();
		$this->assertEmpty( wp_next_scheduled( 'sp_reindex' ) );

		// Trigger post index.
		$this->fake_cron();

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
		$this->fake_cron();

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
		$this->fake_cron();

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
	}

	function test_singular_index_invalid_response() {
		SP_Config()->update_settings( array( 'host' => 'http://localhost', 'active' => true ) );

		$posts = array(
			$this->factory->post->create( array( 'post_title' => 'searchpress' ) ),
		);

		// Trigger post index.
		$this->fake_cron();

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	function test_singular_index_non_200() {
		// This domain is used in unit tests, and we'll get a 404 from trying to use it with ES
		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com', 'active' => true ) );

		$posts = array(
			$this->factory->post->create( array( 'post_title' => 'searchpress' ) ),
		);

		// Trigger post index.
		$this->fake_cron();

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

		// Trigger post index.
		$this->fake_cron();

		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	public function test_empty_data() {
		// Send ES empty data
		add_filter( 'sp_post_pre_index', '__return_empty_array' );
		$this->assertFalse( SP_Sync_Meta()->has_errors() );
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'publish' ) );

		// Trigger post index.
		$this->fake_cron();

		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	public function test_oembed_meta_keys() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'publish' ) );
		add_post_meta( $post_id, '_oembed_test', rand_str() );
		SP_Sync_Manager()->sync_post( $post_id );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		$posts = sp_wp_search( array( 'fields' => array( 'post_meta._oembed_test.raw' ) ), true );
		$results = sp_results_pluck( $posts, 'post_meta._oembed_test.raw' );

		$this->assertEmpty( $results );
	}

	public function test_counts() {
		SP_Sync_Manager()->published_posts = false;
		$this->assertSame( 0, SP_Sync_Manager()->count_posts() );
		$this->assertSame( 0, SP_Sync_Manager()->count_posts_indexed() );

		$this->factory->post->create( array( 'post_title' => 'test post 1', 'post_name' => 'test-post-1', 'post_status' => 'publish' ) );
		$this->factory->post->create( array( 'post_title' => 'test post 2', 'post_name' => 'test-post-2', 'post_status' => 'publish' ) );
		$this->factory->post->create( array( 'post_title' => 'test post 3', 'post_name' => 'test-post-3', 'post_status' => 'publish' ) );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );

		SP_Sync_Manager()->published_posts = false;
		$this->assertSame( 3, SP_Sync_Manager()->count_posts() );
		$this->assertSame( 3, SP_Sync_Manager()->count_posts_indexed() );
	}

	public function test_counts_mixed_types_and_statuses() {
		SP_Sync_Manager()->published_posts = false;
		$this->assertSame( 0, SP_Sync_Manager()->count_posts() );
		$this->assertSame( 0, SP_Sync_Manager()->count_posts_indexed() );

		// These should be indexed:
		$this->factory->post->create( array( 'post_title' => 'test count 1', 'post_type' => 'attachment',    'post_status' => 'inherit' ) );
		$this->factory->post->create( array( 'post_title' => 'test count 2', 'post_type' => 'page',          'post_status' => 'publish' ) );
		$this->factory->post->create( array( 'post_title' => 'test count 3', 'post_type' => 'post',          'post_status' => 'draft' ) );
		$this->factory->post->create( array( 'post_title' => 'test count 4', 'post_type' => 'post',          'post_status' => 'future' ) );
		$this->factory->post->create( array( 'post_title' => 'test count 5', 'post_type' => 'post',          'post_status' => 'publish' ) );

		// These should not be indexed:
		$this->factory->post->create( array( 'post_title' => 'test count 6', 'post_type' => 'nav_menu_item', 'post_status' => 'publish' ) );
		$this->factory->post->create( array( 'post_title' => 'test count 7', 'post_type' => 'post',          'post_status' => 'auto-draft' ) );
		$this->factory->post->create( array( 'post_title' => 'test count 8', 'post_type' => 'revision',      'post_status' => 'inherit' ) );

		// Trigger post index.
		$this->fake_cron();

		SP_API()->post( '_refresh' );
		SP_Sync_Manager()->published_posts = false;

		$this->assertSame( 5, SP_Sync_Manager()->count_posts() );
		$this->assertSame( 5, SP_Sync_Manager()->count_posts_indexed() );
	}
}
