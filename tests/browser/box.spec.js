// The boxes of four values (2026-11-10, as WP Banner's): each corner's
// radius and each side of Image border its own (cases.php slide-box), per
// device; and on the edit screen, the box: linked one value goes to all
// four, set apart each its own, linked again the first one's everywhere.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const { setup, edit, frame, openPage } = require( './admin-kit' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

// The first image's corners and its frame's sides, as drawn.
const drawn = ( page ) => page.evaluate( () => {
	const media = document.querySelector( '.wp-swiper .wp-swiper__media' );
	const style = getComputedStyle( media );
	const after = getComputedStyle( media, '::after' );
	return {
		radius: [ style.borderTopLeftRadius, style.borderTopRightRadius, style.borderBottomRightRadius, style.borderBottomLeftRadius ],
		border: [ after.borderTopWidth, after.borderRightWidth, after.borderBottomWidth, after.borderLeftWidth ],
		line: after.borderBottomStyle,
	};
} );

test( 'slide-box: each corner and each side its own, per device', async ( { page, isMobile } ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( fixture + '?case=slide-box' );
	await page.waitForFunction( () => document.querySelector( '.wp-swiper .swiper' ) && document.querySelector( '.wp-swiper .swiper' ).swiper );
	const at = await drawn( page );
	// Mobile: its own top right (10), the others the desktop's.
	expect( at.radius ).toEqual( [ '20px', isMobile ? '10px' : '0px', '20px', '0px' ] );
	expect( at.border ).toEqual( [ '0px', '0px', '6px', '0px' ] );
	// Its line (Image border > Line).
	expect( at.line ).toBe( 'dashed' );
	expect( errors ).toEqual( [] );
} );

test( 'thumbs-box: each thumbnail corner its own, in the lightbox too', async ( { page } ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( fixture + '?case=thumbs-box' );
	await page.waitForFunction( () => document.querySelector( '.wp-swiper .swiper' ) && document.querySelector( '.wp-swiper .swiper' ).swiper );
	const corners = ( selector ) => page.evaluate( ( sel ) => {
		const style = getComputedStyle( document.querySelector( sel ) );
		return [ style.borderTopLeftRadius, style.borderTopRightRadius, style.borderBottomRightRadius, style.borderBottomLeftRadius ];
	}, selector );
	expect( await corners( '.wp-swiper__thumbs .swiper-slide' ) ).toEqual( [ '12px', '0px', '12px', '0px' ] );
	// The lightbox has no radius of its own for its thumbnails: these.
	await page.locator( '.wp-swiper > .swiper:not(.wp-swiper__thumbs) .swiper-slide-active' ).first().click();
	await expect( page.locator( '.wp-swiper-lightbox__thumbs .swiper-slide' ).first() ).toBeVisible();
	expect( await corners( '.wp-swiper-lightbox__thumbs .swiper-slide' ) ).toEqual( [ '12px', '0px', '12px', '0px' ] );
	expect( errors ).toEqual( [] );
} );

test.describe( 'the edit screen', () => {
	setup( test );

	test( 'the box: linked one value is the four, apart each its own, linked again the first one\'s', async ( { page } ) => {
		const errors = await edit( page, 'fade-shadow' );
		await openPage( page, 'layout' );
		const field = page.locator( '[data-field="border_radius"]' );
		const corner = ( key ) => page.locator( '#wp-swiper-border_radius' + key );
		const link = field.locator( '.wp-swiper-box__link' );
		await expect( field ).toHaveClass( /is-linked/ );
		await expect( link ).toHaveAttribute( 'aria-pressed', 'true' );
		// No slider (it held the number to 100). Linked: a number typed goes to all four.
		await expect( field.locator( '.wp-swiper-range' ) ).toHaveCount( 0 );
		await corner( '_br' ).fill( '24' );
		for ( const key of [ '', '_tr', '_br', '_bl' ] ) {
			await expect( corner( key ) ).toHaveValue( '24' );
		}
		// Apart: each its own; the frame drawn so.
		await link.click();
		await expect( field ).not.toHaveClass( /is-linked/ );
		await expect( page.locator( '#wp-swiper-border_radius_linked' ) ).toHaveValue( '0' );
		await corner( '_tr' ).fill( '0' );
		await expect( corner( '' ) ).toHaveValue( '24' );
		expect( await field.locator( '.wp-swiper-box__frame' ).evaluate( ( el ) => el.style.borderTopRightRadius ) ).toBe( '0px' );
		expect( await field.locator( '.wp-swiper-box__frame' ).evaluate( ( el ) => el.style.borderTopLeftRadius ) ).toBe( '12px' );

		// The preview takes it with Update.
		await page.click( '.wp-swiper-preview__update' );
		await expect.poll( () => frame( page ).locator( '.wp-swiper' ).first().evaluate( ( el ) => el.style.getPropertyValue( '--wp-swiper-radius' ) ) ).toBe( '24px 0px 24px 24px' );

		// Saved and read back as set.
		await Promise.all( [ page.waitForNavigation(), page.click( '#publish' ) ] );
		await page.waitForSelector( '#wp-swiper-settings' );
		await openPage( page, 'layout' );
		await expect( corner( '_tr' ) ).toHaveValue( '0' );
		await expect( page.locator( '#wp-swiper-border_radius_linked' ) ).toHaveValue( '0' );

		// Linked again: the top left's on every corner.
		await field.locator( '.wp-swiper-box__link' ).click();
		await expect( page.locator( '#wp-swiper-border_radius_linked' ) ).toHaveValue( '1' );
		for ( const key of [ '', '_tr', '_br', '_bl' ] ) {
			await expect( corner( key ) ).toHaveValue( '24' );
		}
		expect( errors ).toEqual( [] );
	} );

	test( 'another effect and back: each keeps its own box, the link as "1" / "0"', async ( { page } ) => {
		const errors = await edit( page, 'fade-shadow' );
		await openPage( page, 'layout' );
		const field = page.locator( '[data-field="border_radius"]' );
		const linkedValue = page.locator( '#wp-swiper-border_radius_linked' );
		await field.locator( '.wp-swiper-box__link' ).click();
		await page.locator( '#wp-swiper-border_radius_tr' ).fill( '5' );
		await expect( linkedValue ).toHaveValue( '0' );
		// Slide: its own Layout (set together, as by default).
		await page.selectOption( '#wp-swiper-effect', 'slide' );
		await expect( linkedValue ).toHaveValue( '1' );
		await expect( field ).toHaveClass( /is-linked/ );
		// Fade again: as it was left.
		await page.selectOption( '#wp-swiper-effect', 'fade' );
		await expect( linkedValue ).toHaveValue( '0' );
		await expect( field ).not.toHaveClass( /is-linked/ );
		await expect( page.locator( '#wp-swiper-border_radius_tr' ) ).toHaveValue( '5' );
		expect( errors ).toEqual( [] );
	} );
} );
