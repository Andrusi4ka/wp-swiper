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
  `_backups` поруч із сайтом, вдома — `git stash push -u -m "backup/before-…"`
  і одразу `git stash apply` (не гілки: їх назбиралось забагато; 2026-11-03
  усі гілки, крім `main`, видалено на прохання користувача). Після коміту
  ці stash не потрібні — `git stash clear`.
- На кожну нову функцію додавай тести в `tests/`. Перед комітом прожени
  весь PHPUnit, а якщо змінено фронтенд — і тести в браузері.
- Перевіряй зміни в браузері, якщо є як: адмінка, прев'ю, сайт.
- Комітить і пушить лише на прохання. Робоча гілка — `main` (одна гілка,
  рішення користувача 2026-11-03; `art-effect` і `text-mask` злиті й видалені).
  Реліз робиться тільки на прохання.
- Користувач теж пушить, з роботи й з дому. Перед роботою роби `git fetch`
  і, якщо гілка відстає, `git pull --ff-only`.
- **Не оновлювати WP Swiper через WordPress на сайті, де лежить робоча
  копія.** 2026-11-04 вдома оновлення з релізу v3.3.0 стерло теку
  `wp-swiper` (лишилась лише `.git/objects`: пакети git у Windows «лише
  читання») і поставило `wp-swiper-main`. Код був на GitHub, тож склоновано
  заново; залишок — `C:/OSPanel/home/_backups/wp-swiper-git-leftover-2026-11-04`
  (там лише старі stash). Активною має бути `wp-swiper/wp-swiper.php` —
  браузерні тести беруть саме активну копію.

## Стан

- Останній реліз **v3.4.0** (2026-11-05): Content in a window для Ring і
  сіток з колом / квадратом замість стилів «+ text» (старі переносяться),
  перетягування кутів Grid shapes, Shadow color для Effect shadows,
  плитки стилів Creative, темний екран прев'ю, ширше поле коду редакторів;
  `admin.js` розділено на частини, тести адмінки; `.pot` оновлено (WP-CLI
  2.11.0, phar у тимчасовій теці, SHA-512 звірено).
- Попередній — **v3.3.0** (2026-11-04): Collage Rotation (за крайніми
  точками фото), Content in a place (колаж) і Content in a window (Art SVG
  shape) зі шорткодами, тінь у фоні для Collage / Art, Developer mode для
  колажу й SVG shape, картка «What … can do»; `.pot` оновлено (WP-CLI
  2.11.0, phar у тимчасовій теці, SHA-512 звірено). Попередній — v3.2.1
  (2026-11-04): WP Swiper лише для адміністраторів. Ще раніше — v3.2.0 (2026-11-03): ефект Text mask, Gradient для
  всіх ефектів, автооновлення прев'ю на Effect / Style, виправлення з
  `8eb8a1b` (слеші при збереженні, слухачі плеєрів); `.pot` оновлено
  (WP-CLI 2.11.0, phar у тимчасовій теці, SHA-512 звірено).
- Після v3.0.0 в `art-effect` (2026-10-28): тести в браузері
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
  **Creative Style — плитки** (2026-11-05, рішення користувача): як Style
  колажу й Art, іконки `assets/images/creative/{style}.svg` (малюнки
  користувача, переписані на `currentColor` без `<style>` Illustrator;
  `creative-slide-apart.svg` → `apart.svg`), першим у блоці Effect — над
  Direction; інший стиль з плиток одразу оновлює прев'ю (як усі `tiles`).
  Direction — не в Advanced, а в блоці Effect сторінки Layout (першим, крім
  Creative — після його Style), лише для
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
  (2026-10-29). У Safari перевірив користувач (2026-11-05, v3.4.0): працює коректно.
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

1. Робота вже в `main` (окремої гілки немає).
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

- **Layout однаковий для всіх ефектів** (2026-11-05): головний блок ефекту
  (Effect / Arrangement / Art чи Text mask) → **Size** → власні блоки
  ефекту (Content…, Slides, Letters, Strips) → Look → Zoom → Background.
  У Size спершу Height (`height` / `art_height`), далі Gap
  (`space_between` / `collage_gap` / `art_gap` / `text_mask_gap`, усі
  звуться «Gap (px)»), далі Alignment Art, потім Image fit і пропорції
  слайдерів. Порядок задає кінець `WP_Swiper_Settings::blocks()`; нове поле
  розміру — у блок `size`. Тест `test_every_effect_has_its_size_in_one_place`
  (допоміжна `layout_for()` у `class-wp-swiper-testcase.php`).

- **Лише адміністратори** (2026-11-04, v3.2.1): меню, слайдери (додати,
  редагувати, копіювати, видалити), прев'ю, What's new, елемент WPBakery і
  повідомлення на сторінках («slider not found», фон без класу, SVG shape
  без файлу) — лише з `WP_Swiper_Settings::CAPABILITY` (`manage_options`).
  Усі `cap` типу запису = вона (`capabilities` + `map_meta_cap` у
  `register_post_type`). Інші ролі WP Swiper в адмінці не бачать узагалі;
  `WP_Swiper_WPBakery::map()` для них не реєструє елемент (WPBakery лишає
  наш обробник шорткоду, тож сторінки показують слайдери як і раніше).
  Фільтра для іншої ролі немає — додати, лише якщо попросить. Нова
  перевірка прав = `CAPABILITY`, не `edit_posts`. Тести:
  `tests/test-capability.php`, заглушки WPBakery — `tests/stubs/`.
- Сторінку **WP Swiper → Settings** (власна палітра) прибрано — не
  повертати. Палітра «Text color» = кольори теми + чорний/білий/сірі
  (`WP_Swiper_Palette`); `uninstall.php` досі видаляє старий
  `wp_swiper_text_colors`.
