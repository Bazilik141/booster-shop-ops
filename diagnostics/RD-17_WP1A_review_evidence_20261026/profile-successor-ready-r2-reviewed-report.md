# RD-17 WP1A: profile recipient bridge

Date: 2026-10-26. Executor: Codex, assigned by owner. Current status:
R3_SOURCE_RECONCILED_REVIEW_HELD_CLAUDE_LIMIT. No Apply or owner QA.

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


## Accepted r2 review and mechanical release closure

Actual independent subscription Claude returned **Review OK for owner Upload
and Verify**, request `c6136634-dd50-4169-a055-7887fed66065`, model
`claude-opus-5-5`, effort high, tools/MCP/hooks disabled, no paid/API fallback.
Complete response and original provider result are preserved under
`diagnostics/RD-17_WP1A_review_evidence_20261026/profile-ready-r2-*`.
The verdict binds runner
`27c6bec2e5113d03313b5ed3ca5074e48748f20bdfbe73d6e09968feda2e6bd4`
and owner packet
`ae92eb4a2f3db35a186c912af5c85017a09f2cf4a2238906e3f2a293b3d190ff`.

No mandatory findings remain. Actual review closed M1 by the implemented
deterministic degradation and M2 by the explicit recipient-change/QA statement.
O1/O5 were repaired and rechecked; remaining optional notes are recorded above.
Additional r2 optional note: legacy profile first/surname that fail the existing
ACC009 name rules (including too-short or Latin names) skip the bridge and
patronymic seed entirely, preserving the pre-patch checkout projection. This
does not change name rules or rewrite legacy profile data. The pinned mysqli
driver contract is required; a transient metadata DB error deliberately blocks
the unverified new-default snapshot until recovery. A forged copy POST with
invalid required profile names receives a generic missing-middle refusal; the
normal UI hides that button.

All 75 declared inputs and the exact pair remained unchanged through review;
the tracked dirty-repository content comparison found no changes. The monitor
covers that declared set plus tracked dirty content, not a whole-untracked-tree
or production certification. Local closure separately recomputed payload,
runner and owner-packet identities; the reviewer bound the declared hashes
and stated that it did not itself recompute SHA-256. The original reviewed
report is preserved as `profile-ready-r2-reviewed-report.md`; this appended
verdict/status bookkeeping changes no executable bytes or acceptance behavior.

Host Verify must establish all 107 conditional pins and runtime admission.
The next owner action is Upload then Verify and return the complete receipt
before Apply. No host execution, deployment, owner QA, address-WP1B release,
RD17 completion, RD18 work, Git, Notion or canonical status update is claimed.


## Owner Verify stop and bounded hash diagnosis

Owner ran Verify at 2026-10-26T13:23:14Z. Saved complete stdout reports PHP
`/usr/local/bin/php`, version 8.0.30, runner lint success, then only
`error=dependency_source_drift`. State binds the unchanged reviewed r2 pair and
records uploaded=true; no admission, verified or applyAttempted entry exists.
The native runner check reaches its dependency comparison before production
target writes/backups. This is a known fail-closed result, not ambiguous partial
Apply; mechanical classification does not require a separate model-result call.
The original content verdict remains recorded, but host composition is HELD.
No dependency pin, executable pair, staged runner, owner state, cache or source
was changed or retried by the executor. No agent server/key/DB access occurred.
The evidence record is `owner-Verify-dependency-stop_20261026.json` in the
existing WP1A review-evidence folder. Of the 107 dependencies, 48 have current
raw files in the two RD17 local captures; all 48 match their expected pins.
The offending live path(s) cannot be inferred from this generic stdout.

Prepared a separate owner-only hash inspector and exact owner transport:
`diagnostics/RD-17-WP1A_dependency-drift-inspect_20261026.php`
SHA-256 `55e5339d45e4aa6e414617c4cf5c0bd330d135aa051061d906467efaa1f407da`;
`diagnostics/RD-17-WP1A_dependency-drift_owner_20261026.ps1`
SHA-256 `acafd789f9fad535e975ef5ca034c8a82543e512ac3282587ebc742ae113eed3`.
Status: LOCAL_CANDIDATE_NOT_REVIEWED. Required integrated independent CODE review
is for this new diagnostic pair, not another result-review stage.

