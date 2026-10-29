<?php
/**
 * Slider settings schema: single source of truth for the admin form,
 * sanitization and the front-end config.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Settings {

	const POST_TYPE     = 'wp_swiper';
	const META_SLIDES   = '_wp_swiper_slides';
	const META_SETTINGS = '_wp_swiper_settings';

	/**
	 * Effects that always show exactly one slide.
	 */
	const SINGLE_SLIDE_EFFECTS = array( 'fade', 'cube', 'flip', 'cards', 'creative', 'thumbs', 'collage' );

	/**
	 * Effects that can be the background of their parent block ("Use as background").
	 */
	const BACKGROUND_EFFECTS = array( 'slide', 'fade', 'collage' );

	/**
	 * Effects with a main image that can zoom ("Zoom the main image"); Slide
	 * only with one slide in view or the wider active slide (is_zoom()).
	 */
	const ZOOM_EFFECTS = array( 'slide', 'fade', 'cube', 'flip', 'creative', 'cards', 'thumbs' );

	/**
	 * The collage's template Styles: places of fixed proportions (collage.js
	 * COLLAGE_TEMPLATES), resized in the preview by dragging the lines between
	 * them (collage_cuts).
	 */
	const COLLAGE_TEMPLATES = array( 'feature-left', 'feature-center', 'mosaic', 'banner' );

	/**
	 * The collage's Scattered Style: a photo in the middle and arms of photos
	 * around it (collage.js scatterLayout()), resized in the preview by
	 * dragging the photos' outer edges; its sizes kept in collage_cuts too.
	 */
	const COLLAGE_SCATTER = 'scatter';

	/**
	 * The collage's Freeform Style: each photo where it was put, as large as
	 * it was made (collage.js freeLayout(): at random until set in the
	 * preview), never over another; kept in collage_cuts too.
	 */
	const COLLAGE_FREEFORM = 'freeform';

	/**
	 * Effects whose photos can have a shadow (Image shadow): those where they
	 * stand apart, and Fade, one photo framed by it (see shadow() for the rest
	 * of the conditions).
	 */
	const SHADOW_EFFECTS = array( 'slide', 'fade', 'coverflow', 'cards', 'creative', 'collage', 'art' );

	/**
	 * Field definitions grouped into sections (includes/settings-fields.php),
	 * Art's own after them.
	 *
	 * @return array
	 */
	public static function fields() {
		$fields = require __DIR__ . '/settings-fields.php';
		// Art: its own settings, kept apart (class-wp-swiper-art.php).
		return $fields + WP_Swiper_Art::fields();
	}

	/**
	 * "Use as background": on, with an effect that can be one.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_background( $settings ) {
		return ! empty( $settings['background'] ) && in_array( $settings['effect'], self::BACKGROUND_EFFECTS, true );
	}

	/**
	 * Whether the slider shows its slides' captions: not as a background
	 * (behind a block's content, where they would get in its way).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function can_caption( $settings ) {
		return ! self::is_background( $settings );
	}

	/**
	 * Whether the slider shows its slides' descriptions (under it, the current
	 * slide's): not as a background (behind a block's content), nor without a
	 * current slide (Continuous scroll, Collage), nor beside a vertical slider.
	 *
	 * @param array $settings Slider settings (Direction as shown: render.php
	 *                        makes fading sliders horizontal).
	 * @return bool
	 */
	public static function can_describe( $settings ) {
		return 'horizontal' === $settings['direction']
			&& ! self::is_background( $settings )
			&& ! self::is_marquee( $settings )
			&& ! self::is_collage( $settings );
	}

	/**
	 * A slide's own font size (caption or description) as stored: the sizes
	 * on desktop, tablet and mobile, "20,16," (8–200 px; an empty tablet or
	 * mobile one takes the larger screen's, an empty desktop one the plugin's
	 * 16px: ",,14" is a size on mobile only). '' for none (the plugin's size).
	 *
	 * @param mixed $value Raw input: "20,16," or [ desktop, tablet, mobile ].
	 * @return string
	 */
	public static function sanitize_font_sizes( $value ) {
		$parts = is_array( $value ) ? array_values( $value ) : explode( ',', (string) $value );
		$sizes = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$size    = isset( $parts[ $i ] ) ? trim( (string) $parts[ $i ] ) : '';
			$sizes[] = '' === $size || ! is_numeric( $size ) ? '' : (string) max( 8, min( 200, (int) $size ) );
		}
		return '' === implode( '', $sizes ) ? '' : implode( ',', $sizes );
	}

	/**
	 * The CSS variables of a font size (sanitize_font_sizes()): --{name} on
	 * desktop, -tablet and -mobile where set (the CSS takes the larger
	 * screen's for the others).
	 *
	 * @param string $sizes Sizes as stored.
	 * @param string $name  The variable, e.g. "wp-swiper-caption-size".
	 * @return string[] e.g. [ '--wp-swiper-caption-size:20px' ]; none without sizes.
	 */
	public static function font_size_vars( $sizes, $name ) {
		$sizes = self::sanitize_font_sizes( $sizes );
		if ( '' === $sizes ) {
			return array();
		}
		$vars = array();
		foreach ( array_combine( array( '', '-tablet', '-mobile' ), explode( ',', $sizes ) ) as $suffix => $size ) {
			if ( '' !== $size ) {
				$vars[] = '--' . $name . $suffix . ':' . (int) $size . 'px';
			}
		}
		return $vars;
	}

	/**
	 * The CSS variables of a slide's own font size (sanitize_font_sizes()),
	 * on its caption: an empty tablet or mobile size takes the slide's own
	 * for the larger screen, so a slide's size stays its own on every screen
	 * after it; before its first size (",,14": desktop and tablet) the
	 * slider's (caption_sizes()) or the plugin's, from the block around it.
	 *
	 * @param string $sizes Sizes as stored.
	 * @param string $name  The variable, e.g. "wp-swiper-caption-size".
	 * @return string[]
	 */
	public static function own_font_size_vars( $sizes, $name ) {
		$sizes = self::sanitize_font_sizes( $sizes );
		if ( '' === $sizes ) {
			return array();
		}
		$parts = explode( ',', $sizes );
		for ( $i = 1; $i < 3; $i++ ) {
			if ( '' === $parts[ $i ] ) {
				$parts[ $i ] = $parts[ $i - 1 ];
			}
		}
		return self::font_size_vars( implode( ',', $parts ), $name );
	}

	/**
	 * The captions' font size of the whole slider (Caption page, caption_size),
	 * as stored per slide ("20,16,"); one saved with the old Font size fields
	 * (until 2.8, legacy_font_sizes()) until it is saved again. '' for none:
	 * the plugin's 16px.
	 *
	 * @param array $settings Slider settings.
	 * @return string
	 */
	public static function caption_sizes( $settings ) {
		$sizes = array();
		foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
			$sizes[] = isset( $settings[ 'caption_size' . $suffix ] ) ? (string) $settings[ 'caption_size' . $suffix ] : '';
		}
		$sizes = self::sanitize_font_sizes( $sizes );
		return '' !== $sizes ? $sizes : self::legacy_font_sizes( $settings, 'caption' );
	}

	/**
	 * A slide's own font weight (caption or description) as stored: one for
	 * every screen, "700" (100–900 in hundreds). '' for none (the tag's or the
	 * page's own). A value saved per device ("700,,400", until 2.9) keeps its
	 * desktop weight.
	 *
	 * @param mixed $value Raw input: "700", or the old "700,,400".
	 * @return string
	 */
	public static function sanitize_font_weights( $value ) {
		$parts  = is_array( $value ) ? array_values( $value ) : explode( ',', (string) $value );
		$weight = isset( $parts[0] ) ? trim( (string) $parts[0] ) : '';
		return in_array( $weight, array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ), true ) ? $weight : '';
	}

	/**
	 * A slide's own text color (caption or description) as stored: "#rrggbb"
	 * (or "#rgb"), or "rgba(r,g,b,a)" below full opacity (a full one is kept
	 * as its hex). '' for none or anything else (the Caption / Description
	 * page's color).
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	/**
	 * The background's overlay: its color ("#rrggbb" or "rgba(r,g,b,a)"), or
	 * '' for none (opacity 0, or no color).
	 *
	 * @param array $settings The slider's settings.
	 * @return string
	 */
	public static function background_overlay( $settings ) {
		$color = self::sanitize_slide_color( isset( $settings['background_overlay_color'] ) ? $settings['background_overlay_color'] : '' );
		if ( '' === $color || preg_match( '/,0\)$/', $color ) ) {
			return '';
		}
		return $color;
	}

	/**
	 * Until 3.0 the overlay's opacity was a field of its own (Overlay opacity
	 * (%), 0 = none) beside a plain color: both as one color with its opacity.
	 *
	 * @param array $saved A slider's saved settings.
	 * @return array
	 */
	public static function merge_overlay_opacity( $saved ) {
		return self::merge_opacity( $saved, 'background_overlay_opacity', 'background_overlay_color' );
	}

	/**
	 * Until 3.0 the caption background's opacity was a field of its own
	 * (Background opacity (%)) beside a plain color: one color with it now.
	 *
	 * @param array $saved A slider's saved settings.
	 * @return array
	 */
	public static function merge_caption_bg_opacity( $saved ) {
		return self::merge_opacity( $saved, 'caption_bg_opacity', 'caption_bg_color' );
	}

	/**
	 * An opacity field (%) and a plain color beside it as one color with its
	 * opacity (no color saved: black, as both had by default).
	 *
	 * @param array  $saved   A slider's saved settings.
	 * @param string $opacity The opacity's key (dropped).
	 * @param string $color   The color's key.
	 * @return array
	 */
	private static function merge_opacity( $saved, $opacity, $color ) {
		if ( ! isset( $saved[ $opacity ] ) ) {
			return $saved;
		}
		$hex = isset( $saved[ $color ] ) ? sanitize_hex_color( (string) $saved[ $color ] ) : '';
		$hex = $hex ? strtolower( $hex ) : '#000000';
		if ( 4 === strlen( $hex ) ) {
			$hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
		}
		$percent           = max( 0, min( 100, (int) $saved[ $opacity ] ) );
		list( $r, $g, $b ) = sscanf( $hex, '#%02x%02x%02x' );
		$saved[ $color ]   = self::sanitize_slide_color( sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, $percent >= 100 ? '1' : self::alpha_text( $percent / 100 ) ) );
		unset( $saved[ $opacity ] );
		return $saved;
	}

	// An opacity as CSS writes it: "0.4", a dot whatever the locale.
	private static function alpha_text( $alpha ) {
		return rtrim( rtrim( number_format( (float) $alpha, 2, '.', '' ), '0' ), '.' ) ?: '0';
	}

	/**
	 * The caption background's two ends: its color, and the same color
	 * see-through (where the gradient fades out).
	 *
	 * @param array $settings The slider's settings.
	 * @return string[] [ from, to ], as "rgba(r,g,b,a)".
	 */
	public static function caption_bg_colors( $settings ) {
		$color = self::sanitize_slide_color( isset( $settings['caption_bg_color'] ) ? $settings['caption_bg_color'] : '' );
		if ( '' === $color ) {
			$color = self::fields()['caption_bg_color']['default'];
		}
		if ( preg_match( '/^rgba\((\d+),(\d+),(\d+),([\d.]+)\)$/', $color, $m ) ) {
			list( , $r, $g, $b, $a ) = $m;
		} else {
			list( $r, $g, $b ) = sscanf( $color, '#%02x%02x%02x' );
			$a                 = 1;
		}
		return array(
			sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, self::alpha_text( $a ) ),
			sprintf( 'rgba(%d,%d,%d,0)', $r, $g, $b ),
		);
	}

	/**
	 * Rounded corners got their switch (as Image border): a slider saved
	 * before it with a radius above 0 on any device has it on, so it shows
	 * as it did; one saved without keeps it off.
	 *
	 * @param array $saved A slider's saved settings.
	 * @return array
	 */
	public static function merge_rounded( $saved ) {
		if ( ! $saved || isset( $saved['border_rounded'] ) ) {
			return $saved;
		}
		$rounded = false;
		foreach ( array( 'border_radius', 'border_radius_tablet', 'border_radius_mobile' ) as $key ) {
			if ( isset( $saved[ $key ] ) && '' !== (string) $saved[ $key ] && (int) $saved[ $key ] > 0 ) {
				$rounded = true;
			}
		}
		$saved['border_rounded'] = $rounded;
		return $saved;
	}

	/**
	 * The settings as drawn: with Rounded corners off, no radius anywhere
	 * on the slides (the lightbox has its own, lightbox_image_radius()).
	 *
	 * @param array $settings The slider's settings.
	 * @return array
	 */
	public static function apply_rounded( $settings ) {
		if ( empty( $settings['border_rounded'] ) ) {
			$settings['border_radius']        = 0;
			$settings['border_radius_tablet'] = '';
			$settings['border_radius_mobile'] = '';
		}
		return $settings;
	}

	public static function sanitize_slide_color( $value ) {
		$value = strtolower( preg_replace( '/\s+/', '', (string) $value ) );
		$hex   = sanitize_hex_color( $value );
		if ( $hex ) {
			return $hex;
		}
		if ( ! preg_match( '/^rgba\((\d{1,3}),(\d{1,3}),(\d{1,3}),(0|1|0?\.\d+|1\.0+)\)$/', $value, $m ) ) {
			return '';
		}
		$rgb = array_map(
			function ( $channel ) {
				return min( 255, (int) $channel );
			},
			array_slice( $m, 1, 3 )
		);
		$alpha = round( (float) $m[4], 2 );
		if ( $alpha >= 1 ) {
			return vsprintf( '#%02x%02x%02x', $rgb );
		}
		// A dot whatever the locale (PHP 7 prints floats with the locale's).
		return sprintf( 'rgba(%d,%d,%d,%s)', $rgb[0], $rgb[1], $rgb[2], rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) );
	}

	/**
	 * The CSS variable of a font weight (sanitize_font_weights()), the same
	 * on every screen.
	 *
	 * @param string $weights Weight as stored.
	 * @param string $name    The variable, e.g. "wp-swiper-caption-weight".
	 * @return string[] none without a weight.
	 */
	public static function font_weight_vars( $weights, $name ) {
		$weight = self::sanitize_font_weights( $weights );
		return '' === $weight ? array() : array( '--' . $name . ':' . (int) $weight );
	}

	/**
	 * The font weight of the whole slider's captions or descriptions, from a
	 * slider saved while it had one (until 2.8): as stored per slide
	 * (sanitize_font_weights()), for the slides without their own until it is
	 * saved again (the editor moves it into them, admin.js); '' for none.
	 *
	 * @param array  $settings Slider settings.
	 * @param string $text     caption | description.
	 * @return string
	 */
	public static function legacy_font_weights( $settings, $text ) {
		return isset( $settings[ $text . '_weight' ] ) ? self::sanitize_font_weights( (string) $settings[ $text . '_weight' ] ) : '';
	}

	/**
	 * The font size of the whole slider's captions or descriptions, from a
	 * slider saved while it had one (until 2.8): its sizes as stored per
	 * slide (sanitize_font_sizes()), for the slides without their own until
	 * it is saved again (the editor moves it into them, admin.js); '' for none.
	 *
	 * @param array  $settings Slider settings.
	 * @param string $text     caption | description.
	 * @return string
	 */
	public static function legacy_font_sizes( $settings, $text ) {
		if ( ! isset( $settings[ $text . '_font' ] ) || 'custom' !== $settings[ $text . '_font' ] ) {
			return '';
		}
		$sizes = array();
		foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
			$sizes[] = isset( $settings[ $text . '_font_size' . $suffix ] ) ? (string) $settings[ $text . '_font_size' . $suffix ] : '';
		}
		if ( '' === $sizes[0] ) {
			$sizes[0] = '16';
		}
		return self::sanitize_font_sizes( $sizes );
	}

	/**
	 * The descriptions' alignment of a slider saved while it had an Alignment
	 * field (until 2.8): center or right still apply to the paragraphs without
	 * their own until it is saved again (the editor moves it into them, admin.js).
	 *
	 * @param array $settings Slider settings.
	 * @return string center | right, or '' (left: none needed)
	 */
	public static function legacy_description_align( $settings ) {
		$align = isset( $settings['description_align'] ) ? $settings['description_align'] : '';
		return in_array( $align, array( 'center', 'right' ), true ) ? $align : '';
	}

	/**
	 * How a description changes with its slide (frontend.css: the motion's
	 * animations): "Text transition", where "As the slider" takes the motion
	 * nearest to the effect's.
	 *
	 * @param array $settings Slider settings.
	 * @return string none | fade | slide | push | zoom | cube | flip | coverflow | cards | rotate
	 */
	public static function description_motion( $settings ) {
		// Fade, Slide or None (a choice: sanitized).
		if ( 'effect' !== $settings['description_transition'] ) {
			return $settings['description_transition'];
		}
		switch ( $settings['effect'] ) {
			case 'thumbs':
				return 'fade' === $settings['thumbs_transition'] ? 'fade' : 'slide';
			case 'creative':
				$styles = array(
					'push'     => 'push',
					'zoom'     => 'zoom',
					'apart'    => 'slide',
					'flip'     => 'flip',
					'rotate'   => 'rotate',
					'door'     => 'cube',
					'carousel' => 'coverflow',
				);
				return isset( $styles[ $settings['creative_style'] ] ) ? $styles[ $settings['creative_style'] ] : 'push';
			case 'fade':
			case 'cube':
			case 'flip':
			case 'coverflow':
			case 'cards':
				return $settings['effect'];
			case 'art':
				// Art does not move: one text fades into the next.
				return 'fade';
		}
		return 'slide';
	}

	/**
	 * The markup a slide description may have: paragraphs, headings, line
	 * breaks, bold, italic, underline and text color (a span the editor
	 * styles), links and lists; paragraphs, headings and list items aligned
	 * (their text-align only, sanitize_description()).
	 *
	 * @return array wp_kses() allowed tags.
	 */
	public static function description_tags() {
		return array(
			'p'      => array( 'style' => true ),
			'h1'     => array( 'style' => true ),
			'h2'     => array( 'style' => true ),
			'h3'     => array( 'style' => true ),
			'h4'     => array( 'style' => true ),
			'h5'     => array( 'style' => true ),
			'h6'     => array( 'style' => true ),
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'span'   => array( 'style' => true ),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array( 'style' => true ),
		);
	}

	/**
	 * The markup a caption may have: paragraphs and headings (aligned), bold,
	 * italic, underline (a span the editor styles) and line breaks. It sits
	 * over the image, letting clicks through to the slide: no links.
	 *
	 * @return array wp_kses() allowed tags.
	 */
	public static function caption_tags() {
		return array(
			'p'      => array( 'style' => true ),
			'h1'     => array( 'style' => true ),
			'h2'     => array( 'style' => true ),
			'h3'     => array( 'style' => true ),
			'h4'     => array( 'style' => true ),
			'h5'     => array( 'style' => true ),
			'h6'     => array( 'style' => true ),
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'span'   => array( 'style' => true ),
		);
	}

	/**
	 * A slide's own image position ("Cover": the point kept in view, on every
	 * device): "x y", percent of its width and height, 0–100 to one decimal.
	 * '' for none (the slider's Image position) or anything else.
	 *
	 * @param string $value Raw input, e.g. "30 72.5".
	 * @return string
	 */
	public static function sanitize_position( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\s*(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s*$/', $value, $match ) ) {
			return '';
		}
		// %F: a dot whatever the locale (PHP 7.4); "30.0" as "30".
		$point = array_map(
			function ( $number ) {
				return rtrim( rtrim( sprintf( '%.1F', min( 100, max( 0, (float) $number ) ) ), '0' ), '.' );
			},
			array( $match[1], $match[2] )
		);
		return $point[0] . ' ' . $point[1];
	}

	/**
	 * A slide description from the editor: its allowed markup only; '' when
	 * no text is left.
	 *
	 * @param string $html Raw input.
	 * @return string
	 */
	public static function sanitize_description( $html ) {
		// Paragraphs, headings and list items keep their alignment (the editor's
		// buttons), and no other attribute.
		$html = preg_replace_callback(
			'#<(p|h[1-6]|li)\b([^>]*)>#i',
			function ( $match ) {
				$align = self::text_align( $match[2] );
				return '<' . $match[1] . ( '' !== $align ? ' style="text-align: ' . $align . '"' : '' ) . '>';
			},
			(string) $html
		);
		return self::sanitize_rich_text( $html, self::description_tags() );
	}

	/**
	 * The alignment set in a tag's attributes (its style's text-align).
	 *
	 * @param string $attrs The tag's attributes.
	 * @return string left | center | right, or ''
	 */
	private static function text_align( $attrs ) {
		return preg_match( '/text-align\s*:\s*(left|center|right)\b/i', (string) $attrs, $match ) ? strtolower( $match[1] ) : '';
	}

	/**
	 * A caption from the editor (or a plain one saved before it had one): its
	 * allowed markup only; '' when no text is left.
	 *
	 * @param string $html Raw input.
	 * @return string
	 */
	public static function sanitize_caption( $html ) {
		// Paragraphs and headings (the editor's Format) keep their alignment,
		// and no other attribute.
		$html = preg_replace_callback(
			'#<(p|h[1-6])\b([^>]*)>#i',
			function ( $match ) {
				$align = self::text_align( $match[2] );
				return '<' . $match[1] . ( '' !== $align ? ' style="text-align: ' . $align . '"' : '' ) . '>';
			},
			(string) $html
		);
		// Other blocks (the editor before it had a Format, text pasted in): an
		// aligned one a span shown as a block, with that alignment only (as
		// saved until 2.8), the others lines, so their words do not run together.
		$html = preg_replace_callback(
			'#<(div|li|blockquote)\b([^>]*)>(.*?)</\1>#is',
			function ( $match ) {
				$align = self::text_align( $match[2] );
				return '' !== $align ? '<span style="display: block; text-align: ' . $align . '">' . $match[3] . '</span>' : $match[0];
			},
			$html
		);
		$html = preg_replace( '#\s*</(?:div|li|blockquote)>\s*#i', '<br>', $html );
		// Those spans keep their display: WordPress allows it in a style from 6.1
		// only (safe_style_css); here on older ones too, for this caption only.
		$display = function ( $styles ) {
			return array_merge( (array) $styles, array( 'display' ) );
		};
		add_filter( 'safe_style_css', $display );
		$html = self::sanitize_rich_text( $html, self::caption_tags() );
		remove_filter( 'safe_style_css', $display );
		return preg_replace( '#^(?:\s*<br\s*/?>)+|(?:<br\s*/?>\s*)+$#i', '', $html );
	}

	/**
	 * The tag a slider's captions were in while it had one for all (until
	 * 2.8, the Caption page's Tag): for the captions saved then, without
	 * paragraphs or headings of their own. "div" for none.
	 *
	 * @param array $settings Slider settings.
	 * @return string div | p | h1–h6
	 */
	public static function legacy_caption_tag( $settings ) {
		$tag = isset( $settings['caption_tag'] ) ? (string) $settings['caption_tag'] : '';
		return in_array( $tag, array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $tag : 'div';
	}

	/**
	 * A caption (sanitize_caption()) as shown: its own paragraphs and
	 * headings; one saved before it had them in the slider's old tag
	 * (legacy_caption_tag()), none for "div".
	 *
	 * @param string $caption  Sanitized caption.
	 * @param array  $settings Slider settings.
	 * @return string
	 */
	public static function caption_markup( $caption, $settings ) {
		if ( preg_match( '#<(?:p|h[1-6])\b#i', $caption ) ) {
			return $caption;
		}
		$tag = self::legacy_caption_tag( $settings );
		return 'div' === $tag ? $caption : '<' . $tag . '>' . $caption . '</' . $tag . '>';
	}

	/**
	 * Editor HTML with the given tags only, trimmed; '' when no text is left.
	 *
	 * @param string $html Raw input.
	 * @param array  $tags wp_kses() allowed tags.
	 * @return string
	 */
	private static function sanitize_rich_text( $html, $tags ) {
		$html = trim( wp_kses( (string) $html, $tags ) );
		// The editor's empty paragraph is "<p>&nbsp;</p>": no-break spaces count as none.
		$text = preg_replace( '/[\s\x{00A0}]+/u', '', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
		return '' === (string) $text ? '' : $html;
	}

	/**
	 * Image shadow, where it applies: on, with an effect whose photos stand
	 * apart (SHADOW_EFFECTS; Creative: the carousel; Slide: more than one
	 * slide in view, a continuous scroll, Width from image or the wider active
	 * slide), not as a background. Art has no spread (its shadow follows each
	 * shape: CSS drop-shadow).
	 *
	 * @param array $settings Slider settings.
	 * @return array|false { x, y, blur, spread, color, room: { top, right, bottom, left } } (px).
	 */
	public static function shadow( $settings ) {
		$effect = $settings['effect'];
		if ( empty( $settings['image_shadow'] ) || ! in_array( $effect, self::SHADOW_EFFECTS, true ) || self::is_background( $settings ) ) {
			return false;
		}
		// Creative: only the carousel has photos side by side.
		if ( 'creative' === $effect && ! self::is_carousel( $settings ) ) {
			return false;
		}
		// Slide: only with more than one slide in view (or of their own widths).
		if ( 'slide' === $effect && ! self::is_marquee( $settings ) && ! self::is_auto_width( $settings ) && ! self::is_expand( $settings )
			&& max( self::responsive( $settings, 'slides_per_view' ) ) <= 1 ) {
			return false;
		}

		$x      = (int) $settings['image_shadow_x'];
		$y      = (int) $settings['image_shadow_y'];
		$blur   = max( 0, (int) $settings['image_shadow_blur'] );
		$spread = 'art' === $effect ? 0 : (int) $settings['image_shadow_spread'];
		$color  = self::sanitize_slide_color( $settings['image_shadow_color'] );
		$color  = '' === $color ? 'rgba(0,0,0,0.3)' : $color;
		$reach  = $blur + $spread;

		return array(
			'x'      => $x,
			'y'      => $y,
			'blur'   => $blur,
			'spread' => $spread,
			'color'  => $color,
			// Room on each side for it to show (not cut off at the block's edges).
			'room'   => array(
				'top'    => max( 0, $reach - $y ),
				'right'  => max( 0, $reach + $x ),
				'bottom' => max( 0, $reach + $y ),
				'left'   => max( 0, $reach - $x ),
			),
		);
	}

	/**
	 * "Zoom the main image": on, with an effect that shows one main image
	 * (Slide: one slide in view on every device, or the wider active slide).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_zoom( $settings ) {
		if ( empty( $settings['zoom'] ) || ! in_array( $settings['effect'], self::ZOOM_EFFECTS, true ) ) {
			return false;
		}
		if ( 'slide' !== $settings['effect'] ) {
			return true;
		}
		if ( self::is_marquee( $settings ) || self::is_auto_width( $settings ) ) {
			return false;
		}
		return self::is_expand( $settings ) || max( self::responsive( $settings, 'slides_per_view' ) ) <= 1;
	}

	/**
	 * How long the zoom lasts (ms): the one set, else as long as a slide is
	 * shown with Autoplay (its delay plus the transition), else 6000.
	 *
	 * @param array $settings Slider settings.
	 * @return int
	 */
	public static function zoom_duration( $settings ) {
		if ( '' !== $settings['zoom_duration'] ) {
			return (int) $settings['zoom_duration'];
		}
		return $settings['autoplay'] ? (int) $settings['autoplay_delay'] + (int) $settings['speed'] : 6000;
	}

	/**
	 * Whether the "wider active slide" layout applies to these settings
	 * (horizontal: the active slide is wider; vertical: it is taller). It needs
	 * the Slide effect and cropped images (Image fit "Cover").
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_expand( $settings ) {
		return $settings['expand_active'] && 'slide' === $settings['effect'] && 'contain' !== $settings['image_fit']
			&& ! self::is_auto_width( $settings ) && ! self::is_marquee( $settings );
	}

	/**
	 * Slide effect with "Width from image": slides as wide as their images.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_auto_width( $settings ) {
		return ! empty( $settings['auto_width'] ) && 'slide' === $settings['effect'];
	}

	/**
	 * Creative effect with the "Carousel" style.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_carousel( $settings ) {
		return 'creative' === $settings['effect'] && 'carousel' === $settings['creative_style'];
	}

	/**
	 * Creative "Carousel" style. Swiper's creative slides always span the
	 * slider; the visible image (.wp-swiper__media) takes the `width` share of
	 * it so that the middle slide and `neighbours` slides on each side fit:
	 * each neighbour `offset` (share of the image width) from the one before,
	 * shrinking by (1 - scale) per step.
	 *
	 * @param array $settings Slider settings.
	 * @return array|false [ neighbours, offset, scale, opacity, width ] (shares, not %).
	 */
	public static function carousel( $settings ) {
		if ( ! self::is_carousel( $settings ) ) {
			return false;
		}
		$neighbours = max( 1, min( 3, (int) $settings['creative_neighbours'] ) );
		$offset     = max( 0.2, min( 1, $settings['creative_offset'] / 100 ) );
		$scale      = max( 0.5, min( 1, (float) $settings['creative_scale'] ) );
		$last       = max( 0.05, 1 - ( 1 - $scale ) * $neighbours ); // Scale of the outermost one.

		return array(
			'neighbours' => $neighbours,
			'offset'     => $offset,
			'scale'      => $scale,
			'opacity'    => max( 0, min( 100, (int) $settings['creative_opacity'] ) ) / 100,
			// The middle image plus the outermost neighbours' far halves.
			'width'      => round( min( 1, 1 / ( 2 * $neighbours * $offset + $last ) ), 4 ),
		);
	}

	/**
	 * Thumbnails effect: one slide at a time and a strip of thumbnails that
	 * picks it. The strip is its only control for now (Controls is not used).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_thumbs( $settings ) {
		return 'thumbs' === $settings['effect'];
	}

	/**
	 * Collage effect: the photos placed together, not a slider.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_collage( $settings ) {
		return 'collage' === $settings['effect'];
	}

	/**
	 * Slides that fade into one another: the Fade effect, or Thumbnails with
	 * the Fade transition. Neither has a direction (always horizontal).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_fade( $settings ) {
		return 'fade' === $settings['effect'] || ( self::is_thumbs( $settings ) && 'fade' === $settings['thumbs_transition'] );
	}

	/**
	 * Thumbnails effect: on which devices the strip is a column left or right
	 * of the slider (Thumbnails position is set per device).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => bool, 'tablet' => bool, 'mobile' => bool ]
	 */
	public static function thumbs_side( $settings ) {
		return array_map(
			function ( $position ) {
				return in_array( $position, array( 'left', 'right' ), true );
			},
			self::responsive( $settings, 'thumbs_position' )
		);
	}

	/**
	 * Thumbnail proportions on each device: a preset ("4-3") or, with
	 * "Custom", the two numbers of its own fields (per device as well).
	 *
	 * @param array $settings Slider settings.
	 * @return array device => [ width, height ], both above 0.
	 */
	public static function thumbs_ratio( $settings ) {
		$custom = self::responsive( $settings, 'thumbs_ratio_custom' );
		$ratios = array();

		foreach ( self::responsive( $settings, 'thumbs_ratio' ) as $device => $choice ) {
			$ratios[ $device ] = self::ratio_sides( 'custom' === $choice ? $custom[ $device ] : $choice );
		}
		return $ratios;
	}

	/**
	 * Image proportions on each device (Slide effect): [ width, height ], or
	 * false where they are "Off" (the images fill the row there); false
	 * altogether without any. Only for a horizontal row of slides of one
	 * width: not with "Width from image", the wider active slide or as a
	 * background (it fills its block).
	 *
	 * @param array $settings Slider settings.
	 * @return array|false device => [ width, height ] | false
	 */
	public static function slide_ratios( $settings ) {
		if ( 'slide' !== $settings['effect'] || 'horizontal' !== $settings['direction']
			|| self::is_auto_width( $settings ) || self::is_expand( $settings ) || self::is_background( $settings ) ) {
			return false;
		}
		$custom = self::responsive( $settings, 'slide_ratio_custom' );
		$ratios = array();
		foreach ( self::responsive( $settings, 'slide_ratio' ) as $device => $choice ) {
			$ratios[ $device ] = 'off' === $choice ? false : self::ratio_sides( 'custom' === $choice ? $custom[ $device ] : $choice );
		}
		return array_filter( $ratios ) ? $ratios : false;
	}

	/**
	 * The two sides of proportions stored as "4x3" (Custom) or "4-3" (a preset).
	 *
	 * @param string $value Two numbers and a separator.
	 * @return array [ width, height ], both above 0 (4:3 for anything else).
	 */
	private static function ratio_sides( $value ) {
		$ratio = array_map( 'floatval', preg_split( '/[x-]/', (string) $value ) ) + array( 1, 1 );
		return $ratio[0] > 0 && $ratio[1] > 0 ? array( $ratio[0], $ratio[1] ) : array( 4.0, 3.0 );
	}

	/**
	 * Proportions as typed ("4x3", "16 : 9", "2,35×1") in the stored form
	 * "4x3": two numbers from 0.1 to 100, at most two decimals.
	 *
	 * @param string $value Raw input.
	 * @return string '' if it is not two numbers.
	 */
	public static function normalize_ratio( $value ) {
		if ( ! preg_match( '/^\s*(\d+(?:[.,]\d+)?)\s*[x×х:\/*]\s*(\d+(?:[.,]\d+)?)\s*$/iu', (string) $value, $parts ) ) {
			return '';
		}
		$sides = array();
		foreach ( array( $parts[1], $parts[2] ) as $number ) {
			$side = max( 0.1, min( 100, (float) str_replace( ',', '.', $number ) ) );
			// With a point whatever the locale, and no trailing zeros: "2.35", "4".
			$sides[] = rtrim( rtrim( number_format( $side, 2, '.', '' ), '0' ), '.' );
		}
		return implode( 'x', $sides );
	}

	/**
	 * Custom thumbnail proportions were two fields (1.2.0), now one ("4x3"):
	 * values saved the old way are put together.
	 *
	 * @param array $values Saved settings, or one effect's saved Layout values.
	 * @return array
	 */
	private static function merge_ratio_fields( $values ) {
		if ( isset( $values['thumbs_ratio_custom'] ) || ! isset( $values['thumbs_ratio_width'], $values['thumbs_ratio_height'] ) ) {
			return $values;
		}
		$ratio = self::normalize_ratio( number_format( (float) $values['thumbs_ratio_width'], 2, '.', '' ) . 'x' . number_format( (float) $values['thumbs_ratio_height'], 2, '.', '' ) );
		if ( '' !== $ratio ) {
			$values['thumbs_ratio_custom'] = $ratio;
		}
		return $values;
	}

	/**
	 * A collage order as stored: photo IDs, positive whole numbers, comma
	 * separated, each once, at most 500.
	 *
	 * @param string $value Raw input, e.g. "12, 7,30".
	 * @return string "12,7,30"; '' for none.
	 */
	public static function sanitize_order( $value ) {
		$ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY ) ) );
		return implode( ',', array_slice( array_values( array_unique( $ids ) ), 0, 500 ) );
	}

	/**
	 * A collage template's sizes as stored: the template (one of
	 * COLLAGE_TEMPLATES), a colon and its cuts as a mosaic's
	 * (sanitize_mosaic()), "feature-left:V0.7[0,H0.5[1,2]]". collage.js uses
	 * them while they have the template's places. Or Scattered's, "scatter:"
	 * and its places (sanitize_scatter()); or Freeform's, "freeform:" and its
	 * places (sanitize_freeform()).
	 *
	 * @param string $value Raw input.
	 * @return string As above; '' when it is not one.
	 */
	public static function sanitize_template_cuts( $value ) {
		if ( ! preg_match( '/^\s*([a-z-]+):(.+)$/s', (string) $value, $match ) ) {
			return '';
		}
		if ( self::COLLAGE_SCATTER === $match[1] ) {
			$cuts = self::sanitize_scatter( $match[2] );
		} elseif ( self::COLLAGE_FREEFORM === $match[1] ) {
			$cuts = self::sanitize_freeform( $match[2] );
		} elseif ( in_array( $match[1], self::COLLAGE_TEMPLATES, true ) ) {
			$cuts = self::sanitize_mosaic( $match[2] );
		} else {
			return '';
		}
		return '' === $cuts ? '' : $match[1] . ':' . $cuts;
	}

	/**
	 * Scattered places as stored (collage.js scatterLayout()): the middle
	 * photo's width x height, then the arms that have photos, each once and
	 * in this order: T (above it), R (right), B (below), L (left), each its
	 * first photo's size and the next ones', each after the way it steps from
	 * the one before, "a" (along the arm) or "o" (outwards):
	 * "1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8;L0.85x0.5". Sizes 0.05–20, in no
	 * unit (the collage is scaled to its block); at most 100 places.
	 *
	 * @param string $value Raw input.
	 * @return string As above, sizes to four decimals; '' when it is not one.
	 */
	public static function sanitize_scatter( $value ) {
		$value = preg_replace( '/\s+/', '', (string) $value );
		if ( '' === $value || strlen( $value ) > 4000 ) {
			return '';
		}
		$number = function ( $text ) {
			$size = (float) $text;
			// A dot whatever the locale; no trailing zeros.
			return $size >= 0.05 && $size <= 20 ? rtrim( rtrim( sprintf( '%.4F', $size ), '0' ), '.' ) : null;
		};
		$size   = function ( $text ) use ( $number ) {
			if ( ! preg_match( '/^(\d*\.?\d+)x(\d*\.?\d+)$/', $text, $match ) ) {
				return null;
			}
			$w = $number( $match[1] );
			$h = $number( $match[2] );
			return null === $w || null === $h ? null : $w . 'x' . $h;
		};

		$groups = explode( ';', $value );
		$middle = $size( array_shift( $groups ) );
		if ( null === $middle ) {
			return '';
		}
		$out    = array( $middle );
		$places = 1;
		$arms   = 'TRBL';
		$last   = -1;
		foreach ( $groups as $group ) {
			$arm = '' === $group ? false : strpos( $arms, $group[0] );
			// Each arm once, in order, with a photo at least.
			if ( false === $arm || $arm <= $last ) {
				return '';
			}
			$last  = $arm;
			$steps = explode( ',', substr( $group, 1 ) );
			$first = $size( array_shift( $steps ) );
			if ( null === $first ) {
				return '';
			}
			$arm_out = $arms[ $arm ] . $first;
			++$places;
			foreach ( $steps as $step ) {
				$way  = substr( $step, 0, 1 );
				$next = $size( substr( $step, 1 ) );
				if ( ( 'a' !== $way && 'o' !== $way ) || null === $next ) {
					return '';
				}
				$arm_out .= ',' . $way . $next;
				++$places;
			}
			$out[] = $arm_out;
		}
		return $places > 100 ? '' : implode( ';', $out );
	}

	/**
	 * Freeform places as stored (collage.js freeLayout(), serializeFree()):
	 * the frame's width / height (0.05–20), then each place's x,y,w,h in the
	 * frame's units (its height is 1): "1.8;0.1,0.05,0.5,0.4;0.65,0,0.3,0.45".
	 * x and y -20–20 (a place may run past the frame), w and h 0.005–20; at
	 * least one place, at most 100.
	 *
	 * @param string $value Raw input.
	 * @return string As above, numbers to four decimals; '' when it is not one.
	 */
	public static function sanitize_freeform( $value ) {
		$value = preg_replace( '/\s+/', '', (string) $value );
		if ( '' === $value || strlen( $value ) > 4000 ) {
			return '';
		}
		// A dot whatever the locale; no trailing zeros.
		$number = function ( $text, $min, $max ) {
			if ( ! preg_match( '/^-?\d*\.?\d+$/', $text ) ) {
				return null;
			}
			$size = (float) $text;
			if ( $size < $min || $size > $max ) {
				return null;
			}
			$out = rtrim( rtrim( sprintf( '%.4F', $size ), '0' ), '.' );
			return '-0' === $out ? '0' : $out;
		};
		$parts  = explode( ';', $value );
		$aspect = $number( array_shift( $parts ), 0.05, 20 );
		if ( null === $aspect || ! $parts || count( $parts ) > 100 || 0.0 === (float) $aspect ) {
			return '';
		}
		$out = array( $aspect );
		foreach ( $parts as $part ) {
			$numbers = explode( ',', $part );
			if ( 4 !== count( $numbers ) ) {
				return '';
			}
			$x = $number( $numbers[0], -20, 20 );
			$y = $number( $numbers[1], -20, 20 );
			$w = $number( $numbers[2], 0.005, 20 );
			$h = $number( $numbers[3], 0.005, 20 );
			if ( null === $x || null === $y || null === $w || null === $h ) {
				return '';
			}
			$out[] = $x . ',' . $y . ',' . $w . ',' . $h;
		}
		return implode( ';', $out );
	}

	/**
	 * Random mosaic cuts as stored: a place's number (0 for the first place),
	 * or a cut, "V" (side by side) or "H" (one over the other), the share of
	 * the first part (0.05–0.95) and the two parts in brackets:
	 * "V0.6[0,H0.5[1,2]]". Every place once, numbered from 0 with none left
	 * out; at most 100 places.
	 *
	 * @param string $value Raw input.
	 * @return string The cuts as above, shares to four decimals; '' when it is not one.
	 */
	public static function sanitize_mosaic( $value ) {
		$value = preg_replace( '/\s+/', '', (string) $value );
		if ( '' === $value || strlen( $value ) > 4000 ) {
			return '';
		}
		$at     = 0;
		$leaves = array();
		$parse  = function () use ( &$parse, &$at, &$leaves, $value ) {
			if ( preg_match( '/\G(\d{1,3})/', $value, $match, 0, $at ) ) {
				$at      += strlen( $match[1] );
				$leaves[] = (int) $match[1];
				return $match[1] === (string) (int) $match[1] ? (string) (int) $match[1] : null;
			}
			if ( ! preg_match( '/\G([VH])(\d*\.?\d+)\[/', $value, $match, 0, $at ) ) {
				return null;
			}
			$at   += strlen( $match[0] );
			$share = (float) $match[2];
			if ( $share < 0.05 || $share > 0.95 ) {
				return null;
			}
			$a = $parse();
			if ( null === $a || ',' !== substr( $value, $at, 1 ) ) {
				return null;
			}
			++$at;
			$b = $parse();
			if ( null === $b || ']' !== substr( $value, $at, 1 ) ) {
				return null;
			}
			++$at;
			// A dot whatever the locale; no trailing zeros.
			$share = rtrim( rtrim( sprintf( '%.4F', $share ), '0' ), '.' );
			return $match[1] . $share . '[' . $a . ',' . $b . ']';
		};
		$tree = $parse();
		if ( null === $tree || strlen( $value ) !== $at || count( $leaves ) > 100 ) {
			return '';
		}
		$sorted = $leaves;
		sort( $sorted );
		return range( 0, count( $leaves ) - 1 ) === $sorted ? $tree : '';
	}

	/**
	 * The collage's order on each device as slide indexes: the photos of the
	 * order set, by ID, in its order (a photo twice in the slides: its first
	 * slide); false where none is set (the shuffle). Photos not in it come
	 * after them (frontend.js), so do new ones.
	 *
	 * @param array $settings Slider settings.
	 * @param array $slides   The slides shown.
	 * @return array device => int[] | false
	 */
	public static function collage_orders( $settings, $slides ) {
		$orders = array();
		foreach ( self::responsive( $settings, 'collage_order' ) as $device => $value ) {
			$value = self::sanitize_order( $value );
			if ( '' === $value ) {
				$orders[ $device ] = false;
				continue;
			}
			$used    = array();
			$indexes = array();
			foreach ( explode( ',', $value ) as $id ) {
				foreach ( $slides as $index => $slide ) {
					if ( (int) $slide['id'] === (int) $id && ! isset( $used[ $index ] ) ) {
						$used[ $index ] = true;
						$indexes[]      = $index;
						break;
					}
				}
			}
			$orders[ $device ] = $indexes ? $indexes : false;
		}
		return $orders;
	}

	/**
	 * Slide effect with "Continuous scroll".
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_marquee( $settings ) {
		return ! empty( $settings['marquee'] ) && 'slide' === $settings['effect'];
	}

	/**
	 * Whether an attachment is an image (an SVG too, which WordPress itself
	 * does not count as one).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_image( $attachment_id ) {
		return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id )
			&& 0 === strpos( (string) get_post_mime_type( $attachment_id ), 'image/' );
	}

	/**
	 * The continuous scroll's separator: what it is and its size per device;
	 * false without one (or with "Own image" and no image chosen).
	 *
	 * @param array $settings Slider settings.
	 * @return array|false [ 'type' => 'dot'|'image', 'image' => int, 'size' => [ device => int ] ]
	 */
	public static function separator( $settings ) {
		if ( ! self::is_marquee( $settings ) || empty( $settings['marquee_separator'] ) || 'none' === $settings['marquee_separator'] ) {
			return false;
		}
		$image = (int) $settings['marquee_separator_image'];
		if ( 'image' === $settings['marquee_separator'] && ! self::is_image( $image ) ) {
			return false;
		}
		return array(
			'type'  => $settings['marquee_separator'],
			'image' => 'image' === $settings['marquee_separator'] ? $image : 0,
			'size'  => array_map( 'intval', self::responsive( $settings, 'marquee_separator_size' ) ),
		);
	}

	/**
	 * Space between the slides per device: the one set, and with a separator
	 * at least its size (so it never covers the images).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => int, 'tablet' => int, 'mobile' => int ]
	 */
	public static function gaps( $settings ) {
		$gaps      = array_map( 'intval', self::responsive( $settings, 'space_between' ) );
		$separator = self::separator( $settings );
		if ( $separator ) {
			foreach ( $gaps as $device => $gap ) {
				$gaps[ $device ] = max( $gap, $separator['size'][ $device ] );
			}
		}
		return $gaps;
	}

	/**
	 * Slides per group for each device: Slide effect only, and not where the
	 * slider moves one slide at a time anyway (wider active slide, marquee).
	 *
	 * @param array $settings Slider settings.
	 * @return array|false [ 'desktop' => int, 'tablet' => int, 'mobile' => int ]
	 */
	public static function groups( $settings ) {
		if ( 'slide' !== $settings['effect'] || self::is_expand( $settings ) || self::is_marquee( $settings ) ) {
			return false;
		}
		return array_map( 'intval', self::responsive( $settings, 'slides_per_group' ) );
	}

	/**
	 * Visible slides per device in the wider active slide layout: Slides per
	 * view as a whole number, at least 2 (the active one plus a narrow one).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => int, 'tablet' => int, 'mobile' => int ]
	 */
	public static function expand_visible( $settings ) {
		return array_map(
			function ( $per_view ) {
				return max( 2, (int) round( $per_view ) );
			},
			self::responsive( $settings, 'slides_per_view' )
		);
	}

	/**
	 * Pages of the settings menu, in its order. Advanced's own fields are
	 * always in view beside the menu; a page shows the fields of its section
	 * (Layout, Caption) or those of Advanced given its name as `panel`.
	 *
	 * @return array page => [ title, dashicon ].
	 */
	public static function pages() {
		return array(
			'lightbox'    => array( __( 'Lightbox', 'wp-swiper' ), 'dashicons-format-gallery' ),
			'controls'    => array( __( 'Controls', 'wp-swiper' ), 'dashicons-leftright' ),
			'layout'      => array( __( 'Layout', 'wp-swiper' ), 'dashicons-layout' ),
			// The Thumbnails effect's strip (its menu item shows for that effect only).
			'thumbs'      => array( __( 'Thumbnails', 'wp-swiper' ), 'dashicons-images-alt2' ),
			'caption'     => array( __( 'Caption', 'wp-swiper' ), 'dashicons-editor-textcolor' ),
			// The slides' descriptions under the slider.
			'description' => array( __( 'Description', 'wp-swiper' ), 'dashicons-text-page' ),
			// Video slides (its menu item shows while the slider has one).
			'video'       => array( __( 'Video', 'wp-swiper' ), 'dashicons-video-alt3' ),
			// The outer block's class and id.
			'attributes'  => array( __( 'Custom', 'wp-swiper' ), 'dashicons-editor-code' ),
		);
	}

	/**
	 * The blocks some pages of the menu are in: a title over each and its
	 * fields in this order (admin.js hides a block with none of them shown).
	 * A field of such a page in no block would come after them.
	 *
	 * @return array page => [ block => [ 'title' => string, 'fields' => string[] ] ]
	 */
	public static function blocks() {
		$blocks = array(
			'controls' => array(
				// Collage: the only block of this page it uses (admin.js).
				'swapping'         => array(
					'title'  => __( 'Photo swapping', 'wp-swiper' ),
					'fields' => array( 'collage_swap', 'collage_swap_delay', 'collage_swap_speed', 'collage_pause' ),
				),
				'movement'         => array(
					'title'  => __( 'Movement', 'wp-swiper' ),
					'fields' => array( 'speed', 'loop', 'autoplay', 'autoplay_delay', 'pause_on_hover' ),
				),
				'navigation'       => array(
					'title'  => __( 'Arrows and keyboard', 'wp-swiper' ),
					'fields' => array( 'navigation', 'navigation_color', 'navigation_size', 'keyboard' ),
				),
				'pagination'       => array(
					'title'  => __( 'Pagination', 'wp-swiper' ),
					'fields' => array( 'pagination', 'pagination_position', 'pagination_position_vertical', 'pagination_placement', 'pagination_offset' ),
				),
				'pagination_style' => array(
					'title'  => __( 'Pagination style', 'wp-swiper' ),
					'fields' => array(
						'pagination_bullet_width',
						'pagination_bullet_height',
						'pagination_bullet_radius',
						'pagination_bullet_gap',
						'pagination_bullet_active',
						'pagination_fraction_size',
						'pagination_colors',
						'pagination_color',
						'pagination_inactive_color',
						'pagination_inactive_opacity',
					),
				),
			),
			'layout'   => array(
				// The chosen effect's own settings (the others are hidden), its
				// style first: what follows depends on it.
				'effect'      => array(
					'title'  => __( 'Effect', 'wp-swiper' ),
					'fields' => array(
						'creative_style',
						'creative_neighbours',
						'creative_offset',
						'creative_scale',
						'creative_opacity',
						'thumbs_transition',
						'coverflow_rotate',
						'coverflow_depth',
						'coverflow_stretch',
						'coverflow_scale',
						'cube_shadow',
						'cube_shadow_offset',
						'cube_shadow_scale',
						'cube_shadow_opacity',
						'cube_slide_shadows',
						'cards_offset',
						'cards_tilt',
						'cards_rotate',
						'effect_shadows',
					),
				),
				'arrangement' => array(
					'title'  => __( 'Arrangement', 'wp-swiper' ),
					'fields' => array( 'collage_layout', 'collage_order', 'collage_count', 'collage_columns', 'collage_row_height', 'collage_row_exact', 'collage_gap' ),
				),
				'slides'      => array(
					'title'  => __( 'Slides', 'wp-swiper' ),
					'fields' => array( 'slides_per_view', 'slides_per_group', 'space_between', 'auto_width', 'centered_slides', 'expand_active', 'active_width', 'marquee', 'marquee_speed', 'marquee_direction', 'marquee_separator', 'marquee_separator_image', 'marquee_separator_size', 'marquee_separator_color' ),
				),
				'size'        => array(
					'title'  => __( 'Size and fit', 'wp-swiper' ),
					'fields' => array( 'height', 'slide_ratio', 'slide_ratio_custom', 'slide_distribution', 'image_fit', 'image_position', 'image_align', 'image_align_vertical' ),
				),
				// Art's Image border here too (its own fields, as a slider's): the
				// shadow under it on both.
				'look'        => array(
					'title'  => __( 'Look', 'wp-swiper' ),
					'fields' => array( 'border_rounded', 'border_radius', 'image_border', 'image_border_width', 'image_border_color', 'art_border', 'art_border_width', 'art_border_color', 'image_shadow', 'image_shadow_x', 'image_shadow_y', 'image_shadow_blur', 'image_shadow_spread', 'image_shadow_color' ),
				),
				'zoom'        => array(
					'title'  => __( 'Zoom', 'wp-swiper' ),
					'fields' => array( 'zoom', 'zoom_direction', 'zoom_amount', 'zoom_origin', 'zoom_duration' ),
				),
				// Last: the slider behind its block's content, for the effects that
				// can be one (the block shows for them only); its settings under
				// the checkbox while it is on (admin.js).
				'background'  => array(
					'title'  => __( 'Background', 'wp-swiper' ),
					'fields' => array( 'background', 'background_class', 'background_overlay_color' ),
				),
			),
			'thumbs'   => array(
				'placement' => array(
					'title'  => __( 'Placement', 'wp-swiper' ),
					'fields' => array( 'thumbs_position', 'thumbs_align', 'thumbs_spacing' ),
				),
				'size'      => array(
					'title'  => __( 'Size', 'wp-swiper' ),
					'fields' => array( 'thumbs_per_view', 'thumbs_height', 'thumbs_gap', 'thumbs_ratio', 'thumbs_ratio_custom' ),
				),
				'look'      => array(
					'title'  => __( 'Look', 'wp-swiper' ),
					'fields' => array( 'thumbs_radius', 'thumbs_border_width', 'thumbs_border_color', 'thumbs_active_color', 'thumbs_opacity' ),
				),
			),
			// Block names stay apart from the other pages' (data-block).
			'lightbox'    => array(
				'lightbox_main'         => array(
					'title'  => __( 'Lightbox', 'wp-swiper' ),
					'fields' => array( 'lightbox', 'lightbox_videos', 'lightbox_border_rounded', 'lightbox_radius' ),
				),
				// The Thumbnails effect's strip in it (the block shows for that effect only).
				'lightbox_thumbs'       => array(
					'title'  => __( 'Thumbnails', 'wp-swiper' ),
					'fields' => array( 'lightbox_thumbs', 'lightbox_thumbs_radius' ),
				),
				'lightbox_descriptions' => array(
					'title'  => __( 'Descriptions', 'wp-swiper' ),
					'fields' => array( 'description_lightbox', 'description_lightbox_place', 'description_lightbox_width', 'description_lightbox_max' ),
				),
			),
			'caption'     => array(
				'caption_text'       => array(
					'title'  => __( 'Text', 'wp-swiper' ),
					'fields' => array( 'art_caption_middle', 'caption_size', 'caption_color', 'caption_orientation', 'caption_shadow' ),
				),
				'caption_background' => array(
					'title'  => __( 'Background', 'wp-swiper' ),
					'fields' => array( 'caption_bg', 'caption_bg_style', 'caption_bg_color' ),
				),
			),
			'description' => array(
				'description_text'  => array(
					'title'  => __( 'Text', 'wp-swiper' ),
					'fields' => array( 'description_own_color', 'description_color' ),
				),
				'description_place' => array(
					'title'  => __( 'Placement and transition', 'wp-swiper' ),
					'fields' => array( 'description_offset', 'description_transition' ),
				),
			),
			'attributes'  => array(
				'attributes' => array(
					'title'  => __( 'HTML attributes', 'wp-swiper' ),
					'fields' => array( 'css_class', 'css_id' ),
				),
			),
		);
		// Art: its blocks first on its pages (class-wp-swiper-art.php).
		foreach ( WP_Swiper_Art::blocks() as $page => $art_blocks ) {
			$blocks[ $page ] = $art_blocks + $blocks[ $page ];
		}
		return $blocks;
	}

	/**
	 * Storage keys of a field per device. Responsive fields keep tablet and
	 * mobile values under suffixed keys; '' there means "inherit".
	 *
	 * @param string $key   Field name.
	 * @param array  $field Field definition.
	 * @return array device => storage key.
	 */
	public static function device_keys( $key, $field ) {
		if ( empty( $field['responsive'] ) ) {
			return array( 'desktop' => $key );
		}
		return array(
			'desktop' => $key,
			'tablet'  => $key . '_tablet',
			'mobile'  => $key . '_mobile',
		);
	}

	/**
	 * @return array Default values keyed by storage key.
	 */
	public static function defaults() {
		$defaults = array();
		foreach ( self::fields() as $key => $field ) {
			foreach ( self::device_keys( $key, $field ) as $device => $storage_key ) {
				if ( 'desktop' === $device ) {
					$defaults[ $storage_key ] = $field['default'];
				} else {
					$defaults[ $storage_key ] = isset( $field['device_defaults'][ $device ] ) ? $field['device_defaults'][ $device ] : '';
				}
			}
		}
		return $defaults;
	}

	/**
	 * Value of a responsive field on each device, with inheritance resolved
	 * (an empty tablet value takes desktop's, an empty mobile one tablet's).
	 *
	 * @param array  $settings Slider settings.
	 * @param string $key      Field name.
	 * @return array [ 'desktop' => v, 'tablet' => v, 'mobile' => v ]
	 */
	public static function responsive( $settings, $key ) {
		$desktop = $settings[ $key ];
		$tablet  = '' === $settings[ $key . '_tablet' ] ? $desktop : $settings[ $key . '_tablet' ];
		$mobile  = '' === $settings[ $key . '_mobile' ] ? $tablet : $settings[ $key . '_mobile' ];

		return array(
			'desktop' => $desktop,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		);
	}

	/**
	 * Saved settings merged over defaults.
	 *
	 * @param int $post_id Slider ID.
	 * @return array
	 */
	public static function get( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_SETTINGS, true );
		$saved = is_array( $saved ) ? $saved : array();

		// Before responsive fields (no version mark), a mobile height of 0
		// meant "same as desktop"; now that is an empty value.
		if ( empty( $saved['_version'] ) && isset( $saved['height_mobile'] ) && 0 === (int) $saved['height_mobile'] ) {
			$saved['height_mobile'] = '';
		}

		// Version 3 merged "Visible slides" into "Slides per view": a slider in
		// the wider active slide layout keeps the counts it showed before
		// (Slides per view was hidden, so unused, in that layout).
		if ( ( empty( $saved['_version'] ) || $saved['_version'] < 3 ) && ! empty( $saved['expand_active'] ) ) {
			$saved['slides_per_view']        = isset( $saved['visible_slides'] ) ? $saved['visible_slides'] : 5;
			$saved['slides_per_view_tablet'] = isset( $saved['visible_slides_tablet'] ) ? $saved['visible_slides_tablet'] : '';
			$saved['slides_per_view_mobile'] = isset( $saved['visible_slides_mobile'] ) ? $saved['visible_slides_mobile'] : 3;
		}

		// The progress bar pagination was removed.
		if ( isset( $saved['pagination'] ) && 'progressbar' === $saved['pagination'] ) {
			$saved['pagination'] = 'bullets';
		}

		// Cube shadows were the one Effect shadows checkbox before they got their
		// own: a cube that had it on keeps both.
		if ( $saved && ! isset( $saved['cube_shadow'] ) && ! empty( $saved['effect_shadows'] ) ) {
			$saved['cube_shadow']        = true;
			$saved['cube_slide_shadows'] = true;
		}

		// Custom thumbnail proportions: one field instead of two.
		$saved = self::merge_ratio_fields( $saved );
		// The background's overlay: its opacity in its color.
		$saved = self::merge_overlay_opacity( $saved );
		// The caption background's: its opacity in its color too.
		$saved = self::merge_caption_bg_opacity( $saved );
		// Rounded corners: on where a radius was saved.
		$saved = self::merge_rounded( $saved );
		if ( ! empty( $saved['layouts'] ) && is_array( $saved['layouts'] ) ) {
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_ratio_fields' ), array_filter( $saved['layouts'], 'is_array' ) );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_overlay_opacity' ), $saved['layouts'] );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_rounded' ), $saved['layouts'] );
		}

		// Version 6: the lightbox radii are numbers of their own, empty = the
		// slider's. "Rounded corners in the lightbox" took the slider's; without
		// it the corners were square: 0 then (tablet and mobile inherit it).
		// Set in full: 1.2.0 had a "lightbox_radius" of its own, which may still
		// be saved, and would otherwise override the slider's.
		if ( $saved && ( empty( $saved['_version'] ) || $saved['_version'] < 6 ) ) {
			$radius = empty( $saved['lightbox_rounded'] ) ? 0 : '';
			foreach ( array( 'lightbox_radius', 'lightbox_thumbs_radius' ) as $key ) {
				$saved[ $key ]             = $radius;
				$saved[ $key . '_tablet' ] = '';
				$saved[ $key . '_mobile' ] = '';
			}
		}

		// Font size of the whole slider (until 2.8 just a size, then "Default" or
		// "Custom"; now each slide's own): a slider saved with a size of its own
		// (not 16px, or one on tablet or mobile) keeps it as Custom, read by
		// legacy_font_sizes() until it is saved again.
		foreach ( array( 'caption', 'description' ) as $text ) {
			// Only for a slider saved with a size (one saved since has none).
			$sized = isset( $saved[ $text . '_font_size' ] ) || isset( $saved[ $text . '_font_size_tablet' ] ) || isset( $saved[ $text . '_font_size_mobile' ] );
			if ( $saved && $sized && ! isset( $saved[ $text . '_font' ] ) ) {
				$own = ( isset( $saved[ $text . '_font_size' ] ) && 16 !== (int) $saved[ $text . '_font_size' ] )
					|| ( isset( $saved[ $text . '_font_size_tablet' ] ) && '' !== (string) $saved[ $text . '_font_size_tablet' ] )
					|| ( isset( $saved[ $text . '_font_size_mobile' ] ) && '' !== (string) $saved[ $text . '_font_size_mobile' ] );
				$saved[ $text . '_font' ] = $own ? 'custom' : 'default';
			}
		}

		// The captions' old Font size of the whole slider (legacy_font_sizes()):
		// now the Caption page's Font size, until it has one of its own.
		if ( $saved && ! isset( $saved['caption_size'] ) ) {
			$legacy = self::legacy_font_sizes( $saved, 'caption' );
			if ( '' !== $legacy ) {
				list( $saved['caption_size'], $saved['caption_size_tablet'], $saved['caption_size_mobile'] ) = explode( ',', $legacy );
			}
		}

		$settings = wp_parse_args( $saved, self::defaults() );

		// Version 4: a Height of 0 means 500px and "Auto" fills the parent block.
		// Before, every slider but the wider active slide one filled its parent
		// unless it had Image fit "Cover" and a height on every device; those
		// that did keep doing so. (Only saved sliders: new ones start at 500px.)
		if ( $saved && ( empty( $saved['_version'] ) || $saved['_version'] < 4 ) && ! self::is_expand( $settings ) ) {
			if ( 'contain' === $settings['image_fit'] || ! min( self::responsive( $settings, 'height' ) ) ) {
				$settings['height_unit'] = 'auto';
			}
		}

		// Version 5: Stack offset (Cards) is a % of the card width; its old
		// default, 16, spread the stack far outside the block: 8 as in Swiper.
		if ( $saved && ( empty( $saved['_version'] ) || $saved['_version'] < 5 ) ) {
			if ( 16 === (int) $settings['cards_offset'] ) {
				$settings['cards_offset'] = 8;
			}
			if ( ! empty( $settings['layouts'] ) && is_array( $settings['layouts'] ) ) {
				foreach ( $settings['layouts'] as $effect => $layout ) {
					if ( isset( $layout['cards_offset'] ) && 16 === (int) $layout['cards_offset'] ) {
						$settings['layouts'][ $effect ]['cards_offset'] = 8;
					}
				}
			}
		}

		// The lightbox image's Rounded corners got their switch, its radius no
		// "As the slides" (merge_lightbox_rounded()).
		if ( $saved && ! isset( $saved['lightbox_border_rounded'] ) ) {
			$settings = self::merge_lightbox_rounded( $settings );
		}

		return $settings;
	}

	/**
	 * Until 3.0 the lightbox image's radius was a number per device, empty
	 * for the slider's own there (Art's Rounded corners for Art). Now a
	 * switch and a radius of its own: the corners it showed, as its own
	 * radius (a smaller screen with the same as the larger one inherits),
	 * the switch on where any is above 0.
	 *
	 * @param array $settings A slider's settings (over the defaults).
	 * @return array
	 */
	public static function merge_lightbox_rounded( $settings ) {
		$fallback = 'art' === $settings['effect'] ? 'art_radius' : 'border_radius';
		$own      = self::responsive( $settings, 'lightbox_radius' );
		$slider   = self::responsive( self::apply_rounded( $settings ), $fallback );
		$shown    = array();
		foreach ( $own as $device => $radius ) {
			$shown[ $device ] = (int) ( '' === $radius ? $slider[ $device ] : $radius );
		}
		$settings['lightbox_border_rounded'] = max( $shown ) > 0;
		if ( $settings['lightbox_border_rounded'] ) {
			$settings['lightbox_radius']        = $shown['desktop'];
			$settings['lightbox_radius_tablet'] = $shown['tablet'] !== $shown['desktop'] ? $shown['tablet'] : '';
			$settings['lightbox_radius_mobile'] = $shown['mobile'] !== $shown['tablet'] ? $shown['mobile'] : '';
		} else {
			$settings['lightbox_radius']        = self::fields()['lightbox_radius']['default'];
			$settings['lightbox_radius_tablet'] = '';
			$settings['lightbox_radius_mobile'] = '';
		}
		return $settings;
	}

	/**
	 * The lightbox image's corners per device: its own radius while Rounded
	 * corners is on, else none.
	 *
	 * @param array $settings A slider's settings.
	 * @return int[] device => px.
	 */
	public static function lightbox_image_radius( $settings ) {
		$radius = self::responsive( $settings, 'lightbox_radius' );
		foreach ( $radius as $device => $px ) {
			$radius[ $device ] = empty( $settings['lightbox_border_rounded'] ) ? 0 : (int) $px;
		}
		return $radius;
	}

	/**
	 * Per device, whether the slider takes its height from its parent block
	 * there (Height unit "Auto") instead of a set Height.
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => bool, 'tablet' => bool, 'mobile' => bool ]
	 */
	public static function auto_heights( $settings ) {
		return array_map(
			function ( $unit ) {
				return 'auto' === $unit;
			},
			self::responsive( $settings, 'height_unit' )
		);
	}

	/**
	 * Whether the slider takes its height from its parent block on some
	 * device (Height unit "Auto" there).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_auto_height( $settings ) {
		return in_array( true, self::auto_heights( $settings ), true );
	}

	/**
	 * Whether it does so on every device.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_auto_height_everywhere( $settings ) {
		return ! in_array( false, self::auto_heights( $settings ), true );
	}

	/**
	 * CSS height per device: the Height in that device's unit, 0 = 500px;
	 * "auto" where the unit is "Auto" (frontend.js sizes the slider there).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => string, 'tablet' => string, 'mobile' => string ]
	 */
	public static function css_heights( $settings ) {
		$units = self::responsive( $settings, 'height_unit' );
		$css   = array();
		foreach ( self::responsive( $settings, 'height' ) as $device => $height ) {
			if ( 'auto' === $units[ $device ] ) {
				$css[ $device ] = 'auto';
			} else {
				$css[ $device ] = $height ? (int) $height . $units[ $device ] : '500px';
			}
		}
		return $css;
	}

	/**
	 * Height "Auto" for frontend.js: false where no device has it; else
	 * whether each device does.
	 *
	 * @param array $settings Slider settings.
	 * @return array|false [ 'desktop' => bool, 'tablet' => bool, 'mobile' => bool ]
	 */
	public static function fill_config( $settings ) {
		return self::is_auto_height( $settings ) ? self::auto_heights( $settings ) : false;
	}

	/**
	 * Saved slides: list of [ 'id' => int, 'caption' => string, 'link' => string,
	 * 'new_tab' => bool, 'poster' => int, 'embed' => string, 'description' =>
	 * string ] (slides saved before "new tab", video slides or descriptions
	 * existed have no new_tab / poster / embed / description key). A
	 * description: HTML (see description_tags()); a caption too (see
	 * caption_tags()), plain text when saved before it had an editor. "id": an image, or a video (its "poster": the image shown
	 * before it plays; 0 = none); or "embed": a YouTube / Vimeo link (id 0).
	 *
	 * @param int $post_id Slider ID.
	 * @return array
	 */
	public static function get_slides( $post_id ) {
		$slides = get_post_meta( $post_id, self::META_SLIDES, true );
		return is_array( $slides ) ? $slides : array();
	}

	/**
	 * Whether a media library item can be a slide: an image or a video.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_slide_media( $attachment_id ) {
		return wp_attachment_is_image( $attachment_id ) || self::is_video( $attachment_id );
	}

	/**
	 * Whether a saved slide can be shown: a YouTube / Vimeo link it can read,
	 * or an image or video still in the media library.
	 *
	 * @param array $slide Slide data.
	 * @return bool
	 */
	public static function is_slide( $slide ) {
		if ( ! empty( $slide['embed'] ) ) {
			return (bool) self::parse_embed( $slide['embed'] );
		}
		return ! empty( $slide['id'] ) && self::is_slide_media( $slide['id'] );
	}

	/**
	 * A YouTube or Vimeo link as its provider and video id ("hash": an
	 * unlisted Vimeo video's key; "vertical": a YouTube Short).
	 *
	 * @param string $url Video page or player address.
	 * @return array|false [ 'provider', 'id', 'hash', 'vertical' ]
	 */
	public static function parse_embed( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtube-nocookie\.com/embed/|youtu\.be/)([\w-]{11})~i', $url, $match ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $match[1],
				'hash'     => '',
				'vertical' => false !== stripos( $url, '/shorts/' ),
			);
		}
		if ( preg_match( '~^https?://(?:www\.)?(?:player\.)?vimeo\.com/(?:.*?/)?(\d{6,})(?:/([\da-f]{6,}))?~i', $url, $match ) ) {
			$hash = isset( $match[2] ) ? $match[2] : '';
			if ( ! $hash && preg_match( '~[?&]h=([\da-f]+)~i', $url, $key ) ) {
				$hash = $key[1];
			}
			return array(
				'provider' => 'vimeo',
				'id'       => $match[1],
				'hash'     => $hash,
				'vertical' => false,
			);
		}
		return false;
	}

	/**
	 * A YouTube / Vimeo video's size, thumbnail and title from the service
	 * (oEmbed), kept for a week (an hour when the service did not answer).
	 *
	 * @param string $url Video address.
	 * @return array [ 'width', 'height', 'thumb', 'title' ], empty if unknown.
	 */
	public static function embed_meta( $url ) {
		$key  = 'wp_swiper_oembed_' . md5( $url );
		$meta = get_transient( $key );
		if ( false === $meta ) {
			$data = _wp_oembed_get_object()->get_data( $url );
			$meta = $data ? array(
				'width'  => isset( $data->width ) ? (int) $data->width : 0,
				'height' => isset( $data->height ) ? (int) $data->height : 0,
				'thumb'  => isset( $data->thumbnail_url ) ? esc_url_raw( $data->thumbnail_url ) : '',
				'title'  => isset( $data->title ) ? sanitize_text_field( $data->title ) : '',
			) : array();
			set_transient( $key, $meta, $data ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
		}
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Width and height of a slide: its image's or video's, a YouTube / Vimeo
	 * video's from the service (16:9 when unknown, 9:16 for a YouTube Short).
	 *
	 * @param array $slide Slide data.
	 * @return array|false [ width, height ]
	 */
	public static function slide_size( $slide ) {
		if ( empty( $slide['embed'] ) ) {
			return self::media_size( $slide['id'] );
		}
		$embed = self::parse_embed( $slide['embed'] );
		$meta  = self::embed_meta( $slide['embed'] );
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) && ! ( $embed && $embed['vertical'] ) ) {
			return array( $meta['width'], $meta['height'] );
		}
		return $embed && $embed['vertical'] ? array( 9, 16 ) : array( 16, 9 );
	}

	/**
	 * A YouTube / Vimeo video's own thumbnail: YouTube's largest (the page
	 * falls back to `fallback` where it has none), Vimeo's from the service;
	 * `small` for thumbnails (YouTube's 16:9 one: its larger ones that are
	 * always there have black bands in the picture).
	 *
	 * @param string $url Video address.
	 * @return array [ 'src', 'fallback', 'small' ], empty strings if none.
	 */
	public static function embed_thumb( $url ) {
		$embed = self::parse_embed( $url );
		if ( $embed && 'youtube' === $embed['provider'] ) {
			return array(
				'src'      => 'https://i.ytimg.com/vi/' . $embed['id'] . '/maxresdefault.jpg',
				'fallback' => 'https://i.ytimg.com/vi/' . $embed['id'] . '/hqdefault.jpg',
				'small'    => 'https://i.ytimg.com/vi/' . $embed['id'] . '/mqdefault.jpg',
			);
		}
		$meta  = $embed ? self::embed_meta( $url ) : array();
		$thumb = empty( $meta['thumb'] ) ? '' : $meta['thumb'];
		return array(
			'src'      => $thumb,
			'fallback' => '',
			'small'    => $thumb,
		);
	}

	/**
	 * Whether a slide's media is a video.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_video( $attachment_id ) {
		return (bool) wp_attachment_is( 'video', $attachment_id );
	}

	/**
	 * Width and height of a slide's media: an image's full size, a video's
	 * frame (from its metadata).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|false [ width, height ], both above 0.
	 */
	public static function media_size( $attachment_id ) {
		if ( self::is_video( $attachment_id ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			$size = array( isset( $meta['width'] ) ? (int) $meta['width'] : 0, isset( $meta['height'] ) ? (int) $meta['height'] : 0 );
		} else {
			$src  = wp_get_attachment_image_src( $attachment_id, 'full' );
			$size = $src ? array( (int) $src[1], (int) $src[2] ) : array( 0, 0 );
		}
		return $size[0] > 0 && $size[1] > 0 ? $size : false;
	}

	/**
	 * The image that stands for a video slide (before it plays, in the strip
	 * of thumbnails): its poster, else the video's own featured image.
	 *
	 * @param array $slide Slide data.
	 * @return int Attachment ID, 0 if none.
	 */
	public static function poster( $slide ) {
		if ( ! empty( $slide['poster'] ) && wp_attachment_is_image( $slide['poster'] ) ) {
			return (int) $slide['poster'];
		}
		// A YouTube / Vimeo video has its own thumbnail instead (embed_thumb()).
		if ( ! empty( $slide['embed'] ) || empty( $slide['id'] ) ) {
			return 0;
		}
		$featured = (int) get_post_thumbnail_id( $slide['id'] );
		return $featured && wp_attachment_is_image( $featured ) ? $featured : 0;
	}

	/**
	 * Space-separated CSS class names, each run through sanitize_html_class().
	 *
	 * @param string $classes Raw input.
	 * @return string
	 */
	public static function sanitize_classes( $classes ) {
		return implode( ' ', array_unique( array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', trim( $classes ) ) ) ) ) );
	}

	/**
	 * An HTML id: letters, digits, "-" and "_" only, so it also works as a CSS
	 * selector and a link target (#id). Spaces become "-" ("hero slider").
	 *
	 * @param string $id Raw input.
	 * @return string
	 */
	public static function sanitize_id( $id ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', preg_replace( '/\s+/', '-', trim( $id ) ) );
	}

	/**
	 * Sanitize raw form input against the schema.
	 *
	 * @param array $input Raw $_POST values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$clean = array( '_version' => 6 ) + self::sanitize_fields( $input );

		// Layout values of every effect (see layouts()), sent by the editor as
		// JSON; the chosen effect's are the ones posted as fields.
		$layouts = isset( $input['layouts'] ) ? json_decode( (string) $input['layouts'], true ) : null;
		$layouts = is_array( $layouts ) ? $layouts : array();

		$clean['layouts'] = array();
		foreach ( array_keys( self::fields()['effect']['choices'] ) as $effect ) {
			if ( $effect === $clean['effect'] ) {
				$clean['layouts'][ $effect ] = array_intersect_key( $clean, array_flip( self::layout_keys() ) );
			} elseif ( isset( $layouts[ $effect ] ) && is_array( $layouts[ $effect ] ) ) {
				$clean['layouts'][ $effect ] = self::sanitize_fields( $layouts[ $effect ], 'layout' );
			}
		}

		return $clean;
	}

	/**
	 * Storage keys of the Layout fields: the values each effect keeps its own.
	 *
	 * @return string[]
	 */
	public static function layout_keys() {
		$keys = array();
		foreach ( self::fields() as $key => $field ) {
			if ( 'layout' === $field['section'] ) {
				$keys = array_merge( $keys, array_values( self::device_keys( $key, $field ) ) );
			}
		}
		return $keys;
	}

	/**
	 * Layout values of every effect, for the editor to swap in when the effect
	 * changes: the chosen effect's are the slider's settings; another one's
	 * are those saved for it, or the defaults if it was never used.
	 *
	 * @param array $settings Slider settings (see get()).
	 * @param array $saved    Saved per-effect layouts (settings' "layouts").
	 * @return array effect => [ storage key => value ]
	 */
	public static function layouts( $settings, $saved ) {
		$keys     = array_flip( self::layout_keys() );
		$defaults = array_intersect_key( self::defaults(), $keys );
		$layouts  = array();

		foreach ( array_keys( self::fields()['effect']['choices'] ) as $effect ) {
			if ( $effect === $settings['effect'] ) {
				$layouts[ $effect ] = array_intersect_key( $settings, $keys );
			} elseif ( isset( $saved[ $effect ] ) && is_array( $saved[ $effect ] ) ) {
				// Fields added since it was saved take their defaults.
				$layouts[ $effect ] = array_intersect_key( $saved[ $effect ], $keys ) + $defaults;
			} else {
				$layouts[ $effect ] = $defaults;
			}
		}
		return $layouts;
	}

	/**
	 * Sanitize raw values against the schema.
	 *
	 * @param array       $input   Raw values keyed by storage key.
	 * @param string|null $section Only this section's fields (null: all).
	 * @return array
	 */
	private static function sanitize_fields( $input, $section = null ) {
		$clean = array();

		foreach ( self::fields() as $key => $field ) {
			if ( null !== $section && $field['section'] !== $section ) {
				continue;
			}
			// Tablet/mobile values of responsive fields: empty inherits.
			foreach ( array_slice( self::device_keys( $key, $field ), 1 ) as $storage_key ) {
				$value = isset( $input[ $storage_key ] ) ? trim( (string) $input[ $storage_key ] ) : '';
				if ( 'select' === $field['type'] ) {
					$value = array_key_exists( $value, $field['choices'] ) ? $value : '';
				} elseif ( 'ratio' === $field['type'] ) {
					$value = self::normalize_ratio( $value );
				} elseif ( 'order' === $field['type'] ) {
					$value = self::sanitize_order( $value );
				} elseif ( 'mosaic' === $field['type'] ) {
					$value = self::sanitize_mosaic( $value );
				} elseif ( 'cuts' === $field['type'] ) {
					$value = self::sanitize_template_cuts( $value );
				} elseif ( 'svg_shape' === $field['type'] ) {
					// Art's SVG shape of its own on this device ('' : the larger screen's).
					$value = WP_Swiper_Svg_Shape::sanitize( $value );
				} elseif ( '' !== $value ) {
					$value = 'float' === $field['type'] ? round( (float) $value, 2 ) : (int) $value;
					$value = max( $field['min'], min( $field['max'], $value ) );
				}
				$clean[ $storage_key ] = $value;
			}

			$value = isset( $input[ $key ] ) ? $input[ $key ] : null;

			switch ( $field['type'] ) {
				case 'checkbox':
					$clean[ $key ] = ! empty( $value );
					break;

				case 'number':
					// A field that may stay empty on desktop too (`empty`): '' is kept.
					if ( ! empty( $field['empty'] ) && ( null === $value || '' === trim( (string) $value ) ) ) {
						$clean[ $key ] = '';
						break;
					}
					$value         = null === $value ? $field['default'] : (int) $value;
					$clean[ $key ] = max( $field['min'], min( $field['max'], $value ) );
					break;

				case 'float':
					$value         = null === $value ? $field['default'] : round( (float) $value, 2 );
					$clean[ $key ] = max( $field['min'], min( $field['max'], $value ) );
					break;

				case 'select':
					$value         = sanitize_text_field( (string) $value );
					$clean[ $key ] = array_key_exists( $value, $field['choices'] ) ? $value : $field['default'];
					break;

				case 'color':
					$color         = sanitize_hex_color( (string) $value );
					$clean[ $key ] = $color ? $color : $field['default'];
					break;

				case 'alpha_color': // A color with its opacity: "#rrggbb" or "rgba(r,g,b,a)".
					$color         = self::sanitize_slide_color( (string) $value );
					$clean[ $key ] = '' !== $color ? $color : $field['default'];
					break;

				case 'editor': // Text from WordPress's editor: a description's markup (links too).
					$clean[ $key ] = self::sanitize_description( (string) $value );
					break;

				case 'ratio': // Proportions, "4x3".
					$ratio         = self::normalize_ratio( (string) $value );
					$clean[ $key ] = '' !== $ratio ? $ratio : $field['default'];
					break;

				case 'text': // CSS class names: anything else is dropped.
					$clean[ $key ] = self::sanitize_classes( (string) $value );
					break;

				case 'id': // An HTML id.
					$clean[ $key ] = self::sanitize_id( (string) $value );
					break;

				case 'seed': // The Collage's shuffle: a whole number from 1.
					$clean[ $key ] = null === $value ? $field['default'] : max( 1, min( 999999, absint( $value ) ) );
					break;

				case 'order': // Photo IDs in their order, "12,7,30"; '' = none.
					$clean[ $key ] = self::sanitize_order( null === $value ? '' : (string) $value );
					break;

				case 'mosaic': // Random mosaic cuts, "V0.6[0,H0.5[1,2]]"; '' = none.
					$clean[ $key ] = self::sanitize_mosaic( null === $value ? '' : (string) $value );
					break;

				case 'cuts': // A template's sizes, "feature-left:V0.7[0,H0.5[1,2]]"; '' = none.
					$clean[ $key ] = self::sanitize_template_cuts( null === $value ? '' : (string) $value );
					break;

				case 'image': // An image of the media library (its ID); 0 = none.
					$clean[ $key ] = self::is_image( absint( $value ) ) ? absint( $value ) : 0;
					break;

				case 'svg_shape': // Art's SVG shape: its shapes' geometry only, as JSON; '' = none.
					$clean[ $key ] = WP_Swiper_Svg_Shape::sanitize( null === $value ? '' : (string) $value );
					break;
			}
		}

		return $clean;
	}
}
