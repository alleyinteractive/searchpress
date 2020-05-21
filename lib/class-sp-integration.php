<?php
/**
 * SearchPress library: SP_Integration class
 *
 * @package SearchPress
 */

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
class SP_Integration extends SP_Singleton {

	/**
	 * Whether we should execute the found_posts query or not.
	 *
	 * @access protected
	 * @var bool
	 */
	protected $do_found_posts;

	/**
	 * The number of found posts for this query.
	 *
	 * @access protected
	 * @var int
	 */
	protected $found_posts = 0;

	/**
	 * The SearchPress query variable from the current query.
	 *
	 * @access protected
	 * @var array
	 */
	protected $sp;

	/**
	 * The search object in use for this request.
	 *
	 * @access public
	 * @var SP_WP_Search
	 */
	public $search_obj;

	/**
	 * Initializes functionality of this class.
	 *
	 * @codeCoverageIgnore
	 * @access public
	 */
	public function setup() {
		if ( ! is_admin() && apply_filters( 'sp_ready', null ) ) {
			$this->init_hooks();
		}
	}

	/**
	 * Initializes action and filter hooks used by SearchPress.
	 *
	 * @access public
	 */
	public function init_hooks() {
		add_filter( 'posts_pre_query', [ $this, 'filter__posts_pre_query' ], 10, 2 );

		// Checks to see if we need to worry about found_posts.
		// add_filter( 'post_limits_request', array( $this, 'filter__post_limits_request' ), 999, 2 );

		// // Replaces the standard search query with one that fetches the posts based on post IDs supplied by ES.
		// add_filter( 'posts_request', array( $this, 'filter__posts_request' ), 5, 2 );

		// // Nukes the FOUND_ROWS() database query.
		// add_filter( 'found_posts_query', array( $this, 'filter__found_posts_query' ), 5, 2 );

		// // Since the FOUND_ROWS() query was nuked, we need to supply the total number of found posts.
		// add_filter( 'found_posts', array( $this, 'filter__found_posts' ), 5, 2 );

		// Add our custom query var for advanced searches.
		add_filter( 'query_vars', array( $this, 'query_vars' ) );

		// Force the search template if ?sp[force]=1.
		add_action( 'parse_query', array( $this, 'force_search_template' ), 5 );
	}

	/**
	 * Removes SearchPress hooks when SearchPress should not be used.
	 *
	 * @access public
	 */
	public function remove_hooks() {
		// remove_filter( 'post_limits_request', array( $this, 'filter__post_limits_request' ), 999, 2 );
		// remove_filter( 'posts_request', array( $this, 'filter__posts_request' ), 5, 2 );
		// remove_filter( 'found_posts_query', array( $this, 'filter__found_posts_query' ), 5, 2 );
		// remove_filter( 'found_posts', array( $this, 'filter__found_posts' ), 5, 2 );
		remove_filter( 'query_vars', array( $this, 'query_vars' ) );
		remove_action( 'parse_query', array( $this, 'force_search_template' ), 5 );
	}

	/**
	 * Filter the 'posts_pre_query' action to replace the entire query with the results
	 * returned by SearchPress.
	 *
	 * @param array|null $posts Array of posts, defaults to null.
	 * @param \WP_Query  $query Query object.
	 * @return array|null
	 */
	public function filter__posts_pre_query( $posts, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $posts;
		}

		$es_wp_query_args = $this->build_es_request( $query );

		// Convert the WP-style args into ES args.
		$this->search_obj = new SP_WP_Search( $es_wp_query_args );
		$results          = $this->search_obj->get_results( 'hits' );

		// Total number of results for paging purposes.
		$this->found_posts  = $this->search_obj->get_results( 'total' );
		$query->found_posts = $this->found_posts;

		if ( empty( $results ) ) {
			return [];
		}

		/**
		 * Allow the entire SearchPress result to be overridden.
		 *
		 * @param WP_Post[]|null $results Query results.
		 * @param SP_WP_Search   $search Search object.
		 * @param WP_Query       WP Query object.
		 */
		$pre_search_results = apply_filters( 'sp_pre_search_results', null, $this->search_obj, $query );
		if ( null !== $pre_search_results ) {
			return $pre_search_results;
		}

