// What is done by hand in the editor's preview (preview-collage.js,
// preview-art.js) and kept by the editor (admin-preview.js): Random
// mosaic's lines, Scattered's edges and ×, Freeform's corners and moves,
// Overlap's undo, Grid shapes drawn, Art's photos swapped, Undo / Redo of
// them. Desktop only; setup and helpers: admin-kit.js.
const { test, expect } = require( '@playwright/test' );
const { setup, edit, frame, openPage } = require( './admin-kit' );

setup( test );

// A drag with the mouse from one point to another (page px), in steps.
async function drag( page, from, to, steps = 8 ) {
	await page.mouse.move( from.x, from.y );
	await page.mouse.down();
	// Past the few px a press needs to become a drag first.
	await page.mouse.move( from.x + ( to.x - from.x ) / steps, from.y + ( to.y - from.y ) / steps );
	await page.mouse.move( to.x, to.y, { steps } );
	await page.mouse.up();
}

// The middle of an element (page px), scrolled into view first.
async function middle( locator, dx = 0.5, dy = 0.5 ) {
	await locator.scrollIntoViewIfNeeded();
	const box = await locator.boundingBox();
	return { x: box.x + box.width * dx, y: box.y + box.height * dy };
}

// The collage's places in the frame: grid px, rounded.
function cellBoxes( page ) {
	return frame( page ).locator( '.wp-swiper-collage__cell' ).evaluateAll( ( cells ) => cells.map( ( cell ) => [ cell.offsetLeft, cell.offsetTop, cell.offsetWidth, cell.offsetHeight ].map( Math.round ) ) );
}

const setting = ( page, name ) => page.locator( 'input[name="wp_swiper_settings[' + name + ']"]' );

