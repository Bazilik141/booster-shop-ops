# CONTENT-005 — pre-deploy review of the publication patch

Date: 2026-08-10
Reviewer: Claude (chat), `bs-patch-review`
Patch: `patches/CONTENT-005_starter-decks-publish_20260810.php` (429 lines, 61 716 bytes)
Executor report: `diagnostics/CONTENT-005_starter-decks-publish_report_20260810.md`
Handoff: `handoffs/handoff_CONTENT-005_claude-code-publish-starter-decks_20260810.md`
Content source: `plans/CONTENT-005_starter-deck-cards_final_20260810.md`

**Verdict: `Return for changes`** — one blocking defect, small and local. Everything else is strong.

---

## What was verified, and how

### Payload integrity — passed, independently

The embedded base64 payload was decoded in the sandbox and compared field by field against the plan
file. `PAYLOAD_SHA256` recomputes correctly (`ccebeb5deed546b5…`), and for all five SKUs the
`description`, `name`, `meta_title`, `meta_description`, `meta_keyword` and `seo` are **byte-identical
to the plan**. Eight attribute rows per product. The FAQ markup, ARIA wiring and the
`prod-st32` … `prod-st36` ids survived unedited.

This is the check that mattered most and it is clean. The executor did not "improve" the copy.

### Seven patch conventions

| # | Convention | Result |
|---|---|---|
| C1 | File-exists check | **Pass, adapted.** No file is edited; instead it requires `config.php` in `getcwd()` (`run_from_public_html_required`) and calls `table_exists` on all twelve tables. |
| C2 | Anchor pre-check | **Pass, unusually strong.** Twelve OP-16 template fields, language, categories `[60,68]`, store `[0]`, empty layout set, the OP-16 SEO row, manufacturer name `Bandai`, stock status name `Передзамовлення`, seven existing attribute ids **and** their sort orders, SEO keyword collision, FAQ-id collision. Any drift aborts. |
| C3 | Backup before write | **Pass, with a caveat.** `_patch_backups/<PATCH_ID>-<ts>/prestate.json` is written before the transaction. It is a *record* of prior state, not a restorable dump — for an insert-only patch that is adequate, and the header rightly makes a fresh cPanel MySQL backup mandatory. |
| C4 | `php -l` gate | **Pass, adapted** — see F2. Nothing is written to disk, so the patch lints *itself* at startup instead of linting a written target. |
| C5 | Idempotent marker | **Pass, strong.** A complete pre-existing set returns `already_applied=yes` and writes nothing; a *partial* set aborts rather than half-repairing. |
| C6 | DB changes | **Pass.** Ordered rollback SQL in the header, `--apply` required, no default write, fresh DB backup demanded. |
| C7 | Self-delete | **Pass.** `@unlink(__FILE__)` on success, result reported. |

### Scope

- **S1 · Do not touch — pass.** Every statement is an `INSERT`. Product 107 is read, never written.
  `product_related` rows are created one-directionally (`new → 107`), so the OP-16 box row is
  untouched. That is the correct reading of the handoff's exclusion and worth calling out as a
  deliberate good choice, not an omission.
- **S2 · One work package — pass.** Five products plus one attribute, one coherent package, one
  transaction.
- **S3 · Risky zones — DB, Merchant feed, SEO.** All three apply and are named in the smoke section
  below.
- **S4 · UI/CSS discipline — n/a.** No CSS, Twig or JS is touched.
- **S5 · SEO URL rules — pass.** `One-Piece-Starter-Deck-ST-32-Roronoa-Zoro` and siblings are
  human-readable and contain no SKU.

### Safety

- **B1 · Destructive ops — clean.** No `DROP`, `TRUNCATE`, `DELETE` or `UPDATE` in the executed path.
  The header's rollback `DELETE`s are bounded by explicit id lists and are run by hand.
- **B2 · Secrets — clean.** Credentials come from `config.php`; nothing literal in the file.
- **B3 · Unbounded reach — see D1.** Row count is pinned at five. **Column count is not.**
- **B4 · Syntax — not verified locally.** PHP is unavailable in the review sandbox. Residual risk is
  low: PHP refuses to execute a file that does not parse, and the patch additionally self-lints
  before touching the database.

---

## D1 · Blocking — the product row clones every column, including ones it should not

```php
$columns = rows($db, 'SHOW COLUMNS FROM ' . $t('product'));
foreach ($fields as $field) {
    if ($field === 'product_id') { continue; }
    $insertFields[] = $field;
    $templateValues[] = $source[$field];
}
```

