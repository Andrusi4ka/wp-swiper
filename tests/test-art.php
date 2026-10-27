<?php
/**
 * WP_Swiper_Art: the Art effect kept apart from the others — its own
 * settings (art_*, Art only), markup, script and stylesheet.
 */

class Test_WP_Swiper_Art extends WP_Swiper_TestCase {

	public function set_up() {
		parent::set_up();
		WP_Swiper_Render::register_assets();
		WP_Swiper_Art::register_assets();
	}

	public function test_art_is_an_effect_with_its_styles() {
		$this->assertArrayHasKey( 'art', WP_Swiper_Settings::fields()['effect']['choices'] );
		$this->assertArrayHasKey( 'ring', WP_Swiper_Settings::fields()['art_style']['choices'] );
	}

	public function test_art_settings_are_its_own() {
		$fields = WP_Swiper_Settings::fields();
		foreach ( WP_Swiper_Art::fields() as $key => $field ) {
			$this->assertStringStartsWith( 'art_', $key );
			$this->assertSame( array( 'art' ), $field['effects'], $key );
			// On the Layout page; Photo swapping on the Controls page.
			if ( 0 === strpos( $key, 'art_swap' ) || 'art_pause' === $key ) {
				$this->assertSame( array( 'advanced', 'controls' ), array( $field['section'], $field['panel'] ), $key );
			} else {
				$this->assertSame( 'layout', $field['section'], $key );
			}
			$this->assertSame( $field, $fields[ $key ], $key );
		}
		// No other field is Art's.
		foreach ( $fields as $key => $field ) {
			if ( 0 !== strpos( $key, 'art_' ) && ! empty( $field['effects'] ) ) {
				$this->assertNotContains( 'art', $field['effects'], $key );
			}
		}
		// Its blocks first on their pages: Art on Layout, Photo swapping on Controls.
		$blocks = WP_Swiper_Settings::blocks();
		$this->assertSame( 'art', array_keys( $blocks['layout'] )[0] );
		$this->assertSame( 'art_swap', array_keys( $blocks['controls'] )[0] );
		$this->assertArrayNotHasKey( 'art_swap', $blocks['layout'] );
	}

	public function test_ring_markup() {
		$slides = $this->slides( 3 );
		$html   = $this->render( array( 'effect' => 'art', 'art_ring_count' => 5, 'art_ring_opening' => 72, 'css_class' => 'my-art', 'css_id' => 'hero' ), $slides );

		$this->assertStringContainsString( 'class="wp-swiper-art wp-swiper-art--ring wp-swiper-art--1 my-art" id="hero"', $html );
		// The middle one and five around it, empty (art.js fills them); each
		// photo once in the pool, the three repeat in the pieces.
		$this->assertSame( 6, substr_count( $html, '<div class="wp-swiper-art__piece" data-piece=' ) );
		$this->assertStringContainsString( '<template class="wp-swiper-art__pool">', $html );
		$this->assertSame( 3, substr_count( $html, 'class="wp-swiper-art__photo"' ) );
		$this->assertSame( 1, preg_match( '/data-wp-swiper-art="([^"]*)"/', $html, $match ) );
		$config = json_decode( html_entity_decode( $match[1], ENT_QUOTES ), true );
		$this->assertSame(
			array(
				'style'    => 'ring',
				'gap'      => array( 'desktop' => 8, 'tablet' => 8, 'mobile' => 8 ),
				'radius'   => array( 'desktop' => 14, 'tablet' => 14, 'mobile' => 14 ),
				'count'    => array( 'desktop' => 5, 'tablet' => 5, 'mobile' => 5 ),
				'center'   => array( 'desktop' => 46, 'tablet' => 46, 'mobile' => 46 ),
				'opening'  => array( 'desktop' => 72, 'tablet' => 72, 'mobile' => 72 ),
				'order'    => array( 'desktop' => false, 'tablet' => false, 'mobile' => false ),
				'ids'      => array_map( 'intval', wp_list_pluck( $slides, 'id' ) ),
				'auto'     => array( 'desktop' => false, 'tablet' => false, 'mobile' => false ),
				'swap'     => array( 'delay' => 3000, 'pause' => true ),
				'lightbox' => false,
			),
			$config
		);
		$this->assertStringContainsString( '--wp-swiper-art-size:560px', $html );
		// No captions, no descriptions, no label (there is none any more).
		$this->assertStringNotContainsString( 'wp-swiper-art__caption', $html );
		$this->assertStringNotContainsString( 'wp-swiper-descriptions', $html );
		$this->assertStringNotContainsString( 'wp-swiper-art__label', $html );
	}