- **Art: розмір фото** (2026-11-05, рішення користувача — було «завжди
  `full`»): без `srcset` / `sizes` (ту спробу відкочено, «щось зламав»), а
  копії WordPress у `data-wp-swiper-copies` (`[ ширина, висота, URL ]` від
  вужчої, лише тих самих пропорцій ±2% — обрізані мініатюри ні;
  `WP_Swiper_Art::copies()`); SVG без копій — свій файл. `art.js
  sizeImage()`: найменша копія ≥ ширини, якою фото намальоване (cover над
  рамкою `<img>`, × DPR до 3, × 1.06 з Zoom on hover); ніколи меншу за вже
  показану (без другого завантаження при звуженні). Клон з шаблону — без
  `src` до виміру (`hold()`), тож повний розмір не качається; невидиме
  вікно — після `draw()` (`sizePhotos()`). `preload()` — копія для
  головного вікна. Лайтбокс — як і був, `full`. Тест —
  `tests/browser/art-copies.spec.js`; на телефоні в 3–7 разів менше даних
  (Grid + circle 370 КБ замість 1,2 МБ). Клік по
  меншому вікну «перенести в головне» прибрано: клік — посилання або
  лайтбокс, як в інших стилях.
- **Art Grid + circle:** фото неповного останнього ряду — по центру ряду.
  Варіант «квадрат / Fill width» з полем Shape відхилено.
- **Art стилі**, порядок: Ring, Grid + circle, Grid + square, Grid shapes,
  SVG shape завжди останнім. Стилі «+ text» (Ring + text, Grid + circle +
  text, Grid + square + text) прибрано 2026-11-05 на прохання користувача:
  замість них Content in a window для Ring і сіток з колом / квадратом (див.
  нижче). Квадрат — та сама сітка
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
  Точки можна тягти (2026-11-05, ідея користувача): всередину по діагоналі
  радіус більшає, назовні — меншає, 1px руху = 1px радіуса (точка на дузі
  відстає від миші — свідомо, інакше 0–100% ≈ 20px). Червона точка (вибрана
  кліком, лише одна) — тягне лише свій кут; будь-яка інша — увесь шматок
  (червона знімається; кути зі своїм радіусом не міняються). Клік без руху
  — вибрати / зняти. Під час руху біля точки «NN%»; Escape — розкладка як
  була. `preview-art.js turnMove()`, напрям і радіус точки дає `art.js`
  (`dx`, `dy`, `r`, `half` у `corners` контуру). Тест —
  `tests/browser/art-corners.spec.js` (підключає `preview-art.js` до
  `fixture.php`). Точку під кнопками Shapes / Photos не взяти — як і раніше.
  Іконка `assets/images/art/grid-shapes.svg` — малюнок користувача.
- **Zoom on hover** (2026-10-30, `image_hover_zoom`): перемикач у Layout → Look
  (не в Lightbox — це вигляд фото, а не поведінка після кліку), без поля
  сили — фіксовано 106% (рішення користувача). Slide, Fade, Creative,
  Thumbnails, Collage, Art; не для Cube / Flip / Coverflow / Cards (3D,
  рішення користувача), не у фоні, не з Zoom the main image
  (`hover_zoom()`), лише `@media (hover: hover)`. Art мав збільшення завжди:
  старі слайдери Art — увімкнено (`merge_hover_zoom()`), для Art за
  замовчуванням увімкнено (`layouts()`), для решти — вимкнено.
- **Art як фон** (Use as background): будь-який стиль. Сітки (`is_wide()`)
  розтягуються на блок; кільця й SVG shape (свої пропорції) — Background
  size (Contain / Cover) і Background position (9 положень, без «None»,
  за замовч. Center center), обидва для кожного пристрою (рішення
  користувача), `art_bg_size` / `art_bg_position` у блоці Background.
  `background_fit()` дає змінні, `art.css` ставить сцену через `cqw/cqh`
  блоку (`container-type: size`). У фоні: без підписів, описів, посилань і
  лайтбокса, висота блоку; переносить у блок `art.js background()` (на
  сторінці з Art може не бути `frontend.js`), правила хоста продубльовано в
  `art.css`.
