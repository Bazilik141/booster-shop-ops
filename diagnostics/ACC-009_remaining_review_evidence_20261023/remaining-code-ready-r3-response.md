# ACC-009 WP3b + N2 + WP3c: r3 integrated CODE and composition review

**Overall verdict: REVIEW_OK for all three packages.** This covers the six exact identities below. Release must still follow the owner sequence in section 8. Applying WP3c needs its own separate owner go and a fresh full DB backup.

**Basis.** I read the following statically:
- the factored common runner algorithm;
- the WP3b decoded spec and the N2/WP3c field deltas (the packet asserts reconstruction equality);
- every current after-image and the full diff;
- the three owner packets;
- the newly material owning dependencies:
  - checkout-reskin.js and the main NP form;
  - the guest NP form and the NP COD injection hook;
  - payment_method.createAccount and the legacy SimpleCheckout controller;
  - booster_coupon, success, confirm, checkout-state and the register.twig template;
- the tree-inventory and pin-provenance generators;
- the fidelity harnesses and their results.

I executed nothing and computed no hashes. Hash equality comes from the packet's own assertions.

I relied on r2's accepted scope, at the exact rendered hashes, for:
- the unchanged readers reused by r2 hash under r2's M7 closure;
- the API/admin-order boundaries under M8;
- the packet transport logic;
- the metadata transaction.

I did not re-read those bodies now. Protected H1 raw bodies were not provided, and I did not ask for them.

## 1. Per-package verdicts and exact identities

| Package | Runner SHA256 | Owner packet SHA256 | Verdict |
|---|---|---|---|
| ACC-009-WP3b | `eb3ad8d992670780e1fc9b6b2489b7ac1bf7a4ffc0ccb53c96b2dacfa1cbedb1` (465672 B, 9 targets) | `da68ab45a43603ba05edfeafce686d8971933048316c873d5047711cb6fd9504` | **REVIEW_OK** |
| ACC-009-N2 | `70765d994f5ba1099dcc23b09864dd4f78682652c34738159e88943776fe1e53` (900564 B, 18 targets) | `2b22ad3ea8debd06e0a915a83ad0253106bb0c61a9cefe71df5b91b1bc0cb851` | **REVIEW_OK** |
| ACC-009-WP3c | `40571ef8cec7806e835a9072750e09fcc42f364f81c8f6d3ee44dd5ccc88cbe7` (120448 B, metadata only) | `1faa8ac48c56660df8c4bf89dc9973f75458fdb149f553a05d53ac977eb0e635` | **REVIEW_OK** (activation gated, section 8) |

**Packet bindings.** For each packet I checked that the following match `release-facts.json`:
- `$Sha`, `$Bytes` and `$TargetCount` (9/18/0);
- the inline source-gate counts (9/18);
- the remote `F/SHA/BYTES` constants.

`Assert-Release` reads `diagnostics\ACC-009_remaining_repair_r3_evidence_20261023\review-release.json` and requires both `runner_sha256` and `packet_sha256` per part.

Packet logic is byte-equivalent to the r2-accepted packets apart from the identities and paths. That covers:
- release-before-transport;
- the strict validators;
- the 2 h / 30 min expiry;
- the atomic `CreateNew` Apply marker;
- the 600 s wait with no replay;
- the `&&`-gated template-PHP-only cache cleanup;
- the backup attestation.

## 2. Mandatory findings

**None.** All five r2 mandatory findings are closed. My assessment follows.

### R2-M1 — Closed. Catalog-reader and caller pins come from the admitted current tree.

**Inventory derivation.** `build_inventory_tree.py` builds the tree in three steps:
1. It materializes 1787 ordinary catalog and admin-caller code files from the archive. Config files and protected members are excluded.
2. It overlays every exact pinned context identity (182) from the composition and accepted sources, hash-verified.
3. It applies the per-stage candidate payloads.

`test_current_tree.py` then:
- runs the real `ar_catalog_readers` and `ar_customer_callers` for the pre-WP3b, post-WP3b and post-N2 states;
- asserts equality with the fixture states;
- asserts that spec maps equal the production states.

The C1 reader `catalog/controller/checkout/checkout.php` is present in all three stages:
- `761c05…` in the WP3b and N2 states;
- `dab581…` in the WP3c state.

The Pinta catalog controller `39faa7…` is in the overlaid tree and correctly does not match.

**Protected H1 files.** The scanner now includes `catalog/model/checkout/order.php` and the Telegram event controller unconditionally:
- by path, after the link check and size cap;
- by `hash_file`, with no regex classification.

This is safe and consistent: the context already requires the exact after identities (`2ed58b…` and `d46a49…`). It also makes the map independent of hidden literals. The test-only projection substitution is explicit and confined to those two paths.

