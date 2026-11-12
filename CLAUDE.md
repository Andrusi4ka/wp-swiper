# WP Swiper: інструкції для нового чату

Власний плагін WordPress зі слайдерами на Swiper. Автор Andrii Boiko,
репозиторій `Andrusi4ka/wp-swiper`. Цей файл і `docs/` не потрапляють у
реліз (`export-ignore` у `.gitattributes`).

Тут — лише те, що треба пам'ятати завжди: як працювати, заборони, пастки,
як запускати. **Подробиці кожного рішення (чому так, що відхилено, як
влаштовано) — у [docs/decisions.md](docs/decisions.md)**: читай потрібний
розділ, перш ніж міняти відповідну частину. Що в якому релізі — changelog у
`readme.txt` і `git log`; журнал змін не вести ні тут, ні там.

## Як працювати з користувачем

- Відповідай українською.
- Питання, ідеї й «чи можемо ми…» — це обговорення. Код пиши тільки після
  явного «так / давай / зроби». Якщо є кілька варіантів, коротко опиши план
  і порадь один.
- Перед будь-якою зміною коду зроби точку відкату. На роботі це zip у
  `_backups` поруч із сайтом, вдома — `git stash push -u -m "backup/before-…"`
  і одразу `git stash apply` (не гілки). Після коміту ці stash не потрібні —
  `git stash clear`.
- На кожну нову функцію додавай тести в `tests/`. Перед комітом прожени
  весь PHPUnit, а якщо змінено фронтенд — і тести в браузері.
- Перевіряй зміни в браузері, якщо є як: адмінка, прев'ю, сайт.
- Комітить і пушить лише на прохання. Одна гілка — `main` (рішення
  користувача 2026-11-03; інших гілок не заводити). Реліз — тільки на прохання.
- Користувач теж пушить, з роботи й з дому. Перед роботою `git fetch` і,
  якщо гілка відстає, `git pull --ff-only`.
- Нове рішення користувача: подробиці — у свій розділ `docs/decisions.md`;
  сюди — рядок, лише якщо це заборона чи пастка.
- **Не оновлювати WP Swiper через WordPress на сайті з робочою копією.**
  2026-11-04 таке оновлення стерло теку з `.git` (склоновано заново). Тепер
  копія з `.git` оновлень не отримує (`wp_swiper_is_working_copy()`, фільтр
  `wp_swiper_is_working_copy`, `tests/test-updates.php`) — не прибирати.
  Вдома активна `wp-swiper/wp-swiper.php`, на роботі — `wp-swiper-main`;
  браузерні тести беруть саме активну копію.

## Стан

- Останній реліз **v3.7.0** (2026-11-12): Collage **Overlap**. Попередній —
  v3.6.0 (2026-11-10).
- Старі гілки збережено в `Creativity.local/_backups/wp-swiper-old-branches-2026-11-09.bundle`.

## Наступний реліз (на прохання)

1. Робота вже в `main`.
2. Підняти версію в `wp-swiper.php` (`Version:` і `WP_SWIPER_VERSION`) та в
   `readme.txt` (`Stable tag:`), додати changelog.
3. Перегенерувати `.pot`:
   `wp i18n make-pot . languages/wp-swiper.pot --exclude=vendor,tests,docs`.
   WP-CLI 2.11.0 phar у тимчасовій теці (SHA-512 звірити). Якщо тека
   називається не `wp-swiper-main`, поправ у `.pot` рядок `Report-Msgid-Bugs-To`.
4. Тег `vX.Y.Z` → workflow Release (спершу тести). Він мінімізує
   `assets/js/*.js` (terser@5.31.6) і `assets/css/*.css`
   (lightningcss-cli@1.25.1) у копії для zip; у репозиторії файли як є.

## Не міняти без прохання

Рішення користувача, коротко. Чому і як — у `docs/decisions.md`, розділ
«Рішення користувача».

**Редактор**
- Layout однаковий для всіх ефектів: головний блок ефекту → **Size** (Height,
  Gap, далі решта розмірів) → власні блоки ефекту → Look → Zoom → Background.
  Порядок — кінець `WP_Swiper_Settings::blocks()`, нове поле розміру — у блок
  `size` (тест `test_every_effect_has_its_size_in_one_place`).
- Телефон і планшет завжди наслідують більший екран: жодних `device_defaults`.
- Один пристрій на весь редактор (`shareDevice()` → `setDevice()`,
  `localStorage wpSwiperDevice`). `showFieldDevice()` — одна на весь JS,
  копій не заводити.
