<?php

class SearchPress_UnitTestCase extends WP_UnitTestCase {
	protected $sp_settings;

	public function setUp() {
		parent::setUp();

		sp_index_flush_data();
		SP_Cron()->setup();
		wp_clear_scheduled_hook( 'sp_heartbeat' );
		$this->sp_settings = SP_Config()->get_settings();
	}

	public function tearDown() {
		SP_Config()->update_settings( $this->sp_settings );
		SP_Sync_Meta()->reset( 'save' );
		SP_Sync_Manager()->published_posts = false;
		sp_index_flush_data();

		$this->reset_post_types();
		$this->reset_taxonomies();
		$this->reset_post_statuses();
		SP_Config()->post_types = null;
		SP_Config()->post_statuses = null;
		sp_searchable_post_types( true );
		sp_searchable_post_statuses( true );

		SP_Heartbeat()->record_pulse();
		wp_clear_scheduled_hook( 'sp_reindex' );
		wp_clear_scheduled_hook( 'sp_heartbeat' );

		parent::tearDown();
	}

	function search_and_get_field( $args, $field = 'post_name' ) {
		$args = wp_parse_args( $args, array(
			'fields' => $field
		) );
		$posts = sp_wp_search( $args, true );
		return sp_results_pluck( $posts, $field );
	}
}
