<?php
/**
 * Text mask: an effect of its own, drawn by Art as its Style "text" (a word
 * whose letters are windows onto one photo, a strip under each).
 */
class Test_WP_Swiper_Text_Mask extends WP_Swiper_TestCase {

	public function set_up() {
		parent::set_up();
		WP_Swiper_Render::register_assets();
		WP_Swiper_Art::register_assets();
	}

	private function text( $values = array() ) {
		return $this->settings( array( 'effect' => 'text-mask' ) + $values );
	}

	public function test_an_effect_drawn_by_art() {
		$this->assertArrayHasKey( 'text-mask', WP_Swiper_Settings::fields()['effect']['choices'] );
		$settings = $this->text( array( 'art_style' => 'grid-shapes' ) );
		$this->assertTrue( WP_Swiper_Art::is_art( $settings ) );
		$this->assertTrue( WP_Swiper_Art::is_art( $this->settings( array( 'effect' => 'art' ) ) ) );
		$this->assertFalse( WP_Swiper_Art::is_art( $this->settings( array( 'effect' => 'slide' ) ) ) );
		// Its own Style, whatever Art's is; as wide as the column, its letters windows.
		$this->assertSame( 'text', WP_Swiper_Art::style_of( $settings ) );
		$this->assertTrue( WP_Swiper_Art::is_wide( $settings ) );
		$this->assertTrue( WP_Swiper_Art::has_windows( 'text' ) );
		$this->assertFalse( WP_Swiper_Render::needs_swiper( $settings ) );
		// Image shadow (no spread, as Art's) and Zoom on hover; no background yet.
		$this->assertContains( 'text-mask', WP_Swiper_Settings::SHADOW_EFFECTS );
		$this->assertContains( 'text-mask', WP_Swiper_Settings::HOVER_ZOOM_EFFECTS );
		$this->assertNotContains( 'text-mask', WP_Swiper_Settings::BACKGROUND_EFFECTS );
		$shadow = WP_Swiper_Settings::shadow( $this->text( array( 'image_shadow' => true, 'image_shadow_spread' => 9 ) ) );
		$this->assertSame( 0, $shadow['spread'] );
	}

	public function test_its_letters_are_its_windows() {
		$this->assertSame( array( 'L', 'A', 'H', 'O', 'R', 'E' ), WP_Swiper_Art::letters( $this->text( array( 'text_mask_title' => 'LAHORE' ) ) ) );
		// Spaces left out; letters of any script one each.
		$this->assertSame( array( 'Л', 'ь', 'в', 'і', 'в', 'О', 'К' ), WP_Swiper_Art::letters( $this->text( array( 'text_mask_title' => ' Львів  ОК ' ) ) ) );
		// No word: one letter, never none.
		$this->assertSame( array( 'T' ), WP_Swiper_Art::letters( $this->text( array( 'text_mask_title' => '   ' ) ) ) );
		$this->assertSame( array( 'desktop' => 8, 'tablet' => 8, 'mobile' => 8 ), WP_Swiper_Art::counts( $this->text( array( 'text_mask_title' => 'WP SWIPER' ) ) ) );
	}

