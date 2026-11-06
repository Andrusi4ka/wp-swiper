// Content in a place (cases.php collage-content*): an image over text in
// one place of the collage instead of a photo, at the place set per
// device, the photos in the others; upright when turned, its text cut at a
// word where it does not fit; no lightbox for it; moved with the photos.
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

function state( page ) {
	return page.evaluate( () => {
		const cells = [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) );
		const content = cells.filter( ( cell ) => cell.classList.contains( 'wp-swiper-collage__cell--content' ) );
		const cell = content[ 0 ];
		const inner = cell && cell.querySelector( '.wp-swiper-content__inner' );
		const text = cell && cell.querySelector( '.wp-swiper-content__text' );
		const box = inner && inner.getBoundingClientRect();
		const place = cell && cell.getBoundingClientRect();
		return {
			cells: cells.length,
			contents: content.length,
			index: cells.indexOf( cell ),
			image: !! ( cell && cell.querySelector( 'img.wp-swiper-content__image' ) ),
			text: text ? text.textContent : '',
			// All of the text inside: nothing over the edges.
			fits: !! inner && inner.scrollHeight <= inner.clientHeight + 1,
			// Upright: its box on the page as large as it is.
			upright: !! inner && Math.abs( box.width - inner.offsetWidth ) <= 1 && Math.abs( box.height - inner.offsetHeight ) <= 1,
			inside: !! inner && box.left >= place.left - 1 && box.right <= place.right + 1 && box.top >= place.top - 1 && box.bottom <= place.bottom + 1,
			// Each other place a photo, each photo once.
			photos: cells.filter( ( c ) => c !== cell ).map( ( c ) => c.wpReal ),
			background: cell ? getComputedStyle( cell.firstElementChild ).backgroundColor : '',
		};
	} );
}

test( 'collage-content: the image and text in the place set for the device', async ( { page } ) => {
	const errors = await open( page, 'collage-content' );
	const found = await state( page );
	const mobile = page.viewportSize().width < 768;
	expect( found.cells ).toBe( 6 );
	expect( found.contents ).toBe( 1 );
	// Place 2 on desktop, 1 on mobile (its own).
	expect( found.index ).toBe( mobile ? 0 : 1 );
	expect( found.image ).toBe( true );
	expect( found.text ).toContain( 'Our work' );
	expect( found.fits ).toBe( true );
	// No background by default.
	expect( found.background ).toBe( 'rgba(0, 0, 0, 0)' );
	expect( found.photos.every( ( real ) => real >= 0 ) ).toBe( true );
	expect( new Set( found.photos ).size ).toBe( 5 );
	// The image border on it too.
	expect( await page.evaluate( () => getComputedStyle( document.querySelector( '.wp-swiper-collage__cell--content' ), '::after' ).borderTopWidth ) ).not.toBe( '0px' );
	// A click on it opens no lightbox.
	await page.locator( '.wp-swiper-collage__cell--content' ).click();
	await page.waitForTimeout( 400 );
	expect( await page.locator( '.wp-swiper-lightbox.is-open, .wp-swiper-lightbox[aria-hidden="false"]' ).count() ).toBe( 0 );
	expect( errors ).toEqual( [] );
} );

test( 'collage-content-scatter: turned, upright inside its place, the text cut with "…"', async ( { page } ) => {
	const errors = await open( page, 'collage-content-scatter' );
	const found = await state( page );
	expect( found.index ).toBe( 0 );
	expect( found.upright ).toBe( true );
	expect( found.inside ).toBe( true );
	expect( found.fits ).toBe( true );
	expect( found.text.trim().endsWith( '…' ) ).toBe( true );
	expect( found.text.length ).toBeLessThan( 1300 );
	expect( found.background ).toBe( 'rgb(30, 60, 90)' );
	expect( errors ).toEqual( [] );
} );

test( 'collage-content-feature: in a template\'s place, at its bottom', async ( { page } ) => {
	const errors = await open( page, 'collage-content-feature' );
	const found = await state( page );
	expect( found.index ).toBe( 0 );
	const gap = await page.evaluate( () => {
		const cell = document.querySelector( '.wp-swiper-collage__cell--content' ).getBoundingClientRect();
		const text = document.querySelector( '.wp-swiper-content__text' ).getBoundingClientRect();
		return { bottom: cell.bottom - text.bottom, top: text.top - cell.top };
	} );
	// Bottom: nearer the bottom than the top (the padding there).
	expect( gap.bottom ).toBeLessThan( gap.top );
	expect( errors ).toEqual( [] );
} );

