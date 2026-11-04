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

	public function test_strips_into_letters() {
		// 0 by default (the letters whole), one for every screen, after Strip width.
		$this->assertSame( 0, WP_Swiper_Settings::defaults()['text_mask_join'] );
		$this->assertTrue( empty( WP_Swiper_Settings::fields()['text_mask_join']['responsive'] ) );
		$fields = WP_Swiper_Art::blocks()['layout']['text_strips']['fields'];
		$this->assertSame( array_search( 'text_mask_strip_width', $fields, true ) + 1, array_search( 'text_mask_join', $fields, true ) );
		// A share of the letter for art.js, at most 30%.
		$this->assertSame( 0, WP_Swiper_Art::text_config( $this->text() )['join'] );
		$this->assertSame( 0.2, WP_Swiper_Art::text_config( $this->text( array( 'text_mask_join' => 20 ) ) )['join'] );
		$this->assertSame( 0.3, WP_Swiper_Art::text_config( $this->text( array( 'text_mask_join' => 90 ) ) )['join'] );
		$this->assertSame( 30, WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_join' => '90' ) )['text_mask_join'] );
		$this->assertSame( 0, WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_join' => '-5' ) )['text_mask_join'] );
	}

	public function test_rounded_strips() {
		// As a slider's: the switch off, the radius per device under it (12px), then which end.
		$defaults = WP_Swiper_Settings::defaults();
		$fields   = WP_Swiper_Settings::fields();
		$this->assertFalse( $defaults['text_mask_rounded'] );
		$this->assertSame( 'checkbox', $fields['text_mask_rounded']['type'] );
		$this->assertSame( 'Rounded corners', $fields['text_mask_rounded']['label'] );
		$this->assertSame( 12, $defaults['text_mask_radius'] );
		$this->assertSame( 'Radius (px)', $fields['text_mask_radius']['label'] );
		$this->assertTrue( $fields['text_mask_radius']['responsive'] );
		$this->assertSame( 'end', $defaults['text_mask_corners'] );
		$strips = WP_Swiper_Art::blocks()['layout']['text_strips']['fields'];
		// In the Look block, where a slider's and Art's Rounded corners are (right after Art's).
		$look = WP_Swiper_Settings::blocks()['layout']['look']['fields'];
		$at   = array_search( 'art_radius', $look, true );
		$this->assertSame( array( 'text_mask_rounded', 'text_mask_radius', 'text_mask_corners' ), array_slice( $look, $at + 1, 3 ) );
		$this->assertNotContains( 'text_mask_rounded', $strips );
		$id = $this->slider( null, array( 'effect' => 'text-mask' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();
		$this->assertGreaterThan( strpos( $html, 'data-block="look"' ), strpos( $html, 'data-field="text_mask_rounded"' ) );
		$this->assertLessThan( strpos( $html, 'data-field="image_hover_zoom"' ), strpos( $html, 'data-field="text_mask_corners"' ) );

		// Off: no radius on any device (whatever its number).
		$text = WP_Swiper_Art::text_config( $this->text( array( 'text_mask_radius' => 40 ) ) );
		$this->assertSame( array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ), $text['radius'] );
		$this->assertSame( 'end', $text['corners'] );
		// On: per device, the phone the tablet's; at most 200.
		$text = WP_Swiper_Art::text_config( $this->text( array( 'text_mask_rounded' => true, 'text_mask_radius' => 500, 'text_mask_radius_tablet' => 20, 'text_mask_corners' => 'both' ) ) );
		$this->assertSame( array( 'desktop' => 200, 'tablet' => 20, 'mobile' => 20 ), $text['radius'] );
		$this->assertSame( 'both', $text['corners'] );
		$this->assertSame( 'end', WP_Swiper_Art::text_config( $this->text( array( 'text_mask_corners' => 'all' ) ) )['corners'] );
		$this->assertSame( 'end', WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_corners' => 'all' ) )['text_mask_corners'] );
	}

	public function test_gradient_is_parsed_kept_and_drawn_as_css() {
		// Linear: where it starts (as WoodMart's "left": towards the right).
		$this->assertSame( 'linear-gradient(to right, rgba(255,0,128,0.55) 0%, rgba(0,96,255,0.55) 100%)', WP_Swiper_Art::gradient_css( WP_Swiper_Art::GRADIENT_DEFAULT ) );
		$this->assertSame( 'linear-gradient(to top, #ff0000 0%, #0000ff 100%)', WP_Swiper_Art::gradient_css( 'linear;bottom;#ff0000@0;#0000ff@100' ) );
		// Radial: a circle or an ellipse, its middle.
		$this->assertSame( 'radial-gradient(circle at left top, #ff0000 10%, rgba(0,0,255,0.5) 80%)', WP_Swiper_Art::gradient_css( 'circle;left top;#ff0000@10;rgba(0,0,255,.5)@80' ) );
		$this->assertSame( 'radial-gradient(ellipse at center center, #f00 0%, #00f 100%)', WP_Swiper_Art::gradient_css( 'ellipse;center center;#f00@0;#00f@100' ) );

		// The points in order of their positions, within 0-100, kept as they came at one place.
		$gradient = WP_Swiper_Art::parse_gradient( 'linear;left;#0000ff@100;#ff0000@-5;#00ff00@50.25;#ffffff@50.25' );
		$this->assertSame( array( array( '#ff0000', 0.0 ), array( '#00ff00', 50.3 ), array( '#ffffff', 50.3 ), array( '#0000ff', 100.0 ) ), $gradient['stops'] );
		$this->assertSame( 'linear;left;#ff0000@0;#00ff00@50.3;#ffffff@50.3;#0000ff@100', WP_Swiper_Art::gradient_value( $gradient ) );
		// Points that are not one are left out; fewer than two: no gradient.
		$this->assertCount( 2, WP_Swiper_Art::parse_gradient( 'linear;left;#ff0000@0;red@20;#00ff00;url(x)@30;#0000ff@100' )['stops'] );
		$this->assertCount( WP_Swiper_Art::GRADIENT_STOPS, WP_Swiper_Art::parse_gradient( 'linear;left;' . implode( ';', array_fill( 0, 15, '#ff0000@0' ) ) )['stops'] );
		foreach ( array( '', 'linear;left;#ff0000@0', 'conic;left;#ff0000@0;#00ff00@100', 'linear;center;#ff0000@0;#00ff00@100', 'circle;left;#ff0000@0;#00ff00@100', 'circle;top left;#ff0000@0;#00ff00@100', 'linear;left;#ff0000@0;expression(x)@100' ) as $value ) {
			$this->assertNull( WP_Swiper_Art::parse_gradient( $value ), $value );
			$this->assertSame( '', WP_Swiper_Art::gradient_css( $value ), $value );
		}

		// Saved: as kept, or the default one for anything else.
		$this->assertSame( 'circle;center bottom;#ff0000@0;#0000ff@100', WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'image_gradient' => 'circle;center bottom;#FF0000@0;#0000ff@100' ) )['image_gradient'] );
		$this->assertSame( WP_Swiper_Art::GRADIENT_DEFAULT, WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'image_gradient' => 'linear-gradient(red, blue)' ) )['image_gradient'] );
		$this->assertSame( WP_Swiper_Art::GRADIENT_DEFAULT, WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'image_gradient' => array( 'x' ) ) )['image_gradient'] );
	}

	public function test_gradient_over_the_letters_and_strips() {
		// Off by default, its default colors pink to blue, half see-through.
		$this->assertFalse( WP_Swiper_Settings::defaults()['image_gradient_on'] );
		$this->assertSame( WP_Swiper_Art::GRADIENT_DEFAULT, WP_Swiper_Settings::defaults()['image_gradient'] );
		// In Look, after the image borders, before Zoom on hover (every effect's).
		$look = WP_Swiper_Settings::blocks()['layout']['look']['fields'];
		$this->assertSame( array( 'art_border_color', 'image_gradient_on', 'image_gradient', 'image_hover_zoom' ), array_slice( $look, array_search( 'art_border_color', $look, true ), 4 ) );

		$this->assertStringNotContainsString( 'wp-swiper-art--gradient', $this->render( array( 'effect' => 'text-mask', 'image_gradient' => 'linear;top;#ff0000@0;#0000ff@100' ) ) );
		$html = $this->render( array( 'effect' => 'text-mask', 'image_gradient_on' => true, 'image_gradient' => 'linear;top;#ff0000@0;rgba(0,0,255,0.5)@100' ) );
		$this->assertStringContainsString( 'wp-swiper-art--gradient', $html );
		$this->assertStringContainsString( '--wp-swiper-art-gradient:linear-gradient(to bottom, #ff0000 0%, rgba(0,0,255,0.5) 100%)', $html );
	}

	public function test_gradient_editor() {
		$id = $this->slider( null, array( 'effect' => 'text-mask', 'image_gradient' => 'circle;left top;#ff0000@0;#0000ff@100' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-field="image_gradient"', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-gradient" data-default="' . esc_attr( WP_Swiper_Art::GRADIENT_DEFAULT ) . '">', $html );
		// The value kept in its input, the label on the type.
		$this->assertStringContainsString( '<input type="hidden" class="wp-swiper-gradient__value" name="wp_swiper_settings[image_gradient]" value="circle;left top;#ff0000@0;#0000ff@100">', $html );
		$this->assertStringContainsString( '<select class="wp-swiper-gradient__type" id="wp-swiper-image_gradient"', $html );
		$this->assertStringContainsString( '<label for="wp-swiper-image_gradient">Gradient colors</label>', $html );
		// The chosen point's color: a swatch with opacity, no input of its own.
		$this->assertSame( 1, preg_match( '#<div class="wp-swiper-gradient__stop">\s*<span class="wp-swiper-color-pick" data-alpha="1" data-default="\#000000">\s*<button#', $html ) );
	}

	public function test_letter_size_auto_and_stretch() {
		// Letter size Auto by default (the word as wide as it can be), per device.
		$this->assertSame( 'auto', WP_Swiper_Settings::defaults()['text_mask_size_unit'] );
		$this->assertSame( 'text_mask_size', WP_Swiper_Settings::fields()['text_mask_size_unit']['inline_in'] );
		$text = WP_Swiper_Art::text_config( $this->text() );
		$this->assertSame( array( 'desktop' => true, 'tablet' => true, 'mobile' => true ), $text['auto'] );
		// % on desktop; the tablet its own Auto, the phone the tablet's.
		$text = WP_Swiper_Art::text_config( $this->text( array( 'text_mask_size_unit' => '%', 'text_mask_size_unit_tablet' => 'auto', 'text_mask_size_unit_mobile' => '' ) ) );
		$this->assertSame( array( 'desktop' => false, 'tablet' => true, 'mobile' => true ), $text['auto'] );
		$text = WP_Swiper_Art::text_config( $this->text( array( 'text_mask_size_unit' => '%' ) ) );
		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => false ), $text['auto'] );
		$this->assertSame( 'auto', WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_size_unit' => 'px' ) )['text_mask_size_unit'] );

		// Stretch letters: off by default.
		$this->assertFalse( WP_Swiper_Settings::defaults()['text_mask_stretch'] );
		$this->assertFalse( WP_Swiper_Art::text_config( $this->text() )['stretch'] );
		$this->assertTrue( WP_Swiper_Art::text_config( $this->text( array( 'text_mask_stretch' => true ) ) )['stretch'] );
	}

	public function test_strips_go_down_or_sideways() {
		// Down by default (the word across); right or left: the letters one under another.
		$this->assertSame( 'down', WP_Swiper_Settings::defaults()['text_mask_direction'] );
		$this->assertSame( array( 'down', 'right', 'left' ), array_keys( WP_Swiper_Settings::fields()['text_mask_direction']['choices'] ) );
		foreach ( array( 'down', 'right', 'left' ) as $direction ) {
			$config = $this->config( $this->render( array( 'effect' => 'text-mask', 'text_mask_direction' => $direction ) ), 'data-wp-swiper-art' );
			$this->assertSame( $direction, $config['text']['direction'] );
		}
		$this->assertSame( 'down', WP_Swiper_Art::text_config( $this->text( array( 'text_mask_direction' => 'up' ) ) )['direction'] );
		$this->assertSame( 'down', WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_direction' => 'up' ) )['text_mask_direction'] );
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
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
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

	public function test_three_blocks_the_word_its_letters_its_strips() {
		$blocks = WP_Swiper_Settings::blocks()['layout'];
		// After Art's blocks (Content in the middle and Opening: Art's only, so
		// on screen right after the word's block).
		$names = array_keys( $blocks );
		$art   = array_search( 'art', $names, true );
		$this->assertSame( array( 'art', 'art_middle', 'art_opening', 'text_letters', 'text_strips' ), array_slice( $names, $art, 5 ) );
		foreach ( array_merge( $blocks['art_middle']['fields'], $blocks['art_opening']['fields'] ) as $key ) {
			$this->assertSame( array( 'art' ), WP_Swiper_Settings::fields()[ $key ]['effects'], $key );
		}
		$this->assertSame( array( 'text_mask_font', 'text_mask_weight', 'text_mask_font_name', 'text_mask_size', 'text_mask_stretch', 'text_mask_gap' ), $blocks['text_letters']['fields'] );
		$this->assertSame( array( 'text_mask_strip_width', 'text_mask_join', 'text_mask_strips', 'text_mask_strip_min', 'text_mask_strip_max' ), $blocks['text_strips']['fields'] );
		// The word's block: Text mask's fields only those of the word.
		$word = array_values( preg_grep( '/^text_mask_/', $blocks['art']['fields'] ) );
		$this->assertSame( array( 'text_mask_direction', 'text_mask_title', 'text_mask_photos' ), $word );
		$this->assertContains( 'art_height', $blocks['art']['fields'] );
		// Text mask's fields only: hidden for Art (admin.js, no field shown).
		$fields = WP_Swiper_Settings::fields();
		foreach ( array_merge( $blocks['text_letters']['fields'], $blocks['text_strips']['fields'] ) as $key ) {
			$this->assertSame( array( 'text-mask' ), $fields[ $key ]['effects'], $key );
		}

		$id = $this->slider( null, array( 'effect' => 'text-mask' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( '<div class="wp-swiper-block" data-block="text_letters"><h4 class="wp-swiper-subheading">Letters</h4>', $html );
		$this->assertStringContainsString( '<div class="wp-swiper-block" data-block="text_strips"><h4 class="wp-swiper-subheading">Strips</h4>', $html );
		$this->assertLessThan( strpos( $html, 'data-block="text_letters"' ), strpos( $html, 'data-field="art_height"' ) );
		$this->assertLessThan( strpos( $html, 'data-block="text_strips"' ), strpos( $html, 'data-field="text_mask_gap"' ) );
		$this->assertGreaterThan( strpos( $html, 'data-block="text_strips"' ), strpos( $html, 'data-field="text_mask_join"' ) );
	}

	public function test_strips_go_is_chosen_from_icons_first_in_the_block() {
		$id = $this->slider( null, array( 'effect' => 'text-mask', 'text_mask_direction' => 'right' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();

		// One value for every screen: its select (hidden) and the icons, no Inherit.
		$this->assertSame( 1, preg_match( '#<div class="wp-swiper-field wp-swiper-field--select wp-swiper-field--tiles" data-field="text_mask_direction"#', $html ) );
		$this->assertMatchesRegularExpression( '#<select id="wp-swiper-text_mask_direction" name="wp_swiper_settings\[text_mask_direction\]" class="wp-swiper-tiles__select" hidden>#', $html );
		$this->assertMatchesRegularExpression( '#<option value="right"\s+selected=\'selected\'>Right</option>#', $html );
		foreach ( WP_Swiper_Settings::fields()['text_mask_direction']['choices'] as $direction => $label ) {
			$this->assertFileExists( WP_SWIPER_DIR . 'assets/images/text-mask/' . $direction . '.svg', $direction );
			$this->assertMatchesRegularExpression( '#<button type="button" class="wp-swiper-tiles__tile" data-value="' . $direction . '" role="radio" aria-checked="false" title="' . preg_quote( $label, '#' ) . '" aria-label="' . preg_quote( $label, '#' ) . '">\s*<svg class="wp-swiper-tiles__icon" aria-hidden="true" focusable="false"[^>]*fill="currentColor"#', $html, $direction );
		}
		$this->assertSame( 1, preg_match_all( '#data-field="text_mask_direction".*?</div>\s*</div>#s', $html, $match ) );
		$this->assertStringNotContainsString( 'tiles__tile--inherit', $match[0][0] );

		// At the top of the block: right after Art's Style (not shown for Text mask).
		$fields = WP_Swiper_Art::blocks()['layout']['art']['fields'];
		$this->assertSame( array( 'art_style', 'text_mask_direction', 'text_mask_title' ), array_slice( $fields, 0, 3 ) );
		$this->assertLessThan( strpos( $html, 'data-field="text_mask_title"' ), strpos( $html, 'data-field="text_mask_direction"' ) );
	}
}
