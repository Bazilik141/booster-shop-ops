# Independent review — 3D-P-014 rev 2 (Codex implementation)

**Date:** 2026-08-08 · **Reviewer:** Claude (chat) · **Author under review:** Codex
**Verdict: Review OK; owner QA required.**

Reviewed against `handoffs/handoff_3D-P-014_sync-failure-visibility_20260803.md` rev 2 and the
bounded diff `HEAD → working tree` for `crm/apps-script/Code.gs` and
`dashboard/booster-dashboard.html`. Mechanical checks already proven by Codex are not repeated.

## Spec conformance — verified independently

| Rev 2 requirement | Result |
|---|---|
| Journal in the main CRM, hidden tab, 7 approved columns | ✅ `_Журнал_3DP_синхронізації`, created on first event, `hideSheet()`, header schema asserted on every append |
| Local write, no network call | ✅ `SpreadsheetApp` only |
| `sales.getParent()`, never `SpreadsheetApp.getActive()` | ✅ verified; throws if the parent is unavailable, and that throw is caught by the wrapper |
| All nine outcome branches journalled, including the three that logged nothing | ✅ `missing_order_id`→`error`, `no_3dp_sku`, `not_configured`, `invalid_qty`, `negative_stock`, `duplicate_key`, `created`, `updated`, `noop`, plus catch-all split into `skipped_schema` / `skipped_api_error` |
| Optional 4th `source` param, default `unknown`, wrapper keeps 3-arg form | ✅ `sync3dpSales_(sales, orderId, rowNumbers, source)`; wrapper forwards `arguments[3]`, so existing 3-arg callers and tests are unaffected |
| Call sites labelled | ✅ `apiAddSale_`, `apiUpdateSale_` |
| `sync_journal` in the existing CRM registry | ✅ added to `handleApiAction_`; token-gated by `doGet` like every other action; deliberately **absent** from `CACHEABLE_ACTIONS`, so the panel always reads fresh — correct for a diagnostic surface |
| Bounded read, newest-first | ✅ reuses `apiRecentLimit_`, reads only the tail range, reverses |
| Fail-open preserved | ✅ `crm3dpAppendJournal_` swallows after attempting; no new throw path reaches the order save |
| Row cap + trim | ✅ 1000, `deleteRows` only on overflow |
| Single bounded write per event | ✅ one `setValues` per event |
| `updateSaleStatus()` NOT touched (WP4 boundary) | ✅ byte-identical to `HEAD` |
| 3D-P Apps Script project untouched | ✅ |
| Dashboard panel reads the CRM API, escapes output, flags non-success | ✅ `refreshThreeDpSyncJournal()` via the CRM client, `threeDpEsc` on every field, `issue-tag` on non-`created`/`updated`/`noop`, explicit refresh button |

## Regression check

`node --test tests/3d-p-010-crm-packaging-pull.test.mjs` — 8/8 pass on the modified source. The
`crm3dpEnsureStock_` and `crm3dpLogSkip_` / `crm3dpLogWarning_` signature changes did not break the
existing 3D-P-010 contract tests.

Codex also marked the local `Code.gs` as **prepared, not deployed** in `crm/apps-script/SOURCE_STATE.md`
without being asked. That is exactly the OPS-CODEMIRROR discipline that was missing when three
consecutive attempts were planned against an assumed version. Noted as a positive.

## Findings — none blocking

**F1 — `detail` is canned text, not the actual reason.** `crm3dpJournalDetail_(outcome)` returns a
fixed sentence per outcome and discards the `reason` argument. Rev 2 asked for "a short
human-readable reason" constrained to exclude tokens, tokenised URLs and customer data; Codex chose
full suppression instead of redaction, which is a defensible security-first reading.

Cost: for `skipped_api_error` — the exact case rev 2 exists for — the `detail` column carries no
information beyond the `outcome` code. The raw reason still reaches `Logger.log`, which is the
low-retention surface this task was written to escape.

Not a blocker: the owner's primary need (that it failed, when, for which order and SKU) is met.
Recommended follow-up, not a return: sanitise and truncate the real reason — strip anything matching
the configured URL or token, cap at ~200 chars — so an API error is diagnosable from the journal
alone.

**F2 — `error` outcome has a hardcoded meaning.** `crm3dpJournalDetail_('error')` returns
"CRM order ID is missing." The `error` outcome is generic in the spec; any future branch reusing it
will be mislabelled. One-line fix whenever F1 is addressed.

**F3 — the create path can emit two rows for one sale line.** On create, `created` is journalled and
then, if `crm3dpEnsureStock_` reports `already_applied`, a `noop` row is added for the same line.
Both are true statements about different decisions, so this is accurate rather than wrong, but the
journal will read as noisier than "one row per line". Worth knowing before interpreting it; no change
recommended.

## Follow-up round — verified 2026-08-08 (second pass)

Codex implemented F1–F3. Re-reviewed independently; all three are closed.

**F1 closed, and the trust boundary is better than what was asked for.**
`crm3dpSanitizeJournalDetail_` collapses whitespace, then redacts in this order: full URLs
(`https?://\S+`) → `token`/`access_token`/`api_key`/`authorization` values → `Bearer <...>` →
emails → phone-shaped digit runs, then truncates to 240 chars. **URL redaction runs first**, so an
Apps Script network exception carrying the fetch URL — which is where the token would realistically
appear, since `crm3dpGet_` appends `&token=` to the query — is neutralised before any other rule
matters.

More importantly, `crm3dpFetchJson_` no longer copies remote text at all: it keeps the HTTP status
and validates the remote `code` against `/^[A-Z][A-Z0-9_]{1,80}$/`, falling back to `remote_error`.
Arbitrary remote strings therefore cannot enter the message in the first place. Sanitisation is now
the second line of defence rather than the only one.

`Logger.log` receives the same sanitised detail, so the raw string is no longer retained anywhere.

**F2 closed.** `error` now reads "3D-P synchronization could not start." — neutral, no hardcoded
single meaning.

**F3 closed, and slightly better than requested.** The create path emits exactly one row. If stock
handling had a problem, the outcome is escalated (`skipped_invalid_qty` / `warning_negative_stock`
via `adjustment.journal_outcome`) and the creation is recorded in `detail` instead of being split
into a second row. One line, one row, no lost information.

### Re-verification after the follow-up

- `node crm/apps-script/tests/3dp-sync-journal.test.mjs` — pass
- `node dashboard/tests/3dp-sync-journal-static.test.mjs` — pass
- `node --test tests/3d-p-010-crm-packaging-pull.test.mjs` — 8/8, no regression
- `node --check` on the modified source — pass
- `updateSaleStatus()` — still byte-identical to `HEAD`; the WP4 boundary holds

### Residual, non-blocking

1. Redaction is pattern-based, so a bare token value appearing with no `token=` prefix and no URL
   would not be caught. With the remote-text trust boundary now in place this has no realistic
   path, but redacting the literal `crm3dpConfig_().token` value would be exact rather than
   heuristic. Optional hardening, not required.
2. The phone pattern `\+?\d[\d\s().-]{7,}\d` will also match a long digit/date-shaped run. Harmless
   over-redaction; no reason to loosen it, just do not read a `[phone redacted]` marker as proof
   that a phone number was present.

**Verdict after follow-up: Review OK; approved for owner deploy and QA.**

## Not proven by this review

Live Apps Script behaviour, real order-save timing, first-run `insertSheet` cost inside the order
path, and dashboard rendering with the owner's stored credentials. All belong to owner QA.
