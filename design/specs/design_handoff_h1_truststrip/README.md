# Handoff: H1 Hero + Trust Strip — Booster Shop Homepage

## Overview

This handoff covers two approved UI elements for the Booster Shop homepage:

1. **H1 Hero block** — the main page heading section, positioned directly below the header (or below the Trust Strip if Trust Strip A is used)
2. **Trust Strip** — a slim informational bar with 4 trust signals, positioned between the header and the H1

Both elements are finalized and approved for implementation.

---

## About the Design Files

`H1 та Trust Strip - варіанти.html` is a **high-fidelity design reference** built in HTML/React — it is a prototype showing approved look and behaviour, not production code to copy directly.

The task is to **recreate these designs in the existing Booster Shop codebase** (OpenCart + Journal/SP theme), using `boostershop-ds.css` for tokens and base components. Do **not** ship the prototype HTML as-is.

`boostershop-ds.css` is the live design system stylesheet — load it as-is; all CSS custom properties (`--bs-*`) referenced below come from it.

---

## Fidelity

**High-fidelity.** Pixel-perfect implementation is expected: exact colors, font sizes, weights, spacing, gradients, and SVG icons as specified below.

---

## 1. H1 Hero Block

### Chosen variant: **"Різкий" gradient**

### Placement
Directly below the Trust Strip (which sits below the header).

### Structure

```
┌─────────────────────────────────────────────────────────────────────┐
│  [padding: 28px 48px 38px]                                          │
│                                                                     │
│  Оригінальні бустери та бокси TCG          ┌──────────────┐        │
│                                            │    Твій      │        │
│  Pokémon · One Piece · та інші             │    TCG       │        │
│  колекційні ігри                           │   магазин    │        │
│                                            └──────────────┘        │
└─────────────────────────────────────────────────────────────────────┘
```

The badge (right side) is **desktop only** — hidden on mobile.

### CSS

```css
.bs-h1-hero {
  background: linear-gradient(135deg, #F5E898 30%, #AECAF2 70%);
  border-top: 3px solid transparent;
  border-image: linear-gradient(to right, #C68A00 30%, #1E3A8A 70%) 1;
  padding: 16px 48px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 28px;
  font-family: 'Manrope', system-ui, sans-serif;
}

/* Heading */
.bs-h1-hero h1 {
  font-size: 40px;
  font-weight: 800;
  letter-spacing: -0.028em;
  line-height: 1.1;
  color: #111827;       /* --bs-ink */
  margin: 0;
  text-wrap: pretty;
}

/* Subtitle */
.bs-h1-hero .subtitle {
  margin: 11px 0 0;
  font-size: 16px;
  font-weight: 500;
  color: #6B7280;       /* --bs-ink-3 */
  line-height: 1.4;
}

/* Dot separator */
.bs-h1-hero .dot {
  margin: 0 6px;
  color: #9CA3AF;       /* --bs-ink-4 */
}
```

### Badge (desktop only, `display: none` on mobile)

```css
.bs-h1-badge {
  flex-shrink: 0;
  background: rgba(255, 255, 255, 0.65);
  border: 1px solid rgba(0, 0, 0, 0.07);
  border-radius: 12px;
  padding: 12px 20px;
  text-align: center;
  line-height: 1;
}

.bs-h1-badge__eyebrow {
  font-family: 'JetBrains Mono', monospace;
  font-size: 10px;
  font-weight: 600;
  color: #6B7280;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 5px;
}

.bs-h1-badge__main {
  font-size: 28px;
  font-weight: 800;
  color: #C68A00;       /* --bs-pokemon */
  letter-spacing: -0.03em;
}

.bs-h1-badge__sub {
  font-family: 'JetBrains Mono', monospace;
  font-size: 10px;
  color: #6B7280;
  margin-top: 5px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
```

### HTML markup

```html
<section class="bs-h1-hero">
  <div>
    <h1>Оригінальні бустери та бокси <span style="color:#C68A00">TCG</span></h1>
    <p class="subtitle">
      Pokémon <span class="dot">·</span>
      One Piece <span class="dot">·</span>
      та інші колекційні ігри
    </p>
  </div>

  <!-- Desktop badge — hide on mobile -->
  <div class="bs-h1-badge" aria-hidden="true">
    <div class="bs-h1-badge__eyebrow">Твій</div>
    <div class="bs-h1-badge__main">TCG</div>
    <div class="bs-h1-badge__sub">магазин</div>
  </div>
</section>
```

