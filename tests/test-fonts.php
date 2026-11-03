<?php
/**
 * Font names (WP_Swiper_Fonts): what is suggested where a font is typed, and
 * what is kept of one typed (it goes into CSS). Text mask's Font "By name".
 */
class Test_WP_Swiper_Fonts extends WP_Swiper_TestCase {

	public function tear_down() {
		delete_option( WP_Swiper_Fonts::WOODMART_OPTION );
		parent::tear_down();
	}

	public function test_a_typed_name_is_kept_safe_for_css() {
		$this->assertSame( 'Playfair Display', WP_Swiper_Fonts::sanitize( '  Playfair   Display ' ) );
		$this->assertSame( '"Open Sans", sans-serif', WP_Swiper_Fonts::sanitize( '"Open Sans", sans-serif' ) );
		// Nothing that could end the CSS value or add a rule.
		$this->assertSame( 'Arialcolorred', WP_Swiper_Fonts::sanitize( 'Arial;color:red' ) );
		$this->assertSame( 'Astylescriptx', WP_Swiper_Fonts::sanitize( 'A</style><script>x' ) );
		$this->assertSame( 'Ax', WP_Swiper_Fonts::sanitize( 'A{}()<>\\x' ) );
		$this->assertSame( 'Fira Sans Ёлка', WP_Swiper_Fonts::sanitize( 'Fira Sans Ёлка' ) );
		$this->assertSame( 100, mb_strlen( WP_Swiper_Fonts::sanitize( str_repeat( 'a', 150 ) ) ) );
		$this->assertSame( '', WP_Swiper_Fonts::sanitize( ' ;; ' ) );
	}

	public function test_woodmart_fonts_first_when_its_settings_are_there() {
		// No WoodMart: none of its, the others still.
		$this->assertSame( array(), WP_Swiper_Fonts::woodmart() );
		$values = wp_list_pluck( WP_Swiper_Fonts::suggestions(), 'value' );
		$this->assertContains( 'Georgia', $values );

		update_option(
			WP_Swiper_Fonts::WOODMART_OPTION,
			array(
				'primary-font'   => array( array( 'google' => 'true', 'font-family' => 'Google Sans Flex', 'font-weight' => '400' ) ),
				'text-font'      => array( array( 'font-family' => 'System font stack' ) ),
				'secondary-font' => array( array( 'font-family' => 'var(--wd-text-font)' ) ),
				'title-font'     => array( array( 'font-family' => 'Georgia' ) ),
				'header-color'   => '#fff',
				'broken'         => array( 'font-family' => 'Not a list' ),
				// Every rule of Advanced typography, not only the first.
				'advanced_typography' => array(
					array( 'custom-selector' => 'h1.title', 'font-family' => 'Google Sans Flex' ),
					array( 'custom-selector' => '.wp-swiper-art__word', 'font-family' => 'Playfair Display' ),
					array( 'custom-selector' => '.x', 'font-family' => '' ),
				),
				// Fonts uploaded to WoodMart (its empty template row kept beside them).
				'multi_custom_fonts'  => array(
					'{{index}}' => array( 'font-name' => '' ),
					array( 'font-name' => 'My Brand', 'font-weight' => '700' ),
				),
			)
		);
		$this->assertSame(
			array(
				array( 'value' => 'Google Sans Flex', 'group' => 'WoodMart', 'label' => 'Primary font' ),
				array( 'value' => 'Georgia', 'group' => 'WoodMart', 'label' => 'Title font' ),
				array( 'value' => 'Google Sans Flex', 'group' => 'WoodMart', 'label' => 'Advanced typography' ),
				array( 'value' => 'Playfair Display', 'group' => 'WoodMart', 'label' => 'Advanced typography' ),
				array( 'value' => 'My Brand', 'group' => 'WoodMart', 'label' => 'Custom fonts' ),
			),
			WP_Swiper_Fonts::woodmart()
		);
		$values = wp_list_pluck( WP_Swiper_Fonts::suggestions(), 'value' );
		$this->assertSame( array( 'Google Sans Flex', 'Georgia', 'Playfair Display', 'My Brand' ), array_slice( $values, 0, 4 ) );
		$suggestions = WP_Swiper_Fonts::suggestions();
		$this->assertSame( 'Google Sans Flex', $suggestions[0]['value'] );
		// Each font once (Georgia: the theme's, not again as a computer's).
		$values = wp_list_pluck( $suggestions, 'value' );
		$this->assertSame( count( $values ), count( array_unique( array_map( 'strtolower', $values ) ) ) );
		$this->assertSame( array( 'WoodMart', 'Title font' ), array( $suggestions[ array_search( 'Georgia', $values, true ) ]['group'], $suggestions[ array_search( 'Georgia', $values, true ) ]['label'] ) );
	}

	public function test_text_mask_font_by_name() {
		$settings = WP_Swiper_Settings::defaults();
		$settings = array_merge( $settings, array( 'effect' => 'text-mask', 'text_mask_font' => 'name', 'text_mask_font_name' => 'Playfair Display' ) );
		$text     = WP_Swiper_Art::text_config( $settings );
		$this->assertSame( array( 'name', 'Playfair Display' ), array( $text['font'], $text['family'] ) );
		// No name: the theme's headings.
		$text = WP_Swiper_Art::text_config( array_merge( $settings, array( 'text_mask_font_name' => '' ) ) );
		$this->assertSame( array( 'heading', '' ), array( $text['font'], $text['family'] ) );
		// Another Font: its name is not used.
		$text = WP_Swiper_Art::text_config( array_merge( $settings, array( 'text_mask_font' => 'serif' ) ) );
		$this->assertSame( array( 'serif', '' ), array( $text['font'], $text['family'] ) );
		// Saved as typed, safe.
		$clean = WP_Swiper_Settings::sanitize( array( 'effect' => 'text-mask', 'text_mask_font' => 'name', 'text_mask_font_name' => 'Lora;}' ) );
		$this->assertSame( array( 'name', 'Lora' ), array( $clean['text_mask_font'], $clean['text_mask_font_name'] ) );
	}

	public function test_the_field_suggests_them() {
		update_option( WP_Swiper_Fonts::WOODMART_OPTION, array( 'primary-font' => array( array( 'font-family' => 'Google Sans Flex' ) ) ) );
		$id = $this->slider( null, array( 'effect' => 'text-mask' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();
		$this->assertSame( 1, preg_match( '/data-field="text_mask_font_name" data-effects="text-mask">/', $html ) );
		// A combobox with its list (font-picker.js draws it from data-fonts).
		$this->assertStringContainsString( 'role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="wp-swiper-text_mask_font_name-list"', $html );
		$this->assertStringContainsString( '<ul class="wp-swiper-font__list" id="wp-swiper-text_mask_font_name-list" role="listbox"', $html );
		$this->assertSame( 1, preg_match( '/<div class="wp-swiper-font" data-fonts="([^"]*)">/', $html, $match ) );
		$fonts = json_decode( html_entity_decode( $match[1], ENT_QUOTES ), true );
		$this->assertSame( array( 'value' => 'Google Sans Flex', 'group' => 'WoodMart', 'label' => 'Primary font' ), $fonts[0] );
		$this->assertStringNotContainsString( '<datalist', $html );
	}
}
