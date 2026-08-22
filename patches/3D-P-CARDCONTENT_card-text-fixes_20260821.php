<?php
declare(strict_types=1);

/*
 * 3D-P-CARDCONTENT / CONTENT-QUALITY — WP1 of 3: text fixes on existing content.
 * 2026-08-21.
 *
 * SOURCE OF TRUTH
 *   handoffs/handoff_CONTENT-QUALITY_card-content-patch_20260821.md   §2
 *   CONTENT-QUALITY_3D-card-fixes_20260821.md  (ChatGPT payload, owner's Cowork chat)
 *   diagnostics/CONTENT-QUALITY_corrections_20260821.md  — OVERRIDES the payload
 *   DB baseline: backup-8.21.2026_22-06-47_boosters.tar.gz -> mysql/boosters_ocart49.sql
 *
 * WHAT THIS DOES — UPDATE ONLY, NO INSERT, NO DELETE OF A ROW
 *   1. 19 products, product_id 125..143, ocp5_product_description (language_id = 4):
 *      - `description`: the three body paragraphs are replaced by the accepted
 *        text and gain one <h2> and exactly one <strong>. The FAQ <section> is
 *        carried over BYTE-FOR-BYTE from the live value except on the five
 *        products whose FAQ text is explicitly changed below.
 *      - ACC-3D-PKM-130 (139): `meta_title`  (corrections §4.3)
 *      - FIG-MEW-100    (132): `meta_description` (payload §4)
 *   2. ocp5_category_description (language_id = 4):
 *      - 73: `name` + `meta_keyword`   (corrections §4.5)
 *      - 74: `name`
 *   3. ocp5_attribute_description (language_id = 4), attribute_id 44:
 *      `Може зустрічатися в Mystery Box Item` -> `Може трапитись у Mystery Box`
 *
 * WHAT THIS DOES NOT TOUCH — handoff §2.2
 *   ocp5_product (no column at all, date_modified included), seo_url, status,
 *   price, quantity, stock_status_id, sort_order, product_to_category,
 *   product_attribute values, FIG-CHARM-001 / product 118, anything outside
 *   125..143, category 71/72 text, category 73/74 description / meta_title /
 *   meta_description, and the enabled flag of categories 73 and 74.
 *
 * BR-CHARM-100 GATE — RESOLVED, VARIANT A
 *   handoff §2.3: variant B only if a product with ocp5_product_code.value =
 *   'BR-CHARM-200' exists and is status = 1. The 2026-08-21 backup has no such
 *   row (only BR-CHARM-100 / product 126), so VARIANT A is used and the third
 *   FAQ item — «Чим він відрізняється від брелока-клікера Чармандер?» — is
 *   deleted. The patch re-checks this at runtime and REFUSES to run if
 *   BR-CHARM-200 has appeared in the meantime, because then the wrong variant
 *   would be written.
 *
 * ⚠ TWO PUBLICATION BLOCKERS ARE WRITTEN INTO THE TEXT ON PURPOSE
 *   ACC-3D-PKM-700 (142) FAQ [2] and ACC-3D-PKM-710 (143) FAQ [2] become
 *   `{{потрібні дані: …}}`. That is what the payload specifies: the capacity is
 *   unknown and must not be published as an assumption. Both products are
 *   status = 0 today, so nothing reaches a customer. The patch REFUSES to run
 *   if either product is visible, and prints a PLACEHOLDER warning at the end.
 *   Get the two numbers before enabling those two products.
 *
 * APOSTROPHE NORMALISATION — deliberate, mechanical
 *   The payload uses U+2019 (’). All nineteen live descriptions use the ASCII
 *   apostrophe ('), 20 occurrences, zero U+2019, and so do the category texts
 *   71/72/73/74. Every body string below therefore uses ASCII '. Typography
 *   only; no wording changes. Recorded in the report.
 *
 * ENCODING — verified against the live rows, not assumed
 *   ocp5_product_description.description stores ENTITY-ENCODED html
 *   (`&lt;p&gt;…`), produced by htmlspecialchars(ENT_COMPAT). Blocks are joined
 *   with CRLF + blank line. bs_encode_html() below reproduces it exactly; the
 *   patch proves it by re-encoding the live FAQ tail and comparing byte-for-byte
 *   before it writes anything.
 *
 * PRE-WRITE STATE GATE (convention C2)
 *   Every one of the 19 descriptions must hash to the value recorded from the
 *   2026-08-21 backup (BEFORE_SHA256). If any row was edited in admin after that
 *   export the patch stops with the product named and writes nothing. Re-export
 *   the backup and have the patch refreshed — do not force it.
 *
 * IDEMPOTENCY (C5)
 *   If every target already holds the new value the patch logs
 *   already_applied=yes and self-deletes without writing.
 *
 * BACKUP (C3)
 *   _patch_backups/<patch>-<ts>/db/before.json   every previous value, verbatim
 *   _patch_backups/<patch>-<ts>/db/rollback.sql  ready-to-run, complete, written
 *                                                BEFORE the first UPDATE
 *
 * ============================ ROLLBACK ============================
 * Full rollback = run _patch_backups/<patch>-<ts>/db/rollback.sql. It is
 * generated from the live values read immediately before the write and covers
 * all 19 descriptions plus every field below. The short fields are also spelled
 * out here so the patch is self-contained even if the backup directory is lost;
 * the 19 description blobs are ~76 KB and are not inlined — they are in
 * rollback.sql, in before.json, and in the 2026-08-21 cPanel backup.
 *
 *   UPDATE `ocp5_product_description`
 *      SET `meta_title` = 'Підставка для картки в магнітному кейсі — 3D-друк | Booster Shop'
 *    WHERE product_id = 139 AND language_id = 4;
 *
 *   UPDATE `ocp5_product_description`
 *      SET `meta_description` = 'Фігурка Мью в покеболі (Pokémon): рожева сфера з вухами та хвостом, 9 см. 3D-друк із PLA власного виробництва, Booster Shop.'
 *    WHERE product_id = 132 AND language_id = 4;
 *
 *   UPDATE `ocp5_category_description`
 *      SET `name` = 'Фігурки та декор',
 *          `meta_keyword` = 'брелок покемон, фігурка покемон, брелок Пікачу, 3D друк покемон, брелоки Pokemon купити Україна'
 *    WHERE category_id = 73 AND language_id = 4;
 *
 *   UPDATE `ocp5_category_description`
 *      SET `name` = 'Фігурки та декор'
 *    WHERE category_id = 74 AND language_id = 4;
 *
 *   UPDATE `ocp5_attribute_description`
 *      SET `name` = 'Може зустрічатися в Mystery Box Item'
 *    WHERE attribute_id = 44 AND language_id = 4;
 *
 * No row is created or deleted by this patch, so rollback needs no DELETE and
 * no INSERT. ocp5_product_attribute is never written — the 19 rows that carry
 * attribute_id 44 keep their «Так»/«Ні» values; the patch asserts the count
 * before and after.
 * ==================================================================
 *
 * DB BACKUP IS THE OWNER'S STEP — convention C3 covers files, not the database.
 * Take a MySQL dump (cPanel -> Backup -> Download a MySQL Database Backup)
 * before running this. The patch runs inside one transaction and rolls itself
 * back on any failure, but that does not replace a dump.
 *
 * RUN:  upload to ~/public_html, then  php 3D-P-CARDCONTENT_card-text-fixes_20260821.php
 * The patch self-deletes on success (C7).
 */

const PATCH_NAME  = '3D-P-CARDCONTENT_card-text-fixes_20260821';
const LANGUAGE_ID = 4;
const STORE_ID    = 0;