	public function test_photos_all_around_by_default() {
		$this->assertStringContainsString( '&quot;opening&quot;:{&quot;desktop&quot;:0,', $this->render( array( 'effect' => 'art' ) ) );
	}

	public function test_ring_settings_per_device() {
		$html = $this->render(
			array(
				'effect'                  => 'art',
				'art_ring_count'          => 6,
				'art_ring_count_tablet'   => '9',
				'art_ring_count_mobile'   => '4',
				'art_ring_opening_mobile' => '90',
				'art_gap_tablet'          => '12',
				'art_radius_mobile'       => '0',
			)
		);
		$config = $this->config( $html, 'data-wp-swiper-art' );
		$this->assertSame( array( 'desktop' => 6, 'tablet' => 9, 'mobile' => 4 ), $config['count'] );
		$this->assertSame( array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 90 ), $config['opening'] );
		// Mobile empty: the tablet's.
		$this->assertSame( array( 'desktop' => 8, 'tablet' => 12, 'mobile' => 12 ), $config['gap'] );
		$this->assertSame( array( 'desktop' => 14, 'tablet' => 14, 'mobile' => 0 ), $config['radius'] );
		// Pieces for the device with the most (tablet: nine around, one in the middle).
		$this->assertSame( 10, substr_count( $html, '<div class="wp-swiper-art__piece" data-piece=' ) );

