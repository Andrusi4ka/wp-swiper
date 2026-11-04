// Every case of cases.php: it shows without errors, loads the files it
// needs, lays its photos out as before (the snapshots) and its controls
// and lightbox work.
const { test, expect } = require( '@playwright/test' );

// The plugin's own folder name (wp-swiper, wp-swiper-main…).
const FIXTURE = '/wp-content/plugins/' + require( 'path' ).basename( require( 'path' ).resolve( __dirname, '../..' ) ) + '/tests/browser/fixture.php';

const CASES = [
	'slide', 'slide-loop', 'slide-captions', 'expand', 'auto-width', 'marquee',
	'slide-gradient', 'collage-gradient', 'art-ring-gradient', 'collage-rotate', 'collage-rotate-fit', 'collage-rotate-scatter', 'collage-rotate-free',
	'collage-rotate-scatter-auto', 'collage-rotate-free-auto',
	'expand-shadow', 'slide-loop-shadow', 'marquee-shadow',
	'fade', 'fade-shadow', 'fade-shadow-contain', 'cube', 'coverflow', 'flip', 'cards', 'creative', 'creative-carousel',
	'thumbs', 'video', 'background',
	'collage-scatter', 'collage-freeform', 'collage-grid', 'collage-masonry',
	'collage-justified', 'collage-random', 'collage-feature',
	'collage-content', 'collage-content-scatter', 'collage-content-feature', 'collage-content-shortcode',
	'art-ring', 'art-ring-text', 'art-grid-circle', 'art-grid-circle-text', 'art-grid-square', 'art-grid-square-text',
	'art-grid-shapes', 'art-grid-shapes-one', 'art-shape-content', 'art-grid-shapes-background', 'art-ring-background', 'collage-background-shadow', 'art-grid-background-shadow',
	'art-text-mask', 'art-text-mask-sans', 'art-text-mask-each', 'art-text-mask-right', 'art-text-mask-left', 'art-text-mask-name', 'art-text-mask-auto', 'art-text-mask-stretch', 'art-text-mask-join', 'art-text-mask-rounded', 'art-text-mask-gradient',
];

// Ones with arrows that move the slides (a marquee runs on its own).
const NAVIGATES = [
	'slide', 'slide-loop', 'slide-captions', 'expand', 'auto-width', 'expand-shadow', 'slide-loop-shadow', 'slide-gradient',
	'fade', 'fade-shadow', 'fade-shadow-contain', 'cube', 'coverflow', 'flip', 'cards', 'creative', 'creative-carousel', 'thumbs', 'video',
];

// Ones whose photo opens the lightbox on a click.
const LIGHTBOX = NAVIGATES.filter( ( name ) => 'video' !== name ).concat(
	// A background takes no clicks: no lightbox.
	CASES.filter( ( name ) => /^(collage|art)-/.test( name ) && ! /background/.test( name ) )
);

/**
 * Opens a case and waits until it has settled: images loaded, layout done.
 * Collects the page's errors in `errors`.
 */
async function open( page, name ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	page.on( 'console', ( message ) => {
		if ( 'error' === message.type() ) {
			errors.push( message.text() );
		}
	} );
	await page.goto( FIXTURE + '?case=' + name );
	await page.waitForLoadState( 'networkidle' );
	// The images that load at once (lazy ones out of view never do), for 5 s at most.
	await page.evaluate( () =>
		Promise.race( [
			Promise.all(
				[].slice.call( document.images )
					.filter( ( image ) => 'lazy' !== image.loading )
					.map( ( image ) =>
						image.complete ? null : new Promise( ( done ) => {
							image.addEventListener( 'load', done );
							image.addEventListener( 'error', done );
						} )
					)
			),
			new Promise( ( done ) => setTimeout( done, 5000 ) ),
		] )
	);
	await page.waitForTimeout( 400 );
	return errors;
}

/**
 * The slides on screen outside the slides' area, in the room kept for the
 * image shadow, that are not hidden: none should be. A moving strip (the
 * marquee) is checked for the mark (slider.js), its fading may be under way.
 */
function inRoom( page, moving ) {
	return page.evaluate( ( moving ) => {
		const el = document.querySelector( '.swiper-initialized' );
		const box = el.getBoundingClientRect();
		const style = getComputedStyle( el );
		const start = box.left + parseFloat( style.paddingLeft );
		const end = box.right - parseFloat( style.paddingRight );
		return el.swiper.slides.filter( ( slide ) => {
			const rect = slide.getBoundingClientRect();
			const out = rect.right <= start + 0.5 || rect.left >= end - 0.5;
			const onScreen = rect.right > box.left && rect.left < box.right;
			const shown = moving ? ! slide.classList.contains( 'wp-swiper-slide--outside' ) : Number( getComputedStyle( slide ).opacity ) > 0.01;
			return out && onScreen && shown;
		} ).length;
	}, moving );
}

/**
 * Where the slider's photos are: the boxes of its shown images and videos,
 * from the slider's top left corner, in whole pixels. Only their sizes for
 * a marquee (it keeps moving).
 */
