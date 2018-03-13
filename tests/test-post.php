<?php

/**
 * @group post
 */
class Tests_Post extends SearchPress_UnitTestCase {

	function setUp() {
		parent::setUp();

		$cat_a = $this->factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'cat-a',
			)
		);
		$cat_b = $this->factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'cat-b',
			)
		);

		$post_id      = $this->factory->post->create(
			array(
				'post_title'    => 'lorem-ipsum',
				'post_date'     => '2009-07-01 00:00:00',
				'tags_input'    => array( 'tag-a', 'tag-b' ),
				'post_category' => array( $cat_a, $cat_b ),
			)
		);
		$this->meta_1 = rand_str();
		$this->meta_3 = array( 'foo' => array( 'bar' => array( 'bat' => true ) ) );
		update_post_meta( $post_id, '_test_key_1', $this->meta_1 );
		update_post_meta( $post_id, '_test_key_2', 721 );
		update_post_meta( $post_id, '_test_key_3', $this->meta_3 );

		$post          = get_post( $post_id );
		$this->sp_post = new SP_Post( $post );
	}

	function test_getting_attributes() {
		$this->assertEquals( 'lorem-ipsum', $this->sp_post->post_name );
		$this->assertEquals( $this->meta_1, $this->sp_post->post_meta['_test_key_1'][0]['raw'] );
		$this->assertEquals( 721, $this->sp_post->post_meta['_test_key_2'][0]['long'] );
		$this->assertEquals( 'cat-a', $this->sp_post->terms['category'][0]['slug'] );
	}

	function test_setting_attributes() {
		$this->sp_post->post_name = 'new-name';
		$this->assertEquals( 'new-name', $this->sp_post->post_name );
	}

}
