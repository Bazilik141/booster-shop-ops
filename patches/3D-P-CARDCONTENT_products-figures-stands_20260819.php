<?php
declare(strict_types=1);

/*
 * 3D-P-CARDCONTENT — work package 4 of 4: 7 figures + 7 card stands.
 * REV 2, 2026-08-19 — dual category assignment + sort_order 8.
 * Supersedes 3D-P-CARDCONTENT_products-figures-stands_20260818.php, never run.
 *
 * SOURCE OF TRUTH
 *   handoffs/handoff_3D-P-CARDCONTENT_figures-wave_20260816.md   (7 SKU)
 *   handoffs/handoff_3D-P-CARDCONTENT_stands-wave_20260816.md    (7 SKU)
 *   Texts in §4 of both are FINAL and reproduced verbatim below.
 *   Preflight: diagnostics/3D-P-002_3D-P-CARDCONTENT_db-preflight_20260818.md
 *
 * REQUIRES, IN ORDER
 *   WP1  3D-P-002_catalog-subcategories_20260818.php   (both stages)
 *   WP2  3D-P-CARDCONTENT_attributes-group10_20260818.php
 *   Every category and attribute id is resolved at runtime and asserted.
 *
 * WHAT THIS DOES
 *   Creates 14 products across THREE subcategories:
 *     Pokémon -> Фігурки та декор    FIG-ONIX-500, FIG-GEOD-511, FIG-MEW-100, FIG-PKBL-600
 *     One Piece -> Фігурки та декор  FIG-LUFFY-500, FIG-LUFFY-400, FIG-LUFFY-410
 *     Аксесуари -> Підставки та декор  all seven ACC-3D-PKM-*
 *   Per product: product, product_description, product_to_store,
 *   product_to_category (TWO rows — subcategory + parent), product_code (SKU),
 *   seo_url, and 13 product_attribute rows.
 *   Total 14 products, 28 category rows, 182 attribute rows.
 *
 * REV 2 CHANGE — "one product = one category" IS RESCINDED
 *   Owner decision 2026-08-19 (handoff_3D-P-002 §4.3). Assignments become:
 *     FIG-ONIX-500, FIG-GEOD-511, FIG-MEW-100, FIG-PKBL-600   -> 73 + 59
 *     FIG-LUFFY-500, FIG-LUFFY-400, FIG-LUFFY-410             -> 74 + 60
 *     ACC-3D-PKM-110/120/130/200/300/700/710                  -> 72 + 70
 *   plus product.sort_order = 8 on all fourteen. Each parent id is read from
 *   the subcategory row the patch already asserts against TARGET_CATEGORIES.
 *   Everything else is byte-identical to the reviewed 20260818 version.
 *
 * ⚠ WHAT sort_order = 8 ACTUALLY DOES ON THIS INSTALL — VERIFIED, NOT ASSUMED
 *   The brief asked to confirm the default sort is p.sort_order ASC before
 *   shipping. It is the default, but it is NOT the primary key of the sort.
 *
 *   catalog/controller/product/category.php:32  -> $sort = 'p.sort_order'
 *   catalog/controller/product/category.php:38  -> $order = 'ASC'
 *   catalog/model/catalog/product.php:285 adds a stock tier AHEAD of it —
 *   this install is NOT stock OpenCart here:
 *
 *     $stock_priority = (CASE
 *        WHEN (p.quantity > 0 OR p.subtract = 0) THEN 1
 *        WHEN (p.subtract = 1 AND p.quantity <= 0 AND p.stock_status_id = 8) THEN 2
 *        ELSE 3 END)
 *     ORDER BY $stock_priority ASC, p.sort_order ASC, LCASE(pd.name) ASC
 *
 *   These products are quantity = 0, subtract = 1, stock_status_id = 8 -> TIER 2,
 *   so they sit after every in-stock product regardless of sort_order. The 8
 *   still does real work: existing sort_order values are 0..3 in category 59,
 *   0..2 in 60 and 1 in 70, so 8 places the 3D items last within their tier and
 *   last again if they later move to tier 1 by going in stock. Correct value —
 *   but the tier, not the 8, decides position while they are preorder.
 *   No OCMOD or theme override of this model file exists in the backup.
 *   Full evidence: diagnostics/3D-P-CARDCONTENT_figures-stands_report_20260819.md
 *
 *   Third sort key is LCASE(pd.name) ASC, so the 3D products order
 *   ALPHABETICALLY among themselves — not "neutral", but deterministic.
 *
 * TWO DIFFERENT ATTRIBUTE SETS — deliberately not one set for both waves
 *   figures: 13 rows including «Призначення»; NO «Матеріал фурнітури», NO «Сумісність»
 *   stands : 13 rows including «Сумісність»;  NO «Матеріал фурнітури», NO «Призначення»
 *   «Магніти» is not used anywhere. The patch asserts each product ends up with
 *   exactly its own set and nothing else.
 *
 * RENUMBERED SKUs — the retired numbers must not appear anywhere
 *   FIG-PKBL-600   (was FIG-PKBL-100)    canon addendum ред. 2
 *   ACC-3D-PKM-700 (was ACC-3D-PKM-140)  canon addendum ред. 3
 *   ACC-3D-PKM-710 (was ACC-3D-PKM-210)  canon addendum ред. 3
 *   The grader is BGS (Beckett), never BGC. The patch refuses to run if any
 *   retired token or «BGC» appears in its own content, and warns if a retired
 *   SKU is found in the database.
 *
 * ⚠ TWO PLACES WHERE THE WAVE HANDOFF OVERRIDES THE SKU CANON — handoff wins
 *   1. plans/3D-P_sku-naming-convention_20260807.md (ред. 2) still names
 *      FIG-PKBL-600 «Фігурка-клікер Покебол, стояча». The figures handoff §1.1
 *      removed «, стояча» on purpose: there is no other pokéball figure, so the
 *      variant token distinguishes nothing and would cost a 301 once options
 *      arrive. Name used here: «Фігурка-клікер Покебол (Pokémon) — 3D-друк».
 *   2. The canon (ред. 3) still names ACC-3D-PKM-710 «…для грейджених карток PSA».
 *      The stands handoff §1.1 removed the PSA token from names and URLs for all
 *      three slab stands, because the deferred BGS/CGC/SGC versions return as
 *      OPTIONS on these same pages. PSA is kept in Meta Title, description,
 *      keywords, the «Сумісність» attribute and the FAQ.
 *   Both are the later, more specific owner decision. Flagged in the report.
 *
 * NOT CREATED — deferred, they come back as product options, not pages
 *   FIG-ONIX-200, FIG-ONIX-501, FIG-GEOD-500, FIG-GEOD-501, FIG-GEOD-510,
 *   ACC-3D-PKM-201, ACC-3D-PKM-202, ACC-3D-PKM-711, ACC-3D-PKM-712,
 *   BR-CHARM-200, BR-PKBL-200, BR-DITTO-400, BR-OP-*.
 *   This is why the shipped names and URLs are variant-neutral. Do not
 *   "restore" PSA, M, L or a colour into a name or URL.
 *
 * CONTENT-005 PRECEDENT — NO TEMPLATE CLONING
 *   Every column is written explicitly; nothing is copied from an existing row.
 *   upc/ean/jan/isbn/mpn are NULL and asserted NULL after the write. No review,
 *   no rating, no image row, no related product, no discount, no option.
 *
 * MANUFACTURER
 *   Native field manufacturer_id = 17 (Booster Shop), asserted by name.
 *   Attribute id 20 «Виробник» (group 7) is NEVER used.
 *
 * COMMERCIAL FIELDS
 *   price      1.00 UAH placeholder for ALL FOURTEEN.
 *              ⚠ Unlike the keychains, neither wave handoff carries a price
 *              decision — figures §5.2 and stands §5.2 both say "задає власник".
 *              The patch reuses the keychain placeholder so the rows are
 *              consistent and obviously provisional. NOT a price decision.
 *              Flagged in the report; the owner sets real prices in admin.
 *   weight     actual mass rounded UP to a multiple of 50 g (owner's rule).
 *              Deliberately different from the «Маса» attribute, which is the
 *              plastic mass shown to the buyer.
 *   dimensions figures §5.2 / stands §5.2 tables, in CENTIMETRES.
 *              ⚠ Both handoffs mark these as "потребують підтвердження власника".
 *              They are entered as given; confirming them is an owner step.
 *   classes    length_class_id = 1 (cm), weight_class_id = 2 (g) — match
 *              config_length_class_id / config_weight_class_id, verified.
 *
 * VISIBILITY / STOCK — same as WP3
 *   status = 0 (not visible). quantity = 0, stock_status_id = 8
 *   («Передзамовлення») is an assumption: both handoffs defer stock to the 3D
 *   preorder task and give no value. Inert while status = 0.
 *
 * ⚠ IMAGE ALT TEXT IS NOT SETTABLE HERE — BLOCKED, NOT SKIPPED
 *   product.twig renders alt="{{ heading_title }}"; stock OpenCart 4 has no
 *   per-product alt field. The agreed alt texts are preserved verbatim in
 *   _patch_backups/<patch>-<ts>/db/alt_texts.json. Setting them needs a theme
 *   change — separate work package.
 *
 * INTERNAL LINKS — every gate is CLOSED, all neighbour names stay PLAIN TEXT
 *   figures §4.9 (three Luffy items) and stands §4.9 (110/120/130/300/710) both
 *   allow anchors ONLY after the target pages are enabled and return 200. All
 *   targets are created by this very patch with status = 0, so no anchor is
 *   written. The pending anchor map is saved to
 *   _patch_backups/<patch>-<ts>/db/pending_anchors.json for the later pass.
 *
 * ROLLBACK
 *   Actual ids in _patch_backups/<patch>-<ts>/db/created_ids.json. Delete in
 *   this order:
 *     DELETE FROM ocp5_product_attribute   WHERE product_id IN (<created_product_ids>);
 *     DELETE FROM ocp5_seo_url             WHERE `key` = 'product_id' AND `value` IN (<created_product_ids as strings>);
 *     DELETE FROM ocp5_product_code        WHERE product_id IN (<created_product_ids>);
 *     DELETE FROM ocp5_product_to_category WHERE product_id IN (<created_product_ids>);   -- removes BOTH rows per product (subcategory 72/73/74 and parent 70/59/60)
 *     DELETE FROM ocp5_product_to_store    WHERE product_id IN (<created_product_ids>);
 *     DELETE FROM ocp5_product_description WHERE product_id IN (<created_product_ids>);
 *     DELETE FROM ocp5_product             WHERE product_id IN (<created_product_ids>);
 *   Expected ids if WP3 ran first (NOT hardcoded, orientation only): 130..143.
 *   Deleting by product_id removes both category rows at once. No existing
 *   product_to_category row is ever touched, so the owner's manual accessory
 *   state (95-100/112-114 in 70+71, and 99 in 70+71+72) is unaffected.
 *
 * NOT TOUCHED
 *   Every existing product and category, the attribute definitions, sitemap,
 *   robots, .htaccess, checkout, payment, CRM.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME      = '3D-P-CARDCONTENT_products-figures-stands_20260819';
const LANGUAGE_ID     = 4;
const STORE_ID        = 0;
const ATTR_GROUP_ID   = 10;
const MANUFACTURER_ID = 17;
const MANUFACTURER    = 'Booster Shop';
const TAX_CLASS_ID    = 0;
const LENGTH_CLASS_ID = 1; // cm
const WEIGHT_CLASS_ID = 2; // g
const STOCK_STATUS_ID = 8; // Передзамовлення — assumption, see the header
const STOCK_STATUS    = 'Передзамовлення';
const QUANTITY        = 0;
const PRICE           = '1.0000';
const STATUS          = 0;  // not visible
const SORT_ORDER      = 8;  // owner decision 2026-08-19 — see the DISPLAY ORDER note in the header

/** Subcategories used by this patch, resolved by full SEO path and asserted. */
const TARGET_CATEGORIES = [
    'pokemon'   => ['keyword' => 'Pokemon/figurky-ta-dekor-pokemon',      'name' => 'Фігурки та декор', 'parent' => 59],
    'one_piece' => ['keyword' => 'One-Piece/figurky-ta-dekor-one-piece',  'name' => 'Фігурки та декор', 'parent' => 60],
    'stands'    => ['keyword' => 'acsesuary/pidstavky-ta-dekor',          'name' => 'Підставки та декор', 'parent' => 70],
];

