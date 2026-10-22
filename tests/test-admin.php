<?php
/**
 * WP_Swiper_Admin and WP_Swiper_Preview: saving (nonce, capability, the
 * slides as posted), the settings screen and the preview's checks.
 */

class Test_WP_Swiper_Admin extends WP_Swiper_TestCase {

	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	private function as_role( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private function post_form( $slides, $settings, $nonce = true ) {
		$_POST = array(
			'wp_swiper_slides'   => $slides,
			'wp_swiper_settings' => $settings,
		);
		if ( $nonce ) {
			$_POST[ WP_Swiper_Admin::NONCE . '_nonce' ] = wp_create_nonce( WP_Swiper_Admin::NONCE );
		}
	}

	private function one_slide( $id ) {
		return array(
			'id'      => array( $id ),
			'embed'   => array( '' ),
			'caption' => array( '' ),
			'link'    => array( '' ),
			'new_tab' => array( '0' ),
			'poster'  => array( '' ),
		);
	}

	public function test_save_needs_the_nonce() {
		$this->as_role( 'editor' );
		$id = $this->slider( array(), array( 'height' => 100 ) );
		$this->post_form( $this->one_slide( $this->image() ), array( 'height' => '700' ), false );

		WP_Swiper_Admin::save( $id );
		$this->assertSame( array(), get_post_meta( $id, WP_Swiper_Settings::META_SLIDES, true ) );
		$this->assertSame( 100, WP_Swiper_Settings::get( $id )['height'] );
	}

	public function test_save_needs_the_capability() {
		$id = $this->slider( array(), array( 'height' => 100 ) );
		$this->as_role( 'subscriber' );
		$this->post_form( $this->one_slide( $this->image() ), array( 'height' => '700' ) );

		WP_Swiper_Admin::save( $id );
		$this->assertSame( 100, WP_Swiper_Settings::get( $id )['height'] );
	}

	public function test_save_stores_sanitized_settings() {
		$this->as_role( 'editor' );
		$id = $this->slider( array() );
		$this->post_form(
			$this->one_slide( $this->image() ),
			array(
				'effect'           => 'fade',
				'height'           => '700',
				'css_class'        => 'a"b',
				'background'       => '1',
				'background_class' => 'hero row',
				'layouts'          => wp_json_encode( array( 'cube' => array( 'height' => '9999' ) ) ),
			)
		);

		WP_Swiper_Admin::save( $id );
		$saved = get_post_meta( $id, WP_Swiper_Settings::META_SETTINGS, true );

		$this->assertSame( 6, $saved['_version'] );
		$this->assertSame( 'fade', $saved['effect'] );
		$this->assertSame( 700, $saved['height'] );
		$this->assertSame( 'ab', $saved['css_class'] );
		$this->assertTrue( $saved['background'] );
		$this->assertSame( 'hero row', $saved['background_class'] );
		$this->assertSame( 2000, $saved['layouts']['cube']['height'] );
		$this->assertSame( 700, $saved['layouts']['fade']['height'] );
	}

	public function test_posted_slides_are_checked_one_by_one() {
		$image  = $this->image();
		$video  = $this->video();
		$poster = $this->image();
		$post   = self::factory()->post->create();
		$embed  = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
		set_transient( 'wp_swiper_oembed_' . md5( $embed ), array(), HOUR_IN_SECONDS );

		$_POST = array(
			'wp_swiper_slides' => array(
				'id'      => array( $image, $video, $post, 0, 0, 999999 ),
				'embed'   => array( '', '', '', $embed, 'https://example.com/not-a-video', '' ),
				'caption' => array( '<b>Hi</b><script>x()</script>', 'Clip', 'x', 'Song', 'y', 'z' ),
				'link'    => array( 'javascript:alert(1)', 'https://example.com', '', 'https://example.com', '', '' ),
				'new_tab' => array( '1', '1', '0', '1', '0', '0' ),
				'poster'  => array( (string) $poster, (string) $poster, '', (string) $image, '', '' ),
			),
		);
		$slides = WP_Swiper_Admin::posted_slides();

		// The post, the unknown link and the missing attachment are dropped.
		$this->assertCount( 3, $slides );

		$this->assertSame( $image, $slides[0]['id'] );
		// The caption keeps its bold (not the script).
		$this->assertSame( '<b>Hi</b>x()', $slides[0]['caption'] );
		$this->assertSame( '', $slides[0]['link'] );
		$this->assertTrue( $slides[0]['new_tab'] );
		// An image has no poster.
		$this->assertSame( 0, $slides[0]['poster'] );

		// A video: no link, its poster kept.
		$this->assertSame( $video, $slides[1]['id'] );
		$this->assertSame( '', $slides[1]['link'] );
		$this->assertFalse( $slides[1]['new_tab'] );
		$this->assertSame( $poster, $slides[1]['poster'] );

		// A YouTube link: no media library item, no link.
		$this->assertSame( 0, $slides[2]['id'] );
		$this->assertSame( $embed, $slides[2]['embed'] );
		$this->assertSame( '', $slides[2]['link'] );
		$this->assertSame( $image, $slides[2]['poster'] );
	}

	public function test_posted_slides_keep_their_description() {
		$image = $this->image();
		$video = $this->video();

		$_POST = array(
			'wp_swiper_slides' => array(
				'id'          => array( $image, $video, $image ),
				'embed'       => array( '', '', '' ),
				'caption'     => array( '', '', '' ),
				'description' => array( '<p>Hi <strong>there</strong><script>x()</script></p>', '<p>&nbsp;</p>', '' ),
				'link'        => array( '', '', '' ),
				'new_tab'     => array( '0', '0', '0' ),
				'poster'      => array( '', '', '' ),
			),
		);
		$this->assertSame( array( '<p>Hi <strong>there</strong>x()</p>', '', '' ), wp_list_pluck( WP_Swiper_Admin::posted_slides(), 'description' ) );

		// Forms from before descriptions: none.
		$_POST = array( 'wp_swiper_slides' => $this->one_slide( $image ) );
		$this->assertSame( '', WP_Swiper_Admin::posted_slides()[0]['description'] );
	}

	public function test_slides_box_keeps_each_slide_in_hidden_fields_and_one_window() {
		$this->as_role( 'editor' );
		$slides                   = $this->slides( 2 );
		$slides[0]['caption']     = '<strong>Big</strong> title';
		$slides[0]['description'] = '<p>A <em>long</em> text</p>';
		$slides[0]['link']        = 'https://example.com/page';
		$slides[0]['new_tab']     = true;
		$id                       = $this->slider( $slides );

		ob_start();
		WP_Swiper_Admin::render_slides_box( get_post( $id ) );
		$html = ob_get_clean();

		// Two slides and the template: hidden fields each, the first one filled.
		foreach ( array( 'caption', 'description' ) as $name ) {
			$this->assertSame( 3, substr_count( $html, '<textarea name="wp_swiper_slides[' . $name . '][]" hidden>' ), $name );
		}
		$this->assertStringContainsString( '&lt;strong&gt;Big&lt;/strong&gt; title</textarea>', $html );
		$this->assertStringContainsString( '&lt;p&gt;A &lt;em&gt;long&lt;/em&gt; text&lt;/p&gt;</textarea>', $html );
		$this->assertStringContainsString( '<input type="hidden" name="wp_swiper_slides[link][]" value="https://example.com/page">', $html );
		$this->assertStringContainsString( '<input type="hidden" name="wp_swiper_slides[new_tab][]" value="1">', $html );
		// No visible field in the slides: a button each, and marks of what they have.
		$this->assertStringNotContainsString( 'type="url" name=', $html );
		$this->assertSame( 3, substr_count( $html, 'class="button button-small wp-swiper-slide__edit"' ) );
		// "Edit info" for the slide with some, "Add info" for the other one and the template.
		$this->assertSame( 1, substr_count( $html, 'wp-swiper-slide__edit">Edit info<' ) );
		$this->assertSame( 2, substr_count( $html, 'wp-swiper-slide__edit">Add info<' ) );
		foreach ( array( 'caption', 'description', 'link' ) as $mark ) {
			$this->assertSame( 1, substr_count( $html, 'data-mark="' . $mark . '">' ), $mark );
		}
		// One window for all, its fields not sent with the form.
		$this->assertSame( 1, substr_count( $html, 'id="wp-swiper-slide-modal"' ) );
		$this->assertStringContainsString( '<textarea id="wp-swiper-caption-editor" rows="3"></textarea>', $html );
		$this->assertStringContainsString( '<textarea id="wp-swiper-description-editor" rows="10"></textarea>', $html );
		$this->assertStringContainsString( '<input type="url" id="wp-swiper-link-input"', $html );
		$this->assertStringContainsString( '<input type="checkbox" id="wp-swiper-new-tab-input">', $html );
	}

	public function test_a_video_slide_shows_no_link_mark() {
		$this->as_role( 'editor' );
		$id = $this->slider(
			array(
				array(
					'id'      => $this->video(),
					'caption' => '',
					'link'    => 'https://example.com',
					'new_tab' => false,
					'poster'  => 0,
				),
			)
		);

		ob_start();
		WP_Swiper_Admin::render_slides_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'data-mark="link" hidden>', $html );
		// Its link is not used: nothing to edit there.
		$this->assertStringContainsString( 'wp-swiper-slide__edit">Add info<', $html );
	}

