# CRM-005 UI follow-up — tile position and copy-result button

Date: 2026-08-09 · Task: `CRM-005` (UI addendum 2) · Executor: **Codex**
Author: Claude (chat) · Dashboard-only. Independent of the `CRM-006` Sheets passes.

Two small changes to `dashboard/booster-dashboard.html`. No Apps Script change for WP1; WP2 needs
none either — the payload already exists client-side.

---

## WP1 — the integrity tile must always be the last card

**Owner-reported defect.** On page load the tile sits in position 7 and `Повторні покупки` is last.
After the tile is clicked they swap, and the tile becomes last. The order is unstable.

**Cause.** Both cards are appended dynamically and the winner is decided by which request resolves
first. `renderCrmIntegrityTile()` runs immediately after the summary block, while `repeatRateCard` is
appended later, inside the `monthly_summary` block — so on load `Повторні покупки` lands after the
tile. Clicking the tile triggers the remove-then-append path, which moves it to the end of the DOM.

**Fix — do it declaratively, not by reordering appends.** Give the card a CSS grid order:

```css
#crmIntegrityCard { order: 999; }
```

`.cards` is a grid, and grid items honour `order` regardless of DOM insertion sequence. Every other
card defaults to `order: 0`, so the tile is last on first paint and stays last no matter which
request resolves when, and no matter how many cards are added later.

Do **not** fix this by moving the `renderCrmIntegrityTile()` call after the `monthly_summary` block.
That would make the tile disappear whenever `monthly_summary` fails — the exact regression the first
UI handoff required be avoided.

Keep the existing remove-then-append idempotency and the state-survives-refresh behaviour untouched.

## WP2 — "Скопіювати результат" button

**Why.** The `OPS-CRMINTEGRITY` rule requires recording the check's bounded output before and after
every structural change, and the executor's handoffs require the raw result. The dashboard renders a
table and nothing else, so the owner has had to send screenshots three times in one day, and every
recorded baseline so far is a transcription rather than a payload. That is a real evidence-quality
problem, not a convenience issue.

**Behaviour.**

- A small secondary button, shown only when a result exists (any status other than `idle`/`running`).
  Place it in the detail block header; when the run is clean and the detail block is hidden, place it
  in the tile's sub-line or reveal a minimal detail block containing just the button.
- Clicking copies `JSON.stringify(crmIntegrityState.result, null, 2)` to the clipboard via
  `navigator.clipboard.writeText`, with a `document.execCommand('copy')` fallback on a temporary
  `textarea` for non-secure contexts.
- Confirm visually — swap the label to `Скопійовано ✓` for ~2 s, then restore. Do not use `alert`.
- On failure, say so in the button label rather than failing silently.

**Do not** add a download-file path, a server round trip, or a second API call. The payload is already
in `crmIntegrityState.result`; this is a clipboard write and nothing more.

## Do not touch

- `apiIntegrityCheck_` and the `integrity_check` response contract.
- The click-to-run rule. No request on page load, still exactly one call site.
- Any other card in `#summaryCards`.
- The `3D-P-025` stock panel.

## Acceptance criteria

- [ ] On first paint, before any click, the integrity tile is the **last** card in the row.
- [ ] It is still last after a click, after a data refresh, and when `monthly_summary` fails.
- [ ] The tile still renders when `monthly_summary` fails.
- [ ] Copy button appears only when a result exists, and copies the full result JSON including
      `problems`, `truncated`, `coverage` and `elapsed_ms`.
- [ ] Copy works from the clean state as well as the problems state.
- [ ] Still zero `integrity_check` requests on page load; still one call site in the file.
- [ ] Existing tile tests continue to pass, extended to cover the ordering rule and the copy path.

## Rollback

Dashboard-only. Revert the file and hard-refresh. Note the standing coupling: this file also carries
`3D-P-025` and the original tile, so a revert takes those with it.

## Note

WP2 is included on Claude's recommendation because the missing raw output has now blocked the
workflow three times today. If the owner does not want the button, drop WP2 and ship WP1 alone — they
are independent.
