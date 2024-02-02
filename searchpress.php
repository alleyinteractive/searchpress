<?php
/**
 * Plugin Name: SearchPress
 * Plugin URI: http://searchpress.org/
 * Description: Elasticsearch for WordPress.
 * Version: 0.4.2
 * Author: Matthew Boynes, Alley Interactive
 * Author URI: http://www.alleyinteractive.com/
 *
 * @package Searchpress
 */

/*
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! defined( 'SP_PLUGIN_URL' ) ) {
	define( 'SP_PLUGIN_URL', plugins_url( '/', __FILE__ ) );
}
if ( ! defined( 'SP_PLUGIN_DIR' ) ) {
	define( 'SP_PLUGIN_DIR', dirname( __FILE__ ) );
}

// Base Singleton class.
require_once SP_PLUGIN_DIR . '/lib/class-sp-singleton.php';

// Base indexable class.
require_once SP_PLUGIN_DIR . '/lib/class-sp-indexable.php';

// Constants, etc.
require_once SP_PLUGIN_DIR . '/lib/globals.php';

// Helper functions.
require_once SP_PLUGIN_DIR . '/lib/functions.php';

// To communicate with the ES API.
require_once SP_PLUGIN_DIR . '/lib/class-sp-api.php';

// Settings, mappings, etc. for ES.
require_once SP_PLUGIN_DIR . '/lib/class-sp-config.php';

// Heartbeat.
require_once SP_PLUGIN_DIR . '/lib/class-sp-heartbeat.php';

// An object wrapper that becomes the indexed ES documents.
require_once SP_PLUGIN_DIR . '/lib/class-sp-post.php';

// A controller for syncing content across to ES.
require_once SP_PLUGIN_DIR . '/lib/class-sp-sync-manager.php';

// Handles compatibility with other plugins.
require_once SP_PLUGIN_DIR . '/lib/class-sp-compat.php';

// Manages all cron processes.
require_once SP_PLUGIN_DIR . '/lib/class-sp-cron.php';

// Manages metadata for the syncing process.
require_once SP_PLUGIN_DIR . '/lib/class-sp-sync-meta.php';

// You know, for search.
require_once SP_PLUGIN_DIR . '/lib/class-sp-search.php';

// Extends the search with WP-style arguments.
require_once SP_PLUGIN_DIR . '/lib/class-sp-wp-search.php';

// Replaces core search with SearchPress.
require_once SP_PLUGIN_DIR . '/lib/class-sp-integration.php';

// Autocomplete search suggestions.
require_once SP_PLUGIN_DIR . '/lib/class-sp-search-suggest.php';

if ( is_admin() ) {
	require_once SP_PLUGIN_DIR . '/lib/class-sp-admin.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include SP_PLUGIN_DIR . '/bin/wp-cli.php';
}

if ( defined( 'SP_DEBUG' ) && SP_DEBUG ) {
	include SP_PLUGIN_DIR . '/lib/class-sp-debug.php';
}

do_action( 'searchpress_loaded' );
