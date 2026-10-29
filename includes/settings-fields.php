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
	// The text's width under the image, per device; its limit on its own row under it.
	'description_lightbox_width' => array(
		'section'         => 'advanced',
		'panel'           => 'lightbox',
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
		'device_defaults' => array( 'mobile' => 4 ),
		'min'             => 2,
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
		'label'      => __( 'Space between slides (px)', 'wp-swiper' ),
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
	),

	// No Tag here: each line of a caption is a paragraph or a heading of its
	// own (the Slide info window's Format); see legacy_caption_tag() for
	// the captions saved with the slider's one.
	// The captions' font size, per device: every slide's, unless it has its
	// own (Slide info window, own_font_size_vars()). Empty: 16px. A slider
	// saved with one of the old Font size fields gets it (get(), caption_sizes()).
	'caption_size'          => array(
		'section'     => 'caption',
		'group'       => 'caption_style',
		'label'       => __( 'Font size (px)', 'wp-swiper' ),
		'type'        => 'number',
		'responsive'  => true,
		'empty'       => true,
		'default'     => '',
		'min'         => 8,
		'max'         => 200,
		'step'        => 1,
		'placeholder' => '16',
		'help'        => __( 'For the captions of all slides. A slide with a font size of its own (its Edit window) keeps that one. Empty: 16px.', 'wp-swiper' ),
	),
	// No Font weight here: each slide's caption has its own; see
	// legacy_font_weights() for the sliders saved with one.
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
	// One row: the style and the color (with its opacity; until 3.0 the
	// opacity was a field of its own, merge_caption_bg_opacity(), get()).
	'caption_bg_style'      => array(
		'section' => 'caption',
		'group'   => 'caption_bg',
		'label'   => __( 'Background style', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'gradient',
		'choices' => array(
			'gradient' => __( 'Gradient (fades out)', 'wp-swiper' ),
			'solid'    => __( 'Solid', 'wp-swiper' ),
		),
		'help'    => __( 'The gradient runs from the image edge where the text is.', 'wp-swiper' ),
	),
	'caption_bg_color'      => array(
		'section' => 'caption',
		'group'   => 'caption_bg',
		'label'   => __( 'Background color', 'wp-swiper' ),
		'type'    => 'alpha_color',
		'default' => 'rgba(0,0,0,0.65)',
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
	// No Font size here either: each slide's description has its own.
	// Nor a Font weight: each slide's description has its own.
	// No Alignment field: each paragraph has its own (the editor's buttons);
	// see legacy_description_align() for the sliders saved with one.
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
	// Per device (empty: the larger screen's).
	'description_offset'    => array(
		'section'    => 'description',
		'label'      => __( 'Gap to the slider (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 16,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
		'help'       => __( 'Under the slider and its pagination, if that is outside.', 'wp-swiper' ),
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
		'panel'   => 'layout',
		'effects' => self::BACKGROUND_EFFECTS,
		'label'   => __( 'Use as background', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'The slider fills the block with the class below, behind its content, and plays on its own: Autoplay and Infinite loop are on; arrows, pagination, keyboard, slide links, captions, descriptions, the lightbox and Height are not used, the images are cropped to fill.', 'wp-swiper' ),
	),
	'background_class'      => array(
		'section'     => 'advanced',
		'panel'       => 'layout',
		'effects'     => self::BACKGROUND_EFFECTS,
		'label'       => __( 'Parent block class', 'wp-swiper' ),
		'type'        => 'text',
		'default'     => '',
		'placeholder' => 'hero-block',
		'help'        => __( 'Give this class to the row or column the slider is in (WPBakery: “Extra class name”) and enter it here. The nearest block around the slider with it is used; without one, the slider shows as usual.', 'wp-swiper' ),
	),
	// The color with its opacity (how dark); opacity 0 = none. Until 3.0 the
	// opacity was a field of its own (background_overlay_opacity, get()).
	'background_overlay_color' => array(
		'section' => 'advanced',
		'panel'   => 'layout',
		'effects' => self::BACKGROUND_EFFECTS,
		'label'   => __( 'Overlay color', 'wp-swiper' ),
		'type'    => 'alpha_color',
		'default' => 'rgba(0,0,0,0)',
		'help'    => __( 'A color over the slides, so that the content on them reads well. Its opacity says how much; at Opacity 0 there is none.', 'wp-swiper' ),
	),
);
