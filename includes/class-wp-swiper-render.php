<?php
/**
 * Front end: [wp_swiper id="123"] shortcode and assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Render {

	const SHORTCODE = 'wp_swiper';

	/**
	 * "Width from image": how many slides may be in view is not known before
	 * the page lays them out, so loop mode plans for this many.
	 */
	const AUTO_WIDTH_VISIBLE = 8;

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'swiper', WP_SWIPER_URL . 'assets/vendor/swiper/swiper-bundle.min.css', array(), WP_SWIPER_SWIPER_VERSION );
		wp_register_style( 'wp-swiper', WP_SWIPER_URL . 'assets/css/frontend.css', array( 'swiper' ), wp_swiper_asset_version( 'assets/css/frontend.css' ) );

		wp_register_script( 'swiper', WP_SWIPER_URL . 'assets/vendor/swiper/swiper-bundle.min.js', array(), WP_SWIPER_SWIPER_VERSION, true );
		wp_register_script( 'wp-swiper', WP_SWIPER_URL . 'assets/js/frontend.js', array( 'swiper' ), wp_swiper_asset_version( 'assets/js/frontend.js' ), true );
		wp_localize_script(
			'wp-swiper',
			'wpSwiperI18n',
			array(
				'dialog' => __( 'Image viewer', 'wp-swiper' ),
				'close'  => __( 'Close', 'wp-swiper' ),
				'prev'   => __( 'Previous image', 'wp-swiper' ),
				'next'   => __( 'Next image', 'wp-swiper' ),
			)
		);

		// Load the CSS in <head> when we already know the page uses a slider,
		// so there is no flash of unstyled images. Otherwise the shortcode
		// enqueues it on demand.
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			wp_enqueue_style( 'wp-swiper' );
		}
	}

	/**
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			self::SHORTCODE
		);

		return self::render( absint( $atts['id'] ), $atts['class'] );
	}

	/**
	 * A published slider's markup (and its assets enqueued).
	 *
	 * @param int    $post_id Slider ID.
	 * @param string $class   Extra CSS classes.
	 * @return string
	 */
	public static function render( $post_id, $class = '' ) {
		$post = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || WP_Swiper_Settings::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return current_user_can( 'edit_posts' )
				? '<p class="wp-swiper-notice">' . esc_html__( 'WP Swiper: slider not found or not published.', 'wp-swiper' ) . '</p>'
				: '';
		}

		return self::render_slider( $post_id, WP_Swiper_Settings::get_slides( $post_id ), WP_Swiper_Settings::get( $post_id ), $class );
	}

	/**
	 * Markup of a slider from given slides and settings (the saved ones, or
	 * those of the edit screen for its preview).
	 *
	 * @param int    $post_id  Slider ID.
	 * @param array  $slides   Slides (see WP_Swiper_Settings::get_slides()).
	 * @param array  $settings Settings over the defaults (see WP_Swiper_Settings::get()).
	 * @param string $class    Extra CSS classes.
	 * @return string
	 */
	public static function render_slider( $post_id, $slides, $settings, $class = '' ) {
		// Images and videos from the media library (ones deleted since are skipped).
		// A video slide has no link: a click on it is the player's.
		$slides = array_map(
			function ( $slide ) {
				$slide['video'] = WP_Swiper_Settings::is_video( $slide['id'] );
				if ( $slide['video'] ) {
					$slide['link'] = '';
				}
				return $slide;
			},
			array_filter(
				$slides,
				function ( $slide ) {
					return WP_Swiper_Settings::is_slide_media( $slide['id'] );
				}
			)
		);

		if ( ! $slides ) {
			return current_user_can( 'edit_posts' )
				? '<p class="wp-swiper-notice">' . esc_html__( 'WP Swiper: this slider has no slides yet.', 'wp-swiper' ) . '</p>'
				: '';
		}

		wp_enqueue_style( 'wp-swiper' );
		wp_enqueue_script( 'wp-swiper' );

		// Fading slides (the Fade effect, Thumbnails with the Fade transition) have no
		// direction of their own: always horizontal (Direction is locked in the editor).
		if ( WP_Swiper_Settings::is_fade( $settings ) ) {
			$settings['direction'] = 'horizontal';
		}

		// Thumbnails: the strip switches the slides. Of Controls only the arrows
		// are used, on the strip (js_config()); the rest is locked in the editor.
		if ( WP_Swiper_Settings::is_thumbs( $settings ) ) {
			$settings['pagination'] = 'none';
			$settings['keyboard']   = false;
		}

		// A click opens either links or the lightbox, never a mix: any link turns the lightbox off.
		if ( array_filter( wp_list_pluck( $slides, 'link' ) ) ) {
			$settings['lightbox'] = false;
		}

		return self::markup( $post_id, array_values( $slides ), $settings, $class );
	}

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
			'coverflow'     => 'coverflow' === $settings['effect'] ? array(
				'rotate'  => $settings['coverflow_rotate'],
				'depth'   => $settings['coverflow_depth'],
				'stretch' => $settings['coverflow_stretch'],
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
				'speed' => $settings['marquee_speed'],
				'pause' => $settings['pause_on_hover'],
			) : false,
			'lightbox'      => $settings['lightbox'] ? array(
				// Corners of the image and of the strip's thumbnails, per device.
				'radius'      => self::lightbox_radius( $settings, 'lightbox_radius', 'border_radius' ),
				'thumbRadius' => self::lightbox_radius( $settings, 'lightbox_thumbs_radius', 'thumbs_radius' ),
				// The strip again under the lightbox image (sized like `thumbs` above).
				'thumbs' => WP_Swiper_Settings::is_thumbs( $settings ) && ! empty( $settings['lightbox_thumbs'] ),
			) : false,
			// Slides as wide as their images ("Width from image"): Swiper's "auto".
			'autoWidth'     => WP_Swiper_Settings::is_auto_width( $settings ),
			'slidesPerView' => WP_Swiper_Settings::responsive( $settings, 'slides_per_view' ),
			'group'         => WP_Swiper_Settings::groups( $settings ),
			'spaceBetween'  => WP_Swiper_Settings::responsive( $settings, 'space_between' ),
			// Slide effect only (the editor offers it there; coverflow, cards and
			// the creative carousel centre on their own).
			'centered'      => self::centered( $settings ),
			// Height "Auto": frontend.js sizes the slider to its parent block.
			'fill'          => WP_Swiper_Settings::is_auto_height( $settings ),
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
	 * A lightbox radius per device: its own where set, else (empty, after
	 * the inheritance from the larger screens) the slider's on that device.
	 *
	 * @param array  $settings Slider settings.
	 * @param string $key      The lightbox's field.
	 * @param string $fallback The slider's field (Layout).
	 * @return array [ 'desktop' => int, 'tablet' => int, 'mobile' => int ]
	 */
	private static function lightbox_radius( $settings, $key, $fallback ) {
		$own    = WP_Swiper_Settings::responsive( $settings, $key );
		$slider = WP_Swiper_Settings::responsive( $settings, $fallback );

		foreach ( $own as $device => $radius ) {
			$own[ $device ] = (int) ( '' === $radius ? $slider[ $device ] : $radius );
		}
		return $own;
	}

	/**
	 * A video slide's player. Its file is not loaded with the page: the
	 * address waits in data-src until frontend.js plays the slide (or loads
	 * the next one ahead), so the copies loop mode repeats load nothing.
	 * Muted in the markup when set: browsers only autoplay muted videos.
	 *
	 * @param array       $slide    Slide data.
	 * @param array|false $size     The video's width and height.
	 * @param array       $settings Slider settings.
	 * @return string
	 */
	private static function video( $slide, $size, $settings ) {
		$poster = WP_Swiper_Settings::poster( $slide );
		$poster = $poster ? wp_get_attachment_image_url( $poster, 'full' ) : '';

		return sprintf(
			'<video class="wp-swiper__image wp-swiper__video" data-src="%1$s"%2$s%3$s preload="none" playsinline%4$s%5$s%6$s></video>',
			esc_url( wp_get_attachment_url( $slide['id'] ) ),
			$size ? sprintf( ' width="%d" height="%d"', $size[0], $size[1] ) : '',
			$poster ? ' poster="' . esc_url( $poster ) . '"' : '',
			$settings['video_muted'] ? ' muted' : '',
			$settings['video_loop'] ? ' loop' : '',
			$settings['video_controls'] || ! $settings['video_autoplay'] ? ' controls' : ''
		);
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

	private static function markup( $post_id, $slides, $settings, $extra_class ) {
		$expand = WP_Swiper_Settings::is_expand( $settings );
		$fill   = WP_Swiper_Settings::is_auto_height( $settings );

		$classes = array(
			'wp-swiper',
			'wp-swiper--' . $post_id,
			'wp-swiper--effect-' . $settings['effect'],
			'wp-swiper--' . $settings['direction'],
			'wp-swiper--fit-' . $settings['image_fit'],
			! $fill && '%' === $settings['height_unit'] && max( WP_Swiper_Settings::responsive( $settings, 'height' ) ) ? 'wp-swiper--height-percent' : '',
			$expand ? 'wp-swiper--expand' : '',
			// Height "Auto": frontend.js sizes the slider to its parent block.
			$fill ? 'wp-swiper--fill' : '',
			WP_Swiper_Settings::is_auto_width( $settings ) ? 'wp-swiper--auto-width' : '',
			WP_Swiper_Settings::is_marquee( $settings ) ? 'wp-swiper--marquee' : '',
			WP_Swiper_Settings::is_carousel( $settings ) ? 'wp-swiper--carousel' : '',
			$settings['lightbox'] ? 'wp-swiper--lightbox' : '',
			$settings['image_border'] ? 'wp-swiper--border' : '',
			// Caption: a set weight overrides the tag's own; vertical text.
			'' !== $settings['caption_weight'] ? 'wp-swiper--caption-weight' : '',
			'horizontal' !== $settings['caption_orientation'] ? 'wp-swiper--caption-' . $settings['caption_orientation'] : '',
			// Caption background: none, or solid instead of the default gradient; text shadow.
			! $settings['caption_bg'] ? 'wp-swiper--caption-nobg' : ( 'solid' === $settings['caption_bg_style'] ? 'wp-swiper--caption-solid' : '' ),
			$settings['caption_shadow'] ? 'wp-swiper--caption-shadow' : '',
		);
		// Thumbnails: where the strip sits on each device ("--thumbs-left",
		// "--thumbs-tablet-top", "--thumbs-mobile-bottom"; one set of CSS rules per device).
		if ( WP_Swiper_Settings::is_thumbs( $settings ) ) {
			foreach ( WP_Swiper_Settings::responsive( $settings, 'thumbs_position' ) as $device => $position ) {
				$classes[] = 'wp-swiper--thumbs-' . ( 'desktop' === $device ? '' : $device . '-' ) . $position;
			}
		}
		if ( 'none' !== $settings['pagination'] ) {
			if ( 'horizontal' === $settings['direction'] ) {
				list( $edge, $align ) = explode( '-', $settings['pagination_position'] );

				$classes[] = 'wp-swiper--pag-' . $edge;  // top | bottom
				$classes[] = 'wp-swiper--pag-' . $align; // left | center | right
			} else {
				list( $side, $align ) = explode( '-', $settings['pagination_position_vertical'] );

				$classes[] = 'wp-swiper--pagv-' . $side;  // left | right
				$classes[] = 'wp-swiper--pagv-' . $align; // top | center | bottom
			}
			$classes[] = 'wp-swiper--pag-' . $settings['pagination_placement'];
		}
		// The slider's own CSS class (Advanced) plus any set where it is placed.
		$classes[] = WP_Swiper_Settings::sanitize_classes( $settings['css_class'] . ' ' . $extra_class );

		$style  = array(
			'--wp-swiper-speed:' . (int) $settings['speed'] . 'ms',
			'--wp-swiper-pagination-offset:' . (int) $settings['pagination_offset'] . 'px',
			'--swiper-navigation-color:' . $settings['navigation_color'],
		);
		// Creative "Carousel": the share of the slider the middle image takes.
		$carousel = WP_Swiper_Settings::carousel( $settings );
		if ( $carousel ) {
			$style[] = '--wp-swiper-carousel-width:' . $carousel['width'];
			// "Contain": the proportions of the image that sets the shared size
			// (CSS): the widest one, across a vertical slider the tallest.
			if ( 'contain' === $settings['image_fit'] ) {
				$ratios = array();
				foreach ( $slides as $slide ) {
					$size = WP_Swiper_Settings::media_size( $slide['id'] );
					if ( $size ) {
						$ratios[] = $size[0] / $size[1];
					}
				}
				if ( $ratios ) {
					$ratio   = 'horizontal' === $settings['direction'] ? max( $ratios ) : min( $ratios );
					$style[] = '--wp-swiper-carousel-ratio:' . round( $ratio, 4 );
				}
			}
		}
		// Cube: how dark its (black) shadow is, and room under the slider for it
		// (it falls `offset` below the cube and is blurred some 20px further).
		if ( 'cube' === $settings['effect'] && $settings['cube_shadow'] ) {
			$style[] = '--wp-swiper-cube-shadow-opacity:' . max( 0, min( 100, (int) $settings['cube_shadow_opacity'] ) ) / 100;
			$style[] = '--wp-swiper-cube-shadow-room:' . ( (int) $settings['cube_shadow_offset'] + 20 ) . 'px';
		}
		$suffix = array(
			'desktop' => '',
			'tablet'  => '-tablet',
			'mobile'  => '-mobile',
		);

		// Thumbnails: the look of the strip (its sizes come from frontend.js).
		$thumbs = WP_Swiper_Settings::is_thumbs( $settings );
		if ( $thumbs ) {
			foreach ( WP_Swiper_Settings::responsive( $settings, 'thumbs_spacing' ) as $device => $spacing ) {
				$style[] = '--wp-swiper-thumbs-spacing' . $suffix[ $device ] . ':' . (int) $spacing . 'px';
			}
			foreach ( WP_Swiper_Settings::thumbs_ratio( $settings ) as $device => $ratio ) {
				$style[] = '--wp-swiper-thumb-ratio' . $suffix[ $device ] . ':' . implode( ' / ', $ratio );
			}
			foreach ( WP_Swiper_Settings::responsive( $settings, 'thumbs_radius' ) as $device => $radius ) {
				$style[] = '--wp-swiper-thumb-radius' . $suffix[ $device ] . ':' . (int) $radius . 'px';
			}
			$style[] = '--wp-swiper-thumb-border-width:' . (int) $settings['thumbs_border_width'] . 'px';
			$style[] = '--wp-swiper-thumb-border-color:' . $settings['thumbs_border_color'];
			$style[] = '--wp-swiper-thumb-active-color:' . $settings['thumbs_active_color'];
			$style[] = '--wp-swiper-thumb-opacity:' . max( 10, min( 100, (int) $settings['thumbs_opacity'] ) ) / 100;
		}

		// Arrow height per device, where set (the CSS has the default sizes).
		if ( $settings['navigation'] ) {
			foreach ( WP_Swiper_Settings::responsive( $settings, 'navigation_size' ) as $device => $size ) {
				if ( '' !== $size ) {
					$style[] = '--wp-swiper-arrow-size' . $suffix[ $device ] . ':' . (int) $size . 'px';
				}
			}
		}

		// Height of the slides per device (0 = 500px), unless "Auto".
		if ( ! $fill ) {
			foreach ( WP_Swiper_Settings::css_heights( $settings ) as $device => $height ) {
				$style[] = '--wp-swiper-height' . $suffix[ $device ] . ':' . $height;
			}
		}

		// When a horizontal slider sizes itself (no free height to fill), every
		// slide takes the first image's proportions (the others are cropped or
		// fitted per "Image fit"); otherwise the tallest image would set the
		// height and leave gaps under the rest.
		$first = WP_Swiper_Settings::media_size( $slides[0]['id'] );
		if ( $first ) {
			$style[] = '--wp-swiper-ratio:' . (int) $first[0] . ' / ' . (int) $first[1];
		}
		// "Contain": where the image sits in its slide (slides are flex rows).
		if ( 'contain' === $settings['image_fit'] ) {
			$edge = array(
				'top'    => 'flex-start',
				'left'   => 'flex-start',
				'center' => 'center',
				'bottom' => 'flex-end',
				'right'  => 'flex-end',
			);
			if ( 'horizontal' === $settings['direction'] ) {
				$style[] = '--wp-swiper-align:' . $edge[ $settings['image_align'] ];
			} else {
				$style[] = '--wp-swiper-justify:' . $edge[ $settings['image_align_vertical'] ];
			}
		}
		// Caption typography.
		foreach ( WP_Swiper_Settings::responsive( $settings, 'caption_font_size' ) as $device => $size ) {
			$style[] = '--wp-swiper-caption-size' . $suffix[ $device ] . ':' . (int) $size . 'px';
		}
		$style[] = '--wp-swiper-caption-color:' . $settings['caption_color'];
		if ( $settings['caption_bg'] ) {
			// The background color at its opacity, and fully transparent for the
			// gradient's far end (same hue, so the fade does not go grey).
			list( $r, $g, $b ) = sscanf( $settings['caption_bg_color'], '#%02x%02x%02x' );
			$alpha              = max( 0, min( 100, (int) $settings['caption_bg_opacity'] ) ) / 100;
			$style[]            = sprintf( '--wp-swiper-caption-bg-from:rgba(%d,%d,%d,%s)', $r, $g, $b, $alpha );
			$style[]            = sprintf( '--wp-swiper-caption-bg-to:rgba(%d,%d,%d,0)', $r, $g, $b );
		}
		if ( '' !== $settings['caption_weight'] ) {
			$style[] = '--wp-swiper-caption-weight:' . (int) $settings['caption_weight'];
		}
		// Desktop 0 needs no variable (the CSS falls back to 0); tablet/mobile
		// are set whenever given (0 included), empty inherits.
		if ( $settings['border_radius'] ) {
			$style[] = '--wp-swiper-radius:' . (int) $settings['border_radius'] . 'px';
		}
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			if ( '' !== $settings[ 'border_radius_' . $device ] ) {
				$style[] = '--wp-swiper-radius-' . $device . ':' . (int) $settings[ 'border_radius_' . $device ] . 'px';
			}
		}
		// Image border: width per device (0 = none there), one color.
		if ( $settings['image_border'] ) {
			foreach ( WP_Swiper_Settings::responsive( $settings, 'image_border_width' ) as $device => $width ) {
				$style[] = '--wp-swiper-border-width' . $suffix[ $device ] . ':' . (int) $width . 'px';
			}
			$style[] = '--wp-swiper-border-color:' . $settings['image_border_color'];
		}
		if ( $expand ) {
			foreach ( WP_Swiper_Settings::responsive( $settings, 'active_width' ) as $device => $width ) {
				$style[] = '--wp-swiper-active-width' . $suffix[ $device ] . ':' . (float) $width . '%';
			}
		}

		$count             = count( $slides );
		$copies            = self::copies( $settings, $count );
		$config            = self::js_config( $settings, $count, $copies, in_array( true, wp_list_pluck( $slides, 'video' ), true ) );
		$pagination_inside = 'inside' === $settings['pagination_placement'];

		ob_start();
		?>
		<?php // The slider's own id (Custom), if any. ?>
		<div<?php echo '' !== $settings['css_id'] ? ' id="' . esc_attr( $settings['css_id'] ) . '"' : ''; ?> class="<?php echo esc_attr( implode( ' ', array_filter( $classes ) ) ); ?>"<?php echo $style ? ' style="' . esc_attr( implode( ';', $style ) ) . '"' : ''; ?>>
			<div class="swiper" data-wp-swiper="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
				<div class="swiper-wrapper">
					<?php
					// With few slides, loop mode needs the set repeated; data-wp-swiper-index
					// keeps the real index so pagination counts real slides only.
					for ( $copy = 0; $copy < $copies; $copy++ ) {
						foreach ( $slides as $index => $slide ) {
							self::render_slide( $slide, $index, $settings, 0 === $copy && 0 === $index );
						}
					}
					?>
				</div>

				<?php if ( $config['navigation'] ) : ?>
					<button type="button" class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Previous slide', 'wp-swiper' ); ?>"></button>
					<button type="button" class="swiper-button-next" aria-label="<?php esc_attr_e( 'Next slide', 'wp-swiper' ); ?>"></button>
				<?php endif; ?>
				<?php
				// Over the slides: inside .swiper, so its position and offset are
				// measured from the slides themselves (whatever room an effect
				// keeps around them, e.g. under a cube or beside a card).
				if ( $config['pagination'] && $pagination_inside ) :
					?>
					<div class="swiper-pagination swiper-no-swiping"></div>
				<?php endif; ?>
			</div>

			<?php
			// Thumbnails: one per real slide (the slider's own markup may repeat
			// them). After the slider in the markup wherever it shows (CSS order).
			if ( $thumbs ) :
				?>
				<div class="swiper wp-swiper__thumbs">
					<div class="swiper-wrapper">
						<?php foreach ( $slides as $index => $slide ) : ?>
							<?php /* translators: %d: slide number. */ ?>
							<div class="swiper-slide<?php echo $slide['video'] ? ' wp-swiper__thumb--video' : ''; ?>" data-wp-swiper-index="<?php echo (int) $index; ?>" role="button" tabindex="0" aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide %d', 'wp-swiper' ), $index + 1 ) ); ?>">
								<?php
								// A video: its poster (CSS adds a play mark), or a dark box without one.
								$thumb_id = $slide['video'] ? WP_Swiper_Settings::poster( $slide ) : $slide['id'];
								if ( $thumb_id ) {
									echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
										$thumb_id,
										'medium',
										false,
										array(
											'class'    => 'wp-swiper__thumb-image',
											'loading'  => 'lazy',
											'decoding' => 'async',
											'alt'      => '',
										)
									);
								} else {
									echo '<span class="wp-swiper__thumb-image wp-swiper__thumb-blank"></span>';
								}
								?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( $config['thumbs']['arrows'] ) : ?>
						<button type="button" class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Previous slide', 'wp-swiper' ); ?>"></button>
						<button type="button" class="swiper-button-next" aria-label="<?php esc_attr_e( 'Next slide', 'wp-swiper' ); ?>"></button>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php // Above, below or beside the slides: outside .swiper, which clips. ?>
			<?php if ( $config['pagination'] && ! $pagination_inside ) : ?>
				<div class="swiper-pagination swiper-no-swiping"></div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param array $slide    Slide data.
	 * @param int   $index    Real slide index.
	 * @param array $settings Slider settings.
	 * @param bool  $eager    Load the image right away (first slide).
	 */
	private static function render_slide( $slide, $index, $settings, $eager ) {
		// The media's proportions size its box (.wp-swiper__media) with "Contain",
		// and the slide itself with "Width from image".
		$size  = WP_Swiper_Settings::media_size( $slide['id'] );
		$ratio = $size ? round( $size[0] / $size[1], 4 ) : 1;

		if ( $slide['video'] ) {
			$image = self::video( $slide, $size, $settings );
		} else {
			// Full size with its srcset: the browser picks the file that fits the slide.
			$image = wp_get_attachment_image(
				$slide['id'],
				'full',
				false,
				array(
					'class'    => 'wp-swiper__image',
					'loading'  => $eager ? 'eager' : 'lazy',
					'decoding' => 'async',
				)
			);
		}
		?>
		<div class="swiper-slide<?php echo $slide['video'] ? ' wp-swiper-slide--video' : ''; ?>" data-wp-swiper-index="<?php echo (int) $index; ?>" style="--wp-swiper-img-ratio:<?php echo esc_attr( $ratio ); ?>"
			<?php // The lightbox shows the images (a video slide is not in it). ?>
			<?php if ( $settings['lightbox'] && ! $slide['video'] ) : ?>
				data-wp-swiper-full="<?php echo esc_url( wp_get_attachment_image_url( $slide['id'], 'full' ) ); ?>"
			<?php endif; ?>
		>
			<?php // Box of the visible image: the whole slide with "Cover", the letterboxed image with "Contain"; caption and corners follow it. ?>
			<div class="wp-swiper__media">
			<?php
			if ( $slide['link'] ) {
				printf(
					'<a class="wp-swiper__link" href="%s"%s>%s</a>',
					esc_url( $slide['link'] ),
					empty( $slide['new_tab'] ) ? '' : ' target="_blank" rel="noopener"',
					$image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
				);
			} else {
				echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
			}
			?>
			<?php if ( $slide['caption'] ) : ?>
				<?php
				// Outer block: position and background; inner tag: semantics and typography.
				$tag = in_array( $settings['caption_tag'], array( 'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $settings['caption_tag'] : 'div';
				?>
				<div class="wp-swiper__caption"><<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted tag name. ?> class="wp-swiper__caption-text"><?php echo esc_html( $slide['caption'] ); ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted tag name. ?>></div>
			<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
