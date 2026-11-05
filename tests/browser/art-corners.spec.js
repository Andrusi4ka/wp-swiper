// Grid shapes in the preview (preview-art.js on cases.php art-grid-shapes,
// added to the page as the preview's frame has it): a corner dot dragged
// rounds the whole shape, the red one (clicked) that corner alone; a click
// moves the red dot or takes it off; the value by the dot while dragging;
// Escape puts the layout back. Desktop only (a mouse).
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const base = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/';

test.skip( ( { isMobile } ) => isMobile, 'dragged with a mouse' );

function state( page ) {
	return page.evaluate( () => {
		const block = document.querySelector( '.wp-swiper-art--grid-shapes' );
		const grid = block.wpSwiperArt.grid();
		const ed = block.wpSwiperArtEditor;
		const shape = ed.selected >= 0 ? grid.cells[ ed.selected ] : -1;
		const outline = shape >= 0 ? block.wpSwiperArtOutlines[ shape ] : null;
		const stage = ed.stage.getBoundingClientRect();
		const label = ed.overlay.querySelector( '.is-corner-value' );
		return {
			text: window.wpSwiperArtCells.text( grid ),
			shape,
			radius: shape >= 0 && grid.radii ? grid.radii[ shape ] : undefined,
			corners: Object.assign( {}, grid.corners || {} ),
			red: ed.corner || null,
			// The dots, the one at the top left last: the Shapes / Photos buttons lie over it.
			dots: outline ? outline.corners.map( ( c ) => ( { key: c.key, x: stage.left + c.x, y: stage.top + c.y, dx: c.dx, dy: c.dy } ) )
				.sort( ( a, b ) => ( b.x + b.y ) - ( a.x + a.y ) ) : [],
			label: label ? label.textContent : null,
			stage: { x: stage.left, y: stage.top, w: stage.width, h: stage.height },
		};
	} );
}

// A dot pressed, moved `by` px inwards (out: less than 0) along its diagonal; the state then.
async function drag( page, dot, by, before ) {
	await page.mouse.move( dot.x, dot.y );
	await page.mouse.down();
	for ( let i = 1; i <= 5; i++ ) {
		await page.mouse.move( dot.x + dot.dx * by * i / 5, dot.y + dot.dy * by * i / 5 );
	}
	const during = await state( page );
	if ( before ) {
		await before();
	}
	await page.mouse.up();
	return during;
}

test( 'art-grid-shapes: corner dots dragged round the shape or one corner', async ( { page } ) => {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( base + 'tests/browser/fixture.php?case=art-grid-shapes' );
	await page.waitForFunction( () => document.querySelector( '.wp-swiper-art--grid-shapes' ).wpSwiperArt );
	await page.addStyleTag( { url: base + 'assets/css/preview-art.css' } );
	await page.addScriptTag( { url: base + 'assets/js/preview-art.js' } );
	await page.waitForFunction( () => document.querySelector( '.wp-swiper-art--grid-shapes' ).wpSwiperArtEditor );

	// The shape at the bottom left selected: its dots.
	let s = await state( page );
	await page.mouse.click( s.stage.x + s.stage.w * 0.2, s.stage.y + s.stage.h * 0.85 );
	s = await state( page );
	expect( s.shape ).toBeGreaterThanOrEqual( 0 );
	expect( s.dots.length ).toBeGreaterThanOrEqual( 4 );
	const shape = s.shape;

	// A blue dot dragged in: the whole shape rounder, its value shown while dragging.
	const was = s.radius;
	let during = await drag( page, s.dots[ 0 ], 20 );
	expect( during.label ).toMatch( /^\d+%$/ );
	s = await state( page );
	expect( s.label ).toBeNull();
	expect( typeof s.radius ).toBe( 'number' );
	expect( s.radius ).not.toBe( was );
	expect( s.corners ).toEqual( {} );
	const all = s.radius;

	// A click: that dot red; dragged out, that corner alone, sharper.
	const key = s.dots[ 1 ].key;
	await page.mouse.click( s.dots[ 1 ].x, s.dots[ 1 ].y );
	s = await state( page );
	expect( s.red ).toBe( key );
	await drag( page, s.dots.find( ( d ) => d.key === key ), -10 );
	s = await state( page );
	expect( s.radius ).toBe( all );
	expect( s.corners[ key ] ).toBeLessThan( all );
	expect( s.red ).toBe( key );

	// Another dot clicked: it red instead; again: none.
	const other = s.dots.find( ( d ) => d.key !== key );
	await page.mouse.click( other.x, other.y );
	expect( ( await state( page ) ).red ).toBe( other.key );
	await page.mouse.click( other.x, other.y );
	expect( ( await state( page ) ).red ).toBeNull();

	// A red dot there, a blue one dragged: the whole shape, no red dot; the corner keeps its own.
	await page.mouse.click( other.x, other.y );
	s = await state( page );
	const own = s.corners[ key ];
	await drag( page, s.dots.find( ( d ) => d.key !== key && d.key !== other.key ), -10 );
	s = await state( page );
	expect( s.red ).toBeNull();
	expect( s.radius ).toBeLessThan( all );
	expect( s.corners[ key ] ).toBe( own );

	// Escape while dragging: the layout as it was, the shape still selected.
	const before = s.text;
	during = await drag( page, s.dots[ 0 ], 25, () => page.keyboard.press( 'Escape' ) );
	expect( during.text ).not.toBe( before );
	s = await state( page );
	expect( s.text ).toBe( before );
	expect( s.shape ).toBe( shape );

	expect( errors ).toEqual( [] );
} );