/** Retired identifiers that must never appear in content or in the database. */
const RETIRED_TOKENS = ['FIG-PKBL-100', 'ACC-3D-PKM-140', 'ACC-3D-PKM-210', 'BGC'];

/** Figures wave — 13 rows, «Призначення» in, «Матеріал фурнітури» and «Сумісність» out. */
const ATTRIBUTE_ORDER_FIGURES = [
    'Тип виробу',
    'Країна виготовлення',
    'Спосіб виготовлення',
    'Матеріал',
    'Колір',
    'Розміри',
    'Маса',
    'Комплектація',
    'Рухомі елементи',
    'Призначення',
    'Вікове позиціонування',
    'Типовий строк виготовлення при відсутності на складі',
    'Може зустрічатися в Mystery Box Item',
];

/** Stands wave — 13 rows, «Сумісність» in, «Матеріал фурнітури» and «Призначення» out. */
const ATTRIBUTE_ORDER_STANDS = [
    'Тип виробу',
    'Країна виготовлення',
    'Спосіб виготовлення',
    'Матеріал',
    'Колір',
    'Розміри',
    'Маса',
    'Комплектація',
    'Сумісність',
    'Рухомі елементи',
    'Вікове позиціонування',
    'Типовий строк виготовлення при відсутності на складі',
    'Може зустрічатися в Mystery Box Item',
];

/** Values shared by both waves (figures §3, stands §3). */
const SHARED_ATTRIBUTES = [
    'Країна виготовлення'                                  => 'Україна',
    'Спосіб виготовлення'                                  => 'пошаровий 3D-друк',
    'Матеріал'                                             => 'Пластик PLA',
    'Вікове позиціонування'                                => '14+',
    'Типовий строк виготовлення при відсутності на складі'  => '1–2 робочих дні',
];

/**
 * Anchors that become legal only once every target page is enabled and returns
 * 200 (figures §4.9, stands §4.9). Written to the backup dir, never into the DB.
 */
const PENDING_ANCHORS = [
    ['card' => 'FIG-LUFFY-500',  'anchor' => 'Панно Луффі',    'target' => '/product/panno-luffy-one-piece-3d-druk'],
    ['card' => 'FIG-LUFFY-500',  'anchor' => 'Картина Луффі',  'target' => '/product/kartyna-luffy-one-piece-3d-druk'],
    ['card' => 'FIG-LUFFY-400',  'anchor' => 'Картина Луффі',  'target' => '/product/kartyna-luffy-one-piece-3d-druk'],
    ['card' => 'FIG-LUFFY-410',  'anchor' => 'Панно Луффі',    'target' => '/product/panno-luffy-one-piece-3d-druk'],
    ['card' => 'ACC-3D-PKM-110', 'anchor' => 'середня',        'target' => '/product/pidstavka-dlia-kartky-v-toploaderi-3d-druk'],
    ['card' => 'ACC-3D-PKM-110', 'anchor' => 'велика',         'target' => '/product/pidstavka-dlia-kartky-v-mahnitnomu-keisi-3d-druk'],
    ['card' => 'ACC-3D-PKM-120', 'anchor' => 'Мала',           'target' => '/product/pidstavka-dlia-kartky-v-protektori-3d-druk'],
    ['card' => 'ACC-3D-PKM-130', 'anchor' => 'мала підставка', 'target' => '/product/pidstavka-dlia-kartky-v-protektori-3d-druk'],
    ['card' => 'ACC-3D-PKM-300', 'anchor' => 'низька версія',  'target' => '/product/pidstavka-dlia-slab-3d-druk'],
    ['card' => 'ACC-3D-PKM-710', 'anchor' => 'підставка на ніжці', 'target' => '/product/pidstavka-dlia-slab-na-nizhtsi-3d-druk'],
];

