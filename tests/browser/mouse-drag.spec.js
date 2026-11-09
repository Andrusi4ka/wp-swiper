// Drag with the mouse (cases.php slide / slide-no-mouse-drag): on, the mouse
// drags the slides with the grab hand; off, it does not move them (nor shows
// the hand), the arrows still do, and a finger on a touch screen still swipes.
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

const state = ( page ) => page.evaluate( () => {
	const swiper = document.querySelector( '.wp-swiper .swiper' ).swiper;
	return {
		index: swiper.realIndex,
		simulateTouch: swiper.params.simulateTouch,
		allowTouchMove: swiper.params.allowTouchMove,
		// Swiper puts the grab hand on the element its touches go to (the wrapper).
		cursor: getComputedStyle( swiper.params.touchEventsTarget === 'container' ? swiper.el : swiper.wrapperEl ).cursor,
	};
} );

// A drag of the mouse from the middle of the slider to the left.
async function drag( page ) {
	const box = await page.locator( '.wp-swiper .swiper' ).first().boundingBox();
	const y = box.y + box.height / 2;
	await page.mouse.move( box.x + box.width * 0.7, y );
	await page.mouse.down();
	await page.mouse.move( box.x + box.width * 0.2, y, { steps: 12 } );
	await page.mouse.up();
	await page.waitForTimeout( 900 );
}

test.describe( 'desktop', () => {
	test.skip( ( { isMobile } ) => isMobile, 'a mouse' );

	test( 'slide: the mouse drags the slides, with the grab hand', async ( { page } ) => {
		const errors = await open( page, 'slide' );
		const before = await state( page );
		expect( before.simulateTouch ).toBe( true );
		expect( before.cursor ).toBe( 'grab' );
		await drag( page );
		expect( ( await state( page ) ).index ).not.toBe( before.index );
		expect( errors ).toEqual( [] );
	} );

	test( 'slide-no-mouse-drag: the mouse moves nothing, the arrows still do', async ( { page } ) => {
		const errors = await open( page, 'slide-no-mouse-drag' );
		const before = await state( page );
		expect( before.simulateTouch ).toBe( false );
		expect( before.cursor ).not.toBe( 'grab' );
		await drag( page );
		expect( ( await state( page ) ).index ).toBe( before.index );
		await page.locator( '.swiper-button-next' ).first().click();
		await expect.poll( async () => ( await state( page ) ).index ).not.toBe( before.index );
		expect( errors ).toEqual( [] );
	} );
} );

test.describe( 'touch', () => {
	test.skip( ( { isMobile } ) => ! isMobile, 'a touch screen' );

	test( 'slide-no-mouse-drag: a finger still swipes', async ( { page } ) => {
		const errors = await open( page, 'slide-no-mouse-drag' );
		const before = await state( page );
		expect( before.allowTouchMove ).toBe( true );
		const box = await page.locator( '.wp-swiper .swiper' ).first().boundingBox();
		const y = box.y + box.height / 2;
		const from = box.x + box.width * 0.8;
		const to = box.x + box.width * 0.1;
		// A swipe: touch events, as a finger gives them (no mouse here).
		const session = await page.context().newCDPSession( page );
		await session.send( 'Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [ { x: from, y } ] } );
		for ( let i = 1; i <= 10; i++ ) {
			await session.send( 'Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [ { x: from + ( to - from ) * i / 10, y } ] } );
		}
		await session.send( 'Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] } );
		await expect.poll( async () => ( await state( page ) ).index ).not.toBe( before.index );
		expect( errors ).toEqual( [] );
	} );
} );
