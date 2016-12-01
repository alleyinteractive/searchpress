<?php

/**
 * @group search
 */
class Tests_Searching extends SearchPress_UnitTestCase {

	function setUp() {
		parent::setUp();

		$cat_a = $this->factory->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-a' ) );
		$cat_b = $this->factory->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-b' ) );
		$cat_c = $this->factory->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-c' ) );

		$test = $this->factory->post->create( array( 'post_title' => 'tag-נ', 'tags_input' => array( 'tag-נ' ), 'post_date' => '2008-11-01 00:00:00' ) );

		$this->factory->post->create( array( 'post_title' => 'cats-a-b-c', 'post_date' => '2008-12-01 00:00:00', 'post_category' => array( $cat_a, $cat_b, $cat_c ) ) );
		$this->factory->post->create( array( 'post_title' => 'cats-a-and-b', 'post_date' => '2009-01-01 00:00:00', 'post_category' => array( $cat_a, $cat_b ) ) );
		$this->factory->post->create( array( 'post_title' => 'cats-b-and-c', 'post_date' => '2009-02-01 00:00:00', 'post_category' => array( $cat_b, $cat_c ) ) );
		$this->factory->post->create( array( 'post_title' => 'cats-a-and-c', 'post_date' => '2009-03-01 00:00:00', 'post_category' => array( $cat_a, $cat_c ) ) );
		$this->factory->post->create( array( 'post_title' => 'cat-a', 'post_date' => '2009-04-01 00:00:00', 'post_category' => array( $cat_a ) ) );
		$this->factory->post->create( array( 'post_title' => 'cat-b', 'post_date' => '2009-05-01 00:00:00', 'post_category' => array( $cat_b ) ) );
		$this->factory->post->create( array( 'post_title' => 'cat-c', 'post_date' => '2009-06-01 00:00:00', 'post_category' => array( $cat_c ) ) );
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
		$this->factory->post->create( array( 'post_title' => 'tags-a-b-c', 'tags_input' => array( 'tag-a', 'tag-b', 'tag-c' ), 'post_date' => '2010-04-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tag-a', 'tags_input' => array( 'tag-a' ), 'post_date' => '2010-05-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tag-b', 'tags_input' => array( 'tag-b' ), 'post_date' => '2010-06-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tag-c', 'tags_input' => array( 'tag-c' ), 'post_date' => '2010-07-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tags-a-and-b', 'tags_input' => array( 'tag-a', 'tag-b' ), 'post_date' => '2010-08-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tags-b-and-c', 'tags_input' => array( 'tag-b', 'tag-c' ), 'post_date' => '2010-09-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tags-a-and-c', 'tags_input' => array( 'tag-a', 'tag-c' ), 'post_date' => '2010-10-01 00:00:00' ) );

		$this->parent_one = $this->factory->post->create( array( 'post_title' => 'parent-one', 'post_date' => '2007-01-01 00:00:01' ) );
		$this->parent_two = $this->factory->post->create( array( 'post_title' => 'parent-two', 'post_date' => '2007-01-01 00:00:02' ) );
		$this->parent_three = $this->factory->post->create( array( 'post_title' => 'parent-three', 'post_date' => '2007-01-01 00:00:03' ) );
		$this->factory->post->create( array( 'post_title' => 'child-one', 'post_parent' => $this->parent_one, 'post_date' => '2007-01-01 00:00:04' ) );
		$this->factory->post->create( array( 'post_title' => 'child-two', 'post_parent' => $this->parent_one, 'post_date' => '2007-01-01 00:00:05' ) );
		$this->factory->post->create( array( 'post_title' => 'child-three', 'post_parent' => $this->parent_two, 'post_date' => '2007-01-01 00:00:06' ) );
		$this->factory->post->create( array( 'post_title' => 'child-four', 'post_parent' => $this->parent_two, 'post_date' => '2007-01-01 00:00:07' ) );

		// Force refresh the index so the data is available immediately
		SP_API()->post( '_refresh' );
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

	function test_query_sorting() {
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
			$this->search_and_get_field( array( 'orderby' => 'date', 'order' => 'desc' ) ),
			'orderby => date desc'
		);

		$this->assertEquals(
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
			$this->search_and_get_field( array( 'orderby' => 'date', 'order' => 'asc' ) ),
			'orderby => date asc'
		);

		$this->assertEquals(
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
			$this->search_and_get_field( array( 'orderby' => 'id', 'order' => 'asc' ) ),
			'orderby => id asc'
		);

		$i = 1;
		foreach ( array( 'lorem-ipsum', 'cat-a', 'cats-a-b-c' ) as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'post' );
			wp_update_post( array( 'ID' => $post->ID, 'menu_order' => $i++ ) );
			sleep( 1 );
		}
		SP_API()->post( '_refresh' );

