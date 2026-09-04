# Claude Code Handoff — UI-FIX batch: round-1 fix requests (2026-09-03)

Executor: Claude Code. This is a response to `diagnostics/UI-FIX_mobile-desktop-polish-batch_report_20260902.md` (the 4-patch delivery: `UI-FIX_price-discount-rows_20260903.php`, `UI-FIX_cms-content_20260903.php`, `UI-FIX_home-category-tiles_20260903.php`, `UI-FIX_mobile-desktop-polish_20260903.php`). Claude (chat) ran a full `bs-patch-review` against `AGENTS.md` conventions on 2026-09-03 and found 5 findings (F1-F5). The owner has since dispositioned all five. **This file covers only F1, F3, F5 - the three that need a Claude Code fix.** F2 and F4 are closed; see section 4 for why, and section 5 for F4's literal replacement content, which still needs to land in the same patch file.

Read this file cold - it does not assume you have the review conversation. **This is round 1 of a two-round process.** After you apply these fixes and update the diagnostics report, the owner will open a **new session** to do round-2 review and the staged production rollout. Do not treat "fixes applied" as "cleared to deploy" - that call happens in round 2.

## 0. Do not touch, still applies

Everything in the original master handoff's "What NOT to touch" section (`handoffs/handoff_UI-FIX_codex-handoff_20260902.md`, section 3) still applies unchanged. This file only narrows scope further: touch only the exact lines named in sections 1-3 below, plus the one report addendum in section 2. Do not re-open Tasks 1-9's already-reviewed logic beyond what's specified here.

## 1. F3 - blocking: `never` return type breaks on production PHP 8.0

**Severity: blocking. This must be fixed before round-2 review can pass.** This is not a style note - a file containing this construct fails to parse at all on production, before any of its own guard code (including its own `php_lint()` self-check) can run.

**Why this is certain, not a guess:** production (`uashared43`) runs PHP 8.0.30 CLI, confirmed 2026-08-24 via `php -v`, and the only other cPanel PHP binary on the host is `ea-php72` - **there is no PHP 8.1+ binary available anywhere on production.** The `never` return type was introduced in PHP 8.1. On 2026-08-24 this exact mistake happened for real: two prior deliverables (`PAY-002_bank-test-drive_diagnostic`, `PAY-004_pumb-customer-selected-term`) both declared `function fail(...): never` and would have died with a bare PHP parse error before any of the patch's own Ukrainian-language failure messaging could run - the owner would have seen a raw PHP fatal error on a production terminal, not an actionable message.

**All 5 occurrences in this batch**, found by `grep -n "): never"` against the 4 delivered patches:

| File | Line | Function |
|---|---|---|
| `patches/UI-FIX_price-discount-rows_20260903.php` | 74 | `function fail(string $message): never {` |
| `patches/UI-FIX_cms-content_20260903.php` | 79 | `function fail(string $message): never {` |
| `patches/UI-FIX_home-category-tiles_20260903.php` | 72 | `function fail(string $message): never {` |
| `patches/UI-FIX_mobile-desktop-polish_20260903.php` | 94 | `function fail(string $message): never {` |
| `patches/UI-FIX_mobile-desktop-polish_20260903.php` | 154 | `function restore_files_and_fail(string $root, string $backupDir, Throwable $error): never {` |

Confirmed by grep across all 4 files that this is the *only* PHP 8.1+ construct present - no `readonly`, no `enum`, no first-class callable syntax `(...)`, no `array_is_list()`, no `new` in a parameter default. So the fix is narrowly scoped to these 5 lines.

**Fix:** drop the `: never` return-type declaration from each of the 5 signatures. Do not replace it with `: void` or anything else - just remove the annotation. These functions always `throw` before reaching a `return`, so removing the type declaration changes nothing about behavior; it only stops PHP 8.0 from refusing to parse the file. Example for the first occurrence:

```php
// before
function fail(string $message): never {

// after
function fail(string $message) {
```

Apply the same removal to all 5 lines above (same pattern - `): never {` becomes `) {`).

**Verification:** neither this repo's sandbox nor Claude (chat)'s cloud sandbox has a real PHP 8.0 binary to lint against (this was tried and failed - no Docker daemon, `php8.0-cli` not available via apt). Removing a return-type annotation is syntactically safe on every PHP version, so no new verification tooling is needed for the fix itself. But add one line to the "Перед запуском" (pre-flight) checklist in the diagnostics report addendum (section 2 below) telling the owner to run `php -l` on each patch from `~/public_html` in cPanel Terminal (real production PHP 8.0.30) as the actual syntax gate, since that's the only real PHP 8.0 available to anyone in this project.

## 2. F1 - silent skip on content drift can produce a false "already applied"

**File:** `patches/UI-FIX_cms-content_20260903.php`, inside the information-row loop building `$infoPlan` (currently around line 264-282).

**Current code:**

```php
    $infoPlan = [];
    $infoAlready = 0;
    foreach ($infoRows as $row) {
        $description = (string)$row['description'];
        if (str_contains($description, 'bs-review-cards')) {
            $infoAlready++;
            continue;
        }
        $count = substr_count($description, $oldLink);
        if ($count === 0) {
            continue;
        }
        need($count === 1, 'olx_link_anchor_count:' . $count . ':language_id=' . (int)$row['language_id']);
        $infoPlan[] = [
            'language_id' => (int)$row['language_id'],
            'before' => $description,
            'after' => str_replace($oldLink, $newCards, $description),
        ];
    }
```

