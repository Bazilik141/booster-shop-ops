# RD-17 WP1A: profile recipient bridge

Date: 2026-10-26. Executor: Codex, assigned by owner. Current status:
LOCAL_CANDIDATE_NOT_REVIEWED_NOT_RELEASED. No production execution or owner QA.

## Result and owner authority

This prerequisite implements the owner's approved profile-to-checkout full-name
bridge and the exact `Підставити дані з профілю` button. The governing amendment
is `plans/RD-17_scope-amendment_20261026.md`, including the final precedence answer.
New authenticated/default checkout uses the current own profile first/surname
and mapped optional patronymic. Any owned legacy/explicit order recipient tuple,
including an explicit empty patronymic, remains authoritative. The button is
hidden without a profile patronymic, when all three names match, during name
entry, and while the existing receiver/payment coordinator is busy. It appears
after entry settles. Copy uses fresh server profile data, preserves telephone,
and goes through the existing single receiver save/revision writer. It does not
write customer/address data. No historical order, profile metadata or name-rule
change; no provider/payment/fiscal/notification semantics change.

RD-17-WP1B (address light-A UI plus direct default-address action) is separately
pending. No address implementation is in this release. RD-18 has not started;
it waits for all RD-17 deployment and owner QA. Shared CSS/header/shell are not
targets. H1 remains granted for future address visual packages.

## Causal source diagnosis and evidence

`catalog/controller/checkout/checkout.php:114-128` has customer first/last-name
fallbacks but only reads patronymic from `rd13_receiver_override`.
`BoosterCheckoutReadiness::sessionOrder` projects saved shipping-address names
and overrides, and its seed drives reskin `initialReceiver`; fixing only the
Twig input would leave readiness/declaration/new-order snapshots inconsistent.
`Confirm::index` composes the order snapshot; NP's exact
`acc009RecipientNameDefaults` method reads its shipping custom-field snapshot,
not a later customer profile. The new shared read-only resolver is applied at
the readiness and new-order composition boundaries. A profile change does not
rewrite an already completed order. Guest behavior is preserved.

Explicit recipient change under owner option 1: without an owned receiver
override, a selected/default saved address may contain another person's name.
Before this patch that saved name seeded the receiver; now the current profile
first/surname/patronymic replaces it in the card, new order and NP snapshot until
the customer edits the receiver. An owned explicit/legacy receiver still wins,
including after an address selection. The stored address itself is not changed.

Inputs: accepted owner RD17 27-file capture at 10:51:48-49Z and checkout 27-file
plus 31 hash-only capture at 11:16:38-39Z on 2026-10-26. Their manifests/intake
records are in the existing RD17 WP0 and checkout-source evidence folders.
Supplement rebinds all original 27 files; comparison with later accepted
ACC009/RD16 sources resolves earlier historical-context differences. Do not
treat the historical comparison's false rows as permission to reuse old source.
Theme counts for the six collected checkout/NP views were zero. Runtime PHP
was 8.0.30; template cache is the exact path below. No live data rows were read.

The source profile mapper retains unchanged raw OpenCart transport text. The
resolver therefore distinguishes stored snapshot strings from one-layer-decoded
DOM strings; apostrophe entities are tested end-to-end. Optional profile
registry/metadata validation failures, malformed customer custom-field JSON,
absent/non-string/invalid patronymic deterministically use profile first/surname
with middle empty. Invalid profile first/surname skips all profile defaults and
returns the pre-patch contact/address projection. names() and seed use the same
tuple. DB/query and identity failures remain fail-closed; a query adapter makes
DB exceptions distinct from registry-validation exceptions. Existing name
validators, optional clear, JSON preservation and ownership remain unchanged.
Courier readiness still requires a manually entered valid patronymic when the
profile cannot supply one; warehouse checkout remains available without it.

## Exact executable pair and target contract

Runner: `patches/RD-17-WP1A_profile-receiver_20261026.php`
SHA-256: `27c6bec2e5113d03313b5ed3ca5074e48748f20bdfbe73d6e09968feda2e6bd4` (401823 bytes).

