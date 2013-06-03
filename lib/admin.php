<?php

add_action( 'admin_menu', 'elasticsearch_sync_menu' );


function elasticsearch_sync_menu() {
	// Add new admin menu and save returned page hook
	$hook_suffix = add_management_page( __('Elasticsearch Sync'), __('Elasticsearch'), 'manage_options', 'elasticsearch_sync', 'elasticsearch_sync' );
}


function elasticsearch_sync() {
	if ( !current_user_can( 'manage_options' ) ) wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	?>
	<div class="wrap">
		<h2>Elasticsearch</h2>

		<code><?php

		ES_Sync_Manager()->sync( 0, 1 );

		?></code>

	</div>
	<?php
}