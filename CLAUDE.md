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
- Реліз **v2.9.2** (2026-10-27, вдома, тег `v2.9.2`; `art-effect` злито в `main`, `.pot` оновлено):
  - `650521c`: CI (`tests.yml`) ганяє тести й при push у `art-effect`.
  - `.pot` перегенеровано під 2.9.1 (491 рядок, +69 нових, −15 прибраних).
  - Колаж винесено з `frontend.js`/`.css` в `collage.js`/`.css` (див. «Як
    влаштований код»). Сторінки зі слайдерами: frontend.js gzip 38.6 → 26.9
    КБ. Перевірено headless Chrome на `creativity.local/slider/` (Scattered):
    розкладка й знімок такі самі, як до змін; клік по фото відкриває
    лайтбокс (Swiper довантажується), помилок у консолі немає. Прев'ю в
    адмінці не перевіряли (потрібен вхід).
- Реліз **v2.10.0** (2026-10-28, вдома, тег `v2.10.0`; коміт `100847d` + реліз,
  `art-effect` злито в `main`, `.pot` оновлено). Art Style **Ring + text**
  (`ring-text`), за іконками користувача (`assets/images/art/ring-text.svg`,
  `grid-circle.svg`, почищені від Illustrator, `currentColor`):
  - те саме кільце (`art.js ring()`, у `STYLES` обидва), але без фото в
    центрі: `WP_Swiper_Art::has_middle_photo()`, `counts()` без +1, у JS
    перший шматок — уже кільця (`hasMiddlePhoto()`). Без Opening (у config
    `opening: 0`), без «підписи лише на центральному фото» і обміну «по
    центру» (у цих полів тепер `'styles' => array( 'ring' )`).
  - центр: галочка `art_center` («Content in the middle», за замовчуванням
    увімкнена; вимкнена — коло порожнє, поля центру сховані в `admin.js`);
    `art_center_image` (+ `_image_size` %), `art_center_text`
    (`editor`, колір теми, слова фарбуються в редакторі), `art_center_font_size`,
    `art_center_padding` (усі для кожного екрана), `art_center_bg`
    (`alpha_color`, за замовчуванням `rgba(255,255,255,0)` = без фону).
    Розмітка `center()` поруч зі сценою (не під тінню), `art.js
    centerContent()` ставить коло над центром і зменшує текст, поки не
    влізе (до 8px). Картинка над текстом, не стискається.
  - Image border Art (`art_border*`) перенесено з блоку «Art» у загальний
    блок «Look» (`WP_Swiper_Settings::blocks()`), одразу перед Image shadow:
    для Art тепер «Look» → Image border → Image shadow.
  - Неактивні плитки «coming soon»: `WP_Swiper_Art::soon()` (зараз порожній),
    ключ поля `'soon'`, `WP_Swiper_Admin::tiles()` малює
    `.wp-swiper-tiles__tile--soon` без `data-value`; таких немає в `styles()`,
    тож `sanitize()` їх не збереже. Майбутній стиль: іконка + рядок у `soon()`.
  - Art Style **Grid + circle** (`grid-circle`), як Grid колажу + коло з фото
    над центром (користувач відмовився від варіанта «квадрат / Fill width» з
    полем Shape — його прибрано):
    - `art_grid_count` (Photos in the grid, 2–36, за замовч. 9; фото кола —
      ще +1) і `art_grid_columns` (2–6, за замовч. 3), обидва для кожного
      екрана; рядів = ceil(фото / колонки), фото неповного останнього ряду
      стоять **по центру** ряду (рішення користувача).
    - Завжди на всю ширину колонки (`is_wide()`, клас `.wp-swiper-art--wide`,
      у config `wide: true`, `auto` усі false); Size, Size unit і Alignment
      тепер лише для ring / ring-text (`styles`).
    - Висота `art_grid_height` + `art_grid_height_unit` (`px` | `auto`, за
      замовч. `auto`, inline, для кожного екрана): `--wp-swiper-art-height*`
      = px або `auto`; в config `square` по пристроях — `art.js` тоді ставить
      сцені висоту з квадратних клітинок. px — клітинки тягнуться під висоту.
      У `admin.js` число сіріє при Auto (як Size).
    - Коло: розмір — той самий `art_ring_center` (тягнеться в прев'ю), центр
      сітки, від меншої сторони; не менше, ніж треба, щоб накрити клітинку з
      центром кола (скільки дозволяє висота). Ця клітинка ховається завжди
      (`gridCell()` → null), її фото чекає обміну.
    - `art.js gridCircle()` / `gridCell()`: клітинка = прямокутник мінус
      коло+Gap, точні перетини сторін з колом, кути (і стики з дугою)
      округлені; клітинки, від яких лишилось < `GRID_MIN_SHARE` (20%),
      сховані, видимі йдуть підряд після кола, `block.wpSwiperArtShown` —
      скільки шматків показано (обмін фото рахує по ньому, при зміні —
      `fill( true )`). `counts()` = фото + 1.
    - «Лише на центральному фото» й обмін «по центру» діють (`styles` ring +
      grid-circle), Opening — ні.
  - Перевірено: 280 тестів (PHP 7.4/8.3); сторінки з Ring + text і Grid + circle
    (3×9, 3×8 з неповним рядом, 4×10 з висотою px, рамка), згенеровані
    через WP-CLI з фото слайдера 140, у headless Chrome. Прев'ю в адмінці
    (перетягування краю центру, обмін фото) не перевіряли — потрібен вхід.

- Реліз **v2.11.0** (2026-10-28, тег `v2.11.0`; `art-effect` злито в `main`;
  `.pot` НЕ оновлено — WP-CLI тут не дозволили завантажити, оновити наступного
  разу) — три зміни нижче.
- **Scattered — перенесення фото на
  кінець рукава** в прев'ю. Під час перетягування (не центрального фото)
  колаж зменшується, щоб влізли пунктирні рамки (`.wp-swiper-collage__slot`)
  на кінцях усіх рукавів («a» і «o»; у порожній рукав — біля центру). Над
  рамкою колаж показує результат, після відпускання фото переїжджає зі своїм
  розміром, фото за ним у старому рукаві підтягуються.
  `collage.js scatterMove()` / `scatterMoves()` / `scatterMovedIndex()`, API
  `scatter.startMove()` / `showMove()` / `endMove()`; `preview-collage.js
  makeSlots()` / `showSlot()` / `store()`. Формат `collage_cuts` той самий.
  Перевірено headless Chrome (колаж головної, перебудований як Scattered):
  перенесення, підтягування рукава, Escape, обмін, центр без рамок; 280 тестів.
- **Caption → Font size для всіх
  слайдів** (`caption_size`, responsive, порожньо = 16px), CSS-змінна на
  блоці (`caption_sizes()`). Розмір слайду (вікно Edit) перекриває його:
  `own_font_size_vars()` тягне власний розмір слайду на менші екрани
  («30,,» = 30 скрізь; «,,12» — до телефона загальний). Старий загальний
  розмір (`legacy_font_sizes()`, до 2.8) `get()` переносить у нове поле;
  `admin.js` більше не копіює його в слайди (`data-legacy-caption-size`
  прибрано). У вікні слайду placeholder показує розмір, який діє.
  Description — як було. Перевірено: 282 тести, каскад у Chrome на 3 ширинах.
- **Undo / Redo в прев'ю** (Collage
  усіх стилів і Art): кнопки ↶ ↷ зліва від пристроїв
  (`.wp-swiper-preview__history`, сховані для інших ефектів), Ctrl+Z /
  Ctrl+Shift+Z / Ctrl+Y у фреймі (`e.code`, будь-яка розкладка). Історія
  окремо для кожного пристрою, до 50 кроків, до перезавантаження сторінки:
  `admin.js record()` перед кожним повідомленням з прев'ю (`collage-*`,
  `art-*`) і перед Shuffle / Reset запам'ятовує поля `collage_order`,
  `_mosaic`, `_cuts`, `_manual`, `art_order`, `art_ring_center` пристрою;
  повідомлення в межах 300 мс — один крок; крок без змін відкидається.
  Назад/вперед: поля → `restorePreview()` викликає у фреймі
  `wpSwiperRestoreCollage()` (`collage.js restore()`) /
  `wpSwiperRestoreArt()` зі значеннями з урахуванням успадкування; якщо
  ніхто не взяв — `updatePreview()`. Перевірено: відновлення колажу у фреймі
  в Chrome (після справжнього переносу, туди й назад); 282 тести. Самі
  кнопки в адмінці не перевіряли — потрібен вхід.
