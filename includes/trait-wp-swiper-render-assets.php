<?php
/**
 * Which files a page gets: registering the scripts and styles, enqueuing
 * them for the sliders shown, and the styles printed in <head> for the
 * content (with those shown after it remembered, wp_swiper_late_styles).
 * Used by WP_Swiper_Render, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Render_Assets {

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
		self::register_content();
		// The collage's layouts (just the numbers) before the collage itself.
		wp_register_script( 'wp-swiper-collage-layouts', WP_SWIPER_URL . 'assets/js/collage-layouts.js', array(), wp_swiper_asset_version( 'assets/js/collage-layouts.js' ), true );
		wp_register_script( 'wp-swiper-collage', WP_SWIPER_URL . 'assets/js/collage.js', array( 'wp-swiper', 'wp-swiper-content', 'wp-swiper-collage-layouts' ), wp_swiper_asset_version( 'assets/js/collage.js' ), true );
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
			if ( array_intersect( WP_Swiper_Art::EFFECTS, $effects ) ) {
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
		return 'collage' !== $settings['effect'] && ! WP_Swiper_Art::is_art( $settings );
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
}
