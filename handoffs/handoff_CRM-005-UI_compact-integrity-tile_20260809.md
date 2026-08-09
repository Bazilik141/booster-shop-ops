# CRM-005-UI — compact integrity tile on Огляд

Date: 2026-08-09 · Task: `CRM-005` (UI addendum) · Executor: **Codex**
Ships in the **same dashboard delivery** as the `3D-P-025` B1/B2 correction — see
`diagnostics/CRM-005_3D-P-025_3D-P-019A_claude-review_20260809.md`. One file, one upload.

## 1. Why

The integrity check currently occupies a full-width `section` directly under the KPI row on Огляд.
It is a rarely-used operational control that visually outranks the daily numbers. The owner asked
for it to become one compact tile in the existing KPI grid, which has a free slot at the end of the
row.

Owner decision 2026-08-09: **click-to-run only.** Do not call `integrity_check` on page load. The
action's runtime has never been measured (20+ individual `getFormula()` round trips against
`Майстер_Товарів`, plus four full-sheet `getValues()`/`getFormulas()` pairs), and Огляд must not pay
that cost on every open.

## 2. What to change

`dashboard/booster-dashboard.html` only. No Apps Script change. The `integrity_check` action, its
response shape and `crmIntegrityRender()`'s problem-table markup all stay as they are.

### 2.1 Remove the full-width section

Delete the `<div class="section" id="crmIntegritySection">…</div>` block that currently sits between
`#summaryCards` and the `.grid2` row.

### 2.2 Add the tile to the KPI grid

`.cards` is `grid-template-columns: repeat(auto-fill, minmax(168px, 1fr))`, so an appended card lands
in the free slot without any CSS change. Follow the existing `repeatRateCard` pattern verbatim —
remove-then-append, so a data refresh cannot duplicate it:

```js
const existing = document.getElementById('crmIntegrityCard');
if (existing) existing.remove();
const card = document.createElement('div');
card.id = 'crmIntegrityCard';
card.className = 'card';           // + ' c-green' only in the clean state
document.getElementById('summaryCards').appendChild(card);
```

Use the established class names: `card-label`, `card-value`, `card-sub`.

**Placement matters.** `repeatRateCard` is appended inside the `monthly_summary` try block. The
integrity tile must **not** be — it has no dependency on that request and must still appear when
`monthly_summary` fails. Append it after the summary cards are rendered, in its own path.

### 2.3 Tile states

| State | `card-label` | `card-value` | `card-sub` | Class |
|---|---|---|---|---|
| Idle (default) | `ЦІЛІСНІСТЬ CRM` | `Перевірити` | `read-only · без вивантаження таблиць` | `card` |
| Running | `ЦІЛІСНІСТЬ CRM` | spinner | `Перевірка…` | `card` |
| Clean | `ЦІЛІСНІСТЬ CRM` | `✓ OK` | `Проблем не знайдено · HH:MM` | `card c-green` |
| Problems | `ЦІЛІСНІСТЬ CRM` | `N` | `проблем(и) · деталі нижче · HH:MM` | `card` + red `card-value` |
| Error | `ЦІЛІСНІСТЬ CRM` | `—` | the error text, muted | `card` |

The whole tile is the click target (`cursor:pointer`, `role="button"`, `tabindex="0"`, Enter/Space
handled). Re-clicking after a result re-runs the check. Block re-entry while a run is in flight.

Timestamp is local `HH:MM`, same convention as the existing `Оновлено о 11:49` sub-header.

### 2.4 Detail area

Add `<div id="crmIntegrityDetail"></div>` immediately after `#summaryCards`, `display:none` by
default.

- Clean or error → stays hidden and is emptied.
- Problems → reveal it and render the existing table (`Вкладка / Рядки / Код / Деталь`), plus the
  existing `truncated` and `coverage.rrp_mismatch_3dp` notes. Reuse the current `crmIntegrityRender()`
  markup; only the target element and the empty-state branch change.

Give it a plain heading (`Перевірка цілісності CRM`) so the table is not orphaned once revealed.

### 2.5 State survives a refresh

Hold the last result in a module-level variable. When the cards are re-rendered by a data refresh,
re-apply that state to the recreated tile and re-render the detail area. A refresh must not silently
reset a red tile to idle — that would hide a known problem.

## 3. Do not touch

- `apiIntegrityCheck_` and every `crmIntegrity*` helper in `crm/apps-script/Code.gs`. This is a
  dashboard-only change.
- The `integrity_check` response contract.
- The problem-row table markup and its `threeDpEsc()` escaping. All server strings stay escaped.
- Any other card in `#summaryCards`.
- The `3D-P-025` stock panel in the same file — that is a separate work package in the same delivery.

## 4. Acceptance criteria

- [ ] No `integrity_check` request is issued on page load. Verify with an empty network log after
      opening Огляд.
- [ ] The tile occupies the free KPI slot and the row reflows without a CSS change.
- [ ] Clean run → green tile, detail area hidden.
- [ ] Problem run → count on the tile, detail area revealed with sheet + rows + code + detail intact.
- [ ] Failed request → the tile shows the error and the detail area stays hidden; no unhandled
      rejection in the console.
- [ ] A data refresh neither duplicates the tile nor resets an existing result.
- [ ] Keyboard: the tile is reachable by Tab and fires on Enter and Space.
- [ ] `monthly_summary` failing does not remove the tile.

## 5. Rollback

Dashboard-only. Revert `dashboard/booster-dashboard.html` and hard-refresh. Nothing is written
anywhere by this change. Note the coupling already recorded in the review: that revert also reverts
the `3D-P-025` fix shipping in the same file.

## 6. Owner QA

Desktop only — the dashboard is PC-only.

1. Open Огляд. The tile reads `Перевірити`; the page loads at its usual speed.
2. Click it. It should report the `РРЦ` rows `71-75` as `price_without_sku`, with the table appearing
   below the KPI row.
3. Reload the page. The tile returns to `Перевірити` on a fresh load; within one session a data
   refresh keeps the last result.

## 7. Recommended status

`CRM-005` stays `In progress` until the owner has run the check live from the new tile.
Claude (chat) is the Notion writer; the executor writes no Notion property.
