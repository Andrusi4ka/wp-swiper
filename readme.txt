=== WP Swiper ===
Contributors: andrusi4ka
Tags: slider, carousel, swiper, gallery, wpbakery
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Image sliders powered by Swiper: build a slider from the media library, pick an effect, insert it with a shortcode or the WPBakery element.

== Description ==

WP Swiper adds a "WP Swiper" menu (for administrators, and the roles they allow under WP Swiper → Settings) where you build image sliders from the media library and show them anywhere with `[wp_swiper id="123"]` or the "WP Swiper" WPBakery element.

* Ten effects: Slide, Fade, Cube, Coverflow, Flip, Cards, Creative (Push, Zoom, Slide apart, Flip, Rotate, Door and Carousel styles), Thumbnails (one slide picked from a strip of thumbnails below, above, left or right of it, set per device, with arrows and its own proportions), Collage (the photos placed together by a template, a grid, justified rows, masonry, a random mosaic, scattered around a photo in the middle, placed freely or over one another, the others swapped in) and Art (the photos in shapes of their own: a ring around a circle, with an image or text in its opening, a grid with a circle, or the shapes of an SVG file a designer drew).
* Image shadow, with its offsets, blur, spread and a colour with opacity, where the photos stand apart.
* A slider can be the background of the row or column it is in, with an overlay; the main image can zoom slowly in or out (Ken Burns).
* Each effect keeps its own Layout settings, so switching effects never loses what you set.
* Slides per view, space between slides, border radius and height per device (desktop, tablet, mobile).
* Slide effect extras: wider active slide, slides as wide as their images, slides per group, continuous scroll (marquee).
* Image fit Cover or Contain, height in px / vh / % or Auto (fills the parent column), its unit per device too.
* Arrows (with their own colour), bullets or fraction pagination in six positions over or outside the slider, with their own size, shape, spacing and colours and a "pill" current bullet, keyboard control, autoplay, infinite loop.
* Lightbox with zoom (and, with Thumbnails, the strip of thumbnails in it), per-slide links (optionally in a new tab), captions (bold, italic, underline, line breaks) with their own typography and background, all of a slide edited in one window.
* Slide descriptions written in WordPress's editor (headings, bold, italic, underline, links, lists), shown under the slider for the current slide and changing with it the way the slides move (sliding, turning with the cube, flipping...); in the lightbox too, over or under the image.
* Image position per device for the part of a cropped image kept in view.
* Video slides from the media library or from a YouTube / Vimeo link, with a poster image: the video of the slide in view plays (muted, looped, with or without controls), the slider can wait for its end, and a file or player loads only when its slide comes up (YouTube in its privacy-enhanced mode). Videos open in the lightbox too, with sound.
* Preview beside the settings: the slider as the current settings draw it, saved or not, as on a desktop, tablet or phone (refreshed with its Update button).
* Settings that do not apply in the current setup are greyed out, and keep their values.

