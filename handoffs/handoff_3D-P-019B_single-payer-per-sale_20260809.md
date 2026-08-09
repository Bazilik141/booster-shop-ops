# 3D-P-019 phase B — owner decision F9: one fixture payer per sale

Date: 2026-08-09 · Task: `3D-P-019` (phase B) · Executor: **Codex**
Status: decision recorded, implementation **not yet authorised**. Phase A ships first.

## 1. The question phase A raised

`Продажі!W` (`Платник фурнітури`) holds exactly one payer. Decision **F2** in
`plans/3D-P-019_fixture-payer-model_20260808.md` records the payer by splitting a fixture into one
`Розхідники` row per payer (`Підвіс (власник)` / `Підвіс (Сергій)`), each with its own stock and unit
cost. Nothing prevents a single order from consuming both rows — and then `W` cannot represent the
truth.

Codex correctly refused to guess this accounting and stopped. Two options were put to the owner:

- restrict a sale to fixtures from a single payer; or
- build a per-line fixture ledger with explicit `W = змішано` and accrual semantics.

## 2. Decision F9 — owner, 2026-08-09

> **A sale is restricted to fixtures from one payer.** No per-line fixture ledger and no
> `W = змішано` in the current CRM. Per-line payer accounting is deferred to NCRM.

### Why this and not the ledger

1. **`W` is under a strict header contract.** `CRM_3DP_SALES_FROZEN_HEADERS_` in the main CRM script
   is `['CRM row number', 'РРЦ на момент продажу, грн', 'Вартість фурнітури за од., грн (заморожена)',
   'Платник фурнітури']`, and V95 compares it with `JSON.stringify(headers) !== JSON.stringify(...)`.
   A per-line model changes that header set, which breaks the sync contract across **two deployed
   scripts at once** until both are updated in lockstep. That is the largest blast radius available
   in the current CRM, taken on for a case that has never occurred.
2. **There is nothing to model from.** 0 real Track-1 sales. 2 fixture rows, both owner-paid. A
   mixed-payer order is hypothetical, and §6 of the design note plus the standing owner decision
   forbid inventing accounting without data.
3. **A restriction is testable and reversible.** A rejected basket is one validation rule with a
   clear message. A ledger is a new accounting subsystem that must then be reconciled against the
   50/50 split and Serhiy's separate fixture accrual (`F7`).
4. **There is a zero-code escape hatch.** A genuinely mixed order is entered as two sale rows.

### Accepted cost of the decision — record it, do not rediscover it

Splitting one physical order into two sale rows **inflates the order count and skews average order
value** in any per-order metric (`channel_stats`, `monthly_summary`, LTV). At current volumes this is
noise, but it is a real distortion and must not be reported later as a defect. If mixed orders ever
become common, that is the signal to revisit F9 — in NCRM, not here.

## 3. What phase B must implement from this

Bounded, and only after phase A is deployed and verified:

1. **Validation at fixture selection.** When an order or write-off already contains a fixture
   attributed to one payer, selecting a fixture attributed to the other payer is rejected.
2. **The message must name both sides.** Not "змішаний платник заборонено" but which fixture is
   already in the basket, which payer it belongs to, and which fixture was just rejected. The owner
   must be able to act on the message without opening the sheet.
3. **`W` is written from the single payer of the fixtures actually consumed**, never defaulted,
   never inferred from the SKU, never left blank when a fixture was consumed.
4. **A sale with no fixtures leaves `W` empty** and must not be caught by the validation.
5. **`F6` still governs stock.** Insufficient fixture stock warns and does not block. F9 restricts
   *whose* fixtures may be combined, not *whether* a low-stock sale may be saved. Do not let the new
   rule turn a warn into a block.

## 4. Do not touch

- `CRM_3DP_SALES_FROZEN_HEADERS_` and the `Продажі` column set. F9 exists precisely so this contract
  stays frozen.
- The 50/50 split base and Serhiy's fixture accrual (`F7`). F9 changes what may be combined in one
  sale, not how money is divided.
- `F2` (per-payer `Розхідники` rows). F9 sits on top of it; it does not replace it.
- Phase A's category rename and the `Платник` column append. Separate, earlier work package.

## 5. Acceptance criteria

- [ ] A basket mixing `(власник)` and `(Сергій)` fixtures is rejected, with both sides named.
- [ ] A basket with several fixtures from the same payer saves normally.
- [ ] A sale with no fixtures saves with `W` empty and no validation error.
- [ ] `W` matches the payer of the fixtures actually consumed, proven on a saved row.
- [ ] Low fixture stock still warns and still saves (`F6` unbroken).
- [ ] `CRM_3DP_SALES_FROZEN_HEADERS_` is byte-identical before and after.

## 6. Rollback

The validation is additive logic on the order/write-off path. Removing it restores the previous
behaviour, which is "no rule at all" — mixed baskets save with an arbitrary `W`. That is the current
state, so rollback is safe but leaves the original ambiguity; it is not a resting place.

Order save is a deployed CRM write path — risky zone. Rollback plan and a focused smoke test are
mandatory before this ships.

## 7. Gate

Not executable yet. Order of operations:

1. `3D-P-025` fix returns and the dashboard ships.
2. `CRM-005` deploys; its integrity check is run and its bounded output kept as the baseline.
3. `3D-P-019` phase A setup action runs, with the integrity check before and after.
4. Only then is phase B specced into an implementation handoff.

## 8. Also record

Append **F9** to the decision table in `plans/3D-P-019_fixture-payer-model_20260808.md` so the design
note does not contradict this handoff.
