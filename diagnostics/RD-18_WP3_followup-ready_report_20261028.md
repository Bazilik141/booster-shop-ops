# RD-18 WP3: owner QA follow-up and current Nova Poshta state

Date: 2026-10-28. Executor: owner-assigned Codex. Filename/version identity
20261027 is retained for this ongoing prepared packet; no prior WP3 deploy exists.
Status: OWNER_APPLY_ACCEPTED_SEVEN_FURTHER_QA_CHANGES.

## Final outcome and explicit scope decisions

The owner's nine findings supersede the earlier detail visual acceptance rules
where specified. RD17 remains accepted; RD18 WP2 Apply succeeded but its final
owner QA was rejected. No new feature acceptance or canonical status write follows.

1. Remove WP2 owned CSS max-width1200/padding24 and its mobile padding rule.
   Detail inherits the same Bootstrap container as list/profile/address pages.
   Actual list/detail x,width,padding match at390/768/1280/1591. Shared shell code
   and styles outside the owned RD18 block are byte-identical.
2. The owner explicitly replaces ASC history with dateDESC,idDESC. SQL page10,
   customer/store/status visibility, counts and notify-only comments are retained.
   Native details is closed initially, with current status/date always visible.
   History pages are genuinely newest-first, rather than reversing one page.
3. The owner explicitly selected current NP state in the status block plus the
   official full-tracking link, released together with the other fixes. No full
   carrier scan-history feature, inferred carrier events or new status writer.
4. Plain cart icon with explicit circular wheels replaces the ambiguous plus/
   tiny path segments. Native per-product cart forms/entire inline JS unchanged.
5. Owner chose whole-order SUPPORT contact, not a new returns-request mechanism.
   One header action links to the existing support bot; per-product return links
   are removed from markup. No invented bot start/prefill protocol.
6. Payment is plain text, invoice remains visible; separate billing disclosure
   and stored totals/recipient/order data remain accessible and unchanged.
7. ACC007 IDs12(Returned) and24(Cancelled) disable the support-return button.
   Disabled control has no href; other problem-group states are not blanket-blocked.
8. Existing gold-soft note styling highlights actual order and notified-history
   comments. Generic explanations are not presented as actual comments. Escaping,
   linebreaks and notify filtering remain intact.
9. Back link and heading share one4px-gap group instead of the shared24px main
   gap. The back link stays44px tall; header return control is44px on mobile.

Owner return choice and NP choice are recorded in the current chat and
work/rd18-followup-20261027/owner-tracking-choice.json. No agent source/DB/business,
SSH/key/server, Git/Notion/dashboard mutation or real provider call occurred.

## NP source, endpoint and privacy boundary

Fresh seven-source owner intake accepted169261bytes;112enabled events/5known
relevant pairs/0unreviewed/0redacted, five routing and seven current order pins
match. Intake source-only Review OK is NOT this final implementation approval.
Acquired native NP/photo source bodies remain local/hash-only for external review.

New account/order.tracking is a GET-only JSON read. Native login.validate checks
customer login and customer_token before any model access. Native getOrder checks
the owner/current store/public visibility; only then is a TTN queried. The new
getTrackingNumber repeats publicOrderScope in its join and chooses native newest
date_time/ref tie deterministically. It returns only a validated14digit number.
Pinta's stored date_time comes from the submitted shipping date; it is NOT used
as an invented carrier event timestamp. No additional customer/order projection
is exposed. Response is private/no-store/Vary Cookie/nosniff, generic failures.

The new helper calls only the confirmed fixed HTTPS TrackingDocument/
getStatusDocuments endpoint. Existing configured API key, when present, stays
server-side. Public official tracking also supplies this method without a key;
no new key provisioning, phone/name payload or admin model invocation is added.
Fixed host, no redirects, HTTPS-only, TLS verification,2second connect/5second
total timeout and64KiB response cap. Only one successful data row with bounded
UTF8 Status is accepted; an optional Number echo must match. Raw errors/other
fields are discarded. No shipping document/order/status or other DB write.

Read starts only when the user expands history. New standalone same-origin JS
uses textContent, fixed carrier link origin/path, one first-open request and
manual retry, no polling. Its one setTimeout is an explicitly documented8second
client AbortController deadline allowing the5second server network budget plus
framework/transport overhead; finally clears it and releases the retry control.
No important/absolute/fixed or unexplained delay was introduced.

