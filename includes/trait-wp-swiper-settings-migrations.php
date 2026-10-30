<?php
/**
 * Older saved sliders brought up to date: the merge_*() steps get() runs
 * on the saved values (and on the other effects' saved Layout values), and
 * the legacy_*() readers of fields that are gone (sanitize() drops unknown
 * keys). Used by WP_Swiper_Settings, so `self::` is that class; the
 * constants they read (OLD_ART_WIDTHS, OLD_MOBILE_DEFAULTS) are there.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Settings_Migrations {

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
	 * Until 3.0 the cube's shadow was black at an Opacity (%) of its own: one
	 * color with its opacity now.
	 *
	 * @param array $saved A slider's saved settings.
	 * @return array
	 */
	public static function merge_cube_shadow_opacity( $saved ) {
		return self::merge_opacity( $saved, 'cube_shadow_opacity', 'cube_shadow_color' );
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

	/**
	 * Rounded corners got their switch (as Image border): a slider saved
	 * before it with a radius above 0 on any device has it on, so it shows
	 * as it did; one saved without keeps it off. Art's too (art_rounded): it
	 * always had a radius (14px unless set), so on unless it was 0 on every
	 * device; off, its radius the default again, for when it is turned on.
	 *
	 * @param array $saved A slider's (or an effect's layout) saved settings.
	 * @return array
	 */
	public static function merge_rounded( $saved ) {
		if ( ! $saved ) {
			return $saved;
		}
		if ( ! isset( $saved['border_rounded'] ) ) {
			$rounded = false;
			foreach ( array( 'border_radius', 'border_radius_tablet', 'border_radius_mobile' ) as $key ) {
				if ( isset( $saved[ $key ] ) && '' !== (string) $saved[ $key ] && (int) $saved[ $key ] > 0 ) {
					$rounded = true;
				}
			}
			$saved['border_rounded'] = $rounded;
		}
		if ( ! isset( $saved['art_rounded'] ) ) {
			// Never saved: the default radius, above 0.
			$rounded = ! isset( $saved['art_radius'] ) || '' === (string) $saved['art_radius'] || (int) $saved['art_radius'] > 0;
			foreach ( array( 'art_radius_tablet', 'art_radius_mobile' ) as $key ) {
				if ( isset( $saved[ $key ] ) && '' !== (string) $saved[ $key ] && (int) $saved[ $key ] > 0 ) {
					$rounded = true;
				}
			}
			$saved['art_rounded'] = $rounded;
			if ( ! $rounded ) {
				$saved['art_radius']        = WP_Swiper_Art::fields()['art_radius']['default'];
				$saved['art_radius_tablet'] = '';
				$saved['art_radius_mobile'] = '';
			}
		}
		return $saved;
	}

	/**
	 * Art's Ring opening got its switch (Opening, a block of its own): on
	 * where a slider saved before it had an Opening above 0 on any device,
	 * so it shows as it did; else off (the photos evenly all around, as
	 * before), the size the default again, for when it is turned on.
	 *
	 * @param array $saved A slider's (or an effect's layout) saved settings.
	 * @return array
	 */
	public static function merge_art_opening( $saved ) {
		if ( ! $saved || isset( $saved['art_opening'] ) ) {
			return $saved;
		}
		$open = false;
		foreach ( array( 'art_ring_opening', 'art_ring_opening_tablet', 'art_ring_opening_mobile' ) as $key ) {
			if ( isset( $saved[ $key ] ) && '' !== (string) $saved[ $key ] && (int) $saved[ $key ] > 0 ) {
				$open = true;
			}
		}
		$saved['art_opening'] = $open;
		if ( ! $open ) {
			$saved['art_ring_opening']        = WP_Swiper_Art::fields()['art_ring_opening']['default'];
			$saved['art_ring_opening_tablet'] = '';
			$saved['art_ring_opening_mobile'] = '';
		}
		return $saved;
	}

	/**
	 * Until 3.1 the rings and SVG shape had a Size (their width: px, % of the
	 * column, vh or Auto) and the grids a Height (px, or Auto: square photos);
	 * now one Height (px, vh, %, Auto) for every Style. A saved one, as its
	 * Style used it: a ring's Size is its height (it is square), a shape's
	 * its width over its drawing's proportions; % of the column (a ring as
	 * wide as the column) is Auto now. A grid's px stays; its square photos
	 * are the height they made in a column OLD_ART_WIDTHS wide.
	 *
	 * @param array $saved A slider's (or an effect's layout) saved settings.
	 * @return array
	 */
	public static function merge_art_height( $saved ) {
		if ( ! $saved || isset( $saved['art_height'] ) || ! ( isset( $saved['art_size'] ) || isset( $saved['art_size_unit'] ) || isset( $saved['art_grid_height'] ) || isset( $saved['art_grid_height_unit'] ) ) ) {
			return $saved;
		}
		$suffixes = array(
			'desktop' => '',
			'tablet'  => '_tablet',
			'mobile'  => '_mobile',
		);
		// An old setting per device as it applied (an empty one: the larger screen's).
		$old = function ( $key, $default ) use ( $saved, $suffixes ) {
			$values = array();
			$last   = $default;
			foreach ( $suffixes as $device => $suffix ) {
				if ( isset( $saved[ $key . $suffix ] ) && '' !== (string) $saved[ $key . $suffix ] ) {
					$last = $saved[ $key . $suffix ];
				}
				$values[ $device ] = $last;
			}
			return $values;
		};
		$style   = isset( $saved['art_style'] ) ? (string) $saved['art_style'] : 'ring';
		$heights = array();
		$units   = array();
		if ( 0 === strpos( $style, 'grid-' ) ) {
			$height  = $old( 'art_grid_height', 500 );
			$unit    = $old( 'art_grid_height_unit', 'auto' );
			$count   = $old( 'art_grid_count', 9 );
			$columns = $old( 'art_grid_columns', 3 );
			$gap     = $old( 'art_gap', 8 );
			foreach ( $suffixes as $device => $suffix ) {
				$units[ $device ] = 'px';
				if ( 'px' === $unit[ $device ] ) {
					$heights[ $device ] = max( 120, min( 2000, (int) $height[ $device ] ) );
					continue;
				}
				$cols = max( 2, min( 6, (int) $columns[ $device ] ) );
				$rows = (int) ceil( max( 2, min( 36, (int) $count[ $device ] ) ) / $cols );
				$step = max( 0, min( 40, (int) $gap[ $device ] ) );
				$cell = ( self::OLD_ART_WIDTHS[ $device ] - ( $cols - 1 ) * $step ) / $cols;
				$heights[ $device ] = max( 120, min( 2000, (int) round( $rows * $cell + ( $rows - 1 ) * $step ) ) );
			}
		} else {
			$size  = $old( 'art_size', 560 );
			$unit  = $old( 'art_size_unit', 'px' );
			$ratio = 1;
			if ( 'shape' === $style ) {
				$shape = WP_Swiper_Art::shape( wp_parse_args( $saved, self::defaults() ) );
				$ratio = $shape && $shape['ratio'] > 0 ? (float) $shape['ratio'] : 1;
			}
			foreach ( $suffixes as $device => $suffix ) {
				$fixed              = in_array( $unit[ $device ], array( 'px', 'vh' ), true );
				$units[ $device ]   = $fixed ? $unit[ $device ] : 'auto';
				$heights[ $device ] = $fixed ? max( 1, (int) round( (int) $size[ $device ] / $ratio ) ) : 560;
			}
		}
		// Tablet and mobile: empty where the same as the larger screen's (they inherit it).
		$saved['art_height']      = $heights['desktop'];
		$saved['art_height_unit'] = $units['desktop'];
		$saved['art_height_tablet']      = $heights['tablet'] !== $heights['desktop'] ? $heights['tablet'] : '';
		$saved['art_height_unit_tablet'] = $units['tablet'] !== $units['desktop'] ? $units['tablet'] : '';
		$saved['art_height_mobile']      = $heights['mobile'] !== $heights['tablet'] ? $heights['mobile'] : '';
		$saved['art_height_unit_mobile'] = $units['mobile'] !== $units['tablet'] ? $units['mobile'] : '';
		return $saved;
	}

	/**
	 * Until the Ring's opening took an image and text together, In the
	 * opening (art_opening_content) showed one: Image, Text or Nothing. The
	 * other one, kept from an earlier choice, is cleared, so it shows as it
	 * did (not something left out then).
	 *
	 * @param array $saved A slider's (or an effect's layout) saved settings.
	 * @return array
	 */
	public static function merge_opening_content( $saved ) {
		if ( ! $saved || ! array_key_exists( 'art_opening_content', $saved ) ) {
			return $saved;
		}
		$content = $saved['art_opening_content'];
		unset( $saved['art_opening_content'] );
		if ( 'image' !== $content ) {
			$saved['art_opening_image'] = 0;
		}
		if ( 'text' !== $content ) {
			$saved['art_opening_text'] = '';
		}
		return $saved;
	}

	/**
	 * The thumbnails' Rounded corners and Frame got their switches: on where
	 * a slider saved before them had a radius above 0 on any device / a frame
	 * wider than 0, so it shows as it did. Off, the radius and width are the
	 * defaults, for when they are turned on.
	 *
	 * @param array $saved A slider's (or an effect's layout) saved settings.
	 * @return array
	 */
	public static function merge_thumbs_switches( $saved ) {
		if ( ! $saved ) {
			return $saved;
		}
		if ( ! isset( $saved['thumbs_rounded'] ) ) {
			$rounded = false;
			foreach ( array( 'thumbs_radius', 'thumbs_radius_tablet', 'thumbs_radius_mobile' ) as $key ) {
				if ( isset( $saved[ $key ] ) && '' !== (string) $saved[ $key ] && (int) $saved[ $key ] > 0 ) {
					$rounded = true;
				}
			}
			$saved['thumbs_rounded'] = $rounded;
			if ( ! $rounded ) {
				$saved['thumbs_radius']        = self::fields()['thumbs_radius']['default'];
				$saved['thumbs_radius_tablet'] = '';
				$saved['thumbs_radius_mobile'] = '';
			}
		}
		if ( ! isset( $saved['thumbs_border'] ) ) {
			// Never saved: the default 1px frame it had.
			$framed                 = ! isset( $saved['thumbs_border_width'] ) || (int) $saved['thumbs_border_width'] > 0;
			$saved['thumbs_border'] = $framed;
			if ( ! $framed ) {
				$saved['thumbs_border_width'] = self::fields()['thumbs_border_width']['default'];
			}
		}
		return $saved;
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
	 * A phone value still at its old default (OLD_MOBILE_DEFAULTS) made empty:
	 * the phone then inherits the larger screen's.
	 *
	 * @param array $values Saved settings, or an effect's Layout values.
	 * @return array
	 */
	public static function inherit_old_mobile_defaults( $values ) {
		foreach ( self::OLD_MOBILE_DEFAULTS as $key => $old ) {
			if ( isset( $values[ $key . '_mobile' ] ) && (string) $old === (string) $values[ $key . '_mobile' ] ) {
				$values[ $key . '_mobile' ] = '';
			}
		}
		return $values;
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
	 * Until Zoom on hover was a setting, Art's photos always zoomed under the
	 * pointer: an Art slider saved before keeps it (and so do its saved
	 * Layout values, layouts['art']).
	 *
	 * @param array $saved A slider's saved settings.
	 * @return array
	 */
	public static function merge_hover_zoom( $saved ) {
		if ( ! is_array( $saved ) || ! $saved ) {
			return $saved;
		}
		if ( ! array_key_exists( 'image_hover_zoom', $saved ) && isset( $saved['effect'] ) && 'art' === $saved['effect'] ) {
			$saved['image_hover_zoom'] = true;
		}
		if ( isset( $saved['layouts']['art'] ) && is_array( $saved['layouts']['art'] ) && ! array_key_exists( 'image_hover_zoom', $saved['layouts']['art'] ) ) {
			$saved['layouts']['art']['image_hover_zoom'] = true;
		}
		return $saved;
	}
}