Bundles [Swiper](https://swiperjs.com/) 11 (MIT) and [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5 (MIT).

== Installation ==

1. Download `wp-swiper.zip` from the latest release on https://github.com/Andrusi4ka/wp-swiper/releases.
2. In WordPress go to Plugins > Add New > Upload Plugin and upload the zip, then activate it.
3. Go to WP Swiper > Add slider, add images, choose the settings and publish.
4. Paste the shortcode shown in the slider's "Shortcode" box into a page, or add the "WP Swiper" element in WPBakery.

Later versions are offered under Plugins like any other update.

== Frequently Asked Questions ==

= Where do updates come from? =

From this plugin's GitHub releases. The plugin header's "Update URI" stops WordPress from offering the unrelated wordpress.org plugin of the same folder name.

= What is removed when I delete the plugin? =

Deleting it under Plugins removes all sliders with their slides and settings. Deactivating or updating it keeps them. Images stay in the media library.

= Why is a setting greyed out? =

It does not apply with the current settings (for example "Next slide when the video ends" without Autoplay). Its value is kept and comes back when it applies again.

= Can a slide be a video? =

Yes: a video from the media library, or a YouTube or Vimeo link (paste it under the slides). Its poster image is chosen on the Video page of the settings. Browsers only start a video by themselves without sound, so keep "Muted" on with "Play automatically".

== Changelog ==

= 3.7.1 =
* Collage, Freeform and Overlap with Height Auto: the photos fill the block as far as they fit, not the frame they were set in the preview (its empty margins). In a narrow, tall column they no longer sit in a thin band in its middle.

= 3.7.0 =
* Collage: a new Style, Overlap — the photos lie over one another, each on a layer of its own. Place them by hand in the preview: drag a photo anywhere, over the others too (the one you move goes on top), resize it by its corners and edges, send it under the others with its arrow. With Rotation, Content in a place, Image shadow and the lightbox (a click opens the photo on top). Per device.

= 3.6.0 =
* WP Swiper → Settings: what each role may do with the sliders — Edit sliders, and with it Add and clone, Delete and the WPBakery element. Administrators may always do all of it; until set, WP Swiper is theirs only, as before.
* Each corner's radius and each side of the image border its own: the slides' Radius, the thumbnails' Radius (the lightbox's thumbnails follow) and Image border's Width are boxes with a link — linked, one number as before. Per device.
* Image border: its Line — Solid, Dashed, Dotted or Double.
* Controls: Drag with the mouse — off, the mouse no longer drags the slides; a finger still swipes them on phones and tablets.
* Collage and Art with more places than photos: a photo dragged in the preview onto any place stays there (it could not go past as many places as there were photos).
* Preview: a screen size of your own for each device (applied with Update), the device buttons as icons; Developer mode as an icon, its labels readable at any scale.
* Color picker: an eyedropper beside the value (Chrome, Edge, Opera) — a color from anywhere on the screen.
* The radii of the slides and the thumbnails have no slider any more: it held them to 100 and 50.

= 3.5.0 =
* Art loads faster: each window takes the smallest copy of its photo that is as large as it is drawn (on a phone several times less to load), and a page loads only the code of its own Style.
* Text mask: the strips can be turned off — the letters alone, as large as the block allows. "Strips go" is now Text position: Top, Bottom (new: the word at the bottom, the strips going up), Left, Right.
* Text mask: Letter alignment, per device — a row's letters on their tops, middles or the baseline; a column's on their left edges, middle line or right edges. With strips too: each strip starts where its letter ends.
* Art, Grid shapes: Content in a window too.
* Caption: a Custom gradient background — your own colors and direction.
* Image shadow for Flip (on the turning card) and Thumbnails (the main photos).
* Editor: a slider beside the number for the radii, Gaps, the active slide's size, the Gaps to the slider, the thumbnails' opacity, the collage's Rotation, Art's image sizes and the lightbox's text width — per device too. New icons for Creative's Styles.
* Fixed: in the preview, content dragged onto a window (Art) or a place (Collage) did not go past as many as there were photos.

= 3.4.0 =
* Art: Ring, Grid + circle and Grid + square have Content in a window too: an image, text or a shortcode in one window instead of a photo — the middle one or any other, per device or dragged onto a window in the preview. In a round window it sits in the square inside the circle.
* Art: the "+ text" Styles are replaced by it. A slider with Ring + text, Grid + circle + text or Grid + square + text becomes Ring, Grid + circle or Grid + square with what was in its middle in window 1; its photos stay where they were.
* Art, Grid shapes: drag a corner dot in the preview to round the shape's corners; click a dot (it turns red) and drag it for that corner alone. The value shows while you drag; Escape puts it back.
* Effect shadows (Coverflow, Cards, Creative): a Shadow color of their own — its opacity is how dark the slides get, any color. By default as before.
* Creative: its Style chosen from icons, at the top of the Effect block.
* Preview: a dark screen for the devices (the button after Rotate), kept for the next visit.
* Fixed: the code view of the settings' text editors (Content in a place, Content in a window, the Ring's opening) was narrow.

