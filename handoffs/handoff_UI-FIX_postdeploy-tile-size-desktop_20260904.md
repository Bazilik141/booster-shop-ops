# Handoff — UI-FIX post-deploy polish: homepage category tiles oversized on wide desktop

Date: 2026-09-04. Written by Claude (chat) after owner QA on
`UI-FIX_home-category-tiles_20260903.php` (patch 3 of the UX-036 batch),
already deployed to production. Priority: normal — cosmetic, not
customer-blocking. Queue after the captcha fix
(`handoffs/handoff_UI-FIX_postdeploy-captcha-regression_20260903.md`, high
priority, still open).

## Owner-reported symptom

On desktop, the two homepage category tiles (Pokémon TCG / One Piece Card
Game) look too large — owner's screenshot shows both nearly full page width.

## Confirmed cause, from the patch source

`patches/UI-FIX_home-category-tiles_20260903.php`, the CSS it adds:

```
.bs-cattiles{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:4px 0 12px}
.bs-cattile{position:relative;display:block;aspect-ratio:1/1;overflow:hidden; ...}
@media (min-width:900px){ .bs-cattiles{gap:24px;margin:8px 0 16px} ... }
```

Two columns at every breakpoint, each tile `aspect-ratio:1/1` — no `max-width`
on `.bs-cattiles` or the tiles themselves, and no breakpoint above 900px. The
patch's own comment records the 2-column choice as deliberate ("the owner's
explicit pick over a full-width square on mobile") but that decision covered
mobile only; nothing caps the tile size on wide viewports, so each tile grows
to roughly half the page width at any desktop size, including very wide
windows.

The design brief (`handoffs/handoff_UI-CD_visual-design-brief_20260902.md`,
§7, Component B) asked for "mockup/spec for both tiles at mobile width and
desktop" but did not fix a pixel target in the brief text itself — check
whether the actual approved mockup specified a desktop max-width that did not
make it into this CSS, or whether one needs to be picked now.

## Suggested options (owner/executor decide, don't default silently — AGENTS.md UI/CSS discipline #4)

- (a) cap `.bs-cattiles` with a `max-width` at a wide-desktop breakpoint (e.g.
  above ~1200–1400px) so tiles stop growing past a fixed size — 1-line change,
  simplest;
- (b) move to more than 2 columns at a wide-desktop breakpoint instead of
  capping size — bigger change, matches full-width layouts elsewhere on the
  site more closely.

## Scope guardrails

Do not touch the mobile/tablet 2-column behavior (explicit owner decision,
confirmed working) or the tile markup/images from patch 3. CSS-only fix.

---
Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_016VGrbhuBLnM2B31XeDjxYP
