# CRM-016 — purchases, order corrections, SKU rename, performance, Telegram

Date: 2026-09-23

## Scope and authority

The owner requested five work packages in one roadmap task. The new Notion row is
`CRM-016` (`3e46bf20-bdb4-8161-92fc-f06c468a6967`), mirrored in
`ROADMAP_FLOW`. Local code is a review candidate. The live CRM sheet was
changed only as recorded below. No Apps Script Web App version or OpenCart
order/SKU was changed by this run.
The current CRM mirror started from a byte-verified V185 owner export plus an
unpublished CRM-015 candidate; changes from that candidate remain intact.
Owner decisions: purchase grain is lot + SKU; `Дата створення` is the purchase
date; shipment date is a new field with historical blanks; order edits affect
CRM only; the SKU rename covers CRM history and links but excludes OpenCart.

## WP1: purchases tab — local candidate

- The dashboard has a `Закупки` page with active and archive sections, search,
  all lots, sortable columns, column visibility, and saved column order. The
  grain is one row per lot ID and SKU. A SKU in two lots stays two rows.
- The API reads the formula-derived management cost per unit and existing
  `Дата створення` as purchase date. The status split matches the current CRM
  status vocabulary. `На складі`, `На складі UA`, `Частково продано`, `Продано`,
  and `Скасовано` are archived; `Замовлено` and `В дорозі` are active.
- Dates read from the purchase sheet use the spreadsheet timezone (live
  metadata: `Europe/Moscow`) to avoid a previous-day display when the Apps
  Script timezone differs in winter.
- A guarded API writer supports a new `Дата відправки в Україну` field with
  strict date validation, lot uniqueness, stale-value rejection, and a check
  against the purchase date. It refuses to write while the header is absent.
- Before the edit, live metadata showed `Закупки` with 21 columns (A:U), header
  row 2. The owner reported the dashboard integrity check was OK. One native
  Sheets batch then appended column V, copied the existing date-column format,
  set V2 to `Дата відправки в Україну`, and left all historical values blank.
  Readback verified 22 columns and the exact new header. The required owner
  post-edit integrity check is pending.
- The existing orders table had an infinite recursion in the empty custom-sort
  path (`sortOrderRows_`); the local candidate returns a copy instead.
- Local browser QA found a same-name collision with the legacy accounting
  `purchaseTableHtml_` declared later in the single dashboard file. The new
  renderer is now `purchaseRegisterTableHtml_`; this was a real render failure
  in the first fixture pass.
- The mobile root layout used `body { display:flex }` with the mobile brandbar
  as a sibling of `.main`; the <=800 px rule hid the sidebar but left the flex
  direction horizontal. It left roughly half the 360 px width for content.
  The source media rule now switches to a column. A repository patch search
  found no prior `mobile-brandbar` selector patch; no new override or
  `!important` was added.

Bounded live status read on 2026-09-23: `Продано` 70,
`Частково продано` 30, `На складі` 2, `На складі UA` 59,
`Замовлено` 10, `В дорозі` 38, `Скасовано` 1. This is a
source check, not a published-dashboard UI test.

## WP2: order-line correction — local candidate

Recommended placement is inside the existing expandable order-items panel in
`Замовлення`, next to each line. The panel already loads individual lines on
demand. The local preview shows the source row, item, quantity, and eligibility
blockers before the guarded correction action.

Direct `deleteRow()` is unsafe. The live `Продажі` sheet has stable CRM row
numbers referenced by `3D_облік_замовлень`, component usage, and other ledgers;
deleting a row shifts those links. A cancellative ledger action or in-place
void with durable audit and formula-aware projections is required. The current
`apiOrderItems_` response also omits its internal source-row number. A correction
must invalidate caches, recalculate remaining line allocations/cost, and verify
stock and order totals. It must reject or explicitly reconcile orders linked to
3D, Mystery Box, components, fiscal receipts, or fulfillment. The local
candidate provides a preview and a button on each line in the expandable
`Замовлення` panel. It accepts only `Нове`, `В обробці`, `Відправлено`, or
`Передзамовлення`, the four statuses authorized by the owner. It rejects the
last line, mixed statuses or sources, non-reserving rows, any fiscal receipt, 3D/Mystery Box orders, linked
accounting/usage/writeoff/expense records, duplicate SKU in the same order,
manual allocation formulas, or later stock-consuming sales of that SKU, because those cases need a more
complex FIFO/ledger correction. A stale preview fingerprint is rejected.

