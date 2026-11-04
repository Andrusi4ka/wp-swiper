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
	 * Whether a Style has a photo in the middle (Ring, Grid + circle, Grid +
	 * square), else (the "+ text" ones) the image and text of center().
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
		return in_array( self::style_of( $settings ), array( 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text', 'grid-shapes', 'text' ), true );
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

	// Grid shapes: the most columns and rows.
	const CELLS_MAX = 12;

	/**
	 * Grid shapes: a layout as stored, read. "4x4:0,0,1,2,…;1=100,7=50;6.2=0":
	 * columns x rows, then the shape of each cell row by row (its number,
	 * -1 for an empty cell), then the shapes' own corner radius (% of the
	 * largest, half a cell's smaller side), each "shape=percent", then single
	 * outward corners' own, each "x.y=percent": the corner's place on the grid
	 * of cells and gaps (cells at even, gaps at odd lines: x from 0 to
	 * 2 × columns - 1). Null for anything else.
	 *
	 * @param string $value Stored value.
	 * @return array|null [ 'columns' => int, 'rows' => int, 'cells' => int[], 'radii' => [ shape => int ], 'corners' => [ "x.y" => int ] ]
	 */
	public static function parse_cells( $value ) {
		$value = preg_replace( '/\s+/', '', (string) $value );
		if ( strlen( $value ) > 4000 || ! preg_match( '/^(\d{1,2})x(\d{1,2}):(-?\d{1,3}(?:,-?\d{1,3})*)(?:;((?:\d{1,3}=\d{1,3})(?:,\d{1,3}=\d{1,3})*)?(?:;((?:\d{1,2}\.\d{1,2}=\d{1,3})(?:,\d{1,2}\.\d{1,2}=\d{1,3})*))?)?$/', $value, $m ) ) {
			return null;
		}
		$columns = (int) $m[1];
		$rows    = (int) $m[2];
		$cells   = array_map( 'intval', explode( ',', $m[3] ) );
		$max     = self::CELLS_MAX * self::CELLS_MAX;
		if ( $columns < 1 || $rows < 1 || $columns > self::CELLS_MAX || $rows > self::CELLS_MAX || count( $cells ) !== $columns * $rows ) {
			return null;
		}
		foreach ( $cells as $cell ) {
			if ( $cell < -1 || $cell >= $max ) {
				return null;
			}
		}
		$radii = array();
		if ( ! empty( $m[4] ) ) {
			foreach ( explode( ',', $m[4] ) as $pair ) {
				list( $shape, $percent ) = array_map( 'intval', explode( '=', $pair ) );
				if ( $shape < $max ) {
					$radii[ $shape ] = min( 100, $percent );
				}
			}
		}
		return array(
			'columns' => $columns,
			'rows'    => $rows,
			'cells'   => $cells,
			'radii'   => $radii,
			'corners' => self::corners_within( empty( $m[5] ) ? array() : self::read_corners( $m[5] ), $columns, $rows ),
		);
	}

	/**
	 * Grid shapes: single corners' own radii as stored ("6.2=0,3.4=100").
	 *
	 * @param string $text Stored.
	 * @return array "x.y" => percent
	 */
	private static function read_corners( $text ) {
		$corners = array();
		foreach ( explode( ',', $text ) as $pair ) {
			list( $at, $percent ) = explode( '=', $pair );
			list( $x, $y )        = array_map( 'intval', explode( '.', $at ) );
			$corners[ $x . '.' . $y ] = min( 100, (int) $percent );
		}
		return $corners;
	}

	/**
	 * Grid shapes: the corners' own radii of a grid of that size (the others
	 * left out), at most one per corner place.
	 *
	 * @param array $corners "x.y" => percent.
	 * @param int   $columns Columns.
	 * @param int   $rows    Rows.
	 * @return array "x.y" => percent
	 */
	private static function corners_within( $corners, $columns, $rows ) {
		$kept = array();
		foreach ( $corners as $at => $percent ) {
			list( $x, $y ) = array_map( 'intval', explode( '.', $at ) );
			if ( $x <= 2 * $columns - 1 && $y <= 2 * $rows - 1 ) {
				$kept[ $x . '.' . $y ] = $percent;
			}
		}
		return $kept;
	}

	/**
	 * Grid shapes: a layout with its shapes numbered from 0 in the order they
	 * first come row by row (empty cells -1), the radii of shapes left out dropped.
	 *
	 * @param array $grid As parse_cells() gives it (any shape numbers).
	 * @return array The same, numbered, with 'count' (how many shapes).
	 */
	public static function number_cells( $grid ) {
		$map   = array();
		$cells = array();
		foreach ( $grid['cells'] as $cell ) {
			if ( $cell < 0 ) {
				$cells[] = -1;
				continue;
			}
			if ( ! isset( $map[ $cell ] ) ) {
				$map[ $cell ] = count( $map );
			}
			$cells[] = $map[ $cell ];
		}
		$radii = array();
		foreach ( $grid['radii'] as $shape => $percent ) {
			if ( isset( $map[ $shape ] ) ) {
				$radii[ $map[ $shape ] ] = $percent;
			}
		}
		ksort( $radii );
		// The corners' own radii are by place on the grid, whatever the shapes' numbers.
		$corners = isset( $grid['corners'] ) ? $grid['corners'] : array();
		ksort( $corners, SORT_NATURAL );
		return array(
			'columns' => $grid['columns'],
			'rows'    => $grid['rows'],
			'cells'   => $cells,
			'radii'   => $radii,
			'corners' => $corners,
			'count'   => count( $map ),
		);
	}

	/**
	 * Grid shapes: a layout as stored (parse_cells()'s format).
	 *
	 * @param array $grid A layout.
	 * @return string
	 */
	public static function cells_text( $grid ) {
		$radii = array();
		foreach ( $grid['radii'] as $shape => $percent ) {
			$radii[] = (int) $shape . '=' . (int) $percent;
		}
		$corners = array();
		foreach ( isset( $grid['corners'] ) ? $grid['corners'] : array() as $at => $percent ) {
			$corners[] = $at . '=' . (int) $percent;
		}
		$text = (int) $grid['columns'] . 'x' . (int) $grid['rows'] . ':' . implode( ',', array_map( 'intval', $grid['cells'] ) );
		if ( $corners ) {
			return $text . ';' . implode( ',', $radii ) . ';' . implode( ',', $corners );
		}
		return $text . ( $radii ? ';' . implode( ',', $radii ) : '' );
	}

	/**
	 * Grid shapes: the layout kept for the setting, numbered (number_cells());
	 * '' for none or anything else (the default layout then).
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_cells( $value ) {
		$grid = self::parse_cells( (string) $value );
		return $grid ? self::cells_text( self::number_cells( $grid ) ) : '';
	}

	/**
	 * Grid shapes: the layout before any is drawn. On 4 x 4 a few shapes of
	 * different sizes, the cells of the others each a shape of its own.
	 *
	 * @param int $columns Columns.
	 * @param int $rows    Rows.
	 * @return array A layout (parse_cells()).
	 */
	public static function default_cells( $columns, $rows ) {
		if ( 4 === $columns && 4 === $rows ) {
			return self::parse_cells( '4x4:0,0,1,2,3,4,4,2,3,4,4,5,6,6,7,5;1=100,7=100' );
		}
		return array(
			'columns' => $columns,
			'rows'    => $rows,
			'cells'   => range( 0, $columns * $rows - 1 ),
			'radii'   => array(),
			'corners' => array(),
		);
	}

	/**
	 * Grid shapes: the layout as drawn, at its Columns and Rows (one for every
	 * device): the saved one (else the default one) where it reaches, each
	 * cell beyond it a shape of its own; numbered (number_cells()).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'columns', 'rows', 'cells', 'radii', 'count' ]
	 */
	public static function cells( $settings ) {
		$columns = max( 2, min( self::CELLS_MAX, (int) ( isset( $settings['art_cells_columns'] ) ? $settings['art_cells_columns'] : 4 ) ) );
		$rows    = max( 2, min( self::CELLS_MAX, (int) ( isset( $settings['art_cells_rows'] ) ? $settings['art_cells_rows'] : 4 ) ) );
		$saved   = self::parse_cells( isset( $settings['art_cells'] ) ? $settings['art_cells'] : '' );
		if ( ! $saved ) {
			$saved = self::default_cells( $columns, $rows );
		}
		$cells = array();
		// Shape numbers for the new cells, past any saved one.
		$next = self::CELLS_MAX * self::CELLS_MAX;
		for ( $y = 0; $y < $rows; $y++ ) {
			for ( $x = 0; $x < $columns; $x++ ) {
				$cells[] = $x < $saved['columns'] && $y < $saved['rows'] ? $saved['cells'][ $y * $saved['columns'] + $x ] : $next++;
			}
		}
		return self::number_cells(
			array(
				'columns' => $columns,
				'rows'    => $rows,
				'cells'   => $cells,
				'radii'   => $saved['radii'],
				// Those the grid still reaches (art.js uses those on an outward corner).
				'corners' => self::corners_within( $saved['corners'], $columns, $rows ),
			)
		);
	}

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
				// Text mask's word here too (Strips go, Word, Photos; its Height
				// is Art's): the block's title says which (admin.js, `titles`).
				'art'        => array(
					'title'  => __( 'Art', 'wp-swiper' ),
					'titles' => array( self::TEXT_EFFECT => __( 'Text mask', 'wp-swiper' ) ),
					'fields' => array( 'art_style', 'text_mask_direction', 'text_mask_title', 'text_mask_photos', 'art_shape', 'art_shape_margins', 'art_cells_columns', 'art_cells_rows', 'art_height', 'art_align', 'art_ring_count', 'art_grid_count', 'art_grid_columns', 'art_gap' ),
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
				// SVG shape: a window with content instead of a photo, a block of its own.
				'art_content' => array(
					'title'  => __( 'Content in a window', 'wp-swiper' ),
					'fields' => array( 'art_content', 'art_content_window', 'art_content_image', 'art_content_image_size', 'art_content_text', 'art_content_font_size', 'art_content_padding', 'art_content_valign', 'art_content_bg' ),
				),
				// Text mask: its letters and its strips, blocks of their own (after
				// Art's own blocks, hidden there as these are for Art: right after
				// the word's block on screen).
				'text_letters' => array(
					'title'  => __( 'Letters', 'wp-swiper' ),
					'fields' => array( 'text_mask_font', 'text_mask_weight', 'text_mask_font_name', 'text_mask_size', 'text_mask_stretch', 'text_mask_gap' ),
				),
				'text_strips'  => array(
					'title'  => __( 'Strips', 'wp-swiper' ),
					'fields' => array( 'text_mask_strip_width', 'text_mask_join', 'text_mask_strips', 'text_mask_strip_min', 'text_mask_strip_max' ),
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
		// Text mask: its letters, on every device.
		if ( 'text' === $style ) {
			return array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), count( self::letters( $settings ) ) );
		}
		// SVG shape: its windows, on every device.
		if ( 'shape' === $style ) {
			return array_map(
				function ( $shape ) {
					return $shape ? count( $shape['shapes'] ) : 1;
				},
				self::shapes( $settings )
			);
		}
		// Grid shapes: its shapes (one layout for every device).
		if ( 'grid-shapes' === $style ) {
			return array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), max( 1, self::cells( $settings )['count'] ) );
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
			// Ring only (Ring + text goes all around; no ring in a grid).
			// Ring: its opening per device, none (0) with Opening off.
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
				// Any piece at random, or the middle one through all the photos.
				// (Ring + text has no middle photo.) SVG shape: its own What changes
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
		// Ring + text: the image and text in the middle (art.js places them).
		$center = in_array( $style, array( 'ring-text', 'grid-circle-text', 'grid-square-text' ), true ) && $settings['art_center'] ? self::center( $settings ) : '';
		// SVG shape: a window with an image and text instead of a photo (art.js
		// fill() moves it there), its window per device; not as a background.
		$content = 'shape' === $style && ! empty( $settings['art_content'] ) && ! $background && $shape ? WP_Swiper_Render::content_box( $settings, 'art_content' ) : '';
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
			self::center_style( $center, $settings ),
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
			<?php echo $opening . $center; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where they are built. ?>
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

	/**
	 * A gradient as stored (the photos' Gradient, admin.js draws its editor):
	 * "linear;left;#ff0080@0;rgba(0,96,255,0.5)@100". Its type (linear, or
	 * radial: circle / ellipse), where it starts (linear: left, right, top,
	 * bottom) or its middle (radial: "left|center|right top|center|bottom"),
	 * then 2 to GRADIENT_STOPS colors with their positions (0-100), in order.
	 *
	 * @param string $value Stored value.
	 * @return array|null [ 'type', 'where', 'stops' => [ [ color, position ] ] ], null if not one.
	 */
	public static function parse_gradient( $value ) {
		$parts = explode( ';', (string) $value );
		if ( count( $parts ) < 4 ) {
			return null;
		}
		$type  = array_shift( $parts );
		$where = array_shift( $parts );
		if ( 'linear' === $type ) {
			if ( ! in_array( $where, array( 'left', 'right', 'top', 'bottom' ), true ) ) {
				return null;
			}
		} elseif ( in_array( $type, array( 'circle', 'ellipse' ), true ) ) {
			$at = explode( ' ', $where );
			if ( 2 !== count( $at ) || ! in_array( $at[0], array( 'left', 'center', 'right' ), true ) || ! in_array( $at[1], array( 'top', 'center', 'bottom' ), true ) ) {
				return null;
			}
		} else {
			return null;
		}
		$stops = array();
		foreach ( $parts as $part ) {
			$point = explode( '@', $part );
			if ( 2 !== count( $point ) || ! is_numeric( $point[1] ) ) {
				continue;
			}
			$color = WP_Swiper_Settings::sanitize_slide_color( $point[0] );
			if ( '' !== $color ) {
				$stops[] = array( $color, round( max( 0, min( 100, (float) $point[1] ) ), 1 ) );
			}
		}
		$stops = array_slice( $stops, 0, self::GRADIENT_STOPS );
		if ( count( $stops ) < 2 ) {
			return null;
		}
		// In order of their positions (those at one place as they came).
		$order = array_keys( $stops );
		usort(
			$order,
			function ( $a, $b ) use ( $stops ) {
				return $stops[ $a ][1] === $stops[ $b ][1] ? $a - $b : ( $stops[ $a ][1] < $stops[ $b ][1] ? -1 : 1 );
			}
		);
		return array(
			'type'  => $type,
			'where' => $where,
			'stops' => array_map(
				function ( $i ) use ( $stops ) {
					return $stops[ $i ];
				},
				$order
			),
		);
	}

	/**
	 * A gradient (parse_gradient()) as stored.
	 *
	 * @param array $gradient The gradient.
	 * @return string
	 */
	public static function gradient_value( $gradient ) {
		$points = array();
		foreach ( $gradient['stops'] as $stop ) {
			$points[] = $stop[0] . '@' . ( 0.0 === fmod( $stop[1], 1 ) ? (int) $stop[1] : $stop[1] );
		}
		return $gradient['type'] . ';' . $gradient['where'] . ';' . implode( ';', $points );
	}

	/**
	 * A stored gradient as CSS (its start "left" as WoodMart's: towards the
	 * right); '' if it is not one.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function gradient_css( $value ) {
		$gradient = self::parse_gradient( $value );
		if ( ! $gradient ) {
			return '';
		}
		$points = array();
		foreach ( $gradient['stops'] as $stop ) {
			$points[] = $stop[0] . ' ' . $stop[1] . '%';
		}
		if ( 'linear' === $gradient['type'] ) {
			$towards = array(
				'left'   => 'right',
				'right'  => 'left',
				'top'    => 'bottom',
				'bottom' => 'top',
			);
			return 'linear-gradient(to ' . $towards[ $gradient['where'] ] . ', ' . implode( ', ', $points ) . ')';
		}
		return 'radial-gradient(' . $gradient['type'] . ' at ' . $gradient['where'] . ', ' . implode( ', ', $points ) . ')';
	}

	/**
	 * Text mask, for art.js text(): the letters (its windows, in order), where
	 * the spaces were (a wider gap before that letter), the font, the
	 * letters' height at most per device (% of the stage's), and the strips:
	 * how their lengths go, the shortest and longest (% of the height under
	 * the letters; Shortest at most Longest), and a number for "Different
	 * lengths" that stays the same for the word (the same strips each time).
	 *
	 * @param array $settings Slider settings.
	 * @return array
	 */
	public static function text_config( $settings ) {
		$word   = isset( $settings['text_mask_title'] ) ? (string) $settings['text_mask_title'] : '';
		$chars  = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
		$spaces = array();
		$index  = 0;
		foreach ( is_array( $chars ) ? $chars : array() as $char ) {
			if ( '' === trim( $char ) ) {
				// Before the next letter (one or more spaces: one wider gap).
				$spaces[ $index ] = 1;
			} else {
				$index++;
			}
		}
		$family = 'name' === $settings['text_mask_font'] ? WP_Swiper_Fonts::sanitize( $settings['text_mask_font_name'] ) : '';
		$min    = max( 0, min( 100, (int) $settings['text_mask_strip_min'] ) );
		$max = max( 0, min( 100, (int) $settings['text_mask_strip_max'] ) );
		return array(
			'letters' => self::letters( $settings ),
			'spaces'  => array_values( array_map( 'intval', array_keys( $spaces ) ) ),
			// "By name" with a name (none: the theme's headings).
			'font'    => in_array( $settings['text_mask_font'], array( 'heading', 'serif', 'sans' ), true ) || ( 'name' === $settings['text_mask_font'] && '' !== $family ) ? $settings['text_mask_font'] : 'heading',
			'family'  => $family,
			'weight'  => in_array( (string) $settings['text_mask_weight'], array( '400', '700', '900' ), true ) ? (int) $settings['text_mask_weight'] : 700,
			'size'    => self::per_device( $settings, 'text_mask_size', 10, 70 ),
			// Per device: Letter size Auto (as wide as the word can be), else its %.
			'auto'    => array_map(
				function ( $unit ) {
					return '%' !== $unit;
				},
				WP_Swiper_Settings::responsive( $settings, 'text_mask_size_unit' )
			),
			'stretch' => ! empty( $settings['text_mask_stretch'] ),
			'direction' => in_array( $settings['text_mask_direction'], array( 'down', 'right', 'left' ), true ) ? $settings['text_mask_direction'] : 'down',
			'width'   => 'letter' === $settings['text_mask_strip_width'] ? 'letter' : 'same',
			// Strips into letters: a share of each letter's depth (0-0.3).
			'join'    => max( 0, min( 30, (int) $settings['text_mask_join'] ) ) / 100,
			// Rounded corners: the strips' radius per device (off: 0), and which end.
			'radius'  => empty( $settings['text_mask_rounded'] ) ? array_fill_keys( array( 'desktop', 'tablet', 'mobile' ), 0 ) : self::per_device( $settings, 'text_mask_radius', 0, 200 ),
			'corners' => 'both' === $settings['text_mask_corners'] ? 'both' : 'end',
			'strips'  => in_array( $settings['text_mask_strips'], array( 'random', 'equal', 'middle', 'sides' ), true ) ? $settings['text_mask_strips'] : 'random',
			'min'     => min( $min, $max ),
			'max'     => max( $min, $max ),
			'seed'    => (int) sprintf( '%u', crc32( $word ) ) % 100000,
		);
	}

	/**
	 * A ring or SVG shape as a background (art.css): per device its width in
	 * the block (container units of the block: the largest that fits, or the
	 * smallest that fills it, its proportions kept), its proportions, and
	 * where it sits (a share of the room left, or cut off, each way).
	 *
	 * @param array $settings Slider settings.
	 * @param array $ratios   device => width / height (none: 1, a ring).
	 * @return string[] CSS variables.
	 */
	public static function background_fit( $settings, $ratios ) {
		$sizes     = WP_Swiper_Settings::responsive( $settings, 'art_bg_size' );
		$positions = WP_Swiper_Settings::responsive( $settings, 'art_bg_position' );
		$share     = array(
			'left'   => 0,
			'top'    => 0,
			'center' => 0.5,
			'right'  => 1,
			'bottom' => 1,
		);
		$style     = array();
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			$ratio    = isset( $ratios[ $device ] ) && (float) $ratios[ $device ] > 0 ? round( (float) $ratios[ $device ], 4 ) : 1;
			$fit      = 'cover' === $sizes[ $device ] ? 'max' : 'min';
			$place    = explode( ' ', (string) $positions[ $device ] ) + array( 'center', 'center' );
			$x        = isset( $share[ $place[0] ] ) ? $share[ $place[0] ] : 0.5;
			$y        = isset( $share[ $place[1] ] ) ? $share[ $place[1] ] : 0.5;
			$suffix   = WP_Swiper_Render::SUFFIX[ $device ];
			$style[]  = '--wp-swiper-art-bg-w' . $suffix . ':' . $fit . '(100cqw, 100cqh * ' . $ratio . ')';
			$style[]  = '--wp-swiper-art-bg-ratio' . $suffix . ':' . $ratio;
			$style[]  = '--wp-swiper-art-bg-x' . $suffix . ':' . $x;
			$style[]  = '--wp-swiper-art-bg-y' . $suffix . ':' . $y;
		}
		return $style;
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
