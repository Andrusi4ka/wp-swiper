<?php
/**
 * Art's sizes per device: how many photos each Style shows, its Height and
 * its units, the grids' heights and the block's. Used by WP_Swiper_Art, so
 * `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Art_Sizes {

	/**
	 * A per-device number setting as each device uses it (tablet and mobile
	 * empty: the larger screen's), within its limits.
	 *
	 * @param array  $settings Slider settings.
	 * @param string $key      Setting.
	 * @param int    $min      Lowest.
	 * @param int    $max      Highest.
	 * @return array device => int
	 */
	public static function per_device( $settings, $key, $min, $max ) {
		return array_map(
			function ( $value ) use ( $min, $max ) {
				return max( $min, min( $max, (int) $value ) );
			},
			WP_Swiper_Settings::responsive( $settings, $key )
		);
	}

	/**
	 * How many photos a Style shows on each device (Ring: the ring's, and
	 * one in the middle; Ring + text: the ring's; Grid + circle: its Photos in
	 * the grid and the circle's, art.js leaves out the cells it covers; Grid +
	 * circle + text: the grid's).
	 *
	 * @param array $settings Slider settings.
	 * @return array device => int
	 */
	public static function counts( $settings ) {
		$style = self::style_of( $settings );
		// Text mask: its letters, on every device.
		if ( 'text' === $style ) {
			return array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), count( self::letters( $settings ) ) );
		}
		// SVG shape: its windows, on every device.
		if ( 'shape' === $style ) {
			return array_map(
				function ( $shape ) {
					return $shape ? count( $shape['shapes'] ) : 1;
				},
				self::shapes( $settings )
			);
		}
		// Grid shapes: its shapes (one layout for every device).
		if ( 'grid-shapes' === $style ) {
			return array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), max( 1, self::cells( $settings )['count'] ) );
		}
		if ( self::is_wide( $settings ) ) {
			$middle = self::has_middle_photo( $style ) ? 1 : 0;
			return array_map(
				function ( $photos ) use ( $middle ) {
					return $photos + $middle;
				},
				self::per_device( $settings, 'art_grid_count', 2, 36 )
			);
		}
		$middle = self::has_middle_photo( $style ) ? 1 : 0;
		return array_map(
			function ( $around ) use ( $middle ) {
				return $middle + $around;
			},
			self::per_device( $settings, 'art_ring_count', 3, 16 )
		);
	}

	/**
	 * Height's unit on each device (tablet and mobile empty: the larger screen's).
	 *
	 * @param array $settings Slider settings.
	 * @return array device => 'px' | 'vh' | '%' | 'auto'
	 */
	public static function height_units( $settings ) {
		return array_map(
			function ( $unit ) {
				return in_array( $unit, array( 'px', 'vh', '%', 'auto' ), true ) ? $unit : 'px';
			},
			WP_Swiper_Settings::responsive( $settings, 'art_height_unit' )
		);
	}

	/**
	 * The stage's width on each device as CSS for a ring or an SVG shape
	 * (art.css keeps it within the column): its Height times its proportions
	 * (a ring 1, a shape its drawing's, `ratios`), px from 160, vh up to 100.
	 * % and Auto: the column's width, art.js makes it smaller to fit the
	 * parent's height or the column's free height.
	 *
	 * @param array $settings Slider settings.
	 * @param array $ratios   device => width / height (1 for a ring).
	 * @return array device => string
	 */
	public static function sizes( $settings, $ratios = array() ) {
		$values = WP_Swiper_Settings::responsive( $settings, 'art_height' );
		$sizes  = array();
		foreach ( self::height_units( $settings ) as $device => $unit ) {
			$ratio = isset( $ratios[ $device ] ) && (float) $ratios[ $device ] > 0 ? (float) $ratios[ $device ] : 1;
			$value = max( 1, (int) $values[ $device ] );
			if ( 'px' === $unit ) {
				$sizes[ $device ] = round( max( 160, min( 2000, $value ) ) * $ratio, 2 ) . 'px';
			} elseif ( 'vh' === $unit ) {
				$sizes[ $device ] = round( min( 100, $value ) * $ratio, 2 ) . 'vh';
			} else {
				$sizes[ $device ] = '100%';
			}
		}
		return $sizes;
	}

	/**
	 * A grid's height on each device as CSS (art.css): px from 120, vh up to
	 * 100; % the outer block's (whose height is the % of its parent,
	 * block_heights()); Auto: art.js fills the column's free height.
	 *
	 * @param array $settings Slider settings.
	 * @return array device => string
	 */
	public static function grid_heights( $settings ) {
		$values  = WP_Swiper_Settings::responsive( $settings, 'art_height' );
		$heights = array();
		foreach ( self::height_units( $settings ) as $device => $unit ) {
			$value = max( 1, (int) $values[ $device ] );
			if ( 'px' === $unit ) {
				$heights[ $device ] = max( 120, min( 2000, $value ) ) . 'px';
			} elseif ( 'vh' === $unit ) {
				$heights[ $device ] = min( 100, $value ) . 'vh';
			} else {
				$heights[ $device ] = '%' === $unit ? '100%' : 'auto';
			}
		}
		return $heights;
	}

	/**
	 * The outer block's height on each device (art.css): Height in % there,
	 * of its parent's (as a slider's); else as tall as it is.
	 *
	 * @param array $settings Slider settings.
	 * @return array device => string
	 */
	public static function block_heights( $settings ) {
		$values  = WP_Swiper_Settings::responsive( $settings, 'art_height' );
		$heights = array();
		foreach ( self::height_units( $settings ) as $device => $unit ) {
			$heights[ $device ] = '%' === $unit ? min( 100, max( 1, (int) $values[ $device ] ) ) . '%' : 'auto';
		}
		return $heights;
	}

	/**
	 * How many pieces the markup has: as many as the device with the most
	 * shows (art.js hides those another device leaves out).
	 *
	 * @param array $settings Slider settings.
	 * @return int
	 */
	public static function count( $settings ) {
		return max( self::counts( $settings ) );
	}
}
