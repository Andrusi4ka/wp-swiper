<?php
/**
 * Stand-ins for the WPBakery functions WP_Swiper_WPBakery::map() calls
 * (test-capability.php), when WPBakery itself is not loaded: they note
 * what was mapped.
 */

function vc_add_shortcode_param( $name, $callback, $script = '' ) {
	return true;
}

function vc_lean_map( $tag, $settings ) {
	$GLOBALS['wp_swiper_test_vc_mapped'][] = $tag;
}
