jQuery( function($) {

	if ( $('#sp_facet_tpl').length ) {
		var facet_tpl = _.template( $('#sp_facet_tpl').html() );
		var facet_count = 0;
	}

	$('.sp-tab-bar a').click( function( e ) {
		e.preventDefault();
		var t = $(this).attr( 'href' );
		$(this).parent().addClass( 'wp-tab-active' ).siblings( 'li' ).removeClass( 'wp-tab-active' );
		$(t).siblings( 'div' ).hide();
		$(t).show();
	} );

	$('#sp_add_facet').click( function( e ) {
		e.preventDefault();
		var facet = facet_tpl( { i: facet_count++ } );
		$('#sp_facets_wrap').append( $(facet).hide().fadeIn() );
	} );

	if ( 'undefined' != typeof sp_facet_options && sp_facet_options.length ) {
		for ( var i = 0; i < sp_facet_options.length; i++ ) {
			var f = sp_facet_options[i];
			var $facet = $( facet_tpl( { i: facet_count++ } ) );

			$facet.find('.sp-facets-title').val( f.title );
			$facet.find('.sp-facets-facet').val( f.facet );
			$facet.find('.sp-facets-logic').val( f.logic );
			$facet.find('.sp-facets-sort').val( f.sort );
			if ( '1' == f.counts )
				$facet.find('.sp-facets-counts').prop( 'checked', true );

			$('#sp_facets_wrap').append( $facet );
		}
	}

	$('#sp_facets_wrap').on( 'click', '.sp-remove', function(e) {
		e.preventDefault();
		$(this).closest('.sp-facet').slideUp('normal', function(){
			$(this).remove();
		});
	});

	$('#sp_facets_wrap').sortable( {
		handle: '.sp-facet-label',
		items: '> .sp-facet',
		axis: 'y',
		stop: function( e, ui ) {
			$('.sp-facet').each(function(i){
				$(this).find('input,select').each(function(){
					$(this).attr( 'id', $(this).attr('id').replace( /sp_facets_\d+_/, 'sp_facets_'+i+'_' ) );
					$(this).attr( 'name', $(this).attr('name').replace( /sp\[facets\]\[\d+\]/, 'sp[facets]['+i+']' ) );
				});
				$(this).find('label').each(function(){
					$(this).attr( 'for', $(this).attr('for').replace( /sp_facets_\d+_/, 'sp_facets_'+i+'_' ) );
				});
			});
		}
	} );

} );