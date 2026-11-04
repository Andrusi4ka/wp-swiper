<?php
/**
 * A slider's config for slider.js (data-config): Swiper's options, zoom,
 * the lightbox's, and the numbers they need (slides per view, loop copies,
 * the thumbnails' ratio). Used by WP_Swiper_Render, so `self::` is that
 * class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Render_Config {

	/**
	 * Swiper config passed to JS through a data attribute.
	 *
	 * @param array $settings Slider settings.
	 * @param int   $count    Number of real slides.
	 * @param int   $copies   How many times the markup repeats them (loop mode).
	 * @param bool  $videos   Some slides are videos.
	 * @return array
	 */
	private static function js_config( $settings, $count, $copies, $videos ) {
		$expand  = WP_Swiper_Settings::is_expand( $settings );
		$marquee = WP_Swiper_Settings::is_marquee( $settings );
		$zoom    = self::zoom_scales( $settings );

		return array(
			// Video slides: how they play (frontend.js videos()).
			'video'         => $videos ? array(
				'autoplay' => (bool) $settings['video_autoplay'],
				'muted'    => (bool) $settings['video_muted'],
				'loop'     => (bool) $settings['video_loop'],
				// Without autoplay the visitor starts it: the controls then always show.
				'controls' => $settings['video_controls'] || ! $settings['video_autoplay'],
				'pause'    => (bool) $settings['video_pause'],
				// Waits for the end of a video: the slider's autoplay (not the marquee)
				// and the video's own; it then plays once.
				'wait'     => $settings['video_wait'] && $settings['video_autoplay'] && $settings['autoplay'] && ! $marquee,
				'preload'  => $settings['video_preload'],
			) : false,
			'effect'        => $settings['effect'],
			// The effect shows exactly one slide (fade, cube...): no slides per view.
			'single'        => in_array( $settings['effect'], WP_Swiper_Settings::SINGLE_SLIDE_EFFECTS, true ),
			'direction'     => $settings['direction'],
			'speed'         => $settings['speed'],
			'loop'          => self::loops( $settings, $count ),
			// Real number of slides; the markup holds $copies copies of them.
			'slideCount'    => $count,
			'copies'        => $copies,
			// Effect depth shading; never with "Contain", where it would cover the
			// empty band around the image too.
			'shadows'       => $settings['effect_shadows'] && 'contain' !== $settings['image_fit'] && 'flip' !== $settings['effect'],
			// Depth and stretch per device ({ mobile, tablet, desktop }).
			'coverflow'     => 'coverflow' === $settings['effect'] ? array(
				'rotate'  => $settings['coverflow_rotate'],
				'depth'   => array_map( 'intval', WP_Swiper_Settings::responsive( $settings, 'coverflow_depth' ) ),
				'stretch' => array_map( 'intval', WP_Swiper_Settings::responsive( $settings, 'coverflow_stretch' ) ),
				'scale'   => $settings['coverflow_scale'],
			) : false,
			'cube'          => 'cube' === $settings['effect'] ? array(
				'shadow'       => $settings['cube_shadow'],
				'offset'       => $settings['cube_shadow_offset'],
				'scale'        => $settings['cube_shadow_scale'],
				// Not with "Contain": it would shade the empty band around the image too.
				'slideShadows' => $settings['cube_slide_shadows'] && 'contain' !== $settings['image_fit'],
			) : false,
			'creative'      => 'creative' === $settings['effect'] ? array(
				'style'    => $settings['creative_style'],
				'carousel' => WP_Swiper_Settings::carousel( $settings ),
			) : false,
			'cards'         => 'cards' === $settings['effect'] ? array(
				// 5..15 (wider ranges were allowed before): below, the far cards fly
				// off the page; above, even three cards spread past the block.
				'offset' => max( 5, min( 15, (int) $settings['cards_offset'] ) ),
				'rotate' => $settings['cards_rotate'],
				'tilt'   => $settings['cards_tilt'],
			) : false,
			// The thumbnails strip, a Swiper of its own (frontend.js thumbs()).
			'thumbs'        => WP_Swiper_Settings::is_thumbs( $settings ) ? array(
				'perView' => WP_Swiper_Settings::responsive( $settings, 'thumbs_per_view' ),
				// "Thumbnail height" per device, px; 0 = from perView (frontend.js).
				'height'  => array_map( 'intval', WP_Swiper_Settings::responsive( $settings, 'thumbs_height' ) ),
				'gap'     => WP_Swiper_Settings::responsive( $settings, 'thumbs_gap' ),
				'center'  => 'center' === $settings['thumbs_align'],
				// Per device, a column beside the slider: frontend.js works out its width
				// from the thumbnails' proportions and frame (its width, one side).
				'side'    => WP_Swiper_Settings::thumbs_side( $settings ),
				'ratio'   => self::thumbs_ratio( $settings ),
				'frame'   => (int) $settings['thumbs_border_width'],
				// "Arrows" (Controls): on the strip, to the previous / next slide.
				'arrows'  => (bool) $settings['navigation'],
				// "Transition": the slides fade instead of sliding.
				'fade'    => 'fade' === $settings['thumbs_transition'],
			) : false,
			'autoplay'      => $settings['autoplay'] ? array(
				'delay'             => $settings['autoplay_delay'],
				'pauseOnMouseEnter' => $settings['pause_on_hover'],
			) : false,
			// The marquee runs on its own: no controls (its own autoplay, frontend.js).
			// Thumbnails: the arrows are the strip's (`thumbs` above), not the slider's.
			'navigation'    => $settings['navigation'] && ! $marquee && ! WP_Swiper_Settings::is_thumbs( $settings ),
			'pagination'    => 'none' === $settings['pagination'] || $marquee ? false : $settings['pagination'],
			'keyboard'      => $settings['keyboard'] && ! $marquee,
			'marquee'       => $marquee ? array(
				'speed'   => $settings['marquee_speed'],
				'pause'   => $settings['pause_on_hover'],
				// Left to right (a vertical slider: top to bottom).
				'reverse' => 'reverse' === $settings['marquee_direction'],
			) : false,
			'lightbox'      => $settings['lightbox'] ? array(
				// Corners of the image and of the strip's thumbnails, per device.
				'radius'      => WP_Swiper_Settings::lightbox_image_radius( $settings ),
				'thumbRadius' => self::lightbox_radius( $settings, 'lightbox_thumbs_radius', 'thumbs_radius' ),
				// The strip again under the lightbox image (sized like `thumbs` above).
				'thumbs' => WP_Swiper_Settings::is_thumbs( $settings ) && ! empty( $settings['lightbox_thumbs'] ),
				// Video slides open in it too ("Videos in the lightbox").
				'videos' => $videos && ! empty( $settings['lightbox_videos'] ),
			) : false,
			// Slides as wide as their images ("Width from image"): Swiper's "auto".
			'autoWidth'     => WP_Swiper_Settings::is_auto_width( $settings ),
			'slidesPerView' => WP_Swiper_Settings::responsive( $settings, 'slides_per_view' ),
			'group'         => WP_Swiper_Settings::groups( $settings ),
			// With a separator, at least its size (WP_Swiper_Settings::gaps()).
			'spaceBetween'  => WP_Swiper_Settings::gaps( $settings ),
			// Image proportions (frontend.js distribute()): width / height per
			// device (false: the images fill the row) and where the free width
			// goes; spaceBetween is then the least space between the images.
			'distribute'    => self::distribute( $settings ),
			// Slide effect only (the editor offers it there; coverflow, cards and
			// the creative carousel centre on their own).
			'centered'      => self::centered( $settings ),
			// Height "Auto": frontend.js sizes the slider to its parent block, on
			// the devices that have it (false: none).
			'fill'          => WP_Swiper_Settings::fill_config( $settings ),
			// "Zoom the main image" (frontend.js zoom()): from one scale to the other.
			'zoom'          => WP_Swiper_Settings::is_zoom( $settings ) ? array(
				'duration' => WP_Swiper_Settings::zoom_duration( $settings ),
				'from'     => $zoom[0],
				'to'       => $zoom[1],
			) : false,
			'expand'        => $expand ? array(
				'active'   => WP_Swiper_Settings::responsive( $settings, 'active_width' ),
				'visible'  => WP_Swiper_Settings::expand_visible( $settings ),
				// The current slide stays in the middle (frontend.js; Swiper itself
				// keeps left-aligned slides in this layout).
				'centered' => self::centered( $settings ),
			) : false,
		);
	}

	/**
	 * One of the nine points of an image ("Zoom towards", "Image position")
	 * as a CSS position.
	 *
	 * @param string $name center, top-left, top... bottom-right.
	 * @return string "x% y%"; the middle for an unknown one.
	 */
	private static function point( $name ) {
		$points = array(
			'center'       => '50% 50%',
			'top-left'     => '0% 0%',
			'top'          => '50% 0%',
			'top-right'    => '100% 0%',
			'left'         => '0% 50%',
			'right'        => '100% 50%',
			'bottom-left'  => '0% 100%',
			'bottom'       => '50% 100%',
			'bottom-right' => '100% 100%',
		);
		return isset( $points[ $name ] ) ? $points[ $name ] : '50% 50%';
	}

	/**
	 * "Zoom the main image": the image's scale when its slide comes up and
	 * when the zoom ends (zoom in: 1 to larger; zoom out: back to 1).
	 *
	 * @param array $settings Slider settings.
	 * @return float[] [ from, to ]
	 */
	private static function zoom_scales( $settings ) {
		$large = round( 1 + max( 2, min( 50, (int) $settings['zoom_amount'] ) ) / 100, 2 );
		return 'out' === $settings['zoom_direction'] ? array( $large, 1 ) : array( 1, $large );
	}

	/**
	 * A lightbox radius per device: its own where set, else (empty, after
	 * the inheritance from the larger screens) the slider's on that device.
	 *
	 * @param array  $settings Slider settings.
	 * @param string $key      The lightbox's field.
	 * @param string $fallback The slider's field (Layout).
	 * @return array [ 'desktop' => int, 'tablet' => int, 'mobile' => int ]
	 */
	public static function lightbox_radius( $settings, $key, $fallback ) {
		$own    = WP_Swiper_Settings::responsive( $settings, $key );
		$slider = WP_Swiper_Settings::responsive( $settings, $fallback );

		foreach ( $own as $device => $radius ) {
			$own[ $device ] = (int) ( '' === $radius ? $slider[ $device ] : $radius );
		}
		return $own;
	}

	/**
	 * What the lightbox shows for a slide (frontend.js lightboxTrigger()): an
	 * image's full size; with "Videos in the lightbox", a video's file or a
	 * YouTube / Vimeo video, with its poster and proportions. Nothing: the
	 * slide does not open it.
	 *
	 * @param array $slide    Slide data.
	 * @param array $settings Slider settings.
	 * @param float $ratio    The slide's width / height.
	 */
	private static function lightbox_attrs( $slide, $settings, $ratio ) {
		if ( ! $settings['lightbox'] ) {
			return;
		}
		if ( ! $slide['video'] ) {
			echo ' data-wp-swiper-full="' . esc_url( wp_get_attachment_image_url( $slide['id'], 'full' ) ) . '"';
			return;
		}
		if ( empty( $settings['lightbox_videos'] ) ) {
			return;
		}
		if ( $slide['embed_info'] ) {
			echo ' data-wp-swiper-embed="' . esc_attr( wp_json_encode( self::embed_data( $slide ) ) ) . '"';
		} else {
			echo ' data-wp-swiper-video="' . esc_url( wp_get_attachment_url( $slide['id'] ) ) . '"';
		}
		echo ' data-wp-swiper-poster="' . esc_url( self::poster_url( $slide ) ) . '" data-wp-swiper-ratio="' . esc_attr( $ratio ) . '"';
	}

	/**
	 * Thumbnail proportions on each device as width / height.
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => float, 'tablet' => float, 'mobile' => float ]
	 */
	private static function thumbs_ratio( $settings ) {
		return array_map(
			function ( $ratio ) {
				return round( $ratio[0] / $ratio[1], 4 );
			},
			WP_Swiper_Settings::thumbs_ratio( $settings )
		);
	}

	/**
	 * Image proportions and Distribution for frontend.js.
	 *
	 * @param array $settings Slider settings.
	 * @return array|false [ 'ratio' => [ device => float|false ], 'mode' => 'between'|'around' ]
	 */
	private static function distribute( $settings ) {
		$ratios = WP_Swiper_Settings::slide_ratios( $settings );
		if ( ! $ratios ) {
			return false;
		}
		return array(
			'ratio' => array_map(
				function ( $ratio ) {
					return $ratio ? round( $ratio[0] / $ratio[1], 4 ) : false;
				},
				$ratios
			),
			'mode'  => 'around' === $settings['slide_distribution'] ? 'around' : 'between',
		);
	}

	/**
	 * "Center active slide": a Slide effect setting (a value saved for another
	 * effect before each effect kept its own Layout values is ignored).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	private static function centered( $settings ) {
		return 'slide' === $settings['effect'] && ! empty( $settings['centered_slides'] );
	}

	/**
	 * @param array $settings Slider settings.
	 * @param int   $count    Number of real slides.
	 * @return bool
	 */
	private static function loops( $settings, $count ) {
		// The marquee always loops.
		return ( $settings['loop'] || WP_Swiper_Settings::is_marquee( $settings ) ) && $count > 1;
	}

	/**
	 * Largest number of slides visible at once across breakpoints.
	 *
	 * @param array $settings Slider settings.
	 * @return float
	 */
	private static function max_per_view( $settings ) {
		if ( WP_Swiper_Settings::is_expand( $settings ) ) {
			// Narrow slides define the grid (the active one is widened in CSS):
			// they share what the active slide leaves, so 100% holds this many.
			$active  = WP_Swiper_Settings::responsive( $settings, 'active_width' );
			$visible = WP_Swiper_Settings::expand_visible( $settings );
			$max     = 1;
			foreach ( $active as $device => $width ) {
				$max = max( $max, 100 * ( $visible[ $device ] - 1 ) / ( 100 - $width ) );
			}
			return $max;
		}
		if ( in_array( $settings['effect'], WP_Swiper_Settings::SINGLE_SLIDE_EFFECTS, true ) ) {
			return 1;
		}
		if ( WP_Swiper_Settings::is_auto_width( $settings ) ) {
			return self::AUTO_WIDTH_VISIBLE;
		}
		return max( WP_Swiper_Settings::responsive( $settings, 'slides_per_view' ) );
	}

	/**
	 * Slides Swiper needs for loop mode: its own minimum (visible + looped
	 * slides) plus one spare, since at the bare minimum it misplaces slides.
	 *
	 * @param array $settings Slider settings.
	 * @return int
	 */
	private static function loop_min_slides( $settings ) {
		$centered = ! WP_Swiper_Settings::is_expand( $settings )
			&& ( self::centered( $settings ) || 'coverflow' === $settings['effect'] );
		$visible  = (int) ceil( self::max_per_view( $settings ) );

		if ( $centered && 0 === $visible % 2 ) {
			++$visible;
		}

		// Swiper loops a whole group at a time.
		$groups = WP_Swiper_Settings::groups( $settings );
		$group  = $groups ? max( $groups ) : 1;
		$looped = $centered ? max( $group, (int) ceil( $visible / 2 ) ) : $group;

		// Cards: Swiper keeps extra slides on both sides of the stack in loop
		// mode (loopAdditionalSlides: 3 with the moving tilt, else 2) and, with
		// fewer, shuffles them and skips one (measured: 7 / 5 slides needed).
		if ( 'cards' === $settings['effect'] ) {
			return $visible + 2 * ( $settings['cards_tilt'] ? 3 : 2 ) + 1;
		}

		// Creative "Carousel": the neighbours in view on both sides, kept by
		// Swiper as loopAdditionalSlides (frontend.js), plus one spare each side.
		$carousel = WP_Swiper_Settings::carousel( $settings );
		if ( $carousel ) {
			return 1 + 2 * ( $carousel['neighbours'] + 1 ) + 1;
		}

		return $visible + $looped + 1;
	}

	/**
	 * How many times to repeat the slides so loop mode has enough of them.
	 *
	 * @param array $settings Slider settings.
	 * @param int   $count    Number of real slides.
	 * @return int
	 */
	private static function copies( $settings, $count ) {
		if ( ! self::loops( $settings, $count ) ) {
			return 1;
		}
		return (int) min( 20, max( 1, ceil( self::loop_min_slides( $settings ) / $count ) ) );
	}
}
