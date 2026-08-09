# 3D-P-019 — fixture payer model: design note

**Date:** 2026-08-08
**Author:** Claude (chat) — design only. No patch, no write to any live system.
**Input:** owner's requirement stated 2026-08-08, plus existing evidence:
`handoffs/handoff_3D-P-010_crm-packaging-cost-pull_20260802.md` (2026-08-02 fixture Addendum),
`diagnostics/3D-P_live-schema-audit_20260803.md`, live `3d-print/apps-script-3dp-api/Code.gs`
(verified 2026-08-08 as byte-identical to the owner's live script export).

---

## 1. The defect this must fix first

`Номенклатура!K` — "Собівартість Сергія (виробнича), грн" — is a formula that already
includes the fixture price. Verified in the live source, `Code.gs` line 1667:

```
K = H/I*J  +  G * Налаштування!B2 * Налаштування!B3  +  G * Налаштування!B4  +  N
    material          electricity                        amortization          fixture
```

There is no payer dimension anywhere. Consequence, with Model B (reimburse Serhiy's cost,
then split profit 50/50):

- a fixture the **owner** bought is booked as **Serhiy's** cost;
- it is therefore reimbursed to Serhiy, who never paid for it;
- and it is subtracted before the 50/50 split, so the split base is wrong too.

The owner has already bought hangers for keychains. This is an active financial defect, not a
theoretical one. It is dormant only because there are still 0 real Track-1 sales.

**Therefore 3D-P-019 is not an "add a field" task. It must remove the unconditional `+ N`
from `K` and replace it with a payer-aware cost path.**

## 2. What already exists — do not rebuild it

The 2026-08-02 Addendum to the 3D-P-010 handoff already decided the storage and the UI shape:

- fixtures live in the main CRM's existing **`Розхідники`** system as rows with
  `Тип = Фурнітура`, each with its own stock, price per unit and consumption history;
- the order-edit form gets a **repeatable multi-line** fixture entry (add/remove rows, each a
  `Розхідник` + `Кількість`) — explicitly not a single dropdown, because one product can carry
  a chain *and* a carabiner, or 2× of the same part;
- each consumed fixture line decrements that `Розхідник`'s stock in the main CRM;
- the 3D-P API already exposes a `3dp_fixtures` read action over `Фурнітура_довідник`.

Open Phase-0 item, still unverified: whether `Розхідники`'s `Тип` field accepts a new
`Фурнітура` value with no code change (likely yes — it may need only data entry).

**So this task is a delta on an existing design, not a new subsystem.**

## 3. Model — separate the three things that are currently fused

| Concept | Where it lives | Who writes it |
|---|---|---|
| **1. Purchase lot** — what was bought, how many, total cost, **who paid**, date | `Розхідники` (main CRM) for owner purchases; 3D-P workbook for Serhiy's purchases | owner / Serhiy |
| **2. Consumption** — which fixture, how many, on which order or write-off | main CRM order-edit form and write-off form | owner |
| **3. Money attribution** — whose cost it was | **derived** from the lot, never typed twice | system |

The single most important rule: **the payer is a property of the purchase lot, not of the sale.**
The owner's sketch has him choosing the fixture at sale time and the payer being "auto-pulled" —
that is exactly right, and it works precisely because the payer is already stored on the lot.
Nobody ever re-states the payer at sale time, so the two can never disagree.

### Unit cost — resolved 2026-08-08 against the live CRM source, and it is simpler than feared

Owner's rule: enter quantity and total cost, unit cost derived (`total ÷ qty`). Correct, and it is
how Serhiy will enter his own purchases.

The follow-up question was what happens when the same fixture exists in two purchases at
different prices. The owner asked for "the same logic as sales in the CRM". Reading the live
mirror (`crm/apps-script/Code.gs`, pulled 2026-08-08) resolves this, and the answer is not the one
either of us assumed:

- **Products** are costed **FIFO** over `Закупки` lots — `getFifoCostBatches_` sorts ascending by
  delivery date, so the **oldest** lot is consumed first. That is the opposite of "newest price".
- **Consumables** (`Розхідники`) have **no lot model at all**. `getAutoConsumableInfo_` reads one
  row per consumable and returns a single current `unitCost`; replenishment adds quantity, and the
  unit cost is simply whatever the row currently says.

Fixtures are consumables, not products. So the CRM-consistent answer is the consumables pattern,
and it happens to match the owner's instinct exactly: **one current unit cost per fixture row; when
a new purchase changes the price, the row's unit cost becomes the new one.** No lot bookkeeping,
no FIFO/LIFO decision, no new machinery in a system that has none.

Historical accuracy is preserved by a mechanism already being built: `3D-P-015` freezes cost into
the sale row as a numeric literal at creation time. So past sales keep the price that was current
when they happened, and a later price change cannot rewrite them.

**Payer is handled by splitting the row, not by adding lot logic.** A fixture bought by both
parties exists as two `Розхідники` rows — e.g. `Підвіс (власник)` and `Підвіс (Сергій)` — each with
its own stock and its own current unit cost. The dropdown therefore shows the payer inherently, the
cost is unambiguous, and the money attribution is exact.

## 4. Serhiy's purchases — reach the CRM by import, not by sync

Owner's sketch: Serhiy enters his purchase in his server, and it "syncs" into a CRM fixtures tab.

Hole: that creates a **second sync direction** (3D-P → main CRM). Today there is exactly one
direction (CRM → 3D-P) and it has failed three times and is still broken. Adding an automatic
reverse direction doubles the failure surface for something that, by the owner's own statement,
happens rarely ("зазвичай це буду я").

**Recommendation:** Serhiy records the purchase in his server → it lands in the 3D-P workbook as
a purchase row → the owner sees it in the 3D-друк tab under "Закупівлі Сергія — потребує
підтвердження" → he confirms, and only then is a `Розхідники` lot created with
`Платник = Сергій`.

This is better than automatic sync for three reasons: it keeps the owner as the gate on anything
that costs him money (consistent with every other authority rule in this project), it produces a
natural review point for the price Serhiy entered, and a missed import is visible as a pending
item rather than as silence.

## 5. Consumption timing

The owner's sketch consumes at **outbound**: order update, and write-off creation. That is
coherent and I recommend keeping it, with one addition he did not mention:

- **Track 2 (marketing freebies) must go through the write-off path**, otherwise a fixture given
  away as a bonus never decrements and its cost never lands anywhere. The write-off form already
  exists in the CRM (`WRITEOFF_TYPES` includes `Промо`, `Подарунок`), so this is a matter of
  adding the same fixture lines to it, not new machinery.

Alternative considered and rejected: consuming at print/assembly time. It would make fixture
stock reflect physical assembly more accurately, but it splits the entry point away from the two
forms the owner already uses, and it breaks when a fixture is attached at packing rather than at
print. The owner attaches at packing, so outbound consumption is the correct model for this shop.

**Stock rule:** if the requested quantity exceeds available stock, apply anyway and surface a
visible warning — the same fail-open principle already locked for 3D-P sales stock on
2026-08-03. Never block an order save on a consumable.

## 6. Money rules — explicit

Per 3D-P sale line:

| Component | Payer | Where it goes |
|---|---|---|
| Print cost (material + electricity + amortization) | Serhiy always | reimbursed to Serhiy, then 50/50 on the remainder |
| Fixture, `Платник = Сергій` | Serhiy | reimbursed to Serhiy as a **separate accrual record**, never merged into the print-cost figure (owner's explicit requirement) |
| Fixture, `Платник = власник` | BoosterShop | a BoosterShop cost (`C_b`), **not** reimbursed; reduces profit before the split |
| Packaging | BoosterShop | `C_b` — already handled by 3D-P-010 |

Track 2 (buyout): the same split applies to what the owner pays Serhiy — he buys the item, and
he reimburses the fixture only if Serhiy paid for it. Combined with decision D3 of 2026-08-07,
the whole Track-2 cost then posts to the main CRM Marketing expense line (3D-P-020).

## 7. Sequencing — fold the schema half into 3D-P-015

`3D-P-015` already rewrites `Номенклатура` columns and freezes cost into the sale row.
`3D-P-019` needs to change the same `K` formula and add the same kind of frozen numeric fields
(`фурнітура — вартість`, `фурнітура — платник`) to the sale row.

**Doing them as two separate migrations means touching deployed write paths twice.**

Recommended split:

- **into `3D-P-015`:** remove `+ N` from `K`; add the frozen fixture cost and payer fields to the
  sale row; keep `Номенклатура!N` only as a default/reference price, not as a cost input.
- **stays in `3D-P-019`:** `Розхідники` lots with a payer column, the owner's multi-line entry in
  the order and write-off forms, Serhiy's purchase entry in his server, and the confirm-import
  step.

`3D-P-019`'s CRM half also inherits the 3D-P-010 blocker: it writes through the same pipe that is
currently broken by the unhooked `updateSaleStatus()` path. It cannot be QA'd before that is
fixed and before `3D-P-014` makes failures visible.

## 8. Holes found in the original sketch — summary

1. Fixture cost is already inside Serhiy's cost formula with no payer → active over-reimbursement.
2. Multiple purchase lots at different prices → costing rule undefined.
3. Automatic 3D-P → CRM sync doubles a failure surface that is already broken in one direction.
4. Track-2 giveaways would never decrement fixture stock unless routed through the write-off form.
5. One product can need two different fixtures, or 2× of one — a single dropdown cannot express
   it (already caught on 2026-08-02, must not be lost again).
6. Serhiy must not be able to record the owner as payer — role integrity, same principle as the
   existing column whitelists.
7. Out-of-stock behaviour must be warn-not-block, consistent with the 2026-08-03 fail-open rule.

## 9. Owner decisions — locked 2026-08-08

| # | Decision |
|---|---|
| F1 | **Unit cost = the current price on the fixture row**, replaced when a newer purchase changes it. No lot/FIFO machinery — fixtures follow the existing `Розхідники` consumables pattern, which has no lots. Historical sales stay correct because `3D-P-015` freezes cost into the sale row at creation. |
| F2 | **Payer is recorded by splitting the fixture into one `Розхідники` row per payer** (`Підвіс (власник)` / `Підвіс (Сергій)`), each with its own stock and unit cost. The payer is therefore visible in the dropdown and never entered twice. |
| F3 | **Serhiy's purchases reach the CRM by owner-confirmed import, not automatic sync.** He records quantity and total cost in his server; the row lands in the 3D-P workbook; the owner confirms it, and only then is a `Розхідники` row created or topped up with `Платник = Сергій`. |
| F4 | **Track-2 giveaways consume fixtures through the existing write-off form.** Accepted for the current Sheets model only — see the NCRM constraint below. |
| F5 | **Serhiy cannot record the owner as payer.** Same integrity principle as the existing column whitelists: his role may only write rows attributed to himself. |
| F6 | **Insufficient fixture stock warns, never blocks** an order save — identical to the 2026-08-03 fail-open rule for 3D-P sale stock. |
| F7 | **Money split as in §6**, with Serhiy's fixture compensation always a separate accrual record from his print cost. |
| F8 | **Schema half ships inside `3D-P-015`** (remove `+ N` from `K`, add frozen fixture cost and payer fields to the sale row). Operational half stays in `3D-P-019`. One migration, not two. |
| F9 | **Locked 2026-08-09. A sale is restricted to fixtures from one payer.** No per-line fixture ledger and no `W = змішано` in the current CRM. `Продажі!W` is part of `CRM_3DP_SALES_FROZEN_HEADERS_`, which V95 enforces by strict `JSON.stringify` equality, so a per-line model would break the sync contract across two deployed scripts at once — for a case that has never occurred (0 real Track-1 sales, 2 fixture rows, both owner-paid). A genuinely mixed order is entered as two sale rows. **Accepted cost:** that split inflates order count and skews average order value in per-order metrics; this is a known distortion, not a defect. Per-line payer accounting is deferred to NCRM. Full rationale and phase-B constraints: `handoffs/handoff_3D-P-019B_single-payer-per-sale_20260809.md`. |

### Carried constraint for NCRM (not for this task)

`F4` is a pragmatic fit to the current Google-Sheets model. When the 3D-P line moves into NCRM,
routing bonus giveaways through a write-off form is the wrong shape: a giveaway is not a loss, it is
a **non-sale usage** with its own compensation rule (the original V1 spec modelled this as
`NonSaleUsage`, §7.11, with a mandatory reason and linked order or campaign). Record this against
the NCRM migration so the shortcut does not silently become the permanent design.

## 10. Verified 2026-08-08 — Finding 9 proven from source

The main CRM mirror confirms what was previously inferred from execution logs:

- `sync3dpSales_` is reached only via the wrapper `sync3dpPackagingCost_`, called from exactly two
  places — `apiAddSale_` and `apiUpdateSale_`, both `doPost` (Web App) paths;
- `updateSaleStatus()` and its alias `updatePaymentStatus()` contain **no 3D-P call of any kind**.

So the fixture flow described here inherits the same defect: fixture lines entered through the
owner's in-Sheet menu form would never reach the 3D-P workbook. `3D-P-010`'s third-path fix and
`3D-P-014`'s journal are hard prerequisites for QA of this task, not merely recommended ordering.
