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
		// As the screens in DEVICES: the iMac's (1.775, ~16:9), the tablet's
		// (0.745, not iPad Air's 820×1180), the phone's.
		'desktop' => array( 1280, 721 ),
		'tablet'  => array( 820, 1101 ),
		'mobile'  => array( 390, 844 ),
	);

	/**
	 * The devices drawn around the frame (assets/images/preview/, the user's
	 * drawings): each one's size and its screen's box and corner radius, in
	 * the drawing's units (its viewBox). Each screen has its frame's
	 * proportions (SIZES): iMac 1.775 = 1280×721, tablet 0.745 = 820×1101,
	 * phone 0.462 = 390×844.
	 */
	const DEVICES = array(
		'desktop' => array(
			'src'    => 'assets/images/preview/desktop.svg',
			'width'  => 1000.2,
			'height' => 793.1,
			'screen' => array( 22.89, 23.39, 954.91, 537.91 ),
			'radius' => 0,
		),
		'tablet'  => array(
			'src'    => 'assets/images/preview/tablet.svg',
			'width'  => 1379.63,
			'height' => 1789.88,
			'screen' => array( 78.02, 84.23, 1214.8, 1631.04 ),
			'radius' => 17.15,
		),
		'mobile' => array(
			'src'    => 'assets/images/preview/phone.svg',
			'width'  => 213.04,
			'height' => 429.61,
			'screen' => array( 12.01, 10.13, 188.99, 409.35 ),
			'radius' => 24.05,
		),
	);

	/**
	 * The device whose settings a screen this wide takes, as the plugin's
	 * breakpoints (frontend.css, slider.js, collage.js, art.js): below 768px
	 * Mobile, up to 1024px Tablet, wider Desktop.
	 *
	 * @param int $width Screen width (px).
	 * @return string desktop | tablet | mobile
	 */
	public static function device_at( $width ) {
		if ( $width <= 767 ) {
			return 'mobile';
		}
		return $width <= 1024 ? 'tablet' : 'desktop';
	}

	/**
	 * The words the frame's own scripts show (window.wpSwiperPreviewI18n): the
	 * Collage's × (preview-collage.js), the Grid shapes buttons and panel
	 * (preview-art.js).
	 *
	 * @return array key => text
	 */
	public static function preview_words() {
		return array(
			'removePlace' => __( 'Remove this place from the collage (the slide stays)', 'wp-swiper' ),
			'shapes'      => __( 'Shapes', 'wp-swiper' ),
			'photos'      => __( 'Photos', 'wp-swiper' ),
			'corners'     => __( 'Corners', 'wp-swiper' ),
			'corner'      => __( 'Corner', 'wp-swiper' ),
			'asShape'     => __( 'As the shape', 'wp-swiper' ),
			'radius'      => __( 'Radius', 'wp-swiper' ),
			'asRadius'    => __( 'As Radius', 'wp-swiper' ),
			'split'       => __( 'Split into cells', 'wp-swiper' ),
			'empty'       => __( 'Empty', 'wp-swiper' ),
		);
	}

	/**
	 * Tablet and Mobile turned sideways (the preview's turn button): what the
	 * note under the bar says, where the screen then takes another device's
	 * settings (its width the portrait height: a phone 844px wide is a tablet).
	 *
	 * @return array device => note text, for the devices that change.
	 */
	public static function turned_notes() {
		$labels = WP_Swiper_Admin::devices();
		$notes  = array();
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$width = self::SIZES[ $device ][1];
			$takes = self::device_at( $width );
			if ( $takes !== $device ) {
				$notes[ $device ] = sprintf(
					/* translators: 1: screen width in px, 2: a device's name (Tablet, Desktop). */
					__( 'Turned sideways the screen is %1$dpx wide: the slider takes the %2$s settings, as it does on the site.', 'wp-swiper' ),
					$width,
					$labels[ $takes ][0]
				);
			}
		}
		return $notes;
	}

	/**
	 * DEVICES for admin.js: each one's size, screen and radius (no file).
	 *
	 * @return array device => [ 'width', 'height', 'screen', 'radius' ].
	 */
	public static function device_frames() {
		$frames = array();
		foreach ( self::DEVICES as $device => $drawing ) {
			unset( $drawing['src'] );
			$frames[ $device ] = $drawing;
		}
		return $frames;
	}

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
				data-sizes="<?php echo esc_attr( wp_json_encode( self::SIZES ) ); ?>"
				data-devices="<?php echo esc_attr( wp_json_encode( self::device_frames() ) ); ?>">
				<div class="wp-swiper-preview__bar">
					<span class="wp-swiper-preview__title"><?php esc_html_e( 'Preview', 'wp-swiper' ); ?></span>
					<?php // Turn the device sideways and back: always here (the bar stays as it is), greyed for Desktop (admin.js). ?>
					<button type="button" class="button wp-swiper-preview__rotate" aria-pressed="false" title="<?php esc_attr_e( 'Turn the tablet or phone sideways', 'wp-swiper' ); ?>" aria-label="<?php esc_attr_e( 'Turn the tablet or phone sideways', 'wp-swiper' ); ?>" disabled>
						<?php echo WP_Swiper_Admin::svg_icon( 'rotate.svg', 'wp-swiper-preview__rotate-icon', 'dashicons-image-rotate' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file. ?>
					</button>
					<?php // Developer mode: the slides', images' and empty spaces' boxes over the preview (assets/js/preview-dev.js). ?>
					<button type="button" class="button wp-swiper-preview__dev" aria-pressed="false" title="<?php esc_attr_e( 'Show the boxes of the slides and images and the empty space around them, with their sizes', 'wp-swiper' ); ?>">
						<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
						<?php esc_html_e( 'Developer mode', 'wp-swiper' ); ?>
					</button>
					<?php // Every effect (admin.js): undo / redo the settings' changes and what was done in the preview. ?>
					<span class="wp-swiper-preview__history" role="group" aria-label="<?php esc_attr_e( 'Undo and redo changes', 'wp-swiper' ); ?>">
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
				<?php // A device turned sideways whose screen then takes another device's settings (admin.js shows it). ?>
				<?php foreach ( self::turned_notes() as $device => $note ) : ?>
					<p class="description wp-swiper-preview__hint wp-swiper-preview__turned" data-device="<?php echo esc_attr( $device ); ?>" hidden>
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<?php echo esc_html( $note ); ?>
					</p>
				<?php endforeach; ?>
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
					<?php esc_html_e( 'Drag the edge of the middle circle or square to resize it.', 'wp-swiper' ); ?>
				</p>
				<?php // Art's Grid shapes only (admin.js): its shapes are drawn here (preview-art.js). ?>
				<p class="description wp-swiper-preview__hint" data-effect="art-cells" hidden>
					<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
					<?php esc_html_e( 'Shapes: drag across cells to join them, click a shape for its corners (a dot on a corner: that corner alone). Photos: drag a photo onto another to swap them.', 'wp-swiper' ); ?>
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
					<?php // Tablet and Mobile: the frame in the device's screen (admin.js fitPreview()); over it, it takes no clicks. ?>
					<?php foreach ( self::DEVICES as $device => $drawing ) : ?>
						<img class="wp-swiper-preview__device" data-device="<?php echo esc_attr( $device ); ?>" src="<?php echo esc_url( WP_SWIPER_URL . $drawing['src'] ); ?>" alt="" hidden>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Background: the slider in a block with its class, as on a page (without
	 * a class there is no such block: the notice says so), and a label in its
	 * corner saying the mode is on (the preview only, never on a page). No
	 * example content over it: it only drew the eye. Otherwise the slider as
	 * it is.
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
			'<div class="wp-swiper-preview-block %1$s"><span class="wp-swiper-preview-block__mode">%2$s</span>%3$s</div>',
			esc_attr( $settings['background_class'] ),
			esc_html__( 'Background mode', 'wp-swiper' ),
			$slider
		);
	}

	/**
	 * The frame of a slider with no slides yet: a picture (assets/images/
	 * no-image.svg) in the middle of the device's screen, in place of the
	 * notice a page shows.
	 *
	 * @return string
	 */
	public static function empty_screen() {
		return sprintf(
			'<div class="wp-swiper-preview-empty"><img src="%s" alt="%s" width="256" height="256"></div>',
			esc_url( WP_SWIPER_URL . 'assets/images/no-image.svg?ver=' . wp_swiper_asset_version( 'assets/images/no-image.svg' ) ),
			esc_attr__( 'No images yet', 'wp-swiper' )
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
		$slides = WP_Swiper_Admin::posted_slides();
		if ( $slides ) {
			$slider = self::background_block( WP_Swiper_Render::render_slider( $post_id, $slides, $settings ), $settings );
		} else {
			$slider = self::empty_screen();
		}

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
	<?php wp_print_styles( array( 'swiper', 'wp-swiper', 'wp-swiper-slider', 'wp-swiper-thumbs', 'wp-swiper-collage', 'wp-swiper-art' ) ); ?>
	<?php // Developer mode (the button by "Preview"; admin.js turns it on in here). ?>
	<link rel="stylesheet" href="<?php echo esc_url( WP_SWIPER_URL . 'assets/css/preview-dev.css?ver=' . wp_swiper_asset_version( 'assets/css/preview-dev.css' ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( WP_SWIPER_URL . 'assets/css/preview-art.css?ver=' . wp_swiper_asset_version( 'assets/css/preview-art.css' ) ); ?>">
	<style>
		html, body { margin: 0; background: #fff; }
		body { font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		/* Tablet and Mobile (in the device): its scroll bar, thin, rounded, see-through, no track. */
		@media (max-width: 1024px) {
			html::-webkit-scrollbar { width: 4px; height: 4px; }
			html::-webkit-scrollbar-track { background: transparent; }
			html::-webkit-scrollbar-thumb { border-radius: 4px; background: rgba(0, 0, 0, 0.35); }
			html::-webkit-scrollbar-corner { background: transparent; }
			@supports not selector(::-webkit-scrollbar) {
				html { scrollbar-width: thin; scrollbar-color: rgba(0, 0, 0, 0.35) transparent; }
			}
		}
		.wp-swiper-preview-page { box-sizing: border-box; min-height: 100vh; padding: 24px; }
		@media (max-width: 767px) { .wp-swiper-preview-page { padding: 12px; } }
		.wp-swiper-preview-block { position: relative; background: #f0f0f1; }
		/* "Background mode": a small label in the block's top right corner, over the slider (the left one has Art's Shapes / Photos buttons). */
		.wp-swiper-preview-block__mode { position: absolute; top: 12px; right: 12px; z-index: 3; padding: 3px 10px; border-radius: 999px; background: rgba(0, 0, 0, 0.6); color: #fff; font-size: 12px; line-height: 18px; font-weight: 600; pointer-events: none; }
		/* Background: the block fills the device's screen, no gaps around it (the
		   preview only; on the site the block's height is the page builder's). */
		.wp-swiper-preview-page--background { padding: 0; }
		.wp-swiper-preview-page--background .wp-swiper-preview-block { height: 100vh; }
		/* Here only (on a page the content takes the clicks): the background slider
		   takes the pointer, so its photos can be dragged (preview-collage.js,
		   preview-art.js). */
		.wp-swiper-preview-block .wp-swiper.wp-swiper--background,
		.wp-swiper-preview-block .wp-swiper-art.wp-swiper-art--background { pointer-events: auto; }
		/* No slides yet: the picture in the middle of the screen, not too big. */
		.wp-swiper-preview-empty { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; }
		.wp-swiper-preview-empty img { display: block; width: min(160px, 32vw); height: auto; }
	</style>
</head>
<body>
	<div class="wp-swiper-preview-page<?php echo WP_Swiper_Settings::is_background( $settings ) ? ' wp-swiper-preview-page--background' : ''; ?>">
		<?php echo $slider; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the slider's markup, escaped where it is built. ?>
	</div>
	<?php wp_print_scripts( array( 'swiper', 'wp-swiper', 'wp-swiper-slider', 'wp-swiper-collage', 'wp-swiper-art' ) ); ?>
	<script src="<?php echo esc_url( WP_SWIPER_URL . 'assets/js/preview-dev.js?ver=' . wp_swiper_asset_version( 'assets/js/preview-dev.js' ) ); ?>"></script>
	<?php // Collage: a photo dragged onto another place swaps with it (the editor stores the order); its words. ?>
	<?php // And Art's Grid shapes: the buttons and the selected shape's panel (preview-art.js). ?>
	<script>window.wpSwiperPreviewI18n = <?php echo wp_json_encode( self::preview_words(), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;</script>
	<script src="<?php echo esc_url( WP_SWIPER_URL . 'assets/js/preview-collage.js?ver=' . wp_swiper_asset_version( 'assets/js/preview-collage.js' ) ); ?>"></script>
	<?php // Art: the same for its pieces, its own script (the editor stores its order apart). ?>
	<script src="<?php echo esc_url( WP_SWIPER_URL . 'assets/js/preview-art.js?ver=' . wp_swiper_asset_version( 'assets/js/preview-art.js' ) ); ?>"></script>
</body>
</html>
		<?php
		exit;
	}
}
