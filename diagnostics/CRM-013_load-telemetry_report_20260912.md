# CRM-013 WP-B — dashboard load telemetry

Date: 2026-09-12
Scope: measurement only; no request, cache-TTL, Sheet schema, or business-rule change.

## Contract

The server appends a bounded `load_telemetry` object only for:

`overview_bootstrap`, `overview_secondary`, `overview_assets`, `sku_list`,
`orders`, `ltv_report`, `finance_report`, and `monthly_summary`.

It contains only `elapsed_ms` and `cache_hit`. Cached data remains raw in
`CacheService`; telemetry is added after a hit, so the elapsed value describes
the current request rather than the original cache fill.

The dashboard records up to 60 local entries with active page, action, queue,
network, next-paint render time, server elapsed time, cache result, response
bytes, deduplication flag, and success state. Entries contain no token, query
parameters, customer rows, or API payload. The copy-out control is under the
existing **Налаштування** page; no sidebar item was added.

## Owner protocol after publication

Run three times, keeping the same browser and network:

1. Cold: `Ctrl+F5`, then open **Огляд → Склад → Замовлення → Клієнти → Фінанси**.
2. Warm: repeat that page order without hard refresh.
3. In **Налаштування**, copy the JSON telemetry before clearing it.

For each page/action report p50 and p95 for cold and warm network time, plus
the first-page time and the three slowest measurements. Treat a worse
`overview_bootstrap` cold median as a regression even if another page improves.

## Validation

- `Code.gs` and the temporary CRM-013 diagnostic runner compiled with
  `new Function` before the runner was removed locally.
- `dashboard-load-telemetry.test.mjs`: 2/2 passed after the persistent test was
  renamed out of the task-specific temporary name.
- Full local Apps Script/dashboard test pass before the rename: 62/62 passed.
- `git diff --check`: passed.

## Owner publication and integrity evidence

The owner published **Version 174, 2026-09-13 13:27**.

Integrity checks completed before and after publication:

| Check | Result | RRP coverage | Elapsed |
| --- | --- | --- | --- |
| Before | `clean: true`, no problems | 66 compared; 6 CRM RRP values missing | 12,345 ms |
| After | `clean: true`, no problems | 66 compared; 6 CRM RRP values missing | 11,531 ms |

No Sheet structure, formulas, or business data were written by WP-B.

## C1 live telemetry results

All listed calls succeeded. Times below are milliseconds. `server` is the
server-provided `load_telemetry.elapsed_ms`; `network` includes Apps Script and
transport overhead; `render` is the dashboard's next-paint approximation.

| Action | Samples | Observed server time | Observed network time | Cache observation | Finding |
| --- | ---: | --- | --- | --- | --- |
| `finance_report` | 3 | 14,653; 12,297; 10,557 | 18,157; 14,318; 13,612 | miss | Principal server bottleneck; server p50 12,297, worst 14,653. |
| `overview_secondary` | 4 | 4,702; 5,589; 7,770; 6,819 | 8,174; 9,247; 11,555; 8,988 | miss | Large response repeatedly recomputes; server median about 6,204. |
| `overview_bootstrap` | 4 | 5,959; 123; 3,493; 275 | 7,801; 3,002; 5,330; 2,987 | hit twice | Cache hit reduces server time from cold 3,493–5,959 to 123–275. |
| `overview_assets` | 4 | 25; 71; 10,992; 119 | 2,352; 2,484; 14,733; 3,165 | hit three times | Cache works when available; only one cold sample, so it is not enough to diagnose its 10,992 ms fill. |
| `sku_list` | 1 | 4,735 | 8,073 | miss | Large response; needs a follow-up sample only after its cache design changes. |
| `ltv_report` | 3 | 2,501; 2,186; 1,603 | 5,205; 4,872; 4,080 | miss | Reasonable compute time, but response is too large for a single cache value. |
| `orders` (large) | 3 | 3,987; 3,605; 4,205 | 6,184; 6,265; 9,102 | not cacheable | The `cache_hit: false` field means this action is not in the cacheable-action set, not that caching failed. |
| `orders` (small) | 3 | 107; 25; 25 | 2,565; 2,736 | not cacheable | The small response is fast on the server. |

Cold overview navigation sometimes incurred browser scheduling before the next
paint (for example, bootstrap 7,153/5,565 ms). The pre-Version-174 secondary
render value of 23,097 ms used the old timing path and had no server field, so
it is historical context rather than an equal baseline. Post-publication,
successful API calls normally painted in 0–88 ms; a few isolated values around
2–5 seconds occurred while opening a page and are browser scheduling
observations, not evidence of server work.

Non-instrumented actions were also exercised successfully: stock alerts,
consumables, order items, update/migration components, 3D-print journal, and
recent purchases. Their absent `server_elapsed_ms` is intentional because they
are outside the narrow C1 telemetry contract.