- Undo / Redo: одна історія, до 50 кроків, без списку слайдів і текстів.
  Прев'ю при відкаті не перезавантажувати. Новий lock — лише через
  `lockable()` / `lockableValue()`, інакше відкат його не поверне.
- Прев'ю оновлюється само лише на інший Effect або Style з плиток
  (`autoUpdate()`); «живого прев'ю» для решти полів не треба.
- Усі кольори — Pickr 1.9.1 (`color_pick()`), з піпеткою де є
  `window.EyeDropper`. Прозорість — у самому кольорі (`alpha_color`), окремих
  полів «opacity %» не повертати (Overlay, Caption bg, Cube shadow).
- Значення за замовчуванням, різне для ефектів, — лише ключ схеми
  `effect_default`.
- Радіуси й Image border — блок «кути / сторони» (`render_box_field()`),
  зв'язаний за замовчуванням. Повзунки — ключ схеми `range`.
- Картка «What … can do» (`includes/effect-notes.php`): новий ефект = його
  список; згадувати шорткод і поворот (Collage); пункт ≤ 120 символів.
- Права за ролями (WP Swiper → Settings, `WP_Swiper_Roles`): видаються на
  льоту фільтром `user_has_cap`, у ролі не записуються. Нова перевірка прав
  = `WP_Swiper_Roles::EDIT` / `CREATE` / `DELETE` / `WPBAKERY` або `cap` типу
  запису; `CAPABILITY` (`manage_options`) — лише «керує сайтом».
- Сторінку з власною палітрою (до 2.9) прибрано — не повертати. Палітра
  «Text color» = кольори теми + чорний / білий / сірі.

**Слайдери**
- Image shadow виходить назовні: `.swiper` `overflow: clip` +
  `overflow-clip-margin`. НЕ `overflow: visible` + `clip-path` (горизонтальна
  прокрутка сторінки). Сусідні слайди за краєм ховає `slider.js hideOutside()`.
- Тінь у режимі фону — лише Collage і Art.
- Zoom on hover — фіксовано 106%, не для Cube / Flip / Coverflow / Cards, не у
  фоні.
- Drag with the mouse вимикає лише мишу (`simulateTouch: false`);
  `allowTouchMove` не чіпати.
- Coverflow: Depth і Stretch — для кожного екрана, Rotate і Scale — одні.
- Minimum block height фону прибрано повністю — не повертати.

**Колаж**
- Freeform: фото не накладаються. Overlap — окремий стиль (шари `z`, верхнє
  — те, що тягнуть; без обміну скиданням; нахил окремого фото — відкладено).
- Кнопка × «прибрати місце» — лише Scattered, Freeform, Overlap.
- Content in a place: вузол переноситься в клітинку сам (не копія), сховище —
  `<div … hidden>`, НЕ `<template>`. Шорткоди в тексті виконуються, сам у
  собі — ні. Не для Justified / Masonry.
- Порядок фото, коли місць більше, ніж фото: «порядок по вікнах»
  (`window.wpSwiperPlaces`, `order_indexes()`), фото можуть повторюватись.

**Art і Text mask**
- Стилі Art: Ring, Grid + circle, Grid + square, Grid shapes, SVG shape
  завжди останнім. Стилів «+ text» немає — замість них Content in a window.
- Розмір фото Art: копії WordPress у `data-wp-swiper-copies`, без
  `srcset` / `sizes` (ту спробу відкочено). Лайтбокс — `full`.
- Grid shapes: зберігається опис сітки (`art_cells`), а не контур; свій радіус
  — лише опуклим кутам.
- Text mask: плагін сам шрифти НЕ вантажить (GDPR); By name бере шрифт, що
  сторінка вже має; список — свій `font-picker.js`, не `<datalist>`. WoodMart —
  не залежність (немає його опції — немає пунктів).
- Gradient зберігається описом (`linear;left;…@0;…@100`), не CSS; gradX не
  підключати.

## Безпека

- Стилі в текстах: лише `TEXT_STYLES`; фільтр `safe_style_css` діє тільки в
  `sanitize_rich_text()`. Тексти санітизуються й при виведенні.
- Тексти не-адміністраторів — через `wp_kses` зі своїм списком тегів.
- Посилання з `target` — `rel="noopener"` через `noopener()`;
  `wp_targeted_link_rel()` застаріла з WP 6.7 — не використовувати.
- `sanitize_fields()`: масив замість одного значення = відсутнє; `sanitize()`
  відкидає невідомі ключі (для прибраних полів — `legacy_*()`).
