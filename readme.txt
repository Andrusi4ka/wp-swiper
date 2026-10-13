=== WP Swiper ===
Contributors: andrusi4ka
Tags: slider, carousel, swiper, gallery, wpbakery
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Image sliders powered by Swiper: build a slider from the media library, pick an effect, insert it with a shortcode or the WPBakery element.

== Description ==

WP Swiper adds a "WP Swiper" menu where you build image sliders from the media library and show them anywhere with `[wp_swiper id="123"]` or the "WP Swiper" WPBakery element.

* Eight effects: Slide, Fade, Cube, Coverflow, Flip, Cards, Creative (Push, Zoom, Slide apart, Flip, Rotate, Door and Carousel styles) and Thumbnails (one slide picked from a strip of thumbnails below, above, left or right of it, set per device, with arrows and its own proportions).
* Each effect keeps its own Layout settings, so switching effects never loses what you set.
* Slides per view, space between slides, border radius and height per device (desktop, tablet, mobile).
* Slide effect extras: wider active slide, slides as wide as their images, slides per group, continuous scroll (marquee).
* Image fit Cover or Contain, height in px / vh / % or Auto (fills the parent column).
* Arrows (with their own colour), bullets or fraction pagination in six positions over or outside the slider, keyboard control, autoplay, infinite loop.
* Lightbox with zoom (and, with Thumbnails, the strip of thumbnails in it), per-slide links (optionally in a new tab), captions with their own typography and background.
* Preview: the saved slider on a page of its own, as on a desktop, tablet or phone.
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

It does not apply with the current settings (for example Slides per view with Fade, which always shows one slide). Its value is kept and comes back when it applies again.

== Changelog ==

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
