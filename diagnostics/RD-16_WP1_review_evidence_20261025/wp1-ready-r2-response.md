# RD-16 WP1 integrated CODE/composition review, R2

**Verdict: Changes requested**

**Bound executable identities.** These are the values pinned in the input. I did not recompute them.
- Runner `patches/RD-16-WP1_account-shell-hub_20261025.php`: 82,227 bytes, SHA-256 `c05e9b50ed7a1a95a7c877cc2142341332eb9679b8cfb1532a871932b0ef9a4e`
- Owner packet `diagnostics/RD-16-WP1_owner_20261025.ps1`: 11,830 bytes, SHA-256 `37840c42e5d7c4d01ffc83779eb3e3e43d0add699c522b34d7e419cd90997c7f`
- The packet pins the runner hash, `runner-results.json`, and `local-checks.json` all bind this same pair.

**Scope.** This pair is not adopted for owner release. The owner Upload, Verify and Apply steps stay HELD. Because the fix below changes the runner bytes, it also changes the runner hash pinned in the packet, so the next pair needs an affected R3 review. No Git, status, deployment, server or key authority follows from this verdict. Host PHP 8.0, MySQL, the live dispatcher, global JS, OPcache and authenticated UI remain unproven, as the package itself declares.

## R1 closure status

### M2 (DOM and focus order): closed
- **Child controller.** `Shell::index` returns `nav` and `aside` as separately rendered blocks. Both come from the same view with a part flag. With no trusted context, or when the customer is not logged in, it returns two empty blocks.
- **Hub DOM.** The order is nav, then `#content` (welcome, cards, latest), then aside. No help or theme block comes before the main content.
- **Desktop.** The grid uses areas `"nav main" "aside main"`, so the aside sits under the nav. Tab order is nav, then main, then aside.
- **Mobile (≤991px).** `#content` becomes `display:contents` and is reordered to welcome(1), nav(2), cards(3), latest(4), aside(5). Interactive DOM order is nav, four card links, latest CTA, support, which matches the visual order.
  - The nav's help chip is `display:none` in hub mode, so it is not focusable.
  - Welcome has no focusables, so placing the H1 before the nav visually is acceptable.
- **Test.** It focuses Logout, presses Tab six times, and expects `card×4, latest, support` at 390, 768 and 1280. This would fail the R1 DOM. The inert, hover and active checks still pass.
- **No regressions found.** There is no duplicated nav and no global rollout, and the legacy `column_left` call is still omitted only at the hub caller.

### M1 (event admission): substantively repaired, but not yet a superset
What is now correct:
- `rd16_event_check()` is a pure function.
- It matches when either `fnmatch($p,$t)` or `fnmatch($p.'*',$t)` succeeds.
- It has no first-match `break`. All ten triggers are evaluated, and any matched trigger that is not `view/…/before` with an admitted theme action fails.
- The zero-theme-row gating is retained.

Comparing `fnmatch` with the dispatcher's start-anchored, unescaped `preg_quote` regex:
- Bracket and backslash differences only make `fnmatch` over-match, which is safe.
- Case sensitivity is the same.

The ten pure cases discriminate correctly. One registration path remains uncovered; see M1-R2.

## Mandatory defect

### M1-R2. A DB trigger with a `system/` prefix is classified irrelevant and silently admitted
**Where:** `rd16_event_check()`, the line `$pattern=preg_replace('~^catalog/~','',$pattern);`.

**The problem.**
- Upstream OpenCart 4.0.x `catalog/controller/startup/event.php` registers DB events whose first segment is `catalog`, with the prefix stripped.
- It also registers events whose first segment is `system`. Depending on the point release, that prefix is stripped or kept.
- The startup controller is not among the pinned sources, so the guard cannot assume either behaviour.
- Today a row such as `system/view/account/account/before → extension/x/render` gives `relevant=false, allowed=true`. Neither `fnmatch` form can match the bare `view/…` triggers. The row would pass Verify, which breaks the claim that "any other enabled hook that can modify these views/controller stops apply."

**Fix.**
- Strip a leading `catalog/` **or** `system/` before matching, for example `~^(?:catalog|system)/~`.
- Alternatively, fail closed on any first segment other than `admin` or `catalog`.
- Add pure cases that must return relevant and disallowed:
  - `system/view/account/account/before` with a custom action
  - `system/controller/account/`
- Keep the existing ten cases.

**What not to change.** An unadmitted runtime hook found at owner Verify is still a legitimate STOP before writes. It must never be relaxed or replayed.

## Optional hardening verified

| Item | Status |
|---|---|
| O1 publish order | Children, labels, CSS, header and hub view are published before `account.php`, so the missing-child exception window is gone. |
| O2 rollback race | A second full plan must show identical `current` snapshots, and every staged candidate is re-hashed before the first write. The race and tamper derivatives refuse with target state unchanged. |
| O3 candidate location | Rollback and auto-restore candidates are written to private same-filesystem staging. Restore order puts the old view and controller back before the new children are removed. |
| O5 / O6 CSS | Scoped `:hover` rules (0,3,0 and 0,4,0) beat `.bs a:hover` (0,2,1) and keep `text-decoration:none`. The 56px rows and the 36rem limit are documented. |
| O7 / O8 | The single-language position and the unchanged edit redirect (`edit.php` 207-209) are accepted as stated. Both stay WP2/QA scope. |
| O10 / O11 | The one-use Apply consumption and the rollback stdout contracts are documented. |
| Packet | Saved PHP path and admission are re-checked against their patterns on load, and backup is pattern-checked before use. The runner and packet SHA bindings across steps are unchanged. |

## Optional notes (non-blocking)

1. **Twig publish order.** `account.twig` is renamed before `account.php`. For a few milliseconds the new view can render with the old controller's data: an empty shell and blank labels. There is no exception leak, assuming non-strict Twig variables. Publishing `account.php` just before the view gives a more graceful window, because the old view simply ignores the new variables.
2. **Leaked auto-restore candidate.** The `RD16-auto-restore-*` candidates are not registered in `$rd16_stage`. If a rename fails there, a private file is left behind. Consider tracking them for cleanup in `finally`.
3. **Empty content blocks.** `content_top` and `content_bottom` may render whitespace-only output. In that case `:empty` will not hide the wrapper and an extra grid or flex gap appears. This is cosmetic, but worth checking at owner QA.
4. **`:visited` colour.** Confirm that DS has no `.bs a:visited` or similar (0,2,1) rule. Such a rule would beat the (0,2,0) component colours on visited links.
5. **R1 O4 still open.** The loose header no-op check is unchanged. It is benign under one-use Apply.
6. **Method-qualified triggers.** The trigger list does not cover forms like `controller/account/account.index/*`. These fire only for non-canonical URLs, which makes this low risk.

## Next step
Repair M1-R2 and add the two pure cases. Then rebuild the runner, update the packet pin, and submit the new pair for an affected R3 review covering only the predicate, its tests and the new hashes. Owner release stays HELD until then.
