<?php
/**
 * WP_Swiper_Settings::get(): sliders saved by older versions read the way
 * they showed then (the migrations by "_version").
 */

class Test_WP_Swiper_Migrations extends WP_Swiper_TestCase {

	private function saved( $settings ) {
		return WP_Swiper_Settings::get( $this->slider( array(), $settings ) );
	}

	public function test_nothing_saved_gives_the_defaults() {
		$id = self::factory()->post->create( array( 'post_type' => WP_Swiper_Settings::POST_TYPE ) );
		$this->assertSame( WP_Swiper_Settings::defaults(), WP_Swiper_Settings::get( $id ) );
	}

	public function test_current_settings_are_read_as_saved() {
		$saved    = WP_Swiper_Settings::sanitize( array( 'effect' => 'fade', 'height' => '300', 'zoom' => '1' ) );
		$expected = $saved + WP_Swiper_Settings::defaults();
		$actual   = $this->saved( $saved );
		ksort( $expected );
		ksort( $actual );
		$this->assertSame( $expected, $actual );
	}

	public function test_unversioned_mobile_height_0_inherits() {
		$this->assertSame( '', $this->saved( array( 'height_mobile' => 0, 'height' => 400 ) )['height_mobile'] );
		$this->assertSame( 0, $this->saved( array( '_version' => 6, 'height_mobile' => 0 ) )['height_mobile'] );
	}

	public function test_v3_visible_slides_become_slides_per_view() {
		$settings = $this->saved(
			array(
				'_version'              => 2,
				'expand_active'         => true,
				'visible_slides'        => 4,
				'visible_slides_mobile' => 2,
			)
		);
		$this->assertSame( 4, $settings['slides_per_view'] );
		$this->assertSame( '', $settings['slides_per_view_tablet'] );
		$this->assertSame( 2, $settings['slides_per_view_mobile'] );
	}

	public function test_progress_bar_pagination_becomes_bullets() {
		$this->assertSame( 'bullets', $this->saved( array( '_version' => 6, 'pagination' => 'progressbar' ) )['pagination'] );
	}

	public function test_effect_shadows_turn_on_both_cube_shadows() {
		$settings = $this->saved( array( '_version' => 6, 'effect' => 'cube', 'effect_shadows' => true ) );
		$this->assertTrue( $settings['cube_shadow'] );
		$this->assertTrue( $settings['cube_slide_shadows'] );

		$own = $this->saved( array( '_version' => 6, 'effect_shadows' => true, 'cube_shadow' => false ) );
		$this->assertFalse( $own['cube_shadow'] );
	}

	public function test_two_ratio_fields_become_one() {
		$settings = $this->saved(
			array(
				'_version'            => 6,
				'thumbs_ratio_width'  => 2.35,
				'thumbs_ratio_height' => 1,
				'layouts'             => array( 'thumbs' => array( 'thumbs_ratio_width' => 5, 'thumbs_ratio_height' => 4 ) ),
			)
		);
		$this->assertSame( '2.35x1', $settings['thumbs_ratio_custom'] );
		$this->assertSame( '5x4', $settings['layouts']['thumbs']['thumbs_ratio_custom'] );
	}

	public function test_v4_sliders_that_filled_their_parent_keep_doing_so() {
		// Contain, or no height on some device: it filled its parent block.
		$this->assertSame( 'auto', $this->saved( array( '_version' => 3, 'image_fit' => 'contain', 'height' => 400 ) )['height_unit'] );
		$this->assertSame( 'auto', $this->saved( array( '_version' => 3, 'height' => 400, 'height_mobile' => 0 ) )['height_unit'] );
		// Cover with a height everywhere: it kept it.
		$this->assertSame( 'px', $this->saved( array( '_version' => 3, 'height' => 400, 'height_mobile' => 300 ) )['height_unit'] );
		// The wider active slide never filled it, even without a height somewhere.
		$this->assertSame( 'px', $this->saved( array( '_version' => 3, 'expand_active' => true, 'height' => 400, 'height_mobile' => 0 ) )['height_unit'] );
		// Saved since: as saved.
		$this->assertSame( 'px', $this->saved( array( '_version' => 6, 'image_fit' => 'contain', 'height' => 400 ) )['height_unit'] );
	}

