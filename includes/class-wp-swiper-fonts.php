<?php
/**
 * Font names to suggest where a font is typed (Text mask's Font "By name"):
 * those the site already loads, so that nothing new is fetched from Google
 * or elsewhere. The theme's typography if it is WoodMart (read only when its
 * settings are there: the plugin does not need it), the fonts WordPress
 * knows (theme.json, the Font Library), then fonts every computer has.
 * Shown in groups by assets/js/font-picker.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Fonts {

	// WoodMart's settings (its typography: "primary-font" => [ [ "font-family" => … ] ]).
	const WOODMART_OPTION = 'xts-woodmart-options';

	// Fonts on any computer.
	const SYSTEM = array( 'Georgia', 'Times New Roman', 'Arial', 'Arial Black', 'Helvetica', 'Verdana', 'Tahoma', 'Trebuchet MS', 'Impact' );

	/**
	 * The suggestions, each font once (where it first comes), the theme's first.
	 *
	 * @return array[] [ 'value' => font family, 'group' => where it comes from, 'label' => which setting ('' none) ]
	 */
	public static function suggestions() {
		$list = array_merge( self::woodmart(), self::wordpress() );
		foreach ( self::SYSTEM as $family ) {
			$list[] = array(
				'value' => $family,
				'group' => __( 'On every computer', 'wp-swiper' ),
				'label' => '',
			);
		}
		$seen = array();
		$out  = array();
		foreach ( $list as $item ) {
			$key = strtolower( $item['value'] );
			if ( '' === $item['value'] || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $item;
		}
		return $out;
	}

	/**
	 * The fonts WoodMart loads: those of its typography settings (Primary
	 * font, Text font…, every rule of Advanced typography), named ones only
	 * (not one that points to another, "var(--wd-title-font)", nor its
	 * "System font stack"); and the fonts uploaded to it (Custom fonts).
	 *
	 * @return array[]
	 */
	public static function woodmart() {
		$options = get_option( self::WOODMART_OPTION );
		if ( ! is_array( $options ) ) {
			return array();
		}
		$out = array();
		foreach ( $options as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			// Custom fonts: each its name; the others a list of rules, each its font.
			$custom = 'multi_custom_fonts' === $key;
			$name   = $custom ? 'font-name' : 'font-family';
			$label  = $custom ? __( 'Custom fonts', 'wp-swiper' ) : ucfirst( str_replace( array( '-', '_' ), ' ', (string) $key ) );
			foreach ( $value as $rule ) {
				$family = is_array( $rule ) && isset( $rule[ $name ] ) && is_string( $rule[ $name ] ) ? self::sanitize( $rule[ $name ] ) : '';
				if ( '' === $family || 0 === stripos( $family, 'var' ) || 'system font stack' === strtolower( $family ) ) {
					continue;
				}
				$out[] = array(
					'value' => $family,
					'group' => 'WoodMart',
					'label' => $label,
				);
			}
		}
		return $out;
	}

	/**
	 * The fonts WordPress knows (theme.json, the Font Library): their names.
	 *
	 * @return array[]
	 */
	public static function wordpress() {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return array();
		}
		$origins = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		$out     = array();
		foreach ( is_array( $origins ) ? $origins : array() as $fonts ) {
			foreach ( is_array( $fonts ) ? $fonts : array() as $font ) {
				$name = is_array( $font ) && isset( $font['name'] ) ? self::sanitize( (string) $font['name'] ) : '';
				if ( '' !== $name ) {
					$out[] = array(
						'value' => $name,
						'group' => 'WordPress',
						'label' => '',
					);
				}
			}
		}
		return $out;
	}

	/**
	 * A font family as typed: names, spaces, commas and quotes only (it goes
	 * into CSS), at most 100 characters; '' for none.
	 *
	 * @param string $value Typed.
	 * @return string
	 */
	public static function sanitize( $value ) {
		$value = preg_replace( '/[^\p{L}\p{N} ,\'"_.-]/u', '', (string) $value );
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ), " ,\t" );
		return mb_substr( $value, 0, 100 );
	}
}