- **Text mask** (гілка `text-mask`, 2026-11-03, ідея користувача — постер
  «LAHORE»): окремий пункт у списку ефектів (`text-mask`), але малює його
  Art як стиль `text` (`WP_Swiper_Art::style_of()`, `is_art()`, `EFFECTS`).
  Layout — три картки (рішення користувача): Text mask (блок `art`: Strips
  go, Word, Photos), Letters (`text_letters`: шрифт, Letter size,
  Stretch; Height і Gap — у блоці Size, як в усіх), Strips (`text_strips`: ширина, Strips into
  letters, довжини). У `blocks()` вони після `art_middle`/`art_opening`
  (ті лише Art, тож на екрані одразу за першою). Кожна літера + смуга під нею — одне вікно. Photos (`text_mask_photos`):
  одне фото крізь усі літери (за замовч.; `art_shape_target` примусово
  `one`) або фото в кожній літері (порядок — `art_order`, перетягування в
  прев'ю, як в Art; What changes без «one»: його значення → `any`; поле
  видно лише з Photo swapping). Без «паперу»/фону (рішення
  користувача); тінь — з блоку Image shadow, без окремої «глибини» (старе
  поле `text_mask_depth` першої спроби 2026-11-02 відкидається). Шрифт —
  заголовки теми / Serif / Sans / By name. Плагін сам шрифти НЕ вантажить
  (рішення користувача — варіант 2; Google Fonts з серверів Google — GDPR):
  By name (`text_mask_font_name`, тип `font`, `WP_Swiper_Fonts::sanitize()`
  — лише літери, цифри, пробіли, коми, лапки) бере шрифт, який сторінка
  вже має. Поле з підказками — свій список, не `<datalist>` (той вузький,
  обрізає підписи, не стилізується; рішення користувача): `font-picker.js`
  (окремий файл, не в `admin.js`), групи з заголовками, назва своїм
  шрифтом, фільтр під час набору, стрілки / Enter / Escape; дані —
  `data-fonts` з `WP_Swiper_Fonts::suggestions()` (`value`, `group`, `label`):
  шрифти WoodMart, якщо є його опція `xts-woodmart-options` (без прив'язки
  до теми: немає опції — немає пунктів; `var(--wd-…)` і «System font stack»
  пропускаються; усі правила кожного списку, зокрема Advanced typography,
  і Custom fonts — `multi_custom_fonts`, `font-name`), шрифти WordPress
  (`wp_get_global_settings`), системні. Лише ті, що WoodMart реально
  вантажить — повного каталогу WoodMart (~1500 Google-шрифтів) у списку
  немає навмисно. Інший шрифт: WoodMart → Typography → Advanced typography
  (правило з цим шрифтом) + Local Google fonts (GDPR) — пояснено в підказці
  поля. Локальне завантаження Google-шрифтів самим плагіном (варіант 3) —
  не робили. Слово одне на всі
  пристрої, розмір літер — сам (ширина) з межею Letter height. Смуга
  починається там, де кінчається літера (захід 2px, `STRIP_JOIN`), літера
  ціла — не заходити смугою в літеру (рішення користувача) за
  замовчуванням; вручну — Strips into letters (%) (`text_mask_join`,
  2026-11-03, 0–30, за замовч. 0, одне на всі пристрої, після Strip width):
  частка глибини кожної літери (Down — її висоти, Right / Left — ширини),
  низ літери зливається зі смугою (`art.js joinInto()`); межі вікон не
  міняються. Смуги: Rounded corners (`text_mask_rounded`, вимкн.), під ним
  Radius (px) для кожного пристрою (`text_mask_radius`, 12) і Corners
  (`text_mask_corners`: `end` — вільний кінець, за замовч.; `both` — і біля
  літери), як радіуси інших ефектів (рішення користувача: px, перемикач),
  у блоці Look (після Art, перенесено на прохання користувача). Радіус ≤ половини товщини смуги і ≤ її довжини
  (½ для both); `art.js stripShape()` — `rect` без радіуса, інакше `path`
  (тести беруть смугу як `rect, path` через `getBBox()`). Gradient — спільний
  для всіх ефектів, див. «Gradient» нижче. Strip width
  (`text_mask_strip_width`): за замовч. Same for all — колонка на літеру,
  усі однакові, рівно Gap між ними, літера по центру колонки, найширша
  літера задає розмір (може виступати на ¼ Gap з боку), пробіл — пів
  колонки; As the letter — смуга на всю ширину своєї літери (2026-11-03, рішення
  користувача, варіант А; було 80%), тож між смугами, як і між літерами,
  рівно Gap (пробіл — ширше). Ширина літери — рамка чорнила (найширше
  місце), тож під T, Y, V смуга ширша за низ літери; смугу «під низом
  літери» відхилено (проміжки знову різні) — лишаємо як є. Strips go
  (`text_mask_direction`, іконки-плитки першими в блоці Text mask —
  `assets/images/text-mask/{down,right,left}.svg`, малюнки користувача,
  2026-11-03): Down (за замовч.) / Right / Left — варіант А
  користувача: літери прямо, одна під одною (читаються згори), біля лівого
  (правого) краю, смуги вбік; «колонки» стають рядками. Right / Left
  (рішення користувача 2026-11-03): слово займає Height блоку (px / vh / % /
  висоту батька), Letter size немає (поле сховане, лише межа 70% ширини);
  Same for all — найвища літера рівно заввишки зі смугою (без виступу ¼ Gap,
  як у Down), тож між літерами Gap, як між смугами; Gap забирає місце в
  літер. Letter size має одиницю (`text_mask_size_unit`, для
  кожного пристрою, як Height): Auto (за замовч.) — слово на всю ширину,
  літери не більше `TEXT_AUTO` = 70% (місце смугам); % — як було. Stretch
  letters (`text_mask_stretch`, вимкн.) — літери ширшими вздовж слова (SVG
  `transform scale`, висота та сама), щоб заповнити свою колонку (As the
  letter — слово ширину), не більше ніж ×2 (`TEXT_STRETCH`). Окремого
  letter-spacing немає навмисно — це Gap (рішення користувача). Старі
  слайдери гілки без одиниці стали Auto (гілка не випущена — свідомо).
  **Смуги вимикаються** (2026-11-06, рішення користувача): перемикач
  `text_mask_strips_on` («Strips», за замовч. увімкнено — старі слайдери
  як були) першим у блоці Strips; поле довжин тепер «Lengths». Вимкнено
  (`text_config()` → `bare`, `art.js textLayout()`): лише літери, Letter
  size Auto — 100% блоку (не `TEXT_AUTO` 70%; % — як задано), відстань
  як у слові (як «As the letter», Gap між літерами); де стоїть — Text
  position і Alignment (нижче); поля смуг і їхні Rounded corners сховані
  (`admin.js`), значення лишаються.
  **Text position** (2026-11-06, рішення користувача; було «Strips go»):
  плитки названо за місцем слова, як на іконці — значення ті самі: `down`
  = Top, `up` = **Bottom** (нове: рядок унизу, смуги вгору, заокруглений
  верхній кінець; `art.js textLayout()` `lift` / `roomUp`, `stripShape()`
  far `[1,1,0,0]`; іконка `assets/images/text-mask/up.svg` — малюнок
  користувача), `right` = Left (літери ліворуч, смуги вправо), `left` =
  Right. Без смуг слово там, де плитка (Left — ліворуч; учорашнє «навпаки»
  скасовано — користувач уточнив). **Letter alignment** (2026-11-06,
  рішення користувача; спершу помилково зробив вирівнювання слова в
  блоці — прибрано): літери одна відносно одної, зі смугами й без, для
  кожного пристрою. `text_mask_align_v` для рядка (Top / Center / Bottom
  = базова лінія, за замовч. — як завжди), `text_mask_align_h` для
  стовпчика (Auto = край, біля якого слово; Left / Center / Right — у
  колонці завширшки найширшої літери, Center — на центральній лінії),
  у блоці Size. Кожна смуга починається там, де її літера закінчується.
  `text_align()` → `text.align` start / center / end; `art.js
  textLayout()`: `top` / `lx`, `columnX`. Тести: `test-text-mask`,
  `text-mask.spec.js` (`art-text-mask-up`, `-bare*`, `-right-center`),
  `admin.spec.js`. У `cases.php` усім старим Text mask додано
  `text_mask_strips_on` (відсутній чекбокс = вимкнено); без смуг —
  `art-text-mask-bare*`.
  Повернуте на 90° слово (варіант Б) не робили — лише якщо попросить. Розкладка — `art.js textLayout()` (усі напрямки),
  малювання — `textMask()`. Підписів
  немає (літери їх ріжуть), описи є. Фон (Use as background) — ще ні. Технічно: `clip-path: url(#…)` на вбудований SVG `<clipPath>` з
  `<text>` + `<rect>` (`art.js textMask()`), бо `path()` тексту не вміє;
  літери міряє canvas (`measureText`, межі чорнила), шрифт інлайн-стилем
  (CSS теми не перебиває). У Safari перевірив користувач (2026-11-05, v3.4.0): працює коректно.
