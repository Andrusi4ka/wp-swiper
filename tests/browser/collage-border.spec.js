// Collage, Image border (cases.php collage-border): the place's own border
// (collage.css), not a frame over the photo. A frame over it shared the
// place's rounded edge with the photo, and the photo showed through there,
// a thin line outside the frame. On the page's white the border is white
// too: past its inner edge, round the large corners, nothing but white.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const fixture = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

async function open( page, name ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( fixture + '?case=' + name );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 400 );
	return errors;
}

// The darkest channel (0–255) in the border and past it round each place's
// large corners (top right, bottom left), from a shot of the page.
async function darkest( page ) {
	const cells = await page.evaluate( () => [].map.call( document.querySelectorAll( '.wp-swiper-collage__cell' ), ( cell ) => {
		const r = cell.getBoundingClientRect();
		const cs = getComputedStyle( cell );
		return {
			x: r.left + window.scrollX,
			y: r.top + window.scrollY,
			w: r.width,
			h: r.height,
			border: parseFloat( cs.borderTopWidth ),
			tr: parseFloat( cs.borderTopRightRadius ),
			bl: parseFloat( cs.borderBottomLeftRadius ),
		};
	} ) );
	const png = ( await page.screenshot( { fullPage: true } ) ).toString( 'base64' );
	return page.evaluate( async ( { png, cells } ) => {
		const img = new Image();
		img.src = 'data:image/png;base64,' + png;
		await img.decode();
		const canvas = document.createElement( 'canvas' );
		canvas.width = img.width;
		canvas.height = img.height;
		const ctx = canvas.getContext( '2d' );
		ctx.drawImage( img, 0, 0 );
		const dpr = img.width / document.documentElement.scrollWidth;
		let min = 255;
		cells.forEach( ( c ) => {
			// Each corner: its arcs' middle, its radius, the square it is drawn in.
			[
				{ cx: c.x + c.w - c.tr, cy: c.y + c.tr, r: c.tr, x0: c.x + c.w - c.tr, y0: c.y },
				{ cx: c.x + c.bl, cy: c.y + c.h - c.bl, r: c.bl, x0: c.x, y0: c.y + c.h - c.bl },
			].forEach( ( k ) => {
				const data = ctx.getImageData( Math.floor( k.x0 * dpr ), Math.floor( k.y0 * dpr ), Math.ceil( k.r * dpr ), Math.ceil( k.r * dpr ) ).data;
				const side = Math.ceil( k.r * dpr );
				for ( let i = 0; i < side * side; i++ ) {
					const x = ( Math.floor( k.x0 * dpr ) + ( i % side ) + 0.5 ) / dpr;
					const y = ( Math.floor( k.y0 * dpr ) + Math.floor( i / side ) + 0.5 ) / dpr;
					// Past the border's inner edge (and its smoothing).
					if ( Math.hypot( x - k.cx, y - k.cy ) > k.r - c.border + 1.5 ) {
						min = Math.min( min, data[ i * 4 ], data[ i * 4 + 1 ], data[ i * 4 + 2 ] );
					}
				}
			} );
		} );
		return { min, cells: cells.length, border: cells[ 0 ] && cells[ 0 ].border };
	}, { png, cells } );
}

test( 'no photo past the border round large corners', async ( { page } ) => {
	const errors = await open( page, 'collage-border' );
	const found = await darkest( page );
	expect( found.cells ).toBe( 2 );
	expect( found.border ).toBe( 10 );
	expect( found.min ).toBeGreaterThanOrEqual( 240 );
	expect( errors ).toEqual( [] );
} );
