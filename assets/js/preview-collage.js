/**
 * Collage in the preview (WP_Swiper_Preview, its frame only): a photo
 * dragged onto another place swaps with it. While dragging, a small copy of
 * the photo follows the pointer and its place is an empty frame; over
 * another place, the two show the swap before it is made (each with the
 * other's photo), back as they were once the pointer leaves. On the drop
 * the collage takes the new order for the device in view (collage.js
 * collage() setOrder()) and the editor stores it (admin.js: a message with
 * the photos' IDs). Scattered: dropped on a dashed frame at an arm's end,
 * the photo moves there instead (makeSlots()).
 */
( function () {
	'use strict';

	var DISTANCE = 5; // px moved before a press becomes a drag.
	var GHOST = 140; // The copy's longer side, px.
	var drag = null;
	// A drag given up with Escape: its release is no click either.
	var swallow = false;

	function stopNextClick() {
		var stop = function ( e ) {
			e.stopPropagation();
			e.preventDefault();
		};
		document.addEventListener( 'click', stop, true );
		// The click comes right after the pointerup, if at all.
		setTimeout( function () {
			document.removeEventListener( 'click', stop, true );
		} );
	}

	function cellAt( x, y, grid ) {
		var node = document.elementFromPoint( x, y );
		var cell = node && node.closest( '.wp-swiper-collage__cell' );
		return cell && cell.parentNode === grid ? cell : null;
	}

	// The photo a place shows now (the last one: a swap may be fading in over it).
	function itemOf( cell ) {
		var items = cell.querySelectorAll( ':scope > .wp-swiper-collage__item:not(.wp-swiper-collage__shown)' );
		return items[ items.length - 1 ] || null;
	}

	// `item` shown in `cell` over its own photo, for the time of the drag.
	function show( cell, item ) {
		if ( ! item ) {
			return;
		}
		var copy = item.cloneNode( true );
		copy.classList.remove( 'is-entering' );
		copy.classList.add( 'wp-swiper-collage__shown' );
		// Its file is loaded already: drawn at once, not when "lazy" gets to it.
		[].forEach.call( copy.querySelectorAll( 'img[loading]' ), function ( img ) {
			img.removeAttribute( 'loading' );
		} );
		cell.appendChild( copy );
	}

	function unshow( cell ) {
		[].forEach.call( cell.querySelectorAll( ':scope > .wp-swiper-collage__shown' ), function ( node ) {
			node.parentNode.removeChild( node );
		} );
	}

	// The small copy of the dragged photo under the pointer.
	function makeGhost( cell, item ) {
		var rect = cell.getBoundingClientRect();
		var scale = GHOST / Math.max( rect.width, rect.height, 1 );
		var ghost = document.createElement( 'div' );
		ghost.className = 'wp-swiper-collage-ghost';
		ghost.style.width = Math.round( rect.width * scale ) + 'px';
		ghost.style.height = Math.round( rect.height * scale ) + 'px';
		var img = item && item.querySelector( 'img' );
		if ( img ) {
			var copy = img.cloneNode( true );
			copy.removeAttribute( 'loading' );
			ghost.appendChild( copy );
		}
		document.body.appendChild( ghost );
		return ghost;
	}

	// Centred on the pointer (it lets the pointer through: the place under it is found).
	function moveGhost( x, y ) {
		if ( drag && drag.ghost ) {
			drag.ghost.style.transform = 'translate(' + ( x - drag.ghost.offsetWidth / 2 ) + 'px, ' + ( y - drag.ghost.offsetHeight / 2 ) + 'px)';
		}
	}

	// Over another place (or none): the swap shown there, or undone.
	function hover( target ) {
		if ( target === drag.target ) {
			return;
		}
		if ( drag.target ) {
			drag.target.classList.remove( 'is-drop-target' );
			unshow( drag.target );
			unshow( drag.cell );
			drag.cell.classList.add( 'is-dragged' );
		}
		drag.target = target;
		if ( target ) {
			target.classList.add( 'is-drop-target' );
			show( target, drag.item );
			show( drag.cell, itemOf( target ) );
			drag.cell.classList.remove( 'is-dragged' );
		}
	}

	// The places (not the handles or frames) in a grid, in order.
	function cellsOf( grid ) {
		return [].slice.call( grid.querySelectorAll( ':scope > .wp-swiper-collage__cell' ) );
	}

	/*
	 * Scattered: a photo can also be moved to the end of an arm (collage.js
	 * scatterMoves()). Those places are dashed frames while it is dragged;
	 * over one, the collage shows itself as it would be (the photo in it, the
	 * photos after it in its arm closed up), back once the pointer leaves.
	 */
	function makeSlots() {
		drag.slots = [];
		drag.slotEls = [];
		var from = cellsOf( drag.grid ).indexOf( drag.cell );
		if ( ! drag.api.scatter || ! drag.api.scatter.active() || from < 1 ) {
			return;
		}
		// Made smaller first where needed, so the frames fit the block too.
		drag.slots = drag.api.scatter.startMove( from );
		drag.moving = drag.slots.length > 0;
		drag.slots.forEach( function ( slot ) {
			var frame = document.createElement( 'div' );
			frame.className = 'wp-swiper-collage__slot';
			frame.style.left = slot.left + 'px';
			frame.style.top = slot.top + 'px';
			frame.style.width = slot.width + 'px';
			frame.style.height = slot.height + 'px';
			// Before the places: under them, wherever a frame runs into one.
			drag.grid.insertBefore( frame, drag.grid.firstChild );
			drag.slotEls.push( frame );
		} );
		if ( drag.moving ) {
			document.documentElement.classList.add( 'wp-swiper-collage-moving' );
		}
	}

	function inSlot( slot, x, y ) {
		return x >= slot.left && x <= slot.left + slot.width && y >= slot.top && y <= slot.top + slot.height;
	}

	// The move to frame `i` shown (-1: none, the collage as it is).
	function showSlot( i ) {
		if ( i === drag.slot ) {
			return;
		}
		if ( drag.slot >= 0 ) {
			drag.slotEls[ drag.slot ].classList.remove( 'is-active' );
		}
		drag.slot = i;
		drag.api.scatter.showMove( i );
		if ( i >= 0 ) {
			// The photo itself in its new place, framed as a place to drop on.
			drag.slotEls[ i ].classList.add( 'is-active' );
			drag.cell.classList.remove( 'is-source', 'is-dragged' );
			drag.cell.classList.add( 'is-drop-target' );
		} else {
			drag.cell.classList.remove( 'is-drop-target' );
			drag.cell.classList.add( 'is-source', 'is-dragged' );
		}
	}

	function end() {
		if ( ! drag ) {
			return;
		}
		if ( drag.moved ) {
			hover( null );
			unshow( drag.cell );
			drag.cell.classList.remove( 'is-dragged', 'is-source', 'is-drop-target' );
			drag.slotEls.forEach( function ( frame ) {
				frame.parentNode.removeChild( frame );
			} );
			document.documentElement.classList.remove( 'wp-swiper-collage-moving' );
			// Given up (or a swap): the collage as it was, fitted to the block again.
			if ( drag.moving ) {
				drag.api.scatter.endMove( -1 );
			}
			if ( drag.ghost ) {
				drag.ghost.parentNode.removeChild( drag.ghost );
			}
			document.documentElement.classList.remove( 'wp-swiper-collage-dragging' );
		}
		drag.api.hold( false );
		drag = null;
	}

	/*
	 * Scattered: a place taken out with its button (collage.js collage()
	 * scatter.removePlace()): the rest of the layout as it is, one photo
	 * fewer on screen on this device, its photo waiting with the others (the
	 * slide stays). The editor stores the layout, the order and the number
	 * (admin.js; Undo brings it back).
	 */
	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest && e.target.closest( '.wp-swiper-collage__remove' );
		if ( ! button ) {
			return;
		}
		// Not a click on the collage (the lightbox).
		e.preventDefault();
		e.stopPropagation();
		var api = button.wpApi;
		var device = api.places().device;
		var done = api.scatter.removePlace( button.wpIndex );
		if ( ! done || ! window.parent || window.parent === window ) {
			return;
		}
		window.parent.postMessage( { wpSwiper: 'collage-count', device: device, count: done.count }, window.location.origin );
		store( api, device, done.order, done.layout );
	}, true );

	document.addEventListener( 'pointerdown', function ( e ) {
		// The button that takes a place out is no photo to drag.
		if ( e.target.closest && e.target.closest( '.wp-swiper-collage__remove' ) ) {
			return;
		}
		var cell = e.button === 0 && e.target.closest( '.wp-swiper-collage__cell' );
		var block = cell && cell.closest( '.wp-swiper-collage' );
		var api = block && block.wpSwiperCollage;
		if ( ! api || ! api.setOrder ) {
			return;
		}
		drag = { cell: cell, grid: cell.parentNode, api: api, x: e.clientX, y: e.clientY, moved: false, target: null, item: null, ghost: null, slots: [], slotEls: [], slot: -1, moving: false };
		api.hold( true );
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
			drag.item = itemOf( drag.cell );
			drag.ghost = makeGhost( drag.cell, drag.item );
			// The place taken from: an empty frame (.is-source hides its own photo).
			drag.cell.classList.add( 'is-source', 'is-dragged' );
			document.documentElement.classList.add( 'wp-swiper-collage-dragging' );
			makeSlots();
		}
		moveGhost( e.clientX, e.clientY );
		var rect = drag.grid.getBoundingClientRect();
		var x = e.clientX - rect.left;
		var y = e.clientY - rect.top;
		// Over the frame shown: it stays (the places moved, the photo is in it now).
		if ( drag.slot >= 0 && inSlot( drag.slots[ drag.slot ], x, y ) ) {
			return;
		}
		showSlot( -1 );
		var target = cellAt( e.clientX, e.clientY, drag.grid );
		target = target === drag.cell ? null : target;
		hover( target );
		if ( ! target ) {
			showSlot( drag.slots.findIndex( function ( slot ) {
				return inSlot( slot, x, y );
			} ) );
		}
	} );

	document.addEventListener( 'pointerup', function () {
		if ( ! drag ) {
			if ( swallow ) {
				swallow = false;
				stopNextClick();
			}
			return;
		}
		// No click after a drag (the lightbox would open).
		if ( drag.moved ) {
			stopNextClick();
		}
		var api = drag.api;
		var places = drag.moved ? api.places() : null;
		var list = places ? places.order.slice() : [];

		// Scattered, dropped on a frame: the photo moved to the end of that arm,
		// its place with it (and so its size); the order follows the places.
		if ( drag.moved && drag.slot >= 0 ) {
			var made = api.scatter.endMove( drag.slot );
			drag.moving = false;
			end();
			if ( made ) {
				list.splice( made.index, 0, list.splice( made.from, 1 )[ 0 ] );
				var layout = api.scatter.serialize();
				api.scatter.set( layout );
				store( api, places.device, list, layout );
			}
			return;
		}

		if ( drag.moved && drag.target ) {
			var cells = cellsOf( drag.grid );
			var from = cells.indexOf( drag.cell );
			var to = cells.indexOf( drag.target );
			var swap = list[ from ];
			list[ from ] = list[ to ];
			list[ to ] = swap;
			// Each photo once (a template with more places than photos repeats them).
			var seen = {};
			list = list.filter( function ( real ) {
				return seen[ real ] ? false : ( seen[ real ] = true );
			} );
			end();
			// Scattered: each place keeps the shape of its photo, so the two
			// places swap sizes too; kept as set, the new order goes into it.
			var scattered = '';
			if ( api.scatter && api.scatter.active() ) {
				api.scatter.swap( from, to );
				scattered = api.scatter.serialize();
				api.scatter.set( scattered );
			}
			store( api, places.device, list, scattered );
			return;
		}
		end();
	} );

	// The new order (and Scattered's layout) taken by the collage and stored by the editor.
	function store( api, device, list, scattered ) {
		api.setOrder( list );
		if ( window.parent && window.parent !== window ) {
			window.parent.postMessage( {
				wpSwiper: 'collage-order',
				device: device,
				ids: list.map( function ( real ) {
					return api.ids[ real ] || 0;
				} ).filter( function ( id ) {
					return id > 0;
				} )
			}, window.location.origin );
			if ( scattered ) {
				window.parent.postMessage( { wpSwiper: 'collage-scatter', device: device, layout: scattered }, window.location.origin );
			}
		}
	}

	/*
	 * Random mosaic and the templates: each cut's line between its two parts
	 * is a handle; dragged
	 * along, the photos on either side grow and shrink (collage.js collage()
	 * mosaic.setShare()), each part at least MIN_PART of the cut's size. On the
	 * release the mosaic as it is now is kept for the device in view and the
	 * editor stores it (admin.js: a message with the cuts).
	 */
	var HANDLE = 12; // The handle's thickness, px (at least the gap).
	// Scattered's button that takes a place out: its size (preview-dev.css) and words.
	var REMOVE_SIZE = 24;
	var REMOVE_LABEL = ( window.wpSwiperPreviewI18n && window.wpSwiperPreviewI18n.removePlace ) || 'Remove this place from the collage';
	var MIN_SHARE = 0.08;
	var MIN_PART = 40; // px.
	var resize = null;

	function drawHandles( block, api ) {
		var grid = block.querySelector( '.wp-swiper-collage__grid' );
		[].forEach.call( grid.querySelectorAll( ':scope > .wp-swiper-collage__line' ), function ( node ) {
			node.parentNode.removeChild( node );
		} );
		api.mosaic.lines().forEach( function ( line ) {
			var thick = Math.max( HANDLE, line.gap );
			var handle = document.createElement( 'div' );
			handle.className = 'wp-swiper-collage__line wp-swiper-collage__line--' + ( line.vertical ? 'vertical' : 'horizontal' );
			if ( line.vertical ) {
				handle.style.left = ( line.at + line.gap / 2 - thick / 2 ) + 'px';
				handle.style.top = line.top + 'px';
				handle.style.width = thick + 'px';
				handle.style.height = line.height + 'px';
			} else {
				handle.style.left = line.left + 'px';
				handle.style.top = ( line.at + line.gap / 2 - thick / 2 ) + 'px';
				handle.style.width = line.width + 'px';
				handle.style.height = thick + 'px';
			}
			handle.wpLine = line;
			handle.wpApi = api;
			grid.appendChild( handle );
		} );

		[].forEach.call( grid.querySelectorAll( ':scope > .wp-swiper-collage__remove' ), function ( node ) {
			node.parentNode.removeChild( node );
		} );

		// Scattered: each photo's two outer edges (scatter below), and a button
		// in its corner that takes its place out (not the middle one's).
		if ( api.scatter && api.scatter.active() ) {
			api.scatter.places().forEach( function ( place ) {
				if ( ! api.scatter.removable( place.index ) ) {
					return;
				}
				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'wp-swiper-collage__remove';
				button.title = REMOVE_LABEL;
				button.setAttribute( 'aria-label', REMOVE_LABEL );
				button.style.left = ( place.left + place.width - REMOVE_SIZE - 6 ) + 'px';
				button.style.top = ( place.top + 6 ) + 'px';
				button.wpIndex = place.index;
				button.wpApi = api;
				grid.appendChild( button );
			} );
			api.scatter.places().forEach( function ( place ) {
				place.edges.forEach( function ( side ) {
					var vertical = side === 'left' || side === 'right';
					var handle = document.createElement( 'div' );
					handle.className = 'wp-swiper-collage__line wp-swiper-collage__edge wp-swiper-collage__line--' + ( vertical ? 'vertical' : 'horizontal' );
					if ( vertical ) {
						handle.style.left = ( place.left + ( side === 'right' ? place.width : 0 ) - HANDLE / 2 ) + 'px';
						handle.style.top = place.top + 'px';
						handle.style.width = HANDLE + 'px';
						handle.style.height = place.height + 'px';
					} else {
						handle.style.left = place.left + 'px';
						handle.style.top = ( place.top + ( side === 'bottom' ? place.height : 0 ) - HANDLE / 2 ) + 'px';
						handle.style.width = place.width + 'px';
						handle.style.height = HANDLE + 'px';
					}
					handle.wpEdge = { index: place.index, side: side, place: place };
					handle.wpApi = api;
					grid.appendChild( handle );
				} );
			} );
		}
	}

	function setup() {
		[].forEach.call( document.querySelectorAll( '.wp-swiper-collage' ), function ( block ) {
			var api = block.wpSwiperCollage;
			if ( ! api || ! api.mosaic || block.wpSwiperHandles ) {
				return;
			}
			block.wpSwiperHandles = true;
			api.onPlace( function () {
				drawHandles( block, api );
			} );
			drawHandles( block, api );
		} );
	}

	document.addEventListener( 'pointerdown', function ( e ) {
		var handle = e.button === 0 && e.target.closest( '.wp-swiper-collage__line' );
		// Scattered's edges: their own handlers (below).
		if ( ! handle || ! handle.wpLine ) {
			return;
		}
		e.preventDefault();
		var line = handle.wpLine;
		resize = {
			api: handle.wpApi,
			grid: handle.parentNode,
			node: line.node,
			line: line,
			device: handle.wpApi.places().device
		};
		resize.api.hold( true );
		handle.classList.add( 'is-active' );
		document.documentElement.classList.add( 'wp-swiper-collage-resizing', 'wp-swiper-collage-resizing--' + ( line.vertical ? 'vertical' : 'horizontal' ) );
	} );

	document.addEventListener( 'pointermove', function ( e ) {
		if ( ! resize ) {
			return;
		}
		var line = resize.line;
		var rect = resize.grid.getBoundingClientRect();
		var size = ( line.vertical ? line.width : line.height ) - line.gap;
		if ( size <= 0 ) {
			return;
		}
		var pointer = line.vertical ? e.clientX - rect.left - line.left : e.clientY - rect.top - line.top;
		var min = Math.min( 0.45, Math.max( MIN_SHARE, MIN_PART / size ) );
		var share = Math.max( min, Math.min( 1 - min, ( pointer - line.gap / 2 ) / size ) );
		resize.api.mosaic.setShare( resize.node, share );
	} );

	document.addEventListener( 'pointerup', function () {
		if ( ! resize ) {
			return;
		}
		var api = resize.api;
		var device = resize.device;
		resize = null;
		document.documentElement.classList.remove( 'wp-swiper-collage-resizing', 'wp-swiper-collage-resizing--vertical', 'wp-swiper-collage-resizing--horizontal' );
		api.hold( false );
		// Kept as it is now: rebuilt (another order, a resize) it stays.
		var text = api.mosaic.serialize();
		api.mosaic.set( text );
		if ( text && window.parent && window.parent !== window ) {
			// A template's sizes are kept apart from Random mosaic's (collage_cuts).
			window.parent.postMessage( { wpSwiper: 'collage-mosaic', device: device, mosaic: text, template: api.mosaic.template() }, window.location.origin );
		}
	} );

	/*
	 * Scattered: a photo's outer edge is a handle; dragged, the photo grows or
	 * shrinks on that side (its other edges stay), at least MIN_PART, and the
	 * photos beyond it move along, a gap away as always (collage.js
	 * collage() scatter.resize()). Meanwhile the collage stays at its scale
	 * and spot (it may run over the block's edges); on the release it is
	 * fitted to the block again, kept for the device in view and the editor
	 * stores it (admin.js: a message with the layout).
	 */
	var edge = null;

	document.addEventListener( 'pointerdown', function ( e ) {
		var handle = e.button === 0 && e.target.closest( '.wp-swiper-collage__edge' );
		if ( ! handle || ! handle.wpEdge ) {
			return;
		}
		e.preventDefault();
		var vertical = handle.wpEdge.side === 'left' || handle.wpEdge.side === 'right';
		edge = {
			api: handle.wpApi,
			grid: handle.parentNode,
			index: handle.wpEdge.index,
			side: handle.wpEdge.side,
			place: handle.wpEdge.place,
			device: handle.wpApi.places().device
		};
		edge.api.hold( true );
		edge.api.scatter.freeze( true );
		handle.classList.add( 'is-active' );
		document.documentElement.classList.add( 'wp-swiper-collage-resizing', 'wp-swiper-collage-resizing--' + ( vertical ? 'vertical' : 'horizontal' ) );
	} );

	document.addEventListener( 'pointermove', function ( e ) {
		if ( ! edge ) {
			return;
		}
		var rect = edge.grid.getBoundingClientRect();
		var x = e.clientX - rect.left;
		var y = e.clientY - rect.top;
		var p = edge.place;
		var width = p.width;
		var height = p.height;
		if ( edge.side === 'right' ) {
			width = x - p.left;
		} else if ( edge.side === 'left' ) {
			width = p.left + p.width - x;
		} else if ( edge.side === 'bottom' ) {
			height = y - p.top;
		} else {
			height = p.top + p.height - y;
		}
		edge.api.scatter.resize( edge.index, Math.max( MIN_PART, width ), Math.max( MIN_PART, height ) );
	} );

	function endEdge() {
		if ( ! edge ) {
			return;
		}
		var api = edge.api;
		var device = edge.device;
		edge = null;
		document.documentElement.classList.remove( 'wp-swiper-collage-resizing', 'wp-swiper-collage-resizing--vertical', 'wp-swiper-collage-resizing--horizontal' );
		api.hold( false );
		// Kept as it is now (fitted to the block again): rebuilt (another order, a resize) it stays.
		var text = api.scatter.serialize();
		api.scatter.set( text );
		if ( text && window.parent && window.parent !== window ) {
			window.parent.postMessage( { wpSwiper: 'collage-scatter', device: device, layout: text }, window.location.origin );
		}
	}
	document.addEventListener( 'pointerup', endEdge );
	document.addEventListener( 'pointercancel', endEdge );

	/*
	 * Undo / redo in the editor (admin.js calls it, as it stores them): the
	 * collages shown for `state.device` take its order (photo IDs, each to
	 * the first slide with it not taken yet, as WP_Swiper_Settings::
	 * collage_orders() does) and sizes at once. False when none did (the
	 * editor then updates the preview).
	 */
	window.wpSwiperRestoreCollage = function ( state ) {
		var done = false;
		[].forEach.call( document.querySelectorAll( '.wp-swiper-collage' ), function ( block ) {
			var api = block.wpSwiperCollage;
			if ( ! api || ! api.restore || api.places().device !== state.device ) {
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
			api.restore( { order: order, mosaic: state.mosaic, cuts: state.cuts, count: Number( state.count ) || 0 } );
			done = true;
		} );
		return done;
	};

	// The collages are set up by frontend.js (with collage.js) once the page is there.
	if ( document.readyState === 'complete' ) {
		setup();
	} else {
		window.addEventListener( 'load', setup );
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		setTimeout( setup );
	} );

	document.addEventListener( 'pointercancel', end );
	// Escape: the drag given up, everything as it was.
	document.addEventListener( 'keydown', function ( e ) {
		if ( drag && e.key === 'Escape' ) {
			swallow = drag.moved;
			end();
		}
	} );
	// The photos are not dragged out as files.
	document.addEventListener( 'dragstart', function ( e ) {
		if ( e.target.closest && e.target.closest( '.wp-swiper-collage__cell' ) ) {
			e.preventDefault();
		}
	} );
}() );