The apply action writes a durable audit snapshot, clears the selected row in
place while preserving formulas, reallocates only manual order discount,
packaging, and shop-delivery totals to surviving lines, refreshes current
cost, clears both orders and general API caches, and runs `integrity_check`
before and after. It also reconciles warehouse
lot statuses for the removed SKU, including reopening `Продано` lots where
units return to stock; the general nightly lot updater does not reopen that
status. Audit captures the prior lot states, and error rollback restores them.
Live `Продажі!Q:S` are
formula-derived payment fees; the candidate neither reallocates nor overwrites
them. The audit is `PENDING` until post-checks pass; only `APPLIED` exclusions
affect future OpenCart sync. An interrupted pending action requires manual
reconciliation rather than a false idempotent success. On a caught error it
restores row and lot snapshots and clears the audit entry.
For OpenCart-source orders, the audit also records an excluded SKU so future
CRM sync skips that line while continuing to update the remaining items;
OpenCart itself stays unchanged. No real order was edited in this run.

The new audit sheet (`Коригування_Рядків_Замовлень`) is created only when the
first eligible correction is applied. Runtime and owner manual QA after Web
App publication remain required. Guarded cases return an explanation and are
not a general-purpose deletion capability.

## WP3: ACC-001-BPJP -> ACC-001-BPEN — live source edit

The canonical CRM workbook is `Booster Shop CRM — облік товарів`. An exact,
bounded scan found the old SKU at `Товари!A192`, `Закупки!E212`, and
`РРЦ!A192`. `Товари!C192` is the manually entered old name. No old SKU was
found in the scanned `Продажі`, `Списання`, `Міграції_Складу`, 3D accounting,
or component-target columns; `ACC-001-BPEN` has no catalogue collision.
The exact writable key/name plan is `Товари!A192` old SKU -> new SKU,
`Товари!C192` old name -> new name, and `Закупки!E212` old SKU -> new SKU.
`РРЦ!G192` is an audit note describing how the row was originally created;
retain that provenance and append the new SKU/date rather than rewriting past
events. The product short name
(`Товари!B192`), purchase name (`Закупки!F212`), stock projection
(`Склад!A192:B192`), RRP SKU/name (`РРЦ!A192:B192`, spilled from
`ARRAYFORMULA` in row 3), and SKU picker are formula-derived; writing those
outputs would destroy formulas. Post-write verification must check old-SKU
absence from key columns, new-SKU presence, 100-unit stock parity, and unchanged
formula cells; the old SKU may remain only in the explicit provenance note.

The owner reported the dashboard integrity precheck was OK. The same native
Sheets batch changed the three source cells and appended the new SKU/date to
`РРЦ!G192` while preserving its original creation note. Readback verified:
`Товари!A192/C192`, `Закупки!E212`, the formula-projected names in
`Товари!B192`, `Закупки!F212`, `РРЦ!A192:B192`, and `Склад!A192:B192` all
show the new SKU/name; stock remains 100 units; formula cells retain their
formulas. The local CRM import normalizer maps the unchanged OpenCart article
`ACC-001-BPJP` to `ACC-001-BPEN` so future order imports continue to use the
new CRM SKU. The full dashboard integrity postcheck is still pending. Rollback is
the reverse of the three source-cell edits plus restoration of the original
`РРЦ!G192` note; remove the new V column only if no shipment dates were entered
after this batch. OpenCart SKU is intentionally excluded from the source edit.

## WP4: performance inspection and ranked options

1. **Orders API (low risk):** `crmGetOrders_` builds the complete client model
   and calls `model.clients.filter(...)` for each first order. Build a `key ->
   client` map once; this removes repeated full-list scans. The dashboard also
   requests active and completed orders separately; combine into one bounded
   server response or reuse the already-built model per request. Check totals,
   sorting, and active/completed counts on the same fixture before deployment.