Only four values are then overridden: `model`, `sku`, `price`, `image`. **Everything else on product
107 is copied verbatim onto all five decks**, including the columns a standard OpenCart 4 `product`
table carries: `ean`, `jan`, `isbn`, `upc`, `mpn`, `location`, `weight`, `length`, `width`, `height`,
`points`, `rating`, `viewed`, `date_added`, `date_modified`.

Two consequences, in order of severity:

1. **Product identifiers.** If product 107 has any value in `ean` / `upc` / `jan` / `isbn` / `mpn`,
   five starter decks silently inherit the booster box's identifier. That is a false product
   identifier on five live pages and a duplicate-GTIN condition in the Merchant feed. It also
   collides directly with the standing Booster Shop rule never to publish an invented or unverified
   GTIN. The patch verifies twelve template columns but **not these** — it only asserts
   `$op16['sku'] === null`.
2. **Dates and counters.** `date_added` and `date_modified` come from OP-16, so the decks are born
   backdated: "latest products" ordering, admin sorting and any date-based module will place them
   wrongly. `viewed` and `rating` are inherited too.

This is the `B3 · unbounded reach` pattern: bounded to five rows, unbounded across columns. A column
added to the table later would be cloned as well, with nobody noticing.

**Required fix, small and local.** After building `$values`, set explicitly:

- `ean`, `jan`, `isbn`, `upc`, `mpn`, `location` → `''`
- `viewed`, `rating` → `0`
- `date_added`, `date_modified` → now

and add an assertion that those identifier columns were empty on 107 to begin with, so a surprise is
reported instead of propagated. Weight and dimensions may keep the template values or be zeroed — the
owner decides; a starter deck is not a booster box, but nothing in the shop currently uses them.

---

## Non-blocking findings

| ID | Where | Issue | Suggested fix |
|---|---|---|---|
| F1 | patch, insert loop | No OpenCart cache invalidation after insert. New products and their SEO URLs may not surface until the cache turns over. | Clear `system/storage/cache` after commit, or add a cache-clear step to the owner's QA list. |
| F2 | patch:166 | The self-lint uses `exec()`. If `exec` is disabled in php.ini — common on cPanel — `$lintStatus` stays `1` and the patch aborts with `php_lint_failed` even though nothing is wrong. Fails closed, so it is safe, but it can block the run for the wrong reason. | Treat "exec unavailable" separately from "lint failed", or drop the self-lint: PHP will not run an unparseable file anyway. |
| F3 | patch, `already_applied` branch | The idempotent exit does not self-delete, so a second run leaves the file on the server. Defensible, but C7 says the patch removes itself after success and this is a success path. | State the intent in the header, or unlink there too. |
| F4 | executor report:5 | `Executor: Codex`. The owner assigned **Claude Code** to this step on 2026-08-10. | Correct the report so the roadmap history stays accurate. |
| F5 | patch, image column | `image` is set to `''`. Correct per the handoff — but an empty image is a Merchant Center disapproval reason. | Owner uploads photos before the feed next runs; keep the products out of the feed until then if the feed is scheduled. |
| F6 | my handoff §7 | The acceptance criterion "attribute table shows all eight rows, **in the plan file's order**" cannot pass: the storefront orders by attribute `sort_order`, so `Додатковий вміст` (4) renders before `Кількість карток у колоді` (5). | **My error, not the patch's.** The criterion is corrected below. Changing `Додатковий вміст`'s sort order would touch an attribute shared with other products and is out of scope. |

---

## Corrected acceptance criterion (supersedes handoff §7 line on attribute order)

> Each page's `Характеристики` tab shows all eight rows. Display order follows OpenCart `sort_order`:
> `Назва сету`, `Мова`, `Тип пакування`, `Додатковий вміст`, `Кількість карток у колоді`, `Стан`,
> `Виробник`, `Рік випуску`.

---

## Before the run

Expected counts to compare against the patch output:

| Object | Expected |
|---|---|
| Products | 5 |
| `product_description` rows (language 4) | 5 |
| `product_attribute` rows | 40 |
| `product_related` rows | 10 |
| `seo_url` rows | 5 |
| New attributes | 1 |
| Tables changed | 9 |

The patch asserts every one of these inside the transaction before committing, so a mismatch rolls
back rather than half-publishing.

**C7 consequence:** the patch deletes itself after a successful `--apply`. If it has to run again —
for a second store, or after a rollback — the file must be re-uploaded from `patches/`. Keep the repo
copy.

## Rollback

Three layers, in the order to reach for them:

