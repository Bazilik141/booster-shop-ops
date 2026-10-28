# RD17-WP1B R2 affected CODE/composition review

**Verdict: Review OK.** There are no mandatory findings. Optional findings are listed below.

## Identity binding

| Artifact | SHA256 (declared) |
|---|---|
| Runner `patches/RD-17-WP1B_addresses_20261026.php` | `4fdcb89f6a652a6a6fc270cfbbf51757bdf998b1c7394a345c228230f0194523` |
| Owner packet `diagnostics/RD-17-WP1B_owner_20261026.ps1` | `88f9f06452ca3c0f2daf27eb787ee9d3b971c3ba8b9e443024ea7fc81a3be5dd` |

- I bound both hashes from the supplied labels. I had no tools, so I did not recompute them.
- The packet's embedded `$Sha` equals the declared runner hash.
- The packet differs from the R1 packet only in that hash: same byte count (11840) and same logic.
- I did not see the screenshots.
- This verdict applies only to this exact pair. It does not carry over the R1 verdict.

## What I reviewed fresh

- The complete R2 runner text and the owner packet.
- The R1→R2 diff and the four changed target payloads:
  - controller;
  - list fragment;
  - DS append source;
  - JS.
- Both R1 and R2 bodies of each changed payload, side by side.
- `build-targets.py`, `build-runner.py` and `prepare-r2.py`, including the assertion that the engine is identical apart from the target table.
- The render, browser, default-action, runner and packet tests, with their results.
- The scope amendment, the R1 verdict and the R1 identity closure.

## Closure of the R1 mandatory findings

**M1 (double-escaped URLs): closed.**
- `add` is decoded once in `rd17Presentation`. It is set only in `index()` and guarded by `isset`.
- The card `edit` and `delete` links are wrapped in `html_entity_decode(..., ENT_QUOTES, 'UTF-8')`. The regex substitution is asserted to match exactly once per route.
- The templates keep `|e('html_attr')`, so the result is encoded exactly once.
- Native outputs are untouched and remain correct, because they still print the already-encoded URLs raw: `save`, `back`, breadcrumbs and the form action.
- The tests now discriminate:
  - The fixture's `url->link` emits `&amp;`.
  - `checkURL` parses the resolved Add and Edit `getAttribute('href')` values and the Delete `e.href`.
  - It also parses the actual default, delete and save request URLs.
  - It requires `route`, `customer_token` and `address_id`, and rejects any `amp;`-prefixed key.
  - The original R1 defect would fail these assertions.

**M2 (name omission): closed mechanically.**
- The owner's verbatim statement ("…не відображати ПІБ…у кабінеті, а не в чекауті") explicitly authorizes omitting full names on account address cards.
- No code changed for M2. The first and last name controls on the form are preserved.

## New changes and their interactions

**Corrupt metadata guard (`rd17CardMetadata`).**
- It is a read-only pre-admission check:
  - `version` must be an int or a string;
  - the ref, type, house and flat fields must be strings if present;
  - the three labels must be strings.
- It then delegates to the unchanged `getBsNpMetadata`.
- After admission, every value cast or concatenated in `getList` is scalar. This removes the Array-to-string warning, which OpenCart 4's error handler escalates to a page failure.
- On rejection it falls back to the stored stock columns. Stored JSON, the hydrator, the validators and the form/save paths are untouched.
- Three native PHP cases cover a label array, a type array and a house array.

**O2: decode once, then escape.**
- Stored text was request-cleaned with `htmlspecialchars`, so it holds entities. NP labels come from directory descriptions.
- Decoding once and then `|e` produces correct text and is XSS-safe whatever the source.
- Content that was already double-encoded displays its remaining entity literally. This is harmless.
- The entities case asserts the absence of `&amp;quot;` and `&amp;amp;` and the correct visible text.

**O4: refresh guard.**
- The response is parsed into an inert `<template>`. Scripts don't run and images don't load, which is safer than the previous `innerHTML`.
- The list is replaced only with the marked fragment. Both the list `<ul>` and the empty-state `<div>` carry the marker.
- Login or full-page HTML is refused, the existing list is retained and the user is told to reload. The browser test covers this.

**O1: breakpoints.**
- `991.98px` and `767.98px` now match the RD16 and native transitions exactly.

**Protected identity.**
- The builder still asserts byte-identical native blocks:
  - `getBsNpMetadata…npMetadata`;
  - `save…delete`;
  - the full original inline scripts.
- The form template, language file, helper, header token logic, rollback order and 58 dependency pins are unchanged.

**Runner.**
- Only the target table differs from R1, as asserted by the normalized-engine comparison and shown in the diff.
- The CSS byte count is consistent: 239150 + 1 + 9125 = 248276.
- The engine test applies the actual nine payloads and checks every after-hash, the marker no-op, partial rollback and fault restore.

## Reused R1 assessments (exact unchanged scope only)

These carry over only to the unchanged parts:
- the concurrency disposition (no materially new risk, no change needed to the protected writers, with no InnoDB scheduling proof);
- runtime, event, theme and InnoDB admission;
- the one-use packet flow;
- the POST, token and ID checks;
- the submit-handler ordering;
- the UI signature checks.

R2 introduces no new `!important`, timers or `fixed` positioning.

## Optional findings (recorded, not new tasks)

1. **Form path has the same corrupt-shape exposure, already present in native code.**
   - The protected `getBsNpMetadata` passes non-empty array labels.
   - The preserved native `address_form.twig` then string-renders them.
   - So editing such a legacy address can still fail. R2 does not introduce this.
   - Fixing it needs a protected-scope decision. Consider it during owner QA if any legacy corrupt rows are known.
2. **The card guard also rejects non-string scalars**, such as a numeric JSON `house`. Such a card falls back to the stored stock text. This is safe degraded display.
3. **The 991/992 boundary test does not discriminate.** Both sides assert `display:flex`, so it does not test fractional widths. The literal values are correct.
4. **Cosmetic log labels are stale:**
   - the browser console says "33 layout" while the results show 38;
   - the packet test prints "profile_packet_steps".
5. **O3 and O5 deferrals are accepted:**
   - The isolation-level fact is not collected.
   - An in-progress checkout keeps its existing selection; check this in owner QA.

## Closure

- **Release.** Owner Upload → Verify → separate Apply may proceed for this exact pair only. If Verify sees any source, dependency or metadata drift, STOP; do not adapt the pins.
- **Mechanical closure.** The expected receipts close the step mechanically:
  - `changed_files=9`;
  - the exact backup path;
  - `self_deleted=yes`;
  - `template_cache_cleanup=ok`;
  - `staged_runner_absent=yes`;
  - `RD17_WP1B_APPLY_OK_OWNER_QA_REQUIRED`.
- **Owner QA should confirm:**
  - Add, Edit and Delete keep the session;
  - the direct default button works and refreshes the list;
  - legacy and courier cards display correctly;
  - an existing checkout keeps its selection;
  - layout at 390, 768 and 1280 px.
- **Not covered by this review:** owner QA, full RD17 closure, any live InnoDB behaviour, and RD18, which still waits.