		$this->assertEquals(
			array(
				'cats-a-b-c',
				'cat-a',
				'lorem-ipsum',
			),
			$this->search_and_get_field( array( 'orderby' => 'modified', 'order' => 'desc', 'posts_per_page' => 3 ) ),
			'orderby => modified desc'
		);

		$this->assertEquals(
			array(
				'cats-a-b-c',
				'cat-a',
				'lorem-ipsum',
			),
			$this->search_and_get_field( array( 'orderby' => 'menu_order', 'order' => 'desc', 'posts_per_page' => 3 ) ),
			'orderby => menu_order desc'
		);

		$this->assertEquals(
			array(
				'child-four',
				'child-three',
				'child-two',
				'child-one',
			),
			$this->search_and_get_field( array( 'orderby' => array( 'parent' => 'desc', 'date' => 'desc' ), 'posts_per_page' => 4 ) ),
			'orderby => array( parent => desc, date => desc )'
		);

		$this->assertEquals(
			array(
				'cat-a',
				'cat-b',
				'cat-c',
			),
			$this->search_and_get_field( array( 'orderby' => 'name', 'order' => 'asc', 'posts_per_page' => 3 ) ),
			'orderby => name asc'
		);

		$this->assertEquals(
			array(
				'cat-a',
				'cat-b',
				'cat-c',
			),
			$this->search_and_get_field( array( 'orderby' => 'title', 'order' => 'asc', 'posts_per_page' => 3 ) ),
			'orderby => title asc'
		);

		$this->assertEquals(
			array(
				'cat-a',
				'cat-b',
				'cat-c',
			),
			$this->search_and_get_field( array( 'orderby' => array( 'title' => 'asc' ), 'posts_per_page' => 3 ) ),
			'orderby => array( title => asc )'
		);

		$this->assertEquals(
			array(
				'tags-b-and-c',
				'tags-a-b-c',
				'tags-a-and-c',
			),
			$this->search_and_get_field( array( 'orderby' => array( 'title' => 'desc' ), 'posts_per_page' => 3 ) ),
			'orderby => array( title => desc )'
		);

		$this->assertEquals(
			array(
				'child-three',
				'child-four',
				'child-one',
				'child-two',
			),
			$this->search_and_get_field( array( 'orderby' => array( 'parent' => 'desc', 'date' => 'asc' ), 'posts_per_page' => 4 ) ),
			'orderby => array( parent => desc, date => asc )'
		);

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
	 * @ticket 18897
	 */
	function test_query_offset_and_paged() {
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
		$this->assertTrue( is_a( reset( $sp_posts ), 'WP_Post' ) );
		$this->assertEquals( reset( $sp_posts )->post_title, 'tags-a-b-c' );
	}

	function test_raw_es() {
		$posts = sp_search( array(
			'query' => array(
				'match_all' => new stdClass,
			),
			'fields' => array( 'post_id' ),
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
			'fields' => array( 'post_id' ),
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
						'value' => 'cucumbers'
					)
				)
			),
			'fields' => array( 'post_id' ),
			'size' => 1,
			'from' => 0,
			'sort' => array(
				'post_name.raw' => 'asc'
			)
		) );
		$this->assertEmpty( $s->get_posts() );
	}

	function test_query_author_vars() {
		$author_1 = $this->factory->user->create( array( 'user_login' => 'author1', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$post_1 = $this->factory->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_1, 'post_date' => '2006-01-04 00:00:00' ) );

		$author_2 = $this->factory->user->create( array( 'user_login' => 'author2', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$post_2 = $this->factory->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_2, 'post_date' => '2006-01-03 00:00:00' ) );

		$author_3 = $this->factory->user->create( array( 'user_login' => 'author3', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$post_3 = $this->factory->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_3, 'post_date' => '2006-01-02 00:00:00' ) );

		$author_4 = $this->factory->user->create( array( 'user_login' => 'author4', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$post_4 = $this->factory->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_4, 'post_date' => '2006-01-01 00:00:00' ) );

		// Force refresh the index so the data is available immediately
		SP_API()->post( '_refresh' );

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