Owner packet: `diagnostics/RD-17-WP1A_owner_20261026.ps1`
SHA-256: `ae92eb4a2f3db35a186c912af5c85017a09f2cf4a2238906e3f2a293b3d190ff` (11874 bytes).

| Live relative target | Required before SHA-256/state | Required after SHA-256 |
|---|---|---|
| `system/library/booster_checkout_profile_receiver.php` | `absent` | `51b644c8ff74e01fb5fe5dae0d53d6c745625ec548cff3c02686eda778fe4389` |
| `system/library/booster_checkout_readiness.php` | `a21e8ccf26b7cec24e8faaabc1d59a6ec164e7a487e08f66349942de08dab7c3` | `3141e73fab09199c56927b7593f1547169a5c8f06c83e40e552f356dfd7c9529` |
| `catalog/controller/checkout/confirm.php` | `333c43b9da2c6101d44e5676a78648b8ab9b75296380675944fef88a3ee69288` | `30e8d442cc997d2b6e975eb3d3d09bcef856344456807f7d2633fb6ff6fb8a1e` |
| `catalog/controller/checkout/checkout.php` | `dab58181bf151676320cc40e8920e9b98296a1d73cba6fd540e5f4372e6096ed` | `0491ddbda5f7b33246071a520379ee7bde80d6785505c9218d8ba393e0b23a94` |
| `catalog/controller/checkout/receiver.php` | `2b55d995af3d30d6213ef8020c6084fa7ec7759a14dce21f6d9f9cb34240bfe1` | `f429b304e3fabfbd2510f36a3ea270fd3fed891de9c865fa7d86063748f43d01` |
| `catalog/view/template/checkout/checkout.twig` | `e17c963336b37183c16761cf3e966e2fc47768e5749fe0078b5c6ee14f1b5260` | `d8cd655d1ae69727fc895b92f56429f0c27c7b39f5e9acb6c852f190c3157f46` |
| `catalog/view/javascript/checkout-reskin.js` | `a73a0209021266618ef395bc30d50be0fd3071a02035c43d79c0b5b9c79db8e6` | `dbafb5c8beba1582f72cb0177f9ba9c57e06e3f4d24c76ac18d9f2d16117e212` |

Root is `/home2/boosters/public_html`. New helper before=`absent`, backup=`none`.
Each of the six replaced targets is backed up beneath the exact emitted
`/home2/boosters/public_html/_patch_backups/RD-17-WP1A_profile-receiver_20261026-<UTC>-<12hex>/`
directory as `original-<target-index>` plus `manifest.json`; rollback requires
all before hashes. The target table order above defines each zero-based index.
The runner and packet refuse changed targets, dependencies, unsafe paths,
symlinks, new-helper collision and a foreign lock. All backups and staged lints
precede target writes; post-write failure attempts restoration, with explicit
failure state. Idempotency is checked before dependencies, so an already-applied
repeat does not refresh another asset token. The checkout view's existing
reskin asset token is replaced by path (`rd17-profile-20261026`); the shared
header DS token and stylesheet remain byte-identical.

## Admission, dependency and privacy boundary

The 107 hash-only dependencies cover source consumers, native engine/startup/
events/template injection and known payment callback contracts. The freshest
58 source/hash identities supersede inherited identities. Additional historical
pins are conditional constraints, not proof of their present host identity.
Owner Verify must establish all pins; any drift is STOP and reassessment, never
blindly changing one expected hash. Dependency origins and exact identities are
in `work/rd17-main-20261026/profile-dependenc{ies,y-origins}.json`.

Runtime admission checks exact application/config/cache paths, technical schema,
zero applicable theme overrides, known normalized relevant event set (12),
payment-code inventory, mapped profile metadata, and generated-source override
absence. It uses a read-only transaction with metadata only; no customer/order
rows are read during admission. The mapper/guard hashes are checked before
requiring metadata code. Verify returns an admission digest; Apply recomputes it
and refuses changed facts. No mysqlnd/get_result dependency, DB mutation, schema
change, migration, raw credential output or SQL rollback.

