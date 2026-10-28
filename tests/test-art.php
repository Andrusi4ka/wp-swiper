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
			// On the Layout page; Photo swapping on the Controls page; captions on the middle photo only on the Caption page.
			if ( 0 === strpos( $key, 'art_swap' ) || 'art_pause' === $key || 'art_shape_target' === $key ) {
				$this->assertSame( array( 'advanced', 'controls' ), array( $field['section'], $field['panel'] ), $key );
			} elseif ( 'art_caption_middle' === $key ) {
				$this->assertSame( 'caption', $field['section'], $key );
			} else {
				$this->assertSame( 'layout', $field['section'], $key );
			}
			$this->assertSame( $field, $fields[ $key ], $key );
		}
		// No other field is Art's, but the Image shadow, shared with the effects whose photos stand apart.
		foreach ( $fields as $key => $field ) {
			if ( 0 !== strpos( $key, 'art_' ) && 0 !== strpos( $key, 'image_shadow' ) && ! empty( $field['effects'] ) ) {
				$this->assertNotContains( 'art', $field['effects'], $key );
			}
		}
		// Its blocks first on their pages: Art on Layout, Photo swapping on Controls.
		$blocks = WP_Swiper_Settings::blocks();
		$this->assertSame( 'art', array_keys( $blocks['layout'] )[0] );
		$this->assertSame( 'art_swap', array_keys( $blocks['controls'] )[0] );
		$this->assertArrayNotHasKey( 'art_swap', $blocks['layout'] );
	}

	public function test_art_shadow_follows_the_shapes() {
		$html = $this->render( array( 'effect' => 'art', 'image_shadow' => true, 'image_shadow_x' => 2, 'image_shadow_y' => 6, 'image_shadow_blur' => 12, 'image_shadow_spread' => 9, 'image_shadow_color' => '#112233' ), $this->slides( 3 ) );
		$this->assertStringContainsString( 'wp-swiper-art--shadow', $html );
		// drop-shadow: no spread.
		$this->assertStringContainsString( '--wp-swiper-art-shadow:drop-shadow(2px 6px 12px #112233)', $html );
		$this->assertStringContainsString( '--wp-swiper-shadow-room:6px 14px 18px 10px', $html );

		$this->assertStringNotContainsString( 'wp-swiper-art--shadow', $this->render( array( 'effect' => 'art' ), $this->slides( 3 ) ) );
	}

	public function test_captions_on_the_middle_photo_only() {
		$slides = $this->slides( 3, array( 'caption' => '<p>Hi</p>' ) );
		$this->assertStringContainsString( 'wp-swiper-art--caption-middle', $this->render( array( 'effect' => 'art', 'art_caption_middle' => true ), $slides ) );
		$this->assertStringNotContainsString( 'wp-swiper-art--caption-middle', $this->render( array( 'effect' => 'art' ), $slides ) );
		$this->assertSame( 'caption', WP_Swiper_Settings::fields()['art_caption_middle']['section'] );
	}

	public function test_swapping_the_middle_photo() {
		$config = $this->config( $this->render( array( 'effect' => 'art', 'art_swap_target' => 'middle', 'art_swap_delay' => 2000 ) ), 'data-wp-swiper-art' );
		$this->assertSame( array( 'delay' => 2000, 'target' => 'middle', 'pause' => true ), $config['swap'] );
		$this->assertSame( 'any', WP_Swiper_Settings::sanitize( array( 'art_swap_target' => 'nope' ) )['art_swap_target'] );
	}

	public function test_an_image_in_the_opening() {
		$image  = $this->image( 400, 200 );
		$values = array(
			'effect'                        => 'art',
			'art_ring_opening'              => 60,
			'art_opening_content'           => 'image',
			'art_opening_image'             => $image,
			'art_opening_image_size'        => 80,
			'art_opening_image_size_mobile' => 50,
			'art_opening_gap'               => 16,
			'art_opening_gap_mobile'        => 6,
		);
		$html   = $this->render( $values, $this->slides( 3 ) );

		// Beside the stage (the photos' shadow is not for it), hidden until art.js places it.
		$this->assertMatchesRegularExpression( '#</div>\s*<div class="wp-swiper-art__opening wp-swiper-art__opening--image" hidden><img[^>]*class="wp-swiper-art__opening-image"#', $html );
		$this->assertStringContainsString( '--wp-swiper-art-opening-size:80%', $html );
		$this->assertStringContainsString( '--wp-swiper-art-opening-size-mobile:50%', $html );
		$this->assertSame( array( 'desktop' => 16, 'tablet' => 16, 'mobile' => 6 ), $this->config( $html, 'data-wp-swiper-art' )['openingGap'] );

		// No image chosen, or nothing in it: none, no gap.
		foreach ( array( array( 'art_opening_image' => 0 ), array( 'art_opening_content' => 'none' ) ) as $other ) {
			$html = $this->render( $other + $values, $this->slides( 3 ) );
			$this->assertStringNotContainsString( 'wp-swiper-art__opening', $html );
			$this->assertArrayNotHasKey( 'openingGap', $this->config( $html, 'data-wp-swiper-art' ) );
		}
	}

	public function test_text_in_the_opening() {
		$text = '<h3>Our <a href="https://example.com/shop">shop</a></h3><script>alert(1)</script><p>Open daily</p>';
		$html = $this->render( array( 'effect' => 'art', 'art_opening_content' => 'text', 'art_opening_text' => $text, 'art_opening_font_size' => 22 ), $this->slides( 3 ) );

		$this->assertStringContainsString( '<div class="wp-swiper-art__opening wp-swiper-art__opening--text" hidden><div class="wp-swiper-art__opening-text"><h3>Our <a href="https://example.com/shop">shop</a></h3>', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '--wp-swiper-art-opening-font:22px', $html );

		// Saved as a description is (its markup, links too).
		$this->assertSame( 'editor', WP_Swiper_Settings::fields()['art_opening_text']['type'] );
		$this->assertSame( '<h3>Our <a href="https://example.com/shop">shop</a></h3>alert(1)<p>Open daily</p>', WP_Swiper_Settings::sanitize( array( 'art_opening_text' => $text ) )['art_opening_text'] );
		$this->assertSame( '', WP_Swiper_Settings::sanitize( array( 'art_opening_text' => '<p>&nbsp;</p>' ) )['art_opening_text'] );
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
				'swap'     => array(
					'delay'  => 3000,
					'target' => 'any',
					'pause'  => true,
				),
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

		// In the first photo only, sanitized as a slider's; saved before in the
		// slider's Tag (until 2.8): in that tag still.
		$this->assertSame( 1, substr_count( $html, 'class="wp-swiper-art__caption"' ) );
		$this->assertStringContainsString( '<div class="wp-swiper-art__photo" data-real="0">', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-art__caption"><div class="wp-swiper-art__caption-text"><h3>Hannah <b>x</b>y</h3></div></div></div>', $html );
		// Lines of its own: as they are.
		$own                  = $slides;
		$own[0]['caption']    = '<h2 style="text-align: right">Own</h2>';
		$this->assertStringContainsString( '<div class="wp-swiper-art__caption-text"><h2 style="text-align: right">Own</h2></div>', $this->render( array( 'effect' => 'art', 'caption_tag' => 'h3' ), $own ) );
		// Its look from the Caption page.
		$this->assertStringContainsString( 'wp-swiper-art--caption-solid wp-swiper-art--caption-shadow', $html );
		$this->assertStringContainsString( '--wp-swiper-caption-size-mobile:12px', $html );
		$this->assertStringContainsString( '--wp-swiper-caption-bg-from:rgba(0,0,0,0.4)', $html );

		// A caption's own font size (the Slide info window), on itself.
		$slides[0]['caption_size'] = '30,24,';
		$this->assertStringContainsString( '<div class="wp-swiper-art__caption" style="--wp-swiper-caption-size:30px;--wp-swiper-caption-size-tablet:24px;--wp-swiper-caption-size-mobile:24px"><div class="wp-swiper-art__caption-text"><h3', $this->render( array( 'effect' => 'art', 'caption_tag' => 'h3' ), $slides ) );
		unset( $slides[0]['caption_size'] );

		// And its own color.
		$slides[0]['caption_color'] = 'rgba(255,255,0,0.8)';
		$this->assertStringContainsString( '<div class="wp-swiper-art__caption" style="--wp-swiper-caption-color:rgba(255,255,0,0.8)"><div class="wp-swiper-art__caption-text"><h3', $this->render( array( 'effect' => 'art', 'caption_tag' => 'h3' ), $slides ) );
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
		$this->assertSame( array( 'delay' => 4500, 'target' => 'any', 'pause' => false ), $this->config( $html, 'data-wp-swiper-art' )['swap'] );
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

	/* Ring + text --------------------------------------------------------- */

	public function test_ring_text_is_the_ring_without_a_middle_photo() {
		$slides = $this->slides( 3, array( 'caption' => '<p>Hi</p>' ) );
		$values = array(
			'effect'              => 'art',
			'art_style'           => 'ring-text',
			'art_ring_count'      => 5,
			// Ring's own: none of them applies.
			'art_ring_opening'    => 72,
			'art_opening_content' => 'text',
			'art_opening_text'    => '<p>Opening</p>',
			'art_caption_middle'  => true,
			'art_swap_target'     => 'middle',
		);
		$html   = $this->render( $values, $slides );
		$config = $this->config( $html, 'data-wp-swiper-art' );

		$this->assertStringContainsString( 'class="wp-swiper-art wp-swiper-art--ring-text wp-swiper-art--', $html );
		// The five around only.
		$this->assertSame( 5, substr_count( $html, '<div class="wp-swiper-art__piece" data-piece=' ) );
		$this->assertSame( array( 'desktop' => 5, 'tablet' => 5, 'mobile' => 5 ), WP_Swiper_Art::counts( $this->settings( $values ) ) );
		$this->assertSame( 'ring-text', $config['style'] );
		// All around, no opening and nothing in it.
		$this->assertSame( 0, $config['opening'] );
		$this->assertStringNotContainsString( 'wp-swiper-art__opening', $html );
		$this->assertArrayNotHasKey( 'openingGap', $config );
		// No middle photo: captions on all of them, any piece swaps.
		$this->assertStringNotContainsString( 'wp-swiper-art--caption-middle', $html );
		$this->assertSame( 'any', $config['swap']['target'] );
		// The middle circle's size still per device (dragged in the preview).
		$this->assertArrayHasKey( 'center', $config );

		// The Ring keeps a photo in the middle.
		$ring = $values;
		$ring['art_style'] = 'ring';
		$this->assertSame( 6, substr_count( $this->render( $ring, $slides ), '<div class="wp-swiper-art__piece" data-piece=' ) );
	}

	public function test_ring_text_image_and_text_in_the_middle() {
		$image  = $this->image( 400, 200 );
		$text   = '<h3>Our <a href="https://example.com/shop">shop</a></h3><script>alert(1)</script><p><span style="color: #ff0000;">Open</span> daily</p>';
		$values = array(
			'effect'                       => 'art',
			'art_style'                    => 'ring-text',
			'art_center_image'             => $image,
			'art_center_image_size'        => 40,
			'art_center_image_size_mobile' => 70,
			'art_center_text'              => $text,
			'art_center_font_size'         => 24,
			'art_center_font_size_mobile'  => 14,
			'art_center_padding'           => 20,
			'art_center_bg'                => 'rgba(10,20,30,0.5)',
		);
		$html   = $this->render( $values, $this->slides( 3 ) );

		// Beside the stage (the photos' shadow is not for it), hidden until art.js
		// places it; the image over the text, the text as the editor's (links and
		// colored words kept, no script).
		$this->assertMatchesRegularExpression( '#</div>\s*<div class="wp-swiper-art__center" hidden><div class="wp-swiper-art__center-inner"><img[^>]*class="wp-swiper-art__center-image"[^>]*><div class="wp-swiper-art__center-text"><h3>Our <a href="https://example.com/shop">shop</a></h3>#', $html );
		$this->assertStringContainsString( '<span style="color: #ff0000">Open</span>', $html );
		$this->assertStringNotContainsString( '<script', $html );

		$this->assertSame( 1, preg_match( '/class="wp-swiper-art [^"]*" style="([^"]*)"/', $html, $match ) );
		$style = html_entity_decode( $match[1] );
		$this->assertStringContainsString( '--wp-swiper-art-center-image:40%', $style );
		$this->assertStringContainsString( '--wp-swiper-art-center-image-tablet:40%', $style );
		$this->assertStringContainsString( '--wp-swiper-art-center-image-mobile:70%', $style );
		$this->assertStringContainsString( '--wp-swiper-art-center-font:24px', $style );
		$this->assertStringContainsString( '--wp-swiper-art-center-font-mobile:14px', $style );
		$this->assertStringContainsString( '--wp-swiper-art-center-padding:20px', $style );
		$this->assertStringContainsString( '--wp-swiper-art-center-bg:rgba(10,20,30,0.5)', $style );

		// Either alone.
		$only_text = $this->render( array( 'art_center_image' => 0 ) + $values, $this->slides( 3 ) );
		$this->assertStringContainsString( '<div class="wp-swiper-art__center-inner"><div class="wp-swiper-art__center-text">', $only_text );
		$only_image = $this->render( array( 'art_center_text' => '' ) + $values, $this->slides( 3 ) );
		$this->assertStringContainsString( 'wp-swiper-art__center-image', $only_image );
		$this->assertStringNotContainsString( 'wp-swiper-art__center-text', $only_image );

		// Neither: no middle block, none of its variables.
		$none = $this->render( array( 'art_center_image' => 0, 'art_center_text' => '<p>&nbsp;</p>' ) + $values, $this->slides( 3 ) );
		$this->assertStringNotContainsString( 'wp-swiper-art__center', $none );
		$this->assertStringNotContainsString( '--wp-swiper-art-center', $none );

		// Content in the middle off: none of it (the circle stays empty).
		$off = $this->render( array( 'art_center' => false ) + $values, $this->slides( 3 ) );
		$this->assertStringNotContainsString( 'wp-swiper-art__center', $off );
		$this->assertStringNotContainsString( '--wp-swiper-art-center', $off );
		$this->assertSame( 7, substr_count( $off, '<div class="wp-swiper-art__piece" data-piece=' ) );

		// The Ring shows none of it.
		$this->assertStringNotContainsString( 'wp-swiper-art__center', $this->render( array( 'art_style' => 'ring' ) + $values, $this->slides( 3 ) ) );
	}

	public function test_ring_text_settings() {
		$fields = WP_Swiper_Settings::fields();
		foreach ( array( 'art_center', 'art_center_image', 'art_center_image_size', 'art_center_text', 'art_center_font_size', 'art_center_padding', 'art_center_bg' ) as $key ) {
			$this->assertSame( array( 'ring-text' ), $fields[ $key ]['styles'], $key );
			$this->assertContains( $key, WP_Swiper_Settings::blocks()['layout']['art']['fields'], $key );
		}
		// Ring's only: the opening. With a middle photo (Ring, Grid + circle):
		// captions on it only, swapping it.
		foreach ( array( 'art_ring_opening', 'art_opening_content', 'art_opening_text' ) as $key ) {
			$this->assertSame( array( 'ring' ), $fields[ $key ]['styles'], $key );
		}
		// SVG shape: captions on its main window only too; its photos move on window by window.
		$this->assertSame( array( 'ring', 'grid-circle', 'shape' ), $fields['art_caption_middle']['styles'] );
		$this->assertSame( array( 'ring', 'grid-circle' ), $fields['art_swap_target']['styles'] );
		$this->assertSame( 'editor', $fields['art_center_text']['type'] );
		// Content in the middle: a checkbox, on by default, before the middle's own settings.
		$this->assertSame( 'checkbox', $fields['art_center']['type'] );
		$this->assertTrue( WP_Swiper_Settings::defaults()['art_center'] );
		$block = WP_Swiper_Settings::blocks()['layout']['art']['fields'];
		$this->assertSame( array_search( 'art_center', $block, true ) + 1, array_search( 'art_center_image', $block, true ) );
		$this->assertSame( 'alpha_color', $fields['art_center_bg']['type'] );

		// The background: none (transparent) by default; a color or nothing else kept.
		$this->assertSame( 'rgba(255,255,255,0)', WP_Swiper_Settings::defaults()['art_center_bg'] );
		$clean = WP_Swiper_Settings::sanitize(
			array(
				'art_center_bg'        => 'red; background: url(x)',
				'art_center_text'      => '<p>&nbsp;</p>',
				'art_center_font_size' => '500',
				'art_center_image'     => '999999',
			)
		);
		$this->assertSame( 'rgba(255,255,255,0)', $clean['art_center_bg'] );
		$this->assertSame( '', $clean['art_center_text'] );
		$this->assertSame( 120, $clean['art_center_font_size'] );
		$this->assertSame( 0, $clean['art_center_image'] );
	}

	public function test_styles_to_come_are_shown_but_not_kept() {
		// None now; a style in soon() would not be one of styles(), nor kept.
		foreach ( array_keys( WP_Swiper_Art::soon() ) as $style ) {
			$this->assertArrayNotHasKey( $style, WP_Swiper_Art::styles() );
		}
		$this->assertSame( 'ring', WP_Swiper_Settings::sanitize( array( 'art_style' => 'triangle' ) )['art_style'] );
		foreach ( array_keys( WP_Swiper_Art::styles() ) as $style ) {
			$this->assertSame( $style, WP_Swiper_Settings::sanitize( array( 'art_style' => $style ) )['art_style'] );
		}

		// Its tile: greyed, without a value (nothing to pick).
		$tiles = new ReflectionMethod( 'WP_Swiper_Admin', 'tiles' );
		$tiles->setAccessible( true );
		ob_start();
		$tiles->invoke( null, array( 'label' => 'Style', 'tiles' => 'art', 'choices' => array( 'ring' => 'Ring' ), 'soon' => array( 'grid-circle' => 'Grid + circle' ) ), false );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-value="ring"', $html );
		$this->assertMatchesRegularExpression( '#<span class="wp-swiper-tiles__tile wp-swiper-tiles__tile--soon" title="Grid \+ circle \(coming soon\)"[^>]*aria-disabled="true">\s*<svg class="wp-swiper-tiles__icon"#', $html );
		$this->assertStringNotContainsString( 'data-value="grid-circle"', $html );

		// On the settings screen: every Style a tile to pick.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( null, array( 'effect' => 'art' ) ) ) );
		$html = ob_get_clean();
		foreach ( array( 'ring', 'ring-text', 'grid-circle' ) as $style ) {
			$this->assertStringContainsString( 'class="wp-swiper-tiles__tile" data-value="' . $style . '"', $html );
			$this->assertStringContainsString( '<option value="' . $style . '"', $html );
		}
		$this->assertStringNotContainsString( 'tiles__tile--soon', $html );

		// The icons: the tile's color (currentColor), inline as they are.
		foreach ( array( 'ring', 'ring-text', 'grid-circle' ) as $style ) {
			$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/art/' . $style . '.svg' );
			$this->assertStringStartsWith( '<svg ', $svg, $style );
			$this->assertStringContainsString( 'fill="currentColor"', $svg, $style );
		}
	}

	/* Grid + circle ------------------------------------------------------- */

	public function test_grid_circle_markup_and_config() {
		$values = array(
			'effect'                      => 'art',
			'art_style'                   => 'grid-circle',
			'art_grid_count'              => 8,
			'art_grid_count_mobile'       => '5',
			'art_grid_columns'            => 3,
			'art_grid_columns_tablet'     => '4',
			'art_grid_columns_mobile'     => '2',
			'art_grid_height'             => 420,
			'art_grid_height_unit'        => 'auto',
			'art_grid_height_unit_tablet' => 'px',
			'art_ring_center'             => 64,
			// Ring's own: no opening in a grid; nor Size (nor its Auto).
			'art_ring_opening'            => 72,
			'art_opening_content'         => 'text',
			'art_opening_text'            => '<p>Opening</p>',
			'art_size_unit'               => 'auto',
			'art_caption_middle'          => true,
			'art_swap_target'             => 'middle',
		);
		$html   = $this->render( $values, $this->slides( 3, array( 'caption' => '<p>Hi</p>' ) ) );
		$config = $this->config( $html, 'data-wp-swiper-art' );

		$this->assertStringContainsString( 'class="wp-swiper-art wp-swiper-art--grid-circle wp-swiper-art--', $html );
		// As wide as the column (art.css), whatever the Size says.
		$this->assertStringContainsString( 'wp-swiper-art--wide', $html );
		$this->assertTrue( $config['wide'] );
		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => false ), $config['auto'] );
		$this->assertSame( 'grid-circle', $config['style'] );
		// Its photos and columns per device (tablet and mobile: their own, else the larger screen's).
		$this->assertSame( array( 'desktop' => 8, 'tablet' => 8, 'mobile' => 5 ), $config['cells'] );
		$this->assertSame( array( 'desktop' => 3, 'tablet' => 4, 'mobile' => 2 ), $config['columns'] );
		// Height Auto (square photos) per device; px: its height.
		$this->assertSame( array( 'desktop' => true, 'tablet' => false, 'mobile' => false ), $config['square'] );
		$this->assertSame( 1, preg_match( '/class="wp-swiper-art [^"]*" style="([^"]*)"/', $html, $match ) );
		$style = html_entity_decode( $match[1] );
		$this->assertStringContainsString( '--wp-swiper-art-height:auto', $style );
		$this->assertStringContainsString( '--wp-swiper-art-height-tablet:420px', $style );
		$this->assertStringContainsString( '--wp-swiper-art-height-mobile:420px', $style );
		// A piece per photo and the circle's, as many as the device with the most.
		$this->assertSame( array( 'desktop' => 9, 'tablet' => 9, 'mobile' => 6 ), WP_Swiper_Art::counts( $this->settings( $values ) ) );
		$this->assertSame( 9, substr_count( $html, '<div class="wp-swiper-art__piece" data-piece=' ) );
		$this->assertSame( 64.0, (float) $config['center']['desktop'] );
		// No opening; a middle photo: its captions only, it swaps.
		$this->assertSame( 0, $config['opening'] );
		$this->assertStringNotContainsString( 'wp-swiper-art__opening', $html );
		$this->assertStringContainsString( 'wp-swiper-art--caption-middle', $html );
		$this->assertSame( 'middle', $config['swap']['target'] );

		// A ring: none of the grid's.
		$ring = $this->render( array( 'art_style' => 'ring' ) + $values, $this->slides( 3 ) );
		$this->assertStringNotContainsString( 'wp-swiper-art--wide', $ring );
		$this->assertStringNotContainsString( '--wp-swiper-art-height', $ring );
		$this->assertArrayNotHasKey( 'cells', $this->config( $ring, 'data-wp-swiper-art' ) );
	}

	public function test_grid_circle_settings() {
		$fields = WP_Swiper_Settings::fields();
		foreach ( array( 'art_grid_count', 'art_grid_columns', 'art_grid_height', 'art_grid_height_unit' ) as $key ) {
			$this->assertSame( array( 'grid-circle' ), $fields[ $key ]['styles'], $key );
			$this->assertTrue( $fields[ $key ]['responsive'], $key );
		}
		// The rings' own and SVG shape's: their Size and Alignment (a grid is as wide as the column).
		foreach ( array( 'art_size', 'art_size_unit', 'art_align' ) as $key ) {
			$this->assertSame( array( 'ring', 'ring-text', 'shape' ), $fields[ $key ]['styles'], $key );
		}
		$this->assertArrayNotHasKey( 'art_grid_shape', $fields );
		$defaults = WP_Swiper_Settings::defaults();
		$this->assertSame( 9, $defaults['art_grid_count'] );
		$this->assertSame( 3, $defaults['art_grid_columns'] );
		$this->assertSame( 'auto', $defaults['art_grid_height_unit'] );
		$this->assertSame( 'art_grid_height_unit', $fields['art_grid_height']['unit'] );
		$this->assertSame( 'art_grid_height', $fields['art_grid_height_unit']['inline_in'] );
		// After the ring's Photos around: Photos, Columns, Height.
		$block = WP_Swiper_Settings::blocks()['layout']['art']['fields'];
		$at    = array_search( 'art_ring_count', $block, true );
		$this->assertSame( array( 'art_ring_count', 'art_grid_count', 'art_grid_columns', 'art_grid_height' ), array_slice( $block, $at, 4 ) );
		// The middle circle's size for it too (dragged in the preview).
		$this->assertContains( 'grid-circle', $fields['art_ring_center']['styles'] );

		$clean = WP_Swiper_Settings::sanitize(
			array(
				'art_grid_count'          => '99',
				'art_grid_count_mobile'   => '1',
				'art_grid_columns'        => '12',
				'art_grid_columns_tablet' => '1',
				'art_grid_columns_mobile' => '',
				'art_grid_height'         => '9000',
				'art_grid_height_unit'    => 'vh',
				'art_grid_shape'          => 'wide',
			)
		);
		$this->assertSame( 36, (int) $clean['art_grid_count'] );
		$this->assertSame( 2, (int) $clean['art_grid_count_mobile'] );
		$this->assertSame( 6, (int) $clean['art_grid_columns'] );
		$this->assertSame( 2, (int) $clean['art_grid_columns_tablet'] );
		$this->assertSame( '', $clean['art_grid_columns_mobile'] );
		$this->assertSame( 2000, (int) $clean['art_grid_height'] );
		$this->assertSame( 'auto', $clean['art_grid_height_unit'] );
		$this->assertArrayNotHasKey( 'art_grid_shape', $clean );
	}

	public function test_image_border_over_the_shadow_in_the_look_block() {
		$blocks = WP_Swiper_Settings::blocks()['layout'];
		// Not in the Art block: in Look, right before the Image shadow.
		foreach ( array( 'art_border', 'art_border_width', 'art_border_color' ) as $key ) {
			$this->assertNotContains( $key, $blocks['art']['fields'], $key );
		}
		$look = $blocks['look']['fields'];
		$at   = array_search( 'art_border', $look, true );
		$this->assertSame( array( 'art_border', 'art_border_width', 'art_border_color', 'image_shadow' ), array_slice( $look, $at, 4 ) );

		// On the screen: the Look title, Image border, then Image shadow.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( null, array( 'effect' => 'art' ) ) ) );
		$html   = ob_get_clean();
		$title  = strpos( $html, '>Look<' );
		$border = strpos( $html, 'data-field="art_border"' );
		$shadow = strpos( $html, 'data-field="image_shadow"' );
		$this->assertNotFalse( $title );
		$this->assertGreaterThan( $title, $border );
		$this->assertGreaterThan( $border, $shadow );
	}

	public function test_settings_screen_marks_ring_fields() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( null, array( 'effect' => 'art' ) ) ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-field="art_ring_count" data-effects="art" data-styles="ring ring-text"', $html );
		$this->assertStringContainsString( 'data-field="art_ring_opening" data-effects="art" data-styles="ring"', $html );
		$this->assertStringContainsString( 'data-field="art_center_text" data-effects="art" data-styles="ring-text"', $html );
		$this->assertStringContainsString( 'data-field="art_style" data-effects="art">', $html );
	}
}
