// A turned collage (cases.php collage-rotate*): the grid turned by the
// device's angle, each photo upright and covering its place, the block
// filled (Fill) or the whole collage in it (Fit); not turned at 0.
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
		const block = document.querySelector( '.wp-swiper-collage' );
		const grid = block.querySelector( '.wp-swiper-collage__grid' );
		const box = block.getBoundingClientRect();
		// Where a point of the block is: in the grid (a place or a gap) or not.
		const inGrid = ( x, y ) => {
			const node = document.elementFromPoint( x, y );
			return !! ( node && node.closest( '.wp-swiper-collage__grid' ) === grid );
		};
		const corners = [ [ box.left + 3, box.top + 3 ], [ box.right - 3, box.top + 3 ], [ box.left + 3, box.bottom - 3 ], [ box.right - 3, box.bottom - 3 ] ];
		return {
			angle: grid.wpAngle || 0,
			turned: block.classList.contains( 'wp-swiper-collage--turned' ),
			transform: grid.style.transform,
			overflow: getComputedStyle( block ).overflow,
			blockHeight: Math.round( box.height ),
			corners: corners.map( ( c ) => inGrid( c[ 0 ], c[ 1 ] ) ),
			places: [].slice.call( grid.querySelectorAll( '.wp-swiper-collage__cell' ) ).map( ( cell ) => {
				const item = cell.lastElementChild;
				const r = item.getBoundingClientRect();
				const cs = getComputedStyle( cell );
				return {
					// Upright: its box on the page as large as it is (not turned).
					drawn: [ r.width, r.height ].map( Math.round ),
					size: [ item.offsetWidth, item.offsetHeight ],
					// Covering its place: the place's corners (turned) inside the photo.
					covered: [ [ 0, 0 ], [ cell.offsetWidth, 0 ], [ 0, cell.offsetHeight ], [ cell.offsetWidth, cell.offsetHeight ] ].every( ( p ) => {
						const m = new DOMMatrix( getComputedStyle( grid ).transform );
						// The corner on the page: the grid's turn about its middle.
						const gx = cell.offsetLeft + p[ 0 ] - grid.offsetWidth / 2;
						const gy = cell.offsetTop + p[ 1 ] - grid.offsetHeight / 2;
						const g = grid.getBoundingClientRect();
						const x = g.left + g.width / 2 + m.a * gx + m.c * gy;
						const y = g.top + g.height / 2 + m.b * gx + m.d * gy;
						return x >= r.left - 1 && x <= r.right + 1 && y >= r.top - 1 && y <= r.bottom + 1;
					} ),
					frame: getComputedStyle( cell, '::after' ).borderTopWidth,
					caption: ( () => {
						const caption = item.querySelector( '.wp-swiper__caption' );
						if ( ! caption ) {
							return null;
						}
						const c = caption.querySelector( '.wp-swiper__caption-text' ).getBoundingClientRect();
						const cellBox = cell.getBoundingClientRect();
						return Math.round( Math.hypot( c.left + c.width / 2 - ( cellBox.left + cellBox.width / 2 ), c.top + c.height / 2 - ( cellBox.top + cellBox.height / 2 ) ) );
					} )(),
					radius: cs.borderTopLeftRadius,
				};
			} ),
		};
	} );
}

test( 'collage-rotate: turned, its photos upright, the block filled', async ( { page } ) => {
	const errors = await open( page, 'collage-rotate' );
	const found = await state( page );
	const mobile = page.viewportSize().width < 768;
	if ( mobile ) {
		// 0 on mobile: not turned.
		expect( found.turned ).toBe( false );
		expect( found.transform ).toBe( '' );
	} else {
		expect( found.angle ).toBe( 30 );
		expect( found.turned ).toBe( true );
		expect( found.transform ).toBe( 'rotate(30deg)' );
		expect( found.overflow ).toBe( 'hidden' );
		expect( found.blockHeight ).toBe( 420 );
		// Fill: every corner of the block has the collage.
		expect( found.corners ).toEqual( [ true, true, true, true ] );
		expect( found.places.length ).toBeGreaterThan( 5 );
		for ( const place of found.places ) {
			expect( Math.abs( place.drawn[ 0 ] - place.size[ 0 ] ) ).toBeLessThanOrEqual( 1 );
			expect( Math.abs( place.drawn[ 1 ] - place.size[ 1 ] ) ).toBeLessThanOrEqual( 1 );
			expect( place.covered ).toBe( true );
		}
	}
	expect( errors ).toEqual( [] );
} );

