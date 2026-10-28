# RD-17 checkout/profile/default-address source supplement

Date: 2026-10-26 (Europe/Kyiv)
Executor: Codex, owner-assigned, same primary workspace.
State: CODE_READY_REVIEW_PENDING; no functional patch or production action.

## Owner outcome and scope

See plans/RD-17_scope-amendment_20261026.md. The owner chose separate form 1A,
accepted NP field order, requested direct default-address selection, and requested
current profile full-name/patronymic propagation through checkout and its relevant
consumers. These direct decisions supersede the earlier presentation-only scope
only for necessary changes. No repeated scope grant is needed. Source collection
and diagnosis precede implementation. RD18 remains unstarted. H1/D2 persist.

## Causal lead, not verified current-host diagnosis

Accepted ACC009 closeout deliberately separated profile patronymic and recipient
patronymic. Its retained N2 checkout controller prefills first/last name from
customer getters, but reads middlename only from rd13_receiver_override. The
receiver endpoint persists order-only values in session. Confirm copies that
value into order/shipping custom_field rd13_receiver_middlename. This explains a
possible missing bridge; it does not prove the current host has these bytes or
inspect the owner's account. Never invent field IDs from the old 29/30/31 receipt.

Default-address current model editAddress rewrites a complete address plus JSON.
Do not implement a card button by synthesizing/resubmitting that form: it could
overwrite unrelated data. Prepare a narrow ownership-checked user POST action
and transaction/session behavior after checking current dependencies. No direct
DB write, schema/migration, backfill, test customer/order/shipment or message.

## Exact source collection

27 new full source files below, relative to ~/public_html. Fixed allowlist,
regular files only; no directories, discovery globs, config, env, key, logs,
sessions, dumps or customer/order row exports. NP creation code is collected as
source only; no PHP entry point from it is invoked and no provider is called.

- `catalog/controller/account/edit.php`
- `catalog/view/template/account/edit.twig`
- `catalog/controller/account/login.php`
- `catalog/controller/checkout/checkout.php`
- `catalog/controller/checkout/confirm.php`
- `catalog/controller/checkout/receiver.php`
- `catalog/controller/checkout/shipping_address.php`
- `catalog/controller/checkout/payment_address.php`
- `catalog/controller/checkout/register.php`
- `catalog/controller/checkout/payment_method.php`
- `catalog/controller/checkout/shipping_method.php`
- `catalog/controller/checkout/success.php`
- `catalog/view/javascript/checkout-reskin.js`
- `catalog/view/javascript/checkout-state.js`
- `catalog/view/javascript/acc009-checkout.js`
- `system/library/booster_checkout_readiness.php`
- `catalog/view/template/checkout/checkout.twig`
- `catalog/view/template/checkout/register.twig`
- `catalog/view/template/checkout/shipping_address.twig`
- `catalog/view/template/checkout/payment_address.twig`
- `extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`
- `extension/PintaNovaPoshtaCod/admin/view/template/shipping/pinta_nova_poshta/create_internet_document.twig`
- `extension/PintaNovaPoshtaCod/catalog/controller/shipping/pinta_nova_poshta.php`
- `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`
- `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/checkout_shipping_address_form.twig`
- `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/checkout_shipping_address_form_register.twig`
- `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig`

31 hash-only files below. Their bodies are NOT archived or returned. The first
27 rebind the already accepted RD17 source context; four order/admin consumer
identities allow reuse of prior accepted scoped contracts without exposing raw
private order/notification sources. Hash matches are not runtime certification.
If a changed consumer needs source, prepare a bounded privacy-safe supplement.