	public function test_the_word_is_a_line_of_plain_text() {
		$field = WP_Swiper_Settings::fields()['text_mask_title'];
		$this->assertSame( 'line', $field['type'] );
		$clean = WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_title' => '<b>Hi</b> <script>x</script>there' ) );
		$this->assertSame( 'Hi there', $clean['text_mask_title'] );
		$clean = WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_title' => str_repeat( 'Ж', 40 ) ) );
		$this->assertSame( str_repeat( 'Ж', WP_Swiper_Art::TEXT_MAX ), $clean['text_mask_title'] );
		// Not sent: the default.
		$this->assertSame( 'TEXT', WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask' ) )['text_mask_title'] );
		$this->assertSame( 'TEXT', WP_Swiper_Settings::defaults()['text_mask_title'] );
	}

	public function test_its_markup_and_config() {
		$html   = $this->render(
			array(
				'effect'              => 'text-mask',
				'text_mask_title'     => 'AB C<i>',
				'text_mask_font'      => 'sans',
				'text_mask_weight'    => '900',
				'text_mask_size'      => 40,
				'text_mask_size_mobile' => 60,
				'text_mask_gap'       => 10,
				'text_mask_gap_mobile' => 4,
				'text_mask_strips'    => 'middle',
				'text_mask_strip_min' => 80,
				'text_mask_strip_max' => 20,
				'art_shape_target'    => 'any',
			),
			$this->slides( 2, array( 'caption' => 'A caption' ) )
		);
		$config = $this->config( $html, 'data-wp-swiper-art' );

		$this->assertSame( 'text', $config['style'] );
		$this->assertTrue( $config['wide'] );
		// One photo through all the letters, whatever What changes says.
		$this->assertTrue( $config['one'] );
		$this->assertStringContainsString( 'wp-swiper-art--one', $html );
		$this->assertStringContainsString( 'wp-swiper-art--text', $html );
		$this->assertSame( array( 'desktop' => 10, 'tablet' => 10, 'mobile' => 4 ), $config['gap'] );
		$text = $config['text'];
		$this->assertSame( array( 'A', 'B', 'C', '<', 'i', '>' ), $text['letters'] );
		// A space before "C" (the third letter).
		$this->assertSame( array( 2 ), $text['spaces'] );
		$this->assertSame( 'sans', $text['font'] );
		$this->assertSame( 900, $text['weight'] );
		$this->assertSame( array( 'desktop' => 40, 'tablet' => 40, 'mobile' => 60 ), $text['size'] );
		$this->assertSame( 'middle', $text['strips'] );
		// Shortest at most Longest.
		$this->assertSame( array( 20, 80 ), array( $text['min'], $text['max'] ) );
		// The same strips for the same word.
		$again = $this->config( $this->render( array( 'effect' => 'text-mask', 'text_mask_title' => 'AB C<i>' ) ), 'data-wp-swiper-art' );
		$this->assertSame( $text['seed'], $again['text']['seed'] );

		// A piece per letter; the word as text, escaped; no captions on it.
		$this->assertSame( 6, substr_count( $html, 'class="wp-swiper-art__piece"' ) );
		$this->assertStringContainsString( '<span class="wp-swiper-art__word">AB C&lt;i&gt;</span>', $html );
		$this->assertStringNotContainsString( 'A caption', $html );
		// Art's script (no Swiper: needs_swiper() above).
		$this->assertTrue( wp_script_is( 'wp-swiper-art', 'enqueued' ) );
	}

	public function test_a_photo_in_each_letter() {
		$this->assertSame( 'one', WP_Swiper_Settings::defaults()['text_mask_photos'] );
		$this->assertSame( array( 'grid-shapes', 'shape', 'text' ), WP_Swiper_Settings::fields()['art_shape_target']['styles'] );
		$slides = $this->slides( 3 );
		$ids    = wp_list_pluck( $slides, 'id' );
		$each   = array(
			'effect'           => 'text-mask',
			'text_mask_title'  => 'LAHORE',
			'text_mask_photos' => 'each',
			'art_swap'         => true,
		);

		// Not one photo: each letter its own, in the order set for the device.
		$html   = $this->render( $each + array( 'art_shape_target' => 'turn', 'art_order_mobile' => $ids[2] . ',' . $ids[0] ), $slides );
		$config = $this->config( $html, 'data-wp-swiper-art' );
		$this->assertArrayNotHasKey( 'one', $config );
		$this->assertStringNotContainsString( 'wp-swiper-art--one', $html );
		$this->assertSame( 'turn', $config['swap']['target'] );
		$this->assertSame( array( 2, 0, 1 ), $config['order']['mobile'] );
		$this->assertFalse( $config['order']['desktop'] );
		$this->assertSame( 6, substr_count( $html, 'class="wp-swiper-art__piece"' ) );
		// All at once: in the order Appearance says.
		$config = $this->config( $this->render( $each + array( 'art_shape_target' => 'advance', 'art_shape_appear' => 'ltr' ), $slides ), 'data-wp-swiper-art' );
		$this->assertSame( array( 'advance', 'ltr' ), array( $config['swap']['target'], $config['appear'] ) );
		// What changes "One photo through all windows" is Photos' to say: a letter at random.
		$config = $this->config( $this->render( $each + array( 'art_shape_target' => 'one' ), $slides ), 'data-wp-swiper-art' );
		$this->assertArrayNotHasKey( 'one', $config );
		$this->assertSame( 'any', $config['swap']['target'] );

		// One photo: whatever What changes says.
		$config = $this->config( $this->render( array( 'text_mask_photos' => 'one', 'art_shape_target' => 'turn' ) + $each, $slides ), 'data-wp-swiper-art' );
		$this->assertTrue( $config['one'] );
		$this->assertSame( 'one', $config['swap']['target'] );
		// An unknown value: one photo.
		$this->assertSame( 'one', WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_photos' => 'all' ) )['text_mask_photos'] );
	}

	public function test_unknown_values_fall_back() {
		$text = WP_Swiper_Art::text_config( $this->text( array( 'text_mask_font' => 'comic', 'text_mask_weight' => '100', 'text_mask_strips' => 'zigzag', 'text_mask_strip_width' => 'wide' ) ) );
		$this->assertSame( array( 'heading', 700, 'random', 'same' ), array( $text['font'], $text['weight'], $text['strips'], $text['width'] ) );
	}

	public function test_strip_width() {
		// All as wide by default (a column per letter); or each as its letter.
		$this->assertSame( 'same', WP_Swiper_Settings::defaults()['text_mask_strip_width'] );
		$this->assertSame( 'same', WP_Swiper_Art::text_config( $this->text() )['width'] );
		$this->assertSame( 'letter', WP_Swiper_Art::text_config( $this->text( array( 'text_mask_strip_width' => 'letter' ) ) )['width'] );
		$this->assertSame( 'same', WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_strip_width' => 'wide' ) )['text_mask_strip_width'] );
	}

	public function test_a_slider_saved_by_the_first_try() {
		// Saved on 2026-11-02 by an earlier version: its word, font and gap are
		// taken; its Depth is gone (the Image shadow instead).
		$id       = $this->slider(
			null,
			array(
				'effect'          => 'text-mask',
				'text_mask_title' => 'Text',
				'text_mask_font'  => 'serif',
				'text_mask_gap'   => 14,
				'text_mask_depth' => 65,
			)
		);
		$settings = WP_Swiper_Settings::get( $id );
		$this->assertSame( 'text-mask', $settings['effect'] );
		$this->assertSame( array( 'Text', 'serif', 14 ), array( $settings['text_mask_title'], $settings['text_mask_font'], $settings['text_mask_gap'] ) );
		$this->assertArrayNotHasKey( 'text_mask_depth', WP_Swiper_Settings::sanitize( $settings ) );
		$this->assertStringContainsString( 'data-wp-swiper-art=', do_shortcode( '[wp_swiper id="' . $id . '"]' ) );
	}

	public function test_the_editor_fields() {
		$id = $this->slider( null, array( 'effect' => 'text-mask' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();
		$this->assertSame( 1, preg_match( '/data-field="text_mask_title" data-effects="text-mask">/', $html ) );
		$this->assertStringContainsString( 'maxlength="' . WP_Swiper_Art::TEXT_MAX . '"', $html );
		// Art's block, titled for it there (admin.js).
		$this->assertStringContainsString( 'data-title="Art" data-titles="{&quot;text-mask&quot;:&quot;Text mask&quot;}">Art</h4>', $html );
		// Art's Height for it too, not its Gap (its own instead).
		$this->assertSame( 1, preg_match( '/data-field="art_height" data-effects="art text-mask">/', $html ) );
		$this->assertSame( 1, preg_match( '/data-field="art_gap" data-effects="art"/', $html ) );
	}
}
