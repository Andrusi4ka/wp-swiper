<?php
/**
 * What each effect can do: a few short points shown under the settings menu
 * for the Effect chosen (WP_Swiper_Admin::effect_notes(), admin.js), and a
 * line for every effect under them. What it can do, not how: the fields
 * have their own help. Returned by WP_Swiper_Settings::effect_notes().
 *
 * @package WP_Swiper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wp_swiper_slider_basics = __( 'Video slides, loop, autoplay, arrows, pagination', 'wp-swiper' );

return array(
	'effects' => array(
		'slide'     => array(
			__( 'One or more slides in view, a gap between them, per device', 'wp-swiper' ),
			__( 'Slides as wide as their images, a wider active slide (Expand), continuous scroll (marquee)', 'wp-swiper' ),
			__( 'Horizontal or vertical; loop, autoplay, arrows, pagination, keyboard', 'wp-swiper' ),
			__( 'Video slides: media library, YouTube, Vimeo', 'wp-swiper' ),
			__( 'Ken Burns zoom, zoom on hover, image shadow, gradient', 'wp-swiper' ),
			__( 'Can be the background of its row or column', 'wp-swiper' ),
		),
		'fade'      => array(
			__( 'One slide at a time, fading into the next', 'wp-swiper' ),
			__( 'Video slides; autoplay can wait for a video to end', 'wp-swiper' ),
			__( 'Ken Burns zoom, zoom on hover, image shadow, gradient', 'wp-swiper' ),
			__( 'Can be the background of its row or column', 'wp-swiper' ),
		),
		'cube'      => array(
			__( 'Slides on the sides of a turning cube, horizontal or vertical', 'wp-swiper' ),
			__( 'Shadow under the cube (offset, scale, color) and shadows on its sides', 'wp-swiper' ),
			$wp_swiper_slider_basics,
			__( 'Ken Burns zoom, gradient', 'wp-swiper' ),
		),
		'coverflow' => array(
			__( 'The slide in the middle in front, the others turned away in depth', 'wp-swiper' ),
			__( 'Rotate, depth, stretch and side slides\' scale (depth and stretch per device)', 'wp-swiper' ),
			$wp_swiper_slider_basics,
			__( 'Image shadow, gradient', 'wp-swiper' ),
		),
		'flip'      => array(
			__( 'Each slide flips over to show the next, horizontal or vertical', 'wp-swiper' ),
			$wp_swiper_slider_basics,
			__( 'Ken Burns zoom, gradient', 'wp-swiper' ),
		),
		'cards'     => array(
			__( 'Slides stacked like a deck, swiped away one by one', 'wp-swiper' ),
			__( 'The cards\' offset, tilt and rotation', 'wp-swiper' ),
			$wp_swiper_slider_basics,
			__( 'Ken Burns zoom, image shadow, gradient', 'wp-swiper' ),
		),
		'creative'  => array(
			__( 'Seven styles: Push, Zoom, Slide apart, Flip, Rotate, Door, Carousel', 'wp-swiper' ),
			__( 'How far the neighbours move, their scale and opacity', 'wp-swiper' ),
			$wp_swiper_slider_basics,
			__( 'Ken Burns zoom, zoom on hover, image shadow, gradient', 'wp-swiper' ),
		),
		'thumbs'    => array(
			__( 'One large slide picked from a strip of thumbnails: below, above, left or right, per device', 'wp-swiper' ),
			__( 'Slide or Fade between slides', 'wp-swiper' ),
			__( 'Thumbnails\' number in view, size, proportions, frame and rounded corners', 'wp-swiper' ),
			__( 'The strip in the lightbox too', 'wp-swiper' ),
			__( 'Ken Burns zoom, zoom on hover, gradient', 'wp-swiper' ),
		),
		'collage'   => array(
			__( 'Templates, Grid, Justified rows, Masonry, Random mosaic, Scattered, Freeform, Overlap', 'wp-swiper' ),
			__( 'The other photos swap in, one place at a time', 'wp-swiper' ),
			__( 'In the preview: drag photos to swap them, lines and edges to resize; move them freely (Freeform, Overlap)', 'wp-swiper' ),
			__( 'Rotation: the whole collage turned, its photos upright, per device', 'wp-swiper' ),
			__( 'Content in a place: an image, text or a shortcode (a form, a theme\'s HTML block) instead of a photo', 'wp-swiper' ),
			__( 'Image shadow, zoom on hover, gradient; can be a background', 'wp-swiper' ),
		),
		'art'       => array(
			__( 'Photos in shapes: a ring, grids with a circle or a square, Grid shapes joined from cells, your own SVG shape', 'wp-swiper' ),
			__( 'Content in a window, the middle one too: an image, text or a shortcode instead of a photo', 'wp-swiper' ),
			__( 'Photos change in their windows, or one photo shows through all of them', 'wp-swiper' ),
			__( 'Drag photos in the preview to swap them; Grid shapes are drawn there', 'wp-swiper' ),
			__( 'Image shadow, zoom on hover, gradient; can be a background', 'wp-swiper' ),
		),
		'text-mask' => array(
			__( 'A word whose letters are windows onto your photos, a strip under each letter', 'wp-swiper' ),
			__( 'One photo through all the letters, or a photo in each', 'wp-swiper' ),
			__( 'Strips go down, right or left; the theme\'s font or any font the site loads', 'wp-swiper' ),
			__( 'Letter size, stretched letters, gap, strips into letters, rounded strips', 'wp-swiper' ),
			__( 'Image shadow, zoom on hover, gradient', 'wp-swiper' ),
		),
	),
	// Under the effect's points, for every effect.
	'all'     => __( 'Every effect: lightbox, captions, descriptions and links per slide; settings per device (desktop, tablet, mobile); placed with a shortcode or the WPBakery element.', 'wp-swiper' ),
);
