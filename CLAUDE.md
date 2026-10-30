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
- Далі (2026-10-29): Use as background — не окрема сторінка меню, а останній
  блок Layout; у прев'ю без тексту «The settings as they are now…», з
  плашкою «Background mode» при фоні (`WP_Swiper_Preview::background_block()`);
  Image shadow і для Fade (Cover — тінь на `.swiper-wrapper`, щоб не
  блідла під час переходу; Contain — на фото). Slide з тінню: відступ під
  тінь показував сусідній слайд (місце під тінь > проміжку); тепер слайди
  цілком поза кадром згасають (`slider.js hideOutside()`, клас
  `wp-swiper-slide--outside`, перевірка щокадру, поки щось рухається).
- 2026-10-29: усі поля кольору однакові — **Pickr 1.9.1** (MIT,
  `assets/vendor/pickr/`, лише в адмінці, як у WPBakery). Замість
  `<input type="color">` і Iris: `WP_Swiper_Admin::color_pick()` (квадратик
  + прихований input), `admin.js` створює Pickr при першому кліку. Без
  прозорості для `color`, з нею для `alpha_color` і Slide info (рішення
  користувача, варіант А). Внизу Cancel (колір на момент відкриття) і Default
  (наша кнопка в `.pcr-interaction`); стрілку «за замовчуванням» прибрано.
  Палітра — та сама, що «Text color». Вікно вужче й квадратніше за Pickr
  (20em, палітра 11em, поле коду окремим рядком, `admin.css`).
  Overlay opacity (%) фону прибрано: прозорість — у самому Overlay color
  (`alpha_color`, за замовч. `rgba(0,0,0,0)` = немає);
  `merge_overlay_opacity()` у `get()` переносить старе, вивід —
  `WP_Swiper_Settings::background_overlay()`. Minimum block height (px) фону
  прибрано повністю (рішення користувача: висоту блоку задає конструктор; на
  сайті вдома жоден слайдер його не мав) — не повертати. Блоки сторінок
  налаштувань (`.wp-swiper-block`) — сірі картки (`#f2f2f2`, рамка 1px `#e4e4e4`, радіус 6px,
  padding 12px, відступ між ними 5px) замість лінії над заголовком.
  Прев'ю фону: блок на весь екран пристрою, без відступів (`100vh`, клас
  `wp-swiper-preview-page--background`; пропорції 3:2 / 3:4 / 9:16 прибрано) — лише прев'ю;
  приклад вмісту (заголовок, текст, кнопка) прибрано на прохання
  користувача, лишилась плашка «Background mode» (у правому верхньому куті —
  ліворуч кнопки Shapes / Photos Art, 2026-10-30).
  Look: перемикач **Rounded corners** (`border_rounded`), під ним Radius (px)
  (`border_radius`, за замовч. тепер 12), як Image border. Вимкнено —
  радіуса немає ніде (`apply_rounded()` у `render_slider()`, і лайтбокс, що
  бере радіус слайдера). Старі слайдери з радіусом > 0 — увімкнено
  (`merge_rounded()` у `get()`, і в `layouts`). Art має свій `art_radius`.
  Блоки (картки) і на Lightbox (Lightbox / Thumbnails / Descriptions),
  Caption (Text / Background), Description (Text / Placement and transition),
  Custom (HTML attributes); Video (Posters — картка в розмітці `render_settings_box()`, не поле схеми; Playback; Player).
  Lightbox: перемикач **Rounded corners** (`lightbox_border_rounded`), під ним
  Radius (px) (`lightbox_radius`, за замовч. 12). «As the slides» (порожньо =
  радіус слайдера / Art) прибрано на прохання користувача — радіус лайтбокса
  лише свій; вимкнено — 0 скрізь (`lightbox_image_radius()`).
  `merge_lightbox_rounded()` у `get()` переносить радіус, який лайтбокс
  реально мав. Thumbnail border radius лайтбокса ще бере радіус мініатюр.
  Description → Gap to the slider (`description_offset`) — для кожного екрана
  (`--wp-swiper-description-offset[-tablet|-mobile]`); старі слайдери — той
  самий відступ скрізь.
  Caption → Background opacity (%) прибрано: прозорість у Background color
  (`alpha_color`, за замовч. `rgba(0,0,0,0.65)`), він в одному ряду з
  Background style (група `caption_bg`). `merge_caption_bg_opacity()` у `get()`;
  вивід (і Art) — `WP_Swiper_Settings::caption_bg_colors()`.
  Cube → Shadow under the cube: Offset (px), Scale і Color в одному ряду (група
  `cube_shadow`); Color (`cube_shadow_color`, `alpha_color`, за замовч.
  `rgba(0,0,0,0.6)`) замість Shadow opacity (%). `merge_cube_shadow_opacity()`
  у `get()` (і в `layouts`); CSS фарбує `::before` тіні Swiper
  (`--wp-swiper-cube-shadow-color`), `opacity: 1`. Blur не додавали (у Swiper
  50px).
  Coverflow: Depth (px) і Stretch (px) — для кожного екрана (вони в px, на
  телефоні ті самі значення діють сильніше); Rotate і Side slides scale — одні
  на всі (рішення користувача). `config.coverflow.depth|stretch` =
  { desktop, tablet, mobile }, `slider.js coverflowAt()`: телефон — основа,
  планшет і десктоп — у breakpoints.
  Thumbnails → Look: першим Inactive thumbnail opacity (%), далі перемикачі
  **Rounded corners** (`thumbs_rounded`, під ним Radius (px), за замовч. 6) і
  **Frame** (`thumbs_border`, за замовч. увімкнено, під ним Width / Color / Active
  color в одному ряду), як Image border. Вимкнено — 0 (`apply_rounded()`; і
  мініатюри лайтбокса, що беруть радіус мініатюр). `merge_thumbs_switches()` у
  `get()` (і в `layouts`): увімкнено, де був радіус > 0 / рамка > 0. У
  `cases.php` випадок `thumbs` має `thumbs_border` (відсутній чекбокс = вимкнено,
  як у формі).
  Direction — не в Advanced, а першим у блоці Effect сторінки Layout, лише для
  Slide, Cube, Coverflow, Flip, Cards, Creative і Thumbnails (не з переходом
  Fade, `admin.js fixedDirection`). Як поле Layout зберігається окремо для
  кожного ефекту (`layouts`); старі слайдери: поточний ефект зберіг свій.
  **Image shadow — назовні** (рішення користувача): місце під тінь більше не
  забирається з фото (був padding `--wp-swiper-shadow-room`, через нього при
  Contain фото меншали і проміжки між ними росли). Тепер фото того самого
  розміру, що без тіні; тінь виходить за межі слайдера на сторінку. Swiper:
  `.swiper` `overflow: clip` + `overflow-clip-margin:
  var(--wp-swiper-shadow-reach)` (найбільший вихід тіні, у `@supports`),
  крім Cards. НЕ `overflow: visible` + `clip-path` — так слайди в черзі за
  краєм розширювали сторінку (горизонтальна прокрутка, перевірено:
  1280 → 3156px). Без підтримки (Safari): старий padding
  `--wp-swiper-shadow-room`, фото трохи менші. `hideOutside()` ховає слайди
  за краєм. Колаж і Art — без padding. Знімки тестів з тінню оновлено вдома
  (2026-10-29). Safari не перевіряли.
  **Прев'ю у пристроях** (усі три): `assets/images/preview/desktop.svg` (iMac),
  `tablet.svg`, `phone.svg` (малюнки користувача, прозорий екран), розміри в
  `WP_Swiper_Preview::DEVICES` (одиниці viewBox; екран має пропорції фрейму,
  тож `SIZES` підігнано під малюнки, рішення користувача: Desktop 1280×721
  (1.775, був 1280×800), Tablet 820×1101 (0.745, а не iPad Air 820×1180),
  Mobile 390×844), `data-devices` (`device_frames()`); `admin.js fitPreview()`
  ставить фрейм в екран (радіус кутів), малюнок поверх (`pointer-events:
  none`), масштабує разом. Тонка смуга прокрутки у фреймі до 1024px. Новий
  пристрій = малюнок + рядок у `DEVICES` (+ `SIZES` під пропорції екрана).
  `random.svg` з кореня видалено (не потрібен). Назви блоків
  (`data-block`) не повторюються між сторінками, крім старих `look`/`size`.
  Перевірено на тестовій сторінці в Chrome
  (стилі WP + admin.css + блок з admin.js); справжню адмінку — руками.

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
- **Art стилі**, порядок: Ring, Ring + text, Grid + circle, Grid + circle +
  text, Grid + square, Grid + square + text, SVG shape завжди останнім.
  Варіанти «+ text» — без фото в середині, з блоком Content in the middle
  (`has_middle_photo()`, `art.js hasMiddlePhoto()`). Квадрат — та сама сітка
  (`gridCircle()`), комірки ріже `squareNodes()`; розмір квадрата — те саме
  поле, що й кола (`art_ring_center`), тягнеться в прев'ю.
