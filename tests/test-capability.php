<?php
/**
 * Administrators only (WP_Swiper_Settings::CAPABILITY): the sliders in the
 * admin, their preview, the WPBakery element, the notices on the pages and
 * What's new are theirs; others do not see WP Swiper at all. Visitors see
 * the sliders on the pages as ever.
 */

class Test_WP_Swiper_Capability extends WP_Swiper_TestCase {

	private function as_role( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	public function test_the_post_type_needs_the_capability() {
		// The plugin's own capabilities (test-roles.php), all of them given to
		// those who manage the site, none to the others while unset.
		$caps = (array) get_post_type_object( WP_Swiper_Settings::POST_TYPE )->cap;
		$ours = wp_list_pluck( WP_Swiper_Roles::actions(), 0 );
		foreach ( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_posts', 'delete_others_posts', 'create_posts', 'read_private_posts' ) as $cap ) {
			$this->assertContains( $caps[ $cap ], $ours, $cap );
		}
		$this->as_role( 'administrator' );
		foreach ( $ours as $cap ) {
			$this->assertTrue( current_user_can( $cap ), $cap );
		}
		$this->as_role( 'editor' );
		foreach ( $ours as $cap ) {
			$this->assertFalse( current_user_can( $cap ), $cap );
		}
	}

	public function test_only_administrators_manage_sliders() {
		$id = $this->slider();
		foreach ( array( 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			$this->as_role( $role );
			// No menu (it needs edit_posts of the type), no slider to edit or delete.
			$this->assertFalse( current_user_can( get_post_type_object( WP_Swiper_Settings::POST_TYPE )->cap->edit_posts ), $role );
			$this->assertFalse( current_user_can( get_post_type_object( WP_Swiper_Settings::POST_TYPE )->cap->create_posts ), $role );
			$this->assertFalse( current_user_can( 'edit_post', $id ), $role );
			$this->assertFalse( current_user_can( 'delete_post', $id ), $role );
		}

		$this->as_role( 'administrator' );
		$this->assertTrue( current_user_can( get_post_type_object( WP_Swiper_Settings::POST_TYPE )->cap->edit_posts ) );
		$this->assertTrue( current_user_can( get_post_type_object( WP_Swiper_Settings::POST_TYPE )->cap->create_posts ) );
		$this->assertTrue( current_user_can( 'edit_post', $id ) );
		$this->assertTrue( current_user_can( 'delete_post', $id ) );
	}

	public function test_an_editor_cannot_save_or_clone() {
		$id = $this->slider( array(), array( 'height' => 100 ) );
		$this->as_role( 'editor' );
		$_POST = array(
			'wp_swiper_slides'                    => array(),
			'wp_swiper_settings'                  => array( 'height' => '700' ),
			WP_Swiper_Admin::NONCE . '_nonce'     => wp_create_nonce( WP_Swiper_Admin::NONCE ),
		);
		WP_Swiper_Admin::save( $id );
		$_POST = array();
		$this->assertSame( 100, WP_Swiper_Settings::get( $id )['height'] );
		$this->assertSame( 0, WP_Swiper_Admin::clone_slider( $id ) );
	}

	public function test_the_wpbakery_element_for_administrators_only() {
		if ( function_exists( 'vc_lean_map' ) ) {
			$this->markTestSkipped( 'WPBakery is loaded.' );
		}
		require_once __DIR__ . '/stubs/wpbakery.php';

		$GLOBALS['wp_swiper_test_vc_mapped'] = array();
		$this->as_role( 'editor' );
		WP_Swiper_WPBakery::map();
		$this->assertSame( array(), $GLOBALS['wp_swiper_test_vc_mapped'] );

		$this->as_role( 'administrator' );
		WP_Swiper_WPBakery::map();
		$this->assertSame( array( WP_Swiper_Render::SHORTCODE ), $GLOBALS['wp_swiper_test_vc_mapped'] );
	}

	public function test_page_notices_for_administrators_only() {
		$draft = $this->slider( null, array(), array( 'post_status' => 'draft' ) );
		$this->as_role( 'editor' );
		$this->assertSame( '', do_shortcode( '[wp_swiper id="' . $draft . '"]' ) );

		$this->as_role( 'administrator' );
		$this->assertStringContainsString( 'wp-swiper-notice', do_shortcode( '[wp_swiper id="' . $draft . '"]' ) );
	}

	public function test_visitors_still_see_the_sliders() {
		$id = $this->slider();
		wp_set_current_user( 0 );
		$this->assertStringContainsString( 'class="wp-swiper', do_shortcode( '[wp_swiper id="' . $id . '"]' ) );
		$this->as_role( 'editor' );
		$this->assertStringContainsString( 'class="wp-swiper', do_shortcode( '[wp_swiper id="' . $id . '"]' ) );
	}

	public function test_whats_new_for_administrators_only() {
		update_option( WP_Swiper_Whats_New::OPTION_VERSION, '1.0.0' );
		WP_Swiper_Whats_New::track_version();

		$this->as_role( 'editor' );
		$this->assertSame( array(), WP_Swiper_Whats_New::entries() );
		$this->as_role( 'administrator' );
		$this->assertArrayHasKey( WP_SWIPER_VERSION, WP_Swiper_Whats_New::entries() );
	}
}
