<?php

/**
 *
 */

if ( !class_exists( 'SP_Facet' ) ) :

class SP_Facet {

	public $facets = array();

	public function __construct( $facets ) {
		$this->facets = $facets;
	}

	public function display( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'as' => 'checkboxes', # links, checkboxes
			'wrap' => 'ol', # ol, ul, div, span, null
			'include_all' => true,
		) );

		switch ( $as ) {
			case 'links' :
				$template = '<a href="';
				break;

			default :
				$template = '<input type="checkbox" name="sp[fa][%3$s][]" value="%1$s"%2$s />';
				break;
		}
	}

}

endif;