2. **Order detail (medium risk):** `apiOrderItems_` reads the full `Продажі`
   table and computes marketing ledger maps on each expansion. Serve a bounded
   order index or short-lived per-order snapshot, invalidated by sale writes;
   benchmark opening 10 orders and verify cost/marketing reconciliation.
3. **Sheets reads (medium risk):** `apiRecentTable_` reads every used column for
   every row even when the caller needs a few fields. Project only necessary
   columns or reuse one request-scoped read; avoid column-by-column round
   trips. Verify exact output parity for purchase, sale, and finance actions.
4. **Finance (higher risk):** CRM-013 owner telemetry measured `finance_report`
   cold server times 10.6–14.7 s, `overview_secondary` 4.7–7.8 s, and large
   `orders` 3.6–4.2 s. Profile finance substeps with existing privacy-bounded
   telemetry, then remove repeated cross-sheet passes one at a time. Do not
   assume an oversized CacheService payload can be cached as one value; the
   recorded 128–157 KB responses exceed its per-key limit. Prior owner choice
   retained full catalogue/client payloads, so pagination needs a new owner
   decision, not an incidental rewrite.
5. **Dashboard rendering (low risk):** avoid reconstructing the full orders
   table on every expanded-row toggle and filter keystroke; retain keyed panels
   or render only the affected row after data has loaded. Verify 3 viewport
   widths and long SKU/name content in manual QA.

The recursion defect above was fixed locally. The other options are proposals;
there was no new live benchmark in this run.

## WP5: Telegram command pipeline — source diagnosis

The Telegram webhook router is in main `crm/apps-script/Code.gs`: `doPost`
recognizes Telegram `message` and `callback_query`; `/orders` and callback
`orders_list` reach `tgCommandOrders_`. The separate automation export
`Версія 4, 9 вер. 2026 р., 1736.txt` implements the token-gated Alerts API
and has no Telegram command router. Thus, changing that Alerts API would not
address this symptom.

Static failure points, in diagnostic order:

1. Telegram webhook may point to an old/wrong Web App deployment, or delivery
   may be failing before `doPost`. Check `getWebhookInfo` status and recent
   Apps Script executions for a single test command.
2. `tgIsAllowedChat_` silently discards text commands if
   `TELEGRAM_ALLOWED_CHAT_ID` is missing or differs. The log records that
   branch. Check the property existence and the incoming chat ID without
   exposing token values.
