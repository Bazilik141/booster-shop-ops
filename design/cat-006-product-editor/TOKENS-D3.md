# CAT-006 editor — D3 tokens «Вугілля з бурштином»

Scope: the CAT-006 editor pages in the OpenCart admin only. Not a storefront palette and not a change to `design/TOKENS.md`.
Implement as CSS custom properties on the editor root (see `SPEC.md` §1). Names below are the token names to use, prefixed `--bse-`.

## Colour roles

| Token | Hex | Role |
|---|---|---|
| `--bse-shell` | `#111114` | Page ground behind cards inside the editor area. |
| `--bse-paper` | `#1B1B20` | Main surface: content area, cards, side panel, modal, dropdown menus. Also the text colour on accent and on inverse surfaces. |
| `--bse-bg` | `#25252C` | Fills: search field, table header row, thumbnails placeholder, code editor, field-like plates. |
| `--bse-line` | `#36363F` | Borders of cards, fields, buttons, table frame. |
| `--bse-line-2` | `#2C2C34` | Row dividers; fill of the «ВИМКНЕНО» / «Завершено» badges. |
| `--bse-line-3` | `#4E4E5A` | Spinner track, secondary text on the inverse selection bar. Dashed «+ Додати» borders use `--bse-ink-4`. |
| `--bse-ink` | `#FAFAFC` | Headings, prices, values. Also the fill of inverse surfaces: selection bar, tooltips, «АКЦІЯ ДО …» badge, «УЖЕ В ТОВАРІ» badge. |
| `--bse-ink-2` | `#E2E2E8` | Body text, field text. |
| `--bse-ink-3` | `#A6A6B3` | Secondary text, captions, hints, inactive tabs, icons. |
| `--bse-ink-3b` | `#3C3C46` | Border of secondary buttons on the inverse selection bar. |
| `--bse-ink-4` | `#73737F` | Placeholders and decorative icons only (3.25:1 on `--bse-bg`): never text that must be read. |
| `--bse-accent` | `#F2B544` | Primary buttons, links, active nav item, active tab underline, checked checkbox/radio (`accent-color`), switch on, selected thumbnails, step bullets, «ГОЛОВНЕ» badge. |
| `--bse-accent-soft` | `#33291A` | Active filter chips, active sub-nav item, «Діє зараз» badge, drop-zone active fill. |
| `--bse-sel` | `#25221C` | Background of selected table rows. |
| `--bse-danger` | `#FF7A7A` | Errors, invalid field border, out-of-stock quantity, special price text, «Вийти без збереження». |
| `--bse-warn-bg` | `#2C1E14` | Conflict rows, non-dictionary attribute row, «Не діє на сайті» row, unsaved-changes pill. |
| `--bse-warn-fg` | `#FDBA74` | Warning text, CRM star, «CRM» marker text. |
| `--bse-warn-line` | `#9A4F16` | Decorative warning borders: unsaved pill, CRM marker, warning plates. |
| `--bse-ok` | `#5EEAD4` | Success only: «Збережено», «Застосовано», success toast icon. |
| `--bse-ok-bg` | `#12282A` | «Застосовано: N» summary chip. |
| `--bse-accent-hover` | `#F6C561` | Hover of primary (accent-filled) buttons. |
| `--bse-switch-off` | `#74747F` | Switch track when off (non-text contrast ≥ 3:1). |
| `--bse-warn-strong` | `#C2691F` | Border of an unresolved conflict control (non-text contrast ≥ 3:1). |
| `--bse-hover` | `rgba(127,127,127,.10)` | Hover fill of an editable list value and icon-only ghost buttons. |
| `--bse-hover-row` | `rgba(127,127,127,.05)` | Hover fill of a table row. |
| `--bse-hover-line` | `rgba(127,127,127,.35)` | Hover border of an editable list value. |
| `--bse-backdrop` | `rgba(17,24,39,.28)` | Side-panel (offcanvas) backdrop. |
| `--bse-backdrop-modal` | `rgba(17,24,39,.45)` | Modal and dialog backdrop. |
| `--bse-shadow-pop` | `0 8px 24px rgba(0,0,0,.12), 0 2px 6px rgba(0,0,0,.07)` | Popovers, menus, tooltips, toasts, side panel, modal, selection bar. |
| `--bse-shadow-knob` | `0 1px 2px rgba(0,0,0,.25)` | Switch knob. |
| Focus ring | `#3B82F6` | `outline: 2px solid; outline-offset: 2px` on `:focus-visible` for every control. |

Backdrops live outside `.bse` (Bootstrap appends them to `<body>`): define them on `body.bse-page .offcanvas-backdrop` / `.modal-backdrop` with the literal values above (see `SPEC.md` §1).