	public function test_settings_screen_has_every_field_once() {
		$this->as_role( 'editor' );
		$id = $this->slider();

		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();

		foreach ( WP_Swiper_Settings::fields() as $key => $field ) {
			if ( ! empty( $field['inline_in'] ) ) {
				continue;
			}
			$this->assertSame( 1, substr_count( $html, 'data-field="' . $key . '"' ), $key );
		}
		foreach ( array_keys( WP_Swiper_Settings::pages() ) as $page ) {
			$this->assertStringContainsString( 'id="wp-swiper-page-' . $page . '"', $html );
		}
		// Blocks: their titles and fields, in their order.
		foreach ( WP_Swiper_Settings::blocks() as $page => $blocks ) {
			$this->assertSame( 1, preg_match( '#<fieldset[^>]*id="wp-swiper-page-' . $page . '"[^>]*>(.*?)</fieldset>#s', $html, $match ), $page );
			$last = -1;
			foreach ( $blocks as $name => $block ) {
				$at = strpos( $match[1], 'data-block="' . $name . '"' );
				$this->assertNotFalse( $at, "$page/$name" );
				$this->assertGreaterThan( $last, $at, "$page/$name in order" );
				$this->assertStringContainsString( '<h4 class="wp-swiper-subheading">' . esc_html( $block['title'] ) . '</h4>', $match[1] );
				$last = $at;
			}
		}
		// The effects a field belongs to, for admin.js.
		$this->assertStringContainsString( 'data-field="background" data-effects="slide fade collage"', $html );
		// Description: its two notes (no description yet, or none shown).
		$this->assertStringContainsString( 'data-note="empty"', $html );
		$this->assertStringContainsString( 'data-note="unavailable"', $html );
		$this->assertStringContainsString( 'id="wp-swiper-layouts"', $html );
		// The demos link: in the left column (before the pages), in a new tab.
		$demos = strpos( $html, '<p class="wp-swiper-demos">' );
		$this->assertNotFalse( $demos );
		$this->assertLessThan( strpos( $html, 'wp-swiper-settings__pane' ), $demos );
		$this->assertStringContainsString( '<a href="https://swiperjs.com/demos" target="_blank" rel="noopener noreferrer">', $html );
	}