test( 'collage-rotate-fit: all of it in the block, the frame on the place, the caption in the middle', async ( { page } ) => {
	const errors = await open( page, 'collage-rotate-fit' );
	const found = await state( page );
	expect( found.angle ).toBe( -20 );
	expect( found.transform ).toBe( 'rotate(-20deg)' );
	expect( found.overflow ).toBe( 'visible' );
	// Fit: the block's corners empty.
	expect( found.corners ).toEqual( [ false, false, false, false ] );
	for ( const place of found.places ) {
		expect( Math.abs( place.drawn[ 0 ] - place.size[ 0 ] ) ).toBeLessThanOrEqual( 1 );
		expect( place.covered ).toBe( true );
		expect( place.frame ).toBe( '3px' );
		expect( place.caption ).not.toBeNull();
		expect( place.caption ).toBeLessThanOrEqual( 2 );
	}
	expect( errors ).toEqual( [] );
} );

// Scattered and Freeform: turned where they are, every place (its corners,
// turned) inside the block, the photos upright and covering their places.
for ( const [ name, angle ] of [ [ 'collage-rotate-scatter', 25 ], [ 'collage-rotate-free', -30 ] ] ) {
	test( name + ': turned in the block, all of it in it', async ( { page } ) => {
		const errors = await open( page, name );
		const found = await state( page );
		expect( found.angle ).toBe( angle );
		expect( found.transform ).toBe( 'rotate(' + angle + 'deg)' );
		expect( found.overflow ).toBe( 'visible' );
		expect( found.places.length ).toBeGreaterThan( 3 );
		for ( const place of found.places ) {
			expect( Math.abs( place.drawn[ 0 ] - place.size[ 0 ] ) ).toBeLessThanOrEqual( 1 );
			expect( place.covered ).toBe( true );
		}
		const inside = await page.evaluate( () => {
			const block = document.querySelector( '.wp-swiper-collage' ).getBoundingClientRect();
			return [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) ).every( ( cell ) => {
				const r = cell.getBoundingClientRect();
				return r.left >= block.left - 1 && r.right <= block.right + 1 && r.top >= block.top - 1 && r.bottom <= block.bottom + 1;
			} );
		} );
		expect( inside ).toBe( true );
		expect( errors ).toEqual( [] );
	} );
}

// Developer mode of the preview (preview-dev.js, loaded here as the preview
// does): a collage's places with their sizes, the Gap between them, and the
// block's size (turned: the angle and the size laid out in); gone when off.
for ( const name of [ 'collage-grid', 'collage-rotate-fit' ] ) {
	test( name + ': Developer mode draws the places and the gaps', async ( { page } ) => {
		const errors = await open( page, name );
		const base = fixture.replace( 'tests/browser/fixture.php', '' );
		await page.addStyleTag( { url: base + 'assets/css/preview-dev.css' } );
		await page.addScriptTag( { url: base + 'assets/js/preview-dev.js' } );
		await page.evaluate( () => window.wpSwiperDev( true ) );
		await page.waitForTimeout( 300 );
		const drawn = await page.evaluate( () => {
			const cells = [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) );
			const boxes = [].slice.call( document.querySelectorAll( '.wp-swiper-dev--collage .wp-swiper-dev__media' ) );
			return {
				cells: cells.length,
				boxes: boxes.length,
				sizes: cells.every( ( cell ) => [].some.call( document.querySelectorAll( '.wp-swiper-dev__size' ), ( label ) => label.textContent === Math.round( cell.offsetWidth * 10 ) / 10 + ' × ' + Math.round( cell.offsetHeight * 10 ) / 10 ) ),
				gaps: [].map.call( document.querySelectorAll( '.wp-swiper-dev--collage .wp-swiper-dev__dimension:not(.wp-swiper-dev__size)' ), ( label ) => Math.round( Number( label.textContent ) ) ),
				block: document.querySelector( '.wp-swiper-dev--block .wp-swiper-dev__block-size' ).textContent,
				grid: document.querySelectorAll( '.wp-swiper-dev__grid' ).length,
			};
		} );
		expect( drawn.boxes ).toBe( drawn.cells );
		expect( drawn.sizes ).toBe( true );
		expect( drawn.gaps.length ).toBeGreaterThan( 0 );
		// The Gap (8, the default), give or take a grid's rounding.
		for ( const gap of drawn.gaps ) {
			expect( Math.abs( gap - 8 ) ).toBeLessThanOrEqual( 1 );
		}
		if ( 'collage-rotate-fit' === name ) {
			expect( drawn.block ).toMatch( /, -20°, laid out in [\d.]+ × [\d.]+$/ );
			expect( drawn.grid ).toBe( 1 );
		} else {
			expect( drawn.block ).toMatch( /^[\d.]+ × [\d.]+$/ );
			expect( drawn.grid ).toBe( 0 );
		}
		await page.evaluate( () => window.wpSwiperDev( false ) );
		expect( await page.locator( '.wp-swiper-collage .wp-swiper-dev' ).count() ).toBe( 0 );
		expect( errors ).toEqual( [] );
	} );
}
