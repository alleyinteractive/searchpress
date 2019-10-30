<?php
/**
 * SearchPress library: helper functions
 *
 * @package SearchPress
 */

/**
 * Pluck a certain field out of an ES response.
 *
 * @param array      $results Elasticsearch results.
 * @param int|string $field A field from the results to place instead of the
 *                          entire object.
 * @param bool       $as_single Optional. Return as single (true) or an array
 *                              (false). Defaults to true.
 * @return array
 */
function sp_results_pluck( $results, $field, $as_single = true ) {
	$return = array();

	if ( empty( $results['hits']['hits'] ) ) {
		return array();
	}

	$parts = explode( '.', $field );
	foreach ( $results['hits']['hits'] as $key => $value ) {
		if ( empty( $value['_source'] ) ) {
			$return[ $key ] = array();
		} elseif ( 1 === count( $parts ) ) {
			if ( array_key_exists( $field, $value['_source'] ) ) {
				$return[ $key ] = (array) $value['_source'][ $field ];
			}
		} else {
			$return[ $key ] = (array) sp_get_array_value_by_path( $value['_source'], $parts );
		}

		// If the result was empty, remove it.
		if ( array() === $return[ $key ] ) {
			unset( $return[ $key ] );
		} elseif ( $as_single ) {
			$return[ $key ] = reset( $return[ $key ] );
		}
	}

	return $return;
}

/**
 * Recursively get an deep array value by a "path" (array of keys). This helper
 * function helps to collect values from an ES _source response.
 *
 * This function is easier to illustrate than explain. Given an array
 * `[ 'grand' => [ 'parent' => [ 'child' => 1 ] ] ]`, passing the `$path`...
 *
 * `[ 'grand' ]`                    yields `[ 'parent' => [ 'child' => 1 ] ]`
 * `[ 'grand', 'parent' ]`          yields `[ 'child' => 1 ]`
 * `[ 'grand', 'parent', 'child' ]` yields `1`
 *
 * If one of the depths is a numeric array, it will be mapped for the remaining
 * path components. In other words, given the an array
 * `[ 'parent' => [ [ 'child' => 1 ], [ 'child' => 2 ] ] ]`, passing the `$path`
 * `[ 'parent', 'child' ]` yields `[ 1, 2 ]`. This feature does not work with
 * multiple depths of numeric arrays.
 *
 * @param  array $array Multi-dimensional array.
 * @param  array $path Single-dimensional array of array keys.
 * @return mixed
 */
function sp_get_array_value_by_path( $array, $path = array() ) {
	if ( isset( $array[0] ) ) {
		return array_map( 'sp_get_array_value_by_path', $array, array_fill( 0, count( $array ), $path ) );
	} elseif ( ! empty( $path ) ) {
		$part = array_shift( $path );
		if ( array_key_exists( $part, $array ) ) {
			$array = $array[ $part ];
		} else {
			return array();
		}
	}
	return empty( $path ) ? $array : sp_get_array_value_by_path( $array, $path );
}

/**
 * Get a list of all searchable post types.
 *
 * @param  bool $reload Optional. Force reload the post types from the cached
 *                      static variable. This is helpful for automated tests.
 * @return array Array of post types with 'exclude_from_search' => false.
 */
function sp_searchable_post_types( $reload = false ) {
	static $post_types;
	if ( empty( $post_types ) || $reload ) {
		$post_types = array_values( get_post_types( array( 'exclude_from_search' => false ) ) );

		/**
		 * Filter the *searchable* post types. Also {@see SP_Config::sync_post_types()}
		 * and the `sp_config_sync_post_types` filter to filter the post types that
		 * SearchPress indexes in Elasticsearch.
		 *
		 * @param array $post_types Post type slugs.
		 */
		$post_types = apply_filters( 'sp_searchable_post_types', $post_types );
	}
	return $post_types;
}

/**
 * Get a list of all searchable post statuses.
 *
 * @param  bool $reload Optional. Force reload the post statuses from the cached
 *                      static variable. This is helpful for automated tests.
 * @return array Array of post statuses. Defaults to 'public' => true.
 */
