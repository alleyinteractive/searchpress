<?php

/**
 *
 */

if ( !class_exists( 'SP_Admin' ) ) :

class SP_Admin {

	private static $instance;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __clone() { wp_die( "Please don't __clone SP_Admin" ); }

	/**
	 * @codeCoverageIgnore
	 */
	public function __wakeup() { wp_die( "Please don't __wakeup SP_Admin" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Admin;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		add_action( 'admin_menu',                                     array( $this, 'admin_menu' )     );
		add_action( 'admin_print_styles-tools_page_searchpress_sync', array( $this, 'admin_styles' )   );
		add_action( 'admin_post_sp_full_sync',                        array( $this, 'full_sync' )      );
		add_action( 'admin_post_sp_cancel_sync',                      array( $this, 'cancel_sync' )    );
		add_action( 'admin_post_sp_settings',                         array( $this, 'save_settings' )  );
		add_action( 'wp_ajax_sp_sync_status',                         array( $this, 'sp_sync_status' ) );
		add_action( 'admin_notices',                                  array( $this, 'admin_notices' )  );
	}


	public function admin_menu() {
		// Add new admin menu and save returned page hook
		$hook_suffix = add_management_page( __( 'SearchPress', 'searchpress' ), __( 'SearchPress', 'searchpress' ), 'manage_options', 'searchpress_sync', array( $this, 'sync' ) );
	}


	public function sync() {
		if ( !current_user_can( 'manage_options' ) ) wp_die( __( 'You do not have sufficient permissions to access this page.', 'searchpress' ) );
		$sync = SP_Sync_Meta();
		?>
		<div class="wrap">
			<h2>SearchPress</h2>

				<?php if ( isset( $_GET['error'] ) ) : ?>
					<div class="error updated"><p><?php printf( __( 'An error has occurred: %s', 'searchpress' ), esc_html( $this->get_error( $_GET['error'] ) ) ) ?></p></div>
				<?php endif ?>

				<?php if ( isset( $_GET['complete'] ) ) : ?>
					<div class="updated success"><p><?php _e( 'Sync complete!', 'searchpress' ); ?></p></div>
				<?php endif ?>

				<h3>Settings</h3>
				<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
					<input type="hidden" name="action" value="sp_settings" />
					<?php wp_nonce_field( 'sp_settings', 'sp_settings_nonce' ); ?>
					<p>
						<input type="text" name="sp_host" value="<?php echo esc_url( SP_Config()->get_setting( 'host' ) ) ?>" style="width:100%;max-width:500px" />
					</p>
					<p>
						<label for="sp_reindex"><input type="checkbox" name="sp_reindex" id="sp_reindex" value="1" /> <?php _e( 'Immediately initiate a full sync', 'searchpress' ); ?>
					</p>
					<?php submit_button( 'Save Settings', 'primary' ) ?>
				</form>

				<hr />

			<?php if ( $sync->running ) : ?>

				<h3><?php _e( 'Sync in progress', 'searchpress' ); ?></h3>
				<p><?php _e( 'You do not need to stay on this page while the sync runs.', 'searchpress' ); ?></p>
				<div class="progress">
					<div class="progress-text"><span id="sync-processed"><?php echo number_format( intval( $sync->processed ) ) ?></span> / <span id="sync-total"><?php echo number_format( intval( $sync->total ) ) ?></span></div>
					<div class="progress-bar" data-processed="<?php echo intval( $sync->processed ) ?>" data-total="<?php echo intval( $sync->total ) ?>" style="width:<?php echo intval( round( 100 * $sync->processed / $sync->total ) ) ?>%;"></div>
				</div>
				<script type="text/javascript">
					var progress_total, progress_processed;
					var sp_url = '<?php echo admin_url( 'tools.php?page=searchpress_sync&complete=1' ) ?>';
					jQuery( function( $ ) {
						progress_total = $( '.progress-bar' ).data( 'total' ) - 0;;
						progress_processed = $( '.progress-bar' ).data( 'processed' ) - 0;
						setInterval( function() {
							jQuery.get( ajaxurl, { action : 'sp_sync_status', t : new Date().getTime() }, function( data ) {
								if ( data.processed ) {
									if ( data.processed == 'complete' ) {
										jQuery( '#sync-processed' ).text( progress_total );
										jQuery( '.progress-bar' ).animate( { width: '100%' }, 1000, 'swing', function() { document.location = sp_url; } );
									} else if ( data.processed > progress_processed ) {
										progress_processed = data.processed;
										jQuery( '#sync-processed' ).text( data.processed );
										var new_width = Math.round( data.processed / progress_total * 100 );
										jQuery( '.progress-bar' ).animate( { width: new_width + '%' }, 1000 );
									}
								}
							}, 'json' );
						}, 5000 );
					} );
				</script>
				<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
					<input type="hidden" name="action" value="sp_cancel_sync" />
					<?php wp_nonce_field( 'sp_sync', 'sp_sync_nonce' ); ?>
					<?php submit_button( 'Cancel Sync', 'delete' ) ?>
				</form>

			<?php else : ?>

				<h3><?php _e( 'Full Sync', 'searchpress' ); ?></h3>
				<p><?php _e( 'Running a full sync will wipe the current index if there is one and rebuild it from scratch.', 'searchpress' ); ?></p>
				<p>
					<?php printf( _n( 'Your site has %s post to index.', 'Your site has %s posts to index.', intval( SP_Sync_Manager()->count_posts() ), 'searchpress' ), number_format( intval( SP_Sync_Manager()->count_posts() ) ) ) ?>
					<?php if ( SP_Sync_Manager()->count_posts() > 25000 ) : ?>
						<?php _e( 'As a result of there being so many posts, this may take a long time to index.', 'searchpress' ); ?>
					<?php endif ?>
					<?php _e( "Exactly how long this will take will vary on a number of factors, like your server's CPU and memory, connection speed, current traffic, average post length, and associated terms and post meta.", 'searchpress' ); ?>
				</p>
				<p><?php _e( 'Your site will not use SearchPress until the indexing is complete.', 'searchpress' ); ?></p>

				<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
					<input type="hidden" name="action" value="sp_full_sync" />
					<?php wp_nonce_field( 'sp_sync', 'sp_sync_nonce' ); ?>
					<?php submit_button( 'Run Full Sync', 'delete' ) ?>
				</form>

			<?php endif ?>
		</div>
		<?php
	}

	public function save_settings() {
		if ( !isset( $_POST['sp_settings_nonce'] ) || ! wp_verify_nonce( $_POST['sp_settings_nonce'], 'sp_settings' ) ) {
			wp_die( 'You are not authorized to perform that action' );
		} else {
			if ( isset( $_POST['sp_host'] ) ) {
				SP_Config()->update_settings( array( 'host' => esc_url( $_POST['sp_host'] ) ) );
			}
			if ( isset( $_POST['sp_reindex'] ) && '1' == $_POST['sp_reindex'] ) {
				# The full sync process checks the nonce, so we have to insert it into the postdata
				$_POST['sp_sync_nonce'] = wp_create_nonce( 'sp_sync' );
				$this->full_sync();
			} else {
				wp_redirect( admin_url( 'tools.php?page=searchpress_sync&save=1' ) );
			}
		}

		exit;
	}

	public function full_sync() {
		if ( !isset( $_POST['sp_sync_nonce'] ) || ! wp_verify_nonce( $_POST['sp_sync_nonce'], 'sp_sync' ) ) {
			wp_die( 'You are not authorized to perform that action' );
		} else {
			SP_Config()->update_settings( array( 'must_init' => false, 'active' => false, 'last_beat' => false ) );
			$result = SP_Config()->flush();
			if ( ! isset( SP_API()->last_request['response_code'] ) || ! in_array( SP_API()->last_request['response_code'], array( 200, 404 ) ) ) {
				wp_redirect( admin_url( 'tools.php?page=searchpress_sync&error=100' ) );
			} else {
				SP_Config()->create_mapping();
				SP_Sync_Manager()->do_cron_reindex();
				wp_redirect( admin_url( 'tools.php?page=searchpress_sync' ) );
			}
			exit;
		}
	}

	public function cancel_sync() {
		if ( !isset( $_POST['sp_sync_nonce'] ) || ! wp_verify_nonce( $_POST['sp_sync_nonce'], 'sp_sync' ) ) {
			wp_die( __( 'You are not authorized to perform that action', 'searchpress' ) );
		} else {
			SP_Sync_Manager()->cancel_reindex();
			wp_redirect( admin_url( 'tools.php?page=searchpress_sync&cancel=1' ) );
			exit;
		}
	}

	public function sp_sync_status() {
		if ( SP_Sync_Meta()->running ) {
			echo json_encode( array(
				'processed' => SP_Sync_Meta()->processed,
				'page' => SP_Sync_Meta()->page
			) );
		} else {
			SP_Config()->update_settings( array( 'active' => true ) );

			echo json_encode( array(
				'processed' => 'complete'
			) );
		}
		exit;
	}

	public function admin_styles() {
		?>
		<style type="text/css">
			div.progress {
				position: relative;
				height: 50px;
				border: 2px solid #111;
				background: #333;
				margin: 9px 0 18px;
			}
			div.progress-bar {
				background: #0074a2;
				position: absolute;
				left: 0;
				top: 0;
				height: 50px;
				z-index: 1;
			}
			div.progress-text {
				color: white;
				text-shadow: 1px 1px 0 #333;
				line-height: 50px;
				text-align: center;
				position: absolute;
				width: 100%;
				z-index: 2;
			}
		</style>
		<?php
	}

	public function get_error( $code ) {
		switch ( $code ) {
			case SP_ERROR_FLUSH_FAIL : return __( 'SearchPress could not flush the old data', 'searchpress' );
		}
		return __( 'Unknown error', 'searchpress' );
	}

	public function admin_notices() {
		if ( isset( $_GET['page'] ) && 'searchpress_sync' == $_GET['page'] ) {
			return;
		} elseif ( SP_Sync_Meta()->running ) {
			printf(
				'<div class="updated"><p>%s <a href="%s">%s</a></p></div>',
				__( 'SearchPress sync is currently running.', 'searchpress' ),
				admin_url( 'tools.php?page=searchpress_sync' ),
				__( 'View status', 'searchpress' )
			);
		} elseif ( SP_Config()->must_init() ) {
			printf(
				'<div class="updated error"><p>%s <a href="%s">%s</a></p></div>',
				__( 'SearchPress needs to be configured and synced before you can use it.', 'searchpress' ),
				admin_url( 'tools.php?page=searchpress_sync' ),
				__( 'Go to SearchPress Settings', 'searchpress' )
			);
		}
	}
}

function SP_Admin() {
	return SP_Admin::instance();
}
add_action( 'after_setup_theme', 'SP_Admin' );

endif;
