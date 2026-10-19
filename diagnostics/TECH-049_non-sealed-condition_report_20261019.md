# Claude Code Report — TECH-049: used condition for non-sealed Bulk cards (JSON-LD + Merchant feed)

Date: 2026-10-19
Executor: Claude Code (cloud session, handoff section 0). Handoff: `handoffs/handoff_TECH-049_non-sealed-condition_claude-code_20261019.md`.

## Blocker found outside scope — read first

The **unmodified** live generator fails on the 10.15.2026 catalog data:

```
error=duplicate product ids in feed source: PKM-JP-ABYE-BBX
exit=1
```

Products 94 («Бустер бокс Pokémon TCG: Abyss Eye (Японське видання)») and 201 (same name + «- уцінка через пошкоджену плівку бокса.», added 2026-10-10 23:14) are both `status=1` with model `PKM-JP-ABYE-BBX`. The generator uses the model as the feed `id` and refuses to replace `merchant-feed.tsv` on a duplicate. If the live DB still has both rows, **every** run of the generator since 2026-10-10 has exited without updating the feed, and handoff QA steps A3 and B5 cannot pass, with or without TECH-049. The patched generator behaves identically here, so TECH-049 neither caused nor fixes this. Not verified against the live DB (the extract is from 10.15) or the live `merchant-feed.tsv` (not in inputs).

Owner decision needed: give product 201 its own model (SKU), or disable one of the two rows, before running the generator. This is a catalog edit outside TECH-049; it is not in either patch.

## Scope

Implemented 1:1 with handoff section 4. Rule in both places: `model` contains `-BULK-`, case-insensitive.

- **WP1** `patches/TECH-049_product-schema-condition_20261019.php` → `public_html/catalog/view/template/product/product.twig`. Only line 1040 (offer-level `itemCondition`) changes, to the Twig expression suggested in the handoff. `model` is passed by the controller (`catalog/controller/product/product.php:299`, `$data['model'] = $product_info['model']`, set on every product). Anchor = that line + the following `"hasMerchantReturnPolicy": {` line (count exactly 1). The policy-level `itemCondition` is checked to stay exactly once and unchanged.
- **WP2** `patches/TECH-049_merchant-feed-condition_20261019.php` → `~/merchant-feed-build.php`. Adds `is_non_sealed_bulk()` + `feed_condition_for()`, a first branch in `product_type_for()` returning `<family> > Bulk Set`, and replaces the `'new',` literal in the row array. No change to the SELECT, headers, `id`, price, availability, link, image, brand or `identifier_exists`. Structure follows `GMC-OPS_merchant-feed-backup-retention_20260921.php`; anchors span the neighbouring lines so the patch fails if TECH-048 has already changed that area.

Precedents used:
- Twig syntax gate (replaces `php -l` for `.twig`): `RD-12-UI-FIX_toast-canonical-rebuild_20260922.php` — the site's own Twig from `DIR_STORAGE/vendor/twig/twig/src` tokenizes and parses the full template before writing and again after writing. In addition, the gate renders the changed line for `pkm-jp-bulk-100`, `OP-JP-OP01-RPK` and an empty model, and aborts if the output is not what is expected.
- Compiled-template cache: no file is deleted. OpenCart's Twig runs with `auto_reload => true` (`system/library/template/twig.php:109`, recorded in the ACC-003 handoff; not re-verified here), so the new mtime of the rewritten file triggers recompilation. This is the same approach as the RD-12 / RD-PP-META product.twig patches.

## Files touched

```
patches/TECH-049_product-schema-condition_20261019.php   sha256 0041453ecbce2a6abdcb9d4c55b1603141211d785e703008bef13f9451bef423
patches/TECH-049_merchant-feed-condition_20261019.php    sha256 5bc1b264d264af0f5d753f1f59cbe017c36b9f3ee052fee614979f1d88f0fcc6
diagnostics/TECH-049_non-sealed-condition_report_20261019.md
```