- **Art Height** — одне поле для всіх стилів, як у слайдерів: px / vh / % /
  Auto (`art_height`, `art_height_unit`). Кільце квадратне, SVG shape за
  пропорціями малюнка (ширина = висота × пропорції, не ширше колонки), сітка
  на всю ширину. %: блок Art — % висоти батька. Auto: вільна висота колонки
  (`art.js fit()`); нема — кільце/фігура на ширину колонки, сітка 500px.
  «Квадратних фото» в сітці більше немає (рішення користувача). Старі
  Size / висота сітки → `merge_art_height()` (сітка з квадратними фото — px
  для колонок 1200/768/390).
- **Art SVG shape:** порядок вікон `main` → назви-числа `2`, `3`… → більші;
  What changes за замовчуванням `any`; трафарет — четвертий варіант What
  changes (`one`), а не окреме поле. Для кожного екрана — лише файл; What
  changes, Appearance, затримка й швидкість — одні на всі екрани. Понад 100
  фігур — лишаються перші 100 за порядком у файлі.
- **Art Grid shapes** (2026-10-30, ідея користувача — «сітки фігур» як у
  Canva): сітка Columns × Rows (2–12, одна на всі пристрої — розтягується),
  клітинки зливаються в шматки, кожен шматок — вікно для фото. Зберігається
  опис сітки, а не контур (`art_cells`), контур будує `art.js gridShapes()`
  під реальний розмір: тому розтягується на будь-який блок і може бути фоном.
  Опуклі кути — радіус шматка (Radius або свій % від половини клітинки,
  100% = коло / «пігулка» в одній клітинці; 2×2 при 100% — круглий
  квадрат, не коло — так задумано, щоб % виглядали однаково); увігнуті —
  радіус шматка навпроти + Gap (рівний проміжок). Дірки (кільце) — самі.
  Стрілок і трикутників у конструкторі немає (для них — SVG shape). Вікна
  поводяться як у SVG shape (What changes, Appearance, `one` — трафарет;
  `has_windows()`). Розкладка за замовчуванням 4×4 — 8 шматків, два круглі
  (`default_cells()`). Прев'ю: кнопки Shapes / Photos над сценою
  (`preview-art.js`); у Shapes протягування зливає прямокутник у шматок
  першої клітинки, клік — панель шматка (Corners, As Radius, Split into
  cells, Empty); шматки, що перестали торкатися, розпадаються (`splitCells`).
  Свій радіус окремого кута — лише для опуклих (рішення користувача): точки
  на кутах вибраного шматка, клік — Corner / As the shape; увігнуті кути
  завжди автоматично (радіус кута сусіда навпроти + Gap). Кут зберігається
  за місцем на сітці, тож не залежить від номерів шматків; кути, що зникли
  після зміни шматка, забуваються (`preview-art.js send()`).
  Іконка `assets/images/art/grid-shapes.svg` — малюнок користувача.
