# Review OK: RD-18 WP1 r3 affected ready review after the M1 repair

**Verdict: Review OK.** There are **no mandatory findings**. M1 from request `fe3fd821-…` is closed by option (a). The rest of the r3 delta is unchanged from what the earlier review accepted. There are five optional items (N1–N5) and the recorded limits below.

## Binding identities

This verdict covers only these bytes. I used no tools and computed no hashes. The values come from the CANDIDATE block, `release-facts.json`, the report and the packet's own literal, and they agree with each other.

| Artifact | SHA256 | Bytes |
|---|---|---|
| Runner `patches/RD-18-WP1_orders-r3_20261027.php` | `bc6c7ab5cf137e1e5b3825f72980b4e1726e242c35bc66a892f1cdb3e0bedd60` | 164754 |
| Owner packet `diagnostics/RD-18-WP1_owner-r3_20261027.ps1` | `d7b0d9b12f30f2fca2cfaa67a388345910a2e9b79286f0af10cbf34da14fb20e` | 11852 |

**Cross-checks**
- The packet's `$Sha` literal equals the runner hash.
- Every one of the 9 rows in `bound-check-results.json` records this pair both before and after its job.
- The earlier verdicts do not certify these bytes:
  - Consumed r3 request `fe3fd821-…` (response `be51060f…`) binds only `4f567…/6f4a…`.
  - The R2 verdict binds only `f2bd…/2b27…`.

**Size check (deterministic, done by hand).** The runner grew by 164754 − 163886 = 868 bytes. The additions account for this exactly:
- The 20 dot-form entries add 870 bytes. For each method, two entries cost `2×20 + 2×len(method) + 13` plus 4 bytes of quotes, comma and space each. The method names total 130 characters, giving 790 + 80 = 870.
- The comment change from "all twelve added" to "their complete" removes 2 bytes.

This matches the guard-only diff and the `build-successor.py` insertion. The packet length is unchanged at 11852 bytes, which fits a change confined to the `$Sha` literal.

## M1 closure assessment

**Event names now match the Loader.**
- `Loader::model` builds each proxy with `callback($route.'.'.$method)`.
- The callback fires `model/account/order.<method>/before` and `model/account/order.<method>/after`.
- After the `catalog/` strip, an enabled row whose trigger is exactly one of these 20 names now fnmatches its target. It is marked relevant and rejected, because it is not `event/theme` and not `view/…/before`.

**Broad patterns are caught.** Each of these hits the dot targets through `fnmatch` or `fnmatch(p.'*')`, while dispatch through Event's `^`-prefix regex would also fire:
- `model/account/order.*`
- `model/account/order.`
- `model/account/order` (no dot)
- `model/account/*`
- `?`-wildcard variants (fnmatch `?` and the Event regex `.` both match the literal `.`)

**Divergences from the Event regex are all conservative.**
- `[…]` and `\` are special to fnmatch but literal to the Event regex. The only effect is extra STOPs.
- The slash aliases and `system/` and bare forms never fire in Event but are still rejected.
- Uppercase `Catalog/`, `catalog//` and similar forms are not registered by `startup/event`, and the runner also treats them as not relevant.

**No new admission.** The five exact raw pairs are still matched before prefix stripping, and none of them is a model route. The multiset still requires exactly five rows.

**Method inventory.** The count is 10, not 11; my earlier "11" was wrong.
- `order.php` uses getOrders, getProducts, getTotalProductsByOrderId, getTotalOrders, getOrder, getOptions, getSubscription, getTotals, getHistories and getTotalHistories.
- `account.php` uses getOrders.
- The callback uses getProducts.
- The test's regex inventory over the actual `tree-after` controllers must equal this set, so any new consumer fails the test.
- getProduct and the BySubscription/ByProductId methods have no RD18 consumers.

**The test is discriminating.**
- It loads the pinned `loader.php` (`ea20…`) and checks its hash against `dependencies.json`.
- `callback()` produces the two exact strings per method. A recording Event fake checks them.
- The same strings pass silently through the extracted r1 classifier, which counts as 20 old misses.
- The current classifier rejects all 3 prefixes × 20 strings, plus 3 × 3 broad patterns, for 69 checks.
- The single factory call proves the cache path ran.
- The fake Model, Registry and factory sit in the engine namespace. `__call` correctly satisfies `is_callable`. No database or real model is reached.

**Capture limit is stated honestly.** The old "112 scanned / 5 relevant" count was produced with slash-only coverage. A future Verify that reveals a dot-form account/order model hook is a correct STOP. It is not grounds to widen the guard again.

