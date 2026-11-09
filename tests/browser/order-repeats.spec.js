// More places than photos (cases.php art-shape-many: 16 windows, 4 photos;
// collage-few: a template of 5 places, 2 photos): the photos repeat, and a
// photo dragged in the preview onto a place past the photos' number lands
// there, the one there goes where it came from, every other place as it was
// (the repeated photos were once taken out, so nothing past the 4th moved).
// Undo / redo in the editor gives such an order back as it was.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';
const base = fixture.replace( 'tests/browser/fixture.php', '' );

test.skip( ( { isMobile } ) => isMobile, 'dragged with a mouse' );

async function open( page, name, script ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( fixture + '?case=' + name );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 800 );
	// The preview's own script, as its frame has it.
	await page.addScriptTag( { url: base + 'assets/js/' + script } );
	return errors;
}

async function drag( page, places, from, to ) {
	const a = await places.nth( from ).boundingBox();
	const b = await places.nth( to ).boundingBox();
	const ax = a.x + a.width / 2;
	const ay = a.y + a.height / 2;
	await page.mouse.move( ax, ay );
	await page.mouse.down();
	for ( let i = 1; i <= 8; i++ ) {
		await page.mouse.move( ax + ( b.x + b.width / 2 - ax ) * i / 8, ay + ( b.y + b.height / 2 - ay ) * i / 8 );
	}
	await page.mouse.up();
	await page.waitForTimeout( 400 );
}

// The places' photos as they are, two of them swapped.
function swapped( list, a, b ) {
	const out = list.slice();
	out[ a ] = list[ b ];
	out[ b ] = list[ a ];
	return out;
}

test( 'art-shape-many: a photo dragged onto the 12th window is there, the others as they were', async ( { page } ) => {
	const errors = await open( page, 'art-shape-many', 'preview-art.js' );
	const pieces = page.locator( '.wp-swiper-art__piece:not([hidden])' );
	await expect( pieces ).toHaveCount( 16 );
	const reals = () => page.evaluate( () => [].slice.call( document.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( p ) => ! p.hidden ).map( ( p ) => p.wpReal ) );
	const before = await reals();
	// Repeated: 4 photos in turn.
	expect( before.slice( 0, 4 ) ).toEqual( before.slice( 4, 8 ) );
	// Window 1 and 12 show different photos (12 repeats the 4th).
	expect( before[ 0 ] ).not.toBe( before[ 11 ] );

	await drag( page, pieces, 0, 11 );
	const after = await reals();
	expect( after ).toEqual( swapped( before, 0, 11 ) );

	// Undo in the editor: the order (photo IDs, some twice) back as it was,
	// then the new one again.
	const ids = ( list ) => page.evaluate( ( reals ) => reals.map( ( real ) => document.querySelector( '.wp-swiper-art' ).wpSwiperArt.ids[ real ] ), list );
	const device = await page.evaluate( () => document.querySelector( '.wp-swiper-art' ).wpSwiperArt.device() );
	await page.evaluate( ( state ) => window.wpSwiperRestoreArt( state ), { device, order: await ids( before ) } );
	expect( await reals() ).toEqual( before );
	await page.evaluate( ( state ) => window.wpSwiperRestoreArt( state ), { device, order: await ids( after ) } );
	expect( await reals() ).toEqual( after );
	expect( errors ).toEqual( [] );
} );

test( 'collage-few: a photo dragged onto the 5th place is there, the others as they were', async ( { page } ) => {
	const errors = await open( page, 'collage-few', 'preview-collage.js' );
	const cells = page.locator( '.wp-swiper-collage__cell' );
	await expect( cells ).toHaveCount( 5 );
	const reals = () => page.evaluate( () => [].map.call( document.querySelectorAll( '.wp-swiper-collage__cell' ), ( c ) => c.wpReal ) );
	const before = await reals();
	// 2 photos in turn: a place past them with the other photo.
	expect( before.slice( 0, 2 ) ).toEqual( before.slice( 2, 4 ) );
	const to = before.findIndex( ( real, i ) => i >= 2 && real !== before[ 0 ] );
	expect( to ).toBeGreaterThanOrEqual( 2 );

	await drag( page, cells, 0, to );
	const after = await reals();
	expect( after ).toEqual( swapped( before, 0, to ) );

	const ids = ( list ) => page.evaluate( ( reals ) => reals.map( ( real ) => document.querySelector( '.wp-swiper-collage' ).wpSwiperCollage.ids[ real ] ), list );
	const device = await page.evaluate( () => document.querySelector( '.wp-swiper-collage' ).wpSwiperCollage.places().device );
	await page.evaluate( ( state ) => window.wpSwiperRestoreCollage( state ), { device, order: await ids( before ) } );
	expect( await reals() ).toEqual( before );
	await page.evaluate( ( state ) => window.wpSwiperRestoreCollage( state ), { device, order: await ids( after ) } );
	expect( await reals() ).toEqual( after );
	expect( errors ).toEqual( [] );
} );
