<?php
/**
 * Runs when the plugin is deleted under Plugins (not on deactivation or
 * update): removes the sliders, their slides and settings, and the update
 * checker's stored state. Images stay in the media library, where other
 * content may use them.
 *
 * @package WP_Swiper
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// All in a function of its own: nothing left in the global scope.
( function () {
	/**
	 * Delete every slider (any status, trash included) of the current site.
	 * wp_delete_post() also removes the post meta (_wp_swiper_slides, _wp_swiper_settings).
	 */
	$uninstall_site = function () {
		global $wpdb;

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wp_swiper' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off cleanup.
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}

		// "What's new": the version last run and the one updated from.
		delete_option( 'wp_swiper_version' );
		delete_option( 'wp_swiper_updated_from' );

		// The slide text editors' own colors, from the Settings page there was until 2.9.
		delete_option( 'wp_swiper_text_colors' );

		// The stylesheets remembered for <head> per page (WP_Swiper_Render::remember_late_styles()).
		delete_option( 'wp_swiper_late_styles' );
	};

	if ( is_multisite() ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
			switch_to_blog( $site_id );
			$uninstall_site();
			restore_current_blog();
		}
	} else {
		$uninstall_site();
	}

	// "What's new": the version each user has seen (users are shared by a network's sites).
	delete_metadata( 'user', 0, 'wp_swiper_seen_version', '', true );

	// Last update check (Plugin Update Checker).
	delete_site_option( 'external_updates-wp-swiper' );
} )();
