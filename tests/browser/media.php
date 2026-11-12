<?php
/**
 * The browser tests' own photos and video (tests/browser/media/), the same
 * on every machine: so the snapshots do not depend on a site's media
 * library. Added to the library once (marked by the meta below, with the
 * file's hash: a file changed here is added again, its old copy deleted),
 * then taken by their names, in order.
 *
 * Used by fixture.php and admin-login.php (after wp-load.php). Their
 * ?media=1 adds what is missing (global-setup.js, before the tests run at
 * once); ?media=remove deletes them from the library.
 *
 * Not in the plugin's zip (tests/ is export-ignore).
 */

// The meta that marks an attachment as one of these: its file's name and hash.
const WP_SWIPER_TEST_MEDIA = '_wp_swiper_test_media';

/**
 * The files here, by kind, in order: photo-01.jpg …, video.mp4.
 *
 * @return array { photos: string[], video: string[] } file paths.
 */
function wp_swiper_test_media_files() {
	$dir    = __DIR__ . '/media/';
	$photos = glob( $dir . 'photo-*.jpg' );
	sort( $photos );
	return array(
		'photos' => $photos,
		'video'  => file_exists( $dir . 'video.mp4' ) ? array( $dir . 'video.mp4' ) : array(),
	);
}

// What marks a file: its name and hash ("photo-01.jpg:3f2a…").
function wp_swiper_test_media_mark( $file ) {
	return basename( $file ) . ':' . md5_file( $file );
}

/**
 * The attachment of a file, added to the library if it is not there (or
 * was added from another version of the file).
 *
 * @param string $file Path.
 * @param bool   $add  Add it when missing.
 * @return int Its ID; 0: none.
 */
function wp_swiper_test_media_id( $file, $add ) {
	$mark  = wp_swiper_test_media_mark( $file );
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => WP_SWIPER_TEST_MEDIA, // phpcs:ignore WordPress.DB.SlowDBQuery -- a few test files.
			'meta_value'  => $mark, // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}
	if ( ! $add ) {
		return 0;
	}
	// Another version of this file: gone first.
	$old = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_query'  => array( array( 'key' => WP_SWIPER_TEST_MEDIA, 'value' => basename( $file ) . ':', 'compare' => 'LIKE' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $old as $id ) {
		wp_delete_attachment( $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	// A copy: media_handle_sideload() moves the file it is given.
	$copy = wp_tempnam( basename( $file ) );
	copy( $file, $copy );
	$id = media_handle_sideload(
		array(
			'name'     => 'wp-swiper-test-' . basename( $file ),
			'tmp_name' => $copy,
		),
		0,
		'WP Swiper test ' . pathinfo( $file, PATHINFO_FILENAME )
	);
	if ( is_wp_error( $id ) ) {
		if ( file_exists( $copy ) ) {
			unlink( $copy );
		}
		return 0;
	}
	update_post_meta( $id, WP_SWIPER_TEST_MEDIA, $mark );
	return (int) $id;
}

/**
 * The tests' slides: the first `$photos` photos, the video first where asked.
 *
 * @param int  $photos How many photos.
 * @param bool $video  The video first.
 * @param bool $add    Add the files missing from the library.
 * @return int[] Attachment IDs.
 */
function wp_swiper_test_media( $photos, $video = false, $add = true ) {
	$files = wp_swiper_test_media_files();
	$list  = array_slice( $files['photos'], 0, max( 0, (int) $photos ) );
	if ( $video ) {
		$list = array_merge( $files['video'], $list );
	}
	$ids = array();
	foreach ( $list as $file ) {
		$id = wp_swiper_test_media_id( $file, $add );
		if ( $id ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/**
 * ?media=1: every file added (a list of what is there); ?media=remove: all
 * of them deleted from the library. Ends the request.
 */
function wp_swiper_test_media_request() {
	if ( ! isset( $_GET['media'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- local test page.
		return;
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	if ( 'remove' === $_GET['media'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		$ids = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_key'    => WP_SWIPER_TEST_MEDIA, // phpcs:ignore WordPress.DB.SlowDBQuery
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_attachment( $id, true );
		}
		echo 'Removed ' . count( $ids ) . "\n";
		exit;
	}
	$files = wp_swiper_test_media_files();
	foreach ( array_merge( $files['photos'], $files['video'] ) as $file ) {
		echo basename( $file ) . ' ' . wp_swiper_test_media_id( $file, true ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- plain text.
	}
	exit;
}