The fixed manifest is exactly the original runner's 107 dependencies plus seven
before/after target contracts. Local build checks the embedded original tables
against that manifest. The owner streams PHP via the existing noninteractive
strict-host-key SSH transport, with no uploaded diagnostic file or remote export.
It uses PHP8.0 -n, never requires config or application code, reads no DB/session/
log/customer records and exports no source bodies; it emits only named technical paths, SHA256, file
sizes, timestamps and matching flags. It checks the existing private staged
main runner hash, exact cwd/root/canonical paths, no symlinks, and stable double
hash/stat observations. Missing files/parents are reported absent. No production
target/metadata/cache writes, provider calls, key/setup/reset or auto-retry.
Backup/rollback/self-delete: none needed; stdin/heredoc only; no persisted remote diagnostic file. A shell may use an
unlinked transient heredoc temporary file.

Owner transport verifies exact local inspector/main/main-packet hashes and the
uploaded/not-applied state; it does not modify that state. One capture creates
only `work/rd17-main-20261026/owner-drift-r1/` locally. It saves raw stdout before
validation, validates every receipt field and all 114 ordered manifest rows,
integer counts, match flags, observed hash/absence states and totals, then saves
receipt.json and prints only mismatches. PowerShell 5.1 uses direct JSON-array
assignment; newer PowerShell uses DateKind String when supported so ISO capture
timestamps stay strings. Capture/replay guard is local: an existing directory
requires returning its saved receipt rather than issuing another capture.

Local evidence: PHP8.0 lint passes; 10 native filesystem/control-flow cases pass
with fictional root/staged runner/manifest hashes, including match, changed/missing
dependency, missing parent, new-helper collision, staged hash drift, bad manifest,
wrong root, traversal and no source mutation. Production guards/functions remain
native. Nine native owner packet AST/hash/state/receipt cases pass each on
PowerShell5.1.26100.9711 and 7.6.5 with the entire native process/SSH function
replaced by a deterministic local mock; malformed/missing/contradictory/extra
receipt data, transport failure, applied-state and replay directory are refused.
No actual SSH, production source/DB, host filesystem or transport was exercised.
Initial local fixture issues (Windows PHP is_readable reports false although
hash_file succeeds, and 5.1 JSON-array/newer ISO auto-conversion differences) were
resolved before review; actual hash reads fail closed on errors, and both shells
pass the final packet tests. This is local evidence, not live hash proof.

Expected owner output: `RD17_WP1A_DRIFT_CAPTURE_READY`, dependency/target drift
counts, offending paths with expected/actual SHA and local receipt path. This
means only a validated observation, never admission. Even zero drift does not
authorize retry/Apply. Return the result for bounded reconciliation: identify
the changed owning source/accepted release, request only needed fresh content
if required, repair/review affected logic before a successor admission. Do not
replace one hash blindly. Main WP1A Apply/QA, address WP1B and RD18 remain pending.


### Hash diagnostic: Review OK and mechanical closure

Actual subscription `claude-opus-5-5`, effort high, request
`d3a5c7af-7c10-495e-a960-421af14f41fa` returned **Review OK for one owner hash
capture only**, with no mandatory findings. Complete original result/response/
run and scoped identity checks are preserved as `drift-ready-r1-*` in the
existing WP1A review-evidence folder. Binding pair:

- PHP `55e5339d45e4aa6e414617c4cf5c0bd330d135aa051061d906467efaa1f407da`
- PS `acafd789f9fad535e975ef5ca034c8a82543e512ac3282587ebc742ae113eed3`
- Decoded 114-row manifest `409bfa8a9a50f5a644ec62d9dd49939c08249d42a02e773cf43f8323528b412e`

All 31 declared inputs stayed unchanged during review. The original main pair
and local uploaded/not-applied state remain unchanged. The reviewer bound the
declared hashes, sampled encoded spans and expressly did not recompute SHA256;
local mechanical closure supplies exact byte identities and builder/manifest
equality evidence. No source-pin refresh or production proof follows.

Two outside-set files changed during review: ROADMAP_SOP.md and the dashboard.
Their bounded current diffs concern TECH-050 future staging/PHP migration,
already-recorded RD18 H1/D2 prose, and canonical review/receipt governance. They
are not read/executed by this inspector, affect no diagnostic/main target/hash,
and do not prove a host PHP change. They were preserved, not authored here.
The identity closure records before/after hashes and the scoped monitor limit;
this is no whole-untracked-repository or host certification.

Optional notes recorded: stderr visibility/transport stalls or a local failure
may leave an empty/raw-only drop; return its evidence rather than rerunning.
PowerShell7.0-7.4 may coerce ISO strings then refuse the receipt, so the released
one-line owner command explicitly uses the locally tested Windows PowerShell5.1.
If such parsing already failed elsewhere, revalidate saved raw stdout locally;
do not recapture. Stdin/heredoc has no persisted remote diagnostic file, though
bash may create an unlinked temporary file. `absent` means not visible to the
owner account, not independent proof of deletion. Additional parser/state/TTY
hardening suggestions are optional and deferred; executable bytes are unchanged.
Linux filesystem/actual SSH transport were not exercised locally. The report's
wording/this verdict bookkeeping does not change diagnostic acceptance or code.

