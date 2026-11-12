// The edit screen's preview column (admin-frame.js, admin-preview.js and the
// frame's preview-*.js): its devices, turned, a screen size of one's own,
// Developer mode, and what is done in it by hand. Moved from admin.spec.js
// as they were (2026-11-10); setup and helpers: admin-kit.js.
const { test, expect } = require( '@playwright/test' );
const { setup, edit, frame, shown, openPage } = require( './admin-kit' );

setup( test );

test( 'the preview: devices, turned, dark screen, Developer mode', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	const box = page.locator( '.wp-swiper-preview__frame' );
	const rotate = page.locator( '.wp-swiper-preview__rotate' );
	await expect( rotate ).toBeDisabled();
	await page.click( '.wp-swiper-preview__devices [data-device="mobile"]' );
	await expect.poll( async () => ( await box.evaluate( ( el ) => el.offsetWidth ) ) ).toBe( 390 );
	await expect( rotate ).toBeEnabled();
	await rotate.click();
	await expect.poll( async () => ( await box.evaluate( ( el ) => el.offsetWidth ) ) ).toBe( 844 );
	await rotate.click();
	await page.click( '.wp-swiper-preview__devices [data-device="desktop"]' );
	await expect.poll( async () => ( await box.evaluate( ( el ) => el.offsetWidth ) ) ).toBe( 1280 );

	// Dark screen: at once, in the frame's page.
	const screen = page.locator( '.wp-swiper-preview__screen' );
	await screen.click();
	await expect( screen ).toHaveAttribute( 'aria-pressed', 'true' );
	await expect( frame( page ).locator( 'html.wp-swiper-preview-dark' ) ).toBeAttached();
	await screen.click();
	await expect( frame( page ).locator( 'html.wp-swiper-preview-dark' ) ).toHaveCount( 0 );

	// Developer mode: its boxes over the slides.
	const dev = page.locator( '.wp-swiper-preview__dev' );
	await dev.click();
	await expect( dev ).toHaveAttribute( 'aria-pressed', 'true' );
	await expect( frame( page ).locator( '[class*="wp-swiper-dev"]' ).first() ).toBeAttached();
	await dev.click();
	expect( errors ).toEqual( [] );
} );

test( 'Art Grid shapes: its tools in the preview', async ( { page } ) => {
	const errors = await edit( page, 'art-grid-shapes' );
	await expect( frame( page ).locator( '.wp-swiper-art-tools__mode' ).first() ).toBeVisible( { timeout: 15000 } );
	expect( errors ).toEqual( [] );
} );

test( 'a field\'s device: the whole editor and the preview take it', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await openPage( page, 'layout' );
	await page.click( '[data-field="height"] .wp-swiper-devices__btn[data-device="tablet"]' );
	await expect.poll( async () => page.locator( '.wp-swiper-preview__frame' ).evaluate( ( el ) => el.offsetWidth ) ).toBe( 820 );
	// Another field shows the tablet's value too.
	await expect( page.locator( '[data-field="space_between"] .wp-swiper-devices__btn[data-device="tablet"]' ) ).toHaveClass( /is-active/ );
	await page.click( '.wp-swiper-preview__devices [data-device="desktop"]' );
	await expect( page.locator( '[data-field="height"] .wp-swiper-devices__btn[data-device="desktop"]' ) ).toHaveClass( /is-active/ );
	expect( errors ).toEqual( [] );
} );

test( 'Collage: a photo dragged onto another in the preview, the two swapped, the order kept', async ( { page } ) => {
	const errors = await edit( page, 'collage-grid' );
	await openPage( page, 'layout' );
	const order = page.locator( 'input[name="wp_swiper_settings[collage_order]"]' );
	await expect( order ).toHaveValue( '' );
	const cells = frame( page ).locator( '.wp-swiper-collage__cell' );
	await expect( cells.nth( 1 ) ).toBeVisible();
	const photo = ( index ) => cells.nth( index ).locator( 'img' ).first().getAttribute( 'src' );
	const first = await photo( 0 );
	const second = await photo( 1 );
	expect( first ).not.toBe( second );

	// The preview is scaled into its device: the boxes are where they are on
	// the screen, once there (the mouse does not scroll the page by itself).
	await cells.nth( 0 ).scrollIntoViewIfNeeded();
	const from = await cells.nth( 0 ).boundingBox();
	const to = await cells.nth( 1 ).boundingBox();
	await page.mouse.move( from.x + from.width / 2, from.y + from.height / 2 );
	await page.mouse.down();
	await page.mouse.move( from.x + from.width / 2 + 20, from.y + from.height / 2 + 20, { steps: 4 } );
	await page.mouse.move( to.x + to.width / 2, to.y + to.height / 2, { steps: 10 } );
	await page.mouse.up();

	await expect.poll( () => photo( 0 ) ).toBe( second );
	expect( await photo( 1 ) ).toBe( first );
	await expect( order ).not.toHaveValue( '' );
	expect( errors ).toEqual( [] );
} );

