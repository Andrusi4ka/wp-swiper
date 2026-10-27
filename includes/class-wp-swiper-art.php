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
			'ring' => __( 'Ring', 'wp-swiper' ),
		);
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
		$art = array( self::EFFECT );

		return array(
			'art_style'          => array(
				'section' => 'layout',
				'effects' => $art,
				'label'   => __( 'Style', 'wp-swiper' ),
				'type'    => 'select',
				'default' => 'ring',
				'choices' => self::styles(),
				'help'    => __( 'The shape the photos make. The slides fill it in their order (they repeat when there are fewer).', 'wp-swiper' ),
			),
			// Which photo is in which piece, per device: the photos' IDs, the first
			// in the first piece ('' on desktop: the slides' order; on tablet and
			// mobile: the larger screen's). Set by dragging a photo onto another
			// piece in the preview (preview-art.js); no field to show.
			'art_order'          => array(
				'section'       => 'layout',
				'effects'       => $art,
				'label'         => __( 'Order', 'wp-swiper' ),
				'type'          => 'order',
				'responsive'    => true,
				'inline_in'     => 'art_style',
				'inline_hidden' => true,
				'default'       => '',
			),
			'art_size'           => array(
				'section'    => 'layout',
				'effects'    => $art,
				'label'      => __( 'Size', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'unit'       => 'art_size_unit',
				'default'    => 560,
				'min'        => 1,
				'max'        => 2000,
				'step'       => 1,
				'help'       => __( 'Its width and height (it is square), never wider than the column. px: the most it gets (160 at least). %: of the column\'s width. vh: of the screen\'s height. Auto: as large as the free space of the column allows (its width, and its height where the row gives it one).', 'wp-swiper' ),
			),
			// Size's unit, per device (tablet and mobile empty: the larger screen's).
			'art_size_unit'      => array(
				'section'    => 'layout',
				'effects'    => $art,
				'label'      => __( 'Size unit', 'wp-swiper' ),
				'type'       => 'select',
				'responsive' => true,
				'inline_in'  => 'art_size',
				'default'    => 'px',
				'choices'    => array(
					'px'   => 'px',
					'%'    => '%',
					'vh'   => 'vh',
					'auto' => __( 'Auto', 'wp-swiper' ),
				),
			),
			// Where the shape sits in its column, per device, when it is narrower.
			'art_align'          => array(
				'section'    => 'layout',
				'effects'    => $art,
				'label'      => __( 'Alignment', 'wp-swiper' ),
				'type'       => 'select',
				'responsive' => true,
				'default'    => 'center',
				'choices'    => array(
					'left'   => __( 'Left', 'wp-swiper' ),
					'center' => __( 'Center', 'wp-swiper' ),
					'right'  => __( 'Right', 'wp-swiper' ),
				),
				'help'       => __( 'Where it sits in its column when it is narrower than the column.', 'wp-swiper' ),
			),
			'art_ring_count'     => array(
				'section'    => 'layout',
				'effects'    => $art,
				'styles'     => array( 'ring' ),
				'label'      => __( 'Photos around', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 7,
				'min'        => 3,
				'max'        => 16,
				'step'       => 1,
				'help'       => __( 'The pieces of the ring; one more photo is in the middle.', 'wp-swiper' ),
			),
			// Ring: the middle circle's size in percent of the whole, per device
			// ('' on tablet and mobile: the larger screen's). Set by dragging its
			// edge in the preview (preview-art.js); no field to show.
			'art_ring_center'    => array(
				'section'       => 'layout',
				'effects'       => $art,
				'styles'        => array( 'ring' ),
				'label'         => __( 'Middle circle (%)', 'wp-swiper' ),
				'type'          => 'float',
				'responsive'    => true,
				'inline_in'     => 'art_style',
				'inline_hidden' => true,
				'default'       => 46,
				'min'           => 20,
				'max'           => 75,
				'step'          => 0.1,
			),
			'art_ring_opening'   => array(
				'section'    => 'layout',
				'effects'    => $art,
				'styles'     => array( 'ring' ),
				'label'      => __( 'Opening (degrees)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 0,
				'min'        => 0,
				'max'        => 180,
				'step'       => 1,
				'help'       => __( '0: the photos evenly all around the circle. More: a gap at the bottom of the ring.', 'wp-swiper' ),
			),
			'art_gap'            => array(
				'section'    => 'layout',
				'effects'    => $art,
				'label'      => __( 'Gap (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 8,
				'min'        => 0,
				'max'        => 40,
				'step'       => 1,
			),
			'art_radius'         => array(
				'section'    => 'layout',
				'effects'    => $art,
				'label'      => __( 'Rounded corners (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 14,
				'min'        => 0,
				'max'        => 60,
				'step'       => 1,
			),
			// A border along each photo's shape (art.js draws it), as a slider's
			// Image border: a width per device, one color.
			'art_border'         => array(
				'section' => 'layout',
				'effects' => $art,
				'label'   => __( 'Image border', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => false,
			),
			'art_border_width'   => array(
				'section'    => 'layout',
				'effects'    => $art,
				'group'      => 'art_border',
				'label'      => __( 'Width (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 2,
				'min'        => 0,
				'max'        => 50,
				'step'       => 1,
			),
			'art_border_color'   => array(
				'section' => 'layout',
				'effects' => $art,
				'group'   => 'art_border',
				'label'   => __( 'Color', 'wp-swiper' ),
				'type'    => 'color',
				'default' => '#ffffff',
			),
			// Photo swapping, as the Collage's: with more photos than pieces, the
			// ones not shown fade in now and then (art.js). On the Controls page,
			// with the other settings of how it moves.
			'art_swap'           => array(
				'section' => 'advanced',
				'panel'   => 'controls',
				'effects' => $art,
				'label'   => __( 'Swap in the other photos', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'With more photos than pieces: every so often a piece, at random, fades to a photo not shown yet. The shape does not change.', 'wp-swiper' ),
			),
			'art_swap_delay'     => array(
				'section' => 'advanced',
				'panel'   => 'controls',
				'effects' => $art,
				'label'   => __( 'A new photo every (ms)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 3000,
				'min'     => 1000,
				'max'     => 30000,
				'step'    => 500,
				'range'   => array( 1000, 10000 ),
			),
			'art_swap_speed'     => array(
				'section' => 'advanced',
				'panel'   => 'controls',
				'effects' => $art,
				'label'   => __( 'Fade (ms)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 800,
				'min'     => 100,
				'max'     => 3000,
				'step'    => 50,
				'range'   => array( 100, 2000 ),
			),
			'art_pause'          => array(
				'section' => 'advanced',
				'panel'   => 'controls',
				'effects' => $art,
				'label'   => __( 'Pause on hover', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
			),
		);
	}

	/**
	 * Art's blocks, per page (before the other effects' blocks there): its
	 * shape on the Layout page, Photo swapping on the Controls page.
	 *
	 * @return array page => [ block => [ 'title' => string, 'fields' => string[] ] ]
	 */
	public static function blocks() {
		return array(
			'layout'   => array(
				'art' => array(
					'title'  => __( 'Art', 'wp-swiper' ),
					'fields' => array( 'art_style', 'art_size', 'art_align', 'art_ring_count', 'art_ring_opening', 'art_gap', 'art_radius', 'art_border', 'art_border_width', 'art_border_color' ),
				),
			),
			// As the Collage's own (the same block and fields).
			'controls' => array(
				'art_swap' => array(
					'title'  => __( 'Photo swapping', 'wp-swiper' ),
					'fields' => array( 'art_swap', 'art_swap_delay', 'art_swap_speed', 'art_pause' ),
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
	 * one in the middle).
	 *
	 * @param array $settings Slider settings.
	 * @return array device => int
	 */
	public static function counts( $settings ) {
		return array_map(
			function ( $around ) {
				return 1 + $around;
			},
			self::per_device( $settings, 'art_ring_count', 3, 16 )
		);
	}

	/**
	 * Size's unit on each device (tablet and mobile empty: the larger screen's).
	 *
	 * @param array $settings Slider settings.
	 * @return array device => 'px' | '%' | 'vh' | 'auto'
	 */
	public static function size_units( $settings ) {
		return array_map(
			function ( $unit ) {
				return in_array( $unit, array( 'px', '%', 'vh', 'auto' ), true ) ? $unit : 'px';
			},
			WP_Swiper_Settings::responsive( $settings, 'art_size_unit' )
		);
	}

	/**
	 * The stage's width on each device as CSS (art.css keeps it within the
	 * column): px from 160, % and vh up to 100; Auto: the column's width, which
	 * art.js makes smaller to fit the free height.
	 *
	 * @param array $settings Slider settings.
	 * @return array device => string
	 */
	public static function sizes( $settings ) {
		$values = WP_Swiper_Settings::responsive( $settings, 'art_size' );
		$sizes  = array();
		foreach ( self::size_units( $settings ) as $device => $unit ) {
			$value = max( 1, (int) $values[ $device ] );
			if ( 'auto' === $unit ) {
				$sizes[ $device ] = '100%';
			} elseif ( 'px' === $unit ) {
				$sizes[ $device ] = max( 160, min( 2000, $value ) ) . 'px';
			} else {
				$sizes[ $device ] = min( 100, $value ) . $unit;
			}
		}
		return $sizes;
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

		wp_enqueue_style( 'wp-swiper-art' );
		wp_enqueue_script( 'wp-swiper-art' );

		// A click opens either links or the lightbox, never a mix (as in a
		// slider): any link turns the lightbox off.
		if ( array_filter( wp_list_pluck( $slides, 'link' ) ) ) {
			$settings['lightbox'] = false;
		}

		$style   = array_key_exists( $settings['art_style'], self::styles() ) ? $settings['art_style'] : 'ring';
		$count   = self::count( $settings );
		$sizes   = self::sizes( $settings );
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
			'radius'  => self::per_device( $settings, 'art_radius', 0, 60 ),
			'count'   => self::per_device( $settings, 'art_ring_count', 3, 16 ),
			// Ring: the middle circle's size per device (% of the whole).
			'center'  => array_map(
				function ( $center ) {
					return round( max( 20, min( 75, (float) $center ) ), 1 );
				},
				WP_Swiper_Settings::responsive( $settings, 'art_ring_center' )
			),
			'opening' => self::per_device( $settings, 'art_ring_opening', 0, 180 ),
			// The photos' order per device (slide indexes; false: the slides'
			// order), and each slide's photo ID for the preview to tell one by.
			'order'   => self::orders( $settings, $slides ),
			'ids'     => array_map( 'intval', wp_list_pluck( $slides, 'id' ) ),
			// Size "Auto" per device: art.js fits it to the column's free height.
			'auto'    => array_map(
				function ( $unit ) {
					return 'auto' === $unit;
				},
				self::size_units( $settings )
			),
			// Photo swapping (art.js): how often, and whether the pointer over it pauses it.
			'swap'    => $settings['art_swap'] ? array(
				'delay' => max( 1000, min( 30000, (int) $settings['art_swap_delay'] ) ),
				'pause' => (bool) $settings['art_pause'],
			) : false,
		);

		$shadow  = WP_Swiper_Settings::shadow( $settings );
		$classes = array_filter(
			array_merge(
				array(
					'wp-swiper-art',
					'wp-swiper-art--' . $style,
					'wp-swiper-art--' . (int) $post_id,
				),
				self::caption_classes( $settings ),
				array( $settings['lightbox'] ? 'wp-swiper-art--lightbox' : '' ),
				// Image border: art.js draws it along each shape.
				array( $settings['art_border'] ? 'wp-swiper-art--border' : '' ),
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
				// Alignment per device (art.css): the stage's side margins.
				'--wp-swiper-art-margin:' . $margins['desktop'],
				'--wp-swiper-art-margin-tablet:' . $margins['tablet'],
				'--wp-swiper-art-margin-mobile:' . $margins['mobile'],
				// Photo swapping: how long a photo fades in.
				'--wp-swiper-art-speed:' . max( 100, min( 3000, (int) $settings['art_swap_speed'] ) ) . 'ms',
			),
			self::caption_style( $settings ),
			self::border_style( $settings ),
			$shadow ? array(
				'--wp-swiper-art-shadow:drop-shadow(' . $shadow['x'] . 'px ' . $shadow['y'] . 'px ' . $shadow['blur'] . 'px ' . $shadow['color'] . ')',
				'--wp-swiper-shadow-room:' . implode( 'px ', $shadow['room'] ) . 'px',
			) : array()
		);

		// The slides' descriptions under it, as under a slider (the same block,
		// look and Text transition; frontend.css): art.js shows the one of the
		// photo under the pointer, else the middle one's. Direction does not
		// apply to Art (an older one may be saved).
		$settings['direction'] = 'horizontal';
		$descriptions          = WP_Swiper_Render::descriptions( $post_id, $slides, $settings );
		if ( '' !== $descriptions ) {
			wp_enqueue_style( 'wp-swiper' );
		}

		// The lightbox: a slider's own (frontend.js openLightbox(), with its
		// Swiper and stylesheet), opened by art.js on a click on a photo. Its
		// image corners: its own radius, else Art's Rounded corners; the
		// descriptions over or under the image, as set.
		$config['lightbox'] = false;
		if ( $settings['lightbox'] ) {
			wp_enqueue_style( 'wp-swiper' );
			wp_enqueue_script( 'wp-swiper' );
			$texts              = '' !== $descriptions && $settings['description_lightbox'];
			$config['lightbox'] = array(
				'radius'       => WP_Swiper_Render::lightbox_radius( $settings, 'lightbox_radius', 'art_radius' ),
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
		// Each photo's caption sets its own size (photo()); a slider saved with
		// one of its own keeps it for the others until saved again.
		$style = array_merge( $style, WP_Swiper_Settings::font_size_vars( WP_Swiper_Settings::legacy_font_sizes( $settings, 'caption' ), 'wp-swiper-caption-size' ) );
		$style[] = '--wp-swiper-caption-color:' . $settings['caption_color'];
		if ( $settings['caption_bg'] ) {
			list( $r, $g, $b ) = sscanf( $settings['caption_bg_color'], '#%02x%02x%02x' );
			$alpha              = max( 0, min( 100, (int) $settings['caption_bg_opacity'] ) ) / 100;
			$style[]            = sprintf( '--wp-swiper-caption-bg-from:rgba(%d,%d,%d,%s)', $r, $g, $b, $alpha );
			$style[]            = sprintf( '--wp-swiper-caption-bg-to:rgba(%d,%d,%d,0)', $r, $g, $b );
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
		$image = wp_get_attachment_image(
			$slide['id'],
			'large',
			false,
			array(
				'class'    => 'wp-swiper-art__image',
				'loading'  => 0 === $index ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);
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
			$size    = WP_Swiper_Settings::font_size_vars( isset( $slide['caption_size'] ) ? $slide['caption_size'] : '', 'wp-swiper-caption-size' );
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
