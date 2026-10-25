<?php
/**
 * WP_Swiper_Whats_New: the readme's Changelog, the versions to show, and
 * when (an update, not a first install; once per user who edits sliders).
 */

class Test_WP_Swiper_Whats_New extends WP_Swiper_TestCase {

	public function tear_down() {
		delete_option( WP_Swiper_Whats_New::OPTION_VERSION );
		delete_option( WP_Swiper_Whats_New::OPTION_FROM );
		parent::tear_down();
	}

	private function as_role( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	public function test_changelog_by_version() {
		$readme = "=== X ===\n\n== Description ==\n\n* Not a change\n\n== Changelog ==\n\n"
			. "= 2.6.0 =\n* One\n* Two that\n  wraps\n\n= v2.5.1 =\n- Three\n\n= 2.5.0 =\n\n"
			. "== Upgrade Notice ==\n\n= 2.6.0 =\n* Not a change either\n";

		$this->assertSame(
			array(
				'2.6.0' => array( 'One', 'Two that wraps' ),
				'2.5.1' => array( 'Three' ),
			),
			WP_Swiper_Whats_New::parse_changelog( $readme )
		);
		$this->assertSame( array(), WP_Swiper_Whats_New::parse_changelog( "=== X ===\n* No changelog" ) );
	}

	public function test_the_plugins_own_readme_has_the_current_version() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		$changelog = WP_Swiper_Whats_New::parse_changelog( file_get_contents( WP_SWIPER_DIR . 'readme.txt' ) );
		$this->assertArrayHasKey( WP_SWIPER_VERSION, $changelog );
	}

	public function test_versions_between() {
		$changelog = array(
			'2.7.0' => array( 'Future' ),
			'2.6.0' => array( 'C' ),
			'2.5.1' => array( 'B' ),
			'2.5.0' => array( 'A' ),
			'2.4.0' => array( 'Old' ),
		);

		$this->assertSame( array( '2.6.0', '2.5.1' ), array_keys( WP_Swiper_Whats_New::between( $changelog, '2.5.0', '2.6.0' ) ) );
		// Not known what came before: the current version only.
		$this->assertSame( array( '2.6.0' => array( 'C' ) ), WP_Swiper_Whats_New::between( $changelog, '', '2.6.0' ) );
		$this->assertSame( array(), WP_Swiper_Whats_New::between( $changelog, '2.6.0', '2.6.0' ) );
	}

	public function test_first_install_shows_nothing() {
		WP_Swiper_Whats_New::track_version();
		$this->assertSame( WP_SWIPER_VERSION, get_option( WP_Swiper_Whats_New::OPTION_VERSION ) );
		$this->assertSame( WP_SWIPER_VERSION, get_option( WP_Swiper_Whats_New::OPTION_FROM ) );

		$this->as_role( 'editor' );
		$this->assertSame( array(), WP_Swiper_Whats_New::entries() );
	}

	public function test_update_from_before_the_option_shows_the_current_version() {
		$this->slider();
		WP_Swiper_Whats_New::track_version();
		$this->assertSame( '', get_option( WP_Swiper_Whats_New::OPTION_FROM ) );

		$this->as_role( 'editor' );
		$this->assertSame( array( WP_SWIPER_VERSION ), array_keys( WP_Swiper_Whats_New::entries() ) );
	}

	public function test_update_remembers_the_version_before() {
		update_option( WP_Swiper_Whats_New::OPTION_VERSION, '1.0.0' );
		WP_Swiper_Whats_New::track_version();
		$this->assertSame( '1.0.0', get_option( WP_Swiper_Whats_New::OPTION_FROM ) );
		$this->assertSame( WP_SWIPER_VERSION, get_option( WP_Swiper_Whats_New::OPTION_VERSION ) );

		// The same version again: nothing changes.
		update_option( WP_Swiper_Whats_New::OPTION_FROM, 'kept' );
		WP_Swiper_Whats_New::track_version();
		$this->assertSame( 'kept', get_option( WP_Swiper_Whats_New::OPTION_FROM ) );
	}

	public function test_once_per_user_who_edits_sliders() {
		update_option( WP_Swiper_Whats_New::OPTION_VERSION, '1.0.0' );
		WP_Swiper_Whats_New::track_version();

		$this->as_role( 'subscriber' );
		$this->assertSame( array(), WP_Swiper_Whats_New::entries() );

		$this->as_role( 'editor' );
		$this->assertArrayHasKey( WP_SWIPER_VERSION, WP_Swiper_Whats_New::entries() );
		WP_Swiper_Whats_New::mark_seen();
		$this->assertSame( array(), WP_Swiper_Whats_New::entries() );
		$this->assertSame( WP_SWIPER_VERSION, get_user_meta( get_current_user_id(), WP_Swiper_Whats_New::META_SEEN, true ) );

		// Another editor has not seen it yet.
		$this->as_role( 'editor' );
		$this->assertArrayHasKey( WP_SWIPER_VERSION, WP_Swiper_Whats_New::entries() );
	}
}