= 3.3.0 =
* Collage: Rotation (-45 to 45°, per device) for Grid, Random mosaic, the templates, Scattered and Freeform: the whole collage turned, each photo upright and covering its place. Grid, mosaic and templates fill the block (edges cut off) or fit in it; Scattered and Freeform turn where they are, as large as their photos' outermost corners allow, and with Height Auto the block is cut to them. Photos dragged and resized at the angle in the preview.
* Collage: Content in a place: an image and text (shortcodes too: a form, a theme's HTML block with buttons) in one place instead of a photo, its place per device or dragged onto a photo in the preview. It stays there while the photos swap; text that does not fit is cut at a word, a shortcode's content scrolls. Not for Justified rows and Masonry.
* Art, SVG shape: Content in a window: the same in one window of the drawing, its background along the window's shape.
* Image shadow as a background (Use as background) for Collage and Art.
* Developer mode for collages (each place, the spaces between them, the angle) and SVG shapes (each window's box and outline, the content's area).
* Under the settings menu, a card on what the chosen effect can do (it folds).

= 3.2.1 =
* WP Swiper is for administrators only: only they see its menu, add, edit, copy and delete sliders, use the preview and the WPBakery element. Editors, authors and the other roles do not see WP Swiper in the admin at all (nor its "What's new"). Visitors see the sliders on the pages as before, and sliders already placed on pages stay there.

= 3.2.0 =
* A new effect, Text mask: a word whose letters are windows onto your photos, a strip under each letter — one photo through all the letters, or a photo in each (drag them onto other letters in the preview to swap them). The strips go down, right or left (the letters one under another), with lengths that differ, are all the same, or grow towards the middle or the sides.
* Text mask, letters: the theme's heading font, a serif or sans-serif one, or one by name (the fonts the site already loads are listed); the weight; Letter size Auto (the word as wide as the slider) or a share of the Height; Stretch letters to fill their columns.
* Text mask, strips: as wide as each other or each as wide as its letter, a Gap apart; Strips into letters, so a letter's foot merges into its strip; Rounded corners at the strip's end or both ends.
* Gradient (Layout, Look), for every effect: one or more colors, see-through or not, over the photos — over each photo of a slider or collage (not over videos), and one across the whole shape of Art and Text mask, the gaps staying empty. Edited as a bar of colors: click to add one, drag it, pick its color, Delete; linear from any side, or radial (a circle or an ellipse) round any middle.
* Editor: the preview follows a new Effect or Style at once; Text mask's settings in three cards (the word, Letters, Strips); one device for the whole editor (a field's device icons switch the preview and every field to it).
* Fixed: saving lost the backslashes in captions, descriptions and SVG shapes; the lightbox left a listener behind for each embedded video it opened; an odd request could give a PHP notice when saving.

= 3.1.0 =
* Art: a new Style, Grid shapes — a grid of Columns x Rows whose cells you join into shapes in the preview: drag across cells to make one shape, click a shape for its corners, split it into its cells or empty them. Outward corners are rounded, inward ones too, so the gap between the shapes stays even, and a shape can go round a hole. Each corner can have a radius of its own (click its dot). The shapes are drawn at the size of the page, so the grid stretches to any column and Height. Its windows change as an SVG shape's do, one photo through all of them included.
* Art: three more grid Styles — Grid + circle + text, Grid + square and Grid + square + text — and the Content in the middle and Opening settings in blocks of their own. One Height for every Style, as a slider's (px, vh, %, Auto).
* Art as a background (Use as background): a grid stretches to the block; a ring or an SVG shape keeps its proportions, with a Background size (Contain: all of it; Cover: the block filled) and a Background position, per device.
* Zoom on hover (Layout, Look): the photo under the pointer zooms in a little — for Slide, Fade, Creative, Thumbnails, Collage and Art (on for Art, which always had it). Not on touch screens.
* Image shadow: outside the photos, which keep their size; for Fade too.
* Rounded corners and Frame are switches now (slides, lightbox, thumbnails); Overlay, caption background and the cube's shadow take a color with its opacity.
* Coverflow Depth and Stretch, and the descriptions' Gap to the slider, per device. Direction is in Layout, for the effects that use it.
* The phone takes the larger screen's values unless it has its own.
* Editor: one color picker everywhere, settings in grey cards, the Slide info window in tabs (Caption, Description, Link), Undo and Redo for every setting (Ctrl+Z, Ctrl+Shift+Z), and in the preview the devices drawn, tablet and phone turning sideways, a background filling the screen.
* Collage: Photos on screen from 1.
* Faster pages: a collage's or Art's page loads no styles of the sliders, and the Thumbnails strip's styles load only where it is shown.
* Security: captions and descriptions keep their editor's styles only (color, underline, alignment); links opening a new tab get rel="noopener"; the updates' build steps are pinned.