/** All 14 products. Texts verbatim from §4 of the two wave handoffs. */
const PRODUCTS = [
    // ---------------------------------------------------------------- figures
    [
        'sku' => 'FIG-ONIX-500', 'wave' => 'figures', 'category' => 'pokemon',
        'name' => 'Фігурка Онікс (Pokémon) — 3D-друк',
        'slug' => 'figurka-onix-pokemon-3d-druk',
        'faq_id' => 'prod-fig-onix-500',
        'alt' => 'Фігурка Онікс, 3D-друк, Booster Shop',
        'meta_title' => 'Фігурка Онікс (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Рухома фігурка Онікс (Pokémon) із сегментованим кам\'яним тілом, майже 29 см завдовжки. 3D-друк із PLA у Booster Shop — купити в Україні.',
        'meta_kw'    => 'рухома фігурка Онікс Pokémon, flexi Онікс 3D-друк, фігурка Onix сегментована купити',
        'body' => [
            'Онікс — це передусім довжина. Фігурка збирає персонажа з ланцюга кам\'яних сегментів, з\'єднаних рухомо, тому тіло не зафіксоване в одній позі й приймає вигин, який йому задаєш. Онікс (Onix) від цього виглядає радше застигнутим у русі, ніж поставленим на полицю.',
            'Сегменти повертаються один відносно одного, і ту саму модель можна скласти кільцем, хвилею або витягнути майже прямо — у розпрямленому вигляді це близько 28,5 см. Голова опрацьована детальніше за корпус: саме вона тримає впізнаваність, коли решта тіла складена в довільний вигин.',
            'Друкуємо кожну фігурку окремо, у Booster Shop в Україні, пошарово з PLA. На гранях помітна тонка структура шарів — природна риса технології, яка не впливає на міцність з\'єднань.',
        ],
        'faq' => [
            ['Це офіційна фігурка Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'Лише сама фігурка Онікс. Картки, бустери, підставки та будь-який декор на фото до комплекту не входять і показані для оформлення.'],
            ['Це одна деталь чи збірна модель?', 'Одна. Рухомі з\'єднання друкуються вже зібраними, тому фігурку не треба складати, склеювати чи докручувати.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'рухома фігурка',
            'Колір'                                => 'сірий',
            'Розміри'                              => '285×31×35 мм',
            'Маса'                                 => 'орієнтовно 42,83 г',
            'Комплектація'                         => '1 рухома фігурка Онікс',
            'Рухомі елементи'                      => 'є, сегментована flexi-конструкція',
            'Призначення'                          => 'декоративний / колекційний виріб',
            'Може зустрічатися в Mystery Box Item' => 'Так',
        ],
        'ship' => ['weight' => '50', 'length' => '29', 'width' => '4', 'height' => '4'],
    ],
    [
        'sku' => 'FIG-GEOD-511', 'wave' => 'figures', 'category' => 'pokemon',
        'name' => 'Фігурка Геодуд (Pokémon) — 3D-друк',
        'slug' => 'figurka-geodude-pokemon-3d-druk',
        'faq_id' => 'prod-fig-geod-511',
        'alt' => 'Фігурка Геодуд, 3D-друк, Booster Shop',
        'meta_title' => 'Фігурка Геодуд (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Сіра фігурка Геодуд (Pokémon) з рухомими сегментованими руками, 19,8 см завдовжки. Друкуємо самі в Україні — купити в Booster Shop.',
        'meta_kw'    => 'рухома фігурка Геодуд Pokémon, фігурка Geodude 3D-друк, Геодуд з рухомими руками купити',
        'body' => [
            'Геодуд у цій моделі — це переважно голова й кулаки. Гранована кам\'яна морда з насупленими бровами займає більшу частину об\'єму, а руки з\'єднані з головою довгими сегментованими ланками й виглядають майже непропорційними. Саме ця диспропорція робить Геодуда (Geodude) впізнаваним і трохи комічним.',
            'Руки рухаються в сегментах, тому позу можна змінювати: опустити кулаки на поверхню, розвести їх у боки або підняти над головою. Одноколірне виконання при цьому працює на користь формі — без кольорових акцентів увага лишається на гранях і на тому, як на них лягає світло.',
            'Відтінок сірого може незначно відрізнятися між партіями — для пошарового 3D-друку це звичайна річ. Кожен екземпляр ми друкуємо окремо, у Booster Shop в Україні, з PLA.',
        ],
        'faq' => [
            ['Це офіційна фігурка Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'У комплект входить лише фігурка Геодуд. Інші предмети та декор, які потрапили в кадр, до комплекту не входять.'],
            ['Фігурка стоїть чи лежить?', 'Ніг у персонажа немає, тому модель розрахована лежати на поверхні, спираючись на голову й руки. Окремої підставки конструкція не передбачає.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'рухома фігурка',
            'Колір'                                => 'сірий',
            'Розміри'                              => '198×38×35 мм',
            'Маса'                                 => 'орієнтовно 27,11 г',
            'Комплектація'                         => '1 рухома фігурка Геодуд',
            'Рухомі елементи'                      => 'є, рухомі сегменти рук',
            'Призначення'                          => 'декоративний / колекційний виріб',
            'Може зустрічатися в Mystery Box Item' => 'Так',
        ],
        'ship' => ['weight' => '50', 'length' => '20', 'width' => '4', 'height' => '4'],
    ],
    [
        'sku' => 'FIG-MEW-100', 'wave' => 'figures', 'category' => 'pokemon',
        'name' => 'Фігурка Мью в покеболі (Pokémon) — 3D-друк',
        'slug' => 'figurka-mew-pokeball-pokemon-3d-druk',
        'faq_id' => 'prod-fig-mew-100',
        'alt' => 'Фігурка Мью в покеболі, 3D-друк, Booster Shop',
        'meta_title' => 'Фігурка Мью в покеболі (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Фігурка Мью в покеболі (Pokémon): рожева сфера з вухами та хвостом, 9 см. 3D-друк із PLA власного виробництва, Booster Shop.',
        'meta_kw'    => 'фігурка Мью в покеболі Pokémon, Mew Pokeball 3D-друк рожевий, фігурка Мью з хвостом купити',
        'body' => [
            'Мью тут не сидить у покеболі — Мью і є покебол. Сфера отримала вуха, чорну смугу з білою кнопкою рівно там, де їй належить бути, і довгий хвіст, що виводить дугу назад. Через це силует читається одночасно і як Pokeball, і як Mew — у цьому вся ідея виробу.',
            'Форма розрахована на розглядання з різних боків: спереду це майже чистий покебол, збоку хвіст різко змінює композицію, а зверху видно вуха, яких у звичайного покебола бути не може. Рожевий корпус із білою кнопкою й чорною смугою тримає обидва образи одночасно.',
            'На округлій поверхні особливо добре читаються горизонтальні лінії пошарового друку, які самі стають частиною фактури моделі. Друкуємо кожну фігурку окремо, у Booster Shop в Україні, з PLA.',
        ],
        'faq' => [
            ['Це офіційна фігурка Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'Лише фігурка Мью в покеболі. Картки, бустери, підставки та декор на фото до комплекту не входять.'],
            ['Це покебол чи фігурка Мью?', 'І те, й те. Виріб побудований як гібрид: форма й кнопка взяті від покебола, вуха та хвіст — від Мью. Окремої фігурки Мью всередині немає.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'фігурка',
            'Колір'                                => 'багатоколірний',
            'Розміри'                              => '90×70×90 мм (з хвостом)',
            'Маса'                                 => 'орієнтовно 60,48 г',
            'Комплектація'                         => '1 фігурка Мью в покеболі',
            'Рухомі елементи'                      => 'немає',
            'Призначення'                          => 'декоративний / колекційний виріб',
            'Може зустрічатися в Mystery Box Item' => 'Так',
        ],
        'ship' => ['weight' => '100', 'length' => '9', 'width' => '7', 'height' => '9'],
    ],
    [
        'sku' => 'FIG-PKBL-600', 'wave' => 'figures', 'category' => 'pokemon',
        'name' => 'Фігурка-клікер Покебол (Pokémon) — 3D-друк',
        'slug' => 'figurka-kliker-pokeball-pokemon-3d-druk',
        'faq_id' => 'prod-fig-pkbl-600',
        'alt' => 'Фігурка-клікер Покебол, 3D-друк, Booster Shop',
        'meta_title' => 'Фігурка-клікер Покебол (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Фігурка-клікер Покебол (Pokémon) 2,5 см із натискною fidget-механікою на чорній основі. 3D-друк PLA — Booster Shop, Україна.',
        'meta_kw'    => 'фігурка-клікер Покебол Pokémon, Pokeball clicker fidget 3D-друк, настільний клікер покебол купити',
        'body' => [
            'Найменший виріб партії — 25 міліметрів у поперечнику, і єдиний у ній із натискною клікер-механікою. У чорне кільце-основу вставлений червоно-білий покебол, а кнопка розташована там само, де й у звичному образі Pokeball, тому механіка не виглядає доробленою збоку.',
            'Тримати його зручно двома пальцями — це тактильний fidget-виріб для робочого столу, а не декор, який стоїть недоторканим. Чорна основа при цьому утримує покебол і не дає йому котитися по поверхні.',
            'Пошарову фактуру на виробі такого розміру видно лише зблизька. Клікер друкуємо у Booster Shop в Україні, з PLA.',
        ],
        'faq' => [
            ['Це офіційний виріб Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'Лише сам клікер разом із основою. Будь-які інші предмети на фото до комплекту не входять.'],
            ['Навіщо чорна основа?', 'Вона утримує покебол і не дає йому котитися по столу. Це частина самого виробу, а не окремий аксесуар.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'фігурка-клікер',
            'Колір'                                => 'багатоколірний',
            'Розміри'                              => '25×25×30 мм',
            'Маса'                                 => 'орієнтовно 6,89 г',
            'Комплектація'                         => '1 фігурка-клікер Покебол',
            'Рухомі елементи'                      => 'є, клікер-механізм',
            'Призначення'                          => 'декоративний / тактильний fidget-виріб',
            'Може зустрічатися в Mystery Box Item' => 'Ні',
        ],
        'ship' => ['weight' => '50', 'length' => '3', 'width' => '3', 'height' => '3'],
    ],
    [
        'sku' => 'FIG-LUFFY-500', 'wave' => 'figures', 'category' => 'one_piece',
        'name' => 'Фігурка Луффі (One Piece) — 3D-друк',
        'slug' => 'figurka-luffy-one-piece-3d-druk',
        'faq_id' => 'prod-fig-luffy-500',
        'alt' => 'Фігурка Луффі, 3D-друк, Booster Shop',
        'meta_title' => 'Фігурка Луффі (One Piece) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Гнучка фігурка Луффі (One Piece) із сегментованим корпусом і деталізованим обличчям, 9,3 см. 3D-друк із PLA — Booster Shop, Україна.',
        'meta_kw'    => 'рухома фігурка Луффі One Piece, flexi фігурка Luffy 3D-друк, гнучка фігурка Луффі біла',
        'body' => [
            'Луффі важко уявити персонажем, який спокійно стоїть на полиці — і ця фігурка з ним не сперечається. Корпус набраний із гнучких ребристих сегментів, тому модель гнеться й скручується замість того, щоб тримати одну задану позу. У білому виконанні впізнаваність Luffy тримається на капелюсі, розкинутих руках і широкій усмішці, а не на кольорових деталях.',
            'Обличчя пропрацьоване несподівано детально як для такого формату — примружені очі та зуби видно й без фарбування. Решта фігури навпаки спрощена, і цей контраст працює на користь: погляд чіпляється за голову, а тіло лишається пластичною основою.',
            'Через гнучку геометрію на переходах між сегментами подекуди лишаються ледь помітні сліди опор. Друкуємо фігурку в Україні, у Booster Shop, із PLA — кожен екземпляр окремо.',
        ],
        'faq' => [
            ['Це офіційна фігурка One Piece?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці One Piece. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'Лише сама фігурка Луффі. Інші предмети та декор, які потрапили в кадр, до комплекту не входять.'],
            ['Чим ця фігурка відрізняється від панно й картини Луффі?', 'Це об\'ємна модель із гнучким корпусом. Панно Луффі і Картина Луффі — пласкі декоративні вироби: вони працюють силуетом і лінією, а не формою.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'рухома фігурка',
            'Колір'                                => 'білий',
            'Розміри'                              => '93×73×24 мм',
            'Маса'                                 => 'орієнтовно 15,84 г',
            'Комплектація'                         => '1 рухома фігурка Луффі',
            'Рухомі елементи'                      => 'є, гнучка сегментована конструкція корпусу',
            'Призначення'                          => 'декоративний / колекційний виріб',
            'Може зустрічатися в Mystery Box Item' => 'Так',
        ],
        'ship' => ['weight' => '50', 'length' => '10', 'width' => '8', 'height' => '3'],
    ],
    [
        'sku' => 'FIG-LUFFY-400', 'wave' => 'figures', 'category' => 'one_piece',
        'name' => 'Панно Луффі (One Piece) — 3D-друк',
        'slug' => 'panno-luffy-one-piece-3d-druk',
        'faq_id' => 'prod-fig-luffy-400',
        'alt' => 'Панно Луффі, 3D-друк, Booster Shop',
        'meta_title' => 'Панно Луффі (One Piece) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Панно Луффі (One Piece) — чорний силует у повний зріст на підставці, 18 см заввишки. Власний 3D-друк із PLA, Booster Shop, Україна.',
        'meta_kw'    => 'панно Луффі силует One Piece, чорний силует Luffy 3D-друк, панно Луффі на підставці купити',
        'body' => [
            'Тут немає жодної риси обличчя — і персонаж усе одно впізнається. Панно побудоване на суцільному чорному контурі: капелюх, піднята до нього рука, розкльошені шорти й характерна постава. Luffy зчитується за позою раніше, ніж встигаєш пошукати деталі.',
            'Найкраще виріб працює проти світла — на світлій стіні, полиці чи біля вікна, де контуру є з чим контрастувати. Стоїть він на власній основі, тому не потребує ні кріплення, ні рамки, а висота силуету 18 см робить його помітним, але не домінантним у композиції.',
            'Чорний колір тут працює на саму ідею панно — підсилює цілісність силуету й контраст зі світлим фоном. Друкуємо його самі, в Україні, з PLA; тонка горизонтальна текстура шарів на великих площинах усе одно читається зблизька.',
        ],
        'faq' => [
            ['Це офіційний виріб One Piece?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці One Piece. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'Лише панно разом із його основою. Фон, предмети та декор на фотографіях до комплекту не входять і показані для оформлення.'],
            ['Чим панно відрізняється від картини Луффі?', 'Панно — суцільний силует фігури в повний зріст. Картина Луффі — лінійний портрет обличчя в прямокутній рамці; там працює контур ліній, а не заповнена форма.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'панно',
            'Колір'                                => 'чорний',
            'Розміри'                              => '99×181×29 мм',
            'Маса'                                 => 'орієнтовно 37,94 г',
            'Комплектація'                         => '1 панно Луффі',
            'Рухомі елементи'                      => 'немає',
            'Призначення'                          => 'декоративний / колекційний виріб',
            'Може зустрічатися в Mystery Box Item' => 'Так',
        ],
        'ship' => ['weight' => '50', 'length' => '19', 'width' => '10', 'height' => '3'],
    ],
    [
        'sku' => 'FIG-LUFFY-410', 'wave' => 'figures', 'category' => 'one_piece',
        'name' => 'Картина Луффі (One Piece) — 3D-друк',
        'slug' => 'kartyna-luffy-one-piece-3d-druk',
        'faq_id' => 'prod-fig-luffy-410',
        'alt' => 'Картина Луффі, 3D-друк, Booster Shop',
        'meta_title' => 'Картина Луффі (One Piece) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Картина Луффі (One Piece): лінійний портрет Luffy у чорній рамці, 25×19 см. 3D-друк власного виробництва — купити в Україні.',
        'meta_kw'    => 'картина Луффі One Piece 3D-друк, лінійний портрет Luffy на полицю, чорна картина Луффі в рамці купити',
        'body' => [
            'Це малюнок, зроблений порожнечею. Обличчя Луффі — заплющені від сміху очі, зуби, солом\'яний капелюх — зібране з тонких чорних ліній у прямокутній рамці, і все, чого немає, працює нарівні з тим, що є. Luffy тут переданий буквально кількома штрихами.',
            'На полиці чи комоді картина тримається сама, спираючись на нижню планку, — свердлити стіну не треба. За форматом 25×19 см це вже не сувенір, а самостійний елемент оформлення, який задає тон усій полиці з колекцією.',
            'Ажурна геометрія з тонкими лініями робить природні особливості друку помітнішими: подекуди на них лишаються ледь помітні сліди опор. На міцність готової рамки це не впливає. Виріб друкуємо у Booster Shop в Україні, з PLA.',
        ],
        'faq' => [
            ['Це офіційний виріб One Piece?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці One Piece. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'У комплект входить лише картина. Полиця, фон і будь-які інші предмети на фото до комплекту не входять.'],
            ['Чим картина відрізняється від панно Луффі?', 'Картина — лінійний портрет обличчя в рамці. Панно Луффі — суцільний силует фігури в повний зріст, без рамки.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'картина',
            'Колір'                                => 'чорний',
            'Розміри'                              => '250×194×27 мм',
            'Маса'                                 => 'орієнтовно 114,27 г',
            'Комплектація'                         => '1 картина Луффі',
            'Рухомі елементи'                      => 'немає',
            'Призначення'                          => 'декоративний / колекційний виріб',
            'Може зустрічатися в Mystery Box Item' => 'Ні',
        ],
        'ship' => ['weight' => '150', 'length' => '25', 'width' => '20', 'height' => '3'],
    ],

    // ----------------------------------------------------------------- stands
    [
        'sku' => 'ACC-3D-PKM-110', 'wave' => 'stands', 'category' => 'stands',
        'name' => 'Підставка для карток, мала — 3D-друк',
        'slug' => 'pidstavka-dlia-kartky-v-protektori-3d-druk',
        'faq_id' => 'prod-acc-3d-pkm-110',
        'alt' => 'Підставка для картки в протекторі, 3D-друк, Booster Shop',
        'meta_title' => 'Підставка для картки в протекторі — 3D-друк | Booster Shop',
        'meta_desc'  => 'Підставка для картки в протекторі 63×89 мм, габарити 87×23×105 мм: вузька чорна рамка, яка не перекриває арт. 3D-друк — купити в Україні.',
        'meta_kw'    => 'підставка для картки в протекторі, підставка для TCG картки 63х89, підставка для картки в sleeve',
        'body' => [
            'Найпростіший спосіб поставити одну картку вертикально. Вузька рамка тримає її по контуру й лишає майже всю площу відкритою — на полиці видно арт, а не тримач навколо нього.',
            'Це найлегша модель серії, близько 13 грамів: тонка рамка на пласкій основі, розрахована на картку в м\'якому протекторі. Формат для щоденного зберігання, коли жорсткий кейс зайвий, а поставити улюблену картку на видноті хочеться.',
            'Підставку друкуємо у Booster Shop в Україні, пошарово з PLA. На пласких гранях рамки зблизька читається тонка структура шарів — природна риса технології.',
        ],
        'faq' => [
            ['Що входить у комплект?', 'Лише сама підставка. Картки, протектори й декор на фото до комплекту не входять і показані для оформлення.'],
            ['Чи підходить підставка для карток Pokémon, One Piece і Magic?', 'Так. Вона розрахована на стандартний формат картки 63×89 мм у м\'якому протекторі — цей розмір використовують Pokémon TCG, One Piece Card Game, Magic: The Gathering, Yu-Gi-Oh! та інші TCG.'],
            ['Яку підставку взяти, якщо картка в топлоадері або магнітному кейсі?', 'Для цих форматів у лінійці є окремі моделі: середня — під топлоадер, велика — під магнітний акриловий кейс. Мала розрахована саме на м\'який протектор.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'підставка для карток',
            'Колір'                                => 'чорний',
            'Розміри'                              => '87×23×105 мм',
            'Маса'                                 => 'орієнтовно 12,82 г',
            'Комплектація'                         => '1 підставка',
            'Сумісність'                           => 'картка 63×89 мм у м\'якому протекторі',
            'Рухомі елементи'                      => 'немає',
            'Може зустрічатися в Mystery Box Item' => 'Так',
        ],
        'ship' => ['weight' => '50', 'length' => '11', 'width' => '9', 'height' => '3'],
    ],
    [
        'sku' => 'ACC-3D-PKM-120', 'wave' => 'stands', 'category' => 'stands',
        'name' => 'Підставка для карток, середня — 3D-друк',
        'slug' => 'pidstavka-dlia-kartky-v-toploaderi-3d-druk',
        'faq_id' => 'prod-acc-3d-pkm-120',
        'alt' => 'Підставка для картки в топлоадері, 3D-друк, Booster Shop',
        'meta_title' => 'Підставка для картки в топлоадері — 3D-друк | Booster Shop',
        'meta_desc'  => 'Підставка для картки в топлоадері: топлоадер тримається вертикально між тонкими опорами, 87×30×118 мм. Власний 3D-друк — купити в Україні.',
        'meta_kw'    => 'підставка для картки в топлоадері, підставка для топлоадера 35PT, підставка toploader 3D-друк',
        'body' => [
            'Топлоадер тут не лежить в основі, а тримається між двома тонкими опорами — картка виглядає підвішеною в повітрі. Жорсткий прозорий корпус стає частиною експозиції замість того, щоб просто захищати.',
            'Опори вужчі за саму картку, тому центральна частина арту не перекрита з жодного боку. Модель вища за малу підставку й помітно вужча за велику: розрахована саме на товщину топлоадера, не на м\'який протектор і не на магнітний кейс.',
            'На тонких опорах подекуди лишаються ледь помітні сліди підтримок — геометрія без них не друкується. Кожен екземпляр виготовляємо у Booster Shop в Україні з PLA.',
        ],
        'faq' => [
            ['Що входить у комплект?', 'У комплект входить лише підставка. Картка, топлоадер та інші предмети на фотографіях до комплекту не входять.'],
            ['Чи підійде підставка для топлоадера з карткою в протекторі всередині?', 'Залежить від товщини конкретного топлоадера. Паз розрахований під стандартний 35PT; якщо картка додатково в протекторі й топлоадер товщий, посадка буде щільнішою.'],
            ['Чим середня підставка відрізняється від малої?', 'Мала розрахована на картку в м\'якому протекторі й тримає її по контуру. Середня приймає жорсткий топлоадер і піднімає його між опорами.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'підставка для карток',
            'Колір'                                => 'чорний',
            'Розміри'                              => '87×30×118 мм',
            'Маса'                                 => 'орієнтовно 17,54 г',
            'Комплектація'                         => '1 підставка',
            'Сумісність'                           => 'картка в топлоадері',
            'Рухомі елементи'                      => 'немає',
            'Може зустрічатися в Mystery Box Item' => 'Так',
        ],
        'ship' => ['weight' => '50', 'length' => '12', 'width' => '9', 'height' => '3'],
    ],
    [
        'sku' => 'ACC-3D-PKM-130', 'wave' => 'stands', 'category' => 'stands',
        'name' => 'Підставка для карток, велика — 3D-друк',
        'slug' => 'pidstavka-dlia-kartky-v-mahnitnomu-keisi-3d-druk',
        'faq_id' => 'prod-acc-3d-pkm-130',
        'alt' => 'Підставка для картки в магнітному кейсі, 3D-друк, Booster Shop',
        'meta_title' => 'Підставка для картки в магнітному кейсі — 3D-друк | Booster Shop',
        'meta_desc'  => 'Підставка для картки в магнітному кейсі 89×65×170 мм — висока чорна опора з помаранчевою лінією. 3D-друк PLA, Booster Shop, Україна.',
        'meta_kw'    => 'підставка для магнітного кейса картки, підставка для One Touch кейса, підставка для картки в акриловому кейсі',
        'body' => [
            'Магнітний акриловий кейс уже сам по собі виглядає як вітрина — цій підставці лишається підняти його й дати простір. Висока опора виносить картку над основою, і замість тримача виходить окремий настільний дисплей.',
            'Чорний корпус розрізає тонка помаранчева лінія по основі — єдиний кольоровий акцент у всій серії підставок. Це найважча з трьох базових моделей, близько 72 грамів, і вагу тут дає саме основа: висока конструкція потребує стійкого фундаменту.',
            'Чорний PLA і висота корпусу означають, що пошарова структура на широких площинах видно зблизька. На несучу здатність опори це не впливає — шари лягають поперек навантаження. Друкуємо у Booster Shop в Україні.',
        ],
        'faq' => [
            ['Що входить у комплект?', 'Лише сама підставка. Картка, магнітний кейс та інший декор на фото до комплекту не входять і показані для оформлення.'],
            ['Під який розмір магнітного кейса розрахована підставка?', 'Під стандартний магнітний акриловий кейс формату One Touch. Товщина кейса залежить від того, скільки карток він розрахований тримати — перед покупкою варто звірити свій формат із габаритами паза.'],
            ['Чи можна поставити в неї звичайну картку в протекторі?', 'Паз розрахований під товщину акрилового кейса, тому тонка картка сидітиме вільно. Для протектора підходить мала підставка серії.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'підставка для карток',
            'Колір'                                => 'чорний із помаранчевою лінією',
            'Розміри'                              => '89×65×170 мм',
            'Маса'                                 => 'орієнтовно 71,92 г',
            'Комплектація'                         => '1 підставка',
            'Сумісність'                           => 'картка в магнітному акриловому кейсі',
            'Рухомі елементи'                      => 'немає',
            'Може зустрічатися в Mystery Box Item' => 'Так',
        ],
        'ship' => ['weight' => '100', 'length' => '17', 'width' => '9', 'height' => '7'],
    ],
    [
        'sku' => 'ACC-3D-PKM-200', 'wave' => 'stands', 'category' => 'stands',
        'name' => 'Підставка для грейджених карток — 3D-друк',
        'slug' => 'pidstavka-dlia-slab-3d-druk',
        'faq_id' => 'prod-acc-3d-pkm-200',
        'alt' => 'Підставка для грейдженої картки, 3D-друк, Booster Shop',
        'meta_title' => 'Підставка для грейдженої картки PSA — 3D-друк | Booster Shop',
        'meta_desc'  => 'Підставка для слаба PSA 89×38×20 мм: низька чорна основа, яка не відбирає увагу в грейдженої картки. Власний 3D-друк — купити в Україні.',
        'meta_kw'    => 'підставка для грейдженої картки PSA, підставка для PSA slab, підставка для слаба купити',
        'body' => [
            'Грейджений слаб не потребує оформлення — йому потрібен кут нахилу. Ця підставка дає рівно це: низьку основу, яка тримає слаб PSA під зручним для перегляду кутом і не додає ані сантиметра декору.',
            'Основа піднімає слаб на два сантиметри й лишається майже непомітною під ним. Такий профіль розрахований на ряд: коли грейджені картки стоять поруч, підставки не мають перетягувати на себе ритм полиці.',
            'Виріб друкуємо в Україні, у Booster Shop, із чорного PLA — на похилій площині основи пошарова фактура читається як тонкі паралельні лінії.',
        ],
        'faq' => [
            ['Що входить у комплект?', 'Лише підставка. Грейджена картка та предмети на фото до комплекту не входять.'],
            ['Під який формат слаба розрахована підставка?', 'Під корпус PSA — це вказано в характеристиках. Корпуси інших грейдингових компаній відрізняються шириною й товщиною, тому в цій моделі слаб іншого формату сидітиме не щільно.'],
            ['Скільки таких підставок стане в ряд на полиці?', 'Кожна займає 89 мм по фронту. На полиці шириною 80 см поміститься близько восьми слабів упритул, менше — якщо лишати проміжки.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'підставка для грейджених карток',
            'Колір'                                => 'чорний',
            'Розміри'                              => '89×38×20 мм',
            'Маса'                                 => 'орієнтовно 16,39 г',
            'Комплектація'                         => '1 підставка',
            'Сумісність'                           => 'грейджений слаб PSA',
            'Рухомі елементи'                      => 'немає',
            'Може зустрічатися в Mystery Box Item' => 'Ні',
        ],
        'ship' => ['weight' => '50', 'length' => '9', 'width' => '4', 'height' => '2'],
    ],
    [
        'sku' => 'ACC-3D-PKM-300', 'wave' => 'stands', 'category' => 'stands',
        'name' => 'Підставка для грейджених карток, на ніжці — 3D-друк',
        'slug' => 'pidstavka-dlia-slab-na-nizhtsi-3d-druk',
        'faq_id' => 'prod-acc-3d-pkm-300',
        'alt' => 'Підставка для грейдженої картки на ніжці, 3D-друк, Booster Shop',
        'meta_title' => 'Підставка для слаба PSA на ніжці — 3D-друк | Booster Shop',
        'meta_desc'  => 'Підставка для слаба PSA на ніжці, висота 226 мм: настільний дисплей для однієї акцентної картки. 3D-друк PLA — купити в Україні.',
        'meta_kw'    => 'підставка PSA slab на ніжці, підставка для грейдженої картки на ніжці, настільний стенд для слаба',
        'body' => [
            'Двадцять два сантиметри висоти перетворюють тримач на подіум. Слаб PSA піднімається над столом на окремій ніжці, і замість «картка лежить під кутом» виходить «картка стоїть на постаменті» — різниця в сприйнятті більша, ніж різниця в конструкції.',
            'Широка основа тримає всю цю висоту стійко, і на неї припадає більша частина зі 105 грамів моделі. Формат для однієї картки, яку хочеться винести вперед: у ряду однакових підставок така модель ламає ритм, і це навмисно.',
            'Висока ніжка друкується шар за шаром знизу вгору, тому на ній структура читається найкраще з усієї серії. Виріб виготовляємо у Booster Shop в Україні з чорного PLA.',
        ],
        'faq' => [
            ['Що входить у комплект?', 'Лише підставка на ніжці. Грейджена картка та декор на фотографіях до комплекту не входять.'],
            ['Чим ця модель відрізняється від низької підставки для слабів?', 'Висотою й задачею. Низька версія тримає слаб під кутом і майже не видно; ця піднімає його на 226 мм і працює як окремий настільний дисплей для однієї акцентної картки.'],
            ['Чи не перекидається підставка з важким слабом?', 'Основа має габарити 102×100 мм і сама важить більшу частину моделі — центр ваги залишається низько. На рівній поверхні конструкція стійка.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'підставка для грейджених карток',
            'Колір'                                => 'чорний',
            'Розміри'                              => '102×100×226 мм',
            'Маса'                                 => 'орієнтовно 105,48 г',
            'Комплектація'                         => '1 підставка на ніжці',
            'Сумісність'                           => 'грейджений слаб PSA',
            'Рухомі елементи'                      => 'немає',
            'Може зустрічатися в Mystery Box Item' => 'Ні',
        ],
        'ship' => ['weight' => '150', 'length' => '23', 'width' => '11', 'height' => '10'],
    ],
    [
        'sku' => 'ACC-3D-PKM-700', 'wave' => 'stands', 'category' => 'stands',
        'name' => 'Обертова підставка для карток у топлоадерах — 3D-друк',
        'slug' => 'obertova-pidstavka-dlia-toploaderiv-3d-druk',
        'faq_id' => 'prod-acc-3d-pkm-700',
        'alt' => 'Обертова підставка для карток у топлоадерах, 3D-друк, Booster Shop',
        'meta_title' => 'Обертова підставка для топлоадерів — 3D-друк | Booster Shop',
        'meta_desc'  => 'Обертова шестигранна підставка на кілька карток у топлоадерах, 127×170×185 мм. Власний 3D-друк PLA — купити в Україні, Booster Shop.',
        'meta_kw'    => 'обертова підставка для топлоадерів, rotating toploader display, шестигранна підставка для карток',
        'body' => [
            'Проблема будь-якої полиці — вона показує лише передній ряд. Ця підставка вирішує це поворотом: топлоадери стоять по гранях шестигранного корпусу, і замість переставляння карток достатньо повернути дисплей потрібною стороною.',
            'Одна модель замінює кілька окремих тримачів і займає при цьому місце одного. Обертова основа — не декоративний елемент, а сенс конструкції: без неї задні грані були б мертвим простором.',
            'Це найтриваліший друк серії — вісім годин на один екземпляр, і великі вертикальні панелі корпусу друкуються суцільно. Через це пошарова фактура на них помітна навіть без освітлення збоку. Виготовляємо у Booster Shop в Україні з чорного PLA.',
        ],
        'faq' => [
            ['Що входить у комплект?', 'Лише обертова підставка. Картки, топлоадери та інші предмети на фото до комплекту не входять.'],
            ['Скільки карток вміщає підставка?', 'Точну кількість слотів уточнюємо — вона залежить від товщини конкретних топлоадерів. Конструкція шестигранна, картки розміщуються по гранях корпусу.'],
            ['Чи потрібно щось збирати або змащувати?', 'Ні. Обертовий механізм друкується як частина конструкції, окремих підшипників чи змазки він не потребує.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'обертова підставка для карток',
            'Колір'                                => 'чорний',
            'Розміри'                              => '127×170×185 мм',
            'Маса'                                 => 'орієнтовно 241,24 г',
            'Комплектація'                         => '1 обертова підставка',
            'Сумісність'                           => 'картки в топлоадерах',
            'Рухомі елементи'                      => 'є, обертова конструкція',
            'Може зустрічатися в Mystery Box Item' => 'Ні',
        ],
        'ship' => ['weight' => '250', 'length' => '19', 'width' => '17', 'height' => '13'],
    ],
    [
        'sku' => 'ACC-3D-PKM-710', 'wave' => 'stands', 'category' => 'stands',
        'name' => 'Обертова підставка для грейджених карток — 3D-друк',
        'slug' => 'obertova-pidstavka-dlia-slab-3d-druk',
        'faq_id' => 'prod-acc-3d-pkm-710',
        'alt' => 'Обертова підставка для грейджених карток, 3D-друк, Booster Shop',
        'meta_title' => 'Обертова підставка для PSA slab — 3D-друк | Booster Shop',
        'meta_desc'  => 'Обертова підставка для слабів PSA 201×170×157 мм: шестигранний дисплей, який повертається до глядача. 3D-друк PLA — купити в Україні.',
        'meta_kw'    => 'обертова підставка PSA slab, rotating PSA slab display, шестигранна підставка для слабів',
        'body' => [
            'Коли грейджених карток стає більше трьох, вони перестають поміщатися в поле зору. Обертовий дисплей збирає слаби PSA навколо шестигранного корпусу — колекція перестає бути рядом і стає об\'єктом, який можна обійти поворотом руки.',
            'Порівняно з підставкою на ніжці тут інший акцент: та виносить уперед одну картку, ця показує серію. Модель нижча за неї, зате втричі ширша — 20 сантиметрів по фронту, повноцінний настільний предмет.',
            'На обертовому вузлі лишаються ледь помітні сліди опор — рухома пара друкується з мінімальним зазором, і без підтримок він не витримується. Модель друкуємо у Booster Shop в Україні пошарово з чорного PLA.',
        ],
        'faq' => [
            ['Що входить у комплект?', 'У комплект входить лише обертова підставка. Грейджені картки та інший декор на фото не входять.'],
            ['Скільки слабів вміщає дисплей?', 'Точну кількість уточнюємо. Корпус шестигранний, слаби розміщуються по його гранях.'],
            ['Під який формат слабів розрахований дисплей?', 'Під корпус PSA — це вказано в характеристиках. Грані розраховані саме на його ширину й товщину, тому слаб іншої грейдингової компанії стане не щільно.'],
        ],
        'attributes' => [
            'Тип виробу'                           => 'обертова підставка для грейджених карток',
            'Колір'                                => 'чорний',
            'Розміри'                              => '201×170×157 мм',
            'Маса'                                 => 'орієнтовно 250,30 г',
            'Комплектація'                         => '1 обертова підставка',
            'Сумісність'                           => 'грейджені слаби PSA',
            'Рухомі елементи'                      => 'є, обертова конструкція',
            'Може зустрічатися в Mystery Box Item' => 'Ні',
        ],
        'ship' => ['weight' => '300', 'length' => '21', 'width' => '17', 'height' => '16'],
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
function bs_product_description(array $product): string {
    $nl = "\r\n";
    $html = '';
    foreach ($product['body'] as $index => $paragraph) {
        if ($index > 0) $html .= $nl;
        $html .= '<p>' . $paragraph . '</p>' . $nl;
    }
    $html .= $nl . bs_faq_html($product['faq_id'], $product['faq']);
    return bs_encode_html($html);
}

// --------------------------------------------------------------------------
// Domain helpers
// --------------------------------------------------------------------------

function bs_attribute_order(string $wave): array {
    return $wave === 'figures' ? ATTRIBUTE_ORDER_FIGURES : ATTRIBUTE_ORDER_STANDS;
}

function bs_category_by_keyword(mysqli $db, array $t, string $keyword): int {
    $rows = bs_select($db, 'SELECT `value` FROM `' . $t['seo_url'] . '` WHERE `key` = \'path\' AND `keyword` = ? AND store_id = ?', 'si', [$keyword, STORE_ID]);
    if ($rows === []) bs_fail('Subcategory «' . $keyword . '» not found. Run WP1 (3D-P-002 subcategories) first.');
    if (count($rows) > 1) bs_fail('Subcategory «' . $keyword . '» has ' . count($rows) . ' seo_url rows — ambiguous, resolve before running');
    $parts = explode('_', (string) $rows[0]['value']);
    return (int) end($parts);
}

function bs_attribute_map(mysqli $db, array $t): array {
    $rows = bs_select($db,
        'SELECT a.attribute_id AS attribute_id, d.name AS name FROM `' . $t['attribute'] . '` a'
        . ' JOIN `' . $t['attribute_description'] . '` d ON d.attribute_id = a.attribute_id AND d.language_id = ?'
        . ' WHERE a.attribute_group_id = ?', 'ii', [LANGUAGE_ID, ATTR_GROUP_ID]);
    $map = [];
    foreach ($rows as $row) {
        $name = (string) $row['name'];
        if (isset($map[$name])) bs_fail('Attribute «' . $name . '» exists twice in group ' . ATTR_GROUP_ID . ' — resolve before running');
        $map[$name] = (int) $row['attribute_id'];
    }
    return $map;
}

function bs_product_id_by_model(mysqli $db, array $t, string $model): int {
    $rows = bs_select($db, 'SELECT product_id FROM `' . $t['product'] . '` WHERE model = ?', 's', [$model]);
    if (count($rows) > 1) bs_fail('Model «' . $model . '» matches ' . count($rows) . ' products — resolve before running');
    return $rows === [] ? 0 : (int) $rows[0]['product_id'];
}

function bs_create_product(mysqli $db, array $t, array $product, array $categoryIds, array $attributeMap, string $today): int {
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
            QUANTITY, STOCK_STATUS_ID, MANUFACTURER_ID, PRICE, TAX_CLASS_ID,
            $today, $ship['weight'], WEIGHT_CLASS_ID, $ship['length'], $ship['width'], $ship['height'], LENGTH_CLASS_ID,
            SORT_ORDER, STATUS,
        ]);
    $productId = (int) $db->insert_id;
    if ($productId < 1) bs_fail('product insert returned no id for ' . $sku);

    bs_exec($db,
        'INSERT INTO `' . $t['product_description'] . '` (`product_id`, `language_id`, `name`, `description`, `tag`, `meta_title`, `meta_description`, `meta_keyword`)'
        . ' VALUES (?, ?, ?, ?, \'\', ?, ?, ?)',
        'iisssss',
        [$productId, LANGUAGE_ID, $product['name'], bs_product_description($product), $product['meta_title'], $product['meta_desc'], $product['meta_kw']]);

    bs_exec($db, 'INSERT INTO `' . $t['product_to_store'] . '` (`product_id`, `store_id`) VALUES (?, ?)', 'ii', [$productId, STORE_ID]);
    // Dual assignment (owner decision 2026-08-19): subcategory AND its parent.
    foreach ($categoryIds as $categoryId) {
        bs_exec($db, 'INSERT INTO `' . $t['product_to_category'] . '` (`product_id`, `category_id`) VALUES (?, ?)', 'ii', [$productId, $categoryId]);
    }
    bs_exec($db, 'INSERT INTO `' . $t['product_code'] . '` (`product_id`, `code`, `value`) VALUES (?, \'SKU\', ?)', 'is', [$productId, $sku]);
    bs_exec($db,
        'INSERT INTO `' . $t['seo_url'] . '` (`store_id`, `language_id`, `key`, `value`, `keyword`, `sort_order`) VALUES (?, ?, \'product_id\', ?, ?, 0)',
        'iiss', [STORE_ID, LANGUAGE_ID, (string) $productId, $product['slug']]);

    $order  = bs_attribute_order($product['wave']);
    $values = array_merge(SHARED_ATTRIBUTES, $product['attributes']);
    foreach ($order as $label) {
        if (!isset($values[$label])) bs_fail('No value for attribute «' . $label . '» on ' . $sku);
        if (!isset($attributeMap[$label])) bs_fail('Attribute «' . $label . '» is missing from group ' . ATTR_GROUP_ID . '. Run WP2 first.');
        bs_exec($db,
            'INSERT INTO `' . $t['product_attribute'] . '` (`product_id`, `attribute_id`, `language_id`, `text`) VALUES (?, ?, ?, ?)',
            'iiis', [$productId, $attributeMap[$label], LANGUAGE_ID, $values[$label]]);
    }
    $extra = array_diff(array_keys($values), $order);
    if ($extra !== []) bs_fail('Unused attribute value(s) on ' . $sku . ': ' . implode(', ', $extra));

    return $productId;
}

function bs_verify_product(mysqli $db, array $t, int $productId, array $product, array $categoryIds, array $attributeMap): void {
    $rows = bs_select($db,
        'SELECT model, sku, upc, ean, jan, isbn, mpn, manufacturer_id, status, rating, weight_class_id, length_class_id, sort_order'
        . ' FROM `' . $t['product'] . '` WHERE product_id = ?', 'i', [$productId]);
    if ($rows === []) bs_fail('Product ' . $productId . ' vanished after insert — rolling back');
    $row = $rows[0];

    foreach (['upc', 'ean', 'jan', 'isbn', 'mpn'] as $identifier) {
        if ($row[$identifier] !== null && $row[$identifier] !== '') {
            bs_fail('CONTENT-005 guard: ' . $product['sku'] . ' has a non-empty ' . $identifier . ' — rolling back');
        }
    }
    if ((string) $row['model'] !== $product['sku'] || (string) $row['sku'] !== $product['sku']) bs_fail('SKU mismatch on ' . $product['sku'] . ' — rolling back');
    if ((int) $row['manufacturer_id'] !== MANUFACTURER_ID) bs_fail('Manufacturer mismatch on ' . $product['sku'] . ' — rolling back');
    if ((int) $row['status'] !== STATUS) bs_fail($product['sku'] . ' is not status=' . STATUS . ' — rolling back');
    if ((int) $row['rating'] !== 0) bs_fail($product['sku'] . ' has a non-zero rating — rolling back');
    if ((int) $row['sort_order'] !== SORT_ORDER) {
        bs_fail($product['sku'] . ' has sort_order ' . $row['sort_order'] . ', expected ' . SORT_ORDER . ' — rolling back');
    }
    if ((int) $row['weight_class_id'] !== WEIGHT_CLASS_ID || (int) $row['length_class_id'] !== LENGTH_CLASS_ID) {
        bs_fail('Unit class mismatch on ' . $product['sku'] . ' — rolling back');
    }

    // Dual assignment: exactly the subcategory and its parent, nothing else.
    $rowsCat = bs_select($db, 'SELECT category_id FROM `' . $t['product_to_category'] . '` WHERE product_id = ? ORDER BY category_id', 'i', [$productId]);
    $haveCat = [];
    foreach ($rowsCat as $r) $haveCat[] = (int) $r['category_id'];
    $wantCat = $categoryIds;
    sort($wantCat);
    if ($haveCat !== $wantCat) {
        bs_fail($product['sku'] . ' is in categories [' . implode(',', $haveCat) . '], expected exactly ['
            . implode(',', $wantCat) . '] (subcategory + parent) — rolling back');
    }

    // Exactly this wave's 13 attributes, nothing else, nothing from another group.
    $order = bs_attribute_order($product['wave']);
    $have  = bs_select($db,
        'SELECT d.name AS name, a.attribute_group_id AS grp FROM `' . $t['product_attribute'] . '` pa'
        . ' JOIN `' . $t['attribute'] . '` a ON a.attribute_id = pa.attribute_id'
        . ' JOIN `' . $t['attribute_description'] . '` d ON d.attribute_id = pa.attribute_id AND d.language_id = ?'
        . ' WHERE pa.product_id = ?', 'ii', [LANGUAGE_ID, $productId]);
    $names = [];
    foreach ($have as $r) {
        if ((int) $r['grp'] !== ATTR_GROUP_ID) bs_fail($product['sku'] . ' has attribute «' . $r['name'] . '» from group ' . $r['grp'] . ' — rolling back');
        $names[] = (string) $r['name'];
    }
    sort($names);
    $expected = $order;
    sort($expected);
    if ($names !== $expected) {
        bs_fail($product['sku'] . ' attribute set mismatch. Got: ' . implode(' | ', $names) . ' — rolling back');
    }

    $seo = bs_select($db, 'SELECT keyword FROM `' . $t['seo_url'] . '` WHERE `key` = \'product_id\' AND `value` = ?', 's', [(string) $productId]);
    if (count($seo) !== 1 || (string) $seo[0]['keyword'] !== $product['slug']) bs_fail('SEO URL mismatch on ' . $product['sku'] . ' — rolling back');

    $code = bs_select($db, 'SELECT code, value FROM `' . $t['product_code'] . '` WHERE product_id = ?', 'i', [$productId]);
    if (count($code) !== 1 || (string) $code[0]['value'] !== $product['sku']) bs_fail('product_code mismatch on ' . $product['sku'] . ' — rolling back');
}

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
    $skus  = array_column(PRODUCTS, 'sku');
    $slugs = array_column(PRODUCTS, 'slug');
    if (count(array_unique($skus)) !== count($skus)) bs_fail('Duplicate SKU in PRODUCTS');
    if (count(array_unique($slugs)) !== count($slugs)) bs_fail('Duplicate slug in PRODUCTS');
    if (count(PRODUCTS) !== 14) bs_fail('Expected 14 products in this wave, found ' . count(PRODUCTS));

    foreach (PRODUCTS as $product) {
        foreach (['name' => 255, 'meta_title' => 255, 'meta_desc' => 255, 'meta_kw' => 255] as $field => $limit) {
            if (mb_strlen($product[$field], 'UTF-8') > $limit) bs_fail('Field ' . $field . ' of ' . $product['sku'] . ' exceeds ' . $limit . ' chars');
        }
        if (mb_strlen($product['sku'], 'UTF-8') > 64) bs_fail('SKU too long: ' . $product['sku']);
        if (count($product['body']) < 3) bs_fail('Card ' . $product['sku'] . ' has fewer than 3 body paragraphs');
        if (count($product['faq']) !== 3) bs_fail('Card ' . $product['sku'] . ' must have 3 FAQ items, has ' . count($product['faq']));
        if (!isset(TARGET_CATEGORIES[$product['category']])) bs_fail('Unknown category key on ' . $product['sku']);

        // Retired identifiers and the BGC spelling must not appear in any field.
        $haystack = $product['sku'] . ' ' . $product['name'] . ' ' . $product['slug'] . ' ' . $product['meta_title']
            . ' ' . $product['meta_desc'] . ' ' . $product['meta_kw'] . ' ' . implode(' ', $product['body'])
            . ' ' . json_encode($product['faq'], JSON_UNESCAPED_UNICODE) . ' ' . json_encode($product['attributes'], JSON_UNESCAPED_UNICODE);
        foreach (RETIRED_TOKENS as $token) {
            if (mb_strpos($haystack, $token) !== false) bs_fail('Retired token «' . $token . '» appears in card ' . $product['sku']);
        }

        // The wave's attribute set must be exactly 13 and fully valued.
        $order  = bs_attribute_order($product['wave']);
        if (count($order) !== 13) bs_fail('Attribute order for wave ' . $product['wave'] . ' is not 13 rows');
        $values = array_merge(SHARED_ATTRIBUTES, $product['attributes']);
        foreach ($order as $label) if (!isset($values[$label])) bs_fail('Missing value «' . $label . '» on ' . $product['sku']);
        $extra = array_diff(array_keys($values), $order);
        if ($extra !== []) bs_fail('Extra attribute value(s) on ' . $product['sku'] . ': ' . implode(', ', $extra));
    }
    bs_log('products_in_patch', (string) count(PRODUCTS));
    bs_log('content_guards', 'ok — no retired token, no BGC, 13 attributes per SKU, 3 FAQ per SKU');

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
            'review'                => bs_table($prefix, 'review'),
        ];
        foreach ($t as $table) if (!bs_table_exists($db, $table)) bs_fail('Required table not found: ' . $table);
        bs_require_columns(bs_columns($db, $t['product']), ['product_id','model','sku','upc','ean','jan','isbn','mpn','quantity','stock_status_id','manufacturer_id','price','weight','weight_class_id','length','width','height','length_class_id','rating','status'], $t['product']);
        bs_require_columns(bs_columns($db, $t['product_code']), ['product_id','code','value'], $t['product_code']);

        // ---- preconditions ------------------------------------------------
        $manufacturer = bs_select($db, 'SELECT name FROM `' . $t['manufacturer'] . '` WHERE manufacturer_id = ?', 'i', [MANUFACTURER_ID]);
        if ($manufacturer === [] || (string) $manufacturer[0]['name'] !== MANUFACTURER) bs_fail('manufacturer_id ' . MANUFACTURER_ID . ' is not «' . MANUFACTURER . '» — stopping');
        bs_log('manufacturer_verified', MANUFACTURER_ID . ' «' . MANUFACTURER . '»');

        $stock = bs_select($db, 'SELECT name FROM `' . $t['stock_status'] . '` WHERE stock_status_id = ? AND language_id = ?', 'ii', [STOCK_STATUS_ID, LANGUAGE_ID]);
        if ($stock === [] || (string) $stock[0]['name'] !== STOCK_STATUS) bs_fail('stock_status_id ' . STOCK_STATUS_ID . ' is not «' . STOCK_STATUS . '» — stopping');
        bs_log('stock_status_verified', STOCK_STATUS_ID . ' «' . STOCK_STATUS . '»');

        $categoryIds = [];
        $assignCategories = [];
        foreach (TARGET_CATEGORIES as $key => $meta) {
            $id = bs_category_by_keyword($db, $t, $meta['keyword']);
            $rows = bs_select($db,
                'SELECT d.name AS name, c.parent_id AS parent_id FROM `' . $t['category'] . '` c'
                . ' JOIN `' . $t['category_description'] . '` d ON d.category_id = c.category_id AND d.language_id = ?'
                . ' WHERE c.category_id = ?', 'ii', [LANGUAGE_ID, $id]);
            if ($rows === [] || (string) $rows[0]['name'] !== $meta['name'] || (int) $rows[0]['parent_id'] !== $meta['parent']) {
                bs_fail('Category «' . $meta['keyword'] . '» resolved to id ' . $id . ' but is not «' . $meta['name'] . '» under parent ' . $meta['parent'] . ' — stopping');
            }
            $categoryIds[$key] = $id;
            // Dual assignment: subcategory + its parent, parent taken from the row
            // just asserted against TARGET_CATEGORIES, not hardcoded a second time.
            $assignCategories[$key] = [$id, (int) $rows[0]['parent_id']];
            if (count(array_unique($assignCategories[$key])) !== 2) bs_fail('Subcategory and parent resolved to the same id for ' . $key . ' — stopping');
            bs_log('category_verified', $key . ' -> id=' . $id . ' «' . $meta['name'] . '» parent=' . $meta['parent']
                . ' | assign=' . implode('+', $assignCategories[$key]));
        }
        bs_log('sort_order', (string) SORT_ORDER);

        $attributeMap = bs_attribute_map($db, $t);
        $needed = array_unique(array_merge(ATTRIBUTE_ORDER_FIGURES, ATTRIBUTE_ORDER_STANDS));
        $missingAttrs = [];
        foreach ($needed as $label) if (!isset($attributeMap[$label])) $missingAttrs[] = $label;
        if ($missingAttrs !== []) bs_fail('Attributes missing from group ' . ATTR_GROUP_ID . ': ' . implode(', ', $missingAttrs) . '. Run WP2 first.');
        bs_log('attributes_resolved', (string) count($needed) . ' distinct labels across both waves');

        // Retired SKUs must not already be in the catalogue.
        foreach (['FIG-PKBL-100', 'ACC-3D-PKM-140', 'ACC-3D-PKM-210'] as $retired) {
            $found = bs_product_id_by_model($db, $t, $retired);
            if ($found > 0) bs_fail('Retired SKU «' . $retired . '» exists as product ' . $found . '. Resolve it before creating the renumbered SKU.');
        }
        bs_log('retired_skus_absent', 'FIG-PKBL-100, ACC-3D-PKM-140, ACC-3D-PKM-210');

        // ---- idempotency --------------------------------------------------
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
            $slugClash = bs_select($db, 'SELECT seo_url_id FROM `' . $t['seo_url'] . '` WHERE (`keyword` = ? OR `keyword` LIKE ?) AND store_id = ?',
                'ssi', [$product['slug'], '%/' . $product['slug'], STORE_ID]);
            if ($slugClash !== []) bs_fail('SEO slug «' . $product['slug'] . '» already resolves (seo_url_id=' . $slugClash[0]['seo_url_id'] . ') — stopping');
            $codeClash = bs_select($db, 'SELECT product_id FROM `' . $t['product_code'] . '` WHERE `code` = \'SKU\' AND `value` = ?', 's', [$product['sku']]);
            if ($codeClash !== []) bs_fail('SKU «' . $product['sku'] . '» already in product_code (product ' . $codeClash[0]['product_id'] . ') — stopping');
        }

        $reviewsBefore  = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['review'] . '`', '', []);
        $productsBefore = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product'] . '`', '', []);

        bs_json_backup($backupDir, 'products_before', [
            'note'               => 'State before WP4. Rollback order is in the patch header.',
            'product_row_count'  => (int) $productsBefore[0]['c'],
            'review_row_count'   => (int) $reviewsBefore[0]['c'],
            'target_category_ids'=> $categoryIds,
            'assigned_category_ids' => $assignCategories,
            'sort_order'         => SORT_ORDER,
            'attribute_ids_used' => $attributeMap,
            'skus_to_create'     => array_column($pending, 'sku'),
            'already_present'    => $already,
        ]);
        bs_json_backup($backupDir, 'alt_texts', [
            'note' => 'Image alt texts from §4 of both wave handoffs. NOT writable by this patch: product.twig '
                    . 'renders alt="{{ heading_title }}" and stock OpenCart 4 has no per-product alt field. '
                    . 'Kept here so the agreed texts are not lost.',
            'alt'  => array_combine(array_column(PRODUCTS, 'sku'), array_column(PRODUCTS, 'alt')),
        ]);
        bs_json_backup($backupDir, 'pending_anchors', [
            'note' => 'Internal links held back by the §4.9 gate in both handoffs. Neighbour names are stored as '
                    . 'PLAIN TEXT. Add anchors only after every target page is enabled and returns 200. '
                    . 'Path form: /product/<seo-name>; the root path returns 404.',
            'anchors' => PENDING_ANCHORS,
        ]);

        $created = [];
        $today   = date('Y-m-d');

        $db->begin_transaction();
        try {
            foreach ($pending as $product) {
                $assign    = $assignCategories[$product['category']];
                $productId = bs_create_product($db, $t, $product, $assign, $attributeMap, $today);
                bs_verify_product($db, $t, $productId, $product, $assign, $attributeMap);
                $created[$product['sku']] = $productId;
                bs_log('created_product', str_pad($product['sku'], 15) . ' id=' . $productId . '  cats=' . implode('+', $assign) . '  /product/' . $product['slug']);
            }

            $reviewsAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['review'] . '`', '', []);
            if ((int) $reviewsAfter[0]['c'] !== (int) $reviewsBefore[0]['c']) bs_fail('Review row count changed — rolling back');
            $productsAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product'] . '`', '', []);
            if ((int) $productsAfter[0]['c'] !== (int) $productsBefore[0]['c'] + count($pending)) bs_fail('Product row count moved unexpectedly — rolling back');

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        bs_json_backup($backupDir, 'created_ids', [
            'note'                => 'Rollback: delete in the order given in the patch header.',
            'created_product_ids' => $created,
            'target_category_ids' => $categoryIds,
            'assigned_category_ids' => $assignCategories,
            'sort_order'          => SORT_ORDER,
            'attribute_rows_each' => 13,
        ]);

        foreach ($categoryIds as $key => $id) {
            $count = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product_to_category'] . '` WHERE category_id = ?', 'i', [$id]);
            bs_log('category_product_count', $key . ' (id=' . $id . ') = ' . $count[0]['c']);
        }

        bs_log('created_products', (string) count($created));
        bs_log('attribute_rows_written', (string) (count($created) * 13));
        bs_log('visibility', 'all created with status=0 — NOT visible, by design');
        bs_log('price_note', 'all at ' . PRICE . ' UAH placeholder — NEITHER wave handoff carries a price decision');
        bs_log('dimensions_note', 'shipping dimensions are the handoff tables — both marked "потребують підтвердження власника"');
        bs_log('anchors_note', 'neighbour names are plain text; pending anchor map saved to the backup dir');
        bs_log('alt_note', 'alt texts saved to backup JSON; theme renders alt from the product name (see header)');
        bs_log('crm_note', 'CRM-005: all 14 SKUs must exist in CRM before the owner makes the products visible');
        bs_log('next', 'clear OpenCart cache + compiled templates, then run §6 Owner QA of BOTH wave handoffs');
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