- **Zoom on hover** (2026-10-30, `image_hover_zoom`): перемикач у Layout → Look
  (не в Lightbox — це вигляд фото, а не поведінка після кліку), без поля
  сили — фіксовано 106% (рішення користувача). Slide, Fade, Creative,
  Thumbnails, Collage, Art; не для Cube / Flip / Coverflow / Cards (3D,
  рішення користувача), не у фоні, не з Zoom the main image
  (`hover_zoom()`), лише `@media (hover: hover)`. Art мав збільшення завжди:
  старі слайдери Art — увімкнено (`merge_hover_zoom()`), для Art за
  замовчуванням увімкнено (`layouts()`), для решти — вимкнено.
- **Art як фон** (Use as background): лише стилі, що розтягуються — сітки
  (`is_wide()`: Grid + circle/square і Grid shapes); кільця й SVG shape — ні
  (свої пропорції). `is_background()` це перевіряє; поля фону мають
  `styles`. У фоні: без підписів, описів, посилань і лайтбокса, висота
  блоку; переносить у блок `art.js background()` (на сторінці з Art може не
  бути `frontend.js`), правила хоста продубльовано в `art.css`.
- **Collage Freeform:** фото не накладаються; Shuffle — нова випадкова
  розкладка, Reset — перша; Gap — «магніт».
