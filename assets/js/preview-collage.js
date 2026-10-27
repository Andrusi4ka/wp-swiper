/**
 * Collage in the preview (WP_Swiper_Preview, its frame only): a photo
 * dragged onto another place swaps with it. While dragging, a small copy of
 * the photo follows the pointer and its place is an empty frame; over
 * another place, the two show the swap before it is made (each with the
 * other's photo), back as they were once the pointer leaves. On the drop
 * the collage takes the new order for the device in view (frontend.js
 * collage() setOrder()) and the editor stores it (admin.js: a message with
 * the photos' IDs).
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

	function end() {
		if ( ! drag ) {
			return;
		}
		if ( drag.moved ) {
			hover( null );
			unshow( drag.cell );
			drag.cell.classList.remove( 'is-dragged', 'is-source' );
			if ( drag.ghost ) {
				drag.ghost.parentNode.removeChild( drag.ghost );
			}
			document.documentElement.classList.remove( 'wp-swiper-collage-dragging' );
		}
		drag.api.hold( false );
		drag = null;
	}

	document.addEventListener( 'pointerdown', function ( e ) {
		var cell = e.button === 0 && e.target.closest( '.wp-swiper-collage__cell' );
		var block = cell && cell.closest( '.wp-swiper-collage' );
		var api = block && block.wpSwiperCollage;
		if ( ! api || ! api.setOrder ) {
			return;
		}
		drag = { cell: cell, grid: cell.parentNode, api: api, x: e.clientX, y: e.clientY, moved: false, target: null, item: null, ghost: null };
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
		}
		moveGhost( e.clientX, e.clientY );
		var target = cellAt( e.clientX, e.clientY, drag.grid );
		hover( target === drag.cell ? null : target );
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
		if ( drag.moved && drag.target ) {
			var cells = [].slice.call( drag.grid.children );
			var from = cells.indexOf( drag.cell );
			var to = cells.indexOf( drag.target );
			var places = drag.api.places();
			var list = places.order.slice();
			var swap = list[ from ];
			list[ from ] = list[ to ];
			list[ to ] = swap;
			// Each photo once (a template with more places than photos repeats them).
			var seen = {};
			list = list.filter( function ( real ) {
				return seen[ real ] ? false : ( seen[ real ] = true );
			} );
			var api = drag.api;
			end();
			api.setOrder( list );
			if ( window.parent && window.parent !== window ) {
				window.parent.postMessage( {
					wpSwiper: 'collage-order',
					device: places.device,
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
	 * Random mosaic and the templates: each cut's line between its two parts
	 * is a handle; dragged
	 * along, the photos on either side grow and shrink (frontend.js collage()
	 * mosaic.setShare()), each part at least MIN_PART of the cut's size. On the
	 * release the mosaic as it is now is kept for the device in view and the
	 * editor stores it (admin.js: a message with the cuts).
	 */
	var HANDLE = 12; // The handle's thickness, px (at least the gap).
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
		if ( ! handle ) {
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

	// The collages are set up by frontend.js once the page is there.
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
