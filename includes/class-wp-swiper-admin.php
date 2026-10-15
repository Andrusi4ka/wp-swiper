<?php
/**
 * Slider edit screen: slides manager, settings and shortcode boxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Admin {

	const NONCE = 'wp_swiper_save';

	public static function init() {
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
			wp_enqueue_script( 'wp-swiper-admin', WP_SWIPER_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), wp_swiper_asset_version( 'assets/js/admin.js' ), true );
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
					'posterRemove' => __( 'Remove', 'wp-swiper' ),
					/* translators: %d: slide number. */
					'slideNumber'  => __( 'Slide %d', 'wp-swiper' ),
					'copied'      => __( 'Copied!', 'wp-swiper' ),
					'singleSlide' => WP_Swiper_Settings::SINGLE_SLIDE_EFFECTS,
					// Effects that can be their block's background ("Use as background").
					'backgroundEffects' => WP_Swiper_Settings::BACKGROUND_EFFECTS,
					// Effects with a main image that can zoom ("Zoom the main image").
					'zoomEffects' => WP_Swiper_Settings::ZOOM_EFFECTS,
					// What Height measures (admin.js): each slide across a horizontal
					// slider; the whole slider, which its slides share, when vertical.
					'heightLabels' => array(
						'horizontal' => __( 'Image height', 'wp-swiper' ),
						'thumbs'     => __( 'Main image height', 'wp-swiper' ),
						'vertical'   => __( 'Slider height', 'wp-swiper' ),
						// The whole collage (templates, Grid, Random mosaic).
						'collage'    => __( 'Collage height', 'wp-swiper' ),
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
		<p class="description wp-swiper-hint"<?php echo $shown ? '' : ' hidden'; ?>><?php esc_html_e( 'Drag slides to reorder. Caption and link are optional; a video slide has no link, its poster image is set on the Video page of the settings.', 'wp-swiper' ); ?></p>

		<ul class="wp-swiper-slides" id="wp-swiper-slide-list">
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
	}

	/**
	 * One slide row. Inputs use parallel arrays so DOM order = saved order.
	 * A video slide (.wp-swiper-slide--video): its poster as the thumbnail (a
	 * video icon without one), no link (CSS); its poster is chosen on the Video
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
		$embed      = ! $template && ! empty( $slide['embed'] ) ? WP_Swiper_Settings::parse_embed( $slide['embed'] ) : false;
		$video      = ! $template && ( $embed || WP_Swiper_Settings::is_video( $slide['id'] ) );
		$poster     = $video ? WP_Swiper_Settings::poster( $slide ) : 0;
		$own_poster = $video && ! empty( $slide['poster'] ) && $poster === (int) $slide['poster'] ? $poster : 0;
		$thumb      = '{{thumb}}';
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
			} else {
				$thumb = $own_poster ? wp_get_attachment_image_url( $own_poster, 'medium' ) : $default;
			}
		}
		?>
		<li class="wp-swiper-slide<?php echo $video ? ' wp-swiper-slide--video' : ''; ?><?php echo $embed ? ' wp-swiper-slide--embed' : ''; ?>" data-title="<?php echo esc_attr( $title ); ?>" data-default-thumb="<?php echo esc_attr( $default ); ?>">
			<div class="wp-swiper-slide__thumb">
				<img src="<?php echo esc_attr( $thumb ); ?>" alt=""<?php echo $thumb ? '' : ' hidden'; ?>>
				<span class="dashicons dashicons-video-alt3 wp-swiper-slide__video-icon" aria-hidden="true"></span>
				<span class="wp-swiper-slide__badge"><?php echo esc_html( $badge ); ?></span>
				<button type="button" class="wp-swiper-slide__remove" title="<?php esc_attr_e( 'Remove slide', 'wp-swiper' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<input type="hidden" name="wp_swiper_slides[id][]" value="<?php echo esc_attr( $slide['id'] ); ?>">
			<?php // A YouTube / Vimeo slide: its link (id 0); empty for the others. ?>
			<input type="hidden" name="wp_swiper_slides[embed][]" value="<?php echo esc_attr( isset( $slide['embed'] ) ? $slide['embed'] : '' ); ?>">
			<input type="text" name="wp_swiper_slides[caption][]" value="<?php echo esc_attr( $slide['caption'] ); ?>" placeholder="<?php esc_attr_e( 'Caption', 'wp-swiper' ); ?>">
			<input type="url" name="wp_swiper_slides[link][]" value="<?php echo esc_attr( $slide['link'] ); ?>" placeholder="<?php esc_attr_e( 'Link (https://…)', 'wp-swiper' ); ?>">
			<?php // The checkbox has no name: an unchecked box is not submitted and would shift the parallel arrays. JS mirrors it into the hidden input. ?>
			<label class="wp-swiper-slide__new-tab">
				<input type="checkbox" <?php checked( ! empty( $slide['new_tab'] ) ); ?>>
				<?php esc_html_e( 'Open in a new tab', 'wp-swiper' ); ?>
			</label>
			<input type="hidden" name="wp_swiper_slides[new_tab][]" value="<?php echo empty( $slide['new_tab'] ) ? '0' : '1'; ?>">
			<?php // Video slides: the poster, an image from the media library (set on the Video page). ?>
			<input type="hidden" name="wp_swiper_slides[poster][]" value="<?php echo $own_poster ? (int) $own_poster : ''; ?>">
		</li>
		<?php
	}

	public static function render_settings_box( $post ) {
		$values = WP_Swiper_Settings::get( $post->ID );
		$fields = WP_Swiper_Settings::fields();
		$pages  = WP_Swiper_Settings::pages();

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
				// admin.js shows it (and greys the box) while no slide has a caption.
				echo '<p class="description wp-swiper-section-note" hidden>' . esc_html__( 'Add a caption to a slide to use these settings.', 'wp-swiper' ) . '</p>';
			}
			if ( 'video' === $page ) {
				// The poster of each video slide: admin.js lists the video slides of
				// the Slides box here; the poster itself stays in its slide's row.
				echo '<div class="wp-swiper-field wp-swiper-posters" data-field="video_posters"><div class="wp-swiper-field__head"><span class="wp-swiper-field__label">' . esc_html__( 'Poster images', 'wp-swiper' ) . '</span>';
				self::help_tip( __( 'Shown before a video plays and as its thumbnail. Without one: the video’s featured image in the media library, else its first frame.', 'wp-swiper' ) );
				echo '</div><ul class="wp-swiper-posters__list"></ul></div>';
			}
			self::render_fields( $keys[ $page ], $fields, $values );
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
	 * Fields one after another; consecutive ones sharing a "group" sit side
	 * by side in one row, and a field's "heading" goes over it.
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
			if ( ! empty( $field['heading'] ) ) {
				echo $group ? '</div>' : '';
				$group = null;
				echo '<h4 class="wp-swiper-subheading">' . esc_html( $field['heading'] ) . '</h4>';
			}
			if ( $field_group !== $group ) {
				echo $group ? '</div>' : '';
				echo $field_group ? '<div class="wp-swiper-field-group">' : '';
				$group = $field_group;
			}
			if ( ! empty( $field['responsive'] ) ) {
				self::render_responsive_field( $key, $field, $values, $fields );
			} else {
				self::render_field( $key, $field, $values[ $key ] );
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
				<input type="color" class="wp-swiper-color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					title="<?php echo esc_attr( $field['label'] ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
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
				<?php elseif ( 'color' === $field['type'] ) : ?>
					<input type="color" class="wp-swiper-color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>">
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
							step="<?php echo esc_attr( $field['step'] ); ?>">
					</div>
				<?php else : ?>
					<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						min="<?php echo esc_attr( $field['min'] ); ?>"
						max="<?php echo esc_attr( $field['max'] ); ?>"
						step="<?php echo esc_attr( $field['step'] ); ?>">
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
	private static function render_responsive_field( $key, $field, $values, $fields ) {
		$devices = array_map(
			function ( $meta ) {
				return array( $meta[1], $meta[2] );
			},
			self::devices()
		);
		$keys    = WP_Swiper_Settings::device_keys( $key, $field );
		?>
		<div class="wp-swiper-field wp-swiper-field--responsive" data-field="<?php echo esc_attr( $key ); ?>"<?php self::effects_attr( $field ); ?>>
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

			<?php
			// Unit select on the same row (shared by all devices).
			if ( ! empty( $field['unit'] ) ) :
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
			$caption       = isset( $raw['caption'][ $i ] ) ? sanitize_text_field( $raw['caption'][ $i ] ) : '';

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
					'caption' => $caption,
					'link'    => '',
					'new_tab' => false,
					'poster'  => $poster && wp_attachment_is_image( $poster ) ? $poster : 0,
				);
				continue;
			}

			if ( ! $attachment_id || ! WP_Swiper_Settings::is_slide_media( $attachment_id ) ) {
				continue;
			}
			$video    = WP_Swiper_Settings::is_video( $attachment_id );
			$poster   = $video ? $poster : 0;
			$slides[] = array(
				'id'      => $attachment_id,
				'caption' => $caption,
				// A video slide has no link (a click on it is the player's).
				'link'    => ! $video && isset( $raw['link'][ $i ] ) ? esc_url_raw( $raw['link'][ $i ] ) : '',
				'new_tab' => ! $video && isset( $raw['new_tab'][ $i ] ) && '1' === $raw['new_tab'][ $i ],
				// A video's poster: an image from the media library, or none.
				'poster'  => $poster && wp_attachment_is_image( $poster ) ? $poster : 0,
			);
		}
		return $slides;
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
