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

	// The Ring under the pointer and how far from its middle, if at its edge.
	function edgeAt( e ) {
		var node = document.elementFromPoint( e.clientX, e.clientY );
		var block = node && node.closest( '.wp-swiper-art--ring' );
		var api = block && block.wpSwiperArt;
		var ring = block && block.wpSwiperArtRing;
		if ( ! api || ! api.setCenter || ! ring ) {
			return null;
		}
		var stage = block.querySelector( '.wp-swiper-art__stage' );
		var rect = stage.getBoundingClientRect();
		var dx = e.clientX - rect.left - ring.cx;
		var dy = e.clientY - rect.top - ring.cy;
		var distance = Math.hypot( dx, dy );
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
			var share = ( Math.hypot( dx, dy ) - ring.gap / 2 ) / ring.outer * 100;
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
