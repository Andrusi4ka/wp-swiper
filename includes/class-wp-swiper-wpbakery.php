<?php
/**
 * WPBakery Page Builder element that wraps the [wp_swiper] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_WPBakery {

	// The "Edit slider" and "Add slider" buttons under the Slider list (a param type of its own).
	const EDIT_PARAM = 'wp_swiper_edit';

	// The Slider list again (wpbakery.js, once back from adding one): its AJAX action.
	const LIST_ACTION = 'wp_swiper_vc_sliders';

	public static function init() {
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
		add_action( 'wp_ajax_' . self::LIST_ACTION, array( __CLASS__, 'ajax_sliders' ) );
	}

	public static function map() {
		if ( function_exists( 'vc_add_shortcode_param' ) ) {
			vc_add_shortcode_param( self::EDIT_PARAM, array( __CLASS__, 'edit_param' ), WP_SWIPER_URL . 'assets/js/wpbakery.js?ver=' . rawurlencode( wp_swiper_asset_version( 'assets/js/wpbakery.js' ) ) );
		}
		if ( function_exists( 'vc_lean_map' ) ) {
			// Lean map: settings (and the sliders query) load only when the editor needs them.
			vc_lean_map( WP_Swiper_Render::SHORTCODE, array( __CLASS__, 'settings' ) );
		}
	}

	/**
	 * The "Add element" tab: with the Woodmart theme its own "Theme elements"
	 * (next to its galleries and carousels), named as the theme names it, its
	 * markup and translation included, or it would be a tab of its own;
	 * otherwise WPBakery's "Content".
	 *
	 * @return string
	 */
	private static function category() {
		if ( function_exists( 'woodmart_get_tab_title_category_for_wpb' ) ) {
			return woodmart_get_tab_title_category_for_wpb( esc_html__( 'Theme elements', 'woodmart' ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- the theme's own tab name.
		}
		return __( 'Content', 'wp-swiper' );
	}

	/**
	 * @return array Element definition.
	 */
	public static function settings() {
		return array(
			'name'        => __( 'WP Swiper', 'wp-swiper' ),
			'base'        => WP_Swiper_Render::SHORTCODE,
			'icon'        => WP_SWIPER_URL . 'assets/images/wpbakery-icon.svg',
			'category'    => self::category(),
			// Last in the "Add element" list: WPBakery sorts by weight (default 0),
			// then by mapping order, where a plugin's element comes first.
			'weight'      => -100,
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
				// Opens the chosen slider's edit screen in a new tab (wpbakery.js keeps
				// it pointing at the slider chosen above). Saves nothing in the shortcode.
				array(
					'type'       => self::EDIT_PARAM,
					'heading'    => '',
					'param_name' => self::EDIT_PARAM,
				),
			),
		);
	}

	/**
	 * The buttons under the Slider list: "Edit slider", a link to post.php for
	 * the slider chosen (wpbakery.js sets it and hides it while none is), and
	 * "Add slider", the new slider screen (for those who may add one); both
	 * open in a new tab, as the note under them says. Back from adding one,
	 * wpbakery.js asks for the list again (data-list: admin-ajax.php,
	 * LIST_ACTION, its nonce) and chooses the new slider. Its field is empty,
	 * so WPBakery adds nothing to the shortcode.
	 *
	 * @param array  $settings The param's settings.
	 * @param string $value    Its value (always empty).
	 * @return string
	 */
	public static function edit_param( $settings, $value ) {
		unset( $value );
		$name = isset( $settings['param_name'] ) ? $settings['param_name'] : self::EDIT_PARAM;
		$add  = '';
		if ( self::can_add() ) {
			$add = sprintf(
				' <a class="button wp-swiper-vc-edit__add" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
				esc_url( admin_url( 'post-new.php?post_type=' . WP_Swiper_Settings::POST_TYPE ) ),
				esc_html__( 'Add slider', 'wp-swiper' )
			);
		}
		$list = add_query_arg(
			array(
				'action'   => self::LIST_ACTION,
				'_wpnonce' => wp_create_nonce( self::LIST_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
		return sprintf(
			'<div class="wp-swiper-vc-edit" data-url="%1$s" data-list="%6$s"><a class="button wp-swiper-vc-edit__link" href="#" target="_blank" rel="noopener" hidden>%2$s</a>%7$s<p class="description wp-swiper-vc-edit__note" style="margin:6px 0 0">%5$s</p><input type="hidden" name="%3$s" class="wpb_vc_param_value %3$s %4$s" value=""></div>',
			esc_url( admin_url( 'post.php?action=edit&post=' ) ),
			esc_html__( 'Edit slider', 'wp-swiper' ),
			esc_attr( $name ),
			esc_attr( self::EDIT_PARAM ),
			esc_html__( 'Opens in a new tab. Back here, a slider published there is in the list, chosen.', 'wp-swiper' ),
			esc_url( $list ),
			$add // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
		);
	}

	// Whether the user may add sliders (the post type's own capability).
	private static function can_add() {
		$type = get_post_type_object( WP_Swiper_Settings::POST_TYPE );
		return $type && current_user_can( $type->cap->create_posts );
	}

	/**
	 * The sliders the list offers (published), by title.
	 *
	 * @return array [ [ 'id' => int, 'label' => string ] ]
	 */
	public static function sliders() {
		$sliders = get_posts(
			array(
				'post_type'      => WP_Swiper_Settings::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$list = array();
		foreach ( $sliders as $slider ) {
			$title  = $slider->post_title ? $slider->post_title : __( '(no title)', 'wp-swiper' );
			$list[] = array(
				'id'    => (int) $slider->ID,
				'label' => sprintf( '%s (#%d)', $title, $slider->ID ),
			);
		}
		return $list;
	}

	/**
	 * @return array WPBakery dropdown values: label => value.
	 */
	private static function slider_options() {
		$options = array( __( '— Select slider —', 'wp-swiper' ) => '' );
		foreach ( self::sliders() as $slider ) {
			$options[ $slider['label'] ] = (string) $slider['id'];
		}
		return $options;
	}

	/**
	 * The Slider list again, for wpbakery.js: the sliders as sliders() gives
	 * them, to those who may edit posts (with the nonce edit_param() gave).
	 */
	public static function ajax_sliders() {
		check_ajax_referer( self::LIST_ACTION );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		wp_send_json_success( self::sliders() );
	}
}