- JSON у `<script>` прев'ю — з `JSON_HEX_TAG | JSON_HEX_AMP`.
- Дії GitHub Actions закріплені за SHA (тег у коментарі); SHA брати через
  `git ls-remote https://github.com/<repo> refs/tags/<tag>` (і `^{}`).
  `tests.yml` — лише читання. Оновлення сайтів ідуть з релізів GitHub: 2FA
  обов'язкова.

## Пастки

- Іконки-малюнки користувача з Illustrator: переписати на `currentColor`
  без `<style>` / `class` (інакше `.st0` потрапить у сторінку адмінки).
- Рамку в CSS задавати `border-width` / `border-style` / `border-color`
  окремо: скорочений `border:` з чотирма товщинами недійсний.
- Значення Layout ефекту (JSON `true` / `false`) у приховане поле писати
  «1» / «0»: рядок «false» став би «увімкнено» (`writeLayout()`).
- Частина JS адмінки не може при завантаженні кликати функцію пізнішої
  частини — лише в обробниках (див. «Як влаштований код»).
- `field( name ).toggle( true )` в `admin.js` показує поле й для інших
  ефектів (фільтр `data-effects` — той самий `display`): ховати лише
  `hide()` під умовою свого ефекту.
- Перетягування в прев'ю: місце вмісту брати з повного списку місць ДО
  того, як прибрано повтори фото (`setContentWindow()`, `setOrder( list, content )`).
- Видаляючи рядки за номерами (sed), звіряй `git diff` — так одного разу
  пропав селектор.

## Відомі обмеження

- Freeform / Overlap: зміна Gap після ручної розстановки не пересуває місця;
  Shuffle або Reset дають нову розкладку.
- Art: текст, що не влазить у розрив Ring чи вікно, обрізається на слові з
  «…», шрифт не зменшується. Прев'ю без стилів теми — влазить інакше.
- Art SVG shape: підпис у центрі bbox вікна може виходити за органічну форму.
- Art: на великих фото «нечітко → скачок» під час анімації (ймовірно, Chrome).
- Адмінку тести перевіряють частково (`admin*.spec.js`; ручні правки в прев'ю —
  `admin-hand.spec.js`). Не покрито: лінії шаблонів, переміщення фото в кінець
  рукава Scattered, обмін у Freeform, перетягування Content, Undo більшості
  правок, крім Freeform / Overlap. Після змін у `admin*.js` чи `preview-*.js`
  прожени їх і попроси користувача перевірити руками решту.
