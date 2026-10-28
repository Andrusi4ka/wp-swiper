// Every case of cases.php: it shows without errors, loads the files it
// needs, lays its photos out as before (the snapshots) and its controls
// and lightbox work.
const { test, expect } = require( '@playwright/test' );

const FIXTURE = '/wp-content/plugins/wp-swiper/tests/browser/fixture.php';

const CASES = [
	'slide', 'slide-loop', 'slide-captions', 'expand', 'auto-width', 'marquee',
	'expand-shadow', 'slide-loop-shadow', 'marquee-shadow',
	'fade', 'fade-shadow', 'fade-shadow-contain', 'cube', 'coverflow', 'flip', 'cards', 'creative', 'creative-carousel',
	'thumbs', 'video', 'background',
	'collage-scatter', 'collage-freeform', 'collage-grid', 'collage-masonry',
	'collage-justified', 'collage-random', 'collage-feature',
	'art-ring', 'art-ring-text', 'art-grid-circle',
];

// Ones with arrows that move the slides (a marquee runs on its own).
const NAVIGATES = [
	'slide', 'slide-loop', 'slide-captions', 'expand', 'auto-width', 'expand-shadow', 'slide-loop-shadow',
	'fade', 'fade-shadow', 'fade-shadow-contain', 'cube', 'coverflow', 'flip', 'cards', 'creative', 'creative-carousel', 'thumbs', 'video',
];

// Ones whose photo opens the lightbox on a click.
const LIGHTBOX = NAVIGATES.filter( ( name ) => 'video' !== name ).concat(
	CASES.filter( ( name ) => /^(collage|art)-/.test( name ) )
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

		if ( /-shadow$/.test( name ) && ! /^fade/.test( name ) ) {
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
					? page.locator( '.wp-swiper-art__piece:visible img' ).first()
					: /^collage-/.test( name )
						? page.locator( '.wp-swiper-collage__grid img:visible' ).first()
						: page.locator( '.swiper-slide-active img' ).first();
				await photo.click();
				const lightbox = page.locator( '.wp-swiper-lightbox' );
				await expect( lightbox ).toBeVisible();
				await page.keyboard.press( 'Escape' );
				await expect( lightbox ).toBeHidden();
				expect( errors ).toEqual( [] );
			} );
		}
	} );
}
