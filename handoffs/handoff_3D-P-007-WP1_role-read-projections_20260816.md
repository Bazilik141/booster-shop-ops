# Codex Handoff — 3D-P-007 WP1: role-based read projections + Serhiy settings grant

Date: 2026-08-16 | Parent: 3D-P-007 (Serhiy local server)
Codex config: model=Sol · effort=xhigh

---

> ## ⚠ REVISION 2026-08-16 (rev 2) — READ THIS FIRST, DO NOT DEPLOY REV 1
>
> The rev 1 patch was delivered and is **correct against rev 1**, but rev 1 stated
> the wrong boundary. The owner corrected it the same day, before deployment.
> Nothing was published, so this costs a rework, not a migration.
>
> **What changed.** Rev 1 assumed the shop margin on 3D products must be hidden
> from Serhiy. That is incoherent for this relationship: 3D products run on an
> agreed **50/50 net-profit split**, so Serhiy already knows the owner's share —
> it is his own figure. He also sets the RRP himself, so hiding the RRP he
> authors was doubly wrong.
>
> **The corrected boundary, in the owner's own words:** everything **inside the
> 3D line is open**; everything **outside it is closed**. Shop-wide turnover,
> other product lines and other people's earnings are not his business. The 3D
> economics are shared economics.
>
> **Concrete effect on rev 1:**
>
> - `SERHIY_FULL_ECONOMICS_VISIBLE_3DP` defaults to **`true`**, from day one.
> - Previously hidden and now **visible**: `Продажі!G` (`Витрати BoosterShop за
>   од.`), `Продажі!V` (`Вартість фурнітури за од., заморожена`), `Продажі!Y`
>   (`Фурнітура власника за од., заморожена`), and in `Аналітика` — `Маржа
>   BoosterShop, грн`, `Маржа BoosterShop, %`, `Витрати BoosterShop (фурнітура),
>   грн`.
> - **Still hidden — and this is now the entire projection:** order and customer
>   identity. `Продажі!N` (`№ замовлення`), `Продажі!T` (`CRM row number`),
>   `Маркетингові_плюшки!G` (`До замовлення №`) and `!H` (`Примітки`). Nothing
>   financial remains projected.
> - `РРЦ рекомендована` in `Аналітика` stays out only because it is an
>   unimplemented placeholder, not because it is secret. Include it as soon as it
>   holds a real value.
>
> **Keep from rev 1, unchanged:** header-name resolution rather than column
> letters, fail-closed on unprojected read actions, the `Налаштування!B2:B5`
> grant with its append-only journal, the material-price trail, the
> byte-identical owner-response criterion, and the live column mapping already
> recorded in `diagnostics/3D-P-007-WP1_role-read-projections_report_20260816.md`
> — that mapping is verified evidence and does not need redoing.
>
> **Retain the projection machinery even though almost nothing is projected now.**
> The mechanism is what makes the boundary explicit and auditable, and the
> identity fields genuinely need it. Do not delete it and hardcode a passthrough.
>
> The rev 1 body below stands wherever it does not conflict with this block.

---

