/**
 * WP Swiper, Text mask (art.js's part, loaded after it where a slider has
 * that effect, and in the preview): its word's letters laid out and drawn,
 * each a window with its strip. art.js's helpers from window.wpSwiperArtKit;
 * the style registered there (Art draws it as its Style "text").
 */
( function () {
	'use strict';

	var kit = window.wpSwiperArtKit;
	if ( ! kit ) {
		return;
	}
	var forDevice = kit.forDevice;
	var place = kit.place;
	var SVG = kit.SVG;
	var gradientPlaces = kit.gradientPlaces;

	/*
	 * Text mask (class-wp-swiper-art.php text_config()): a word across the
	 * stage, each letter a window onto the photo with a strip under it, the
	 * letter and its strip one window (a piece cut to both with an SVG
	 * clipPath: its <text> takes the page's fonts, which clip-path: path()
	 * cannot). The letters as large as fill the width, no taller than Letter
	 * height (% of the stage), at the top, each whole; the strips from where
	 * their letters end down, as long as Strips says (% of the height under
	 * the letters).
	 */
	var TEXT_FONTS = {
		serif: 'Georgia, "Times New Roman", Times, serif',
		sans: '"Arial Black", "Helvetica Neue", Arial, Helvetica, sans-serif'
	};
	// How far up into its letter a strip reaches (px: no seam between them, the
	// letter still whole).
	var STRIP_JOIN = 2;
	// Letter size Auto: at most this share of the stage away from the word; Stretch letters: at most this much.
	var TEXT_AUTO = 0.7;
	var TEXT_STRETCH = 2;
	var textBlocks = 0;
	var measurer = null;

	// The font family: one by name (the page's, if it loads it), a plain one,
	// or the theme's headings' (an h2 in the block, as the page styles it).
	function textFamily( block, text ) {
		if ( text.font === 'name' && text.family ) {
			// A name as typed ("Playfair Display"), quoted; a list ("A", serif) as it is.
			return /[,"']/.test( text.family ) ? text.family : '"' + text.family + '", ' + TEXT_FONTS.serif;
		}
		if ( text.font !== 'heading' ) {
			return TEXT_FONTS[ text.font ] || TEXT_FONTS.serif;
		}
		if ( ! block.wpSwiperHeadingFont ) {
			var probe = document.createElement( 'h2' );
			probe.className = 'wp-swiper-art__probe';
			probe.textContent = 'A';
			block.appendChild( probe );
			block.wpSwiperHeadingFont = getComputedStyle( probe ).fontFamily || TEXT_FONTS.serif;
			block.removeChild( probe );
		}
		return block.wpSwiperHeadingFont;
	}

	// Each letter's ink at 100px: from its origin, left and right, up and down; and a space's width.
	function measureLetters( letters, font ) {
		measurer = measurer || document.createElement( 'canvas' );
		var context = measurer.getContext( '2d' );
		context.font = font;
		var sizes = letters.map( function ( letter ) {
			var m = context.measureText( letter );
			var exact = typeof m.actualBoundingBoxRight === 'number';
			var left = exact ? m.actualBoundingBoxLeft : 0;
			var right = exact ? m.actualBoundingBoxRight : m.width;
			return {
				left: left,
				width: Math.max( 1, left + right ),
				ascent: exact ? m.actualBoundingBoxAscent : 72,
				descent: exact ? Math.max( 0, m.actualBoundingBoxDescent ) : 0
			};
		} );
		sizes.space = context.measureText( ' ' ).width;
		return sizes;
	}

	// The same numbers for the same word each time ("Different lengths").
	function seeded( seed ) {
		var state = ( Number( seed ) || 1 ) >>> 0;
		return function () {
			state = ( state + 0x6D2B79F5 ) >>> 0;
			var t = state;
			t = Math.imul( t ^ ( t >>> 15 ), t | 1 );
			t ^= t + Math.imul( t ^ ( t >>> 7 ), t | 61 );
			return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
		};
	}

	// Each strip's length (0–1 of the height under the letters).
	function stripLengths( text, count ) {
		var min = Math.max( 0, Math.min( 100, Number( text.min ) || 0 ) ) / 100;
		var max = Math.max( min, Math.min( 100, Number( text.max ) || 0 ) / 100 );
		var random = seeded( text.seed );
		var lengths = [];
		for ( var i = 0; i < count; i++ ) {
			var at = count > 1 ? i / ( count - 1 ) : 0.5;
			var share = {
				equal: 1,
				middle: 1 - Math.abs( 2 * at - 1 ),
				sides: Math.abs( 2 * at - 1 )
			}[ text.strips ];
			lengths.push( min + ( max - min ) * ( typeof share === 'number' ? share : random() ) );
		}
		return lengths;
	}

	/*
	 * Where each letter and its strip go, in px of the stage. Strips go:
	 * Down: the word across the stage, the letters at its top, each strip
	 * from where its letter ends down. Right / Left: the letters one under
	 * another, upright, at the stage's left (right) side, each strip from
	 * where its letter ends across (Letter size: of the width there, not the
	 * height). The strips as long as Strips says (of the room past the
	 * letters).
	 *
	 * Strip width "Same for all": a column (Left / Right: a row) per letter,
	 * all as wide, Gap between them (a space half a one more); each strip its
	 * column, each letter in the middle of its own, the widest one at most
	 * its column and half the Gap (a quarter of the Gap out each side, never
	 * touching a neighbour). Letters held smaller by Letter size: the columns
	 * as narrow as they then need. "As the letter": each strip as wide as its
	 * letter, the letters and so the strips Gap apart.
	 *
	 * @return {Object|null} { scale, items: [ { left, top, width, height (the
	 *                       letter's ink), strip: { x, y, w, h } or null } ] }
	 */
	function textLayout( sizes, spaces, count, text, gap, width, height ) {
		var across = text.direction === 'right' || text.direction === 'left';
		// Strips off: the letters alone, as large as the block allows (Auto),
		// Gap between them as a word's; Right at the right, Left at the left.
		var bare = !! text.bare;
		// Along the word, and from the letters to the strips' ends.
		var along = across ? height : width;
		var away = across ? width : height;
		// Each letter's ink along the word and away from it.
		var spans = sizes.map( function ( size ) {
			return across ? size.ascent + size.descent : size.width;
		} );
		var depths = sizes.map( function ( size ) {
			return across ? size.width : size.ascent + size.descent;
		} );
		var total = 0;
		var widest = 0;
		var deepest = 0;
		spans.forEach( function ( span, i ) {
			total += span;
			widest = Math.max( widest, span );
			deepest = Math.max( deepest, depths[ i ] );
		} );
		// Down: the letters' ink above and below the baseline, all on one line.
		var up = 0;
		var down = 0;
		sizes.forEach( function ( size ) {
			up = Math.max( up, size.ascent );
			down = Math.max( down, size.descent );
		} );
		if ( ! across ) {
			deepest = up + down;
		}
		var gaps = gap * ( count - 1 + spaces.length );
		// Letter size: its % of the stage (away from the word), or Auto: as
		// large as the word can be, at most TEXT_AUTO of it (room left for the strips).
		// Right or left: no Letter size, the word fills the Height (Gap takes
		// from the letters); at most TEXT_AUTO of the width.
		var share = across || forDevice( text.auto ) ? ( bare ? 1 : TEXT_AUTO ) : ( Number( forDevice( text.size ) ) || 30 ) / 100;
		var bySize = away * share / ( deepest || 1 );
		// A space: half a column, or (as the letter) a space's width (down) or half the tallest letter.
		var space = across ? widest / 2 : sizes.space;
		var scale;
		var column = 0;
		var used;
		// Stretch letters: along the word, at most TEXT_STRETCH times (as the letter: all alike).
		var stretch = 1;
		if ( text.width !== 'letter' && ! bare ) {
			var units = count + spaces.length / 2;
			var full = ( along - gaps ) / units;
			// Down: the widest letter may stand out of its column by a quarter
			// of Gap. Right or left: the tallest one as tall as its strip, the
			// Gap between the letters as between the strips.
			scale = Math.min( ( full + ( across ? 0 : gap / 2 ) ) / ( widest || 1 ), bySize );
			column = Math.max( 1, Math.min( full, scale * widest - ( across ? 0 : gap / 2 ) ) );
			// Stretched, they fill their columns: the columns as wide as there is room.
			if ( text.stretch ) {
				column = Math.max( column, Math.min( full, scale * widest * TEXT_STRETCH ) );
			}
			used = column * units + gaps;
		} else {
			scale = Math.min( ( along - gaps ) / ( total + spaces.length * space ), bySize );
			if ( text.stretch ) {
				stretch = Math.max( 1, Math.min( TEXT_STRETCH, ( along - gaps ) / ( scale * ( total + spaces.length * space ) ) ) );
			}
			used = scale * stretch * ( total + spaces.length * space ) + gaps;
		}
		if ( ! ( scale > 0 ) || ! ( along - gaps > 0 ) ) {
			return null;
		}
		var at = Math.max( 0, ( along - used ) / 2 );
		// The letters' side: as deep as the deepest one (down: to their
		// baseline, tails below it); the strips beyond it.
		var band = scale * ( across ? deepest : up );
		var room = Math.max( 0, away - band );
		var lengths = stripLengths( text, count );
		// How far a strip goes into its letter: STRIP_JOIN (no seam), or
		// Strips into letters, a share of the letter's depth (at most 0.3).
		var joinShare = Math.max( 0, Math.min( 0.3, Number( text.join ) || 0 ) );
		var joinInto = function ( depth ) {
			return Math.max( Math.min( STRIP_JOIN, depth / 4 ), depth * joinShare );
		};
		// Alignment (per device): the letters against one another across the
		// word — Top / Bottom: their tops, middles or the baseline (start,
		// center, end); Left / Right: their left edges, middle line or right
		// edges, in a column as wide as the widest. Auto: the baseline; the
		// edge the word stands at. With strips or without: each strip starts
		// where its own letter ends. Bottom: the row at the bottom of the
		// block (the strips going up); Right: the column at the right edge.
		var align = forDevice( text.align ) || 'auto';
		if ( 'auto' === align ) {
			align = across ? ( text.direction === 'left' ? 'end' : 'start' ) : 'end';
		}
		var rise = scale * ( up + down );
		var roomUp = Math.max( 0, height - rise );
		var lift = ! across && text.direction === 'up' ? roomUp : 0;
		var columnX = across && text.direction === 'left' ? width - band : 0;
		var items = [];
		for ( var i = 0; i < count; i++ ) {
			var size = sizes[ i ];
			if ( spaces.indexOf( i ) !== -1 ) {
				at += ( column ? column / 2 : scale * stretch * space ) + gap;
			}
			// Stretched: as the letter, all alike; in columns, each to fill its own.
			var widen = column ? ( text.stretch ? Math.max( 1, Math.min( TEXT_STRETCH, column / ( scale * spans[ i ] || 1 ) ) ) : 1 ) : stretch;
			var span = scale * spans[ i ] * widen;
			var depth = scale * depths[ i ];
			// Along the word: in the middle of its column, or where it is.
			var start = column ? at + ( column - span ) / 2 : at;
			// The strip along the word: its column, or as wide as its letter.
			var stripSpan = column || span;
			var stripStart = at;
			// Away from the word: from where its letter ends (STRIP_JOIN into it) to its length.
			var end = band + room * lengths[ i ];
			var item;
			if ( ! across ) {
				// Top at the top (the strips going down), Bottom at the bottom (going
				// up); each letter's top, middle or baseline on one line.
				var top = lift + ( 'start' === align ? 0 : ( 'center' === align ? ( rise - depth ) / 2 : scale * ( up - size.ascent ) ) );
				if ( text.direction === 'up' && ! bare ) {
					var to = top + joinInto( depth );
					var high = roomUp * ( 1 - lengths[ i ] );
					item = { left: start, top: top, width: span, height: depth, strip: to - high > 1 ? { x: stripStart, y: high, w: stripSpan, h: to - high } : null };
				} else {
					var from = top + depth - joinInto( depth );
					var bottom = scale * up + room * lengths[ i ];
					item = { left: start, top: top, width: span, height: depth, strip: ! bare && bottom - from > 1 ? { x: stripStart, y: from, w: stripSpan, h: bottom - from } : null };
				}
			} else {
				// In its column (as wide as the widest letter, at the word's edge):
				// at its left, middle or right.
				var lx = columnX + ( 'start' === align ? 0 : ( 'center' === align ? ( band - depth ) / 2 : band - depth ) );
				if ( bare ) {
					item = { left: lx, top: start, width: depth, height: span, strip: null };
				} else if ( text.direction === 'right' ) {
					// From where its letter ends (into it) out to the right.
					var into = lx + depth - joinInto( depth );
					item = { left: lx, top: start, width: depth, height: span, strip: end - into > 1 ? { x: into, y: stripStart, w: end - into, h: stripSpan } : null };
				} else {
					// From the left edge of its letter (into it) out to the left.
					var edge = lx + joinInto( depth );
					item = { left: lx, top: start, width: depth, height: span, strip: edge - ( width - end ) > 1 ? { x: width - end, y: stripStart, w: edge - ( width - end ), h: stripSpan } : null };
				}
			}
			// How much wider (down) or taller (right, left) its letter is drawn.
			item.stretch = widen;
			items.push( item );
			at += ( column || span ) + gap;
		}
		return { scale: scale, items: items };
	}

	/**
	 * A strip in its letter's clip path: a rect, or with Rounded corners a
	 * path, its corners at the far end rounded (Corners "both": by the letter
	 * too). The radius no more than half the strip's width, nor than its
	 * length (half of it, both ends).
	 */
	function stripShape( x, y, w, h, radius, text ) {
		var round = function ( value ) {
			return Math.round( value * 100 ) / 100;
		};
		var sideways = text.direction === 'right' || text.direction === 'left';
		var both = text.corners === 'both';
		var r = Math.max( 0, Math.min( radius, ( sideways ? h : w ) / 2, ( sideways ? w : h ) / ( both ? 2 : 1 ) ) );
		var shape;
		if ( ! ( r > 0 ) ) {
			shape = document.createElementNS( SVG, 'rect' );
			shape.setAttribute( 'x', round( x ) );
			shape.setAttribute( 'y', round( y ) );
			shape.setAttribute( 'width', round( w ) );
			shape.setAttribute( 'height', round( h ) );
			return shape;
		}
		// Top left, top right, bottom right, bottom left: the far end's.
		var far = text.direction === 'right' ? [ 0, 1, 1, 0 ] : ( text.direction === 'left' ? [ 1, 0, 0, 1 ] : ( text.direction === 'up' ? [ 1, 1, 0, 0 ] : [ 0, 0, 1, 1 ] ) );
		var c = far.map( function ( at ) {
			return at || both ? r : 0;
		} );
		var arc = function ( radius, toX, toY ) {
			return radius ? 'A' + round( radius ) + ' ' + round( radius ) + ' 0 0 1 ' + round( toX ) + ' ' + round( toY ) : 'L' + round( toX ) + ' ' + round( toY );
		};
		shape = document.createElementNS( SVG, 'path' );
		shape.setAttribute( 'd', 'M' + round( x + c[ 0 ] ) + ' ' + round( y ) +
			'H' + round( x + w - c[ 1 ] ) + arc( c[ 1 ], x + w, y + c[ 1 ] ) +
			'V' + round( y + h - c[ 2 ] ) + arc( c[ 2 ], x + w - c[ 2 ], y + h ) +
			'H' + round( x + c[ 3 ] ) + arc( c[ 3 ], x, y + h - c[ 3 ] ) +
			'V' + round( y + c[ 0 ] ) + arc( c[ 0 ], x + c[ 0 ], y ) + 'Z' );
		return shape;
	}

	function textMask( block, stage, config ) {
		var width = stage.clientWidth;
		var height = stage.clientHeight;
		var text = config.text || {};
		var letters = text.letters || [];
		var pieces = stage.querySelectorAll( '.wp-swiper-art__piece' );
		if ( ! width || ! height || ! letters.length ) {
			return;
		}
		var count = Math.min( letters.length, pieces.length );
		var family = textFamily( block, text );
		var weight = Number( text.weight ) || 700;
		var font = weight + ' 100px ' + family;
		// A web font not there yet: drawn again once it is (measured with it).
		if ( document.fonts && document.fonts.load && ! block.wpSwiperFontAsked ) {
			block.wpSwiperFontAsked = true;
			document.fonts.load( font, letters.join( '' ) ).then( function () {
				textMask( block, stage, config );
				gradientPlaces( block, stage );
			}, function () {} );
		}
		var sizes = measureLetters( letters.slice( 0, count ), font );
		var spaces = ( text.spaces || [] ).filter( function ( at ) {
			return at > 0 && at < count;
		} );
		var gap = Math.max( 0, Number( forDevice( config.gap ) ) || 0 );
		// The strips' Rounded corners (off: 0).
		var radius = Math.max( 0, Number( forDevice( text.radius ) ) || 0 );

		var laid = textLayout( sizes, spaces, count, text, gap, width, height );
		if ( ! laid ) {
			for ( var none = 0; none < pieces.length; none++ ) {
				pieces[ none ].hidden = true;
			}
			return;
		}
		var scale = laid.scale;

		// The letters' clip paths, in the block (one SVG, out of view).
		if ( ! block.wpSwiperTextId ) {
			block.wpSwiperTextId = 'wp-swiper-text-' + ( ++textBlocks );
		}
		var defs = block.querySelector( ':scope > .wp-swiper-art__clips' );
		if ( ! defs ) {
			defs = document.createElementNS( SVG, 'svg' );
			defs.setAttribute( 'class', 'wp-swiper-art__clips' );
			defs.setAttribute( 'aria-hidden', 'true' );
			defs.setAttribute( 'focusable', 'false' );
			block.appendChild( defs );
		}
		while ( defs.firstChild ) {
			defs.removeChild( defs.firstChild );
		}

		var windows = [];
		for ( var i = 0; i < count; i++ ) {
			var size = sizes[ i ];
			var piece = pieces[ i ];
			var item = laid.items[ i ];
			var strip = item.strip;
			// Its box: the letter's ink and its strip.
			var box = {
				left: Math.floor( Math.min( item.left, strip ? strip.x : item.left ) ),
				top: Math.floor( Math.min( item.top, strip ? strip.y : item.top ) ),
				width: 0,
				height: 0
			};
			box.width = Math.ceil( Math.max( item.left + item.width, strip ? strip.x + strip.w : 0 ) ) - box.left;
			box.height = Math.ceil( Math.max( item.top + item.height, strip ? strip.y + strip.h : 0 ) ) - box.top;

			var clip = document.createElementNS( SVG, 'clipPath' );
			var id = block.wpSwiperTextId + '-' + i;
			clip.setAttribute( 'id', id );
			clip.setAttribute( 'clipPathUnits', 'userSpaceOnUse' );
			var glyph = document.createElementNS( SVG, 'text' );
			// At its origin: the ink starts `left` before it.
			// Stretched along the word (down: wider; right or left: taller), from its origin.
			var sideways = text.direction === 'right' || text.direction === 'left';
			var sx = sideways ? 1 : item.stretch || 1;
			var sy = sideways ? item.stretch || 1 : 1;
			var ox = Math.round( ( item.left - box.left + scale * size.left * sx ) * 100 ) / 100;
			var oy = Math.round( ( item.top + scale * size.ascent * sy - box.top ) * 100 ) / 100;
			if ( sx !== 1 || sy !== 1 ) {
				glyph.setAttribute( 'x', 0 );
				glyph.setAttribute( 'y', 0 );
				glyph.setAttribute( 'transform', 'translate(' + ox + ' ' + oy + ') scale(' + ( Math.round( sx * 1000 ) / 1000 ) + ' ' + ( Math.round( sy * 1000 ) / 1000 ) + ')' );
			} else {
				glyph.setAttribute( 'x', ox );
				glyph.setAttribute( 'y', oy );
			}
			// As measured, whatever the page's CSS says of text (inline: over its rules).
			glyph.style.cssText = 'font-family:' + family + ';font-weight:' + weight + ';font-size:' + ( Math.round( scale * 10000 ) / 100 ) + 'px;font-style:normal;font-variant:normal;letter-spacing:0;text-transform:none;text-anchor:start;dominant-baseline:alphabetic';
			glyph.textContent = letters[ i ];
			clip.appendChild( glyph );
			if ( strip ) {
				clip.appendChild( stripShape( strip.x - box.left, strip.y - box.top, strip.w, strip.h, radius, text ) );
			}
			defs.appendChild( clip );

			piece.hidden = false;
			place( piece, box );
			piece.style.clipPath = 'url(#' + id + ')';
			piece.classList.remove( 'wp-swiper-art__piece--circle' );
			// One photo through all the letters (art.css): its part of the photo.
			piece.style.setProperty( '--wp-swiper-art-ox', box.left + 'px' );
			piece.style.setProperty( '--wp-swiper-art-oy', box.top + 'px' );
			windows.push( { box: [ box.left / width, box.top / height, box.width / width, box.height / height ] } );
		}
		for ( var extra = count; extra < pieces.length; extra++ ) {
			pieces[ extra ].hidden = true;
		}
		block.style.setProperty( '--wp-swiper-art-sw', width + 'px' );
		block.style.setProperty( '--wp-swiper-art-sh', height + 'px' );
		// For Appearance (wave()): where each window is, and the stage's proportions.
		config.windows = windows;
		config.windowsRatio = width / height;
		block.wpSwiperArtShown = count;
	}

	kit.styles.text = textMask;
	// Blocks of this effect waiting for it (art.js init()).
	kit.init();
}() );
