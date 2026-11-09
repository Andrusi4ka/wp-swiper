/**
 * WP Swiper: the Collage effect's layouts (just the numbers, no page):
 * the templates and Random mosaic (cuts), Scattered, Freeform and the
 * bounds of turned photos (Rotation). Loaded before collage.js, which
 * takes them from window.wpSwiperCollageLayouts.
 */
( function () {
	'use strict';


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
	 * Rotation: rectangles ({ x, y, w, h }) turned by `angle` (degrees, as CSS
	 * rotate()) about 0, 0. Their bounds on the page: from the farthest turned
	 * corner of any of them to the farthest on the other side.
	 *
	 * @param {Object[]} rects  The rectangles.
	 * @param {number}   angle  Degrees.
	 * @return {Object} { width, height, x, y: the bounds' middle }.
	 */
	function turnedBounds( rects, angle ) {
		var turn = ( Number( angle ) || 0 ) * Math.PI / 180;
		var cos = Math.cos( turn );
		var sin = Math.sin( turn );
		var left = Infinity;
		var top = Infinity;
		var right = -Infinity;
		var bottom = -Infinity;
		rects.forEach( function ( r ) {
			var cx = r.x + r.w / 2;
			var cy = r.y + r.h / 2;
			var x = cx * cos - cy * sin;
			var y = cx * sin + cy * cos;
			var halfW = ( r.w * Math.abs( cos ) + r.h * Math.abs( sin ) ) / 2;
			var halfH = ( r.w * Math.abs( sin ) + r.h * Math.abs( cos ) ) / 2;
			left = Math.min( left, x - halfW );
			right = Math.max( right, x + halfW );
			top = Math.min( top, y - halfH );
			bottom = Math.max( bottom, y + halfH );
		} );
		return { width: right - left, height: bottom - top, x: ( left + right ) / 2, y: ( top + bottom ) / 2 };
	}

	/**
	 * Where the rectangles' 0, 0 goes in a box width × height turned by
	 * `angle` about its middle, for their turned bounds to be in its middle
	 * (not turned: their box in its middle).
	 *
	 * @param {Object[]} rects  The rectangles.
	 * @param {number}   angle  Degrees.
	 * @param {number}   width  The box's, px.
	 * @param {number}   height The box's, px.
	 * @return {Object} { x, y }.
	 */
	function turnedOrigin( rects, angle, width, height ) {
		var bounds = turnedBounds( rects, angle );
		var turn = ( Number( angle ) || 0 ) * Math.PI / 180;
		var cos = Math.cos( turn );
		var sin = Math.sin( turn );
		return {
			x: width / 2 - ( bounds.x * cos + bounds.y * sin ),
			y: height / 2 - ( bounds.y * cos - bounds.x * sin )
		};
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
	function scatterFit( layout, width, height, gap, angle ) {
		// Several layouts (a photo being moved, see scatterMoves()): all of them fit.
		// Turned (Rotation): the photos' turned corners fit, not their box turned
		// (Scattered is a cross: the box's corners are empty).
		var layouts = Array.isArray( layout ) ? layout : [ layout ];
		var low = 0;
		var high = Math.max( width, height, 1 );
		for ( var i = 0; i < 32; i++ ) {
			var scale = ( low + high ) / 2;
			var box = turnedBounds( scatterAllRects( layouts, scale, gap ), angle );
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
	 * it) as large as fits, in the middle. Turned (Rotation): the photos'
	 * turned corners as large as fit, in the middle (the frame, turned, is
	 * not what shows).
	 *
	 * @param {Object} layout See freeLayout().
	 * @param {number} width  The block's, px.
	 * @param {number} height The block's, px.
	 * @param {number} angle  Rotation, degrees.
	 * @return {Object} { scale: px per unit, x, y: where the frame's 0, 0 is }.
	 */
	function freeFit( layout, width, height, angle ) {
		if ( angle ) {
			var bounds = turnedBounds( layout.places, angle );
			var size = Math.max( 0.0001, Math.min( width / ( bounds.width || 1 ), height / ( bounds.height || 1 ) ) );
			var origin = turnedOrigin( layout.places.map( function ( r ) {
				return { x: r.x * size, y: r.y * size, w: r.w * size, h: r.h * size };
			} ), angle, width, height );
			return { scale: size, x: origin.x, y: origin.y };
		}
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

	window.wpSwiperCollageLayouts = {
		COLLAGE_TEMPLATES: COLLAGE_TEMPLATES,
		seededRandom: seededRandom,
		seededOrder: seededOrder,
		mosaicTree: mosaicTree,
		parseMosaic: parseMosaic,
		serializeMosaic: serializeMosaic,
		mosaicLeaves: mosaicLeaves,
		COLLAGE_MIN_COUNT: COLLAGE_MIN_COUNT,
		scatterPlaces: scatterPlaces,
		scatterRects: scatterRects,
		scatterAllRects: scatterAllRects,
		scatterCopy: scatterCopy,
		scatterTake: scatterTake,
		scatterMoves: scatterMoves,
		scatterMovedIndex: scatterMovedIndex,
		turnedBounds: turnedBounds,
		turnedOrigin: turnedOrigin,
		scatterFit: scatterFit,
		scatterLayout: scatterLayout,
		parseScatter: parseScatter,
		serializeScatter: serializeScatter,
		freeLayout: freeLayout,
		freeFit: freeFit,
		parseFree: parseFree,
		serializeFree: serializeFree
	};
} )();
