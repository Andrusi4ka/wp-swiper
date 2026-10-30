/**
 * WP Swiper, the Art effect (class-wp-swiper-art.php): each photo's piece
 * gets its place and shape for the Style, again whenever the block's width
 * changes. Kept apart from frontend.js: nothing here is shared with the
 * slider effects.
 *
 * Ring: a circle in the middle; around it a ring cut into pieces with an
 * even gap between them, rounded corners and, if set, an opening at the
 * bottom. Each ring piece is a box around its shape, cut to it with
 * clip-path, so its photo covers just that box.
 *
 * Ring + text: the same ring, all around (no opening), and no photo in the
 * middle: an image and text there instead (centerContent()). Its first
 * piece is the ring's first.
 *
 * Grid + circle: a grid as the Collage's (its Photos in rows of Columns,
 * per device) and the circle over its middle; each cell its box less the
 * circle (gridCircle(), gridCell()).
 *
 * A photo's caption sits in the middle of its shape; the slides'
 * descriptions under the block show the one of the photo under the pointer
 * (or touched, or focused), else the middle photo's.
 */
( function () {
	'use strict';

	var SAMPLES = 24; // Points along an arc to find a piece's box.
	var FIT_MIN = 120; // Height % or "Auto": less height than this, the fallback instead.
	var GRID_FALLBACK = 500; // A grid's height (px) where % or Auto gives it none.

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

		// Where its caption goes: the middle of the shape (halfway across the
		// ring, halfway along the piece), and as wide as a level line through
		// that point stays inside the shape.
		var mid = ( start + end ) / 2;
		var band = ( inner + outer ) / 2;
		var spot = polar( cx, cy, band, mid );
		var across = outer - inner;
		var along = Math.max( 0, band * ( end - start ) - gap );
		var cos = Math.abs( Math.cos( mid ) );
		var sin = Math.abs( Math.sin( mid ) );
		var width = Math.min( cos > 0.01 ? across / cos : Infinity, sin > 0.01 ? along / sin : Infinity );

		return {
			box: box,
			path: path,
			caption: { x: spot.x - box.left, y: spot.y - box.top, width: width }
		};
	}

	function place( piece, box ) {
		piece.style.left = box.left + 'px';
		piece.style.top = box.top + 'px';
		piece.style.width = box.width + 'px';
		piece.style.height = box.height + 'px';
	}

	/*
	 * Image border: a line along the piece's shape (`d`, an SVG path in its
	 * box), over its photos. Twice as wide as set: the shape cuts off its
	 * outer half, so it lies inside the photo as a slider's border does.
	 */
	var SVG = 'http://www.w3.org/2000/svg';
	function outline( block, piece, d ) {
		if ( ! block.classList.contains( 'wp-swiper-art--border' ) ) {
			return;
		}
		var svg = piece.querySelector( ':scope > .wp-swiper-art__border' );
		if ( ! svg ) {
			svg = document.createElementNS( SVG, 'svg' );
			svg.setAttribute( 'class', 'wp-swiper-art__border' );
			svg.setAttribute( 'aria-hidden', 'true' );
			svg.setAttribute( 'focusable', 'false' );
			svg.appendChild( document.createElementNS( SVG, 'path' ) );
			piece.appendChild( svg );
		}
		svg.firstChild.setAttribute( 'd', d );
	}

	// Where in a piece its caption sits (art.css), with some room left at the sides.
	function captionAt( piece, spot ) {
		piece.style.setProperty( '--wp-swiper-art-cx', Math.round( spot.x ) + 'px' );
		piece.style.setProperty( '--wp-swiper-art-cy', Math.round( spot.y ) + 'px' );
		piece.style.setProperty( '--wp-swiper-art-cw', Math.max( 40, Math.round( spot.width * 0.85 ) ) + 'px' );
	}

	function ring( block, stage, config ) {
		var size = stage.clientWidth;
		if ( ! size ) {
			return;
		}
		var pieces = stage.querySelectorAll( '.wp-swiper-art__piece' );
		var cx = size / 2;
		var cy = size / 2;
		var outer = size / 2;
		// This device's values: the middle circle's size, the gap, the photos
		// around it, the corners and the opening.
		var middle = outer * ( Number( forDevice( config.center ) ) || 46 ) / 100;
		var gap = Number( forDevice( config.gap ) ) || 0;
		var inner = Math.min( outer - 12, middle + gap );
		// Ring: the first piece is the middle circle's photo; Ring + text has
		// none there (its image and text instead, centerContent()).
		var first = hasMiddlePhoto( config ) ? 1 : 0;
		var count = Math.max( 1, Math.min( pieces.length - first, Number( forDevice( config.count ) ) || pieces.length - first ) );
		var round = Number( forDevice( config.radius ) ) || 0;
		var opening = ( Number( forDevice( config.opening ) ) || 0 ) * Math.PI / 180;

		// The pieces another device shows more of: none here.
		for ( var extra = count + first; extra < pieces.length; extra++ ) {
			pieces[ extra ].hidden = true;
		}

		// For the preview (preview-art.js): where the middle circle's edge is.
		block.wpSwiperArtRing = { cx: cx, cy: cy, outer: outer, middle: middle, gap: gap };

		// The image or text in the opening (Ring), in the middle (Ring + text).
		openingContent( block, stage, config, { cx: cx, cy: cy, outer: outer, middle: middle, gap: gap, opening: opening } );
		centerContent( block, stage, { cx: cx, cy: cy, middle: middle } );

		// The middle circle.
		if ( first ) {
			place( pieces[ 0 ], { left: cx - middle, top: cy - middle, width: middle * 2, height: middle * 2 } );
			pieces[ 0 ].classList.add( 'wp-swiper-art__piece--circle' );
			pieces[ 0 ].style.clipPath = '';
			captionAt( pieces[ 0 ], { x: middle, y: middle, width: middle * 2 * 0.85 } );
			outline( block, pieces[ 0 ], 'M 0 ' + middle + ' A ' + middle + ' ' + middle + ' 0 1 1 ' + ( middle * 2 ) + ' ' + middle + ' A ' + middle + ' ' + middle + ' 0 1 1 0 ' + middle + ' Z' );
		}

		// The ring, clockwise from the opening's left edge (the opening is at the
		// bottom, a quarter turn from the right).
		var from = Math.PI / 2 + opening / 2;
		var step = ( 2 * Math.PI - opening ) / count;
		for ( var i = 0; i < count; i++ ) {
			var shape = ringPiece( cx, cy, inner, outer, from + i * step, from + ( i + 1 ) * step, gap, round );
			var piece = pieces[ i + first ];
			if ( ! shape ) {
				piece.hidden = true;
				continue;
			}
			piece.hidden = false;
			place( piece, shape.box );
			piece.style.clipPath = 'path("' + shape.path + '")';
			captionAt( piece, shape.caption );
			outline( block, piece, shape.path );
		}
	}

	/**
	 * Ring: the image and text in its opening (class-wp-swiper-art.php
	 * opening()), in the largest box that fits the free space there: under
	 * the middle circle, between the ring's two ends, down to the bottom of
	 * the stage, its Gap from the photos. The opening widens downwards, so
	 * the box starts where its width and height give it the most room.
	 * Hidden where there is no opening, or no room.
	 *
	 * @param {Element} block  The Art block (the content is placed in it, beside the stage).
	 * @param {Element} stage  The stage.
	 * @param {Object}  config Art config.
	 * @param {Object}  ring   { cx, cy, outer, middle, gap, opening (radians) } in stage px.
	 */
	function openingContent( block, stage, config, ring ) {
		var box = block.querySelector( ':scope > .wp-swiper-art__opening' );
		if ( ! box ) {
			return;
		}
		var space = Number( forDevice( config.openingGap ) ) || 0;
		var half = Math.min( ring.opening / 2, Math.PI / 2 - 0.001 );
		if ( half <= 0 ) {
			box.hidden = true;
			return;
		}
		// At a depth t under the middle its half-width is t * k - c: the ring's
		// ends keep half the gap from the opening's sides, the content its own
		// gap from them (both square to the sides).
		var k = Math.tan( half );
		var c = ( ring.gap / 2 + space ) / Math.cos( half );
		var bottom = ring.outer;
		var top = Math.max( ring.middle + ring.gap + space, ( bottom + c / k ) / 2 );
		var halfWidth = Math.min( ring.outer, top * k - c );
		var height = bottom - top;
		if ( halfWidth < 8 || height < 8 ) {
			box.hidden = true;
			return;
		}
		box.hidden = false;
		box.style.left = ( stage.offsetLeft + ring.cx - halfWidth ) + 'px';
		box.style.top = ( stage.offsetTop + ring.cy + top ) + 'px';
		box.style.width = ( halfWidth * 2 ) + 'px';
		box.style.height = height + 'px';
		// The image over the text: the text cut with "…" where both do not fit.
		fitText( box.firstElementChild, '.wp-swiper-art__opening-text' );
	}

	/**
	 * Ring + text: the image and text in the middle circle
	 * (class-wp-swiper-art.php center()), over it: a circle as large as the
	 * middle one (its Middle background), its content within the square that
	 * fits in it, less the Padding (art.css). Text too long for it is cut
	 * with "…" here (fitText()).
	 *
	 * @param {Element} block The Art block (the content is placed in it, beside the stage).
	 * @param {Element} stage The stage.
	 * @param {Object}  ring  { cx, cy, middle } in stage px.
	 */
	function centerContent( block, stage, ring ) {
		var box = block.querySelector( ':scope > .wp-swiper-art__center' );
		if ( ! box ) {
			return;
		}
		box.hidden = false;
		box.style.left = ( stage.offsetLeft + ring.cx - ring.middle ) + 'px';
		box.style.top = ( stage.offsetTop + ring.cy - ring.middle ) + 'px';
		box.style.width = box.style.height = ( ring.middle * 2 ) + 'px';
		// Grid + square + text: a square (art.css), its corners as the photos'.
		box.classList.toggle( 'wp-swiper-art__center--square', !! ring.square );
		box.style.borderRadius = ring.square ? Math.min( ring.round || 0, ring.middle ) + 'px' : '';

		fitText( box.firstElementChild, '.wp-swiper-art__center-text' );
	}

	/**
	 * The text as it is set (its Font size, the theme's headings and colors):
	 * where the image and text do not fit their box, as much of the text as
	 * fits from the top, cut at a word with "…" (the image keeps its share;
	 * no text at all where not even a word fits). Done again from the whole
	 * text each time Art is laid out (a resize too), so a wider screen shows
	 * more of it. The middle's and the opening's.
	 *
	 * @param {Element} inner    The box the image and text are in.
	 * @param {string}  selector The text's.
	 */
	var ELLIPSIS = '\u2026';
	function fitText( inner, selector ) {
		var text = inner && inner.querySelector( selector );
		if ( ! text ) {
			return;
		}
		if ( undefined === text.wpSwiperFull ) {
			text.wpSwiperFull = text.innerHTML;
		}
		text.innerHTML = text.wpSwiperFull;
		text.hidden = false;
		// All of it inside the box (centred in it, what does not fit goes over both edges).
		var fits = function () {
			var box = inner.getBoundingClientRect();
			var first = inner.firstElementChild.getBoundingClientRect();
			var last = inner.lastElementChild.getBoundingClientRect();
			return first.top >= box.top - 1 && last.bottom <= box.bottom + 1 && inner.scrollWidth <= inner.clientWidth + 1;
		};
		if ( fits() ) {
			return;
		}
		// The most characters that fit (binary search), then back to a word.
		var low = 0;
		var high = textLength( text ) - 1;
		while ( low < high ) {
			var mid = Math.ceil( ( low + high ) / 2 );
			cutText( text, mid, false );
			if ( fits() ) {
				low = mid;
			} else {
				high = mid - 1;
			}
		}
		if ( ! cutText( text, low, true ) || ! fits() ) {
			text.innerHTML = '';
			text.hidden = true;
		}
	}

	function textNodes( root ) {
		var walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT );
		var nodes = [];
		while ( walker.nextNode() ) {
			nodes.push( walker.currentNode );
		}
		return nodes;
	}

	function textLength( root ) {
		return textNodes( root ).reduce( function ( sum, node ) {
			return sum + node.nodeValue.length;
		}, 0 );
	}

	/*
	 * The whole text (text.wpSwiperFull) cut after `count` characters (at a
	 * word: back to the last space before it), "…" at its end, everything
	 * after it left out (its tags closed as they are). False if nothing is left.
	 */
	function cutText( text, count, atWord ) {
		text.innerHTML = text.wpSwiperFull;
		var nodes = textNodes( text );
		if ( atWord ) {
			var all = nodes.map( function ( node ) {
				return node.nodeValue;
			} ).join( '' );
			var space = all.slice( 0, count + 1 ).search( /\s\S*$/ );
			count = space > 0 ? space : count;
		}
		var before = 0;
		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			var length = node.nodeValue.length;
			if ( before + length < count ) {
				before += length;
				continue;
			}
			var kept = node.nodeValue.slice( 0, count - before ).replace( /[\s.,;:!?\u2026\-\u2013\u2014]+$/, '' );
			// Nothing of this one: the "…" after the text before it.
			while ( '' === kept && i > 0 ) {
				node = nodes[ --i ];
				kept = node.nodeValue.replace( /[\s.,;:!?\u2026\-\u2013\u2014]+$/, '' );
			}
			if ( '' === kept ) {
				return false;
			}
			node.nodeValue = kept + ELLIPSIS;
			dropAfter( node, text );
			return true;
		}
		return true;
	}

	// Takes out everything after `node` inside `root` (its tags stay closed).
	function dropAfter( node, root ) {
		var at = node;
		while ( at && at !== root ) {
			while ( at.nextSibling ) {
				at.parentNode.removeChild( at.nextSibling );
			}
			at = at.parentNode;
		}
	}

	// Ring, Grid + circle, Grid + square: a photo in the middle; the "+ text"
	// ones: its image and text there.
	function hasMiddlePhoto( config ) {
		return ! /-text$/.test( config.style );
	}

	// Grid + square (+ text): a square in the middle, not a circle.
	function isSquare( config ) {
		return /^grid-square/.test( config.style );
	}

	// A square `size` px, its corners rounded `round` px as the cells' (Q curves).
	function roundedSquare( size, round ) {
		var r = Math.max( 0, Math.min( round, size / 2 - 0.5 ) );
		var e = Math.round( size * 100 ) / 100;
		return [ 'M', r, 0, 'L', e - r, 0, 'Q', e, 0, e, r, 'L', e, e - r, 'Q', e, e, e - r, e, 'L', r, e, 'Q', 0, e, 0, e - r, 'L', 0, r, 'Q', 0, 0, r, 0, 'Z' ].join( ' ' );
	}

	/*
	 * Grid + circle: the grid's Photos in rows of Columns (the last row's in
	 * the middle of it) with the Gap between them, as wide as the column and as
	 * tall as its Height (Auto: square photos); a circle (Middle circle) over
	 * its middle with its photo. Each cell is its box less the circle and the Gap round it, its
	 * corners rounded (where its sides meet the circle too). A cell the
	 * circle covers, or leaves less than GRID_MIN_SHARE of, is left out: the
	 * cells shown take the pieces after the circle's in turn, and
	 * block.wpSwiperArtShown says how many pieces this device shows. The cell
	 * with the circle's middle in it is always left out.
	 */
	var GRID_MIN_SHARE = 0.2;
	var ARC_SAMPLES = 12; // Points along a cell's arc (its area, its caption's place).

	// The angle of `p` round the middle (radians, clockwise from the right).
	function angleOf( cx, cy, p ) {
		return Math.atan2( p.y - cy, p.x - cx );
	}

	// How far the arc goes from angle `from` back to `to` (the way the cells' arcs run).
	function arcSpan( from, to ) {
		var span = from - to;
		while ( span < 0 ) {
			span += 2 * Math.PI;
		}
		while ( span >= 2 * Math.PI ) {
			span -= 2 * Math.PI;
		}
		return span;
	}

	/**
	 * One grid cell (its box at `x0`, `y0`, `w` × `h`) less the circle of
	 * radius `cut` round (cx, cy), or (`square`) the square of half-side
	 * `cut`: its shape (an SVG path relative to the box), its box, and where
	 * its caption goes; null when too little of it is left.
	 */
	function gridCell( cx, cy, cut, x0, y0, w, h, round, square ) {
		// The middle in the cell: its place is the circle's or square's (gridCircle()).
		if ( cx > x0 && cx < x0 + w && cy > y0 && cy < y0 + h ) {
			return null;
		}
		var nodes = square ? squareNodes( cx, cy, cut, x0, y0, w, h ) : circleNodes( cx, cy, cut, x0, y0, w, h );
		if ( ! nodes ) {
			return null;
		}
		return cellShape( nodes, cx, cy, cut, x0, y0, w, h, round );
	}

	/*
	 * A cell's outline less the circle, clockwise: the corners outside it and
	 * where the sides cross it; from a crossing into the circle the outline
	 * follows the circle (node.arc) to where it comes out. Null if it covers all.
	 */
	function circleNodes( cx, cy, cut, x0, y0, w, h ) {
		var corners = [ { x: x0, y: y0 }, { x: x0 + w, y: y0 }, { x: x0 + w, y: y0 + h }, { x: x0, y: y0 + h } ];
		var inside = function ( p ) {
			return Math.hypot( p.x - cx, p.y - cy ) < cut;
		};
		if ( corners.every( inside ) ) {
			return null;
		}
		var nodes = [];
		for ( var i = 0; i < 4; i++ ) {
			var a = corners[ i ];
			var b = corners[ ( i + 1 ) % 4 ];
			if ( ! inside( a ) ) {
				nodes.push( { p: a, arc: false } );
			}
			var dx = b.x - a.x;
			var dy = b.y - a.y;
			var fx = a.x - cx;
			var fy = a.y - cy;
			var qa = dx * dx + dy * dy;
			var qb = 2 * ( fx * dx + fy * dy );
			var qc = fx * fx + fy * fy - cut * cut;
			var disc = qb * qb - 4 * qa * qc;
			if ( disc > 0 ) {
				var root = Math.sqrt( disc );
				var tIn = ( -qb - root ) / ( 2 * qa );
				var tOut = ( -qb + root ) / ( 2 * qa );
				if ( tIn > 1e-6 && tIn < 1 - 1e-6 ) {
					nodes.push( { p: { x: a.x + dx * tIn, y: a.y + dy * tIn }, arc: true } );
				}
				if ( tOut > 1e-6 && tOut < 1 - 1e-6 ) {
					nodes.push( { p: { x: a.x + dx * tOut, y: a.y + dy * tOut }, arc: false } );
				}
			}
		}
		return nodes.length < 3 ? null : nodes;
	}

	/*
	 * A cell's outline less the square (half-side `cut` round the middle),
	 * clockwise, all straight. The middle is not in the cell, so the square
	 * reaches in from its sides: a notch from one (a U), a corner (an L), or
	 * all but one side (a shorter box). Null if it covers all.
	 */
	var EPS = 0.01;
	function squareNodes( cx, cy, cut, x0, y0, w, h ) {
		var x1 = x0 + w;
		var y1 = y0 + h;
		var n = { x0: Math.max( x0, cx - cut ), x1: Math.min( x1, cx + cut ), y0: Math.max( y0, cy - cut ), y1: Math.min( y1, cy + cut ) };
		var corners = [ { x: x0, y: y0 }, { x: x1, y: y0 }, { x: x1, y: y1 }, { x: x0, y: y1 } ];
		if ( n.x1 - n.x0 <= EPS || n.y1 - n.y0 <= EPS ) {
			return corners.map( function ( p ) {
				return { p: p, arc: false };
			} );
		}
		var touch = { top: n.y0 <= y0 + EPS, right: n.x1 >= x1 - EPS, bottom: n.y1 >= y1 - EPS, left: n.x0 <= x0 + EPS };
		if ( touch.top && touch.right && touch.bottom && touch.left ) {
			return null;
		}
		// Each side, clockwise: whether its starting corner is in the square,
		// which way it runs, the square's span on it and the square's edge inside the cell along it.
		var sides = [
			{ touched: touch.top, start: touch.top && touch.left, from: x0, to: x1, lo: n.x0, hi: n.x1, at: function ( v ) {
				return { x: v, y: y0 };
			}, inner: function ( v ) {
				return { x: v, y: n.y1 };
			} },
			{ touched: touch.right, start: touch.right && touch.top, from: y0, to: y1, lo: n.y0, hi: n.y1, at: function ( v ) {
				return { x: x1, y: v };
			}, inner: function ( v ) {
				return { x: n.x0, y: v };
			} },
			{ touched: touch.bottom, start: touch.bottom && touch.right, from: x1, to: x0, lo: n.x0, hi: n.x1, at: function ( v ) {
				return { x: v, y: y1 };
			}, inner: function ( v ) {
				return { x: v, y: n.y0 };
			} },
			{ touched: touch.left, start: touch.left && touch.bottom, from: y1, to: y0, lo: n.y0, hi: n.y1, at: function ( v ) {
				return { x: x0, y: v };
			}, inner: function ( v ) {
				return { x: n.x1, y: v };
			} }
		];
		var points = [];
		sides.forEach( function ( side, i ) {
			if ( ! side.start ) {
				points.push( corners[ i ] );
			}
			if ( ! side.touched ) {
				return;
			}
			var forward = side.to > side.from;
			var enter = forward ? side.lo : side.hi;
			var leave = forward ? side.hi : side.lo;
			if ( Math.abs( enter - side.from ) > EPS ) {
				points.push( side.at( enter ), side.inner( enter ) );
			}
			if ( Math.abs( leave - side.to ) > EPS ) {
				points.push( side.inner( leave ), side.at( leave ) );
			}
		} );
		points = tidyOutline( points );
		return points.length < 3 ? null : points.map( function ( p ) {
			return { p: p, arc: false };
		} );
	}

	// An outline without repeated points, spikes (out and back) or points on a straight line.
	function tidyOutline( points ) {
		var same = function ( a, b ) {
			return Math.abs( a.x - b.x ) < EPS && Math.abs( a.y - b.y ) < EPS;
		};
		var changed = true;
		while ( changed && points.length > 2 ) {
			changed = false;
			for ( var i = 0; i < points.length && points.length > 2; i++ ) {
				var prev = points[ ( i - 1 + points.length ) % points.length ];
				var here = points[ i ];
				var next = points[ ( i + 1 ) % points.length ];
				var cross = ( here.x - prev.x ) * ( next.y - here.y ) - ( here.y - prev.y ) * ( next.x - here.x );
				if ( same( here, next ) || same( prev, next ) || Math.abs( cross ) < EPS ) {
					points.splice( i, 1 );
					changed = true;
					break;
				}
			}
		}
		return points;
	}

	/*
	 * A cell's shape from its outline (circleNodes() or squareNodes()): its
	 * area and caption spot, each corner rounded, as a path in its box.
	 */
	function cellShape( nodes, cx, cy, cut, x0, y0, w, h, round ) {
		var n = nodes.length;
		// node.arc: the edge from it to the next one follows the circle.
		nodes.forEach( function ( node ) {
			node.angle = angleOf( cx, cy, node.p );
		} );
		var edgeLength = function ( k ) {
			var from = nodes[ k ];
			var to = nodes[ ( k + 1 ) % n ];
			return from.arc ? cut * arcSpan( from.angle, to.angle ) : Math.hypot( to.p.x - from.p.x, to.p.y - from.p.y );
		};

		// Its area and centre of mass (the arcs as points along them): too
		// little left, none; else its caption goes there.
		var poly = [];
		nodes.forEach( function ( node, k ) {
			poly.push( node.p );
			if ( node.arc ) {
				var span = arcSpan( node.angle, nodes[ ( k + 1 ) % n ].angle );
				for ( var s = 1; s < ARC_SAMPLES; s++ ) {
					poly.push( polar( cx, cy, cut, node.angle - span * s / ARC_SAMPLES ) );
				}
			}
		} );
		var area = 0;
		var mx = 0;
		var my = 0;
		for ( var j = 0; j < poly.length; j++ ) {
			var p = poly[ j ];
			var q = poly[ ( j + 1 ) % poly.length ];
			var cross = p.x * q.y - q.x * p.y;
			area += cross;
			mx += ( p.x + q.x ) * cross;
			my += ( p.y + q.y ) * cross;
		}
		area /= 2;
		if ( Math.abs( area ) < GRID_MIN_SHARE * w * h ) {
			return null;
		}
		var spot = { x: mx / ( 6 * area ), y: my / ( 6 * area ) };

		// The caption as wide as a level line through that spot stays inside.
		var xs = [];
		for ( var e = 0; e < poly.length; e++ ) {
			var u = poly[ e ];
			var v = poly[ ( e + 1 ) % poly.length ];
			if ( ( u.y <= spot.y ) !== ( v.y <= spot.y ) ) {
				xs.push( u.x + ( spot.y - u.y ) * ( v.x - u.x ) / ( v.y - u.y ) );
			}
		}
		xs.sort( function ( l, r ) {
			return l - r;
		} );
		var width = w;
		for ( var c = 0; c + 1 < xs.length; c += 2 ) {
			if ( xs[ c ] <= spot.x && spot.x <= xs[ c + 1 ] ) {
				width = 2 * Math.min( spot.x - xs[ c ], xs[ c + 1 ] - spot.x );
			}
		}

		// Each corner rounded: the points `r` before and after it along its
		// edges (on an arc, along the circle), a curve through the corner.
		var rel = function ( point ) {
			return ( Math.round( ( point.x - x0 ) * 100 ) / 100 ) + ' ' + ( Math.round( ( point.y - y0 ) * 100 ) / 100 );
		};
		var cutIn = [];
		var cutOut = [];
		nodes.forEach( function ( node, k ) {
			var before = ( k - 1 + n ) % n;
			var r = Math.max( 0, Math.min( round, edgeLength( before ) / 2 - 0.5, edgeLength( k ) / 2 - 0.5 ) );
			cutIn[ k ] = nodes[ before ].arc ? polar( cx, cy, cut, node.angle + r / cut ) : toward( node.p, nodes[ before ].p, r );
			cutOut[ k ] = node.arc ? polar( cx, cy, cut, node.angle - r / cut ) : toward( node.p, nodes[ ( k + 1 ) % n ].p, r );
		} );
		var path = [ 'M', rel( cutOut[ 0 ] ) ];
		for ( var k = 1; k <= n; k++ ) {
			var at = k % n;
			path.push( nodes[ k - 1 ].arc ? 'A ' + cut + ' ' + cut + ' 0 0 0' : 'L', rel( cutIn[ at ] ), 'Q', rel( nodes[ at ].p ), rel( cutOut[ at ] ) );
		}
		path.push( 'Z' );

		return {
			box: { left: x0, top: y0, width: w, height: h },
			path: path.join( ' ' ),
			caption: { x: spot.x - x0, y: spot.y - y0, width: Math.min( w, width ) }
		};
	}

	function gridCircle( block, stage, config ) {
		var width = stage.clientWidth;
		if ( ! width ) {
			return;
		}
		var pieces = stage.querySelectorAll( '.wp-swiper-art__piece' );
		var gap = Number( forDevice( config.gap ) ) || 0;
		var round = Number( forDevice( config.radius ) ) || 0;
		// As the Collage's Grid: its photos in rows of Columns, as many rows as
		// they make; the last one's in the middle of it.
		var columns = Math.max( 2, Math.min( 6, Math.round( Number( forDevice( config.columns ) ) || 3 ) ) );
		// The circle's photo first (Grid + circle); Grid + circle + text has
		// none there (its image and text instead, centerContent()).
		var first = hasMiddlePhoto( config ) ? 1 : 0;
		var count = Math.max( 2, Math.min( pieces.length - first, Math.round( Number( forDevice( config.cells ) ) || pieces.length - first ) ) );
		var rows = Math.ceil( count / columns );
		var cellW = ( width - ( columns - 1 ) * gap ) / columns;
		// Its Height (art.css; % and Auto: fit()), the photos stretched to it.
		var height = stage.clientHeight;
		if ( ! height ) {
			return;
		}
		var cellH = ( height - ( rows - 1 ) * gap ) / rows;
		var cx = width / 2;
		var cy = height / 2;
		var outer = Math.min( width, height ) / 2;

		// Each photo's cell, row by row.
		var cells = [];
		for ( var i = 0; i < count; i++ ) {
			var row = Math.floor( i / columns );
			var inRow = row === rows - 1 ? count - row * columns : columns;
			var shift = ( columns - inRow ) * ( cellW + gap ) / 2;
			cells.push( { x: shift + ( i % columns ) * ( cellW + gap ), y: row * ( cellH + gap ) } );
		}

		// The circle (Grid + square: the square, `middle` its half-side): its
		// size (Middle circle), at least covering the cell its middle is in
		// (that cell is its place, gridCell()), within the grid's smaller side.
		var square = isSquare( config );
		var middle = outer * ( Number( forDevice( config.center ) ) || 46 ) / 100;
		cells.forEach( function ( cell ) {
			if ( cx > cell.x && cx < cell.x + cellW && cy > cell.y && cy < cell.y + cellH ) {
				// Its farthest corner.
				var fx = Math.max( cx - cell.x, cell.x + cellW - cx );
				var fy = Math.max( cy - cell.y, cell.y + cellH - cy );
				var far = square ? Math.max( fx, fy ) : Math.hypot( fx, fy );
				middle = Math.max( middle, far - gap + 1 );
			}
		} );
		middle = Math.max( 4, Math.min( middle, outer - gap - 4 ) );
		var cut = middle + gap;

		// For the preview (preview-art.js): where the middle circle's (or square's) edge is.
		block.wpSwiperArtRing = { cx: cx, cy: cy, outer: outer, middle: middle, gap: gap, square: square };

		// The circle or square: its photo, or its image and text.
		if ( first ) {
			pieces[ 0 ].hidden = false;
			place( pieces[ 0 ], { left: cx - middle, top: cy - middle, width: middle * 2, height: middle * 2 } );
			if ( square ) {
				var box = roundedSquare( middle * 2, round );
				pieces[ 0 ].classList.remove( 'wp-swiper-art__piece--circle' );
				pieces[ 0 ].style.clipPath = 'path("' + box + '")';
				captionAt( pieces[ 0 ], { x: middle, y: middle, width: middle * 2 } );
				outline( block, pieces[ 0 ], box );
			} else {
				pieces[ 0 ].classList.add( 'wp-swiper-art__piece--circle' );
			pieces[ 0 ].style.clipPath = '';
				captionAt( pieces[ 0 ], { x: middle, y: middle, width: middle * 2 * 0.85 } );
				outline( block, pieces[ 0 ], 'M 0 ' + middle + ' A ' + middle + ' ' + middle + ' 0 1 1 ' + ( middle * 2 ) + ' ' + middle + ' A ' + middle + ' ' + middle + ' 0 1 1 0 ' + middle + ' Z' );
			}
		}
		centerContent( block, stage, { cx: cx, cy: cy, middle: middle, square: square, round: round } );

		// The cells in turn, those left in the pieces after it.
		var used = first;
		cells.forEach( function ( cell ) {
			var shape = gridCell( cx, cy, cut, cell.x, cell.y, cellW, cellH, round, square );
			if ( ! shape || used >= pieces.length ) {
				return;
			}
			var piece = pieces[ used++ ];
			piece.hidden = false;
			place( piece, shape.box );
			piece.style.clipPath = 'path("' + shape.path + '")';
			captionAt( piece, shape.caption );
			outline( block, piece, shape.path );
		} );
		for ( var extra = used; extra < pieces.length; extra++ ) {
			pieces[ extra ].hidden = true;
		}
		block.wpSwiperArtShown = used;
	}

	/*
	 * Windows drawn edge to edge would show a hairline of the page between
	 * them (each edge is smoothed on its own, half see-through): each grows
	 * by BLEED px at its box's sides, from its middle, so neighbours overlap
	 * a little there. Too little to see anywhere else.
	 */
	var BLEED = 0.75;

	// The path in px of its box (grown by BLEED: see above).
	function scaledPath( d, width, height, box ) {
		var kx = ( box.width + 2 * BLEED ) / ( box.width || 1 );
		var ky = ( box.height + 2 * BLEED ) / ( box.height || 1 );
		var x = true;
		return d.replace( /-?\d+(?:\.\d+)?/g, function ( number ) {
			var value = x
				? ( Number( number ) * width - box.left - box.width / 2 ) * kx + box.width / 2 + BLEED
				: ( Number( number ) * height - box.top - box.height / 2 ) * ky + box.height / 2 + BLEED;
			x = ! x;
			return String( Math.round( value * 100 ) / 100 );
		} );
	}

	/*
	 * SVG shape: the windows of an SVG file (WP_Swiper_Svg_Shape), the stage
	 * at its drawing's proportions (art.css). Each window's box and path come
	 * in numbers of the stage (0–1 each way): here in px of its box, cut to it
	 * with clip-path; stacked as in the drawing. The main window, where the
	 * current photo shows, is the first piece.
	 */
	function shape( block, stage, config ) {
		var width = stage.clientWidth;
		var height = stage.clientHeight;
		if ( ! width || ! height ) {
			return;
		}
		var pieces = stage.querySelectorAll( '.wp-swiper-art__piece' );
		var shapes = config.shapes || [];
		[].forEach.call( pieces, function ( piece, i ) {
			var win = shapes[ i ];
			piece.hidden = ! win;
			if ( ! win ) {
				return;
			}
			var box = {
				left: win.box[ 0 ] * width,
				top: win.box[ 1 ] * height,
				width: win.box[ 2 ] * width,
				height: win.box[ 3 ] * height
			};
			var d = scaledPath( win.d, width, height, box );
			// The box grows with it.
			place( piece, { left: box.left - BLEED, top: box.top - BLEED, width: box.width + 2 * BLEED, height: box.height + 2 * BLEED } );
			piece.style.clipPath = 'path(' + ( win.rule === 'evenodd' ? 'evenodd, ' : '' ) + '"' + d + '")';
			// The drawing's stacking: the later on top.
			piece.style.zIndex = 1 + ( Number( win.z ) || 0 );
			if ( config.one ) {
				// One photo through all the windows: each its part of the photo,
				// the photo as large as the stage, where the window is in it; the
				// caption in the middle of the stage (art.css).
				piece.style.setProperty( '--wp-swiper-art-ox', ( box.left - BLEED ) + 'px' );
				piece.style.setProperty( '--wp-swiper-art-oy', ( box.top - BLEED ) + 'px' );
				captionAt( piece, { x: width / 2, y: height / 2, width: width } );
			} else {
				captionAt( piece, { x: box.width / 2 + BLEED, y: box.height / 2 + BLEED, width: box.width } );
			}
			outline( block, piece, d );
		} );
		if ( config.one ) {
			block.style.setProperty( '--wp-swiper-art-sw', width + 'px' );
			block.style.setProperty( '--wp-swiper-art-sh', height + 'px' );
		}
	}

	/*
	 * Grid shapes: a grid of Columns x Rows cells (config.grid, one layout for
	 * every device), each cell in a shape (its number) or empty (-1). Drawn
	 * anew at the stage's size, so it stretches to any width and height. A
	 * shape is its cells and the Gap between them where they touch (between
	 * four cells only where all four are its), cut from the stage as one
	 * outline per piece; a shape round a hole gets the hole too. Its corners
	 * are real arcs: outward ones at the shape's radius (Radius, or its own:
	 * a share of half a cell), inward ones at the radius of the shape across
	 * the corner plus the Gap, so the gap between them stays even.
	 */

	// A layout as stored (WP_Swiper_Art::parse_cells()), read; null for anything else.
	function parseCells( text ) {
		var m = /^(\d{1,2})x(\d{1,2}):(-?\d{1,3}(?:,-?\d{1,3})*)(?:;((?:\d{1,3}=\d{1,3})(?:,\d{1,3}=\d{1,3})*)?(?:;((?:\d{1,2}\.\d{1,2}=\d{1,3})(?:,\d{1,2}\.\d{1,2}=\d{1,3})*))?)?$/.exec( String( text || '' ).replace( /\s+/g, '' ) );
		if ( ! m ) {
			return null;
		}
		var columns = Number( m[ 1 ] );
		var rows = Number( m[ 2 ] );
		var cells = m[ 3 ].split( ',' ).map( Number );
		if ( columns < 1 || rows < 1 || columns > 12 || rows > 12 || cells.length !== columns * rows ) {
			return null;
		}
		var radii = {};
		( m[ 4 ] || '' ).split( ',' ).forEach( function ( pair ) {
			var parts = pair.split( '=' );
			if ( parts.length === 2 ) {
				radii[ Number( parts[ 0 ] ) ] = Math.min( 100, Number( parts[ 1 ] ) );
			}
		} );
		// Single outward corners' own radii, by their place on the grid of cells and gaps ("x.y").
		var corners = {};
		( m[ 5 ] || '' ).split( ',' ).forEach( function ( pair ) {
			var parts = pair.split( '=' );
			if ( parts.length === 2 ) {
				var at = parts[ 0 ].split( '.' ).map( Number );
				if ( at[ 0 ] <= 2 * columns - 1 && at[ 1 ] <= 2 * rows - 1 ) {
					corners[ at[ 0 ] + '.' + at[ 1 ] ] = Math.min( 100, Number( parts[ 1 ] ) );
				}
			}
		} );
		return { columns: columns, rows: rows, cells: cells, radii: radii, corners: corners };
	}

	// The shapes numbered from 0 in the order they first come row by row (WP_Swiper_Art::number_cells()).
	function numberCells( grid ) {
		var map = {};
		var count = 0;
		var cells = grid.cells.map( function ( cell ) {
			if ( cell < 0 ) {
				return -1;
			}
			if ( ! ( cell in map ) ) {
				map[ cell ] = count++;
			}
			return map[ cell ];
		} );
		var radii = {};
		Object.keys( grid.radii || {} ).forEach( function ( shape ) {
			if ( shape in map ) {
				radii[ map[ shape ] ] = grid.radii[ shape ];
			}
		} );
		var corners = {};
		Object.keys( grid.corners || {} ).forEach( function ( at ) {
			corners[ at ] = grid.corners[ at ];
		} );
		return { columns: grid.columns, rows: grid.rows, cells: cells, radii: radii, corners: corners, count: count };
	}

	// A layout as stored.
	function cellsText( grid ) {
		var radii = Object.keys( grid.radii || {} ).map( Number ).sort( function ( a, b ) {
			return a - b;
		} ).map( function ( shape ) {
			return shape + '=' + Math.round( grid.radii[ shape ] );
		} );
		var corners = Object.keys( grid.corners || {} ).sort( function ( a, b ) {
			var p = a.split( '.' ).map( Number );
			var q = b.split( '.' ).map( Number );
			return p[ 0 ] - q[ 0 ] || p[ 1 ] - q[ 1 ];
		} ).map( function ( at ) {
			return at + '=' + Math.round( grid.corners[ at ] );
		} );
		var text = grid.columns + 'x' + grid.rows + ':' + grid.cells.join( ',' );
		if ( corners.length ) {
			return text + ';' + radii.join( ',' ) + ';' + corners.join( ',' );
		}
		return text + ( radii.length ? ';' + radii.join( ',' ) : '' );
	}

	// Each shape's cells that touch side by side as one shape: a shape an
	// edit cut in two becomes two (the first part keeps its own radius).
	function splitCells( grid ) {
		var cells = grid.cells.slice();
		var out = cells.map( function () {
			return -1;
		} );
		var radii = {};
		var given = {};
		var next = 0;
		for ( var start = 0; start < cells.length; start++ ) {
			if ( cells[ start ] < 0 || out[ start ] >= 0 ) {
				continue;
			}
			var shape = cells[ start ];
			var stack = [ start ];
			var own = next++;
			// Its radius to the first part only.
			if ( grid.radii && shape in grid.radii && ! given[ shape ] ) {
				radii[ own ] = grid.radii[ shape ];
				given[ shape ] = true;
			}
			out[ start ] = own;
			while ( stack.length ) {
				var at = stack.pop();
				var x = at % grid.columns;
				var y = Math.floor( at / grid.columns );
				[ [ x - 1, y ], [ x + 1, y ], [ x, y - 1 ], [ x, y + 1 ] ].forEach( function ( p ) {
					var k = p[ 1 ] * grid.columns + p[ 0 ];
					if ( p[ 0 ] >= 0 && p[ 0 ] < grid.columns && p[ 1 ] >= 0 && p[ 1 ] < grid.rows && out[ k ] < 0 && cells[ k ] === shape ) {
						out[ k ] = own;
						stack.push( k );
					}
				} );
			}
		}
		return numberCells( { columns: grid.columns, rows: grid.rows, cells: out, radii: radii, corners: grid.corners || {} } );
	}

	// For the preview (preview-art.js): read, write, number and split layouts.
	window.wpSwiperArtCells = { parse: parseCells, text: cellsText, number: numberCells, split: splitCells };

	/*
	 * A shape's outlines in the grid's own units (cells at even, gaps at odd
	 * indexes): its border, interior on the right (clockwise on screen, a
	 * hole the other way), as lists of corners. `filled(i, j)` says whether a
	 * unit is the shape's.
	 */
	function unitLoops( filled, i0, j0, i1, j1 ) {
		var edges = {};
		var key = function ( x, y ) {
			return x + ',' + y;
		};
		var add = function ( x0, y0, x1, y1 ) {
			var k = key( x0, y0 );
			( edges[ k ] = edges[ k ] || [] ).push( { x0: x0, y0: y0, x1: x1, y1: y1, used: false } );
		};
		for ( var j = j0; j <= j1; j++ ) {
			for ( var i = i0; i <= i1; i++ ) {
				if ( ! filled( i, j ) ) {
					continue;
				}
				if ( ! filled( i, j - 1 ) ) {
					add( i, j, i + 1, j );
				}
				if ( ! filled( i + 1, j ) ) {
					add( i + 1, j, i + 1, j + 1 );
				}
				if ( ! filled( i, j + 1 ) ) {
					add( i + 1, j + 1, i, j + 1 );
				}
				if ( ! filled( i - 1, j ) ) {
					add( i, j + 1, i, j );
				}
			}
		}
		var loops = [];
		Object.keys( edges ).forEach( function ( k ) {
			edges[ k ].forEach( function ( first ) {
				if ( first.used ) {
					return;
				}
				var loop = [];
				var edge = first;
				while ( edge && ! edge.used ) {
					edge.used = true;
					loop.push( { x: edge.x0, y: edge.y0 } );
					var dx = edge.x1 - edge.x0;
					var dy = edge.y1 - edge.y0;
					var options = ( edges[ key( edge.x1, edge.y1 ) ] || [] ).filter( function ( e ) {
						return ! e.used;
					} );
					// Where two corners touch: the right turn, round its own cell.
					options.sort( function ( a, b ) {
						var turn = function ( e ) {
							var cross = dx * ( e.y1 - e.y0 ) - dy * ( e.x1 - e.x0 );
							return cross > 0 ? 0 : ( cross === 0 ? 1 : 2 );
						};
						return turn( a ) - turn( b );
					} );
					edge = options[ 0 ];
				}
				if ( loop.length > 2 ) {
					loops.push( loop );
				}
			} );
		} );
		return loops;
	}

	function gridShapes( block, stage, config ) {
		var width = stage.clientWidth;
		var height = stage.clientHeight;
		var grid = config.grid;
		if ( ! width || ! height || ! grid ) {
			return;
		}
		var pieces = stage.querySelectorAll( '.wp-swiper-art__piece' );
		var columns = grid.columns;
		var rows = grid.rows;
		var gap = Number( forDevice( config.gap ) ) || 0;
		var round = Number( forDevice( config.radius ) ) || 0;
		var cellW = ( width - ( columns - 1 ) * gap ) / columns;
		var cellH = ( height - ( rows - 1 ) * gap ) / rows;
		// No room for the Gap: none.
		if ( cellW < 4 || cellH < 4 ) {
			gap = 0;
			cellW = width / columns;
			cellH = height / rows;
		}
		var half = Math.min( cellW, cellH ) / 2;
		var count = 0;
		grid.cells.forEach( function ( cell ) {
			count = Math.max( count, cell + 1 );
		} );

		// Where each unit starts (cells and gaps in turn), across and down.
		var bx = [ 0 ];
		var by = [ 0 ];
		for ( var u = 0; u < 2 * columns - 1; u++ ) {
			bx.push( bx[ u ] + ( u % 2 ? gap : cellW ) );
		}
		for ( var v = 0; v < 2 * rows - 1; v++ ) {
			by.push( by[ v ] + ( v % 2 ? gap : cellH ) );
		}
		var cellAt = function ( x, y ) {
			return x < 0 || y < 0 || x >= columns || y >= rows ? -1 : grid.cells[ y * columns + x ];
		};
		// A shape's radius: its own (a share of half a cell), else Radius.
		var radiusOf = function ( shape ) {
			var own = grid.radii && grid.radii[ shape ];
			if ( typeof own === 'number' && isFinite( own ) ) {
				return half * Math.max( 0, Math.min( 100, own ) ) / 100;
			}
			return Math.min( round, half );
		};
		// An outward corner's radius: its own (a share of half a cell), else its shape's.
		var cornerRadius = function ( shape, ux, uy ) {
			var own = grid.corners && grid.corners[ ux + '.' + uy ];
			if ( typeof own === 'number' && isFinite( own ) ) {
				return half * Math.max( 0, Math.min( 100, own ) ) / 100;
			}
			return radiusOf( shape );
		};
		// The shape of the cell at a point (-1: none, or a gap).
		var shapeAtPoint = function ( px, py ) {
			var x = Math.floor( px / ( cellW + gap ) );
			var y = Math.floor( py / ( cellH + gap ) );
			if ( px - x * ( cellW + gap ) > cellW || py - y * ( cellH + gap ) > cellH ) {
				return -1;
			}
			return cellAt( x, y );
		};

		// For the preview (preview-art.js): the grid as drawn, and each shape's outline.
		block.wpSwiperArtGrid = { columns: columns, rows: rows, cellW: cellW, cellH: cellH, gap: gap, width: width, height: height };
		block.wpSwiperArtOutlines = [];
		var windows = [];

		for ( var shape = 0; shape < count; shape++ ) {
			var piece = pieces[ shape ];
			if ( ! piece ) {
				break;
			}
			// Its cells' extent.
			var x0 = columns;
			var y0 = rows;
			var x1 = -1;
			var y1 = -1;
			var sumX = 0;
			var sumY = 0;
			var cellsIn = 0;
			for ( var k = 0; k < grid.cells.length; k++ ) {
				if ( grid.cells[ k ] === shape ) {
					var cx = k % columns;
					var cy = Math.floor( k / columns );
					x0 = Math.min( x0, cx );
					y0 = Math.min( y0, cy );
					x1 = Math.max( x1, cx );
					y1 = Math.max( y1, cy );
					sumX += cx;
					sumY += cy;
					cellsIn++;
				}
			}
			if ( ! cellsIn ) {
				piece.hidden = true;
				continue;
			}
			var filled = ( function ( own ) {
				return function ( i, j ) {
					if ( i < 0 || j < 0 || i > 2 * columns - 2 || j > 2 * rows - 2 ) {
						return false;
					}
					var x = i >> 1;
					var y = j >> 1;
					var across = i % 2;
					var down = j % 2;
					if ( cellAt( x, y ) !== own ) {
						return false;
					}
					if ( across && cellAt( x + 1, y ) !== own ) {
						return false;
					}
					if ( down && cellAt( x, y + 1 ) !== own ) {
						return false;
					}
					return ! ( across && down && cellAt( x + 1, y + 1 ) !== own );
				};
			}( shape ) );
			var loops = unitLoops( filled, 2 * x0, 2 * y0, 2 * x1, 2 * y1 ).map( function ( loop ) {
				// In px, without points on a straight line or on top of each other
				// (a gap of 0 is a unit of no size).
				return tidyOutline( loop.map( function ( p ) {
					// Its place on the grid too (a corner's own radius is kept by it).
					return { x: bx[ p.x ], y: by[ p.y ], ux: p.x, uy: p.y };
				} ) );
			} ).filter( function ( loop ) {
				return loop.length > 2;
			} );
			var box = { left: bx[ 2 * x0 ], top: by[ 2 * y0 ] };
			box.width = bx[ 2 * x1 ] + cellW - box.left;
			box.height = by[ 2 * y1 ] + cellH - box.top;
			var own = radiusOf( shape );
			// Its outward corners, for the preview: where each is and its place on the grid.
			var handles = [];

			var d = [];
			loops.forEach( function ( loop ) {
				var n = loop.length;
				var corners = loop.map( function ( p, i ) {
					var prev = loop[ ( i - 1 + n ) % n ];
					var next = loop[ ( i + 1 ) % n ];
					var lenIn = Math.hypot( p.x - prev.x, p.y - prev.y ) || 1;
					var lenOut = Math.hypot( next.x - p.x, next.y - p.y ) || 1;
					var din = { x: ( p.x - prev.x ) / lenIn, y: ( p.y - prev.y ) / lenIn };
					var dout = { x: ( next.x - p.x ) / lenOut, y: ( next.y - p.y ) / lenOut };
					var outward = din.x * dout.y - din.y * dout.x > 0;
					// Inwards, along the corner's diagonal.
					var dx = Math.round( dout.x - din.x );
					var dy = Math.round( dout.y - din.y );
					var r;
					if ( outward ) {
						r = cornerRadius( shape, p.ux, p.uy );
					} else {
						// The shape across the corner: its outward corner there (one
						// place on along the diagonal), a Gap away.
						var reach = gap + half / 2;
						var across = shapeAtPoint( p.x + dx * reach, p.y + dy * reach );
						r = ( across >= 0 ? cornerRadius( across, p.ux + dx, p.uy + dy ) : own ) + gap;
					}
					r = Math.max( 0, Math.min( r, lenIn / 2, lenOut / 2 ) );
					if ( outward ) {
						// Its handle on the curve, a little in from the corner.
						var inset = Math.max( 7, r * ( 1 - Math.SQRT1_2 ) );
						handles.push( { key: p.ux + '.' + p.uy, x: p.x + dx * inset, y: p.y + dy * inset } );
					}
					return { p: p, din: din, dout: dout, r: r, sweep: outward ? 1 : 0 };
				} );
				var rel = function ( x, y ) {
					return ( Math.round( ( x - box.left ) * 100 ) / 100 ) + ' ' + ( Math.round( ( y - box.top ) * 100 ) / 100 );
				};
				var from = function ( c ) {
					return rel( c.p.x - c.din.x * c.r, c.p.y - c.din.y * c.r );
				};
				var to = function ( c ) {
					return rel( c.p.x + c.dout.x * c.r, c.p.y + c.dout.y * c.r );
				};
				d.push( 'M', to( corners[ 0 ] ) );
				for ( var c = 1; c <= n; c++ ) {
					var corner = corners[ c % n ];
					d.push( 'L', from( corner ) );
					if ( corner.r > 0.01 ) {
						var r = Math.round( corner.r * 100 ) / 100;
						d.push( 'A', r, r, 0, 0, corner.sweep, to( corner ) );
					}
				}
				d.push( 'Z' );
			} );
			d = d.join( ' ' );

			piece.hidden = false;
			place( piece, box );
			piece.style.clipPath = 'path("' + d + '")';
			piece.classList.remove( 'wp-swiper-art__piece--circle' );
			if ( config.one ) {
				piece.style.setProperty( '--wp-swiper-art-ox', box.left + 'px' );
				piece.style.setProperty( '--wp-swiper-art-oy', box.top + 'px' );
				// The caption in the photo, as large as the stage: its middle (art.css).
				captionAt( piece, { x: width / 2, y: height / 2, width: width } );
			} else {
				// Its caption in the cell nearest its middle, as wide as its run of cells in that row.
				var mx = sumX / cellsIn;
				var my = sumY / cellsIn;
				var best = -1;
				var bestDistance = Infinity;
				for ( var m = 0; m < grid.cells.length; m++ ) {
					if ( grid.cells[ m ] === shape ) {
						var distance = Math.hypot( m % columns - mx, Math.floor( m / columns ) - my );
						if ( distance < bestDistance ) {
							bestDistance = distance;
							best = m;
						}
					}
				}
				var bxCell = best % columns;
				var byCell = Math.floor( best / columns );
				var left = bxCell;
				var right = bxCell;
				while ( cellAt( left - 1, byCell ) === shape ) {
					left--;
				}
				while ( cellAt( right + 1, byCell ) === shape ) {
					right++;
				}
				captionAt( piece, {
					x: ( bx[ 2 * left ] + bx[ 2 * right ] + cellW ) / 2 - box.left,
					y: by[ 2 * byCell ] + cellH / 2 - box.top,
					width: ( right - left + 1 ) * ( cellW + gap ) - gap
				} );
			}
			outline( block, piece, d );
			block.wpSwiperArtOutlines[ shape ] = { box: box, d: d, corners: handles };
			windows.push( { box: [ box.left / width, box.top / height, box.width / width, box.height / height ] } );
		}
		for ( var extra = count; extra < pieces.length; extra++ ) {
			pieces[ extra ].hidden = true;
		}
		if ( config.one ) {
			block.style.setProperty( '--wp-swiper-art-sw', width + 'px' );
			block.style.setProperty( '--wp-swiper-art-sh', height + 'px' );
		}
		// For Appearance (wave()): where each window is, and the stage's proportions.
		config.windows = windows;
		config.windowsRatio = width / height;
		block.wpSwiperArtShown = Math.min( count, pieces.length );
	}

	/*
	 * SVG shape, Appearance (config.appear): when each of the first `count`
	 * windows shows a new photo, a share (0–1) of the Fade speed: by where its
	 * middle is (from the left, the top, along the diagonal, from the stage's
	 * middle out) or at random (a new order each time), spread evenly.
	 */
	function wave( config, count ) {
		// Grid shapes: its windows as last drawn (gridShapes()).
		var shapes = config.windows || config.shapes || [];
		var ratio = Number( config.windowsRatio || config.ratio ) || 1;
		var values = [];
		for ( var k = 0; k < count; k++ ) {
			var box = shapes[ k ] && shapes[ k ].box;
			var x = box ? box[ 0 ] + box[ 2 ] / 2 : 0.5;
			var y = box ? box[ 1 ] + box[ 3 ] / 2 : 0.5;
			values.push( {
				ltr: x,
				ttb: y,
				diagonal: x * ratio + y,
				center: Math.sqrt( Math.pow( ( x - 0.5 ) * ratio, 2 ) + Math.pow( y - 0.5, 2 ) ),
				random: Math.random()
			}[ config.appear ] || 0 );
		}
		if ( config.appear === 'random' ) {
			// Evenly spread: each its place in a shuffled order.
			var ranks = values.map( function ( value, index ) {
				return index;
			} ).sort( function ( a, b ) {
				return values[ a ] - values[ b ];
			} );
			ranks.forEach( function ( index, rank ) {
				values[ index ] = rank;
			} );
		}
		var min = Math.min.apply( Math, values );
		var max = Math.max.apply( Math, values );
		return values.map( function ( value ) {
			return max - min > 1e-6 ? ( value - min ) / ( max - min ) : 0;
		} );
	}

	// The Fade speed (ms), as the block has it (render.php: --wp-swiper-art-speed).
	function fadeSpeed( block ) {
		var speed = parseFloat( getComputedStyle( block ).getPropertyValue( '--wp-swiper-art-speed' ) );
		return speed > 0 ? speed : 800;
	}

	// The longest a wave waits for its images (a slow file: it goes on without).
	var GATE_WAIT = 1500;

	// No motion wanted: no wave either.
	var STILL = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

	var STYLES = { ring: ring, 'ring-text': ring, 'grid-circle': gridCircle, 'grid-circle-text': gridCircle, 'grid-square': gridCircle, 'grid-square-text': gridCircle, 'grid-shapes': gridShapes, shape: shape };

	/**
	 * The slides' descriptions (render.php descriptions()): the block right
	 * after this one, or none. The one of the photo under the pointer (or
	 * touched, or focused) shows, and the middle photo's once the pointer
	 * leaves; one fades into the next as Text transition says (frontend.css),
	 * or they just swap ("None", or no motion wanted).
	 *
	 * @return {Object|null} rest(): back to the middle photo's; refresh(): the middle
	 *                       photo's again (swapped), unless another is pointed at.
	 */
	function descriptions( block, stage, pieces ) {
		var box = block.nextElementSibling;
		if ( ! box || ! box.classList.contains( 'wp-swiper-descriptions' ) ) {
			return null;
		}
		var items = [].slice.call( box.querySelectorAll( '.wp-swiper-descriptions__item' ) );
		var still = box.classList.contains( 'wp-swiper-descriptions--none' ) || ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
		var current = null;

		items.forEach( function ( item ) {
			item.addEventListener( 'animationend', function ( e ) {
				// Its own animation, not one of the text inside.
				if ( e.target === this ) {
					this.classList.remove( 'is-entering', 'is-leaving' );
				}
			} );
		} );

		function show( index ) {
			if ( typeof index !== 'number' || index === current ) {
				return;
			}
			var first = current === null;
			current = index;
			items.forEach( function ( item ) {
				var coming = Number( item.getAttribute( 'data-wp-swiper-index' ) ) === index;
				var going = ! coming && item.classList.contains( 'is-active' );

				// A change during the last one's: from where things are now.
				item.classList.remove( 'is-entering', 'is-leaving' );
				item.classList.toggle( 'is-active', coming );
				if ( first || still || ! ( coming || going ) ) {
					return;
				}
				// Read layout so the animation starts over even with the same class.
				void item.offsetWidth;
				item.classList.add( coming ? 'is-entering' : 'is-leaving' );
			} );
		}

		// A photo pointed at (or focused): its description stays, whatever swaps.
		var pointing = false;

		function rest() {
			pointing = false;
			show( pieces[ 0 ].wpReal );
		}

		function point( e ) {
			var piece = e.target && e.target.closest ? e.target.closest( '.wp-swiper-art__piece' ) : null;
			if ( piece && ! piece.hidden ) {
				pointing = true;
				show( piece.wpReal );
			}
		}

		stage.addEventListener( 'pointerover', point );
		stage.addEventListener( 'focusin', point );
		// A touch leaves as soon as the finger lifts: its photo's stays.
		stage.addEventListener( 'pointerleave', function ( e ) {
			if ( e.pointerType !== 'touch' ) {
				rest();
			}
		} );
		stage.addEventListener( 'focusout', function ( e ) {
			if ( ! stage.contains( e.relatedTarget ) ) {
				rest();
			}
		} );

		return {
			rest: rest,
			// The middle photo was swapped: its description, unless another is pointed at.
			refresh: function () {
				if ( ! pointing ) {
					show( pieces[ 0 ].wpReal );
				}
			}
		};
	}

	/**
	 * "Open images in a lightbox": a click on a photo (or Enter / Space on it)
	 * opens the sliders' lightbox (frontend.js openLightbox()) on it, with the
	 * others in slide order beside it, their captions and, if set, their
	 * descriptions (from the block under this one).
	 *
	 * @param {Object} settings { radius, descriptions, text } (class-wp-swiper-art.php).
	 */
	function lightbox( block, stage, pieces, photos, settings ) {
		pieces.forEach( function ( piece ) {
			piece.setAttribute( 'role', 'button' );
			piece.tabIndex = 0;
		} );

		function open( piece ) {
			if ( typeof window.wpSwiperLightbox !== 'function' ) {
				return false;
			}
			var texts = {};
			var box = block.nextElementSibling;
			if ( settings.descriptions && box && box.classList.contains( 'wp-swiper-descriptions' ) ) {
				box.querySelectorAll( '.wp-swiper-descriptions__item' ).forEach( function ( item ) {
					texts[ item.getAttribute( 'data-wp-swiper-index' ) ] = item.innerHTML;
				} );
			}
			var items = [];
			photos.forEach( function ( photo, real ) {
				var src = photo.getAttribute( 'data-wp-swiper-full' );
				if ( ! src ) {
					return;
				}
				var img = photo.querySelector( 'img' );
				var caption = photo.querySelector( '.wp-swiper-art__caption-text' );
				items.push( {
					real: real,
					type: 'image',
					src: src,
					embed: null,
					poster: '',
					ratio: img && img.width && img.height ? img.width / img.height : 1,
					alt: img ? img.alt : '',
					caption: caption ? caption.innerHTML.trim() : '',
					description: texts[ real ] || '',
					thumb: ''
				} );
			} );
			var start = -1;
			items.forEach( function ( item, i ) {
				if ( item.real === piece.wpReal ) {
					start = i;
				}
			} );
			if ( start < 0 ) {
				return false;
			}
			// Photo swapping waits while it is open (as the Collage's).
			block.classList.add( 'is-viewing' );
			window.wpSwiperLightbox( items, start, function () {
				block.classList.remove( 'is-viewing' );
			}, { radius: settings.radius, thumbRadius: false, thumbs: false, text: settings.text || false } );
			return true;
		}

		function pieceOf( e ) {
			var piece = e.target.closest ? e.target.closest( '.wp-swiper-art__piece' ) : null;
			return piece && piece.parentNode === stage && ! piece.hidden ? piece : null;
		}

		stage.addEventListener( 'click', function ( e ) {
			var piece = pieceOf( e );
			if ( piece && ! e.defaultPrevented && open( piece ) ) {
				e.preventDefault();
			}
		} );
		stage.addEventListener( 'keydown', function ( e ) {
			var piece = pieceOf( e );
			if ( piece && e.target === piece && ( e.key === 'Enter' || e.key === ' ' ) && open( piece ) ) {
				e.preventDefault();
			}
		} );
	}

	// The device by the window's width, as the plugin's breakpoints.
	function device() {
		if ( window.matchMedia( '(max-width: 767px)' ).matches ) {
			return 'mobile';
		}
		return window.matchMedia( '(max-width: 1024px)' ).matches ? 'tablet' : 'desktop';
	}

	// A setting's value on this device: one per device, or (an older config) one for all.
	function forDevice( value ) {
		return value && typeof value === 'object' ? value[ device() ] : value;
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
		// Photo swapping: the photos not in a piece, in the order they come in.
		var queue = [];
		// "The middle photo": the device's order, and where in it the middle one is.
		var sequence = [];
		var middleAt = 0;

		// How many pieces this device shows (Ring: the ring's and the middle
		// one; Ring + text: the ring's).
		// Grid + circle: as many as it laid out (block.wpSwiperArtShown).
		var middlePhoto = hasMiddlePhoto( config ) ? 1 : 0;
		function shownCount() {
			if ( typeof block.wpSwiperArtShown === 'number' ) {
				return Math.max( 1, Math.min( pieces.length, block.wpSwiperArtShown ) );
			}
			// SVG shape: all its windows (the ring's number is not its).
			if ( config.shapes ) {
				return Math.max( 1, Math.min( pieces.length, config.shapes.length ) );
			}
			return Math.max( 1, Math.min( pieces.length, middlePhoto + ( Number( forDevice( config.count ) ) || pieces.length - middlePhoto ) ) );
		}

		/*
		 * The photos in the pieces, in the order of the device (the order set
		 * there, else the slides'); they repeat when there are fewer, and with
		 * more the others wait to be swapped in. A piece keeps its photo while
		 * it stays the same.
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
			order = order.map( Number );
			pieces.forEach( function ( piece, index ) {
				// One photo through all the windows: the first in each.
				var real = config.one ? order[ 0 ] : order[ index % order.length ];
				if ( ! photos[ real ] || piece.wpReal === real ) {
					return;
				}
				piece.wpReal = real;
				// Its photos (whatever was fading in), not its border.
				[].slice.call( piece.querySelectorAll( ':scope > .wp-swiper-art__photo' ) ).forEach( function ( photo ) {
					piece.removeChild( photo );
				} );
				piece.appendChild( photos[ real ].cloneNode( true ) );
			} );
			var shown = shownCount();
			queue = ! config.one && order.length > shown ? order.slice( shown ) : [];
			sequence = order;
			middleAt = 0;
			at = 0;
			// The middle photo may be another one now.
			if ( describe ) {
				describe.rest();
			}
		}

		/*
		 * Photo swapping (as the Collage's): a piece this device shows, at random
		 * (not the one before), fades to the next photo waiting; the one it had
		 * waits at the end. Faded in once its image is there (decoded), at the
		 * latest after the delay between swaps (a slow file); then the photos
		 * under it go.
		 */
		var lastSwapped = -1;

		/*
		 * SVG shape ("advance"): every window takes the photo `by` places on in
		 * the device's order, the main one the current photo; as a slider, from
		 * the last photo back to the first. (A click on any window opens its
		 * photo in the lightbox, as in the other styles.)
		 */
		var at = 0;
		function advance( by ) {
			if ( sequence.length < 2 || ! by ) {
				return;
			}
			at = ( ( at + by ) % sequence.length + sequence.length ) % sequence.length;
			var shown = shownCount();
			// Appearance: one after another over the Fade speed, once all the new
			// images are there (or late), so the wave runs evenly.
			var shares = config.appear && ! STILL ? wave( config, shown ) : null;
			var spread = shares ? fadeSpeed( block ) : 0;
			var release = null;
			var gate = shares ? new Promise( function ( resolve ) {
				release = resolve;
			} ) : null;
			var loads = [];
			for ( var k = 0; k < shown; k++ ) {
				// One photo through all the windows: the same in each.
				var real = sequence[ ( at + ( config.one ? 0 : k ) ) % sequence.length ];
				if ( pieces[ k ].wpReal !== real ) {
					loads.push( fadeTo( pieces[ k ], real, shares ? shares[ k ] * spread : 0, gate ) );
				}
			}
			if ( gate ) {
				// A slow file holds the wave GATE_WAIT ms at most.
				Promise.race( [ Promise.all( loads ), new Promise( function ( resolve ) {
					setTimeout( resolve, GATE_WAIT );
				} ) ] ).then( release );
			}
			// The next photo loaded meanwhile, so the next change is quick.
			preload( sequence[ ( at + 1 ) % sequence.length ] );
			if ( describe ) {
				describe.refresh();
			}
		}

		function swap() {
			var shown = shownCount();
			var index;
			var real;
			if ( config.swap.target === 'advance' || config.one ) {
				advance( 1 );
				return;
			}
			if ( config.swap.target === 'middle' ) {
				// The middle photo: the next of all the photos in turn (the ring as
				// it is: a photo may be in both for a while).
				if ( sequence.length < 2 ) {
					return;
				}
				middleAt = ( middleAt + 1 ) % sequence.length;
				index = 0;
				real = sequence[ middleAt ];
			} else {
				if ( ! queue.length ) {
					return;
				}
				if ( config.swap.target === 'turn' ) {
					// SVG shape, "in turn": the main window, then the next, and so on.
					index = ( lastSwapped + 1 ) % shown;
				} else {
					do {
						index = Math.floor( Math.random() * shown );
					} while ( shown > 1 && index === lastSwapped );
				}
				lastSwapped = index;
				real = queue.shift();
				queue.push( pieces[ index ].wpReal );
			}

			fadeTo( pieces[ index ], real );
			if ( describe && index === 0 ) {
				describe.refresh();
			}
		}

		// Slide `real`'s image fetched and decoded ahead (once).
		var preloaded = {};
		function preload( real ) {
			var source = photos[ real ] && photos[ real ].querySelector( 'img' );
			var src = source && ( source.currentSrc || source.getAttribute( 'src' ) );
			if ( ! src || preloaded[ src ] ) {
				return;
			}
			preloaded[ src ] = new Image();
			preloaded[ src ].decoding = 'async';
			preloaded[ src ].src = src;
		}

		/*
		 * A piece's photo fading to slide `real`'s, `wait` ms after its image
		 * is there (or `gate` lets it, all of a wave's images: advance()).
		 * Returns when its image is there.
		 */
		function fadeTo( piece, real, wait, gate ) {
			piece.wpReal = real;

			var fresh = photos[ real ].cloneNode( true );
			fresh.classList.add( 'is-entering' );
			var img = fresh.querySelector( 'img' );
			// Loaded now, not when "lazy" gets to it (it is about to show).
			if ( img ) {
				img.removeAttribute( 'loading' );
			}
			piece.appendChild( fresh );

			var decoded = img && img.decode ? img.decode().catch( function () {} ) : Promise.resolve();
			var late = new Promise( function ( resolve ) {
				setTimeout( resolve, Math.max( 500, Number( config.swap.delay ) || 3000 ) );
			} );
			Promise.race( [ gate || decoded, late ] ).then( function () {
				setTimeout( function () {
					requestAnimationFrame( function () {
						fresh.classList.remove( 'is-entering' );
					} );
					var speed = parseFloat( getComputedStyle( fresh ).transitionDuration ) * 1000 || 0;
					// Then the photos under it go; not one put over it since (a
					// later change still on its way: it shows once it is there).
					setTimeout( function () {
						if ( fresh.parentNode !== piece ) {
							return;
						}
						var under = true;
						[].slice.call( piece.querySelectorAll( ':scope > .wp-swiper-art__photo' ) ).forEach( function ( photo ) {
							if ( photo === fresh ) {
								under = false;
							} else if ( under ) {
								piece.removeChild( photo );
							}
						} );
					}, speed + 100 );
				}, wait || 0 );
			} );
			return decoded;
		}

		if ( config.swap ) {
			var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			var paused = false;
			var inView = true;
			var schedule = function () {
				setTimeout( function () {
					// Not while paused, off screen, in a hidden tab, in the lightbox,
					// or while a photo is dragged in the preview: try again later.
					if ( ! paused && inView && ! document.hidden && ! block.classList.contains( 'is-viewing' ) && ! stage.querySelector( '.is-source' ) ) {
						swap();
					}
					schedule();
				}, Math.max( 500, Number( config.swap.delay ) || 3000 ) );
			};
			if ( config.swap.pause ) {
				block.addEventListener( 'mouseenter', function () {
					paused = true;
				} );
				block.addEventListener( 'mouseleave', function () {
					paused = false;
				} );
			}
			if ( 'IntersectionObserver' in window ) {
				new IntersectionObserver( function ( entries ) {
					inView = entries[ 0 ].isIntersecting;
				} ).observe( block );
			}
			if ( ! calm ) {
				schedule();
			}
		}

		var describe = descriptions( block, stage, pieces );

		if ( config.lightbox ) {
			lightbox( block, stage, pieces, photos, config.lightbox );
		}
		/*
		 * Height % or "Auto" on this device (px and vh: art.css). Auto: as a
		 * slider's (frontend.js fillHeight()): with the stage collapsed the
		 * column keeps the height its row gives it; the stage is made that tall,
		 * and whatever the column grew beyond it (its padding, the descriptions,
		 * other content) is taken back off. %: the outer block is that share of
		 * its parent (art.css), the stage as tall as the block. A ring or shape
		 * gets that height by its width (its proportions, no wider than the
		 * column); a grid by its own. Too little height (a column of its own,
		 * a parent with none): a ring or shape as wide as the column, a grid
		 * GRID_FALLBACK px.
		 */
		var column = block.closest( '.wpb_column, .vc_column_container' ) || block.parentElement;
		var fitted = config.height && Object.keys( config.height ).some( function ( name ) {
			return '%' === config.height[ name ] || 'auto' === config.height[ name ];
		} );
		function fit() {
			var unit = forDevice( config.height );
			var wide = !! config.wide;
			var side = wide ? 'height' : 'width';
			if ( ( 'auto' !== unit && '%' !== unit ) || ! column ) {
				stage.style[ side ] = '';
				return;
			}
			var free;
			if ( '%' === unit ) {
				stage.style[ side ] = '';
				free = Math.floor( block.clientHeight );
			} else {
				stage.style[ side ] = '0px';
				var collapsed = column.getBoundingClientRect().height;
				stage.style[ side ] = collapsed + 'px';
				free = Math.floor( collapsed - ( column.getBoundingClientRect().height - collapsed ) );
			}
			if ( wide ) {
				stage.style.height = ( free >= FIT_MIN ? free : GRID_FALLBACK ) + 'px';
				return;
			}
			// As tall as that: square, or (SVG shape) its proportions.
			var ratio = Number( config.ratio ) || 1;
			stage.style.width = free >= FIT_MIN ? 'min(100%, ' + Math.floor( free * ratio ) + 'px)' : '';
		}

		var width = 0;
		var height = 0;
		var drawnFor = null;
		var redraw = function () {
			// SVG shape, another file on tablet or mobile: the device's windows
			// and proportions (the stage's are art.css's), before the photos go in.
			var own = config.shapeDevices && config.shapeDevices[ device() ];
			if ( own && config.shapes !== own.shapes ) {
				config.shapes = own.shapes;
				config.ratio = own.ratio;
			}
			fill( false );
			if ( fitted ) {
				fit();
			}
			// Again at another size, or on another device (its own values).
			if ( stage.clientWidth !== width || stage.clientHeight !== height || current !== drawnFor ) {
				width = stage.clientWidth;
				height = stage.clientHeight;
				drawnFor = current;
				draw( block, stage, config );
				// Appearance: the first show in the wave's order too (each piece
				// fades in after its share of the Fade speed).
				if ( config.appear && ! STILL && ! block.classList.contains( 'is-ready' ) ) {
					var first = wave( config, pieces.length );
					var over = fadeSpeed( block );
					pieces.forEach( function ( piece, k ) {
						piece.style.transitionDelay = Math.round( first[ k ] * over ) + 'ms';
					} );
					setTimeout( function () {
						pieces.forEach( function ( piece ) {
							piece.style.transitionDelay = '';
						} );
					}, over + 1000 );
				}
				block.classList.add( 'is-ready' );
				// Grid + circle: another number of cells shown, the photos waiting again.
				if ( block.wpSwiperArtShown !== shownFor ) {
					shownFor = block.wpSwiperArtShown;
					fill( true );
				}
			}
		};
		var shownFor = block.wpSwiperArtShown;
		redraw();
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( redraw ).observe( stage );
			// "Auto": the neighbour columns' content sets the row's height (their
			// images load, their text wraps). Their content, not the row: once
			// this is the tallest in the row, the row would not shrink with them.
			// %: the block's own height (its parent's changes it).
			if ( fitted ) {
				new ResizeObserver( redraw ).observe( block );
			}
			if ( fitted && column && column.parentElement ) {
				var watch = new ResizeObserver( redraw );
				[].forEach.call( column.parentElement.children, function ( sibling ) {
					if ( sibling !== column ) {
						watch.observe( sibling.firstElementChild || sibling );
					}
				} );
			}
		}
		window.addEventListener( 'resize', redraw );
		if ( fitted ) {
			window.addEventListener( 'load', redraw );
		}

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
				// Grid + circle: a larger circle may leave out more cells.
				if ( block.wpSwiperArtShown !== shownFor ) {
					shownFor = block.wpSwiperArtShown;
					fill( true );
				}
			},
			// Grid shapes: the layout as drawn (numbered), and a new one drawn at
			// once, with more pieces where it has more shapes than the markup.
			grid: function () {
				return config.grid || null;
			},
			setGrid: function ( grid ) {
				if ( ! config.grid || ! grid ) {
					return;
				}
				config.grid = grid;
				var need = 0;
				grid.cells.forEach( function ( cell ) {
					need = Math.max( need, cell + 1 );
				} );
				while ( pieces.length < need ) {
					var piece = document.createElement( 'div' );
					piece.className = 'wp-swiper-art__piece';
					piece.setAttribute( 'data-piece', pieces.length );
					stage.appendChild( piece );
					pieces.push( piece );
				}
				draw( block, stage, config );
				shownFor = block.wpSwiperArtShown;
				fill( true );
			},
			ids: config.ids || []
		};
	}

	/*
	 * "Use as background" (as frontend.js background() does for a slider):
	 * the block with the class given, the nearest around it, takes it as its
	 * first child (art.css: behind its content, filling it). None there: it
	 * stays as it is, and those who can fix it see why (its notice after it).
	 */
	function background( block ) {
		if ( ! block.classList.contains( 'wp-swiper-art--background' ) ) {
			return;
		}
		var classes = ( block.getAttribute( 'data-wp-swiper-bg' ) || '' ).split( /\s+/ ).filter( Boolean );
		var host = null;
		if ( classes.length && block.parentElement ) {
			try {
				host = block.parentElement.closest( classes.map( function ( name ) {
					return '.' + ( window.CSS && CSS.escape ? CSS.escape( name ) : name );
				} ).join( '' ) );
			} catch ( e ) {}
		}
		var notice = block.nextElementSibling;
		while ( notice && ! notice.hasAttribute( 'data-wp-swiper-bg-missing' ) && notice.classList.contains( 'wp-swiper-descriptions' ) ) {
			notice = notice.nextElementSibling;
		}
		if ( ! host ) {
			block.classList.remove( 'wp-swiper-art--background' );
			if ( notice && notice.hasAttribute( 'data-wp-swiper-bg-missing' ) ) {
				notice.hidden = false;
			}
			return;
		}
		host.classList.add( 'wp-swiper-bg-host' );
		// Its own position (absolute, sticky...) is kept: any but static holds it.
		if ( getComputedStyle( host ).position === 'static' ) {
			host.classList.add( 'wp-swiper-bg-host--static' );
		}
		host.insertBefore( block, host.firstChild );
	}

	function init() {
		[].forEach.call( document.querySelectorAll( '.wp-swiper-art[data-wp-swiper-art]' ), function ( block ) {
			if ( ! block.wpSwiperArt ) {
				background( block );
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
