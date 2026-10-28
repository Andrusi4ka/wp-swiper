# WP Swiper: інструкції для нового чату

Власний плагін WordPress зі слайдерами на Swiper. Автор Andrii Boiko,
репозиторій `Andrusi4ka/wp-swiper`. Цей файл не потрапляє в реліз
(`export-ignore` у `.gitattributes`).

Що в якому релізі — changelog у `readme.txt` і `git log`. Сюди не пиши
журнал змін, лише те, чого не видно з коду: рішення користувача, відомі
обмеження, підводні камені, як запускати.

## Як працювати з користувачем

- Відповідай українською.
- Питання, ідеї й «чи можемо ми…» — це обговорення. Код пиши тільки після
  явного «так / давай / зроби». Якщо є кілька варіантів, коротко опиши план
  і порадь один.
- Перед будь-якою зміною коду зроби точку відкату. На роботі це zip у
  `_backups` поруч із сайтом, вдома — коміт або гілка (`backup/before-…`).
- На кожну нову функцію додавай тести в `tests/`. Перед комітом прожени
  весь PHPUnit, а якщо змінено фронтенд — і тести в браузері.
- Перевіряй зміни в браузері, якщо є як: адмінка, прев'ю, сайт.
- Комітить і пушить лише на прохання. Робоча гілка — `art-effect`, не `main`.
  Реліз робиться тільки на прохання.
- Користувач теж пушить, з роботи й з дому. Перед роботою роби `git fetch`
  і, якщо гілка відстає, `git pull --ff-only`.

## Стан

- Останній реліз **v3.0.0** (2026-10-28): `art-effect` злито в `main`, `.pot`
  оновлено.
- Після нього в `art-effect` (2026-10-28): тести в браузері
  (`tests/browser/`), схема полів в окремому `includes/settings-fields.php`,
  слайдери в окремому `assets/js/slider.js` (див. «Як влаштований код»).

## Наступний реліз (на прохання)

1. Злити `art-effect` у `main`.
2. Підняти версію в `wp-swiper.php` (`Version:` і `WP_SWIPER_VERSION`) та в
   `readme.txt` (`Stable tag:`), додати changelog.
3. Перегенерувати `.pot`:
   `wp i18n make-pot . languages/wp-swiper.pot --exclude=vendor,tests`. Якщо
   тека називається не `wp-swiper-main`, поправ у `.pot` рядок
   `Report-Msgid-Bugs-To`.
4. Тег `vX.Y.Z` → workflow Release (спершу тести). Він мінімізує наші
   `assets/js/*.js` (terser@5.31.6) і `assets/css/*.css`
   (lightningcss-cli@1.25.1) у копії для zip; у репозиторії файли як є.

## Рішення користувача (не міняти без прохання)

- Сторінку **WP Swiper → Settings** (власна палітра) прибрано — не
  повертати. Палітра «Text color» = кольори теми + чорний/білий/сірі
  (`WP_Swiper_Palette`); `uninstall.php` досі видаляє старий
  `wp_swiper_text_colors`.
- **Art:** фото завжди `full` без `srcset` (`photo()`), без налаштування.
  Спробу підказувати браузеру `sizes` відкочено («щось зламав»). Клік по
  меншому вікну «перенести в головне» прибрано: клік — посилання або
  лайтбокс, як в інших стилях.
- **Art Grid + circle:** фото неповного останнього ряду — по центру ряду.
  Варіант «квадрат / Fill width» з полем Shape відхилено.
- **Art SVG shape:** порядок вікон `main` → назви-числа `2`, `3`… → більші;
  What changes за замовчуванням `any`; трафарет — четвертий варіант What
  changes (`one`), а не окреме поле. Для кожного екрана — лише файл; What
  changes, Appearance, затримка й швидкість — одні на всі екрани. Понад 100
  фігур — лишаються перші 100 за порядком у файлі.
- **Collage Freeform:** фото не накладаються; Shuffle — нова випадкова
  розкладка, Reset — перша; Gap — «магніт».
- Кнопка × «прибрати місце» — лише Scattered і Freeform; для інших стилів —
  коли попросить.
- Undo / Redo в прев'ю — окремо для кожного пристрою, до 50 кроків, до
  перезавантаження сторінки.

## Відомі обмеження

- Freeform: зміна Gap після ручної розстановки не пересуває збережені місця;
  Shuffle або Reset дають нову розкладку з новим Gap.
- Art SVG shape: підпис у центрі bbox вікна (у трафареті — форми) може
  виходити за край органічної форми.
- Art: на великих фото «нечітко → скачок» під час анімації (поява,
  hover-zoom), найімовірніше Chrome так зменшує картинки.
- Прев'ю в адмінці автоматично не перевіряється (тестам потрібен вхід,
  користувач вирішив тестувати лише сайт). Після змін у `admin.js` чи
  `preview-*.js` попроси користувача перевірити руками.

## Відкладено і відхилено

- **Відкладено** («на потім», без нагадувань не починати):
  - Art: `art.js` сам вибирає кожному вікну найменшу копію WordPress ≥
    намальованої ширини (cover × DPR × 1.06 на hover) і ставить її `src`
    (сервер дає список копій у data-атрибуті);
  - редактор градієнта для фону підпису (Linear/Radial, точки кольорів).
    План: свій на Iris, зберігати структуру, а не CSS;
  - безпека: `span style` у підписах/описах пропускає будь-який CSS, який
    дозволяє WordPress. Обмежити до кольору й підкреслення;
  - розділити `admin.js` на частини (вікно Slide info, точка фокуса, прев'ю,
    поля): одна велика функція зі спільним станом, без тестів адмінки
    ризиковано.
