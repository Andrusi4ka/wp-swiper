# WP Swiper: інструкції для нового чату

Власний плагін WordPress зі слайдерами на Swiper. Автор Andrii Boiko,
репозиторій `Andrusi4ka/wp-swiper`. Цей файл не потрапляє в реліз
(`export-ignore` у `.gitattributes`).

## Як працювати з користувачем

- Відповідай українською.
- Питання, ідеї й «чи можемо ми…» — це обговорення. Код пиши тільки після
  явного «так / давай / зроби». Якщо є кілька варіантів, коротко опиши план
  і порадь один.
- Перед будь-якою зміною коду зроби точку відкату. На роботі це zip у
  `_backups` поруч із сайтом, вдома — коміт або гілка.
- На кожну нову функцію додавай тести в `tests/`. Перед комітом прожени
  весь PHPUnit.
- Перевіряй зміни в браузері, якщо є як: адмінка, прев'ю, сайт.
- Комітить і пушить лише на прохання. Робоча гілка — `art-effect`, не `main`.
  Реліз робиться тільки на прохання.
- Користувач теж пушить, з роботи й з дому. Перед роботою роби `git fetch`.

## Стан гілок (на 2026-10-27)

- 2026-10-27 реліз **v2.9.0**: `art-effect` злито в `main` (fast-forward),
  тег `v2.9.0`. Обидві гілки однакові; нова робота — знову в `art-effect`.
  Перевірено перед релізом: тести на PHP 7.4/8.3 з WordPress 6.0 і 7.1,
  слайдери, збережені 2.8.0, показуються як раніше. `.pot` у 2.9.0 НЕ
  оновлено (тут не дозволили завантажити WP-CLI) — оновити наступного разу.
