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

Added 2026-10-26 for RD-17 and RD-18 (same canvas version):

| File | Board | Width | SHA-256 |
|---|---|---|---|
| `A-Addresses.dc.html` | Desktop · addresses, form collapsed | 1280 | `aba85378669c38b7719e9900f60f4831dca2345905b12f92af4ff9c6e4cdb1dd` |
| `A-Addresses-Open.dc.html` | Desktop · addresses, form open | 1280 | `95b75b9a2dcfcc818c37a66d08acf1225d179ef95651691556358477ffa3e361` |
| `A-Mobile-Addresses.dc.html` | Mobile · addresses, form collapsed | 390 | `6130ce2c990330553515cec0f9cae7e25df2aae096eed93c61646364aabc4502` |
| `A-Mobile-Addresses-Open.dc.html` | Mobile · addresses, form open | 390 | `cd4b0353fc84728a9109b4f6771f4e60475dc56391b5058cc25252367abf2565` |
| `A-Orders.dc.html` | Desktop · all orders | 1280 | `1f90b89803730aead35239be23255028a1b40f65635b3cd7aa44d538341c3610` |
| `A-Order.dc.html` | Desktop · order detail | 1280 | `7195affef20cb44fd900f5f4caf241e181e6575cd87cd62fb92cd375517573be` |
| `A-Mobile-Orders.dc.html` | Mobile · all orders | 390 | `deb5dba03761c559d1b26a3668a8ab64ee6ba8f1a45cbc4e3c26d88ff9beece0` |
| `A-Mobile-Order.dc.html` | Mobile · order detail | 390 | `cd3261c8186250d9812027eee3422828e2345c69030c86585d1038b34ceae98d` |

Boards for favorites and settings remain on the canvas only; copy them here
when their task (ACC-006 visual step, ACC-005) is handed off.

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
- The address-form boards show a simplified form. The live Nova Poshta form
  from ACC-002 (lookups, courier mode, stored `bs_np_v1` metadata) is the
  functional source; the boards set only its look.
- Order status names, colors and dates on the boards are illustrative. The live
  ACC-007 status map (labels, groups, hidden statuses, history date/time) is
  the source.
