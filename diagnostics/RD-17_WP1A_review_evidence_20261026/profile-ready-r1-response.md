# RD-17-WP1A independent CODE/composition review — profile recipient bridge

## Verdict: **Changes required**

**Bound artifacts (this verdict covers exactly these bytes):**

| Artifact | SHA-256 | Bytes |
|---|---|---|
| `patches/RD-17-WP1A_profile-receiver_20261026.php` | `d0bb2116e40ca6acff58efe15ba4490bec0c6d2118f53a251c8c401e91ce6849` | 397999 |
| `diagnostics/RD-17-WP1A_owner_20261026.ps1` | `3ef1a07f0c6528c2a25311f278b25eef0eea24340934fed7433c2c4fbd3ed47b` | 11874 |

The seven targets match `profile-targets.json`: the helper is new, and there are six replacements. The decoded payloads match the supplied after-trees.

The engine, packet, precedence logic, copy writer and race handling are sound. Two mandatory items block owner Upload/Verify:

- **M1** is a real availability defect in the risky checkout zone.
- **M2** is a customer-visible recipient change the report does not state.

This is not production acceptance. Host Verify, deployment and owner QA remain separate.

---

## Mandatory findings

### M1 — Checkout becomes unavailable for logged-in customers when profile metadata or a profile row is invalid

**Affected code:**
- `BoosterCheckoutProfileReceiver::loadNames()` and `defaults()`
- `BoosterCheckoutReadiness::sessionOrder()`, used by `assertReady` (courier), `assertDeclaration`, `assertOrder`, `mint` and `accepts`
- `Confirm::index`
- payment-entry `accepts()`

**How it fails:**
- `defaults()` converts every `Throwable` into `BoosterCheckoutNotReady('reload')`. This happens whenever a logged-in checkout has no own override, which is the normal new-checkout state.
- `loadNames()` always constructs the strict `BoosterAccountProfile` registry. That constructor throws on any metadata deviation, for example:
  - a changed label or sort order;
  - a field that is disabled;
  - an added customer group whose assignment differs from `customer_groups`;
  - a malformed or duplicated map.
- `loadNames()` also throws on malformed customer `custom_field` JSON, a non-string patronymic value, or control characters.

**Consequences:**
- One routine admin edit to the profile custom fields blocks **every** new logged-in checkout.
- The customer sees a "reload" message, and reloading never resolves it.
- One customer's legacy `custom_field` blocks that customer permanently.
- Before this patch, such metadata failures affected only account-edit pages, not checkout. The patronymic is optional for warehouse delivery, so refusing the whole checkout is disproportionate.
- Admission requires an active map only at deploy time. It does not protect against later admin edits.

**Closure — either option:**
- **(a) Degrade deterministically, then fresh review.**
  - Registry/mapping failure, malformed or non-string `custom_field`, or an absent field → profile first/last from the customer row with middle `''`.
  - Invalid profile first/last → skip profile defaults entirely, i.e. fall back to the pre-patch behaviour.
  - Keep fail-closed only for identity mismatch and DB errors.
  - Apply the same tuple to `names()` and the checkout seed.
  - Add native cases: registry exception, inactive map, bad JSON, non-string middle. Each must still reach the readiness → Confirm → seal → `accepts` path.
- **(b) Owner informed decision.** Record the owner's explicit acceptance of fail-closed coupling, plus an admin warning/runbook not to edit the mapped fields. The report must state it.

### M2 — Unreported recipient change: saved-address and contact names are replaced by the profile

**Affected code:** `defaultsFromProfile()`, which writes `shipping_firstname`/`lastname` and the contact `firstname`/`lastname`; the readiness seed; `Confirm`; the NP snapshot.

**What changes:**
- Before the patch, a logged-in checkout with no override used the selected or default saved address's recipient names. Those names are written from the receiver card, so they may be a relative.
- After the patch, the receiver card, the order and the NP waybill name the account holder until the customer edits them.
- This is consistent with owner option 1. However, the amendment requires that changes to existing alternate-recipient behaviour be "clearly reported". The report says only "Guest behavior is preserved".

**Closure:**
- Add an explicit report statement to that effect.
- Add an owner QA item: select a saved address whose stored name differs from the profile and confirm the receiver shown and ordered.
- If the owner wants saved-address names to keep precedence, that is a code change and needs a fresh review.
- A prose-only correction closes mechanically and does not change the bound hashes.

---

## Optional findings (recorded, not blocking)

