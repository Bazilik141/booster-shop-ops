# RD-17-WP1A-UI: integrated CODE/composition review of the compact profile action

## Verdict: **Review OK**, for owner Upload and Verify of the exact pair below

There are no mandatory findings.

**What this verdict covers:**
- the presentation package: two new checkout-only assets and two literal asset insertions in `checkout.twig`;
- its runner and owner packet;
- the bindings and rollback.

**What it does not cover:**
- **Apply.** The complete Verify receipt must be returned first.
- **Owner aesthetic and functional QA.** I did not visually inspect the generated screenshots; only their hashes were supplied.
- **Any agent server action.**
- **Any change to the installed bridge, Mono, the base runners, or the shared design-system CSS and header.**

| Artifact | SHA-256 | Bytes |
|---|---|---|
| `patches/RD-17-WP1A-UI_profile-action_20261026.php` | `1f9d7ee4a15d7fc4d8352d19243294cd35d0821043380f5a72a155c4f3b5e269` | 170663 |
| `diagnostics/RD-17-WP1A-UI_owner_20261026.ps1` | `76562834f3ce773369b4d7e67f9d9330c666decfbbf2b5bfc2add5630be93fba` | 11897 |

**How the hashes are bound.** These are the declared identities; I did not recompute any SHA-256. I checked the following cross-references by hand:

- **Packet to runner.** The packet's `$Sha` equals the runner hash above. `state.json` binds both the runner and packet hashes.
- **Embedded target table.** I base64-decoded the runner's target table. It contains:
  - the CSS file, `absent` → `eab7c2ce…9ace`, 3393 B;
  - the JS file, `absent` → `664d5f03…0258`;
  - the Twig template, `d8cd655d…7f46` → `5d16becd…0e90`, 60514 B.
  
  This matches `targets.json`.
- **Twig payload tail.** It decodes to the reskin script tag, then the new UI script tag, then `{{ footer }}`.
- **Embedded dependency table.** It contains:
  - the Mono controller at `bed53fa4…7a76`;
  - `checkout-reskin.js` at `dbafb5c8…e212`, the installed bridge after-state.
- **Payload integrity.** At `--check`, the runner enforces `payload_hash_mismatch` for every payload.

---

## Mandatory findings

None.

---

## Assessment

### 1. Scope and source cause

**The predicate is unchanged.**
- The button appears when the checkout tuple differs from the profile and a stored profile middle name exists.
- It stays hidden when the profile middle name is absent and the checkout middle name was entered manually.
- The browser cases cover both states, plus a Unicode-whitespace profile middle name.

**The root cause is identified correctly.** The reskin builds the button as `.bs-btn.bs-btn-ghost.mt-3` and appends it at the end of the card body. In the design-system CSS:
- `.bs-btn` sets height 44px and 0 16px padding (line 101);
- `.bs-btn-ghost` is transparent (line 125);
- Bootstrap `.mt-3` adds 16px of top margin.

**No shared rule is edited and no override is stacked.**
- The two new files are this component's own selectors.
- The design-system CSS and header are pinned dependencies, not targets.

**Twig changes are exactly two literal blocks.**
- `build-ui.py` asserts that removing them recovers `d8cd655d` byte-for-byte.
- The CSS `<link>` sits in `<body>`, after `{{ header }}`. That is valid, and it cascades after the design-system stylesheet.
- The pinned Twig compile shows that load order is correct (reskin first, then the UI script) and that the root data attributes are unchanged.

### 2. Presentation JS, including focus, tab order, lock, collapse and race behaviour

**Node move, not a copy.**
- The original `[data-rd17-profile-copy]` node is moved with its listener intact. Eligibility, revision, the queue, and the server refusal path all stay native.
- The reskin's `rd17EnsureProfileButton` finds the node with `recap.querySelector`, so it is still located after the move. No duplicate button is created.

**No double toggle and no nested button.**
- The head click handler returns whenever `target.closest('button')` matches.
- So keyboard Enter or Space on the toggle, and any click on the action or inside the toggle's SVG, are never forwarded.
- Only clicks on the chevron span or on the grid gap are forwarded.
- The forwarded `toggle.click()` bubbles back with the toggle as its target, so it does not recurse.
- A forwarded click on a disabled toggle (during the ACC-009 lock) is a no-op, so the lock is respected.

