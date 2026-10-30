<?php
/**
 * Captions, descriptions and colors on the front end: their CSS variables
 * (font sizes and weights), allowed tags and markup, the descriptions'
 * transition, the caption background and the background's overlay. Used by
 * WP_Swiper_Settings, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Settings_Text {

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
}
