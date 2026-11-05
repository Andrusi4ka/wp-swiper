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
	// Effect shadows in a color of their own (effect-shadows.spec.js; Coverflow's without one: its default).
	'coverflow-effect-shadow' => array( 'settings' => $slider + array( 'effect' => 'coverflow', 'slides_per_view' => '3', 'effect_shadows' => '1', 'effect_shadow_color' => '' ) ),
	'cards-effect-shadow'     => array( 'settings' => $slider + array( 'effect' => 'cards', 'effect_shadows' => '1', 'effect_shadow_color' => 'rgba(0,0,255,0.4)' ) ),
	'creative-effect-shadow'  => array( 'settings' => $slider + array( 'effect' => 'creative', 'effect_shadows' => '1', 'effect_shadow_color' => 'rgba(255,0,0,0.6)' ) ),
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
	// Gradient: over each photo of a slider and a collage, one across Art's ring.
	'slide-gradient'   => array( 'settings' => $slider + array( 'effect' => 'slide', 'slides_per_view' => '3', 'space_between' => '12', 'image_gradient_on' => '1', 'image_gradient' => 'linear;left;rgba(255,0,128,0.6)@0;rgba(0,96,255,0.6)@100' ) ),
	'collage-gradient' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'grid', 'lightbox' => '1', 'image_gradient_on' => '1', 'image_gradient' => 'linear;left;rgba(255,0,128,0.6)@0;rgba(0,96,255,0.6)@100' ) ),
	'art-ring-gradient' => array( 'settings' => array( 'effect' => 'art', 'art_style' => 'ring', 'lightbox' => '1', 'image_gradient_on' => '1', 'image_gradient' => 'linear;left;rgba(255,0,128,0.6)@0;rgba(0,96,255,0.6)@100' ) ),
	// Rotation: a grid turned 30° on desktop (filling its block, edges cut), not on mobile; a template turned -20° all of it shown (Fit).
	'collage-rotate'   => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'grid', 'collage_columns' => '3', 'collage_count' => '9', 'collage_rotate' => '30', 'collage_rotate_mobile' => '0', 'height' => '420', 'lightbox' => '1' ) ),
	'collage-rotate-fit' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'feature-left', 'collage_rotate' => '-20', 'collage_rotate_fit' => 'fit', 'height' => '420', 'image_border' => '1', 'image_border_width' => '3', 'lightbox' => '1' ), 'captions' => true ),
	// Rotation of Scattered and Freeform: turned where they are, all of it in the block.
	'collage-rotate-scatter' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'scatter', 'collage_count' => '6', 'collage_rotate' => '25', 'lightbox' => '1' ) ),
	'collage-rotate-free' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'freeform', 'collage_count' => '7', 'collage_rotate' => '-30', 'lightbox' => '1' ) ),
	// Height "Auto": the block from the photos' top-most point to their bottom-most.
	'collage-rotate-scatter-auto' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'scatter', 'collage_count' => '9', 'collage_rotate' => '14', 'height_unit' => 'auto', 'lightbox' => '1' ) ),
	'collage-rotate-free-auto' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'freeform', 'collage_count' => '7', 'collage_rotate' => '20', 'height_unit' => 'auto', 'lightbox' => '1' ) ),
	'collage-scatter'  => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'scatter', 'collage_count' => '6', 'lightbox' => '1' ) ),
	'collage-freeform' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'freeform', 'collage_count' => '7', 'lightbox' => '1' ) ),
	'collage-grid'     => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'grid', 'lightbox' => '1' ) ),
	'collage-masonry'  => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'masonry', 'lightbox' => '1' ) ),
	'collage-justified' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'justified', 'lightbox' => '1' ) ),
	'collage-random'   => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'random', 'lightbox' => '1' ) ),
	'collage-feature'  => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'feature-left', 'lightbox' => '1' ) ),
	// Content in a place: an image over text in one place instead of a photo.
	'collage-content'  => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'grid', 'collage_count' => '6', 'collage_content' => '1', 'collage_content_place' => '2', 'collage_content_place_mobile' => '1', 'collage_content_image' => '{image}', 'collage_content_text' => '<p><strong>Our work</strong></p><p>Photos from the last season.</p>', 'height' => '480', 'image_border' => '1', 'lightbox' => '1' ) ),
	// Turned, the text too long for the place: upright, cut at a word.
	'collage-content-scatter' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'scatter', 'collage_count' => '6', 'collage_rotate' => '14', 'collage_content' => '1', 'collage_content_place' => '1', 'collage_content_text' => '<p>' . str_repeat( 'A long text that does not fit in its place. ', 30 ) . '</p>', 'collage_content_bg' => 'rgba(30,60,90,1)', 'lightbox' => '1' ) ),
	// A shortcode in it (fixture.php: a form whose script marks it once the page is there).
	'collage-content-shortcode' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'grid', 'collage_count' => '6', 'collage_content' => '1', 'collage_content_place' => '3', 'collage_content_text' => '<p>' . str_repeat( 'Write to us. ', 40 ) . '</p>[wp_swiper_fixture_form]', 'height' => '420', 'lightbox' => '1' ) ),
	// Masonry on desktop (no content there), Grid on mobile: each photo once on both.
	'collage-content-masonry' => array(
		'settings' => array( 'effect' => 'collage', 'collage_layout' => 'masonry', 'collage_layout_mobile' => 'grid', 'collage_count' => '10', 'collage_content' => '1', 'collage_content_text' => '<p>Hello</p>' ),
		'photos'   => 4,
	),
	'collage-content-feature' => array( 'settings' => array( 'effect' => 'collage', 'collage_layout' => 'feature-left', 'collage_content' => '1', 'collage_content_place' => '1', 'collage_content_text' => '<p>The big place</p>', 'collage_content_valign' => 'bottom', 'lightbox' => '1' ) ),
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
	// SVG shape (tests/data/ex-3.svg), Content in a window: an image over text in
	// its second window instead of a photo, its first on mobile.
	'art-shape-content' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'shape', 'art_shape' => file_get_contents( dirname( __DIR__ ) . '/data/ex-3.svg' ), 'art_height' => '420', 'art_content' => '1', 'art_content_window' => '2', 'art_content_window_mobile' => '1', 'art_content_image' => '{image}', 'art_content_text' => '<p><strong>Hello</strong> from a window of the shape. ' . str_repeat( 'More text than it can hold in it, so that it is cut at a word. ', 24 ) . '</p>', 'art_content_bg' => 'rgba(30,60,90,1)', 'image_gradient_on' => '1', 'lightbox' => '1' ),
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
	// Text mask (drawn by Art): one photo through the letters, a strip under each.
	// Plain fonts (Georgia, Arial Black): the same on any theme.
	'art-text-mask'      => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'LAHORE', 'text_mask_font' => 'serif', 'text_mask_weight' => '900', 'art_height' => '520', 'lightbox' => '1' ) + $shadow,
		'photos'   => 3,
	),
	'art-text-mask-sans' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'WP SWIPER', 'text_mask_font' => 'sans', 'text_mask_strip_width' => 'letter', 'text_mask_strips' => 'middle', 'text_mask_gap' => '6', 'text_mask_size' => '45', 'art_height' => '360', 'lightbox' => '1' ),
		'photos'   => 1,
	),
	// A photo in each letter (as many photos as letters).
	'art-text-mask-each' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'LAHORE', 'text_mask_photos' => 'each', 'text_mask_font' => 'serif', 'text_mask_weight' => '900', 'art_height' => '520', 'lightbox' => '1' ),
		'photos'   => 6,
	),
	// The letters one under another, the strips going out to the right (all as
	// wide), or to the left (each as its letter).
	'art-text-mask-right' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'LAHORE', 'text_mask_direction' => 'right', 'text_mask_font' => 'serif', 'text_mask_weight' => '900', 'art_height' => '640', 'lightbox' => '1' ),
		'photos'   => 3,
	),
	'art-text-mask-left' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'WP SWIPER', 'text_mask_direction' => 'left', 'text_mask_font' => 'sans', 'text_mask_strip_width' => 'letter', 'text_mask_strips' => 'sides', 'art_height' => '640', 'lightbox' => '1' ),
		'photos'   => 1,
	),
	// A font by name (Impact: on every Windows computer).
	'art-text-mask-name' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'LAHORE', 'text_mask_font' => 'name', 'text_mask_font_name' => 'Impact', 'text_mask_weight' => '400', 'art_height' => '520', 'lightbox' => '1' ),
		'photos'   => 1,
	),
	// Letter size Auto (the default): the word as wide as the stage.
	'art-text-mask-auto' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_title' => 'LAHORE', 'text_mask_font' => 'serif', 'text_mask_weight' => '900', 'art_height' => '520', 'lightbox' => '1' ),
		'photos'   => 1,
	),
	// Stretch letters: held low by Letter size, widened to fill their columns.
	'art-text-mask-stretch' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_size' => '25', 'text_mask_stretch' => '1', 'text_mask_title' => 'WIDE', 'text_mask_font' => 'sans', 'art_height' => '420', 'lightbox' => '1' ),
		'photos'   => 1,
	),
	// Strips into letters: as art-text-mask, the strips 20% into their letters.
	'art-text-mask-join' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'LAHORE', 'text_mask_font' => 'serif', 'text_mask_weight' => '900', 'text_mask_join' => '20', 'art_height' => '520', 'lightbox' => '1' ) + $shadow,
		'photos'   => 1,
	),
	// Rounded corners: the strips' far ends, 30px (on mobile: half the strip at most).
	'art-text-mask-rounded' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'LAHORE', 'text_mask_font' => 'serif', 'text_mask_weight' => '900', 'text_mask_strips' => 'equal', 'text_mask_rounded' => '1', 'text_mask_radius' => '30', 'art_height' => '520', 'lightbox' => '1' ),
		'photos'   => 1,
	),
	// Gradient: over the photo in the letters and strips, one across the word.
	'art-text-mask-gradient' => array(
		'settings' => array( 'effect' => 'text-mask', 'text_mask_size_unit' => '%', 'text_mask_title' => 'LAHORE', 'text_mask_font' => 'serif', 'text_mask_weight' => '900', 'image_gradient_on' => '1', 'image_gradient' => 'linear;left;rgba(255,0,128,0.6)@0;rgba(0,96,255,0.6)@100', 'art_height' => '520', 'lightbox' => '1' ),
		'photos'   => 1,
	),
	// A ring as its block's background, its proportions kept: filling the
	// block (Cover) at the right on desktop, all of it (Contain) at the top on mobile.
	// A background with Image shadow (Collage and Art keep it there).
	'collage-background-shadow' => array(
		'settings' => array( 'effect' => 'collage', 'collage_layout' => 'grid', 'collage_gap' => '20', 'background' => '1', 'background_class' => 'fixture-bg', 'height_unit' => 'auto', 'image_shadow' => '1', 'image_shadow_y' => '8', 'image_shadow_blur' => '16', 'image_shadow_color' => 'rgba(0,0,0,0.6)' ),
		'block'    => true,
	),
	'art-grid-background-shadow' => array(
		'settings' => array( 'effect' => 'art', 'art_style' => 'grid-shapes', 'background' => '1', 'background_class' => 'fixture-bg', 'image_shadow' => '1', 'image_shadow_y' => '8', 'image_shadow_blur' => '16', 'image_shadow_color' => 'rgba(0,0,0,0.6)' ),
		'block'    => true,
	),
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
