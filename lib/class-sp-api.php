<?php
/**
 * SearchPress library: SP_API class
 *
 * @package SearchPress
 */

/**
 * Basic WordPress-oriented Elasticsearch API client
 */
class SP_API extends SP_Singleton {

	/**
	 * The Elasticsearch host URL.
	 *
	 * @var string
	 */
	public $host;

	/**
	 * The slug for the index.
	 *
	 * @var string
	 */
	public $index = '';

	/**
	 * The document type.
	 *
	 * In ES < 6.0, this was like a database table. ES 6.0 deprecated this in
	 * favor of _doc and 7.0 killed support for custom mapping types. This will
	 * eventually be removed in Elasticsearch.
	 *
	 * @var string
	 */
	public $doc_type;

	/**
	 * Default options for requests.
	 *
	 * @var array
	 */
	public $request_defaults = array();

	/**
	 * Stores information about the last request made.
	 *
	 * @var array
	 */
	public $last_request;

	/**
	 * Initializes class variables.
	 *
	 * @codeCoverageIgnore
	 */
	public function setup() {
		$url         = get_site_url();
		$this->index = preg_replace( '#^.*?//(.*?)/?$#', '$1', $url );
		$this->host  = SP_Config()->get_setting( 'host' );
		$host_parts  = wp_parse_url( $this->host );

		/**
		 * Override SSL verification for API requests
		 *
		 * @param bool   $verify_ssl Whether to verify SSL certificate for non-localhost requests.
		 * @param string $host Elasticsearch host.
		 * @return bool
		 */
		$verify_ssl = apply_filters( 'sp_api_verify_ssl', 'https' === $host_parts['scheme'] && 'localhost' !== $host_parts['host'], $this->host );

		$this->request_defaults = array(
			'sslverify'          => $verify_ssl,
			'timeout'            => 10, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			'user-agent'         => 'SearchPress 0.1 for WordPress',
			'reject_unsafe_urls' => false,
			'headers'            => array(
				'Content-Type' => 'application/json',
			),
		);

		// Increase the timeout for bulk indexing.
		if ( wp_doing_cron() || defined( 'WP_CLI' ) && WP_CLI ) {
			$this->request_defaults['timeout'] = 60; // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
		}
	}

	/**
	 * Get the doc type (mapping type) for the index.
	 *
	 * @see SP_API::$doc_type for further explanation.
	 *
	 * @return string
	 */
	public function get_doc_type() {
		if ( empty( $this->doc_type ) ) {
			if ( sp_es_version_compare( '6.0', '<' ) ) {
				$this->doc_type = 'post';
			} else {
				$this->doc_type = '_doc';
			}
		}

		return $this->doc_type;
	}

