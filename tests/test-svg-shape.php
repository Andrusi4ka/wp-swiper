<?php
/**
 * Art, Style "SVG shape": WP_Swiper_Svg_Shape reads a drawing's shapes
 * (geometry only), the Art effect shows a photo in each.
 */

class Test_WP_Swiper_Svg_Shape extends WP_Swiper_TestCase {

	public function set_up() {
		parent::set_up();
		WP_Swiper_Render::register_assets();
		WP_Swiper_Art::register_assets();
	}

	private function file( $name ) {
		return file_get_contents( __DIR__ . '/data/' . $name . '.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test data.
	}

	private function svg( $body, $view = '0 0 100 100' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $view . '">' . $body . '</svg>';
	}

	private function ids( $data ) {
		return wp_list_pluck( $data['shapes'], 'id' );
	}

	public function test_the_designers_files() {
		// Two mirrored halves of a heart: as large as each other, the first in the file is the main one.
		$one = WP_Swiper_Svg_Shape::parse( $this->file( 'ex-1' ), 'ex-1.svg' );
		$this->assertCount( 2, $one['shapes'] );
		$this->assertSame( 'ex-1.svg', $one['name'] );
		$this->assertSame( array( 1000.0, 1000.0 ), array( $one['w'], $one['h'] ) );
		$this->assertSame( 0, $one['shapes'][0]['z'] );
		$this->assertEqualsWithDelta( 0.5, $one['shapes'][0]['box'][0], 0.001 );
		$this->assertEqualsWithDelta( $one['shapes'][0]['area'], $one['shapes'][1]['area'], 0.001 );

		// A large shape and a circle: the large one is the main one.
		$two = WP_Swiper_Svg_Shape::parse( $this->file( 'ex-2' ) );
		$this->assertCount( 2, $two['shapes'] );
		$this->assertEqualsWithDelta( 0.205, $two['shapes'][0]['area'], 0.002 );
		$this->assertEqualsWithDelta( M_PI * 0.12143 * 0.12143, $two['shapes'][1]['area'], 0.0005 );
		// The circle's box: its middle and radius.
		$this->assertEqualsWithDelta( 0.68414 - 0.12143, $two['shapes'][1]['box'][0], 0.001 );
		$this->assertEqualsWithDelta( 0.24286, $two['shapes'][1]['box'][2], 0.001 );

		// Three that lie over each other: the rectangle is the largest (the main
		// one), each keeps its place in the stacking (the rectangle on top).
		$three = WP_Swiper_Svg_Shape::parse( $this->file( 'ex-3' ) );
		$this->assertCount( 3, $three['shapes'] );
		$this->assertSame( array( 2, 0, 1 ), wp_list_pluck( $three['shapes'], 'z' ) );
		$this->assertSame( 0, strpos( $three['shapes'][0]['d'], 'M 0.49549 0.23334 L 0.83667 0.23334' ) );
		$this->assertSame( 0, $three['skipped'] );
		$this->assertSame( 'ex-3.svg: 3 shapes', WP_Swiper_Svg_Shape::summary( WP_Swiper_Svg_Shape::parse( $this->file( 'ex-3' ), 'ex-3.svg' ) ) );
	}

	public function test_the_main_window_and_the_order_by_name() {
		$data = WP_Swiper_Svg_Shape::parse(
			$this->svg(
				'<rect id="big" width="60" height="60"/>' .
				'<rect id="3" x="70" width="10" height="10"/>' .
				'<rect data-name="main" id="main_x" x="70" y="20" width="12" height="12"/>' .
				'<rect id="2" x="70" y="40" width="8" height="8"/>' .
				'<rect id="other" x="70" y="60" width="20" height="20"/>'
			)
		);
		// "main" (the drawing's own name, data-name, over a mangled id), then 2, 3, then larger first.
		$this->assertSame( array( 'main', '2', '3', 'big', 'other' ), $this->ids( $data ) );
		// The stacking stays the file's.
		$this->assertSame( array( 2, 3, 1, 0, 4 ), wp_list_pluck( $data['shapes'], 'z' ) );
	}

	public function test_layers_groups_and_transforms() {
		// One layer holding everything: its objects; a group in it: one window.
		$data = WP_Swiper_Svg_Shape::parse(
			$this->svg(
				'<g id="Layer_1"><g id="pair"><rect width="10" height="10"/><rect x="20" width="10" height="10"/></g>' .
				'<rect id="moved" width="10" height="10" transform="translate(50 50) scale(2)"/></g>'
			)
		);
		$this->assertSame( array( 'moved', 'pair' ), $this->ids( $data ) );
		$this->assertEqualsWithDelta( array( 0.5, 0.5, 0.2, 0.2 ), $data['shapes'][0]['box'], 0.0001 );
		$this->assertEqualsWithDelta( array( 0, 0, 0.3, 0.1 ), $data['shapes'][1]['box'], 0.0001 );
		$this->assertSame( 2, substr_count( $data['shapes'][1]['d'], 'M ' ) );

		// The layer's own transform too; rotate about a point.
		$data = WP_Swiper_Svg_Shape::parse( $this->svg( '<g transform="translate(10,0)"><rect width="10" height="10" transform="rotate(90 5 5)"/></g>' ) );
		$this->assertEqualsWithDelta( array( 0.1, 0, 0.1, 0.1 ), $data['shapes'][0]['box'], 0.0001 );
	}

	public function test_path_commands_arcs_and_curves() {
		// Relative commands, H / V, implicit lines after M, an arc with flags written together.
		$data = WP_Swiper_Svg_Shape::parse( $this->svg( '<path d="m10 10 20 0v20h-20zM50 50a10 10 0 1150 0 10 10 0 01-50 0"/>' ) );
		$this->assertCount( 1, $data['shapes'] );
		$parts = WP_Swiper_Svg_Shape::parse_path( 'm10 10 20 0v20h-20zM50 50a10 10 0 1150 0 10 10 0 01-50 0' );
		$this->assertCount( 2, $parts );
		$this->assertSame( array( 10.0, 10.0 ), array( $parts[0][0], $parts[0][1] ) );
		$this->assertSame( array( 'L', 30.0, 10.0 ), $parts[0][2][0] );
		// The arc (a circle of radius 25 grown from 10) ends where it began, through 100 50.
		$end = end( $parts[1][2] );
		$this->assertEqualsWithDelta( array( 50, 50 ), array( $end[5], $end[6] ), 0.001 );
		// Two half turns: a whole circle round 75 50.
		$circle = WP_Swiper_Svg_Shape::parse( $this->svg( '<path d="M50 50a10 10 0 1150 0 10 10 0 01-50 0"/>' ) );
		$this->assertEqualsWithDelta( array( 0.5, 0.25, 0.5, 0.5 ), $circle['shapes'][0]['box'], 0.001 );
		$this->assertEqualsWithDelta( M_PI * 0.25 * 0.25, $circle['shapes'][0]['area'], 0.002 );

		// S, Q, T: curves.
		$parts = WP_Swiper_Svg_Shape::parse_path( 'M0 0C10 0 10 10 20 10S30 20 40 20Q50 0 60 20T80 20Z' );
		$this->assertCount( 4, $parts[0][2] );
		$this->assertSame( array( 'C', 30.0, 10.0, 30.0, 20.0, 40.0, 20.0 ), $parts[0][2][1] );
		$this->assertTrue( $parts[0][3] );
		// Nonsense ends the path where it stops making sense.
		$this->assertCount( 2, WP_Swiper_Svg_Shape::parse_path( 'M0 0L10 0L10 10Lx 5' )[0][2] );
		$this->assertNull( WP_Swiper_Svg_Shape::parse_path( 'nonsense' ) );
	}

	public function test_no_entities_whatever_the_encoding() {
		$entities = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">]><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><style>.c{fill:#000}&b;</style><rect class="c" width="10" height="10"/></svg>';
		$this->assertNull( WP_Swiper_Svg_Shape::parse( $entities ) );
		// The same in UTF-16 (the plain check for "<!ENTITY" does not see it): refused too.
		$this->assertNull( WP_Swiper_Svg_Shape::parse( "\xFF\xFE" . mb_convert_encoding( str_replace( '<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-16"?>', $entities ), 'UTF-16LE', 'UTF-8' ) ) );
		// A doctype with an internal part, even without entities; a NUL byte; not UTF-8.
		$this->assertNull( WP_Swiper_Svg_Shape::parse( '<!DOCTYPE svg [<!ATTLIST svg x CDATA "1">]>' . $this->svg( '<rect width="10" height="10"/>' ) ) );
		$this->assertNull( WP_Swiper_Svg_Shape::parse( $this->svg( "<rect width=\"10\" height=\"10\"/>\0" ) ) );
		$this->assertNull( WP_Swiper_Svg_Shape::parse( $this->svg( "<rect id=\"\xC3\x28\" width=\"10\" height=\"10\"/>" ) ) );
		// A plain doctype (as some editors write) is fine.
		$this->assertNotNull( WP_Swiper_Svg_Shape::parse( '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . $this->svg( '<rect width="10" height="10"/>' ) ) );
	}

	public function test_what_is_left_out() {
		$data = WP_Swiper_Svg_Shape::parse(
			$this->svg(
				'<defs><style>.out{fill:none;stroke:#000}.gone{display:none}</style><clipPath id="c"><rect width="100" height="100"/></clipPath></defs>' .
				'<rect class="out" width="10" height="10"/>' .
				'<rect class="gone" x="20" width="10" height="10"/>' .
				'<rect style="visibility:hidden" x="40" width="10" height="10"/>' .
				'<rect fill="none" x="60" width="10" height="10"/>' .
				'<text x="0" y="50">Hi</text><line x1="0" y1="0" x2="10" y2="10"/>' .
				'<image href="x.png" width="10" height="10"/>' .
				'<circle cx="50" cy="50" r="10"/>'
			)
		);
		$this->assertCount( 1, $data['shapes'] );
		$this->assertSame( 7, $data['skipped'] );
		$this->assertSame( '1 shape, 7 objects left out (no fill, hidden or text)', WP_Swiper_Svg_Shape::summary( $data ) );

		// Nothing to show, or no SVG at all.
		$this->assertNull( WP_Swiper_Svg_Shape::parse( $this->svg( '<rect fill="none" width="10" height="10"/>' ) ) );
		$this->assertNull( WP_Swiper_Svg_Shape::parse( '<html><body>no</body></html>' ) );
		$this->assertNull( WP_Swiper_Svg_Shape::parse( 'not xml' ) );
		$this->assertNull( WP_Swiper_Svg_Shape::parse( '' ) );
	}

	public function test_at_most_a_hundred_windows_and_the_rest_said() {
		// A grid of 103 squares (as ex-9.svg, larger): the first 100 in the file kept.
		$body = '';
		for ( $i = 0; $i < 103; $i++ ) {
			$body .= '<rect x="' . ( $i % 11 * 9 ) . '" y="' . ( intdiv( $i, 11 ) * 9 ) . '" width="8" height="8"/>';
		}
		$data = WP_Swiper_Svg_Shape::parse( $this->svg( $body ), 'grid.svg' );
		$this->assertSame( 100, WP_Swiper_Svg_Shape::MAX_SHAPES );
		$this->assertCount( 100, $data['shapes'] );
		$this->assertSame( 3, $data['over'] );
		$this->assertSame( 0, $data['skipped'] );
		$this->assertSame( 'grid.svg: 100 shapes, 3 more left out: at most 100 shapes', WP_Swiper_Svg_Shape::summary( $data ) );
		// Kept and read back: the same.
		$kept = WP_Swiper_Svg_Shape::decode( WP_Swiper_Svg_Shape::sanitize( wp_json_encode( array( 'name' => 'grid.svg', 'svg' => $this->svg( $body ) ) ) ) );
		$this->assertCount( 100, $kept['shapes'] );
		$this->assertSame( 3, $kept['over'] );
		// Art draws all of them.
		$html = $this->render( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => WP_Swiper_Svg_Shape::sanitize( $this->svg( $body ) ) ), $this->slides( 4 ) );
		$this->assertSame( 100, substr_count( $html, 'class="wp-swiper-art__piece"' ) );
		// Within the limit, nothing said.
		$this->assertSame( 0, WP_Swiper_Svg_Shape::parse( $this->svg( '<rect width="10" height="10"/>' ) )['over'] );
	}

	public function test_only_the_geometry_is_kept() {
		$evil = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 100" onload="alert(1)">'
			. '<script>alert(2)</script><a xlink:href="javascript:alert(3)"><rect id="x&quot;&gt;&lt;b&gt;" width="50" height="50" onclick="alert(4)"/></a>'
			. '<foreignObject><div>html</div></foreignObject></svg>';
		$value = WP_Swiper_Svg_Shape::sanitize( wp_json_encode( array( 'name' => '../../evil<b>.svg', 'svg' => $evil ) ) );
		$this->assertNotSame( '', $value );
		foreach ( array( 'alert', 'script', 'javascript', 'onclick', 'onload', '<b>', 'html' ) as $word ) {
			$this->assertStringNotContainsString( $word, $value, $word );
		}
		$data = WP_Swiper_Svg_Shape::decode( $value );
		$this->assertCount( 1, $data['shapes'] );
		$this->assertStringNotContainsString( '/', $data['name'] );

		// Entities (which could expand without end): not read.
		$this->assertSame( '', WP_Swiper_Svg_Shape::sanitize( '<!DOCTYPE svg [<!ENTITY a "aaaa">]><svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>' ) );
		// Too large.
		$this->assertSame( '', WP_Swiper_Svg_Shape::sanitize( $this->svg( '<rect width="10" height="10"/>' . str_repeat( ' ', WP_Swiper_Svg_Shape::MAX_BYTES ) ) ) );
	}

	public function test_the_kept_value_is_checked_again() {
		$value = WP_Swiper_Svg_Shape::sanitize( $this->file( 'ex-2' ) );
		$this->assertSame( $value, WP_Swiper_Svg_Shape::sanitize( $value ) );

		$data                       = json_decode( $value, true );
		$data['shapes'][0]['d']     = 'M 0 0 L 1 1 Z"/><script>alert(1)</script>';
		$this->assertSame( '', WP_Swiper_Svg_Shape::sanitize( wp_json_encode( $data ) ) );
		$data                       = json_decode( $value, true );
		$data['shapes'][0]['box']   = array( 1, 2 );
		$this->assertSame( '', WP_Swiper_Svg_Shape::sanitize( wp_json_encode( $data ) ) );
		$this->assertSame( '', WP_Swiper_Svg_Shape::sanitize( '{"v":1,"w":0,"h":10,"shapes":[]}' ) );
		$this->assertNull( WP_Swiper_Svg_Shape::decode( '' ) );

		// As a setting.
		$this->assertSame( $value, WP_Swiper_Settings::sanitize( array( 'art_shape' => $this->file( 'ex-2' ) ) )['art_shape'] );
		$this->assertSame( '', WP_Swiper_Settings::sanitize( array( 'art_shape' => 'nonsense' ) )['art_shape'] );
		$this->assertSame( '', WP_Swiper_Settings::defaults()['art_shape'] );
	}

	public function test_the_frame_leaves_out_the_margins() {
		$data = WP_Swiper_Svg_Shape::decode( WP_Swiper_Svg_Shape::sanitize( $this->svg( '<rect x="10" y="20" width="40" height="20"/><rect x="60" y="20" width="10" height="20"/>' ) ) );
		$crop = WP_Swiper_Svg_Shape::frame( $data, true );
		// The shapes' bounds: 60 by 20.
		$this->assertEqualsWithDelta( 3, $crop['ratio'], 0.0001 );
		$this->assertEqualsWithDelta( array( 0, 0, 40 / 60, 1 ), $crop['shapes'][0]['box'], 0.0001 );
		$this->assertSame( 'M 0 0 L 0.66667 0 L 0.66667 1 L 0 1 Z', $crop['shapes'][0]['d'] );
		// The whole drawing.
		$all = WP_Swiper_Svg_Shape::frame( $data, false );
		$this->assertEqualsWithDelta( 1, $all['ratio'], 0.0001 );
		$this->assertSame( 'M 0.1 0.2 L 0.5 0.2 L 0.5 0.4 L 0.1 0.4 Z', $all['shapes'][0]['d'] );
	}

	public function test_art_shows_a_photo_in_each_window() {
		$this->assertArrayHasKey( 'shape', WP_Swiper_Art::styles() );
		$this->assertFileExists( WP_SWIPER_DIR . 'assets/images/art/shape.svg' );
		$shape = WP_Swiper_Svg_Shape::sanitize( $this->file( 'ex-3' ) );
		$html  = $this->render( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $shape ), $this->slides( 4 ) );

		$this->assertStringContainsString( 'wp-swiper-art--shape', $html );
		$this->assertSame( 3, substr_count( $html, 'class="wp-swiper-art__piece"' ) );
		$config = $this->config( $html, 'data-wp-swiper-art' );
		$this->assertSame( 'shape', $config['style'] );
		$this->assertCount( 3, $config['shapes'] );
		$this->assertSame( array( 2, 0, 1 ), wp_list_pluck( $config['shapes'], 'z' ) );
		$this->assertEqualsWithDelta( 1.01145, $config['ratio'], 0.0001 );
		// What changes: a window at random by default; in turn; or all at once (as a slider).
		$this->assertSame( 'any', $config['swap']['target'] );
		$field = WP_Swiper_Settings::fields()['art_shape_target'];
		$this->assertSame( array( 'shape' ), $field['styles'] );
		$this->assertSame( array( 'advanced', 'controls' ), array( $field['section'], $field['panel'] ) );
		$this->assertSame( array( 'any', 'turn', 'advance', 'one' ), array_keys( $field['choices'] ) );
		$this->assertArrayNotHasKey( 'one', $config );
		$this->assertStringNotContainsString( 'wp-swiper-art--one', $html );
		foreach ( array( 'turn', 'advance', 'one' ) as $target ) {
			$config = $this->config( $this->render( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $shape, 'art_shape_target' => $target ), $this->slides( 4 ) ), 'data-wp-swiper-art' );
			$this->assertSame( $target, $config['swap']['target'] );
		}
		$this->assertSame( 'any', WP_Swiper_Settings::sanitize( array( 'art_shape_target' => 'nonsense' ) )['art_shape_target'] );
		// One photo through all windows: a stencil (art.js, art.css), with swapping off too.
		$this->assertSame( 'one', WP_Swiper_Settings::sanitize( array( 'art_shape_target' => 'one' ) )['art_shape_target'] );
		$one = $this->render( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $shape, 'art_shape_target' => 'one', 'art_swap' => false ), $this->slides( 4 ) );
		$this->assertStringContainsString( 'wp-swiper-art--one', $one );
		$this->assertTrue( $this->config( $one, 'data-wp-swiper-art' )['one'] );
		$this->assertFalse( $this->config( $one, 'data-wp-swiper-art' )['swap'] );
		// Only for SVG shape.
		$ring = $this->render( array( 'effect' => 'art', 'art_style' => 'ring', 'art_shape_target' => 'one' ), $this->slides( 4 ) );
		$this->assertStringNotContainsString( 'wp-swiper-art--one', $ring );
		$this->assertArrayNotHasKey( 'one', $this->config( $ring, 'data-wp-swiper-art' ) );

