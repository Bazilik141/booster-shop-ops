# CONTENT-QUALITY — patch review 2026-08-27

Date: 2026-08-27 | Task: `CONTENT-QUALITY` | Author: Claude (chat)
Reviewed: `patches/CONTENT-QUALITY_cards-update-28_20260827.php`, `..._categories-73-74_20260827.php`, `..._create-br-charm-200_20260827.php`
Contract: `handoffs/handoff_CONTENT-QUALITY_patch-30-cards_20260827.md` + `addendum_CONTENTQUALITY_patchfaqrecovery_20260827.md`
Evidence: full read of all three runners, decode and verification of all three embedded payloads, `backup-8.24.2026_10-35-09_boosters` for live schema, names, slugs and template rows

**Verdict: Return for changes.** Six blocking defects, one of which stops all three files before a single guard executes.

The engineering is otherwise strong and should not be rewritten: payload integrity hashing, `--dry-run`, `before.json` + `restore.sql` written before any write, a single transaction with rollback, prepared statements throughout, and post-write assertions on encoding, product names and commercial fields. The defects below are scope and target-platform, not craft.

---

## 1. Blocking

### B1 · `never` return type — PHP 8.1 syntax on a PHP 8.0 host. All three files.

```php
function fail(string $message): never { throw new RuntimeException($message); }
```

`cards-update-28` line 21, `categories-73-74` line 19, `create-br-charm-200` line 20.

`never` was introduced in PHP 8.1. Production runs PHP 8.0 (`project_production_php_80.md`, recorded from an earlier incident on this host). The file fails to parse, so `lint_self()`, the config check, the backup and the transaction never run — the owner sees a parse error and nothing else. This is the same failure mode that has already cost this project one deploy round.

Fix: `: void` with the `throw` kept, or no return type. Then re-verify under an 8.0 parser, not the local 8.4.

No other 8.1+ construct is present — `str_contains`, `str_starts_with`, `mixed`, constructor-less destructuring and `match`-free code are all 8.0-safe. `never` is the only offender.

### B2 · WP1 creates `PKM-JP-SVEL-SET`, which both governing documents place out of scope

`cards-update-28` lines 89–98, 118, 120, 131, 135 create the product, its description, store and category links, `seo_url` row and attributes.

- Parent handoff §6: out of scope — no decided slug, no price, no CRM row.
- Addendum §8: *"Keep it out of WP1/WP2/WP3 … creation of that product still requires its own explicit create-patch handoff. Do not silently fold it into this wave."*

It also breaks the one-work-package rule: the 28-card content update and a product creation share one patch file and one transaction, so the owner cannot roll back the creation without rolling back all 28 descriptions, and cannot re-run one without the other.

### B3 · `PKM-JP-SVEL-SET` physical specs are copied from a booster box

`cards-update-28` line 120 takes the template from product 146 `PKM-JP-STES-BBX` and line 90 writes its `weight`, `weight_class_id`, `length`, `width`, `height`, `length_class_id`, `manufacturer_id`, `shipping`, `tax_class_id` and `sort_order` onto the new product.

Product 146 in the backup: weight `300.00000000`, dimensions `150 × 150 × 50`. That is a 30-pack booster box. A starter set is a different package, and these values drive the delivery calculation.

Inventing specifications is a hard brand rule for this project. Even after B2 is resolved and the product gets its own patch, the dimensions and weight must come from the owner or from the physical product, not from a sibling row.

### B4 · `PKM-JP-SVEL-SET` slug is invented and does not match the live convention

Payload value: `pokemon-tcg-starter-set-terastal-loudbone-ex-jp`.

The release package says explicitly: *"SEO URL: resolve against existing site convention during release; do not invent a new global convention."*

Live sealed-product slugs in `ocp5_seo_url` (94 product rows in the 24.08 backup) are capitalised, hyphenated and carry no locale suffix:

```
Pokemon-booster-box-Hot-Wind-Arena
Pokemon-boosters-Munics-Zero
One-Piece-Starter-Deck-ST-36-Eustass-Kid
One-Piece-Starter-Deck-ST-32-Roronoa-Zoro
```

The generated slug is all-lowercase with a `-jp` tail and matches neither that convention nor the separate lowercase transliterated convention the 3D products use. This is an owner decision, not an executor default.

### B5 · The addendum is not implemented — the payload is plain v2

Decoded `PAYLOAD_B64` (integrity hash verifies) contains exactly the audited v2 content for all 28 cards, byte-for-byte identical to the repository release archive including the `/product/` prefixes. That part is correct. What is missing is the entire overlay:

| Measure | Addendum §6 requires | Payload actually contains |
|---|---:|---:|
| FAQ items across the 28 cards | 52 | **43** |
| Cards with 1 item | 11 | **17** |
| Cards with 2 | 11 | **7** |
| Cards with 3 | 5 | **4** |
| Cards with 4 | 1 | **0** |

Per card: `ACC-3D-PKM-110` has 1 item, not 3. `ACC-3D-PKM-120`, `-130`, `-200`, `-700` and `FIG-LUFFY-500` have 1, not 2. `ACC-007-400` has 2, not 4. And `PKM-JP-INFX-BBX` does not contain the approved sentence — a text search for `не гарантуються виробником` in its body returns nothing.

