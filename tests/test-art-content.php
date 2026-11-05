<?php
/**
 * Art, Content in a window (Ring, Grid + circle, Grid + square, SVG shape):
 * an image over text in one window instead of a photo
 * (WP_Swiper_Render::content_box(), as the Collage's Content in a place),
 * its window per device in the config for art.js; not for the other Styles,
 * not as a background, not without a file (SVG shape). The "+ text" Styles
 * (until 3.4) became their Style with it in the middle (merge_art_center()).
 */

class Test_WP_Swiper_Art_Content extends WP_Swiper_TestCase {

	private function values( $more = array() ) {
		return $more + array(
			'effect'           => 'art',
			'art_style'        => 'shape',
			'art_shape'        => WP_Swiper_Svg_Shape::sanitize( file_get_contents( __DIR__ . '/data/ex-3.svg' ) ),
			'art_content'      => true,
			'art_content_text' => '<p>Hello</p>',
		);
	}

	private function art_config( $html ) {
		return $this->config( $html, 'data-wp-swiper-art' );
	}

	public function test_off_by_default() {
		$this->assertFalse( WP_Swiper_Settings::defaults()['art_content'] );
		$this->assertSame( 'rgba(0,0,0,0)', WP_Swiper_Settings::defaults()['art_content_bg'] );
		$html = $this->render( $this->values( array( 'art_content' => false ) ) );
		$this->assertStringNotContainsString( 'wp-swiper-art__content-pool', $html );
		$this->assertArrayNotHasKey( 'content', $this->art_config( $html ) );
	}

	public function test_the_content_and_its_window_per_device() {
		$html = $this->render( $this->values( array( 'art_content_window' => 2, 'art_content_window_mobile' => 1, 'art_content_bg' => 'rgba(30,60,90,1)' ) ) );
		// On the page, hidden (art.js moves this node into its window), drawn by content_box().
		$this->assertSame( 1, preg_match( '#<div class="wp-swiper-art__content-pool" hidden><div class="wp-swiper-content wp-swiper-content--center" style="([^"]*)"><div class="wp-swiper-content__inner"><div class="wp-swiper-content__text"><p>Hello</p></div></div></div></div>#', $html, $match ) );
		// The color as kept (an opaque one as hex).
		$this->assertStringContainsString( '--wp-swiper-content-bg:#1e3c5a', $match[1] );
		$this->assertSame( array( 'desktop' => 2, 'tablet' => 2, 'mobile' => 1 ), $this->art_config( $html )['content']['window'] );
		// Its look is frontend.css's, its text fitted by content.js (art.js's dependency).
		$this->assertTrue( wp_style_is( 'wp-swiper', 'enqueued' ) );
		WP_Swiper_Art::register_assets();
		$this->assertContains( 'wp-swiper-content', wp_scripts()->query( 'wp-swiper-art' )->deps );
		$this->assertTrue( wp_script_is( 'wp-swiper-content', 'registered' ) );
	}