= 3.0.0 =
* Collage: a new Style, Freeform — the photos at random places and sizes, a Gap apart and never over each other. In the preview drag a photo anywhere (it stops a Gap away from the others and snaps to their edges), drag its corners or edges to resize it, drop it on another photo to swap the two, or take its place out with its ×. Everything is in shares of the Height, so a new Height scales it all, gaps too. Shuffle gives a new layout, Reset the first one.
* Art, SVG shape: What changes has "One photo through all windows" — the shape becomes a stencil over one photo, its caption in the middle of the shape; the next photo comes into all windows at once.
* Art, SVG shape: Appearance — with all windows changing at once, they can show the new photo together, at random, left to right, top to bottom, diagonally or from the middle out, over the Fade speed; on the first show too.
* Art, SVG shape: a file of its own for tablet and mobile (empty: the larger screen's).
* Art, SVG shape: up to 100 shapes (was 24); more are said under the field.
* Fixed: in an SVG shape with more than 8 windows, only the first 8 changed photos; a new photo could be lost when changes came close together.
* Security: alt texts and video titles with quotes are shown safely in the lightbox; SVG files are checked more strictly.

= 2.13.0 =
* Art: a new Style, SVG shape — upload an SVG file and each of its shapes is a window a photo shows through: a heart in two halves, an organic shape with a circle, shapes lying over each other. Only the drawing's shapes are kept (no file in the media library, nothing of it on the page); objects without a fill, hidden ones and text are left out, and the later ones lie on top, as drawn. The main window is the shape named "main" in the drawing, else the largest; then those named 2, 3..., then the larger ones. The empty margins of the file are left out, unless kept. What changes: one window at random, the windows in turn, or all of them at once (as a slider). A click on any window opens its photo in the lightbox.
* Art: the photos are loaded in their full size.

= 2.12.0 =
* Collage, Scattered: a round × in the corner of each photo in the preview (not the middle one) takes its place out of the collage on the device shown. The rest of the layout stays as it is (the photos after it in its arm close up), Photos on screen goes down by one and its photo waits with the others: the slide stays. Undo brings it back.

= 2.11.0 =
* Collage, Scattered: a photo can also be moved to the end of any arm. While it is dragged in the preview, dashed frames show where it can go (along the arm or outwards; next to the middle photo in an empty arm); over one, the collage shows itself as it would be. Dropped there, the photo keeps its size and the photos after it in its old arm close up. The middle photo still only swaps.
* Caption: a Font size for every slide's caption, per device (empty: 16px). A slide with a size of its own (its Edit window) keeps it, on the smaller screens too; the Edit window shows the size that applies. A slider saved with one size for all its captions (until 2.8) finds it here.
* Preview, Collage and Art: Undo and Redo buttons left of the devices (Ctrl+Z, Ctrl+Shift+Z in the preview) for what was done there — photos swapped or moved, sizes dragged — and for Shuffle and Reset. Per device; the preview changes back at once.

= 2.10.0 =
* Art: a new Style, Ring + text — the ring of photos with no photo in the middle. With Content in the middle on, the circle holds an image and/or text from WordPress's editor (in the theme's colour, single words coloured as in descriptions); its image size, font size and padding per device, and a background colour with opacity (none by default). Text too long for the circle gets smaller to fit.
* Art: a new Style, Grid + circle — a grid like the Collage's, its photos and columns per device, the last row's photos in the middle of it, with a photo circle over the middle of the grid that cuts the photos around it. As wide as the column; its height per device in px, or Auto for square photos. Drag the circle's edge in the preview to resize it.
* Art: the Image border is in the Look block now, right over the Image shadow.

= 2.9.2 =
* Faster pages: a Collage's script and styles load only where a collage is shown, so pages with sliders load a third less script.
* The translation template is up to date.

= 2.9.1 =
* Art: the Style is chosen from icons, as the Collage's.
* Preview, a slider as a background: its photos can be dragged there too (swapped, resized), even under the block's content, which now sits in the middle of the block. On the page nothing changes.