None of the addendum §6 assertions exist in the runner either; the run prints `updated_descriptions=28` and no FAQ count at all.

### B6 · No idempotency marker — convention C5 missing in all three

`AGENTS.md` requires `already_applied=yes` on a repeat run. None of the three files contains the string.

Worse than a missing report: each one *fails* on a second run, and with a misleading message.

- WP1 asserts `affected_rows === 1` per description; MySQL reports 0 changed rows when the value already matches, so a re-run dies on `description_update_failed:BR-MEW-100` as if the write had failed.
- WP2 dies the same way on `category_update_failed:73`.
- WP3 dies on `new_sku_exists`, which at least reads correctly.

Combined with C7 self-delete, the practical effect is: if the owner re-uploads a patch after a partial-looking run, the error text will suggest corruption where there is none.

---

## 2. Non-blocking

| ID | Where | What | Fix |
|---|---|---|---|
| N1 | WP1 payload | Nine cards carry a `name` field that is never written. Three of those values are the wrong payload names (`ACC-007-400`, `YGO-JP-QCAC-BBX`, `YGO-JP-BETB-BBX`/`-BST`) documented in parent §4. The field sits one line away from the write loop. | Drop `name` from the payload; the post-write assertion at line 133 already covers the guarantee. |
| N2 | WP2, WP3 | Both carry the full helper set from WP1 — `check_html`, `html_encode`, `require_columns`, `restore_update`, `appender` — several unused. WP2 in particular has no HTML to check. | Cosmetic; trim or leave, but it inflates the read for the next reviewer. |
| N3 | WP1 payload | `PKM-JP-SVEL-SET` price `1600.0000` has no source in the repository. | When the product gets its own patch, record where the price came from. |
| N4 | WP3 | `subtract=1`, `minimum=1`, `rating=0` are hardcoded rather than taken from the `BR-CHARM-100` template row. | Harmless while `quantity = 0` and `config_stock_checkout = 0`; align with the sibling for consistency. |

---

## 3. Verified correct — do not change in the next round

- Payload integrity: base64 + SHA-256 checked at load; all three hashes verify.
- WP1 content is byte-identical to the repository v2 archive for all 28 cards, and `check_html` enforces at runtime: exactly one `bs-faq-accordion` section, no legacy markup, unique HTML ids, and every `href` starting `/product/`.
- `name` is not written and is asserted equal to the pre-change value after the write (line 133) — the four-name trap from parent §4 is correctly avoided.
- `status`, `price`, `quantity`, `stock_status_id`, `image` re-read and asserted unchanged for all 28 products (line 132).
- Storage encoding asserted both ways: `&lt;h2&gt;` present and raw `<h2>` absent (line 133). `html_encode` uses `ENT_COMPAT`, which matches how the live rows are stored.
- Attributes 43 and 44 are read and compared to their expected names and neither is created nor renamed — exactly the verification-only scope the parent handoff asked for.
- Attribute ids are resolved by name against `attribute_description` and the run aborts on a missing attribute rather than creating one.
- `ocp5_seo_url` and `ocp5_product_attribute` inserts match the live schema in the 24.08 backup, including the `ON DUPLICATE KEY` requirement on the composite primary key.
- WP2 verifies both categories are disabled before and after, and refuses to run otherwise.
- WP3 takes its template from product 126 `BR-CHARM-100` — the correct sibling — inserts with `status = 0`, `quantity = 0`, `stock_status_id = 8`, the 1 UAH placeholder price, categories 59 + 73, and verifies the Mystery Box attribute value after the write.
- `--dry-run` exits before the backup directory is created, so it is genuinely side-effect free.
- C1–C4, C6, C7 are satisfied in all three: anchor pre-checks, backup before write, `php -l` self-gate, rollback SQL in the header and in `_patch_backups/`, self-delete after commit.

No destructive operation, no secret, no unbounded write, no syntax error under the local parser.

---

## 4. Process note on the addendum

The addendum asks for FAQ answers to be recovered from the pre-change live rows and rephrased. That produces **new client-facing Ukrainian copy** — seven cards, roughly nine items, plus one body sentence.

Two consequences the next round has to respect:

1. The recovery happens at **generation** time against the backup, not at runtime. A PHP runner cannot rephrase copy; the finished text must be in the payload and covered by the payload hash.
2. That text has never been through a content gate. It needs a `bs-content-qa` / `bs-3dp-card-qa` pass before it reaches a patch — the same gate the v2 wave went through — with particular attention to the deprecated claims the addendum lists in §2 (`зручний кут`, untested stability, unconfirmed compatibility).

---

## 5. What the next round must produce

1. Replace `: never` in all three files and confirm against a PHP 8.0 parser.
2. Add `already_applied=yes` to all three.
3. Remove `PKM-JP-SVEL-SET` from WP1 entirely — payload, insert, template lookup and the restore-side DELETE block. It returns as its own patch after the owner decides slug, price, weight and dimensions.
4. Author the addendum FAQ recovery and the Inferno X sentence, put them through a content gate, embed them in the WP1 payload, and add the §6 count assertions plus the `52` output line.
5. WP2 and WP3 need only items 1 and 2; their scope and logic are correct as they stand.
