# Handoff: 404 «Сторінка не знайдена» — Booster Shop

## Overview
Full visual redesign of the 404 error page. Replaces the default blank
OpenCart/Journal error page with an on-brand, TCG-themed experience that
reduces bounce by offering a search bar, direct CTAs, and category shortcuts.

## About the design files
The `.html` files are **HTML design references** — not production code. Use
them to see the intended look and behaviour. The implementation target is the
existing OpenCart theme (Twig template + theme stylesheet), using the
established `bs-*` design-token system.

**File to replace:** `catalog/view/template/error/not_found.twig`
**CSS to add:** append `booster-404.css` to your category/common stylesheet.
No JS required.

---

## Design concept
**«Цю сторінку видалено з колоди»** — three TCG trading cards fanned out: two
face-down (brand blue, `BS` monogram), one face-up showing `?` with an
«НЕ ЗНАЙДЕНО» label. Behind them: ghost `404` text in pale blue. Gold and
blue sparkles for atmosphere. 100% SVG — zero HTTP requests.

---

## Layout

### Desktop (≥ 760px)
```
[ Site header (from layout) ]
[ Breadcrumb (from layout) ]

[ .bs-404-wrap — centered column, max-width 720px ]
  ↕ padding 40px top / 60px bottom
  [ SVG illustration  400px wide ]
  [ H1  27px / 800 / --bs-blue ]
  [ Body text  15px / --bs-ink-3 / max 450px ]
  [ Search bar  420px wide ]
  [ CTA row — "На головну" + "Продовжити покупки" ]
  [ Category pills ]
```

### Mobile (< 760px)
- Illustration shrinks to 300px max-width
- H1 → 22px; body → 14px
- Search bar → 100% width
- CTA buttons → stacked full-width column

---

## Component specs

### `.bs-404-illustration`
| Property | Value |
|---|---|
| Container | `width: 400px`, `max-width: 100%` |
| SVG viewBox | `0 0 440 260` |
| SVG inline | Yes — no `<img>` |

### `.bs-404-heading` (H1)
| Property | Value |
|---|---|
| Font size | 27px desktop / 22px mobile |
| Font weight | 800 |
| Color | `--bs-blue` (#1E3A8A) |
| Letter-spacing | -0.02em |
| Line-height | 1.2 |
| Max-width | 500px desktop / 340px mobile |

### `.bs-404-body`
| Property | Value |
|---|---|
| Font size | 15px desktop / 14px mobile |
| Color | `--bs-ink-3` (#6B7280) |
| Line-height | 1.65 |
| Max-width | 450px desktop / 320px mobile |

### `.bs-404-search`
| Property | Value |
|---|---|
| Width | 420px desktop / 100% mobile |
| Height | ~46px (padding 11px 14px) |
| Border | 1.5px solid `--bs-line` |
| Border-radius | `--bs-r` (10px) |
| Focus | border-color: `--bs-blue`, shadow 3px blue-10% |

### `.bs-404-btn-primary` — «На головну»
| Property | Value |
|---|---|
| Height | 48px |
| Padding | 0 28px |
| Background | `--bs-green` (#16A34A) |
| Color | #fff |
| Border-radius | `--bs-r-pill` (999px) |
| Shadow | `0 4px 16px -4px rgba(22,163,74,.42)` |
| Hover | `--bs-green-hover` (#15803D) |
| Icon | Arrow-left inline SVG (14×14) |

### `.bs-404-btn-secondary` — «Продовжити покупки»
| Property | Value |
|---|---|
| Height | 48px |
| Padding | 0 28px |
| Background | `--bs-paper` |
| Border | 1.5px solid `--bs-line` |
| Color | `--bs-ink-2` |
| Border-radius | `--bs-r-pill` |
| Hover | border: `--bs-ink-4`, bg: `--bs-line-2` |

### `.bs-404-cats__pill`
| Property | Value |
|---|---|
| Padding | 6px 14px |
| Border-radius | `--bs-r-pill` |
| Background | `--bs-blue-soft` (#E8EEFB) |
| Color | `--bs-blue` (#1E3A8A) |
| Font | 13px / 600 |

---

## Copy

### Heading (ігровий тон — approved)
> **Цю сторінку видалено з колоди**

### Body
> Схоже, ця картка вже не в нашій колоді — її видалили, переклали або вона
> ніколи й не існувала. Спробуйте пошук або оберіть категорію нижче.

### Neutral fallback (if tone needs to change)
> Сторінку не знайдено. На жаль, сторінку, яку Ви шукаєте, не знайдено.
> Ймовірно, Ви вказали неіснуючу адресу або сторінку було видалено.

---

## OpenCart implementation notes

1. **Template file:** `catalog/view/template/error/not_found.twig`
   Replace the existing content block with the markup from `not_found.twig.html`
   (rename to `.twig`, remove the Twig comment header if desired).

2. **CSS:** Append `booster-404.css` to your main theme stylesheet.
   All selectors are `.bs-404-*` — safe to add without touching existing rules.

3. **Search form:** The `{{ search }}` Twig variable should resolve to
   `index.php?route=product/search` (standard OC). The `name="search"` param
   feeds the search controller.

4. **Category pill URLs:** Update the four hardcoded hrefs in the Twig snippet
   to your real category routes (e.g. `index.php?route=product/category&path=59`).

5. **`{{ continue }}`** already points to `route=common/home` in the default OC
   error controller — no controller change needed.

6. **No JS:** The entire redesign is pure CSS + inline SVG. No scripts, no
   additional font downloads (Manrope is already loaded site-wide).

7. **`visually-hidden` class:** If your theme doesn't already define it, add:
   ```css
   .visually-hidden {
     position: absolute; width: 1px; height: 1px;
     padding: 0; margin: -1px; overflow: hidden;
     clip: rect(0,0,0,0); white-space: nowrap; border: 0;
   }
   ```

---

## Design tokens used (all in `boostershop-ds.css`)
| Token | Value | Where |
|---|---|---|
| `--bs-blue` | `#1E3A8A` | H1, card fill, pill bg/text |
| `--bs-blue-soft` | `#E8EEFB` | Ghost 404, pill bg |
| `--bs-blueLight` | `#3B82F6` | Sparkles, card accent |
| `--bs-green` | `#16A34A` | Primary CTA |
| `--bs-green-hover` | `#15803D` | Primary CTA hover |
| `--bs-gold` | `#D4A017` | Sparkles |
| `--bs-paper` | `#FFFFFF` | Center card, search bg, secondary CTA |
| `--bs-bg` | `#F7F7F5` | Page background |
| `--bs-line` | `#E5E7EB` | Search border, secondary CTA border |
| `--bs-line-2` | `#EEF0F2` | Secondary CTA hover bg |
| `--bs-ink-2` | `#1F2937` | Secondary CTA text |
| `--bs-ink-3` | `#6B7280` | Body text |
| `--bs-ink-4` | `#9CA3AF` | Search placeholder, category label |
| `--bs-r` | `10px` | Search border-radius |
| `--bs-r-pill` | `999px` | Buttons, pills |
| Font | `Manrope` 400–800 | All text |

---

## Assets
None. The illustration is 100% inline SVG — no images, no icon fonts, no
additional network requests.

## Files in this bundle
| File | What it is |
|---|---|
| `booster-404.css` | Production CSS — append to theme stylesheet |
| `not_found.twig.html` | Twig markup — rename to `.twig` and place in `catalog/view/template/error/` |
| `404 — Сторінка не знайдена.html` | Full interactive design reference (Desktop + Mobile tabs, Tweaks panel) |
| `tweaks-panel.jsx` | Dependency for the reference file (not needed in production) |
