# Codex Handoff — 3D-P-008: 3D-P Apps Script API foundation (read + scoped write) + Sheet reconciliation

Date: 2026-07-31 | Parent: 3D-P-000 · related: 3D-P-001, 3D-P-006, 3D-P-007
Codex config: model=Sol · effort=xhigh

## Addendum — Schema correction, 2026-08-02 (read this first; base task below is Done and deployed)

Base scope below shipped 2026-08-01 (see `diagnostics/3D-P-008_apps-script-api-foundation_report_20260801.md`).
Owner then got Serhiy's final, simplified cost-calculation model, which invalidates the deployed
`Номенклатура!O` ("Комбінована амортизація, грн/год") column and its `K` formula term. This addendum is a
**follow-up patch to the already-live schema**, not a new task. `3D-P-006`/`3D-P-007` must not build their
calculator UI until this addendum ships.

**Final cost model (owner-confirmed, 2026-08-02):**

- `Собівартість матеріалу (per unit) = (вага_виробу_за_од ÷ вага_котушки) × ціна_котушки`
- `Електроенергія (per unit) = 0.17 кВт × час_друку_за_од(год) × ціна_ел_ен(грн/кВт·год)`
- `Амортизація (per unit) = амортизація_ставка(грн/год) × час_друку_за_од(год)`
- `Собівартість Сергія (виробнича) = Матеріал + Електроенергія + Амортизація` (+ фурнітура, once assigned —
  see below; праця is intentionally excluded, fully absorbed by the 50/50 split, no separate tracking)

**Batch/session input model:** Serhiy works per print session (batch), not per single unit. He picks a SKU
from a dropdown and enters the quantity of units produced in that session; the session's total print time and
total product weight get evenly divided by that quantity before being stored as the per-unit `час_друку`/
`вага_виробу` values consumed by the formulas above. Whether this division happens client-side in the
calculator UI (006/007) before a normal `3dp_write`, or server-side via a new dedicated write action, is
Codex's implementation choice — either is acceptable as long as the value that lands in `Номенклатура` is
always the **per-unit, already-divided** number, never a raw batch total.

**Plastic type:** drop entirely. Do not add or keep any plastic-type column/whitelist entry — owner explicitly
does not want it tracked (`4.1: похуй, скіп`).

**Fixed global constants (NOT per-SKU, NOT re-entered per calculation):**

- Printer power: `170 Вт` (`0.17 кВт`)
- Electricity price: `4.32 грн/кВт·год`
- Printer amortization: `12 грн/год`