## Root cause confirmed by measured response sizes

Apps Script CacheService limits an individual cached value to **100 KB** and
does not guarantee retention until its requested expiration. The official API
documentation is: <https://developers.google.com/apps-script/reference/cache/cache>.

These successful responses exceed that per-key limit:

| Action | Measured response bytes | Consequence |
| --- | ---: | --- |
| `overview_secondary` | 156,645–156,652 | Cannot be stored as one CacheService value. |
| `sku_list` | 156,124 | Cannot be stored as one CacheService value. |
| `ltv_report` | 128,075–128,076 | Cannot be stored as one CacheService value. |

This explains their persistent `cache_hit: false` values. The current guarded
cache write prevents an outage, but it cannot make an oversized value cacheable.
Raising the TTL would not solve this limit.

The 15:51 results were collected more than an hour after the prior full pass,
so they are not a controlled warm-cache benchmark. They still prove the
instrumentation and cache-hit path work (`overview_bootstrap` and
`overview_assets` returned hits); they do not prove a precise cache lifetime.

## Conclusion and bounded next gate

WP-B/C1 is complete: telemetry is live, bounded, privacy-preserving, and the
post-publication integrity check is clean. No dashboard regression or failed
request was observed.

## C2 local candidate — not yet published

The owner authorized C2 after the findings above. The local candidate makes no
Sheet write and changes neither P&L formula nor inventory valuation rules.

1. `doGet` now serializes a cache entry normally when it is below 95 KB, or as
   gzip + Base64 when the normal JSON is larger. It reads either representation
   transparently. If even the compressed representation exceeds the safety
   limit, it bypasses cache without failing the API call. This makes the known
   128–157 KB JSON responses cacheable without truncating their dashboard data.
2. Telemetry now returns `cache_state`: `stored_plain`, `stored_gzip`, `hit`,
   `value_too_large`, `write_error`, `not_cacheable`, or `request_error`.
   The dashboard displays and records that state, so uncached `orders` is no
   longer indistinguishable from a failed cache write.
3. `apiFinanceReport_` no longer calls full `apiSkuList_` merely to calculate
   inventory assets. Its dedicated snapshot reads only active catalogue rows,
   direct stock projection, current FIFO/last-lot cost, and the reservation map
   already derived while the finance sales model is being built. The resulting
   formula remains: non-3D, non-Mystery active physical units × current cost;
   3D, Mystery, and missing-cost SKUs remain excluded exactly as before.

Local validation:

- Apps Script source parsed through Node stdin; `git diff --check` passed.
- Focused suite: 12/12 passed, including gzip round-trip below 95 KB, unchanged
  P&L reconciliation and missing-value behavior, finance asset call boundary,
  telemetry privacy/bounds, and current-cost rules.

Publication remains an owner gate. After publishing a new Web App version, run
one integrity check and a controlled two-pass test: first open **Огляд**, then
**Клієнти**, then **Фінанси**; immediately repeat the same order. Expected
second-pass evidence is `cache_state: hit` for the first two large responses
and a materially lower `finance_report.server_elapsed_ms`. Compare finance
totals and inventory-assets value between the pre-C2 and post-C2 display; any
difference is a stop condition, not a performance win.

## C2 publication result — Version 175, 2026-09-13 17:34

The owner published Version 175 and supplied both a cold pass and a browser
reload pass. Post-publication integrity was clean: no problems, 66 RRP rows
compared, 6 skipped CRM RRP values, elapsed 12,053 ms.

| Action | Cold evidence | Reload evidence | Result |
| --- | --- | --- | --- |
| `finance_report` | 12,918 ms server, `stored_plain` | 79 ms server, `hit` | Cache works; server work fell 99.4%. |
| `overview_assets` | 10,337 ms server, `stored_plain` | 139 ms server, `hit` | Cache works; server work fell 98.7%. |
| `overview_bootstrap` | 3,336 ms, `stored_plain` | 4,145 ms, `stored_plain` | Expected miss: its TTL is 90 seconds and the reload started about 104 seconds later. |
| `overview_secondary` | 5,211 ms, `stored_gzip` | 7,582 ms, `stored_gzip` | Unexpected miss despite no GET-side cache invalidation. |
| `ltv_report` | 1,848 ms, `stored_gzip` | 2,661 ms, `stored_gzip` | Unexpected miss despite the same parameters and a 180-second TTL. |

`orders` correctly reports `not_cacheable`; its two different response sizes
were not cache failures.

The comparison isolates the remaining issue to large gzip cache values:
small plain values hit, while gzip values are accepted by `put` but are absent
on the following request. CacheService does not promise retention, so a
successful `put` is not proof that a large value will be retrievable.