**No observer loop.**
- The new observer watches child-list changes only.
- The upgrade guard is the `bs-co-profile-copy` class.
- The reskin's hidden/style writes and the lock's `disabled` writes are attribute mutations, so they do not trigger this observer.
- The upgrade's own DOM moves trigger one further `enhance()` call, which returns early, and one throttled reskin `sync`, which is idempotent.
- The root `data-` marker prevents a second observer if the script is executed again.

**Timing.**
- The recap and button are created inside one reskin `sync` task.
- The observer microtask upgrades them before paint, so there is no flash of the old ghost button.
- Guest checkout never creates the recap, so the script stays inert there.

**Collapse and feedback.**
- The rule `(1,5,0) .is-collapsed > head > .bs-co-profile-copy { display:none }` reproduces the old in-body availability. It applies because the reskin sets `style.display=''` when the button is shown.
- `[data-co-receiver-status]` remains in the body. So refusal and validation feedback is visible whenever the action is visible.
- Mobile initial collapse behaves as before.

**Tab order.** It changes from fields → action to toggle → action → fields.
- The action is still keyboard-reachable through toggle, then Tab; the test covers this.
- Shift+Tab from the first name skips the action, because it is hidden while a name field is focused. That hiding is pre-existing logic.
- **Lost focus.** After a keyboard copy, the pending state hides the focused button and focus falls to the body. This existed before the change; the navigation starting point is now in the header. See optional finding O-2.

**Selectors.** All the selectors this script depends on come from the pinned reskin `dbafb5c8`. If they drift, the script fails soft and leaves the original button in place.

**XSS.** The SVG string is static and the caption is set through `textContent`. No user data reaches an HTML sink.

### 3. CSS signature scan

| Signature | New files | Existing (pre-existing, untouched) |
|---|---|---|
| `!important` | none | Twig inline styles, design-system r4–r7 rules |
| `setTimeout` / `setInterval` | none (MutationObserver plus a guard only) | reskin `scheduleSync` and captcha `setInterval`, Twig enhancement timers |
| `position: absolute` / `fixed` | none | Twig radio `absolute`, submit loader `fixed` |
| Magic pixels | All commented: 36px and 44px are token arithmetic; 13px and 12px are label sizes; 128px is the label max-width; 2px outline and offset. Uncommented but conventional: `1px` border, `.6` disabled opacity. | — |

**Contrast and states.**
- Default contrast is 8.9:1, which passes AA.
- The `:active` state uses paper text on blue.
- There is a visible `:focus-visible` ring.
- Hover changes only the border colour, which is subtle. That is for owner QA.

### 4. Runner and packet

**Build provenance.**
- The runner is built deterministically from the reviewed r3 bytes `ef0d7a7d…`.
- The replacements are enumerated and count-asserted:
  - the target and dependency tables;
  - `RD16_ID`;
  - the lock path, kept deliberately at `RD-17-WP1A_profile-receiver_20261026.lock`;
  - the apply order `[0,1,2]` and rollback order `[2,1,0]`;
  - `changed_files=3`.
- The runtime admission block is byte-identical to r3 (`8a8d66ac…`).
- I read the full runner. There is no other index-specific logic.

**New-file behaviour.**
- A new target that unexpectedly exists produces `target_before_mismatch`.
- New files are created 0644, outside the web root in staging, on the same device, and moved into place with an atomic rename.
- Only `original-2` is backed up.

**Write order and failure.**
- Assets are written first and the view last. So no window exists in which the Twig template references a missing asset.
- Rollback restores the view first, then removes JS and CSS. Its preflight checks every target state and the backup hash before any write.
- The partial-restore and post-write-failure paths are tested.

**Lint coverage.** `rd16_lint` does nothing for `.css`, `.js` and `.twig` files. Twig validity is therefore proven only by the local compile under the pinned Twig version, which is acceptable for two literal insertions.

**Packet.**
- The packet keeps the reviewed r3 structure.
- Its receipts directory, staged basename and backup regex are new: the regex is `RD-17-WP1A-UI_profile-action_20261026-<UTC>-<12hex>`.
- The cache cleanup heredoc delimiter was renamed consistently at both ends.
- The cache cleanup still runs only after `&&` and is still followed by `runner_did_not_self_delete`.
- Apply and Rollback remain one-use, and strict host-key checking is kept.

**Dependency count.** There are 113 dependencies: the 107 accepted ones (including Mono `bed53fa4`) plus the six installed bridge after-states. The view is excluded because it is the target. Any drift stops the run; nothing is blindly re-pinned.

### 5. Test fidelity

