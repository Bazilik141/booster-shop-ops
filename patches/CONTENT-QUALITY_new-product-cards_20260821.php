<?php
declare(strict_types=1);

/*
 * CONTENT-QUALITY — WP2 of 3: nine new product cards (8 sealed TCG + 1 accessory).
 * 2026-08-21.  Run AFTER WP1 (3D-P-CARDCONTENT_card-text-fixes_20260821.php).
 *
 * ⚠ `CONTENT-QUALITY` is a WORKING LABEL. No roadmap ID exists for this work yet
 *   (handoff §1). This patch does not touch Notion and does not touch
 *   ROADMAP_FLOW.
 *
 * SOURCE OF TRUTH
 *   handoffs/handoff_CONTENT-QUALITY_card-content-patch_20260821.md   §3
 *   CONTENT-QUALITY_3D-card-fixes_20260821.md — «Додаток 2026-08-21», 9 cards
 *   diagnostics/CONTENT-QUALITY_corrections_20260821.md — OVERRIDES the payload
 *     for categories (§1.2), SEO URLs (§2), attributes (§3.1/§3.2) and the
 *     ACC-007-400 body + FAQ (§4.2) and the QCAC paragraph (§4.9).
 *   DB baseline: backup-8.21.2026_22-06-47_boosters.tar.gz -> mysql/boosters_ocart49.sql
 *
 * WHAT THIS DOES — INSERT ONLY, NOT ONE UPDATE
 *   Creates nine products. Per product: one row in product, product_description
 *   (language_id 4), product_to_store (store_id 0), product_code (SKU),
 *   seo_url (key product_id) and TWO product_to_category rows — the subcategory
 *   and its parent, which is how all 85 existing products are filed
 *   (corrections §1.1: 85 of 85, no exceptions).
 *   Attribute rows: 9 per sealed SKU, 5 for ACC-007-400. Total 77.
 *
 *   SKU                price   categories        attributes
 *   ACC-007-400          550   70 + 71           5   (group 9)
 *   YGO-JP-QCAC-BBX     2800   66 + 65           9   (group 7)
 *   PKM-JP-STES-BBX     5900   59 + 62           9
 *   PKM-JP-STES-BST      220   59 + 61           9
 *   PKM-JP-INFX-BBX     6500   59 + 62           9
 *   PKM-JP-TGTR-BBX    12000   59 + 62           9
 *   PKM-JP-TGTR-BST      320   59 + 61           9
 *   YGO-JP-BETB-BBX     2500   66 + 65           9
 *   YGO-JP-BETB-BST       75   66 + 65           9
 *
 * CATEGORY 61 IS RESOLVED BY ID, NOT BY KEYWORD — handoff §3.3
 *   ocp5_seo_url holds TWO `key='path'` rows for value 59_61:
 *     (1056, 'Pokemon/bustery-pokemon')  and  (1068, 'Pokemon-boosters')
 *   The keyword resolver used by the 3D-P-002 wave would abort on that
 *   («has N seo_url rows — ambiguous»). So every category here is resolved by
 *   category_id and asserted against its name AND parent_id in
 *   ocp5_category_description / ocp5_category. The duplicate row is NOT touched
 *   — that is WP3, diagnosis only.
 *
 * VISIBILITY, STOCK, PRICE — handoff §3.5
 *   status = 0            products are NOT enabled by this patch
 *   quantity = 0          stock is the owner's
 *   stock_status_id = 8   «Передзамовлення», same as the 3D wave
 *   image = ''            photos are the owner's step
 *   price                 from CRM, per the table above
 *   sort_order = 1        mirrored from the neighbour products (all are 1)
 *
 * TECHNICAL FIELDS ARE MIRRORED, NOT INVENTED — handoff §3.4
 *   Everything the payload does not specify is copied from the nearest existing
 *   neighbour in the 2026-08-21 backup. Actual values, read from the dump:
 *
 *   neighbour            manufacturer  weight  wcls  L    W    H   lcls  sort
 *   50  PKM-JP-MSYM-BST  11            10      2     15   7    1   1     1
 *   56  PKM-MEGA-BOX     11            300     2     150  150  50  2     1
 *   83  YGO-JP-QCAC-BST  13            10      2     14   7    1   1     1
 *   102 YGO-JP-WPP5-BBX  13            250     2     14   9    8   1     1
 *   112 ACC-007-360      16            125     2     200  200  15  2     1
 *
 *   The two box neighbours disagree with each other (56 is 150×150×50 in mm,
 *   102 is 14×9×8 in cm). Both are mirrored as they stand, per the handoff.
 *   Consistent shipping dimensions across box SKUs are a separate decision.
 *   tax_class_id 0, points 0, shipping 1, subtract 1, minimum 1, rating 0,
 *   master_id 0, upc/ean/jan/isbn/mpn NULL — matching every neighbour.
 *
 * ATTRIBUTE WORDING IS THE LIVE WORDING — corrections §3.1
 *   Taken verbatim from the products already on the site, not from the draft:
 *     Мова               «Японська (Japanese)»                        (45 rows)
 *     Стан               «Новий, нерозпакований (Sealed)»             (50 rows)
 *     Походження, box    «Оригінальний sealed box в заводській плівці» (6 rows)
 *     Походження, pack   «Box / Case sourced (з оригінального боксу / кейсу)» (19 rows)
 *     Зважування         «Без зважування (Unweighed)»                 (29 rows)
 *   No attribute, category or manufacturer is created by this patch.
 *
 * DELIBERATELY ABSENT — handoff §3.6
 *   - attribute 24 «Додатковий вміст» on YGO-JP-BETB-BBX: no `+1 Expansion
 *     Pack` until first-print is confirmed (owner decision).
 *   - attribute 29 «Матеріал» on ACC-007-400: the material is unknown and the
 *     field is not created at all (owner decision 2026-08-21). ACC-007-400
 *     therefore gets FIVE attributes, not six. The handoff's owner-QA step 7
 *     says «шість полів» — that count includes «Матеріал» and is one too many
 *     once the owner's decision is applied. Flagged in the report.
 *   - product 83 (Yu-Gi-Oh-boosters-Quarter-Century-Art-Collection) is not
 *     touched, although it is the same set as YGO-JP-QCAC-BBX.
 *
 * ONE EXECUTOR-APPLIED TEXT FIX BEYOND THE CORRECTIONS — flagged for review
 *   corrections §4.9 removed the QCAC sentence about «Secret Rare та Quarter
 *   Century Secret Rare варіанти» because QCSR variants did not verify. The
 *   payload's FAQ [3] on the same card repeated the same unverified token:
 *     was: «Чи гарантується конкретна QCSR або альтернативна ілюстрація?» /
 *          «Ні. Офіційна сторінка описує доступні варіанти карт, але не
 *           гарантує конкретну карту або варіант в окремому боксі.»
 *     now: «Чи гарантується конкретна карта або рідкість?» /
 *          «Ні. Офіційна сторінка описує пул сету, але не гарантує конкретну
 *           карту чи рідкість в окремому боксі.»
 *   Same protective meaning, no unverified token. `meta_keyword` still carries
 *   «QCSR QCAC» as a search token — left as the payload wrote it, flagged.
 *
 * APOSTROPHES — ASCII ('), matching the 2026-08-19 wave and the category texts.
 *
 * ENCODING — description is stored ENTITY-ENCODED (`&lt;p&gt;…`), CRLF, blank
 *   line between blocks, exactly as the 2026-08-19 wave wrote it. FAQ markup is
 *   the current site convention: <section class="bs-faq-accordion"> with
 *   data-bs-faq-id="prod-<sku-lowercase>" — 71 of the 85 live products use it.
 *
 * PREFLIGHT — the patch refuses to run if (handoff §3.7)
 *   - any of the nine SKUs already exists in product_code or as product.model;
 *   - any proposed seo_url keyword already resolves (exact or as a path tail);
 *   - any of categories 59/61/62/65/66/70/71 is missing, renamed or re-parented;
 *   - any of attributes 12..21, 27, 28, 30, 33, 34 is missing or renamed;
 *   - language_id 4 or store_id 0 is not the configured default;
 *   - manufacturer 11/13/16 or stock_status 8 is not what it should be.
 *
 * IDEMPOTENCY (C5)
 *   Products already present by model are skipped; if all nine exist the patch
 *   logs already_applied=yes and self-deletes without writing.
 *
 * BACKUP (C3)
 *   _patch_backups/<patch>-<ts>/db/before.json    counts + resolved ids
 *   _patch_backups/<patch>-<ts>/db/created_ids.json  actual product_ids
 *   _patch_backups/<patch>-<ts>/db/rollback.sql   generated AFTER the insert,
 *                                                 with the real ids filled in
 *
 * ============================ ROLLBACK ============================
 * Everything this patch writes is new rows, so rollback is DELETE only. Run
 * _patch_backups/<patch>-<ts>/db/rollback.sql, or by hand, in this order, with
 * the ids from created_ids.json (the products are also findable by model):
 *
 *   SET @ids = '<comma separated product_id list>';   -- e.g. 144,145,…,152
 *
 *   DELETE FROM `ocp5_seo_url`
 *    WHERE `key` = 'product_id' AND FIND_IN_SET(`value`, @ids);
 *   DELETE FROM `ocp5_product_attribute`   WHERE FIND_IN_SET(product_id, @ids);
 *   DELETE FROM `ocp5_product_to_category` WHERE FIND_IN_SET(product_id, @ids);
 *   DELETE FROM `ocp5_product_to_store`    WHERE FIND_IN_SET(product_id, @ids);
 *   DELETE FROM `ocp5_product_code`        WHERE FIND_IN_SET(product_id, @ids);
 *   DELETE FROM `ocp5_product_description` WHERE FIND_IN_SET(product_id, @ids);
 *   DELETE FROM `ocp5_product`             WHERE FIND_IN_SET(product_id, @ids);
 *
 * Nothing else is written, so no UPDATE is needed to undo this patch. The
 * duplicate seo_url row of category 61 is untouched and must stay untouched.
 * ==================================================================
 *
 * DB BACKUP IS THE OWNER'S STEP — take a MySQL dump before running
 * (cPanel -> Backup -> Download a MySQL Database Backup). The whole insert runs
 * in one transaction and rolls itself back on any failure, but that is not a
 * substitute for a dump.
 *
 * RUN:  upload to ~/public_html, then  php CONTENT-QUALITY_new-product-cards_20260821.php
 * The patch self-deletes on success (C7).
 */

