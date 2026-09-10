# CRM-011 R2 — Pass B finance contract report

Date: 2026-09-09

## Outcome

Pass B is implemented locally and is ready for the owner-controlled Apps Script
publication gate. It has not been published and has not written to any Sheet.
The dashboard consumer is updated locally in the same candidate.

## Source gate

The owner confirmed that the repository `Code.gs` was the latest code Codex had
supplied and authorised continuing from it. The workspace also contains the
complete V168 owner export (`Версія 168, 8 вер. 2026 р., 2005.csv`, 9,678
normalised lines). Before Pass B the repository mirror was that V168 baseline
plus the documented local CRM-011 follow-up delta. This is owner-confirmed
provenance, not an independent post-publication byte comparison.

## Files touched

```text
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/crm-011-r2-pass-b.test.mjs
crm/apps-script/tests/monthly-profit-preorders.test.mjs
dashboard/booster-dashboard.html
dashboard/tests/crm-011-r2-pass-a.test.mjs
diagnostics/CRM-011-R2_pass-B_finance-contract_report_20260909.md
```

## B1 — shadow declarations

The superseded CRM-011 declarations were removed first:

- `crmGetOrders_` v4 body;
- old `apiLtvReport_`;
- old `apiQualifiedClientsReport_`.

Duplicate scan after removal:

```text
Name                   Count
apiAddSale_                2
getDirectOrderExpense_     2
```

The three B1 targets now each occur once. The two remaining declarations are
the pre-existing exceptions that the handoff explicitly says not to touch.
This resolves the handoff's internal conflict between “no duplicates” and its
more specific exclusion without expanding scope.

The stale `monthly-profit-preorders.test.mjs` assertion was updated from the
deleted v4 order-cache body to the live v5 implementation. No production logic
was changed for that test.

## B2 — P&L contract and reconciliation

`finance_report.pnl` now returns:

```text
revenue
cogs
gross_profit
packaging
delivery
payment_fees
operating_expenses
writeoffs
net_profit
margin_pct
```

The sales columns are resolved by these exact headers, independent of position:

```text
Сума продажу
Управлінська собівартість продажу
Пакування
Еквайринг
Нова Пей
Комісія маркетплейсу
Доставка за рахунок магазину
Чистий прибуток
```

Therefore the local V expansion is explicitly:

```text
Чистий прибуток = Сума продажу
  − Управлінська собівартість продажу
  − Пакування
  − Еквайринг
  − Нова Пей
  − Комісія маркетплейсу
  − Доставка за рахунок магазину
```

Operating expenses resolve `Сума` and `Рахувати в операційці?`; write-offs
resolve `Управлінська сума списання`. A write-off with a missing or zero
valuation is excluded and counted in `data_quality`. Mystery Box and component
write-offs whose value is already included in an order's management COGS are
also counted and excluded from the separate write-off deduction to prevent a
double subtraction.

Every complete sales row is reconciled against `Чистий прибуток`. A difference
above 0.02 UAH stops the report with `CRM011_PNL_RECONCILIATION_FAILED`; it is
not silently absorbed into another line.

`totals` now includes `avg_order`, `net_profit`, and the net `margin_pct` used by
the five KPI tiles.

### Local three-order arithmetic fixture

```text
Revenue                 350
− COGS                  140
− Packaging              12
− Payment fees            9
− Delivery               14
− Operating expenses     30
− Valued write-offs      10
= Net profit            135
Net margin            38.57%
Average order         116.67
```

The three order-level checks each reconcile to a zero difference. A deliberate
1 UAH mismatch makes the test fail closed.

## B3 — New vs Repeat

Classification is now attached to each chronologically ordered identified
order, with the first order New and every later order Repeat. Two orders from
one client inside the same period therefore contribute once to each group.

The response returns, for both `new` and `repeat`:

```text
orders · revenue · avg_order · margin_pct
```

Orders without one stable phone/name identity remain in P&L and increment
`unidentified_orders`; they are not silently assigned to New.

## B4 — dashboard rendering

The flat P&L table was the presentation root cause: all rows had identical
weight, so deductions and subtotals were visually indistinguishable. The new
table uses dedicated, new `.finance-*` classes rather than overriding shared
dashboard selectors.

