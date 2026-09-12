# Codex Handoff — CRM-013: BR-* catalogue gap, then load telemetry

Date: 2026-09-12 | Parent: CRM-012
Executor: Codex — same executor, no mid-round swap. model=Terra · effort=high.
Both work packages are diagnostic. **No fix is authorised in this round.**

Evidence: `diagnostics/CRM-012_claude-review_20260911.md`.

## Two independent work packages. Do not merge them.

WP-A is read-only diagnosis of a suspected catalogue gap. WP-B is a measurement
pass whose output the owner produces by using the dashboard. Neither one changes
business behaviour, and WP-B must not wait on WP-A.

---

## WP-A — Why `BR-*` is invisible to the 3D name report

### What is established

The owner ran `crm0123dpNameDivergenceReportForOwner()` on 2026-09-11. Every
returned row is `ACC-3D-*`. Not one `BR-*` row appeared, although the dashboard
Products tab renders `BR-CHARM-100`, `BR-BULB-100`, `BR-SQUIR-100`,
`BR-MEW-100`, `BR-PIKA-100`, `BR-UMBRE-100/110/120`, `BR-CHARM-200` with the
name `3D-друк`.

The filter is not the cause. `is3dpPackagingSku_` tests
`/^(?:BR|FIG|ACC-3D)-[A-Z0-9][A-Z0-9-]*$/`, which `BR-CHARM-100` satisfies.

The cause is the SKU source. The report builds its set from
`Object.keys(productBySku).concat(Object.keys(remoteBySku))` — `Товари` plus the
3D-P catalogue — and reads `Майстер_Товарів` only as a name lookup. The
dashboard Products tab reads `Майстер_Товарів` (`apiSkuList_` over
`_getAutoSs()`). A SKU present in `Майстер_Товарів` but absent from `Товари` is
therefore visible in the dashboard and invisible to the report by construction.

### The question this WP answers

Is `BR-*` missing from `Товари`, and if so what else is missing with it?

This matters more than the name. In the owner's Products view every `BR-*` row
shows `—` for `Дин. РРЦ` and `Поточна собівартість`, stock 0, and a margin of
`20.0%*` with the asterisk that marks a default rather than a computed value.
That is the signature of rows with no CRM catalogue or stock backing, not of
rows with a bad name. If the family was never properly registered, then cost,
FIFO, stock projection and RRP for those SKUs are all unbacked, and renaming
them would only hide it.

### Required output — a report, no writes

For every SKU matching `^(?:BR|FIG)-`, state presence and content per sheet:

| source | what to report |
|---|---|
| `Товари` (CRM) | present? row number; `Коротка назва`, `Сет / група`, `Формат`, `Активний` |
| `Майстер_Товарів` (Автоматизація) | present? row number; `Назва`, `Активний` |
| `РРЦ` | present? manual RRP value |
| `Склад` | present? stock row |
| `Закупки` | any lot for that SKU |
| `Продажі` | any sale line for that SKU |
| 3D-P catalogue (`3dp_skus`, `include_archived=true`) | present? name, status |

Then classify each SKU into exactly one bucket and count the buckets:

- **orphan-in-master** — in `Майстер_Товарів` only; no `Товари` / `РРЦ` /
  `Склад` row. Renders in the dashboard, backed by nothing.
- **partially registered** — in `Товари` but missing at least one of
  `РРЦ` / `Склад`.
- **fully registered** — present everywhere expected.
- **has movement without registration** — any `Закупки` or `Продажі` line for a
  SKU that is not fully registered. Report these first; they are the ones where
  money has already moved against an unbacked SKU.

Read bounded ranges and header-resolved columns. Do not use
`apiIntegrityCheck_` as evidence here — it does not cover `Закупки`, `Продажі`,
`Склад` or `Списання`, so a clean check says nothing about this.

### Also report, separately

The one-line change that would have caught this: the divergence report should
take its SKU set from `Майстер_Товарів` as a third source, not only as a lookup.
State it; do not implement it this round.

### What NOT to do

- No writes to any sheet. No SKU creation, no rename, no RRP or stock entry.
- Do not infer a canonical name for anything.
- Do not "fix" `is3dpCatalogSku_` or `is3dpPackagingSku_` — they are correct.
- Do not delete or archive a `BR-*` row because it looks orphaned. An orphan
  with sales history is a repair, decided by the owner, not a cleanup.

### Acceptance

The bucket counts sum to the total number of `BR-*` and `FIG-*` SKUs found in
any source; `BR-CHARM-100` is traced explicitly across all seven sources; and
the report names which bucket explains the `3D-друк` name and the `20.0%*`
margin the owner sees.

---

## WP-B — C1 load telemetry

The owner has asked what this is in practice: Codex adds the counters, the owner
uses the dashboard normally, the owner sends the numbers back, and only then is
any optimisation decided. Nothing is reordered or deleted in this round.

### Client side

For every GET action already going through the existing request path, record and
expose: action name, active page, queue time, network time, render time, cache
hit or miss, response size, and whether the request was de-duplicated by the
existing in-flight map.

Surface it where the owner can copy it out in one action — a bounded table or a
single JSON blob behind an existing service control. Do not add a new sidebar
entry for this.

### Server side

Return a bounded `elapsed_ms` and a cache-hit flag from the heavy read actions:
`overview_bootstrap`, `overview_secondary`, `overview_assets`, `sku_list`,
`orders`, `ltv_report`, `finance_report`, `monthly_summary`.

**Never log customer rows, tokens, phone numbers or full payloads.** Timings,
counts and sizes only.

### Owner measurement protocol

Write it into the report as numbered steps the owner can follow without
interpretation: cold load after Ctrl+F5, then first open of each page in a
stated order, then a warm repeat of the same order. Three runs, because a single
Apps Script timing is noise — the same discipline already recorded for PSI
measurement.

### Acceptance

The owner completes the protocol and produces p50/p95 for cold load, warm load
and first page open, and the three slowest actions are identified by measured
evidence rather than by reasoning about the code. Initial Overview time shows no
regression from adding the counters.

### Explicitly out of scope

C2 idle prefetch, C3 duplicated-work removal (including the `loadUpdates()` /
`loadAccounting()` coupling), and C4 dead-code deletion. C3 and C4 are ordered
after this measurement and are not authorised by it.

---

## Round-wide rules

- Preconditions: `SOURCE_STATE.md` records V173 as byte-verified. Confirm that
  still holds before editing `Code.gs`. Before trusting any new owner export,
  check it contains a string only the newest round introduced — a version number
  in a filename is not evidence.
- WP-A writes nothing, so `OPS-CRMINTEGRITY` is not triggered by it. WP-B adds
  no sheet structure either. Run the integrity check once before and once after
  the publication that carries WP-B, and record both outputs.
- Do not add another one-shot `…ForOwner` function unless the work genuinely
  cannot be done from the dashboard. There are already six, plus thirteen
  `setup…` and eleven repair/backfill functions, of which about nine are spent.
  That inventory is the subject of C4; do not grow it further here.
- Secrets stay out: `.env.review`, `scripts/.env`, `client_secret.json`, and
  Apps Script Script Properties never enter a mirror.

## Deliverables

One diagnostic per work package. WP-A: the source-by-source table, the bucket
counts, the `BR-CHARM-100` trace, and the proposed report-source change stated
but not implemented. WP-B: the telemetry contract, the owner protocol, and the
measured p50/p95 once the owner has run it.
