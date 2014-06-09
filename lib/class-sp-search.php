<?php
/**
 * Copyright (C) 2012-2013 Automattic
 * Copyright (C) 2013 SearchPress
 *
 * The following code is a derivative work of code from the Automattic plugin
 * WordPress.com VIP Search Add-On, which is licensed GPLv2. This code therefore
 * is also licensed under the terms of the GNU Public License, verison 2.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 or greater,
 * as published by the Free Software Foundation.
 *
 * You may NOT assume that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * The license for this software can likely be found here:
 * http://www.gnu.org/licenses/gpl-2.0.html
 */


if ( !class_exists( 'SP_Search' ) ) :

class SP_Search {

	public $facets = array();

	private $do_found_posts;

	private $found_posts = 0;

	private $search_result;

	private $sp;

	private static $instance;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone SP_Search" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup SP_Search" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Search;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		if ( ! is_admin() && SP_Config()->active() ) {
			$this->init_hooks();
		}
	}

	public function init_hooks() {
		# Checks to see if we need to worry about found_posts
		add_filter( 'post_limits_request', array( $this, 'filter__post_limits_request' ), 999, 2 );

		# Replaces the standard search query with one that fetches the posts based on post IDs supplied by ES
		add_filter( 'posts_request',       array( $this, 'filter__posts_request' ),         5, 2 );

		# Nukes the FOUND_ROWS() database query
		add_filter( 'found_posts_query',   array( $this, 'filter__found_posts_query' ),     5, 2 );

		# Since the FOUND_ROWS() query was nuked, we need to supply the total number of found posts
		add_filter( 'found_posts',         array( $this, 'filter__found_posts' ),           5, 2 );

		# Add our custom query var for advanced searches
		add_filter( 'query_vars',          array( $this, 'query_vars' ) );

		# Force the search template if ?sp[force]=1
		add_action( 'parse_query',         array( $this, 'force_search_template' ), 5 );
	}


	/**
	 * Add a query var for holding advanced search fields
	 *
	 * @param array $qv
	 * @return array
	 */
	public function query_vars( $qv ) {
		$qv[] = 'sp';
		return $qv;
	}


	/**
	 * Set a faceted search as a search (and thus force the search template). A hook for the parse_query action.
	 *
	 * @param object $wp_query The current WP_Query. Passed by reference and modified if necessary.
	 * @return void
	 * @author Matthew Boynes
	 */
	public function force_search_template( &$wp_query ) {
		if ( ! $wp_query->is_main_query() )
			return;

		# Load our sp query string variable
		$this->sp = get_query_var( 'sp' );

		# If this is a search, but not a keyword search, we have to fake it
		if ( ! $wp_query->is_search() && ! empty( $this->sp ) && '1' == $this->sp['force'] ) {
			# First, we'll set the search string to something phony
			$wp_query->set( 's', '1441f19754335ca4638bfdf1aea00c6d' );
			$wp_query->is_search = true;
			$wp_query->is_home = false;
		}
	}


	public function search( $es_args ) {
		$es_args = apply_filters( 'sp_search_query_args', $es_args );

		return SP_API()->search( json_encode( $es_args ), array( 'output' => ARRAY_A ) );
	}


	public function wp_search( $wp_args ) {
		$wp_args = apply_filters( 'sp_search_wp_query_args', $wp_args );
		$es_args = $this->wp_to_es_args( $wp_args );

		return $this->search( $es_args );
	}


	public function wp_to_es_args( $args ) {
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
			 *     'Tag'       => array( 'type' => 'taxonomy', 'taxonomy' => 'post_tag', 'count' => 10 ) ),
			 *     'Post Type' => array( 'type' => 'post_type', 'count' => 10 ) ),
			 * );
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
				'operator' => 'and'
			) ) );

			$multi_match = $this->setup_multi_match_query( $multi_match );

			$es_query_args['query']['bool']['must'] = $multi_match;

			if ( ! $args['orderby'] ) {
				$args['orderby'] = array( 'relevance' );
			}
		} elseif ( empty( $args['orderby'] ) ) {
			$args['orderby'] = array( 'date' );
		}

		// Ordering
		if ( 'asc' == strtolower( $args['order'] ) ) {
			$args['order'] = 'asc';
		} else {
			$args['order'] = 'desc';
		}

		$es_query_args['sort'] = array();
		foreach ( (array) $args['orderby'] as $orderby ) {
			// Translate orderby from WP field to ES field
			switch ( $orderby ) {
				case 'relevance' :
					$es_query_args['sort'][] = array( '_score' => $args['order'] );
					break;
				case 'date' :
					$es_query_args['sort'][] = array( 'post_date.date' => $args['order'] );
					break;
				case 'modified' :
					$es_query_args['sort'][] = array( 'post_modified.date' => $args['order'] );
					break;
				case 'ID' :
				case 'id' :
					$es_query_args['sort'][] = array( 'post_id' => $args['order'] );
					break;
				case 'author' :
					$es_query_args['sort'][] = array( 'post_author.user_id' => $args['order'] );
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

	public function filter__post_limits_request( $limits, $query ) {
		if ( ! $query->is_search() )
			return $limits;

		if ( empty( $limits ) || $query->get( 'no_found_rows' ) ) {
			$this->do_found_posts = false;
		} else {
			$this->do_found_posts = true;
		}

		return $limits;
	}

	public function filter__posts_request( $sql, &$query ) {
		global $wpdb;

		if ( ! $query->is_main_query() || ! $query->is_search() )
			return $sql;

		$page = ( $query->get( 'paged' ) ) ? absint( $query->get( 'paged' ) ) : 1;

		# If we put in a phony search term, remove it now
		if ( '1441f19754335ca4638bfdf1aea00c6d' == $query->get( 's' ) )
			$query->set( 's', '' );

		// Start building the WP-style search query args
		// They'll be translated to ES format args later
		$es_wp_query_args = array(
			'query'          => $query->get( 's' ),
			'posts_per_page' => $query->get( 'posts_per_page' ),
			'paged'          => $page,
		);

		$query_vars = $this->parse_query( $query );

		# Set taxonomy terms
		if ( ! empty( $query_vars['terms'] ) )
			$es_wp_query_args['terms'] = $query_vars['terms'];

		# Set post types
		$es_wp_query_args['post_type'] = $query_vars['post_type'];

		# Set date range
		if ( $query->get( 'year' ) ) {
			if ( $query->get( 'monthnum' ) ) {
				// Padding
				$date_monthnum = sprintf( '%02d', $query->get( 'monthnum' ) );

				if ( $query->get( 'day' ) ) {
					// Padding
					$date_day = sprintf( '%02d', $query->get( 'day' ) );

					$date_start = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $date_day . ' 00:00:00';
					$date_end   = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $date_day . ' 23:59:59';
				} else {
					$days_in_month = date( 't', mktime( 0, 0, 0, $query->get( 'monthnum' ), 14, $query->get( 'year' ) ) ); // 14 = middle of the month so no chance of DST issues

					$date_start = $query->get( 'year' ) . '-' . $date_monthnum . '-01 00:00:00';
					$date_end   = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $days_in_month . ' 23:59:59';
				}
			} else {
				$date_start = $query->get( 'year' ) . '-01-01 00:00:00';
				$date_end   = $query->get( 'year' ) . '-12-31 23:59:59';
			}

			$es_wp_query_args['date_range'] = array( 'gte' => $date_start, 'lte' => $date_end );
		}

		# Advanced search fields
		if ( ! empty( $this->sp ) ) {
			# Date from and to
			if ( ! empty( $this->sp['f'] ) && $gte = strtotime( $this->sp['f'] ) ) {
				$es_wp_query_args['date_range']['gte'] = date( 'Y-m-d 00:00:00', $gte );
			}
			if ( ! empty( $this->sp['t'] ) && $lte = strtotime( $this->sp['t'] ) ) {
				$es_wp_query_args['date_range']['lte'] = date( 'Y-m-d 23:59:59', $lte );
			}
		}

		if ( ! empty( $es_wp_query_args['date_range'] ) && empty( $es_wp_query_args['date_range']['field'] ) ) {
			$es_wp_query_args['date_range']['field'] = 'post_date.date';
		} elseif ( ! empty( $es_wp_query_args['date_range']['field'] ) ) {
			$es_wp_query_args['date_range']['field'] .= '.date';
		}


		/** Ordering */
		# Set results sorting
		if ( $orderby = $query->get( 'orderby' ) ) {
			if ( in_array( $orderby, array( 'date', 'relevance' ) ) )
				$es_wp_query_args['orderby'] = $orderby;
		}

		# Set sort ordering
		if ( $order = strtolower( $query->get( 'order' ) ) ) {
			if ( 'date' == $es_wp_query_args['orderby'] && in_array( $order, array( 'asc', 'desc' ) ) )
				$es_wp_query_args['order'] = $order;
		}


		// Facets
		if ( ! empty( $this->facets ) ) {
			$es_wp_query_args['facets'] = $this->facets;
		}

		// You can use this filter to modify the search query parameters, such as controlling the post_type.
		// These arguments are in the format for wp_to_es_args(), i.e. WP-style.
		$es_wp_query_args = apply_filters( 'sp_search_wp_query_args', $es_wp_query_args, $query );

		// Convert the WP-style args into ES args
		$es_query_args = $this->wp_to_es_args( $es_wp_query_args );

		$es_query_args['fields'] = array( 'post_id' );

		// Do the actual search query!
		$this->search_result = $this->search( $es_query_args );

		if ( is_wp_error( $this->search_result ) || ! is_array( $this->search_result ) || empty( $this->search_result['hits'] ) || empty( $this->search_result['hits']['hits'] ) ) {
			$this->found_posts = 0;
			return "SELECT * FROM {$wpdb->posts} WHERE 1=0 /* SearchPress search results */";
		}

		// Get the post IDs of the results
		$post_ids = array();
		foreach ( (array) $this->search_result['hits']['hits'] as $result ) {
			// Fields arg
			if ( ! empty( $result['fields'] ) && ! empty( $result['fields']['post_id'] ) ) {
				$result['fields']['post_id'] = (array) $result['fields']['post_id'];
				$post_ids[] = reset( $result['fields']['post_id'] );
			}
			// Full source objects
			elseif ( ! empty( $result['_source'] ) && ! empty( $result['_source']['id'] ) ) {
				$post_ids[] = $result['_source']['id'];
			}
			// Unknown results format
			else {
				return "SELECT * FROM {$wpdb->posts} WHERE 1=0 /* SearchPress search results */"; // $sql;
			}
		}

		// Total number of results for paging purposes
		$this->found_posts = $this->search_result['hits']['total'];

		// Replace the search SQL with one that fetches the exact posts we want in the order we want
		$post_ids_string = implode( ',', array_map( 'absint', $post_ids ) );
		return "SELECT * FROM {$wpdb->posts} WHERE {$wpdb->posts}.ID IN( {$post_ids_string} ) ORDER BY FIELD( {$wpdb->posts}.ID, {$post_ids_string} ) /* SearchPress search results */";
	}

	public function filter__found_posts_query( $sql, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() )
			return $sql;

		return '';
	}

	public function filter__found_posts( $found_posts, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() )
			return $found_posts;
		return $this->found_posts;
	}

	public function set_facets( $facets ) {
		$this->facets = $facets;
	}

	public function get_search_result( $raw = false ) {
		if ( $raw )
			return $this->search_result;

		return ( ! empty( $this->search_result ) && ! is_wp_error( $this->search_result ) && is_array( $this->search_result ) && ! empty( $this->search_result['hits'] ) ) ? $this->search_result['hits'] : false;
	}

	public function get_search_facets() {
		$search_result = $this->get_search_result();
		return ( ! empty( $search_result ) && ! empty( $search_result['facets'] ) ) ? $search_result['facets'] : array();
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

	public function get_taxonomy_query_var( $taxonomy_name ) {
		$taxonomy = get_taxonomy( $taxonomy_name );

		if ( ! $taxonomy || is_wp_error( $taxonomy ) )
			return false;

		// category_name only accepts a single slug so make a custom, fake query var for categories
		if ( 'category_name' == $taxonomy->query_var )
			$taxonomy->query_var = 'category';

		return $taxonomy->query_var;
	}

	public function get_valid_taxonomy_query_vars( $query = false ) {
		$taxonomies = get_taxonomies( array( 'public' => true ), $output = 'objects' );
		$query_vars = wp_list_pluck( $taxonomies, 'query_var' );
		if ( $query ) {
			$return = array();
			foreach ( $query->query as $qv => $value ) {
				if ( in_array( $qv, $query_vars ) ) {
					$taxonomy = array_search( $qv, $query_vars );
					$return[ $taxonomy ] = $value;
				}
			}
			return $return;
		}
		return $query_vars;
	}

	public function parse_query( $query ) {
		$vars = array();

		# Taxonomy filters
		$terms = $this->get_valid_taxonomy_query_vars( $query );
		if ( ! empty( $terms ) ) {
			$vars['terms'] = $terms;
		}

		# Post type filters
		$searchable_post_types = sp_searchable_post_types();

		if ( $query->get( 'post_type' ) && 'any' != $query->get( 'post_type' ) ) {
			$post_types = (array) $query->get( 'post_type' );
		} elseif ( ! empty( $_GET['post_type'] ) ) {
			$post_types = explode( ',', $_GET['post_type'] );
		} else {
			$post_types = false;
		}

		$vars['post_type'] = array();

		# Validate post types, making sure they exist and are not excluded from search
		if ( $post_types ) {
			foreach ( (array) $post_types as $post_type ) {
				if ( in_array( $post_type, $searchable_post_types ) ) {
					$vars['post_type'][] = $post_type;
				}
			}
		}

		if ( empty( $vars['post_type'] ) )
			$vars['post_type'] = $searchable_post_types;

		return $vars;
	}

	/**
	 * Ugly hack to search across all fields for each word in the query. By default, multi_match requires that each
	 * word in a phrase exist in the same field. This is not ideal for our purposes; we'd rather each word exist in
	 * any of the fields. There's no clean way to accomplish that, so we create a massive bool query.
	 *
	 * @return array
	 */
	public function setup_multi_match_query( $multi_match ) {
		if ( $words = preg_split( '/\s+/', $multi_match[0]['multi_match']['query'] ) ) {
			$matches = array();
			$base = $multi_match[0]['multi_match'];
			# Stopwords will break this hack, because if a query just has a stopword, that's like searching for nothing
			$stopwords = array( "a", "an", "and", "are", "as", "at", "be", "but", "by", "for", "if", "in", "into", "is", "it", "no", "not", "of", "on", "or", "such", "that", "the", "their", "then", "there", "these", "they", "this", "to", "was", "will", "with" );
			foreach ( $words as $word ) {
				# Make sure that the "word" isn't empty, isn't a stopword, and has at least one letter or number in it
				if ( empty( $word ) || in_array( strtolower( $word ), $stopwords ) || ! preg_match( '/[a-z0-9]/i', $word ) )
					continue;
				$matches[] = array( 'multi_match' => array_merge( $base, array( 'query' => $word ) ) );
			}
			if ( ! empty( $matches ) ) {
				return $matches;
			}
		}

		return $multi_match;
	}


}

function SP_Search() {
	return SP_Search::instance();
}
add_action( 'after_setup_theme', 'SP_Search' );

endif;