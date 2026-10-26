<?php
/**
 * The colors the slide text editors offer (the "Text color" button of the
 * Caption and Description editors, admin.js): the theme's colors, the
 * user's own (WP Swiper > Settings, one list for all sliders) and black,
 * white and greys. Any other color can still be picked there ("Custom...").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Palette {

	const OPTION = 'wp_swiper_text_colors';
	const GROUP  = 'wp_swiper_settings';
	const PAGE   = 'wp-swiper-settings';
	const MAX    = 24;

	/**
	 * The settings page's hook (add_page()).
	 *
	 * @var string
	 */
	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function add_page() {
		self::$hook = (string) add_submenu_page(
			'edit.php?post_type=' . WP_Swiper_Settings::POST_TYPE,
			__( 'WP Swiper settings', 'wp-swiper' ),
			__( 'Settings', 'wp-swiper' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	public static function enqueue( $hook ) {
		if ( '' === self::$hook || self::$hook !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wp-swiper-palette', WP_SWIPER_URL . 'assets/css/palette.css', array(), wp_swiper_asset_version( 'assets/css/palette.css' ) );
		wp_enqueue_script( 'wp-swiper-palette', WP_SWIPER_URL . 'assets/js/palette.js', array( 'jquery', 'wp-color-picker' ), wp_swiper_asset_version( 'assets/js/palette.js' ), true );
	}

	/**
	 * The user's colors as saved: each a color ("#rrggbb") and a name, the
	 * empty and the repeated ones left out, no more than MAX.
	 *
	 * @param mixed $input Posted rows: [ [ 'color' => ..., 'name' => ... ], ... ].
	 * @return array
	 */
	public static function sanitize( $input ) {
		$clean = array();
		$seen  = array();
		foreach ( is_array( $input ) ? $input : array() as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['color'] ) ) {
				continue;
			}
			$color = self::hex( (string) $row['color'] );
			if ( '' === $color || isset( $seen[ $color ] ) ) {
				continue;
			}
			$seen[ $color ] = true;
			$clean[]        = array(
				'color' => $color,
				'name'  => mb_substr( sanitize_text_field( isset( $row['name'] ) ? (string) $row['name'] : '' ), 0, 40 ),
			);
			if ( count( $clean ) >= self::MAX ) {
				break;
			}
		}
		return $clean;
	}

	/**
	 * A CSS color as "#rrggbb" (lowercase): from "#rgb", "#rrggbb" or
	 * "rgb()"/"rgba()" (its alpha dropped); '' for anything else.
	 *
	 * @param string $value Color.
	 * @return string
	 */
	public static function hex( $value ) {
		$value = strtolower( trim( $value ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $match ) ) {
			$value = '#' . $match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2];
		}
		if ( preg_match( '/^#[0-9a-f]{6}$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*[\d.]+\s*)?\)$/', $value, $match ) ) {
			return sprintf( '#%02x%02x%02x', min( 255, (int) $match[1] ), min( 255, (int) $match[2] ), min( 255, (int) $match[3] ) );
		}
		return '';
	}

	/**
	 * The theme's colors: Woodmart's own (Theme Settings), and any theme's
	 * palette (theme.json or its editor color palette).
	 *
	 * @return array [ [ 'color' => '#rrggbb', 'name' => string ], ... ]
	 */
	public static function theme_colors() {
		$colors = array();

		if ( 'woodmart' === get_template() ) {
			$options = get_option( 'xts-woodmart-options' );
			$names   = array(
				'primary-color'   => __( 'Primary color', 'wp-swiper' ),
				'secondary-color' => __( 'Secondary color', 'wp-swiper' ),
				'link-color'      => __( 'Link color', 'wp-swiper' ),
			);
			foreach ( $names as $key => $name ) {
				$value = is_array( $options ) && isset( $options[ $key ] ) ? $options[ $key ] : '';
				if ( is_array( $value ) ) {
					$value = isset( $value['idle'] ) ? $value['idle'] : '';
				}
				$colors[] = array(
					'color' => self::hex( (string) $value ),
					'name'  => $name,
				);
			}
		}

		if ( function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
			foreach ( is_array( $palette ) ? $palette : array() as $entry ) {
				if ( is_array( $entry ) && isset( $entry['color'] ) ) {
					$colors[] = array(
						'color' => self::hex( (string) $entry['color'] ),
						'name'  => isset( $entry['name'] ) ? sanitize_text_field( (string) $entry['name'] ) : '',
					);
				}
			}
		}

		return array_values(
			array_filter(
				$colors,
				function ( $entry ) {
					return '' !== $entry['color'];
				}
			)
		);
	}

	/**
	 * The user's own colors (Settings).
	 *
	 * @return array [ [ 'color' => '#rrggbb', 'name' => string ], ... ]
	 */
	public static function own_colors() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Black, white and greys, always there.
	 *
	 * @return array
	 */
	public static function basic_colors() {
		return array(
			array( 'color' => '#000000', 'name' => __( 'Black', 'wp-swiper' ) ),
			array( 'color' => '#444444', 'name' => __( 'Dark gray', 'wp-swiper' ) ),
			array( 'color' => '#888888', 'name' => __( 'Gray', 'wp-swiper' ) ),
			array( 'color' => '#cccccc', 'name' => __( 'Light gray', 'wp-swiper' ) ),
			array( 'color' => '#ffffff', 'name' => __( 'White', 'wp-swiper' ) ),
		);
	}

	/**
	 * The editors' palette: the theme's colors, the user's, then the basic
	 * ones; each color once (its first name), a nameless one named by itself.
	 *
	 * @return array [ [ 'color' => '#rrggbb', 'name' => string ], ... ]
	 */
	public static function colors() {
		$all  = array();
		$seen = array();
		foreach ( array_merge( self::theme_colors(), self::own_colors(), self::basic_colors() ) as $entry ) {
			if ( isset( $seen[ $entry['color'] ] ) ) {
				continue;
			}
			$seen[ $entry['color'] ] = true;
			$all[]                   = array(
				'color' => $entry['color'],
				'name'  => '' !== $entry['name'] ? $entry['name'] : $entry['color'],
			);
		}
		return $all;
	}

	/**
	 * The palette as TinyMCE's textcolor takes it (forecolor_map): the color
	 * without "#", then its name, one after the other.
	 *
	 * @return string[]
	 */
	public static function tinymce_map() {
		$map = array();
		foreach ( self::colors() as $entry ) {
			$map[] = strtoupper( substr( $entry['color'], 1 ) );
			$map[] = $entry['name'];
		}
		return $map;
	}

	/**
	 * WP Swiper > Settings: the theme's colors (shown), the user's own (a list
	 * to add to, change and remove from).
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$theme = self::theme_colors();
		$own   = self::own_colors();
		?>
		<div class="wrap wp-swiper-palette">
			<h1><?php esc_html_e( 'WP Swiper settings', 'wp-swiper' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				// Removing them all still sends the option (as empty); before the rows, which take its place.
				printf( '<input type="hidden" name="%s" value="">', esc_attr( self::OPTION ) );
				?>
				<h2><?php esc_html_e( 'Text colors', 'wp-swiper' ); ?></h2>
				<p class="description"><?php esc_html_e( 'The colors the "Text color" button offers in a slide\'s caption and description, in every slider. Any other color can still be picked there with "Custom...".', 'wp-swiper' ); ?></p>

				<h3><?php esc_html_e( 'From the theme', 'wp-swiper' ); ?></h3>
				<?php if ( $theme ) : ?>
					<ul class="wp-swiper-palette__theme">
						<?php foreach ( $theme as $entry ) : ?>
							<li>
								<span class="wp-swiper-palette__swatch" style="background-color: <?php echo esc_attr( $entry['color'] ); ?>"></span>
								<span><?php echo esc_html( '' !== $entry['name'] ? $entry['name'] : $entry['color'] ); ?></span>
								<code><?php echo esc_html( $entry['color'] ); ?></code>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="description"><?php esc_html_e( 'They follow the theme: change them in its settings.', 'wp-swiper' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'The theme has no colors of its own to offer.', 'wp-swiper' ); ?></p>
				<?php endif; ?>

				<h3><?php esc_html_e( 'Your colors', 'wp-swiper' ); ?></h3>
				<table class="wp-swiper-palette__own widefat striped" data-max="<?php echo (int) self::MAX; ?>">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Color', 'wp-swiper' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'wp-swiper' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'wp-swiper' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $own as $index => $entry ) {
							self::row( $index, $entry['color'], $entry['name'] );
						}
						?>
					</tbody>
				</table>
				<p class="wp-swiper-palette__empty"<?php echo $own ? ' hidden' : ''; ?>><?php esc_html_e( 'None yet.', 'wp-swiper' ); ?></p>
				<p>
					<button type="button" class="button wp-swiper-palette__add"><?php esc_html_e( 'Add color', 'wp-swiper' ); ?></button>
				</p>
				<template class="wp-swiper-palette__template">
					<?php self::row( '__i__', '', '' ); ?>
				</template>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * One of the user's colors in the list.
	 *
	 * @param int|string $index Its row ('__i__' in the template palette.js copies).
	 * @param string     $color "#rrggbb".
	 * @param string     $name  Name.
	 */
	private static function row( $index, $color, $name ) {
		$base = self::OPTION . '[' . $index . ']';
		?>
		<tr class="wp-swiper-palette__row">
			<td><input type="text" class="wp-swiper-palette__color" name="<?php echo esc_attr( $base . '[color]' ); ?>" value="<?php echo esc_attr( $color ); ?>" aria-label="<?php esc_attr_e( 'Color', 'wp-swiper' ); ?>"></td>
			<td><input type="text" class="regular-text" name="<?php echo esc_attr( $base . '[name]' ); ?>" value="<?php echo esc_attr( $name ); ?>" maxlength="40" placeholder="<?php esc_attr_e( 'e.g. Brand blue', 'wp-swiper' ); ?>" aria-label="<?php esc_attr_e( 'Name', 'wp-swiper' ); ?>"></td>
			<td><button type="button" class="button-link button-link-delete wp-swiper-palette__remove"><?php esc_html_e( 'Remove', 'wp-swiper' ); ?></button></td>
		</tr>
		<?php
	}
}
