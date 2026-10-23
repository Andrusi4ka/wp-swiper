/**
 * Developer mode of the preview (WP_Swiper_Preview, its frame only): over
 * each slider, the slides' boxes (dashed), the images' boxes (solid, with
 * their size), the space between the slides and the empty space inside a
 * slide around its image ("Space around", "Contain"), hatched, and the
 * distance between the images and from the slider's edges. Checked every
 * frame while it is on (redrawn when something moved), so it follows the
 * moving slides.
 * admin.js turns it on and off: wpSwiperDev( true | false ).
 */
( function () {
	'use strict';

	var on = false;
	var layers = [];

	function round( value ) {
		return Math.round( value * 10 ) / 10;
	}

	// A box of the layer, at `rect` (px, relative to the layer).
	function box( layer, className, rect, label ) {
		var node = document.createElement( 'div' );
		node.className = 'wp-swiper-dev__' + className;
		node.style.left = rect.left + 'px';
		node.style.top = rect.top + 'px';
		node.style.width = rect.width + 'px';
		node.style.height = rect.height + 'px';
		if ( label ) {
			var text = document.createElement( 'span' );
			text.className = 'wp-swiper-dev__label';
			text.textContent = label;
			node.appendChild( text );
		}
		layer.appendChild( node );
	}

	// `rect` relative to `origin`.
	function within( rect, origin ) {
		return {
			left: rect.left - origin.left,
			top: rect.top - origin.top,
			width: rect.width,
			height: rect.height,
			right: rect.right - origin.left,
			bottom: rect.bottom - origin.top
		};
	}

	// The empty bands of `outer` around `inner` (left, right, top, bottom).
	function bands( outer, inner ) {
		return [
			{ left: outer.left, top: outer.top, width: inner.left - outer.left, height: outer.height },
			{ left: inner.right, top: outer.top, width: outer.right - inner.right, height: outer.height },
			{ left: inner.left, top: outer.top, width: inner.width, height: inner.top - outer.top },
			{ left: inner.left, top: inner.bottom, width: inner.width, height: outer.bottom - inner.bottom }
		].filter( function ( band ) {
			return band.width >= 1 && band.height >= 1;
		} );
	}

	function draw( swiperEl ) {
		var layer = swiperEl.querySelector( ':scope > .wp-swiper-dev' );
		if ( ! layer ) {
			layer = document.createElement( 'div' );
			layer.className = 'wp-swiper-dev';
			swiperEl.appendChild( layer );
			layers.push( layer );
		}

		var origin = swiperEl.getBoundingClientRect();
		var vertical = swiperEl.classList.contains( 'swiper-vertical' );
		var slides = [].slice.call( swiperEl.querySelectorAll( ':scope > .swiper-wrapper > .swiper-slide' ) )
			.map( function ( slide ) {
				var media = slide.querySelector( '.wp-swiper__media' );
				return {
					cell: within( slide.getBoundingClientRect(), origin ),
					media: media ? within( media.getBoundingClientRect(), origin ) : null
				};
			} )
			// In view (the layer clips the rest).
			.filter( function ( item ) {
				return item.cell.right > 0 && item.cell.left < origin.width && item.cell.bottom > 0 && item.cell.top < origin.height;
			} )
			.sort( function ( a, b ) {
				return vertical ? a.cell.top - b.cell.top : a.cell.left - b.cell.left;
			} );

		// Nothing moved since the last frame: the boxes drawn stand.
		var key = slides.map( function ( item ) {
			return [ item.cell.left, item.cell.top, item.cell.width, item.cell.height ].concat(
				item.media ? [ item.media.left, item.media.top, item.media.width, item.media.height ] : []
			).map( round ).join( ',' );
		} ).join( ';' );
		if ( layer.getAttribute( 'data-key' ) === key ) {
			return;
		}
		layer.setAttribute( 'data-key', key );
		layer.textContent = '';

		slides.forEach( function ( item, index ) {
			box( layer, 'cell', item.cell );
			if ( item.media ) {
				bands( item.cell, item.media ).forEach( function ( band ) {
					box( layer, 'empty', band );
				} );
				box( layer, 'media', item.media, round( item.media.width ) + ' × ' + round( item.media.height ) );
			}
			var next = slides[ index + 1 ];
			if ( ! next ) {
				return;
			}
			// Swiper's space to the next slide.
			var gap = vertical
				? { left: item.cell.left, top: item.cell.bottom, width: item.cell.width, height: next.cell.top - item.cell.bottom }
				: { left: item.cell.right, top: item.cell.top, width: next.cell.left - item.cell.right, height: item.cell.height };
			if ( gap.width >= 1 && gap.height >= 1 ) {
				box( layer, 'gap', gap );
			}
			// The distance from this image to the next one (gap and empty space
			// together), in the middle between them.
			var from = item.media || item.cell;
			var to = next.media || next.cell;
			var space = vertical ? to.top - from.bottom : to.left - from.right;
			if ( space >= 0.5 ) {
				dimension( layer, vertical
					? { x: from.left + from.width / 2, y: from.bottom + space / 2 }
					: { x: from.right + space / 2, y: from.top + from.height / 2 }, round( space ) );
			}
		} );

		// From the slider's edges to the first and last image in view.
		var first = slides[ 0 ];
		var last = slides[ slides.length - 1 ];
		if ( first && first.media ) {
			var start = vertical ? first.media.top : first.media.left;
			if ( start >= 0.5 && ( vertical ? first.cell.top : first.cell.left ) >= -0.5 ) {
				dimension( layer, vertical ? { x: first.media.left + first.media.width / 2, y: start / 2 } : { x: start / 2, y: first.media.top + first.media.height / 2 }, round( start ) );
			}
		}
		if ( last && last.media ) {
			var size = vertical ? origin.height : origin.width;
			var end = size - ( vertical ? last.media.bottom : last.media.right );
			if ( end >= 0.5 && ( vertical ? last.cell.bottom : last.cell.right ) <= size + 0.5 ) {
				dimension( layer, vertical ? { x: last.media.left + last.media.width / 2, y: size - end / 2 } : { x: size - end / 2, y: last.media.top + last.media.height / 2 }, round( end ) );
			}
		}
	}

	// A distance's label, centred on `at` (px, relative to the layer).
	function dimension( layer, at, value ) {
		var node = document.createElement( 'span' );
		node.className = 'wp-swiper-dev__label wp-swiper-dev__dimension';
		node.style.left = at.x + 'px';
		node.style.top = at.y + 'px';
		node.textContent = value;
		layer.appendChild( node );
	}

	function frame() {
		if ( ! on ) {
			return;
		}
		[].forEach.call( document.querySelectorAll( '.wp-swiper .swiper.swiper-initialized' ), draw );
		requestAnimationFrame( frame );
	}

	window.wpSwiperDev = function ( state ) {
		var was = on;
		on = !! state;
		document.documentElement.classList.toggle( 'wp-swiper-dev-on', on );
		if ( on && ! was ) {
			requestAnimationFrame( frame );
		}
		if ( ! on ) {
			layers.forEach( function ( layer ) {
				layer.textContent = '';
				layer.removeAttribute( 'data-key' );
			} );
		}
	};
}() );
