<?php
/**
 * WP_Swiper_Settings: the schema, sanitizing, per-device values, the
 * per-effect Layout values and the helpers that decide what applies.
 */

class Test_WP_Swiper_Settings extends WP_Swiper_TestCase {

	public function test_every_field_has_a_default_for_every_device() {
		$defaults = WP_Swiper_Settings::defaults();
		foreach ( WP_Swiper_Settings::fields() as $key => $field ) {
			foreach ( WP_Swiper_Settings::device_keys( $key, $field ) as $storage_key ) {
				$this->assertArrayHasKey( $storage_key, $defaults, $storage_key );
			}
		}
	}

	public function test_every_field_is_valid() {
		$pages = WP_Swiper_Settings::pages();
		foreach ( WP_Swiper_Settings::fields() as $key => $field ) {
			$this->assertContains( $field['section'], array( 'advanced', 'layout', 'caption', 'description' ), $key );
			if ( isset( $field['panel'] ) ) {
				$this->assertArrayHasKey( $field['panel'], $pages, "$key: unknown page" );
			}
			if ( 'select' === $field['type'] ) {
				$this->assertArrayHasKey( $field['default'], $field['choices'], "$key: default not a choice" );
			}
			if ( in_array( $field['type'], array( 'number', 'float' ), true ) && '' !== $field['default'] ) {
				$this->assertGreaterThanOrEqual( $field['min'], $field['default'], $key );
				$this->assertLessThanOrEqual( $field['max'], $field['default'], $key );
			}
			if ( ! empty( $field['effects'] ) ) {
				$this->assertSame( array(), array_diff( $field['effects'], array_keys( WP_Swiper_Settings::fields()['effect']['choices'] ) ), "$key: unknown effect" );
			}
		}
	}

	public function test_every_menu_page_has_fields() {
		$used = array();
		foreach ( WP_Swiper_Settings::fields() as $field ) {
			$used[] = isset( $field['panel'] ) ? $field['panel'] : $field['section'];
		}
		foreach ( array_keys( WP_Swiper_Settings::pages() ) as $page ) {
			$this->assertContains( $page, $used, "Page $page has no fields." );
		}
	}

	public function test_every_field_of_a_page_with_blocks_is_in_one_block() {
		$fields = WP_Swiper_Settings::fields();
		foreach ( WP_Swiper_Settings::blocks() as $page => $blocks ) {
			$this->assertArrayHasKey( $page, WP_Swiper_Settings::pages() );

			$on_page = array();
			foreach ( $fields as $key => $field ) {
				if ( empty( $field['inline_in'] ) && ( isset( $field['panel'] ) ? $field['panel'] : $field['section'] ) === $page ) {
					$on_page[] = $key;
				}
			}
			$in_blocks = array();
			foreach ( $blocks as $name => $block ) {
				$this->assertNotEmpty( $block['title'], "$page/$name" );
				foreach ( $block['fields'] as $key ) {
					$this->assertContains( $key, $on_page, "$page/$name: $key is not a field of this page." );
				}
				$in_blocks = array_merge( $in_blocks, $block['fields'] );
			}
			sort( $on_page );
			sort( $in_blocks );
			$this->assertSame( $on_page, $in_blocks, "$page: every field in exactly one block." );
		}
	}

	public function test_sanitize_clamps_and_validates_each_type() {
		$clean = WP_Swiper_Settings::sanitize(
			array(
				'effect'            => 'nonsense',
				'loop'              => '',
				'autoplay'          => '1',
				'speed'             => '99999',
				'autoplay_delay'    => '-5',
				'slides_per_view'   => '2.456',
				'navigation_color'  => 'red',
				'caption_color'     => '#abcdef',
				'css_class'         => 'one  two"><script> two',
				'css_id'            => 'hero slider!<b>',
				'thumbs_ratio_custom' => '16 : 9',
				'collage_seed'      => '0',
				'lightbox_radius'   => '',
				'navigation_size'   => '500',
			)
		);

		$this->assertSame( 'slide', $clean['effect'] );
		$this->assertFalse( $clean['loop'] );
		$this->assertTrue( $clean['autoplay'] );
		$this->assertSame( 10000, $clean['speed'] );
		$this->assertSame( 500, $clean['autoplay_delay'] );
		$this->assertSame( 2.46, $clean['slides_per_view'] );
		$this->assertSame( '#ffffff', $clean['navigation_color'] );
		$this->assertSame( '#abcdef', $clean['caption_color'] );
		$this->assertSame( 'one twoscript two', $clean['css_class'] );
		$this->assertSame( 'hero-sliderb', $clean['css_id'] );
		$this->assertSame( '16x9', $clean['thumbs_ratio_custom'] );
		$this->assertSame( 1, $clean['collage_seed'] );
		$this->assertSame( '', $clean['lightbox_radius'] );
		$this->assertSame( 120, $clean['navigation_size'] );
		$this->assertSame( 6, $clean['_version'] );
	}