const ATTRIBUTE_ID_MYSTERY = 44;
const ATTRIBUTE_OLD_NAME   = 'Може зустрічатися в Mystery Box Item';
const ATTRIBUTE_NEW_NAME   = 'Може трапитись у Mystery Box';
const ATTRIBUTE_ROW_COUNT  = 19;   // ocp5_product_attribute rows with attribute_id = 44

const CHARM_GATE_SKU = 'BR-CHARM-200';
const PLACEHOLDER_MARKER = '{{потрібні дані';

// --------------------------------------------------------------------------
// 19 products. `body` is the complete new body: one <h2> then three <p>.
// `faq` describes what happens to the live FAQ <section>:
//   'keep'    — carried over byte-for-byte
//   'replace' — exact-string swaps, each must match exactly once
//   'drop'    — rebuild without item N (BR-CHARM-100, variant A)
// --------------------------------------------------------------------------
const PRODUCTS = [
    125 => [
        'sku'           => 'BR-MEW-100',
        'before_sha256' => '41ad584e47ad6b198f5c1f1815e565a06ac4ec11ffd13b88814dbabdcbedbc74',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Мью в кількох лініях і одному рожевому кольорі</h2>',
            '<p>Брелок Мью (Pokémon) побудований майже без промальованих рис: образ тримається на <strong>характерному контурі голови, великих вирізах очей і рожевому кольорі</strong>. Через це Мью (Mew) виглядає не як зменшена фігурка, а як лаконічний графічний символ персонажа.</p>',
            '<p>Формат 40×38×4 мм робить модель компактною, але помітно щільнішою за зовсім тонкі пласкі підвіски. Вирізи залишають силует легким візуально, а на ключах чи рюкзаку рожевий контур помітний навіть здалеку — колір тут працює не просто як оформлення, а як частина впізнаваного образу.</p>',
            '<p>Друкуємо кожен брелок у Booster Shop в Україні пошаровим 3D-друком із PLA, тому на поверхні може читатися характерна тонка фактура шарів. Металева фурнітура комплектується з наявного запасу: кільце, ланцюжок або карабін у конкретному виробі можуть відрізнятися від того, що показано на фото.</p>',
        ],
    ],

    126 => [
        'sku'           => 'BR-CHARM-100',
        'before_sha256' => '05c4a8a71decec1ceb527f3b8170d757c56b4a8fc0abb0f6f7077521fecced26',
        // Variant A — see the gate in the header.
        'faq'           => ['mode' => 'drop', 'item' => 3, 'items_before' => 3],
        'body'          => [
            '<h2>Чармандер без зайвого рельєфу</h2>',
            '<p>Брелок Чармандер (Pokémon) передає персонажа буквально кількома деталями: <strong>великими вирізами очей, лінією мордочки та помаранчевим силуетом</strong>. Чармандер (Charmander) лишається очевидним навіть без складного рельєфу, а сам дизайн виходить чистим і графічним.</p>',
            '<p>Це статична версія в легкому пласкому форматі. Увесь акцент залишається на силуеті персонажа та компактності — без окремої тактильної механіки.</p>',
            '<p>Друкуємо брелок у Booster Shop в Україні методом пошарового 3D-друку з PLA; подекуди на поверхні можуть залишатися ледь помітні сліди підтримок. Фурнітура на фото показана як один із можливих варіантів — фактичне металеве кільце, ланцюжок або карабін можуть відрізнятися.</p>',
        ],
    ],

    127 => [
        'sku'           => 'BR-SQUIR-100',
        'before_sha256' => 'eecf7e9e5d2656fc5b6689e75dd6c9cb216b53dabf55e4dc13e0f3cd86fdb8df',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>М\'які форми замість складної деталізації</h2>',
            '<p>Брелок Сквіртл (Pokémon) читається через <strong>максимально м\'які форми</strong>: округлу голову, великі вирізи очей і тонку усмішку. Блакитний Сквіртл (Squirtle) має спокійний, доброзичливий образ, хоча деталей тут зовсім небагато.</p>',
            '<p>Товщина лише 2,5 мм підтримує саме цей легкий графічний характер дизайну. Широкі відкриті очі та плавний нижній контур не перевантажують маленьку форму, тому модель добре читається навіть без додаткового рельєфу чи багатоколірних елементів.</p>',
            // corrections §4.1 — «між партіями пластику», not «між окремими екземплярами»
            '<p>Друкуємо брелок у Booster Shop в Україні з PLA. Відтінок блакитного може трохи відрізнятися між партіями пластику. Металева фурнітура ставиться з наявного запасу, тож конкретний тип кріплення може не збігатися з фотографією.</p>',
        ],
    ],

    128 => [
        'sku'           => 'BR-BULB-100',
        'before_sha256' => '7e32eb8546ce246433d124fe70e4fd256b36a06cdc742d7ef7b61f22c7c2f35e',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Бульбазавр, якого тримає сам контур</h2>',
            '<p>Брелок Бульбазавр (Pokémon) будується на <strong>загострених вухах, широкій формі голови та характерних вирізах на лобі</strong>. Разом із зеленим кольором цього вистачає, щоб Бульбазавр (Bulbasaur) упізнавався без складної деталізації.</p>',
            '<p>Форма тут важливіша за кількість декоративних елементів. Невисокий широкий контур робить брелок візуально щільним і добре помітним на сумці чи рюкзаку, а вирізи всередині силуету не дають йому перетворитися на суцільну зелену пластину.</p>',
            // corrections §4.1
            '<p>Друкуємо модель у Booster Shop в Україні пошарово з PLA. На поверхні природно помітна тонка структура друку, а відтінок зеленого може незначно змінюватися між партіями пластику. Конкретний тип металевої фурнітури залежить від наявного запасу й може відрізнятися від фото.</p>',
        ],
    ],

    129 => [
        'sku'           => 'BR-PIKA-100',
        'before_sha256' => 'acd9ed6549dadce4c050d5de8e66c5b0979ed0847f9261c7fd21b478f610b411',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Довгі вуха, жовтий колір — і Пікачу вже впізнається</h2>',
            '<p>Брелок Пікачу (Pokémon) працює так, що <strong>головну роль грає сам силует</strong>: довгі вуха, округла мордочка, вирізи очей і щік складаються у знайомий образ ще до будь-яких дрібних деталей. Жовтий колір завершує цей мінімалістичний Пікачу (Pikachu) і підсилює впізнаваність персонажа.</p>',
            '<p>При розмірі 55,5×43,4×5 мм пластикова частина важить близько 3,74 г. Великі вирізи та відкритий простір навколо вух дають Пікачу виразний контур без зайвої масивності.</p>',
            '<p>Друкуємо Пікачу у Booster Shop в Україні пошарово з PLA, тому на поверхні може бути помітна характерна структура шарів. Фурнітура комплектується окремо з поточного запасу, тому її фактичний тип може відрізнятися від фотографії.</p>',
        ],
    ],

    130 => [
        'sku'           => 'FIG-ONIX-500',
        'before_sha256' => '9ac4982f7cd905415c84d087397de285dafbcba8c11d225b8944b826a8b2c5ef',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Сегментоване тіло, не прив\'язане до однієї пози</h2>',
            '<p>Фігурка Онікс (Pokémon) — це передусім довжина. Модель збирає персонажа з ланцюга кам\'яних сегментів, з\'єднаних рухомо, тому <strong>тіло не зафіксоване в одній позі</strong>, а положення сегментів можна змінювати. Онікс (Onix) від цього виглядає радше застигнутим у русі, ніж поставленим на полицю.</p>',
            '<p>Сегменти повертаються один відносно одного, і ту саму модель можна скласти кільцем, хвилею або витягнути майже прямо — у розпрямленому вигляді довжина моделі становить 285 мм. Голова опрацьована детальніше за корпус: саме вона тримає впізнаваність, коли решта тіла складена в довільний вигин.</p>',
            '<p>Друкуємо кожну фігурку окремо, у Booster Shop в Україні, пошарово з PLA. На гранях помітна тонка структура шарів — природна риса технології.</p>',
        ],
    ],

    131 => [
        'sku'           => 'FIG-GEOD-511',
        'before_sha256' => '7c71f1465aaa9737732ae47c429652d7b38d69751a4818914184903c9bb54fa5',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            // corrections §4.7 — <h2> must not repeat the H1; key moved into p1
            '<h2>Голова, кулаки і майже нічого між ними</h2>',
            '<p>Фігурка Геодуд (Pokémon) — це переважно <strong>голова й кулаки</strong>. Гранована кам\'яна морда з насупленими бровами займає більшу частину об\'єму, а руки з\'єднані з головою довгими сегментованими ланками й виглядають майже непропорційними. Саме ця диспропорція робить Геодуда (Geodude) впізнаваним і трохи комічним.</p>',
            '<p>Руки рухаються в сегментах, тому позу можна змінювати: опустити кулаки на поверхню, розвести їх у боки або підняти над головою. Одноколірне виконання при цьому працює на користь формі — без кольорових акцентів увага лишається на гранях і на тому, як на них лягає світло.</p>',
            // corrections §4.1
            '<p>Відтінок сірого може незначно відрізнятися між партіями пластику — для пошарового 3D-друку це природна особливість. Кожен екземпляр ми друкуємо окремо, у Booster Shop в Україні, з PLA.</p>',
        ],
    ],

    132 => [
        'sku'              => 'FIG-MEW-100',
        'before_sha256'    => '5e0fb5f8b0fac2afcfc4b7480e7720fd4b74f52129aedff478932fffd5daae1b',
        'faq'              => ['mode' => 'keep'],
        'meta_description' => [
            'old' => 'Фігурка Мью в покеболі (Pokémon): рожева сфера з вухами та хвостом, 9 см. 3D-друк із PLA власного виробництва, Booster Shop.',
            'new' => 'Фігурка Мью в покеболі (Pokémon): рожева сфера з вухами та хвостом, 90×70×90 мм. 3D-друк PLA — купити в Україні у Booster Shop.',
        ],
        'body'             => [
            // corrections §4.7
            '<h2>Два образи, які не заважають один одному</h2>',
            '<p>У фігурці Мью в покеболі (Pokémon) Мью не сидить у покеболі — <strong>Мью і є покебол</strong>. Сфера отримала вуха, чорну смугу з білою кнопкою рівно там, де їй належить бути, і довгий хвіст, що виводить дугу назад. Через це силует читається одночасно і як покебол, і як Мью (Mew) — у цьому вся ідея виробу.</p>',
            '<p>Форма розрахована на розглядання з різних боків: спереду це майже чистий покебол, збоку хвіст різко змінює композицію, а зверху видно вуха, яких у звичайного покебола бути не може. Рожевий корпус із білою кнопкою й чорною смугою тримає обидва образи одночасно.</p>',
            '<p>На округлій поверхні особливо добре читаються горизонтальні лінії пошарового друку, які самі стають частиною фактури моделі. Друкуємо кожну фігурку окремо, у Booster Shop в Україні, з PLA.</p>',
        ],
    ],

    133 => [
        'sku'           => 'FIG-PKBL-600',
        'before_sha256' => '0508b00ef711908d062f74d4cfcedc9968d6ee08a9a048ec249be12f172f06d0',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Клікер-механіка всередині знайомого покебола</h2>',
            '<p>Фігурка-клікер Покебол (Pokémon) має 25 мм у поперечнику й <strong>натискну клікер-механіку</strong>. У чорне кільце-основу вставлений червоно-білий покебол, а кнопка розташована там само, де й у звичному образі покебола, тому механіка не виглядає доробленою збоку.</p>',
            '<p>Тримати його зручно двома пальцями — це тактильний fidget-виріб для робочого столу, а не декор, який стоїть недоторканим. Чорна основа при цьому утримує покебол і не дає йому котитися по поверхні.</p>',
            '<p>Пошарову фактуру на виробі такого розміру видно лише зблизька. Клікер друкуємо у Booster Shop в Україні, з PLA.</p>',
        ],
    ],

    134 => [
        'sku'           => 'FIG-LUFFY-500',
        'before_sha256' => '3d43d461f1da1967d2986dabe93a63a3ad44316ad7df3a1008c68d214233c561',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            // corrections §4.7
            '<h2>Персонаж, який не тримає одну позу</h2>',
            '<p>Фігурка Луффі (One Piece) не стоїть на полиці спокійно: <strong>корпус набраний із гнучких ребристих сегментів</strong>, тому модель гнеться й скручується замість того, щоб тримати одну задану позу. У білому виконанні впізнаваність Луффі (Luffy) тримається на капелюсі, розкинутих руках і широкій усмішці, а не на кольорових деталях.</p>',
            '<p>Обличчя пропрацьоване несподівано детально як для такого формату — примружені очі та зуби видно й без фарбування. Решта фігури навпаки спрощена, і цей контраст працює на користь: погляд чіпляється за голову, а тіло лишається пластичною основою.</p>',
            '<p>Через гнучку геометрію на переходах між сегментами подекуди лишаються ледь помітні сліди опор. Друкуємо фігурку в Україні, у Booster Shop, із PLA — кожен екземпляр окремо.</p>',
        ],
    ],

    135 => [
        'sku'           => 'FIG-LUFFY-400',
        'before_sha256' => 'f4848a54ce2942deda4ad41b8dddf04dc22c6f90c6db3c082ba95eb6d5a39a97',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Силует Луффі без жодної риси обличчя</h2>',
            '<p>Панно Луффі (One Piece) не має жодної риси обличчя — і <strong>персонаж усе одно впізнається</strong>. Воно побудоване на суцільному чорному контурі: капелюх, піднята до нього рука, розкльошені шорти й характерна постава. Луффі (Luffy) зчитується за позою ще до пошуку дрібних деталей.</p>',
            '<p>Світлий фон підсилює контраст чорного силуету — на світлій стіні, полиці чи біля вікна контуру є з чим працювати. Стоїть він на власній основі, тому не потребує ні кріплення, ні рамки, а висота силуету 181 мм робить його помітним, але не домінантним у композиції.</p>',
            '<p>Чорний колір тут працює на саму ідею панно — підсилює цілісність силуету й контраст зі світлим фоном. Друкуємо його самі, в Україні, з PLA; тонка горизонтальна текстура шарів на великих площинах усе одно читається зблизька.</p>',
        ],
    ],

    136 => [
        'sku'           => 'FIG-LUFFY-410',
        'before_sha256' => '6f27e25e5be832a36802d6c3f7e99049133058c30abddd18fd5973bfca835b95',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Портрет Луффі, намальований порожнечею</h2>',
            '<p>Картина Луффі (One Piece) — це малюнок, зроблений порожнечею. <strong>Обличчя Луффі зібране з тонких чорних ліній</strong> у прямокутній рамці: заплющені від сміху очі, зуби, солом\'яний капелюх — і все, чого немає, працює нарівні з тим, що є. Луффі (Luffy) тут переданий буквально кількома штрихами.</p>',
            // corrections §4.8
            '<p>На полиці чи комоді картина тримається сама, спираючись на нижню планку, — свердлити стіну не треба. За габаритами 250×194×27 мм це вже не сувенір, а самостійний елемент оформлення, який задає тон полиці з колекцією.</p>',
            '<p>Ажурна геометрія з тонкими лініями робить природні особливості друку помітнішими: подекуди на них лишаються ледь помітні сліди опор. Виріб друкуємо у Booster Shop в Україні, з PLA.</p>',
        ],
    ],

    137 => [
        'sku'           => 'ACC-3D-PKM-110',
        'before_sha256' => '9c975ac05b3e38af68341722c287ae2edf995a8ca38862a31a780fa598d72542',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Картка на видноті, рамка — майже ні</h2>',
            '<p>Підставка для картки в протекторі — найпростіший спосіб поставити одну картку вертикально. Вузька рамка тримає її по контуру й <strong>лишає майже всю площу відкритою</strong> — на полиці видно арт, а не тримач навколо нього.</p>',
            '<p>Це тонка рамка на пласкій основі, розрахована на картку в м\'якому протекторі. Формат для щоденного зберігання, коли жорсткий кейс зайвий, а поставити улюблену картку на видноті хочеться.</p>',
            '<p>Підставку друкуємо у Booster Shop в Україні, пошарово з PLA. На пласких гранях рамки зблизька читається тонка структура шарів — природна риса технології.</p>',
        ],
    ],

    138 => [
        'sku'           => 'ACC-3D-PKM-120',
        'before_sha256' => '7144ff48ad6c5ef0cde82f61cb3eabcc650f430dae83ee8f28d9a980ba881c0d',
        'faq'           => ['mode' => 'keep'],
        'body'          => [
            '<h2>Топлоадер між двома тонкими опорами</h2>',
            '<p>Підставка для картки в топлоадері влаштована так: топлоадер не лежить в основі, а <strong>тримається між двома тонкими опорами</strong> — картка виглядає підвішеною в повітрі. Жорсткий прозорий корпус стає частиною експозиції замість того, щоб просто захищати.</p>',
            '<p>Опори вужчі за саму картку, тому центральна частина арту не перекрита з жодного боку. Модель вища за малу підставку й помітно вужча за велику: розрахована саме на товщину топлоадера, не на м\'який протектор і не на магнітний кейс.</p>',
            '<p>На тонких опорах подекуди лишаються ледь помітні сліди підтримок — геометрія без них не друкується. Кожен екземпляр виготовляємо у Booster Shop в Україні з PLA.</p>',
        ],
    ],

    139 => [
        'sku'           => 'ACC-3D-PKM-130',
        'before_sha256' => '5c6e2a06dee36fe267697d9ebcc3c18ab8e5f0e878627be21430a60e5dde6c09',
        'faq'           => ['mode' => 'keep'],
        'meta_title'    => [
            'old' => 'Підставка для картки в магнітному кейсі — 3D-друк | Booster Shop',
            // corrections §4.3 — keeps «3D-друк», 53 chars
            'new' => 'Підставка під магнітний кейс — 3D-друк | Booster Shop',
        ],
        'body'          => [
            '<h2>Магнітний кейс як окремий настільний дисплей</h2>',
            '<p>Підставка для картки в магнітному кейсі працює з кейсом, який уже сам по собі виглядає як вітрина: їй лишається підняти його й дати простір. Висока опора <strong>виносить картку над основою</strong>, і замість тримача виходить окремий настільний дисплей.</p>',
            '<p>Чорний корпус розрізає тонка помаранчева лінія по основі. Висока конструкція лишає кейс головним візуальним акцентом, а помаранчева лінія додає контраст до чорної опори.</p>',
            '<p>На широких чорних площинах пошарова структура помітна зблизька. Друкуємо підставку у Booster Shop в Україні з PLA.</p>',
        ],
    ],

    140 => [
        'sku'           => 'ACC-3D-PKM-200',
        'before_sha256' => '70a8142ae4f789099f3e7ff2dabdcfdbe254ce80ee46fca4dcbc77e365e65cb7',
        'faq'           => ['mode' => 'replace', 'pairs' => [
            [
                '<p>Під корпус PSA — це вказано в характеристиках. Корпуси інших грейдингових компаній відрізняються шириною й товщиною, тому в цій моделі слаб іншого формату сидітиме не щільно.</p>',
                '<p>Під корпус PSA — це вказано в характеристиках. Сумісність з іншими форматами без окремої перевірки не заявляємо.</p>',
            ],
            [
                '<span>Скільки таких підставок стане в ряд на полиці?</span>',
                '<span>Скільки місця займає одна підставка по фронту?</span>',
            ],
            [
                '<p>Кожна займає 89 мм по фронту. На полиці шириною 80 см поміститься близько восьми слабів упритул, менше — якщо лишати проміжки.</p>',
                '<p>Габарити підставки — 89×38×20 мм. Для розміщення в ряд орієнтуйтеся на 89 мм по фронту для одного виробу.</p>',
            ],
        ]],
        'body'          => [
            '<h2>Низький профіль для грейдженого слаба</h2>',
            // corrections §4.6
            '<p>Грейджений слаб не потребує оформлення — йому потрібен нахил. Підставка для грейдженої картки PSA дає рівно це: <strong>низьку основу під слаб PSA</strong>, без зайвого декору.</p>',
            '<p>Основа має висоту 20 мм і лишається майже непомітною під слабом. Такий профіль розрахований на ряд: коли грейджені картки стоять поруч, підставки не мають перетягувати на себе ритм полиці.</p>',
            '<p>Виріб друкуємо в Україні, у Booster Shop, із чорного PLA — на похилій площині основи пошарова фактура читається як тонкі паралельні лінії.</p>',
        ],
    ],

    141 => [
        'sku'           => 'ACC-3D-PKM-300',
        'before_sha256' => '951af5bc0c21acfdd7ca406de3259d3d241f90480e371aee345cb5c54a779cc3',
        'faq'           => ['mode' => 'replace', 'pairs' => [
            [
                '<span>Чи не перекидається підставка з важким слабом?</span>',
                '<span>Яка основа у підставки на ніжці?</span>',
            ],
            [
                '<p>Основа має габарити 102×100 мм і сама важить більшу частину моделі — центр ваги залишається низько. На рівній поверхні конструкція стійка.</p>',
                '<p>Основа має габарити 102×100 мм, а висока ніжка розташована над широкою основою. Підставка призначена для одного слаба PSA.</p>',
            ],
        ]],
        'body'          => [
            '<h2>Слаб на окремому постаменті</h2>',
            '<p>Підставка для слаба PSA на ніжці має висоту 226 мм і перетворює тримач на подіум. Слаб PSA <strong>піднімається над столом на окремій ніжці</strong>, і замість «картка лежить під кутом» виходить «картка стоїть на постаменті» — різниця в сприйнятті більша, ніж різниця в конструкції.</p>',
            '<p>У конструкції поєднані широка основа й висока ніжка. Формат призначений для однієї картки, яку хочеться винести вперед: поруч із низькими підставками така модель створює окремий акцент.</p>',
            '<p>На високій чорній ніжці пошарова фактура помітна зблизька. Виріб виготовляємо у Booster Shop в Україні з PLA.</p>',
        ],
    ],

    142 => [
        'sku'           => 'ACC-3D-PKM-700',
        'before_sha256' => '64fd7b3d297346ac510809d13f7dce98f7669ce343fde9c759bc759b8d091eed',
        'faq'           => ['mode' => 'replace', 'pairs' => [
            [
                '<p>Точну кількість слотів уточнюємо — вона залежить від товщини конкретних топлоадерів. Конструкція шестигранна, картки розміщуються по гранях корпусу.</p>',
                '<p>{{потрібні дані: точна кількість карток у топлоадерах, яку вміщує ACC-3D-PKM-700}}</p>',
            ],
        ]],
        'body'          => [
            '<h2>Шестигранний дисплей, який повертає картки до глядача</h2>',
            '<p>Обертова підставка для топлоадерів вирішує проблему переднього ряду: у ній <strong>топлоадери стоять по гранях шестигранного корпусу</strong>, і замість переставляння карток дисплей можна повернути потрібною стороною.</p>',
            // corrections §4.4
            '<p>Одна модель замінює кілька окремих тримачів і займає при цьому місце одного. Задні грані перестають бути мертвим простором: до них є доступ без перестановки карток.</p>',
            '<p>На великих вертикальних панелях корпусу пошарова фактура помітна зблизька. Виготовляємо підставку у Booster Shop в Україні з чорного PLA.</p>',
        ],
    ],

    143 => [
        'sku'           => 'ACC-3D-PKM-710',
        'before_sha256' => '70bcb0302defcc283ece062fdeca3b74d22bab86fd19d6bcf66c883b5425564c',
        'faq'           => ['mode' => 'replace', 'pairs' => [
            [
                '<p>Точну кількість уточнюємо. Корпус шестигранний, слаби розміщуються по його гранях.</p>',
                '<p>{{потрібні дані: точна кількість слабів PSA, яку вміщує ACC-3D-PKM-710}}</p>',
            ],
            [
                '<p>Під корпус PSA — це вказано в характеристиках. Грані розраховані саме на його ширину й товщину, тому слаб іншої грейдингової компанії стане не щільно.</p>',
                '<p>Під корпус PSA — це вказано в характеристиках. Сумісність з іншими форматами без окремої перевірки не заявляємо.</p>',
            ],
        ]],
        'body'          => [
            '<h2>Кілька слабів навколо одного корпусу</h2>',
            '<p>Обертова підставка для PSA slab <strong>збирає слаби PSA навколо шестигранного корпусу</strong> — колекція перестає бути одним рядом і стає окремим настільним дисплеєм, який можна повернути потрібною стороною.</p>',
            // corrections §4.8
            '<p>Порівняно з підставкою на ніжці тут інший акцент: та виносить уперед одну картку, ця показує кілька позицій навколо корпусу. Модель нижча за неї, зате помітно більша в основі — найбільший габарит становить 201 мм.</p>',
            '<p>На обертовому вузлі можуть лишатися ледь помітні сліди опор. Модель друкуємо у Booster Shop в Україні пошарово з чорного PLA.</p>',
        ],
    ],
];

