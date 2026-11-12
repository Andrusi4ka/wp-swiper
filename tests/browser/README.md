# Browser tests

[Playwright](https://playwright.dev/) tests of the front end on a local WordPress site with this plugin active. They open `fixture.php?case=<name>` for every case in `cases.php` (all the effects and the Collage and Art styles) at a desktop and a phone size, and check that each one:

- shows without errors in the console;
- loads the files it should (`snapshots/<size>/<case>-files.json`);
- lays its photos out as before (`snapshots/<size>/<case>-layout.txt`: each shown image's box, in pixels from the slider's corner);
- moves on with its arrow, and opens and closes the lightbox from a photo.

`fixture.php` renders the slider the way the editor's preview does, from the case's settings over the defaults, with the tests' own photos as its slides (and their video where a case asks for one): see "The photos and the video" below. It saves nothing, answers only requests from this machine (127.x) and is not part of the plugin's zip (`tests/` is export-ignore).

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

## The edit screen (admin*.spec.js)

Smoke tests of the slider's edit screen (admin.js and its parts): it opens without errors and loads every part; settings pages, another effect and Style (the preview drawn again), a color in Pickr, the gradient, Undo / Redo, the preview's devices, turning, dark screen and Developer mode, a field's device, the Slide info window, an image's position, the Collage's Shuffle, Art's Grid shapes tools, the font list, and a slider saved and read back (`admin.spec.js`, `admin-preview.spec.js`). What is done by hand in the preview, kept by the editor and undone (`admin-hand.spec.js`): Random mosaic's lines, Scattered's edges and ×, Freeform's corners and moves with Undo / Redo, Overlap's layers, Grid shapes joined and split, Art's photos swapped. Desktop only.

`admin-login.php` is their way in, from this machine only (as `fixture.php`, not in the zip): it logs in as the tests' own administrator, `wp-swiper-tests` (made the first time, with a password nobody knows: this page is its only way in), saves a slider with a case's settings (`cases.php`) and the tests' own photos, and opens it. Each test deletes its own slider after it (`?delete=<id>`); `?cleanup=1` deletes any left by a test that stopped half way. `global-setup.js` makes the user's one session before the tests (`?login=1`): tests that logged in at once each with a new session wrote the user's sessions over each other and were logged out.

The user can be deleted in Users when the tests are not needed; it is made again on the next run.

## Adding a case

A new entry in `cases.php` (settings as the edit screen posts them: a checkbox left out is off) and its name in `CASES` in `sliders.spec.js` (and in `NAVIGATES` if its arrow moves the slides). Then record its snapshots.

## The photos and the video

The slides are the tests' own files, `media/` (`photo-01.jpg` … `photo-10.jpg`, each its own proportions and color, its number on it; `video.mp4`, 4 s, 640 px, no sound), so the snapshots are the same on every machine, whatever its media library has. `media.php` adds them to the library once (marked by the meta `_wp_swiper_test_media`, the file's name and hash: a file changed here is added again, its old copy deleted) and gives them by name, in order. `global-setup.js` adds them before the tests run at once (`fixture.php?media=1`); `fixture.php?media=remove` deletes them from the library.

A new photo: `photo-11.jpg` and so on (a case takes the first ones; `cases.php` `photos`). After any change here the layouts' snapshots change too: `--update-snapshots` and look over the difference.