	public function test_sanitize_tablet_and_mobile_values_may_inherit() {
		$clean = WP_Swiper_Settings::sanitize(
			array(
				'height'                => '400',
				'height_tablet'         => '',
				'height_mobile'         => '9999',
				'thumbs_position'       => 'left',
				'thumbs_position_tablet' => 'diagonal',
				'thumbs_position_mobile' => 'top',
			)
		);
		$this->assertSame( 400, $clean['height'] );
		$this->assertSame( '', $clean['height_tablet'] );
		$this->assertSame( 2000, $clean['height_mobile'] );
		$this->assertSame( '', $clean['thumbs_position_tablet'] );
		$this->assertSame( 'top', $clean['thumbs_position_mobile'] );
	}

	public function test_responsive_inherits_from_larger_screens() {
		$settings = $this->settings(
			array(
				'height'        => 600,
				'height_tablet' => '',
				'height_mobile' => 300,
				'space_between' => 10,
				'space_between_tablet' => 20,
				'space_between_mobile' => '',
			)
		);
		$this->assertSame( array( 'desktop' => 600, 'tablet' => 600, 'mobile' => 300 ), WP_Swiper_Settings::responsive( $settings, 'height' ) );
		$this->assertSame( array( 'desktop' => 10, 'tablet' => 20, 'mobile' => 20 ), WP_Swiper_Settings::responsive( $settings, 'space_between' ) );
	}

	public function test_sanitize_keeps_the_layout_of_every_effect() {
		$fade   = array( 'border_radius' => '12', 'height' => '300', 'zoom' => '1' );
		$clean  = WP_Swiper_Settings::sanitize(
			array(
				'effect'        => 'slide',
				'border_radius' => '5',
				'layouts'       => wp_json_encode( array( 'fade' => $fade, 'unknown' => array( 'height' => 1 ) ) ),
			)
		);
		$this->assertSame( 5, $clean['layouts']['slide']['border_radius'] );
		$this->assertSame( 12, $clean['layouts']['fade']['border_radius'] );
		$this->assertTrue( $clean['layouts']['fade']['zoom'] );
		$this->assertArrayNotHasKey( 'unknown', $clean['layouts'] );
		// Only Layout fields in a layout.
		$this->assertArrayNotHasKey( 'autoplay', $clean['layouts']['fade'] );

		$broken = WP_Swiper_Settings::sanitize( array( 'layouts' => '{not json' ) );
		$this->assertSame( array( 'slide' ), array_keys( $broken['layouts'] ) );
	}

	public function test_layouts_fill_in_fields_added_since_saving() {
		$settings = $this->settings( array( 'effect' => 'slide', 'border_radius' => 3 ) );
		$layouts  = WP_Swiper_Settings::layouts( $settings, array( 'fade' => array( 'border_radius' => 7 ) ) );

		$this->assertSame( 3, $layouts['slide']['border_radius'] );
		$this->assertSame( 7, $layouts['fade']['border_radius'] );
		// Saved before zoom existed: its default.
		$this->assertFalse( $layouts['fade']['zoom'] );
		// Never used: the defaults.
		$this->assertSame( WP_Swiper_Settings::defaults()['height'], $layouts['cube']['height'] );
		$this->assertCount( count( WP_Swiper_Settings::fields()['effect']['choices'] ), $layouts );
	}

	/**
	 * @dataProvider ratios
	 */
	public function test_normalize_ratio( $input, $expected ) {
		$this->assertSame( $expected, WP_Swiper_Settings::normalize_ratio( $input ) );
	}

