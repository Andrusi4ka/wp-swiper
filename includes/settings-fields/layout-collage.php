<?php
/**
 * Settings schema, part: Creative's Style (first in its Layout), then the
 * Collage's: Style, order, places, Arrangement (Gap, Rotation), Content in
 * a place, Photo swapping. Read inside WP_Swiper_Settings::fields()
 * (through settings-fields.php), so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	// Creative effect: Swiper's demo looks (frontend.js, creativePresets()).
	// First in its Layout: the fields below depend on it.
	'creative_style'        => array(
		'section' => 'layout',
		'effects' => array( 'creative' ),
		'label'   => __( 'Style', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'push',
		'choices' => array(
			'push'     => __( 'Push: the next slide covers the current one', 'wp-swiper' ),
			'zoom'     => __( 'Zoom: the current slide sinks back', 'wp-swiper' ),
			'apart'    => __( 'Slide apart: both move off and back', 'wp-swiper' ),
			'flip'     => __( 'Flip: a half turn in depth', 'wp-swiper' ),
			'rotate'   => __( 'Rotate: fly off with a quarter turn', 'wp-swiper' ),
			'door'     => __( 'Door: swing round an edge', 'wp-swiper' ),
			'carousel' => __( 'Carousel: neighbours in view on both sides', 'wp-swiper' ),
		),
	),

	// Collage effect: first in its Layout. The photos are placed by a
	// template or arranged by their proportions; with more photos than
	// places, the others are swapped in one at a time (collage.js collage()).
	'collage_layout'        => array(
		'section'         => 'layout',
		'effects'         => array( 'collage' ),
		'label'           => __( 'Style', 'wp-swiper' ),
		'type'            => 'select',
		// Shown as icons, assets/images/collage/{style}.svg (admin.js keeps the select behind them).
		'tiles'           => 'collage',
		'responsive'      => true,
		'default'         => 'feature-center',
		'choices'         => array(
			'feature-left'   => __( 'Template: a big photo on the left (3 photos)', 'wp-swiper' ),
			'feature-center' => __( 'Template: a big photo in the middle (5 photos)', 'wp-swiper' ),
			'mosaic'         => __( 'Template: mosaic (5 photos)', 'wp-swiper' ),
			'banner'         => __( 'Template: a wide photo on top (4 photos)', 'wp-swiper' ),
			'grid'           => __( 'Grid', 'wp-swiper' ),
			'justified'      => __( 'Justified rows', 'wp-swiper' ),
			'masonry'        => __( 'Masonry', 'wp-swiper' ),
			'random'         => __( 'Random mosaic', 'wp-swiper' ),
			'scatter'        => __( 'Scattered', 'wp-swiper' ),
			'freeform'       => __( 'Freeform', 'wp-swiper' ),
		),
		'help'            => __( 'Templates, Grid and Random mosaic fill the Height; Random mosaic cuts it into places of different sizes, at random (Shuffle), shaped after their photos. Scattered: a photo in the middle and the others around it in uneven arms, each shaped after its photo, the gap between neighbours always the same; as large as the Height and the width allow, the corners left empty. Freeform: the photos at random places and sizes, a Gap apart, each then moved and resized by hand in the preview; all of it scales with the Height. Justified rows keep each photo’s proportions in rows across the width; Masonry in columns. Both are as tall as their photos make them; as a background, as tall as the block (the places stretched or shrunk to it).', 'wp-swiper' ),
	),
	// The order of the photos without one set (collage_order): the shuffle
	// of this number (the same number, the same arrangement). Kept inside
	// the Order field; Shuffle no longer changes it (a shuffle is an order
	// of its own now).
	'collage_seed'          => array(
		'section'   => 'layout',
		'effects'   => array( 'collage' ),
		'label'     => __( 'Shuffle', 'wp-swiper' ),
		'type'      => 'seed',
		'inline_in' => 'collage_order',
		'default'   => 1,
	),
	// Which photo is in which place, per device: the photos' IDs, the
	// first ones in the first places ('' on desktop: the shuffle above;
	// on tablet and mobile: the larger screen's). Set by dragging a photo
	// onto another place in the preview, or by Shuffle.
	'collage_order'         => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Order', 'wp-swiper' ),
		'type'       => 'order',
		'responsive' => true,
		'default'    => '',
		'help'       => __( 'Drag a photo onto another place in the preview to swap the two; with Random mosaic and the templates, drag a line between photos to resize them; with Scattered, drag a photo\'s outer edge to resize it, or drop it on a dashed frame to move it to the end of an arm; with Freeform, drag a photo anywhere (it keeps a Gap from the others) or its corner or edge to resize it, and drop it on another photo to swap the two. Shuffle mixes the photos at random (Freeform: a new layout too). All only for the device chosen (a tablet or phone without its own takes the larger screen\'s); Reset goes back to that, order and sizes.', 'wp-swiper' ),
	),
	// Random mosaic: its cuts as set by dragging the lines between the
	// photos in the preview, per device (see sanitize_mosaic()); '' on
	// desktop: worked out from the photos; on tablet and mobile: the
	// larger screen's. Kept inside the Order field, whose Reset clears it too.
	'collage_mosaic'        => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Mosaic sizes', 'wp-swiper' ),
		'type'       => 'mosaic',
		'responsive' => true,
		'inline_in'  => 'collage_order',
		'default'    => '',
	),
	// A template's sizes, as set by dragging the lines between its photos (or
	// Scattered's, its photos' outer edges, "scatter:...", see sanitize_scatter())
	// in the preview, per device: the template and its cuts (see
	// sanitize_template_cuts()); used while the device's Style is that
	// template, else its own proportions. '' on tablet and mobile: the
	// larger screen's. Kept inside the Order field, whose Reset clears it too.
	'collage_cuts'          => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Template sizes', 'wp-swiper' ),
		'type'       => 'cuts',
		'responsive' => true,
		'inline_in'  => 'collage_order',
		'default'    => '',
	),
	// Whether the order or the mosaic sizes of a device were set by hand in
	// the preview (1) rather than by Shuffle: Shuffle is then unavailable
	// there until Reset (admin.js). Kept inside the Order field; the slider
	// itself does not use it.
	'collage_manual'        => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Arranged by hand', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'inline_in'  => 'collage_order',
		'default'    => 0,
		'min'        => 0,
		'max'        => 1,
		'step'       => 1,
	),
	'collage_count'         => array(
		'section'         => 'layout',
		'effects'         => array( 'collage' ),
		'label'           => __( 'Photos on screen', 'wp-swiper' ),
		'type'            => 'number',
		'responsive'      => true,
		'default'         => 6,
		'min'             => 1,
		'max'             => 30,
		'step'            => 1,
		'help'            => __( 'Grid, Justified rows, Masonry, Random mosaic, Scattered and Freeform; a template has its own number of places.', 'wp-swiper' ),
	),
	'collage_columns'       => array(
		'section'         => 'layout',
		'effects'         => array( 'collage' ),
		'label'           => __( 'Columns', 'wp-swiper' ),
		'type'            => 'number',
		'responsive'      => true,
		'default'         => 3,
		'min'             => 1,
		'max'             => 8,
		'step'            => 1,
		'help'            => __( 'Grid and Masonry.', 'wp-swiper' ),
	),
	'collage_row_height'    => array(
		'section'         => 'layout',
		'effects'         => array( 'collage' ),
		'label'           => __( 'Row height (px)', 'wp-swiper' ),
		'type'            => 'number',
		'responsive'      => true,
		'default'         => 220,
		'min'             => 60,
		'max'             => 800,
		'step'            => 10,
		'help'            => __( 'Justified rows: the height of a row (with Exact row height off, about it).', 'wp-swiper' ),
	),
	// Justified rows: rows of exactly the Row height (their right ends
	// uneven), or fitted to the width (their heights a little uneven).
	'collage_row_exact'     => array(
		'section' => 'layout',
		'effects' => array( 'collage' ),
		'label'   => __( 'Exact row height', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
		'help'    => __( 'Every row exactly the Row height, each photo at its own proportions, nothing cropped; a row ends where the next photo would not fit, so the right ends are uneven. Off: each row is fitted to the width exactly, its height a little more or less.', 'wp-swiper' ),
	),
	'collage_gap'           => array(
		'section'         => 'layout',
		'effects'         => array( 'collage' ),
		'label'           => __( 'Gap between photos (px)', 'wp-swiper' ),
		'type'            => 'number',
		'responsive'      => true,
		'default'         => 8,
		'min'             => 0,
		'max'             => 60,
		'step'            => 1,
	),
	// The whole collage turned (collage.js orient()), its photos upright, per
	// device: Grid, Random mosaic and the templates (laid out in the Height:
	// in a larger (Fill) or smaller (Fit) box that, turned, covers or fits the
	// block, so the Gap and the places keep their sizes), Scattered and
	// Freeform (turned where they are, as large as fits the block turned);
	// not Justified rows or Masonry (their height is their photos').
	'collage_rotate'        => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Rotation (°)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 0,
		'min'        => -45,
		'max'        => 45,
		'step'       => 1,
		'help'       => __( 'Grid, Random mosaic, the templates, Scattered and Freeform: the whole collage turned, its photos upright (Scattered and Freeform as large as fits the block turned). 0: not turned. Tablet and mobile can have their own.', 'wp-swiper' ),
	),
	'collage_rotate_fit'    => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Turned collage', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'default'    => 'fill',
		'choices'    => array(
			'fill' => __( 'Fill the block (its edges cut off)', 'wp-swiper' ),
			'fit'  => __( 'All of it (the corners empty)', 'wp-swiper' ),
		),
	),
	// A place of the collage with an image over text instead of a photo
	// (collage.js build()), which stays there while the photos swap. Its place
	// per device (counted as the places are laid out), or dragged onto
	// another photo in the preview. Not Justified rows or Masonry (their
	// places are their photos' proportions), not a background (no content
	// over the block's).
	'collage_content'       => array(
		'section' => 'layout',
		'effects' => array( 'collage' ),
		'label'   => __( 'Content in a place', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'An image and/or text in one place of the collage instead of a photo; it stays there while the photos swap. Not for Justified rows and Masonry.', 'wp-swiper' ),
	),
	'collage_content_place' => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Place', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 1,
		'min'        => 1,
		'max'        => 30,
		'step'       => 1,
		'help'       => __( 'Which place has it, counted as the places are laid out (1: the first). Or drag it onto a photo in the preview.', 'wp-swiper' ),
	),
	'collage_content_image' => array(
		'section' => 'layout',
		'effects' => array( 'collage' ),
		'label'   => __( 'Image', 'wp-swiper' ),
		'type'    => 'image',
		'default' => 0,
	),
	'collage_content_image_size' => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Image size (%)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 50,
		'min'        => 10,
		'max'        => 100,
		'step'       => 5,
		'help'       => __( 'Of the width inside the place; the image keeps its proportions. With text under it: at most half the height.', 'wp-swiper' ),
	),
	'collage_content_text'  => array(
		'section' => 'layout',
		'effects' => array( 'collage' ),
		'label'   => __( 'Text', 'wp-swiper' ),
		'type'    => 'editor',
		'default' => '',
		'help'    => __( 'Shortcodes work here too: a form, a theme\x27s HTML block with buttons… With one, nothing is cut: the place scrolls where it is too tall.', 'wp-swiper' ),
	),
	'collage_content_font_size' => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Font size (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 18,
		'min'        => 8,
		'max'        => 120,
		'step'       => 1,
		'help'       => __( 'Text that does not fit in the place is cut at a word, with "…".', 'wp-swiper' ),
	),
	'collage_content_padding' => array(
		'section'    => 'layout',
		'effects'    => array( 'collage' ),
		'label'      => __( 'Padding (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 16,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
		'help'       => __( 'Between the place\'s edge and the image and text.', 'wp-swiper' ),
	),
	'collage_content_valign' => array(
		'section' => 'layout',
		'effects' => array( 'collage' ),
		'label'   => __( 'Vertical alignment', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'center',
		'choices' => array(
			'top'    => __( 'Top', 'wp-swiper' ),
			'center' => __( 'Center', 'wp-swiper' ),
			'bottom' => __( 'Bottom', 'wp-swiper' ),
		),
	),
	'collage_content_bg'    => array(
		'section' => 'layout',
		'effects' => array( 'collage' ),
		'label'   => __( 'Background', 'wp-swiper' ),
		'type'    => 'alpha_color',
		'default' => 'rgba(0,0,0,0)',
		'help'    => __( 'The place\'s color under the image and text; at Opacity 0 (as by default) there is none.', 'wp-swiper' ),
	),
	// Photo swapping: on the Controls page, with the other settings of how it moves.
	'collage_swap'          => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => array( 'collage' ),
		'label'   => __( 'Swap in the other photos', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
		'help'    => __( 'With more photos than places: every so often a place, at random, fades to a photo not shown yet. The arrangement does not change.', 'wp-swiper' ),
	),
	'collage_swap_delay'    => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => array( 'collage' ),
		'label'   => __( 'A new photo every (ms)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 3000,
		'min'     => 1000,
		'max'     => 30000,
		'step'    => 500,
		'range'   => array( 1000, 10000 ),
	),
	'collage_swap_speed'    => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => array( 'collage' ),
		'label'   => __( 'Fade (ms)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 800,
		'min'     => 100,
		'max'     => 3000,
		'step'    => 50,
		'range'   => array( 100, 2000 ),
	),
	'collage_pause'         => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => array( 'collage' ),
		'label'   => __( 'Pause on hover', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
	),
);
