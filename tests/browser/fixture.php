<?php
/**
 * A page with one slider for the browser tests (tests/browser/*.spec.js):
 * ?case=<name> picks its settings from cases.php, its slides are the first
 * images of the media library (and its first video where a case asks for
 * it). Nothing is saved: it renders the slider the way the preview does
 * and enqueues and prints the files the way a page would.
 *
 * Opened over HTTP from this machine only, on the local site
 * (https://creativity.local/wp-content/plugins/wp-swiper/tests/browser/fixture.php).
 * Not in the plugin's zip (tests/ is export-ignore).
 */

$wp_swiper_remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
if ( 'cli' !== PHP_SAPI && 0 !== strpos( $wp_swiper_remote, '127.' ) && '::1' !== $wp_swiper_remote ) {
	http_response_code( 403 );
	exit( 'Local only.' );
}

require dirname( __DIR__, 5 ) . '/wp-load.php';

$wp_swiper_cases = require __DIR__ . '/cases.php';
$wp_swiper_name  = isset( $_GET['case'] ) ? sanitize_key( wp_unslash( $_GET['case'] ) ) : '';

if ( ! isset( $wp_swiper_cases[ $wp_swiper_name ] ) ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo "Cases:\n" . implode( "\n", array_keys( $wp_swiper_cases ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text of our own keys.
	exit;
}

$wp_swiper_case = $wp_swiper_cases[ $wp_swiper_name ] + array(
	'photos'   => 8,
	'video'    => false,
	'captions' => false,
	'block'    => false,
);

// The slides: the first images by ID (the same each time), a caption on
// each where the case asks for one, the first video first where it does.
$wp_swiper_ids = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'numberposts'    => $wp_swiper_case['photos'],
		'fields'         => 'ids',
	)
);
if ( $wp_swiper_case['video'] ) {
	$wp_swiper_video = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'video',
			'post_status'    => 'inherit',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'numberposts'    => 1,
			'fields'         => 'ids',
		)
	);
	$wp_swiper_ids   = array_merge( $wp_swiper_video, $wp_swiper_ids );
}
$wp_swiper_slides = array();
foreach ( $wp_swiper_ids as $wp_swiper_index => $wp_swiper_id ) {
	// All the keys a saved slide has (WP_Swiper_Admin::posted_slides()).
	$wp_swiper_slides[] = array(
		'id'                 => $wp_swiper_id,
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

$wp_swiper_settings = wp_parse_args( WP_Swiper_Settings::sanitize( $wp_swiper_case['settings'] ), WP_Swiper_Settings::defaults() );

// As on a page: the assets registered on wp_enqueue_scripts, then the
// shortcode's render enqueues what this slider needs.
WP_Swiper_Render::register_assets();
$wp_swiper_markup = WP_Swiper_Render::render_slider( 0, $wp_swiper_slides, $wp_swiper_settings );
if ( $wp_swiper_case['block'] ) {
	$wp_swiper_markup = '<div class="fixture-block ' . esc_attr( $wp_swiper_settings['background_class'] ) . '"><div class="fixture-block__content"><h2>Block</h2></div>' . $wp_swiper_markup . '</div>';
}

nocache_headers();
header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $wp_swiper_name ); ?></title>
	<?php wp_print_styles(); ?>
	<style>
		html, body { margin: 0; background: #fff; }
		body { font: 16px/1.5 sans-serif; }
		.fixture { box-sizing: border-box; max-width: 1200px; margin: 0 auto; padding: 24px; }
		.fixture-block { position: relative; min-height: 480px; display: flex; align-items: center; justify-content: center; }
		.fixture-block__content { position: relative; z-index: 1; color: #fff; }
	</style>
</head>
<body>
	<div class="fixture">
		<?php echo $wp_swiper_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the slider's markup, escaped where it is built. ?>
	</div>
	<?php
	wp_print_scripts();
	// Which of our files the page loaded, for the tests.
	$wp_swiper_ours   = function ( $handles ) {
		return array_values(
			array_filter(
				$handles,
				function ( $handle ) {
					return 0 === strpos( $handle, 'wp-swiper' ) || 'swiper' === $handle;
				}
			)
		);
	};
	$wp_swiper_loaded = array(
		'scripts' => $wp_swiper_ours( wp_scripts()->done ),
		'styles'  => $wp_swiper_ours( wp_styles()->done ),
	);
	?>
	<script>window.fixtureLoaded = <?php echo wp_json_encode( $wp_swiper_loaded ); ?>;</script>
</body>
</html>
