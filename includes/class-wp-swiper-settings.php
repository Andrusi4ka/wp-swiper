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
	const SINGLE_SLIDE_EFFECTS = array( 'fade', 'cube', 'flip', 'cards', 'creative', 'thumbs', 'collage' );

	/**
	 * Effects that can be the background of their parent block ("Use as background").
	 */
	const BACKGROUND_EFFECTS = array( 'slide', 'fade', 'collage' );

	/**
	 * Effects with a main image that can zoom ("Zoom the main image"); Slide
	 * only with one slide in view or the wider active slide (is_zoom()).
	 */
	const ZOOM_EFFECTS = array( 'slide', 'fade', 'cube', 'flip', 'creative', 'cards', 'thumbs' );

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
					// Not a slider: the photos placed together, the others swapped in.
					'collage'   => __( 'Collage', 'wp-swiper' ),
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
			// Shown while some slide is a video (admin.js).
			'lightbox_videos'       => array(
				'section' => 'advanced',
				'panel'   => 'lightbox',
				'label'   => __( 'Videos in the lightbox', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'A click on a video slide opens it in the lightbox, with sound and controls. Off: the lightbox shows the images only.', 'wp-swiper' ),
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
			// One row: the text's width under the image, per device, and its limit.
			'description_lightbox_width' => array(
				'section'         => 'advanced',
				'panel'           => 'lightbox',
				'group'           => 'description_lightbox_width',
				'label'           => __( 'Text width (% of the window)', 'wp-swiper' ),
				'type'            => 'number',
				'responsive'      => true,
				'default'         => 75,
				'device_defaults' => array( 'mobile' => 100 ),
				'min'             => 30,
				'max'             => 100,
				'step'            => 5,
				'help'            => __( 'As wide as the image above it, within this width and the maximum; not narrower than 500px where the window allows.', 'wp-swiper' ),
			),
			'description_lightbox_max' => array(
				'section' => 'advanced',
				'panel'   => 'lightbox',
				'group'   => 'description_lightbox_width',
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
			// places, the others are swapped in one at a time (frontend.js collage()).
			'collage_layout'        => array(
				'section'         => 'layout',
				'effects'         => array( 'collage' ),
				'label'           => __( 'Style', 'wp-swiper' ),
				'type'            => 'select',
				'responsive'      => true,
				'default'         => 'feature-center',
				'device_defaults' => array( 'mobile' => 'masonry' ),
				'choices'         => array(
					'feature-left'   => __( 'Template: a big photo on the left (3 photos)', 'wp-swiper' ),
					'feature-center' => __( 'Template: a big photo in the middle (5 photos)', 'wp-swiper' ),
					'mosaic'         => __( 'Template: mosaic (5 photos)', 'wp-swiper' ),
					'banner'         => __( 'Template: a wide photo on top (4 photos)', 'wp-swiper' ),
					'grid'           => __( 'Grid', 'wp-swiper' ),
					'justified'      => __( 'Justified rows', 'wp-swiper' ),
					'masonry'        => __( 'Masonry', 'wp-swiper' ),
					'random'         => __( 'Random mosaic', 'wp-swiper' ),
				),
				'help'            => __( 'Templates, Grid and Random mosaic fill the Height; Random mosaic cuts it into places of different sizes, at random (Shuffle), shaped after their photos. Justified rows keep each photo’s proportions in rows across the width; Masonry in columns. Both are as tall as their photos make them; as a background, as tall as the block (the places stretched or shrunk to it).', 'wp-swiper' ),
			),
			// Which photos come first and where they sit: a number the Shuffle
			// button draws (admin.js); the same number, the same arrangement.
			'collage_seed'          => array(
				'section' => 'layout',
				'effects' => array( 'collage' ),
				'label'   => __( 'Order', 'wp-swiper' ),
				'type'    => 'seed',
				'default' => 1,
				'help'    => __( 'Masonry and Random mosaic: Shuffle mixes the photos, which ones show first and where, and with them the arrangement. It then stays as it is until you shuffle again.', 'wp-swiper' ),
			),
			'collage_count'         => array(
				'section'         => 'layout',
				'effects'         => array( 'collage' ),
				'label'           => __( 'Photos on screen', 'wp-swiper' ),
				'type'            => 'number',
				'responsive'      => true,
				'default'         => 6,
				'device_defaults' => array( 'mobile' => 4 ),
				'min'             => 2,
				'max'             => 30,
				'step'            => 1,
				'help'            => __( 'Grid, Justified rows, Masonry and Random mosaic; a template has its own number of places.', 'wp-swiper' ),
			),
			'collage_columns'       => array(
				'section'         => 'layout',
				'effects'         => array( 'collage' ),
				'label'           => __( 'Columns', 'wp-swiper' ),
				'type'            => 'number',
				'responsive'      => true,
				'default'         => 3,
				'device_defaults' => array( 'mobile' => 2 ),
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
				'device_defaults' => array( 'mobile' => 140 ),
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
				'device_defaults' => array( 'mobile' => 6 ),
				'min'             => 0,
				'max'             => 60,
				'step'            => 1,
			),
			'collage_swap'          => array(
				'section' => 'layout',
				'effects' => array( 'collage' ),
				'label'   => __( 'Swap in the other photos', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'With more photos than places: every so often a place, at random, fades to a photo not shown yet. The arrangement does not change.', 'wp-swiper' ),
			),
			'collage_swap_delay'    => array(
				'section' => 'layout',
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
				'section' => 'layout',
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
				'section' => 'layout',
				'effects' => array( 'collage' ),
				'label'   => __( 'Pause on hover', 'wp-swiper' ),
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
				'help'       => __( 'Every image is as tall as the Height and as wide as these proportions make it; Slides per view of them share the row and the free width goes around them (Distribution). Space between slides is the least space left; where they do not fit with it, they get smaller. Off: the images fill the row.', 'wp-swiper' ),
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
			),
			'thumbs_per_view'       => array(
				'section'         => 'layout',
				'panel'           => 'thumbs',
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

			// Slide descriptions: under the slider, the current slide's only (see
			// can_describe() for the sliders that show them). The page is greyed
			// while no slide has one (admin.js).
			'description_font_size' => array(
				'section'    => 'description',
				'label'      => __( 'Font size (px)', 'wp-swiper' ),
				'type'       => 'number',
				'responsive' => true,
				'default'    => 16,
				'min'        => 8,
				'max'        => 200,
				'step'       => 1,
			),
			'description_weight'    => array(
				'section' => 'description',
				'label'   => __( 'Font weight', 'wp-swiper' ),
				'type'    => 'select',
				'default' => '',
				'choices' => array(
					''    => __( 'Default (of the page)', 'wp-swiper' ),
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
			'description_align'     => array(
				'section'   => 'description',
				'label'     => __( 'Alignment', 'wp-swiper' ),
				'type'      => 'select',
				'segmented' => true,
				'default'   => 'left',
				'choices'   => array(
					'left'   => __( 'Left', 'wp-swiper' ),
					'center' => __( 'Center', 'wp-swiper' ),
					'right'  => __( 'Right', 'wp-swiper' ),
				),
			),
			// How the text changes with the slide (see description_motion()).
			'description_transition' => array(
				'section' => 'description',
				'label'   => __( 'Text transition', 'wp-swiper' ),
				'type'    => 'select',
				'default' => 'effect',
				'choices' => array(
					'effect' => __( 'As the slider', 'wp-swiper' ),
					'fade'   => __( 'Fade', 'wp-swiper' ),
					'slide'  => __( 'Slide', 'wp-swiper' ),
					'none'   => __( 'None', 'wp-swiper' ),
				),
				'help'    => __( 'As the slider: the text moves the way the slides do (slides, turns with a cube, flips...), at the same speed, once a slide is let go.', 'wp-swiper' ),
			),
			'description_offset'    => array(
				'section' => 'description',
				'label'   => __( 'Gap to the slider (px)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 16,
				'min'     => 0,
				'max'     => 200,
				'step'    => 1,
				'help'    => __( 'Under the slider and its pagination, if that is outside.', 'wp-swiper' ),
			),
			'description_own_color' => array(
				'section' => 'description',
				'label'   => __( 'Own text color', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => false,
				'help'    => __( 'Off: the colour of the page’s text.', 'wp-swiper' ),
			),
			'description_color'     => array(
				'section' => 'description',
				'label'   => __( 'Color', 'wp-swiper' ),
				'type'    => 'color',
				'default' => '#333333',
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

			// The slider as the background of a block it is in (a row, a column),
			// found by a class given to that block (frontend.js background()).
			'background'            => array(
				'section' => 'advanced',
				'panel'   => 'background',
				'effects' => self::BACKGROUND_EFFECTS,
				'label'   => __( 'Use as background', 'wp-swiper' ),
				'type'    => 'checkbox',
				'default' => false,
				'help'    => __( 'The slider fills the block with the class below, behind its content, and plays on its own: Autoplay and Infinite loop are on; arrows, pagination, keyboard, slide links, the lightbox and Height are not used, the images are cropped to fill.', 'wp-swiper' ),
			),
			'background_class'      => array(
				'section'     => 'advanced',
				'panel'       => 'background',
				'effects'     => self::BACKGROUND_EFFECTS,
				'label'       => __( 'Parent block class', 'wp-swiper' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => 'hero-block',
				'help'        => __( 'Give this class to the row or column the slider is in (WPBakery: “Extra class name”) and enter it here. The nearest block around the slider with it is used; without one, the slider shows as usual.', 'wp-swiper' ),
			),
			// One row: how dark, and in what color.
			'background_overlay_opacity' => array(
				'section' => 'advanced',
				'panel'   => 'background',
				'effects' => self::BACKGROUND_EFFECTS,
				'group'   => 'background_overlay',
				'label'   => __( 'Overlay opacity (%)', 'wp-swiper' ),
				'type'    => 'number',
				'default' => 0,
				'min'     => 0,
				'max'     => 100,
				'step'    => 5,
				'help'    => __( 'A color over the slides, so that the content on them reads well. 0 = none.', 'wp-swiper' ),
			),
			'background_overlay_color' => array(
				'section' => 'advanced',
				'panel'   => 'background',
				'effects' => self::BACKGROUND_EFFECTS,
				'group'   => 'background_overlay',
				'label'   => __( 'Overlay color', 'wp-swiper' ),
				'type'    => 'color',
				'default' => '#000000',
			),
			// Per device; empty (desktop too) = none: the block as tall as its content.
			'background_min_height' => array(
				'section'     => 'advanced',
				'panel'       => 'background',
				'effects'     => self::BACKGROUND_EFFECTS,
				'label'       => __( 'Minimum block height (px)', 'wp-swiper' ),
				'type'        => 'number',
				'responsive'  => true,
				'empty'       => true,
				'default'     => '',
				'min'         => 0,
				'max'         => 2000,
				'step'        => 1,
				'placeholder' => __( 'None', 'wp-swiper' ),
				'help'        => __( 'The block is as tall as its content (or as the page builder makes it); this keeps it at least this tall. Empty: no minimum.', 'wp-swiper' ),
			),
		);
	}

	/**
	 * "Use as background": on, with an effect that can be one.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_background( $settings ) {
		return ! empty( $settings['background'] ) && in_array( $settings['effect'], self::BACKGROUND_EFFECTS, true );
	}

	/**
	 * Whether the slider shows its slides' descriptions (under it, the current
	 * slide's): not as a background (behind a block's content), nor without a
	 * current slide (Continuous scroll, Collage), nor beside a vertical slider.
	 *
	 * @param array $settings Slider settings (Direction as shown: render.php
	 *                        makes fading sliders horizontal).
	 * @return bool
	 */
	public static function can_describe( $settings ) {
		return 'horizontal' === $settings['direction']
			&& ! self::is_background( $settings )
			&& ! self::is_marquee( $settings )
			&& ! self::is_collage( $settings );
	}

	/**
	 * How a description changes with its slide (frontend.css: the motion's
	 * animations): "Text transition", where "As the slider" takes the motion
	 * nearest to the effect's.
	 *
	 * @param array $settings Slider settings.
	 * @return string none | fade | slide | push | zoom | cube | flip | coverflow | cards | rotate
	 */
	public static function description_motion( $settings ) {
		// Fade, Slide or None (a choice: sanitized).
		if ( 'effect' !== $settings['description_transition'] ) {
			return $settings['description_transition'];
		}
		switch ( $settings['effect'] ) {
			case 'thumbs':
				return 'fade' === $settings['thumbs_transition'] ? 'fade' : 'slide';
			case 'creative':
				$styles = array(
					'push'     => 'push',
					'zoom'     => 'zoom',
					'apart'    => 'slide',
					'flip'     => 'flip',
					'rotate'   => 'rotate',
					'door'     => 'cube',
					'carousel' => 'coverflow',
				);
				return isset( $styles[ $settings['creative_style'] ] ) ? $styles[ $settings['creative_style'] ] : 'push';
			case 'fade':
			case 'cube':
			case 'flip':
			case 'coverflow':
			case 'cards':
				return $settings['effect'];
		}
		return 'slide';
	}

	/**
	 * The markup a slide description may have: paragraphs, headings, line
	 * breaks, bold, italic, underline (a span the editor styles), links and lists.
	 *
	 * @return array wp_kses() allowed tags.
	 */
	public static function description_tags() {
		return array(
			'p'      => array(),
			'h1'     => array(),
			'h2'     => array(),
			'h3'     => array(),
			'h4'     => array(),
			'h5'     => array(),
			'h6'     => array(),
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'span'   => array( 'style' => true ),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
		);
	}

	/**
	 * The markup a caption may have: bold, italic, underline (a span the
	 * editor styles) and line breaks. It sits over the image in one tag of
	 * its own (Caption page), letting clicks through to the slide: no blocks,
	 * no links.
	 *
	 * @return array wp_kses() allowed tags.
	 */
	public static function caption_tags() {
		return array(
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'span'   => array( 'style' => true ),
		);
	}

	/**
	 * A slide's own image position ("Cover": the point kept in view, on every
	 * device): "x y", percent of its width and height, 0–100 to one decimal.
	 * '' for none (the slider's Image position) or anything else.
	 *
	 * @param string $value Raw input, e.g. "30 72.5".
	 * @return string
	 */
	public static function sanitize_position( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\s*(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)\s*$/', $value, $match ) ) {
			return '';
		}
		// %F: a dot whatever the locale (PHP 7.4); "30.0" as "30".
		$point = array_map(
			function ( $number ) {
				return rtrim( rtrim( sprintf( '%.1F', min( 100, max( 0, (float) $number ) ) ), '0' ), '.' );
			},
			array( $match[1], $match[2] )
		);
		return $point[0] . ' ' . $point[1];
	}

	/**
	 * A slide description from the editor: its allowed markup only; '' when
	 * no text is left.
	 *
	 * @param string $html Raw input.
	 * @return string
	 */
	public static function sanitize_description( $html ) {
		return self::sanitize_rich_text( $html, self::description_tags() );
	}

	/**
	 * A caption from the editor (or a plain one saved before it had one): its
	 * allowed markup only; '' when no text is left.
	 *
	 * @param string $html Raw input.
	 * @return string
	 */
	public static function sanitize_caption( $html ) {
		// Paragraphs and other blocks (text pasted in) become lines, so their
		// words do not run together once the blocks are gone.
		$html = preg_replace( '#\s*</(?:p|div|h[1-6]|li|blockquote)>\s*#i', '<br>', (string) $html );
		$html = self::sanitize_rich_text( $html, self::caption_tags() );
		return preg_replace( '#^(?:\s*<br\s*/?>)+|(?:<br\s*/?>\s*)+$#i', '', $html );
	}

	/**
	 * Editor HTML with the given tags only, trimmed; '' when no text is left.
	 *
	 * @param string $html Raw input.
	 * @param array  $tags wp_kses() allowed tags.
	 * @return string
	 */
	private static function sanitize_rich_text( $html, $tags ) {
		$html = trim( wp_kses( (string) $html, $tags ) );
		// The editor's empty paragraph is "<p>&nbsp;</p>": no-break spaces count as none.
		$text = preg_replace( '/[\s\x{00A0}]+/u', '', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
		return '' === (string) $text ? '' : $html;
	}

	/**
	 * "Zoom the main image": on, with an effect that shows one main image
	 * (Slide: one slide in view on every device, or the wider active slide).
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_zoom( $settings ) {
		if ( empty( $settings['zoom'] ) || ! in_array( $settings['effect'], self::ZOOM_EFFECTS, true ) ) {
			return false;
		}
		if ( 'slide' !== $settings['effect'] ) {
			return true;
		}
		if ( self::is_marquee( $settings ) || self::is_auto_width( $settings ) ) {
			return false;
		}
		return self::is_expand( $settings ) || max( self::responsive( $settings, 'slides_per_view' ) ) <= 1;
	}

	/**
	 * How long the zoom lasts (ms): the one set, else as long as a slide is
	 * shown with Autoplay (its delay plus the transition), else 6000.
	 *
	 * @param array $settings Slider settings.
	 * @return int
	 */
	public static function zoom_duration( $settings ) {
		if ( '' !== $settings['zoom_duration'] ) {
			return (int) $settings['zoom_duration'];
		}
		return $settings['autoplay'] ? (int) $settings['autoplay_delay'] + (int) $settings['speed'] : 6000;
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
	 * Collage effect: the photos placed together, not a slider.
	 *
	 * @param array $settings Slider settings.
	 * @return bool
	 */
	public static function is_collage( $settings ) {
		return 'collage' === $settings['effect'];
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
			$ratios[ $device ] = self::ratio_sides( 'custom' === $choice ? $custom[ $device ] : $choice );
		}
		return $ratios;
	}

	/**
	 * Image proportions on each device (Slide effect): [ width, height ], or
	 * false where they are "Off" (the images fill the row there); false
	 * altogether without any. Only for a horizontal row of slides of one
	 * width: not with "Width from image", the wider active slide or as a
	 * background (it fills its block).
	 *
	 * @param array $settings Slider settings.
	 * @return array|false device => [ width, height ] | false
	 */
	public static function slide_ratios( $settings ) {
		if ( 'slide' !== $settings['effect'] || 'horizontal' !== $settings['direction']
			|| self::is_auto_width( $settings ) || self::is_expand( $settings ) || self::is_background( $settings ) ) {
			return false;
		}
		$custom = self::responsive( $settings, 'slide_ratio_custom' );
		$ratios = array();
		foreach ( self::responsive( $settings, 'slide_ratio' ) as $device => $choice ) {
			$ratios[ $device ] = 'off' === $choice ? false : self::ratio_sides( 'custom' === $choice ? $custom[ $device ] : $choice );
		}
		return array_filter( $ratios ) ? $ratios : false;
	}

	/**
	 * The two sides of proportions stored as "4x3" (Custom) or "4-3" (a preset).
	 *
	 * @param string $value Two numbers and a separator.
	 * @return array [ width, height ], both above 0 (4:3 for anything else).
	 */
	private static function ratio_sides( $value ) {
		$ratio = array_map( 'floatval', preg_split( '/[x-]/', (string) $value ) ) + array( 1, 1 );
		return $ratio[0] > 0 && $ratio[1] > 0 ? array( $ratio[0], $ratio[1] ) : array( 4.0, 3.0 );
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
	 * Whether an attachment is an image (an SVG too, which WordPress itself
	 * does not count as one).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_image( $attachment_id ) {
		return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id )
			&& 0 === strpos( (string) get_post_mime_type( $attachment_id ), 'image/' );
	}

	/**
	 * The continuous scroll's separator: what it is and its size per device;
	 * false without one (or with "Own image" and no image chosen).
	 *
	 * @param array $settings Slider settings.
	 * @return array|false [ 'type' => 'dot'|'image', 'image' => int, 'size' => [ device => int ] ]
	 */
	public static function separator( $settings ) {
		if ( ! self::is_marquee( $settings ) || empty( $settings['marquee_separator'] ) || 'none' === $settings['marquee_separator'] ) {
			return false;
		}
		$image = (int) $settings['marquee_separator_image'];
		if ( 'image' === $settings['marquee_separator'] && ! self::is_image( $image ) ) {
			return false;
		}
		return array(
			'type'  => $settings['marquee_separator'],
			'image' => 'image' === $settings['marquee_separator'] ? $image : 0,
			'size'  => array_map( 'intval', self::responsive( $settings, 'marquee_separator_size' ) ),
		);
	}

	/**
	 * Space between the slides per device: the one set, and with a separator
	 * at least its size (so it never covers the images).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'desktop' => int, 'tablet' => int, 'mobile' => int ]
	 */
	public static function gaps( $settings ) {
		$gaps      = array_map( 'intval', self::responsive( $settings, 'space_between' ) );
		$separator = self::separator( $settings );
		if ( $separator ) {
			foreach ( $gaps as $device => $gap ) {
				$gaps[ $device ] = max( $gap, $separator['size'][ $device ] );
			}
		}
		return $gaps;
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
			'lightbox'    => array( __( 'Lightbox', 'wp-swiper' ), 'dashicons-format-gallery' ),
			'controls'    => array( __( 'Controls', 'wp-swiper' ), 'dashicons-leftright' ),
			'layout'      => array( __( 'Layout', 'wp-swiper' ), 'dashicons-layout' ),
			// The Thumbnails effect's strip (its menu item shows for that effect only).
			'thumbs'      => array( __( 'Thumbnails', 'wp-swiper' ), 'dashicons-images-alt2' ),
			'caption'     => array( __( 'Caption', 'wp-swiper' ), 'dashicons-editor-textcolor' ),
			// The slides' descriptions under the slider.
			'description' => array( __( 'Description', 'wp-swiper' ), 'dashicons-text-page' ),
			// Video slides (its menu item shows while the slider has one).
			'video'       => array( __( 'Video', 'wp-swiper' ), 'dashicons-video-alt3' ),
			// The outer block's class and id.
			'attributes'  => array( __( 'Custom', 'wp-swiper' ), 'dashicons-editor-code' ),
			// The slider behind its block's content (its menu item shows for the effects that can be).
			'background'  => array( __( 'Background', 'wp-swiper' ), 'dashicons-format-image' ),
		);
	}

	/**
	 * The blocks some pages of the menu are in: a title over each and its
	 * fields in this order (admin.js hides a block with none of them shown).
	 * A field of such a page in no block would come after them.
	 *
	 * @return array page => [ block => [ 'title' => string, 'fields' => string[] ] ]
	 */
	public static function blocks() {
		return array(
			'controls' => array(
				'movement'         => array(
					'title'  => __( 'Movement', 'wp-swiper' ),
					'fields' => array( 'speed', 'loop', 'autoplay', 'autoplay_delay', 'pause_on_hover' ),
				),
				'navigation'       => array(
					'title'  => __( 'Arrows and keyboard', 'wp-swiper' ),
					'fields' => array( 'navigation', 'navigation_color', 'navigation_size', 'keyboard' ),
				),
				'pagination'       => array(
					'title'  => __( 'Pagination', 'wp-swiper' ),
					'fields' => array( 'pagination', 'pagination_position', 'pagination_position_vertical', 'pagination_placement', 'pagination_offset' ),
				),
				'pagination_style' => array(
					'title'  => __( 'Pagination style', 'wp-swiper' ),
					'fields' => array(
						'pagination_bullet_width',
						'pagination_bullet_height',
						'pagination_bullet_radius',
						'pagination_bullet_gap',
						'pagination_bullet_active',
						'pagination_fraction_size',
						'pagination_colors',
						'pagination_color',
						'pagination_inactive_color',
						'pagination_inactive_opacity',
					),
				),
			),
			'layout'   => array(
				// The chosen effect's own settings (the others are hidden), its
				// style first: what follows depends on it.
				'effect'      => array(
					'title'  => __( 'Effect', 'wp-swiper' ),
					'fields' => array(
						'creative_style',
						'creative_neighbours',
						'creative_offset',
						'creative_scale',
						'creative_opacity',
						'thumbs_transition',
						'coverflow_rotate',
						'coverflow_depth',
						'coverflow_stretch',
						'coverflow_scale',
						'cube_shadow',
						'cube_shadow_offset',
						'cube_shadow_scale',
						'cube_shadow_opacity',
						'cube_slide_shadows',
						'cards_offset',
						'cards_tilt',
						'cards_rotate',
						'effect_shadows',
					),
				),
				'arrangement' => array(
					'title'  => __( 'Arrangement', 'wp-swiper' ),
					'fields' => array( 'collage_layout', 'collage_seed', 'collage_count', 'collage_columns', 'collage_row_height', 'collage_row_exact', 'collage_gap' ),
				),
				'swapping'    => array(
					'title'  => __( 'Photo swapping', 'wp-swiper' ),
					'fields' => array( 'collage_swap', 'collage_swap_delay', 'collage_swap_speed', 'collage_pause' ),
				),
				'slides'      => array(
					'title'  => __( 'Slides', 'wp-swiper' ),
					'fields' => array( 'slides_per_view', 'auto_width', 'slides_per_group', 'space_between', 'centered_slides', 'expand_active', 'active_width', 'marquee', 'marquee_speed', 'marquee_direction', 'marquee_separator', 'marquee_separator_image', 'marquee_separator_size', 'marquee_separator_color' ),
				),
				'size'        => array(
					'title'  => __( 'Size and fit', 'wp-swiper' ),
					'fields' => array( 'height', 'slide_ratio', 'slide_ratio_custom', 'slide_distribution', 'image_fit', 'image_position', 'image_align', 'image_align_vertical' ),
				),
				'look'        => array(
					'title'  => __( 'Look', 'wp-swiper' ),
					'fields' => array( 'border_radius', 'image_border', 'image_border_width', 'image_border_color' ),
				),
				'zoom'        => array(
					'title'  => __( 'Zoom', 'wp-swiper' ),
					'fields' => array( 'zoom', 'zoom_direction', 'zoom_amount', 'zoom_origin', 'zoom_duration' ),
				),
			),
			'thumbs'   => array(
				'placement' => array(
					'title'  => __( 'Placement', 'wp-swiper' ),
					'fields' => array( 'thumbs_position', 'thumbs_align', 'thumbs_spacing' ),
				),
				'size'      => array(
					'title'  => __( 'Size', 'wp-swiper' ),
					'fields' => array( 'thumbs_per_view', 'thumbs_height', 'thumbs_gap', 'thumbs_ratio', 'thumbs_ratio_custom' ),
				),
				'look'      => array(
					'title'  => __( 'Look', 'wp-swiper' ),
					'fields' => array( 'thumbs_radius', 'thumbs_border_width', 'thumbs_border_color', 'thumbs_active_color', 'thumbs_opacity' ),
				),
			),
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
	 * 'new_tab' => bool, 'poster' => int, 'embed' => string, 'description' =>
	 * string ] (slides saved before "new tab", video slides or descriptions
	 * existed have no new_tab / poster / embed / description key). A
	 * description: HTML (see description_tags()); a caption too (see
	 * caption_tags()), plain text when saved before it had an editor. "id": an image, or a video (its "poster": the image shown
	 * before it plays; 0 = none); or "embed": a YouTube / Vimeo link (id 0).
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
	 * Whether a saved slide can be shown: a YouTube / Vimeo link it can read,
	 * or an image or video still in the media library.
	 *
	 * @param array $slide Slide data.
	 * @return bool
	 */
	public static function is_slide( $slide ) {
		if ( ! empty( $slide['embed'] ) ) {
			return (bool) self::parse_embed( $slide['embed'] );
		}
		return ! empty( $slide['id'] ) && self::is_slide_media( $slide['id'] );
	}

	/**
	 * A YouTube or Vimeo link as its provider and video id ("hash": an
	 * unlisted Vimeo video's key; "vertical": a YouTube Short).
	 *
	 * @param string $url Video page or player address.
	 * @return array|false [ 'provider', 'id', 'hash', 'vertical' ]
	 */
	public static function parse_embed( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtube-nocookie\.com/embed/|youtu\.be/)([\w-]{11})~i', $url, $match ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $match[1],
				'hash'     => '',
				'vertical' => false !== stripos( $url, '/shorts/' ),
			);
		}
		if ( preg_match( '~^https?://(?:www\.)?(?:player\.)?vimeo\.com/(?:.*?/)?(\d{6,})(?:/([\da-f]{6,}))?~i', $url, $match ) ) {
			$hash = isset( $match[2] ) ? $match[2] : '';
			if ( ! $hash && preg_match( '~[?&]h=([\da-f]+)~i', $url, $key ) ) {
				$hash = $key[1];
			}
			return array(
				'provider' => 'vimeo',
				'id'       => $match[1],
				'hash'     => $hash,
				'vertical' => false,
			);
		}
		return false;
	}

	/**
	 * A YouTube / Vimeo video's size, thumbnail and title from the service
	 * (oEmbed), kept for a week (an hour when the service did not answer).
	 *
	 * @param string $url Video address.
	 * @return array [ 'width', 'height', 'thumb', 'title' ], empty if unknown.
	 */
	public static function embed_meta( $url ) {
		$key  = 'wp_swiper_oembed_' . md5( $url );
		$meta = get_transient( $key );
		if ( false === $meta ) {
			$data = _wp_oembed_get_object()->get_data( $url );
			$meta = $data ? array(
				'width'  => isset( $data->width ) ? (int) $data->width : 0,
				'height' => isset( $data->height ) ? (int) $data->height : 0,
				'thumb'  => isset( $data->thumbnail_url ) ? esc_url_raw( $data->thumbnail_url ) : '',
				'title'  => isset( $data->title ) ? sanitize_text_field( $data->title ) : '',
			) : array();
			set_transient( $key, $meta, $data ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
		}
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Width and height of a slide: its image's or video's, a YouTube / Vimeo
	 * video's from the service (16:9 when unknown, 9:16 for a YouTube Short).
	 *
	 * @param array $slide Slide data.
	 * @return array|false [ width, height ]
	 */
	public static function slide_size( $slide ) {
		if ( empty( $slide['embed'] ) ) {
			return self::media_size( $slide['id'] );
		}
		$embed = self::parse_embed( $slide['embed'] );
		$meta  = self::embed_meta( $slide['embed'] );
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) && ! ( $embed && $embed['vertical'] ) ) {
			return array( $meta['width'], $meta['height'] );
		}
		return $embed && $embed['vertical'] ? array( 9, 16 ) : array( 16, 9 );
	}

	/**
	 * A YouTube / Vimeo video's own thumbnail: YouTube's largest (the page
	 * falls back to `fallback` where it has none), Vimeo's from the service;
	 * `small` for thumbnails (YouTube's 16:9 one: its larger ones that are
	 * always there have black bands in the picture).
	 *
	 * @param string $url Video address.
	 * @return array [ 'src', 'fallback', 'small' ], empty strings if none.
	 */
	public static function embed_thumb( $url ) {
		$embed = self::parse_embed( $url );
		if ( $embed && 'youtube' === $embed['provider'] ) {
			return array(
				'src'      => 'https://i.ytimg.com/vi/' . $embed['id'] . '/maxresdefault.jpg',
				'fallback' => 'https://i.ytimg.com/vi/' . $embed['id'] . '/hqdefault.jpg',
				'small'    => 'https://i.ytimg.com/vi/' . $embed['id'] . '/mqdefault.jpg',
			);
		}
		$meta  = $embed ? self::embed_meta( $url ) : array();
		$thumb = empty( $meta['thumb'] ) ? '' : $meta['thumb'];
		return array(
			'src'      => $thumb,
			'fallback' => '',
			'small'    => $thumb,
		);
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
		// A YouTube / Vimeo video has its own thumbnail instead (embed_thumb()).
		if ( ! empty( $slide['embed'] ) || empty( $slide['id'] ) ) {
			return 0;
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

				case 'seed': // The Collage's shuffle: a whole number from 1.
					$clean[ $key ] = null === $value ? $field['default'] : max( 1, min( 999999, absint( $value ) ) );
					break;

				case 'image': // An image of the media library (its ID); 0 = none.
					$clean[ $key ] = self::is_image( absint( $value ) ) ? absint( $value ) : 0;
					break;
			}
		}

		return $clean;
	}
}
