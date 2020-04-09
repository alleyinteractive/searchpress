<?php

/**
 * @group suggest
 */
class Tests_Search_Suggest extends SearchPress_UnitTestCase {

	/**
	 * @var \WP_Post
	 */
	protected $matching_post;

	/**
	 * @var \WP_Post
	 */
	protected $unmatching_post;

	public function setUp() {
		parent::setUp();

		// Enable the Search Suggestions feature.
		SP_Search_Suggest::instance()->setup();

		// Unfortunately this gets run an extra time on these tests.
		sp_index_flush_data();

		// Don't auto-sync posts to ES.
		remove_action( 'save_post', array( SP_Sync_Manager(), 'sync_post' ) );

		$this->matching_post   = self::factory()->post->create_and_get( array( 'post_title' => 'testing suggestions' ) );
		$this->unmatching_post = self::factory()->post->create_and_get( array( 'post_title' => 'blah blah blah' ) );
		$this->index( array( $this->matching_post, $this->unmatching_post ) );
	}

	/**
	 * @test
	 */
	function it_should_find_matching_post_using_suggest_api() {
		$suggestions = SP_Search_Suggest::instance()->get_suggestions( 'test' );
		$this->assertCount( 1, $suggestions );
		$this->assertSame(
			$this->matching_post->post_title,
			$suggestions[0]['_source']['post_title']
		);
	}

	/**
	 * @test
	 */
	function it_should_have_a_rest_endpoint() {
		// Mock REST API.
		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server();
		do_action( 'rest_api_init' );

		// Build the API request.
		$suggest_url = sprintf( '/%s/suggest/test', SP_Config()->namespace );
		$request     = new WP_REST_Request( 'GET', $suggest_url );

		// Dispatch the request.
		$response = rest_get_server()->dispatch( $request );

		// Assert the request was successful.
		$this->assertNotWPError( $response );
		$this->assertInstanceOf( '\WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		// Confirm the response data.
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame(
			$this->matching_post->post_title,
			$data[0]['_source']['post_title']
		);
	}
}