Targets (if the live files match the inputs; each patch prints `sha256_before`/`sha256_after`):

| Target | before | after |
|---|---|---|
| `public_html/catalog/view/template/product/product.twig` | `db2de45a6ecac92cde119bf84546d77883be731f3dee4c737ff9c12ce9c5748e` | `882eeb6de97308267802c8833a385f6ef1fa05b2c082e362f7f158eb4046f536` |
| `~/merchant-feed-build.php` | `ef75327cba3c906d0d0779841bdd9620c4f1649894ee9c2fc4de6b1801ee177d` | `e5406b06e74f604db839462573c4adfeedef4a5260e0197bfeee358ce736c20a` |

A different `sha256_before` on the server is not an error in itself. The anchors decide whether the patch applies, but a mismatch means the live file differs from the 10.15 backup.

## Test environment

Scratch tree with `home/merchant-feed-build.php`, `public_html/config.php` stub (DB constants and `DIR_STORAGE` only) and `storage/vendor/twig` = Twig **3.18.0** (the version TECH-045 found on the site). Scratch MariaDB 10.11 loaded from `catalog-extract_backup-10.15.2026.sql.txt`. CLI PHP 8.3.6. No real database or server was touched.

## Acceptance evidence

**AC1 + AC2 — Twig render.** The Product JSON-LD `<script>` block is extracted from the original and the patched template and rendered with Twig 3.18.0 in 3 contexts (full; special price + preorder + no rating + no extra images; no manufacturer/popup/description) × 8 models:

```
model                    offer           policy         check
PKM-JP-BULK-155          UsedCondition   NewCondition   valid_json, diff = offer line only
OP-JP-BULK-155           UsedCondition   NewCondition   valid_json, diff = offer line only
PKM-MIX-BULK-100         UsedCondition   NewCondition   valid_json, diff = offer line only
pkm-jp-bulk-100          UsedCondition   NewCondition   valid_json, diff = offer line only
OP-JP-OP01-RPK           NewCondition    NewCondition   valid_json, byte_identical
PKM-JP-EXSD-STD-GRS      NewCondition    NewCondition   valid_json, byte_identical
ACC-3D-ONIX-110-21-BLK   NewCondition    NewCondition   valid_json, byte_identical
<empty>                  NewCondition    NewCondition   valid_json, byte_identical
failures=0   (24 renders)
```
An undefined `model` also renders `NewCondition`. Not run: a full OpenCart page render (header/footer controllers, theme override); covered by owner QA A2/B4.

**AC3 — feed unchanged for current data.**
- On the extract as-is, old and new generator both stop with the duplicate-`id` error above. The outputs are identical, and `merchant-feed.tsv` is not written.
- Fixture: product 201 set to `status=0` in a second scratch DB, only to get past the duplicate. Both generators then write `rows_written=125 in_stock=104 preorder=21`, and the outputs are byte-identical (sha256 `0c7de0d2…a0a6`). `condition` = `new` on all 125 rows.

**AC4 — Bulk rows.** The three CAT-005 cards are inserted into the fixture DB as eligible rows (`status=1`, `quantity=10`, placeholder image; names, meta descriptions, manufacturers and prices from `CAT-005_bulk-cards_20261018.php`):

```
PKM-MIX-BULK-100  in_stock  150.00 UAH  used  The Pokémon Company  no  no  Pokemon TCG > Bulk Set
OP-JP-BULK-155    in_stock  250.00 UAH  used  Bandai               no  no  One Piece Card Game > Bulk Set
PKM-JP-BULK-155   in_stock  250.00 UAH  used  The Pokémon Company  no  no  Pokemon TCG > Bulk Set
```
`rows_written=128` (+3). With the 3 Bulk rows removed, the output is byte-identical to the AC3 baseline. No duplicate `id`. For comparison, the unpatched generator types the same three rows as `new` / `… > Booster Pack`.

**AC5 — patch mechanics** (both patches, fresh trees each time):