	public function test_v5_old_cards_offset_default() {
		$settings = $this->saved(
			array(
				'_version'     => 4,
				'cards_offset' => 16,
				'layouts'      => array(
					'cards' => array( 'cards_offset' => 16 ),
					'slide' => array( 'cards_offset' => 12 ),
				),
			)
		);
		$this->assertSame( 8, $settings['cards_offset'] );
		$this->assertSame( 8, $settings['layouts']['cards']['cards_offset'] );
		$this->assertSame( 12, $settings['layouts']['slide']['cards_offset'] );
	}

	public function test_v6_lightbox_radii() {
		// "Rounded corners in the lightbox" on: the slider's (its radius, then its own since 3.0).
		$rounded = $this->saved( array( '_version' => 5, 'lightbox_rounded' => true, 'lightbox_radius' => 30, 'border_radius' => 10 ) );
		$this->assertTrue( $rounded['lightbox_border_rounded'] );
		$this->assertSame( 10, $rounded['lightbox_radius'] );
		$this->assertSame( '', $rounded['lightbox_thumbs_radius'] );
		// Off: square.
		$square = $this->saved( array( '_version' => 5, 'border_radius' => 10 ) );
		$this->assertFalse( $square['lightbox_border_rounded'] );
		$this->assertSame( '', $square['lightbox_radius_mobile'] );
	}

	public function test_settings_saved_before_new_fields_get_their_defaults() {
		// As 1.3.1 saved them: no Collage, Background or Zoom keys.
		$settings = $this->saved( array( '_version' => 6, 'effect' => 'fade', 'height' => 350 ) );
		$this->assertFalse( $settings['background'] );
		$this->assertFalse( $settings['zoom'] );
		$this->assertSame( 'feature-center', $settings['collage_layout'] );
		$this->assertFalse( WP_Swiper_Settings::is_background( $settings ) );
		$this->assertFalse( WP_Swiper_Settings::is_zoom( $settings ) );
	}

	public function test_overlay_opacity_becomes_the_overlay_colors_own() {
		// Overlay opacity (%) beside a plain color, until 3.0.
		$settings = $this->saved( array( 'background_overlay_opacity' => 40, 'background_overlay_color' => '#102030' ) );
		$this->assertSame( 'rgba(16,32,48,0.4)', $settings['background_overlay_color'] );
		$this->assertArrayNotHasKey( 'background_overlay_opacity', $settings );
		// 0 was none: still none.
		$settings = $this->saved( array( 'background_overlay_opacity' => 0, 'background_overlay_color' => '#ffffff' ) );
		$this->assertSame( 'rgba(255,255,255,0)', $settings['background_overlay_color'] );
		$this->assertSame( '', WP_Swiper_Settings::background_overlay( $settings ) );
		// 100: the plain color; its default color (black) when none was saved.
		$this->assertSame( '#000000', $this->saved( array( 'background_overlay_opacity' => 100 ) )['background_overlay_color'] );
		// Saved since: as it is.
		$this->assertSame( 'rgba(1,2,3,0.5)', $this->saved( array( 'background_overlay_color' => 'rgba(1,2,3,0.5)' ) )['background_overlay_color'] );
	}