// --------------------------------------------------------------------------
// Categories 73 and 74. Description / meta_title / meta_description untouched.
// --------------------------------------------------------------------------
const CATEGORIES = [
    73 => [
        'name'         => ['old' => 'Фігурки та декор', 'new' => 'Фігурки та декор Pokémon'],
        // corrections §4.5 — Cyrillic product-intent keys restored, «брелок Пікачу» stays out
        'meta_keyword' => [
            'old' => 'брелок покемон, фігурка покемон, брелок Пікачу, 3D друк покемон, брелоки Pokemon купити Україна',
            'new' => 'брелоки покемон, фігурки покемон, брелоки Pokémon, 3D-друк Pokémon, декор покемон купити Україна',
        ],
    ],
    74 => [
        'name' => ['old' => 'Фігурки та декор', 'new' => 'Фігурки та декор One Piece'],
    ],
];

// --------------------------------------------------------------------------
// Shared helpers (house style, identical to the 2026-08-19 wave)
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

/** Rebuilds the accordion exactly the way the 2026-08-19 wave wrote it. */
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

function bs_faq_marker(): string { return bs_encode_html('<section class="bs-faq-accordion"'); }

/** Splits the stored (entity-encoded) description into [body, faq_tail]. */
function bs_split_description(string $stored, int $productId): array {
    $marker = bs_faq_marker();
    $count  = substr_count($stored, $marker);
    if ($count !== 1) {
        bs_fail('Product ' . $productId . ': expected exactly 1 FAQ section marker, found ' . $count . ' — stopping');
    }
    $pos = strpos($stored, $marker);
    return [substr($stored, 0, $pos), substr($stored, $pos)];
}

