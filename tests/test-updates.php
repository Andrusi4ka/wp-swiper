<?php
/**
 * Updates from GitHub releases (wp_swiper_updates()): never for a git working
 * copy, because an update replaces the whole plugin folder and would wipe the
 * work in progress (wp_swiper_is_working_copy()).
 */

class Test_WP_Swiper_Updates extends WP_Swiper_TestCase {

	const FACTORY = 'YahnisElsts\PluginUpdateChecker\v5\PucFactory';

	public function tear_down() {
		remove_all_filters( 'wp_swiper_is_working_copy' );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_a_folder_with_git_is_a_working_copy() {
		$this->assertSame( file_exists( WP_SWIPER_DIR . '.git' ), wp_swiper_is_working_copy() );

		add_filter( 'wp_swiper_is_working_copy', '__return_true' );
		$this->assertTrue( wp_swiper_is_working_copy() );
		remove_all_filters( 'wp_swiper_is_working_copy' );

		add_filter( 'wp_swiper_is_working_copy', '__return_false' );
		$this->assertFalse( wp_swiper_is_working_copy() );
	}

	public function test_a_working_copy_gets_no_update_checker() {
		if ( class_exists( self::FACTORY, false ) ) {
			$this->markTestSkipped( 'The update checker was loaded before this test.' );
		}
		set_current_screen( 'dashboard' );
		add_filter( 'wp_swiper_is_working_copy', '__return_true' );

		wp_swiper_updates();

		$this->assertFalse( class_exists( self::FACTORY, false ) );
	}

	public function test_an_installed_copy_gets_the_update_checker() {
		set_current_screen( 'dashboard' );
		add_filter( 'wp_swiper_is_working_copy', '__return_false' );

		wp_swiper_updates();

		$this->assertTrue( class_exists( self::FACTORY, false ) );
	}
}
