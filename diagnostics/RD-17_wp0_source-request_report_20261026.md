# RD-17 WP0 - address redesign source request

Date: 2026-10-26 (Europe/Kyiv)
Executor: Codex, directly assigned by the owner.
Project/root: Booster Shop, `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`.
Gate: WP1A_R3_OWNER_APPLY_RECEIPT_ACCEPTED_OWNER_UI_QA_PENDING; RD17 incomplete.
Current continuation: `diagnostics/RD-17_WP1A_profile-receiver_report_20261026.md`.
The owner-approved functional amendment below supersedes the original
presentation-only scope for the default action and profile-name bridge only.

## Authority and sequence

RD-17 only. Finish its deployment and owner QA before any RD-18 execution.
RD-18's handoff was read for sequencing only; D2 variant 1 is already decided.
One writer for DS CSS, header token and shell. Owner H1 on 2026-10-26 permits
each RD-17/RD-18 WP to replace only the existing DS reference token value;
no other header byte is authorized. Do not ask again per WP.
Presentation only; ACC-002 NP behavior, ACC-008 guard/writers and ACC-009 name
rules remain protected. No DB writes, agent SSH/key/server/deployment, Git or
Notion/dashboard changes. Existing unrelated working-tree changes are preserved.

## Exact owner-run collection set

All paths below are relative to `~/public_html`. Exactly 27 regular files;
no directory export or glob. The three address views are all required.

| Purpose | Exact paths |
|---|---|
| Page, AJAX list, form, save/delete ownership | `catalog/controller/account/address.php` |
| Wrapper, list fragment, form and inline NP picker | `catalog/view/template/account/address.twig`, `catalog/view/template/account/address_list.twig`, `catalog/view/template/account/address_form.twig` |
| Address labels/errors | `extension/ukrainian/catalog/language/uk-ua/account/address.php` |
| Current shell contract and labels | `catalog/controller/account/shell.php`, `catalog/view/template/account/shell.twig`, `extension/ukrainian/catalog/language/uk-ua/account/account.php` |
| Shared styles and token | `catalog/view/stylesheet/boostershop-ds.css`, `catalog/view/template/common/header.twig` |
| Name binding and protected validators | `catalog/view/javascript/booster-account-universal.js`, `catalog/view/javascript/booster-account-profile.js`, `system/library/booster_account_profile.php` |
| Preservation guard and read/write context (read-only sources) | `system/library/booster_custom_field_guard.php`, `catalog/model/account/address.php`, `catalog/model/account/customer.php`, `catalog/model/account/custom_field.php`, `system/library/cart/customer.php` |
| AJAX notice ownership and cascading styles | `catalog/view/javascript/common.js`, `catalog/view/stylesheet/stylesheet.css`, `catalog/view/stylesheet/bootstrap.css` |
| Actual template/override loading | `system/library/template/twig.php`, `system/engine/loader.php`, `catalog/controller/event/theme.php`, `catalog/model/design/theme.php` |
| Script/style injection context | `catalog/controller/common/header.php`, `catalog/view/template/common/footer.twig` |

No config, SSH/key, .env, log, session, upload, download, DB dump or customer
record is exported. Owner-run PHP loads config locally without exposing it.
The fixed prepared SELECTs inspect theme counts for address/list/form/shell/
header/footer across all stores, plus bounded address layout candidates/module
placements. Schema is checked before queries, DB_PORT is cast to int, and
bind_result is used inside a READ ONLY transaction. No raw theme code or business
rows are returned. Any enabled override blocks template planning until resolved.
Event/OCMOD admission before application is a later requirement; zero theme
counts alone do not establish absence of all runtime customization.

## Evidence already inspected and its limits

The newest root backup by filename is
`backup-10.15.2026_13-09-55_boosters.tar.gz`, predating the protected releases.
Only the three named views and address controller were extracted locally, to
`work/rd17-wp0-20261026/historical/`. No DB dump was opened.
The historical controller renders `account/address` from index(), returns
`account/address_list` from getList(), and renders `account/address_form` from
form(). Both address.twig and address_list.twig exist: the latter is a fragment,
not an alternative filename. Backup-suffixed copies are excluded.
Historical form NP JavaScript and CSS are inline in address_form.twig, with
Pinta directory endpoint references; no independent NP asset is established.
The fresh form/controller/header/footer will determine whether another exact
dependency is required. Never invent an NP asset filename or pull a directory.

