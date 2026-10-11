# Codex Report — RD-25 WP1 F6: featured original-image payload

Date: 2026-10-10
Status: **Current featured source verified; companion patch prepared for review. Not deployed.**

## Scope and finding

Owner asked whether the non-blocking findings should be checked or corrected now, then supplied `RD-25-featured-check-20261010.tar.gz` in response to the one-file request. This authorizes the local F6 check and the proposed narrow fix; no commit, push, production deployment, roadmap mutation, WP2 or WP3 is included.

The archive contains exactly `extension/opencart/catalog/controller/module/featured.php`. At line 33, the module already loads each configured product through `getProduct()`. At line 65, its explicit `$product_data` array omits `image`; line 84 sends that array to `product/thumb`. Approved WP1 r3 therefore reloads the original source through its request-cached fallback. **The uncertainty in F6 is closed: the omission exists in the owner-provided current source.**

The companion adds only an idempotency comment and `'image' => $product['image']` to that array. It reuses the original source already fetched. No row union or commerce-field change is introduced. Removing those two added lines restores the original source bytes exactly. Existing product reads, legacy `resize()` calls, price visibility, tax, stock, preorder data, actions and module axis remain intact.

## Files and hash pins

Patch: `patches/RD-25-WP1_featured-image-payload_20261010.php` (10,257 bytes).
Only production target: `extension/opencart/catalog/controller/module/featured.php`.
Report: `diagnostics/RD-25-WP1_featured-image-payload_report_20261010.md`.

| Artifact | SHA256 |
|---|---|
| Current owner archive | `3a2c2e98469fb8da33d3450e8daf20aa60ed3caa5b8dea63ebab67d56a288846` |
| Featured before | `aa9bd528b74256883224d5406366edc5d171000d7fdd8ae61006068398b9f839` |
| Featured candidate | `b981a76485faf267c9362240dc50f972adb15379dffaf3a0ce52a93d3e1deaae` |
| Companion runner | `0f7b394c634a05f5b8abb4fa684647015b98442b7dbd604f477c432d9bce2aa3` |
| Approved WP1 r3 runner, unchanged | `49ff6c088caf05eecbba47d660af98ce18b85bd50739e1540a3bde637ce01e83` |

[Source diff](RD-25-WP1_featured-image-payload_evidence_20261010/source.diff), [manifest](RD-25-WP1_featured-image-payload_evidence_20261010/manifest.json), [completed six-caller evidence](RD-25-WP1_featured-image-payload_evidence_20261010/caller-image-audit.json).

This supplement supersedes the earlier report's missing-featured-source note. The approved r3 patch, report and sealed evidence remain unchanged. Its eleven targets are disjoint from this companion's target. F1/F5 classification, metadata v3, CSS/header token, JSON-LD and 700×700 lightbox paths are untouched.

## Call counts and local proof

The figures below are **getProduct API calls**, not counts of individual SQL queries. In the first module execution, let K be configured entries queried by Featured, U be distinct rendered positive IDs, and M be distinct rendered IDs with empty source images, excluding IDs already cached by another source-less card in the request. Existing Featured cost is K; approved r3 can add U calls; this companion adds only M. Repeated configured entries still retain Featured's existing reads.

| Fixture | Rendered cards | Existing Featured calls | Extra thumb calls before → after | Total before → after |
|---|---:|---:|---:|---:|
| normal | 24 | 24 | 24 → 0 | 48 → 24 |
| vertical | 24 | 24 | 24 → 0 | 48 → 24 |
| hidden | 24 | 24 | 24 → 0 | 48 → 24 |
| mixed | 24 | 24 | 24 → 1 | 48 → 25 |
| empty | 24 | 24 | 24 → 24 | 48 → 48 |
| missing | 24 | 24 | 24 → 0 | 48 → 24 |
| duplicates | 3 | 3 | 2 → 0 | 5 → 3 |
| invalid | 2 | 3 | 2 → 0 | 5 → 3 |
| no-products | 0 | 0 | 0 → 0 | 0 → 0 |

