<?php
/**
 * Saving: sanitize() and the cleaning of each kind of value (font sizes and
 * weights, colors, captions and descriptions, the collage's orders and
 * sizes, classes and id). Used by WP_Swiper_Settings, so `self::` is that
 * class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Settings_Sanitize {

	/**
	 * Sanitize raw form input against the schema.
	 *
	 * @param array $input Raw $_POST values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$clean = array( '_version' => 7 ) + self::sanitize_fields( $input );

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

				case 'cells': // Art's Grid shapes layout, "4x4:0,0,1,…;1=100"; '' = the default one.
					$clean[ $key ] = WP_Swiper_Art::sanitize_cells( null === $value ? '' : (string) $value );
					break;
			}
		}

		return $clean;
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
}
