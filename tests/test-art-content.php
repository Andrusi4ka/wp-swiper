<?php
/**
 * Art, SVG shape, Content in a window: an image over text in one window of
 * the shape instead of a photo (WP_Swiper_Render::content_box(), as the
 * Collage's Content in a place), its window per device in the config for
 * art.js; not for the other Styles, not as a background, not without a file.
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

	public function test_only_svg_shape() {
		foreach ( array( 'ring', 'grid-shapes', 'grid-circle' ) as $style ) {
			$this->assertStringNotContainsString( 'wp-swiper-art__content-pool', $this->render( $this->values( array( 'art_style' => $style ) ) ), $style );
		}
		// No file yet: no shape, no content.
		$this->assertStringNotContainsString( 'wp-swiper-art__content-pool', $this->render( $this->values( array( 'art_shape' => '' ) ) ) );
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

	public function test_a_block_of_its_own_for_svg_shape() {
		$blocks = WP_Swiper_Settings::blocks()['layout'];
		$this->assertSame( array( 'art_content', 'art_content_window', 'art_content_image', 'art_content_image_size', 'art_content_text', 'art_content_font_size', 'art_content_padding', 'art_content_valign', 'art_content_bg' ), $blocks['art_content']['fields'] );
		$fields = WP_Swiper_Settings::fields();
		foreach ( $blocks['art_content']['fields'] as $key ) {
			$this->assertSame( array( 'shape' ), $fields[ $key ]['styles'], $key );
			$this->assertSame( array( 'art' ), $fields[ $key ]['effects'], $key );
		}
	}
}