		// Appearance: the order the windows change in, only where they all change at once.
		$field = WP_Swiper_Settings::fields()['art_shape_appear'];
		$this->assertSame( array( 'shape' ), $field['styles'] );
		$this->assertSame( array( 'advanced', 'controls' ), array( $field['section'], $field['panel'] ) );
		$this->assertSame( array( 'all', 'random', 'ltr', 'ttb', 'diagonal', 'center' ), array_keys( $field['choices'] ) );
		$this->assertSame( 'all', WP_Swiper_Settings::sanitize( array( 'art_shape_appear' => 'sideways' ) )['art_shape_appear'] );
		$this->assertArrayNotHasKey( 'appear', $this->config( $one, 'data-wp-swiper-art' ) );
		foreach ( array( 'one', 'advance' ) as $target ) {
			$wave = $this->render( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $shape, 'art_shape_target' => $target, 'art_shape_appear' => 'diagonal' ), $this->slides( 4 ) );
			$this->assertSame( 'diagonal', $this->config( $wave, 'data-wp-swiper-art' )['appear'], $target );
		}
		$any = $this->render( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $shape, 'art_shape_target' => 'any', 'art_shape_appear' => 'diagonal' ), $this->slides( 4 ) );
		$this->assertArrayNotHasKey( 'appear', $this->config( $any, 'data-wp-swiper-art' ) );
		$config = $this->config( $html, 'data-wp-swiper-art' );
		$this->assertStringContainsString( '--wp-swiper-art-ratio:1.01145', $html );
		$this->assertSame( array( 'desktop' => 3, 'tablet' => 3, 'mobile' => 3 ), WP_Swiper_Art::counts( $this->settings( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $shape ) ) ) );

		// With the file's margins: the whole drawing.
		$config = $this->config( $this->render( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $shape, 'art_shape_margins' => true ), $this->slides( 4 ) ), 'data-wp-swiper-art' );
		$this->assertEqualsWithDelta( 1, $config['ratio'], 0.0001 );
	}

	public function test_art_photos_are_the_full_size_only() {
		$slides = $this->slides( 2 );
		// Smaller copies, as WordPress makes them: elsewhere it offers them (srcset).
		foreach ( $slides as $slide ) {
			$meta          = wp_get_attachment_metadata( $slide['id'] );
			$base          = pathinfo( $meta['file'], PATHINFO_FILENAME );
			$meta['sizes'] = array(
				'medium' => array( 'file' => $base . '-300x150.jpg', 'width' => 300, 'height' => 150, 'mime-type' => 'image/jpeg' ),
				'large'  => array( 'file' => $base . '-1024x512.jpg', 'width' => 1024, 'height' => 512, 'mime-type' => 'image/jpeg' ),
			);
			wp_update_attachment_metadata( $slide['id'], $meta );
		}
		$this->assertStringContainsString( 'srcset=', wp_get_attachment_image( $slides[0]['id'], 'full' ) );

		$html = $this->render( array( 'effect' => 'art' ), $slides );
		preg_match_all( '/<img[^>]*class="wp-swiper-art__image"[^>]*>/', $html, $images );
		$this->assertCount( 2, $images[0] );
		foreach ( $images[0] as $i => $img ) {
			// One file, nothing for the browser to pick a smaller one from.
			$this->assertStringContainsString( 'src="' . wp_get_attachment_image_url( $slides[ $i ]['id'], 'full' ) . '"', $img );
			$this->assertStringNotContainsString( 'srcset=', $img );
			$this->assertStringNotContainsString( 'sizes=', $img );
		}
		// Only while Art draws them: other images keep WordPress's copies.
		$this->assertFalse( has_filter( 'wp_calculate_image_srcset', '__return_false' ) );
	}

	public function test_no_file_yet() {
		$values = array( 'effect' => 'art', 'art_style' => 'shape' );
		$this->assertSame( '', $this->render( $values ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertStringContainsString( 'upload an SVG file for the SVG shape style', $this->render( $values ) );
	}

	public function test_the_field_in_the_editor() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$shape = WP_Swiper_Svg_Shape::sanitize( wp_json_encode( array( 'name' => 'ex-3.svg', 'svg' => $this->file( 'ex-3' ) ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( null, array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $shape ) ) ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'data-field="art_shape"', $html );
		$this->assertStringContainsString( 'data-styles="shape"', $html );
		$this->assertStringContainsString( '<span class="wp-swiper-shape__summary">ex-3.svg: 3 shapes</span>', $html );
		// The windows in small, in the drawing's stacking, the main one marked.
		$this->assertSame( 1, substr_count( $html, 'class="wp-swiper-shape__main"' ) );
		$this->assertSame( 2, substr_count( $html, 'class="wp-swiper-shape__window"' ) );
		$this->assertStringContainsString( 'accept=".svg,image/svg+xml"', $html );
		// Gap and Rounded corners are not for it; Height is (every Style's).
		$this->assertSame( 1, preg_match( '/data-field="art_gap"[^>]*data-styles="ring ring-text grid-circle grid-circle-text grid-square grid-square-text"/', $html ) );
		$this->assertSame( 1, preg_match( '/data-field="art_height" data-effects="art">/', $html ) );
	}

	public function test_a_file_per_device() {
		$desktop = WP_Swiper_Svg_Shape::sanitize( $this->file( 'ex-3' ) );
		$mobile  = WP_Swiper_Svg_Shape::sanitize( $this->file( 'ex-1' ) );
		$this->assertTrue( WP_Swiper_Settings::fields()['art_shape']['responsive'] );
		$clean = WP_Swiper_Settings::sanitize( array( 'art_shape' => $desktop, 'art_shape_tablet' => '', 'art_shape_mobile' => $mobile ) );
		$this->assertSame( '', $clean['art_shape_tablet'] );
		$this->assertSame( $mobile, $clean['art_shape_mobile'] );

		// Tablet takes the desktop's; mobile its own (3 windows, then 2).
		$values = array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $desktop, 'art_shape_mobile' => $mobile );
		$this->assertSame( array( 'desktop' => 3, 'tablet' => 3, 'mobile' => 2 ), WP_Swiper_Art::counts( $this->settings( $values ) ) );
		$html   = $this->render( $values, $this->slides( 4 ) );
		$config = $this->config( $html, 'data-wp-swiper-art' );
		// As many pieces as the most windows; each device's windows and proportions.
		$this->assertSame( 3, substr_count( $html, 'class="wp-swiper-art__piece"' ) );
		$this->assertCount( 3, $config['shapes'] );
		$this->assertCount( 3, $config['shapeDevices']['tablet']['shapes'] );
		$this->assertCount( 2, $config['shapeDevices']['mobile']['shapes'] );
		$this->assertSame( $config['shapes'], $config['shapeDevices']['desktop']['shapes'] );
		$this->assertStringContainsString( '--wp-swiper-art-ratio-mobile:' . $config['shapeDevices']['mobile']['ratio'], $html );
		$this->assertStringContainsString( '--wp-swiper-art-ratio-tablet:' . $config['ratio'], $html );

		// One file for all: as before, nothing per device.
		$one = $this->render( array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => $desktop ), $this->slides( 4 ) );
		$this->assertArrayNotHasKey( 'shapeDevices', $this->config( $one, 'data-wp-swiper-art' ) );
		$this->assertStringNotContainsString( '--wp-swiper-art-ratio-mobile', $one );

		// The field: a box per device, tablet and mobile hidden; an empty one says it inherits.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( null, $values ) ) );
		$admin = ob_get_clean();
		$this->assertStringContainsString( '<div class="wp-swiper-devices__input wp-swiper-shape-device" data-device="desktop">', $admin );
		$this->assertStringContainsString( '<div class="wp-swiper-devices__input wp-swiper-shape-device" data-device="tablet" hidden>', $admin );
		$this->assertStringContainsString( '<div class="wp-swiper-devices__input wp-swiper-shape-device" data-device="mobile" hidden>', $admin );
		$this->assertStringContainsString( 'name="wp_swiper_settings[art_shape_mobile]"', $admin );
		$this->assertStringContainsString( '<span class="wp-swiper-shape__summary">As on the larger screen: upload a file for this one to change it.</span>', $admin );
		$this->assertStringContainsString( '<span class="wp-swiper-shape__summary">2 shapes</span>', $admin );
	}
}
