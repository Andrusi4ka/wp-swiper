/**
 * Developer mode of the preview (WP_Swiper_Preview, its frame only): over
 * each slider, the slides' boxes (dashed), the images' boxes (solid, with
 * their size), the space between the slides and the empty space inside a
 * slide around its image ("Space around", "Contain"), hatched, and the
 * distance between the images and from the slider's edges. Checked every
 * frame while it is on (redrawn when something moved), so it follows the
 * moving slides. A collage: its places, the space between them, its
 * grid's box (turned, Rotation) and the block's (drawCollage()).
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
		return node;
	}

	/*
	 * A collage: a layer in its grid (turned with it, Rotation; its labels
	 * upright, preview-dev.css), each place's box with its size, the space
	 * between neighbouring places (hatched, the distance on it), the grid's
	 * own box with its size (and angle); and over the block, its box with its
	 * size (a turned grid's edges cut off by it, or its corners empty).
	 */
	function drawCollage( block ) {
		var grid = block.querySelector( '.wp-swiper-collage__grid' );
		if ( ! grid ) {
			return;
		}
		var layer = grid.querySelector( ':scope > .wp-swiper-dev' );
		if ( ! layer ) {
			layer = document.createElement( 'div' );
			layer.className = 'wp-swiper-dev wp-swiper-dev--collage';
			grid.appendChild( layer );
		}
		var outer = block.querySelector( ':scope > .wp-swiper-dev' );
		if ( ! outer ) {
			outer = document.createElement( 'div' );
			outer.className = 'wp-swiper-dev wp-swiper-dev--block';
			block.appendChild( outer );
		}
		var angle = grid.wpAngle || 0;
		var places = [].slice.call( grid.querySelectorAll( ':scope > .wp-swiper-collage__cell' ) ).map( function ( cell ) {
			var left = cell.offsetLeft;
			var top = cell.offsetTop;
			return { left: left, top: top, width: cell.offsetWidth, height: cell.offsetHeight, right: left + cell.offsetWidth, bottom: top + cell.offsetHeight };
		} );
		var size = { width: grid.offsetWidth, height: grid.offsetHeight };
		var key = [ angle, size.width, size.height, block.offsetWidth, block.offsetHeight ].concat( places.map( function ( p ) {
			return [ p.left, p.top, p.width, p.height ].map( round ).join( ',' );
		} ) ).join( ';' );
		if ( layer.getAttribute( 'data-key' ) === key ) {
			return;
		}
		layer.setAttribute( 'data-key', key );
		layer.textContent = '';
		outer.textContent = '';

		// The space to each place's nearest neighbour to the right and below
		// (sharing some of its height, or width), where nothing is between.
		var between = function ( a, b, across ) {
			var from = across ? Math.max( a.top, b.top ) : Math.max( a.left, b.left );
			var to = across ? Math.min( a.bottom, b.bottom ) : Math.min( a.right, b.right );
			var space = across ? b.left - a.right : b.top - a.bottom;
			if ( to - from < 1 || space < 0.5 ) {
				return null;
			}
			return across
				? { left: a.right, top: from, width: space, height: to - from, space: space }
				: { left: from, top: a.bottom, width: to - from, height: space, space: space };
		};
		places.forEach( function ( a ) {
			[ true, false ].forEach( function ( across ) {
				var nearest = null;
				places.forEach( function ( b ) {
					var gap = b !== a ? between( a, b, across ) : null;
					if ( gap && ( ! nearest || gap.space < nearest.space ) ) {
						nearest = gap;
					}
				} );
				if ( nearest ) {
					box( layer, 'gap', nearest );
					dimension( layer, { x: nearest.left + nearest.width / 2, y: nearest.top + nearest.height / 2 }, round( nearest.space ) );
				}
			} );
		} );
		places.forEach( function ( p ) {
			box( layer, 'media', p );
			dimension( layer, { x: p.left + p.width / 2, y: p.top + p.height / 2 }, round( p.width ) + ' × ' + round( p.height ) ).classList.add( 'wp-swiper-dev__size' );
		} );
		// The grid's box where it is not the block's (Fill, Fit: larger or
		// smaller, turned); the block's label says the angle and that size
		// (the box's own corner may be cut off).
		var other = Math.abs( size.width - block.offsetWidth ) > 1 || Math.abs( size.height - block.offsetHeight ) > 1;
		if ( angle && other ) {
			box( layer, 'grid', { left: 0, top: 0, width: size.width, height: size.height } );
		}
		box( outer, 'cell', { left: 0, top: 0, width: block.offsetWidth, height: block.offsetHeight } );
		var label = round( block.offsetWidth ) + ' × ' + round( block.offsetHeight );
		if ( angle ) {
			label += ', ' + angle + '°' + ( other ? ', laid out in ' + round( size.width ) + ' × ' + round( size.height ) : '' );
		}
		dimension( outer, { x: block.offsetWidth / 2, y: 12 }, label ).classList.add( 'wp-swiper-dev__block-size' );
	}

	function frame() {
		if ( ! on ) {
			return;
		}
		[].forEach.call( document.querySelectorAll( '.wp-swiper .swiper.swiper-initialized' ), draw );
		[].forEach.call( document.querySelectorAll( '.wp-swiper .wp-swiper-collage' ), drawCollage );
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
			// A collage's layers go (its grid is built again, with them or not).
			[].forEach.call( document.querySelectorAll( '.wp-swiper-collage .wp-swiper-dev' ), function ( layer ) {
				layer.remove();
			} );
		}
	};
}() );