- Реліз **v2.12.0** (2026-10-28, тег `v2.12.0`; `.pot` знову НЕ оновлено): **Scattered — прибрати місце** (лише
  Scattered, рішення користувача; для інших стилів — коли попросить). Кнопка ×
  у куті кожного фото в прев'ю (не центрального, не нижче 2 фото), видно при
  наведенні на колаж (`.wp-swiper-collage__remove`, окремі елементи сітки —
  не в комірці, бо `swap()` чистить комірку). `collage.js scatterTake()` /
  `scatter.removable()` / `removePlace()`: місце виймається з рукава (решта
  як є), `collage_count` пристрою −1, фото — в кінець порядку (чекає в
  черзі, слайд лишається). Повідомлення `collage-count` + `collage-order` +
  `collage-scatter` = один крок Undo (`collage_count` у `HISTORY_FIELDS`,
  `restore()` бере `count`). Текст кнопки — `window.wpSwiperPreviewI18n` у
  фреймі. Перевірено в Chrome (клік, без лайтбокса, Undo назад); 282 тести.

- Реліз **v2.13.0** (2026-10-28, тег `v2.13.0`; `.pot` знову НЕ оновлено): **Art Style «SVG shape»** (`shape`,
  етап 1; трафарет — етап 2, ще не робили). Дизайнер малює форму, адмін
  завантажує SVG у полі `art_shape` (тип `svg_shape`):
  - Файл читає браузер (`admin.js`, FileReader, ≤ 1 МБ) і кладе в поле
    `{name, svg}`; у медіабібліотеку НЕ йде. Сервер (`WP_Swiper_Svg_Shape`,
    `includes/class-wp-swiper-svg-shape.php`) при Update прев'ю / збереженні
    лишає тільки геометрію: JSON `{v,name,w,h,skipped,shapes:[{id,d,box,area,
    rule,z}]}`, `d` — лише M/L/C/Z і числа 0–1 від viewBox. Сам файл ніде не
    виводиться. DOCTYPE з ENTITY — відмова.
  - Розбір: path (усі команди, дуги → криві), rect (rx/ry), circle, ellipse,
    polygon/polyline; transform (усі види) з батьками; класи з `<style>`
    (лише `.class`), атрибути, inline style. Без заливки / прихований /
    opacity 0 / text / image / use / line — пропускаються і рахуються в
    `skipped`. Один шар-група навколо всього розгортається; група всередині =
    одне вікно. Назва = `data-name` або `id` (Illustrator).
  - Порядок вікон (`ordered()`): `main` → числа `2`,`3`… → більші; `z` — порядок
    у файлі (пізніший зверху). Рішення користувача.
  - `art_shape_margins` (за замовч. вимкнено): обрізати до фігур
    (`frame( $data, true )`) чи все полотно.
  - Art: `shape()` / `counts()` = кількість вікон; config `shapes`, `ratio`,
    `swap.target` з `art_shape_target` (Controls, What changes: `any` —
    одне вікно навмання, за замовч., рішення користувача; `turn` — по черзі
    від головного; `advance` — усі разом, як слайдер); CSS
    `--wp-swiper-art-ratio`. Без файлу —
    повідомлення для редакторів, відвідувачам нічого. Gap і Rounded corners
    не для shape (`styles`), Size / Alignment / «лише на головному» — так.
  - Вікна край у край (серце з двох половин) давали світлу волосинку на
    стику при дробовому масштабі екрана (125%): кожен край згладжується
    окремо. `BLEED` = 0.75 px — кожне вікно (шлях і коробка) росте від свого
    центру, сусіди трохи перекриваються. Перевірено знімками при 1 / 1.25 /
    1.5: лінія (стовпчик 205 між 129 і 73) зникла.
  - `art.js shape()`: clip-path path() у px вікна, z-index; `advance( by )` —
    усі вікна беруть фото на `by` далі (`fadeTo()` винесено зі `swap()`);
    Клік по будь-якому вікну — як в інших стилях Art: посилання або лайтбокс
    (курсор zoom-in). Клік по меншому вікну «перенести в головне» був і
    прибраний на прохання користувача (дві поведінки й два курсори плутали).
    Size Auto враховує пропорції (`fit()`).
    Спроба підказувати браузеру `sizes` картинок (як `sizeImages()` колажу)
    була й відкочена на прохання користувача («щось зламав»); у тесті старий
    код і так брав достатній файл. Тепер фото Art — завжди `full` без
    `srcset` (`photo()`: фільтр `wp_calculate_image_srcset` → false лише на
    час виводу; рішення користувача, без налаштування). Скачок лишився, але
    невеликий; найпомітніший на фото природи з великою роздільною здатністю —
    найімовірніше Chrome грубо зменшує картинки під час анімації (поява,
    hover-zoom) і перемальовує якісно після неї. Вимір кадрів у headless
    Chrome не вдався (screencast дав 1 кадр).
  - Поле в адмінці: мініатюра вікон (`WP_Swiper_Admin::shape_thumb()`,
    головне — кольором), підсумок, Upload / Replace / Remove; іконка плитки
    `assets/images/art/shape.svg` (з `SVG-shape.svg` користувача).
  - Тести: `tests/test-svg-shape.php`, приклади дизайнера в `tests/data/`
    (ex-1 серце з двох половин, ex-2 фігура + коло, ex-3 три з перекриттям).
    293 тести (PHP 7.4/8.3). У Chrome: три приклади на сторінці зі справжніми
    art.js/art.css — форми, шари, клік по колу, автозсув. Завантаження файлу
    в адмінці не перевіряли — потрібен вхід.