- Кнопка × «прибрати місце» — лише Scattered і Freeform; для інших стилів —
  коли попросить.
- Телефон (і планшет) завжди наслідує більший екран: жодних `device_defaults`
  (окремих значень за замовчуванням для пристрою) — не додавати. До версії 7
  вони були у 8 полів (Style/кількість/колонки/висота ряду/відступ колажу,
  Active slide width, Thumbnails per view, ширина опису в лайтбоксі);
  `_version` 7: збережене значення телефона, що дорівнює старому
  (`OLD_MOBILE_DEFAULTS`), стає Inherit (`inherit_old_mobile_defaults()`).
- Undo / Redo (кнопки біля пристроїв прев'ю, Ctrl+Z / Ctrl+Shift+Z / Ctrl+Y
  поза текстовими полями) — для всіх ефектів: усі налаштування слайдера +
  правки руками в прев'ю Collage/Art. Одна історія на сторінку (не окремо
  для пристроїв), до 50 кроків, до перезавантаження. Список слайдів і тексти
  слайдів — ні (можливо, другим кроком). Крок пам'ятає сторінку й пристрій:
  відкат спершу показує їх, потім підсвічує змінені поля. Прев'ю при відкаті
  НЕ перезавантажувати (рішення користувача): лише Update «застарілий»;
  правки руками в прев'ю Collage/Art повертаються там одразу
  (`wpSwiperRestoreCollage/Art`). Знімок — значення полів + стани
  блокувань (`lockStates` у `lockable()`/`lockableValue()`) + `layouts` інших
  ефектів. Новий lock = через ці дві функції, інакше відкат його не поверне.

## Відомі обмеження

- Freeform: зміна Gap після ручної розстановки не пересуває збережені місця;
  Shuffle або Reset дають нову розкладку з новим Gap.
- Art: текст у середині кола (Ring + text) і в розриві (Ring), що не влазить,
  обрізається на слові з «…» (`art.js fitText()`), шрифт не зменшується
  (рішення користувача: розмір і стилі теми лишаються). Прев'ю без стилів
  теми, тож там тексту влазить інакше, ніж на сайті. Слово, ширше за коло
  (великий заголовок теми), переноситься по літерах.
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
  `self::` там — цей клас), Art додає свої з `WP_Swiper_Art::fields()`
  (так само `require` файлу `includes/art-fields.php`).
