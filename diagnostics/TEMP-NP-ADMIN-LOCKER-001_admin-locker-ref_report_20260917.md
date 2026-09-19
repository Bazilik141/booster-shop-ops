# Codex Report — TEMP-NP-ADMIN-LOCKER-001: Nova Poshta admin parcel-locker search and stable refs

Date: 2026-09-17

Executor: Codex · model=Sol · effort=xhigh

Status: deployed to production; owner confirmed the combined point search, sender parcel-locker selection, and subsequent waybill flow work without failures.

`TEMP-NP-ADMIN-LOCKER-001` is intentionally non-canonical. Claude should create the canonical roadmap task and reconcile the committed temporary artifacts afterward.

## Outcome

Prepared a file-only OpenCart runner that fixes both reported admin flows:

1. The waybill form now searches branches and parcel lockers through one `Відділення / поштомат` field for the recipient.
2. The sender settings search now permits Nova Poshta parcel lockers, including search by the numeric fragment `49489`.
3. The form preserves the selected Nova Poshta point UUID and TTN creation uses that UUID. Existing orders also reuse `shipping_custom_field.bs_np_v1.warehouse_ref`, so a changed display label after a reference refresh no longer causes the local `Відділення одержувача не знайдено` failure.

No sender point is hard-coded and the runner does not write the database. After deployment, the owner selected parcel locker `49489` in the module settings and saved it; OpenCart's existing setting workflow persisted its UUID.

Production QA later exposed a separate Nova Poshta rule: a sender parcel locker rejected a next-day `DateTime`. The follow-up runner `TEMP-NP-ADMIN-LOCKER-001_postomat-same-day_20260917.php` corrected that independently. On 2026-09-19 the owner confirmed that several subsequent orders completed without failures.

## Source and evidence boundary

Inspected source: owner-supplied cPanel archive `backup-9.7.2026_20-35-02_boosters.tar.gz`.

The owner explicitly declined to provide a newer archive. Therefore the runner uses exact anchors and fails before backup/write if any target block has drifted since 2026-09-07. Production deployment and owner QA later passed together with the separately documented same-day follow-up.

The backup database provides the relevant identity evidence without relying on the mutable address text:

- order `364` carries `bs_np_v1.type=poshtomat` and a canonical `warehouse_ref` for point `59187`;
- point `59187` exists under that ref in the backup reference table;
- sender candidate `49489` exists as a parcel locker in Dnipro under ref `cfb575d9-d99c-11ef-98f8-d4f5ef0df2b9`.

Nova Poshta's current business documentation states that documents and parcels up to 20 kg can be sent from a parcel locker and that the waybill can be created in the business cabinet or through the API: <https://novaposhta.ua/for-business/send/from-parcel-locker/>.

## Root cause

### Empty recipient autocomplete

`admin/view/template/shipping/pinta_nova_poshta/create_internet_document.twig` sent:

```js
type: (element.attr('name') === 'sender_address_warehouse') ? 'sender' : ''
```

`catalog/controller/shipping/pinta_nova_poshta.php::searchWarehouse()` only emitted options for `sender`, `warehouse`, or `poshtoma`. The empty recipient type therefore produced an empty result even when matching records existed.

### Sender lockers intentionally excluded

The same endpoint limited `sender` results to ordinary and cargo branch type UUIDs, and its final condition explicitly excluded the parcel-locker type `f9316480-5f2d-425d-bc2c-ac7cd29decf0`.

### Waybill creation used a mutable label as identity

`prepareSenderAddress()` and `prepareRecipientAddress()` called the admin warehouse model's exact-name lookup. After a directory refresh, any display-label change or local directory miss caused `Відділення ... не знайдено`, even though checkout had already saved the immutable point UUID in `bs_np_v1`.

## Implemented changes

The runner changes these production files:

```text
extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php
extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
extension/PintaNovaPoshtaCod/catalog/controller/shipping/pinta_nova_poshta.php
extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/create_internet_document.twig
extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/index.twig
```

Behavior:

- adds an admin-only `recipient` warehouse-search mode that returns branches and parcel lockers together;
- extends `sender` mode with the Nova Poshta parcel-locker type while retaining the existing ordinary/cargo branch types;
- preserves storefront `warehouse` vs `poshtoma` filtering unchanged;
- adds hidden sender/recipient point-ref fields, fills them on dropdown selection, and clears stale refs if the operator edits the city or point text;
- validates the ref as a UUID before using it;
- pre-fills an existing warehouse/parcel-locker order from `bs_np_v1.warehouse_ref` when available;
- retains exact-label lookup as a backward-compatible fallback for orders/settings without a stored ref;
- changes the relevant admin labels to `Відділення / поштомат`;
- adds no CSS, `!important`, `setTimeout`, fixed/absolute positioning, or magic-pixel override.

The `ServiceType` payload remains Nova Poshta's existing `Warehouse`/`Doors` contract. A separate `ParcelLocker` delivery type was not added.

## Files added to the repository

```text
patches/TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_20260917.php
diagnostics/TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_report_20260917.md
```