	public function test_ring_and_the_grids_with_a_circle_or_square_too() {
		foreach ( array( 'ring', 'grid-circle', 'grid-square' ) as $style ) {
			$html = $this->render( $this->values( array( 'art_style' => $style ) ), $this->slides( 4 ) );
			$this->assertStringContainsString( '<div class="wp-swiper-art__content-pool" hidden><div class="wp-swiper-content', $html, $style );
			$this->assertSame( array( 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ), $this->art_config( $html )['content']['window'], $style );
			$this->assertTrue( WP_Swiper_Art::has_content( $style ), $style );
		}
		// Not Grid shapes (yet), nor Text mask.
		foreach ( array( 'grid-shapes', 'text' ) as $style ) {
			$this->assertFalse( WP_Swiper_Art::has_content( $style ), $style );
		}
		$this->assertStringNotContainsString( 'wp-swiper-art__content-pool', $this->render( $this->values( array( 'art_style' => 'grid-shapes' ) ) ) );
		$this->assertStringNotContainsString( 'wp-swiper-art__content-pool', $this->render( $this->values( array( 'effect' => 'text-mask' ) ) ) );
		// SVG shape without a file yet: no shape, no content.
		$this->assertStringNotContainsString( 'wp-swiper-art__content-pool', $this->render( $this->values( array( 'art_shape' => '' ) ) ) );
		// A round window: its image and text in the square inside it (art.css).
		$css = file_get_contents( WP_SWIPER_DIR . 'assets/css/art.css' );
		$this->assertStringContainsString( ".wp-swiper-art__piece--circle > .wp-swiper-content > .wp-swiper-content__inner {
	width: 70.71%;
	height: 70.71%;", $css );
	}

	public function test_the_text_styles_became_their_style_with_it_in_the_middle() {
		$this->assertSame( array( 'ring', 'grid-circle', 'grid-square', 'grid-shapes', 'shape' ), array_keys( WP_Swiper_Art::styles() ) );
		$styles = array(
			'ring-text'        => 'ring',
			'grid-circle-text' => 'grid-circle',
			'grid-square-text' => 'grid-square',
		);
		foreach ( $styles as $old => $style ) {
			$this->assertFalse( file_exists( WP_SWIPER_DIR . 'assets/images/art/' . $old . '.svg' ), $old );
			$saved = WP_Swiper_Settings::get(
				$this->slider(
					array(),
					array(
						'effect'                       => 'art',
						'art_style'                    => $old,
						'art_opening'                  => true,
						'art_center_image'             => 7,
						'art_center_image_size_mobile' => 70,
						'art_center_text'              => '<p>Hello</p>',
						'art_center_font_size'         => 24,
						'art_center_bg'                => 'rgba(10,20,30,0.5)',
					)
				)
			);
			$this->assertSame( $style, $saved['art_style'], $old );
			// On (as Content in the middle was by default), in the middle one.
			$this->assertTrue( $saved['art_content'], $old );
			$this->assertSame( 1, $saved['art_content_window'], $old );
			$this->assertSame( 7, $saved['art_content_image'], $old );
			// Its values; its defaults then where none was kept (60%, 12px).
			$this->assertSame( 60, $saved['art_content_image_size'], $old );
			$this->assertSame( 70, $saved['art_content_image_size_mobile'], $old );
			$this->assertSame( '<p>Hello</p>', $saved['art_content_text'], $old );
			$this->assertSame( 24, $saved['art_content_font_size'], $old );
			$this->assertSame( 12, $saved['art_content_padding'], $old );
			$this->assertSame( 'rgba(10,20,30,0.5)', $saved['art_content_bg'], $old );
			$this->assertArrayNotHasKey( 'art_center_text', $saved, $old );
			// Ring + text went all around: no Opening.
			$this->assertSame( 'ring-text' !== $old, $saved['art_opening'], $old );
			// Drawn as such: the content in the middle window.
			$html = WP_Swiper_Render::render_slider( 1, $this->slides( 4 ), $saved );
			$this->assertStringContainsString( 'wp-swiper-art--' . $style . ' ', $html, $old );
			$this->assertStringContainsString( '<p>Hello</p>', $html, $old );
			$this->assertSame( 1, $this->art_config( $html )['content']['window']['desktop'], $old );
		}
		// Content in the middle off: none (the middle stays empty, now a photo there).
		$off = WP_Swiper_Settings::get( $this->slider( array(), array( 'effect' => 'art', 'art_style' => 'ring-text', 'art_center' => false ) ) );
		$this->assertFalse( $off['art_content'] );
		// In another effect's saved Layout too.
		$layouts = WP_Swiper_Settings::get( $this->slider( array(), array( 'effect' => 'slide', 'layouts' => array( 'art' => array( 'art_style' => 'grid-square-text', 'art_center_text' => '<p>Hi</p>' ) ) ) ) )['layouts'];
		$this->assertSame( 'grid-square', $layouts['art']['art_style'] );
		$this->assertSame( '<p>Hi</p>', $layouts['art']['art_content_text'] );
		// The old fields and their block are gone.
		$this->assertArrayNotHasKey( 'art_center', WP_Swiper_Settings::fields() );
		$this->assertArrayNotHasKey( 'art_middle', WP_Swiper_Settings::blocks()['layout'] );
	}

	public function test_not_as_a_background() {
		$html = $this->render( $this->values( array( 'background' => true, 'background_class' => 'hero' ) ) );
		$this->assertStringNotContainsString( 'wp-swiper-art__content-pool', $html );
	}

	public function test_shortcodes_run() {
		add_shortcode(
			'wp_swiper_test_button',
			function () {
				return '<a class="btn" href="#go">Go</a>';
			}
		);
		$html = $this->render( $this->values( array( 'art_content_text' => '<p>Hi</p>[wp_swiper_test_button]' ) ) );
		remove_shortcode( 'wp_swiper_test_button' );
		$this->assertStringContainsString( '<p>Hi</p><a class="btn" href="#go">Go</a>', $html );
		$this->assertStringContainsString( 'wp-swiper-content--scroll', $html );
	}

	public function test_saved_values_sanitized() {
		$saved = WP_Swiper_Settings::sanitize(
			array(
				'effect'             => 'art',
				'art_style'          => 'shape',
				'art_content'        => '1',
				'art_content_window' => '500',
				'art_content_valign' => 'sideways',
				'art_content_text'   => '<p>Text</p><script>x</script>',
			)
		);
		$this->assertTrue( $saved['art_content'] );
		$this->assertSame( 100, $saved['art_content_window'] );
		$this->assertSame( 'center', $saved['art_content_valign'] );
		$this->assertStringNotContainsString( '<script', $saved['art_content_text'] );
	}

	public function test_a_block_of_its_own() {
		$blocks = WP_Swiper_Settings::blocks()['layout'];
		$this->assertSame( array( 'art_content', 'art_content_window', 'art_content_image', 'art_content_image_size', 'art_content_text', 'art_content_font_size', 'art_content_padding', 'art_content_valign', 'art_content_bg' ), $blocks['art_content']['fields'] );
		$fields = WP_Swiper_Settings::fields();
		foreach ( $blocks['art_content']['fields'] as $key ) {
			$this->assertSame( array( 'ring', 'grid-circle', 'grid-square', 'shape' ), $fields[ $key ]['styles'], $key );
			$this->assertSame( array( 'art' ), $fields[ $key ]['effects'], $key );
		}
	}
}
