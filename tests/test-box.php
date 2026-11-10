<?php
/**
 * The boxes of four values (2026-11-10, as WP Banner's): Image border's
 * sides and Rounded corners' radii, each its own once not linked, per
 * device; linked (the default, and every slider saved before): one number,
 * as it was.
 */

class Test_WP_Swiper_Box extends WP_Swiper_TestCase {

	public function test_the_fields() {
		$fields = WP_Swiper_Settings::fields();
		$this->assertSame( array( 'border_radius', 'border_radius_tr', 'border_radius_br', 'border_radius_bl' ), $fields['border_radius']['box'] );
		$this->assertSame( 'border_radius_linked', $fields['border_radius']['box_link'] );
		$this->assertSame( array( 'image_border_width', 'image_border_right', 'image_border_bottom', 'image_border_left' ), $fields['image_border_width']['box'] );
		$this->assertSame( 'image_border_linked', $fields['image_border_width']['box_link'] );
		foreach ( array( 'border_radius_tr', 'border_radius_br', 'border_radius_bl', 'border_radius_linked' ) as $key ) {
			$this->assertSame( 'border_radius', $fields[ $key ]['inline_in'], $key );
		}
		foreach ( array( 'image_border_right', 'image_border_bottom', 'image_border_left', 'image_border_linked' ) as $key ) {
			$this->assertSame( 'image_border_width', $fields[ $key ]['inline_in'], $key );
		}
		$this->assertTrue( WP_Swiper_Settings::defaults()['border_radius_linked'] );
		$this->assertTrue( WP_Swiper_Settings::defaults()['image_border_linked'] );
	}

	public function test_the_link_sent_or_not() {
		// The edit screen sends it always ("1" / "0").
		$this->assertFalse( WP_Swiper_Settings::sanitize( array( 'border_radius_linked' => '0' ) )['border_radius_linked'] );
		$this->assertTrue( WP_Swiper_Settings::sanitize( array( 'border_radius_linked' => '1' ) )['border_radius_linked'] );
		// Not sent (a form from before the box): linked, as by default.
		$this->assertTrue( WP_Swiper_Settings::sanitize( array() )['border_radius_linked'] );
		$this->assertTrue( WP_Swiper_Settings::sanitize( array() )['image_border_linked'] );
		// Other checkboxes left out are still off.
		$this->assertFalse( WP_Swiper_Settings::sanitize( array() )['image_border'] );
	}

	public function test_linked_one_number_as_before() {
		$html = $this->render( array( 'border_rounded' => true, 'border_radius' => 16, 'border_radius_tr' => 0, 'border_radius_mobile' => 6, 'image_border' => true, 'image_border_width' => 3, 'image_border_left' => 9 ) );
		$style = $this->root_style( $html );
		$this->assertStringContainsString( '--wp-swiper-radius:16px', $style );
		$this->assertStringContainsString( '--wp-swiper-radius-mobile:6px', $style );
		$this->assertStringNotContainsString( '--wp-swiper-radius-tablet', $style );
		$this->assertStringContainsString( '--wp-swiper-border-width:3px', $style );
		$this->assertStringContainsString( '--wp-swiper-border-width-mobile:3px', $style );
	}

	public function test_each_corner_and_side_its_own() {
		$html  = $this->render(
			array(
				'border_rounded'          => true,
				'border_radius_linked'    => false,
				'border_radius'           => 20,
				'border_radius_tr'        => 0,
				'border_radius_br'        => 20,
				'border_radius_bl'        => 0,
				// Tablet: only the top right its own; the others the desktop's.
				'border_radius_tr_tablet' => 8,
				'image_border'            => true,
				'image_border_linked'     => false,
				'image_border_width'      => 0,
				'image_border_right'      => 0,
				'image_border_bottom'     => 4,
				'image_border_left'       => 0,
			)
		);
		$style = $this->root_style( $html );
		$this->assertStringContainsString( '--wp-swiper-radius:20px 0px 20px 0px', $style );
		$this->assertStringContainsString( '--wp-swiper-radius-tablet:20px 8px 20px 0px', $style );
		$this->assertStringNotContainsString( '--wp-swiper-radius-mobile', $style );
		$this->assertStringContainsString( '--wp-swiper-border-width:0px 0px 4px 0px', $style );
		$this->assertStringContainsString( '--wp-swiper-border-width-tablet:0px 0px 4px 0px', $style );

		// Four the same: one length.
		$this->assertSame( '7px', WP_Swiper_Settings::box_css( array( 7, 7, 7, 7 ) ) );
		$this->assertSame( '7px 0px 7px 1px', WP_Swiper_Settings::box_css( array( 7, 0, 7, 1 ) ) );
	}

