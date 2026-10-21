# ACC-009-QA Focused R2 Delta Re-check

**Verdict: REVIEW_OK**

**Bound hashes** (taken from the supplied headers and facts; I did not recompute any of them):

| Item | SHA256 |
|---|---|
| Patch / runner (89448 B) | `55f7efc8d23a1a18959926cbc1c4dd9c3193d06249ba30a54b920ca73171c2a6` |
| Owner PS packet | `fbeb4b6cc635813893334da9eced9fe3abbcf62a824c934bcef2d26f1c2e6de8` |
| acc009-checkout.js | `8137d8b6…` → `0cb1acff…` |
| checkout-reskin.js | `9af1c78e…` → `a73a0209…` (unchanged) |
| checkout.twig | `d989bf48…` → `397b85cc…` |

**Binding chain:**
- In the PS delta, `$Sha`, `$Bytes` and target rows 0 and 2 match `release-facts.json` exactly. Row 1 and the 9 dependency pins are unchanged.
- `runner-result.json` is bound to the new runner hash.
- `command-check.json` binds the new PS hash to the new runner hash.
- The runner implementation is unchanged. The runtime check `payload sha256 == spec.after` still ties the shipped bytes to the reviewed after-images.

## M1: CLOSED

The fix sits in the `finishDraft` success callback, after the full validity guard. That guard checks `json.success`, the absence of `json.error`, the `draftGeneration` version and the canonical tuple.

- **Order of operations:** state is committed first. Then `#acc009-status` is removed as a node, so no empty `.alert` box is left behind. Then `followNotice = false` is set, then `release('',…)` runs.
- **Error paths are unchanged:** both the refusal and the transport error still create or keep the alert. Nothing is removed on failure.
- **Absent node is tolerated:** `placeTail` and `layoutSettled` already handled a missing node (accepted in R1).
- **Notices cleared:** removal also clears an earlier `begin()` notice or `acc009_notice` notice. This is the intended behaviour, because the finish success resolves that notice.
- **New test:**
  1. `finishFailures=1` makes the first finish fail, and the test asserts the "Перевірте поля адреси" alert is present.
  2. The corrected value is submitted and native finish succeeds.
  3. The test then asserts `#acc009-status` count = 0, the CTA is enabled and the hint shows "Все готово".

  This is the closure scenario I asked for.
- **Reruns on new bytes:** 15/15 browser checks, 8/8 runner checks, PS5 parse with native dry-run and apply receipts, and 4 malformed receipts refused. All are bound to the new hashes.

## New mandatory findings

None.

## Optional dispositions

| Item | Disposition |
|---|---|
| **O1** | Accepted. `${files[@]+"${files[@]}"}` is the standard `set -u` idiom that works on bash below 4.4, and the inner quotes keep paths intact. The empty fixture passed on Git Bash 5 only; no Bash 4.2 host claim is made. |
| **O2** | Accepted. A non-empty, non-NP code now returns false, and the stale NP-code fallback is unchanged (still tested with `.warehouse`). See residual note 1. |
| **O3** | Accepted. The 10px gap is now documented. Final look remains with owner visual QA. |
| **O4** | Accepted. `requiredMiddle()` is now called once. See residual note 2 for a small behaviour change. |
| **O5** | Deferral accepted. No screen-reader certification is claimed. |
| **O6** | Retained as an owner visual decision. Accepted. |
| **O7** | Accepted. The test now asserts the CTA is disabled and the hint is specific after `fill('')`, before blur and save. |
| **O8** | Accepted as a condition on the future rollback packet. No rollback command is released now. |

## Residual notes (non-blocking, no action required)

1. **O2 stale-code window.** Suppose an authorised user goes from a non-NP method (e.g. pickup) to a courier address, and the quote has not yet replaced the non-NP code. For that window, middlename is not required. This matches whatever session method would actually be confirmed, so I do not treat it as a defect. There is no browser check for this specific transition.
2. **O4 side effects now run earlier.** `requiredMiddle()` now runs before the `hasDraft`/`deliveryReady` short-circuit. Its label and `aria-required` side effects therefore also update during a draft. This is benign and arguably more consistent, and the readiness result is unchanged.

## Limits

- The browser checks use fictional loopback, quote, CAPTCHA and provider boundaries. They are not production proof.
- No production Twig compile, Bash 4.2 host, screen-reader or provider verification has been done.
- The owner must confirm the PS file hash `fbeb4b6c…` locally before running it.
- Owner Upload, Verify, Preflight and Apply remain owner-only. This review does not grant Apply.
