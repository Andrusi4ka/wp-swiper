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
 * A photo's caption sits in the middle of its shape; the slides'
 * descriptions under the block show the one of the photo under the pointer
 * (or touched, or focused), else the middle photo's.
 */
( function () {
	'use strict';

	var SAMPLES = 24; // Points along an arc to find a piece's box.
	var FIT_MIN = 120; // Size "Auto": less free height than this, the column's width instead.

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
		var count = Math.max( 1, Math.min( pieces.length - 1, Number( forDevice( config.count ) ) || pieces.length - 1 ) );
		var round = Number( forDevice( config.radius ) ) || 0;
		var opening = ( Number( forDevice( config.opening ) ) || 0 ) * Math.PI / 180;

		// The pieces another device shows more of: none here.
		for ( var extra = count + 1; extra < pieces.length; extra++ ) {
			pieces[ extra ].hidden = true;
		}

		// For the preview (preview-art.js): where the middle circle's edge is.
		block.wpSwiperArtRing = { cx: cx, cy: cy, outer: outer, middle: middle, gap: gap };

		// The image or text in the opening.
		openingContent( block, stage, config, { cx: cx, cy: cy, outer: outer, middle: middle, gap: gap, opening: opening } );

		// The middle circle.
		place( pieces[ 0 ], { left: cx - middle, top: cy - middle, width: middle * 2, height: middle * 2 } );
		pieces[ 0 ].classList.add( 'wp-swiper-art__piece--circle' );
		captionAt( pieces[ 0 ], { x: middle, y: middle, width: middle * 2 * 0.85 } );
		outline( block, pieces[ 0 ], 'M 0 ' + middle + ' A ' + middle + ' ' + middle + ' 0 1 1 ' + ( middle * 2 ) + ' ' + middle + ' A ' + middle + ' ' + middle + ' 0 1 1 0 ' + middle + ' Z' );

		// The ring, clockwise from the opening's left edge (the opening is at the
		// bottom, a quarter turn from the right).
		var first = Math.PI / 2 + opening / 2;
		var step = ( 2 * Math.PI - opening ) / count;
		for ( var i = 0; i < count; i++ ) {
			var shape = ringPiece( cx, cy, inner, outer, first + i * step, first + ( i + 1 ) * step, gap, round );
			var piece = pieces[ i + 1 ];
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
	 * Ring: the image or text in its opening (class-wp-swiper-art.php
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
	}

	var STYLES = { ring: ring };

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

		// How many pieces this device shows (Ring: the ring's and the middle one).
		function shownCount() {
			return Math.max( 1, Math.min( pieces.length, 1 + ( Number( forDevice( config.count ) ) || pieces.length - 1 ) ) );
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
				var real = order[ index % order.length ];
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
			queue = order.length > shown ? order.slice( shown ) : [];
			sequence = order;
			middleAt = 0;
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
		function swap() {
			var shown = shownCount();
			var index;
			var real;
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
				do {
					index = Math.floor( Math.random() * shown );
				} while ( shown > 1 && index === lastSwapped );
				lastSwapped = index;
				real = queue.shift();
				queue.push( pieces[ index ].wpReal );
			}

			var piece = pieces[ index ];
			piece.wpReal = real;

			var fresh = photos[ real ].cloneNode( true );
			fresh.classList.add( 'is-entering' );
			piece.appendChild( fresh );
			if ( describe && index === 0 ) {
				describe.refresh();
			}

			var img = fresh.querySelector( 'img' );
			var decoded = img && img.decode ? img.decode().catch( function () {} ) : Promise.resolve();
			var late = new Promise( function ( resolve ) {
				setTimeout( resolve, Math.max( 500, Number( config.swap.delay ) || 3000 ) );
			} );
			Promise.race( [ decoded, late ] ).then( function () {
				requestAnimationFrame( function () {
					fresh.classList.remove( 'is-entering' );
				} );
				var speed = parseFloat( getComputedStyle( fresh ).transitionDuration ) * 1000 || 0;
				setTimeout( function () {
					[].slice.call( piece.querySelectorAll( ':scope > .wp-swiper-art__photo' ) ).forEach( function ( photo ) {
						if ( photo !== fresh && fresh.parentNode === piece ) {
							piece.removeChild( photo );
						}
					} );
				}, speed + 100 );
			} );
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
		 * Size "Auto" on this device: the stage as large as the column's free
		 * height allows (and no wider than the column, art.css). As a slider's
		 * Height "Auto" (frontend.js fillHeight()): with the stage collapsed the
		 * column keeps the height its row gives it; the stage is made that tall,
		 * and whatever the column grew beyond it (its padding, the descriptions,
		 * other content) is taken back off. Too little free height (a column of
		 * its own, stacked columns on phones): the column's width.
		 */
		var column = block.closest( '.wpb_column, .vc_column_container' ) || block.parentElement;
		var autoSize = config.auto && Object.keys( config.auto ).some( function ( name ) {
			return config.auto[ name ];
		} );
		function fit() {
			if ( ! forDevice( config.auto ) || ! column ) {
				stage.style.width = '';
				return;
			}
			stage.style.width = '0px';
			var collapsed = column.getBoundingClientRect().height;
			stage.style.width = collapsed + 'px';
			var free = Math.floor( collapsed - ( column.getBoundingClientRect().height - collapsed ) );
			stage.style.width = free >= FIT_MIN ? 'min(100%, ' + free + 'px)' : '';
		}

		var width = 0;
		var drawnFor = null;
		var redraw = function () {
			fill( false );
			if ( autoSize ) {
				fit();
			}
			// Again at another width, or on another device (its own values).
			if ( stage.clientWidth !== width || current !== drawnFor ) {
				width = stage.clientWidth;
				drawnFor = current;
				draw( block, stage, config );
				block.classList.add( 'is-ready' );
			}
		};
		redraw();
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( redraw ).observe( stage );
			// "Auto": the neighbour columns' content sets the row's height (their
			// images load, their text wraps). Their content, not the row: once
			// this is the tallest in the row, the row would not shrink with them.
			if ( autoSize && column && column.parentElement ) {
				var watch = new ResizeObserver( redraw );
				[].forEach.call( column.parentElement.children, function ( sibling ) {
					if ( sibling !== column ) {
						watch.observe( sibling.firstElementChild || sibling );
					}
				} );
			}
		}
		window.addEventListener( 'resize', redraw );
		if ( autoSize ) {
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
