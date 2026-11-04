/**
 * WP Swiper: content in a place of its own (a Collage's place, an Art
 * window: WP_Swiper_Render::content_box()), shared by collage.js and art.js
 * (each loads this first). Its text cut at a word, with "…", where it does
 * not fit (the font size stays the one set); with a shortcode's markup, never
 * cut: its place scrolls (frontend.css).
 */
( function () {
	'use strict';

	/**
	 * @param {Element} box The content (.wp-swiper-content), in its place.
	 */
	function fit( box ) {
		// With a shortcode's markup (a form, buttons…): never cut, it scrolls
		// instead (frontend.css --scroll); its nodes stay as they are.
		if ( ! box || box.classList.contains( 'wp-swiper-content--scroll' ) ) {
			return;
		}
		var inner = box.querySelector( '.wp-swiper-content__inner' );
		var text = inner && inner.querySelector( '.wp-swiper-content__text' );
		if ( ! text ) {
			return;
		}
		if ( undefined === text.wpSwiperFull ) {
			text.wpSwiperFull = text.innerHTML;
		}
		text.innerHTML = text.wpSwiperFull;
		text.hidden = false;
		var fits = function () {
			return inner.scrollHeight <= inner.clientHeight + 1 && inner.scrollWidth <= inner.clientWidth + 1;
		};
		if ( ! inner.clientHeight || fits() ) {
			return;
		}
		var nodes = function () {
			var walker = document.createTreeWalker( text, NodeFilter.SHOW_TEXT );
			var list = [];
			while ( walker.nextNode() ) {
				list.push( walker.currentNode );
			}
			return list;
		};
		var total = nodes().reduce( function ( sum, node ) {
			return sum + node.nodeValue.length;
		}, 0 );
		// The first `count` characters (back to a word), "…" after them,
		// nothing after it; false if nothing is left.
		var cutAt = function ( count, atWord ) {
			text.innerHTML = text.wpSwiperFull;
			var left = count;
			var list = nodes();
			var at = -1;
			for ( var i = 0; i < list.length; i++ ) {
				if ( left <= list[ i ].nodeValue.length ) {
					at = i;
					break;
				}
				left -= list[ i ].nodeValue.length;
			}
			if ( at < 0 ) {
				return true;
			}
			var value = list[ at ].nodeValue.slice( 0, left );
			if ( atWord && /\S$/.test( value ) && /^\S/.test( list[ at ].nodeValue.slice( left ) ) ) {
				value = value.replace( /\S+$/, '' );
			}
			list[ at ].nodeValue = value.replace( /\s+$/, '' ) + '…';
			// Everything after the cut: the rest of its nodes, then any element left empty.
			for ( var j = at + 1; j < list.length; j++ ) {
				list[ j ].nodeValue = '';
			}
			[].slice.call( text.querySelectorAll( '*' ) ).reverse().forEach( function ( node ) {
				if ( ! node.textContent.trim() && ! node.querySelector( 'img' ) && node.tagName !== 'BR' ) {
					node.parentNode.removeChild( node );
				}
			} );
			return '…' !== text.textContent.trim();
		};
		var low = 0;
		var high = total - 1;
		while ( low < high ) {
			var mid = Math.ceil( ( low + high ) / 2 );
			cutAt( mid, false );
			if ( fits() ) {
				low = mid;
			} else {
				high = mid - 1;
			}
		}
		if ( ! cutAt( low, true ) || ! fits() ) {
			text.innerHTML = '';
			text.hidden = true;
		}
	}

	window.wpSwiperContent = { fit: fit };
}() );
