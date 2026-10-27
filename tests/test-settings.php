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

	public function test_height_unit_per_device() {
		// Tablet in %, mobile "Auto"; an empty unit takes the larger screen's.
		$settings = $this->settings(
			array(
				'height'             => 400,
				'height_tablet'      => 80,
				'height_mobile'      => 300,
				'height_unit'        => 'px',
				'height_unit_tablet' => '%',
				'height_unit_mobile' => 'auto',
			)
		);
		$this->assertSame( array( 'desktop' => '400px', 'tablet' => '80%', 'mobile' => 'auto' ), WP_Swiper_Settings::css_heights( $settings ) );
		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => true ), WP_Swiper_Settings::auto_heights( $settings ) );
		$this->assertTrue( WP_Swiper_Settings::is_auto_height( $settings ) );
		$this->assertFalse( WP_Swiper_Settings::is_auto_height_everywhere( $settings ) );
		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => true ), WP_Swiper_Settings::fill_config( $settings ) );

		$inherit = $this->settings( array( 'height_unit' => 'auto', 'height_unit_tablet' => '', 'height_unit_mobile' => '' ) );
		$this->assertTrue( WP_Swiper_Settings::is_auto_height_everywhere( $inherit ) );

		$none = $this->settings( array( 'height_unit' => 'vh', 'height_unit_mobile' => 'px' ) );
		$this->assertFalse( WP_Swiper_Settings::is_auto_height( $none ) );
		$this->assertFalse( WP_Swiper_Settings::fill_config( $none ) );
	}

	public function test_sanitize_keeps_a_height_unit_per_device() {
		$clean = WP_Swiper_Settings::sanitize( array( 'height_unit' => 'vh', 'height_unit_tablet' => '', 'height_unit_mobile' => 'auto' ) );
		$this->assertSame( 'vh', $clean['height_unit'] );
		$this->assertSame( '', $clean['height_unit_tablet'] );
		$this->assertSame( 'auto', $clean['height_unit_mobile'] );

		$bad = WP_Swiper_Settings::sanitize( array( 'height_unit' => 'em', 'height_unit_tablet' => 'rem' ) );
		$this->assertSame( 'px', $bad['height_unit'] );
		$this->assertSame( '', $bad['height_unit_tablet'] );
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
		// Text color (the editor's button): a span's, kept in captions and descriptions.
		$this->assertSame( '<h2><span style="color: #2dc26b">Green</span></h2>', WP_Swiper_Settings::sanitize_description( '<h2><span style="color: #2dc26b;">Green</span></h2>' ) );
		$this->assertSame( 'A <span style="color: #e03e2d">red</span> word', WP_Swiper_Settings::sanitize_caption( 'A <span style="color: #e03e2d;">red</span> word' ) );
		// Alignment (the editor's buttons): kept on paragraphs, headings and list
		// items, their only attribute; a caption's aligned lines become block spans.
		$this->assertSame(
			'<p style="text-align: center">Para</p><h2 style="text-align: right">Head</h2><ul><li style="text-align: left">i</li></ul><p>p</p>',
			WP_Swiper_Settings::sanitize_description( '<p style="text-align: center;">Para</p><h2 style="color:red; text-align: right" class="x">Head</h2><ul><li style="text-align:left">i</li></ul><p style="position: fixed">p</p>' )
		);
		$this->assertSame(
			'Plain<br><span style="display: block;text-align: center">Center <span style="color: #e03e2d">red</span></span>',
			WP_Swiper_Settings::sanitize_caption( 'Plain<br><div style="text-align: center;" onclick="x()">Center <span style="color: #e03e2d;">red</span></div>' )
		);
		$this->assertSame( '<p>pasted</p><p>two</p>', WP_Swiper_Settings::sanitize_caption( '<p>pasted</p><p>two</p>' ) );
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
		// Paragraphs and headings (the editor's Format) with their alignment only;
		// links and scripts go, their text stays.
		$this->assertSame( '<p>One</p><h2>Two</h2>link', WP_Swiper_Settings::sanitize_caption( '<p>One</p><h2>Two</h2><a href="https://example.com">link</a>' ) );
		$this->assertSame(
			'<p style="text-align: right">R</p><h3 style="text-align: center">C</h3><h6>Six</h6>',
			WP_Swiper_Settings::sanitize_caption( '<p class="x" style="color:red; text-align: right" onclick="x()">R</p><h3 style="text-align:center">C</h3><h6 style="position: fixed">Six</h6>' )
		);
		$this->assertSame( 'Hi alert(1)', WP_Swiper_Settings::sanitize_caption( 'Hi <script>alert(1)</script>' ) );
		// Other blocks (pasted) become lines, so no words run together.
		$this->assertSame( 'a<br>b', WP_Swiper_Settings::sanitize_caption( '<div>a</div><div>b</div>' ) );
		// No line breaks around the text.
		$this->assertSame( '<p>Text</p>', WP_Swiper_Settings::sanitize_caption( '<br><p>Text</p><br />' ) );
		// A plain caption saved before: kept, its "&" as HTML.
		$this->assertSame( 'Salt &amp; pepper', WP_Swiper_Settings::sanitize_caption( 'Salt & pepper' ) );
		foreach ( array( '', '<br>', '<p>&nbsp;</p>', '<h2></h2>', '<strong> </strong>' ) as $empty ) {
			$this->assertSame( '', WP_Swiper_Settings::sanitize_caption( $empty ), $empty );
		}
	}

	/* Image proportions ---------------------------------------------- */

	public function test_slide_ratios_per_device() {
		$this->assertFalse( WP_Swiper_Settings::slide_ratios( $this->settings() ) );

		$ratios = WP_Swiper_Settings::slide_ratios( $this->settings( array( 'slide_ratio' => '4-3', 'slide_ratio_tablet' => 'custom', 'slide_ratio_custom_tablet' => '2.35x1', 'slide_ratio_mobile' => 'off' ) ) );
		$this->assertSame( array( 4.0, 3.0 ), $ratios['desktop'] );
		$this->assertSame( array( 2.35, 1.0 ), $ratios['tablet'] );
		$this->assertFalse( $ratios['mobile'] );

		// Inherited from the larger screen.
		$this->assertSame( array( 16.0, 9.0 ), WP_Swiper_Settings::slide_ratios( $this->settings( array( 'slide_ratio' => '16-9' ) ) )['mobile'] );
		// Only on a phone.
		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => array( 9.0, 16.0 ) ), WP_Swiper_Settings::slide_ratios( $this->settings( array( 'slide_ratio_mobile' => '9-16' ) ) ) );
	}

	public function test_slide_ratios_only_for_a_horizontal_row_of_slides() {
		$on  = array( 'slide_ratio' => '1-1' );
		$off = array(
			'fade'             => array( 'effect' => 'fade' ),
			'vertical'         => array( 'direction' => 'vertical' ),
			'width from image' => array( 'auto_width' => true ),
			'wider active'     => array( 'expand_active' => true ),
			'background'       => array( 'background' => true ),
		);
		foreach ( $off as $name => $values ) {
			$this->assertFalse( WP_Swiper_Settings::slide_ratios( $this->settings( $values + $on ) ), $name );
		}
		// The continuous scroll keeps them.
		$this->assertNotFalse( WP_Swiper_Settings::slide_ratios( $this->settings( array( 'marquee' => true ) + $on ) ) );
	}

	public function test_sanitize_slide_ratio_and_distribution() {
		$clean = WP_Swiper_Settings::sanitize( array( 'slide_ratio' => '5-4', 'slide_ratio_tablet' => 'off', 'slide_ratio_mobile' => '', 'slide_ratio_custom' => '21 : 9', 'slide_distribution' => 'evenly' ) );
		$this->assertSame( 'off', $clean['slide_ratio'] );
		$this->assertSame( 'off', $clean['slide_ratio_tablet'] );
		$this->assertSame( '', $clean['slide_ratio_mobile'] );
		$this->assertSame( '21x9', $clean['slide_ratio_custom'] );
		$this->assertSame( 'between', $clean['slide_distribution'] );
		$this->assertSame( 'around', WP_Swiper_Settings::sanitize( array( 'slide_distribution' => 'around' ) )['slide_distribution'] );
	}

	/* Collage order -------------------------------------------------- */

	public function test_sanitize_order() {
		$this->assertSame( '12,7,30', WP_Swiper_Settings::sanitize_order( ' 12, 7,30 ' ) );
		$this->assertSame( '12,7', WP_Swiper_Settings::sanitize_order( '12,x,-7,0,12,7' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_order( 'abc' ) );
		$this->assertSame( 500, count( explode( ',', WP_Swiper_Settings::sanitize_order( implode( ',', range( 1, 600 ) ) ) ) ) );

		$clean = WP_Swiper_Settings::sanitize( array( 'collage_order' => '3, 2,1', 'collage_order_tablet' => '', 'collage_order_mobile' => '5;x' ) );
		$this->assertSame( '3,2,1', $clean['collage_order'] );
		$this->assertSame( '', $clean['collage_order_tablet'] );
		$this->assertSame( '5', $clean['collage_order_mobile'] );
	}

	public function test_collage_orders_per_device_by_photo() {
		$slides = $this->slides( 4 );
		$ids    = wp_list_pluck( $slides, 'id' );

		$this->assertSame( array( 'desktop' => false, 'tablet' => false, 'mobile' => false ), WP_Swiper_Settings::collage_orders( $this->settings(), $slides ) );

		$orders = WP_Swiper_Settings::collage_orders(
			$this->settings(
				array(
					// A photo no longer in the slider is left out.
					'collage_order'        => $ids[2] . ',' . $ids[0] . ',999999',
					'collage_order_mobile' => $ids[3] . ',' . $ids[1],
				)
			),
			$slides
		);
		$this->assertSame( array( 2, 0 ), $orders['desktop'] );
		// Tablet: the desktop's.
		$this->assertSame( array( 2, 0 ), $orders['tablet'] );
		$this->assertSame( array( 3, 1 ), $orders['mobile'] );

		// The same photo twice in the slides: its first slide.
		$twice = array( $slides[0], $slides[1], $slides[0] );
		$this->assertSame( array( 0, 1 ), WP_Swiper_Settings::collage_orders( $this->settings( array( 'collage_order' => $ids[0] . ',' . $ids[1] ) ), $twice )['desktop'] );
	}

	/**
	 * @dataProvider mosaics
	 */
	public function test_sanitize_mosaic( $input, $expected ) {
		$this->assertSame( $expected, WP_Swiper_Settings::sanitize_mosaic( $input ) );
	}

	public function mosaics() {
		return array(
			'kept'              => array( 'V0.6[0,H0.5[1,2]]', 'V0.6[0,H0.5[1,2]]' ),
			'spaces, zeros'     => array( ' V0.60000[ 0 , H.5[1,2] ]', 'V0.6[0,H0.5[1,2]]' ),
			'four decimals'     => array( 'H0.3333333[1,0]', 'H0.3333[1,0]' ),
			'one place'         => array( '0', '0' ),
			'a place twice'     => array( 'V0.6[0,0]', '' ),
			'a place missing'   => array( 'V0.6[0,2]', '' ),
			'share too big'     => array( 'V0.99[0,1]', '' ),
			'share too small'   => array( 'V0.01[0,1]', '' ),
			'unknown cut'       => array( 'X0.5[0,1]', '' ),
			'trailing text'     => array( 'V0.5[0,1]x', '' ),
			'leading zero'      => array( 'V0.5[01,1]', '' ),
			'markup'            => array( 'V0.5[0,<b>1</b>]', '' ),
			'empty'             => array( '', '' ),
		);
	}

	public function test_sanitize_template_cuts() {
		$this->assertSame( array( 'feature-left', 'feature-center', 'mosaic', 'banner' ), WP_Swiper_Settings::COLLAGE_TEMPLATES );
		$this->assertSame( 'feature-left:V0.7[0,H0.5[1,2]]', WP_Swiper_Settings::sanitize_template_cuts( 'feature-left:V0.7[0,H0.5[1,2]]' ) );
		$this->assertSame( 'banner:H0.6[0,V0.3333[1,V0.5[2,3]]]', WP_Swiper_Settings::sanitize_template_cuts( ' banner:H0.6[0, V0.33333[1,V0.5[2,3]]]' ) );
		// Not a template, not cuts, or no template at all: none.
		foreach ( array( '', 'random:V0.5[0,1]', 'grid:V0.5[0,1]', 'feature-left:', 'feature-left:bad', 'V0.5[0,1]', 'feature-left:V0.5[0,0]' ) as $bad ) {
			$this->assertSame( '', WP_Swiper_Settings::sanitize_template_cuts( $bad ), $bad );
		}
	}

	public function test_sanitize_keeps_template_cuts_per_device() {
		$clean = WP_Swiper_Settings::sanitize( array( 'collage_cuts' => 'mosaic:H0.5[V0.6[0,1],V0.3[2,V0.5[3,4]]]', 'collage_cuts_tablet' => 'random:V0.5[0,1]', 'collage_cuts_mobile' => '' ) );
		$this->assertSame( 'mosaic:H0.5[V0.6[0,1],V0.3[2,V0.5[3,4]]]', $clean['collage_cuts'] );
		$this->assertSame( '', $clean['collage_cuts_tablet'] );
		$this->assertSame( '', $clean['collage_cuts_mobile'] );
	}

	public function test_sanitize_scatter() {
		$this->assertSame( 'scatter', WP_Swiper_Settings::COLLAGE_SCATTER );
		$this->assertArrayHasKey( 'scatter', WP_Swiper_Settings::fields()['collage_layout']['choices'] );
		// Sizes to four decimals, no trailing zeros, spaces dropped.
		$this->assertSame( '1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8,o0.5x0.9;B0.9x0.5;L0.8333x0.5', WP_Swiper_Settings::sanitize_scatter( '1.20x1.6; T0.9x0.6;R1.0x0.7,a0.8x0.8,o0.5x0.9;B0.9x0.5;L0.83333x0.5' ) );
		// The middle photo alone; some arms empty.
		$this->assertSame( '1x1', WP_Swiper_Settings::sanitize_scatter( '1x1' ) );
		$this->assertSame( '1x1;R2x1;L1x2,o1x1', WP_Swiper_Settings::sanitize_scatter( '1x1;R2x1;L1x2,o1x1' ) );
		foreach (
			array(
				'',
				'1x',
				'x1',
				'1x1;',
				'1x1;T',
				'1x1;X1x1',
				'1x1;R1x1;T1x1', // Out of order.
				'1x1;T1x1;T1x1', // Twice.
				'1x1;T1x1,1x1', // A step without its way.
				'1x1;T1x1,b1x1',
				'0.01x1',
				'1x21',
				'V0.5[0,1]',
			) as $bad
		) {
			$this->assertSame( '', WP_Swiper_Settings::sanitize_scatter( $bad ), $bad );
		}
		// At most 100 places.
		$this->assertSame( '', WP_Swiper_Settings::sanitize_scatter( '1x1;T' . implode( ',a', array_fill( 0, 100, '1x1' ) ) ) );
		$this->assertNotSame( '', WP_Swiper_Settings::sanitize_scatter( '1x1;T' . implode( ',a', array_fill( 0, 99, '1x1' ) ) ) );
	}

	public function test_sanitize_template_cuts_keeps_scattered() {
		$this->assertSame( 'scatter:1.2x1.6;R1x0.7', WP_Swiper_Settings::sanitize_template_cuts( 'scatter:1.20x1.6;R1x0.7' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_template_cuts( 'scatter:V0.5[0,1]' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_template_cuts( 'feature-left:1x1;R1x1' ) );

		$clean = WP_Swiper_Settings::sanitize( array( 'collage_layout' => 'scatter', 'collage_cuts' => 'scatter:1x1;B2x1', 'collage_cuts_mobile' => 'scatter:bad' ) );
		$this->assertSame( 'scatter', $clean['collage_layout'] );
		$this->assertSame( 'scatter:1x1;B2x1', $clean['collage_cuts'] );
		$this->assertSame( '', $clean['collage_cuts_mobile'] );
	}

	public function test_sanitize_keeps_a_mosaic_per_device() {
		$clean = WP_Swiper_Settings::sanitize( array( 'collage_mosaic' => 'V0.5[0,1]', 'collage_mosaic_tablet' => '', 'collage_mosaic_mobile' => 'bad' ) );
		$this->assertSame( 'V0.5[0,1]', $clean['collage_mosaic'] );
		$this->assertSame( '', $clean['collage_mosaic_tablet'] );
		$this->assertSame( '', $clean['collage_mosaic_mobile'] );
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

	public function test_sanitize_position_reads_a_point_in_percent() {
		$this->assertSame( '30 72.5', WP_Swiper_Settings::sanitize_position( '30 72.5' ) );
		$this->assertSame( '0 100', WP_Swiper_Settings::sanitize_position( ' 0   100 ' ) );
		// One decimal, within 0–100, "30.0" as "30".
		$this->assertSame( '33.3 100', WP_Swiper_Settings::sanitize_position( '33.333 250' ) );
		$this->assertSame( '30 50', WP_Swiper_Settings::sanitize_position( '30.04 50.0' ) );
		// Anything else: none.
		foreach ( array( '', '50', '50% 50%', '-5 10', 'top left', '1 2 3', null, array( 1, 2 ) ) as $value ) {
			$this->assertSame( '', WP_Swiper_Settings::sanitize_position( $value ), var_export( $value, true ) );
		}
	}

	public function test_marquee_direction_is_one_of_its_choices() {
		$this->assertSame( 'normal', WP_Swiper_Settings::defaults()['marquee_direction'] );
		$this->assertSame( 'reverse', WP_Swiper_Settings::sanitize( array( 'marquee_direction' => 'reverse' ) )['marquee_direction'] );
		$this->assertSame( 'normal', WP_Swiper_Settings::sanitize( array( 'marquee_direction' => 'sideways' ) )['marquee_direction'] );
	}
}
