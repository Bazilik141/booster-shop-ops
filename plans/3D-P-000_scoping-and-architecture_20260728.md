# 3D-P — 3D-Print Merchandise Program: Scoping & Architecture Plan

Date: 2026-07-28 | Owner: Raccoon | Author: Claude
Parent: none (new program, task-code series `3D-P-XXX` per owner instruction)
Related: `CAT-002` (catalog/accessories parent), NCRM program (future integration target)

## 1. Context

A friend ("the Friend") owns a 3D printer and will produce figurines and
accessories themed around the TCGs Booster Shop sells. Reference images
shared 2026-07-28 (Pikachu figure, Gengar figure, Mew pokeball case, Ditto
pen holder, pokeball deck boxes in three colors, Onix keychain, "Pikachu
through the wall" decorative figure) are stock photos found by the Friend as
inspiration, except the Onix chain photo which the Friend printed himself.
No product has been finalized; the Friend is currently testing his print
process and skill. This plan captures the scoping session held 2026-07-28
and does not itself approve any commercial term, category, or SKU.

## 2. Business model — two distinct tracks

The owner confirmed two tracks with different economics. They must not be
merged in tooling or reporting.

**Track A — "Site sale" (revenue share).** The Friend prints, the owner
lists the item for sale on boostershop.website, the Friend receives a
percentage of the sale price. Owner's cost basis is materials only; the
Friend's payment is a share of revenue, not an added cost line.

**Track B — "Marketing freebie."** The owner buys finished figurines from
the Friend at a minimal markup (near print cost) and bundles them free into
large customer orders as a bonus. These items are never listed for sale;
the purchase price is a marketing expense, tracked separately from Track A.

Both tracks share the same physical nomenclature (a given SKU could
theoretically feed either track, or both over time), so the tracking
workbook keeps one master item list and separates the two tracks only at
the transaction level (sales log vs. freebie-purchase log).

## 3. Open commercial decisions (owner-confirmed as not yet final)

- Track A revenue-share percentage — agreed in principle, not fixed.
- Track B purchase price ("Friend's minimal markup") — not a fixed number yet.
- Order-value threshold that triggers a free Track-B bonus — not defined.
- Who else besides the Friend/owner may enter data into the tracking sheet — currently: both.

None of these are assumed or hardcoded in the workbook; the relevant cells
are left blank and highlighted yellow (see workbook `Легенда` tab).

## 4. Catalog / SEO positioning — risk-gated recommendation

Ran through `bs-seo-risk-gate` before recommending anything, because this
touches category structure and product schema (both flagged zones in
`AGENTS.md` risky zones and in the skill's High-risk list).

**Owner's question:** merge into the existing "Аксесуари" category (renamed
"Аксесуари та фігурки"), or add a "Фігурки" subcategory under each
franchise (matching the existing `Pokémon > Бустери / Бустер бокси /
Набори` pattern documented in `plans/categories-full-structure-2026-06-04.md`)?

**Recommendation: do not rename/merge "Аксесуари". Add per-franchise
"Фігурки" subcategories instead**, and keep only genuinely generic
(non-IP-themed) 3D prints in the existing "Аксесуари" category.

Reasoning: "Аксесуари" today holds functional, keyword-driven SKUs
(sleeves/protectors, toploaders, magnetic cases — see
`plans/accessories_sku_cards_20260620.md`) that rank on functional intent
("протектори для карток", "топлоадери"). Every reference item shown is
IP-themed (Pikachu, Gengar, Mew, Ditto, Onix) — the buyer's intent there is
"Pokémon merch", not "card accessory". Folding franchise-themed figurines
into "Аксесуари" would dilute that category's existing keyword focus and
mix two different search intents on one page. The site already has a
working precedent for franchise-scoped subcategories (`Набори Pokémon`,
`Набори One Piece` per the June structure plan) — "Фігурки" under `Pokémon`
(ID 59) fits that precedent exactly and requires no rename of anything that
already has traffic.

**1. Risk:** Medium overall (new, additive category — no existing URL
changed, no redirect needed); **High** specifically for two sub-items: (a)
Product JSON-LD schema on the new SKUs, and (b) any Merchant Center feed
inclusion.
**2. Affected assets:** new category page(s) (`Pokémon > Фігурки`, later
per-franchise as real SKUs appear), new product detail pages, Product
schema for those SKUs, sitemap (new URLs must be picked up by the existing
sitemap regen path — `TECH-029`/`sitemap-regen.sh`), Merchant feed
inclusion/exclusion decision.
**3. Do not touch:** existing "Аксесуари" category name, slug, or meta
(no rename, no merge); do not invent a GTIN, `aggregateRating`, or
`reviewCount` for these SKUs — they are small-batch/print-on-demand goods
with no manufacturer identifier (see `bs-merchant-schema-qa` and the
project's brand/SEO constraints on invented structured data).
**4. Safest next action:** create the new subcategory only once a first
real, owner-approved SKU exists (no category with zero products); model
Product schema without `gtin`/`gtin13`/`mpn` fields that don't exist, use
`brand` = Booster Shop or a clearly distinct "hand-crafted/print-on-demand"
framing consistent with the "curated store, not marketplace" brand rule.
**5. QA checklist:** category renders + breadcrumb correct; new URL appears
in sitemap after regen; canonical self-referencing; Rich Results test
passes with no GTIN/rating errors; no orphan pages.
**6. Owner approval required:** yes — for the final category name/slug, and
separately for whether any 3D-printed SKU ever enters the Merchant feed at
all (open question, not yet decided).
**7. Related smoke checks:** `bs-merchant-schema-qa` before any Merchant
feed inclusion. `bs-checkout-smoke` not triggered (no checkout/payment
logic changes expected).

This is scoping only — no OpenCart category has been created, no product
schema written. See task `3D-P-002`.

## 5. CRM / tracking workbook architecture

Ran through `bs-crm-plan` rules before proposing anything, because the
owner's stated end goal is Apps-Script-level sync with the existing manual
CRM.

**Hard constraints applied:**
- CRM and the master dashboard (`1YUGdtxHQJee6vY8MdwRsrUxudJCMtnghOGPVJXwO5ik`)
  are read-only from this program's perspective by default; no write-back
  without explicit owner approval.
- No formulas in the master dashboard are touched by this plan or workbook.
- Sequence to follow: **read → import → dashboard/report → automation**.
  Automation (Apps Script sync of sales/stock into the main CRM) only after
  (a) explicit owner approval and (b) the manual/standalone workbook has run
  stable for 2+ weeks.

**Phase 1 (delivered this session):** a standalone workbook,
`3d-print/3D-P_nomenclature-tracker_v1_20260728.xlsx`, entirely independent
of the live CRM/dashboard. Tabs: `Легенда` (legend/instructions),
`Номенклатура` (master item list — SKU, franchise, type, track, status,
print time, material, material cost), `Друк-лог` (production log — the
Friend's primary input), `Продажі` (Track A sales log with revenue-share
math), `Маркетингові_плюшки` (Track B purchase/gift log), `Наявність`
(stock — formula-only, derived from the three logs above, nobody edits it
by hand), `Аналітика` (margin calculator with 3 RRP scenarios per SKU, plus
a market-reference block — see §6). All formulas verified with `recalc.py`
(451 formulas, 0 errors).

**Phase 2 (not started, needs owner approval):** once this workbook is
live in Google Sheets and has real data for 2+ weeks, a narrow, read-only
pull from the existing CRM/dashboard (e.g. via the documented Apps Script
GET actions `summary`/`orders`/`stock_alerts`/`sku_list`, not a full sheet
export) to cross-reference any 3D-print SKU that starts flowing through
real OpenCart orders. This is read-only in this phase — it populates the
3D-P workbook, it does not write anything back to the CRM.

**Phase 3 (not started, deferred):** two-way automation (e.g. auto-logging
a site sale from an OpenCart order, or syncing stock back). Requires
explicit owner approval per write action, a rollback plan, and must never
overwrite manual-CRM columns or formulas. This is the eventual
"script-based sync" the owner described — intentionally the last phase, not
the first.

**Phase 4 (future, deferred to NCRM backlog):** a dedicated 3D-print module
inside NCRM with narrow, purpose-built UI access for the Friend, replacing
the interim spreadsheet — matches the owner's stated long-term intent.
Parallels the existing `NCRM-18` deferral pattern (starts only after the
current NCRM backlog `NCRM-11..17` closes).

**Data-entry model:** both the Friend and the owner will enter data
(owner's answer to Q4). The workbook splits by tab rather than by
permission: the Friend's natural inputs (`Друк-лог`, and the
production-side columns of `Номенклатура`) are separate tabs/columns from
the owner's inputs (`Продажі`, `Маркетингові_плюшки`, classification
columns of `Номенклатура`), so both can write without colliding on the same
cells. `Наявність` is formula-only and neither should hand-edit it.

## 6. Pricing & sizing analytics — evidence gap

Owner asked for analysis on viable RRP and sizes. A bounded web search
(2026-07-28) found real market presence for 3D-printed Pokémon merch on
Etsy and Prom.ua, but only **one** concrete, citable price point: a
hand-painted 3D-printed Mimikyu figurine on Etsy at **$27.50**
([listing](https://www.etsy.com/listing/1294929392/3d-printed-mimkyu-pokemon-figurine-hand)).
Generic TCG deck-box/dice-tower searches surfaced product categories but no
citable prices; Ukrainian searches confirmed Prom.ua has active
"3D-друк фігурок" and "фігурки покемонів" categories (447 and 2,490 listings
respectively) but again no per-item price extracted from search snippets
alone.

**This was not enough evidence to set an RRP as of 2026-07-28.** Per project
rule (never invent prices/specifications), the workbook's `Аналітика` tab
shipped with this single data point clearly labeled as insufficient. This
gap has since been closed — see §14 for the 2026-07-29 research pass (30
verified comparables). The remaining open step is reconciling these market
bands against the Friend's real cost/print-time on concrete SKUs, not
finding more listings.

## 7. Task breakdown

| ID | Title | Status | Priority | Notes |
|---|---|---|---|---|
| 3D-P-000 | Discovery & scoping (this plan) | In progress | High | Open owner decisions listed in §3, §6 |
| 3D-P-001 | Nomenclature & cost/RRP tracking workbook | In progress | High | v1 delivered this session, standalone |
| 3D-P-002 | Catalog placement — Pokémon "Фігурки" subcategory | Not started | Medium | Blocked on first real, owner-approved SKU |
| 3D-P-003 | Pricing & sizing market research | In progress | Medium | 30 comparables found 2026-07-29 (§14); still needs reconciliation against Friend's real cost/print-time before RRP is final |
| 3D-P-004 | Marketing-freebie sourcing flow | Not started | Medium | Needs order-value threshold + Friend's buy price |
| 3D-P-005 | Future NCRM module, narrow Friend access | Not started | Low | Deferred behind NCRM-11..17 and a stable Phase-1 workbook |

Notion page IDs and dashboard mirror entries are recorded in
`ROADMAP_SOP.md §5` and `context-index.md` as of this session.

## 8. Risks & rollback

- **No production writes made.** Nothing in OpenCart, the live CRM, or the
  master dashboard formulas was touched. The only new artifacts are: this
  plan, the standalone workbook, 6 Notion cards, and additive entries in
  `context-index.md`, `ROADMAP_SOP.md §5`, and the dashboard mirror
  (`dashboard/booster-dashboard.html`, new `3D-PRINT` section — pure
  addition, no existing entry edited).
- **Rollback:** delete the new workbook file, delete/archive the 6 Notion
  cards, revert the three additive doc/dashboard edits via `git diff` — all
  reversible with no server or database impact.
- **Main risk going forward:** SEO/schema work in `3D-P-002` and Merchant
  feed inclusion — both gated High-risk above and require explicit owner
  approval before any OpenCart change.

## 9. Owner decisions needed now

1. Confirm (even provisionally) the Track A revenue-share % and Track B
   buy-price basis.
2. Confirm the "Фігурки" subcategory-per-franchise approach for
   `3D-P-002` (or reject it and state the preferred alternative).
3. Decide whether 3D-printed SKUs should ever enter the Merchant feed.
4. Approve moving the v1 workbook into Google Sheets and grant the Friend
   whatever access level is intended.

## 10. Next actions

- Owner reviews this plan + the workbook, answers §9.
- `3D-P-003` (pricing research) can start independently at any time — it
  blocks nothing else.
- `3D-P-002` stays parked until a first real SKU exists.

## 11. External handoff review — `3D-P_handoff_v1.md` (ChatGPT, 2026-07-28)

Owner ran a separate deep session with ChatGPT and uploaded its output.
Archived verbatim at `plans/3D-P_handoff-chatgpt_v1_20260728.md`. Reviewed in
full against this plan, `AGENTS.md`/`ROADMAP_SOP.md`, and the `bs-crm-plan`
rules.

### 11.1 Verdict

A strong, detailed business/data-model spec — adopt its terminology,
financial formulas, entity model, and consolidated partner-questions (§11.3
below). **Do not** adopt its technical-architecture recommendation (a
separate VPS-hosted backend plus two bespoke web UIs, §14 of that document)
as a build order for right now: standing that up today would create a
second, parallel CRM-like system while NCRM (`NCRM-00..19`) is still
mid-build — exactly the "parallel writer / duplicate manual workflow" risk
`bs-crm-plan` and this project's rules warn against, and it competes with the
already-recorded `3D-P-005` plan (defer to a future NCRM module). Recommend
treating the ChatGPT document as the **requirements spec for that future
NCRM module** rather than a separate system to commission now. This is an
owner decision either way (§11.4), not something assumed here.

### 11.2 Urgent, unrelated finding — live credential exposure

The document's §14.3 warns that any secret-like token must be pulled out of
`booster-dashboard.html` before ever giving the Friend dashboard access.
Checked — **this is not hypothetical**: `dashboard/booster-dashboard.html`
(repo mirror) has a hardcoded bot token at line 522 (`const TOKEN = '...'`),
sent on every Apps Script request (lines 526 and 538). `git log` shows this
file has been committed repeatedly with this constant intact, and the repo
has a live GitHub remote (`github.com/Bazilik141/booster-shop-ops`). This is
a live-credential exposure issue, **independent of the 3D-P program** —
treat it as urgent regardless of any 3D-P architecture decision. The token
value is intentionally not reproduced here. Owner action needed now: confirm
whether that GitHub repository is public, and rotate the token either way —
a committed secret should be treated as compromised even in a private repo.

### 11.3 Adopted into this plan (refines §2-3's informal framing)

- Two commercial models, formalized, not one: **Model A** — Friend gets
  roughly 70% of net revenue, his production-cost recovery is baked into
  that share. **Model B** — Friend's production cost is reimbursed first,
  then the remaining profit is split 50/50. The calculator should show both
  side by side before the owner and the Friend pick one; once picked, it is
  fixed per-SKU with an effective date, and closed historical periods are
  never recalculated.
- All share math runs on **actual sale price after discount/coupon**, never
  on RRP.
- "Sold" vs "marketing freebie" vs "sample" vs "scrapped" are **stock-movement
  types on the same SKU**, not separate SKU categories — sharper than this
  plan's original two-track framing in §2; adopt the terminology, keep the
  two-track economics.
- Cost is versioned per SKU; a sale always references the cost version that
  was active when it happened, so a later material-price change never
  rewrites an old sale's math.
- Two physical stock locations (Friend's workshop / BoosterShop); the CRM
  only ever needs the aggregate "available in Ukraine" figure, the 3D
  program needs the location split. Stock sitting at the Friend's counts as
  available but with its own handover lead time, not instant-ship.
- Settlement/period mechanics (twice-monthly or monthly-with-advance),
  a below-minimum-price flag requiring manual sign-off, and an audit log on
  every price/cost/share-model change — sound practice, adopt as
  requirements now even though no system enforces them yet.
- A consolidated, priority-ranked list of ~43 open questions to settle with
  the Friend (document §18) — the top 10 (Priority 1) are the real blocker
  for `3D-P-000`; nothing here shrinks that list, it mostly confirms and
  sharpens it.

### 11.4 Owner decisions now needed

1. **Architecture fork** (blocks `3D-P-005` scope): (a) keep deferring to
   NCRM as originally planned, (b) commission a separate bespoke
   backend/VPS now per the ChatGPT recommendation, or (c) keep extending the
   Google Sheets version for longer and accept its limits (no server-side
   role enforcement, weaker audit trail than a real database). No default
   has been assumed here.
2. **Financial model A vs B** — the ChatGPT document's own suggestion is to
   compare both on 5-10 real SKUs before committing; reasonable, but moot
   until a real SKU exists and sells.
3. **CRM token rotation** — independent and urgent, see §11.2.
4. The Priority-1 question list for the Friend (§11.3) is the concrete next
   conversation to have before any financial automation proceeds.

### 11.5 What was deliberately not done, and why

- The Google Sheet was **not** rebuilt into the full entity model
  (`Product`/`Variant`/`CostVersion`/`ProductionBatch`/`Settlement`/... —
  document §7). The document's own staging (§20: "Етап 0" fixes decisions
  before "Етап 1" normalizes data) puts decision-making before data-model
  rebuilds — doing the rebuild before §11.4 is answered would mean redoing
  it once an architecture is chosen.
- No backend, API, or auth work was started. That is implementation, and
  per this project's role split (`AGENTS.md`) it belongs to Codex behind an
  owner-approved handoff once the architecture fork is resolved — not
  something to build directly during a scoping review.
- No new `3D-P-0xx` Notion cards were created; the existing six were updated
  instead — matches both this project's "don't create duplicates" rule and
  the ChatGPT document's own instruction (§20) not to touch the existing
  `3D-P-000–005` codes.

## 12. Owner decisions — closed 2026-07-28

- **Architecture fork (§11.4.1):** resolved as a hybrid, not any of the three
  original options cleanly. Build on Google Sheets now, plus a **local
  server UI** on top of it for a better day-to-day experience than raw
  spreadsheet editing. Migrate from Sheets to NCRM once NCRM is ready
  (matches the original `3D-P-005` deferral — NCRM stays the eventual home,
  nothing changes there). **Open follow-up, not yet answered:** is the local
  server for the owner's own use only, or does the Friend need remote access
  to it too? If the Friend needs remote access, the document's §14.1 options
  (Tailscale-style private network, a small VPS, an always-on home
  server/NAS, or a temporary Apps-Script webapp) still apply — "local
  server" alone does not solve remote reachability. Needs a follow-up
  decision once this is in scope.
- **Financial model (§11.4.2):** resolved — **Model B only**
  (production-cost reimbursement to the Friend first, remaining profit
  split 50/50). The side-by-side Model A/B comparison recommended by the
  ChatGPT document is dropped; Model A is out of scope going forward. The
  workbook's `Продажі`/`Аналітика` formulas are being updated to Model B
  math (v2, this session) — see `3d-print/` folder.
- **CRM token rotation (§11.2):** owner confirms this is handled.

`3D-P-005`'s scope note: it now targets migrating this Sheets+local-server
setup into NCRM once NCRM is ready, not a green-field NCRM module design
from scratch.

## 13. Local-server architecture — clarified 2026-07-28

Owner clarified the "Sheets + local server UI" decision from §12: it is two
separate local clients against one shared backend, not a single server the
owner would host and the Friend would need to reach remotely.

- **Owner's access:** a tab inside the existing `booster-dashboard.html` —
  already local, already working, no new infrastructure needed.
- **Friend's access:** a local server running on the Friend's own machine,
  pulling data through the Google Apps Script Web API with Google
  authorization. This is exactly option 4 in the ChatGPT document's §14.1
  ("тимчасово — вебзастосунок на Apps Script із Google-авторизацією").
  Because the Friend's server runs on his own machine, the
  remote-reachability problem the document describes in §14.1-14.2
  (Tailscale / VPS / NAS) doesn't apply here — nothing needs to reach into
  the owner's home network. Confirmed resolved, no longer an open question.

**New, concrete requirement this creates (not yet built):** the Friend's
server must not reuse the master CRM token already found hardcoded in
`booster-dashboard.html` (§11.2). Per the document's own §14.4 ("API за
ролями"), the Friend needs a separate, narrowly-scoped Apps Script action
that returns only 3D-P data (his SKUs, his sale lines, his accrual/payout),
behind its own distinct token — never the master token, and never raw
access to CRM order/customer data. This is Apps Script backend work (a
risky zone per `AGENTS.md`) and should go through a proper Codex handoff
once the Friend's server is actually being built, not wired up ad hoc.

## 14. 3D-P-003 — Market pricing research findings, 2026-07-29

Bounded web research (Prom.ua category listing + Etsy search/similar-items,
both fetched 2026-07-29; one Etsy point carried over from 2026-07-28) found
**30 verified, citable price points** — up from the single data point this
plan flagged as insufficient in §6. Full source list with URLs and dates is
logged row-by-row in the workbook's `Аналітика` tab, "Ринкові орієнтири"
block (`3D-P_nomenclature-tracker_v2_20260728.xlsx`), not duplicated here.

**Sources checked:**

- Prom.ua category `3d-pechat-figurok.html` ("3D друк фігурок", 1,535
  listings total) — 22 comparables extracted from the first 29 listed by
  rating, all real, live, priced in UAH.
- Etsy — 9 comparables (1 from 2026-07-28, 8 new), priced in USD.
- Excluded, honestly reported rather than guessed at: the Etsy "Kanto
  Starters" unpainted-figures listing checked 2026-07-28 is now sold
  out/unavailable; a Prom.ua URL surfaced by an earlier search snippet
  (`p925737942-druk.html`) turned out to be a typography/print-shop
  services page, unrelated to 3D-printed figurines — not used.

**Price bands found (UAH, Prom.ua — primary reference, since Booster Shop
sells in UAH):**

| Segment | Range | Median | n |
|---|---|---|---|
| Keychain / small format (5-8 cm, single-color PLA) | 160-230 грн | ≈200 грн | 6 |
| Medium figurine (8-16 cm, single/few-color, no hand-paint) | 200-430 грн | ≈299 грн | 11 |
| Hand-painted / decorative statuette | 500-3,000 грн | — (too few, wide spread) | 3 |
| Multi-figure set (price per set, not per unit) | 290-934 грн/компл. | — | 3 |

**Etsy (USD, secondary/export reference only — not a basis for UAH RRP):**
simple single-color figure $5.00-9.99; hand-painted/detailed $18.10-29.98;
large or custom pieces $60.00-90.00 (pre-discount).

**Read on the Friend's Onix keychain reference photo:** it falls closest to
the "keychain / small format" band above (160-230 грн). This is a directional
placement only — not a price recommendation — pending the caveat below.

**Still not resolved — do not set a final RRP from this alone.** This is
real market-rate evidence, not the Friend's actual production cost or print
time. Per §6's original rule and the workbook's `Собівартість Сергія`
simplification note, an RRP decision still needs the Friend's real material
cost + print-time-based cost for 3-5 concrete SKUs, checked against these
bands — not the bands alone. `3D-P-003` stays **In progress** (not Done)
until that reconciliation happens; recommended next step is owner + Friend
picking 3-5 candidate SKUs and running them through the `Продажі`/`Аналітика`
tabs with real numbers.