- `catalog/controller/account/address.php`
- `catalog/view/template/account/address.twig`
- `catalog/view/template/account/address_list.twig`
- `catalog/view/template/account/address_form.twig`
- `extension/ukrainian/catalog/language/uk-ua/account/address.php`
- `catalog/controller/account/shell.php`
- `catalog/view/template/account/shell.twig`
- `catalog/view/stylesheet/boostershop-ds.css`
- `catalog/view/template/common/header.twig`
- `catalog/view/javascript/booster-account-universal.js`
- `catalog/view/javascript/booster-account-profile.js`
- `system/library/booster_account_profile.php`
- `system/library/booster_custom_field_guard.php`
- `catalog/model/account/address.php`
- `catalog/model/account/customer.php`
- `catalog/model/account/custom_field.php`
- `system/library/cart/customer.php`
- `catalog/view/javascript/common.js`
- `catalog/view/stylesheet/stylesheet.css`
- `catalog/view/stylesheet/bootstrap.css`
- `system/library/template/twig.php`
- `system/engine/loader.php`
- `catalog/controller/event/theme.php`
- `catalog/model/design/theme.php`
- `catalog/controller/common/header.php`
- `catalog/view/template/common/footer.twig`
- `extension/ukrainian/catalog/language/uk-ua/account/account.php`
- `catalog/model/checkout/order.php`
- `adminEvhenii/model/sale/order.php`
- `adminEvhenii/controller/sale/order.php`
- `adminEvhenii/view/template/sale/order_info.twig`

## Read-only host contract and private artifacts

Collector: diagnostics/RD-17_checkout-source_collect_20261026.sh.
Owner packet: diagnostics/RD-17_checkout-source_owner_20261026.ps1.
Remote private exports: ~/private/booster-pulls/RD-17-checkout-source-20261026-r1.tar.gz
and the same stem .manifest.txt. Local drop:
work/rd17-supplement-20261026/owner-drop-r1/. Task work folder is ignored locally.
Both remote names and local drop must be absent; preserve originals/no replay.
Only exact private exports are removed after both downloaded hashes are verified.
No live source/cache/DB mutation. Production backup/rollback: none.

Manifest: exact 27 source hashes, 31 dependency hashes, their before/after equality,
UTC times, archive hash, schema-checked prepared read-only technical metadata.
Theme counts use six exact checkout/NP view routes; layout lookup is checkout only.
No theme code, customer/order rows or secret settings are queried. Config loads
only in owner PHP locally with errors/output suppressed; DB_PORT cast and
bind_result; no mysqlnd APIs. Existing fresh source/runtime checks before eventual
application remain necessary; these theme counts are not event/OCMOD admission.

Expected collector receipt, five LF lines:
task=RD-17-SUPPLEMENT
source_files=27
archive_sha256=<64 lowercase hex>
manifest_sha256=<64 lowercase hex>
done=ok

After verifying downloads and exact cleanup the owner packet prints
RD-17_CHECKOUT_SOURCE_READY and the local drop. Any other result: stop and
preserve local/remote evidence, do not rerun or manually clean. Existing alias
and agent setup is reused; the AI never launches SSH/SCP or key tools.

## Local checks, review and next work

Derived additively from the accepted RD17 WP0 collector/packet; consumed original
bytes and receipts are untouched. Full archive/dependency path components reject
symlinks, sources have per-file/total caps and obvious-secret screening; hash-only
files have caps and are excluded from archive. All 58 identities are rehashed
after capture and the 27 archive members are independently checked.
Tests use real Bash/tar/SHA with synthetic sources and mocked PHP metadata.
Seven cases: success/exact archive exclusion, missing archived source, missing
hash source, source drift, hash-only drift, metadata failure/no archive, and
existing export preservation. PowerShell AST and PHP8.3.30 syntax pass. Actual
PHP8.0/MySQL/SSH runtime remains owner evidence, not inferred from local mocks.
Inherited LF native transport and failure preservation are unchanged except exact
task identifiers/paths/hash. No PowerShell runtime or network test is claimed.

Actual subscription Claude integrated CODE/composition review of this executable
collection pair is required before owner use. This is not a separate early design
or actual-result exception call. No paid API/overage, model tools/MCP/hooks.
Keep optional notes and material fake boundaries; do not expand scope to repair
unrelated historical packets. Record exact identities/verdict after response.