const PATCH_NAME  = 'CONTENT-QUALITY_new-product-cards_20260821';
const LANGUAGE_ID = 4;
const STORE_ID    = 0;

const STATUS          = 0;
const QUANTITY        = 0;
const STOCK_STATUS_ID = 8;
const STOCK_STATUS    = 'Передзамовлення';
const TAX_CLASS_ID    = 0;
const SORT_ORDER      = 1;   // mirrored: products 50, 56, 83, 102 and 112 all have sort_order = 1
const WEIGHT_CLASS_ID = 2;   // g
const LENGTH_CLASS_ID_CM = 1;
const LENGTH_CLASS_ID_MM = 2;

const ATTR_GROUP_SEALED    = 7;   // Характеристики
const ATTR_GROUP_ACCESSORY = 9;   // Характеристики аксесуарів

/** Every category this patch files a product into: id => [name, parent_id]. */
const TARGET_CATEGORIES = [
    59 => ['name' => 'Pokémon',                  'parent' => 0],
    61 => ['name' => 'Бустери Pokémon',          'parent' => 59],
    62 => ['name' => 'Бустер бокси Pokémon',     'parent' => 59],
    65 => ['name' => 'Yu-Gi-Oh!',                'parent' => 66],
    66 => ['name' => 'Інші TCG',                 'parent' => 0],
    70 => ['name' => 'Аксесуари',                'parent' => 0],
    71 => ['name' => 'Протектори та зберігання', 'parent' => 70],
];

/** Attribute id => exact name. Asserted before anything is written. */
const REQUIRED_ATTRIBUTES = [
    12 => 'Мова',
    13 => 'Назва сету',
    14 => 'Рік випуску',
    15 => 'Кількість карток у бустері',
    16 => 'Кількість бустерів у боксі',
    17 => 'Стан',
    18 => 'Походження товару',
    19 => 'Зважування',
    20 => 'Виробник',
    21 => 'Тип пакування',
    27 => 'Тип товару',
    28 => 'Кількість в упаковці',
    30 => 'Розмір / Формат',
    33 => 'Сумісність з картками',
    34 => 'Кількість кишеньок',
];

/** manufacturer_id => exact name. Asserted before anything is written. */
const REQUIRED_MANUFACTURERS = [
    11 => 'The Pokémon Company',
    13 => 'Konami',
    16 => 'Generic',
];

/** Attribute values that never change inside this wave. */
const SEALED_COMMON = [
    12 => 'Японська (Japanese)',
    17 => 'Новий, нерозпакований (Sealed)',
];
const ORIGIN_BOX  = 'Оригінальний sealed box в заводській плівці';
const ORIGIN_PACK = 'Box / Case sourced (з оригінального боксу / кейсу)';
const UNWEIGHED   = 'Без зважування (Unweighed)';

