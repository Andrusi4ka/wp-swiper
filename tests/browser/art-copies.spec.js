// Art's photos (art.js sizeImage()): each window's image the smallest of
// the copies WordPress made (data-wp-swiper-copies) at least as wide as it
// is drawn (covering its box, at the screen's density, Zoom on hover's 6%
// more), not the full size where a smaller copy does; nothing else of them
// fetched (a clone has no src before it is sized).
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

const CASES = [ 'art-ring', 'art-grid-circle', 'art-grid-shapes', 'art-grid-shapes-one', 'art-text-mask', 'art-text-mask-each' ];

for ( const name of CASES ) {
	test( name + ': each window the copy it needs', async ( { page } ) => {
		const errors = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );
		await page.goto( fixture + '?case=' + name );
		await page.waitForLoadState( 'networkidle' );
		await page.waitForTimeout( 500 );
		const found = await page.evaluate( () => {
			const block = document.querySelector( '.wp-swiper-art' );
			const zoom = block.classList.contains( 'wp-swiper-art--hover-zoom' ) ? 1.06 : 1;
			const density = Math.min( 3, window.devicePixelRatio || 1 );
			const images = [].slice.call( block.querySelectorAll( '.wp-swiper-art__piece:not([hidden]) > .wp-swiper-art__photo img' ) );
			const fetched = performance.getEntriesByType( 'resource' ).map( ( e ) => e.name );
			const all = [];
			const wrong = [];
			images.forEach( ( img ) => {
				const list = JSON.parse( img.getAttribute( 'data-wp-swiper-copies' ) || 'null' );
				if ( ! list ) {
					return;
				}
				list.forEach( ( c ) => all.push( c[ 2 ] ) );
				const full = list[ list.length - 1 ];
				const drawn = Math.max( img.offsetWidth, img.offsetHeight * full[ 0 ] / full[ 1 ] ) * density * zoom;
				const at = list.findIndex( ( c ) => c[ 2 ] === img.getAttribute( 'src' ) );
				// As wide as it is drawn (or the full size), and the copy before it is not.
				const enough = at >= 0 && ( list[ at ][ 0 ] >= drawn - 1 || at === list.length - 1 );
				const smallest = at <= 0 || list[ at - 1 ][ 0 ] < drawn + 1;
				if ( ! enough || ! smallest ) {
					wrong.push( { src: img.getAttribute( 'src' ), drawn: Math.round( drawn ), copies: list.map( ( c ) => c[ 0 ] ) } );
				}
			} );
			// Every image of theirs fetched is one a window shows.
			const shown = new Set( images.map( ( img ) => img.currentSrc || img.getAttribute( 'src' ) ) );
			const extra = fetched.filter( ( url ) => all.indexOf( url ) !== -1 && ! shown.has( url ) );
			// Without copies (an SVG: no smaller ones): its own src, as before.
			const plain = images.filter( ( img ) => ! img.hasAttribute( 'data-wp-swiper-copies' ) );
			return { images: images.length, withCopies: images.length - plain.length, plainWithSrc: plain.every( ( img ) => !! img.getAttribute( 'src' ) ), wrong, extra };
		} );
		expect( found.images ).toBeGreaterThan( 0 );
		// The media library's photos (JPEG) have smaller copies; an SVG keeps its file.
		expect( found.withCopies ).toBeGreaterThan( 0 );
		expect( found.plainWithSrc ).toBe( true );
		expect( found.wrong ).toEqual( [] );
		expect( found.extra ).toEqual( [] );
		expect( errors ).toEqual( [] );
	} );
}

// A window grows (a wider screen): its photo takes a larger copy; it shrinks: it keeps it.
test( 'art-grid-circle: a larger copy when the windows grow, none smaller after', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'resized on a desktop' );
	await page.setViewportSize( { width: 700, height: 800 } );
	await page.goto( fixture + '?case=art-grid-circle' );
	await page.waitForLoadState( 'networkidle' );
	const widths = () => page.evaluate( () => [].map.call( document.querySelectorAll( '.wp-swiper-art__piece:not([hidden]) > .wp-swiper-art__photo img' ), ( img ) => img.wpWidth || 0 ) );
	const small = await widths();
	await page.setViewportSize( { width: 1600, height: 800 } );
	await page.waitForTimeout( 600 );
	const large = await widths();
	expect( large.some( ( w, i ) => w > small[ i ] ) ).toBe( true );
	await page.setViewportSize( { width: 700, height: 800 } );
	await page.waitForTimeout( 600 );
	expect( await widths() ).toEqual( large );
} );
