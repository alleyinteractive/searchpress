<?php

/**
 * @group facets
 */
class Tests_Faceting extends SearchPress_UnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$posts_to_index = array();

		$author_a = self::factory()->user->create( array( 'user_login' => 'author_a', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_a, 'post_date' => '2007-01-04 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_a, 'post_date' => '2007-01-05 00:00:00' ) );

		$author_b = self::factory()->user->create( array( 'user_login' => 'author_b', 'user_pass' => rand_str(), 'role' => 'author' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => rand_str(), 'post_author' => $author_b, 'post_date' => '2007-01-03 00:00:00' ) );

		$cat_a = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-a' ) );
		$cat_b = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-b' ) );
		$cat_c = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-c' ) );

		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'cats-a-b-c', 'post_date' => '2008-12-01 00:00:00', 'post_category' => array( $cat_a, $cat_b, $cat_c ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'cats-a-and-b', 'post_date' => '2009-01-01 00:00:00', 'post_category' => array( $cat_a, $cat_b ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'cats-b-and-c', 'post_date' => '2009-02-01 00:00:00', 'post_category' => array( $cat_b, $cat_c ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'cats-a-and-c', 'post_date' => '2009-03-01 00:00:00', 'post_category' => array( $cat_a, $cat_c ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'cat-a', 'post_date' => '2009-04-01 00:00:00', 'post_category' => array( $cat_a ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'cat-b', 'post_date' => '2009-05-01 00:00:00', 'post_category' => array( $cat_b ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'cat-c', 'post_date' => '2009-06-01 00:00:00', 'post_category' => array( $cat_c ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'lorem-ipsum', 'post_date' => '2009-07-01 00:00:00', 'post_category' => array( $cat_a ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'comment-test', 'post_date' => '2009-08-01 00:00:00', 'post_category' => array( $cat_a ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'one-trackback', 'post_date' => '2009-09-01 00:00:00', 'post_category' => array( $cat_b ) ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'many-trackbacks', 'post_date' => '2009-10-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'no-comments', 'post_date' => '2009-10-02 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'one-comment', 'post_date' => '2009-11-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'contributor-post-approved', 'post_date' => '2009-12-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'embedded-video', 'post_date' => '2010-01-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'simple-markup-test', 'post_date' => '2010-02-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'raw-html-code', 'post_date' => '2010-03-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tags-a-b-c', 'tags_input' => array( 'tag-a', 'tag-b', 'tag-c' ), 'post_date' => '2010-04-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tag-a', 'tags_input' => array( 'tag-a' ), 'post_date' => '2010-05-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tag-a-2', 'tags_input' => array( 'tag-a' ), 'post_date' => '2010-05-01 00:00:01' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tag-a-3', 'tags_input' => array( 'tag-a' ), 'post_date' => '2010-05-01 00:00:02' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tag-b', 'tags_input' => array( 'tag-b' ), 'post_date' => '2010-06-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tag-b-2', 'tags_input' => array( 'tag-b' ), 'post_date' => '2010-06-01 00:00:01' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tag-c', 'tags_input' => array( 'tag-c' ), 'post_date' => '2010-07-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tags-a-and-b', 'tags_input' => array( 'tag-a', 'tag-b' ), 'post_date' => '2010-08-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tags-b-and-c', 'tags_input' => array( 'tag-b', 'tag-c' ), 'post_date' => '2010-09-01 00:00:00' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'tags-a-and-c', 'tags_input' => array( 'tag-a', 'tag-c' ), 'post_date' => '2010-10-01 00:00:00' ) );

		$parent_one = self::factory()->post->create( array( 'post_title' => 'parent-one', 'post_type' => 'page', 'post_date' => '2007-01-01 00:00:01' ) );
		$parent_two = self::factory()->post->create( array( 'post_title' => 'parent-two', 'post_type' => 'page', 'post_date' => '2007-01-01 00:00:02' ) );
		$posts_to_index[] = $parent_one;
		$posts_to_index[] = $parent_two;
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'parent-three', 'post_type' => 'page', 'post_date' => '2007-01-01 00:00:03' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'child-one', 'post_parent' => $parent_one, 'post_type' => 'page', 'post_date' => '2007-01-01 00:00:04' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'child-two', 'post_parent' => $parent_one, 'post_type' => 'page', 'post_date' => '2007-01-01 00:00:05' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'child-three', 'post_parent' => $parent_two, 'post_type' => 'page', 'post_date' => '2007-01-01 00:00:06' ) );
		$posts_to_index[] = self::factory()->post->create( array( 'post_title' => 'child-four', 'post_parent' => $parent_two, 'post_type' => 'page', 'post_date' => '2007-01-01 00:00:07' ) );

		self::index( $posts_to_index );
	}

	function test_faceting() {
		$s = new SP_WP_Search( array(
			'post_type' => array( 'post', 'page' ),
			'posts_per_page' => 0,
			'facets' => array(
				'Tag'       => array( 'type' => 'taxonomy', 'taxonomy' => 'post_tag', 'count' => 10 ),
				'Post Type' => array( 'type' => 'post_type', 'count' => 10 ),
				'Author'    => array( 'type' => 'author', 'count' => 10 ),
				'Histogram' => array( 'type' => 'date_histogram', 'interval' => 'year', 'count' => 10 ),
			 ),
		) );
		$facets = $s->get_results( 'facets' );

		$this->assertNotEmpty( $facets );
		$this->assertNotEmpty( $facets['Tag']['buckets'] );
		$this->assertNotEmpty( $facets['Post Type']['buckets'] );
		$this->assertNotEmpty( $facets['Author']['buckets'] );
		$this->assertNotEmpty( $facets['Histogram']['buckets'] );
	}

	function test_parsed_data() {
		$s = new SP_WP_Search( array(
			'post_type' => array( 'post', 'page' ),
			'posts_per_page' => 0,
			'facets' => array(
				'Tag'       => array( 'type' => 'taxonomy', 'taxonomy' => 'post_tag', 'count' => 10 ),
				'Post Type' => array( 'type' => 'post_type', 'count' => 10 ),
			 ),
		) );
		$facet_data = $s->get_facet_data();

		// Tags
		$this->assertEquals(
			array( 'tag-a', 'tag-b', 'tag-c' ),
			wp_list_pluck( $facet_data['Tag']['items'], 'name' )
		);
		$this->assertEquals(
			array( 6, 5, 4 ),
			wp_list_pluck( $facet_data['Tag']['items'], 'count' )
		);
		$this->assertEquals(
			array( array( 'tag' => 'tag-a' ), array( 'tag' => 'tag-b' ), array( 'tag' => 'tag-c' ) ),
			wp_list_pluck( $facet_data['Tag']['items'], 'query_vars' )
		);

		// Post Type
		$this->assertEquals( 'Post', $facet_data['Post Type']['items'][0]['name'] );
		$this->assertEquals( 'Page', $facet_data['Post Type']['items'][1]['name'] );
		$this->assertEquals( 30, $facet_data['Post Type']['items'][0]['count'] );
		$this->assertEquals( 7, $facet_data['Post Type']['items'][1]['count'] );
		$this->assertEquals( array( 'post_type' => 'post' ), $facet_data['Post Type']['items'][0]['query_vars'] );
		$this->assertEquals( array( 'post_type' => 'page' ), $facet_data['Post Type']['items'][1]['query_vars'] );
	}

	public function test_post_type() {
		$label = rand_str();
		register_post_type( 'custom-post-type', array(
			'public' => true,
			'labels' => array(
				'singular_name' => $label,
			),
		) );
		SP_Config()->post_types = null;
		sp_searchable_post_types( true );

		$posts_to_index = array(
			self::factory()->post->create( array( 'post_title' => 'first lorem', 'post_date' => '2010-01-01 00:00:00', 'post_type' => 'custom-post-type' ) ),
			self::factory()->post->create( array( 'post_title' => 'second lorem', 'post_date' => '2010-02-01 00:00:00', 'post_type' => 'custom-post-type' ) ),
		);
		self::index( $posts_to_index );

		$s = new SP_WP_Search( array(
			'post_type' => array( 'post', 'page', 'custom-post-type' ),
			'posts_per_page' => 0,
			'facets' => array(
				'Post Type' => array( 'type' => 'post_type', 'count' => 10 ),
			 ),
		) );
		$facet_data = $s->get_facet_data();

		$this->assertCount( 3, $facet_data['Post Type']['items'] );
		$this->assertEquals( $label, $facet_data['Post Type']['items'][2]['name'] );
		$this->assertEquals( 2, $facet_data['Post Type']['items'][2]['count'] );
		$this->assertEquals( array( 'post_type' => 'custom-post-type' ), $facet_data['Post Type']['items'][2]['query_vars'] );
	}

	function test_tax_query_var() {
		$s = new SP_WP_Search( array(
			'post_type' => array( 'post', 'page' ),
			'posts_per_page' => 0,
			'facets' => array(
				'Category'  => array( 'type' => 'taxonomy', 'taxonomy' => 'category', 'count' => 10 ),
			 ),
		) );
		$facet_data = $s->get_facet_data();

		$this->assertEquals(
			array( array( 'category_name' => 'uncategorized' ), array( 'category_name' => 'cat-a' ), array( 'category_name' => 'cat-b' ), array( 'category_name' => 'cat-c' ) ),
			wp_list_pluck( $facet_data['Category']['items'], 'query_vars' )
		);
	}

	function test_histograms() {
		$s = new SP_WP_Search( array(
			'post_type' => array( 'post', 'page' ),
			'posts_per_page' => 0,
			'facets' => array(
				'Year' => array( 'type' => 'date_histogram', 'interval' => 'year', 'count' => 10 ),
				'Month' => array( 'type' => 'date_histogram', 'interval' => 'month', 'count' => 10 ),
				'Day' => array( 'type' => 'date_histogram', 'interval' => 'day', 'field' => 'post_modified', 'count' => 10 ),
			 ),
		) );
		$facet_data = $s->get_facet_data();

		$this->assertEquals( '2007', $facet_data['Year']['items'][0]['name'] );
		$this->assertEquals( '2008', $facet_data['Year']['items'][1]['name'] );
		$this->assertEquals( '2009', $facet_data['Year']['items'][2]['name'] );
		$this->assertEquals( '2010', $facet_data['Year']['items'][3]['name'] );
		$this->assertEquals( 10, $facet_data['Year']['items'][0]['count'] );
		$this->assertEquals( 1, $facet_data['Year']['items'][1]['count'] );
		$this->assertEquals( 13, $facet_data['Year']['items'][2]['count'] );
		$this->assertEquals( 13, $facet_data['Year']['items'][3]['count'] );
		$this->assertEquals( array( 'year' => '2007' ), $facet_data['Year']['items'][0]['query_vars'] );
		$this->assertEquals( array( 'year' => '2008' ), $facet_data['Year']['items'][1]['query_vars'] );
		$this->assertEquals( array( 'year' => '2009' ), $facet_data['Year']['items'][2]['query_vars'] );
		$this->assertEquals( array( 'year' => '2010' ), $facet_data['Year']['items'][3]['query_vars'] );

		$this->assertEquals( 'January 2007', $facet_data['Month']['items'][0]['name'] );
		$this->assertEquals( 10, $facet_data['Month']['items'][0]['count'] );
		$this->assertEquals( array( 'year' => '2007', 'monthnum' => 1 ), $facet_data['Month']['items'][0]['query_vars'] );

		$this->assertEquals( 'January 1, 2007', $facet_data['Day']['items'][0]['name'] );
		$this->assertEquals( 7, $facet_data['Day']['items'][0]['count'] );
		$this->assertEquals( array( 'year' => '2007', 'monthnum' => 1, 'day' => 1 ), $facet_data['Day']['items'][0]['query_vars'] );
	}

	function test_author_facets() {
		$s = new SP_WP_Search( array(
			'post_type' => array( 'post', 'page' ),
			'posts_per_page' => 0,
			'facets' => array(
				'Author'    => array( 'type' => 'author', 'count' => 10 ),
			 ),
		) );
		$facet_data = $s->get_facet_data();

		$this->assertEquals( 'author_a', $facet_data['Author']['items'][0]['name'] );
		$this->assertEquals( 2, $facet_data['Author']['items'][0]['count'] );

		$this->assertEquals( 'author_b', $facet_data['Author']['items'][1]['name'] );
		$this->assertEquals( 1, $facet_data['Author']['items'][1]['count'] );
	}

	function test_facet_by_taxonomy() {
		// Fake a taxonomy query to WP_Query so the query vars are set properly.
		global $wp_query;
		$wp_query->parse_query(
			[
				'post_type' => ['post', 'page'],
				'tax_query' => [
					[
						'taxonomy' => 'category',
						'field'    => 'slug',
						'terms'    => ['cat-a'],
					],
					[
						'taxonomy' => 'category',
						'field'    => 'slug',
						'terms'    => ['cat-b'],
					],
					[
						'taxonomy' => 'category',
						'field'    => 'slug',
						'terms'    => ['cat-c'],
					],
				],
			]
		);

		$s = new SP_WP_Search( array(
			'post_type' => array( 'post', 'page' ),
			'posts_per_page' => 0,
			'facets' => array(
				'Category'  => array( 'type' => 'taxonomy', 'taxonomy' => 'category', 'count' => 10 ),
			),
		) );
		$facet_data = $s->get_facet_data(
			[
				'exclude_current'     => false,
				'join_existing_terms' => false,
			]
		);

		$this->assertEquals(
			array( false, true, true, true ),
			wp_list_pluck( $facet_data['Category']['items'], 'selected' )
		);
	}
}
