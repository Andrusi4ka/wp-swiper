// SVG shape, Content in a window (cases.php art-shape-content): an image over
// text in one window instead of a photo, the window set per device; the
// photos in the others; no lightbox or button for it, no Gradient over it,
// its text cut; moved with the photos (the preview's order with -1).
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

async function open( page, name ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( fixture + '?case=' + name );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 800 );
	return errors;
}

function state( page ) {
	return page.evaluate( () => {
		const pieces = [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( piece ) => ! piece.hidden );
		const piece = document.querySelector( '.wp-swiper-art__piece--content' );
		const box = piece && piece.querySelector( '.wp-swiper-content' );
		const inner = box && box.querySelector( '.wp-swiper-content__inner' );
		const text = box && box.querySelector( '.wp-swiper-content__text' );
		return {
			windows: pieces.length,
			index: pieces.indexOf( piece ),
			contents: document.querySelectorAll( '.wp-swiper-art__piece--content' ).length,
			photos: pieces.filter( ( p ) => p !== piece ).map( ( p ) => p.wpReal ),
			hasPhoto: !! ( piece && piece.querySelector( '.wp-swiper-art__photo' ) ),
			role: piece ? piece.getAttribute( 'role' ) : 'none',
			gradient: piece ? getComputedStyle( piece, '::after' ).content : '',
			background: box ? getComputedStyle( box ).backgroundColor : '',
			image: !! ( box && box.querySelector( 'img.wp-swiper-content__image' ) ),
			fits: !! inner && inner.scrollHeight <= inner.clientHeight + 1,
			cut: !! text && text.textContent.trim().endsWith( '…' ),
		};
	} );
}

test( 'art-shape-content: the content in the window set for the device', async ( { page } ) => {
	const errors = await open( page, 'art-shape-content' );
	const found = await state( page );
	const mobile = page.viewportSize().width < 768;
	expect( found.windows ).toBe( 3 );
	expect( found.contents ).toBe( 1 );
	// Window 2 on desktop, 1 on mobile.
	expect( found.index ).toBe( mobile ? 0 : 1 );
	expect( found.hasPhoto ).toBe( false );
	expect( found.photos.every( ( real ) => real >= 0 ) ).toBe( true );
	expect( new Set( found.photos ).size ).toBe( 2 );
	// No photo to open: no button; no Gradient over it.
	expect( found.role ).toBeNull();
	expect( found.gradient ).toBe( 'none' );
	expect( found.background ).toBe( 'rgb(30, 60, 90)' );
	expect( found.image ).toBe( true );
	expect( found.fits ).toBe( true );
	expect( found.cut ).toBe( true );
	// A click on it opens no lightbox (on it: other windows of the drawing lie over parts of it).
	await page.evaluate( () => document.querySelector( '.wp-swiper-art__piece--content .wp-swiper-content__text' ).click() );
	await page.waitForTimeout( 400 );
	expect( await page.locator( '.wp-swiper-lightbox.is-open' ).count() ).toBe( 0 );
	expect( errors ).toEqual( [] );
} );

test( 'art-shape-content: moved with the photos, a photo where it was', async ( { page } ) => {
	const errors = await open( page, 'art-shape-content' );
	const moved = await page.evaluate( () => {
		const api = document.querySelector( '.wp-swiper-art' ).wpSwiperArt;
		const list = api.pieces().slice();
		const from = list.indexOf( -1 );
		const to = from === 2 ? 0 : 2;
		list[ from ] = list[ to ];
		list[ to ] = -1;
		api.setOrder( list );
		const pieces = [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) );
		return {
			window: api.contentWindow(),
			at: pieces.findIndex( ( p ) => p.classList.contains( 'wp-swiper-art__piece--content' ) ),
			to,
			// The window it left has a photo and is a button again.
			left: pieces[ from ].wpReal >= 0 && !! pieces[ from ].querySelector( '.wp-swiper-art__photo' ) && ! pieces[ from ].querySelector( '.wp-swiper-content' ),
			role: pieces[ from ].getAttribute( 'role' ),
			nodes: document.querySelectorAll( '.wp-swiper-content' ).length,
		};
	} );
	expect( moved.at ).toBe( moved.to );
	expect( moved.window ).toBe( moved.to + 1 );
	expect( moved.left ).toBe( true );
	expect( moved.role ).toBe( 'button' );
	// The same node moved, not copied.
	expect( moved.nodes ).toBe( 1 );
	expect( errors ).toEqual( [] );
} );

