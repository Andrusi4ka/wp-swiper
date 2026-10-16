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
				'caption' => array( '<b>Hi</b>', 'Clip', 'x', 'Song', 'y', 'z' ),
				'link'    => array( 'javascript:alert(1)', 'https://example.com', '', 'https://example.com', '', '' ),
				'new_tab' => array( '1', '1', '0', '1', '0', '0' ),
				'poster'  => array( (string) $poster, (string) $poster, '', (string) $image, '', '' ),
			),
		);
		$slides = WP_Swiper_Admin::posted_slides();

		// The post, the unknown link and the missing attachment are dropped.
		$this->assertCount( 3, $slides );

		$this->assertSame( $image, $slides[0]['id'] );
		$this->assertSame( 'Hi', $slides[0]['caption'] );
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
		$this->assertStringContainsString( 'id="wp-swiper-layouts"', $html );
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
	}
}
