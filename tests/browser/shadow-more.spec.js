// Image shadow on Flip and Thumbnails (cases.php flip-shadow, thumbs-shadow,
// thumbs-fade-shadow): Flip's on its card, turning with it, the slider not
// cut off at its edges (its card turns past them); Thumbnails' on the main
// photos — sliding: on each slide, the neighbours past the edges hidden;
// fading: on the frame (.swiper-wrapper), so it does not pale mid-change —
// never on the strip of thumbnails.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

async function open( page, name ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( fixture + '?case=' + name );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 600 );
	return errors;
}

function shadows( page ) {
	return page.evaluate( () => {
		const root = document.querySelector( '.wp-swiper' );
		const main = root.querySelector( ':scope > .swiper:not(.wp-swiper__thumbs)' );
		const strip = root.querySelector( ':scope > .wp-swiper__thumbs' );
		const active = main.querySelector( '.swiper-slide-active' );
		return {
			shadowClass: root.classList.contains( 'wp-swiper--shadow' ),
			active: getComputedStyle( active ).boxShadow,
			wrapper: getComputedStyle( main.querySelector( '.swiper-wrapper' ) ).boxShadow,
			overflow: getComputedStyle( main ).overflow,
			strip: strip ? [].map.call( strip.querySelectorAll( '.swiper-slide' ), ( s ) => getComputedStyle( s ).boxShadow ) : [],
			stripWrapper: strip ? getComputedStyle( strip.querySelector( '.swiper-wrapper' ) ).boxShadow : 'none',
			outside: [].filter.call( main.querySelectorAll( '.swiper-slide' ), ( s ) => s.classList.contains( 'wp-swiper-slide--outside' ) ).length,
			slides: main.querySelectorAll( '.swiper-slide' ).length,
		};
	} );
}

test( 'flip-shadow: on the card, the slider not cut off', async ( { page } ) => {
	const errors = await open( page, 'flip-shadow' );
	const s = await shadows( page );
	expect( s.shadowClass ).toBe( true );
	expect( s.active ).not.toBe( 'none' );
	expect( s.overflow ).toBe( 'visible' );
	// Mid-turn, no errors.
	await page.evaluate( () => document.querySelector( '.wp-swiper .swiper' ).swiper.slideNext() );
	await page.waitForTimeout( 900 );
	expect( errors ).toEqual( [] );
} );

test( 'thumbs-shadow: on each main slide, the neighbours hidden, none on the strip', async ( { page } ) => {
	const errors = await open( page, 'thumbs-shadow' );
	const s = await shadows( page );
	expect( s.shadowClass ).toBe( true );
	expect( s.active ).not.toBe( 'none' );
	expect( s.wrapper ).toBe( 'none' );
	expect( s.outside ).toBeGreaterThan( 0 );
	expect( s.outside ).toBeLessThan( s.slides );
	expect( s.strip.length ).toBeGreaterThan( 0 );
	s.strip.forEach( ( shadow ) => expect( shadow ).toBe( 'none' ) );
	expect( s.stripWrapper ).toBe( 'none' );
	expect( errors ).toEqual( [] );
} );

test( 'thumbs-fade-shadow: on the frame, none on the slides or the strip', async ( { page } ) => {
	const errors = await open( page, 'thumbs-fade-shadow' );
	const s = await shadows( page );
	expect( s.shadowClass ).toBe( true );
	expect( s.wrapper ).not.toBe( 'none' );
	expect( s.active ).toBe( 'none' );
	s.strip.forEach( ( shadow ) => expect( shadow ).toBe( 'none' ) );
	expect( s.stripWrapper ).toBe( 'none' );
	expect( errors ).toEqual( [] );
} );