Rules:
- Accent is the only action colour. Success uses `--bse-ok`, never the accent; errors never use accent or ok.
- Text on an accent fill is `--bse-paper`. Text on an inverse (`--bse-ink`) fill is `--bse-paper`.
- Owner decision 2026-10-28: amber (`#F2B544`) as button/link colour is approved **for this admin editor only**. Storefront rules in `design/BRAND.md` / `RULES.md` (gold never text or button, green only for purchase) are unchanged.
- `color-scheme: dark` on the editor root so native controls (date input, select popup, checkbox) render dark.

## Contrast (WCAG, computed)

| Pair | Ratio |
|---|---|
| `--bse-paper` text on `--bse-accent` (primary button) | 9.37:1 |
| `--bse-accent` text on `--bse-paper` (link) | 9.37:1 |
| `--bse-accent` on `--bse-accent-soft` (active chip) | 7.79:1 |
| `--bse-ink-2` on `--bse-paper` | 13.3:1 |
| `--bse-ink-3` on `--bse-paper` / `--bse-shell` | 7.13:1 / 7.83:1 |
| `--bse-danger` on `--bse-paper` | 6.79:1 |
| `--bse-warn-fg` on `--bse-warn-bg` | 9.56:1 |
| `--bse-ok` on `--bse-paper` | 11.6:1 |
| `--bse-paper` on `--bse-ink` (inverse bar, tooltip) | 16.45:1 |
| `--bse-ink-4` on `--bse-bg` (placeholders only) | 3.25:1 |
| Focus ring `#3B82F6` on `--bse-paper` | 4.66:1 |
| `--bse-paper` text on `--bse-accent-hover` | 10.68:1 |

Non-text contrast (WCAG 1.4.11, target 3:1)

| Element | Ratio | Status |
|---|---|---|
| Switch track off `--bse-switch-off` on `--bse-paper` / `--bse-sel` | 3.71:1 / 3.43:1 | passes |
| Unresolved conflict border `--bse-warn-strong` on `--bse-paper` / `--bse-warn-bg` | 4.36:1 / 4.09:1 | passes |
| Dashed «+ Додати» / empty-attribute border `--bse-ink-4` on `--bse-paper` | 3.67:1 | passes |
| Checked checkbox / radio `--bse-accent` on `--bse-sel` | 8.66:1 | passes |
| Field and card borders `--bse-line` on `--bse-paper` | 1.43:1 | **below 3:1 — open owner decision** (see below) |

Field borders: raising `--bse-line` to ≥ 3:1 (≈ `#70707D`) would also brighten every card, table and divider line and change the approved look. Options for the owner: keep (fields are identified by their labels and placement), or add a separate `--bse-field-line` `#70707D` for inputs/selects only.

## Type

Family: Manrope 400/500/600/700/800 (the admin may load it from Google Fonts or the site's self-hosted copy). Monospace (`ui-monospace, SFMono-Regular, Menlo, Consolas, monospace`) for model/SKU, file keys, SEO slug and the HTML code view.

| Use | Size / weight |
|---|---|
| List page H1 «Товари» | 26 / 800, letter-spacing -0.01em |
| Product title in sticky header | 20 / 800 |
| Panel / modal title | 20 / 800 |
| Section (tile) title | 17 / 700; sub-group title 15 / 700 |
| Body, controls | 14 (buttons 14.5 / 700; primary large buttons 44 px tall) |
| Table text | product name 13.5 / 600; price 14 / 800; quantity 14 / 700; model 12.5 mono |
| Table header | 12 / 700 `--bse-ink-3` |
| Field label | 13 / 600 |
| Hints, captions | 12 / 400 `--bse-ink-3`; cell status lines 11.5 |
| Badges | 10.5 / 700, uppercase, letter-spacing .04em, radius 6 |

## Shape and size

| Item | Value |
|---|---|
| Radii | badge, field, small button, chip-in-field 6; button 8; card / tile / table frame 10; modal 14; pill chips 999 |
| Heights | primary action 44; toolbar button and filter field 40 / 36; search 44; inline cell input 32; segmented 32–42; table row min 64 |
| Switch | 36 × 20, knob 16, knob offset 2 / 18; off track `--bse-switch-off`, on track `--bse-accent` |
| Checkbox | 16 × 16, `accent-color: var(--bse-accent)` |
| Spacing | 4 px grid; card padding 20 × 22; section gap 20; page padding 24 × 28 |
| Shadows | `--bse-shadow-pop` for popovers, menus, toasts, side panel, modal, selection bar; `--bse-shadow-knob` for switch knobs. Cards have none. |
| Icons | Inline stroke SVG, 1.75–2.4 stroke, 12–18 px, `aria-hidden="true"`. No icon font, no emoji. |
