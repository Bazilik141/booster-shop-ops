# RD18WP5 Review: Review OK

**Exact pair bound by this review:**

| Role | Path | SHA256 | Bytes |
|---|---|---|---|
| Runner | `patches\RD-18-WP5_shipping-display_20261028.php` | `0274bc8dae812feb09121907ec6576ae73adc0201a491d5e5b4a6a9b3d947fd7` | 151558 |
| Owner packet | `diagnostics\RD-18-WP5_owner_20261028.ps1` | `07dc12dab06f1e2486da92efed471cd76fd2582edb49f95d417eef415c208a39` | 11854 |

**What this review is based on:**
- I worked from text only. I did not recompute any hash, run anything or look at screenshots.
- The pair is consistent across the packet's `$Sha` value, `release-facts.json`, all nine `check-results.json` pairs, the report and the frozen references.
- The packet body was supplied in full and reviewed directly. Its hash is accepted by reference.
- For the runner I saw only the control excerpt. The two compiled B64 blobs (targets and dependencies) are elided. Equality of the decoded payloads to `tree-after` and of the 96 dependencies to their origins rests on the executor's `prove-boundaries.py` logic, which I reviewed, and its reported pass.
- None of this is host, runtime or QA certification.

## Code and composition assessment

**Controller helper `rd18Shipping`:**
- It only reads things it is given: the order, the totals and `is_np`. There are no calls to cart, coupon, quote, API, config, currency or model.
- It acts only when there is one numeric shipping row with value 0.0. Positive, non-NP, missing, duplicate and invalid shipping rows return the empty projection, so the native "0.00 ₴" text and the grand total are kept.
- **UAH branch:** the amount is `total × currency_value`, the same formula native `currency->format` uses, rounded to kopecks.
  - At or above 2000 it shows "За наш кошт" with no notice.
  - Below 2000 it shows the saved positive estimate, or "За тарифами НП", plus the notice.
  - A stale saved label cannot override this, as the owner's policy requires.
- **Foreign or invalid currency branch:**
  - It is "known" only when a recognizable saved label or positive estimate exists.
  - If nothing is recognizable, it shows the generic tariff label with `separate=false`, so it never claims who pays.
  - This matches the specification.
- **Input handling:**
  - `rd18Fields` accepts both a decoded array and a JSON string.
  - Markup is stripped after entity decoding.
  - Zero-valued estimates are rejected by the `[1-9]` check.
- `-0.0`, DECIMAL strings and legacy string `shipping_method` values are all handled safely.

**Twig:**
- The replacement is limited to `total.code == 'shipping' and rd18_shipping.display`.
- All output is escaped with `|e`.
- The notice is the immediate next `<p>` sibling after the payment line; the Twig comment emits nothing.
- The existing `bs-order-muted` class is reused. No CSS, asset or token changes.

**Language file:**
- The support text is exactly "Є питання щодо замовлення?".
- The new labels are exactly as requested.
- `shipping_separate` is added to the `rd18Presentation` key list.
- The controller differs from the native one only by the helper, the call line and that one key, as `prove-boundaries` normalization asserts.

**Apply and rollback order:**
- Apply runs in the order 2 → 0 → 1 (labels, controller, template). Every intermediate state is render-safe: the old template ignores `rd18_shipping`, and the new label exists before the controller reads it.
- Rollback runs 1 → 0 → 2, also safe at each step.
- All three backups are written and hash-verified before the first rename.
- Rollback is preflighted twice.
- A failure after a write triggers automatic restore of only the targets already changed.

**Packet:**
- Steps are one-use: `applyAttempted` is saved before invocation.
- The PHP path and admission are pinned from Verify.
- Cache cleanup is chained with `&&`. If Apply fails, the trailing staged-absence check fails closed because the runner was not self-deleted.
- The backup regex matches the runner's `gmdate` and 12-hex nonce format.
- No replay is possible: re-Upload refuses because the staged runner is still present.

**Tests:**
- The 20 helper cases match the code paths described.
- The language-only registry makes any access to anything else throw.
- The before/after render comparison of 36 cases compares the captured totals, products, trace and pager, which shows totals are not mutated.
- The browser test checks:
  - the grand total and FormData are identical before and after;
  - the exact copy and notice position;
  - no horizontal overflow at four widths.

## Mandatory findings (code)

None. I found no defect that blocks release.

## Mandatory gate closures (process, unchanged by this review)

- **M-G1:** Owner Upload, then Verify.
  - The receipt must show PHP 8.0.x, a 64-hex admission, `syntax_gate=ok`, `production_writes=0`, `backup=none`, `done=ok` and `RD18_WP5_VERIFY_OK`.
  - This includes the host matching the newly pinned `catalog/controller/checkout/confirm.php` (`30e8d442…`) and the other 95 dependencies.
  - Any drift is a STOP. No re-pinning.
- **M-G2:** A separate Apply only after the Verify receipt is accepted.
  - The receipt must show three target lines, `changed_files=3`, `self_deleted=yes`, `done=ok`, `template_cache_cleanup=ok`, `staged_runner_absent=yes` and `RD18_WP5_APPLY_OK_OWNER_QA_REQUIRED`.
  - The backup must be recorded in packet state.
- **M-G3:** Owner mobile and desktop QA on real orders. This is the final RD18 acceptance and stays open.

## Optional findings

1. **The estimate is shown without qualification (owner QA judgement).** A bare "₴85.00" in the totals list, next to a grand total that excludes it, could be read as a charged amount or as the final carrier price. The notice reduces this risk. Consider a "≈" or "орієнтовно" prefix if QA finds it ambiguous.
2. **The threshold overrides the saved label for historical orders.** Policy is "UAH ≥2000 → free" even when the saved label says paid. Orders placed before the rule existed, or under a different configured threshold, will be relabelled. Also, the live NP free-from threshold is a config value; only the source default of 2000 is pinned, and admission does not check the live setting. The owner should confirm the live threshold is 2000 and accept the retroactive policy.
3. **Rounding near the boundary.** Kopeck rounding makes totals from 1999.995 up to just under 2000 display as free, while an unrounded producer `>=` comparison would not. This is negligible with 2-decimal totals.
4. **Untested fallback paths.** Three paths have no test case:
   - foreign currency with "За наш кошт" (expect free, no notice);
   - foreign currency with only the tariff label (expect tariff with notice);
   - UAH with `currency_value` of 0 or non-numeric (expect the label fallback).

   The code is simple, so add these in a later package.
5. **Legacy support key.** `rd18_support` ('Питання щодо замовлення? Напишіть нам') still exists. If the pinned `order_list.twig`, whose body I did not see, renders it, the list page keeps the old wording. Check this during QA.
6. **Forward compatibility with PHP 8.1+.** `trim(preg_replace(...))` can receive null on invalid UTF-8, which raises a deprecation from 8.1. This is harmless under the PHP 8.0 gate.
7. **Cosmetic.**
   - The runner comment "twenty-six targets" is stale.
   - RollbackUpload does not refuse after `rollbackAttempted`; the Rollback step itself does refuse, so this is safe.

The task stays held until this review is accepted. Only Upload → Verify can be released, and deployment remains an owner-only gate.
