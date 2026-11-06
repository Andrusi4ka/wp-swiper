// The slider's edit screen (admin.js and its parts): smoke tests of what
// one does there, so a change to the admin scripts that breaks the screen
// shows. admin-login.php logs in as the tests' own administrator, saves a
// slider with a case's settings (cases.php) and opens it; each test's
// slider is deleted after it (only its own: the others may still be open). Desktop only (the editor is not for a phone).
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const helper = '/wp-content/plugins/' + path.basename( path.resolve( __dirname, '../..' ) ) + '/tests/browser/admin-login.php';

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

test( 'opens: the slides, the menu and the preview, no errors', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await expect( page.locator( '.wp-swiper-slide' ) ).toHaveCount( 8 );
	// admin.js and its parts, every part there (run in its order by admin.js).
	const loaded = await page.evaluate( ( dir ) => ( {
		files: [].map.call( document.querySelectorAll( 'script[src*="/' + dir + '/assets/js/admin"]' ), ( s ) => s.src.replace( /.*\/assets\/js\/|\?.*/g, '' ) ).sort(),
		parts: Object.keys( window.wpSwiperEditor.parts ).sort(),
	} ), path.basename( path.resolve( __dirname, '../..' ) ) );
	expect( loaded.files ).toEqual( [ 'admin-colors.js', 'admin-pages.js', 'admin-preview.js', 'admin-slide-info.js', 'admin-slides.js', 'admin.js' ] );
	expect( loaded.parts ).toEqual( [ 'colors', 'fields', 'focus', 'head', 'pages', 'preview', 'slideFonts', 'slideInfo', 'slides', 'tail' ] );
	// One settings page in view at a time.
	await openPage( page, 'layout' );
	await expect( page.locator( '#wp-swiper-page-controls' ) ).toBeHidden();
	await openPage( page, 'controls' );
	await expect( page.locator( '#wp-swiper-page-layout' ) ).toBeHidden();
	// What the effect can do, under the menu.
	await expect( page.locator( '.wp-swiper-notes' ) ).toBeVisible();
	expect( errors ).toEqual( [] );
} );

test( 'another effect: its fields and the preview drawn again', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await openPage( page, 'layout' );
	await expect( page.locator( '[data-field="collage_layout"]' ) ).toBeHidden();
	await page.selectOption( '#wp-swiper-effect', 'collage' );
	await expect( page.locator( '[data-field="collage_layout"]' ) ).toBeVisible();
	await expect( frame( page ).locator( '.wp-swiper-collage' ) ).toBeAttached( { timeout: 15000 } );
	// Art: its Style as icons; another Style drawn at once.
	await page.selectOption( '#wp-swiper-effect', 'art' );
	await expect( frame( page ).locator( '.wp-swiper-art--ring' ) ).toBeAttached( { timeout: 15000 } );
	await page.click( '[data-field="art_style"] .wp-swiper-tiles__tile[data-value="grid-circle"]' );
	await expect( frame( page ).locator( '.wp-swiper-art--grid-circle' ) ).toBeAttached( { timeout: 15000 } );
	expect( errors ).toEqual( [] );
} );

test( 'a color: Pickr opens from its swatch, the value kept', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await openPage( page, 'layout' );
	await page.check( '#wp-swiper-image_border' );
	const field = page.locator( '[data-field="image_border_color"]' );
	await expect( field ).toBeVisible();
	await field.locator( '.wp-swiper-color-pick__swatch' ).click();
	const app = page.locator( '.pcr-app.visible' );
	await expect( app ).toBeVisible();
	await app.locator( '.pcr-result' ).fill( '#ff0000' );
	await app.locator( '.pcr-result' ).press( 'Enter' );
	await page.keyboard.press( 'Escape' );
	await expect( field.locator( '.wp-swiper-color-pick__value' ) ).toHaveValue( /^#ff0000$/i );
	expect( errors ).toEqual( [] );
} );

test( 'the gradient: its editor once on, a point added on its bar', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await openPage( page, 'layout' );
	await page.check( '#wp-swiper-image_gradient_on' );
	const editor = page.locator( '[data-field="image_gradient"] .wp-swiper-gradient' );
	await expect( editor ).toBeVisible();
	const value = editor.locator( '.wp-swiper-gradient__value' );
	const before = ( await value.inputValue() ).split( ';' ).length;
	const bar = editor.locator( '.wp-swiper-gradient__bar' );
	const box = await bar.boundingBox();
	await page.mouse.click( box.x + box.width * 0.5, box.y + box.height / 2 );
	await expect.poll( async () => ( await value.inputValue() ).split( ';' ).length ).toBe( before + 1 );
	expect( errors ).toEqual( [] );
} );

test( 'undo and redo a setting', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await openPage( page, 'layout' );
	const height = page.locator( '#wp-swiper-height' );
	const was = await height.inputValue();
	await height.fill( '333' );
	await height.dispatchEvent( 'change' );
	const undo = page.locator( '.wp-swiper-preview__undo' );
	await expect( undo ).toBeEnabled();
	await undo.click();
	await expect( height ).toHaveValue( was );
	await page.click( '.wp-swiper-preview__redo' );
	await expect( height ).toHaveValue( '333' );
	expect( errors ).toEqual( [] );
} );

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