Raw order/notification model bodies remain excluded: exact host hashes and
accepted source contracts are reused within scope. Fictional fixture names are
not customer records. Native application runtime will SELECT only its own
authenticated customer's first/surname/custom fields when needed; that normal
feature read is not an agent query. No agent SSH/key/server action occurred.

## Local validation and limits

- PHP8.0.30 lint of five target PHP sources and runner; seven decoded payload
  identities, UTF8 Ukrainian label and LF preservation verified.
- 37 native resolver/mapper/readiness/receiver/exact NP-name-method cases, with
  DB/auth/cart/response and NP class wrapper faked. Includes guest, legacy and
  explicit empty tuples, inactive/malformed mapping, hostile source/error
  handling, stale revisions, latest server names, ignored forged copy names,
  absent/Unicode-empty patronymic and raw/display apostrophe transport.
- 15 actual full Confirm.index plus readiness declaration/order/seal cases:
  preview no write, new profile order, manual/optional-empty recipient, stale
  declaration refusal, existing completed order unchanged, profile read failure
  before order write. Registry exception/inactive map/bad JSON/non-string or
  absent middle each passes warehouse readiness -> Confirm -> seal -> accepts;
  invalid profile first name preserves the old projection through that path.
  Metadata DB errors remain blocked; degraded courier still requires middle.
  Cart/totals/order store/provider lookup are fakes; no real
  order/provider/DB was exercised.
- Actual Checkout.index plus pinned Twig ArrayLoader renders the modified real
  checkout template root/profile/seed/token. Child views and framework context
  are fake. This is root-data rendering proof, not a production page.
- 12 isolated Chrome/Playwright cases use full real common.js/coordinator/reskin
  and the reskin-generated recipient DOM, current Bootstrap/DS, at 390/768/1280.
  Names at 32 characters, overflow, hover/keyboard focus/active, edit settle,
  absent/Unicode-empty middle, stale server profile, duplicate signature,
  name-edit response race and telephone-only queued race pass. The host shell
  and HTTP backend are synthetic loopback services; no full payment/provider
  integration or live account/browser session was exercised.
  Server copy-refusal warning is now displayed verbatim as text; browser/native
  revision-echo tests bind the same error response shape. Eligibility uses the
  validated helper tuple, and receiver checks raw stored middle once.
- Eight runner engine cases execute the actual payloads/file engine on local
  PHP8.0; runtime metadata and dependency identities are substituted. Apply/
  partial rollback/new-helper removal/no-op, corrupt backups, helper/target/
  dependency drift, wrong admission, lock and post-write restoration covered.
- 13 exact native event classifier/theme-path cases; no host mysqli execution.
- Actual owner packet AST/six-step receipt/state transitions and apply replay
  refusal pass with SSH/SCP replaced by deterministic local mocks. No network.

Test sources/results live under `work/rd17-main-20261026/test-profile*` and
`profile-*-results.json`; renderer's pinned dependency manifest is included in
the review dependency set. UI signature scan: no new `!important`, timers,
absolute/fixed positioning or unexplained magic pixels. Existing reskin timers
and positioning remain historical; the button reuses existing DS styling and
native layout. A narrow disabled-attribute observer avoids self-trigger loops.

These checks do not establish live InnoDB, host source/cache/runtime, provider
behavior, live checkout acceptance or owner QA. Owner Verify and QA remain.

## Owner execution and receipt contract (HELD until independent Review OK)

The owner packet has six explicit steps. Staging is private, outside webroot:
`/home2/boosters/private/booster-patches/RD-17-WP1A_profile-receiver_20261026.php`.
The packet always invokes it with `public_html` as cwd. It uses the existing
owner SSH agent, strict known-host verification and PHP8.0 admission; no key/
account setup or cleanup is required here. Upload does not deploy. Verify is
read-only for production; stop and return the complete receipt before Apply.

1. `-Step Upload`: expected `RD17_WP1A_UPLOAD_OK`.
2. `-Step Verify`: expected `production_writes=0`, `admission_sha256=<64hex>`,
   `done=ok`, then `RD17_WP1A_VERIFY_OK`. A mismatch blocks Apply.
