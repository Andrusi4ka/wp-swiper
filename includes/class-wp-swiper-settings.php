<?php
/**
 * Slider settings schema: single source of truth for the admin form,
 * sanitization and the front-end config.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Swiper_Settings {

	const POST_TYPE     = 'wp_swiper';
	const META_SLIDES   = '_wp_swiper_slides';
	const META_SETTINGS = '_wp_swiper_settings';

	/**
	 * Effects that always show exactly one slide.
	 */
	const SINGLE_SLIDE_EFFECTS = array( 'fade', 'cube', 'flip', 'cards', 'creative', 'thumbs' );

	/**
	 * Field definitions grouped into sections. `effects`: the effects a field
	 * applies to (the editor hides it for the others); none = all effects.
	 *
	 * @return array
	 */
	public static function fields() {
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
				),
			),
			'direction'             => array(
				'section'   => 'advanced',
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
			// Per device; empty (desktop too, `empty`) takes the slider's own
			// radius on that device (see WP_Swiper_Render::lightbox_radius()).
			'lightbox_radius'       => array(
				'section'     => 'advanced',
				'panel'       => 'lightbox',
				'label'       => __( 'Image border radius (px)', 'wp-swiper' ),
				'type'        => 'number',
				'responsive'  => true,
				'empty'       => true,
				'default'     => '',
				'min'         => 0,
				'max'         => 200,
				'step'        => 1,
				'placeholder' => __( 'As the slides', 'wp-swiper' ),
				'help'        => __( 'Empty: the slide border radius (Layout).', 'wp-swiper' ),
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
				'placeholder' => __( 'As the slider’s', 'wp-swiper' ),
				'help'        => __( 'Empty: the thumbnail border radius (Layout).', 'wp-swiper' ),
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
			'keyboard'              => array(
				'section' => 'advanced',
				'panel'   => 'controls',
				'label'   => __( 'Keyboard control', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
			),

			// Thumbnails effect: how one slide gives way to the next. First in its
			// Layout; a select, so that other transitions can join.
			'thumbs_transition'     => array(
				'section' => 'layout',
				'effects' => array( 'thumbs' ),
				'label'   => __( 'Transition', 'wp-swiper' ),
				'type'    => 'select',
				'default' => 'slide',
				'choices' => array(
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
				'label'      => __( 'Space between slides (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 0,
				'min'        => 0,
				'max'        => 200,
				'step'       => 1,
			),
			'border_radius'         => array(
				'section'    => 'layout',
				'label'      => __( 'Slide border radius (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 0,
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
			'image_fit'             => array(
				'section' => 'layout',
				'label'   => __( 'Image fit', 'wp-swiper' ),
				'type'    => 'select',
				'default' => 'cover',
				'choices' => array(
					'cover'   => __( 'Cover (crop to fill)', 'wp-swiper' ),
					'contain' => __( 'Contain (show whole image)', 'wp-swiper' ),
				),
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
				'device_defaults' => array( 'mobile' => 70 ),
				'min'             => 5,
				'max'             => 95,
				'step'            => 0.5,
				'help'            => __( '% of the slider width (vertical sliders: of its height).', 'wp-swiper' ),
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
			// Rendered next to the Height input (its "unit"), not as a field of its own.
			'height_unit'           => array(
				'section'   => 'layout',
				'label'     => __( 'Height unit', 'wp-swiper' ),
				'type'      => 'select',
				'inline_in' => 'height',
				'default'   => 'px',
				'choices'   => array(
					'px'   => 'px',
					'vh'   => 'vh',
					'%'    => '%',
					'auto' => __( 'Auto', 'wp-swiper' ),
				),
			),

			// Creative effect: Swiper's demo looks (frontend.js, creativePresets()).
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
			'coverflow_depth'       => array(
				'section' => 'layout',
				'effects' => array( 'coverflow' ),
				'label'   => __( 'Depth (px)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 100,
				'min'     => 0,
				'max'     => 1000,
				'step'    => 10,
				'help'    => __( 'How far back the side slides sit: the more, the smaller they look.', 'wp-swiper' ),
			),
			'coverflow_stretch'     => array(
				'section' => 'layout',
				'effects' => array( 'coverflow' ),
				'label'   => __( 'Stretch (px)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 0,
				'min'     => -500,
				'max'     => 500,
				'step'    => 5,
				'help'    => __( 'Above 0 the side slides move in under the active one; below 0 they move apart.', 'wp-swiper' ),
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

			// Cube effect: its two shadows (Effect shadows is not used for it).
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
				'label'   => __( 'Shadow offset (px)', 'wp-swiper' ),
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
				'label'   => __( 'Shadow scale', 'wp-swiper' ),
				'type'    => 'float',
				'default' => 0.94,
				'min'     => 0.1,
				'max'     => 1.5,
				'step'    => 0.01,
				'help'    => __( 'Size of the shadow: 1 = as wide as the cube.', 'wp-swiper' ),
			),
			'cube_shadow_opacity'   => array(
				'section' => 'layout',
				'effects' => array( 'cube' ),
				'label'   => __( 'Shadow opacity (%)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 60,
				'min'     => 0,
				'max'     => 100,
				'step'    => 5,
				'help'    => __( 'The shadow is black: lower values make it a lighter grey.', 'wp-swiper' ),
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
			// "heading": a title over it and the fields after it on that page.
			'thumbs_position'       => array(
				'section'    => 'layout',
				'panel'      => 'thumbs',
				'heading'    => __( 'Placement', 'wp-swiper' ),
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
			),
			'thumbs_per_view'       => array(
				'section'         => 'layout',
				'panel'           => 'thumbs',
				'heading'         => __( 'Size', 'wp-swiper' ),
				'effects'         => array( 'thumbs' ),
				'label'           => __( 'Thumbnails per view', 'wp-swiper' ),
				'type'            => 'number',
				'responsive'      => true,
				'default'         => 6,
				'device_defaults' => array( 'mobile' => 4 ),
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
			// Also that of the thumbnails in the lightbox, unless it has its own.
			'thumbs_radius'         => array(
				'section'    => 'layout',
				'panel'      => 'thumbs',
				'heading'    => __( 'Look', 'wp-swiper' ),
				'effects'    => array( 'thumbs' ),
				'label'      => __( 'Thumbnail border radius (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 0,
				'min'        => 0,
				'max'        => 200,
				'step'       => 1,
			),
			// One row: the frame's width and its two colors.
			'thumbs_border_width'   => array(
				'section' => 'layout',
				'panel'   => 'thumbs',
				'effects' => array( 'thumbs' ),
				'group'   => 'thumbs_border',
				'label'   => __( 'Frame width (px)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 1,
				'min'     => 0,
				'max'     => 20,
				'step'    => 1,
			),
			'thumbs_border_color'   => array(
				'section' => 'layout',
				'panel'   => 'thumbs',
				'effects' => array( 'thumbs' ),
				'group'   => 'thumbs_border',
				'label'   => __( 'Frame color', 'wp-swiper' ),
				'type'    => 'color',
				'default' => '#dddddd',
			),
			'thumbs_active_color'   => array(
				'section' => 'layout',
				'panel'   => 'thumbs',
				'effects' => array( 'thumbs' ),
				'group'   => 'thumbs_border',
				'label'   => __( 'Active frame color', 'wp-swiper' ),
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
			),

			// Caption text (all slides of the slider).
			'caption_tag'           => array(
				'section' => 'caption',
				'label'   => __( 'Tag', 'wp-swiper' ),
				'type'    => 'select',
				'default' => 'div',
				'choices' => array(
					'div' => 'div',
					'p'   => 'p',
					'h1'  => 'h1',
					'h2'  => 'h2',
					'h3'  => 'h3',
					'h4'  => 'h4',
					'h5'  => 'h5',
					'h6'  => 'h6',
				),
			),
			'caption_font_size'     => array(
				'section'    => 'caption',
				'label'      => __( 'Font size (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 16,
				'min'        => 8,
				'max'        => 200,
				'step'       => 1,
			),
			'caption_weight'        => array(
				'section' => 'caption',
				'group'   => 'caption_style',
				'label'   => __( 'Font weight', 'wp-swiper' ),
				'type'    => 'select',
				'default' => '',
				'choices' => array(
					''    => __( 'Default (of the tag)', 'wp-swiper' ),
					'100' => '100',
					'200' => '200',
					'300' => '300',
					'400' => __( '400 (normal)', 'wp-swiper' ),
					'500' => '500',
					'600' => '600',
					'700' => __( '700 (bold)', 'wp-swiper' ),
					'800' => '800',
					'900' => '900',
				),
			),
			'caption_color'         => array(
				'section' => 'caption',
				'group'   => 'caption_style',
				'label'   => __( 'Color', 'wp-swiper' ),
				'type'    => 'color',
				'default' => '#ffffff',
			),
			'caption_orientation'   => array(
				'section' => 'caption',
				'label'   => __( 'Text direction', 'wp-swiper' ),
				'type'    => 'select',
				'default' => 'horizontal',
				'choices' => array(
					'horizontal' => __( 'Horizontal', 'wp-swiper' ),
					'down'       => __( 'Vertical, top to bottom', 'wp-swiper' ),
					'up'         => __( 'Vertical, bottom to top', 'wp-swiper' ),
				),
				'help'    => __( 'Vertical text runs along the left edge of the image.', 'wp-swiper' ),
			),
			'caption_bg'            => array(
				'section' => 'caption',
				'label'   => __( 'Background', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
			),
			'caption_bg_style'      => array(
				'section' => 'caption',
				'label'   => __( 'Background style', 'wp-swiper' ),
				'type'    => 'select',
				'default' => 'gradient',
				'choices' => array(
					'gradient' => __( 'Gradient (fades out)', 'wp-swiper' ),
					'solid'    => __( 'Solid', 'wp-swiper' ),
				),
				'help'    => __( 'The gradient runs from the image edge where the text is.', 'wp-swiper' ),
			),
			// One row, like Font weight and Color.
			'caption_bg_opacity'    => array(
				'section' => 'caption',
				'group'   => 'caption_bg',
				'label'   => __( 'Background opacity (%)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 65,
				'min'     => 0,
				'max'     => 100,
				'step'    => 5,
			),
			'caption_bg_color'      => array(
				'section' => 'caption',
				'group'   => 'caption_bg',
				'label'   => __( 'Background color', 'wp-swiper' ),
				'type'    => 'color',
				'default' => '#000000',
			),
			'caption_shadow'        => array(
				'section' => 'caption',
				'label'   => __( 'Text shadow', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => false,
				'help'    => __( 'Keeps the text readable on light images, e.g. without a background.', 'wp-swiper' ),
			),

			// Video slides (a video from the media library): how they play. The
			// page shows while the slider has one (admin.js). Only the video of
			// the slide in view plays, and only while the slider is on screen.
			'video_autoplay'        => array(
				'section' => 'advanced',
				'panel'   => 'video',
				'label'   => __( 'Play automatically', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'When its slide is shown. Browsers only start a video by themselves without sound, so a video with sound starts muted.', 'wp-swiper' ),
			),
			'video_muted'           => array(
				'section' => 'advanced',
				'panel'   => 'video',
				'label'   => __( 'Muted', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'Without sound at first; with Show controls the visitor can turn it on.', 'wp-swiper' ),
			),
			'video_loop'            => array(
				'section' => 'advanced',
				'panel'   => 'video',
				'label'   => __( 'Loop', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'Starts over at the end while its slide is shown.', 'wp-swiper' ),
			),
			'video_controls'        => array(
				'section' => 'advanced',
				'panel'   => 'video',
				'label'   => __( 'Show controls', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => false,
				'help'    => __( 'Play, sound and progress. Always on without Play automatically: the visitor starts the video.', 'wp-swiper' ),
			),
			'video_pause'           => array(
				'section' => 'advanced',
				'panel'   => 'video',
				'label'   => __( 'Pause when the slide is left', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'Off: it plays on unseen (sound too) and carries on from there when its slide is back.', 'wp-swiper' ),
			),
			'video_wait'            => array(
				'section' => 'advanced',
				'panel'   => 'video',
				'label'   => __( 'Next slide when the video ends', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => false,
				'help'    => __( 'With Autoplay (Controls) and Play automatically: on a video slide the slider waits for the end of the video instead of the delay. The video then plays once.', 'wp-swiper' ),
			),
			'video_preload'         => array(
				'section' => 'advanced',
				'panel'   => 'video',
				'label'   => __( 'Preload the next video', 'wp-swiper' ),
				'type'    => 'select',
				'default' => 'metadata',
				'choices' => array(
					'none'     => __( 'No: only when it plays', 'wp-swiper' ),
					'metadata' => __( 'Its first frame (light)', 'wp-swiper' ),
					'auto'     => __( 'The video itself (heavy)', 'wp-swiper' ),
				),
				'help'    => __( 'For the video of the next slide only; the others load when their slide comes up.', 'wp-swiper' ),
			),

			// The slider's outer block (.wp-swiper): classes and an id of its own.
			'css_class'             => array(
				'section'     => 'advanced',
				'panel'       => 'attributes',
				'label'       => __( 'CSS class', 'wp-swiper' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => 'my-slider',
				'help'        => __( 'One or more, separated by spaces. Added to the slider’s outer block, with those given where it is placed.', 'wp-swiper' ),
			),
			'css_id'                => array(
				'section'     => 'advanced',
				'panel'       => 'attributes',
				'label'       => __( 'ID', 'wp-swiper' ),
				'type'        => 'id',
				'default'     => '',
				'placeholder' => 'my-slider',
				'help'        => __( 'The id of the slider’s outer block: letters, digits, - and _. An id must be unique on a page, so do not place this slider twice on one page.', 'wp-swiper' ),
			),
		);
	}

	/**
	 * Whether the "wider active slide" layout applies to these settings
	 * (horizontal: the active slide is wider; vertical: it is taller). It needs
	 * the Slide effect and cropped images (Image fit "Cover").
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_expand( $settings ) {
		return $settings['expand_active'] && 'slide' === $settings['effect'] && 'contain' !== $settings['image_fit']
			&& ! self::is_auto_width( $settings ) && ! self::is_marquee( $settings );
	}

	/**
	 * Slide effect with "Width from image": slides as wide as their images.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_auto_width( $settings ) {
		return ! empty( $settings['auto_width'] ) && 'slide' === $settings['effect'];
	}

	/**
	 * Creative effect with the "Carousel" style.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_carousel( $settings ) {
		return 'creative' === $settings['effect'] && 'carousel' === $settings['creative_style'];
	}

	/**
	 * Creative "Carousel" style. Swiper's creative slides always span the
	 * slider; the visible image (.wp-swiper__media) takes the `width` share of
	 * it so that the middle slide and `neighbours` slides on each side fit:
	 * each neighbour `offset` (share of the image width) from the one before,
	 * shrinking by (1 - scale) per step.
	 *
	 * @param array $settings Slider settings.
	 * @return array|false [ neighbours, offset, scale, opacity, width ] (shares, not %).
	 */
	public static function carousel( $settings ) {
		if ( ! self::is_carousel( $settings ) ) {
			return false;
		}
		$neighbours = max( 1, min( 3, (int) $settings['creative_neighbours'] ) );
		$offset     = max( 0.2, min( 1, $settings['creative_offset'] / 100 ) );
		$scale      = max( 0.5, min( 1, (float) $settings['creative_scale'] ) );
		$last       = max( 0.05, 1 - ( 1 - $scale ) * $neighbours ); // Scale of the outermost one.

		return array(
			'neighbours' => $neighbours,
			'offset'     => $offset,
			'scale'      => $scale,
			'opacity'    => max( 0, min( 100, (int) $settings['creative_opacity'] ) ) / 100,
			// The middle image plus the outermost neighbours' far halves.
			'width'      => round( min( 1, 1 / ( 2 * $neighbours * $offset + $last ) ), 4 ),
		);
	}

	/**
	 * Thumbnails effect: one slide at a time and a strip of thumbnails that
	 * picks it. The strip is its only control for now (Controls is not used).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_thumbs( $settings ) {
		return 'thumbs' === $settings['effect'];
	}

	/**
	 * Slides that fade into one another: the Fade effect, or Thumbnails with
	 * the Fade transition. Neither has a direction (always horizontal).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_fade( $settings ) {
		return 'fade' === $settings['effect'] || ( self::is_thumbs( $settings ) && 'fade' === $settings['thumbs_transition'] );
	}

	/**
	 * Thumbnails effect: on which devices the strip is a column left or right
	 * of the slider (Thumbnails position is set per device).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => bool, 'tablet' => bool, 'mobile' => bool ]
	 */
	public static function thumbs_side( $settings ) {
		return array_map(
			function ( $position ) {
				return in_array( $position, array( 'left', 'right' ), true );
			},
			self::responsive( $settings, 'thumbs_position' )
		);
	}

	/**
	 * Thumbnail proportions on each device: a preset ("4-3") or, with
	 * "Custom", the two numbers of its own fields (per device as well).
	 *
	 * @param array $settings Slider settings.
	 * @return array device => [ width, height ], both above 0.
	 */
	public static function thumbs_ratio( $settings ) {
		$custom = self::responsive( $settings, 'thumbs_ratio_custom' );
		$ratios = array();

		foreach ( self::responsive( $settings, 'thumbs_ratio' ) as $device => $choice ) {
			// Both are "4x3" / "4-3": two numbers and a separator.
			$ratio             = array_map( 'floatval', preg_split( '/[x-]/', 'custom' === $choice ? $custom[ $device ] : $choice ) ) + array( 1, 1 );
			$ratios[ $device ] = $ratio[0] > 0 && $ratio[1] > 0 ? array( $ratio[0], $ratio[1] ) : array( 4.0, 3.0 );
		}
		return $ratios;
	}

	/**
	 * Proportions as typed ("4x3", "16 : 9", "2,35×1") in the stored form
	 * "4x3": two numbers from 0.1 to 100, at most two decimals.
	 *
	 * @param string $value Raw input.
	 * @return string '' if it is not two numbers.
	 */
	public static function normalize_ratio( $value ) {
		if ( ! preg_match( '/^\s*(\d+(?:[.,]\d+)?)\s*[x×х:\/*]\s*(\d+(?:[.,]\d+)?)\s*$/iu', (string) $value, $parts ) ) {
			return '';
		}
		$sides = array();
		foreach ( array( $parts[1], $parts[2] ) as $number ) {
			$side = max( 0.1, min( 100, (float) str_replace( ',', '.', $number ) ) );
			// With a point whatever the locale, and no trailing zeros: "2.35", "4".
			$sides[] = rtrim( rtrim( number_format( $side, 2, '.', '' ), '0' ), '.' );
		}
		return implode( 'x', $sides );
	}

	/**
	 * Custom thumbnail proportions were two fields (1.2.0), now one ("4x3"):
	 * values saved the old way are put together.
	 *
	 * @param array $values Saved settings, or one effect's saved Layout values.
	 * @return array
	 */
	private static function merge_ratio_fields( $values ) {
		if ( isset( $values['thumbs_ratio_custom'] ) || ! isset( $values['thumbs_ratio_width'], $values['thumbs_ratio_height'] ) ) {
			return $values;
		}
		$ratio = self::normalize_ratio( number_format( (float) $values['thumbs_ratio_width'], 2, '.', '' ) . 'x' . number_format( (float) $values['thumbs_ratio_height'], 2, '.', '' ) );
		if ( '' !== $ratio ) {
			$values['thumbs_ratio_custom'] = $ratio;
		}
		return $values;
	}

	/**
	 * Slide effect with "Continuous scroll".
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_marquee( $settings ) {
		return ! empty( $settings['marquee'] ) && 'slide' === $settings['effect'];
	}

	/**
	 * Slides per group for each device: Slide effect only, and not where the
	 * slider moves one slide at a time anyway (wider active slide, marquee).
	 *
	 * @param array $settings Slider settings.
	 * @return array|false [ 'desktop' => int, 'tablet' => int, 'mobile' => int ]
	 */
	public static function groups( $settings ) {
		if ( 'slide' !== $settings['effect'] || self::is_expand( $settings ) || self::is_marquee( $settings ) ) {
			return false;
		}
		return array_map( 'intval', self::responsive( $settings, 'slides_per_group' ) );
	}

	/**
	 * Visible slides per device in the wider active slide layout: Slides per
	 * view as a whole number, at least 2 (the active one plus a narrow one).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => int, 'tablet' => int, 'mobile' => int ]
	 */
	public static function expand_visible( $settings ) {
		return array_map(
			function ( $per_view ) {
				return max( 2, (int) round( $per_view ) );
			},
			self::responsive( $settings, 'slides_per_view' )
		);
	}

	/**
	 * Pages of the settings menu, in its order. Advanced's own fields are
	 * always in view beside the menu; a page shows the fields of its section
	 * (Layout, Caption) or those of Advanced given its name as `panel`.
	 *
	 * @return array page => [ title, dashicon ].
	 */
	public static function pages() {
		return array(
			'lightbox'   => array( __( 'Lightbox', 'wp-swiper' ), 'dashicons-format-gallery' ),
			'controls'   => array( __( 'Controls', 'wp-swiper' ), 'dashicons-leftright' ),
			'layout'     => array( __( 'Layout', 'wp-swiper' ), 'dashicons-layout' ),
			// The Thumbnails effect's strip (its menu item shows for that effect only).
			'thumbs'     => array( __( 'Thumbnails', 'wp-swiper' ), 'dashicons-images-alt2' ),
			'caption'    => array( __( 'Caption', 'wp-swiper' ), 'dashicons-editor-textcolor' ),
			// Video slides (its menu item shows while the slider has one).
			'video'      => array( __( 'Video', 'wp-swiper' ), 'dashicons-video-alt3' ),
			// The outer block's class and id.
			'attributes' => array( __( 'Custom', 'wp-swiper' ), 'dashicons-editor-code' ),
		);
	}

	/**
	 * Storage keys of a field per device. Responsive fields keep tablet and
	 * mobile values under suffixed keys; '' there means "inherit".
	 *
	 * @param string $key   Field name.
	 * @param array  $field Field definition.
	 * @return array device => storage key.
	 */
	public static function device_keys( $key, $field ) {
		if ( empty( $field['responsive'] ) ) {
			return array( 'desktop' => $key );
		}
		return array(
			'desktop' => $key,
			'tablet'  => $key . '_tablet',
			'mobile'  => $key . '_mobile',
		);
	}

	/**
	 * @return array Default values keyed by storage key.
	 */
	public static function defaults() {
		$defaults = array();
		foreach ( self::fields() as $key => $field ) {
			foreach ( self::device_keys( $key, $field ) as $device => $storage_key ) {
				if ( 'desktop' === $device ) {
					$defaults[ $storage_key ] = $field['default'];
				} else {
					$defaults[ $storage_key ] = isset( $field['device_defaults'][ $device ] ) ? $field['device_defaults'][ $device ] : '';
				}
			}
		}
		return $defaults;
	}

	/**
	 * Value of a responsive field on each device, with inheritance resolved
	 * (an empty tablet value takes desktop's, an empty mobile one tablet's).
	 *
	 * @param array  $settings Slider settings.
	 * @param string $key      Field name.
	 * @return array [ 'desktop' => v, 'tablet' => v, 'mobile' => v ]
	 */
	public static function responsive( $settings, $key ) {
		$desktop = $settings[ $key ];
		$tablet  = '' === $settings[ $key . '_tablet' ] ? $desktop : $settings[ $key . '_tablet' ];
		$mobile  = '' === $settings[ $key . '_mobile' ] ? $tablet : $settings[ $key . '_mobile' ];

		return array(
			'desktop' => $desktop,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		);
	}

	/**
	 * Saved settings merged over defaults.
	 *
	 * @param int $post_id Slider ID.
	 * @return array
	 */
	public static function get( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_SETTINGS, true );
		$saved = is_array( $saved ) ? $saved : array();

		// Before responsive fields (no version mark), a mobile height of 0
		// meant "same as desktop"; now that is an empty value.
		if ( empty( $saved['_version'] ) && isset( $saved['height_mobile'] ) && 0 === (int) $saved['height_mobile'] ) {
			$saved['height_mobile'] = '';
		}

		// Version 3 merged "Visible slides" into "Slides per view": a slider in
		// the wider active slide layout keeps the counts it showed before
		// (Slides per view was hidden, so unused, in that layout).
		if ( ( empty( $saved['_version'] ) || $saved['_version'] < 3 ) && ! empty( $saved['expand_active'] ) ) {
			$saved['slides_per_view']        = isset( $saved['visible_slides'] ) ? $saved['visible_slides'] : 5;
			$saved['slides_per_view_tablet'] = isset( $saved['visible_slides_tablet'] ) ? $saved['visible_slides_tablet'] : '';
			$saved['slides_per_view_mobile'] = isset( $saved['visible_slides_mobile'] ) ? $saved['visible_slides_mobile'] : 3;
		}

		// The progress bar pagination was removed.
		if ( isset( $saved['pagination'] ) && 'progressbar' === $saved['pagination'] ) {
			$saved['pagination'] = 'bullets';
		}

		// Cube shadows were the one Effect shadows checkbox before they got their
		// own: a cube that had it on keeps both.
		if ( $saved && ! isset( $saved['cube_shadow'] ) && ! empty( $saved['effect_shadows'] ) ) {
			$saved['cube_shadow']        = true;
			$saved['cube_slide_shadows'] = true;
		}

		// Custom thumbnail proportions: one field instead of two.
		$saved = self::merge_ratio_fields( $saved );
		if ( ! empty( $saved['layouts'] ) && is_array( $saved['layouts'] ) ) {
			$saved['layouts'] = array_map( array( __CLASS__, 'merge_ratio_fields' ), array_filter( $saved['layouts'], 'is_array' ) );
		}

		// Version 6: the lightbox radii are numbers of their own, empty = the
		// slider's. "Rounded corners in the lightbox" took the slider's; without
		// it the corners were square: 0 then (tablet and mobile inherit it).
		// Set in full: 1.2.0 had a "lightbox_radius" of its own, which may still
		// be saved, and would otherwise override the slider's.
		if ( $saved && ( empty( $saved['_version'] ) || $saved['_version'] < 6 ) ) {
			$radius = empty( $saved['lightbox_rounded'] ) ? 0 : '';
			foreach ( array( 'lightbox_radius', 'lightbox_thumbs_radius' ) as $key ) {
				$saved[ $key ]             = $radius;
				$saved[ $key . '_tablet' ] = '';
				$saved[ $key . '_mobile' ] = '';
			}
		}

		$settings = wp_parse_args( $saved, self::defaults() );

		// Version 4: a Height of 0 means 500px and "Auto" fills the parent block.
		// Before, every slider but the wider active slide one filled its parent
		// unless it had Image fit "Cover" and a height on every device; those
		// that did keep doing so. (Only saved sliders: new ones start at 500px.)
		if ( $saved && ( empty( $saved['_version'] ) || $saved['_version'] < 4 ) && ! self::is_expand( $settings ) ) {
			if ( 'contain' === $settings['image_fit'] || ! min( self::responsive( $settings, 'height' ) ) ) {
				$settings['height_unit'] = 'auto';
			}
		}

		// Version 5: Stack offset (Cards) is a % of the card width; its old
		// default, 16, spread the stack far outside the block: 8 as in Swiper.
		if ( $saved && ( empty( $saved['_version'] ) || $saved['_version'] < 5 ) ) {
			if ( 16 === (int) $settings['cards_offset'] ) {
				$settings['cards_offset'] = 8;
			}
			if ( ! empty( $settings['layouts'] ) && is_array( $settings['layouts'] ) ) {
				foreach ( $settings['layouts'] as $effect => $layout ) {
					if ( isset( $layout['cards_offset'] ) && 16 === (int) $layout['cards_offset'] ) {
						$settings['layouts'][ $effect ]['cards_offset'] = 8;
					}
				}
			}
		}

		return $settings;
	}

	/**
	 * Whether the slider takes its height from its parent block (Height unit
	 * "Auto") instead of a set Height.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_auto_height( $settings ) {
		return 'auto' === $settings['height_unit'];
	}

	/**
	 * CSS height per device: the Height in its unit, 0 = 500px.
	 *
	 * @param array $settings Slider settings (not "Auto").
	 * @return array [ 'desktop' => string, 'tablet' => string, 'mobile' => string ]
	 */
	public static function css_heights( $settings ) {
		$unit = $settings['height_unit'];

		return array_map(
			function ( $height ) use ( $unit ) {
				return $height ? (int) $height . $unit : '500px';
			},
			self::responsive( $settings, 'height' )
		);
	}

	/**
	 * Saved slides: list of [ 'id' => int, 'caption' => string, 'link' => string,
	 * 'new_tab' => bool, 'poster' => int ] (slides saved before "new tab" or
	 * video slides existed have no new_tab / poster key). "id": an image, or a
	 * video (its "poster": the image shown before it plays; 0 = none).
	 *
	 * @param int $post_id Slider ID.
	 * @return array
	 */
	public static function get_slides( $post_id ) {
		$slides = get_post_meta( $post_id, self::META_SLIDES, true );
		return is_array( $slides ) ? $slides : array();
	}

	/**
	 * Whether a media library item can be a slide: an image or a video.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_slide_media( $attachment_id ) {
		return wp_attachment_is_image( $attachment_id ) || self::is_video( $attachment_id );
	}

	/**
	 * Whether a slide's media is a video.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_video( $attachment_id ) {
		return (bool) wp_attachment_is( 'video', $attachment_id );
	}

	/**
	 * Width and height of a slide's media: an image's full size, a video's
	 * frame (from its metadata).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|false [ width, height ], both above 0.
	 */
	public static function media_size( $attachment_id ) {
		if ( self::is_video( $attachment_id ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			$size = array( isset( $meta['width'] ) ? (int) $meta['width'] : 0, isset( $meta['height'] ) ? (int) $meta['height'] : 0 );
		} else {
			$src  = wp_get_attachment_image_src( $attachment_id, 'full' );
			$size = $src ? array( (int) $src[1], (int) $src[2] ) : array( 0, 0 );
		}
		return $size[0] > 0 && $size[1] > 0 ? $size : false;
	}

	/**
	 * The image that stands for a video slide (before it plays, in the strip
	 * of thumbnails): its poster, else the video's own featured image.
	 *
	 * @param array $slide Slide data.
	 * @return int Attachment ID, 0 if none.
	 */
	public static function poster( $slide ) {
		if ( ! empty( $slide['poster'] ) && wp_attachment_is_image( $slide['poster'] ) ) {
			return (int) $slide['poster'];
		}
		$featured = (int) get_post_thumbnail_id( $slide['id'] );
		return $featured && wp_attachment_is_image( $featured ) ? $featured : 0;
	}

	/**
	 * Space-separated CSS class names, each run through sanitize_html_class().
	 *
	 * @param string $classes Raw input.
	 * @return string
	 */
	public static function sanitize_classes( $classes ) {
		return implode( ' ', array_unique( array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', trim( $classes ) ) ) ) ) );
	}

	/**
	 * An HTML id: letters, digits, "-" and "_" only, so it also works as a CSS
	 * selector and a link target (#id). Spaces become "-" ("hero slider").
	 *
	 * @param string $id Raw input.
	 * @return string
	 */
	public static function sanitize_id( $id ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', preg_replace( '/\s+/', '-', trim( $id ) ) );
	}

	/**
	 * Sanitize raw form input against the schema.
	 *
	 * @param array $input Raw $_POST values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$clean = array( '_version' => 6 ) + self::sanitize_fields( $input );

		// Layout values of every effect (see layouts()), sent by the editor as
		// JSON; the chosen effect's are the ones posted as fields.
		$layouts = isset( $input['layouts'] ) ? json_decode( (string) $input['layouts'], true ) : null;
		$layouts = is_array( $layouts ) ? $layouts : array();

		$clean['layouts'] = array();
		foreach ( array_keys( self::fields()['effect']['choices'] ) as $effect ) {
			if ( $effect === $clean['effect'] ) {
				$clean['layouts'][ $effect ] = array_intersect_key( $clean, array_flip( self::layout_keys() ) );
			} elseif ( isset( $layouts[ $effect ] ) && is_array( $layouts[ $effect ] ) ) {
				$clean['layouts'][ $effect ] = self::sanitize_fields( $layouts[ $effect ], 'layout' );
			}
		}

		return $clean;
	}

	/**
	 * Storage keys of the Layout fields: the values each effect keeps its own.
	 *
	 * @return string[]
	 */
	public static function layout_keys() {
		$keys = array();
		foreach ( self::fields() as $key => $field ) {
			if ( 'layout' === $field['section'] ) {
				$keys = array_merge( $keys, array_values( self::device_keys( $key, $field ) ) );
			}
		}
		return $keys;
	}

	/**
	 * Layout values of every effect, for the editor to swap in when the effect
	 * changes: the chosen effect's are the slider's settings; another one's
	 * are those saved for it, or the defaults if it was never used.
	 *
	 * @param array $settings Slider settings (see get()).
	 * @param array $saved    Saved per-effect layouts (settings' "layouts").
	 * @return array effect => [ storage key => value ]
	 */
	public static function layouts( $settings, $saved ) {
		$keys     = array_flip( self::layout_keys() );
		$defaults = array_intersect_key( self::defaults(), $keys );
		$layouts  = array();

		foreach ( array_keys( self::fields()['effect']['choices'] ) as $effect ) {
			if ( $effect === $settings['effect'] ) {
				$layouts[ $effect ] = array_intersect_key( $settings, $keys );
			} elseif ( isset( $saved[ $effect ] ) && is_array( $saved[ $effect ] ) ) {
				// Fields added since it was saved take their defaults.
				$layouts[ $effect ] = array_intersect_key( $saved[ $effect ], $keys ) + $defaults;
			} else {
				$layouts[ $effect ] = $defaults;
			}
		}
		return $layouts;
	}

	/**
	 * Sanitize raw values against the schema.
	 *
	 * @param array       $input   Raw values keyed by storage key.
	 * @param string|null $section Only this section's fields (null: all).
	 * @return array
	 */
	private static function sanitize_fields( $input, $section = null ) {
		$clean = array();

		foreach ( self::fields() as $key => $field ) {
			if ( null !== $section && $field['section'] !== $section ) {
				continue;
			}
			// Tablet/mobile values of responsive fields: empty inherits.
			foreach ( array_slice( self::device_keys( $key, $field ), 1 ) as $storage_key ) {
				$value = isset( $input[ $storage_key ] ) ? trim( (string) $input[ $storage_key ] ) : '';
				if ( 'select' === $field['type'] ) {
					$value = array_key_exists( $value, $field['choices'] ) ? $value : '';
				} elseif ( 'ratio' === $field['type'] ) {
					$value = self::normalize_ratio( $value );
				} elseif ( '' !== $value ) {
					$value = 'float' === $field['type'] ? round( (float) $value, 2 ) : (int) $value;
					$value = max( $field['min'], min( $field['max'], $value ) );
				}
				$clean[ $storage_key ] = $value;
			}

			$value = isset( $input[ $key ] ) ? $input[ $key ] : null;

			switch ( $field['type'] ) {
				case 'checkbox':
					$clean[ $key ] = ! empty( $value );
					break;

				case 'number':
					// A field that may stay empty on desktop too (`empty`): '' is kept.
					if ( ! empty( $field['empty'] ) && ( null === $value || '' === trim( (string) $value ) ) ) {
						$clean[ $key ] = '';
						break;
					}
					$value         = null === $value ? $field['default'] : (int) $value;
					$clean[ $key ] = max( $field['min'], min( $field['max'], $value ) );
					break;

				case 'float':
					$value         = null === $value ? $field['default'] : round( (float) $value, 2 );
					$clean[ $key ] = max( $field['min'], min( $field['max'], $value ) );
					break;

				case 'select':
					$value         = sanitize_text_field( (string) $value );
					$clean[ $key ] = array_key_exists( $value, $field['choices'] ) ? $value : $field['default'];
					break;

				case 'color':
					$color         = sanitize_hex_color( (string) $value );
					$clean[ $key ] = $color ? $color : $field['default'];
					break;

				case 'ratio': // Proportions, "4x3".
					$ratio         = self::normalize_ratio( (string) $value );
					$clean[ $key ] = '' !== $ratio ? $ratio : $field['default'];
					break;

				case 'text': // CSS class names: anything else is dropped.
					$clean[ $key ] = self::sanitize_classes( (string) $value );
					break;

				case 'id': // An HTML id.
					$clean[ $key ] = self::sanitize_id( (string) $value );
					break;
			}
		}

		return $clean;
	}
}
