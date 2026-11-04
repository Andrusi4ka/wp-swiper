<?php
/**
 * Art's settings (WP_Swiper_Art::fields(), merged into
 * WP_Swiper_Settings::fields()): for this effect only, named art_*. Read
 * inside WP_Swiper_Art::fields(), so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wp_swiper_art = array( self::EFFECT );
// Text mask (its own effect, drawn by Art: style_of() 'text'): Art's fields it
// uses too (data-styles still says which Styles of Art).
$wp_swiper_art_text = self::EFFECTS;

return array(
	'art_style'          => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'label'   => __( 'Style', 'wp-swiper' ),
		'type'    => 'select',
		// Shown as icons, assets/images/art/{style}.svg (as the Collage's Style).
		'tiles'   => 'art',
		'default' => 'ring',
		'choices' => self::styles(),
		// Greyed tiles after them (admin's tiles()), not choices.
		'soon'    => self::soon(),
		'help'    => __( 'The shape the photos make. The slides fill it in their order (they repeat when there are fewer).', 'wp-swiper' ),
	),
	// Which photo is in which piece, per device: the photos' IDs, the first
	// in the first piece ('' on desktop: the slides' order; on tablet and
	// mobile: the larger screen's). Set by dragging a photo onto another
	// piece in the preview (preview-art.js); no field to show.
	'art_order'          => array(
		'section'       => 'layout',
		'effects'       => $wp_swiper_art,
		'label'         => __( 'Order', 'wp-swiper' ),
		'type'          => 'order',
		'responsive'    => true,
		'inline_in'     => 'art_style',
		'inline_hidden' => true,
		'default'       => '',
	),
	// SVG shape: the file's shapes (WP_Swiper_Svg_Shape: their geometry
	// only, read when the slider is saved or the preview updated).
	'art_shape'          => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'shape' ),
		'label'      => __( 'Shape (SVG file)', 'wp-swiper' ),
		'type'       => 'svg_shape',
		// A file of its own on tablet and mobile, else the larger screen's.
		'responsive' => true,
		'default'    => '',
		'help'       => __( 'Each object of the drawing is a window for a photo: the current photo in the main one, the next ones in the others. Tablet and mobile can have a file of their own (empty: the larger screen’s). The main window is the object or layer named "main" in the drawing, else the largest; then those named 2, 3..., then the larger ones. Objects without a fill, hidden ones and text are left out; the later ones lie on top, as in the drawing.', 'wp-swiper' ),
	),
	// SVG shape: the empty space around the shapes in the file kept, or
	// left out (by default: the slider as large as the shapes).
	'art_shape_margins'  => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'shape' ),
		'label'   => __( 'Keep the file\'s empty margins', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'Off: the slider is as large as its shapes. On: the whole drawing, with the space around them.', 'wp-swiper' ),
	),
	// Grid shapes: which cell is in which shape, and the shapes' own corners
	// (WP_Swiper_Art::parse_cells()), one layout for every device. Drawn in
	// the preview (preview-art.js); no field to show.
	'art_cells'          => array(
		'section'       => 'layout',
		'effects'       => $wp_swiper_art,
		'styles'        => array( 'grid-shapes' ),
		'label'         => __( 'Shapes', 'wp-swiper' ),
		'type'          => 'cells',
		'inline_in'     => 'art_style',
		'inline_hidden' => true,
		'default'       => '',
	),
	// Grid shapes: its columns and rows, one for every device (it stretches
	// to the column and the Height).
	'art_cells_columns'  => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'grid-shapes' ),
		'group'   => 'art_cells',
		'label'   => __( 'Columns', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 4,
		'min'     => 2,
		'max'     => 12,
		'step'    => 1,
	),
	'art_cells_rows'     => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'grid-shapes' ),
		'group'   => 'art_cells',
		'label'   => __( 'Rows', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 4,
		'min'     => 2,
		'max'     => 12,
		'step'    => 1,
		'help'    => __( 'The grid the shapes are drawn on. In the preview: drag across cells to join them into one shape, click a shape for its corners. It stretches to the column and the Height.', 'wp-swiper' ),
	),
	// Height for every Style, as a slider's: its number and unit per device
	// (tablet and mobile empty: the larger screen's). A ring is as wide as
	// it is tall, an SVG shape as its drawing; a grid as wide as the column.
	// Until 3.1 the rings' Size (their width) and the grids' Height (px or
	// square photos), merge_art_height().
	'art_height'         => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art_text,
		'label'      => __( 'Height', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'unit'       => 'art_height_unit',
		'default'    => 560,
		'min'        => 1,
		'max'        => 2000,
		'step'       => 1,
		'help'       => __( 'px: its height (a ring as wide, an SVG shape as its drawing, never wider than the column; a grid as wide as the column). vh: of the screen\'s height. %: of the parent block\'s height. Auto: fills the free height of the parent column; where there is none, a ring or shape as wide as the column, a grid 500px.', 'wp-swiper' ),
	),
	'art_height_unit'    => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art_text,
		'label'      => __( 'Height unit', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'inline_in'  => 'art_height',
		'default'    => 'px',
		'choices'    => array(
			'px'   => 'px',
			'vh'   => 'vh',
			'%'    => '%',
			'auto' => __( 'Auto', 'wp-swiper' ),
		),
	),
	// Where the shape sits in its column, per device, when it is narrower.
	'art_align'          => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring', 'ring-text', 'shape' ),
		'label'      => __( 'Alignment', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'default'    => 'center',
		'choices'    => array(
			'left'   => __( 'Left', 'wp-swiper' ),
			'center' => __( 'Center', 'wp-swiper' ),
			'right'  => __( 'Right', 'wp-swiper' ),
		),
		'help'       => __( 'Where it sits in its column when it is narrower than the column.', 'wp-swiper' ),
	),
	'art_ring_count'     => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring', 'ring-text' ),
		'label'      => __( 'Photos around', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 7,
		'min'        => 3,
		'max'        => 16,
		'step'       => 1,
		'help'       => __( 'The pieces of the ring. Ring: one more photo is in the middle.', 'wp-swiper' ),
	),
	// Grid + circle, as the Collage's Grid: the photos in the grid (the
	// circle's one more) and its columns per device, as many rows as they
	// make (the last one's photos in the middle); as wide as the column.
	'art_grid_count'     => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text' ),
		'label'      => __( 'Photos in the grid', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 9,
		'min'        => 2,
		'max'        => 36,
		'step'       => 1,
		'help'       => __( 'One more is in the circle. A photo the circle covers waits to be swapped in.', 'wp-swiper' ),
	),
	'art_grid_columns'   => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text' ),
		'label'      => __( 'Columns', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 3,
		'min'        => 2,
		'max'        => 6,
		'step'       => 1,
		'help'       => __( 'As many rows as the photos make; the last one\'s photos in the middle.', 'wp-swiper' ),
	),
	// Ring: the middle circle's size in percent of the whole, per device
	// ('' on tablet and mobile: the larger screen's). Set by dragging its
	// edge in the preview (preview-art.js); no field to show.
	'art_ring_center'    => array(
		'section'       => 'layout',
		'effects'       => $wp_swiper_art,
		'styles'        => array( 'ring', 'ring-text', 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text' ),
		'label'         => __( 'Middle circle (%)', 'wp-swiper' ),
		'type'          => 'float',
		'responsive'    => true,
		'inline_in'     => 'art_style',
		'inline_hidden' => true,
		'default'       => 46,
		'min'           => 20,
		'max'           => 75,
		'step'          => 0.1,
	),
	// Ring: a gap at the bottom of the ring, for an image or text (In the
	// opening), a block of its own: the switch, then its size and content.
	// Off (by default): the photos evenly all around. Saved before the
	// switch: on where an Opening above 0 was (merge_art_opening(), get()).
	'art_opening'        => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring' ),
		'label'   => __( 'Opening', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'A gap at the bottom of the ring, for an image or text. Off: the photos evenly all around the circle.', 'wp-swiper' ),
	),
	'art_ring_opening'   => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring' ),
		'label'      => __( 'Opening (degrees)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 90,
		'min'        => 0,
		'max'        => 180,
		'step'       => 1,
		'help'       => __( 'How wide the gap at the bottom is; 0 on a device: none there.', 'wp-swiper' ),
	),
	// Not for SVG shape: its windows are as drawn.
	'art_gap'            => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring', 'ring-text', 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text', 'grid-shapes' ),
		'label'      => __( 'Gap (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 8,
		'min'        => 0,
		'max'        => 40,
		'step'       => 1,
	),
	// Rounded corners, as a slider's: the radius once it is on (on by
	// default, as Art always had them). Saved before the switch: on
	// where a radius above 0 was (merge_rounded(), get()).
	'art_rounded'        => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring', 'ring-text', 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text', 'grid-shapes' ),
		'label'   => __( 'Rounded corners', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
	),
	'art_radius'         => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring', 'ring-text', 'grid-circle', 'grid-circle-text', 'grid-square', 'grid-square-text', 'grid-shapes' ),
		'label'      => __( 'Radius (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 14,
		'min'        => 0,
		'max'        => 60,
		'step'       => 1,
	),
	// A border along each photo's shape (art.js draws it), as a slider's
	// Image border: a width per device, one color.
	'art_border'         => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'label'   => __( 'Image border', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	'art_border_width'   => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'group'      => 'art_border',
		'label'      => __( 'Width (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 2,
		'min'        => 0,
		'max'        => 50,
		'step'       => 1,
	),
	'art_border_color'   => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'group'   => 'art_border',
		'label'   => __( 'Color', 'wp-swiper' ),
		'type'    => 'color',
		'default' => '#ffffff',
	),
	// Ring + text: what is in the middle circle (center()), an image over
	// text from the editor, either or both; a background with its
	// opacity (none at 0, as by default). Only while Content in the
	// middle is on (off: the circle stays empty).
	'art_center'         => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring-text', 'grid-circle-text', 'grid-square-text' ),
		'label'   => __( 'Content in the middle', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
		'help'    => __( 'An image and/or text in the middle circle, with its own settings.', 'wp-swiper' ),
	),
	'art_center_image'   => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring-text', 'grid-circle-text', 'grid-square-text' ),
		'label'   => __( 'Image in the middle', 'wp-swiper' ),
		'type'    => 'image',
		'default' => 0,
	),
	'art_center_image_size' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring-text', 'grid-circle-text', 'grid-square-text' ),
		'label'      => __( 'Image size (%)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 60,
		'min'        => 10,
		'max'        => 100,
		'step'       => 5,
		'help'       => __( 'Of the width inside the circle; the image keeps its proportions.', 'wp-swiper' ),
	),
	'art_center_text'    => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring-text', 'grid-circle-text', 'grid-square-text' ),
		'label'   => __( 'Text in the middle', 'wp-swiper' ),
		'type'    => 'editor',
		'default' => '',
	),
	'art_center_font_size' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring-text', 'grid-circle-text', 'grid-square-text' ),
		'label'      => __( 'Font size (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 18,
		'min'        => 8,
		'max'        => 120,
		'step'       => 1,
		'help'       => __( 'Too much text for the circle: it gets smaller there to fit.', 'wp-swiper' ),
	),
	'art_center_padding' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring-text', 'grid-circle-text', 'grid-square-text' ),
		'label'      => __( 'Padding (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 12,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
		'help'       => __( 'Between the circle\'s edge and the image and text.', 'wp-swiper' ),
	),
	'art_center_bg'      => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring-text', 'grid-circle-text', 'grid-square-text' ),
		'label'   => __( 'Middle background', 'wp-swiper' ),
		'type'    => 'alpha_color',
		'default' => 'rgba(255,255,255,0)',
		'help'    => __( 'The circle\'s color under the image and text; at Opacity 0 there is none.', 'wp-swiper' ),
	),
	// SVG shape: a window with an image over text instead of a photo (art.js
	// fill()), which stays there while the photos change, as the Collage's
	// Content in a place (WP_Swiper_Render::content_box()). Its window per
	// device (in the drawing's order: the main one first), or dragged onto
	// another window in the preview. Not as a background.
	'art_content'        => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'shape' ),
		'label'   => __( 'Content in a window', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'An image and/or text in one window of the shape instead of a photo; it stays there while the photos change.', 'wp-swiper' ),
	),
	'art_content_window' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'shape' ),
		'label'      => __( 'Window', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 1,
		'min'        => 1,
		'max'        => 100,
		'step'       => 1,
		'help'       => __( 'Which window has it, in the drawing\'s order (1: the main one). Or drag it onto a window in the preview.', 'wp-swiper' ),
	),
	'art_content_image'  => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'shape' ),
		'label'   => __( 'Image', 'wp-swiper' ),
		'type'    => 'image',
		'default' => 0,
	),
	'art_content_image_size' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'shape' ),
		'label'      => __( 'Image size (%)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 50,
		'min'        => 10,
		'max'        => 100,
		'step'       => 5,
		'help'       => __( 'Of the width inside the window\'s box; the image keeps its proportions. With text under it: at most half the height.', 'wp-swiper' ),
	),
	'art_content_text'   => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'shape' ),
		'label'   => __( 'Text', 'wp-swiper' ),
		'type'    => 'editor',
		'default' => '',
		'help'    => __( 'Shortcodes work here too: a form, a theme\'s HTML block with buttons… With one, nothing is cut: the window scrolls where it is too tall.', 'wp-swiper' ),
	),
	'art_content_font_size' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'shape' ),
		'label'      => __( 'Font size (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 18,
		'min'        => 8,
		'max'        => 120,
		'step'       => 1,
		'help'       => __( 'Text that does not fit in the window\'s box is cut at a word, with "…". A shape not a rectangle cuts it off at its edge: keep a Padding.', 'wp-swiper' ),
	),
	'art_content_padding' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'shape' ),
		'label'      => __( 'Padding (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 16,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
		'help'       => __( 'Between the window\'s box and the image and text.', 'wp-swiper' ),
	),
	'art_content_valign' => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'shape' ),
		'label'   => __( 'Vertical alignment', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'center',
		'choices' => array(
			'top'    => __( 'Top', 'wp-swiper' ),
			'center' => __( 'Center', 'wp-swiper' ),
			'bottom' => __( 'Bottom', 'wp-swiper' ),
		),
	),
	'art_content_bg'     => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'shape' ),
		'label'   => __( 'Background', 'wp-swiper' ),
		'type'    => 'alpha_color',
		'default' => 'rgba(0,0,0,0)',
		'help'    => __( 'The window\'s color under the image and text, along its shape; at Opacity 0 (as by default) there is none.', 'wp-swiper' ),
	),
	// Ring: what sits in the opening at the bottom (art.js places it in
	// the free space there, a gap from the photos): an image over text from
	// the editor (with links), either or both, as Ring + text's middle. Until
	// then one or the other (art_opening_content, merge_opening_content()).
	'art_opening_image'  => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring' ),
		'label'   => __( 'Image in the opening', 'wp-swiper' ),
		'type'    => 'image',
		'default' => 0,
	),
	'art_opening_image_size' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring' ),
		'label'      => __( 'Image size (%)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 100,
		'min'        => 10,
		'max'        => 100,
		'step'       => 5,
		'help'       => __( 'Of the free space in the opening; the image keeps its proportions. With text under it: at most half the height.', 'wp-swiper' ),
	),
	'art_opening_text'   => array(
		'section' => 'layout',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring' ),
		'label'   => __( 'Text in the opening', 'wp-swiper' ),
		'type'    => 'editor',
		'default' => '',
	),
	'art_opening_font_size' => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring' ),
		'label'      => __( 'Font size (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 16,
		'min'        => 8,
		'max'        => 120,
		'step'       => 1,
	),
	'art_opening_gap'    => array(
		'section'    => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring' ),
		'label'      => __( 'Gap from the photos (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 12,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
	),
	// As a background, the Styles of their own proportions (a ring, an SVG
	// shape; a grid stretches to the block): the whole shape in the block
	// (Contain) or the block filled, the rest cut off (Cover), and where it
	// sits, per device (the block's proportions differ). Shown while Use as
	// background is on (admin.js).
	'art_bg_size'        => array(
		'section'    => 'advanced',
		'panel'      => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring', 'ring-text', 'shape' ),
		'label'      => __( 'Background size', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'default'    => 'contain',
		'choices'    => array(
			'contain' => __( 'Contain', 'wp-swiper' ),
			'cover'   => __( 'Cover', 'wp-swiper' ),
		),
		'help'       => __( 'Contain: the whole shape, as large as fits in the block. Cover: it fills the block, what does not fit cut off at its edges.', 'wp-swiper' ),
	),
	'art_bg_position'    => array(
		'section'    => 'advanced',
		'panel'      => 'layout',
		'effects'    => $wp_swiper_art,
		'styles'     => array( 'ring', 'ring-text', 'shape' ),
		'label'      => __( 'Background position', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'default'    => 'center center',
		'choices'    => array(
			'left top'      => __( 'Left top', 'wp-swiper' ),
			'left center'   => __( 'Left center', 'wp-swiper' ),
			'left bottom'   => __( 'Left bottom', 'wp-swiper' ),
			'right top'     => __( 'Right top', 'wp-swiper' ),
			'right center'  => __( 'Right center', 'wp-swiper' ),
			'right bottom'  => __( 'Right bottom', 'wp-swiper' ),
			'center top'    => __( 'Center top', 'wp-swiper' ),
			'center center' => __( 'Center center', 'wp-swiper' ),
			'center bottom' => __( 'Center bottom', 'wp-swiper' ),
		),
		'help'       => __( 'Contain: where the shape sits in the block. Cover: which part of it stays in view.', 'wp-swiper' ),
	),
	// Captions on the middle photo only (the Caption page): the ring's
	// photos without, the middle one's showing as it changes.
	'art_caption_middle' => array(
		'section' => 'caption',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring', 'grid-circle', 'grid-square', 'grid-shapes', 'shape' ),
		'label'   => __( 'Only on the middle photo', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'Art: the photos around it show none.', 'wp-swiper' ),
	),
	// Photo swapping, as the Collage's: with more photos than pieces, the
	// ones not shown fade in now and then (art.js). On the Controls page,
	// with the other settings of how it moves.
	'art_swap'           => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => $wp_swiper_art_text,
		'label'   => __( 'Swap in the other photos', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
		'help'    => __( 'Every so often a photo fades to another one (What changes). The shape does not change.', 'wp-swiper' ),
	),
	// Which piece gets a new photo: one at random (with more photos than
	// pieces: those not shown), or the middle one, in turn through all
	// the photos (the ring stays as it is).
	'art_swap_target'    => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => $wp_swiper_art,
		'styles'  => array( 'ring', 'grid-circle', 'grid-square' ),
		'label'   => __( 'What changes', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'any',
		'choices' => array(
			'any'    => __( 'Any piece at random', 'wp-swiper' ),
			'middle' => __( 'The middle photo', 'wp-swiper' ),
		),
		'help'    => __( 'Any piece at random: only with more photos than pieces, those not shown come in. The middle photo: it goes through all the photos in turn, the ring stays as it is.', 'wp-swiper' ),
	),
	// SVG shape: which window gets a new photo — one at random (with more
	// photos than windows: those not shown), the windows in turn, or all
	// of them at once, each taking the next one's photo (as a slider).
	'art_shape_target'   => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => $wp_swiper_art_text,
		'styles'  => array( 'grid-shapes', 'shape', 'text' ),
		'label'   => __( 'What changes', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'any',
		'choices' => array(
			'any'     => __( 'One window at random', 'wp-swiper' ),
			'turn'    => __( 'The windows in turn', 'wp-swiper' ),
			'advance' => __( 'All windows at once', 'wp-swiper' ),
			'one'     => __( 'One photo through all windows', 'wp-swiper' ),
		),
		'help'    => __( 'One window at random, or in turn (the main one, then the next): only with more photos than windows, those not shown come in. All windows at once: as a slider, each takes the photo of the next one, the main one the next photo. One photo through all windows: the shape is a stencil over one photo, its caption in the middle of the shape; the next photo comes into all windows at once (with Photo swapping on).', 'wp-swiper' ),
	),
	// SVG shape, the windows all changing at once (one photo through all,
	// or all at once): together, or one after another — at random, from
	// the left, from the top, along the diagonal or from the middle out —
	// over the Fade speed; on the first show too (art.js).
	'art_shape_appear'   => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => $wp_swiper_art_text,
		'styles'  => array( 'grid-shapes', 'shape', 'text' ),
		'label'   => __( 'Appearance', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'all',
		'choices' => array(
			'all'      => __( 'All together', 'wp-swiper' ),
			'random'   => __( 'At random', 'wp-swiper' ),
			'ltr'      => __( 'Left to right', 'wp-swiper' ),
			'ttb'      => __( 'Top to bottom', 'wp-swiper' ),
			'diagonal' => __( 'Diagonally', 'wp-swiper' ),
			'center'   => __( 'From the middle out', 'wp-swiper' ),
		),
		'help'    => __( 'The order the windows show a new photo in, one after another over the Fade speed; also when the shape first shows. With One photo through all windows or All windows at once.', 'wp-swiper' ),
	),
	'art_swap_delay'     => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => $wp_swiper_art_text,
		'label'   => __( 'A new photo every (ms)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 3000,
		'min'     => 1000,
		'max'     => 30000,
		'step'    => 500,
		'range'   => array( 1000, 10000 ),
	),
	'art_swap_speed'     => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => $wp_swiper_art_text,
		'label'   => __( 'Fade (ms)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 800,
		'min'     => 100,
		'max'     => 3000,
		'step'    => 50,
		'range'   => array( 100, 2000 ),
	),
	'art_pause'          => array(
		'section' => 'advanced',
		'panel'   => 'controls',
		'effects' => $wp_swiper_art_text,
		'label'   => __( 'Pause on hover', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => true,
	),

	/*
	 * Text mask: a word whose letters are windows onto the photo, each with a
	 * strip under it (art.js text()). As wide as the column (Height as a
	 * grid's); the letters as large as fit, no taller than Letter height.
	 */
	'text_mask_title'    => array(
		'section'   => 'layout',
		'effects'   => array( self::TEXT_EFFECT ),
		'label'     => __( 'Word', 'wp-swiper' ),
		'type'      => 'line',
		'default'   => 'TEXT',
		'maxlength' => self::TEXT_MAX,
		'help'      => __( 'Each letter is a window onto the photo, with a strip under it. Spaces leave a wider gap.', 'wp-swiper' ),
	),
	// One photo through all the letters (the word a stencil over it), or a
	// photo in each, in the slides' order (or the order set by dragging in
	// the preview, per device), repeated when there are fewer.
	'text_mask_photos'   => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Photos', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'one',
		'choices' => array(
			'one'  => __( 'One photo through all letters', 'wp-swiper' ),
			'each' => __( 'A photo in each letter', 'wp-swiper' ),
		),
		'help'    => __( 'A photo in each letter: in the slides\' order, repeated when there are fewer; drag a photo onto another letter in the preview to swap them.', 'wp-swiper' ),
	),
	// Down: the word across, the strips under its letters. Right / Left: the
	// letters one under another, upright, the strips going out sideways.
	// Shown as icons, assets/images/text-mask/{direction}.svg (as Art's Style),
	// first in the block.
	'text_mask_direction' => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Strips go', 'wp-swiper' ),
		'tiles'   => 'text-mask',
		'type'    => 'select',
		'default' => 'down',
		'choices' => array(
			'down'  => __( 'Down', 'wp-swiper' ),
			'right' => __( 'Right', 'wp-swiper' ),
			'left'  => __( 'Left', 'wp-swiper' ),
		),
		'help'    => __( 'Down: the word across, the strips under its letters. Right or Left: the letters one under another (read from the top), the strips going out to that side.', 'wp-swiper' ),
	),
	// The theme's heading font (a heading's, as the page has it), or a plain one.
	'text_mask_font'     => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Font', 'wp-swiper' ),
		'type'    => 'select',
		'group'   => 'text_mask_font',
		'default' => 'heading',
		'choices' => array(
			'heading' => __( 'The theme\'s headings', 'wp-swiper' ),
			'serif'   => __( 'Serif', 'wp-swiper' ),
			'sans'    => __( 'Sans-serif', 'wp-swiper' ),
			'name'    => __( 'By name', 'wp-swiper' ),
		),
	),
	'text_mask_weight'   => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Weight', 'wp-swiper' ),
		'type'    => 'select',
		'group'   => 'text_mask_font',
		'default' => '700',
		'choices' => array(
			'400' => __( 'Normal', 'wp-swiper' ),
			'700' => __( 'Bold', 'wp-swiper' ),
			'900' => __( 'Black', 'wp-swiper' ),
		),
		'help'    => __( 'Bold letters make wider windows: thin ones show little of the photo.', 'wp-swiper' ),
	),
	// Font "By name": a font the site already loads (nothing is fetched for
	// it), typed or picked from those the theme and WordPress name
	// (WP_Swiper_Fonts::suggestions()).
	'text_mask_font_name' => array(
		'section'     => 'layout',
		'effects'     => array( self::TEXT_EFFECT ),
		'label'       => __( 'Font name', 'wp-swiper' ),
		'type'        => 'font',
		'default'     => '',
		'placeholder' => 'Playfair Display',
		'help'        => __( 'A font the site already loads (the theme\'s are in the list), or any on every computer. One the page does not load shows in a plain font instead: nothing is fetched for it. Another font: have the theme load it first (WoodMart: Theme settings > Typography > Advanced typography, a rule with that font; Local Google fonts keeps it on your server). It is then in the list.', 'wp-swiper' ),
	),
	// The letters' height at most (% of the Height); narrower columns make them smaller.
	'text_mask_size'     => array(
		'section'    => 'layout',
		'effects'    => array( self::TEXT_EFFECT ),
		'label'      => __( 'Letter size', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'unit'       => 'text_mask_size_unit',
		'default'    => 30,
		'min'        => 10,
		'max'        => 70,
		'step'       => 1,
		'help'       => __( 'Auto: the word as wide as it can be (the letters at most 70% of the Height). %: of the Height, the letters no taller than this. With strips right or left there is no Letter size: the word fills the Height.', 'wp-swiper' ),
	),
	// Auto: no Letter size of its own, the letters as large as fill the width.
	'text_mask_size_unit' => array(
		'section'    => 'layout',
		'effects'    => array( self::TEXT_EFFECT ),
		'label'      => __( 'Letter size unit', 'wp-swiper' ),
		'type'       => 'select',
		'responsive' => true,
		'inline_in'  => 'text_mask_size',
		'default'    => 'auto',
		'choices'    => array(
			'auto' => __( 'Auto', 'wp-swiper' ),
			'%'    => '%',
		),
	),
	// Each letter stretched along the word (up to twice its width), to fill its
	// strip (or the word the width): its height as Letter size has it.
	'text_mask_stretch'  => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Stretch letters', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
		'help'    => __( 'Each letter wider (up to twice), to fill its strip, or with strips as the letter the word the width; their height stays. Good with narrow fonts; it changes the font\'s look.', 'wp-swiper' ),
	),
	'text_mask_gap'      => array(
		'section'    => 'layout',
		'effects'    => array( self::TEXT_EFFECT ),
		'label'      => __( 'Gap (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 12,
		'min'        => 0,
		'max'        => 60,
		'step'       => 1,
	),
	// The strips all as wide (a column per letter, Gap between them, each
	// letter in the middle of its own), or each as its letter.
	'text_mask_strip_width' => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Strip width', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'same',
		'choices' => array(
			'same'   => __( 'Same for all', 'wp-swiper' ),
			'letter' => __( 'As the letter', 'wp-swiper' ),
		),
		'help'    => __( 'Same for all: the strips as wide as each other, Gap apart, each letter in the middle of its strip (the widest letter sets the size). As the letter: each strip as wide as its letter, the letters and their strips Gap apart.', 'wp-swiper' ),
	),
	// How long each letter's strip is, between Shortest and Longest (% of the
	// height under the letters).
	'text_mask_strips'   => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Strips', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'random',
		'choices' => array(
			'random' => __( 'Different lengths', 'wp-swiper' ),
			'equal'  => __( 'All the same', 'wp-swiper' ),
			'middle' => __( 'Longer in the middle', 'wp-swiper' ),
			'sides'  => __( 'Longer at the sides', 'wp-swiper' ),
		),
	),
	// How far the strips go into their letters: a share of each letter's
	// depth (down: its height; right / left: its width), one for every screen
	// (the same look at any size). 0: the letters whole (STRIP_JOIN only).
	'text_mask_join'     => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Strips into letters (%)', 'wp-swiper' ),
		'type'    => 'number',
		'default' => 0,
		'min'     => 0,
		'max'     => 30,
		'step'    => 1,
		'range'   => array( 0, 30 ),
		'help'    => __( 'How far each strip goes into its letter, of the letter\'s size: the letter\'s end merges into the strip. 0: the letters whole.', 'wp-swiper' ),
	),
	'text_mask_strip_min' => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Shortest (%)', 'wp-swiper' ),
		'type'    => 'number',
		'group'   => 'text_mask_strip',
		'default' => 40,
		'min'     => 0,
		'max'     => 100,
		'step'    => 1,
	),
	'text_mask_strip_max' => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Longest (%)', 'wp-swiper' ),
		'type'    => 'number',
		'group'   => 'text_mask_strip',
		'default' => 100,
		'min'     => 0,
		'max'     => 100,
		'step'    => 1,
		'help'    => __( 'Of the height under the letters. All the same: Longest.', 'wp-swiper' ),
	),
	// The strips' corners (in Look, as a slider's Rounded corners): the switch, the
	// radius per device under it (no larger than half a strip's width, nor
	// than its length), and which end (Corners).
	'text_mask_rounded'  => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Rounded corners', 'wp-swiper' ),
		'type'    => 'checkbox',
		'default' => false,
	),
	'text_mask_radius'   => array(
		'section'    => 'layout',
		'effects'    => array( self::TEXT_EFFECT ),
		'label'      => __( 'Radius (px)', 'wp-swiper' ),
		'type'       => 'number',
		'responsive' => true,
		'default'    => 12,
		'min'        => 0,
		'max'        => 200,
		'step'       => 1,
		'help'       => __( 'At most half the strip\'s width: a larger one rounds its end in a half circle.', 'wp-swiper' ),
	),
	'text_mask_corners'  => array(
		'section' => 'layout',
		'effects' => array( self::TEXT_EFFECT ),
		'label'   => __( 'Corners', 'wp-swiper' ),
		'type'    => 'select',
		'default' => 'end',
		'choices' => array(
			'end'  => __( 'At the strip\'s end', 'wp-swiper' ),
			'both' => __( 'At both ends', 'wp-swiper' ),
		),
		'help'    => __( 'At both ends: by the letter too (where the strip is wider than the letter\'s foot).', 'wp-swiper' ),
	),
);
