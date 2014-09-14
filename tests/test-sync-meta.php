<?php

/**
 * @group sync_meta
 */
class Tests_Sync_Meta extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		delete_option( 'sp_sync_meta' );
	}

	function test_sync_meta_start() {
		$this->assertFalse( SP_Sync_Meta()->running );
		SP_Sync_Meta()->start();
		$this->assertTrue( SP_Sync_Meta()->running );
		$this->assertGreaterThan( 0, SP_Sync_Meta()->started );
	}

	function test_sync_meta_reset() {
		SP_Sync_Meta()->start();
		$this->assertTrue( SP_Sync_Meta()->running );

		SP_Sync_Meta()->reset();
		$this->assertFalse( SP_Sync_Meta()->running );
	}

	function test_sync_meta_storage() {
		$meta = get_option( 'sp_sync_meta', null );
		$this->assertNull( $meta );

		SP_Sync_Meta()->start( 'save' );
		$meta = get_option( 'sp_sync_meta', null );
		$this->assertTrue( $meta['running'] );
	}

	function test_sync_meta_reset_save() {
		SP_Sync_Meta()->start( 'save' );
		$meta = get_option( 'sp_sync_meta', null );
		$this->assertTrue( $meta['running'] );

		SP_Sync_Meta()->reset( 'save' );
		$meta = get_option( 'sp_sync_meta', null );
		$this->assertFalse( $meta['running'] );
	}

	function test_sync_meta_deleting() {
		SP_Sync_Meta()->start( 'save' );
		$meta = get_option( 'sp_sync_meta', null );
		$this->assertTrue( SP_Sync_Meta()->running );
		$this->assertTrue( $meta['running'] );

		SP_Sync_Meta()->delete();
		$this->assertFalse( SP_Sync_Meta()->running );
		$meta = get_option( 'sp_sync_meta', null );
		$this->assertNull( $meta );
	}

	function test_sync_meta_magic_set() {
		SP_Sync_Meta()->reset();
		$this->assertFalse( SP_Sync_Meta()->running );
		SP_Sync_Meta()->running = true;
		$this->assertTrue( SP_Sync_Meta()->running );
	}

	function test_sync_meta_magic_isset() {
		SP_Sync_Meta()->start( 'save' );
		$meta = get_option( 'sp_sync_meta', null );
		$this->assertEmpty( $meta['messages'] );
		$this->assertTrue( isset( $meta['messages'] ) );
		$this->assertEmpty( SP_Sync_Meta()->messages );
		$this->assertTrue( isset( SP_Sync_Meta()->messages ) );
	}

	function test_sync_meta_invalid_property() {
		SP_Sync_Meta()->reset();

		// Getting
		$this->assertFalse( SP_Sync_Meta()->running );
		$this->assertInstanceOf( 'WP_Error', SP_Sync_Meta()->foo );

		// Setting
		SP_Sync_Meta()->running = true;
		$this->assertTrue( SP_Sync_Meta()->running );
		SP_Sync_Meta()->foo = 'bar';
		$this->assertInstanceOf( 'WP_Error', SP_Sync_Meta()->foo );
	}
}