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
];

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
					const rect = clip && clip.querySelector( 'rect' );
					// Its strip in the stage: x, y, width, height.
					const strip = rect ? [ 'x', 'y', 'width', 'height' ].map( ( key ) => Number( rect.getAttribute( key ) ) ) : null;
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
		expect( 'stage ' + found.stage.join( 'x' ) + '\n' + found.pieces.map( ( piece ) => piece.letter + ' ' + piece.box.join( ' ' ) ).join( '\n' ) + '\n' ).toMatchSnapshot( name + '.windows.txt' );
	} );
}
