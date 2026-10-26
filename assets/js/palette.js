/**
 * WP Swiper > Settings, Text colors (class-wp-swiper-palette.php): the
 * user's colors, each with WordPress's color picker; added from the row
 * template, removed with their button.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $table = $( '.wp-swiper-palette__own' );
		if ( ! $table.length ) {
			return;
		}
		var $rows = $table.find( 'tbody' );
		var template = $( '.wp-swiper-palette__template' ).html();
		var max = Number( $table.data( 'max' ) ) || 24;
		// New rows' keys: after the saved ones (saving numbers them again).
		var next = $rows.children().length;

		function picker( $row ) {
			$row.find( '.wp-swiper-palette__color' ).wpColorPicker();
		}

		function sync() {
			var count = $rows.children().length;
			$table.prop( 'hidden', count === 0 );
			$( '.wp-swiper-palette__empty' ).prop( 'hidden', count > 0 );
			$( '.wp-swiper-palette__add' ).prop( 'disabled', count >= max );
		}

		$rows.children().each( function () {
			picker( $( this ) );
		} );

		$( '.wp-swiper-palette__add' ).on( 'click', function () {
			var $row = $( template.replace( /__i__/g, String( next++ ) ) ).appendTo( $rows );
			picker( $row );
			sync();
			$row.find( '.wp-color-result' ).trigger( 'focus' );
		} );

		$rows.on( 'click', '.wp-swiper-palette__remove', function () {
			$( this ).closest( 'tr' ).remove();
			sync();
			$( '.wp-swiper-palette__add' ).trigger( 'focus' );
		} );

		sync();
	} );
}( jQuery ) );
