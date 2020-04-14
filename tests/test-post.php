<?php

/**
 * @group post
 */
class Tests_Post extends WP_UnitTestCase {
	protected static $sp_post;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		$cat_a = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-a' ) );
		$cat_b = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-b' ) );

		$post_id = self::factory()->post->create( array(
			'post_title' => 'lorem-ipsum',
			'post_date' => '2009-07-01 00:00:00',
			'tags_input' => array( 'tag-a', 'tag-b' ),
			'post_category' => array( $cat_a, $cat_b ),
		) );
		update_post_meta( $post_id, '_test_key_1', 'test meta string' );
		update_post_meta( $post_id, '_test_key_2', 721 );
		update_post_meta( $post_id, '_test_key_3', array( 'foo' => array( 'bar' => array( 'bat' => true ) ) ) );

		$post = get_post( $post_id );
		self::$sp_post = new SP_Post( $post );
	}

	function test_getting_attributes() {
		$this->assertEquals( 'lorem-ipsum', self::$sp_post->post_name );
		$this->assertEquals( 'test meta string', self::$sp_post->post_meta['_test_key_1'][0]['raw'] );
		$this->assertEquals( 721, self::$sp_post->post_meta['_test_key_2'][0]['long'] );
		$this->assertEquals( 'cat-a', self::$sp_post->terms['category'][0]['slug'] );
	}

	function test_setting_attributes() {
		self::$sp_post->post_name = 'new-name';
		$this->assertEquals( 'new-name', self::$sp_post->post_name );
	}
}
