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

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __clone() { wp_die( "Please don't __clone SP_API" ); }

	/**
	 * @codeCoverageIgnore
	 */
	public function __wakeup() { wp_die( "Please don't __wakeup SP_API" ); }

	/**
	 * @codeCoverageIgnore
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_API;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function setup() {
		$url = get_site_url();
		$this->index = preg_replace( '#^.*?//(.*?)/?$#', '$1', $url );
		$this->host = SP_Config()->get_setting( 'host' );
		$this->request_defaults = array(
			'sslverify'          => false,
			'timeout'            => 10,
			'user-agent'         => 'SearchPress 0.1 for WordPress',
			'reject_unsafe_urls' => false
		);

		# Increase the timeout for bulk indexing
		if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || defined( 'WP_CLI' ) && WP_CLI ) {
			$this->request_defaults['timeout'] = 60;
		}
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

		return json_encode( array(
			'error' => array(
				'code' => $result->get_error_code(),
				'message' => $result->get_error_message(),
				'data' => $result->get_error_data()
			)
		) );
	}

	public function parse_url( $url = '' ) {
		if ( is_string( $url ) ) {
			if ( preg_match( '#^https?://#i', $url ) ) {
				return $url;
			} elseif ( '/' == substr( $url, 0, 1 ) ) {
				return $this->host . $url;
			}
		}

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
		return $this->put( 'post/' . $post->post_id, $post->to_json() );
	}

	public function index_posts( $posts ) {
		$body = array();
		foreach ( $posts as $post ) {
			$body[] = '{ "index": { "_id" : ' . $post->post_id . ' } }';
			$body[] = addcslashes( $post->to_json(), "\n" );
		}
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

	public function cluster_health() {
		$health_uri = apply_filters( 'sp_cluster_health_uri', '/_cluster/health/' . $this->index );
		return $this->get( $health_uri );
	}
}

function SP_API() {
	return SP_API::instance();
}

endif;