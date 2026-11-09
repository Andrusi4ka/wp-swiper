<?php
/**
 * More places than photos (a Collage template, Art with more windows than
 * photos): the photos repeat, and an order set by hand there says what each
 * place shows, a photo in as many places as it was put in (dragged in the
 * preview past the photos' number). Kept when saved, given to collage.js and
 * art.js as it is (content.js wpSwiperPlaces.order() lays it out).
 */

class Test_WP_Swiper_Order_Repeats extends WP_Swiper_TestCase {

	public function test_an_order_keeps_a_photo_more_than_once() {
		$this->assertSame( '5,7,5,9,7', WP_Swiper_Settings::sanitize_order( '5,7,5,9,7' ) );
		// Saved as the field is.
		$this->assertSame( '5,7,5,9,7', WP_Swiper_Settings::sanitize( array( 'art_order' => '5,7,5,9,7' ) )['art_order'] );
		$this->assertSame( '5,7,5', WP_Swiper_Settings::sanitize( array( 'collage_order_tablet' => '5,7,5' ) )['collage_order_tablet'] );
	}

	public function test_ids_to_slides_each_free_one_first_then_the_first_again() {
		$slides = $this->slides( 3 );
		$ids    = wp_list_pluck( $slides, 'id' );
		// Each once: as before.
		$this->assertSame( array( 2, 0, 1 ), WP_Swiper_Settings::order_indexes( $ids[2] . ',' . $ids[0] . ',' . $ids[1], $slides ) );
		// A photo again once its slide is taken: that slide again.
		$this->assertSame( array( 0, 1, 2, 1, 0, 1 ), WP_Swiper_Settings::order_indexes( implode( ',', array( $ids[0], $ids[1], $ids[2], $ids[1], $ids[0], $ids[1] ) ), $slides ) );
		// A photo in two slides: its free one first.
		$twice = array( $slides[0], $slides[0], $slides[1] );
		$this->assertSame( array( 0, 1, 0 ), WP_Swiper_Settings::order_indexes( implode( ',', array( $ids[0], $ids[0], $ids[0] ) ), $twice ) );
		// An ID no slide has: left out.
		$this->assertSame( array( 1 ), WP_Swiper_Settings::order_indexes( '999999,' . $ids[1], $slides ) );
		$this->assertSame( array(), WP_Swiper_Settings::order_indexes( '', $slides ) );
	}

	public function test_collage_and_art_get_the_order_with_its_repeats() {
		$slides = $this->slides( 3 );
		$ids    = wp_list_pluck( $slides, 'id' );
		$order  = implode( ',', array( $ids[1], $ids[0], $ids[2], $ids[1], $ids[1] ) );

		$this->assertSame( array( 1, 0, 2, 1, 1 ), WP_Swiper_Settings::collage_orders( $this->settings( array( 'collage_order' => $order ) ), $slides )['desktop'] );
		// Art: the photos missing from it after it (none here).
		$this->assertSame( array( 1, 0, 2, 1, 1 ), WP_Swiper_Art::orders( $this->settings( array( 'art_order' => $order ) ), $slides )['desktop'] );
		$this->assertSame( array( 1, 1, 0, 2 ), WP_Swiper_Art::orders( $this->settings( array( 'art_order' => $ids[1] . ',' . $ids[1] ) ), $slides )['desktop'] );
	}

	public function test_the_scripts_lay_it_out_and_the_previews_keep_it() {
		$content = file_get_contents( WP_SWIPER_DIR . 'assets/js/content.js' );
		$this->assertStringContainsString( 'window.wpSwiperPlaces = { order: order, reals: reals, kept: kept };', $content );
		$this->assertStringContainsString( 'window.wpSwiperPlaces.order(', file_get_contents( WP_SWIPER_DIR . 'assets/js/collage.js' ) );
		$this->assertStringContainsString( 'window.wpSwiperPlaces.order(', file_get_contents( WP_SWIPER_DIR . 'assets/js/art.js' ) );
		foreach ( array( 'preview-art.js', 'preview-collage.js' ) as $file ) {
			$js = file_get_contents( WP_SWIPER_DIR . 'assets/js/' . $file );
			$this->assertStringContainsString( 'window.wpSwiperPlaces.kept( list, api.ids.length )', $js, $file );
			$this->assertStringContainsString( 'window.wpSwiperPlaces.reals( state.order || [], api.ids )', $js, $file );
		}
	}
}
