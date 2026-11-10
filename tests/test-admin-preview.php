<?php
/**
 * WP_Swiper_Preview and the preview column of the edit screen: its bar
 * (devices, turned, a screen size of one's own, dark screen, Developer
 * mode), the frame's page and its checks, its files. (Moved from
 * test-admin.php as it is, 2026-11-10.)
 */

class Test_WP_Swiper_Admin_Preview extends WP_Swiper_TestCase {

	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	private function as_role( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	public function test_preview_has_the_developer_mode_button_right_of_its_title() {
		$this->as_role( 'administrator' );

		ob_start();
		WP_Swiper_Preview::render_panel( get_post( $this->slider() ) );
		$html = ob_get_clean();

		$title  = strpos( $html, 'wp-swiper-preview__title' );
		$button = strpos( $html, '<button type="button" class="button wp-swiper-preview__dev" aria-pressed="false"' );
		$this->assertNotFalse( $button );
		$this->assertGreaterThan( $title, $button );
		// An icon only (WP Banner's "</>", inline, in its button's color), named for screen readers and on hover.
		$this->assertStringContainsString( 'aria-label="Developer mode">', $html );
		$this->assertSame( 1, preg_match( '#<svg class="wp-swiper-preview__dev-icon" aria-hidden="true" focusable="false"[^>]*viewBox="0 0 1000 1000">#', $html ) );
		$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/developer-mode.svg' );
		$this->assertDoesNotMatchRegularExpression( '~<script|<foreignObject|<style|class=|\son\w+=|href=~i', $svg );
		$this->assertStringContainsString( 'currentColor', $svg );
		// Before the device switch and Update.
		$this->assertLessThan( strpos( $html, 'wp-swiper-preview__update' ), $button );
		$this->assertLessThan( strpos( $html, 'wp-swiper-preview__devices' ), $button );
		// The collage's hint, hidden until admin.js sees that effect.
		$this->assertStringContainsString( '<p class="description wp-swiper-preview__hint" data-effect="collage" hidden>', $html );
		$this->assertStringContainsString( '<p class="description wp-swiper-preview__hint" data-effect="mosaic" hidden>', $html );
		// Undo / redo: shown for every effect (the settings' changes too),
		// greyed with nothing to go back to, before the device switch.
		$this->assertStringContainsString( '<span class="wp-swiper-preview__history" role="group" aria-label="Undo and redo changes">', $html );
		$this->assertSame( 1, preg_match( '#<button type="button" class="button wp-swiper-preview__undo" disabled[^>]*aria-label="Undo">#', $html ) );
		$this->assertSame( 1, preg_match( '#<button type="button" class="button wp-swiper-preview__redo" disabled[^>]*aria-label="Redo">#', $html ) );
		$this->assertLessThan( strpos( $html, 'wp-swiper-preview__devices' ), strpos( $html, 'wp-swiper-preview__history' ) );
		$this->assertGreaterThan( $button, strpos( $html, 'wp-swiper-preview__history' ) );
		// Scattered: resizing by the edges, and moving a photo to a dashed frame.
		$this->assertSame( 3, substr_count( $html, '<p class="description wp-swiper-preview__hint" data-effect="scatter" hidden>' ) );
		$this->assertStringContainsString( 'take its place out of the collage on this device; the slide stays.', $html );
		$this->assertStringContainsString( 'Drop a photo on a dashed frame to move it to the end of that arm.', $html );
		// Freeform: photos moved anywhere, resized by their corners and edges, a place taken out by its ×.
		$this->assertSame( 3, substr_count( $html, '<p class="description wp-swiper-preview__hint" data-effect="freeform" hidden>' ) );
		$this->assertStringContainsString( 'Drag a corner or an edge of a photo to resize it.', $html );
	}

	public function test_preview_tablet_and_mobile_in_their_devices() {
		$this->assertSame( array( 'desktop', 'tablet', 'mobile' ), array_keys( WP_Swiper_Preview::DEVICES ) );
		foreach ( WP_Swiper_Preview::DEVICES as $device => $drawing ) {
			$file = WP_SWIPER_DIR . $drawing['src'];
			$this->assertFileExists( $file, $device );
			$svg = file_get_contents( $file );
			// The drawing's size is its viewBox; no script, nothing loaded from elsewhere.
			$this->assertStringContainsString( 'viewBox="0 0 ' . $drawing['width'] . ' ' . $drawing['height'] . '"', $svg, $device );
			$this->assertStringNotContainsString( '<script', $svg, $device );
			$this->assertDoesNotMatchRegularExpression( '#href="https?:#', $svg, $device );
			// Its screen, inside it, as the device's frame (SIZES): the same proportions.
			list( $x, $y, $w, $h ) = $drawing['screen'];
			$this->assertLessThanOrEqual( $drawing['width'], $x + $w, $device );
			$this->assertLessThanOrEqual( $drawing['height'], $y + $h, $device );
			$this->assertEqualsWithDelta( WP_Swiper_Preview::SIZES[ $device ][0] / WP_Swiper_Preview::SIZES[ $device ][1], $w / $h, 0.002, $device );
		}
		// The tablet's frame as its screen (not iPad Air's 1180): still a tablet's width.
		$this->assertSame( array( 820, 1101 ), WP_Swiper_Preview::SIZES['tablet'] );

		// The panel: each drawing hidden until its device (admin.js), their sizes for it (no file).
		ob_start();
		WP_Swiper_Preview::render_panel( get_post( $this->slider( $this->slides( 1 ) ) ) );
		$html = ob_get_clean();
		foreach ( WP_Swiper_Preview::DEVICES as $device => $drawing ) {
			$this->assertStringContainsString( '<img class="wp-swiper-preview__device" data-device="' . $device . '" src="' . esc_url( WP_SWIPER_URL . $drawing['src'] ) . '" alt="" hidden>', $html );
		}
		// The iMac: a desktop's width, its screen's square corners.
		$this->assertSame( array( 1280, 721 ), WP_Swiper_Preview::SIZES['desktop'] );
		$this->assertGreaterThan( 1024, WP_Swiper_Preview::SIZES['desktop'][0] );
		$this->assertSame( 1, preg_match( '#data-devices="([^"]+)"#', $html, $match ) );
		$data = json_decode( html_entity_decode( $match[1] ), true );
		$this->assertSame( array( 'desktop', 'tablet', 'mobile' ), array_keys( $data ) );
		$this->assertSame( array( 'width', 'height', 'screen', 'radius' ), array_keys( $data['mobile'] ) );
		$this->assertSame( 17.15, $data['tablet']['radius'] );

		// In a device, its thin scroll bar (the frame's own styles, tablet and mobile widths).
		$source = file_get_contents( WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php' );
		$this->assertMatchesRegularExpression( '#@media \(max-width: 1024px\) \{\s*html::-webkit-scrollbar \{ width: 4px; height: 4px; \}#', $source );
		$this->assertStringContainsString( 'scrollbar-width: thin;', $source );
	}

	public function test_preview_labels_the_background_mode() {
		$slider = '<div class="wp-swiper">slider</div>';

		// On: the block with the label, then the slider; no example content.
		$html = WP_Swiper_Preview::background_block( $slider, $this->settings( array( 'background' => true, 'background_class' => 'hero' ) ) );
		$this->assertSame( '<div class="wp-swiper-preview-block hero"><span class="wp-swiper-preview-block__mode">Background mode</span>' . $slider . '</div>', $html );
		// In the top right corner: the left one has Art's Shapes / Photos buttons.
		$this->assertStringContainsString( '.wp-swiper-preview-block__mode { position: absolute; top: 12px; right: 12px;', file_get_contents( WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php' ) );
		$this->assertStringNotContainsString( 'wp-swiper-preview-block__content', $html );

		// Off, or an effect that cannot be one: the slider alone, no label.
		$this->assertSame( $slider, WP_Swiper_Preview::background_block( $slider, $this->settings() ) );
		$this->assertSame( $slider, WP_Swiper_Preview::background_block( $slider, $this->settings( array( 'effect' => 'cube', 'background' => true ) ) ) );

		// Never on a page.
		$this->assertStringNotContainsString( 'Background mode', $this->render( array( 'background' => true, 'background_class' => 'hero' ) ) );
	}

	public function test_preview_background_block_fills_the_screen() {
		// The preview's own styles: no gaps around the block, as tall as the device's screen (no shapes).
		$source = file_get_contents( WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php' );
		$this->assertStringContainsString( '.wp-swiper-preview-page--background { padding: 0; }', $source );
		$this->assertStringContainsString( '.wp-swiper-preview-page--background .wp-swiper-preview-block { height: 100vh; }', $source );
		$this->assertStringContainsString( 'WP_Swiper_Settings::is_background( $settings ) ? \' wp-swiper-preview-page--background\'', $source );
		$this->assertStringNotContainsString( 'aspect-ratio', $source );
		$this->assertStringNotContainsString( 'wp-swiper-preview-block__content', $source );
	}

	public function test_preview_turns_tablet_and_mobile_sideways() {
		// The device a screen this wide takes, as the plugin's breakpoints.
		$this->assertSame( 'mobile', WP_Swiper_Preview::device_at( 390 ) );
		$this->assertSame( 'mobile', WP_Swiper_Preview::device_at( 767 ) );
		$this->assertSame( 'tablet', WP_Swiper_Preview::device_at( 768 ) );
		$this->assertSame( 'tablet', WP_Swiper_Preview::device_at( 1024 ) );
		$this->assertSame( 'desktop', WP_Swiper_Preview::device_at( 1025 ) );
		// Turned: the phone (844px wide) a tablet, the tablet (1101px) a desktop.
		$notes = WP_Swiper_Preview::turned_notes();
		$this->assertSame( array( 'tablet', 'mobile' ), array_keys( $notes ) );
		$this->assertSame( 'Turned sideways the screen is 844px wide: the slider takes the Tablet settings, as it does on the site.', $notes['mobile'] );
		$this->assertSame( 'Turned sideways the screen is 1101px wide: the slider takes the Desktop settings, as it does on the site.', $notes['tablet'] );

		$this->as_role( 'administrator' );
		ob_start();
		WP_Swiper_Preview::render_panel( get_post( $this->slider() ) );
		$html = ob_get_clean();
		// The button between the title and Developer mode, always there (the bar
		// does not move), greyed until admin.js sees Tablet or Mobile; its icon inline.
		$button = strpos( $html, '<button type="button" class="button wp-swiper-preview__rotate" aria-pressed="false" title="Turn the tablet or phone sideways" aria-label="Turn the tablet or phone sideways" disabled>' );
		$this->assertNotFalse( $button );
		$this->assertGreaterThan( strpos( $html, 'wp-swiper-preview__title' ), $button );
		$this->assertLessThan( strpos( $html, 'wp-swiper-preview__dev"' ), $button );
		$this->assertSame( 1, substr_count( $html, 'wp-swiper-preview__rotate"' ) );
		$this->assertSame( 1, preg_match( '#<svg class="wp-swiper-preview__rotate-icon" aria-hidden="true" focusable="false"[^>]*viewBox="0 0 417.11 311.33">#', $html ) );
		// The notes, hidden at first, one per device.
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$this->assertSame( 1, substr_count( $html, '<p class="description wp-swiper-preview__hint wp-swiper-preview__turned" data-device="' . $device . '" hidden>' ), $device );
		}
		$this->assertStringContainsString( esc_html( $notes['mobile'] ), $html );
		// The icon: shapes in its button's color only (nothing that runs or loads).
		$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/rotate.svg' );
		$this->assertDoesNotMatchRegularExpression( '~<script|<foreignObject|<style|\son\w+=|href=|#333~i', $svg );
		$this->assertStringContainsString( 'currentColor', $svg );
	}

	public function test_preview_screen_size_of_ones_own() {
		$this->as_role( 'administrator' );
		ob_start();
		WP_Swiper_Preview::render_panel( get_post( $this->slider() ) );
		$html = ob_get_clean();
		// After Dark screen, before Developer mode: width × height, as WP Banner's.
		$group = strpos( $html, '<span class="wp-swiper-preview__size" role="group" aria-labelledby="wp-swiper-preview-size-label">' );
		$this->assertNotFalse( $group );
		$this->assertGreaterThan( strpos( $html, 'wp-swiper-preview__screen"' ), $group );
		$this->assertLessThan( strpos( $html, 'wp-swiper-preview__dev"' ), $group );
		$this->assertStringContainsString( '<input type="number" class="wp-swiper-preview__size-w" min="240" max="3840" step="1" aria-label="Screen width (px)">', $html );
		$this->assertStringContainsString( '<input type="number" class="wp-swiper-preview__size-h" min="240" max="2160" step="1" aria-label="Screen height (px)">', $html );
		// No names: never sent with the slider's settings.
		$this->assertDoesNotMatchRegularExpression( '~wp-swiper-preview__size-[wh]"[^>]*\sname=~', $html );
		$this->assertStringContainsString( 'data-screen-limits="' . esc_attr( wp_json_encode( WP_Swiper_Preview::SCREEN_LIMITS ) ) . '"', $html );
		// Its note (which device's settings the width takes), hidden at first.
		$this->assertSame( 1, substr_count( $html, '<p class="description wp-swiper-preview__hint wp-swiper-preview__size-note" hidden>' ) );
		// Applied with Update only; kept in this browser per device.
		$js = $this->admin_js();
		$this->assertStringContainsString( "var SCREEN_KEY = 'wpSwiperScreens';", $js );
		$this->assertStringContainsString( "// The screen sizes typed apply now (the frame takes its size).\n\t\t\tapplyScreens();", $js );
	}

	public function test_preview_screen_light_or_dark() {
		$this->as_role( 'administrator' );
		ob_start();
		WP_Swiper_Preview::render_panel( get_post( $this->slider() ) );
		$html = ob_get_clean();
		// Right after Rotate, before Developer mode; the user's icon inline, in its button's color.
		$button = strpos( $html, '<button type="button" class="button wp-swiper-preview__screen" aria-pressed="false" title="Dark screen" aria-label="Dark screen">' );
		$this->assertNotFalse( $button );
		$this->assertGreaterThan( strpos( $html, 'wp-swiper-preview__rotate"' ), $button );
		$this->assertLessThan( strpos( $html, 'wp-swiper-preview__dev"' ), $button );
		$this->assertSame( 1, preg_match( '#<svg class="wp-swiper-preview__screen-icon" aria-hidden="true" focusable="false"[^>]*viewBox="0 0 1000 1000">#', $html ) );
		$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/light-dark.svg' );
		$this->assertDoesNotMatchRegularExpression( '~<script|<foreignObject|<style|class=|\son\w+=|href=|#333~i', $svg );
		$this->assertStringContainsString( 'currentColor', $svg );
		$this->assertFileDoesNotExist( WP_SWIPER_DIR . 'light-dark.svg' );

		// The frame's page: dark by its class, set before it is drawn from the
		// same key admin.js keeps; admin.js turns it at once.
		$source = file_get_contents( WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php' );
		$this->assertStringContainsString( 'html.wp-swiper-preview-dark, html.wp-swiper-preview-dark body { background: #121212; color: #e6e6e6; }', $source );
		$this->assertStringContainsString( "window.localStorage.getItem( 'wpSwiperDark' ) === '1'", $source );
		$js = $this->admin_js();
		$this->assertStringContainsString( "var DARK_KEY = 'wpSwiperDark';", $js );
		$this->assertStringContainsString( "doc.documentElement.classList.toggle( 'wp-swiper-preview-dark', darkOn );", $js );
	}

	public function test_preview_without_slides_shows_the_no_image_picture_in_the_middle() {
		$html = WP_Swiper_Preview::empty_screen();
		$this->assertFileExists( WP_SWIPER_DIR . 'assets/images/no-image.svg' );
		$this->assertSame( 1, preg_match( '#^<div class="wp-swiper-preview-empty"><img src="[^"]*/assets/images/no-image\.svg\?ver=[^"]+" alt="No images yet" width="256" height="256"></div>$#', $html ), $html );
		// Its place and size (the frame's styles), and the frame uses it with no slides only.
		$source = file_get_contents( WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php' );
		$this->assertStringContainsString( '.wp-swiper-preview-empty { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; }', $source );
		$this->assertStringContainsString( '.wp-swiper-preview-empty img { display: block; width: min(160px, 32vw); height: auto; }', $source );
		$this->assertSame( 1, preg_match( '#if \( \$slides \) \{\s*\$slider = self::background_block\( WP_Swiper_Render::render_slider\( \$post_id, \$slides, \$settings \), \$settings \);\s*\} else \{\s*\$slider = self::empty_screen\(\);#', $source ) );
		// The SVG: shapes only (nothing that runs or loads).
		$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/no-image.svg' );
		$this->assertDoesNotMatchRegularExpression( '#<script|<foreignObject|\son\w+=|href=#i', $svg );
	}

	public function test_preview_refuses_a_bad_nonce() {
		$this->as_role( 'administrator' );
		$id    = $this->slider();
		$_POST = array(
			'post_id'  => (string) $id,
			'_wpnonce' => 'wrong',
		);

		$this->expectException( 'WPDieException' );
		WP_Swiper_Preview::render_frame();
	}

	public function test_preview_refuses_those_who_cannot_edit() {
		$id = $this->slider();
		$this->as_role( 'subscriber' );
		$_POST = array(
			'post_id'  => (string) $id,
			'_wpnonce' => wp_create_nonce( WP_Swiper_Preview::ACTION . '_' . $id ),
		);

		$this->expectException( 'WPDieException' );
		WP_Swiper_Preview::render_frame();
	}

	public function test_preview_refuses_other_posts() {
		$this->as_role( 'administrator' );
		$id    = self::factory()->post->create();
		$_POST = array(
			'post_id'  => (string) $id,
			'_wpnonce' => wp_create_nonce( WP_Swiper_Preview::ACTION . '_' . $id ),
		);

		$this->expectException( 'WPDieException' );
		WP_Swiper_Preview::render_frame();
	}

	public function test_developer_mode_reads_at_any_scale() {
		// The preview's scale (1 to 4) on its page, as WP Banner's.
		$js = file_get_contents( WP_SWIPER_DIR . 'assets/js/preview-dev.js' );
		$this->assertStringContainsString( 'Math.max( 1, Math.min( 4, window.innerWidth / width ) )', $js );
		$this->assertStringContainsString( "document.documentElement.style.setProperty( '--wp-swiper-dev-k', value );", $js );
		// Every size of the layer times it: about 12px labels on the screen.
		$css  = file_get_contents( WP_SWIPER_DIR . 'assets/css/preview-dev.css' );
		$from = strpos( $css, '/* Developer mode of the preview' );
		$this->assertNotFalse( $from );
		$dev = substr( $css, $from );
		$this->assertStringContainsString( '--k: var(--wp-swiper-dev-k, 1);', $dev );
		$this->assertStringContainsString( 'font: 600 calc(12px * var(--k))/1.35', $dev );
		// No fixed px left there but 0 and those times --k.
		$this->assertSame( 0, preg_match_all( '#(?<![\d.(*])(?<!calc\()-?[1-9]\d*px(?! \* var\(--k\))#', preg_replace( '#/\*.*?\*/#s', '', $dev ) ) );
	}

	public function test_preview_files_are_registered() {
		WP_Swiper_Render::register_assets();
		WP_Swiper_Preview::register_assets();
		foreach ( array( 'wp-swiper-preview-dev', 'wp-swiper-preview-art' ) as $handle ) {
			$this->assertTrue( wp_style_is( $handle, 'registered' ), $handle );
		}
		foreach ( array( 'wp-swiper-preview-dev', 'wp-swiper-preview-collage', 'wp-swiper-preview-art' ) as $handle ) {
			$this->assertTrue( wp_script_is( $handle, 'registered' ), $handle );
		}
		// Art's after art.js (its layouts: window.wpSwiperArtCells).
		$this->assertSame( array( 'wp-swiper-art' ), wp_scripts()->query( 'wp-swiper-preview-art' )->deps );
		// Their words before the collage's script, unable to close it.
		$before = implode( '', (array) wp_scripts()->get_data( 'wp-swiper-preview-collage', 'before' ) );
		$this->assertStringContainsString( 'window.wpSwiperPreviewI18n = {', $before );
		$this->assertStringContainsString( '"split":"Split into cells"', $before );
		// No file of the frame printed by hand.
		$source = file_get_contents( WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php' );
		$this->assertDoesNotMatchRegularExpression( '#<(?:script|link)[^>]*(?:src|href)=#', $source );
	}
}
