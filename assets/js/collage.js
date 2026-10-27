/**
 * WP Swiper: the Collage effect (no slider: the photos sit together in
 * places). Loaded after frontend.js, only where a collage is shown
 * (render.php); frontend.js init() lays out each collage with it.
 */
( function () {
	'use strict';

	// frontend.js's own, shared with this file (window.wpSwiperShared).
	var shared = window.wpSwiperShared;
	if ( ! shared ) {
		return;
	}
	var device = shared.device;
	var fills = shared.fills;
	var fillHeight = shared.fillHeight;
	var watchNeighbours = shared.watchNeighbours;
	var lightboxItem = shared.lightboxItem;
	var openLightbox = shared.openLightbox;
	var posterFallbacks = shared.posterFallbacks;
	var LIGHTBOX_SHOWN = shared.LIGHTBOX_SHOWN;

	/* ------------------------------------------------------------------ *
	 * Collage effect
	 *
	 * Not a slider: the photos sit together in places of a template, of a
	 * grid, or arranged by their proportions (Justified rows, Masonry). The
	 * order is the one set for the device (dragged in the preview, or
	 * shuffled), else the saved shuffle (the same number, the same order),
	 * so the arrangement does not change from visit to visit. With more
	 * photos than places, every so often a place at random fades to a photo
	 * not shown yet; the places keep their size, the photo is cropped to fit.
	 * ------------------------------------------------------------------ */

	// The templates: their places as cuts, the same as a Random mosaic's
	// (parseMosaic()), the big one first: "V" side by side, "H" one over the
	// other, the share of the first part. Laid out as a mosaic is, so the
	// lines between the places resize them in the preview (collage_cuts).
	var COLLAGE_TEMPLATES = {
		// A big photo on the left (2/3), two on the right.
		'feature-left': 'V0.6667[0,H0.5[1,2]]',
		// Two on the left, a big one in the middle (half), two on the right.
		'feature-center': 'V0.25[H0.5[1,2],V0.6667[0,H0.5[3,4]]]',
		// A wide one (2/3) and one on top, three under them.
		mosaic: 'H0.5[V0.6667[0,1],V0.3333[2,V0.5[3,4]]]',
		// A wide one on top (3/5 of the height), three under it.
		banner: 'H0.6[0,V0.3333[1,V0.5[2,3]]]'
	};

	// A small seeded random generator (mulberry32): the same seed, the same numbers.
	function seededRandom( seed ) {
		var state = seed >>> 0;
		return function () {
			state = ( state + 0x6d2b79f5 ) >>> 0;
			var t = state;
			t = Math.imul( t ^ ( t >>> 15 ), t | 1 );
			t ^= t + Math.imul( t ^ ( t >>> 7 ), t | 61 );
			return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
		};
	}

	// 0 .. count - 1 shuffled by the seed.
	function seededOrder( count, seed ) {
		var random = seededRandom( seed );
		var order = [];
		for ( var i = 0; i < count; i++ ) {
			order.push( i );
		}
		for ( var j = count - 1; j > 0; j-- ) {
			var k = Math.floor( random() * ( j + 1 ) );
			var swap = order[ j ];
			order[ j ] = order[ k ];
			order[ k ] = swap;
		}
		return order;
	}

	// Random mosaic: how many mosaics are tried for one shuffle (the best kept).
	var MOSAIC_TRIES = 60;

	/**
	 * Random mosaic: the block cut in two, each part again, until there is a
	 * place for every photo; flush on all four sides. A cut mostly runs
	 * across the part's longer side, at a random place among its photos and
	 * where they would fit side by side (or stacked) at their own
	 * proportions, give or take. Of MOSAIC_TRIES such mosaics (all from the
	 * shuffle, so the same each time) the one that crops its photos least
	 * is kept, with no sliver and no place far smaller than the others. The
	 * cuts are kept as shares, so a resize keeps the mosaic.
	 *
	 * @param {number[]} ratios Width / height of each place's photo, in order.
	 * @param {number}   aspect Width / height of the block.
	 * @param {Function} random Seeded random numbers.
	 * @return {Object} { leaf: index } or { vertical, share, a, b }.
	 */
	function mosaicTree( ratios, aspect, random ) {
		var count = ratios.length;

		function cut( list, shape ) {
			if ( list.length === 1 ) {
				return { leaf: list[ 0 ] };
			}
			var split = 1 + Math.floor( random() * ( list.length - 1 ) );
			var a = list.slice( 0, split );
			var b = list.slice( split );
			var vertical = shape >= 1 ? random() < 0.8 : random() < 0.2;
			// Side by side the photos share the width (their widths at one
			// height add up), stacked the height (their heights at one width).
			var weight = function ( group ) {
				return group.reduce( function ( sum, index ) {
					return sum + ( vertical ? ratios[ index ] : 1 / ratios[ index ] );
				}, 0 );
			};
			var share = weight( a ) / ( weight( a ) + weight( b ) ) * ( 0.85 + random() * 0.3 );
			share = Math.max( 0.2, Math.min( 0.8, share ) );
			return {
				vertical: vertical,
				share: share,
				a: cut( a, vertical ? shape * share : shape / share ),
				b: cut( b, vertical ? shape * ( 1 - share ) : shape / ( 1 - share ) )
			};
		}

		// What a mosaic costs: each photo's crop (log of how far its place's
		// shape is from its own), slivers (over 3:1) and tiny places dearer.
		function cost( node, shape, area ) {
			if ( undefined !== node.leaf ) {
				var crop = Math.abs( Math.log( shape / ratios[ node.leaf ] ) );
				var sliver = Math.max( 0, Math.abs( Math.log( shape ) ) - Math.log( 3 ) );
				var tiny = area < 0.35 / count ? 1 : 0;
				return crop + 3 * sliver + tiny;
			}
			var s = node.share;
			return node.vertical
				? cost( node.a, shape * s, area * s ) + cost( node.b, shape * ( 1 - s ), area * ( 1 - s ) )
				: cost( node.a, shape / s, area * s ) + cost( node.b, shape / ( 1 - s ), area * ( 1 - s ) );
		}

		var all = ratios.map( function ( r, index ) {
			return index;
		} );
		var best = null;
		var bestCost = Infinity;
		for ( var i = 0; i < MOSAIC_TRIES; i++ ) {
			var tree = cut( all, aspect );
			var price = cost( tree, aspect, 1 );
			if ( price < bestCost ) {
				best = tree;
				bestCost = price;
			}
		}
		return best;
	}

	/**
	 * Random mosaic cuts as stored (WP_Swiper_Settings::sanitize_mosaic()):
	 * "V0.6[0,H0.5[1,2]]" to a tree as mosaicTree() makes, and back.
	 *
	 * @param {string} text The cuts.
	 * @return {Object|null} The tree; null when it is not one.
	 */
	function parseMosaic( text ) {
		var at = 0;
		text = String( text || '' );
		function node() {
			var leaf = /^\d+/.exec( text.slice( at ) );
			if ( leaf ) {
				at += leaf[ 0 ].length;
				return { leaf: Number( leaf[ 0 ] ) };
			}
			var cut = /^([VH])(\d*\.?\d+)\[/.exec( text.slice( at ) );
			if ( ! cut ) {
				return null;
			}
			at += cut[ 0 ].length;
			var a = node();
			if ( ! a || text.charAt( at ) !== ',' ) {
				return null;
			}
			at++;
			var b = node();
			if ( ! b || text.charAt( at ) !== ']' ) {
				return null;
			}
			at++;
			return { vertical: cut[ 1 ] === 'V', share: Number( cut[ 2 ] ), a: a, b: b };
		}
		var tree = text ? node() : null;
		return tree && at === text.length ? tree : null;
	}

	function serializeMosaic( node ) {
		if ( undefined !== node.leaf ) {
			return String( node.leaf );
		}
		return ( node.vertical ? 'V' : 'H' ) + Math.round( node.share * 10000 ) / 10000 + '[' + serializeMosaic( node.a ) + ',' + serializeMosaic( node.b ) + ']';
	}

	// The places of a mosaic, each once: its leaves, sorted.
	function mosaicLeaves( node, list ) {
		list = list || [];
		if ( undefined !== node.leaf ) {
			list.push( node.leaf );
		} else {
			mosaicLeaves( node.a, list );
			mosaicLeaves( node.b, list );
		}
		return list;
	}

	/*
	 * Scattered: a photo in the middle and four arms of photos around it,
	 * turning like a pinwheel. Each arm keeps to its own quarter: above the
	 * middle photo from its left edge on (T), right of it from its top edge
	 * down (R), below it from its right edge back (B), left of it from its
	 * bottom edge up (L). An arm's first photo touches the middle one, a gap
	 * between them; each next one touches the one before, either along the
	 * arm ("a": right, down, left, up) or outwards ("o": up, right, down,
	 * left), lined up with it on the side towards the middle. So photos never
	 * overlap and neighbours are always a gap apart, whatever their sizes,
	 * while the outline stays uneven, its corners empty.
	 *
	 * The layout: { middle: { w, h }, arms: [ T, R, B, L ] }, each arm a list
	 * of { w, h, step } (step: "a" or "o"; none for the first), sizes in no
	 * unit: the whole is scaled to its block. The places in order: the
	 * middle one, then T's, R's, B's and L's.
	 */
	var SCATTER_ARMS = 'TRBL';
	// Scattered: how many layouts are tried for one shuffle (the one filling its block most kept).
	var SCATTER_TRIES = 80;

	// The layout's places in order (the objects themselves: resizing one resizes the layout).
	function scatterPlaces( layout ) {
		var list = [ layout.middle ];
		layout.arms.forEach( function ( arm ) {
			list = list.concat( arm );
		} );
		return list;
	}

	/**
	 * The places' rectangles at a scale (px per unit), a gap apart, the
	 * middle photo's top left corner at 0, 0. Each with its arm (-1: the
	 * middle) and the edges it is resized by: those not lined up with its
	 * neighbour (the others stay where they are as it changes size).
	 *
	 * @param {Object} layout See above.
	 * @param {number} scale  px per unit.
	 * @param {number} gap    px.
	 * @return {Object[]} { x, y, w, h, arm, edges: [ horizontal edge, vertical edge ] }
	 */
	function scatterRects( layout, scale, gap ) {
		// The edges each arm grows from (the middle: right and down, as it is placed from its top left).
		var EDGES = [ [ 'right', 'top' ], [ 'right', 'bottom' ], [ 'left', 'bottom' ], [ 'left', 'top' ] ];
		var mid = { x: 0, y: 0, w: layout.middle.w * scale, h: layout.middle.h * scale, arm: -1, edges: [ 'right', 'bottom' ] };
		var rects = [ mid ];
		layout.arms.forEach( function ( arm, a ) {
			var prev = null;
			arm.forEach( function ( place ) {
				var w = place.w * scale;
				var h = place.h * scale;
				var along = place.step !== 'o';
				var x;
				var y;
				if ( ! prev ) {
					// Next to the middle photo, lined up with its edge where the arm starts.
					x = [ mid.x, mid.x + mid.w + gap, mid.x + mid.w - w, mid.x - gap - w ][ a ];
					y = [ mid.y - gap - h, mid.y, mid.y + mid.h + gap, mid.y + mid.h - h ][ a ];
				} else if ( a === 0 ) {
					x = along ? prev.x + prev.w + gap : prev.x;
					y = along ? prev.y + prev.h - h : prev.y - gap - h;
				} else if ( a === 1 ) {
					x = along ? prev.x : prev.x + prev.w + gap;
					y = along ? prev.y + prev.h + gap : prev.y;
				} else if ( a === 2 ) {
					x = along ? prev.x - gap - w : prev.x + prev.w - w;
					y = along ? prev.y : prev.y + prev.h + gap;
				} else {
					x = along ? prev.x + prev.w - w : prev.x - gap - w;
					y = along ? prev.y - gap - h : prev.y + prev.h - h;
				}
				prev = { x: x, y: y, w: w, h: h, arm: a, edges: EDGES[ a ] };
				rects.push( prev );
			} );
		} );
		return rects;
	}

	// The rectangles' bounds: { left, top, width, height }.
	function scatterBox( rects ) {
		var left = Infinity;
		var top = Infinity;
		var right = -Infinity;
		var bottom = -Infinity;
		rects.forEach( function ( r ) {
			left = Math.min( left, r.x );
			top = Math.min( top, r.y );
			right = Math.max( right, r.x + r.w );
			bottom = Math.max( bottom, r.y + r.h );
		} );
		return { left: left, top: top, width: right - left, height: bottom - top };
	}

	/**
	 * The largest scale at which the layout fits the block, the gaps kept
	 * as they are (they do not scale).
	 *
	 * @param {Object} layout See above.
	 * @param {number} width  The block's, px.
	 * @param {number} height The block's, px.
	 * @param {number} gap    px.
	 * @return {number} px per unit.
	 */
	function scatterFit( layout, width, height, gap ) {
		var low = 0;
		var high = Math.max( width, height, 1 );
		for ( var i = 0; i < 32; i++ ) {
			var scale = ( low + high ) / 2;
			var box = scatterBox( scatterRects( layout, scale, gap ) );
			if ( box.width <= width && box.height <= height ) {
				low = scale;
			} else {
				high = scale;
			}
		}
		return Math.max( low, 0.0001 );
	}

	/**
	 * A layout for these photos: the first in the middle (larger), the others
	 * dealt out to the arms, each place shaped after its photo, give or take,
	 * and a little larger or smaller than the others. Of SCATTER_TRIES such
	 * layouts (all from the shuffle, so the same each time) the one whose
	 * photos cover most of the block is kept.
	 *
	 * @param {number[]} ratios Width / height of each place's photo, in order.
	 * @param {number}   width  The block's, px.
	 * @param {number}   height The block's, px.
	 * @param {number}   gap    px.
	 * @param {Function} random Seeded random numbers.
	 * @return {Object} The layout.
	 */
	function scatterLayout( ratios, width, height, gap, random ) {
		function shape( ratio, area ) {
			var r = Math.max( 0.4, Math.min( 2.5, ( ratio || 1 ) * ( 0.9 + random() * 0.2 ) ) );
			return { w: Math.sqrt( area * r ), h: Math.sqrt( area / r ) };
		}

		var others = ratios.length - 1;
		var best = null;
		var bestScore = -Infinity;
		for ( var t = 0; t < SCATTER_TRIES; t++ ) {
			// How many photos each arm gets: as many each (fewer than four: one
			// each to some), the rest to arms at random; now and then one moved
			// to another arm, so the arms are a little uneven.
			var counts = [ 0, 0, 0, 0 ];
			var arms = seededOrder( 4, Math.floor( random() * 4294967296 ) );
			for ( var i = 0; i < others; i++ ) {
				counts[ arms[ i % 4 ] ]++;
			}
			if ( others > 4 && random() < 0.4 ) {
				var from = arms[ Math.floor( random() * 4 ) ];
				if ( counts[ from ] > 1 ) {
					counts[ from ]--;
					counts[ arms[ Math.floor( random() * 4 ) ] ]++;
				}
			}
			var layout = { middle: shape( ratios[ 0 ], 1.5 + random() * 0.6 ), arms: [ [], [], [], [] ] };
			var index = 1;
			counts.forEach( function ( count, a ) {
				for ( var k = 0; k < count; k++ ) {
					var place = shape( ratios[ index++ ], 0.6 + random() * 0.65 );
					if ( k ) {
						place.step = random() < 0.55 ? 'a' : 'o';
					}
					layout.arms[ a ].push( place );
				}
			} );

			// How good it is: the share of the block its photos cover, less how
			// far the middle photo is from the block's middle.
			var scale = scatterFit( layout, width, height, gap );
			var rects = scatterRects( layout, scale, gap );
			var box = scatterBox( rects );
			var cover = rects.reduce( function ( sum, r ) {
				return sum + r.w * r.h;
			}, 0 ) / ( width * height );
			var offX = Math.abs( rects[ 0 ].x + rects[ 0 ].w / 2 - box.left - box.width / 2 ) / width;
			var offY = Math.abs( rects[ 0 ].y + rects[ 0 ].h / 2 - box.top - box.height / 2 ) / height;
			var score = cover - 0.8 * ( offX + offY );
			if ( score > bestScore ) {
				best = layout;
				bestScore = score;
			}
		}
		return best;
	}

	/**
	 * A Scattered layout as stored (WP_Swiper_Settings::sanitize_scatter()):
	 * "1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8;L0.85x0.5", and back.
	 *
	 * @param {string} text The layout.
	 * @return {Object|null} The layout; null when it is not one.
	 */
	function parseScatter( text ) {
		var size = function ( part ) {
			var match = /^(\d*\.?\d+)x(\d*\.?\d+)$/.exec( part );
			return match && Number( match[ 1 ] ) > 0 && Number( match[ 2 ] ) > 0 ? { w: Number( match[ 1 ] ), h: Number( match[ 2 ] ) } : null;
		};
		var groups = String( text || '' ).split( ';' );
		var middle = size( groups.shift() );
		if ( ! middle ) {
			return null;
		}
		var layout = { middle: middle, arms: [ [], [], [], [] ] };
		var last = -1;
		for ( var g = 0; g < groups.length; g++ ) {
			var a = SCATTER_ARMS.indexOf( groups[ g ].charAt( 0 ) );
			if ( a <= last ) {
				return null;
			}
			last = a;
			var steps = groups[ g ].slice( 1 ).split( ',' );
			for ( var k = 0; k < steps.length; k++ ) {
				var step = k ? steps[ k ].charAt( 0 ) : '';
				var place = size( k ? steps[ k ].slice( 1 ) : steps[ k ] );
				if ( ! place || ( k && step !== 'a' && step !== 'o' ) ) {
					return null;
				}
				if ( k ) {
					place.step = step;
				}
				layout.arms[ a ].push( place );
			}
		}
		return layout;
	}

	function serializeScatter( layout ) {
		var number = function ( value ) {
			return Math.round( Math.max( 0.05, Math.min( 20, value ) ) * 10000 ) / 10000;
		};
		var size = function ( place ) {
			return number( place.w ) + 'x' + number( place.h );
		};
		var parts = [ size( layout.middle ) ];
		layout.arms.forEach( function ( arm, a ) {
			if ( arm.length ) {
				parts.push( SCATTER_ARMS.charAt( a ) + arm.map( function ( place, k ) {
					return ( k ? place.step : '' ) + size( place );
				} ).join( ',' ) );
			}
		} );
		return parts.join( ';' );
	}

	/**
	 * @param {Element} el     The .wp-swiper-collage block.
	 * @param {Object}  config { layout, count, columns, rowHeight, gap: per
	 *                         device; seed; swap: false or { delay, pause };
	 *                         fill; lightbox: false or { radius } }.
	 */
	function collage( el, config ) {
		var grid = el.querySelector( '.wp-swiper-collage__grid' );
		var pool = el.querySelector( 'template.wp-swiper-collage__pool' );
		var slides = pool ? [].slice.call( pool.content.children ) : [];
		var order = [];
		var cells = [];
		var queue = [];
		var tree = null;
		var current = null;
		var lastSwapped = -1;
		var timer = null;
		var paused = false;
		var inView = true;
		var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var root = el.parentNode;
		// Height "Auto" in a column (on the devices that have it, fills()): the
		// collage fills the column's height, like a slider (fillHeight()); not a
		// background (it fills its block anyway).
		var stretchy = !! config.fill && ! root.classList.contains( 'wp-swiper--background' );
		var stretched = false;
		// Random mosaic and the templates: their cuts as last laid out (place()),
		// for the preview.
		var mosaicLines = [];
		// Scattered: its layout (scatterLayout()), whether it is the one set in
		// the preview, the block's proportions it was worked out for, and its
		// places as last laid out (place(): the scale, where the middle photo's
		// corner is, the rectangles in grid px). Held at a scale and spot while
		// a place is resized in the preview (frozen).
		var scatter = null;
		var scatterSet = false;
		var scatterAspect = 0;
		var scatterView = null;
		var frozen = null;
		// Called once the places are laid out (the preview draws the lines then).
		var placed = [];

		if ( ! slides.length ) {
			return;
		}

		/**
		 * Height "Auto": as tall as the free height of the column (the templates,
		 * Grid and Random mosaic). Justified rows and Masonry stay as tall as
		 * their photos make them. Too little free height (stacked columns on
		 * phones): the fixed proportions (.wp-swiper-collage__grid--ratio).
		 *
		 * @return {boolean} The height changed.
		 */
		function stretch() {
			var before = root.offsetHeight;
			var layout = config.layout[ device() ] || 'grid';
			if ( stretchy && fills( config ) && layout !== 'justified' && layout !== 'masonry' ) {
				fillHeight( root, false, 0 );
				stretched = !! root.style.height;
			} else {
				root.style.height = '';
				stretched = false;
			}
			el.classList.toggle( 'wp-swiper-collage--stretch', stretched );
			grid.classList.toggle( 'wp-swiper-collage__grid--stretch', stretched );
			return Math.abs( root.offsetHeight - before ) > 1;
		}

		// Random mosaic: its cuts for the block's proportions, from the shuffle
		// too (and the number of places).
		function cut() {
			// The cuts set in the preview for this device, while they have a
			// place for every place shown (each once); else worked out.
			var stored = config.mosaic && config.mosaic[ current ] ? parseMosaic( config.mosaic[ current ] ) : null;
			if ( stored ) {
				var leaves = mosaicLeaves( stored ).sort( function ( x, y ) {
					return x - y;
				} );
				if ( leaves.length === cells.length && leaves.every( function ( leaf, i ) {
					return leaf === i;
				} ) ) {
					tree = stored;
					return;
				}
			}
			var aspect = grid.clientWidth / ( grid.clientHeight || 1 ) || 1.5;
			tree = mosaicTree( cells.map( function ( cell ) {
				return cell.wpRatio;
			} ), aspect, seededRandom( ( config.seed || 1 ) * 7919 + cells.length ) );
		}

		// A template's cuts: the ones set in the preview for this device, while
		// they are this template's and have its places; else its own.
		function templateTree( layout ) {
			var own = parseMosaic( COLLAGE_TEMPLATES[ layout ] );
			var stored = config.cuts && config.cuts[ current ];
			var prefix = layout + ':';
			var set = stored && stored.indexOf( prefix ) === 0 ? parseMosaic( stored.slice( prefix.length ) ) : null;
			if ( set && mosaicLeaves( set ).sort().join( ',' ) === mosaicLeaves( own ).sort().join( ',' ) ) {
				return set;
			}
			return own;
		}

		// Scattered: the layout set in the preview for this device, while it
		// has a place for every place shown; else worked out for the block's
		// proportions (from the shuffle too, and the number of places).
		function scatterCut() {
			var stored = config.cuts && config.cuts[ current ];
			var set = stored && stored.indexOf( 'scatter:' ) === 0 ? parseScatter( stored.slice( 8 ) ) : null;
			var width = grid.clientWidth || 1;
			var height = grid.clientHeight || 1;
			scatterAspect = width / height;
			scatterSet = !! set && scatterPlaces( set ).length === cells.length;
			if ( scatterSet ) {
				scatter = set;
				return;
			}
			scatter = scatterLayout( cells.map( function ( cell ) {
				return cell.wpRatio;
			} ), width, height, Number( config.gap[ current ] ) || 0, seededRandom( ( config.seed || 1 ) * 7919 + cells.length * 31 + 7 ) );
		}

		function ratioOf( slide ) {
			return Number( slide.style.getPropertyValue( '--wp-swiper-img-ratio' ) ) || 1;
		}

		/**
		 * The photos in their order on a device: the order set there (slide
		 * indexes, the first ones in the first places), then the photos not in
		 * it in shuffle order; without one, the shuffle.
		 *
		 * @param {string} name Device.
		 * @return {number[]}
		 */
		function orderFor( name ) {
			var shuffled = seededOrder( slides.length, config.seed || 1 );
			var set = config.order && config.order[ name ];
			if ( ! set || ! set.length ) {
				return shuffled;
			}
			var seen = {};
			var out = [];
			set.concat( shuffled ).forEach( function ( real ) {
				real = Number( real );
				if ( real >= 0 && real < slides.length && ! seen[ real ] ) {
					seen[ real ] = true;
					out.push( real );
				}
			} );
			return out;
		}

		// A copy of slide `real` to put in a place.
		function copy( real ) {
			var node = slides[ real ].cloneNode( true );
			node.classList.add( 'wp-swiper-collage__item' );
			return node;
		}

		// The image file the browser picks: the width it is drawn at (cropped to the place).
		function sizeImages( cell ) {
			var w = cell.clientWidth;
			var h = cell.clientHeight;
			[].forEach.call( cell.querySelectorAll( 'img.wp-swiper__image' ), function ( img ) {
				var ratio = img.getAttribute( 'width' ) / img.getAttribute( 'height' ) || 1;
				var drawn = Math.ceil( Math.max( w, h * ratio ) );
				if ( drawn > 0 ) {
					img.setAttribute( 'sizes', drawn + 'px' );
				}
			} );
		}

		/**
		 * A background (frontend.js background()) fills its block: Justified
		 * rows and Masonry, as tall as their photos make them, take its height
		 * instead. Each line of places (a row; a column of Masonry) keeps its
		 * widths, and its heights are scaled to fill the block, the gaps kept:
		 * the places keep their order and look, their photos are cropped to
		 * them as always.
		 *
		 * @param {Element[][]} lines  The places of each line, from the top or left.
		 * @param {number}      height The block's height.
		 * @param {number}      gap    The gap.
		 * @param {boolean}     across The lines run across (rows): scaled together.
		 */
		function fillLines( lines, height, gap, across ) {
			var size = function ( cell ) {
				return parseFloat( cell.style.height ) || 0;
			};
			// Rows share one scale (a row is as tall as its places); a column has its own.
			var groups = across ? [ lines ] : lines.map( function ( line ) {
				return [ line ];
			} );
			groups.forEach( function ( group ) {
				var spans = group.map( function ( line ) {
					return across ? Math.max.apply( Math, line.map( size ) ) : line.reduce( function ( sum, cell ) {
						return sum + size( cell );
					}, 0 );
				} );
				var steps = across ? group.length - 1 : group[ 0 ].length - 1;
				var total = spans.reduce( function ( sum, span ) {
					return sum + span;
				}, 0 );
				var scale = total > 0 ? Math.max( 0, height - gap * steps ) / total : 1;
				var top = 0;
				group.forEach( function ( line, i ) {
					line.forEach( function ( cell ) {
						var h = size( cell ) * scale;
						cell.style.top = top + 'px';
						cell.style.height = h + 'px';
						if ( ! across ) {
							top += h + gap;
						}
					} );
					if ( across ) {
						top += spans[ i ] * scale + gap;
					}
				} );
			} );
		}

		// Justified rows and Masonry: each place absolutely positioned, the
		// block as tall as they make it (a background: as its block, see
		// fillLines()); again on every resize.
		function place() {
			var layout = config.layout[ current ];
			var gap = Number( config.gap[ current ] ) || 0;
			var width = grid.clientWidth;
			if ( ! width ) {
				return;
			}
			var fill = el.parentNode.classList.contains( 'wp-swiper--background' );
			// The places of each row (Justified) or column (Masonry), for fillLines().
			var lines = [];
			var set = function ( cell, left, top, w, h ) {
				cell.style.left = left + 'px';
				cell.style.top = top + 'px';
				cell.style.width = w + 'px';
				cell.style.height = h + 'px';
			};
			var setHeight = function ( height ) {
				grid.style.height = fill ? '' : Math.max( 0, height ) + 'px';
			};

			if ( layout === 'scatter' ) {
				// The block keeps its Height; the layout as large as fits it, in its
				// middle (held where it is while a place is resized in the preview).
				mosaicLines = [];
				if ( scatter ) {
					var gridHeight = grid.clientHeight;
					var scale = frozen ? frozen.scale : scatterFit( scatter, width, gridHeight, gap );
					var rects = scatterRects( scatter, scale, gap );
					var box = scatterBox( rects );
					var originX = frozen ? frozen.x : ( width - box.width ) / 2 - box.left;
					var originY = frozen ? frozen.y : ( gridHeight - box.height ) / 2 - box.top;
					rects.forEach( function ( r, i ) {
						if ( cells[ i ] ) {
							set( cells[ i ], originX + r.x, originY + r.y, r.w, r.h );
						}
					} );
					scatterView = { scale: scale, x: originX, y: originY, rects: rects };
				}
			} else if ( layout === 'random' || COLLAGE_TEMPLATES[ layout ] ) {
				// The block keeps its Height; the cuts are laid over it, gaps between.
				// Each cut kept with its part and the line between its two (mosaicLines).
				mosaicLines = [];
				var apply = function ( node, left, top, w, h ) {
					if ( undefined !== node.leaf ) {
						set( cells[ node.leaf ], left, top, w, h );
						return;
					}
					if ( node.vertical ) {
						var first = Math.max( 0, ( w - gap ) * node.share );
						mosaicLines.push( { node: node, vertical: true, left: left, top: top, width: w, height: h, at: left + first, gap: gap } );
						apply( node.a, left, top, first, h );
						apply( node.b, left + first + gap, top, Math.max( 0, w - first - gap ), h );
					} else {
						var upper = Math.max( 0, ( h - gap ) * node.share );
						mosaicLines.push( { node: node, vertical: false, left: left, top: top, width: w, height: h, at: top + upper, gap: gap } );
						apply( node.a, left, top, w, upper );
						apply( node.b, left, top + upper + gap, w, Math.max( 0, h - upper - gap ) );
					}
				};
				if ( tree ) {
					apply( tree, 0, 0, width, grid.clientHeight );
				}
			} else if ( layout === 'masonry' ) {
				var columns = Math.max( 1, Number( config.columns[ current ] ) || 1 );
				var columnWidth = ( width - gap * ( columns - 1 ) ) / columns;
				var heights = [];
				for ( var c = 0; c < columns; c++ ) {
					heights.push( 0 );
					lines.push( [] );
				}
				cells.forEach( function ( cell ) {
					var shortest = heights.indexOf( Math.min.apply( Math, heights ) );
					var h = columnWidth / cell.wpRatio;
					set( cell, shortest * ( columnWidth + gap ), heights[ shortest ], columnWidth, h );
					heights[ shortest ] += h + gap;
					lines[ shortest ].push( cell );
				} );
				setHeight( Math.max.apply( Math, heights ) - gap );
			} else if ( config.rowExact ) {
				// Justified, "Exact row height": every row the row height, each photo
				// at its own proportions; a row ends where the next photo would not
				// fit (one wider than the block on its own is fitted to the width).
				var rowHeight = Math.max( 20, Number( config.rowHeight[ current ] ) || 200 );
				var x = 0;
				var y = 0;
				var line = [];
				cells.forEach( function ( cell ) {
					var w = cell.wpRatio * rowHeight;
					var h = rowHeight;
					if ( w > width ) {
						h = width / cell.wpRatio;
						w = width;
					}
					if ( x > 0 && x + w > width + 0.5 ) {
						x = 0;
						y += rowHeight + gap;
						lines.push( line );
						line = [];
					}
					set( cell, x, y, w, h );
					line.push( cell );
					x += w + gap;
				} );
				lines.push( line );
				setHeight( y + rowHeight );
			} else {
				// Justified: fill a row up to about the row height, then fit it to the width exactly.
				var target = Math.max( 20, Number( config.rowHeight[ current ] ) || 200 );
				var top = 0;
				var row = [];
				var sum = 0;
				var flush = function ( h ) {
					var left = 0;
					row.forEach( function ( cell ) {
						var w = cell.wpRatio * h;
						set( cell, left, top, w, h );
						left += w + gap;
					} );
					top += h + gap;
					lines.push( row );
					row = [];
					sum = 0;
				};
				cells.forEach( function ( cell ) {
					row.push( cell );
					sum += cell.wpRatio;
					if ( sum * target + gap * ( row.length - 1 ) >= width ) {
						flush( ( width - gap * ( row.length - 1 ) ) / sum );
					}
				} );
				// The last row is not stretched: at most the row height.
				if ( row.length ) {
					flush( Math.min( target, ( width - gap * ( row.length - 1 ) ) / sum ) );
				}
				setHeight( top - gap );
			}
			// Random mosaic is cut to the block's height already.
			if ( fill && lines.length ) {
				fillLines( lines, grid.clientHeight, gap, layout !== 'masonry' );
			}
			cells.forEach( sizeImages );
			placed.forEach( function ( callback ) {
				callback();
			} );
		}

		// The places for this device's layout, the photos in shuffle order.
		function build() {
			current = device();
			order = orderFor( current );
			var layout = config.layout[ current ] || 'grid';
			var template = COLLAGE_TEMPLATES[ layout ] ? templateTree( layout ) : null;
			// Laid out by cuts (Random mosaic, the templates), or Scattered: in the block's Height.
			var cuts = layout === 'random' || layout === 'scatter' || !! template;
			// Places positioned by the script.
			var free = cuts || layout === 'justified' || layout === 'masonry';
			var gap = Number( config.gap[ current ] ) || 0;
			// A template has its places (photos repeat when there are fewer); the
			// others show as many as set, at most the photos there are.
			var count = template ? mosaicLeaves( template ).length : Math.min( slides.length, Math.max( 1, Number( config.count[ current ] ) || 1 ) );

			grid.innerHTML = '';
			grid.className = 'wp-swiper-collage__grid wp-swiper-collage__grid--' + layout + ( free ? ' wp-swiper-collage__grid--free' : '' ) + ( cuts ? ' wp-swiper-collage__grid--cuts' : '' ) + ( fills( config ) ? ' wp-swiper-collage__grid--ratio' : '' ) + ( stretched ? ' wp-swiper-collage__grid--stretch' : '' );
			grid.style.cssText = '';
			cells = [];
			for ( var i = 0; i < count; i++ ) {
				var real = order[ i % slides.length ];
				var cell = document.createElement( 'div' );
				cell.className = 'wp-swiper-collage__cell';
				// A place's proportions stay those of the photo it got first.
				cell.wpRatio = ratioOf( slides[ real ] );
				cell.wpReal = real;
				cell.appendChild( copy( real ) );
				grid.appendChild( cell );
				cells.push( cell );
			}
			// The photos not in a place wait their turn, in shuffle order.
			queue = order.slice( count );

			tree = template;
			scatter = null;
			scatterView = null;
			frozen = null;
			if ( layout === 'random' ) {
				cut();
			} else if ( layout === 'scatter' ) {
				scatterCut();
			}

			if ( free ) {
				place();
			} else {
				grid.style.gap = gap + 'px';
				var columns = Math.max( 1, Math.min( count, Number( config.columns[ current ] ) || 1 ) );
				grid.style.gridTemplateColumns = 'repeat(' + columns + ', 1fr)';
				grid.style.gridTemplateRows = 'repeat(' + Math.ceil( count / columns ) + ', 1fr)';
				cells.forEach( sizeImages );
			}
			posterFallbacks( grid );
		}

		// One place, at random (not the one before), fades to the next photo waiting.
		function swap() {
			if ( ! queue.length || ! cells.length ) {
				return;
			}
			var index;
			do {
				index = Math.floor( Math.random() * cells.length );
			} while ( cells.length > 1 && index === lastSwapped );
			lastSwapped = index;

			var cell = cells[ index ];
			var real = queue.shift();
			queue.push( cell.wpReal );
			cell.wpReal = real;

			var fresh = copy( real );
			fresh.classList.add( 'is-entering' );
			cell.appendChild( fresh );
			sizeImages( cell );
			posterFallbacks( cell );

			// Faded in once its image is there (decoded), so it does not pop in
			// half-drawn; at the latest after the delay between swaps (a slow file).
			var img = fresh.querySelector( 'img' );
			var decoded = img && img.decode ? img.decode().catch( function () {} ) : Promise.resolve();
			var late = new Promise( function ( resolve ) {
				setTimeout( resolve, Math.max( 500, Number( config.swap.delay ) || 3000 ) );
			} );
			Promise.race( [ decoded, late ] ).then( function () {
				requestAnimationFrame( function () {
					fresh.classList.remove( 'is-entering' );
				} );
				// Then the photos under it go: all of them, should swaps have piled up.
				var speed = parseFloat( getComputedStyle( fresh ).transitionDuration ) * 1000 || 0;
				setTimeout( function () {
					while ( fresh.parentNode === cell && cell.firstElementChild !== fresh ) {
						cell.removeChild( cell.firstElementChild );
					}
				}, speed + 100 );
			} );
		}

		function schedule() {
			clearTimeout( timer );
			if ( ! config.swap || calm || ! queue.length ) {
				return;
			}
			timer = setTimeout( function () {
				// Not while paused, off screen or in a hidden tab: try again later.
				if ( ! paused && inView && ! document.hidden ) {
					swap();
				}
				schedule();
			}, Math.max( 500, Number( config.swap.delay ) || 3000 ) );
		}

		if ( config.swap && config.swap.pause ) {
			el.addEventListener( 'mouseenter', function () {
				paused = true;
			} );
			el.addEventListener( 'mouseleave', function () {
				paused = false;
			} );
		}
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				inView = entries[ 0 ].isIntersecting;
			} ).observe( el );
		}

		// Lightbox: a click on a place opens its photo, the others in slide order beside it.
		if ( config.lightbox ) {
			el.addEventListener( 'click', function ( e ) {
				var cell = e.target.closest( '.wp-swiper-collage__cell' );
				if ( ! cell || e.defaultPrevented || e.target.closest( '.wp-swiper__link' ) ) {
					return;
				}
				var item = cell.lastElementChild;
				if ( ! item || ! item.matches( LIGHTBOX_SHOWN ) ) {
					return;
				}
				e.preventDefault();
				var items = [];
				slides.forEach( function ( slide, real ) {
					if ( slide.matches( LIGHTBOX_SHOWN ) ) {
						items.push( lightboxItem( slide, real, '' ) );
					}
				} );
				var start = 0;
				items.forEach( function ( entry, i ) {
					if ( entry.real === cell.wpReal ) {
						start = i;
					}
				} );
				paused = true;
				openLightbox( items, start, function () {
					paused = false;
				}, { radius: config.lightbox.radius, thumbRadius: false, thumbs: false } );
			} );
		}

		// The height first: Random mosaic cuts for the block's proportions.
		stretch();
		build();
		schedule();
		requestAnimationFrame( function () {
			el.parentNode.classList.add( 'wp-swiper--ready' );
		} );

		// A background's block may change height with no resize of the window
		// (its content's fonts and images load): the places positioned by the
		// script follow it. Placing them never changes it (fillLines()).
		if ( 'ResizeObserver' in window && el.parentNode.classList.contains( 'wp-swiper--background' ) ) {
			var blockHeight = el.clientHeight;
			new ResizeObserver( function () {
				if ( Math.abs( el.clientHeight - blockHeight ) > 1 ) {
					blockHeight = el.clientHeight;
					if ( grid.classList.contains( 'wp-swiper-collage__grid--free' ) ) {
						place();
					}
				}
			} ).observe( el );
		}

		// Another device: its own layout, built again (the same shuffle); the
		// same device: the arranged layouts re-measured (Random mosaic cut
		// again when the column's height changed).
		var queued = false;
		function refit() {
			if ( queued ) {
				return;
			}
			queued = true;
			requestAnimationFrame( function () {
				queued = false;
				var resized = stretch();
				if ( device() !== current ) {
					build();
					schedule();
				} else if ( grid.classList.contains( 'wp-swiper-collage__grid--free' ) ) {
					// A template keeps its shares whatever the block's proportions.
					if ( resized && tree && config.layout[ current ] === 'random' ) {
						cut();
					}
					// Scattered, unless set in the preview: worked out again once the
					// block's proportions are a tenth off those it was made for.
					if ( scatter && ! scatterSet && ! frozen && grid.clientHeight && Math.abs( grid.clientWidth / grid.clientHeight / scatterAspect - 1 ) > 0.1 ) {
						scatterCut();
					}
					place();
				} else {
					cells.forEach( sizeImages );
				}
			} );
		}
		window.addEventListener( 'resize', refit );
		if ( stretchy ) {
			window.addEventListener( 'load', refit );
			watchNeighbours( root, refit );
		}

		return {
			rebuild: build,
			// For the preview (preview-collage.js): the photos as placed now on this
			// device (the places in turn, then those waiting), a new order for it,
			// each slide's photo ID, and the photo swapping held while dragging.
			places: function () {
				return { device: current, count: cells.length, order: cells.map( function ( cell ) {
					return cell.wpReal;
				} ).concat( queue ) };
			},
			setOrder: function ( list ) {
				config.order = config.order || {};
				config.order[ current ] = list;
				build();
				schedule();
			},
			ids: config.ids || [],
			hold: function ( on ) {
				paused = !! on;
			},
			// Random mosaic and the templates: their cuts' lines as laid out now
			// (grid px), a cut's new share laid out at once, the cuts as stored,
			// and new ones (or none: worked out again, the template's own) for
			// this device; which template is shown ('' : none, Random mosaic).
			mosaic: {
				lines: function () {
					return tree ? mosaicLines.slice() : [];
				},
				template: function () {
					return COLLAGE_TEMPLATES[ config.layout[ current ] ] ? config.layout[ current ] : '';
				},
				setShare: function ( node, share ) {
					node.share = share;
					place();
				},
				serialize: function () {
					return tree ? serializeMosaic( tree ) : '';
				},
				set: function ( text ) {
					var layout = config.layout[ current ];
					if ( COLLAGE_TEMPLATES[ layout ] ) {
						config.cuts = config.cuts || {};
						config.cuts[ current ] = text ? layout + ':' + text : '';
						tree = templateTree( layout );
					} else {
						config.mosaic = config.mosaic || {};
						config.mosaic[ current ] = text || '';
						cut();
					}
					place();
				}
			},
			// Scattered: whether it is shown; its places as laid out now (grid px,
			// each with the edges it is resized by); held at their scale and spot
			// (on) or fitted to the block again (off); a place's new size in grid
			// px, laid out at once; two places' sizes swapped (their photos are
			// swapped: each place keeps the shape of its photo); the layout as
			// stored, and a new one (or none: worked out again) for this device.
			scatter: {
				active: function () {
					return !! scatter && config.layout[ current ] === 'scatter';
				},
				places: function () {
					if ( ! scatterView ) {
						return [];
					}
					return scatterView.rects.map( function ( r, index ) {
						return { index: index, left: scatterView.x + r.x, top: scatterView.y + r.y, width: r.w, height: r.h, edges: r.edges.slice() };
					} );
				},
				freeze: function ( on ) {
					frozen = on && scatterView ? { scale: scatterView.scale, x: scatterView.x, y: scatterView.y } : null;
					if ( ! on ) {
						place();
					}
				},
				resize: function ( index, width, height ) {
					var spot = scatter && scatterPlaces( scatter )[ index ];
					var scale = scatterView ? scatterView.scale : 0;
					if ( ! spot || ! scale ) {
						return;
					}
					spot.w = Math.max( 0.05, Math.min( 20, width / scale ) );
					spot.h = Math.max( 0.05, Math.min( 20, height / scale ) );
					place();
				},
				swap: function ( from, to ) {
					var list = scatter ? scatterPlaces( scatter ) : [];
					if ( list[ from ] && list[ to ] ) {
						var w = list[ from ].w;
						var h = list[ from ].h;
						list[ from ].w = list[ to ].w;
						list[ from ].h = list[ to ].h;
						list[ to ].w = w;
						list[ to ].h = h;
					}
				},
				serialize: function () {
					return scatter ? serializeScatter( scatter ) : '';
				},
				set: function ( text ) {
					config.cuts = config.cuts || {};
					config.cuts[ current ] = text ? 'scatter:' + text : '';
					frozen = null;
					scatterCut();
					place();
				}
			},
			onPlace: function ( callback ) {
				placed.push( callback );
			}
		};
	}

	window.wpSwiperCollage = collage;
	// frontend.js already ran (this file came later, say added by a page
	// builder): the collages now. Those laid out already are left as they are.
	if ( document.readyState !== 'loading' && window.wpSwiperInit ) {
		window.wpSwiperInit();
	}
}() );
