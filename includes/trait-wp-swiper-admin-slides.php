<?php
/**
 * The Slides box of the edit screen: the list of slides, each slide's row
 * (its values in hidden wp_swiper_slides[...] fields) and reading them back
 * on save. Used by WP_Swiper_Admin, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Admin_Slides {

	public static function render_slides_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		// Images and videos deleted from the media library are skipped (and dropped on save).
		$slides = array_filter( WP_Swiper_Settings::get_slides( $post->ID ), array( 'WP_Swiper_Settings', 'is_slide' ) );
		$shown  = count( $slides );
		?>
		<?php // Only useful once there are slides; admin.js toggles it with the list. ?>
		<p class="description wp-swiper-hint"<?php echo $shown ? '' : ' hidden'; ?>><?php esc_html_e( 'Drag slides to reorder. “Edit”: a slide’s caption, description and link, all optional; a video slide has no link, its poster image is set on the Video page of the settings.', 'wp-swiper' ); ?></p>

		<?php
		// A slider saved with a font size of its own (until 2.8): admin.js moves
		// it into the slides without theirs, so saving keeps it per slide.
		$settings = WP_Swiper_Settings::get( $post->ID );
		?>
		<ul class="wp-swiper-slides" id="wp-swiper-slide-list" data-legacy-caption-tag="<?php echo esc_attr( WP_Swiper_Settings::legacy_caption_tag( $settings ) ); ?>" data-legacy-description-size="<?php echo esc_attr( WP_Swiper_Settings::legacy_font_sizes( $settings, 'description' ) ); ?>" data-legacy-caption-weight="<?php echo esc_attr( WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ) ); ?>" data-legacy-description-weight="<?php echo esc_attr( WP_Swiper_Settings::legacy_font_weights( $settings, 'description' ) ); ?>">
			<?php
			foreach ( $slides as $slide ) {
				self::render_slide_item( $slide );
			}
			?>
		</ul>

		<p class="wp-swiper-empty"<?php echo $shown ? ' hidden' : ''; ?>><?php esc_html_e( 'No slides yet.', 'wp-swiper' ); ?></p>

		<div class="wp-swiper-add-row">
			<button type="button" class="button button-primary" id="wp-swiper-add">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add images or videos', 'wp-swiper' ); ?>
			</button>
			<?php // A YouTube / Vimeo video: its link (admin.js checks it and adds the slide). ?>
			<span class="wp-swiper-embed-add">
				<input type="url" id="wp-swiper-embed-url" placeholder="<?php esc_attr_e( 'YouTube or Vimeo link', 'wp-swiper' ); ?>" aria-label="<?php esc_attr_e( 'YouTube or Vimeo link', 'wp-swiper' ); ?>">
				<button type="button" class="button" id="wp-swiper-embed-add"><?php esc_html_e( 'Add video link', 'wp-swiper' ); ?></button>
			</span>
			<span class="wp-swiper-embed-error" role="alert" hidden><?php esc_html_e( 'That is not a YouTube or Vimeo video link.', 'wp-swiper' ); ?></span>
		</div>

		<script type="text/html" id="tmpl-wp-swiper-slide">
			<?php self::render_slide_item( array(), true ); ?>
		</script>

		<?php
		// A slide's caption, description and link: one window for all slides,
		// a tab each (admin.js fills it from the slide and starts its editors
		// as their tabs show, removes them on closing, so the slides stay plain
		// fields that can be dragged). Its fields have no name: what is applied
		// goes to the slide's own hidden ones.
		?>
		<div class="wp-swiper-modal" id="wp-swiper-slide-modal" role="dialog" aria-modal="true" aria-labelledby="wp-swiper-slide-modal-title" hidden>
			<div class="wp-swiper-modal__backdrop"></div>
			<div class="wp-swiper-modal__box">
				<div class="wp-swiper-modal__head">
					<?php // The slide's thumbnail (admin.js puts it in: its image or poster, else a video icon). ?>
					<span class="wp-swiper-modal__thumb" aria-hidden="true"><span class="wp-swiper-modal__thumb-media"></span></span>
					<div class="wp-swiper-modal__heading">
						<h2 class="wp-swiper-modal__title" id="wp-swiper-slide-modal-title"><?php esc_html_e( 'Slide info', 'wp-swiper' ); ?> <span class="wp-swiper-modal__slide"></span></h2>
						<?php // Another image or video for the slide (admin.js). ?>
						<button type="button" class="button button-small wp-swiper-modal__replace">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
							<?php esc_html_e( 'Replace photo', 'wp-swiper' ); ?>
						</button>
					</div>
					<button type="button" class="wp-swiper-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-swiper' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</div>
				<?php
				// Its three parts, one tab each (admin.js switches them; Caption on
				// opening). A dot on a tab: that part has something in it.
				$tabs = array(
					'caption'     => __( 'Caption', 'wp-swiper' ),
					'description' => __( 'Description', 'wp-swiper' ),
					'link'        => __( 'Link', 'wp-swiper' ),
				);
				?>
				<div class="wp-swiper-modal__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Slide info', 'wp-swiper' ); ?>">
					<?php foreach ( $tabs as $tab => $tab_label ) : ?>
						<button type="button" class="wp-swiper-modal__tab" id="wp-swiper-modal-tab-<?php echo esc_attr( $tab ); ?>" role="tab" data-tab="<?php echo esc_attr( $tab ); ?>" aria-controls="wp-swiper-modal-panel-<?php echo esc_attr( $tab ); ?>" aria-selected="<?php echo 'caption' === $tab ? 'true' : 'false'; ?>"<?php echo 'caption' === $tab ? '' : ' tabindex="-1"'; ?>>
							<?php echo esc_html( $tab_label ); ?><span class="wp-swiper-modal__tab-dot" aria-hidden="true"></span>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="wp-swiper-modal__body">
					<div class="wp-swiper-modal__section" id="wp-swiper-modal-panel-caption" role="tabpanel" data-tab="caption" aria-labelledby="wp-swiper-modal-tab-caption">
						<h3 class="wp-swiper-modal__label screen-reader-text"><label for="wp-swiper-caption-editor"><?php esc_html_e( 'Caption', 'wp-swiper' ); ?></label></h3>
						<textarea id="wp-swiper-caption-editor" rows="3"></textarea>
						<?php // Its font size, weight and color side by side. ?>
						<div class="wp-swiper-modal__text-style">
							<?php self::font_control( 'caption_size' ); ?>
							<?php self::font_control( 'caption_weight' ); ?>
							<?php self::color_control( 'caption_color' ); ?>
						</div>
					</div>
					<div class="wp-swiper-modal__section" id="wp-swiper-modal-panel-description" role="tabpanel" data-tab="description" aria-labelledby="wp-swiper-modal-tab-description" hidden>
						<h3 class="wp-swiper-modal__label screen-reader-text"><label for="wp-swiper-description-editor"><?php esc_html_e( 'Description', 'wp-swiper' ); ?></label></h3>
						<textarea id="wp-swiper-description-editor" rows="10"></textarea>
						<div class="wp-swiper-modal__text-style">
							<?php self::font_control( 'description_size' ); ?>
							<?php self::font_control( 'description_weight' ); ?>
							<?php self::color_control( 'description_color' ); ?>
						</div>
					</div>
					<?php // Not for video slides (a click on one is the player's): admin.js hides its tab. ?>
					<div class="wp-swiper-modal__section wp-swiper-modal__link" id="wp-swiper-modal-panel-link" role="tabpanel" data-tab="link" aria-labelledby="wp-swiper-modal-tab-link" hidden>
						<h3 class="wp-swiper-modal__label screen-reader-text"><label for="wp-swiper-link-input"><?php esc_html_e( 'Link', 'wp-swiper' ); ?></label></h3>
						<p class="description"><?php esc_html_e( 'A click on the image opens it. A slider with links has no lightbox.', 'wp-swiper' ); ?></p>
						<input type="url" id="wp-swiper-link-input" class="large-text" placeholder="https://…" spellcheck="false">
						<label class="wp-swiper-modal__new-tab">
							<input type="checkbox" id="wp-swiper-new-tab-input">
							<?php esc_html_e( 'Open in a new tab', 'wp-swiper' ); ?>
						</label>
					</div>
				</div>
				<div class="wp-swiper-modal__foot">
					<button type="button" class="button wp-swiper-modal__cancel"><?php esc_html_e( 'Cancel', 'wp-swiper' ); ?></button>
					<button type="button" class="button button-primary wp-swiper-modal__apply"><?php esc_html_e( 'Apply', 'wp-swiper' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		// An image's own position ("Cover"): a click (or drag, or the arrow keys)
		// on the image sets the point kept in view; the frames show the crops.
		?>
		<div class="wp-swiper-modal wp-swiper-modal--position" id="wp-swiper-position-modal" role="dialog" aria-modal="true" aria-labelledby="wp-swiper-position-modal-title" hidden>
			<div class="wp-swiper-modal__backdrop"></div>
			<div class="wp-swiper-modal__box">
				<div class="wp-swiper-modal__head">
					<h2 class="wp-swiper-modal__title" id="wp-swiper-position-modal-title"><?php esc_html_e( 'Image position', 'wp-swiper' ); ?> <span class="wp-swiper-modal__slide"></span></h2>
					<button type="button" class="wp-swiper-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-swiper' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</div>
				<div class="wp-swiper-modal__body">
					<p class="description"><?php esc_html_e( 'Click the part of the image to keep in view when the slider crops it (Image fit "Cover"). It applies on every device, over the slider\'s Image position.', 'wp-swiper' ); ?></p>
					<div class="wp-swiper-focus">
						<div class="wp-swiper-focus__stage" tabindex="0" role="slider" aria-label="<?php esc_attr_e( 'Point kept in view', 'wp-swiper' ); ?>" aria-valuemin="0" aria-valuemax="100">
							<img class="wp-swiper-focus__image" alt="" draggable="false">
							<span class="wp-swiper-focus__point" aria-hidden="true"></span>
						</div>
						<?php // The crops of a wide, a square and a tall slide (admin.js sets their object-position). ?>
						<div class="wp-swiper-focus__frames" aria-hidden="true">
							<span class="wp-swiper-focus__frame wp-swiper-focus__frame--wide"><img alt=""></span>
							<span class="wp-swiper-focus__frame wp-swiper-focus__frame--square"><img alt=""></span>
							<span class="wp-swiper-focus__frame wp-swiper-focus__frame--tall"><img alt=""></span>
						</div>
					</div>
					<p class="wp-swiper-focus__value"></p>
				</div>
				<div class="wp-swiper-modal__foot">
					<button type="button" class="button-link wp-swiper-focus__reset"><?php esc_html_e( 'Use the slider\'s position', 'wp-swiper' ); ?></button>
					<button type="button" class="button wp-swiper-modal__cancel"><?php esc_html_e( 'Cancel', 'wp-swiper' ); ?></button>
					<button type="button" class="button button-primary wp-swiper-modal__apply"><?php esc_html_e( 'Apply', 'wp-swiper' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * One slide row. Inputs use parallel arrays so DOM order = saved order.
	 * Its caption, description and link are edited in the slide's window.
	 * A video slide (.wp-swiper-slide--video): its poster as the thumbnail (a
	 * video icon without one), no link (the window has none); its poster is chosen on the Video
	 * page of the settings (admin.js), kept in the row's hidden input.
	 *
	 * The JS template ($template) has {{id}}, {{thumb}}, {{title}}, {{embed}}
	 * and {{badge}} placeholders; admin.js marks a video row itself.
	 *
	 * @param array $slide    Slide data.
	 * @param bool  $template The row admin.js copies for new slides.
	 */
	private static function render_slide_item( $slide, $template = false ) {
		if ( $template ) {
			$slide = array(
				'id'      => '{{id}}',
				'caption' => '',
				'link'    => '',
				'embed'   => '{{embed}}',
			);
		}
		$caption     = isset( $slide['caption'] ) ? (string) $slide['caption'] : '';
		$description = isset( $slide['description'] ) ? (string) $slide['description'] : '';
		$link        = isset( $slide['link'] ) ? (string) $slide['link'] : '';
		$position    = isset( $slide['position'] ) ? WP_Swiper_Settings::sanitize_position( $slide['position'] ) : '';

		$embed      = ! $template && ! empty( $slide['embed'] ) ? WP_Swiper_Settings::parse_embed( $slide['embed'] ) : false;
		$video      = ! $template && ( $embed || WP_Swiper_Settings::is_video( $slide['id'] ) );
		$poster     = $video ? WP_Swiper_Settings::poster( $slide ) : 0;
		$own_poster = $video && ! empty( $slide['poster'] ) && $poster === (int) $slide['poster'] ? $poster : 0;
		$thumb      = '{{thumb}}';
		// The image in the Image position window (larger than the thumbnail).
		$full       = '{{full}}';
		$title      = '{{title}}';
		$badge      = '{{badge}}';
		// A video's thumbnail without a poster of its own (admin.js shows it again
		// when the poster is removed): a YouTube / Vimeo thumbnail, the video's
		// featured image, or none.
		$default = '{{thumb}}';

		if ( ! $template ) {
			$badge   = __( 'Video', 'wp-swiper' );
			$default = '';
			if ( $embed ) {
				$meta    = WP_Swiper_Settings::embed_meta( $slide['embed'] );
				$own     = WP_Swiper_Settings::embed_thumb( $slide['embed'] );
				$default = $own['small'];
				$title   = empty( $meta['title'] ) ? $slide['embed'] : $meta['title'];
				$badge   = 'youtube' === $embed['provider'] ? 'YouTube' : 'Vimeo';
			} elseif ( $video ) {
				$featured = (int) get_post_thumbnail_id( $slide['id'] );
				$default  = $featured ? (string) wp_get_attachment_image_url( $featured, 'medium' ) : '';
				// The video's name in the Video page's list of posters.
				$title = get_the_title( $slide['id'] );
				$title = '' !== $title ? $title : wp_basename( (string) get_attached_file( $slide['id'] ) );
			} else {
				$title = '';
			}
			// An image: itself; a video: its own poster, else its default thumbnail.
			if ( ! $video ) {
				$thumb = wp_get_attachment_image_url( $slide['id'], 'medium' );
				$full  = wp_get_attachment_image_url( $slide['id'], 'large' );
			} else {
				$thumb    = $own_poster ? wp_get_attachment_image_url( $own_poster, 'medium' ) : $default;
				$full     = '';
				$position = '';
			}
		}
		?>
		<li class="wp-swiper-slide<?php echo $video ? ' wp-swiper-slide--video' : ''; ?><?php echo $embed ? ' wp-swiper-slide--embed' : ''; ?>" data-title="<?php echo esc_attr( $title ); ?>" data-default-thumb="<?php echo esc_attr( $default ); ?>" data-full="<?php echo esc_attr( (string) $full ); ?>"<?php echo '' !== $position ? ' style="--wp-swiper-thumb-position:' . esc_attr( str_replace( ' ', '% ', $position ) . '%' ) . '"' : ''; ?>>
			<div class="wp-swiper-slide__thumb">
				<img src="<?php echo esc_attr( $thumb ); ?>" alt=""<?php echo $thumb ? '' : ' hidden'; ?>>
				<span class="dashicons dashicons-video-alt3 wp-swiper-slide__video-icon" aria-hidden="true"></span>
				<span class="wp-swiper-slide__badge"><?php echo esc_html( $badge ); ?></span>
				<span class="wp-swiper-slide__tools">
					<?php // An image's own position ("Cover"): its window (admin.js). Shown while the slider crops (.wp-swiper-slides--positions). ?>
					<button type="button" class="wp-swiper-slide__position<?php echo '' !== $position ? ' is-set' : ''; ?>" title="<?php esc_attr_e( 'Image position', 'wp-swiper' ); ?>">
						<?php echo self::position_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file. ?>
					</button>
					<?php // A copy of the slide, everything of it, right after it (admin.js). ?>
					<button type="button" class="wp-swiper-slide__clone" title="<?php esc_attr_e( 'Clone slide', 'wp-swiper' ); ?>" aria-label="<?php esc_attr_e( 'Clone slide', 'wp-swiper' ); ?>">
						<?php echo self::clone_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file. ?>
					</button>
				</span>
				<button type="button" class="wp-swiper-slide__remove" title="<?php esc_attr_e( 'Remove slide', 'wp-swiper' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<input type="hidden" name="wp_swiper_slides[id][]" value="<?php echo esc_attr( $slide['id'] ); ?>">
			<?php // A YouTube / Vimeo slide: its link (id 0); empty for the others. ?>
			<input type="hidden" name="wp_swiper_slides[embed][]" value="<?php echo esc_attr( isset( $slide['embed'] ) ? $slide['embed'] : '' ); ?>">
			<?php // Caption and description (HTML), link and "new tab" ("1" / "0"): edited in the slide's window (admin.js). ?>
			<textarea name="wp_swiper_slides[caption][]" hidden><?php echo esc_textarea( $caption ); ?></textarea>
			<textarea name="wp_swiper_slides[description][]" hidden><?php echo esc_textarea( $description ); ?></textarea>
			<?php // Their own font sizes per device ("20,16,"; empty: the plugin's), set in the Slide info window. ?>
			<input type="hidden" name="wp_swiper_slides[caption_size][]" value="<?php echo esc_attr( isset( $slide['caption_size'] ) ? WP_Swiper_Settings::sanitize_font_sizes( $slide['caption_size'] ) : '' ); ?>">
			<input type="hidden" name="wp_swiper_slides[description_size][]" value="<?php echo esc_attr( isset( $slide['description_size'] ) ? WP_Swiper_Settings::sanitize_font_sizes( $slide['description_size'] ) : '' ); ?>">
			<?php // And font weights ("700"; empty: the tag's or the page's). ?>
			<input type="hidden" name="wp_swiper_slides[caption_weight][]" value="<?php echo esc_attr( isset( $slide['caption_weight'] ) ? WP_Swiper_Settings::sanitize_font_weights( $slide['caption_weight'] ) : '' ); ?>">
			<input type="hidden" name="wp_swiper_slides[description_weight][]" value="<?php echo esc_attr( isset( $slide['description_weight'] ) ? WP_Swiper_Settings::sanitize_font_weights( $slide['description_weight'] ) : '' ); ?>">
			<?php // And colors ("#rrggbb" or "rgba(r,g,b,a)"; empty: the Caption / Description page's). ?>
			<input type="hidden" name="wp_swiper_slides[caption_color][]" value="<?php echo esc_attr( isset( $slide['caption_color'] ) ? WP_Swiper_Settings::sanitize_slide_color( $slide['caption_color'] ) : '' ); ?>">
			<input type="hidden" name="wp_swiper_slides[description_color][]" value="<?php echo esc_attr( isset( $slide['description_color'] ) ? WP_Swiper_Settings::sanitize_slide_color( $slide['description_color'] ) : '' ); ?>">
			<input type="hidden" name="wp_swiper_slides[link][]" value="<?php echo esc_attr( $link ); ?>">
			<input type="hidden" name="wp_swiper_slides[new_tab][]" value="<?php echo empty( $slide['new_tab'] ) ? '0' : '1'; ?>">
			<?php // Opens the slide's info window (caption, description, link). ?>
			<button type="button" class="button button-small wp-swiper-slide__edit"><?php esc_html_e( 'Edit', 'wp-swiper' ); ?></button>
			<?php // What the slide has (admin.js shows them as it changes; a video's link is not used). ?>
			<span class="wp-swiper-slide__marks">
				<span class="wp-swiper-slide__mark" data-mark="caption"<?php echo '' !== $caption ? '' : ' hidden'; ?>><?php esc_html_e( 'Caption', 'wp-swiper' ); ?></span>
				<span class="wp-swiper-slide__mark" data-mark="description"<?php echo '' !== $description ? '' : ' hidden'; ?>><?php esc_html_e( 'Description', 'wp-swiper' ); ?></span>
				<span class="wp-swiper-slide__mark" data-mark="link"<?php echo '' !== $link && ! $video ? '' : ' hidden'; ?>><?php esc_html_e( 'Link', 'wp-swiper' ); ?></span>
				<span class="wp-swiper-slide__mark wp-swiper-slide__mark--position" data-mark="position"<?php echo '' !== $position ? '' : ' hidden'; ?>><?php esc_html_e( 'Position', 'wp-swiper' ); ?></span>
			</span>
			<?php // The image's own position, "x y" in percent; empty: the slider's Image position. ?>
			<input type="hidden" name="wp_swiper_slides[position][]" value="<?php echo esc_attr( $position ); ?>">
			<?php // Video slides: the poster, an image from the media library (set on the Video page). ?>
			<input type="hidden" name="wp_swiper_slides[poster][]" value="<?php echo $own_poster ? (int) $own_poster : ''; ?>">
		</li>
		<?php
	}

	/**
	 * The slides sent by the edit screen, sanitized (callers check the nonce).
	 *
	 * @return array See WP_Swiper_Settings::get_slides().
	 */
	public static function posted_slides() {
		$raw    = isset( $_POST['wp_swiper_slides'] ) ? wp_unslash( $_POST['wp_swiper_slides'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- sanitized per field below; nonce checked by the callers.
		$ids    = isset( $raw['id'] ) ? (array) $raw['id'] : array();
		$slides = array();

		foreach ( $ids as $i => $attachment_id ) {
			// This slide's value of a field: a plain string, else none (a form
			// made by hand could send lists, which nothing here takes).
			$value = function ( $name ) use ( $raw, $i ) {
				return isset( $raw[ $name ] ) && is_array( $raw[ $name ] ) && isset( $raw[ $name ][ $i ] ) && is_scalar( $raw[ $name ][ $i ] ) ? (string) $raw[ $name ][ $i ] : null;
			};
			$attachment_id = is_scalar( $attachment_id ) ? absint( $attachment_id ) : 0;
			$poster        = null !== $value( 'poster' ) ? absint( $value( 'poster' ) ) : 0;
			$caption       = null !== $value( 'caption' ) ? WP_Swiper_Settings::sanitize_caption( $value( 'caption' ) ) : '';
			$description   = null !== $value( 'description' ) ? WP_Swiper_Settings::sanitize_description( $value( 'description' ) ) : '';
			// Their own font sizes per device ("20,16,"), or none: the plugin's.
			$caption_size     = null !== $value( 'caption_size' ) ? WP_Swiper_Settings::sanitize_font_sizes( $value( 'caption_size' ) ) : '';
			$description_size = null !== $value( 'description_size' ) ? WP_Swiper_Settings::sanitize_font_sizes( $value( 'description_size' ) ) : '';
			// And weights ("700"), or none: the tag's or the page's.
			$caption_weight     = null !== $value( 'caption_weight' ) ? WP_Swiper_Settings::sanitize_font_weights( $value( 'caption_weight' ) ) : '';
			$description_weight = null !== $value( 'description_weight' ) ? WP_Swiper_Settings::sanitize_font_weights( $value( 'description_weight' ) ) : '';
			// And colors ("#rrggbb", "rgba(r,g,b,a)"), or none: the Caption / Description page's.
			$caption_color     = null !== $value( 'caption_color' ) ? WP_Swiper_Settings::sanitize_slide_color( $value( 'caption_color' ) ) : '';
			$description_color = null !== $value( 'description_color' ) ? WP_Swiper_Settings::sanitize_slide_color( $value( 'description_color' ) ) : '';

			// A YouTube / Vimeo slide: its link, once it reads as one (anything else
			// there is ignored: the slide is then its media library item, if any).
			$embed = null !== $value( 'embed' ) ? esc_url_raw( trim( $value( 'embed' ) ) ) : '';
			if ( '' !== $embed && WP_Swiper_Settings::parse_embed( $embed ) ) {
				// Its size, thumbnail and title from the service, cached now
				// rather than when a visitor first sees it.
				WP_Swiper_Settings::embed_meta( $embed );
				$slides[] = array(
					'id'      => 0,
					'embed'   => $embed,
					'caption'     => $caption,
					'description' => $description,
					'caption_size'     => $caption_size,
					'description_size' => $description_size,
					'caption_weight'     => $caption_weight,
					'description_weight' => $description_weight,
					'caption_color'      => $caption_color,
					'description_color'  => $description_color,
					'link'        => '',
					'new_tab'     => false,
					'poster'      => $poster && wp_attachment_is_image( $poster ) ? $poster : 0,
					'position'    => '',
				);
				continue;
			}

			if ( ! $attachment_id || ! WP_Swiper_Settings::is_slide_media( $attachment_id ) ) {
				continue;
			}
			$video    = WP_Swiper_Settings::is_video( $attachment_id );
			$poster   = $video ? $poster : 0;
			$slides[] = array(
				'id'          => $attachment_id,
				'caption'     => $caption,
				'description' => $description,
				'caption_size'     => $caption_size,
				'description_size' => $description_size,
				'caption_weight'     => $caption_weight,
				'description_weight' => $description_weight,
				'caption_color'      => $caption_color,
				'description_color'  => $description_color,
				// A video slide has no link (a click on it is the player's).
				'link'        => ! $video && null !== $value( 'link' ) ? esc_url_raw( $value( 'link' ) ) : '',
				'new_tab'     => ! $video && '1' === $value( 'new_tab' ),
				// A video's poster: an image from the media library, or none.
				'poster'      => $poster && wp_attachment_is_image( $poster ) ? $poster : 0,
				// An image's own position ("Cover"), or none: the slider's.
				'position'    => ! $video && null !== $value( 'position' ) ? WP_Swiper_Settings::sanitize_position( $value( 'position' ) ) : '',
			);
		}
		return $slides;
	}
}
