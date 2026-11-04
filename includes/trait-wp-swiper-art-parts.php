<?php
/**
 * The pieces of Art's markup render() puts together: Text mask's config,
 * the background's fit, the photos' order, the opening and the middle's
 * content, the Height, captions, border and each photo. Used by
 * WP_Swiper_Art, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Art_Parts {

	/**
	 * Text mask, for art.js text(): the letters (its windows, in order), where
	 * the spaces were (a wider gap before that letter), the font, the
	 * letters' height at most per device (% of the stage's), and the strips:
	 * how their lengths go, the shortest and longest (% of the height under
	 * the letters; Shortest at most Longest), and a number for "Different
	 * lengths" that stays the same for the word (the same strips each time).
	 *
	 * @param array $settings Slider settings.
	 * @return array
	 */
	public static function text_config( $settings ) {
		$word   = isset( $settings['text_mask_title'] ) ? (string) $settings['text_mask_title'] : '';
		$chars  = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
		$spaces = array();
		$index  = 0;
		foreach ( is_array( $chars ) ? $chars : array() as $char ) {
			if ( '' === trim( $char ) ) {
				// Before the next letter (one or more spaces: one wider gap).
				$spaces[ $index ] = 1;
			} else {
				$index++;
			}
		}
		$family = 'name' === $settings['text_mask_font'] ? WP_Swiper_Fonts::sanitize( $settings['text_mask_font_name'] ) : '';
		$min    = max( 0, min( 100, (int) $settings['text_mask_strip_min'] ) );
		$max = max( 0, min( 100, (int) $settings['text_mask_strip_max'] ) );
		return array(
			'letters' => self::letters( $settings ),
			'spaces'  => array_values( array_map( 'intval', array_keys( $spaces ) ) ),
			// "By name" with a name (none: the theme's headings).
			'font'    => in_array( $settings['text_mask_font'], array( 'heading', 'serif', 'sans' ), true ) || ( 'name' === $settings['text_mask_font'] && '' !== $family ) ? $settings['text_mask_font'] : 'heading',
			'family'  => $family,
			'weight'  => in_array( (string) $settings['text_mask_weight'], array( '400', '700', '900' ), true ) ? (int) $settings['text_mask_weight'] : 700,
			'size'    => self::per_device( $settings, 'text_mask_size', 10, 70 ),
			// Per device: Letter size Auto (as wide as the word can be), else its %.
			'auto'    => array_map(
				function ( $unit ) {
					return '%' !== $unit;
				},
				WP_Swiper_Settings::responsive( $settings, 'text_mask_size_unit' )
			),
			'stretch' => ! empty( $settings['text_mask_stretch'] ),
			'direction' => in_array( $settings['text_mask_direction'], array( 'down', 'right', 'left' ), true ) ? $settings['text_mask_direction'] : 'down',
			'width'   => 'letter' === $settings['text_mask_strip_width'] ? 'letter' : 'same',
			// Strips into letters: a share of each letter's depth (0-0.3).
			'join'    => max( 0, min( 30, (int) $settings['text_mask_join'] ) ) / 100,
			// Rounded corners: the strips' radius per device (off: 0), and which end.
			'radius'  => empty( $settings['text_mask_rounded'] ) ? array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), 0 ) : self::per_device( $settings, 'text_mask_radius', 0, 200 ),
			'corners' => 'both' === $settings['text_mask_corners'] ? 'both' : 'end',
			'strips'  => in_array( $settings['text_mask_strips'], array( 'random', 'equal', 'middle', 'sides' ), true ) ? $settings['text_mask_strips'] : 'random',
			'min'     => min( $min, $max ),
			'max'     => max( $min, $max ),
			'seed'    => (int) sprintf( '%u', crc32( $word ) ) % 100000,
		);
	}

	/**
	 * A ring or SVG shape as a background (art.css): per device its width in
	 * the block (container units of the block: the largest that fits, or the
	 * smallest that fills it, its proportions kept), its proportions, and
	 * where it sits (a share of the room left, or cut off, each way).
	 *
	 * @param array $settings Slider settings.
	 * @param array $ratios   device => width / height (none: 1, a ring).
	 * @return string[] CSS variables.
	 */
	public static function background_fit( $settings, $ratios ) {
		$sizes     = WP_Swiper_Settings::responsive( $settings, 'art_bg_size' );
		$positions = WP_Swiper_Settings::responsive( $settings, 'art_bg_position' );
		$share     = array(
			'left'   => 0,
			'top'    => 0,
			'center' => 0.5,
			'right'  => 1,
			'bottom' => 1,
		);
		$style     = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			$ratio    = isset( $ratios[ $device ] ) && (float) $ratios[ $device ] > 0 ? round( (float) $ratios[ $device ], 4 ) : 1;
			$fit      = 'cover' === $sizes[ $device ] ? 'max' : 'min';
			$place    = explode( ' ', (string) $positions[ $device ] ) + array( 'center', 'center' );
			$x        = isset( $share[ $place[0] ] ) ? $share[ $place[0] ] : 0.5;
			$y        = isset( $share[ $place[1] ] ) ? $share[ $place[1] ] : 0.5;
			$suffix   = WP_Swiper_Render::SUFFIX[ $device ];
			$style[]  = '--wp-swiper-art-bg-w' . $suffix . ':' . $fit . '(100cqw, 100cqh * ' . $ratio . ')';
			$style[]  = '--wp-swiper-art-bg-ratio' . $suffix . ':' . $ratio;
			$style[]  = '--wp-swiper-art-bg-x' . $suffix . ':' . $x;
			$style[]  = '--wp-swiper-art-bg-y' . $suffix . ':' . $y;
		}
		return $style;
	}

	/**
	 * The order of the photos per device as slide indexes: those of the order
	 * set there (by photo ID; a photo twice in the slides: its first slide),
	 * then the others in the slides' order; false where none is set.
	 *
	 * @param array $settings Slider settings.
	 * @param array $slides   The slides shown (images).
	 * @return array device => int[] | false
	 */
	public static function orders( $settings, $slides ) {
		$orders = array();
		foreach ( WP_Swiper_Settings::responsive( $settings, 'art_order' ) as $device => $value ) {
			$value = WP_Swiper_Settings::sanitize_order( $value );
			if ( '' === $value ) {
				$orders[ $device ] = false;
				continue;
			}
			$used = array();
			foreach ( explode( ',', $value ) as $id ) {
				foreach ( $slides as $index => $slide ) {
					if ( (int) $slide['id'] === (int) $id && ! isset( $used[ $index ] ) ) {
						$used[ $index ] = true;
						break;
					}
				}
			}
			$list = array_keys( $used );
			foreach ( array_keys( $slides ) as $index ) {
				if ( ! isset( $used[ $index ] ) ) {
					$list[] = $index;
				}
			}
			$orders[ $device ] = $used ? $list : false;
		}
		return $orders;
	}

	/**
	 * Ring: what sits in its opening, an image over the editor's text (either
	 * or both, as Ring + text's middle), hidden until art.js places it
	 * (beside the stage, not in it: the photos' shadow is not for it). The
	 * image the size that fits the largest Art; '' when neither is there.
	 *
	 * @param array $settings Slider settings.
	 * @return string Markup.
	 */
	public static function opening( $settings ) {
		$image = wp_get_attachment_image( (int) $settings['art_opening_image'], 'large', false, array( 'class' => 'wp-swiper-art__opening-image', 'loading' => 'lazy' ) );
		$text  = WP_Swiper_Settings::sanitize_description( $settings['art_opening_text'] );
		if ( '' === $image && '' === $text ) {
			return '';
		}
		return '<div class="wp-swiper-art__opening" hidden><div class="wp-swiper-art__opening-inner">'
			. $image
			. ( '' === $text ? '' : '<div class="wp-swiper-art__opening-text">' . $text . '</div>' )
			. '</div></div>';
	}

	/**
	 * The opening's CSS variables per device: the image's size (% of the free
	 * space) and the text's font size; none without content.
	 *
	 * @param string $opening  The markup (opening()).
	 * @param array  $settings Slider settings.
	 * @return string[]
	 */
	private static function opening_style( $opening, $settings ) {
		if ( '' === $opening ) {
			return array();
		}
		$style  = array();
		$vars   = array(
			'art_opening_image_size' => array( '--wp-swiper-art-opening-size', '%', 10, 100 ),
			'art_opening_font_size'  => array( '--wp-swiper-art-opening-font', 'px', 8, 120 ),
		);
		$suffix = array(
			'desktop' => '',
			'tablet'  => '-tablet',
			'mobile'  => '-mobile',
		);
		foreach ( $vars as $key => $var ) {
			foreach ( self::per_device( $settings, $key, $var[2], $var[3] ) as $device => $value ) {
				$style[] = $var[0] . $suffix[ $device ] . ':' . $value . $var[1];
			}
		}
		return $style;
	}

	/**
	 * The grids: the stage's height per device (art.css, grid_heights()).
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	private static function height_style( $settings ) {
		$style = array();
		foreach ( self::grid_heights( $settings ) as $device => $height ) {
			$style[] = '--wp-swiper-art-height' . WP_Swiper_Render::SUFFIX[ $device ] . ':' . $height;
		}
		return $style;
	}

	/**
	 * Ring + text: what is in the middle circle, an image over the editor's
	 * text (either or both), hidden until art.js places it over the circle
	 * (beside the stage, as the opening's: the photos' shadow is not for it);
	 * '' when neither is there.
	 *
	 * @param array $settings Slider settings.
	 * @return string Markup.
	 */
	public static function center( $settings ) {
		$image = wp_get_attachment_image( (int) $settings['art_center_image'], 'large', false, array( 'class' => 'wp-swiper-art__center-image', 'loading' => 'lazy' ) );
		$text  = WP_Swiper_Settings::sanitize_description( $settings['art_center_text'] );
		if ( '' === $image && '' === $text ) {
			return '';
		}
		return '<div class="wp-swiper-art__center" hidden><div class="wp-swiper-art__center-inner">'
			. $image
			. ( '' === $text ? '' : '<div class="wp-swiper-art__center-text">' . $text . '</div>' )
			. '</div></div>';
	}

	/**
	 * The middle's CSS variables per device: the image's size (% of the width
	 * inside the circle), the font size and the padding; its background.
	 * None without content.
	 *
	 * @param string $center   The markup (center()).
	 * @param array  $settings Slider settings.
	 * @return string[]
	 */
	private static function center_style( $center, $settings ) {
		if ( '' === $center ) {
			return array();
		}
		$style = array();
		$vars  = array(
			'art_center_image_size' => array( '--wp-swiper-art-center-image', '%', 10, 100 ),
			'art_center_font_size'  => array( '--wp-swiper-art-center-font', 'px', 8, 120 ),
			'art_center_padding'    => array( '--wp-swiper-art-center-padding', 'px', 0, 200 ),
		);
		foreach ( $vars as $key => $var ) {
			foreach ( self::per_device( $settings, $key, $var[2], $var[3] ) as $device => $value ) {
				$style[] = $var[0] . WP_Swiper_Render::SUFFIX[ $device ] . ':' . $value . $var[1];
			}
		}
		$bg = WP_Swiper_Settings::sanitize_slide_color( (string) $settings['art_center_bg'] );
		if ( '' !== $bg ) {
			$style[] = '--wp-swiper-art-center-bg:' . $bg;
		}
		return $style;
	}

	/**
	 * The captions' look (the Caption page): a set weight, the text shadow and
	 * the background: none, the whole photo darkened (Solid) or darkened from
	 * its caption outwards (Gradient). Vertical text does not apply to Art.
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	private static function caption_classes( $settings ) {
		return array(
			// A weight saved for the whole slider (until 2.8); each caption's on itself (photo()).
			'' !== WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ) ? 'wp-swiper-art--caption-weight' : '',
			! $settings['caption_bg'] ? 'wp-swiper-art--caption-nobg' : ( 'solid' === $settings['caption_bg_style'] ? 'wp-swiper-art--caption-solid' : '' ),
			$settings['caption_shadow'] ? 'wp-swiper-art--caption-shadow' : '',
		);
	}

	/**
	 * The captions' size per device, color, background and weight: the same
	 * variables as a slider's (art.css uses them).
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	private static function caption_style( $settings ) {
		$style = array();
		// The Caption page's size for every photo; one with its own sets it on
		// its caption (photo()).
		$style = array_merge( $style, WP_Swiper_Settings::font_size_vars( WP_Swiper_Settings::caption_sizes( $settings ), 'wp-swiper-caption-size' ) );
		$style[] = '--wp-swiper-caption-color:' . $settings['caption_color'];
		if ( $settings['caption_bg'] ) {
			list( $from, $to ) = WP_Swiper_Settings::caption_bg_colors( $settings );
			$style[]           = '--wp-swiper-caption-bg-from:' . $from;
			$style[]           = '--wp-swiper-caption-bg-to:' . $to;
		}
		$style = array_merge( $style, WP_Swiper_Settings::font_weight_vars( WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ), 'wp-swiper-caption-weight' ) );
		return $style;
	}

	/**
	 * Image border: its width per device (0: none there) and its color (art.css).
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	private static function border_style( $settings ) {
		if ( ! $settings['art_border'] ) {
			return array();
		}
		$style = array();
		foreach ( self::per_device( $settings, 'art_border_width', 0, 50 ) as $device => $width ) {
			$style[] = '--wp-swiper-art-border-width' . WP_Swiper_Render::SUFFIX[ $device ] . ':' . $width . 'px';
		}
		$color   = sanitize_hex_color( $settings['art_border_color'] );
		$style[] = '--wp-swiper-art-border-color:' . ( $color ? $color : '#ffffff' );
		return $style;
	}

	/**
	 * One photo in the pool (art.js copies it into its piece), linked when
	 * its slide has a link, with its caption (art.js sets where in the
	 * piece's shape the text sits).
	 *
	 * @param array $slide    Slide.
	 * @param int   $index    Its slide index (0: the first).
	 * @param array $settings Slider settings.
	 */
	private static function photo( $slide, $index, $settings ) {
		// The full size only, no smaller copies to pick from (srcset): a photo
		// is cropped to its shape, often drawn wider than its piece, and the
		// browser's guess from the piece made it blurry first, sharp later.
		add_filter( 'wp_calculate_image_srcset', '__return_false', 99 );
		$image = wp_get_attachment_image(
			$slide['id'],
			'full',
			false,
			array(
				'class'    => 'wp-swiper-art__image',
				'loading'  => 0 === $index ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);
		remove_filter( 'wp_calculate_image_srcset', '__return_false', 99 );
		if ( ! empty( $slide['link'] ) ) {
			$image = sprintf(
				'<a class="wp-swiper-art__link" href="%s"%s>%s</a>',
				esc_url( $slide['link'] ),
				empty( $slide['new_tab'] ) ? '' : ' target="_blank" rel="noopener"',
				$image
			);
		}
		// Sanitized again: saved before, or posted by the preview.
		$caption = empty( $slide['caption'] ) ? '' : WP_Swiper_Settings::sanitize_caption( $slide['caption'] );
		if ( '' !== $caption ) {
			// Outer block: the background over the photo; inner one: typography,
			// its lines paragraphs or headings (caption_markup()).
			// Its own font size (the Slide info window), per device.
			$size    = WP_Swiper_Settings::own_font_size_vars( isset( $slide['caption_size'] ) ? $slide['caption_size'] : '', 'wp-swiper-caption-size' );
			// And its own font weight (a class: none keeps the tag's).
			$weight  = WP_Swiper_Settings::font_weight_vars( isset( $slide['caption_weight'] ) ? $slide['caption_weight'] : '', 'wp-swiper-caption-weight' );
			$size    = array_merge( $size, $weight );
			// And its own color (none: the Caption page's).
			$color   = isset( $slide['caption_color'] ) ? WP_Swiper_Settings::sanitize_slide_color( $slide['caption_color'] ) : '';
			if ( '' !== $color ) {
				$size[] = '--wp-swiper-caption-color:' . $color;
			}
			$caption = sprintf( '<div class="wp-swiper-art__caption%3$s"%2$s><div class="wp-swiper-art__caption-text">%1$s</div></div>', WP_Swiper_Settings::caption_markup( $caption, $settings ), $size ? ' style="' . esc_attr( implode( ';', $size ) ) . '"' : '', $weight ? ' wp-swiper-art__caption--weight' : '' );
		}
		printf(
			'<div class="wp-swiper-art__photo" data-real="%d"%s>%s%s</div>',
			(int) $index,
			// What the lightbox shows: the image's full size.
			$settings['lightbox'] ? ' data-wp-swiper-full="' . esc_url( wp_get_attachment_image_url( $slide['id'], 'full' ) ) . '"' : '',
			$image, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup, link escaped above.
			$caption // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text sanitized above (caption_tags()), whitelisted tags.
		);
	}
}
