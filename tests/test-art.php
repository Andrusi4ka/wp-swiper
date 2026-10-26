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
			$this->assertSame( 'layout', $field['section'], $key );
			$this->assertSame( $field, $fields[ $key ], $key );
		}
		// No other field is Art's.
		foreach ( $fields as $key => $field ) {
			if ( 0 !== strpos( $key, 'art_' ) && ! empty( $field['effects'] ) ) {
				$this->assertNotContains( 'art', $field['effects'], $key );
			}
		}
		// Its blocks first on the Layout page.
		$this->assertSame( array( 'art', 'art_label' ), array_slice( array_keys( WP_Swiper_Settings::blocks()['layout'] ), 0, 2 ) );
	}

	public function test_ring_markup() {
		$slides = $this->slides( 3 );
		$html   = $this->render( array( 'effect' => 'art', 'art_ring_count' => 5, 'art_ring_opening' => 72, 'art_label_title' => 'Hannah <b>x</b>', 'art_label_subtitle' => 'Photography', 'css_class' => 'my-art', 'css_id' => 'hero' ), $slides );

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
				'style'   => 'ring',
				'gap'     => 8,
				'radius'  => 14,
				'count'   => 5,
				'center'  => array( 'desktop' => 46, 'tablet' => 46, 'mobile' => 46 ),
				'opening' => 72,
				'order'   => array( 'desktop' => false, 'tablet' => false, 'mobile' => false ),
				'ids'     => array_map( 'intval', wp_list_pluck( $slides, 'id' ) ),
			),
			$config
		);
		// The label, as plain text (markup shown, never run; saving strips it).
		$this->assertStringContainsString( '<span class="wp-swiper-art__title">Hannah &lt;b&gt;x&lt;/b&gt;</span>', $html );
		$this->assertStringContainsString( '<span class="wp-swiper-art__subtitle">Photography</span>', $html );
		$this->assertStringContainsString( '--wp-swiper-art-size:560px', $html );
	}

	public function test_no_label_without_one() {
		$this->assertStringNotContainsString( 'wp-swiper-art__label', $this->render( array( 'effect' => 'art', 'art_ring_opening' => 72 ) ) );
	}

	public function test_photos_all_around_by_default_with_no_label() {
		$html = $this->render( array( 'effect' => 'art', 'art_label_title' => 'Hannah' ) );
		$this->assertStringContainsString( '&quot;opening&quot;:0', $html );
		// No room for the label in a closed ring; from 20 degrees on there is.
		$this->assertStringNotContainsString( 'wp-swiper-art__label', $html );
		$this->assertStringNotContainsString( 'wp-swiper-art__label', $this->render( array( 'effect' => 'art', 'art_ring_opening' => 19, 'art_label_title' => 'Hannah' ) ) );
		$this->assertStringContainsString( 'wp-swiper-art__label', $this->render( array( 'effect' => 'art', 'art_ring_opening' => 20, 'art_label_title' => 'Hannah' ) ) );
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
		$this->assertNotContains( 'art_ring_center', WP_Swiper_Art::blocks()['art']['fields'] );
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
				'effect'             => 'art',
				'art_style'          => 'nonsense',
				'art_ring_count'     => '40',
				'art_label_title'    => '<script>x</script>Hannah',
				'art_label_subtitle' => str_repeat( 'a', 200 ),
			)
		);
		$this->assertSame( 'art', $clean['effect'] );
		$this->assertSame( 'ring', $clean['art_style'] );
		$this->assertSame( 16, $clean['art_ring_count'] );
		$this->assertSame( 'Hannah', $clean['art_label_title'] );
		$this->assertSame( 120, strlen( $clean['art_label_subtitle'] ) );
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