		// Get the post IDs of the results.
		$post_ids = $this->search_obj->pluck_field();
		$post_ids = array_filter( array_map( 'absint', $post_ids ) );
		$posts    = array_filter( array_map( 'get_post', $post_ids ) );
		return $posts;
	}


	/**
	 * Add a query var for holding advanced search fields.
	 *
	 * @param array $qv Query variables to be filtered.
	 * @return array The filtered list of query variables.
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
		if ( ! $wp_query->is_main_query() ) {
			return;
		}

		// Load our sp query string variable.
		$this->sp = get_query_var( 'sp' );

		// If this is a search, but not a keyword search, we have to fake it.
		if ( ! $wp_query->is_search() && ! empty( $this->sp ) && 1 === intval( $this->sp['force'] ) ) {
			// First, we'll set the search string to something phony.
			$wp_query->set( 's', '1441f19754335ca4638bfdf1aea00c6d' );
			$wp_query->is_search = true;
			$wp_query->is_home   = false;
		}
	}

	/**
	 * A filter callback for post_limits_request to determine if we should
	 * calculate the total number of posts that match the query or not.
	 *
	 * @param string   $limits The LIMIT clause of the query.
	 * @param WP_Query $query  The current query being executed.
	 * @access public
	 * @return string The unmodified value of $limits.
	 */
	public function filter__post_limits_request( $limits, $query ) {
		if ( ! $query->is_search() ) {
			return $limits;
		}

		if ( empty( $limits ) || $query->get( 'no_found_rows' ) ) {
			$this->do_found_posts = false;
		} else {
			$this->do_found_posts = true;
		}

		return $limits;
	}

	/**
	 * A filter callback for posts_request that replaces the normal query with
	 * one that queries based on post IDs found by Elasticsearch in the proper
	 * order.
	 *
	 * @param string   $sql   The SQL to be filtered.
	 * @param WP_Query $query The query object for the query to be filtered.
	 * @access public
	 * @return string The modified SQL for the posts_request operation.
	 */
	public function filter__posts_request( $sql, $query ) {
		global $wpdb;

		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $sql;
		}

		// If we put in a phony search term, remove it now.
		if ( '1441f19754335ca4638bfdf1aea00c6d' === $query->get( 's' ) ) {
			$query->set( 's', '' );
		}

		$es_wp_query_args = $this->build_es_request( $query );

		// Convert the WP-style args into ES args.
		$this->search_obj = new SP_WP_Search( $es_wp_query_args );
		$results          = $this->search_obj->get_results( 'hits' );

		// Total number of results for paging purposes.
		$this->found_posts = $this->search_obj->get_results( 'total' );

		if ( empty( $results ) ) {
			return "SELECT * FROM {$wpdb->posts} WHERE 1=0 /* SearchPress search results */";
		}

		// Get the post IDs of the results.
		$post_ids = $this->search_obj->pluck_field();
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids );

		// Replace the search SQL with one that fetches the exact posts we want in the order we want.
		$post_ids_string = implode( ',', $post_ids );
		return "SELECT * FROM {$wpdb->posts} WHERE {$wpdb->posts}.ID IN( {$post_ids_string} ) ORDER BY FIELD( {$wpdb->posts}.ID, {$post_ids_string} ) /* SearchPress search results */";
	}

	/**
	 * Nixes the found posts query if we are going to keep track of the value
	 * ourselves by querying ES.
	 *
	 * @param string   $sql   The SQL to be used to determine the number of found posts.
	 * @param WP_Query $query The query currently being executed.
	 * @access public
	 * @return string The modified SQL for the found posts query.
	 */
	public function filter__found_posts_query( $sql, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $sql;
		}

		return '';
	}

	/**
	 * A filter callback for found_posts that overrides the main query and the
	 * search query to use SearchPress' found posts count.
	 *
	 * @param array    $found_posts The array of posts found by WordPress.
	 * @param WP_Query $query       The WP_Query object for the request.
	 * @access public
	 * @return int The number of found posts.
	 */
	public function filter__found_posts( $found_posts, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $found_posts;
		}

		return $this->found_posts;
	}

	/**
	 * Given a query object, build the variables needed for an Elasticsearch
	 * request.
	 *
	 * @param WP_Query $query The query to use when building the ES query.
	 * @access protected
	 * @return array The ES query to execute.
	 */
	protected function build_es_request( $query ) {
		$page = ( $query->get( 'paged' ) ) ? absint( $query->get( 'paged' ) ) : 1;

		// Start building the WP-style search query args.
		// They'll be translated to ES format args later.
		$es_wp_query_args = array(
			'query'          => $query->get( 's' ),
			'posts_per_page' => $query->get( 'posts_per_page' ),
			'paged'          => $page,
		);

		$query_vars = $this->parse_query( $query );

		// Set taxonomy terms.
		if ( ! empty( $query_vars['terms'] ) ) {
			$es_wp_query_args['terms'] = $query_vars['terms'];
		}

		// Set post types.
		if ( ! empty( $query_vars['post_type'] ) ) {
			$es_wp_query_args['post_type'] = $query_vars['post_type'];
		}

		// Set date range.
		if ( $query->get( 'year' ) ) {
			if ( $query->get( 'monthnum' ) ) {
				// Padding.
				$date_monthnum = sprintf( '%02d', $query->get( 'monthnum' ) );

				if ( $query->get( 'day' ) ) {
					// Padding.
					$date_day = sprintf( '%02d', $query->get( 'day' ) );

					$date_start = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $date_day . ' 00:00:00';
					$date_end   = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $date_day . ' 23:59:59';
				} else {
					$days_in_month = gmdate( 't', mktime( 0, 0, 0, $query->get( 'monthnum' ), 14, $query->get( 'year' ) ) ); // 14 = middle of the month so no chance of DST issues

					$date_start = $query->get( 'year' ) . '-' . $date_monthnum . '-01 00:00:00';
					$date_end   = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $days_in_month . ' 23:59:59';
				}
			} else {
				$date_start = $query->get( 'year' ) . '-01-01 00:00:00';
				$date_end   = $query->get( 'year' ) . '-12-31 23:59:59';
			}

			$es_wp_query_args['date_range'] = array(
				'gte' => $date_start,
				'lte' => $date_end,
			);
		}

		// Advanced search fields.
		if ( ! empty( $this->sp ) ) {
			// Date from and to.
			if ( ! empty( $this->sp['f'] ) ) {
				$gte = strtotime( $this->sp['f'] );
				if ( false !== $gte ) {
					$es_wp_query_args['date_range']['gte'] = gmdate( 'Y-m-d 00:00:00', $gte );
				}
			}
			if ( ! empty( $this->sp['t'] ) ) {
				$lte = strtotime( $this->sp['t'] );
				if ( false !== $lte ) {
					$es_wp_query_args['date_range']['lte'] = gmdate( 'Y-m-d 23:59:59', $lte );
				}
			}
		}

		if ( ! empty( $es_wp_query_args['date_range'] ) && empty( $es_wp_query_args['date_range']['field'] ) ) {
			$es_wp_query_args['date_range']['field'] = 'post_date';
		}

		/** Ordering */
		// Set results sorting.
		$orderby = $query->get( 'orderby' );
		if ( ! empty( $orderby ) ) {
			if ( in_array( $orderby, array( 'date', 'relevance' ), true ) ) {
				$es_wp_query_args['orderby'] = $orderby;
			}
		}

		// Set sort ordering.
		$order = strtolower( $query->get( 'order' ) );
		if ( ! empty( $order ) ) {
			if ( in_array( $order, array( 'asc', 'desc' ), true ) ) {
				$es_wp_query_args['order'] = $order;
			}
		}

		// Facets.
		if ( ! empty( $this->facets ) ) {
			$es_wp_query_args['facets'] = $this->facets;
		}

		$es_wp_query_args['fields'] = array( 'post_id' );

		return $es_wp_query_args;
	}

	/**
	 * Gets a list of valid taxonomy query variables, optionally filtering by
	 * a provided query.
	 *
	 * @param WP_Query|bool $query Optional. The query to filter by. Defaults to false.
	 * @access protected
	 * @return array An array of valid taxonomy query variables.
	 */
	protected function get_valid_taxonomy_query_vars( $query = false ) {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$query_vars = wp_list_pluck( $taxonomies, 'query_var' );
		if ( $query ) {
			$return = array();
			foreach ( $query->query as $qv => $value ) {
				if ( in_array( $qv, $query_vars, true ) ) {
					$taxonomy            = array_search( $qv, $query_vars, true );
					$return[ $taxonomy ] = $value;
				}
			}
			return $return;
		}
		return $query_vars;
	}

	/**
	 * Parses query to be used with SearchPress.
	 *
	 * @param WP_Query $query The query to be parsed.
	 * @access protected
	 * @return array The parsed query to be executed against Elasticsearch.
	 */
	protected function parse_query( $query ) {
		$vars = array();

		// Taxonomy filters.
		$terms = $this->get_valid_taxonomy_query_vars( $query );
		if ( ! empty( $terms ) ) {
			$vars['terms'] = $terms;
		}

		// Post type filters.
		$indexed_post_types = SP_Config()->sync_post_types();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( $query->get( 'post_type' ) && 'any' !== $query->get( 'post_type' ) ) {
			$post_types = (array) $query->get( 'post_type' );
		} elseif ( ! empty( $_GET['post_type'] ) ) {
			$post_types = explode( ',', sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) );
		} else {
			$post_types = false;
		}
		// phpcs:enable

		$vars['post_type'] = array();

		// Validate post types, making sure they exist and are indexed.
		if ( $post_types ) {
			foreach ( (array) $post_types as $post_type ) {
				if ( in_array( $post_type, $indexed_post_types, true ) ) {
					$vars['post_type'][] = $post_type;
				}
			}
		}

		if ( empty( $vars['post_type'] ) ) {
			$vars['post_type'] = sp_searchable_post_types();
		}

		return $vars;
	}
}

/**
 * Returns an initialized instance of the SP_Integration class.
 *
 * @return SP_Integration An initialized instance of the SP_Integration class.
 */
function SP_Integration() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return SP_Integration::instance();
}
add_action( 'after_setup_theme', 'SP_Integration', 30 ); // Must init after SP_Heartbeat.