- Undo: правки одного поля (і однієї правки в прев'ю) протягом 0,8 с — один
  крок (`noteChange()`); у тестах між правками чекати ≥ 1 с.

## Відкладено і відхилено

- **Відкладено** (без нагадувань не починати):
  - нахил окремого фото в Overlap;
  - поділ `slider.js` за ефектами (2026-11-12: виграш кілька КБ після
    стиснення проти Swiper, ризик для всіх слайдерів — до реальної причини);
  - разовий перезапис міграцій за `_version` замість `merge_*()` у `get()`
    (2026-11-12: зараз дешево, а помилка перезапису псує збережені дані —
    повернутись, коли `merge_*` стане помітно більше);
  - Text mask як фон; повернуте на 90° слово; червона підсвітка обрізаного
    тексту в Developer mode.
- **Відхилено, не пропонувати знову:**
  - «Proportions» слайдів («висота+пропорція» і «кількість+пропорція»);
  - зміна розмірів для Grid, Justified rows і Masonry;
  - прибирання загальної Image position;
  - Art Grid + circle з полем Shape («квадрат / Fill width»);
  - Art: «квадратні фото» в сітці; клік по меншому вікну «перенести в головне».

## Як влаштований код (коротко)

Докладно — `docs/decisions.md`, «Як влаштований код».

- **Схема налаштувань** — `includes/settings-fields.php` (зливає частини з
  `includes/settings-fields/`), Art додає свої (`includes/art-fields.php`).
  Ключ двічі — падає тест.
- **Класи розбито на трейти** за темами (`trait-wp-swiper-<клас>-<тема>.php`):
  `WP_Swiper_Settings` (migrations, sanitize, text, media, box),
  `WP_Swiper_Admin` (slides, controls, fields, list), `WP_Swiper_Render`
  (assets, config, media, collage), `WP_Swiper_Art` (cells, sizes, gradient,
  parts). Новий метод — у трейт своєї теми; константи — лише в класі (PHP 7.4).
- **JS адмінки** — частини `window.wpSwiperEditor.parts.<назва>`, `admin.js`
  запускає їх по черзі (`head`, `slides`, …, `box`, `frame`, `preview`,
  `tail`). Спільне — через `shared`: з ранішої частини — аліас на початку, з
  пізнішої або змінне — `shared.x` щоразу. Нова частина — рядок у
  `WP_Swiper_Admin::SCRIPTS` і в списку запуску. PHP-тести шукають JS через
  `admin_js()`.
- **CSS адмінки** — частини в `WP_Swiper_Admin::STYLES` (новий файл — рядок там).
- **Сайт**: `frontend.js/.css` — завжди (лайтбокс, відео, висоти, фон);
  `slider.js/.css` — слайдери (правило лише для слайдерів — у `slider.css`);
  `thumbs.css` — Thumbnails; `collage-layouts.js` + `collage.js/.css` —
  колаж (нова функція розкладки — у `collage-layouts.js` і
  `wpSwiperCollageLayouts`); `art.js` + `art-cells.js` (Grid shapes) +
  `art-text.js` (Text mask), лише для свого стилю (`WP_Swiper_Art::SCRIPTS`),
  помічники частинам — `window.wpSwiperArtKit`; `content.js` — спільне колажу
  й Art (Content, `wpSwiperPlaces`). Спільне `slider.js` / `collage.js` —
  `window.wpSwiperShared`.
- **Прев'ю** — iframe `class-wp-swiper-preview.php` зі своїм списком скриптів:
  новий скрипт сайту — додати й туди; власні `preview-*.js/css` — у
  `register_assets()`. `preview-*.js` шлють `postMessage`, `admin-preview.js`
  пише значення в приховані поля. Developer mode: нові px у шарі — лише
  `calc(… * var(--k))`.
- Новий пристрій прев'ю = малюнок + рядок у `WP_Swiper_Preview::DEVICES`
  (+ `SIZES`). Брейкпоінти 767 / 1024 не міняти.

## Тести

**PHPUnit** — `tests/README.md`. PHP 7.4+, Composer, окрема база MySQL
`wp_swiper_tests` лише для тестів (тести стирають її таблиці), `WP_CORE_DIR` —
копія ядра WordPress (можна скопіювати ядро сайту без `wp-content`,
`wp-config.php` і `_backups`). 470 тестів (2026-11-12); CI ганяє їх на PHP
7.4 / 8.3 при push у `main` та на PR.

- **Вдома** (OSPanel): PHP `C:/OSPanel/modules/PHP-8.3/PHP/php.exe` (і
  `PHP-7.4/PHP/php.exe`) з `-d display_startup_errors=0` (попередження
  imagick); `WP_TESTS_DB_HOST=127.127.126.4`. WP-CLI немає: можна
  завантажити офіційний `wp-cli.phar` у тимчасову теку.
- **На роботі**: PHP `C:/OSPanel/modules/PHP-8.3/php.exe` (і
  `PHP-7.4/php.exe`, без теки `PHP`), `WP_TESTS_DB_HOST=127.0.1.13`. Python
  немає (скрипти — на PHP), WP-CLI для тестів не потрібен.

**У браузері** — `tests/browser/` (Playwright 1.48.2, встановлений Chrome),
`tests/browser/README.md`. Лише локально (потрібен сайт).
`cd tests/browser && npm install && npx playwright test`; після навмисної
зміни розкладки — `--update-snapshots` і переглянути різницю.

- `fixture.php` показує слайдер з налаштувань `cases.php` на власних фото
  й відео тестів (лише з 127.x). `sliders.spec.js` — кожен випадок на desktop і
  mobile: без помилок, які файли, де фото (знімки в `snapshots/`), стрілки,
  лайтбокс.
- **Медіа тестів — свої** (2026-11-12, `tests/browser/media/`: 10 фото,
  згенерованих GD, і 4-секундне відео користувача, 640 px): `media.php` один
  раз додає їх у медіатеку (meta `_wp_swiper_test_media` = ім'я:хеш),
  `global-setup.js` — перед тестами (`fixture.php?media=1`; `?media=remove`
  — прибрати). Тож знімки однакові вдома й на роботі; медіатека сайту не
  важлива. Нове фото — `photo-11.jpg`, потім `--update-snapshots`.
- Адмінка: `admin.spec.js`, `admin-preview.spec.js`, `admin-hand.spec.js`
  (лише desktop, 36 тестів), помічники — `admin-kit.js`. Вхід — `admin-login.php` (лише з 127.x),
  тестовий адміністратор `wp-swiper-tests` створюється сам, одна сесія на
  всіх (`global-setup.js`). Кожен тест створює слайдер із `cases.php` і
  видаляє свій; `?cleanup=1` — залишки.