RD-16's accepted post-release shell already accepts active='address' and
mode='page' and renders a child view explicitly. It does not require a Twig
include or a global account-menu change. Recheck fresh identities before reuse.
The CSS prefix remains `.bs-account-page`.
All four address board hashes match README; markup/spec was read. Their names,
addresses and form hints are mock content. NP functionality and ACC-009 rules
take precedence over the simplified boards.

## Fresh-source drift - NOT YET MEASURED

No current owner capture exists for RD-17. No claim of matching production.
After intake, compare each source's exact bytes against the latest applicable
accepted artifact, treating an expected later release as lineage, not a defect:

- RD-16 WP2 DS: `e1d33339b3056de105b430c0a7e5153a4ae3e4be32922f45126118f3b4a2696f`.
- RD-16 WP2 header: `66b626a4b99fc6e563fb68754dc49ec3eedce7a509349b43bd5c3bcb2f1d5a51`;
  token `rd16-wp2-20261026`. Separately compare normalized non-token bytes.
- RD-16 WP1 shell controller: `b39077e4b62f540a4ec47fc02021447ea961fbb8b2423d4027688f45c14aa3e1`;
  shell view: `f61496ef34e56f813a17ace4795962ff450ed71ec88f1413a62a0e486f85ca16`.
- ACC-009 N2 address controller: `462560a1f0d9c2c2c59523a5cab5de320620716a1e0985dc2cbb36986b21dff2`;
  form: `a5d2ad5d4d08406994c00583c722c825c45000c05097c7963d44231ba03ed39f`;
  universal JS: `2d9ca8f133bb5e8d9f7c938a8e92d3db86d01edcedb15261525a5353a08ac368`.

Use `diagnostics/ACC-009_closeout_evidence_20261023/release-artifacts.json`,
RD-16 accepted WP1/WP2 receipts and ACC-008 r11/r16 records. N2 legitimately
supersedes earlier address source hashes; verify preservation of protected
blocks rather than demanding the obsolete r16 whole-file hash.

## Four G0 decisions - all pending fresh source

| Item | Evidence needed | Bounded recommendation / constraint |
|---|---|---|
| 1. Form flow | Actual form route, save action, NP initialization, cancel behavior | Provisionally prefer (a), separate form page: least interaction risk, but differs from the board's inline collapse. Owner chooses after fresh evidence. (b) requires proving one writer/endpoint/widget initialization. |
| 2. Set default | Existing independent card action, or only current form choice | Reuse a live action unchanged if present; otherwise retain default only inside form and record the card-action gap. No new writer. |
| 3. Patronymic / prefill | Actual stored recipient field, rendering, current prefill path | Restyle existing fields only. No new stored field, inferred value, prefill writer or misleading prefill hint. |
| 4. Field order | Existing NP dependency/reset chain, country/zone hidden controls | Prefer target order only where dependencies permit. Preserve functional area-before-city or hidden controls if required; explain the deviation. |

ACC-008 r16 consumes the existing success message in form() and sets it only
after successful commit. Review fresh sources for interrupted-redirect behavior;
if presentation cannot prevent stale replay, report the limitation instead of
changing session/writer logic. No G0 answer is inferred from old source.

## Owner packet and stop conditions

Collector: `diagnostics/RD-17_wp0_collect_20261026.sh`.
PowerShell: `diagnostics/RD-17_wp0_owner-pull_20261026.ps1`.
Task-private exports: `~/private/booster-pulls/RD-17-source-20261026-r1.tar.gz`
and `RD-17-source-20261026-r1.manifest.txt` in that same directory.
Local drop: `work/rd17-wp0-20261026/owner-drop-r1/`.
All these export/drop names must be absent before the first run; no replay.
UTC times, all source hashes, archive hash and metadata enter the manifest.
Only the two exact remote private export files are deleted, after both local
downloads match their remote hashes. Live source, cache and DB are untouched.
Production backup/rollback: none, because production writes are zero.

