<?php
declare(strict_types=1);

/**
 * UI-FIX — CMS content that lives in the database, not in templates.
 *
 * Three changes, all owner-approved 2026-09-03 (AGENTS.md convention C6):
 *
 * 1. Task 3a — guarantee page (`information_id` 5, /information/original-garanty).
 *    The plain text link "Переглянути відгуки на OLX →" is replaced by the same
 *    two-icon review component the product pages already use (Telegram + OLX),
 *    reusing the existing `.bs-review-card*` markup and CSS rather than a new
 *    component. Telegram target is the owner-confirmed
 *    https://t.me/boostershop_tcg/23; the OLX target is the link already there.
 *    This page's body is admin-editable rich text stored in
 *    `information_description`, which is why this is a DB change and not a
 *    template edit.
 *
 * 2. Task 8 — the legacy `.category-tiles` row is removed from the homepage.
 *    It is not template markup: it is the HTML module "Категорії"
 *    (`module_id` 5, code `opencart.html.5`) placed in `content_top` of the
 *    Home layout. This patch removes only its PLACEMENT
 *    (`layout_module` row), so the module itself survives untouched in the
 *    admin and rollback is a single INSERT.
 *    Measured on production 2026-09-03: that block already computes to
 *    `display: none` at 390px and at 1280px, so removing it changes nothing
 *    visually. What it does remove is a second, duplicate pair of
 *    /catalog/Pokemon and /catalog/One-Piece links served to crawlers on every
 *    homepage load, plus ~700 bytes of dead markup. The Component B spec
 *    explicitly asks for deletion rather than `display:none`.
 *
 * 3. Homepage FAQ — the tile description copy removed by Task 8 does not
 *    disappear. Owner decision 2026-09-03: it is reformatted as a four-question
 *    FAQ accordion appended to the "Головна SEO" HTML module (`module_id` 9),
 *    which already renders at the bottom of the homepage. One question per
 *    category (Pokémon, One Piece, Інші TCG, Аксесуари), each answer linking to
 *    its category with a descriptive anchor.
 *    Native <details>/<summary>, no JavaScript, so every answer is in the served
 *    HTML and indexable — unlike CSS-hidden text, which carries no SEO weight.
 *    No FAQPage JSON-LD is added: schema is a risky zone and out of this
 *    batch's scope, and Google has limited FAQ rich results to government and
 *    health sites since August 2023, so there is nothing to lose by omitting it.
 *    The draft copy below is written to be adapted by Claude at patch review.
 *
 * DEPENDENCY: run patches/UI-FIX_mobile-desktop-polish_20260903.php FIRST.
 * It ships the `.bs-review-card` colour fix for content pages, the FAQ
 * accordion CSS, and the OLX icon asset this patch's markup points at. Running
 * this one alone leaves the guarantee-page cards blue-and-underlined, the FAQ
 * unstyled and the OLX icon a broken image.
 *
 * ROLLBACK
 * --------
 * `restore.sql` is written into the backup directory BEFORE any write and
 * contains the complete previous value of every touched row. Shape:
 *
 *   UPDATE `ocp5_information_description` SET `description` = '<previous>'
 *    WHERE `information_id` = 5 AND `language_id` = <id>;
 *   UPDATE `ocp5_module` SET `setting` = '<previous>' WHERE `module_id` = 9;
 *   INSERT INTO `ocp5_layout_module`
 *     (`layout_module_id`,`layout_id`,`code`,`position`,`sort_order`)
 *     VALUES (<id>,1,'opencart.html.5','content_top',<sort>);
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_cms-content_20260903.php --dry-run
 *   php UI-FIX_cms-content_20260903.php
 */

const PATCH_ID = 'UI-FIX_cms-content_20260903';
const INFORMATION_ID = 5;
const SEO_MODULE_ID = 9;
const LEGACY_MODULE_CODE = 'opencart.html.5';
const HOME_LAYOUT_ID = 1;
const FAQ_MARKER = 'bs-home-faq';

function out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function fail(string $message) {
    throw new RuntimeException($message);
}

function need(bool $ok, string $message): void {
    if (!$ok) {
        fail($message);
    }
}

function php_lint(): void {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    need($code === 0, 'php_lint_failed:' . implode(' | ', $output));
    out('php_lint', 'ok');
}

/**
 * @return array{0: mysqli, 1: string}
 */
