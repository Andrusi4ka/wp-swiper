<?php
/**
 * The admin browser tests' way in (tests/browser/admin.spec.js), as
 * fixture.php is the site's: from this machine only (127.x), not in the
 * plugin's zip (tests/ is export-ignore).
 *
 * ?case=<name>: logs in as the tests' own administrator (made the first
 * time, user "wp-swiper-tests", no password: this page is its only way
 * in), saves a new slider with the case's settings (cases.php) and the
 * tests' own photos (media.php), and opens its edit screen.
 * ?delete=<id>: deletes that slider, if the tests made it (that user's);
 * ?cleanup=1: all of them (left by a test that stopped half way).
 * ?login=1: the session only (global-setup.js).
 */

$wp_swiper_remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
if ( 'cli' !== PHP_SAPI && 0 !== strpos( $wp_swiper_remote, '127.' ) && '::1' !== $wp_swiper_remote ) {
	http_response_code( 403 );
	exit( 'Local only.' );
}

require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once __DIR__ . '/media.php';

const WP_SWIPER_TESTS_USER = 'wp-swiper-tests';

// The tests' administrator: made once, with a password nobody knows.
$wp_swiper_user = get_user_by( 'login', WP_SWIPER_TESTS_USER );
if ( ! $wp_swiper_user ) {
	$wp_swiper_user_id = wp_insert_user(
		array(
			'user_login' => WP_SWIPER_TESTS_USER,
			'user_pass'  => wp_generate_password( 32, true, true ),
			'user_email' => WP_SWIPER_TESTS_USER . '@example.invalid',
			'role'       => 'administrator',
		)
	);
	if ( is_wp_error( $wp_swiper_user_id ) ) {
		http_response_code( 500 );
		exit( esc_html( $wp_swiper_user_id->get_error_message() ) );
	}
	$wp_swiper_user = get_user_by( 'id', $wp_swiper_user_id );
}

// The sliders the tests made: one, or all, deleted (with their meta).
if ( isset( $_GET['delete'] ) || isset( $_GET['cleanup'] ) ) {
	$wp_swiper_made = get_posts(
		array(
			'post_type'   => WP_Swiper_Settings::POST_TYPE,
			'post_status' => 'any',
			'author'      => $wp_swiper_user->ID,
			'numberposts' => -1,
			'fields'      => 'ids',
			'include'     => isset( $_GET['delete'] ) ? array( absint( $_GET['delete'] ) ) : array(),
		)
	);
	foreach ( $wp_swiper_made as $wp_swiper_id ) {
		wp_delete_post( $wp_swiper_id, true );
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 'Deleted ' . count( $wp_swiper_made ) . "\n";
	exit;
}

$wp_swiper_cases = require __DIR__ . '/cases.php';
$wp_swiper_name  = isset( $_GET['case'] ) ? sanitize_key( wp_unslash( $_GET['case'] ) ) : 'slide';
if ( ! isset( $wp_swiper_cases[ $wp_swiper_name ] ) ) {
	http_response_code( 404 );
	exit( 'No such case.' );
}
$wp_swiper_case = $wp_swiper_cases[ $wp_swiper_name ] + array(
	'photos'   => 8,
	'captions' => false,
);

// One session for every test, made once and kept (its token in the user's
// meta): a new one each time would be written to the user's sessions by
// requests at once, each over the others', and logged them out.
$wp_swiper_sessions = WP_Session_Tokens::get_instance( $wp_swiper_user->ID );
$wp_swiper_token    = (string) get_user_meta( $wp_swiper_user->ID, 'wp_swiper_tests_token', true );
if ( '' === $wp_swiper_token || ! $wp_swiper_sessions->verify( $wp_swiper_token ) ) {
	$wp_swiper_token = $wp_swiper_sessions->create( time() + 30 * DAY_IN_SECONDS );
	update_user_meta( $wp_swiper_user->ID, 'wp_swiper_tests_token', $wp_swiper_token );
}
wp_set_current_user( $wp_swiper_user->ID );
wp_set_auth_cookie( $wp_swiper_user->ID, false, is_ssl(), $wp_swiper_token );
// ?login=1: only that (global-setup.js, before the tests run at once).
if ( isset( $_GET['login'] ) ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "Logged in\n";
	exit;
}
// What's new seen already (its window would be over the editor).
update_user_meta( $wp_swiper_user->ID, WP_Swiper_Whats_New::META_SEEN, WP_SWIPER_VERSION );

// The slides: the tests' own photos (media.php), as fixture.php's.
$wp_swiper_slides = array();
$wp_swiper_ids    = wp_swiper_test_media( $wp_swiper_case['photos'] );
foreach ( $wp_swiper_ids as $wp_swiper_index => $wp_swiper_id ) {
	$wp_swiper_slides[] = array(
		'id'                 => $wp_swiper_id,
		// A caption on each where the case asks for one (as fixture.php).
		'caption'            => $wp_swiper_case['captions'] ? 'Caption ' . ( $wp_swiper_index + 1 ) : '',
		'description'        => '',
		'caption_size'       => '',
		'description_size'   => '',
		'caption_weight'     => '',
		'description_weight' => '',
		'caption_color'      => '',
		'description_color'  => '',
		'link'               => '',
		'new_tab'            => false,
		'poster'             => 0,
		'position'           => '',
	);
}

$wp_swiper_post = wp_insert_post(
	array(
		'post_type'   => WP_Swiper_Settings::POST_TYPE,
		'post_status' => 'publish',
		'post_title'  => 'Browser test: ' . $wp_swiper_name,
		'post_author' => $wp_swiper_user->ID,
	)
);
update_post_meta( $wp_swiper_post, WP_Swiper_Settings::META_SLIDES, $wp_swiper_slides );
update_post_meta( $wp_swiper_post, WP_Swiper_Settings::META_SETTINGS, WP_Swiper_Settings::sanitize( $wp_swiper_case['settings'] ) );

wp_safe_redirect( admin_url( 'post.php?post=' . (int) $wp_swiper_post . '&action=edit' ) );
exit;
