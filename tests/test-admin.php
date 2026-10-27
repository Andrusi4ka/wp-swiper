<?php
/**
 * WP_Swiper_Admin and WP_Swiper_Preview: saving (nonce, capability, the
 * slides as posted), the settings screen, the preview's checks and cloning.
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

	public function test_posted_slides_keep_their_font_sizes() {
		$image = $this->image();
		$_POST = array(
			'wp_swiper_slides' => $this->one_slide( $image ) + array(
				'caption_size'     => array( '24,,16' ),
				'description_size' => array( ',18,' ),
			),
		);
		$slide = WP_Swiper_Admin::posted_slides()[0];
		$this->assertSame( '24,,16', $slide['caption_size'] );
		// No desktop size: the plugin's there, the tablet one kept.
		$this->assertSame( ',18,', $slide['description_size'] );

		// Forms from before: none.
		$_POST = array( 'wp_swiper_slides' => $this->one_slide( $image ) );
		$this->assertSame( '', WP_Swiper_Admin::posted_slides()[0]['caption_size'] );
		$this->assertSame( '', WP_Swiper_Admin::posted_slides()[0]['caption_weight'] );
	}

	public function test_posted_slides_keep_their_font_weights() {
		$_POST = array(
			'wp_swiper_slides' => $this->one_slide( $this->image() ) + array(
				'caption_weight'     => array( '700,,400' ),
				'description_weight' => array( '650,300,' ),
			),
		);
		$slide = WP_Swiper_Admin::posted_slides()[0];
		// One weight for every screen: an old per-device one keeps its desktop weight.
		$this->assertSame( '700', $slide['caption_weight'] );
		// Not a weight: none.
		$this->assertSame( '', $slide['description_weight'] );
	}

	public function test_posted_slides_keep_their_colors() {
		$_POST = array(
			'wp_swiper_slides' => $this->one_slide( $this->image() ) + array(
				'caption_color'     => array( 'rgba(0, 0, 0, 0.5)' ),
				'description_color' => array( 'red;position:fixed' ),
			),
		);
		$slide = WP_Swiper_Admin::posted_slides()[0];
		$this->assertSame( 'rgba(0,0,0,0.5)', $slide['caption_color'] );
		// Not a color: none (the Description page's).
		$this->assertSame( '', $slide['description_color'] );

		// Forms from before: none.
		$_POST = array( 'wp_swiper_slides' => $this->one_slide( $this->image() ) );
		$this->assertSame( '', WP_Swiper_Admin::posted_slides()[0]['caption_color'] );
	}

	public function test_slide_info_window_has_a_color_under_each_editor() {
		$this->as_role( 'editor' );
		$slides                     = $this->slides( 1 );
		$slides[0]['caption_color'] = 'rgba(255,0,0,0.25)';
		$id                         = $this->slider( $slides );

		ob_start();
		WP_Swiper_Admin::render_slides_box( get_post( $id ) );
		$html = ob_get_clean();

		// After each editor's font rows: Default / Custom, a swatch, the picker.
		$caption     = strpos( $html, 'data-prop="caption_weight"' );
		$description = strpos( $html, 'id="wp-swiper-description-editor"' );
		$this->assertGreaterThan( $caption, strpos( $html, 'data-prop="caption_color"' ) );
		$this->assertLessThan( $description, strpos( $html, 'data-prop="caption_color"' ) );
		$this->assertGreaterThan( strpos( $html, 'data-prop="description_weight"' ), strpos( $html, 'data-prop="description_color"' ) );
		$this->assertSame( 2, substr_count( $html, 'class="wp-swiper-modal__color-mode"' ) );
		$this->assertSame( 2, substr_count( $html, 'class="wp-swiper-color-pick__hex"' ) );
		$this->assertSame( 2, substr_count( $html, '<input type="range" min="0" max="100" step="1" value="100">' ) );
		// The slide's own values kept with it.
		$this->assertStringContainsString( '<input type="hidden" name="wp_swiper_slides[caption_color][]" value="rgba(255,0,0,0.25)">', $html );
		$this->assertStringContainsString( '<input type="hidden" name="wp_swiper_slides[description_color][]" value="">', $html );
	}

	public function test_slide_info_window_text_settings_in_a_row_and_a_replace_button() {
		$this->as_role( 'editor' );
		$id = $this->slider( $this->slides( 1 ) );

		ob_start();
		WP_Swiper_Admin::render_slides_box( get_post( $id ) );
		$html = ob_get_clean();

		// Size, weight and color side by side under each editor, a label over each field.
		$this->assertSame( 2, preg_match_all( '#<div class="wp-swiper-modal__text-style">\s*<div class="wp-swiper-modal__size wp-swiper-modal__size--devices" data-prop="(caption|description)_size">#', $html ) );
		$this->assertSame( 6, substr_count( $html, '<div class="wp-swiper-modal__size-head">' ) );
		// No explanations under the headings.
		$this->assertStringNotContainsString( 'Over the image, styled on the Caption page', $html );
		$this->assertStringNotContainsString( 'Shown under the slider while this slide is the current one', $html );
		// The thumbnail: just the picture (no icon on hover, no click).
		$this->assertStringContainsString( '<span class="wp-swiper-modal__thumb" aria-hidden="true"><span class="wp-swiper-modal__thumb-media"></span></span>', $html );
		$this->assertStringNotContainsString( 'wp-swiper-modal__thumb-replace', $html );
		// The Replace photo button under the title replaces the media.
		$this->assertMatchesRegularExpression( '#<button type="button" class="button button-small wp-swiper-modal__replace">\s*<span class="dashicons dashicons-update" aria-hidden="true"></span>\s*Replace photo\s*</button>#', $html );
	}

	public function test_slide_info_window_has_a_font_size_and_weight_under_each_editor() {
		$this->as_role( 'editor' );
		$slides                      = $this->slides( 1 );
		$slides[0]['caption_size']   = '22,,14';
		$slides[0]['caption_weight'] = '600,,';
		$id                          = $this->slider( $slides, array( 'description_font' => 'custom', 'description_font_size' => 19, 'caption_weight' => '300' ) );

		ob_start();
		WP_Swiper_Admin::render_slides_box( get_post( $id ) );
		$html = ob_get_clean();

		// After each editor, its two rows; for each device a value.
		$caption     = strpos( $html, 'id="wp-swiper-caption-editor"' );
		$description = strpos( $html, 'id="wp-swiper-description-editor"' );
		foreach ( array( 'caption_size', 'caption_weight' ) as $prop ) {
			$this->assertGreaterThan( $caption, strpos( $html, 'data-prop="' . $prop . '"' ), $prop );
			$this->assertLessThan( $description, strpos( $html, 'data-prop="' . $prop . '"' ), $prop );
		}
		foreach ( array( 'description_size', 'description_weight' ) as $prop ) {
			$this->assertGreaterThan( $description, strpos( $html, 'data-prop="' . $prop . '"' ), $prop );
		}
		// Sizes per device; weights one plain list each (Default, 100–900), no devices.
		$this->assertSame( 6, substr_count( $html, 'data-device=' ) - substr_count( $html, 'wp-swiper-devices__' ) );
		$this->assertSame( 2, substr_count( $html, 'class="wp-swiper-modal__weight"' ) );
		// A size: device icons switching one field (tablet and mobile hidden at first).
		$this->assertSame( 2, substr_count( $html, 'class="wp-swiper-modal__size wp-swiper-modal__size--devices"' ) );
		$this->assertSame( 6, substr_count( $html, '<button type="button" class="wp-swiper-devices__btn' ) - substr_count( $html, 'wp-swiper-field--responsive' ) * 3 );
		$this->assertSame( 4, preg_match_all( '#class="wp-swiper-modal__size-input" data-device="(tablet|mobile)"\s+hidden#', $html ) );
		$this->assertStringNotContainsString( 'wp-swiper-modal__size-mode', $html );
		$this->assertSame( 0, substr_count( $html, '<option value="">Inherit</option>' ) );
		// The slide's own values kept with it.
		$this->assertStringContainsString( '<input type="hidden" name="wp_swiper_slides[caption_size][]" value="22,,14">', $html );
		$this->assertStringContainsString( '<input type="hidden" name="wp_swiper_slides[description_size][]" value="">', $html );
		$this->assertStringContainsString( '<input type="hidden" name="wp_swiper_slides[caption_weight][]" value="600">', $html );
		// The slider's own size and weight from before (admin.js moves them into the slides without theirs).
		$this->assertStringContainsString( 'data-legacy-caption-size="" data-legacy-description-size="19,," data-legacy-caption-weight="300" data-legacy-description-weight=""', $html );
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
		// "Edit" on each, whatever the slide has.
		$this->assertSame( 3, substr_count( $html, 'wp-swiper-slide__edit">Edit<' ) );
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
		// The same button as any slide.
		$this->assertStringContainsString( 'wp-swiper-slide__edit">Edit<', $html );
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

	public function test_preview_has_the_developer_mode_button_right_of_its_title() {
		$this->as_role( 'editor' );

		ob_start();
		WP_Swiper_Preview::render_panel( get_post( $this->slider() ) );
		$html = ob_get_clean();

		$title  = strpos( $html, 'wp-swiper-preview__title' );
		$button = strpos( $html, '<button type="button" class="button wp-swiper-preview__dev" aria-pressed="false"' );
		$this->assertNotFalse( $button );
		$this->assertGreaterThan( $title, $button );
		// Before the device switch and Update.
		$this->assertLessThan( strpos( $html, 'wp-swiper-preview__update' ), $button );
		$this->assertLessThan( strpos( $html, 'wp-swiper-preview__devices' ), $button );
		// The collage's hint, hidden until admin.js sees that effect.
		$this->assertStringContainsString( '<p class="description wp-swiper-preview__hint" data-effect="collage" hidden>', $html );
		$this->assertStringContainsString( '<p class="description wp-swiper-preview__hint" data-effect="mosaic" hidden>', $html );
	}

	public function test_collage_order_field_per_device_with_shuffle_and_reset() {
		$this->as_role( 'editor' );
		$id = $this->slider( null, array( 'effect' => 'collage', 'collage_order' => '5,4', 'collage_seed' => 77 ) );

		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertSame( 1, preg_match( '#<div class="wp-swiper-field wp-swiper-field--responsive" data-field="collage_order".*?wp-swiper-order-reset.*?</div>#s', $html, $match ) );
		$field = $match[0];
		foreach ( array( 'collage_order' => '5,4', 'collage_order_tablet' => '', 'collage_order_mobile' => '' ) as $key => $value ) {
			$this->assertMatchesRegularExpression( '#type="hidden"[^>]*name="wp_swiper_settings\[' . $key . '\]"\s+value="' . preg_quote( $value, '#' ) . '"#', $field, $key );
		}
		$this->assertStringContainsString( 'wp-swiper-shuffle', $field );
		// The shuffle number is kept inside it, not as a field of its own.
		$this->assertStringContainsString( '<input type="hidden" id="wp-swiper-collage_seed" name="wp_swiper_settings[collage_seed]" value="77">', $html );
		$this->assertStringNotContainsString( 'data-field="collage_seed"', $html );

		// The mosaic sizes: kept inside the Order field (its Reset clears them
		// too), one value per device, no field of their own.
		$this->assertStringNotContainsString( 'data-field="collage_mosaic"', $html );
		foreach ( array( 'collage_mosaic' => 'desktop', 'collage_mosaic_tablet' => 'tablet', 'collage_mosaic_mobile' => 'mobile' ) as $key => $device ) {
			$this->assertStringContainsString( '<input type="hidden" id="wp-swiper-' . $key . '" name="wp_swiper_settings[' . $key . ']" value="" class="wp-swiper-devices__input" data-device="' . $device . '">', $field, $key );
		}
		// A template's sizes too, apart from the mosaic's.
		$this->assertStringNotContainsString( 'data-field="collage_cuts"', $html );
		foreach ( array( 'collage_cuts' => 'desktop', 'collage_cuts_tablet' => 'tablet', 'collage_cuts_mobile' => 'mobile' ) as $key => $device ) {
			$this->assertStringContainsString( '<input type="hidden" id="wp-swiper-' . $key . '" name="wp_swiper_settings[' . $key . ']" value="" class="wp-swiper-devices__input" data-device="' . $device . '">', $field, $key );
		}
		// Whether a device's order or sizes were set by hand (admin.js greys Shuffle then).
		foreach ( array( 'collage_manual', 'collage_manual_tablet', 'collage_manual_mobile' ) as $key ) {
			$this->assertStringContainsString( 'id="wp-swiper-' . $key . '" name="wp_swiper_settings[' . $key . ']"', $field, $key );
		}
		$this->assertStringNotContainsString( 'data-field="collage_manual"', $html );
	}

	public function test_collage_manual_is_saved_per_device() {
		$clean = WP_Swiper_Settings::sanitize( array( 'collage_manual' => '1', 'collage_manual_tablet' => '', 'collage_manual_mobile' => '7' ) );
		$this->assertSame( 1, $clean['collage_manual'] );
		$this->assertSame( '', $clean['collage_manual_tablet'] );
		$this->assertSame( 1, $clean['collage_manual_mobile'] );
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
		// The slider choice and, under it, its Edit button: no class field.
		$this->assertSame( array( 'id', WP_Swiper_WPBakery::EDIT_PARAM ), wp_list_pluck( $settings['params'], 'param_name' ) );
		$this->assertSame( WP_Swiper_WPBakery::EDIT_PARAM, $settings['params'][1]['type'] );
	}

	public function test_wpbakery_edit_button() {
		$html = WP_Swiper_WPBakery::edit_param( array( 'param_name' => WP_Swiper_WPBakery::EDIT_PARAM ), '' );

		// wpbakery.js adds the chosen slider's ID to this address.
		$this->assertStringContainsString( 'data-url="' . esc_url( admin_url( 'post.php?action=edit&post=' ) ) . '"', $html );
		// A new tab; hidden until a slider is chosen.
		$this->assertStringContainsString( 'target="_blank" rel="noopener" hidden>', $html );
		// Its field is empty: WPBakery adds nothing to the shortcode.
		$this->assertStringContainsString( 'name="' . WP_Swiper_WPBakery::EDIT_PARAM . '" class="wpb_vc_param_value', $html );
		$this->assertStringContainsString( 'value=""', $html );
	}

	public function test_wpbakery_element_keeps_a_class_given_before() {
		$id = $this->slider();
		$this->assertContains( 'old-class', $this->root_classes( do_shortcode( '[wp_swiper id="' . $id . '" class="old-class"]' ) ) );
	}

	public function test_image_field_shows_the_chosen_image() {
		$this->as_role( 'editor' );
		$image = $this->image();
		$id    = $this->slider( null, array( 'marquee' => true, 'marquee_separator' => 'image', 'marquee_separator_image' => $image ) );

		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="wp_swiper_settings[marquee_separator_image]" value="' . $image . '"', $html );
		$this->assertStringContainsString( '<span class="wp-swiper-picker__thumb"><img src="', $html );
		$this->assertStringContainsString( '<button type="button" class="button-link wp-swiper-picker__remove">', $html );

		// None chosen: no thumbnail, Remove hidden.
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider() ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="wp_swiper_settings[marquee_separator_image]" value=""', $html );
		$this->assertStringContainsString( '<span class="wp-swiper-picker__thumb"></span>', $html );
		$this->assertStringContainsString( 'wp-swiper-picker__remove" hidden>', $html );
	}

	public function test_clone_copies_the_slides_and_settings_as_a_draft() {
		$this->as_role( 'editor' );
		$slides = array(
			array(
				'id'          => $this->image(),
				'caption'     => 'A \ backslash',
				'description' => '',
				'link'        => '',
				'new_tab'     => false,
				'poster'      => 0,
			),
		);
		$id = $this->slider( array(), array( 'height' => 420 ), array( 'post_title' => 'Brands' ) );
		update_post_meta( $id, WP_Swiper_Settings::META_SLIDES, wp_slash( $slides ) );

		$copy = WP_Swiper_Admin::clone_slider( $id );
		$this->assertGreaterThan( $id, $copy );
		$this->assertSame( 'Brands (copy)', get_post( $copy )->post_title );
		$this->assertSame( 'draft', get_post( $copy )->post_status );
		$this->assertSame( $slides, get_post_meta( $copy, WP_Swiper_Settings::META_SLIDES, true ) );
		$this->assertSame( 420, WP_Swiper_Settings::get( $copy )['height'] );
		// The original is left as it was.
		$this->assertSame( 'publish', get_post( $id )->post_status );
	}

	public function test_clone_needs_the_capability() {
		$id = $this->slider();
		$this->as_role( 'subscriber' );

		$this->assertSame( 0, WP_Swiper_Admin::clone_slider( $id ) );
	}

	public function test_clone_only_sliders_not_in_the_trash() {
		$this->as_role( 'editor' );
		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$trashed = $this->slider( null, array(), array( 'post_status' => 'trash' ) );

		$this->assertSame( 0, WP_Swiper_Admin::clone_slider( $page ) );
		$this->assertSame( 0, WP_Swiper_Admin::clone_slider( $trashed ) );
	}

	public function test_clone_link_after_quick_edit() {
		$this->as_role( 'editor' );
		$post    = get_post( $this->slider() );
		$actions = WP_Swiper_Admin::row_actions(
			array(
				'edit'                 => 'Edit',
				'inline hide-if-no-js' => 'Quick Edit',
				'trash'                => 'Trash',
			),
			$post
		);

		$this->assertSame( array( 'edit', 'inline hide-if-no-js', 'wp_swiper_clone', 'trash' ), array_keys( $actions ) );
		$this->assertStringContainsString( 'action=wp_swiper_clone', $actions['wp_swiper_clone'] );
		$this->assertStringContainsString( '_wpnonce=', $actions['wp_swiper_clone'] );

		// Other post types keep their links.
		$page = get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		$this->assertSame( array( 'edit' => 'Edit' ), WP_Swiper_Admin::row_actions( array( 'edit' => 'Edit' ), $page ) );
	}

	public function test_posted_slides_keep_an_images_position() {
		$image = $this->image();
		$video = $this->video();

		$_POST = array(
			'wp_swiper_slides' => array(
				'id'       => array( $image, $image, $video ),
				'embed'    => array( '', '', '' ),
				'caption'  => array( '', '', '' ),
				'link'     => array( '', '', '' ),
				'new_tab'  => array( '0', '0', '0' ),
				'poster'   => array( '', '', '' ),
				'position' => array( '25.55 80', 'top', '10 10' ),
			),
		);
		// Sanitized; a video's is not kept (its picture is not a cropped image here).
		$this->assertSame( array( '25.6 80', '', '' ), wp_list_pluck( WP_Swiper_Admin::posted_slides(), 'position' ) );

		// Forms from before positions: none.
		$_POST = array( 'wp_swiper_slides' => $this->one_slide( $image ) );
		$this->assertSame( '', WP_Swiper_Admin::posted_slides()[0]['position'] );
	}

	public function test_slides_box_keeps_the_position_and_shows_it() {
		$this->as_role( 'editor' );
		$slides                = $this->slides( 2 );
		$slides[0]['position'] = '20 75';
		$id                    = $this->slider( $slides );

		ob_start();
		WP_Swiper_Admin::render_slides_box( get_post( $id ) );
		$html = ob_get_clean();

		// Two slides and the template: a field and a button each; the window once.
		$this->assertSame( 3, substr_count( $html, 'name="wp_swiper_slides[position][]"' ) );
		$this->assertSame( 3, preg_match_all( '/class="wp-swiper-slide__position( is-set)?"/', $html ) );
		// Its icon inline (it takes the button's color), comments left out.
		$this->assertSame( 3, substr_count( $html, '<svg class="wp-swiper-slide__position-icon" aria-hidden="true" focusable="false"' ) );
		$this->assertStringNotContainsString( 'iconoir', $html );
		$this->assertSame( 1, substr_count( $html, 'id="wp-swiper-position-modal"' ) );
		$this->assertStringContainsString( 'name="wp_swiper_slides[position][]" value="20 75"', $html );
		// The thumbnail shows the crop; the button and the mark say it is set.
		$this->assertStringContainsString( 'style="--wp-swiper-thumb-position:20% 75%"', $html );
		$this->assertSame( 1, substr_count( $html, 'wp-swiper-slide__position is-set"' ) );
		$this->assertSame( 1, substr_count( $html, 'data-mark="position">' ) );
		// The larger image for the window.
		$this->assertStringContainsString( 'data-full="' . esc_attr( wp_get_attachment_image_url( $slides[0]['id'], 'large' ) ) . '"', $html );
	}

	public function test_caption_and_description_pages_say_why_they_are_greyed() {
		$this->as_role( 'editor' );
		$id = $this->slider( $this->slides( 1 ) );

		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();

		// No caption yet, or none shown (as a background): admin.js shows one.
		$this->assertStringContainsString( 'data-note="empty" hidden>Add a caption to a slide to use these settings.</p>', $html );
		$this->assertStringContainsString( 'data-note="unavailable" hidden>Captions are not shown when the slider is used as a background.</p>', $html );
		$this->assertStringContainsString( 'the Collage effect or when the slider is used as a background.</p>', $html );
		// And the background's own help says so.
		$this->assertStringContainsString( 'slide links, captions, descriptions, the lightbox and Height are not used', $html );
	}

	public function test_each_color_setting_has_an_arrow_back_to_its_default() {
		$this->as_role( 'editor' );
		$id = $this->slider( $this->slides( 1 ), array( 'caption_color' => '#ff0000' ) );

		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();

		// The Caption's color: the one set, its arrow back to white beside it.
		$this->assertMatchesRegularExpression( '#<span class="wp-swiper-color-field">\s*<input type="color" class="wp-swiper-color" id="wp-swiper-caption_color" name="wp_swiper_settings\[caption_color\]"\s+value="\#ff0000">\s*<button type="button" class="wp-swiper-color-pick__reset wp-swiper-color-reset" data-default="\#ffffff"#', $html );
		// One for every color setting.
		$colors = array_filter(
			WP_Swiper_Settings::fields(),
			function ( $field ) {
				return 'color' === $field['type'];
			}
		);
		$this->assertSame( count( $colors ), substr_count( $html, 'wp-swiper-color-reset"' ) );
	}

	public function test_collage_style_is_chosen_from_icons() {
		$this->as_role( 'editor' );
		$id = $this->slider( $this->slides( 1 ), array( 'effect' => 'collage', 'collage_layout' => 'grid' ) );

		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();

		// The selects stay (one per device, hidden by the CSS), the icons over them.
		$this->assertStringContainsString( 'wp-swiper-field--responsive wp-swiper-field--tiles" data-field="collage_layout"', $html );
		$this->assertStringContainsString( 'name="wp_swiper_settings[collage_layout]"', $html );
		$this->assertMatchesRegularExpression( '#<option value="grid"\s+selected=\'selected\'>Grid</option>#', $html );
		// A tile per style, its icon inline (it takes the tile's color), and Inherit.
		foreach ( array_keys( WP_Swiper_Settings::fields()['collage_layout']['choices'] ) as $style ) {
			$this->assertFileExists( WP_SWIPER_DIR . 'assets/images/collage/' . $style . '.svg', $style );
			$this->assertMatchesRegularExpression( '#<button type="button" class="wp-swiper-tiles__tile" data-value="' . $style . '" role="radio" aria-checked="false" title="[^"]+" aria-label="[^"]+">\s*<svg class="wp-swiper-tiles__icon" aria-hidden="true" focusable="false"[^>]*fill="currentColor"#', $html, $style );
		}
		$this->assertSame( 9, substr_count( $html, '<svg class="wp-swiper-tiles__icon"' ) );
		$this->assertStringContainsString( 'class="wp-swiper-tiles__tile wp-swiper-tiles__tile--inherit" data-value="" role="radio" aria-checked="false" hidden>Inherit</button>', $html );
	}

	public function test_each_slide_has_a_clone_button_right_of_the_position_one() {
		$this->as_role( 'editor' );
		$id = $this->slider( $this->slides( 2 ) );

		ob_start();
		WP_Swiper_Admin::render_slides_box( get_post( $id ) );
		$html = ob_get_clean();

		// Two slides and the template: the position button, then Clone, top left.
		$this->assertSame( 3, preg_match_all( '#<span class="wp-swiper-slide__tools">\s*(<\?php.*?\?>\s*)?<button type="button" class="wp-swiper-slide__position[^"]*"[^>]*>.*?</button>\s*<button type="button" class="wp-swiper-slide__clone" title="Clone slide" aria-label="Clone slide">#s', $html ) );
		// Its icon inline from assets/images (it takes the button's color).
		$this->assertFileExists( WP_SWIPER_DIR . 'assets/images/clone-icon.svg' );
		$this->assertSame( 3, substr_count( $html, '<svg class="wp-swiper-slide__clone-icon" aria-hidden="true" focusable="false"' ) );
		$this->assertStringNotContainsString( 'stroke="#000"', $html );
	}
}
