// Text mask (cases.php art-text-mask*): its letters' windows, where they
// are (a snapshot) and that each one is cut to its letter and strip.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

// Name, word, how many photos, the Gap between the strips where they are all as wide (Strip width "Same for all").
for ( const [ name, word, photos, gap ] of [ [ 'art-text-mask', 'LAHORE', 1, 12 ], [ 'art-text-mask-sans', 'WPSWIPER', 1, null ], [ 'art-text-mask-each', 'LAHORE', 6, 12 ] ] ) {
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
					// The middle of its strip's bottom: in the window; just left of
					// its box: not (the gap, or the page).
					const inside = document.elementFromPoint( box.left + box.width / 2, box.bottom - 3 );
					const before = document.elementFromPoint( box.left - 1, box.bottom - 3 );
					const rect = clip && clip.querySelector( 'rect' );
					return {
						strip: rect ? [ box.left - stage.left + Number( rect.getAttribute( 'x' ) ), Number( rect.getAttribute( 'width' ) ) ] : null,
						box: [ box.left - stage.left, box.top - stage.top, box.width, box.height ].map( Math.round ),
						letter: clip ? clip.querySelector( 'text' ).textContent : '',
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
		// One photo through all the letters, or one in each (Photos).
		expect( new Set( found.pieces.map( ( piece ) => piece.photo ) ).size ).toBe( photos );
		let right = -Infinity;
		for ( const piece of found.pieces ) {
			expect( piece.hit, piece.letter ).toBe( true );
			expect( piece.missed, piece.letter ).toBe( true );
			// Left to right, apart, within the stage, from its top.
			expect( piece.box[ 0 ] ).toBeGreaterThan( right );
			right = piece.box[ 0 ] + piece.box[ 2 ];
			expect( right ).toBeLessThanOrEqual( found.stage[ 0 ] + 1 );
			expect( piece.box[ 1 ] + piece.box[ 3 ] ).toBeLessThanOrEqual( found.stage[ 1 ] + 1 );
		}
		expect( Math.min( ...found.pieces.map( ( piece ) => piece.box[ 1 ] ) ) ).toBeLessThanOrEqual( 1 );
		// Strip width "Same for all": the strips as wide as each other, Gap apart.
		if ( null !== gap ) {
			const strips = found.pieces.map( ( piece ) => piece.strip );
			expect( strips.every( Boolean ) ).toBe( true );
			for ( let i = 1; i < strips.length; i++ ) {
				expect( strips[ i ][ 1 ] ).toBeCloseTo( strips[ 0 ][ 1 ], 1 );
				expect( strips[ i ][ 0 ] - ( strips[ i - 1 ][ 0 ] + strips[ i - 1 ][ 1 ] ) ).toBeCloseTo( gap, 0 );
			}
		}
		expect( 'stage ' + found.stage.join( 'x' ) + '\n' + found.pieces.map( ( piece ) => piece.letter + ' ' + piece.box.join( ' ' ) ).join( '\n' ) + '\n' ).toMatchSnapshot( name + '.windows.txt' );
	} );
}