test( 'Slide info: a caption written, kept in the slide, its tab marked', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await page.locator( '.wp-swiper-slide__edit' ).first().click();
	const modal = page.locator( '.wp-swiper-modal' ).first();
	await expect( modal ).toBeVisible();
	// The Caption tab first; the code view of its editor.
	await expect( page.locator( '#wp-swiper-modal-tab-caption' ) ).toHaveAttribute( 'aria-selected', 'true' );
	await page.locator( '#wp-swiper-modal-panel-caption .wp-switch-editor.switch-html' ).click();
	await page.locator( '#wp-swiper-modal-panel-caption textarea' ).first().fill( '<p>Hello caption</p>' );
	await page.click( '.wp-swiper-modal__apply' );
	await expect( modal ).toBeHidden();
	await expect( page.locator( '.wp-swiper-slide' ).first().locator( 'textarea[name="wp_swiper_slides[caption][]"]' ) ).toHaveValue( /Hello caption/ );
	// The other tabs open too.
	await page.locator( '.wp-swiper-slide__edit' ).first().click();
	await page.click( '#wp-swiper-modal-tab-link' );
	await expect( page.locator( '#wp-swiper-modal-panel-link' ) ).toBeVisible();
	await page.click( '.wp-swiper-modal__cancel' );
	expect( errors ).toEqual( [] );
} );

test( 'a slide removed, the settings saved and read back', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await page.locator( '.wp-swiper-slide__remove' ).first().click();
	await expect( page.locator( '.wp-swiper-slide' ) ).toHaveCount( 7 );
	await page.selectOption( '#wp-swiper-effect', 'fade' );
	await openPage( page, 'layout' );
	await page.locator( '#wp-swiper-height' ).fill( '420' );
	await Promise.all( [ page.waitForNavigation(), page.click( '#publish' ) ] );
	await page.waitForSelector( '#wp-swiper-settings' );
	await expect( page.locator( '.wp-swiper-slide' ) ).toHaveCount( 7 );
	await expect( page.locator( '#wp-swiper-effect' ) ).toHaveValue( 'fade' );
	await expect( page.locator( '#wp-swiper-height' ) ).toHaveValue( '420' );
	expect( errors ).toEqual( [] );
} );

test( 'Art Grid shapes: its tools in the preview', async ( { page } ) => {
	const errors = await edit( page, 'art-grid-shapes' );
	await expect( frame( page ).locator( '.wp-swiper-art-tools__mode' ).first() ).toBeVisible( { timeout: 15000 } );
	expect( errors ).toEqual( [] );
} );