**Real components exercised:**
- `common.js`, the ACC-009 coordinator and the reskin;
- the new assets;
- the generated recipient DOM;
- the pinned design-system CSS, Bootstrap and Manrope;
- the pinned Twig compile;
- the PHP 8.0 file engine;
- the packet's abstract syntax tree and state graph on PowerShell 5.1 and 7.

**Faked or synthetic:**
- the host shell and HTTP services;
- the runtime metadata and dependency hashes;
- SSH and SCP.

**Not exercised:** the full Twig inline `enhanceCheckout` observer. On reading it, that observer only touches the register, NP and confirm areas, never the recap header, so no interference is evident.

None of these limits blocks Upload or Verify.

---

## Optional findings (recorded, not blocking)

**O-1. Mobile header alignment.** At ≤900px the receiver head is narrower than its sibling cards.
- **Left inset:** 12px, against 16px at ≤640 and 20px at 641–900 on sibling cards.
- **Icon box:** 26px, against 30px.
- **Chevron right margin:** 12px, against 16/20px.

The result is that the card icons and chevrons in the stacked layout no longer line up. If owner QA objects, keep the standard paddings and restrict the compaction to the narrowest widths (for example ≤360px).

**O-2. Focus after a keyboard copy.** After a keyboard-initiated copy, consider moving focus to the first name field or to the status line instead of letting it fall to the body. This behaviour existed before the change.

**O-3. Clipped focus ring on the toggle.** The new toggle `:focus-visible` outline uses a +2px offset. The card's `overflow:hidden` clips it on the top and left edges. A negative offset would keep the whole ring visible.

**O-4. Leftover gap and cursor.** When the action is hidden, a leftover column gap remains: 8px on desktop, 4px on mobile. The forwarded gap area also shows the default cursor rather than a pointer. Both are cosmetic.

**O-5. Degraded states.**
- If the UI JS is missing, the original ghost button in the body remains and is fully functional.
- If the UI CSS is missing but the JS loads, the header is unstyled but still functional.

Owner QA should include a hard reload. The new `?v=` tokens should prevent stale assets.

**O-6. Release sequencing.** Add this to the report.
- The UI pins Mono `bed53fa4` and every base after-state. If the Mono diagnostic is removed or any base file changes between UI Verify and Apply, the run stops (STOP).
- After UI Apply, base WP1A rollback (the r3 pin `d8cd655d`) and any Mono or base recovery will refuse with `rollback_target_drift`.
  - Run the reviewed UI rollback first, or build a successor pinned to `5d16be`.
  - Never invoke the old runners by hand.
- Any future writer of `checkout.twig` or of the reskin recap (WP1B) must pin `5d16be` and the selectors listed in section 2.

---

## Closure

1. **Mechanical check.** Before Upload, confirm both hashes with `Get-FileHash`, and confirm that `work/rd17-profile-ui-20261026/owner-receipts/` does not yet exist.
2. **Owner Upload.** Expect `RD17_WP1A_UI_UPLOAD_OK`.
3. **Owner Verify.** Expect, in order:
   - `php_version=8.0.x`;
   - `admission_sha256=<64hex>`;
   - `syntax_gate=ok`;
   - `production_writes=0`;
   - `backup=none`;
   - `done=ok`;
   - `RD17_WP1A_UI_VERIFY_OK`.
   
   Return the complete Verify receipt before Apply. Any `error=`, `packet_error=` or STOP is a held outcome: no re-run and no re-pinning.
4. **Separate Apply.** Expect:
   - `changed_files=3`, with the target lines as tabulated above;
   - `self_deleted=yes`;
   - `done=ok`;
   - `template_cache_cleanup=ok`;
   - `staged_runner_absent=yes`.
5. **Owner QA.** Check each of the following:
   - **Desktop** at 1280 and about 1000px, and **mobile** at 390 and 320px: header layout and the O-1 alignment.
   - **Keyboard:** Tab from the toggle reaches the action; Enter copies.
   - **Chevron:** clicking it collapses and expands the card; the action is hidden while collapsed.
   - **Predicate**, both directions:
     - the button appears after clearing the checkout middle name when the profile has one;
     - it does not appear when the profile has no middle name.
   - **Copy result:** the copy saves without a page reload and leaves the telephone unchanged.
   - **Guest checkout:** unchanged.
6. **Bookkeeping.** Record O-6 and this verdict. Address WP1B waits for the UI owner QA. RD-18 remains unstarted.
