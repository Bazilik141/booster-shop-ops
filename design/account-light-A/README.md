# Account redesign — light variant A (RD-16 boards)

Owner-approved 2026-10-13 as the light design for the whole account area
(RD-16/17/18; RD-19/20 not drawn yet). Dark variant C lives in `../dark-theme/`
for RD-29.

Copied 2026-10-25 from the live Claude Design canvas
https://claude.ai/artifact/d5fbf671-8451-42ce-b887-4e7904ad81f3 (version
1791881628-03e4) so the executor can read them without canvas access.

| File | Board | Width | SHA-256 |
|---|---|---|---|
| `A-Hub.dc.html` | Desktop · account hub | 1280 | `6c1085c3bccc699d8cada99d8c6baed36ad9618ba1e684504f39358f19f967c5` |
| `A-Edit.dc.html` | Desktop · personal data | 1280 | `d74aa76aa11fe84d7e63198748b5130ee14a6e96c5aa2d139603fd50ab7bf8d1` |
| `A-Mobile-Hub.dc.html` | Mobile · account hub | 390 | `cd407c76c242e0b4a0d24270e9788b205e05a0576f57a9cf1a3136e92069e4c5` |
| `A-Mobile-Edit.dc.html` | Mobile · personal data | 390 | `ca08dc5aa609df4d5bcbaacb27e7cb3c8a8d3152b4da5b25dd70ac13047a42ce` |

Boards for other pages (addresses, orders, order detail, favorites, settings)
remain on the canvas only; copy them here when their task (RD-17, RD-18,
ACC-006 visual step, ACC-005) is handed off.

## How to read them

- These are Claude Design sources (`*.dc.html`). They need the canvas runtime
  (`support.js`) to render. As a spec, read the markup inside `<x-dc>`.
- Markup is inline-styled mock code, not Twig, and does not use `bs-*`
  classes. Take layout, hierarchy, spacing, copy and states. Do not copy markup.
  Map every hex value to an existing `boostershop-ds.css` token
  (`design/TOKENS.md`).
- Placeholder data (names, order number, sums, "3 товари") is not content.
- The name `pattern` and hint in the boards were drawn **before** ACC-009.
  The live ACC-009 name policy (3–32 characters, Ukrainian letters,
  apostrophe/hyphen only between letters, whitespace removed) supersedes them.