test( 'Collage Overlap: the photo moved goes on top, its arrow sends it back, saved as it is', async ( { page } ) => {
	// Two photos, the first over the second (cases.php collage-overlap-set).
	const errors = await edit( page, 'collage-overlap-set' );
	await openPage( page, 'layout' );
	// No Gap (they lie over one another); its own hints over the preview.
	await expect( page.locator( '.wp-swiper-field[data-field="collage_gap"]' ) ).toBeHidden();
	await expect( page.locator( '.wp-swiper-preview__hint[data-effect="overlap"]' ).first() ).toBeVisible();
	await expect( page.locator( '.wp-swiper-preview__hint[data-effect="freeform"]' ).first() ).toBeHidden();
	// Nor "drop a photo on another to swap them": here it lies over it.
	await expect( page.locator( '.wp-swiper-preview__hint[data-effect="collage"]' ) ).toBeHidden();

	const cells = frame( page ).locator( '.wp-swiper-collage__cell' );
	await expect( cells ).toHaveCount( 2 );
	const layers = () => cells.evaluateAll( ( list ) => list.map( ( cell ) => Number( cell.style.zIndex ) ) );
	const photos = () => cells.evaluateAll( ( list ) => list.map( ( cell ) => cell.querySelector( 'img' ).getAttribute( 'src' ) ) );
	const cuts = page.locator( 'input[name="wp_swiper_settings[collage_cuts]"]' );
	expect( await layers() ).toEqual( [ 2, 1 ] );
	const before = await photos();

	// The lower photo, by its own part (right of the first), moved a little
	// left, over the first: on top now, nothing swapped.
	await cells.nth( 1 ).scrollIntoViewIfNeeded();
	const box = await cells.nth( 1 ).boundingBox();
	const x = box.x + box.width * 0.8;
	const y = box.y + box.height / 2;
	await page.mouse.move( x, y );
	await page.mouse.down();
	await page.mouse.move( x - 10, y, { steps: 3 } );
	await page.mouse.move( x - 40, y, { steps: 6 } );
	await page.mouse.up();
	await expect.poll( layers ).toEqual( [ 1, 2 ] );
	expect( await photos() ).toEqual( before );
	await expect.poll( () => cuts.inputValue() ).toMatch( /^overlap:[\d.]+;[-\d.,]+,0;[-\d.,]+,1$/ );

	// Its arrow: under the other one again (the lower one has none).
	const back = frame( page ).locator( '.wp-swiper-collage__back' );
	await expect( back ).toHaveCount( 1 );
	await back.click();
	await expect.poll( layers ).toEqual( [ 2, 1 ] );
	await expect.poll( () => cuts.inputValue() ).toMatch( /^overlap:[\d.]+;[-\d.,]+,1;[-\d.,]+,0$/ );

	// Saved: as it was left.
	const kept = await cuts.inputValue();
	await Promise.all( [ page.waitForNavigation(), page.click( '#publish' ) ] );
	await page.waitForSelector( '#wp-swiper-settings' );
	await shown( page );
	await expect( page.locator( 'input[name="wp_swiper_settings[collage_cuts]"]' ) ).toHaveValue( kept );
	await expect.poll( layers ).toEqual( [ 2, 1 ] );
	expect( errors ).toEqual( [] );
} );

