<?php
/**
 * WP_Swiper_Palette: the "Text color" palette of the slide text editors —
 * the theme's colors, then black, white and greys.
 */

class Test_WP_Swiper_Palette extends WP_Swiper_TestCase {

	public function test_hex() {
		$this->assertSame( '#aabbcc', WP_Swiper_Palette::hex( '#ABC' ) );
		$this->assertSame( '#f57662', WP_Swiper_Palette::hex( ' #F57662 ' ) );
		$this->assertSame( '#f57662', WP_Swiper_Palette::hex( 'rgb(245,118,98)' ) );
		$this->assertSame( '#010203', WP_Swiper_Palette::hex( 'rgba(1, 2, 3, .5)' ) );
		foreach ( array( '', 'red', '#12345', 'var(--x)', 'url(x)' ) as $bad ) {
			$this->assertSame( '', WP_Swiper_Palette::hex( $bad ), $bad );
		}
	}

	public function test_palette_and_tinymce_map() {
		// Colors saved by the Settings page there was until 2.9: not offered any more.
		update_option( 'wp_swiper_text_colors', array( array( 'color' => '#2a6fdb', 'name' => 'Brand blue' ) ) );

		$colors = wp_list_pluck( WP_Swiper_Palette::colors(), 'name', 'color' );
		$this->assertArrayNotHasKey( '#2a6fdb', $colors );
		// The basic ones, after the theme's (none in the tests' theme).
		$this->assertSame( array( '#000000', '#444444', '#888888', '#cccccc', '#ffffff' ), array_slice( array_keys( $colors ), -5 ) );
		$this->assertSame( 'Black', $colors['#000000'] );

		$map = WP_Swiper_Palette::tinymce_map();
		$this->assertSame( count( $colors ) * 2, count( $map ) );
		$at = array_search( '000000', $map, true );
		$this->assertNotFalse( $at );
		$this->assertSame( 'Black', $map[ $at + 1 ] );
	}

	public function test_no_settings_page() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'admin_menu' );
		$this->assertEmpty( menu_page_url( 'wp-swiper-settings', false ) );
	}

	public function test_editor_gets_the_palette() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'post' );
		get_current_screen()->post_type = WP_Swiper_Settings::POST_TYPE;
		WP_Swiper_Admin::enqueue( 'post.php' );
		$data = wp_scripts()->get_data( 'wp-swiper-admin', 'data' );
		$this->assertStringContainsString( '"textColors":["', $data );
		$this->assertStringContainsString( '"000000","Black"', $data );
	}
}
