<?php
/**
 * The Art effect: photos in a shape of their own, picked from its Style
 * (Ring, more to come). Not a slider and kept apart from the others: its
 * settings are its own (art_*, Layout only), it has its own markup, script
 * (assets/js/art.js) and stylesheet (assets/css/art.css), loaded only where
 * an Art slider is shown. The rest of the plugin only lists it as an effect
 * and hands its rendering over here (WP_Swiper_Render::render_slider()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-swiper-art-cells.php';
require_once __DIR__ . '/trait-wp-swiper-art-sizes.php';
require_once __DIR__ . '/trait-wp-swiper-art-gradient.php';
require_once __DIR__ . '/trait-wp-swiper-art-parts.php';

class WP_Swiper_Art {

	use WP_Swiper_Art_Cells;
	use WP_Swiper_Art_Sizes;
	use WP_Swiper_Art_Gradient;
	use WP_Swiper_Art_Parts;

	const EFFECT = 'art';

	// Text mask: an effect of its own in the list, drawn here as a Style of
	// Art would be ('text', style_of()): its letters are its windows.
	const TEXT_EFFECT = 'text-mask';

	// The effects this class draws.
	const EFFECTS = array( self::EFFECT, self::TEXT_EFFECT );

	// Text mask: the most characters of its word.
	const TEXT_MAX = 24;

	// The photos' Gradient (as WoodMart's): "type;where;color@position;…",
	// see parse_gradient(). By default from pink to blue, half see-through.
	const GRADIENT_DEFAULT = 'linear;left;rgba(255,0,128,0.55)@0;rgba(0,96,255,0.55)@100';

	// The most color points of a gradient.
	const GRADIENT_STOPS = 10;

	/**
	 * Whether this class draws the slider (Art or Text mask).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_art( $settings ) {
		return isset( $settings['effect'] ) && in_array( $settings['effect'], self::EFFECTS, true );
	}

	/**
	 * Text mask: the letters of its word (its windows, in order), spaces left
	 * out; at least one.
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	public static function letters( $settings ) {
		$word    = isset( $settings['text_mask_title'] ) ? (string) $settings['text_mask_title'] : '';
		$letters = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
		$letters = array_values(
			array_filter(
				is_array( $letters ) ? $letters : array(),
				function ( $letter ) {
					return '' !== trim( $letter );
				}
			)
		);
		return $letters ? $letters : array( 'T' );
	}


	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'wp-swiper-art', WP_SWIPER_URL . 'assets/css/art.css', array(), wp_swiper_asset_version( 'assets/css/art.css' ) );
		WP_Swiper_Render::register_content();
		wp_register_script( 'wp-swiper-art', WP_SWIPER_URL . 'assets/js/art.js', array( 'wp-swiper-content' ), wp_swiper_asset_version( 'assets/js/art.js' ), true );
	}

	/**
	 * The styles of Art: the Style list (each with its own fields below).
	 *
	 * @return array value => label
	 */
	public static function styles() {
		return array(
			// A circle in the middle, the others around it in a ring (with an
			// opening at the bottom, if set). Content in a window: an image and/or
			// text in one of them (the middle one too) instead of a photo, as in
			// the grids and SVG shape (the "+ text" Styles until 3.4).
			'ring'      => __( 'Ring', 'wp-swiper' ),
			// A square grid (its Columns per device) with a circle over its
			// middle: the cells around it cut by it (those it covers left out).
			'grid-circle' => __( 'Grid + circle', 'wp-swiper' ),
			// The grid with a square over its middle instead: the cells around it
			// cut by it (an L at a corner, a notch at a side), its photo in it.
			'grid-square'      => __( 'Grid + square', 'wp-swiper' ),
			// A grid whose cells are joined into shapes in the preview (cells()):
			// each shape a window for a photo, its corners rounded outwards and,
			// where it turns in, inwards; drawn anew at any size (art.js), so it
			// stretches to the column and the Height.
			'grid-shapes'      => __( 'Grid shapes', 'wp-swiper' ),
			// The shapes of an SVG file, each a window for a photo
			// (WP_Swiper_Svg_Shape): the current one in the main window, the next
			// ones in the others, moving on window by window.
			'shape'       => __( 'SVG shape', 'wp-swiper' ),
		);
	}

	/**
	 * SVG shape: its windows for a page (WP_Swiper_Svg_Shape::frame()); null
	 * when no file was uploaded (or it had no shapes).
	 *
	 * @param array $settings Slider settings.
	 * @return array|null
	 */
	public static function shape( $settings ) {
		return self::shapes( $settings )['desktop'];
	}

	/**
	 * SVG shape per device: a file of its own on tablet and mobile, else the
	 * larger screen's (as a responsive setting); each as shape() gives it,
	 * null for none.
	 *
	 * @param array $settings Slider settings.
	 * @return array device => array|null
	 */
	public static function shapes( $settings ) {
		$values = array();
		$last   = '';
		foreach ( array( 'desktop' => 'art_shape', 'tablet' => 'art_shape_tablet', 'mobile' => 'art_shape_mobile' ) as $device => $key ) {
			$value             = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
			$last              = '' !== $value ? $value : $last;
			$values[ $device ] = $last;
		}
		$crop = empty( $settings['art_shape_margins'] );
		return array_map(
			function ( $value ) use ( $crop ) {
				$data = WP_Swiper_Svg_Shape::decode( $value );
				return $data ? WP_Swiper_Svg_Shape::frame( $data, $crop ) : null;
			},
			$values
		);
	}

	/**
	 * Styles to come: shown as greyed tiles, not to be chosen (nor saved: the
	 * Style keeps to styles()).
	 *
	 * @return array value => label
	 */
	public static function soon() {
		return array();
	}

	/**
	 * Whether a Style's first window is its middle one (Ring, Grid + circle,
	 * Grid + square), its main one (SVG shape) or first shape (Grid shapes):
	 * not Text mask's letters.
	 *
	 * @param string $style Style.
	 * @return bool
	 */
	public static function has_middle_photo( $style ) {
		// SVG shape: its main window; Grid shapes: its first shape.
		return in_array( $style, array( 'ring', 'grid-circle', 'grid-square', 'grid-shapes', 'shape' ), true );
	}

	/**
	 * Whether it is as wide as the column (the grids), not a square of its
	 * Size (the rings).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_wide( $settings ) {
		return in_array( self::style_of( $settings ), array( 'grid-circle', 'grid-square', 'grid-shapes', 'text' ), true );
	}

	/**
	 * Whether a Style's pieces are windows that change as the SVG shape's do
	 * (What changes, Appearance, One photo through all windows): SVG shape
	 * and Grid shapes; Text mask's letters too.
	 *
	 * @param string $style Style.
	 * @return bool
	 */
	public static function has_windows( $style ) {
		return in_array( $style, array( 'grid-shapes', 'shape', 'text' ), true );
	}

	/**
	 * Whether a Style can have Content in a window: Ring, Grid + circle,
	 * Grid + square, Grid shapes, SVG shape (not Text mask: its letters).
	 *
	 * @param string $style Style.
	 * @return bool
	 */
	public static function has_content( $style ) {
		return in_array( $style, array( 'ring', 'grid-circle', 'grid-square', 'grid-shapes', 'shape' ), true );
	}

	// Grid shapes: the most columns and rows.
	const CELLS_MAX = 12;

	/**
	 * The Style as drawn: a known one, else Ring.
	 *
	 * @param array $settings Slider settings.
	 * @return string
	 */
	public static function style_of( $settings ) {
		if ( isset( $settings['effect'] ) && self::TEXT_EFFECT === $settings['effect'] ) {
			return 'text';
		}
		$style = isset( $settings['art_style'] ) ? (string) $settings['art_style'] : '';
		return array_key_exists( $style, self::styles() ) ? $style : 'ring';
	}

	/**
	 * Art's settings, merged into WP_Swiper_Settings::fields(): for this
	 * effect only, named art_*; on the Layout page, but for Photo swapping on
	 * the Controls page. "styles": the Styles that use a field (admin.js
	 * shows it for those).
	 *
	 * @return array
	 */
	public static function fields() {
		return require __DIR__ . '/art-fields.php';
	}

	/**
	 * Art's blocks, per page (before the other effects' blocks there): its
	 * shape on the Layout page, the Ring's Opening and Content in a window
	 * blocks after it (its Rounded corners and Image border are in the Look block,
	 * where a slider's are, over the Image shadow: WP_Swiper_Settings::blocks()),
	 * Photo swapping on the Controls page.
	 *
	 * @return array page => [ block => [ 'title' => string, 'fields' => string[] ] ]
	 */
	public static function blocks() {
		return array(
			'layout'   => array(
				// Text mask's word here too (Strips go, Word, Photos): the block's
				// title says which (admin.js, `titles`). Height, Gap and Alignment
				// are in the Size block, as every effect's (WP_Swiper_Settings::blocks()).
				'art'        => array(
					'title'  => __( 'Art', 'wp-swiper' ),
					'titles' => array( self::TEXT_EFFECT => __( 'Text mask', 'wp-swiper' ) ),
					'fields' => array( 'art_style', 'text_mask_direction', 'text_mask_title', 'text_mask_photos', 'art_shape', 'art_shape_margins', 'art_cells_columns', 'art_cells_rows', 'art_ring_count', 'art_grid_count', 'art_grid_columns' ),
				),
				// Ring: its opening at the bottom and what is in it, a block of its own too.
				'art_opening' => array(
					'title'  => __( 'Opening', 'wp-swiper' ),
					'fields' => array( 'art_opening', 'art_ring_opening', 'art_opening_image', 'art_opening_image_size', 'art_opening_text', 'art_opening_font_size', 'art_opening_gap' ),
				),
				// A window with content instead of a photo (Ring, the grids, SVG
				// shape: has_content()), a block of its own.
				'art_content' => array(
					'title'  => __( 'Content in a window', 'wp-swiper' ),
					'fields' => array( 'art_content', 'art_content_window', 'art_content_image', 'art_content_image_size', 'art_content_text', 'art_content_font_size', 'art_content_padding', 'art_content_valign', 'art_content_bg' ),
				),
				// Text mask: its letters and its strips, blocks of their own (after
				// Art's own blocks, hidden there as these are for Art: right after
				// the word's block on screen).
				'text_letters' => array(
					'title'  => __( 'Letters', 'wp-swiper' ),
					'fields' => array( 'text_mask_font', 'text_mask_weight', 'text_mask_font_name', 'text_mask_size', 'text_mask_stretch' ),
				),
				'text_strips'  => array(
					'title'  => __( 'Strips', 'wp-swiper' ),
					'fields' => array( 'text_mask_strips_on', 'text_mask_strip_width', 'text_mask_join', 'text_mask_strips', 'text_mask_strip_min', 'text_mask_strip_max' ),
				),
			),
			// As the Collage's own (the same block and fields).
			'controls' => array(
				'art_swap' => array(
					'title'  => __( 'Photo swapping', 'wp-swiper' ),
					'fields' => array( 'art_swap', 'art_swap_target', 'art_shape_target', 'art_shape_appear', 'art_swap_delay', 'art_swap_speed', 'art_pause' ),
				),
			),
		);
	}

	/**
	 * The Art slider: an outer block (with the slider's own class and id), a
	 * square stage with a piece per photo (art.js gives each its shape and
	 * place), each photo with its caption, and the slides' descriptions under
	 * it. Images only: video slides are left out.
	 *
	 * @param int    $post_id     Slider ID.
	 * @param array  $slides      Slides (from the media library, checked).
	 * @param array  $settings    Slider settings.
	 * @param string $extra_class Classes set where it is placed.
	 * @return string
	 */
	public static function render( $post_id, $slides, $settings, $extra_class = '' ) {
		$slides = array_values(
			array_filter(
				$slides,
				function ( $slide ) {
					return empty( $slide['embed'] ) && ! empty( $slide['id'] ) && ! WP_Swiper_Settings::is_video( $slide['id'] );
				}
			)
		);
		if ( ! $slides ) {
			return current_user_can( WP_Swiper_Settings::CAPABILITY )
				? '<p class="wp-swiper-notice">' . esc_html__( 'WP Swiper: the Art effect shows images only; this slider has none.', 'wp-swiper' ) . '</p>'
				: '';
		}

		WP_Swiper_Render::style( 'wp-swiper-art' );
		wp_enqueue_script( 'wp-swiper-art' );

		// "Use as background" (a Style that stretches: the grids): it fills the
		// block with the class given (art.js puts it there), behind its content,
		// as a slider does: no captions, links, descriptions or lightbox (no
		// click reaches it), the block's height, not its own.
		$background = WP_Swiper_Settings::is_background( $settings );
		if ( $background ) {
			$settings['lightbox'] = false;
			$slides               = array_map(
				function ( $slide ) {
					$slide['caption'] = '';
					$slide['link']    = '';
					return $slide;
				},
				$slides
			);
		}

		// A click opens either links or the lightbox, never a mix (as in a
		// slider): any link turns the lightbox off.
		if ( array_filter( wp_list_pluck( $slides, 'link' ) ) ) {
			$settings['lightbox'] = false;
		}

		$style   = self::style_of( $settings );
		// Text mask: one photo through all its letters (the word is the
		// stencil), or a photo in each letter, changing as What changes says
		// (its "one" is Photos' to say: there a window at random instead). No
		// captions on it (each letter would cut one up).
		if ( 'text' === $style ) {
			if ( 'each' !== $settings['text_mask_photos'] ) {
				$settings['art_shape_target'] = 'one';
			} elseif ( ! in_array( $settings['art_shape_target'], array( 'any', 'turn', 'advance' ), true ) ) {
				$settings['art_shape_target'] = 'any';
			}
			$slides                       = array_map(
				function ( $slide ) {
					$slide['caption'] = '';
					return $slide;
				},
				$slides
			);
		}
		// SVG shape: no file yet (or no shapes in it), nothing to show.
		$shapes = 'shape' === $style ? self::shapes( $settings ) : null;
		$shape  = $shapes ? $shapes['desktop'] : null;
		if ( 'shape' === $style && ! $shape ) {
			return current_user_can( WP_Swiper_Settings::CAPABILITY )
				? '<p class="wp-swiper-notice">' . esc_html__( 'WP Swiper: upload an SVG file for the SVG shape style (Layout, Art).', 'wp-swiper' ) . '</p>'
				: '';
		}
		$middle  = self::has_middle_photo( $style );
		$count   = self::count( $settings );
		// SVG shape and Grid shapes: windows, changing as What changes says.
		$windows = self::has_windows( $style ) && ( 'shape' !== $style || $shape );
		// Alignment per device: the side margins that put it there.
		$margins = array_map(
			function ( $align ) {
				if ( 'left' === $align ) {
					return '0 auto 0 0';
				}
				return 'right' === $align ? '0 0 0 auto' : '0 auto';
			},
			WP_Swiper_Settings::responsive( $settings, 'art_align' )
		);

		// Per device: the photos around the ring, the gap, the corners, the opening.
		$config = array(
			'style'   => $style,
			'gap'     => self::per_device( $settings, 'art_gap', 0, 40 ),
			'radius'  => self::per_device( WP_Swiper_Settings::apply_rounded( $settings ), 'art_radius', 0, 60 ),
			'count'   => self::per_device( $settings, 'art_ring_count', 3, 16 ),
			// Ring: the middle circle's size per device (% of the whole).
			'center'  => array_map(
				function ( $center ) {
					return round( max( 20, min( 75, (float) $center ) ), 1 );
				},
				WP_Swiper_Settings::responsive( $settings, 'art_ring_center' )
			),
			// Ring only (no ring in a grid): its opening per device, none (0) with Opening off.
			'opening' => 'ring' === $style ? ( empty( $settings['art_opening'] ) ? array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), 0 ) : self::per_device( $settings, 'art_ring_opening', 0, 180 ) ) : 0,
			// The photos' order per device (slide indexes; false: the slides'
			// order), and each slide's photo ID for the preview to tell one by.
			'order'   => self::orders( $settings, $slides ),
			'ids'     => array_map( 'intval', wp_list_pluck( $slides, 'id' ) ),
			// Height's unit per device: with % and Auto art.js sizes it there
			// (a background: the block's height, art.css).
			'height'  => $background ? array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), 'px' ) : self::height_units( $settings ),
			// Photo swapping (art.js): how often, and whether the pointer over it pauses it.
			'swap'    => $settings['art_swap'] ? array(
				'delay'  => max( 1000, min( 30000, (int) $settings['art_swap_delay'] ) ),
				// Any piece at random, or the middle one through all the photos
				// (art.js: any, where Content in a window is there). SVG shape: its own What changes
				// (a window at random, in turn, or all at once: "advance").
				'target' => $windows
					? ( in_array( $settings['art_shape_target'], array( 'any', 'turn', 'advance', 'one' ), true ) ? $settings['art_shape_target'] : 'any' )
					: ( $middle && 'middle' === $settings['art_swap_target'] ? 'middle' : 'any' ),
				'pause'  => (bool) $settings['art_pause'],
			) : false,
		);

		// The grids: as wide as the column, their photos and columns per device.
		$wide = self::is_wide( $settings );
		if ( $wide ) {
			$config['wide'] = true;
		}
		if ( $wide && 'grid-shapes' !== $style ) {
			$config['columns'] = self::per_device( $settings, 'art_grid_columns', 2, 6 );
			$config['cells']   = self::per_device( $settings, 'art_grid_count', 2, 36 );
		}
		// Text mask: its word and how it is drawn (art.js text()), its own Gap.
		if ( 'text' === $style ) {
			$config['gap']  = self::per_device( $settings, 'text_mask_gap', 0, 60 );
			$config['text'] = self::text_config( $settings );
		}
		// Grid shapes: its layout (art.js draws each shape at the stage's size).
		if ( 'grid-shapes' === $style ) {
			$grid           = self::cells( $settings );
			$config['grid'] = array(
				'columns' => $grid['columns'],
				'rows'    => $grid['rows'],
				'cells'   => $grid['cells'],
				// Objects, also when there is none.
				'radii'   => (object) $grid['radii'],
				'corners' => (object) $grid['corners'],
			);
		}

		// SVG shape: its windows (paths and boxes in numbers of the stage, the
		// main one first, each with its place in the stacking) and proportions.
		if ( $shape ) {
			$config['shapes'] = $shape['shapes'];
			$config['ratio']  = $shape['ratio'];
			// Another file on tablet or mobile: each device's (art.js takes the
			// one of the device shown). One that cannot be read: the desktop's.
			$own = array_map(
				function ( $one ) use ( $shape ) {
					return $one ? $one : $shape;
				},
				$shapes
			);
			if ( $own['tablet'] !== $shape || $own['mobile'] !== $shape ) {
				$config['shapeDevices'] = $own;
			}
		}
		// SVG shape, "One photo through all windows": every window shows its
		// part of the same photo (with swapping off too).
		$one = $windows && 'one' === $settings['art_shape_target'];
		if ( $one ) {
			$config['one'] = true;
		}
		// SVG shape, the windows all changing at once: the order they show a new
		// photo in (none: together).
		if ( $windows && in_array( $settings['art_shape_target'], array( 'one', 'advance' ), true ) && 'all' !== $settings['art_shape_appear'] ) {
			$config['appear'] = $settings['art_shape_appear'];
		}

		$shadow  = WP_Swiper_Settings::shadow( $settings );
		// A ring's or shape's width per device: its Height times its proportions.
		$ratios = array();
		if ( $shape ) {
			foreach ( isset( $config['shapeDevices'] ) ? $config['shapeDevices'] : array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), $shape ) as $device => $drawing ) {
				$ratios[ $device ] = $drawing['ratio'];
			}
		}
		$sizes  = self::sizes( $settings, $ratios );
		$blocks = self::block_heights( $settings );
		// Ring: an image or text in its opening (art.js places it, a gap from the photos).
		$opening = 'ring' === $style && ! empty( $settings['art_opening'] ) ? self::opening( $settings ) : '';
		if ( '' !== $opening ) {
			$config['openingGap'] = self::per_device( $settings, 'art_opening_gap', 0, 200 );
		}
		// Content in a window: an image and text instead of a photo (art.js
		// fill() moves it there), its window per device; not as a background.
		$content = self::has_content( $style ) && ! empty( $settings['art_content'] ) && ! $background && ( 'shape' !== $style || $shape ) ? WP_Swiper_Render::content_box( $settings, 'art_content' ) : '';
		if ( '' !== $content ) {
			$config['content'] = array( 'window' => self::per_device( $settings, 'art_content_window', 1, 100 ) );
			// Its look is frontend.css's (as a Collage's place's).
			WP_Swiper_Render::style( 'wp-swiper' );
		}

		// The Gradient, while it is on: its CSS ('' none), one across the whole shape (art.js, art.css).
		$gradient = WP_Swiper_Settings::gradient( $settings );

		$classes = array_filter(
			array_merge(
				array(
					'wp-swiper-art',
					'wp-swiper-art--' . $style,
					'wp-swiper-art--' . (int) $post_id,
				),
				self::caption_classes( $settings ),
				array( $settings['lightbox'] ? 'wp-swiper-art--lightbox' : '' ),
				// Grid + circle (art.css): the stage as wide as the column, its Height.
				array( $wide ? 'wp-swiper-art--wide' : '' ),
				// Image border: art.js draws it along each shape.
				array( $settings['art_border'] ? 'wp-swiper-art--border' : '' ),
				// Captions on the middle photo only (art.css).
				array( $middle && $settings['art_caption_middle'] ? 'wp-swiper-art--caption-middle' : '' ),
				// SVG shape, one photo through all the windows (art.css).
				array( $one ? 'wp-swiper-art--one' : '' ),
				// Image shadow (art.css): along each shape, room kept around the stage for it.
				array( $shadow ? 'wp-swiper-art--shadow' : '' ),
				// "Zoom on hover" (art.css): the photo under the pointer.
				array( WP_Swiper_Settings::hover_zoom( $settings ) ? 'wp-swiper-art--hover-zoom' : '' ),
				// Gradient (art.css): over the photos, one across the whole shape.
				array( '' !== $gradient ? 'wp-swiper-art--gradient' : '' ),
				// The block's background (art.js, art.css), with a color over it; a
				// ring or SVG shape placed in the block (its size and position).
				array( $background ? 'wp-swiper-art--background' : '' ),
				array( $background && ! $wide ? 'wp-swiper-art--bg-fit' : '' ),
				array( $background && '' !== WP_Swiper_Settings::background_overlay( $settings ) ? 'wp-swiper-art--bg-overlay' : '' ),
				array( WP_Swiper_Settings::sanitize_classes( $settings['css_class'] . ' ' . $extra_class ) )
			)
		);
		$css     = array_merge(
			array(
				'--wp-swiper-art-size:' . $sizes['desktop'],
				'--wp-swiper-art-size-tablet:' . $sizes['tablet'],
				'--wp-swiper-art-size-mobile:' . $sizes['mobile'],
				// Height in %: the outer block's, of its parent (art.css).
				'--wp-swiper-art-block-height:' . $blocks['desktop'],
				'--wp-swiper-art-block-height-tablet:' . $blocks['tablet'],
				'--wp-swiper-art-block-height-mobile:' . $blocks['mobile'],
				// Alignment per device (art.css): the stage's side margins.
				'--wp-swiper-art-margin:' . $margins['desktop'],
				'--wp-swiper-art-margin-tablet:' . $margins['tablet'],
				'--wp-swiper-art-margin-mobile:' . $margins['mobile'],
				// Photo swapping: how long a photo fades in.
				'--wp-swiper-art-speed:' . max( 100, min( 3000, (int) $settings['art_swap_speed'] ) ) . 'ms',
			),
			$wide ? self::height_style( $settings ) : array(),
			// SVG shape: the stage at its drawing's proportions (art.css).
			$shape ? array( '--wp-swiper-art-ratio:' . $shape['ratio'] ) : array(),
			// Another file on tablet or mobile: its proportions there (art.css).
			isset( $config['shapeDevices'] ) ? array(
				'--wp-swiper-art-ratio-tablet:' . $config['shapeDevices']['tablet']['ratio'],
				'--wp-swiper-art-ratio-mobile:' . $config['shapeDevices']['mobile']['ratio'],
			) : array(),
			self::opening_style( $opening, $settings ),
			self::caption_style( $settings ),
			self::border_style( $settings ),
			$shadow ? array(
				'--wp-swiper-art-shadow:drop-shadow(' . $shadow['x'] . 'px ' . $shadow['y'] . 'px ' . $shadow['blur'] . 'px ' . $shadow['color'] . ')',
			) : array(),
			'' !== $gradient ? array( '--wp-swiper-art-gradient:' . $gradient ) : array(),
			// The background's Overlay color (art.css).
			$background && '' !== WP_Swiper_Settings::background_overlay( $settings ) ? array( '--wp-swiper-bg-overlay:' . WP_Swiper_Settings::background_overlay( $settings ) ) : array(),
			// A ring or SVG shape as a background: its size and place in the block.
			$background && ! $wide ? self::background_fit( $settings, $ratios ) : array()
		);

		// The slides' descriptions under it, as under a slider (the same block,
		// look and Text transition; frontend.css): art.js shows the one of the
		// photo under the pointer, else the middle one's. Direction does not
		// apply to Art (an older one may be saved).
		$settings['direction'] = 'horizontal';
		$descriptions          = $background ? '' : WP_Swiper_Render::descriptions( $post_id, $slides, $settings );
		if ( '' !== $descriptions ) {
			WP_Swiper_Render::style( 'wp-swiper' );
		}

		// The lightbox: a slider's own (frontend.js openLightbox(), with its
		// Swiper and stylesheet), opened by art.js on a click on a photo. Its
		// image corners: its own radius, else Art's Rounded corners; the
		// descriptions over or under the image, as set.
		$config['lightbox'] = false;
		if ( $settings['lightbox'] ) {
			// frontend.js, without Swiper: the lightbox loads it when it first opens.
			WP_Swiper_Render::enqueue( false );
			$texts              = '' !== $descriptions && $settings['description_lightbox'];
			$config['lightbox'] = array(
				'radius'       => WP_Swiper_Settings::lightbox_image_radius( $settings ),
				'descriptions' => $texts,
				'text'         => $texts && 'under' === $settings['description_lightbox_place'] ? array(
					'width' => array_map( 'intval', WP_Swiper_Settings::responsive( $settings, 'description_lightbox_width' ) ),
					'max'   => (int) $settings['description_lightbox_max'],
					'align' => WP_Swiper_Settings::legacy_description_align( $settings ) ? WP_Swiper_Settings::legacy_description_align( $settings ) : 'left',
				) : false,
			);
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo '' !== $settings['css_id'] ? ' id="' . esc_attr( $settings['css_id'] ) . '"' : ''; ?> style="<?php echo esc_attr( implode( ';', $css ) ); ?>" data-wp-swiper-art="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"<?php echo $background ? ' data-wp-swiper-bg="' . esc_attr( $settings['background_class'] ) . '"' : ''; ?>>
			<div class="wp-swiper-art__stage">
				<?php
				// The pieces, empty: art.js puts the photos in them in the device's order.
				for ( $i = 0; $i < $count; $i++ ) {
					printf( '<div class="wp-swiper-art__piece" data-piece="%d"></div>', (int) $i );
				}
				?>
			</div>
			<?php if ( 'text' === $style ) : ?>
				<?php // Text mask: its word as text (the letters drawn are pictures of it), for screen readers and search. ?>
				<span class="wp-swiper-art__word"><?php echo esc_html( trim( (string) $settings['text_mask_title'] ) ); ?></span>
			<?php endif; ?>
			<?php echo $opening; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built. ?>
			<?php if ( '' !== $content ) : ?>
				<?php // On the page itself, hidden: art.js moves this very node into its window (a shortcode's scripts find it). ?>
				<div class="wp-swiper-art__content-pool" hidden><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built. ?></div>
			<?php endif; ?>
			<template class="wp-swiper-art__pool">
				<?php
				foreach ( $slides as $index => $slide ) {
					self::photo( $slide, $index, $settings );
				}
				?>
			</template>
		</div>
		<?php
		echo $descriptions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
		// No block with the class around it: art.js leaves it as it is and
		// shows this to those who can fix it (as a slider's).
		if ( $background && current_user_can( WP_Swiper_Settings::CAPABILITY ) ) {
			echo '<p class="wp-swiper-notice" data-wp-swiper-bg-missing hidden>' . esc_html(
				'' === $settings['background_class']
					? __( 'WP Swiper: this slider is set to be a background, but has no parent block class (Background).', 'wp-swiper' )
					/* translators: %s: CSS class name(s). */
					: sprintf( __( 'WP Swiper: no block with the class “%s” around this slider, so it is not a background. Give that class to its row or column.', 'wp-swiper' ), $settings['background_class'] )
			) . '</p>';
		}
		return ob_get_clean();
	}

}
