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

	// The layouts (collage-layouts.js, loaded before this file).
	var layouts = window.wpSwiperCollageLayouts;
	if ( ! layouts ) {
		return;
	}
	var COLLAGE_TEMPLATES = layouts.COLLAGE_TEMPLATES;
	var seededRandom = layouts.seededRandom;
	var seededOrder = layouts.seededOrder;
	var mosaicTree = layouts.mosaicTree;
	var parseMosaic = layouts.parseMosaic;
	var serializeMosaic = layouts.serializeMosaic;
	var mosaicLeaves = layouts.mosaicLeaves;
	var COLLAGE_MIN_COUNT = layouts.COLLAGE_MIN_COUNT;
	var scatterPlaces = layouts.scatterPlaces;
	var scatterRects = layouts.scatterRects;
	var scatterAllRects = layouts.scatterAllRects;
	var scatterCopy = layouts.scatterCopy;
	var scatterTake = layouts.scatterTake;
	var scatterMoves = layouts.scatterMoves;
	var scatterMovedIndex = layouts.scatterMovedIndex;
	var turnedBounds = layouts.turnedBounds;
	var turnedOrigin = layouts.turnedOrigin;
	var scatterFit = layouts.scatterFit;
	var scatterLayout = layouts.scatterLayout;
	var parseScatter = layouts.parseScatter;
	var serializeScatter = layouts.serializeScatter;
	var freeLayout = layouts.freeLayout;
	var freeFit = layouts.freeFit;
	var parseFree = layouts.parseFree;
	var serializeFree = layouts.serializeFree;

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
		// Content in a place (render.php collage_content()): an image over text
		// in one place instead of a photo, kept there while the photos swap.
		// This very node is moved into its place (not a copy): the scripts of a
		// shortcode in it find it, a form keeps what was typed. Back in its
		// hidden holder while the places are made again (build()).
		var contentPool = el.querySelector( ':scope > .wp-swiper-collage__content-pool' );
		var contentNode = contentPool && config.content ? contentPool.firstElementChild : null;
		if ( contentNode ) {
			contentNode.classList.add( 'wp-swiper-collage__item' );
		}
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
		// The column's free height it was given then (stretch()), the block's
		// height before trim() took the empty bands off.
		var freeHeight = '';
		// Rotation, Height "Auto" (Scattered, Freeform): the block cut to the
		// photos' top-most and bottom-most points (trim()): its height and how far
		// up the turned grid goes for that.
		var trimmed = null;
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
			freeHeight = root.style.height;
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

		/**
		 * Content in a place: which of `count` places has it on this device
		 * (-1: none). Not Justified rows or Masonry (their places are their
		 * photos'); not when it would be the only place.
		 *
		 * @param {number} count The places.
		 * @return {number} The place (from 0).
		 */
		function contentPlace( count ) {
			var layout = config.layout[ current ] || 'grid';
			if ( ! contentNode || count < 2 || layout === 'justified' || layout === 'masonry' ) {
				return -1;
			}
			var place = Math.round( Number( config.content.place && config.content.place[ current ] ) || 1 );
			return Math.max( 0, Math.min( count, place ) - 1 );
		}

		// The content's text cut where it does not fit (content.js).
		function fitContent( cell ) {
			if ( window.wpSwiperContent ) {
				window.wpSwiperContent.fit( cell.querySelector( '.wp-swiper-content' ) );
			}
		}

		/*
		 * Rotation (collage.css --turned): the Styles laid out in the Height
		 * (Grid, Random mosaic, the templates; Scattered and Freeform turned
		 * where they are, as large as fits turned) turned by this device's angle.
		 * The grid is laid out in a box that, turned, covers the block (Fill:
		 * its edges cut off) or fits in it (Fit), so the Gap and the places
		 * keep their sizes; the block keeps the height it has unturned. Each
		 * photo is turned back, upright (sizeImages()), a shadow falls as on
		 * the page. Measured unturned each time (the Height, its proportions,
		 * a column's height).
		 */
		var TURNED = [ 'grid', 'random', 'scatter', 'freeform' ];
		// Laid out in the block itself, as large as fits it: turned where they are.
		var TURNED_IN_PLACE = [ 'scatter', 'freeform' ];
		function angleNow() {
			var layout = config.layout[ current ] || 'grid';
			if ( TURNED.indexOf( layout ) === -1 && ! COLLAGE_TEMPLATES[ layout ] ) {
				return 0;
			}
			var angle = Number( config.rotate && config.rotate[ current ] ) || 0;
			return Math.max( -45, Math.min( 45, angle ) );
		}
		function orient() {
			var angle = angleNow();
			if ( grid.wpTurned ) {
				[ 'position', 'left', 'top', 'width', 'height', 'transform' ].forEach( function ( property ) {
					grid.style[ property ] = '';
				} );
				grid.style.removeProperty( '--wp-swiper-collage-turn' );
				grid.style.removeProperty( '--wp-swiper-shadow' );
				el.style.height = '';
				// Cut by trim(): the column's free height back, to measure in.
				if ( trimmed && stretched ) {
					root.style.height = freeHeight;
				}
				grid.wpTurned = false;
			}
			var inPlace = TURNED_IN_PLACE.indexOf( config.layout[ current ] ) !== -1;
			grid.wpAngle = angle;
			// For the preview (preview-dev.js): the grid is the block, turned.
			grid.wpInPlace = inPlace && !! angle;
			el.classList.toggle( 'wp-swiper-collage--turned', !! angle );
			el.classList.toggle( 'wp-swiper-collage--turned-fit', !! angle && ( inPlace || !! ( config.rotateFit && config.rotateFit[ current ] ) ) );
			if ( ! angle ) {
				return;
			}
			var width = grid.clientWidth;
			var height = grid.clientHeight;
			if ( ! width || ! height ) {
				return;
			}
			var turn = angle * Math.PI / 180;
			var cos = Math.abs( Math.cos( turn ) );
			var sin = Math.abs( Math.sin( turn ) );
			var w = width * cos + height * sin;
			var h = width * sin + height * cos;
			if ( inPlace ) {
				// Scattered, Freeform: the grid stays the block, turned about its
				// middle; their photos laid out as large as fits it turned
				// (scatterFit(), freeFit() with the angle).
				w = width;
				h = height;
			} else if ( config.rotateFit && config.rotateFit[ current ] ) {
				// Turned, as large as fits the block (its proportions kept).
				var k = Math.min( width / w, height / h );
				w = width * k;
				h = height * k;
			}
			el.style.height = height + 'px';
			grid.style.position = 'absolute';
			grid.style.left = ( width - w ) / 2 + 'px';
			grid.style.top = ( height - h ) / 2 + 'px';
			grid.style.width = w + 'px';
			grid.style.height = h + 'px';
			grid.style.transform = 'rotate(' + angle + 'deg)';
			grid.style.setProperty( '--wp-swiper-collage-turn', angle + 'deg' );
			// Image shadow: its offset turned back, so it falls as on the page.
			var shadow = /^\s*(-?[\d.]+)px\s+(-?[\d.]+)px\s+(.*)$/.exec( getComputedStyle( root ).getPropertyValue( '--wp-swiper-shadow' ) );
			if ( shadow ) {
				var x = Number( shadow[ 1 ] );
				var y = Number( shadow[ 2 ] );
				var back = -turn;
				grid.style.setProperty( '--wp-swiper-shadow', Math.round( ( x * Math.cos( back ) - y * Math.sin( back ) ) * 10 ) / 10 + 'px ' + Math.round( ( x * Math.sin( back ) + y * Math.cos( back ) ) * 10 ) / 10 + 'px ' + shadow[ 3 ] );
			}
			grid.wpTurned = true;
		}

		/**
		 * Rotation, Height "Auto" (Scattered, Freeform; not a background): the
		 * block cut to the photos' top-most and bottom-most points, no empty
		 * bands above or below; the photos stay as large as they are. While a
		 * photo is moved or resized in the preview (`held`), as it was cut.
		 *
		 * @param {boolean} held The places held where they are.
		 */
		function trim( held ) {
			var places = cells.filter( function ( cell ) {
				return !! cell.style.width;
			} ).map( function ( cell ) {
				return { x: parseFloat( cell.style.left ), y: parseFloat( cell.style.top ), w: parseFloat( cell.style.width ), h: parseFloat( cell.style.height ) };
			} );
			if ( ! grid.wpInPlace || ! fills( config ) || root.classList.contains( 'wp-swiper--background' ) || ! places.length ) {
				trimmed = null;
				return;
			}
			var width = grid.offsetWidth;
			var height = grid.offsetHeight;
			if ( ! held || ! trimmed ) {
				// Turned about the grid's middle, as on the page.
				var bounds = turnedBounds( places.map( function ( r ) {
					return { x: r.x - width / 2, y: r.y - height / 2, w: r.w, h: r.h };
				} ), grid.wpAngle );
				trimmed = { height: Math.max( 1, bounds.height ), top: height / 2 + bounds.y - bounds.height / 2 };
			}
			var extra = root.offsetHeight - el.offsetHeight;
			el.style.height = trimmed.height + 'px';
			grid.style.top = -trimmed.top + 'px';
			if ( stretched ) {
				root.style.height = trimmed.height + extra + 'px';
			}
		}

		// The image file the browser picks: the width it is drawn at (cropped to
		// the place). Turned: the photo upright, as large as covers its place
		// (collage.css), its caption no wider than fits in the place.
		/*
		 * An order for this device as places() gives it: -1 (Content in a
		 * place) where it is in it, so moved with the photos it moves.
		 */
		// `at`: Content in a place's place in it (from 0) when the list has it no
		// longer (the preview drops repeated photos first, which would move it).
		function takeOrder( list, at ) {
			at = undefined === at ? list.indexOf( -1 ) : at;
			if ( at !== -1 && config.content ) {
				config.content.place = config.content.place || {};
				config.content.place[ current ] = at + 1;
			}
			config.order = config.order || {};
			config.order[ current ] = list.filter( function ( real ) {
				return real !== -1;
			} );
		}

		function sizeImages( cell ) {
			var w = cell.clientWidth;
			var h = cell.clientHeight;
			var angle = grid.wpAngle || 0;
			if ( angle ) {
				var turn = angle * Math.PI / 180;
				var cos = Math.abs( Math.cos( turn ) );
				var sin = Math.abs( Math.sin( turn ) );
				var upright = { w: w * cos + h * sin, h: w * sin + h * cos };
				cell.style.setProperty( '--wp-swiper-collage-uw', upright.w + 'px' );
				cell.style.setProperty( '--wp-swiper-collage-uh', upright.h + 'px' );
				cell.style.setProperty( '--wp-swiper-collage-cw', Math.min( w, h ) / ( cos + sin ) + 'px' );
				// Content in a place: upright too, as large as fits inside it, its
				// proportions (collage.css).
				var inside = Math.min( w / ( w * cos + h * sin ), h / ( w * sin + h * cos ) );
				cell.style.setProperty( '--wp-swiper-collage-iw', w * inside + 'px' );
				cell.style.setProperty( '--wp-swiper-collage-ih', h * inside + 'px' );
				w = upright.w;
				h = upright.h;
			} else {
				[ 'uw', 'uh', 'cw', 'iw', 'ih' ].forEach( function ( name ) {
					cell.style.removeProperty( '--wp-swiper-collage-' + name );
				} );
			}
			if ( cell.wpReal === -1 ) {
				fitContent( cell );
				// Its image not loaded yet: fitted again once it is.
				[].forEach.call( cell.querySelectorAll( 'img.wp-swiper-content__image' ), function ( img ) {
					if ( ! img.complete && ! img.wpSwiperFit ) {
						img.wpSwiperFit = true;
						img.addEventListener( 'load', function () {
							fitContent( cell );
						} );
					}
				} );
			}
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
			// Turned (or back): the box to lay out in first.
			orient();
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
					var scale = held ? held.scale : scatterFit( scatter, width, gridHeight, gap, grid.wpAngle );
					var rects = scatterRects( move ? move.layout : scatter, scale, gap );
					var origin = held ? held : turnedOrigin( rects, grid.wpAngle, width, gridHeight );
					var originX = origin.x;
					var originY = origin.y;
					cells.forEach( function ( cell, c ) {
						var r = rects[ move ? scatterMovedIndex( c, moving.from, move.index ) : c ];
						if ( r ) {
							set( cell, originX + r.x, originY + r.y, r.w, r.h );
						}
					} );
					scatterView = { scale: scale, x: originX, y: originY, rects: rects };
					trim( !! held );
				}
			} else if ( layout === 'freeform' ) {
				// The block keeps its Height; the frame as large as fits it, in its
				// middle (held where it is while a photo is moved in the preview).
				mosaicLines = [];
				if ( free ) {
					var view = freeHeld || freeFit( free, width, grid.clientHeight, grid.wpAngle );
					cells.forEach( function ( cell, c ) {
						var r = free.places[ c ];
						if ( r ) {
							set( cell, view.x + r.x * view.scale, view.y + r.y * view.scale, r.w * view.scale, r.h * view.scale );
						}
					} );
					freeView = view;
					trim( !! freeHeld );
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
			// Content in a place takes one of them (one more than the photos at most;
			// not Justified rows or Masonry, which have none: no photo twice there).
			var withContent = !! contentNode && layout !== 'justified' && layout !== 'masonry';
			var count = template ? mosaicLeaves( template ).length : Math.min( slides.length + ( withContent ? 1 : 0 ), Math.max( 1, Number( config.count[ current ] ) || 1 ) );
			var content = contentPlace( count );
			// The places for photos, in turn.
			var shown = 0;

			// The content back in its holder first: not lost with the old places.
			if ( contentNode && contentNode.parentNode !== contentPool ) {
				contentPool.appendChild( contentNode );
			}
			grid.innerHTML = '';
			grid.className = 'wp-swiper-collage__grid wp-swiper-collage__grid--' + layout + ( positioned ? ' wp-swiper-collage__grid--free' : '' ) + ( cuts ? ' wp-swiper-collage__grid--cuts' : '' ) + ( fills( config ) ? ' wp-swiper-collage__grid--ratio' : '' ) + ( stretched ? ' wp-swiper-collage__grid--stretch' : '' );
			grid.style.cssText = '';
			cells = [];
			for ( var i = 0; i < count; i++ ) {
				var cell = document.createElement( 'div' );
				cell.className = 'wp-swiper-collage__cell';
				if ( i === content ) {
					// The content (-1 in the order the preview gets: places()):
					// square, for the styles that shape a place after its photo.
					cell.classList.add( 'wp-swiper-collage__cell--content' );
					cell.wpRatio = 1;
					cell.wpReal = -1;
					// As a photo is, for the preview, Rotation and the swap (collage.css).
					cell.appendChild( contentNode );
				} else {
					var real = order[ shown++ % slides.length ];
					// A place's proportions stay those of the photo it got first.
					cell.wpRatio = ratioOf( slides[ real ] );
					cell.wpReal = real;
					cell.appendChild( copy( real ) );
				}
				grid.appendChild( cell );
				cells.push( cell );
			}
			// The photos not in a place wait their turn, in shuffle order.
			queue = order.slice( shown );

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
				orient();
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
			// The places with photos (Content in a place stays as it is).
			var photos = cells.filter( function ( cell ) {
				return cell.wpReal !== -1;
			} );
			if ( ! queue.length || ! photos.length ) {
				return;
			}
			var index;
			do {
				index = Math.floor( Math.random() * photos.length );
			} while ( photos.length > 1 && index === lastSwapped );
			lastSwapped = index;

			var cell = photos[ index ];
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
					orient();
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
			// A new order for this device (as places() gives it: -1, Content in a
			// place, where it is now in it; moved with the photos, it moves).
			setOrder: function ( list, content ) {
				takeOrder( list, content );
				build();
				schedule();
			},
			// Content in a place: where it is on this device (from 1; 0: none).
			contentPlace: function () {
				var at = -1;
				cells.forEach( function ( cell, i ) {
					at = cell.wpReal === -1 ? i : at;
				} );
				return at + 1;
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
				if ( state.contentPlace > 0 && config.content ) {
					config.content.place = config.content.place || {};
					config.content.place[ current ] = state.contentPlace;
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
					var scale = Math.min( scatterView.scale, scatterFit( layouts, width, height, gap, grid.wpAngle ) );
					var origin = turnedOrigin( scatterAllRects( layouts, scale, gap ), grid.wpAngle, width, height );
					moving = { from: from, moves: moves, shown: -1, scale: scale, x: origin.x, y: origin.y };
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
					return index > 0 && index < cells.length && cells.length > COLLAGE_MIN_COUNT && cells[ index ].wpReal !== -1;
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
					takeOrder( list );
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
					return index >= 0 && index < cells.length && cells.length > COLLAGE_MIN_COUNT && cells[ index ].wpReal !== -1;
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
					takeOrder( list );
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
