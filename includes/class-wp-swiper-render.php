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
	 * A stylesheet of the plugin's, enqueued; if <head> is out already,
	 * remembered for this page (remember_late_styles()).
	 *
	 * @param string $handle swiper | wp-swiper | wp-swiper-collage | wp-swiper-art.
	 */
	public static function style( $handle ) {
		// Printed in <head> already (the page's content has the slider): not late.
		$printed = wp_style_is( $handle, 'done' );
		wp_enqueue_style( $handle );
		if ( ! $printed && did_action( 'wp_head' ) ) {
			self::$late[ $handle ] = true;
		}
	}

	/**
	 * The page as remembered for its late stylesheets: a post, the front or
	 * blog page, a term's archive, a post type's; null for the rest (search,
	 * 404, dates...).
	 *
	 * @return string|null
	 */
	public static function late_key() {
		if ( is_singular() ) {
			return 'p' . get_queried_object_id();
		}
		if ( is_front_page() || is_home() ) {
			return 'home';
		}
		$object = get_queried_object();
		if ( $object instanceof WP_Term ) {
			return 't' . $object->term_id;
		}
		if ( is_post_type_archive() ) {
			$type = get_query_var( 'post_type' );
			return 'a' . sanitize_key( is_array( $type ) ? reset( $type ) : $type );
		}
		return null;
	}

	/**
	 * After the page (wp_footer): its late stylesheets kept for its next
	 * visits, written only when there is something new.
	 */
	public static function remember_late_styles() {
		$key  = self::late_key();
		$late = array_keys( self::$late );
		// Taken: the next page (or test) starts with none.
		self::$late = array();
		if ( ! $late || null === $key ) {
			return;
		}
		$map  = get_option( self::LATE_OPTION, array() );
		$map  = is_array( $map ) ? $map : array();
		$all  = isset( $map['*'] ) ? (array) $map['*'] : array();
		$own  = isset( $map[ $key ] ) ? (array) $map[ $key ] : array();
		$new  = array_diff( $late, $all, $own );
		if ( ! $new ) {
			return;
		}
		$map[ $key ] = array_values( array_unique( array_merge( $own, $new ) ) );
		// Many pages: a slider every page shows; then for all of them.
		$pages = array_diff_key( $map, array( '*' => true ) );
		if ( count( $pages ) > self::LATE_PAGES ) {
			$handles = $all;
			foreach ( $pages as $list ) {
				$handles = array_merge( $handles, (array) $list );
			}
			$map = array( '*' => array_values( array_unique( $handles ) ) );
		}
		update_option( self::LATE_OPTION, $map, true );
	}

	/**
	 * The stylesheets remembered for this page (and for every page), for <head>.
	 *
	 * @return string[]
	 */
	public static function late_styles() {
		$map = get_option( self::LATE_OPTION, array() );
		if ( ! is_array( $map ) || ! $map ) {
			return array();
		}
		$key     = self::late_key();
		$handles = isset( $map['*'] ) ? (array) $map['*'] : array();
		if ( null !== $key && isset( $map[ $key ] ) ) {
			$handles = array_merge( $handles, (array) $map[ $key ] );
		}
		return array_values( array_intersect( array_unique( $handles ), array( 'swiper', 'wp-swiper', 'wp-swiper-slider', 'wp-swiper-thumbs', 'wp-swiper-collage', 'wp-swiper-art' ) ) );
	}

	public static function register_assets() {
		// Art's stylesheet first: enqueued below for a page that has Art (one
		// not registered yet would be left out).
		WP_Swiper_Art::register_assets();

		// Swiper on its own: only the sliders need it (enqueue()); a Collage or
		// Art loads it for its lightbox, when that first opens (frontend.js).
		wp_register_style( 'swiper', WP_SWIPER_URL . 'assets/vendor/swiper/swiper-bundle.min.css', array(), WP_SWIPER_SWIPER_VERSION );
		wp_register_style( 'wp-swiper', WP_SWIPER_URL . 'assets/css/frontend.css', array(), wp_swiper_asset_version( 'assets/css/frontend.css' ) );
		// The sliders' own rules (all but Collage and Art), after frontend's
		// (enqueue()); the Thumbnails effect's strip after those.
		wp_register_style( 'wp-swiper-slider', WP_SWIPER_URL . 'assets/css/slider.css', array( 'wp-swiper' ), wp_swiper_asset_version( 'assets/css/slider.css' ) );
		wp_register_style( 'wp-swiper-thumbs', WP_SWIPER_URL . 'assets/css/thumbs.css', array( 'wp-swiper-slider' ), wp_swiper_asset_version( 'assets/css/thumbs.css' ) );
		// The Collage effect's own files, after frontend's (enqueue_collage()).
		wp_register_style( 'wp-swiper-collage', WP_SWIPER_URL . 'assets/css/collage.css', array( 'wp-swiper' ), wp_swiper_asset_version( 'assets/css/collage.css' ) );

		wp_register_script( 'swiper', WP_SWIPER_URL . 'assets/vendor/swiper/swiper-bundle.min.js', array(), WP_SWIPER_SWIPER_VERSION, true );
		wp_register_script( 'wp-swiper', WP_SWIPER_URL . 'assets/js/frontend.js', array(), wp_swiper_asset_version( 'assets/js/frontend.js' ), true );
		wp_register_script( 'wp-swiper-collage', WP_SWIPER_URL . 'assets/js/collage.js', array( 'wp-swiper' ), wp_swiper_asset_version( 'assets/js/collage.js' ), true );
		// The sliders themselves, after Swiper and frontend.js (enqueue()): a
		// page with only a Collage or Art does without them.
		wp_register_script( 'wp-swiper-slider', WP_SWIPER_URL . 'assets/js/slider.js', array( 'swiper', 'wp-swiper' ), wp_swiper_asset_version( 'assets/js/slider.js' ), true );
		wp_localize_script(
			'wp-swiper',
			'wpSwiperI18n',
			array(
				'dialog' => __( 'Image viewer', 'wp-swiper' ),
				'close'  => __( 'Close', 'wp-swiper' ),
				'prev'   => __( 'Previous image', 'wp-swiper' ),
				'next'   => __( 'Next image', 'wp-swiper' ),
				// Swiper's files, for a lightbox opened where no slider loaded them.
				'swiper' => array(
					'js'  => add_query_arg( 'ver', WP_SWIPER_SWIPER_VERSION, WP_SWIPER_URL . 'assets/vendor/swiper/swiper-bundle.min.js' ),
					'css' => add_query_arg( 'ver', WP_SWIPER_SWIPER_VERSION, WP_SWIPER_URL . 'assets/vendor/swiper/swiper-bundle.min.css' ),
				),
			)
		);

		// Load the CSS in <head> when we already know the page uses a slider,
		// so there is no flash of unstyled images (Swiper's too, unless all of
		// them are Collages or Art). Otherwise the shortcode enqueues it on demand.
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			wp_enqueue_style( 'wp-swiper' );
			if ( self::content_needs_swiper( $post->post_content ) ) {
				wp_enqueue_style( 'swiper' );
				wp_enqueue_style( 'wp-swiper-slider' );
			}
			// The Thumbnails effect's, the Collage's and Art's own stylesheets
			// too (Art's registered by WP_Swiper_Art, also on this hook).
			$effects = self::content_effects( $post->post_content );
			if ( in_array( 'thumbs', $effects, true ) ) {
				wp_enqueue_style( 'wp-swiper-thumbs' );
			}
			if ( in_array( 'collage', $effects, true ) ) {
				wp_enqueue_style( 'wp-swiper-collage' );
			}
			if ( in_array( WP_Swiper_Art::EFFECT, $effects, true ) ) {
				wp_enqueue_style( 'wp-swiper-art' );
			}
		}
		// Those a slider of this page needed after <head> before (remember_late_styles()).
		foreach ( self::late_styles() as $handle ) {
			wp_enqueue_style( $handle );
		}
	}

	/**
	 * Whether the sliders of some content need Swiper: one at least that is
	 * no Collage or Art (or one whose ID cannot be told).
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	public static function content_needs_swiper( $content ) {
		foreach ( self::content_effects( $content ) as $effect ) {
			if ( null === $effect || self::needs_swiper( array( 'effect' => $effect ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The effects of the sliders in some content (its shortcodes), each once;
	 * null for one whose ID cannot be told.
	 *
	 * @param string $content Post content.
	 * @return array
	 */
	public static function content_effects( $content ) {
		if ( ! preg_match_all( '/\[' . self::SHORTCODE . '\b([^\]]*)\]/', (string) $content, $matches ) ) {
			return array();
		}
		$effects = array();
		foreach ( $matches[1] as $attrs ) {
			$atts      = shortcode_parse_atts( $attrs );
			$id        = is_array( $atts ) && isset( $atts['id'] ) ? absint( $atts['id'] ) : 0;
			$effects[] = $id ? WP_Swiper_Settings::get( $id )['effect'] : null;
		}
		return array_values( array_unique( $effects, SORT_REGULAR ) );
	}

	/**
	 * Whether a slider needs Swiper on the page: all but a Collage and Art
	 * (their lightbox loads it when it opens).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function needs_swiper( $settings ) {
		return ! in_array( $settings['effect'], array( 'collage', WP_Swiper_Art::EFFECT ), true );
	}

	/**
	 * The front end's files for a slider: frontend.js and .css, and where it
	 * needs Swiper, Swiper's files and slider.js and .css (after Swiper and
	 * frontend's; Swiper's stylesheet before frontend.css), with the
	 * Thumbnails effect's thumbs.css after them.
	 *
	 * @param bool $swiper With Swiper.
	 * @param bool $thumbs With the Thumbnails effect's strip.
	 */
	public static function enqueue( $swiper, $thumbs = false ) {
		if ( $swiper ) {
			$file = wp_styles()->query( 'wp-swiper', 'registered' );
			if ( $file && ! in_array( 'swiper', $file->deps, true ) ) {
				$file->deps[] = 'swiper';
			}
			self::style( 'swiper' );
			wp_enqueue_script( 'wp-swiper-slider' );
		}
		self::style( 'wp-swiper' );
		if ( $swiper ) {
			self::style( 'wp-swiper-slider' );
			if ( $thumbs ) {
				self::style( 'wp-swiper-thumbs' );
			}
		}
		wp_enqueue_script( 'wp-swiper' );
	}

	/**
	 * The Collage effect's own files (collage.js and .css), after
	 * frontend's: a slider page does without them.
	 */
	public static function enqueue_collage() {
		self::enqueue( false );
		self::style( 'wp-swiper-collage' );
		wp_enqueue_script( 'wp-swiper-collage' );
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
			return current_user_can( 'edit_posts' )
				? '<p class="wp-swiper-notice">' . esc_html__( 'WP Swiper: this slider has no slides yet.', 'wp-swiper' ) . '</p>'
				: '';
		}

		// Art: drawn by its own class, with its own settings and assets only.
		if ( WP_Swiper_Art::EFFECT === $settings['effect'] ) {
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
		if ( $background && current_user_can( 'edit_posts' ) ) {
			$html .= '<p class="wp-swiper-notice" data-wp-swiper-bg-missing hidden>' . esc_html(
				'' === $settings['background_class']
					? __( 'WP Swiper: this slider is set to be a background, but has no parent block class (Background).', 'wp-swiper' )
					/* translators: %s: CSS class name(s). */
					: sprintf( __( 'WP Swiper: no block with the class “%s” around this slider, so it is not a background. Give that class to its row or column.', 'wp-swiper' ), $settings['background_class'] )
			) . '</p>';
		}

		return $html;
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
	 * A video slide's poster address: the one chosen, else (a YouTube / Vimeo
	 * video) its own thumbnail.
	 *
	 * @param array $slide Slide data.
	 * @return string '' if none.
	 */
	private static function poster_url( $slide ) {
		$poster = WP_Swiper_Settings::poster( $slide );
		if ( $poster ) {
			return (string) wp_get_attachment_image_url( $poster, 'full' );
		}
		if ( $slide['embed_info'] ) {
			$thumb = WP_Swiper_Settings::embed_thumb( $slide['embed'] );
			return $thumb['src'];
		}
		return '';
	}

	/**
	 * A YouTube / Vimeo video for frontend.js: provider, id, Vimeo's key, title.
	 *
	 * @param array $slide Slide data.
	 * @return array
	 */
	private static function embed_data( $slide ) {
		$meta = WP_Swiper_Settings::embed_meta( $slide['embed'] );
		return array(
			'provider' => $slide['embed_info']['provider'],
			'id'       => $slide['embed_info']['id'],
			'hash'     => $slide['embed_info']['hash'],
			'title'    => empty( $meta['title'] ) ? '' : $meta['title'],
		);
	}

	/**
	 * A YouTube / Vimeo slide: a "facade", its poster (and a play button)
	 * until frontend.js puts the service's player in it, which is when the
	 * slide comes up (Play automatically) or is clicked. Nothing of the
	 * service loads with the page but the poster image.
	 *
	 * @param array $slide Slide data.
	 * @return string
	 */
	private static function embed( $slide ) {
		$poster   = WP_Swiper_Settings::poster( $slide );
		$thumb    = WP_Swiper_Settings::embed_thumb( $slide['embed'] );
		$src      = $poster ? wp_get_attachment_image_url( $poster, 'full' ) : $thumb['src'];
		$fallback = $poster ? '' : $thumb['fallback'];
		$data     = self::embed_data( $slide );

		return sprintf(
			'<div class="wp-swiper__image wp-swiper__embed" data-wp-swiper-embed-player="%1$s">%2$s<button type="button" class="wp-swiper__play" aria-label="%3$s"></button></div>',
			esc_attr( wp_json_encode( $data ) ),
			$src ? sprintf(
				'<img class="wp-swiper__embed-poster" src="%s" alt="" loading="lazy" decoding="async"%s>',
				esc_url( $src ),
				$fallback ? ' data-fallback="' . esc_url( $fallback ) . '"' : ''
			) : '',
			/* translators: %s: video title. */
			esc_attr( $data['title'] ? sprintf( __( 'Play video: %s', 'wp-swiper' ), $data['title'] ) : __( 'Play video', 'wp-swiper' ) )
		);
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
			// Image shadow (frontend.css), with room kept around the photos for it.
			$shadow ? 'wp-swiper--shadow' : '',
			// Caption: a weight saved for the whole slider (until 2.8) overrides the
			// tag's own (each slide's is on its caption, render_slide()); vertical text.
			'' !== WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ) ? 'wp-swiper--caption-weight' : '',
			'horizontal' !== $settings['caption_orientation'] ? 'wp-swiper--caption-' . $settings['caption_orientation'] : '',
			// Caption background: none, or solid instead of the default gradient; text shadow.
			! $settings['caption_bg'] ? 'wp-swiper--caption-nobg' : ( 'solid' === $settings['caption_bg_style'] ? 'wp-swiper--caption-solid' : '' ),
			$settings['caption_shadow'] ? 'wp-swiper--caption-shadow' : '',
			// The block's background (frontend.js background()), with a color over it.
			$background ? 'wp-swiper--background' : '',
			$background && '' !== WP_Swiper_Settings::background_overlay( $settings ) ? 'wp-swiper--bg-overlay' : '',
			// "Zoom the main image" (frontend.js zoom()).
			$zoom ? 'wp-swiper--zoom' : '',
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
			</div>
		</div>
		<?php
		return ob_get_clean();
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
