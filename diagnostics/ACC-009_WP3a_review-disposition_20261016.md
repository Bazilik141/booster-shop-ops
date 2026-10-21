# ACC-009 WP3a — independent review disposition and next scope decision

Date: 2026-10-16 (Europe/Kyiv)
Executor: Codex. Workspace: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`.
Current technical state: **PREFLIGHT_REVIEW_OK_BOUNDED — owner selected separate NP correction preparation**. The latest choice authorizes preparation/design, not a deploy or implicit protected-source implementation. Current continuation: `plans/ACC-009_NP-preparation_20261016.md` and `work/acc009-np-prep-20261016/state.json`; waiting for the exact two current checkout JS sources. The earlier choice discussion below is retained as history.

## Outcome

Actual Claude returned **Preflight Review OK (bounded diagnosis only)**. Mandatory findings: none. The complete response has been read. The two current archives contain exactly 30 accepted regular source files; together with 13 retained r16 admission images they support the declared direct NP document/name and admin-autocomplete findings. This is not a live NP result or implementation acceptance.

Reviewed report: `diagnostics/ACC-009_WP3a_preflight_report_20261016.md`, SHA-256 `235c345c0ec9a726d2d085854cc7da7caaee92a33d94d7d8ec18f7fc39723944`. Keep that report byte-identical, including its review-time pending status. This disposition is the current status/finding interpretation and does not rewrite the frozen review inputs.

Full actual verdict: `diagnostics/ACC-009_WP3a_claude-review-r1_20261016.md`. Independent closure: `diagnostics/ACC-009_WP3a_evidence_20261016/review-r1-preflight-closure.json`.

## Actual transport and closure

- New one-shot request: `05ea6e5b-ddc1-4555-81d4-7b68ef39da8b`; consumed, never replay.
- Original native process 8828, controlled session 97486; exit 0. Native Claude Code 2.1.299, actual `claude-opus-5-5`, high effort, saved Claude.ai subscription verified. No direct key/fallback, no tools/MCP, hooks disabled. Fresh native rate receipt explicitly reports `isUsingOverage=false`.
- Packet 652,065 bytes; 53 frozen inputs. CLI/drop/env/args AST matches the previously accepted subscription transport. The retained same-model run advertised a 1,000,000-token context; this was sufficient for the conservative preflight packet estimate. No upgrade or entitlement-probe request was used.
- Independent post-result check confirms all 53 inputs, packet, original full result, rendered verdict and Git HEAD/index unchanged. Review duration 184,346 ms, one provider turn; usage reports 250,010 cache-creation input tokens, two other input tokens and 18,007 output tokens including 13,037 thinking tokens. These client counts/list-price estimates do not establish a subscription charge, quota debit or savings; those remain unknown.
- Preparation caught a stock OpenCart PHPDoc email example; it was verified as an exact public example, not customer data. No real customer/contact/checkpoint value was supplied. Local probe data is fictional. Privacy/context preparation checks are not provider calls; only the one actual review above was dispatched.

## Accepted findings and limits

1. ASCII apostrophe survives the inspected direct source path: saved order snapshot → NP defaults → double-quoted name controls/multipart POST → request cleaning/trim/local regex → contact/counterparty JSON → contact reference in the document. Remote NP acceptance, current runtime overrides and authenticated hosting execution remain unverified.
2. Current NP first/last-name validation rejects spaces and one-character names. Its legacy pattern also accepts some names rejected by the approved Ukrainian-only policy. **The mismatch pre-exists ACC-009.** Current checkout already accepts broader length-only strings; the approved account policy narrows that input. WP3b is not the cause of this existing incompatibility. There is no reason to weaken the approved policy silently.
3. NP defaults split the final word of `shipping_firstname` as patronymic and ignore the separately stored `rd13_receiver_middlename`. A compound given name can therefore be silently misclassified. Explicit empty receiver patronymic must remain distinct from an absent key in any future design. Do not read today's profile to rewrite old order identity.
4. `getCustomers()` retains raw custom-field JSON because PHP array union preserves the left row key. Admin autocomplete forwards it; the sale-order JS enumerates character indices and can populate wrong controls. The ordinary customer list maps only display fields, so no visible custom-field list-column defect is claimed.
5. The missing legacy registration helper, two external checkout assets, fresh override admission and remote NP semantics are correctly bounded. They do not defeat this diagnosis or the G3 scope choice. They remain mandatory context if a later design depends on those paths. No universal NP/checkout/caller certification is implied.

## Optional review notes: retained, not silently implemented

### Separate NP Doors defect

The reviewer found an undefined local object in `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php::saveDoorsAddress()`. Codex inspected the complete current method at lines 1246-1276 and confirmed that line 1265 calls `$PintaCounterparty->saveAddress(...)` without declaring/constructing that variable in method scope or receiving it as a parameter. If the supplied source runs and its city/street lookups succeed, that branch is expected to fail on PHP 8. This is a source finding, not a reproduced hosting failure or proof that every live courier shipment fails. No method was executed and no correction was made. Keep it as a separate owner-visible NP item; no Notion/dashboard task or status was written.

### Before WP3b design/CODE

- Inventory all `getCustomers()` and customer-autocomplete callers in admin/extensions before changing the model return contract. Existing callers may independently decode JSON, which would fail on a returned array. The preferred root repair remains the owning model contract after this inventory, rather than a client parsing workaround. A targeted endpoint decode/whitelist is the reviewer's alternative, with its different blast radius; no route has been changed yet.
- Trace `adminEvhenii/controller/sale/order.php::call()` and the delegated customer/snapshot path. Codex additionally inspected current lines 1207-1290 after review: it creates a catalog store instance and invokes `api/order` with the posted data. The exact delegated current body still needs source discovery before an autocomplete fix can be treated as safe for order edits. This is additional read-only context, not a retroactive expansion of the review verdict.
- Correct prefilling can cause previously blank custom fields to be posted into order editing. Verify stored-snapshot and `rd13_receiver_middlename` preservation, not merely selector rendering.
- The endpoint currently serializes the whole model row, including sensitive internal columns such as a password hash. This is a stock source-level minimization concern; no real row/hash was fetched. Do not silently bundle a whitelist behavior change without its consumer contract and scope.
- WP3b must be inert while WP3c metadata is absent; discover stable field identities, never assume numeric IDs. New fields remain absent from registration/checkout. Profile and receiver patronymics remain distinct; no automatic profile-to-receiver prefilling.

### Before any NP follow-up

- Obtain current `checkout-state.js` / `checkout-reskin.js` to establish whether the visible NP middle-name input reaches the receiver endpoint. Their omission does not block this diagnosis, but it blocks accepting a new patronymic routing/default design.
- Obtain exact current target/override admission. Decide how absent legacy patronymic differs from an explicit empty snapshot. A safe candidate uses an existing shipping receiver-patronymic key as authoritative and never splits that name; the owner must decide the absent-key fallback.
- Relaxing the NP validator requires authoritative documentation for that API contract or a separately owner-authorized observation. No routine counterparty/contact/document creation is authorized as a diagnostic.
- Preserve sender settings, codes, status IDs, references, document/contact creation flow and historical records. The Doors item is separately scoped.

## Concrete next owner choice

The owner has already approved the three fields and account name policy and removed repeated routine “go” waits. Those decisions are not re-asked. The new G3 choice concerns **protected NP scope and the known downstream limit**, not whether the approved fields are desirable.

Recommended: continue WP3b in the accepted account/admin scope (optional patronymic/Telegram/Viber, edited-name normalization/validation and safe admin autocomplete), keep registration/checkout and NP business logic outside the patch, and carry the pre-existing NP incompatibility as an explicit separate item. The writer can resolve routine implementation details through caller inventory and independent design/CODE review. No separate routine approval is required for read-only preparation.

Alternative: assign an additional, separately designed/reviewed NP source package to address default patronymic selection and, only after evidence supports it, validator compatibility. That is a new protected-area grant and must not be inferred. An async owner choice has been presented; no NP change follows before its answer and the applicable design/review/deploy gates.

WP3c remains a distinct metadata-only stage with exact discovered identities, rollback SQL, its own review, a fresh owner-held DB dump and explicit go. Current WP2-only Git permission does not authorize any WP3 commit/push. No WP3 source patch, metadata write or production action has occurred.
