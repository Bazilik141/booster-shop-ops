# ACC-009 — separately authorized NP correction preparation

Date: 2026-10-16 (Europe/Kyiv)
Executor: Codex, continuing the owner's assignment. Retain the risky-zone model/effort recommendation; actual independent design/CODE review uses Claude Opus/high through the saved subscription CLI.
Workspace: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`.
State: **PREPARATION_IN_PROGRESS — WAIT_CURRENT_CHECKOUT_JS**.

## Owner authorization and sequence

After the accepted WP3a diagnosis the owner selected **“Додати окремий етап підготовки виправлень НП”** in response to the NP scope question. This authorizes a separate preparation/design stage for the identified NP findings. It is not authority to deploy, create contacts/counterparties/shipments, change business records, or silently implement a protected-area patch. Do not ask again whether this preparation may start.

Keep WP3b (profile/admin source) and WP3c (metadata) separate. This additional NP stage does not replace the original account task, combine its source/metadata packages, authorize a WP3 commit/push, or change the accepted name/contact policies. Work sequentially with one source writer. Finish the NP source intake/design and its independent review before presenting a concrete implementation scope. The owner removed repeated routine go waits; only real evidence/functional/production authority dependencies require a stop.

The source-preflight report and r1 verdict remain frozen. Current authority is recorded by this new plan and `work/acc009-np-prep-20261016/authority.json`, not by editing the accepted packet. No Notion/dashboard task or status is created.

## Accepted starting evidence

- `diagnostics/ACC-009_WP3a_review-disposition_20261016.md` and full actual `ACC-009_WP3a_claude-review-r1_20261016.md`: Preflight Review OK, no mandatory findings, bounded source diagnosis only.
- Current source drops: 25 files, SHA `bfadf716fd47a23c0ee252ba30ca95200e5e7a4fcb0e14d4c0f69a4368c1ce2d`; five additional files, SHA `90be3fe0fcc6f57463410ce4ef1c2377e823aa82e42e80935c37651579886fc9`. Their 30 extracted source identities were rehashed before this preparation.
- `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`: default name split at336-350; first/last-name regex at978/982/986/990; undefined local counterparty object in `saveDoorsAddress()` at1246-1276. All are source observations, not reproduced hosting failures.
- Admitted checkout receiver/confirm controllers retain separate, revisioned order-only receiver data. Their source admission is historical at the native r16 check, not a new blanket runtime assertion.

## Current missing evidence: two JS files, read-only

Request only:

```text
catalog/view/javascript/checkout-state.js
catalog/view/javascript/checkout-reskin.js
```

The current checkout template references these assets. They are required to determine whether the visible NP middle-name input is sent to `checkout/receiver.save`, when it is sent, and whether recipient modes or delayed responses can drop/change it. Existing archive evidence does not contain them. Do not substitute an old backup or infer routing from a field name. The owner command is `work/acc009-np-prep-20261016/owner-source-request.sh`; it creates the two-file archive outside public_html.

Intake must accept the exact two regular files, reject links/duplicates/traversal/unexpected members, record archive/file hashes, and extract outside the repository. These are read-only dependencies. They are not assigned for editing merely because this stage needs to inspect them. If design proves a change to protected checkout/shared assets is necessary, state that exact scope and coordination need before implementation. Do not edit or freeze another chat's active source; use task-owned source snapshots.

## Candidate NP subpackages — design only

### N1. Recipient defaults and patronymic precedence

Problem: `makeDefaultFormData()` always splits the final word of shipping first name into patronymic and never reads `rd13_receiver_middlename`. A compound first name may be silently reclassified.

Candidate behavior to design after JS intake:

- Use the order's shipping snapshot only for delivery identity; never today's profile patronymic/contact values.
- A present `shipping_custom_field.rd13_receiver_middlename` is authoritative, **including an explicit empty string**. Do not split shipping first name in that case.
- An absent key is not the same as an empty key. Show the owner a concrete legacy/guest fallback decision: retain the old final-word inference, or retain the complete given name and leave patronymic for explicit operator entry. The latter avoids silent name reclassification and is the current recommendation, not an approved business decision.
- Do not invent a fallback to today's customer. Do not silently use a potentially inconsistent order-header key as shipping truth; assess same-order header versus shipping precedence explicitly from actual writers.
- Handle malformed/non-scalar receiver-patronymic data without string-casting arrays/objects, data loss or a new external request. Define the operator-visible repair/error behavior in the reviewed design.
- Keep saved profile/address JSON, `bs_np_v1`, order history and stored order rows unchanged. This is form-default selection for an existing order, not a migration/backfill.

Final target is expected to be the existing NP document controller. A Twig change is a conditional candidate only if source evidence requires a new manual-entry explanation or safe error state; no Twig/JS/CSS edit is currently assigned. Fresh target/override admission and a separate review are required before a source runner can be delivered.

### N2. Validator compatibility and evidence boundary

The current NP local regex accepts ASCII apostrophe but rejects spaces and one-character names. This pre-exists ACC-009. The approved profile policy remains unchanged. Do not copy the legacy NP pattern as the new profile validator.

Relaxing the local NP regex requires evidence that the relevant Counterparty/ContactPerson API accepts the proposed values. On this preparation pass, targeted official-domain searches did not find that name constraint. The official [integration page](https://novaposhta.ua/for-business/cooperation/integration/) identifies the JSON API and links to the developer portal; its linked portal returned HTTP403 through the browsing tool. That page establishes the endpoint, **not** punctuation/minimum-name-length semantics. A separate WMS or international Nova Post API contract is not a substitute.

Therefore do not change the validator based on the current evidence. Keep this candidate gated on applicable authoritative documentation or a separately owner-authorized observation. Preparation authority does not permit a live `Counterparty.save`, `ContactPerson.save`, `Address.save` or `InternetDocument.save` probe, even without purchasing a label. No new API key is requested or loaded.

### N3. Undefined object in the Doors address helper

The current complete `saveDoorsAddress()` method calls `$PintaCounterparty->saveAddress(...)` without constructing or receiving that local variable. If the supplied source executes and city/street lookup succeeds, PHP8 is expected to fail before the API call. This is not a claim that all deployed courier shipments fail; overrides/runtime are unverified.

Prepare this as a separately identified correction candidate, since it was an additional finding, rather than silently bundling it with name defaults. A minimal design would construct the already-required counterparty library inside the successful local lookup branch before the existing save call. It would retain arguments, service-type routing, API-response validation and error handling. Review must verify sender/recipient Doors callers and stubbed success/error behavior without any real network. Restoring a formerly failing branch can enable an external address write, so the design must expose that behavior and retain owner execution/QA gates.

The owner's generic NP-preparation choice allows assessment and presentation of this known finding. It does not itself deploy/fix the branch or authorize a real address/label creation.

## Design acceptance checklist

Use fictional fixtures and source inspection. Do not execute the supplied application, private config or external NP methods during this intake.

| Case | Required design result |
| --- | --- |
| Shipping key present and nonempty | Exact order receiver patronymic; no first-name split or profile lookup |
| Shipping key present and empty; header/profile nonempty | Empty stays empty; no resurrection from another source |
| Key absent in guest/legacy order | Follow the specifically approved fallback; do not invent certainty |
| Compound given name with ASCII apostrophe/hyphen/space | Preserve identity boundaries; distinguish local regex refusal from remote acceptance |
| Corrupt JSON/non-scalar key | Safe, bounded operator behavior; no irreversible write |
| Other recipient mode / delayed save | Establish actual JS → revisioned receiver → confirm snapshot behavior before choosing defaults |
| Doors lookup fails | Existing error path, no external address call |
| Doors lookup succeeds | Correct dependency initialization and unchanged arguments/response handling in isolated stubs only |
| Source rerun/rollback | Exact per-file hashes/anchors, private backups, PHP8.0 lint/restore, idempotence, rollback, self-delete |

No field IDs are inferred; no customer/order DDL, metadata change, backfill, contact syncing or status transition is included. If later markup changes are authorized, define required device widths, long names and interactive states explicitly; do not reuse deferred WP2 width QA as a WP3/NP pass.

## Review and next boundary

Complete the two-JS intake and source trace, then automatically send one coherent design package to actual Claude Opus/high using a new task-scoped subscription CLI request. Reuse verified auth/environment/tool/hook controls, not a consumed request. Include the complete material functions/callers and fictional evidence; preserve source/host/remote distinctions. Do not dispatch a positive design review while its mandatory JS context is missing.

After design review, present only the genuinely new functional choices (legacy absent-key fallback, exact source patch scope, optional Doors inclusion, and any validator evidence gap). Do not re-ask the accepted profile policies or permission to perform this preparation. Implementation, CODE review, owner host admission/deploy/QA and WP3c remain later distinct boundaries. No commit/push is authorized for this additional stage.