Expected remote stdout: task=RD-17-WP0; source_files=27; archive_sha256=<64 hex>;
manifest_sha256=<64 hex>; done=ok, as five lines. Owner packet then prints
`RD-17_SOURCE_READY` and the local path. Anything else: stop, preserve evidence,
send the error; do not recollect or clean manually.

The executor prepared this derived packet under the canonical integrated-review
policy, which supersedes the older routine separate command-drafting round.
Independent subscription Claude review is required before owner use. Review
does not authorize WP1 or substitute for G0, deployment or owner QA.

Local PHP lint is on 8.3.30; hosting 8.0/MySQL/schema execution remains unproven.
Initial Git Bash launch hit its known sandbox MSYS initialization denial; the
same bounded synthetic fixture was launched under approved normal-user rights.
PowerShell AST parse passed. Record final fixture/review identities below.

Next: reviewed owner capture, strict archive/manifest/member/privacy/hash intake,
fresh drift and four G0 findings, then STOP for owner G0 decision. No WP1 before
that decision; no RD-18 until RD-17 deployment and owner QA are accepted.


## Integrated ready review accepted - 2026-10-26

Gate now: OWNER_SOURCE_CAPTURE_PENDING; G0 remains unpassed.
Actual subscription Claude Opus 5.5, high, request `a787f5b4-d2dc-439a-bac7-6fee7ea1f99f`,
returned **Code/Composition Review OK**, mandatory findings: none.
Collector SHA-256: `6559ddced3910e3f922a09744ea5a01d84806bdd153463b82c4fbafae28d7e9b`.
Owner packet SHA-256: `7b799c408aceab7a3de1b08d709a20c032b58798ddf49acca6c33014bbc05a5a`.
Exact review packet: 158045 bytes, SHA-256
`c9532fe5cd3117325f2b86852d5751d166f0d6b012adcc7864a32e7b66af07aa`.
Raw response/run and mechanical closure are preserved under
`diagnostics/RD-17_wp0_review_evidence_20261026/`.
All declared inputs were unchanged at review completion and checked again
before this nonsemantic bookkeeping append. Reviewed executable bytes remain
unchanged. No production access or owner run has occurred.

All four synthetic collector cases passed: successful 27-member archive;
missing source refused; source drift refused; existing export preserved.
PHP 8.3.30 lint and PowerShell AST passed. Metadata PHP/DB and native owner
SSH/SCP have not run here. Raw fixtures/local checks remain under the task work
folder. That folder now has its own narrow .gitignore; root owner .gitignore
was not changed. Only sanitized evidence should be deliberately published.

Optional O1-O7 are recorded in the closure without expanding scope. In
particular, confirm fresh theme route storage before interpreting count=0;
any early owner failure consumes r1 and requires targeted assessment, never
blind replay; inherited native transport may emit a harmless first-line BOM
on some Framework console configurations. PowerShell runtime/SSH and real
PHP8.0/MySQL remain unproven boundaries. Original optional formatting and
review adapter stay unchanged; the adapter is outside the owner's verdict.
No further review round is required for these disclosed optional notes or
this expected identity/receipt bookkeeping.