- `includes/class-wp-swiper-settings.php` — `get()`, `defaults()`,
  `pages()` / `blocks()` (меню й блоки адмінки), перевірки ефектів
  (`is_*`), висоти, `layouts`, усі константи. Решта методів класу — у
  трейтах (`use` у класі; виклики як і раніше `WP_Swiper_Settings::…`,
  `self::` у трейті — цей клас; констант у трейтах немає, PHP 7.4):
  - `trait-wp-swiper-settings-migrations.php` — `merge_*()` і `legacy_*()`
    (перенесення старих збережень), `inherit_old_mobile_defaults()`;
  - `trait-wp-swiper-settings-sanitize.php` — `sanitize()`,
    `sanitize_fields()` і всі `sanitize_*()`;
  - `trait-wp-swiper-settings-text.php` — підписи, описи, шрифти, кольори
    фону підпису й Overlay (CSS-змінні, теги, розмітка, перехід описів);
  - `trait-wp-swiper-settings-media.php` — слайди, відео, embed, постери;
  - `sanitize()` відкидає невідомі ключі. Тому для прибраних полів є
    `legacy_*()`, а `admin.js` при завантаженні переносить старі значення в
    нові місця;
  - `art_cells` (Art Grid shapes, тип `cells`, `WP_Swiper_Art::parse_cells()`):
    `"4x4:0,0,1,2,…;1=100,7=50"` — колонки x рядки, номер шматка кожної
    клітинки по рядках (-1 — порожня), далі власні радіуси шматків у %,
    далі (`;;` без радіусів шматків) свої радіуси опуклих кутів `x.y=%` —
    місце кута на сітці клітинок і проміжків (0 … 2×Columns−1).
    Зберігається пронумерованим з 0 (`number_cells()`); інші Columns / Rows —
    `cells()` бере збережене, де дістає, нові клітинки — окремі шматки. Той
    самий розбір у `art.js` (`window.wpSwiperArtCells` для прев'ю);
  - `collage_cuts` зберігає розміри колажу для кожного пристрою:
    шаблони й Random mosaic — дерево розрізів
    (`"feature-left:V0.5[0,H0.5[1,2]]"`), Scattered —
    `"scatter:1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8"`, Freeform —
    `"freeform:2.3077;x,y,w,h;…"` (в одиницях висоти кадру, ≤ 100 місць).
- Вікно Slide info — вкладки Caption / Description / Link (Link немає для
  відео-слайда), при відкритті завжди Caption, крапка на вкладці = там щось
  є; висота вікна стала (за Description). Редактор опису стартує при
  першому показі його вкладки (TinyMCE, запущений у прихованому блоці,
  малюється криво).
- `includes/class-wp-swiper-admin.php`: адмінка, вікно Slide info, рядки
  слайдів (значення слайду в прихованих `wp_swiper_slides[...]`),
  `posted_slides()`, плитки `tiles()`. Іконки інлайн через `svg_icon()`;
  іконки стилів — `assets/images/{collage,art}/{style}.svg` (`currentColor`).
- `includes/class-wp-swiper-render.php`: розмітка й `data-*` конфіг, які файли
  підключити:
  - `enqueue( $swiper, $thumbs )`: `frontend.js`/`.css` завжди; для
    слайдерів (`needs_swiper()`, не Collage/Art) ще Swiper, `slider.js`
    (deps `swiper`, `wp-swiper`) і `slider.css` (dep `wp-swiper`), для
    ефекту Thumbnails ще `thumbs.css` (dep `wp-swiper-slider`).
    `enqueue_collage()` — `collage.js`/`.css`;
  - CSS сайту, як і JS: `frontend.css` — спільне для слайдерів, колажу й Art
    (база, відео, підписи, описи — їх показує й Art, лайтбокс, фон, рамка й
    тінь фото); `slider.css` — лише слайдери (marquee, Expand, ефекти,
    пагінація, стрілки, zoom); `thumbs.css` — смуга Thumbnails. Нове правило
    лише для слайдерів — у `slider.css`. Колаж отримує класи кореня з тієї
    самої `markup()`, але `render_slider()` знімає йому пагінацію, стрілки,
    Direction, тож правила `slider.css` йому не потрібні. Поділ перевірено
    (2026-10-30): `getComputedStyle` усіх елементів 34 випадків до і після —
    без змін, крім невживаних у колажі `--swiper-pagination-bullet-*`;
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
  `wp_print_scripts()`; новий скрипт сайту — додати й туди). Без слайдів —
  `empty_screen()`: `assets/images/no-image.svg` (малюнок користувача) по
  центру екрана, 160px (на телефоні 32vw), замість напису «no slides yet».
  Поворот Tablet/Mobile (кнопка `rotate.svg` між «Preview» і Developer mode,
  завжди видна — для Desktop неактивна, щоб панель не стрибала; рішення
  користувача — варіант «показувати як є»): рамка міняє сторони (844×390,
  1101×820), малюнок пристрою повертається на 90° за годинниковою, плавно
  (`admin.js fitPreview()`, анімація — FLIP на `.wp-swiper-preview__stage`).
  Плагін вибирає пристрій за шириною, тож повернутий телефон бере
  налаштування Tablet, планшет — Desktop; про це підказка
  `turned_notes()` (`device_at()` = брейкпоінти 767/1024). Брейкпоінти НЕ
  міняли. Поворот пам'ятається для кожного пристрою (`localStorage`
  `wpSwiperTurned`). Смужка «додому» з `tablet.svg` при повороті стає збоку
  (частина малюнка). Developer mode схований (місце лишається, `visibility`,
  щоб панель не переносилась інакше) для Collage, Art і слайдера без слайдів.
  Скрипти прев'ю:
  - `preview-collage.js`: обмін фото, лінії-ручки, Scattered, Freeform;
  - `preview-art.js`;
  - `preview-dev.js`: Developer mode.

  Вони шлють `postMessage`, `admin.js` записує значення в приховані поля.

