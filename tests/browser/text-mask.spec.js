// Text mask (cases.php art-text-mask*): its letters' windows, where they
// are (a snapshot) and that each one is cut to its letter and strip.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

// Name, word, how many photos, the Gap between the strips where they are all
// as wide (Strip width "Same for all"; null: as the letter), and the axis the
// word goes along (x: Strips go down; y: right or left, the letters one under another).
const CASES = [
	[ 'art-text-mask', 'LAHORE', 1, 12, 'x' ],
	[ 'art-text-mask-sans', 'WPSWIPER', 1, null, 'x' ],
	[ 'art-text-mask-each', 'LAHORE', 6, 12, 'x' ],
	[ 'art-text-mask-right', 'LAHORE', 1, 12, 'y' ],
	[ 'art-text-mask-left', 'WPSWIPER', 1, null, 'y' ],
	[ 'art-text-mask-name', 'LAHORE', 1, 12, 'x' ],
	[ 'art-text-mask-auto', 'LAHORE', 1, 12, 'x' ],
	[ 'art-text-mask-stretch', 'WIDE', 1, 12, 'x' ],
	[ 'art-text-mask-join', 'LAHORE', 1, 12, 'x' ],
	[ 'art-text-mask-rounded', 'LAHORE', 1, 12, 'x' ],
	[ 'art-text-mask-gradient', 'LAHORE', 1, 12, 'x' ],
];

// Strip width "As the letter": the Gap of those cases.
const LETTER_GAPS = { 'art-text-mask-sans': 6, 'art-text-mask-left': 12 };

// Each strip's top (strips down), from the top of its letter (its window's).
async function stripTops( page ) {
	return page.evaluate( () => [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( piece ) => ! piece.hidden ).map( ( piece ) => {
		const clip = document.querySelector( piece.style.clipPath.replace( /^url\("?|"?\)$/g, '' ) );
		return clip.querySelector( 'rect, path' ).getBBox().y;
	} ) );
}

