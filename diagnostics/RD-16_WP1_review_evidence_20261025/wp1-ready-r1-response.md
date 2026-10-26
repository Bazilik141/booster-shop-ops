# RD-16 WP1 Integrated CODE/Composition Review

**Verdict: Changes requested**

**Bound executable identities.** I took these from the input pins. I did not recompute them.
- Runner `patches/RD-16-WP1_account-shell-hub_20261025.php`: 79,815 bytes, SHA-256 `941df6b338b2f65bbefac4ca978068f26453c7214f3856d0a867faf884d9a9e6`
- Owner packet `diagnostics/RD-16-WP1_owner_20261025.ps1`: SHA-256 `dbc0efc9c69353a6e2eab37b257b9db3017070eb9984e31a22c5b79b8e4b740d`

The verdict covers only this pair. It does not adopt the owner Upload/Verify/Apply launchers. Fixing M1 and M2 changes the runner bytes, which changes the runner hash pinned inside the packet, so a new pair needs a fresh integrated review.

These gates stay in place:
- The owner alone deploys, through separate Upload, Verify and Apply actions.
- Runtime host admission happens only at the owner Verify step.
- Owner QA follows Apply.
- WP2 waits until the owner QA-passes the deployed WP1.

This review grants no Git, status, deployment, key or server authority. The held Git block is out of scope.

## Mandatory defects

### M1. The event-hook admission check is weaker than the dispatcher it is meant to guard
**Where:** `rd16_runtime()`, the event loop.

The report and the code comment promise that any other enabled hook that can modify these views or controllers stops Apply. The check does not deliver that, for two reasons.

**1. The matcher can miss hooks that the live dispatcher would fire.**
- The check uses `fnmatch($pattern, $trigger)`, which only matches the whole trigger string.
- OpenCart's `Event::trigger` matches with a regex anchored at the start but not at the end (`'/^' . … . '/'`). It therefore fires on prefixes too.
- Example: a DB trigger `catalog/view/account/account` or `catalog/controller/account/` fires on our routes live, but passes this check.
- `system/engine/event.php` and the action-event config are not among the collected or pinned sources. So the guard cannot claim it mirrors the live semantics.

**2. The loop stops checking after the first match.**
- Each event's trigger loop `break`s on the first matching trigger.
- A broad pattern (for example `*`) with action `event/theme` gets admitted on `view/account/account/before`.
- It is then never checked against the `controller/...` or `/after` triggers it also matches.

**Fix:**
- Make the matcher a superset of the dispatcher whatever its exact semantics: treat a match as `fnmatch($pattern, $t) || fnmatch($pattern . '*', $t)`.
- Evaluate every one of the ten triggers for each event.
- Fail if any matched trigger is not a `view/…/before` trigger with an admitted theme action.
- Keep the zero-theme-row requirement.
- Extract this into a small pure function and cover it with a fixture. Include an exact match, a prefix-only trigger, `*` with `event/theme`, and an `admin/` trigger.

### M2. On ≤991px, keyboard and screen-reader order does not follow the visual order
**Where:** the hub plus shell markup and the mobile `display:contents` / `order` CSS. This is a WCAG 2.4.3 (Level A) and 1.3.2 issue.

The DOM order is:
1. Shell: nav, then the help section with its Telegram link, then the theme section.
2. `#content`: welcome h1, the four cards with their links, then the latest order and its CTA.

Below 992px the CSS reorders this visually to: welcome, nav, cards, latest, help, theme.

So a Tab press goes from the last nav item to «Допомога», which sits visually below the latest-order row. Focus then jumps back up to the four card links. Screen readers also read «Потрібна допомога?» and «Тема сайту» before the H1 and the cards. The UI fixture checked that disabled targets are inert, but never checked focus order against visual order.

**Fix (either is acceptable):**
- **Preferred:** make the DOM order of interactive content match the mobile visual order.
  - Let the shell render the nav block and the help/theme aside separately, for example two returned keys or two calls.
  - Render the aside after the main content in the hub template.
  - Use `grid-template-areas` or `grid-row` so the aside still sits under the nav on desktop.
- **Alternative:** keep the current DOM and change the mobile `order` so help/theme follow the nav directly. This deviates from the board order.

In either case, add a UI assertion: on 390 and 768, the Tab sequence after the nav must reach the card links before the support link.

## Optional notes