	public function test_a_saved_radius_turns_rounded_corners_on() {
		// Saved before the switch with a radius: on, the radius kept.
		$settings = $this->saved( array( 'border_radius' => 16 ) );
		$this->assertTrue( $settings['border_rounded'] );
		$this->assertSame( 16, $settings['border_radius'] );
		// A radius on a smaller screen only: on too.
		$this->assertTrue( $this->saved( array( 'border_radius' => 0, 'border_radius_mobile' => 8 ) )['border_rounded'] );
		// No radius (0 everywhere): off.
		$this->assertFalse( $this->saved( array( 'border_radius' => 0, 'border_radius_tablet' => '' ) )['border_rounded'] );
		// Saved since: as it is.
		$this->assertFalse( $this->saved( array( 'border_rounded' => false, 'border_radius' => 20 ) )['border_rounded'] );
		// Each effect's own layout too.
		$settings = $this->saved( array( 'border_radius' => 0, 'layouts' => array( 'fade' => array( 'border_radius' => 9 ) ) ) );
		$this->assertTrue( $settings['layouts']['fade']['border_rounded'] );
		$this->assertFalse( $settings['border_rounded'] );
	}

	public function test_lightbox_radius_as_the_slides_becomes_its_own() {
		// Empty ("As the slides"): the slider's radius, now the lightbox's own, switch on.
		$settings = $this->saved( array( '_version' => 6, 'border_radius' => 16, 'border_radius_mobile' => 6, 'lightbox_radius' => '' ) );
		$this->assertTrue( $settings['lightbox_border_rounded'] );
		$this->assertSame( array( 16, '', 6 ), array( $settings['lightbox_radius'], $settings['lightbox_radius_tablet'], $settings['lightbox_radius_mobile'] ) );
		$this->assertSame( array( 'desktop' => 16, 'tablet' => 16, 'mobile' => 6 ), WP_Swiper_Settings::lightbox_image_radius( $settings ) );
		// Its own radius on desktop, the slider's on mobile.
		$settings = $this->saved( array( '_version' => 6, 'border_radius' => 0, 'border_radius_mobile' => 8, 'lightbox_radius' => 20, 'lightbox_radius_mobile' => '' ) );
		$this->assertSame( array( 'desktop' => 20, 'tablet' => 20, 'mobile' => 20 ), WP_Swiper_Settings::lightbox_image_radius( $settings ) );
		// Square slides, "As the slides": square, switch off (its radius back to the default).
		$settings = $this->saved( array( '_version' => 6, 'border_radius' => 0, 'lightbox_radius' => '' ) );
		$this->assertFalse( $settings['lightbox_border_rounded'] );
		$this->assertSame( 12, $settings['lightbox_radius'] );
		$this->assertSame( array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ), WP_Swiper_Settings::lightbox_image_radius( $settings ) );
		// A radius of 0 of its own: square too.
		$this->assertFalse( $this->saved( array( '_version' => 6, 'border_radius' => 16, 'lightbox_radius' => 0 ) )['lightbox_border_rounded'] );
		// Art: its Rounded corners were the lightbox's.
		$settings = $this->saved( array( '_version' => 6, 'effect' => 'art', 'art_radius' => 14, 'lightbox_radius' => '' ) );
		$this->assertTrue( $settings['lightbox_border_rounded'] );
		$this->assertSame( 14, $settings['lightbox_radius'] );
		// Saved since: as it is.
		$settings = $this->saved( array( '_version' => 6, 'lightbox_border_rounded' => false, 'lightbox_radius' => 30, 'border_radius' => 16 ) );
		$this->assertFalse( $settings['lightbox_border_rounded'] );
		$this->assertSame( 30, $settings['lightbox_radius'] );
	}

	public function test_caption_bg_opacity_becomes_the_colors_own() {
		$settings = $this->saved( array( 'caption_bg_opacity' => 40, 'caption_bg_color' => '#102030' ) );
		$this->assertSame( 'rgba(16,32,48,0.4)', $settings['caption_bg_color'] );
		$this->assertArrayNotHasKey( 'caption_bg_opacity', $settings );
		// Full: the plain color; none saved: black, as before.
		$this->assertSame( '#ffffff', $this->saved( array( 'caption_bg_opacity' => 100, 'caption_bg_color' => '#ffffff' ) )['caption_bg_color'] );
		$this->assertSame( 'rgba(0,0,0,0.65)', $this->saved( array( 'caption_bg_opacity' => 65 ) )['caption_bg_color'] );
		// Saved since: as it is.
		$this->assertSame( 'rgba(1,2,3,0.5)', $this->saved( array( 'caption_bg_color' => 'rgba(1,2,3,0.5)' ) )['caption_bg_color'] );
	}

	public function test_cube_shadow_opacity_becomes_a_color() {
		// It was black at that opacity.
		$settings = $this->saved( array( 'effect' => 'cube', 'cube_shadow' => true, 'cube_shadow_opacity' => 40 ) );
		$this->assertSame( 'rgba(0,0,0,0.4)', $settings['cube_shadow_color'] );
		$this->assertArrayNotHasKey( 'cube_shadow_opacity', $settings );
		$this->assertSame( '#000000', $this->saved( array( 'cube_shadow_opacity' => 100 ) )['cube_shadow_color'] );
		// Never saved: the default, the same black at 60%.
		$this->assertSame( 'rgba(0,0,0,0.6)', $this->saved( array( 'effect' => 'cube' ) )['cube_shadow_color'] );
		// In another effect's layout values too.
		$layouts = $this->saved( array( 'effect' => 'slide', 'layouts' => array( 'cube' => array( 'cube_shadow_opacity' => 20 ) ) ) )['layouts'];
		$this->assertSame( 'rgba(0,0,0,0.2)', $layouts['cube']['cube_shadow_color'] );
		$this->assertArrayNotHasKey( 'cube_shadow_opacity', $layouts['cube'] );
	}

	public function test_thumbnail_switches_on_where_they_had_them() {
		// A radius on any screen: Rounded corners on, the radius kept.
		$settings = $this->saved( array( 'effect' => 'thumbs', 'thumbs_radius' => 0, 'thumbs_radius_tablet' => 8 ) );
		$this->assertTrue( $settings['thumbs_rounded'] );
		$this->assertSame( 8, $settings['thumbs_radius_tablet'] );
		// None: off, the default radius waiting for it.
		$settings = $this->saved( array( 'effect' => 'thumbs', 'thumbs_radius' => 0 ) );
		$this->assertFalse( $settings['thumbs_rounded'] );
		$this->assertSame( 6, $settings['thumbs_radius'] );

		// A frame: on, its width kept; 0: off, the default width waiting for it.
		$settings = $this->saved( array( 'effect' => 'thumbs', 'thumbs_border_width' => 3 ) );
		$this->assertTrue( $settings['thumbs_border'] );
		$this->assertSame( 3, $settings['thumbs_border_width'] );
		$settings = $this->saved( array( 'effect' => 'thumbs', 'thumbs_border_width' => 0 ) );
		$this->assertFalse( $settings['thumbs_border'] );
		$this->assertSame( 1, $settings['thumbs_border_width'] );

		// Saved since: as they are.
		$settings = $this->saved( array( 'thumbs_rounded' => false, 'thumbs_radius' => 9, 'thumbs_border' => true, 'thumbs_border_width' => 2 ) );
		$this->assertFalse( $settings['thumbs_rounded'] );
		$this->assertSame( 9, $settings['thumbs_radius'] );
		$this->assertTrue( $settings['thumbs_border'] );

		// In another effect's layout values too.
		$layouts = $this->saved( array( 'effect' => 'slide', 'layouts' => array( 'thumbs' => array( 'thumbs_radius' => 5, 'thumbs_border_width' => 0 ) ) ) )['layouts'];
		$this->assertTrue( $layouts['thumbs']['thumbs_rounded'] );
		$this->assertFalse( $layouts['thumbs']['thumbs_border'] );
	}

	public function test_version_7_the_phone_inherits_what_had_an_old_phone_default() {
		// No phone defaults of their own any more: empty, inheriting.
		foreach ( array_keys( WP_Swiper_Settings::OLD_MOBILE_DEFAULTS ) as $key ) {
			$this->assertArrayNotHasKey( 'device_defaults', WP_Swiper_Settings::fields()[ $key ], $key );
			$this->assertSame( '', WP_Swiper_Settings::defaults()[ $key . '_mobile' ], $key );
		}
		// Saved at the old default: inherits now (Style, numbers, the lightbox text width).
		$settings = $this->saved(
			array(
				'_version'                          => 6,
				'effect'                            => 'collage',
				'collage_layout'                    => 'grid',
				'collage_layout_mobile'             => 'masonry',
				'collage_count'                     => 8,
				'collage_count_mobile'              => 4,
				'collage_columns_mobile'            => 2,
				'collage_row_height_mobile'         => 140,
				'collage_gap_mobile'                => 6,
				'active_width_mobile'               => 70,
				'thumbs_per_view_mobile'            => 4,
				'description_lightbox_width_mobile' => 100,
				'layouts'                           => array( 'thumbs' => array( 'thumbs_per_view_mobile' => 4, 'collage_layout_mobile' => 'masonry' ) ),
			)
		);
		foreach ( array_keys( WP_Swiper_Settings::OLD_MOBILE_DEFAULTS ) as $key ) {
			$this->assertSame( '', $settings[ $key . '_mobile' ], $key );
		}
		$this->assertSame( 'grid', WP_Swiper_Settings::responsive( $settings, 'collage_layout' )['mobile'] );
		$this->assertSame( 8, WP_Swiper_Settings::responsive( $settings, 'collage_count' )['mobile'] );
		$this->assertSame( '', $settings['layouts']['thumbs']['thumbs_per_view_mobile'] );
		$this->assertSame( '', $settings['layouts']['thumbs']['collage_layout_mobile'] );
		// A phone value of its own (not the old default) stays.
		$own = $this->saved( array( '_version' => 6, 'collage_layout_mobile' => 'justified', 'collage_count_mobile' => 3, 'thumbs_per_view_mobile' => '' ) );
		$this->assertSame( 'justified', $own['collage_layout_mobile'] );
		$this->assertSame( 3, $own['collage_count_mobile'] );
		// Saved since (version 7): as saved, the old default too (chosen then).
		$this->assertSame( 'masonry', $this->saved( array( '_version' => 7, 'collage_layout_mobile' => 'masonry' ) )['collage_layout_mobile'] );
		$this->assertSame( 7, WP_Swiper_Settings::sanitize( array() )['_version'] );
	}

	public function test_art_saved_before_zoom_on_hover_keeps_it() {
		// Art's photos always zoomed under the pointer: an Art slider saved before keeps it.
		$this->assertTrue( $this->saved( array( '_version' => 7, 'effect' => 'art' ) )['image_hover_zoom'] );
		// The others had none.
		$this->assertFalse( $this->saved( array( '_version' => 7, 'effect' => 'slide' ) )['image_hover_zoom'] );
		// Saved since: as set.
		$this->assertFalse( $this->saved( array( '_version' => 7, 'effect' => 'art', 'image_hover_zoom' => false ) )['image_hover_zoom'] );
		// Art's saved Layout values too.
		$merged = WP_Swiper_Settings::merge_hover_zoom( array( 'effect' => 'slide', 'layouts' => array( 'art' => array( 'art_style' => 'ring' ), 'fade' => array() ) ) );
		$this->assertTrue( $merged['layouts']['art']['image_hover_zoom'] );
		$this->assertArrayNotHasKey( 'image_hover_zoom', $merged['layouts']['fade'] );
		$this->assertArrayNotHasKey( 'image_hover_zoom', $merged );
		// Art never chosen: its Layout values the defaults, with it on.
		$layouts = WP_Swiper_Settings::layouts( $this->settings( array( 'effect' => 'slide' ) ), array() );
		$this->assertTrue( $layouts['art']['image_hover_zoom'] );
		$this->assertFalse( $layouts['fade']['image_hover_zoom'] );
	}
}
