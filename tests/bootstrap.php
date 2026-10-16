<?php
/**
 * PHPUnit bootstrap: the WordPress test suite (wp-phpunit, installed by
 * Composer) with this plugin loaded as a must-use plugin.
 */

$wp_swiper_root = dirname( __DIR__ );
$wp_swiper_deps = $wp_swiper_root . '/tests/vendor';

if ( ! file_exists( $wp_swiper_deps . '/autoload.php' ) ) {
	fwrite( STDERR, "Run `composer install` first (see tests/README.md).\n" );
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $wp_swiper_deps . '/yoast/phpunit-polyfills' );
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$wp_swiper_tests_dir = $wp_swiper_deps . '/wp-phpunit/wp-phpunit';
require_once $wp_swiper_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () use ( $wp_swiper_root ) {
		require $wp_swiper_root . '/wp-swiper.php';
	}
);

require $wp_swiper_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-wp-swiper-testcase.php';
