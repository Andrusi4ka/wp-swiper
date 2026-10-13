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
					'frameTitle'  => __( 'Select slide images', 'wp-swiper' ),
					'frameButton' => __( 'Add to slider', 'wp-swiper' ),
					'copied'      => __( 'Copied!', 'wp-swiper' ),
					'singleSlide' => WP_Swiper_Settings::SINGLE_SLIDE_EFFECTS,
				)
			);
		}
	}

	public static function render_slides_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		// Images deleted from the media library are skipped (and dropped on save).
		$slides = array_filter(
			WP_Swiper_Settings::get_slides( $post->ID ),
			function ( $slide ) {
				return wp_attachment_is_image( $slide['id'] );
			}
		);
		$shown  = count( $slides );
		?>
		<?php // Only useful once there are slides; admin.js toggles it with the list. ?>
		<p class="description wp-swiper-hint"<?php echo $shown ? '' : ' hidden'; ?>><?php esc_html_e( 'Drag slides to reorder. Caption and link are optional.', 'wp-swiper' ); ?></p>

		<ul class="wp-swiper-slides" id="wp-swiper-slide-list">
			<?php
			foreach ( $slides as $slide ) {
				self::render_slide_item( $slide );
			}
			?>
		</ul>

		<p class="wp-swiper-empty"<?php echo $shown ? ' hidden' : ''; ?>><?php esc_html_e( 'No slides yet.', 'wp-swiper' ); ?></p>

		<p>
			<button type="button" class="button button-primary" id="wp-swiper-add">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add images', 'wp-swiper' ); ?>
			</button>
		</p>

		<script type="text/html" id="tmpl-wp-swiper-slide">
			<?php self::render_slide_item( array( 'id' => '{{id}}', 'caption' => '', 'link' => '' ), '{{thumb}}' ); ?>
		</script>
		<?php
	}

	/**
	 * One slide row. Inputs use parallel arrays so DOM order = saved order.
	 *
	 * @param array       $slide Slide data.
	 * @param string|null $thumb Thumbnail URL override (used by the JS template).
	 */
	private static function render_slide_item( $slide, $thumb = null ) {
		if ( null === $thumb ) {
			$thumb = wp_get_attachment_image_url( $slide['id'], 'medium' );
		}
		?>
		<li class="wp-swiper-slide">
			<div class="wp-swiper-slide__thumb">
				<img src="<?php echo esc_attr( $thumb ); ?>" alt="">
				<button type="button" class="wp-swiper-slide__remove" title="<?php esc_attr_e( 'Remove slide', 'wp-swiper' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<input type="hidden" name="wp_swiper_slides[id][]" value="<?php echo esc_attr( $slide['id'] ); ?>">
			<input type="text" name="wp_swiper_slides[caption][]" value="<?php echo esc_attr( $slide['caption'] ); ?>" placeholder="<?php esc_attr_e( 'Caption', 'wp-swiper' ); ?>">
			<input type="url" name="wp_swiper_slides[link][]" value="<?php echo esc_attr( $slide['link'] ); ?>" placeholder="<?php esc_attr_e( 'Link (https://…)', 'wp-swiper' ); ?>">
			<?php // The checkbox has no name: an unchecked box is not submitted and would shift the parallel arrays. JS mirrors it into the hidden input. ?>
			<label class="wp-swiper-slide__new-tab">
				<input type="checkbox" <?php checked( ! empty( $slide['new_tab'] ) ); ?>>
				<?php esc_html_e( 'Open in a new tab', 'wp-swiper' ); ?>
			</label>
			<input type="hidden" name="wp_swiper_slides[new_tab][]" value="<?php echo empty( $slide['new_tab'] ) ? '0' : '1'; ?>">
		</li>
		<?php
	}

	public static function render_settings_box( $post ) {
		$values = WP_Swiper_Settings::get( $post->ID );
		$fields = WP_Swiper_Settings::fields();

		echo '<div class="wp-swiper-settings">';
		foreach ( WP_Swiper_Settings::sections() as $section => $title ) {
			echo '<fieldset class="wp-swiper-settings__section" data-section="' . esc_attr( $section ) . '"><legend>' . esc_html( $title );
			if ( 'layout' === $section ) {
				// Its fields depend on the effect: "Layout · Slide" (admin.js keeps it current).
				$effects = $fields['effect']['choices'];
				echo ' · <span class="wp-swiper-layout-effect">' . esc_html( $effects[ $values['effect'] ] ) . '</span>';
			}
			echo '</legend>';
			if ( 'controls' === $section ) {
				// admin.js shows it (and greys all but the arrows) for the Thumbnails effect.
				echo '<p class="description wp-swiper-section-note" hidden>' . esc_html__( 'With the Thumbnails effect only the arrows are used: they sit on the strip of thumbnails.', 'wp-swiper' ) . '</p>';
			}
			if ( 'caption' === $section ) {
				// admin.js shows it (and greys the box) while no slide has a caption.
				echo '<p class="description wp-swiper-section-note" hidden>' . esc_html__( 'Add a caption to a slide to use these settings.', 'wp-swiper' ) . '</p>';
			}

			// Consecutive fields sharing a "group" sit side by side in one row.
			$group = null;
			foreach ( $fields as $key => $field ) {
				// Fields drawn inside another one (a unit select) are skipped here.
				if ( $field['section'] !== $section || ! empty( $field['inline_in'] ) ) {
					continue;
				}
				$field_group = isset( $field['group'] ) ? $field['group'] : null;
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

			echo '</fieldset>';
		}
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
		<div class="wp-swiper-field wp-swiper-field--<?php echo esc_attr( $field['type'] ); ?><?php echo empty( $field['compact'] ) ? '' : ' wp-swiper-field--compact'; ?>" data-field="<?php echo esc_attr( $key ); ?>"<?php self::effects_attr( $field ); ?>>
			<?php if ( ! empty( $field['compact'] ) && 'color' === $field['type'] ) : ?>
				<input type="color" class="wp-swiper-color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					title="<?php echo esc_attr( $field['label'] ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
			<?php elseif ( 'checkbox' === $field['type'] ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value ); ?>>
					<?php echo esc_html( $field['label'] ); ?>
				</label>
			<?php else : ?>
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>

				<?php if ( 'text' === $field['type'] ) : ?>
					<input type="text" class="regular-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>"
						spellcheck="false">
				<?php elseif ( 'color' === $field['type'] ) : ?>
					<input type="color" class="wp-swiper-color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>">
				<?php elseif ( 'select' === $field['type'] ) : ?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
						<?php foreach ( $field['choices'] as $choice => $choice_label ) : ?>
							<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						min="<?php echo esc_attr( $field['min'] ); ?>"
						max="<?php echo esc_attr( $field['max'] ); ?>"
						step="<?php echo esc_attr( $field['step'] ); ?>">
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $field['help'] ) ) : ?>
				<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Number (or select) field with a desktop / tablet / mobile switcher. Tablet
	 * and mobile inputs may stay empty: the placeholder shows the inherited
	 * value (a select has an "Inherit" choice instead).
	 *
	 * @param string $key    Field name.
	 * @param array  $field  Field definition.
	 * @param array  $values Saved settings.
	 * @param array  $fields All field definitions (for the unit select).
	 */
	private static function render_responsive_field( $key, $field, $values, $fields ) {
		$devices = array(
			'desktop' => array( __( 'Desktop', 'wp-swiper' ), 'dashicons-desktop' ),
			'tablet'  => array( __( 'Tablet (768–1024px)', 'wp-swiper' ), 'dashicons-tablet' ),
			'mobile'  => array( __( 'Mobile (below 768px)', 'wp-swiper' ), 'dashicons-smartphone' ),
		);
		$keys = WP_Swiper_Settings::device_keys( $key, $field );
		?>
		<div class="wp-swiper-field wp-swiper-field--responsive" data-field="<?php echo esc_attr( $key ); ?>"<?php self::effects_attr( $field ); ?>>
			<div class="wp-swiper-field__head">
				<label for="wp-swiper-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
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

			<?php if ( ! empty( $field['help'] ) ) : ?>
				<p class="description wp-swiper-help"><?php echo esc_html( $field['help'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $field['help_contain'] ) ) : ?>
				<?php // admin.js shows it instead of the help above while Image fit is "Contain". ?>
				<p class="description wp-swiper-help-contain" hidden><?php echo esc_html( $field['help_contain'] ); ?></p>
			<?php endif; ?>
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
		// A page with this slider alone (WP_Swiper_Preview): the saved one, drafts too.
		printf(
			'<p><a class="button" href="%s" target="_blank" rel="noopener">%s</a></p><p class="description">%s</p>',
			esc_url( WP_Swiper_Preview::url( $post->ID ) ),
			esc_html__( 'Preview', 'wp-swiper' ),
			esc_html__( 'Opens the saved slider on a page of its own: save your changes first.', 'wp-swiper' )
		);
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

		// Slides.
		$raw    = isset( $_POST['wp_swiper_slides'] ) ? wp_unslash( $_POST['wp_swiper_slides'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$ids    = isset( $raw['id'] ) ? (array) $raw['id'] : array();
		$slides = array();

		foreach ( $ids as $i => $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
				continue;
			}
			$slides[] = array(
				'id'      => $attachment_id,
				'caption' => isset( $raw['caption'][ $i ] ) ? sanitize_text_field( $raw['caption'][ $i ] ) : '',
				'link'    => isset( $raw['link'][ $i ] ) ? esc_url_raw( $raw['link'][ $i ] ) : '',
				'new_tab' => isset( $raw['new_tab'][ $i ] ) && '1' === $raw['new_tab'][ $i ],
			);
		}
		update_post_meta( $post_id, WP_Swiper_Settings::META_SLIDES, $slides );

		// Settings.
		$settings = isset( $_POST['wp_swiper_settings'] ) ? (array) wp_unslash( $_POST['wp_swiper_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized against the schema.
		update_post_meta( $post_id, WP_Swiper_Settings::META_SETTINGS, WP_Swiper_Settings::sanitize( $settings ) );
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
				// As the slider shows them: images deleted from the library are skipped.
				echo (int) count(
					array_filter(
						WP_Swiper_Settings::get_slides( $post_id ),
						function ( $slide ) {
							return wp_attachment_is_image( $slide['id'] );
						}
					)
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
