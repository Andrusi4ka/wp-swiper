<?php
/**
 * Settings schema, part: Thumbnails transition, slides per view and Gap,
 * Look (rounded corners, Image border, Gradient, Zoom on hover, Image
 * shadow), continuous scroll, Image fit and position, Zoom the main image.
 * Read inside WP_Swiper_Settings::fields() (through settings-fields.php),
 * so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	// Thumbnails effect: how one slide gives way to the next. First in its
	// Layout; a select, so that other transitions can join.
	'thumbs_transition'     => array(
		'section'   => 'layout',
		'effects'   => array( 'thumbs' ),
		'label'     => __( 'Transition', 'wp-swiper' ),
		'type'      => 'select',
		// Shown as a row of buttons, like Direction (admin.js keeps the select behind them).
		'segmented' => true,
		'default'   => 'slide',
		'choices'   => array(
			'slide' => __( 'Slide', 'wp-swiper' ),
			'fade'  => __( 'Fade', 'wp-swiper' ),
		),
	),
	'slides_per_view'       => array(
		'section'    => 'layout',
		'label'      => __( 'Slides per view', 'wp-swiper' ),
		'type'       => 'float',
		'responsive' => true,
		'default'    => 1,
		'min'        => 1,
		'max'        => 10,
		'step'       => 0.1,
	),
	// Slide effect: each slide as wide as its image allows at the slider
	// height (replaces Slides per view).
	'auto_width'            => array(
		'section' => 'layout',
		'effects' => array( 'slide' ),
		'label'   => __( 'Width from image', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'All slides share the slider height; each one is as wide as its image’s proportions make it.', 'wp-swiper' ),
	),
	'slides_per_group'      => array(
		'section'    => 'layout',
		'effects'    => array( 'slide' ),
		'label'      => __( 'Slides per group', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 1,
		'min'        => 1,
		'max'        => 10,
		'step'       => 1,
		'help'       => __( 'How many slides one step moves (arrows, swipe, autoplay); the pagination counts the steps.', 'wp-swiper' ),
	),
	'space_between'         => array(
		'section'    => 'layout',
		'effects'    => array( 'slide', 'coverflow' ),
		'label'      => __( 'Gap (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 0,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
	),
	// Rounded corners, as Image border: the radius once it is on. A slider
	// saved before with a radius keeps it on (merge_rounded(), get()).
	'border_rounded'        => array(
		'section' => 'layout',
		'label'   => __( 'Rounded corners', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	'border_radius'         => array(
		'section'    => 'layout',
		'label'      => __( 'Radius (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 12,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
	),
	// A frame drawn over the image (follows its corners; with "Contain"
	// the image, not the empty band). Width and color once it is on.
	'image_border'          => array(
		'section' => 'layout',
		'label'   => __( 'Image border', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	'image_border_width'    => array(
		'section'    => 'layout',
		'group'      => 'image_border',
		'label'      => __( 'Width (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 2,
		'min'        => 0,
		'max'        => 50,
		'step'       => 1,
	),
	'image_border_color'    => array(
		'section' => 'layout',
		'group'   => 'image_border',
		'label'   => __( 'Color', 'wp-swiper' ),
		'type'    => 'color',
		'default' => '#ffffff',
	),
	// Image shadow: one for every screen, where the photos stand apart
	// (see shadow() for when it applies). Its offsets, blur and spread
	// in one row, its color (with opacity) under them.
	// A gradient over the photos (WP_Swiper_Art::parse_gradient()), edited as
	// WoodMart's (admin.js): over each photo of a slider or collage (not a
	// video's); one across the whole shape of Art and Text mask (its gaps
	// stay empty). The switch, its colors under it.
	'image_gradient_on'     => array(
		'section' => 'layout',
		'effects' => self::GRADIENT_EFFECTS,
		'label'   => __( 'Gradient', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'A gradient over the photos: over each photo of a slider or collage (not over videos); one across the whole shape of Art and Text mask, the gaps staying empty. See-through colors let the photo show.', 'wp-swiper' ),
	),
	'image_gradient'        => array(
		'section' => 'layout',
		'effects' => self::GRADIENT_EFFECTS,
		'label'   => __( 'Gradient colors', 'wp-swiper' ),
		'type'    => 'gradient',
		'default' => WP_Swiper_Art::GRADIENT_DEFAULT,
		'help'    => __( 'Click the bar to add a color, drag a color along it, pick one to change or delete it.', 'wp-swiper' ),
	),
	// The photo under the pointer zooms a little in its box (its frame and
	// corners stay). Off by default; on for Art, which always had it
	// (merge_hover_zoom(), layouts()).
	'image_hover_zoom'      => array(
		'section' => 'layout',
		'effects' => self::HOVER_ZOOM_EFFECTS,
		'label'   => __( 'Zoom on hover', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'The photo under the pointer zooms in a little. Not on touch screens, not for a background, nor with Zoom the main image.', 'wp-swiper' ),
	),
	'image_shadow'          => array(
		'section' => 'layout',
		'effects' => self::SHADOW_EFFECTS,
		'label'   => __( 'Image shadow', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'A shadow under each photo where they stand apart: several slides in view, Coverflow, Cards, the Creative carousel, Collage and Art (where it follows each shape, with no spread); with Fade, around the photo in view. Not for a background. Room is kept around the photos for it, so they get a little smaller.', 'wp-swiper' ),
	),
	'image_shadow_x'        => array(
		'section' => 'layout',
		'effects' => self::SHADOW_EFFECTS,
		'group'   => 'image_shadow',
		'label'   => __( 'Horizontal (px)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 0,
		'min'     => -100,
		'max'     => 100,
		'step'    => 1,
	),
	'image_shadow_y'        => array(
		'section' => 'layout',
		'effects' => self::SHADOW_EFFECTS,
		'group'   => 'image_shadow',
		'label'   => __( 'Vertical (px)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 8,
		'min'     => -100,
		'max'     => 100,
		'step'    => 1,
	),
	'image_shadow_blur'     => array(
		'section' => 'layout',
		'effects' => self::SHADOW_EFFECTS,
		'group'   => 'image_shadow',
		'label'   => __( 'Blur radius (px)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 20,
		'min'     => 0,
		'max'     => 100,
		'step'    => 1,
	),
	'image_shadow_spread'   => array(
		'section' => 'layout',
		'effects' => self::SHADOW_EFFECTS,
		'group'   => 'image_shadow',
		'label'   => __( 'Spread radius (px)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 0,
		'min'     => -50,
		'max'     => 50,
		'step'    => 1,
	),
	// "#rrggbb" or "rgba(r,g,b,a)" (sanitize_slide_color()).
	'image_shadow_color'    => array(
		'section' => 'layout',
		'effects' => self::SHADOW_EFFECTS,
		'label'   => __( 'Color', 'wp-swiper' ),
		'type'    => 'alpha_color',
		'default' => 'rgba(0,0,0,0.3)',
	),
	'centered_slides'       => array(
		'section' => 'layout',
		// Coverflow always centres the active slide.
		'effects' => array( 'slide' ),
		'label'   => __( 'Center active slide', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	// Slide effect: a strip that keeps moving at an even speed (marquee).
	'marquee'               => array(
		'section' => 'layout',
		'effects' => array( 'slide' ),
		'label'   => __( 'Continuous scroll', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'The slides move on their own without stopping, in a loop. Pause on hover applies; arrows, pagination, keyboard, dragging, autoplay and transition speed do not.', 'wp-swiper' ),
	),
	'marquee_speed'         => array(
		'section' => 'layout',
		'effects' => array( 'slide' ),
		'label'   => __( 'Scroll speed (px/s)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 50,
		'min'     => 5,
		'max'     => 1000,
		'step'    => 5,
	),
	// Continuous scroll: which way the strip moves (Swiper's autoplay
	// reverseDirection). The labels are the horizontal slider's; admin.js
	// names them for a vertical one (bottom to top, top to bottom).
	'marquee_direction'     => array(
		'section' => 'layout',
		'effects' => array( 'slide' ),
		'label'   => __( 'Scroll direction', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'normal',
		'choices' => array(
			'normal'  => __( 'Right to left', 'wp-swiper' ),
			'reverse' => __( 'Left to right', 'wp-swiper' ),
		),
	),
	// Continuous scroll: something between the images that moves with them.
	'marquee_separator'     => array(
		'section' => 'layout',
		'effects' => array( 'slide' ),
		'label'   => __( 'Separator', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'none',
		'choices' => array(
			'none'  => __( 'None', 'wp-swiper' ),
			'dot'   => __( 'Dot', 'wp-swiper' ),
			'image' => __( 'Own image', 'wp-swiper' ),
		),
		'help'    => __( 'Stands in the middle of the space between the slides and moves with them.', 'wp-swiper' ),
	),
	'marquee_separator_image' => array(
		'section' => 'layout',
		'effects' => array( 'slide' ),
		'label'   => __( 'Separator image', 'wp-swiper' ),
		'type'    => 'image',
		'default' => 0,
		'help'    => __( 'From the media library; PNG or SVG with a transparent background suits best.', 'wp-swiper' ),
	),
	'marquee_separator_size' => array(
		'section'    => 'layout',
		'effects'    => array( 'slide' ),
		'label'      => __( 'Separator size (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 10,
		'min'        => 2,
		'max'        => 200,
		'step'       => 1,
		'help'       => __( 'The dot\'s diameter; an image fits in a square of this size. The space between the slides is at least as wide.', 'wp-swiper' ),
	),
	'marquee_separator_color' => array(
		'section' => 'layout',
		'effects' => array( 'slide' ),
		'label'   => __( 'Dot color', 'wp-swiper' ),
		'type'    => 'color',
		'default' => '#000000',
	),
	'image_fit'             => array(
		'section' => 'layout',
		'label'   => __( 'Image fit', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'cover',
		'choices' => array(
			'cover'   => __( 'Cover', 'wp-swiper' ),
			'contain' => __( 'Contain', 'wp-swiper' ),
		),
		'help'    => __( 'Cover: the image fills the slide, cropped where its proportions differ. Contain: the whole image is shown, with empty space beside it where its proportions differ.', 'wp-swiper' ),
	),
	// Wider active slide layout: Slide effect with Image fit "Cover" only.
	'expand_active'         => array(
		'section' => 'layout',
		'effects' => array( 'slide' ),
		'label'   => __( 'Make the active slide wider', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'The number of slides comes from “Slides per view”, always rounded to a whole number.', 'wp-swiper' ),
	),
	'active_width'          => array(
		'section'         => 'layout',
		'effects'         => array( 'slide' ),
		'label'           => __( 'Active slide size (%)', 'wp-swiper' ),
		'type'            => 'float',
		'responsive'      => true,
		'default'         => 40,
		'min'             => 5,
		'max'             => 95,
		'step'            => 0.5,
		'help'            => __( '% of the slider width (vertical sliders: of its height).', 'wp-swiper' ),
	),
	// "Cover": the part of a cropped image kept in view, per device (a
	// narrow phone screen often needs another one). Background and Collage
	// crop too. See WP_Swiper_Render::point().
	'image_position'        => array(
		'section'    => 'layout',
		'label'      => __( 'Image position', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'default'    => 'center',
		'choices'    => array(
			'center'       => __( 'Center', 'wp-swiper' ),
			'top-left'     => __( 'Top left', 'wp-swiper' ),
			'top'          => __( 'Top', 'wp-swiper' ),
			'top-right'    => __( 'Top right', 'wp-swiper' ),
			'left'         => __( 'Left', 'wp-swiper' ),
			'right'        => __( 'Right', 'wp-swiper' ),
			'bottom-left'  => __( 'Bottom left', 'wp-swiper' ),
			'bottom'       => __( 'Bottom', 'wp-swiper' ),
			'bottom-right' => __( 'Bottom right', 'wp-swiper' ),
		),
		'help'       => __( 'The part of the image kept in view where it is cropped to fill its slide. Not for YouTube / Vimeo videos.', 'wp-swiper' ),
	),
	// Where a whole ("Contain") image sits in its slide: across a horizontal
	// slider (top...bottom) or a vertical one (left...right).
	'image_align'           => array(
		'section' => 'layout',
		'label'   => __( 'Image alignment', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'center',
		'choices' => array(
			'top'    => __( 'Top', 'wp-swiper' ),
			'center' => __( 'Center', 'wp-swiper' ),
			'bottom' => __( 'Bottom', 'wp-swiper' ),
		),
	),
	'image_align_vertical'  => array(
		'section' => 'layout',
		'label'   => __( 'Image alignment', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'center',
		'choices' => array(
			'left'   => __( 'Left', 'wp-swiper' ),
			'center' => __( 'Center', 'wp-swiper' ),
			'right'  => __( 'Right', 'wp-swiper' ),
		),
	),
	// The image of the slide in view zooms slowly while it is shown (Ken
	// Burns, frontend.js zoom()). Its settings once it is on.
	'zoom'                  => array(
		'section' => 'layout',
		'effects' => self::ZOOM_EFFECTS,
		'label'   => __( 'Zoom the main image', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'The image of the slide in view slowly zooms in or out while it is shown. Slide effect: with one slide in view (Slides per view 1 on every device) or the wider active slide. Not for video slides.', 'wp-swiper' ),
	),
	'zoom_direction'        => array(
		'section'   => 'layout',
		'effects'   => self::ZOOM_EFFECTS,
		'label'     => __( 'Zoom direction', 'wp-swiper' ),
		'type'      => 'select',
		'segmented' => true,
		'default'   => 'in',
		'choices'   => array(
			'in'  => __( 'Zoom in', 'wp-swiper' ),
			'out' => __( 'Zoom out', 'wp-swiper' ),
		),
	),
	'zoom_amount'           => array(
		'section' => 'layout',
		'effects' => self::ZOOM_EFFECTS,
		'label'   => __( 'Zoom (%)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 15,
		'min'     => 2,
		'max'     => 50,
		'step'    => 1,
		'range'   => array( 2, 30 ),
		'help'    => __( 'How much larger the image gets: 15 = 115% of its size.', 'wp-swiper' ),
	),
	'zoom_origin'           => array(
		'section' => 'layout',
		'effects' => self::ZOOM_EFFECTS,
		'label'   => __( 'Zoom towards', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'center',
		'choices' => array(
			'center'       => __( 'Center', 'wp-swiper' ),
			'top-left'     => __( 'Top left', 'wp-swiper' ),
			'top'          => __( 'Top', 'wp-swiper' ),
			'top-right'    => __( 'Top right', 'wp-swiper' ),
			'left'         => __( 'Left', 'wp-swiper' ),
			'right'        => __( 'Right', 'wp-swiper' ),
			'bottom-left'  => __( 'Bottom left', 'wp-swiper' ),
			'bottom'       => __( 'Bottom', 'wp-swiper' ),
			'bottom-right' => __( 'Bottom right', 'wp-swiper' ),
		),
		'help'    => __( 'The point of the image that stays in place: the rest grows away from it (zoom in) or towards it (zoom out).', 'wp-swiper' ),
	),
	// Empty: as long as a slide is shown with Autoplay (see zoom_duration()).
	'zoom_duration'         => array(
		'section'     => 'layout',
		'effects'     => self::ZOOM_EFFECTS,
		'label'       => __( 'Zoom duration (ms)', 'wp-swiper' ),
		'type'        => 'number',
		'empty'       => true,
		'default'     => '',
		'min'         => 1000,
		'max'         => 60000,
		'step'        => 100,
		'placeholder' => __( 'Auto', 'wp-swiper' ),
		'help'        => __( 'Auto (empty): as long as a slide is shown with Autoplay (its delay plus the transition speed); without Autoplay, 6000.', 'wp-swiper' ),
	),
);
