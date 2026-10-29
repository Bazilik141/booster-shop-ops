# Account redesign — light variant A (RD-16 boards)

Owner-approved 2026-10-13 as the light design for the whole account area
(RD-16/17/18/19/20). Dark variant C lives in `../dark-theme/`
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


Added 2026-10-29 for RD-19 and RD-20 (owner-approved round 4, canvas version
`1793293749-fa9d`). Start with `rd19-rd20/SPEC.md`; static previews in
`rd19-rd20/preview/`, full-page renders in `rd19-rd20/screens/`.
Unlike earlier rounds, these boards include the live header, breadcrumbs and
footer (redrawn 2026-10-29) for context only; the grey area below the footer
holds states and notes, not page content.

| File | Board | Width | SHA-256 |
|---|---|---|---|
| `A-Login.dc.html` | Desktop · sign-in (RD-19) | 1280 | `dc20c077a7b1fce506fc90430e660de7cbb610ebd372d48add79c8fb7165ce84` |
| `A-Mobile-Login.dc.html` | Mobile · sign-in (RD-19) | 390 | `3eb56bd6bb4045b0ffc218a5f9f1c5cbc01c51eff09b753b43a81e051d414e38` |
| `A-Forgotten.dc.html` | Desktop · forgotten password (RD-19) | 1280 | `8917e1177dd5232425e0a68ead209b88e4265d9bdc9a00d7f07c52147306ccc6` |
| `A-Mobile-Forgotten.dc.html` | Mobile · forgotten password (RD-19) | 390 | `4515bb5ff364a865259edf3adffbc23e7b3b5ddb1138cb36e7ad9f89f08a1ebf` |
| `A-Reset.dc.html` | Desktop · new password (RD-19) | 1280 | `fb7fcf43eecadab986bb9285082f54970f422d8dfe811f7bf2d667b46dbd284c` |
| `A-Mobile-Reset.dc.html` | Mobile · new password (RD-19) | 390 | `53b16a42d5c90ac969b1a8582517727e7a48b34b7ae6838e5b151e9afce415f1` |
| `A-Register.dc.html` | Desktop · registration step 1 (RD-20) | 1280 | `d5e4ec7a5c8acaffc05f7805624fdb66c4270da060d9b402fbb26feaafe940bd` |
| `A-Mobile-Register.dc.html` | Mobile · registration step 1 (RD-20) | 390 | `0d58fb76baa4f29f8d6155e6e27309594f37b2700f56132e1210a75c654ae1ed` |
| `A-Register-Address.dc.html` | Desktop · registration step 2, address (RD-20) | 1280 | `584c8019c507b6b844c891ced099cf0540571139821263aa1c88de80087e7010` |
| `A-Mobile-Register-Address.dc.html` | Mobile · registration step 2, address (RD-20) | 390 | `566074fdc7925526cf68feadafc69e447917e92f6a31135475e49ba0f4f2a3d8` |
| `A-Register-Done.dc.html` | Desktop · hub with one-time toast (RD-20) | 1280 | `fa18d637a57e563b171a584dce4e8a1ffd23c5facce2e20d09de71fb33163ab2` |
| `A-Mobile-Register-Done.dc.html` | Mobile · hub with one-time toast (RD-20) | 390 | `a89c4f3a71dd82743c1de6a9475494715d0c00a90fa774685a23c61deb95c245` |

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