/** Reads the live FAQ items out of an encoded tail. */
function bs_faq_items(string $encodedTail, int $productId): array {
    $decoded = html_entity_decode($encodedTail, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!preg_match('/data-bs-faq-id="([^"]*)"/', $decoded, $idMatch)) {
        bs_fail('Product ' . $productId . ': FAQ section has no data-bs-faq-id — stopping');
    }
    preg_match_all('/<span>(.*?)<\/span>/su', $decoded, $questions);
    preg_match_all('/class="bs-faq-panel"[^>]*>\r\n<p>(.*?)<\/p>/su', $decoded, $answers);
    if (count($questions[1]) === 0 || count($questions[1]) !== count($answers[1])) {
        bs_fail('Product ' . $productId . ': cannot pair FAQ questions ('
            . count($questions[1]) . ') with answers (' . count($answers[1]) . ') — stopping');
    }
    $items = [];
    foreach ($questions[1] as $index => $question) $items[] = [$question, $answers[1][$index]];
    return [$idMatch[1], $items];
}

/** Applies the configured FAQ operation and returns the new encoded tail. */
function bs_faq_tail(string $liveTail, array $spec, int $productId): string {
    $mode = (string) $spec['mode'];

    if ($mode === 'keep') {
        return $liveTail;
    }

    if ($mode === 'replace') {
        $tail = $liveTail;
        foreach ($spec['pairs'] as $pair) {
            $needle  = bs_encode_html($pair[0]);
            $replace = bs_encode_html($pair[1]);
            $hits    = substr_count($tail, $needle);
            // Re-run of an applied patch: the anchor is gone and its replacement is in place.
            if ($hits === 0 && substr_count($tail, $replace) === 1) continue;
            if ($hits !== 1) {
                bs_fail('Product ' . $productId . ': FAQ anchor matched ' . $hits . ' times, expected 1 — «'
                    . mb_substr($pair[0], 0, 60, 'UTF-8') . '…» — stopping');
            }
            $tail = str_replace($needle, $replace, $tail);
        }
        return $tail;
    }

    if ($mode === 'drop') {
        [$faqId, $items] = bs_faq_items($liveTail, $productId);
        // Re-run of an applied patch: the item is already gone, leave the tail alone.
        if (count($items) === (int) $spec['items_before'] - 1) return $liveTail;
        if (count($items) !== (int) $spec['items_before']) {
            bs_fail('Product ' . $productId . ': FAQ has ' . count($items) . ' items, expected '
                . $spec['items_before'] . ' before the drop — stopping');
        }
        // Prove the generator matches the live markup byte-for-byte before rebuilding.
        if (bs_encode_html(bs_faq_html($faqId, $items)) !== $liveTail) {
            bs_fail('Product ' . $productId . ': live FAQ markup does not round-trip through the generator. '
                . 'It was hand-edited. Refusing to rebuild it — stopping');
        }
        $index = (int) $spec['item'] - 1;
        if (!isset($items[$index])) bs_fail('Product ' . $productId . ': FAQ item ' . $spec['item'] . ' does not exist — stopping');
        array_splice($items, $index, 1);
        return bs_encode_html(bs_faq_html($faqId, $items));
    }

    bs_fail('Product ' . $productId . ': unknown FAQ mode «' . $mode . '»');
}

