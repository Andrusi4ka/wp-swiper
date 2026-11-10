// The edit screen's tests (admin*.spec.js): their own setup and helpers.
// admin-login.php logs in as the tests' own administrator, saves a slider
// with a case's settings (cases.php) and opens it; each test's slider is
// deleted after it (only its own: the others may still be open). Desktop
// only (the editor is not for a phone).
const { expect } = require( '@playwright/test' );
const path = require( 'path' );
const helper = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/admin-login.php';

// Called by each spec file with its `test`.
function setup( test ) {
	test.skip( ( { isMobile } ) => isMobile, 'the editor is for a desktop' );

	// The admin with the theme's and other plugins' scripts and the preview, a few
	// tests at once on a local server: more time than the site's pages need.
	test.describe.configure( { timeout: 90000 } );

	test.afterEach( async ( { page, request } ) => {
		const id = /[?&]post=(\d+)/.exec( page.url() );
		if ( id ) {
			await request.get( helper + '?delete=' + id[ 1 ] );
		}
	} );
}

// The edit screen of a new slider with this case's settings; errors kept.
async function edit( page, name ) {
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	page.on( 'console', ( message ) => {
		// A theme's or another plugin's failed request is not ours.
		if ( message.type() === 'error' && ! /Failed to load resource/.test( message.text() ) ) {
			errors.push( message.text() );
		}
	} );
	await page.setViewportSize( { width: 1440, height: 900 } );
	await page.goto( helper + '?case=' + name );
	await page.waitForSelector( '#wp-swiper-settings', { timeout: 60000 } );
	await shown( page );
	return errors;
}

// The preview's frame, once it shows a slider (or Art, a collage).
function frame( page ) {
	return page.frameLocator( '.wp-swiper-preview__frame' );
}

async function shown( page ) {
	await expect( frame( page ).locator( '.wp-swiper, .wp-swiper-art' ).first() ).toBeAttached( { timeout: 15000 } );
}

async function openPage( page, name ) {
	await page.click( '.wp-swiper-menu__item[data-page="' + name + '"]' );
	await expect( page.locator( '#wp-swiper-page-' + name ) ).toBeVisible();
}

module.exports = { helper, setup, edit, frame, shown, openPage };
