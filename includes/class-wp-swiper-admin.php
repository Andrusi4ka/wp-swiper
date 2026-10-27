<?php
/**
 * Slider edit screen: slides manager, settings and shortcode boxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Admin {

	const NONCE = 'wp_swiper_save';

	// admin-post.php action of the "Clone" link in the sliders list.
	const CLONE_ACTION = 'wp_swiper_clone';

	public static function init() {
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_' . self::CLONE_ACTION, array( __CLASS__, 'handle_clone' ) );
		add_action( 'admin_notices', array( __CLASS__, 'clone_notice' ) );

		add_action( 'add_meta_boxes_' . WP_Swiper_Settings::POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . WP_Swiper_Settings::POST_TYPE, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_filter( 'manage_' . WP_Swiper_Settings::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . WP_Swiper_Settings::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function add_meta_boxes() {
		add_meta_box( 'wp-swiper-slides', __( 'Slides', 'wp-swiper' ), array( __CLASS__, 'render_slides_box' ), null, 'normal', 'high' );
		add_meta_box( 'wp-swiper-settings', __( 'Slider settings', 'wp-swiper' ), array( __CLASS__, 'render_settings_box' ), null, 'normal', 'default' );
		add_meta_box( 'wp-swiper-shortcode', __( 'Shortcode', 'wp-swiper' ), array( __CLASS__, 'render_shortcode_box' ), null, 'side', 'high' );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || WP_Swiper_Settings::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'wp-swiper-admin', WP_SWIPER_URL . 'assets/css/admin.css', array(), wp_swiper_asset_version( 'assets/css/admin.css' ) );

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			// The slide descriptions' editor (admin.js starts it in its window, wp.editor).
			wp_enqueue_editor();
			// Iris: the color picker of a slide's text color (Slide info window).
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-swiper-admin', WP_SWIPER_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'iris' ), wp_swiper_asset_version( 'assets/js/admin.js' ), true );
			wp_localize_script(
				'wp-swiper-admin',
				'wpSwiperAdmin',
				array(
					'frameTitle'  => __( 'Select slide images or videos', 'wp-swiper' ),
					'frameButton' => __( 'Add to slider', 'wp-swiper' ),
					// A video slide's poster (the image shown before it plays).
					'posterTitle'  => __( 'Choose the poster image', 'wp-swiper' ),
					'posterButton' => __( 'Use as poster', 'wp-swiper' ),
					'videoBadge'   => __( 'Video', 'wp-swiper' ),
					'posterChoose' => __( 'Choose', 'wp-swiper' ),
					'posterChange' => __( 'Change', 'wp-swiper' ),
					// The Slide info window's thumbnail: another image or video for the slide.
					'replaceTitle'  => __( 'Replace the slide\'s image or video', 'wp-swiper' ),
					'replaceButton' => __( 'Replace', 'wp-swiper' ),
					'posterRemove' => __( 'Remove', 'wp-swiper' ),
					// An image setting (the continuous scroll's separator).
					'imageTitle'   => __( 'Choose an image', 'wp-swiper' ),
					'imageButton'  => __( 'Use this image', 'wp-swiper' ),
					/* translators: %d: slide number. */
					'slideNumber'  => __( 'Slide %d', 'wp-swiper' ),
					'copied'      => __( 'Copied!', 'wp-swiper' ),
					'unsaved'     => __( 'The changes you made will be lost if you leave this page.', 'wp-swiper' ),
					// Collage Order: Shuffle while the photos were arranged by hand.
					'shuffleLocked' => __( 'The photos were arranged by hand on this device: Reset first to shuffle them.', 'wp-swiper' ),
					'resetNothing'  => __( 'Nothing to reset: this device has no order or sizes of its own.', 'wp-swiper' ),
					/* translators: 1: percent across the image, 2: percent down it. */
					'positionPoint' => __( 'Kept in view: %1$s%% across, %2$s%% down.', 'wp-swiper' ),
					'positionNone'  => __( 'Not set: the slider\'s Image position applies.', 'wp-swiper' ),
					// A slider saved with the old Description Alignment (center, right):
					// admin.js moves it into each description's paragraphs.
					'legacyDescriptionAlign' => get_post() ? WP_Swiper_Settings::legacy_description_align( WP_Swiper_Settings::get( get_post()->ID ) ) : '',
					// Developer mode with an effect that is no slider (Collage, Art).
					'devUnavailable' => __( 'Not available for this effect: it shows the boxes of a slider\'s slides, and this effect has none.', 'wp-swiper' ),
					// The slide text editors' "Text color" palette (TinyMCE's forecolor_map).
					'textColors'  => WP_Swiper_Palette::tinymce_map(),
					'singleSlide' => WP_Swiper_Settings::SINGLE_SLIDE_EFFECTS,
					// Effects that can be their block's background ("Use as background").
					'backgroundEffects' => WP_Swiper_Settings::BACKGROUND_EFFECTS,
					// Effects with a main image that can zoom ("Zoom the main image").
					'zoomEffects' => WP_Swiper_Settings::ZOOM_EFFECTS,
					// Effects whose photos can have a shadow ("Image shadow").
					'shadowEffects' => WP_Swiper_Settings::SHADOW_EFFECTS,
					// The collage's template Styles: their sizes kept apart (collage_cuts).
					'collageTemplates' => WP_Swiper_Settings::COLLAGE_TEMPLATES,
					// What Height measures (admin.js): each slide across a horizontal
					// slider; the whole slider, which its slides share, when vertical.
					'heightLabels' => array(
						'horizontal' => __( 'Image height', 'wp-swiper' ),
						'thumbs'     => __( 'Main image height', 'wp-swiper' ),
						'vertical'   => __( 'Slider height', 'wp-swiper' ),
						// The whole collage (templates, Grid, Random mosaic).
						'collage'    => __( 'Collage height', 'wp-swiper' ),
					),
					// Continuous scroll's Scroll direction, named for the slider's Direction.
					'marqueeDirections' => array(
						'horizontal' => array(
							'normal'  => __( 'Right to left', 'wp-swiper' ),
							'reverse' => __( 'Left to right', 'wp-swiper' ),
						),
						'vertical'   => array(
							'normal'  => __( 'Bottom to top', 'wp-swiper' ),
							'reverse' => __( 'Top to bottom', 'wp-swiper' ),
						),
					),
				)
			);
		}
	}

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
		<ul class="wp-swiper-slides" id="wp-swiper-slide-list" data-legacy-caption-tag="<?php echo esc_attr( WP_Swiper_Settings::legacy_caption_tag( $settings ) ); ?>" data-legacy-caption-size="<?php echo esc_attr( WP_Swiper_Settings::legacy_font_sizes( $settings, 'caption' ) ); ?>" data-legacy-description-size="<?php echo esc_attr( WP_Swiper_Settings::legacy_font_sizes( $settings, 'description' ) ); ?>" data-legacy-caption-weight="<?php echo esc_attr( WP_Swiper_Settings::legacy_font_weights( $settings, 'caption' ) ); ?>" data-legacy-description-weight="<?php echo esc_attr( WP_Swiper_Settings::legacy_font_weights( $settings, 'description' ) ); ?>">
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
		// A slide's caption, description and link: one window for all slides
		// (admin.js fills it from the slide and starts its editors on opening,
		// removes them on closing, so the slides stay plain fields that can be
		// dragged). Its fields have no name: what is applied goes to the
		// slide's own hidden ones.
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
				<div class="wp-swiper-modal__body">
					<div class="wp-swiper-modal__section">
						<h3 class="wp-swiper-modal__label"><label for="wp-swiper-caption-editor"><?php esc_html_e( 'Caption', 'wp-swiper' ); ?></label></h3>
						<textarea id="wp-swiper-caption-editor" rows="3"></textarea>
						<?php // Its font size, weight and color side by side. ?>
						<div class="wp-swiper-modal__text-style">
							<?php self::font_control( 'caption_size' ); ?>
							<?php self::font_control( 'caption_weight' ); ?>
							<?php self::color_control( 'caption_color' ); ?>
						</div>
					</div>
					<div class="wp-swiper-modal__section">
						<h3 class="wp-swiper-modal__label"><label for="wp-swiper-description-editor"><?php esc_html_e( 'Description', 'wp-swiper' ); ?></label></h3>
						<textarea id="wp-swiper-description-editor" rows="10"></textarea>
						<div class="wp-swiper-modal__text-style">
							<?php self::font_control( 'description_size' ); ?>
							<?php self::font_control( 'description_weight' ); ?>
							<?php self::color_control( 'description_color' ); ?>
						</div>
					</div>
					<?php // Not for video slides (a click on one is the player's): admin.js hides it. ?>
					<div class="wp-swiper-modal__section wp-swiper-modal__link">
						<h3 class="wp-swiper-modal__label"><label for="wp-swiper-link-input"><?php esc_html_e( 'Link', 'wp-swiper' ); ?></label></h3>
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
	 * A color setting's arrow back to its default color (as the Slide info
	 * window's): admin.js puts data-default into the color input beside it.
	 *
	 * @param array $field The field.
	 */
	private static function color_reset( $field ) {
		?>
		<button type="button" class="wp-swiper-color-pick__reset wp-swiper-color-reset" data-default="<?php echo esc_attr( $field['default'] ); ?>" title="<?php esc_attr_e( 'Back to the default color', 'wp-swiper' ); ?>" aria-label="<?php esc_attr_e( 'Back to the default color', 'wp-swiper' ); ?>">
			<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
		</button>
		<?php
	}

	/**
	 * In the Slide info window, under a text's editor: its font size, one
	 * field with the desktop / tablet / mobile icons that pick which device's
	 * value it shows (as a responsive setting does; empty: the plugin's 16px
	 * on desktop, the larger screen's on tablet and mobile); or its font
	 * weight, one list for every screen (Default: the tag's or the page's).
	 * No names: admin.js puts what is applied into the slide's own hidden
	 * value of the same name.
	 *
	 * @param string $prop caption_size | caption_weight | description_size | description_weight.
	 */
	private static function font_control( $prop ) {
		$weight  = ( false !== strpos( $prop, '_weight' ) );
		$label   = $weight ? __( 'Font weight', 'wp-swiper' ) : __( 'Font size', 'wp-swiper' );
		$devices = array(
			'desktop' => array( __( 'Desktop', 'wp-swiper' ), 'dashicons-desktop' ),
			'tablet'  => array( __( 'Tablet', 'wp-swiper' ), 'dashicons-tablet' ),
			'mobile'  => array( __( 'Mobile', 'wp-swiper' ), 'dashicons-smartphone' ),
		);
		$weights = array(
			'100' => '100',
			'200' => '200',
			'300' => '300',
			'400' => __( '400 (normal)', 'wp-swiper' ),
			'500' => '500',
			'600' => '600',
			'700' => __( '700 (bold)', 'wp-swiper' ),
			'800' => '800',
			'900' => '900',
		);
		$id      = 'wp-swiper-' . str_replace( '_', '-', $prop ) . '-mode';
		if ( $weight ) {
			?>
			<div class="wp-swiper-modal__size" data-prop="<?php echo esc_attr( $prop ); ?>">
				<div class="wp-swiper-modal__size-head">
					<label class="wp-swiper-modal__size-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
				</div>
				<select id="<?php echo esc_attr( $id ); ?>" class="wp-swiper-modal__weight">
					<option value=""><?php esc_html_e( 'Default', 'wp-swiper' ); ?></option>
					<?php foreach ( $weights as $value => $name ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php
			return;
		}
		?>
		<div class="wp-swiper-modal__size wp-swiper-modal__size--devices" data-prop="<?php echo esc_attr( $prop ); ?>">
			<?php // The device icons at the end of the label's line, as over a responsive setting. ?>
			<div class="wp-swiper-modal__size-head">
				<label class="wp-swiper-modal__size-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
				<span class="wp-swiper-devices" role="group" aria-label="<?php esc_attr_e( 'Device', 'wp-swiper' ); ?>">
					<?php foreach ( $devices as $device => $meta ) : ?>
						<button type="button" class="wp-swiper-devices__btn<?php echo 'desktop' === $device ? ' is-active' : ''; ?>"
							data-device="<?php echo esc_attr( $device ); ?>"
							aria-pressed="<?php echo 'desktop' === $device ? 'true' : 'false'; ?>"
							title="<?php echo esc_attr( $meta[0] ); ?>">
							<span class="dashicons <?php echo esc_attr( $meta[1] ); ?>" aria-hidden="true"></span>
						</button>
					<?php endforeach; ?>
				</span>
			</div>
			<span class="wp-swiper-modal__size-field">
				<?php foreach ( $devices as $device => $meta ) : ?>
					<?php // Desktop's id: the label's. Empty desktop: the plugin's 16px (admin.js puts the inherited one into the others). ?>
					<input type="number" min="8" max="200" step="1" class="wp-swiper-modal__size-input" data-device="<?php echo esc_attr( $device ); ?>"
						<?php echo 'desktop' === $device ? 'id="' . esc_attr( $id ) . '" placeholder="16"' : 'hidden'; ?>
						aria-label="<?php echo esc_attr( $label . ' — ' . $meta[0] ); ?>">
				<?php endforeach; ?>
				<span class="wp-swiper-modal__size-unit">px</span>
			</span>
		</div>
		<?php
	}

	/**
	 * A color picker with its opacity, as a swatch that opens it (Iris: the
	 * color and a palette, then an Opacity slider), and an arrow back to the
	 * default color (admin.js). In the Slide info window (color_control())
	 * and for a setting of type alpha_color.
	 *
	 * @param bool   $hidden Hidden at first (the window's Default).
	 * @param string $id     The swatch's id (a setting's label points to it); '': none.
	 */
	private static function color_pick( $hidden, $id = '' ) {
		?>
		<span class="wp-swiper-color-pick"<?php echo $hidden ? ' hidden' : ''; ?>>
			<button type="button" class="wp-swiper-color-pick__swatch"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> aria-expanded="false" aria-label="<?php esc_attr_e( 'Choose a color', 'wp-swiper' ); ?>">
				<span class="wp-swiper-color-pick__chip" aria-hidden="true"></span>
				<span class="dashicons dashicons-color-picker" aria-hidden="true"></span>
			</button>
			<button type="button" class="wp-swiper-color-pick__reset" title="<?php esc_attr_e( 'Back to the default color', 'wp-swiper' ); ?>" aria-label="<?php esc_attr_e( 'Back to the default color', 'wp-swiper' ); ?>">
				<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
			</button>
			<span class="wp-swiper-color-pick__pop" hidden>
				<?php // Iris goes right after this field (admin.js). ?>
				<input type="text" class="wp-swiper-color-pick__hex" spellcheck="false" aria-label="<?php esc_attr_e( 'Hex color', 'wp-swiper' ); ?>">
				<label class="wp-swiper-color-pick__opacity">
					<span><?php esc_html_e( 'Opacity', 'wp-swiper' ); ?>: <output>100%</output></span>
					<input type="range" min="0" max="100" step="1" value="100">
				</label>
			</span>
		</span>
		<?php
	}

	/**
	 * In the Slide info window, under a text's editor (after its font rows):
	 * its color, Default (the Caption page's; the Description page's or the
	 * page's text) or Custom: a swatch that opens a picker under it (Iris: the
	 * color and a palette, then an Opacity slider) and an arrow back to the
	 * default color. No names: admin.js puts what is applied into the slide's
	 * own hidden value of the same name ("#rrggbb" or "rgba(r,g,b,a)").
	 *
	 * @param string $prop caption_color | description_color.
	 */
	private static function color_control( $prop ) {
		$id = 'wp-swiper-' . str_replace( '_', '-', $prop ) . '-mode';
		?>
		<div class="wp-swiper-modal__size wp-swiper-modal__color" data-prop="<?php echo esc_attr( $prop ); ?>">
			<div class="wp-swiper-modal__size-head">
				<label class="wp-swiper-modal__size-label" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Color', 'wp-swiper' ); ?></label>
			</div>
			<span class="wp-swiper-modal__color-row">
				<select id="<?php echo esc_attr( $id ); ?>" class="wp-swiper-modal__color-mode">
					<option value=""><?php esc_html_e( 'Default', 'wp-swiper' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Custom', 'wp-swiper' ); ?></option>
				</select>
				<?php self::color_pick( true ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * The Image position button's icon (assets/images/position-icon.svg, from
	 * iconoir, MIT): inline, so it takes the button's color (currentColor).
	 *
	 * @return string SVG markup, or a dashicon without the file.
	 */
	private static function position_icon() {
		return self::svg_icon( 'position-icon.svg', 'wp-swiper-slide__position-icon', 'dashicons-location' );
	}

	/**
	 * The Clone slide button's icon (assets/images/clone-icon.svg), inline too.
	 *
	 * @return string SVG markup, or a dashicon without the file.
	 */
	private static function clone_icon() {
		return self::svg_icon( 'clone-icon.svg', 'wp-swiper-slide__clone-icon', 'dashicons-admin-page' );
	}

	/**
	 * One of the plugin's SVG icons (assets/images) inline, so it takes its
	 * button's color (currentColor). Read once; its comments left out.
	 *
	 * @param string $file     File name in assets/images.
	 * @param string $class    The svg's class.
	 * @param string $fallback A dashicon without the file.
	 * @return string
	 */
	private static function svg_icon( $file, $class, $fallback ) {
		static $icons = array();
		if ( ! isset( $icons[ $file ] ) ) {
			$path = WP_SWIPER_DIR . 'assets/images/' . $file;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			$svg            = is_readable( $path ) ? trim( preg_replace( '/<!--.*?-->/s', '', (string) file_get_contents( $path ) ) ) : '';
			$icons[ $file ] = '' !== $svg
				? preg_replace( '/^<svg\b/', '<svg class="' . esc_attr( $class ) . '" aria-hidden="true" focusable="false"', $svg )
				: '<span class="dashicons ' . esc_attr( $fallback ) . '" aria-hidden="true"></span>';
		}
		return $icons[ $file ];
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

	public static function render_settings_box( $post ) {
		$values = WP_Swiper_Settings::get( $post->ID );
		$fields = WP_Swiper_Settings::fields();
		$pages  = WP_Swiper_Settings::pages();
		$blocks = WP_Swiper_Settings::blocks();

		// Fields per place: Advanced's own, then each menu page's.
		$keys = array_fill_keys( array_merge( array( 'advanced' ), array_keys( $pages ) ), array() );
		foreach ( $fields as $key => $field ) {
			// Fields drawn inside another one (a unit select) are skipped here.
			if ( empty( $field['inline_in'] ) ) {
				$keys[ isset( $field['panel'] ) ? $field['panel'] : $field['section'] ][] = $key;
			}
		}

		// Left: Advanced and the menu; next to it the chosen page (admin.js).
		// The fieldsets with a data-section are the ones admin.js looks for.
		echo '<div class="wp-swiper-settings wp-swiper-switches">';
		echo '<div class="wp-swiper-settings__main">';
		echo '<fieldset class="wp-swiper-settings__section" data-section="advanced"><legend>' . esc_html__( 'Advanced', 'wp-swiper' ) . '</legend>';
		self::render_fields( $keys['advanced'], $fields, $values );
		echo '</fieldset>';

		echo '<nav class="wp-swiper-menu" aria-label="' . esc_attr__( 'Slider settings', 'wp-swiper' ) . '">';
		foreach ( $pages as $page => $meta ) {
			printf(
				'<button type="button" class="wp-swiper-menu__item" data-page="%1$s" aria-controls="wp-swiper-page-%1$s" aria-current="%2$s"><span class="dashicons %3$s" aria-hidden="true"></span><span class="wp-swiper-menu__title">%4$s</span></button>',
				esc_attr( $page ),
				'lightbox' === $page ? 'true' : 'false',
				esc_attr( $meta[1] ),
				esc_html( $meta[0] )
			);
		}
		echo '</nav>';

		// At the foot of the column (admin.css), the box's bottom left corner.
		printf(
			'<p class="wp-swiper-demos">%1$s <a href="https://swiperjs.com/demos" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span><span class="dashicons dashicons-external" aria-hidden="true"></span></a></p>',
			esc_html__( 'Want to see more effects?', 'wp-swiper' ),
			esc_html__( 'Swiper demos', 'wp-swiper' ),
			esc_html__( '(opens in a new tab)', 'wp-swiper' )
		);
		echo '</div>';

		echo '<div class="wp-swiper-settings__pane">';
		foreach ( $pages as $page => $meta ) {
			// The section of its fields: Layout's per-effect values are found by it
			// (the Thumbnails page holds Layout fields too); none for Advanced's.
			$section = $fields[ $keys[ $page ][0] ]['section'];
			printf(
				'<fieldset class="wp-swiper-settings__section wp-swiper-page" id="wp-swiper-page-%1$s" data-page="%1$s"%2$s%3$s><legend>%4$s</legend>',
				esc_attr( $page ),
				'advanced' === $section ? '' : ' data-section="' . esc_attr( $section ) . '"',
				// The first page until admin.js shows the one chosen last.
				'lightbox' === $page ? '' : ' hidden',
				esc_html( $meta[0] )
			);
			if ( 'controls' === $page ) {
				// admin.js shows it (and greys the pagination and keyboard) for the Thumbnails effect.
				echo '<p class="description wp-swiper-section-note" hidden>' . esc_html__( 'With the Thumbnails effect the thumbnails switch the slides: pagination and keyboard control are not used, and the arrows sit on the strip of thumbnails.', 'wp-swiper' ) . '</p>';
			}
			if ( 'caption' === $page ) {
				// admin.js shows one of them (and greys the box): no slide has a
				// caption yet, or this slider shows none (can_caption()).
				echo '<p class="description wp-swiper-section-note" data-note="empty" hidden>' . esc_html__( 'Add a caption to a slide to use these settings.', 'wp-swiper' ) . '</p>';
				echo '<p class="description wp-swiper-section-note" data-note="unavailable" hidden>' . esc_html__( 'Captions are not shown when the slider is used as a background.', 'wp-swiper' ) . '</p>';
			}
			if ( 'description' === $page ) {
				// admin.js shows one of them (and greys the box): no slide has a
				// description yet, or this slider shows none (can_describe()).
				echo '<p class="description wp-swiper-section-note" data-note="empty" hidden>' . esc_html__( 'Add a description to a slide (“Edit”) to use these settings.', 'wp-swiper' ) . '</p>';
				echo '<p class="description wp-swiper-section-note" data-note="unavailable" hidden>' . esc_html__( 'Descriptions are not shown with a vertical slider, Continuous scroll, the Collage effect or when the slider is used as a background.', 'wp-swiper' ) . '</p>';
			}
			if ( 'video' === $page ) {
				// The poster of each video slide: admin.js lists the video slides of
				// the Slides box here; the poster itself stays in its slide's row.
				echo '<div class="wp-swiper-field wp-swiper-posters" data-field="video_posters"><div class="wp-swiper-field__head"><span class="wp-swiper-field__label">' . esc_html__( 'Poster images', 'wp-swiper' ) . '</span>';
				self::help_tip( __( 'Shown before a video plays and as its thumbnail. Without one: the video’s featured image in the media library, else its first frame.', 'wp-swiper' ) );
				echo '</div><ul class="wp-swiper-posters__list"></ul></div>';
			}
			if ( isset( $blocks[ $page ] ) ) {
				self::render_blocks( $blocks[ $page ], $keys[ $page ], $fields, $values );
			} else {
				self::render_fields( $keys[ $page ], $fields, $values );
			}
			echo '</fieldset>';
		}
		echo '</div>';

		// The free half: the slider as these settings draw it.
		WP_Swiper_Preview::render_panel( $post );
		echo '</div>';

		// Each effect keeps its own Layout values: admin.js swaps them into the
		// fields when the effect changes and sends them all back on save.
		$layouts = WP_Swiper_Settings::layouts( $values, isset( $values['layouts'] ) ? $values['layouts'] : array() );
		printf(
			'<input type="hidden" id="wp-swiper-layouts" name="wp_swiper_settings[layouts]" value="%s">',
			esc_attr( wp_json_encode( $layouts ) )
		);
	}

	/**
	 * The devices of the per-device values: label, title and icon.
	 *
	 * @return array device => [ label, title, dashicon ].
	 */
	public static function devices() {
		return array(
			'desktop' => array( __( 'Desktop', 'wp-swiper' ), __( 'Desktop (1025px and wider)', 'wp-swiper' ), 'dashicons-desktop' ),
			'tablet'  => array( __( 'Tablet', 'wp-swiper' ), __( 'Tablet (768–1024px)', 'wp-swiper' ), 'dashicons-tablet' ),
			'mobile'  => array( __( 'Mobile', 'wp-swiper' ), __( 'Mobile (below 768px)', 'wp-swiper' ), 'dashicons-smartphone' ),
		);
	}

	/**
	 * Desktop / tablet / mobile buttons with names (the preview's).
	 *
	 * @param string $class Extra class.
	 */
	public static function render_device_switch( $class ) {
		echo '<div class="wp-swiper-segmented wp-swiper-device-switch ' . esc_attr( $class ) . '" role="group" aria-label="' . esc_attr__( 'Device', 'wp-swiper' ) . '">';
		foreach ( self::devices() as $device => $meta ) {
			printf(
				'<button type="button" class="wp-swiper-segmented__btn" data-device="%1$s" title="%2$s" aria-pressed="%3$s"><span class="dashicons %4$s" aria-hidden="true"></span>%5$s</button>',
				esc_attr( $device ),
				esc_attr( $meta[1] ),
				'desktop' === $device ? 'true' : 'false',
				esc_attr( $meta[2] ),
				esc_html( $meta[0] )
			);
		}
		echo '</div>';
	}

	/**
	 * A field's help text as an "i" icon that shows it on hover and focus.
	 *
	 * @param string $text  Help text.
	 * @param string $class Extra class (the "Contain" variant, admin.js).
	 * @param bool   $hide  Hidden at first.
	 */
	private static function help_tip( $text, $class = '', $hide = false ) {
		printf(
			'<span class="wp-swiper-tip%1$s" tabindex="0" role="img" aria-label="%2$s" data-tip="%2$s"%3$s><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></span>',
			$class ? ' ' . esc_attr( $class ) : '',
			esc_attr( $text ),
			$hide ? ' hidden' : ''
		);
	}

	/**
	 * A page's fields in its blocks (WP_Swiper_Settings::blocks()): each with
	 * its title, in the block's order; admin.js hides a block once none of its
	 * fields is shown. Fields in no block come after them.
	 *
	 * @param array    $blocks The page's blocks.
	 * @param string[] $keys   The page's fields.
	 * @param array    $fields All field definitions.
	 * @param array    $values Saved settings.
	 */
	private static function render_blocks( $blocks, $keys, $fields, $values ) {
		foreach ( $blocks as $name => $block ) {
			$in = array_values( array_intersect( $block['fields'], $keys ) );
			if ( ! $in ) {
				continue;
			}
			printf( '<div class="wp-swiper-block" data-block="%s"><h4 class="wp-swiper-subheading">%s</h4>', esc_attr( $name ), esc_html( $block['title'] ) );
			self::render_fields( $in, $fields, $values );
			echo '</div>';
			$keys = array_diff( $keys, $in );
		}
		self::render_fields( array_values( $keys ), $fields, $values );
	}

	/**
	 * Fields one after another; consecutive ones sharing a "group" sit side
	 * by side in one row.
	 *
	 * @param string[] $keys   Field names, in order.
	 * @param array    $fields All field definitions.
	 * @param array    $values Saved settings.
	 */
	private static function render_fields( $keys, $fields, $values ) {
		$group = null;
		foreach ( $keys as $key ) {
			$field       = $fields[ $key ];
			$field_group = isset( $field['group'] ) ? $field['group'] : null;
			if ( $field_group !== $group ) {
				echo $group ? '</div>' : '';
				echo $field_group ? '<div class="wp-swiper-field-group" data-group="' . esc_attr( $field_group ) . '">' : '';
				$group = $field_group;
			}
			if ( ! empty( $field['responsive'] ) && isset( $field['devices'] ) && 'inline' === $field['devices'] ) {
				self::render_inline_devices_field( $key, $field, $values );
			} elseif ( ! empty( $field['responsive'] ) ) {
				self::render_responsive_field( $key, $field, $values, $fields );
			} else {
				self::render_field( $key, $field, $values[ $key ] );
			}
			// Values kept with this field and set by script only (inline_hidden):
			// hidden inputs, one per device, after it.
			foreach ( $fields as $inline_key => $inline ) {
				if ( empty( $inline['inline_hidden'] ) || ! isset( $inline['inline_in'] ) || $key !== $inline['inline_in'] ) {
					continue;
				}
				foreach ( WP_Swiper_Settings::device_keys( $inline_key, $inline ) as $storage_key ) {
					printf( '<input type="hidden" id="wp-swiper-%1$s" name="wp_swiper_settings[%1$s]" value="%2$s">', esc_attr( $storage_key ), esc_attr( $values[ $storage_key ] ) );
				}
			}
		}
		echo $group ? '</div>' : '';
	}

	/**
	 * data-effects attribute of a field that applies to some effects only
	 * (admin.js hides it for the others).
	 *
	 * @param array $field Field definition.
	 */
	private static function effects_attr( $field ) {
		if ( ! empty( $field['effects'] ) ) {
			echo ' data-effects="' . esc_attr( implode( ' ', $field['effects'] ) ) . '"';
		}
		// Art: the Styles a field belongs to (admin.js hides it for the others).
		if ( ! empty( $field['styles'] ) ) {
			echo ' data-styles="' . esc_attr( implode( ' ', $field['styles'] ) ) . '"';
		}
	}

	private static function render_field( $key, $field, $value ) {
		$id   = 'wp-swiper-' . $key;
		$name = 'wp_swiper_settings[' . $key . ']';
		?>
		<div class="wp-swiper-field wp-swiper-field--<?php echo esc_attr( $field['type'] ); ?><?php echo empty( $field['compact'] ) ? '' : ' wp-swiper-field--compact'; ?><?php echo empty( $field['segmented'] ) ? '' : ' wp-swiper-field--segmented'; ?>" data-field="<?php echo esc_attr( $key ); ?>"<?php self::effects_attr( $field ); ?>>
			<?php if ( ! empty( $field['segmented'] ) && 'select' === $field['type'] ) : ?>
				<?php // The select stays the field (locks, saving); the buttons only set it (admin.js). ?>
				<div class="wp-swiper-field__head">
					<span class="wp-swiper-field__label" id="<?php echo esc_attr( $id ); ?>-label"><?php echo esc_html( $field['label'] ); ?></span>
					<?php self::field_tips( $field ); ?>
				</div>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" hidden>
					<?php foreach ( $field['choices'] as $choice => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="wp-swiper-segmented" role="group" aria-labelledby="<?php echo esc_attr( $id ); ?>-label">
					<?php foreach ( $field['choices'] as $choice => $choice_label ) : ?>
						<button type="button" class="wp-swiper-segmented__btn" data-value="<?php echo esc_attr( $choice ); ?>" aria-pressed="<?php echo $value === $choice ? 'true' : 'false'; ?>"><?php echo esc_html( $choice_label ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php elseif ( ! empty( $field['compact'] ) && 'color' === $field['type'] ) : ?>
				<span class="wp-swiper-color-field">
					<input type="color" class="wp-swiper-color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						title="<?php echo esc_attr( $field['label'] ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
					<?php self::color_reset( $field ); ?>
				</span>
			<?php elseif ( 'checkbox' === $field['type'] ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value ); ?>>
					<?php echo esc_html( $field['label'] ); ?>
				</label>
				<?php // Beside the label, not in it: a click on the icon must not toggle the box. ?>
				<?php self::field_tips( $field ); ?>
			<?php else : ?>
				<div class="wp-swiper-field__head">
					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					<?php self::field_tips( $field ); ?>
				</div>

				<?php // Class names or an id: a plain text input. ?>
				<?php if ( in_array( $field['type'], array( 'text', 'id' ), true ) ) : ?>
					<input type="text" class="regular-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>"
						spellcheck="false">
				<?php elseif ( 'image' === $field['type'] ) : ?>
					<?php
					// The ID is the value kept; the buttons pick it from the media library (admin.js).
					$thumb = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : '';
					?>
					<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo $value ? (int) $value : ''; ?>" data-url="<?php echo esc_url( (string) $thumb ); ?>">
					<div class="wp-swiper-picker">
						<span class="wp-swiper-picker__thumb"><?php echo $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : ''; ?></span>
						<button type="button" class="button wp-swiper-picker__choose"><?php echo $thumb ? esc_html__( 'Change', 'wp-swiper' ) : esc_html__( 'Choose', 'wp-swiper' ); ?></button>
						<button type="button" class="button-link wp-swiper-picker__remove"<?php echo $thumb ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'wp-swiper' ); ?></button>
					</div>
				<?php elseif ( 'color' === $field['type'] ) : ?>
					<span class="wp-swiper-color-field">
						<input type="color" class="wp-swiper-color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>">
						<?php self::color_reset( $field ); ?>
					</span>
				<?php elseif ( 'alpha_color' === $field['type'] ) : ?>
					<?php // A color with its opacity, as in the Slide info window: the value kept, the picker (admin.js). ?>
					<span class="wp-swiper-alpha-color" data-default="<?php echo esc_attr( $field['default'] ); ?>">
						<input type="hidden" class="wp-swiper-alpha-color__value" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
						<?php self::color_pick( false, $id ); ?>
					</span>
				<?php elseif ( 'seed' === $field['type'] ) : ?>
					<?php // The number is the value kept; the button draws a new one (admin.js). ?>
					<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<button type="button" class="button wp-swiper-shuffle">
						<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
						<?php esc_html_e( 'Shuffle', 'wp-swiper' ); ?>
					</button>
				<?php elseif ( 'select' === $field['type'] ) : ?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
						<?php foreach ( $field['choices'] as $choice => $choice_label ) : ?>
							<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php elseif ( ! empty( $field['range'] ) ) : ?>
					<?php // The slider has no name: the number is the value sent; admin.js keeps the two in step. ?>
					<div class="wp-swiper-field__row wp-swiper-field__row--range">
						<input type="range" class="wp-swiper-range"
							value="<?php echo esc_attr( $value ); ?>"
							min="<?php echo esc_attr( $field['range'][0] ); ?>"
							max="<?php echo esc_attr( $field['range'][1] ); ?>"
							step="<?php echo esc_attr( $field['step'] ); ?>"
							aria-label="<?php echo esc_attr( $field['label'] ); ?>">
						<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							min="<?php echo esc_attr( $field['min'] ); ?>"
							max="<?php echo esc_attr( $field['max'] ); ?>"
							step="<?php echo esc_attr( $field['step'] ); ?>"<?php echo empty( $field['placeholder'] ) ? '' : ' placeholder="' . esc_attr( $field['placeholder'] ) . '"'; ?>>
					</div>
				<?php else : ?>
					<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						min="<?php echo esc_attr( $field['min'] ); ?>"
						max="<?php echo esc_attr( $field['max'] ); ?>"
						step="<?php echo esc_attr( $field['step'] ); ?>"<?php echo empty( $field['placeholder'] ) ? '' : ' placeholder="' . esc_attr( $field['placeholder'] ) . '"'; ?>>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * A field's help icon, and its "Contain" variant (admin.js shows one of
	 * the two by Image fit).
	 *
	 * @param array $field Field definition.
	 */
	private static function field_tips( $field ) {
		if ( ! empty( $field['help'] ) ) {
			self::help_tip( $field['help'], 'wp-swiper-help' );
		}
		if ( ! empty( $field['help_contain'] ) ) {
			self::help_tip( $field['help_contain'], 'wp-swiper-help-contain', true );
		}
	}

	/**
	 * Number (or select) field with a desktop / tablet / mobile switcher of its
	 * own (small icons by the label: the fields set per device are the ones with
	 * them). Tablet and mobile inputs may stay empty: the placeholder shows the
	 * inherited value (a select has an "Inherit" choice instead).
	 *
	 * @param string $key    Field name.
	 * @param array  $field  Field definition.
	 * @param array  $values Saved settings.
	 * @param array  $fields All field definitions (for the unit select).
	 */
	/**
	 * A number per device side by side ('devices' => 'inline'): Desktop, then
	 * a button that shows Tablet and Mobile beside it (shown from the start
	 * where either has a value of its own). Empty tablet and mobile values
	 * inherit (admin.js shows the inherited one as their placeholder).
	 *
	 * @param string $key    Field name.
	 * @param array  $field  Field definition.
	 * @param array  $values Saved settings.
	 */
	private static function render_inline_devices_field( $key, $field, $values ) {
		$devices = array_map(
			function ( $meta ) {
				return array( $meta[1], $meta[2] );
			},
			self::devices()
		);
		$keys    = WP_Swiper_Settings::device_keys( $key, $field );
		$open    = '' !== (string) $values[ $keys['tablet'] ] || '' !== (string) $values[ $keys['mobile'] ];
		$names   = array(
			'desktop' => __( 'Desktop', 'wp-swiper' ),
			'tablet'  => __( 'Tablet', 'wp-swiper' ),
			'mobile'  => __( 'Mobile', 'wp-swiper' ),
		);
		?>
		<div class="wp-swiper-field wp-swiper-field--responsive wp-swiper-field--inline-devices" data-field="<?php echo esc_attr( $key ); ?>"<?php self::effects_attr( $field ); ?>>
			<div class="wp-swiper-field__head">
				<span class="wp-swiper-field__label"><?php echo esc_html( $field['label'] ); ?></span>
				<?php self::field_tips( $field ); ?>
			</div>
			<div class="wp-swiper-inline-devices<?php echo $open ? ' is-open' : ''; ?>">
				<?php foreach ( $keys as $device => $storage_key ) : ?>
					<div class="wp-swiper-inline-devices__item" data-device="<?php echo esc_attr( $device ); ?>">
						<label for="wp-swiper-<?php echo esc_attr( $storage_key ); ?>" title="<?php echo esc_attr( $devices[ $device ][0] ); ?>">
							<span class="dashicons <?php echo esc_attr( $devices[ $device ][1] ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( $names[ $device ] ); ?>
						</label>
						<span class="wp-swiper-inline-devices__box">
							<input type="number"
								id="wp-swiper-<?php echo esc_attr( $storage_key ); ?>"
								class="wp-swiper-devices__input"
								data-device="<?php echo esc_attr( $device ); ?>"
								name="wp_swiper_settings[<?php echo esc_attr( $storage_key ); ?>]"
								value="<?php echo esc_attr( $values[ $storage_key ] ); ?>"
								min="<?php echo esc_attr( $field['min'] ); ?>"
								max="<?php echo esc_attr( $field['max'] ); ?>"
								step="<?php echo esc_attr( $field['step'] ); ?>"
								aria-label="<?php echo esc_attr( $field['label'] . ' — ' . $devices[ $device ][0] ); ?>">
							<?php if ( ! empty( $field['suffix'] ) ) : ?>
								<span class="wp-swiper-inline-devices__unit" aria-hidden="true"><?php echo esc_html( $field['suffix'] ); ?></span>
							<?php endif; ?>
						</span>
					</div>
					<?php if ( 'desktop' === $device ) : ?>
						<button type="button" class="wp-swiper-inline-devices__toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Tablet and mobile sizes', 'wp-swiper' ); ?>" title="<?php esc_attr_e( 'Tablet and mobile sizes', 'wp-swiper' ); ?>">
							<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function render_responsive_field( $key, $field, $values, $fields ) {
		$devices = array_map(
			function ( $meta ) {
				return array( $meta[1], $meta[2] );
			},
			self::devices()
		);
		$keys    = WP_Swiper_Settings::device_keys( $key, $field );
		?>
		<div class="wp-swiper-field wp-swiper-field--responsive<?php echo empty( $field['tiles'] ) ? '' : ' wp-swiper-field--tiles'; ?>" data-field="<?php echo esc_attr( $key ); ?>"<?php self::effects_attr( $field ); ?>>
			<div class="wp-swiper-field__head">
				<label for="wp-swiper-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<?php self::field_tips( $field ); ?>
				<span class="wp-swiper-devices" role="group" aria-label="<?php esc_attr_e( 'Device', 'wp-swiper' ); ?>">
					<?php foreach ( $devices as $device => $meta ) : ?>
						<button type="button" class="wp-swiper-devices__btn<?php echo 'desktop' === $device ? ' is-active' : ''; ?>"
							data-device="<?php echo esc_attr( $device ); ?>"
							aria-pressed="<?php echo 'desktop' === $device ? 'true' : 'false'; ?>"
							title="<?php echo esc_attr( $meta[0] ); ?>">
							<span class="dashicons <?php echo esc_attr( $meta[1] ); ?>"></span>
						</button>
					<?php endforeach; ?>
				</span>
			</div>

			<div class="wp-swiper-field__row">
			<?php foreach ( $keys as $device => $storage_key ) : ?>
				<?php if ( 'select' === $field['type'] ) : ?>
					<?php // Tablet and mobile start with an empty choice: inherit. ?>
					<select
						id="wp-swiper-<?php echo esc_attr( $storage_key ); ?>"
						class="wp-swiper-devices__input"
						data-device="<?php echo esc_attr( $device ); ?>"
						name="wp_swiper_settings[<?php echo esc_attr( $storage_key ); ?>]"
						aria-label="<?php echo esc_attr( $field['label'] . ' — ' . $devices[ $device ][0] ); ?>"
						<?php echo 'desktop' === $device ? '' : 'hidden'; ?>>
						<?php if ( 'desktop' !== $device ) : ?>
							<option value="" <?php selected( $values[ $storage_key ], '' ); ?>><?php esc_html_e( 'Inherit', 'wp-swiper' ); ?></option>
						<?php endif; ?>
						<?php foreach ( $field['choices'] as $choice => $choice_label ) : ?>
							<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $values[ $storage_key ], $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php continue; ?>
				<?php endif; ?>
				<?php if ( 'order' === $field['type'] ) : ?>
					<?php // The collage's order: photo IDs, set from the preview or by Shuffle (admin.js). ?>
					<input type="hidden"
						id="wp-swiper-<?php echo esc_attr( $storage_key ); ?>"
						class="wp-swiper-devices__input"
						data-device="<?php echo esc_attr( $device ); ?>"
						name="wp_swiper_settings[<?php echo esc_attr( $storage_key ); ?>]"
						value="<?php echo esc_attr( $values[ $storage_key ] ); ?>">
					<?php continue; ?>
				<?php endif; ?>
				<?php if ( 'ratio' === $field['type'] ) : ?>
					<?php // Proportions typed as "4x3"; admin.js shows the inherited one as the placeholder. ?>
					<input type="text"
						id="wp-swiper-<?php echo esc_attr( $storage_key ); ?>"
						class="wp-swiper-devices__input"
						data-device="<?php echo esc_attr( $device ); ?>"
						name="wp_swiper_settings[<?php echo esc_attr( $storage_key ); ?>]"
						value="<?php echo esc_attr( $values[ $storage_key ] ); ?>"
						placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
						inputmode="text" autocomplete="off" spellcheck="false"
						aria-label="<?php echo esc_attr( $field['label'] . ' — ' . $devices[ $device ][0] ); ?>"
						<?php echo 'desktop' === $device ? '' : 'hidden'; ?>>
					<?php continue; ?>
				<?php endif; ?>
				<input type="number"
					id="wp-swiper-<?php echo esc_attr( $storage_key ); ?>"
					class="wp-swiper-devices__input"
					data-device="<?php echo esc_attr( $device ); ?>"
					name="wp_swiper_settings[<?php echo esc_attr( $storage_key ); ?>]"
					value="<?php echo esc_attr( $values[ $storage_key ] ); ?>"
					min="<?php echo esc_attr( $field['min'] ); ?>"
					max="<?php echo esc_attr( $field['max'] ); ?>"
					step="<?php echo esc_attr( $field['step'] ); ?>"
					<?php // What an empty desktop value means (`empty` fields); tablet and mobile get theirs from admin.js. ?>
					<?php if ( 'desktop' === $device && ! empty( $field['placeholder'] ) ) : ?>
						placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
					<?php endif; ?>
					aria-label="<?php echo esc_attr( $field['label'] . ' — ' . $devices[ $device ][0] ); ?>"
						<?php echo 'desktop' === $device ? '' : 'hidden'; ?>>
			<?php endforeach; ?>

			<?php if ( ! empty( $field['tiles'] ) ) : ?>
				<?php
				// The choices as icons (assets/images/{tiles}/{choice}.svg) for the
				// device shown; its select (hidden) keeps the value (admin.js).
				// Tablet and mobile: Inherit first, the larger screen's choice marked.
				?>
				<div class="wp-swiper-tiles" role="radiogroup" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
					<button type="button" class="wp-swiper-tiles__tile wp-swiper-tiles__tile--inherit" data-value="" role="radio" aria-checked="false" hidden><?php esc_html_e( 'Inherit', 'wp-swiper' ); ?></button>
					<?php foreach ( $field['choices'] as $choice => $choice_label ) : ?>
						<button type="button" class="wp-swiper-tiles__tile" data-value="<?php echo esc_attr( $choice ); ?>" role="radio" aria-checked="false" title="<?php echo esc_attr( $choice_label ); ?>" aria-label="<?php echo esc_attr( $choice_label ); ?>">
							<?php echo self::svg_icon( $field['tiles'] . '/' . $choice . '.svg', 'wp-swiper-tiles__icon', 'dashicons-format-gallery' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file. ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( 'order' === $field['type'] ) : ?>
				<?php // For the device shown: a new order at random; back to the larger screen's order and mosaic sizes (desktop: the shuffle, sizes worked out). ?>
				<button type="button" class="button wp-swiper-shuffle">
					<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
					<?php esc_html_e( 'Shuffle', 'wp-swiper' ); ?>
				</button>
				<button type="button" class="button-link wp-swiper-order-reset"><?php esc_html_e( 'Reset', 'wp-swiper' ); ?></button>
				<?php
				// Fields kept inside this one: the shuffle number of the order without
				// one set; the mosaic sizes per device (devices__input: their dot too).
				foreach ( $fields as $inline_key => $inline ) {
					if ( ! isset( $inline['inline_in'] ) || $key !== $inline['inline_in'] ) {
						continue;
					}
					foreach ( WP_Swiper_Settings::device_keys( $inline_key, $inline ) as $inline_device => $inline_storage ) {
						printf(
							'<input type="hidden" id="wp-swiper-%1$s" name="wp_swiper_settings[%1$s]" value="%2$s"%3$s>',
							esc_attr( $inline_storage ),
							esc_attr( $values[ $inline_storage ] ),
							empty( $inline['responsive'] ) ? '' : ' class="wp-swiper-devices__input" data-device="' . esc_attr( $inline_device ) . '"'
						);
					}
				}
				?>
			<?php endif; ?>

			<?php
			// Unit select on the same row: one per device (shown with its value,
			// tablet and mobile may inherit), or one shared by all devices.
			if ( ! empty( $field['unit'] ) && ! empty( $fields[ $field['unit'] ]['responsive'] ) ) :
				$unit = $fields[ $field['unit'] ];
				foreach ( WP_Swiper_Settings::device_keys( $field['unit'], $unit ) as $device => $unit_key ) :
					?>
					<select id="wp-swiper-<?php echo esc_attr( $unit_key ); ?>"
						class="wp-swiper-devices__input wp-swiper-devices__unit"
						data-device="<?php echo esc_attr( $device ); ?>"
						name="wp_swiper_settings[<?php echo esc_attr( $unit_key ); ?>]"
						aria-label="<?php echo esc_attr( $unit['label'] . ' — ' . $devices[ $device ][0] ); ?>"
						<?php echo 'desktop' === $device ? '' : 'hidden'; ?>>
						<?php if ( 'desktop' !== $device ) : ?>
							<option value="" <?php selected( $values[ $unit_key ], '' ); ?>><?php esc_html_e( 'Inherit', 'wp-swiper' ); ?></option>
						<?php endif; ?>
						<?php foreach ( $unit['choices'] as $choice => $choice_label ) : ?>
							<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $values[ $unit_key ], $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php
				endforeach;
			elseif ( ! empty( $field['unit'] ) ) :
				$unit = $fields[ $field['unit'] ];
				?>
				<select id="wp-swiper-<?php echo esc_attr( $field['unit'] ); ?>" name="wp_swiper_settings[<?php echo esc_attr( $field['unit'] ); ?>]" aria-label="<?php echo esc_attr( $unit['label'] ); ?>">
					<?php foreach ( $unit['choices'] as $choice => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $values[ $field['unit'] ], $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			</div>


		</div>
		<?php
	}

	public static function render_shortcode_box( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the slider to get its shortcode.', 'wp-swiper' ) . '</p>';
			return;
		}
		self::render_shortcode_input( $post->ID );
		echo '<p class="description">' . esc_html__( 'Paste it into any page, or use the “WP Swiper” element in WPBakery.', 'wp-swiper' ) . '</p>';
	}

	private static function render_shortcode_input( $post_id ) {
		printf(
			'<input type="text" class="wp-swiper-shortcode" readonly value="%s" onclick="this.select()">',
			esc_attr( sprintf( '[wp_swiper id="%d"]', $post_id ) )
		);
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, WP_Swiper_Settings::META_SLIDES, self::posted_slides() );

		// Settings.
		$settings = isset( $_POST['wp_swiper_settings'] ) ? (array) wp_unslash( $_POST['wp_swiper_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized against the schema.
		update_post_meta( $post_id, WP_Swiper_Settings::META_SETTINGS, WP_Swiper_Settings::sanitize( $settings ) );
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
			$attachment_id = absint( $attachment_id );
			$poster        = isset( $raw['poster'][ $i ] ) ? absint( $raw['poster'][ $i ] ) : 0;
			$caption       = isset( $raw['caption'][ $i ] ) ? WP_Swiper_Settings::sanitize_caption( $raw['caption'][ $i ] ) : '';
			$description   = isset( $raw['description'][ $i ] ) ? WP_Swiper_Settings::sanitize_description( $raw['description'][ $i ] ) : '';
			// Their own font sizes per device ("20,16,"), or none: the plugin's.
			$caption_size     = isset( $raw['caption_size'][ $i ] ) ? WP_Swiper_Settings::sanitize_font_sizes( $raw['caption_size'][ $i ] ) : '';
			$description_size = isset( $raw['description_size'][ $i ] ) ? WP_Swiper_Settings::sanitize_font_sizes( $raw['description_size'][ $i ] ) : '';
			// And weights ("700"), or none: the tag's or the page's.
			$caption_weight     = isset( $raw['caption_weight'][ $i ] ) ? WP_Swiper_Settings::sanitize_font_weights( $raw['caption_weight'][ $i ] ) : '';
			$description_weight = isset( $raw['description_weight'][ $i ] ) ? WP_Swiper_Settings::sanitize_font_weights( $raw['description_weight'][ $i ] ) : '';
			// And colors ("#rrggbb", "rgba(r,g,b,a)"), or none: the Caption / Description page's.
			$caption_color     = isset( $raw['caption_color'][ $i ] ) ? WP_Swiper_Settings::sanitize_slide_color( $raw['caption_color'][ $i ] ) : '';
			$description_color = isset( $raw['description_color'][ $i ] ) ? WP_Swiper_Settings::sanitize_slide_color( $raw['description_color'][ $i ] ) : '';

			// A YouTube / Vimeo slide: its link, once it reads as one (anything else
			// there is ignored: the slide is then its media library item, if any).
			$embed = isset( $raw['embed'][ $i ] ) ? esc_url_raw( trim( $raw['embed'][ $i ] ) ) : '';
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
				'link'        => ! $video && isset( $raw['link'][ $i ] ) ? esc_url_raw( $raw['link'][ $i ] ) : '',
				'new_tab'     => ! $video && isset( $raw['new_tab'][ $i ] ) && '1' === $raw['new_tab'][ $i ],
				// A video's poster: an image from the media library, or none.
				'poster'      => $poster && wp_attachment_is_image( $poster ) ? $poster : 0,
				// An image's own position ("Cover"), or none: the slider's.
				'position'    => ! $video && isset( $raw['position'][ $i ] ) ? WP_Swiper_Settings::sanitize_position( $raw['position'][ $i ] ) : '',
			);
		}
		return $slides;
	}

	/**
	 * Whether the current user may clone this slider: edit it and add sliders.
	 * Not one in the Trash.
	 *
	 * @param WP_Post|null $post Slider.
	 * @return bool
	 */
	public static function can_clone( $post ) {
		if ( ! $post || WP_Swiper_Settings::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return false;
		}
		$type = get_post_type_object( WP_Swiper_Settings::POST_TYPE );
		return current_user_can( 'edit_post', $post->ID ) && current_user_can( $type->cap->create_posts );
	}

	/**
	 * A copy of a slider: its slides and settings, the title "<title> (copy)".
	 * A draft, so it shows nowhere until it is published.
	 *
	 * @param int $post_id Slider ID.
	 * @return int The copy's ID, 0 when not allowed or it failed.
	 */
	public static function clone_slider( $post_id ) {
		$post = get_post( $post_id );
		if ( ! self::can_clone( $post ) ) {
			return 0;
		}

		/* translators: %s: title of the slider cloned. */
		$title = sprintf( __( '%s (copy)', 'wp-swiper' ), $post->post_title );
		$id    = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => WP_Swiper_Settings::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => trim( $title ),
					'post_author' => get_current_user_id(),
					'menu_order'  => $post->menu_order,
				)
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		// As stored (update_post_meta() unslashes: captions keep their backslashes).
		foreach ( array( WP_Swiper_Settings::META_SLIDES, WP_Swiper_Settings::META_SETTINGS ) as $key ) {
			if ( metadata_exists( 'post', $post->ID, $key ) ) {
				update_post_meta( $id, $key, wp_slash( get_post_meta( $post->ID, $key, true ) ) );
			}
		}
		return $id;
	}

	/**
	 * "Clone" under a slider's title in the sliders list, after Quick Edit.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( WP_Swiper_Settings::POST_TYPE !== $post->post_type || ! self::can_clone( $post ) ) {
			return $actions;
		}
		$url  = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::CLONE_ACTION,
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::CLONE_ACTION . '_' . $post->ID
		);
		$link = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			/* translators: %s: slider title. */
			esc_attr( sprintf( __( 'Clone &#8220;%s&#8221;', 'wp-swiper' ), _draft_or_post_title( $post ) ) ),
			esc_html__( 'Clone', 'wp-swiper' )
		);

		$out = array();
		foreach ( $actions as $key => $action ) {
			$out[ $key ] = $action;
			if ( 'inline hide-if-no-js' === $key ) {
				$out['wp_swiper_clone'] = $link;
			}
		}
		// No Quick Edit link: after Edit, or first.
		if ( ! isset( $out['wp_swiper_clone'] ) ) {
			$out = isset( $out['edit'] )
				? array_merge( array( 'edit' => $out['edit'] ), array( 'wp_swiper_clone' => $link ), $out )
				: array( 'wp_swiper_clone' => $link ) + $out;
		}
		return $out;
	}

	/**
	 * admin-post.php: clone the slider, then back to the sliders list.
	 */
	public static function handle_clone() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( self::CLONE_ACTION . '_' . $post_id );

		$id = self::clone_slider( $post_id );
		if ( ! $id ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to clone this slider.', 'wp-swiper' ), '', array( 'response' => 403, 'back_link' => true ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'        => WP_Swiper_Settings::POST_TYPE,
					'wp_swiper_cloned' => $id,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * The sliders list after a clone: a link to the copy.
	 */
	public static function clone_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . WP_Swiper_Settings::POST_TYPE !== $screen->id || empty( $_GET['wp_swiper_cloned'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			return;
		}
		$post = get_post( absint( $_GET['wp_swiper_cloned'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! $post || WP_Swiper_Settings::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Slider cloned as a draft:', 'wp-swiper' ),
			esc_url( get_edit_post_link( $post->ID ) ),
			esc_html( _draft_or_post_title( $post ) )
		);
	}

	public static function columns( $columns ) {
		$date = $columns['date'];
		unset( $columns['date'] );

		$columns['wp_swiper_slides']    = __( 'Slides', 'wp-swiper' );
		$columns['wp_swiper_effect']    = __( 'Effect', 'wp-swiper' );
		$columns['wp_swiper_shortcode'] = __( 'Shortcode', 'wp-swiper' );
		$columns['date']                = $date;

		return $columns;
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'wp_swiper_slides':
				// As the slider shows them: images and videos deleted from the library are skipped.
				echo (int) count(
					array_filter( WP_Swiper_Settings::get_slides( $post_id ), array( 'WP_Swiper_Settings', 'is_slide' ) )
				);
				break;

			case 'wp_swiper_effect':
				$settings = WP_Swiper_Settings::get( $post_id );
				$choices  = WP_Swiper_Settings::fields()['effect']['choices'];
				echo esc_html( $choices[ $settings['effect'] ] );
				break;

			case 'wp_swiper_shortcode':
				self::render_shortcode_input( $post_id );
				break;
		}
	}
}
