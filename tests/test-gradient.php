<?php
/**
 * The Gradient over the photos (image_gradient_on, image_gradient): every
 * effect's; over each photo of a slider or collage, one across the whole
 * shape of Art and Text mask. Its value and CSS: test-text-mask.php.
 */
class Test_WP_Swiper_Gradient extends WP_Swiper_TestCase {

	const RED_TO_BLUE = 'linear;top;#ff0000@0;rgba(0,0,255,0.5)@100';
	const CSS         = 'linear-gradient(to bottom, #ff0000 0%, rgba(0,0,255,0.5) 100%)';

	public function set_up() {
		parent::set_up();
		WP_Swiper_Render::register_assets();
		WP_Swiper_Art::register_assets();
	}

	public function test_every_effect_has_it() {
		$effects = array_keys( WP_Swiper_Settings::fields()['effect']['choices'] );
		$this->assertSame( $effects, WP_Swiper_Settings::GRADIENT_EFFECTS );
		foreach ( array( 'image_gradient_on', 'image_gradient' ) as $key ) {
			$this->assertSame( 'layout', WP_Swiper_Settings::fields()[ $key ]['section'], $key );
			$this->assertSame( WP_Swiper_Settings::GRADIENT_EFFECTS, WP_Swiper_Settings::fields()[ $key ]['effects'], $key );
		}
		// Off: none; on: its CSS, for every effect.
		$this->assertSame( '', WP_Swiper_Settings::gradient( $this->settings( array( 'image_gradient' => self::RED_TO_BLUE ) ) ) );
		foreach ( $effects as $effect ) {
			$this->assertSame( self::CSS, WP_Swiper_Settings::gradient( $this->settings( array( 'effect' => $effect, 'image_gradient_on' => true, 'image_gradient' => self::RED_TO_BLUE ) ) ), $effect );
		}
		// Not a gradient (saved before it was checked): none.
		$this->assertSame( '', WP_Swiper_Settings::gradient( $this->settings( array( 'image_gradient_on' => true, 'image_gradient' => 'red' ) ) ) );
	}

	public function test_a_slider_has_it_over_each_photo() {
		foreach ( array( 'slide', 'fade', 'cube', 'coverflow', 'flip', 'cards', 'creative', 'thumbs', 'collage' ) as $effect ) {
			$off = $this->render( array( 'effect' => $effect, 'image_gradient' => self::RED_TO_BLUE ) );
			$this->assertStringNotContainsString( 'wp-swiper--gradient', $off, $effect );
			$this->assertStringNotContainsString( '--wp-swiper-gradient:', $off, $effect );
			$on = $this->render( array( 'effect' => $effect, 'image_gradient_on' => true, 'image_gradient' => self::RED_TO_BLUE ) );
			$this->assertMatchesRegularExpression( '/class="[^"]*\bwp-swiper--gradient\b/', $on, $effect );
			$this->assertStringContainsString( '--wp-swiper-gradient:' . self::CSS, $on, $effect );
		}
	}

	public function test_art_has_one_across_its_shape() {
		$html = $this->render( array( 'effect' => 'art', 'art_style' => 'ring', 'image_gradient_on' => true, 'image_gradient' => self::RED_TO_BLUE ) );
		$this->assertStringContainsString( 'wp-swiper-art--gradient', $html );
		$this->assertStringContainsString( '--wp-swiper-art-gradient:' . self::CSS, $html );
		// The slider's own class is not Art's.
		$this->assertStringNotContainsString( 'wp-swiper--gradient', $html );
		$this->assertStringNotContainsString( 'wp-swiper-art--gradient', $this->render( array( 'effect' => 'art' ) ) );
	}

	public function test_text_mask_gradient_saved_before_is_kept() {
		$id = $this->slider(
			null,
			array(
				'effect'                => 'text-mask',
				'text_mask_gradient_on' => true,
				'text_mask_gradient'    => self::RED_TO_BLUE,
				'layouts'               => wp_json_encode( array() ),
			)
		);
		// Saved as it was then (the old keys, as sanitize() no longer keeps them).
		update_post_meta(
			$id,
			WP_Swiper_Settings::META_SETTINGS,
			array_merge(
				get_post_meta( $id, WP_Swiper_Settings::META_SETTINGS, true ),
				array(
					'text_mask_gradient_on' => true,
					'text_mask_gradient'    => self::RED_TO_BLUE,
					'layouts'               => array( 'text-mask' => array( 'text_mask_gradient_on' => true, 'text_mask_gradient' => self::RED_TO_BLUE ) ),
				)
			)
		);
		$settings = WP_Swiper_Settings::get( $id );
		$this->assertTrue( $settings['image_gradient_on'] );
		$this->assertSame( self::RED_TO_BLUE, $settings['image_gradient'] );
		$this->assertArrayNotHasKey( 'text_mask_gradient_on', $settings );

		$saved = WP_Swiper_Settings::merge_text_mask_gradient( array( 'layouts' => array( 'text-mask' => array( 'text_mask_gradient_on' => true ) ) ) );
		$this->assertSame( array( 'image_gradient_on' => true ), $saved['layouts']['text-mask'] );
		// The new ones, once there, stay.
		$this->assertFalse( WP_Swiper_Settings::merge_text_mask_gradient( array( 'text_mask_gradient_on' => true, 'image_gradient_on' => false ) )['image_gradient_on'] );
	}

	public function test_its_editor_for_every_effect() {
		$id = $this->slider( null, array( 'effect' => 'slide', 'image_gradient' => 'circle;left top;#ff0000@0;#0000ff@100' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-field="image_gradient_on" data-effects="' . implode( ' ', WP_Swiper_Settings::GRADIENT_EFFECTS ) . '"', $html );
		$this->assertStringContainsString( 'data-field="image_gradient" data-effects="' . implode( ' ', WP_Swiper_Settings::GRADIENT_EFFECTS ) . '"', $html );
		$this->assertStringContainsString( '<input type="hidden" class="wp-swiper-gradient__value" name="wp_swiper_settings[image_gradient]" value="circle;left top;#ff0000@0;#0000ff@100">', $html );
	}
}
