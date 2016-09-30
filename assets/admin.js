jQuery( function( $ ) {
	$( 'a.nav-tab' ).click( function( e ) {
		e.preventDefault();
		$( '.nav-tab-active' ).removeClass( 'nav-tab-active' );
		$( '.tab-content' ).hide();
		$( $( this ).attr( 'href' ) ).fadeIn();
		$( this ).addClass( 'nav-tab-active' );
	} );
	$( 'a.nav-tab-active' ).click();

	var progress_total = $( '.progress-bar' ).data( 'total' ) - 0
	, progress_processed = $( '.progress-bar' ).data( 'processed' ) - 0;

	setInterval( function() {
		jQuery.get( ajaxurl, { action : 'sp_sync_status', t : new Date().getTime() }, function( data ) {
			if ( data.processed ) {
				if ( data.processed == 'complete' ) {
					jQuery( '#sync-processed' ).text( progress_total );
					jQuery( '.progress-bar' ).animate( { width: '100%' }, 1000, 'swing', function() {
						document.location = searchpress.admin_url + '&complete=1';
					} );
				} else if ( data.processed > progress_processed ) {
					var new_width = Math.round( data.processed / progress_total * 100 );
					progress_processed = data.processed;
					jQuery( '#sync-processed' ).text( data.processed );
					jQuery( '.progress-bar' ).animate( { width: new_width + '%' }, 1000 );
				}
			}
		}, 'json' );
	}, 10000 );
} );
