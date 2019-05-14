<?php
/**
 * SearchPress library: SP_Search class
 *
 * @package SearchPress
 */

/**
 * You know, for search.
 *
 * This class provides an object with which you can perform searches with
 * Elasticsearch, using Elasticsearch's DSL syntax.
 */
class SP_Search {

	/**
	 * Elasticsearch arguments for this query. It is stored after the
	 * `sp_search_query_args` filter is applied, and immediately before query
	 * is run.
	 *
	 * @var array
	 */
	public $es_args;

	/**
	 * The raw results of the ES query.
	 *
	 * @var array
	 */
	public $search_results;

	/**
	 * The WP_Post objects corresponding to the search, should they be
	 * requested. This is more or less a cache.
	 *
	 * @var array
	 */
	public $posts = null;

	/**
	 * Instantiate the object.
	 *
	 * @param bool|array $es_args Optional. Array of Elasticsearch arguments. If
	 *                            present, the search is performed immediately.
	 *                            Otherwise, the object is created but the
	 *                            search must be called manually.
	 */
	public function __construct( $es_args = false ) {
		if ( false !== $es_args ) {
			$this->search( $es_args );
		}
	}

	/**
	 * Perform an Elasticsearch query.
	 *
	 * @param  array $es_args Elsticsearch query DSL as a PHP array.
	 * @return array Raw response from the ES server, parsed by json_Decode.
	 */
	public function search( $es_args ) {
		$this->es_args        = apply_filters( 'sp_search_query_args', $es_args );
		$this->search_results = SP_API()->search( wp_json_encode( $this->es_args ), array( 'output' => ARRAY_A ) );
		return $this->search_results;
	}

	/**
	 * Get the results of the current object's query.
	 *
	 * @param  string $return Optional. The data you want to receive. Options are:
	 *                        raw: Default. The full raw response.
	 *                        hits: Just the document data (response.hits.hits).
	 *                        total: The total number of results found (response.hits.total).
	 *                        facets: Just the facet data (response.facets).
	 * @return mixed Depends on what you've asked to return.
	 */
	public function get_results( $return = 'raw' ) {
		switch ( $return ) {
			case 'hits':
				return ( ! empty( $this->search_results['hits']['hits'] ) ) ? $this->search_results['hits']['hits'] : array();

			case 'total':
				return ( ! empty( $this->search_results['hits']['total'] ) ) ? intval( $this->search_results['hits']['total'] ) : 0;

			case 'facets':
				return ( ! empty( $this->search_results['aggregations'] ) ) ? $this->search_results['aggregations'] : array();

			default:
				return $this->search_results;
		}
	}

	/**
	 * Pluck a certain field out of an ES response.
	 *
	 * @see sp_results_pluck
	 *
	 * @param int|string $field A field from the retuls to place instead of the entire object.
	 * @param bool       $as_single Return as single (true) or an array (false). Defaults to true.
	 * @return array
	 */
	public function pluck_field( $field = null, $as_single = true ) {
		if ( ! $field ) {
			$field = reset( $this->es_args['_source'] );
		}

		return sp_results_pluck( $this->search_results, $field, $as_single );
	}

	/**
	 * Get the posts for this search.
	 *
	 * @return array array of WP_Post objects, as with get_posts.
	 */
	public function get_posts() {
		if ( isset( $this->posts ) ) {
			return $this->posts;
		}

		if ( 0 === $this->get_results( 'total' ) ) {
			$this->posts = array();
		} else {
			$ids         = $this->pluck_field( 'post_id' );
			$this->posts = get_posts( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
				array(
					'post_type'      => array_values( get_post_types() ),
					'post_status'    => array_values( get_post_stati() ),
					'posts_per_page' => $this->get_results( 'total' ),
					'post__in'       => $ids,
					'orderby'        => 'post__in',
					'order'          => 'ASC',
				)
			);
		}

		return $this->posts;
	}
}
