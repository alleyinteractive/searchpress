<?php

/**
 * SearchPress configuration
 */

class SP_Config extends SP_Singleton {

	/**
	 * Cached settings from wp_options.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Cached post_statuses to index.
	 *
	 * @var array
	 */
	public $post_statuses;

	/**
	 * Cached post_types to index.
	 *
	 * @var array
	 */
	public $post_types;

	/**
	 * @codeCoverageIgnore
	 */
	public function setup() {
		// initialize anything for the singleton here
	}

	/**
	 * Get an array of post_statuses which should be indexed. Only posts in
	 * these statuses will be indexed with one exception: posts in the `inherit`
	 * post status will use the parent's post status when making that decision
	 * (that said, the indexed status will still be `inherit`).
	 *
	 * @return array Post statuses.
	 */
	public function sync_statuses() {
		if ( ! isset( $this->post_statuses ) ) {
			// Get all post statuses that aren't explicitly flagged as internal.
			$this->post_statuses = array_values( get_post_stati( array( 'internal' => false ) ) );

			/**
			 * Filter the *indexed* (synced) post statuses. Also
			 * {@see sp_searchable_post_statuses()} and the
			 * `sp_searchable_post_statuses` filter to filter the post statuses
			 * that SearchPress searches in Elasticsearch. If you add a post
			 * status to this list and don't want it to be searchable, it either
			 * needs `'exclude_from_search' => true` or it needs to be removed
			 * from the searchable statuses using that filter.
			 *
			 * @param array $post_statuses Valid post statuses to index.
			 */
			$this->post_statuses = apply_filters( 'sp_config_sync_statuses', $this->post_statuses );
		}
		return $this->post_statuses;
	}

	/**
	 * Get an array of post_types which should be indexed. Only posts in
	 * these types will be indexed.
	 *
	 * @return array Post types.
	 */
	public function sync_post_types() {
		if ( ! isset( $this->post_types ) ) {
			$this->post_types = array_values( get_post_types( array( 'show_ui' => true, 'public' => true, 'exclude_from_search' => false ), 'names', 'or' ) );

			/**
			 * Filter the *indexed* (synced) post types. Also
			 * {@see sp_searchable_post_types()} and the
			 * `sp_searchable_post_types` filter to filter the post types
			 * that SearchPress searches in Elasticsearch. If you add a post
			 * type to this list and don't want it to be searchable, it either
			 * needs `'exclude_from_search' => true` or it needs to be removed
			 * from the searchable types using that filter.
			 *
			 * @param array $post_types Valid post types to index.
			 */
			return apply_filters( 'sp_config_sync_post_types', $this->post_types );
		}
		return $this->post_types;
	}


