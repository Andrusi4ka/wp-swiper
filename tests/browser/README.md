# Browser tests

[Playwright](https://playwright.dev/) tests of the front end on a local WordPress site with this plugin active. They open `fixture.php?case=<name>` for every case in `cases.php` (all the effects and the Collage and Art styles) at a desktop and a phone size, and check that each one:

- shows without errors in the console;
- loads the files it should (`snapshots/<size>/<case>-files.json`);
- lays its photos out as before (`snapshots/<size>/<case>-layout.txt`: each shown image's box, in pixels from the slider's corner);
- moves on with its arrow, and opens and closes the lightbox from a photo.

`fixture.php` renders the slider the way the editor's preview does, from the case's settings over the defaults, with the first images of the media library as its slides (and its first video where a case asks for one). It saves nothing, answers only requests from this machine (127.x) and is not part of the plugin's zip (`tests/` is export-ignore).

## Running them

Needs Node 18+, Google Chrome (the installed one is used, nothing else is downloaded) and the site with some images (and a video) in its media library.

```
cd tests/browser
npm install
npx playwright test
```

`WP_SWIPER_SITE` is the site's address (default `https://creativity.local`).

After a change that is meant to move the photos or load other files, record the new state and look at what changed in `snapshots/` before committing it:

```
npx playwright test --update-snapshots
```

The snapshots depend on the media library's images: on another site record them first.

## Adding a case

A new entry in `cases.php` (settings as the edit screen posts them: a checkbox left out is off) and its name in `CASES` in `sliders.spec.js` (and in `NAVIGATES` if its arrow moves the slides). Then record its snapshots.
