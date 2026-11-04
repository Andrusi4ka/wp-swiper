<?php
/**
 * The Collage's markup and its Content in a place (an image and text,
 * shortcodes run), shared with Art's Content in a window. Used by
 * WP_Swiper_Render, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Render_Collage {

	/**
	 * Collage effect: the outer block (the slider's classes and style), an
	 * empty grid and every slide in a <template>. collage.js collage() puts
	 * the slides in the grid's places in the order of the shuffle, and swaps
	 * the ones left over in; until then nothing of them loads.
	 *
	 * @param array  $slides   Slides.
	 * @param array  $settings Slider settings.
	 * @param string $attrs    The outer block's attributes (id, classes, style...).
	 * @return string
	 */
	private static function collage( $slides, $settings, $attrs ) {
		$per_device = function ( $key ) use ( $settings ) {
			return array_map( 'intval', WP_Swiper_Settings::responsive( $settings, $key ) );
		};
		$config     = array(
			'layout'    => WP_Swiper_Settings::responsive( $settings, 'collage_layout' ),
			'count'     => $per_device( 'collage_count' ),
			'columns'   => $per_device( 'collage_columns' ),
			'rowHeight' => $per_device( 'collage_row_height' ),
			'rowExact'  => (bool) $settings['collage_row_exact'],
			'gap'       => $per_device( 'collage_gap' ),
			// Rotation per device (°, -45 to 45; collage.js orient() turns the
			// Styles laid out in the Height), and whether all of it shows (Fit)
			// or it fills the block (its edges cut off).
			'rotate'    => array_map(
				function ( $angle ) {
					return max( -45, min( 45, $angle ) );
				},
				$per_device( 'collage_rotate' )
			),
			'rotateFit' => array_map(
				function ( $fit ) {
					return 'fit' === $fit;
				},
				WP_Swiper_Settings::responsive( $settings, 'collage_rotate_fit' )
			),
			'seed'      => (int) $settings['collage_seed'],
			// The order set per device (slide indexes, false: the shuffle), and
			// each slide's photo ID, for the preview to tell a new order by.
			// Indexes as frontend.js counts them: the slides in the pool, in turn.
			'order'     => WP_Swiper_Settings::collage_orders( $settings, array_values( $slides ) ),
			'ids'       => array_map( 'intval', array_values( wp_list_pluck( $slides, 'id' ) ) ),
			// Random mosaic cuts set in the preview, per device ('' : worked out;
			// frontend.js uses them while their places match the places shown).
			'mosaic'    => array_map( array( 'WP_Swiper_Settings', 'sanitize_mosaic' ), WP_Swiper_Settings::responsive( $settings, 'collage_mosaic' ) ),
			// A template's sizes set there, per device ("feature-left:V0.7[...]";
			// collage.js uses them while the device's Style is that template).
			'cuts'      => array_map( array( 'WP_Swiper_Settings', 'sanitize_template_cuts' ), WP_Swiper_Settings::responsive( $settings, 'collage_cuts' ) ),
			'swap'      => $settings['collage_swap'] ? array(
				'delay' => (int) $settings['collage_swap_delay'],
				'pause' => (bool) $settings['collage_pause'],
			) : false,
			// Height "Auto": the collage fills the free height of its column (collage.js
			// collage() stretch()); where there is none, the grid takes fixed
			// proportions. Per device (false: none).
			'fill'      => WP_Swiper_Settings::fill_config( $settings ),
			'lightbox'  => $settings['lightbox'] ? array(
				'radius' => WP_Swiper_Settings::lightbox_image_radius( $settings ),
			) : false,
		);
		// Content in a place: its place per device (from 1, as the places are
		// laid out; collage.js keeps it there) while there is any.
		$content = self::collage_content( $settings );
		if ( '' !== $content ) {
			$config['content'] = array( 'place' => $per_device( 'collage_content_place' ) );
		}

		ob_start();
		?>
		<div<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built. ?>>
			<div class="wp-swiper-collage" data-wp-swiper-collage="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
				<div class="wp-swiper-collage__grid<?php echo $config['fill'] && $config['fill']['desktop'] ? ' wp-swiper-collage__grid--ratio' : ''; ?>"></div>
				<template class="wp-swiper-collage__pool">
					<?php
					foreach ( $slides as $index => $slide ) {
						self::render_slide( $slide, $index, $settings, false );
					}
					?>
				</template>
				<?php if ( '' !== $content ) : ?>
					<?php // On the page itself, hidden (not a <template>): collage.js moves this very node into its place, so the scripts of a shortcode in it find it, and a form keeps what was typed. ?>
					<div class="wp-swiper-collage__content-pool" hidden><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built. ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * content.js: content in a place of its own (a Collage's place, an Art
	 * window), its text fitted; registered for collage.js and art.js (WP_Swiper_Art
	 * registers its files on its own: either may come first).
	 */
	public static function register_content() {
		if ( ! wp_script_is( 'wp-swiper-content', 'registered' ) ) {
			wp_register_script( 'wp-swiper-content', WP_SWIPER_URL . 'assets/js/content.js', array(), wp_swiper_asset_version( 'assets/js/content.js' ), true );
		}
	}

	/**
	 * Collage, Content in a place: content_box() for its place; '' when it is
	 * off, the Style is Justified rows or Masonry on every device, or the
	 * collage is a background.
	 *
	 * @param array $settings Slider settings.
	 * @return string Markup.
	 */
	public static function collage_content( $settings ) {
		if ( empty( $settings['collage_content'] ) || WP_Swiper_Settings::is_background( $settings ) ) {
			return '';
		}
		$styles = array_diff( WP_Swiper_Settings::responsive( $settings, 'collage_layout' ), array( 'justified', 'masonry' ) );
		return $styles ? self::content_box( $settings, 'collage_content' ) : '';
	}

	/**
	 * Content in a place of its own (a Collage's place, an Art window): an
	 * image over the editor's text (either or both) on its background, its
	 * sizes per device as CSS variables (frontend.css); '' with neither. The
	 * settings are `{$key}_image`, `_image_size`, `_text`, `_font_size`,
	 * `_padding`, `_valign` and `_bg`. Shortcodes in the text run (a form, a
	 * theme's HTML block…), once the text is sanitized: their markup is
	 * theirs; with any, the place scrolls rather than cutting it (content.js).
	 * Not inside itself (a slider whose content shows a slider whose content…).
	 *
	 * @param array  $settings Slider settings.
	 * @param string $key      The settings' prefix ("collage_content", "art_content").
	 * @return string Markup.
	 */
	public static function content_box( $settings, $key ) {
		$image = wp_get_attachment_image( (int) $settings[ $key . '_image' ], 'large', false, array( 'class' => 'wp-swiper-content__image', 'loading' => 'lazy' ) );
		$text  = WP_Swiper_Settings::sanitize_description( $settings[ $key . '_text' ] );
		if ( '' === $image && '' === $text ) {
			return '';
		}
		$scroll = false;
		if ( false !== strpos( $text, '[' ) ) {
			if ( self::$content_depth > 0 ) {
				// Inside a content already: its shortcodes left out.
				$text = strip_shortcodes( $text );
			} else {
				++self::$content_depth;
				$ran = do_shortcode( $text );
				--self::$content_depth;
				$scroll = $ran !== $text;
				$text   = $ran;
			}
		}

		$style = array();
		$vars  = array(
			'_image_size' => array( '--wp-swiper-content-image', '%', 10, 100 ),
			'_font_size'  => array( '--wp-swiper-content-font', 'px', 8, 120 ),
			'_padding'    => array( '--wp-swiper-content-padding', 'px', 0, 200 ),
		);
		foreach ( $vars as $suffix => $var ) {
			foreach ( WP_Swiper_Settings::responsive( $settings, $key . $suffix ) as $device => $value ) {
				$style[] = $var[0] . self::SUFFIX[ $device ] . ':' . max( $var[2], min( $var[3], (int) $value ) ) . $var[1];
			}
		}
		$bg = WP_Swiper_Settings::sanitize_slide_color( (string) $settings[ $key . '_bg' ] );
		if ( '' !== $bg ) {
			$style[] = '--wp-swiper-content-bg:' . $bg;
		}
		$align = in_array( $settings[ $key . '_valign' ], array( 'top', 'bottom' ), true ) ? $settings[ $key . '_valign' ] : 'center';

		return '<div class="wp-swiper-content wp-swiper-content--' . $align . ( $scroll ? ' wp-swiper-content--scroll' : '' ) . '" style="' . esc_attr( implode( ';', $style ) ) . '">'
			. '<div class="wp-swiper-content__inner">'
			. $image
			. ( '' === $text ? '' : '<div class="wp-swiper-content__text">' . $text . '</div>' )
			. '</div></div>';
	}
}
