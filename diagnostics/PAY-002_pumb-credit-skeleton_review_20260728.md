# Review report — PAY-002 pumb_credit skeleton

Date: 2026-07-28
Audience: Claude post-patch review
Scope: `patches/PAY-002_pumb-credit-skeleton_20260728.php` against
`handoffs/handoff_PAY-002_pumb-credit-skeleton_20260727.md` and
`diagnostics/PAY-002_pumb-credit-skeleton_report_20260728.md`

## 1. Verdict

**Review OK; owner QA required, with one mandatory pre-run DB check
(section 3) before deploying.**

The patch matches the handoff's scope closely: disabled-by-default
`extension/pumb_credit` skeleton, OAuth2 client with token caching, two
callback routes correctly separated by `is_test`, the `$error` property
declared on the admin controller (the exact bug that hit `mono_chast`),
the owner-approved 6→5 order-status consolidation, and the OC4
`extension_install`/`extension_path` registry rows (the other bug class that
hit `mono_chast`). Nothing in "Do not touch" was touched: no checkout/Twig
change, no NCRM file, no fiscalization/Merchant/sitemap file, no hardcoded
amount ceiling.

## 2. What was checked

- Full patch body read against handoff scope, "do not touch" list, and
  acceptance criteria — file by file, not just the diagnostic's summary.
- Preflight checks: `config.php`, live mono controller, 8 required DB tables,
  exact single occurrence of the mono status anchor, exactly-one-row check per
  old status name, zero-row check per new shared name (refuses an ambiguous
  merge) — all present before any write.
- Idempotency marker and its own self-check (marker + required files +
  registry row) — present, matches `AGENTS.md` patch conventions.
- `php -l` gate runs after files are written, before any DB mutation; on
  failure the catch block restores the mono controller from backup and
  deletes the newly written `pumb_credit` tree.
- Both callback routes (`callback`/`callbackTest`) reject on missing/invalid
  Basic auth or disallowed IP **before** touching the database — confirmed no
  transaction row is written on a rejected request.
- Default settings confirmed: `payment_pumb_credit_status='0'`,
  `test_mode='1'`, callback Basic credentials and IPs start empty — and
  `validBasicAuth()` returns `false` when the configured user/password is
  empty, so the live callback endpoints reject everything until the owner
  fills in real credentials. The method stays invisible on checkout
  regardless (`getMethods()` returns `[]` unconditionally, mirroring the
  `mono_chast` isolation pattern).
- Status-consolidation mutation logic (the actual `UPDATE`/`DELETE` on
  `order_status`, `order`, `order_history`) resolves every status ID by name
  at run time (`$statusIds[...]`, looked up via `SELECT ... WHERE name = ?`)
  — not from any hardcoded constant. This part is robust to drift between the
  backup snapshot and the live table.
- `createPayload()` matches the real PUMB `POST /sf-credits` schema from
  `plans/PAY-002_pumb-protocol-revision_20260727.md` §4.2 (`store_order_id`,
  `point_of_sale_code`, `partner_name`, `channel_type=INTERNET`,
  `flow.type=DIGITAL_SF`, `customer.phone`, `invoices[]`, `credit_request`).
- Mono-controller change is a single `str_replace` on a pre-verified,
  count-checked anchor (`SUCCESS/DONE` status key `'done'` → `'active'`) —
  cannot silently touch anything else in that file by construction.

## 3. Finding — rollback/evidence path assumes a hardcoded `order_status_id=20`

Two code paths do **not** follow the same "resolve by name, not by ID" rule
the rest of the script uses, and both run **before** the try block that
computes `$statusIds` by name:

- Evidence/backup gathering (lines ~328–329):
  `SELECT ... FROM order WHERE order_status_id=20` and the same for
  `order_history`. This is meant to capture every order currently sitting in
  "ПЧ mono — завершена" so the rollback can restore them.
- Rollback-script generation (line ~340):
  `if ((int)$row['order_status_id'] === 20) $rollback .= sqlInsert(...)` —
  decides which of the six status rows needs a full `INSERT` (to recreate the
  row this patch deletes) versus a plain rename-back `UPDATE`.

Both assume "завершена" is currently `order_status_id=20`, sourced from the
`backup-7.24.2026_17-02-32_boosters.tar.gz` snapshot cited in the Codex
report — not from a live query at write time (none was possible locally).
That snapshot is 4 days old relative to this patch. This repository has had
concurrent order-status-adjacent activity this week (NCRM-series work,
`ROADMAP_SOP.md` governance changes, and this project's own `ORD-PREORDER`
task is specifically scoped to add a *new* OC order status) — enough reason
not to treat the July 24 ID as still current without checking.

**Consequence if the assumption is wrong:** the actual consolidation
(`UPDATE`/`DELETE` on `order_status`) still runs correctly, because that part
resolves IDs by name. But the *backup evidence* would capture the wrong set
of orders (or none), and the *generated rollback.sql* would misidentify which
status row to fully restore — i.e., the safety net for this risky-zone DB
write would not be trustworthy, without the run itself failing loudly.

**Required before running on the live server** — either:

(a) Owner runs one read-only check first:
```sql
SELECT order_status_id, name FROM oc_order_status WHERE name LIKE 'ПЧ mono — %' ORDER BY order_status_id;
```
(adjust prefix to `ocp5_` per the confirmed `DB_PREFIX`) and confirms
"ПЧ mono — завершена" is still 20 before running the patch; if it is not,
stop and send this back to Codex rather than running it, **or**

