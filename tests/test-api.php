<?php

/**
 * @group api
 */
class Tests_Api extends SearchPress_UnitTestCase {
	var $post_id;

	function setUp() {
		parent::setUp();

		$this->post_id = $this->factory->post->create( array( 'post_title' => 'lorem-ipsum', 'post_date' => '2009-07-01 00:00:00' ) );

		// Force refresh the index so the data is available immediately
		SP_API()->post( '_refresh' );
	}

	function test_api_get() {
		$response = SP_API()->get( "post/{$this->post_id}" );
		$this->assertEquals( 'GET', SP_API()->last_request['params']['method'] );
		$this->assertEquals( '200', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
		$this->assertEquals( $this->post_id, $response->_source->post_id );

		SP_API()->get( "post/foo" );
		$this->assertEquals( 'GET', SP_API()->last_request['params']['method'] );
		$this->assertEquals( '404', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
	}

	function test_api_post() {
		$response = SP_API()->post( 'post/_search', '{"query":{"match_all":{}}}' );
		$this->assertEquals( 'POST', SP_API()->last_request['params']['method'] );
		$this->assertEquals( '200', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
		$this->assertEquals( $this->post_id, $response->hits->hits[0]->_source->post_id );
	}

	function test_api_put() {
		SP_API()->get( 'post/123456' );
		$this->assertEquals( '404', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );

		$response = SP_API()->put( 'post/123456', '{"post_id":123456}' );
		$this->assertEquals( 'PUT', SP_API()->last_request['params']['method'] );
		$this->assertEquals( '201', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );

		$response = SP_API()->get( 'post/123456' );
		$this->assertEquals( 123456, $response->_source->post_id );
	}

	function test_api_delete() {
		SP_API()->put( 'post/123456', '{"post_id":123456}' );
		$this->assertEquals( '201', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
		$response = SP_API()->get( "post/123456" );
		$this->assertEquals( 123456, $response->_source->post_id );

		SP_API()->delete( 'post/123456' );
		$this->assertEquals( '200', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );

		SP_API()->delete( 'post/123456' );
		$this->assertEquals( '404', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
	}

	function test_api_error() {
		$response = json_decode( SP_API()->request( 'http://asdf.jkl;/some/bad/url' ) );
		$this->assertNotEmpty( $response->error );
	}

	public function test_version() {
		$this->assertRegExp( '/^\d+\.\d+\.\d+/', SP_API()->version() );
	}

	function test_wrapping_sp_remote_request() {
		$pre          = '';
		$post         = '';
		$method       = null;
		$request_time = 0;
		add_filter( 'sp_remote_request', function( $callable ) use ( &$pre, &$post, &$method, &$request_time ) {
			return function( $url, $request_args ) use ( $callable, &$pre, &$post, &$method, &$request_time ) {
				$start    = microtime( true );

				$pre      = 'before request';
				$method   = $request_args['method'];
				$response = call_user_func( $callable, $url, $request_args );
				$post     = 'after request';

				$request_time = microtime( true ) - $start;
				return $response;
			};
		} );

		SP_API()->put( 'post/123456', '{"post_id":123456}' );

		$this->assertSame( 'PUT', $method );
		$this->assertSame( 'before request', $pre );
		$this->assertSame( 'after request', $post );
		$this->assertGreaterThan( 0, $request_time );
	}
}
