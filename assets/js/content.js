/**
 * WP Swiper: content in a place of its own (a Collage's place, an Art
 * window: WP_Swiper_Render::content_box()), shared by collage.js and art.js
 * (each loads this first). Its text cut at a word, with "…", where it does
 * not fit (the font size stays the one set); with a shortcode's markup, never
 * cut: its place scrolls (frontend.css). And the photos' order in the places
 * (window.wpSwiperPlaces, below), the same for both and their previews.
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

	/*
	 * The photos' order in a Collage's places or Art's windows (slide indexes,
	 * the first in the first place), shared by collage.js and art.js and by
	 * their previews. With more places than photos the photos repeat, and an
	 * order set there by hand (a photo dragged in the preview) says what each
	 * place shows: a photo in as many places as it was put in. Else each
	 * photo once, the others waiting their turn.
	 */

	// Each photo once, `set` first, then `rest`; indexes from 0 to total - 1.
	function once( set, total ) {
		var seen = {};
		var out = [];
		set.forEach( function ( real ) {
			real = Number( real );
			if ( real >= 0 && real < total && Math.floor( real ) === real && ! seen[ real ] ) {
				seen[ real ] = true;
				out.push( real );
			}
		} );
		return out;
	}

	/**
	 * The order to lay out.
	 *
	 * @param {number[]} set    The order set (slide indexes), or none.
	 * @param {number}   total  How many photos there are.
	 * @param {number}   places How many places take a photo.
	 * @param {number[]} rest   The photos in their turn after the set ones.
	 * @return {number[]} Each place takes order[ i % order.length ].
	 */
	function order( set, total, places, rest ) {
		var list = ( set || [] ).map( Number ).filter( function ( real ) {
			return real >= 0 && real < total && Math.floor( real ) === real;
		} );
		// More places than photos: as set, so long as every photo is in it
		// (a photo added since then would not be: each once, as before).
		if ( places > total && list.length > total ) {
			var take = list.slice( 0, places );
			var every = once( take, total ).length === total;
			if ( every ) {
				return take;
			}
		}
		return once( list.concat( rest || [] ), total );
	}

	/**
	 * Photo IDs as stored to slide indexes: each to a slide with it not taken
	 * yet (WP_Swiper_Settings::order_indexes() the same); once all of them
	 * are, a photo in more places than it has slides, to its first again.
	 *
	 * @param {number[]} ids      Photo IDs.
	 * @param {number[]} slideIds Each slide's photo ID.
	 * @return {number[]}
	 */
	function reals( ids, slideIds ) {
		var used = {};
		var out = [];
		( ids || [] ).forEach( function ( id ) {
			var first = -1;
			for ( var real = 0; real < slideIds.length; real++ ) {
				if ( slideIds[ real ] !== id ) {
					continue;
				}
				first = first === -1 ? real : first;
				if ( ! used[ real ] ) {
					used[ real ] = true;
					out.push( real );
					return;
				}
			}
			if ( first !== -1 ) {
				out.push( first );
			}
		} );
		return out;
	}

	/**
	 * After a swap in the preview: the order to keep, Content (-1) left out.
	 * More places than photos: as the places are (a photo put past the
	 * photos' number stays there); else each photo once.
	 *
	 * @param {number[]} list  The places' slide indexes, then those waiting.
	 * @param {number}   total How many photos there are.
	 * @return {number[]}
	 */
	function kept( list, total ) {
		var photos = list.filter( function ( real ) {
			return real !== -1;
		} );
		return photos.length > total ? photos : once( photos, total );
	}

	window.wpSwiperPlaces = { order: order, reals: reals, kept: kept };
}() );