3. `tgCommandOrders_` calls `crmGetOrders_('active', 200, ...)
   before sending a response. A runtime exception/timeout or a failed
   `sendMessage`/`editMessageText` call can look like no reaction. Check the
   execution error and the logged Telegram HTTP status. The callback is
   acknowledged before the slow CRM read, but the menu message is not.

The owner reported on 2026-09-23 that pressing Telegram commands produces no
new execution in the main CRM Apps Script project. Assuming that the correct
project and execution filters were checked, this shifts the leading diagnosis
upstream of `doPost`: missing/wrong webhook URL, inaccessible/stale deployment,
or Telegram delivery failure. This is an inference from the observation, not
a verified webhook state. The next minimal evidence is a redacted
`getWebhookInfo` result: URL identity, `pending_update_count`,
`last_error_message`, and `allowed_updates`. Do not paste `TELEGRAM_BOT_TOKEN`
or full private order/customer logs into the diagnostic. Official Bot API
field contract: <https://core.telegram.org/bots/api#getwebhookinfo>.

The owner then supplied `getWebhookInfo`: `url=""`,
`pending_update_count=7`, no delivery error, and `allowed_updates` containing
`message`, `edited_message`, `channel_post`, and `edited_channel_post`, but not
`callback_query`. The empty URL confirms that no webhook is set for this bot;
the seven updates await delivery. This explains the absence of new Apps Script
executions and command replies. It does not establish why the webhook was
removed or whether another service is polling. A bounded repository search
found no `getUpdates`, `deleteWebhook`, or `setWebhook` client in the CRM,
dashboard, 3D-P, or scripts trees; it cannot rule out an external poller. The
main CRM `doPost` handles
both `message` and `callback_query` before checking the CRM API token. Restore
the webhook to the current main-CRM `/exec` URL with `callback_query` included,
preserve the seven pending updates, and then inspect `getWebhookInfo` and one
fresh `/orders` execution. The owner controls this production integration.

Owner-run PowerShell repair (prompts for the bot token without putting it in
shell history; preserves pending updates and the prior update types):

```powershell
$webhookUrl = 'https://script.google.com/macros/s/AKfycbz2WIFlW7A-ta7HtewK-0wsklekB-HDIgCx3CF1JGfmPaRvs2UgI0qktcfhvKzPYDbX-A/exec'
$botSecret = Read-Host 'Telegram bot token' -AsSecureString
$botTokenValue = ([pscredential]::new('bot', $botSecret)).GetNetworkCredential().Password
try {
  $body = @{ url=$webhookUrl; allowed_updates=@('message','edited_message','channel_post','edited_channel_post','callback_query'); drop_pending_updates=$false } | ConvertTo-Json -Compress
  $result = Invoke-RestMethod -Method Post -Uri ("https://api.telegram.org/bot{0}/setWebhook" -f $botTokenValue) -ContentType 'application/json' -Body $body
  $result | Select-Object ok, description
  $info = Invoke-RestMethod -Method Get -Uri ("https://api.telegram.org/bot{0}/getWebhookInfo" -f $botTokenValue)
  $info.result | Select-Object url, pending_update_count, allowed_updates, last_error_message
} catch {
  Write-Host 'Telegram API request failed. Check the HTTP status and token locally; do not paste the token or full request URL.'
} finally {
  Remove-Variable botTokenValue, botSecret -ErrorAction SilentlyContinue
}
```

After a successful `setWebhook`, send a fresh `/orders` to the bot and verify
that the main CRM Apps Script has a new execution. If Telegram delivery fails,
the next `getWebhookInfo` `last_error_message` identifies the remaining
deployment/access issue. Do not drop the seven pending updates without a
separate owner decision.

## Local verification and remaining gates

- Focused purchase-register and order-line guard/positive-path fixture tests
  passed via direct Node execution. The order fixture covers allowed status,
  rejected statuses/links/formulas/later sales, old-to-new SKU import alias,
  return of a fully sold lot to warehouse status, and pending versus applied
  audit behavior;
  Existing order-items and preorder/stock regression files also passed.
  Apps Script source and dashboard inline scripts parsed; scoped
  `git diff --check` passed. `node --test` itself hit Windows sandbox
  `spawn EPERM`, so the same test file was run directly and passed.
- The in-app browser refused the `file://` URL, so a localhost-only fixture was
  used. With three representative purchase rows, including a long name/SKU,
  the active/archive split and table rendering were inspected at 1280, 768,
  and 360 px. The 360 px pass found and verified the mobile flex fix. Direct
  fixture interaction verified ascending/descending sort, hide column,
  move column, search, and persisted preferences after reload. This is local
  browser QA with fixture data, not a live CRM API or production UI test.
- The owner supplied the post-edit `integrity_check` result: `clean=true`,
  `problems=[]`, 67 3D-P RRP rows compared, six skipped for missing CRM RRP,
  `elapsed_ms=86150`. The structural/catalogue integrity gate passed.
- Required before release: publish a fresh CRM Web App version; open the canonical dashboard;
  verify both purchase sections, column controls, shipment-date write, order
  totals, and a post-change integrity check. The owner controls deployment.

## 2026-09-23 follow-up: finance, alerts, duplicate blocks, 3D and latency

The owner supplied a clean post-edit integrity payload (`clean=true`,
`problems=[]`, 67 RRP comparisons, six missing-CRM-RRP skips, 86.150 s).
This closes the CRM-016 catalogue/header integrity gate. The purchase screenshot
shows `unknown action: purchase_register`: the local dashboard calls an action
that is present only in the unpublished main CRM candidate. The dashboard now
explains that publication is required instead of displaying the raw API error.

