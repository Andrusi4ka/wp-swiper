<?php
/**
 * Collage, Content in a place (WP_Swiper_Render::collage_content()): an
 * image over text in one place instead of a photo, its place per device in
 * the config for collage.js; not for Justified rows or Masonry everywhere,
 * a background, or with neither image nor text.
 */

class Test_WP_Swiper_Collage_Content extends WP_Swiper_TestCase {

	private function values( $more = array() ) {
		return $more + array(
			'effect'               => 'collage',
			'collage_layout'       => 'grid',
			'collage_content'      => true,
			'collage_content_text' => '<p>Hello <strong>there</strong></p>',
		);
	}

	public function test_off_by_default() {
		$this->assertFalse( WP_Swiper_Settings::defaults()['collage_content'] );
		// No background unless one is chosen.
		$this->assertSame( 'rgba(0,0,0,0)', WP_Swiper_Settings::defaults()['collage_content_bg'] );
		$html = $this->render( array( 'effect' => 'collage' ) );
		$this->assertStringNotContainsString( 'wp-swiper-collage__content', $html );
		$this->assertArrayNotHasKey( 'content', $this->config( $html, 'data-wp-swiper-collage' ) );
	}

	public function test_the_content_and_its_place_per_device() {
		$html = $this->render( $this->values( array( 'collage_content_place' => 3, 'collage_content_place_mobile' => 1 ) ) );
		// On the page, hidden (collage.js moves this node into its place).
		$this->assertStringContainsString( '<div class="wp-swiper-collage__content-pool" hidden><div class="wp-swiper-collage__content wp-swiper-collage__content--center" style=', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-collage__content-text"><p>Hello <strong>there</strong></p></div>', $html );
		// Tablet as desktop, mobile its own.
		$this->assertSame( array( 'desktop' => 3, 'tablet' => 3, 'mobile' => 1 ), $this->config( $html, 'data-wp-swiper-collage' )['content']['place'] );
	}

	public function test_the_image_and_the_sizes() {
		$image = $this->image();
		$html  = $this->render(
			$this->values(
				array(
					'collage_content_image'             => $image,
					'collage_content_image_size'        => 40,
					'collage_content_font_size_mobile'  => 14,
					'collage_content_padding'           => 500,
					'collage_content_valign'            => 'bottom',
					'collage_content_bg'                => 'rgba(10,20,30,0.5)',
				)
			)
		);
		$this->assertSame( 1, preg_match( '/<div class="wp-swiper-collage__content-inner"><img [^>]*class="wp-swiper-collage__content-image"/', $html ) );
		$this->assertStringContainsString( 'wp-swiper-collage__content--bottom', $html );
		$this->assertStringContainsString( '--wp-swiper-collage-content-image:40%', $html );
		$this->assertStringContainsString( '--wp-swiper-collage-content-font-mobile:14px', $html );
		// Kept to its range.
		$this->assertStringContainsString( '--wp-swiper-collage-content-padding:200px', $html );
		$this->assertStringContainsString( '--wp-swiper-collage-content-bg:rgba(10,20,30,0.5)', $html );

		// The image alone is content enough.
		$this->assertStringContainsString( 'wp-swiper-collage__content-image', $this->render( $this->values( array( 'collage_content_image' => $image, 'collage_content_text' => '' ) ) ) );
	}

	public function test_none_without_image_or_text() {
		$this->assertStringNotContainsString( 'wp-swiper-collage__content', $this->render( $this->values( array( 'collage_content_text' => '' ) ) ) );
	}

	public function test_not_for_justified_or_masonry_everywhere() {
		$this->assertStringNotContainsString( 'wp-swiper-collage__content', $this->render( $this->values( array( 'collage_layout' => 'masonry' ) ) ) );
		$this->assertStringNotContainsString( 'wp-swiper-collage__content', $this->render( $this->values( array( 'collage_layout' => 'justified' ) ) ) );
		// Masonry on desktop, Grid on mobile: there (collage.js leaves it out on desktop).
		$this->assertStringContainsString( 'wp-swiper-collage__content-pool', $this->render( $this->values( array( 'collage_layout' => 'masonry', 'collage_layout_mobile' => 'grid' ) ) ) );
	}

	public function test_not_as_a_background() {
		$html = $this->render( $this->values( array( 'background' => true, 'background_class' => 'hero' ) ) );
		$this->assertStringNotContainsString( 'wp-swiper-collage__content', $html );
	}

	public function test_the_text_sanitized() {
		$html = $this->render( $this->values( array( 'collage_content_text' => '<p onclick="x()">Hi</p><script>alert(1)</script>' ) ) );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringContainsString( '<p>Hi</p>', $html );
	}

	public function test_shortcodes_run_and_the_place_scrolls() {
		add_shortcode(
			'wp_swiper_test_button',
			function () {
				return '<a class="btn" href="#go">Go</a><input name="q">';
			}
		);
		$html = $this->render( $this->values( array( 'collage_content_text' => '<p>Before</p>[wp_swiper_test_button]' ) ) );
		remove_shortcode( 'wp_swiper_test_button' );
		// Its markup as it gives it (after the text is sanitized: an input stays).
		$this->assertStringContainsString( '<p>Before</p><a class="btn" href="#go">Go</a><input name="q">', $html );
		$this->assertStringContainsString( 'wp-swiper-collage__content--scroll', $html );

		// Brackets that are no shortcode: cut as text (no scrolling).
		$html = $this->render( $this->values( array( 'collage_content_text' => '<p>[not one]</p>' ) ) );
		$this->assertStringContainsString( '<p>[not one]</p>', $html );
		$this->assertStringNotContainsString( '--scroll', $html );
	}

	public function test_not_inside_itself() {
		// A slider whose content shows itself: its shortcodes left out there.
		$id = $this->slider( null, $this->values() );
		update_post_meta( $id, WP_Swiper_Settings::META_SETTINGS, $this->values( array( 'collage_content_text' => '<p>Me</p>[wp_swiper id="' . $id . '"]' ) ) );
		$html = do_shortcode( '[wp_swiper id="' . $id . '"]' );
		$this->assertSame( 2, substr_count( $html, 'data-wp-swiper-collage=' ) );
		$this->assertSame( 2, substr_count( $html, '<p>Me</p>' ) );
	}

	public function test_saved_values_sanitized() {
		$saved = WP_Swiper_Settings::sanitize(
			array(
				'effect'                 => 'collage',
				'collage_content'        => '1',
				'collage_content_place'  => '99',
				'collage_content_valign' => 'middle',
				'collage_content_text'   => '<p>Text</p><script>x</script>',
			)
		);
		$this->assertTrue( $saved['collage_content'] );
		$this->assertSame( 30, $saved['collage_content_place'] );
		$this->assertSame( 'center', $saved['collage_content_valign'] );
		$this->assertStringNotContainsString( '<script', $saved['collage_content_text'] );
	}

	public function test_a_block_of_its_own_on_the_layout_page() {
		$blocks = WP_Swiper_Settings::blocks()['layout'];
		$this->assertSame( array( 'collage_content', 'collage_content_place', 'collage_content_image', 'collage_content_image_size', 'collage_content_text', 'collage_content_font_size', 'collage_content_padding', 'collage_content_valign', 'collage_content_bg' ), $blocks['collage_content']['fields'] );
		// Right after Size and fit.
		$keys = array_keys( $blocks );
		$this->assertSame( array_search( 'size', $keys, true ) + 1, array_search( 'collage_content', $keys, true ) );
	}
}
