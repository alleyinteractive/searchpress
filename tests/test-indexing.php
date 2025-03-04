<?php

/**
 * @group indexing
 */
class Tests_Indexing extends SearchPress_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		sp_index_flush_data();
		sp_add_sync_hooks();
	}

	function test_new_post() {
		self::factory()->post->create( array( 'post_title' => 'test post' ) );
		SP_API()->post( '_refresh' );

		$this->assertEquals(
			array( 'test-post' ),
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
	}

	public function post_statuses_data() {
		return [
			// status       index  search  ...register_post_status args
			// ------------+------+-------+--------------------------------
			// Core post statuses
			[ 'publish',    true,  true ],
			[ 'future',     true,  false ],
			[ 'draft',      true,  false ],
			[ 'pending',    true,  false ],
			[ 'private',    true,  false ],
			[ 'trash',      false, false ],
			[ 'auto-draft', false, false ],
			[ 'inherit',    true,  false ], // 'inherit' without a parent

			// custom post statuses
			[ 'cps-1',      false, false, [] ], // Assumed to be internal
			[ 'cps-2',      true,  true,  [ 'internal' => false ] ],
			[ 'cps-3',      false, false, [ 'internal' => true ] ],
			[ 'cps-4',      true,  true,  [ 'public' => false ] ],
			[ 'cps-5',      true,  true,  [ 'public' => true ] ],
			[ 'cps-6',      true,  true,  [ 'public' => true, 'exclude_from_search' => false ] ],
			[ 'cps-7',      true,  false, [ 'public' => true, 'exclude_from_search' => true ] ],
			[ 'cps-8',      true,  false, [ 'public' => true, 'private' => true ] ],
			[ 'cps-9',      true,  false, [ 'public' => true, 'protected' => true ] ],
			[ 'cps-10',     false, false, [ 'public' => true, 'internal' => true ] ],
			[ 'cps-11',     true,  false, [ 'private' => true ] ],
			[ 'cps-12',     true,  false, [ 'protected' => true ] ],
			[ 'cps-13',     false, false, [ 'exclude_from_search' => false ] ], // Assumed to be internal
			[ 'cps-14',     false, false, [ 'exclude_from_search' => true ] ], // Assumed to be internal
			[ 'cps-15',     true,  true,  [ 'internal' => false, 'exclude_from_search' => false ] ],
			[ 'cps-16',     true,  false, [ 'internal' => false, 'exclude_from_search' => true ] ],
			[ 'cps-17',     true,  false, [ 'private' => true, 'exclude_from_search' => false ] ],
			[ 'cps-18',     true,  false, [ 'private' => true, 'exclude_from_search' => true ] ],
			[ 'cps-19',     true,  false, [ 'protected' => true, 'exclude_from_search' => false ] ],
			[ 'cps-20',     true,  false, [ 'protected' => true, 'exclude_from_search' => true ] ],
		];
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
		$post_id = self::factory()->post->create( $args );
		SP_API()->post( '_refresh' );

		// Test the indexability of this status
		$results = SP_API()->get( SP_API()->get_api_endpoint( '_doc', $post_id ), '', ARRAY_A );
		$this->assertSame(
			$index,
			! empty( $results['_source']['post_id'] ) && $results['_source']['post_id'] === $post_id,
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
		$post_id = self::factory()->post->create();
		self::factory()->attachment->create_object( 'image.jpg', $post_id, array(
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_title'     => 'test attachment',
			'post_name'      => 'test-attachment-1',
		) );
		SP_API()->post( '_refresh' );

		// Test the searchability (and inherent indexability) of this status
		$this->assertSame(
			array( 'test-attachment-1' ),
			$this->search_and_get_field( array( 'query' => 'test attachment', 'post_status' => [ 'inherit', 'publish' ] ) ),
			'Inherit publish status should be searchable'
		);
	}

	public function test_post_status_inherit_draft() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		self::factory()->attachment->create_object( 'image.jpg', $post_id, array(
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_title'     => 'test attachment',
			'post_name'      => 'test-attachment-2',
		) );
		SP_API()->post( '_refresh' );

		// Test the searchability (and inherent indexability) of this status
		$this->assertSame(
			array(),
			$this->search_and_get_field( array( 'query' => 'test attachment' ) ),
			'Inherit draft status should not be searchable'
		);
	}

	public function test_orphan_post_status_inherit() {
		self::factory()->attachment->create_object( 'image.jpg', 0, [
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_title'     => 'test attachment',
			'post_name'      => 'test-attachment-3',
		] );
		SP_API()->post( '_refresh' );

		// Test the searchability (and inherent indexability) of this status
		$this->assertSame(
			[],
			$this->search_and_get_field( [ 'query' => 'test attachment' ] ),
			'Inherit status without parent should not be searchable'
		);
	}

	public function post_types_data() {
		return [
			// post type       index  search  ...register_post_type args
			// ---------------+------+-------+-----------------------------
			// Core post types
			[ 'post',          true,  true ],
			[ 'page',          true,  true ],
			[ 'attachment',    true,  false ],
			[ 'revision',      false, false ],
			[ 'nav_menu_item', false, false ],

			// Custom post types
			[ 'cpt-1',         false, false, [] ],
			[ 'cpt-2',         true,  true,  [ 'public' => true ] ],
			[ 'cpt-3',         true,  false, [ 'show_ui' => true ] ],
			[ 'cpt-4',         true,  true,  [ 'exclude_from_search' => false ] ],
			[ 'cpt-5',         true,  true,  [ 'public' => true, 'exclude_from_search' => false ] ],
			[ 'cpt-6',         true,  false, [ 'public' => true, 'exclude_from_search' => true ] ],
			[ 'cpt-7',         true,  true,  [ 'show_ui' => true, 'exclude_from_search' => false ] ],
			[ 'cpt-8',         true,  false, [ 'show_ui' => true, 'exclude_from_search' => true ] ],
			[ 'cpt-9',         true,  true,  [ 'public' => true, 'show_ui' => false ] ],
			[ 'cpt-10',        true,  true,  [ 'public' => true, 'show_ui' => true ] ],
			[ 'cpt-11',        true,  true,  [ 'public' => true, 'show_ui' => false, 'exclude_from_search' => false ] ],
			[ 'cpt-12',        true,  false, [ 'public' => true, 'show_ui' => true, 'exclude_from_search' => true ] ],
		];
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
			$parent_id = self::factory()->post->create();
			$post_id = self::factory()->attachment->create_object( 'image.jpg', $parent_id, array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
				'post_title'     => 'test post',
			) );
		} else {
			$post_id = self::factory()->post->create( array( 'post_title' => 'test post', 'post_type' => $type ) );
		}
		SP_API()->post( '_refresh' );

		// Test the indexability of this type
		$results = SP_API()->get( SP_API()->get_api_endpoint( '_doc', $post_id ), '', ARRAY_A );
		$this->assertSame(
			$index,
			! empty( $results['_source']['post_id'] ) && $results['_source']['post_id'] === $post_id,
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
		$post_id = self::factory()->post->create( array( 'post_title' => 'test post' ) );
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
		$post_id = self::factory()->post->create( array( 'post_title' => 'test post' ) );
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
		$post_id = self::factory()->post->create( array( 'post_title' => 'test post' ) );
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
		$post_id = self::factory()->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'draft' ) );
		SP_API()->post( '_refresh' );
		$this->assertEmpty(
			$this->search_and_get_field( array( 'query' => 'test post' ) )
		);
		$this->assertSame(
			array( 'draft' ),
			$this->search_and_get_field( array( 'query' => 'test post', 'post_status' => array_values( get_post_stati() ) ), 'post_status' )
		);

		wp_publish_post( $post_id );
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
			self::factory()->post->create( array( 'post_title' => 'test one' ) ),
			self::factory()->post->create( array( 'post_title' => 'test two' ) ),
			self::factory()->post->create( array( 'post_title' => 'test three' ) ),
			self::factory()->post->create( array( 'post_title' => 'test four' ) ),
			self::factory()->post->create( array( 'post_title' => 'test five' ) ),
			self::factory()->post->create( array( 'post_title' => 'test six' ) ),
			self::factory()->post->create( array( 'post_title' => 'test seven' ) ),
			self::factory()->post->create( array( 'post_title' => 'test eight' ) ),
			self::factory()->post->create( array( 'post_title' => 'searchpress' ) ),
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

		$this->fake_cron();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		$this->fake_cron();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		$this->fake_cron();
		$this->assertEmpty( wp_next_scheduled( 'sp_reindex' ) );

		SP_API()->post( '_refresh' );
		$this->assertEquals(
			array( 'searchpress' ),
			$this->search_and_get_field( array( 'query' => 'searchpress' ) )
		);
	}

	function test_cron_index_invalid_response() {
		$posts = array(
			self::factory()->post->create( array( 'post_title' => 'test one' ) ),
			self::factory()->post->create( array( 'post_title' => 'test two' ) ),
			self::factory()->post->create( array( 'post_title' => 'test three' ) ),
			self::factory()->post->create( array( 'post_title' => 'test four' ) ),
			self::factory()->post->create( array( 'post_title' => 'test five' ) ),
			self::factory()->post->create( array( 'post_title' => 'test six' ) ),
			self::factory()->post->create( array( 'post_title' => 'test seven' ) ),
			self::factory()->post->create( array( 'post_title' => 'test eight' ) ),
			self::factory()->post->create( array( 'post_title' => 'searchpress' ) ),
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
			self::factory()->post->create( array( 'post_title' => 'test one' ) ),
			self::factory()->post->create( array( 'post_title' => 'test two' ) ),
			self::factory()->post->create( array( 'post_title' => 'test three' ) ),
			self::factory()->post->create( array( 'post_title' => 'test four' ) ),
			self::factory()->post->create( array( 'post_title' => 'test five' ) ),
			self::factory()->post->create( array( 'post_title' => 'test six' ) ),
			self::factory()->post->create( array( 'post_title' => 'test seven' ) ),
			self::factory()->post->create( array( 'post_title' => 'test eight' ) ),
			self::factory()->post->create( array( 'post_title' => 'searchpress' ) ),
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
			self::factory()->post->create( array( 'post_title' => 'searchpress' ) ),
		);

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	function test_singular_index_non_200() {
		// This domain is used in unit tests, and we'll get a 404 from trying to use it with ES
		SP_Config()->update_settings( array( 'host' => 'http://asdftestblog1.files.wordpress.com', 'active' => true ) );

		$posts = array(
			self::factory()->post->create( array( 'post_title' => 'searchpress' ) ),
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
		$post_id = self::factory()->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'publish' ) );
		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	public function test_empty_data() {
		// Send ES empty data
		add_filter( 'sp_post_pre_index', '__return_empty_array' );
		$this->assertFalse( SP_Sync_Meta()->has_errors() );
		$post_id = self::factory()->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'publish' ) );
		$this->assertTrue( SP_Sync_Meta()->has_errors() );
	}

	public function test_oembed_meta_keys() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'test post', 'post_name' => 'test-post', 'post_status' => 'publish' ) );
		add_post_meta( $post_id, '_oembed_test', rand_str() );
		SP_Sync_Manager()->sync_post( $post_id );
		SP_API()->post( '_refresh' );

		$posts = sp_wp_search( array( 'fields' => array( 'post_meta._oembed_test.raw' ) ), true );
		$results = sp_results_pluck( $posts, 'post_meta._oembed_test.raw' );

		$this->assertEmpty( $results );
	}

	public function test_counts() {
		SP_Sync_Manager()->published_posts = false;
		$this->assertSame( 0, SP_Sync_Manager()->count_posts() );
		$this->assertSame( 0, SP_Sync_Manager()->count_posts_indexed() );

		self::factory()->post->create( array( 'post_title' => 'test post 1', 'post_name' => 'test-post-1', 'post_status' => 'publish' ) );
		self::factory()->post->create( array( 'post_title' => 'test post 2', 'post_name' => 'test-post-2', 'post_status' => 'publish' ) );
		self::factory()->post->create( array( 'post_title' => 'test post 3', 'post_name' => 'test-post-3', 'post_status' => 'publish' ) );
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
		self::factory()->post->create( array( 'post_title' => 'test count 1', 'post_type' => 'attachment',    'post_status' => 'inherit' ) );
		self::factory()->post->create( array( 'post_title' => 'test count 2', 'post_type' => 'page',          'post_status' => 'publish' ) );
		self::factory()->post->create( array( 'post_title' => 'test count 3', 'post_type' => 'post',          'post_status' => 'draft' ) );
		self::factory()->post->create( array( 'post_title' => 'test count 4', 'post_type' => 'post',          'post_status' => 'future' ) );
		self::factory()->post->create( array( 'post_title' => 'test count 5', 'post_type' => 'post',          'post_status' => 'publish' ) );

		// These should not be indexed:
		self::factory()->post->create( array( 'post_title' => 'test count 6', 'post_type' => 'nav_menu_item', 'post_status' => 'publish' ) );
		self::factory()->post->create( array( 'post_title' => 'test count 7', 'post_type' => 'post',          'post_status' => 'auto-draft' ) );
		self::factory()->post->create( array( 'post_title' => 'test count 8', 'post_type' => 'revision',      'post_status' => 'inherit' ) );
		SP_API()->post( '_refresh' );
		SP_Sync_Manager()->published_posts = false;

		$this->assertSame( 5, SP_Sync_Manager()->count_posts() );
		$this->assertSame( 5, SP_Sync_Manager()->count_posts_indexed() );
	}
}
