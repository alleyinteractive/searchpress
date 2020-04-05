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
		$response = SP_API()->get( SP_API()->get_doc_type() . "/{$this->post_id}" );
		$this->assertEquals( 'GET', SP_API()->last_request['params']['method'] );
		$this->assertEquals( '200', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
		$this->assertEquals( $this->post_id, $response->_source->post_id );

		SP_API()->get( SP_API()->get_doc_type() . "/foo" );
		$this->assertEquals( 'GET', SP_API()->last_request['params']['method'] );
		$this->assertEquals( '404', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
	}

	function test_api_post() {
		$response = SP_API()->post( SP_API()->get_doc_type() . '/_search', '{"query":{"match_all":{}}}' );
		$this->assertEquals( 'POST', SP_API()->last_request['params']['method'] );
		$this->assertEquals( '200', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
		$this->assertEquals( $this->post_id, $response->hits->hits[0]->_source->post_id );
	}

	function test_api_put() {
		SP_API()->get( SP_API()->get_doc_type() . '/123456' );
		$this->assertEquals( '404', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );

		$response = SP_API()->put( SP_API()->get_doc_type() . '/123456', '{"post_id":123456}' );
		$this->assertEquals( 'PUT', SP_API()->last_request['params']['method'] );
		$this->assertEquals( '201', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );

		$response = SP_API()->get( SP_API()->get_doc_type() . '/123456' );
		$this->assertEquals( 123456, $response->_source->post_id );
	}

	function test_api_delete() {
		SP_API()->put( SP_API()->get_doc_type() . '/123456', '{"post_id":123456}' );
		$this->assertEquals( '201', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
		$response = SP_API()->get( SP_API()->get_doc_type() . "/123456" );
		$this->assertEquals( 123456, $response->_source->post_id );

		SP_API()->delete( SP_API()->get_doc_type() . '/123456' );
		$this->assertEquals( '200', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );

		SP_API()->delete( SP_API()->get_doc_type() . '/123456' );
		$this->assertEquals( '404', wp_remote_retrieve_response_code( SP_API()->last_request['response'] ) );
	}

	function test_api_error() {
		$response = json_decode( SP_API()->request( 'http://asdf.jkl;/some/bad/url' ) );
		$this->assertNotEmpty( $response->error );
	}

	public function test_version() {
		$this->assertRegExp( '/^\d+\.\d+\.\d+/', SP_API()->version() );
	}
}
