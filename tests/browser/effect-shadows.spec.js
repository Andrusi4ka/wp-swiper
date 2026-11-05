// Effect shadows in their own color (cases.php *-effect-shadow): Swiper's
// shading layers painted with --wp-swiper-effect-shadow, the opacity Swiper
// gives them as the slides move left as it is; Coverflow without a color
// kept: its own default (black at 50%, as Swiper drew it).
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

// The slider's color, and each kind of shading layer's paint.
function shading( page ) {
	return page.evaluate( () => {
		const root = document.querySelector( '.wp-swiper' );
		const paint = ( selector ) => {
			const layer = root.querySelector( selector );
			return layer ? { color: getComputedStyle( layer ).backgroundColor, image: getComputedStyle( layer ).backgroundImage } : null;
		};
		return {
			variable: root.style.getPropertyValue( '--wp-swiper-effect-shadow' ),
			creative: paint( '.swiper-slide-shadow-creative' ),
			cards: paint( '.swiper-slide-shadow-cards' ),
			left: paint( '.swiper-slide-shadow-coverflow.swiper-slide-shadow-left' ),
			right: paint( '.swiper-slide-shadow-coverflow.swiper-slide-shadow-right' ),
		};
	} );
}

async function next( page ) {
	await page.evaluate( () => document.querySelector( '.wp-swiper .swiper' ).swiper.slideNext( 0 ) );
	await page.waitForTimeout( 100 );
}

test( 'creative-effect-shadow: the shading in its color', async ( { page } ) => {
	const errors = await open( page, 'creative-effect-shadow' );
	await next( page );
	const s = await shading( page );
	expect( s.variable ).toBe( 'rgba(255,0,0,0.6)' );
	expect( s.creative.color ).toBe( 'rgba(255, 0, 0, 0.6)' );
	expect( errors ).toEqual( [] );
} );

test( 'cards-effect-shadow: the shading in its color', async ( { page } ) => {
	const errors = await open( page, 'cards-effect-shadow' );
	await next( page );
	const s = await shading( page );
	expect( s.cards.color ).toBe( 'rgba(0, 0, 255, 0.4)' );
	expect( errors ).toEqual( [] );
} );

test( 'coverflow-effect-shadow: its sides at its own default', async ( { page } ) => {
	const errors = await open( page, 'coverflow-effect-shadow' );
	const s = await shading( page );
	expect( s.variable ).toBe( 'rgba(0,0,0,0.5)' );
	expect( s.left.image ).toBe( 'linear-gradient(to left, rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0))' );
	expect( s.right.image ).toBe( 'linear-gradient(to right, rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0))' );
	expect( errors ).toEqual( [] );
} );

test( 'creative: no Effect shadows, no color', async ( { page } ) => {
	await open( page, 'creative' );
	await next( page );
	const s = await shading( page );
	expect( s.variable ).toBe( '' );
	expect( s.creative ).toBeNull();
} );
