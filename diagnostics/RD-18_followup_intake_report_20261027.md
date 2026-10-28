# RD-18 follow-up: nine owner QA findings and NP source intake

Date: 2026-10-27. Executor: owner-assigned Codex.
Status: UI_LOCAL_DRAFT_CHECKED; NP_SOURCE_ACCEPTED; OWNER_TRACKING_SCOPE_CHOICE_PENDING; NOT_DEPLOYED.

The owner rejected WP2 final QA and supplied nine explicit corrections. WP2
Apply itself remains accepted; its one-use route is consumed and must not replay.
No RD18 final acceptance or canonical status/Git/Notion/dashboard write occurred.

## Recorded owner decisions and causes

1. WP2 orders.css73 forces max-width1200 and padding24; other sections inherit
   Bootstrap .container. Remove this owning rule and its mobile padding override,
   rather than adding another width override. Local list/detail x,width,padding
   now match at390/768/1280/1591.
2. Owner explicitly replaces the previous ACC007 ASC-history requirement with
   newest-first. The local model changes only the two history ORDER BY directions
   to dateDESC,idDESC, retaining ownership/store/visibility/notify/counts/page10.
   One native details disclosure is closed by default; current status/date remain
   visible. No invented current-history identity or future event.
3. NP parcel information requested when TTN exists. Old23Oct Pinta admin source
   has TrackingDocument.getStatusDocuments and an order-to-TTN model; current
   bodies and full-history availability are unverified. No API/history contract
   guessed, key read, tracking request or parcel/customer row fetched by agent.
4. The small SVG combines a plus/cart stroke and .01 wheel segments. Local icon
   uses a plain cart outline and two explicit circles; native forms/handlers stay.
5. Owner chose support contact for the whole order, explicitly no new return
   request mechanism. Local header has one support-return action; per-item return
   links are removed from markup. No bot start/prefill protocol invented.
6. Payment is a details/summary in WP2. Local candidate changes it to plain
   paragraphs, with invoice still visible; billing remains its separate disclosure.
7. Current verified ACC007 IDs12 and24 mean Returned and Cancelled. Only these
   states disable the new order-wide support-return button; it has no href.
   The presentation map and order status writers are unchanged.
8. Actual order comments and notified-history comments gain existing gold-soft
   note styling. Generic status explanations are not highlighted as real comments.
   Notify filtering, escaping and line breaks remain unchanged.
9. Shared main gap24 plus a44px link creates the excessive heading interval.
   Back link/title become one local flex group with4px internal gap; touch target
   remains44px and the shared shell rule stays unchanged.

## Bounded local draft evidence

work/rd18-followup-20261027 contains the unreviewed local UI draft, no production
runner or released UI apply packet. Native PHP/Twig render23contexts before/after.
Actual Chrome:88order layouts plus20hub layouts at four widths, matching list/
detail container bounds, long text/money, closed/touch/keyboard history disclosure,
plain payment, highlighted comments,2disabled return states, focus rings,5exact
FormData pairs,4single fake native cart posts and1history AJAX page2. Synthetic
loopback only. Initial hidden-history innerText assertion corrected to textContent;
actual default closed/open behavior remains asserted. Executor inspected1591/390
screenshots. All data/images are fictional; production header/cache not emulated.

Actual native model SQL captured from20calls and executed on an in-memory SQLite
fixture:3pages newest-first, tied timestamp IDs descending, NULL dates last,
no duplicate/lost rows, hidden-state counts/customer/store/status0 boundaries and
unnotified comment suppression preserved. This is not host MySQL evidence.
Earlier concurrent render fixture raced on a shared synthetic image directory;
the affected after-run was rerun sequentially and passed. No production mutation.

## NP source-only release

Collector: diagnostics/RD-18-WP3_np-source_collect_20261027.sh
SHA256 d52a1b416ec9eedf8624ef1b241d9ac487193bbe6fc3271470bbb56f436c053f
Owner packet: diagnostics/RD-18-WP3_np-source_owner_20261027.ps1
SHA256 108578c6ea554b588b8964a3c3293ea99589fd0c96003507e2e49f38721dff3f