For 24 distinct cards with nonempty originals, calls fall from **48 to 24**. A missing filesystem image with a nonempty path still avoids the product reload and keeps the existing legacy-thumb fallback. Empty source fields intentionally retain WP1's generic request-cached lookup; this patch does not alter the approved shared thumb controller. Thus there is no promise of zero extra calls for cards with empty image fields.

**64/64 checks passed.** [Checks](RD-25-WP1_featured-image-payload_evidence_20261010/checks.json) and [runtime payload results](RD-25-WP1_featured-image-payload_evidence_20261010/runtime-payload.json).

- Runner/candidate syntax, no-write dry run, exact single-target output, byte-identical verified backup, APPLIED receipt, repeat/self-delete, exact rollback/reapply, drift refusal, missing-target refusal, newer-change rollback refusal, and invalid-candidate lint refusal before target write.
- Both application orders with the actual approved r3 runner; all eleven r3 outputs still match its approved candidates.
- Nine scenarios execute the actual uploaded Featured controller and actual approved r3 Thumb controller with local mock OpenCart services. Counts, every other caller payload field, final thumb commerce/state/fit data, existing legacy-image calls and read-only attribute queries are compared before/after. Second-module execution introduces no further request-cache fallback reads.

Local PHP: **8.3.30**. Production compatibility target: **8.0**; native PHP 8.0 was not available locally. The runner lints its candidate with the hosting PHP before target writes, then lints/readbacks the installed file and restores on failure. The image backend in the caller probes is mocked; no new UI/production-performance result is asserted. Existing 253 r3 UI checks are historical evidence for its unchanged geometry, not new F6 live tests.

## Runner contract and rollback

The runner requires the exact fresh featured source hash, one anchor and a real writable target; it refuses drift before target writes. Verified backups are placed in `_patch_backups/RD-25-WP1_featured-image-payload_20261010-<UTC>-<random>/`. It prints `cwd`, `time`, backup path, lint results, `changed_files=1`, `done=ok`, and self-deletes on successful apply/repeat/rollback. `--dry-run` keeps the runner and reports `target_writes=0`.

Logs: [dry run](RD-25-WP1_featured-image-payload_evidence_20261010/dry-run.txt), [apply](RD-25-WP1_featured-image-payload_evidence_20261010/apply.txt), [repeat](RD-25-WP1_featured-image-payload_evidence_20261010/repeat.txt), [rollback](RD-25-WP1_featured-image-payload_evidence_20261010/rollback.txt).

After independent review, upload the companion to `~/public_html`. If approved WP1 r3 is already installed, run:

```bash
cd ~/public_html && php RD-25-WP1_featured-image-payload_20261010.php
```

If WP1 r3 is not installed yet, upload both approved runners and run the normal combined deployment/cache cleanup:

```bash
cd ~/public_html && php RD-25-WP1_media-fit-catalog_20261009.php && php RD-25-WP1_featured-image-payload_20261010.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Companion rollback: re-upload the same companion and supply the exact printed backup directory. It verifies current after-hash and original backup before restoring; later source changes are refused.

```bash
cd ~/public_html && php RD-25-WP1_featured-image-payload_20261010.php --rollback=_patch_backups/<exact-F6-directory-printed-by-runner>
```

## Owner QA / limits

- [ ] Independently review the two-line source diff before deploying the companion.
- [ ] Confirm owner-run `changed_files=1`, `done=ok`; reload home and inspect Recommended cards, photos, names, prices, sale/preorder/rare/out states, and add-to-cart behavior. Check any configured vertical Featured module too.
- [ ] Confirm no new PHP/console errors. Local counts do not prove a measured production response-time improvement.

Geodude/Ditto source photo rectangles remain accepted. The original r3 file-date safety gate remains. WP2/WP3 still require owner WP1 deployment and QA. Nothing has been committed, pushed or deployed by this executor.
