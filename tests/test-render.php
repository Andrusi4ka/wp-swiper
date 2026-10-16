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
		$this->assertTrue( $this->config( $html )['fill'] );
		$this->assertContains( 'wp-swiper--fill', $this->root_classes( $html ) );
		$this->assertStringNotContainsString( '--wp-swiper-height', $this->root_style( $html ) );
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

	public function test_captions_are_escaped_and_tag_whitelisted() {
		$html = $this->render( array( 'caption_tag' => 'script' ), $this->slides( 1, array( 'caption' => '<b>Bold</b> & co' ) ) );
		$this->assertStringContainsString( '<div class="wp-swiper__caption-text">&lt;b&gt;Bold&lt;/b&gt; &amp; co</div>', $html );

		$h2 = $this->render( array( 'caption_tag' => 'h2' ), $this->slides( 1, array( 'caption' => 'Title' ) ) );
		$this->assertStringContainsString( '<h2 class="wp-swiper__caption-text">Title</h2>', $h2 );
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

	public function test_background_overlay_and_minimum_heights() {
		$html  = $this->render(
			array(
				'background'                   => true,
				'background_class'             => 'hero',
				'background_overlay_opacity'   => 40,
				'background_overlay_color'     => '#102030',
				'background_min_height'        => '',
				'background_min_height_tablet' => 500,
				'background_min_height_mobile' => '',
			)
		);
		$style = $this->root_style( $html );

		$this->assertContains( 'wp-swiper--bg-overlay', $this->root_classes( $html ) );
		$this->assertStringContainsString( '--wp-swiper-bg-overlay:rgba(16,32,48,0.4)', $style );
		$this->assertStringNotContainsString( '--wp-swiper-bg-min-height:', $style );
		$this->assertStringContainsString( '--wp-swiper-bg-min-height-tablet:500px', $style );
		// Mobile inherits the tablet's.
		$this->assertStringContainsString( '--wp-swiper-bg-min-height-mobile:500px', $style );

		$plain = $this->render( array( 'background' => true, 'background_class' => 'hero' ) );
		$this->assertNotContains( 'wp-swiper--bg-overlay', $this->root_classes( $plain ) );
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
}