	public function test_list_columns() {
		$id = $this->slider( $this->slides( 2 ), array( 'effect' => 'cube' ) );

		ob_start();
		WP_Swiper_Admin::column_content( 'wp_swiper_slides', $id );
		WP_Swiper_Admin::column_content( 'wp_swiper_effect', $id );
		WP_Swiper_Admin::column_content( 'wp_swiper_shortcode', $id );
		$html = ob_get_clean();

		$this->assertStringStartsWith( '2Cube', $html );
		$this->assertStringContainsString( '[wp_swiper id=&quot;' . $id . '&quot;]', $html );
	}

	public function test_preview_refuses_a_bad_nonce() {
		$this->as_role( 'editor' );
		$id    = $this->slider();
		$_POST = array(
			'post_id'  => (string) $id,
			'_wpnonce' => 'wrong',
		);

		$this->expectException( 'WPDieException' );
		WP_Swiper_Preview::render_frame();
	}

	public function test_preview_refuses_those_who_cannot_edit() {
		$id = $this->slider();
		$this->as_role( 'subscriber' );
		$_POST = array(
			'post_id'  => (string) $id,
			'_wpnonce' => wp_create_nonce( WP_Swiper_Preview::ACTION . '_' . $id ),
		);

		$this->expectException( 'WPDieException' );
		WP_Swiper_Preview::render_frame();
	}

	public function test_preview_refuses_other_posts() {
		$this->as_role( 'administrator' );
		$id    = self::factory()->post->create();
		$_POST = array(
			'post_id'  => (string) $id,
			'_wpnonce' => wp_create_nonce( WP_Swiper_Preview::ACTION . '_' . $id ),
		);

		$this->expectException( 'WPDieException' );
		WP_Swiper_Preview::render_frame();
	}

	public function test_post_type() {
		$type = get_post_type_object( WP_Swiper_Settings::POST_TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertTrue( $type->show_ui );
		$this->assertSame( 'post', $type->capability_type );
	}

	public function test_wpbakery_element() {
		$id       = $this->slider( null, array(), array( 'post_title' => 'Home hero' ) );
		$settings = WP_Swiper_WPBakery::settings();

		$this->assertSame( 'wp_swiper', $settings['base'] );
		$this->assertSame( (string) $id, $settings['params'][0]['value'][ 'Home hero (#' . $id . ')' ] );
		// The slider choice only: no class field.
		$this->assertSame( array( 'id' ), wp_list_pluck( $settings['params'], 'param_name' ) );
	}

	public function test_wpbakery_element_keeps_a_class_given_before() {
		$id = $this->slider();
		$this->assertContains( 'old-class', $this->root_classes( do_shortcode( '[wp_swiper id="' . $id . '" class="old-class"]' ) ) );
	}
}
