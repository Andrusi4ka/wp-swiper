<?php
/**
 * Front end: [wp_swiper id="123"] shortcode and assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-swiper-render-assets.php';
require_once __DIR__ . '/trait-wp-swiper-render-config.php';
require_once __DIR__ . '/trait-wp-swiper-render-media.php';
require_once __DIR__ . '/trait-wp-swiper-render-collage.php';

class WP_Swiper_Render {

	use WP_Swiper_Render_Assets;
	use WP_Swiper_Render_Config;
	use WP_Swiper_Render_Media;
	use WP_Swiper_Render_Collage;

	const SHORTCODE = 'wp_swiper';

	/**
	 * "Width from image": how many slides may be in view is not known before
	 * the page lays them out, so loop mode plans for this many.
	 */
	const AUTO_WIDTH_VISIBLE = 8;

	/**
	 * What a per-device custom property's name ends with on each device
	 * ("--wp-swiper-height", "--wp-swiper-height-tablet"...).
	 */
	const SUFFIX = array(
		'desktop' => '',
		'tablet'  => '-tablet',
		'mobile'  => '-mobile',
	);

	/**
	 * How deep in Content in a place's shortcodes (collage_content()): inside
	 * one, a content's shortcodes do not run again.
	 *
	 * @var int
	 */
	private static $content_depth = 0;

	/**
	 * Whether the page's first image got fetchpriority="high" (render_slide()).
	 *
	 * @var bool
	 */
	private static $priority = false;

	/*
	 * Stylesheets a slider needed once <head> was out (a Woodmart HTML block,
	 * the header or footer, a widget): printed in the footer, the images show
	 * unstyled for a moment. Remembered per page (late_key()) in this option,
	 * the next visit has them in <head>; once more pages than LATE_PAGES need
	 * them, every page has them ('*': a slider shown site-wide).
	 */
	const LATE_OPTION = 'wp_swiper_late_styles';
	const LATE_PAGES  = 12;

	/**
	 * This page's stylesheets enqueued late (style()): handle => true.
	 *
	 * @var array
	 */
	private static $late = array();

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'remember_late_styles' ), 99 );
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
			return current_user_can( WP_Swiper_Settings::CAPABILITY )
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
		// Rounded corners off: no radius on the slides (the lightbox has its own switch).
		$settings = WP_Swiper_Settings::apply_rounded( $settings );
		// Images and videos from the media library (ones deleted since are skipped)
		// and YouTube / Vimeo links ("embed": the parsed link). A video slide has
		// no link: a click on it is the player's.
		$slides = array_map(
			function ( $slide ) {
				$slide['embed_info'] = empty( $slide['embed'] ) ? false : WP_Swiper_Settings::parse_embed( $slide['embed'] );
				$slide['video']      = $slide['embed_info'] || WP_Swiper_Settings::is_video( $slide['id'] );
				if ( $slide['video'] ) {
					$slide['link'] = '';
				}
				return $slide;
			},
			array_filter( $slides, array( 'WP_Swiper_Settings', 'is_slide' ) )
		);

		if ( ! $slides ) {
			return current_user_can( WP_Swiper_Settings::CAPABILITY )
				? '<p class="wp-swiper-notice">' . esc_html__( 'WP Swiper: this slider has no slides yet.', 'wp-swiper' ) . '</p>'
				: '';
		}

		// Art: drawn by its own class, with its own settings and assets only.
		if ( WP_Swiper_Art::is_art( $settings ) ) {
			return WP_Swiper_Art::render( $post_id, $slides, $settings, $class );
		}

		if ( WP_Swiper_Settings::is_collage( $settings ) ) {
			self::enqueue_collage();
		} else {
			self::enqueue( self::needs_swiper( $settings ), WP_Swiper_Settings::is_thumbs( $settings ) );
		}

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

		// Continuous scroll runs on its own: no pagination (nor its classes and look).
		if ( WP_Swiper_Settings::is_marquee( $settings ) ) {
			$settings['pagination'] = 'none';
		}

		// Collage: not a slider. Its places crop their photos (they keep their
		// size whatever photo comes in) and it has no controls of its own.
		if ( WP_Swiper_Settings::is_collage( $settings ) ) {
			$settings['direction']  = 'horizontal';
			$settings['image_fit']  = 'cover';
			$settings['navigation'] = false;
			$settings['pagination'] = 'none';
			$settings['keyboard']   = false;
		}

		// The block's background (frontend.js background()): behind its content,
		// where no click or hover reaches it, so it plays on its own and has
		// nothing to click; it fills the block, so its images are cropped to fill
		// and Height is not used (the CSS takes the block's).
		$background = WP_Swiper_Settings::is_background( $settings );
		if ( $background ) {
			$settings['image_fit'] = 'cover';
			// On every device (tablet and mobile inherit it).
			$settings['height_unit']        = 'px';
			$settings['height_unit_tablet'] = '';
			$settings['height_unit_mobile'] = '';
			$settings['autoplay']       = true;
			$settings['loop']           = true;
			$settings['pause_on_hover'] = false;
			$settings['collage_pause']  = false;
			$settings['navigation']     = false;
			$settings['pagination']     = 'none';
			$settings['keyboard']       = false;
			$settings['mouse_drag']     = false;
			$settings['lightbox']       = false;
			$settings['video_autoplay'] = true;
			$settings['video_muted']    = true;
			$settings['video_controls'] = false;
			$slides                     = array_map(
				function ( $slide ) {
					$slide['link'] = '';
					return $slide;
				},
				$slides
			);
		}

		// A click opens either links or the lightbox, never a mix: any link turns the lightbox off.
		if ( array_filter( wp_list_pluck( $slides, 'link' ) ) ) {
			$settings['lightbox'] = false;
		}

		$html = self::markup( $post_id, array_values( $slides ), $settings, $class );

		// No block with the class around it: frontend.js leaves it a slider as
		// usual and shows this to those who can fix it.
		if ( $background && current_user_can( WP_Swiper_Settings::CAPABILITY ) ) {
			$html .= '<p class="wp-swiper-notice" data-wp-swiper-bg-missing hidden>' . esc_html(
				'' === $settings['background_class']
					? __( 'WP Swiper: this slider is set to be a background, but has no parent block class (Background).', 'wp-swiper' )
					/* translators: %s: CSS class name(s). */
					: sprintf( __( 'WP Swiper: no block with the class “%s” around this slider, so it is not a background. Give that class to its row or column.', 'wp-swiper' ), $settings['background_class'] )
			) . '</p>';
		}

		return $html;
	}

	private static function markup( $post_id, $slides, $settings, $extra_class ) {
		$background = WP_Swiper_Settings::is_background( $settings );
		$zoom       = WP_Swiper_Settings::is_zoom( $settings );
		$expand     = WP_Swiper_Settings::is_expand( $settings );
		$shadow     = WP_Swiper_Settings::shadow( $settings );
		$videos     = in_array( true, wp_list_pluck( $slides, 'video' ), true );
		// Per device: a Height in % of the parent block (the whole chain must stretch).
		$percent = array_map(
			function ( $height ) {
				return '%' === substr( $height, -1 );
			},
			WP_Swiper_Settings::css_heights( $settings )
		);

		$classes = array(
			'wp-swiper',
			'wp-swiper--' . $post_id,
			'wp-swiper--effect-' . $settings['effect'],
			// Thumbnails fading (its Transition): the Image shadow as Fade's (slider.css).
			WP_Swiper_Settings::is_thumbs( $settings ) && WP_Swiper_Settings::is_fade( $settings ) ? 'wp-swiper--thumbs-fade' : '',
			'wp-swiper--' . $settings['direction'],
			'wp-swiper--fit-' . $settings['image_fit'],
			in_array( true, $percent, true ) ? 'wp-swiper--height-percent' : '',
			$expand ? 'wp-swiper--expand' : '',
			// Height "Auto": frontend.js sizes the slider to its parent block. On
			// every device: from the start; on some: frontend.js sets the class
			// on those (the CSS heights are "auto" there until it does).
			WP_Swiper_Settings::is_auto_height_everywhere( $settings ) ? 'wp-swiper--fill' : '',
			WP_Swiper_Settings::is_auto_width( $settings ) ? 'wp-swiper--auto-width' : '',
			WP_Swiper_Settings::is_marquee( $settings ) ? 'wp-swiper--marquee' : '',
			WP_Swiper_Settings::separator( $settings ) ? 'wp-swiper--separator' : '',
			WP_Swiper_Settings::is_carousel( $settings ) ? 'wp-swiper--carousel' : '',
			$settings['lightbox'] ? 'wp-swiper--lightbox' : '',
			// Video slides the visitor starts: the YouTube / Vimeo play button shows.
			$videos && ! $settings['video_autoplay'] ? 'wp-swiper--video-manual' : '',
			// With controls a YouTube / Vimeo player takes clicks; without, the slider does.
			$videos && ( $settings['video_controls'] || ! $settings['video_autoplay'] ) ? 'wp-swiper--video-controls' : '',
			// A click on a video slide opens the lightbox (its play button says so).
			$videos && $settings['lightbox'] && ! empty( $settings['lightbox_videos'] ) ? 'wp-swiper--lightbox-videos' : '',
			$settings['image_border'] ? 'wp-swiper--border' : '',
			// Gradient over each photo (frontend.css).
			'' !== WP_Swiper_Settings::gradient( $settings ) ? 'wp-swiper--gradient' : '',
			// Image shadow (frontend.css), with room kept around the photos for it.
			$shadow ? 'wp-swiper--shadow' : '',
			// Caption: a weight saved for the whole slider (until 2.8) overrides the
			// tag's own (each slide's is on its caption, render_slide()); vertical text.
			'' !== WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ) ? 'wp-swiper--caption-weight' : '',
			'horizontal' !== $settings['caption_orientation'] ? 'wp-swiper--caption-' . $settings['caption_orientation'] : '',
			// Caption background: none, or solid instead of the default gradient; text shadow.
			! $settings['caption_bg'] ? 'wp-swiper--caption-nobg' : ( 'solid' === $settings['caption_bg_style'] ? 'wp-swiper--caption-solid' : ( '' !== WP_Swiper_Settings::caption_gradient( $settings ) ? 'wp-swiper--caption-custom' : '' ) ),
			$settings['caption_shadow'] ? 'wp-swiper--caption-shadow' : '',
			// The block's background (frontend.js background()), with a color over it.
			$background ? 'wp-swiper--background' : '',
			$background && '' !== WP_Swiper_Settings::background_overlay( $settings ) ? 'wp-swiper--bg-overlay' : '',
			// "Zoom the main image" (frontend.js zoom()).
			$zoom ? 'wp-swiper--zoom' : '',
			// "Zoom on hover" (frontend.css): the photo under the pointer.
			WP_Swiper_Settings::hover_zoom( $settings ) ? 'wp-swiper--hover-zoom' : '',
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
			// Its own colours, over the white / text colour ones.
			if ( $settings['pagination_colors'] ) {
				$classes[] = 'wp-swiper--pag-colors';
			}
			// A fraction size on some device.
			if ( array_filter( WP_Swiper_Settings::responsive( $settings, 'pagination_fraction_size' ), 'strlen' ) ) {
				$classes[] = 'wp-swiper--fraction-size';
			}
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
					$size = WP_Swiper_Settings::slide_size( $slide );
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
		// Effect shadows (Coverflow, Cards, Creative): their color, with its
		// opacity (slider.css; as js_config()'s "shadows": not with Contain).
		if ( $settings['effect_shadows'] && 'contain' !== $settings['image_fit'] && in_array( $settings['effect'], WP_Swiper_Settings::fields()['effect_shadow_color']['effects'], true ) ) {
			$shadow_color = WP_Swiper_Settings::sanitize_slide_color( $settings['effect_shadow_color'] );
			$style[]      = '--wp-swiper-effect-shadow:' . ( '' === $shadow_color ? WP_Swiper_Settings::effect_default( 'effect_shadow_color', $settings['effect'] ) : $shadow_color );
		}
		// Cube: its shadow's color (with its opacity), and room under the slider
		// for it (it falls `offset` below the cube and is blurred some 20px further).
		if ( 'cube' === $settings['effect'] && $settings['cube_shadow'] ) {
			$cube_color = WP_Swiper_Settings::sanitize_slide_color( $settings['cube_shadow_color'] );
			$style[]    = '--wp-swiper-cube-shadow-color:' . ( '' === $cube_color ? WP_Swiper_Settings::fields()['cube_shadow_color']['default'] : $cube_color );
			$style[]    = '--wp-swiper-cube-shadow-room:' . ( (int) $settings['cube_shadow_offset'] + 20 ) . 'px';
		}

		// Thumbnails: the look of the strip (its sizes come from frontend.js).
		$thumbs = WP_Swiper_Settings::is_thumbs( $settings );
		if ( $thumbs ) {
			foreach ( WP_Swiper_Settings::responsive( $settings, 'thumbs_spacing' ) as $device => $spacing ) {
				$style[] = '--wp-swiper-thumbs-spacing' . self::SUFFIX[ $device ] . ':' . (int) $spacing . 'px';
			}
			foreach ( WP_Swiper_Settings::thumbs_ratio( $settings ) as $device => $ratio ) {
				$style[] = '--wp-swiper-thumb-ratio' . self::SUFFIX[ $device ] . ':' . implode( ' / ', $ratio );
			}
			foreach ( WP_Swiper_Settings::responsive( $settings, 'thumbs_radius' ) as $device => $radius ) {
				$style[] = '--wp-swiper-thumb-radius' . self::SUFFIX[ $device ] . ':' . (int) $radius . 'px';
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
					$style[] = '--wp-swiper-arrow-size' . self::SUFFIX[ $device ] . ':' . (int) $size . 'px';
				}
			}
		}

		// Pagination look (frontend.css hands these to Swiper's own properties):
		// bullet size, shape, spacing and the current one's length per device,
		// where set; the fraction's size; its own colours.
		if ( 'none' !== $settings['pagination'] ) {
			$pagination = array(
				'bullet-width'  => 'pagination_bullet_width',
				'bullet-height' => 'pagination_bullet_height',
				'bullet-gap'    => 'pagination_bullet_gap',
				'bullet-active' => 'pagination_bullet_active',
				'fraction-size' => 'pagination_fraction_size',
			);
			foreach ( $pagination as $name => $key ) {
				foreach ( WP_Swiper_Settings::responsive( $settings, $key ) as $device => $value ) {
					if ( '' !== $value ) {
						$style[] = '--wp-swiper-' . $name . self::SUFFIX[ $device ] . ':' . (int) $value . 'px';
					}
				}
			}
			if ( '' !== $settings['pagination_bullet_radius'] ) {
				$style[] = '--wp-swiper-bullet-radius:' . (int) $settings['pagination_bullet_radius'] . 'px';
			}
			if ( $settings['pagination_colors'] ) {
				$style[] = '--wp-swiper-pag-color:' . $settings['pagination_color'];
				$style[] = '--wp-swiper-pag-inactive-color:' . $settings['pagination_inactive_color'];
				$style[] = '--wp-swiper-pag-inactive-opacity:' . max( 0, min( 100, (int) $settings['pagination_inactive_opacity'] ) ) / 100;
			}
		}

		// Continuous scroll separator: its size and the gap it stands in the
		// middle of, per device; the dot's color.
		$separator = WP_Swiper_Settings::separator( $settings );
		if ( $separator ) {
			$gaps = WP_Swiper_Settings::gaps( $settings );
			foreach ( $separator['size'] as $device => $size ) {
				$style[] = '--wp-swiper-separator-size' . self::SUFFIX[ $device ] . ':' . $size . 'px';
				$style[] = '--wp-swiper-gap' . self::SUFFIX[ $device ] . ':' . $gaps[ $device ] . 'px';
			}
			if ( 'dot' === $separator['type'] ) {
				$style[] = '--wp-swiper-separator-color:' . $settings['marquee_separator_color'];
			}
		}

		// Height of the slides per device (0 = 500px; "auto" where the unit is
		// "Auto"), unless "Auto" everywhere. With a % on some device: the
		// block's own height there, 100% (else auto).
		if ( ! WP_Swiper_Settings::is_auto_height_everywhere( $settings ) ) {
			foreach ( WP_Swiper_Settings::css_heights( $settings ) as $device => $height ) {
				$style[] = '--wp-swiper-height' . self::SUFFIX[ $device ] . ':' . $height;
			}
		}
		if ( in_array( true, $percent, true ) ) {
			foreach ( $percent as $device => $on ) {
				$style[] = '--wp-swiper-block-height' . self::SUFFIX[ $device ] . ':' . ( $on ? '100%' : 'auto' );
			}
		}

		// When a horizontal slider sizes itself (no free height to fill), every
		// slide takes the first image's proportions (the others are cropped or
		// fitted per "Image fit"); otherwise the tallest image would set the
		// height and leave gaps under the rest.
		$first = WP_Swiper_Settings::slide_size( $slides[0] );
		if ( $first ) {
			$style[] = '--wp-swiper-ratio:' . (int) $first[0] . ' / ' . (int) $first[1];
		}
		// "Cover": the part of a cropped image kept in view per device, unless it
		// is the middle everywhere (the CSS default).
		$positions = WP_Swiper_Settings::responsive( $settings, 'image_position' );
		if ( 'cover' === $settings['image_fit'] && array( 'center' ) !== array_values( array_unique( $positions ) ) ) {
			foreach ( $positions as $device => $position ) {
				$style[] = '--wp-swiper-img-position' . self::SUFFIX[ $device ] . ':' . self::point( $position );
			}
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
		// Caption typography: the Caption page's size for every slide; one with
		// its own sets it on its caption (render_slide()).
		$style = array_merge( $style, WP_Swiper_Settings::font_size_vars( WP_Swiper_Settings::caption_sizes( $settings ), 'wp-swiper-caption-size' ) );
		$style[] = '--wp-swiper-caption-color:' . $settings['caption_color'];
		if ( $settings['caption_bg'] ) {
			// The background color with its opacity, and fully transparent for the
			// gradient's far end (same hue, so the fade does not go grey).
			list( $from, $to ) = WP_Swiper_Settings::caption_bg_colors( $settings );
			$style[]           = '--wp-swiper-caption-bg-from:' . $from;
			$style[]           = '--wp-swiper-caption-bg-to:' . $to;
			// Custom gradient: its own colors (CSS of the caption's background).
			if ( '' !== WP_Swiper_Settings::caption_gradient( $settings ) ) {
				$style[] = '--wp-swiper-caption-gradient:' . WP_Swiper_Settings::caption_gradient( $settings );
			}
		}
		$style = array_merge( $style, WP_Swiper_Settings::font_weight_vars( WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ), 'wp-swiper-caption-weight' ) );
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
				$style[] = '--wp-swiper-border-width' . self::SUFFIX[ $device ] . ':' . (int) $width . 'px';
			}
			$style[] = '--wp-swiper-border-color:' . $settings['image_border_color'];
		}
		// Gradient over each photo.
		$gradient = WP_Swiper_Settings::gradient( $settings );
		if ( '' !== $gradient ) {
			$style[] = '--wp-swiper-gradient:' . $gradient;
		}
		// Image shadow: as CSS box-shadow, out of the slider's box (no room
		// taken from the photos): how far the slider shows past its sides for
		// it (the farthest it reaches, frontend.css: overflow-clip-margin);
		// where a browser cannot, the room kept inside (top right bottom left).
		if ( $shadow ) {
			$style[] = '--wp-swiper-shadow:' . $shadow['x'] . 'px ' . $shadow['y'] . 'px ' . $shadow['blur'] . 'px ' . $shadow['spread'] . 'px ' . $shadow['color'];
			$style[] = '--wp-swiper-shadow-reach:' . max( $shadow['room'] ) . 'px';
			$style[] = '--wp-swiper-shadow-room:' . implode( 'px ', $shadow['room'] ) . 'px';
		}
		if ( $expand ) {
			foreach ( WP_Swiper_Settings::responsive( $settings, 'active_width' ) as $device => $width ) {
				$style[] = '--wp-swiper-active-width' . self::SUFFIX[ $device ] . ':' . (float) $width . '%';
			}
		}
		// Zoom: the point it zooms towards, and the scale of the images waiting
		// their turn (zoom out: they come up large).
		if ( $zoom ) {
			$style[] = '--wp-swiper-zoom-origin:' . self::point( $settings['zoom_origin'] );
			$style[] = '--wp-swiper-zoom-from:' . self::zoom_scales( $settings )[0];
		}

		// Background: the overlay (the block's height is the page builder's).
		if ( $background ) {
			$overlay = WP_Swiper_Settings::background_overlay( $settings );
			if ( '' !== $overlay ) {
				$style[] = '--wp-swiper-bg-overlay:' . $overlay;
			}
		}

		$collage = WP_Swiper_Settings::is_collage( $settings );
		if ( $collage ) {
			$style[] = '--wp-swiper-collage-speed:' . (int) $settings['collage_swap_speed'] . 'ms';
		}

		// The outer block's own attributes (both markups): its id, if any (Custom),
		// and with Background the class of the block it fills.
		$attrs = sprintf(
			'%1$s class="%2$s"%3$s%4$s',
			'' !== $settings['css_id'] ? ' id="' . esc_attr( $settings['css_id'] ) . '"' : '',
			esc_attr( implode( ' ', array_filter( $classes ) ) ),
			$style ? ' style="' . esc_attr( implode( ';', $style ) ) . '"' : '',
			$background ? ' data-wp-swiper-bg="' . esc_attr( $settings['background_class'] ) . '"' : ''
		);

		if ( $collage ) {
			return self::collage( $slides, $settings, $attrs );
		}

		$count             = count( $slides );
		$copies            = self::copies( $settings, $count );
		$config            = self::js_config( $settings, $count, $copies, $videos );
		$pagination_inside = 'inside' === $settings['pagination_placement'];
		$separator         = self::separator( $settings );

		// Slide descriptions, under the slider (frontend.js descriptions()).
		$descriptions           = self::descriptions( $post_id, $slides, $settings );
		$config['descriptions'] = '' !== $descriptions;
		if ( $config['lightbox'] ) {
			$config['lightbox']['descriptions'] = $config['descriptions'] && $settings['description_lightbox'];
			// Under the image (frontend.js fitLightboxText()): the text's width per
			// device (% of the window), its limit (px) and its alignment.
			$config['lightbox']['text'] = $config['lightbox']['descriptions'] && 'under' === $settings['description_lightbox_place'] ? array(
				'width' => array_map( 'intval', WP_Swiper_Settings::responsive( $settings, 'description_lightbox_width' ) ),
				'max'   => (int) $settings['description_lightbox_max'],
				// A slider saved with an Alignment field: its own (else left).
				'align' => WP_Swiper_Settings::legacy_description_align( $settings ) ? WP_Swiper_Settings::legacy_description_align( $settings ) : 'left',
			) : false;
		}

		ob_start();
		?>
		<div<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built. ?>>
			<div class="swiper" data-wp-swiper="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
				<div class="swiper-wrapper">
					<?php
					// With few slides, loop mode needs the set repeated; data-wp-swiper-index
					// keeps the real index so pagination counts real slides only.
					for ( $copy = 0; $copy < $copies; $copy++ ) {
						foreach ( $slides as $index => $slide ) {
							self::render_slide( $slide, $index, $settings, 0 === $copy && 0 === $index, $separator );
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
								// A video: its poster (CSS adds a play mark), a YouTube / Vimeo video's own
								// thumbnail without one, or a dark box.
								$thumb_id  = $slide['video'] ? WP_Swiper_Settings::poster( $slide ) : $slide['id'];
								$thumb_url = ! $thumb_id && $slide['embed_info'] ? WP_Swiper_Settings::embed_thumb( $slide['embed'] ) : null;
								if ( $thumb_url && $thumb_url['small'] ) {
									printf(
										'<img class="wp-swiper__thumb-image" src="%s" alt="" loading="lazy" decoding="async">',
										esc_url( $thumb_url['small'] )
									);
								} elseif ( $thumb_id ) {
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
		echo $descriptions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
		return ob_get_clean();
	}

	/**
	 * The slides' descriptions: a block right after the slider's (so under
	 * all of it, whatever sits beside or above its slides), the current
	 * slide's shown. They share one place in it (CSS), so the block is as
	 * tall as the longest and the page under it does not move as they change;
	 * the one going and the one coming move there at once (Text transition).
	 * Slides without one show none there.
	 *
	 * @param int   $post_id  Slider ID.
	 * @param array $slides   Slides.
	 * @param array $settings Slider settings.
	 * @return string '' if the slider shows none.
	 */
	public static function descriptions( $post_id, $slides, $settings ) {
		if ( ! WP_Swiper_Settings::can_describe( $settings ) ) {
			return '';
		}
		$items = '';
		foreach ( $slides as $index => $slide ) {
			// Sanitized again: saved before, or posted by the preview.
			$text = empty( $slide['description'] ) ? '' : WP_Swiper_Settings::sanitize_description( $slide['description'] );
			if ( '' !== $text ) {
				// Its own font size (the Slide info window), per device.
				$size   = WP_Swiper_Settings::font_size_vars( isset( $slide['description_size'] ) ? $slide['description_size'] : '', 'wp-swiper-description-size' );
				// And its own font weight (a class: none keeps the page's).
				$weight = WP_Swiper_Settings::font_weight_vars( isset( $slide['description_weight'] ) ? $slide['description_weight'] : '', 'wp-swiper-description-weight' );
				$size   = array_merge( $size, $weight );
				// And its own color (a class: none keeps the Description page's or the page's).
				$color = isset( $slide['description_color'] ) ? WP_Swiper_Settings::sanitize_slide_color( $slide['description_color'] ) : '';
				if ( '' !== $color ) {
					$size[] = '--wp-swiper-description-color:' . $color;
				}
				$items .= sprintf(
					'<div class="wp-swiper-descriptions__item%1$s" data-wp-swiper-index="%2$d"%4$s>%3$s</div>',
					// The first slide's until frontend.js takes over.
					( 0 === $index ? ' is-active' : '' ) . ( $weight ? ' wp-swiper-descriptions__item--weight' : '' ) . ( '' !== $color ? ' wp-swiper-descriptions__item--color' : '' ),
					(int) $index,
					// Paragraphs for text typed in the editor's code view (only <p> and <br> are added).
					wpautop( $text ),
					$size ? ' style="' . esc_attr( implode( ';', $size ) ) . '"' : ''
				);
			}
		}
		if ( '' === $items ) {
			return '';
		}

		$style = array(
			'--wp-swiper-speed:' . (int) $settings['speed'] . 'ms',
		);
		// The gap to the slider, per device (each set: a smaller screen inherits).
		foreach ( WP_Swiper_Settings::responsive( $settings, 'description_offset' ) as $device => $offset ) {
			$style[] = '--wp-swiper-description-offset' . self::SUFFIX[ $device ] . ':' . (int) $offset . 'px';
		}
		// Each paragraph has its own alignment; a slider saved with an Alignment
		// field keeps it for the ones without, until it is saved again.
		if ( WP_Swiper_Settings::legacy_description_align( $settings ) ) {
			$style[] = '--wp-swiper-description-align:' . WP_Swiper_Settings::legacy_description_align( $settings );
		}
		// Each description has its own size (on its item, above); a slider saved
		// with one of its own keeps it for the others until saved again.
		$style = array_merge( $style, WP_Swiper_Settings::font_size_vars( WP_Swiper_Settings::legacy_font_sizes( $settings, 'description' ), 'wp-swiper-description-size' ) );
		// "Text transition": the motion's animations (frontend.css).
		$classes = array( 'wp-swiper-descriptions', 'wp-swiper-descriptions--' . $post_id, 'wp-swiper-descriptions--' . WP_Swiper_Settings::description_motion( $settings ) );
		// A weight saved for the whole slider (until 2.8), for the descriptions
		// without their own (each one's is on its item, above).
		$weight = WP_Swiper_Settings::font_weight_vars( WP_Swiper_Settings::legacy_font_weights( $settings, 'description' ), 'wp-swiper-description-weight' );
		if ( $weight ) {
			$classes[] = 'wp-swiper-descriptions--weight';
			$style     = array_merge( $style, $weight );
		}
		// Off: the page's text colour.
		if ( $settings['description_own_color'] ) {
			$classes[] = 'wp-swiper-descriptions--color';
			$style[]   = '--wp-swiper-description-color:' . $settings['description_color'];
		}

		return sprintf(
			'<div class="%1$s" style="%2$s">%3$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( implode( ';', $style ) ),
			$items
		);
	}

	/**
	 * The continuous scroll's separator, put after each slide's image: in the
	 * middle of the gap to the next slide (CSS), copied with the slide by the
	 * loop. Decoration only: hidden from screen readers, not in the lightbox.
	 *
	 * @param array $settings Slider settings.
	 * @return string Markup; '' without a separator.
	 */
	private static function separator( $settings ) {
		$separator = WP_Swiper_Settings::separator( $settings );
		if ( ! $separator ) {
			return '';
		}
		if ( 'dot' === $separator['type'] ) {
			$inner = '<span class="wp-swiper__separator-dot"></span>';
		} else {
			// The file that fits the largest size set (srcset), not the full one.
			$inner = wp_get_attachment_image(
				$separator['image'],
				'full',
				false,
				array(
					'class'    => 'wp-swiper__separator-image',
					'alt'      => '',
					'sizes'    => max( $separator['size'] ) . 'px',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
		}
		return '<span class="wp-swiper__separator" aria-hidden="true">' . $inner . '</span>';
	}

	/**
	 * @param array  $slide     Slide data.
	 * @param int    $index     Real slide index.
	 * @param array  $settings  Slider settings.
	 * @param bool   $eager     Load the image right away (first slide).
	 * @param string $separator The continuous scroll's separator (separator()).
	 */
	private static function render_slide( $slide, $index, $settings, $eager, $separator = '' ) {
		// The media's proportions size its box (.wp-swiper__media) with "Contain",
		// and the slide itself with "Width from image".
		$size  = WP_Swiper_Settings::slide_size( $slide );
		$ratio = $size ? round( $size[0] / $size[1], 4 ) : 1;
		$style = '--wp-swiper-img-ratio:' . $ratio;

		// "Cover": an image's own position, on every device, over the slider's
		// (all three variables: the slider may set its own per device).
		$position = empty( $slide['position'] ) || $slide['video'] ? '' : WP_Swiper_Settings::sanitize_position( $slide['position'] );
		if ( '' !== $position && 'cover' === $settings['image_fit'] && ! WP_Swiper_Settings::is_auto_width( $settings ) ) {
			$point = str_replace( ' ', '% ', $position ) . '%';
			foreach ( self::SUFFIX as $suffix ) {
				$style .= ';--wp-swiper-img-position' . $suffix . ':' . $point;
			}
		}

		if ( $slide['embed_info'] ) {
			$image = self::embed( $slide );
		} elseif ( $slide['video'] ) {
			$image = self::video( $slide, $size, $settings );
		} else {
			// Full size with its srcset: the browser picks the file that fits the slide.
			$attrs = array(
				'class'    => 'wp-swiper__image',
				'loading'  => $eager ? 'eager' : 'lazy',
				'decoding' => 'async',
			);
			// The first slider's first image, often the page's largest one in view
			// (what Google measures as LCP): fetched before the others. Once a
			// page (WordPress then gives no other image the same, from 6.3).
			if ( $eager && ! self::$priority ) {
				self::$priority        = true;
				$attrs['fetchpriority'] = 'high';
			}
			$image = wp_get_attachment_image( $slide['id'], 'full', false, $attrs );
		}
		?>
		<div class="swiper-slide<?php echo $slide['video'] ? ' wp-swiper-slide--video' : ''; ?>" data-wp-swiper-index="<?php echo (int) $index; ?>" style="<?php echo esc_attr( $style ); ?>"
			<?php self::lightbox_attrs( $slide, $settings, $ratio ); ?>
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
			<?php
			// Sanitized again: saved before, or posted by the preview.
			// None as a background (can_caption()).
			$caption = empty( $slide['caption'] ) || ! WP_Swiper_Settings::can_caption( $settings ) ? '' : WP_Swiper_Settings::sanitize_caption( $slide['caption'] );
			if ( '' !== $caption ) :
				// Outer block: position and background; inner one: typography, its
				// lines paragraphs or headings (caption_markup()).
				?>
				<?php // Its own font size (the Slide info window), per device. ?>
				<?php
				$caption_size   = WP_Swiper_Settings::own_font_size_vars( isset( $slide['caption_size'] ) ? $slide['caption_size'] : '', 'wp-swiper-caption-size' );
				// And its own font weight (a class: none keeps the paragraph's or heading's).
				$caption_weight = WP_Swiper_Settings::font_weight_vars( isset( $slide['caption_weight'] ) ? $slide['caption_weight'] : '', 'wp-swiper-caption-weight' );
				$caption_size   = array_merge( $caption_size, $caption_weight );
				// And its own color (none: the Caption page's).
				$caption_color  = isset( $slide['caption_color'] ) ? WP_Swiper_Settings::sanitize_slide_color( $slide['caption_color'] ) : '';
				if ( '' !== $caption_color ) {
					$caption_size[] = '--wp-swiper-caption-color:' . $caption_color;
				}
				?>
				<div class="wp-swiper__caption<?php echo $caption_weight ? ' wp-swiper__caption--weight' : ''; ?>"<?php echo $caption_size ? ' style="' . esc_attr( implode( ';', $caption_size ) ) . '"' : ''; ?>><div class="wp-swiper__caption-text"><?php echo WP_Swiper_Settings::caption_markup( $caption, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized above (caption_tags()), a whitelisted tag around it. ?></div></div>
			<?php endif; ?>
			</div>
			<?php echo $separator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built in separator(). ?>
		</div>
		<?php
	}
}