- **Gradient** (2026-11-03, спершу лише Text mask, потім на прохання
  користувача — усі ефекти, `GRADIENT_EFFECTS`): `image_gradient_on` +
  `image_gradient`, у Look після Image border, перед Zoom on hover. Рішення
  користувача: поверх фото, редактор як у WoodMart (без поля Position, Delete
  червоним). Слайдери й колаж — на кожне фото (`.wp-swiper--gradient`,
  `.wp-swiper__media::before`, z-index 1 — під підписом і рамкою; не на
  відео-слайдах); Art і Text mask — один на всю сцену (`::after` вікна,
  `art.js gradientPlaces()` → `--wp-swiper-art-gx/gy/gw/gh`), проміжки
  порожні. Вивід — `WP_Swiper_Settings::gradient()`. Тип поля `gradient`,
  зберігається опис, а не CSS — `linear;left;rgba(…)@0;#00f@100` (тип
  linear/circle/ellipse; де починається left/right/top/bottom, як у WoodMart:
  left = `to right`; або центр «x y»; 2–10 точок),
  `WP_Swiper_Art::parse_gradient()` / `gradient_value()` / `gradient_css()`.
  Редактор — `gradient_field()` + `admin.js` (смуга, точки: клік додає,
  тягнути / стрілки, Delete; колір — той самий Pickr: свотч без input шле
  подію `wpSwiperColor`; `syncGradients()` після Undo). Старі
  `text_mask_gradient*` → `merge_text_mask_gradient()`. gradX з WoodMart не
  підключаємо (тема — не залежність). Не для лайтбокса й мініатюр.
- **Collage Freeform:** фото не накладаються; Shuffle — нова випадкова
  розкладка, Reset — перша; Gap — «магніт».
- **Collage Rotation** (2026-11-03, ідея користувача): `collage_rotate`
  (−45…45°, для кожного пристрою — рішення користувача) і
  `collage_rotate_fit` (`fill` за замовч. — краї обрізані / `fit` — весь,
  кути порожні, теж для кожного пристрою), у блоці Arrangement після Gap.
  Grid, Random mosaic і шаблони (розкладені у висоті), а також Scattered і
  Freeform (на прохання користувача: повертаються на місці, без Fill / Fit —
  масштаб так, щоб повернута рамка композиції вміщалась у блок,
  `scatterFit()` / `freeFit()` з кутом; у прев'ю зсув миші Freeform —
  `gridDelta()`); не Justified / Masonry (висота від фото). `collage.js orient()`
  (у `place()`, перед CSS-сіткою в `build()`, у `refit()`): міряє сітку
  неповернутою, розкладає її в більшій (Fill: `w·cos+h·sin` × `w·sin+h·cos`)
  чи меншій (Fit) рамці, тож Gap і плитки справжні, не масштабовані; блок
  тримає свою висоту (`el.style.height`). Фото — назад рівно, розміром
  `--wp-swiper-collage-uw/uh` (`sizeImages()`), плитка обрізає; рамка — на
  плитці (`cell::after`), підпис — по центру, не ширше `--wp-swiper-collage-cw`,
  тінь — зсув повернуто назад (падає вниз). Прев'ю: ручки всередині сітки
  повертаються з нею, миша → координати сітки через
  `preview-collage.js gridPoint()` (`grid.wpAngle`) — перетягування ліній
  працює під кутом (замість «випрямляти, поки тягнеш» — так зручніше).
  Scattered і Freeform під кутом (2026-11-04, рішення користувача) —
  за крайніми точками фото: вміщаються повернуті кути кожного фото
  (`turnedBounds()`, `turnedOrigin()`), а не повернутий прямокутник навколо
  них (у хреста Scattered його кути порожні — були смуги згори й знизу);
  Freeform під кутом — без рамки «кадру». px / vh / %: висота як задано,
  фото торкаються верху й низу або боків, по центру. Auto (варіант А):
  блок обрізано від верхньої точки до нижньої, фото того самого розміру
  (`trim()`; у колонці — і `.wp-swiper`, `freeHeight` повертається перед
  вимірюванням); при 0° нічого не міняється. Повернута сітка = блок, її
  порожні кути ріже `overflow: clip` + `overflow-clip-margin` (тінь видно),
  без підтримки — `hidden` (раніше `visible` давало прокрутку сторінки).
  Developer mode: червона рамка — навколо фото, повернута з ними
  (`grid.wpInPlace`).