## Тести

**PHPUnit** — див. `tests/README.md`. Треба PHP 7.4+, Composer, WP-CLI і
окрема база MySQL `wp_swiper_tests`, лише для тестів (тести стирають її
таблиці). 349 тестів, CI (`tests.yml`) ганяє їх на PHP 7.4 / 8.3 при push у
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

На роботі (OSPanel, є `_backups` поруч із сайтом): PHP —
`C:/OSPanel/modules/PHP-8.3/php.exe` (і `PHP-7.4/php.exe`, без теки `PHP`),
`WP_TESTS_DB_HOST=127.0.1.13` (`mysql-8.0`), Python немає (скрипти — на PHP).
WP-CLI для тестів не потрібен: досить `WP_CORE_DIR` і `tests/vendor`.

**У браузері** — `tests/browser/` (Playwright 1.48.2, встановлений Chrome),
див. `tests/browser/README.md`. Лише локально, у CI не ходять (потрібен сайт).
Знімки записані на роботі, з її медіатекою. Вдома (2026-10-29) у медіатеці
немає відео, тож `video` не збігається зі знімком (156 інших проходять);
знімки вдома не оновлювати. Усі `art-grid-*` записано вдома (2026-10-29),
на роботі вони теж проходять (2026-10-30). На роботі (2026-10-30)
`video-layout` теж не збігається: у знімку 4 відео, в медіатеці 3 — так і
до поділу CSS; `video-layout.txt` не перезаписувати, доки не з'ясується,
котра медіатека правильна. Видаляючи рядки за номерами (sed), звіряй
`git diff` — так одного разу пропав селектор `.wp-swiper.wp-swiper--background`.
`fixture.php` показує слайдер з налаштувань `cases.php` на фото з медіатеки,
нічого не зберігає, відкривається лише з 127.x. Перевіряє 37 випадків
(усі ефекти, стилі колажу й Art) на desktop і mobile: без помилок у консолі,
які файли підключено, де стоять фото (знімки в `snapshots/`), стрілки,
лайтбокс. Запуск: `cd tests/browser && npm install && npx playwright test`;
після навмисної зміни розкладки — `npx playwright test --update-snapshots`
і переглянути різницю знімків.
