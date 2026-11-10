/**
 * WP Swiper slider edit screen: the boxes of four values (2026-11-10, as WP
 * Banner's): Image border's sides and Rounded corners' radii
 * (WP_Swiper_Admin::render_box_field()). A part of admin.js, run by it after
 * admin-pages.js (its syncResponsive() draws them again, shared.syncBox).
 *
 * The frame is drawn as the values of the device shown are (a side's line
 * 1–6px, none: light and dashed; a corner's radius halved, at most 40px).
 * Linked (the default, as before the box: one number): a value typed, or the
 * slider moved, goes to the other three of that device; linking again puts
 * the first one's (top, top left) on every side of every device, as the
 * site then draws it (WP_Swiper_Settings::box_values()).
 */
( function ( $ ) {
	'use strict';

	var parts = ( window.wpSwiperEditor = window.wpSwiperEditor || { parts: {} } ).parts;

	parts.box = function ( shared ) {
		var CORNERS = [ 'TopLeft', 'TopRight', 'BottomRight', 'BottomLeft' ];
		var SIDES = [ 'Top', 'Right', 'Bottom', 'Left' ];

		function linked( $field ) {
			var value = $field.find( '.wp-swiper-box__linked' ).val();
			return value === '1' || value === 'true';
		}

		// A part's input on a device.
		function input( $field, part, device ) {
			return $field.find( '.wp-swiper-box .wp-swiper-devices__input[data-part="' + part + '"][data-device="' + device + '"]' );
		}

		// The device shown (its inputs not hidden).
		function shownDevice( $field ) {
			return $field.find( '.wp-swiper-box .wp-swiper-devices__input' ).filter( function () {
				return ! this.hidden;
			} ).first().data( 'device' ) || 'desktop';
		}

		// What a part shows on a device: its own value, or the one it inherits.
		function valueOf( $input ) {
			var value = $input.val();
			return Number( '' !== value ? value : ( $input.attr( 'placeholder' ) || 0 ) ) || 0;
		}

		// The first part's value of a device on the other three (an empty one: empty too).
		function spread( $field, device ) {
			var value = input( $field, 0, device ).val();
			[ 1, 2, 3 ].forEach( function ( part ) {
				input( $field, part, device ).val( value );
			} );
		}

		function syncBox( $field ) {
			if ( ! $field.hasClass( 'wp-swiper-field--box' ) ) {
				return;
			}
			var on = linked( $field );
			var titles = $field.data( 'titles' ) || [];
			$field.toggleClass( 'is-linked', on );
			$field.find( '.wp-swiper-box__link' ).attr( {
				'aria-pressed': on ? 'true' : 'false',
				title: titles[ on ? 0 : 1 ] || '',
				'aria-label': titles[ on ? 0 : 1 ] || ''
			} );
			var device = shownDevice( $field );
			var frame = $field.find( '.wp-swiper-box__frame' )[ 0 ];
			if ( ! frame ) {
				return;
			}
			[ 0, 1, 2, 3 ].forEach( function ( part ) {
				var value = valueOf( input( $field, part, device ) );
				if ( $field.hasClass( 'wp-swiper-field--corners' ) ) {
					frame.style[ 'border' + CORNERS[ part ] + 'Radius' ] = Math.min( 40, value / 2 ) + 'px';
				} else {
					frame.style[ 'border' + SIDES[ part ] ] = value ? Math.min( 6, Math.max( 1, value ) ) + 'px solid #8c8f94' : '1px dashed #c3c4c7';
				}
			} );
		}
		shared.syncBox = syncBox;

		var $boxes = $( '#wp-swiper-settings .wp-swiper-field--box' );

		// Linked from before (a slider saved without the box): the other three as
		// the first, so setting them apart starts from what is drawn.
		$boxes.each( function () {
			var $field = $( this );
			if ( linked( $field ) ) {
				[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
					spread( $field, device );
				} );
			}
			shared.syncResponsive( $field );
		} );

		$( '#wp-swiper-settings' )
			// Linked: one value typed is the four of its device.
			.on( 'input change', '.wp-swiper-field--box .wp-swiper-box .wp-swiper-devices__input', function () {
				var $field = $( this ).closest( '.wp-swiper-field--box' );
				if ( linked( $field ) ) {
					var part = String( $( this ).data( 'part' ) );
					var value = $( this ).val();
					[ 0, 1, 2, 3 ].forEach( function ( other ) {
						if ( String( other ) !== part ) {
							input( $field, other, $( this ).data( 'device' ) ).val( value );
						}
					}, this );
				}
				shared.syncResponsive( $field );
			} )
			// The slider (linked only): it set the first one shown; the other three too.
			.on( 'input', '.wp-swiper-field--box .wp-swiper-range', function () {
				var $field = $( this ).closest( '.wp-swiper-field--box' );
				spread( $field, shownDevice( $field ) );
				shared.syncResponsive( $field );
			} )
			// Set together or each on its own; together again: the first one's
			// on every side of every device.
			.on( 'click', '.wp-swiper-field--box .wp-swiper-box__link', function () {
				var $field = $( this ).closest( '.wp-swiper-field--box' );
				var on = ! linked( $field );
				if ( on ) {
					[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
						spread( $field, device );
					} );
				}
				// A change as any other (Undo, the preview out of date, unsaved).
				$field.find( '.wp-swiper-box__linked' ).val( on ? '1' : '0' ).trigger( 'change' );
				shared.syncResponsive( $field );
			} );
	};
}( jQuery ) );