- **Collage: Content in a place** (2026-11-04, ідея користувача): у
  одній клітинці замість фото — картинка над текстом (варіант «картка»,
  рішення користувача; картинка фоном не робили), на своєму фоні
  (`collage_content_bg`, `alpha_color`, за замовч. `rgba(0,0,0,0)` — фону
  немає, рішення користувача); Image size %, Font size, Padding —
  для кожного пристрою; Vertical alignment — одне. Блок `collage_content`
  на Layout після Size and fit (перенесено на прохання користувача). Клітинка — `collage_content_place` (від 1,
  для кожного пристрою) або перетягнути вміст на фото в прев'ю: у порядку
  `places()` вміст — `-1`, тож drag / Undo / × працюють як з фото
  (`collage.js takeOrder()`, повідомлення `collage-content` → admin.js).
  Не міняється під час обміну фото (`swap()` її оминає), без лайтбокса, не
  прибирається ×. Займає одне з місць (місць не більше, ніж фото + 1).
  Пропорції клітинки для Scattered / Freeform / Random mosaic — 1. Під
  кутом — рівно, найбільший прямокутник її пропорцій усередині клітинки
  (`--wp-swiper-collage-iw/ih`, `sizeImages()`). Текст, що не влазить, —
  обрізано на слові з «…» (`content.js`, спільний з Art; art.js `fitText()`
  для Ring — свій). Не для Justified / Masonry (на пристрої
  з ними — без вмісту; рішення користувача: «почекати») і не у фоні.
  Розмітка — `WP_Swiper_Render::collage_content()` (→ `content_box()`) у схованому
  `<div class="wp-swiper-collage__content-pool" hidden>` (НЕ `<template>`):
  `collage.js` переносить у клітинку сам цей вузол, не копію (перед
  `grid.innerHTML = ''` повертає його назад) — тож скрипти шорткоду його
  бачать, а введене у форму не губиться. **Шорткоди** в Text (2026-11-04,
  рішення користувача — у тому ж полі, не окреме): `do_shortcode()` після
  санітизації тексту; з ними — клас `--scroll`, текст не обрізається,
  клітинка прокручується. Сам у собі не виконується (`$content_depth`,
  усередині — `strip_shortcodes()`). Перевірено: своя форма у
  `fixture.php` (`[wp_swiper_fixture_form]`), кнопка WoodMart
  (`[woodmart_button]`) — тимчасово; HTML-блоків WoodMart на сайті на
  роботі немає. У прев'ю скрипти шорткодів можуть не підключитися (свій
  список скриптів прев'ю).
