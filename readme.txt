=== WP Swiper ===
Contributors: andrusi4ka
Tags: slider, carousel, swiper, gallery, wpbakery
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Image sliders powered by Swiper: build a slider from the media library, pick an effect, insert it with a shortcode or the WPBakery element.

== Description ==

WP Swiper adds a "WP Swiper" menu where you build image sliders from the media library and show them anywhere with `[wp_swiper id="123"]` or the "WP Swiper" WPBakery element.

* Seven effects: Slide, Fade, Cube, Coverflow, Flip, Cards and Creative (Push, Zoom, Slide apart, Flip, Rotate, Door and Carousel styles).
* Each effect keeps its own Layout settings, so switching effects never loses what you set.
* Slides per view, space between slides, border radius and height per device (desktop, tablet, mobile).
* Slide effect extras: wider active slide, slides as wide as their images, slides per group, continuous scroll (marquee).
* Image fit Cover or Contain, height in px / vh / % or Auto (fills the parent column).
* Arrows (with their own colour), bullets or fraction pagination in six positions over or outside the slider, keyboard control, autoplay, infinite loop.
* Lightbox with zoom, per-slide links (optionally in a new tab), captions with their own typography and background.
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

= Why is a setting greyed out? =

It does not apply with the current settings (for example Slides per view with Fade, which always shows one slide). Its value is kept and comes back when it applies again.

== Changelog ==

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