| Test | WP1 | WP2 |
|---|---|---|
| 1st run | `done=ok`, backup written, self-deleted | `done=ok`, backup written, `php_lint` clean, self-deleted |
| 2nd run | `already_applied=yes`, exit 0 | `already_applied=yes`, exit 0 |
| Anchor mutated | offer line re-indented → `anchor_count … actual=0`, exit 1, target unchanged, no backup | `'new',` → `"new",` → `expected anchor count != 1 name=condition_literal actual=0`, exit 1, target unchanged, no backup |
| Other failure | unrelated broken Twig tag → `twig_compile_failed line=1549`, exit 1, nothing written | marker present + literal present → `partial or unexpected state …`, exit 1 |
| Rollback | backup sha = `db2de45a…748e` (original) | backup sha = `ef75327c…e177d` (original) |

Not exercised: the restore-after-write branches (write failure, readback mismatch, post-write Twig/lint failure). They copy the backup back, using the same mechanism as the GMC-OPS / RD-12 precedents.

`php -l`: both patch files and the patched generator report `No syntax errors detected`. PHP 8.0: `phpcs` + PHPCompatibility (`testVersion 8.0`) reports 0 issues on both patches, the patched generator and the patched template. A negative control with 8.1 features was flagged, so the check does work.

**AC6 — write set.** Hash snapshot of the whole scratch tree before/after running both patches. The only changes are the two targets plus their two backup files (`public_html/_patch_backups/TECH-049_product-schema-condition_20261019-<ts>/…`, `~/_patch_backups/TECH-049_merchant-feed-condition_20261019-<ts>/…`). `merchant-feed.tsv` and `config.php` are unchanged, and the patch files deleted themselves.

## Run commands (owner)

Upload both files to `~/public_html`, then:

```bash
cd ~/public_html
php TECH-049_product-schema-condition_20261019.php
php TECH-049_merchant-feed-condition_20261019.php
```

Re-run the feed generator (QA steps A3 and B5). The generator builds paths from its own `__DIR__`, so the working directory does not matter. Running it before the duplicate-`id` blocker is fixed will print `error=duplicate…` and leave the feed as it was.

```bash
php ~/merchant-feed-build.php
```

The repository does not record how the generator is scheduled. The «manual tool» wording quoted in the handoff is not in the GMC-OPS patch or its commit. Whether a cron job also runs it is **unverified**.

## Rollback

```bash
cp ~/public_html/_patch_backups/TECH-049_product-schema-condition_20261019-<ts>/catalog/view/template/product/product.twig ~/public_html/catalog/view/template/product/product.twig
cp ~/_patch_backups/TECH-049_merchant-feed-condition_20261019-<ts>/merchant-feed-build.php ~/merchant-feed-build.php
php ~/merchant-feed-build.php
```

The exact `<ts>` paths are printed as `backup=` by each patch. The template rollback needs no cache step (auto_reload). No DB rollback is needed, because neither patch writes the DB.

## Post-deploy QA

As in handoff section 8. Additions:
- A3 and B5 depend on the duplicate-`id` blocker above being resolved first.
- B5: a card enters the feed only with a non-empty main image (`p.image <> ''`). CAT-005 created the cards with `image ''`.
- If a Bulk card still shows `NewCondition` after B4, check the theme DB override for `product/product` (not in the extract, so **unverified**) before any second patch.

## Side effects / risks

- `description_for()` has a fallback text that says «Original sealed…», used only when both meta description and description are empty. All three CAT-005 cards have a meta description, so the fallback is not reached. It is left unchanged (out of scope).
- `merchantReturnPolicy.itemCondition` stays `NewCondition` for Bulk cards, as the handoff requires (policy-level, out of scope).
- TECH-048 must rebase on WP2. WP2's anchors include `product_type_for()`'s first four lines and the `money_uah` / `'new'` / `brand_for` row lines.

## Status

TECH-049 stays **In progress** until deploy and Stage A pass. Closure (`Done`) is only after Stage B on the live cards and the owner's confirmation. The executor writes no Notion or dashboard status.