test( 'Art, more windows than photos: a photo dragged onto the 12th window, saved, still there', async ( { page } ) => {
	const errors = await edit( page, 'art-shape-many' );
	const pieces = frame( page ).locator( '.wp-swiper-art__piece:not([hidden])' );
	await expect( pieces ).toHaveCount( 16 );
	const reals = () => frame( page ).locator( '.wp-swiper-art' ).evaluate( ( block ) => [].slice.call( block.querySelectorAll( '.wp-swiper-art__piece' ) ).filter( ( p ) => ! p.hidden ).map( ( p ) => p.wpReal ) );
	const before = await reals();
	expect( before[ 0 ] ).not.toBe( before[ 11 ] );

	await pieces.nth( 0 ).scrollIntoViewIfNeeded();
	const a = await pieces.nth( 0 ).boundingBox();
	const b = await pieces.nth( 11 ).boundingBox();
	await page.mouse.move( a.x + a.width / 2, a.y + a.height / 2 );
	await page.mouse.down();
	for ( let i = 1; i <= 10; i++ ) {
		await page.mouse.move( a.x + a.width / 2 + ( b.x - a.x ) * i / 10, a.y + a.height / 2 + ( b.y - a.y ) * i / 10 );
	}
	await page.mouse.up();
	const expected = before.slice();
	expected[ 0 ] = before[ 11 ];
	expected[ 11 ] = before[ 0 ];
	await expect.poll( reals ).toEqual( expected );
	// Every window's photo kept: 16 IDs, some of them twice.
	const order = page.locator( '#wp-swiper-art_order' );
	await expect.poll( async () => ( await order.inputValue() ).split( ',' ).length ).toBe( 16 );

	await Promise.all( [ page.waitForNavigation(), page.click( '#publish' ) ] );
	await page.waitForSelector( '#wp-swiper-settings' );
	await shown( page );
	await expect.poll( reals ).toEqual( expected );
	expect( errors ).toEqual( [] );
} );

test( 'Screen size of one\'s own: applied with Update, turned with the device, its note', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	const box = page.locator( '.wp-swiper-preview__frame' );
	const frameSize = () => box.evaluate( ( el ) => [ el.offsetWidth, el.offsetHeight ] );
	const width = page.locator( '.wp-swiper-preview__size-w' );
	const height = page.locator( '.wp-swiper-preview__size-h' );
	const note = page.locator( '.wp-swiper-preview__size-note' );
	const drawing = page.locator( '.wp-swiper-preview__device[data-device="desktop"]' );
	await page.click( '.wp-swiper-preview__devices [data-device="desktop"]' );
	await expect( width ).toHaveAttribute( 'placeholder', '1280' );
	await expect( height ).toHaveAttribute( 'placeholder', '721' );
	await expect( width ).toHaveValue( '' );

	// Typed: nothing yet, Update says it is out of date.
	await width.fill( '900' );
	expect( await frameSize() ).toEqual( [ 1280, 721 ] );
	await expect( page.locator( '.wp-swiper-preview__update' ) ).toHaveClass( /is-stale/ );
	await expect( note ).toBeHidden();
	// Update: that size, in a plain frame; 900px takes the Tablet settings.
	await page.click( '.wp-swiper-preview__update' );
	await expect.poll( frameSize ).toEqual( [ 900, 721 ] );
	await expect( drawing ).toBeHidden();
	await expect( note ).toBeVisible();
	await expect( note ).toContainText( '900px wide' );
	await expect( note ).toContainText( 'Tablet' );
	await shown( page );

	// Mobile: its own size until one is typed; turned, the two swapped.
	await page.click( '.wp-swiper-preview__devices [data-device="mobile"]' );
	await expect.poll( frameSize ).toEqual( [ 390, 844 ] );
	await expect( width ).toHaveValue( '' );
	await expect( width ).toHaveAttribute( 'placeholder', '390' );
	await height.fill( '600' );
	await height.press( 'Enter' );
	await expect.poll( frameSize ).toEqual( [ 390, 600 ] );
	await expect( note ).toBeHidden();
	await page.click( '.wp-swiper-preview__rotate' );
	await expect.poll( frameSize ).toEqual( [ 600, 390 ] );
	await expect( width ).toHaveValue( '600' );
	await expect( height ).toHaveValue( '' );
	await expect( height ).toHaveAttribute( 'placeholder', '390' );
	await page.click( '.wp-swiper-preview__rotate' );
	await expect( height ).toHaveValue( '600' );

	// Desktop again: its size kept; emptied and updated, its own size in its drawing.
	await page.click( '.wp-swiper-preview__devices [data-device="desktop"]' );
	await expect.poll( frameSize ).toEqual( [ 900, 721 ] );
	await expect( width ).toHaveValue( '900' );
	await width.fill( '' );
	await page.click( '.wp-swiper-preview__update' );
	await expect.poll( frameSize ).toEqual( [ 1280, 721 ] );
	await expect( drawing ).toBeVisible();
	await expect( note ).toBeHidden();
	expect( errors ).toEqual( [] );
} );
