<?php
/**
 * SearchPress library: SP_Compat class
 *
 * @package SearchPress
 */

/**
 * Action hook and filter callbacks for compatibility with other plugins.
 */
class SP_Compat extends SP_Singleton {

	/**
	 * Advanced Post Cache can periodically return the wrong value for
	 * found_posts, which can cause the reindex operation to abort prematurely.
	 * We will unhook it before indexing to prevent this.
	 */
	public function action_sp_pre_index_loop() {
		global $advanced_post_cache_object;

		if ( ! empty( $advanced_post_cache_object ) ) {
			remove_filter(
				'found_posts',
				array( $advanced_post_cache_object, 'found_posts' )
			);
			remove_filter(
				'found_posts_query',
				array( $advanced_post_cache_object, 'found_posts_query' )
			);
		}
	}

	/**
	 * Setup the actions for this singleton.
	 */
	public function setup() {
		add_action( 'sp_pre_index_loop', array( $this, 'action_sp_pre_index_loop' ) );
	}
}

/**
 * Returns an initialized instance of the SP_Compat class.
 *
 * @return SP_Compat An initialized instance of the SP_Compat class.
 */
function SP_Compat() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return SP_Compat::instance();
}

SP_Compat();
