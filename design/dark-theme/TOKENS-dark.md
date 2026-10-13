# Dark palette — direction C (extracted from the boards, 2026-10-13)

Not canon. Values are taken from the C boards as drawn. Contrast ratios were computed on 2026-10-13 (WCAG relative luminance); they are not a substitute for a check on the real implementation.

## Surfaces and lines

| Role | Value | Notes |
|---|---|---|
| Page | `#0B0F1A` | also input background |
| Surface (cards, nav, sidebar) | `#131A2B` | |
| Surface, footer bars of forms | `#0F1526` | |
| Read-only field | `#1B2236` | |
| Hairline border | `rgba(255,255,255,.08)` | cards |
| Control border | `rgba(255,255,255,.14)` | inputs, thumbnails, secondary buttons use `.18` |
| Hero | radial `rgba(224,168,46,.28)` at top-right over linear `#1B2440 → #10162A`, border `rgba(224,168,46,.35)` | |

## Text

| Role | Value | On `#131A2B` |
|---|---|---|
| Primary | `#E5E7EB` / `#F3F4F6` | 14.0 : 1 |
| Headings | `#FFFFFF` | 17.4 : 1 |
| Muted | `#9CA3AF` | 6.8 : 1 (7.5 : 1 on page) |
| Hero body | `#C9CFDB` | 9.8 : 1 on `#1B2440` |
| Input placeholder | `#9CA3AF` | 7.5 : 1 on `#0B0F1A` (a first draft used `#6B7280`, 4.0 : 1 — rejected) |

## Accent (gold foil)

| Role | Value | Contrast |
|---|---|---|
| Foil gradient (primary button, mark) | `linear-gradient(135deg, #F5D27A, #C68A00)` | text `#111827` on it: 12.2 : 1 (light end), 5.9 : 1 (dark end) |
| Gold text / links | `#F5D27A` | 11.9 : 1 on surface |
| Mid gold (focus, active input border) | `#E0A82E` | |
| Active nav item | fill `rgba(224,168,46,.12)`, text `#F5D27A`, 3 px left rule with the foil gradient | |
| Icon chip | circle, fill `rgba(224,168,46,.12)`, border `rgba(224,168,46,.4)`, icon `#F5D27A` | |

## Status and semantic

| Role | Value |
|---|---|
| Danger / required star / heart chip | `#F87171` on `rgba(248,113,113,.12)`, border `.4` (6.3 : 1 on surface) |
| Info status pill («Відправлено») | text `#93C5FD`, fill `rgba(96,165,250,.14)`, border `rgba(96,165,250,.35)` (9.6 : 1) |
| Telegram button | page-colour fill, border `rgba(34,158,217,.5)`, icon `#229ED9`, label `#FFFFFF` |
| Disabled item | `#9CA3AF` at 50 % opacity, `cursor: not-allowed`, «Скоро» tag |
| Focus ring | 2 px `#F5D27A`, offset 2 px |

Green stays reserved for purchase actions, as in the light system.

## Type

- Body and UI: Manrope (400–800), unchanged from the light system.
- Display: **Playfair Display 700** for H1 (38 px desktop hub, 34 px form pages, 28 px mobile) and section H2 («Останнє замовлення», 26 px desktop / 20 px mobile). Used only for page and section titles, never in controls or body copy.
- Small overline on the hero: Manrope 11 px, 700, uppercase, letter-spacing .14em, `#F5D27A`.

## Shape

Radii as in the light system (cards 14, controls 8, nav items 10). Cards are flat: border instead of shadow. No shadows on dark.

## Open for RD-29

- Product photos on white backgrounds and `cut` images (RD-25) on `#0B0F1A` / `#131A2B`.
- Bank logos (PUMB, monobank) and any legal block keep their official backgrounds.
- The hero glow and Playfair headings were drawn for the account area; whether they extend to catalog and product pages is an owner decision.
