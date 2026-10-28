# Review OK — RD-18 WP1 account orders, light-A, integrated ready review

## Binding identities

The verdict covers both artifacts together, as supplied. I did not compute hashes and used no tools.

| Artifact | SHA256 |
|---|---|
| Runner `patches/RD-18-WP1_orders_20261027.php` (162710 B) | `1219a01e03dbe8a51300bd0b0289f47bda21f6e029f720ef277bbd815b1da12e` |
| Owner packet `diagnostics/RD-18-WP1_owner_20261027.ps1` (11814 B) | `9010a8ce81f8beb17080996904ff4f27fcd3d3038709ef4a689f365a3b11bc51` |

Internal consistency checks:
- **Packet pins the runner.** The packet's embedded `$Sha` equals the runner hash above.
- **Embedded contract.** I decoded the start of the runner's embedded contract. The order.php before-hash (`05d7de4e…`) and after-hash (`7c7302b9…`) match the target table.
- **Payload hashes.** The nine payload hashes in `render-after-results.json` match the table's after-hashes.
- **Runtime guard.** The runner re-hashes every payload against its `after` value before writing, so a corrupted embedded payload fails closed.

This verdict is void for any other bytes.

## Mandatory findings

None.

## What was checked and accepted

**Scope and protected contracts**
- **Protected files untouched.** The model, the ACC007 library, the shell, checkout, NP and payment are not targets; their hashes are pinned among the 66 dependencies.
- **Query paths unchanged.** Queries, counts, `limit=10`, the hidden-status predicate, history order and the notify mask are the same.
- **G0 summary read (list).** It adds one existing `getProducts` call per order ID that is already owner-scoped. The first product is chosen by the smallest `order_product_id`, so the choice is stable.
- **Remaining-position count.** It uses the native position count, with correct Ukrainian plural forms.

**History and current state**
- **Recorded history only.** Each native row is shown once and no predicted future step is invented.
- **Current-state panel.** It uses the existing `bs_status_date`. That date is set only when the latest history row matches the current status, so the panel never claims a repeated, backdated or paginated row is current.
- **History paging.** The native `#history` fragment pager and its handler are preserved.

**Reorder and native actions**
- **Forms and handler.** Every `form-product-N` block and the entire inline handler are byte-identical (the build asserts this). The broad `$('form')` binding is pre-existing and no new forms are added.
- **Purchase styling.** The reorder button uses `--bs-buy`.
- **Other native actions kept.** Return, conditional PAY-003 credit link, subscription and reward entries remain, and the support link uses `rel="noopener"`.

**Snapshot and dates**
- **Recipient.** Name and middle name come only from the shipping bucket of the stored order (`shipping_custom_field.rd13_receiver_middlename`). This matches the pinned RD13 and RD17 writers. A missing, empty or malformed middle name is not invented, and no current-profile read occurs.
- **Creation date.** It reuses the ACC007 Kyiv parser with a truthful "unknown" label, instead of the old epoch-1970 fallback.

**Escaping**
- **New outputs.** Every new output decodes one entity layer and then escapes in Twig.
- **Raw outputs.** Only native or pre-escaped fragments render raw: pagination, history fragment, shell, breadcrumbs, content slots, hidden form values.

**D2 and H1**
- **One colour mapping on hub, list, detail and history.**
  - in_progress: gold-soft background / warning-fg text.
  - in_transit: blue-soft / blue.
  - completed: ink-2 / paper with a check icon.
  - cancelled and neutral: line-2 / ink-3.
- **Legacy styles.** The library's green style is no longer used on these surfaces.
- **Hub.** The hub controller adds only the group field. The hub Twig test shows identical output after normalising the one badge, and the address badge keeps its source rule.
- **H1 token.** It is read via regex, replaced wholesale, non-token bytes are pinned by the normalised hash, and a no-op never re-busts the token.

**UI signature audit**
- **No new `!important`, timers, absolute/fixed positioning or new tokens.** The only `!important` hits are inside a CSS comment.
- **Pixel values.**
  - `48px` / `44px` touch targets and `2px` focus rings are commented.
  - `999px` is the pill radius documented in TOKENS.md.
  - `1px` values are hairlines.
  - `767px` / `991.98px` are breakpoints aligned with the existing ones and the 768 QA width.
- **Focus style.** The buy button uses a box-shadow focus ring because of the global outline rule.
- **Mobile layout override.** `.bs-account-orders .bs-account-main {display:flex}` wins over the RD16 hub rule only by source order at equal specificity. It applies only to the order pages, so the accepted hub is not affected.
- **Chip script.** It changes horizontal scroll only, on DOMContentLoaded, and uses no timers.

**Runner**
- **Paths.**
  - Target paths are derived from cwd (`~/public_html`); `__DIR__` is used only for the private stage.
  - Stage and backup must be on the same device as the web root.
  - Path walks reject symlinks.