**Provenance.**
- The void15 projection manifest binds `after_source_sha256` to `2ed58b…` and `d46a49…`, which resolves the r2 `9dd7…` evidence note.
- `verify_pins.py` independently re-derives 148/157/163 known pins from the hash-checked applied C1/H1 `B_SPEC`, QA3, C2 and the preceding payloads. This also closes r2's optional item O6.

**Cap.** The caller per-file cap was raised to 2 MB. The aggregate caps remain bounded.

### R2-M2 — Closed. The First15 trusted flag is preserved without request minting.

- `addCustomer(array $data, array $trustedService = [])` filters `custom_field` through `publicCustomerFields`. It then sets `bs_first15_pending` only when the second argument holds the strict integer `1`.
- Only `createAccount` passes the server literal.
- The OC4 model proxy spreads `$args`, and the only registered events on `addCustomer` are `after` handlers that read `$args[0]`, so the extra argument is compatible.
- Both shapes keep the flag at the top level:
  - wrapped `['account'=>…]` session shape: `{'account':{…},'bs_first15_pending':1}`;
  - flat shape.
  This matches what `booster_coupon::applyPendingFirst15ForCustomer` and the `success.php` fallback read.

**Other creation paths cannot set the flag:**
- standalone registration filters its own IDs and uses one argument;
- checkout `register.save` forces `account=0`;
- the legacy SimpleCheckout `registerValidate` passes only `request->post` as one argument.

**Tests.** The interaction test runs the actual `createAccount` into the actual `addCustomer`, and runs the exact legacy call-site in both the flat and wrapped shapes. The full SimpleCheckout bootstrap was not executed, as the packet declares.

### R2-M3 — Closed. Nonshipping guests are no longer blocked by hidden NP controls.

- The pre-loop predicate now matches C1 exactly: `hasShipping() && !isLogged() && array_key_exists('shipping_novaposhta_type')`.
- `begin()` client validation skips hidden and zero-rect inputs through `kind()`. `prepareNpCheckout` hides `#shipping-novaposhta` for nonshipping carts.
- The final readiness name gate on a nonshipping guest view checks the contact names and an empty middle name only.

**Proof.** The native save plus matching declaration (checkout-modes) and the browser path through confirmation to the fictional provider both use the actual injected hook markup.

### R2-M4 — Closed via option (1), with visible error mapping.

- `shipping_address.save` now calls `names($post_info, session customer)` with a safe empty fallback. The posted names come from the receiver card, falling back to profile data attributes when no card exists.
- The values are equal to the profile or must be strict. `names()` retains the exact DB bytes.
- `showNameErrors` maps `firstname`/`lastname` to the visible editor in this order:
  1. the visible NP control;
  2. otherwise `#bs-co-recv-*`, after opening the existing collapsed card.
  It hooks both `finishDraft` and the global `ajaxSuccess` for `checkout/shipping_address.save` (the Pinta warehouse autosave). The own hint id matches the profile-JS clearing rule.

**Organic reachability.** A backend error needs a receiver-card value that differs from the profile and is invalid. `receiver.save` rejects the same value under the same rule, so the two layers agree. The packet notes that the transport-drift injection is only a test device.

**Without a recap card.** A logged-in customer with zero saved addresses has no recap card. The posted names are then the profile values, so `names()` accepts them and no invisible-error dead end exists.

**Tests.** Warehouse autosave and courier finish are covered both natively and in the browser.

### R2-M5 — Closed. The admin form no longer renders "Array".

- `displayFields` replaces non-string text defaults with `''`.
- The actual `form()` rendered through the real Twig (only the history widgets stubbed) produces empty inputs for keys 901–903, for both an existing customer and a new one, with no warning and no write.
- A blind save preserves the unrelated JSON (`7`) and stores no `Array` or `@Array`; the empty strings remain explicit.

Non-text custom-field types still receive the stock OC4 array `value`. That is pre-existing stock behaviour and does not affect the mapped text fields.

## 3. Interaction audit of the repairs together

**Guest courier: contact save versus finish.**
- Doors contact autosave tolerates unfinished names.
- `check()` normalizes in place by reference, even inside a short-circuited condition.
- Finish and non-doors saves are strict.
- `rd13_receiver_override` stores normalized names when valid. The readiness `invalid_names` gate catches any staged invalid guest names even with a matching declaration (`declaration-result`).

**Ordering and composition.**
- WP3b alone changes no customer-creation path.
- Between N2 and WP3c, `publicCustomerFields`/`catalogHidden` with no mapping is inert apart from numeric-ID filtering. That filtering is the intended M6/M7 behaviour.
- WP3c activation is only consumed by the readers that WP3b and N2 already installed.

**Source rollback:**
- reverse ordering guards (metadata, then N2, then WP3b);
- exact-state plan and check mode;
- owned-target-only restore.
These are unchanged and still exercised by `source-runner-checks`.