Owner: run the exact reviewed packet once in PowerShell:

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-17_wp0_owner-pull_20261026.ps1'
```

Expect `RD-17_SOURCE_READY`. Send that output or the exact failure. Keep the
created local files in place; the executor will validate them locally, report
fresh drift and the four G0 choices, then STOP. Do not deploy a patch.

## Owner source capture accepted; G0 findings - 2026-10-26

Owner supplied the expected success output in this chat. The complete saved
five-line receipt, both local download hashes, manifest structure, exact ordered
27-member archive, regular-file types, path safety, sizes and every source hash
were checked locally. Collection: 2026-10-26T10:51:48Z to 10:51:49Z.
Total source size: 783262 bytes. No secret signatures found in a complete bounded
source scan (not a universal absence guarantee). No customer records were read.
Archive SHA-256: `d28b79cfe1439a761751c9159a59c9092a5f7ea7ce173be8844d5d923bb30282`.
Manifest SHA-256: `0d1ea61028b35628a7dd646c5111a56404094304af46c422e4ad505fd8fbd999`.
Owner receipt reports zero production writes and exact private export cleanup.
The agent did not invoke SSH/key/server tools. This accepts source capture,
not implementation, deployment or authenticated UI QA.

Evidence:
- `diagnostics/RD-17_wp0_review_evidence_20261026/owner-intake-r1.json`.
- `diagnostics/RD-17_wp0_review_evidence_20261026/g0-source-facts.json`.
- Private sources: `work/rd17-wp0-20261026/current-r1/` (task-locally ignored).

### Drift and protected lineage

All 24 sources with a latest applicable RD-16 WP2 dependency/after or ACC-009
release identity match exactly. In particular the DS CSS, header including
`rd16-wp2-20261026`, shell pair, address controller/form, universal/profile JS,
profile library and guard have no unexplained drift.
The remaining three sources (address wrapper, list fragment, address language)
match the 2026-10-15 backup bytes. That is explicitly a historical comparison;
no separate newer accepted release baseline was found for those three files.

ACC-008 r16 generated controller hash equals the ACC-009 N2 admitted before hash;
fresh controller equals its accepted after hash. NP helper functions, the r16
success/redirect block, inline NP/country scripts, and preservation guard compare
byte-for-byte with the applicable ACC-008 sources. N2 is the expected later
name-policy release, not drift to undo. No source repair is indicated by intake.

### Theme, shell and notice ownership

Owner PHP is 8.0.30. Six exact routes have zero total and zero enabled theme rows
across all stores: address wrapper/list/form, shell, header and footer. The fresh
theme model queries the same exact route string; this closes review optional O1's
route-shape check for these counts. This is not event/OCMOD certification.
Both address page routes match store 0 / layout 6 / account/%. Candidate module
is `ciaccountsidebar.account_sidebar` in column_left. The current address callers
invoke column_left; suppress it at those callers, not globally. The fresh shell
already supports active='address', mode='page', so no shell-file edit is needed
on current evidence. Host template cache is
`/home2/boosters/ocartdata/storage/cache/template/`, confirmed by the loader's
DIR_CACHE + template/ convention. No cache was changed.

Presentation causes: `address.twig:11` renders the legacy column-left layout;
`address_list.twig:2-12` renders the table/action icons; `address_form.twig:13-270`
owns the existing inline address styles and `:272` renders column-left.
Existing form warnings use the generic common.js AJAX handler (`:179-184`),
which calls the cart-styled bsToast (`:72` onward); delete callbacks have local
alert markup in address.twig. WP1 must handle account notices locally and keep
shared common.js/NP scripts unchanged. Source-rule cleanup in the address form
is preferable to stacking unscoped overrides; no code is authored at G0.
The inspected local delete handler directly calls the existing delete endpoint;
it contains no confirmation dialog. Do not claim an existing dialog was verified
or silently change delete semantics. Runtime event admission remains required
before application.

### G0.1 - form flow (owner choice required)

Confirmed separate pages: index() renders `account/address`; getList() returns
`account/address_list`; form() renders `account/address_form`. Add/edit links
use `account/address.form`, with address_id for edit; the sole form posts to
`account/address.save`. Add redirects to the list (or the existing required-after-
registration/checkout flow); successful edit redirects to the same form.
One `#form-address` / `#bs-np-account-picker` initializes once on its form page.

Recommendation: **(a) retain the separate form route**. Restyle list and form
with the shell and light-A components; Add/Edit navigate to the form, Cancel
returns to the list. Trade-off: the board's inline collapse is not implemented.
Option **(b) inline collapsed form** follows the board but requires a larger
presentation composition/focus/initialization change. It is not categorically
impossible within scope; it must preserve one save endpoint/writer and one NP
initialization, plus existing redirects. It needs explicit owner selection and
proof in WP1, not an unreviewed shortcut here.

### G0.2 - default address

