/**
 * The Art effect in the preview (WP_Swiper_Preview, its frame only; apart
 * from the collage's preview-collage.js): a photo dragged onto another
 * piece swaps with it. While dragging, a small copy of the photo in a white
 * frame follows the pointer, its piece is an empty shape, and over another
 * piece both show the swap before it is made; Escape gives it up. On the
 * drop the Art block takes the new order for the device in view (art.js
 * setOrder()) and the editor stores it (admin.js: an "art-order" message
 * with the photos' IDs).
 */
( function () {
	'use strict';

	var DISTANCE = 5; // px moved before a press becomes a drag.
	var GHOST = 140; // The copy's longer side, px.
	var drag = null;
	var swallow = false;

	function stopNextClick() {
		var stop = function ( e ) {
			e.stopPropagation();
			e.preventDefault();
		};
		document.addEventListener( 'click', stop, true );
		setTimeout( function () {
			document.removeEventListener( 'click', stop, true );
		} );
	}

	function pieceAt( x, y, stage ) {
		var node = document.elementFromPoint( x, y );
		var piece = node && node.closest( '.wp-swiper-art__piece' );
		return piece && piece.parentNode === stage ? piece : null;
	}

	// The piece's own photo (not one shown for the drag).
	function ownPhoto( piece ) {
		return [].filter.call( piece.childNodes, function ( node ) {
			return ! ( node.classList && node.classList.contains( 'wp-swiper-art__shown' ) );
		} );
	}

	// `nodes` shown in `piece` over its own photo, for the time of the drag.
	function show( piece, nodes ) {
		var box = document.createElement( 'div' );
		box.className = 'wp-swiper-art__shown';
		nodes.forEach( function ( node ) {
			var copy = node.cloneNode( true );
			if ( copy.querySelectorAll ) {
				[].forEach.call( copy.querySelectorAll( 'img[loading]' ), function ( img ) {
					img.removeAttribute( 'loading' );
				} );
				if ( copy.matches && copy.matches( 'img[loading]' ) ) {
					copy.removeAttribute( 'loading' );
				}
			}
			box.appendChild( copy );
		} );
		piece.appendChild( box );
	}

	function unshow( piece ) {
		[].forEach.call( piece.querySelectorAll( ':scope > .wp-swiper-art__shown' ), function ( node ) {
			node.parentNode.removeChild( node );
		} );
	}

	function makeGhost( piece ) {
		var rect = piece.getBoundingClientRect();
		var scale = GHOST / Math.max( rect.width, rect.height, 1 );
		var ghost = document.createElement( 'div' );
		ghost.className = 'wp-swiper-art-ghost';
		ghost.style.width = Math.round( rect.width * scale ) + 'px';
		ghost.style.height = Math.round( rect.height * scale ) + 'px';
		var img = piece.querySelector( 'img' );
		if ( img ) {
			var copy = img.cloneNode( true );
			copy.removeAttribute( 'loading' );
			ghost.appendChild( copy );
		}
		document.body.appendChild( ghost );
		return ghost;
	}

	function moveGhost( x, y ) {
		if ( drag && drag.ghost ) {
			drag.ghost.style.transform = 'translate(' + ( x - drag.ghost.offsetWidth / 2 ) + 'px, ' + ( y - drag.ghost.offsetHeight / 2 ) + 'px)';
		}
	}

	function hover( target ) {
		if ( target === drag.target ) {
			return;
		}
		if ( drag.target ) {
			drag.target.classList.remove( 'is-drop-target' );
			unshow( drag.target );
			unshow( drag.piece );
			drag.piece.classList.add( 'is-dragged' );
		}
		drag.target = target;
		if ( target ) {
			target.classList.add( 'is-drop-target' );
			show( drag.piece, ownPhoto( target ) );
			show( target, drag.photo );
			drag.piece.classList.remove( 'is-dragged' );
		}
	}

	function end() {
		if ( ! drag ) {
			return;
		}
		if ( drag.moved ) {
			hover( null );
			unshow( drag.piece );
			drag.piece.classList.remove( 'is-dragged', 'is-source' );
			if ( drag.ghost ) {
				drag.ghost.parentNode.removeChild( drag.ghost );
			}
			document.documentElement.classList.remove( 'wp-swiper-art-dragging' );
		}
		drag = null;
	}

	document.addEventListener( 'pointerdown', function ( e ) {
		var piece = e.button === 0 && e.target.closest( '.wp-swiper-art__piece' );
		var block = piece && piece.closest( '.wp-swiper-art' );
		var api = block && block.wpSwiperArt;
		if ( ! api || ! api.setOrder ) {
			return;
		}
		drag = { piece: piece, stage: piece.parentNode, api: api, x: e.clientX, y: e.clientY, moved: false, target: null, photo: null, ghost: null };
	} );

	document.addEventListener( 'pointermove', function ( e ) {
		if ( ! drag ) {
			return;
		}
		if ( ! drag.moved ) {
			if ( Math.abs( e.clientX - drag.x ) < DISTANCE && Math.abs( e.clientY - drag.y ) < DISTANCE ) {
				return;
			}
			drag.moved = true;
			drag.photo = ownPhoto( drag.piece );
			drag.ghost = makeGhost( drag.piece );
			drag.piece.classList.add( 'is-source', 'is-dragged' );
			document.documentElement.classList.add( 'wp-swiper-art-dragging' );
		}
		moveGhost( e.clientX, e.clientY );
		var target = pieceAt( e.clientX, e.clientY, drag.stage );
		hover( target === drag.piece ? null : target );
	} );

	document.addEventListener( 'pointerup', function () {
		if ( ! drag ) {
			if ( swallow ) {
				swallow = false;
				stopNextClick();
			}
			return;
		}
		// No click after a drag (a photo's link would open).
		if ( drag.moved ) {
			stopNextClick();
		}
		if ( drag.moved && drag.target ) {
			var pieces = [].slice.call( drag.stage.querySelectorAll( ':scope > .wp-swiper-art__piece' ) );
			var from = pieces.indexOf( drag.piece );
			var to = pieces.indexOf( drag.target );
			var api = drag.api;
			var list = api.pieces().slice();
			var swap = list[ from ];
			list[ from ] = list[ to ];
			list[ to ] = swap;
			// Each photo once (with fewer photos than pieces they repeat).
			var seen = {};
			list = list.filter( function ( real ) {
				return seen[ real ] ? false : ( seen[ real ] = true );
			} );
			end();
			api.setOrder( list );
			if ( window.parent && window.parent !== window ) {
				window.parent.postMessage( {
					wpSwiper: 'art-order',
					device: api.device(),
					ids: list.map( function ( real ) {
						return api.ids[ real ] || 0;
					} ).filter( function ( id ) {
						return id > 0;
					} )
				}, window.location.origin );
			}
			return;
		}
		end();
	} );

	/*
	 * Ring: the middle circle's edge (the gap round it) is a handle; dragged
	 * in or out, the circle grows or shrinks and the ring follows (art.js
	 * setCenter()), the ring kept at least MIN_RING thick. On hover and while
	 * dragged a white line marks the edge and the cursor points away from the
	 * middle. On the release the size is kept for the device in view and the
	 * editor stores it (admin.js: an "art-center" message). Caught before a
	 * photo's drag (capture), so the edge is never taken for a photo.
	 */
	var MIN_CENTER = 20; // % of the whole.
	var MAX_CENTER = 75;
	var MIN_RING = 20; // px.
	var resize = null;

	// How far a point is from the middle: round a circle; along the axes for
	// a square (Grid + square), whose edge is at the same distance all along.
	function reach( ring, dx, dy ) {
		return ring.square ? Math.max( Math.abs( dx ), Math.abs( dy ) ) : Math.hypot( dx, dy );
	}

	// The Ring (Ring + text, the grids) under the pointer and how far from its middle, if at its edge.
	function edgeAt( e ) {
		var node = document.elementFromPoint( e.clientX, e.clientY );
		var block = node && node.closest( '.wp-swiper-art--ring, .wp-swiper-art--ring-text, .wp-swiper-art--grid-circle, .wp-swiper-art--grid-circle-text, .wp-swiper-art--grid-square, .wp-swiper-art--grid-square-text' );
		var api = block && block.wpSwiperArt;
		var ring = block && block.wpSwiperArtRing;
		if ( ! api || ! api.setCenter || ! ring ) {
			return null;
		}
		var stage = block.querySelector( '.wp-swiper-art__stage' );
		var rect = stage.getBoundingClientRect();
		var dx = e.clientX - rect.left - ring.cx;
		var dy = e.clientY - rect.top - ring.cy;
		var distance = reach( ring, dx, dy );
		var edge = ring.middle + ring.gap / 2;
		if ( Math.abs( distance - edge ) > Math.max( 8, ring.gap / 2 + 4 ) ) {
			return null;
		}
		return { block: block, stage: stage, api: api, ring: ring, dx: dx, dy: dy };
	}

	// A resize cursor pointing away from the middle.
	function edgeCursor( dx, dy ) {
		var angle = ( Math.atan2( dy, dx ) * 180 / Math.PI + 360 ) % 180;
		if ( angle < 22.5 || angle >= 157.5 ) {
			return 'ew-resize';
		}
		if ( angle < 67.5 ) {
			return 'nwse-resize';
		}
		return angle < 112.5 ? 'ns-resize' : 'nesw-resize';
	}

	// The white line on the edge (one per Ring), where its edge is now.
	function markEdge( block, on ) {
		var stage = block.querySelector( '.wp-swiper-art__stage' );
		var line = stage.querySelector( ':scope > .wp-swiper-art__edge' );
		if ( ! line ) {
			line = document.createElement( 'div' );
			line.className = 'wp-swiper-art__edge';
			stage.appendChild( line );
		}
		var ring = block.wpSwiperArtRing;
		var radius = ring.middle + ring.gap / 2;
		line.style.left = ( ring.cx - radius ) + 'px';
		line.style.top = ( ring.cy - radius ) + 'px';
		line.style.width = line.style.height = ( radius * 2 ) + 'px';
		line.classList.toggle( 'is-square', !! ring.square );
		line.classList.toggle( 'is-shown', on );
	}

	var hovered = null;
	function hoverEdge( found, e ) {
		var block = found ? found.block : null;
		if ( hovered && hovered !== block ) {
			markEdge( hovered, false );
		}
		hovered = block;
		document.documentElement.classList.toggle( 'wp-swiper-art-edge', !! found );
		if ( found ) {
			document.documentElement.style.setProperty( '--wp-swiper-art-edge-cursor', edgeCursor( found.dx, found.dy ) );
			markEdge( block, true );
		}
	}

	document.addEventListener( 'pointerdown', function ( e ) {
		if ( e.button !== 0 || drag ) {
			return;
		}
		var found = edgeAt( e );
		if ( ! found ) {
			return;
		}
		// Not a photo's drag (preview-art.js above), nor a text selection.
		e.stopImmediatePropagation();
		e.preventDefault();
		resize = found;
		resize.share = null;
		document.documentElement.classList.add( 'wp-swiper-art-resizing' );
	}, true );

	document.addEventListener( 'pointermove', function ( e ) {
		if ( resize ) {
			var rect = resize.stage.getBoundingClientRect();
			var ring = resize.block.wpSwiperArtRing;
			var dx = e.clientX - rect.left - ring.cx;
			var dy = e.clientY - rect.top - ring.cy;
			var max = Math.min( MAX_CENTER, ( ring.outer - ring.gap - MIN_RING ) / ring.outer * 100 );
			var share = ( reach( ring, dx, dy ) - ring.gap / 2 ) / ring.outer * 100;
			share = Math.round( Math.max( MIN_CENTER, Math.min( max, share ) ) * 10 ) / 10;
			if ( share !== resize.share ) {
				resize.share = share;
				resize.api.setCenter( share );
			}
			document.documentElement.style.setProperty( '--wp-swiper-art-edge-cursor', edgeCursor( dx, dy ) );
			markEdge( resize.block, true );
			return;
		}
		if ( ! drag ) {
			hoverEdge( edgeAt( e ), e );
		}
	} );

	document.addEventListener( 'pointerup', function () {
		if ( ! resize ) {
			return;
		}
		var done = resize;
		resize = null;
		document.documentElement.classList.remove( 'wp-swiper-art-resizing' );
		stopNextClick();
		if ( null !== done.share && window.parent && window.parent !== window ) {
			window.parent.postMessage( { wpSwiper: 'art-center', device: done.api.device(), center: done.share }, window.location.origin );
		}
	} );

	/*
	 * Undo / redo in the editor (admin.js calls it, as it stores them): the
	 * Art blocks shown for `state.device` take its order (photo IDs, each to
	 * the first slide with it not taken yet, as WP_Swiper_Art::orders() does)
	 * and middle circle at once. False when none did (the editor then updates
	 * the preview).
	 */
	window.wpSwiperRestoreArt = function ( state ) {
		var done = false;
		var missed = false;
		[].forEach.call( document.querySelectorAll( '.wp-swiper-art' ), function ( block ) {
			var api = block.wpSwiperArt;
			if ( ! api || ! api.setOrder || api.device() !== state.device ) {
				return;
			}
			var used = {};
			var order = [];
			( state.order || [] ).forEach( function ( id ) {
				for ( var real = 0; real < api.ids.length; real++ ) {
					if ( api.ids[ real ] === id && ! used[ real ] ) {
						used[ real ] = true;
						order.push( real );
						return;
					}
				}
			} );
			if ( api.setCenter && typeof state.center === 'number' && isFinite( state.center ) ) {
				api.setCenter( state.center );
			}
			// Grid shapes: its layout (none kept, the default one: the editor updates the preview).
			if ( api.setGrid && api.grid() && typeof state.cells === 'string' && window.wpSwiperArtCells ) {
				var grid = window.wpSwiperArtCells.parse( state.cells );
				var now = api.grid();
				if ( ! grid || grid.columns !== now.columns || grid.rows !== now.rows ) {
					missed = true;
					return;
				}
				api.setGrid( window.wpSwiperArtCells.number( grid ) );
				if ( block.wpSwiperArtEditor ) {
					block.wpSwiperArtEditor.selected = -1;
					block.wpSwiperArtEditor.corner = null;
					refresh( block.wpSwiperArtEditor );
				}
			}
			api.setOrder( order );
			done = true;
		} );
		return done && ! missed;
	};

	/*
	 * Grid shapes (art.js gridShapes()): the Shapes / Photos buttons over the
	 * stage. Shapes (the first time): the grid shows; a drag from one cell to
	 * another joins the cells of that rectangle to the first cell's shape (a
	 * new shape from an empty cell); a click selects the cell's shape (an
	 * empty cell becomes a shape of its own), its panel sets its corners (a
	 * share of half a cell, or Radius), splits it into its cells or empties
	 * them. Each change is drawn at once and kept by the editor (admin.js: an
	 * "art-cells" message with the layout). Photos: they swap by dragging, as
	 * in the other Styles. Caught before a photo's drag (capture).
	 */
	var I18N = window.wpSwiperPreviewI18n || {};
	var MODE_KEY = 'wpSwiperArtCellsMode';
	var SVG_NS = 'http://www.w3.org/2000/svg';
	var cellsMode = 'shapes';
	try {
		cellsMode = window.sessionStorage.getItem( MODE_KEY ) === 'photos' ? 'photos' : 'shapes';
	} catch ( e ) {}
	var editors = [];
	var paint = null;

	function word( key, fallback ) {
		return I18N[ key ] || fallback;
	}

	function cellsTools() {
		return window.wpSwiperArtCells;
	}

	function button( label, className ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = className;
		b.textContent = label;
		return b;
	}

	function makeEditor( block ) {
		var api = block.wpSwiperArt;
		var stage = block.querySelector( '.wp-swiper-art__stage' );
		var ed = { block: block, api: api, stage: stage, selected: -1 };

		ed.tools = document.createElement( 'div' );
		ed.tools.className = 'wp-swiper-art-tools';
		ed.shapesButton = button( word( 'shapes', 'Shapes' ), 'wp-swiper-art-tools__mode' );
		ed.photosButton = button( word( 'photos', 'Photos' ), 'wp-swiper-art-tools__mode' );
		ed.tools.appendChild( ed.shapesButton );
		ed.tools.appendChild( ed.photosButton );

		// The selected shape's panel.
		ed.panel = document.createElement( 'div' );
		ed.panel.className = 'wp-swiper-art-tools__panel';
		ed.panel.hidden = true;
		var corners = document.createElement( 'label' );
		corners.className = 'wp-swiper-art-tools__corners';
		// "Corners" (the shape's), or "Corner" (the one selected).
		ed.rangeLabel = document.createTextNode( word( 'corners', 'Corners' ) + ' ' );
		corners.appendChild( ed.rangeLabel );
		ed.range = document.createElement( 'input' );
		ed.range.type = 'range';
		ed.range.min = 0;
		ed.range.max = 100;
		ed.range.step = 5;
		corners.appendChild( ed.range );
		ed.rangeValue = document.createElement( 'span' );
		ed.rangeValue.className = 'wp-swiper-art-tools__value';
		corners.appendChild( ed.rangeValue );
		ed.panel.appendChild( corners );
		ed.radiusButton = button( word( 'asRadius', 'As Radius' ), 'wp-swiper-art-tools__action' );
		ed.splitButton = button( word( 'split', 'Split into cells' ), 'wp-swiper-art-tools__action' );
		ed.emptyButton = button( word( 'empty', 'Empty' ), 'wp-swiper-art-tools__action' );
		ed.panel.appendChild( ed.radiusButton );
		ed.panel.appendChild( ed.splitButton );
		ed.panel.appendChild( ed.emptyButton );
		ed.tools.appendChild( ed.panel );
		block.appendChild( ed.tools );

		ed.overlay = document.createElementNS( SVG_NS, 'svg' );
		ed.overlay.setAttribute( 'class', 'wp-swiper-art-cells' );
		ed.overlay.setAttribute( 'aria-hidden', 'true' );
		stage.appendChild( ed.overlay );

		ed.shapesButton.addEventListener( 'click', function () {
			setMode( 'shapes' );
		} );
		ed.photosButton.addEventListener( 'click', function () {
			setMode( 'photos' );
		} );
		ed.range.addEventListener( 'input', function () {
			setRadius( ed, Number( ed.range.value ), false );
		} );
		ed.range.addEventListener( 'change', function () {
			setRadius( ed, Number( ed.range.value ), true );
		} );
		ed.radiusButton.addEventListener( 'click', function () {
			setRadius( ed, null, true );
		} );
		ed.splitButton.addEventListener( 'click', function () {
			change( ed, function ( grid, shape ) {
				var next = grid.count;
				grid.cells = grid.cells.map( function ( cell ) {
					return cell === shape ? next++ : cell;
				} );
				delete grid.radii[ shape ];
			} );
		} );
		ed.emptyButton.addEventListener( 'click', function () {
			change( ed, function ( grid, shape ) {
				grid.cells = grid.cells.map( function ( cell ) {
					return cell === shape ? -1 : cell;
				} );
				delete grid.radii[ shape ];
			} );
			ed.selected = -1;
			ed.corner = null;
			refresh( ed );
		} );

		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( function () {
				refresh( ed );
			} ).observe( stage );
		}
		window.addEventListener( 'resize', function () {
			refresh( ed );
		} );
		editors.push( ed );
		refresh( ed );
		return ed;
	}

	// A copy of the layout as drawn, to change.
	function layoutOf( ed ) {
		var grid = ed.api.grid();
		var radii = {};
		Object.keys( grid.radii || {} ).forEach( function ( shape ) {
			radii[ shape ] = grid.radii[ shape ];
		} );
		var corners = {};
		Object.keys( grid.corners || {} ).forEach( function ( at ) {
			corners[ at ] = grid.corners[ at ];
		} );
		var count = 0;
		grid.cells.forEach( function ( cell ) {
			count = Math.max( count, cell + 1 );
		} );
		return { columns: grid.columns, rows: grid.rows, cells: grid.cells.slice(), radii: radii, corners: corners, count: count };
	}

	// The outward corners of the shapes as drawn ("x.y" => true): a corner's
	// own radius kept only while it is one (a shape changed: those gone, forgotten).
	function cornersDrawn( ed ) {
		var drawn = {};
		( ed.block.wpSwiperArtOutlines || [] ).forEach( function ( outline ) {
			( outline && outline.corners || [] ).forEach( function ( corner ) {
				drawn[ corner.key ] = true;
			} );
		} );
		return drawn;
	}

	// A new layout: its shapes split where they no longer touch, drawn, kept by the editor.
	function apply( ed, grid, keep ) {
		grid = cellsTools().split( grid );
		ed.api.setGrid( grid );
		if ( keep !== false ) {
			send( ed );
		}
		refresh( ed );
	}

	function send( ed ) {
		var grid = ed.api.grid();
		var drawn = cornersDrawn( ed );
		Object.keys( grid.corners || {} ).forEach( function ( at ) {
			if ( ! drawn[ at ] ) {
				delete grid.corners[ at ];
			}
		} );
		if ( ed.corner && ! drawn[ ed.corner ] ) {
			ed.corner = null;
		}
		if ( window.parent && window.parent !== window ) {
			window.parent.postMessage( { wpSwiper: 'art-cells', device: ed.api.device(), cells: cellsTools().text( ed.api.grid() ) }, window.location.origin );
		}
	}

	// The selected shape changed by `fn( grid, shape )`.
	function change( ed, fn ) {
		var grid = layoutOf( ed );
		var shape = ed.selected >= 0 ? grid.cells[ ed.selected ] : -1;
		if ( shape < 0 ) {
			return;
		}
		fn( grid, shape );
		apply( ed, grid );
	}

	// The selected shape's corners: a share of half a cell (null: Radius). Kept on release.
	// Or the selected corner's (null: its shape's).
	function setRadius( ed, value, keep ) {
		var grid = layoutOf( ed );
		var shape = ed.selected >= 0 ? grid.cells[ ed.selected ] : -1;
		if ( shape < 0 ) {
			return;
		}
		var own = ed.corner ? grid.corners : grid.radii;
		var key = ed.corner ? ed.corner : shape;
		if ( null === value ) {
			delete own[ key ];
		} else {
			own[ key ] = value;
		}
		ed.api.setGrid( cellsTools().number( grid ) );
		if ( keep ) {
			send( ed );
		}
		refresh( ed );
	}

	function setMode( mode ) {
		cellsMode = mode;
		try {
			window.sessionStorage.setItem( MODE_KEY, mode );
		} catch ( e ) {}
		editors.forEach( function ( ed ) {
			if ( mode !== 'shapes' ) {
				ed.selected = -1;
				ed.corner = null;
			}
			refresh( ed );
		} );
	}

	// The cell under a point (the gap after a cell counts as the cell's).
	function cellAt( ed, x, y ) {
		var g = ed.block.wpSwiperArtGrid;
		if ( ! g ) {
			return -1;
		}
		var rect = ed.stage.getBoundingClientRect();
		var cx = Math.max( 0, Math.min( g.columns - 1, Math.floor( ( x - rect.left ) / ( g.cellW + g.gap ) ) ) );
		var cy = Math.max( 0, Math.min( g.rows - 1, Math.floor( ( y - rect.top ) / ( g.cellH + g.gap ) ) ) );
		return cy * g.columns + cx;
	}

	function svgNode( name, attrs, parent ) {
		var node = document.createElementNS( SVG_NS, name );
		Object.keys( attrs ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		parent.appendChild( node );
		return node;
	}

	// The grid over the stage, the rectangle being drawn, the selected shape; the buttons and panel.
	function refresh( ed ) {
		var g = ed.block.wpSwiperArtGrid;
		var grid = ed.api.grid();
		var on = cellsMode === 'shapes';
		document.documentElement.classList.toggle( 'wp-swiper-art-cells-mode', on );
		ed.shapesButton.classList.toggle( 'is-active', on );
		ed.photosButton.classList.toggle( 'is-active', ! on );
		ed.tools.style.left = ( ed.stage.offsetLeft + 8 ) + 'px';
		ed.tools.style.top = ( ed.stage.offsetTop + 8 ) + 'px';
		while ( ed.overlay.firstChild ) {
			ed.overlay.removeChild( ed.overlay.firstChild );
		}
		ed.overlay.style.display = on && g ? '' : 'none';
		if ( ! g || ! grid ) {
			ed.panel.hidden = true;
			return;
		}
		ed.overlay.setAttribute( 'width', g.width );
		ed.overlay.setAttribute( 'height', g.height );
		ed.overlay.setAttribute( 'viewBox', '0 0 ' + g.width + ' ' + g.height );
		var box = function ( x0, y0, x1, y1 ) {
			return {
				x: x0 * ( g.cellW + g.gap ),
				y: y0 * ( g.cellH + g.gap ),
				width: ( x1 - x0 + 1 ) * ( g.cellW + g.gap ) - g.gap,
				height: ( y1 - y0 + 1 ) * ( g.cellH + g.gap ) - g.gap
			};
		};
		grid.cells.forEach( function ( cell, k ) {
			var b = box( k % g.columns, Math.floor( k / g.columns ), k % g.columns, Math.floor( k / g.columns ) );
			b[ 'class' ] = cell < 0 ? 'is-empty' : 'is-cell';
			svgNode( 'rect', b, ed.overlay );
		} );
		if ( paint && paint.ed === ed ) {
			var a = paint.from;
			var z = paint.to;
			var p = box( Math.min( a % g.columns, z % g.columns ), Math.min( Math.floor( a / g.columns ), Math.floor( z / g.columns ) ), Math.max( a % g.columns, z % g.columns ), Math.max( Math.floor( a / g.columns ), Math.floor( z / g.columns ) ) );
			p[ 'class' ] = 'is-paint';
			svgNode( 'rect', p, ed.overlay );
		}
		var shape = ed.selected >= 0 ? grid.cells[ ed.selected ] : -1;
		var outline = shape >= 0 && ed.block.wpSwiperArtOutlines ? ed.block.wpSwiperArtOutlines[ shape ] : null;
		if ( on && outline ) {
			svgNode( 'path', { d: outline.d, transform: 'translate(' + outline.box.left + ' ' + outline.box.top + ')', 'class': 'is-selected' }, ed.overlay );
		}
		// Its outward corners: a dot on each (its own radius: filled; the one selected: larger).
		if ( on && outline ) {
			( outline.corners || [] ).forEach( function ( corner ) {
				var mine = grid.corners && typeof grid.corners[ corner.key ] === 'number';
				svgNode( 'circle', {
					cx: corner.x,
					cy: corner.y,
					r: corner.key === ed.corner ? 7 : 5,
					'class': 'is-corner' + ( mine ? ' is-own' : '' ) + ( corner.key === ed.corner ? ' is-active' : '' )
				}, ed.overlay );
			} );
		}
		ed.panel.hidden = ! ( on && shape >= 0 );
		if ( shape >= 0 ) {
			var shapeOwn = grid.radii && typeof grid.radii[ shape ] === 'number';
			var cornerOwn = !! ed.corner && grid.corners && typeof grid.corners[ ed.corner ] === 'number';
			var value = cornerOwn ? grid.corners[ ed.corner ] : ( shapeOwn ? grid.radii[ shape ] : null );
			ed.rangeLabel.nodeValue = ( ed.corner ? word( 'corner', 'Corner' ) : word( 'corners', 'Corners' ) ) + ' ';
			ed.range.value = null === value ? 0 : value;
			ed.rangeValue.textContent = null === value ? word( 'radius', 'Radius' ) : value + '%';
			ed.radiusButton.textContent = ed.corner ? word( 'asShape', 'As the shape' ) : word( 'asRadius', 'As Radius' );
			ed.radiusButton.disabled = ed.corner ? ! cornerOwn : ! shapeOwn;
		}
	}

	// The selected shape's corner dot under a point ("x.y"), if any.
	function cornerAt( ed, x, y ) {
		var grid = ed.api.grid();
		var shape = ed.selected >= 0 && grid ? grid.cells[ ed.selected ] : -1;
		var outline = shape >= 0 && ed.block.wpSwiperArtOutlines ? ed.block.wpSwiperArtOutlines[ shape ] : null;
		if ( ! outline ) {
			return null;
		}
		var rect = ed.stage.getBoundingClientRect();
		var found = null;
		var best = 12;
		( outline.corners || [] ).forEach( function ( corner ) {
			var distance = Math.hypot( x - rect.left - corner.x, y - rect.top - corner.y );
			if ( distance < best ) {
				best = distance;
				found = corner.key;
			}
		} );
		return found;
	}

	function editorOf( target ) {
		for ( var i = 0; i < editors.length; i++ ) {
			if ( editors[ i ].stage.contains( target ) ) {
				return editors[ i ];
			}
		}
		return null;
	}

	document.addEventListener( 'pointerdown', function ( e ) {
		if ( e.button !== 0 || cellsMode !== 'shapes' ) {
			return;
		}
		var ed = editorOf( e.target );
		if ( ! ed ) {
			return;
		}
		e.stopImmediatePropagation();
		e.preventDefault();
		// A dot on the selected shape's corner: that corner selected (again: none).
		var corner = cornerAt( ed, e.clientX, e.clientY );
		if ( corner ) {
			ed.corner = ed.corner === corner ? null : corner;
			stopNextClick();
			refresh( ed );
			return;
		}
		var cell = cellAt( ed, e.clientX, e.clientY );
		paint = { ed: ed, from: cell, to: cell, moved: false };
		refresh( ed );
	}, true );

	document.addEventListener( 'pointermove', function ( e ) {
		if ( ! paint ) {
			return;
		}
		var cell = cellAt( paint.ed, e.clientX, e.clientY );
		if ( cell !== paint.to ) {
			paint.to = cell;
			paint.moved = true;
			refresh( paint.ed );
		}
	} );

	document.addEventListener( 'pointerup', function () {
		if ( ! paint ) {
			return;
		}
		var done = paint;
		paint = null;
		stopNextClick();
		var ed = done.ed;
		var grid = layoutOf( ed );
		var columns = grid.columns;
		var first = grid.cells[ done.from ];
		if ( done.from === done.to ) {
			// A click: its shape selected (again: none); an empty cell a shape of its own.
			if ( first < 0 ) {
				grid.cells[ done.from ] = grid.count;
				ed.selected = done.from;
				ed.corner = null;
				apply( ed, grid );
				return;
			}
			// On the selected shape with a corner selected: back to the whole shape.
			var same = ed.selected >= 0 && grid.cells[ ed.selected ] === first;
			if ( same && ed.corner ) {
				ed.corner = null;
			} else {
				ed.selected = same ? -1 : done.from;
				ed.corner = null;
			}
			refresh( ed );
			return;
		}
		ed.corner = null;
		// A drag: the rectangle joins the first cell's shape (a new one from an empty cell).
		var shape = first >= 0 ? first : grid.count;
		var x0 = Math.min( done.from % columns, done.to % columns );
		var x1 = Math.max( done.from % columns, done.to % columns );
		var y0 = Math.min( Math.floor( done.from / columns ), Math.floor( done.to / columns ) );
		var y1 = Math.max( Math.floor( done.from / columns ), Math.floor( done.to / columns ) );
		for ( var y = y0; y <= y1; y++ ) {
			for ( var x = x0; x <= x1; x++ ) {
				grid.cells[ y * columns + x ] = shape;
			}
		}
		ed.selected = done.from;
		apply( ed, grid );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}
		if ( paint ) {
			var ed = paint.ed;
			paint = null;
			refresh( ed );
			return;
		}
		editors.forEach( function ( one ) {
			one.selected = -1;
			one.corner = null;
			refresh( one );
		} );
	} );

	// The Grid shapes blocks, once art.js has set them up.
	function setupEditors() {
		[].forEach.call( document.querySelectorAll( '.wp-swiper-art--grid-shapes' ), function ( block ) {
			if ( block.wpSwiperArt && block.wpSwiperArt.setGrid && ! block.wpSwiperArtEditor && cellsTools() ) {
				block.wpSwiperArtEditor = makeEditor( block );
			}
		} );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', setupEditors );
	} else {
		setupEditors();
	}
	window.addEventListener( 'load', setupEditors );

	document.addEventListener( 'pointercancel', end );
	document.addEventListener( 'keydown', function ( e ) {
		if ( drag && e.key === 'Escape' ) {
			swallow = drag.moved;
			end();
		}
	} );
	// The photos are not dragged out as files or links.
	document.addEventListener( 'dragstart', function ( e ) {
		if ( e.target.closest && e.target.closest( '.wp-swiper-art__piece' ) ) {
			e.preventDefault();
		}
	} );
}() );