### Mobile overrides (`max-width: 640px`)

```css
@media (max-width: 640px) {
  .bs-h1-hero {
    padding: 14px 18px 18px;
  }
  .bs-h1-hero h1 {
    font-size: 26px;
  }
  .bs-h1-hero .subtitle {
    font-size: 13px;
  }
  .bs-h1-badge {
    display: none;
  }
}
```

---

## 2. Trust Strip — V2 (White + gold rule)

### Placement
Between the site header and the H1 hero block.

### Chosen variant: **V2 — White + gold rule**

The strip background is **white** (same as the header), making it visually part of the header zone. A `3px` gold bottom line is the only visible separator, cleanly anchoring the transition into the H1.

### Behaviour
Purely informational — **no hover states, no links, not clickable**.

### Items (in order)

| Key      | Icon       | Label                      | Icon SVG path                                      |
|----------|------------|----------------------------|----------------------------------------------------|
| shield   | Shield ✓   | Гарантія оригінальності    | See SVG specs below                                |
| zap      | Lightning  | Швидка доставка            | See SVG specs below                                |
| send     | Arrow/send | Telegram підтримка         | See SVG specs below                                |
| truck    | Truck      | Привеземо під замовлення   | See SVG specs below                                |

### SVG Icons
All icons: `viewBox="0 0 24 24"`, `fill="none"`, `stroke="#C68A00"`, `stroke-width="1.8"`, `stroke-linecap="round"`, `stroke-linejoin="round"`.

**Shield (Гарантія оригінальності)**
```svg
<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
<polyline points="9,12 11,14 15,10"/>
```

**Zap (Швидка доставка)**
```svg
<polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2"/>
```

**Send (Telegram підтримка)**
```svg
<line x1="22" y1="2" x2="11" y2="13"/>
<polygon points="22,2 15,22 11,13 2,9 22,2"/>
```

**Truck (Привеземо під замовлення)**
```svg
<rect x="1" y="3" width="15" height="13" rx="1"/>
<path d="M16 8h4l3 3v5h-7V8z"/>
<circle cx="5.5" cy="18.5" r="2.5"/>
<circle cx="18.5" cy="18.5" r="2.5"/>
```

### Desktop CSS & markup

```css
.bs-trust-strip {
  background: #FFFFFF;
  border-bottom: 3px solid #C68A00;   /* --bs-pokemon */
  font-family: 'Manrope', system-ui, sans-serif;
}

.bs-trust-strip__inner {
  max-width: 1160px;
  margin: 0 auto;
  padding: 0 48px;
  display: flex;
  align-items: center;
  height: 36px;
}

.bs-trust-strip__item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.bs-trust-strip__divider {
  width: 1px;
  height: 22px;
  background: #E5E7EB;
  flex-shrink: 0;
}

.bs-trust-strip__item svg {
  width: 13px;
  height: 13px;
  flex-shrink: 0;
}

.bs-trust-strip__label {
  font-size: 12px;
  font-weight: 600;
  color: #111827;   /* --bs-ink */
  white-space: nowrap;
}
```

```html
<div class="bs-trust-strip">
  <div class="bs-trust-strip__inner">

    <div class="bs-trust-strip__item">
      <!-- shield icon -->
      <svg ...><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9,12 11,14 15,10"/></svg>
      <span class="bs-trust-strip__label">Гарантія оригінальності</span>
    </div>

    <div class="bs-trust-strip__divider"></div>

    <div class="bs-trust-strip__item">
      <!-- zap icon -->
      <svg ...><polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2"/></svg>
      <span class="bs-trust-strip__label">Швидка доставка</span>
    </div>

    <div class="bs-trust-strip__divider"></div>

    <div class="bs-trust-strip__item">
      <!-- send icon -->
      <svg ...><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9 22,2"/></svg>
      <span class="bs-trust-strip__label">Telegram підтримка</span>
    </div>

    <div class="bs-trust-strip__divider"></div>

    <div class="bs-trust-strip__item">
      <!-- truck icon -->
      <svg ...><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      <span class="bs-trust-strip__label">Привеземо під замовлення</span>
    </div>

  </div>
</div>
```

