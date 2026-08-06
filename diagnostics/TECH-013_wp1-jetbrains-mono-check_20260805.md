# TECH-013 WP1 — §5E JetBrains Mono removal gate: **FAILED, link kept**

Date: 2026-08-05 · Executor: Claude Code · Gate defined in handoff §5E
Verdict: **Do not remove `header.twig:54`.** WP1 change #3 is dropped.

---

## What §5E required

> The font is **not** in the loaded set on either desktop or mobile, so those labels
> already render in fallback today. […] Residual risk: the labels may be hidden at capture
> time and could trigger the load when the menu opens. **Before deleting, open the mobile
> menu and screenshot `.bs-menu__label`; repeat after.** If identical, the removal stands.
> If not, keep the link and record why.

**The residual risk materialised.** The §5C/§5E capture was taken with the burger menu
closed. `.bs-menu__label` lives inside `#bs-menu`, which is `hidden` until the menu opens,
so the font was never requested at capture time — which is exactly why it looked unused.

## Method

`https://boostershop.website/`, viewport **390×844, dpr 2**, fresh load, burger menu opened
via `#bs-menu-open`. Glyph advance measured with `Range.getBoundingClientRect()` over the
label's text contents — this returns the exact painted advance width of the text run, so it
detects a font substitution more precisely than a screenshot can.

Three states measured: **BEFORE** (live, as-is) → **AFTER** (the JetBrains Mono `<link>`
removed from the DOM, which also drops its CSS-connected `@font-face` rules, leaving the DS
rule `font-family: 'JetBrains Mono', ui-monospace, monospace` to fall back) → **RESTORED**
(link re-added).

A screenshot could not be captured in this environment: the browser pane was not being
displayed, so the page was not compositing frames (which also froze the panel's 0.3 s slide
transition). The geometric measurement below is the substitute, and it is a stricter test —
it is a numeric comparison rather than an eyeball one.

## Result

**On opening the menu, `JetBrains Mono 600` moves to `status: "loaded"`.** It is genuinely
downloaded and painted — it is not unused.

| Element | Metric | BEFORE | AFTER (link removed) | Δ | RESTORED |
|---|---|---|---|---|---|
| `DIV.bs-menu__label` — "Каталог" | advance width | **51.453 px** | 47.766 px | **−3.687 px (−7.2 %)** | 51.453 px |
| | line height | 14 px | 13 px | −1 px | 14 px |
| `DIV.bs-menu__label.bs-menu__label--sep` — "Інформація" | advance width | **73.500 px** | 68.234 px | **−5.266 px (−7.2 %)** | 73.500 px |
| | line height | 14 px | 13 px | −1 px | 14 px |

Computed style on both, unchanged throughout:
`font-family: "JetBrains Mono", ui-monospace, monospace` · `font-size: 10.5px` ·
`font-weight: 700` · `letter-spacing: 1.05px`.

**RESTORED matches BEFORE exactly** (`matchesOriginal: true`), which rules out measurement
drift, layout reflow or transition timing as the cause. The only variable was the presence
of the `<link>`.

## Verdict

Removing `header.twig:54` would visibly change both mobile-menu section headings: −7.2 %
narrower text and a 1 px shorter line box, in a different monospace typeface. That is not
"identical", so §5E's own rule applies: **keep the link and record why.**

`header.twig:54` is left untouched by
`patches/TECH-013_wp1-render-blocking_20260805.php`.

## Consequence for WP1's expected saving

WP1 removes **one** of the three head Google Fonts `<link>` requests (Inter), not two as
§5E projected, on top of hoisting the Manrope `@import` out of `boostershop-ds.css`. The
hoist remains the dominant lever — it converts a four-hop serialized chain into a
head-discoverable request — so the effect on FCP/LCP should still be large, but the
render-blocking savings estimate should be set expecting one fewer request eliminated.

## Reproduction

Open `https://boostershop.website/` at 390×844, open the burger menu, then in the console:

```js
document.getElementById('bs-menu-open').click();
setTimeout(() => {
  const m = () => [...document.querySelectorAll('.bs-menu__label')].map(el => {
    const r = document.createRange(); r.selectNodeContents(el);
    const b = r.getBoundingClientRect();
    return { text: el.textContent.trim(), w: +b.width.toFixed(3), h: +b.height.toFixed(3) };
  });
  console.log('BEFORE', m());
  console.log('JBM loaded?', [...document.fonts].some(f => /JetBrains/i.test(f.family) && f.status === 'loaded'));
  document.querySelectorAll('link[href*="JetBrains+Mono"]').forEach(l => l.remove());
  setTimeout(() => console.log('AFTER', m()), 800);
}, 1200);
```

---

## Related observation — NOT acted on, logged for later

While mapping the head, two further `preconnect` hints were found beyond the four font
ones: `https://www.google.com` and `https://www.gstatic.com`, emitted into the head by
`ps_google_recaptcha`. Total preconnect hints on the live home page are therefore **six**,
not four — which explains the PSI ">4 preconnect hints" warning more completely than the
duplicate font pair alone. WP1 removes the duplicate pair, taking the total from 6 to 4.

Trimming the reCAPTCHA hints is outside the five changes the owner scoped for WP1 and would
touch a module that participates in checkout, so it was not done. Worth a decision in
Stage 2.

## Related observation — pre-existing, NOT fixed (out of scope)

The `CARD-CLICK-DELAY-FIX-20260526` inline shim in the head (`header.twig:79–85`) reads
`window.ps_dataLayer` and sets `tracking_delay = 0`. In the rendered page that shim runs at
source line ~52, but `var ps_dataLayer = {…}` is not defined until source line ~212, in a
later inline block. The guard therefore fails and **the shim is already a no-op today** —
before and independently of this patch. It was not touched: it is unrelated to WP1's scope
and the handoff forbids fixing unrelated bugs. Flagged for separate triage.

---

_No production file was modified by this check — DOM edits were made in a local browser
session only and reverted. Nothing committed._