Exact seven source names are listed in work/rd18-followup-20261027/source-facts.json.
Scope: Pinta admin/catalog controllers, internet-document model, catalogue shipping
model and two library sources. Additional hashes only: five deployed WP2 targets,
history model/map and five routing core files. Bounded readonly event metadata;
no config body, customer/catalogue/order rows, logs, callback or NP API call.
Creates only two private export artifacts; owner downloads and verifies hashes,
then removes those exact exports. No production source/DB/cache write.

Actual subscription Claude source-only Review OK, no mandatory findings:
request293c54b8-af31-4aed-9a4a-1b3199fba4dc. Review input51823bytes,
SHA256 2f4969ee6a635c5899b3e6fafbb7451ab1953b5c30ca669a97955f74502d239f.
Only newly authored collector/commands/synthetic fixtures were transmitted.
No acquired NP bodies or owner screenshots/customer details were transmitted.
No tools/MCP/hooks, API-key billing or paid overage used. Source-only verdict does
not approve the UI/history change, tracking implementation, deployment or QA.

Local collector checks: PHP8lint/extracted metadata on fake mysqli and12technical
files; Bash/tar seven synthetic files with fakePHP, five pass cases including
missing/drift/replay/literal-key STOP; Unix symlink path honestly NOT_TESTED
because Windows MSYS ln copied instead of creating a link. PS5.1/PS7 full packet
AST/state/download/hash/cleanup mocks replace both SSH/SCP before invocation.
No real config/DB/native SSH/Unix/API proof. Key screen is keyed-form only, not
comprehensive; prepared intake checks broader quoted32hex before writing files.

## Next gate

Owner runs source-only packet once, without a Step parameter. Expected
RD18_NP_SOURCE_READY; send complete output. Existing exports/drop, missing source,
drift, unknown/redacted hooks or cleanup ambiguity are held without replay/reset.
Prepared local intake verifies exact archive inventory/types/hashes/privacy/core
and deployed-source pins before source consumption. Then inspect actual NP paths,
resolve supported tracking/history data, complete one coherent guarded follow-up
patch/owner packet and integrated ready review, followed by owner deploy and QA.
No new return writer, DB/status writer or speculative shipment events authorized.

## Fresh NP source intake and inspected call path

Owner completed the exact source-only collector. Mechanical intake accepted7files,
169261bytes,112enabled events/5known relevant pairs/zero unreviewed or redacted.
All5routing-core pins and7current order-file pins match. Archive inventory,
entry types, download/source hashes and privacy screens passed before local writes.
The private export cleanup receipt is complete. Collector is consumed; no replay.
Evidence: diagnostics/RD-18_WP3_np-source_review_evidence_20261027/owner-np-source-intake-r1.json.

Current Pinta admin controller shipping/pinta_nova_poshta.php303-326 calls fixed
TrackingDocument/getStatusDocuments and uses data[0].Status. The current seven
files have no full tracking-history read or persisted sequence of carrier scans.
This does not prove that a full-history API is unavailable; it is not verified.
Primary developer documentation was inaccessible through the web tool. Conflicting
third-party descriptions were not accepted as a production endpoint contract.

The TTN getter uses pinta_nova_poshta_internet_document with order_id and orders
by date_timeDESC. Its date_time is populated from the submitted shipping_date
(InternetDocument.DateTime), not a proven carrier scan timestamp. Do not present
it as the time the carrier accepted a parcel or as an inferred movement event.
Schema installation code is source evidence only; live schema needs admission
before any new runtime query.

The Pinta API class constructs a separate DB connection, performs a broad settings
read and uses30second cURL timeout with left-union defaults preventing overrides.
The admin status request has no explicit timeout. Neither is suitable to copy
unchanged into a customer-page blocking path. Any new read-only tracking path
must retain native authenticated ownership/store/visibility checks, keep the key
on the server, bound network waits/responses, handle unavailable carrier data,
and avoid order/status/NP-document writes. Existing snapshot fields stay native.

Pending owner product choice (asked once via asynchronous question): current
carrier state within the status block plus official full-tracking link, or full
carrier route inside the cabinet, requiring a separate owner-run API contract
check on an existing TTN. Do not silently substitute the former for the latter.
Eight independent UI/history fixes remain locally checked, no ready implementation
verdict or deploy packet released until this affects the final coherent scope.