Existing canonical private DIR_CACHE is required outside the web root. No new
cache directory. Per-order/store/TTN hashed filenames,0600 cache files, nonblocking
flock. Validated Status/state/check time only; no raw response, TTN, key, phone or
recipient field is persisted. Success300seconds/failure60seconds, with an honest
up-to5minute UI hint. A locked/missing/unwritable/invalid cache yields unavailable;
the order page and official link remain usable where a TTN was read. Canonical
path/symlink/type guards are conventional trusted-private-directory checks,
not an adversarial same-user filesystem-race certification.

Official public tracking SPA was inspected directly on the primary domain.
Its movement route accepts reversed unpadded base64. The generated link is
https://tracking.novaposhta.ua/#/uk/movement/<encoded-number>, with noreferrer.
The static route proof records source URL/hash, small decoder and fictional
roundtrip. No real TTN was sent to that website or to an API by the agent.

## Guarded deployment and rollback

Runner: patches/RD-18-WP3_order-followup_20261027.php
Owner packet: diagnostics/RD-18-WP3_owner_20261027.ps1
Exact identities and all target before/after/absent states are in
work/rd18-followup-20261027/release-facts.json and targets.json, supplied together
in the ready review. Nine targets: seven existing plus new helper and JS.
Seventy-eight full source dependency hashes, including the fresh NP sources.

Full compiled payloads match all nine local afterfiles. H1 changes only the
existing DS token VALUE; CSS replaces its owning RD18 block in place, preserves
every outside byte including terminal CRLF, and retains D2 map byte-for-byte.
Pure event gate appends8protective tracking controller/model dot/pipe/slash edges
to the prior94, making102; exact five admitted hook pairs/multiset unchanged.
Unknown new-endpoint hooks are checked during host Verify, not silently admitted
from the earlier source collector's narrower predicate.

Runtime Check/Apply add only cURL availability, canonical writable private-cache
mapping and SHOW COLUMNS for four TTN table fields. No provider request, cache
file creation, API key export or business-row query in Verify. Existing native
source/theme/layout/readonly metadata gates and double preflight remain.

Same shared logical RD18 lock. New private stage filename and local owner receipt
directory, not the consumed WP2 route. Apply indices3,6,7,8,5,0,2,1,4: CSS/labels/
new helper+JS/model before controller/views, header last. Rollback1,2,0,5,6,3,8,7,4:
views/controller/model restored before removing assets, header last. Seven exact
original-file backups; indices7,8 have backup=none and are removed on rollback.
Future backup pattern:
/home2/boosters/public_html/_patch_backups/RD-18-WP3_order-followup_20261027-<UTC>-<12hex>/original-0..6.
Actual backup currently none. Derived image and private NP cache files are not
purged by rollback. Later target edits cause rollback STOP; never force it.

Owner NEW Upload -> Verify, send full receipt before separate Apply. Expected
Verify: PHP8.0.x/admission64hex/syntax_gate=ok/production_writes=0/backup=none/
done=ok/RD18_WP3_VERIFY_OK. Apply: nine exact rows/changed_files=9/self_deleted=yes/
done=ok/template_cache_cleanup=ok/staged_runner_absent=yes and
RD18_WP3_APPLY_OK_OWNER_QA_REQUIRED. One-use attempt markers, no replay/reset.
Cache cleanup is reviewed packet patch-success && template-cache cleanup.

## Local evidence and limits

All14checks bracket the exact runner+packet hashes before and after each job.
PHP8lint on runner/four PHP targets; new JS syntax.150event cases/25pair mutations/
13theme cases and exact-hook multiset. Actual nine-payload file engine:7cases,
7backups/2new files, no-op after later unrelated CSS/token, tamper/drift/lock/
admission refusal,8partial restores and automatic restoration after partial writes.
Runtime metadata is replaced in that fixture; it is not host execution proof.

Actual new helper/cache/controller and native login body:14carrier response
cases and11endpoint authority cases; cold/warm/expired/failure/locked/nonregular
cache, public-keyless shape, fixed transport options and64KiB cap. cURL is
namespace-intercepted before any call, model rows/config fictional. Native model
20history+5TTN queries execute on in-memory SQLite fixtures:3pages DESC with
tie/null/count/visibility/store/customer/notify protection and latest-TTN tie.
Not MySQL/server/API proof. Native current catalogue/image/Loader/GD10cases and
separate no-GD fixture preserve the accepted photo behavior.

Native PHP/Twig23contexts before/after retain EXACT original read trace,
products(including photo aliases)/totals/pager. Intended history sort is checked
on actual SQL separately. Real Chrome88order+20hub layouts at390/768/1280/1591,
long text/money,44px cart interaction, keyboard focus,5FormData pairs/4single fake
cart posts/1native history AJAX. Separate28carrier scenarios at the same widths,
no request on page load, first open/reopen/manual retry, absent TTN/unavailable/
malformed/HTTP failure/HTML injection/long status, fixed prefilled link/referrer,
actual8second browser abort and usable page. All traffic is loopback; external
browser network blocked. Executor viewed closed and NP-open desktop/mobile PNGs.
PS5.1/PS7 full actual packet AST/sixstep state/replay mocks replace both SSH/SCP
before invocation. No real provider/analytics/cart/customer mutation.

