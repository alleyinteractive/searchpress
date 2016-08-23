<?php

/**
 * SearchPress configuration
 */

class SP_Config {

	private static $instance;

	public $settings;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * @codeCoverageIgnore
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Config;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function setup() {
		// initialize anything for the singleton here
	}


	public function sync_statuses() {
		return apply_filters( 'sp_config_sync_statuses', array( 'publish' ) );
	}


	public function sync_post_types() {
		return apply_filters( 'sp_config_sync_post_types', sp_searchable_post_types() );
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
		return SP_API()->put( '', json_encode( $mapping ) );
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
		$this->settings = wp_parse_args( $new_settings, $this->settings );
		update_option( 'sp_settings', $this->settings );
	}
}

function SP_Config() {
	return SP_Config::instance();
}
