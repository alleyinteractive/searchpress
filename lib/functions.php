<?php

/**
 * Pluck a certain field out of an ES response.
 *
 * @since 3.1.0
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
 * Get a list of all searchable post types. This is a simple wrapper for core
 * functionality because we end up calling this a lot in this plugin.
 *
 * @return array Array of post types with 'exclude_from_search' => false.
 */
function sp_searchable_post_types() {
	return array_values( get_post_types( array( 'exclude_from_search' => false ) ) );
}