function sp_searchable_post_statuses( $reload = false ) {
	static $post_statuses;
	if ( empty( $post_statuses ) || $reload ) {
		// Start with the statuses that SearchPress syncs, since we can't search
		// on anything that isn't in there.
		$post_statuses = SP_Config()->sync_statuses();

		// Collect post statuses we don't want to search and exclude them.
		$exclude       = array_values(
			get_post_stati(
				array(
					'internal'            => true,
					'exclude_from_search' => true,
					'private'             => true,
					'protected'           => true,
				),
				'names',
				'or'
			)
		);
		$post_statuses = array_values( array_diff( $post_statuses, $exclude ) );

		/**
		 * Filter the *searchable* post statuses. Also {@see SP_Config::sync_statuses()}
		 * and the `sp_config_sync_post_statuses` filter to filter the post statuses that
		 * SearchPress indexes in Elasticsearch.
		 *
		 * @param array $post_statuses Post statuses.
		 */
		$post_statuses = apply_filters( 'sp_searchable_post_statuses', $post_statuses );
	}
	return $post_statuses;
}

/**
 * Run a search through SearchPress using Elasticsearch syntax.
 *
 * @see SP_Search::search()
 *
 * @param array $es_args    PHP array of ES arguments.
 * @param bool  $raw_result Whether to return the raw result or a post list.
 * @return array Search results.
 */
function sp_search( $es_args, $raw_result = false ) {
	$s = new SP_Search( $es_args );
	return $raw_result ? $s->get_results() : $s->get_posts();
}

/**
 * Run a search through SearchPress using WP-friendly syntax.
 *
 * @see SP_WP_Search
 *
 * @param array $wp_args    PHP array of search arguments.
 * @param bool  $raw_result Whether to return the raw result or a post list.
 * @return array Search results.
 */
function sp_wp_search( $wp_args, $raw_result = false ) {
	$s = new SP_WP_Search( $wp_args );
	return $raw_result ? $s->get_results() : $s->get_posts();
}

/**
 * To be used with the sp_cluster_health_uri filter, force SP to check the
 * global cluster cluster health instead of the index's health. This is helpful
 * when the index doesn't exist yet.
 *
 * @return string Always returns '/_cluster/health'.
 */
function sp_global_cluster_health() {
	return '/_cluster/health';
}

/**
 * Compare an Elasticsearch version against the one in use. This is a convenient
 * wrapper for `version_compare()`, setting the second argument to the current
 * version of Elasticsearch.
 *
 * For example, to see if the current version of Elasticsearch is 5.x, you would
 * call `sp_es_version_compare( '5.0' )`.
 *
 * @param  string $version Version number.
 * @param  string $compare Optional. Test for a particular relationship. Default
 *                         is `>=`.
 * @return bool|null Null on failure, bool on success.
 */
function sp_es_version_compare( $version, $compare = '>=' ) {
	return version_compare( SP_Config()->get_es_version(), $version, $compare );
}

/**
 * Make a remote request.
 *
 * This is separated out as its own function in order to filter the callable
 * which is used to make the request. This pattern allows you to replace or
 * wrap the request to wp_remote_request() as needed. The filtered callable is
 * immediately invoked.
 *
 * @param string $url  ES endpoint URL.
 * @param array  $args Optional. Request arguments. Default empty array.
 * @return WP_Error|array The response or WP_Error on failure.
 */
function sp_remote_request( $url, $request_params = array() ) {
	/**
	 * Filter the callable used to make API requests to ES.
	 *
	 * @param callable $callable Request callable. Should be compatible with
	 *                           wp_remote_request.
	 * @param string   $url      ES endpoint URL.
	 * @param array    $args     Optional. Request arguments. Default empty
	 *                           array.
	 */
	$callable = apply_filters(
		'sp_remote_request',
		'wp_remote_request',
		$url,
		$request_params
	);

	// Revert back to wp_remote_request if something went awry.
	if ( ! is_callable( $callable ) ) {
		$callable = 'wp_remote_request';
	}

	return call_user_func( $callable, $url, $request_params );
}