function layout( page, name ) {
	return page.evaluate( ( moving ) => {
		const root = document.querySelector( '.fixture > *' );
		const base = root.getBoundingClientRect();
		const boxes = [].slice.call( root.querySelectorAll( 'img, video' ) )
			.map( ( media ) => media.getBoundingClientRect() )
			.filter( ( box ) => box.width > 1 && box.height > 1 )
			.map( ( box ) => ( moving ? [ box.width, box.height ] : [ box.left - base.left, box.top - base.top, box.width, box.height ] ).map( Math.round ).join( ' ' ) );
		if ( moving ) {
			boxes.sort();
		}
		return [ 'root ' + Math.round( base.width ) + 'x' + Math.round( base.height ) ].concat( boxes ).join( '\n' ) + '\n';
	}, /^marquee/.test( name ) );
}

for ( const name of CASES ) {
	test.describe( name, () => {
		test( 'shows without errors, as before', async ( { page } ) => {
			const errors = await open( page, name );
			expect( errors ).toEqual( [] );

			const loaded = await page.evaluate( () => window.fixtureLoaded );
			// Soft: the layout below is still compared when the files differ.
			expect.soft( JSON.stringify( loaded, null, '\t' ) + '\n' ).toMatchSnapshot( name + '.files.json' );

			if ( ! /^(collage|art)-/.test( name ) ) {
				await expect( page.locator( '.swiper-initialized' ).first() ).toBeAttached();
			}
			expect( await layout( page, name ) ).toMatchSnapshot( name + '.layout.txt' );
		} );

		if ( NAVIGATES.includes( name ) ) {
			test( 'the arrow shows the next slide', async ( { page } ) => {
				await open( page, name );
				// The slide in front: Swiper's, or the wider active one's (frontend.js CURRENT_CLASS).
				const index = () => page.evaluate( () => {
					const swiper = document.querySelector( '.swiper-initialized' ).swiper;
					const current = swiper.el.querySelector( '.wp-swiper-slide-current' );
					return current ? current.getAttribute( 'data-swiper-slide-index' ) + '/' + swiper.slides.indexOf( current ) : swiper.realIndex;
				} );
				const before = await index();
				await page.locator( '.swiper-button-next' ).first().click();
				await expect.poll( index ).not.toBe( before );
			} );
		}

		// Sliders only (Collage and Art have no neighbour slides).
		if ( /-shadow$/.test( name ) && ! /^(fade|collage|art)/.test( name ) ) {
			test( 'no neighbour shows in the room for the shadow', async ( { page } ) => {
				await open( page, name );
				expect( await inRoom( page, /^marquee/.test( name ) ) ).toBe( 0 );
				if ( NAVIGATES.includes( name ) ) {
					for ( let i = 0; i < 3; i++ ) {
						await page.locator( '.swiper-button-next' ).first().click();
						await page.waitForTimeout( 900 );
						expect( await inRoom( page, /^marquee/.test( name ) ) ).toBe( 0 );
					}
				} else {
					await page.waitForTimeout( 1500 );
					expect( await inRoom( page, /^marquee/.test( name ) ) ).toBe( 0 );
				}
			} );
		}

		if ( LIGHTBOX.includes( name ) ) {
			test( 'a photo opens the lightbox', async ( { page } ) => {
				const errors = await open( page, name );
				const photo = /^art-/.test( name )
					// A photo's window, not Content in a window's (no lightbox).
					? page.locator( '.wp-swiper-art__piece:not(.wp-swiper-art__piece--content):visible' ).first()
					: /^collage-/.test( name )
						// A photo's place, not Content in a place's (no lightbox).
						? page.locator( '.wp-swiper-collage__cell:not(.wp-swiper-collage__cell--content) img:visible' ).first()
						: page.locator( '.swiper-slide-active img' ).first();
				if ( /^art-/.test( name ) ) {
					// Art: the first piece near its left edge, its own there (its
					// middle may be another piece's: a shape round a hole).
					const box = await photo.boundingBox();
					// Text mask: the middle of its letter's strip (its clip path's
					// rect or path; beside a letter's stem there may be no window).
					const strip = /text-mask/.test( name ) ? await photo.evaluate( ( piece ) => {
						const bounds = document.querySelector( piece.style.clipPath.replace( /^url\("?|"?\)$/g, '' ) ).querySelector( 'rect, path' ).getBBox();
						return { x: bounds.x + bounds.width / 2, y: bounds.y + bounds.height / 2 };
					} ) : null;
					await photo.click( { position: strip || { x: 12, y: box.height / 2 } } );
				} else if ( /^collage-rotate/.test( name ) ) {
					// Turned: the first place whose middle is in the block (Fill
					// cuts off the places at its edges), clicked there.
					const spot = await page.evaluate( () => {
						const block = document.querySelector( '.wp-swiper-collage' ).getBoundingClientRect();
						for ( const cell of document.querySelectorAll( '.wp-swiper-collage__cell' ) ) {
							const r = cell.getBoundingClientRect();
							const x = r.left + r.width / 2;
							const y = r.top + r.height / 2;
							if ( x > block.left + 10 && x < block.right - 10 && y > block.top + 10 && y < block.bottom - 10 ) {
								return { x, y };
							}
						}
						return null;
					} );
					expect( spot ).not.toBeNull();
					await page.mouse.click( spot.x, spot.y );
				} else {
					await photo.click();
				}
				const lightbox = page.locator( '.wp-swiper-lightbox' );
				await expect( lightbox ).toBeVisible();
				await page.keyboard.press( 'Escape' );
				await expect( lightbox ).toBeHidden();
				expect( errors ).toEqual( [] );
			} );
		}
	} );
}
