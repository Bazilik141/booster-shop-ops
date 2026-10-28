# Review OK: RD-18 WP1 R2 affected ready repair

No mandatory findings. This verdict is my own for the current pair. It does not rely on the R1 approval of the old hashes.

## Binding identities

The verdict covers both artifacts together, as supplied. I used no tools and computed no hashes. The values below are taken from the file headers and checked against each other.

| Artifact | SHA256 |
|---|---|
| Runner `patches/RD-18-WP1_orders_20261027.php` | `f2bd1d4312923ba7c1d4cdc46cf83ccf44a631f50a027e87f9e3a6bd9a428221` |
| Owner packet `diagnostics/RD-18-WP1_owner_20261027.ps1` | `2b2713c0ff86a824dacf224b8105ed779d198893943f1d138e8bccb5a3724b91` |

**Cross-checks**
- **Packet pins the runner.** The packet's `$Sha` literal equals the runner hash. `release-facts.json`, `r2-repair-proof.json` and the report table all carry the same pair.
- **Embedded contract.** I decoded fragments of the double-base64 contract.
  - Target 0 after-hash is `d024d4a2…a03f` and target 7 after-hash is `b3db6ec9…104a`. Both match the report table and the plain after-tree.
  - The order.php payload contains the new `$key === 'comment' ? '' : "\n"` expression, the "Stored extension labels…" comment and `trim(strip_tags(`.
  - The CSS payload contains the new "767px is the integer mobile…" comment.
- **Runtime guard.** The unchanged guard re-hashes every payload against its `after` value before any write, so any mismatch fails closed.
- **R1 artifacts.** They are bound by the old hashes `1219a01e…` / `9010a8ce…` and response `c535d9a9…`. The R1 identity closure is consistent with the supplied R1 response.

This verdict is void for any other bytes.

## Composition: what changed between R1 and R2

`prepare-r2.py` asserts the following, and `r1-to-r2.diff` agrees:

- **Changed targets.** Only order.php and boostershop-ds.css changed.
- **Before states.** All nine before-hashes are identical.
- **Runner engine.** The runner body is identical after the `rd16_targets` payload is normalised out. `rd16_dependencies` (66 entries) is not normalised, so it is identical byte for byte. The runner template is also identical.
- **Owner packet.** The packet equals the R1 packet with only the runner-hash literal swapped. Its size is unchanged at 11814 B.
- **Rendered payloads.** The rendered payload hashes equal the target after-hashes.

There is no change to queries, the status map, history, receiver or business rules, the reorder form or handler, the hub, dependencies, admission, write or restore order, or the packet steps. All R1 accepted findings on the unchanged scope carry over.

## O1: comment line breaks

The fix is complete and correct.

- **Comment.** Native `nl2br()` gives `line<br />\nline`. The comment now has only the `<br>` variants removed, so the original `\n` / `\r\n` remains. The template's `|e|nl2br` then yields exactly one break per newline. The test asserts 2 `<br>` for 3 lines.
- **Literal `<br>` in stored text.** The replacement runs before entity decoding. A customer's literal `&lt;br&gt;` therefore survives and is shown escaped, which is truthful.
- **Billing address.** It still maps `<br>` to `\n`. The 2-break assertion is retained.

## O2: shipping and payment method markup

The fix is complete for its purpose.

- **How it works.** The display alias is decoded once, then `<br>` becomes a space, then `strip_tags`, then `trim`, then Twig `|e`. Twig escaping still protects the output, so `strip_tags` is presentation only.
- **What is unchanged.** Native `payment_method` and `shipping_method`, stored data and checkout/payment behaviour are not touched.
- **Test.** The browser test proves visible text with no `<` and no `<img>` in the payment block.

## O6: CSS comment

Comment-only. Declarations are identical, per the diff.

## O3, O4, O5 and O7 dispositions

All four are accepted as recorded:

- **O3.** The phone shown is the order's contact phone, with a truthful label.
- **O4.** This is disclosure only. A consumed owner packet still means STOP, and a no-op result grants no recovery permission.
- **O5.** The duplication comes from a single authoring source. A shared-shell refactor is out of scope.
- **O7.** The owner's D2 palette choice stands.

## UI signature scan (R2 delta)

No new `!important` (the only hit is inside a comment), `setTimeout`, absolute/fixed positioning or uncommented magic pixel value. The `767px` breakpoint and `2px` border now have comments.

## Optional findings (non-blocking; any code change needs an affected review)

- **R2-O1: strip order and stray `<`.** `strip_tags` runs after decoding.
  - A legitimately encoded `&lt;…&gt;` in a method label is stripped as if it were a tag.
  - PHP `strip_tags` drops everything from an unclosed `<` followed by a non-space character to the end of the string. For example, `Кур'єр (<2 кг)` would be truncated.
  - Safer order: `rd18Text(strip_tags(str_replace(br,' ',raw)))`.
  - Real NP, Hutko, monobank and PUMB names are unlikely to hit this.
  - Closure: owner QA confirms that real method names display fully.
- **R2-O2: shipping method not asserted in the browser test.** The `method-markup` browser case asserts only the payment block. The shipping alias uses the same loop, but `<strong>Нова пошта</strong>` → `Нова пошта` in the delivery panel is not asserted. This is a coverage gap, not a defect.
- **R2-O3: test results not bound to hashes.** The `runner-results.json` and `packet-PS51/PS7-results.json` files do not embed the runner or packet hash they tested. The claim that they ran on the current pair rests on the executor's statement.

## Evidence limits

- **Not read in full.** I did not decode the full base64 payloads. I used the readable after-tree for order.php and CSS, the decoded fragments above, the matching hashes and the runtime guard.
- **Not executed or seen.** I executed no tests, saw no screenshots and make no visual-fidelity claim.
- **No real environment certified.**
  - Real MySQL admission and the host event set for the new triggers are not exercised. An unadmitted hook will STOP at Verify with `production_writes=0`.
  - The packet's SSH/SCP transport was mocked.
  - Data is fictional only.
  - Unix ownership and permissions are uncertified.
  - Twig is linted locally only; the host lints PHP only.
- **Reused proof.** The earlier 102/20/6/13 metadata-gate results are reused on the basis that the code is identical. I accept that because the engine-equality assertion holds.

## Owner instructions (unchanged; not granted by this review)

1. Run `-Step Upload`.
2. Run `-Step Verify`. It must print `RD18_WP1_VERIFY_OK`. Any `error=` or `packet_error=` means STOP: reassess, never re-pin on the host.
3. Run `-Step Apply` as a separate step. It is one-use; never replay it.
4. A complete Apply receipt closes mechanically.
5. Do owner QA as listed in the report, and also check:
   - real payment and shipping method names display fully, with no logo and no truncation (R2-O1);
   - a multi-line customer comment shows single line breaks.

Rollback remains a separate owner decision. This review grants no deployment, live data access, Git, Notion or dashboard action.
