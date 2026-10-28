# RD-17-WP1A r2: integrated CODE/composition review of the profile recipient bridge

## Verdict: **Review OK** for owner Upload and Verify

I found no mandatory findings. Both r1 mandatory items are closed.

This verdict covers only the exact pair below. It is not production acceptance. Host Verify must pass all 107 pins and the admission digest. Owner deployment and owner QA remain separate.

| Artifact | SHA-256 | Bytes |
|---|---|---|
| `patches/RD-17-WP1A_profile-receiver_20261026.php` | `27c6bec2e5113d03313b5ed3ca5074e48748f20bdfbe73d6e09968feda2e6bd4` | 401823 |
| `diagnostics/RD-17-WP1A_owner_20261026.ps1` | `ae92eb4a2f3db35a186c912af5c85017a09f2cf4a2238906e3f2a293b3d190ff` | 11874 |

**How I checked the bindings:**
- These are the declared identities. I did not recompute SHA-256 values; byte identity is the harness's closure job.
- I decoded these embedded payload spans by hand and they match the r2 sources:
  - the helper header and the `BoosterCheckoutProfileMetadataDb` class;
  - `receiver.php`, which now calls `name($rd17_stored_names['middlename'] …)`;
  - the reskin line that writes `status.textContent = json.error.warning`.
- At `--check`, the runner itself enforces `hash(payload) === after` for every target (`payload_hash_mismatch`). A table/payload mismatch would therefore fail closed at Verify.
- The packet `$Sha` equals the runner hash above.
- The packet's state file binds both runner and packet hashes. Any stale r1 state is refused.

---

## Mandatory findings

None.

### r1 M1: availability coupling is closed by option (a)

**Affected code:** `BoosterCheckoutProfileReceiver::loadNames()`.

**Required names are checked first and use no DB.**
- If the profile firstname or lastname fails `text()`, the control-character check or `name()`, the resolver sets `stored = []` and `profile = []`.
- `defaultsFromProfile()` then hits the new `if (!$names) return $order;` guard. This gives exactly the pre-patch projection.
- `names()` returns `[]`, which renders as `"[]"`. In JavaScript the `typeof` guards then fail, so `rd17ProfileNames` is null and the button never appears.

**The optional registry failure path degrades and does not block.**
- Any `BoosterCustomFieldException` falls back to `middle = ''`. This covers changed labels, sort order, status, group assignment, a duplicate or malformed map, and JSON failure inside the constructor (that one goes through `fail()`).
- Inactive map: zero setting rows, so `ids()` is empty and middle is `''`.
- Malformed `custom_field` JSON: the inner `catch (RuntimeException)` yields fields `[]`.
- Non-string, absent, invalid or control-character patronymic: the `text()`/`name()` gate leaves middle as `''`.

**DB and identity failures still fail closed, and the separation is real.**
- `BoosterCheckoutProfileMetadataDb` turns every driver throw, and every non-object or wrong-shape result, into `RuntimeException('profile_database_unavailable')`.
- That is not a `BoosterCustomFieldException`, so it escapes the narrow catch. `names()` and `defaults()` then fail closed.
- The customer-row query and `customerId()` mismatch also stay fail-closed.

**Every caller sees the same tuple, from one cache.**
- Readiness `sessionOrder` (seed and declaration), `Confirm`, `checkout.php`'s `rd17_profile_names` and `receiver.php` all use the same registry-cached instance.
- `degraded_button_seed_mismatch` asserts this directly.

**Courier keeps its safety requirement.**
- With a degraded empty middle, `assertReady` still demands a typed patronymic.
- The `degraded_courier_requires_middle` test refuses with no write.
- The typed value then creates an own override, which takes precedence over profile defaults.

**The required native evidence is present.**
- The Confirm suite runs real `Confirm.index`, declaration, `mint` and `accepts`.
- It passes for `registry_exception`, `inactive_map`, `bad_json`, `nonstring_middle`, `absent_middle` and `invalid_firstname`.
- `metadata_db_exception_before_order` is blocked with zero writes.

### r1 M2: reporting is closed

- The report now states the saved-address-to-profile recipient change in the card, the new order and the NP snapshot.
- It adds the matching owner QA item: select a saved address whose stored name differs from the profile.
- This was prose only and did not affect any hash.

### r1 optional repairs are verified

**O1 (copy refusal message and eligibility):**
- On a copy error the server text is now written via `textContent` (no HTML sink). It overrides the generic message from `surfaceReceiverErrors`.
- `payload.rd17_use_profile` survives on the error branch, because the `delete` runs only on success.
- Eligibility via `middleReady` is now equivalent to validation. The helper only ever exposes a middle name that is `name()`-valid and non-empty, or `''`.

**O5 (double decode):**
- `receiver.php` now validates the raw stored middle name once.
- Afterwards, `check()` and `names()` handle normalization and byte retention exactly as in r1.
- The `copy_apostrophe_one_layer` test still passes.

---

## Interactions re-checked in r2

