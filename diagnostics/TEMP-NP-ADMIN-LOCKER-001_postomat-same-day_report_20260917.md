# Codex Report — TEMP-NP-ADMIN-LOCKER-001: sender parcel-locker same-day DateTime

Date: 2026-09-17

Executor: Codex · model=Sol · effort=xhigh

Status: deployed to production; on 2026-09-19 the owner confirmed several subsequent orders completed without failures.

`TEMP-NP-ADMIN-LOCKER-001` remains intentionally non-canonical. Claude should create the canonical roadmap task and reconcile both committed temporary artifacts afterward.

## Outcome

Prepared a second, file-only OpenCart runner for the production failure found during the first patch's mandatory Nova Poshta API QA.

When the configured sender point is a parcel locker, the waybill form now uses the current server date both when the form opens and immediately before form validation/API submission. Ordinary sender branches and address senders retain the module's existing `today`/`tomorrow` behavior.

The runner does not change the already deployed combined branch/parcel-locker search or stable sender/recipient UUID behavior.

## Production evidence and confirmed root cause

Owner QA on 2026-09-17 established:

1. Sender parcel locker `49489` was selectable and persisted after the first patch.
2. With `DateTime=18.09.2026`, TTN creation failed for both an ordinary recipient branch and a recipient parcel locker with `Incorrect DateTime for sending from Postomat`.
3. The same form succeeded after changing the shipping date to `17.09.2026`.

This isolates the failure to the sender parcel locker's shipping date. Recipient point type and point UUID resolution are not the cause.

The module's existing default logic uses the next day when `shipping_pinta_nova_poshta_default_shipping_date=tomorrow`, then passes that value to Nova Poshta unchanged as `DateTime`. That behavior remains valid for ordinary branches but is rejected for this parcel-locker sender flow.

## Source boundary

The implementation baseline is the exact generated output of:

```text
TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_20260917.php
```

applied to the owner-supplied `backup-9.7.2026_20-35-02_boosters.tar.gz` fixture. The owner declined to provide a newer archive. The first patch was then deployed successfully enough to select/save sender parcel locker `49489` and reach the Nova Poshta API.

The follow-up runner therefore requires exact first-patch anchors and fails before backup/write if production has drifted.

## Implemented changes

The runner changes two production files:

```text
extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
extension/PintaNovaPoshtaCod/admin/model/module/warehouse.php
```

Behavior:

- adds an exact `getByRef()` warehouse lookup using the already persisted Nova Poshta point UUID;
- detects the Nova Poshta parcel-locker type UUID `f9316480-5f2d-425d-bc2c-ac7cd29decf0`;
- sets `shipping_date=date('d.m.Y')` when the sender is that point type;
- runs the normalization when creating default form data, so the visible field opens with today;
- runs it again on POST before validation, so stale tabs or manual future dates cannot send an invalid parcel-locker `DateTime`;
- falls back to the existing exact-name warehouse lookup only when a stable ref cannot resolve;
- preserves future-date behavior for an ordinary branch and leaves address senders unchanged.

No database write, schema change, Twig/CSS/JS change, checkout change, payment change, or order-status change is included. The new model query is read-only and limited to one exact primary-key ref.

## Artifact

```text
patches/TEMP-NP-ADMIN-LOCKER-001_postomat-same-day_20260917.php
SHA-256: 6D623C6CF7BB7972D61A22EAB05F2F02C0DDC1282F77DE2AE9467BA39EA152AE
```

## Validation

- runner `php -l`: passed;
- generated controller `php -l`: passed;
- generated warehouse model `php -l`: passed;
- PHP 8.0/host compatibility scan: passed for the runner and both generated files;
- generated fixture files match the independently prepared expected files byte-for-byte;
- focused behavior harness: 5/5 passed:
  - parcel-locker ref forces today;
  - ordinary branch ref preserves the configured date;
  - address sender preserves the configured date;
  - legacy exact-name parcel-locker fallback forces today;
  - unknown point preserves the configured date;
- repeat run: `already_applied=yes`, `done=ok`;
- simulated source drift: failed before backup/write with `anchor_count[controller_post_normalisation]=0;expected=1`;
- simulated post-write PHP lint failure: rollback attempted and both restored SHA-256 values matched the pre-run files;
- generated diff contains only the date normalization helper, its two call sites, the parcel-locker type constant, and the exact-ref model lookup;
- no live production write, deployment, commit, push, or additional Nova Poshta API request was performed by Codex.

## Fixture result

```text
patch=TEMP-NP-ADMIN-LOCKER-001_postomat-same-day_20260917
backup=.../_patch_backups/TEMP-NP-ADMIN-LOCKER-001_postomat-same-day_20260917-20260917-105936
php_lint[.../admin/controller/shipping/internet_document.php]=ok
php_lint[.../admin/model/module/warehouse.php]=ok
changed_files=2
database_changes=none
cache_clear=not_required
already_applied=no
done=ok
```

## Safety and rollback

The runner:

- checks both target files exist;
- requires every old anchor exactly once;
- rejects partial marker state;
- backs up both files before writing;
- lints both changed PHP files;
- restores both files if writing, lint, or post-check fails;
- reports `already_applied=yes` on a fully patched tree;
- self-deletes after success or already-applied detection;
- performs no database operation.

Rollback path printed at runtime:

```text
_patch_backups/TEMP-NP-ADMIN-LOCKER-001_postomat-same-day_20260917-YYYYMMDD-HHMMSS/
```

Restore the two files from their matching relative paths. No SQL rollback or template-cache cleanup is required.

## Owner run command

Upload the runner to `~/public_html`, then run:

```bash
cd ~/public_html || exit
php TEMP-NP-ADMIN-LOCKER-001_postomat-same-day_20260917.php
```

Expected success tail:

```text
changed_files=2
database_changes=none
cache_clear=not_required
already_applied=no
done=ok
```

Stop if `done=error`, an anchor count differs, any PHP lint fails, or `changed_files` is not `2`.

## Post-deploy QA

Owner confirmation on 2026-09-19:

- [x] The follow-up runner was deployed.
- [x] Several subsequent orders created Nova Poshta waybills without failures.
- [x] `Incorrect DateTime for sending from Postomat` did not recur in those orders.

The chat evidence did not separately enumerate the standard Tier 1 URL smoke or OpenCart-log review, so this report does not claim those checks independently.

The live recipient-parcel-locker route does not need another duplicate TTN merely to re-prove the same date rule: the owner already demonstrated that recipient type was irrelevant to the failure.

## Remaining gate

The reported Nova Poshta admin flow is production-accepted based on the owner's successful multi-order QA. Claude still needs to create the canonical roadmap task and reconcile the temporary ID; that bookkeeping does not block the verified runtime fix.