for ( const [ name, word, photos, gap, axis ] of CASES ) {
	test( name + ': a window per letter, cut to it', async ( { page } ) => {
		const errors = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );
		await page.goto( fixture + '?case=' + name );
		await page.waitForLoadState( 'networkidle' );
		await page.evaluate( () => document.fonts.ready );
		await page.waitForTimeout( 400 );

		const found = await page.evaluate( () => {
			const block = document.querySelector( '.wp-swiper-art' );
			const stage = block.querySelector( '.wp-swiper-art__stage' ).getBoundingClientRect();
			const pieces = [].slice.call( block.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( piece ) => ! piece.hidden );
			return {
				word: block.querySelector( '.wp-swiper-art__word' ).textContent,
				stage: [ stage.width, stage.height ].map( Math.round ),
				pieces: pieces.map( ( piece ) => {
					const box = piece.getBoundingClientRect();
					const clip = document.querySelector( piece.style.clipPath.replace( /^url\("?|"?\)$/g, '' ) );
					// Its strip (a rect, or rounded a path): x, y, width, height in the stage.
					const shape = clip && clip.querySelector( 'rect, path' );
					const bounds = shape ? shape.getBBox() : null;
					const strip = bounds ? [ bounds.x, bounds.y, bounds.width, bounds.height ] : null;
					if ( strip ) {
						strip[ 0 ] += box.left - stage.left;
						strip[ 1 ] += box.top - stage.top;
					}
					// The middle of its strip: in the window; just left of its box:
					// not (the gap, or the page).
					const inside = strip ? document.elementFromPoint( stage.left + strip[ 0 ] + strip[ 2 ] / 2, stage.top + strip[ 1 ] + strip[ 3 ] / 2 ) : null;
					const before = document.elementFromPoint( box.left - 1, box.bottom - 3 );
					return {
						strip: strip,
						box: [ box.left - stage.left, box.top - stage.top, box.width, box.height ].map( Math.round ),
						letter: clip ? clip.querySelector( 'text' ).textContent : '',
						family: clip ? clip.querySelector( 'text' ).style.fontFamily : '',
						transform: clip ? clip.querySelector( 'text' ).getAttribute( 'transform' ) || '' : '',
						photo: ( piece.querySelector( 'img' ) || {} ).currentSrc || '',
						hit: !! ( inside && inside.closest( '.wp-swiper-art__piece' ) === piece ),
						missed: ! ( before && before.closest( '.wp-swiper-art__piece' ) === piece ),
					};
				} ),
			};
		} );

		expect( errors ).toEqual( [] );
		expect( found.pieces.map( ( piece ) => piece.letter ).join( '' ) ).toBe( word );
		expect( found.word.replace( /\s/g, '' ) ).toBe( word );
		// Font by name: that font first (quoted), a plain one after it.
		if ( 'art-text-mask-name' === name ) {
			expect( found.pieces[ 0 ].family ).toMatch( /^"?Impact"?, Georgia/ );
		}
		// Letter size Auto: the strips from the stage's left edge to its right.
		if ( 'art-text-mask-auto' === name ) {
			const last = found.pieces[ found.pieces.length - 1 ].strip;
			expect( found.pieces[ 0 ].strip[ 0 ] ).toBeLessThanOrEqual( 1 );
			expect( last[ 0 ] + last[ 2 ] ).toBeGreaterThanOrEqual( found.stage[ 0 ] - 1 );
		}
		// Stretch letters: wider only (their height as it is), at most twice; the narrow "I" for one.
		if ( 'art-text-mask-stretch' === name ) {
			const scales = found.pieces.map( ( piece ) => {
				const match = / scale\(([\d.]+) ([\d.]+)\)$/.exec( piece.transform );
				return match ? [ Number( match[ 1 ] ), Number( match[ 2 ] ) ] : [ 1, 1 ];
			} );
			for ( const [ sx, sy ] of scales ) {
				expect( sx ).toBeGreaterThanOrEqual( 1 );
				expect( sx ).toBeLessThanOrEqual( 2 );
				expect( sy ).toBe( 1 );
			}
			expect( scales[ 1 ][ 0 ] ).toBeGreaterThan( 1 );
		}
		// Strips into letters 20%: as art-text-mask (the letters as large), each
		// strip starting a fifth of its letter higher (there: STRIP_JOIN, 2px, in).
		if ( 'art-text-mask-join' === name ) {
			const into = await stripTops( page );
			await page.goto( fixture + '?case=art-text-mask' );
			await page.waitForLoadState( 'networkidle' );
			await page.evaluate( () => document.fonts.ready );
			await page.waitForTimeout( 400 );
			const whole = await stripTops( page );
			expect( into.length ).toBe( whole.length );
			whole.forEach( ( top, i ) => {
				// The letter's height: its strip's top there and 2px.
				expect( into[ i ] / ( top + 2 ) ).toBeCloseTo( 0.8, 1 );
			} );
		}
		// Rounded corners: each strip a path, its far end's corners off the
		// window (2px in from them), the middle of that end in it.
		if ( 'art-text-mask-rounded' === name ) {
			const ends = await page.evaluate( () => {
				const stage = document.querySelector( '.wp-swiper-art__stage' ).getBoundingClientRect();
				return [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( piece ) => ! piece.hidden ).map( ( piece ) => {
					const box = piece.getBoundingClientRect();
					const shape = document.querySelector( piece.style.clipPath.replace( /^url\("?|"?\)$/g, '' ) ).querySelector( 'rect, path' );
					const b = shape.getBBox();
					const at = ( x, y ) => {
						const hit = document.elementFromPoint( box.left + x, box.top + y );
						return !! ( hit && hit.closest( '.wp-swiper-art__piece' ) === piece );
					};
					return {
						path: 'path' === shape.tagName,
						left: at( b.x + 2, b.y + b.height - 2 ),
						right: at( b.x + b.width - 2, b.y + b.height - 2 ),
						middle: at( b.x + b.width / 2, b.y + b.height - 2 ),
						inStage: box.top + b.y + b.height <= stage.bottom + 1,
					};
				} );
			} );
			for ( const end of ends ) {
				expect( end ).toEqual( { path: true, left: false, right: false, middle: true, inStage: true } );
			}
		}
		// Gradient: over each window's photo, as large as the stage and where
		// the window is in it (one across the word); none without it.
		const layers = await page.evaluate( () => {
			const stage = document.querySelector( '.wp-swiper-art__stage' ).getBoundingClientRect();
			return [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( piece ) => ! piece.hidden ).map( ( piece ) => {
				const box = piece.getBoundingClientRect();
				const after = getComputedStyle( piece, '::after' );
				return {
					image: after.backgroundImage,
					size: [ parseFloat( after.width ), parseFloat( after.height ) ].map( Math.round ),
					at: [ box.left - stage.left + parseFloat( after.left ), box.top - stage.top + parseFloat( after.top ) ].map( Math.round ),
					stage: [ stage.width, stage.height ].map( Math.round ),
					events: after.pointerEvents,
				};
			} );
		} );
		for ( const layer of layers ) {
			if ( 'art-text-mask-gradient' === name ) {
				expect( layer.image ).toMatch( /^linear-gradient\(to right, rgba\(255, 0, 128, 0\.6\) 0%, rgba\(0, 96, 255, 0\.6\) 100%\)$/ );
				expect( layer.size ).toEqual( layer.stage );
				expect( Math.abs( layer.at[ 0 ] ) ).toBeLessThanOrEqual( 1 );
				expect( Math.abs( layer.at[ 1 ] ) ).toBeLessThanOrEqual( 1 );
				expect( layer.events ).toBe( 'none' );
			} else {
				expect( layer.image ).toBe( 'none' );
			}
		}
		// One photo through all the letters, or one in each (Photos).
		expect( new Set( found.pieces.map( ( piece ) => piece.photo ) ).size ).toBe( photos );
		// Along the word: x (left, width) or y (top, height) of a box.
		const at = 'x' === axis ? 0 : 1;
		let end = -Infinity;
		for ( const piece of found.pieces ) {
			expect( piece.hit, piece.letter ).toBe( true );
			expect( piece.missed, piece.letter ).toBe( true );
			// In order along the word, apart, within the stage.
			expect( piece.box[ at ] ).toBeGreaterThan( end );
			end = piece.box[ at ] + piece.box[ at + 2 ];
			expect( piece.box[ 0 ] + piece.box[ 2 ] ).toBeLessThanOrEqual( found.stage[ 0 ] + 1 );
			expect( piece.box[ 1 ] + piece.box[ 3 ] ).toBeLessThanOrEqual( found.stage[ 1 ] + 1 );
		}
		// The letters along one side: the top (down), the left or right.
		const side = 'x' === axis ? Math.min( ...found.pieces.map( ( piece ) => piece.box[ 1 ] ) ) : Math.min( ...found.pieces.map( ( piece ) => Math.min( piece.box[ 0 ], found.stage[ 0 ] - piece.box[ 0 ] - piece.box[ 2 ] ) ) );
		expect( side ).toBeLessThanOrEqual( 1 );
		// Strip width "Same for all": the strips as wide as each other, Gap apart.
		if ( null !== gap ) {
			const strips = found.pieces.map( ( piece ) => piece.strip );
			expect( strips.every( Boolean ) ).toBe( true );
			for ( let i = 1; i < strips.length; i++ ) {
				expect( strips[ i ][ at + 2 ] ).toBeCloseTo( strips[ 0 ][ at + 2 ], 1 );
				expect( strips[ i ][ at ] - ( strips[ i - 1 ][ at ] + strips[ i - 1 ][ at + 2 ] ) ).toBeCloseTo( gap, 0 );
			}
		}
		// Strip width "As the letter": each strip as wide as its letter, so the
		// strips Gap apart as the letters are (wider across the space, "WP SWIPER").
		if ( LETTER_GAPS[ name ] ) {
			const strips = found.pieces.map( ( piece ) => piece.strip );
			const apart = strips.slice( 1 ).map( ( strip, i ) => strip[ at ] - ( strips[ i ][ at ] + strips[ i ][ at + 2 ] ) );
			expect( apart.filter( ( d ) => Math.abs( d - LETTER_GAPS[ name ] ) <= 0.6 ).length ).toBe( apart.length - 1 );
			expect( apart[ 1 ] ).toBeGreaterThan( LETTER_GAPS[ name ] + 2 );
		}
		expect( 'stage ' + found.stage.join( 'x' ) + '\n' + found.pieces.map( ( piece ) => piece.letter + ' ' + piece.box.join( ' ' ) ).join( '\n' ) + '\n' ).toMatchSnapshot( name + '.windows.txt' );
	} );
}