- KPI tiles are Revenue, Net profit, Net margin, Orders, and Average order.
- P&L separates deductions, Gross profit, and Net profit with subtotal borders.
- Net profit is coloured by sign.
- P&L and Cashflow show absolute and percentage comparison deltas when enabled.
- New vs Repeat is a metric-by-group table and surfaces unidentified orders.
- Assets remain a current balance without period comparison, per A8.

No chart, `!important`, absolute/fixed positioning, timer, or shared-theme
override was added in Pass B.

## Performance

The report performs one full header-resolved `Продажі` read and reuses that
model for P&L, New/Repeat, and cash-in. It similarly reuses one `Витрати` model
for P&L and cashflow. The old second full sales path and the separate formula
scan were removed. The finance cache contract was bumped from v1 to v2 so an
old cached payload cannot survive publication.

## Local verification

```text
crm/apps-script/tests/crm-011-r2-pass-b.test.mjs: 8/8 pass
dashboard/tests/crm-011-r2-pass-a.test.mjs: 7/7 pass
all in-scope CRM and dashboard test files: 34/34 pass
```

The Pass B test covers declaration counts, header reordering, same-period
first/second-order classification, full P&L arithmetic, fail-closed mismatch,
single sales-model reuse, missing cash-in values, expanded dashboard rendering,
and absence of an Assets comparison.

`dashboard/tests/dashboard-contract.test.mjs` retains the handoff-documented
pre-existing mismatch for `Кейс / контейнер для зберігання`. It is outside
CRM-011 R2 and was not modified.

## Owner publication evidence

The owner published the candidate and ran
`crm011PassBVerificationForOwner()` on 2026-09-09 at 21:12 Europe/Kyiv. The
bounded result returned `ok=true`.

Post-publication integrity was clean twice in the supplied evidence:

```text
helper:     clean=true · problems=[] · elapsed_ms=14623
standalone: clean=true · problems=[] · elapsed_ms=12976
```

The earlier pre-publication V168 evidence retained in the task chat was also
clean (`problems=[]`, `elapsed_ms=16254`). It is an older baseline, not a
same-minute pre-deploy run. The owner-controlled `Продажі` backup remains an
operational gate whose creation cannot be proven from code or the bounded
verification response.

### Live period and P&L

```text
Period                    2026-09-01 — 2026-09-30
Orders / units            25 / 98
Revenue                   20,505.00
COGS                      14,438.83
Gross profit               6,066.17
Packaging                     82.70
Delivery                      136.00
Payment fees                  251.34
Operating expenses          1,420.00
Valued write-offs              91.07
Net profit                  4,085.06
Net margin                    19.92%
Average order                820.20
```

The aggregate independently re-adds to the returned net profit:

```text
6,066.17 - 82.70 - 136.00 - 251.34 - 1,420.00 - 91.07 = 4,085.06
```

All five returned order samples (`MBZ-FOP-0007`, `OC-FOP-0355`,
`OC-FOP-0356`, `OC-FOP-0360`, `OC-FOP-0350`) had `difference=0`. The live
headers resolved to the intended sales, expense, and write-off fields recorded
above. No sales component, cash-in, operating-expense, or write-off value was
missing. Five order-linked write-offs were deliberately excluded from the
separate write-off deduction because their value was already represented in
order COGS.

### Live New vs Repeat

```text
Group      Orders   Revenue    Average order   Order contribution margin
New            17   16,380.00          963.53                       27.38%
Repeat          8    4,125.00          515.63                       26.96%
Unidentified    0
```

The New/Repeat margin is the margin on the classified orders before shared
operating expenses and separate write-offs. It therefore must not be compared
as if it were the final P&L net margin of 19.92%.

### Live segment distribution

```text
VIP          1    0.76%
Lost        10    7.63%
Inactive    52   39.69%
New         54   41.22%
Repeat      14   10.69%
Total      131   99.99% after display rounding
```

The largest segment is New at 41.22%. No segment is near the handoff's 80–90%
concentration warning, so threshold investigation is not triggered by this
distribution.

The remaining acceptance action is owner visual QA in the Finance tab,
including comparison mode. Code-side and live bounded-data Pass B gates are
complete.

## Rollback

No schema or data migration is part of Pass B. If the new deployment fails QA,
restore the immediately previous active Apps Script deployment shown in
deployment history; do not assume that its version number is V168. Restore the
saved pre-step `Продажі` copy only if a separate, verified data mutation
occurred. The Pass B read paths themselves do not write Sheet data.