	public function ratios() {
		return array(
			array( '4x3', '4x3' ),
			array( '16 : 9', '16x9' ),
			array( '2,35×1', '2.35x1' ),
			array( '4х3', '4x3' ), // Cyrillic "х".
			array( '1/1', '1x1' ),
			array( '0x5', '0.1x5' ),
			array( '500x1', '100x1' ),
			array( '4.567x3', '4.57x3' ),
			array( 'wide', '' ),
			array( '4x', '' ),
			array( '', '' ),
		);
	}

	public function test_thumbs_ratio_per_device() {
		$settings = $this->settings(
			array(
				'thumbs_ratio'               => 'custom',
				'thumbs_ratio_custom'        => '5x2',
				'thumbs_ratio_tablet'        => '16-9',
				'thumbs_ratio_mobile'        => '',
			)
		);
		$this->assertSame(
			array(
				'desktop' => array( 5.0, 2.0 ),
				'tablet'  => array( 16.0, 9.0 ),
				'mobile'  => array( 16.0, 9.0 ),
			),
			WP_Swiper_Settings::thumbs_ratio( $settings )
		);
	}

	public function test_css_heights() {
		$settings = $this->settings( array( 'height' => 0, 'height_tablet' => 50, 'height_unit' => 'vh' ) );
		$this->assertSame( array( 'desktop' => '500px', 'tablet' => '50vh', 'mobile' => '50vh' ), WP_Swiper_Settings::css_heights( $settings ) );
	}

	public function test_is_expand() {
		$on = array( 'effect' => 'slide', 'expand_active' => true );
		$this->assertTrue( WP_Swiper_Settings::is_expand( $this->settings( $on ) ) );
		$this->assertFalse( WP_Swiper_Settings::is_expand( $this->settings( array( 'image_fit' => 'contain' ) + $on ) ) );
		$this->assertFalse( WP_Swiper_Settings::is_expand( $this->settings( array( 'auto_width' => true ) + $on ) ) );
		$this->assertFalse( WP_Swiper_Settings::is_expand( $this->settings( array( 'marquee' => true ) + $on ) ) );
		$this->assertFalse( WP_Swiper_Settings::is_expand( $this->settings( array( 'effect' => 'fade' ) + $on ) ) );
	}

	public function test_groups_only_where_steps_are_slides() {
		$settings = $this->settings( array( 'slides_per_group' => 2, 'slides_per_group_mobile' => 1 ) );
		$this->assertSame( array( 'desktop' => 2, 'tablet' => 2, 'mobile' => 1 ), WP_Swiper_Settings::groups( $settings ) );
		$this->assertFalse( WP_Swiper_Settings::groups( $this->settings( array( 'effect' => 'fade' ) ) ) );
		$this->assertFalse( WP_Swiper_Settings::groups( $this->settings( array( 'marquee' => true ) ) ) );
	}

	public function test_carousel_shares() {
		$this->assertFalse( WP_Swiper_Settings::carousel( $this->settings( array( 'effect' => 'creative' ) ) ) );

		$carousel = WP_Swiper_Settings::carousel(
			$this->settings(
				array(
					'effect'              => 'creative',
					'creative_style'      => 'carousel',
					'creative_neighbours' => 1,
					'creative_offset'     => 100,
					'creative_scale'      => 1,
					'creative_opacity'    => 50,
				)
			)
		);
		// Three images side by side, each a third.
		$this->assertSame( 0.3333, $carousel['width'] );
		$this->assertSame( 0.5, $carousel['opacity'] );
	}

	/**
	 * @dataProvider zoom_cases
	 */
	public function test_is_zoom( $values, $expected ) {
		$this->assertSame( $expected, WP_Swiper_Settings::is_zoom( $this->settings( array( 'zoom' => true ) + $values ) ) );
	}

	public function zoom_cases() {
		return array(
			'fade'                   => array( array( 'effect' => 'fade' ), true ),
			'cube'                   => array( array( 'effect' => 'cube' ), true ),
			'thumbs'                 => array( array( 'effect' => 'thumbs' ), true ),
			'coverflow: no'          => array( array( 'effect' => 'coverflow' ), false ),
			'collage: no'            => array( array( 'effect' => 'collage' ), false ),
			'slide, one in view'     => array( array( 'effect' => 'slide', 'slides_per_view' => 1 ), true ),
			'slide, two on desktop'  => array( array( 'effect' => 'slide', 'slides_per_view' => 2, 'slides_per_view_mobile' => 1 ), false ),
			'slide, two on mobile'   => array( array( 'effect' => 'slide', 'slides_per_view' => 1, 'slides_per_view_mobile' => 2 ), false ),
			'slide, wider active'    => array( array( 'effect' => 'slide', 'slides_per_view' => 3, 'expand_active' => true ), true ),
			'slide, marquee'         => array( array( 'effect' => 'slide', 'marquee' => true ), false ),
			'slide, width from image' => array( array( 'effect' => 'slide', 'auto_width' => true ), false ),
		);
	}