const PRODUCTS = [
    // ---------------------------------------------------------------- accessory
    [
        'sku'        => 'ACC-007-400',
        'slug'       => 'albom-dlia-kartok-na-400-slotiv',
        'categories' => [70, 71],
        'name'       => 'Альбом для колекційних карток на 400 карток, жовтий',
        'meta_title' => 'Жовтий альбом для карток на 400 карток | Booster Shop',
        'meta_desc'  => 'Жовтий альбом для колекційних карток на 400 карток: 4-pocket сторінки, кільцевий механізм і блискавка. Купити в Україні — Booster Shop.',
        'meta_kw'    => 'альбом для карток 400, жовтий альбом для карток, альбом для колекційних карток, TCG альбом 4 pocket',
        'price'      => '550.0000',
        'mirror'     => 'product 112 ACC-007-360',
        'ship'       => ['manufacturer' => 16, 'weight' => '125', 'length' => '200', 'width' => '200', 'height' => '15', 'length_class' => LENGTH_CLASS_ID_MM],
        'group'      => ATTR_GROUP_ACCESSORY,
        // corrections §4.2 — no «за фото товару», no 24×18 см, no manual-check paragraph
        'body'       => [
            '<h2>Жовтий альбом на 400 карток — колекція в одному місці</h2>',
            '<p>Альбом для колекційних карток на 400 карток розрахований на <strong>зберігання до 400 карток стандартного формату</strong> — коли колекцію зручніше тримати в одному великому альбомі, а не розкладати по кількох менших.</p>',
            '<p>Сторінки на чотири кишеньки, кільцевий механізм, застібка-блискавка по периметру та ремінець на руку. Формат із чотирма кишеньками зручний, коли картки переглядають послідовно, одну за одною, а не порівнюють по дев\'ять на розвороті.</p>',
            '<p>Жовта обкладинка помітна на полиці й серед інших альбомів колекції. Кільцевий механізм дозволяє додавати й переставляти аркуші, а блискавка тримає альбом закритим під час перенесення.</p>',
        ],
        'faq'        => [
            ['Чим цей альбом відрізняється від альбому на 360 карток?', 'Місткістю й форматом сторінки: тут чотири кишеньки на сторону й до 400 карток, у меншому — 3×3 кишеньки та до 360 карток.'],
            ['Який формат сторінок усередині?', 'По чотири кишеньки на сторону.'],
            ['Чи входять картки в комплект?', 'Ні. У комплекті лише альбом.'],
        ],
        // corrections §3.2 — five fields; «Матеріал» (29) is deliberately absent
        'attributes' => [
            27 => 'Альбом для колекційних карток',
            28 => '1 шт',
            30 => '4 кишеньки на сторону; місткість до 400 карток',
            33 => 'Pokémon, One Piece, MTG, Lorcana та інші Standard-size TCG',
            34 => 'До 400 карток',
        ],
    ],

    // ------------------------------------------------------------- Yu-Gi-Oh! box
    [
        'sku'        => 'YGO-JP-QCAC-BBX',
        'slug'       => 'YuGiOh-booster-box-Quarter-Century-Art-Collection',
        'categories' => [66, 65],
        'name'       => 'Бустер бокс Yu-Gi-Oh! OCG: Quarter Century Art Collection (Японське видання)',
        'meta_title' => 'QCAC бустер бокс Yu-Gi-Oh! OCG JP — sealed | Booster Shop',
        'meta_desc'  => 'Японський sealed бокс Yu-Gi-Oh! OCG Quarter Century Art Collection: 15 бустерів по 4 карти, 100 типів карт. Заводський шрінк. Купити в Україні.',
        'meta_kw'    => 'Quarter Century Art Collection booster box, QCAC booster box, Yu-Gi-Oh OCG Japanese box, QCSR QCAC',
        'price'      => '2800.0000',
        'mirror'     => 'product 102 YGO-JP-WPP5-BBX',
        'ship'       => ['manufacturer' => 13, 'weight' => '250', 'length' => '14', 'width' => '9', 'height' => '8', 'length_class' => LENGTH_CLASS_ID_CM],
        'group'      => ATTR_GROUP_SEALED,
        'body'       => [
            '<h2>Quarter Century Art Collection — арт-реліз Yu-Gi-Oh! OCG у форматі боксу</h2>',
            '<p>Бустер бокс Quarter Century Art Collection містить <strong>15 японських бустерів по 4 карти</strong>. QCAC — спеціальний реліз Yu-Gi-Oh! OCG від 22 лютого 2025 року з акцентом на відомі карти, альтернативні ілюстрації та колекційний формат сету.</p>',
            // corrections §4.9 — the QCSR sentence is removed, only the verified pool stays
            '<p>Офіційний пул налічує 100 типів карт: 40 Ultra Rare і 60 Super Rare.</p>',
            '<p>Бокс продається новим і в заводському шрінку. Один бокс не містить усі 100 типів карт, а конкретні карти, рідкісності й альтернативні ілюстрації в окремому боксі не гарантуються.</p>',
        ],
        'faq'        => [
            ['Скільки бустерів у Quarter Century Art Collection box?', '15 бустерів по 4 карти в кожному.'],
            ['Чи можна зібрати всі 100 типів карт з одного боксу?', 'Ні. Konami прямо зазначає, що один бокс не містить усі 100 типів карт.'],
            // executor fix, see the header: no unverified QCSR token
            ['Чи гарантується конкретна карта або рідкість?', 'Ні. Офіційна сторінка описує пул сету, але не гарантує конкретну карту чи рідкість в окремому боксі.'],
        ],
        'attributes' => [
            13 => 'Quarter Century Art Collection',
            21 => 'Sealed Booster Box',
            15 => '4',
            16 => '15',
            18 => ORIGIN_BOX,
            20 => 'Konami',
            14 => '2025',
        ],
    ],

    // -------------------------------------------------------- Pokémon Storm Emeralda
    [
        'sku'        => 'PKM-JP-STES-BBX',
        'slug'       => 'Pokemon-booster-box-Storm-Emeralda',
        'categories' => [59, 62],
        'name'       => 'Бустер бокс Pokémon TCG: Storm Emeralda (Японське видання)',
        'meta_title' => 'Storm Emeralda бокс Pokémon TCG JP — sealed | Booster Shop',
        'meta_desc'  => 'Японський sealed бокс Pokémon TCG Storm Emeralda: 30 бустерів по 5 карт, Mega Rayquaza ex. Заводський шрінк. Купити в Україні.',
        'meta_kw'    => 'Storm Emeralda booster box, Pokémon Storm Emeralda JP, Mega Rayquaza ex, Pokemon M6 booster box',
        'price'      => '5900.0000',
        'mirror'     => 'product 56 PKM-MEGA-BOX',
        'ship'       => ['manufacturer' => 11, 'weight' => '300', 'length' => '150', 'width' => '150', 'height' => '50', 'length_class' => LENGTH_CLASS_ID_MM],
        'group'      => ATTR_GROUP_SEALED,
        'body'       => [
            '<h2>Storm Emeralda — японський бустер бокс із Mega Rayquaza ex</h2>',
            '<p>Бустер бокс Pokémon TCG Storm Emeralda містить <strong>30 японських бустерів по 5 випадкових карт</strong>. Японський реліз вийшов 31 липня 2026 року й побудований навколо повернення Mega Rayquaza ex.</p>',
            '<p>Одна з помітних механік сету — стадіон, який складається з двох окремих карт із однаковою назвою та розігрується як одна конструкція. На офіційній сторінці ця механіка показана разом із Mega Rayquaza ex як один із центральних елементів релізу.</p>',
            '<p>Бокс продається новим і в заводському шрінку. Конкретні карти в бустерах випадкові: картка товару не обіцяє Mega Rayquaza ex, конкретну рідкість або визначену кількість хітів у боксі.</p>',
        ],
        'faq'        => [
            ['Скільки бустерів у Storm Emeralda booster box?', '30 бустерів; у кожному по 5 випадкових карт.'],
            ['Чи гарантується Mega Rayquaza ex?', 'Ні. Mega Rayquaza ex представлений у сеті, але конкретні карти в кожному бустері випадкові.'],
            ['Чи гарантується конкретна кількість рідкісних карт у боксі?', 'Ні. Booster Shop не заявляє неофіційні pull rates або не підтверджені виробником гарантії хітів.'],
        ],
        'attributes' => [
            13 => 'Storm Emeralda',
            21 => 'Sealed Booster Box',
            15 => '5',
            16 => '30',
            18 => ORIGIN_BOX,
            20 => 'The Pokémon Company',
            14 => '2026',
        ],
    ],

    [
        'sku'        => 'PKM-JP-STES-BST',
        'slug'       => 'Pokemon-boosters-Storm-Emeralda',
        'categories' => [59, 61],
        'name'       => 'Бустер Pokémon TCG: Storm Emeralda (Японське видання)',
        'meta_title' => 'Storm Emeralda бустер Pokémon TCG JP — sealed | Booster Shop',
        'meta_desc'  => 'Японський sealed бустер Pokémon TCG Storm Emeralda: 5 карт, із заводського боксу. Mega Rayquaza ex у сеті. Купити в Україні.',
        'meta_kw'    => 'Storm Emeralda booster, Pokémon Storm Emeralda JP, Mega Rayquaza ex, Pokemon M6 booster',
        'price'      => '220.0000',
        'mirror'     => 'product 50 PKM-JP-MSYM-BST',
        'ship'       => ['manufacturer' => 11, 'weight' => '10', 'length' => '15', 'width' => '7', 'height' => '1', 'length_class' => LENGTH_CLASS_ID_CM],
        'group'      => ATTR_GROUP_SEALED,
        'body'       => [
            '<h2>Storm Emeralda — один японський бустер із сету Mega Rayquaza ex</h2>',
            '<p>Бустер Pokémon TCG Storm Emeralda містить <strong>5 випадкових карт японського видання</strong>. Сет вийшов 31 липня 2026 року, а його центральною картою в офіційній презентації стала Mega Rayquaza ex.</p>',
            '<p>Storm Emeralda також вводить стадіон із двох окремих карт, які поєднуються в одну конструкцію на полі. Це одна з механічних особливостей релізу поряд із новими Pokémon ex.</p>',
            '<p>Поштучні бустери для цього SKU отримуються з бустер боксів у заводському шрінку, які розкриваються під роздрібний продаж паків. Сам бустер лишається запечатаним. Конкретна карта або рідкість у ньому не гарантується.</p>',
        ],
        'faq'        => [
            ['Скільки карт у бустері Storm Emeralda?', '5 випадкових карт.'],
            ['Чи гарантується Mega Rayquaza ex?', 'Ні. Mega Rayquaza ex є частиною сету, але конкретний вміст окремого бустера випадковий.'],
            ['Це запечатаний бустер чи розсип?', 'Це запечатаний бустер. Для цього SKU поштучні паки дістаються з бустер боксів у шрінку, які відкриваються під продаж окремих паків.'],
        ],
        'attributes' => [
            13 => 'Storm Emeralda',
            21 => 'Sealed Booster Pack',
            15 => '5',
            18 => ORIGIN_PACK,
            19 => UNWEIGHED,
            20 => 'The Pokémon Company',
            14 => '2026',
        ],
    ],

    // ------------------------------------------------------------ Pokémon Inferno X
    [
        'sku'        => 'PKM-JP-INFX-BBX',
        'slug'       => 'Pokemon-booster-box-Inferno-X',
        'categories' => [59, 62],
        'name'       => 'Бустер бокс Pokémon TCG: Inferno X (Японське видання)',
        'meta_title' => 'Inferno X бокс Pokémon TCG JP — sealed | Booster Shop',
        'meta_desc'  => 'Японський sealed бокс Pokémon TCG Inferno X: 30 бустерів по 5 карт, Mega Charizard X ex. Заводський шрінк. Купити в Україні.',
        'meta_kw'    => 'Inferno X booster box, Pokémon Inferno X JP, Mega Charizard X ex, Pokemon M2 booster box',
        'price'      => '6500.0000',
        'mirror'     => 'product 56 PKM-MEGA-BOX',
        'ship'       => ['manufacturer' => 11, 'weight' => '300', 'length' => '150', 'width' => '150', 'height' => '50', 'length_class' => LENGTH_CLASS_ID_MM],
        'group'      => ATTR_GROUP_SEALED,
        'body'       => [
            '<h2>Inferno X — японський бустер бокс із Mega Charizard X ex</h2>',
            '<p>Бустер бокс Pokémon TCG Inferno X містить <strong>30 японських бустерів по 5 випадкових карт</strong>. Реліз вийшов 26 вересня 2025 року, а центральною картою офіційної презентації став Mega Charizard X ex.</p>',
            '<p>Inferno X належить до лінійки Pokémon Card Game MEGA й будує основний образ сету навколо Mega Evolution та вогняної тематики Mega Charizard X ex.</p>',
            '<p>Бокс продається новим і в заводському шрінку. Конкретний склад кожного бустера випадковий: Booster Shop не заявляє гарантований Mega Charizard X ex, конкретну рідкість або неофіційний pull rate для боксу.</p>',
        ],
        'faq'        => [
            ['Скільки бустерів у Inferno X booster box?', '30 бустерів; у кожному по 5 випадкових карт.'],
            ['Чи гарантується Mega Charizard X ex?', 'Ні. Ця карта є частиною сету, але конкретні карти в кожному бустері випадкові.'],
            ['Бокс продається в шрінку?', 'Так. За правилом магазину box-SKU продаються новими й у заводському шрінку.'],
        ],
        'attributes' => [
            13 => 'Inferno X',
            21 => 'Sealed Booster Box',
            15 => '5',
            16 => '30',
            18 => ORIGIN_BOX,
            20 => 'The Pokémon Company',
            14 => '2025',
        ],
    ],

    // ------------------------------------------------- Pokémon The Glory of Team Rocket
    [
        'sku'        => 'PKM-JP-TGTR-BBX',
        'slug'       => 'Pokemon-booster-box-The-Glory-of-Team-Rocket',
        'categories' => [59, 62],
        'name'       => 'Бустер бокс Pokémon TCG: The Glory of Team Rocket (Японське видання)',
        'meta_title' => 'Team Rocket бокс Pokémon TCG JP — sealed | Booster Shop',
        'meta_desc'  => 'Японський sealed бокс Pokémon TCG The Glory of Team Rocket: 30 бустерів по 5 карт. Заводський шрінк. Купити в Україні — Booster Shop.',
        'meta_kw'    => 'The Glory of Team Rocket booster box, Team Rocket Pokemon JP, Pokémon sv10 booster box, Pokemon Team Rocket box',
        'price'      => '12000.0000',
        'mirror'     => 'product 56 PKM-MEGA-BOX',
        'ship'       => ['manufacturer' => 11, 'weight' => '300', 'length' => '150', 'width' => '150', 'height' => '50', 'length_class' => LENGTH_CLASS_ID_MM],
        'group'      => ATTR_GROUP_SEALED,
        'body'       => [
            '<h2>The Glory of Team Rocket — бустер бокс у тематиці Team Rocket</h2>',
            '<p>Бустер бокс Pokémon TCG The Glory of Team Rocket містить <strong>30 японських бустерів по 5 випадкових карт</strong>. Тематичний реліз вийшов 18 квітня 2025 року й побудований навколо Team Rocket та їхніх покемонів.</p>',
            '<p>На офіційній сторінці серед ключових Pokémon ex показані Team Rocket\'s Mewtwo ex, Crobat ex, Moltres ex і Persian ex. Це опис пулу сету, а не обіцянка конкретної карти в одному боксі.</p>',
            '<p>Бокс продається новим і в заводському шрінку. Конкретні карти, рідкісності та хіти в окремому боксі не гарантуються.</p>',
        ],
        'faq'        => [
            ['Скільки бустерів у The Glory of Team Rocket booster box?', '30 бустерів; у кожному по 5 випадкових карт.'],
            ['Чи гарантується конкретна карта Team Rocket?', 'Ні. Конкретні карти в бустерах випадкові.'],
            ['Бокс продається в шрінку?', 'Так. За правилом магазину box-SKU продаються новими й у заводському шрінку.'],
        ],
        'attributes' => [
            13 => 'The Glory of Team Rocket',
            21 => 'Sealed Booster Box',
            15 => '5',
            16 => '30',
            18 => ORIGIN_BOX,
            20 => 'The Pokémon Company',
            14 => '2025',
        ],
    ],

    [
        'sku'        => 'PKM-JP-TGTR-BST',
        'slug'       => 'Pokemon-boosters-The-Glory-of-Team-Rocket',
        'categories' => [59, 61],
        'name'       => 'Бустер Pokémon TCG: The Glory of Team Rocket (Японське видання)',
        'meta_title' => 'Team Rocket бустер Pokémon TCG JP — sealed | Booster Shop',
        'meta_desc'  => 'Японський sealed бустер Pokémon TCG The Glory of Team Rocket: 5 карт, із заводського боксу. Купити в Україні.',
        'meta_kw'    => 'The Glory of Team Rocket booster, Team Rocket Pokemon JP, Pokémon sv10 booster, Pokemon Team Rocket pack',
        'price'      => '320.0000',
        'mirror'     => 'product 50 PKM-JP-MSYM-BST',
        'ship'       => ['manufacturer' => 11, 'weight' => '10', 'length' => '15', 'width' => '7', 'height' => '1', 'length_class' => LENGTH_CLASS_ID_CM],
        'group'      => ATTR_GROUP_SEALED,
        'body'       => [
            '<h2>The Glory of Team Rocket — один японський бустер тематичного релізу</h2>',
            '<p>Бустер Pokémon TCG The Glory of Team Rocket містить <strong>5 випадкових карт японського видання</strong>. Сет вийшов 18 квітня 2025 року й зосереджений на Team Rocket та їхніх покемонах.</p>',
            '<p>Офіційна презентація релізу показує Team Rocket\'s Mewtwo ex, Crobat ex, Moltres ex і Persian ex серед помітних карт сету. В окремому паку жодна з них не гарантується.</p>',
            '<p>Поштучні бустери для цього SKU отримуються з бустер боксів у заводському шрінку, які розкриваються під роздрібний продаж паків. Сам бустер лишається запечатаним. Конкретна карта або рідкість у ньому не гарантується.</p>',
        ],
        'faq'        => [
            ['Скільки карт у бустері The Glory of Team Rocket?', '5 випадкових карт.'],
            ['Це запечатаний бустер чи розсип?', 'Це запечатаний бустер. Для цього SKU паки дістаються з бустер боксів у шрінку, які розкриваються під поштучний продаж.'],
            ['Чи гарантується конкретна карта Team Rocket?', 'Ні. Конкретний вміст окремого бустера випадковий.'],
        ],
        'attributes' => [
            13 => 'The Glory of Team Rocket',
            21 => 'Sealed Booster Pack',
            15 => '5',
            18 => ORIGIN_PACK,
            19 => UNWEIGHED,
            20 => 'The Pokémon Company',
            14 => '2025',
        ],
    ],

    // --------------------------------------------------------- Yu-Gi-Oh! BEYOND THE BRAVE
    [
        'sku'        => 'YGO-JP-BETB-BBX',
        'slug'       => 'YuGiOh-booster-box-Beyond-the-Brave',
        'categories' => [66, 65],
        'name'       => 'Бустер бокс Yu-Gi-Oh! OCG: BEYOND THE BRAVE (Японське видання)',
        'meta_title' => 'BEYOND THE BRAVE бокс Yu-Gi-Oh! OCG JP | Booster Shop',
        'meta_desc'  => 'Японський sealed бокс Yu-Gi-Oh! OCG BEYOND THE BRAVE: 30 бустерів по 5 карт, 80 типів карт. Заводський шрінк. Купити в Україні.',
        'meta_kw'    => 'BEYOND THE BRAVE booster box, Yu-Gi-Oh OCG BETB box, BEYOND THE BRAVE JP, Konami BETB box',
        'price'      => '2500.0000',
        'mirror'     => 'product 102 YGO-JP-WPP5-BBX',
        'ship'       => ['manufacturer' => 13, 'weight' => '250', 'length' => '14', 'width' => '9', 'height' => '8', 'length_class' => LENGTH_CLASS_ID_CM],
        'group'      => ATTR_GROUP_SEALED,
        'body'       => [
            '<h2>BEYOND THE BRAVE — японський Yu-Gi-Oh! OCG box на 30 бустерів</h2>',
            '<p>Бустер бокс BEYOND THE BRAVE містить <strong>30 японських бустерів по 5 карт</strong>. Реліз вийшов 18 липня 2026 року й належить до базової лінійки Yu-Gi-Oh! OCG.</p>',
            '<p>Офіційний пул сету налічує 80 типів карт. Для частини Prismatic Secret Rare карт існує оформлення Overframe, а сам реліз поєднує кілька тем і підтримку старіших архетипів.</p>',
            '<p>Бокс продається новим і в заводському шрінку. Картка товару не обіцяє конкретну карту, рідкість, Overframe або інший визначений хіт у коробці.</p>',
        ],
        'faq'        => [
            ['Скільки бустерів у BEYOND THE BRAVE box?', '30 бустерів по 5 карт у кожному.'],
            ['Скільки типів карт у сеті?', '80 типів карт згідно з офіційною сторінкою Konami.'],
            ['Чи гарантується Overframe або конкретна рідкість?', 'Ні. Окремий бокс не гарантує конкретний хіт або визначену рідкість.'],
        ],
        // NOTE: attribute 24 «Додатковий вміст» is deliberately not written — see the header.
        'attributes' => [
            13 => 'BEYOND THE BRAVE',
            21 => 'Sealed Booster Box',
            15 => '5',
            16 => '30',
            18 => ORIGIN_BOX,
            20 => 'Konami',
            14 => '2026',
        ],
    ],

    [
        'sku'        => 'YGO-JP-BETB-BST',
        'slug'       => 'Yu-Gi-Oh-boosters-Beyond-the-Brave',
        'categories' => [66, 65],
        'name'       => 'Бустер Yu-Gi-Oh! OCG: BEYOND THE BRAVE (Японське видання)',
        'meta_title' => 'BEYOND THE BRAVE бустер Yu-Gi-Oh! OCG JP | Booster Shop',
        'meta_desc'  => 'Японський sealed бустер Yu-Gi-Oh! OCG BEYOND THE BRAVE: 5 карт, із заводського боксу. Сет на 80 типів карт. Купити в Україні.',
        'meta_kw'    => 'BEYOND THE BRAVE booster, Yu-Gi-Oh OCG BETB booster, BEYOND THE BRAVE JP, Konami BETB pack',
        'price'      => '75.0000',
        'mirror'     => 'product 83 YGO-JP-QCAC-BST',
        'ship'       => ['manufacturer' => 13, 'weight' => '10', 'length' => '14', 'width' => '7', 'height' => '1', 'length_class' => LENGTH_CLASS_ID_CM],
        'group'      => ATTR_GROUP_SEALED,
        'body'       => [
            '<h2>BEYOND THE BRAVE — один японський бустер із базового OCG-релізу</h2>',
            '<p>Бустер BEYOND THE BRAVE містить <strong>5 випадкових карт японського видання</strong>. Сет вийшов 18 липня 2026 року й належить до базової лінійки Yu-Gi-Oh! OCG.</p>',
            '<p>Офіційний пул сету налічує 80 типів карт. Для частини Prismatic Secret Rare карт існує оформлення Overframe, але це опис релізу, а не гарантія конкретної карти в паку.</p>',
            '<p>Поштучні бустери для цього SKU отримуються з бустер боксів у заводському шрінку, які розкриваються під роздрібний продаж паків. Сам бустер лишається запечатаним. Конкретна карта або рідкість у ньому не гарантується.</p>',
        ],
        'faq'        => [
            ['Скільки карт у бустері BEYOND THE BRAVE?', '5 карт.'],
            ['Чи гарантується Overframe або конкретна рідкість?', 'Ні. Overframe існує для частини карт відповідного типу рідкісності, але конкретний вміст окремого бустера не гарантується.'],
            ['Це запечатаний бустер чи розсип?', 'Це запечатаний бустер. Для цього SKU поштучні паки дістаються з бустер боксів у шрінку, які розкриваються під поштучний продаж.'],
        ],
        'attributes' => [
            13 => 'BEYOND THE BRAVE',
            21 => 'Sealed Booster Pack',
            15 => '5',
            18 => ORIGIN_PACK,
            19 => UNWEIGHED,
            20 => 'Konami',
            14 => '2026',
        ],
    ],
];