(b) Codex ships a one-line follow-up: move the name→ID lookup for "ПЧ mono —
завершена" above the backup/evidence block (reusing the same lookup pattern
already used later in the file) and use that variable instead of the literal
`20` in both places. This is the preferred fix — it removes the dependency on
the July 24 snapshot entirely and makes the rollback path exactly as robust as
the mutation path already is.

Not a "return for changes" on its own — the disabled skeleton carries no live
risk to customers (`payment_pumb_credit_status=0`, checkout untouched) — but
it is a required gate before this specific patch is run against production
data, because it is a DB write in a risky zone and the rollback path is the
control that exists specifically for when something goes wrong.

## 4. Not independently verified this session

- No cPanel backup was mounted in this Claude session; the DB-prefix, table
  existence, and status-ID claims in the Codex report are trusted from
  Codex's own stated evidence, not re-verified against a raw backup file.
- Whether `backup-7.24.2026_17-02-32_boosters.tar.gz` is still the newest
  available backup as of 2026-07-28 was not confirmed.
- No deployment, OpenCart runtime, DB mutation, OAuth request, or callback
  HTTP request was executed by either Codex or Claude — consistent with the
  Codex report's own "Open risks" section.

## 3a. Pre-run DB check — verified 2026-07-28

Owner ran a byte-level check on the live server: `HEX(name)` per row 17–22
compared against `bin2hex()` of the exact six literal strings used by the
patch. All six matched exactly (case-insensitive hex only; MySQL `HEX()`
returns uppercase, PHP `bin2hex()` returns lowercase — no other difference).

Confirmed: `order_status_id=20` is "ПЧ mono — завершена" on the live server as
of 2026-07-28. The patch's hardcoded assumption (section 3) holds. **Cleared
to run.**

Note for the record: an earlier attempt to verify this via `md5()` comparison
over a plain `mysqli` fetch (without `$db->set_charset('utf8mb4')`, unlike the
patch itself) produced false `NOT_FOUND` results for all six rows — a
charset-transcoding artifact in that one-off diagnostic script, not a real
data problem. `HEX()`, computed server-side, is unaffected by connection
charset and was used to get the authoritative answer.

The underlying code fragility flagged in section 3 (hardcoded `20` in the
backup-evidence and rollback-branch code, instead of resolving by name like
the rest of the script) is still worth a follow-up fix for future patches
against this table, but is not a blocker for running this specific patch now
that the assumption is manually confirmed correct.

## 5. Owner QA (in order)

1. ~~Pre-run DB check~~ ✅ done 2026-07-28 — see section 3a. `order_status_id=20`
   confirmed as "ПЧ mono — завершена".
2. Confirm `backup-7.24.2026_17-02-32_boosters.tar.gz` is still the newest
   backup, or pull a fresh one if not.
3. Upload and run the patch per the Codex report's deploy command; confirm
   `done=ok` and no `ERROR:` output.
4. Admin → Extensions → Payments → PUMB settings opens and saves without an
   `$error` crash.
5. Confirm `payment_pumb_credit_status` stays `0` and PUMB does not appear on
   checkout.
6. Admin → Orders status dropdown shows exactly the five shared
   `Розстрочка — ...` labels, no `ПЧ mono — ...` labels remain.
7. If any real order was previously in "ПЧ mono — завершена", confirm it now
   shows "Розстрочка — оформлено" and its order history is intact.
8. ✅ done 2026-07-29 — test callback route (`...callbackTest`): missing/wrong
   Basic auth → 401, no row written; correct test credentials → 200, row
   written with `is_test=1`, `cap_id='TEST-CAP-001'` correctly persisted.
   Production route left untested positively (no production credentials
   configured yet — by design). Full detail:
   `diagnostics/PAY-002_confirm-idempotency-guard_review_20260729.md` §7.
9. Do not enter production OAuth credentials, enable the method, or send the
   callback URLs to the bank as final until this QA and the bank's own
   answers (source IPs, confirmed max amount) are in.
10. ✅ done 2026-07-29 — section 6's duplicate-create guard is fixed, reviewed
    (2 rounds), independently verified, and deployed
    (`patches/PAY-002_confirm-idempotency-guard_20260729.php`).

**All items not gated on bank credentials are complete.**

## 6. New finding (2026-07-28, post-bank-answer) — no guard against duplicate `POST /sf-credits`

Surfaced by the bank's round-2 answer to Q8 (`plans/PAY-002_pumb-protocol-revision_20260727.md`
§7b), not by static review alone: the bank has no unique constraint on
`store_order_id` and a repeated `POST /sf-credits` for the same order simply
creates a second, independent application with a new `cap_id`.

`confirm()` (lines 149–163) has no pre-check for an existing open transaction
for the `order_id` before calling the create endpoint — every invocation
creates a new application. A double-click, a page refresh after confirm, or a
browser resubmit will now provably (not just theoretically) create two live
credit applications for one order.

Not a blocker for the disabled skeleton (`payment_pumb_credit_status=0`,
`confirm()` is unreachable by real customers today), but this must be closed
before the method is enabled or `PAY-001-SMOKE` runs. Recommended fix: in
`confirm()`, call `transactionByOrder($orderId, $isTest)` first; if a
non-terminal transaction already exists for this order, return its
`cap_id`/`state` instead of creating a new one. Route this as a small Codex
follow-up against this same file before enabling.