**Precedence**
- An own override wins before any profile read.
- This holds for a legacy tuple, an explicit empty middle, and a guest-origin override rebound after `createAccount`.
- A foreign override leads to profile defaults.
- Guests trigger no profile read.
- A non-shipping cart changes only the contact fields.
- In a `createAccount` request, `[]` is cached during the guest phase and the session later becomes logged in. The new `!$names` guard prevents an undefined index there. The next request then sees the own override.

**Transport**
- Raw stored bytes go into the order and the seal. `view()` decodes exactly one layer.
- The DOM and the declaration use the decoded values.
- On a copy, the override stores the bytes produced by `names()`/`check()`.
- Readiness and `Confirm` both read the override, so the seal and `accepts` stay consistent.

**Copy path**
- The server reads the names. Forged POST names are ignored.
- An empty, Unicode-blank, invalid or unavailable profile is refused with no write.
- The revision is echoed on every copy response, so the AJAX gate displays the refusal.
- On a refusal, `rd17_profile_names` refreshes from the server and the stale button hides.
- Telephone is preserved.

**Front-end races (unchanged from r1, still covered)**
- The edit generation protects newer typing.
- A queued telephone-only edit inherits the copied names.
- The debounce/blur owner is still the existing writer.

**Runner and packet**
- The engine is byte-identical apart from the target table, by the masked equivalence.
- Unchanged behaviour:
  - the no-op check runs before dependency checks;
  - admission is recomputed at Apply;
  - backups and staged lint happen before any write;
  - apply order is 0,4,1,2,3,6,5 and rollback order is the reverse;
  - rollback preflights everything and removes only the helper;
  - cache cleanup runs only after success via `&&`;
  - Apply is one-use.
- No customer or order rows are read during admission.

**UI scan**
- The r2 change adds one JavaScript `textContent` assignment.
- No new `!important`, `setTimeout`, `position:absolute/fixed`, pixel value or CSS.
- The r1 additions are unchanged:
  - one commented inline `style.display`;
  - a `Promise` microtask, not a timer;
  - a disabled-only `MutationObserver`.
- Historical reskin timers and the loader's `position:fixed` are pre-existing.

---

## Optional findings (recorded, not blocking)

### O-A: the strict driver-shape assertion depends on the mysqli contract
- **Where:** `BoosterCheckoutProfileMetadataDb`, which requires `is_int(num_rows)` and array `row`/`rows`.
- **Risk:** if the real driver ever returned another shape, every logged-in checkout would fail closed with "reload".
- **Why I accept it:**
  - The pinned `system/library/db/mysqli.php` (`f47e18c9…`) is the stock OpenCart 4 driver: integer `num_rows`, array `row`/`rows`.
  - The accepted mapper already compares `num_rows !== 1` strictly in production on account pages.
  - Host Verify pins the driver.
- **Closure:** none required. If desired, add a line to the owner QA so a fresh logged-in checkout is verified first. The report already says to "verify a fresh checkout before normal use".

### O-B: legacy names that fail ACC-009 rules silently skip the whole bridge
- **Where:** the first/last name gate in `loadNames()`.
- **Effect:** two-letter names such as «Ян» or «Ія», Latin names, and similar cases get no profile defaults and no patronymic seeding. This is the pre-patch behaviour, exactly as M1(a) prescribed.
- **Closure:** an optional sentence in the report or QA so the owner is not surprised.

### O-C: the copy refusal text is inaccurate in one unreachable case
- **Where:** `receiver.php`.
- **Effect:** if the profile names themselves are invalid, a copy POST is refused with «У профілі не вказано по батькові». The button is never shown in that state, so only a forged POST reaches this message.
- **Closure:** cosmetic only.

### O-D: a transient metadata DB error now blocks logged-in checkout
- **Effect:** the customer sees "reload" until the DB recovers. This is by design and matches M1(a): DB errors stay fail-closed.
- **Closure:** record only.

### Carried from r1
O2 (stale-profile 409 loop), O3 (deploy-window seals), O4 (`RD16_*` names) and O6 (stale lock) are recorded in the report and unchanged.

---

## Fake boundaries and material context

**Test fidelity is adequate.**
- The registry fake raises the same `BoosterCustomFieldException` path as real label or group drift.
- The metadata-DB fake raises through the real adapter.
- Result objects match the stock driver's shape.
- These are native: resolver, mapper, readiness, `Confirm`, receiver, the NP-name method, the generated reskin DOM and coordinator.
- These are fake: DB, cart, order store, provider and HTTP.

**Missing context, none of which blocks approval:**
- The real `mysqli.php` and `framework.php` bodies were not supplied. They are relied on only through accepted pins and the existing mapper's production behaviour. r2 introduces no new reliance on error-handler semantics.
- The Twig autoescape-off assumption is inherited and fails safe: the button would simply never show.
- The 107 dependency pins are conditional constraints, not attestation of the present host. Any mismatch at Verify is a STOP.

**Next action:** owner `-Step Upload`, then `-Step Verify`, and return the complete receipt before Apply.
