<?php
/**
 * WP_Swiper_Settings: the boxes of four values (2026-11-10, as WP Banner's,
 * the user's choice): a field with `box` (its four keys: the top / top
 * left first, then clockwise) and `box_link` (set together, the default:
 * one number, as before). Image border's sides, Rounded corners' radii.
 *
 * @package WP_Swiper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Settings_Box {

	/**
	 * The boxes: their first key => [ the four keys, the link's key ].
	 *
	 * @return array
	 */
	public static function boxes() {
		$boxes = array();
		foreach ( self::fields() as $key => $field ) {
			if ( ! empty( $field['box'] ) ) {
				$boxes[ $key ] = array( $field['box'], $field['box_link'] );
			}
		}
		return $boxes;
	}

	/**
	 * Whether a key is a box's link.
	 *
	 * @param string $key Field name.
	 * @return bool
	 */
	public static function is_box_link( $key ) {
		foreach ( self::boxes() as $box ) {
			if ( $box[1] === $key ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A box's four values on each device, as drawn: tablet and mobile empty
	 * take the larger screen's; linked, the first one on every side.
	 *
	 * @param array  $settings Slider settings.
	 * @param string $first    The box's first key (border_radius, image_border_width).
	 * @return array device => int[4]
	 */
	public static function box_values( $settings, $first ) {
		$boxes = self::boxes();
		$keys  = $boxes[ $first ][0];
		if ( ! isset( $settings[ $boxes[ $first ][1] ] ) || $settings[ $boxes[ $first ][1] ] ) {
			$keys = array_fill( 0, 4, $first );
		}
		$values = array();
		foreach ( $keys as $i => $key ) {
			foreach ( self::responsive( $settings, $key ) as $device => $value ) {
				$values[ $device ][ $i ] = max( 0, (int) $value );
			}
		}
		return $values;
	}

	/**
	 * Whether a box has a value of its own on a device (tablet, mobile).
	 *
	 * @param array  $settings Slider settings.
	 * @param string $first    The box's first key.
	 * @param string $device   tablet or mobile.
	 * @return bool
	 */
	public static function box_own( $settings, $first, $device ) {
		$boxes = self::boxes();
		$keys  = ! isset( $settings[ $boxes[ $first ][1] ] ) || $settings[ $boxes[ $first ][1] ] ? array( $first ) : $boxes[ $first ][0];
		foreach ( $keys as $key ) {
			if ( isset( $settings[ $key . '_' . $device ] ) && '' !== $settings[ $key . '_' . $device ] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Four values as CSS: one length when they are the same, else four
	 * (border-width and border-radius take either).
	 *
	 * @param int[] $values Four whole px.
	 * @return string "12px" or "12px 0px 12px 0px".
	 */
	public static function box_css( $values ) {
		$values = array_map( 'intval', array_values( $values ) );
		if ( count( array_unique( $values ) ) === 1 ) {
			return $values[0] . 'px';
		}
		return implode(
			' ',
			array_map(
				function ( $value ) {
					return $value . 'px';
				},
				$values
			)
		);
	}
}
