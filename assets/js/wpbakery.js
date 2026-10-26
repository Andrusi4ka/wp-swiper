/**
 * WP Swiper element in WPBakery: the "Edit slider" button under the Slider
 * list (class-wp-swiper-wpbakery.php edit_param()) opens the chosen
 * slider's edit screen in a new tab; hidden while none is chosen.
 *
 * WPBakery attaches this file at the end of the element's settings form,
 * possibly each time a form opens: the handlers are bound once, the buttons
 * of the forms in view are set on every run.
 */
( function ( $ ) {
	'use strict';

	// The Slider list of the same settings form.
	function sliderSelect( box ) {
		var $form = $( box ).closest( '.vc_edit_form_elements, .vc_ui-panel-content, form' );
		return ( $form.length ? $form : $( document ) ).find( 'select.wpb_vc_param_value[name="id"]' ).first();
	}

	function sync( box ) {
		var $box = $( box );
		var id = parseInt( sliderSelect( box ).val(), 10 );
		var $link = $box.find( '.wp-swiper-vc-edit__link' );
		if ( id > 0 ) {
			$link.attr( 'href', $box.attr( 'data-url' ) + id ).prop( 'hidden', false );
		} else {
			$link.attr( 'href', '#' ).prop( 'hidden', true );
		}
		// "Opens in a new tab", with the button.
		$box.find( '.wp-swiper-vc-edit__note' ).prop( 'hidden', ! ( id > 0 ) );
	}

	function syncAll() {
		$( '.wp-swiper-vc-edit' ).each( function () {
			sync( this );
		} );
	}

	if ( ! window.wpSwiperVcEdit ) {
		window.wpSwiperVcEdit = true;

		// Another slider chosen.
		$( document ).on( 'change', 'select.wpb_vc_param_value[name="id"]', function () {
			var $form = $( this ).closest( '.vc_edit_form_elements, .vc_ui-panel-content, form' );
			( $form.length ? $form : $( document ) ).find( '.wp-swiper-vc-edit' ).each( function () {
				sync( this );
			} );
		} );

		// A settings form shown later (WPBakery renders them as they open).
		if ( 'MutationObserver' in window ) {
			new MutationObserver( function ( records ) {
				for ( var i = 0; i < records.length; i++ ) {
					for ( var j = 0; j < records[ i ].addedNodes.length; j++ ) {
						var node = records[ i ].addedNodes[ j ];
						if ( node.nodeType === 1 && ( node.matches( '.wp-swiper-vc-edit' ) || node.querySelector( '.wp-swiper-vc-edit' ) ) ) {
							syncAll();
							return;
						}
					}
				}
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	$( syncAll );
}( window.jQuery ) );