function connect(): array {
    need(PHP_SAPI === 'cli', 'cli_only');
    $cwd = getcwd();
    need($cwd !== false, 'cwd_unavailable');
    need(is_file($cwd . DIRECTORY_SEPARATOR . 'config.php'), 'run_from_public_html_required');
    require $cwd . DIRECTORY_SEPARATOR . 'config.php';
    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PORT', 'DB_PREFIX'] as $constant) {
        need(defined($constant), 'config_constant_missing:' . $constant);
    }
    need((string)DB_PREFIX === 'ocp5_', 'db_prefix_mismatch_expected_ocp5_');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli((string)DB_HOSTNAME, (string)DB_USERNAME, (string)DB_PASSWORD, (string)DB_DATABASE, (int)DB_PORT);
    $db->set_charset('utf8mb4');

    return [$db, (string)DB_PREFIX];
}

/**
 * @param array<int, string|int> $params
 * @return array<int, array<string, mixed>>
 */
function rows(mysqli $db, string $sql, array $params = []): array {
    $statement = $db->prepare($sql);
    need($statement instanceof mysqli_stmt, 'prepare_failed:' . $db->error);
    if ($params !== []) {
        $types = str_repeat('s', count($params));
        $bind = [$types];
        foreach ($params as $index => $value) {
            $params[$index] = (string)$value;
            $bind[] = &$params[$index];
        }
        need((bool)call_user_func_array([$statement, 'bind_param'], $bind), 'bind_failed:' . $statement->error);
    }
    need($statement->execute(), 'execute_failed:' . $statement->error);
    // This host's mysqli is built WITHOUT mysqlnd, so the mysqlnd-only
    // prepared-statement result helpers are unavailable and fatal here. See
    // diagnostics/LEGAL-002_offer_mono_pumb_archive_v3_report_20260724.md and
    // ..._v4_report_20260724.md: v2 of that patch died on exactly that call.
    // Read through result_metadata() + bind_result() instead — the same proven
    // pattern as bs_stmt_rows() in LEGAL-002 ..._v4_20260724.php, which ran to
    // completion on this host. Return shape is unchanged: a list of associative
    // arrays keyed by column name.
    $metadata = $statement->result_metadata();
    need($metadata instanceof mysqli_result, 'result_failed');
    $row = [];
    $refs = [];
    foreach ($metadata->fetch_fields() as $field) {
        $row[$field->name] = null;
        $refs[] = &$row[$field->name];
    }
    need((bool)call_user_func_array([$statement, 'bind_result'], $refs), 'result_bind_failed:' . $statement->error);
    $out = [];
    while ($statement->fetch()) {
        // bind_result binds by reference — without a per-iteration copy every
        // element would point at the same final row.
        $copy = [];
        foreach ($row as $key => $value) {
            $copy[$key] = $value;
        }
        $out[] = $copy;
    }
    $metadata->free();
    $statement->close();

    return $out;
}

/**
 * @param array<int, string|int> $params
 */
function execute(mysqli $db, string $sql, array $params = []): int {
    $statement = $db->prepare($sql);
    need($statement instanceof mysqli_stmt, 'prepare_failed:' . $db->error);
    if ($params !== []) {
        $types = str_repeat('s', count($params));
        $bind = [$types];
        foreach ($params as $index => $value) {
            $params[$index] = (string)$value;
            $bind[] = &$params[$index];
        }
        need((bool)call_user_func_array([$statement, 'bind_param'], $bind), 'bind_failed:' . $statement->error);
    }
    need($statement->execute(), 'execute_failed:' . $statement->error);
    $affected = $statement->affected_rows;
    $statement->close();

    return $affected;
}

function quote(mysqli $db, string $value): string {
    return "'" . $db->real_escape_string($value) . "'";
}

/** Stored CMS HTML in this install is entity-encoded; match that exactly. */
function encode_cms(string $html): string {
    return htmlspecialchars($html, ENT_COMPAT | ENT_HTML5, 'UTF-8');
}

