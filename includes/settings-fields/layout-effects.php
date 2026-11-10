<?php
/**
 * Settings schema, part: Height and slide proportions, then each effect's
 * own: Creative, Coverflow, Cube, Cards, Thumbnails. Read inside
 * WP_Swiper_Settings::fields() (through settings-fields.php), so `self::`
 * is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	'height'                => array(
		'section'    => 'layout',
		'label'      => __( 'Height', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'unit'       => 'height_unit',
		'default'    => 0,
		'min'        => 0,
		'max'        => 2000,
		'step'       => 1,
		'help'       => __( '0 = 500px. Auto: fills the free height of the parent column; where there is none, follows the first image’s proportions.', 'wp-swiper' ),
		// Shown instead of "help" while Image fit is "Contain".
		'help_contain' => __( '0 = 500px. Auto: fills the free height of the parent column. If the height is greater than that of the images that fit in a row, it is ignored: the slider is as tall as the tallest of them.', 'wp-swiper' ),
	),
	// Slide effect: every image of the Height and these proportions, the
	// free width of the row spread around them (slide_distribution).
	'slide_ratio'           => array(
		'section'    => 'layout',
		'effects'    => array( 'slide' ),
		'label'      => __( 'Image proportions', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'default'    => 'off',
		'choices'    => array(
			'off'    => __( 'Off', 'wp-swiper' ),
			'1-1'    => '1:1',
			'4-3'    => '4:3',
			'3-2'    => '3:2',
			'16-9'   => '16:9',
			'3-4'    => '3:4',
			'9-16'   => '9:16',
			// Its two numbers: the field below.
			'custom' => __( 'Custom', 'wp-swiper' ),
		),
		'help'       => __( 'Every image is as tall as the Height and as wide as these proportions make it; Slides per view of them share the row and the free width goes around them (Distribution). The Gap is the least space left; where they do not fit with it, they get smaller. Off: the images fill the row.', 'wp-swiper' ),
	),
	// Shown while a device has "Custom" (like thumbs_ratio_custom).
	'slide_ratio_custom'    => array(
		'section'     => 'layout',
		'effects'     => array( 'slide' ),
		'label'       => __( 'Custom proportions', 'wp-swiper' ),
		'type'        => 'ratio',
		'responsive'  => true,
		'default'     => '4x3',
		'placeholder' => '4x3',
		'help'        => __( 'Width x height, for example 4x3 or 2.35x1.', 'wp-swiper' ),
	),
	// Where the free width of the row goes, like flex's justify-content.
	'slide_distribution'    => array(
		'section'   => 'layout',
		'effects'   => array( 'slide' ),
		'label'     => __( 'Distribution', 'wp-swiper' ),
		'type'      => 'select',
		'segmented' => true,
		'default'   => 'between',
		'choices'   => array(
			'between' => __( 'Space between', 'wp-swiper' ),
			'around'  => __( 'Space around', 'wp-swiper' ),
		),
		'help'      => __( 'Space between: the outer images at the row\'s edges, the free width between them. Space around: each image in the middle of its equal share, so half a space at the edges.', 'wp-swiper' ),
	),
	// Rendered next to the Height input (its "unit"), not as a field of its
	// own; per device (tablet and mobile empty: the larger screen's).
	'height_unit'           => array(
		'section'    => 'layout',
		'label'      => __( 'Height unit', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'inline_in'  => 'height',
		'default'    => 'px',
		'choices'   => array(
			'px'   => 'px',
			'vh'   => 'vh',
			'%'    => '%',
			'auto' => __( 'Auto', 'wp-swiper' ),
		),
	),

	// Carousel style: the slide in the middle, its neighbours beside it.
	'creative_neighbours'   => array(
		'section' => 'layout',
		'effects' => array( 'creative' ),
		'label'   => __( 'Visible neighbours', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 1,
		'min'     => 1,
		'max'     => 3,
		'step'    => 1,
		'help'    => __( 'Slides in view on each side of the middle one.', 'wp-swiper' ),
	),
	'creative_offset'       => array(
		'section' => 'layout',
		'effects' => array( 'creative' ),
		'label'   => __( 'Neighbour offset (%)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 60,
		'min'     => 20,
		'max'     => 100,
		'step'    => 5,
		'help'    => __( 'How far each neighbour sits from the one before it, in % of the middle slide’s width: below 100 they overlap.', 'wp-swiper' ),
	),
	'creative_scale'        => array(
		'section' => 'layout',
		'effects' => array( 'creative' ),
		'label'   => __( 'Neighbour scale', 'wp-swiper' ),
		'type'    => 'float',
		'default' => 0.8,
		'min'     => 0.5,
		'max'     => 1,
		'step'    => 0.05,
		'help'    => __( 'Size of the first neighbour; each further one shrinks as much again.', 'wp-swiper' ),
	),
	'creative_opacity'      => array(
		'section' => 'layout',
		'effects' => array( 'creative' ),
		'label'   => __( 'Neighbour opacity (%)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 100,
		'min'     => 0,
		'max'     => 100,
		'step'    => 5,
		'help'    => __( 'Of the first neighbour; each further one fades as much again.', 'wp-swiper' ),
	),

	// Depth shading Swiper draws on the slides (the cube has its own shadows;
	// on Flip it only flashes mid-turn, so it is not offered there).
	'effect_shadows'        => array(
		'section' => 'layout',
		'effects' => array( 'coverflow', 'cards', 'creative' ),
		'label'   => __( 'Effect shadows', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'Shades the slides as they turn. Not with Image fit “Contain”.', 'wp-swiper' ),
	),
	// Its color, with its opacity: how dark the slides get at most (Swiper's
	// own until 3.3: black at 15%, Coverflow's side shading at 50% — its own
	// default, effect_default(); merge_effect_shadow_color(), get()).
	'effect_shadow_color'   => array(
		'section'        => 'layout',
		'effects'        => array( 'coverflow', 'cards', 'creative' ),
		'label'          => __( 'Shadow color', 'wp-swiper' ),
		'type'           => 'alpha_color',
		'default'        => 'rgba(0,0,0,0.15)',
		'effect_default' => array( 'coverflow' => 'rgba(0,0,0,0.5)' ),
		'help'           => __( 'Its opacity is how dark the slides get at most.', 'wp-swiper' ),
	),

	// Coverflow effect (the defaults are Swiper's look the plugin always had).
	'coverflow_rotate'      => array(
		'section' => 'layout',
		'effects' => array( 'coverflow' ),
		'label'   => __( 'Rotate (°)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 50,
		// Below 0 Swiper turns them the other way (its angle is rotate x offset).
		'min'     => -90,
		'max'     => 90,
		'step'    => 1,
		'help'    => __( 'How far the side slides turn: above 0 towards the middle one, below 0 away from it (outwards).', 'wp-swiper' ),
	),
	// Depth and Stretch are in px, so the same value does more to a phone's
	// smaller slides: one per device (tablet and mobile empty: the larger
	// screen's, as every slider saved before). Rotate and scale look the same
	// at any size: one for every screen.
	'coverflow_depth'       => array(
		'section'    => 'layout',
		'effects'    => array( 'coverflow' ),
		'label'      => __( 'Depth (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 100,
		'min'        => 0,
		'max'        => 1000,
		'step'       => 10,
		'help'       => __( 'How far back the side slides sit: the more, the smaller they look.', 'wp-swiper' ),
	),
	'coverflow_stretch'     => array(
		'section'    => 'layout',
		'effects'    => array( 'coverflow' ),
		'label'      => __( 'Stretch (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 0,
		'min'        => -500,
		'max'        => 500,
		'step'       => 5,
		'help'       => __( 'Above 0 the side slides move in under the active one; below 0 they move apart.', 'wp-swiper' ),
	),
	'coverflow_scale'       => array(
		'section' => 'layout',
		'effects' => array( 'coverflow' ),
		'label'   => __( 'Side slides scale', 'wp-swiper' ),
		'type'    => 'float',
		'default' => 1,
		'min'     => 0.1,
		'max'     => 1.5,
		'step'    => 0.05,
		'help'    => __( '1 = the size of the active slide; 0.8 = smaller.', 'wp-swiper' ),
	),

	// Cube effect: its two shadows (Effect shadows is not used for it). The
	// one under it: its offset, size and color (with opacity) in one row
	// (until 3.0 an Opacity (%) of a black one, merge_cube_shadow_opacity(),
	// get()).
	'cube_shadow'           => array(
		'section' => 'layout',
		'effects' => array( 'cube' ),
		'label'   => __( 'Shadow under the cube', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	'cube_shadow_offset'    => array(
		'section' => 'layout',
		'effects' => array( 'cube' ),
		'group'   => 'cube_shadow',
		'label'   => __( 'Offset (px)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 20,
		'min'     => 0,
		'max'     => 200,
		'step'    => 1,
		'help'    => __( 'How far below the cube the shadow falls.', 'wp-swiper' ),
	),
	'cube_shadow_scale'     => array(
		'section' => 'layout',
		'effects' => array( 'cube' ),
		'group'   => 'cube_shadow',
		'label'   => __( 'Scale', 'wp-swiper' ),
		'type'    => 'float',
		'default' => 0.94,
		'min'     => 0.1,
		'max'     => 1.5,
		'step'    => 0.01,
		'help'    => __( 'Size of the shadow: 1 = as wide as the cube.', 'wp-swiper' ),
	),
	// "#rrggbb" or "rgba(r,g,b,a)" (sanitize_slide_color()).
	'cube_shadow_color'     => array(
		'section' => 'layout',
		'effects' => array( 'cube' ),
		'group'   => 'cube_shadow',
		'label'   => __( 'Color', 'wp-swiper' ),
		'type'    => 'alpha_color',
		'default' => 'rgba(0,0,0,0.6)',
	),
	'cube_slide_shadows'    => array(
		'section' => 'layout',
		'effects' => array( 'cube' ),
		'label'   => __( 'Darken side faces', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'Shades the faces as they turn away. Not with Image fit “Contain”.', 'wp-swiper' ),
	),

	// Cards effect.
	'cards_offset'          => array(
		'section' => 'layout',
		'effects' => array( 'cards' ),
		// Swiper's perSlideOffset: a % of the card width, shrinking for each
		// card further back; below 5 the far cards fly off the page.
		'label'   => __( 'Stack offset (%)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 8,
		'min'     => 5,
		'max'     => 15,
		'step'    => 1,
		'help'    => __( 'How far each card of the stack peeks out from under the one above it, in % of the card width. Three cards show on each side; what reaches past the block is cut off.', 'wp-swiper' ),
	),
	// Swiper's cardsEffect.rotate: all rotation of the cards (the stack's
	// and the moving card's), so Stack rotation applies only with it.
	'cards_tilt'            => array(
		'section' => 'layout',
		'effects' => array( 'cards' ),
		'label'   => __( 'Rotate cards', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
		'help'    => __( 'Tilts the cards of the stack and, while it moves, the card being turned.', 'wp-swiper' ),
	),
	'cards_rotate'          => array(
		'section' => 'layout',
		'effects' => array( 'cards' ),
		'label'   => __( 'Stack rotation (°)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 2,
		'min'     => 0,
		'max'     => 15,
		'step'    => 1,
		'help'    => __( 'Tilt of each card of the stack. 0 = straight.', 'wp-swiper' ),
	),

	// Thumbnails effect: the strip of small images that picks the slide, on
	// a page of its own ("panel"); still Layout values, kept per effect.
	// Per device: tablet and mobile may stay empty (inherit), as the numbers do.
	// Its blocks (Placement, Size, Look): blocks().
	'thumbs_position'       => array(
		'section'    => 'layout',
		'panel'      => 'thumbs',
		'effects'    => array( 'thumbs' ),
		'label'      => __( 'Thumbnails position', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'default'    => 'bottom',
		'choices'    => array(
			'bottom' => __( 'Below the slider', 'wp-swiper' ),
			'top'    => __( 'Above the slider', 'wp-swiper' ),
			'left'   => __( 'Left of the slider', 'wp-swiper' ),
			'right'  => __( 'Right of the slider', 'wp-swiper' ),
		),
		'help'       => __( 'Left or right: a column as tall as the slider; its width follows from the thumbnail size.', 'wp-swiper' ),
	),
	'thumbs_align'          => array(
		'section' => 'layout',
		'panel'   => 'thumbs',
		'effects' => array( 'thumbs' ),
		'label'   => __( 'Thumbnails alignment', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'center',
		'choices' => array(
			'center' => __( 'Center', 'wp-swiper' ),
			'start'  => __( 'Start (left; top in a column)', 'wp-swiper' ),
		),
		'help'    => __( 'Where the thumbnails sit while there are too few to fill the strip.', 'wp-swiper' ),
	),
	'thumbs_spacing'        => array(
		'section'    => 'layout',
		'panel'      => 'thumbs',
		'effects'    => array( 'thumbs' ),
		'label'      => __( 'Gap to the slider (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 10,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
		'range'      => array( 0, 100 ),
	),
	'thumbs_per_view'       => array(
		'section'         => 'layout',
		'panel'           => 'thumbs',
		'effects'         => array( 'thumbs' ),
		'label'           => __( 'Thumbnails per view', 'wp-swiper' ),
		'type'            => 'number',
		'responsive'      => true,
		'default'         => 6,
		'min'             => 2,
		'max'             => 20,
		'step'            => 1,
		'help'            => __( 'Sets the thumbnail size: this many fill the strip. With more slides the strip scrolls.', 'wp-swiper' ),
	),
	// Per device; empty (desktop too) or 0 = from Thumbnails per view and the
	// proportions. Set, it replaces Thumbnails per view on that device.
	'thumbs_height'         => array(
		'section'     => 'layout',
		'panel'       => 'thumbs',
		'effects'     => array( 'thumbs' ),
		'label'       => __( 'Thumbnail height (px)', 'wp-swiper' ),
		'type'        => 'number',
		'responsive'  => true,
		'empty'       => true,
		'default'     => '',
		'min'         => 0,
		'max'         => 600,
		'step'        => 1,
		'placeholder' => __( 'Auto', 'wp-swiper' ),
		'help'        => __( 'Auto (empty or 0): the size comes from Thumbnails per view and the proportions. Set: thumbnails this tall, frame included; their width follows from the proportions and as many show as fit, the rest scroll.', 'wp-swiper' ),
	),
	'thumbs_gap'            => array(
		'section'    => 'layout',
		'panel'      => 'thumbs',
		'effects'    => array( 'thumbs' ),
		'label'      => __( 'Space between thumbnails (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 10,
		'min'        => 0,
		'max'        => 100,
		'step'       => 1,
	),
	// Per device, like the position.
	'thumbs_ratio'          => array(
		'section'    => 'layout',
		'panel'      => 'thumbs',
		'effects'    => array( 'thumbs' ),
		'label'      => __( 'Thumbnail proportions', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'default'    => '4-3',
		'choices'    => array(
			'1-1'  => __( 'Square (1:1)', 'wp-swiper' ),
			'4-3'  => '4:3',
			'3-2'  => '3:2',
			'16-9' => '16:9',
			// Its two numbers: the fields below.
			'custom' => __( 'Custom', 'wp-swiper' ),
		),
		'help'       => __( 'The images are cropped or shown whole per “Image fit”.', 'wp-swiper' ),
	),
	// Shown while a device has "Custom": width x height in one field ("4x3",
	// see normalize_ratio()); per device too (those with "Custom" use theirs).
	'thumbs_ratio_custom'   => array(
		'section'     => 'layout',
		'panel'       => 'thumbs',
		'effects'     => array( 'thumbs' ),
		'label'       => __( 'Custom proportions', 'wp-swiper' ),
		'type'        => 'ratio',
		'responsive'  => true,
		'default'     => '4x3',
		'placeholder' => '4x3',
		'help'        => __( 'Width x height, for example 4x3 or 2.35x1.', 'wp-swiper' ),
	),
	// Rounded corners of the thumbnails, as the slides': the radius once it is
	// on; off, none on any screen (apply_rounded()). A slider saved before
	// with a radius keeps it on (merge_thumbs_switches(), get()).
	'thumbs_rounded'        => array(
		'section' => 'layout',
		'panel'   => 'thumbs',
		'effects' => array( 'thumbs' ),
		'label'   => __( 'Rounded corners', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	// Also that of the thumbnails in the lightbox, unless it has its own.
	// Each corner its own (2026-11-10, the user's choice, as the slides'
	// Radius): a box (admin-box.js), linked by default; this is the top
	// left's, and every corner's while linked. No slider (as the slides').
	'thumbs_radius'         => array(
		'section'    => 'layout',
		'panel'      => 'thumbs',
		'effects'    => array( 'thumbs' ),
		'label'      => __( 'Radius (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 6,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
		'box'        => array( 'thumbs_radius', 'thumbs_radius_tr', 'thumbs_radius_br', 'thumbs_radius_bl' ),
		'box_link'   => 'thumbs_radius_linked',
	),
	'thumbs_radius_tr'      => array(
		'section'    => 'layout',
		'panel'      => 'thumbs',
		'effects'    => array( 'thumbs' ),
		'inline_in'  => 'thumbs_radius',
		'label'      => __( 'Top right radius (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 6,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
	),
	'thumbs_radius_br'      => array(
		'section'    => 'layout',
		'panel'      => 'thumbs',
		'effects'    => array( 'thumbs' ),
		'inline_in'  => 'thumbs_radius',
		'label'      => __( 'Bottom right radius (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 6,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
	),
	'thumbs_radius_bl'      => array(
		'section'    => 'layout',
		'panel'      => 'thumbs',
		'effects'    => array( 'thumbs' ),
		'inline_in'  => 'thumbs_radius',
		'label'      => __( 'Bottom left radius (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 6,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
	),
	'thumbs_radius_linked'  => array(
		'section'   => 'layout',
		'panel'     => 'thumbs',
		'effects'   => array( 'thumbs' ),
		'inline_in' => 'thumbs_radius',
		'label'     => __( 'Corners set together', 'wp-swiper' ),
		'type'      => 'checkbox',
		'default'   => true,
	),
	// The frame, as Image border: its width and two colors once it is on;
	// off, none (apply_rounded()). On by default, as the thumbnails always
	// had one; a slider saved with a width of 0 has it off.
	'thumbs_border'         => array(
		'section' => 'layout',
		'panel'   => 'thumbs',
		'effects' => array( 'thumbs' ),
		'label'   => __( 'Frame', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
	),
	// One row: the frame's width and its two colors.
	'thumbs_border_width'   => array(
		'section' => 'layout',
		'panel'   => 'thumbs',
		'effects' => array( 'thumbs' ),
		'group'   => 'thumbs_border',
		'label'   => __( 'Width (px)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 1,
		'min'     => 1,
		'max'     => 20,
		'step'    => 1,
	),
	'thumbs_border_color'   => array(
		'section' => 'layout',
		'panel'   => 'thumbs',
		'effects' => array( 'thumbs' ),
		'group'   => 'thumbs_border',
		'label'   => __( 'Color', 'wp-swiper' ),
		'type'    => 'color',
		'default' => '#dddddd',
	),
	'thumbs_active_color'   => array(
		'section' => 'layout',
		'panel'   => 'thumbs',
		'effects' => array( 'thumbs' ),
		'group'   => 'thumbs_border',
		'label'   => __( 'Active color', 'wp-swiper' ),
		'type'    => 'color',
		'default' => '#333333',
	),
	'thumbs_opacity'        => array(
		'section' => 'layout',
		'panel'   => 'thumbs',
		'effects' => array( 'thumbs' ),
		'label'   => __( 'Inactive thumbnail opacity (%)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 60,
		'min'     => 10,
		'max'     => 100,
		'step'    => 5,
		'range'   => array( 10, 100 ),
	),
);