1. The fresh cPanel MySQL backup the owner takes immediately before `--apply`. This is the real one.
2. The ordered rollback SQL in the patch header, using `rollback_product_ids_csv` and
   `new_attribute_id` printed on success. Run the last two `DELETE`s only when
   `new_attribute_created=yes`.
3. `_patch_backups/CONTENT-005_starter-decks-publish_20260810-<ts>/prestate.json` — a record of the
   template state, useful for diagnosis, **not** a restorable dump.

---

# Round 2 — re-review of the revised patch, 2026-08-10

Patch now 467 lines / 64 047 bytes (was 429 / 61 716). Only the delta was re-read, plus a full
re-verification of the payload.

**Verdict: `Deploy OK; є неблокуючі зауваження`.**

## Payload — re-verified, unchanged

`PAYLOAD_SHA256` still recomputes to `ccebeb5deed546b5…` and all five descriptions remain
byte-identical to `plans/CONTENT-005_starter-deck-cards_final_20260810.md`. The revision did not
disturb the content.

## D1 — fixed, and fixed properly

The `SHOW COLUMNS` clone loop is gone. The insert is now built from two explicit sets:

- `$copyFields` — twelve values genuinely inherited from the template: `quantity`,
  `stock_status_id`, `manufacturer_id`, `shipping`, `tax_class_id`, `date_available`,
  `weight_class_id`, `length_class_id`, `subtract`, `minimum`, `sort_order`, `status`.
- `$fixedValues` — everything else set deliberately: identifiers and `location` blank, `points`,
  `weight`, `length`, `width`, `height`, `viewed`, `rating`, `master_id` zero, `price` `700.0000`,
  `image` empty, `date_added` / `date_modified` at run time.

Two additions I did not ask for and which are better than what I asked for:

```php
foreach (['ean', 'jan', 'isbn', 'upc', 'mpn', 'location'] as $field) {
    require_true($source[$field] === null || trim((string)$source[$field]) === '',
        'op16_' . $field . '_must_be_empty');
}
```

— a surprise on the template now aborts instead of propagating, and:

```php
require_true(
    (string)$column['Null'] === 'YES' || $column['Default'] !== null
        || stripos((string)$column['Extra'], 'auto_increment') !== false,
    'unhandled_required_product_column_' . $field
);
```

— any future non-nullable column without a default aborts the patch rather than being silently
omitted. That closes the forward-compatibility hole in the original finding, not just the present
symptom.

## F2 — fixed

`function_exists('exec')` plus a `disable_functions` check; when unavailable it reports
`php_lint=skipped_exec_unavailable_php_parser_already_validated` and continues. No more false abort
on hosts with `exec` disabled.

## F3 — fixed

The `already_applied` branch now self-deletes on `--apply` and deliberately does not on `--dry-run`.
That distinction is correct.

## F1 — addressed outside the patch, acceptable

Cache clearing moved into the owner's run command rather than into the patch. Reasonable: it is a
deployment step, not a data change, and it is chained with `&&` so it only runs after a successful
apply. See N1 below.

## F4 — noted, not verified

The report now states the owner reassigned the executor from Claude Code to Codex. That
reassignment does not appear in the chat record available to this reviewer; it is recorded here as
the executor's claim so the roadmap history is not silently rewritten. It has no bearing on the
patch.

## Remaining, all non-blocking

| ID | Where | Note |
|---|---|---|
| N1 | report, run command | The cache one-liner depends on `DIR_CACHE` being defined by `config.php` and on OpenCart 4's cache file naming. Neither was verified from this side. It runs after the patch has already committed and is chained with `&&`, so a failure harms nothing. If it errors, delete the contents of `system/storage/cache/` by hand instead. |
| N2 | patch, `$fixedValues` | `date_added` / `date_modified` use `gmdate()`, i.e. UTC. Kyiv is UTC+3 in August, so the five rows will read three hours earlier than the real insert time. Invisible to customers; affects only admin sorting. |
| N3 | patch, `already_applied` branch | Prints `already_applied=yes` but no `done=ok`, unlike every other exit path. Cosmetic output inconsistency. |
| N4 | carried from round 1 (F5) | Empty `image` is a Merchant Center disapproval reason. Upload the photos before the feed next runs or before any Merchant validation. |

## Smoke after deploy

- `bs-deploy-verify` — general post-deploy checklist.
- `bs-merchant-schema-qa` — mandatory here. Five new products enter the feed and generate Product
  schema; D1 and F5 both land in this gate.
- `bs-seo-risk-gate` — before the sitemap is regenerated with the five new URLs.
- `bs-checkout-smoke` — not applicable; no purchase-flow code is touched.