### Mobile overrides (`max-width: 640px`)

On mobile the strip switches to a **2×2 grid**:

```css
@media (max-width: 640px) {
  .bs-trust-strip {
    padding: 7px 16px;
    border-bottom: 3px solid #C68A00;
  }
  .bs-trust-strip__inner {
    display: grid;
    grid-template-columns: 1fr 1fr;
    height: auto;
    gap: 7px 0;
    max-width: 100%;
  }
  /* Hide the vertical dividers on mobile */
  .bs-trust-strip__divider {
    display: none;
  }
  /* Right-column items get a left border instead */
  .bs-trust-strip__item:nth-child(even) {
    padding-left: 11px;
    border-left: 1px solid #E5E7EB;
    justify-content: flex-start;
  }
  .bs-trust-strip__item:nth-child(odd) {
    padding-right: 11px;
    justify-content: flex-start;
  }
  .bs-trust-strip__item svg {
    width: 11px;
    height: 11px;
  }
  .bs-trust-strip__label {
    font-size: 10.5px;
    line-height: 1.3;
    white-space: normal;
  }
}
```

> **Width note:** `.bs-trust-strip` must be placed **inside the page content container wrapper** (same element that constrains the header, categories, product grid), not as a full-width block above it. The `padding: 0 48px` on `.bs-trust-strip__inner` aligns the items with the rest of the page content.

> **Mobile layout note:**

---

## Page Order (homepage, top section)

```
1. <header>           — site header (logo, search, cart)
2. .bs-trust-strip    — 36px info bar
3. .bs-h1-hero        — H1 gradient block
4. ...category nav / product grid...
```

---

## Design Tokens Used

| Token | Value | CSS var |
|-------|-------|---------|
| Gold accent | `#C68A00` | `--bs-pokemon` |
| Blue brand | `#1E3A8A` | `--bs-blue` |
| Background | `#F7F7F5` | `--bs-bg` |
| Paper | `#FFFFFF` | `--bs-paper` |
| Ink | `#111827` | `--bs-ink` |
| Ink 3 | `#6B7280` | `--bs-ink-3` |
| Ink 4 | `#9CA3AF` | `--bs-ink-4` |
| Line | `#E5E7EB` | `--bs-line` |
| Line 2 | `#EEF0F2` | `--bs-line-2` |
| H1 gradient start | `#F5E898` | — |
| H1 gradient end | `#AECAF2` | — |

## Typography

| Element | Size | Weight | Font |
|---------|------|--------|------|
| H1 desktop | 40px | 800 | Manrope |
| H1 mobile | 26px | 800 | Manrope |
| H1 letter-spacing | -0.028em | — | — |
| Subtitle desktop | 16px | 500 | Manrope |
| Subtitle mobile | 13px | 500 | Manrope |
| Trust strip desktop | 12px | 600 | Manrope |
| Trust strip mobile | 10.5px | 600 | Manrope |
| Badge eyebrow/sub | 10px | 600 | JetBrains Mono |
| Badge main (TCG) | 28px | 800 | Manrope |

## Fonts Required

- **Manrope** weights 400, 500, 600, 700, 800 — already in `boostershop-ds.css`
- **JetBrains Mono** weights 400, 500 — used for badge eyebrow/sub only; load separately if not already in the theme

---

## Assets

No image assets. All icons are inline SVG.

---

## Files in This Package

| File | Purpose |
|------|---------|
| `H1 та Trust Strip - варіанти.html` | Full hi-fi design reference (all variants + chosen) — open in browser |
| `boostershop-ds.css` | Live design system stylesheet with all `--bs-*` tokens |
| `README.md` | This document |

---

## Notes for Developer

- The `border-image` gradient on `.bs-h1-hero` requires `border-top: 3px solid transparent` to be set first — otherwise `border-image` has nothing to paint on.
- The Trust Strip must **not** have `cursor: pointer`, `hover` backgrounds, or any interactive affordances — it is purely decorative/informational.
- On OpenCart/Journal, the Trust Strip can be placed as a custom HTML module in the layout manager, positioned in the `content_top` position above the category/page content.
- The H1 block can be placed as a custom HTML module in the same `content_top` position, below the Trust Strip.
