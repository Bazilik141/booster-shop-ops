# 3D-P — session continuation brief

**Date:** 2026-08-08, end of session · **For:** the next Claude (chat) session working the 3D-P series
**Purpose:** pick up the next task without re-deriving today's state. Read this first, then
`context-index.md` for the specific task.

---

## 1. What changed today — six tasks closed

| Task | Result |
|---|---|
| `3D-P-014` | CRM-local sync journal live. Hidden tab `_Журнал_3DP_синхронізації` in the **main CRM**, read action `sync_journal`, panel in 3D-друк → Інформація |
| `3D-P-010` | **All three CRM sale-write paths hooked and proven live.** This is the headline |
| `3D-P-022` | SKU trigger aligned with the canonical convention; `ACC-3D-` family now syncs |
| `3D-P-021` | Demo/test data removed from the live workbook — independently confirmed |
| `3D-P-023` | Journal timestamp renders in Kyiv time |
| `3D-P-006` | Closed on owner authorization; superseded by `3D-P-013` |

**The CRM → 3D-P sync worked for the first time on 2026-08-08**, after failing three times (V87
never fired, V89 never fired, third write path was never connected).

Live journal evidence, all from the owner's dashboard panel:

```
updateSaleStatus   MAN-FOP-0006  260  ACC-3D-DITTO-410  noop
apiUpdateSale_     MAN-FOP-0006  260  ACC-3D-DITTO-410  noop
apiAddSale_        MAN-FOP-0007  —    —                 skipped_no_3dp_sku
apiUpdateSale_     MAN-FOP-0005  258  ACC-3D-DITTO-410  warning_negative_stock (created)
apiAddSale_        MAN-FOP-0006  260  ACC-3D-DITTO-410  warning_negative_stock (created)
```

## 2. Verified system state

- **Main CRM Apps Script:** V92 published 2026-08-08 15:23 Kyiv, plus the `3D-P-023` deploy after
  it. Mirror `crm/apps-script/Code.gs` matches. See `crm/apps-script/SOURCE_STATE.md`.
- **3D-P Apps Script:** V7, 2026-08-03 20:55. Unchanged; nothing landed on that side today.
- **3D-P workbook:** demo rows gone. Live SKUs are `FIG-CHARM-001` (legacy) and
  `ACC-3D-DITTO-410`. The latter is the only SKU present in all three catalogues.

**Not verified, do not claim otherwise:**

1. `skipped_api_error` (3D-P API unreachable) and `skipped_not_configured` — owner chose to skip;
   covered by mock tests only.
2. Order-save timing before/after the journal — never measured. The menu path once took 42 s
   before any sync existed.
3. `skipped_sku_shape` — covered by tests only; a thin guard after the trigger was widened.
4. Byte-identity of the CRM mirror after deploy — inferred from a wholesale paste, not re-exported.

## 3. The three-catalogue trap — this cost an hour today, do not rediscover it

A 3D-print SKU must exist in **three separate places** before it can be sold and synced. They are
not connected to each other; the only link is the SKU string.

| # | Where | File | Consequence if missing |
|---|---|---|---|
| 1 | `Товари` | main CRM spreadsheet | product does not exist for accounting |
| 2 | `Майстер_Товарів` + column `Активний` = `так` | **"Booster Shop — Майстер-дашборд автоматизацій"** — a *different* Google file | the SKU never appears in the dashboard's Облік dropdown, so no sale can be entered |
| 3 | `Номенклатура` | 3D-P workbook | the sync has nothing to attach to; stock adjustment fails |

Item 2 is the one everyone forgets: the row can be present and still be invisible because
`Активний` is blank. `apiSkuList_` filters on it.

Also: the dashboard is a local file. After any change to it the owner must hard-refresh
(Ctrl+F5) or he will be looking at a cached older version — this caused two false alarms today.

## 4. Next task — `3D-P-015`, and it needs a revision first

`handoffs/handoff_3D-P-015_price-model-rebuild_20260803.md` is **stale**. It was written on
2026-08-03 and four things have changed since. Do not hand it to an executor as-is; write a
revision block at the top the same way `3D-P-014` rev 2 was done.

What the revision must add:

1. **Column placement decided (2026-08-07, D1):** the three new business columns are appended
   **after** the technical `O`/`P` block, becoming `Q`, `R`, `S`. No shift, no migration, no
   whitelist change. The handoff still presents this as an open question.