## Що далі / відкладено

- **Наступний реліз** (на прохання; останній — 2.13.0, 2026-10-28):
  - злити `art-effect` у `main`;
  - підняти версію в `wp-swiper.php` (`Version:` і `WP_SWIPER_VERSION`) та в
    `readme.txt` (`Stable tag:`) і додати changelog;
  - перегенерувати `.pot`:
    `wp i18n make-pot . languages/wp-swiper.pot --exclude=vendor,tests`. Якщо тека
    називається не `wp-swiper-main`, поправ у `.pot` рядок
    `Report-Msgid-Bugs-To`;
  - тег `vX.Y.Z` → workflow Release (спершу тести).
- **Відкладено користувачем** («на потім», без нагадувань не починати):
  - Art, «нечітко → скачок» на великих фото: `art.js` сам вибирає для
    кожного вікна найменшу копію WordPress ≥ намальованої ширини (обрізання
    cover × DPR × 1.06 на hover) і ставить її `src` (сервер дає й список
    копій у data-атрибуті), щоб браузер майже не зменшував картинку;
  - Art SVG shape, етап 2: режим «трафарет» (одне фото крізь усі фігури);
    підпис у центрі bbox може виходити за край органічної форми;
  - редактор градієнта для фону підпису (типи Linear/Radial, точки
    кольорів). План: свій на Iris, зберігати структуру, а не CSS;
  - безпека: `span style` у підписах/описах пропускає будь-який CSS, який
    дозволяє WordPress. Обмежити до кольору й підкреслення;
  - оптимізація: ~~не вантажити Swiper для Collage/Art~~, ~~мінімізувати JS/CSS
    при релізі~~, ~~колаж в окремий `collage.js`/`.css`~~ (зроблено). Далі
    можна: слайдер+відео окремо від `frontend.js`; `fields()` з
    `class-wp-swiper-settings.php` в окремий файл (лише для читабельності);
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
- `assets/js/frontend.js`: Swiper, lightbox, відео, фон, `init()`.
- `assets/js/collage.js` + `assets/css/collage.css`: колаж (`collage()`:
  шаблони й Random mosaic через дерева розрізів `parseMosaic` /
  `serializeMosaic`, Scattered). Вантажаться лише там, де є колаж
  (`WP_Swiper_Render::enqueue_collage()`), після `frontend.js` / `.css`.
  Спільне з `frontend.js` бере з `window.wpSwiperShared` (device, fills,
  fillHeight, watchNeighbours, lightboxItem, openLightbox, posterFallbacks,
  LIGHTBOX_SHOWN); `init()` викликає `window.wpSwiperCollage`. Нова
  спільна функція для колажу = додати її в `wpSwiperShared`.
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
Станом на цей коміт: 280 тестів, усі зелені. CI (`tests.yml`) ганяє їх на
PHP 7.4 / 8.3 при push у `main` і `art-effect` та на PR.

Вдома (OSPanel): PHP у PATH немає — `C:/OSPanel/modules/PHP-8.3/PHP/php.exe
tests/vendor/bin/phpunit`; база — `WP_TESTS_DB_HOST=127.127.126.4`
(на `localhost` MySQL не слухає). WP-CLI вдома не встановлено: користувач
дозволив завантажувати офіційний `wp-cli.phar` (GitHub releases) у
тимчасову теку й запускати через цей PHP.
