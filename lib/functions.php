<?php
/**
 * Template Tags for SearchPress
 */

/**
 * Output or return a 'Did you mean?' search suggestion. This requires that you add_theme_support( 'search-suggestion' ).
 *
 * @param array $args {
 * 		An array of options.
 *
 * 		@type bool $echo If false, returns the string instead out outputting it. Default is true.
 * 		@type string $template The 'template' with which to output the suggestion. Use %s to indicate where the suggestion goes.
 * }
 * @return string|null If $args['echo'] is set to true, always returns null.
 */
function sp_suggestion( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'echo'     => true,
		'template' => '<div class="search-suggestions">' . __( 'Did you mean: &ldquo;%s&rdquo;?', 'searchpress' ) . '</div>'
	) );

	$suggestion = SP_Search()->get_suggestion();

	if ( ! empty( $suggestion['text'] ) && ! empty( $suggestion['highlighted'] ) ) {
		$html = '<a href="' . add_query_arg( 's', $suggestion['text'] ) . '">' . wp_kses( $suggestion['highlighted'], array( 'em' => array() ) ) . '</a>';
		if ( $args['echo'] ) {
			printf( $args['template'], $html );
		} else {
			return $html;
		}
	}

	return null;
}