- **O1 — Copy refusal text is lost.**
  - `surfaceReceiverErrors` ignores the `warning` key, so the specific server message (e.g. "У профілі не вказано по батькові…") becomes the generic "Перевірте дані отримувача."
  - Button eligibility uses `middleReady`, which checks presence only. A profile patronymic that is present but fails ACC-009 name rules therefore shows the button, and clicking it gives a misleading "not specified" refusal.
  - Consider surfacing `warning` in the status, and aligning eligibility with or distinguishing from `name()`.
- **O2 — Stale-profile loop.** If the profile changes in another tab after the page renders, every Confirm returns 409 needs_confirm, because the declaration is captured from stale DOM. Only a page reload recovers. Consider a reload hint on repeated 409s.
- **O3 — Deploy-window transition.** In-flight sessions with a pre-deploy order snapshot or seal (address names) will fail `accepts` / `assertOrder` against the profile-based `sessionOrder`. Status≠0 retries will hit `terminal`. Deploy at low traffic and add a note to QA.
- **O4 — Report inaccuracies.**
  - Backups are written as `<backup>/original-<i>` plus `manifest.json`, not "using its relative target path".
  - `RD16_*` identifiers remain in the runner (cosmetic).
- **O5 — Double decode.** `receiver.php` runs `name($rd17_names['middlename'])` on an already-decoded display value. Using `storedNames()['middlename']` avoids the double decode. Low impact.
- **O6 — Stale lock.** A lock left by a killed run needs manual removal before Verify or Apply can proceed. Document this in the owner notes.

---

## Interactions verified as correct

**Precedence and data flow:**
- An own override, including an explicit empty patronymic, a legacy tuple, or a guest-origin override rebound after `createAccount`, skips profile defaults identically in `sessionOrder` and `Confirm`.
- A foreign override yields profile defaults.
- Guests trigger no profile DB read.
- A non-shipping cart touches only the contact fields.

**Snapshot transport:**
- Stored raw profile bytes flow into the order and the seal snapshot.
- `view()` decodes exactly one layer.
- The DOM and the declaration use decoded values.
- The apostrophe round-trip is consistent across seed, copy reply, `BoosterAccountProfile::names` byte preservation and the NP `acc009RecipientNameDefaults` snapshot.

**Copy path:**
- The server ignores submitted names and reads the DB.
- An empty or Unicode-blank profile patronymic is refused without a write.
- The revision is echoed on refusal.
- The `!isset($json['error'])` gate is correct.
- Telephone is preserved, and no profile or address write occurs.

**Front-end races:**
- The edit generation protects newer typing.
- A queued telephone-only edit inherits the copied names.
- The signature exception allows recopying an invalid unsaved tuple.
- The disabled-attribute observer cannot self-trigger, because it writes only `hidden` and `style`.
- The button is hidden while the coordinator is busy or locked, during name focus, and while a save is pending or queued.

**Runner:**
- No-op is detected before dependency checks, so a DS change does not cause a re-touch.
- Admission is recomputed at Apply.
- Backups and staged lint happen before any write.
- Apply order puts helper and writer first and the view last; rollback order is the reverse.
- Rollback preflights all targets and backups, re-checks before the first write, restores permissions and removes only the created helper.
- The cache path is fixed; the metadata adapter is read-only and allowlisted.
- There is no DB mutation.

**Packet:**
- Steps are strictly separated, with strict host keys and BatchMode.
- Apply is one-use, and replay is refused.
- Cache cleanup runs only after runner success via `&&`.
- The exact saved backup is required.
- Receipts are persisted before checks.

**UI signature scan:**
- No new `!important`, `setTimeout`, `position:absolute/fixed` or unexplained pixel values in production targets.
- The single inline `style.display` is justified in a comment.
- `mt-3` and `bs-btn-ghost` reuse existing styles.
- Historical uses (loader `position:fixed`, `!important` hide classes, existing reskin timers) are unchanged.
- `setTimeout` appears only in the browser test.

---

## Fake boundaries and missing context

- **Tests:** test fidelity is adequate for this scope. The resolver, mapper, readiness, Confirm, receiver and the NP-name method are native code. The browser fake omits the full checkout.twig inline script and the provider flow; the report states this honestly.
- **No material context gap blocks approval.** One inherited assumption: Twig autoescape is assumed off. The new attribute follows the existing `acc009_seed` pattern and fails safe (button never shows) if that assumption is wrong.
- **Dependency pins:** the 107 hash-only conditional pins are constraints, not host attestation. Host Verify must establish them, and any mismatch is a STOP.

**Next action:** resolve M1 by option (a) with a fresh affected review, or by option (b). Correct the report per M2, which can close mechanically. Owner Upload/Verify remains HELD until then.