Next owner action: one `-NoProfile -File` invocation of the diagnostic owner PS
using `C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe`, no -Step.
Return console output; raw/validated receipts will be available locally at the
named work drop. No main Verify/Apply, pin edits, remote cleanup, key setup,
deployment, customer/order/DB writes, QA or RD18 work is authorized by this
capture. Main WP1A stays HELD until bounded source reconciliation is complete.


## Reconciled Mono source and successor r3

The owner completed the reviewed hash capture once at 2026-10-26T13:53:06Z.
Strict local JSON validation rejects duplicate keys and checks raw vs saved
receipts, all fields, 114 ordered manifest rows, counts, flags and states.
Result: 106/107 dependencies match, all seven targets match before; the new
helper is absent. Only `extension/mono_chast/catalog/controller/payment/mono_chast.php`
differs: expected `6c53f4129166dfeb9c8654b33edc99a663925f61d58b0a6cfed700dac76bd2ac`,
observed `bed53fa4185b873dbb782224e7e73f98f4a0cdb962b74226a3501bb7b4957a76`.
No source/DB/cache/production write or source-body export occurred. Capture is
consumed; do not rerun it. Raw/validated receipts stay in owner-drift-r1.

This is an identified omission in RD17 dependency composition: the inherited
ACC009 N2 pin preceded the already accepted 2026-10-23 Mono diagnostic release.
That release's exact retained after-image equals today's observed hash/size.
Its two recorded literal replacements reproduce the whole after-image from
the retained before-image; reversing them recovers the original bytes exactly.
They add MC01/MC02 support-code responses and MC04/MC05 labels at the existing
error sites. Readiness, identity, ownership, idempotency, bank/API/create and
callback code are unchanged. Previous actual independent review
`b73b49ce-8207-43fb-b522-7bdf589f7693` and the complete accepted 42-line owner
Apply receipt establish that deployed diagnostic lineage. A fresh source pull
is unnecessary because the current host hash identifies that exact retained
source. See `owner-drift-r1-reconciliation_20261026.json` for exact evidence.
This establishes source identity, not live payment/FPM/customer resolution.

Prepared a fresh successor pair, still LOCAL_CANDIDATE_NOT_REVIEWED / HELD:

- `patches/RD-17-WP1A_profile-receiver-r3_20261026.php`
  SHA256 `ef0d7a7d87c58340b73cfdbb0fc26a497920adb7c38eeceaf8913dbe803f8e85`, 401823 bytes.
- `diagnostics/RD-17-WP1A_owner-r3_20261026.ps1`
  SHA256 `0a2b8c0935a56197398268ba549857bf07badeae722f7b8c5c5ac1bfd6912712`, 11883 bytes.

The only engine change is the reconciled Mono dependency in the encoded
107-entry table. Masking that table recovers byte-identical engine source;
the full unchanged seven-payload table and every target before/after state
remain exactly those accepted in r2. No Mono file is a write/rollback target.
The owner packet has exactly four reversible literal substitutions: runner
name, SHA, new local receipt directory, and private staged basename. Reversing
those substitutions reproduces the entire reviewed old packet. Cache, receipt,
backup, restore, admission, six steps, no-replay and runtime logic are unchanged.

New private stage:
`/home2/boosters/private/booster-patches/RD-17-WP1A_profile-receiver-r3_20261026.php`.
New local state/receipts: `work/rd17-main-20261026/owner-profile-r3-receipts/`.
The old executable pair, private stage and uploaded/not-applied state are
preserved; there is no automatic old-file cleanup, overwrite or state migration.
Internal task/lock/backup identity deliberately remains
`RD-17-WP1A_profile-receiver_20261026` so old/new runners cannot become competing
writers. Backup prefix remains the same UTC/random directory family; actual
Apply will emit it. Backups are original-1 through original-6 plus manifest;
new helper index0 before absent, backup none. Rollback preflights all seven
targets/needed backups, restores the six existing files and removes only the
new helper. It never restores, edits or removes Mono or its diagnostic codes.

