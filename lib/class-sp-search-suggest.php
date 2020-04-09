<?php
/**
 * This file contains the SP_Search_Suggest class
 *
 * @package SearchPress
 */

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
		$doc_type = SP_API()->get_doc_type();
		if ( isset( $mapping['mappings'][ $doc_type ] ) ) {
			$props =& $mapping['mappings'][ $doc_type ]['properties'];
		} else {
			$props =& $mapping['mappings']['properties'];
		}

		$props['search_suggest'] = array(
			'type' => 'completion',
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
	 * @param array   $data    `sp_post_pre_index` data.
	 * @param SP_Post $sp_post Post being indexed, as an SP_Post.
	 * @return array Search suggest data.
	 */
	public function sp_post_pre_index( $data, $sp_post ) {
		/**
		 * Filter if the post should be searchable using search suggest.
		 *
		 * By default, this assumes that search suggest would be used on the
		 * frontend, so a post must meet the criteria to be considered "public".
		 * That is, its post type and post status must exist within the
		 * `sp_searchable_post_types()` and `sp_searchable_post_statuses()`
		 * arrays, respectively.
		 *
		 * If you're using search suggest in the admin, you should either
		 * always return true for this filter so that private post types and
		 * statuses show in the suggestion results, or add a second search
		 * suggest index with permissions-based access.
		 *
		 * @param  bool    $is_searchable Is this post searchable and thus
		 *                                should be added to the search suggest
		 *                                data?
		 * @param array    $data          sp_post_pre_index data.
		 * @param \SP_Post $sp_post       The \SP_Post object.
		 */
		if ( apply_filters( 'sp_search_suggest_post_is_searchable', $sp_post->is_searchable(), $data, $sp_post ) ) {
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
				'input' => apply_filters(
					'sp_search_suggest_data',
					array(
						$data['post_title'],
					),
					$data,
					$sp_post
				),
			);
		}

		return $data;
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			SP_Config()->namespace,
			'/suggest/(?P<fragment>.+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_response' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( $this, 'rest_schema' ),
			)
		);
	}

	/**
	 * Generate the REST schema for the search suggestions endpoint.
	 *
	 * @return array Endpoint schema data.
	 */
	public function rest_schema() {
		return array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => __( 'Search suggestions', 'searchpress' ),
			'type'    => 'array',
			'items'   => array(
				'type'       => 'object',
				'properties' => array(
					'text'    => array(
						'type'        => 'string',
						'description' => __( 'Matching text excerpt.', 'searchpress' ),
					),
					'_score'  => array(
						'type'        => 'number',
						'description' => __( 'Calculated match score of the search result.', 'searchpress' ),
					),
					'_source' => array(
						'type'       => 'object',
						'properties' => array(
							'post_title' => array(
								'type'        => 'string',
								'description' => __( 'Title of the search result.', 'searchpress' ),
							),
							'post_id'    => array(
								'type'        => 'integer',
								'description' => __( 'ID of the search result.', 'searchpress' ),
							),
							'permalink'  => array(
								'type'        => 'string',
								'description' => __( 'Permalink to the search result.', 'searchpress' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Send API response for REST endpoint.
	 *
	 * @param WP_REST_Request $request REST request data.
	 * @return WP_REST_Response
	 */
	public function rest_response( $request ) {
		// Sanitize the request arguments.
		$fragment = sanitize_text_field( wp_unslash( $request['fragment'] ) );

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
		$request = apply_filters(
			'sp_search_suggest_query',
			array(
				'suggest' => array(
					'search_suggestions' => array(
						'prefix'     => $fragment,
						'completion' => array(
							'field' => 'search_suggest',
						),
					),
				),
				'_source' => array(
					'post_id',
					'post_title',
					'permalink',
				),
			)
		);
		$results = SP_API()->search( wp_json_encode( $request ), array( 'output' => ARRAY_A ) );

		$options = ! empty( $results['suggest']['search_suggestions'][0]['options'] )
			? $results['suggest']['search_suggestions'][0]['options']
			: array();

		// Remove some data that could be considered sensitive.
		$options = array_map(
			function( $option ) {
				unset( $option['_index'], $option['_type'], $option['_id'] );
				return $option;
			},
			$options
		);

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
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/7.6/search-suggesters.html#completion-suggester
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
