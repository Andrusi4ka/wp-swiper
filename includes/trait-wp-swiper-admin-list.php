<?php
/**
 * The sliders list (edit.php): its columns and the Clone row action. Used
 * by WP_Swiper_Admin, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Admin_List {

	/**
	 * Whether the current user may clone this slider: edit it and add sliders.
	 * Not one in the Trash.
	 *
	 * @param WP_Post|null $post Slider.
	 * @return bool
	 */
	public static function can_clone( $post ) {
		if ( ! $post || WP_Swiper_Settings::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return false;
		}
		$type = get_post_type_object( WP_Swiper_Settings::POST_TYPE );
		return current_user_can( 'edit_post', $post->ID ) && current_user_can( $type->cap->create_posts );
	}

	/**
	 * A copy of a slider: its slides and settings, the title "<title> (copy)".
	 * A draft, so it shows nowhere until it is published.
	 *
	 * @param int $post_id Slider ID.
	 * @return int The copy's ID, 0 when not allowed or it failed.
	 */
	public static function clone_slider( $post_id ) {
		$post = get_post( $post_id );
		if ( ! self::can_clone( $post ) ) {
			return 0;
		}

		/* translators: %s: title of the slider cloned. */
		$title = sprintf( __( '%s (copy)', 'wp-swiper' ), $post->post_title );
		$id    = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => WP_Swiper_Settings::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => trim( $title ),
					'post_author' => get_current_user_id(),
					'menu_order'  => $post->menu_order,
				)
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		// As stored (update_post_meta() unslashes: captions keep their backslashes).
		foreach ( array( WP_Swiper_Settings::META_SLIDES, WP_Swiper_Settings::META_SETTINGS ) as $key ) {
			if ( metadata_exists( 'post', $post->ID, $key ) ) {
				update_post_meta( $id, $key, wp_slash( get_post_meta( $post->ID, $key, true ) ) );
			}
		}
		return $id;
	}

	/**
	 * "Clone" under a slider's title in the sliders list, after Quick Edit.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( WP_Swiper_Settings::POST_TYPE !== $post->post_type || ! self::can_clone( $post ) ) {
			return $actions;
		}
		$url  = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::CLONE_ACTION,
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::CLONE_ACTION . '_' . $post->ID
		);
		$link = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			/* translators: %s: slider title. */
			esc_attr( sprintf( __( 'Clone &#8220;%s&#8221;', 'wp-swiper' ), _draft_or_post_title( $post ) ) ),
			esc_html__( 'Clone', 'wp-swiper' )
		);

		$out = array();
		foreach ( $actions as $key => $action ) {
			$out[ $key ] = $action;
			if ( 'inline hide-if-no-js' === $key ) {
				$out['wp_swiper_clone'] = $link;
			}
		}
		// No Quick Edit link: after Edit, or first.
		if ( ! isset( $out['wp_swiper_clone'] ) ) {
			$out = isset( $out['edit'] )
				? array_merge( array( 'edit' => $out['edit'] ), array( 'wp_swiper_clone' => $link ), $out )
				: array( 'wp_swiper_clone' => $link ) + $out;
		}
		return $out;
	}

	/**
	 * admin-post.php: clone the slider, then back to the sliders list.
	 */
	public static function handle_clone() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( self::CLONE_ACTION . '_' . $post_id );

		$id = self::clone_slider( $post_id );
		if ( ! $id ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to clone this slider.', 'wp-swiper' ), '', array( 'response' => 403, 'back_link' => true ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'        => WP_Swiper_Settings::POST_TYPE,
					'wp_swiper_cloned' => $id,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * The sliders list after a clone: a link to the copy.
	 */
	public static function clone_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . WP_Swiper_Settings::POST_TYPE !== $screen->id || empty( $_GET['wp_swiper_cloned'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			return;
		}
		$post = get_post( absint( $_GET['wp_swiper_cloned'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! $post || WP_Swiper_Settings::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Slider cloned as a draft:', 'wp-swiper' ),
			esc_url( get_edit_post_link( $post->ID ) ),
			esc_html( _draft_or_post_title( $post ) )
		);
	}

	public static function columns( $columns ) {
		$date = $columns['date'];
		unset( $columns['date'] );

		$columns['wp_swiper_slides']    = __( 'Slides', 'wp-swiper' );
		$columns['wp_swiper_effect']    = __( 'Effect', 'wp-swiper' );
		$columns['wp_swiper_shortcode'] = __( 'Shortcode', 'wp-swiper' );
		$columns['date']                = $date;

		return $columns;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'wp_swiper_slides':
				// As the slider shows them: images and videos deleted from the library are skipped.
				echo (int) count(
					array_filter( WP_Swiper_Settings::get_slides( $post_id ), array( 'WP_Swiper_Settings', 'is_slide' ) )
				);
				break;

			case 'wp_swiper_effect':
				$settings = WP_Swiper_Settings::get( $post_id );
				$choices  = WP_Swiper_Settings::fields()['effect']['choices'];
				echo esc_html( $choices[ $settings['effect'] ] );
				break;

			case 'wp_swiper_shortcode':
				self::render_shortcode_input( $post_id );
				break;
		}
	}
}
