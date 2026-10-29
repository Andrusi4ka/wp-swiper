<?php
/**
 * WP_Swiper_Render: the shortcode, the markup, the config frontend.js gets,
 * and what each effect and mode forces.
 */

class Test_WP_Swiper_Render extends WP_Swiper_TestCase {

	public function set_up() {
		parent::set_up();
		WP_Swiper_Render::register_assets();
	}

	/* Shortcode ------------------------------------------------------- */

	public function test_shortcode_renders_a_published_slider() {
		$id   = $this->slider();
		$html = do_shortcode( '[wp_swiper id="' . $id . '"]' );

		$this->assertContains( 'wp-swiper--' . $id, $this->root_classes( $html ) );
		$this->assertSame( 3, substr_count( $html, 'class="swiper-slide' ) );
		$this->assertTrue( wp_script_is( 'wp-swiper', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wp-swiper', 'enqueued' ) );
	}

	// Fresh script and style queues, the plugin's files registered again.
	private function fresh_assets() {
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		WP_Swiper_Render::register_assets();
	}

	public function test_collage_files_only_where_a_collage_is_shown() {
		// A slider: no collage files.
		$this->fresh_assets();
		do_shortcode( '[wp_swiper id="' . $this->slider() . '"]' );
		$this->assertFalse( wp_script_is( 'wp-swiper-collage', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'wp-swiper-collage', 'enqueued' ) );

		// A Collage: its own files, after frontend's.
		$this->fresh_assets();
		do_shortcode( '[wp_swiper id="' . $this->slider( null, array( 'effect' => 'collage' ) ) . '"]' );
		$this->assertTrue( wp_script_is( 'wp-swiper-collage', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wp-swiper-collage', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wp-swiper', 'enqueued' ) );
		$this->assertSame( array( 'wp-swiper' ), wp_scripts()->query( 'wp-swiper-collage' )->deps );
		$this->assertSame( array( 'wp-swiper' ), wp_styles()->query( 'wp-swiper-collage' )->deps );
		wp_scripts()->all_deps( wp_scripts()->queue );
		$this->assertSame( array( 'wp-swiper', 'wp-swiper-collage' ), array_values( array_intersect( wp_scripts()->to_do, array( 'wp-swiper', 'wp-swiper-collage' ) ) ) );

		// The files exist where they are registered.
		$this->assertFileExists( WP_SWIPER_DIR . 'assets/js/collage.js' );
		$this->assertFileExists( WP_SWIPER_DIR . 'assets/css/collage.css' );
	}

	public function test_collage_styles_in_head_and_remembered_when_late() {
		$collage = $this->slider( null, array( 'effect' => 'collage' ) );

		// In the page's content: in <head>, without Swiper.
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => '[wp_swiper id="' . $collage . '"]' ) );
		$this->go_to( get_permalink( $page ) );
		$this->fresh_assets();
		$this->assertTrue( wp_style_is( 'wp-swiper-collage', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'swiper', 'enqueued' ) );

		// Shown after <head> (say in the footer): in <head> next time.
		delete_option( WP_Swiper_Render::LATE_OPTION );
		$other = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $other ) );
		$this->fresh_assets();
		$GLOBALS['wp_actions']['wp_head'] = 1;
		do_shortcode( '[wp_swiper id="' . $collage . '"]' );
		WP_Swiper_Render::remember_late_styles();
		unset( $GLOBALS['wp_actions']['wp_head'] );
		$this->assertSame( array( 'p' . $other => array( 'wp-swiper', 'wp-swiper-collage' ) ), get_option( WP_Swiper_Render::LATE_OPTION ) );
		$this->assertSame( array( 'wp-swiper', 'wp-swiper-collage' ), WP_Swiper_Render::late_styles() );
	}

	public function test_swiper_only_where_a_slider_needs_it() {
		// A Collage: no Swiper; its lightbox loads it when it opens.
		$this->fresh_assets();
		do_shortcode( '[wp_swiper id="' . $this->slider( null, array( 'effect' => 'collage', 'lightbox' => true ) ) . '"]' );
		$this->assertTrue( wp_script_is( 'wp-swiper', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'swiper', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'swiper', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'wp-swiper-slider', 'enqueued' ) );
		$this->assertSame( array(), wp_scripts()->query( 'wp-swiper' )->deps );
		$i18n = wp_scripts()->get_data( 'wp-swiper', 'data' );
		$this->assertStringContainsString( 'swiper-bundle.min.js?ver=' . WP_SWIPER_SWIPER_VERSION, wp_unslash( $i18n ) );
		$this->assertStringContainsString( 'swiper-bundle.min.css?ver=' . WP_SWIPER_SWIPER_VERSION, wp_unslash( $i18n ) );

		// A slider on the same page: Swiper too and slider.js, after Swiper
		// and frontend.js; Swiper's stylesheet before frontend.css.
		do_shortcode( '[wp_swiper id="' . $this->slider() . '"]' );
		$this->assertTrue( wp_script_is( 'wp-swiper-slider', 'enqueued' ) );
		$this->assertSame( array( 'swiper', 'wp-swiper' ), wp_scripts()->query( 'wp-swiper-slider' )->deps );
		$this->assertSame( array(), wp_scripts()->query( 'wp-swiper' )->deps );
		$this->assertSame( array( 'swiper' ), wp_styles()->query( 'wp-swiper' )->deps );
		// slider.js printed after both (frontend.js itself needs no Swiper).
		wp_scripts()->all_deps( wp_scripts()->queue );
		$order = array_values( array_intersect( wp_scripts()->to_do, array( 'swiper', 'wp-swiper', 'wp-swiper-slider' ) ) );
		$this->assertCount( 3, $order );
		$this->assertSame( 'wp-swiper-slider', $order[2] );
		$this->assertFileExists( WP_SWIPER_DIR . 'assets/js/slider.js' );
	}

	public function test_slider_file_not_loaded_for_collage_or_art() {
		foreach ( array( 'collage', 'art' ) as $effect ) {
			$this->fresh_assets();
			do_shortcode( '[wp_swiper id="' . $this->slider( null, array( 'effect' => $effect ) ) . '"]' );
			$this->assertFalse( wp_script_is( 'wp-swiper-slider', 'enqueued' ), $effect );
		}
	}

	public function test_first_image_of_the_page_fetched_first() {
		// A new page: no image has it yet.
		$flag = new ReflectionProperty( 'WP_Swiper_Render', 'priority' );
		$flag->setAccessible( true );
		$flag->setValue( null, false );

		$first  = $this->render();
		$second = $this->render();
		// The first slider's first image only (eager); the others lazy, without it.
		$this->assertSame( 1, substr_count( $first, 'fetchpriority="high"' ) );
		$this->assertMatchesRegularExpression( '/<img[^>]*loading="eager"[^>]*fetchpriority="high"|<img[^>]*fetchpriority="high"[^>]*loading="eager"/', $first );
		$this->assertStringNotContainsString( 'fetchpriority="high"', $second );
	}

	public function test_styles_needed_after_head_are_in_head_next_time() {
		delete_option( WP_Swiper_Render::LATE_OPTION );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page ) );

		// A slider in, say, the footer: <head> is out when it shows.
		$this->fresh_assets();
		$GLOBALS['wp_actions']['wp_head'] = 1;
		do_shortcode( '[wp_swiper id="' . $this->slider() . '"]' );
		WP_Swiper_Render::remember_late_styles();
		$this->assertSame( array( 'p' . $page => array( 'swiper', 'wp-swiper' ) ), get_option( WP_Swiper_Render::LATE_OPTION ) );

		// The next visit: in <head> (enqueued as the page starts).
		unset( $GLOBALS['wp_actions']['wp_head'] );
		$this->fresh_assets();
		$this->assertTrue( wp_style_is( 'wp-swiper', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'swiper', 'enqueued' ) );

		// Another page: not there.
		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		$this->fresh_assets();
		$this->assertFalse( wp_style_is( 'wp-swiper', 'enqueued' ) );
	}

	public function test_styles_printed_in_head_are_not_late() {
		delete_option( WP_Swiper_Render::LATE_OPTION );
		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
		$this->fresh_assets();
		// <head> printed them (the content has the slider), then the content shows it.
		wp_styles()->done = array( 'swiper', 'wp-swiper' );
		$GLOBALS['wp_actions']['wp_head'] = 1;
		do_shortcode( '[wp_swiper id="' . $this->slider() . '"]' );
		WP_Swiper_Render::remember_late_styles();
		unset( $GLOBALS['wp_actions']['wp_head'] );
		$this->assertFalse( get_option( WP_Swiper_Render::LATE_OPTION ) );
	}

	public function test_styles_needed_on_many_pages_are_on_every_page() {
		$map = array();
		for ( $i = 1; $i <= WP_Swiper_Render::LATE_PAGES; $i++ ) {
			$map[ 'p' . $i ] = array( 'wp-swiper' );
		}
		update_option( WP_Swiper_Render::LATE_OPTION, $map );

		// One page more (an Art): from now on every page has them.
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page ) );
		$this->fresh_assets();
		$GLOBALS['wp_actions']['wp_head'] = 1;
		do_shortcode( '[wp_swiper id="' . $this->slider( null, array( 'effect' => 'art' ) ) . '"]' );
		WP_Swiper_Render::remember_late_styles();
		unset( $GLOBALS['wp_actions']['wp_head'] );
		$this->assertSame( array( '*' => array( 'wp-swiper', 'wp-swiper-art' ) ), get_option( WP_Swiper_Render::LATE_OPTION ) );

		$this->go_to( home_url( '/?s=nothing' ) );
		$this->assertSame( array( 'wp-swiper', 'wp-swiper-art' ), WP_Swiper_Render::late_styles() );
	}

	public function test_styles_in_head_when_the_content_has_a_slider() {
		$art  = $this->slider( null, array( 'effect' => 'art' ) );
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => '[wp_swiper id="' . $art . '"]' ) );
		$this->go_to( get_permalink( $page ) );
		$this->fresh_assets();
		// Art: its own stylesheet, no Swiper.
		$this->assertTrue( wp_style_is( 'wp-swiper-art', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'swiper', 'enqueued' ) );
	}

	public function test_content_needs_swiper() {
		$slider  = $this->slider();
		$collage = $this->slider( null, array( 'effect' => 'collage' ) );
		$art     = $this->slider( null, array( 'effect' => 'art' ) );

		$this->assertFalse( WP_Swiper_Render::content_needs_swiper( 'No slider here.' ) );
		$this->assertFalse( WP_Swiper_Render::content_needs_swiper( '[wp_swiper id="' . $collage . '"] [wp_swiper id="' . $art . '"]' ) );
		$this->assertTrue( WP_Swiper_Render::content_needs_swiper( '[wp_swiper id="' . $collage . '"][vc_row][wp_swiper id="' . $slider . '" class="x"][/vc_row]' ) );
		// An ID that cannot be told: Swiper, to be safe.
		$this->assertTrue( WP_Swiper_Render::content_needs_swiper( '[wp_swiper]' ) );
	}

	public function test_shortcode_hides_what_cannot_show_from_visitors() {
		$draft = $this->slider( null, array(), array( 'post_status' => 'draft' ) );
		$post  = self::factory()->post->create();
		$empty = $this->slider( array() );

		foreach ( array( $draft, $post, $empty, 0, 999999 ) as $id ) {
			$this->assertSame( '', do_shortcode( '[wp_swiper id="' . $id . '"]' ), "Slider $id" );
		}
	}

	public function test_shortcode_explains_to_editors() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$draft = $this->slider( null, array(), array( 'post_status' => 'draft' ) );
		$this->assertStringContainsString( 'wp-swiper-notice', do_shortcode( '[wp_swiper id="' . $draft . '"]' ) );
		$this->assertStringContainsString( 'no slides yet', do_shortcode( '[wp_swiper id="' . $this->slider( array() ) . '"]' ) );
	}

	public function test_deleted_media_is_skipped() {
		$slides = $this->slides( 2 );
		wp_delete_attachment( $slides[0]['id'], true );
		$html = WP_Swiper_Render::render_slider( 1, $slides, $this->settings( array( 'loop' => false ) ) );

		$this->assertSame( 1, substr_count( $html, 'class="swiper-slide' ) );
		$this->assertSame( 1, $this->config( $html )['slideCount'] );
	}

	public function test_extra_and_own_classes_are_sanitized() {
		$id   = $this->slider( null, array( 'css_class' => 'mine', 'css_id' => 'hero' ) );
		$html = do_shortcode( '[wp_swiper id="' . $id . '" class="placed &quot;&gt;&lt;script&gt;x"]' );

		$classes = $this->root_classes( $html );
		$this->assertContains( 'mine', $classes );
		$this->assertContains( 'placed', $classes );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( 'id="hero"', $html );
	}

	/* Markup and config ----------------------------------------------- */

	public function test_default_slider() {
		$html   = $this->render();
		$config = $this->config( $html );

		$this->assertSame( 'slide', $config['effect'] );
		$this->assertFalse( $config['single'] );
		$this->assertTrue( $config['loop'] );
		$this->assertSame( 3, $config['slideCount'] );
		$this->assertFalse( $config['zoom'] );
		$this->assertFalse( $config['video'] );
		$this->assertContains( 'wp-swiper--horizontal', $this->root_classes( $html ) );
		$this->assertContains( 'wp-swiper--fit-cover', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-height:500px', $this->root_style( $html ) );
		$this->assertStringContainsString( 'swiper-button-next', $html );
		$this->assertStringContainsString( 'class="swiper-pagination', $html );
		// The first image loads at once, the others lazily.
		$this->assertSame( 1, substr_count( $html, 'loading="eager"' ) );
	}

	public function test_loop_repeats_too_few_slides() {
		$config = $this->config( $this->render( array( 'slides_per_view' => 3 ), $this->slides( 2 ) ) );
		$this->assertGreaterThan( 1, $config['copies'] );
		$this->assertSame( 2, $config['slideCount'] );

		// One slide does not loop.
		$this->assertFalse( $this->config( $this->render( array(), $this->slides( 1 ) ) )['loop'] );
	}

	public function test_markup_holds_every_copy() {
		$html   = $this->render( array( 'slides_per_view' => 3 ), $this->slides( 2 ) );
		$config = $this->config( $html );
		$this->assertSame( 2 * $config['copies'], substr_count( $html, 'class="swiper-slide' ) );
	}

	public function test_fade_is_single_and_horizontal() {
		$html = $this->render( array( 'effect' => 'fade', 'direction' => 'vertical' ) );
		$this->assertTrue( $this->config( $html )['single'] );
		$this->assertContains( 'wp-swiper--horizontal', $this->root_classes( $html ) );
	}

	public function test_height_auto_fills_the_parent() {
		$html = $this->render( array( 'height_unit' => 'auto' ) );
		$this->assertSame( array( 'desktop' => true, 'tablet' => true, 'mobile' => true ), $this->config( $html )['fill'] );
		$this->assertContains( 'wp-swiper--fill', $this->root_classes( $html ) );
		$this->assertStringNotContainsString( '--wp-swiper-height', $this->root_style( $html ) );
	}

	public function test_height_auto_on_some_devices_only() {
		$html  = $this->render( array( 'height' => 400, 'height_unit' => 'px', 'height_unit_tablet' => '', 'height_unit_mobile' => 'auto' ) );
		$style = $this->root_style( $html );

		// frontend.js sizes it on the phone only (it sets the class there).
		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => true ), $this->config( $html )['fill'] );
		$this->assertNotContains( 'wp-swiper--fill', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-height:400px', $style );
		$this->assertStringContainsString( '--wp-swiper-height-tablet:400px', $style );
		$this->assertStringContainsString( '--wp-swiper-height-mobile:auto', $style );

		$this->assertFalse( $this->config( $this->render( array( 'height_unit' => 'px' ) ) )['fill'] );
	}

	public function test_height_in_percent_on_some_devices_only() {
		$html  = $this->render( array( 'height' => 500, 'height_mobile' => 60, 'height_unit' => 'px', 'height_unit_mobile' => '%' ) );
		$style = $this->root_style( $html );

		// The block stretches (100%) only where the Height is in %.
		$this->assertContains( 'wp-swiper--height-percent', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-height-mobile:60%', $style );
		$this->assertStringContainsString( '--wp-swiper-block-height:auto', $style );
		$this->assertStringContainsString( '--wp-swiper-block-height-tablet:auto', $style );
		$this->assertStringContainsString( '--wp-swiper-block-height-mobile:100%', $style );

		$plain = $this->render( array( 'height' => 500, 'height_unit' => 'px' ) );
		$this->assertNotContains( 'wp-swiper--height-percent', $this->root_classes( $plain ) );
		$this->assertStringNotContainsString( '--wp-swiper-block-height', $this->root_style( $plain ) );
	}

	public function test_responsive_values_reach_the_config() {
		$config = $this->config( $this->render( array( 'slides_per_view' => 3, 'slides_per_view_tablet' => 2, 'slides_per_view_mobile' => '' ) ) );
		$this->assertEquals( array( 'desktop' => 3, 'tablet' => 2, 'mobile' => 2 ), $config['slidesPerView'] );
	}

	public function test_marquee_has_no_controls_and_always_loops() {
		$config = $this->config( $this->render( array( 'marquee' => true, 'loop' => false, 'navigation' => true, 'keyboard' => true ) ) );
		$this->assertTrue( $config['loop'] );
		$this->assertFalse( $config['navigation'] );
		$this->assertFalse( $config['pagination'] );
		$this->assertFalse( $config['keyboard'] );
		$this->assertSame( 50, $config['marquee']['speed'] );
	}

	public function test_marquee_direction() {
		// Right to left by default (as before the setting).
		$this->assertFalse( $this->config( $this->render( array( 'marquee' => true ) ) )['marquee']['reverse'] );
		$this->assertTrue( $this->config( $this->render( array( 'marquee' => true, 'marquee_direction' => 'reverse' ) ) )['marquee']['reverse'] );
		// No continuous scroll, nothing of it.
		$this->assertFalse( $this->config( $this->render( array( 'marquee_direction' => 'reverse' ) ) )['marquee'] );
	}

	public function test_pagination_classes() {
		$html = $this->render( array( 'pagination' => 'fraction', 'pagination_position' => 'top-right', 'pagination_placement' => 'outside' ) );
		$classes = $this->root_classes( $html );
		$this->assertContains( 'wp-swiper--pag-top', $classes );
		$this->assertContains( 'wp-swiper--pag-right', $classes );
		$this->assertContains( 'wp-swiper--pag-outside', $classes );
		$this->assertSame( 'fraction', $this->config( $html )['pagination'] );

		$none = $this->render( array( 'pagination' => 'none' ) );
		$this->assertStringNotContainsString( 'class="swiper-pagination', $none );
	}

	public function test_pagination_look_defaults_keep_the_old_bullets() {
		$html  = $this->render();
		$style = $this->root_style( $html );

		// 8px circles, as before; nothing else set.
		$this->assertStringContainsString( '--wp-swiper-bullet-width:8px', $style );
		$this->assertStringContainsString( '--wp-swiper-bullet-height:8px', $style );
		foreach ( array( 'bullet-radius', 'bullet-gap', 'bullet-active', 'fraction-size', 'pag-color' ) as $name ) {
			$this->assertStringNotContainsString( '--wp-swiper-' . $name, $style, $name );
		}
		$this->assertNotContains( 'wp-swiper--pag-colors', $this->root_classes( $html ) );
		$this->assertNotContains( 'wp-swiper--fraction-size', $this->root_classes( $html ) );
	}

	public function test_pagination_bullets_per_device_and_pill() {
		$style = $this->root_style(
			$this->render(
				array(
					'pagination_bullet_width'         => 10,
					'pagination_bullet_height'        => 4,
					'pagination_bullet_height_mobile' => 3,
					'pagination_bullet_radius'        => 0,
					'pagination_bullet_gap'           => '',
					'pagination_bullet_gap_tablet'    => 6,
					'pagination_bullet_active'        => 30,
				)
			)
		);

		$this->assertStringContainsString( '--wp-swiper-bullet-width:10px', $style );
		$this->assertStringContainsString( '--wp-swiper-bullet-width-mobile:10px', $style );
		$this->assertStringContainsString( '--wp-swiper-bullet-height-tablet:4px', $style );
		$this->assertStringContainsString( '--wp-swiper-bullet-height-mobile:3px', $style );
		$this->assertStringContainsString( '--wp-swiper-bullet-radius:0px', $style );
		// No gap on desktop (Swiper's own); the tablet's, inherited by mobile.
		$this->assertStringNotContainsString( '--wp-swiper-bullet-gap:', $style );
		$this->assertStringContainsString( '--wp-swiper-bullet-gap-tablet:6px', $style );
		$this->assertStringContainsString( '--wp-swiper-bullet-gap-mobile:6px', $style );
		$this->assertStringContainsString( '--wp-swiper-bullet-active:30px', $style );
	}

	public function test_pagination_own_colors_and_fraction_size() {
		$html = $this->render(
			array(
				'pagination'                  => 'fraction',
				'pagination_colors'           => true,
				'pagination_color'            => '#ff0000',
				'pagination_inactive_color'   => '#00ff00',
				'pagination_inactive_opacity' => 30,
				'pagination_fraction_size_mobile' => 12,
			)
		);
		$style = $this->root_style( $html );

		$this->assertContains( 'wp-swiper--pag-colors', $this->root_classes( $html ) );
		$this->assertContains( 'wp-swiper--fraction-size', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-pag-color:#ff0000', $style );
		$this->assertStringContainsString( '--wp-swiper-pag-inactive-color:#00ff00', $style );
		$this->assertStringContainsString( '--wp-swiper-pag-inactive-opacity:0.3', $style );
		$this->assertStringContainsString( '--wp-swiper-fraction-size-mobile:12px', $style );
		$this->assertStringNotContainsString( '--wp-swiper-fraction-size:', $style );
	}

	public function test_no_pagination_look_without_pagination() {
		foreach ( array( array( 'pagination' => 'none' ), array( 'effect' => 'thumbs' ), array( 'marquee' => true ), array( 'background' => true ) ) as $values ) {
			$html = $this->render( $values + array( 'pagination_colors' => true, 'pagination_bullet_active' => 30 ) );
			$this->assertStringNotContainsString( '--wp-swiper-bullet', $this->root_style( $html ), wp_json_encode( $values ) );
			$this->assertNotContains( 'wp-swiper--pag-colors', $this->root_classes( $html ), wp_json_encode( $values ) );
		}
	}

	public function test_captions_keep_their_styles_only_and_their_lines_tags() {
		// No Tag on the Caption page any more: each line has its own.
		$this->assertArrayNotHasKey( 'caption_tag', WP_Swiper_Settings::fields() );

		$html = $this->render( array( 'caption_tag' => 'script' ), $this->slides( 1, array( 'caption' => '<b>Bold</b> & co<br>line <a href="https://example.com" onclick="x()">two</a><img src=x onerror=alert(1)>' ) ) );
		$this->assertStringContainsString( '<div class="wp-swiper__caption-text"><b>Bold</b> &amp; co<br>line two</div>', $html );

		// Its lines: paragraphs and headings of their own, aligned.
		$own = $this->render( array(), $this->slides( 1, array( 'caption' => '<h2 style="text-align: center">Title</h2><p>Sub</p>' ) ) );
		$this->assertStringContainsString( '<div class="wp-swiper__caption-text"><h2 style="text-align: center">Title</h2><p>Sub</p></div>', $own );

		// Saved before with the slider's Tag (until 2.8): in that tag still,
		// but not around lines of its own.
		$h2 = $this->render( array( 'caption_tag' => 'h2' ), $this->slides( 1, array( 'caption' => 'Title' ) ) );
		$this->assertStringContainsString( '<div class="wp-swiper__caption-text"><h2>Title</h2></div>', $h2 );
		$own = $this->render( array( 'caption_tag' => 'h2' ), $this->slides( 1, array( 'caption' => '<p>Own</p>' ) ) );
		$this->assertStringContainsString( '<div class="wp-swiper__caption-text"><p>Own</p></div>', $own );
		$this->assertSame( 'h2', WP_Swiper_Settings::legacy_caption_tag( array( 'caption_tag' => 'h2' ) ) );
		$this->assertSame( 'div', WP_Swiper_Settings::legacy_caption_tag( array( 'caption_tag' => 'script' ) ) );
		$this->assertSame( 'div', WP_Swiper_Settings::legacy_caption_tag( array() ) );
	}

	public function test_no_captions_as_a_background() {
		$slides = $this->slides( 2, array( 'caption' => '<p>Over the photo</p>' ) );
		$this->assertStringContainsString( 'wp-swiper__caption-text', $this->render( array(), $slides ) );

		// Behind a block's content: neither captions nor descriptions.
		foreach ( array( 'slide', 'fade', 'collage' ) as $effect ) {
			$settings = array( 'effect' => $effect, 'background' => true, 'background_class' => 'hero' );
			$html     = $this->render( $settings, $slides );
			$this->assertStringNotContainsString( 'wp-swiper__caption', $html, $effect );
			$this->assertFalse( WP_Swiper_Settings::can_caption( $settings ), $effect );
			$this->assertFalse( WP_Swiper_Settings::can_describe( array( 'direction' => 'horizontal' ) + $settings ), $effect );
		}
		// "Use as background" left on with an effect that cannot be one: captions shown.
		$this->assertTrue( WP_Swiper_Settings::can_caption( array( 'effect' => 'cube', 'background' => true ) ) );
	}

	public function test_no_caption_without_text() {
		$html = $this->render( array(), $this->slides( 1, array( 'caption' => '<strong> </strong><br>' ) ) );
		$this->assertStringNotContainsString( 'wp-swiper__caption', $html );
	}

	/* Links and the lightbox ------------------------------------------ */

	public function test_lightbox_marks_the_full_images() {
		$html = $this->render( array( 'lightbox' => true ) );
		$this->assertContains( 'wp-swiper--lightbox', $this->root_classes( $html ) );
		$this->assertSame( 3, substr_count( $html, 'data-wp-swiper-full=' ) );
		$this->assertIsArray( $this->config( $html )['lightbox'] );
	}

	public function test_a_link_turns_the_lightbox_off() {
		$slides            = $this->slides( 2 );
		$slides[1]['link'] = 'https://example.com/page';
		$slides[1]['new_tab'] = true;
		$html = $this->render( array( 'lightbox' => true ), $slides );

		$this->assertFalse( $this->config( $html )['lightbox'] );
		$this->assertStringNotContainsString( 'data-wp-swiper-full', $html );
		$this->assertStringContainsString( 'href="https://example.com/page" target="_blank" rel="noopener"', $html );
	}

	/* Videos ---------------------------------------------------------- */

	public function test_a_video_slide_loads_nothing_up_front_and_has_no_link() {
		$slides = array(
			array( 'id' => $this->video(), 'caption' => '', 'link' => 'https://example.com', 'new_tab' => false, 'poster' => 0 ),
		);
		$html = $this->render( array( 'video_muted' => true ), $slides );

		$this->assertStringContainsString( '<video class="wp-swiper__image wp-swiper__video" data-src=', $html );
		$this->assertStringContainsString( 'preload="none"', $html );
		$this->assertStringContainsString( ' muted', $html );
		$this->assertStringNotContainsString( 'wp-swiper__link', $html );
		$this->assertIsArray( $this->config( $html )['video'] );
	}

	public function test_a_youtube_slide_is_a_facade() {
		$slides = array(
			array( 'id' => 0, 'embed' => 'https://youtu.be/dQw4w9WgXcQ', 'caption' => '', 'link' => '', 'new_tab' => false, 'poster' => 0 ),
		);
		// No call to YouTube from the tests: its oEmbed data is already "cached".
		set_transient( 'wp_swiper_oembed_' . md5( $slides[0]['embed'] ), array( 'width' => 1280, 'height' => 720, 'thumb' => '', 'title' => 'Song' ), HOUR_IN_SECONDS );
		$html = $this->render( array(), $slides );

		$this->assertStringContainsString( 'data-wp-swiper-embed-player=', $html );
		$this->assertStringContainsString( 'i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringContainsString( 'Play video: Song', $html );
	}

	/* Effects --------------------------------------------------------- */

	public function test_thumbnails_have_a_strip_and_no_pagination() {
		$html   = $this->render( array( 'effect' => 'thumbs', 'pagination' => 'bullets', 'keyboard' => true, 'thumbs_position_mobile' => 'left' ) );
		$config = $this->config( $html );

		$this->assertSame( 3, substr_count( $html, 'role="button" tabindex="0" aria-label="Go to slide' ) );
		$this->assertFalse( $config['pagination'] );
		$this->assertFalse( $config['keyboard'] );
		$this->assertTrue( $config['thumbs']['side']['mobile'] );
		$this->assertFalse( $config['thumbs']['side']['desktop'] );
		$this->assertContains( 'wp-swiper--thumbs-mobile-left', $this->root_classes( $html ) );
	}

	public function test_collage_is_not_a_slider() {
		$html   = $this->render( array( 'effect' => 'collage', 'image_fit' => 'contain', 'navigation' => true, 'collage_layout_mobile' => 'grid' ), $this->slides( 7 ) );
		$config = $this->config( $html, 'data-wp-swiper-collage' );

		$this->assertStringNotContainsString( 'data-wp-swiper=', $html );
		$this->assertStringNotContainsString( 'swiper-button', $html );
		$this->assertContains( 'wp-swiper--fit-cover', $this->root_classes( $html ) );
		$this->assertStringContainsString( '<template class="wp-swiper-collage__pool">', $html );
		$this->assertSame( 7, substr_count( $html, 'class="swiper-slide' ) );
		$this->assertSame( 'grid', $config['layout']['mobile'] );
		$this->assertSame( 'feature-center', $config['layout']['desktop'] );
		$this->assertIsArray( $config['swap'] );
		$this->assertStringContainsString( '--wp-swiper-collage-speed:800ms', $this->root_style( $html ) );
	}

	public function test_collage_without_swapping_stands_still() {
		$config = $this->config( $this->render( array( 'effect' => 'collage', 'collage_swap' => false ) ), 'data-wp-swiper-collage' );
		$this->assertFalse( $config['swap'] );
	}

	public function test_creative_carousel_shares() {
		$html = $this->render( array( 'effect' => 'creative', 'creative_style' => 'carousel' ) );
		$this->assertContains( 'wp-swiper--carousel', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-carousel-width:', $this->root_style( $html ) );
		$this->assertIsArray( $this->config( $html )['creative']['carousel'] );
	}

	public function test_wider_active_slide() {
		$html = $this->render( array( 'expand_active' => true, 'slides_per_view' => 4, 'active_width_mobile' => 80 ) );
		$config = $this->config( $html );
		$this->assertContains( 'wp-swiper--expand', $this->root_classes( $html ) );
		$this->assertSame( 4, $config['expand']['visible']['desktop'] );
		$this->assertStringContainsString( '--wp-swiper-active-width-mobile:80%', $this->root_style( $html ) );
	}

	/* Background ------------------------------------------------------ */

	public function test_background_forces_a_slider_that_plays_on_its_own() {
		$slides            = $this->slides( 2 );
		$slides[0]['link'] = 'https://example.com';
		$html   = $this->render(
			array(
				'effect'           => 'fade',
				'background'       => true,
				'background_class' => 'hero-row',
				'autoplay'         => false,
				'loop'             => false,
				'navigation'       => true,
				'pagination'       => 'bullets',
				'keyboard'         => true,
				'lightbox'         => true,
				'image_fit'        => 'contain',
				'height_unit'      => 'auto',
			),
			$slides
		);
		$config = $this->config( $html );

		$this->assertContains( 'wp-swiper--background', $this->root_classes( $html ) );
		$this->assertContains( 'wp-swiper--fit-cover', $this->root_classes( $html ) );
		$this->assertStringContainsString( 'data-wp-swiper-bg="hero-row"', $html );
		$this->assertIsArray( $config['autoplay'] );
		$this->assertFalse( $config['autoplay']['pauseOnMouseEnter'] );
		$this->assertTrue( $config['loop'] );
		$this->assertFalse( $config['navigation'] );
		$this->assertFalse( $config['pagination'] );
		$this->assertFalse( $config['keyboard'] );
		$this->assertFalse( $config['lightbox'] );
		$this->assertFalse( $config['fill'] );
		$this->assertStringNotContainsString( 'wp-swiper__link', $html );
		$this->assertStringNotContainsString( 'swiper-button', $html );
	}

	public function test_background_overlay() {
		$html  = $this->render(
			array(
				'background'               => true,
				'background_class'         => 'hero',
				'background_overlay_color' => 'rgba(16,32,48,0.4)',
			)
		);
		$style = $this->root_style( $html );

		$this->assertContains( 'wp-swiper--bg-overlay', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-bg-overlay:rgba(16,32,48,0.4)', $style );

		$plain = $this->render( array( 'background' => true, 'background_class' => 'hero' ) );
		$this->assertNotContains( 'wp-swiper--bg-overlay', $this->root_classes( $plain ) );
		$this->assertStringNotContainsString( '--wp-swiper-bg-overlay', $this->root_style( $plain ) );

		// A plain color: full.
		$solid = $this->render( array( 'background' => true, 'background_class' => 'hero', 'background_overlay_color' => '#102030' ) );
		$this->assertContains( 'wp-swiper--bg-overlay', $this->root_classes( $solid ) );
		$this->assertStringContainsString( '--wp-swiper-bg-overlay:#102030', $this->root_style( $solid ) );
		// Opacity 0: none.
		$clear = $this->render( array( 'background' => true, 'background_class' => 'hero', 'background_overlay_color' => 'rgba(16,32,48,0)' ) );
		$this->assertNotContains( 'wp-swiper--bg-overlay', $this->root_classes( $clear ) );
	}

	public function test_background_overlay_color_has_opacity_and_no_field_of_its_own() {
		$fields = WP_Swiper_Settings::fields();
		$this->assertArrayNotHasKey( 'background_overlay_opacity', $fields );
		$this->assertSame( 'alpha_color', $fields['background_overlay_color']['type'] );
		$this->assertSame( 'rgba(0,0,0,0)', $fields['background_overlay_color']['default'] );
		$this->assertArrayNotHasKey( 'group', $fields['background_overlay_color'] );
	}

	public function test_rounded_corners_only_while_on() {
		$fields = WP_Swiper_Settings::fields();
		$this->assertSame( 'checkbox', $fields['border_rounded']['type'] );
		$this->assertFalse( $fields['border_rounded']['default'] );
		$this->assertSame( 12, $fields['border_radius']['default'] );

		// On: the radius per device; the lightbox's not (its own switch, off).
		$html   = $this->render( array( 'border_rounded' => true, 'border_radius' => 16, 'border_radius_mobile' => 6, 'lightbox' => true ) );
		$style  = $this->root_style( $html );
		$config = $this->config( $html );
		$this->assertStringContainsString( '--wp-swiper-radius:16px', $style );
		$this->assertStringContainsString( '--wp-swiper-radius-mobile:6px', $style );
		$this->assertSame( array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ), $config['lightbox']['radius'] );

		// Off: none anywhere, whatever radius is kept.
		$html   = $this->render( array( 'border_rounded' => false, 'border_radius' => 16, 'border_radius_mobile' => 6, 'lightbox' => true ) );
		$config = $this->config( $html );
		$this->assertStringNotContainsString( '--wp-swiper-radius', $this->root_style( $html ) );
		$this->assertSame( array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ), $config['lightbox']['radius'] );

		// The lightbox's own switch: its own radius, whatever the slides'.
		$html = $this->render( array( 'border_rounded' => false, 'lightbox' => true, 'lightbox_border_rounded' => true, 'lightbox_radius' => 24, 'lightbox_radius_mobile' => 8 ) );
		$this->assertSame( array( 'desktop' => 24, 'tablet' => 24, 'mobile' => 8 ), $this->config( $html )['lightbox']['radius'] );
		// A collage's lightbox too.
		$html = $this->render( array( 'effect' => 'collage', 'lightbox' => true, 'lightbox_border_rounded' => true, 'lightbox_radius' => 24 ) );
		$this->assertSame( 24, $this->config( $html, 'data-wp-swiper-collage' )['lightbox']['radius']['desktop'] );
	}

	public function test_width_from_image_under_space_between_slides() {
		$slides = WP_Swiper_Settings::blocks()['layout']['slides']['fields'];
		$this->assertSame( array_search( 'space_between', $slides, true ) + 1, array_search( 'auto_width', $slides, true ) );
	}

	public function test_rounded_corners_switch_before_its_radius_in_look() {
		$look = WP_Swiper_Settings::blocks()['layout']['look']['fields'];
		$this->assertSame( array( 'border_rounded', 'border_radius', 'image_border' ), array_slice( $look, 0, 3 ) );
	}

	public function test_background_has_no_minimum_block_height() {
		// The block's height is the page builder's: no field, nothing on the slider,
		// even for a slider saved with one.
		$this->assertArrayNotHasKey( 'background_min_height', WP_Swiper_Settings::fields() );
		$id = $this->slider(
			$this->slides( 1 ),
			array(
				'background'                   => true,
				'background_class'             => 'hero',
				'background_min_height'        => 600,
				'background_min_height_tablet' => 500,
			)
		);
		$html = WP_Swiper_Render::render_slider( $id, WP_Swiper_Settings::get_slides( $id ), WP_Swiper_Settings::get( $id ) );
		$this->assertStringNotContainsString( 'bg-min-height', $html );
		$this->assertStringNotContainsString( 'bg-host--min', file_get_contents( WP_SWIPER_DIR . 'assets/js/frontend.js' ) );
		$this->assertStringNotContainsString( 'bg-host--min', file_get_contents( WP_SWIPER_DIR . 'assets/css/frontend.css' ) );
	}

	public function test_background_notice_for_editors_only() {
		$values = array( 'background' => true, 'background_class' => 'hero' );
		$this->assertStringNotContainsString( 'data-wp-swiper-bg-missing', $this->render( $values ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$html = $this->render( $values );
		$this->assertStringContainsString( '<p class="wp-swiper-notice" data-wp-swiper-bg-missing hidden>', $html );
		$this->assertStringContainsString( 'hero', $html );
	}

	public function test_background_collage() {
		$html = $this->render( array( 'effect' => 'collage', 'background' => true, 'background_class' => 'hero', 'height_unit' => 'auto' ) );
		$this->assertContains( 'wp-swiper--background', $this->root_classes( $html ) );
		$this->assertStringContainsString( 'data-wp-swiper-bg="hero"', $html );
		$this->assertFalse( $this->config( $html, 'data-wp-swiper-collage' )['fill'] );
	}

	public function test_background_only_for_its_effects() {
		$html = $this->render( array( 'effect' => 'cube', 'background' => true, 'background_class' => 'hero' ) );
		$this->assertNotContains( 'wp-swiper--background', $this->root_classes( $html ) );
		$this->assertStringNotContainsString( 'data-wp-swiper-bg', $html );
	}

	/* Image position -------------------------------------------------- */

	public function test_image_position_per_device() {
		$style = $this->root_style( $this->render( array( 'image_position' => 'top', 'image_position_mobile' => 'bottom-right' ) ) );

		$this->assertStringContainsString( '--wp-swiper-img-position:50% 0%', $style );
		// Tablet inherits the desktop's.
		$this->assertStringContainsString( '--wp-swiper-img-position-tablet:50% 0%', $style );
		$this->assertStringContainsString( '--wp-swiper-img-position-mobile:100% 100%', $style );
	}

	public function test_image_position_only_where_it_crops() {
		// The middle everywhere: the CSS default.
		$this->assertStringNotContainsString( '--wp-swiper-img-position', $this->root_style( $this->render() ) );
		$this->assertStringContainsString( '--wp-swiper-img-position-mobile:50% 0%', $this->root_style( $this->render( array( 'image_position_mobile' => 'top' ) ) ) );
		// "Contain" crops nothing.
		$this->assertStringNotContainsString( '--wp-swiper-img-position', $this->root_style( $this->render( array( 'image_fit' => 'contain', 'image_position' => 'top' ) ) ) );
		// A background crops whatever Image fit says.
		$style = $this->root_style( $this->render( array( 'image_fit' => 'contain', 'image_position' => 'left', 'background' => true, 'background_class' => 'hero' ) ) );
		$this->assertStringContainsString( '--wp-swiper-img-position:0% 50%', $style );
		// So does a collage.
		$this->assertStringContainsString( '--wp-swiper-img-position:100% 0%', $this->render( array( 'effect' => 'collage', 'image_position' => 'top-right' ) ) );
	}

	/* Zoom ------------------------------------------------------------ */

	public function test_zoom_in() {
		$html   = $this->render( array( 'effect' => 'fade', 'zoom' => true, 'zoom_amount' => 20, 'zoom_origin' => 'top-left', 'autoplay' => true, 'autoplay_delay' => 3000, 'speed' => 500 ) );
		$config = $this->config( $html );

		$this->assertContains( 'wp-swiper--zoom', $this->root_classes( $html ) );
		$this->assertSame( array( 'duration' => 3500, 'from' => 1, 'to' => 1.2 ), $config['zoom'] );
		$this->assertStringContainsString( '--wp-swiper-zoom-origin:0% 0%', $this->root_style( $html ) );
		$this->assertStringContainsString( '--wp-swiper-zoom-from:1', $this->root_style( $html ) );
	}

	public function test_zoom_out() {
		$html = $this->render( array( 'effect' => 'cube', 'zoom' => true, 'zoom_direction' => 'out', 'zoom_amount' => 10, 'zoom_duration' => 8000 ) );
		$this->assertSame( array( 'duration' => 8000, 'from' => 1.1, 'to' => 1 ), $this->config( $html )['zoom'] );
		$this->assertStringContainsString( '--wp-swiper-zoom-from:1.1', $this->root_style( $html ) );
	}

	public function test_no_zoom_without_a_main_image() {
		$html = $this->render( array( 'effect' => 'slide', 'zoom' => true, 'slides_per_view' => 3 ) );
		$this->assertFalse( $this->config( $html )['zoom'] );
		$this->assertNotContains( 'wp-swiper--zoom', $this->root_classes( $html ) );
		$this->assertStringNotContainsString( '--wp-swiper-zoom', $this->root_style( $html ) );
	}

	/* Descriptions ---------------------------------------------------- */

	/**
	 * Three slides, the first and the last with a description.
	 *
	 * @return array
	 */
	private function described_slides() {
		$slides                   = $this->slides( 3 );
		$slides[0]['description'] = '<p>First <strong>one</strong></p>';
		$slides[2]['description'] = "Third\n\nparagraph";
		return $slides;
	}

	/**
	 * The descriptions block of some markup, or ''.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	private function descriptions_block( $html ) {
		return preg_match( '#<div class="wp-swiper-descriptions .*$#s', $html, $match ) ? $match[0] : '';
	}

	public function test_descriptions_after_the_slider() {
		$html  = $this->render( array( 'pagination_placement' => 'outside' ), $this->described_slides() );
		$block = $this->descriptions_block( $html );

		$this->assertTrue( $this->config( $html )['descriptions'] );
		// After the slider's block, its outside pagination included.
		$this->assertGreaterThan( strrpos( $html, 'class="swiper-pagination' ), strpos( $html, 'wp-swiper-descriptions' ) );
		// One per slide with a description, by its index; the first shown before the script runs.
		$this->assertSame( 2, substr_count( $block, 'wp-swiper-descriptions__item' ) );
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item is-active" data-wp-swiper-index="0"><p>First <strong>one</strong></p>', $block );
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item" data-wp-swiper-index="2"><p>Third</p>', $block );
		$this->assertStringContainsString( '<p>paragraph</p>', $block );
		$this->assertStringNotContainsString( 'data-wp-swiper-index="1"', $block );
	}

	public function test_description_look() {
		$block = $this->descriptions_block(
			$this->render(
				array(
					'speed'                        => 400,
					'description_font'             => 'custom',
					'description_font_size'        => 18,
					'description_font_size_mobile' => 14,
					'description_offset'           => 24,
					// Saved while it had an Alignment field (until 2.8): still applies.
					'description_align'            => 'center',
				),
				$this->described_slides()
			)
		);
		// Its motion: the Slide effect's by default.
		$this->assertStringContainsString( 'class="wp-swiper-descriptions wp-swiper-descriptions--1 wp-swiper-descriptions--slide"', $block );
		// A size saved for the whole slider (until 2.8): still applies; tablet takes desktop's (CSS).
		foreach ( array( '--wp-swiper-speed:400ms', '--wp-swiper-description-offset:24px', '--wp-swiper-description-align:center', '--wp-swiper-description-size:18px', '--wp-swiper-description-size-mobile:14px' ) as $value ) {
			$this->assertStringContainsString( $value, $block );
		}
		// The page's own weight and colour unless set.
		$this->assertStringNotContainsString( '--wp-swiper-description-weight', $block );
		$this->assertStringNotContainsString( '--wp-swiper-description-color', $block );

		$own = $this->descriptions_block( $this->render( array( 'description_weight' => '600', 'description_own_color' => true, 'description_color' => '#123456' ), $this->described_slides() ) );
		$this->assertStringContainsString( 'wp-swiper-descriptions--weight wp-swiper-descriptions--color', $own );
		$this->assertStringContainsString( '--wp-swiper-description-weight:600', $own );
		$this->assertStringContainsString( '--wp-swiper-description-color:#123456', $own );
	}

	public function test_description_gap_per_device() {
		$this->assertTrue( WP_Swiper_Settings::fields()['description_offset']['responsive'] );
		// Tablet's own, mobile inherits it.
		$block = $this->descriptions_block( $this->render( array( 'description_offset' => 24, 'description_offset_tablet' => 10, 'description_offset_mobile' => '' ), $this->described_slides() ) );
		foreach ( array( '--wp-swiper-description-offset:24px', '--wp-swiper-description-offset-tablet:10px', '--wp-swiper-description-offset-mobile:10px' ) as $value ) {
			$this->assertStringContainsString( $value, $block );
		}
		// Saved before it was per device: the one gap on every screen.
		$id    = $this->slider( $this->described_slides(), array( 'description_offset' => 30 ) );
		$saved = get_post_meta( $id, WP_Swiper_Settings::META_SETTINGS, true );
		unset( $saved['description_offset_tablet'], $saved['description_offset_mobile'] );
		update_post_meta( $id, WP_Swiper_Settings::META_SETTINGS, $saved );
		$this->assertSame( array( 'desktop' => 30, 'tablet' => 30, 'mobile' => 30 ), WP_Swiper_Settings::responsive( WP_Swiper_Settings::get( $id ), 'description_offset' ) );
		// The CSS takes each device's.
		$css = file_get_contents( WP_SWIPER_DIR . 'assets/css/frontend.css' );
		$this->assertStringContainsString( 'margin-top: var(--wp-swiper-description-offset-tablet, var(--wp-swiper-description-offset, 16px));', $css );
		$this->assertStringContainsString( 'margin-top: var(--wp-swiper-description-offset-mobile, var(--wp-swiper-description-offset-tablet, var(--wp-swiper-description-offset, 16px)));', $css );
	}

	public function test_caption_background_color_with_its_opacity() {
		$fields = WP_Swiper_Settings::fields();
		$this->assertArrayNotHasKey( 'caption_bg_opacity', $fields );
		$this->assertSame( 'alpha_color', $fields['caption_bg_color']['type'] );
		$this->assertSame( 'rgba(0,0,0,0.65)', $fields['caption_bg_color']['default'] );
		// Style and color on one row.
		$this->assertSame( 'caption_bg', $fields['caption_bg_style']['group'] );
		$this->assertSame( 'caption_bg', $fields['caption_bg_color']['group'] );
		$this->assertSame( array( 'caption_bg', 'caption_bg_style', 'caption_bg_color' ), WP_Swiper_Settings::blocks()['caption']['caption_background']['fields'] );

		// Its color with its opacity; the far end of the gradient the same color, clear.
		$style = $this->root_style( $this->render( array( 'caption_bg' => true, 'caption_bg_color' => 'rgba(16,32,48,0.4)' ) ) );
		$this->assertStringContainsString( '--wp-swiper-caption-bg-from:rgba(16,32,48,0.4)', $style );
		$this->assertStringContainsString( '--wp-swiper-caption-bg-to:rgba(16,32,48,0)', $style );
		// A plain color: full.
		$style = $this->root_style( $this->render( array( 'caption_bg' => true, 'caption_bg_color' => '#ffffff' ) ) );
		$this->assertStringContainsString( '--wp-swiper-caption-bg-from:rgba(255,255,255,1)', $style );
		// The default, as before (black at 65%).
		$this->assertStringContainsString( '--wp-swiper-caption-bg-from:rgba(0,0,0,0.65)', $this->root_style( $this->render( array( 'caption_bg' => true ) ) ) );
	}

	public function test_description_motion_class() {
		$block = $this->descriptions_block( $this->render( array( 'effect' => 'cube' ), $this->described_slides() ) );
		$this->assertStringContainsString( 'wp-swiper-descriptions--cube', $block );

		$block = $this->descriptions_block( $this->render( array( 'effect' => 'cube', 'description_transition' => 'none' ), $this->described_slides() ) );
		$this->assertStringContainsString( 'wp-swiper-descriptions--none', $block );
		$this->assertStringNotContainsString( 'wp-swiper-descriptions--cube', $block );
	}

	public function test_description_markup_is_sanitized_again() {
		$slides                   = $this->slides( 1 );
		$slides[0]['description'] = '<p onclick="x()">Hi<script>alert(1)</script></p>';
		$block                    = $this->descriptions_block( $this->render( array(), $slides ) );

		$this->assertStringContainsString( '<p>Hialert(1)</p>', $block );
		$this->assertStringNotContainsString( '<script', $block );
		$this->assertStringNotContainsString( 'onclick', $block );
	}

	public function test_no_descriptions_block_without_one() {
		$html = $this->render( array(), $this->slides( 2, array( 'description' => '<p>&nbsp;</p>' ) ) );
		$this->assertStringNotContainsString( 'wp-swiper-descriptions', $html );
		$this->assertFalse( $this->config( $html )['descriptions'] );
		// Slides saved before descriptions.
		$this->assertStringNotContainsString( 'wp-swiper-descriptions', $this->render() );
	}

	public function test_no_descriptions_where_the_slider_shows_none() {
		$cases = array(
			'vertical'   => array( 'direction' => 'vertical' ),
			'marquee'    => array( 'marquee' => true ),
			'background' => array( 'background' => true, 'background_class' => 'hero' ),
			'collage'    => array( 'effect' => 'collage' ),
		);
		foreach ( $cases as $name => $values ) {
			$this->assertStringNotContainsString( 'wp-swiper-descriptions', $this->render( $values, $this->described_slides() ), $name );
		}
		// Fade always slides horizontally, whatever Direction was saved.
		$this->assertStringContainsString( 'wp-swiper-descriptions', $this->render( array( 'effect' => 'fade', 'direction' => 'vertical' ), $this->described_slides() ) );
	}

	public function test_descriptions_in_the_lightbox() {
		$config = $this->config( $this->render( array( 'lightbox' => true ), $this->described_slides() ) );
		$this->assertTrue( $config['lightbox']['descriptions'] );

		$config = $this->config( $this->render( array( 'lightbox' => true, 'description_lightbox' => false ), $this->described_slides() ) );
		$this->assertFalse( $config['lightbox']['descriptions'] );

		// None to show.
		$config = $this->config( $this->render( array( 'lightbox' => true ) ) );
		$this->assertFalse( $config['lightbox']['descriptions'] );
	}

	public function test_lightbox_descriptions_over_the_image_by_default() {
		$config = $this->config( $this->render( array( 'lightbox' => true ), $this->described_slides() ) );
		$this->assertFalse( $config['lightbox']['text'] );
	}

	public function test_font_size_per_slide() {
		// No field of the slider's any more: each slide's caption and description has its own.
		$this->assertArrayNotHasKey( 'caption_font_size', WP_Swiper_Settings::fields() );
		$this->assertArrayNotHasKey( 'description_font', WP_Swiper_Settings::fields() );

		$slides                        = $this->described_slides();
		$slides[0]['caption']          = 'Big';
		$slides[0]['caption_size']     = '28,,18';
		$slides[0]['description_size'] = '20,17,';
		$slides[1]['caption']          = 'Plain';
		$html = $this->render( array(), $slides );

		// The first slide's caption and description: their sizes on themselves.
		$this->assertStringContainsString( '<div class="wp-swiper__caption" style="--wp-swiper-caption-size:28px;--wp-swiper-caption-size-tablet:28px;--wp-swiper-caption-size-mobile:18px">', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item is-active" data-wp-swiper-index="0" style="--wp-swiper-description-size:20px;--wp-swiper-description-size-tablet:17px">', $html );
		// The second: the plugin's size (none of its own), nor one of the slider's.
		$this->assertStringContainsString( '<div class="wp-swiper__caption"><div class="wp-swiper__caption-text">Plain', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item" data-wp-swiper-index="2">', $html );
		$this->assertStringNotContainsString( 'wp-swiper-caption-size:', $this->root_style( $html ) );
	}

	public function test_caption_font_size_for_all_slides() {
		// The Caption page's Font size: per device, empty for none (16px).
		$field = WP_Swiper_Settings::fields()['caption_size'];
		$this->assertSame( 'caption', $field['section'] );
		$this->assertTrue( $field['responsive'] );
		$this->assertSame( '', WP_Swiper_Settings::defaults()['caption_size'] );
		$clean = WP_Swiper_Settings::sanitize( array( 'caption_size' => '22', 'caption_size_tablet' => '', 'caption_size_mobile' => '500' ) );
		$this->assertSame( array( 22, '', 200 ), array( $clean['caption_size'], $clean['caption_size_tablet'], $clean['caption_size_mobile'] ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize( array() )['caption_size'] );

		$slides                    = $this->slides( 3 );
		$slides[0]['caption']      = 'Own';
		$slides[0]['caption_size'] = '30,,';
		$slides[1]['caption']      = 'Plain';
		$slides[2]['caption']      = 'Phone only';
		$slides[2]['caption_size'] = ',,12';
		$html = $this->render( array( 'caption_size' => 22, 'caption_size_tablet' => '', 'caption_size_mobile' => 14 ), $slides );

		// On the block, for every slide.
		$this->assertStringContainsString( '--wp-swiper-caption-size:22px;--wp-swiper-caption-size-mobile:14px', $this->root_style( $html ) );
		// A slide's own on every screen after it (its desktop one on tablet and phone).
		$this->assertStringContainsString( '<div class="wp-swiper__caption" style="--wp-swiper-caption-size:30px;--wp-swiper-caption-size-tablet:30px;--wp-swiper-caption-size-mobile:30px">', $html );
		// None: the block's.
		$this->assertStringContainsString( '<div class="wp-swiper__caption"><div class="wp-swiper__caption-text">Plain', $html );
		// Only on phones: the block's on the larger screens.
		$this->assertStringContainsString( '<div class="wp-swiper__caption" style="--wp-swiper-caption-size-mobile:12px">', $html );

		// Art too.
		$art = $this->render( array( 'effect' => 'art', 'caption_size' => 18 ), $slides );
		$this->assertStringContainsString( '--wp-swiper-caption-size:18px', $art );
		$this->assertStringContainsString( '<div class="wp-swiper-art__caption" style="--wp-swiper-caption-size:30px;--wp-swiper-caption-size-tablet:30px;--wp-swiper-caption-size-mobile:30px">', $art );

		$this->assertSame( array( '--x:20px', '--x-tablet:16px', '--x-mobile:16px' ), WP_Swiper_Settings::own_font_size_vars( '20,16,', 'x' ) );
		$this->assertSame( array(), WP_Swiper_Settings::own_font_size_vars( '', 'x' ) );
	}

	public function test_old_caption_font_size_becomes_the_caption_pages() {
		// Saved with the old Font size of the whole slider (until 2.8): now the
		// Caption page's, shown there and kept when saved.
		$settings = WP_Swiper_Settings::get( $this->slider( null, array( '_version' => 6, 'caption_font' => 'custom', 'caption_font_size' => 24, 'caption_font_size_mobile' => '15' ) ) );
		$this->assertSame( array( '24', '', '15' ), array( $settings['caption_size'], $settings['caption_size_tablet'], $settings['caption_size_mobile'] ) );
		$this->assertSame( '24,,15', WP_Swiper_Settings::caption_sizes( $settings ) );
		// "Default" then: none.
		$settings = WP_Swiper_Settings::get( $this->slider( null, array( '_version' => 6, 'caption_font' => 'default', 'caption_font_size' => 30 ) ) );
		$this->assertSame( '', $settings['caption_size'] );
		// Its own since: that one.
		$settings = WP_Swiper_Settings::get( $this->slider( null, array( '_version' => 6, 'caption_size' => 19, 'caption_font' => 'custom', 'caption_font_size' => 24 ) ) );
		$this->assertSame( 19, (int) $settings['caption_size'] );
		$this->assertSame( '19,,', WP_Swiper_Settings::caption_sizes( $settings ) );
	}

	public function test_font_weight_per_slide() {
		$this->assertArrayNotHasKey( 'caption_weight', WP_Swiper_Settings::fields() );
		$this->assertArrayNotHasKey( 'description_weight', WP_Swiper_Settings::fields() );

		$slides                          = $this->described_slides();
		$slides[0]['caption']            = 'Bold';
		$slides[0]['caption_weight']     = '700,,400';
		$slides[0]['description_weight'] = '300,,';
		$slides[0]['description_size']   = '18,,';
		$slides[1]['caption']            = 'As the tag';
		$html = $this->render( array(), $slides );

		// A class (none keeps the tag's / page's own) and one weight for every
		// screen (an old per-device value: its desktop one).
		$this->assertStringContainsString( '<div class="wp-swiper__caption wp-swiper__caption--weight" style="--wp-swiper-caption-weight:700">', $html );
		$this->assertStringNotContainsString( 'weight-mobile', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item is-active wp-swiper-descriptions__item--weight" data-wp-swiper-index="0" style="--wp-swiper-description-size:18px;--wp-swiper-description-weight:300">', $html );
		$this->assertStringContainsString( '<div class="wp-swiper__caption"><div class="wp-swiper__caption-text">As the tag', $html );
		$this->assertNotContains( 'wp-swiper--caption-weight', $this->root_classes( $html ) );

		// A weight saved for the whole slider (until 2.8): still applies to the others.
		$html = $this->render( array( 'caption_weight' => '800' ), $slides );
		$this->assertContains( 'wp-swiper--caption-weight', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-caption-weight:800', $this->root_style( $html ) );
	}

	public function test_color_per_slide() {
		$slides                         = $this->described_slides();
		$slides[0]['caption']           = 'Red';
		$slides[0]['caption_color']     = '#ff0000';
		$slides[0]['description_color'] = 'rgba(0,0,255,0.5)';
		$slides[1]['caption']           = 'As the page';
		$html = $this->render( array( 'caption_color' => '#eeeeee' ), $slides );

		// On the caption itself, over the Caption page's on the slider.
		$this->assertStringContainsString( '<div class="wp-swiper__caption" style="--wp-swiper-caption-color:#ff0000">', $html );
		$this->assertStringContainsString( '--wp-swiper-caption-color:#eeeeee', $this->root_style( $html ) );
		$this->assertStringContainsString( '<div class="wp-swiper__caption"><div class="wp-swiper__caption-text">As the page', $html );
		// A description's: a class (none keeps the page's) and its color.
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item is-active wp-swiper-descriptions__item--color" data-wp-swiper-index="0" style="--wp-swiper-description-color:rgba(0,0,255,0.5)">', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-descriptions__item" data-wp-swiper-index="2">', $html );

		// Saved before or posted by the preview: checked again.
		$slides[0]['caption_color'] = 'red;position:fixed';
		$this->assertStringNotContainsString( 'position:fixed', $this->render( array(), $slides ) );
	}

	public function test_sanitize_slide_color() {
		$this->assertSame( '#ff0000', WP_Swiper_Settings::sanitize_slide_color( '#FF0000' ) );
		$this->assertSame( '#abc', WP_Swiper_Settings::sanitize_slide_color( '#abc' ) );
		$this->assertSame( 'rgba(255,0,0,0.5)', WP_Swiper_Settings::sanitize_slide_color( 'rgba(255, 0, 0, .5)' ) );
		$this->assertSame( 'rgba(255,0,0,0.25)', WP_Swiper_Settings::sanitize_slide_color( 'rgba(300,0,0,0.25)' ) );
		$this->assertSame( 'rgba(1,2,3,0)', WP_Swiper_Settings::sanitize_slide_color( 'rgba(1,2,3,0)' ) );
		// Full opacity: its hex.
		$this->assertSame( '#010203', WP_Swiper_Settings::sanitize_slide_color( 'rgba(1,2,3,1)' ) );
		foreach ( array( '', 'red', 'rgba(1,2,3,2)', 'rgb(1,2,3)', '#12345', 'rgba(1,2,3,0.5);x:y', 'expression(alert(1))' ) as $bad ) {
			$this->assertSame( '', WP_Swiper_Settings::sanitize_slide_color( $bad ), $bad );
		}
	}

	public function test_sanitize_font_weights() {
		$this->assertSame( '600', WP_Swiper_Settings::sanitize_font_weights( '600' ) );
		$this->assertSame( '700', WP_Swiper_Settings::sanitize_font_weights( '700,,400' ) );
		$this->assertSame( '100', WP_Swiper_Settings::sanitize_font_weights( array( '100', '650', 'bold' ) ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_font_weights( '650' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_font_weights( ',700,' ) );
		$this->assertSame( '300', WP_Swiper_Settings::legacy_font_weights( array( 'caption_weight' => '300' ), 'caption' ) );
		$this->assertSame( '', WP_Swiper_Settings::legacy_font_weights( array( 'caption_weight' => '' ), 'caption' ) );
		$this->assertSame( '', WP_Swiper_Settings::legacy_font_weights( array(), 'description' ) );
	}

	public function test_sanitize_font_sizes() {
		$this->assertSame( '20,16,', WP_Swiper_Settings::sanitize_font_sizes( '20,16,' ) );
		$this->assertSame( '200,8,', WP_Swiper_Settings::sanitize_font_sizes( array( 999, 2, 'x' ) ) );
		// Without a desktop size: the plugin's there, the others kept.
		$this->assertSame( ',14,12', WP_Swiper_Settings::sanitize_font_sizes( ',14,12' ) );
		$this->assertSame( array( '--x-tablet:14px', '--x-mobile:12px' ), WP_Swiper_Settings::font_size_vars( ',14,12', 'x' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_font_sizes( ',,' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_font_sizes( '' ) );
	}

	public function test_font_size_saved_for_the_whole_slider() {
		// Until 2.8 a slider had one size: it still applies to the slides without their own.
		$html = $this->render( array( 'caption_font' => 'custom', 'caption_font_size' => 22, 'caption_font_size_mobile' => '12' ), $this->described_slides() );
		$this->assertStringContainsString( '--wp-swiper-caption-size:22px;--wp-swiper-caption-size-mobile:12px', $this->root_style( $html ) );
		$this->assertSame( '22,,12', WP_Swiper_Settings::legacy_font_sizes( array( 'caption_font' => 'custom', 'caption_font_size' => 22, 'caption_font_size_tablet' => '', 'caption_font_size_mobile' => '12' ), 'caption' ) );
		$this->assertSame( '', WP_Swiper_Settings::legacy_font_sizes( array( 'caption_font' => 'default', 'caption_font_size' => 30 ), 'caption' ) );
		// One saved since has no such keys.
		$this->assertArrayNotHasKey( 'caption_font', WP_Swiper_Settings::get( $this->slider( null, array( '_version' => 6, 'height' => 300 ) ) ) );

		// Saved before it had the choice (until 2.8): a size of its own stays Custom.
		$this->assertSame( 'default', WP_Swiper_Settings::get( $this->slider( null, array( '_version' => 6, 'caption_font_size' => 16 ) ) )['caption_font'] );
		$this->assertSame( 'custom', WP_Swiper_Settings::get( $this->slider( null, array( '_version' => 6, 'caption_font_size' => 20 ) ) )['caption_font'] );
		$this->assertSame( 'custom', WP_Swiper_Settings::get( $this->slider( null, array( '_version' => 6, 'description_font_size' => 16, 'description_font_size_mobile' => '14' ) ) )['description_font'] );
		$this->assertSame( 'default', WP_Swiper_Settings::get( $this->slider( null, array( 'caption_font' => 'default', 'caption_font_size' => 30 ) ) )['caption_font'] );
	}

	public function test_descriptions_align_per_paragraph() {
		// No Alignment field any more: each paragraph has its own (the editor's
		// buttons); the block sets none, the lightbox text starts on the left.
		$this->assertArrayNotHasKey( 'description_align', WP_Swiper_Settings::fields() );
		$this->assertArrayNotHasKey( 'description_align', WP_Swiper_Settings::defaults() );
		$this->assertStringNotContainsString( '--wp-swiper-description-align', $this->descriptions_block( $this->render( array(), $this->described_slides() ) ) );
		$config = $this->config( $this->render( array( 'lightbox' => true, 'description_lightbox_place' => 'under' ), $this->described_slides() ) );
		$this->assertSame( 'left', $config['lightbox']['text']['align'] );

		// An old left one needs nothing; saving drops the old value.
		$this->assertSame( '', WP_Swiper_Settings::legacy_description_align( array( 'description_align' => 'left' ) ) );
		$this->assertSame( 'right', WP_Swiper_Settings::legacy_description_align( array( 'description_align' => 'right' ) ) );
		$this->assertArrayNotHasKey( 'description_align', WP_Swiper_Settings::sanitize( array( 'description_align' => 'center' ) ) );
	}

	public function test_lightbox_descriptions_under_the_image() {
		$values = array(
			'lightbox'                          => true,
			'description_lightbox_place'        => 'under',
			'description_lightbox_width'        => 60,
			'description_lightbox_width_tablet' => '',
			'description_lightbox_max'          => 700,
			// Saved while it had an Alignment field (until 2.8): still applies.
			'description_align'                 => 'center',
		);
		$config = $this->config( $this->render( $values, $this->described_slides() ) );

		// Tablet inherits the desktop's; mobile has its own default (the whole width).
		$this->assertSame(
			array(
				'width' => array(
					'desktop' => 60,
					'tablet'  => 60,
					'mobile'  => 100,
				),
				'max'   => 700,
				'align' => 'center',
			),
			$config['lightbox']['text']
		);

		// Not without descriptions in the lightbox.
		$off = $this->config( $this->render( array( 'description_lightbox' => false ) + $values, $this->described_slides() ) );
		$this->assertFalse( $off['lightbox']['text'] );
		$none = $this->config( $this->render( $values ) );
		$this->assertFalse( $none['lightbox']['text'] );
	}

	/* Image proportions ---------------------------------------------- */

	public function test_distribute_config_per_device() {
		$values = array( 'slide_ratio' => '4-3', 'slide_ratio_mobile' => 'off', 'slides_per_view' => 5, 'space_between' => 10, 'height' => 200 );
		$config = $this->config( $this->render( $values ) );

		$this->assertEquals( array( 'desktop' => 1.3333, 'tablet' => 1.3333, 'mobile' => false ), $config['distribute']['ratio'] );
		$this->assertSame( 'between', $config['distribute']['mode'] );
		// The set space and count stay: the least space, the images in the row.
		$this->assertEquals( 10, $config['spaceBetween']['desktop'] );
		$this->assertEquals( 5, $config['slidesPerView']['desktop'] );

		$around = $this->config( $this->render( array( 'slide_distribution' => 'around' ) + $values ) );
		$this->assertSame( 'around', $around['distribute']['mode'] );
	}

	public function test_no_distribute_without_proportions() {
		$this->assertFalse( $this->config( $this->render( array( 'slide_distribution' => 'around' ) ) )['distribute'] );
		$this->assertFalse( $this->config( $this->render( array( 'slide_ratio' => '1-1', 'effect' => 'fade' ) ) )['distribute'] );
	}

	public function test_distribute_keeps_the_height_as_set() {
		$style = $this->root_style( $this->render( array( 'slide_ratio' => '1-1', 'height' => 240 ) ) );
		$this->assertStringContainsString( '--wp-swiper-height:240px', $style );
	}

	/* Collage order -------------------------------------------------- */

	public function test_collage_config_has_the_order_and_the_photo_ids() {
		$slides = $this->slides( 3 );
		$ids    = array_map( 'intval', wp_list_pluck( $slides, 'id' ) );
		$html   = $this->render( array( 'effect' => 'collage', 'collage_order_tablet' => $ids[1] . ',' . $ids[2] ), $slides );
		$config = $this->config( $html, 'data-wp-swiper-collage' );

		$this->assertSame( $ids, $config['ids'] );
		$this->assertFalse( $config['order']['desktop'] );
		$this->assertSame( array( 1, 2 ), $config['order']['tablet'] );
		// Mobile: the tablet's.
		$this->assertSame( array( 1, 2 ), $config['order']['mobile'] );
	}

	public function test_collage_config_has_the_mosaic_per_device() {
		$config = $this->config( $this->render( array( 'effect' => 'collage', 'collage_layout' => 'random', 'collage_mosaic_tablet' => 'H0.3[1,0]' ) ), 'data-wp-swiper-collage' );
		$this->assertSame( array( 'desktop' => '', 'tablet' => 'H0.3[1,0]', 'mobile' => 'H0.3[1,0]' ), $config['mosaic'] );
	}

	public function test_collage_config_has_the_template_sizes_per_device() {
		$config = $this->config( $this->render( array( 'effect' => 'collage', 'collage_layout' => 'feature-left', 'collage_cuts' => 'feature-left:V0.5[0,H0.3[1,2]]', 'collage_cuts_mobile' => 'nope' ) ), 'data-wp-swiper-collage' );
		// Tablet: the desktop's; checked again (mobile's is not one: the tablet's).
		$this->assertSame( array( 'desktop' => 'feature-left:V0.5[0,H0.3[1,2]]', 'tablet' => 'feature-left:V0.5[0,H0.3[1,2]]', 'mobile' => '' ), $config['cuts'] );
	}

	public function test_collage_config_has_the_scattered_layout_per_device() {
		$config = $this->config(
			$this->render(
				array(
					'effect'                => 'collage',
					'collage_layout'        => 'scatter',
					'collage_count'         => 7,
					'collage_count_mobile'  => 4,
					'collage_gap'           => 14,
					'collage_gap_mobile'    => 6,
					'collage_cuts'          => 'scatter:1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8',
					'collage_cuts_tablet'   => '',
					'collage_cuts_mobile'   => 'scatter:bad',
				),
				$this->slides( 8 )
			),
			'data-wp-swiper-collage'
		);
		$this->assertSame( 'scatter', $config['layout']['desktop'] );
		$this->assertEquals( array( 'desktop' => 7, 'tablet' => 7, 'mobile' => 4 ), $config['count'] );
		$this->assertEquals( array( 'desktop' => 14, 'tablet' => 14, 'mobile' => 6 ), $config['gap'] );
		// Tablet: the desktop's; checked again (mobile's is not one: none, worked out).
		$this->assertSame( array( 'desktop' => 'scatter:1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8', 'tablet' => 'scatter:1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8', 'mobile' => '' ), $config['cuts'] );
	}

	public function test_collage_config_has_the_freeform_layout_per_device() {
		$config = $this->config(
			$this->render(
				array(
					'effect'              => 'collage',
					'collage_layout'      => 'freeform',
					'collage_count'       => 2,
					'collage_cuts'        => 'freeform:1.6;0.1,0.1,0.6,0.8;0.75,0.2,0.5,0.5',
					'collage_cuts_tablet' => '',
					'collage_cuts_mobile' => 'freeform:bad',
				),
				$this->slides( 3 )
			),
			'data-wp-swiper-collage'
		);
		$this->assertSame( 'freeform', $config['layout']['desktop'] );
		// Tablet: the desktop's; mobile's is not one: none, worked out.
		$this->assertSame( array( 'desktop' => 'freeform:1.6;0.1,0.1,0.6,0.8;0.75,0.2,0.5,0.5', 'tablet' => 'freeform:1.6;0.1,0.1,0.6,0.8;0.75,0.2,0.5,0.5', 'mobile' => '' ), $config['cuts'] );
	}

	public function test_image_shadow_and_its_room() {
		$on    = array( 'image_shadow' => true, 'image_shadow_x' => 0, 'image_shadow_y' => 8, 'image_shadow_blur' => 20, 'image_shadow_spread' => 0, 'image_shadow_color' => 'rgba(0,0,0,0.3)' );
		$html  = $this->render( $on + array( 'effect' => 'collage' ) );
		$style = $this->root_style( $html );

		$this->assertContains( 'wp-swiper--shadow', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-shadow:0px 8px 20px 0px rgba(0,0,0,0.3)', $style );
		// Out of the slider's box, the photos keep their size: it cuts as far past its sides as the shadow reaches at most;
		// the room per side (top right bottom left) for a browser that cannot (Safari: kept inside).
		$this->assertStringContainsString( '--wp-swiper-shadow-reach:28px', $style );
		$this->assertStringContainsString( '--wp-swiper-shadow-room:12px 20px 28px 20px', $style );
		$css = file_get_contents( WP_SWIPER_DIR . 'assets/css/frontend.css' );
		$this->assertStringContainsString( '@supports (overflow-clip-margin: 1px)', $css );
		$this->assertStringContainsString( 'overflow-clip-margin: var(--wp-swiper-shadow-reach, 0px);', $css );
		// overflow: clip, never visible: the slides waiting past the edges make the page no wider.
		$this->assertStringNotContainsString( 'clip-path: var(--wp-swiper-shadow-clip', $css );
		// A collage keeps no room: nothing of it is cut off.
		$this->assertStringNotContainsString( '--wp-swiper-shadow-room', file_get_contents( WP_SWIPER_DIR . 'assets/css/collage.css' ) );

		$this->assertContains( 'wp-swiper--shadow', $this->root_classes( $this->render( $on + array( 'effect' => 'coverflow' ) ) ) );
		// Fade: its one photo, the class the CSS frames it by too.
		$fade = $this->root_classes( $this->render( $on + array( 'effect' => 'fade' ) ) );
		$this->assertContains( 'wp-swiper--shadow', $fade );
		$this->assertContains( 'wp-swiper--effect-fade', $fade );
		// None where it does not apply: one slide in view.
		$single = $this->render( $on + array( 'effect' => 'slide', 'slides_per_view' => 1, 'slides_per_view_tablet' => '', 'slides_per_view_mobile' => '' ) );
		$this->assertNotContains( 'wp-swiper--shadow', $this->root_classes( $single ) );
		$this->assertStringNotContainsString( '--wp-swiper-shadow', $this->root_style( $single ) );
	}

	public function test_collage_height_auto_per_device() {
		$html   = $this->render( array( 'effect' => 'collage', 'collage_layout' => 'scatter', 'height_unit' => 'px', 'height_unit_tablet' => 'auto', 'height_unit_mobile' => 'px' ) );
		$config = $this->config( $html, 'data-wp-swiper-collage' );
		$this->assertSame( array( 'desktop' => false, 'tablet' => true, 'mobile' => false ), $config['fill'] );
		// The grid takes fixed proportions only on the devices with "Auto" (frontend.js).
		$this->assertStringContainsString( '<div class="wp-swiper-collage__grid"></div>', $html );

		$all = $this->render( array( 'effect' => 'collage', 'height_unit' => 'auto' ) );
		$this->assertStringContainsString( '<div class="wp-swiper-collage__grid wp-swiper-collage__grid--ratio"></div>', $all );
	}

	public function test_collage_ids_follow_the_slides_shown() {
		$slides = $this->slides( 3 );
		// A slide whose photo is gone is skipped: the indexes stay those of the pool.
		$slides[1]['id'] = 999999;
		$ids             = array_map( 'intval', wp_list_pluck( $slides, 'id' ) );
		$config          = $this->config( $this->render( array( 'effect' => 'collage', 'collage_order' => $ids[2] . ',' . $ids[0] ), $slides ), 'data-wp-swiper-collage' );

		$this->assertSame( array( $ids[0], $ids[2] ), $config['ids'] );
		$this->assertSame( array( 1, 0 ), $config['order']['desktop'] );
	}

	/* Continuous scroll separator ------------------------------------ */

	public function test_marquee_separator_dot_after_every_slide() {
		$values = array(
			'marquee'                       => true,
			'marquee_separator'             => 'dot',
			'marquee_separator_size'        => 16,
			'marquee_separator_size_tablet' => 12,
			'marquee_separator_color'       => '#ff0000',
			'space_between'                 => 40,
			'space_between_tablet'          => 4,
		);
		$html   = $this->render( $values, $this->slides( 2 ) );
		$config = $this->config( $html );

		$this->assertContains( 'wp-swiper--separator', $this->root_classes( $html ) );
		// One per slide, its copies included (the loop copies it with its slide).
		$this->assertSame( 2 * $config['copies'], substr_count( $html, '<span class="wp-swiper__separator" aria-hidden="true"><span class="wp-swiper__separator-dot"></span></span>' ) );
		// The gap fits the separator on every device.
		$this->assertEquals( array( 'desktop' => 40, 'tablet' => 12, 'mobile' => 12 ), $config['spaceBetween'] );

		$style = $this->root_style( $html );
		$this->assertStringContainsString( '--wp-swiper-separator-size:16px', $style );
		$this->assertStringContainsString( '--wp-swiper-separator-size-tablet:12px', $style );
		$this->assertStringContainsString( '--wp-swiper-separator-size-mobile:12px', $style );
		$this->assertStringContainsString( '--wp-swiper-gap:40px', $style );
		$this->assertStringContainsString( '--wp-swiper-gap-tablet:12px', $style );
		$this->assertStringContainsString( '--wp-swiper-separator-color:#ff0000', $style );
	}

	public function test_marquee_separator_own_image() {
		$image = $this->image( 64, 32 );
		$html  = $this->render( array( 'marquee' => true, 'marquee_separator' => 'image', 'marquee_separator_image' => $image, 'marquee_separator_size' => 24 ), $this->slides( 3 ) );

		$this->assertSame( 1, preg_match( '#<span class="wp-swiper__separator" aria-hidden="true">(<img[^>]*>)</span>#', $html, $match ) );
		$this->assertStringContainsString( 'class="wp-swiper__separator-image"', $match[1] );
		$this->assertStringContainsString( 'alt=""', $match[1] );
		$this->assertStringContainsString( 'loading="lazy"', $match[1] );
		// A dot's color only for a dot.
		$this->assertStringNotContainsString( '--wp-swiper-separator-color', $this->root_style( $html ) );
	}

	public function test_no_separator_without_marquee_or_image() {
		$cases = array(
			'no marquee'       => array( 'marquee_separator' => 'dot' ),
			'none'             => array( 'marquee' => true ),
			'image not chosen' => array( 'marquee' => true, 'marquee_separator' => 'image' ),
		);
		foreach ( $cases as $name => $values ) {
			$html = $this->render( $values + array( 'space_between' => 10 ) );
			$this->assertStringNotContainsString( 'wp-swiper__separator', $html, $name );
			$this->assertNotContains( 'wp-swiper--separator', $this->root_classes( $html ), $name );
			$this->assertSame( 10, $this->config( $html )['spaceBetween']['desktop'], $name );
		}
	}

	/* An image's own position -------------------------------------------- */

	/**
	 * The style of slide `index` in some markup.
	 */
	private function slide_style( $html, $index = 0 ) {
		$this->assertSame( 1, preg_match( '/data-wp-swiper-index="' . $index . '" style="([^"]*)"/', $html, $match ), "No slide $index." );
		return html_entity_decode( $match[1], ENT_QUOTES );
	}

	public function test_slide_position_over_the_sliders_on_every_device() {
		$slides                = $this->slides( 2 );
		$slides[0]['position'] = '20 75';
		$html                  = $this->render( array( 'image_position' => 'top', 'image_position_mobile' => 'bottom' ), $slides );

		$style = $this->slide_style( $html, 0 );
		$this->assertStringContainsString( '--wp-swiper-img-position:20% 75%', $style );
		$this->assertStringContainsString( '--wp-swiper-img-position-tablet:20% 75%', $style );
		$this->assertStringContainsString( '--wp-swiper-img-position-mobile:20% 75%', $style );
		// The other slide: the slider's (on the block).
		$this->assertStringNotContainsString( '--wp-swiper-img-position', $this->slide_style( $html, 1 ) );
		$this->assertStringContainsString( '--wp-swiper-img-position:50% 0%', $this->root_style( $html ) );
	}

	public function test_slide_position_only_where_it_crops() {
		$slides                = $this->slides( 2 );
		$slides[0]['position'] = '20 75';

		// "Contain" crops nothing; nor do slides as wide as their images.
		$this->assertStringNotContainsString( '--wp-swiper-img-position', $this->slide_style( $this->render( array( 'image_fit' => 'contain' ), $slides ) ) );
		$this->assertStringNotContainsString( '--wp-swiper-img-position', $this->slide_style( $this->render( array( 'auto_width' => true ), $slides ) ) );
		// A collage and a background crop whatever Image fit says.
		$this->assertStringContainsString( '--wp-swiper-img-position:20% 75%', $this->slide_style( $this->render( array( 'image_fit' => 'contain', 'effect' => 'collage' ), $slides ) ) );
		$this->assertStringContainsString( '--wp-swiper-img-position:20% 75%', $this->slide_style( $this->render( array( 'image_fit' => 'contain', 'background' => true, 'background_class' => 'hero' ), $slides ) ) );
	}

	public function test_slide_position_not_for_videos_nor_when_invalid() {
		$slides = array(
			array(
				'id'       => $this->video(),
				'caption'  => '',
				'link'     => '',
				'new_tab'  => false,
				'poster'   => 0,
				'position' => '20 75',
			),
		);
		$this->assertStringNotContainsString( '--wp-swiper-img-position', $this->slide_style( $this->render( array(), $slides ) ) );

		$slides                = $this->slides( 1 );
		$slides[0]['position'] = '20% 75%;color:red';
		$this->assertSame( '--wp-swiper-img-ratio:2', $this->slide_style( $this->render( array(), $slides ) ) );
	}
}
