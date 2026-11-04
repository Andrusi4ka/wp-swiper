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
	// The turned grid's empty corners cut off (the shadows shown past the edges).
	expect( found.overflow ).toBe( 'clip' );
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
// As large as fits: the photos' outermost corners (not their box turned)
// touch the block's top and bottom or its sides. Height "Auto": the block
// from the top-most point to the bottom-most. No page scrollbar from the
// turned grid's corners.
for ( const [ name, angle, auto ] of [ [ 'collage-rotate-scatter', 25, false ], [ 'collage-rotate-free', -30, false ], [ 'collage-rotate-scatter-auto', 14, true ], [ 'collage-rotate-free-auto', 20, true ] ] ) {
	test( name + ': turned in the block, all of it in it', async ( { page } ) => {
		const errors = await open( page, name );
		const found = await state( page );
		expect( found.angle ).toBe( angle );
		expect( found.transform ).toBe( 'rotate(' + angle + 'deg)' );
		expect( found.overflow ).toBe( 'clip' );
		expect( found.places.length ).toBeGreaterThan( 3 );
		for ( const place of found.places ) {
			expect( Math.abs( place.drawn[ 0 ] - place.size[ 0 ] ) ).toBeLessThanOrEqual( 1 );
			expect( place.covered ).toBe( true );
		}
		const fit = await page.evaluate( () => {
			const block = document.querySelector( '.wp-swiper-collage' ).getBoundingClientRect();
			const cells = [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) ).map( ( cell ) => cell.getBoundingClientRect() );
			const reach = ( pick, side ) => Math[ side ].apply( null, cells.map( pick ) );
			return {
				inside: cells.every( ( r ) => r.left >= block.left - 1 && r.right <= block.right + 1 && r.top >= block.top - 1 && r.bottom <= block.bottom + 1 ),
				// How far the photos are from each of the block's edges.
				top: reach( ( r ) => r.top, 'min' ) - block.top,
				bottom: block.bottom - reach( ( r ) => r.bottom, 'max' ),
				left: reach( ( r ) => r.left, 'min' ) - block.left,
				right: block.right - reach( ( r ) => r.right, 'max' ),
				scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			};
		} );
		expect( fit.inside ).toBe( true );
		// In the middle, touching one pair of edges.
		expect( Math.abs( fit.top - fit.bottom ) ).toBeLessThanOrEqual( 1 );
		expect( Math.abs( fit.left - fit.right ) ).toBeLessThanOrEqual( 1 );
		expect( Math.min( fit.top + fit.bottom, fit.left + fit.right ) ).toBeLessThanOrEqual( 2 );
		if ( auto ) {
			expect( fit.top + fit.bottom ).toBeLessThanOrEqual( 2 );
		}
		expect( fit.scroll ).toBeLessThanOrEqual( 0 );
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

// Developer mode, Scattered turned: the box round the photos (turned with
// them), not the block's; the block's label with the angle only.
test( 'collage-rotate-scatter-auto: Developer mode boxes the photos', async ( { page } ) => {
	const errors = await open( page, 'collage-rotate-scatter-auto' );
	const base = fixture.replace( 'tests/browser/fixture.php', '' );
	await page.addStyleTag( { url: base + 'assets/css/preview-dev.css' } );
	await page.addScriptTag( { url: base + 'assets/js/preview-dev.js' } );
	await page.evaluate( () => window.wpSwiperDev( true ) );
	await page.waitForTimeout( 300 );
	const drawn = await page.evaluate( () => {
		const cells = [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) );
		const frame = document.querySelectorAll( '.wp-swiper-dev__grid' );
		const box = frame[ 0 ] ? { left: frame[ 0 ].offsetLeft, top: frame[ 0 ].offsetTop, right: frame[ 0 ].offsetLeft + frame[ 0 ].offsetWidth, bottom: frame[ 0 ].offsetTop + frame[ 0 ].offsetHeight } : null;
		return {
			frames: frame.length,
			// The photos' box in the grid: each photo in it, one at each of its edges.
			tight: !! box && [ 'left', 'top' ].every( ( side ) => cells.some( ( c ) => Math.abs( ( 'left' === side ? c.offsetLeft : c.offsetTop ) - box[ side ] ) <= 1 ) ) &&
				cells.some( ( c ) => Math.abs( c.offsetLeft + c.offsetWidth - box.right ) <= 1 ) &&
				cells.some( ( c ) => Math.abs( c.offsetTop + c.offsetHeight - box.bottom ) <= 1 ),
			block: document.querySelector( '.wp-swiper-dev--block .wp-swiper-dev__block-size' ).textContent,
		};
	} );
	expect( drawn.frames ).toBe( 1 );
	expect( drawn.tight ).toBe( true );
	expect( drawn.block ).toMatch( /^[\d.]+ × [\d.]+, 14°$/ );
	expect( errors ).toEqual( [] );
} );

// Height "Auto" in a tall column (more free height than the turned photos
// need: they touch the sides first): the block cut to them, no empty bands;
// as before once the column is short again.
for ( const name of [ 'collage-rotate-scatter-auto', 'collage-rotate-free-auto' ] ) {
	test( name + ': in a tall column, cut to the photos', async ( { page } ) => {
		const errors = await open( page, name );
		const measure = () => page.evaluate( () => {
			const block = document.querySelector( '.wp-swiper-collage' ).getBoundingClientRect();
			const cells = [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) ).map( ( cell ) => cell.getBoundingClientRect() );
			return {
				height: block.height,
				root: document.querySelector( '.wp-swiper' ).getBoundingClientRect().height,
				top: Math.min.apply( null, cells.map( ( r ) => r.top ) ) - block.top,
				bottom: block.bottom - Math.max.apply( null, cells.map( ( r ) => r.bottom ) ),
				sides: Math.min.apply( null, cells.map( ( r ) => r.left ) ) - block.left + block.right - Math.max.apply( null, cells.map( ( r ) => r.right ) ),
			};
		} );
		const before = await measure();
		await page.evaluate( () => {
			document.querySelector( '.fixture' ).style.height = '2000px';
			window.dispatchEvent( new Event( 'resize' ) );
		} );
		await page.waitForTimeout( 300 );
		const tall = await measure();
		expect( tall.sides ).toBeLessThanOrEqual( 2 );
		expect( tall.top + tall.bottom ).toBeLessThanOrEqual( 2 );
		expect( Math.abs( tall.root - tall.height ) ).toBeLessThanOrEqual( 1 );
		expect( tall.height ).toBeLessThan( 1900 );
		await page.evaluate( () => window.dispatchEvent( new Event( 'resize' ) ) );
		await page.waitForTimeout( 300 );
		expect( Math.abs( ( await measure() ).height - tall.height ) ).toBeLessThanOrEqual( 1 );
		await page.evaluate( () => {
			document.querySelector( '.fixture' ).style.height = '';
			window.dispatchEvent( new Event( 'resize' ) );
		} );
		await page.waitForTimeout( 300 );
		expect( Math.abs( ( await measure() ).height - before.height ) ).toBeLessThanOrEqual( 1 );
		expect( errors ).toEqual( [] );
	} );
}