// --------------------------------------------------------------------------
// Shared helpers (house style)
// --------------------------------------------------------------------------

function bs_log(string $key, string $value = ''): void {
    echo $key . ($value === '' ? '' : '=' . $value) . PHP_EOL;
}
function bs_fail(string $message): void { throw new RuntimeException($message); }
function bs_path(string $base, string $part): string {
    return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part);
}
function bs_table(string $prefix, string $suffix): string {
    $table = $prefix . $suffix;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) bs_fail('Unsafe DB table name from DB_PREFIX');
    return $table;
}
function bs_quote(mysqli $db, string $value): string { return "'" . $db->real_escape_string($value) . "'"; }
function bs_table_exists(mysqli $db, string $table): bool {
    $r = $db->query('SHOW TABLES LIKE ' . bs_quote($db, $table));
    $ok = $r->num_rows === 1; $r->free(); return $ok;
}
function bs_columns(mysqli $db, string $table): array {
    $r = $db->query('SHOW COLUMNS FROM `' . $table . '`'); $columns = [];
    while ($row = $r->fetch_assoc()) $columns[(string) $row['Field']] = true;
    $r->free(); return $columns;
}
function bs_require_columns(array $columns, array $needed, string $table): void {
    foreach ($needed as $column) if (!isset($columns[$column])) bs_fail('Unexpected schema: ' . $table . '.' . $column . ' is missing');
}
function bs_lint_self(): void {
    if (!function_exists('exec')) bs_fail('PHP exec() is unavailable; cannot pass mandatory php -l gate');
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php'; $output = []; $code = 1;
    @exec(escapeshellarg($php) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) bs_fail('php -l gate failed: ' . implode(' ', $output));
    bs_log('php_l', 'ok');
}
function bs_connect(): mysqli {
    if (!extension_loaded('mysqli')) bs_fail('mysqli extension is not loaded');
    foreach (['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX'] as $constant) {
        if (!defined($constant)) bs_fail('Missing config constant: ' . $constant);
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, defined('DB_PORT') ? (int) DB_PORT : 3306);
    $db->set_charset('utf8mb4'); bs_log('db_connect', 'ok'); return $db;
}
function bs_stmt_rows(mysqli_stmt $stmt): array {
    $metadata = $stmt->result_metadata();
    if ($metadata === false) bs_fail('Cannot read SQL result metadata');
    $row = []; $refs = [];
    foreach ($metadata->fetch_fields() as $field) { $row[$field->name] = null; $refs[] = &$row[$field->name]; }
    if (!call_user_func_array([$stmt, 'bind_result'], $refs)) bs_fail('Cannot bind SQL result columns');
    $rows = [];
    while ($stmt->fetch()) { $copy = []; foreach ($row as $k => $v) $copy[$k] = $v; $rows[] = $copy; }
    $metadata->free();
    return $rows;
}
function bs_bind_guard(string $sql, string $types, array $params): void {
    $marks = substr_count($sql, '?');
    if ($marks !== strlen($types) || $marks !== count($params)) {
        bs_fail('Bind mismatch: ' . $marks . ' placeholders, ' . strlen($types) . ' type chars, ' . count($params) . ' params in: ' . $sql);
    }
}
function bs_select(mysqli $db, string $sql, string $types, array $params): array {
    bs_bind_guard($sql, $types, $params);
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $key => &$value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
    }
    $stmt->execute(); $rows = bs_stmt_rows($stmt); $stmt->close(); return $rows;
}
function bs_exec(mysqli $db, string $sql, string $types, array $params): int {
    bs_bind_guard($sql, $types, $params);
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $key => &$value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) bs_fail('Cannot bind query parameters');
    }
    $stmt->execute(); $affected = $stmt->affected_rows; $stmt->close(); return (int) $affected;
}
function bs_json_backup(string $dir, string $name, array $payload): void {
    $path = bs_path($dir, 'db/' . $name . '.json'); $parent = dirname($path);
    if (!is_dir($parent) && !mkdir($parent, 0755, true)) bs_fail('Cannot create DB backup directory');
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json) || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) bs_fail('Cannot write DB backup: ' . $name);
    bs_log('backup_db', $path);
}
function bs_self_delete(): void {
    @unlink(__FILE__);
    bs_log('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
}
function bs_encode_html(string $html): string {
    return htmlspecialchars($html, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}
function bs_faq_html(string $faqId, array $items): string {
    $nl  = "\r\n";
    $out = '<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="' . $faqId . '">' . $nl;
    $out .= '<h2 class="bs-faq-title">FAQ</h2>' . $nl;
    $i = 0;
    foreach ($items as $item) {
        $i++;
        $btn   = 'bs-faq-' . $faqId . '-button-' . $i;
        $panel = 'bs-faq-' . $faqId . '-panel-' . $i;
        $out .= $nl;
        $out .= '<div class="bs-faq-item">' . $nl;
        $out .= '<h3 class="bs-faq-question"><button aria-controls="' . $panel . '" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="' . $btn . '" type="button"><span>' . $item[0] . '</span></button></h3>' . $nl;
        $out .= '<div aria-labelledby="' . $btn . '" class="bs-faq-panel" hidden="" id="' . $panel . '" role="region">' . $nl;
        $out .= '<p>' . $item[1] . '</p>' . $nl;
        $out .= '</div>' . $nl;
        $out .= '</div>' . $nl;
    }
    $out .= $nl . '</section>';
    return $out;
}

// --------------------------------------------------------------------------
// Domain helpers
// --------------------------------------------------------------------------

function bs_faq_id(string $sku): string { return 'prod-' . strtolower($sku); }

function bs_description(array $product): string {
    $html = implode("\r\n\r\n", $product['body']) . "\r\n\r\n" . bs_faq_html(bs_faq_id($product['sku']), $product['faq']);
    return bs_encode_html($html);
}

function bs_product_id_by_model(mysqli $db, array $t, string $model): int {
    $rows = bs_select($db, 'SELECT product_id FROM `' . $t['product'] . '` WHERE model = ?', 's', [$model]);
    if (count($rows) > 1) bs_fail('Model «' . $model . '» matches ' . count($rows) . ' products — resolve before running');
    return $rows === [] ? 0 : (int) $rows[0]['product_id'];
}

function bs_create_product(mysqli $db, array $t, array $product, string $today): int {
    $sku  = $product['sku'];
    $ship = $product['ship'];

    bs_exec($db,
        'INSERT INTO `' . $t['product'] . '` ('
        . '`master_id`, `model`, `sku`, `upc`, `ean`, `jan`, `isbn`, `mpn`, `location`, `variant`, `override`,'
        . ' `quantity`, `stock_status_id`, `image`, `manufacturer_id`, `shipping`, `price`, `points`, `tax_class_id`,'
        . ' `date_available`, `weight`, `weight_class_id`, `length`, `width`, `height`, `length_class_id`,'
        . ' `subtract`, `minimum`, `rating`, `sort_order`, `status`, `date_added`, `date_modified`'
        . ') VALUES ('
        . '0, ?, ?, NULL, NULL, NULL, NULL, NULL, \'\', \'\', \'\','
        . ' ?, ?, \'\', ?, 1, ?, 0, ?,'
        . ' ?, ?, ?, ?, ?, ?, ?,'
        . ' 1, 1, 0, ?, ?, NOW(), NOW())',
        //  model sku | qty stock manuf price tax | date weight wcls len wid hei lcls | sort status
        'ssiiisississsiii',
        [
            $sku, $sku,
            QUANTITY, STOCK_STATUS_ID, $ship['manufacturer'], $product['price'], TAX_CLASS_ID,
            $today, $ship['weight'], WEIGHT_CLASS_ID, $ship['length'], $ship['width'], $ship['height'], $ship['length_class'],
            SORT_ORDER, STATUS,
        ]);
    $productId = (int) $db->insert_id;
    if ($productId < 1) bs_fail('product insert returned no id for ' . $sku);

    bs_exec($db,
        'INSERT INTO `' . $t['product_description'] . '` (`product_id`, `language_id`, `name`, `description`, `tag`, `meta_title`, `meta_description`, `meta_keyword`)'
        . ' VALUES (?, ?, ?, ?, \'\', ?, ?, ?)',
        'iisssss',
        [$productId, LANGUAGE_ID, $product['name'], bs_description($product), $product['meta_title'], $product['meta_desc'], $product['meta_kw']]);

    bs_exec($db, 'INSERT INTO `' . $t['product_to_store'] . '` (`product_id`, `store_id`) VALUES (?, ?)', 'ii', [$productId, STORE_ID]);
    foreach ($product['categories'] as $categoryId) {
        bs_exec($db, 'INSERT INTO `' . $t['product_to_category'] . '` (`product_id`, `category_id`) VALUES (?, ?)', 'ii', [$productId, $categoryId]);
    }
    bs_exec($db, 'INSERT INTO `' . $t['product_code'] . '` (`product_id`, `code`, `value`) VALUES (?, \'SKU\', ?)', 'is', [$productId, $sku]);
    bs_exec($db,
        'INSERT INTO `' . $t['seo_url'] . '` (`store_id`, `language_id`, `key`, `value`, `keyword`, `sort_order`) VALUES (?, ?, \'product_id\', ?, ?, 0)',
        'iiss', [STORE_ID, LANGUAGE_ID, (string) $productId, $product['slug']]);

    foreach (bs_expected_attributes($product) as $attributeId => $text) {
        bs_exec($db,
            'INSERT INTO `' . $t['product_attribute'] . '` (`product_id`, `attribute_id`, `language_id`, `text`) VALUES (?, ?, ?, ?)',
            'iiis', [$productId, $attributeId, LANGUAGE_ID, $text]);
    }

    return $productId;
}

function bs_expected_attributes(array $product): array {
    $values = $product['group'] === ATTR_GROUP_SEALED
        ? $product['attributes'] + SEALED_COMMON
        : $product['attributes'];
    ksort($values);
    return $values;
}

function bs_verify_product(mysqli $db, array $t, int $productId, array $product): void {
    $rows = bs_select($db,
        'SELECT model, sku, upc, ean, jan, isbn, mpn, manufacturer_id, status, quantity, stock_status_id, rating,'
        . ' weight_class_id, length_class_id, sort_order, image, price'
        . ' FROM `' . $t['product'] . '` WHERE product_id = ?', 'i', [$productId]);
    if ($rows === []) bs_fail('Product ' . $productId . ' vanished after insert — rolling back');
    $row = $rows[0];

    foreach (['upc', 'ean', 'jan', 'isbn', 'mpn'] as $identifier) {
        if ($row[$identifier] !== null && $row[$identifier] !== '') {
            bs_fail('CONTENT-005 guard: ' . $product['sku'] . ' has a non-empty ' . $identifier . ' — rolling back');
        }
    }
    if ((string) $row['model'] !== $product['sku'] || (string) $row['sku'] !== $product['sku']) bs_fail('SKU mismatch on ' . $product['sku'] . ' — rolling back');
    if ((int) $row['manufacturer_id'] !== (int) $product['ship']['manufacturer']) bs_fail('Manufacturer mismatch on ' . $product['sku'] . ' — rolling back');
    if ((int) $row['status'] !== STATUS)                   bs_fail($product['sku'] . ' is not status=' . STATUS . ' — rolling back');
    if ((int) $row['quantity'] !== QUANTITY)               bs_fail($product['sku'] . ' quantity is not ' . QUANTITY . ' — rolling back');
    if ((int) $row['stock_status_id'] !== STOCK_STATUS_ID) bs_fail($product['sku'] . ' stock_status_id is not ' . STOCK_STATUS_ID . ' — rolling back');
    if ((int) $row['rating'] !== 0)                        bs_fail($product['sku'] . ' has a non-zero rating — rolling back');
    if ((string) $row['image'] !== '')                     bs_fail($product['sku'] . ' has a non-empty image — rolling back');
    if ((int) $row['sort_order'] !== (int) SORT_ORDER) bs_fail($product['sku'] . ' sort_order mismatch — rolling back');
    if ((int) $row['weight_class_id'] !== WEIGHT_CLASS_ID) bs_fail('Weight class mismatch on ' . $product['sku'] . ' — rolling back');
    if ((int) $row['length_class_id'] !== (int) $product['ship']['length_class']) bs_fail('Length class mismatch on ' . $product['sku'] . ' — rolling back');
    if (abs((float) $row['price'] - (float) $product['price']) > 0.00001) bs_fail('Price mismatch on ' . $product['sku'] . ' — rolling back');

    $haveCat = [];
    foreach (bs_select($db, 'SELECT category_id FROM `' . $t['product_to_category'] . '` WHERE product_id = ? ORDER BY category_id', 'i', [$productId]) as $r) {
        $haveCat[] = (int) $r['category_id'];
    }
    $wantCat = $product['categories'];
    sort($wantCat);
    if ($haveCat !== $wantCat) {
        bs_fail($product['sku'] . ' is in categories [' . implode(',', $haveCat) . '], expected exactly [' . implode(',', $wantCat) . '] — rolling back');
    }

    $wantAttrs = bs_expected_attributes($product);
    $haveAttrs = [];
    foreach (bs_select($db,
        'SELECT pa.attribute_id AS attribute_id, pa.text AS text, a.attribute_group_id AS grp'
        . ' FROM `' . $t['product_attribute'] . '` pa'
        . ' JOIN `' . $t['attribute'] . '` a ON a.attribute_id = pa.attribute_id'
        . ' WHERE pa.product_id = ? AND pa.language_id = ? ORDER BY pa.attribute_id', 'ii', [$productId, LANGUAGE_ID]) as $r) {
        if ((int) $r['grp'] !== (int) $product['group']) {
            bs_fail($product['sku'] . ' has attribute ' . $r['attribute_id'] . ' from group ' . $r['grp'] . ', expected group ' . $product['group'] . ' — rolling back');
        }
        $haveAttrs[(int) $r['attribute_id']] = (string) $r['text'];
    }
    if ($haveAttrs !== $wantAttrs) {
        bs_fail($product['sku'] . ' attribute set mismatch. Got ids [' . implode(',', array_keys($haveAttrs))
            . '], expected [' . implode(',', array_keys($wantAttrs)) . '] — rolling back');
    }
    // Owner decisions, asserted explicitly rather than left to the diff.
    if (isset($haveAttrs[29])) bs_fail($product['sku'] . ' must not have attribute 29 «Матеріал» — rolling back');
    if (isset($haveAttrs[24])) bs_fail($product['sku'] . ' must not have attribute 24 «Додатковий вміст» — rolling back');

    $seo = bs_select($db, 'SELECT keyword FROM `' . $t['seo_url'] . '` WHERE `key` = \'product_id\' AND `value` = ?', 's', [(string) $productId]);
    if (count($seo) !== 1 || (string) $seo[0]['keyword'] !== $product['slug']) bs_fail('SEO URL mismatch on ' . $product['sku'] . ' — rolling back');

    $code = bs_select($db, 'SELECT code, value FROM `' . $t['product_code'] . '` WHERE product_id = ?', 'i', [$productId]);
    if (count($code) !== 1 || (string) $code[0]['code'] !== 'SKU' || (string) $code[0]['value'] !== $product['sku']) {
        bs_fail('product_code mismatch on ' . $product['sku'] . ' — rolling back');
    }

    $store = bs_select($db, 'SELECT store_id FROM `' . $t['product_to_store'] . '` WHERE product_id = ?', 'i', [$productId]);
    if (count($store) !== 1 || (int) $store[0]['store_id'] !== STORE_ID) bs_fail('product_to_store mismatch on ' . $product['sku'] . ' — rolling back');

    $desc = bs_select($db, 'SELECT description, name, meta_title FROM `' . $t['product_description'] . '` WHERE product_id = ? AND language_id = ?', 'ii', [$productId, LANGUAGE_ID]);
    if (count($desc) !== 1) bs_fail('product_description row count mismatch on ' . $product['sku'] . ' — rolling back');
    $stored = (string) $desc[0]['description'];
    if ($stored !== bs_description($product))                                   bs_fail('description readback mismatch on ' . $product['sku'] . ' — rolling back');
    if (substr_count($stored, bs_encode_html('<h2>')) !== 1)                    bs_fail($product['sku'] . ': body must have exactly one <h2> — rolling back');
    if (substr_count($stored, bs_encode_html('<strong>')) !== 1)                bs_fail($product['sku'] . ': body must have exactly one <strong> — rolling back');
    if (substr_count($stored, bs_encode_html('<div class="bs-faq-item">')) !== 3) bs_fail($product['sku'] . ': FAQ must have 3 items — rolling back');
    if (strpos($stored, bs_encode_html('</section>')) === false)                bs_fail($product['sku'] . ': FAQ section is not closed — rolling back');
}

function bs_sql_ids(array $ids): string { return implode(',', array_map('intval', $ids)); }

// --------------------------------------------------------------------------

function bs_run(): void {
    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') bs_fail('Cannot determine cwd');
    bs_log('patch', PATCH_NAME); bs_log('cwd', $cwd); bs_log('time', date('c'));

    $config = bs_path($cwd, 'config.php');
    if (!is_file($config)) bs_fail('config.php not found. Run this patch from ~/public_html.');

    bs_lint_self();
    require_once $config;

    // ---- static content guards, before the database is touched ------------
    if (count(PRODUCTS) !== 9) bs_fail('Expected 9 products, found ' . count(PRODUCTS));
    $skus  = array_column(PRODUCTS, 'sku');
    $slugs = array_column(PRODUCTS, 'slug');
    if (count(array_unique($skus)) !== 9)  bs_fail('Duplicate SKU in PRODUCTS');
    if (count(array_unique($slugs)) !== 9) bs_fail('Duplicate slug in PRODUCTS');

    foreach (PRODUCTS as $product) {
        foreach (['name' => 255, 'meta_title' => 255, 'meta_desc' => 255, 'meta_kw' => 255] as $field => $limit) {
            if (mb_strlen($product[$field], 'UTF-8') > $limit) bs_fail('Field ' . $field . ' of ' . $product['sku'] . ' exceeds ' . $limit . ' chars');
        }
        if (mb_strlen($product['sku'], 'UTF-8') > 64) bs_fail('SKU too long: ' . $product['sku']);
        if (!preg_match('/^[A-Za-z0-9\-]+$/', $product['slug'])) bs_fail('Slug of ' . $product['sku'] . ' has characters outside [A-Za-z0-9-]');
        if (count($product['body']) !== 4) bs_fail('Card ' . $product['sku'] . ' must be <h2> + 3 paragraphs, has ' . count($product['body']));
        if (count($product['faq']) !== 3)  bs_fail('Card ' . $product['sku'] . ' must have 3 FAQ items, has ' . count($product['faq']));
        $body = implode(' ', $product['body']);
        if (substr_count($body, '<h2>') !== 1)      bs_fail('Card ' . $product['sku'] . ' must have exactly one <h2>');
        if (substr_count($body, '<strong>') !== 1)  bs_fail('Card ' . $product['sku'] . ' must have exactly one <strong>');
        if (substr_count($body, '</strong>') !== 1) bs_fail('Card ' . $product['sku'] . ' has an unbalanced <strong>');
        if (strpos($body, "\u{2019}") !== false)    bs_fail('Card ' . $product['sku'] . ' uses U+2019; this wave is ASCII apostrophes only');
        if (count($product['categories']) !== 2)    bs_fail('Card ' . $product['sku'] . ' must sit in a subcategory AND its parent');
        foreach ($product['categories'] as $categoryId) {
            if (!isset(TARGET_CATEGORIES[$categoryId])) bs_fail('Card ' . $product['sku'] . ' references unlisted category ' . $categoryId);
        }
        $expected = bs_expected_attributes($product);
        foreach (array_keys($expected) as $attributeId) {
            if (!isset(REQUIRED_ATTRIBUTES[$attributeId])) bs_fail('Card ' . $product['sku'] . ' uses attribute ' . $attributeId . ', which the patch does not verify');
        }
        if (isset($expected[24])) bs_fail('Card ' . $product['sku'] . ' must not carry attribute 24 (owner decision)');
        if (isset($expected[29])) bs_fail('Card ' . $product['sku'] . ' must not carry attribute 29 (owner decision)');
        $count = count($expected);
        $want  = $product['group'] === ATTR_GROUP_SEALED ? 9 : 5;
        if ($count !== $want) bs_fail('Card ' . $product['sku'] . ' has ' . $count . ' attributes, expected ' . $want);
        // A box SKU carries «Кількість бустерів у боксі» and no «Зважування»; a pack is the mirror image.
        $isBox = ($product['attributes'][21] ?? '') === 'Sealed Booster Box';
        if ($product['group'] === ATTR_GROUP_SEALED) {
            if ($isBox && (!isset($expected[16]) || isset($expected[19]))) bs_fail('Box SKU ' . $product['sku'] . ' must have attribute 16 and not 19');
            if (!$isBox && (isset($expected[16]) || !isset($expected[19]))) bs_fail('Pack SKU ' . $product['sku'] . ' must have attribute 19 and not 16');
        }
    }
    bs_log('content_guards', 'ok — 9 cards, one <h2> + one <strong> + 3 FAQ each, 9/5 attributes, ASCII apostrophes');

    $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His'));
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) bs_fail('Cannot create backup directory');
    bs_log('backup_dir', $backupDir);

    $db = bs_connect();
    try {
        $prefix = (string) DB_PREFIX;
        $t = [
            'product'               => bs_table($prefix, 'product'),
            'product_description'   => bs_table($prefix, 'product_description'),
            'product_to_store'      => bs_table($prefix, 'product_to_store'),
            'product_to_category'   => bs_table($prefix, 'product_to_category'),
            'product_code'          => bs_table($prefix, 'product_code'),
            'product_attribute'     => bs_table($prefix, 'product_attribute'),
            'seo_url'               => bs_table($prefix, 'seo_url'),
            'attribute'             => bs_table($prefix, 'attribute'),
            'attribute_description' => bs_table($prefix, 'attribute_description'),
            'category'              => bs_table($prefix, 'category'),
            'category_description'  => bs_table($prefix, 'category_description'),
            'manufacturer'          => bs_table($prefix, 'manufacturer'),
            'stock_status'          => bs_table($prefix, 'stock_status'),
            'language'              => bs_table($prefix, 'language'),
            'review'                => bs_table($prefix, 'review'),
        ];
        foreach ($t as $table) if (!bs_table_exists($db, $table)) bs_fail('Required table not found: ' . $table);
        bs_require_columns(bs_columns($db, $t['product']), ['product_id','model','sku','upc','ean','jan','isbn','mpn','quantity','stock_status_id','manufacturer_id','price','weight','weight_class_id','length','width','height','length_class_id','rating','sort_order','status'], $t['product']);
        bs_require_columns(bs_columns($db, $t['product_code']), ['product_id','code','value'], $t['product_code']);

        // ---- preconditions -------------------------------------------------
        $language = bs_select($db, 'SELECT code, status FROM `' . $t['language'] . '` WHERE language_id = ?', 'i', [LANGUAGE_ID]);
        if ($language === [] || (int) $language[0]['status'] !== 1) bs_fail('language_id ' . LANGUAGE_ID . ' is missing or disabled — stopping');
        bs_log('language_verified', LANGUAGE_ID . ' «' . $language[0]['code'] . '»');

        $stores = bs_select($db, 'SELECT DISTINCT store_id FROM `' . $t['product_to_store'] . '`', '', []);
        foreach ($stores as $row) {
            if ((int) $row['store_id'] !== STORE_ID) bs_fail('product_to_store carries store_id ' . $row['store_id'] . '; this patch assumes only ' . STORE_ID . ' — stopping');
        }
        bs_log('store_verified', 'every existing product_to_store row is store_id ' . STORE_ID);

        foreach (REQUIRED_MANUFACTURERS as $manufacturerId => $name) {
            $rows = bs_select($db, 'SELECT name FROM `' . $t['manufacturer'] . '` WHERE manufacturer_id = ?', 'i', [$manufacturerId]);
            if ($rows === [] || (string) $rows[0]['name'] !== $name) bs_fail('manufacturer_id ' . $manufacturerId . ' is not «' . $name . '» — stopping');
        }
        bs_log('manufacturers_verified', implode(', ', array_map(static fn ($id, $n) => $id . '=' . $n, array_keys(REQUIRED_MANUFACTURERS), REQUIRED_MANUFACTURERS)));

        $stock = bs_select($db, 'SELECT name FROM `' . $t['stock_status'] . '` WHERE stock_status_id = ? AND language_id = ?', 'ii', [STOCK_STATUS_ID, LANGUAGE_ID]);
        if ($stock === [] || (string) $stock[0]['name'] !== STOCK_STATUS) bs_fail('stock_status_id ' . STOCK_STATUS_ID . ' is not «' . STOCK_STATUS . '» — stopping');
        bs_log('stock_status_verified', STOCK_STATUS_ID . ' «' . STOCK_STATUS . '»');

        // Categories by ID — handoff §3.3. Never by keyword: category 61 is ambiguous.
        foreach (TARGET_CATEGORIES as $categoryId => $meta) {
            $rows = bs_select($db,
                'SELECT d.name AS name, c.parent_id AS parent_id, c.status AS status FROM `' . $t['category'] . '` c'
                . ' JOIN `' . $t['category_description'] . '` d ON d.category_id = c.category_id AND d.language_id = ?'
                . ' WHERE c.category_id = ?', 'ii', [LANGUAGE_ID, $categoryId]);
            if ($rows === []) bs_fail('Category ' . $categoryId . ' does not exist — stopping');
            if ((string) $rows[0]['name'] !== $meta['name']) {
                bs_fail('Category ' . $categoryId . ' is named «' . $rows[0]['name'] . '», expected «' . $meta['name'] . '» — stopping');
            }
            if ((int) $rows[0]['parent_id'] !== $meta['parent']) {
                bs_fail('Category ' . $categoryId . ' has parent ' . $rows[0]['parent_id'] . ', expected ' . $meta['parent'] . ' — stopping');
            }
            bs_log('category_verified', $categoryId . ' «' . $meta['name'] . '» parent=' . $meta['parent'] . ' status=' . $rows[0]['status']);
        }

        foreach (REQUIRED_ATTRIBUTES as $attributeId => $name) {
            $rows = bs_select($db,
                'SELECT d.name AS name, a.attribute_group_id AS grp FROM `' . $t['attribute'] . '` a'
                . ' JOIN `' . $t['attribute_description'] . '` d ON d.attribute_id = a.attribute_id AND d.language_id = ?'
                . ' WHERE a.attribute_id = ?', 'ii', [LANGUAGE_ID, $attributeId]);
            if ($rows === []) bs_fail('Attribute ' . $attributeId . ' does not exist — stopping');
            if ((string) $rows[0]['name'] !== $name) bs_fail('Attribute ' . $attributeId . ' is named «' . $rows[0]['name'] . '», expected «' . $name . '» — stopping');
            $wantGroup = $attributeId <= 21 ? ATTR_GROUP_SEALED : ATTR_GROUP_ACCESSORY;
            if ((int) $rows[0]['grp'] !== $wantGroup) bs_fail('Attribute ' . $attributeId . ' is in group ' . $rows[0]['grp'] . ', expected ' . $wantGroup . ' — stopping');
        }
        bs_log('attributes_verified', count(REQUIRED_ATTRIBUTES) . ' ids checked by id, name and group');

        // ---- idempotency + clash preflight ---------------------------------
        $pending = [];
        $already = [];
        foreach (PRODUCTS as $product) {
            $existingId = bs_product_id_by_model($db, $t, $product['sku']);
            if ($existingId > 0) { $already[$product['sku']] = $existingId; continue; }
            $pending[] = $product;
        }
        if ($pending === []) {
            bs_log('already_applied', 'yes');
            bs_log('existing_product_ids', json_encode($already, JSON_UNESCAPED_UNICODE));
            bs_log('done', 'ok');
            bs_self_delete();
            return;
        }
        if ($already !== []) bs_log('partially_applied', json_encode($already, JSON_UNESCAPED_UNICODE));

        foreach ($pending as $product) {
            $codeClash = bs_select($db, 'SELECT product_id FROM `' . $t['product_code'] . '` WHERE `code` = \'SKU\' AND `value` = ?', 's', [$product['sku']]);
            if ($codeClash !== []) bs_fail('SKU «' . $product['sku'] . '» already in product_code (product ' . $codeClash[0]['product_id'] . ') — stopping');
            $slugClash = bs_select($db, 'SELECT seo_url_id, `key`, `value` FROM `' . $t['seo_url'] . '` WHERE (`keyword` = ? OR `keyword` LIKE ?) AND store_id = ?',
                'ssi', [$product['slug'], '%/' . $product['slug'], STORE_ID]);
            if ($slugClash !== []) {
                bs_fail('SEO slug «' . $product['slug'] . '» already resolves (seo_url_id=' . $slugClash[0]['seo_url_id']
                    . ', key=' . $slugClash[0]['key'] . ', value=' . $slugClash[0]['value'] . ') — stopping');
            }
        }
        bs_log('preflight', count($pending) . ' SKUs and slugs are free');

        $reviewsBefore  = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['review'] . '`', '', []);
        $productsBefore = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product'] . '`', '', []);
        $seoBefore      = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['seo_url'] . '`', '', []);
        $dupBefore      = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['seo_url'] . '` WHERE `key` = \'path\' AND `value` = \'59_61\'', '', []);
        if ((int) $dupBefore[0]['c'] !== 2) {
            bs_log('note', 'category 61 has ' . $dupBefore[0]['c'] . ' path rows, expected the known 2 — not fatal, WP3 territory');
        }

        bs_json_backup($backupDir, 'before', [
            'note'                => 'State before WP2. Rollback is DELETE only — see rollback.sql and the patch header.',
            'product_row_count'   => (int) $productsBefore[0]['c'],
            'seo_url_row_count'   => (int) $seoBefore[0]['c'],
            'review_row_count'    => (int) $reviewsBefore[0]['c'],
            'category_61_path_rows' => (int) $dupBefore[0]['c'],
            'skus_to_create'      => array_column($pending, 'sku'),
            'already_present'     => $already,
            'mirrored_from'       => array_combine(array_column(PRODUCTS, 'sku'), array_column(PRODUCTS, 'mirror')),
        ]);

        $created = [];
        $today   = date('Y-m-d');

        $db->begin_transaction();
        try {
            foreach ($pending as $product) {
                $productId = bs_create_product($db, $t, $product, $today);
                bs_verify_product($db, $t, $productId, $product);
                $created[$product['sku']] = $productId;
                bs_log('created_product', str_pad($product['sku'], 16) . ' id=' . $productId
                    . '  cats=' . implode('+', $product['categories'])
                    . '  attrs=' . count(bs_expected_attributes($product))
                    . '  /product/' . $product['slug']);
            }

            $reviewsAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['review'] . '`', '', []);
            if ((int) $reviewsAfter[0]['c'] !== (int) $reviewsBefore[0]['c']) bs_fail('Review row count changed — rolling back');
            $productsAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product'] . '`', '', []);
            if ((int) $productsAfter[0]['c'] !== (int) $productsBefore[0]['c'] + count($pending)) bs_fail('Product row count moved unexpectedly — rolling back');
            $seoAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['seo_url'] . '`', '', []);
            if ((int) $seoAfter[0]['c'] !== (int) $seoBefore[0]['c'] + count($pending)) bs_fail('seo_url row count moved unexpectedly — rolling back');
            $dupAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['seo_url'] . '` WHERE `key` = \'path\' AND `value` = \'59_61\'', '', []);
            if ((int) $dupAfter[0]['c'] !== (int) $dupBefore[0]['c']) bs_fail('The category 61 path rows changed — this patch must not touch them — rolling back');

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        $ids = bs_sql_ids(array_values($created));
        $sql  = "-- Rollback for " . PATCH_NAME . "\n-- Generated " . date('c') . " with the real product ids.\n";
        $sql .= "-- Deletes exactly the nine rows this patch created and nothing else.\n\n";
        $sql .= "START TRANSACTION;\n";
        $sql .= 'DELETE FROM `' . $t['seo_url'] . "` WHERE `key` = 'product_id' AND `value` IN ('" . implode("','", array_map('strval', array_values($created))) . "');\n";
        foreach (['product_attribute', 'product_to_category', 'product_to_store', 'product_code', 'product_description', 'product'] as $table) {
            $sql .= 'DELETE FROM `' . $t[$table] . '` WHERE `product_id` IN (' . $ids . ");\n";
        }
        $sql .= "COMMIT;\n";
        $rollbackPath = bs_path($backupDir, 'db/rollback.sql');
        if (file_put_contents($rollbackPath, $sql, LOCK_EX) === false) bs_fail('Cannot write rollback.sql');
        bs_log('rollback_sql', $rollbackPath);

        bs_json_backup($backupDir, 'created_ids', [
            'note'                => 'Rollback: run db/rollback.sql, or delete in the order given in the patch header.',
            'created_product_ids' => $created,
            'categories'          => array_combine(array_column(PRODUCTS, 'sku'), array_map(static fn ($p) => implode('+', $p['categories']), PRODUCTS)),
            'seo_urls'            => array_combine(array_column(PRODUCTS, 'sku'), array_column(PRODUCTS, 'slug')),
            'attribute_rows'      => array_combine(array_column(PRODUCTS, 'sku'), array_map(static fn ($p) => count(bs_expected_attributes($p)), PRODUCTS)),
        ]);

        foreach (TARGET_CATEGORIES as $categoryId => $meta) {
            $count = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product_to_category'] . '` WHERE category_id = ?', 'i', [$categoryId]);
            bs_log('category_product_count', $categoryId . ' «' . $meta['name'] . '» = ' . $count[0]['c']);
        }

        bs_log('created_products', (string) count($created));
        bs_log('attribute_rows_written', (string) array_sum(array_map(static fn ($p) => count(bs_expected_attributes($p)), $pending)));
        bs_log('visibility', 'all nine created with status=0 — NOT visible, by design');
        bs_log('images', 'all nine have an empty image field — the owner adds photos');
        bs_log('acc_007_400_note', 'five attributes, no «Матеріал» (29) — owner decision 2026-08-21; the handoff QA line says six, which is one too many');
        bs_log('betb_note', 'YGO-JP-BETB-BBX has no «Додатковий вміст» (24) — no +1 Expansion Pack until first-print is confirmed');
        bs_log('qcac_note', 'YGO-JP-QCAC-BBX shares the set with existing product 83 (the pack). Product 83 was not touched; check both cards agree before enabling');
        bs_log('crm_note', 'CRM-005: all nine SKUs must exist in CRM before the owner makes them visible');
        bs_log('next', 'clear OpenCart cache + compiled templates, then run §7 Owner QA of the handoff');
        bs_log('done', 'ok');
        bs_self_delete();
    } finally {
        $db->close();
    }
}

try {
    bs_run();
} catch (Throwable $e) {
    bs_log('error', $e->getMessage());
    bs_log('done', 'failed');
    exit(1);
}
