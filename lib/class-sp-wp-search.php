<?php

/**
 * You know, for WordPress-style searching.
 *
 * This class provides an object with which you can perform searches with
 * Elasticsearch, using a WordPress-friendly syntax.
 */
class SP_WP_Search extends SP_Search {

	public $wp_args;

	public $facets = array();

	public function __construct( $wp_args ) {
		$this->wp_args = apply_filters( 'sp_search_wp_query_args', $wp_args );
		$es_args = $this->wp_to_es_args( $this->wp_args );
		if ( ! empty( $this->wp_args['facets'] ) ) {
			$this->facets = $this->wp_args['facets'];
		}
		$this->search( $es_args );
	}

	public static function wp_to_es_args( $args ) {
		$defaults = array(
			'query'          => null,    // Search phrase
			'query_fields'   => array(
				'post_title^3',
				'post_excerpt^2',
				'post_content',
				'post_author.display_name',
				'terms.category.name.value',
				'terms.post_tag.name.value'
			),

			'post_type'      => null,  // string or an array
			'terms'          => array(), // ex: array( 'taxonomy-1' => 'slug', 'taxonomy-2' => 'slug-a,slug-b', 'taxonomy-3' => 'slug-c+slug-d+slug-e' )

			'author'         => null,    // id or an array of ids
			'author_name'    => array(), // string or an array

			'date_range'     => null,    // array( 'field' => 'post_date', 'gt' => 'YYYY-MM-dd', 'lte' => 'YYYY-MM-dd' ); date formats: 'YYYY-MM-dd' or 'YYYY-MM-dd HH:MM:SS'

			'orderby'        => null,    // Defaults to 'relevance' if query is set, otherwise 'date'. Pass an array for multiple orders.
			'order'          => 'DESC',

			'posts_per_page' => 10,
			'offset'         => null,
			'paged'          => 1,

			/**
			 * Facets. Examples:
			 * array(
			 *     'Tag'       => array( 'type' => 'taxonomy', 'taxonomy' => 'post_tag', 'count' => 10 ),
			 *     'Post Type' => array( 'type' => 'post_type', 'count' => 10 ),
			 * )
			 */
			'facets'         => null,

			'fields'         => array( 'post_id' )
		);

		$args = wp_parse_args( $args, $defaults );

		// Posts per page
		$es_query_args = array(
			'size' => absint( $args['posts_per_page'] ),
		);
		$filters = array();

		/**
		 * Pagination
		 *
		 * @see trac ticket 18897
		 *
		 * Important: SearchPress currently emulates the (arguably broken)
		 * behavior of core here. The above mentioned ticket would alter this
		 * behavior, and should that happen, SearchPress would be updated to
		 * reflect. In other words, presently, if offset is set, paged is
		 * ignored. If core ever allows both to be set, so will SP.
		 */
		if ( ! empty( $args['offset'] ) ) {
			$es_query_args['from'] = absint( $args['offset'] );
		} else {
			$es_query_args['from'] = max( 0, ( absint( $args['paged'] ) - 1 ) * $es_query_args['size'] );
		}

		// Post type
		if ( ! empty( $args['post_type'] ) ) {
			if ( 'any' == $args['post_type'] ) {
				$args['post_type'] = sp_searchable_post_types();
			}
			$filters[] = array( 'terms' => array( 'post_type.raw' => (array) $args['post_type'] ) );
		}

		// Author
		// @todo Add support for comma-delim terms like wp_query
		if ( ! empty( $args['author'] ) ) {
			$filters[] = array( 'terms' => array( 'post_author.user_id' => (array) $args['author'] ) );
		}
		if ( ! empty( $args['author_name'] ) ) {
			$filters[] = array( 'terms' => array( 'post_author.login' => (array) $args['author_name'] ) );
		}

		// Date range
		if ( ! empty( $args['date_range'] ) ) {
			if ( ! empty( $args['date_range']['field'] ) ) {
				$field = $args['date_range']['field'];
				unset( $args['date_range']['field'] );
			} else {
				$field = 'post_date';
			}
			$filters[] = array( 'range' => array( "{$field}.date" => $args['date_range'] ) );
		}

		// Taxonomy terms
		if ( ! empty( $args['terms'] ) ) {
			foreach ( (array) $args['terms'] as $tax => $terms ) {
				if ( strpos( $terms, ',' ) ) {
					$terms = explode( ',', $terms );
					$comp = 'or';
				} else {
					$terms = explode( '+', $terms );
					$comp = 'and';
				}

				$terms = array_map( 'sanitize_title', $terms );
				if ( count( $terms ) ) {
					$tax_fld = 'terms.' . $tax . '.slug';
					foreach ( $terms as $term ) {
						if ( 'and' == $comp )
							$filters[] = array( 'term' => array( $tax_fld => $term ) );
						else
							$or[] = array( 'term' => array( $tax_fld => $term ) );
					}

					if ( 'or' == $comp )
						$filters[] = array( 'or' => $or );
				}
			}
		}

		if ( ! empty( $filters ) ) {
			$es_query_args['filter'] = array( 'and' => $filters );
		}

		// Fill in the query
		//  todo: add auto phrase searching
		//  todo: add fuzzy searching to correct for spelling mistakes
		//  todo: boost title, tag, and category matches
		if ( ! empty( $args['query'] ) ) {
			$multi_match = array( array( 'multi_match' => array(
				'query'    => $args['query'],
				'fields'   => $args['query_fields'],
				'type'     => 'cross_fields',
				'operator' => 'and'
			) ) );

			$es_query_args['query']['bool']['must'] = $multi_match;

			if ( ! $args['orderby'] ) {
				$args['orderby'] = 'relevance';
			}
		} elseif ( empty( $args['orderby'] ) ) {
			$args['orderby'] = 'date';
		}

		// Ordering

		$es_query_args['sort'] = array();
		if ( is_string( $args['orderby'] ) ) {
			$args['order'] = ( 'asc' == strtolower( $args['order'] ) ) ? 'asc' : 'desc';
			$args['orderby'] = array( $args['orderby'] => $args['order'] );
		}

		foreach ( (array) $args['orderby'] as $orderby => $order ) {
			$order = ( 'asc' == strtolower( $order ) ) ? 'asc' : 'desc';
			// Translate orderby from WP field to ES field
			switch ( strtolower( $orderby ) ) {
				case 'relevance' :
					$es_query_args['sort'][] = array( '_score' => $order );
					break;
				case 'date' :
					$es_query_args['sort'][] = array( 'post_date.date' => $order );
					break;
				case 'modified' :
					$es_query_args['sort'][] = array( 'post_modified.date' => $order );
					break;
				case 'id' :
					$es_query_args['sort'][] = array( 'post_id' => $order );
					break;
				case 'author' :
					$es_query_args['sort'][] = array( 'post_author.user_id' => $order );
					break;
				case 'name' :
					$es_query_args['sort'][] = array( 'post_name.raw' => $order );
					break;
				case 'title' :
					$es_query_args['sort'][] = array( 'post_title.raw' => $order );
					break;
				case 'menu_order' :
					$es_query_args['sort'][] = array( 'menu_order' => $order );
					break;
				case 'parent' :
					$es_query_args['sort'][] = array( 'post_parent' => $order );
					break;
			}
		}
		if ( empty( $es_query_args['sort'] ) ) {
			unset( $es_query_args['sort'] );
		}

		// Facets
		if ( ! empty( $args['facets'] ) ) {
			foreach ( (array) $args['facets'] as $label => $facet ) {
				switch ( $facet['type'] ) {

					case 'taxonomy':
						$es_query_args['facets'][ $label ] = array(
							'terms' => array(
								'field' => "terms.{$facet['taxonomy']}.slug",
								'size' => $facet['count'],
							),
						);

						break;

					case 'post_type':
						$es_query_args['facets'][ $label ] = array(
							'terms' => array(
								'field' => 'post_type',
								'size' => $facet['count'],
							),
						);

						break;

					case 'date_histogram':
						$es_query_args['facets'][ $label ] = array(
							'date_histogram' => array(
								'interval' => $facet['interval'],
								'field'    => ! empty( $facet['field'] ) ? "{$facet['field']}.date" : 'post_date.date',
								'size'     => $facet['count'],
							),
						);

						break;
				}
			}

			// If we have facets, we need to move our filters to a filtered
			// query, or else they won't have an effect on the facets.
			if ( ! empty( $es_query_args['facets'] ) ) {
				if ( ! empty( $es_query_args['filter'] ) ) {
					if ( ! empty( $es_query_args['query'] ) ) {
						$es_query = $es_query_args['query'];
					}
					$es_query_args['query'] = array(
						'filtered' => array(
							'filter' => $es_query_args['filter']
						)
					);
					unset( $es_query_args['filter'] );
					if ( ! empty( $es_query ) ) {
						$es_query_args['query']['filtered']['query'] = $es_query;
					}
				}
			}
		}

		// Fields
		if ( ! empty( $args['fields'] ) ) {
			$es_query_args['fields'] = (array) $args['fields'];
		}

		return $es_query_args;
	}

