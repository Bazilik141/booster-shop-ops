# CAT-006 — Booster product admin editor: approved design (v0.1 layout + D3 colours)

Status: **approved by the owner on 2026-10-28.** Layout v0.1, colour scheme D3 «Вугілля з бурштином».
Roadmap ID: CAT-006 (Notion `4076bf20-bdb4-81c8-9f37-e0ee44071858`).
Requirements: `plans/CAT-006_product-admin-editor_requirements_20261028.md` (data rules, diagnosis items, owner decisions D1/D2).
Design brief: `handoffs/handoff_CAT-006_claude-design-brief_20261028.md`.

## Files

| Path | What it is | Use it for |
|---|---|---|
| `SPEC.md` | Executor-facing design spec: layout metrics, components, behaviour, states, copy rules, scoping. | The written contract. Read first. |
| `TOKENS-D3.md` | D3 palette by role, contrast checks, type and shape scale. | Every colour, size and radius. |
| `preview/*.html` | Static HTML snapshots of every screen and state, rendered from the canvas with the exact computed inline styles (hex colours). Open in a browser; needs internet only for the Manrope font. Links between screens work; controls are inert. | Pixel reference. Measure here; inspect with DevTools. |
| `screens/*.png` | Screenshots of the same 15 snapshots at 1440 px (full page) plus two 1920 px checks. | Quick visual comparison and QA. |
| `source/*.dc.html` | Claude Design sources (interactive prototype). They need the Claude Design runtime and do not render on their own. | Rebuilding or changing the canvas only. |

Live canvas (interactive, private to the owner): https://claude.ai/code/artifact/8a3151f1-b86f-4d33-af86-7b1f64a28334 — page «Затверджено: v0.1 + D3».

## Snapshot index

| # | File | Screen / state |
|---|---|---|
| 01 | `01-list` | Product list, default (6 selected, filter chip, CRM markers, specials) |
| 02 | `02-list-empty-search` | Empty search result |
| 03 | `03-list-loading` | List loading (skeleton) |
| 04 | `04-list-inline-save-error` | Inline save error in a price cell |
| 05 | `05-list-crm-refresh-in-progress` | «Оновити з CRM» in progress |
| 06 | `06-list-crm-refresh-failed` | CRM refresh failed banner |
| 07 | `07-bulk-special-form` | Bulk panel, special price, step 1 |
| 08 | `08-bulk-special-preview-conflicts` | Preview with two overlapping-special conflicts |
| 09 | `09-bulk-result-partial-failure` | Result: applied / skipped / error |
| 10 | `10-bulk-tiers-duplicate-warning` | Quantity tiers preview with a duplicate-tier warning |
| 11 | `11-product-page` | Single product page, all sections expanded |
| 12 | `12-product-unsaved-dialog` | Leave with unsaved changes dialog |
| 13 | `13-variant-family` | Variant family view |
| 14 | `14-image-picker` | Image picker modal |
| 15 | `15-image-picker-upload` | Picker: drag-over, upload progress, upload error |

## Precedence inside this package

1. `SPEC.md` and `TOKENS-D3.md` (written rules).
2. `preview/*.html` (exact values: sizes, spacing, colours, copy).
3. `screens/*.png`.
4. `source/*.dc.html` and the live canvas.

The requirements plan wins over all of these on data behaviour (what is saved, how specials and tiers are stored).

## Not part of the approved design

- The grey/dark OpenCart header and left menu in the snapshots are a placeholder. The real admin shell stays stock (see `SPEC.md` §1).
- Product names, prices, quantities, file names and attribute values are sample data, not facts.
- The canvas pages «Кольорові схеми» and «Темні варіанти» are rejected explorations (light canon, A, B, C, D1, D2).