	public function test_rounded_corners_off_none_at_all() {
		$html = $this->render( array( 'border_rounded' => false, 'border_radius_linked' => false, 'border_radius' => 20, 'border_radius_tr' => 10, 'border_radius_br_mobile' => 5 ) );
		$this->assertStringNotContainsString( '--wp-swiper-radius', $this->root_style( $html ) );
	}

	public function test_the_box_on_the_edit_screen() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $this->slider( $this->slides( 1 ), array( 'border_rounded' => true, 'border_radius_linked' => false, 'border_radius_bl' => 30 ) ) ) );
		$html = ob_get_clean();

		// One field each; the parts inside it, per device, at their corners / sides.
		$this->assertSame( 1, substr_count( $html, 'data-field="border_radius"' ) );
		$this->assertSame( 0, substr_count( $html, 'data-field="border_radius_tr"' ) );
		$this->assertSame( 1, preg_match( '#<div class="wp-swiper-field wp-swiper-field--responsive wp-swiper-field--box wp-swiper-field--corners" data-field="border_radius"#', $html ) );
		$this->assertSame( 1, preg_match( '#<div class="wp-swiper-field wp-swiper-field--responsive wp-swiper-field--box wp-swiper-field--sides is-linked" data-field="image_border_width"#', $html ) );
		foreach ( array( 'tl' => 'border_radius', 'tr' => 'border_radius_tr', 'br' => 'border_radius_br', 'bl' => 'border_radius_bl' ) as $place => $key ) {
			$this->assertSame( 1, preg_match( '#<span class="wp-swiper-box__part wp-swiper-box__part--' . $place . '">\s*<input type="number"\s+id="wp-swiper-' . $key . '"#', $html ), $place );
			foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
				$this->assertStringContainsString( 'name="wp_swiper_settings[' . $key . $suffix . ']"', $html );
			}
		}
		$this->assertSame( 1, preg_match( '#id="wp-swiper-border_radius_bl"[^>]*value="30"#', $html ) );
		foreach ( array( 'top' => 'image_border_width', 'right' => 'image_border_right', 'bottom' => 'image_border_bottom', 'left' => 'image_border_left' ) as $place => $key ) {
			$this->assertSame( 1, preg_match( '#wp-swiper-box__part--' . $place . '">\s*<input type="number"\s+id="wp-swiper-' . $key . '"#', $html ), $place );
		}
		// The link: its state sent always; WP Banner's icon, in its color.
		$this->assertStringContainsString( '<input type="hidden" class="wp-swiper-box__linked" id="wp-swiper-border_radius_linked" name="wp_swiper_settings[border_radius_linked]" value="0">', $html );
		$this->assertStringContainsString( '<input type="hidden" class="wp-swiper-box__linked" id="wp-swiper-image_border_linked" name="wp_swiper_settings[image_border_linked]" value="1">', $html );
		// The slides' radius, Image border, the thumbnails' radius.
		$this->assertSame( 3, preg_match_all( '#<button type="button" class="wp-swiper-box__link" aria-pressed="(true|false)"#', $html ) );
		$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/border-link.svg' );
		$this->assertStringContainsString( 'currentColor', $svg );
		$this->assertDoesNotMatchRegularExpression( '~<script|<foreignObject|<style|class=|\son\w+=|href=~i', $svg );
	}

	public function test_the_border_line() {
		$fields = WP_Swiper_Settings::fields();
		$this->assertSame( 'solid', $fields['image_border_style']['default'] );
		$this->assertSame( array( 'solid', 'dashed', 'dotted', 'double' ), array_keys( $fields['image_border_style']['choices'] ) );
		// Left of the color, in its row; shown after the width.
		$this->assertSame( 'image_border', $fields['image_border_style']['group'] );
		$look = WP_Swiper_Settings::blocks()['layout']['look']['fields'];
		$this->assertSame( array_search( 'image_border_color', $look, true ) - 1, array_search( 'image_border_style', $look, true ) );
		$this->assertSame( 'solid', WP_Swiper_Settings::sanitize( array( 'image_border_style' => 'wavy' ) )['image_border_style'] );
		// Solid: no variable (the CSS falls back to it); the others theirs.
		$this->assertStringNotContainsString( '--wp-swiper-border-style', $this->root_style( $this->render( array( 'image_border' => true ) ) ) );
		$this->assertStringContainsString( '--wp-swiper-border-style:dotted', $this->root_style( $this->render( array( 'image_border' => true, 'image_border_style' => 'dotted' ) ) ) );
		$this->assertStringNotContainsString( '--wp-swiper-border-style', $this->root_style( $this->render( array( 'image_border' => false, 'image_border_style' => 'dotted' ) ) ) );
		foreach ( array( 'frontend.css', 'collage.css' ) as $file ) {
			$this->assertStringContainsString( 'border-style: var(--wp-swiper-border-style, solid);', file_get_contents( WP_SWIPER_DIR . 'assets/css/' . $file ), $file );
		}
	}

	public function test_the_thumbnails_corners_and_the_lightbox_ones() {
		$fields = WP_Swiper_Settings::fields();
		$this->assertSame( array( 'thumbs_radius', 'thumbs_radius_tr', 'thumbs_radius_br', 'thumbs_radius_bl' ), $fields['thumbs_radius']['box'] );
		$this->assertSame( 'thumbs_radius_linked', $fields['thumbs_radius']['box_link'] );
		// No slider (as the slides').
		$this->assertArrayNotHasKey( 'range', $fields['thumbs_radius'] );
		$thumbs = array( 'effect' => 'thumbs', 'lightbox' => true, 'lightbox_thumbs' => true, 'thumbs_rounded' => true );

		// Linked: one number, as before (the lightbox's too).
		$html = $this->render( $thumbs + array( 'thumbs_radius' => 10, 'thumbs_radius_tr' => 0 ) );
		$this->assertStringContainsString( '--wp-swiper-thumb-radius:10px', $this->root_style( $html ) );
		$this->assertSame( array( 'desktop' => 10, 'tablet' => 10, 'mobile' => 10 ), $this->config( $html )['lightbox']['thumbRadius'] );

		// Apart: four, per device; the lightbox's thumbnails take them too.
		$html = $this->render( $thumbs + array( 'thumbs_radius_linked' => false, 'thumbs_radius' => 10, 'thumbs_radius_tr' => 0, 'thumbs_radius_br' => 10, 'thumbs_radius_bl' => 0, 'thumbs_radius_bl_mobile' => 4 ) );
		$this->assertStringContainsString( '--wp-swiper-thumb-radius:10px 0px 10px 0px', $this->root_style( $html ) );
		$this->assertStringContainsString( '--wp-swiper-thumb-radius-mobile:10px 0px 10px 4px', $this->root_style( $html ) );
		$this->assertSame( array( 'desktop' => '10px 0px 10px 0px', 'tablet' => '10px 0px 10px 0px', 'mobile' => '10px 0px 10px 4px' ), $this->config( $html )['lightbox']['thumbRadius'] );
		// The lightbox's own radius over them.
		$html = $this->render( $thumbs + array( 'thumbs_radius_linked' => false, 'thumbs_radius_tr' => 0, 'lightbox_thumbs_radius' => 3 ) );
		$this->assertSame( 3, $this->config( $html )['lightbox']['thumbRadius']['desktop'] );

		// Rounded corners off: none, any corner.
		$html = $this->render( array( 'thumbs_rounded' => false ) + $thumbs + array( 'thumbs_radius_linked' => false, 'thumbs_radius_br' => 20 ) );
		$this->assertStringContainsString( '--wp-swiper-thumb-radius:0px', $this->root_style( $html ) );
	}
}
