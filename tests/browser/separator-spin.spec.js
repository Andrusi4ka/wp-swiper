// Separator rotation (cases.php marquee-spin / marquee-spin-reverse): an own
// image as the continuous scroll's separator turns all the time, one turn in
// 360° / its °/s, the way the strip runs; with "reduce motion" it stays still.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

async function open( page, name ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( fixture + '?case=' + name );
	await page.waitForFunction( () => document.querySelector( '.wp-swiper .swiper' ) && document.querySelector( '.wp-swiper .swiper' ).swiper );
	return errors;
}

// The first separator image's animation and its angle now (degrees, from its matrix).
const spin = ( page ) => page.evaluate( () => {
	const image = document.querySelector( '.wp-swiper__separator-image' );
	const style = getComputedStyle( image );
	const m = new DOMMatrix( style.transform );
	return {
		name: style.animationName,
		duration: style.animationDuration,
		direction: style.animationDirection,
		angle: Math.round( Math.atan2( m.b, m.a ) * 180 / Math.PI ),
	};
} );

test( 'turns one way at its speed', async ( { page } ) => {
	const errors = await open( page, 'marquee-spin' );
	const first = await spin( page );
	expect( first.name ).toBe( 'wp-swiper-separator-spin' );
	expect( first.duration ).toBe( '8s' );
	expect( first.direction ).toBe( 'normal' );
	await page.waitForTimeout( 600 );
	// It moved on: about 27° in 0.6 s at 45°/s.
	expect( ( await spin( page ) ).angle ).not.toBe( first.angle );
	expect( errors ).toEqual( [] );
} );

test( 'turns the other way when the strip runs left to right', async ( { page } ) => {
	const errors = await open( page, 'marquee-spin-reverse' );
	const state = await spin( page );
	expect( state.duration ).toBe( '4s' );
	expect( state.direction ).toBe( 'reverse' );
	expect( errors ).toEqual( [] );
} );

test( 'stays still with reduce motion', async ( { page } ) => {
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	const errors = await open( page, 'marquee-spin' );
	expect( ( await spin( page ) ).name ).toBe( 'none' );
	expect( errors ).toEqual( [] );
} );
