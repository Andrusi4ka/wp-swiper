<?php
/**
 * Settings schema, part: Effect, Direction, Movement, Lightbox, Arrows and
 * keyboard, Pagination. Read inside WP_Swiper_Settings::fields() (through
 * settings-fields.php), so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	'effect'                => array(
		'section' => 'advanced',
		'label'   => __( 'Effect', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'slide',
		'choices' => array(
			'slide'     => __( 'Slide', 'wp-swiper' ),
			'fade'      => __( 'Fade', 'wp-swiper' ),
			'cube'      => __( 'Cube', 'wp-swiper' ),
			'coverflow' => __( 'Coverflow', 'wp-swiper' ),
			'flip'      => __( 'Flip', 'wp-swiper' ),
			'cards'     => __( 'Cards', 'wp-swiper' ),
			'creative'  => __( 'Creative', 'wp-swiper' ),
			// One slide at a time, picked from a strip of thumbnails.
			'thumbs'    => __( 'Thumbnails', 'wp-swiper' ),
			// Not a slider: the photos placed together, the others swapped in.
			'collage'   => __( 'Collage', 'wp-swiper' ),
			// Not a slider either: photos in a shape of its own (its Style),
			// all of it in class-wp-swiper-art.php, art.js and art.css.
			'art'       => __( 'Art', 'wp-swiper' ),
			// A word whose letters are windows onto a photo, a strip under each:
			// drawn by Art too (WP_Swiper_Art, style "text").
			'text-mask' => __( 'Text mask', 'wp-swiper' ),
		),
	),
	// Layout (its Effect block, first): for the effects that can move up and
	// down only. Fade, Collage and Art (and Thumbnails with the Fade
	// transition, admin.js) are always horizontal (render_slider()). Kept per
	// effect, as every Layout field.
	'direction'             => array(
		'section'   => 'layout',
		'effects'   => array( 'slide', 'cube', 'coverflow', 'flip', 'cards', 'creative', 'thumbs' ),
		'label'     => __( 'Direction', 'wp-swiper' ),
		'type'      => 'select',
		// Shown as a row of buttons (admin.js keeps the select behind them).
		'segmented' => true,
		'default'   => 'horizontal',
		'choices' => array(
			'horizontal' => __( 'Horizontal', 'wp-swiper' ),
			'vertical'   => __( 'Vertical', 'wp-swiper' ),
		),
	),
	// Controls, at the top: how the slider moves, then (below, after the
	// Lightbox fields) the arrows, pagination and keyboard.
	'speed'                 => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Transition speed (ms)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 600,
		'min'     => 100,
		'max'     => 10000,
		'step'    => 50,
		// A slider next to the number over the usual values; the number takes any up to max.
		'range'   => array( 100, 3000 ),
	),
	'loop'                  => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Infinite loop', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
	),
	// Swiper's simulateTouch (slider.js): the mouse drags the slides as a
	// finger does. Off: a finger still swipes them on a touch screen.
	'mouse_drag'            => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Drag with the mouse', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
		'help'    => __( 'Off: on a computer the slides move only with the arrows, the pagination, the keyboard or autoplay. On phones and tablets a finger still swipes them.', 'wp-swiper' ),
	),

	'autoplay'              => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Autoplay', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	// Right under Autoplay, both shown while it (or Continuous scroll) is on.
	'autoplay_delay'        => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Delay between slides (ms)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 4000,
		'min'     => 500,
		'max'     => 60000,
		'step'    => 100,
		'range'   => array( 500, 15000 ),
	),
	'pause_on_hover'        => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Pause on hover', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
	),
	'lightbox'              => array(
		'section' => 'advanced',
		'panel'   => 'lightbox',
		'label'   => __( 'Open images in a lightbox', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'Available only when the images have no links.', 'wp-swiper' ),
	),
	// Shown while some slide is a video (admin.js).
	'lightbox_videos'       => array(
		'section' => 'advanced',
		'panel'   => 'lightbox',
		'label'   => __( 'Videos in the lightbox', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
		'help'    => __( 'A click on a video slide opens it in the lightbox, with sound and controls. Off: the lightbox shows the images only.', 'wp-swiper' ),
	),
	// The lightbox image's rounded corners, as the slider's (Look): its own
	// radius per device once on; off, none. Until 3.0 an empty radius took
	// the slider's (merge_lightbox_rounded(), get()).
	'lightbox_border_rounded' => array(
		'section' => 'advanced',
		'panel'   => 'lightbox',
		'label'   => __( 'Rounded corners', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	'lightbox_radius'       => array(
		'section'    => 'advanced',
		'panel'      => 'lightbox',
		'label'      => __( 'Radius (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 12,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
		'range'      => array( 0, 100 ),
	),
	// Thumbnails effect: its strip in the lightbox too, always under the
	// image, as many in view as in the slider.
	'lightbox_thumbs'       => array(
		'section' => 'advanced',
		'panel'   => 'lightbox',
		'effects' => array( 'thumbs' ),
		'label'   => __( 'Thumbnails in the lightbox', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'Under the image, as many in view as in the slider.', 'wp-swiper' ),
	),
	// Like the image's: per device, empty takes the slider's thumbnail radius.
	'lightbox_thumbs_radius' => array(
		'section'     => 'advanced',
		'panel'       => 'lightbox',
		'effects'     => array( 'thumbs' ),
		'label'       => __( 'Thumbnail border radius (px)', 'wp-swiper' ),
		'type'        => 'number',
		'responsive'  => true,
		'empty'       => true,
		'default'     => '',
		'min'         => 0,
		'max'         => 200,
		'step'        => 1,
		'range'       => array( 0, 50 ),
		'placeholder' => __( 'As the slider’s', 'wp-swiper' ),
		'help'        => __( 'Empty: the thumbnail border radius (Layout).', 'wp-swiper' ),
	),
	// Shown while some slide has a description that the slider shows (admin.js).
	'description_lightbox'  => array(
		'section' => 'advanced',
		'panel'   => 'lightbox',
		'label'   => __( 'Descriptions in the lightbox', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
		'help'    => __( 'The slide’s description under its image, with the caption.', 'wp-swiper' ),
	),
	// Where: a panel over the bottom of the image, or below the image
	// (frontend.js fitLightboxText()), its width set below.
	'description_lightbox_place' => array(
		'section'   => 'advanced',
		'panel'     => 'lightbox',
		'label'     => __( 'Description place', 'wp-swiper' ),
		'type'      => 'select',
		'segmented' => true,
		'default'   => 'over',
		'choices'   => array(
			'over'  => __( 'Over the image', 'wp-swiper' ),
			'under' => __( 'Under the image', 'wp-swiper' ),
		),
		'help'      => __( 'Under the image: the image gets smaller to leave room for the text (at most 40% of the window; longer text scrolls).', 'wp-swiper' ),
	),
	// The text's width under the image, per device; its limit on its own row under it.
	'description_lightbox_width' => array(
		'section'         => 'advanced',
		'panel'           => 'lightbox',
		'label'           => __( 'Text width (% of the window)', 'wp-swiper' ),
		'type'            => 'number',
		'responsive'      => true,
		'default'         => 75,
		'min'             => 30,
		'max'             => 100,
		'step'            => 5,
		'range'           => array( 30, 100 ),
		'help'            => __( 'As wide as the image above it, within this width and the maximum; not narrower than 500px where the window allows.', 'wp-swiper' ),
	),
	'description_lightbox_max' => array(
		'section' => 'advanced',
		'panel'   => 'lightbox',
		'label'   => __( 'At most (px)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 800,
		'min'     => 300,
		'max'     => 2000,
		'step'    => 10,
	),

	'navigation'            => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'group'   => 'arrows',
		'label'   => __( 'Arrows', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
	),
	// Compact: a swatch right after the Arrows checkbox, its label as a tooltip.
	'navigation_color'      => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'group'   => 'arrows',
		'compact' => true,
		'label'   => __( 'Arrow color', 'wp-swiper' ),
		'type'    => 'color',
		'default' => '#ffffff',
	),
	// Swiper's navigation size: the arrows' height (their width and offset
	// follow). Per device; empty (desktop too) = the plugin's own sizes.
	'navigation_size'       => array(
		'section'     => 'advanced',
		'panel'       => 'controls',
		'label'       => __( 'Arrow height (px)', 'wp-swiper' ),
		'type'        => 'number',
		'responsive'  => true,
		'empty'       => true,
		'default'     => '',
		'min'         => 10,
		'max'         => 120,
		'step'        => 1,
		'placeholder' => __( 'Auto', 'wp-swiper' ),
		'help'        => __( 'Auto (empty): 32px on the slider, 20px on the strip of thumbnails (Thumbnails effect). Their width follows.', 'wp-swiper' ),
	),
	'pagination'            => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Pagination', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'bullets',
		'choices' => array(
			'none'     => __( 'None', 'wp-swiper' ),
			'bullets'  => __( 'Bullets', 'wp-swiper' ),
			'fraction' => __( 'Fraction (1 / 5)', 'wp-swiper' ),
		),
	),
	'pagination_position'   => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Pagination position', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'bottom-center',
		'choices' => array(
			'bottom-left'   => __( 'Bottom left', 'wp-swiper' ),
			'bottom-center' => __( 'Bottom center', 'wp-swiper' ),
			'bottom-right'  => __( 'Bottom right', 'wp-swiper' ),
			'top-left'      => __( 'Top left', 'wp-swiper' ),
			'top-center'    => __( 'Top center', 'wp-swiper' ),
			'top-right'     => __( 'Top right', 'wp-swiper' ),
		),
	),
	// Shown instead of pagination_position for vertical sliders.
	'pagination_position_vertical' => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Pagination position', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'right-center',
		'choices' => array(
			'left-top'     => __( 'Left top', 'wp-swiper' ),
			'left-center'  => __( 'Left center', 'wp-swiper' ),
			'left-bottom'  => __( 'Left bottom', 'wp-swiper' ),
			'right-top'    => __( 'Right top', 'wp-swiper' ),
			'right-center' => __( 'Right center', 'wp-swiper' ),
			'right-bottom' => __( 'Right bottom', 'wp-swiper' ),
		),
	),
	'pagination_placement'  => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Pagination placement', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'inside',
		'choices' => array(
			'inside'  => __( 'Over the slider', 'wp-swiper' ),
			'outside' => __( 'Outside the slider', 'wp-swiper' ),
		),
	),
	'pagination_offset'     => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Pagination offset (px)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 16,
		'min'     => 0,
		'max'     => 200,
		'step'    => 1,
		'help'    => __( 'Distance from the slider edge (over the slider) or gap to the slider (outside).', 'wp-swiper' ),
	),
	// Bullets: their size and shape per device (the CSS custom properties
	// Swiper reads, see WP_Swiper_Render::markup()). The defaults are the
	// look the bullets always had.
	'pagination_bullet_width' => array(
		'section'    => 'advanced',
		'panel'      => 'controls',
		'label'      => __( 'Bullet width (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 8,
		'min'        => 2,
		'max'        => 60,
		'step'       => 1,
	),
	'pagination_bullet_height' => array(
		'section'    => 'advanced',
		'panel'      => 'controls',
		'label'      => __( 'Bullet height (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 8,
		'min'        => 2,
		'max'        => 60,
		'step'       => 1,
	),
	// Empty: fully rounded (a circle, or a pill when wider than tall).
	'pagination_bullet_radius' => array(
		'section'     => 'advanced',
		'panel'       => 'controls',
		'label'       => __( 'Bullet border radius (px)', 'wp-swiper' ),
		'type'        => 'number',
		'empty'       => true,
		'default'     => '',
		'min'         => 0,
		'max'         => 30,
		'step'        => 1,
		'range'       => array( 0, 30 ),
		'placeholder' => __( 'Round', 'wp-swiper' ),
		'help'        => __( 'Empty: fully rounded, a circle or, when wider than tall, a pill. 0 = square corners.', 'wp-swiper' ),
	),
	// Empty: Swiper's own spacing (8px in a row, 12px in a column).
	'pagination_bullet_gap' => array(
		'section'     => 'advanced',
		'panel'       => 'controls',
		'label'       => __( 'Space between bullets (px)', 'wp-swiper' ),
		'type'        => 'number',
		'responsive'  => true,
		'empty'       => true,
		'default'     => '',
		'min'         => 0,
		'max'         => 40,
		'step'        => 1,
		'placeholder' => __( 'Auto', 'wp-swiper' ),
	),
	// The "pill": the current bullet longer than the others (frontend.css).
	'pagination_bullet_active' => array(
		'section'     => 'advanced',
		'panel'       => 'controls',
		'label'       => __( 'Current bullet length (px)', 'wp-swiper' ),
		'type'        => 'number',
		'responsive'  => true,
		'empty'       => true,
		'default'     => '',
		'min'         => 2,
		'max'         => 120,
		'step'        => 1,
		'placeholder' => __( 'As the others', 'wp-swiper' ),
		'help'        => __( 'Makes the current bullet longer than the others, e.g. 24 for a pill: wider in a row, taller in a column (vertical sliders). It grows and shrinks smoothly. Empty: as the others.', 'wp-swiper' ),
	),
	'pagination_fraction_size' => array(
		'section'     => 'advanced',
		'panel'       => 'controls',
		'label'       => __( 'Fraction font size (px)', 'wp-swiper' ),
		'type'        => 'number',
		'responsive'  => true,
		'empty'       => true,
		'default'     => '',
		'min'         => 8,
		'max'         => 60,
		'step'        => 1,
		'placeholder' => __( 'Auto', 'wp-swiper' ),
	),
	// Off: white over the slider, the page's text colour outside it.
	'pagination_colors'     => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Own pagination colors', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'Off: white over the slider; outside it, the colour of the page’s text.', 'wp-swiper' ),
	),
	// One row: the current one, the others and how see-through they are.
	'pagination_color'      => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'group'   => 'pagination_colors',
		'label'   => __( 'Current', 'wp-swiper' ),
		'type'    => 'color',
		'default' => '#ffffff',
	),
	'pagination_inactive_color' => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'group'   => 'pagination_colors',
		'label'   => __( 'Others', 'wp-swiper' ),
		'type'    => 'color',
		'default' => '#ffffff',
	),
	'pagination_inactive_opacity' => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'group'   => 'pagination_colors',
		'label'   => __( 'Others’ opacity (%)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 50,
		'min'     => 0,
		'max'     => 100,
		'step'    => 5,
	),
	'keyboard'              => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'label'   => __( 'Keyboard control', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
	),
);