		// Each kept within its limits, empty ones as they are.
		$clean = WP_Swiper_Settings::sanitize( array( 'art_ring_count_mobile' => '99', 'art_gap_tablet' => '-5', 'art_radius_mobile' => '', 'art_ring_opening_tablet' => '500' ) );
		$this->assertSame( 16, (int) $clean['art_ring_count_mobile'] );
		$this->assertSame( 0, (int) $clean['art_gap_tablet'] );
		$this->assertSame( '', $clean['art_radius_mobile'] );
		$this->assertSame( 180, (int) $clean['art_ring_opening_tablet'] );
	}

	public function test_captions_on_the_photos() {
		$slides               = $this->slides( 2 );
		$slides[0]['caption'] = 'Hannah <b>x</b><script>y</script>';
		$html                 = $this->render( array( 'effect' => 'art', 'caption_tag' => 'h3', 'caption_shadow' => true, 'caption_bg_style' => 'solid', 'caption_bg_opacity' => 40, 'caption_font' => 'custom', 'caption_font_size_mobile' => 12 ), $slides );

		// In the first photo only, in its tag, sanitized as a slider's.
		$this->assertSame( 1, substr_count( $html, 'class="wp-swiper-art__caption"' ) );
		$this->assertStringContainsString( '<div class="wp-swiper-art__photo" data-real="0">', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-art__caption"><h3 class="wp-swiper-art__caption-text">Hannah <b>x</b>y</h3></div></div>', $html );
		// Its look from the Caption page.
		$this->assertStringContainsString( 'wp-swiper-art--caption-solid wp-swiper-art--caption-shadow', $html );
		$this->assertStringContainsString( '--wp-swiper-caption-size-mobile:12px', $html );
		$this->assertStringContainsString( '--wp-swiper-caption-bg-from:rgba(0,0,0,0.4)', $html );

		// A caption's own font size (the Slide info window), on itself.
		$slides[0]['caption_size'] = '30,24,';
		$this->assertStringContainsString( '<div class="wp-swiper-art__caption" style="--wp-swiper-caption-size:30px;--wp-swiper-caption-size-tablet:24px"><h3', $this->render( array( 'effect' => 'art', 'caption_tag' => 'h3' ), $slides ) );
		unset( $slides[0]['caption_size'] );

		// And its own color.
		$slides[0]['caption_color'] = 'rgba(255,255,0,0.8)';
		$this->assertStringContainsString( '<div class="wp-swiper-art__caption" style="--wp-swiper-caption-color:rgba(255,255,0,0.8)"><h3', $this->render( array( 'effect' => 'art', 'caption_tag' => 'h3' ), $slides ) );
		unset( $slides[0]['caption_color'] );

		// No background: no colour for it.
		$html = $this->render( array( 'effect' => 'art', 'caption_bg' => false ), $slides );
		$this->assertStringContainsString( 'wp-swiper-art--caption-nobg', $html );
		$this->assertStringNotContainsString( '--wp-swiper-caption-bg-from', $html );
	}

	public function test_descriptions_under_it() {
		$slides                   = $this->slides( 3 );
		$slides[0]['description'] = 'First';
		$slides[2]['description'] = '<p>Third</p>';
		wp_dequeue_style( 'wp-swiper' );
		wp_dequeue_script( 'wp-swiper' );
		// A direction saved before does not keep them away.
		$html = $this->render( array( 'effect' => 'art', 'direction' => 'vertical' ), $slides );

		// Right after the block, the slides' indexes as art.js knows them (the
		// first's shown until it takes over); "As the slider": they fade.
		$this->assertSame( 1, preg_match( '#</template>\s*</div>\s*<div class="wp-swiper-descriptions wp-swiper-descriptions--1 wp-swiper-descriptions--fade"#', $html ) );
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item is-active" data-wp-swiper-index="0"><p>First</p>', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item" data-wp-swiper-index="2"><p>Third</p>', $html );
		$this->assertStringNotContainsString( 'data-wp-swiper-index="1"', $html );
		// Their look is in frontend.css.
		$this->assertTrue( wp_style_is( 'wp-swiper', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'wp-swiper', 'enqueued' ) );
	}

	public function test_size_units_per_device() {
		$html = $this->render(
			array(
				'effect'               => 'art',
				'art_size'             => 80,
				'art_size_unit'        => '%',
				'art_size_tablet'      => '60',
				'art_size_unit_tablet' => 'vh',
				'art_size_unit_mobile' => 'auto',
			)
		);
		$this->assertStringContainsString( '--wp-swiper-art-size:80%;--wp-swiper-art-size-tablet:60vh;--wp-swiper-art-size-mobile:100%', $html );
		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => true ), $this->config( $html, 'data-wp-swiper-art' )['auto'] );

		// px from 160, % and vh up to 100; an empty unit: the larger screen's.
		$sizes = WP_Swiper_Art::sizes( $this->settings( array( 'art_size' => 50, 'art_size_mobile' => '150', 'art_size_unit_tablet' => '%' ) ) );
		$this->assertSame( array( 'desktop' => '160px', 'tablet' => '50%', 'mobile' => '100%' ), $sizes );

		$clean = WP_Swiper_Settings::sanitize( array( 'art_size_unit' => 'vh', 'art_size_unit_tablet' => 'em', 'art_size_unit_mobile' => 'auto' ) );
		$this->assertSame( array( 'vh', '', 'auto' ), array( $clean['art_size_unit'], $clean['art_size_unit_tablet'], $clean['art_size_unit_mobile'] ) );
	}

	public function test_photo_swapping() {
		$html = $this->render( array( 'effect' => 'art', 'art_swap_delay' => 4500, 'art_swap_speed' => 650, 'art_pause' => false ) );
		$this->assertSame( array( 'delay' => 4500, 'pause' => false ), $this->config( $html, 'data-wp-swiper-art' )['swap'] );
		$this->assertStringContainsString( '--wp-swiper-art-speed:650ms', $html );

		// Off: nothing swaps.
		$this->assertFalse( $this->config( $this->render( array( 'effect' => 'art', 'art_swap' => false ) ), 'data-wp-swiper-art' )['swap'] );

		// Within the Collage's limits.
		$clean = WP_Swiper_Settings::sanitize( array( 'effect' => 'art', 'art_swap_delay' => '50', 'art_swap_speed' => '99999' ) );
		$this->assertSame( 1000, $clean['art_swap_delay'] );
		$this->assertSame( 3000, $clean['art_swap_speed'] );
		$this->assertFalse( $clean['art_swap'] );
	}

	public function test_image_border() {
		// Off by default: no class, no variables.
		$html = $this->render( array( 'effect' => 'art' ) );
		$this->assertStringNotContainsString( 'wp-swiper-art--border', $html );
		$this->assertStringNotContainsString( '--wp-swiper-art-border', $html );

		// On: a width per device (mobile empty: the tablet's), one color.
		$html = $this->render( array( 'effect' => 'art', 'art_border' => true, 'art_border_width' => 3, 'art_border_width_tablet' => '0', 'art_border_color' => '#f57662' ) );
		$this->assertStringContainsString( 'wp-swiper-art--border', $html );
		$this->assertStringContainsString( '--wp-swiper-art-border-width:3px;--wp-swiper-art-border-width-tablet:0px;--wp-swiper-art-border-width-mobile:0px;--wp-swiper-art-border-color:#f57662', $html );

		$clean = WP_Swiper_Settings::sanitize( array( 'effect' => 'art', 'art_border' => '1', 'art_border_width_mobile' => '99', 'art_border_color' => 'red' ) );
		$this->assertTrue( $clean['art_border'] );
		$this->assertSame( 50, (int) $clean['art_border_width_mobile'] );
		$this->assertSame( '#ffffff', $clean['art_border_color'] );
	}

	public function test_alignment_per_device() {
		// Centered by default; right on desktop, left on mobile (tablet: the desktop's).
		$this->assertStringContainsString( '--wp-swiper-art-margin:0 auto;', $this->render( array( 'effect' => 'art' ) ) );
		$html = $this->render( array( 'effect' => 'art', 'art_align' => 'right', 'art_align_mobile' => 'left' ) );
		$this->assertStringContainsString( '--wp-swiper-art-margin:0 0 0 auto;--wp-swiper-art-margin-tablet:0 0 0 auto;--wp-swiper-art-margin-mobile:0 auto 0 0', $html );
		// Right under Size.
		$fields = WP_Swiper_Art::blocks()['layout']['art']['fields'];
		$this->assertSame( array_search( 'art_size', $fields, true ) + 1, array_search( 'art_align', $fields, true ) );

		$clean = WP_Swiper_Settings::sanitize( array( 'effect' => 'art', 'art_align' => 'middle', 'art_align_tablet' => 'left' ) );
		$this->assertSame( array( 'center', 'left', '' ), array( $clean['art_align'], $clean['art_align_tablet'], $clean['art_align_mobile'] ) );
	}

	public function test_lightbox() {
		$slides                   = $this->slides( 2 );
		$slides[1]['description'] = 'Second';
		wp_dequeue_script( 'wp-swiper' );
		$html   = $this->render( array( 'effect' => 'art', 'lightbox' => true, 'art_radius_mobile' => '4', 'lightbox_radius_tablet' => '20', 'description_lightbox_place' => 'under' ), $slides );
		$config = $this->config( $html, 'data-wp-swiper-art' );

		$this->assertStringContainsString( 'wp-swiper-art--lightbox', $html );
		// Each photo with its full size for it.
		$this->assertSame( 2, substr_count( $html, 'data-wp-swiper-full="' ) );
		$this->assertStringContainsString( ' data-wp-swiper-full="' . wp_get_attachment_image_url( $slides[0]['id'], 'full' ) . '"', $html );
		// Its corners: its own (tablet; mobile inherits it), else Art's Rounded corners.
		$this->assertSame( array( 'desktop' => 14, 'tablet' => 20, 'mobile' => 20 ), $config['lightbox']['radius'] );
		// Descriptions in it, under the image.
		$this->assertTrue( $config['lightbox']['descriptions'] );
		$this->assertSame( 800, $config['lightbox']['text']['max'] );
		// The sliders' lightbox (frontend.js, with Swiper).
		$this->assertTrue( wp_script_is( 'wp-swiper', 'enqueued' ) );

		// Off by default; any link turns it off.
		$this->assertFalse( $this->config( $this->render( array( 'effect' => 'art' ), $slides ), 'data-wp-swiper-art' )['lightbox'] );
		$slides[0]['link'] = 'https://example.com/';
		$html              = $this->render( array( 'effect' => 'art', 'lightbox' => true ), $slides );
		$this->assertFalse( $this->config( $html, 'data-wp-swiper-art' )['lightbox'] );
		$this->assertStringNotContainsString( 'data-wp-swiper-full', $html );
	}

	public function test_art_loads_only_its_own_assets() {
		wp_dequeue_style( 'wp-swiper' );
		wp_dequeue_script( 'wp-swiper' );
		$this->render( array( 'effect' => 'art' ) );
		$this->assertTrue( wp_style_is( 'wp-swiper-art', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'wp-swiper-art', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'wp-swiper', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'wp-swiper', 'enqueued' ) );
	}

	public function test_other_effects_do_not_load_art() {
		wp_dequeue_style( 'wp-swiper-art' );
		wp_dequeue_script( 'wp-swiper-art' );
		$html = $this->render( array( 'effect' => 'slide', 'art_ring_count' => 9 ) );
		$this->assertStringNotContainsString( 'wp-swiper-art', $html );
		$this->assertFalse( wp_script_is( 'wp-swiper-art', 'enqueued' ) );
	}

	public function test_art_shows_images_only() {
		$slides   = $this->slides( 1 );
		$slides[] = array(
			'id'      => $this->video(),
			'caption' => '',
			'link'    => '',
			'new_tab' => false,
			'poster'  => 0,
		);
		$html = $this->render( array( 'effect' => 'art', 'art_ring_count' => 3 ), $slides );
		// The one image in the pool (art.js puts it in all four pieces).
		$this->assertSame( 1, substr_count( $html, 'wp-swiper-art__image' ) );
		$this->assertStringNotContainsString( '<video', $html );
	}

	public function test_middle_circle_per_device_without_a_field() {
		$html   = $this->render( array( 'effect' => 'art', 'art_ring_center' => 52.36, 'art_ring_center_mobile' => '30' ) );
		$this->assertSame( 1, preg_match( '/data-wp-swiper-art="([^"]*)"/', $html, $match ) );
		$config = json_decode( html_entity_decode( $match[1], ENT_QUOTES ), true );
		// Tablet: the desktop's.
		$this->assertEquals( array( 'desktop' => 52.4, 'tablet' => 52.4, 'mobile' => 30 ), $config['center'] );

		// Kept as saved, within 20–75; no field of its own on the Layout page.
		$clean = WP_Swiper_Settings::sanitize( array( 'art_ring_center' => '90', 'art_ring_center_tablet' => '', 'art_ring_center_mobile' => '12.345' ) );
		$this->assertSame( 75.0, (float) $clean['art_ring_center'] );
		$this->assertSame( '', $clean['art_ring_center_tablet'] );
		$this->assertSame( 20.0, (float) $clean['art_ring_center_mobile'] );
		$this->assertNotContains( 'art_ring_center', WP_Swiper_Art::blocks()['layout']['art']['fields'] );
	}

	public function test_order_per_device_by_photo() {
		$slides = $this->slides( 4 );
		$ids    = wp_list_pluck( $slides, 'id' );

		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => false ), WP_Swiper_Art::orders( $this->settings(), $slides ) );

		$orders = WP_Swiper_Art::orders(
			$this->settings(
				array(
					// A photo no longer in the slider is left out; the others follow in the slides' order.
					'art_order'        => $ids[2] . ',999999,' . $ids[0],
					'art_order_mobile' => (string) $ids[3],
				)
			),
			$slides
		);
		$this->assertSame( array( 2, 0, 1, 3 ), $orders['desktop'] );
		// Tablet: the desktop's.
		$this->assertSame( array( 2, 0, 1, 3 ), $orders['tablet'] );
		$this->assertSame( array( 3, 0, 1, 2 ), $orders['mobile'] );
	}

	public function test_order_is_kept_in_hidden_values_after_the_style() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( null, array( 'effect' => 'art', 'art_order_tablet' => '5,4' ) ) ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'data-field="art_order"', $html );
		$style = strpos( $html, 'data-field="art_style"' );
		foreach ( array( 'art_order' => '', 'art_order_tablet' => '5,4', 'art_order_mobile' => '', 'art_ring_center' => '46', 'art_ring_center_tablet' => '', 'art_ring_center_mobile' => '' ) as $key => $value ) {
			$at = strpos( $html, '<input type="hidden" id="wp-swiper-' . $key . '" name="wp_swiper_settings[' . $key . ']" value="' . $value . '">' );
			$this->assertNotFalse( $at, $key );
			$this->assertGreaterThan( $style, $at, $key );
		}
		// The preview's hint for Art.
		ob_start();
		WP_Swiper_Preview::render_panel( get_post( $this->slider() ) );
		$this->assertStringContainsString( '<p class="description wp-swiper-preview__hint" data-effect="art" hidden>', ob_get_clean() );
	}

	public function test_sanitize_art_settings() {
		$clean = WP_Swiper_Settings::sanitize(
			array(
				'effect'          => 'art',
				'art_style'       => 'nonsense',
				'art_ring_count'  => '40',
				'art_label_title' => 'Hannah',
			)
		);
		$this->assertSame( 'art', $clean['effect'] );
		$this->assertSame( 'ring', $clean['art_style'] );
		$this->assertSame( 16, $clean['art_ring_count'] );
		// The label is gone: nothing of it is kept.
		$this->assertArrayNotHasKey( 'art_label_title', $clean );
	}

	public function test_settings_screen_marks_ring_fields() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( null, array( 'effect' => 'art' ) ) ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-field="art_ring_count" data-effects="art" data-styles="ring"', $html );
		$this->assertStringContainsString( 'data-field="art_style" data-effects="art">', $html );
	}
}
