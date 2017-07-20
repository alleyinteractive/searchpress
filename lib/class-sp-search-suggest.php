<?php

/**
 * Autocomplete search suggestions.
 */

class SP_Search_Suggest extends SP_Singleton {

	/**
	 * Setup the singleton.
	 */
	public function setup() {
		add_filter( 'sp_config_mapping', array( $this, 'sp_config_mapping' ), 5 );
		add_filter( 'sp_map_version', array( $this, 'sp_map_version' ), 5 );
		add_filter( 'sp_post_pre_index', array( $this, 'sp_post_pre_index' ), 5, 2 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Update the SP mapping.
	 *
	 * @param  array $mapping Elasticsearch mapping as a PHP array.
	 * @return array
	 */
	public function sp_config_mapping( $mapping ) {
		$mapping['mappings']['post']['properties']['search_suggest'] = array(
			'type' => 'completion',
			'analyzer' => 'simple',
			'search_analyzer' => 'simple',
			'payloads' => true,
		);

		return $mapping;
	}

	/**
	 * Update the mapping version to tell SP we modified the map.
	 *
	 * @param  float $version Mapping version.
	 * @return float
	 */
	public function sp_map_version( $version ) {
		// Add a randomish number to the version.
		return $version + 0.073;
	}

	/**
	 * Builds the post data for search suggest.
	 *
	 * @return array Search suggest data.
	 */
	public function sp_post_pre_index( $data, $sp_post ) {
		/**
		 * Filters the search suggest data (fields/content).
		 *
		 * To filter any other characteristics of search suggest, use the
		 * `sp_post_pre_index` filter.
		 *
		 * @see https://www.elastic.co/guide/en/elasticsearch/reference/2.4/search-suggesters-completion.html
		 *
		 * @param array    $search_suggest_data Array of data for search suggesters
		 *                                      completion. By default, this just
		 *                                      includes the post_title.
		 * @param array    $data                sp_post_pre_index data.
		 * @param \SP_Post $sp_post             The \SP_Post object.
		 */
		$data['search_suggest'] = array(
			'input' => apply_filters( 'sp_search_suggest_data', array(
				$data['post_title'],
			), $data, $sp_post ),
			'output' => $data['post_title'],
			'payload' => array(
				'id' => $data['post_id'],
				'permalink' => $data['permalink'],
			),
		);

		return $data;
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route( SP_Config()->namespace, '/suggest/(?P<fragment>.+)', array(
			'methods' => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'rest_response' ),
		) );
	}

	/**
	 * Send API response for REST endpoint.
	 *
	 * @param \WP_REST_Request  REST request data.
	 */
	public function rest_response( $request ) {
		// Sanitize the request arguments.
		$fragment = sanitize_text_field( $request['fragment'] );

		// Query search suggest against the fragment.
		$data = $this->get_suggestions( $fragment );

		// Send the response.
		return rest_ensure_response( $data );
	}

	/**
	 * Query Elasticsearch for search suggestions.
	 *
	 * @param  string $fragment Search fragment.
	 * @return array
	 */
	public function get_suggestions( $fragment ) {
		/**
		 * Filter the raw search suggest query.
		 *
		 * @param array Search suggest query.
		 */
		$request = apply_filters( 'sp_search_suggest_query', array(
			'search_suggestions' => array(
				'text' => $fragment,
				'completion' => array(
					'field' => 'search_suggest',
				),
			),
		) );
		$results = SP_API()->post( '_suggest', wp_json_encode( $request ), ARRAY_A );

		$options = ! empty( $results['search_suggestions'][0]['options'] )
			? $results['search_suggestions'][0]['options']
			: array();

		/**
		 * Filter the raw search suggest options.
		 *
		 * @param array  $options  Search suggest options.
		 * @param array  $results  Search suggest raw results.
		 * @param string $fragment Search fragment producing the results.
		 */
		return apply_filters(
			'sp_search_suggest_results',
			$options,
			$results,
			$fragment
		);
	}
}

/**
 * Optionally setup search suggest. This runs after the theme and plugins have
 * all been setup.
 */
function sp_maybe_enable_search_suggest() {
	/**
	 * Checks if search suggestions are enabled. If true, adds the config to
	 * the mapping. If you'd like to edit it, use the `sp_config_mapping`
	 * filter.
	 *
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/2.4/search-suggesters-completion.html
	 *
	 * @param  boolean $enabled Enabled if true, disabled if false. Defaults
	 *                          to false.
	 */
	if ( apply_filters( 'sp_enable_search_suggest', false ) ) {
		// Initialize the singleton.
		SP_Search_Suggest::instance();
	}
}
add_action( 'after_setup_theme', 'sp_maybe_enable_search_suggest', 100 );