function review_cards_html(): string {
    return '<div class="bs-review-cards">' . "\n"
        . '<a href="https://t.me/boostershop_tcg/23" target="_blank" rel="noopener noreferrer" class="bs-review-card bs-review-card--tg">' . "\n"
        . '<div class="bs-review-card__icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
        . '<circle cx="12" cy="12" r="12" fill="#29B6F6"/>'
        . '<path d="M5 11.8l2.8 1.05 1.08 3.47c.07.22.34.3.52.16l1.56-1.27a.37.37 0 0 1 .45-.01l2.82 2.05c.2.15.49.04.55-.2l2.18-9.57c.07-.28-.19-.52-.46-.42L5 10.97a.38.38 0 0 0 0 .73z" fill="#fff"/>'
        . '</svg></div>' . "\n"
        . '<div class="bs-review-card__body"><div class="bs-review-card__title">Відгуки в Telegram</div>'
        . '<div class="bs-review-card__sub">Реальні відгуки покупців у каналі</div></div>' . "\n"
        . '<div class="bs-review-card__arrow">›</div>' . "\n"
        . '</a>' . "\n"
        . '<a href="https://www.olx.ua/uk/list/user/ubnF9/?tab=ratings" target="_blank" rel="noopener noreferrer" class="bs-review-card bs-review-card--olx">' . "\n"
        . '<div class="bs-review-card__icon"><img src="/image/catalog/reviews/olx-review-icon-120.png?v=uifix-20260903" alt="OLX" width="40" height="40" loading="lazy" decoding="async"></div>' . "\n"
        . '<div class="bs-review-card__body"><div class="bs-review-card__title">Відгуки на OLX</div>'
        . '<div class="bs-review-card__sub">Оцінки та коментарі покупців</div></div>' . "\n"
        . '<div class="bs-review-card__arrow">›</div>' . "\n"
        . '</a>' . "\n"
        . '</div>';
}

function faq_html(): string {
    $items = [
        [
            'Які бустери Pokémon TCG є в наявності?',
            '<p>Оригінальні бустери, бустер-бокси та набори Pokémon TCG — японські, корейські та англійські видання. '
            . 'Усе запечатане (sealed), без розпакування, зважування чи відбору карт. '
            . 'Повний асортимент — у категорії <a href="/catalog/Pokemon">Pokémon TCG</a>.</p>',
        ],
        [
            'Що є з One Piece Card Game?',
            '<p>Оригінальні бустери та бустер-бокси One Piece Card Game від Bandai. Бустери продаємо прямо з боксів, без сортування — '
            . 'заводські шанси на рідкісні карти зберігаються повністю. '
            . 'Категорія: <a href="/catalog/One-Piece">One Piece Card Game</a>.</p>',
        ],
        [
            'Які ще колекційні карткові ігри у вас є?',
            '<p>Окрім Pokémon і One Piece — Yu-Gi-Oh! та Magic: The Gathering: бустери, бустер-бокси та набори. '
            . 'Дивіться розділ <a href="/catalog/more-tcg">Інші TCG</a>.</p>',
        ],
        [
            'Чи продаєте аксесуари для зберігання карток?',
            '<p>Так: протектори (sleeves), деку-бокси, плеймати, біндери та листи-кишені на 9 карток. '
            . 'Весь асортимент — у категорії <a href="/catalog/acsesuary">Аксесуари</a>.</p>',
        ],
    ];

    $html = '<div class="' . FAQ_MARKER . '">';
    foreach ($items as [$question, $answer]) {
        $html .= "\n" . '<details class="bs-home-faq__item">'
            . '<summary>' . $question . '</summary>'
            . '<div class="bs-home-faq__answer">' . $answer . '</div>'
            . '</details>';
    }
    $html .= "\n" . '</div>';

    return $html;
}

$arguments = array_slice($argv ?? [], 1);
need($arguments === [] || $arguments === ['--dry-run'], 'usage:php ' . basename(__FILE__) . ' [--dry-run]');
$dryRun = $arguments === ['--dry-run'];

php_lint();

$transaction = false;
$db = null;

