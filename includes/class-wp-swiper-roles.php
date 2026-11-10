<?php
/**
 * WP Swiper → Settings (under "Add slider"): what each role may do with the
 * sliders, as checkboxes per action (the user's choice, 2026-11-10). Roles
 * that manage the site (manage_options: Administrators) always may do all of
 * it, and only they open this page. Unset, WP Swiper is theirs only, as it
 * was before.
 *
 * The rights are the plugin's own capabilities, given at run time
 * (user_has_cap) from the option, never written into the roles: deleting the
 * plugin leaves the roles as they were. The slider post type maps its
 * capabilities to them (wp-swiper.php).
 *
 * @package WP_Swiper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Roles {

	const OPTION = 'wp_swiper_roles';
	const GROUP  = 'wp_swiper_roles';
	const PAGE   = 'wp-swiper-settings';

	// The plugin's capabilities (the post type's map to them).
	const EDIT     = 'wp_swiper_edit_sliders';
	const CREATE   = 'wp_swiper_create_sliders';
	const DELETE   = 'wp_swiper_delete_sliders';
	const WPBAKERY = 'wp_swiper_wpbakery';

	public static function init() {
		add_filter( 'user_has_cap', array( __CLASS__, 'grant' ), 10, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $hook ) {
			add_action( $hook . self::OPTION, array( __CLASS__, 'forget' ) );
		}
	}

	/**
	 * The actions a role may be given, in the table's order: key =>
	 * [ capability, name, what it allows ]. Add and clone, Delete and the
	 * WPBakery element: with Edit sliders only (sanitize(); the user's choice).
	 *
	 * @return array
	 */
	public static function actions() {
		return array(
			'edit'     => array( self::EDIT, __( 'Edit sliders', 'wp-swiper' ), __( 'WP Swiper in the menu: open any slider, change it and its preview.', 'wp-swiper' ) ),
			'create'   => array( self::CREATE, __( 'Add and clone', 'wp-swiper' ), __( 'New sliders and copies of them. With Edit sliders only.', 'wp-swiper' ) ),
			'delete'   => array( self::DELETE, __( 'Delete', 'wp-swiper' ), __( 'Sliders to the Trash and for good. With Edit sliders only.', 'wp-swiper' ) ),
			'wpbakery' => array( self::WPBAKERY, __( 'WPBakery element', 'wp-swiper' ), __( 'Put a slider on a page with the WPBakery element (the sliders to choose from). With Edit sliders only. Pages show the sliders to everyone anyway.', 'wp-swiper' ) ),
		);
	}

	/**
	 * Whether a role manages the site (all of WP Swiper, always).
	 *
	 * @param string $role Role name.
	 * @return bool
	 */
	public static function is_full( $role ) {
		$object = get_role( $role );
		return $object && $object->has_cap( WP_Swiper_Settings::CAPABILITY );
	}

	/**
	 * The rights set, as saved (sanitize()): role => [ action => true ].
	 *
	 * @return array
	 */
	public static function rights() {
		// Asked for on every capability check: worked out once (again when saved).
		if ( null === self::$rights ) {
			$saved        = get_option( self::OPTION, array() );
			self::$rights = is_array( $saved ) ? self::sanitize( $saved ) : array();
		}
		return self::$rights;
	}

	/**
	 * @var array|null rights() as worked out.
	 */
	private static $rights = null;

	// The option saved, added or deleted: rights() works them out again.
	public static function forget() {
		self::$rights = null;
	}

	/**
	 * What is kept: the site's roles that do not manage it, the known
	 * actions, on; Add and clone, Delete only with Edit sliders.
	 *
	 * @param mixed $value Posted or saved: role => [ action => anything ].
	 * @return array role => [ action => true ]
	 */
	public static function sanitize( $value ) {
		$clean = array();
		if ( ! is_array( $value ) ) {
			return $clean;
		}
		$roles = wp_roles()->roles;
		foreach ( $value as $role => $actions ) {
			$role = (string) $role;
			if ( ! isset( $roles[ $role ] ) || self::is_full( $role ) || ! is_array( $actions ) ) {
				continue;
			}
			$on = array();
			foreach ( array_keys( self::actions() ) as $action ) {
				if ( ! empty( $actions[ $action ] ) && is_scalar( $actions[ $action ] ) ) {
					$on[ $action ] = true;
				}
			}
			if ( empty( $on['edit'] ) ) {
				unset( $on['create'], $on['delete'], $on['wpbakery'] );
			}
			if ( $on ) {
				$clean[ $role ] = $on;
			}
		}
		return $clean;
	}

	/**
	 * user_has_cap: the plugin's capabilities, from the user's roles. Those
	 * who manage the site have them all.
	 *
	 * @param array   $allcaps The user's capabilities.
	 * @param array   $caps    Asked for.
	 * @param array   $args    The check.
	 * @param WP_User $user    The user.
	 * @return array
	 */
	public static function grant( $allcaps, $caps, $args, $user ) {
		$actions = self::actions();
		if ( ! empty( $allcaps[ WP_Swiper_Settings::CAPABILITY ] ) ) {
			foreach ( $actions as $action ) {
				$allcaps[ $action[0] ] = true;
			}
			return $allcaps;
		}
		$roles = isset( $user->roles ) ? (array) $user->roles : array();
		if ( ! $roles ) {
			return $allcaps;
		}
		$rights = self::rights();
		foreach ( $roles as $role ) {
			if ( empty( $rights[ $role ] ) ) {
				continue;
			}
			foreach ( $rights[ $role ] as $action => $on ) {
				$allcaps[ $actions[ $action ][0] ] = true;
			}
		}
		return $allcaps;
	}

	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * WP Swiper → Settings, right under "Add slider"; site managers only.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . WP_Swiper_Settings::POST_TYPE,
			__( 'WP Swiper settings', 'wp-swiper' ),
			__( 'Settings', 'wp-swiper' ),
			WP_Swiper_Settings::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' ),
			// After "All sliders" and "Add slider".
			2
		);
	}

	/**
	 * Its script (Add and clone, Delete only with Edit) and styles, on it only.
	 *
	 * @param string $hook The admin page.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'wp-swiper-roles', WP_SWIPER_URL . 'assets/css/roles.css', array(), wp_swiper_asset_version( 'assets/css/roles.css' ) );
		wp_enqueue_script( 'wp-swiper-roles', WP_SWIPER_URL . 'assets/js/roles.js', array(), wp_swiper_asset_version( 'assets/js/roles.js' ), true );
	}

	/**
	 * The page: a table of the site's roles and the actions, saved by
	 * WordPress's options.php (its nonce and capability).
	 */
	public static function render() {
		if ( ! current_user_can( WP_Swiper_Settings::CAPABILITY ) ) {
			return;
		}
		$actions = self::actions();
		$rights  = self::rights();
		?>
		<div class="wrap wp-swiper-roles">
			<h1><?php esc_html_e( 'WP Swiper settings', 'wp-swiper' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<h2><?php esc_html_e( 'Roles', 'wp-swiper' ); ?></h2>
				<p class="description"><?php esc_html_e( 'What each role may do with the sliders. Roles that manage the site (Administrators) may do all of it, and only they open this page. Visitors see the sliders on the pages whatever is set here.', 'wp-swiper' ); ?></p>
				<table class="widefat striped wp-swiper-roles__table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Role', 'wp-swiper' ); ?></th>
							<?php foreach ( $actions as $key => $action ) : ?>
								<?php // What it allows on hover only (the user's choice: no text under the names). ?>
								<th scope="col" class="wp-swiper-roles__action" title="<?php echo esc_attr( $action[2] ); ?>"><?php echo esc_html( $action[1] ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( wp_roles()->roles as $role => $data ) : ?>
							<?php $full = self::is_full( $role ); ?>
							<tr data-role="<?php echo esc_attr( $role ); ?>"<?php echo $full ? ' class="is-full"' : ''; ?>>
								<th scope="row">
									<?php echo esc_html( translate_user_role( $data['name'] ) ); ?>
									<?php if ( $full ) : ?>
										<span class="wp-swiper-roles__always"><?php esc_html_e( 'always all', 'wp-swiper' ); ?></span>
									<?php endif; ?>
								</th>
								<?php foreach ( $actions as $key => $action ) : ?>
									<td class="wp-swiper-roles__action">
										<?php
										printf(
											'<input type="checkbox" id="%1$s" data-action="%2$s" aria-label="%3$s"%4$s>',
											esc_attr( 'wp-swiper-role-' . $role . '-' . $key ),
											esc_attr( $key ),
											/* translators: 1: an action (Edit sliders), 2: a role (Editor). */
											esc_attr( sprintf( __( '%1$s: %2$s', 'wp-swiper' ), $action[1], translate_user_role( $data['name'] ) ) ),
											// Administrators: shown on, never sent (no name).
											$full ? ' checked disabled' : ' name="' . esc_attr( self::OPTION . '[' . $role . '][' . $key . ']' ) . '" value="1"' . ( empty( $rights[ $role ][ $key ] ) ? '' : ' checked' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
										);
										?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
