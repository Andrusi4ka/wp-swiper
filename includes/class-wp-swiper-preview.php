<?php
/**
 * Preview: a page with nothing but one slider, opened from its edit screen
 * (the "Preview" button of the Shortcode box). It shows the saved slider,
 * drafts included, to those who may edit it.
 *
 * Two pages under one address: the outer one holds a desktop / tablet /
 * mobile switcher and a frame; the slider is in the frame, so that its CSS
 * and frontend.js see the frame's width as the device's.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Preview {

	const QUERY = 'wp_swiper_preview';

	/**
	 * Frame widths (px) inside the plugin's breakpoints; desktop takes the window.
	 */
	const WIDTHS = array(
		'desktop' => 0,
		'tablet'  => 820,
		'mobile'  => 390,
	);

	public static function init() {
		// Once the main query has run: the assets are registered as on any page.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	/**
	 * Address of a slider's preview.
	 *
	 * @param int  $post_id Slider ID.
	 * @param bool $frame   The inner page (the slider itself).
	 * @return string
	 */
	public static function url( $post_id, $frame = false ) {
		$args = array(
			self::QUERY => (int) $post_id,
			'_wpnonce'  => wp_create_nonce( self::QUERY . '_' . (int) $post_id ),
		);
		if ( $frame ) {
			$args['frame'] = 1;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public static function maybe_render() {
		if ( ! isset( $_GET[ self::QUERY ] ) ) {
			return;
		}
		$post_id = absint( wp_unslash( $_GET[ self::QUERY ] ) );
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || WP_Swiper_Settings::POST_TYPE !== $post->post_type
			|| ! wp_verify_nonce( $nonce, self::QUERY . '_' . $post_id )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'This preview link is not valid any more. Open the preview from the slider’s edit screen.', 'wp-swiper' ), '', array( 'response' => 403 ) );
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( isset( $_GET['frame'] ) ) {
			self::render_frame( $post );
		} else {
			self::render_page( $post );
		}
		exit;
	}

	/**
	 * The outer page: device switcher and the frame.
	 *
	 * @param WP_Post $post Slider.
	 */
	private static function render_page( $post ) {
		$title   = $post->post_title ? $post->post_title : __( '(no title)', 'wp-swiper' );
		/* translators: %s: slider title. */
		$heading = sprintf( __( 'Preview: %s', 'wp-swiper' ), $title );
		$devices = array(
			'desktop' => __( 'Desktop', 'wp-swiper' ),
			'tablet'  => __( 'Tablet (768–1024px)', 'wp-swiper' ),
			'mobile'  => __( 'Mobile (below 768px)', 'wp-swiper' ),
		);
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $heading ); ?></title>
	<style>
		html, body { height: 100%; margin: 0; }
		body { display: flex; flex-direction: column; background: #f0f0f1; color: #1d2327; font: 14px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		.bar { display: flex; flex: none; flex-wrap: wrap; align-items: center; gap: 8px 16px; padding: 10px 16px; border-bottom: 1px solid #c3c4c7; background: #fff; }
		.bar__title { margin: 0 auto 0 0; font-size: 14px; font-weight: 600; }
		.bar__note { color: #646970; }
		.bar button { padding: 5px 12px; border: 1px solid #2271b1; border-radius: 3px; background: #f6f7f7; color: #2271b1; font: inherit; cursor: pointer; }
		.bar button[aria-pressed="true"] { background: #2271b1; color: #fff; }
		.bar button:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; }
		.stage { flex: 1 1 auto; min-height: 0; padding: 16px; overflow: auto; }
		.stage iframe { display: block; width: 100%; max-width: 100%; height: 100%; margin: 0 auto; border: 0; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15); }
	</style>
</head>
<body>
	<div class="bar">
		<h1 class="bar__title"><?php echo esc_html( $heading ); ?></h1>
		<span class="bar__note"><?php esc_html_e( 'Shows the saved slider: save your changes, then reload.', 'wp-swiper' ); ?></span>
		<span role="group" aria-label="<?php esc_attr_e( 'Device', 'wp-swiper' ); ?>">
			<?php foreach ( $devices as $device => $label ) : ?>
				<button type="button" data-width="<?php echo (int) self::WIDTHS[ $device ]; ?>" aria-pressed="<?php echo 'desktop' === $device ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
		</span>
	</div>
	<div class="stage">
		<iframe src="<?php echo esc_url( self::url( $post->ID, true ) ); ?>" title="<?php echo esc_attr( $title ); ?>"></iframe>
	</div>
	<script>
		( function () {
			var frame = document.querySelector( 'iframe' );
			var buttons = document.querySelectorAll( '.bar button' );

			[].forEach.call( buttons, function ( button ) {
				button.addEventListener( 'click', function () {
					var width = Number( button.getAttribute( 'data-width' ) );
					frame.style.width = width ? width + 'px' : '';
					[].forEach.call( buttons, function ( other ) {
						other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
					} );
				} );
			} );
		}() );
	</script>
</body>
</html>
		<?php
	}

	/**
	 * The inner page: the slider and the plugin's own assets, no theme. The
	 * block around it is as tall as the frame, for Height "Auto" to fill.
	 *
	 * @param WP_Post $post Slider.
	 */
	private static function render_frame( $post ) {
		WP_Swiper_Render::register_assets();
		$slider = WP_Swiper_Render::render( $post->ID, '', true );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $post->post_title ); ?></title>
	<?php wp_print_styles( array( 'wp-swiper' ) ); ?>
	<style>
		html, body { margin: 0; background: #fff; }
		body { font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		.wp-swiper-preview { box-sizing: border-box; min-height: 100vh; padding: 24px; }
		@media (max-width: 767px) { .wp-swiper-preview { padding: 12px; } }
	</style>
</head>
<body>
	<div class="wp-swiper-preview">
		<?php echo $slider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the slider's markup, escaped where it is built. ?>
	</div>
	<?php wp_print_scripts( array( 'wp-swiper' ) ); ?>
</body>
</html>
		<?php
	}
}