**Problem:** each row has to land in exactly one of two buckets - "already applied" (has the `bs-review-cards` marker) or "needs the edit" (has the old OLX-link anchor). If a row has *neither* (content drifted for some other reason - manual admin edit, partial prior run, anything not anticipated), the `$count === 0` branch silently `continue`s. Downstream, `$hasWork = $infoPlan !== [] || $layoutRows !== [] || $faqPlanned;` - if this row was the only source of work and the other two checks (`$layoutRows`, `$faqPlanned`) also come up empty for unrelated reasons, the patch reports `already_applied=yes` and self-deletes, even though this row's content was never actually touched. That's a false positive that looks like success.

**Fix:** the `$count === 0` branch must fail loudly instead of silently continuing, since at that point it's neither the before-state nor the after-state this patch expects:

```php
        $count = substr_count($description, $oldLink);
        if ($count === 0) {
            fail('information_row_unexpected_content:language_id=' . (int)$row['language_id']
                . ' - neither the bs-review-cards marker nor the OLX link anchor was found; '
                . 'content has drifted from what this patch expects, refusing to guess');
        }
```

**Verification:** re-run `--dry-run` against production data (read-only) and confirm the row-classification counts (`information_rows_already_done` / `plan_information_rows`) still add up to the full row count with no row falling through uncounted. Note this in the updated diagnostics report.

## 3. F5 - undisclosed deviation: eager/fetchpriority on the Pokémon tile

**File:** `patches/UI-FIX_home-category-tiles_20260903.php`, lines ~289-290 (Pokémon tile `<img>`) vs. line ~303 (One Piece tile `<img>`):

```php
                 loading="eager" fetchpriority="high" decoding="async">   // Pokemon tile, line ~290
                 ...
                 loading="lazy" decoding="async">                         // One Piece tile, line ~303
```

**Not a defect** - eager-loading plus a high fetch-priority hint on the first, above-the-fold tile is a reasonable, low-risk LCP (Largest Contentful Paint) optimization. **The problem is purely disclosure:** the patch's own top-of-block comment says only "hrefs and order are unchanged from the previous markup" and says nothing about loading/fetchpriority attributes, and the diagnostics report's own "deviations from the handoff" table (near the top of `diagnostics/UI-FIX_mobile-desktop-polish-batch_report_20260902.md`) doesn't list this one. A reviewer has to notice it independently instead of being told.

**Fix - no code change required.** Add one row to the diagnostics report's existing deviations table (or a short addendum near it) stating: the Pokémon tile got `loading="eager" fetchpriority="high"`, the One Piece tile stayed `loading="lazy"`, and the rationale (first tile is above the fold on both mobile and desktop, so eager+high-priority shortens LCP; the second tile is typically below the fold). One sentence is enough - this just needs to be a self-disclosed, intentional choice on the record rather than a silent addition.

## 4. F2 and F4 - closed, no action needed here

- **F2 (Task 10 scope):** the owner confirmed 2026-09-03 that the simplified "v2 FINAL" version already implemented in `patches/UI-FIX_mobile-desktop-polish_20260903.php` (hardcoded "orientovno 3-4 tyzhni" string, product page only, gated on `_is_preorder`, no new DB table, no admin fields, no DB write) is final. Nothing to change. Task 11 (Component D, a separate design pass for this label) is now moot and does not need to happen - the hardcoded-string version doesn't need its own layout/typography brief.
- **F4 (FAQ copy):** Claude (chat) has rewritten the FAQ text directly - see section 5 for the literal replacement content. Do not touch the FAQ wording yourself beyond pasting in what's given below; there's no open question here.

## 5. F4 - finalized FAQ copy (paste into `faq_html()`)

**File:** `patches/UI-FIX_cms-content_20260903.php`, function `faq_html()` (currently starts at line 193). Replace the existing `$items = [ ... ];` array (currently lines 194-216) with the block below. Everything else in `faq_html()` (the wrapping `<div>`/`<details>` loop) stays as-is - only the `$items` array content changes. Same facts and same target links as the original draft (Pokémon/One Piece/Yu-Gi-Oh!+MTG/accessories) - this is a copy-polish pass, not a content change, so it doesn't need a fresh owner fact-check.

```php
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
```

## 6. What to send back

Update `diagnostics/UI-FIX_mobile-desktop-polish-batch_report_20260902.md` in place (or append a dated addendum section) covering:

- F3: confirm all 5 `: never` occurrences removed, and add the `php -l`-on-production pre-flight line to the owner-run checklist.
- F1: confirm the `fail()` branch added, and the dry-run row-classification re-check.
- F5: the one-line disclosure of the eager/fetchpriority choice.
- F4: confirm the FAQ array replaced verbatim as given in section 5.
- Re-run `php -l` in whatever sandbox you have (same caveat as before - not real 8.0, but still catches gross syntax errors) on all 4 patches after edits and report the result.

**Next step:** the owner will pick this up in a new session for round-2 review and staged production rollout. Do not run anything on production yourself - this batch has no staging, production is the only environment, and the owner is the sole deploy gate (`AGENTS.md`).

---
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01LiZyzyoCvT5guWsBVPtTgf
