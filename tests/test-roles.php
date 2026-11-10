<?php
/**
 * WP Swiper → Settings (WP_Swiper_Roles): what each role may do with the
 * sliders, checkboxes per action. Administrators always all of it, they
 * alone open the page; unset, as before (administrators only). The rights
 * are given at run time, the roles themselves never changed.
 */

class Test_WP_Swiper_Roles extends WP_Swiper_TestCase {

	public function tear_down() {
		delete_option( WP_Swiper_Roles::OPTION );
		parent::tear_down();
	}

	private function as_role( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private function allow( $rights ) {
		update_option( WP_Swiper_Roles::OPTION, $rights );
	}

	private function type_cap( $cap ) {
		return get_post_type_object( WP_Swiper_Settings::POST_TYPE )->cap->$cap;
	}

	public function test_the_post_type_maps_to_the_plugins_capabilities() {
		$caps = (array) get_post_type_object( WP_Swiper_Settings::POST_TYPE )->cap;
		foreach ( array( 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'read_private_posts' ) as $cap ) {
			$this->assertSame( WP_Swiper_Roles::EDIT, $caps[ $cap ], $cap );
		}
		foreach ( array( 'create_posts', 'publish_posts' ) as $cap ) {
			$this->assertSame( WP_Swiper_Roles::CREATE, $caps[ $cap ], $cap );
		}
		foreach ( array( 'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts' ) as $cap ) {
			$this->assertSame( WP_Swiper_Roles::DELETE, $caps[ $cap ], $cap );
		}
	}

	public function test_unset_administrators_only_as_before() {
		$this->assertSame( array(), WP_Swiper_Roles::rights() );
		$this->as_role( 'editor' );
		foreach ( WP_Swiper_Roles::actions() as $action ) {
			$this->assertFalse( current_user_can( $action[0] ), $action[0] );
		}
		$this->as_role( 'administrator' );
		foreach ( WP_Swiper_Roles::actions() as $action ) {
			$this->assertTrue( current_user_can( $action[0] ), $action[0] );
		}
	}

	public function test_only_known_roles_and_actions_the_others_with_edit_only() {
		$clean = WP_Swiper_Roles::sanitize(
			array(
				'editor'        => array( 'edit' => '1', 'create' => '1', 'delete' => '1', 'wpbakery' => '1', 'hack' => '1' ),
				'author'        => array( 'edit' => '1', 'wpbakery' => '1' ),
				// Without Edit sliders: none of the others (the element too).
				'contributor'   => array( 'edit' => array( '1' ), 'create' => '1', 'delete' => '1', 'wpbakery' => '1' ),
				// Always all of it: not kept.
				'administrator' => array( 'edit' => '1' ),
				'no-such-role'  => array( 'edit' => '1' ),
				'subscriber'    => 'edit',
			)
		);
		$this->assertSame(
			array(
				'editor' => array( 'edit' => true, 'create' => true, 'delete' => true, 'wpbakery' => true ),
				'author' => array( 'edit' => true, 'wpbakery' => true ),
			),
			$clean
		);
		$this->assertSame( array(), WP_Swiper_Roles::sanitize( 'editor' ) );
	}

	public function test_edit_only_changes_sliders_but_adds_or_deletes_none() {
		$id = $this->slider( array(), array( 'height' => 100 ) );
		$this->allow( array( 'editor' => array( 'edit' => '1' ) ) );
		$this->as_role( 'editor' );

		$this->assertTrue( current_user_can( $this->type_cap( 'edit_posts' ) ) );
		$this->assertTrue( current_user_can( 'edit_post', $id ) );
		$this->assertFalse( current_user_can( $this->type_cap( 'create_posts' ) ) );
		$this->assertFalse( current_user_can( 'delete_post', $id ) );

		// Saved by them; not cloned.
		$_POST = array(
			'wp_swiper_slides'                => array(),
			'wp_swiper_settings'              => array( 'height' => '700' ),
			WP_Swiper_Admin::NONCE . '_nonce' => wp_create_nonce( WP_Swiper_Admin::NONCE ),
		);
		WP_Swiper_Admin::save( $id );
		$_POST = array();
		$this->assertSame( 700, WP_Swiper_Settings::get( $id )['height'] );
		$this->assertSame( 0, WP_Swiper_Admin::clone_slider( $id ) );
		// Another role is not given it.
		$this->as_role( 'author' );
		$this->assertFalse( current_user_can( 'edit_post', $id ) );
	}

	public function test_add_clone_and_delete_when_given() {
		$id = $this->slider();
		$this->allow( array( 'editor' => array( 'edit' => '1', 'create' => '1', 'delete' => '1' ) ) );
		$this->as_role( 'editor' );
		$this->assertTrue( current_user_can( $this->type_cap( 'create_posts' ) ) );
		$this->assertTrue( current_user_can( 'delete_post', $id ) );
		$this->assertGreaterThan( 0, WP_Swiper_Admin::clone_slider( $id ) );
	}

	public function test_the_roles_themselves_never_change() {
		$this->allow( array( 'editor' => array( 'edit' => '1', 'create' => '1' ) ) );
		$this->as_role( 'editor' );
		$this->assertTrue( current_user_can( WP_Swiper_Roles::EDIT ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( WP_Swiper_Roles::EDIT ) );
		// Saved again: worked out again (not the rights of before).
		$this->allow( array() );
		$this->assertFalse( current_user_can( WP_Swiper_Roles::EDIT ) );
	}

	public function test_the_wpbakery_element_for_the_roles_given_it() {
		// WPBakery itself (not the stand-ins test-capability.php may have loaded).
		$stub = realpath( __DIR__ . '/stubs/wpbakery.php' );
		if ( function_exists( 'vc_lean_map' ) && realpath( ( new ReflectionFunction( 'vc_lean_map' ) )->getFileName() ) !== $stub ) {
			$this->markTestSkipped( 'WPBakery is loaded.' );
		}
		require_once $stub;
		$this->allow(
			array(
				'author'      => array( 'edit' => '1', 'wpbakery' => '1' ),
				'editor'      => array( 'edit' => '1' ),
				// The element alone is not kept (with Edit sliders only).
				'contributor' => array( 'wpbakery' => '1' ),
			)
		);

		$GLOBALS['wp_swiper_test_vc_mapped'] = array();
		foreach ( array( 'editor', 'contributor' ) as $role ) {
			$this->as_role( $role );
			WP_Swiper_WPBakery::map();
			$this->assertSame( array(), $GLOBALS['wp_swiper_test_vc_mapped'], $role );
		}

		$this->as_role( 'author' );
		WP_Swiper_WPBakery::map();
		$this->assertSame( array( WP_Swiper_Render::SHORTCODE ), $GLOBALS['wp_swiper_test_vc_mapped'] );
	}

	public function test_page_notices_and_whats_new_for_those_who_edit() {
		$draft = $this->slider( null, array(), array( 'post_status' => 'draft' ) );
		$this->allow( array( 'editor' => array( 'edit' => '1' ) ) );
		$this->as_role( 'editor' );
		$this->assertStringContainsString( 'wp-swiper-notice', do_shortcode( '[wp_swiper id="' . $draft . '"]' ) );

		update_option( WP_Swiper_Whats_New::OPTION_VERSION, '1.0.0' );
		WP_Swiper_Whats_New::track_version();
		$this->assertArrayHasKey( WP_SWIPER_VERSION, WP_Swiper_Whats_New::entries() );
	}

	public function test_the_settings_page_under_add_slider_for_administrators_only() {
		global $submenu;
		$parent = 'edit.php?post_type=' . WP_Swiper_Settings::POST_TYPE;
		$this->as_role( 'administrator' );
		set_current_screen( 'dashboard' );
		$submenu[ $parent ] = array(
			5  => array( 'All sliders', WP_Swiper_Roles::EDIT, $parent ),
			10 => array( 'Add slider', WP_Swiper_Roles::CREATE, 'post-new.php?post_type=' . WP_Swiper_Settings::POST_TYPE ),
		);
		WP_Swiper_Roles::menu();
		$items = array_values( $submenu[ $parent ] );
		$this->assertSame( 'Add slider', $items[1][0] );
		$this->assertSame( 'Settings', $items[2][0] );
		$this->assertSame( WP_Swiper_Settings::CAPABILITY, $items[2][1] );
		$this->assertSame( WP_Swiper_Roles::PAGE, $items[2][2] );

		// The table: every role, Administrators always all (no names: never sent).
		ob_start();
		WP_Swiper_Roles::render();
		$html = ob_get_clean();
		$this->assertStringContainsString( '<form method="post" action="options.php">', $html );
		$this->assertSame( 1, preg_match( '#name=[\'"]option_page[\'"] value=[\'"]' . WP_Swiper_Roles::GROUP . '[\'"]#', $html ) );
		$this->assertStringContainsString( '<input type="checkbox" id="wp-swiper-role-editor-edit" data-action="edit" aria-label="Edit sliders: Editor" name="' . WP_Swiper_Roles::OPTION . '[editor][edit]" value="1">', $html );
		$this->assertStringContainsString( '<input type="checkbox" id="wp-swiper-role-administrator-edit" data-action="edit" aria-label="Edit sliders: Administrator" checked disabled>', $html );
		$this->assertStringNotContainsString( WP_Swiper_Roles::OPTION . '[administrator]', $html );
		$this->assertSame( 1, preg_match( '#<tr data-role="administrator" class="is-full">#', $html ) );

		$this->as_role( 'editor' );
		ob_start();
		WP_Swiper_Roles::render();
		$this->assertSame( '', trim( ob_get_clean() ) );
		unset( $submenu[ $parent ] );
		set_current_screen( 'front' );
	}

	public function test_deleted_with_the_plugin() {
		$this->assertStringContainsString( "delete_option( 'wp_swiper_roles' );", file_get_contents( WP_SWIPER_DIR . 'uninstall.php' ) );
		$this->assertSame( 'wp_swiper_roles', WP_Swiper_Roles::OPTION );
	}
}