Public controller methods are index/list/npMetadata/form/save/delete. No
independent set-default action exists in this source path. Current list has
only edit/delete links. Form `:478-496` owns the default yes/no controls; save
validates and passes them through the existing guarded writer. First address
defaults automatically; demoting the current main address alone is rejected.

Recommendation: keep changing the default **inside the existing form only**.
Do not add a direct card button that writes or a second save path. Record the
board's direct-action gap as follow-up, as required by the handoff.

### G0.3 - patronymic and profile prefill

form() `:452-462` prefills firstname and lastname from customer profile only for
a new/unresolved address; edit uses that address's own values. There is no
dedicated recipient middlename/patronymic field or profile-patronymic prefill in
the address controller/template. ACC-009 catalogHidden hides the registered
account-profile patronymic/Telegram/Viber fields from generic custom-field
readers. The address template still has a generic address custom-field loop.
Its arbitrary DB-defined labels were not exported; preserve that loop without
claiming every possible DB-defined custom field is absent.

Recommendation: retain existing first/last-name prefill and any existing generic
address fields; add no recipient patronymic, storage or prefill writer, and no
board hint implying patronymic was copied. The dedicated board field is a
follow-up. Do not infer it from a profile name, company, SKU or free text.

### G0.4 - actual NP field order

Current visible order: firstname, lastname, NP area, city, delivery type,
warehouse/postomat or street, courier house/flat, generic address custom fields,
default. Country is a hidden fixed Ukraine value (220), zone is hidden; city/
address/postcode stubs are hidden too. NP updateAvailability requires area_ref
before city and city_ref before point; changing area clears city/point and
changing city/type clears the point and courier details. These are dependencies,
not decorative field choices.

Recommendation: keep this functional order and all hidden controls intact.
Use light-A layout/labels/spacing without moving area below city, adding a
second region input, converting type to a separate courier writer, or exposing
country/zone as new editable controls. This is the declared board deviation.

### ACC-008 interrupted-redirect limitation

Both index() and form() consume session.success once on request. Successful
save sets it after the existing commit path. If the browser never performs the
redirect, the next relevant request can still consume that stored message.
Changing only the banner's markup/style cannot invalidate a server-side flash
before that page is requested; hiding the DOM would not solve the cause.
Recommendation: implement accessible account-styled notices in WP1, preserve
one-use behavior, and record interrupted-redirect stale replay as not fully
achievable in this presentation scope. No session/writer/guard change is made.

### Stop at G0

Await the owner's choice for item 1 and acceptance of bounded items 2-4 above.
No implementation patch, target edit, status/Git write or new model review was
performed. This is deterministic expected intake plus source diagnosis under
the accepted capture contract, not a separate actual-result review exception.
After G0 approval, prepare one coherent WP1 code/runner/owner-packet/test/rollback
package for integrated independent review. RD-18 remains unstarted until RD-17
deployment and owner QA are accepted. H1 remains granted, no repeat request.

## Owner G0 response and expanded outcome - 2026-10-26

The owner's direct reply chose 1A (separate form) and accepted item 4 (NP order).
For item 2 the owner requests a direct default-address button, superseding the
form-only recommendation. For item 3 the owner accepts no dedicated patronymic
display on account addresses, but reports saved profile patronymic missing from
checkout and explicitly requests a complete current-profile full-name integration
across relevant interactions. Do not interpret that reply as accepting the old
recommendation to leave the checkout profile bridge absent.

Recorded scope: `plans/RD-17_scope-amendment_20261026.md`. Existing handoff remains
history and governs unchanged areas; the latest owner direction grants necessary
bounded functional preparation. No repeated permission request for the same
default-action/checkout-name scope is needed. No deployment, agent server/key,
Notion/Git, direct DB mutation or historical-order rewrite is granted.

Preparation now needs fresh checkout/NP sources outside the initial 27-file
address-only capture. Source-supplement package and exact list:
`diagnostics/RD-17_checkout-source-request_report_20261026.md`.
The initial capture stays accepted/consumed and must not be replayed. Its file
identities are rebound by hash only in the new capture. RD18 remains unstarted.
Do not begin a visual-only patch that would ignore the owner's expanded request.
