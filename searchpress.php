<?php
/**
 * Plugin Name: SearchPress
 * Plugin URI: http://searchpress.org/
 * Description: Elasticsearch for WordPress.
 * Version: 0.3
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
require_once SP_PLUGIN_DIR . '/lib/class-sp-singleton.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Base indexable class.
require_once SP_PLUGIN_DIR . '/lib/class-sp-indexable.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Constants, etc.
require_once SP_PLUGIN_DIR . '/lib/globals.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Helper functions.
require_once SP_PLUGIN_DIR . '/lib/functions.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// To communicate with the ES API.
require_once SP_PLUGIN_DIR . '/lib/class-sp-api.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Settings, mappings, etc. for ES.
require_once SP_PLUGIN_DIR . '/lib/class-sp-config.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Heartbeat.
require_once SP_PLUGIN_DIR . '/lib/class-sp-heartbeat.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// An object wrapper that becomes the indexed ES documents.
require_once SP_PLUGIN_DIR . '/lib/class-sp-post.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// A controller for syncing content across to ES.
require_once SP_PLUGIN_DIR . '/lib/class-sp-sync-manager.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Manages all cron processes.
require_once SP_PLUGIN_DIR . '/lib/class-sp-cron.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Manages metadata for the syncing process.
require_once SP_PLUGIN_DIR . '/lib/class-sp-sync-meta.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// You know, for search.
require_once SP_PLUGIN_DIR . '/lib/class-sp-search.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Extends the search with WP-style arguments.
require_once SP_PLUGIN_DIR . '/lib/class-sp-wp-search.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

// Replaces core search with SearchPress.
require_once SP_PLUGIN_DIR . '/lib/class-sp-integration.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

if ( is_admin() ) {
	require_once SP_PLUGIN_DIR . '/lib/class-sp-admin.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include SP_PLUGIN_DIR . '/bin/wp-cli.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile
}

if ( defined( 'SP_DEBUG' ) && SP_DEBUG ) {
	include SP_PLUGIN_DIR . '/lib/class-sp-debug.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile
}

do_action( 'searchpress_loaded' );