// The preview moves it as a photo (preview-collage.js: the order with -1
// where it is, collage.js setOrder()): its place follows.
test( 'collage-content: moved with the photos', async ( { page } ) => {
	const errors = await open( page, 'collage-content' );
	const moved = await page.evaluate( () => {
		const api = document.querySelector( '.wp-swiper-collage' ).wpSwiperCollage;
		const list = api.places().order.slice();
		const from = list.indexOf( -1 );
		list.splice( from, 1 );
		list.splice( 4, 0, -1 );
		api.setOrder( list );
		const cells = [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) );
		return { place: api.contentPlace(), index: cells.findIndex( ( c ) => c.classList.contains( 'wp-swiper-collage__cell--content' ) ), order: api.places().order.indexOf( -1 ) };
	} );
	expect( moved ).toEqual( { place: 5, index: 4, order: 4 } );
	expect( errors ).toEqual( [] );
} );

// A shortcode's markup (fixture.php: a form): the very node on the page,
// so its script found it; laid out again, what was typed stays; never cut,
// it scrolls instead.
test( 'collage-content-shortcode: a form that works and scrolls', async ( { page } ) => {
	const errors = await open( page, 'collage-content-shortcode' );
	const form = page.locator( '.wp-swiper-collage__cell--content form.fixture-form' );
	await expect( form ).toHaveCount( 1 );
	await expect( form ).toHaveAttribute( 'data-ready', '1' );
	await form.locator( 'input' ).fill( 'typed' );
	const found = await page.evaluate( () => {
		const api = document.querySelector( '.wp-swiper-collage' ).wpSwiperCollage;
		// Laid out again (as on another device, or a photo dragged in the preview).
		api.setOrder( api.places().order.slice() );
		const inner = document.querySelector( '.wp-swiper-collage__cell--content .wp-swiper-content__inner' );
		return {
			typed: document.querySelector( '.wp-swiper-collage__cell--content input' ).value,
			forms: document.querySelectorAll( 'form.fixture-form' ).length,
			cut: inner.textContent.indexOf( '…' ) !== -1,
			scrolls: getComputedStyle( inner ).overflowY,
			taller: inner.scrollHeight > inner.clientHeight,
		};
	} );
	expect( found ).toEqual( { typed: 'typed', forms: 1, cut: false, scrolls: 'auto', taller: true } );
	expect( errors ).toEqual( [] );
} );

// Justified rows or Masonry have no content: as many places as photos, each
// once (not one more); on a device with Grid, the content and every photo.
test( 'collage-content-masonry: no photo twice where there is no content', async ( { page } ) => {
	const errors = await open( page, 'collage-content-masonry' );
	const found = await state( page );
	const mobile = page.viewportSize().width < 768;
	expect( found.contents ).toBe( mobile ? 1 : 0 );
	expect( found.cells ).toBe( mobile ? 5 : 4 );
	expect( new Set( found.photos ).size ).toBe( 4 );
	expect( found.photos.length ).toBe( 4 );
	expect( errors ).toEqual( [] );
} );

// The preview (preview-collage.js, added as its frame has it): Content in a
// place dragged onto the last place of a template with more places than
// photos (cases.php collage-content-few: 5 places, 2 photos, repeated). It
// lands there, not on the 3rd (the repeated photos once moved it up).
test( 'collage-content-few: dragged onto the 5th place, it is in the 5th', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'dragged with a mouse' );
	const errors = await open( page, 'collage-content-few' );
	const base = fixture.replace( 'tests/browser/fixture.php', '' );
	await page.addScriptTag( { url: base + 'assets/js/preview-collage.js' } );
	const cells = page.locator( '.wp-swiper-collage__cell' );
	await expect( cells ).toHaveCount( 5 );
	const before = await page.evaluate( () => [].findIndex.call( document.querySelectorAll( '.wp-swiper-collage__cell' ), ( c ) => c.classList.contains( 'wp-swiper-collage__cell--content' ) ) );
	expect( before ).toBe( 0 );
	const from = await cells.nth( 0 ).boundingBox();
	const to = await cells.nth( 4 ).boundingBox();
	await page.mouse.move( from.x + from.width / 2, from.y + from.height / 2 );
	await page.mouse.down();
	for ( let i = 1; i <= 8; i++ ) {
		await page.mouse.move( from.x + from.width / 2 + ( to.x + to.width / 2 - from.x - from.width / 2 ) * i / 8, from.y + from.height / 2 + ( to.y + to.height / 2 - from.y - from.height / 2 ) * i / 8 );
	}
	await page.mouse.up();
	await page.waitForTimeout( 400 );
	const after = await page.evaluate( () => [].findIndex.call( document.querySelectorAll( '.wp-swiper-collage__cell' ), ( c ) => c.classList.contains( 'wp-swiper-collage__cell--content' ) ) );
	expect( after ).toBe( 4 );
	expect( errors ).toEqual( [] );
} );
