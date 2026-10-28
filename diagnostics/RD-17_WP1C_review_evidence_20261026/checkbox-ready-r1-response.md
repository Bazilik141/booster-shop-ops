# RD17-WP1C: integrated CODE and composition review (default-address checkbox)

**Verdict: Review OK.** There are no mandatory findings. The optional findings are listed below.

## Identity binding

| Artifact | SHA256 (declared, bound) |
|---|---|
| Runner `patches/RD-17-WP1C_default-checkbox_20261026.php` (99726 B) | `5821c91f9e5f649cbfec65623ee3c118ccb1dd01e4804e592aad334c3165853a` |
| Owner packet `diagnostics/RD-17-WP1C_owner_20261026.ps1` (11875 B) | `7fa079594688c4389e3021a6ce9bdc3fedc08f9b9c6f2ea661b2945daa771fb4` |

- **Hashes are bound from labels, not recomputed.** I had no tools, so I took both hashes from the supplied labels.
- **Packet hash cross-checks.** The packet's embedded `$Sha` equals the runner hash.
- **Packet size is consistent with the parent.** The packet is 35 B larger than the WP1B parent packet (11840 B). That delta is exactly accounted for by the declared substitutions:
  - 4 × the task id (+7 each);
  - the receipt directory name (+6);
  - "nine" changed to "three" (+1).
  - The `RD17_WP1B_`→`RD17_WP1C_` and `changed_files=9`→`3` replacements are zero-length changes.
  - So no other logic changed in the packet.
- **Target table decodes consistently.** I decoded the head of the embedded target table, and it matches `targets.json`:
  - form: replace, `6792…`→`e7ad…`;
  - DS: `replace_block`, `44a3…`→`f141…`. Its `payload`/`noop_block` are the marked `default-check.css`, and `replace_from` starts at `.bs-address-default-card {`;
  - header: token, with `normalized_before`.
- **CSS byte count is consistent.** The new block (946 B) minus the old 5-rule block gives the +247 B change (248276→248523).
- **Scope of this verdict.** It covers only this exact pair. It does not inherit the WP1B verdict.
- **Not reviewed.** The screenshots were not viewed. The parent runner body was not supplied. I reviewed the runtime-admission block as written here; its byte-identity to WP1B R2 is the executor's assertion and I did not verify it.

## What I reviewed fresh

- The complete runner.
- The complete owner packet.
- The before/after form templates in full.
- The CSS splice and the header diff.
- `build.py`, `prepare-checks.py` and the four test harnesses, with their results.
- The controller and JS context.
- The checkout `shipping.index` excerpt.
- The scope amendment, the WP1B R2 verdict, the Apply receipt and the owner QA note.

## Payload semantics

**Default field (form).**
- The hidden `default=0` precedes a checkbox `default=1`. Both jQuery `serialize()` and native submission preserve DOM order.
- PHP's request parser keeps the last value, so the server sees:
  - checked → `'1'`;
  - unchecked → `'0'`.
- The `default` key is therefore always present, which matches the old radio pair. One of the two radios was always checked before.
- `saveAcc008`'s `!(bool)$post_info['default']` guard and the first-address auto-default are unchanged.
- The test discriminates:
  - It parses actual jQuery bodies with PHP 8.0.30 `parse_str`.
  - It compares them against the old radio form's parsed POST.
  - A reversed hidden/checkbox order would fail the checked case.
- The JS post-response `name===key` value write cannot touch `default`, because `save` returns only `error`, `redirect` or `success`.

**Label text.**
- `entry_default` ("Основна адреса") comes from `account/address` language data, which the form loads.
- It reaches the view through the same view/before language merge the old template already relied on for `text_yes` and `text_no`.
- It is printed with `|e`.

**Preservation.**
- Everything outside the default group and its marker is byte-identical, including the complete country/NP script tail. The builder asserts this and I confirmed it against the two supplied bodies.
- No application code changes: no PHP, JS, guard, model, NP, endpoint or checkout code is touched.

**Checkout.**
- The supplied `shipping.index` adopts the default only when `shipping_address` is empty.
- Keeping an already-selected address is the reviewed WP1B contract and is consistent with the owner's clarification. No change is warranted.

## CSS and UI

- The five WP1B-owned rules are replaced in place by an exact literal block. This is not an appended override.
- All rules are scoped to `.bs-account-page`.
- Signature scan:
  - no `!important`, timers, `absolute` or `fixed` in the new code;
  - the 44 px hit area and the 2 px focus ring are commented;
  - the 20 px glyph uses `var(--bs-s5)` and is commented;
  - the inherited NP `setTimeout`/dropdown code is outside the diff and unchanged.