## C2b local candidate — small gzip shards, not yet published

The local candidate replaces each gzip cache value with a small manifest plus
deterministic 12 KB parts. The response is assembled only when every part is
present; a missing part becomes a safe cache miss and recomputes the response.
This keeps every stored value far below the documented per-key limit and adds
`stored_gzip_sharded` / `hit_sharded` telemetry states. Plain small values and
all API payloads remain unchanged.

Validation after C2b: Apps Script syntax parse and `git diff --check` passed;
the focused suite is 13/13, including a gzip shard write → read → decode round
trip, finance reconciliation, inventory-current-cost semantics, and telemetry
privacy/bounds.

Version 176 publication and the same two-pass test are still owner-gated.

## C2b publication result — Version 176, 2026-09-13 17:51

The owner published Version 176, completed a cold pass, reloaded the browser,
and immediately repeated the pass. Integrity remained clean: no problems,
66 RRP rows compared, 6 skipped CRM RRP values, elapsed 15,909 ms.

| Action | Cold server ms | Reload server ms | Reload cache state | Conclusion |
| --- | ---: | ---: | --- | --- |
| `overview_bootstrap` | 4,144 | 108 | `hit` | Plain cache works. |
| `overview_assets` | 10,878 | 22 | `hit` | Plain cache works. |
| `finance_report` | 9,642 | 121 | `hit` | Plain cache works. |
| `overview_secondary` | 6,091 | 5,793 | `stored_gzip_sharded` | Missed again; shards did not provide a reusable entry. |
| `ltv_report` | 2,972 | 2,872 | `stored_gzip_sharded` | Missed again; shards did not provide a reusable entry. |

This is the configured stop condition. The same Web App, cache version, and
short test interval produced hits for the small entries and misses for both
large entries even after they were split into 12 KB values. Further attempts to
change CacheService representation are not justified by the evidence.

No additional cache code should be published under CRM-013. A future, separately
scoped product decision is required:

- keep full catalogue/client payloads and accept their current cold-load cost;
- change the dashboard contract to fetch a small overview projection and lazy,
  paginated catalogue/client data only when the corresponding page is opened.

The second option is the durable performance route, but changes visible loading
behaviour and response contracts, so it requires owner approval before design
or implementation.

## Owner decision — 2026-09-13

The owner selected the first option: retain Version 176 and its current UI
contract. No lazy-loading/pagination redesign is authorized at this time.

CRM-013 is complete. Its accepted outcome is clean integrity, live bounded
telemetry, reliable cache acceleration for small high-value responses (including
Finance and Overview assets), and an evidence-backed limit recorded for the
two large read payloads. No further cache representation work remains.

## Post-review cleanup candidate — not yet published

Claude's review correctly identified residual cost in Version 176: every
oversized response still performed gzip compression and multiple cache writes,
despite the V176 evidence that those entries never returned as hits. The raw
samples are directionally consistent with that added work: `ltv_report` cold
server time rose from 1,848 ms in V175 to 2,972 ms in V176, and
`overview_secondary` from 5,211 ms to 6,091 ms. The sample is too small to
assign an exact percentage causally, but the unnecessary work is certain from
the code path and the zero cache-hit result.

The local cleanup candidate removes all executable gzip/base64/shard cache
logic. A response above 95 KB returns `cache_state: value_too_large` immediately
after serialization and makes no `cache.put` call. Small responses retain the
working plain-cache path unchanged.

Local validation: 12/12 focused tests passed, including a direct assertion that
an oversized response creates zero cache entries; Apps Script syntax parse and
`git diff --check` passed. Publication of this cleanup is owner-gated.

## Post-review cleanup publication evidence

The owner supplied the live smoke output after publication. It matches the
cleanup contract exactly:

| Action | Server ms | Cache state | Result |
| --- | ---: | --- | --- |
| `overview_secondary` | 7,057 | `value_too_large` | No gzip/shard cache write is attempted. |
| `ltv_report` | 2,583 | `value_too_large` | No gzip/shard cache write is attempted. |
| `overview_bootstrap` | 4,272 | `stored_plain` | Small cache path remains available. |
| `overview_assets` | 13,397 | `stored_plain` | Small cache path remains available. |
| `finance_report` | 11,028 | `stored_plain` | Small cache path remains available. |

The supplied post-publication integrity check was clean: no problems, 66 RRP
rows compared, 6 skipped CRM RRP values, elapsed 11,514 ms. This closes the
post-review cleanup gate; no further owner test or CRM-013 code change remains.

The owner should delete the already-executed temporary live `CRM-013` Apps
Script file; its local counterpart has been removed. The persistent telemetry
test remains because it validates production dashboard behaviour rather than
performing a one-time repair.