	// Turns raw ES facet data into data that is more useful in a WordPress setting
	public function get_facet_data() {
		if ( empty( $this->facets ) ) {
			return false;
		}

		$facets = $this->get_results( 'facets' );

		if ( ! $facets ) {
			return false;
		}

		$facet_data = array();

		foreach ( $facets as $label => $facet ) {
			if ( empty( $this->facets[ $label ] ) ) {
				continue;
			}

			$facet_data[ $label ] = $this->facets[ $label ];
			$facet_data[ $label ]['items'] = array();

			// All taxonomy terms are going to have the same query_var
			if( 'taxonomy' == $this->facets[ $label ]['type'] ) {
				$tax_query_var = $this->get_taxonomy_query_var( $this->facets[ $label ]['taxonomy'] );

				if ( ! $tax_query_var ) {
					continue;
				}

				$existing_term_slugs = ( get_query_var( $tax_query_var ) ) ? explode( ',', get_query_var( $tax_query_var ) ) : array();
			}

			$items = array();
			if ( ! empty( $facet['terms'] ) ) {
				$items = (array) $facet['terms'];
			} elseif ( ! empty( $facet['entries'] ) ) {
				$items = (array) $facet['entries'];
			}

			// Some facet types like date_histogram don't support the max results parameter
			if ( count( $items ) > $this->facets[ $label ]['count'] ) {
				$items = array_slice( $items, 0, $this->facets[ $label ]['count'] );
			}

			foreach ( $items as $item ) {
				if ( false === ( $datum = apply_filters( 'sp_search_facet_datum', false, $item, $this->facets ) ) ) {
					$query_vars = array();

					switch ( $this->facets[ $label ]['type'] ) {
						case 'taxonomy':
							$term = get_term_by( 'slug', $item['term'], $this->facets[ $label ]['taxonomy'] );

							if ( ! $term ) {
								continue 2; // switch() is considered a looping structure
							}

							// Don't allow refinement on a term we're already refining on
							if ( in_array( $term->slug, $existing_term_slugs ) ) {
								continue 2;
							}

							$slugs = array_merge( $existing_term_slugs, array( $term->slug ) );

							$query_vars = array( $tax_query_var => implode( ',', $slugs ) );
							$name       = $term->name;

							break;

						case 'post_type':
							$post_type = get_post_type_object( $item['term'] );

							if ( ! $post_type || $post_type->exclude_from_search ) {
								continue 2;  // switch() is considered a looping structure
							}

							$query_vars = array( 'post_type' => $item['term'] );
							$name       = $post_type->labels->singular_name;

							break;

						case 'date_histogram':
							$timestamp = $item['time'] / 1000;

							switch ( $this->facets[ $label ]['interval'] ) {
								case 'year':
									$query_vars = array(
										'year'     => date( 'Y', $timestamp ),
									);
									$name = date( 'Y', $timestamp );
									break;

								case 'month':
									$query_vars = array(
										'year'     => date( 'Y', $timestamp ),
										'monthnum' => date( 'n', $timestamp ),
									);
									$name = date( 'F Y', $timestamp );
									break;

								case 'day':
									$query_vars = array(
										'year'     => date( 'Y', $timestamp ),
										'monthnum' => date( 'n', $timestamp ),
										'day'      => date( 'j', $timestamp ),
									);
									$name = date( 'F j, Y', $timestamp );
									break;

								default:
									continue 3; // switch() is considered a looping structure
							}

							break;

						default:
							//continue 2; // switch() is considered a looping structure
					}

					$datum = array(
						'url'        => add_query_arg( $query_vars ),
						'query_vars' => $query_vars,
						'name'       => $name,
						'count'      => $item['count'],
					);
				}

				$facet_data[ $label ]['items'][] = $datum;
			}
		}

		return apply_filters( 'sp_search_facet_data', $facet_data );
	}

	protected function get_taxonomy_query_var( $taxonomy_name ) {
		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( ! $taxonomy || is_wp_error( $taxonomy ) ) {
			return false;
		}

		return $taxonomy->query_var;
	}

}