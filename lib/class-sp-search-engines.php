<?php

/**
 * @todo  Add a "default search engine" option; this needs to unset other default search engine
 * @todo  Add base search options
 * @todo  Add facet options
 * @todo  Flush rewrites on activation
 * @todo  Tie options into the actual search query
 */

if ( !class_exists( 'SP_Search_Engines' ) ) :

class SP_Search_Engines {

	private static $instance;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone SP_Search_Engines" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup SP_Search_Engines" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SP_Search_Engines;
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		# Register the post type
		add_action( 'init', array( $this, 'add_post_type' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		add_action( 'save_post', array( $this, 'save' ) );

		add_filter( 'post_type_link', array( $this, 'default_search_url' ), 10, 2 );
	}


	public function add_post_type() {
		register_post_type( 'sp_search_engine', array(
			'public'              => true,
			'exclude_from_search' => true,
			'show_in_nav_menus'   => true,
			'show_ui'             => true,
			'supports'            => array( 'title' ),
			'query_var'           => true,
			'rewrite'             => array( 'slug' => 'searches' ),
			'labels'              => array(
				'name'                => __( 'Search Engines', 'searchpress' ),
				'singular_name'       => __( 'Search Engine', 'searchpress' ),
				'add_new'             => __( 'Add Search Engine', 'searchpress' ),
				'all_items'           => __( 'Search Engines', 'searchpress' ),
				'add_new_item'        => __( 'Add new Search Engine', 'searchpress' ),
				'edit_item'           => __( 'Edit Search Engine', 'searchpress' ),
				'new_item'            => __( 'New Search Engine', 'searchpress' ),
				'view_item'           => __( 'View Search Engine', 'searchpress' ),
				'search_items'        => __( 'Search Search Engines', 'searchpress' ),
				'not_found'           => __( 'No Search Engines found', 'searchpress' ),
				'not_found_in_trash'  => __( 'No Search Engines found in trash', 'searchpress' ),
				'parent_item_colon'   => __( 'Parent Search Engine', 'searchpress' ),
				'menu_name'           => __( 'Search', 'searchpress' ),
			),
		) );
	}

	public function add_meta_boxes() {
		add_meta_box( 'config', __( 'Search Options', 'searchpress' ), array( $this, 'config_box' ), 'sp_search_engine', 'normal' );
		// add_meta_box( 'facets', __( 'Search Facets', 'searchpress' ), array( $this, 'facets_box' ), 'sp_search_engine', 'normal' );
	}

	public function config_box( $post ) {
		wp_nonce_field( 'searchpress_search_engine_meta', 'sp_search_engine_config_nonce' );
		$default = ( $post->ID == SP_Config()->get_setting( 'engine' ) );
		$options = get_post_meta( $post->ID, 'sp_options', true );
		$options = wp_parse_args( $options, array(
			'content_types' => array(),
			'facets' => array()
		) );
		$post_types = get_post_types( array( 'exclude_from_search' => false ), 'objects' );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		?>
		<div class="sp-tabs-wrapper sp-fields-wrapper">
			<ul class="wp-tab-bar sp-tab-bar">
				<li class="wp-tab-active"><a href="#sp_engine_config_tab_basic">Basic Settings</a></li>
				<li class="hide-if-no-js"><a href="#sp_engine_config_tab_types">Content Types</a></li>
				<li class="hide-if-no-js"><a href="#sp_engine_config_tab_facets">Search Facets</a></li>
			</ul>
			<div id="sp_engine_config_tab_basic" class="wp-tabs-panel sp-tabs-panel">
				<div class="sp-field-wrapper sp-checkboxes">
					<p>
						<label class="sp-checkboxes-label"><?php _e( 'Default Search', 'searchpress' ); ?></label>
						<label for="sp_engine_default">
							<input type="checkbox" name="sp[default]" id="sp_engine_default" value="1"<?php checked( $default ) ?> />
							<?php _e( "Use as the site's default search", 'searchpress' ); ?>
						</label>
					</p>
				</div>
			</div>
			<div id="sp_engine_config_tab_types" class="wp-tabs-panel sp-tabs-panel" style="display:none">
				<div class="sp-field-wrapper sp-checkboxes">
					<p>
						<label class="sp-checkboxes-label"><?php _e( 'Content Types', 'searchpress' ); ?></label>
						<?php foreach ( $post_types as $type ) : ?>
							<label>
								<input type="checkbox" name="sp[content_types][]" value="<?php echo esc_attr( $type->name ) ?>"<?php checked( in_array( $type->name, $options['content_types'] ) ) ?> />
								<?php echo esc_html( $type->labels->name ); ?>
							</label>
						<?php endforeach ?>
					</p>
				</div>
			</div>
			<div id="sp_engine_config_tab_facets" class="wp-tabs-panel sp-tabs-panel" style="display:none">
				<script type="text/template" id="sp_facet_tpl">
				<div class="sp-facet">
					<div class="sp-facet-label">
						<a href="#" class="sp-remove" title="Remove"><?php _e( '&times;', 'searchpress' ); ?></a>
						<h4><?php _e( 'Facet', 'searchpress' ); ?></h4>
					</div>
					<div class="sp-facet-inner">
						<p>
							<span class="sp-field-label"><label for="sp_facets_<%= i %>_title"><?php _e( 'Title', 'searchpress' ); ?></label></span>
							<input type="text" name="sp[facets][<%= i %>][title]" id="sp_facets_<%= i %>_title" class="sp-facets-title" />
						</p>
						<p>
							<span class="sp-field-label"><label for="sp_facets_<%= i %>_facet"><?php _e( 'Facet Data', 'searchpress' ); ?></label></span>
							<select name="sp[facets][<%= i %>][facet]" id="sp_facets_<%= i %>_facet" class="sp-facets-facet">
								<option value=""><?php _e( 'Choose One', 'searchpress' ); ?></option>
								<?php foreach ( $taxonomies as $tax ) : ?>
								<option value="<?php echo esc_attr( $tax->name ) ?>"><?php echo esc_html( $tax->labels->name ) ?></option>
								<?php endforeach ?>
								<option value="post_type">Post Types</option>
								<option value="author">Post Authors</option>
								<option value="date">Publication Date</option>
							</select>
						</p>
						<p>
							<span class="sp-field-label"><label for="sp_facets_<%= i %>_logic"><?php _e( 'Facet Logic', 'searchpress' ); ?></label></span>
							<select name="sp[facets][<%= i %>][logic]" id="sp_facets_<%= i %>_logic" class="sp-facets-logic">
								<option value="intersection">Intersection (and)</option>
								<option value="union">Union (or)</option>
							</select>
						</p>
						<p>
							<span class="sp-field-label"><label for="sp_facets_<%= i %>_sort"><?php _e( 'Facet Sorting', 'searchpress' ); ?></label></span>
							<select name="sp[facets][<%= i %>][sort]" id="sp_facets_<%= i %>_sort" class="sp-facets-sort">
								<option value="count">Post Count</option>
								<option value="title">Title</option>
							</select>
						</p>
						<p>
							<label for="sp_facets_<%= i %>_counts">
								<input type="checkbox" name="sp[facets][<%= i %>][counts]" id="sp_facets_<%= i %>_counts" value="1" class="sp-facets-counts" />
								<?php _e( 'Include post counts', 'searchpress' ); ?>
							</label>
						</p>
					</div>
				</div>
				</script>
				<script type="text/javascript">
					var sp_facet_options = <?php echo json_encode( $options['facets'] ) ?>;
				</script>
				<div id="sp_facets_wrap"></div>
				<p><?php submit_button( __( 'Add Search Facet', 'searchpress' ), 'secondary', 'sp_add_facet', false ); ?></p>
			</div>
		</div>
		<?php
	}

	public function facets_box( $post ) {

	}

	/**
	 * Save the meta when the post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save( $post_id ) {

		# Check if our nonce is set.
		if ( ! isset( $_POST['sp_search_engine_config_nonce'] ) )
			return $post_id;

		# Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['sp_search_engine_config_nonce'], 'searchpress_search_engine_meta' ) )
			return $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		# Check the user's permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		# Sanitize the user input.
		if ( isset( $_POST['sp']['default'] ) && '1' == $_POST['sp']['default'] ) {
			SP_Config()->update_settings( array( 'engine' => $post_id ) );
		} elseif ( SP_Config()->get_setting( 'engine' ) == $post_id ) {
			SP_Config()->update_settings( array( 'engine' => null ) );
		}

		$sp = array(
			'content_types' => array(),
			'facets' => array()
		);

		# Add valid content types
		$post_types = get_post_types( array( 'exclude_from_search' => false ) );
		foreach ( $_POST['sp']['content_types'] as $post_type ) {
			if ( in_array( $post_type, $post_types ) )
				$sp['content_types'][] = $post_type;
		}

		# TODO: sanitize this
		$sp['facets'] = $_POST['sp']['facets'];

		# Update the meta field.
		update_post_meta( $post_id, 'sp_options', $sp );
	}

	public function default_search_url( $link, $post ) {
		if ( 'sp_search_engine' == get_post_type( $post ) ) {
			if ( SP_Config()->get_setting( 'engine' ) == $post->ID ) {
				return home_url( '/search/' );
			}
		}
		return 'link';
	}

}

function SP_Search_Engines() {
	return SP_Search_Engines::instance();
}
add_action( 'after_setup_theme', 'SP_Search_Engines' );

endif;