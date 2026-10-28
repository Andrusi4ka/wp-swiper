<?php
/**
 * The sliders of the browser tests (fixture.php?case=<name>): settings as
 * the edit screen would post them (a checkbox left out is off), over the
 * defaults. No autoplay and no swaps, so that a page looks the same each
 * time it is opened.
 *
 * Keys: settings; photos (how many images, 8); video (the first video as
 * the first slide); captions (a caption on each slide); block (the slider
 * inside a block, for Use as background).
 */

$slider = array(
	'navigation' => '1',
	'pagination' => 'bullets',
	'lightbox'   => '1',
	'keyboard'   => '1',
	'height'     => '400',
);

return array(
	'slide'            => array( 'settings' => $slider + array( 'effect' => 'slide' ) ),
	'slide-loop'       => array( 'settings' => $slider + array( 'effect' => 'slide', 'loop' => '1', 'slides_per_view' => '3', 'space_between' => '12' ) ),
	'slide-captions'   => array(
		'settings' => $slider + array( 'effect' => 'slide' ),
		'captions' => true,
	),
	'expand'           => array( 'settings' => $slider + array( 'effect' => 'slide', 'expand_active' => '1', 'slides_per_view' => '5' ) ),
	'auto-width'       => array( 'settings' => $slider + array( 'effect' => 'slide', 'auto_width' => '1', 'space_between' => '12' ) ),
	'marquee'          => array( 'settings' => $slider + array( 'effect' => 'slide', 'marquee' => '1', 'slides_per_view' => '3' ) ),
	'fade'             => array( 'settings' => $slider + array( 'effect' => 'fade' ) ),
	'cube'             => array( 'settings' => $slider + array( 'effect' => 'cube' ) ),
	'coverflow'        => array( 'settings' => $slider + array( 'effect' => 'coverflow', 'slides_per_view' => '3' ) ),
	'flip'             => array( 'settings' => $slider + array( 'effect' => 'flip' ) ),
	'cards'            => array( 'settings' => $slider + array( 'effect' => 'cards' ) ),
	'creative'         => array( 'settings' => $slider + array( 'effect' => 'creative' ) ),
	'creative-carousel' => array( 'settings' => $slider + array( 'effect' => 'creative', 'creative_style' => 'carousel' ) ),
	'thumbs'           => array( 'settings' => $slider + array( 'effect' => 'thumbs' ) ),
	'video'            => array(
		'settings' => $slider + array( 'effect' => 'slide' ),
		'video'    => true,
		'photos'   => 3,
	),
	'background'       => array(
		'settings' => array(
			'effect'           => 'fade',
			'background'       => '1',
			'background_class' => 'fixture-bg',
		),
		'block'    => true,
	),
	'collage-scatter'  => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'scatter', 'collage_count' => '6', 'lightbox' => '1' ) ),
	'collage-freeform' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'freeform', 'collage_count' => '7', 'lightbox' => '1' ) ),
	'collage-grid'     => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'grid', 'lightbox' => '1' ) ),
	'collage-masonry'  => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'masonry', 'lightbox' => '1' ) ),
	'collage-justified' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'justified', 'lightbox' => '1' ) ),
	'collage-random'   => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'random', 'lightbox' => '1' ) ),
	'collage-feature'  => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'feature-left', 'lightbox' => '1' ) ),
	'art-ring'         => array( 'settings' => array( 'effect' => 'art', 'art_style' => 'ring', 'lightbox' => '1' ) ),
	'art-ring-text'    => array( 'settings' => array( 'effect' => 'art', 'art_style' => 'ring-text', 'art_center' => '1', 'art_center_text' => '<p>Hello</p>', 'lightbox' => '1' ) ),
	'art-grid-circle'  => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'grid-circle', 'lightbox' => '1' ),
		'photos'   => 10,
	),
);
