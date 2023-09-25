<?php
/**
 * SearchPress library: SP_Config class
 *
 * @package SearchPress
 */

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
	 * REST API namespace.
	 *
	 * @var string
	 */
	public $namespace = 'searchpress/v1';

	/**
	 * Stub for initializing the singleton.
	 *
	 * @codeCoverageIgnore
	 */
	public function setup() {
		// Initialize anything for the singleton here.
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

			// Add 'inherit', which gets special treatment due to attachments.
			$this->post_statuses[] = 'inherit';

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

			// If we haven't hit `wp_loaded` yet, we don't want to cache the post
			// statuses in the property, since not all post statuses may have been
			// registered yet.
			if ( ! did_action( 'wp_loaded' ) ) {
				$uncached_post_statuses = $this->post_statuses;
				$this->post_statuses    = null;
				return $uncached_post_statuses;
			}
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
			$this->post_types = array_values(
				get_post_types(
					array(
						'show_ui'             => true,
						'public'              => true,
						'exclude_from_search' => false,
					),
					'names',
					'or'
				)
			);

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
			$this->post_types = apply_filters( 'sp_config_sync_post_types', $this->post_types );

			// If we haven't hit `wp_loaded` yet, we don't want to cache the post
			// types in the property, since not all post types may have been
			// registered yet.
			if ( ! did_action( 'wp_loaded' ) ) {
				$uncached_post_types = $this->post_types;
				$this->post_types    = null;
				return $uncached_post_types;
			}
		}
		return $this->post_types;
	}

	/**
	 * Set the mappings.
	 *
	 * The map version is stored in sp_settings. When the map is updated, the
	 * version is bumped. When updates are made to the map, the site should be
	 * reindexed.
	 *
	 * @return mixed {@see SP_API::put()}.
	 */
	public function create_mapping() {
		if ( sp_es_version_compare( '5.0' ) ) {
			$analyzed_string_type = 'text';
			$not_analyzed_string  = array( 'type' => 'keyword' );
		} else {
			$analyzed_string_type = 'string';
			$not_analyzed_string  = array(
				'type'  => 'string',
				'index' => 'not_analyzed',
			);
		}
		$mapping = array(
			'settings' => array(
				'analysis' => array(
					'analyzer' => array(
						'default' => array(
							'tokenizer' => 'standard',
							'filter'    => array( 'sp_word_delimiter', 'lowercase', 'stop', 'sp_snowball' ),
							'language'  => 'English',
						),
					),
					'filter'   => array(
						'sp_word_delimiter' => array(
							'type'              => 'word_delimiter',
							'preserve_original' => true,
						),
						'sp_snowball'       => array(
							'type'     => 'snowball',
							'language' => 'English',
						),
					),
				),
			),
			'mappings' => array(
				'date_detection'    => false,
				'dynamic_templates' => array(
					array(
						'template_meta_value' => array(
							'path_match' => 'post_meta.*.value',
							'mapping'    => array(
								'type' => $analyzed_string_type,
							),
						),
					),
					array(
						'template_meta_raw' => array(
							'path_match' => 'post_meta.*.raw',
							'mapping'    => $not_analyzed_string,
						),
					),
					array(
						'template_meta_long' => array(
							'path_match' => 'post_meta.*.long',
							'mapping'    => array(
								'type' => 'long',
							),
						),
					),
					array(
						'template_meta_double' => array(
							'path_match' => 'post_meta.*.double',
							'mapping'    => array(
								'type' => 'double',
							),
						),
					),
					array(
						'template_meta_boolean' => array(
							'path_match' => 'post_meta.*.boolean',
							'mapping'    => array(
								'type' => 'boolean',
							),
						),
					),
					array(
						'template_meta_date' => array(
							'path_match' => 'post_meta.*.date',
							'mapping'    => array(
								'type' => 'date',
								'format' => 'yyyy-MM-dd',
							),
						),
					),
					array(
						'template_meta_datetime' => array(
							'path_match' => 'post_meta.*.datetime',
							'mapping'    => array(
								'type' => 'date',
								'format' => 'yyyy-MM-dd HH:mm:ss',
							),
						),
					),
					array(
						'template_meta_time' => array(
							'path_match' => 'post_meta.*.time',
							'mapping'    => array(
								'type' => 'date',
								'format' => 'HH:mm:ss',
							),
						),
					),
					array(
						'template_terms' => array(
							'path_match' => 'terms.*',
							'mapping'    => array(
								'type'       => 'object',
								'properties' => array(
									'name'    => array(
										'type'   => $analyzed_string_type,
										'fields' => array(
											'raw' => $not_analyzed_string,
										),
									),
									'term_id' => array( 'type' => 'long' ),
									'parent'  => array( 'type' => 'long' ),
									'slug'    => $not_analyzed_string,
								),
							),
						),
					),
				),
				'properties'        => array(
					'post_id'           => array( 'type' => 'long' ),
					'post_author'       => array(
						'type'       => 'object',
						'properties' => array(
							'user_id'       => array( 'type' => 'long' ),
							'display_name'  => array( 'type' => $analyzed_string_type ),
							'login'         => $not_analyzed_string,
							'user_nicename' => $not_analyzed_string,
						),
					),
					'post_date'         => array(
						'type'       => 'object',
						'properties' => array(
							'date'              => array(
								'type'   => 'date',
								'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd',
							),
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
					'post_date_gmt'     => array(
						'type'       => 'object',
						'properties' => array(
							'date'              => array(
								'type'   => 'date',
								'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd',
							),
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
					'post_modified'     => array(
						'type'       => 'object',
						'properties' => array(
							'date'              => array(
								'type'   => 'date',
								'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd',
							),
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
						'type'       => 'object',
						'properties' => array(
							'date'              => array(
								'type'   => 'date',
								'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd',
							),
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
					'post_title'        => array(
						'type'   => $analyzed_string_type,
						'fields' => array(
							'raw' => $not_analyzed_string,
						),
					),
					'post_excerpt'      => array( 'type' => $analyzed_string_type ),
					'post_content'      => array( 'type' => $analyzed_string_type ),
					'post_status'       => $not_analyzed_string,
					'parent_status'     => $not_analyzed_string,
					'post_name'         => array(
						'type'   => $analyzed_string_type,
						'fields' => array(
							'raw' => $not_analyzed_string,
						),
					),
					'post_parent'       => array( 'type' => 'long' ),
					'post_type'         => array(
						'type'   => $analyzed_string_type,
						'fields' => array(
							'raw' => $not_analyzed_string,
						),
					),
					'post_mime_type'    => $not_analyzed_string,
					'post_password'     => $not_analyzed_string,
					'menu_order'        => array( 'type' => 'integer' ),
					'permalink'         => array( 'type' => $analyzed_string_type ),
				),
			),
		);

		/*
		 * ES < 7.0 require mapping types.
		 * See https://www.elastic.co/guide/en/elasticsearch/reference/7.0/removal-of-types.html.
		 */
		if ( sp_es_version_compare( '7.0', '<' ) ) {
			$post_mapping        = $mapping['mappings'];
			$mapping['mappings'] = array(
				SP_API()->get_doc_type() => $post_mapping,
			);
		}

		/**
		 * Filter the mappings. Plugins and themes can customize the mappings
		 * however they need by manipulating this array.
		 *
		 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping.html
		 * @param array $mapping SearchPress' mapping array.
		 */
		$mapping = apply_filters( 'sp_config_mapping', $mapping );

		/**
		 * Filter the map version. Plugins and themes can tweak this value if
		 * they update the mapping, and SearchPress will flag that the site
		 * needs to be reindexed.
		 *
		 * @param  int $map_version Map version. Should be of the format
		 *                          `{year}{month}{day}{version}` where version
		 *                          is a two-digit sequential number.
		 */
		$this->update_settings(
			array(
				'map_version' => apply_filters( 'sp_map_version', SP_MAP_VERSION ),
			)
		);

		return SP_API()->put( '', wp_json_encode( $mapping ) );
	}

	/**
	 * Deletes all data on the remote using the API.
	 *
	 * @return object The response from the API.
	 */
	public function flush() {
		return SP_API()->delete();
	}

	/**
	 * Gets saved settings merged with defaults.
	 *
	 * @return array The SearchPress settings.
	 */
	public function get_settings() {
		$settings       = get_option( 'sp_settings' );
		$this->settings = wp_parse_args(
			$settings,
			array(
				'host'        => 'http://localhost:9200',
				'must_init'   => true,
				'active'      => false,
				'map_version' => 0,
				'es_version'  => -1,
			)
		);
		return $this->settings;
	}

	/**
	 * Gets a setting for a given key.
	 *
	 * @param string $key The key to look up.
	 * @return mixed The value of the setting, or null if not found.
	 */
	public function get_setting( $key ) {
		if ( ! $this->settings ) {
			$this->get_settings();
		}
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null;
	}

	/**
	 * Magic method for getting settings using function call syntax.
	 *
	 * @param string $method The method that was called. Used as setting key.
	 * @param mixed  $value  Not used.
	 * @return mixed The value for the setting.
	 */
	public function __call( $method, $value ) {
		return $this->get_setting( $method );
	}

	/**
	 * Updates saved settings with new values.
	 *
	 * @param array $new_settings The new settings to save.
	 */
	public function update_settings( $new_settings = array() ) {
		if ( ! $this->settings ) {
			$this->get_settings();
		}
		$old_settings   = $this->settings;
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

	/**
	 * Update the stored version of Elasticsearch if it's changed.
	 */
	public function update_version() {
		$version = SP_API()->version();
		if ( $version && $this->get_setting( 'es_version' ) !== $version ) {
			$this->update_settings(
				array(
					'es_version' => $version,
				)
			);
		}
	}

	/**
	 * Get the ES version, either from cache or directly from ES.
	 *
	 * @return string|bool Version string on success, false on failure.
	 */
	public function get_es_version() {
		$version = $this->get_setting( 'es_version' );
		return -1 !== $version ? $version : SP_API()->version();
	}
}

/**
 * Returns an initialized instance of the SP_Config class.
 *
 * @return SP_Config An initialized instance of the SP_Config class.
 */
function SP_Config() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return SP_Config::instance();
}
