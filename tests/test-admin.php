<?php

/**
 * @group admin
 */
class Tests_Admin extends WP_UnitTestCase {
	function setUp() {
		parent::setUp();
	}

	function test_admin() {
		// is_admin returns false, so this file doesn't get loaded with the rest of the plugin
		require_once dirname( __FILE__ ) . '/../lib/admin.php';
		SP_Admin();
	}

	function test_settings_page() {
		$current_user = get_current_user_id();

		// Ensure that editors don't have access to the settings
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
		try {
			ob_start();
			SP_Admin()->sync();
			ob_end_clean();
		} catch ( WPDieException $e ) {
			ob_end_clean();
			$this->assertEquals( 'You do not have sufficient permissions to access this page.', $e->getMessage() );
		}

		// ... and that admins do
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		SP_Admin()->sync();
		$contents = ob_get_clean();
		// Keep it simple, just make sure there's HTML
		$this->assertContains( '<h2>SearchPress</h2>', $contents );

		wp_set_current_user( $current_user );
	}
}