// Developer mode of the preview (preview-dev.js, loaded here as the preview
// does): each window's box with its number and size and its outline; the
// content's area inside the Padding, its size and that its text was cut;
// gone when off.
test( 'art-shape-content: Developer mode draws the windows and the content area', async ( { page } ) => {
	const errors = await open( page, 'art-shape-content' );
	const base = fixture.replace( 'tests/browser/fixture.php', '' );
	await page.addStyleTag( { url: base + 'assets/css/preview-dev.css' } );
	await page.addScriptTag( { url: base + 'assets/js/preview-dev.js' } );
	await page.evaluate( () => window.wpSwiperDev( true ) );
	await page.waitForTimeout( 300 );
	const drawn = await page.evaluate( () => {
		const stage = document.querySelector( '.wp-swiper-art__stage' );
		const pieces = [].slice.call( stage.querySelectorAll( ':scope > .wp-swiper-art__piece' ) ).filter( ( p ) => ! p.hidden );
		const windows = [].slice.call( stage.querySelectorAll( '.wp-swiper-dev__window' ) );
		const area = stage.querySelector( '.wp-swiper-dev__area' );
		const inner = stage.querySelector( '.wp-swiper-art__piece--content .wp-swiper-content__inner' );
		const piece = inner.closest( '.wp-swiper-art__piece' );
		return {
			pieces: pieces.length,
			windows: windows.length,
			// Each box where its window is, its number and size.
			boxes: pieces.every( ( p, i ) => {
				const w = windows[ i ];
				return w && Math.abs( w.offsetLeft - p.offsetLeft ) <= 1 && Math.abs( w.offsetWidth - p.offsetWidth ) <= 1 && w.textContent.indexOf( ( i + 1 ) + ': ' ) === 0;
			} ),
			content: stage.querySelectorAll( '.wp-swiper-dev__window--content' ).length,
			outlines: stage.querySelectorAll( '.wp-swiper-dev__outlines path' ).length,
			// The area: the content's box less its Padding (16px by default).
			area: area && Math.abs( area.offsetLeft - ( piece.offsetLeft + 16 ) ) <= 1 && Math.abs( area.offsetWidth - ( inner.clientWidth - 32 ) ) <= 1,
			label: area ? area.textContent : '',
			size: stage.querySelector( '.wp-swiper-dev__block-size' ).textContent,
		};
	} );
	expect( drawn.windows ).toBe( drawn.pieces );
	expect( drawn.boxes ).toBe( true );
	expect( drawn.content ).toBe( 1 );
	expect( drawn.outlines ).toBe( drawn.pieces );
	expect( drawn.area ).toBe( true );
	expect( drawn.label ).toMatch( /^[\d.]+ × [\d.]+, padding 16, text cut$/ );
	expect( drawn.size ).toMatch( /^[\d.]+ × [\d.]+$/ );
	await page.evaluate( () => window.wpSwiperDev( false ) );
	expect( await page.locator( '.wp-swiper-art .wp-swiper-dev' ).count() ).toBe( 0 );
	expect( errors ).toEqual( [] );
} );

