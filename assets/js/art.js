/**
 * WP Swiper, the Art effect (class-wp-swiper-art.php): each photo's piece
 * gets its place and shape for the Style, again whenever the block's width
 * changes. Kept apart from frontend.js: nothing here is shared with the
 * slider effects.
 *
 * Ring: a circle in the middle; around it a ring cut into pieces with an
 * even gap between them, rounded corners and an opening at the bottom where
 * the label sits. Each ring piece is a box around its shape, cut to it with
 * clip-path, so its photo covers just that box.
 */
( function () {
	'use strict';

	var SAMPLES = 24; // Points along an arc to find a piece's box.

	// A point at `radius` and `angle` (radians, clockwise from the right) from the middle.
	function polar( cx, cy, radius, angle ) {
		return { x: cx + radius * Math.cos( angle ), y: cy + radius * Math.sin( angle ) };
	}

	// The point `distance` from `from` towards `to`.
	function toward( from, to, distance ) {
		var length = Math.hypot( to.x - from.x, to.y - from.y ) || 1;
		var t = Math.min( 1, distance / length );
		return { x: from.x + ( to.x - from.x ) * t, y: from.y + ( to.y - from.y ) * t };
	}

	/**
	 * One ring piece between the angles `start` and `end`: the shape (an
	 * SVG path, relative to its box) and the box (relative to the stage).
	 */
	function ringPiece( cx, cy, inner, outer, start, end, gap, round ) {
		// The straight sides keep half the gap from the line between pieces.
		var shiftIn = Math.asin( Math.min( 1, gap / 2 / inner ) );
		var shiftOut = Math.asin( Math.min( 1, gap / 2 / outer ) );
		var a0 = start + shiftIn;
		var a1 = end - shiftIn;
		var b0 = start + shiftOut;
		var b1 = end - shiftOut;
		if ( a1 <= a0 || b1 <= b0 ) {
			return null;
		}
		// Corners no rounder than the piece leaves room for.
		var r = Math.max( 0, Math.min( round, ( outer - inner ) / 2 - 0.5, inner * ( a1 - a0 ) / 2 - 0.5, outer * ( b1 - b0 ) / 2 - 0.5 ) );

		var A = polar( cx, cy, inner, a0 ); // Inner, at the start.
		var B = polar( cx, cy, outer, b0 ); // Outer, at the start.
		var C = polar( cx, cy, outer, b1 ); // Outer, at the end.
		var D = polar( cx, cy, inner, a1 ); // Inner, at the end.

		// The points where each corner's curve starts and ends.
		var AB = toward( A, B, r );
		var BA = toward( B, A, r );
		var CD = toward( C, D, r );
		var DC = toward( D, C, r );
		var Bo = polar( cx, cy, outer, b0 + r / outer );
		var Co = polar( cx, cy, outer, b1 - r / outer );
		var Di = polar( cx, cy, inner, a1 - r / inner );
		var Ai = polar( cx, cy, inner, a0 + r / inner );

		// Its box: the corners and points along both arcs.
		var xs = [ A.x, B.x, C.x, D.x ];
		var ys = [ A.y, B.y, C.y, D.y ];
		for ( var i = 0; i <= SAMPLES; i++ ) {
			var po = polar( cx, cy, outer, b0 + ( b1 - b0 ) * i / SAMPLES );
			var pi = polar( cx, cy, inner, a0 + ( a1 - a0 ) * i / SAMPLES );
			xs.push( po.x, pi.x );
			ys.push( po.y, pi.y );
		}
		var box = {
			left: Math.floor( Math.min.apply( Math, xs ) ),
			top: Math.floor( Math.min.apply( Math, ys ) )
		};
		box.width = Math.ceil( Math.max.apply( Math, xs ) ) - box.left;
		box.height = Math.ceil( Math.max.apply( Math, ys ) ) - box.top;

		var p = function ( point ) {
			return ( Math.round( ( point.x - box.left ) * 100 ) / 100 ) + ' ' + ( Math.round( ( point.y - box.top ) * 100 ) / 100 );
		};
		var largeOut = b1 - b0 - 2 * r / outer > Math.PI ? 1 : 0;
		var largeIn = a1 - a0 - 2 * r / inner > Math.PI ? 1 : 0;
		var path = [
			'M', p( AB ),
			'L', p( BA ),
			'Q', p( B ), p( Bo ),
			'A', outer, outer, 0, largeOut, 1, p( Co ),
			'Q', p( C ), p( CD ),
			'L', p( DC ),
			'Q', p( D ), p( Di ),
			'A', inner, inner, 0, largeIn, 0, p( Ai ),
			'Q', p( A ), p( AB ),
			'Z'
		].join( ' ' );

		return { box: box, path: path };
	}

	function place( piece, box ) {
		piece.style.left = box.left + 'px';
		piece.style.top = box.top + 'px';
		piece.style.width = box.width + 'px';
		piece.style.height = box.height + 'px';
	}

	function ring( block, stage, config ) {
		var size = stage.clientWidth;
		if ( ! size ) {
			return;
		}
		var pieces = stage.querySelectorAll( '.wp-swiper-art__piece' );
		var label = stage.querySelector( '.wp-swiper-art__label' );
		var cx = size / 2;
		var cy = size / 2;
		var outer = size / 2;
		// The middle circle: this device's size (an older config: one for all).
		var share = typeof config.center === 'object' ? config.center[ device() ] : config.center;
		var middle = outer * ( Number( share ) || 46 ) / 100;
		var gap = config.gap;
		var inner = Math.min( outer - 12, middle + gap );
		var count = Math.max( 1, pieces.length - 1 );
		var opening = config.opening * Math.PI / 180;

		// For the preview (preview-art.js): where the middle circle's edge is.
		block.wpSwiperArtRing = { cx: cx, cy: cy, outer: outer, middle: middle, gap: gap };

		// The middle circle.
		place( pieces[ 0 ], { left: cx - middle, top: cy - middle, width: middle * 2, height: middle * 2 } );
		pieces[ 0 ].classList.add( 'wp-swiper-art__piece--circle' );

		// The ring, clockwise from the opening's left edge (the opening is at the
		// bottom, a quarter turn from the right).
		var first = Math.PI / 2 + opening / 2;
		var step = ( 2 * Math.PI - opening ) / count;
		for ( var i = 0; i < count; i++ ) {
			var shape = ringPiece( cx, cy, inner, outer, first + i * step, first + ( i + 1 ) * step, gap, config.radius );
			var piece = pieces[ i + 1 ];
			if ( ! shape ) {
				piece.hidden = true;
				continue;
			}
			piece.hidden = false;
			place( piece, shape.box );
			piece.style.clipPath = 'path("' + shape.path + '")';
		}

		// The label in the opening, between the middle circle and the edge; its
		// size follows the ring's.
		block.style.setProperty( '--wp-swiper-art-scale', ( size / 560 ).toFixed( 3 ) );
		if ( label ) {
			label.hidden = config.opening < 20;
			var band = ( inner + outer ) / 2;
			label.style.left = cx + 'px';
			label.style.top = ( cy + band ) + 'px';
			label.style.maxWidth = Math.max( 60, 2 * band * Math.sin( opening / 2 ) ) + 'px';
		}
	}

	var STYLES = { ring: ring };

	// The device by the window's width, as the plugin's breakpoints.
	function device() {
		if ( window.matchMedia( '(max-width: 767px)' ).matches ) {
			return 'mobile';
		}
		return window.matchMedia( '(max-width: 1024px)' ).matches ? 'tablet' : 'desktop';
	}

	/**
	 * @return {Object|null} For the preview (preview-art.js): the device shown,
	 *                       the photo in each piece, a new order for the device,
	 *                       each slide's photo ID.
	 */
	function setup( block ) {
		var config;
		try {
			config = JSON.parse( block.getAttribute( 'data-wp-swiper-art' ) );
		} catch ( e ) {
			return null;
		}
		var stage = block.querySelector( '.wp-swiper-art__stage' );
		var pool = block.querySelector( 'template.wp-swiper-art__pool' );
		var photos = pool ? [].slice.call( pool.content.children ) : [];
		var pieces = stage ? [].slice.call( stage.querySelectorAll( '.wp-swiper-art__piece' ) ) : [];
		var draw = STYLES[ config.style ];
		if ( ! stage || ! draw || ! photos.length ) {
			return null;
		}
		var current = null;

		/*
		 * The photos in the pieces, in the order of the device (the order set
		 * there, else the slides'); they repeat when there are fewer. A piece
		 * keeps its photo while it stays the same.
		 */
		function fill( force ) {
			var name = device();
			if ( name === current && ! force ) {
				return;
			}
			current = name;
			var order = config.order && config.order[ name ];
			if ( ! order || ! order.length ) {
				order = photos.map( function ( photo, index ) {
					return index;
				} );
			}
			pieces.forEach( function ( piece, index ) {
				var real = Number( order[ index % order.length ] );
				if ( ! photos[ real ] || piece.wpReal === real ) {
					return;
				}
				piece.wpReal = real;
				piece.textContent = '';
				[].forEach.call( photos[ real ].childNodes, function ( node ) {
					piece.appendChild( node.cloneNode( true ) );
				} );
			} );
		}

		var width = 0;
		var redraw = function () {
			fill( false );
			if ( stage.clientWidth !== width ) {
				width = stage.clientWidth;
				draw( block, stage, config );
				block.classList.add( 'is-ready' );
			}
		};
		redraw();
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( redraw ).observe( stage );
		}
		window.addEventListener( 'resize', redraw );

		return {
			device: function () {
				return current;
			},
			pieces: function () {
				return pieces.map( function ( piece ) {
					return piece.wpReal;
				} );
			},
			setOrder: function ( list ) {
				config.order = config.order || {};
				config.order[ current ] = list;
				fill( true );
			},
			// Ring: the middle circle's size for this device (% of the whole), drawn at once.
			setCenter: function ( share ) {
				if ( typeof config.center !== 'object' || ! config.center ) {
					config.center = { desktop: config.center, tablet: config.center, mobile: config.center };
				}
				config.center[ current ] = share;
				draw( block, stage, config );
			},
			ids: config.ids || []
		};
	}

	function init() {
		[].forEach.call( document.querySelectorAll( '.wp-swiper-art[data-wp-swiper-art]' ), function ( block ) {
			if ( ! block.wpSwiperArt ) {
				block.wpSwiperArt = setup( block ) || true;
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