These three must live as **editable settings cells** (a small named range, e.g. a new `Налаштування` block —
placement is Codex's call, propose one and confirm with the owner) that the `K`-equivalent formula references,
not hardcoded literals in the Apps Script source and not per-SKU manual columns. Owner must be able to update
any of the three later (electricity price changes, amortization estimate revised) without a new Codex deploy.

**Required schema changes to `Номенклатура`:**

- Remove `O` ("Комбінована амортизація, грн/год") as a per-SKU manual input — superseded, amortization and
  electricity are now global-constant-driven formula terms, not manual per-SKU entries.
- Confirm/rename the manual columns actually used for material calc so they hold: вага_виробу (per unit, г),
  час_друку (per unit, год), вага_котушки (г), ціна_котушки (грн). If the currently-deployed `J`
  ("Ціна матеріалу, грн/кг") column is still a raw manual price/kg field, replace it with the
  вага_котушки/ціна_котушки pair — material price/kg is now always *derived* (ціна_котушки ÷ вага_котушки),
  never entered directly.
- Recompute the `K` (or equivalent) formula per the model above, referencing the new global settings cells.
- Read current live headers/whitelist maps first (`OWNER_MANUAL_COLUMNS_3DP` / `SERHIY_MANUAL_COLUMNS_3DP` /
  `FORMULA_COLUMNS_3DP`) — do not assume the column letters described here are still current; confirm against
  the live sheet the same way the original task did (font-color inspection, not guessing).

**Defect/waste tracking (new, separate from cost formula):** cost calc intentionally ignores defect rate.
Instead, add a `Брак, шт` (defect count) field to `Друк-лог`, editable by either the owner (dashboard) or
Serhiy (his local server, `3D-P-007`) **after** a batch/session is produced — reuse the existing print-log
edit-with-history mechanism (`было → стало`) already built for `Друк-лог`, no new write-action type needed if
that mechanism already covers arbitrary column edits; confirm and extend the whitelist if not.

**Fixture/hardware (фурнітура):** no longer a free-value manual field entered upfront. Add a small reference
list (new tab or range, e.g. `Фурнітура_довідник`: name, price/шт) that `3D-P-006`/`3D-P-007`'s calculator
dropdown reads from. Assigning a fixture to a SKU is a **separate, later step** (after printing, once Serhiy/
owner decide which hardware variant), writing the selected reference price into `Номенклатура`'s existing
fixture column — it must remain editable independently of the material/amortization/electricity fields, not
gate the SKU's initial creation.

**Model link instead of photo:** unrelated to this API but noted for consistency —
`plans/3D-P-002_catalog-placement-admin-guide_20260731.md` §2/§7 updated 2026-08-02: the Enable-gate is now "a
link to the model" rather than a photo. No Sheet schema impact, mentioned here only so Codex has the same
picture.

**Acceptance criteria for this addendum:**

- [ ] `Номенклатура!O` (Комбінована амортизація) removed/repurposed; no per-SKU amortization or electricity
      manual input remains.
- [ ] New settings block exists with the three constants, editable, and the cost formula reads from it (not
      hardcoded literals).
- [ ] Material cost is derived from вага_котушки/ціна_котушки, not a manual price/kg field.
- [ ] `Друк-лог` supports a defect-count field, editable post-production by owner or Serhiy, with history.
- [ ] `Фурнітура_довідник` reference list exists; fixture assignment on a SKU is independently editable/updatable.
- [ ] No plastic-type column exists anywhere in the whitelist or schema.
- [ ] Reconciliation diff (already produced, still pending owner approval — unaffected by this addendum) is
      re-checked for any cell this addendum touches before being applied.
- [ ] `ROADMAP_FLOW` entry for `3D-P-008` updated to reflect this addendum's status.

This addendum must ship (or be explicitly owner-waived) **before** `3D-P-006`/`3D-P-007` build calculator UI
against the schema — both handoffs now reference this section instead of the original 2026-07-31 combined-rate
design.

**Status: Done, deployed 2026-08-02 (owner-run).** See `diagnostics/3D-P-008_schema-correction_report_20260802.md`.

## Addendum #2 — API/schema extension for 3D-P-013's Вироби/Калькулятор needs, 2026-08-02

Codex reviewed `3D-P-013` (dashboard restructure) and correctly flagged that four of its requirements are not
"pure UI" — they need new API surface on top of Addendum #1's already-deployed schema. This addendum scopes
that narrow extension. `3D-P-013` splits into a Phase A (buildable now, no API changes) and a Phase B (blocked
on this addendum) — see the split in `3D-P-013`'s handoff.

**1. Owner-only write access to `Налаштування`.** Addendum #1 made `Налаштування!B2:B4` visible, blue,
human-editable cells directly in Sheets, but did not add `Налаштування` to the API's write whitelist — the
dashboard/API cannot write to it today. Add `Налаштування` to `OWNER_MANUAL_COLUMNS_3DP` only (never
`SERHIY_MANUAL_COLUMNS_3DP` — these three constants are owner-only, per the original 2026-08-02 cost-model
decision). Same formula-check/optimistic-lock/`_Аудит_API` guarantees as every other write.

**2. Raw batch-draft persistence.** `3D-P-013`'s calculator needs the raw entered values (Кількість у партії,
шт; Сумарна вага партії, г; Сумарний час партії, год; Вага котушки, г; Ціна котушки, грн) to survive a SKU
reselect/new session — not just the derived per-unit values already written to `G:J`. Add storage for these
five raw values (new `Номенклатура` columns, or a small dedicated keyed range/tab — Codex's call, confirm
against current live headers first) and add them to the write whitelist for **both** owner and Serhiy (Serhiy
is the one entering batch data day-to-day). Same guard rails as every other manual cell.

**3. SKU archive/restore for `Номенклатура`.** Addendum #1 built archive/restore only for `Друк-лог` rows.
`3D-P-013`'s Вироби zone needs the same reversible-status mechanism for SKU rows in `Номенклатура` — mirror
the existing `Друк-лог` archive/restore implementation exactly (same status field pattern, same audit
guarantees). Archived SKUs must stop appearing in active dropdowns/availability calculations but remain
restorable.

**4. Traceable наявність (stock) adjustment.** `3D-P-013` needs a dedicated action for correcting/writing off
stock — not a raw `3dp_write` overwrite of the наявність cell. New action (e.g. `3dp_adjust_stock`) that takes
a delta or new value plus a short reason/note, applies it, and logs old→new plus the note — reuse the existing
`було → стало` history mechanism already built for `Друк-лог` edits if the write path allows it, otherwise log
via `_Аудит_API` with the note included.

