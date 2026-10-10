# WP Swiper

Image sliders for WordPress powered by [Swiper](https://swiperjs.com/): build a slider from the media library, pick an effect and insert it with a shortcode or the WPBakery element.

- **Effects:** Slide, Fade, Cube, Coverflow, Flip, Cards, Creative (Push, Zoom, Slide apart, Flip, Rotate, Door, Carousel).
- **Per effect Layout:** each effect keeps its own layout values.
- **Per device:** slides per view, spacing, radius and height for desktop, tablet and mobile.
- **Controls:** arrows, bullets or fraction pagination, keyboard, autoplay, loop, lightbox, links, captions.

Requires WordPress 6.0+ and PHP 7.4+.

## Install

Download `wp-swiper.zip` from the [latest release](https://github.com/Andrusi4ka/wp-swiper/releases/latest) and upload it under **Plugins > Add New > Upload Plugin**. Newer releases then show up as regular plugin updates.

Use it with `[wp_swiper id="123"]` (the id is shown on the slider's edit screen) or the **WP Swiper** element in WPBakery.

## Releasing a new version

1. Raise the version in `wp-swiper.php` (the `Version:` header and `WP_SWIPER_VERSION`) and in `readme.txt` (`Stable tag:`), and add a changelog entry to `readme.txt`.
2. Commit, then tag and push:
   ```
   git tag v1.2.0
   git push origin main --tags
   ```
3. The **Release** workflow (`.github/workflows/release.yml`) builds `wp-swiper.zip` and publishes a GitHub release for the tag.
4. Sites with the plugin see the update under **Plugins** (WordPress checks every 12 hours; **Dashboard > Updates > Check again** checks at once).

The tag must match the `Version:` header, without the `v`.

## Structure

| Path | What it holds |
|---|---|
| `wp-swiper.php` | Plugin header, post type, update checker |
| `includes/class-wp-swiper-settings.php` | Settings schema (single source for the form, saving and the front end), migrations |
| `includes/class-wp-swiper-admin.php` | Slider edit screen |
| `includes/class-wp-swiper-render.php` | Shortcode and markup |
| `includes/class-wp-swiper-wpbakery.php` | WPBakery element |
| `assets/js/admin.js`, `assets/css/admin.css` | Edit screen behaviour and styles |
| `assets/js/frontend.js`, `assets/css/frontend.css` | Slider behaviour and styles |
| `assets/vendor/swiper` | Swiper 11 (MIT) |
| `vendor/plugin-update-checker` | Plugin Update Checker 5 (MIT) |

## License

GPL-2.0-or-later. Swiper and Plugin Update Checker are MIT licensed.