- **Art: Content in a window** (2026-11-04 для SVG shape, ідея користувача;
  2026-11-05 — і для Ring, Grid + circle, Grid + square замість стилів
  «+ text», рішення користувача; 2026-11-05 — і Grid shapes, вікно 1 —
  перший шматок; `WP_Swiper_Art::has_content()`): одне вікно — вміст замість фото, як
  Content in a place колажу (ті самі поля з префіксом `art_content_*`;
  `art_content_window` — номер вікна: 1 — середнє коло / квадрат (SVG
  shape — головне вікно малюнка), далі решта по порядку; для кожного
  пристрою, або перетягнути в прев'ю). Кругле вікно (`piece--circle`):
  зображення й текст у вписаному квадраті (70.71%, `art.css`), фон — на
  все коло (варіант користувача). Photo swapping «the middle photo», коли
  вміст у середині, міняє будь-яке інше (`art.js swap()`). Старі «+ text»
  → `merge_art_center()` у `get()` (і в `layouts`): базовий стиль,
  `art_content` = старе `art_center` (за замовч. увімкнено), вікно 1,
  значення `art_center_*` (або їхні тодішні за замовч.: 60%, 12px), Ring +
  text — без Opening; поля `art_center_*` і `center()` / `centerContent()`
  видалено. Перевірено: розкладка фото така сама, як у «+ text» (знімки
  `art-*-content` = старі `art-*-text`). Блок `art_content` після Opening.
  `art.js`: `contentAt()`, у `fill()` вікно отримує сам вузол вмісту
  (`-1` у `pieces()`), `advance()` / `swap()` його оминають, без
  `role`/`tabindex` (`markContent()`), описи беруть перше вікно з фото
  (`mainPhoto()`). Фон заповнює фігуру (clip-path), без Gradient. Текст
  ставиться в рамку вікна (bbox), криву фігуру ріже контур, а вікна, що
  лежать зверху (порядок малюнка), його закривають — підказка поля радить
  Padding. Не у фоні. **Спільне з колажем** (НЕ дублювати):
  `WP_Swiper_Render::content_box( $settings, $prefix )` — розмітка
  `.wp-swiper-content` / `__inner` / `__image` / `__text`, змінні
  `--wp-swiper-content-*`; стилі — `frontend.css` (Art з вмістом його
  підключає); обрізання тексту — `assets/js/content.js`
  (`window.wpSwiperContent.fit()`, залежність `collage.js` і `art.js`,
  реєструє `WP_Swiper_Render::register_content()` для обох). Тестовий
  SVG — `tests/data/ex-3.svg` (вікна в ньому накладаються).
- **Картка «What … can do»** (2026-11-04, ідея користувача): під лівим
  меню редактора — 3–6 коротких пунктів, що вміє вибраний ефект (що, а не
  як — для «як» є підказки полів), і спільний рядок для всіх ефектів.
  Тексти — `includes/effect-notes.php` (`WP_Swiper_Settings::effect_notes()`),
  погоджені користувачем; обов'язково згадувати шорткод і поворот (Collage).
  Новий ефект = його список тут (тест перевіряє, що списки є для всіх
  ефектів). Вивід — `WP_Swiper_Admin::effect_notes()`, `<details>` (відкрита),
  згортається; стан — `localStorage` `wpSwiperNotes`; список міняється з
  Effect (`admin.js showNotes()`).
- **Effect shadows: колір** (2026-11-05, рішення користувача): під
  перемикачем Effect shadows — Shadow color (`effect_shadow_color`,
  `alpha_color`) для Coverflow, Cards і Creative; прозорість кольору = сила
  (окремого поля % немає, як Cube Color). Swiper сам міняє `opacity` шару з
  рухом, ми лише фарбуємо шар (`--wp-swiper-effect-shadow`, `slider.css`):
  Creative / Cards — `background`, Coverflow — бічні градієнти до
  `transparent`. Без змінної — як у Swiper. За замовч. 15% чорного, для
  Coverflow 50% (як малював Swiper) — **власне значення ефекту**: ключ
  схеми `effect_default` (effect => значення), `effect_defaults()` /
  `effect_default()` у `WP_Swiper_Settings`; діє в `layouts()` (ефект ще не
  вибирали), `sanitize_fields( …, $effect )` (порожнє / хибне),
  `merge_effect_defaults()` у `get()` (збережене до поля), а кнопка Default
  у Pickr бере його з `data-effect-default` (`admin.js`, `syncFields()`).
  Новому полю з різними значеннями для ефектів — лише `effect_default`.
  Тести: `test-render`, `test-migrations`, `tests/browser/effect-shadows.spec.js`
  (випадки `*-effect-shadow` у `cases.php`, без знімків).
- **Фон підпису: Custom gradient** (2026-11-05, рішення користувача):
  третій Background style (`custom`), колір ховається, замість нього
  редактор градієнта (`caption_bg_gradient`, тип `gradient`, за замовч. як
  «згасання» — колір знизу, прозоро вгорі). `WP_Swiper_Settings::
  caption_gradient()` → клас `--caption-custom` і `--wp-swiper-caption-
  gradient`: смуга підпису слайдера / колажу (і повернутого колажу), уся
  площа шматка Art. Тести: `test-render`, `gradient.spec.js`
  (`*-caption-gradient`), `admin.spec.js`.
- **Тінь у режимі фону** (2026-11-04, рішення користувача): лише Collage і
  Art (`SHADOW_BACKGROUND_EFFECTS`; `shadow()` і `admin.js canShadow`), бо
  там фото стоять окремо; для слайдерів у фоні — ні. Огляд «спільності»
  налаштувань того ж дня: тінь для Slide з одним слайдом (з проміжком),
  інших стилів Creative; рамка й фон для Text mask; Ken Burns для
  Coverflow — запропоновано, поки не робили («поки достатньо»). Zoom on
  hover для 3D і фон для 3D / Thumbnails — не раджу.
- **Image shadow для Flip і Thumbnails** (2026-11-06, рішення
  користувача): обидва в `SHADOW_EFFECTS`. Flip — тінь на картці
  (`.swiper-slide`), повертається з нею; `.swiper-flip` не обрізається
  (виключено з `overflow: clip` / padding тіні у `frontend.css`, як Cards).
  Thumbnails — на головних фото: з переходом Slide — на кожному слайді,
  сусідні за краєм ховає `slider.js hideOutside()` (як у Slide); з Fade —
  клас `wp-swiper--thumbs-fade`, тінь на `.swiper-wrapper` (як у Fade);
  смуга мініатюр (`.wp-swiper__thumbs`) — без тіні й зі своїм боксом
  Swiper. Усі правила Thumbnails — у `thumbs.css` (тест не пускає
  `.wp-swiper__thumbs` у `frontend.css` / `slider.css`). Тест —
  `tests/browser/shadow-more.spec.js`.
- Кнопка × «прибрати місце» — лише Scattered і Freeform; для інших стилів —
  коли попросить.
- Телефон (і планшет) завжди наслідує більший екран: жодних `device_defaults`
  (окремих значень за замовчуванням для пристрою) — не додавати. До версії 7
  вони були у 8 полів (Style/кількість/колонки/висота ряду/відступ колажу,
  Active slide width, Thumbnails per view, ширина опису в лайтбоксі);
  `_version` 7: збережене значення телефона, що дорівнює старому
  (`OLD_MOBILE_DEFAULTS`), стає Inherit (`inherit_old_mobile_defaults()`).
- Один пристрій на весь редактор, в обидва боки (2026-11-03, рішення
  користувача): значок пристрою будь-якого поля, фокус у значенні
  планшета/телефона поля «розміри поруч», розмір шрифту у Slide info чи
  Desktop/Tablet/Mobile прев'ю — `shareDevice()` → `setDevice()`: прев'ю
  (лише рамка, без перемальовування) і всі поля показують цей пристрій
  (`showFieldDevice()`, одна на весь `admin.js` — у блоці Undo була копія
  `var showFieldDevice`, що заміщувала її: не заводити знову). Пам'ятається
  (`localStorage` `wpSwiperDevice`).
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
- Прев'ю оновлюється само лише на інший Effect або Style з іконок (поля
  `tiles`: Style Collage / Art, Strips go Text mask), 2026-11-03, рішення
  користувача; «живе прев'ю» для решти полів — не треба. `admin.js
  autoUpdate()` (одразу, без затримки — рішення користувача; той самий стиль, що
  вже показано, — ні, `markShown()`), лише на подію `change` — Undo ставить
  значення без неї, тож відкат прев'ю не перезавантажує.

## Безпека (огляд 2026-10-30)

- Стилі в текстах (підписи, описи, тексти Art): лише `TEXT_STYLES` —
  колір, підкреслення, вирівнювання (+ `display` у підписах), фільтр
  `safe_style_css` діє тільки під час `sanitize_rich_text()`. Тексти
  санітизуються й при виведенні, тож це діє і для старих слайдерів.
- Посилання з `target` в описах отримують `rel="noopener"` (`noopener()`;
  `wp_targeted_link_rel()` застаріла з WP 6.7 — не використовувати).
- `sanitize_fields()`: масив замість одного значення = відсутнє (`is_scalar`).
- JSON у `<script>` прев'ю — з `JSON_HEX_TAG | JSON_HEX_AMP`.
- Дії GitHub Actions закріплені за SHA комітів (тег у коментарі);
  оновлюючи дію, бери SHA через `git ls-remote https://github.com/<repo>
  refs/tags/<tag>` (і `^{}` для анотованих тегів). `tests.yml` — лише
  читання. Оновлення сайтів ідуть з релізів GitHub: 2FA на обліковому
  записі обов'язкова.

## Відомі обмеження

- Freeform: зміна Gap після ручної розстановки не пересуває збережені місця;
  Shuffle або Reset дають нову розкладку з новим Gap.
- Art: текст у розриві (Ring), що не влазить,
  обрізається на слові з «…» (`art.js fitText()`), шрифт не зменшується
  (рішення користувача: розмір і стилі теми лишаються). Прев'ю без стилів
  теми, тож там тексту влазить інакше, ніж на сайті. Слово, ширше за коло
  (великий заголовок теми), переноситься по літерах.
- Art SVG shape: підпис у центрі bbox вікна (у трафареті — форми) може
  виходити за край органічної форми.
- Art: на великих фото «нечітко → скачок» під час анімації (поява,
  hover-zoom), найімовірніше Chrome так зменшує картинки.
- Адмінку перевіряють лише «димові» тести `tests/browser/admin.spec.js`
  (2026-11-05, див. «Тести»): відкривається, основні дії працюють. Тонкі
  дії в прев'ю (перетягування фото, лінії колажу, Grid shapes) — ні. Після
  змін у `admin*.js` чи `preview-*.js` прожени їх і попроси користувача
  перевірити руками те, чого вони не торкаються.

## Відкладено і відхилено

- **Відкладено** («на потім», без нагадувань не починати):
  - (порожньо: розмір фото Art, Content in a window для Grid shapes і
    градієнт фону підпису зроблено 2026-11-05).
- **Відхилено, не пропонувати знову:**
  - «Proportions» слайдів («висота+пропорція» і «кількість+пропорція»);
  - зміна розмірів для Grid, Justified rows і Masonry;
  - прибирання загальної Image position.

## Як влаштований код (коротко)

- `includes/settings-fields.php` — схема всіх налаштувань (section, type,
  responsive, effects, `inline_in`, `tiles`, `styles`, `soon`…). Його
  повертає `WP_Swiper_Settings::fields()` (`require` всередині методу, тож
  `self::` там — цей клас), Art додає свої з `WP_Swiper_Art::fields()`
  (так само `require` файлу `includes/art-fields.php`). Сам файл лише
  зливає частини з `includes/settings-fields/` у порядку: `general`,
  `layout-collage`, `layout-look`, `layout-effects`, `content` (що в
  якій — у doc кожної). Порядок полів той самий, що був; ключ двічі —
  падає тест.
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
- Великі класи розбито на трейти так само, як `WP_Swiper_Settings`
  (2026-11-05, лише перенесення методів, без змін коду). Новий метод — у
  трейт своєї теми; `self::` у трейті — клас, константи — у класі:
  - `WP_Swiper_Admin` (`init`, `enqueue`, вікно налаштувань, `save`) +
    `trait-wp-swiper-admin-slides.php` (Slides box, рядки слайдів,
    `posted_slides()`), `-controls` (шрифт, колір Pickr, градієнт, іконки),
    `-fields` (блоки й усі типи полів, `tiles()`), `-list` (колонки списку,
    Clone);
  - `WP_Swiper_Render` (`render_slider()`, `markup()`, описи, слайд) +
    `trait-wp-swiper-render-assets.php` (реєстрація й підключення файлів,
    стилі в `<head>`), `-config` (`js_config()` і числа для нього), `-media`
    (відео, embed, постери), `-collage` (колаж і Content in a place);
  - `WP_Swiper_Art` (стилі, `fields()`, `blocks()`, `render()`) +
    `trait-wp-swiper-art-cells.php` (Grid shapes, `art_cells`), `-sizes`
    (кількості й висоти), `-gradient` (Gradient усіх ефектів), `-parts`
    (шматки розмітки `render()`).
- `admin.css` розрізано на частини в тому самому порядку
  (`WP_Swiper_Admin::STYLES`, кожна після попередньої): `admin.css` (база,
  Slides box), `admin-settings.css` (меню, сторінки, прев'ю), `-fields`,
  `-slide-info`, `-color` (і Pickr, і градієнт), `-position`, `-controls`
  (стани полів, ряди, перемикачі, розміри, font picker). Нове правило — у
  частину своєї теми; новий файл — рядок у `STYLES`.
- **JS адмінки** (2026-11-05 розділено, рішення користувача; раніше одна
  функція `admin.js` на 3 800 рядків): `admin.js` — ядро (поля, блокування
  `lockable()`, Layout кожного ефекту, `syncFields()`) і запуск частин;
  частини — `admin-slides.js` (слайди, постери, файл SVG shape),
  `admin-slide-info.js` (вікно Slide info, шрифти, точка фокуса),
  `admin-colors.js` (Pickr, градієнт), `admin-pages.js` (меню сторінок,
  картка ефекту, редактори тексту, значення для пристроїв, `setDevice()`),
  `admin-preview.js` (прев'ю, Undo / Redo, повідомлення від
  `preview-*.js`). Кожна частина — `window.wpSwiperEditor.parts.<назва> =
  function ( shared )`; `admin.js` запускає їх на ready у тому порядку, що
  був в одній функції (`[ 'head', 'slides', …, 'tail' ]`). Спільне — через
  `shared`: узяте з частини раніше — аліас на початку (`var x = shared.x`),
  з частини пізніше або змінне (`currentDevice`, `layouts`, `layoutEffect`,
  `$editing`, `frame`, `captionsShown`) — `shared.x` щоразу. Нове спільне =
  `shared.x = x` там, де воно з'являється. Частина НЕ може при завантаженні
  кликати функцію пізнішої частини (вона ще не запущена) — лише в
  обробниках; тому `captionForEditor()` перенесено в `admin-slide-info.js`
  перед першим викликом. Розділено механічно (acorn: хто що оголошує й
  бере), перевірено, що без доданих рядків код дослівно той самий. Підключення —
  `WP_Swiper_Admin::SCRIPTS` (залежності `admin.js`; переклади — у
  `wpSwiperAdmin` на `admin.js`); нова частина — рядок там і в списку
  запуску. Тести PHP, що шукають шматок JS, читають усі через
  `admin_js()` (`class-wp-swiper-testcase.php`). Згадки «`admin.js` …» вище
  в цьому файлі — про весь JS адмінки.
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
  `wp_print_scripts()`; новий скрипт сайту — додати й туди; власні файли прев'ю
  (`preview-*.js/css`) — у `WP_Swiper_Preview::register_assets()`, не тегами
  вручну). Без слайдів —
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
  (частина малюнка). **Темний екран** (2026-11-05, ідея користувача): кнопка
  після Rotate, іконка `assets/images/light-dark.svg` (малюнок користувача,
  переписаний на `currentColor` без `<style>` — класи Illustrator `.st0`
  інакше потрапили б у сторінку адмінки); фон сторінки прев'ю `#121212`,
  текст без свого кольору `#e6e6e6`, блок фону `#1e1e1e` (рішення
  користувача — мої варіанти за замовч.). Лише адмінка, не налаштування
  слайдера. Клас `wp-swiper-preview-dark` на `<html>` рамки: `admin.js`
  ставить одразу (без Update), а сторінка прев'ю сама читає той самий
  `localStorage` `wpSwiperDark` інлайн-скриптом у `<head>` — без білого
  спалаху при оновленні. Developer mode схований (місце лишається, `visibility`,
  щоб панель не переносилась інакше) для Text mask, Art (крім SVG shape) і
  слайдера без слайдів (з 2026-11-03 є й для Collage, з 2026-11-04 — для
  Art SVG shape).
  Скрипти прев'ю:
  - `preview-collage.js`: обмін фото, лінії-ручки, Scattered, Freeform;
  - `preview-art.js`;
  - `preview-dev.js`: Developer mode — слайди (межі, фото з розміром,
    проміжки, порожнеча при Contain) і колаж (`drawCollage()`, 2026-11-03:
    шар у сітці, повертається з нею, підписи рівні; місця з розміром,
    проміжки до найближчих сусідів, рамка блоку з розміром, при повороті —
    кут і розмір розкладки, червона рамка розкладки лише для Fill / Fit).
    Art SVG shape (`drawArt()`, 2026-11-04): рамки вікон з номером і
    розміром, контури фігур (SVG з їхніх clip-path), робоча область Content
    in a window (рамка вікна мінус Padding) з розміром і «text cut».
    Другим кроком, якщо попросить, — червона підсвітка, де текст виходить
    за контур чи під інше вікно; потім Text mask (смуги й літери).

  Вони шлють `postMessage`, `admin.js` записує значення в приховані поля.