**What this addendum does NOT cover** (explicitly out of scope, already handled elsewhere):
- The recommended-RRP generation formula — still pending owner confirmation, tracked in `3D-P-013`.
- `% orders with 3D items` — depends entirely on `3D-P-010`, no second detection mechanism here.
- Fixture/packaging pull — `3D-P-010`'s scope, unrelated to this addendum.

## Acceptance criteria (Addendum #2)

**2026-08-02 clarification: these are API-level checks, not dashboard-UI checks.** `3D-P-013`'s Phase B UI
does not exist yet, so "save via calculator" / "reselect SKU" below must be read as **direct API calls**
(extend `tests/live-addendum2-smoke.ps1`, or add a small companion script, following the same pattern already
used for the negative/no-net-change smoke), not manual clicks in a dashboard that isn't built. Owner should
not need to wait for `3D-P-013` to validate Addendum #2 — use a designated test SKU (reuse the existing
"ПРИКЛАД"/test-row exclusion convention so it doesn't pollute real views) and have the script report clear
before/after values for each check so the owner can eyeball the result from the terminal, same as the prior
smoke tests.

- [ ] `Налаштування` writable via `3dp_write` for the owner token only; Serhiy token rejected with the normal
      `COLUMN_NOT_ALLOWED`-equivalent error.
- [ ] Raw batch-draft values round-trip: save all five via the API, read them back (same and a fresh call),
      confirm exact values returned, unchanged by an unrelated read.
- [ ] `Номенклатура` SKU archive/restore works identically in spirit to `Друк-лог`'s existing mechanism —
      archived SKU disappears from `3dp_skus`/`3dp_overview` by default, appears with `include_archived=true`,
      remains restorable, audit trail intact.
- [ ] Stock adjustment requires a reason/note, is traceable (old→new + note visible in the ledger afterward),
      never a silent direct overwrite, and `Наявність!G` reflects the same delta.
- [ ] All four additions pass the same negative tests as the rest of the API (formula-cell rejection,
      non-whitelist rejection, stale-write rejection) — do not skip these for the new surface just because it's
      small.
- [ ] `ROADMAP_FLOW` entry for `3D-P-008` updated to reflect this addendum.

## Recommended status after Addendum #2

`In progress` until deployed and the four acceptance criteria above pass owner QA → then this addendum is
Done; `3D-P-013` Phase B can then start.

**Status: Done, 2026-08-02.** See `diagnostics/3D-P-008_addendum-2_report_20260802.md` — all four positive QA
checks passed live on `FIG-CHARM-001`.

## Addendum #3 — durable write path for посилання на модель / РРЦ (фактична) / Ціна під викуп, 2026-08-02

**Superseded 2026-08-08 by `3D-P-015`.** Do not implement this addendum separately: its durable
`Номенклатура!Q:S` schema, owner-only API writes, and dashboard wiring ship together with the
3D-P-015 price-model migration, which also owns the required live preflight and audit trail.

Codex found a real gap while building `3D-P-013` (see `diagnostics/3D-P-013_dashboard-tab-restructure_report_20260802.md`,
"Hard boundary" section): the live API fixture has **no durable `Номенклатура` column or whitelisted action**
for посилання на модель, РРЦ (фактична), or Ціна під викуп (Track-2 buyout price). Claude's `3D-P-013`
handoff incorrectly assumed these "already fit the existing whitelist" — that was wrong, and Codex correctly
refused to invent a destination rather than guess a column. This addendum closes that gap.