**Hardening**
- **O1. Write order.** Apply renames `account.php` and `account.twig` before `shell.php` exists. For a few milliseconds a request can get an `\Exception` from `load->controller('account/shell')`. With `autoescape:false`, `{{ rd16_shell }}` would print the exception's message and stack trace. Writing the new shell, language and CSS files first, then the header and Twig, then `account.php` last removes that window.
- **O2. Rollback race.** Rollback stages candidates based on the first plan, then writes based on the second. If a row changes from `before` to `after` between the two plans, `$rd16_stage[$i]` is undefined. That happens mid-loop, possibly after earlier restores. Either require the second plan's `current` values to equal the first's, or stage from the second plan. Also re-hash each candidate before the rename.
- **O3. Candidate location.** Rollback and auto-restore candidates (`restore-*.php`, `auto-restore-*`) are written inside the webroot backup directory. The private `$stageRoot` is on the same filesystem and would avoid placing transient `.php` files under `public_html`.
- **O4. Loose header no-op check.** The no-op check accepts any header token. After a failed automatic restore, a state of six targets at "after" plus the old header token would report `already_applied=yes`. This is benign under the packet's one-use Apply rule, but worth documenting.

**CSS and UI**
- **O5. Hover underline.** `.bs a:hover { text-decoration: underline }` has specificity (0,2,1), which beats `.bs-account-page a { text-decoration: none }`. Nav items and the blue or ink action buttons therefore underline on hover, unlike the board. Add scoped `text-decoration:none` to their `:hover` rules.
- **O6. Undocumented sizes.** `min-height:56px` (mobile rows) and `max-width:36rem` are not covered by the CSS comment. All other px values are explained. No `!important`, `setTimeout`, absolute/fixed positioning or green token was found in the new CSS or markup.
- **O7. Other languages.** The labels exist only in the `uk-ua` extension file. If any other storefront language is enabled, the hub will show raw `rd16_*` keys. Confirm the store is single-language, or add a fallback.

**Owner QA and report**
- **O8. Extra QA checks.**
  - The profile-save success message now lands on the restyled hub, with `|e` escaping and `role=status`. Confirm it renders correctly after a save. WP1 does not touch the edit page.
  - If the hub shows an empty shell or raw keys right after Apply, consider OPcache timestamp validation before treating it as a code failure.
- **O9. Expected Verify stops.** A plausible Verify STOP is an analytics or SEO extension hooked to the `common/header` view. That outcome is correct fail-closed behaviour. It needs a new reviewed pair, not an in-place edit.
- **O10. One-use Apply is strict.** If admission drifts between Verify and Apply, the runner refuses with zero writes, but the packet has already consumed Apply. A new packet is then required. State this in the report.
- **O11. Rollback stdout contract.** Document the rollback-check and rollback stdout lines explicitly: `backup=`, `rollback_preflight=ok`, `rollback=ok`, `changed_files=N`, `self_deleted=yes`, `done=ok`.

## What reviews clean
- **Data sources.**
  - The latest order uses the unchanged `getOrders(0,1)`. Its `publicOrderScope` already enforces customer, store, status > 0 and ACC-007 hidden status 15.
  - The label comes from `Acc007OrderStatus::presentation`.
  - The favorites count uses the same `account/wishlist.total` call as the header.
  - The address uses the customer-scoped `getAddress` and renders no `custom_field` keys.
  - All reads are SELECTs, and no model or library body changes.
- **Shell mechanism.**
  - The shell is an explicit child render with no includes and no global rollout.
  - The legacy sidebar is omitted at the hub caller only.
  - A direct request to the `account/shell` route returns an empty string.
- **Escaping and links.**
  - Dynamic text uses `|e` and URLs use `|e('html_attr')`.
  - Links are decoded once from the controller and keep the existing logout route and arguments.
- **Runner.**
  - It is fail-closed throughout: exclusive lock, path and symlink walks, before/absent admission, header check by normalized hash, 32 dependency pins, and an admission hash that includes runtime facts and permissions.
  - Lint and payload hashes are checked before backup.
  - Live state is rechecked before the first write.
  - Restore is automatic, but only after a full plan check.
  - Rollback checks every target and backup before any write.
- **Packet.**
  - Native arguments are allowlisted, and stdin is sent LF-only with no CR.
  - The receipt is written before any gate check.
  - Runner and packet SHAs are bound across steps.
  - Apply and rollback are consumed before dispatch.
  - PHP path, admission and backup values are pattern-checked before being inserted into the remote script.
  - Under `set -e`, `apply && cache` still exits nonzero on runner failure, via the self-delete check.
- **Tests.** The fake boundaries are declared and proportionate. Host mysqli admission remains unexercised by design and is gated at owner Verify.
