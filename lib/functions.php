<?php

/**
 * Pluck a certain field out of an ES response.
 *
 * @param array $results Elasticsearch results.
 * @param int|string $field A field from the retuls to place instead of the entire object.
 * @param bool $as_single Return as single (true) or an array (false). Defaults to true.
 * @return array
 */
function sp_results_pluck( $results, $field, $as_single = true ) {
	$return = array();

	if ( empty( $results['hits']['hits'] ) ) {
		return array();
	}

	foreach ( $results['hits']['hits'] as $key => $value ) {
		if ( ! empty( $value['fields'][ $field ] ) ) {
			$return[ $key ] = (array) $value['fields'][ $field ];
			if ( $as_single ) {
				$return[ $key ] = reset( $return[ $key ] );
			}
		}
	}

	return $return;
}

/**
 * Get a list of all searchable post types.
 *
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
 * @return array Array of post statuses with 'exclude_from_search' => false.
 */
function sp_searchable_post_statuses( $reload = false ) {
	static $post_statuses;
	if ( empty( $post_statuses ) || $reload ) {
		$post_statuses = array_values( get_post_stati( array( 'exclude_from_search' => false ) ) );

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
 * @param  array $es_args PHP array of ES arguments.
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
 * @param  array $wp_args PHP array of search arguments.
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
