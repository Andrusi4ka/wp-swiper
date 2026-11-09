<?php
/**
 * The settings form: its blocks and fields from the schema
 * (settings-fields.php), the device switch, the Style tiles and every field
 * type, per device or not. Used by WP_Swiper_Admin, so `self::` is that
 * class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Admin_Fields {

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
	 * Desktop / tablet / mobile buttons (the preview's): icons only, so the
	 * bar keeps Update on its row (the user's choice); the name for screen
	 * readers, with its widths on hover (title).
	 *
	 * @param string $class Extra class.
	 */
	public static function render_device_switch( $class ) {
		echo '<div class="wp-swiper-segmented wp-swiper-device-switch ' . esc_attr( $class ) . '" role="group" aria-label="' . esc_attr__( 'Device', 'wp-swiper' ) . '">';
		foreach ( self::devices() as $device => $meta ) {
			printf(
				'<button type="button" class="wp-swiper-segmented__btn" data-device="%1$s" title="%2$s" aria-pressed="%3$s"><span class="dashicons %4$s" aria-hidden="true"></span><span class="screen-reader-text">%5$s</span></button>',
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
			// Another title for some effects (`titles`: effect => title, admin.js).
			printf(
				'<div class="wp-swiper-block" data-block="%s"><h4 class="wp-swiper-subheading"%s>%s</h4>',
				esc_attr( $name ),
				empty( $block['titles'] ) ? '' : ' data-title="' . esc_attr( $block['title'] ) . '" data-titles="' . esc_attr( wp_json_encode( $block['titles'] ) ) . '"',
				esc_html( $block['title'] )
			);
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

	/**
	 * A select's choices as icons (a field's "tiles": assets/images/{tiles}/{choice}.svg),
	 * the select behind them (hidden) keeping the value (admin.js).
	 *
	 * @param array $field   The field.
	 * @param bool  $inherit With an Inherit tile (a responsive field's tablet and mobile).
	 */
	private static function tiles( $field, $inherit ) {
		?>
		<div class="wp-swiper-tiles" role="radiogroup" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
			<?php if ( $inherit ) : ?>
				<button type="button" class="wp-swiper-tiles__tile wp-swiper-tiles__tile--inherit" data-value="" role="radio" aria-checked="false" hidden><?php esc_html_e( 'Inherit', 'wp-swiper' ); ?></button>
			<?php endif; ?>
			<?php foreach ( $field['choices'] as $choice => $choice_label ) : ?>
				<button type="button" class="wp-swiper-tiles__tile" data-value="<?php echo esc_attr( $choice ); ?>" role="radio" aria-checked="false" title="<?php echo esc_attr( $choice_label ); ?>" aria-label="<?php echo esc_attr( $choice_label ); ?>">
					<?php echo self::svg_icon( $field['tiles'] . '/' . $choice . '.svg', 'wp-swiper-tiles__icon', 'dashicons-format-gallery' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file. ?>
				</button>
			<?php endforeach; ?>
			<?php // Choices to come ("soon"): greyed, not to be picked (no data-value). ?>
			<?php foreach ( isset( $field['soon'] ) ? $field['soon'] : array() as $choice => $choice_label ) : ?>
				<?php /* translators: %s: a Style's name. */ $soon_label = sprintf( __( '%s (coming soon)', 'wp-swiper' ), $choice_label ); ?>
				<span class="wp-swiper-tiles__tile wp-swiper-tiles__tile--soon" title="<?php echo esc_attr( $soon_label ); ?>" aria-label="<?php echo esc_attr( $soon_label ); ?>" aria-disabled="true">
					<?php echo self::svg_icon( $field['tiles'] . '/' . $choice . '.svg', 'wp-swiper-tiles__icon', 'dashicons-format-gallery' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG file. ?>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Art's SVG shape in small: its windows as drawn (the file's margins left
	 * out), the main one, where the current photo shows, darker.
	 *
	 * @param array $data WP_Swiper_Svg_Shape::decode().
	 * @return string SVG markup.
	 */
	public static function shape_thumb( $data ) {
		$frame  = WP_Swiper_Svg_Shape::frame( $data, true );
		$ratio  = max( 0.1, (float) $frame['ratio'] );
		$shapes = $frame['shapes'];
		// Drawn in the file's stacking order: the later on top.
		$order = array_keys( $shapes );
		usort(
			$order,
			function ( $a, $b ) use ( $shapes ) {
				return $shapes[ $a ]['z'] - $shapes[ $b ]['z'];
			}
		);
		$paths = '';
		foreach ( $order as $index ) {
			$paths .= sprintf(
				'<path d="%s" fill-rule="%s" class="%s"/>',
				esc_attr( $shapes[ $index ]['d'] ),
				esc_attr( $shapes[ $index ]['rule'] ),
				0 === $index ? 'wp-swiper-shape__main' : 'wp-swiper-shape__window'
			);
		}
		// Numbers of the stage (0–1 each way), drawn at its proportions.
		return sprintf(
			'<svg viewBox="0 0 1 1" preserveAspectRatio="none" style="aspect-ratio:%s" aria-hidden="true" focusable="false">%s</svg>',
			esc_attr( (string) $ratio ),
			$paths
		);
	}

	/**
	 * Art's SVG shape field for a device: the shapes kept (their geometry
	 * only); the button reads a file into the value (admin.js), read on the
	 * server when the preview updates or the slider is saved. Tablet and
	 * mobile without a file of their own take the larger screen's.
	 *
	 * @param string $id     The hidden input's id.
	 * @param string $name   Its name.
	 * @param string $value  The shapes kept.
	 * @param string $device Device.
	 */
	private static function shape_box( $id, $name, $value, $device ) {
		$shape   = WP_Swiper_Svg_Shape::decode( $value );
		$summary = ! $shape && 'desktop' !== $device
			? __( 'As on the larger screen: upload a file for this one to change it.', 'wp-swiper' )
			: WP_Swiper_Svg_Shape::summary( $shape );
		?>
		<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<div class="wp-swiper-shape">
			<span class="wp-swiper-shape__thumb"<?php echo $shape ? '' : ' hidden'; ?>><?php echo $shape ? self::shape_thumb( $shape ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from checked numbers. ?></span>
			<span class="wp-swiper-shape__summary"><?php echo esc_html( $summary ); ?></span>
			<span class="wp-swiper-shape__actions">
				<label class="button wp-swiper-shape__choose">
					<?php echo $shape ? esc_html__( 'Replace SVG', 'wp-swiper' ) : esc_html__( 'Upload SVG', 'wp-swiper' ); ?>
					<input type="file" accept=".svg,image/svg+xml" class="screen-reader-text">
				</label>
				<button type="button" class="button-link wp-swiper-shape__remove"<?php echo $shape ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'wp-swiper' ); ?></button>
			</span>
		</div>
		<?php
	}

	private static function render_field( $key, $field, $value ) {
		$id   = 'wp-swiper-' . $key;
		$name = 'wp_swiper_settings[' . $key . ']';
		?>
		<div class="wp-swiper-field wp-swiper-field--<?php echo esc_attr( $field['type'] ); ?><?php echo empty( $field['compact'] ) ? '' : ' wp-swiper-field--compact'; ?><?php echo empty( $field['segmented'] ) ? '' : ' wp-swiper-field--segmented'; ?><?php echo empty( $field['tiles'] ) ? '' : ' wp-swiper-field--tiles'; ?>" data-field="<?php echo esc_attr( $key ); ?>"<?php self::effects_attr( $field ); ?>>
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
				<?php self::color_field( $field, $id, $name, $value, true ); ?>
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
				<?php // A line of text too (`line`: Text mask's word). ?>
				<?php if ( in_array( $field['type'], array( 'text', 'id', 'line' ), true ) ) : ?>
					<input type="text" class="regular-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>"
						<?php echo isset( $field['maxlength'] ) ? ' maxlength="' . (int) $field['maxlength'] . '"' : ''; ?>
						spellcheck="false">
				<?php elseif ( 'font' === $field['type'] ) : ?>
					<?php // A font name: typed, or picked from those the site names, in groups (WP_Swiper_Fonts; font-picker.js draws the list). ?>
					<div class="wp-swiper-font" data-fonts="<?php echo esc_attr( wp_json_encode( WP_Swiper_Fonts::suggestions() ) ); ?>">
						<input type="text" class="regular-text wp-swiper-font__input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>" maxlength="100" autocomplete="off"
							role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>-list"
							placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>"
							spellcheck="false">
						<button type="button" class="wp-swiper-font__toggle" tabindex="-1" aria-label="<?php esc_attr_e( 'Fonts', 'wp-swiper' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
						<ul class="wp-swiper-font__list" id="<?php echo esc_attr( $id ); ?>-list" role="listbox" aria-label="<?php esc_attr_e( 'Fonts', 'wp-swiper' ); ?>" hidden></ul>
					</div>
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
				<?php elseif ( 'svg_shape' === $field['type'] ) : ?>
					<?php self::shape_box( $id, $name, $value, 'desktop' ); ?>
				<?php elseif ( in_array( $field['type'], array( 'color', 'alpha_color' ), true ) ) : ?>
					<?php self::color_field( $field, $id, $name, $value, false ); ?>
				<?php elseif ( 'gradient' === $field['type'] ) : ?>
					<?php self::gradient_field( $field, $id, $name, $value ); ?>
				<?php elseif ( 'editor' === $field['type'] ) : ?>
					<?php // WordPress's editor (admin.js starts it once the field is in view), as a slide's description's. ?>
					<textarea class="wp-swiper-settings-editor" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="6"><?php echo esc_textarea( $value ); ?></textarea>
				<?php elseif ( 'seed' === $field['type'] ) : ?>
					<?php // The number is the value kept; the button draws a new one (admin.js). ?>
					<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<button type="button" class="button wp-swiper-shuffle">
						<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
						<?php esc_html_e( 'Shuffle', 'wp-swiper' ); ?>
					</button>
				<?php elseif ( 'select' === $field['type'] ) : ?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo empty( $field['tiles'] ) ? '' : ' class="wp-swiper-tiles__select" hidden'; ?>>
						<?php foreach ( $field['choices'] as $choice => $choice_label ) : ?>
							<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php
					// One value for every screen: its choices as icons (no Inherit).
					if ( ! empty( $field['tiles'] ) ) {
						self::tiles( $field, false );
					}
					?>
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

			<div class="wp-swiper-field__row<?php echo empty( $field['range'] ) ? '' : ' wp-swiper-field__row--range'; ?>">
			<?php if ( ! empty( $field['range'] ) ) : ?>
				<?php // One slider for the device shown, left of its number (admin.js syncRange(): its value, or the one it inherits). ?>
				<input type="range" class="wp-swiper-range"
					value="<?php echo esc_attr( $values[ $keys['desktop'] ] ); ?>"
					min="<?php echo esc_attr( $field['range'][0] ); ?>"
					max="<?php echo esc_attr( $field['range'][1] ); ?>"
					step="<?php echo esc_attr( $field['step'] ); ?>"
					aria-label="<?php echo esc_attr( $field['label'] ); ?>">
			<?php endif; ?>
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
				<?php if ( 'svg_shape' === $field['type'] ) : ?>
					<?php // Art's SVG shape: a file per device, the device shown (admin.js shows the box). ?>
					<div class="wp-swiper-devices__input wp-swiper-shape-device" data-device="<?php echo esc_attr( $device ); ?>"<?php echo 'desktop' === $device ? '' : ' hidden'; ?>>
						<?php self::shape_box( 'wp-swiper-' . $storage_key, 'wp_swiper_settings[' . $storage_key . ']', $values[ $storage_key ], $device ); ?>
					</div>
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

			<?php
			// The choices as icons for the device shown; its select (hidden) keeps
			// the value (admin.js). Tablet and mobile: Inherit first, the larger
			// screen's choice marked.
			if ( ! empty( $field['tiles'] ) ) {
				self::tiles( $field, true );
			}
			?>

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
}
