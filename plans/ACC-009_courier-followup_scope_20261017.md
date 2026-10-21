# ACC-009 courier follow-up — owner-approved scope and acceptance boundary

Date: 2026-10-17
Executor: Codex, continuing the assigned risky-zone work and original Sol/xhigh
recommendation. Actual independent reviewer: Claude Opus/high via subscription.
This is a scope record, not completed technical design/CODE or deployment approval.

Authority: direct owner answers recorded in
work/acc009-courier-findings-20261017/courier-owner-authority.json.
Both courier fixes and independent P1 policy were explicitly selected. Do not
request another routine go or repeat those choices. Fresh source is a real
dependency before implementation; use the27-file current source request (fresh-source-request-r3.json).

## Assigned preparation

1. Courier address editor/persistence: typing house/optional flat must not close
   the editor or create permanent address-book rows for intermediate input.
   Define an intentional completion action and a reviewed draft/commit boundary.
   Preserve focus and pending edits across background refreshes; stale responses
   must not hide/overwrite newer input. A longer debounce is not sufficient.
   Do not silently replace another saved address. Retain the single successful
   AddressCommitted transition and completion -> quote -> payment sequencing.
2. NP courier prefill: use the order's shipping snapshot and validated NP refs
   without inferring the address from a generic service label or today's profile.
   Verify field/type/city/street consistency and the POST consumer as well as GET
   defaults. Define explicit legacy/absent/conflicting-data handling. Preserve
   warehouse/postomat and sender paths, authoritative recipient snapshot names,
   N1 display encoding and order history. No new remote GET/POST is implied.
3. P1: patronymic required when placing a courier order as a shop policy.
   Profile stays optional; no new mandatory rule for other delivery types.
   Enforce against the final resolved delivery and recipient state on the server,
   with clear UI feedback and correct pending-revision handling. Cover signed-in,
   guest and other-recipient checkout. Never borrow today's profile value to
   fill an intentionally empty order recipient. Flat remains optional.

## Verification required before delivery

Design against fresh source, then actual design review before source patches.
Use separate bounded runners/stages as the reviewed composition requires. Native
PHP8.0 compatibility, exact source/runtime admission, private backups, lint,
idempotence, rollback and self-delete remain mandatory. Isolated fictional tests
must distinguish partial house/flat edits, repeated finish actions, delayed saves,
mode/type switches, guest and recipient revisions, empty required patronymic,
snapshot preservation and missing/conflicting courier metadata. No real NP call
or production order is required for these tests.

UI acceptance must cover desktop/tablet/phone, long content and edit/focus/error
states. Source/local proof, owner host execution, rendering, API semantics and
owner QA remain distinct. No implicit broad checkout/payment rewrite.

## Preserved gates and exclusions

N1 is deployed, with successful source apply receipt and public reachability.
Detailed name/key/quote/width QA remains incomplete. N3 has not been deployed.
Its release needs accepted bounded N1 QA and explicit owner acknowledgment of
recipient AND sender Address.save and subsequent document calls. Past owner
POSTs and the new preparation grant do not supply that acknowledgment.

N3 and courier prefill share the admin controller. The prepared N3 runner needs
the exact N1 after-image. Intended delivery order is N1 accepted -> separately
acknowledged N3 apply/QA -> separately admitted prefill on that resulting base.
Any changed order requires a newly reviewed composition and SHA-bound runners.
No concurrent writer. Checkout preparation can advance independently within
the new scope after freshness and design prerequisites.

N2 remains evidence-gated: do not remove spaces from identities or relax the
legacy NP regex without applicable remote semantics/authority. No historical
address cleanup, deduplication, row deletion or remote-object rollback is granted.
Do not infer a newly created remote contact solely from a successful save reply.

No agent deploy, NP API mutation, WP3 commit/push, Notion/dashboard/status-system
write or GA4/wishlist/header/media/photo edit. WP3b and WP3c remain separate
assigned sequential packages; profile policy and metadata gates are unchanged.

Reviewed diagnosis r2 requires effective catalog OCMOD/generated files, DB theme
overrides and catalog view-event admission before design acceptance (S2); tar
identity alone is insufficient. Final-order P1 must guard confirm.confirm before
addOrder/editOrder and verify guest/other-receiver middle-name snapshot routing.
Readiness with no explicit finish yet must not choose a stale session address.
An explicit visible finish control must be shown to the owner at design review,
without re-asking the already approved scope. The source request now includes
shipping_method/payment_method controllers/templates, including the second
createAccount/addAddress path. Old23-file request remains historical.
