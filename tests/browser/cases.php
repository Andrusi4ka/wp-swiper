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

$shadow = array(
	'image_shadow'        => '1',
	'image_shadow_x'      => '0',
	'image_shadow_y'      => '12',
	'image_shadow_blur'   => '24',
	'image_shadow_spread' => '0',
	'image_shadow_color'  => 'rgba(0,0,0,0.5)',
);

return array(
	'slide'            => array( 'settings' => $slider + array( 'effect' => 'slide' ) ),
	'slide-loop'       => array( 'settings' => $slider + array( 'effect' => 'slide', 'loop' => '1', 'slides_per_view' => '3', 'space_between' => '12' ) ),
	'slide-captions'   => array(
		'settings' => $slider + array( 'effect' => 'slide' ),
		'captions' => true,
	),
	'expand'           => array( 'settings' => $slider + array( 'effect' => 'slide', 'expand_active' => '1', 'slides_per_view' => '5' ) ),
	// Image shadow on a strip: its room shows no neighbour (slider.js hideOutside()).
	'expand-shadow'    => array(
		'settings' => $slider + $shadow + array( 'effect' => 'slide', 'expand_active' => '1', 'slides_per_view' => '5', 'space_between' => '6', 'active_width' => '50', 'active_width_mobile' => '70', 'loop' => '1', 'border_radius' => '10' ),
		'photos'   => 10,
	),
	'slide-loop-shadow' => array( 'settings' => $slider + $shadow + array( 'effect' => 'slide', 'loop' => '1', 'slides_per_view' => '3', 'space_between' => '12' ) ),
	'marquee-shadow'   => array( 'settings' => $slider + $shadow + array( 'effect' => 'slide', 'marquee' => '1', 'slides_per_view' => '3', 'space_between' => '12' ) ),
	'auto-width'       => array( 'settings' => $slider + array( 'effect' => 'slide', 'auto_width' => '1', 'space_between' => '12' ) ),
	'marquee'          => array( 'settings' => $slider + array( 'effect' => 'slide', 'marquee' => '1', 'slides_per_view' => '3' ) ),
	'fade'             => array( 'settings' => $slider + array( 'effect' => 'fade' ) ),
	'fade-shadow'      => array( 'settings' => $slider + $shadow + array( 'effect' => 'fade', 'border_rounded' => '1', 'border_radius' => '16' ) ),
	'fade-shadow-contain' => array( 'settings' => $slider + $shadow + array( 'effect' => 'fade', 'image_fit' => 'contain' ) ),
	'cube'             => array( 'settings' => $slider + array( 'effect' => 'cube' ) ),
	'coverflow'        => array( 'settings' => $slider + array( 'effect' => 'coverflow', 'slides_per_view' => '3' ) ),
	'flip'             => array( 'settings' => $slider + array( 'effect' => 'flip' ) ),
	'cards'            => array( 'settings' => $slider + array( 'effect' => 'cards' ) ),
	'creative'         => array( 'settings' => $slider + array( 'effect' => 'creative' ) ),
	'creative-carousel' => array( 'settings' => $slider + array( 'effect' => 'creative', 'creative_style' => 'carousel' ) ),
	// The frame as the thumbnails always had it (a missing checkbox is off, as in a form).
	'thumbs'           => array( 'settings' => $slider + array( 'effect' => 'thumbs', 'thumbs_border' => '1' ) ),
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
	// No photo in the circle: an image and text there, cut with an ellipsis where it does not fit.
	'art-grid-circle-text' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'grid-circle-text', 'art_center' => '1', 'art_center_text' => '<h3>Hello</h3><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>', 'lightbox' => '1' ),
		'photos'   => 10,
	),
	// A square over the grid's middle: the cells cut by it (an L at a corner, a notch at a side).
	'art-grid-square' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'grid-square', 'lightbox' => '1' ),
		'photos'   => 10,
	),
	'art-grid-square-text' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'grid-square-text', 'art_center' => '1', 'art_center_text' => '<h3>Hello</h3><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>', 'lightbox' => '1' ),
		'photos'   => 10,
	),
	// Grid shapes, the default layout (4 x 4, two of its shapes circles).
	'art-grid-shapes' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'grid-shapes', 'art_rounded' => '1', 'lightbox' => '1' ),
		'photos'   => 10,
	),
	// Grid shapes as its block's background: it fills the block behind its content.
	'art-grid-shapes-background' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'grid-shapes', 'art_rounded' => '1', 'background' => '1', 'background_class' => 'fixture-bg', 'background_overlay_color' => 'rgba(0,0,0,0.3)' ),
		'photos'   => 10,
		'block'    => true,
	),
	// A ring as its block's background, its proportions kept: filling the
	// block (Cover) at the right on desktop, all of it (Contain) at the top on mobile.
	'art-ring-background' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'ring', 'art_rounded' => '1', 'background' => '1', 'background_class' => 'fixture-bg', 'art_bg_size' => 'cover', 'art_bg_position' => 'right center', 'art_bg_size_mobile' => 'contain', 'art_bg_position_mobile' => 'center top' ),
		'photos'   => 8,
		'block'    => true,
	),
	// Shapes that turn in (L shapes) and one round a hole (with a shape in
	// it), one photo through all of them, stretched to a wide stage.
	'art-grid-shapes-one' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'grid-shapes', 'art_cells_columns' => '5', 'art_cells_rows' => '4', 'art_cells' => '5x4:0,0,0,0,1,0,2,2,0,1,0,2,2,0,3,0,0,0,0,3;2=40', 'art_height' => '420', 'art_gap' => '10', 'art_rounded' => '1', 'art_radius' => '30', 'art_shape_target' => 'one', 'lightbox' => '1' ),
		'photos'   => 4,
	),
);
