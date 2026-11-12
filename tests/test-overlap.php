<?php
/**
 * Collage, the Overlap Style: Freeform's photos over one another, each place
 * on a layer of its own. Its layout kept in collage_cuts as "overlap:" and
 * each place's x,y,w,h,z (WP_Swiper_Settings::sanitize_freeform() with
 * layers); laid out by collage.js (freeLayout(), place()), set by hand in
 * the preview (preview-collage.js).
 */

class Test_WP_Swiper_Overlap extends WP_Swiper_TestCase {

	public function test_a_style_of_its_own_after_freeform() {
		$this->assertSame( 'overlap', WP_Swiper_Settings::COLLAGE_OVERLAP );
		$styles = array_keys( WP_Swiper_Settings::fields()['collage_layout']['choices'] );
		$this->assertSame( 'overlap', end( $styles ) );
		$this->assertSame( array_search( 'freeform', $styles, true ) + 1, array_search( 'overlap', $styles, true ) );
		$this->assertStringContainsString( 'Overlap:', WP_Swiper_Settings::fields()['collage_layout']['help'] );
		$this->assertStringContainsString( 'with Overlap', WP_Swiper_Settings::fields()['collage_order']['help'] );
		$this->assertStringContainsString( 'Overlap', WP_Swiper_Settings::fields()['collage_rotate']['help'] );
		$this->assertSame( 'overlap', WP_Swiper_Settings::sanitize( array( 'effect' => 'collage', 'collage_layout' => 'overlap' ) )['collage_layout'] );
	}

	public function test_its_icon_takes_the_tile_color() {
		$svg = file_get_contents( WP_SWIPER_DIR . 'assets/images/collage/overlap.svg' );
		$this->assertStringContainsString( 'fill="currentColor"', $svg );
		// Illustrator's own classes would land in the editor's page.
		$this->assertStringNotContainsString( '<style', $svg );
		$this->assertStringNotContainsString( 'class=', $svg );
		$this->assertFileDoesNotExist( WP_SWIPER_DIR . 'Overlap.svg' );
	}

	public function test_sanitize_keeps_each_place_layer() {
		// Five numbers: the layer a whole number 0–99, no trailing zeros.
		$this->assertSame( '1.8;0.1,0.05,0.5,0.4,1;0,0,0.3,0.45,0', WP_Swiper_Settings::sanitize_freeform( '1.80;0.1,0.05,0.5000,0.4,1;0,0,0.3,0.45,00', true ) );
		// Four: as it comes (collage.js gives the places their layers in order).
		$this->assertSame( '2;0,0,1,1', WP_Swiper_Settings::sanitize_freeform( '2;0,0,1,1', true ) );
		$this->assertSame( '2;0,0,1,1;0.5,0.5,1,1,99', WP_Swiper_Settings::sanitize_freeform( '2;0,0,1,1;0.5,0.5,1,1,99', true ) );
		foreach ( array( '2;0,0,1,1,-1', '2;0,0,1,1,1.5', '2;0,0,1,1,100', '2;0,0,1,1,a', '2;0,0,1,1,1,1', '2;0,0,1,1,' ) as $bad ) {
			$this->assertSame( '', WP_Swiper_Settings::sanitize_freeform( $bad, true ), $bad );
		}
		// Freeform has no layers: five numbers are not one of its places.
		$this->assertSame( '', WP_Swiper_Settings::sanitize_freeform( '2;0,0,1,1,1' ) );
	}

