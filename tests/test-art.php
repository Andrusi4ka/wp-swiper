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
			if ( 0 === strpos( $key, 'art_swap' ) || 'art_pause' === $key || in_array( $key, array( 'art_shape_target', 'art_shape_appear' ), true ) ) {
				$this->assertSame( array( 'advanced', 'controls' ), array( $field['section'], $field['panel'] ), $key );
			} elseif ( 'art_caption_middle' === $key ) {
				$this->assertSame( 'caption', $field['section'], $key );
			} elseif ( 0 === strpos( $key, 'art_bg_' ) ) {
				// A ring's or SVG shape's size and place as a background: with the Background's fields.
				$this->assertSame( array( 'advanced', 'layout' ), array( $field['section'], $field['panel'] ), $key );
			} else {
				$this->assertSame( 'layout', $field['section'], $key );
			}
			$this->assertSame( $field, $fields[ $key ], $key );
		}
		// No other field is Art's, but the Image shadow, shared with the effects whose photos stand apart,
		// and the Background, shared with the effects that can be one.
		foreach ( $fields as $key => $field ) {
			if ( 0 !== strpos( $key, 'art_' ) && 0 !== strpos( $key, 'image_shadow' ) && 'image_hover_zoom' !== $key && 0 !== strpos( $key, 'background' ) && ! empty( $field['effects'] ) ) {
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
		// Out of the stage's box: no room taken from it.
		$this->assertStringNotContainsString( 'shadow-room', $html );
		$this->assertStringNotContainsString( 'shadow-room', file_get_contents( WP_SWIPER_DIR . 'assets/css/art.css' ) );

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
			'art_opening'                   => true,
			'art_ring_opening'              => 60,
			'art_opening_image'             => $image,
			'art_opening_image_size'        => 80,
			'art_opening_image_size_mobile' => 50,
			'art_opening_gap'               => 16,
			'art_opening_gap_mobile'        => 6,
		);
		$html   = $this->render( $values, $this->slides( 3 ) );

		// Beside the stage (the photos' shadow is not for it), hidden until art.js places it.
		$this->assertMatchesRegularExpression( '#</div>\s*<div class="wp-swiper-art__opening" hidden><div class="wp-swiper-art__opening-inner"><img[^>]*class="wp-swiper-art__opening-image"[^>]*></div></div>#', $html );
		$this->assertStringContainsString( '--wp-swiper-art-opening-size:80%', $html );
		$this->assertStringContainsString( '--wp-swiper-art-opening-size-mobile:50%', $html );
		$this->assertSame( array( 'desktop' => 16, 'tablet' => 16, 'mobile' => 6 ), $this->config( $html, 'data-wp-swiper-art' )['openingGap'] );

		// No image chosen (and no text): none, no gap.
		foreach ( array( array( 'art_opening_image' => 0 ) ) as $other ) {
			$html = $this->render( $other + $values, $this->slides( 3 ) );
			$this->assertStringNotContainsString( 'wp-swiper-art__opening', $html );
			$this->assertArrayNotHasKey( 'openingGap', $this->config( $html, 'data-wp-swiper-art' ) );
		}
	}

	public function test_text_in_the_opening() {
		$text = '<h3>Our <a href="https://example.com/shop">shop</a></h3><script>alert(1)</script><p>Open daily</p>';
		$html = $this->render( array( 'effect' => 'art', 'art_opening' => true, 'art_opening_text' => $text, 'art_opening_font_size' => 22 ), $this->slides( 3 ) );

		$this->assertStringContainsString( '<div class="wp-swiper-art__opening" hidden><div class="wp-swiper-art__opening-inner"><div class="wp-swiper-art__opening-text"><h3>Our <a href="https://example.com/shop">shop</a></h3>', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '--wp-swiper-art-opening-font:22px', $html );

		// Saved as a description is (its markup, links too).
		$this->assertSame( 'editor', WP_Swiper_Settings::fields()['art_opening_text']['type'] );
		$this->assertSame( '<h3>Our <a href="https://example.com/shop">shop</a></h3>alert(1)<p>Open daily</p>', WP_Swiper_Settings::sanitize( array( 'art_opening_text' => $text ) )['art_opening_text'] );
		$this->assertSame( '', WP_Swiper_Settings::sanitize( array( 'art_opening_text' => '<p>&nbsp;</p>' ) )['art_opening_text'] );
	}

	public function test_ring_markup() {
		$slides = $this->slides( 3 );
		$html   = $this->render( array( 'effect' => 'art', 'art_ring_count' => 5, 'art_opening' => true, 'art_ring_opening' => 72, 'css_class' => 'my-art', 'css_id' => 'hero' ), $slides );

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
				'height'   => array( 'desktop' => 'px', 'tablet' => 'px', 'mobile' => 'px' ),
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
				'art_opening'             => true,
				'art_ring_opening'        => 0,
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
		$html                 = $this->render( array( 'effect' => 'art', 'caption_tag' => 'h3', 'caption_shadow' => true, 'caption_bg_style' => 'solid', 'caption_bg_color' => 'rgba(0,0,0,0.4)', 'caption_font' => 'custom', 'caption_font_size_mobile' => 12 ), $slides );

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
		// slider.js (left by other tests) needs frontend.js: out of the queue too.
		wp_dequeue_script( 'wp-swiper-slider' );
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

	public function test_height_units_per_device() {
		// One Height for every Style, as a slider's: px, vh, %, Auto (no Size, no grid Height of its own).
		$fields = WP_Swiper_Settings::fields();
		$this->assertArrayNotHasKey( 'art_size', $fields );
		$this->assertArrayNotHasKey( 'art_size_unit', $fields );
		$this->assertArrayNotHasKey( 'art_grid_height_unit', $fields );
		$this->assertSame( 'Height', $fields['art_height']['label'] );
		$this->assertArrayNotHasKey( 'styles', $fields['art_height'] );
		$this->assertArrayNotHasKey( 'styles', $fields['art_height_unit'] );
		$this->assertSame( array( 'px', 'vh', '%', 'auto' ), array_keys( $fields['art_height_unit']['choices'] ) );
		$this->assertSame( array_keys( $fields['height_unit']['choices'] ), array_keys( $fields['art_height_unit']['choices'] ) );
		$this->assertSame( 'art_height_unit', $fields['art_height']['unit'] );
		$this->assertSame( 'art_height', $fields['art_height_unit']['inline_in'] );
		$defaults = WP_Swiper_Settings::defaults();
		$this->assertSame( array( 560, 'px' ), array( $defaults['art_height'], $defaults['art_height_unit'] ) );

		$html = $this->render(
			array(
				'effect'                 => 'art',
				'art_height'             => 80,
				'art_height_unit'        => 'vh',
				'art_height_tablet'      => '60',
				'art_height_unit_tablet' => '%',
				'art_height_unit_mobile' => 'auto',
			)
		);
		// A ring: as wide as tall; % and Auto: the column's width (art.js fits it); the block % of its parent.
		$this->assertStringContainsString( '--wp-swiper-art-size:80vh;--wp-swiper-art-size-tablet:100%;--wp-swiper-art-size-mobile:100%', $html );
		$this->assertStringContainsString( '--wp-swiper-art-block-height:auto;--wp-swiper-art-block-height-tablet:60%;--wp-swiper-art-block-height-mobile:auto', $html );
		$this->assertSame( array( 'desktop' => 'vh', 'tablet' => '%', 'mobile' => 'auto' ), $this->config( $html, 'data-wp-swiper-art' )['height'] );
		$this->assertStringNotContainsString( '--wp-swiper-art-height', $html );

		// px from 160 (a grid 120), vh and % up to 100; an empty unit: the larger screen's.
		$settings = $this->settings( array( 'art_height' => 50, 'art_height_mobile' => '150', 'art_height_unit_tablet' => 'vh', 'art_height_unit_mobile' => 'px' ) );
		$this->assertSame( array( 'desktop' => '160px', 'tablet' => '50vh', 'mobile' => '160px' ), WP_Swiper_Art::sizes( $settings ) );
		$this->assertSame( array( 'desktop' => '120px', 'tablet' => '50vh', 'mobile' => '150px' ), WP_Swiper_Art::grid_heights( $settings ) );
		// An SVG shape: its width its Height times its drawing's proportions.
		$this->assertSame( array( 'desktop' => '300px', 'tablet' => '75vh', 'mobile' => '240px' ), WP_Swiper_Art::sizes( $this->settings( array( 'art_height' => 200, 'art_height_unit_tablet' => 'vh', 'art_height_tablet' => '50', 'art_height_unit_mobile' => 'px' ) ), array( 'desktop' => 1.5, 'tablet' => 1.5, 'mobile' => 1.5 ) ) );

		$clean = WP_Swiper_Settings::sanitize( array( 'art_height_unit' => 'vh', 'art_height_unit_tablet' => 'em', 'art_height_unit_mobile' => '%' ) );
		$this->assertSame( array( 'vh', '', '%' ), array( $clean['art_height_unit'], $clean['art_height_unit_tablet'], $clean['art_height_unit_mobile'] ) );
		$this->assertArrayNotHasKey( 'art_size', $clean );
	}

	public function test_height_from_the_old_size_and_grid_height() {
		$get = function ( $saved ) {
			return WP_Swiper_Settings::get( $this->slider( array(), array( '_version' => 7, 'effect' => 'art' ) + $saved ) );
		};
		// A ring: its Size (its width, it is square) is its height; % of the column: Auto.
		$ring = $get( array( 'art_style' => 'ring', 'art_size' => 480, 'art_size_unit' => 'px', 'art_size_tablet' => '70', 'art_size_unit_tablet' => 'vh', 'art_size_unit_mobile' => '%' ) );
		$this->assertSame( array( 480, 'px' ), array( $ring['art_height'], $ring['art_height_unit'] ) );
		$this->assertSame( array( 70, 'vh' ), array( $ring['art_height_tablet'], $ring['art_height_unit_tablet'] ) );
		$this->assertSame( 'auto', $ring['art_height_unit_mobile'] );
		// The old keys go with the next save (sanitize() keeps the known ones only).
		$this->assertArrayNotHasKey( 'art_size', WP_Swiper_Settings::sanitize( $ring ) );
		// A grid at px: as it was; square photos: the height they made in a 1200px column (8px gap).
		$grid = $get( array( 'art_style' => 'grid-circle', 'art_grid_height' => 420, 'art_grid_height_unit' => 'px' ) );
		$this->assertSame( array( 420, 'px', '', '', '', '' ), array( $grid['art_height'], $grid['art_height_unit'], $grid['art_height_tablet'], $grid['art_height_unit_tablet'], $grid['art_height_mobile'], $grid['art_height_unit_mobile'] ) );
		$square = $get( array( 'art_style' => 'grid-square', 'art_grid_height' => 500, 'art_grid_height_unit' => 'auto', 'art_grid_count' => 9, 'art_grid_columns' => 3, 'art_gap' => 8 ) );
		// Three rows of (1200 - 16) / 3 = 394.67 and two gaps: 1200; tablet (768): 768; phone (390): 390.
		$this->assertSame( array( 1200, 'px' ), array( $square['art_height'], $square['art_height_unit'] ) );
		$this->assertSame( array( 768, '' ), array( $square['art_height_tablet'], $square['art_height_unit_tablet'] ) );
		$this->assertSame( array( 390, '' ), array( $square['art_height_mobile'], $square['art_height_unit_mobile'] ) );
		// Saved with Height already: as saved.
		$this->assertSame( 300, $get( array( 'art_height' => 300, 'art_size' => 480 ) )['art_height'] );
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
		// Right under Height.
		$fields = WP_Swiper_Art::blocks()['layout']['art']['fields'];
		$this->assertSame( array_search( 'art_height', $fields, true ) + 1, array_search( 'art_align', $fields, true ) );

		$clean = WP_Swiper_Settings::sanitize( array( 'effect' => 'art', 'art_align' => 'middle', 'art_align_tablet' => 'left' ) );
		$this->assertSame( array( 'center', 'left', '' ), array( $clean['art_align'], $clean['art_align_tablet'], $clean['art_align_mobile'] ) );
	}

	public function test_lightbox() {
		$slides                   = $this->slides( 2 );
		$slides[1]['description'] = 'Second';
		wp_dequeue_script( 'wp-swiper' );
		$html   = $this->render( array( 'effect' => 'art', 'lightbox' => true, 'art_radius_mobile' => '4', 'lightbox_border_rounded' => true, 'lightbox_radius_tablet' => '20', 'description_lightbox_place' => 'under' ), $slides );
		$config = $this->config( $html, 'data-wp-swiper-art' );

		$this->assertStringContainsString( 'wp-swiper-art--lightbox', $html );
		// Each photo with its full size for it.
		$this->assertSame( 2, substr_count( $html, 'data-wp-swiper-full="' ) );
		$this->assertStringContainsString( ' data-wp-swiper-full="' . wp_get_attachment_image_url( $slides[0]['id'], 'full' ) . '"', $html );
		// Its corners: its own radius (12 by default; tablet's own, mobile inherits it), not Art's Rounded corners.
		$this->assertSame( array( 'desktop' => 12, 'tablet' => 20, 'mobile' => 20 ), $config['lightbox']['radius'] );
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
		// slider.js (left by other tests) needs frontend.js: out of the queue too.
		wp_dequeue_script( 'wp-swiper-slider' );
		// So does slider.css (and thumbs.css, after it) frontend.css.
		wp_dequeue_style( 'wp-swiper-slider' );
		wp_dequeue_style( 'wp-swiper-thumbs' );
		$this->render( array( 'effect' => 'art' ) );
		$this->assertFalse( wp_style_is( 'wp-swiper-slider', 'enqueued' ) );
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
			'art_opening'         => true,
			'art_ring_opening'    => 72,
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
			$this->assertSame( array( 'ring-text', 'grid-circle-text', 'grid-square-text' ), $fields[ $key ]['styles'], $key );
			// A block of its own, Content in the middle, right after Art's.
			$this->assertContains( $key, WP_Swiper_Settings::blocks()['layout']['art_middle']['fields'], $key );
			$this->assertNotContains( $key, WP_Swiper_Settings::blocks()['layout']['art']['fields'], $key );
		}
		$layout = array_keys( WP_Swiper_Settings::blocks()['layout'] );
		$this->assertSame( array( 'art', 'art_middle' ), array_slice( $layout, 0, 2 ) );
		$this->assertSame( 'Content in the middle', WP_Swiper_Settings::blocks()['layout']['art_middle']['title'] );
		// Ring's only: the opening. With a middle photo (Ring, Grid + circle):
		// captions on it only, swapping it.
		foreach ( array( 'art_ring_opening', 'art_opening_image', 'art_opening_text' ) as $key ) {
			$this->assertSame( array( 'ring' ), $fields[ $key ]['styles'], $key );
		}
		// SVG shape: captions on its main window only too; its photos move on window by window.
		$this->assertSame( array( 'ring', 'grid-circle', 'grid-square', 'grid-shapes', 'shape' ), $fields['art_caption_middle']['styles'] );
		$this->assertSame( array( 'ring', 'grid-circle', 'grid-square' ), $fields['art_swap_target']['styles'] );
		$this->assertSame( 'editor', $fields['art_center_text']['type'] );
		// Content in the middle: a checkbox, on by default, before the middle's own settings.
		$this->assertSame( 'checkbox', $fields['art_center']['type'] );
		$this->assertTrue( WP_Swiper_Settings::defaults()['art_center'] );
		$block = WP_Swiper_Settings::blocks()['layout']['art_middle']['fields'];
		$this->assertSame( 'art_center', $block[0] );
		$this->assertSame( 'art_center_image', $block[1] );
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
			'art_height'                  => 420,
			'art_height_unit'             => 'px',
			'art_height_unit_tablet'      => 'auto',
			'art_height_mobile'           => '80',
			'art_height_unit_mobile'      => '%',
			'art_ring_center'             => 64,
			// Ring's own: no opening in a grid.
			'art_opening'                 => true,
			'art_ring_opening'            => 72,
			'art_opening_text'            => '<p>Opening</p>',
			'art_caption_middle'          => true,
			'art_swap_target'             => 'middle',
		);
		$html   = $this->render( $values, $this->slides( 3, array( 'caption' => '<p>Hi</p>' ) ) );
		$config = $this->config( $html, 'data-wp-swiper-art' );

		$this->assertStringContainsString( 'class="wp-swiper-art wp-swiper-art--grid-circle wp-swiper-art--', $html );
		// As wide as the column (art.css).
		$this->assertStringContainsString( 'wp-swiper-art--wide', $html );
		$this->assertTrue( $config['wide'] );
		$this->assertSame( array( 'desktop' => 'px', 'tablet' => 'auto', 'mobile' => '%' ), $config['height'] );
		$this->assertSame( 'grid-circle', $config['style'] );
		// Its photos and columns per device (tablet and mobile: their own, else the larger screen's).
		$this->assertSame( array( 'desktop' => 8, 'tablet' => 8, 'mobile' => 5 ), $config['cells'] );
		$this->assertSame( array( 'desktop' => 3, 'tablet' => 4, 'mobile' => 2 ), $config['columns'] );
		// Its Height per device: px; Auto (art.js fills the column); % (all of the block, the block that % of its parent).
		$this->assertArrayNotHasKey( 'square', $config );
		$this->assertSame( 1, preg_match( '/class="wp-swiper-art [^"]*" style="([^"]*)"/', $html, $match ) );
		$style = html_entity_decode( $match[1] );
		$this->assertStringContainsString( '--wp-swiper-art-height:420px;--wp-swiper-art-height-tablet:auto;--wp-swiper-art-height-mobile:100%', $style );
		$this->assertStringContainsString( '--wp-swiper-art-block-height:auto;--wp-swiper-art-block-height-tablet:auto;--wp-swiper-art-block-height-mobile:80%', $style );
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
		foreach ( array( 'art_grid_count', 'art_grid_columns' ) as $key ) {
			$this->assertSame( array( 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text' ), $fields[ $key ]['styles'], $key );
			$this->assertTrue( $fields[ $key ]['responsive'], $key );
		}
		// The rings' own and SVG shape's: their Alignment (a grid is as wide as the column).
		foreach ( array( 'art_align' ) as $key ) {
			$this->assertSame( array( 'ring', 'ring-text', 'shape' ), $fields[ $key ]['styles'], $key );
		}
		$this->assertArrayNotHasKey( 'art_grid_shape', $fields );
		$defaults = WP_Swiper_Settings::defaults();
		$this->assertSame( 9, $defaults['art_grid_count'] );
		$this->assertSame( 3, $defaults['art_grid_columns'] );
		$this->assertArrayNotHasKey( 'art_grid_height', $fields );
		// After the ring's Photos around: Photos, Columns, the Gap.
		$block = WP_Swiper_Settings::blocks()['layout']['art']['fields'];
		$at    = array_search( 'art_ring_count', $block, true );
		$this->assertSame( array( 'art_ring_count', 'art_grid_count', 'art_grid_columns', 'art_gap' ), array_slice( $block, $at, 4 ) );
		// The middle circle's size for it too (dragged in the preview).
		$this->assertContains( 'grid-circle', $fields['art_ring_center']['styles'] );

		$clean = WP_Swiper_Settings::sanitize(
			array(
				'art_grid_count'          => '99',
				'art_grid_count_mobile'   => '1',
				'art_grid_columns'        => '12',
				'art_grid_columns_tablet' => '1',
				'art_grid_columns_mobile' => '',
				'art_grid_shape'          => 'wide',
			)
		);
		$this->assertSame( 36, (int) $clean['art_grid_count'] );
		$this->assertSame( 2, (int) $clean['art_grid_count_mobile'] );
		$this->assertSame( 6, (int) $clean['art_grid_columns'] );
		$this->assertSame( 2, (int) $clean['art_grid_columns_tablet'] );
		$this->assertSame( '', $clean['art_grid_columns_mobile'] );
		$this->assertArrayNotHasKey( 'art_grid_shape', $clean );
	}

	public function test_image_border_over_the_shadow_in_the_look_block() {
		$blocks = WP_Swiper_Settings::blocks()['layout'];
		// Not in the Art block: in Look, right before Zoom on hover and the Image shadow.
		foreach ( array( 'art_border', 'art_border_width', 'art_border_color' ) as $key ) {
			$this->assertNotContains( $key, $blocks['art']['fields'], $key );
		}
		$look = $blocks['look']['fields'];
		$at   = array_search( 'art_border', $look, true );
		$this->assertSame( array( 'art_border', 'art_border_width', 'art_border_color', 'image_hover_zoom', 'image_shadow' ), array_slice( $look, $at, 5 ) );

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
		$this->assertStringContainsString( 'data-field="art_center_text" data-effects="art" data-styles="ring-text grid-circle-text grid-square-text"', $html );
		$this->assertStringContainsString( 'data-field="art_style" data-effects="art">', $html );
	}

	public function test_rounded_corners_a_switch_with_the_radius_under_it() {
		$fields = WP_Swiper_Settings::fields();
		// As a slider's: a switch (on by default, as Art always had them), the radius under it.
		$this->assertSame( 'checkbox', $fields['art_rounded']['type'] );
		$this->assertSame( 'Rounded corners', $fields['art_rounded']['label'] );
		$this->assertTrue( WP_Swiper_Settings::defaults()['art_rounded'] );
		$this->assertSame( 'Radius (px)', $fields['art_radius']['label'] );
		$this->assertSame( $fields['art_radius']['styles'], $fields['art_rounded']['styles'] );
		$look = WP_Swiper_Settings::blocks()['layout']['look']['fields'];
		$this->assertSame( array_search( 'art_rounded', $look, true ) + 1, array_search( 'art_radius', $look, true ) );

		// Off: no radius on the photos, nor on the lightbox's that take theirs.
		$on  = $this->config( $this->render( array( 'effect' => 'art', 'art_radius_mobile' => '6' ) ), 'data-wp-swiper-art' );
		$off = $this->config( $this->render( array( 'effect' => 'art', 'art_rounded' => false, 'art_radius_mobile' => '6' ) ), 'data-wp-swiper-art' );
		$this->assertSame( array( 'desktop' => 14, 'tablet' => 14, 'mobile' => 6 ), $on['radius'] );
		$this->assertSame( array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ), $off['radius'] );
		$this->assertSame( 0, (int) WP_Swiper_Settings::apply_rounded( $this->settings( array( 'art_rounded' => false, 'art_radius' => 20 ) ) )['art_radius'] );
		// A slider's own Rounded corners leave Art's as they are.
		$this->assertSame( 20, WP_Swiper_Settings::apply_rounded( $this->settings( array( 'border_rounded' => false, 'art_radius' => 20 ) ) )['art_radius'] );

		// Saved before the switch: on where a radius above 0 was (or none saved,
		// the default's), off where it was 0 everywhere (the default radius kept for later).
		$get = function ( $saved ) {
			return WP_Swiper_Settings::get( $this->slider( array(), $saved ) );
		};
		$this->assertTrue( $get( array( '_version' => 7, 'effect' => 'art', 'art_radius' => 10 ) )['art_rounded'] );
		$this->assertTrue( $get( array( '_version' => 7, 'effect' => 'art', 'border_radius' => 0 ) )['art_rounded'] );
		$this->assertTrue( $get( array( '_version' => 7, 'effect' => 'art', 'art_radius' => 0, 'art_radius_mobile' => 8 ) )['art_rounded'] );
		$zero = $get( array( '_version' => 7, 'effect' => 'art', 'art_radius' => 0, 'art_radius_tablet' => '', 'art_radius_mobile' => '0' ) );
		$this->assertFalse( $zero['art_rounded'] );
		$this->assertSame( 14, $zero['art_radius'] );
		$this->assertSame( '', $zero['art_radius_mobile'] );
		// Turned off by hand: stays off.
		$this->assertFalse( $get( array( '_version' => 7, 'effect' => 'art', 'art_rounded' => false, 'art_radius' => 10 ) )['art_rounded'] );
		// In the other effects' Layout values too.
		$layouts = $get( array( '_version' => 7, 'effect' => 'slide', 'layouts' => array( 'art' => array( 'art_radius' => 0 ) ) ) )['layouts'];
		$this->assertFalse( $layouts['art']['art_rounded'] );
	}

	public function test_ring_opening_a_block_with_its_switch() {
		$fields = WP_Swiper_Settings::fields();
		$blocks = WP_Swiper_Settings::blocks()['layout'];
		// A block of its own after Content in the middle: the switch, then the size and what is in it.
		$this->assertSame( array( 'art', 'art_middle', 'art_opening' ), array_slice( array_keys( $blocks ), 0, 3 ) );
		$this->assertSame( 'Opening', $blocks['art_opening']['title'] );
		$this->assertSame( array( 'art_opening', 'art_ring_opening', 'art_opening_image', 'art_opening_image_size', 'art_opening_text', 'art_opening_font_size', 'art_opening_gap' ), $blocks['art_opening']['fields'] );
		foreach ( $blocks['art_opening']['fields'] as $key ) {
			$this->assertNotContains( $key, $blocks['art']['fields'], $key );
			$this->assertSame( array( 'ring' ), $fields[ $key ]['styles'], $key );
		}
		// Off by default (the photos all around); on, 90 degrees to start with.
		$this->assertSame( 'checkbox', $fields['art_opening']['type'] );
		$this->assertFalse( WP_Swiper_Settings::defaults()['art_opening'] );
		$this->assertSame( 90, WP_Swiper_Settings::defaults()['art_ring_opening'] );

		// Off: no opening and nothing in it, whatever the size says.
		$values = array( 'effect' => 'art', 'art_ring_opening' => 80, 'art_opening_text' => '<p>Hi</p>' );
		$off    = $this->render( $values );
		$this->assertSame( array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ), $this->config( $off, 'data-wp-swiper-art' )['opening'] );
		$this->assertStringNotContainsString( 'wp-swiper-art__opening', $off );
		$on = $this->render( array( 'art_opening' => true ) + $values );
		$this->assertSame( array( 'desktop' => 80, 'tablet' => 80, 'mobile' => 80 ), $this->config( $on, 'data-wp-swiper-art' )['opening'] );
		$this->assertStringContainsString( 'wp-swiper-art__opening-text', $on );

		// Saved before the switch: on where an Opening above 0 was; else off (the size the default again).
		$get = function ( $saved ) {
			return WP_Swiper_Settings::get( $this->slider( array(), $saved ) );
		};
		$open = $get( array( '_version' => 7, 'effect' => 'art', 'art_ring_opening' => 72 ) );
		$this->assertTrue( $open['art_opening'] );
		$this->assertSame( 72, $open['art_ring_opening'] );
		$this->assertTrue( $get( array( '_version' => 7, 'effect' => 'art', 'art_ring_opening' => 0, 'art_ring_opening_mobile' => '60' ) )['art_opening'] );
		$closed = $get( array( '_version' => 7, 'effect' => 'art', 'art_ring_opening' => 0, 'art_ring_opening_tablet' => '0' ) );
		$this->assertFalse( $closed['art_opening'] );
		$this->assertSame( 90, $closed['art_ring_opening'] );
		$this->assertSame( '', $closed['art_ring_opening_tablet'] );
		// Never saved (the old default, 0): off.
		$this->assertFalse( $get( array( '_version' => 7, 'effect' => 'art' ) )['art_opening'] );
		// Turned off by hand: stays off; and in the other effects' Layout values too.
		$this->assertFalse( $get( array( '_version' => 7, 'effect' => 'art', 'art_opening' => false, 'art_ring_opening' => 72 ) )['art_opening'] );
		$this->assertTrue( $get( array( '_version' => 7, 'effect' => 'slide', 'layouts' => array( 'art' => array( 'art_ring_opening' => 50 ) ) ) )['layouts']['art']['art_opening'] );
	}

	public function test_an_image_and_text_in_the_opening_together() {
		// As Ring + text's middle: both, the image over the text; no In the opening choice any more.
		$this->assertArrayNotHasKey( 'art_opening_content', WP_Swiper_Settings::fields() );
		$this->assertSame( 'Image in the opening', WP_Swiper_Settings::fields()['art_opening_image']['label'] );
		$this->assertSame( 'Text in the opening', WP_Swiper_Settings::fields()['art_opening_text']['label'] );
		$html = $this->render(
			array(
				'effect'                 => 'art',
				'art_opening'            => true,
				'art_opening_image'      => $this->image( 400, 200 ),
				'art_opening_image_size' => 70,
				'art_opening_text'       => '<p>Shop</p>',
				'art_opening_font_size'  => 20,
			),
			$this->slides( 3 )
		);
		$this->assertMatchesRegularExpression( '#<div class="wp-swiper-art__opening" hidden><div class="wp-swiper-art__opening-inner"><img[^>]*class="wp-swiper-art__opening-image"[^>]*><div class="wp-swiper-art__opening-text"><p>Shop</p></div></div></div>#', $html );
		$this->assertStringContainsString( '--wp-swiper-art-opening-size:70%', $html );
		$this->assertStringContainsString( '--wp-swiper-art-opening-font:20px', $html );
		$this->assertArrayHasKey( 'openingGap', $this->config( $html, 'data-wp-swiper-art' ) );

		// Saved with In the opening: the one it showed (the other cleared), none for Nothing.
		$get = function ( $content ) {
			return WP_Swiper_Settings::get(
				$this->slider(
					array(),
					array(
						'_version'            => 7,
						'effect'              => 'art',
						'art_ring_opening'    => 60,
						'art_opening_content' => $content,
						'art_opening_image'   => 12,
						'art_opening_text'    => '<p>Old</p>',
						'layouts'             => array(
							'art' => array(
								'art_opening_content' => 'text',
								'art_opening_image'   => 12,
								'art_opening_text'    => '<p>Old</p>',
							),
						),
					)
				)
			);
		};
		$image = $get( 'image' );
		$this->assertSame( 12, $image['art_opening_image'] );
		$this->assertSame( '', $image['art_opening_text'] );
		$this->assertArrayNotHasKey( 'art_opening_content', $image );
		$text = $get( 'text' );
		$this->assertSame( 0, $text['art_opening_image'] );
		$this->assertSame( '<p>Old</p>', $text['art_opening_text'] );
		$none = $get( 'none' );
		$this->assertSame( 0, $none['art_opening_image'] );
		$this->assertSame( '', $none['art_opening_text'] );
		// The other effects' Layout values too.
		$this->assertSame( 0, $image['layouts']['art']['art_opening_image'] );
		$this->assertSame( '<p>Old</p>', $image['layouts']['art']['art_opening_text'] );
		// Saved since: as saved, both.
		$both = WP_Swiper_Settings::get( $this->slider( array(), array( '_version' => 7, 'art_opening_image' => 12, 'art_opening_text' => '<p>New</p>' ) ) );
		$this->assertSame( 12, $both['art_opening_image'] );
		$this->assertSame( '<p>New</p>', $both['art_opening_text'] );
	}

	public function test_grid_circle_with_text_in_the_circle() {
		// After Grid + circle, SVG shape last; its icon there.
		$styles = array_keys( WP_Swiper_Art::styles() );
		$this->assertSame( array( 'ring', 'ring-text', 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text', 'grid-shapes', 'shape' ), $styles );
		$this->assertSame( 'Grid + circle + text', WP_Swiper_Art::styles()['grid-circle-text'] );
		$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/art/grid-circle-text.svg' );
		$this->assertStringContainsString( 'fill="currentColor"', $svg );
		$this->assertDoesNotMatchRegularExpression( '~<script|<foreignObject|<style|\son\w+=|href=~i', $svg );

		// The grid's settings, the circle's size and corners, and Content in the middle (as Ring + text);
		// not those of a photo in the middle.
		$fields = WP_Swiper_Settings::fields();
		foreach ( array( 'art_grid_count', 'art_grid_columns', 'art_ring_center', 'art_gap', 'art_rounded', 'art_radius', 'art_center', 'art_center_text', 'art_center_bg' ) as $key ) {
			$this->assertContains( 'grid-circle-text', $fields[ $key ]['styles'], $key );
		}
		foreach ( array( 'art_caption_middle', 'art_swap_target', 'art_ring_count', 'art_align', 'art_opening' ) as $key ) {
			$this->assertNotContains( 'grid-circle-text', $fields[ $key ]['styles'], $key );
		}
		$this->assertFalse( WP_Swiper_Art::has_middle_photo( 'grid-circle-text' ) );

		$values = array(
			'effect'           => 'art',
			'art_style'        => 'grid-circle-text',
			'art_grid_count'   => 8,
			'art_center_text'  => '<h3>Hello</h3><p>World</p>',
			'art_center_bg'    => 'rgba(255,255,255,0.8)',
			'art_caption_middle' => true,
			'art_swap_target'  => 'middle',
		);
		$html   = $this->render( $values, $this->slides( 10 ) );
		$config = $this->config( $html, 'data-wp-swiper-art' );
		// As wide as the column, a grid's config; the grid's photos only (no photo in the circle).
		$this->assertSame( 1, preg_match( '/<div class="wp-swiper-art wp-swiper-art--grid-circle-text[^"]* wp-swiper-art--wide[ "]/', $html ) );
		$this->assertSame( 'grid-circle-text', $config['style'] );
		$this->assertSame( array( 'desktop' => 8, 'tablet' => 8, 'mobile' => 8 ), $config['cells'] );
		$this->assertSame( array( 'desktop' => 8, 'tablet' => 8, 'mobile' => 8 ), WP_Swiper_Art::counts( $this->settings( $values ) ) );
		$this->assertSame( 8, substr_count( $html, 'class="wp-swiper-art__piece" data-piece=' ) );
		// Its image and text in the circle, as Ring + text's; captions and swapping not of a middle photo.
		$this->assertStringContainsString( '<div class="wp-swiper-art__center" hidden><div class="wp-swiper-art__center-inner"><div class="wp-swiper-art__center-text"><h3>Hello</h3><p>World</p></div></div></div>', $html );
		$this->assertStringContainsString( '--wp-swiper-art-center-bg:rgba(255,255,255,0.8)', $html );
		$this->assertStringNotContainsString( 'wp-swiper-art--caption-middle', $html );
		$this->assertSame( 'any', $config['swap']['target'] );
		// Content in the middle off: the circle empty.
		$this->assertStringNotContainsString( 'wp-swiper-art__center', $this->render( array( 'art_center' => false ) + $values, $this->slides( 10 ) ) );
		// Grid + circle keeps its photo in the circle: one piece more.
		$photo = array( 'art_style' => 'grid-circle' ) + $values;
		$this->assertSame( 9, substr_count( $this->render( $photo, $this->slides( 10 ) ), 'class="wp-swiper-art__piece" data-piece=' ) );
		$this->assertStringNotContainsString( 'wp-swiper-art__center', $this->render( $photo, $this->slides( 10 ) ) );
	}

	public function test_grid_square_with_a_photo_or_text_in_the_square() {
		$styles = WP_Swiper_Art::styles();
		$this->assertSame( 'Grid + square', $styles['grid-square'] );
		$this->assertSame( 'Grid + square + text', $styles['grid-square-text'] );
		// SVG shape last.
		$this->assertSame( 'shape', array_key_last( $styles ) );
		foreach ( array( 'grid-square', 'grid-square-text' ) as $style ) {
			$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/art/' . $style . '.svg' );
			$this->assertStringContainsString( 'fill="currentColor"', $svg, $style );
			$this->assertDoesNotMatchRegularExpression( '~<script|<foreignObject|<style|\son\w+=|href=~i', $svg, $style );
		}

		$fields = WP_Swiper_Settings::fields();
		// Both: a grid's settings, the middle's size and corners.
		foreach ( array( 'art_grid_count', 'art_grid_columns', 'art_ring_center', 'art_gap', 'art_rounded', 'art_radius' ) as $key ) {
			$this->assertContains( 'grid-square', $fields[ $key ]['styles'], $key );
			$this->assertContains( 'grid-square-text', $fields[ $key ]['styles'], $key );
		}
		// A photo in the square: its captions and swapping, as Grid + circle's; with text, Content in the middle.
		foreach ( array( 'art_caption_middle', 'art_swap_target' ) as $key ) {
			$this->assertContains( 'grid-square', $fields[ $key ]['styles'], $key );
			$this->assertNotContains( 'grid-square-text', $fields[ $key ]['styles'], $key );
		}
		foreach ( array( 'art_center', 'art_center_text', 'art_center_bg' ) as $key ) {
			$this->assertContains( 'grid-square-text', $fields[ $key ]['styles'], $key );
			$this->assertNotContains( 'grid-square', $fields[ $key ]['styles'], $key );
		}
		$this->assertTrue( WP_Swiper_Art::has_middle_photo( 'grid-square' ) );
		$this->assertFalse( WP_Swiper_Art::has_middle_photo( 'grid-square-text' ) );

		$values = array(
			'effect'             => 'art',
			'art_style'          => 'grid-square',
			'art_grid_count'     => 8,
			'art_center_text'    => '<p>In the square</p>',
			'art_caption_middle' => true,
			'art_swap_target'    => 'middle',
		);
		// A photo in the square: one piece more than the grid's, as wide as the column.
		$photo  = $this->render( $values, $this->slides( 10 ) );
		$config = $this->config( $photo, 'data-wp-swiper-art' );
		$this->assertSame( 1, preg_match( '/<div class="wp-swiper-art wp-swiper-art--grid-square[ "][^"]* wp-swiper-art--wide[ "]/', $photo ) );
		$this->assertSame( array( 'desktop' => 8, 'tablet' => 8, 'mobile' => 8 ), $config['cells'] );
		$this->assertSame( 9, substr_count( $photo, 'class="wp-swiper-art__piece" data-piece=' ) );
		$this->assertStringContainsString( 'wp-swiper-art--caption-middle', $photo );
		$this->assertSame( 'middle', $config['swap']['target'] );
		$this->assertStringNotContainsString( 'wp-swiper-art__center', $photo );
		// Text in the square: the grid's pieces only, its image and text as Ring + text's.
		$text = $this->render( array( 'art_style' => 'grid-square-text' ) + $values, $this->slides( 10 ) );
		$this->assertSame( 8, substr_count( $text, 'class="wp-swiper-art__piece" data-piece=' ) );
		$this->assertStringContainsString( '<div class="wp-swiper-art__center" hidden><div class="wp-swiper-art__center-inner"><div class="wp-swiper-art__center-text"><p>In the square</p></div></div></div>', $text );
		$this->assertStringNotContainsString( 'wp-swiper-art--caption-middle', $text );
		$this->assertSame( 'any', $this->config( $text, 'data-wp-swiper-art' )['swap']['target'] );
		$this->assertSame( array( 'desktop' => 8, 'tablet' => 8, 'mobile' => 8 ), WP_Swiper_Art::counts( $this->settings( array( 'art_style' => 'grid-square-text' ) + $values ) ) );
		$this->assertSame( array( 'desktop' => 9, 'tablet' => 9, 'mobile' => 9 ), WP_Swiper_Art::counts( $this->settings( $values ) ) );
		// The preview's hint says a square's edge drags too.
		$this->assertStringContainsString( "Drag the edge of the middle circle or square to resize it.", file_get_contents( WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php' ) );
	}

	/* Grid shapes ------------------------------------------------------ */

	public function test_grid_shapes_is_a_style_of_windows_with_its_icon() {
		$this->assertSame( 'Grid shapes', WP_Swiper_Art::styles()['grid-shapes'] );
		$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/art/grid-shapes.svg' );
		$this->assertStringContainsString( 'fill="currentColor"', $svg );
		$this->assertDoesNotMatchRegularExpression( '~<script|<foreignObject|<style|\son\w+=|href=~i', $svg );
		// As wide as the column (it stretches), its first shape the main one, windows as SVG shape's.
		$this->assertTrue( WP_Swiper_Art::is_wide( $this->settings( array( 'art_style' => 'grid-shapes' ) ) ) );
		$this->assertTrue( WP_Swiper_Art::has_middle_photo( 'grid-shapes' ) );
		$this->assertTrue( WP_Swiper_Art::has_windows( 'grid-shapes' ) );
		$this->assertTrue( WP_Swiper_Art::has_windows( 'shape' ) );
		$this->assertFalse( WP_Swiper_Art::has_windows( 'grid-square' ) );
	}

	public function test_grid_shapes_layout_is_read_and_numbered() {
		$this->assertSame(
			array(
				'columns' => 2,
				'rows'    => 2,
				'cells'   => array( 5, 5, -1, 9 ),
				'radii'   => array( 9 => 50, 3 => 20 ),
				'corners' => array(),
			),
			WP_Swiper_Art::parse_cells( ' 2x2:5,5,-1,9;9=50,3=20 ' )
		);
		// Without radii; a radius over 100 is 100.
		$this->assertSame( array(), WP_Swiper_Art::parse_cells( '2x1:0,1' )['radii'] );
		$this->assertSame( array( 0 => 100 ), WP_Swiper_Art::parse_cells( '2x1:0,1;0=250' )['radii'] );
		foreach ( array( '', 'nonsense', '2x2:0,1,2', '13x1:' . implode( ',', range( 0, 12 ) ), '0x2:', '2x1:0,-2', '2x1:0,144', '2x1:0,1;a=5', '2x1:0,1;<b>' ) as $bad ) {
			$this->assertNull( WP_Swiper_Art::parse_cells( $bad ), $bad );
		}
		// Kept numbered from 0 in the order the shapes first come; the radii of shapes left out dropped.
		$this->assertSame( '2x2:0,0,-1,1;1=50', WP_Swiper_Art::sanitize_cells( '2x2:5,5,-1,9;9=50,3=20' ) );
		$this->assertSame( '', WP_Swiper_Art::sanitize_cells( '2x2:0,1' ) );
		$this->assertSame( '', WP_Swiper_Art::sanitize_cells( '' ) );
		// So when the slider is saved.
		$saved = WP_Swiper_Settings::sanitize( array( 'effect' => 'art', 'art_style' => 'grid-shapes', 'art_cells' => '2x2:7,7,3,3' ) );
		$this->assertSame( '2x2:0,0,1,1', $saved['art_cells'] );
		$this->assertSame( '', WP_Swiper_Settings::sanitize( array( 'effect' => 'art', 'art_cells' => '"><script>' ) )['art_cells'] );
	}

	public function test_grid_shapes_layout_fits_its_columns_and_rows() {
		// None drawn: the default one, on 4 x 4 a few shapes (two of them round)...
		$grid = WP_Swiper_Art::cells( $this->settings( array( 'art_style' => 'grid-shapes' ) ) );
		$this->assertSame( array( 4, 4, 8 ), array( $grid['columns'], $grid['rows'], $grid['count'] ) );
		$this->assertSame( array( 1 => 100, 7 => 100 ), $grid['radii'] );
		// ...else each cell a shape of its own.
		$grid = WP_Swiper_Art::cells( $this->settings( array( 'art_style' => 'grid-shapes', 'art_cells_columns' => 3, 'art_cells_rows' => 2 ) ) );
		$this->assertSame( range( 0, 5 ), $grid['cells'] );
		// A drawn one at other Columns and Rows: kept where it reaches, each new cell its own shape.
		$grid = WP_Swiper_Art::cells( $this->settings( array( 'art_cells' => '2x2:0,0,1,-1;1=40', 'art_cells_columns' => 3, 'art_cells_rows' => 3 ) ) );
		$this->assertSame( array( 0, 0, 1, 2, -1, 3, 4, 5, 6 ), $grid['cells'] );
		$this->assertSame( array( 2 => 40 ), $grid['radii'] );
		$this->assertSame( 7, $grid['count'] );
		// Fewer: cut off.
		$grid = WP_Swiper_Art::cells( $this->settings( array( 'art_cells' => '3x2:0,1,2,3,3,3', 'art_cells_columns' => 2, 'art_cells_rows' => 2 ) ) );
		$this->assertSame( array( 0, 1, 2, 2 ), $grid['cells'] );
		// Columns and Rows within 2–12.
		$grid = WP_Swiper_Art::cells( $this->settings( array( 'art_cells_columns' => 40, 'art_cells_rows' => 1 ) ) );
		$this->assertSame( array( 12, 2 ), array( $grid['columns'], $grid['rows'] ) );
	}

	public function test_grid_shapes_single_corners_have_their_own_radius() {
		// After the shapes' radii (none: an empty part), by their place on the grid of cells and gaps.
		$grid = WP_Swiper_Art::parse_cells( '3x3:0,0,0,0,1,2,0,2,2;;2.2=100,5.0=0' );
		$this->assertSame( array( '2.2' => 100, '5.0' => 0 ), $grid['corners'] );
		$this->assertSame( array(), $grid['radii'] );
		$this->assertSame( array( '3.3' => 100 ), WP_Swiper_Art::parse_cells( '2x2:0,1,2,3;0=40;3.3=250' )['corners'] );
		// A place past the grid (2 x columns - 1) left out.
		$this->assertSame( array( '3.3' => 5 ), WP_Swiper_Art::parse_cells( '2x2:0,1,2,3;;3.3=5,4.0=5' )['corners'] );
		foreach ( array( '2x2:0,1,2,3;;3=5', '2x2:0,1,2,3;;3.3', '2x2:0,1,2,3;;a.b=5', '2x2:0,1,2,3;;3.3=5;1' ) as $bad ) {
			$this->assertNull( WP_Swiper_Art::parse_cells( $bad ), $bad );
		}
		// Kept in order, the shapes' radii between (empty: none).
		$this->assertSame( '3x3:0,0,0,0,1,2,0,2,2;;2.2=100,5.0=0', WP_Swiper_Art::sanitize_cells( '3x3:9,9,9,9,4,7,9,7,7;;5.0=0,2.2=100' ) );
		$this->assertSame( '2x2:0,1,2,3;1=40;3.3=0', WP_Swiper_Art::sanitize_cells( '2x2:0,5,2,3;5=40;3.3=0' ) );
		// With fewer columns: the corners the grid still reaches.
		$grid = WP_Swiper_Art::cells( $this->settings( array( 'art_cells' => '3x3:0,0,0,0,1,2,0,2,2;;2.2=100,5.0=0', 'art_cells_columns' => 2, 'art_cells_rows' => 3 ) ) );
		$this->assertSame( array( '2.2' => 100 ), $grid['corners'] );
		// In the config, an object (art.js reads it by place).
		$config = $this->config( $this->render( array( 'effect' => 'art', 'art_style' => 'grid-shapes', 'art_cells' => '3x3:0,0,0,0,1,2,0,2,2;;2.2=100', 'art_cells_columns' => 3, 'art_cells_rows' => 3 ) ), 'data-wp-swiper-art' );
		$this->assertSame( array( '2.2' => 100 ), $config['grid']['corners'] );
		$this->assertStringContainsString( '&quot;corners&quot;:{}', $this->render( array( 'effect' => 'art', 'art_style' => 'grid-shapes' ) ) );
		// The preview's words for it.
		$this->assertSame( 'As the shape', WP_Swiper_Preview::preview_words()['asShape'] );
	}

	public function test_grid_shapes_markup_and_config() {
		$values = array(
			'effect'            => 'art',
			'art_style'         => 'grid-shapes',
			'art_cells'         => '3x2:0,0,1,2,-1,1;0=100',
			'art_cells_columns' => 3,
			'art_cells_rows'    => 2,
		);
		$html   = $this->render( $values, $this->slides( 5 ) );
		$config = $this->config( $html, 'data-wp-swiper-art' );
		$this->assertSame(
			array(
				'columns' => 3,
				'rows'    => 2,
				'cells'   => array( 0, 0, 1, 2, -1, 1 ),
				'radii'   => array( 0 => 100 ),
				'corners' => array(),
			),
			$config['grid']
		);
		$this->assertSame( 'grid-shapes', $config['style'] );
		$this->assertTrue( $config['wide'] );
		// Not the Grid + circle's photos and columns.
		$this->assertArrayNotHasKey( 'columns', $config );
		$this->assertArrayNotHasKey( 'cells', $config );
		// A piece per shape; as wide as the column, its Height.
		$this->assertSame( 3, substr_count( $html, 'class="wp-swiper-art__piece"' ) );
		$this->assertSame( 1, preg_match( '/<div class="wp-swiper-art wp-swiper-art--grid-shapes[ "][^"]* wp-swiper-art--wide[ "]/', $html ) );
		$this->assertSame( array( 'desktop' => 3, 'tablet' => 3, 'mobile' => 3 ), WP_Swiper_Art::counts( $this->settings( $values ) ) );
		// No radii: an object all the same (art.js reads it by shape).
		$this->assertStringContainsString( '&quot;radii&quot;:{}', $this->render( array( 'art_cells' => '3x2:0,0,1,1,2,2' ) + $values ) );

		// What changes as SVG shape's: one photo through all windows, in a wave.
		$one    = $this->render( $values + array( 'art_shape_target' => 'one', 'art_shape_appear' => 'ltr' ), $this->slides( 5 ) );
		$config = $this->config( $one, 'data-wp-swiper-art' );
		$this->assertTrue( $config['one'] );
		$this->assertSame( 'one', $config['swap']['target'] );
		$this->assertSame( 'ltr', $config['appear'] );
		$this->assertSame( 1, preg_match( '/<div class="wp-swiper-art [^"]*wp-swiper-art--one[ "]/', $one ) );
		// Not for the other Styles.
		$ring = $this->config( $this->render( array( 'effect' => 'art', 'art_shape_target' => 'one' ) ), 'data-wp-swiper-art' );
		$this->assertArrayNotHasKey( 'one', $ring );
		$this->assertArrayNotHasKey( 'grid', $ring );
	}

	public function test_grid_shapes_fields() {
		$fields = WP_Swiper_Settings::fields();
		// The layout: kept with the Style, set by the preview only.
		$this->assertSame( 'cells', $fields['art_cells']['type'] );
		$this->assertSame( 'art_style', $fields['art_cells']['inline_in'] );
		$this->assertTrue( $fields['art_cells']['inline_hidden'] );
		// Columns and Rows side by side, one for every device.
		foreach ( array( 'art_cells_columns', 'art_cells_rows' ) as $key ) {
			$this->assertSame( array( 'grid-shapes' ), $fields[ $key ]['styles'], $key );
			$this->assertSame( 'art_cells', $fields[ $key ]['group'], $key );
			$this->assertArrayNotHasKey( 'responsive', $fields[ $key ], $key );
		}
		// Gap and corners as the grids'; captions, What changes and Appearance as SVG shape's.
		foreach ( array( 'art_gap', 'art_rounded', 'art_radius', 'art_caption_middle', 'art_shape_target', 'art_shape_appear' ) as $key ) {
			$this->assertContains( 'grid-shapes', $fields[ $key ]['styles'], $key );
		}
		foreach ( array( 'art_grid_count', 'art_grid_columns', 'art_ring_center', 'art_swap_target', 'art_center', 'art_shape', 'art_align' ) as $key ) {
			$this->assertNotContains( 'grid-shapes', $fields[ $key ]['styles'], $key );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( null, array( 'effect' => 'art', 'art_style' => 'grid-shapes', 'art_cells' => '2x2:0,0,1,1' ) ) ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( '<input type="hidden" id="wp-swiper-art_cells" name="wp_swiper_settings[art_cells]" value="2x2:0,0,1,1">', $html );
		$this->assertStringContainsString( 'data-field="art_cells_columns" data-effects="art" data-styles="grid-shapes"', $html );
		// The preview's hint and its words.
		$this->assertStringContainsString( 'data-effect="art-cells"', file_get_contents( WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php' ) );
		$this->assertSame( 'Split into cells', WP_Swiper_Preview::preview_words()['split'] );
	}

	public function test_art_as_a_background_with_any_style() {
		$on = array( 'effect' => 'art', 'background' => true, 'background_class' => 'hero' );
		foreach ( array_keys( WP_Swiper_Art::styles() ) as $style ) {
			$this->assertTrue( WP_Swiper_Settings::is_background( $this->settings( array( 'art_style' => $style ) + $on ) ), $style );
		}
		$fields = WP_Swiper_Settings::fields();
		foreach ( array( 'background', 'background_class', 'background_overlay_color' ) as $key ) {
			$this->assertContains( 'art', $fields[ $key ]['effects'], $key );
			$this->assertArrayNotHasKey( 'styles', $fields[ $key ], $key );
		}
		// A ring's or SVG shape's size and place (a grid stretches): per device, in the Background block.
		foreach ( array( 'art_bg_size' => 'contain', 'art_bg_position' => 'center center' ) as $key => $default ) {
			$this->assertSame( array( 'ring', 'ring-text', 'shape' ), $fields[ $key ]['styles'], $key );
			$this->assertTrue( $fields[ $key ]['responsive'], $key );
			$this->assertSame( $default, $fields[ $key ]['default'], $key );
		}
		$this->assertSame( array( 'contain', 'cover' ), array_keys( $fields['art_bg_size']['choices'] ) );
		$this->assertCount( 9, $fields['art_bg_position']['choices'] );
		$this->assertSame( array( 'background', 'background_class', 'art_bg_size', 'art_bg_position', 'background_overlay_color' ), WP_Swiper_Settings::blocks()['layout']['background']['fields'] );
	}

	public function test_ring_or_shape_as_a_background_is_placed_in_the_block() {
		$values = array(
			'effect'                 => 'art',
			'art_style'              => 'ring',
			'background'             => true,
			'background_class'       => 'hero',
			'art_bg_size'            => 'cover',
			'art_bg_position'        => 'right bottom',
			'art_bg_size_mobile'     => 'contain',
			'art_bg_position_mobile' => 'left top',
		);
		$html = $this->render( $values, $this->slides( 4 ) );
		$this->assertSame( 1, preg_match( '/<div class="wp-swiper-art [^"]*wp-swiper-art--background wp-swiper-art--bg-fit[ "]/', $html ) );
		// Cover: the smallest that fills the block; Contain: the largest that fits; a ring square.
		$this->assertStringContainsString( '--wp-swiper-art-bg-w:max(100cqw, 100cqh * 1)', $html );
		$this->assertStringContainsString( '--wp-swiper-art-bg-w-tablet:max(100cqw, 100cqh * 1)', $html );
		$this->assertStringContainsString( '--wp-swiper-art-bg-w-mobile:min(100cqw, 100cqh * 1)', $html );
		$this->assertStringContainsString( '--wp-swiper-art-bg-x:1;--wp-swiper-art-bg-y:1', html_entity_decode( $html ) );
		$this->assertStringContainsString( '--wp-swiper-art-bg-x-mobile:0;--wp-swiper-art-bg-y-mobile:0', html_entity_decode( $html ) );
		// An SVG shape: its drawing's proportions.
		$this->assertContains( '--wp-swiper-art-bg-ratio:2.5', WP_Swiper_Art::background_fit( $this->settings( $values ), array( 'desktop' => 2.5, 'tablet' => 2.5, 'mobile' => 0.8 ) ) );
		$this->assertContains( '--wp-swiper-art-bg-w-mobile:min(100cqw, 100cqh * 0.8)', WP_Swiper_Art::background_fit( $this->settings( $values ), array( 'desktop' => 2.5, 'tablet' => 2.5, 'mobile' => 0.8 ) ) );
		// A grid stretches to the block: none of it.
		$grid = $this->render( array( 'art_style' => 'grid-shapes' ) + $values, $this->slides( 4 ) );
		$this->assertStringContainsString( 'wp-swiper-art--background', $grid );
		$this->assertStringNotContainsString( 'wp-swiper-art--bg-fit', $grid );
		$this->assertStringNotContainsString( 'art-bg-w', $grid );
	}

	public function test_style_without_one_saved_is_ring() {
		// Settings without a Style (or an unknown one): Ring, no notice.
		$this->assertSame( 'ring', WP_Swiper_Art::style_of( array( 'effect' => 'art' ) ) );
		$this->assertSame( 'ring', WP_Swiper_Art::style_of( array( 'art_style' => 'nope' ) ) );
		// So it can be a background too (as a ring, placed in the block).
		$this->assertTrue( WP_Swiper_Settings::is_background( array( 'effect' => 'art', 'background' => true ) ) );
	}

	public function test_art_background_markup() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$slides = $this->slides( 4, array( 'caption' => '<p>Over the photo</p>', 'description' => '<p>Under it</p>' ) );
		$values = array(
			'effect'                   => 'art',
			'art_style'                => 'grid-shapes',
			'background'               => true,
			'background_class'         => 'hero',
			'background_overlay_color' => 'rgba(0,0,0,0.4)',
			'lightbox'                 => true,
			'art_height_unit'          => '%',
		);
		$html   = $this->render( $values, $slides );
		$config = $this->config( $html, 'data-wp-swiper-art' );
		$this->assertSame( 1, preg_match( '/<div class="wp-swiper-art [^"]*wp-swiper-art--background wp-swiper-art--bg-overlay[ "]/', $html ) );
		$this->assertStringContainsString( 'data-wp-swiper-bg="hero"', $html );
		$this->assertStringContainsString( '--wp-swiper-bg-overlay:rgba(0,0,0,0.4)', $html );
		// Behind the block's content: no captions, descriptions or lightbox; the block's height.
		$this->assertStringNotContainsString( 'wp-swiper-art__caption', $html );
		$this->assertStringNotContainsString( 'wp-swiper-descriptions', $html );
		$this->assertFalse( $config['lightbox'] );
		$this->assertSame( array( 'desktop' => 'px', 'tablet' => 'px', 'mobile' => 'px' ), $config['height'] );
		// Its notice, shown by art.js where no block has the class.
		$this->assertStringContainsString( 'data-wp-swiper-bg-missing hidden', $html );

		// A ring too: placed in the block, no captions either.
		$ring = $this->render( array( 'art_style' => 'ring' ) + $values, $slides );
		$this->assertStringContainsString( 'wp-swiper-art--bg-fit', $ring );
		$this->assertStringContainsString( 'data-wp-swiper-bg="hero"', $ring );
		$this->assertStringNotContainsString( 'wp-swiper-art__caption', $ring );
		// Not a background: captions and all.
		$this->assertStringContainsString( 'wp-swiper-art__caption', $this->render( array( 'art_style' => 'ring', 'background' => false ) + $values, $slides ) );
		// Without the overlay color: no overlay.
		$this->assertStringNotContainsString( 'wp-swiper-art--bg-overlay', $this->render( array( 'background_overlay_color' => 'rgba(0,0,0,0)' ) + $values, $slides ) );
	}
}
