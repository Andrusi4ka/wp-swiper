<?php
/**
 * The slider settings schema (WP_Swiper_Settings::fields(), which merges
 * Art's own into it): each field's section, type, default, effects and the
 * rest. Read inside WP_Swiper_Settings::fields(), so `self::` is that class.
 *
 * `effects`: the effects a field applies to (the editor hides it for the
 * others); none = all effects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The fields in this order, each file a part of the form (see its doc).
return array_merge(
	require __DIR__ . '/settings-fields/general.php',
	require __DIR__ . '/settings-fields/layout-collage.php',
	require __DIR__ . '/settings-fields/layout-look.php',
	require __DIR__ . '/settings-fields/layout-effects.php',
	require __DIR__ . '/settings-fields/content.php',
);