After owner capture: strict receipt/manifest/member/hash/privacy intake, drift
assessment, trace current profile -> session/checkout -> explicit receiver ->
order/shipping snapshot -> admin/NP. Cover logged-in/guest, empty/cleared optional
name, profile edits, stale session/receiver, saved-address changes, explicit other
recipient and order-only edits. Preserve unknown JSON, readiness revisions,
payment/CAPTCHA/First15 and past order snapshots. Then author the bounded fixes
and visual package under the recorded scope with required review and owner QA.
No further owner scope confirmation is required merely because fresh evidence
arrives. New consequential behavior choices remain owner decisions.


## Integrated review accepted - owner source capture pending

Actual subscription Claude Opus5.5/high, request `95edcec0-bc6c-4acf-accf-37bfcbe6a244`,
returned **Code/Composition Review OK**, no mandatory findings.
Collector SHA-256: `c8aca9e1106e4629384fc44e629f4fbe24f450b2f8452f87c1f3d057f1997ae5`.
Owner packet SHA-256: `61ffc18ef3d4f2c48f69fda95617746d78fd2d83aafdfea1978db46d7b7aed0f`.
Review packet: 145819 bytes, SHA-256
`2cd81f8ec9895cd74016a73f341891fc558c1730a50110d0ee87c8e77e84e2c0`.
Original response/run and mechanical closure are under
`diagnostics/RD-17_checkout-source_review_evidence_20261026/`.
All declared inputs matched after the exchange and again before this bookkeeping
append. No reviewed executable changed. All seven fixture cases passed. Explicit
negated shell exclusion assertion is ineffective under set-e (O1); the archive's
exact27 count plus per-member source verification still establishes exclusion.
O1-O5 and untested branches are recorded in the closure, not silently repaired.
This report append is expected mechanical closure and does not need another call.

Before model disclosure of fresh source, perform strict intake and privacy-safe
sanitization/allowlisting, especially confirm/success/NP sources. Matching hash-only
consumers do not grant raw source disclosure or prove runtime. Current status is
OWNER_SOURCE_CAPTURE_PENDING, no functional fix/visual patch/deployment.