- Що увійшло в v2.9.0:
  - `db1a5f8`, `fed44df`: ефект Art (Style «Ring»), підписи й описи для Art,
    сторінка Settings > Palette, вирівнювання абзаців опису.
  - `85f42a8`: у вікні Slide info для Caption і Description окремо для
    кожного слайду: Font size (поле з іконками desktop/tablet/mobile), Font
    weight (один список на всі екрани), Color (Iris + Opacity). Кнопка
    Replace photo, кнопка Clone на мініатюрі слайду.
  - коміт після нього (з цим файлом):
    - **Caption:** редактор має Format (абзац або H1–H6), як Description.
      Поле Tag на сторінці Caption прибрано. Старі підписи показуються в
      старому тезі через `legacy_caption_tag()` / `caption_markup()`, а
      `admin.js` переносить їх у новий формат при відкритті сторінки.
    - **Use as background:** не показує ні підписів, ні описів. Сторінки
      Caption і Description сіріють з поясненням.
    - **Image fit:** звичайний випадаючий список (був вкладками).
    - **Style колажу:** вибирається плитками-іконками
      (`assets/images/collage/{style}.svg`, `currentColor`). На tablet і
      mobile є плитка Inherit.
    - **4 шаблони колажу** (`feature-left`, `feature-center`, `mosaic`,
      `banner`) змінюють розміри перетягуванням ліній у прев'ю, як Random
      mosaic:
      - шаблони тепер описані деревами розрізів у `frontend.js`;
      - розміри зберігаються для кожного пристрою в `collage_cuts` у форматі
        `"feature-left:V0.5[0,H0.5[1,2]]"`;
      - Reset у полі Order їх очищає.
  - коміт після `f25fd0e` (2026-10-27, вдома):
    - **Height unit для кожного пристрою** (усі слайдери): `height_unit`
      responsive. «Auto» тільки на деяких екранах: `config.fill` =
      `{desktop,tablet,mobile}`, `frontend.js fills()` / `fitHeight()`
      ставить `.wp-swiper--fill` на потрібних. % на деяких:
      `--wp-swiper-block-height[-device]`.
    - **Style колажу «Scattered»** (`scatter`): фото посередині й 4 «руки»
      вертушкою, проміжки всюди однакові, кути порожні
      (`frontend.js scatterLayout()` / `scatterRects()`). Кількість фото —
      `collage_count`. Розміри: тягнути зовнішні краї фото в прев'ю
      (`preview-collage.js`), зберігаються в `collage_cuts` як
      `"scatter:1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8"`
      (`sanitize_scatter()`). При обміні фото їхні місця теж міняються
      розмірами. Іконка — колишній `random.svg` з кореня, тепер
      `assets/images/collage/scatter.svg`.
    - Кнопка на мініатюрі слайду завжди «Edit» (була «Add info» / «Edit info»).
    - Біля кожного кольору в налаштуваннях — стрілка назад до кольору за
      замовчуванням (`color_reset()`, як у вікні Slide info).
  - наступний коміт (2026-10-27, вдома):
    - **Image shadow** (блок Look): `image_shadow` + `_x`, `_y`, `_blur`,
      `_spread`, `_color` (тип `alpha_color`: той самий вибір кольору з
      прозорістю, що у Slide info, `color_pick()`). Одна на всі екрани.
      Де діє — `WP_Swiper_Settings::shadow()`: `SHADOW_EFFECTS` (slide з
      >1 слайдом / marquee / auto width / expand, coverflow, cards,
      creative carousel, collage, art), не фон. Swiper і колаж: box-shadow
      + padding під тінь (`--wp-swiper-shadow-room`); Contain — тінь на
      `.wp-swiper__media`. Art: `filter: drop-shadow` на сцені, без spread.
  - коміт після `2287e82` (2026-10-27, вдома), Art Ring:
    - `art_caption_middle` (сторінка Caption): підписи лише на центральному
      фото (`.wp-swiper-art--caption-middle`, CSS).
    - `art_swap_target` (Controls): `any` — як було; `middle` — центр по
      черзі проходить усі фото, кільце стоїть (`art.js swap()`, `sequence`).
    - Вміст вирізу (Opening): `art_opening_content` none/image/text,
      `art_opening_image` + `_image_size` (%), `art_opening_text` (новий тип
      поля `editor`: textarea + wp.editor у налаштуваннях, старт коли видно,
      `sanitize_description()`), `art_opening_font_size`, `art_opening_gap`
      (px, для кожного екрана). Блок поруч зі сценою (не під тінню),
      `art.js openingContent()` рахує найбільший прямокутник у вирізі.
  - коміт перед злиттям у main (2026-10-27, вдома), елемент WPBakery:
    - кнопка «Add slider» (нова вкладка, `post-new.php`) поруч з «Edit
      slider», лише для тих, хто може створювати слайдери. Після
      повернення у вкладку `wpbakery.js` бере список заново
      (AJAX `wp_swiper_vc_sliders`, `WP_Swiper_WPBakery::sliders()`,
      лише опубліковані) і вибирає щойно доданий.
  - коміт після злиття (2026-10-27, вдома): сторінку **WP Swiper → Settings**
    (власні кольори палітри) прибрано на прохання користувача — не
    повертати. Палітра «Text color» = кольори теми + чорний/білий/сірі
    (`WP_Swiper_Palette`), `palette.js`/`palette.css` видалено;
    `uninstall.php` досі видаляє старий `wp_swiper_text_colors`.
  - коміт «швидкість» (2026-10-27, вдома):
    - Swiper лише для слайдерів: `WP_Swiper_Render::enqueue( $swiper )`,
      `needs_swiper()` (не Collage/Art); `wp-swiper` без залежностей, `swiper`
      додається в deps, коли треба. Лайтбокс Collage/Art довантажує Swiper
      при першому відкритті (`frontend.js withSwiper()`, `wpSwiperI18n.swiper`).
    - `fetchpriority="high"` першому фото першого слайдера сторінки.
    - Стилі в `<head>`: з вмісту сторінки (`content_effects()`, і Art теж);
      а показані після `<head>` (HTML-блок, шапка) запам'ятовуються
      (`style()`, `remember_late_styles()`, опція `wp_swiper_late_styles`,
      понад `LATE_PAGES` сторінок → `'*'`, на всіх).
    - Мінімізація при релізі (`release.yml`): у копії для zip наші
      `assets/js/*.js` — terser@5.31.6, `assets/css/*.css` —
      lightningcss-cli@1.25.1, ті самі імена; у репозиторії файли як є.
      Перевірено: стиснуті файли працюють так само (лайтбокс, адмінка,
      знімки сторінок). frontend.js gzip 38.7 → 16.6 КБ, frontend.css
      13.8 → 6.5 КБ.
- Реліз **v2.9.1** (2026-10-27, тег `v2.9.1`; `.pot` знову не оновлено):
  - Style в Art вибирається плитками-іконками, як у колажу: `'tiles' => 'art'`,
    іконки в `assets/images/art/{style}.svg` (`currentColor`; `ring.svg` —
    іконка користувача, перенесена з `collage/`). Плитки тепер і для поля
    з одним значенням на всі екрани (`WP_Swiper_Admin::tiles()`, прихований
    `select.wp-swiper-tiles__select`, без Inherit). Новий стиль Art = нова
    іконка в `assets/images/art/`.
  - Прев'ю, фон (Use as background): фото можна тягнути (обмін, розміри),
    приклад вмісту блоку пропускає мишку і стоїть по центру (стилі в
    `class-wp-swiper-preview.php`). На сайті фон як і був — без мишки.