- The browser tests ran in actual Chrome with the real DS/Bootstrap stylesheet and the real scripts. They checked:
  - one checkbox with an accessible name;
  - no yes/no text;
  - a label of at least 44 px;
  - no overflow at 390, 768 and 1280 px;
  - label click, Space, keyboard focus-visible, hover and active states;
  - a single save request;
  - the ACC009 Latin-name block still applies.

## Engine

**Apply path for `replace_block`.**
- The full-file before-hash is pinned first, so the anchor count check is a second, redundant guard.
- It then requires exactly one occurrence of `replace_from` and performs `str_replace`.
- The whole-file after-hash is then verified.
- This is sound and fails closed.

**No-op branch.** It reports `already_applied=yes` only when all of these hold:
- the form equals its after-hash;
- the new CSS block appears exactly once;
- the header matches its normalized before-state.

Behaviour:
- It survives later DS appends and token changes without retouching the token. This is tested, and consistent with conventions 5 and 8.
- Any partial state (block present but form not at its after-hash) falls through to `target_before_mismatch`.

**Ordering, backup and recovery.**
- Write order: CSS → form → header (token published last).
- Each target is backed up to `original-0`, `original-1` and `original-2`. Every target and backup is preflighted before any restore.
- Automatic restore after a post-write fault is tested.
- Partial rollback is tested: 2 files changed, with the dynamic header restored exactly.

**Lock and admission.**
- The shared `RD-17-WP1B…lock` correctly serializes the WP1B and WP1C writers.
- A stale lock fails closed (tested).
- The admission hash binds runtime facts, target rows (mode, uid, gid) and all 64 dependencies.
- The 6 added dependency pins equal the WP1B Apply after-hashes in the receipt.

**Interaction with the WP1B rollback.**
- After WP1C, the WP1B rollback hits `rollback_target_drift`, because the form and DS are no longer at the WP1B after-states.
- It fails closed rather than clobbering. The documented order (roll back WP1C first) is correct.

## Packet

- The flow is unchanged and one-use: Upload → Verify (admission, `production_writes=0`, `done=ok`) → separate Apply.
- `applyAttempted` is set before SSH, and replay is refused. This was tested on PowerShell 5.1 and 7 with the SSH/SCP boundary mocked.
- Cache cleanup runs only after the runner exits 0, via `&&`.
- The success checks are:
  - `changed_files=3`;
  - the exact backup regex;
  - self-delete;
  - `staged_runner_absent=yes`.
- Two outcomes stop the packet even though the runner exits 0:
  - an `already_applied=yes` result during Verify (no admission line);
  - the same result during Apply (`runner_did_not_self_delete`).
- The state file is new and binds both hashes. No WP1B state is reset or replayed.

## Optional findings (recorded, not new tasks)

1. **Rollback order republishes the old DS token before the CSS is restored** (`[0,2,1]`).
   - In a very short window, a client could cache the WP1C CSS under the WP1B token, then see unstyled old radios until that cache expires.
   - This is cosmetic, narrow and only matters on rollback; the pattern is inherited.
   - A future successor could restore CSS → form → header.
2. **Unchecking the current main address returns the existing `error_default` text** ("Ви не можете видалити основну адресу!"). It talks about deletion.
   - The behaviour is unchanged from choosing "Ні" before, and you correctly did not change the guard or wording.
   - Note this for owner QA. Rewording would be a separate, scoped language decision.
3. **No-op marker excludes the header by convention.** If an automatic restore ever failed after the CSS and form writes, a later run would report `already_applied` with a stale token. The packet already STOPs on that failure, so this is noted only.
4. **Minor uncommented typographic literals** (`.9375rem`, `line-height:1.4`). They are not pixel values. Dark theme and real fonts were not exercised beyond the pinned font; leave both to owner QA.
5. **Stale inherited comments** remain ("twenty-six targets", "twelve added dependency hashes").

## Closure

- **Release.** Owner Upload → Verify may proceed for this exact pair. Return the complete Verify receipt before a separate Apply. Any drift in the 3 before-states, the 64 dependencies, the header non-token bytes or the metadata is a STOP: reassess, do not re-pin.
- **Mechanical acceptance.** The Apply step closes mechanically if the receipt shows all of:
  - the 3 exact target states;
  - the backup path;
  - `changed_files=3`;
  - `self_deleted=yes`;
  - `done=ok`;
  - `template_cache_cleanup=ok`;
  - `staged_runner_absent=yes`;
  - `RD17_WP1C_APPLY_OK_OWNER_QA_REQUIRED`.
- **Owner QA should confirm:**
  - the checkbox looks right on mobile and desktop, in light and dark theme;
  - save works checked and unchecked for a secondary address;
  - the rejection message appears when unchecking the current main address;
  - an existing checkout keeps its selected address.
- **Not covered by this review:** live host, MySQL or InnoDB behaviour; aesthetic approval; full RD17 closure; RD18, which stays unstarted.