test( 'Text mask: the font list opens and filters', async ( { page } ) => {
	const errors = await edit( page, 'art-text-mask-name' );
	await openPage( page, 'layout' );
	// A click: all of them, in groups; typing part of a name: those with it.
	const input = page.locator( '[data-field="text_mask_font_name"] .wp-swiper-font__input' );
	const list = page.locator( '[data-field="text_mask_font_name"] .wp-swiper-font__list' );
	await input.click();
	await expect( list ).toBeVisible();
	const options = list.locator( '[role="option"]' );
	const all = await options.count();
	expect( all ).toBeGreaterThan( 0 );
	const name = ( await options.last().innerText() ).trim().split( /\s+/ )[ 0 ];
	await input.fill( '' );
	await input.pressSequentially( name.slice( 0, 3 ) );
	await expect( list ).toBeVisible();
	await expect( options.first() ).toContainText( name.slice( 0, 3 ), { ignoreCase: true } );
	await page.keyboard.press( 'Escape' );
	await expect( list ).toBeHidden();
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

test( 'Collage: Shuffle sets an order, Reset clears it', async ( { page } ) => {
	const errors = await edit( page, 'collage-grid' );
	await openPage( page, 'layout' );
	const order = page.locator( 'input[name="wp_swiper_settings[collage_order]"]' );
	await expect( order ).toHaveValue( '' );
	await page.click( '[data-field="collage_order"] .wp-swiper-shuffle' );
	await expect( order ).not.toHaveValue( '' );
	await page.click( '[data-field="collage_order"] .wp-swiper-order-reset' );
	await expect( order ).toHaveValue( '' );
	expect( errors ).toEqual( [] );
} );

test( 'a slide\'s image position: its window opens and closes', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await page.locator( '.wp-swiper-slide__position' ).first().click();
	const modal = page.locator( '#wp-swiper-position-modal' );
	await expect( modal ).toBeVisible();
	await modal.locator( '.wp-swiper-modal__close' ).click();
	await expect( modal ).toBeHidden();
	expect( errors ).toEqual( [] );
} );

test( 'Creative: its Style from icons, first in the Effect block, the preview drawn again', async ( { page } ) => {
	const errors = await edit( page, 'creative' );
	await openPage( page, 'layout' );
	const block = page.locator( '#wp-swiper-page-layout [data-field="creative_style"]' );
	await expect( block ).toBeVisible();
	await expect( block.locator( '.wp-swiper-tiles__tile' ) ).toHaveCount( 7 );
	await expect( block.locator( '.wp-swiper-tiles__tile[data-value="push"]' ) ).toHaveAttribute( 'aria-checked', 'true' );
	// Above Direction.
	const style = await block.boundingBox();
	const direction = await page.locator( '#wp-swiper-page-layout [data-field="direction"]' ).boundingBox();
	expect( style.y ).toBeLessThan( direction.y );
	if ( process.env.WP_SWIPER_SHOTS ) {
		await block.screenshot( { path: process.env.WP_SWIPER_SHOTS + '/creative-tiles.png' } );
	}
	await block.locator( '.wp-swiper-tiles__tile[data-value="carousel"]' ).click();
	await expect( page.locator( '#wp-swiper-creative_style' ) ).toHaveValue( 'carousel' );
	await expect( block.locator( '.wp-swiper-tiles__tile[data-value="carousel"]' ) ).toHaveAttribute( 'aria-checked', 'true' );
	// The Carousel's own settings show; the preview drawn with it at once.
	await expect( page.locator( '[data-field="creative_neighbours"]' ) ).toBeVisible();
	await expect( frame( page ).locator( '.wp-swiper--carousel' ) ).toBeAttached( { timeout: 15000 } );
	expect( errors ).toEqual( [] );
} );

test( 'Caption: a Custom gradient shows its editor instead of the color', async ( { page } ) => {
	const errors = await edit( page, 'slide-caption-gradient' );
	await openPage( page, 'caption' );
	await page.selectOption( '#wp-swiper-caption_bg_style', 'gradient' );
	await expect( page.locator( '[data-field="caption_bg_color"]' ) ).toBeVisible();
	await expect( page.locator( '[data-field="caption_bg_gradient"]' ) ).toBeHidden();
	await page.selectOption( '#wp-swiper-caption_bg_style', 'custom' );
	await expect( page.locator( '[data-field="caption_bg_color"]' ) ).toBeHidden();
	const editor = page.locator( '[data-field="caption_bg_gradient"] .wp-swiper-gradient' );
	await expect( editor ).toBeVisible();
	// Its editor works as the photos' Gradient's: a click adds a color.
	const value = editor.locator( '.wp-swiper-gradient__value' );
	const before = ( await value.inputValue() ).split( ';' ).length;
	const bar = await editor.locator( '.wp-swiper-gradient__bar' ).boundingBox();
	await page.mouse.click( bar.x + bar.width * 0.5, bar.y + bar.height / 2 );
	await expect.poll( async () => ( await value.inputValue() ).split( ';' ).length ).toBe( before + 1 );
	expect( errors ).toEqual( [] );
} );

test( 'Text mask: Strips off hides their settings, on shows them again', async ( { page } ) => {
	const errors = await edit( page, 'art-text-mask' );
	await openPage( page, 'layout' );
	const strips = [ 'text_mask_strip_width', 'text_mask_join', 'text_mask_strips', 'text_mask_strip_min', 'text_mask_strip_max', 'text_mask_rounded' ];
	await expect( page.locator( '[data-field="text_mask_strips_on"]' ) ).toBeVisible();
	for ( const name of strips ) {
		await expect( page.locator( '[data-field="' + name + '"]' ) ).toBeVisible();
	}
	await page.locator( '#wp-swiper-text_mask_strips_on' ).uncheck( { force: true } );
	for ( const name of strips ) {
		await expect( page.locator( '[data-field="' + name + '"]' ) ).toBeHidden();
	}
	await page.locator( '#wp-swiper-text_mask_strips_on' ).check( { force: true } );
	await expect( page.locator( '[data-field="text_mask_strip_width"]' ) ).toBeVisible();
	expect( errors ).toEqual( [] );
} );

test( 'Text mask: Letter alignment, for a row or a column, with strips or without', async ( { page } ) => {
	const errors = await edit( page, 'art-text-mask' );
	await openPage( page, 'layout' );
	const vertical = page.locator( '[data-field="text_mask_align_v"]' );
	const horizontal = page.locator( '[data-field="text_mask_align_h"]' );
	// With strips too. Top: top / center / bottom.
	await expect( vertical ).toBeVisible();
	await expect( horizontal ).toBeHidden();
	await page.locator( '#wp-swiper-text_mask_strips_on' ).uncheck( { force: true } );
	await expect( vertical ).toBeVisible();
	await expect( horizontal ).toBeHidden();
	// Left (its value "right"): left / center / right; the tile's name as its place.
	const tile = page.locator( '[data-field="text_mask_direction"] .wp-swiper-tiles__tile[data-value="right"]' );
	await expect( tile ).toHaveAttribute( 'title', 'Left' );
	await tile.click();
	await expect( horizontal ).toBeVisible();
	await expect( vertical ).toBeHidden();
	expect( errors ).toEqual( [] );
} );
