// The Gradient over the photos (cases.php *-gradient): over each photo of a
// slider and a collage, as large as it; one across Art's whole stage, each
// window showing its part. Clicks go through it; without it, nothing is there.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

const GRADIENT = /^linear-gradient\(to right, rgba\(255, 0, 128, 0\.6\) 0%, rgba\(0, 96, 255, 0\.6\) 100%\)$/;

async function open( page, name ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( fixture + '?case=' + name );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 500 );
	return errors;
}

// Each photo's box and its ::before layer.
function photoLayers( page ) {
	return page.evaluate( () => [].slice.call( document.querySelectorAll( '.wp-swiper .swiper-slide > .wp-swiper__media' ) ).map( ( media ) => {
		const box = media.getBoundingClientRect();
		const before = getComputedStyle( media, '::before' );
		return {
			image: before.backgroundImage,
			size: [ parseFloat( before.width ), parseFloat( before.height ) ].map( Math.round ),
			box: [ box.width, box.height ].map( Math.round ),
			events: before.pointerEvents,
			zIndex: before.zIndex,
		};
	} ) );
}

for ( const name of [ 'slide-gradient', 'collage-gradient' ] ) {
	test( name + ': over each photo, as large as it', async ( { page } ) => {
		const errors = await open( page, name );
		const layers = await photoLayers( page );
		expect( layers.length ).toBeGreaterThan( 2 );
		for ( const layer of layers ) {
			expect( layer.image ).toMatch( GRADIENT );
			expect( layer.size ).toEqual( layer.box );
			expect( layer.events ).toBe( 'none' );
			expect( layer.zIndex ).toBe( '1' );
		}
		expect( errors ).toEqual( [] );
	} );
}

for ( const name of [ 'slide', 'collage-grid' ] ) {
	test( name + ': no gradient without it', async ( { page } ) => {
		await open( page, name );
		for ( const layer of await photoLayers( page ) ) {
			expect( layer.image ).toBe( 'none' );
		}
	} );
}

test( 'art-ring-gradient: one across the stage, each window its part', async ( { page } ) => {
	const errors = await open( page, 'art-ring-gradient' );
	const layers = await page.evaluate( () => {
		const stage = document.querySelector( '.wp-swiper-art__stage' ).getBoundingClientRect();
		return [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( piece ) => ! piece.hidden ).map( ( piece ) => {
			const box = piece.getBoundingClientRect();
			const after = getComputedStyle( piece, '::after' );
			return {
				image: after.backgroundImage,
				size: [ parseFloat( after.width ), parseFloat( after.height ) ].map( Math.round ),
				// Where the layer starts: the stage's corner.
				at: [ box.left - stage.left + parseFloat( after.left ), box.top - stage.top + parseFloat( after.top ) ].map( Math.round ),
				stage: [ stage.width, stage.height ].map( Math.round ),
			};
		} );
	} );
	expect( layers.length ).toBeGreaterThan( 2 );
	for ( const layer of layers ) {
		expect( layer.image ).toMatch( GRADIENT );
		expect( layer.size ).toEqual( layer.stage );
		expect( Math.abs( layer.at[ 0 ] ) ).toBeLessThanOrEqual( 1 );
		expect( Math.abs( layer.at[ 1 ] ) ).toBeLessThanOrEqual( 1 );
	}
	expect( errors ).toEqual( [] );
} );

// Captions on a Custom gradient (cases.php *-caption-gradient): each
// caption's background its colors and direction — a slider's band, a turned
// collage's place, Art's whole piece.
const CAPTION = /^linear-gradient\(to top, rgba\(255, 0, 128, 0\.7\) 0%, rgba\(0, 96, 255, 0\) 100%\)$/;
for ( const [ name, selector ] of [ [ 'slide-caption-gradient', '.wp-swiper__caption' ], [ 'collage-caption-gradient', '.wp-swiper__caption' ], [ 'art-caption-gradient', '.wp-swiper-art__caption' ] ] ) {
	test( name + ': the captions on their own gradient', async ( { page } ) => {
		const errors = await open( page, name );
		const images = await page.evaluate( ( sel ) => [].map.call( document.querySelectorAll( sel ), ( caption ) => getComputedStyle( caption ).backgroundImage ), selector );
		expect( images.length ).toBeGreaterThan( 0 );
		images.forEach( ( image ) => expect( image ).toMatch( CAPTION ) );
		expect( errors ).toEqual( [] );
	} );
}