// Ring (cases.php art-ring-content-swap): Content in a window in the middle
// circle, Photo swapping set to "the middle photo": the content stays there,
// the other photos swap in the ring instead (art.js swap()).
test( 'art-ring-content-swap: the content stays in the middle, the ring swaps', async ( { page } ) => {
	const errors = await open( page, 'art-ring-content-swap' );
	const look = () => page.evaluate( () => {
		const pieces = [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( p ) => ! p.hidden );
		return {
			content: pieces.findIndex( ( p ) => p.classList.contains( 'wp-swiper-art__piece--content' ) ),
			circle: pieces[ 0 ].classList.contains( 'wp-swiper-art__piece--circle' ),
			photos: pieces.slice( 1 ).map( ( p ) => p.wpReal ).join( ',' ),
		};
	} );
	const before = await look();
	expect( before.content ).toBe( 0 );
	expect( before.circle ).toBe( true );
	await page.waitForTimeout( 3500 );
	const after = await look();
	expect( after.content ).toBe( 0 );
	expect( after.photos ).not.toBe( before.photos );
	expect( errors ).toEqual( [] );
} );

// Grid shapes (cases.php art-grid-shapes-content): the content in its second
// shape (the first on mobile), the photos in the others; its background along
// the shape (clip-path), no button or Gradient on it.
test( 'art-grid-shapes-content: the content in the shape set for the device', async ( { page } ) => {
	const errors = await open( page, 'art-grid-shapes-content' );
	const found = await state( page );
	const mobile = page.viewportSize().width < 768;
	expect( found.contents ).toBe( 1 );
	expect( found.index ).toBe( mobile ? 0 : 1 );
	expect( found.hasPhoto ).toBe( false );
	expect( found.role ).toBeNull();
	expect( found.background ).toBe( 'rgb(30, 60, 90)' );
	expect( found.photos.every( ( real ) => real >= 0 ) ).toBe( true );
	expect( await page.locator( '.wp-swiper-art__piece--content .wp-swiper-content__text' ).innerText() ).toContain( 'Hello shapes' );
	expect( errors ).toEqual( [] );
} );

// The preview (preview-art.js, added to the page as its frame has it): Content
// in a window dragged onto a window past the photos (cases.php
// art-shape-content-many: 16 windows, 4 photos, repeated). It lands there,
// not on the 5th (the repeated photos once moved it up), the photos in the
// others. (The message to the editor goes from the preview's frame only.)
test( 'art-shape-content-many: dragged onto the 12th window, it is in the 12th', async ( { page, isMobile } ) => {
	test.skip( isMobile, 'dragged with a mouse' );
	const errors = await open( page, 'art-shape-content-many' );
	await page.addStyleTag( { url: fixture.replace( 'tests/browser/fixture.php', '' ) + 'assets/css/preview-art.css' } );
	await page.addScriptTag( { url: fixture.replace( 'tests/browser/fixture.php', '' ) + 'assets/js/preview-art.js' } );
	const pieces = page.locator( '.wp-swiper-art__piece:not([hidden])' );
	await expect( pieces ).toHaveCount( 16 );
	const from = await pieces.nth( 0 ).boundingBox();
	const to = await pieces.nth( 11 ).boundingBox();
	await page.mouse.move( from.x + from.width / 2, from.y + from.height / 2 );
	await page.mouse.down();
	for ( let i = 1; i <= 8; i++ ) {
		await page.mouse.move( from.x + from.width / 2 + ( to.x - from.x ) * i / 8, from.y + from.height / 2 + ( to.y - from.y ) * i / 8 );
	}
	await page.mouse.up();
	await page.waitForTimeout( 300 );
	const found = await page.evaluate( () => {
		const all = [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( p ) => ! p.hidden );
		return {
			at: all.findIndex( ( p ) => p.classList.contains( 'wp-swiper-art__piece--content' ) ),
			photos: all.filter( ( p ) => p.wpReal >= 0 ).length,
			window: document.querySelector( '.wp-swiper-art' ).wpSwiperArt.contentWindow(),
		};
	} );
	expect( found.at ).toBe( 11 );
	expect( found.window ).toBe( 12 );
	expect( found.photos ).toBe( 15 );
	expect( errors ).toEqual( [] );
} );
