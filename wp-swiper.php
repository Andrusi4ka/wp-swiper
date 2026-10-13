<?php
/**
 * Plugin Name:       WP Swiper
 * Plugin URI:        https://github.com/Andrusi4ka/wp-swiper
 * Description:       Image sliders powered by Swiper. Build a slider from the media library, pick an effect and insert it with a shortcode or the WPBakery element.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Andrii Boiko
 * Author URI:        https://github.com/Andrusi4ka
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-swiper
 * Domain Path:       /languages
 * Update URI:        https://github.com/Andrusi4ka/wp-swiper
 *
 * @package WP_Swiper
 */

// "Update URI": wordpress.org has another plugin in a "wp-swiper" folder;
// without it WordPress would offer that one as an update. Updates come from
// this plugin's GitHub releases instead (wp_swiper_updates()).

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_SWIPER_VERSION', '1.2.1' );
define( 'WP_SWIPER_REPOSITORY', 'https://github.com/Andrusi4ka/wp-swiper/' );
define( 'WP_SWIPER_SWIPER_VERSION', '11.2.10' );
define( 'WP_SWIPER_FILE', __FILE__ );
define( 'WP_SWIPER_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SWIPER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Version string for a plugin asset: the plugin version plus the file's
 * modification time, so browsers fetch it again whenever it changes.
 *
 * @param string $path Path relative to the plugin folder.
 * @return string
 */
function wp_swiper_asset_version( $path ) {
	$time = @filemtime( WP_SWIPER_DIR . $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file just keeps the plain version.
	return $time ? WP_SWIPER_VERSION . '.' . $time : WP_SWIPER_VERSION;
}

require_once WP_SWIPER_DIR . 'includes/class-wp-swiper-settings.php';
require_once WP_SWIPER_DIR . 'includes/class-wp-swiper-admin.php';
require_once WP_SWIPER_DIR . 'includes/class-wp-swiper-render.php';
require_once WP_SWIPER_DIR . 'includes/class-wp-swiper-wpbakery.php';
require_once WP_SWIPER_DIR . 'includes/class-wp-swiper-preview.php';

/**
 * Register the slider post type.
 */
function wp_swiper_register_post_type() {
	register_post_type(
		WP_Swiper_Settings::POST_TYPE,
		array(
			'labels'          => array(
				'name'               => __( 'Sliders', 'wp-swiper' ),
				'singular_name'      => __( 'Slider', 'wp-swiper' ),
				'menu_name'          => __( 'WP Swiper', 'wp-swiper' ),
				'add_new'            => __( 'Add slider', 'wp-swiper' ),
				'add_new_item'       => __( 'Add new slider', 'wp-swiper' ),
				'edit_item'          => __( 'Edit slider', 'wp-swiper' ),
				'new_item'           => __( 'New slider', 'wp-swiper' ),
				'all_items'          => __( 'All sliders', 'wp-swiper' ),
				'search_items'       => __( 'Search sliders', 'wp-swiper' ),
				'not_found'          => __( 'No sliders found.', 'wp-swiper' ),
				'not_found_in_trash' => __( 'No sliders found in Trash.', 'wp-swiper' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 25,
			// A data URI (not a file URL) so WordPress repaints it in the admin color scheme like the dashicons.
			'menu_icon'       => 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( WP_SWIPER_DIR . 'assets/images/menu-icon.svg' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'rewrite'         => false,
		)
	);
}
add_action( 'init', 'wp_swiper_register_post_type' );

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'wp-swiper', false, dirname( plugin_basename( WP_SWIPER_FILE ) ) . '/languages' );
	}
);

/**
 * Updates from GitHub: a new release (tag "vX.Y.Z" with the wp-swiper.zip
 * built by .github/workflows/release.yml) shows up under Plugins like any
 * other update.
 */
function wp_swiper_updates() {
	$library = WP_SWIPER_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	// Only where WordPress looks for updates: the admin and its cron.
	if ( ( ! is_admin() && ! wp_doing_cron() ) || ! file_exists( $library ) ) {
		return;
	}
	require_once $library;

	$checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( WP_SWIPER_REPOSITORY, WP_SWIPER_FILE, 'wp-swiper' );
	// The release's zip, which holds just the plugin (not the repository's dev files).
	$checker->getVcsApi()->enableReleaseAssets( '/wp-swiper\.zip($|[?&#])/i' );
}
add_action( 'plugins_loaded', 'wp_swiper_updates' );

WP_Swiper_Admin::init();
WP_Swiper_Render::init();
WP_Swiper_WPBakery::init();
WP_Swiper_Preview::init();