**Host ordering is unchanged.** The event scan inside `rd16_runtime` runs before the 66 dependency hashes, before candidate staging and before any backup or webroot write. Apply recomputes admission.

## Rest of the coherent package (unchanged and still accepted)

- **Packet:** the r3 identity, receipt directory, `R3` banners, heredoc delimiter, backup regex and one-use states are consistent.
- **Lock:** the shared `RD-18-WP1_orders_20261027.lock` makes the old and new runners mutually exclusive.
- **Rollback and engine:** restore order, full live and backup preflight, partial recovery and self-delete are verbatim. They are covered by `runner_engine=pass 7` on the bound pair.
- **Payloads and dependencies:**
  - `build-successor.py` asserts that the decoded target and dependency contracts equal those of the R2 runner.
  - The runtime check `payload_hash_mismatch` and the dependency guards still fail closed.
- **UI:** the guard and packet add no `!important`, `setTimeout`, fixed or absolute positioning, or magic values. The R2 UI verdict carries over because the payloads are byte-identical.
- **O2 closed:** all 9 jobs are bracketed by the exact pair. Each result and fixture hash in `bound-check-results.json` matches its declared input hash.
- **Identical hook result files:** the before and after files share hash `f437…`. This is expected, because the JSON stores only per-case data that the tests claim are equal; see N3.

## Mandatory findings

None.

## Optional findings (non-blocking)

**N1: stale count in a docblock.** The `rd16_event_check` docblock says "Evaluate all twenty-six targets". There are now 86: 14 view, 32 controller and 40 model. The report says the numeric comment was corrected, but only the "twelve" comment was. This is literal prose with no behaviour change, so it can be fixed through the mechanical path.

**N2: proxy construction is read, not executed.** The Loader test calls `callback('account/order.'.$method)` directly. The `$route.'.'.$method` join inside `Loader::model()` is confirmed by reading the source, not by running it. Optionally, also drive `Loader::model()` with a fake Proxy.

**N3: hook results do not identify their run.** `hook-before-results.json` and `hook-after-results.json` record neither the mode nor the template hash. The run alone cannot show that the after run used the new templates. Optionally, add `mode` and the `order_info.twig` hash.

**N4: old classifier copy is not hash-checked.** The loader test reads `r1-reviewed/…r3….php` without checking its hash against `4f567…`. The "old misses 20" result depends on that file being the reviewed one.

**N5: record the second reason the old pair is superseded.** The old R2 pair also lacks the M1 coverage. The O4 record should say it is superseded both for its stale multiset and for slash-only model coverage, and that it must never be run.

**Carried forward**
- **O1:** language and other-model hooks, plus the catalog, manufacturer and language bodies, remain outside the admission. They are faked in tests and are a recorded limit.
- **O5:** the tests render through a bare Twig `ArrayLoader`, not the production `twig.php` wrapper and cache. This stays an owner Verify/QA boundary.

## Evidence limits

- **No tools.** I did not decode, hash or execute anything, and saw no screenshots.
- **Elided contract bodies.** The two encoded contract functions were elided from the excerpt. I rely on:
  - the stated hash and length comments;
  - the `contracts(code)==contracts(old)` assertion;
  - the exact 868-byte accounting;
  - the guard-only diff;
  - the runtime payload and dependency guards.

  The excerpt itself is not deployable.
- **Script not supplied.** `repair-m1.py` (`38e5…`), which presumably wrote the final facts and proof JSON, was not supplied. I judged the final runner and packet bytes and evidence, not that generation step.
- **Not certified:**
  - real MySQL and event metadata, theme settings and Unix ownership;
  - native SSH/SCP;
  - real GA4 or analytics delivery, and real catalog data;
  - whether the callback source on the host is current. The 66 pins must match on the host at Verify; drift means STOP and a named fresh pull, never a re-pin.

## Next step (no grant)

The owner runs only the NEW packet, first Upload and then Verify. The expected output is:
- `RD18_WP1_R3_UPLOAD_OK`, then `RD18_WP1_R3_VERIFY_OK`;
- an `admission_sha256` value, `syntax_gate=ok`, `production_writes=0`, `backup=none` and `done=ok`.

The owner returns this output before a separate Apply. Any STOP from an unadmitted hook, source, dependency or runtime check is a correct fail-closed result.

This review grants no deployment, SSH, key, server, database, Git, Notion or dashboard action. It makes no production or QA claim.