Justification: risky zone (financial data boundary, deployed Apps Script, feeds
Serhiy's accrual), multi-surface, and architecturally load-bearing — every later
work package in 3D-P-007 is built on the projection contract defined here. Codex
also owns this file from 3D-P-019; a second author would be a parallel-writer
violation.

Owner decisions this handoff implements are recorded in
`plans/3D-P-007_serhiy-data-scope-decision-list_20260816.md` (2026-08-16).

## Context

Serhiy's local server package exists (`3d-print/serhiy-local-server/`) but was
specced before 3D-P-015 (фактична РРЦ, frozen sale economics) and 3D-P-019
(fixture payer, sale mode). Before it can be re-specced or installed, the API
underneath it has to be able to express "Serhiy sees this, not that".

Today it cannot. `tableAction3dp_` backs `3dp_sales`, `3dp_plyushky`,
`3dp_payouts` and `3dp_fixtures` and returns the **whole sheet**. The eight
`assertOwner3dp_` call sites guard writes. `SERHIY_MANUAL_COLUMNS_3DP` and
`OWNER_MANUAL_COLUMNS_3DP` are write allowlists consumed by
`assertCellWriteAllowed3dp_`. There is no read-side role filter anywhere.

Live baseline: 3D-P Web App **V23** (2026-08-13 20:17), CRM **V122**
(2026-08-15 09:47). The repository mirror `3d-print/apps-script-3dp-api/Code.gs`
is byte-identical to the V23 export except a trailing newline.

## Scope (what to change)

All changes in `3d-print/apps-script-3dp-api/Code.gs` plus its test folder.
One patch file, WP1 only.

### 1. Read projection layer, keyed by header name

- Add `SERHIY_READ_PROJECTION_3DP` — a per-sheet **allowlist of header names**
  (not column letters) describing what role `serhiy` may read.
- Apply it inside `tableAction3dp_` and in every other read path that returns
  row data (`3dp_overview`, `3dp_bootstrap`, `3dp_information_bootstrap`,
  `3dp_skus`, `3dp_print_log`, `3dp_get_row`, `3dp_get_range`,
  `3dp_stock_adjustments`, `3dp_batch_draft`). A read action that is not
  explicitly projected must **fail closed** for role `serhiy`, never fall through
  to the full sheet.
- **Match by header string, resolve to index at runtime.** Do not hardcode
  column letters: `Продажі` gained `U`–`AA` during 3D-P-015/019 and will move
  again. A header present in the allowlist but missing from the live sheet is a
  hard error, not a silent skip.
- `3dp_get_range` must reject any range for role `serhiy` that resolves to a
  non-projected column, rather than trimming it silently.

### 2. Visibility sets

Owner role: unchanged, full access. Only role `serhiy` is projected.

**Visible to Serhiy:**

- `Номенклатура` — SKU, name, print time, mass/material inputs, material price,
  `K` Собівартість Сергія (виробнича), and `Q` РРЦ фактична.
- `Друк-лог` — full sheet including defect count, status and history columns.
- `Наявність` — full sheet.
- `Продажі` — date, SKU, quantity, `U` РРЦ на момент продажу, `W` Платник
  фурнітури, `X` Режим CRM, `Z` Фурнітура Сергія за од. (заморожена),
  `AA` Ціна викупу за од. (заморожена), and the per-row Serhiy accrual.
- `Виплати` — full sheet.
- `Маркетингові_плюшки` — SKU, quantity, date.
- `Фурнітура_довідник` — full sheet.
- `Аналітика` — SKU, Назва, Собівартість Сергія, Час друку, `% прибутку Сергію`,
  `РРЦ фактична`, `Нараховано Сергію, грн`, `Прибуток Сергію/год друку, грн`.
- `Налаштування` — B2–B5 (see §3).
- `_Чернетки_партій` — his own drafts.

**Hidden from Serhiy:**

- `Продажі` — `V` Вартість фурнітури за од. (заморожена, сукупна),
  `Y` Фурнітура власника за од. (заморожена), `CRM row number`, any order
  identifier, and packaging cost.
- `Аналітика` — `Маржа BoosterShop, грн`, `Маржа BoosterShop, %`,
  `Витрати BoosterShop (фурнітура), грн`, `РРЦ рекомендована`.
- `Маркетингові_плюшки` — any order or customer linkage column.
- Any customer-identifying field anywhere. Non-negotiable.

⚠ Resolve `Продажі` and `Маркетингові_плюшки` column identity **by reading live
header row 1**, and record the resolved mapping in your report. Do not infer
which column holds packaging cost or the order link from this handoff — it is not
stated here because it is not proven.

### 3. Settings: grant Serhiy write on B2–B5, with a journal

- Add `'Налаштування': ['B']` to `SERHIY_MANUAL_COLUMNS_3DP`, **bounded to rows
  2–5 only**. `Налаштування!B1` and anything below B5 stay owner-only.
- The four parameters are printer power (kW), electricity price (UAH/kWh),
  printer amortisation (UAH/h) and planned defect share.
- Every write to `Налаштування!B2:B5` by **either** role appends a row to a new
  append-only journal: timestamp (Kyiv), actor role, parameter name, old value,
  new value. Never overwrite or delete journal rows.
- Mirror the existing history convention already used for `Друк-лог` (`K`) and
  `Номенклатура` (`P`) rather than inventing a third pattern.
- Expose the journal through a read action for the owner dashboard. Role
  `serhiy` may read his own entries; that is a projection like any other.
- Reject non-numeric input and values outside sane bounds; a rejected write
  changes nothing and writes no journal row.

⚠ **Why the journal is mandatory.** Amortisation and planned defect both multiply
into `Номенклатура!K`, which is the base of Serhiy's accrual. Granting write
without a trail means he can raise his own payout with no visible trace. The
owner accepted free editing **on condition** of the journal (decision 2026-08-16,
variant A). Do not ship the grant without it.

### 4. Material price change trail

- `Номенклатура` material-price edits already sit inside Serhiy's write
  allowlist. Extend the same append-only trail: who, when, old → new.
- Reuse the settings journal or the existing `Номенклатура!P` history —
  whichever is the smaller change. State which you chose and why.

### 5. Visibility toggle

- Add one named constant, `SERHIY_FULL_ECONOMICS_VISIBLE_3DP`, default `false`.
- When `true`, role `serhiy` reads exactly what the owner reads and the
  projection layer is bypassed. One constant, one line to flip.
- The constant must be the **only** place the answer lives. No duplicate flag in
  the local server, no second switch in the UI.

## What NOT to touch

- `crm/apps-script/Code.gs` — main CRM. Nothing in WP1 needs it, and it is the
  live V122 baseline.
- `dashboard/booster-dashboard.html` — the owner's settings block and the journal
  panel are WP2, after this deploys.
- `3d-print/serhiy-local-server/` — the re-spec is WP2 and gets its own handoff.
  `lib/calculator.mjs` was audited 2026-08-07 and implements the locked cost
  formula correctly; it is not to be rewritten.
- `Продажі` column set — do not add, remove or reorder columns.
  `CRM_3DP_SALES_FROZEN_HEADERS_` is enforced by strict `JSON.stringify` equality
  in the CRM, so a column-set change breaks sync in two deployed scripts at once.
- Owner-role behaviour — no owner read path may change shape. The dashboard is
  built on the current responses.
- Any token. `BOOSTER_3DP_SERHIY_TOKEN` and `BOOSTER_3DP_TOKEN` stay in Script
  Properties and appear in no file, report, diff or message.

## Acceptance criteria

- [ ] Under an owner token, every existing read action returns a byte-identical
      response shape to V23. Prove it, do not assert it.
- [ ] Under a Serhiy token, each hidden field is **absent from the payload**, not
      blanked or nulled.
- [ ] A read action with no projection entry fails closed for role `serhiy`.
- [ ] `3dp_get_range` rejects a range covering a non-projected column for
      role `serhiy`.
- [ ] A header in the allowlist that is missing live raises a clear error naming
      the sheet and header.
- [ ] Serhiy can write `Налаштування!B2:B5`; `B1` and rows below `B5` are
      rejected.
- [ ] Every accepted settings write appends exactly one journal row with old and
      new value; a rejected write appends none.
- [ ] Material-price edits leave an equivalent trail.
- [ ] Flipping `SERHIY_FULL_ECONOMICS_VISIBLE_3DP` to `true` makes the Serhiy
      payload equal to the owner payload, with no other edit.
- [ ] Local regression suites pass, including the existing 3D-P API, fixture
      usage, phase A setup and sync journal tests.
- [ ] Report records the live-resolved column mapping for `Продажі` and
      `Маркетингові_плюшки`.

## QA checklist (owner runs after deploy)

There is no staging. The owner publishes a new 3D-P Web App version and runs
these against production.

- [ ] Read live `Налаштування!B2:B5` **before** anything else and record the four
      actual values. The repository note from 2026-08-08 says defect `0.1` and
      power `0.15` kW; the owner stated `0.08` and `0.11` on 2026-08-16. These
      disagree — the live sheet decides.
- [ ] Open the dashboard, `Ctrl+F5`, confirm every 3D-друк zone renders exactly
      as before. Any owner-side change is a regression.
- [ ] Call one read action with the owner token and confirm the margin fields are
      present.
- [ ] Call the same action with the Serhiy token and confirm they are absent.
- [ ] Change one settings parameter as owner, confirm one journal row with the
      correct old and new value.
- [ ] Attempt a settings write outside B2–B5 and confirm rejection.
- [ ] `integrity_check` returns `clean=true`, `problems=[]`.

## Risks

Risky zone: CRM-adjacent, financial, deployed Apps Script, production-direct.

- **Blast radius.** The projection layer sits in the shared read path. A mistake
  there breaks the owner's dashboard, not just Serhiy's view — hence the
  byte-identical owner-response criterion.
- **Rollback.** Republish V23 as a new version, then owner hard-refresh. WP1
  performs no migration and no data repair, so rollback is code-only. Preserve
  the journal sheet on rollback; never delete it.
- **Silent-trim failure mode.** The dangerous outcome is a projection that
  quietly drops a column the owner still needs. Fail closed and raise, never trim
  quietly.
- **The boundary is partial by design.** Serhiy sees фактична РРЦ (owner decision
  4.3). With RRP and his own cost he can approximate the shop margin by
  subtraction. Projections protect the exact figure and the owner's own outlays,
  not the existence of a margin. Do not describe this as full concealment
  anywhere in code comments or the report.

## Delivery

Patch file into `patches/`, report into `diagnostics/`. No commit, no push, no
Apps Script publication, no live Sheet write by the executor. The owner deploys.

Sequenced after WP1: **WP2** — Serhiy local-server re-spec against the projected
contract plus the settings block behind a gear toggle, mirroring the owner
dashboard pattern. **WP3** — install on Serhiy's machine, then joint owner QA,
which is also the outstanding closure gate for 3D-P-015.
