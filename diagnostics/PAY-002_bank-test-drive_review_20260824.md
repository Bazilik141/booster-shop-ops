# PAY-002 — bank test-drive script review (pre-run gate)

Date: 2026-08-24
Reviewer: Claude (chat). Author: Codex.

Script: `patches/PAY-002_bank-test-drive_diagnostic_20260824.php` (241 lines,
read in full)
Report: `diagnostics/PAY-002_bank-test-drive_report_20260824.md`
Handoff: `handoffs/handoff_PAY-002_bank-test-drive-script_20260824.md`

## Verdict

**Deploy OK; є неблокуючі зауваження.** No blocking defect. Every guard the
handoff required is present and correctly implemented, the write boundary holds,
and no secret can reach the output.

## Guards — verified line by line

| Guard | Where | Status |
|---|---|---|
| CLI only | `:112` | ✅ before any DB or network access |
| cURL present | `:113` | ✅ |
| Arguments validated before network | `:114` → `parseArguments()` `:64-86` | ✅ order/term/units/phone all whitelisted; unknown or duplicate flags rejected |
| `config.php` + DB constants | `:116-120` | ✅ prefix pattern-checked |
| `test_mode = 1` | `:133-134` | ✅ |
| Test hosts only | `:137` via `exactTestHost()` `:60-63` | ✅ `parse_url` + exact host + `https` scheme. Not a substring guess. `pumb.ua` cannot pass |
| Method not live | `:138-139` | ✅ **and correctly accepts an absent row as "off"** — the exact mistake that blocked the `PAY-004` patch is not repeated here |
| OAuth credentials non-empty | `:140-141` via `requiredCredential()` `:42-49` | ✅ names the empty key via `missing_setting` before failing |
| Order and products exist | `:143-167` | ✅ |
| Local amount reconciliation before sending | `:168-169`, printed at `:187-189` | ✅ three figures printed; a mismatch aborts before the bank sees it |
| `--live` required for create | `:217` | ✅ dry-run exits before `POST /sf-credits` and before any write |
| Repeat-create warning | `:208-216` | ✅ satisfies handoff criterion 10 |

## Secrets

Clean. `$username` / `$password` are used only inside `http_build_query()` at
`:192` and never printed. The bearer token is never echoed — `:197` prints the
literal `ok`. `oauth_transport_error` at `:196` prints `curl_error()`, which
cannot contain the credentials because they travel in the POST body, not the URL.
The only personal datum in the output is the test phone number inside
`payload_json`, which the handoff explicitly allows.

## Write boundary

One `INSERT`, prepared and bound, at `:229-232`. No `UPDATE`, no `DELETE`, no
`ALTER`, no settings write, no order or order-history write. The undo statement is
printed at `:238` and names exactly one row.

## Non-blocking — report as fixes or as owner awareness

| ID | Severity | Where | What | Note |
|---|---|---|---|---|
| N-1 | Medium — **check before running** | `:23` `function fail(...): never` | The `never` return type requires **PHP 8.1+**. On a PHP 8.0 CLI the script dies with a parse error before any guard runs. Harmless but confusing. The same construct is used in `patches/PAY-004_..._20260824.php:27`, so one check covers both | Owner runs `php -v` in `~/public_html` once. Nothing to fix if 8.1+ |
| N-2 | Low | `:201-206` | The fixture step prints `http` and `state` only, not the raw body. If the bank's fixture response has a different shape, `state` prints empty and the reason is invisible. The handoff asked for verbatim evidence | Add a raw-body line for the two fixtures. Would have to be re-requested from the executor; not worth a round on its own |
| N-3 | Medium — **owner awareness** | `:104-108`, `:228` | The local row stores a synthetic `store_order_id` (`PUMB-TEST-<order>-<hash>`) to dodge the table's unique `(store_order_id, is_test)` key. Justified and documented in the report. But `order_id` is the **real** one, and the module's `transactionByOrder($orderId, $isTest)` matches on `order_id` — so a later genuine `confirm()` in test mode for that same order would hit the idempotency guard and return the **diagnostic** `cap_id` instead of creating a new application | Use a disposable order for testing, or run the printed `DELETE` before testing the real flow on the same order |
| N-4 | Info | `:222` | A create failure (e.g. `400`) exits with status `0`. Cosmetic — the output is read by a human, and a `400` body is a successful outcome for this exercise | — |
| N-5 | Info | `:224`, `:231` | `POST /sf-credits` is documented to return `{"id": <cap_id>}` only, with no `state`. The stored row's `state` will therefore be an **empty string** until the first callback or poll fills it. Expected, not a defect | Owner should not read an empty `state` column as a failure |

## Confirmed safe — worth stating because it looks risky and is not

A bank **test** callback cannot move a real OpenCart order. Two independent
guards, both verified in the live controller:

- `handleCallback()` `:131` — `if (!$isTest && $orderId > 0) $this->applyOrderStatus(...)`. Test callbacks skip status entirely.
- `applyOrderStatus()` `:200` — returns early unless `payment_pumb_credit_status` is truthy, which it is not.

So the order used for the test keeps its current status throughout.

## Observation outside this scope — for the PAY-002 backlog

Live controller `:196`, the `ON DUPLICATE KEY UPDATE` branch of
`upsertTransaction()`:

```
`agreement_number`=NULLIF(VALUES(`agreement_number`),'')
```

On any later callback that carries no `guarantee_letter`, `VALUES(agreement_number)`
is `''`, so `NULLIF` writes `NULL` — **wiping a previously stored agreement
number**. `agreement_number` is the value a refund requires
(`plans/PAY-002_pumb-protocol-revision_20260727.md` §4.5), and it is captured only
from the guarantee letter. Compare the adjacent `guarantee_letter` column, which
is correctly protected with `COALESCE(VALUES(...), ...)`.

Not triggered by this diagnostic and not in its scope. Worth its own round before
any refund is attempted on a real order.

## Before running

1. `php -v` — confirm 8.1 or newer (see N-1).
2. Confirm the OAuth username and password are actually saved in the admin panel.
   In `backup-8.24.2026_10-35-09_boosters.tar.gz` (2026-08-24 10:35) both were
   empty, along with both IP allowlist fields. If the owner's save landed after
   that timestamp this is stale — the script will say so plainly and stop.
3. Pick a **disposable** order for `--order=` (see N-3).
4. Expected dry-run output: `mode=dry_run`, the three amount lines, `payload_json`,
   `oauth_http=200`, `oauth_token=ok`, both fixture lines, and
   `dry_run=complete_no_create_post_no_transaction_write`.

## Rollback

The script writes no file and no setting. On a successful create it prints
`rollback_delete_sql` naming exactly one row. Deleting that row does **not**
cancel the application at the bank — use the admin cancel action or let it expire.

Deleting the uploaded file is the entire rollback for the script itself. It does
not self-delete, by design: repeat runs are expected.

## Recommended status

`PAY-002` stays `In progress`. This produces evidence, not closure.
