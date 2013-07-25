<?php

/**
 * Basic WordPress-oriented Elasticsearch API client
 */

if ( !class_exists( 'SP_API' ) ) :

class SP_API {

	private static $instance;

	public $connection;

	public $host;

	public $index = '';

	public $type = 'post';

	public $request_defaults = array();

	public $last_request;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone SP_API" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup SP_API" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_API;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		$this->index = get_current_blog_id();
		$this->host = SP_Config()->get_setting( 'host' );
		$this->request_defaults = array(
			'sslverify'          => false,
			'timeout'            => 10,
			'user-agent'         => 'SearchPress 0.1 for WordPress',
			'reject_unsafe_urls' => false
		);
	}

	/**
	 * GET wrapper for request.
	 */
	function get( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'GET', $body ), ( $output == ARRAY_A ) );
	}

	/**
	 * POST wrapper for request.
	 */
	function post( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'POST', $body ), ( $output == ARRAY_A ) );
	}

	/**
	 * DELETE wrapper for request.
	 */
	function delete( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'DELETE', $body ), ( $output == ARRAY_A ) );
	}

	/**
	 * POST wrapper for request.
	 */
	function put( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'PUT', $body ), ( $output == ARRAY_A ) );
	}


	public function request( $url = '', $method = 'GET', $body = '', $request_params = array() ) {
		$url = $this->parse_url( $url );
		$request_params = array_merge(
			$this->request_defaults,
			$request_params,
			array( 'method' => $method, 'body' => $body )
		);
		$result = wp_remote_request( $url, $request_params );

		if ( ! is_wp_error( $result ) ) {
			$this->last_request = array(
				'url'              => $url,
				'params'           => $request_params,
				'response_code'    => $result['response']['code'],
				'response_headers' => $result['headers'],
				'response'         => $result
			);
			return $result['body'];
		}

		return '{ "error" : "' . esc_js( $result->get_error_message() ) . '" }';
	}

	public function parse_url( $url = '' ) {
		if ( is_string( $url ) && preg_match( '#^https?://#i', $url ) )
			return $url;

		$defaults = array(
			'host'  => $this->host,
			'index' => $this->index
		);

		if ( ! $url )
			$url = array();

		if ( ! is_array( $url ) )
			$url = array( 'action' => $url );

		$url = wp_parse_args( $url, $defaults );
		$formatted_url = $url['host'];
		foreach ( array( 'index', 'type', 'id', 'action' ) as $key ) {
			if ( isset( $url[ $key ] ) ) {
				$formatted_url .= '/' . $url[ $key ];
			}
		}
		return $formatted_url;
	}

	public function index_post( $post ) {
		// error_log( "Indexed post {$post->post_id}" );
		return $this->put( 'post/' . $post->post_id, $post->to_json() );
	}

	public function index_posts( $posts ) {
		$body = array();
		foreach ( $posts as $post ) {
			$body[] = '{ "index": { "_id" : ' . $post->post_id . ' } }';
			$body[] = addcslashes( $post->to_json(), "\n" );
		}
		// error_log( "Indexing " . count( $posts ) . " posts" );
		return $this->put( 'post/_bulk', wp_check_invalid_utf8( implode( "\n", $body ), true ) . "\n" );
	}

	public function delete_post( $post_id ) {
		return $this->delete( "post/{$post_id}" );
	}

	public function search( $query, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'output' => OBJECT
		) );
		return $this->post( 'post/_search', $query, $args['output'] );
	}
}

function SP_API() {
	return SP_API::instance();
}

endif;