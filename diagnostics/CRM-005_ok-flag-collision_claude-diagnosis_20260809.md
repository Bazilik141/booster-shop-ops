# CRM-005 — `ok:false` collides with the dashboard transport contract

Date: 2026-08-09 · Author: Claude (chat) · Executor to fix: **Codex**
Severity: **blocking.** The integrity check is unusable in exactly the case it exists for.
Owner symptom: the tile reads `Перевірка недоступна: API error` on the live deployment.

## Diagnosis

The check ran successfully. It reached the sheets, found problems, and the dashboard then discarded
the result as a transport failure.

`crmIntegrityFinalize_` overloads `ok` as a verdict:

```js
report.ok = report.problems.length === 0;
```

The dashboard's generic CRM caller treats `ok` as a transport success flag:

```js
const d = await r.json();
if (!d.ok) throw new Error(d.error || 'API error');
```

So a completed run that finds problems returns `{ ok: false, …, problems: [...] }` with **no `error`
field**, and `call()` throws the literal string `API error`. That is precisely the observed message.

Every other `ok:false` producer in the script carries an `error` string — `bad token`,
`unknown action: …`, `crm busy, retry later`, and the `doGet` catch. `crmIntegrityFinalize_` is the
only path that emits `ok:false` without one, which is why the message degrades to the fallback.

**Consequence:** a clean CRM shows `✓ OK`; a dirty CRM — the only case worth running the check for —
reports a fake API failure and hides the findings. The failure is silent in the worst direction.

## Why both test layers missed it

- `crm/apps-script/tests/integrity-check.test.mjs` calls `apiIntegrityCheck_` **directly** and reads
  `result.problems`. It never crosses `call()`.
- `tests/crm-005-integrity-tile.test.mjs` **stubs** `call`, returning result objects straight to the
  tile. It never exercises the real `if (!d.ok) throw` line.

Each layer is correct in isolation; the defect lives in the seam between them, and nothing tested the
seam. Same class of gap as B1 — a green suite that never touched the real response contract.

This was also present in the first delivery and was not caught in either of my reviews. Recording
that so the pattern is visible: reviewing a producer and a consumer separately does not review the
contract between them.

## Required fix

**Do not weaken `call()`.** `ok` means "the request succeeded" for every other action; changing that
contract for one action would put the burden on every future caller.

Server side, `crm/apps-script/Code.gs`:

1. A completed run returns `ok: true`, whether or not problems were found. `ok: false` stays reserved
   for a genuine failure — bad token, unknown action, thrown exception.
2. Carry the verdict in its own field, e.g. `clean: report.problems.length === 0`. Do not reuse `ok`.
3. Leave `problems`, `truncated`, `coverage` and `elapsed_ms` unchanged.

Dashboard side:

4. `runCrmIntegrityCheck()` selects its state from `problems.length` (or the new `clean` field), not
   from `ok`. The `catch` branch then means only a real failure.

## Test that must accompany the fix

The unit and tile tests stay, but neither closes the gap. Add one that exercises the transport rule:

- [ ] Feed the **actual** `apiIntegrityCheck_` return value for a workbook with injected defects
      through the real `call()` logic (`if (!d.ok) throw new Error(d.error || 'API error')`) and
      assert it does **not** throw and that the problems arrive intact.
- [ ] Same for a clean workbook.
- [ ] Assert a genuine failure shape (`{ ok:false, error:'bad token' }`) still throws with its own
      message, so the fix does not blind the error path.

Extracting the `call()` body from the dashboard by the same brace-matching approach already used in
`tests/3d-p-025-stock-actual-count.test.mjs` is the cheapest way to test the real thing rather than a
copy of it.

## Blast radius

Read-only. No workbook data is written or at risk. `Code.gs` remains additive against V95 — the
change is inside code added by this task, so nothing deployed before today is affected.

## Note for the owner's next run

Once fixed, the first live click should list `РРЦ` rows `71-75` as `price_without_sku`. The current
`API error` already tells us the check reached the sheets and found something — a check that could
not reach them would have failed with a different message.

## Sequencing

The dashboard is again a shared-rollback file across `3D-P-025` and `CRM-005`. `3D-P-025` owner QA is
still unrun and is not blocked by this defect — the two features are independent at runtime, but they
ship in the same file, so the fixed dashboard should be uploaded once and both QA'd after.
