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
	// The fewest photos on screen (the Photos on screen field's min).
	var COLLAGE_MIN_COUNT = 1;
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

	// The rectangles of several layouts in one list (their middle photos all at 0, 0).
	function scatterAllRects( layouts, scale, gap ) {
		return layouts.reduce( function ( list, layout ) {
			return list.concat( scatterRects( layout, scale, gap ) );
		}, [] );
	}

	function scatterCopy( layout ) {
		var place = function ( spot ) {
			var copy = { w: spot.w, h: spot.h };
			if ( spot.step ) {
				copy.step = spot.step;
			}
			return copy;
		};
		return { middle: place( layout.middle ), arms: layout.arms.map( function ( arm ) {
			return arm.map( place );
		} ) };
	}

	/**
	 * Scattered: place `from` taken out of its arm (the layout changed). The
	 * places after it in the arm close up: the next one steps from the one
	 * before it, or touches the middle photo if it is first now.
	 *
	 * @param {Object} layout See scatterRects().
	 * @param {number} from   The place (not 0, the middle one).
	 * @return {Object|null} The place taken; null for the middle one or none.
	 */
	function scatterTake( layout, from ) {
		if ( from < 1 ) {
			return null;
		}
		var index = 1;
		var place = null;
		layout.arms.some( function ( list ) {
			if ( from < index + list.length ) {
				var k = from - index;
				place = list.splice( k, 1 )[ 0 ];
				if ( ! k && list.length ) {
					delete list[ 0 ].step;
				}
				return true;
			}
			index += list.length;
			return false;
		} );
		return place;
	}

	/**
	 * Scattered: the layout with place `from` taken out of its arm and put
	 * at the end of arm `arm`, a step `step` ("a" or "o") from the last
	 * photo there; into an empty arm, next to the middle photo. The places
	 * after it in its old arm close up: the next one steps from the one
	 * before it, or touches the middle photo if it is first now. The place
	 * keeps its size (the shape of its photo).
	 *
	 * @param {Object} layout See scatterRects().
	 * @param {number} from   The place moved (not 0, the middle one).
	 * @param {number} arm    0–3: T, R, B, L.
	 * @param {string} step   "a" or "o"; not used for an empty arm.
	 * @return {Object|null} { layout: a new one, index: the place's index in
	 *                       it }; null for the middle place or when nothing
	 *                       would change.
	 */
	function scatterMove( layout, from, arm, step ) {
		var copy = scatterCopy( layout );
		var place = scatterTake( copy, from );
		if ( ! place ) {
			return null;
		}
		var target = copy.arms[ arm ];
		if ( target.length ) {
			place.step = step;
		} else {
			delete place.step;
		}
		target.push( place );
		var at = 1;
		for ( var a = 0; a < arm; a++ ) {
			at += copy.arms[ a ].length;
		}
		at += target.length - 1;
		if ( at === from && serializeScatter( copy ) === serializeScatter( layout ) ) {
			return null;
		}
		return { layout: copy, index: at };
	}

	/**
	 * The places place `from` can be moved to (scatterMove()): the end of
	 * each arm, along it or outwards; an empty arm, next to the middle photo.
	 *
	 * @param {Object} layout See scatterRects().
	 * @param {number} from   The place moved.
	 * @return {Object[]} { layout, index } of each.
	 */
	function scatterMoves( layout, from ) {
		var moves = [];
		if ( from < 1 ) {
			return moves;
		}
		[ 0, 1, 2, 3 ].forEach( function ( arm ) {
			[ 'a', 'o' ].forEach( function ( step ) {
				var move = scatterMove( layout, from, arm, step );
				// Into an empty arm "a" and "o" are the same place: it once.
				var same = move && moves.some( function ( other ) {
					return other.index === move.index && serializeScatter( other.layout ) === serializeScatter( move.layout );
				} );
				if ( move && ! same ) {
					moves.push( move );
				}
			} );
		} );
		return moves;
	}

	/**
	 * Where place `c` of a layout is once place `from` was moved to `index`
	 * (scatterMove()): the places between take one step up or down.
	 *
	 * @param {number} c     The place's index before.
	 * @param {number} from  The place moved, before.
	 * @param {number} index The place moved, after.
	 * @return {number}
	 */
	function scatterMovedIndex( c, from, index ) {
		if ( c === from ) {
			return index;
		}
		var rest = c > from ? c - 1 : c;
		return rest >= index ? rest + 1 : rest;
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
		// Several layouts (a photo being moved, see scatterMoves()): all of them fit.
		var layouts = Array.isArray( layout ) ? layout : [ layout ];
		var low = 0;
		var high = Math.max( width, height, 1 );
		for ( var i = 0; i < 32; i++ ) {
			var scale = ( low + high ) / 2;
			var box = scatterBox( scatterAllRects( layouts, scale, gap ) );
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

	/*
	 * Freeform: each place where it was put, as large as it was made, in a
	 * frame: { aspect, places: [ { x, y, w, h } ] }, the frame 1 high and
	 * `aspect` wide, the places in its units (so in shares of the block's
	 * height: a new Height scales all of it, gaps too). Places never overlap,
	 * neighbours at least a gap apart. Worked out at random for the block
	 * (freeLayout()) until set in the preview, where photos are moved and
	 * resized by hand (preview-collage.js); the frame is then the block as
	 * it was there. Shown as large as the frame fits the block, in its middle.
	 */
	// Freeform: layouts tried for one shuffle (the one filling its block best
	// kept), spots tried for each place.
	var FREE_TRIES = 24;
	var FREE_SPOTS = 60;
	// Freeform: the share of the block its photos cover, about.
	var FREE_COVER = 0.58;

	// Whether two places are less than `gap` apart (they overlap or touch).
	function freeClash( a, b, gap ) {
		var e = 1e-6;
		return a.x < b.x + b.w + gap - e && b.x < a.x + a.w + gap - e && a.y < b.y + b.h + gap - e && b.y < a.y + a.h + gap - e;
	}

	/**
	 * Freeform: a layout for these photos in a block `aspect` wide, 1 high.
	 * The larger places first, near the middle; each next one a gap from a
	 * place already there (beside it, above or below, now and then lined up
	 * with its edge), in the block, clear of all the others, and of the spots
	 * tried the one nearest the middle, touching most places. Each shaped
	 * after its photo, give or take, some larger than others. Of FREE_TRIES
	 * such layouts (all from the shuffle, so the same each time) the one
	 * spreading over the block most evenly is kept, centred in it.
	 *
	 * @param {number[]} ratios Width / height of each place's photo, in order.
	 * @param {number}   aspect The block's width / height.
	 * @param {number}   gap    In shares of the block's height.
	 * @param {Function} random Seeded random numbers.
	 * @return {Object} { aspect, places }.
	 */
	function freeLayout( ratios, aspect, gap, random ) {
		var count = ratios.length;
		var best = null;
		var bestScore = -Infinity;

		function spot( placed, w, h ) {
			var choice = null;
			var choiceScore = Infinity;
			if ( ! placed.length ) {
				// The first one about the middle.
				var first = { x: ( aspect - w ) / 2 + ( random() - 0.5 ) * aspect * 0.2, y: ( 1 - h ) / 2 + ( random() - 0.5 ) * 0.2, w: w, h: h };
				first.x = Math.max( 0, Math.min( aspect - w, first.x ) );
				first.y = Math.max( 0, Math.min( 1 - h, first.y ) );
				return first;
			}
			for ( var k = 0; k < FREE_SPOTS; k++ ) {
				var o = placed[ Math.floor( random() * placed.length ) ];
				var side = Math.floor( random() * 4 );
				var across = side % 2 === 0;
				// Along the side: at least a quarter of the smaller one beside the other.
				var size = across ? h : w;
				var other = across ? o.h : o.w;
				var start = across ? o.y : o.x;
				var overlap = Math.min( size, other ) * 0.25;
				var along = random() < 0.3 ? ( random() < 0.5 ? start : start + other - size ) : start - size + overlap + random() * ( size + other - 2 * overlap );
				var r = { w: w, h: h };
				if ( side === 0 ) {
					r.x = o.x + o.w + gap;
					r.y = along;
				} else if ( side === 2 ) {
					r.x = o.x - gap - w;
					r.y = along;
				} else if ( side === 1 ) {
					r.y = o.y + o.h + gap;
					r.x = along;
				} else {
					r.y = o.y - gap - h;
					r.x = along;
				}
				if ( r.x < 0 || r.y < 0 || r.x + w > aspect || r.y + h > 1 ) {
					continue;
				}
				var touching = 0;
				var clear = placed.every( function ( p ) {
					if ( freeClash( r, p, gap ) ) {
						return false;
					}
					if ( freeClash( r, p, gap * 1.5 + 0.002 ) ) {
						touching++;
					}
					return true;
				} );
				if ( ! clear ) {
					continue;
				}
				var dx = ( r.x + w / 2 - aspect / 2 ) / aspect;
				var dy = r.y + h / 2 - 0.5;
				var score = dx * dx + dy * dy - 0.02 * touching + random() * 0.01;
				if ( score < choiceScore ) {
					choice = r;
					choiceScore = score;
				}
			}
			return choice;
		}

		for ( var t = 0; t < FREE_TRIES; t++ ) {
			var weights = ratios.map( function () {
				return 0.45 + random() * 1.2;
			} );
			var total = weights.reduce( function ( sum, value ) {
				return sum + value;
			}, 0 );
			var cover = FREE_COVER * ( 0.92 + random() * 0.16 );
			// The larger first.
			var turn = weights.map( function ( value, index ) {
				return index;
			} ).sort( function ( a, b ) {
				return weights[ b ] - weights[ a ];
			} );
			var places = [];
			var placed = [];
			var ok = turn.every( function ( index ) {
				var ratio = Math.max( 0.5, Math.min( 2.2, ( ratios[ index ] || 1 ) * ( 0.85 + random() * 0.3 ) ) );
				var area = weights[ index ] / total * cover * aspect;
				var w = Math.sqrt( area * ratio );
				var h = Math.sqrt( area / ratio );
				// No taller than the block, no wider than it, less a little.
				var fit = Math.min( 1, 0.85 / h, aspect * 0.85 / w );
				w *= fit;
				h *= fit;
				for ( var shrink = 0; shrink < 10; shrink++ ) {
					var r = spot( placed, w, h );
					if ( r ) {
						places[ index ] = r;
						placed.push( r );
						return true;
					}
					w *= 0.88;
					h *= 0.88;
				}
				return false;
			} );
			if ( ! ok ) {
				continue;
			}
			// How good: the share covered, how much of the block it spreads
			// over, how far from its middle it is.
			var box = scatterBox( places );
			var area = places.reduce( function ( sum, r ) {
				return sum + r.w * r.h;
			}, 0 ) / aspect;
			var off = Math.abs( box.left + box.width / 2 - aspect / 2 ) / aspect + Math.abs( box.top + box.height / 2 - 0.5 );
			var score = area + 0.35 * ( box.width / aspect + box.height ) - off;
			if ( score > bestScore ) {
				best = { places: places, box: box };
				bestScore = score;
			}
		}
		if ( ! best ) {
			// None fitted (a great many photos): a plain row, each a share of the width.
			var each = ( aspect - gap * ( count - 1 ) ) / count;
			return { aspect: aspect, places: ratios.map( function ( ratio, i ) {
				var h = Math.min( 1, each / ( ratio || 1 ) );
				return { x: i * ( each + gap ), y: ( 1 - h ) / 2, w: each, h: h };
			} ) };
		}
		// Centred in the block.
		var shiftX = ( aspect - best.box.width ) / 2 - best.box.left;
		var shiftY = ( 1 - best.box.height ) / 2 - best.box.top;
		return { aspect: aspect, places: best.places.map( function ( r ) {
			return { x: r.x + shiftX, y: r.y + shiftY, w: r.w, h: r.h };
		} ) };
	}

	/**
	 * Where a Freeform layout goes in a block: the frame (and any place out of
	 * it) as large as fits, in the middle.
	 *
	 * @param {Object} layout See freeLayout().
	 * @param {number} width  The block's, px.
	 * @param {number} height The block's, px.
	 * @return {Object} { scale: px per unit, x, y: where the frame's 0, 0 is }.
	 */
	function freeFit( layout, width, height ) {
		var box = scatterBox( layout.places.concat( [ { x: 0, y: 0, w: layout.aspect, h: 1 } ] ) );
		var scale = Math.max( 0.0001, Math.min( width / box.width, height / box.height ) );
		return {
			scale: scale,
			x: ( width - box.width * scale ) / 2 - box.left * scale,
			y: ( height - box.height * scale ) / 2 - box.top * scale
		};
	}

	/**
	 * A Freeform layout as stored (WP_Swiper_Settings::sanitize_freeform()):
	 * the frame's aspect, then each place's x,y,w,h: "1.8;0.1,0.05,0.5,0.4;…",
	 * and back.
	 *
	 * @param {string} text The layout.
	 * @return {Object|null} The layout; null when it is not one.
	 */
	function parseFree( text ) {
		var parts = String( text || '' ).split( ';' );
		var aspect = Number( parts.shift() );
		if ( ! ( aspect > 0 ) || ! parts.length ) {
			return null;
		}
		var places = [];
		for ( var i = 0; i < parts.length; i++ ) {
			var n = parts[ i ].split( ',' ).map( Number );
			if ( n.length !== 4 || n.some( isNaN ) || ! ( n[ 2 ] > 0 ) || ! ( n[ 3 ] > 0 ) ) {
				return null;
			}
			places.push( { x: n[ 0 ], y: n[ 1 ], w: n[ 2 ], h: n[ 3 ] } );
		}
		return { aspect: aspect, places: places };
	}

	function serializeFree( layout ) {
		var number = function ( value ) {
			return Math.round( value * 10000 ) / 10000;
		};
		return [ number( layout.aspect ) ].concat( layout.places.map( function ( r ) {
			return [ number( r.x ), number( r.y ), number( r.w ), number( r.h ) ].join( ',' );
		} ) ).join( ';' );
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
		// Scattered, a photo dragged in the preview: the place it is taken from,
		// the places it can be moved to (scatterMoves()), the one shown now (-1:
		// none) and the scale and spot all of them fit the block at.
		var moving = null;
		// Freeform: its layout (freeLayout()), whether it is the one set in the
		// preview, the block's proportions it was worked out for, where it was
		// last laid out (freeFit()) and, while a photo is moved or resized in
		// the preview, that spot held.
		var free = null;
		var freeSet = false;
		var freeAspect = 0;
		var freeView = null;
		var freeHeld = null;
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

		// Freeform: the layout set in the preview for this device, while it has
		// a place for every place shown; else worked out for the block (from
		// the shuffle, the photos in their places and their number: Shuffle
		// gives a new one, Reset the first one back).
		function freeCut() {
			var stored = config.cuts && config.cuts[ current ];
			var set = stored && stored.indexOf( 'freeform:' ) === 0 ? parseFree( stored.slice( 9 ) ) : null;
			var width = grid.clientWidth || 1;
			var height = grid.clientHeight || 1;
			freeAspect = width / height;
			freeSet = !! set && set.places.length === cells.length;
			if ( freeSet ) {
				free = set;
				return;
			}
			var seed = cells.reduce( function ( hash, cell ) {
				return Math.imul( hash ^ ( cell.wpReal + 1 ), 2654435761 ) >>> 0;
			}, ( ( config.seed || 1 ) * 7919 + cells.length * 131 + 17 ) >>> 0 );
			free = freeLayout( cells.map( function ( cell ) {
				return cell.wpRatio;
			} ), freeAspect, ( Number( config.gap[ current ] ) || 0 ) / height, seededRandom( seed ) );
		}

		// Freeform: its layout as shown now, the block its frame (as stored).
		function freeFramed() {
			var v = freeHeld || freeView;
			if ( ! free || ! v ) {
				return null;
			}
			var left = -v.x / v.scale;
			var top = -v.y / v.scale;
			var high = ( grid.clientHeight || 1 ) / v.scale;
			return {
				aspect: ( grid.clientWidth || 1 ) / v.scale / high,
				places: free.places.map( function ( r ) {
					return { x: ( r.x - left ) / high, y: ( r.y - top ) / high, w: r.w / high, h: r.h / high };
				} )
			};
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
					// A photo being moved: held where all the places it can go fit, the
					// move under the pointer shown (each cell at its place after it).
					var held = moving || frozen;
					var move = moving && moving.shown >= 0 ? moving.moves[ moving.shown ] : null;
					var scale = held ? held.scale : scatterFit( scatter, width, gridHeight, gap );
					var rects = scatterRects( move ? move.layout : scatter, scale, gap );
					var box = scatterBox( rects );
					var originX = held ? held.x : ( width - box.width ) / 2 - box.left;
					var originY = held ? held.y : ( gridHeight - box.height ) / 2 - box.top;
					cells.forEach( function ( cell, c ) {
						var r = rects[ move ? scatterMovedIndex( c, moving.from, move.index ) : c ];
						if ( r ) {
							set( cell, originX + r.x, originY + r.y, r.w, r.h );
						}
					} );
					scatterView = { scale: scale, x: originX, y: originY, rects: rects };
				}
			} else if ( layout === 'freeform' ) {
				// The block keeps its Height; the frame as large as fits it, in its
				// middle (held where it is while a photo is moved in the preview).
				mosaicLines = [];
				if ( free ) {
					var view = freeHeld || freeFit( free, width, grid.clientHeight );
					cells.forEach( function ( cell, c ) {
						var r = free.places[ c ];
						if ( r ) {
							set( cell, view.x + r.x * view.scale, view.y + r.y * view.scale, r.w * view.scale, r.h * view.scale );
						}
					} );
					freeView = view;
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
			// Laid out by cuts (Random mosaic, the templates), or Scattered, Freeform: in the block's Height.
			var cuts = layout === 'random' || layout === 'scatter' || layout === 'freeform' || !! template;
			// Places positioned by the script.
			var positioned = cuts || layout === 'justified' || layout === 'masonry';
			var gap = Number( config.gap[ current ] ) || 0;
			// A template has its places (photos repeat when there are fewer); the
			// others show as many as set, at most the photos there are.
			var count = template ? mosaicLeaves( template ).length : Math.min( slides.length, Math.max( 1, Number( config.count[ current ] ) || 1 ) );

			grid.innerHTML = '';
			grid.className = 'wp-swiper-collage__grid wp-swiper-collage__grid--' + layout + ( positioned ? ' wp-swiper-collage__grid--free' : '' ) + ( cuts ? ' wp-swiper-collage__grid--cuts' : '' ) + ( fills( config ) ? ' wp-swiper-collage__grid--ratio' : '' ) + ( stretched ? ' wp-swiper-collage__grid--stretch' : '' );
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
			moving = null;
			free = null;
			freeView = null;
			freeHeld = null;
			if ( layout === 'random' ) {
				cut();
			} else if ( layout === 'scatter' ) {
				scatterCut();
			} else if ( layout === 'freeform' ) {
				freeCut();
			}

			if ( positioned ) {
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
					if ( scatter && ! scatterSet && ! frozen && ! moving && grid.clientHeight && Math.abs( grid.clientWidth / grid.clientHeight / scatterAspect - 1 ) > 0.1 ) {
						scatterCut();
					}
					// Freeform, the same.
					if ( free && ! freeSet && ! freeHeld && grid.clientHeight && Math.abs( grid.clientWidth / grid.clientHeight / freeAspect - 1 ) > 0.1 ) {
						freeCut();
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
			// Undo / redo in the editor (preview-collage.js): this device's order
			// (slide indexes; none: the shuffle's), Random mosaic's sizes and the
			// templates' or Scattered's, as given, laid out again.
			restore: function ( state ) {
				config.order = config.order || {};
				config.mosaic = config.mosaic || {};
				config.cuts = config.cuts || {};
				config.order[ current ] = state.order && state.order.length ? state.order : false;
				config.mosaic[ current ] = state.mosaic || '';
				config.cuts[ current ] = state.cuts || '';
				// Photos on screen (a place of Scattered taken out has one fewer).
				if ( state.count > 0 ) {
					config.count[ current ] = state.count;
				}
				build();
				schedule();
			},
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
			// swapped: each place keeps the shape of its photo); a photo moved to
			// the end of an arm (startMove(), showMove(), endMove()); the layout as
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
				// A photo dragged from place `from`: the collage made smaller where
				// needed for every place it can be moved to (scatterMoves()) to fit
				// the block too, and held there; those places (grid px), in order.
				startMove: function ( from ) {
					moving = null;
					var moves = scatter && scatterView ? scatterMoves( scatter, from ) : [];
					if ( ! moves.length ) {
						return [];
					}
					var width = grid.clientWidth;
					var height = grid.clientHeight;
					var gap = Number( config.gap[ current ] ) || 0;
					var layouts = [ scatter ].concat( moves.map( function ( move ) {
						return move.layout;
					} ) );
					var scale = Math.min( scatterView.scale, scatterFit( layouts, width, height, gap ) );
					var box = scatterBox( scatterAllRects( layouts, scale, gap ) );
					moving = { from: from, moves: moves, shown: -1, scale: scale, x: ( width - box.width ) / 2 - box.left, y: ( height - box.height ) / 2 - box.top };
					place();
					return moves.map( function ( move ) {
						var r = scatterRects( move.layout, scale, gap )[ move.index ];
						return { left: moving.x + r.x, top: moving.y + r.y, width: r.w, height: r.h };
					} );
				},
				// The collage as move `i` would make it (-1: as it is).
				showMove: function ( i ) {
					var shown = moving && moving.moves[ i ] ? i : -1;
					if ( moving && shown !== moving.shown ) {
						moving.shown = shown;
						place();
					}
				},
				// The drag over: move `i` made (none: -1), the collage fitted to the
				// block again. Returns { from, index } of the place moved, or null.
				endMove: function ( i ) {
					var move = moving && moving.moves[ i ];
					var from = moving ? moving.from : -1;
					moving = null;
					if ( move ) {
						scatter = move.layout;
					}
					place();
					return move ? { from: from, index: move.index } : null;
				},
				// Whether place `index` can be taken out (removePlace()): not the
				// middle one, nor below the fewest photos on screen.
				removable: function ( index ) {
					return index > 0 && index < cells.length && cells.length > COLLAGE_MIN_COUNT;
				},
				/*
				 * Place `index` taken out of the layout for this device (the rest
				 * as it is; the places after it in its arm close up), one photo
				 * fewer on screen, and its photo to the end of the order (it waits
				 * with the others; the slide stays). Returns what to store: the
				 * layout, the number of photos and the order (slide indexes); null
				 * when it cannot.
				 */
				removePlace: function ( index ) {
					if ( ! scatter || ! this.removable( index ) ) {
						return null;
					}
					var layout = scatterCopy( scatter );
					scatterTake( layout, index );
					var list = cells.map( function ( cell ) {
						return cell.wpReal;
					} ).concat( queue );
					list.push( list.splice( index, 1 )[ 0 ] );
					var text = serializeScatter( layout );
					config.cuts = config.cuts || {};
					config.cuts[ current ] = 'scatter:' + text;
					config.order = config.order || {};
					config.order[ current ] = list;
					config.count[ current ] = cells.length - 1;
					build();
					schedule();
					return { layout: text, count: cells.length, order: list };
				},
				serialize: function () {
					return scatter ? serializeScatter( scatter ) : '';
				},
				set: function ( text ) {
					config.cuts = config.cuts || {};
					config.cuts[ current ] = text ? 'scatter:' + text : '';
					frozen = null;
					moving = null;
					scatterCut();
					place();
				}
			},
			/*
			 * Freeform (preview-collage.js): whether it is shown; its places as
			 * laid out now (grid px); the gap between them there (px); a photo
			 * moved or resized: begin() holds the collage at its scale and spot,
			 * setRect() puts a place at grid px, cancel() puts all back as they
			 * were, store() keeps it as it is for this device (the frame the
			 * block, as shown) and returns it as stored.
			 */
			free: {
				active: function () {
					return !! free && config.layout[ current ] === 'freeform';
				},
				places: function () {
					if ( ! free || ! freeView ) {
						return [];
					}
					var v = freeView;
					return free.places.map( function ( r, index ) {
						return { index: index, left: v.x + r.x * v.scale, top: v.y + r.y * v.scale, width: r.w * v.scale, height: r.h * v.scale };
					} );
				},
				gap: function () {
					var height = grid.clientHeight || 1;
					return freeView ? ( Number( config.gap[ current ] ) || 0 ) * Math.min( 1, freeView.scale / height ) : 0;
				},
				begin: function () {
					if ( free && freeView ) {
						freeHeld = { scale: freeView.scale, x: freeView.x, y: freeView.y, before: free.places.map( function ( r ) {
							return { x: r.x, y: r.y, w: r.w, h: r.h };
						} ) };
					}
				},
				setRect: function ( index, rect ) {
					var v = freeHeld || freeView;
					var r = free && v ? free.places[ index ] : null;
					if ( ! r ) {
						return;
					}
					r.x = ( rect.left - v.x ) / v.scale;
					r.y = ( rect.top - v.y ) / v.scale;
					r.w = rect.width / v.scale;
					r.h = rect.height / v.scale;
					place();
				},
				cancel: function () {
					if ( free && freeHeld ) {
						free.places = freeHeld.before;
					}
					freeHeld = null;
					place();
				},
				store: function () {
					var framed = freeFramed();
					if ( ! framed ) {
						return '';
					}
					free = framed;
					var text = serializeFree( free );
					config.cuts = config.cuts || {};
					config.cuts[ current ] = 'freeform:' + text;
					freeSet = true;
					freeHeld = null;
					place();
					return text;
				},
				// Whether place `index` can be taken out (removePlace()): not below
				// the fewest photos on screen.
				removable: function ( index ) {
					return index >= 0 && index < cells.length && cells.length > COLLAGE_MIN_COUNT;
				},
				/*
				 * Place `index` taken out for this device, the others where they
				 * are, one photo fewer on screen, and its photo to the end of the
				 * order (it waits with the others; the slide stays). Returns what
				 * to store: the layout, the number of photos and the order (slide
				 * indexes); null when it cannot.
				 */
				removePlace: function ( index ) {
					var framed = this.removable( index ) ? freeFramed() : null;
					if ( ! framed ) {
						return null;
					}
					framed.places.splice( index, 1 );
					var list = cells.map( function ( cell ) {
						return cell.wpReal;
					} ).concat( queue );
					list.push( list.splice( index, 1 )[ 0 ] );
					var text = serializeFree( framed );
					config.cuts = config.cuts || {};
					config.cuts[ current ] = 'freeform:' + text;
					config.order = config.order || {};
					config.order[ current ] = list;
					config.count[ current ] = cells.length - 1;
					build();
					schedule();
					return { layout: text, count: cells.length, order: list };
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