New focused native evidence: PHP8.0.30 `-n -d allow_url_fopen=0` ran 16 complete
RD17 Confirm/profile/readiness -> exact current Mono cases. Native code handles
declaration/order creation/seal/matching/accepts and full Mono confirm; the
profile per-request cache is reset between endpoints to represent separate HTTP
requests. Covers parts3/4/5, profile/default/alternate/explicit-empty/guest names,
degraded metadata/legacy names, existing transaction idempotency, existing
MC01/MC02, changed profile/middle/identity or stale browser seal refusing before
bank calls. Registry/controller facade, DB/order/credit/cart/totals/rendering
and namespaced cURL are explicit local fakes; no network/global cURL, real
bank/order/customer data, host DB or provider action occurred. Native production
order/credit DB implementations and browser concurrency are not established.

PHP8.0 successor lint and seven payload hashes pass. Eight native local runner
packaging/rollback/failure cases pass for the successor basename with runtime
metadata/dependency identities faked. Six actual successor packet state/receipt
steps and replay refusal pass with SSH/SCP replaced, both in native Windows
PowerShell5.1 and PowerShell7. Original 37 resolver/receiver/NP, 15 Confirm,
12 browser and 13 event/theme cases are reused by unchanged executable input
identity, not rerun or relabelled as host proof. No new CSS/JS/UI signature or
business/name-rule change. Existing RD17 source/QA limits remain.

Required next step: fresh affected integrated CODE/composition review of this
exact new runner/packet pair, the reconciled current Mono source/lineage,
interaction tests, preservation/transport/rollback and expected receipt.
This is not a separate result review of the known source stop. After Review OK,
owner Upload then Verify on the new pair, return the complete receipt before
Apply. Host still must re-admit runtime and all107 pins; current capture is no
future guarantee. New paths isolate state, while the unchanged shared lock
serializes writers. No main deployment, source pin edit on the server, real
bank/customer/DB action, Git, status/Notion/dashboard write or RD18 work here.


### Successor r3 review dispatch refused by subscription session limit

Fresh actual request `c5ce87dc-fd49-4c65-a81c-cd49ae8edf4b` was consumed on
2026-10-26. Native saved-subscription admission passed, tools/MCP/hooks were
disabled, requested model `claude-opus-5-5`, effort high. The provider returned
exit1/is_error=true with a five_hour rejection before substantive review:
`You've hit your session limit · resets 7:50pm (Europe/Kyiv)`.
Observed reset is 2026-10-26T17:50:00Z (19:50 Kyiv). No paid overage/API fallback,
auth reset/login/key operation, retry, substitute model or model verdict.
The actual observed assistant model was synthetic refusal, not an Opus CODE
review. Full original result and run are preserved as
`profile-successor-ready-r1-original-result.json` / `-run.json`.

All57 declared inputs and exact successor pair remain unchanged after refusal;
the input packet is 833914 bytes, SHA256
`40d2fa985d56a8d71c625603b4ee742165c4392017cb3907302ecab1bcbda170`.
The original submitted report is preserved separately; this appended failure
bookkeeping does not change either executable. No old accepted verdict covers
the new r3 admission contract. Successor remains NOT_REVIEWED / HELD despite
completed source reconciliation, local16 native interaction cases, eight engine
packaging cases and six mocked owner steps on both tested PowerShell engines.

The hash diagnostic is closed mechanically on its complete expected receipt;
source identity is reconciled and no fresh pull is needed. Old uploaded
runner/state/pair is preserved, never retried or applied. No owner action is
needed now; do not run old/new Upload/Verify/Apply before successor Review OK.
On a later continuation after reported quota reset, verify unchanged artifacts
and use a fresh review identity with retained history; never replay this consumed
request or rebuild unaffected code/tests solely for quota. No automatic retry,
scheduled continuation or background execution is installed or promised.
Owner deployment/QA, address WP1B and all RD18 work remain pending.

### Owner resume after reported quota reset

Owner replied `go` on 2026-10-26; current tool time is after the reported
17:50Z reset. Saved subscription/native CLI2.1.303 identity is verified unchanged,
without opening credentials or commissioning a model probe. The successor
runner ef0d7a7d87c58340b73cfdbb0fc26a497920adb7c38eeceaf8913dbe803f8e85
and owner packet 0a2b8c0935a56197398268ba549857bf07badeae722f7b8c5c5ac1bfd6912712
are unchanged. Of the prior57 declared inputs, only this report's recorded
quota/failure bookkeeping changed; all sources, instructions and harnesses
remain identical. Original uploaded state is preserved; no new r3 owner receipt
directory exists. No repeat source pull, code rebuild or unaffected test rerun.

Proceed with a fresh successor CODE request/stage, retaining the consumed failed
request and its original result. The exact pair stays HELD until an actual
accepted verdict and identity closure. No owner Upload/Verify/Apply or agent
server/SSH/key action follows merely from this resumption.