The owner-supplied CRM-013 telemetry contains 19 recent GETs. The slowest
ordinary loads were `finance_report` 19.917 s network / 16.436 s server,
`overview_assets` 19.095 / 15.637 s, `sku_list` 11.023 / 7.156 s, and one
`orders` call 8.036 / 5.622 s. `integrity_check` took 88.773 s network and is
an explicit deep audit, not the routine page load. `sku_list` (177,993 B),
`overview_secondary` (178,523 B), and `ltv_report` (137,055 B) report
`value_too_large`, so their single-value cache writes do not help repeat
loads. The `finance_report` `render_ms=604079` is time until the next animation
frame, not a CPU render profile; browser background throttling is the likely
cause. The local telemetry now records this value as unavailable when page
visibility changes while waiting for that frame.

Local changes in this follow-up:

- `loadAccounting` fetches SKU and packaging choices concurrently. A generation
  guard prevents an older in-flight load from moving a second copy of migration
  and records blocks into `updatesContent`. Returning to an already populated
  Updates page reuses those blocks. Errors now replace the spinner there.
- 3D-P GET requests now have a 20 s timeout and one bounded retry. Failure
  replaces both calculator and card spinners with an error and retry button.
  This removes an indefinite UI wait but does not establish why the separate
  3D-P Web App stalled in the owner's session; its URL, deployment, Apps Script
  execution, and response time still need live evidence.
- Finance layout stacks the compact assets card above a collapsed ZenMarket
  statement in the left column. `finance_report` carries the latest 15
  `ZenMarket_Рахунок` movements (date, signed JPY amount, JPY currency, balance
  after, note) from a bounded tail read. The response uses a distinct cache
  key from the older response shape.
- The Alerts API candidate adds one locked batch action. It validates every
  current alert ID before writing the control sheet once; the dashboard has
  row checkboxes, Select All, a count, and one confirmation for dismissal.
  The separate Alerts Web App must also be published; its earlier granular
  alert hotfix is still part of that local candidate.
- `crmGetOrders_` builds one client-key map per request instead of scanning
  all clients for each order. It retains the prior first-match behavior.
- The local dashboard now exposes a masked CRM API token field in Settings.
  The owner's Codex browser did not reopen the JavaScript prompt when
  “Оновити дані” was clicked. Saving the field stores the token only in that
  browser's local storage and reloads Overview. The missing-token banner links
  directly to this control; no token was entered by the agent.

Local checks: both Apps Script sources and dashboard inline JS parsed; focused
alert-batch, order-lookup, purchase-register, order-line, and ZenMarket-account
fixtures passed; `git diff --check` passed. The local browser showed the
collapsed statement and no horizontal page overflow at 1280, 768, and 360 px;
the disclosure expanded at 360 px. That browser had no CRM token, so it did
not verify populated finance/alert tables or live 3D behavior. No Web App
publication or production latency benchmark occurred in this follow-up.

The owner then entered the CRM API token directly in the Codex browser using
the new Settings field. The existing live Overview and Finance actions loaded
without an authentication error. On populated Finance, the assets card stayed
compact (259/242/312 px at 1280/768/360 px), the collapsed statement stayed
below it, and the page had no horizontal overflow. The published API did not
return `statement`, so the disclosure correctly stated that API publication is
needed. Navigating rapidly from Accounting to Updates produced exactly one
migration block and one records block after completion. A follow-up browser
pass found a blank Updates area during the request; the local candidate was
adjusted to retain the spinner until the latest load is ready. A second rapid
Accounting-to-Updates pass showed one spinner while loading and exactly one
migration block plus one records block afterward. The 3D tab had
no separate 3D-P credentials in this browser, and the UI correctly replaced
both spinners with a configuration message. These checks are read-only browser
QA of live legacy actions plus local rendering, not new API or 3D runtime QA.

Next performance work should profile the 16.4 s finance server path and
15.6 s assets path by substep, then remove repeated sheet scans with parity
checks. A separate response-shaping decision is needed for the three payloads
above the single-value cache limit. A fresh telemetry sample after publication
can measure whether concurrent form requests and the client lookup improve
perceived and server latency respectively.
