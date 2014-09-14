<?php

/**
 * @group indexing
 */
class Tests_Indexing extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		sp_index_flush_data();
		SP_Sync_Meta()->reset();
		if ( $ts = wp_next_scheduled( 'sp_reindex' ) ) {
			wp_unschedule_event( $ts, 'sp_reindex' );
		}
	}

	function tearDown() {
		SP_Config()->update_settings( array( 'host' => 'http://localhost:9200', 'active' => true ) );
		SP_API()->setup();
		SP_Sync_Meta()->reset( 'save' );
		sp_index_flush_data();
		if ( $ts = wp_next_scheduled( 'sp_reindex' ) ) {
			wp_unschedule_event( $ts, 'sp_reindex' );
		}
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
		if ( $ts = wp_next_scheduled( 'sp_reindex' ) ) {
			wp_unschedule_event( $ts, 'sp_reindex' );
		}

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

		$this->_fake_cron();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		$this->_fake_cron();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );

		$this->_fake_cron();
		$this->assertEmpty( wp_next_scheduled( 'sp_reindex' ) );

		SP_API()->post( '_refresh' );
		$this->assertEquals(
			array( 'searchpress' ),
			$this->search_and_get_field( array( 'query' => 'searchpress' ) )
		);
	}

	function test_index_invalid_response() {
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

		// Because we changed the host, we have to re-init SP_API
		SP_API()->setup();

		SP_Sync_Manager()->do_cron_reindex();
		SP_Sync_Meta()->bulk = 3;
		SP_Sync_Meta()->save();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );
		$this->_fake_cron();

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
	}

	function test_index_non_200() {
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

		SP_Config()->update_settings( array( 'host' => 'http://localhost/' . rand_str(), 'active' => false ) );

		// Because we changed the host, we have to re-init SP_API
		SP_API()->setup();

		SP_Sync_Manager()->do_cron_reindex();
		SP_Sync_Meta()->bulk = 3;
		SP_Sync_Meta()->save();
		$this->assertNotEmpty( wp_next_scheduled( 'sp_reindex' ) );
		$this->_fake_cron();

		$this->assertNotEmpty( SP_Sync_Meta()->messages['error'] );
	}

	// @todo Test updating terms
	// @todo Test deleting terms

	/**
	 * Fakes a cron job
	 */
	function _fake_cron() {
		$crons = _get_cron_array();
		foreach ( $crons as $timestamp => $cronhooks ) {
			foreach ( $cronhooks as $hook => $keys ) {
				if ( substr( $hook, 0, 3 ) !== 'sp_' ) {
					continue; // only run our own jobs.
				}

				foreach ( $keys as $k => $v ) {
					$schedule = $v['schedule'];

					if ( $schedule != false ) {
						$new_args = array( $timestamp, $schedule, $hook, $v['args'] );
						call_user_func_array( 'wp_reschedule_event', $new_args );
					}

					wp_unschedule_event( $timestamp, $hook, $v['args'] );
					do_action_ref_array( $hook, $v['args'] );
				}
			}
		}
	}
}