<?php

/**
 * @group search
 */
class Tests_Searching extends SearchPress_UnitTestCase {
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::create_sample_content();
	}

	function test_basic_query() {
		$this->assertEquals(
			array(
				'tags-a-and-c',
				'tags-b-and-c',
				'tags-a-and-b',
				'tag-c',
				'tag-b',
				'tag-a',
				'tags-a-b-c',
				'raw-html-code',
				'simple-markup-test',
				'embedded-video',
			),
			$this->search_and_get_field( array() )
		);
	}

	function test_query_tag_a() {
		$this->assertEquals(
			array(
				'tags-a-and-c',
				'tags-a-and-b',
				'tag-a',
				'tags-a-b-c',
			),
			$this->search_and_get_field( array( 'terms' => array( 'post_tag' => 'tag-a' ) ) )
		);
	}

	function test_query_tag_b() {
		$this->assertEquals(
			array(
				'tags-b-and-c',
				'tags-a-and-b',
				'tag-b',
				'tags-a-b-c',
			),
			$this->search_and_get_field( array( 'terms' => array( 'post_tag' => 'tag-b' ) ) )
		);
	}

	function test_query_tag_nun() {
		$this->assertEquals(
			array( 'tag-%d7%a0' ),
			$this->search_and_get_field( array( 'terms' => array( 'post_tag' => 'tag-נ' ) ) )
		);
	}

	function test_query_tags_union() {
		$this->assertEquals(
			array(
				'tags-a-and-c',
				'tags-b-and-c',
				'tags-a-and-b',
				'tag-c',
				'tag-b',
				'tags-a-b-c',
			),
			$this->search_and_get_field( array( 'terms' => array( 'post_tag' => 'tag-b,tag-c' ) ) )
		);
	}

	function test_query_tags_intersection() {
		$this->assertEquals(
			array(
				'tags-a-and-c',
				'tags-a-b-c',
			),
			$this->search_and_get_field( array( 'terms' => array( 'post_tag' => 'tag-a+tag-c' ) ) )
		);
	}

	function test_query_category_name() {
		$this->assertEquals(
			array(
				'cat-a',
				'cats-a-and-c',
				'cats-a-and-b',
				'cats-a-b-c',
			),
			$this->search_and_get_field( array( 'terms' => array( 'category' => 'cat-a' ) ) )
		);
	}

	public function data_for_query_sorting() {
		return array(
			array(
				array(
					'tags-a-and-c',
					'tags-b-and-c',
					'tags-a-and-b',
					'tag-c',
					'tag-b',
					'tag-a',
					'tags-a-b-c',
					'raw-html-code',
					'simple-markup-test',
					'embedded-video',
				),
				array( 'orderby' => 'date', 'order' => 'desc' ),
				'orderby => date desc',
			),
			array(
				array(
					'parent-one',
					'parent-two',
					'parent-three',
					'child-one',
					'child-two',
					'child-three',
					'child-four',
					'tag-%d7%a0',
					'cats-a-b-c',
					'cats-a-and-b',
				),
				array( 'orderby' => 'date', 'order' => 'asc' ),
				'orderby => date asc',
			),
			array(
				array(
					'tag-%d7%a0',
					'cats-a-b-c',
					'cats-a-and-b',
					'cats-b-and-c',
					'cats-a-and-c',
					'cat-a',
					'cat-b',
					'cat-c',
					'lorem-ipsum',
					'comment-test',
				),
				array( 'orderby' => 'id', 'order' => 'asc' ),
				'orderby => id asc',
			),
			array(
				array( 'cats-a-b-c', 'cat-a', 'lorem-ipsum' ),
				array( 'orderby' => 'modified', 'order' => 'desc', 'posts_per_page' => 3 ),
				'orderby => modified desc',
			),
			array(
				array( 'cats-a-b-c', 'cat-a', 'lorem-ipsum' ),
				array( 'orderby' => 'menu_order', 'order' => 'desc', 'posts_per_page' => 3 ),
				'orderby => menu_order desc',
			),
			array(
				array(
					'child-four',
					'child-three',
					'child-two',
					'child-one',
				),
				array( 'orderby' => array( 'parent' => 'desc', 'date' => 'desc' ), 'posts_per_page' => 4 ),
				'orderby => parent desc, date desc',
			),
			array(
				array( 'cat-a', 'cat-b', 'cat-c' ),
				array( 'orderby' => 'name', 'order' => 'asc', 'posts_per_page' => 3 ),
				'orderby => name asc',
			),
			array(
				array( 'cat-a', 'cat-b', 'cat-c' ),
				array( 'orderby' => 'title', 'order' => 'asc', 'posts_per_page' => 3 ),
				'orderby => title asc',
			),
			array(
				array( 'cat-a', 'cat-b', 'cat-c' ),
				array( 'orderby' => array( 'title' => 'asc' ), 'posts_per_page' => 3 ),
				'orderby => title asc',
			),
			array(
				array( 'tags-b-and-c', 'tags-a-b-c', 'tags-a-and-c' ),
				array( 'orderby' => array( 'title' => 'desc' ), 'posts_per_page' => 3 ),
				'orderby => title desc',
			),
			array(
				array( 'child-three', 'child-four', 'child-one', 'child-two' ),
				array( 'orderby' => array( 'parent' => 'desc', 'date' => 'asc' ), 'posts_per_page' => 4 ),
				'orderby => parent desc, date asc',
			),
		);
	}

	/**
	 * @dataProvider data_for_query_sorting
	 */
	function test_query_sorting( $expected, $params, $message ) {
		$this->assertEquals( $expected, $this->search_and_get_field( $params ), $message );
	}

	function test_invalid_sorting() {
		$es_args = SP_WP_Search::wp_to_es_args( array( 'orderby' => 'modified', 'order' => 'desc' ) );
		$this->assertEquals( 'desc', $es_args['sort'][0]['post_modified.date'], 'Verify es_args["sort"] exists' );

		$es_args = SP_WP_Search::wp_to_es_args( array( 'orderby' => 'modified_gmt' ) );
		$this->assertTrue( empty( $es_args['sort'] ), 'Verify es_args["sort"] exists' );
	}

	function test_query_posts_per_page() {
		$this->assertEquals(
			array(
				'tags-a-and-c',
				'tags-b-and-c',
				'tags-a-and-b',
				'tag-c',
				'tag-b',
			),
			$this->search_and_get_field( array( 'posts_per_page' => 5 ) )
		);
	}

	function test_query_offset() {
		$this->assertEquals(
			array(
				'tags-a-and-b',
				'tag-c',
				'tag-b',
				'tag-a',
				'tags-a-b-c',
				'raw-html-code',
				'simple-markup-test',
				'embedded-video',
				'contributor-post-approved',
				'one-comment',
			),
			$this->search_and_get_field( array( 'offset' => 2 ) )
		);
	}

	function test_query_paged() {
		$this->assertEquals(
			array(
				'contributor-post-approved',
				'one-comment',
				'no-comments',
				'many-trackbacks',
				'one-trackback',
				'comment-test',
				'lorem-ipsum',
				'cat-c',
				'cat-b',
				'cat-a',
			),
			$this->search_and_get_field( array( 'paged' => 2 ) )
		);
	}

	function test_query_paged_and_posts_per_page() {
		$this->assertEquals(
			array(
				'no-comments',
				'many-trackbacks',
				'one-trackback',
				'comment-test',
			),
			$this->search_and_get_field( array( 'paged' => 4, 'posts_per_page' => 4 ) )
		);
	}

	/**
	 * Skipping this test until https://core.trac.wordpress.org/ticket/18897 is
	 * resolved.
	 */
	function test_query_offset_and_paged() {
		$this->markTestSkipped();
		$this->assertEquals(
			array(
				'many-trackbacks',
				'one-trackback',
				'comment-test',
				'lorem-ipsum',
				'cat-c',
				'cat-b',
				'cat-a',
				'cats-a-and-c',
				'cats-b-and-c',
				'cats-a-and-b',
			),
			$this->search_and_get_field( array( 'paged' => 2, 'offset' => 3 ) )
		);
	}

	function test_exlude_from_search_empty() {
		global $wp_post_types;
		foreach ( array_keys( $wp_post_types ) as $slug ) {
			$wp_post_types[ $slug ]->exclude_from_search = true;
		}
		SP_Config()->post_types = null;
		sp_searchable_post_types( true );

		$this->assertEmpty( $this->search_and_get_field( array( 'post_type' => 'any' ) ) );

		foreach ( array_keys( $wp_post_types ) as $slug ) {
			$wp_post_types[ $slug ]->exclude_from_search = false;
		}
		SP_Config()->post_types = null;
		sp_searchable_post_types( true );

		$this->assertNotEmpty( $this->search_and_get_field( array( 'post_type' => 'any' ) ) );
	}

	function test_query_post_type() {
		$this->assertEmpty( $this->search_and_get_field( array( 'post_type' => 'page' ) ) );
		$this->assertNotEmpty( $this->search_and_get_field( array( 'post_type' => 'post' ) ) );
	}

	function test_basic_search() {
		$expected = array(
			'cat-c',
			'cat-b',
			'cat-a',
			'cats-a-and-c',
			'cats-b-and-c',
			'cats-a-and-b',
			'cats-a-b-c',
		);

		$this->assertEquals(
			$expected,
			$this->search_and_get_field( array( 'query' => 'cat', 'orderby' => 'date' ) )
		);
		$this->assertEquals(
			$expected,
			$this->search_and_get_field( array( 'query' => 'cats', 'orderby' => 'date' ) )
		);

		$this->assertEquals(
			array(
				'many-trackbacks',
				'one-trackback',
			),
			$this->search_and_get_field( array( 'query' => 'trackback', 'orderby' => 'date' ) )
		);
	}

	function test_query_date_ranges() {
		$this->assertEquals(
			array(
				'contributor-post-approved',
				'one-comment',
				'no-comments',
				'many-trackbacks',
				'one-trackback',
				'comment-test',
				'lorem-ipsum',
				'cat-c',
				'cat-b',
				'cat-a',
			),
			$this->search_and_get_field( array(
				'date_range' => array( 'field' => 'post_date', 'gte' => '2009-01-01', 'lt' => '2010-01-01' )
			) )
		);

		$this->assertEquals(
			array(
				'contributor-post-approved',
				'one-comment',
			),
			$this->search_and_get_field( array(
				'date_range' => array( 'field' => 'post_date', 'gt' => '2009-10-02', 'lte' => '2009-12-01 00:00:00' )
			) )
		);

		$this->assertEquals(
			array(
				'one-comment',
			),
			$this->search_and_get_field( array(
				'date_range' => array( 'field' => 'post_date', 'gte' => '2009-11-01 00:00:00', 'lt' => '2009-12-01 00:00:00' )
			) )
		);

		$this->assertEquals(
			array(
				'parent-one',
			),
			$this->search_and_get_field( array(
				'date_range' => array( 'lt' => '2007-01-01 00:00:02' )
			) )
		);
	}

	function test_search_get_posts() {
		$db_posts = get_posts( 'tag=tag-a&order=id&order=asc' );
		$sp_posts = sp_wp_search( array( 'terms' => array( 'post_tag' => 'tag-a' ), 'orderby' => 'id', 'order' => 'asc' ) );

		$this->assertEquals( $db_posts, $sp_posts );
		$this->assertInstanceOf( '\WP_Post', reset( $sp_posts ) );
		$this->assertEquals( 'tags-a-b-c', reset( $sp_posts )->post_title );
	}

	function test_raw_es() {
		$posts = sp_search( array(
			'query' => array(
				'match_all' => new stdClass(),
			),
			'_source' => array( 'post_id' ),
			'size' => 1,
			'from' => 1,
			'sort' => array(
				'post_name.raw' => 'asc',
			),
		) );
		$this->assertEquals( 'cat-b', $posts[0]->post_name );

		$s = new SP_Search( array(
			'query' => array(
				'match_all' => new stdClass
			),
			'_source' => array( 'post_id' ),
			'size' => 1,
			'from' => 2,
			'sort' => array(
				'post_name.raw' => 'asc'
			)
		) );
		$posts = $s->get_posts();
		$this->assertEquals( 'cat-c', $posts[0]->post_name );

		// Test running it again
		$posts = $s->get_posts();
		$this->assertEquals( 'cat-c', $posts[0]->post_name );

		// Verify emptiness
		$s = new SP_Search( array(
			'query' => array(
				'term' => array(
					'post_name.raw' => array(
						'value' => 'cucumbers',
					)
				)
			),
			'_source' => array( 'post_id' ),
			'size' => 1,
			'from' => 0,
			'sort' => array(
				'post_name.raw' => 'asc',
			)
		) );
		$this->assertEmpty( $s->get_posts() );
	}

	function test_query_author_vars() {
		$author_1 = self::factory()->user->create( array( 'user_login' => 'author1', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$post_1 = self::factory()->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_1, 'post_date' => '2006-01-04 00:00:00' ) );

		$author_2 = self::factory()->user->create( array( 'user_login' => 'author2', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$post_2 = self::factory()->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_2, 'post_date' => '2006-01-03 00:00:00' ) );

		$author_3 = self::factory()->user->create( array( 'user_login' => 'author3', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$post_3 = self::factory()->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_3, 'post_date' => '2006-01-02 00:00:00' ) );

		$author_4 = self::factory()->user->create( array( 'user_login' => 'author4', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$post_4 = self::factory()->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_4, 'post_date' => '2006-01-01 00:00:00' ) );

		// Index the posts.
		self::index( array( $post_1, $post_2, $post_3, $post_4 ) );

		$this->assertEquals(
			array( $author_2 ),
			$this->search_and_get_field( array( 'author' => $author_2 ), 'post_author.user_id' )
		);

		$this->assertEquals(
			array( $author_1, $author_2, $author_3, $author_4 ),
			$this->search_and_get_field( array(
				'author' => array( $author_1, $author_2, $author_3, $author_4 )
			), 'post_author.user_id' )
		);

		$this->assertEquals(
			array( $author_3 ),
			$this->search_and_get_field( array( 'author_name' => 'author3' ), 'post_author.user_id' )
		);

		$this->assertEquals(
			array( $author_1, $author_2, $author_3, $author_4 ),
			$this->search_and_get_field( array(
				'author_name' => array( 'author1', 'author2', 'author3', 'author4' )
			), 'post_author.user_id' )
		);

		$this->assertEquals(
			array( $author_4, $author_3, $author_2, $author_1 ),
			$this->search_and_get_field( array(
				'author_name' => array( 'author1', 'author2', 'author3', 'author4' ),
				'orderby' => 'author'
			), 'post_author.user_id' )
		);
	}
}