No production source mirror was edited directly. No database, checkout UI, payment, Checkbox, Hutko, order status, CRM, theme stylesheet, or shared design-system file is in scope.

## Fixture result

The runner was executed from a clean fixture copied from the 2026-09-07 backup, not from its source tree:

```text
patch=TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_20260917
backup=.../_patch_backups/TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_20260917-20260917-103856
php_lint[.../admin/controller/shipping/internet_document.php]=ok
php_lint[.../admin/controller/shipping/pinta_nova_poshta.php]=ok
php_lint[.../catalog/controller/shipping/pinta_nova_poshta.php]=ok
changed_files=5
database_changes=none
cache_clear=required
already_applied=no
done=ok
```

Generated fixture files matched the independently prepared expected files byte-for-byte, except that the original two Twig files intentionally retained their existing no-final-newline state.

## Validation

- runner `php -l`: passed;
- three resulting PHP files `php -l`: passed;
- two Twig inline scripts parsed successfully after Twig placeholders were rendered to inert test values;
- source-contract checks: 11/11 passed (combined recipient search, sender locker type, storefront split preservation, ref persistence/clearing, label, and scope signatures);
- order metadata helper tests: 5/5 passed (array metadata, JSON metadata, courier rejection, malformed UUID rejection, and missing metadata fallback);
- repeat fixture run: `already_applied=yes`, `done=ok`;
- anchor-drift fixture: failed before backup/write with `anchor_count[admin_delivery_type_label]=0;expected=1`;
- forced post-write PHP lint failure: rollback attempted and all five restored-file SHA-256 values matched the clean fixture;
- override-history scan found earlier TTN patches touching the admin controller; the exact-anchor implementation preserves their current backup state;
- no live Nova Poshta API request, production write, deployment, commit, or push was performed.

## Idempotency and safety

The runner:

- checks all five target files exist;
- requires every old anchor exactly once;
- refuses partially applied marker state;
- backs up all five files before writing;
- lints every changed PHP target;
- restores every written file if writing, lint, or post-check fails;
- reports `already_applied=yes` on a fully patched tree;
- self-deletes after success or already-applied detection;
- performs no DB operation.

## Rollback

Backup path printed at runtime:

```text
_patch_backups/TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_20260917-YYYYMMDD-HHMMSS/
```

Restore the five files from the same relative paths under that directory, then clear OpenCart caches/compiled templates. No SQL rollback is required.

## Run command (owner, only after Claude review and canonical task approval)

```bash
cd ~/public_html
php TEMP-NP-ADMIN-LOCKER-001_admin-locker-ref_20260917.php
# Then clear OpenCart caches and compiled templates through the normal admin Developer Settings control.
```

Expected success tail:

```text
changed_files=5
database_changes=none
cache_clear=required
already_applied=no
done=ok
```

Stop if `done=error`, an anchor count differs, any PHP lint fails, or the printed `changed_files` value is not `5`.

## Post-deploy QA checklist

- [ ] Clear OpenCart cache and compiled templates.
- [ ] Open Nova Poshta settings. Confirm the sender type and point label read `Відділення / поштомат`.
- [ ] With area Dnipropetrovsk and city Dnipro, enter `49489`; confirm the correct parcel locker appears, select it, save, reopen settings, and confirm the selection remains.
- [ ] Open order `364` and its waybill form. Confirm the recipient field is populated and saving no longer raises the local `Відділення одержувача не знайдено` error.
- [ ] Create one controlled test waybill from sender parcel locker `49489` with a parcel within Nova Poshta's locker limits. Confirm the Nova Poshta API accepts `SenderAddress`; do not call the task production-proven before this succeeds.
- [ ] In a safe test order/form, search recipient points by a branch number and a parcel-locker number; both must appear in the same dropdown.
- [ ] Confirm an ordinary sender branch remains searchable/selectable.
- [ ] Confirm ordinary recipient branch delivery still creates a waybill.
- [ ] Confirm address delivery still returns street suggestions and creates a waybill.
- [ ] Check the admin form at approximately 360 px, 768 px, and 1440 px widths. Hidden ref fields must not alter layout; the longer combined label may wrap but must remain readable and associated with its control.
- [ ] Run the standard Tier 1 smoke pages from `AGENTS.md`, including cart and checkout entry.
- [ ] Review the OpenCart error log for new Pinta/Nova Poshta errors after the controlled tests.

## Risks and open gates

- Risk: high because the change affects Nova Poshta TTN creation and sender identity.
- The code baseline is ten days older than the report date; exact anchors protect against silent overwrite but cannot prove the current production tree is unchanged.
- The reference table was not read directly after the owner's 2026-09-17 refresh, but owner QA confirmed live search and selection for `49489`.
- The Pinta payload was exercised against the owner's live Nova Poshta account. A next-day sender-postomat restriction required the separate same-day follow-up; after that deployment, several production orders completed without failures.
- Nova Poshta states a 20 kg limit for parcel-locker sending. The patch does not add a new weight-limit validator; the API remains authoritative.
- No staging environment exists. Deployment and final manual QA were completed by the owner.
