# RD17-WP1B integrated CODE/composition review

**Verdict: Changes required.** One mandatory code defect blocks owner Upload/Verify/Apply. One mandatory evidence or decision item also remains open.

## Identity binding

| Artifact | SHA256 (as declared) |
|---|---|
| Runner `patches/RD-17-WP1B_addresses_20261026.php` | `0bcb27c2fa60fdb9c8104c5101d427c64e2cb3bc1dda46e2ab739762259cb41b` |
| Owner packet `diagnostics/RD-17-WP1B_owner_20261026.ps1` | `858d2263ee64a38b40c62eaa06740155f1176c347bfb52d544a79b2885b78144` |

- I bound these hashes from the supplied labels. I did not recompute them.
- I reviewed the full runner and packet text, the decoded nine payloads (via the supplied tree-after bodies), the protected sources, the tests and the results.
- I saw no images.
- This verdict applies only to this pair.

## Mandatory findings

### M1 — Double-encoded URLs break Add, Edit and Delete, and log the customer out

**Cause.**
- OpenCart `url->link()` (non-js) already returns `&amp;`-encoded URLs. Your own fixture models this, and RD16 shell links are `html_entity_decode`d for this reason.
- The new templates escape those URLs a second time:
  - `address.twig`: `href="{{ add|e('html_attr') }}"`
  - `address_list.twig`: `{{ address.edit|e('html_attr') }}` and `{{ address.delete|e('html_attr') }}`

**Effect.**
1. After HTML parsing, each `href` contains a literal `&amp;`. PHP then receives keys `amp;customer_token` and `amp;address_id`.
2. `login.validate` fails and the controller redirects to `account/login`.
3. `account/login` finds `session.customer_token` set, so it shows `error_token` and calls `logout()`.
4. Delete via JS follows `json.redirect` into the same logout.

**Why tests missed it.** The browser test routes `**/address.delete**` with a glob and never checks the query. The Edit and Add links are never followed.

**Fix.** Use one of the following:
- `html_entity_decode` `add`, `edit` and `delete` in the controller (same pattern as `rd17_list` and `rd17_default`), then keep `|e('html_attr')`; or
- print them raw, as the native code did.

**Closure.**
- Add discriminating assertions that parse the resolved `href` and `this.href` for Add, Edit and Delete. Each must:
  - contain `customer_token` and `address_id` (where applicable);
  - contain no `amp;`-prefixed keys.
- The delete and default route handlers must assert the query parameters.
- Rebuild targets, runner and packet. Both hashes will change.
- Rerun render, browser, runner-engine and packet mock tests.
- Send a fresh narrow review of the delta.

### M2 — The address card omits recipient names, contrary to the handoff

- The handoff acceptance criteria require the stored full name on each card.
- The scope amendment waives only *address-book patronymic display*.
- The claim "list intentionally omits recipient names per owner's accepted direction" has no supporting evidence in the supplied documents.

**Closure.** Use one of the following:
- Record the exact owner statement. This closes mechanically, with no code change.
- Or render stored `firstname` and `lastname`, escaped. Do this in the same M1 repair batch, with an escaped long-name check.

## Concurrency boundary (special attention)

**Decision: no material new unresolved risk. No change to the protected writers is required.**

**The new helper:**
- runs a locked customer range `ORDER BY address_id FOR UPDATE`;
- does a single `CASE` UPDATE scoped by `customer_id`;
- runs an in-transaction confirm read that rejects any count other than exactly one;
- does no JSON, text, session or NP writes.

**What it cannot cause.** It cannot itself commit a two-default state.
- Under READ COMMITTED, a checkout `addAddress` that interleaves is caught by the confirm read, which rolls back.
- Under REPEATABLE READ, gap locks block the insert until commit.

**Residual outcomes:**
- **Zero default.** Possible when it interleaves with unmodified native writers:
  - native delete's precheck runs outside its transaction;
  - non-transactional checkout `addAddress` plus its "others = 0" UPDATE under READ COMMITTED.
  - These race classes already exist between native writers themselves.
  - The outcome is self-healing: the next checkout add sets the default, or the user presses the button again.
- **Deadlock.** Possible when ACC008 locks row X and then updates the others. InnoDB rolls back one side and no data is lost.
  - If ACC008 is the victim, an uncaught driver exception surfaces as a failed save. The new JS locks the form and advises reloading.

**What is not proven.** The fake-DB tests do not prove InnoDB scheduling or isolation behaviour. The context correctly does not claim they do.

## Interaction checks passed (subject to M1)

- **POST, token and ID checks.** Requires POST; `login.validate` token in the URL; digit-only bounded `address_id`; ownership enforced inside the lock; generic errors only.
- **Submit handler order.**
  - ACC009 capture validation stops events at document capture.
  - The native NP target handler runs first. The new handler honours `isDefaultPrevented`.
  - `common.js` now ignores the form because the `ajax` marker is removed.
  - The serialized payload equals the native one (tested).
- **Protected source blocks are byte-identical.** This covers the ACC008, NP, save and delete blocks and the original inline scripts. The builder asserts it.
- **Session behaviour.** Checkout session selection is unchanged, which is consistent with how the native form handles default changes.
- **Runner:**
  - 9 targets, with an apply/rollback order that has acceptable transient windows;
  - a dynamic token with a normalized non-token check (H1 respected);
  - `already_applied` works by content marker and survives later CSS/token changes;
  - full-target backup preflight and partial restore;
  - InnoDB metadata, all-store theme, alternate templates, layout and event multiset admission;
  - 58 dependency pins, with refusal on drift;
  - no DB writes.
- **Packet:**
  - strict hosts and agent, one-use admission, no replay;
  - `&&`-chained cache cleanup plus a self-delete check that fails closed;
  - rollback requires a recorded exact backup.
- **UI signatures:**
  - no new `!important`, timers or `fixed`;
  - `absolute` is used only on the NP dropdown, as at the original source, with a comment;
  - the 232, 800, 96, 999, 48 and 44 px values are explained;
  - box-shadow focus is justified by the `outline: none !important` rule at `stylesheet.css:18`.

## Optional findings (recorded, not new tasks)

1. **Breakpoints.** `max-width:991px` and `767px` differ from RD16's `991.98px` and the native `767.98px`. At fractional widths, RD16's mobile `display: contents` main could apply without the address override.
2. **Escaping of legacy stored text.** Legacy fallback and courier fields hold request-cleaned, entity-encoded stored text. `|e` may show `&quot;` or `&amp;` literally. Consider `html_entity_decode` before escaping, or check this in owner QA.
3. **Isolation level.** Record `@@transaction_isolation` as a bounded Verify fact.
4. **List refresh.** `refresh()` would insert login HTML into the list if the token check fails. Guard it with a marker or content-type check.
5. **Owner QA note.** An in-progress checkout keeps its already-selected address after a default change.

## Bounded closure

1. Repair M1, and M2 if code is chosen, as one batch.
2. Rerun the affected render, browser, runner and packet tests with the new assertions.
3. Regenerate the runner and packet.
4. Send a fresh narrow review that binds both new hashes.

If M2 is closed by an owner statement only, that part closes mechanically.

Not covered by this verdict: the owner Upload/Verify/Apply steps, owner live QA and full RD17 closure all remain separate gates, and RD18 continues to wait.