- Після 2.9.1 (2026-10-27, вдома, в `art-effect`):
  - `650521c`: CI (`tests.yml`) ганяє тести й при push у `art-effect`.
  - `.pot` перегенеровано під 2.9.1 (491 рядок, +69 нових, −15 прибраних).

## Що далі / відкладено

- **Наступний реліз** (на прохання; 2.9.0 вийшов 2026-10-27):
  - злити `art-effect` у `main`;
  - підняти версію в `wp-swiper.php` (`Version:` і `WP_SWIPER_VERSION`) та в
    `readme.txt` (`Stable tag:`) і додати changelog;
  - перегенерувати `.pot`:
    `wp i18n make-pot . languages/wp-swiper.pot --exclude=vendor,tests`. Якщо тека
    називається не `wp-swiper-main`, поправ у `.pot` рядок
    `Report-Msgid-Bugs-To`;
  - тег `vX.Y.Z` → workflow Release (спершу тести).
- **Відкладено користувачем** («на потім», без нагадувань не починати):
  - редактор градієнта для фону підпису (типи Linear/Radial, точки
    кольорів). План: свій на Iris, зберігати структуру, а не CSS;
  - безпека: `span style` у підписах/описах пропускає будь-який CSS, який
    дозволяє WordPress. Обмежити до кольору й підкреслення;
  - оптимізація: ~~не вантажити Swiper для Collage/Art~~, ~~мінімізувати JS/CSS
    при релізі~~ (зроблено), розбити `frontend.js` за функціями;
  - тести в браузері (Playwright).
- **Відхилено, не пропонувати знову:**
  - «Proportions» слайдів (варіанти «висота+пропорція» і
    «кількість+пропорція»);
  - зміна розмірів для Grid, Justified rows і Masonry;
  - прибирання загальної Image position (залишили).

## Як влаштований код (коротко)

- `includes/class-wp-swiper-settings.php`:
  - `fields()` — схема всіх налаштувань (section, type, responsive, effects,
    `inline_in`, `tiles`…);
  - `pages()` / `blocks()` — меню й блоки адмінки;
  - `sanitize()` відкидає невідомі ключі. Тому для прибраних полів є
    `legacy_*()`, а `admin.js` при завантаженні переносить старі значення в
    нові місця.
- `includes/class-wp-swiper-admin.php`: адмінка, вікно Slide info, рядки
  слайдів (значення слайду в прихованих `wp_swiper_slides[...]`),
  `posted_slides()`. Іконки вставляються інлайн через `svg_icon()`.
- `includes/class-wp-swiper-render.php`: розмітка й `data-*` конфіг. Art
  окремо: `class-wp-swiper-art.php`, `art.js`, `art.css`.
- `assets/js/frontend.js`: Swiper, lightbox, колаж (`collage()`: шаблони й
  Random mosaic через дерева розрізів `parseMosaic` / `serializeMosaic`).
- Прев'ю — iframe:
  - `preview-collage.js`: обмін фото, лінії-ручки;
  - `preview-art.js`;
  - `preview-dev.js`: Developer mode.
  
  Вони шлють `postMessage`, `admin.js` записує значення в приховані поля.

## Тести

Див. `tests/README.md`. Треба PHP 7.4+, Composer, WP-CLI і окрема база MySQL
`wp_swiper_tests`, лише для тестів. Потім:
`composer install`, `wp core download --path=<core>`,
`WP_CORE_DIR=<core> WP_TESTS_DB_HOST=<host> tests/vendor/bin/phpunit`.
Станом на цей коміт: 271 тест, усі зелені. CI (`tests.yml`) ганяє їх на
PHP 7.4 / 8.3 при push у `main` і `art-effect` та на PR.

Вдома (OSPanel): PHP у PATH немає — `C:/OSPanel/modules/PHP-8.3/PHP/php.exe
tests/vendor/bin/phpunit`; база — `WP_TESTS_DB_HOST=127.127.126.4`
(на `localhost` MySQL не слухає). WP-CLI вдома не встановлено: користувач
дозволив завантажувати офіційний `wp-cli.phar` (GitHub releases) у
тимчасову теку й запускати через цей PHP.
