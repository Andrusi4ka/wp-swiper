=== WP Swiper ===
Contributors: andrusi4ka
Tags: slider, carousel, swiper, gallery, wpbakery
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Image sliders powered by Swiper: build a slider from the media library, pick an effect, insert it with a shortcode or the WPBakery element.

== Description ==

WP Swiper adds a "WP Swiper" menu where you build image sliders from the media library and show them anywhere with `[wp_swiper id="123"]` or the "WP Swiper" WPBakery element.

* Nine effects: Slide, Fade, Cube, Coverflow, Flip, Cards, Creative (Push, Zoom, Slide apart, Flip, Rotate, Door and Carousel styles), Thumbnails (one slide picked from a strip of thumbnails below, above, left or right of it, set per device, with arrows and its own proportions) and Collage (the photos placed together by a template, a grid, justified rows, masonry or a random mosaic, the others swapped in).
* A slider can be the background of the row or column it is in, with an overlay; the main image can zoom slowly in or out (Ken Burns).
* Each effect keeps its own Layout settings, so switching effects never loses what you set.
* Slides per view, space between slides, border radius and height per device (desktop, tablet, mobile).
* Slide effect extras: wider active slide, slides as wide as their images, slides per group, continuous scroll (marquee).
* Image fit Cover or Contain, height in px / vh / % or Auto (fills the parent column).
* Arrows (with their own colour), bullets or fraction pagination in six positions over or outside the slider, with their own size, shape, spacing and colours and a "pill" current bullet, keyboard control, autoplay, infinite loop.
* Lightbox with zoom (and, with Thumbnails, the strip of thumbnails in it), per-slide links (optionally in a new tab), captions with their own typography and background.
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