= 2.9.0 =
* New effect, Art: the photos in shapes of their own, the first Style a Ring — a circle in the middle and the others around it, with an even gap, rounded corners and, if set, an opening at the bottom. Its size, alignment, number of photos and opening per device; the middle circle resized by dragging its edge in the preview, the photos swapped by dragging one onto another. Captions, descriptions under it, the lightbox, an image border, photo swapping.
* Art, Ring: captions on the middle photo only (Caption page); photo swapping can change the middle photo instead, in turn through all the photos (What changes); an image or text with links (WordPress's editor) in the opening, its size and its gap from the photos per device.
* Collage: a new Style, Scattered — a photo in the middle and the others around it in uneven arms, shaped after their photos, the gap between neighbours always the same and the corners left empty. Its number of photos per device; drag a photo's outer edge in the preview to resize it.
* Collage: the Style is chosen from icons; the four templates are resized by dragging the lines between their photos, as the Random mosaic.
* Image shadow (Layout, Look): its horizontal and vertical offset, blur, spread and a colour with opacity, for several slides in view, Coverflow, Cards, the Creative carousel, Collage and Art (along each shape). Room is kept around the photos for it.
* Height: its unit (px, vh, %, Auto) per device.
* The slide window (Edit): each slide's caption and description have their own font size per device, weight and colour with opacity; a caption is paragraphs or headings (Format). Replace photo in the window, Clone on each slide. The button on a slide now reads Edit.
* Every colour setting has an arrow back to its default colour. The editors' Text color offers the theme's colours.
* A slider as a background shows no captions or descriptions (their pages say so).
* WPBakery element: an Add slider button; back on the page builder's tab, the slider published there is in the list, chosen.
* Faster pages: Collage and Art no longer load Swiper (their lightbox loads it when it opens); the first slider's first image is fetched first; the styles of a slider shown outside the page's content come in the page's head from the next visit; the plugin's scripts and stylesheets are minified.
* Captions keep the alignment of their lines on WordPress 6.0 too.

= 2.8.0 =
* Collage, Random mosaic: drag a line between photos in the preview to resize them — the photos on either side grow and shrink, each part at least 40px. The sizes are kept per device (tablet and phone take the larger screen's until they get their own) and stay when photos are swapped or the window is resized; with another number of photos the mosaic is worked out again. A hint under the preview says so.
* Collage, Order: one Reset (in red) now clears both the order and the mosaic sizes of the device shown. It is greyed while there is nothing to reset, and Shuffle is greyed once the photos were arranged or resized by hand there, so it cannot undo that by accident. The field follows the preview's device.

= 2.7.0 =
* Collage: drag a photo onto another place in the preview to swap the two. While dragging, a small copy of the photo follows the pointer, its place shows an empty frame, and over another place both show the swap before you let go (Escape gives it up). A hint under the preview says so.
* Collage: the order of the photos is kept per device (Order field, in Arrangement) — tablet and phone take the larger screen's until they get their own. It is kept by photo, so reordering the slides does not change it, and new photos come after it. Shuffle mixes the photos and Reset goes back to the larger screen's order, both for the device shown; Shuffle now works for every collage style. Collages saved before keep their order.
* WPBakery element: the "Edit slider" button has no icon now, with "Opens in a new tab." under it.

= 2.6.1 =
* WPBakery element: an "Edit slider" button under the Slider list opens the chosen slider's edit screen in a new tab. It follows the slider chosen and is hidden while none is; the shortcode stays as it was.

= 2.6.0 =
* Slides: each image can have its own position for Image fit "Cover" — the point kept in view where the slider crops it, on every device, over the slider's Image position. The button on the image's thumbnail opens a window with the larger image: click, drag or use the arrow keys to set the point, and see the crops of a wide, a square and a tall slide. "Use the slider's position" clears it. The thumbnail shows the crop and a "Position" mark. Also for a collage and a background, which always crop.
* Collage: Height "Auto" fills the free height of the column, like the other effects (templates, Grid and Random mosaic; Justified rows and Masonry stay as tall as their photos make them). Where there is no free height, as with stacked columns on phones, it keeps the 3:2 proportions.
* Continuous scroll: "Scroll direction" — right to left (as before) or left to right; a vertical slider moves bottom to top or top to bottom. Slides of different widths keep an even speed either way, and Pause on hover works.
* Sliders list: "Clone" under a slider's title makes a copy with its slides and settings, titled "(copy)", as a draft.
* Slider edit screen: leaving it (closing, reloading, a link) with unsaved changes to the slides, the settings or the title asks first.
* After an update, a "What's new" window with the changes since the version you last saw: on the plugin's screens, and from a notice on the Dashboard. Once for each user who edits sliders; not after a first install.
* Settings: Image fit and the Thumbnails' Transition are buttons, like Direction.
* Fix: Image proportions with Space around and Image fit "Contain" — the caption and the rounded corners are the image's, not its frame's, and Image alignment applies, as with Space between.

= 2.5.0 =
* New in Layout, Size and fit: "Image proportions" per device (Off, 1:1, 4:3, 3:2, 16:9, 3:4, 9:16 or Custom such as 2.35x1), Slide effect. Every image is as tall as the Height and as wide as its proportions make it, Slides per view of them share the row and the free width goes around them like flex items — "Distribution": Space between (the outer images at the row's edges) or Space around (each image in the middle of its equal share). Space between slides is the least space left: where the images do not fit with it, they get smaller, and so does the slider. Off keeps the images filling the row, as before. Works with the continuous scroll and its separator.
* Preview: a "Developer mode" button right of its title. It draws over the preview the slides' boxes, the images' boxes with their size, the space between the slides and the empty space around an image, and the distances between the images and from the slider's edges; it follows the moving slides and is kept for the next visit. Only in the preview, never on the site.

= 2.4.0 =
* Continuous scroll: a "Separator" between the slides (Layout, Slides) — None, Dot or Own image from the media library (PNG or SVG with a transparent background suits best). It stands in the middle of the space between the slides and moves with them. "Separator size" per device (the dot's diameter; an image fits in a square of that size) and "Dot color". The space between the slides is at least as wide as the separator.
* Settings: an image field with a thumbnail and Choose / Change / Remove buttons.

= 2.3.1 =
* Settings: a "Want to see more effects? Swiper demos" link in the bottom left corner of the Slider settings box, opening swiperjs.com/demos in a new tab.

= 2.3.0 =
* Slides: each card has a thumbnail, an "Add info" button ("Edit info" once it has something) and marks of what it has (Caption, Description, Link). The caption, description, link and "Open in a new tab" are edited in one "Slide info" window with the slide's thumbnail; a video slide has no link there. Apply keeps the changes, Cancel or Escape drops them.
* Captions: bold, italic, underline and line breaks (Enter starts a new line) in their own small editor, on the slides and in the lightbox. Text pasted in paragraphs or headings keeps its lines; links and other markup are left out (the slide's own link is the Link field). Captions saved before stay as they were.

= 2.2.0 =
* New in Lightbox: "Description place" — over the image (as before) or under it. Under the image the picture gets smaller to leave room, the text is as wide as the image within "Text width" (% of the window, per device; 75% / 100% on phones) and "At most" (800px), not narrower than 500px where the window allows, at most 40% of the window tall (longer scrolls), aligned as on the Description page.
* Descriptions: headings (h1–h6) are kept, with a Paragraph / Heading list in the editor. On the page they take "Own text color" too; in the lightbox they are light and sized for the panel.
* Description window: the slide's thumbnail beside the title; the slide's button shows its text only.
* Collage as a background: Justified rows and Masonry (the phones' default style) fill the block's height instead of stopping where the photos end, also when the block changes height later.
* Settings: fields side by side line up (the lightbox text's width, the image border's width and colour).

= 2.1.0 =
* New: slide descriptions. Each slide has an "Add description" button that opens WordPress's editor in a window (bold, italic, underline, links, lists). The current slide's description shows under the slider, below an outside pagination and a strip of thumbnails beside it; the block is as tall as the longest one, so the page under it does not move. Not with a vertical slider, Continuous scroll, Collage or as a background.
* New Description page: font size per device, weight, alignment, gap to the slider, own text colour (off: the page's), and "Text transition": As the slider (the text slides, turns with the cube, flips, moves as Coverflow, Cards or the Creative style do, at the slider's speed and the way it went), Fade, Slide or None. No motion where the visitor's system asks for reduced motion.
* New in Lightbox: "Descriptions in the lightbox", with the caption in a panel under the image.
* New in Layout: "Image position" per device — the part of an image cropped by "Cover" kept in view (also for a background and a collage); video posters follow it.
* WPBakery: the WP Swiper element has the slider choice only (no "Extra CSS class" field). A class given before is kept, and the shortcode's `class` still works.

= 2.0.0 =
* New: pagination style (Controls). Bullet width and height per device, border radius (empty: round; 0: square), space between bullets per device, and a longer current bullet ("pill"), wider in a row and taller in a column, growing and shrinking smoothly. Fraction font size per device. "Own pagination colors": the current bullet's, the others' and their opacity (the fraction takes the current one's); off, white over the slider and the page's text colour outside it as before. Sliders saved before look the same.
* Settings: Layout and Controls in blocks with titles, like the Thumbnails page — Controls: Movement, Arrows and keyboard, Pagination, Pagination style; Layout: Effect, Arrangement and Photo swapping (Collage), Slides, Size and fit, Look, Zoom. A block with none of its fields in use for the chosen effect is hidden. Some Layout fields moved with their block.
* Settings: whatever is unavailable (greyed) has one light grey, switches on or off alike (the knob shows which), and the Controls fields the Thumbnails effect does not use are greyed like the others.
* Settings: number fields that may stay empty show what empty means ("Round", "Auto").
* Preview of a background: the block at a screen's proportions, 16:9 on desktop, 4:3 on tablet and 3:4 on mobile, unless it has a minimum height of its own.
* Continuous scroll: no pagination classes or styles in the markup (it has no pagination).
* Development: PHPUnit tests with the WordPress test suite (tests/), run by GitHub Actions on every push on PHP 7.4 and 8.3 with WordPress 6.0 and 7.1; a release is published only once they pass.

= 1.4.0 =
* New effect: Collage, last in the effects list — not a slider but the photos placed together: four templates (a big photo on the left / in the middle, mosaic, a wide photo on top), Grid, Justified rows, Masonry and Random mosaic, set per device. Order with Shuffle, photos on screen, columns, row height, gap; with more photos than places, one place at a time fades to a photo not shown yet.
* New Background page: "Use as background" (Slide, Fade, Collage) — the slider fills the row or column with the class given to it, behind its content, with an overlay colour and a minimum block height per device. It plays on its own: arrows, pagination, keyboard, links, the lightbox and Height are not used. Without such a block it shows as usual, with a note for editors.
* New in Layout: "Zoom the main image" — the image of the slide in view slowly zooms in or out (Ken Burns) towards a point you choose, for as long as the slide is shown or a set time. For the effects with one main image (Slide with one slide in view or the wider active slide).
* WPBakery: with the Woodmart theme the WP Swiper element sits in its "Theme elements" tab.
* languages/wp-swiper.pot: the new strings.

= 1.3.1 =
* Slider settings: one accent colour (#2271b1) for the plugin's controls and WordPress's own in its boxes (focus rings, buttons, links), whatever the admin colour scheme of the profile; the rest of the admin keeps the scheme.
* Creative effect: Style is the first field of its Layout, above the fields that depend on it.
* Video page: a long YouTube / Vimeo title or link is cut at the right under a fade instead of widening the settings and hiding the Change and Remove buttons.
* Video page: Remove (a poster) is red, like WordPress's delete links.

= 1.3.0 =
* New: video slides. A video from the media library, or a YouTube / Vimeo link (pasted under the slides). Only the video of the slide in view plays, and only while the slider is on screen; a file or player loads only when its slide comes up, so the page stays as light as before. YouTube plays in its privacy-enhanced mode.
* New Video page: play automatically, muted, loop, show controls, pause when the slide is left, next slide when the video ends (with Autoplay), preload the next video, and the poster image of every video slide.
* New: "Videos in the lightbox" — a click on a video slide opens it in the lightbox, with sound and controls.
* New settings screen: Advanced (effect, direction) with a menu of pages beside it — Lightbox, Controls, Layout, Thumbnails, Caption, Video, Custom — and a preview of the slider in the other half, drawn from the current settings, saved or not, refreshed with its Update button. The separate Preview page is gone.
* Switches instead of checkboxes, Direction as buttons, sliders for transition speed and delay, help texts behind an "i" icon. Slides per view is hidden for the effects that show one slide.
* Controls now holds speed, loop and autoplay (with delay and pause on hover) above the arrows, pagination and keyboard; with Thumbnails only the pagination and keyboard are greyed.
* Thumbnails: a page of its own (Placement, Size, Look) and a new "Thumbnail height" per device (instead of Thumbnails per view).
* New: arrow height per device; the slider's own ID (Custom page, with the CSS class); Coverflow Rotate down to -90° (the side slides turn outwards).
* Lightbox: the image and the thumbnails in it take a border radius of their own, per device (empty: the slider's). Sliders that had "Rounded corners in the lightbox" keep their look.
* Height says what it measures: Image height, Main image height (Thumbnails) or Slider height (vertical).

= 1.2.1 =
* Thumbnails: "Transition" (first in Layout) — the slides slide or fade into one another. With Fade the direction is always horizontal.
* Thumbnails: thumbnail proportions and thumbnail border radius are set per device.
* Thumbnails: custom proportions are typed in one field, as "4x3" (also "16:9", "2.35x1"); those set in 1.2.0 are kept.
* Lists set per device say just "Inherit" for tablet and mobile.
* Lightbox: "Rounded corners in the lightbox" replaces "Lightbox image radius". The image takes the slide border radius and the thumbnails in the lightbox the thumbnail border radius, per device. It is off by default: tick it on sliders whose lightbox had a radius.
* Removed: "Thumbnail padding".

= 1.2.0 =
* Thumbnails: the position of the strip is set per device (desktop, tablet, mobile); an empty tablet or mobile choice takes the one of the larger screen.
* Thumbnails: "Custom" thumbnail proportions, with a width and a height of your own.
* Thumbnails: arrows on the strip (Controls > Arrows, with their colour) go to the previous and next slide. Arrows is on by default, so existing Thumbnails sliders get them; untick it to hide them. The rest of Controls stays greyed out for this effect.
* Thumbnails: "Thumbnails in the lightbox" shows the strip under the lightbox image, as many in view as in the slider; the lightbox image radius applies to them.
* New: a Preview button in the Shortcode box opens the saved slider (drafts too) on a page of its own, with a desktop / tablet / mobile switcher.

= 1.1.6 =
* New effect, Thumbnails: one slide at a time and a strip of thumbnails that picks it, below, above, left or right of the slider. Its Layout settings: thumbnails per view, gaps, proportions, alignment, border radius, padding, frame and inactive opacity.
* With Thumbnails the Controls settings are greyed out for now: the thumbnails switch the slides.

= 1.1.5 =
* "Delay between slides" now sits right under Autoplay in the slider settings.

= 1.1.4 =
* Deleting the plugin under Plugins now removes its sliders (with their slides and settings) and its update data. Deactivating or updating keeps them; images stay in the media library.
* The WP Swiper element is listed last in the WPBakery "Add element" window.

= 1.1.3 =
* New: Image border (Layout) — a frame around the images with its own width per device and colour, for every effect. It follows the corners, takes no room, and with Contain frames the image itself.

= 1.1.2 =
* Cards: arrows, pagination, keyboard and autoplay move the cards frame by frame, so the next card comes on top halfway through the move (as when dragged) instead of at once.
* Cards: at most three cards of the stack show on each side, and the stack never widens the page on a phone; Stack offset goes up to 15%.
* Creative Carousel with Contain: all images share one height (vertical sliders: one width), so the middle image is always the biggest, and the slider is no taller than the images.
* Creative Carousel: captions show on the middle slide only.
* Fixed: on a phone, a slider with Contain could get the desktop height limit (the device was read before the page settled); devices are now read through the same media queries as the CSS.

= 1.1.1 =
* Fixed: pagination over the slider is now placed from the slides' edges in every effect and direction. Vertical Cube, Flip and Cards no longer put "bottom" pagination 40px below the slides or "center" 20px off, and Cards side pagination sits on the card.
* The room under a Cube now follows its shadow (none without one); Flip no longer keeps 40px empty below the slides.
* Fixed: a click on Cube pagination no longer opens the lightbox or the slide's link.

= 1.1.0 =
* Settings in four boxes (Advanced, Controls, Layout · effect, Caption); each effect keeps its own Layout values.
* Settings that do not apply are greyed out instead of left active; their values are kept on save.
* Height: 0 means 500px, "Auto" fills the parent column; with Contain the slider is never taller than its images.
* New: arrow colour, Coverflow and Cards settings, Cube shadow settings, Creative styles including Carousel.
* New for the Slide effect: width from image, slides per group, continuous scroll with pause on hover.
* Fixed: arrows hidden by the theme's button styles, Cards loop skipping a slide, Cards stack spreading outside the block, rounded Cards corners at radius 0, autoplay looping with Infinite loop off, Creative slides moving sideways in vertical sliders.
* Removed: mouse wheel control, grab cursor setting (the hand cursor is always on).
* Updates from GitHub releases.

= 1.0.0 =
* First version.