- **Відхилено, не пропонувати знову:**
  - «Proportions» слайдів («висота+пропорція» і «кількість+пропорція»);
  - зміна розмірів для Grid, Justified rows і Masonry;
  - прибирання загальної Image position.

## Як влаштований код (коротко)

- `includes/settings-fields.php` — схема всіх налаштувань (section, type,
  responsive, effects, `inline_in`, `tiles`, `styles`, `soon`…). Його
  повертає `WP_Swiper_Settings::fields()` (`require` всередині методу, тож
  `self::` там — цей клас), Art додає свої з `WP_Swiper_Art::fields()`.
- `includes/class-wp-swiper-settings.php`:
  - `pages()` / `blocks()` — меню й блоки адмінки;
  - `sanitize()` відкидає невідомі ключі. Тому для прибраних полів є
    `legacy_*()`, а `admin.js` при завантаженні переносить старі значення в
    нові місця;
  - `collage_cuts` зберігає розміри колажу для кожного пристрою:
    шаблони й Random mosaic — дерево розрізів
    (`"feature-left:V0.5[0,H0.5[1,2]]"`), Scattered —
    `"scatter:1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8"`, Freeform —
    `"freeform:2.3077;x,y,w,h;…"` (в одиницях висоти кадру, ≤ 100 місць).
- `includes/class-wp-swiper-admin.php`: адмінка, вікно Slide info, рядки
  слайдів (значення слайду в прихованих `wp_swiper_slides[...]`),
  `posted_slides()`, плитки `tiles()`. Іконки інлайн через `svg_icon()`;
  іконки стилів — `assets/images/{collage,art}/{style}.svg` (`currentColor`).
- `includes/class-wp-swiper-render.php`: розмітка й `data-*` конфіг, які файли
  підключити:
  - `enqueue( $swiper )`: `frontend.js`/`.css` завжди; для слайдерів
    (`needs_swiper()`, не Collage/Art) ще Swiper і `slider.js`
    (deps `swiper`, `wp-swiper`). `enqueue_collage()` — `collage.js`/`.css`;
  - стилі в `<head>` — з вмісту сторінки (`content_effects()`); показані
    після `<head>` запам'ятовуються (`remember_late_styles()`, опція
    `wp_swiper_late_styles`, понад `LATE_PAGES` сторінок → `'*'`);
  - `fetchpriority="high"` першому фото першого слайдера сторінки.
- Art: `class-wp-swiper-art.php`, `art.js`, `art.css`.
  `class-wp-swiper-svg-shape.php` розбирає SVG у геометрію (JSON лише з
  M/L/C/Z і числами 0–1); сам файл ніде не виводиться.
- JS на сайті:
  - `frontend.js` — на кожній сторінці зі слайдером, колажем або Art:
    лайтбокс (сам є Swiper; на сторінці без Swiper довантажує його при
    першому відкритті, `withSwiper()`), плеєри відео, висоти, фон, `init()`;
  - `slider.js` — слайдери: ефекти, Expand, мініатюри, пагінація, відео-
    слайди, marquee, zoom. `init()` викликає `window.wpSwiperSlider( el,
    config )`;
  - `collage.js` + `collage.css` — колаж. `init()` викликає
    `window.wpSwiperCollage`;
  - `slider.js` і `collage.js` беруть спільне з `window.wpSwiperShared`.
    Нова спільна функція = додати її туди. Обидва, якщо прийшли пізніше за
    `frontend.js`, самі ще раз викликають `wpSwiperInit()`.
- Прев'ю — iframe (`class-wp-swiper-preview.php`, свій список скриптів у
  `wp_print_scripts()`; новий скрипт сайту — додати й туди):
  - `preview-collage.js`: обмін фото, лінії-ручки, Scattered, Freeform;
  - `preview-art.js`;
  - `preview-dev.js`: Developer mode.

  Вони шлють `postMessage`, `admin.js` записує значення в приховані поля.

## Тести

**PHPUnit** — див. `tests/README.md`. Треба PHP 7.4+, Composer, WP-CLI і
окрема база MySQL `wp_swiper_tests`, лише для тестів (тести стирають її
таблиці). 302 тести, CI (`tests.yml`) ганяє їх на PHP 7.4 / 8.3 при push у
`main` і `art-effect` та на PR.

Вдома (OSPanel): PHP у PATH немає, MySQL на `localhost` не слухає.
- PHP: `C:/OSPanel/modules/PHP-8.3/PHP/php.exe` (і `PHP-7.4/PHP/php.exe`),
  з `-d display_startup_errors=0` (інакше сиплеться попередження про
  imagick).
- `WP_TESTS_DB_HOST=127.127.126.4` (у `hosts` це `mysql-8.4`).
- `WP_CORE_DIR` — копія ядра WordPress у тимчасовій теці: можна скопіювати
  ядро сайту `C:/OSPanel/home/Creativity.local` без `wp-content` і
  `wp-config.php`.
- WP-CLI не встановлено: користувач дозволив завантажувати офіційний
  `wp-cli.phar` (GitHub releases) у тимчасову теку й запускати через цей PHP.

**У браузері** — `tests/browser/` (Playwright 1.48.2, встановлений Chrome),
див. `tests/browser/README.md`. Лише локально, у CI не ходять (потрібен сайт).
`fixture.php` показує слайдер з налаштувань `cases.php` на фото з медіатеки,
нічого не зберігає, відкривається лише з 127.x. Перевіряє 26 випадків
(усі ефекти, стилі колажу й Art) на desktop і mobile: без помилок у консолі,
які файли підключено, де стоять фото (знімки в `snapshots/`), стрілки,
лайтбокс. Запуск: `cd tests/browser && npm install && npx playwright test`;
після навмисної зміни розкладки — `npx playwright test --update-snapshots`
і переглянути різницю знімків.
