<?php
/**
 * Preview on the slider edit screen: a frame beside the settings with the
 * slider as the form's values draw it, saved or not. Nothing is saved: the
 * editor posts the form into the frame (admin.js) when it opens and on the
 * "Update" button, and the frame is a page with the slider and the
 * plugin's own assets alone, so its CSS and frontend.js take the frame's
 * width for the device's.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Preview {

	const ACTION = 'wp_swiper_preview';

	/**
	 * Frame sizes (px) per device, inside the plugin's breakpoints; the frame
	 * is drawn this size and scaled down to fit (admin.js).
	 */
	const SIZES = array(
		'desktop' => array( 1280, 800 ),
		'tablet'  => array( 820, 1180 ),
		'mobile'  => array( 390, 844 ),
	);

	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'render_frame' ) );
	}

	/**
	 * The preview column: device switcher, "Update" and the frame.
	 *
	 * @param WP_Post $post Slider.
	 */
	public static function render_panel( $post ) {
		?>
		<div class="wp-swiper-settings__preview">
			<div class="wp-swiper-preview"
				data-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				data-action="<?php echo esc_attr( self::ACTION ); ?>"
				data-post="<?php echo (int) $post->ID; ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( self::ACTION . '_' . $post->ID ) ); ?>"
				data-sizes="<?php echo esc_attr( wp_json_encode( self::SIZES ) ); ?>">
				<div class="wp-swiper-preview__bar">
					<span class="wp-swiper-preview__title"><?php esc_html_e( 'Preview', 'wp-swiper' ); ?></span>
					<?php // Developer mode: the slides', images' and empty spaces' boxes over the preview (assets/js/preview-dev.js). ?>
					<button type="button" class="button wp-swiper-preview__dev" aria-pressed="false" title="<?php esc_attr_e( 'Show the boxes of the slides and images and the empty space around them, with their sizes', 'wp-swiper' ); ?>">
						<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
						<?php esc_html_e( 'Developer mode', 'wp-swiper' ); ?>
					</button>
					<?php // Collage and Art only (admin.js): undo / redo what was done in the preview, for the device shown. ?>
					<span class="wp-swiper-preview__history" role="group" aria-label="<?php esc_attr_e( 'Changes made in the preview', 'wp-swiper' ); ?>" hidden>
						<button type="button" class="button wp-swiper-preview__undo" disabled title="<?php esc_attr_e( 'Undo (Ctrl+Z)', 'wp-swiper' ); ?>" aria-label="<?php esc_attr_e( 'Undo', 'wp-swiper' ); ?>">
							<span class="dashicons dashicons-undo" aria-hidden="true"></span>
						</button>
						<button type="button" class="button wp-swiper-preview__redo" disabled title="<?php esc_attr_e( 'Redo (Ctrl+Shift+Z)', 'wp-swiper' ); ?>" aria-label="<?php esc_attr_e( 'Redo', 'wp-swiper' ); ?>">
							<span class="dashicons dashicons-redo" aria-hidden="true"></span>
						</button>
					</span>
					<?php // The same device as the settings' values (admin.js). ?>
					<?php WP_Swiper_Admin::render_device_switch( 'wp-swiper-preview__devices' ); ?>
					<button type="button" class="button wp-swiper-preview__update">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php esc_html_e( 'Update', 'wp-swiper' ); ?>
					</button>
				</div>
				<?php // Collage only (admin.js): its photos swap places by dragging here (preview-collage.js). ?>
				<p class="description wp-swiper-preview__hint" data-effect="collage" hidden>
					<span class="dashicons dashicons-move" aria-hidden="true"></span>
					<?php esc_html_e( 'Drag a photo onto another to swap them.', 'wp-swiper' ); ?>
				</p>
				<?php // Art only (admin.js): its photos swap places by dragging here (preview-art.js). ?>
				<p class="description wp-swiper-preview__hint" data-effect="art" hidden>
					<span class="dashicons dashicons-move" aria-hidden="true"></span>
					<?php esc_html_e( 'Drag a photo onto another to swap them.', 'wp-swiper' ); ?>
				</p>
				<?php // Art only too: the middle circle's edge resizes it (preview-art.js). ?>
				<p class="description wp-swiper-preview__hint" data-effect="art" hidden>
					<span class="dashicons dashicons-image-filter" aria-hidden="true"></span>
					<?php esc_html_e( 'Drag the edge of the middle circle to resize it.', 'wp-swiper' ); ?>
				</p>
				<?php // Random mosaic only (admin.js): the lines between its photos resize them. ?>
				<p class="description wp-swiper-preview__hint" data-effect="mosaic" hidden>
					<span class="dashicons dashicons-leftright" aria-hidden="true"></span>
					<?php esc_html_e( 'Drag a line between photos to resize them.', 'wp-swiper' ); ?>
				</p>
				<?php // Scattered only (admin.js): a photo's outer edges resize it. ?>
				<p class="description wp-swiper-preview__hint" data-effect="scatter" hidden>
					<span class="dashicons dashicons-editor-expand" aria-hidden="true"></span>
					<?php esc_html_e( 'Drag the outer edge of a photo to resize it.', 'wp-swiper' ); ?>
				</p>
				<?php // Scattered only too: a photo dropped on a dashed frame moves there (preview-collage.js). ?>
				<p class="description wp-swiper-preview__hint" data-effect="scatter" hidden>
					<span class="dashicons dashicons-move" aria-hidden="true"></span>
					<?php esc_html_e( 'Drop a photo on a dashed frame to move it to the end of that arm.', 'wp-swiper' ); ?>
				</p>
				<?php // Scattered only too: a photo's × takes its place out of the collage (preview-collage.js). ?>
				<p class="description wp-swiper-preview__hint" data-effect="scatter" hidden>
					<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
					<?php esc_html_e( 'Click × on a photo to take its place out of the collage on this device; the slide stays.', 'wp-swiper' ); ?>
				</p>
				<?php // Freeform only (admin.js): photos moved anywhere, a Gap from the others (preview-collage.js). ?>
				<p class="description wp-swiper-preview__hint" data-effect="freeform" hidden>
					<span class="dashicons dashicons-move" aria-hidden="true"></span>
					<?php esc_html_e( 'Drag a photo anywhere: it stops a Gap away from the others and snaps to their edges. Drop it on another photo to swap the two.', 'wp-swiper' ); ?>
				</p>
				<?php // Freeform only too: a photo's corners and edges resize it. ?>
				<p class="description wp-swiper-preview__hint" data-effect="freeform" hidden>
					<span class="dashicons dashicons-editor-expand" aria-hidden="true"></span>
					<?php esc_html_e( 'Drag a corner or an edge of a photo to resize it.', 'wp-swiper' ); ?>
				</p>
				<?php // Freeform only too: a photo's × takes its place out (preview-collage.js); the others stay where they are. ?>
				<p class="description wp-swiper-preview__hint" data-effect="freeform" hidden>
					<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
					<?php esc_html_e( 'Click × on a photo to take its place out of the collage on this device; the slide stays.', 'wp-swiper' ); ?>
				</p>
				<div class="wp-swiper-preview__stage">
					<iframe class="wp-swiper-preview__frame" name="wp-swiper-preview-<?php echo (int) $post->ID; ?>" title="<?php esc_attr_e( 'Slider preview', 'wp-swiper' ); ?>" src="about:blank"></iframe>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Background: the slider in a block with its class and some content over
	 * it, as on a page (without a class there is no such block: the notice
	 * says so), and a label in its corner saying the mode is on (the preview
	 * only, never on a page). Otherwise the slider as it is.
	 *
	 * @param string $slider   The slider's markup.
	 * @param array  $settings Slider settings.
	 * @return string
	 */
	public static function background_block( $slider, $settings ) {
		if ( ! WP_Swiper_Settings::is_background( $settings ) ) {
			return $slider;
		}
		return sprintf(
			'<div class="wp-swiper-preview-block %1$s"><span class="wp-swiper-preview-block__mode">%2$s</span><div class="wp-swiper-preview-block__content"><h2>%3$s</h2><p>%4$s</p><span class="wp-swiper-preview-block__button">%5$s</span></div>%6$s</div>',
			esc_attr( $settings['background_class'] ),
			esc_html__( 'Background mode', 'wp-swiper' ),
			esc_html__( 'The block’s content', 'wp-swiper' ),
			esc_html__( 'Text, buttons and other elements of the row or column sit over the slider.', 'wp-swiper' ),
			esc_html__( 'Button', 'wp-swiper' ),
			$slider
		);
	}

	/**
	 * The frame: the slider drawn from the posted form, with the plugin's
	 * assets and no theme. The block around it is as tall as the frame, for
	 * Height "Auto" to fill.
	 */
	public static function render_frame() {
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || WP_Swiper_Settings::POST_TYPE !== $post->post_type
			|| ! wp_verify_nonce( $nonce, self::ACTION . '_' . $post_id )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'The preview is not available any more: reload the page.', 'wp-swiper' ), '', array( 'response' => 403 ) );
		}

		// As saving would store them, over the defaults (see WP_Swiper_Settings::get()).
		$input    = isset( $_POST['wp_swiper_settings'] ) ? (array) wp_unslash( $_POST['wp_swiper_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized against the schema.
		$settings = wp_parse_args( WP_Swiper_Settings::sanitize( $input ), WP_Swiper_Settings::defaults() );

		WP_Swiper_Render::register_assets();
		WP_Swiper_Art::register_assets();
		$slider = WP_Swiper_Render::render_slider( $post_id, WP_Swiper_Admin::posted_slides(), $settings );

		$slider = self::background_block( $slider, $settings );

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Robots-Tag: noindex, nofollow' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $post->post_title ); ?></title>
	<?php wp_print_styles( array( 'swiper', 'wp-swiper', 'wp-swiper-collage', 'wp-swiper-art' ) ); ?>
	<?php // Developer mode (the button by "Preview"; admin.js turns it on in here). ?>
	<link rel="stylesheet" href="<?php echo esc_url( WP_SWIPER_URL . 'assets/css/preview-dev.css?ver=' . wp_swiper_asset_version( 'assets/css/preview-dev.css' ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( WP_SWIPER_URL . 'assets/css/preview-art.css?ver=' . wp_swiper_asset_version( 'assets/css/preview-art.css' ) ); ?>">
	<style>
		html, body { margin: 0; background: #fff; }
		body { font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		.wp-swiper-preview-page { box-sizing: border-box; min-height: 100vh; padding: 24px; }
		@media (max-width: 767px) { .wp-swiper-preview-page { padding: 12px; } }
		.wp-swiper-preview-block { position: relative; background: #f0f0f1; }
		/* "Background mode": a small label in the block's corner, over the slider and its content. */
		.wp-swiper-preview-block__mode { position: absolute; top: 12px; left: 12px; z-index: 3; padding: 3px 10px; border-radius: 999px; background: rgba(0, 0, 0, 0.6); color: #fff; font-size: 12px; line-height: 18px; font-weight: 600; pointer-events: none; }
		/* Its content in the middle of the block, as an example of what sits over the slider. */
		.wp-swiper-preview-block { display: flex; align-items: center; justify-content: center; }
		/* Background: the block at a screen's usual proportions per device, unless
		   it has a minimum height of its own (shown as set; more content makes it taller). */
		.wp-swiper-preview-block.wp-swiper-bg-host:not(.wp-swiper-bg-host--min) { aspect-ratio: 16 / 9; }
		@media (max-width: 1024px) { .wp-swiper-preview-block.wp-swiper-bg-host:not(.wp-swiper-bg-host--min) { aspect-ratio: 4 / 3; } }
		@media (max-width: 767px) { .wp-swiper-preview-block.wp-swiper-bg-host:not(.wp-swiper-bg-host--min) { aspect-ratio: 3 / 4; } }
		.wp-swiper-preview-block__content { box-sizing: border-box; max-width: 640px; padding: 72px 40px; color: #fff; text-align: center; text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5); }
		/* Here only (on a page the content takes the clicks): the background slider
		   takes the pointer, the example content lets it through, so its photos can
		   be dragged (preview-collage.js, preview-art.js) under it too. */
		.wp-swiper-preview-block .wp-swiper.wp-swiper--background { pointer-events: auto; }
		.wp-swiper-preview-block__content { pointer-events: none; }
		.wp-swiper-preview-block__content h2 { margin: 0 0 12px; font-size: 32px; line-height: 1.2; }
		.wp-swiper-preview-block__content p { margin: 0 0 24px; }
		.wp-swiper-preview-block__button { display: inline-block; padding: 10px 24px; border-radius: 4px; background: #fff; color: #1d2327; text-shadow: none; font-weight: 600; }
		@media (max-width: 767px) { .wp-swiper-preview-block__content { padding: 40px 20px; } .wp-swiper-preview-block__content h2 { font-size: 24px; } }
	</style>
</head>
<body>
	<div class="wp-swiper-preview-page">
		<?php echo $slider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the slider's markup, escaped where it is built. ?>
	</div>
	<?php wp_print_scripts( array( 'swiper', 'wp-swiper', 'wp-swiper-slider', 'wp-swiper-collage', 'wp-swiper-art' ) ); ?>
	<script src="<?php echo esc_url( WP_SWIPER_URL . 'assets/js/preview-dev.js?ver=' . wp_swiper_asset_version( 'assets/js/preview-dev.js' ) ); ?>"></script>
	<?php // Collage: a photo dragged onto another place swaps with it (the editor stores the order); its words. ?>
	<script>window.wpSwiperPreviewI18n = <?php echo wp_json_encode( array( 'removePlace' => __( 'Remove this place from the collage (the slide stays)', 'wp-swiper' ) ) ); ?>;</script>
	<script src="<?php echo esc_url( WP_SWIPER_URL . 'assets/js/preview-collage.js?ver=' . wp_swiper_asset_version( 'assets/js/preview-collage.js' ) ); ?>"></script>
	<?php // Art: the same for its pieces, its own script (the editor stores its order apart). ?>
	<script src="<?php echo esc_url( WP_SWIPER_URL . 'assets/js/preview-art.js?ver=' . wp_swiper_asset_version( 'assets/js/preview-art.js' ) ); ?>"></script>
</body>
</html>
		<?php
		exit;
	}
}