	/**
	 * Executes a GET request against the API.
	 *
	 * @param string $url    The URL to send the request to.
	 * @param string $body   The body of the request.
	 * @param string $output The return format. Defaults to OBJECT.
	 * @return object|array JSON-encoded response from the API.
	 */
	public function get( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'GET', $body ), ( ARRAY_A === $output ) );
	}

	/**
	 * Executes a POST request against the API.
	 *
	 * @param string $url    The URL to send the request to.
	 * @param string $body   The body of the request.
	 * @param string $output The return format. Defaults to OBJECT.
	 * @return object|array JSON-encoded response from the API.
	 */
	public function post( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'POST', $body ), ( ARRAY_A === $output ) );
	}

	/**
	 * Executes a DELETE request against the API.
	 *
	 * @param string $url    The URL to send the request to.
	 * @param string $body   The body of the request.
	 * @param string $output The return format. Defaults to OBJECT.
	 * @return object|array JSON-encoded response from the API.
	 */
	public function delete( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'DELETE', $body ), ( ARRAY_A === $output ) );
	}

	/**
	 * Executes a PUT request against the API.
	 *
	 * @param string $url    The URL to send the request to.
	 * @param string $body   The body of the request.
	 * @param string $output The return format. Defaults to OBJECT.
	 * @return object|array JSON-encoded response from the API.
	 */
	public function put( $url = '', $body = '', $output = OBJECT ) {
		return json_decode( $this->request( $url, 'PUT', $body ), ( ARRAY_A === $output ) );
	}

	/**
	 * A generic function to send a request to the API.
	 *
	 * @param string $url            The URL to send the request to.
	 * @param string $method         The method for the request. Defaults to GET.
	 * @param string $body           The body of the request.
	 * @param array  $request_params Additional parameters to send with wp_remote_request.
	 * @return string The JSON-encoded result of wp_remote_request.
	 */
	public function request( $url = '', $method = 'GET', $body = '', $request_params = array() ) {
		$url            = $this->parse_url( $url );
		$request_params = array_merge(
			$this->request_defaults,
			$request_params,
			array(
				'method' => $method,
				'body'   => $body,
			)
		);
		$result         = sp_remote_request( $url, $request_params );

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

		return wp_json_encode(
			array(
				'error' => array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
					'data'    => $result->get_error_data(),
				),
			)
		);
	}

	/**
	 * Normalizes various formats of URLs.
	 *
	 * @param string|array $url The URL to normalize.
	 * @return string The normalized URL.
	 */
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

		$url           = wp_parse_args( $url, $defaults );
		$formatted_url = $url['host'];
		foreach ( array( 'index', 'type', 'id', 'action' ) as $key ) {
			if ( isset( $url[ $key ] ) ) {
				$formatted_url .= '/' . $url[ $key ];
			}
		}
		return $formatted_url;
	}

	/**
	 * Indexes an individual post.
	 *
	 * @param SP_Post|WP_Post|int $post The post to add to the index.
	 * @return object|WP_Error The API response, or a WP_Error on error.
	 */
	public function index_post( $post ) {
		// Ensure $post is a valid object and should be indexed.
		if ( ! $post instanceof SP_Post ) {
			$post = new SP_Post( $post );
		}
		if ( ! $post->should_be_indexed() ) {
			return new WP_Error( 'unindexable-post', __( 'Post should not be indexed', 'searchpress' ) );
		}

		$json = $post->to_json();
		if ( empty( $json ) ) {
			return new WP_Error( 'invalid-json', __( 'Invalid JSON', 'searchpress' ) );
		}
		return $this->put( "{$this->get_doc_type()}/{$post->post_id}", $json );
	}

	/**
	 * Indexes an array of posts.
	 *
	 * @param array $posts An array of posts to index. May either be post IDs,
	 *                     WP_Post objects, or SP_Post objects.
	 * @return object The API response.
	 */
	public function index_posts( $posts ) {
		$body = array();
		foreach ( $posts as $post ) {
			// Ensure $post is a valid object and should be indexed.
			if ( ! $post instanceof SP_Post ) {
				$post = new SP_Post( $post );
			}
			if ( ! $post->should_be_indexed() ) {
				continue;
			}

			$json = $post->to_json();
			if ( empty( $json ) ) {
				// Translators: post ID.
				SP_Sync_Meta()->log( new WP_Error( 'error', sprintf( __( 'Unable to index post %d: Invalid JSON', 'searchpress' ), $post->post_id ) ) );
			} else {
				$body[] = '{ "index": { "_id" : ' . $post->post_id . ' } }';
				$body[] = addcslashes( $json, "\n" );
			}
		}
		return $this->put(
			"{$this->get_doc_type()}/_bulk",
			wp_check_invalid_utf8( implode( "\n", $body ), true ) . "\n"
		);
	}

	/**
	 * Executes a post deletion.
	 *
	 * @param int $post_id The post ID to delete.
	 * @return object The response from the API.
	 */
	public function delete_post( $post_id ) {
		return $this->delete( "{$this->get_doc_type()}/{$post_id}" );
	}

	/**
	 * Executes a search.
	 *
	 * @param string $query Query arguments for the search as encoded JSON.
	 * @param array  $args  Additional arguments for the post function.
	 * @return object|array Return format according to $args['output']. Defaults
	 *                      to object.
	 */
	public function search( $query, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'output' => OBJECT,
			)
		);
		return $this->post( "{$this->get_doc_type()}/_search", $query, $args['output'] );
	}

	/**
	 * Get the cluster health.
	 *
	 * @return object|array Response from the cluster health API. The most
	 *                      important part of the successful response is
	 *                      $health->status, which is the "red", "yellow", or
	 *                      "green" status indicator.
	 */
	public function cluster_health() {
		/**
		 * Filter the cluster health URI (or URL). Defaults to
		 * `"/_cluster/health/{$this->index}?wait_for_status=yellow&timeout=0.2s"`.
		 *
		 * By default, this will wait up to 0.2 seconds and it will wait for a
		 * yellow status. To change either value, filter the URI and
		 * manipulate the string.
		 *
		 * @param string $url URI or URL to hit to query the cluster health.
		 */
		$health_uri = apply_filters( 'sp_cluster_health_uri', '/_cluster/health?wait_for_status=yellow&timeout=200ms' ); // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
		return $this->get( $health_uri );
	}

	/**
	 * Get the version from Elasticsearch.
	 *
	 * @return string|bool Version string on success, false on failure.
	 */
	public function version() {
		static $version;
		if ( ! isset( $version ) ) {
			$response = $this->get( '/' );
			$version  = ! empty( $response->version->number ) ? $response->version->number : false;
		}
		return $version;
	}
}

/**
 * Returns an initialized instance of the SP_API class.
 *
 * @return SP_API An initialized instance of the SP_API class.
 */
function SP_API() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return SP_API::instance();
}
