# Token registry

Source of truth: `tokens/boostershop-ds.css`, a copy of the live file fetched 2026-10-09 (cache token `ui-pcard-badge-20261006`; same file in `live-snapshots/20261009_design-canon/`). Never hardcode hex in templates; use `var(--bs-*)`. Re-fetch the live file before any patch: this copy goes stale with the next CSS deploy.

## Live `:root` (2026-10-09)
| Token | Value | Use |
|---|---|---|
| `--bs-paper` | `#FFFFFF` | cards, page body |
| `--bs-bg` | `#F7F7F5` | fields, hover fills, media/logo panels |
| `--bs-line` / `--bs-line-2` | `#E5E7EB` / `#EEF0F2` | borders / dividers, hover, disabled fill |
| `--bs-ink` … `--bs-ink-4` | `#111827` `#1F2937` `#6B7280` `#9CA3AF` | text levels |
| `--bs-blue` / `--bs-blue-soft` | `#1E3A8A` / `#E8EEFB` | links, active, focus |
| `--bs-blue-light` | `#3B82F6` (hover `#2563EB`) | preorder, secondary purchase |
| `--bs-gold` | `#C68A00` | site accent. Defined `#D4A017` in the main block, overridden globally by the RD-10F `:root` block (2026-06-11). Canonical `#C68A00` (owner 2026-10-09). |
| `--bs-gold-soft` | `#FBF4DC` | gold tint |
| `--bs-pokemon` / `--bs-onepiece` | `#C68A00` / `#1E40AF` | category accents only |
| `--bs-other-tcg` / `--bs-accessories` | `#065F46` / `#0D9488` | category accents (CAT-002-5) |
| `--bs-yugioh` / `--bs-mtg` | `#7C3AED` / `#B45309` | burger/chips (CAT-002-5) |
| `--bs-buy` / `--bs-buy-hover` | `#12883E` / `#15803D` | every purchase/cart control (TECH-045 WP-E, owner 2026-09-29) |
| `--bs-green` / `--bs-green-d` / `--bs-green-hover` | `#16A34A` / `#15803D` / alias of `-d` | non-button success: free-shipping reached, mobile success toast, text |
| `--bs-green-soft` | `#F3FBF6` | free-shipping reached background |
| `--bs-danger` | `#DC2626` | errors, sale price |
| `--bs-warning-bg/-fg/-line` | `#FFFBEA` / `#92400E` / `#FCD34D` | warnings |
| `--bs-r-sm` / `--bs-r` / `--bs-r-lg` | 6 / 10 / 14px | badges, fields / cards / sheets |
| `--bs-sh-sm` | `0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.05)` | cards |
| `--bs-sh-md` | `0 4px 12px rgba(0,0,0,.10), 0 2px 4px rgba(0,0,0,.06)` | raised blocks |
| `--bs-sh-pop` | `0 8px 24px rgba(0,0,0,.12), 0 2px 6px rgba(0,0,0,.07)` | popovers, mini-cart, toasts, dropdowns |
| `--bs-z-dropdown/-sticky/-overlay/-modal/-toast` | 100 / 200 / 300 / 400 / 500 | z-index scale |
| `--bs-header-sticky-h` | 69px; 57px ≤768 | sticky header height |
| `--bs-s1…s10` | 4 8 12 16 20 24 32 40 | spacing |

## Used but not defined in `:root`
Rules carry a fallback, so nothing breaks. Do not add new uses; prefer the defined tokens.
| Name in CSS | Fallback | Defined equivalent |
|---|---|---|
| `--bs-r-pill` | `999px` | none; write `999px` or add the token in a dedicated patch |
| `--bs-radius-sm`, `--bs-radius-md`, `--bs-surface` | per rule | `--bs-r-sm`, `--bs-r`, `--bs-paper` |
| `--bs-header-h` | per rule | `--bs-header-sticky-h` |
| `--bs-related-progress-color` | per rule | — |

## Component values not tokenised (live)
| Element | Value |
|---|---|
| `.bs-btn` | height 44, 14.5/700, padding 0 16, radius 8px |
| `.bs-badge--rare` | bg `#4C0519`, text `#FDE68A` |
| `.bs-badge--preorder` | bg `#FEF3C7`, text `#92400E`, border `#F59E0B`. Canonical (owner 2026-10-09). The earlier blue rule in the same file is dead code (see drift). |
| `.bs-badge--instock` | bg `#D1FAE5`, text `#065F46`, border `#6EE7B7`. Deliberate exception to "green only for purchase" (owner 2026-10-09). |
| PUMB red `#E60C2A` / soft `#FDECEE` | from bank materials (PAY-001); not found in the five live stylesheets |

## Mockup-only file
`tokens/tokens.css` is the Claude Design mockup token file. It still has the old gold `#D4A017`, the old shadows, the type ramp `--bs-h1…--bs-micro` and a 13.5/600 `.bs-btn`. Use it for mockups only; site values come from the table above.

## Known drift
Resolve to the canonical value when a task touches the element.
- **Dead CSS — preorder badge (cleanup backlog).** `boostershop-ds.css` defines `.bs-badge--preorder` three times: the original blue rule (bg `--bs-blue-soft`, border `#c7d2fe`) and two identical amber rules later in the file. Amber is canonical; a cleanup patch may delete the blue rule and the duplicate amber one. Visual result must not change.
- **Contrast below WCAG AA 4.5:1 (measured 2026-10-09):** white on `--bs-blue-light` (preorder button "Передзамовити", 14.5–16px bold) 3.68:1; `--bs-green #16A34A` as text on white (checkout free-shipping text) 3.30:1; out-of-stock badge `--bs-ink-3` on `--bs-line-2` 4.23:1; `--bs-gold #C68A00` on white 2.98:1 (never use as text); `--bs-ink-4` on white 2.54:1 (placeholders only). Passing reference: white on `--bs-buy` 4.55:1, on `--bs-buy-hover` 5.02:1, on `#2563EB` 5.17:1.
- `--bs-danger`: old docs (HANDOFF-header-menu-search) list `#B91C1C`; one `#B91C1C` remains in the live CSS. Canonical `#DC2626`.
- Raw `#16A34A` appears 3 times and `#D4A017` 4 times in the live CSS outside `:root`; replace with tokens when touched.
- `booster-typography.css` declares `Inter` on `body` (overridden by Manrope). `stylesheet.css` declares `"Open Sans"` on `footer h5`. Both are legacy.
- Cart trigger `#1fa247` / `#18853a` and "Продовжити покупки" `#1f95d1`: no longer present in the live CSS (fixed by TECH-045 WP-E).
- PUMB chip `#7C3AED` in the original HANDOFF.md product page is obsolete; PUMB uses its bank red per PAY-001.
