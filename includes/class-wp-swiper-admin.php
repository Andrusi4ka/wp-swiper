<?php
/**
 * Slider edit screen: slides manager, settings and shortcode boxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-swiper-admin-slides.php';
require_once __DIR__ . '/trait-wp-swiper-admin-controls.php';
require_once __DIR__ . '/trait-wp-swiper-admin-fields.php';
require_once __DIR__ . '/trait-wp-swiper-admin-list.php';

class WP_Swiper_Admin {

	use WP_Swiper_Admin_Slides;
	use WP_Swiper_Admin_Controls;
	use WP_Swiper_Admin_Fields;
	use WP_Swiper_Admin_List;

	const NONCE = 'wp_swiper_save';

	// admin-post.php action of the "Clone" link in the sliders list.
	const CLONE_ACTION = 'wp_swiper_clone';

	// The admin's styles, handle => file, loaded in this order (each after the
	// one before): admin.css cut into parts, the rules in the same order.
	const STYLES = array(
		'wp-swiper-admin'            => 'admin.css',
		'wp-swiper-admin-settings'   => 'admin-settings.css',
		'wp-swiper-admin-fields'     => 'admin-fields.css',
		'wp-swiper-admin-slide-info' => 'admin-slide-info.css',
		'wp-swiper-admin-color'      => 'admin-color.css',
		'wp-swiper-admin-position'   => 'admin-position.css',
		'wp-swiper-admin-controls'   => 'admin-controls.css',
	);

	// admin.js's parts, handle => file, its dependencies (admin.js runs them
	// in their order, window.wpSwiperEditor.parts): the slides, the Slide info
	// window, the colors and gradient, the pages and devices, the preview and
	// Undo / Redo.
	const SCRIPTS = array(
		'wp-swiper-admin-slides'     => 'admin-slides.js',
		'wp-swiper-admin-slide-info' => 'admin-slide-info.js',
		'wp-swiper-admin-colors'     => 'admin-colors.js',
		'wp-swiper-admin-pages'      => 'admin-pages.js',
		'wp-swiper-admin-box'        => 'admin-box.js',
		'wp-swiper-admin-frame'      => 'admin-frame.js',
		'wp-swiper-admin-preview'    => 'admin-preview.js',
	);

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

		// admin.css, then its parts in their order: the cascade as one file's.
		$deps = array();
		foreach ( self::STYLES as $handle => $file ) {
			wp_enqueue_style( $handle, WP_SWIPER_URL . 'assets/css/' . $file, $deps, wp_swiper_asset_version( 'assets/css/' . $file ) );
			$deps = array( $handle );
		}

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			// The slide descriptions' editor (admin.js starts it in its window, wp.editor).
			wp_enqueue_editor();
			// Pickr 1.9.1 (MIT): every color field's picker (color_pick(), admin.js).
			wp_enqueue_style( 'wp-swiper-pickr', WP_SWIPER_URL . 'assets/vendor/pickr/classic.min.css', array(), '1.9.1' );
			wp_enqueue_script( 'wp-swiper-pickr', WP_SWIPER_URL . 'assets/vendor/pickr/pickr.min.js', array(), '1.9.1', true );
			foreach ( self::SCRIPTS as $handle => $file ) {
				wp_register_script( $handle, WP_SWIPER_URL . 'assets/js/' . $file, array( 'jquery', 'jquery-ui-sortable', 'wp-swiper-pickr' ), wp_swiper_asset_version( 'assets/js/' . $file ), true );
			}
			wp_enqueue_script( 'wp-swiper-admin', WP_SWIPER_URL . 'assets/js/admin.js', array_merge( array( 'jquery', 'jquery-ui-sortable', 'wp-swiper-pickr' ), array_keys( self::SCRIPTS ) ), wp_swiper_asset_version( 'assets/js/admin.js' ), true );
			// A font name's list (Text mask's Font name), on its own: nothing of admin.js in it.
			wp_enqueue_script( 'wp-swiper-font-picker', WP_SWIPER_URL . 'assets/js/font-picker.js', array(), wp_swiper_asset_version( 'assets/js/font-picker.js' ), true );
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
					// Art's SVG shape (admin.js reads the file into the field).
					/* translators: %s: file name. */
					'shapeRead'     => __( '%s: read when the preview updates or the slider is saved.', 'wp-swiper' ),
					'shapeNone'     => __( 'No shape yet: upload an SVG file.', 'wp-swiper' ),
					'shapeInherit'  => __( 'As on the larger screen: upload a file for this one to change it.', 'wp-swiper' ),
					'shapeTooBig'   => __( 'This file is too large (1 MB at most).', 'wp-swiper' ),
					'shapeNotSvg'   => __( 'This is no SVG file.', 'wp-swiper' ),
					'shapeUpload'   => __( 'Upload SVG', 'wp-swiper' ),
					'shapeReplace'  => __( 'Replace SVG', 'wp-swiper' ),
					// A slider saved with the old Description Alignment (center, right):
					// admin.js moves it into each description's paragraphs.
					'legacyDescriptionAlign' => get_post() ? WP_Swiper_Settings::legacy_description_align( WP_Swiper_Settings::get( get_post()->ID ) ) : '',
					// The slide text editors' "Text color" palette (TinyMCE's forecolor_map);
					// the color fields' palette too.
					'textColors'  => WP_Swiper_Palette::tinymce_map(),
					// The color fields' picker (Pickr).
					'colorPicker' => array(
						'ui:dialog'       => __( 'Color picker', 'wp-swiper' ),
						'btn:toggle'      => __( 'Choose a color', 'wp-swiper' ),
						'btn:swatch'      => __( 'Color', 'wp-swiper' ),
						'btn:cancel'      => __( 'Cancel', 'wp-swiper' ),
						'aria:btn:cancel' => __( 'Back to the color it had when opened', 'wp-swiper' ),
						'aria:input'      => __( 'Color value', 'wp-swiper' ),
						'aria:palette'    => __( 'Color and brightness', 'wp-swiper' ),
						'aria:hue'        => __( 'Hue', 'wp-swiper' ),
						'aria:opacity'    => __( 'Opacity', 'wp-swiper' ),
						'default'         => __( 'Default', 'wp-swiper' ),
						'defaultLabel'    => __( 'Back to the default color', 'wp-swiper' ),
						// The eyedropper, where the browser has one (admin-colors.js).
						'eyedropper'      => __( 'Pick a color from the screen', 'wp-swiper' ),
					),
					'singleSlide' => WP_Swiper_Settings::SINGLE_SLIDE_EFFECTS,
					// Effects that can be their block's background ("Use as background").
					'backgroundEffects' => WP_Swiper_Settings::BACKGROUND_EFFECTS,
					// Effects with a main image that can zoom ("Zoom the main image").
					'zoomEffects' => WP_Swiper_Settings::ZOOM_EFFECTS,
					// Effects whose photos can have a shadow ("Image shadow").
					'shadowEffects' => WP_Swiper_Settings::SHADOW_EFFECTS,
					'shadowBackgroundEffects' => WP_Swiper_Settings::SHADOW_BACKGROUND_EFFECTS,
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
					// The preview at a screen size of one's own whose width takes
					// another device's settings (admin.js; WP_Swiper_Preview::device_at()).
					/* translators: 1: screen width in px, 2: a device's name (Tablet, Desktop). */
					'screenNote'  => __( 'The screen is %1$spx wide: the slider takes the %2$s settings, as it does on the site.', 'wp-swiper' ),
					'deviceNames' => array_map(
						function ( $device ) {
							return $device[0];
						},
						self::devices()
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

	/**
	 * Under the settings menu: what the chosen effect can do, a few points
	 * (WP_Swiper_Settings::effect_notes()), and a line for every effect. A box
	 * that folds (admin.js keeps it as it was left); every effect's list is
	 * there, the chosen one shown (admin.js on a new Effect).
	 *
	 * @param string $effect The effect chosen now.
	 */
	public static function effect_notes( $effect ) {
		$notes   = WP_Swiper_Settings::effect_notes();
		$choices = WP_Swiper_Settings::fields()['effect']['choices'];
		echo '<details class="wp-swiper-notes" open>';
		echo '<summary class="wp-swiper-notes__summary">';
		foreach ( $notes['effects'] as $name => $points ) {
			printf(
				'<span class="wp-swiper-notes__title" data-effect="%1$s"%2$s>%3$s</span>',
				esc_attr( $name ),
				$name === $effect ? '' : ' hidden',
				/* translators: %s: effect name, as in the Effect list (Slide, Collage…). */
				esc_html( sprintf( __( 'What %s can do', 'wp-swiper' ), isset( $choices[ $name ] ) ? $choices[ $name ] : $name ) )
			);
		}
		echo '</summary>';
		foreach ( $notes['effects'] as $name => $points ) {
			printf( '<ul class="wp-swiper-notes__list" data-effect="%1$s"%2$s>', esc_attr( $name ), $name === $effect ? '' : ' hidden' );
			foreach ( $points as $point ) {
				echo '<li>' . esc_html( $point ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<p class="wp-swiper-notes__all">' . esc_html( $notes['all'] ) . '</p>';
		echo '</details>';
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

		self::effect_notes( $values['effect'] );

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
				// The poster of each video slide, in a block (a grey card) of its own
				// before the settings' blocks: admin.js lists the video slides of the
				// Slides box here; the poster itself stays in its slide's row.
				echo '<div class="wp-swiper-block" data-block="video_posters"><h4 class="wp-swiper-subheading">' . esc_html__( 'Posters', 'wp-swiper' ) . '</h4>';
				echo '<div class="wp-swiper-field wp-swiper-posters" data-field="video_posters"><div class="wp-swiper-field__head"><span class="wp-swiper-field__label">' . esc_html__( 'Poster images', 'wp-swiper' ) . '</span>';
				self::help_tip( __( 'Shown before a video plays and as its thumbnail. Without one: the video’s featured image in the media library, else its first frame.', 'wp-swiper' ) );
				echo '</div><ul class="wp-swiper-posters__list"></ul></div></div>';
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

		// wp_slash(): update_post_meta() unslashes, so backslashes in captions,
		// descriptions and JSON (SVG shapes) would be lost.
		update_post_meta( $post_id, WP_Swiper_Settings::META_SLIDES, wp_slash( self::posted_slides() ) );

		// Settings.
		$settings = isset( $_POST['wp_swiper_settings'] ) ? (array) wp_unslash( $_POST['wp_swiper_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized against the schema.
		update_post_meta( $post_id, WP_Swiper_Settings::META_SETTINGS, wp_slash( WP_Swiper_Settings::sanitize( $settings ) ) );
	}

}
