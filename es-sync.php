<?php

/*
	Plugin Name: Elasticsearch Sync
	Plugin URI: http://www.alleyinteractive.com/
	Description: A data synchronization plugin to index WordPress data in elasticsearch
	Version: 0.1
	Author: Matthew Boynes
	Author URI: http://www.alleyinteractive.com/
*/
/*  This program is free software; you can redistribute it and/or modify
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



if ( !defined( 'ES_SYNC_PLUGIN_URL' ) )
	define( 'ES_SYNC_PLUGIN_URL', plugins_url( '/', __FILE__ ) );
if ( !defined( 'ES_SYNC_PLUGIN_DIR' ) )
	define( 'ES_SYNC_PLUGIN_DIR', __DIR__ );

require_once ES_SYNC_PLUGIN_DIR . '/lib/class-es-sync-manager.php';

if ( is_admin() && ( !defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
	require_once ES_SYNC_PLUGIN_DIR . '/lib/admin.php';
}


?>