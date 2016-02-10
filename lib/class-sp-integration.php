<?php

/**
 * Replace the WordPress core search with SearchPress
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

if ( !class_exists( 'SP_Integration' ) ) :

class SP_Integration {

	protected $do_found_posts;

	protected $found_posts = 0;

	protected $sp;

	public $search_obj;

	private static $instance;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __clone() { wp_die( "Please don't __clone SP_Integration" ); }

	/**
	 * @codeCoverageIgnore
	 */
	public function __wakeup() { wp_die( "Please don't __wakeup SP_Integration" ); }

	/**
	 * @codeCoverageIgnore
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Integration;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function setup() {
		if ( ! is_admin() && SP_Config()->active() ) {
			$this->init_hooks();
		}
	}

	public function init_hooks() {
		// Checks to see if we need to worry about found_posts
		add_filter( 'post_limits_request', array( $this, 'filter__post_limits_request' ), 999, 2 );

		// Replaces the standard search query with one that fetches the posts based on post IDs supplied by ES
		add_filter( 'posts_request',       array( $this, 'filter__posts_request' ),         5, 2 );

		// Nukes the FOUND_ROWS() database query
		add_filter( 'found_posts_query',   array( $this, 'filter__found_posts_query' ),     5, 2 );

		// Since the FOUND_ROWS() query was nuked, we need to supply the total number of found posts
		add_filter( 'found_posts',         array( $this, 'filter__found_posts' ),           5, 2 );

		// Add our custom query var for advanced searches
		add_filter( 'query_vars',          array( $this, 'query_vars' ) );

		// Force the search template if ?sp[force]=1
		add_action( 'parse_query',         array( $this, 'force_search_template' ), 5 );
	}


	public function remove_hooks() {
		remove_filter( 'post_limits_request', array( $this, 'filter__post_limits_request' ), 999, 2 );
		remove_filter( 'posts_request',       array( $this, 'filter__posts_request' ),         5, 2 );
		remove_filter( 'found_posts_query',   array( $this, 'filter__found_posts_query' ),     5, 2 );
		remove_filter( 'found_posts',         array( $this, 'filter__found_posts' ),           5, 2 );
		remove_filter( 'query_vars',          array( $this, 'query_vars' ) );
		remove_action( 'parse_query',         array( $this, 'force_search_template' ), 5 );
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
		if ( ! $wp_query->is_main_query() ) {
			return;
		}

		// Load our sp query string variable
		$this->sp = get_query_var( 'sp' );

		// If this is a search, but not a keyword search, we have to fake it
		if ( ! $wp_query->is_search() && ! empty( $this->sp ) && '1' == $this->sp['force'] ) {
			// First, we'll set the search string to something phony
			$wp_query->set( 's', '1441f19754335ca4638bfdf1aea00c6d' );
			$wp_query->is_search = true;
			$wp_query->is_home = false;
		}
	}


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


	public function filter__posts_request( $sql, &$query ) {
		global $wpdb;

		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $sql;
		}

		// If we put in a phony search term, remove it now
		if ( '1441f19754335ca4638bfdf1aea00c6d' == $query->get( 's' ) ) {
			$query->set( 's', '' );
		}

		$es_wp_query_args = $this->build_es_request( $query );

		// Convert the WP-style args into ES args
		$this->search_obj = new SP_WP_Search( $es_wp_query_args );
		$results = $this->search_obj->get_results( 'hits' );

		// Total number of results for paging purposes
		$this->found_posts = $this->search_obj->get_results( 'total' );

		if ( empty( $results ) ) {
			return "SELECT * FROM {$wpdb->posts} WHERE 1=0 /* SearchPress search results */";
		}

		// Get the post IDs of the results
		$post_ids = $this->search_obj->pluck_field();
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids );

		// Replace the search SQL with one that fetches the exact posts we want in the order we want
		$post_ids_string = implode( ',', $post_ids );
		return "SELECT * FROM {$wpdb->posts} WHERE {$wpdb->posts}.ID IN( {$post_ids_string} ) ORDER BY FIELD( {$wpdb->posts}.ID, {$post_ids_string} ) /* SearchPress search results */";
	}


	public function filter__found_posts_query( $sql, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $sql;
		}

		return '';
	}


	public function filter__found_posts( $found_posts, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $found_posts;
		}

		return $this->found_posts;
	}


	protected function build_es_request( $query ) {
		$page = ( $query->get( 'paged' ) ) ? absint( $query->get( 'paged' ) ) : 1;

		// Start building the WP-style search query args
		// They'll be translated to ES format args later
		$es_wp_query_args = array(
			'query'          => $query->get( 's' ),
			'posts_per_page' => $query->get( 'posts_per_page' ),
			'paged'          => $page,
		);

		$query_vars = $this->parse_query( $query );

		// Set taxonomy terms
		if ( ! empty( $query_vars['terms'] ) ) {
			$es_wp_query_args['terms'] = $query_vars['terms'];
		}

		// Set post types
		if ( ! empty( $query_vars['post_type'] ) ) {
			$es_wp_query_args['post_type'] = $query_vars['post_type'];
		}

		// Set date range
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

		// Advanced search fields
		if ( ! empty( $this->sp ) ) {
			// Date from and to
			if ( ! empty( $this->sp['f'] ) ) {
				$gte = strtotime( $this->sp['f'] );
				if ( false !== $gte ) {
					$es_wp_query_args['date_range']['gte'] = date( 'Y-m-d 00:00:00', $gte );
				}
			}
			if ( ! empty( $this->sp['t'] ) ) {
				$lte = strtotime( $this->sp['t'] );
				if ( false !== $lte ) {
					$es_wp_query_args['date_range']['lte'] = date( 'Y-m-d 23:59:59', $lte );
				}
			}
		}

		if ( ! empty( $es_wp_query_args['date_range'] ) && empty( $es_wp_query_args['date_range']['field'] ) ) {
			$es_wp_query_args['date_range']['field'] = 'post_date';
		}


		/** Ordering */
		// Set results sorting
		if ( $orderby = $query->get( 'orderby' ) ) {
			if ( in_array( $orderby, array( 'date', 'relevance' ) ) ) {
				$es_wp_query_args['orderby'] = $orderby;
			}
		}

		// Set sort ordering
		if ( $order = strtolower( $query->get( 'order' ) ) ) {
			if ( in_array( $order, array( 'asc', 'desc' ) ) ) {
				$es_wp_query_args['order'] = $order;
			}
		}


		// Facets
		if ( ! empty( $this->facets ) ) {
			$es_wp_query_args['facets'] = $this->facets;
		}

		$es_wp_query_args['fields'] = array( 'post_id' );

		return $es_wp_query_args;
	}


	protected function get_valid_taxonomy_query_vars( $query = false ) {
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


	protected function parse_query( $query ) {
		$vars = array();

		// Taxonomy filters
		$terms = $this->get_valid_taxonomy_query_vars( $query );
		if ( ! empty( $terms ) ) {
			$vars['terms'] = $terms;
		}

		// Post type filters
		$searchable_post_types = sp_searchable_post_types();

		if ( $query->get( 'post_type' ) && 'any' != $query->get( 'post_type' ) ) {
			$post_types = (array) $query->get( 'post_type' );
		} elseif ( ! empty( $_GET['post_type'] ) ) {
			$post_types = explode( ',', $_GET['post_type'] );
		} else {
			$post_types = false;
		}

		$vars['post_type'] = array();

		// Validate post types, making sure they exist and are not excluded from search
		if ( $post_types ) {
			foreach ( (array) $post_types as $post_type ) {
				if ( in_array( $post_type, $searchable_post_types ) ) {
					$vars['post_type'][] = $post_type;
				}
			}
		}

		if ( empty( $vars['post_type'] ) ) {
			$vars['post_type'] = $searchable_post_types;
		}

		return $vars;
	}

}

function SP_Integration() {
	return SP_Integration::instance();
}
add_action( 'after_setup_theme', 'SP_Integration' );

endif;
