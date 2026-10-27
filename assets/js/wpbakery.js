/**
 * WP Swiper element in WPBakery: under the Slider list, "Edit slider"
 * (class-wp-swiper-wpbakery.php edit_param()) opens the chosen slider's
 * edit screen in a new tab, hidden while none is chosen; "Add slider" opens
 * the new slider screen in a new tab. Back on this tab after that, the list
 * is asked for again (admin-ajax.php) and the slider added there, the
 * newest of those not in the list before, is chosen.
 *
 * WPBakery attaches this file at the end of the element's settings form,
 * possibly each time a form opens: the handlers are bound once, the buttons
 * of the forms in view are set on every run.
 */
( function ( $ ) {
	'use strict';

	// The settings form a box is in.
	function formOf( node ) {
		var $form = $( node ).closest( '.vc_edit_form_elements, .vc_ui-panel-content, form' );
		return $form.length ? $form : $( document );
	}

	// The Slider list of the same settings form.
	function sliderSelect( box ) {
		return formOf( box ).find( 'select.wpb_vc_param_value[name="id"]' ).first();
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
		// "Opens in a new tab", while there is a button.
		$box.find( '.wp-swiper-vc-edit__note' ).prop( 'hidden', ! ( id > 0 ) && ! $box.find( '.wp-swiper-vc-edit__add' ).length );
	}

	function syncAll() {
		$( '.wp-swiper-vc-edit' ).each( function () {
			sync( this );
		} );
	}

	/**
	 * The Slider list as the site has it now: its options again (the first,
	 * "Select slider", kept), the slider chosen kept; with `choose`, the
	 * newest of those not there before is chosen.
	 *
	 * @param {Element} box    The buttons' box.
	 * @param {boolean} choose Choose a slider added meanwhile.
	 */
	function refresh( box, choose ) {
		var $select = sliderSelect( box );
		var url = $( box ).attr( 'data-list' );
		if ( ! $select.length || ! url ) {
			return;
		}
		$.getJSON( url ).done( function ( response ) {
			if ( ! response || ! response.success || ! Array.isArray( response.data ) ) {
				return;
			}
			var known = {};
			$select.find( 'option' ).each( function () {
				known[ this.value ] = true;
			} );
			var chosen = $select.val();
			var added = 0;
			$select.find( 'option' ).slice( 1 ).remove();
			response.data.forEach( function ( slider ) {
				var id = String( slider.id );
				$( '<option>' ).val( id ).text( slider.label ).addClass( id ).appendTo( $select );
				if ( ! known[ id ] ) {
					added = Math.max( added, slider.id );
				}
			} );
			if ( choose && added ) {
				chosen = String( added );
			}
			$select.val( $select.find( 'option[value="' + chosen + '"]' ).length ? chosen : '' );
			// WPBakery and the buttons take the choice as one made by hand.
			$select.trigger( 'change' );
		} );
	}

	if ( ! window.wpSwiperVcEdit ) {
		window.wpSwiperVcEdit = true;

		// Another slider chosen.
		$( document ).on( 'change', 'select.wpb_vc_param_value[name="id"]', function () {
			formOf( this ).find( '.wp-swiper-vc-edit' ).each( function () {
				sync( this );
			} );
		} );

		// "Add slider": the form waits for this tab to be back in view.
		$( document ).on( 'click', '.wp-swiper-vc-edit__add', function () {
			$( this ).closest( '.wp-swiper-vc-edit' ).addClass( 'is-adding' );
		} );

		// Back on this tab: the lists of the forms open, the new slider chosen
		// where it was added from.
		var back = function () {
			if ( document.hidden ) {
				return;
			}
			$( '.wp-swiper-vc-edit' ).each( function () {
				var adding = $( this ).hasClass( 'is-adding' );
				$( this ).removeClass( 'is-adding' );
				refresh( this, adding );
			} );
		};
		document.addEventListener( 'visibilitychange', back );
		window.addEventListener( 'focus', function () {
			// Focus also comes back from the page's own fields: only after a new tab.
			if ( $( '.wp-swiper-vc-edit.is-adding' ).length ) {
				back();
			}
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
