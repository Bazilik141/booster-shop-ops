# Roadmap reconciliation — Notion ↔ dashboard, 2026-09-08

Full sweep of all 288 Notion rows against the dashboard `ROADMAP_TASKS` mirror,
run on owner instruction. Notion remains canonical; this file records what the
sweep found and did, so the next session does not repeat the analysis.

## Outcome

| | before | after |
|---|---|---|
| Notion rows | 288 | 288 |
| dashboard `ROADMAP_TASKS` rows | 130 | 206 |
| not-closed Notion rows with no dashboard row | ~110 | 0 |
| Notion↔dashboard status conflicts | 4 | 0 |
| dashboard `status` values outside the SOP vocabulary | 1 (`superseded`) | 0 |

## What was wrong, by class

**Status conflicts (4).** `PAY-001-UI`, `R-13.5`, `RD-13` were open in Notion and
`done` on the dashboard — the dashboard was right in all three. `MKT-TG-004`
carried a `superseded` status the SOP vocabulary does not define.

**Shipped but never closed (10).** Work delivered under a different patch or
version name than the roadmap ID, so nobody went back to the row: `TECH-005`,
`TECH-007`, `TECH-008`, `TECH-009`, `TECH-011`, `TECH-033`, `CRM-001`,
`CRM-002`, `3D-P-000`, `MKT-TG-005`.

**Verified live during this sweep (6).** Closed against the 2026-09-07 cPanel
backup rather than against a report: `CAT-001` (category and both children exist
in `ocp5_category_description`), `SEO-002` (`<h1 id="bs-home-title">` in
`home.twig`), `POLISH-001` (`© {{ "now"|date("Y") }}` in `footer.twig`),
`POLISH-004` (zero `route=` in `footer.twig`), `UX-019` (back-to-top + cookie
notice in `footer.twig`), `R-13.1` (three-tier `$stock_priority` prepended to
every catalogue `ORDER BY` in `catalog/model/catalog/product.php:285-296`).

**Superseded but never marked (17).** The legacy `R-`/`UX-` redesign series was
replaced by `RD-01…RD-23` in `plans/RD-redesign-roadmap-plan_2026-05-30.md`, but
§6 of that plan asked the owner four questions that were never answered — so the
RD rows were created and the old rows were left open for three months. Closed
with `Stage` = "Superseded by RD-XX": `R-04`, `R-11`, `R-11-UI-1`, `R-11-UI-2`,
`R-11b`, `R-12`, `R-13`, `R-14`, `R-15`, `UX-007`, `UX-013`, `UX-014`, `UX-020`,
`UX-024`, `UX-026`, `MKT-006`, `UX-012`. Separately `TECH-002` and `TECH-004`
were closed as merged into `TECH-013`, whose own title says
"об'єднує TECH-002/003/004".

Closing a superseded row does **not** claim the work is done. `RD-11`, `RD-12`
and `RD-16…RD-20` are still `Not started`; each closure note says so explicitly.

**Not tasks (2).** `AUTO-010` ("Scope guard: що не робити зараз") and `UX-032`
("Розглянути після UX-024..031") have no deliverable and no acceptance criteria.
Archived with an `[ARCHIVED]` name prefix, following the NCRM-07b / TECH-013
convention. `CONTENT-20260721-test` — a leftover roadmap-write test — was
archived the same way.

**Data quality.** `Last Updated` is a text property and had been used to store
whole progress paragraphs on `AUTO-002`, `AUTO-003`, `AUTO-004`, `AUTO-005`,
`AUTO-012` and `BUG-002`. Each paragraph was moved verbatim into its page body
and the field set to the date the paragraph itself named. The Notion ID
`ST-2b.1–2b.4` uses an en-dash while the dashboard used a hyphen, so exact-ID
lookups never matched the two; the dashboard was aligned to Notion.

**ID collisions.** Eight IDs resolve to two live pages each — `AUTO-001` through
`AUTO-006`, `OPS-003`, `TECH-029`. These are different tasks sharing an ID, not
duplicates, so none was archived; every colliding page now carries an
`[ID-колізія, партія YYYY-MM-DD]` name prefix. The full pairing table is in
`ROADMAP_SOP.md` §5.

## Left open on purpose, with the remainder measured

- **`SETUP-001`** — `minimum` is clean on 117 of 119 products; only
  `product_id` 59 (`= 2`) and 73 (`= 5`) remain, and 73 may be deliberate. The
  300 ₴ order minimum is not configured at all: no order-minimum key exists in
  `ocp5_setting`.
- **`TECH-032`** — parametric URLs are blocked in the live `robots.txt`
  (`page`, `sort`, `order`, `limit`, `filter_*`), internal search is not. The
  remainder is `Disallow: /*?search=` and `Disallow: /*&search=`. Route it
  through `bs-seo-risk-gate` first.
- **`CHECKOUT-002`** — mechanism shipped 2026-07-19; Part A timings and Part B
  loader approval are unmeasured and stay in scope by owner decision.
- **`TECH-005`** — closed `Done` watch-only: Search Console still reports the
  fetch error and the owner accepted it on 2026-09-08. Do not cite that closure
  as evidence the sitemap error is fixed.

## Defect found in passing, not part of this sweep

`catalog/view/template/product/product.twig` hard-codes `reviewCount` to `"1"`
inside the `aggregateRating` block. The block is correctly gated behind
`{% if review_status and rating %}`, so nothing is fabricated for products with
no reviews, but the count will be wrong for any product with two or more.

## Second pass — owner decisions of 2026-09-08

**Closed as superseded (8).** The six `AUTO-` rows of the 2026-05-20 batch
(`AUTO-002` master automation table, `AUTO-003` sales reports, `AUTO-004`
channel analytics, `AUTO-005` competitor-monitoring MVP, `AUTO-006` competitor
classification, `AUTO-007` market-price recommendation) had sat `In progress`
since May. That programme was replaced by the CRM dashboard and its Apps Script
API (CRM-MULTICHANNEL, CRM-004…010, DASH-001/002, OPS-CODEMIRROR), by the NCRM
series, and — for competitor and pricing work — by the `bs-competitor-watch`
skill and the CRM РРЦ reconciliation. `UX-015` (Hutko return/session) and
`UX-016` (Checkbox/fiscalization) were May umbrellas with no scope of their own;
the real defects live under ST-2b.6, the CHECKOUT- series and `bs-checkout-smoke`.

**Downgraded, not closed (9).** `MKT-001`, `MKT-002`, `OPS-002`, `UX-001`,
`UX-002`, `UX-003`, `UX-005`, `UX-008`, `UX-010` — ideas with no owner, no date
and no acceptance criteria, several sitting at `High` and outranking work that
was actually moving. The three that were `In progress` went to `Not started`;
all nine went to `Low`, with `Stage` = "Backlog — понижено 2026-09-08". Each
note records what has shipped underneath the umbrella since May, so a future
rescope starts from the live site rather than the May state.

## Method note

Production evidence came from `_patch_backups/` directory names, template and
model files, and the SQL dump inside the newest cPanel backup — not from git
history. See project memory `roadmap-status-drift-evidence` for why a roadmap
status is never evidence, and `no-git-via-sandbox` for why git is not used here.