3. `-Step Apply`, only after admission acceptance: emits backup, target before/
   after hashes, `changed_files=7`, `self_deleted=yes`, `done=ok`; cache cleanup
   follows only success via `&&`, then `template_cache_cleanup=ok`,
   `staged_runner_absent=yes`, `RD17_WP1A_APPLY_OK_OWNER_QA_REQUIRED`.
   Receipts/state are saved in the ignored task work folder. A partial result
   or transport loss is unresolved; do not replay the consumed Apply.
4. Owner-only rollback choice: `RollbackUpload` -> `RollbackVerify` ->
   `Rollback`. The exact saved backup path is required. Before any restore,
   every target and required backup is preflighted, including partial restores.
   Reverse order restores six sources, removes only the new helper, self-deletes
   the staged runner and clears only compiled PHP templates under
   `/home2/boosters/ocartdata/storage/cache/template/`. No modification cache or
   customer/order/profile data is altered. Expected `rollback_preflight=ok`,
   then `rollback=ok`, cache/absence confirmations and owner-QA marker.

Owner UI QA after deployment: profile middle appears in a fresh checkout and
collapsed summary; explicit other recipient/empty middle survives revisit;
button eligibility and exact label match the owner decision; copy keeps phone,
does not reload and keeps latest typing; saved-address selection and warehouse/
courier transitions preserve recipient rules. No real order/shipment/payment
or notification is authorized by this packet. Existing order/provider behavior
has local snapshot evidence only. Report any unexpected behavior before WP1B.

Specifically select a saved address whose stored name differs from the profile:
confirm the shown receiver uses the profile until deliberately edited; the new
order/NP snapshot must match that receiver. This ordered-snapshot behavior has
native fictional-order evidence; actual order validation requires an owner's
authorized normal/test order, not an agent-created test order.

Operational limits: a profile changed in another tab may require checkout reload
after a stale declaration/409; automatic cross-tab refresh is deferred. Existing
in-flight pre-deploy orders/seals may be refused by the unchanged readiness
contract when their snapshot differs; use a quiet owner deployment window and
verify a fresh checkout before normal use. A killed process can leave the
private `RD-17-WP1A_profile-receiver_20261026.lock`; stop and assess its receipt
and process state rather than automatically deleting it. Cosmetic RD16_* engine
names are retained. These optional review notes do not authorize server actions.

## Independent review and closure

Initial actual integrated review (request 62f23d4f-51ac-4844-9f4d-b0919485bff3)
returned Changes required, bound to runner d0bb2116e40ca6acff58efe15ba4490bec0c6d2118f53a251c8c401e91ce6849
and packet 3ef1a07f0c6528c2a25311f278b25eef0eea24340934fed7433c2c4fbd3ed47b.
Complete response/run/original provider result and identity closure are preserved
under `diagnostics/RD-17_WP1A_review_evidence_20261026/profile-ready-r1-*`.
All 69 declared inputs stayed unchanged during that review; no tracked dirty
repository content changed. That is scoped identity proof, not whole-worktree
or host certification. Actual native subscription model was claude-opus-5-5,
effort high, no tools/MCP/hooks/paid fallback; duration 305747ms. Observed usage:
822355 cache-creation input, 2 input, 0 cache-read and 28002 output tokens.
Reported list-price estimates are not subscription debits.

M1 is repaired by reviewer option (a), deterministic optional-data degradation,
with added full native readiness/Confirm/seal/accepts coverage, not an owner
acceptance of fail-closed coupling. M2's saved-address recipient change and
corresponding owner QA are explicitly recorded above. Optional O1 (copy warning/
eligibility) and O5 (one-layer raw middle check) are repaired in the same batch;
O2/O3/O4/O6 are recorded above, with literal backup documentation corrected.
The new exact pair remains HELD pending a fresh affected substantive review.
Prior Review OK verdicts cover only source collectors. The coherent package
requires actual subscription Opus/high review
of exact runner and owner packet hashes, all seven sources, interactions,
tests/fake boundaries, admission/receipt/rollback and privacy before owner use.
No Git, Notion or roadmap status mutation, deployment, owner QA, or RD17/RD18
completion is claimed. Next action is the integrated ready-stage review.
