<?php
/**
 * Small controls of the settings form: the font picker, the color swatch
 * (Pickr), the gradient editor, and the inline SVG icons. Used by
 * WP_Swiper_Admin, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Admin_Controls {

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
	 * A color field, the same everywhere: a swatch (the color over a
	 * checkerboard, so a see-through one shows as such) that opens the picker
	 * (Pickr, admin.js: the color, hue, opacity where it is allowed, the
	 * palette, HEX / RGB / HSL, Cancel and Default). A setting keeps the color
	 * in its hidden input ("#rrggbb", or "rgba(r,g,b,a)" with opacity); in the
	 * Slide info window (color_control()) admin.js keeps it on the field.
	 *
	 * @param array $args {
	 *     @type string $id      The swatch's id (a label points to it); '': none.
	 *     @type string $name    The input's name; '': no input (Slide info).
	 *     @type string $value   The color kept.
	 *     @type string $default What Default goes back to; '': admin.js says.
	 *     @type array  $effect_default Its own Default for some effects (effect => color; admin.js swaps it in).
	 *     @type bool   $alpha   With opacity.
	 *     @type bool   $hidden  Hidden at first (the window's Default).
	 *     @type bool   $compact The small swatch of a field on one row.
	 *     @type string $label   Its name, when no label beside it says it.
	 * }
	 */
	private static function color_pick( $args ) {
		$args  = wp_parse_args(
			$args,
			array(
				'id'             => '',
				'name'           => '',
				'value'          => '',
				'default'        => '',
				'effect_default' => array(),
				'alpha'          => false,
				'hidden'         => false,
				'compact'        => false,
				'label'          => '',
			)
		);
		$label = '' !== $args['label'] ? $args['label'] : __( 'Choose a color', 'wp-swiper' );
		?>
		<span class="wp-swiper-color-pick<?php echo $args['compact'] ? ' wp-swiper-color-pick--compact' : ''; ?>" data-alpha="<?php echo $args['alpha'] ? '1' : '0'; ?>"<?php echo '' !== $args['default'] ? ' data-default="' . esc_attr( $args['default'] ) . '"' : ''; ?><?php echo $args['effect_default'] ? ' data-base-default="' . esc_attr( $args['default'] ) . '" data-effect-default="' . esc_attr( wp_json_encode( $args['effect_default'] ) ) . '"' : ''; ?><?php echo $args['hidden'] ? ' hidden' : ''; ?>>
			<?php if ( '' !== $args['name'] ) : ?>
				<input type="hidden" class="wp-swiper-color-pick__value" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>">
			<?php endif; ?>
			<button type="button" class="wp-swiper-color-pick__swatch"<?php echo $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $label ); ?>"<?php echo '' !== $args['label'] ? ' title="' . esc_attr( $args['label'] ) . '"' : ''; ?>>
				<span class="wp-swiper-color-pick__chip" aria-hidden="true"></span>
			</button>
		</span>
		<?php
	}

	/**
	 * A gradient setting (the photos' Gradient), edited as WoodMart's: its
	 * type and where it starts (or its middle), the gradient as a bar with its
	 * colors' points under it (click: a new one; drag: move it), the chosen
	 * point's color (with opacity) and Delete. admin.js draws it
	 * and keeps the stored value (WP_Swiper_Art::parse_gradient()) in the input.
	 *
	 * @param array  $field The field.
	 * @param string $id    The type select's id (the label's).
	 * @param string $name  The input's name.
	 * @param string $value The gradient kept.
	 */
	private static function gradient_field( $field, $id, $name, $value ) {
		$sides = array(
			'left'   => __( 'Left', 'wp-swiper' ),
			'right'  => __( 'Right', 'wp-swiper' ),
			'top'    => __( 'Top', 'wp-swiper' ),
			'bottom' => __( 'Bottom', 'wp-swiper' ),
		);
		?>
		<div class="wp-swiper-gradient" data-default="<?php echo esc_attr( $field['default'] ); ?>">
			<input type="hidden" class="wp-swiper-gradient__value" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<div class="wp-swiper-gradient__selects">
				<select class="wp-swiper-gradient__type" id="<?php echo esc_attr( $id ); ?>" aria-label="<?php esc_attr_e( 'Gradient type', 'wp-swiper' ); ?>">
					<option value="linear"><?php esc_html_e( 'Linear', 'wp-swiper' ); ?></option>
					<option value="circle"><?php esc_html_e( 'Radial - Circle', 'wp-swiper' ); ?></option>
					<option value="ellipse"><?php esc_html_e( 'Radial - Ellipse', 'wp-swiper' ); ?></option>
				</select>
				<select class="wp-swiper-gradient__direction" aria-label="<?php esc_attr_e( 'Starts at', 'wp-swiper' ); ?>">
					<?php foreach ( $sides as $side => $label ) : ?>
						<option value="<?php echo esc_attr( $side ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="wp-swiper-gradient__x" aria-label="<?php esc_attr_e( 'Middle across', 'wp-swiper' ); ?>" hidden>
					<option value="center"><?php esc_html_e( 'Center', 'wp-swiper' ); ?></option>
					<option value="left"><?php echo esc_html( $sides['left'] ); ?></option>
					<option value="right"><?php echo esc_html( $sides['right'] ); ?></option>
				</select>
				<select class="wp-swiper-gradient__y" aria-label="<?php esc_attr_e( 'Middle down', 'wp-swiper' ); ?>" hidden>
					<option value="center"><?php esc_html_e( 'Center', 'wp-swiper' ); ?></option>
					<option value="top"><?php echo esc_html( $sides['top'] ); ?></option>
					<option value="bottom"><?php echo esc_html( $sides['bottom'] ); ?></option>
				</select>
			</div>
			<?php // The gradient left to right, its colors' points under it (admin.js). ?>
			<div class="wp-swiper-gradient__bar" title="<?php esc_attr_e( 'Click to add a color', 'wp-swiper' ); ?>"><span class="wp-swiper-gradient__fill"></span></div>
			<div class="wp-swiper-gradient__stops" role="listbox" aria-label="<?php esc_attr_e( 'Gradient colors', 'wp-swiper' ); ?>"></div>
			<div class="wp-swiper-gradient__stop">
				<?php
				self::color_pick(
					array(
						'default' => '#000000',
						'alpha'   => true,
						'label'   => __( 'Color', 'wp-swiper' ),
					)
				);
				?>
				<button type="button" class="button-link wp-swiper-gradient__delete"><?php esc_html_e( 'Delete', 'wp-swiper' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * A color setting (color: "#rrggbb"; alpha_color: with opacity).
	 *
	 * @param array  $field   The field.
	 * @param string $id      The swatch's id.
	 * @param string $name    The input's name.
	 * @param string $value   The color kept.
	 * @param bool   $compact On one row with another field: its label as the swatch's name.
	 */
	private static function color_field( $field, $id, $name, $value, $compact ) {
		self::color_pick(
			array(
				'id'             => $id,
				'name'           => $name,
				'value'          => $value,
				'default'        => $field['default'],
				'effect_default' => isset( $field['effect_default'] ) ? $field['effect_default'] : array(),
				'alpha'          => 'alpha_color' === $field['type'],
				'compact'        => $compact,
				'label'          => $compact ? $field['label'] : '',
			)
		);
	}

	/**
	 * In the Slide info window, under a text's editor (after its font rows):
	 * its color, Default (the Caption page's; the Description page's or the
	 * page's text) or Custom: a color field (color_pick(), with opacity; its
	 * Default goes back to that color). No names: admin.js puts what is
	 * applied into the slide's own hidden value of the same name ("#rrggbb"
	 * or "rgba(r,g,b,a)").
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
				<?php
				self::color_pick(
					array(
						'alpha'  => true,
						'hidden' => true,
					)
				);
				?>
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
	public static function svg_icon( $file, $class, $fallback ) {
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
}
