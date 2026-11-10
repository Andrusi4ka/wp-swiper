// The slider's edit screen (admin.js and its parts): smoke tests of what
// one does there, so a change to the admin scripts that breaks the screen
// shows. The preview column's: admin-preview.spec.js; setup and helpers:
// admin-kit.js.
const { test, expect } = require( '@playwright/test' );
const path = require( 'path' );
const { helper, setup, edit, frame, openPage } = require( './admin-kit' );

setup( test );

test( 'opens: the slides, the menu and the preview, no errors', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await expect( page.locator( '.wp-swiper-slide' ) ).toHaveCount( 8 );
	// admin.js and its parts, every part there (run in its order by admin.js).
	const loaded = await page.evaluate( ( dir ) => ( {
		files: [].map.call( document.querySelectorAll( 'script[src*="/' + dir + '/assets/js/admin"]' ), ( s ) => s.src.replace( /.*\/assets\/js\/|\?.*/g, '' ) ).sort(),
		parts: Object.keys( window.wpSwiperEditor.parts ).sort(),
	} ), path.basename( path.resolve( __dirname, '../..' ) ) );
	expect( loaded.files ).toEqual( [ 'admin-colors.js', 'admin-frame.js', 'admin-pages.js', 'admin-preview.js', 'admin-slide-info.js', 'admin-slides.js', 'admin.js' ] );
	expect( loaded.parts ).toEqual( [ 'colors', 'fields', 'focus', 'frame', 'head', 'pages', 'preview', 'slideFonts', 'slideInfo', 'slides', 'tail' ] );
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

test( 'a color: the eyedropper beside its value takes a color from the screen, the opacity kept', async ( { page } ) => {
	// Image shadow on, its color with opacity (0.5).
	const errors = await edit( page, 'fade-shadow' );
	await openPage( page, 'layout' );
	const field = page.locator( '[data-field="image_shadow_color"]' );
	await expect( field ).toBeVisible();
	const value = field.locator( '.wp-swiper-color-pick__value' );
	const alpha = ( await value.inputValue() ).replace( /^rgba\(\d+,\d+,\d+,([\d.]+)\)$/, '$1' );
	// The browser's own picker waits for a click on the screen: one that
	// gives a color at once instead (Chrome has the real one, so the button shows).
	await page.evaluate( () => {
		window.EyeDropper = class {
			open() {
				return Promise.resolve( { sRGBHex: '#12ab34' } );
			}
		};
	} );
	await field.locator( '.wp-swiper-color-pick__swatch' ).click();
	const app = page.locator( '.pcr-app.visible' );
	await expect( app ).toBeVisible();
	const drop = app.locator( '.wp-swiper-pcr__dropper' );
	await expect( drop ).toBeVisible();
	await expect( drop ).toHaveAttribute( 'aria-label', 'Pick a color from the screen' );
	// Beside the value, on its row.
	const [ a, b ] = [ await app.locator( '.pcr-result' ).boundingBox(), await drop.boundingBox() ];
	expect( Math.abs( a.y - b.y ) ).toBeLessThan( 4 );
	expect( b.x ).toBeGreaterThan( a.x );
	await drop.click();
	await expect( value ).toHaveValue( 'rgba(18,171,52,' + alpha + ')' );
	await expect( app.locator( '.pcr-result' ) ).toHaveValue( /12ab34/i );
	await page.keyboard.press( 'Escape' );
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

test( 'Drag with the mouse: in Movement after the loop, locked for a continuous scroll', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await openPage( page, 'controls' );
	const fields = await page.locator( '#wp-swiper-page-controls [data-block="movement"] .wp-swiper-field' ).evaluateAll( ( els ) => els.map( ( el ) => el.getAttribute( 'data-field' ) ) );
	expect( fields.indexOf( 'mouse_drag' ) ).toBe( fields.indexOf( 'loop' ) + 1 );
	const box = page.locator( '#wp-swiper-mouse_drag' );
	await expect( box ).toBeVisible();
	await expect( box ).toBeChecked();
	await expect( box ).toBeEnabled();
	expect( errors ).toEqual( [] );
} );

test( 'Drag with the mouse: a continuous scroll has none (locked off)', async ( { page } ) => {
	const errors = await edit( page, 'marquee' );
	await openPage( page, 'controls' );
	await expect( page.locator( '#wp-swiper-mouse_drag' ) ).toBeDisabled();
	await expect( page.locator( '#wp-swiper-mouse_drag' ) ).not.toBeChecked();
	expect( errors ).toEqual( [] );
} );

test( 'Settings: under Add slider, a role given Edit sliders may then add, kept when saved', async ( { page, request } ) => {
	// Logged in (and What's new seen) by opening a slider; that one deleted at once.
	const errors = await edit( page, 'slide' );
	const id = /[?&]post=(\d+)/.exec( page.url() )[ 1 ];
	await request.get( helper + '?delete=' + id );
	await page.goto( '/wp-admin/edit.php?post_type=wp_swiper&page=wp-swiper-settings' );

	// In the menu right after "Add slider".
	const items = await page.locator( '#menu-posts-wp_swiper .wp-submenu li a' ).allTextContents();
	expect( items.slice( 0, 3 ).map( ( text ) => text.trim() ) ).toEqual( [ 'All sliders', 'Add new slider', 'Settings' ] );

	// Administrators: always all, greyed.
	const admin = page.locator( 'tr[data-role="administrator"] input' );
	await expect( admin.first() ).toBeChecked();
	await expect( admin.first() ).toBeDisabled();
	// Add and clone, Delete, the WPBakery element: only with Edit sliders.
	const box = ( action ) => page.locator( 'tr[data-role="editor"] input[data-action="' + action + '"]' );
	await expect( box( 'edit' ) ).not.toBeChecked();
	await expect( box( 'create' ) ).toBeDisabled();
	// The WPBakery element too: no use without the sliders.
	await expect( box( 'wpbakery' ) ).toBeDisabled();
	await box( 'edit' ).check();
	await expect( box( 'wpbakery' ) ).toBeEnabled();
	await expect( box( 'create' ) ).toBeEnabled();
	await box( 'create' ).check();
	await Promise.all( [ page.waitForNavigation(), page.click( '#submit' ) ] );
	await expect( page.locator( '#setting-error-settings_updated' ) ).toBeVisible();
	await expect( box( 'edit' ) ).toBeChecked();
	await expect( box( 'create' ) ).toBeChecked();
	await expect( box( 'delete' ) ).not.toBeChecked();

	// Back as it was: Edit off takes Add and clone off too.
	await box( 'edit' ).uncheck();
	await expect( box( 'create' ) ).not.toBeChecked();
	await expect( box( 'create' ) ).toBeDisabled();
	await Promise.all( [ page.waitForNavigation(), page.click( '#submit' ) ] );
	await expect( box( 'edit' ) ).not.toBeChecked();
	await expect( box( 'create' ) ).not.toBeChecked();
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

test( 'Radius: a slider left of the number, per device, inheriting', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await openPage( page, 'layout' );
	const field = page.locator( '[data-field="border_radius"]' );
	const range = field.locator( '.wp-swiper-range' );
	const desktop = page.locator( '#wp-swiper-border_radius' );
	const tablet = page.locator( '#wp-swiper-border_radius_tablet' );
	// The radius under Rounded corners, once it is on.
	await page.locator( '#wp-swiper-border_rounded' ).check( { force: true } );
	await expect( field ).toBeVisible();
	// The slider left of the number.
	const rangeBox = await range.boundingBox();
	const numberBox = await desktop.boundingBox();
	expect( rangeBox.x + rangeBox.width ).toBeLessThanOrEqual( numberBox.x );
	// The slider moves the number; a number typed moves the slider.
	await range.fill( '30' );
	await expect( desktop ).toHaveValue( '30' );
	await desktop.fill( '42' );
	await expect( range ).toHaveValue( '42' );
	// Tablet without its own: the slider at the value it inherits; moved, a value of its own.
	await field.locator( '.wp-swiper-devices__btn[data-device="tablet"]' ).click();
	await expect( tablet ).toBeVisible();
	await expect( tablet ).toHaveValue( '' );
	await expect( range ).toHaveValue( '42' );
	await range.fill( '10' );
	await expect( tablet ).toHaveValue( '10' );
	await expect( desktop ).toHaveValue( '42' );
	// Back on desktop: its own value.
	await field.locator( '.wp-swiper-devices__btn[data-device="desktop"]' ).click();
	await expect( range ).toHaveValue( '42' );
	if ( process.env.WP_SWIPER_SHOTS ) {
		await field.screenshot( { path: process.env.WP_SWIPER_SHOTS + '/radius-slider.png' } );
	}
	expect( errors ).toEqual( [] );
} );

test( 'Make the active slide wider: its size from a slider, by halves', async ( { page } ) => {
	const errors = await edit( page, 'slide' );
	await openPage( page, 'layout' );
	await page.locator( '#wp-swiper-expand_active' ).check( { force: true } );
	const field = page.locator( '[data-field="active_width"]' );
	await expect( field ).toBeVisible();
	const range = field.locator( '.wp-swiper-range' );
	await range.fill( '55.5' );
	await expect( page.locator( '#wp-swiper-active_width' ) ).toHaveValue( '55.5' );
	if ( process.env.WP_SWIPER_SHOTS ) {
		await field.screenshot( { path: process.env.WP_SWIPER_SHOTS + '/active-width.png' } );
	}
	expect( errors ).toEqual( [] );
} );

test( 'Thumbnails: Gap to the slider and Inactive thumbnail opacity from sliders', async ( { page } ) => {
	const errors = await edit( page, 'thumbs' );
	await openPage( page, 'thumbs' );
	const gap = page.locator( '[data-field="thumbs_spacing"]' );
	const opacity = page.locator( '[data-field="thumbs_opacity"]' );
	await expect( gap.locator( '.wp-swiper-range' ) ).toBeVisible();
	await gap.locator( '.wp-swiper-range' ).fill( '24' );
	await expect( page.locator( '#wp-swiper-thumbs_spacing' ) ).toHaveValue( '24' );
	await opacity.locator( '.wp-swiper-range' ).fill( '55' );
	await expect( page.locator( '#wp-swiper-thumbs_opacity' ) ).toHaveValue( '55' );
	if ( process.env.WP_SWIPER_SHOTS ) {
		await page.locator( '#wp-swiper-page-thumbs' ).screenshot( { path: process.env.WP_SWIPER_SHOTS + '/thumbs-sliders.png' } );
	}
	expect( errors ).toEqual( [] );
} );

test( 'Collage Rotation: a slider from -45 to 45', async ( { page } ) => {
	const errors = await edit( page, 'collage-rotate' );
	await openPage( page, 'layout' );
	const field = page.locator( '[data-field="collage_rotate"]' );
	const range = field.locator( '.wp-swiper-range' );
	await expect( range ).toBeVisible();
	await expect( range ).toHaveValue( '30' );
	await range.fill( '-20' );
	await expect( page.locator( '#wp-swiper-collage_rotate' ) ).toHaveValue( '-20' );
	if ( process.env.WP_SWIPER_SHOTS ) {
		await field.screenshot( { path: process.env.WP_SWIPER_SHOTS + '/rotation.png' } );
	}
	expect( errors ).toEqual( [] );
} );

test( 'Art: Image size from a slider in Content in a window and in the Opening', async ( { page } ) => {
	const errors = await edit( page, 'art-ring-content' );
	await openPage( page, 'layout' );
	const content = page.locator( '[data-field="art_content_image_size"]' );
	await expect( content.locator( '.wp-swiper-range' ) ).toBeVisible();
	await content.locator( '.wp-swiper-range' ).fill( '70' );
	await expect( page.locator( '#wp-swiper-art_content_image_size' ) ).toHaveValue( '70' );
	await page.locator( '#wp-swiper-art_opening' ).check( { force: true } );
	const opening = page.locator( '[data-field="art_opening_image_size"]' );
	await expect( opening.locator( '.wp-swiper-range' ) ).toBeVisible();
	await opening.locator( '.wp-swiper-range' ).fill( '40' );
	await expect( page.locator( '#wp-swiper-art_opening_image_size' ) ).toHaveValue( '40' );
	if ( process.env.WP_SWIPER_SHOTS ) {
		await opening.screenshot( { path: process.env.WP_SWIPER_SHOTS + '/opening-size.png' } );
	}
	expect( errors ).toEqual( [] );
} );
