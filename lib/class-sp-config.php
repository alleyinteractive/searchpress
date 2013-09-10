<?php

/**
 * SearchPress configuration
 */

if ( !class_exists( 'SP_Config' ) ) :

class SP_Config {

	private static $instance;

	public $settings;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone SP_Config" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup SP_Config" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Config;
			self::$instance->setup();
		}
		return self::$instance;
	}


	public function setup() {
		# initialize anything for the singleton here
	}


	public function sync_statuses() {
		return apply_filters( 'sp_config_sync_statuses', array( 'publish' ) );
	}


	public function sync_post_types() {
		return apply_filters( 'sp_config_sync_post_types', get_post_types( array( 'exclude_from_search' => false ) ) );
	}


	public function create_mapping() {
		$mapping = array(
			# 'settings' => array(
			# 	'index' => array(
			# 		'number_of_replicas' => 1,
			# 		'number_of_shards' => 1
			# 	),
			# 	'analysis' => array(
			# 		'analyzer' => array(
			# 			'default' => array(
			# 				'type'  => 'snowball',
			# 				'language'  => 'English'
			# 			),
			# 			'autocomplete' => array(
			# 				'tokenizer' => 'lowercase',
			# 				'filter' => array( 'edge_ngram' )
			# 			)
			# 		),
			# 		'filter' => array(
			# 			'edge_ngram' => array(
			# 				'side' => 'front',
			# 				'max_gram' => 10,
			# 				'min_gram' => 3,
			# 				'type' => 'edgeNGram'
			# 			)
			# 		)
			# 	)
			# ),
			# 'index' => array( 'query' => 'default_field' )
			'mappings' => array(
				'post' => array(
					"date_detection" => false,
					"dynamic_templates" => array(
						array(
							"template_meta" => array(
								"path_match" => "post_meta.*",
								"mapping" => array(
									"type" => "multi_field",
									"path" => "full",
									"fields" => array(
										"{name}" => array(
											"type" => "string",
											"index" => "analyzed"
										),
										"raw" => array(
											"type" => "string",
											"index" => "not_analyzed",
											'include_in_all' => false
										)
									)
								)
							)
						),
						array(
							"template_terms" => array(
								"path_match" => "terms.*",
								"mapping" => array(
									"type" => "object",
									"path" => "full",
									"properties" => array(
										"name" => array(
											"type" => "string"
										),
										"term_id" => array(
											"type" => "long"
										),
										"parent" => array(
											"type" => "long"
										),
										"slug" => array(
											"type" => "string",
											"index" => "not_analyzed"
										)
									)
								)
							)
						)
					),
					'properties' => array(
						'post_id' => array(
							'type' => 'long',
							'index' => 'not_analyzed',
							'include_in_all' => false
						),
						'post_author' => array(
							'type' => 'object',
							'path' => 'full',
							'properties' => array(
								'display_name' => array(
									'type' => 'string'
								),
								'login' => array(
									'type' => 'string',
									'index' => 'not_analyzed'
								)
							)
						),
						'post_author_name' => array(
							'type' => 'string'
						),
						'post_date' => array(
							'type' => 'date',
							'format' => 'YYYY-MM-dd HH:mm:ss',
							'include_in_all' => false
						),
						'post_date_gmt' => array(
							'type' => 'date',
							'format' => 'YYYY-MM-dd HH:mm:ss',
							'include_in_all' => false
						),
						'post_title' => array(
							'type' => 'string',
							'_boost'  => 3.0,
							'store'  => 'yes'
						),
						'post_excerpt' => array(
							'type' => 'string',
							'_boost'  => 2.0
						),
						'post_content' => array(
							'type' => 'string',
							'_boost'  => 1.0
						),
						'post_status' => array(
							'type' => 'string',
							'index' => 'no'
						),
						'post_name' => array(
							'type' => 'multi_field',
							'fields' => array(
								'post_name' => array(
									'type' => 'string'
								),
								'raw' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
									'include_in_all' => false
								)
							)
						),
						'post_modified' => array(
							'type' => 'date',
							'format' => 'YYYY-MM-dd HH:mm:ss',
							'include_in_all' => false
						),
						'post_modified_gmt' => array(
							'type' => 'date',
							'format' => 'YYYY-MM-dd HH:mm:ss',
							'include_in_all' => false
						),
						'post_parent' => array(
							'type' => 'long',
							'index' => 'not_analyzed',
							'include_in_all' => false
						),
						'post_type' => array(
							'type' => 'multi_field',
							'fields' => array(
								'post_type' => array(
									'type' => 'string'
								),
								'raw' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
									'include_in_all' => false
								)
							)
						),
						'post_mime_type' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
							'include_in_all' => false
						),
						'permalink' => array(
							'type' => 'string'
						),
						'terms' => array(
							"type" => "object"
						),
						'post_meta' => array(
							'type' => 'object'
						)
					)
				)
			)
		);
		$mapping = apply_filters( 'sp_config_mapping', $mapping );
		return SP_API()->put( '', json_encode( $mapping ) );
	}


	public function flush() {
		return SP_API()->delete();
	}


	/**
	 * Should we attempt to unserialize post meta?
	 *
	 * @return bool
	 * @todo implement this as a site option
	 */
	public function unserialize_meta() {
		return apply_filters( 'sp_config_unserialize_meta', false );
	}


	public function get_settings() {
		$settings = get_option( 'sp_settings' );
		$this->settings = wp_parse_args( $settings, array(
			'host'      => 'http://localhost:9200',
			'must_init' => true,
			'active'    => false,
			'last_beat' => false
		) );
		return $this->settings;
	}


	public function get_setting( $key ) {
		if ( ! $this->settings )
			$this->get_settings();
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : null;
	}


	public function __call( $method, $value ) {
		return $this->get_setting( $method );
	}


	public function update_settings( $new_settings = array() ) {
		if ( ! $this->settings )
			$this->get_settings();
		$this->settings = wp_parse_args( $new_settings, $this->settings );
		update_option( 'sp_settings', $this->settings )	;
	}

}

function SP_Config() {
	return SP_Config::instance();
}

endif;