	public function test_is_zoom_off() {
		$this->assertFalse( WP_Swiper_Settings::is_zoom( $this->settings( array( 'effect' => 'fade' ) ) ) );
	}

	public function test_zoom_duration() {
		$this->assertSame( 3000, WP_Swiper_Settings::zoom_duration( $this->settings( array( 'zoom_duration' => 3000 ) ) ) );
		$this->assertSame( 4600, WP_Swiper_Settings::zoom_duration( $this->settings( array( 'autoplay' => true, 'autoplay_delay' => 4000, 'speed' => 600 ) ) ) );
		$this->assertSame( 6000, WP_Swiper_Settings::zoom_duration( $this->settings( array( 'autoplay' => false ) ) ) );
	}

	public function test_is_background() {
		foreach ( array( 'slide', 'fade', 'collage' ) as $effect ) {
			$this->assertTrue( WP_Swiper_Settings::is_background( $this->settings( array( 'effect' => $effect, 'background' => true ) ) ), $effect );
		}
		$this->assertFalse( WP_Swiper_Settings::is_background( $this->settings( array( 'effect' => 'cube', 'background' => true ) ) ) );
		$this->assertFalse( WP_Swiper_Settings::is_background( $this->settings( array( 'effect' => 'fade' ) ) ) );
	}

	/**
	 * @dataProvider embeds
	 */
	public function test_parse_embed( $url, $expected ) {
		$embed = WP_Swiper_Settings::parse_embed( $url );
		if ( false === $expected ) {
			$this->assertFalse( $embed );
			return;
		}
		$this->assertSame( $expected, array_intersect_key( $embed, $expected ) );
	}