- **Locking.** An exclusive lock file is used.
- **No-op check.** It runs before admission and does not touch the token.
- **Admission.** It is computed at Verify from runtime facts, target rows with mode/uid/gid, and dependencies, and must match exactly at Apply.
- **Metadata reads.** They are read-only: inside a READ ONLY transaction, prepared statements with `bind_result`, no `get_result`. A different theme, layout, alternate template, event or hook set means STOP.
- **Backups.** They are hash-verified before writing, and lint runs on the stage copies.
- **Write order.** Labels and CSS first, then controllers, then views, then the header token last.
- **Failure handling.**
  - Post-write lint runs on the live file, with automatic restore of exactly the targets already changed.
  - Rollback preflights all targets and backups twice. It supports mixed partial before/after states and stops on foreign drift.
  - Restore order is views first, header last.
  - The runner self-deletes on success.

**Packet**
- **Steps.** Upload, Verify and Apply are separate. Apply is one-use, with `applyAttempted` recorded before dispatch, and replay is refused.
- **Cache cleanup.** It is chained with `&&` after the runner succeeds. A failed runner skips the cleanup and then fails the self-delete check.
- **Transport.** Strict host checking, BatchMode, agent endpoint, no forwarding.
- **Rollback path.** It is a separate owner decision and uses only the exact backup path saved from the Apply receipt.

**Platform**
- **PHP and MySQL.** The code is PHP 8.0-compatible and uses no mysqlnd-only APIs.

## Optional findings

None of these block deployment. Fold them into a later repair if one happens; any code change needs an affected review.

- **O1 — Comment line breaks are doubled.** Native `comment` is `nl2br()` output (`<br />` plus the original newline). Replacing `<br />` with `"\n"` and then applying Twig `nl2br` gives double breaks for multi-line customer comments. It is cosmetic.
  - Closure: remove the `<br />` without inserting a newline, or project the raw `order_info['comment']`.
- **O2 — Shipping and payment method names are now escaped.** Native rendered them raw. If any stored method name contains markup (for example a logo `<img>`), the markup will show as literal text. The tests used plain fictional names.
  - Closure: owner QA confirms real NP, Hutko, monobank and PUMB method names display cleanly.
- **O3 — Delivery phone source.** The delivery block shows the order's contact `telephone`, because OpenCart has no shipping telephone. For guest-origin receiver overrides this is the buyer's number, not the receiver's. The label "Телефон у замовленні" is truthful. QA awareness only.
- **O4 — Header-only partial state is treated as applied.** The no-op check accepts any well-shaped header token. A state where eight targets are after-image but the header still has the old token would report `already_applied` without a cache-bust. It is reachable only outside the runner's own automatic restore. Disclosure only.
- **O5 — Duplicated code to consolidate later.** The chip-reveal script is duplicated in two templates, and the RD16 shell labels are copied into `order.php`. Both risk future drift.
- **O6 — Small CSS cleanups.**
  - Add a one-line comment for the `767px` breakpoint and the timeline dot `2px` stroke.
  - `column_right` is still loaded but not rendered, which matches the accepted RD16 hub.
- **O7 — Contrast decided by the owner.** ink-3 on line-2 for the cancelled and neutral badges is 4.23:1 per TOKENS.md, below AA for small text. This was the owner's D2 choice; recorded for awareness.

## Evidence limits

- **No visual inspection by me.** No screenshots were supplied, so I make no visual-fidelity claim against the boards.
- **Admission not exercised against real MySQL locally.**
  - The DB and admission code is reused from the RD17 runner, which ran on host.
  - The host's enabled event set for RD18's new model and pagination triggers is unverified.
  - An unadmitted hook will STOP at Verify with `production_writes=0`. That is fail-closed and must not be adapted on production.
- **Twig syntax gated locally only.** Templates are compiled on the pinned ACC-008 Twig vendor. The host runner lints only PHP.
- **Fictional data only.** Real payment/shipping names, NP address formats, comments, file-type options, ownership and permissions are uncertified.
- **Not read in full.** I did not decode the full base64 payloads; I relied on the supplied after-tree, the matching hashes and the runner's runtime hash guard. I did not see the selector at `stylesheet.css:18`.
- **Packet transport mocked.** The PowerShell 5.1 and 7 tests mocked SSH/SCP.
- **Stale status field.** `release-facts.json` status was edited after `build-runner.py` wrote it. This is bookkeeping only.

## Next steps (not granted by this review)

1. Owner runs Upload.
2. Owner runs Verify. It must print `RD18_WP1_VERIFY_OK`; any `error=` or `packet_error=` means STOP and reassess, never a re-pin on host.
3. Owner runs Apply as a separate step.
4. Complete expected Apply receipt closes mechanically.
5. Owner performs QA, including O2.

This review grants no deployment, live data access, Git or Notion action.
