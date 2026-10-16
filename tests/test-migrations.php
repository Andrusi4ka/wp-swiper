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
		// "Rounded corners in the lightbox" on: the slider's (empty).
		$rounded = $this->saved( array( '_version' => 5, 'lightbox_rounded' => true, 'lightbox_radius' => 30 ) );
		$this->assertSame( '', $rounded['lightbox_radius'] );
		$this->assertSame( '', $rounded['lightbox_thumbs_radius'] );
		// Off: square.
		$square = $this->saved( array( '_version' => 5 ) );
		$this->assertSame( 0, $square['lightbox_radius'] );
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
}
