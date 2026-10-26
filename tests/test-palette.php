<?php
/**
 * WP_Swiper_Palette: the "Text color" palette of the slide text editors —
 * the theme's colors, the user's own (Settings), black, white and greys.
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

	public function test_sanitize_own_colors() {
		$clean = WP_Swiper_Palette::sanitize(
			array(
				array( 'color' => '#ABC', 'name' => '<b>Brand</b>' ),
				array( 'color' => 'red', 'name' => 'Not a color' ),
				array( 'color' => '#aabbcc', 'name' => 'Same again' ),
				array( 'color' => '#123456', 'name' => str_repeat( 'a', 60 ) ),
				'nonsense',
			)
		);
		$this->assertSame(
			array(
				array( 'color' => '#aabbcc', 'name' => 'Brand' ),
				array( 'color' => '#123456', 'name' => str_repeat( 'a', 40 ) ),
			),
			$clean
		);
		$this->assertSame( array(), WP_Swiper_Palette::sanitize( '' ) );

		$many = array();
		for ( $i = 0; $i < 40; $i++ ) {
			$many[] = array( 'color' => sprintf( '#%06x', $i ) );
		}
		$this->assertCount( WP_Swiper_Palette::MAX, WP_Swiper_Palette::sanitize( $many ) );
	}

	public function test_palette_order_and_tinymce_map() {
		update_option( WP_Swiper_Palette::OPTION, array( array( 'color' => '#2a6fdb', 'name' => 'Brand blue' ), array( 'color' => '#ffffff', 'name' => 'Paper' ), array( 'color' => '#1b9e77', 'name' => '' ) ) );

		$colors = wp_list_pluck( WP_Swiper_Palette::colors(), 'name', 'color' );
		// The user's, each color once (its first name), a nameless one by its value; then the basic ones.
		$this->assertSame( 'Brand blue', $colors['#2a6fdb'] );
		$this->assertSame( 'Paper', $colors['#ffffff'] );
		$this->assertSame( '#1b9e77', $colors['#1b9e77'] );
		$this->assertSame( 'Black', $colors['#000000'] );
		$keys = array_keys( $colors );
		$this->assertLessThan( array_search( '#000000', $keys, true ), array_search( '#2a6fdb', $keys, true ) );

		$map = WP_Swiper_Palette::tinymce_map();
		$this->assertSame( count( $colors ) * 2, count( $map ) );
		$at = array_search( '2A6FDB', $map, true );
		$this->assertNotFalse( $at );
		$this->assertSame( 'Brand blue', $map[ $at + 1 ] );
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