	public function embeds() {
		return array(
			array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', array( 'provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'vertical' => false ) ),
			array( 'https://youtu.be/dQw4w9WgXcQ?t=10', array( 'provider' => 'youtube', 'id' => 'dQw4w9WgXcQ' ) ),
			array( 'https://www.youtube.com/watch?list=x&v=dQw4w9WgXcQ', array( 'id' => 'dQw4w9WgXcQ' ) ),
			array( 'https://www.youtube.com/shorts/dQw4w9WgXcQ', array( 'id' => 'dQw4w9WgXcQ', 'vertical' => true ) ),
			array( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', array( 'id' => 'dQw4w9WgXcQ' ) ),
			array( 'https://vimeo.com/123456789', array( 'provider' => 'vimeo', 'id' => '123456789', 'hash' => '' ) ),
			array( 'https://vimeo.com/123456789/abcdef1234', array( 'id' => '123456789', 'hash' => 'abcdef1234' ) ),
			array( 'https://player.vimeo.com/video/123456789?h=abc123def', array( 'id' => '123456789', 'hash' => 'abc123def' ) ),
			array( 'https://example.com/watch?v=dQw4w9WgXcQ', false ),
			array( 'javascript:alert(1)//youtu.be/dQw4w9WgXcQ', false ),
			array( '', false ),
		);
	}

	public function test_sanitize_classes_and_id() {
		$this->assertSame( 'a b', WP_Swiper_Settings::sanitize_classes( ' a  b a ' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_classes( '"><' ) );
		$this->assertSame( 'my-hero_1', WP_Swiper_Settings::sanitize_id( 'my hero_1' ) );
	}

	public function test_can_describe() {
		$this->assertTrue( WP_Swiper_Settings::can_describe( $this->settings() ) );
		foreach ( array( 'fade', 'cube', 'coverflow', 'flip', 'cards', 'creative', 'thumbs' ) as $effect ) {
			$this->assertTrue( WP_Swiper_Settings::can_describe( $this->settings( array( 'effect' => $effect ) ) ), $effect );
		}
		$this->assertTrue( WP_Swiper_Settings::can_describe( $this->settings( array( 'expand_active' => true ) ) ) );

		$this->assertFalse( WP_Swiper_Settings::can_describe( $this->settings( array( 'direction' => 'vertical' ) ) ) );
		$this->assertFalse( WP_Swiper_Settings::can_describe( $this->settings( array( 'effect' => 'collage' ) ) ) );
		$this->assertFalse( WP_Swiper_Settings::can_describe( $this->settings( array( 'marquee' => true ) ) ) );
		$this->assertFalse( WP_Swiper_Settings::can_describe( $this->settings( array( 'background' => true ) ) ) );
		// Background is for some effects only: a cube is a slider as usual.
		$this->assertTrue( WP_Swiper_Settings::can_describe( $this->settings( array( 'effect' => 'cube', 'background' => true ) ) ) );
	}

	/**
	 * @dataProvider description_motions
	 */
	public function test_description_motion( $values, $expected ) {
		$this->assertSame( $expected, WP_Swiper_Settings::description_motion( $this->settings( $values ) ) );
	}

	public function description_motions() {
		return array(
			'slide'              => array( array(), 'slide' ),
			'wider active slide' => array( array( 'expand_active' => true ), 'slide' ),
			'fade'               => array( array( 'effect' => 'fade' ), 'fade' ),
			'cube'               => array( array( 'effect' => 'cube' ), 'cube' ),
			'flip'               => array( array( 'effect' => 'flip' ), 'flip' ),
			'coverflow'          => array( array( 'effect' => 'coverflow' ), 'coverflow' ),
			'cards'              => array( array( 'effect' => 'cards' ), 'cards' ),
			'creative push'      => array( array( 'effect' => 'creative' ), 'push' ),
			'creative door'      => array( array( 'effect' => 'creative', 'creative_style' => 'door' ), 'cube' ),
			'creative carousel'  => array( array( 'effect' => 'creative', 'creative_style' => 'carousel' ), 'coverflow' ),
			'thumbs sliding'     => array( array( 'effect' => 'thumbs' ), 'slide' ),
			'thumbs fading'      => array( array( 'effect' => 'thumbs', 'thumbs_transition' => 'fade' ), 'fade' ),
			'own fade'           => array( array( 'effect' => 'cube', 'description_transition' => 'fade' ), 'fade' ),
			'own slide'          => array( array( 'effect' => 'fade', 'description_transition' => 'slide' ), 'slide' ),
			'none'               => array( array( 'effect' => 'cube', 'description_transition' => 'none' ), 'none' ),
		);
	}

	public function test_sanitize_description() {
		$this->assertSame(
			'<p>Hi <strong>you</strong> <em>and</em> <span style="text-decoration: underline">me</span> <a href="https://example.com" target="_blank" rel="noopener">here</a></p><ul><li>One</li></ul>',
			WP_Swiper_Settings::sanitize_description( ' <p>Hi <strong>you</strong> <em>and</em> <span style="text-decoration: underline;">me</span> <a href="https://example.com" target="_blank" rel="noopener" onclick="x()">here</a></p><ul><li>One</li></ul> ' )
		);
		// Other markup goes, its text stays; scripts and unsafe links do not run.
		$this->assertSame( '<p>Hi alert(1)</p>', WP_Swiper_Settings::sanitize_description( '<p>Hi <img src=x onerror=alert(1)><script>alert(1)</script></p>' ) );
		$this->assertSame( '<a href="alert(1)">x</a>', WP_Swiper_Settings::sanitize_description( '<a href="javascript:alert(1)">x</a>' ) );
		// No text: none.
		foreach ( array( '', '<p>&nbsp;</p>', '<p> </p><br>', '<strong></strong>', "<p>\xC2\xA0</p>" ) as $empty ) {
			$this->assertSame( '', WP_Swiper_Settings::sanitize_description( $empty ), $empty );
		}
		$this->assertSame( 'Plain text', WP_Swiper_Settings::sanitize_description( 'Plain text' ) );
		// Headings, without attributes.
		$this->assertSame(
			'<h1>One</h1><h2>Two</h2><h3>Three</h3><h4>Four</h4><h5>Five</h5><h6>Six</h6>',
			WP_Swiper_Settings::sanitize_description( '<h1 class="x">One</h1><h2 style="color:red">Two</h2><h3>Three</h3><h4>Four</h4><h5>Five</h5><h6>Six</h6>' )
		);
		$this->assertSame( '', WP_Swiper_Settings::sanitize_description( '<h2></h2>' ) );
	}

	public function test_sanitize_caption() {
		// Bold, italic, underline and line breaks.
		$this->assertSame(
			'<strong>Big</strong> <em>and</em> <span style="text-decoration: underline">me</span><br>next line',
			WP_Swiper_Settings::sanitize_caption( ' <strong>Big</strong> <em>and</em> <span style="text-decoration: underline;">me</span><br>next line ' )
		);
		// Blocks become lines (no words run together), links and scripts go, their text stays.
		$this->assertSame( 'One<br>Two<br>link', WP_Swiper_Settings::sanitize_caption( '<p>One</p><h2>Two</h2><a href="https://example.com">link</a>' ) );
		$this->assertSame( 'Hi alert(1)', WP_Swiper_Settings::sanitize_caption( 'Hi <script>alert(1)</script>' ) );
		// No line breaks around the text.
		$this->assertSame( 'Text', WP_Swiper_Settings::sanitize_caption( '<br><p>Text</p><br />' ) );
		// A plain caption saved before: kept, its "&" as HTML.
		$this->assertSame( 'Salt &amp; pepper', WP_Swiper_Settings::sanitize_caption( 'Salt & pepper' ) );
		foreach ( array( '', '<br>', '<p>&nbsp;</p>', '<strong> </strong>' ) as $empty ) {
			$this->assertSame( '', WP_Swiper_Settings::sanitize_caption( $empty ), $empty );
		}
	}

	/* Continuous scroll separator ------------------------------------ */

	public function test_sanitize_keeps_only_an_image_for_an_image_field() {
		$image = $this->image();
		$this->assertSame( $image, WP_Swiper_Settings::sanitize( array( 'marquee_separator_image' => (string) $image ) )['marquee_separator_image'] );
		$others = array( '', '0', 'abc', (string) $this->video(), (string) self::factory()->post->create(), '999999' );
		foreach ( $others as $value ) {
			$this->assertSame( 0, WP_Swiper_Settings::sanitize( array( 'marquee_separator_image' => $value ) )['marquee_separator_image'], $value );
		}
		$this->assertSame( 'none', WP_Swiper_Settings::sanitize( array( 'marquee_separator' => 'star' ) )['marquee_separator'] );
	}

	public function test_separator_needs_the_marquee_and_an_image_when_own() {
		$marquee = array( 'marquee' => true, 'marquee_separator' => 'dot' );
		$this->assertFalse( WP_Swiper_Settings::separator( $this->settings( array( 'marquee' => true ) ) ) );
		$this->assertFalse( WP_Swiper_Settings::separator( $this->settings( array( 'marquee_separator' => 'dot' ) ) ) );
		$this->assertFalse( WP_Swiper_Settings::separator( $this->settings( array( 'effect' => 'fade' ) + $marquee ) ) );
		$this->assertFalse( WP_Swiper_Settings::separator( $this->settings( array( 'marquee_separator' => 'image' ) + $marquee ) ) );

		$dot = WP_Swiper_Settings::separator( $this->settings( array( 'marquee_separator_size' => 12, 'marquee_separator_size_mobile' => 6 ) + $marquee ) );
		$this->assertSame( 'dot', $dot['type'] );
		$this->assertSame( 0, $dot['image'] );
		$this->assertSame( array( 'desktop' => 12, 'tablet' => 12, 'mobile' => 6 ), $dot['size'] );

		$image = $this->image( 64, 64 );
		$own   = WP_Swiper_Settings::separator( $this->settings( array( 'marquee_separator' => 'image', 'marquee_separator_image' => $image ) + $marquee ) );
		$this->assertSame( 'image', $own['type'] );
		$this->assertSame( $image, $own['image'] );
	}

	public function test_gaps_are_at_least_the_separator() {
		$values = array( 'space_between' => 30, 'space_between_tablet' => 8, 'space_between_mobile' => '' );
		$this->assertSame( array( 'desktop' => 30, 'tablet' => 8, 'mobile' => 8 ), WP_Swiper_Settings::gaps( $this->settings( $values ) ) );

		$values += array( 'marquee' => true, 'marquee_separator' => 'dot', 'marquee_separator_size' => 20, 'marquee_separator_size_mobile' => 4 );
		$this->assertSame( array( 'desktop' => 30, 'tablet' => 20, 'mobile' => 8 ), WP_Swiper_Settings::gaps( $this->settings( $values ) ) );
	}
}
