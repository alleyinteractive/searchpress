<?php

/**
 * @group admin
 */
class Tests_Admin extends WP_UnitTestCase {
	protected $current_user;
	protected $sp_settings;

	function setUp() {
		parent::setUp();
		// is_admin returns false, so this file doesn't get loaded with the rest of the plugin
		require_once dirname( __FILE__ ) . '/../lib/class-sp-admin.php';
		$this->current_user = get_current_user_id();
		$this->sp_settings = get_option( 'sp_settings' );

		add_filter( 'wp_redirect', array( $this, 'prevent_redirect' ) );
	}

	public function tearDown() {
		wp_set_current_user( $this->current_user );
		SP_Config()->update_settings( $this->sp_settings );
		SP_Sync_Manager()->published_posts = false;
		wp_clear_scheduled_hook( 'sp_reindex' );

		SP_Sync_Meta()->reset( 'save' );

		parent::tearDown();
	}

	/**
	 * Prevent the admin methods from redirecting and exiting.
	 *
	 * This leverages `wp_die()`, which is overridden in phpunit to be an
	 * exception with the message of whatever is passed to wp_die().
	 *
	 * @param  string $location URL to which to redirect.
	 */
	public function prevent_redirect( $location ) {
		wp_die( $location );
	}

	/**
	 * Simply ensure that this works.
	 */
	function test_admin() {
		SP_Admin();
	}

	/**
	 * Ensure that admins do have access to the settings screen.
	 */
	function test_settings_page_custom_capability() {
		$this->expectOutputRegex( '/<h2>SearchPress<\/h2>/' );
		add_filter( 'sp_admin_settings_capability', function() { return 'edit_posts'; } );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
		SP_Admin()->setup();
		SP_Admin()->sync();
	}

	/**
	 * Ensure that non-admins don't have access to the settings screen.
	 *
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You do not have sufficient permissions to access this page.
	 */
	function test_settings_page_no_access() {
		// Ensure that editors don't have access to the settings
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
		SP_Admin()->setup();
		SP_Admin()->sync();
	}

	/**
	 * Ensure that admins do have access to the settings screen.
	 */
	function test_settings_page_access() {
		$this->expectOutputRegex( '/<h2>SearchPress<\/h2>/' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Admin()->setup();
		SP_Admin()->sync();
	}

	/**
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You do not have sufficient permissions to access this page.
	 */
	public function test_save_settings_no_access() {
		SP_Admin()->save_settings();
	}

	/**
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You are not authorized to perform that action
	 */
	public function test_save_settings_invalid_nonce() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Admin()->save_settings();
	}

	public function test_save_settings_no_changes() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'sp_settings_nonce' => wp_create_nonce( 'sp_settings' ),
		);

		/**
		 * @see Tests_Admin::prevent_redirect() For how wp_die() is leveraged here.
		 */
		try {
			SP_Admin()->save_settings();
			$this->fail( 'Failed to save settings' );
		} catch ( WPDieException $e ) {
			// Make sure the settings didn't change
			$this->assertSame( $this->sp_settings, get_option( 'sp_settings' ) );
			// Verify the redirect url
			$this->assertSame( admin_url( 'tools.php?page=searchpress&save=1' ), $e->getMessage() );
		}
	}

	public function test_save_settings_update_host() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$host = 'http://' . rand_str() . ':9200';
		$_POST = array(
			'sp_settings_nonce' => wp_create_nonce( 'sp_settings' ),
			'sp_host' => $host,
		);

		/**
		 * @see Tests_Admin::prevent_redirect() For how wp_die() is leveraged here.
		 */
		try {
			SP_Admin()->save_settings();
			$this->fail( 'Failed to save settings' );
		} catch ( WPDieException $e ) {
			// Make sure the settings updated
			$new_settings = get_option( 'sp_settings' );
			$this->assertNotSame( $this->sp_settings, $new_settings );
			$this->assertSame( $host, $new_settings['host'] );
			// Verify the redirect url
			$this->assertSame( admin_url( 'tools.php?page=searchpress&save=1' ), $e->getMessage() );
		}
	}

	public function test_save_settings_full_sync() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'sp_settings_nonce' => wp_create_nonce( 'sp_settings' ),
			'sp_reindex' => '1',
		);

		/**
		 * @see Tests_Admin::prevent_redirect() For how wp_die() is leveraged here.
		 */
		try {
			SP_Admin()->save_settings();
			$this->fail( 'Failed to save settings' );
		} catch ( WPDieException $e ) {
			// Verify the redirect url
			$this->assertSame( admin_url( 'tools.php?page=searchpress' ), $e->getMessage() );
		}
	}

	/**
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You do not have sufficient permissions to access this page.
	 */
	public function test_trigger_full_sync_no_access() {
		SP_Admin()->full_sync();
	}

	/**
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You are not authorized to perform that action
	 */
	public function test_trigger_full_sync_invalid_nonce() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Admin()->full_sync();
	}

	public function test_trigger_full_sync() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'sp_sync_nonce' => wp_create_nonce( 'sp_sync' ),
		);

		/**
		 * @see Tests_Admin::prevent_redirect() For how wp_die() is leveraged here.
		 */
		try {
			SP_Admin()->full_sync();
			$this->fail( 'Failed to trigger full sync' );
		} catch ( WPDieException $e ) {
			// Verify the redirect url
			$this->assertSame( admin_url( 'tools.php?page=searchpress' ), $e->getMessage() );
		}
	}

	/**
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You do not have sufficient permissions to access this page.
	 */
	public function test_trigger_cancel_sync_no_access() {
		SP_Admin()->cancel_sync();
	}

	/**
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You are not authorized to perform that action
	 */
	public function test_trigger_cancel_sync_invalid_nonce() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Admin()->cancel_sync();
	}

	public function test_trigger_cancel_sync() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'sp_sync_nonce' => wp_create_nonce( 'sp_sync' ),
		);

		/**
		 * @see Tests_Admin::prevent_redirect() For how wp_die() is leveraged here.
		 */
		try {
			SP_Admin()->cancel_sync();
			$this->fail( 'Failed to trigger full sync' );
		} catch ( WPDieException $e ) {
			// Verify the redirect url
			$this->assertSame( admin_url( 'tools.php?page=searchpress&cancel=1' ), $e->getMessage() );
		}
	}

	/**
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You do not have sufficient permissions to access this page.
	 */
	public function test_trigger_clear_log_no_access() {
		SP_Admin()->clear_log();
	}

	/**
	 * @expectedException WPDieException
	 * @expectedExceptionMessage You are not authorized to perform that action
	 */
	public function test_trigger_clear_log_invalid_nonce() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Admin()->clear_log();
	}

	public function test_trigger_clear_log() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'sp_sync_nonce' => wp_create_nonce( 'sp_flush_log_nonce' ),
		);

		/**
		 * @see Tests_Admin::prevent_redirect() For how wp_die() is leveraged here.
		 */
		try {
			SP_Admin()->clear_log();
			$this->fail( 'Failed to trigger full sync' );
		} catch ( WPDieException $e ) {
			// Verify the redirect url
			$this->assertSame( admin_url( 'tools.php?page=searchpress&clear_log=1' ), $e->getMessage() );
		}
	}
}
