<?php

/**
 * @group indexing
 */
class Tests_Indexing extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		sp_index_flush_data();
	}

	function search_and_get_field( $args, $field = 'post_name' ) {
		$args = wp_parse_args( $args, array(
			'fields' => $field
		) );
		$posts = SP_Search()->wp_search( $args );
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

}