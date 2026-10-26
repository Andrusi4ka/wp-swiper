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
			// A circle in the middle, the others around it in a ring with an
			// opening at the bottom for a label (a logo and two lines of text).
			'ring' => __( 'Ring', 'wp-swiper' ),
		);
	}

	/**
	 * Art's settings, merged into WP_Swiper_Settings::fields(): all on the
	 * Layout page, for this effect only, named art_*. "styles": the Styles
	 * that use a field (admin.js shows it for those).
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
				'label'      => __( 'Size (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 560,
				'min'        => 160,
				'max'        => 2000,
				'step'       => 10,
				'help'       => __( 'The widest it gets; a narrower column makes it smaller.', 'wp-swiper' ),
			),
			'art_ring_count'     => array(
				'section' => 'layout',
				'effects' => $art,
				'styles'  => array( 'ring' ),
				'label'   => __( 'Photos around', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 7,
				'min'     => 3,
				'max'     => 16,
				'step'    => 1,
				'help'    => __( 'The pieces of the ring; one more photo is in the middle.', 'wp-swiper' ),
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
				'section' => 'layout',
				'effects' => $art,
				'styles'  => array( 'ring' ),
				'label'   => __( 'Opening (degrees)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 0,
				'min'     => 0,
				'max'     => 180,
				'step'    => 1,
				'help'    => __( '0: the photos evenly all around the circle. More: a gap at the bottom where the label (Logo, Title, Subtitle) sits, from 20 degrees on.', 'wp-swiper' ),
			),
			'art_gap'            => array(
				'section' => 'layout',
				'effects' => $art,
				'label'   => __( 'Gap (px)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 8,
				'min'     => 0,
				'max'     => 40,
				'step'    => 1,
			),
			'art_radius'         => array(
				'section' => 'layout',
				'effects' => $art,
				'label'   => __( 'Rounded corners (px)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 14,
				'min'     => 0,
				'max'     => 60,
				'step'    => 1,
			),
			'art_label_image'    => array(
				'section' => 'layout',
				'effects' => $art,
				'styles'  => array( 'ring' ),
				'label'   => __( 'Logo', 'wp-swiper' ),
				'type'    => 'image',
				'default' => 0,
				'help'    => __( 'An image over the title, in the opening; PNG or SVG with a transparent background suits best.', 'wp-swiper' ),
			),
			'art_label_title'    => array(
				'section' => 'layout',
				'effects' => $art,
				'styles'  => array( 'ring' ),
				'label'   => __( 'Title', 'wp-swiper' ),
				'type'    => 'line',
				'default' => '',
			),
			'art_label_subtitle' => array(
				'section' => 'layout',
				'effects' => $art,
				'styles'  => array( 'ring' ),
				'label'   => __( 'Subtitle', 'wp-swiper' ),
				'type'    => 'line',
				'default' => '',
			),
			'art_label_color'    => array(
				'section' => 'layout',
				'effects' => $art,
				'styles'  => array( 'ring' ),
				'label'   => __( 'Label color', 'wp-swiper' ),
				'type'    => 'color',
				'default' => '#ffffff',
			),
		);
	}

	/**
	 * Art's blocks on the Layout page (before the other effects' blocks).
	 *
	 * @return array
	 */
	public static function blocks() {
		return array(
			'art'       => array(
				'title'  => __( 'Art', 'wp-swiper' ),
				'fields' => array( 'art_style', 'art_size', 'art_ring_count', 'art_ring_opening', 'art_gap', 'art_radius' ),
			),
			'art_label' => array(
				'title'  => __( 'Label', 'wp-swiper' ),
				'fields' => array( 'art_label_image', 'art_label_title', 'art_label_subtitle', 'art_label_color' ),
			),
		);
	}

	/**
	 * How many photos a Style shows.
	 *
	 * @param array $settings Slider settings.
	 * @return int
	 */
	public static function count( $settings ) {
		return 1 + max( 3, min( 16, (int) $settings['art_ring_count'] ) );
	}

	/**
	 * The Art slider: an outer block (with the slider's own class and id), a
	 * square stage with a piece per photo (art.js gives each its shape and
	 * place) and, for Ring, the label. Images only: video slides are left out.
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

		$style = array_key_exists( $settings['art_style'], self::styles() ) ? $settings['art_style'] : 'ring';
		$count = self::count( $settings );
		$sizes = array_map( 'intval', WP_Swiper_Settings::responsive( $settings, 'art_size' ) );

		$config = array(
			'style'   => $style,
			'gap'     => max( 0, min( 40, (int) $settings['art_gap'] ) ),
			'radius'  => max( 0, min( 60, (int) $settings['art_radius'] ) ),
			'count'   => $count - 1,
			// Ring: the middle circle's size per device (% of the whole).
			'center'  => array_map(
				function ( $center ) {
					return round( max( 20, min( 75, (float) $center ) ), 1 );
				},
				WP_Swiper_Settings::responsive( $settings, 'art_ring_center' )
			),
			'opening' => max( 0, min( 180, (int) $settings['art_ring_opening'] ) ),
			// The photos' order per device (slide indexes; false: the slides'
			// order), and each slide's photo ID for the preview to tell one by.
			'order'   => self::orders( $settings, $slides ),
			'ids'     => array_map( 'intval', wp_list_pluck( $slides, 'id' ) ),
		);

		$classes = array_filter(
			array(
				'wp-swiper-art',
				'wp-swiper-art--' . $style,
				'wp-swiper-art--' . (int) $post_id,
				WP_Swiper_Settings::sanitize_classes( $settings['css_class'] . ' ' . $extra_class ),
			)
		);
		$css     = array(
			'--wp-swiper-art-size:' . $sizes['desktop'] . 'px',
			'--wp-swiper-art-size-tablet:' . $sizes['tablet'] . 'px',
			'--wp-swiper-art-size-mobile:' . $sizes['mobile'] . 'px',
			'--wp-swiper-art-label-color:' . ( sanitize_hex_color( $settings['art_label_color'] ) ? $settings['art_label_color'] : '#ffffff' ),
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo '' !== $settings['css_id'] ? ' id="' . esc_attr( $settings['css_id'] ) . '"' : ''; ?> style="<?php echo esc_attr( implode( ';', $css ) ); ?>" data-wp-swiper-art="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<div class="wp-swiper-art__stage">
				<?php
				// The pieces, empty: art.js puts the photos in them in the device's order.
				for ( $i = 0; $i < $count; $i++ ) {
					printf( '<div class="wp-swiper-art__piece" data-piece="%d"></div>', (int) $i );
				}
				// The label only where the ring leaves it room (art.js hides it below 20 degrees too).
				if ( 'ring' === $style && $config['opening'] >= 20 ) {
					self::label( $settings );
				}
				?>
			</div>
			<template class="wp-swiper-art__pool">
				<?php
				foreach ( $slides as $index => $slide ) {
					self::photo( $slide, $index );
				}
				?>
			</template>
		</div>
		<?php
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
	 * One photo in the pool (art.js copies it into its piece), linked when
	 * its slide has a link.
	 *
	 * @param array $slide Slide.
	 * @param int   $index Its slide index (0: the first).
	 */
	private static function photo( $slide, $index ) {
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
		printf(
			'<div class="wp-swiper-art__photo" data-real="%d">%s</div>',
			(int) $index,
			$image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup, link escaped above.
		);
	}

	/**
	 * Ring: the label in the opening (logo, title, subtitle; each if set).
	 *
	 * @param array $settings Slider settings.
	 */
	private static function label( $settings ) {
		$logo     = (int) $settings['art_label_image'];
		$title    = trim( (string) $settings['art_label_title'] );
		$subtitle = trim( (string) $settings['art_label_subtitle'] );
		if ( ! WP_Swiper_Settings::is_image( $logo ) && '' === $title && '' === $subtitle ) {
			return;
		}
		echo '<div class="wp-swiper-art__label">';
		if ( WP_Swiper_Settings::is_image( $logo ) ) {
			echo wp_get_attachment_image( $logo, 'medium', false, array( 'class' => 'wp-swiper-art__logo', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		}
		if ( '' !== $title ) {
			echo '<span class="wp-swiper-art__title">' . esc_html( $title ) . '</span>';
		}
		if ( '' !== $subtitle ) {
			echo '<span class="wp-swiper-art__subtitle">' . esc_html( $subtitle ) . '</span>';
		}
		echo '</div>';
	}
}