## Тести

**PHPUnit** — див. `tests/README.md`. Треба PHP 7.4+, Composer, WP-CLI і
окрема база MySQL `wp_swiper_tests`, лише для тестів (тести стирають її
таблиці). 424 тести, CI (`tests.yml`) ганяє їх на PHP 7.4 / 8.3 при push у
`main` та на PR.

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
Адмінка (2026-11-05, рішення користувача — раніше лише сайт):
`admin.spec.js`, 13 «димових» тестів редактора (лише desktop). Вхід —
`admin-login.php` (лише з 127.x, як `fixture.php`): тестовий
адміністратор `wp-swiper-tests` без відомого пароля, створюється сам,
одна сесія на всіх (`global-setup.js` створює її перед тестами — інакше
паралельні входи затирали сесії одне одному й тести вилітали на
wp-login); кожен тест створює слайдер із `cases.php` і видаляє свій після
себе, `?cleanup=1` — залишки. Користувача можна видалити — створиться знову.
`fixture.php` показує слайдер з налаштувань `cases.php` на фото з медіатеки,
нічого не зберігає, відкривається лише з 127.x. Перевіряє 65 випадків
(усі ефекти, стилі колажу й Art) на desktop і mobile: без помилок у консолі,
які файли підключено, де стоять фото (знімки в `snapshots/`), стрілки,
лайтбокс. Запуск: `cd tests/browser && npm install && npx playwright test`;
після навмисної зміни розкладки — `npx playwright test --update-snapshots`
і переглянути різницю знімків.
