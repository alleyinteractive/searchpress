<?php

/**
 * You know, for search.
 *
 * This class provides an object with which you can perform searches with
 * Elasticsearch, using Elasticsearch's DSL syntax.
 */
class SP_Search {

	public $es_args;

	public $search_results;

	public $posts = null;

	public function __construct( $es_args = false ) {
		if ( false !== $es_args ) {
			$this->search( $es_args );
		}
	}

	public function search( $es_args ) {
		$this->es_args = apply_filters( 'sp_search_query_args', $es_args );
		$this->search_results = SP_API()->search( json_encode( $this->es_args ), array( 'output' => ARRAY_A ) );
		return $this->search_results;
	}

	public function get_results( $return = 'raw' ) {
		switch ( $return ) {
			case 'hits' :
				return ( ! empty( $this->search_results['hits']['hits'] ) ) ? $this->search_results['hits']['hits'] : array();

			case 'total' :
				return ( ! empty( $this->search_results['hits']['total'] ) ) ? $this->search_results['hits']['total'] : 0;

			case 'facets' :
				return ( ! empty( $this->search_results['facets'] ) ) ? $this->search_results['facets'] : array();

			default :
				return $this->search_results;
		}
	}

	/**
	 * Pluck a certain field out of an ES response.
	 *
	 * @see sp_results_pluck
	 *
	 * @param int|string $field A field from the retuls to place instead of the entire object.
	 * @param bool $as_single Return as single (true) or an array (false). Defaults to true.
	 * @return array
	 */
	public function pluck_field( $field = null, $as_single = true ) {
		$return = array();

		if ( ! $field ) {
			$field = reset( $this->es_args['fields'] );
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

		if ( 0 == $this->get_results( 'total' ) ) {
			$this->posts = array();
		} else {
			$ids = $this->pluck_field( 'post_id' );
			$this->posts = get_posts( array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => $this->get_results( 'total' ),
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'order'          => 'ASC',
			) );
		}

		return $this->posts;
	}
}