Owner runs once in PowerShell:

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-17_checkout-source_owner_20261026.ps1'
```

Expect `RD-17_CHECKOUT_SOURCE_READY`. Send that output or the exact failure.
If failed (including SSH auth before remote execution), preserve the created
local folder and remote evidence, do not rerun or delete artifacts manually;
the executor will assess a fresh recovery identity if needed. No upload/apply,
DB action, order/shipment or message follows from running this collection.

## Owner capture accepted and functional trace - 2026-10-26

Current state: SOURCE_ACCEPTED_RECEIVER_PRECEDENCE_QUESTION_PENDING.
Owner reported RD-17_CHECKOUT_SOURCE_READY. Strict local intake accepted the
complete 27-source archive (739655 bytes), exact 31 hash-only dependencies,
manifest/receipt structure and both hashes. All 27 prior RD17 source identities
remain unchanged. Collection: 2026-10-26T11:16:38Z through 11:16:39Z.
Archive SHA-256: `04d56ac2cce8ae356d9f72664a4e6d847accac385fb5c62ba4f5e4a555e9fcf2`.
Manifest/receipt identities and all source hashes are recorded in
`diagnostics/RD-17_checkout-source_review_evidence_20261026/owner-intake-r1.json`.
The complete bounded source-signature screen found no hits. This is not a
universal secret-absence proof; only necessary technical snippets were read.
No raw protected order/notification source was exported or disclosed.

All applicable ACC009 target hashes match their accepted after state; RD16 WP2
legitimately supersedes the older profile controller/view identities. 21 new
sources have exact retained native after/source matches. Three more (confirm
and the two NP form templates) match the later accepted composition-context
ledger. Remaining shipping_method, NP admin form template and NP catalog shipping
controller are first current baselines here; no unsupported prior-release match
is claimed for them. Source identity is not runtime/owner QA evidence.

The order model hash matches the accepted ACC009 composition-context identity;
the three admin order consumer hashes match the older accepted courier-admission
ledger. This reuses scoped consumer contracts only; the raw source bodies and
unlisted literals remain private. New theme counts are all zero for six named
catalog checkout/NP views. Metadata remains source-capture context, not complete
event/OCMOD/pre-application admission.

### Verified current data path

- checkout.php:114-128 reads receiver_override. First/last names have customer
  profile fallback; middlename has no mapped-profile fallback at all.
- checkout.php later builds acc009_seed.view from readiness.sessionOrder(). The
  JavaScript initialReceiver() uses that seed to populate the card, which can
  override the earlier controller attribute fallbacks with saved-address names.
- readiness.sessionOrder():96-125 starts from session customer/shipping address,
  then applies the authorized session receiver override. view():126-134 reads
  rd13_receiver_middlename from order/shipping custom_field. It never resolves
  the current mapped profile patronymic.
- receiver.save persists a full order-only receiver tuple/revision after native
  validation. It has no provenance metadata identifying profile-derived defaults
  versus a deliberate manual recipient or explicit empty middle name.
- confirm.php:118-136 and :214-222 copy the accepted receiver tuple into new
  contact/shipping snapshots and their existing rd13_receiver_middlename keys.
  No historical-order rewrite is needed or authorized.
- NP admin acc009RecipientNameDefaults():353-370 already reads the shipping
  snapshot key, distinguishing absent/malformed values. It must keep reading the
  historical snapshot, never today's live profile for an old order.
- Existing success handling clears receiver_override at successful order end.

Root cause is the missing profile-to-effective-receiver bridge plus competing
initial seed, not profile persistence or a missing decorative label. An isolated
Twig input change would leave declaration/order/NP payloads inconsistent.

### Required recipient-precedence clarification

An asynchronous owner question is pending. Recommendation: new/default checkout
uses current profile full name, preserve an existing explicit/legacy receiver
tuple, and offer a deliberate "use my profile details" action. Alternative:
reset to the current profile on every checkout opening, allowing a later manual
edit. The legacy tuple does not record intent, so choosing the latter silently
could replace another person's recipient data. The owner's previous scope grant
stands; this is a new behavior-precedence choice, not repeated permission for
the profile bridge. Do not author the dependent resolver until it is answered.

### Independent default-address preparation

Prepared a local candidate server action and helper under the ignored
`work/rd17-main-20261026/`, not a released patch. It accepts authenticated POST
through actual account/login.validate, checks integer address_id and ownership,
locks the customer's address range, changes only default flags, confirms exactly
one default and commits. It preserves address JSON/text, foreign-customer rows
and the already selected checkout shipping/payment addresses. Repeating an
already-default selection issues no UPDATE. Exceptions/false query outcomes
fail closed with rollback and fixed public messages.

Focused native PHP check: 14 cases passed, running the full current Address
controller with the added method, actual Login.validate and authored helper.
Fake registry/customer/DB/response boundaries are explicit; this proves neither
real row locks/InnoDB nor live hosting execution. Cases include success/no-op,
foreign/missing address, bad method/token/login/ID, begin/lock/update/commit/
confirmation failures. Initial fixture build assumed CRLF; actual source is LF,
so the builder was corrected to preserve exact source LF before tests passed.
Original protected controller prefix remains byte-identical in the candidate.
Results: `work/rd17-main-20261026/default-test-results.json`.

No main runner, visual patch, independent functional CODE approval, deployment,
canonical status/Git mutation, DB/customer/order/NP/provider action or owner QA.
Once the owner answers the receiver-precedence question, continue the already
authorized source implementation and coherent tests/review without another scope
confirmation. RD18 remains unstarted.

## Continuation: precedence resolved and WP1A prepared

The owner answered the pending question on 2026-10-26: preserve existing order
recipient tuples; new checkout uses current profile names. The exact button is
`Підставити дані з профілю`, hidden without profile patronymic or when the tuple
already matches, and shown only after editing/saving settles. It copies names
through the existing receiver writer without changing telephone/profile/address.
The full accepted answer is recorded in `plans/RD-17_scope-amendment_20261026.md`.
No further behavior/scope question is open for this bridge.

Prepared the independently deployable prerequisite RD-17-WP1A (seven targets,
including one new read-only helper). Integrated subscription CODE/composition
review is in progress; no source-collector verdict approves its implementation.
See `diagnostics/RD-17_WP1A_profile-receiver_report_20261026.md` for the exact
artifact pair, native/fake evidence boundaries, admission, receipt and rollback.
Address UI/default action remains WP1B, not released or deployed. RD18 remains
unstarted. No agent server, DB/business, Git or status action occurred.

### WP1A review outcome

Fresh affected actual subscription Claude review request
`c6136634-dd50-4169-a055-7887fed66065` returned Review OK for owner Upload/Verify
after repairing initial M1/M2. Exact pair and complete verdict/identity/closure
are in the WP1A report/evidence. All 75 declared inputs stayed unchanged during
that review. Owner next: Upload -> Verify -> return receipt before Apply.
No deployment, owner QA, WP1B/RD17 completion or RD18 execution is claimed.

### Owner Verify stop

At 2026-10-26T13:23:14Z, owner Verify returned PHP8.0.30 and lint success,
then `error=dependency_source_drift`, without admission. The existing uploaded
reviewed pair/state are preserved; Apply remains HELD. The exact path cannot
be identified from the generic error. A separate fixed-path hash-only diagnostic
is undergoing integrated CODE review; it will observe the original 107
dependencies and seven target states without source bodies/DB/production writes.
See the existing WP1A report for receipt identity, known-stop classification
and the next bounded action. No blind Verify retry or one-hash pin update.

### Mono reconciliation and successor review hold

The owner hash capture at 2026-10-26T13:53:06Z strictly validates all114 rows:
106 dependencies and all7 target-before states match, only the Mono controller
is bed53fa... instead of inherited6c53f4.... The exact current hash equals the
retained, independently reviewed and owner-applied October23 diagnostic source.
Two recorded literal changes reproduce/reverse the whole source exactly; no
new pull is needed. See the WP1A report/evidence for complete hashes/lineage.

Successor r3 preserves all7 payloads/targets and old stage/state, changes only
the reconciled dependency admission and four new-stage/state owner literals.
Local16 native Confirm/profile/readiness->Mono, eight engine and six mocked
owner-step cases (PowerShell5.1/7) pass. Fresh integrated review request
c5ce87dc-fd49-4c65-a81c-cd49ae8edf4b was refused by the Claude five-hour session
limit (reset19:50 Europe/Kyiv). No substantive verdict; r3 remains HELD.
No owner Upload/Verify/Apply now, no replay/paid fallback/login/key action or
new source pull. Resume a fresh review request after quota availability; no
automatic future run is installed. RD17 deployment/QA and RD18 remain pending.

### Successor r3 accepted review after owner resume

Owner resumed after the reported quota reset. Fresh request
f7518901-281c-4d55-a1ea-a69fa9aa1092 returned Review OK for only the exact r3
runner/owner-packet Upload and Verify, with no mandatory findings. All59 declared
inputs stayed unchanged during review; old pair/state are preserved. Recorded
Mono lifecycle sequencing and corrected the origins label mechanically, without
changing code or rerunning unaffected tests. See the existing WP1A report and
new r3 owner handoff for exact hashes and receipt contract. Next: owner Upload
then Verify on the new pair, return full receipt before Apply. No deployment,
owner QA, address WP1B, RD18, agent server/Git/status/business writes occurred.

### Owner r3 source deployment receipt accepted

Owner returned successful Apply: all18 machine rows, all7 before/after hashes,
six exact backup members/new-helper backup none, admission/lint, self-delete,
81 compiled template removals/cache success and staged-r3 absence match.
State records applied/applyAttempted true; exact reviewed executable pair is
unchanged. The emitted backup is in the main WP1A report and receipt JSON.
Complete expected output closes mechanically; no additional result model call
or repeat Apply/cache cleanup. This is owner source-deployment evidence, not
authenticated browser/FPM/payment/NP/customer or owner-QA proof. Owner numbered
checkout/profile/button QA is next; no real order/payment is required. Address
WP1B and all RD18 work remain pending; no task Done/status/Git/Notion mutation.