**Metadata transaction.** Unchanged and r2-accepted: `GET_LOCK`, then `SERIALIZABLE`, then the range locks; snapshot equality; journal and `rollback.sql` written before `COMMIT`; an unknown commit is a STOP.

**Privacy:**
- protected bodies are neither rendered nor scanned;
- the test projections are explicit;
- sender values and grants are never emitted;
- no credentials appear in the packet.

**Styling.** The added checkout UI uses existing classes plus `invalid-feedback`. No `!important`, timers or positioning are introduced by r3. Profile JS behaviour is unchanged from r2.

## 4. Optional suggestions (not release-blocking)

- **O-r3-1 — Client/server legacy-name source parity.** These are UX edge cases only; server validation is authoritative in both.
  - **Current mismatch:** The client legacy rule accepts any value equal to the seed `contact` or `receiver`. For logged-in customers with an active override, both reflect the override, not the profile, while the server `names()` compares only to the session profile.
  - **Effect A:** A pre-deploy session holding a legacy override that differs from the profile shows client-valid but server-rejected.
  - **Effect B:** Conversely, a user who reverts the field to a legacy profile name sees a stale client hint while the server accepts it.
  - **Fix:** Seed the authoritative profile names separately.
- **O-r3-2 — Stale name hints.** `[data-acc009-name-error]` hints and `is-invalid` are cleared only by a valid input event. Clear them on the next successful `shipping_address.save` as well. On mobile, consider scrolling or focusing the reopened receiver card.
- **O-r3-3 — New-address parity.** A new account-page address pre-fills a legacy profile name and is then strict. Checkout new NP addresses accept the unchanged profile name. This is consistent with "listed entry strict", but it is worth stating in owner QA notes.
- **O-r3-4 — Duplicate admin NP errors.** `internet_document` now reports name errors twice (pre-loop plus `validateFormdata`). Consider deduplicating.
- **O-r3-5 — Wrong release path in closure tooling.** `close_review.py` checks `ACC-009_remaining_evidence_20261022/review-release.json` for `review_release_absent`. The packets use `ACC-009_remaining_repair_r3_evidence_20261023/`. Correct the path so the closure field is meaningful.
- **Retained from r2:**
  - **O1** (fail-safe profile/admin pages when the strict mapping constructor throws). Recommended before WP3c, plus an admin runbook warning not to edit the three fields.
  - **O2** (`invalid_names` copy).
  - **O3** (MutationObserver scope).
  - **O4** (storage normalization / NFC).
  - **O5** (sender comparison through `text()`).
  - **r1 O2/O7/O9.**

## 5. Limits

All proof is local and fictional at the DB, auth, HTTP, directory picker, quote, CAPTCHA and provider boundaries. None of it is production proof.

**Host admission remains the only live proof, and it stays fail-closed:**
- The 222/225/227 context pins.
- The 37 readers / 24 callers maps.
  - **Most likely remaining STOP:** any host code file under the scan roots that was added or changed after the 2026-10-15 backup, is not covered by an overlay, and matches a pattern.
  - **Other STOP triggers:** count caps (40000/30000) and per-file caps (2 MB) on the full host extension tree, which was never measured.
- **Database and configuration:**
  - grants shape (a wildcard grant STOPs);
  - startup/theme rows;
  - events and extensions;
  - sender identity (entity-encoded values STOP);
  - web runtime.

**Not established:**
- NP provider semantic acceptance of the normalized names. Legacy order names need correction before each TTN, as the owner already knows.
- The full SimpleCheckout bootstrap.
- Real MySQL behaviour.
- Owner UI QA.

**Out of scope:**
- Admin order API and legacy name rules (unchanged, as declared).
- Auth-routing documentation.
- SimpleCheckout retirement.

## 6. Next owner gate

1. Record this acceptance and create `review-release.json` (schema `acc009.remaining-review-release.v1`, `REVIEW_OK`). Bind this request's id and the six exact hashes per part. Any byte change to a runner or packet voids this verdict for that part.
2. Owner runs **WP3b**: Upload, Verify, Preflight and Apply as separate invocations, with receipts. Then focused QA:
   - profile and admin form (no "Array", fields absent pre-WP3c);
   - legacy-name profile save.
3. Owner runs **N2** the same way. Focused QA:
   - nonshipping guest checkout;
   - guest courier finish;
   - logged-in legacy-profile checkout (new NP warehouse and courier);
   - account-checkout First15 on the next order;
   - admin NP TTN with corrected names.
4. **WP3c** only with its separate owner go and a fresh, consistency-attested full DB backup. O1 is recommended first.
5. Expected receipts close mechanically.
   - Any admission STOP is recorded as STOP and leads to reassessment or regeneration, never on-host adaptation.
   - An uncertain metadata commit needs assessment.
   - There is no automatic replay or rollback.
