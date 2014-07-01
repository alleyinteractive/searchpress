<?php

class SP_Search {

	public $es_args;

	public $facets = array();

	public $search_results;

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

	public function set_facets( $facets ) {
		$this->facets = $facets;
	}

	public function get_search_results( $raw = false ) {
		if ( $raw ) {
			return $this->search_results;
		}

		return ( ! empty( $this->search_results['hits'] ) ) ? $this->search_results['hits'] : false;
	}

	public function get_search_hits() {
		$search_results = $this->get_search_results();
		return ( ! empty( $search_results['hits'] ) ) ? $search_results['hits'] : array();
	}

	public function get_total_results() {
		$search_results = $this->get_search_results();
		return ( ! empty( $search_results['total'] ) ) ? $search_results['total'] : 0;
	}

	public function get_search_facets() {
		$search_results = $this->get_search_results();
		return ( ! empty( $search_results['facets'] ) ) ? $search_results['facets'] : array();
	}

	// Turns raw ES facet data into data that is more useful in a WordPress setting
	public function get_search_facet_data() {
		if ( empty( $this->facets ) )
			return false;

		$facets = $this->get_search_facets();

		if ( ! $facets )
			return false;

		$facet_data = array();

		foreach ( $facets as $label => $facet ) {
			if ( empty( $this->facets[ $label ] ) )
				continue;

			$facets_data[ $label ] = $this->facets[ $label ];
			$facets_data[ $label ]['items'] = array();

			// All taxonomy terms are going to have the same query_var
			if( 'taxonomy' == $this->facets[ $label ]['type'] ) {
				$tax_query_var = $this->get_taxonomy_query_var( $this->facets[ $label ]['taxonomy'] );

				if ( ! $tax_query_var )
					continue;

				$existing_term_slugs = ( get_query_var( $tax_query_var ) ) ? explode( ',', get_query_var( $tax_query_var ) ) : array();

				// This plugon's custom "categery" isn't a real query_var, so manually handle it
				if ( 'category' == $tax_query_var && ! empty( $_GET[ $tax_query_var ] ) ) {
					$slugs = explode( ',', $_GET[ $tax_query_var ] );

					foreach ( $slugs as $slug ) {
						$existing_term_slugs[] = $slug;
					}
				}
			}

			$items = array();
			if ( ! empty( $facet['terms'] ) ) {
				$items = (array) $facet['terms'];
			}
			elseif ( ! empty( $facet['entries'] ) ) {
				$items = (array) $facet['entries'];
			}

			// Some facet types like date_histogram don't support the max results parameter
			if ( count( $items ) > $this->facets[ $label ]['count'] ) {
				$items = array_slice( $items, 0, $this->facets[ $label ]['count'] );
			}

			foreach ( $items as $item ) {
				$query_vars = array();

				switch ( $this->facets[ $label ]['type'] ) {
					case 'taxonomy':
						$term = get_term_by( 'id', $item['term'], $this->facets[ $label ]['taxonomy'] );

						if ( ! $term )
							continue 2; // switch() is considered a looping structure

						// Don't allow refinement on a term we're already refining on
						if ( in_array( $term->slug, $existing_term_slugs ) )
							continue 2;

						$slugs = array_merge( $existing_term_slugs, array( $term->slug ) );

						$query_vars = array( $tax_query_var => implode( ',', $slugs ) );
						$name       = $term->name;

						break;

					case 'post_type':
						$post_type = get_post_type_object( $item['term'] );

						if ( ! $post_type || $post_type->exclude_from_search )
							continue 2;  // switch() is considered a looping structure

						$query_vars = array( 'post_type' => $item['term'] );
						$name       = $post_type->labels->singular_name;

						break;

					case 'date_histogram':
						$timestamp = $item['time'] / 1000;

						switch ( $this->facets[ $label ]['interval'] ) {
							case 'year':
								$query_vars = array(
									'year'     => date( 'Y', $timestamp ),
									'monthnum' => false,
									'day'      => false,
								);
								$name = date( 'Y', $timestamp );
								break;

							case 'month':
								$query_vars = array(
									'year'     => date( 'Y', $timestamp ),
									'monthnum' => date( 'n', $timestamp ),
									'day'      => false,
								);
								$name = date( 'F Y', $timestamp );
								break;

							case 'day':
								$query_vars = array(
									'year'     => date( 'Y', $timestamp ),
									'monthnum' => date( 'n', $timestamp ),
									'day'      => date( 'j', $timestamp ),
								);
								$name = date( 'F jS, Y', $timestamp );
								break;

							default:
								continue 3; // switch() is considered a looping structure
						}

						break;

					default:
						//continue 2; // switch() is considered a looping structure
				}

				$facets_data[ $label ]['items'][] = array(
					'url'        => add_query_arg( $query_vars ),
					'query_vars' => $query_vars,
					'name'       => $name,
					'count'      => $item['count'],
				);
			}
		}

		return $facets_data;
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
}
