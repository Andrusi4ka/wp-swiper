<?php
/**
 * WPBakery Page Builder element that wraps the [wp_swiper] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_WPBakery {

	public static function init() {
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
	}

	public static function map() {
		if ( function_exists( 'vc_lean_map' ) ) {
			// Lean map: settings (and the sliders query) load only when the editor needs them.
			vc_lean_map( WP_Swiper_Render::SHORTCODE, array( __CLASS__, 'settings' ) );
		}
	}

	/**
	 * @return array Element definition.
	 */
	public static function settings() {
		return array(
			'name'        => __( 'WP Swiper', 'wp-swiper' ),
			'base'        => WP_Swiper_Render::SHORTCODE,
			'icon'        => WP_SWIPER_URL . 'assets/images/wpbakery-icon.svg',
			'category'    => __( 'Content', 'wp-swiper' ),
			'description' => __( 'Image slider created in WP Swiper', 'wp-swiper' ),
			'params'      => array(
				array(
					'type'        => 'dropdown',
					'heading'     => __( 'Slider', 'wp-swiper' ),
					'param_name'  => 'id',
					'value'       => self::slider_options(),
					'admin_label' => true,
					'description' => sprintf(
						/* translators: %s: link to the sliders screen */
						__( 'Create and edit sliders under %s.', 'wp-swiper' ),
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . WP_Swiper_Settings::POST_TYPE ) ) . '" target="_blank">WP Swiper</a>'
					),
				),
				array(
					'type'       => 'textfield',
					'heading'    => __( 'Extra CSS class', 'wp-swiper' ),
					'param_name' => 'class',
				),
			),
		);
	}

	/**
	 * @return array WPBakery dropdown values: label => value.
	 */
	private static function slider_options() {
		$options = array( __( '— Select slider —', 'wp-swiper' ) => '' );

		$sliders = get_posts(
			array(
				'post_type'      => WP_Swiper_Settings::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $sliders as $slider ) {
			$title = $slider->post_title ? $slider->post_title : __( '(no title)', 'wp-swiper' );
			$options[ sprintf( '%s (#%d)', $title, $slider->ID ) ] = (string) $slider->ID;
		}

		return $options;
	}
}