try {
    [$db, $prefix] = connect();

    $informationTable = $prefix . 'information_description';
    $moduleTable = $prefix . 'module';
    $layoutModuleTable = $prefix . 'layout_module';
    foreach ([$informationTable, $moduleTable, $layoutModuleTable] as $table) {
        rows($db, 'SELECT 1 FROM `' . $table . '` LIMIT 0');
    }

    $oldLink = '&lt;p&gt;&lt;a href=&quot;https://www.olx.ua/uk/list/user/ubnF9/?tab=ratings&quot; '
        . 'rel=&quot;noopener&quot; target=&quot;_blank&quot;&gt;Переглянути відгуки на OLX →&lt;/a&gt;&lt;/p&gt;';
    $newCards = encode_cms(review_cards_html());

    /* ---- 1. guarantee page --------------------------------------------- */

    $infoRows = rows(
        $db,
        'SELECT `information_id`, `language_id`, `title`, `description`
           FROM `' . $informationTable . '` WHERE `information_id` = ?',
        [INFORMATION_ID]
    );
    need($infoRows !== [], 'information_row_missing');

    $infoPlan = [];
    $infoAlready = 0;
    foreach ($infoRows as $row) {
        $description = (string)$row['description'];
        if (str_contains($description, 'bs-review-cards')) {
            $infoAlready++;
            continue;
        }
        // Every row must be in exactly one of two states: already carrying the
        // component, or still carrying the old link. Anything else means the
        // content drifted (a manual admin edit, a partial earlier run) and a
        // silent `continue` here could let the run report already_applied=yes
        // while this row was never touched. Refuse instead of guessing.
        $count = substr_count($description, $oldLink);
        if ($count === 0) {
            fail('information_row_unexpected_content:language_id=' . (int)$row['language_id']
                . ' - neither the bs-review-cards marker nor the OLX link anchor was found; '
                . 'content has drifted from what this patch expects, refusing to guess');
        }
        need($count === 1, 'olx_link_anchor_count:' . $count . ':language_id=' . (int)$row['language_id']);
        $infoPlan[] = [
            'language_id' => (int)$row['language_id'],
            'before' => $description,
            'after' => str_replace($oldLink, $newCards, $description),
        ];
    }
    out('plan_information_rows', (string)count($infoPlan));

    /* ---- 2. legacy homepage module placement ---------------------------- */

    $layoutRows = rows(
        $db,
        'SELECT `layout_module_id`, `layout_id`, `code`, `position`, `sort_order`
           FROM `' . $layoutModuleTable . '` WHERE `layout_id` = ? AND `code` = ?',
        [HOME_LAYOUT_ID, LEGACY_MODULE_CODE]
    );
    need(count($layoutRows) <= 1, 'unexpected_layout_module_rows:' . count($layoutRows));
    foreach ($layoutRows as $row) {
        out('plan_layout_module', sprintf(
            'remove id=%d layout=%d code=%s position=%s sort=%d (module itself is kept)',
            (int)$row['layout_module_id'],
            (int)$row['layout_id'],
            (string)$row['code'],
            (string)$row['position'],
            (int)$row['sort_order']
        ));
    }

    /* ---- 3. homepage FAQ ------------------------------------------------ */

    $moduleRows = rows($db, 'SELECT `module_id`, `name`, `code`, `setting` FROM `' . $moduleTable . '` WHERE `module_id` = ?', [SEO_MODULE_ID]);
    need(count($moduleRows) === 1, 'seo_module_missing');
    $moduleRow = $moduleRows[0];
    need((string)$moduleRow['code'] === 'opencart.html', 'seo_module_unexpected_code:' . (string)$moduleRow['code']);

    $setting = json_decode((string)$moduleRow['setting'], true, 512, JSON_THROW_ON_ERROR);
    need(is_array($setting) && isset($setting['module_description']), 'seo_module_setting_shape');

    $faqPlanned = false;
    $settingAfter = $setting;
    foreach ($settingAfter['module_description'] as $languageId => $description) {
        need(is_array($description) && array_key_exists('description', $description), 'seo_module_description_shape');
        $current = (string)$description['description'];
        if (str_contains($current, FAQ_MARKER)) {
            continue;
        }
        $settingAfter['module_description'][$languageId]['description'] = rtrim($current, "\r\n")
            . "\n\n" . encode_cms(faq_html());
        $faqPlanned = true;
        out('plan_faq_language', (string)$languageId);
    }

    $settingJson = json_encode($settingAfter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    /* ---- gate ----------------------------------------------------------- */

    $hasWork = $infoPlan !== [] || $layoutRows !== [] || $faqPlanned;
    if (!$hasWork) {
        if ($dryRun) {
            out('already_applied', 'yes');
            out('dry_run', 'ok');
            exit(0);
        }
        out('already_applied', 'yes');
        out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    if ($dryRun) {
        out('information_rows_already_done', (string)$infoAlready);
        out('dry_run', 'ok');
        exit(0);
    }

    /* ---- backup --------------------------------------------------------- */

    $root = rtrim((string)(getcwd() ?: __DIR__), "/\\");
    $backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . gmdate('Ymd-His');
    need(!file_exists($backupDir), 'backup_path_exists');
    need(mkdir($backupDir, 0750, true), 'backup_create_failed');

    $before = json_encode(
        ['information' => $infoRows, 'layout_module' => $layoutRows, 'module' => $moduleRow],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    need(file_put_contents($backupDir . '/before.json', $before . PHP_EOL, LOCK_EX) !== false, 'backup_write_failed');

    $restore = "-- " . PATCH_ID . " rollback\nSTART TRANSACTION;\n";
    foreach ($infoPlan as $item) {
        $restore .= 'UPDATE `' . $informationTable . '` SET `description` = ' . quote($db, $item['before'])
            . ' WHERE `information_id` = ' . INFORMATION_ID
            . ' AND `language_id` = ' . $item['language_id'] . ";\n";
    }
    if ($faqPlanned) {
        $restore .= 'UPDATE `' . $moduleTable . '` SET `setting` = ' . quote($db, (string)$moduleRow['setting'])
            . ' WHERE `module_id` = ' . SEO_MODULE_ID . ";\n";
    }
    foreach ($layoutRows as $row) {
        $restore .= 'INSERT INTO `' . $layoutModuleTable . '` '
            . '(`layout_module_id`,`layout_id`,`code`,`position`,`sort_order`) VALUES ('
            . (int)$row['layout_module_id'] . ','
            . (int)$row['layout_id'] . ','
            . quote($db, (string)$row['code']) . ','
            . quote($db, (string)$row['position']) . ','
            . (int)$row['sort_order'] . ");\n";
    }
    $restore .= "COMMIT;\n";
    need(file_put_contents($backupDir . '/restore.sql', $restore, LOCK_EX) !== false, 'restore_write_failed');
    out('backup', str_replace($root . '/', '', $backupDir));

    /* ---- write ---------------------------------------------------------- */

    $db->begin_transaction();
    $transaction = true;

    foreach ($infoPlan as $item) {
        $affected = execute(
            $db,
            'UPDATE `' . $informationTable . '` SET `description` = ?
              WHERE `information_id` = ? AND `language_id` = ?',
            [$item['after'], INFORMATION_ID, $item['language_id']]
        );
        need($affected === 1, 'information_update_failed:language_id=' . $item['language_id']);
    }

    foreach ($layoutRows as $row) {
        $affected = execute(
            $db,
            'DELETE FROM `' . $layoutModuleTable . '`
              WHERE `layout_module_id` = ? AND `layout_id` = ? AND `code` = ?',
            [$row['layout_module_id'], HOME_LAYOUT_ID, LEGACY_MODULE_CODE]
        );
        need($affected === 1, 'layout_module_delete_failed:' . (int)$row['layout_module_id']);
    }

    if ($faqPlanned) {
        $affected = execute(
            $db,
            'UPDATE `' . $moduleTable . '` SET `setting` = ? WHERE `module_id` = ?',
            [$settingJson, SEO_MODULE_ID]
        );
        need($affected === 1, 'seo_module_update_failed');
    }

    /* ---- verify --------------------------------------------------------- */

    foreach ($infoPlan as $item) {
        $check = rows(
            $db,
            'SELECT `description` FROM `' . $informationTable . '` WHERE `information_id` = ? AND `language_id` = ?',
            [INFORMATION_ID, $item['language_id']]
        );
        need(count($check) === 1, 'verify_information_missing');
        $value = (string)$check[0]['description'];
        need(str_contains($value, 'bs-review-cards'), 'verify_cards_missing');
        need(str_contains($value, 't.me/boostershop_tcg/23'), 'verify_telegram_target_missing');
        need(!str_contains($value, 'Переглянути відгуки на OLX'), 'verify_old_link_present');
        need(str_contains($value, 'olx.ua/uk/list/user/ubnF9'), 'verify_olx_target_missing');
    }

    $leftover = rows(
        $db,
        'SELECT COUNT(*) AS `total` FROM `' . $layoutModuleTable . '` WHERE `layout_id` = ? AND `code` = ?',
        [HOME_LAYOUT_ID, LEGACY_MODULE_CODE]
    );
    need((int)$leftover[0]['total'] === 0, 'verify_layout_module_remains');

    $moduleStillThere = rows($db, 'SELECT COUNT(*) AS `total` FROM `' . $moduleTable . '` WHERE `module_id` = 5');
    need((int)$moduleStillThere[0]['total'] === 1, 'verify_legacy_module_deleted_by_mistake');

    if ($faqPlanned) {
        $check = rows($db, 'SELECT `setting` FROM `' . $moduleTable . '` WHERE `module_id` = ?', [SEO_MODULE_ID]);
        $decoded = json_decode((string)$check[0]['setting'], true, 512, JSON_THROW_ON_ERROR);
        foreach ($decoded['module_description'] as $languageId => $description) {
            need(str_contains((string)$description['description'], FAQ_MARKER), 'verify_faq_missing:' . $languageId);
            need(str_contains((string)$description['description'], 'Booster Shop'), 'verify_original_copy_lost:' . $languageId);
        }
    }

    $db->commit();
    $transaction = false;

    out('information_rows_updated', (string)count($infoPlan));
    out('layout_module_rows_removed', (string)count($layoutRows));
    out('faq_installed', $faqPlanned ? 'yes' : 'already');
    out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($transaction && $db instanceof mysqli) {
        $db->rollback();
    }
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . PHP_EOL);
    exit(1);
}
