<?php
/**
 * What each effect can do (includes/effect-notes.php): a few points for
 * every effect of the Effect list, shown under the settings menu for the
 * chosen one (WP_Swiper_Admin::effect_notes()), in a box that folds.
 */

class Test_WP_Swiper_Effect_Notes extends WP_Swiper_TestCase {

	public function test_every_effect_has_a_few_points() {
		$notes   = WP_Swiper_Settings::effect_notes();
		$effects = array_keys( WP_Swiper_Settings::fields()['effect']['choices'] );
		$this->assertSame( $effects, array_keys( $notes['effects'] ) );
		foreach ( $notes['effects'] as $effect => $points ) {
			$this->assertGreaterThanOrEqual( 3, count( $points ), $effect );
			$this->assertLessThanOrEqual( 6, count( $points ), $effect );
			foreach ( $points as $point ) {
				$this->assertNotSame( '', trim( $point ), $effect );
				// Short: one line of the narrow column, give or take.
				$this->assertLessThanOrEqual( 120, strlen( $point ), $effect . ': ' . $point );
			}
		}
		$this->assertNotSame( '', $notes['all'] );
	}

	public function test_the_collage_tells_of_rotation_and_shortcodes() {
		$collage = implode( ' ', WP_Swiper_Settings::effect_notes()['effects']['collage'] );
		$this->assertStringContainsString( 'Rotation', $collage );
		$this->assertStringContainsString( 'shortcode', $collage );
		$this->assertStringContainsString( 'shortcode', WP_Swiper_Settings::effect_notes()['all'] );
	}

	public function test_the_box_under_the_menu() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->slider( null, array( 'effect' => 'collage' ) );
		ob_start();
		WP_Swiper_Admin::render_settings_box( get_post( $id ) );
		$html = ob_get_clean();

		// After the menu, before the demos link; open until folded.
		$this->assertSame( 1, preg_match( '#</nav><details class="wp-swiper-notes" open><summary class="wp-swiper-notes__summary">#', $html ) );
		$this->assertLessThan( strpos( $html, 'wp-swiper-demos' ), strpos( $html, 'wp-swiper-notes' ) );
		// The chosen effect's title and list shown, the others hidden.
		$this->assertStringContainsString( '<span class="wp-swiper-notes__title" data-effect="collage">What Collage can do</span>', $html );
		$this->assertStringContainsString( '<span class="wp-swiper-notes__title" data-effect="slide" hidden>What Slide can do</span>', $html );
		$this->assertStringContainsString( '<ul class="wp-swiper-notes__list" data-effect="collage"><li>', $html );
		$this->assertStringContainsString( '<ul class="wp-swiper-notes__list" data-effect="text-mask" hidden>', $html );
		$this->assertSame( 11, substr_count( $html, '<ul class="wp-swiper-notes__list"' ) );
		// Escaped (a theme&#039;s).
		$this->assertStringContainsString( 'a theme&#039;s HTML block', $html );
		$this->assertStringContainsString( '<p class="wp-swiper-notes__all">Every effect:', $html );
	}
}
