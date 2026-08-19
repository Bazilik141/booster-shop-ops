<?php
declare(strict_types=1);

/*
 * 3D-P-CARDCONTENT — work package 3 of 4: five Pokémon keychains.
 * REV 2, 2026-08-19 — dual category assignment + sort_order 8.
 * Supersedes 3D-P-CARDCONTENT_products-keychains_20260818.php, which was never run.
 *
 * SOURCE OF TRUTH
 *   handoffs/handoff_3D-P-CARDCONTENT_five-pokemon-keychains_20260816.md
 *   Texts in §4 are FINAL and are reproduced verbatim below. Legal answers and
 *   the hardware disclaimer are agreed word for word — do not edit them here.
 *   Preflight: diagnostics/3D-P-002_3D-P-CARDCONTENT_db-preflight_20260818.md
 *
 * REQUIRES, IN ORDER
 *   WP1  3D-P-002_catalog-subcategories_20260818.php   (both stages)
 *   WP2  3D-P-CARDCONTENT_attributes-group10_20260818.php
 *   The patch refuses to run if the subcategory or any of the 13 attributes is
 *   missing. It resolves every id at runtime — nothing is hardcoded.
 *
 * WHAT THIS DOES
 *   Creates five products, each written field by field from an explicit list.
 *   Per product it writes exactly:
 *     ocp5_product              1 row
 *     ocp5_product_description  1 row
 *     ocp5_product_to_store     1 row  (store 0)
 *     ocp5_product_to_category  2 rows (Фігурки та декор 73 AND parent Pokémon 59)
 *     ocp5_product_code         1 row  (code='SKU')  — OC 4.1 SKU store
 *     ocp5_seo_url              1 row  (key='product_id')
 *     ocp5_product_attribute   13 rows (attribute_group_id = 10)
 *   Total: 5 products, 10 category rows, 65 attribute rows.
 *
 * REV 2 CHANGE — "one product = one category" IS RESCINDED
 *   Owner decision 2026-08-19 (handoff_3D-P-002 §4.3). Each product now gets
 *   TWO product_to_category rows — the subcategory and its parent — so it
 *   appears in the parent listing directly, and product.sort_order = 8.
 *   The parent id is read from the subcategory row the patch already asserts;
 *   it is not hardcoded a second time. Everything else is byte-identical to
 *   the reviewed 20260818 version: texts, SKUs, slugs, attributes, prices,
 *   visibility, shipping fields and the BR-CHARM-200 anchor gate.
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
 *   These products are quantity = 0, subtract = 1, stock_status_id = 8, so
 *   they land in TIER 2 and sit after every in-stock product no matter what
 *   sort_order says. sort_order = 8 still does real work: existing products
 *   in category 59 use sort_order 0..3, so 8 puts the 3D items last WITHIN
 *   their tier, and last again if the owner later puts them in stock and they
 *   move to tier 1. Both effects point the same way, so 8 is correct — but
 *   the tier, not the 8, is what decides position while they are preorder.
 *   No OCMOD or theme override of this model file exists in the backup.
 *   Full evidence: diagnostics/3D-P-CARDCONTENT_keychains_report_20260819.md
 *
 *   Third sort key is LCASE(pd.name) ASC, so the 3D products order
 *   ALPHABETICALLY among themselves — not "neutral", but deterministic.
 *
 * CONTENT-005 PRECEDENT — NO TEMPLATE CLONING
 *   No row is copied from an existing product. Every column is set explicitly.
 *   upc / ean / jan / isbn / mpn are written as NULL and asserted NULL after
 *   the write: these goods have no GTIN, and a cloned identifier is exactly the
 *   failure CONTENT-005 produced (wrong identifier + duplicate GTIN in the feed).
 *   No review, no rating, no product_image, no product_related, no discount,
 *   no reward, no option, no layout row is created.
 *
 * MANUFACTURER
 *   Native product field manufacturer_id = 17 (Booster Shop), asserted by name.
 *   Attribute id 20 «Виробник» (group 7) is NEVER used — it belongs to sealed
 *   goods and would render a second attribute table with a second heading.
 *
 * COMMERCIAL FIELDS — owner decisions, reproduced, not invented
 *   price          1.00 UAH for all five. Deliberate placeholder (handoff §5.2)
 *                  until Serhiy's dashboard sets real prices. The owner changes
 *                  it in admin. Products are created INVISIBLE, so the stub
 *                  price is never customer-facing.
 *   weight         50 g for all five — actual mass rounded UP to a multiple of
 *                  50. This is the SHIPPING weight and is deliberately a
 *                  different number from the «Маса» attribute (2,61–3,74 g),
 *                  which is the plastic mass shown to the buyer.
 *   dimensions     handoff §5.2 table, in CENTIMETRES.
 *   classes        length_class_id = 1 (cm) and weight_class_id = 2 (g) match
 *                  config_length_class_id / config_weight_class_id on this
 *                  install — verified, see the preflight §5.
 *
 * VISIBILITY — created NOT visible, on purpose
 *   status = 0. config_stock_checkout = 0 blocks checkout for out-of-stock
 *   items globally and the 3D preorder task is not done, so these must not go
 *   live yet (handoff §5.3).
 *
 * ⚠ ASSUMPTION THE PATCH WAS FORCED TO MAKE — quantity and stock status
 *   Handoff §5.2 says both "depend on the separate 3D preorder task" and gives
 *   no value. The patch writes quantity = 0 and stock_status_id = 8
 *   («Передзамовлення»), because that is the honest state of a made-to-order
 *   item with no stock, and it is the state the preorder task will build on.
 *   Both are inert while status = 0. If the owner wants 5 «Закінчився»,
 *   it is one edit in admin. Flagged in the report.
 *
 * ⚠ IMAGE ALT TEXT IS NOT SETTABLE HERE — NOT SKIPPED, BLOCKED
 *   Handoff §4 specifies an alt text per SKU and Owner QA asks to verify it.
 *   catalog/view/template/product/product.twig line 61 renders
 *   alt="{{ heading_title }}" — the alt is the product NAME, and stock
 *   OpenCart 4 has no per-product alt field. The specified alt texts therefore
 *   cannot be written by a product patch; doing so needs a theme change, which
 *   is a separate work package. The texts are preserved verbatim in
 *   _patch_backups/<patch>-<ts>/db/alt_texts.json so nothing is lost.
 *
 * INTERNAL LINKS — the BR-CHARM-100 gate is CLOSED
 *   Handoff §4.2 allows one anchor to /product/brelok-kliker-charmander-pokemon-3d-druk
 *   ONLY if BR-CHARM-200 is already published and returns 200. BR-CHARM-200 is
 *   explicitly out of scope and does not exist, so the gate is closed and the
 *   plain-text variant of that answer is used, exactly as §4.2 specifies.
 *
 * ROLLBACK
 *   Actual product ids are written to
 *   _patch_backups/<patch>-<ts>/db/created_ids.json. Delete in this order:
 *     DELETE FROM ocp5_product_attribute   WHERE product_id IN (<created_product_ids>);
 *     DELETE FROM ocp5_seo_url             WHERE `key` = 'product_id' AND `value` IN (<created_product_ids as strings>);
 *     DELETE FROM ocp5_product_code        WHERE product_id IN (<created_product_ids>);
 *     DELETE FROM ocp5_product_to_category WHERE product_id IN (<created_product_ids>);   -- removes BOTH rows per product (subcategory 73 and parent 59)
 *     DELETE FROM ocp5_product_to_store    WHERE product_id IN (<created_product_ids>);
 *     DELETE FROM ocp5_product_description WHERE product_id IN (<created_product_ids>);
 *     DELETE FROM ocp5_product             WHERE product_id IN (<created_product_ids>);
 *   Expected ids (NOT hardcoded, orientation only): 125..129.
 *   Deleting by product_id removes both category rows at once; no existing
 *   product_to_category row is ever touched, so accessories 95-100/112-114
 *   and their owner-set 70+71(+72) assignments are unaffected.
 *
 * NOT TOUCHED
 *   Every existing product and category, the attribute definitions themselves,
 *   sitemap, robots, .htaccess, checkout, payment, CRM.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME      = '3D-P-CARDCONTENT_products-keychains_20260819';
const LANGUAGE_ID     = 4;
const STORE_ID        = 0;
const ATTR_GROUP_ID   = 10;
const MANUFACTURER_ID = 17;
const MANUFACTURER    = 'Booster Shop';
const TAX_CLASS_ID    = 0;
const LENGTH_CLASS_ID = 1; // cm
const WEIGHT_CLASS_ID = 2; // g
const STOCK_STATUS_ID = 8; // Передзамовлення — see the assumption note in the header
const STOCK_STATUS    = 'Передзамовлення';
const QUANTITY        = 0;
const PRICE           = '1.0000';
const STATUS          = 0;  // not visible
const SORT_ORDER      = 8;  // owner decision 2026-08-19 — see the DISPLAY ORDER note in the header

// The subcategory these five belong to, resolved by its full SEO path.
const TARGET_CATEGORY_KEYWORD = 'Pokemon/figurky-ta-dekor-pokemon';
const TARGET_CATEGORY_NAME    = 'Фігурки та декор';
const TARGET_PARENT_ID        = 59;

// Attribute labels used by this wave — 13 rows, resolved by name inside group 10.
// «Матеріал фурнітури» is present; «Призначення» and «Сумісність» are NOT
// (handoff §3: "Полів «Магніти» і «Сумісність» у цих товарах немає").
const ATTRIBUTE_ORDER = [
    'Тип виробу',
    'Країна виготовлення',
    'Спосіб виготовлення',
    'Матеріал',
    'Колір',
    'Матеріал фурнітури',
    'Розміри',
    'Маса',
    'Комплектація',
    'Рухомі елементи',
    'Вікове позиціонування',
    'Типовий строк виготовлення при відсутності на складі',
    'Може зустрічатися в Mystery Box Item',
];

// Shared attribute values (handoff §3).
const SHARED_ATTRIBUTES = [
    'Тип виробу'                                           => 'брелок',
    'Країна виготовлення'                                  => 'Україна',
    'Спосіб виготовлення'                                  => 'пошаровий 3D-друк',
    'Матеріал'                                             => 'Пластик PLA',
    'Матеріал фурнітури'                                   => 'метал',
    'Комплектація'                                         => '1 брелок із металевою фурнітурою',
    'Рухомі елементи'                                      => 'немає',
    'Вікове позиціонування'                                => '14+',
    'Типовий строк виготовлення при відсутності на складі'  => '1–2 робочих дні',
    'Може зустрічатися в Mystery Box Item'                 => 'Ні',
];

/** Five products. Texts verbatim from handoff §4. */
const PRODUCTS = [
    [
        'sku'        => 'BR-MEW-100',
        'name'       => 'Брелок Мью (Pokémon) — 3D-друк',
        'slug'       => 'brelok-mew-pokemon-3d-druk',
        'faq_id'     => 'prod-br-mew-100',
        'alt'        => 'Брелок Мью, 3D-друк, Booster Shop',
        'meta_title' => 'Брелок Мью (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Брелок Мью (Pokémon) у рожевому мінімалістичному дизайні, 40×38 мм. 3D-друк із PLA у Booster Shop — купити в Україні, виготовлення 1–2 дні.',
        'meta_kw'    => 'брелок Мью Pokémon, брелок Mew 3D-друк, брелок Мью купити Україна',
        'body'       => [
            'Брелок Мью (Pokémon) побудований майже без промальованих рис: образ тримається на характерному контурі голови, великих вирізах очей і рожевому кольорі. Через це Мью (Mew) виглядає не як зменшена фігурка, а як лаконічний графічний символ персонажа.',
            'Формат 40×38×4 мм робить модель компактною, але помітно щільнішою за зовсім тонкі пласкі підвіски. Вирізи залишають силует легким візуально, а на ключах чи рюкзаку рожевий контур помітний навіть здалеку — колір тут працює не просто як оформлення, а як частина впізнаваного образу.',
            'Кожен екземпляр друкується у Booster Shop в Україні пошаровим 3D-друком із PLA, тому на поверхні може читатися характерна тонка фактура шарів. Металева фурнітура комплектується з наявного запасу: кільце, ланцюжок або карабін у конкретному виробі можуть відрізнятися від того, що показано на фото.',
        ],
        'faq'        => [
            ['Це офіційний брелок Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'Лише брелок Мью з металевою фурнітурою. Картки, фігурки, підставки, бустери та інший декор на фото до комплекту не входять.'],
        ],
        'attributes' => [
            'Колір'   => 'рожевий',
            'Розміри' => '40×38×4 мм',
            'Маса'    => 'орієнтовно 3,37 г (без фурнітури)',
        ],
        'ship'       => ['weight' => '50', 'length' => '4', 'width' => '4', 'height' => '1'],
    ],
    [
        'sku'        => 'BR-CHARM-100',
        'name'       => 'Брелок Чармандер (Pokémon) — 3D-друк',
        'slug'       => 'brelok-charmander-pokemon-3d-druk',
        'faq_id'     => 'prod-br-charm-100',
        'alt'        => 'Брелок Чармандер, 3D-друк, Booster Shop',
        'meta_title' => 'Брелок Чармандер (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Брелок Чармандер (Pokémon) — легкий плаский 3D-друк із PLA у помаранчевому кольорі, 35×40 мм. Купити в Україні у Booster Shop.',
        'meta_kw'    => 'брелок Чармандер Pokémon, брелок Charmander 3D-друк, брелок Чармандер купити Україна',
        'body'       => [
            'Брелок Чармандер (Pokémon) передає персонажа буквально кількома деталями: великими вирізами очей, характерною лінією мордочки та впізнаваним помаранчевим силуетом. Чармандер (Charmander) лишається очевидним навіть без складного рельєфу, а сам дизайн виходить чистим і графічним.',
            'Це статична версія для тих, кому подобається саме легкий плаский формат. Якщо потрібна модель із тактильною механікою, окремо є брелок-клікер Чармандер; тут же весь акцент залишається на силуеті персонажа та компактності.',
            'Брелок виготовляється у Booster Shop в Україні методом пошарового 3D-друку з PLA; подекуди на поверхні можуть залишатися ледь помітні сліди підтримок. Фурнітура на фото показана як один із можливих варіантів — фактичне металеве кільце, ланцюжок або карабін можуть відрізнятися.',
        ],
        // Third answer is the PLAIN-TEXT variant: the BR-CHARM-200 gate is closed.
        'faq'        => [
            ['Це офіційний брелок Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'У комплект входить лише брелок Чармандер із металевою фурнітурою. Інші предмети та декор, які можуть бути присутні на фото, до комплекту не входять.'],
            ['Чим він відрізняється від брелока-клікера Чармандер?', 'Ця модель пласка й статична. Брелок-клікер Чармандер має об\'ємніший корпус і окрему натискну fidget-механіку.'],
        ],
        'attributes' => [
            'Колір'   => 'помаранчевий',
            'Розміри' => '35×40×2,5 мм',
            'Маса'    => 'орієнтовно 2,61 г (без фурнітури)',
        ],
        'ship'       => ['weight' => '50', 'length' => '4', 'width' => '4', 'height' => '1'],
    ],
    [
        'sku'        => 'BR-SQUIR-100',
        'name'       => 'Брелок Сквіртл (Pokémon) — 3D-друк',
        'slug'       => 'brelok-squirtle-pokemon-3d-druk',
        'faq_id'     => 'prod-br-squir-100',
        'alt'        => 'Брелок Сквіртл, 3D-друк, Booster Shop',
        'meta_title' => 'Брелок Сквіртл (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Брелок Сквіртл (Pokémon) із м\'яким округлим силуетом у блакитному кольорі, 40×37,5 мм. 3D-друк PLA — купити в Україні у Booster Shop.',
        'meta_kw'    => 'брелок Сквіртл Pokémon, брелок Squirtle 3D-друк, брелок Сквіртл купити Україна',
        'body'       => [
            'У брелоку Сквіртл (Pokémon) характер персонажа читається через максимально м\'які форми: округлу голову, великі вирізи очей і тонку усмішку. Блакитний Сквіртл (Squirtle) виглядає спокійніше й доброзичливіше за більш ламані силуети серії, хоча деталей тут зовсім небагато.',
            'Товщина лише 2,5 мм підтримує саме цей легкий графічний характер дизайну. Широкі відкриті очі та плавний нижній контур не перевантажують маленьку форму, тому модель добре читається навіть без додаткового рельєфу чи багатоколірних елементів.',
            'Виріб друкується у Booster Shop в Україні з PLA окремими партіями, тому відтінок блакитного між партіями може трохи відрізнятися. Металева фурнітура ставиться з наявного запасу, тож конкретний тип кріплення може не збігатися з фотографією.',
        ],
        'faq'        => [
            ['Це офіційний брелок Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'Лише брелок Сквіртл із металевою фурнітурою. Картки, бустери, фігурки, підставки та інші предмети з фотографій не є частиною комплекту.'],
        ],
        'attributes' => [
            'Колір'   => 'блакитний',
            'Розміри' => '40×37,5×2,5 мм',
            'Маса'    => 'орієнтовно 2,75 г (без фурнітури)',
        ],
        'ship'       => ['weight' => '50', 'length' => '4', 'width' => '4', 'height' => '1'],
    ],
    [
        'sku'        => 'BR-BULB-100',
        'name'       => 'Брелок Бульбазавр (Pokémon) — 3D-друк',
        'slug'       => 'brelok-bulbasaur-pokemon-3d-druk',
        'faq_id'     => 'prod-br-bulb-100',
        'alt'        => 'Брелок Бульбазавр, 3D-друк, Booster Shop',
        'meta_title' => 'Брелок Бульбазавр (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Брелок Бульбазавр (Pokémon) із виразним зеленим силуетом, 40×35 мм. 3D-друк із PLA у Booster Shop — купити в Україні, виготовлення 1–2 дні.',
        'meta_kw'    => 'брелок Бульбазавр Pokémon, брелок Bulbasaur 3D-друк, брелок Бульбазавр купити Україна',
        'body'       => [
            'Брелок Бульбазавр (Pokémon) має найбільш ламаний силует у цій п\'ятірці: загострені вуха, широку форму голови та характерні вирізи на лобі. Разом із зеленим кольором цього вистачає, щоб Бульбазавр (Bulbasaur) упізнавався без складної деталізації.',
            'Форма тут важливіша за кількість декоративних елементів. Невисокий широкий контур робить брелок візуально щільним і добре помітним на сумці чи рюкзаку, а вирізи всередині силуету не дають йому перетворитися на суцільну зелену пластину.',
            'Модель пошарово друкується у Booster Shop в Україні з PLA, тому на поверхні природно помітна тонка структура друку, а відтінок зеленого між партіями може незначно змінюватися. Конкретний тип металевої фурнітури залежить від наявного запасу й може відрізнятися від фото.',
        ],
        'faq'        => [
            ['Це офіційний брелок Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'До комплекту входить лише брелок Бульбазавр із металевою фурнітурою. Картки, фігурки, підставки, бустери та декор на фото до комплекту не входять і показані лише для оформлення.'],
        ],
        'attributes' => [
            'Колір'   => 'зелений',
            'Розміри' => '40×35×2,5 мм',
            'Маса'    => 'орієнтовно 2,64 г (без фурнітури)',
        ],
        'ship'       => ['weight' => '50', 'length' => '4', 'width' => '4', 'height' => '1'],
    ],
    [
        'sku'        => 'BR-PIKA-100',
        'name'       => 'Брелок Пікачу (Pokémon) — 3D-друк',
        'slug'       => 'brelok-pikachu-pokemon-3d-druk',
        'faq_id'     => 'prod-br-pika-100',
        'alt'        => 'Брелок Пікачу, 3D-друк, Booster Shop',
        'meta_title' => 'Брелок Пікачу (Pokémon) — 3D-друк | Booster Shop',
        'meta_desc'  => 'Брелок Пікачу (Pokémon) у жовтому кольорі з великим впізнаваним силуетом, 55,5×43,4 мм. 3D-друк PLA — купити в Україні у Booster Shop.',
        'meta_kw'    => 'брелок Пікачу Pokémon, брелок Pikachu 3D-друк, брелок Пікачу купити Україна',
        'body'       => [
            'У брелоку Пікачу (Pokémon) головну роль грає сам силует: довгі вуха, округла мордочка, вирізи очей і щік складаються у знайомий образ ще до будь-яких дрібних деталей. Жовтий колір завершує цей мінімалістичний Пікачу (Pikachu) і робить його найпомітнішим візуально серед п\'яти моделей серії.',
            'При розмірі 55,5×43,4×5 мм модель відчутно більша за інші брелоки цієї п\'ятірки, але пластикова частина важить лише близько 3,74 г. Великі вирізи та відкритий простір навколо вух дають Пікачу масштаб і виразний контур без зайвої масивності.',
            'Пікачу друкується у Booster Shop в Україні пошарово з PLA, тому на поверхні може бути помітна характерна структура шарів; для цієї технології це природна риса й вона не впливає на міцність виробу. Фурнітура комплектується окремо з поточного запасу, тому її фактичний тип може відрізнятися від фотографії.',
        ],
        'faq'        => [
            ['Це офіційний брелок Pokémon?', 'Ні. Це 3D-друкований виріб Booster Shop у тематиці Pokémon. Booster Shop не є ліцензіатом, партнером чи афілійованою особою правовласника.'],
            ['Що входить у комплект?', 'Лише брелок Пікачу з металевою фурнітурою. Будь-які картки, фігурки, підставки або інший реквізит на фотографіях до комплекту не входять.'],
        ],
        'attributes' => [
            'Колір'   => 'жовтий',
            'Розміри' => '55,5×43,4×5 мм',
            'Маса'    => 'орієнтовно 3,74 г (без фурнітури)',
        ],
        'ship'       => ['weight' => '50', 'length' => '6', 'width' => '5', 'height' => '1'],
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

/** Descriptions on this install are stored entity-encoded with ENT_COMPAT. */
function bs_encode_html(string $html): string {
    return htmlspecialchars($html, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}

/** Product FAQ accordion, byte-shaped like the newest live products (120..124). */
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

/**
 * Product description: body paragraphs then the FAQ section.
 * No H2 heading is invented — the card texts are final and contain none.
 */
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

/** Create one product, every column written explicitly. Returns the new id. */
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

    $values = array_merge(SHARED_ATTRIBUTES, $product['attributes']);
    foreach (ATTRIBUTE_ORDER as $label) {
        if (!isset($values[$label])) bs_fail('No value for attribute «' . $label . '» on ' . $sku);
        if (!isset($attributeMap[$label])) bs_fail('Attribute «' . $label . '» is missing from group ' . ATTR_GROUP_ID . '. Run WP2 first.');
        bs_exec($db,
            'INSERT INTO `' . $t['product_attribute'] . '` (`product_id`, `attribute_id`, `language_id`, `text`) VALUES (?, ?, ?, ?)',
            'iiis', [$productId, $attributeMap[$label], LANGUAGE_ID, $values[$label]]);
    }
    $extra = array_diff(array_keys($values), ATTRIBUTE_ORDER);
    if ($extra !== []) bs_fail('Unused attribute value(s) on ' . $sku . ': ' . implode(', ', $extra));

    return $productId;
}

/** Post-write assertions on a single product. Runs inside the transaction. */
function bs_verify_product(mysqli $db, array $t, int $productId, array $product, array $categoryIds): void {
    $rows = bs_select($db,
        'SELECT model, sku, upc, ean, jan, isbn, mpn, manufacturer_id, status, quantity, stock_status_id,'
        . ' price, weight, weight_class_id, length, width, height, length_class_id, rating, sort_order'
        . ' FROM `' . $t['product'] . '` WHERE product_id = ?', 'i', [$productId]);
    if ($rows === []) bs_fail('Product ' . $productId . ' vanished after insert — rolling back');
    $row = $rows[0];

    foreach (['upc', 'ean', 'jan', 'isbn', 'mpn'] as $identifier) {
        if ($row[$identifier] !== null && $row[$identifier] !== '') {
            bs_fail('CONTENT-005 guard: ' . $product['sku'] . ' has a non-empty ' . $identifier . ' («' . (string) $row[$identifier] . '») — rolling back');
        }
    }
    if ((string) $row['model'] !== $product['sku']) bs_fail('Model mismatch on ' . $product['sku'] . ' — rolling back');
    if ((string) $row['sku'] !== $product['sku']) bs_fail('SKU column mismatch on ' . $product['sku'] . ' — rolling back');
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

    $attrCount = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product_attribute'] . '` WHERE product_id = ?', 'i', [$productId]);
    if ((int) $attrCount[0]['c'] !== count(ATTRIBUTE_ORDER)) {
        bs_fail($product['sku'] . ' has ' . $attrCount[0]['c'] . ' attribute rows, expected ' . count(ATTRIBUTE_ORDER) . ' — rolling back');
    }
    $foreign = bs_select($db,
        'SELECT pa.attribute_id AS attribute_id FROM `' . $t['product_attribute'] . '` pa'
        . ' JOIN `' . $t['attribute'] . '` a ON a.attribute_id = pa.attribute_id'
        . ' WHERE pa.product_id = ? AND a.attribute_group_id <> ?', 'ii', [$productId, ATTR_GROUP_ID]);
    if ($foreign !== []) {
        bs_fail($product['sku'] . ' has attribute(s) outside group ' . ATTR_GROUP_ID . ' (id=' . $foreign[0]['attribute_id'] . ') — rolling back');
    }

    $seo = bs_select($db, 'SELECT keyword FROM `' . $t['seo_url'] . '` WHERE `key` = \'product_id\' AND `value` = ?', 's', [(string) $productId]);
    if (count($seo) !== 1 || (string) $seo[0]['keyword'] !== $product['slug']) {
        bs_fail('SEO URL mismatch on ' . $product['sku'] . ' — rolling back');
    }
    $code = bs_select($db, 'SELECT code, value FROM `' . $t['product_code'] . '` WHERE product_id = ?', 'i', [$productId]);
    if (count($code) !== 1 || (string) $code[0]['code'] !== 'SKU' || (string) $code[0]['value'] !== $product['sku']) {
        bs_fail('product_code mismatch on ' . $product['sku'] . ' — rolling back');
    }
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

    // Static content guards before touching the database.
    $skus = array_column(PRODUCTS, 'sku');
    $slugs = array_column(PRODUCTS, 'slug');
    if (count(array_unique($skus)) !== count($skus)) bs_fail('Duplicate SKU in PRODUCTS');
    if (count(array_unique($slugs)) !== count($slugs)) bs_fail('Duplicate slug in PRODUCTS');
    foreach (PRODUCTS as $product) {
        foreach (['name' => 255, 'meta_title' => 255, 'meta_desc' => 255, 'meta_kw' => 255] as $field => $limit) {
            if (mb_strlen($product[$field], 'UTF-8') > $limit) bs_fail('Field ' . $field . ' of ' . $product['sku'] . ' exceeds ' . $limit . ' chars');
        }
        if (mb_strlen($product['sku'], 'UTF-8') > 64) bs_fail('SKU too long: ' . $product['sku']);
        if (count($product['body']) < 3) bs_fail('Card ' . $product['sku'] . ' has fewer than 3 body paragraphs');
        if ($product['faq'] === []) bs_fail('Card ' . $product['sku'] . ' has no FAQ');
    }
    bs_log('products_in_patch', (string) count(PRODUCTS));

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
            'category_description'  => bs_table($prefix, 'category_description'),
            'category'              => bs_table($prefix, 'category'),
            'manufacturer'          => bs_table($prefix, 'manufacturer'),
            'stock_status'          => bs_table($prefix, 'stock_status'),
            'review'                => bs_table($prefix, 'review'),
        ];
        foreach ($t as $table) if (!bs_table_exists($db, $table)) bs_fail('Required table not found: ' . $table);

        bs_require_columns(bs_columns($db, $t['product']), ['product_id','master_id','model','sku','upc','ean','jan','isbn','mpn','quantity','stock_status_id','image','manufacturer_id','price','tax_class_id','date_available','weight','weight_class_id','length','width','height','length_class_id','subtract','minimum','rating','sort_order','status'], $t['product']);
        bs_require_columns(bs_columns($db, $t['product_code']), ['product_id','code','value'], $t['product_code']);
        bs_require_columns(bs_columns($db, $t['product_attribute']), ['product_id','attribute_id','language_id','text'], $t['product_attribute']);

        // --- preconditions -------------------------------------------------
        $manufacturer = bs_select($db, 'SELECT name FROM `' . $t['manufacturer'] . '` WHERE manufacturer_id = ?', 'i', [MANUFACTURER_ID]);
        if ($manufacturer === [] || (string) $manufacturer[0]['name'] !== MANUFACTURER) {
            bs_fail('manufacturer_id ' . MANUFACTURER_ID . ' is not «' . MANUFACTURER . '» — stopping');
        }
        bs_log('manufacturer_verified', MANUFACTURER_ID . ' «' . MANUFACTURER . '»');

        $stock = bs_select($db, 'SELECT name FROM `' . $t['stock_status'] . '` WHERE stock_status_id = ? AND language_id = ?', 'ii', [STOCK_STATUS_ID, LANGUAGE_ID]);
        if ($stock === [] || (string) $stock[0]['name'] !== STOCK_STATUS) {
            bs_fail('stock_status_id ' . STOCK_STATUS_ID . ' is not «' . STOCK_STATUS . '» — stopping');
        }
        bs_log('stock_status_verified', STOCK_STATUS_ID . ' «' . STOCK_STATUS . '»');

        $categoryId = bs_category_by_keyword($db, $t, TARGET_CATEGORY_KEYWORD);
        $categoryRows = bs_select($db,
            'SELECT d.name AS name, c.parent_id AS parent_id FROM `' . $t['category'] . '` c'
            . ' JOIN `' . $t['category_description'] . '` d ON d.category_id = c.category_id AND d.language_id = ?'
            . ' WHERE c.category_id = ?', 'ii', [LANGUAGE_ID, $categoryId]);
        if ($categoryRows === [] || (string) $categoryRows[0]['name'] !== TARGET_CATEGORY_NAME || (int) $categoryRows[0]['parent_id'] !== TARGET_PARENT_ID) {
            bs_fail('Category resolved from «' . TARGET_CATEGORY_KEYWORD . '» is not «' . TARGET_CATEGORY_NAME
                . '» under parent ' . TARGET_PARENT_ID . ' — stopping');
        }
        bs_log('category_verified', $categoryId . ' «' . TARGET_CATEGORY_NAME . '» parent=' . TARGET_PARENT_ID . ' url=/catalog/' . TARGET_CATEGORY_KEYWORD);

        // Dual assignment: the subcategory plus its parent, taken from the row we
        // just asserted — the parent id is not hardcoded a second time.
        $assignCategories = [$categoryId, (int) $categoryRows[0]['parent_id']];
        if (count(array_unique($assignCategories)) !== 2) bs_fail('Subcategory and parent resolved to the same id — stopping');
        bs_log('assign_categories', implode(' + ', $assignCategories) . ' (subcategory + parent)');
        bs_log('sort_order', (string) SORT_ORDER);

        $attributeMap = bs_attribute_map($db, $t);
        $missingAttrs = [];
        foreach (ATTRIBUTE_ORDER as $label) if (!isset($attributeMap[$label])) $missingAttrs[] = $label;
        if ($missingAttrs !== []) {
            bs_fail('Attributes missing from group ' . ATTR_GROUP_ID . ': ' . implode(', ', $missingAttrs) . '. Run WP2 first.');
        }
        foreach (ATTRIBUTE_ORDER as $label) bs_log('attribute_resolved', $label . ' -> id=' . $attributeMap[$label]);

        // --- idempotency ---------------------------------------------------
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

        // --- collision guards on the pending set ---------------------------
        foreach ($pending as $product) {
            $slugClash = bs_select($db, 'SELECT seo_url_id, `key`, `keyword` FROM `' . $t['seo_url'] . '` WHERE (`keyword` = ? OR `keyword` LIKE ?) AND store_id = ?',
                'ssi', [$product['slug'], '%/' . $product['slug'], STORE_ID]);
            if ($slugClash !== []) bs_fail('SEO slug «' . $product['slug'] . '» already resolves (seo_url_id=' . $slugClash[0]['seo_url_id'] . ') — stopping');

            $codeClash = bs_select($db, 'SELECT product_id FROM `' . $t['product_code'] . '` WHERE `code` = \'SKU\' AND `value` = ?', 's', [$product['sku']]);
            if ($codeClash !== []) bs_fail('SKU «' . $product['sku'] . '» already in product_code (product ' . $codeClash[0]['product_id'] . ') — stopping');
        }

        $reviewsBefore = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['review'] . '`', '', []);
        $productsBefore = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product'] . '`', '', []);

        bs_json_backup($backupDir, 'products_before', [
            'note'                     => 'State before WP3. Rollback order is in the patch header.',
            'product_row_count'        => (int) $productsBefore[0]['c'],
            'review_row_count'         => (int) $reviewsBefore[0]['c'],
            'target_category_id'       => $categoryId,
            'assigned_category_ids'    => $assignCategories,
            'sort_order'               => SORT_ORDER,
            'attribute_ids_used'       => $attributeMap,
            'skus_to_create'           => array_column($pending, 'sku'),
            'already_present'          => $already,
        ]);
        bs_json_backup($backupDir, 'alt_texts', [
            'note' => 'Image alt texts from handoff §4. NOT writable by this patch: '
                    . 'catalog/view/template/product/product.twig renders alt="{{ heading_title }}" and stock '
                    . 'OpenCart 4 has no per-product alt field. Kept here so the agreed texts are not lost.',
            'alt'  => array_combine(array_column(PRODUCTS, 'sku'), array_column(PRODUCTS, 'alt')),
        ]);

        $created = [];
        $today   = date('Y-m-d');

        $db->begin_transaction();
        try {
            foreach ($pending as $product) {
                $productId = bs_create_product($db, $t, $product, $assignCategories, $attributeMap, $today);
                bs_verify_product($db, $t, $productId, $product, $assignCategories);
                $created[$product['sku']] = $productId;
                bs_log('created_product', $product['sku'] . ' (id=' . $productId . ') /product/' . $product['slug']);
            }

            $reviewsAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['review'] . '`', '', []);
            if ((int) $reviewsAfter[0]['c'] !== (int) $reviewsBefore[0]['c']) bs_fail('Review row count changed — rolling back');

            $productsAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product'] . '`', '', []);
            if ((int) $productsAfter[0]['c'] !== (int) $productsBefore[0]['c'] + count($pending)) {
                bs_fail('Product row count moved by an unexpected amount — rolling back');
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        bs_json_backup($backupDir, 'created_ids', [
            'note'                 => 'Rollback: delete in the order given in the patch header.',
            'created_product_ids'  => $created,
            'target_category_id'   => $categoryId,
            'assigned_category_ids'=> $assignCategories,
            'sort_order'           => SORT_ORDER,
            'attribute_rows_each'  => count(ATTRIBUTE_ORDER),
        ]);

        bs_log('created_products', (string) count($created));
        bs_log('attribute_rows_written', (string) (count($created) * count(ATTRIBUTE_ORDER)));
        bs_log('visibility', 'all created with status=0 — NOT visible, by design');
        bs_log('price_note', 'all at ' . PRICE . ' UAH placeholder — owner sets real prices in admin');
        bs_log('alt_note', 'alt texts saved to backup JSON; theme renders alt from the product name (see header)');
        bs_log('crm_note', 'CRM-005: these SKUs must exist in CRM before the owner makes the products visible');
        bs_log('next', 'clear OpenCart cache + compiled templates, then run the handoff §6 Owner QA');
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
