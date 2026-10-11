# Codex Report — RD-25 WP1: cold metadata cache warnings

Date: 2026-10-11. Patch retains its initial preparation date, 20261010.
Status: **Repair prepared and locally verified; independent review and owner deployment/QA pending.**

## Incident and diagnosis

The owner supplied storefront warning text and a screenshot on 2026-10-10: `catalog/model/tool/image.php:308` reads absent `DIR_CACHE/rd25-media-v3-<hash>.json` files, with two warnings per source image. The owner reports clean subsequent page loads after refresh.

The exact approved WP1 r3 candidate has `@file_get_contents($cache_file)` at line 308 in `rd25ReadMedia()`. `mediaInfo()` invokes it once before the lock and once after acquiring the lock. On a cold first request, absence is normal, so both reads raise a missing-file warning before successful classification writes the JSON. Warm requests then read a real file. The unconditional cache read is the defect in the executor's implementation.

The previous regression suite used ordinary PHP CLI error handling and missed storefront custom-handler behavior. A user-defined PHP handler can receive warning callbacks despite the suppression mask; [PHP manual, set_error_handler](https://www.php.net/manual/en/function.set-error-handler.php). The actual deployed site's handler source was not supplied; its behavior is inferred from the visible warning under the known r3 `@` read. The new local regression deliberately models a handler that prints callbacks regardless of the suppression mask, plus one that throws exceptions.

**Reproduction:** three actual GD PNG/JPEG/WebP fixture sources → **6 warning callbacks and warning HTML** under r3, versus **0 callbacks and empty warning output** with the repair. Classification metadata, fit output pixels/dimensions and fit cache keys are identical between the original and repaired model. [Original probe](RD-25-WP1_media-cache-miss_evidence_20261010/probe-source-printing.json), [fixed probe](RD-25-WP1_media-cache-miss_evidence_20261010/probe-expected-printing.json), [owner hash/source matches](RD-25-WP1_media-cache-miss_evidence_20261010/owner-warning-path-matches.json).

Sequential page warming can temporarily populate this metadata, but it does not correct the first-read behavior for newly added/changed sources or deleted metadata. No production crawl was performed and no global error-display setting was changed.

## Implementation and source gate

Only `catalog/model/tool/image.php::rd25ReadMedia()` changes:

1. Missing, non-file or unreadable JSON returns an ordinary cache miss (`[]`) before any read.
2. A temporary handler contains only `E_WARNING` during the single `file_get_contents()` call, covering file disappearance between the guard and read.
3. `finally` restores the prior site's handler even when a fixture read throws an exception. A failed read continues through the existing invalid-metadata/cache-miss path.

Metadata validation, regeneration locks, atomic JSON publication, classification v3 (F1/F5), source-alpha margins, `resize()`/`resizeFit()`, filenames, fit pixels, CSS/header tokens, product/lightbox/JSON-LD URLs, controllers and F6 are unchanged. No source asset or DB write is introduced. **No cache deletion or cleanup is needed**; all existing metadata and fit images are retained.

The before-source is reconstructed byte for byte from the approved r3 candidate, not claimed to be a new server export. The warning file/line/version matches that candidate. The runner validates its full hash on the hosting machine before any target write; if the model has subsequently changed, it refuses and needs a fresh one-file export. This bound repair requires installed WP1 r3. Original approved r3 and F6 delivery artifacts remain unchanged.

## Files and hash pins

Patch: `patches/RD-25-WP1_media-cache-miss_20261010.php` (10,790 bytes).
Report: `diagnostics/RD-25-WP1_media-cache-miss_report_20261011.md`.
Single production target: `catalog/model/tool/image.php`.

| Artifact | SHA256 |
|---|---|
| Installed-r3 model expected before | `a42869efe054311be56f1c37e56969e8e1318331de55b6ca3e8aa4b83b0a4ddd` |
| Repaired model candidate | `9d6aaec56eaa44be072e67957e43b7365ca8f115283988d0ab7a3227db885285` |
| Repair runner | `085cbb193b7c877f03768278acef97dd700d66aea042bee2b584e0997bbc32c3` |

[Readable source diff](RD-25-WP1_media-cache-miss_evidence_20261010/source.diff), [manifest](RD-25-WP1_media-cache-miss_evidence_20261010/manifest.json). Removing only the replacement read block restores every other source byte exactly.

## Local verification

Runtime: **PHP 8.3.30 + GD**. Production target remains **PHP 8.0**; native PHP 8.0 was unavailable locally. The runner gates the candidate with hosting `php -l` before the target write and verifies lint/readback after writing, with restoration on failure.

- **39/39 repair checks:** syntax, no-write dry run, exact single-target apply, verified backup, idempotency/self-delete, exact rollback/reapply, newer-source rollback refusal, drift/missing-target refusal, invalid candidate lint failure before target write; cold/warm reads, preserved pixel/key metadata, deletion after guard, unreadability guard, directory path, corrupt/stale metadata regeneration, changed source stat, exception propagation/handler restoration, unchanged reporting mask, unrelated warnings still reaching the site handler, and both custom-handler modes. F6 composes in both orders with exact outputs.
- **79/79 existing WP1 functional checks** pass on a separate repaired fixture, including PNG/JPEG/WebP and warm-cache behavior.
- **All 286 archive asset classifications match r3** for version, dimensions, type, class, corner alpha/RGB, near-white result and reason. Counts remain **164 cut / 122 photo**.
- Warm calls require **zero GD decodes**; absent metadata regenerates without changing retained fit pixels or keys.

[Repair checks](RD-25-WP1_media-cache-miss_evidence_20261010/checks.json), [full WP1 functional checks](RD-25-WP1_media-cache-miss_evidence_20261010/functional-checks.json), [current regression classification](RD-25-WP1_media-cache-miss_evidence_20261010/classification.json), [throwing-handler probe](RD-25-WP1_media-cache-miss_evidence_20261010/probe-expected-throwing.json). The unreadable-file condition is an explicit fixture hook because Windows filesystem permissions differ from the host; deletion-after-check is also deliberately injected. No production warning-free first visit or production latency improvement is claimed yet. UI geometry was not edited or re-tested in a browser for this PHP read repair.

The first attempted elevated local test launch was not executed because the automatic approval service hit a usage limit. After the owner resumed on 2026-10-11, the same authorized local checks were approved and completed. No approval mechanism was bypassed.

## Runner contract, deployment and rollback

The runner enforces the exact before-hash, one anchor, target existence/writability and symlink refusal. It creates and verifies `_patch_backups/RD-25-WP1_media-cache-miss_20261010-<UTC>-<random>/`, lints the candidate, writes atomically, then lints/readbacks and prints `changed_files=1`, `done=ok`, `self_delete=ok`. Repeat reports `already_applied=yes` without rewriting content. The `--dry-run` keeps the runner and reports `target_writes=0`.

After independent review, upload only this repair to `~/public_html` and run:

```bash
cd ~/public_html && php RD-25-WP1_media-cache-miss_20261010.php
```

Logs: [dry run](RD-25-WP1_media-cache-miss_evidence_20261010/dry-run.txt), [apply](RD-25-WP1_media-cache-miss_evidence_20261010/apply.txt), [repeat](RD-25-WP1_media-cache-miss_evidence_20261010/repeat.txt), [rollback](RD-25-WP1_media-cache-miss_evidence_20261010/rollback.txt).

Rollback: re-upload this repair and use the exact printed directory. Later model changes are refused.

```bash
cd ~/public_html && php RD-25-WP1_media-cache-miss_20261010.php --rollback=_patch_backups/<exact-directory-printed-by-repair>
```

To roll back WP1 r3 as well, roll back this repair first so the original r3 model hash is restored. Do not rerun/re-upload the original WP1 r3 runner as a repair: its source-content gate correctly refuses a model containing this newer fix. F6 is a separate controller target and remains independent.

## Owner QA / remaining limits

- [ ] Independently review the narrow cache-read diff; confirm owner-run `changed_files=1`, `done=ok`.
- [ ] Open home/category/search/featured pages; photos and cards render normally and no `rd25-media-v3` warning text appears. A warm-only visit is not proof of the cold path.
- [ ] Confirm one cold source on the hosting system (a newly added source or one deliberately removed metadata JSON, with image-cache files retained). First and subsequent requests must be clean; metadata must regenerate. No broad cache clear or site crawl is needed.
- [ ] Check OpenCart error log and normal listing actions. If the runner reports a source-hash mismatch, preserve the model and send its current export for adaptation.

No commit, push, deployment, live DB write or roadmap/status mutation was performed. WP2/WP3 remain gated by owner WP1 QA.
