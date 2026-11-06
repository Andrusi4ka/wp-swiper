<?php
/**
 * Settings schema, part: Caption, Description, Video, Custom (HTML
 * attributes), Use as background. Read inside WP_Swiper_Settings::fields()
 * (through settings-fields.php), so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

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
			'custom'   => __( 'Custom gradient', 'wp-swiper' ),
		),
		'help'    => __( 'Gradient: from the image edge where the text is, fading out. Custom gradient: your own colors and direction (below).', 'wp-swiper' ),
	),
	'caption_bg_color'      => array(
		'section' => 'caption',
		'group'   => 'caption_bg',
		'label'   => __( 'Background color', 'wp-swiper' ),
		'type'    => 'alpha_color',
		'default' => 'rgba(0,0,0,0.65)',
	),
	// Background style "Custom gradient": its own colors, edited as the
	// photos' Gradient (the same field type and editor; WP_Swiper_Art::
	// gradient_css()), over the caption's band (Art: its photo's piece).
	// By default as the fading one: the color at the bottom, none at the top.
	'caption_bg_gradient'   => array(
		'section' => 'caption',
		'label'   => __( 'Gradient colors', 'wp-swiper' ),
		'type'    => 'gradient',
		'default' => 'linear;bottom;rgba(0,0,0,0.65)@0;rgba(0,0,0,0)@100',
		'help'    => __( 'Click the bar to add a color, drag a color along it, pick one to change or delete it.', 'wp-swiper' ),
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
		'range'      => array( 0, 100 ),
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
