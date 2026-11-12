// Collage, the Overlap Style (cases.php collage-overlap*): Freeform's photos
// over one another, each place on a layer of its own (collage.js place():
// z-index); the photo on top takes the click (the lightbox opens it).
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

async function open( page, name ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	page.on( 'console', ( message ) => {
		if ( 'error' === message.type() ) {
			errors.push( message.text() );
		}
	} );
	await page.goto( fixture + '?case=' + name );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 400 );
	return errors;
}

// The places: where they are in the grid, their layers, their photos.
function places( page ) {
	return page.evaluate( () => [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) ).map( ( cell ) => ( {
		x: cell.offsetLeft,
		y: cell.offsetTop,
		w: cell.offsetWidth,
		h: cell.offsetHeight,
		z: Number( cell.style.zIndex ),
		src: ( cell.querySelector( 'img' ) || {} ).currentSrc || '',
	} ) ) );
}

// A file's name without WordPress's size ("-300x200", "-scaled") and folders.
const file = ( url ) => String( url ).split( '/' ).pop().replace( /\?.*$/, '' ).replace( /-(\d+x\d+|scaled)(?=\.\w+$)/, '' );

test.describe( 'Collage: Overlap', () => {
	test( 'its photos lie over one another, each on a layer of its own, in the block', async ( { page } ) => {
		const errors = await open( page, 'collage-overlap' );
		const list = await places( page );
		expect( list.length ).toBe( 7 );
		// Layers 1 … 7, each once.
		expect( list.map( ( p ) => p.z ).sort( ( a, b ) => a - b ) ).toEqual( [ 1, 2, 3, 4, 5, 6, 7 ] );
		let over = 0;
		list.forEach( ( a, i ) => list.forEach( ( b, j ) => {
			if ( j > i && Math.min( a.x + a.w, b.x + b.w ) - Math.max( a.x, b.x ) > 1 && Math.min( a.y + a.h, b.y + b.h ) - Math.max( a.y, b.y ) > 1 ) {
				over++;
			}
		} ) );
		expect( over ).toBeGreaterThan( 2 );
		// All in the block (the grid as large as it, nothing past it).
		const grid = await page.evaluate( () => {
			const g = document.querySelector( '.wp-swiper-collage__grid' );
			return { w: g.clientWidth, h: g.clientHeight };
		} );
		list.forEach( ( p ) => {
			expect( p.x ).toBeGreaterThanOrEqual( -1 );
			expect( p.y ).toBeGreaterThanOrEqual( -1 );
			expect( p.x + p.w ).toBeLessThanOrEqual( grid.w + 1 );
			expect( p.y + p.h ).toBeLessThanOrEqual( grid.h + 1 );
		} );
		// The shadow on each place, so it shows which one lies on top.
		const shadow = await page.evaluate( () => getComputedStyle( document.querySelector( '.wp-swiper-collage__cell' ) ).boxShadow );
		expect( shadow ).not.toBe( 'none' );
		expect( errors ).toEqual( [] );
	} );

	for ( const [ name, top ] of [ [ 'collage-overlap-set', 0 ], [ 'collage-overlap-set-under', 1 ] ] ) {
		test( 'as set by hand, the photo on top takes the click there (' + name + ')', async ( { page } ) => {
			const errors = await open( page, name );
			const list = await places( page );
			expect( list.length ).toBe( 2 );
			expect( list[ top ].z ).toBeGreaterThan( list[ 1 - top ].z );
			// The middle of the part where the two lie over one another.
			const spot = await page.evaluate( () => {
				const [ a, b ] = [].slice.call( document.querySelectorAll( '.wp-swiper-collage__cell' ) ).map( ( cell ) => cell.getBoundingClientRect() );
				return { x: ( Math.max( a.left, b.left ) + Math.min( a.right, b.right ) ) / 2, y: ( Math.max( a.top, b.top ) + Math.min( a.bottom, b.bottom ) ) / 2 };
			} );
			const hit = await page.evaluate( ( p ) => {
				const cell = document.elementFromPoint( p.x, p.y ).closest( '.wp-swiper-collage__cell' );
				return [].indexOf.call( document.querySelectorAll( '.wp-swiper-collage__cell' ), cell );
			}, spot );
			expect( hit ).toBe( top );
			await page.mouse.click( spot.x, spot.y );
			const lightbox = page.locator( '.wp-swiper-lightbox' );
			await expect( lightbox ).toBeVisible();
			// Its photo: the one on top.
			await page.waitForTimeout( 300 );
			const shown = await page.evaluate( () => {
				const img = document.querySelector( '.wp-swiper-lightbox .swiper-slide-active img' );
				return img ? img.currentSrc || img.src : '';
			} );
			expect( file( shown ) ).toBe( file( list[ top ].src ) );
			await page.keyboard.press( 'Escape' );
			await expect( lightbox ).toBeHidden();
			expect( errors ).toEqual( [] );
		} );
	}

	test( 'Height Auto: the photos as large as fit the block, not the frame they were set in', async ( { page } ) => {
		// The photos' box in the block (grid px) and the block's size.
		const fit = async ( name ) => {
			await open( page, name );
			return page.evaluate( () => {
				const grid = document.querySelector( '.wp-swiper-collage__grid' );
				const cells = [].slice.call( grid.querySelectorAll( '.wp-swiper-collage__cell' ) );
				const left = Math.min( ...cells.map( ( c ) => c.offsetLeft ) );
				const top = Math.min( ...cells.map( ( c ) => c.offsetTop ) );
				const right = Math.max( ...cells.map( ( c ) => c.offsetLeft + c.offsetWidth ) );
				const bottom = Math.max( ...cells.map( ( c ) => c.offsetTop + c.offsetHeight ) );
				return { left, top, right, bottom, width: grid.clientWidth, height: grid.clientHeight };
			} );
		};
		// Auto: touching two opposite sides of the block, in its middle.
		const auto = await fit( 'collage-overlap-auto' );
		const across = Math.abs( auto.left ) < 2 && Math.abs( auto.right - auto.width ) < 2;
		const down = Math.abs( auto.top ) < 2 && Math.abs( auto.bottom - auto.height ) < 2;
		expect( across || down ).toBe( true );
		expect( Math.abs( auto.left - ( auto.width - auto.right ) ) ).toBeLessThan( 2 );
		expect( Math.abs( auto.top - ( auto.height - auto.bottom ) ) ).toBeLessThan( 2 );
		// px: the frame as set, its empty margins round the photos.
		const px = await fit( 'collage-overlap-px' );
		expect( px.left ).toBeGreaterThan( 20 );
		expect( px.width - px.right ).toBeGreaterThan( 20 );
	} );

	test( 'turned, it stays in the block, its layers kept', async ( { page } ) => {
		const errors = await open( page, 'collage-overlap-rotate' );
		const state = await page.evaluate( () => {
			const block = document.querySelector( '.wp-swiper-collage' );
			const grid = block.querySelector( '.wp-swiper-collage__grid' );
			const box = block.getBoundingClientRect();
			return {
				angle: grid.wpAngle,
				layers: [].slice.call( grid.querySelectorAll( '.wp-swiper-collage__cell' ) ).map( ( cell ) => Number( cell.style.zIndex ) ),
				// Every photo's turned corners in the block.
				inside: [].slice.call( grid.querySelectorAll( '.wp-swiper-collage__cell' ) ).every( ( cell ) => {
					const r = cell.getBoundingClientRect();
					return r.left >= box.left - 2 && r.right <= box.right + 2 && r.top >= box.top - 2 && r.bottom <= box.bottom + 2;
				} ),
			};
		} );
		expect( state.angle ).toBe( -15 );
		expect( state.layers.slice().sort( ( a, b ) => a - b ) ).toEqual( [ 1, 2, 3, 4, 5, 6 ] );
		expect( state.inside ).toBe( true );
		expect( errors ).toEqual( [] );
	} );
} );