test( 'Random mosaic: a line dragged resizes the photos beside it, the mosaic kept', async ( { page } ) => {
	const errors = await edit( page, 'collage-random' );
	await openPage( page, 'layout' );
	await expect( setting( page, 'collage_mosaic' ) ).toHaveValue( '' );
	const line = frame( page ).locator( '.wp-swiper-collage__line--vertical:not(.wp-swiper-collage__edge)' ).first();
	await expect( line ).toBeAttached();
	const before = await cellBoxes( page );

	const from = await middle( line );
	await drag( page, from, { x: from.x + 40, y: from.y } );

	await expect.poll( () => setting( page, 'collage_mosaic' ).inputValue() ).toMatch( /^[VH][\d.]+\[/ );
	expect( await cellBoxes( page ) ).not.toEqual( before );
	expect( errors ).toEqual( [] );
} );

test( 'Scattered: an edge dragged resizes its photo, × takes a place out', async ( { page } ) => {
	const errors = await edit( page, 'collage-scatter' );
	await openPage( page, 'layout' );
	const cells = frame( page ).locator( '.wp-swiper-collage__cell' );
	await expect( cells ).toHaveCount( 6 );
	const before = await cellBoxes( page );

	const edge = frame( page ).locator( '.wp-swiper-collage__edge.wp-swiper-collage__line--vertical' ).first();
	const from = await middle( edge );
	await drag( page, from, { x: from.x + 30, y: from.y } );
	await expect.poll( () => setting( page, 'collage_cuts' ).inputValue() ).toMatch( /^scatter:/ );
	expect( await cellBoxes( page ) ).not.toEqual( before );

	// Its ×: one place fewer on this device, the number kept.
	await frame( page ).locator( '.wp-swiper-collage__remove' ).first().click();
	await expect( cells ).toHaveCount( 5 );
	await expect( setting( page, 'collage_count' ) ).toHaveValue( '5' );
	expect( errors ).toEqual( [] );
} );

test( 'Freeform: a corner resizes a photo, a drag moves it; Undo and Redo bring them back', async ( { page } ) => {
	const errors = await edit( page, 'collage-freeform' );
	await openPage( page, 'layout' );
	const cuts = setting( page, 'collage_cuts' );
	await expect( cuts ).toHaveValue( '' );
	const first = await cellBoxes( page );

	// The first photo's lower right corner, inwards: smaller (nothing in the way).
	const corner = frame( page ).locator( '.wp-swiper-collage__grip--se' ).first();
	const from = await middle( corner );
	await drag( page, from, { x: from.x - 30, y: from.y - 30 } );
	await expect.poll( () => cuts.inputValue() ).toMatch( /^freeform:/ );
	const resized = await cellBoxes( page );
	expect( resized[ 0 ][ 2 ] ).toBeLessThan( first[ 0 ][ 2 ] );
	expect( resized[ 0 ][ 3 ] ).toBeLessThan( first[ 0 ][ 3 ] );
	const afterResize = await cuts.inputValue();
	// Changes of one field within 0.8 s are one step of the history (admin-preview.js noteChange()).
	await page.waitForTimeout( 1000 );

	// Then moved into the room it left (down and right).
	const photo = frame( page ).locator( '.wp-swiper-collage__cell' ).first();
	const at = await middle( photo );
	await drag( page, at, { x: at.x + 12, y: at.y + 12 } );
	await expect.poll( () => cuts.inputValue() ).not.toBe( afterResize );
	const moved = await cellBoxes( page );
	expect( [ moved[ 0 ][ 0 ], moved[ 0 ][ 1 ] ] ).not.toEqual( [ resized[ 0 ][ 0 ], resized[ 0 ][ 1 ] ] );
	expect( [ moved[ 0 ][ 2 ], moved[ 0 ][ 3 ] ] ).toEqual( [ resized[ 0 ][ 2 ], resized[ 0 ][ 3 ] ] );
	const afterMove = await cuts.inputValue();
	await page.waitForTimeout( 1000 );

	// Undo: the move, then the resize, in the field and in the preview at once.
	const undo = page.locator( '.wp-swiper-preview__undo' );
	const redo = page.locator( '.wp-swiper-preview__redo' );
	await undo.click();
	await expect( cuts ).toHaveValue( afterResize );
	await expect.poll( () => cellBoxes( page ) ).toEqual( resized );
	await undo.click();
	await expect( cuts ).toHaveValue( '' );
	await expect.poll( () => cellBoxes( page ) ).toEqual( first );
	// Redo: both again.
	await redo.click();
	await expect( cuts ).toHaveValue( afterResize );
	await redo.click();
	await expect( cuts ).toHaveValue( afterMove );
	await expect.poll( () => cellBoxes( page ) ).toEqual( moved );
	expect( errors ).toEqual( [] );
} );

test( 'Overlap: Undo puts the photo moved back under the other one', async ( { page } ) => {
	const errors = await edit( page, 'collage-overlap-set' );
	await openPage( page, 'layout' );
	const cells = frame( page ).locator( '.wp-swiper-collage__cell' );
	const layers = () => cells.evaluateAll( ( list ) => list.map( ( cell ) => Number( cell.style.zIndex ) ) );
	const cuts = setting( page, 'collage_cuts' );
	const before = await cuts.inputValue();
	expect( await layers() ).toEqual( [ 2, 1 ] );

	const from = await middle( cells.nth( 1 ), 0.8 );
	await drag( page, from, { x: from.x - 40, y: from.y } );
	await expect.poll( layers ).toEqual( [ 1, 2 ] );
	await page.waitForTimeout( 1000 );

	await page.locator( '.wp-swiper-preview__undo' ).click();
	await expect( cuts ).toHaveValue( before );
	await expect.poll( layers ).toEqual( [ 2, 1 ] );
	expect( errors ).toEqual( [] );
} );

test( 'Grid shapes: cells dragged over join one shape, Split into cells takes it apart', async ( { page } ) => {
	const errors = await edit( page, 'art-grid-shapes' );
	await openPage( page, 'layout' );
	const value = page.locator( '#wp-swiper-art_cells' );
	const before = await value.inputValue();
	// The shape of each cell, as kept ("4x4:0,0,1,…;…").
	const shapes = async () => ( /^\d+x\d+:([-\d,]+)/.exec( await value.inputValue() ) || [ '', '' ] )[ 1 ].split( ',' ).map( Number );

	await frame( page ).locator( '.wp-swiper-art-tools__mode' ).first().click();
	// The stage's default 4 × 4 cells: from the first to the second of the second row.
	const stage = frame( page ).locator( '.wp-swiper-art__stage' );
	const a = await middle( stage, 1 / 8, 1 / 8 );
	const b = await middle( stage, 3 / 8, 3 / 8 );
	await drag( page, a, b );
	await expect.poll( () => value.inputValue() ).not.toBe( before );
	const joined = await shapes();
	expect( joined.length ).toBe( 16 );
	expect( new Set( [ joined[ 0 ], joined[ 1 ], joined[ 4 ], joined[ 5 ] ] ).size ).toBe( 1 );

	// That shape is selected: its panel; split, each of its cells a shape of its own.
	const panel = frame( page ).locator( '.wp-swiper-art-tools__panel' );
	await expect( panel ).toBeVisible();
	await panel.locator( '.wp-swiper-art-tools__action' ).nth( 1 ).click();
	await expect.poll( async () => {
		const split = await shapes();
		return new Set( [ split[ 0 ], split[ 1 ], split[ 4 ], split[ 5 ] ] ).size;
	} ).toBe( 4 );
	expect( errors ).toEqual( [] );
} );

test( 'Art: a photo dragged onto another window swaps the two, the order kept', async ( { page } ) => {
	const errors = await edit( page, 'art-grid-circle' );
	await openPage( page, 'layout' );
	const pieces = frame( page ).locator( '.wp-swiper-art__piece:not([hidden]):not(.wp-swiper-art__piece--content)' );
	await expect( pieces.nth( 2 ) ).toBeVisible();
	const reals = () => pieces.evaluateAll( ( list ) => list.map( ( piece ) => piece.wpReal ) );
	const before = await reals();
	// Two windows whose middles are their own (the middle circle lies over
	// some of the grid's).
	const clear = await pieces.evaluateAll( ( list ) => list.map( ( piece, i ) => {
		const r = piece.getBoundingClientRect();
		const top = document.elementFromPoint( r.left + r.width / 2, r.top + r.height / 2 );
		return top && top.closest( '.wp-swiper-art__piece' ) === piece ? i : -1;
	} ).filter( ( i ) => i >= 0 ) );
	expect( clear.length ).toBeGreaterThan( 1 );
	const [ one, two ] = clear;
	expect( before[ one ] ).not.toBe( before[ two ] );

	await drag( page, await middle( pieces.nth( one ) ), await middle( pieces.nth( two ) ), 10 );
	const expected = before.slice();
	expected[ one ] = before[ two ];
	expected[ two ] = before[ one ];
	await expect.poll( reals ).toEqual( expected );
	await expect.poll( () => page.locator( '#wp-swiper-art_order' ).inputValue() ).not.toBe( '' );
	expect( errors ).toEqual( [] );
} );
