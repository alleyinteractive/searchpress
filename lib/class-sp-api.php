<?php

/**
 * Basic WordPress-oriented Elasticsearch API client
 */

class SP_API extends SP_Singleton {

	public $host;

	public $index = '';

	public $request_defaults = array();

	public $last_request;

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
			'reject_unsafe_urls' => false,
		);

		// Increase the timeout for bulk indexing
		if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || defined( 'WP_CLI' ) && WP_CLI ) {
			$this->request_defaults['timeout'] = 60;
		}
	}

	/**
	 * GET wrapper for request.
	 */
	function get( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'GET', $body ), ( ARRAY_A === $output ) );
	}

	/**
	 * POST wrapper for request.
	 */
	function post( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'POST', $body ), ( ARRAY_A === $output ) );
	}

	/**
	 * DELETE wrapper for request.
	 */
	function delete( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'DELETE', $body ), ( ARRAY_A === $output ) );
	}

	/**
	 * POST wrapper for request.
	 */
	function put( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'PUT', $body ), ( ARRAY_A === $output ) );
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
				'response'         => $result,
			);
			return $result['body'];
		}

		return wp_json_encode( array(
			'error' => array(
				'code' => $result->get_error_code(),
				'message' => $result->get_error_message(),
				'data' => $result->get_error_data(),
			),
		) );
	}

	public function parse_url( $url = '' ) {
		if ( is_string( $url ) ) {
			if ( preg_match( '#^https?://#i', $url ) ) {
				return $url;
			} elseif ( '/' === substr( $url, 0, 1 ) ) {
				return $this->host . $url;
			}
		}

		$defaults = array(
			'host'  => $this->host,
			'index' => $this->index,
		);

		if ( ! $url ) {
			$url = array();
		}

		if ( ! is_array( $url ) ) {
			$url = array( 'action' => $url );
		}

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
		$json = $post->to_json();
		if ( empty( $json ) ) {
			return new WP_Error( 'invalid-json', __( 'Invalid JSON', 'searchpress' ) );
		}
		return $this->put( 'post/' . $post->post_id, $json );
	}

	public function index_posts( $posts ) {
		$body = array();
		foreach ( $posts as $post ) {
			$json = $post->to_json();
			if ( empty( $json ) ) {
				SP_Sync_Meta()->log( new WP_Error( 'error', sprintf( __( 'Unable to index post %d: Invalid JSON', 'searchpress' ), $post->post_id ) ) );
			} else {
				$body[] = '{ "index": { "_id" : ' . $post->post_id . ' } }';
				$body[] = addcslashes( $json, "\n" );
			}
		}
		return $this->put( 'post/_bulk', wp_check_invalid_utf8( implode( "\n", $body ), true ) . "\n" );
	}

	public function delete_post( $post_id ) {
		return $this->delete( "post/{$post_id}" );
	}

	public function search( $query, $args = array() ) {
		$args = wp_parse_args( $args, array(
			'output' => OBJECT,
		) );
		return $this->post( 'post/_search', $query, $args['output'] );
	}

	public function cluster_health() {
		$health_uri = apply_filters( 'sp_cluster_health_uri', '/_cluster/health/' . $this->index . '?wait_for_status=yellow&timeout=0.2s' );
		return $this->get( $health_uri );
	}
}

function SP_API() {
	return SP_API::instance();
}
