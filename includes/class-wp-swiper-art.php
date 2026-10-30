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

class WP_Swiper_Art {

	const EFFECT = 'art';


	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'wp-swiper-art', WP_SWIPER_URL . 'assets/css/art.css', array(), wp_swiper_asset_version( 'assets/css/art.css' ) );
		wp_register_script( 'wp-swiper-art', WP_SWIPER_URL . 'assets/js/art.js', array(), wp_swiper_asset_version( 'assets/js/art.js' ), true );
	}

	/**
	 * The styles of Art: the Style list (each with its own fields below).
	 *
	 * @return array value => label
	 */
	public static function styles() {
		return array(
			// A circle in the middle, the others around it in a ring (with an
			// opening at the bottom, if set).
			'ring'      => __( 'Ring', 'wp-swiper' ),
			// The same ring without a photo in the middle: an image and/or text
			// there instead (center()).
			'ring-text'   => __( 'Ring + text', 'wp-swiper' ),
			// A square grid (its Columns per device) with a circle over its
			// middle: the cells around it cut by it (those it covers left out).
			'grid-circle' => __( 'Grid + circle', 'wp-swiper' ),
			// The same grid without a photo in the circle: an image and/or text
			// there instead, as Ring + text's (center()).
			'grid-circle-text' => __( 'Grid + circle + text', 'wp-swiper' ),
			// The grid with a square over its middle instead: the cells around it
			// cut by it (an L at a corner, a notch at a side), its photo in it.
			'grid-square'      => __( 'Grid + square', 'wp-swiper' ),
			// The same with an image and/or text in the square (center()).
			'grid-square-text' => __( 'Grid + square + text', 'wp-swiper' ),
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
	 * Whether a Style has a photo in the middle (Ring, Grid + circle, Grid +
	 * square), else (the "+ text" ones) the image and text of center().
	 *
	 * @param string $style Style.
	 * @return bool
	 */
	public static function has_middle_photo( $style ) {
		// SVG shape: its main window.
		return in_array( $style, array( 'ring', 'grid-circle', 'grid-square', 'shape' ), true );
	}

	/**
	 * Whether it is as wide as the column (the grids), not a square of its
	 * Size (the rings).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_wide( $settings ) {
		return in_array( self::style_of( $settings ), array( 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text' ), true );
	}

	/**
	 * The Style as drawn: a known one, else Ring.
	 *
	 * @param array $settings Slider settings.
	 * @return string
	 */
	public static function style_of( $settings ) {
		return array_key_exists( $settings['art_style'], self::styles() ) ? $settings['art_style'] : 'ring';
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
	 * shape on the Layout page, Ring + text's Content in the middle a block
	 * after it (its Rounded corners and Image border are in the Look block,
	 * where a slider's are, over the Image shadow: WP_Swiper_Settings::blocks()),
	 * Photo swapping on the Controls page.
	 *
	 * @return array page => [ block => [ 'title' => string, 'fields' => string[] ] ]
	 */
	public static function blocks() {
		return array(
			'layout'   => array(
				'art'        => array(
					'title'  => __( 'Art', 'wp-swiper' ),
					'fields' => array( 'art_style', 'art_shape', 'art_shape_margins', 'art_height', 'art_align', 'art_ring_count', 'art_grid_count', 'art_grid_columns', 'art_gap' ),
				),
				// Ring + text: what is in the middle, a block of its own (shown
				// for that Style only, as its fields are).
				'art_middle' => array(
					'title'  => __( 'Content in the middle', 'wp-swiper' ),
					'fields' => array( 'art_center', 'art_center_image', 'art_center_image_size', 'art_center_text', 'art_center_font_size', 'art_center_padding', 'art_center_bg' ),
				),
				// Ring: its opening at the bottom and what is in it, a block of its own too.
				'art_opening' => array(
					'title'  => __( 'Opening', 'wp-swiper' ),
					'fields' => array( 'art_opening', 'art_ring_opening', 'art_opening_image', 'art_opening_image_size', 'art_opening_text', 'art_opening_font_size', 'art_opening_gap' ),
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
	 * A per-device number setting as each device uses it (tablet and mobile
	 * empty: the larger screen's), within its limits.
	 *
	 * @param array  $settings Slider settings.
	 * @param string $key      Setting.
	 * @param int    $min      Lowest.
	 * @param int    $max      Highest.
	 * @return array device => int
	 */
	public static function per_device( $settings, $key, $min, $max ) {
		return array_map(
			function ( $value ) use ( $min, $max ) {
				return max( $min, min( $max, (int) $value ) );
			},
			WP_Swiper_Settings::responsive( $settings, $key )
		);
	}

	/**
	 * How many photos a Style shows on each device (Ring: the ring's, and
	 * one in the middle; Ring + text: the ring's; Grid + circle: its Photos in
	 * the grid and the circle's, art.js leaves out the cells it covers; Grid +
	 * circle + text: the grid's).
	 *
	 * @param array $settings Slider settings.
	 * @return array device => int
	 */
	public static function counts( $settings ) {
		$style = self::style_of( $settings );
		// SVG shape: its windows, on every device.
		if ( 'shape' === $style ) {
			return array_map(
				function ( $shape ) {
					return $shape ? count( $shape['shapes'] ) : 1;
				},
				self::shapes( $settings )
			);
		}
		if ( self::is_wide( $settings ) ) {
			$middle = self::has_middle_photo( $style ) ? 1 : 0;
			return array_map(
				function ( $photos ) use ( $middle ) {
					return $photos + $middle;
				},
				self::per_device( $settings, 'art_grid_count', 2, 36 )
			);
		}
		$middle = self::has_middle_photo( $style ) ? 1 : 0;
		return array_map(
			function ( $around ) use ( $middle ) {
				return $middle + $around;
			},
			self::per_device( $settings, 'art_ring_count', 3, 16 )
		);
	}

	/**
	 * Height's unit on each device (tablet and mobile empty: the larger screen's).
	 *
	 * @param array $settings Slider settings.
	 * @return array device => 'px' | 'vh' | '%' | 'auto'
	 */
	public static function height_units( $settings ) {
		return array_map(
			function ( $unit ) {
				return in_array( $unit, array( 'px', 'vh', '%', 'auto' ), true ) ? $unit : 'px';
			},
			WP_Swiper_Settings::responsive( $settings, 'art_height_unit' )
		);
	}

	/**
	 * The stage's width on each device as CSS for a ring or an SVG shape
	 * (art.css keeps it within the column): its Height times its proportions
	 * (a ring 1, a shape its drawing's, `ratios`), px from 160, vh up to 100.
	 * % and Auto: the column's width, art.js makes it smaller to fit the
	 * parent's height or the column's free height.
	 *
	 * @param array $settings Slider settings.
	 * @param array $ratios   device => width / height (1 for a ring).
	 * @return array device => string
	 */
	public static function sizes( $settings, $ratios = array() ) {
		$values = WP_Swiper_Settings::responsive( $settings, 'art_height' );
		$sizes  = array();
		foreach ( self::height_units( $settings ) as $device => $unit ) {
			$ratio = isset( $ratios[ $device ] ) && (float) $ratios[ $device ] > 0 ? (float) $ratios[ $device ] : 1;
			$value = max( 1, (int) $values[ $device ] );
			if ( 'px' === $unit ) {
				$sizes[ $device ] = round( max( 160, min( 2000, $value ) ) * $ratio, 2 ) . 'px';
			} elseif ( 'vh' === $unit ) {
				$sizes[ $device ] = round( min( 100, $value ) * $ratio, 2 ) . 'vh';
			} else {
				$sizes[ $device ] = '100%';
			}
		}
		return $sizes;
	}

	/**
	 * A grid's height on each device as CSS (art.css): px from 120, vh up to
	 * 100; % the outer block's (whose height is the % of its parent,
	 * block_heights()); Auto: art.js fills the column's free height.
	 *
	 * @param array $settings Slider settings.
	 * @return array device => string
	 */
	public static function grid_heights( $settings ) {
		$values  = WP_Swiper_Settings::responsive( $settings, 'art_height' );
		$heights = array();
		foreach ( self::height_units( $settings ) as $device => $unit ) {
			$value = max( 1, (int) $values[ $device ] );
			if ( 'px' === $unit ) {
				$heights[ $device ] = max( 120, min( 2000, $value ) ) . 'px';
			} elseif ( 'vh' === $unit ) {
				$heights[ $device ] = min( 100, $value ) . 'vh';
			} else {
				$heights[ $device ] = '%' === $unit ? '100%' : 'auto';
			}
		}
		return $heights;
	}

	/**
	 * The outer block's height on each device (art.css): Height in % there,
	 * of its parent's (as a slider's); else as tall as it is.
	 *
	 * @param array $settings Slider settings.
	 * @return array device => string
	 */
	public static function block_heights( $settings ) {
		$values  = WP_Swiper_Settings::responsive( $settings, 'art_height' );
		$heights = array();
		foreach ( self::height_units( $settings ) as $device => $unit ) {
			$heights[ $device ] = '%' === $unit ? min( 100, max( 1, (int) $values[ $device ] ) ) . '%' : 'auto';
		}
		return $heights;
	}

	/**
	 * How many pieces the markup has: as many as the device with the most
	 * shows (art.js hides those another device leaves out).
	 *
	 * @param array $settings Slider settings.
	 * @return int
	 */
	public static function count( $settings ) {
		return max( self::counts( $settings ) );
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
			return current_user_can( 'edit_posts' )
				? '<p class="wp-swiper-notice">' . esc_html__( 'WP Swiper: the Art effect shows images only; this slider has none.', 'wp-swiper' ) . '</p>'
				: '';
		}

		WP_Swiper_Render::style( 'wp-swiper-art' );
		wp_enqueue_script( 'wp-swiper-art' );

		// A click opens either links or the lightbox, never a mix (as in a
		// slider): any link turns the lightbox off.
		if ( array_filter( wp_list_pluck( $slides, 'link' ) ) ) {
			$settings['lightbox'] = false;
		}

		$style   = self::style_of( $settings );
		// SVG shape: no file yet (or no shapes in it), nothing to show.
		$shapes = 'shape' === $style ? self::shapes( $settings ) : null;
		$shape  = $shapes ? $shapes['desktop'] : null;
		if ( 'shape' === $style && ! $shape ) {
			return current_user_can( 'edit_posts' )
				? '<p class="wp-swiper-notice">' . esc_html__( 'WP Swiper: upload an SVG file for the SVG shape style (Layout, Art).', 'wp-swiper' ) . '</p>'
				: '';
		}
		$middle  = self::has_middle_photo( $style );
		$count   = self::count( $settings );
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
			// Ring only (Ring + text goes all around; no ring in a grid).
			// Ring: its opening per device, none (0) with Opening off.
			'opening' => 'ring' === $style ? ( empty( $settings['art_opening'] ) ? array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), 0 ) : self::per_device( $settings, 'art_ring_opening', 0, 180 ) ) : 0,
			// The photos' order per device (slide indexes; false: the slides'
			// order), and each slide's photo ID for the preview to tell one by.
			'order'   => self::orders( $settings, $slides ),
			'ids'     => array_map( 'intval', wp_list_pluck( $slides, 'id' ) ),
			// Height's unit per device: with % and Auto art.js sizes it there.
			'height'  => self::height_units( $settings ),
			// Photo swapping (art.js): how often, and whether the pointer over it pauses it.
			'swap'    => $settings['art_swap'] ? array(
				'delay'  => max( 1000, min( 30000, (int) $settings['art_swap_delay'] ) ),
				// Any piece at random, or the middle one through all the photos.
				// (Ring + text has no middle photo.) SVG shape: its own What changes
				// (a window at random, in turn, or all at once: "advance").
				'target' => $shape
					? ( in_array( $settings['art_shape_target'], array( 'any', 'turn', 'advance', 'one' ), true ) ? $settings['art_shape_target'] : 'any' )
					: ( $middle && 'middle' === $settings['art_swap_target'] ? 'middle' : 'any' ),
				'pause'  => (bool) $settings['art_pause'],
			) : false,
		);

		// The grids: as wide as the column, their photos and columns per device.
		$wide = self::is_wide( $settings );
		if ( $wide ) {
			$config['wide']    = true;
			$config['columns'] = self::per_device( $settings, 'art_grid_columns', 2, 6 );
			$config['cells']   = self::per_device( $settings, 'art_grid_count', 2, 36 );
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
		$one = $shape && 'one' === $settings['art_shape_target'];
		if ( $one ) {
			$config['one'] = true;
		}
		// SVG shape, the windows all changing at once: the order they show a new
		// photo in (none: together).
		if ( $shape && in_array( $settings['art_shape_target'], array( 'one', 'advance' ), true ) && 'all' !== $settings['art_shape_appear'] ) {
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
		// Ring + text: the image and text in the middle (art.js places them).
		$center = in_array( $style, array( 'ring-text', 'grid-circle-text', 'grid-square-text' ), true ) && $settings['art_center'] ? self::center( $settings ) : '';

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
			self::center_style( $center, $settings ),
			self::caption_style( $settings ),
			self::border_style( $settings ),
			$shadow ? array(
				'--wp-swiper-art-shadow:drop-shadow(' . $shadow['x'] . 'px ' . $shadow['y'] . 'px ' . $shadow['blur'] . 'px ' . $shadow['color'] . ')',
			) : array()
		);

		// The slides' descriptions under it, as under a slider (the same block,
		// look and Text transition; frontend.css): art.js shows the one of the
		// photo under the pointer, else the middle one's. Direction does not
		// apply to Art (an older one may be saved).
		$settings['direction'] = 'horizontal';
		$descriptions          = WP_Swiper_Render::descriptions( $post_id, $slides, $settings );
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
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo '' !== $settings['css_id'] ? ' id="' . esc_attr( $settings['css_id'] ) . '"' : ''; ?> style="<?php echo esc_attr( implode( ';', $css ) ); ?>" data-wp-swiper-art="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<div class="wp-swiper-art__stage">
				<?php
				// The pieces, empty: art.js puts the photos in them in the device's order.
				for ( $i = 0; $i < $count; $i++ ) {
					printf( '<div class="wp-swiper-art__piece" data-piece="%d"></div>', (int) $i );
				}
				?>
			</div>
			<?php echo $opening . $center; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where they are built. ?>
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
		return ob_get_clean();
	}

	/**
	 * The order of the photos per device as slide indexes: those of the order
	 * set there (by photo ID; a photo twice in the slides: its first slide),
	 * then the others in the slides' order; false where none is set.
	 *
	 * @param array $settings Slider settings.
	 * @param array $slides   The slides shown (images).
	 * @return array device => int[] | false
	 */
	public static function orders( $settings, $slides ) {
		$orders = array();
		foreach ( WP_Swiper_Settings::responsive( $settings, 'art_order' ) as $device => $value ) {
			$value = WP_Swiper_Settings::sanitize_order( $value );
			if ( '' === $value ) {
				$orders[ $device ] = false;
				continue;
			}
			$used = array();
			foreach ( explode( ',', $value ) as $id ) {
				foreach ( $slides as $index => $slide ) {
					if ( (int) $slide['id'] === (int) $id && ! isset( $used[ $index ] ) ) {
						$used[ $index ] = true;
						break;
					}
				}
			}
			$list = array_keys( $used );
			foreach ( array_keys( $slides ) as $index ) {
				if ( ! isset( $used[ $index ] ) ) {
					$list[] = $index;
				}
			}
			$orders[ $device ] = $used ? $list : false;
		}
		return $orders;
	}

	/**
	 * Ring: what sits in its opening, an image over the editor's text (either
	 * or both, as Ring + text's middle), hidden until art.js places it
	 * (beside the stage, not in it: the photos' shadow is not for it). The
	 * image the size that fits the largest Art; '' when neither is there.
	 *
	 * @param array $settings Slider settings.
	 * @return string Markup.
	 */
	public static function opening( $settings ) {
		$image = wp_get_attachment_image( (int) $settings['art_opening_image'], 'large', false, array( 'class' => 'wp-swiper-art__opening-image', 'loading' => 'lazy' ) );
		$text  = WP_Swiper_Settings::sanitize_description( $settings['art_opening_text'] );
		if ( '' === $image && '' === $text ) {
			return '';
		}
		return '<div class="wp-swiper-art__opening" hidden><div class="wp-swiper-art__opening-inner">'
			. $image
			. ( '' === $text ? '' : '<div class="wp-swiper-art__opening-text">' . $text . '</div>' )
			. '</div></div>';
	}

	/**
	 * The opening's CSS variables per device: the image's size (% of the free
	 * space) and the text's font size; none without content.
	 *
	 * @param string $opening  The markup (opening()).
	 * @param array  $settings Slider settings.
	 * @return string[]
	 */
	private static function opening_style( $opening, $settings ) {
		if ( '' === $opening ) {
			return array();
		}
		$style  = array();
		$vars   = array(
			'art_opening_image_size' => array( '--wp-swiper-art-opening-size', '%', 10, 100 ),
			'art_opening_font_size'  => array( '--wp-swiper-art-opening-font', 'px', 8, 120 ),
		);
		$suffix = array(
			'desktop' => '',
			'tablet'  => '-tablet',
			'mobile'  => '-mobile',
		);
		foreach ( $vars as $key => $var ) {
			foreach ( self::per_device( $settings, $key, $var[2], $var[3] ) as $device => $value ) {
				$style[] = $var[0] . $suffix[ $device ] . ':' . $value . $var[1];
			}
		}
		return $style;
	}

	/**
	 * The grids: the stage's height per device (art.css, grid_heights()).
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	private static function height_style( $settings ) {
		$style = array();
		foreach ( self::grid_heights( $settings ) as $device => $height ) {
			$style[] = '--wp-swiper-art-height' . WP_Swiper_Render::SUFFIX[ $device ] . ':' . $height;
		}
		return $style;
	}

	/**
	 * Ring + text: what is in the middle circle, an image over the editor's
	 * text (either or both), hidden until art.js places it over the circle
	 * (beside the stage, as the opening's: the photos' shadow is not for it);
	 * '' when neither is there.
	 *
	 * @param array $settings Slider settings.
	 * @return string Markup.
	 */
	public static function center( $settings ) {
		$image = wp_get_attachment_image( (int) $settings['art_center_image'], 'large', false, array( 'class' => 'wp-swiper-art__center-image', 'loading' => 'lazy' ) );
		$text  = WP_Swiper_Settings::sanitize_description( $settings['art_center_text'] );
		if ( '' === $image && '' === $text ) {
			return '';
		}
		return '<div class="wp-swiper-art__center" hidden><div class="wp-swiper-art__center-inner">'
			. $image
			. ( '' === $text ? '' : '<div class="wp-swiper-art__center-text">' . $text . '</div>' )
			. '</div></div>';
	}

	/**
	 * The middle's CSS variables per device: the image's size (% of the width
	 * inside the circle), the font size and the padding; its background.
	 * None without content.
	 *
	 * @param string $center   The markup (center()).
	 * @param array  $settings Slider settings.
	 * @return string[]
	 */
	private static function center_style( $center, $settings ) {
		if ( '' === $center ) {
			return array();
		}
		$style = array();
		$vars  = array(
			'art_center_image_size' => array( '--wp-swiper-art-center-image', '%', 10, 100 ),
			'art_center_font_size'  => array( '--wp-swiper-art-center-font', 'px', 8, 120 ),
			'art_center_padding'    => array( '--wp-swiper-art-center-padding', 'px', 0, 200 ),
		);
		foreach ( $vars as $key => $var ) {
			foreach ( self::per_device( $settings, $key, $var[2], $var[3] ) as $device => $value ) {
				$style[] = $var[0] . WP_Swiper_Render::SUFFIX[ $device ] . ':' . $value . $var[1];
			}
		}
		$bg = WP_Swiper_Settings::sanitize_slide_color( (string) $settings['art_center_bg'] );
		if ( '' !== $bg ) {
			$style[] = '--wp-swiper-art-center-bg:' . $bg;
		}
		return $style;
	}

	/**
	 * The captions' look (the Caption page): a set weight, the text shadow and
	 * the background: none, the whole photo darkened (Solid) or darkened from
	 * its caption outwards (Gradient). Vertical text does not apply to Art.
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	private static function caption_classes( $settings ) {
		return array(
			// A weight saved for the whole slider (until 2.8); each caption's on itself (photo()).
			'' !== WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ) ? 'wp-swiper-art--caption-weight' : '',
			! $settings['caption_bg'] ? 'wp-swiper-art--caption-nobg' : ( 'solid' === $settings['caption_bg_style'] ? 'wp-swiper-art--caption-solid' : '' ),
			$settings['caption_shadow'] ? 'wp-swiper-art--caption-shadow' : '',
		);
	}

	/**
	 * The captions' size per device, color, background and weight: the same
	 * variables as a slider's (art.css uses them).
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	private static function caption_style( $settings ) {
		$style = array();
		// The Caption page's size for every photo; one with its own sets it on
		// its caption (photo()).
		$style = array_merge( $style, WP_Swiper_Settings::font_size_vars( WP_Swiper_Settings::caption_sizes( $settings ), 'wp-swiper-caption-size' ) );
		$style[] = '--wp-swiper-caption-color:' . $settings['caption_color'];
		if ( $settings['caption_bg'] ) {
			list( $from, $to ) = WP_Swiper_Settings::caption_bg_colors( $settings );
			$style[]           = '--wp-swiper-caption-bg-from:' . $from;
			$style[]           = '--wp-swiper-caption-bg-to:' . $to;
		}
		$style = array_merge( $style, WP_Swiper_Settings::font_weight_vars( WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ), 'wp-swiper-caption-weight' ) );
		return $style;
	}

	/**
	 * Image border: its width per device (0: none there) and its color (art.css).
	 *
	 * @param array $settings Slider settings.
	 * @return string[]
	 */
	private static function border_style( $settings ) {
		if ( ! $settings['art_border'] ) {
			return array();
		}
		$style = array();
		foreach ( self::per_device( $settings, 'art_border_width', 0, 50 ) as $device => $width ) {
			$style[] = '--wp-swiper-art-border-width' . WP_Swiper_Render::SUFFIX[ $device ] . ':' . $width . 'px';
		}
		$color   = sanitize_hex_color( $settings['art_border_color'] );
		$style[] = '--wp-swiper-art-border-color:' . ( $color ? $color : '#ffffff' );
		return $style;
	}

	/**
	 * One photo in the pool (art.js copies it into its piece), linked when
	 * its slide has a link, with its caption (art.js sets where in the
	 * piece's shape the text sits).
	 *
	 * @param array $slide    Slide.
	 * @param int   $index    Its slide index (0: the first).
	 * @param array $settings Slider settings.
	 */
	private static function photo( $slide, $index, $settings ) {
		// The full size only, no smaller copies to pick from (srcset): a photo
		// is cropped to its shape, often drawn wider than its piece, and the
		// browser's guess from the piece made it blurry first, sharp later.
		add_filter( 'wp_calculate_image_srcset', '__return_false', 99 );
		$image = wp_get_attachment_image(
			$slide['id'],
			'full',
			false,
			array(
				'class'    => 'wp-swiper-art__image',
				'loading'  => 0 === $index ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);
		remove_filter( 'wp_calculate_image_srcset', '__return_false', 99 );
		if ( ! empty( $slide['link'] ) ) {
			$image = sprintf(
				'<a class="wp-swiper-art__link" href="%s"%s>%s</a>',
				esc_url( $slide['link'] ),
				empty( $slide['new_tab'] ) ? '' : ' target="_blank" rel="noopener"',
				$image
			);
		}
		// Sanitized again: saved before, or posted by the preview.
		$caption = empty( $slide['caption'] ) ? '' : WP_Swiper_Settings::sanitize_caption( $slide['caption'] );
		if ( '' !== $caption ) {
			// Outer block: the background over the photo; inner one: typography,
			// its lines paragraphs or headings (caption_markup()).
			// Its own font size (the Slide info window), per device.
			$size    = WP_Swiper_Settings::own_font_size_vars( isset( $slide['caption_size'] ) ? $slide['caption_size'] : '', 'wp-swiper-caption-size' );
			// And its own font weight (a class: none keeps the tag's).
			$weight  = WP_Swiper_Settings::font_weight_vars( isset( $slide['caption_weight'] ) ? $slide['caption_weight'] : '', 'wp-swiper-caption-weight' );
			$size    = array_merge( $size, $weight );
			// And its own color (none: the Caption page's).
			$color   = isset( $slide['caption_color'] ) ? WP_Swiper_Settings::sanitize_slide_color( $slide['caption_color'] ) : '';
			if ( '' !== $color ) {
				$size[] = '--wp-swiper-caption-color:' . $color;
			}
			$caption = sprintf( '<div class="wp-swiper-art__caption%3$s"%2$s><div class="wp-swiper-art__caption-text">%1$s</div></div>', WP_Swiper_Settings::caption_markup( $caption, $settings ), $size ? ' style="' . esc_attr( implode( ';', $size ) ) . '"' : '', $weight ? ' wp-swiper-art__caption--weight' : '' );
		}
		printf(
			'<div class="wp-swiper-art__photo" data-real="%d"%s>%s%s</div>',
			(int) $index,
			// What the lightbox shows: the image's full size.
			$settings['lightbox'] ? ' data-wp-swiper-full="' . esc_url( wp_get_attachment_image_url( $slide['id'], 'full' ) ) . '"' : '',
			$image, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup, link escaped above.
			$caption // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text sanitized above (caption_tags()), whitelisted tags.
		);
	}
}
