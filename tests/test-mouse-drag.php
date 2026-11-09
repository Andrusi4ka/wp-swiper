<?php
/**
 * Drag with the mouse (Controls → Movement, `mouse_drag`): on by default, so
 * sliders saved before it keep dragging; off, the config says so and
 * slider.js turns Swiper's simulateTouch off (a finger still swipes).
 */

class Test_WP_Swiper_Mouse_Drag extends WP_Swiper_TestCase {

	public function test_the_field_sits_in_movement_after_the_loop() {
		$field = WP_Swiper_Settings::fields()['mouse_drag'];
		$this->assertSame( 'checkbox', $field['type'] );
		$this->assertTrue( $field['default'] );
		$this->assertSame( 'controls', $field['panel'] );

		$movement = WP_Swiper_Settings::blocks()['controls']['movement']['fields'];
		$this->assertSame( array_search( 'loop', $movement, true ) + 1, array_search( 'mouse_drag', $movement, true ) );
	}

	public function test_on_by_default_and_for_sliders_saved_before_it() {
		$this->assertTrue( WP_Swiper_Settings::defaults()['mouse_drag'] );
		$this->assertTrue( $this->config( $this->render() )['mouseDrag'] );

		// Saved before the setting: no key, so on as ever.
		$id = $this->slider( null, array( 'effect' => 'slide', 'loop' => true ) );
		$this->assertTrue( WP_Swiper_Settings::get( $id )['mouse_drag'] );
	}

	public function test_off_reaches_the_config() {
		$this->assertFalse( $this->config( $this->render( array( 'mouse_drag' => false ) ) )['mouseDrag'] );
		// The edit screen posts no unchecked checkbox: off.
		$this->assertFalse( WP_Swiper_Settings::sanitize( array( 'loop' => '1' ) )['mouse_drag'] );
		$this->assertTrue( WP_Swiper_Settings::sanitize( array( 'mouse_drag' => '1' ) )['mouse_drag'] );
	}

	public function test_every_slider_effect_has_it() {
		foreach ( array( 'slide', 'fade', 'cube', 'coverflow', 'flip', 'cards', 'creative', 'thumbs' ) as $effect ) {
			$this->assertFalse( $this->config( $this->render( array( 'effect' => $effect, 'mouse_drag' => false ) ) )['mouseDrag'], $effect );
		}
	}

	public function test_slider_js_turns_the_mouse_off() {
		$js = file_get_contents( WP_SWIPER_DIR . 'assets/js/slider.js' );
		$this->assertStringContainsString( 'config.mouseDrag === false', $js );
		$this->assertStringContainsString( 'options.simulateTouch = false;', $js );
		// A background and the marquee lock it in the editor, as the keyboard.
		$this->assertStringContainsString( "var lockMouseDrag = lockable( 'mouse_drag' );", $this->admin_js() );
		$this->assertStringContainsString( 'lockMouseDrag( still );', $this->admin_js() );
	}
}
