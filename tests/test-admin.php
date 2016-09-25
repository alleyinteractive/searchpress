<?php

/**
 * @group admin
 */
class Tests_Admin extends WP_UnitTestCase {
	protected $current_user;
	protected $sp_settings;
	protected $old_wp_scripts, $old_wp_styles;
	protected $old_screen;

	function setUp() {
		parent::setUp();
		// is_admin returns false, so this file doesn't get loaded with the rest of the plugin
		require_once dirname( __FILE__ ) . '/../lib/class-sp-admin.php';
		$this->current_user = get_current_user_id();
		$this->sp_settings = get_option( 'sp_settings' );

		$this->old_screen = get_current_screen();
		set_current_screen( 'dashboard-user' );

		// Re-init scripts. @see Tests_Dependencies_Scripts.
		$this->old_wp_scripts = isset( $GLOBALS['wp_scripts'] ) ? $GLOBALS['wp_scripts'] : null;
		remove_action( 'wp_default_scripts', 'wp_default_scripts' );
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_scripts']->default_version = get_bloginfo( 'version' );

		// Re-init styles. @see Tests_Dependencies_Styles.
		$this->old_wp_styles = isset( $GLOBALS['wp_styles'] ) ? $GLOBALS['wp_styles'] : null;
		remove_action( 'wp_default_styles', 'wp_default_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		$GLOBALS['wp_styles'] = new WP_Styles();
		$GLOBALS['wp_styles']->default_version = get_bloginfo( 'version' );

		add_filter( 'wp_redirect', array( $this, 'prevent_redirect' ) );
	}

	public function tearDown() {
		wp_set_current_user( $this->current_user );
		SP_Config()->update_settings( $this->sp_settings );
		SP_Sync_Manager()->published_posts = false;
		wp_clear_scheduled_hook( 'sp_reindex' );
		SP_Sync_Meta()->reset( 'save' );

		// Restore current_screen.
		$GLOBALS['current_screen'] = $this->old_screen;

		// Restore scripts. @see Tests_Dependencies_Scripts.
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		add_action( 'wp_default_scripts', 'wp_default_scripts' );

		// Restore styles. @see Tests_Dependencies_Styles.
		$GLOBALS['wp_styles'] = $this->old_wp_styles;
		add_action( 'wp_default_styles', 'wp_default_styles' );
		add_action( 'wp_print_styles', 'print_emoji_styles' );

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

	public function test_admin_notices_no_access() {
		$this->expectOutputString( '' );
		SP_Admin()->admin_notices();
	}

	public function test_admin_notices_no_notices() {
		$this->expectOutputString( '' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Admin()->admin_notices();
	}

	public function test_admin_notices_running() {
		$this->expectOutputRegex( '/SearchPress sync is currently running/' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Sync_Meta()->running = true;
		SP_Admin()->admin_notices();
	}

	public function test_no_admin_notices_on_sp_page() {
		$this->expectOutputString( '' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_GET = array( 'page' => 'searchpress' );
		SP_Config()->update_settings( array( 'must_init' => true ) );
		SP_Admin()->admin_notices();
	}

	public function test_admin_notices_must_init() {
		$this->expectOutputRegex( '/SearchPress needs to be configured and synced before you can use it/' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Config()->update_settings( array( 'must_init' => true ) );
		SP_Admin()->admin_notices();
	}

	public function test_admin_notices_has_errors() {
		$this->expectOutputRegex( '/SearchPress encountered an error/' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Sync_Meta()->log( new WP_Error( 'error', 'Testing error notice' ) );
		SP_Admin()->admin_notices();
	}

	public function test_errors() {
		$this->assertSame( 'SearchPress could not flush the old data', SP_Admin()->get_error( SP_ERROR_FLUSH_FAIL ) );
		$this->assertSame( 'Unknown error', SP_Admin()->get_error( null ) );
	}

	public function test_active_tab_sync() {
		$this->expectOutputRegex( '/<a class="nav-tab" href="#sp-settings">Settings<\/a>.*<a class="nav-tab nav-tab-active" href="#sp-sync">Sync<\/a>/s' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Sync_Meta()->running = true;
		SP_Admin()->setup();
		SP_Admin()->sync();
	}

	public function test_active_tab_log() {
		$this->expectOutputRegex( '/<a class="nav-tab" href="#sp-settings">Settings<\/a>.*<a class="nav-tab" href="#sp-sync">Sync<\/a>.*<a class="nav-tab nav-tab-active" href="#sp-log">Log<\/a>/s' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Sync_Meta()->log( new WP_Error( 'error', 'Testing error notice' ) );
		SP_Admin()->setup();
		SP_Admin()->sync();
	}

	public function log_output_data() {
		return array(
			array( 'error',   'Errors' ),
			array( 'warning', 'Warnings' ),
			array( 'line',    'Messages' ),
			array( 'success', 'Success' ),
			array( 'other', '' ),
		);
	}

	/**
	 * @dataProvider log_output_data
	 */
	public function test_log_output( $type, $heading ) {
		$this->expectOutputRegex( "/<h3>{$heading}<\/h3>.*<ol class=\"{$type}\">/s" );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Sync_Meta()->log( new WP_Error( $type, 'Testing notices' ) );
		SP_Admin()->setup();
		SP_Admin()->sync();
	}

	public function test_sync_status_running() {
		$this->expectOutputString( wp_json_encode( array(
			'processed' => 22,
			'page' => 2,
		) ) );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Sync_Meta()->running = true;
		SP_Sync_Meta()->processed = 22;
		SP_Sync_Meta()->page = 2;
		SP_Admin()->sp_sync_status();
	}

	public function test_sync_status_done() {
		$this->expectOutputString( wp_json_encode( array(
			'processed' => 'complete',
		) ) );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Sync_Meta()->running = false;
		SP_Admin()->sp_sync_status();
	}

	public function test_assets_global() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		SP_Admin()->setup();

		$this->assertSame( 0, did_action( 'admin_enqueue_scripts' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'enqueued' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'to_do' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'done' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'enqueued' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'to_do' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'done' ) );

		do_action( 'admin_enqueue_scripts' );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'enqueued' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'to_do' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'done' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'enqueued' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'to_do' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'done' ) );
	}

	public function test_assets_settings() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$_GET = array(
			'page' => 'searchpress',
		);
		SP_Admin()->setup();

		$this->assertSame( 0, did_action( 'admin_enqueue_scripts' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'enqueued' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'to_do' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'done' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'enqueued' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'to_do' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'done' ) );

		do_action( 'admin_enqueue_scripts' );
		$this->assertNotFalse( wp_scripts()->query( 'searchpress-admin-js' ) );
		$this->assertTrue( wp_scripts()->query( 'searchpress-admin-js', 'enqueued' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'to_do' ) );
		$this->assertFalse( wp_scripts()->query( 'searchpress-admin-js', 'done' ) );
		$this->assertNotFalse( wp_styles()->query( 'searchpress-admin-css' ) );
		$this->assertTrue( wp_styles()->query( 'searchpress-admin-css', 'enqueued' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'to_do' ) );
		$this->assertFalse( wp_styles()->query( 'searchpress-admin-css', 'done' ) );
	}
}
