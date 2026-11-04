<?php
/**
 * Gradient (every effect, image_gradient): its value as stored
 * ("linear;left;#f00@0;#00f@100") read, written back and turned into CSS.
 * Used by WP_Swiper_Art, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Art_Gradient {

	/**
	 * A gradient as stored (the photos' Gradient, admin.js draws its editor):
	 * "linear;left;#ff0080@0;rgba(0,96,255,0.5)@100". Its type (linear, or
	 * radial: circle / ellipse), where it starts (linear: left, right, top,
	 * bottom) or its middle (radial: "left|center|right top|center|bottom"),
	 * then 2 to GRADIENT_STOPS colors with their positions (0-100), in order.
	 *
	 * @param string $value Stored value.
	 * @return array|null [ 'type', 'where', 'stops' => [ [ color, position ] ] ], null if not one.
	 */
	public static function parse_gradient( $value ) {
		$parts = explode( ';', (string) $value );
		if ( count( $parts ) < 4 ) {
			return null;
		}
		$type  = array_shift( $parts );
		$where = array_shift( $parts );
		if ( 'linear' === $type ) {
			if ( ! in_array( $where, array( 'left', 'right', 'top', 'bottom' ), true ) ) {
				return null;
			}
		} elseif ( in_array( $type, array( 'circle', 'ellipse' ), true ) ) {
			$at = explode( ' ', $where );
			if ( 2 !== count( $at ) || ! in_array( $at[0], array( 'left', 'center', 'right' ), true ) || ! in_array( $at[1], array( 'top', 'center', 'bottom' ), true ) ) {
				return null;
			}
		} else {
			return null;
		}
		$stops = array();
		foreach ( $parts as $part ) {
			$point = explode( '@', $part );
			if ( 2 !== count( $point ) || ! is_numeric( $point[1] ) ) {
				continue;
			}
			$color = WP_Swiper_Settings::sanitize_slide_color( $point[0] );
			if ( '' !== $color ) {
				$stops[] = array( $color, round( max( 0, min( 100, (float) $point[1] ) ), 1 ) );
			}
		}
		$stops = array_slice( $stops, 0, self::GRADIENT_STOPS );
		if ( count( $stops ) < 2 ) {
			return null;
		}
		// In order of their positions (those at one place as they came).
		$order = array_keys( $stops );
		usort(
			$order,
			function ( $a, $b ) use ( $stops ) {
				return $stops[ $a ][1] === $stops[ $b ][1] ? $a - $b : ( $stops[ $a ][1] < $stops[ $b ][1] ? -1 : 1 );
			}
		);
		return array(
			'type'  => $type,
			'where' => $where,
			'stops' => array_map(
				function ( $i ) use ( $stops ) {
					return $stops[ $i ];
				},
				$order
			),
		);
	}

	/**
	 * A gradient (parse_gradient()) as stored.
	 *
	 * @param array $gradient The gradient.
	 * @return string
	 */
	public static function gradient_value( $gradient ) {
		$points = array();
		foreach ( $gradient['stops'] as $stop ) {
			$points[] = $stop[0] . '@' . ( 0.0 === fmod( $stop[1], 1 ) ? (int) $stop[1] : $stop[1] );
		}
		return $gradient['type'] . ';' . $gradient['where'] . ';' . implode( ';', $points );
	}

	/**
	 * A stored gradient as CSS (its start "left" as WoodMart's: towards the
	 * right); '' if it is not one.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function gradient_css( $value ) {
		$gradient = self::parse_gradient( $value );
		if ( ! $gradient ) {
			return '';
		}
		$points = array();
		foreach ( $gradient['stops'] as $stop ) {
			$points[] = $stop[0] . ' ' . $stop[1] . '%';
		}
		if ( 'linear' === $gradient['type'] ) {
			$towards = array(
				'left'   => 'right',
				'right'  => 'left',
				'top'    => 'bottom',
				'bottom' => 'top',
			);
			return 'linear-gradient(to ' . $towards[ $gradient['where'] ] . ', ' . implode( ', ', $points ) . ')';
		}
		return 'radial-gradient(' . $gradient['type'] . ' at ' . $gradient['where'] . ', ' . implode( ', ', $points ) . ')';
	}
}