**Before writing any code:** confirm via a bounded live read of `Номенклатура`'s current headers whether these
three fields exist anywhere as plain (non-whitelisted) columns already, or need to be created from scratch.
Do not assume either way — the schema has changed twice today (Addendum #1 removed/renamed columns, Addendum
#2 added `O:P` technical columns), so a fresh header read is required, not a memory of an earlier version.

**Scope:**

- If the three fields already exist as columns: add them to `OWNER_MANUAL_COLUMNS_3DP` (all three are
  owner-set — Serhiy does not set pricing or the model link) with the same formula-check/optimistic-lock/audit
  guarantees as every other manual cell.
- If any do not exist: add the missing column(s), following the existing pattern (confirm exact letters
  against live headers, do not guess a position), then whitelist as above.
- `3D-P-013`'s Вироби zone (and the corresponding read-only columns in Інформація) can then switch from
  read-only/placeholder to full CRUD for these three fields — that UI wiring is `3D-P-013`'s job once this
  addendum ships, not this addendum's.

**Note on urgency:** these three fields matter for closing `3D-P-002`'s Enable gate (real РРЦ + model link).
If this addendum takes a while, the owner can still edit the underlying Sheet cells directly (outside the
guarded API) to unblock `3D-P-002` in the meantime — slower and manual, but not blocked on this addendum. Do
not treat this addendum as gating `3D-P-002`.

### Acceptance criteria (Addendum #3)

- [ ] Live header read confirms exact current state of these three fields before any write.
- [ ] All three writable via `3dp_write` for the owner token only; Serhiy token rejected.
- [ ] Formula-cell rejection, non-whitelist rejection, and stale-write rejection all still function for the
      new/updated columns.
- [ ] `ROADMAP_FLOW` entry for `3D-P-008` updated.

### Recommended status after Addendum #3

`Not started`. Small, bounded — should not need `xhigh` effort like the base task; propose `medium`/`high`
depending on whether new columns are needed.

---

## Context

The 3D-print business line's data lives in a Google Sheets workbook, confirmed live at:
`https://docs.google.com/spreadsheets/d/1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo/edit`
(tabs: Легенда, Номенклатура, Друк-лог, Продажі, Виплати, Маркетингові_плюшки, Наявність, Аналітика).

Two consumers need this data: the owner's dashboard tab (3D-P-006) and Serhiy's local server (3D-P-007). Both
were originally scoped to read the Sheet independently. Owner decided 2026-07-31 to build **one shared API
first** instead — narrower, cheaper (in tokens/API calls), and it lets both downstream tasks proceed in
parallel instead of each re-solving Sheet access.

Owner also explicitly approved **scoped write access** in this task (previously this whole series was
read-only-only, per the `bs-crm-plan` skill's default rule). This is a real scope escalation — treat every
write path with the same care as CRM-003 (the token-hardcoding incident) and the AGENTS.md CRM risky-zone
rules. Every write must be narrow, whitelisted, and logged.

**Concrete proof this matters, found this session:** `Номенклатура!J3` (Ціна матеріалу, грн/кг) is filled in
the local xlsx (`3d-print/3D-P_nomenclature-tracker_v6_20260731.xlsx`, value `1549.98`) but **blank** in the
live Sheet linked above. This changes `Собівартість Сергія` for that SKU from 3.00 грн to 6.80 грн, which
cascades into every downstream margin/RRP number. This gap must be closed as part of this task's acceptance
criteria, not left for someone to notice later.

Full target-state vision remains `plans/3D-P_handoff-chatgpt_v1_20260728.md` (24 sections). This task is still
a bounded subset — a data-access layer, not the full entity model (`Product`/`Variant`/`CostVersion` etc. are
still explicitly out of scope).

## Scope (what to change)

- **New Apps Script Web App**, bound to the live Sheet above. This is a **new, separate script project** —
  do not add to the existing main-CRM Apps Script.
- Auth: new `BOOSTER_3DP_TOKEN`, stored as an Apps Script Script Property, never in any client file.
- **Read actions** (GET):
  - `action=3dp_get_row&sheet=<name>&sku=<sku>` — single row from a SKU-keyed tab (Номенклатура, Наявність),
    matched by the SKU value actually present in column A (do not hardcode a specific SKU string anywhere —
    SKUs get renamed, e.g. `FIG-CHARM-001` may already be `BR-CHARM-001` by build time).
  - `action=3dp_get_range&sheet=<name>&range=<A1 notation>` — narrow arbitrary-range read, for anything not
    cleanly SKU-keyed (e.g. a specific Виплати period row, a specific Легенда open-question line).
  - `action=3dp_overview` — aggregate summary (SKU count, наявно/продано/видано, нараховано Сергію поточний
    місяць) — used by dashboard cards.
  - `action=3dp_skus` / `3dp_sales` / `3dp_plyushky` / `3dp_payouts` — bounded single-tab reads (one tab each,
    not the whole workbook) for dashboard tables. Every one of these must **filter out illustrative/example
    rows** (`Статус` containing "ПРИКЛАД" or "видалити", or SKU = `ПРИКЛАД-001`) before returning — real
    downstream consumers must never see placeholder data mixed with real rows.
- **Write actions** (POST) — new in this task, apply the safety model below:
  - `action=3dp_write` — body `{sheet, sku_or_row, column, value, expected_current (optional)}`. Server-side:
    1. Reject if `(sheet, column)` is not in a hardcoded whitelist of manual-input ("blue") cells — build this
       whitelist by reading each tab's actual current font-color formatting via Apps Script's
       `getFontColorObjects()` (or equivalent), not by guessing from the column name. Cross-check against the
       Легенда's own documented color convention (blue = manual input, black = formula — do not touch).
    2. Reject if the target cell currently contains a formula (`=...`) — belt-and-suspenders on top of the
       whitelist.
    3. If `expected_current` is provided and doesn't match the live cell value, reject (optimistic-lock
       conflict — prevents blind overwrites of a value someone else just changed).
    4. On success, append a row to a new **system** tab `_Аудит_API` (timestamp, token-identity label passed
       in the request, sheet, cell, old value, new value) — do not expose this tab in any read action, it is
       an internal audit log only.
  - `action=3dp_append_row` — body `{sheet, values: {column: value, ...}}` — appends to the first fully-empty
    row of a tab, only accepting values for whitelisted manual-input columns (same whitelist as `3dp_write`).
    Used e.g. for adding a new SKU or logging a new Продажі/Виплати entry from the dashboard later.
- **One-time reconciliation** (do this using the write action above, as the first real proof it works):
  1. Produce a diff report first: compare every "blue" manual-input cell in the local
     `3d-print/3D-P_nomenclature-tracker_v6_20260731.xlsx` against the same cell in the live Sheet. List every
     difference (cell, local value, live value) — **do not apply anything yet at this point.**
  2. Show the diff report to the owner in chat and get an explicit go-ahead before writing anything — this is
     real business data (pricing, costs), a silent auto-merge is not acceptable even though the mechanism
     exists.
  3. Once approved, apply only the confirmed-newer values via `3dp_write`, logging each to `_Аудит_API`.

## What NOT to touch

- Main CRM Apps Script project/webapp, `BOOSTER_CRM_TOKEN` — completely separate script and credential.
- `Легенда`, `Друк-лог`, and the free-text market-research block inside `Аналітика` — these stay
  human-only/documentation. No read action should return them wholesale, no write action should ever target
  them (they're not part of the manual-input whitelist).
- Any formula cell, in any tab — enforced by the whitelist + the formula-detection check, but call it out
  explicitly: this is the single most important invariant of this task.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant
  feed, Product schema — unrelated, do not touch.
- Do not build any UI in this task (no dashboard tab, no local server) — this is API-only. 3D-P-006 and
  3D-P-007 consume it separately.

## Acceptance criteria

- [ ] Webapp deployed, all five read actions return real, bounded (not whole-document) data, with example
      rows filtered out.
- [ ] `3dp_get_row`/`3dp_get_range` confirmed working for at least one SKU-keyed and one arbitrary-range query.
- [ ] `3dp_write` rejects: (a) a formula-cell target, (b) a non-whitelisted column, (c) a stale
      `expected_current` — three explicit negative tests, not just the happy path.
- [ ] `_Аудит_API` tab logs every successful write with old/new value and the caller's identity label.
- [ ] `BOOSTER_3DP_TOKEN` exists only as a Script Property — confirm via diff/grep that no client file contains it.
- [ ] Reconciliation diff report produced and shown to owner **before** any write; owner's go-ahead obtained;
      confirmed differences (starting with `Номенклатура!J3`) applied and visible live afterward.
- [ ] `ROADMAP_FLOW` entry for `3D-P-008` added in `dashboard/booster-dashboard.html`.

## QA checklist (owner runs after deploy)

- [ ] Review the reconciliation diff report before approving any writes (see above — this is a hard gate, not
      optional).
- [ ] After reconciliation, open the live Sheet and spot-check `Номенклатура!J3` (and any other applied diffs)
      match the local file.
- [ ] Try to write to a formula cell (e.g. `Номенклатура!K3`) via the API directly (e.g. curl/Postman) and
      confirm it's rejected — don't just trust the acceptance-criteria checkbox, verify it once by hand.

## Rollback note

- The Apps Script webapp is a new, separate deployment — disable it or redeploy a prior version; zero effect
  on the main CRM script.
- Any writes made during reconciliation are individually logged in `_Аудит_API` with old values — the owner
  can manually revert specific cells from that log if a reconciliation write turns out to be wrong.
- No OpenCart/database changes in this task.

## Risks

- **CRM risky zone** per `AGENTS.md`, now with real write capability — extra care beyond the original
  read-only design. The whitelist + formula-check + audit log are the core mitigations; do not ship without
  all three.
- The Sheet is being hand-edited multiple times a day by the owner (and possibly Serhiy) — a write race is
  possible. The `expected_current` optimistic-lock check exists specifically for this; use it in every write
  the downstream tasks (006/007) eventually make, not just in this task's reconciliation step.
- Scope-creep risk: this task is the API only. Do not start building the dashboard tab or local server here —
  those are 3D-P-006 and 3D-P-007.

## Recommended status after execution

`In progress` in Notion until owner approves the reconciliation diff and confirms writes landed correctly →
then `Done`. Update `3D-P-006` and `3D-P-007` blockers to "none" once this ships — they can start in parallel.