function bs_new_description(string $liveDescription, array $product, int $productId): string {
    [, $liveTail] = bs_split_description($liveDescription, $productId);
    $body = bs_encode_html(implode("\r\n\r\n", $product['body']));
    return $body . "\r\n\r\n" . bs_faq_tail($liveTail, $product['faq'], $productId);
}

function bs_sql_string(mysqli $db, ?string $value): string {
    return $value === null ? 'NULL' : "'" . $db->real_escape_string($value) . "'";
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
    if (count(PRODUCTS) !== 19) bs_fail('Expected 19 products, found ' . count(PRODUCTS));
    foreach (PRODUCTS as $productId => $product) {
        if ($productId < 125 || $productId > 143) bs_fail('Product id ' . $productId . ' is outside the 125..143 wave');
        if (count($product['body']) !== 4) bs_fail('Card ' . $product['sku'] . ' must be <h2> + 3 paragraphs, has ' . count($product['body']));
        $body = implode(' ', $product['body']);
        if (substr_count($body, '<h2>') !== 1)     bs_fail('Card ' . $product['sku'] . ' must have exactly one <h2> in the body');
        if (substr_count($body, '<strong>') !== 1) bs_fail('Card ' . $product['sku'] . ' must have exactly one <strong> in the body');
        if (substr_count($body, '</strong>') !== 1) bs_fail('Card ' . $product['sku'] . ' has an unbalanced <strong>');
        if (strpos($body, "\u{2019}") !== false)   bs_fail('Card ' . $product['sku'] . ' uses U+2019; this wave is ASCII apostrophes only');
        if (!preg_match('/^[0-9a-f]{64}$/', (string) $product['before_sha256'])) bs_fail('Card ' . $product['sku'] . ' has no valid before_sha256');
        foreach (['meta_title' => 255, 'meta_description' => 255] as $field => $limit) {
            if (isset($product[$field]) && mb_strlen($product[$field]['new'], 'UTF-8') > $limit) {
                bs_fail($field . ' of ' . $product['sku'] . ' exceeds ' . $limit . ' chars');
            }
        }
    }
    bs_log('content_guards', 'ok — 19 cards, one <h2> and one <strong> each, ASCII apostrophes');

    $backupDir = bs_path($cwd, '_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His'));
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) bs_fail('Cannot create backup directory');
    bs_log('backup_dir', $backupDir);

    $db = bs_connect();
    try {
        $prefix = (string) DB_PREFIX;
        $t = [
            'product'               => bs_table($prefix, 'product'),
            'product_description'   => bs_table($prefix, 'product_description'),
            'product_code'          => bs_table($prefix, 'product_code'),
            'product_attribute'     => bs_table($prefix, 'product_attribute'),
            'category_description'  => bs_table($prefix, 'category_description'),
            'attribute_description' => bs_table($prefix, 'attribute_description'),
        ];
        foreach ($t as $table) if (!bs_table_exists($db, $table)) bs_fail('Required table not found: ' . $table);
        bs_require_columns(bs_columns($db, $t['product_description']), ['product_id','language_id','name','description','meta_title','meta_description','meta_keyword'], $t['product_description']);
        bs_require_columns(bs_columns($db, $t['category_description']), ['category_id','language_id','name','meta_keyword'], $t['category_description']);
        bs_require_columns(bs_columns($db, $t['attribute_description']), ['attribute_id','language_id','name'], $t['attribute_description']);

        // ---- BR-CHARM-100 variant gate (handoff §2.3) ----------------------
        $charm = bs_select($db, 'SELECT pc.product_id AS product_id, p.status AS status FROM `' . $t['product_code'] . '` pc'
            . ' JOIN `' . $t['product'] . '` p ON p.product_id = pc.product_id'
            . ' WHERE pc.code = \'SKU\' AND pc.value = ?', 's', [CHARM_GATE_SKU]);
        if ($charm !== []) {
            bs_fail(CHARM_GATE_SKU . ' now exists as product ' . $charm[0]['product_id'] . ' (status ' . $charm[0]['status']
                . '). This patch carries variant A, which says the clicker does not exist. Stop and have the text re-cut as variant B.');
        }
        bs_log('charm_gate', CHARM_GATE_SKU . ' absent -> VARIANT A, third FAQ item of BR-CHARM-100 is removed');

        // ---- read every current value -------------------------------------
        $ids  = implode(',', array_map('intval', array_keys(PRODUCTS)));
        $live = bs_select($db,
            'SELECT pd.product_id AS product_id, pd.name AS name, pd.description AS description,'
            . ' pd.meta_title AS meta_title, pd.meta_description AS meta_description, pd.meta_keyword AS meta_keyword,'
            . ' p.model AS model, p.status AS status'
            . ' FROM `' . $t['product_description'] . '` pd'
            . ' JOIN `' . $t['product'] . '` p ON p.product_id = pd.product_id'
            . ' WHERE pd.language_id = ? AND pd.product_id IN (' . $ids . ')', 'i', [LANGUAGE_ID]);
        if (count($live) !== count(PRODUCTS)) bs_fail('Expected ' . count(PRODUCTS) . ' description rows, got ' . count($live));

        $current = [];
        foreach ($live as $row) $current[(int) $row['product_id']] = $row;

        $codes = bs_select($db, 'SELECT product_id, value FROM `' . $t['product_code'] . '` WHERE code = \'SKU\' AND product_id IN (' . $ids . ')', '', []);
        $codeBySku = [];
        foreach ($codes as $row) $codeBySku[(int) $row['product_id']] = (string) $row['value'];

        // ---- identity + state gate ----------------------------------------
        $statusChanged = [];
        foreach (PRODUCTS as $productId => $product) {
            if (!isset($current[$productId])) bs_fail('Product ' . $productId . ' has no language ' . LANGUAGE_ID . ' description row');
            $row = $current[$productId];
            if ((string) $row['model'] !== $product['sku']) {
                bs_fail('Product ' . $productId . ' is model «' . $row['model'] . '», expected «' . $product['sku'] . '» — stopping');
            }
            if (($codeBySku[$productId] ?? '') !== $product['sku']) {
                bs_fail('Product ' . $productId . ' product_code SKU is «' . ($codeBySku[$productId] ?? '') . '», expected «' . $product['sku'] . '» — stopping');
            }
            $statusChanged[$productId] = (int) $row['status'];
        }
        bs_log('identity_verified', '19 product_id -> model -> product_code.SKU triples match');

        // Placeholder safety: never write a {{потрібні дані}} marker onto a visible product.
        foreach (PRODUCTS as $productId => $product) {
            $carriesPlaceholder = false;
            if (($product['faq']['mode'] ?? '') === 'replace') {
                foreach ($product['faq']['pairs'] as $pair) {
                    if (strpos($pair[1], PLACEHOLDER_MARKER) !== false) $carriesPlaceholder = true;
                }
            }
            if ($carriesPlaceholder && (int) $current[$productId]['status'] !== 0) {
                bs_fail('Product ' . $productId . ' (' . $product['sku'] . ') is VISIBLE and this patch would write a '
                    . '«потрібні дані» placeholder into its FAQ. Get the capacity number first — stopping');
            }
        }

        // ---- compute the new values, then decide idempotency ---------------
        $newDescription = [];
        $pending        = [];
        $alreadyDone    = [];
        foreach (PRODUCTS as $productId => $product) {
            $liveValue = (string) $current[$productId]['description'];
            $target    = bs_new_description($liveValue, $product, $productId);
            $newDescription[$productId] = $target;

            if ($liveValue === $target) { $alreadyDone[] = $product['sku']; continue; }
            if (hash('sha256', $liveValue) !== $product['before_sha256']) {
                bs_fail('Product ' . $productId . ' (' . $product['sku'] . '): description does not match the 2026-08-21 '
                    . 'backup (sha256 ' . substr(hash('sha256', $liveValue), 0, 16) . '… vs expected '
                    . substr((string) $product['before_sha256'], 0, 16) . '…). It was edited after the export. '
                    . 'Re-export the backup and have the patch refreshed — stopping');
            }
            $pending[] = $productId;
        }

        // categories
        $categoryRows = bs_select($db,
            'SELECT category_id, name, meta_keyword FROM `' . $t['category_description'] . '`'
            . ' WHERE language_id = ? AND category_id IN (73, 74)', 'i', [LANGUAGE_ID]);
        if (count($categoryRows) !== 2) bs_fail('Expected category_description rows for 73 and 74, got ' . count($categoryRows));
        $categoryCurrent = [];
        foreach ($categoryRows as $row) $categoryCurrent[(int) $row['category_id']] = $row;

        $categoryPending = [];
        foreach (CATEGORIES as $categoryId => $spec) {
            $row  = $categoryCurrent[$categoryId];
            $todo = [];
            foreach ($spec as $field => $values) {
                $now = (string) $row[$field];
                if ($now === $values['new']) continue;
                if ($now !== $values['old']) {
                    bs_fail('Category ' . $categoryId . '.' . $field . ' is «' . $now . '», expected «' . $values['old'] . '» — stopping');
                }
                $todo[$field] = $values['new'];
            }
            if ($todo !== []) $categoryPending[$categoryId] = $todo;
        }

        // attribute 44
        $attrRows = bs_select($db, 'SELECT name FROM `' . $t['attribute_description'] . '` WHERE attribute_id = ? AND language_id = ?', 'ii', [ATTRIBUTE_ID_MYSTERY, LANGUAGE_ID]);
        if (count($attrRows) !== 1) bs_fail('attribute_description for attribute ' . ATTRIBUTE_ID_MYSTERY . ' language ' . LANGUAGE_ID . ': expected 1 row, got ' . count($attrRows));
        $attrNow     = (string) $attrRows[0]['name'];
        $attrPending = false;
        if ($attrNow !== ATTRIBUTE_NEW_NAME) {
            if ($attrNow !== ATTRIBUTE_OLD_NAME) bs_fail('Attribute ' . ATTRIBUTE_ID_MYSTERY . ' is named «' . $attrNow . '», expected «' . ATTRIBUTE_OLD_NAME . '» — stopping');
            $attrPending = true;
        }

        $attrValueRowsBefore = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product_attribute'] . '` WHERE attribute_id = ?', 'i', [ATTRIBUTE_ID_MYSTERY]);
        $attrValueCount      = (int) $attrValueRowsBefore[0]['c'];
        if ($attrValueCount !== ATTRIBUTE_ROW_COUNT) {
            bs_fail('ocp5_product_attribute has ' . $attrValueCount . ' rows with attribute_id ' . ATTRIBUTE_ID_MYSTERY
                . ', expected ' . ATTRIBUTE_ROW_COUNT . ' — stopping');
        }

        // meta fields
        $metaPending = [];
        foreach (PRODUCTS as $productId => $product) {
            foreach (['meta_title', 'meta_description'] as $field) {
                if (!isset($product[$field])) continue;
                $now = (string) $current[$productId][$field];
                if ($now === $product[$field]['new']) continue;
                if ($now !== $product[$field]['old']) {
                    bs_fail('Product ' . $productId . '.' . $field . ' is «' . $now . '», expected «' . $product[$field]['old'] . '» — stopping');
                }
                $metaPending[$productId][$field] = $product[$field]['new'];
            }
        }

        if ($pending === [] && $categoryPending === [] && $metaPending === [] && !$attrPending) {
            bs_log('already_applied', 'yes');
            bs_log('done', 'ok');
            bs_self_delete();
            return;
        }
        if ($alreadyDone !== []) bs_log('partially_applied', implode(', ', $alreadyDone));
        bs_log('descriptions_to_write', (string) count($pending));
        bs_log('meta_fields_to_write', (string) count($metaPending));
        bs_log('categories_to_write', (string) count($categoryPending));
        bs_log('attribute_to_write', $attrPending ? 'yes' : 'no');

        // ---- backups, written BEFORE the first UPDATE ----------------------
        $before = ['note' => 'Values read immediately before WP1 wrote anything. Rollback with db/rollback.sql.',
                   'products' => [], 'categories' => [], 'attribute' => ['attribute_id' => ATTRIBUTE_ID_MYSTERY, 'name' => $attrNow,
                   'product_attribute_row_count' => $attrValueCount]];
        foreach (PRODUCTS as $productId => $product) {
            $before['products'][$product['sku']] = [
                'product_id'       => $productId,
                'status'           => (int) $current[$productId]['status'],
                'name'             => (string) $current[$productId]['name'],
                'description'      => (string) $current[$productId]['description'],
                'meta_title'       => (string) $current[$productId]['meta_title'],
                'meta_description' => (string) $current[$productId]['meta_description'],
                'meta_keyword'     => (string) $current[$productId]['meta_keyword'],
                'sha256'           => hash('sha256', (string) $current[$productId]['description']),
            ];
        }
        foreach ($categoryCurrent as $categoryId => $row) {
            $before['categories'][$categoryId] = ['name' => (string) $row['name'], 'meta_keyword' => (string) $row['meta_keyword']];
        }
        bs_json_backup($backupDir, 'before', $before);

        $sql  = "-- Rollback for " . PATCH_NAME . "\n";
        $sql .= "-- Generated " . date('c') . " from the live values, before any write.\n";
        $sql .= "-- Run against the OpenCart database to restore the pre-patch state.\n\n";
        $sql .= "START TRANSACTION;\n\n";
        foreach (PRODUCTS as $productId => $product) {
            $row = $current[$productId];
            $sql .= '-- ' . $product['sku'] . "\n";
            $sql .= 'UPDATE `' . $t['product_description'] . '` SET `description` = ' . bs_sql_string($db, (string) $row['description'])
                 . ', `meta_title` = ' . bs_sql_string($db, $row['meta_title'] === null ? null : (string) $row['meta_title'])
                 . ', `meta_description` = ' . bs_sql_string($db, $row['meta_description'] === null ? null : (string) $row['meta_description'])
                 . ' WHERE `product_id` = ' . (int) $productId . ' AND `language_id` = ' . LANGUAGE_ID . ";\n\n";
        }
        foreach ($categoryCurrent as $categoryId => $row) {
            $sql .= 'UPDATE `' . $t['category_description'] . '` SET `name` = ' . bs_sql_string($db, (string) $row['name'])
                 . ', `meta_keyword` = ' . bs_sql_string($db, $row['meta_keyword'] === null ? null : (string) $row['meta_keyword'])
                 . ' WHERE `category_id` = ' . (int) $categoryId . ' AND `language_id` = ' . LANGUAGE_ID . ";\n";
        }
        $sql .= "\n" . 'UPDATE `' . $t['attribute_description'] . '` SET `name` = ' . bs_sql_string($db, $attrNow)
             . ' WHERE `attribute_id` = ' . ATTRIBUTE_ID_MYSTERY . ' AND `language_id` = ' . LANGUAGE_ID . ";\n\n";
        $sql .= "COMMIT;\n";
        $rollbackPath = bs_path($backupDir, 'db/rollback.sql');
        if (file_put_contents($rollbackPath, $sql, LOCK_EX) === false) bs_fail('Cannot write rollback.sql');
        bs_log('rollback_sql', $rollbackPath);

        // ---- write ---------------------------------------------------------
        $db->begin_transaction();
        try {
            foreach ($pending as $productId) {
                bs_exec($db, 'UPDATE `' . $t['product_description'] . '` SET `description` = ? WHERE `product_id` = ? AND `language_id` = ?',
                    'sii', [$newDescription[$productId], $productId, LANGUAGE_ID]);
                bs_log('description_written', str_pad(PRODUCTS[$productId]['sku'], 15) . ' id=' . $productId
                    . '  ' . strlen((string) $current[$productId]['description']) . ' -> ' . strlen($newDescription[$productId]) . ' bytes');
            }
            foreach ($metaPending as $productId => $fields) {
                foreach ($fields as $field => $value) {
                    bs_exec($db, 'UPDATE `' . $t['product_description'] . '` SET `' . $field . '` = ? WHERE `product_id` = ? AND `language_id` = ?',
                        'sii', [$value, $productId, LANGUAGE_ID]);
                    bs_log('meta_written', PRODUCTS[$productId]['sku'] . '.' . $field);
                }
            }
            foreach ($categoryPending as $categoryId => $fields) {
                foreach ($fields as $field => $value) {
                    bs_exec($db, 'UPDATE `' . $t['category_description'] . '` SET `' . $field . '` = ? WHERE `category_id` = ? AND `language_id` = ?',
                        'sii', [$value, $categoryId, LANGUAGE_ID]);
                    bs_log('category_written', $categoryId . '.' . $field);
                }
            }
            if ($attrPending) {
                bs_exec($db, 'UPDATE `' . $t['attribute_description'] . '` SET `name` = ? WHERE `attribute_id` = ? AND `language_id` = ?',
                    'sii', [ATTRIBUTE_NEW_NAME, ATTRIBUTE_ID_MYSTERY, LANGUAGE_ID]);
                bs_log('attribute_written', ATTRIBUTE_ID_MYSTERY . ' -> «' . ATTRIBUTE_NEW_NAME . '»');
            }

            // ---- verify inside the transaction -----------------------------
            $after = bs_select($db,
                'SELECT pd.product_id AS product_id, pd.description AS description, pd.meta_title AS meta_title,'
                . ' pd.meta_description AS meta_description, p.status AS status, p.date_modified AS date_modified'
                . ' FROM `' . $t['product_description'] . '` pd JOIN `' . $t['product'] . '` p ON p.product_id = pd.product_id'
                . ' WHERE pd.language_id = ? AND pd.product_id IN (' . $ids . ')', 'i', [LANGUAGE_ID]);
            if (count($after) !== count(PRODUCTS)) bs_fail('Row count changed during the write — rolling back');

            $marker = bs_faq_marker();
            foreach ($after as $row) {
                $productId = (int) $row['product_id'];
                $product   = PRODUCTS[$productId];
                $value     = (string) $row['description'];

                if ($value !== $newDescription[$productId]) bs_fail('Product ' . $productId . ' description readback mismatch — rolling back');
                if ((int) $row['status'] !== $statusChanged[$productId]) bs_fail('Product ' . $productId . ' status changed — rolling back');

                [$bodyPart, $tailPart] = bs_split_description($value, $productId);
                if (substr_count($bodyPart, bs_encode_html('<h2>')) !== 1)     bs_fail('Product ' . $productId . ': body has no single <h2> — rolling back');
                if (substr_count($bodyPart, bs_encode_html('<strong>')) !== 1) bs_fail('Product ' . $productId . ': body has no single <strong> — rolling back');
                if (substr_count($bodyPart, bs_encode_html('<p>')) !== 3)      bs_fail('Product ' . $productId . ': body is not 3 paragraphs — rolling back');
                if (substr_count($tailPart, $marker) !== 1)                    bs_fail('Product ' . $productId . ': FAQ section damaged — rolling back');
                if (strpos($tailPart, bs_encode_html('</section>')) === false)  bs_fail('Product ' . $productId . ': FAQ section is not closed — rolling back');

                $expectedItems = $product['faq']['mode'] === 'drop'
                    ? (int) $product['faq']['items_before'] - 1
                    : substr_count((string) $current[$productId]['description'], bs_encode_html('<div class="bs-faq-item">'));
                $haveItems = substr_count($tailPart, bs_encode_html('<div class="bs-faq-item">'));
                if ($haveItems !== $expectedItems) {
                    bs_fail('Product ' . $productId . ': FAQ has ' . $haveItems . ' items, expected ' . $expectedItems . ' — rolling back');
                }

                foreach (['meta_title', 'meta_description'] as $field) {
                    $want = isset($product[$field]) ? $product[$field]['new'] : (string) $current[$productId][$field];
                    if ((string) $row[$field] !== $want) bs_fail('Product ' . $productId . '.' . $field . ' readback mismatch — rolling back');
                }
            }

            $catAfter = bs_select($db, 'SELECT category_id, name, meta_keyword FROM `' . $t['category_description'] . '` WHERE language_id = ? AND category_id IN (73, 74)', 'i', [LANGUAGE_ID]);
            $seen = [];
            foreach ($catAfter as $row) {
                $categoryId = (int) $row['category_id'];
                $seen[] = (string) $row['name'];
                if ((string) $row['name'] !== CATEGORIES[$categoryId]['name']['new']) bs_fail('Category ' . $categoryId . ' name readback mismatch — rolling back');
                if (isset(CATEGORIES[$categoryId]['meta_keyword']) && (string) $row['meta_keyword'] !== CATEGORIES[$categoryId]['meta_keyword']['new']) {
                    bs_fail('Category ' . $categoryId . ' meta_keyword readback mismatch — rolling back');
                }
            }
            if (count(array_unique($seen)) !== 2) bs_fail('Categories 73 and 74 do not have two distinct names — rolling back');

            $attrAfter = bs_select($db, 'SELECT name FROM `' . $t['attribute_description'] . '` WHERE attribute_id = ? AND language_id = ?', 'ii', [ATTRIBUTE_ID_MYSTERY, LANGUAGE_ID]);
            if ((string) $attrAfter[0]['name'] !== ATTRIBUTE_NEW_NAME) bs_fail('Attribute rename readback mismatch — rolling back');
            $attrValueRowsAfter = bs_select($db, 'SELECT COUNT(*) AS c FROM `' . $t['product_attribute'] . '` WHERE attribute_id = ?', 'i', [ATTRIBUTE_ID_MYSTERY]);
            if ((int) $attrValueRowsAfter[0]['c'] !== $attrValueCount) bs_fail('product_attribute row count for attribute ' . ATTRIBUTE_ID_MYSTERY . ' changed — rolling back');

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        // ---- post-commit summary -------------------------------------------
        $placeholders = [];
        foreach (PRODUCTS as $productId => $product) {
            if (strpos($newDescription[$productId], PLACEHOLDER_MARKER) !== false) $placeholders[] = $product['sku'] . ' (id ' . $productId . ')';
        }
        bs_log('descriptions_written', (string) count($pending));
        bs_log('attribute_value_rows', ATTRIBUTE_ID_MYSTERY . ' still has ' . $attrValueCount . ' product rows, values untouched');
        bs_log('product_table', 'never written — status, price, quantity, sort_order, date_modified all untouched');
        if ($placeholders !== []) {
            bs_log('PLACEHOLDER', 'these cards now carry «потрібні дані» in the FAQ and MUST NOT be enabled until the number is known: ' . implode(', ', $placeholders));
        }
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