2. **Scope grew (2026-08-08, F8):** the fixture *schema* half of `3D-P-019` ships inside this task.
   Concretely: remove the unconditional `+ N` from the `Номенклатура!K` formula (verified live as
   `K = H/I*J + G*B2*B3 + G*B4 + N`, which books an owner-bought fixture as Serhiy's cost), add
   frozen fixture cost and fixture payer fields to the sale row alongside the frozen РРЦ, and keep
   `Номенклатура!N` as a reference price only. Full reasoning in
   `plans/3D-P-019_fixture-payer-model_20260808.md`.
3. **`3D-P-021` is done**, so the Аналітика rebuild no longer risks running over demo rows. The
   handoff's caution about that can be dropped.
4. **The dashboard surrogates** must be replaced as part of this: today the 3D tab shows РРЦ = the
   last post-discount sale price, ціна під викуп = the last plyushky purchase price, and посилання
   на модель = browser localStorage. See `diagnostics/3D-P_gap-register-and-work-plan_20260807.md`
   §3.2.

Recommended executor: Codex, it owns this file family. The recommended-РРЦ generator stays an
explicit placeholder — it is unapproved and there are still 0 real Track-1 sales to compute it from.

## 5. Queue after `3D-P-015`

`3D-P-019` (fixture operational half: `Розхідники` rows per payer, order/write-off forms, Serhiy's
purchase import) → `3D-P-017` (returns) → `3D-P-016` (break-even) → `3D-P-020` (Track-2 cost into
main CRM marketing) → `3D-P-018` (Виробництво zone) → `CRM-004` (main-CRM validation defects) →
`3D-P-013` owner QA gate → `3D-P-007` Serhiy's server re-spec, **last**, per the owner's sequencing.

## 6. Open owner decisions

1. Below-minimum sale behaviour (`3D-P-016`): flag only, require a reason, or block.
2. Where the fixture payer is recorded (`3D-P-019`): fixture reference row, print batch, or SKU.
3. `Фурнітура_довідник` source data — the tab is empty; no fixture work can start without it.
4. Recommended-РРЦ mechanism (`C4` in the gap register).
5. Owner-dashboard production zone (`3D-P-018`): view print-log rows only, or also enter them —
   **answered 2026-08-08: enter and edit**, all four API actions in scope.
6. Serhiy's data scope (`3D-P-007`): the current server README grants more than V1 §3.9/§9.2.
7. Licence cost tracking — never decided; the line is IP-adjacent.

## 7. Working rules that were established the hard way today

- **Verify against the live system before asserting.** Two wrong claims were made from inference
  today — that `FIG-CHARM-001` existed in the CRM catalogue (a live read returned zero
  occurrences) and that the fix was to add a missing row (the row existed; a flag was blank). Both
  were caught by the owner, not by the process. Read the live sheet or the mirrored source first.
- **`crm/apps-script/SOURCE_STATE.md` is checked before any Apps Script task** (`AGENTS.md` →
  "Apps Script mirrors"). Whoever changes a live script refreshes the mirror in the same session.
- **Commit blocks must have their file count computed, not guessed.** The guard caught a stale
  count once, and a four-file block once left HEAD with a half-committed feature — the dashboard
  panel was committed without the CRM code implementing it.
- **The owner is the only deploy gate.** Codex asked for `BOOSTER_3DP_URL` and `BOOSTER_3DP_TOKEN`
  in its own environment; this was refused. Agents do not hold write tokens for live systems.

## 8. Reference documents

- `diagnostics/3D-P_state-audit_20260807.md` — what was deployed vs stale, cross-system drift.
- `diagnostics/3D-P_gap-register-and-work-plan_20260807.md` — agreed-vs-implemented register,
  work packages, owner decisions D1–D7.
- `plans/3D-P-019_fixture-payer-model_20260808.md` — fixture design, decisions F1–F8.
- `diagnostics/3D-P_live-schema-audit_20260803.md` — canonical live workbook schema. **Finding 4
  in it is wrong** and is struck in the gap register; do not schedule work off it.
- `plans/3D-P_sku-naming-convention_20260807.md` — canonical SKU grammar.
- `crm/apps-script/SOURCE_STATE.md` — mirror and deployment state for both Apps Script projects.