First final smoke stopped at a fixture's programmatic mouse-focus assumption;
the focused test now establishes keyboard modality with Tab before asserting
focus-visible. Production code did not change; full final14check pass follows.
Synthetic invalid-date history rows exercise truthful fallback, not a real
carrier chronology. Bare Twig ArrayLoader lacks production wrapper/cache. Text
reviewer is given code/evidence, not screenshots or native acquired source bodies.

After actual ready Review OK and owner Apply, owner QA must check all nine
requested behaviors on desktop/tablet/mobile, live TTN/status/link and carrier
failure, real stored totals/payment/billing/recipient, cart/history interactions,
cancelled/returned disabled return and visible keyboard focus. Until then no
production integration or final visual acceptance is claimed.

## Actual ready review and mechanical release

Actual subscription Claude Review OK, no mandatory code/composition findings,
request14467fd5-b388-44e6-a140-2d2a04007025. Both exact runner/owner packet hashes
bound. Fourteen result/fixture hashes and all148frozen inputs rechecked unchanged
before this bookkeeping. No code/command/payload/test byte changed after review.
Reviewed report snapshot preserved separately; original text-only verdict remains
immutable. No screenshot, host/API or owner visual approval is inferred.

Optional observations recorded, no new implementation round:
- Current bs_status_message is computed but not displayed as an additional
  standalone current-state explanation. Expanded history shows the generic
  explanation unless replaced by a notified comment, preserving that priority.
  Owner QA can request the extra current-state message if desired.
- NP is intentionally loaded only when history expands; owner confirms placement
  during QA. Test actual configured canonical host, real TTN and movement link.
- NP latest-TTN sorting assumes the DateTime type in the hash-bound native
  installation source. Verify checks columns, not types; test a multi-TTN order
  if one exists. No new DB/schema mutation or assumed scan timestamp.
- Live Language/keyless/not-found behavior and carrier availability are not
  proven by mocks. Missing key doesn't cause new credential provisioning; all
  unsupported/failure paths are optional unavailable text with link when known.
- Alternate-origin config_url mismatch leaves carrier region hidden; QA uses
  canonical host. Disabled return reason is tooltip/ARIA, not a new visible note.
- Official SPA hash-route may change upstream. Derived private status-cache files
  are retained outside cache.* cleanup and on rollback; no housekeeping added.
- Minor unused fields/docblock/whitespace are cosmetic; no byte-changing repair.

Owner self-checks exact packet SHA256 before first invocation. NEW Upload then
Verify only are released now. Complete expected no-write Verify receipt is
assessed before a separate Apply. Any STOP preserves state/receipts without
replay/reset/repin. Final live owner QA on all nine requirements remains required.

## Owner Verify receipt accepted

Owner Upload/Verify2026-10-28 receipt matched the exact reviewed runner/packet. PHP8.0.30, syntax_gate=ok, production_writes=0, backup=none, done=ok, admission 9e30ff3c4ef48d4ef77cc5af2a2d1b9422b1c3f3fb07b187528cdcd7db736fbd. Native local one-use state uploaded/verified=true and applyAttempted absent. Accepted mechanically under the reviewed expected-result contract. Host schema/cURL/cache/source/hook admission passed; actual NP API, production render and owner QA remain untested. Next owner action: exact WP3packet -Step Apply ONCE, then send complete output. No Upload/Verify replay or state reset. Evidence diagnostics/RD-18_WP3_review_evidence_20261028/owner-Verify-accepted_20261028.json.

## Owner Apply accepted; seven additional findings

All9target hashes matched; seven backups plus2newfiles,48template cache entries removed, selfdelete/stagedabsence/cleanup successful. Backup /home2/boosters/public_html/_patch_backups/RD-18-WP3_order-followup_20261027-20261028T065858Z-ab4ed879e027. Exact reviewed pair/admission matched. Apply consumed; no replay/reset. Owner screenshots show the NP status region in use and request seven further changes (invoice placeholder, future bank-transfer wording, compact snapshot delivery details/logo, header tracking button, always-visible compact NP region, support linebreaks/phone). Final owner QA acceptance remains open. No bank/account details or raw owner screenshots are transferred for external review. Evidence diagnostics/RD-18_WP3_review_evidence_20261028/owner-Apply-accepted_20261028.json.