	public function test_sanitize_template_cuts_keeps_overlap() {
		$this->assertSame( 'overlap:1.5;0,0,0.5,0.5,1;0.2,0.2,0.5,0.5,0', WP_Swiper_Settings::sanitize_template_cuts( 'overlap:1.50;0,0,0.5,0.5,1;0.2,0.2,0.5,0.5,0' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_template_cuts( 'overlap:1x1;R1x1' ) );
		$this->assertSame( '', WP_Swiper_Settings::sanitize_template_cuts( 'freeform:1.5;0,0,0.5,0.5,1' ) );

		$clean = WP_Swiper_Settings::sanitize(
			array(
				'effect'              => 'collage',
				'collage_layout'      => 'overlap',
				'collage_cuts'        => 'overlap:1.5;0,0,0.5,0.5,1;0.2,0.2,0.5,0.5,0',
				'collage_cuts_mobile' => 'overlap:bad',
			)
		);
		$this->assertSame( 'overlap:1.5;0,0,0.5,0.5,1;0.2,0.2,0.5,0.5,0', $clean['collage_cuts'] );
		$this->assertSame( '', $clean['collage_cuts_mobile'] );
	}

	public function test_the_config_has_its_layout_per_device() {
		$config = $this->config(
			$this->render(
				array(
					'effect'              => 'collage',
					'collage_layout'      => 'overlap',
					'collage_count'       => 2,
					'collage_cuts'        => 'overlap:1.6;0.1,0.1,0.6,0.8,1;0.5,0.2,0.5,0.5,0',
					'collage_cuts_tablet' => '',
				),
				$this->slides( 3 )
			),
			'data-wp-swiper-collage'
		);
		$this->assertSame( 'overlap', $config['layout']['desktop'] );
		$this->assertSame( 'overlap:1.6;0.1,0.1,0.6,0.8,1;0.5,0.2,0.5,0.5,0', $config['cuts']['tablet'] );
	}

	public function test_content_in_a_place_is_one_of_its_places() {
		$html = $this->render(
			array(
				'effect'               => 'collage',
				'collage_layout'       => 'overlap',
				'collage_content'      => true,
				'collage_content_text' => '<p>Hello</p>',
			),
			$this->slides( 3 )
		);
		$this->assertStringContainsString( 'wp-swiper-collage__content-pool', $html );
		$this->assertTrue( $this->config( $html, 'data-wp-swiper-collage' )['content'] !== false );
	}

	public function test_the_preview_says_how_it_is_set_by_hand() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->slider( $this->slides( 2 ), array( 'effect' => 'collage', 'collage_layout' => 'overlap' ) );
		ob_start();
		WP_Swiper_Preview::render_panel( get_post( $id ) );
		$html = ob_get_clean();
		$this->assertSame( 3, substr_count( $html, '<p class="description wp-swiper-preview__hint" data-effect="overlap" hidden>' ) );
		$this->assertStringContainsString( 'the photo moved goes on top', $html );
		$this->assertStringContainsString( 'send it under the others', $html );
		$this->assertArrayHasKey( 'sendBack', WP_Swiper_Preview::preview_words() );
	}

	public function test_the_notes_name_it() {
		$collage = implode( ' ', WP_Swiper_Settings::effect_notes()['effects']['collage'] );
		$this->assertStringContainsString( 'Overlap', $collage );
	}

	public function test_scripts_lay_it_out_as_freeform_on_layers() {
		$collage = file_get_contents( WP_SWIPER_DIR . 'assets/js/collage.js' );
		$this->assertStringContainsString( "var FREE_STYLES = [ 'freeform', 'overlap' ];", $collage );
		$this->assertStringContainsString( "var TURNED_IN_PLACE = [ 'scatter', 'freeform', 'overlap' ];", $collage );
		$this->assertStringContainsString( 'cell.style.zIndex', $collage );
		$layouts = file_get_contents( WP_SWIPER_DIR . 'assets/js/collage-layouts.js' );
		$this->assertStringContainsString( 'function freeLayers(', $layouts );
		$this->assertStringContainsString( 'freeLayers: freeLayers', $layouts );
		// The editor stores the layout after its own style.
		$this->assertStringContainsString( "data.style === 'overlap' ? 'overlap:' : 'freeform:'", $this->admin_js() );
	}
}