	public function create_mapping() {
		$mapping = array(
			'settings' => array(
				'analysis' => array(
					'analyzer' => array(
						'default' => array(
							'tokenizer' => 'standard',
							'filter' => array( 'standard', 'sp_word_delimiter', 'lowercase', 'stop', 'sp_snowball' ),
							'language' => 'English',
						),
					),
					'filter' => array(
						'sp_word_delimiter' => array( 'type' => 'word_delimiter', 'preserve_original' => true ),
						'sp_snowball' => array( 'type' => 'snowball', 'language' => 'English' ),
					),
				),
			),
			'mappings' => array(
				'post' => array(
					'date_detection' => false,
					'dynamic_templates' => array(
						array(
							'template_meta' => array(
								'path_match' => 'post_meta.*',
								'mapping' => array(
									'type' => 'object',
									'properties' => array(
										'value' => array( 'type' => 'string' ),
										'raw' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
										'long' => array( 'type' => 'long' ),
										'double' => array( 'type' => 'double' ),
										'boolean' => array( 'type' => 'boolean' ),
										'date' => array( 'type' => 'date', 'format' => 'YYYY-MM-dd' ),
										'datetime' => array( 'type' => 'date', 'format' => 'YYYY-MM-dd HH:mm:ss' ),
										'time' => array( 'type' => 'date', 'format' => 'HH:mm:ss' ),
									),
								),
							),
						),
						array(
							'template_terms' => array(
								'path_match' => 'terms.*',
								'mapping' => array(
									'type' => 'object',
									'properties' => array(
										'name' => array(
											'type' => 'string',
											'fields' => array(
												'raw' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
											),
										),
										'term_id' => array( 'type' => 'long' ),
										'parent' => array( 'type' => 'long' ),
										'slug' => array( 'type' => 'string', 'index' => 'not_analyzed' ),
									),
								),
							),
						),
					),
					'_all' => array( 'analyzer' => 'simple' ),
					'properties' => array(
						'post_id'     => array( 'type' => 'long', 'include_in_all' => false ),
						'post_author' => array(
							'type'       => 'object',
							'properties' => array(
								'user_id'       => array( 'type' => 'long', 'include_in_all' => false ),
								'display_name'  => array( 'type' => 'string' ),
								'login'         => array( 'type' => 'string', 'index' => 'not_analyzed' ),
								'user_nicename' => array( 'type' => 'string', 'index' => 'not_analyzed' ),
							),
						),
						'post_date' => array(
							'type' => 'object',
							'include_in_all' => false,
							'properties' => array(
								'date'              => array( 'type' => 'date', 'format' => 'YYYY-MM-dd HH:mm:ss||YYYY-MM-dd' ),
								'year'              => array( 'type' => 'short' ),
								'month'             => array( 'type' => 'byte' ),
								'day'               => array( 'type' => 'byte' ),
								'hour'              => array( 'type' => 'byte' ),
								'minute'            => array( 'type' => 'byte' ),
								'second'            => array( 'type' => 'byte' ),
								'week'              => array( 'type' => 'byte' ),
								'day_of_week'       => array( 'type' => 'byte' ),
								'day_of_year'       => array( 'type' => 'short' ),
								'seconds_from_day'  => array( 'type' => 'integer' ),
								'seconds_from_hour' => array( 'type' => 'short' ),
							),
						),
						'post_date_gmt' => array(
							'type' => 'object',
							'include_in_all' => false,
							'properties' => array(
								'date'              => array( 'type' => 'date', 'format' => 'YYYY-MM-dd HH:mm:ss||YYYY-MM-dd' ),
								'year'              => array( 'type' => 'short' ),
								'month'             => array( 'type' => 'byte' ),
								'day'               => array( 'type' => 'byte' ),
								'hour'              => array( 'type' => 'byte' ),
								'minute'            => array( 'type' => 'byte' ),
								'second'            => array( 'type' => 'byte' ),
								'week'              => array( 'type' => 'byte' ),
								'day_of_week'       => array( 'type' => 'byte' ),
								'day_of_year'       => array( 'type' => 'short' ),
								'seconds_from_day'  => array( 'type' => 'integer' ),
								'seconds_from_hour' => array( 'type' => 'short' ),
							),
						),
						'post_modified' => array(
							'type' => 'object',
							'include_in_all' => false,
							'properties' => array(
								'date'              => array( 'type' => 'date', 'format' => 'YYYY-MM-dd HH:mm:ss||YYYY-MM-dd' ),
								'year'              => array( 'type' => 'short' ),
								'month'             => array( 'type' => 'byte' ),
								'day'               => array( 'type' => 'byte' ),
								'hour'              => array( 'type' => 'byte' ),
								'minute'            => array( 'type' => 'byte' ),
								'second'            => array( 'type' => 'byte' ),
								'week'              => array( 'type' => 'byte' ),
								'day_of_week'       => array( 'type' => 'byte' ),
								'day_of_year'       => array( 'type' => 'short' ),
								'seconds_from_day'  => array( 'type' => 'integer' ),
								'seconds_from_hour' => array( 'type' => 'short' ),
							),
						),
						'post_modified_gmt' => array(
							'type' => 'object',
							'include_in_all' => false,
							'properties' => array(
								'date'              => array( 'type' => 'date', 'format' => 'YYYY-MM-dd HH:mm:ss||YYYY-MM-dd' ),
								'year'              => array( 'type' => 'short' ),
								'month'             => array( 'type' => 'byte' ),
								'day'               => array( 'type' => 'byte' ),
								'hour'              => array( 'type' => 'byte' ),
								'minute'            => array( 'type' => 'byte' ),
								'second'            => array( 'type' => 'byte' ),
								'week'              => array( 'type' => 'byte' ),
								'day_of_week'       => array( 'type' => 'byte' ),
								'day_of_year'       => array( 'type' => 'short' ),
								'seconds_from_day'  => array( 'type' => 'integer' ),
								'seconds_from_hour' => array( 'type' => 'short' ),
							),
						),
						'post_title' => array(
							'type' => 'string',
							'fields' => array(
								'raw' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
							),
						),
						'post_excerpt' => array( 'type' => 'string' ),
						'post_content' => array( 'type' => 'string' ),
						'post_status' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
						'parent_status' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
						'post_name' => array(
							'type' => 'string',
							'fields' => array(
								'raw' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
							),
						),
						'post_parent' => array( 'type' => 'long', 'include_in_all' => false ),
						'post_type' => array(
							'type' => 'string',
							'fields' => array(
								'raw' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
							),
						),
						'post_mime_type' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
						'post_password' => array( 'type' => 'string', 'index' => 'not_analyzed', 'include_in_all' => false ),
						'menu_order' => array( 'type' => 'integer' ),
						'permalink' => array( 'type' => 'string' ),
						'terms' => array( 'type' => 'object' ),
						'post_meta' => array( 'type' => 'object' ),
					),
				),
			),
		);
		$mapping = apply_filters( 'sp_config_mapping', $mapping );
		return SP_API()->put( '', wp_json_encode( $mapping ) );
	}


	public function flush() {
		return SP_API()->delete();
	}


	public function get_settings() {
		$settings = get_option( 'sp_settings' );
		$this->settings = wp_parse_args( $settings, array(
			'host'      => 'http://localhost:9200',
			'must_init' => true,
			'active'    => false,
			'last_beat' => false,
		) );
		return $this->settings;
	}


	public function get_setting( $key ) {
		if ( ! $this->settings ) {
			$this->get_settings();
		}
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null;
	}


	public function __call( $method, $value ) {
		return $this->get_setting( $method );
	}


	public function update_settings( $new_settings = array() ) {
		if ( ! $this->settings ) {
			$this->get_settings();
		}
		$old_settings = $this->settings;
		$this->settings = wp_parse_args( $new_settings, $this->settings );
		update_option( 'sp_settings', $this->settings );

		if ( ! empty( $new_settings['host'] ) ) {
			SP_API()->host = $this->get_setting( 'host' );
		}

		/**
		 * Fires after the settings have been updated.
		 *
		 * @param array $settings The final settings.
		 * @param array $new_settings The settings being updated.
		 * @param array $old_settings The old settings.
		 */
		do_action( 'sp_config_update_settings', $this->settings, $new_settings, $old_settings );
	}
}

function SP_Config() {
	return SP_Config::instance();
}
