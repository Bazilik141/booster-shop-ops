# RD-16 WP1 affected R3 review: event-namespace predicate, fixtures and identity binding

**Verdict: Review OK**

## Bound identities

These are the pinned values from the input. I did not recompute them because I had no tools.

| Artifact | Bytes | SHA-256 |
|---|---|---|
| Runner `patches/RD-16-WP1_account-shell-hub_20261025.php` | 82,692 | `c8a8cc5559f09fd57e4c027b6f7aac8ec939210a0e090f31980fe53c5abfda1a` |
| Owner packet `diagnostics/RD-16-WP1_owner_20261025.ps1` | 11,830 | `c99761454e87b9053dee64583318cb6213b2fa9d9944d2357f57643fb730aeaf` |

**These identities agree across the package:**
- `local-checks.json` and `r3-equivalence.json` carry the same pair.
- The packet keeps its exact R2 length, which fits a swap of one 64-hex literal.
- The runner grew by 465 bytes. That fits the supplied block: a widened docblock, a two-line comment, a do/while strip loop, and four `.index` targets.

**What this verdict covers and does not cover:**
- It applies only to this exact pair.
- It certifies nothing about host PHP 8.0, MySQL, the live dispatcher or config hooks, OPcache, global JS or authenticated UI.
- It grants no Git, status, SSH, key, server or deployment authority.
- The owner's separate Upload, Verify and Apply steps stay owner-only.
- Native Verify may still legitimately fail closed before any writes.

## Scope of reuse

**Change boundary.** I accept the declared boundary as attested:
- The runner is byte-identical to R2 outside the predicate block (outside-block digest `f31c2456…`).
- The packet differs only in its runner-SHA literal.

**Reused R2 results.** Within their fake boundaries, I reuse R2's acceptance of:
- the DOM and focus order,
- publish and rollback,
- CSS, cache and owner sequencing,
- the ten-case file engine, the five backend cases and the seventeen UI cases.

A change confined to a pure function that those tests never call does not invalidate them. I did not reopen them.

**One reliance stated explicitly.** I rely on R2's full-runner reading that the unchanged runtime caller passes every enabled `trigger`/`action` row to `rd16_event_check()` with no SQL prefix pre-filter. R2's own M1-R2 finding presupposes this: a `system/` row was classified, not dropped. The strip fix is effective only under that reading.

## M1-R2 closure: closed

### Registration model

The historical `startup/event.php` (interaction evidence only) handles DB rows like this:
- A `catalog/…` row is registered with its first segment shifted off.
- `$part[0]` is then re-tested against `system`. So `catalog/system/…` registers both `system/…` and the full original trigger.
- A row whose first segment is `system` is registered with its full trigger.

The dispatcher matches with a start-anchored glob. In this source, therefore, a retained `system/…` or full `catalog/system/…` pattern can never fire on `view/…` or `controller/…` triggers.

### Predicate soundness

**Strip loop.** The loop `~^(?:catalog|system)(?:/|$)~` runs to a fixpoint.
- It terminates, because each pass removes at least one character or the pattern stops changing.
- It has no ReDoS exposure.
- It cannot over-strip: `catalogue/…` and `systemx/…` are untouched. Startup never registers those rows anyway (`==` is case-sensitive), so treating them as irrelevant is correct.

**Why stripping never admits a hook.** Every target begins with `view/` or `controller/`.
- An unstripped `catalog/` or `system/` literal prefix matches no target.
- Stripping a literal prefix can therefore only add matches.
- More matches can only add denials. A previously irrelevant (allowed) row can become relevant-and-denied, but no denied row can become allowed.

The predicate therefore over-approximates every registration variant safely: strip, retain, nested, and both orders. This includes a variant that strips `system/`, which this source does not show.

**Bare and edge rows.**
- A bare `catalog` or `system` row reduces to `''`. Then `fnmatch('*', …)` matches all fourteen targets. Startup registers `''` for a bare `catalog` row, and an empty anchored regex fires on everything, so the predicate is sound here.
- `catalog//view…` reduces to `/view…`. The predicate treats it as irrelevant, which agrees with the dispatcher, because it cannot fire either.

**Admission rule unchanged:**
- All fourteen targets are evaluated, with no `break`.
- The action is admitted only if it is `event/theme` or `event/theme.index`, after `|`→`.` normalization.
- The matched trigger must be both `view/…` and `…/before`.
- Admin is never stripped, so it remains irrelevant.
- No relaxation was introduced.

**Admitting `system/view/*/before` with `event/theme`** (case 17) is consistent with the existing native theme admission. Under the historical source it is also inert.

**PHP 8.0 compatibility.** The block uses only do/while, `preg_replace`, `fnmatch`, `str_replace`, `in_array` and `substr`. No PHP 8.1+ constructs appear.

### Fixture fidelity

`test-events.php` evals the exact runner slice, from `function rd16_event_check(` up to the runtime marker. It asserts both booleans for each case. I traced all nineteen against the supplied block by hand, and each expected value holds:
- **Cases 1–10:** the R2 ten are preserved.
- **Cases 11–12:** the two R2-mandated `system/` cases return relevant and disallowed.
- **Cases 13–14:** the `.index` controller spellings are matched by exact target (`fnmatch` without `FNM_PERIOD`).
- **Cases 15–16:** bare `catalog`/`system` reduce to `''`, then `*`, so all targets match and the row is denied.
- **Case 17:** `system/view/*/before` with the theme action is admitted.
- **Cases 18–19:** both nested orders strip fully and are denied.

The cases discriminate. In particular, cases 11 and 12 would have returned `relevant=false` under the R2 regex.

## Mandatory defects

None.

## Optional notes (non-blocking)

1. **Legacy `|` method separator.** The new targets cover `account.index` but not `account|index`. The `|`→`.` normalization is applied to actions, not to trigger patterns. A DB pattern spelled `controller/account/account|index/…` would be irrelevant to the predicate. The risk is the same as R2's note 6: low, non-canonical URL only, and broad patterns still catch it. Consider adding the `|index` spellings, or normalizing the pattern, in a future revision.
2. **Fixture boundaries.** Consider adding negative fixtures:
   - `catalogue/view/account/account/before` and `systemx/…`, which must be irrelevant;
   - `system/model/…`, which must be irrelevant and documents the out-of-scope model layer.
3. **Digest cross-binding.** `event-results.json` hashes the function-only slice (`f26533ec…`). `r3-equivalence.json` hashes the block including its comment (`b443e3e5…`). These are different extents, so the digests correctly differ. Cross-recording one in the other would bind the test artifact to the equivalence proof.
4. **Possible false STOP.** Conservative stripping can STOP Verify on a host with benign `system/…` or bare-namespace rows that the real dispatcher would never fire. That is an acceptable fail-closed STOP before writes. Owner QA should read such a STOP as "inspect rows", not as a defect to relax.
5. **Carried over from R2.** R2 optional notes 1–5 (Twig publish order, the untracked auto-restore candidate, whitespace `:empty`, `:visited` specificity, and R1 O4) are unchanged and remain non-blocking.

## Next step

The pair `c8a8cc55…` / `c99761454…` is accepted for the owner gate. The owner alone runs Upload, then Verify, and only after a passing Verify, Apply. The deployment, QA and WP2 gates are unchanged.
