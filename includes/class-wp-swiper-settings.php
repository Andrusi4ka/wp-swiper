<?php
/**
 * Slider settings schema: single source of truth for the admin form,
 * sanitization and the front-end config.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-swiper-settings-migrations.php';
require_once __DIR__ . '/trait-wp-swiper-settings-sanitize.php';
require_once __DIR__ . '/trait-wp-swiper-settings-text.php';
require_once __DIR__ . '/trait-wp-swiper-settings-media.php';

class WP_Swiper_Settings {

	use WP_Swiper_Settings_Migrations;
	use WP_Swiper_Settings_Sanitize;
	use WP_Swiper_Settings_Text;
	use WP_Swiper_Settings_Media;

	const POST_TYPE     = 'wp_swiper';
	const META_SLIDES   = '_wp_swiper_slides';
	const META_SETTINGS = '_wp_swiper_settings';

	/**
	 * Effects that always show exactly one slide.
	 */
	const SINGLE_SLIDE_EFFECTS = array( 'fade', 'cube', 'flip', 'cards', 'creative', 'thumbs', 'collage' );

	/**
	 * Effects that can be the background of their parent block ("Use as
	 * background"); Art: a grid stretches to the block, a ring or SVG shape is
	 * placed in it (Background size and position, WP_Swiper_Art::render()).
	 */
	const BACKGROUND_EFFECTS = array( 'slide', 'fade', 'collage', 'art' );

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
	 * Effects whose photos can zoom under the pointer (Zoom on hover): not
	 * those that turn in 3D (Cube, Flip, Coverflow, Cards).
	 */
	const HOVER_ZOOM_EFFECTS = array( 'slide', 'fade', 'creative', 'thumbs', 'collage', 'art' );

	/**
	 * The column widths a saved grid's square photos are measured for
	 * (merge_art_height()): a typical desktop, tablet and phone.
	 */
	const OLD_ART_WIDTHS = array(
		'desktop' => 1200,
		'tablet'  => 768,
		'mobile'  => 390,
	);

	/**
	 * The phone's own defaults until version 7 (the field's mobile value then
	 * started as this, not inheriting): field => value.
	 */
	const OLD_MOBILE_DEFAULTS = array(
		'description_lightbox_width' => 100,
		'collage_layout'             => 'masonry',
		'collage_count'              => 4,
		'collage_columns'            => 2,
		'collage_row_height'         => 140,
		'collage_gap'                => 6,
		'active_width'               => 70,
		'thumbs_per_view'            => 4,
	);

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
	 * The settings as drawn: with Rounded corners off, no radius anywhere
	 * on the slides (the lightbox has its own, lightbox_image_radius()); the
	 * same for the thumbnails' (and so the lightbox's, which take theirs
	 * unless they have their own), and with their Frame off, none.
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
		// Art's photos (and so the lightbox's that take theirs).
		if ( isset( $settings['art_rounded'] ) && ! $settings['art_rounded'] ) {
			$settings['art_radius']        = 0;
			$settings['art_radius_tablet'] = '';
			$settings['art_radius_mobile'] = '';
		}
		if ( empty( $settings['thumbs_rounded'] ) ) {
			$settings['thumbs_radius']        = 0;
			$settings['thumbs_radius_tablet'] = '';
			$settings['thumbs_radius_mobile'] = '';
		}
		if ( isset( $settings['thumbs_border'] ) && ! $settings['thumbs_border'] ) {
			$settings['thumbs_border_width'] = 0;
		}
		return $settings;
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
	 * "Zoom on hover": on, with an effect whose photos can (not those turning
	 * in 3D), where the pointer reaches them (not a background) and the main
	 * image does not zoom already.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function hover_zoom( $settings ) {
		return ! empty( $settings['image_hover_zoom'] )
			&& in_array( $settings['effect'], self::HOVER_ZOOM_EFFECTS, true )
			// No pointer reaches a background; the main image already zooms.
			&& ! self::is_background( $settings )
			&& ! self::is_zoom( $settings );
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
						'direction',
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
						'cube_shadow_color',
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
				// Art's Rounded corners and Image border here too (its own fields,
				// where a slider's are): the shadow under them on both.
				'look'        => array(
					'title'  => __( 'Look', 'wp-swiper' ),
					'fields' => array( 'border_rounded', 'border_radius', 'art_rounded', 'art_radius', 'image_border', 'image_border_width', 'image_border_color', 'art_border', 'art_border_width', 'art_border_color', 'image_hover_zoom', 'image_shadow', 'image_shadow_x', 'image_shadow_y', 'image_shadow_blur', 'image_shadow_spread', 'image_shadow_color' ),
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
					'fields' => array( 'background', 'background_class', 'art_bg_size', 'art_bg_position', 'background_overlay_color' ),
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
					'fields' => array( 'thumbs_opacity', 'thumbs_rounded', 'thumbs_radius', 'thumbs_border', 'thumbs_border_width', 'thumbs_border_color', 'thumbs_active_color' ),
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
			// After the Posters block (the video slides' list, class-wp-swiper-admin.php).
			'video'       => array(
				'video_playback' => array(
					'title'  => __( 'Playback', 'wp-swiper' ),
					'fields' => array( 'video_autoplay', 'video_muted', 'video_loop', 'video_pause', 'video_wait' ),
				),
				'video_player'   => array(
					'title'  => __( 'Player', 'wp-swiper' ),
					'fields' => array( 'video_controls', 'video_preload' ),
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
		// The cube's shadow: its opacity in a color of its own.
		$saved = self::merge_cube_shadow_opacity( $saved );
		// Rounded corners: on where a radius was saved.
		$saved = self::merge_rounded( $saved );
		// The thumbnails' Rounded corners and Frame: on where they had them.
		$saved = self::merge_thumbs_switches( $saved );
		// Art's Ring opening: on where it had one; what was in it, image or text.
		$saved = self::merge_art_opening( $saved );
		$saved = self::merge_opening_content( $saved );
		// Art: one Height for every Style (the rings' Size, the grids' Height until 3.1).
		$saved = self::merge_art_height( $saved );
		// Art's photos always zoomed under the pointer: Zoom on hover on there.
		$saved = self::merge_hover_zoom( $saved );
		if ( ! empty( $saved['layouts'] ) && is_array( $saved['layouts'] ) ) {
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_ratio_fields' ), array_filter( $saved['layouts'], 'is_array' ) );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_overlay_opacity' ), $saved['layouts'] );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_cube_shadow_opacity' ), $saved['layouts'] );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_rounded' ), $saved['layouts'] );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_thumbs_switches' ), $saved['layouts'] );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_art_opening' ), $saved['layouts'] );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_opening_content' ), $saved['layouts'] );
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_art_height' ), $saved['layouts'] );
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

		// Version 7: the phone inherits these from the larger screen (it had a
		// value of its own by default). One saved still at that old default
		// inherits now, in the other effects' Layout values too.
		if ( $saved && ( empty( $saved['_version'] ) || $saved['_version'] < 7 ) ) {
			$saved = self::inherit_old_mobile_defaults( $saved );
			if ( ! empty( $saved['layouts'] ) && is_array( $saved['layouts'] ) ) {
				$saved['layouts'] = array_map( array( __CLASS__, 'inherit_old_mobile_defaults' ), array_filter( $saved['layouts'], 'is_array' ) );
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
				// Art: Zoom on hover on by default (its photos always had it).
				if ( 'art' === $effect && isset( $defaults['image_hover_zoom'] ) ) {
					$layouts[ $effect ]['image_hover_zoom'] = true;
				}
			}
		}
		return $layouts;
	}
}
