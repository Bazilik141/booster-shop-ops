# RD-16 - account shell, hub and personal-data delivery

Date: 2026-10-25 (Europe/Kyiv)
Executor: Codex, assigned directly by the owner in this chat.
Project/root: Booster Shop, `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`.
Current gate: RD16_DONE_NOTION_AND_DASHBOARD_SYNCHRONIZED. Owner explicitly reassigned Notion/dashboard closeout and scoped commit/push to Codex on 2026-10-26. Canonical RD-16 Notion status Done was read back; dashboard RD-16 and RD-ACCOUNT subtask 1 match. Reviewed deployed artifacts and both owner QA acceptances are unchanged. Git publication uses an isolated commit from remote master; see final publication evidence and Git history.

## Outcome and authority

Prepared a bounded owner-run collection of 34 exact source files plus read-only
technical metadata. No production patch, customer reads, DB writes, deployment,
cache changes, Git writes or roadmap/status writes. Existing unrelated tracked
and untracked changes were observed and preserved; no existing task file was edited.
The handoff explicitly requires fresh evidence and a stop at G0 before WP1.
WP2 additionally requires owner acceptance of deployed WP1.

The newest root backup is `backup-10.15.2026_13-09-55_boosters.tar.gz`.
It predates ACC-008 and ACC-009 deployment. The bounded historical source index
is `work/rd16-wp0-20261025/baseline-index.json`; it is discovery evidence only.
31 of the 34 requested files exist in that backup; the three new ACC-009 library/JS
files are absent as expected from the older date. No database dump was read.

## Exact dependency and collection set

The executable allowlist is the 34-entry `rd16_files` array in
`diagnostics/RD-16_wp0_collect_20261025.sh`; no discovery glob or directory export.
Every path below is relative to `~/public_html`.

| Purpose | Exact source paths |
|---|---|
| Hub and profile entry points and protected save context | `catalog/controller/account/account.php`, `catalog/controller/account/edit.php` |
| Existing ACC-007 status filtering and ACC-006 count consumer | `catalog/controller/account/order.php`, `catalog/controller/account/wishlist.php` |
| Verify existing logout route/token contract | `catalog/controller/account/logout.php` |
| Read-only existing data/model contracts; never edit these | `catalog/model/account/address.php`, `catalog/model/account/customer.php`, `catalog/model/account/custom_field.php`, `catalog/model/account/order.php`, `catalog/model/account/wishlist.php`, `system/library/cart/customer.php` |
| Existing status presentation and protected profile normalization/validation | `system/library/acc007_order_status.php`, `system/library/booster_account_profile.php` |
| Loader and DB theme override call path | `system/library/template/twig.php`, `system/engine/loader.php`, `catalog/controller/event/theme.php`, `catalog/model/design/theme.php` |
| Current menu/layout owner and markup | `catalog/model/design/layout.php`, `catalog/controller/common/column_left.php`, `catalog/view/template/common/column_left.twig`, `extension/opencart/catalog/controller/module/account.php`, `extension/opencart/catalog/view/template/module/account.twig` |
| Shared injection/order, count source and cache reference; read-only context | `catalog/controller/common/header.php`, `catalog/view/template/common/header.twig`, `catalog/view/template/common/footer.twig` |
| Intended presentation targets | `catalog/view/template/account/account.twig`, `catalog/view/template/account/edit.twig`, `catalog/view/stylesheet/boostershop-ds.css` |
| Existing ACC-009 bindings; read-only | `catalog/view/javascript/booster-account-profile.js`, `catalog/view/javascript/booster-account-universal.js` |
| Cascading styles that affect the new scoped block; read-only | `catalog/view/stylesheet/stylesheet.css`, `catalog/view/stylesheet/bootstrap.css` |
| Existing Ukrainian labels and errors | `extension/ukrainian/catalog/language/uk-ua/account/account.php`, `extension/ukrainian/catalog/language/uk-ua/account/edit.php` |

Historical public PHP/Twig literal-reference search found the DS asset reference
only in `catalog/view/template/common/header.twig` (line 76 in the backup).
Current references must still be checked after intake. The handoff both requires
cache busting and protects the shared header: if the reference remains only there,
G0 must settle permission for a token-only edit or an alternative before shipping.
No header change has been made or silently authorized.

The owner-run PHP block loads config locally on the server without exporting it.
It validates table columns before prepared SELECTs and a READ ONLY transaction.
Output is restricted to per-route theme counts for hub/profile/header/column-left/footer/account-module (all stores), bounded layout-route
candidates and module placement metadata, PHP version and a derived `DIR_CACHE/template/` path (to confirm against the fresh loader).
It never selects customer, order, session, payment or secret settings records.
Zero enabled theme rows across all stores establishes absence for the named route;
any nonzero count needs targeted resolution before Twig planning. Raw override
code is not exported. Default layout, selected store and active module settings
remain unproven when the route candidates do not determine the actual menu.
No assumption of active routing follows merely from installed module files.

## Preliminary shell recommendation - not implementation approval

Recommend (a): a dedicated account-shell child controller rendering its own view,
called explicitly only from RD-16 hub/profile as each WP is authorized. Future
account tasks opt in individually. This avoids duplicated navigation and avoids
relying on cross-template Twig includes. The historical Twig loader switches to
an isolated ArrayLoader for nonempty override code (`template/twig.php::render`),
so include support is not established. The trade-off is an explicit controller
call per adopting page and a new child controller/view to admit as absent targets.
Do not replace the shared account module globally or activate other account pages.
Final mechanism/impact report follows fresh-source and theme/menu checks at G0.

The historical hub controller already builds its logout URL with language and
customer_token; the historical logout controller itself does not validate a token.
Retain the actual current controller-generated link after intake, rather than
constructing a URL from this historical observation.

## Executable identities and owner contract

- Collector: `diagnostics/RD-16_wp0_collect_20261025.sh`
  SHA-256 `00f14db7e815f4dd0bb9dad5e7f05e7335af1474223a0f0a1536028bd0b421d6`.
- PowerShell packet: `diagnostics/RD-16_wp0_owner-pull_20261025.ps1`
  SHA-256 `0ff72f1729a7575e5eb9c6402a1c8f1e6d7e61005157efd68bbc660de3e37be4`.
- Remote files: `~/private/booster-pulls/RD-16-source-20261025-r1.tar.gz`
  and `~/private/booster-pulls/RD-16-source-20261025-r1.manifest.txt`.
- Local destination: `work/rd16-wp0-20261025/owner-drop-r1/`.
- Before: both remote export names and local drop directory absent.
- After: two downloaded, SHA-256 verified files and owner receipt; the two exact
  remote exports removed only after both downloads match their remote hashes.
- No production target changes, backup or production rollback: `none`.
  On any failure, preserve artifacts and report; do not replay or wildcard-clean.
- Existing SSH alias/config is reused with strict host checking, public-key-only
  noninteractive Windows-agent transport, no agent forwarding or key utilities.
  The owner alone launches this packet. AI has not invoked SSH/SCP/key tools.

The collector refuses symlink components, missing/empty sources, files over 2 MiB,
total over 8 MiB, obvious key signatures, PHP outside 8.0.x, schema differences,
source drift, archive/source mismatch and existing export names. The manifest
contains UTC start/finish, exact source hashes, archive hash and technical metadata.
Source screening is not proof that all embedded credentials are absent: after
download, intake must screen the complete selected source before model review.

Expected remote stdout (exact five lines; hashes computed at collection):

```text
task=RD-16-WP0
source_files=34
archive_sha256=<64 lowercase hex>
manifest_sha256=<64 lowercase hex>
done=ok
```

After verified downloads and exact private export cleanup, PowerShell prints
`RD-16_SOURCE_READY` and the local drop path. Other output/errors mean STOP and
targeted assessment; the script offers no automatic retry or production apply.

Owner launcher, after required review acceptance:

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16_wp0_owner-pull_20261025.ps1'
```

## Local checks and independent review

- Fixed-file list: 34 unique allowlisted paths, no config/key/log/dump/customer
  data export and no edit authority inferred from read-only dependencies.
- PowerShell AST parse: passed. Collector hash is pinned inside the packet.
- Bash syntax and focused fixture pass: success, missing file, mid-collection
  source change, existing-export preservation all passed. Real Bash/tar/SHA-256,
  synthetic source files and mocked PHP metadata boundary; no SSH/DB/server use.
  Harness: `work/rd16-wp0-20261025/check-collector.sh`; fixture logs retained there.
- Extracted metadata PHP: `php -l` passed under local PHP 8.3.30. This is syntax
  evidence, not PHP 8.0/MySQL hosting execution. No mysqlnd-only APIs are used.
- Git Bash needed an approved normal-user execution boundary because sandbox
  denied its MSYS process initialization. No repository or production access
  expansion followed. An initial fixture path error was fixed before testing;
  Git Bash's binary hash marker is accepted alongside GNU's text marker.
- Independent subscription Claude R1 returned Changes requested (request
  `450c89ae-8bbd-4e02-b64b-6cd0033d5567`): M1 exact native stdin transport,
  M2 cleanup EOL guards, M3 failed receipt preservation. All three were repaired
  together; R2 accepted the exact executable pair as Review OK (see closure below).

## Next bounded action and remaining gates

After independent review acceptance, owner runs the exact launcher once and
sends its result. Codex then validates archive/manifest identity, safe member
names/types/counts/sizes, source hashes, privacy and the ACC-009 release after-hashes
from `diagnostics/ACC-009_closeout_evidence_20261023/release-artifacts.json`.
Only relevant shared paths are compared; unrelated ACC-009/admin/checkout files
are not recollected for RD-16. Drift is reported, never silently accepted.

Then close WP0 with the actual shell mechanism, theme/menu facts and bounded
impact set and stop at G0. No WP1 implementation before that gate. WP1 requires
actual source rationale, override history, 390/768/1280, long content and keyboard
states, synthetic order/address/favorites edge cases, integrated code/packet
review, owner deploy and QA. WP2 remains separately gated by WP1 owner QA.

## R1 repair and affected verification

M1: replaced PowerShell native string pipelines with a controlled Process
StandardInput.BaseStream UTF-8 write and close, no appended terminator. Native
arguments are fixed and allowlisted. Broken stdin sends return a failure while
retaining returned stdout. M2: normalize both bodies to LF and reject remaining
CR before process launch; cleanup uses exact absolute paths after directory and
hash gates. M3: write collection/cleanup raw stdout before exit/regex checks.

Optional review notes also addressed: load `./config.php`, initialize the module
metadata array, check prepared-fetch errors, collect theme counts for the other
three owning menu/shared views, hash/decode the same single collector byte read,
and label the cache path as derived. Event/default-layout/active-module state
remains a declared G0 uncertainty; no new event or settings reads were added.

Local `check-transport.ps1` extracts the exact production helper, cleanup literal
and receipt regex from the packet AST without executing the owner packet. Real
PowerShell-to-Bash transmission passed with LF and CRLF copies: collector exit 0,
exact five-line receipt accepted, synthetic private cleanup exit 0 and both exact
export files absent. Nonzero output is retained and a bare CR is refused before
launch. Fixture: `work/rd16-wp0-20261025/transport-fixture-r4/`. PHP alone is mocked;
no SSH/SCP/DB/server access occurred. Earlier test-harness locator and Git Bash
hash-format failures are preserved as fixture r2/r3; no owner command was run.

Refreshed affected PHP lint passed under local PHP 8.3.30. Actual host/MySQL
execution remains owner-only and unproven until collection. The archive path is
confirmed ignored by `.gitignore` rule `*.tar.gz`; no staging or Git writes.

## Accepted R2 identity and current owner gate

Actual subscription Claude Opus/high R2 returned **Review OK**, request
`9a667f74-425f-454c-9915-2964ebb5d3f2`. It closed M1-M3 and explicitly adopted
the exact owner launcher above. The accepted collector/PowerShell SHA-256 values
are the two full executable identities in this report. Both executables remain
byte-identical to the reviewed pair. Tools/MCP/hooks were disabled; observed
model `claude-opus-5-5`, no tool calls, overage false, no API/server fallback.
The raw review packet is 81,156 bytes, SHA-256
`1e14b84216e32207f5c2521a969c9c519769dcfc43cd1fbd4c586a824f6d1d62`.
The response, complete provider result and immutable closure are under
`diagnostics/RD-16_wp0_review_evidence_20261025/`.

The local transport fixture was launched with `powershell.exe -NoProfile -File`,
not pwsh; that executable reports Windows PowerShell **5.1.26100.9711**. This
clarifies optional R2 note 1 without changing any executable. The mocked PHP and
absent real SSH/MySQL boundaries remain as documented. Other optional R2 notes
are nonblocking and do not establish host runtime.

The reviewed report snapshot SHA-256 was
`ec60c4d3a89b10a093c11a599bea888c71001abd656351aea1844b87bca86741`.
After acceptance, only this mechanical verdict/host-version bookkeeping and two
mojibake heading separators were corrected. The exact reviewed text is preserved
in the immutable R2 packet; the verdict does not claim to have reviewed these
subsequent documentation bytes. No executable or result contract changed.

**Released scope: owner-run WP0 read-only collection only, once.** No production
implementation/apply, Git/status write or G0/WP1 transition is released. On STOP,
send the result and preserve the local raw receipt; do not rerun. On
`RD-16_SOURCE_READY`, Codex verifies intake and reports G0 before implementation.

## Owner collection R1 - accepted mechanical receipt, 2026-10-25

Owner reported `RD-16_SOURCE_READY`. Local archive and manifest match the exact
hashes in the saved owner receipt. Collection time: `2026-10-25T07:54:50Z`.
Archive SHA-256: `82520fde09a5364124f25e566e227e406cad09dd1250da8d49c3c09e2f6f2b79`.
Manifest SHA-256: `3636e66e4ba8c80e21487e4fe05267b319286dae2982e2d26d4faa2fc9dda29a`.
34 unique regular members, 758,720 bytes; allowlist, paths, types, size caps and
all per-file SHA-256 values verified before explicit safe extraction to
`work/rd16-wp0-20261025/current-r1/`. No symlink/hardlink/traversal members.
No obvious secret signature in the selected source; this is not comprehensive
credential attestation. Remote private export cleanup has its expected receipt.
Production source/DB/cache writes: zero by the accepted collector contract.
Mechanical receipt check needs no separate result-model call.

All **7 shared ACC-009 after-hashes match**: profile library, profile JS, universal
JS, edit controller, edit Twig, account customer model and custom-field model.
No ACC-009 drift was found. Differences from the stale October 15 backup are
recorded as historical differences, not automatically classified as unknown drift.
Structured evidence: `diagnostics/RD-16_wp0_review_evidence_20261025/owner-intake-r1.json`.

PHP host version: `8.0.30`. Theme table rows are zero across all stores for hub,
edit, header, column-left, footer and standard account module. The fresh theme
model filters enabled rows, so there is no named DB override for those routes.
Fresh `template/twig.php:98` retains isolated ArrayLoader for injected code;
`DIR_CACHE . 'template/'` matches the collector-derived cache path.
Fresh header line 76 remains the single discovered DS reference, so the narrow
header token-only permission question remains for G0.

## Source gap found during actual menu verification

The DB layout metadata resolves both hub and edit to store 0, layout 6, route
`account/%`. Its sole assigned column-left module is
**`ciaccountsidebar.account_sidebar`**, not the standard `opencart.account`
module collected initially. The initial allowlist omitted the actual module
implementation. That omission is corrected transparently with a narrow named
supplement; do not recollect the 34 already accepted files or infer the custom
module's current bytes from old backup/test trees.

Historical module source identifies a controller-rendered custom view and a
`document->addStyle()` dependency. They explain exactly these three missing files:

| File | Purpose |
|---|---|
| `extension/ciaccountsidebar/catalog/controller/module/account_sidebar.php` | actual menu composition, controller URL construction and style injection |
| `extension/ciaccountsidebar/catalog/view/template/module/account_sidebar.twig` | actual desktop nav markup, inline styles and duplicate-menu interaction |
| `extension/ciaccountsidebar/catalog/view/stylesheet/accountdashboard/sidebar.css` | material styles injected by the menu controller |

These files are read-only context; there is no authority to replace the shared
module globally. WP1 will opt the hub into a dedicated shell only; profile opts
in under WP2, other account pages under their assigned later tasks. The shell
recommendation remains (a), a child controller plus its own rendered view; final
G0 decision/impact statement awaits the actual module supplement.

## Menu supplement - coherent owner-only collection package

New collector: `diagnostics/RD-16_wp0_menu-collect_20261025.sh`, SHA-256
`64d8ecfc5844f78661ab9a3fd5de26ac4219b0671d738cfc1f136804a64f9d39`.
New owner packet: `diagnostics/RD-16_wp0_owner-menu-pull_20261025.ps1`, SHA-256
`e194f34fb1b2d939faefeb23f2674c96d6c3cf9e7eb3c6e92ca044e49f334cbc`.
The original accepted collector/packet remain unchanged and consumed, no replay.

This successor reuses the accepted collection/transport/error/cleanup mechanisms,
exports only the three named public files, checks all 34 accepted context hashes
with symlink walks before/after collection, and adds the actual custom-menu view
to the existing bounded theme-count query. It never exports context sources
again, settings values, config, customer data or DB contents. It performs no
production source/DB/cache write. An active custom-menu theme override, source
drift or missing source remains a STOP/G0 assessment, never silently accepted.
Default layout, active module settings and event overrides are still not attested;
layout assignment alone is not proof of visible runtime output.

Remote before-state: both exact private export names absent. Outputs:
`~/private/booster-pulls/RD-16-menu-20261025-r1.tar.gz` and
`~/private/booster-pulls/RD-16-menu-20261025-r1.manifest.txt`. Local destination:
`work/rd16-wp0-20261025/owner-menu-drop-r1/` (must be absent initially).
Both downloads must match their remote receipt hashes before exact private
export cleanup. Raw success/failure stdout is preserved. Production backup and
rollback: none, because this collector changes no production target. No wildcard
or recursive cleanup, no retry on STOP.

Exact remote stdout is the accepted five-line shape with
`task=RD-16-WP0-MENU`, `source_files=3`, computed archive/manifest SHA-256 and
`done=ok`. PowerShell prints **`RD-16_MENU_SOURCE_READY`** after download, hash
verification and exact private export cleanup. The manifest additionally states
`context_sources=34` and `context_pre_post=ok`.

Local checks: PowerShell parse and local PHP 8.3.30 lint passed. Windows
PowerShell 5.1 fixture reused the exact helper/cleanup/receipt regex extracted
from the new packet AST. Real Bash/tar/SHA-256 passed LF and CRLF success, exact
three-file receipt, exact private cleanup and refusal after a changed pinned
context file (no archive created). Nonzero stdout and bare-CR refusal still pass.
34 fixture context files are verified production source copies; the three new
module files are synthetic, PHP metadata is mocked. No local SSH/SCP/DB/server
operation occurred. Fixture: `work/rd16-wp0-20261025/menu-transport-fixture-r1/`.
This does not prove the three missing live files or real module rendering.

Owner launcher, HELD until integrated independent supplement review accepts the
exact two new executable hashes:

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16_wp0_owner-menu-pull_20261025.ps1'
```

Current next action: finish integrated review of this narrow supplement, then
owner runs it once. After safe intake and G0 report, wait for the required owner
G0 decision before WP1. No WP1 markup/CSS/controller implementation has started.

## Menu supplement accepted for owner collection only

Actual subscription Claude Opus/high returned **Review OK**, request
`5e37bee8-c238-4d0a-81dc-1cfcb6a39c31`, for the exact new collector and owner
packet hashes listed above. No mandatory finding. It explicitly adopted the exact
new owner launcher for one run. All 19 declared dependencies were unchanged
through completed review; tools/MCP/hooks disabled, no observed tool use or
API/paid-overage route. The immutable raw packet is 93,291 bytes, SHA-256
`d4f9e6f8d7240703f6ba57a1efdc9830ba53436963f43ea90037be18a1bf9fe4`.
Review, provider result and closure: same task evidence directory, stage
`wp0-ready-r3`. No executable changed after acceptance. This appended verdict
is mechanical bookkeeping; reviewed report snapshot is preserved in the packet.
The earlier HELD label is superseded **only for owner-run menu source collection**.

After owner `RD-16_MENU_SOURCE_READY`, intake must check the exact task and
three-file count, both archive/manifest hashes, exact regular-file members and
per-file hashes, `context_sources=34`, `context_pre_post=ok`, and the custom-menu
theme count/key format. Do not infer active runtime links from DB placement:
settings, event overrides and runtime DIR_EXTENSION mapping are not attested.
The new shell is proposed as an explicit per-page opt-in, so existing menu
settings/other account pages are not to be mutated or globally redirected.
Any newly material source dependency is assessed from actual files, not guessed.

Current gate remains WAIT_OWNER_MENU_SOURCE_SUPPLEMENT; G0 not ready, no WP1.
Owner runs the adopted menu launcher once. On any STOP, retain receipts/artifacts
and report; no replay, no recollection of the already accepted main 34 files.

## G0 conclusion - evidence complete; owner gate remains

Owner reported `RD-16_MENU_SOURCE_READY`. The menu supplement matches its exact
archive SHA-256 `09ab6fa2a4075ddf7c6a5e06f9d4d2d025ae677ee3002524e05aaf2ead7fb19e`
and manifest SHA-256 `612dbbe5aa4187ef8082411fecb66ea4ac6b7fc4621de76ab2eef179c59c1dcb`.
Collection time: `2026-10-25T08:15:08Z`. Three unique regular members, 9,341 bytes,
all paths/types/sizes/per-file hashes checked before explicit safe extraction to
`work/rd16-wp0-20261025/current-menu-r1/`. Archive/context/privacy/cleanup/zero-write
contracts match the accepted collector. No result ambiguity requiring a separate
Claude call. Structured evidence: `owner-menu-intake-r1.json` in this evidence
directory. The original 34 local sources still match the original manifest;
the supplement host receipt confirms their 34 hash pins before/after collection.
Together, 37 unique source files and 768,061 bytes are verified.

### Source findings and root cause

- All 7 shared ACC-009 release after-hashes match with no drift. Profile guard,
  validation JS, field names/IDs, success path and writer logic remain protected.
- All seven queried theme routes have zero DB rows across all stores, including
  `extension/ciaccountsidebar/module/account_sidebar`. `Loader::view()` preserves
  this route when triggering the view-before event, and `event/theme.php` passes
  that same route to the exact-equality theme lookup. Thus the custom-menu count
  uses the relevant key format. Other arbitrary events are not certified absent.
- The layout assigns the custom module. Its current source at line 21 injects
  `accountdashboard/sidebar.css`; line 92 renders the custom Twig. Links/colors
  depend on settings, which were not read. No existing rendered link inventory
  or module-enabled runtime claim is inferred from assignment alone.
- The source reason the existing page lacks the approved hub is concrete:
  `account/account.twig:16` renders `column_left`, and line 22 renders the legacy
  mobile menu. The controller supplies links, not hub summary data. The desktop
  module has its own `.account-sidebar` markup, settings-driven inline colors and
  an added desktop override section in `sidebar.css:48`. This is an existing
  presentation to replace on the opted-in pages, not a new data/validation defect.
- `common/column_left.twig` hides the desktop surface below 768px; the hub mobile
  menu uses `d-md-none`. ACC-001 already corrected the prior 768-991 duplicate
  class. Its patch history and the current markup were inspected; RD-16 must
  preserve one nav surface through 390/768/1280 rather than revive that defect.
- The module language load reads `heading_title`, but the current custom Twig
  does not render that value. No fourth language-file source supplement is
  required to understand or bypass this module on the hub. Its settings,
  language file, template and stylesheet will not be edited globally.
- Fresh order model `publicOrderScope()` already applies customer/store ownership
  and ACC-007 hidden-status filtering. Hub latest order will reuse `getOrders(0,1)`
  and `Acc007OrderStatus::presentation()`. Header uses `account/wishlist.total`;
  that method explicitly performs a read-only visible-product count. Hub will
  reuse this exact count source. No new model bodies or business SQL are needed.
- Hub currently obtains logout with `url->link('account/logout', language +
  customer_token)` at `account.php:52`; the live logout body does not itself
  require/validate customer_token. Preserve the existing controller-generated
  route/arguments rather than constructing a URL in Twig.
- All four local light-A boards match their recorded README SHA-256 values.
  This is design-source validation, not rendered UI acceptance.

### Selected shell mechanism and contained impact

Select **(a), a dedicated controller-rendered block with its own Twig view**.
The hub explicitly requests that block in WP1; edit explicitly opts in only in
WP2. Later tasks opt in individually. A separate rendered view does not depend
on cross-template includes resolving through an isolated ArrayLoader. The trade-
off is an explicit child-controller call and context/label contract per adopting
page, plus two new files to admit. Do not duplicate the complete nav per page.

WP1 will replace the hub's old mobile markup and stop the hub's old column-left
menu rendering/style injection at its caller, then render one new scoped shell.
It will not edit or replace shared `common/column_left`, the `ciaccountsidebar`
module, its settings or its CSS. Therefore address/order/login/wishlist and other
account pages do **not** automatically adopt the shell. No owner decision for a
global menu rollout is needed or implied. Shared DS CSS additions must use new
scoped account classes; no new override of legacy `.account-sidebar` is proposed.

### Concrete WP1 preparation scope proposed for owner G0 approval

| Target | Intended change |
|---|---|
| `catalog/controller/account/account.php` | readonly hub summaries and explicit shell call; preserve auth/session/logout contracts |
| `catalog/view/template/account/account.twig` | approved light hub, truthful empty states, one nav surface |
| `catalog/controller/account/shell.php` (new proposed file) | shared child renderer; admission requires file absent before apply |
| `catalog/view/template/account/shell.twig` (new proposed file) | sidebar/mobile nav, support and inert theme placeholder |
| `extension/ukrainian/catalog/language/uk-ua/account/account.php` | prefixed hub/shell presentation labels; shell context receives labels explicitly |
| `catalog/view/stylesheet/boostershop-ds.css` | scoped light-A account styles mapped to existing DS tokens |
| `catalog/view/template/common/header.twig` - **pending narrow exception** | cache token of the existing DS stylesheet reference only |

The proposed new file paths have not been attested absent on the host. The
future guarded runner must check absent-state and refuse collisions. No PHP
runner or changed production target content has been authored at this G0 gate.
Any truly necessary scope change found during implementation must be reported.

### Exact owner decision still needed

The handoff explicitly says `WP0 - fresh evidence (stop at G0)`. Evidence and the
mechanism recommendation are now complete. Await the owner's **go for WP1**.

The current DS reference is in `catalog/view/template/common/header.twig:76`.
The handoff requires cache busting but also lists the shared header under do-not-
touch. Request a **token-only exception for this existing DS reference**. Read
and validate its current token by asset path and replace it wholesale; preserve
all other header bytes and behavior. This changes the DS asset URL shared by
pages, so browsers request the new CSS; it changes no header structure or logic.
No duplicate stylesheet injection or broad header edit is proposed. The exception
is not assumed from G0 preparation and no header bytes have changed.

After that bounded approval: implement WP1, validate actual generated UI at
390/768/1280 with long content and keyboard states, cover discriminating synthetic
order/address/favorites cases, prepare guarded apply/rollback and the exact owner
packet, then integrated independent ready-stage review. Owner deploy/QA remain
separate. WP2 remains blocked until owner QA-passes WP1; editable email is the
handoff's default and needs no new decision. Runtime extension-root mapping and
any applicable hooks remain application-admission checks, not claimed runtime
proof from these source archives. No Git/status/production/data mutation grant
follows from this report.

## WP1 implementation - owner approval and concrete ready package

Owner explicitly approved WP1 plus the CSS cache-token exception on 2026-10-25. This authorizes
local WP1 implementation and only the existing DS cache-token change in the
protected header. The G0 wait is consumed for WP1 preparation. It grants no
agent server/key operation, production deployment, Git/status mutation or WP2.
The chosen renderer is implemented as (a), explicit per-page child rendering.

### Result and exact changed targets

The hub now has the light-A welcome, one nav, four truthful summary cards and
a latest-visible-order row, desktop support/theme cards and a mobile list nav.
Settings is a non-focusable disabled link role, current nav has aria-current,
the two theme buttons are disabled under an aria-hidden blurred group and
support uses the exact Telegram URL with target=_blank/rel=noopener. There is no
new JS, storage, dark-mode logic or backend writer. Mobile retains all four
summary cards because the explicit handoff requires them, although the mobile
board abbreviates that content. Decorative board rings are omitted; functional
layout, surface hierarchy and existing site container are retained.

The old sidebar call is omitted at the hub caller, so neither duplicate menu nor
its injected legacy CSS is generated there. The shared module/column-left source
and all other account pages remain unchanged. Shell page-mode chip styles are
provided but no other page opts in. The profile form, validation JS and ACC-008
guard bodies are unchanged and hash-pinned as dependencies.

| Target | Before SHA-256 / absent | After SHA-256 | Backup copy index |
|---|---|---|---|
| `catalog/controller/account/account.php` | `58e9054bdc38281c2d8bdad2797e514cd9b172d4a048a19c6c92a79a98014443` | `fcb06de0862686ab1d8afc333651a7f97fb6aa9615d5bf13909d3a1b6a055b5c` | `original-0` |
| `catalog/view/template/account/account.twig` | `8f8ae6e2b315b13def1e937f18b84975bfc4f6f2792637e4113b87752b857ed6` | `7cf795ace7b9c8f81ddfc44d246eb8273c1aa9dc77f55a7b751c93d08b166520` | `original-1` |
| `catalog/controller/account/shell.php` | `absent` | `b39077e4b62f540a4ec47fc02021447ea961fbb8b2423d4027688f45c14aa3e1` | `none` |
| `catalog/view/template/account/shell.twig` | `absent` | `f61496ef34e56f813a17ace4795962ff450ed71ec88f1413a62a0e486f85ca16` | `none` |
| `extension/ukrainian/catalog/language/uk-ua/account/account.php` | `f1933ecf6f491921945152e03fd390a53eddd36773f09d6d087c002afb3ae597` | `121f9e65a90820469c0d013d923e00b1a94fadc22ecf2c36c64e84f46a7a5040` | `original-4` |
| `catalog/view/stylesheet/boostershop-ds.css` | `c5af44b72b362a8090b2f5699d41f06d0e2d1663ca9b82173c66d9d67413af0d` | `ab66de1d4bea57e39f165636c53868dfbb1f518ed17055647bdb3f9e09011b65` | `original-5` |
| `catalog/view/template/common/header.twig` | `4f202948f7cd377424b40cae8682bf3b6f8f0802ee17775fd6c72dd42e5cbc56` | `4401309361e51e502754ebba0fad0843da0a98db24711d0c99a76a42225eed83` | `original-6` |

All unrelated original target bytes and EOLs are preserved: CSS and namespaced
Ukrainian labels are appended, the hub controller replaces one checked old
column-left call with readonly summary/rendering code, and hub Twig is replaced.
New files must be absent at host admission.

Header before-hash in the table is the fresh intake token's identity. Other
well-shaped current DS tokens are accepted only if replacing that one token
with `RD16_TOKEN` reproduces normalized SHA-256
`54879a997e8e085166ac2aa40ad4b20b4776de927d68394d871b13c81a83d46c`.
Runtime computes and records the actual header before-hash/backup; no old token
is anchored or appended. Applied output is fixed at `rd16-wp1-20261025`.
A complete no-op leaves a later asset token untouched (verified locally).

### Data flow and owning CSS interactions

Profile uses existing authenticated Customer getters. Default address uses the
current default ID and customer-scoped `account/address.getAddress`; only public
address text is rendered, never service custom_field keys. Order uses actual
`getOrders(0,1)` and the ACC-007 publicOrderScope/presentation map; hidden 15 and
other customers/stores are excluded by the existing model. Favorites uses the
same actual `account/wishlist.total` method as the header. No model body, status
map or query ownership predicate is changed. All dynamic text/URLs are escaped;
controller URLs are entity-decoded once before attribute escaping to preserve
existing logout route/parameters. Labels come from namespaced language keys
passed explicitly to the child controller; it never reads nav data from GET/POST.

Root style interactions were inspected before completion: legacy sidebar rules
in its own CSS and settings-driven Twig !important declarations are bypassed at
the hub caller, not overridden globally. New rules use `.bs-account-page` plus
component classes. That prefix is necessary because existing DS `.bs a` at
line 97 otherwise wins over a single component class and makes blue CTA text
unreadable. The real-body fixture exposed this and the authored source selectors
were corrected before review; no !important was added. Existing ACC-001 history
and the current md breakpoint were checked. A single 992px layout transition
keeps one nav across the previous md/lg overlap class.

Board colors map to existing paper/bg/line/ink/blue/blue-soft/gold-soft/warning-fg
and danger tokens. No token was added. The favorite's pink mock fill becomes
the existing neutral surface with danger-colored heart. ACC-007 labels are kept
exactly, but badges use blue-soft rather than importing a green status style,
as the handoff reserves green outside this shell. New CSS/markup has no
!important, setTimeout, absolute/fixed positioning or unmentioned pixel constants;
board touch/icon/sidebar dimensions and Bootstrap boundaries are documented.

### Guarded runner, runtime admission and receipts

Runner: `patches/RD-16-WP1_account-shell-hub_20261025.php`, 82,692 bytes, SHA-256 `c8a8cc5559f09fd57e4c027b6f7aac8ec939210a0e090f31980fe53c5abfda1a`.

Owner packet: `diagnostics/RD-16-WP1_owner_20261025.ps1`, SHA-256 `c99761454e87b9053dee64583318cb6213b2fa9d9944d2357f57643fb730aeaf`.

The self-contained runner's cwd is the actual webroot; it refuses execution
from private staging inside that webroot and enforces same-filesystem atomic
renames. Existing targets are admitted by before-hash, new targets by absence;
header non-token bytes by normalized hash. 32 unchanged input sources are pinned.
A task lock is exclusively created and only its actual owner removes it.
Payload SHA-256 and PHP lint pass before backup/write; dependencies and all live
target hashes are rechecked before first write. Existing modes/UID/GID are
restored on staged candidates; new files use 0644. Post-write PHP lint failure
restores known partial writes after a complete target/backup preflight.

Host --check loads config only in owner-run PHP without printing its contents.
It checks webroot/catalog/extension mappings and the verified template-cache
directory; validates table columns before READ ONLY SELECTs; requires zero active
theme overrides for hub/new shell/header, current account layout `[0,6,account/%]`
with only the existing custom column-left module, and no unadmitted hooks for the
new/changed controller/view routes. The native view-before theme handler is
allowed only with zero corresponding active theme rows. Arbitrary other hooks
STOP before mutation. No settings/customer/order/session data is selected.
Those runtime facts, target states (including permissions) and dependency hashes
form an admission SHA-256. Apply requires the same freshly verified admission.
No runtime preflight is claimed passed before owner Verify.

Owner PHP must be 8.0.x. The packet resolves/records the actual PHP path in Verify
and binds the same executable in Apply and rollback. Local native machinery was
tested with PHP 8.3.30, not production 8.0.30.

Successful --check stdout:

```text
admission_sha256=<64 lowercase hex>
syntax_gate=ok
production_writes=0
backup=none
done=ok
```

Successful Apply stdout, after admission and syntax lines:

```text
backup=/home2/boosters/public_html/_patch_backups/RD-16-WP1_account-shell-hub_20261025-<YYYYMMDDTHHMMSSZ>-<12 hex>
target=<each of the seven exact paths> sha256_before=<actual hash|absent> sha256_after=<table hash> backup=<exact original-N path|none>
changed_files=7
self_deleted=yes
done=ok
```

The exact full backup path is created on host and captured from the receipt; no
concrete future timestamp/path is invented here. Private staged runner is removed
only after success. No-op: `already_applied=yes`, no asset-token write. Failure:
`error=<fixed reason>`, nonzero. A failed write may additionally emit
`automatic_restore=ok`. Preserve receipts; no blind replay.

Apply runs first; only its success invokes external template-cache cleanup using
`&&`. Cleanup validates the exact configured
`/home2/boosters/ocartdata/storage/cache/template/`, preflights entries/symlinks,
and removes only compiled PHP files inside it. It never clears general cache or
modification directories. Packet then verifies private staged runner absence.
Expected wrapper lines: `template_cache_removed=<count>`,
`template_cache_cleanup=ok`, `staged_runner_absent=yes`. Owner success marker:
`RD16_APPLY_OK_OWNER_QA_REQUIRED`. Cache failure after source success remains a
STOP with preserved source/backup receipt, not an excuse to replay Apply.

### Backup and rollback

Five existing targets have verified exact copies `original-0`, `original-1`,
`original-4`, `original-5`, `original-6` plus manifest under the receipt's exact
backup directory. The two new shell files have backup=none and before=absent.
Rollback preflights EVERY current state and required backup hash before any
restore/delete: after -> restore checked backup or remove own created file;
before/absent -> skip; anything else -> STOP whole rollback. Header backup's
non-token bytes are also bound to the normalized header contract. Partial apply
and already-restored targets are supported. Restore candidates are prepared,
linted and chmod/chown/chgrp checked before first rollback write; mtime is current,
not copied stale. External verified template-cache cleanup follows successful
rollback. DB mutations/SQL rollback: none. WP2 must be rolled back first if ever
applied later; WP2 is not implemented or released now.

### Local verification and material fake boundaries

- Native proposed Account/Shell controllers, existing complete Order/Address
  models, existing Wishlist::total and real Twig ArrayLoader render the actual
  delivered hub/shell markup. Five synthetic contexts cover empty state, hidden
  latest with older visible order, visible latest, 32-character first/last names
  plus long email/long ACC-007 label, and escaping. Query spy refuses non-SELECT,
  checks customer/store/hidden predicates and returns controlled results. All
  service metadata remains unchanged. Fake bootstrap, DB results/localisation,
  Customer getters and header/footer CONTENT are declared; this is not MySQL or
  authenticated production proof.
- Local Chrome over loopback HTTP loads full admitted Bootstrap/DS/stylesheet
  and the six live public CSS/Manrope assets read from their exact header URLs
  (public-assets-manifest.json records time/hash). Actual body class bs and
  main/footer wrappers are represented. 17 UI cases: 390/768/1280 with five
  data contexts plus 991/992 boundary. One nav, visible logout, truthful cards,
  support attributes, inert keyboard targets, hover/focus/active, white CTA text,
  actual Manrope faces, no overflow/page/console errors passed. Screenshots at
  390/768/1280 were visually inspected; header/footer content remains synthetic.
  Existing shared JS is not executed; no JS was added. Host UI QA remains required.
- Real local file/lint/apply/no-op/rollback engine passed ten discriminating
  scenarios, including dynamic token, corrupted backup refusal before any
  rollback write, mixed partial state, target/dependency drift, new-file
  collision, one-use admission and lock ownership, and automatic restore after
  a deliberately injected post-write lint failure. ONLY host-admission function
  is replaced in the derivative; explicit post-lint, rollback-state-race and candidate-corruption derivatives are used.
  Unmodified actual runner separately refuses missing config with zero writes.
  R2 passing native fixture binds the R2 runner; R3 reuses it by the exact outside-predicate equivalence proof below.
- PowerShell parse, generated Bash Upload/Verify/Apply/Rollback syntax and
  extracted cleanup PHP lint passed. A local cleanup derivative changes ONLY
  expected cache root to a synthetic fixture: deletes one nested compiled PHP,
  retains unrelated cache PHP and non-PHP template file. No SSH/SCP was executed
  by the agent. Known real owner transport from WP0 is reused with exact LF stdin,
  raw receipts before gates, strict SSH alias/agent options and no key actions.
- PHP syntax passed locally; no get_result/fetch_all or PHP8.1+ language syntax.
  Runtime extension mapping, native event/layout admission, actual PHP8.0/MySQL,
  production global JS interaction and authenticated UI are deliberately not
  claimed. OCMOD absence/refresh is not certified or modified.

Evidence is retained under `work/rd16-wp1-20261025/`: targets/dependencies JSON,
build scripts, native backend/runner/UI fixtures/results, local preview PNGs and
public asset hashes. Earlier failed fixture path/Windows realpath and incomplete
body-wrapper discoveries were corrected before final assertions; raw historical
fixture directories remain. No customer records, keys or hosting archives are
staged for Git.

### Exact owner steps after integrated review accepts this pair

Each command is a separate owner action in normal PowerShell. No agent runs it.
The existing unlocked key/alias is reused; no new setup or mandatory Lock.

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16-WP1_owner_20261025.ps1' -Step Upload
```

Expected `RD16_UPLOAD_OK`, private upload only; then separately:

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16-WP1_owner_20261025.ps1' -Step Verify
```

Expected `RD16_VERIFY_OK`, no production target writes. Any STOP -> preserve
receipt and send it, no Apply. After successful admission, owner deployment:

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16-WP1_owner_20261025.ps1' -Step Apply
```

For a separately chosen rollback, use `-Step RollbackUpload`, then separately
`-Step RollbackVerify`, then separately `-Step Rollback`. If a failed Apply left
the exact reviewed staged runner present, do not overwrite/re-upload it; start
with RollbackVerify after assessing its preserved receipt. The packet uses only
the exact saved backup, hash-pins the executable and packet across steps, records
raw outputs and consumes Apply/rollback attempts before dispatch to prevent replay.

### Owner QA and next gate

After Apply receipt: hard-refresh, authenticate, check 390/768/1280, nav single
copy and logout/support, actual profile/default-address/latest-order/favorites
values or truthful empty states, no placeholder data, disabled settings/theme
and no new browser console errors. Do not create production synthetic accounts
or mutate business data for the local fixture cases. Tier 1 after deployment:
`/`, `/catalog/Pokemon`, `/catalog/Pokemon/Pokemon-booster-box`,
`/product/One-Piece-Boosters-OP-11`, `/product/FIG-CHARM-001`,
`/information/oplata-i-dostavka`, `index.php?route=checkout/cart`,
`index.php?route=checkout/checkout`. Require final 200 responses and owner QA.

Current next action: integrated actual subscription Claude CODE/composition
review of exact runner/packet/rationale/dependencies/tests/receipt/rollback.
No accepted WP1 review, host Apply or owner QA is claimed yet. WP2 stays gated
by explicit owner acceptance of deployed WP1. No commit/push/status/deploy by
the agent has occurred.

### Held Git handoff (not authorized or executed)

No standing Git grant follows from go WP1. If the owner later explicitly requests
a scoped commit/push, the prepared block below stages only the runner, owner
packet and this current report. It excludes source drops, archives, fixtures,
fonts, unrelated owner changes and secret-bearing files. On failure the pause
sentinel remains for assessment; do not reset/stage other work to repair it.

```powershell
Set-Location -LiteralPath 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
$ErrorActionPreference = 'Stop'
if ((git branch --show-current).Trim() -cne 'master' -or $LASTEXITCODE -ne 0) { throw 'STOP: expected master; do not switch automatically.' }
git diff --cached --quiet
if ($LASTEXITCODE -ne 0) { throw 'STOP: index is not empty or unavailable.' }
if (Test-Path -LiteralPath '.autosync-pause') { throw 'STOP: existing pause belongs to another workflow.' }
[void](New-Item -ItemType File -Path '.autosync-pause')
$Rd16Paths = @('patches/RD-16-WP1_account-shell-hub_20261025.php','diagnostics/RD-16-WP1_owner_20261025.ps1','diagnostics/RD-16_wp0_source-request_report_20261025.md')
git add -- $Rd16Paths
if ($LASTEXITCODE -ne 0) { throw 'STOP: stage failed; pause preserved.' }
$Rd16Staged = @(git diff --cached --name-only)
if ($LASTEXITCODE -ne 0 -or (($Rd16Staged | Sort-Object) -join "`n") -cne (($Rd16Paths | Sort-Object) -join "`n")) { throw 'STOP: staged set mismatch; pause preserved.' }
git commit -m 'feat(account): add RD-16 shell and hub'
if ($LASTEXITCODE -ne 0) { throw 'STOP: commit failed; pause preserved.' }
git push origin HEAD:master
if ($LASTEXITCODE -ne 0) { throw 'STOP: push failed; pause preserved.' }
Remove-Item -LiteralPath '.autosync-pause'
```

## WP1 R1 repairs and affected R2 package

Actual Opus/high R1 (`b6a39c2a-81db-41af-ae3f-afbd50394d8f`) returned Changes
requested, M1 and M2. The rejected identities were runner
`941df6b338b2f65bbefac4ca978068f26453c7214f3856d0a867faf884d9a9e6` and packet
`dbc0efc9c69353a6e2eab37b257b9db3017070eb9984e31a22c5b79b8e4b740d`.
Their full source/review/closure is preserved under stage wp1-ready-r1. No
owner launcher was released. All substantive repairs were batched before R2.

M1: the exact event classifier is now a small pure function. It treats a full
fnmatch OR prefix-extended fnmatch as relevant, evaluates all ten target
controller/view triggers, and permits a theme action only if EVERY matched
trigger is view/before. It retains zero-theme-row gating. There is no first-match
break. Ten native pure-function cases cover exact/custom, prefix-only view and
controller, broad `*` theme (rejected), view-before theme/alias, view-after,
header prefix and irrelevant admin. No claim to have executed or certified the
live Event dispatcher or configuration-provided hooks follows. The SQL admission
checks enabled DB events; config-registered hooks remain outside that attestation.

M2: the single explicit child call now returns independently rendered `nav` and
`aside` strings using the same standalone Twig view/ArrayLoader with a part flag.
The hub DOM is nav, main content, then support/theme aside. Desktop grid areas
put the aside under nav; mobile uses the main's contents to show welcome then nav
while the interactive DOM sequence is nav -> four cards -> latest -> support.
No help/theme block precedes main content in the DOM. A new exact Tab assertion
passes on 390/768/1280 and would fail the R1 layout. Existing empty/hidden/visible/
long/escaping cases, single-nav, no-overflow and inert controls still pass.
Direct controller calls without trusted internal context return two empty blocks.

O1 adopted: apply publishes child controller/view, labels and CSS first, then
header token and hub view, with the calling hub controller last. This removes
the missing-child publication window. O2 adopted: rollback requires identical
current-state snapshots across its two complete plans and re-hashes EVERY restore
candidate before any write. New native fault fixtures prove both state-race and
candidate corruption stop before any rollback target write. O3 adopted: rollback
and automatic-restore candidates are private staged files on the verified same
filesystem, not transient PHP files under the public backup folder. Compatible
old view/entry point are restored before removal of newly created children.

O5 adopted at the source selectors: scoped nav/action hover rules explicitly keep
text-decoration:none. Their computed hover style is checked. O6 comments now
explain 56px mobile rows and the 36rem readable line limit. No new global override
or !important was introduced.

O7: the current AGENTS.md website-facts contract says one storefront language;
the assigned handoff requests Ukrainian language files. This package follows
that single-language authority and does not claim to have enumerated enabled
DB language rows or add a new multilingual feature.

O8 checked against CURRENT protected edit source rather than assumed: its lines
207-209 set `acc008_profile_success` and redirect back to `account/edit`, not
to the hub. That profile-success path is unchanged and remains WP2 QA scope.
The existing hub's generic success notice is retained/escaped with role=status.
OPcache/global-JS runtime behavior remains owner QA, not local source proof.

O9/O10: Verify may legitimately STOP on an unadmitted relevant hook. A zero-write
admission drift between Verify and Apply still consumes the packet's one-use
Apply attempt. Preserve its receipt and obtain a reviewed resume/new packet;
do not edit state or replay in place. These are fail-closed contracts, not
permission to relax a preflight.

O11 exact rollback-check stdout: `backup=<exact recorded path>`,
`rollback_preflight=ok`, `done=ok`. Successful rollback additionally prints
`rollback=ok`, `changed_files=<0..7>`, `self_deleted=yes`, `done=ok`; the owner
wrapper then requires template-cache-cleanup and staged-runner-absence lines.
All-before mixed states may restore zero files; arbitrary drift stops all writes.

The R2 local passing fixture is runner-fixture-r6, binding the R2 runner hash. Its ten cases include actual file mechanics with ONLY host-admission
replaced, plus explicitly declared fault derivatives. Pure event predicate has
ten separate actual function cases. UI has seventeen contexts including a real
focus-order assertion and hover decoration checks. Fake boundaries are unchanged
and explicit; actual host PHP8.0/MySQL/dispatcher/globalJS/OPcache are not claimed.

Current next action: affected integrated independent R2 review of the exact new
runner and owner packet hashes above. Owner Upload/Verify/Apply remain HELD until
that verdict; no source/DB/cache deployment, Git or status write has occurred.

R2 packet also revalidates saved PHP-path/admission patterns when reading local
state, before insertion into any remote body. This is local input hardening;
SSH/Apply sequencing, one-use gates and the receipt contracts are unchanged.
Local PowerShell syntax is rechecked on the exact current bytes.

## WP1 R3 - registration/matcher interaction audit after two returns

R2 (`87083552-aff5-49bd-af6e-d6e4fc4ecbd5`) closed R1 M2 and verified the batched
publish/rollback/CSS/state hardening, but returned M1-R2 for missing system/
registration-prefix coverage. Rejected R2 identities: runner
`c05e9b50ed7a1a95a7c877cc2142341332eb9679b8cfb1532a871932b0ef9a4e`, packet
`37840c42e5d7c4d01ffc83779eb3e3e43d0add699c522b34d7e419cd90997c7f`.
R2 result and closure remain immutable. No owner execution was released.

The repeated-class escalation audit was performed before R3. Owning path:
DB setting/event getter -> catalog startup/event namespace registration -> Event
dispatch -> fresh Loader controller/view before/after triggers. The available
historical source for catalog/startup/event.php was read completely, together
with catalog/model/setting/event.php and the fresh Loader trigger code. It shows
catalog prefix stripping, a separate system branch, and possible interaction
after the first namespace is shifted. These historical files are path/interaction
evidence, NOT current host/version attestation. Current dispatcher/config body
remains uncollected and no such certification is claimed.

The fixture gap was registration namespace, not a failure of the isolated
full-or-prefix matcher cases. The predicate now conservatively strips any leading
sequence of catalog/ and system/, including bare namespace rows, before matching.
Thus either strip-or-retain startup behavior is over-approximated safely. Admin
remains irrelevant. Four method-qualified .index controller targets were added
(total fourteen before/after targets). All matches are evaluated; an unknown
action or any non-view-before match remains denied. No runtime hook is relaxed.

Nineteen exact native predicate cases preserve the prior ten and cover the two
mandatory system prefixes, both canonical/noncanonical controller spellings,
bare catalog/system, permitted system view-before theme, and nested namespace
combinations observed as a possible registration interaction. PHP lint and
PowerShell parse pass on the exact new pair.

The new runner is byte-identical to R2 OUTSIDE the documented pure predicate
block (including its explanatory comment). The owner packet differs ONLY in
its pinned runner-hash literal. `work/rd16-wp1-20261025/r3-equivalence.json` binds
both old/new hashes and the exact unchanged outside-block digest. All seven
application target payloads, backend data flow, fixed mobile DOM/focus order,
32 dependency pins, file mechanics, cache body and owner sequencing are unchanged.
Reuse the accepted R2 review coverage and R2 local ten-case file-engine / five
backend / seventeen UI / cache results within their stated fake boundaries;
those bodies were not rerun or falsely called new host proof. Only the changed
pure function was natively reexecuted with nineteen cases.

Current next action: affected R3 independent review of the predicate/registration
coverage, its fixtures, equivalence and exact new executable hashes. The remaining
owner gate is still Upload/Verify after acceptance, not a new design question.
No agent SSH/key/server operation, production mutation, Git/status write or WP2
work occurred.


## WP1 R3 accepted - mechanical owner-gate closure

Actual subscription Claude Opus/high returned **Review OK**, with no mandatory
findings. Request `2e26aa18-5a2b-4794-b829-2bd45348fc30` is consumed and must not
be replayed. The remaining M1-R2 registration finding is closed. R2 coverage of
the unchanged application, DOM/focus, file engine, rollback and owner sequencing
is reused under the exact byte-equivalence proof, with its original fake limits.

Accepted executable identities:
- Runner: 82692 bytes, SHA-256 `c8a8cc5559f09fd57e4c027b6f7aac8ec939210a0e090f31980fe53c5abfda1a`.
- Owner packet: 11830 bytes, SHA-256 `c99761454e87b9053dee64583318cb6213b2fa9d9944d2357f57643fb730aeaf`.
- Raw R3 review input: 38195 bytes, SHA-256 `c011c44098961d7b97992641ae0e9509512d3886602d94db91cecc8d6b1abbfb`.

All 85 declared input files and the raw review packet were independently
rehashed unchanged after completion. Native exchange metadata confirms the
requested model, no tools/MCP, subscription routing and no overage/API fallback.
The exact verdict is preserved in
`diagnostics/RD-16_WP1_review_evidence_20261025/wp1-ready-r3-response.md`.
This report's current-gate line and this closure section are post-review,
nonsemantic bookkeeping. Executable bytes and reviewed technical content are
unchanged; the closure JSON preserves the reviewed report hash.

Owner next action in normal PowerShell: run the exact packet separately with
`-Step Upload`, then `-Step Verify`. Expected `RD16_UPLOAD_OK` and
`RD16_VERIFY_OK`. Preserve the Verify output for the deterministic receipt check
before the separate owner Apply choice. Any STOP: preserve receipts, do not edit
state or replay. Exact launchers, Apply/rollback contracts, seven target states,
backup rules and cache scope are documented above.

Owner Upload/Verify/Apply: NOT RUN. No host PHP8.0/MySQL/dispatcher/global-JS/
OPcache/authenticated UI proof is claimed. Optional review notes remain
nonblocking within these declared limits. Production deployment and final manual
QA remain owner gates. WP2 remains held until owner acceptance of deployed WP1.
No Git commit/push, Notion/dashboard status mutation or agent server/key action.


## Owner Verify refusal - event admission, 2026-10-25

The owner ran the exact R3 packet Upload and Verify. Local saved receipts:
`work/rd16-wp1-20261025/owner-receipts/Upload-20261025T1930214906461Z.txt`
and `Verify-20261025T1930546118304Z.txt`. Host PHP /usr/local/bin/php 8.0.30
lint passed; Verify returned `error=unadmitted_target_event` and no admission.
Local state contains uploaded=true and the accepted pair only, no verified or
Apply evidence. This is an expected fail-closed branch, mechanically classified;
no separate actual-result review requested. Current executable verdict remains
byte-bound but does not admit this host; Apply and WP2 remain HELD.

Missing facts: which relevant enabled trigger/action rows were denied, and the
current registration/dispatch/action-resolution source. WP0 explicitly did not
collect event metadata; the R3 predicate is conservative and refusal does not
prove an unsafe live event or a matcher defect. Do not disable an event, relax
admission, edit prior state or repeat Verify without assessment.

Prepared standalone diagnostic `diagnostics/RD-16-WP1_event-inspect_20261025.php`
and owner packet `diagnostics/RD-16-WP1_event-inspect_owner_20261025.ps1`.
Owner streams PHP stdin via strict existing SSH alias, with no remote staging,
source/DB/cache/state writes or app bootstrap. Config is required privately only
to connect, never returned. Read-only SQL: SHOW event columns, transaction READ
ONLY, at most1025 enabled trigger/action rows (cap1024), at most64 relevant rows.
Uses exact R3 predicate to identify both admitted and denied relevant metadata.
Noncanonical/long field values are SHA-redacted; no customer query. Collects only
four named core sources (startup/event, model/setting/event, engine/event,
engine/action), regular unlinked paths, max64KiB each and128KiB total, base64 and
hashes. No dynamic action source paths, secret config content or broad dump.
Raw stdout saved locally before validation. One-use exclusive local attempt
receipt is created before dispatch; no silent retries. Prior owner state untouched.

Local proof: PHP8.3 lint, native exact collector/predicate with fake mysqli and
synthetic source files; success/read-only SQL/metadata privacy/row cap/relevant
cap/schema/source cap pass. Owner packet AST and native generated Bash -n pass.
Native PowerShell wrapper tested with ONLY transport replaced and fixture root:
raw receipt saved and repeat refused. No host8.0/MySQL/SSH execution is claimed.
One Git Bash -n sandbox launch was denied by MSYS object creation; the exact local
syntax-only test completed under approved normal-user execution, no remote action.

R4 is a normal coherent ready-stage review of this NEW read-only diagnostic and
its owner launcher, not an actual-result exception or a main Apply release.
No executable changes to accepted R3 runner or owner packet.


## Event diagnostic R5 repairs after R4 return

R4 request 477f064e-73e5-4570-8d96-c7197b842774 returned Changes requested;
no owner diagnostic released. Mandatory M1 stdin STDERR failure fixed: bounded
stdout STOP reason uses a closed literal allowlist, unknown errors map internal,
exit1 and no exception details. Native php -n reading exact diagnostic from stdin
now returns exactly RD16_EVENT_INSPECT_STOP reason=php, exit1, empty stderr.
Mandatory M2 no-temp claim fixed using reviewer's preferred exec handoff:
Bash's final command execs PHP, which reads the remaining source from the same
non-seekable stdin. No heredoc. Native local bash -s -> exec PHP stdin transfer
passed with exact failure marker; only fixed host checks/PHP path replaced with
local PHP -n. Bash -n checks the shell prefix, not the following PHP source.

Batched small optional hardening: exact DIR_SYSTEM/DIR_APPLICATION mapping
checks bind collected sources to config paths; launcher reads PHP bytes once,
hashes and transmits that buffer; ssh.exe presence precheck before consuming an
attempt; success regex uses strict end anchor. stdout receipt is decoded UTF8
text rather than raw byte capture (payload is ASCII). Existing one-use local
owner wrapper success/replay-refusal fixture passes after repair.
No additional source/schema scope, no event admission relaxation or main changes.
R4 optional extra sources/metadata/tests are nonblocking and are not automatic
new tasks. Runtime result still unknown until owner read-only diagnosis.


## Event diagnostic R5 accepted - owner read-only gate

Actual subscription Claude Opus/high returned Review OK, mandatory findings none;
R4 M1/M2 closed. Request 7da25097-fefe-445a-91bc-014c8ed51ecd consumed, no replay.
R5 raw input59424 bytes SHA256 e597e564b58c1dc804beff129db6b3b412949050e54d72a7dc1c7f5ae0d668a1.
Accepted diagnostic PHP5824 bytes SHA256
61c329aa15b358ff3d8b1d43080b4ece8abea45ab1cc3749bcf5da0e9a88c448.
Accepted owner PS4493 bytes SHA256
95e8591f630a76117e9c61270950968dffd46aa9ea60373e22234f812adae9c8.
All declared inputs and packet rehashed unchanged after review; current accepted
executable bytes confirmed. Native record confirms subscription-only model,
no tools/MCP/overage/API fallback. Real diagnostic attempt absent before release.
This section/current-gate update are mechanical bookkeeping, no executable edit.

Owner runs diagnostics/RD-16-WP1_event-inspect_owner_20261025.ps1 once in normal
PowerShell. Expected RD16_EVENT_INSPECT_OK and saved receipt path. On any STOP,
preserve attempt/stdout and send complete terminal text (stderr is terminal-only),
never retry or edit one-use state. On intake verify attempt phpSha/packetSha
against the exact accepted pair, validate complete JSON/source lengths/hashes,
then inspect relevant technical rows and sources without executing the capture.
No additional source/schema/testing optional note is an automatic new task.
Main runner/state unchanged. No Apply, DB/source/cache mutation, Git/status write,
agent SSH/key/server operation or WP2. Diagnosis execution has NOT happened yet.


## Owner event diagnosis accepted - actual hooks found

Owner ran the exact accepted R5 diagnostic once. attempt.json binds both PHP and
PS hashes; complete two-line stdout task/write/privacy contract accepted. All four
fixed source base64 payload lengths and SHA256 values verified;6145 bytes total,
PHP8.0.30 capture2026-10-25T19:50:39Z. Extracted under
work/rd16-event-20261025/current-r1, read as source, never executed.
112 enabled event rows scanned; exactly four relevant rows, all denied:
- catalog/view/account/account/before -> extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewAccountAccountBefore
- catalog/view/common/header/before -> extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewCommonHeaderBefore
- catalog/view/common/header/before -> extension/ps_google_recaptcha/captcha/ps_google_recaptcha.eventCatalogViewCommonHeaderBefore
- catalog/view/common/header/before -> extension/ps_live_search/module/ps_live_search.eventCatalogViewCommonHeaderBefore
No event is changed or disabled. Fresh startup strips catalog/, native dispatcher
uses anchored prefix regex globs, Action resolves the method after the last dot.
These are actual registrations, not a conservative system-prefix false positive.
Known fail-closed result closes mechanically; no extra actual-result review.

Historical Oct23/Oct15 extension source was used ONLY for bounded call-path
routing: enhanced header/account hooks load their analytics model and replace
Twig code; recaptcha header loads its captcha model; live search adds JS/CSS.
Current handler/model/asset bytes are missing. A new narrow read-only supplement
must collect three controllers,two owning models,two JS assets and one CSS asset,
plus revalidate the four already collected core sources in the same capture.
No config/settings/customer data read, no unsafe generic extension admission.
Main Apply/WP2 held. No main runner/owner-state change.


## Hook source supplement ready - R6

New dedicated diagnostic/launcher derived from exact accepted R5 pair. Changes:
fixed source list expands4->12, per-file cap64KiB->512KiB,total128KiB->2MiB;
task/marker/receipt path identities distinct. No SQL, predicate, bootstrap, root,
path/link, stream, attempt or error contract relaxation. Existing R5 files and
consumed real receipt remain unchanged. No remote staging or here-document.
Extra paths: three action controllers,two owning replacement models,two injected
JavaScript files,one injected stylesheet; collected4 core sources revalidated in
same capture. Historical analytics controller266277 bytes justifies larger cap;
no settings/config source or data requested. Sources collected, not executed.
Source list in work/rd16-hooks-20261025/source-set.json.

Native exact collector with twelve synthetic sources and fake mysqli passes;
source cap test now524289 bytes. PHP stdin failure and native Bash->PHP exec
handoff pass; PowerShell AST,receipt preservation and no-replay pass. Test limits
unchanged: local PHP8.3, fake DB/host checks/owner transport, no server invocation.
Ready-stage R6 review binds new diagnostic+launcher only. Main Apply/WP2 held.


## Hook supplement R6 accepted - one owner capture ready

Actual subscription Claude Opus/high Review OK; no mandatory findings.
Request dc8cc5ba-6b02-46e0-89e1-1dea21d3608f consumed, no replay.
Diagnostic6475 bytes SHA256 f24136e1a7d01c12128ae3fd58f26645ee3519e08943045f7943fd59bc0b9154.
OwnerPS4488 bytes SHA256 e0c06bc511010eb513517dfee51e6626378b1289e0e3d2a55e5f607129daaaa1.
R6 packet72994 bytes SHA256 1f09d3e1f60f5d5e6ecf9bbd8415f7a92c3944876c6b616197358719f54cd544.
All declared file hashes/lengths and raw packet revalidated after completion;
real supplement attempt.json/stdout.txt absent at release. Main R3 pair hashes
reconfirmed unchanged. Owner invocation is the new hook-sources_owner packet,
not the consumed event-inspect packet. Expected RD16_HOOK_SOURCES_OK plus receipt.

Intake requirements: verify both attempt hashes, exactly12 source keys, base64
lengths/hashes,total cap/source_bytes, complete JSON task/write/privacy contract;
four core files match prior6145-byte capture, same four trigger/action/denied rows.
On drift/absent needed dependency hold affected composition, do not rerun. Follow
references on the affected hook/replacement path, not unrelated methods. Captured
code stays private; only needed excerpts enter later review. Sources are standard
OC4 path bytes; DIR_EXTENSION/autoload not independently attested by this collector
(recorded assumption), and future main admission still must enforce its own mapping.
If launcher fails after saving stdout, preserve full terminal text and receipt;
intake may assess the complete payload but never reinterpret it as automatic
main admission or a successful wrapper. No ad hoc source-list expansion.
This appended closure is mechanical; no executable bytes changed after review.
Main Apply/WP2 remain HELD; no collection execution yet, no deployment/status/Git.


## Hook sources accepted; WP1 successor main package R2 ready for R7 review

Owner's supplement receipt is complete despite terminal paste omitting the OK
line. The actual saved file has the exact two-line contract and both attempt
hashes match accepted R6. Twelve sources395387 bytes, all present, verified
base64 lengths/hashes, same four core hashes and same four denied pairs,112
active rows, capture2026-10-25T20:00:29Z. No raw config/customer data returned.
Canonical intake acceptance saved beside review evidence; source not executed
outside explicitly synthetic local test registries.

Root cause: the R3 guard intentionally admitted only native theme hooks. Live
has three installed extensions with four exact relevant hooks. Current source
proves their calls and template mutations; no extension is disabled or patched.
The analytics newsletter replacement searches `<h2>{{ text_my_account }}</h2>`.
That literal is absent from BOTH fresh before and proposed after hub. Native
hook test confirms unchanged template/no newsletter script in both; pre-existing
anchor behavior is preserved, not silently repaired or newly instrumented.

Successor: patches/RD-16-WP1_account-shell-hub_20261025_r2.php and
 diagnostics/RD-16-WP1_owner-r2_20261025.ps1. Same7 application target contracts
and payloads exactly; same backup/rollback/cache/no-op logic. Only admission
changes: four exact raw trigger/action pairs allowed, twelve current core/
extension dependencies added (44total), DIR_SYSTEM/optional DIR_TEMPLATE mapping,
read-only default-store two template-setting checks and standard-template fallback
absence guard. Unknown/broader/altered hooks remain denied. Non-default theme or
alternate fallback files STOP; no ad hoc path guessing or source relaxation.
No user/session/analytics behavior is authored. Captured handlers execute only
on real storefront as before; runner does metadata/file admission, not hooks.

New owner packet has only runner filename/hash and receipt-directory literal
changes against accepted R3. It uses new owner-receipts-r2/state.json, separate
from consumed/failed first run. Prior packet/state/private staged file retained,
not overwritten/deleted/replayed. Owner must Upload new filename, Verify anew,
then decide separate Apply after deterministic receipt check. No old state import.
Same RD16_ID lock/backup marker prevents competing task writers; no earlier Apply.

Native composition tests run full captured Event/Action/startup registration,
three controller classes and two models plus Twig ArrayLoader. Synthetic registry/
factory wiring, read-only event DB results, config/customer/cart/currency; VERSION
fixture4.0.2.3, enhanced-conversion flag off, no real account/analytics/network.
All6 header hook orders produce identical transformed before/after Twig except
DS token, asset-data equality (2JS/1CSS), each analytics/recaptcha injection once.
Actual vendor filesystem template fallback exercised on proposed source. Hub hook
on/off preserves source/session behavior; disabled header hooks leave code empty.
No affected hook-path reference outside collected sources was encountered except
existing framework/config/standard data services already bounded by source pins
or explicitly fake unchanged global behavior. No license/private source publication.

Browser17cases rerun with native hook-produced inline head, both captured vendor
JS assets, captured live-search CSS, public current jQuery3.7.1, admitted main
CSS/fonts and unchanged generated hub. Three widths390/768/1280 and991/992 boundary,
long/empty/escaped contexts, tab/hover/focus/active,no overflow/console/page errors.
Synthetic visible header/footer/config/data remain; common.js/global gtag loader,
host OPcache and authenticated runtime NOT attested. Existing5 backend cases reused
because all app payloads are identical; new11-case native file-engine suite passed
with ONLY host admission replaced, now44 pins and a new hook-dependency drift case.
New predicate/native template guard39+4cases pass. An initial wildcard fixture
used an irrelevant */view prefix; corrected to actual relevant view/*/before
mutation, then passed. No production code change resulted from that test correction.

Main-r2-equivalence.json proves unchanged7 payloads and all file/rollback mechanics
outside new admission/dependency blob; owner literal-only changes. Native host
PHP8.0/MySQL/settings/fallback and current target pins remain owner Verify gate.
No source/cache deployment, Git/status write, owner-state edit or WP2.


R7 main executable identities before dispatch:
- R2 runner87299 bytes SHA256 a46a3addb8f73d83d1ef3645625cddd9f5b9d4f485d7f6cea81bc2d31911f62e.
- R2 owner packet SHA256 de32ed409ccbbf3e8e67894aceb7eddafdd6836399ec419f3992776972e04e7c.
Both are HELD pending integrated ready review. Old R3 identities remain historical,
not permission to run the new pair. Owner packet AST pass; literal-only equivalence
reuses accepted four Bash body/cache syntax checks. Twelve dependency pin check
follows runtime metadata and precedes any target/backup writes, then rechecks
immediately before publication. Main runner never invokes captured hook methods.
Native hook tests exercise only synthetic data/services; hosted customer runtime
and owner QA remain unproved. Per-target states/backup/rollback/stdout stay exactly
as the accepted7-target table above; expected Upload/Verify/Apply markers unchanged.


## R8 - all-store fallback admission repair after R7 return

R7 request4db662ca-c696-4b59-9e82-64b7e553949b returned Changes requested M1:
store0-only setting lookup missed store-N overlays. R7 inputs80files unchanged
at completion; rejected pair retained under rejected-r7 and raw R7 packet.
Rejected runner a46a3add... and packet de32ed40... were never released or run.
Old R3 pair is explicitly SUPERSEDED: DO NOT RUN either old runner/owner packet,
even if future event changes would let its old Verify pass. Physical private
stage/state retained for evidence; no remote cleanup or replay permission.

M1 repaired: READ ONLY query now reads only the same two technical setting keys
across ALL stores, max1025rows with cap1024 and value read capped257characters.
Pure native helper groups by(store_id,key), rejects invalid IDs/duplicate keys,
overlays every store's own rows on store0 and checks every effective pair.
A store with no own rows is covered by the store0 check. No settings are printed.
R7 O1 batched: derive the theme segment exactly as vendor code, allow safe names
such as basic only when both effective and default fallback twigs are absent;
unsafe names/alternate files STOP. Null maps empty; empty segment is normalised
to template/template, without a double slash. No guessed live theme allowlist.
R7 O2: enforce exact captured four-hook multiset; missing/duplicate/extra rows STOP.
R7 O3: DIR_TEMPLATE is now required and exact, DIR_CATALOG must be undefined,
any DIR_MODIFICATION prefix that could redirect an affected vendor lookup STOP.
R7 O4: builder compares payloads against decoded immutable accepted R3 runner,
not against its own targets.json. R8 also proves full target/dependency blobs
byte-identical to rejected R7. R7 O5 retirement stated above. R7 O6 new cross-pair
and pipe-method alias refusal fixtures added; pure exact-pair function unchanged.

Native admission suite now41predicate cases (19earlier+22exact/mutation),13theme
cases including store-N unsafe override/present file/inherited default directory,
basic absent/present,duplicate store-key,modification prefix;4multiset cases.
All pass. Eleven-case real file/lint/apply/rollback/restore fixture rerun fresh
runner-fixture-r8b with ONLY host runtime admission replaced, passes and binds
new runner hash. No host8.0/MySQL/settings claim. The new readonly SQL is reviewed
statically; native host settings remain Verify admission, not a fabricated fixture.
UI17/native hook composition6orders/backend5 reused unchanged because seven app
payloads,all44pin literals,exact pair function and file mechanics are byte-identical.
Owner packet differs ONLY in runner SHA literal; no filename/state/path/command
change since rejected R7. New state still absent, main not uploaded/applied.

R8 affected review scope is settings/template guard,multiset/runtime mapping,
fixtures,equivalence and exact new executable hashes. No new owner source request,
app behavior,cache scope,Git/status change,production write or WP2.

Current R8 runner SHA256 2104651019f35584f9fa24ab2310a16c65e2ba3abdeb381baa2472a9e2860651; owner packet SHA256 f1c6ffc2d0f49a4ee6354c17f69f22acf5e619bd546eab6723d21106eed016bd.


R8 owning-path audit also checked historical startup/setting lines45-48 and
model/setting/setting lines52-55 (interaction evidence only): serialized values
are decoded instead of treated as plain strings. Runtime schema therefore now
requires setting.serialized and rejects any serialized row for the two theme
keys, before deriving a path. This is fail-closed metadata admission, not a
claim the historical startup source is the current host. A serialized-value
fixture discriminates this branch. LEFT(value,257) bounds returned technical
field size; values longer than256bytes stop. Source/target/dependency blobs
remain identical; final11-case fixture binds exact latest runner hash.


## R8 accepted - owner Upload/Verify gate

Actual subscription Claude Opus/high returned Review OK, no mandatory findings;
R7 M1 closed. Request88519f3f-bac1-456e-afc7-07bc9249be97 consumed, no replay.
Accepted exact pair:
- patches/RD-16-WP1_account-shell-hub_20261025_r2.php,89497bytes,
  SHA256 2104651019f35584f9fa24ab2310a16c65e2ba3abdeb381baa2472a9e2860651.
- diagnostics/RD-16-WP1_owner-r2_20261025.ps1,11839bytes,
  SHA256 f1c6ffc2d0f49a4ee6354c17f69f22acf5e619bd546eab6723d21106eed016bd.
- R8 raw input117825bytes,
  SHA256 bef718aac9721326fcd896ef71ba7c3320762b459c1de2666497a9245069b3eb.
All91declared inputs and raw packet rehashed unchanged after completion. Actual
native exchange confirms requested model/subscription/no tools/MCP/overage/API
fallback. Full response preserved in wp1-ready-r8-response.md; this gate line and
closure section are post-review mechanical bookkeeping only. Executables unchanged.

Owner in normal PowerShell, separate steps (never old packet):
1. & 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16-WP1_owner-r2_20261025.ps1' -Step Upload
   Expected RD16_UPLOAD_OK (new private basename _r2, prior staged file untouched).
2. & 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16-WP1_owner-r2_20261025.ps1' -Step Verify
   Expected RD16_VERIFY_OK,production_writes=0,backup=none,done=ok and admission.
Send complete Verify output; executor deterministically checks new saved receipt/
state/exact pair before supplying the separate owner Apply command. Any STOP:
preserve receipts/state, do not replay/edit state or invoke old runner. No main
Upload/Verify/Apply has happened for this accepted successor at release time.
Apply remains separate owner action; deployed-WP1 QA must pass before WP2.
Seven before/after states and5original backups/2new absent facts remain exactly
as original target table; backup root/publication/rollback/cache contracts unchanged.
No source/DB/cache deployment,agent SSH/key/server,Git or roadmap/status writes.

Optional R8 notes remain nonblocking, not automatic tasks: DIR_MODIFICATION test
covers literal-prefix interception only, not a general OCMOD certification;
future configuration changes can fail-closed scripted rollback; extra missing/
duplicate/native-theme event rows STOP under exact multiset. Runtime SQL/constant
code is statically reviewed; first actual host execution is owner Verify. Native
host8.0/MySQL/settings/OPcache/authenticated UI/common.js/gtag remain owner gates.


## Owner R2 Upload/Verify accepted - ready separate owner Apply

Owner reported Verify success. Saved Upload2026-10-25T20:46:01Z and
Verify2026-10-25T20:46:10Z exact bytes match the reviewed receipt contract, no
normalization/omission. Local state binds both accepted R8 executable hashes,
uploaded/verified=true, PHP/usr/local/bin/php and exact admission below; no
applyAttempted or Apply receipt. Exact local PHP and PS hashes reconfirmed.
Native host PHP8.0.30 runner lint and source/metadata/dependency admission pass
per the accepted guarded runner (including44pins,all-store theme guard,4hooks).
production_writes=0;backup=none; no source application or cache cleanup yet.
Admission SHA256 c15ac653fcb030068b23963d357533920ef1014fe5d91b25f5dbe11fd7d9f268.
Mechanical acceptance saved as owner-r2-verify-accepted.json; no extra model call
needed for a complete expected receipt. R8 closure stays immutable historical.

Next exact owner action (writes7 admitted targets, creates complete backups first,
then template-cache cleanup through accepted && sequencing):
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16-WP1_owner-r2_20261025.ps1' -Step Apply
Expected RD16_APPLY_OK_OWNER_QA_REQUIRED plus exact Backup line. Preserve complete
stdout and do not repeat Apply on STOP/failure. Apply remains NOT RUN at this
record. Native Verify proves its admission path only, not OPcache/authenticated
storefront/visual/data QA. WP2 held until deployed WP1 owner QA acceptance.
No agent SSH/key/server operation,Git commit/push or Notion/dashboard mutation.


## Owner R2 Apply accepted - deployed WP1, owner QA pending

Owner ran exact accepted R8 packet Apply once. Saved complete18machine-line
receipt Apply-20261025T2048021490448Z.txt and packet state match exact identities
and the same Verify admission. All7 per-target before/after hashes,5original backup
paths and2new-file absent/backup=none states match accepted contract. changed_files7,
self_deleted=yes,done=ok;template_cache_removed102,cleanup=ok,staged_runner_absent=yes.
Local validator first expected19lines by counting error, corrected to the contract's
18 before strict full-byte comparison; receipt was never normalized/edited/omitted.
Final acceptanceJSON owner-r2-apply-accepted.json preserves receipt/state SHA256.
Actual host application timestamp2026-10-25T20:48:01Z,PHP8.0.30.
Backup:/home2/boosters/public_html/_patch_backups/RD-16-WP1_account-shell-hub_20261025-20261025T204801Z-29e73b9335c0
Apply consumed; never replay. Old private R3 runner remains superseded,DO NOT RUN.
Rollback remains the exact separate reviewed RollbackUpload->RollbackVerify->Rollback
sequence of the R2 packet using this backup, only if a real defect requires it.
No DB mutations or SQL rollback. Code/command review remains immutable R8;
complete expected source/cache receipt closes mechanically, no extra result review.

Post-apply anonymous public HTTPS GET smoke,11requests,no cookie jar/auth/scripts/
POST/orders/messages. All bodies under256KiB,no truncation and no captured PHP
fatal marker. Seven of8literal Tier1 URLs200;the legacy FIG-CHARM-001 URL404 matches
Oct21prior evidence (not a new RD16 regression attribution). Current owner-provided
figurka-charizard-pokemon-3d-druk URL200. Do not claim all8literal URLs passed or
change protected SEO/redirects to hide the stale smoke-list item. Empty checkout
entry resolves cart200; guestaccount resolves login200, not private hub. All10HTML
responses carry rd16-wp1-20261025 DS token. Public DS234826bytes SHA256 exactly
ab66de1d4bea57e39f165636c53868dfbb1f518ed17055647bdb3f9e09011b65.
Full bounded proof in public-smoke-postapply-r2.json. This independently verifies
public CSS/token delivery and representative availability, not authenticated hub,
full JS/analytics/OPcache semantics or owner acceptance. No agent SSH/key/server
operation,Git commit/push,Notion/dashboard write or WP2 execution.

Owner WP1 manual QA required before WP2:
1. Hard refresh signed-in account hub on phone/tablet/desktop. One nav; no horizontal
   overflow/overlap; settings/theme show Soon and remain inactive.
2. Four cards show actual account/default address/latest visible order/favorites,
   or truthful empty states. No demo/sample values; count agrees with favorites.
3. Profile/address/orders/favorites/latest-order links open correct existing pages;
   support opens BoosterShop_Support_bot. Those other pages retain existing UI
   until their own tasks/WP2; do not count an unchanged edit UI as a WP1 defect.
4. Logout works,then login and return to hub. No profile saves/order creation
   requested for this presentation-only WP1 QA.
Owner response WP1 QA OK accepts this gate only; RD16 Done still requires WP2 QA.
WP2 remains unstarted pending owner acceptance and applicable task authority.


## Owner WP1 QA accepted - 2026-10-26

Direct owner reply: WP1 QA OK. This accepts the preceding deployed-WP1 owner
checklist as reported; no extra hidden/device/business cases inferred. Evidence
owner-WP1-QA-accepted_20261026.json. Handoff condition for WP2 preparation is met.
RD16 remains incomplete until WP2 deploy/owner QA. No Notion/dashboard status,
Git commit/push or production action follows. Continue within initial assigned
handoff scope: restyle edit/index presentation only, preserving all save/guard/
profile JS logic,posted fields and current email editability (handoff D1default).
The previously explicit shared-header cache-token exception was for WP1 only;
any WP2 token-only exception must be resolved against its concrete ready package
before owner application. No shared-header mutation is released by QA acceptance.

WP2 bounded missing fact: active view/account/edit and controller/account/edit
(index/save) hooks, not enumerated by WP1 predicate. Prepare one read-only source
snapshot rather than discover unknown callbacks after a main Verify. Refresh
fixed source files for actual edit/controller/Twig/profileJS/guard/languages,
WP1 applied shell/CSS/header preimages,known hook callpath/assets and core settings/
factory source. Config credentials/customer rows/settings values are not returned.
No real profile saves or test data writes. Existing WP1 host receipts are reuse
evidence only; WP2 must admit its own fresh before hashes and runtime metadata.


## WP2 intake ready - 2026-10-26

A single bounded read-only diagnostic/owner launcher is prepared, not a main
patch. Fixed33 owning source files: profile controller/Twig/language/JS/helper/
custom-field guard and three account models; current WP1 shell/CSS/header and
account labels; known vendor hook controllers/models/assets; core dispatcher/
Action/factory/settings loader; shared AJAX common.js,jQuery,stylesheet,Bootstrap.
Historical file sizes estimate about1.23MiB,not a current-source attestation;
per-file512KiB,total2MiB fail-closed limits. Config required privately for DB
connection/mapping only, never returned. No settings values/customer rows read.
Metadata: active event trigger/action pairs relevant to26before/after targets
including account/edit view and edit/index/save/pipe variants,old hub/shell/header;
1024active-row scan cap,64relevant-row cap,canonical-only fields elseSHAredaction.
Read-only theme enabled-counts for3routes,layout candidates for account/edit and
hub,max16rows;layout6modules,max16rows. SQL schemas checked,READ ONLY transaction,
rollback finally. Only technical metadata and fixed source bytes returned; never
execute captured source,include dynamic action files,stage remote file or cleancache.
Root/application/system/extension/template mappings pinned to named host paths.

Owner invocation is one streamed PHP stdin through same strict SSH alias and
existing auth. No heredoc/tempfile; native Bash exec handoff,LF/noCR transport,
read sourcebytes once/hash same buffer,local CreateNew one-use attempt ledger,
decodedUTF8ASCII stdout saved BEFORE validation; no automaticretry. Real intake
receipt directory is owner-receipts-intake,distinct from both priorconsumed WP1
captures and the future WP2 apply packet. No agent SSH/key/server invocation.
No WP2 main patch/header-token exception/deployment exists or is released.

Local native proof: PHP8.3lint,exact collector/predicate with fake mysqli/33
synthetic source files;readonly query whitelist,theme/layout metadata,account/%
route,source hashes/caps/redaction,rows/relevant caps/schema refusal and new edit
view/index/save/pipe hook cases pass. PowerShell AST/native wrapper receipt+repeat
refusal pass with ONLY transport/root substituted. Native Bash->PHP stdin and
exact controlled failure marker pass;Bash-n covers shellprefix. Host8.0/MySQL/
actualcode/sources not yet proved. Same CLI binary2.1.303/subscription savedlogin
verified via read-only discovery; no login/key setup requested. Review adapter
is a routing/task-authority-only copy of prior checked adapter, all restricted
model/auth/tool/MCP/overage/identity checks unchanged. One normal coherent
intake ready review,not separate design/result exception.


## WP2 intake r1 accepted - one owner read-only capture

Actual subscription Claude Opus/high Review OK,no mandatory code findings.
Request1ac6b61f-e52e-4507-94c8-1e2d59e61229 consumed,no replay.
Accepted PHP9900bytes SHA256 f3b5cdc13a78b96ffdb92ee8d7ce297b530b4a03b8f6aad7d92777a4eeccd6ba.
Accepted PS4496bytes SHA256 897303201d8aa5937eebb481aa7925696949f9a16b0e4085e8a567c685133b1a.
Raw input103757bytes SHA256 23a407ae1a7091c5ab99aca68fcf37c484c7eb2f37a455e2a3e8e2bd050cf7bd.
All26declared inputs andpacket rehashed unchanged aftercompletion. Real intake
attempt.json/stdout.txt bothabsent atrelease. Current exactPHP+PS bytesconfirmed.
One earlierlocalpacketbuilder path-spelling error and manifest-missing adapter
preflight stopped before any model invocation; corrected locally,no consumed
request/model replay or owneraction. Actual requestabovecompleted normally.
Gate/current section is mechanical bookkeeping only,no executablechange.

Owner normal PowerShell command,once:
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16-WP2_intake_owner_20261026.ps1'
Expected RD16_WP2_INTAKE_OK plus savedreceiptpath. This reads code/technicalmetadata
only,not mainWP2Upload/Apply. Preservefullterminaltext andreceipt onSTOP,no retry.
No WP2 header-token exception/datawrite/deployment granted. Intake NOTRUN yet.

Binding intakeconditions preserved in fullreviewresponse:verifyattempt hashes,
exacttop-levelkeys/PHP8.0.30/twoLF-lines/33sourcekeys/base64lengths/hashes/totals;
compare12priorR6sourcehashes and5WP1-applied targetafterhashes;recordanydrift andhold
composition,no rerun. Neededabsence/newhandleroutside33/themeenabled/otherlayout
or non-coremoduleholds affectedcomposition. Known ciaccountsidebar was admitted
in WP0/WP1,but is literally non-core: ifpresent in thiscapture,flag that binding
condition explicitly for reasoned disposition in the subsequent coherent main
review,rather than silently treating it as cleared or adding a separate result
reviewcall. Existing module/oldtheme/universalJS evidence may be reused only by
unchangedidentity/scope;new dependencies require reviewed supplement,not ad hoc
path expansion. Themedisabledrows/routecounts and languageafterhooks remain
reported limitations,not blanket source-equivalence certification.

Only a launcher throw in ConvertFrom-Json is eligible for independent mechanical
intake,ifcompletevalidpayload/OKmarker passes allreviewconditions. Contractmismatch,
nonzeroexit orSTOP neveraccepted as success. Capturedsources stayprivate and are
notexecuted;quoteonly neededcompleteaffectedmethods inlaterreviews. Main WP2
implementation/composition remainsheld pending currentintake and ownreadyreview.
No agent SSH/key/server,Git/status mutation or customer/businessdataread/write.


## WP2 current intake and local implementation - 2026-10-26

Owner's exact intake pair completed at2026-10-26T07:20:29Z. Complete2LFline payload,
exact12topkeys,PHP8.0.30,attemptpair,33sourcekeys/allpresent/1227692bytes/lengths/
SHA256/totals verified. Twelve R6paths and five WP1-applied afterhashes unchanged.
112activeeventrows,same4header/hub hooks,no edit/index/save/pipe hook. Enabled theme
counts empty;sole candidate store0/layout6/account%,sole module CI sidebar left0.
Intake binding condition5 literally names non-core modules; CI is pre-existing
and was explicitly adopted at WP0/G0/WP1. That composition hold is NOT silently
waived: main ready review must give its reasoned disposition,with the old3CI source
bodies/hash pins and current exact metadata. No new source-list expansion or extra
result-review call. CI only adds its own sidebar stylesheet; new edit opt-in omits
that call exactly like accepted hub. Other account routes/module files unchanged.

WP2 targets5existing,nonew: edit controller index-only opt-in,editTwig,editUA labels
append-only,DS append-only,header DS-token-only PROPOSED. Entire save() and private
writer tail byte-identical; seven guard/helper/model/profileJS/commonJS sources
byte-identical. Exact authored fragment rendered with native shell/Twig/profile
display helper. Root cause of horizontal stock form: edit.twig .row/.col-sm-2/
.col-sm-10. Prior-patch/shared-CSS search found existing WP1 account component,
no earlier bs-account-edit component. New CSS page-prefixed,DS tokens only,no
important/script/timeouts/positioning layer/new tokens. Page-only mobile main box
is needed because hub's display:contents ordering must remain unchanged. 48px
controls,44pxchips,8pxdot,300/720track/form limits and999pill are board/touch cues,
explained in CSS. No generic .form-control/global nav override. Desktop/grouping
matches A-Edit,mobile uses page chips. Email stays editable per D1default; no board
name pattern/static hint copied. All10customfieldtype branches andACC008checkbox
presence markers retained verbatim except wrapper classes,rendered in exactlyone
of2groups using current authoritative acc009_field_kinds (name=patronymic),not IDs
or translated-label discovery. Field IDs,names,types,data/error selectors retained.

Initial local generator used default Windows text encoding and a first-inner-for
split. Native Twig caught the truncated nested loop; first visual inspection
caught mojibake. Both corrected BEFORE any review/release: outer-loop rsplit,
explicitUTF8,rawbytes append/prefix assertions for language/CSS,byte-regex token
replacement and byte-only single controller-anchor replacement. Entire existing
language values now compared natively; literal Ukrainian heading/label assertions
added. Old language error strings/sourceEOL and all unrelated sharedbytes preserved.
No malformed candidate was uploaded or applied. Final screenshots390/768/1280
visually inspected after corrections; synthetic header/footer/breadcrumb/data,
not a screenshot of live or an authenticated QA claim.

Evidence: native renderer7before/after contexts(empty/filled/long/escaped/legacy/
alltypes/success),input/form/error-node attrs and FormData equal except classes/
intentional display order; globally unique fictional optionIDs. Browser23layout
contexts3widths+991/992 boundaries,19nativeJS rules and identical messages,legacy2,
current common.js real AJAX serializer pair compared with ONLY loopback response
mocked,visible errors,tab nav->firstname,hover/focus/pressed,zerooverflow/errors.
Current native guard2filled/cleared in-memory JSON roundtrips preservebs_first15,
bs_np_v1,unknown nested emptyobjects/999,checkboxclear,service-keyinjection refusal;
10PHPname+8optional rules,3client/serverhint parity. No actual controller save/model
write/MySQL or host roundtrip is claimed. Protected body/source equivalence is the
primary unchanged business-logic evidence; owner-host savedata QA remains required.
Guard differs from early ACC008r7,so no old four-writer fixture verdict is falsely
reused. Current captured guard itself was executed against synthetic lockedrow.

Self-contained runner derives from accepted WP1R8file engine. Changes: taskID/token,
5existingtargets,52pins,26eventtargets,edit included in fallback/theme guards,and
all theme rows counted conservatively regardlessstatus. Exact4capturedhookmultiset
stillrequired. Publishing labels/CSS/token first,then caller,then newview minimises
old-caller/newview interval; no zero-downtime atomic-multifile claim. Rollback view,
caller,header,CSS,labels; all five targets/backups preflight twice,private restore
candidates lint/hash checked before writes. Same lock/selfdelete/noop/dynamictoken/
restore-on-fault engine. Native9case file suite passed exactfinalrunner with ONLY
host runtimeadmission replaced: actual apply/rollback/noop/dynamictoken/backup
corruption/mixedstates/source+protecteddepdrift/wrongadmission/ownedlock/fault/
race/candidatetampers; explicitly declared fault derivatives. Pure admission old41
plus4edit cases,13theme/multiset cases pass. LocalPHP8.3 only; host8.0 first execution
will be ownerVerify. Sourceguard rejects unknown/drift,newmodules/layouts/hooks.

OwnerPS derives from accepted R8protocol with newtask/paths/sha/count/markers,
separate owner-receipts-main,state not imported from intake. AST and4Bash-n command
shapes passed;no real owner script/SSH/SCP invoked. Literal patch&&cachecleanup
sequencing retained,private staging outsidewebroot,backup before sourcewrites.
New WP2 header-token-only exception still PENDING: handoff section5 protects shared
header and prior go WP1+cache applied toWP1. Concrete proposed change is one DS
reference token to rd16-wp2-20261026; no other headerbyte or product/checkout change.
Ready review may accept conditional code but must NOT release ownerUpload/Apply
until this owner exception. D1emaildefault needs no new decision. No WP2 deployed.

WP2 before/after contracts (all existing,5backups):
- catalog/controller/account/edit.php: before 91a358589fa318df92c9fff8fea6ddd949479f008556a5c15f9750dc01b9dc54; after c1c31f06556c98a68586dc9562eba4b3397d5d9b5bf98f8cca171cb0e9632113; backup <receipt backup>/original-0.
- catalog/view/template/account/edit.twig: before 69b27f6192de5b2dbe1d46604e69cce1f2ab0d9be855aa8fdb8a465b4645f476; after c061d04d06906474bebd08700a75a659212e8e84a431c5ee5bcdc7961c4fcc2d; backup <receipt backup>/original-1.
- extension/ukrainian/catalog/language/uk-ua/account/edit.php: before 710e41700410dbd8cb14a2985c3d2f85deb106478a53a72737c5c39fe66c6d79; after 8e37b5575aae9bc8f0e5a160cb6cd1c42ddc4b8d6228084978351bedd0314622; backup <receipt backup>/original-2.
- catalog/view/stylesheet/boostershop-ds.css: before ab66de1d4bea57e39f165636c53868dfbb1f518ed17055647bdb3f9e09011b65; after e1d33339b3056de105b430c0a7e5153a4ae3e4be32922f45126118f3b4a2696f; backup <receipt backup>/original-3.
- catalog/view/template/common/header.twig: before 4401309361e51e502754ebba0fad0843da0a98db24711d0c99a76a42225eed83; after 66b626a4b99fc6e563fb68754dc49ec3eedce7a509349b43bd5c3bcb2f1d5a51; backup <receipt backup>/original-4.

Proposed runner SHA256 d03a0e76c63892365ecef4a601456365429828aae8201a1916514f03b989e851; exact ownerPS SHA256 04633bf5e023e063d46cbbe3ae3c19dbbbd95ef7c4b0c94b5faa971bcfa31cea.
Backup:cwD/_patch_backups/RD-16-WP2_personal-data_20261026-YYYYMMDDTHHMMSSZ-12hex/.
Expected Verify:admission_sha256,syntax_gate=ok,production_writes=0,backup=none,done=ok;
Apply:all5targetrows,changed_files5,self_deleted=yes,done=ok,cache cleanup and stage
absence plus RD16_WP2_APPLY_OK_OWNER_QA_REQUIRED. Rollback contract reused with5
existingtargets,backup verification andtemplatecachecleanup. No DBwrite/SQLrollback.
No agentserver/key,Git or Notion/dashboard mutation. WP1QA accepted; RD16 notDone.

## WP2 integrated ready review accepted conditionally - 2026-10-26

Actual subscription Claude Opus 5.5, high, request
`5624fdc6-9184-4ab9-b154-377a9ca468b4` returned **Review OK, conditional**,
with no mandatory code changes. The full preserved response and mechanical closure
are `diagnostics/RD-16_WP2_review_evidence_20261026/wp2-main-ready-r1-response.md`
and `wp2-main-ready-r1-closure.json` in the same directory. Native exit 0;
no tools, MCP, API fallback or paid overage. No reviewer execution or host proof.

Both artifact identities above remain exact: runner 107879 bytes,
SHA-256 `d03a0e76c63892365ecef4a601456365429828aae8201a1916514f03b989e851`;
owner packet 11928 bytes,
SHA-256 `04633bf5e023e063d46cbbe3ae3c19dbbbd95ef7c4b0c94b5faa971bcfa31cea`.
The raw review packet is 337419 bytes, SHA-256
`e9aaa1fec492afd3e30653b79d06d620bf7d2c5446fda6c52f552e57f9164d8e`.
All 143 frozen input lengths and hashes were rechecked unchanged after completion.
Recent outside-set paths were only the three review outputs and the mechanical
closure script; no overlapping implementation change was found in that bounded
metadata check. No complete pre-run whole-repository snapshot is claimed.
This section and the top gate are post-review bookkeeping; executable bytes did
not change. The closure retains the reviewed report's original hash in its
referenced frozen manifest.

The reviewer explicitly **cleared intake condition 5 for the known CI sidebar on
account/edit only**: the caller no longer invokes its sole column_left position,
the exact layout/module multiset and three CI sources are pinned, and other
routes remain unchanged. Any layout, module or dependency drift fully reinstates
STOP. Owner QA must confirm a single edit nav through 768-991 and unchanged other
account pages. This is not a blanket waiver of non-core-module checks.

Optional notes O1-O7 and inherited notes are retained in the response/closure,
not adopted in this revision. They concern focus-outline strength, potential
iOS input zoom, explicit DS pressed backgrounds, mobile flex-direction,
decorative/optional/autocomplete fidelity, inherited admission/language gaps and
cosmetics. They are non-blocking; no mandatory finding was downgraded. Adopting
code changes would require affected review and new artifact binding.

**Remaining owner gate:** handoff section 5 protects shared `common/header.twig`.
The earlier WP1 exception does not cover WP2. Ask the owner for the concrete
WP2 token-only exception: the single DS CSS reference becomes
`rd16-wp2-20261026`; all normalized header bytes are identical; rollback restores
the saved original header/token. All Upload/Verify/Apply/Rollback steps remain
HELD until this explicit permission. Do not release an execution command yet.
Granting it changes no reviewed artifact bytes.

Owner-receipts-main/state.json remains absent. Host PHP 8.0/MySQL admission and
authenticated save/reopen/clear flows are still unexecuted. After permission,
owner Upload, Verify and Apply remain separate actions with receipt inspection;
post-Apply owner QA at 390/768/1280 and 991/992, validation/success/save/clear and
Tier 1 smoke is mandatory. RD-16 is not Done. No Git, roadmap or deployment
authority follows from this review.

## Owner WP2 header-token-only exception granted - 2026-10-26

Owner explicitly approved WP2 plus the CSS cache-token exception on 2026-10-26.
package. The scope is the reviewed personal-data implementation and the single
DS CSS reference token in shared header, becoming `rd16-wp2-20261026`; no other
header bytes change. Record:
`diagnostics/RD-16_WP2_review_evidence_20261026/owner-WP2-header-token-exception_20261026.json`.
This satisfies the main review's remaining procedural hold. The historical
closure and the HELD comment in the owner packet are preserved unchanged; no
executable bytes or acceptance criteria changed.

Before releasing the first owner command, all 142 reviewed non-report inputs
were rechecked unchanged. Only the canonical report contains the recorded
post-review bookkeeping. Runner and owner packet remain bound to the hashes
above. Owner-receipts-main/state.json is absent; Upload, Verify and Apply have
not been run. Release Upload first, inspect its receipt, then Verify, then Apply
as separate owner actions; stop on any deviation and preserve receipts.
Backups and live state remain unknown until actual owner evidence. No agent
SSH/key/server operation, Git/status write, QA acceptance or deployment follows.

## Owner WP2 Upload accepted - 2026-10-26

Owner reported `RD16_WP2_UPLOAD_OK`. The saved Upload receipt contains exactly
`php_path=/usr/local/bin/php`, `php_version=8.0.30`, `stage_ready=yes` with native
LF endings. State has only the exact reviewed runner/packet SHA-256 values and
uploaded=true; both local artifacts were rehashed unchanged. Receipt binding:
`diagnostics/RD-16_WP2_review_evidence_20261026/owner-main-Upload-accepted_20261026.json`.
SCP success is owner-reported and accepted by the reviewed owner wrapper; the
saved stdout itself is the pre-upload staging check. Remote staged-file SHA,
syntax and runtime/source/metadata admission remain unproven until owner Verify.
No Apply or cache cleanup occurred. Next: owner `-Step Verify`, then inspect the
complete saved receipt before releasing Apply. No agent SSH/key/server action.

## Owner WP2 Verify accepted - 2026-10-26

Owner reported `RD16_WP2_VERIFY_OK`. Saved Verify stdout matched the complete
eight-line contract exactly, including native LF endings. PHP path is
`/usr/local/bin/php`, version 8.0.30; staged runner syntax passed and the reviewed
check path returned syntax_gate=ok, production_writes=0, backup=none, done=ok.
Admission: `2aaf5d06a7c4eef932ebfa9c96beeaa93de770f9afbd4050f37b5e2295ddb849`.
Owner state contains the same admission and exact reviewed runner/packet hashes,
uploaded=true, verified=true, and no applyAttempted or backup. Both local artifact
lengths and hashes were rechecked unchanged; explicit owner header-token grant
is retained. Evidence:
`diagnostics/RD-16_WP2_review_evidence_20261026/owner-main-Verify-accepted_20261026.json`.

This is owner-host Verify evidence for the reviewed staged hash, PHP syntax and
runtime/source/metadata admission. It is not Apply, authenticated save, UI QA or
agent server execution. No production files or template cache were changed.
Next released action: owner one-use `-Step Apply` for the five admitted targets,
with backup-before-write, self-delete and success-chained template cache cleanup.
On failure preserve the complete receipt and do not retry. Actual backup path
and per-target after states remain unknown until Apply receipt. Owner post-Apply
QA remains mandatory; RD-16 is not Done. No Git/Notion/dashboard writes.

## Owner WP2 Apply accepted - 2026-10-26

Complete saved Apply stdout matched the exact reviewed contract, all five ordered
target before/after hashes, and original-0 through original-4 backup references.
PHP 8.0.30, the accepted admission and both exact local artifact hashes matched;
owner state records applyAttempted=true, applied=true and the same backup.
Evidence: `diagnostics/RD-16_WP2_review_evidence_20261026/owner-main-Apply-accepted_20261026.json`.

Actual backup:
`/home2/boosters/public_html/_patch_backups/RD-16-WP2_personal-data_20261026-20261026T085509Z-9581b50acfd9`.
All five targets existed, so each has its original-N backup. Receipt reports
changed_files=5, self_deleted=yes, done=ok, template_cache_removed=57,
template_cache_cleanup=ok and staged_runner_absent=yes. No SQL mutation or SQL
rollback. This accepts complete owner-host execution evidence; agents did not
perform SSH/key/server operations or independently read remote filesystem paths.

Rollback remains a separate owner decision through the reviewed owner packet,
using this exact backup, preflight and restored-template-cache sequence. No
rollback is requested or run. If reverting both packages, WP2 precedes WP1.

Owner authenticated UI QA is still PENDING: refresh, save/reopen, clear optional
fields/reopen, Latin-name validation/error, success notice, editable email,
390/768/1280 widths and 991/992 boundary, one edit nav and unchanged other account
pages. Local guard fixtures and source identity do not prove live service-key
round trips or authenticated runtime behavior. RD-16 is not Done; no Notion,
dashboard, Git or memory update was made.

## WP2 post-Apply public smoke - 2026-10-26

Anonymous HTTPS GETs only; no cookie jar, login, scripts, form submission, order,
message or customer-data read. Captured metadata only, bodies capped at 262144
bytes plus a truncation marker and discarded. Proof:
`diagnostics/RD-16_WP2_review_evidence_20261026/public-smoke-postapply-r1.json` and
`public-smoke-timeout-followup-r1.json` in the same folder.

First pass had four TimeoutError outcomes (category, nested category, sealed
product, current 3D product). A single targeted follow-up of those four with
reduced concurrency returned HTTP 200 for each; original timeout results remain
preserved. No repeated successful checks or backend mutations.

Final public outcomes: home, both categories, sealed product, current 3D product,
information and cart returned 200. Empty checkout redirected to cart/200;
anonymous hub and edit redirected to login/200. All captured HTML used the new
`rd16-wp2-20261026` DS token with no captured PHP fatal marker or truncation.
Public CSS is 239150 bytes, SHA-256
`e1d33339b3056de105b430c0a7e5153a4ae3e4be32922f45126118f3b4a2696f`,
exactly the admitted after hash. This independently confirms public asset
delivery and token behavior, not authenticated form execution or visual QA.

Seven of the eight literal legacy Tier 1 URLs passed. The obsolete
`/product/FIG-CHARM-001` still returns the previously recorded 404; the current
representative `/product/figurka-charizard-pokemon-3d-druk` returns 200. No new
regression is established by this known stale smoke-list item; do not claim all
eight literal URLs return 200 or change protected redirects/SEO to mask it.
Only authenticated owner WP2 QA remains; no Done/status transition is made.

## Owner WP2 QA accepted and implementation closeout - 2026-10-26

Owner directly replied `WP2 QA OK` to the post-Apply checklist. Record:
`diagnostics/RD-16_WP2_review_evidence_20261026/owner-WP2-QA-accepted_20261026.json`.
This is owner acceptance of the deployed personal-data page, responsive layout,
single edit navigation, save/reopen, clearing optional fields/reopen, success
notice, Latin-name validation error, editable email and unchanged other account
pages. Do not infer additional unreported test cases, raw service-key inspection,
browser-console instrumentation or agent-authenticated live execution.

WP1 owner QA was already accepted. Both RD-16 work packages are now deployed
and owner-QA accepted; no implementation or owner QA gate remains in this handoff.
Per handoff section 10, this also closes the ACC-008 deferred responsive item
covered by WP2, not unrelated ACC-008 scope. The obsolete literal 3D smoke URL's
known baseline 404 remains disclosed above; the current representative passes.
Optional review notes remain non-blocking and recorded, not new assigned work.

Status-writer handoff: under ROADMAP_SOP.md sections 3-4 and the task handoff
section 10, the default Claude writer may close RD-16 canonical Notion after
checking this accepted evidence, then synchronize the dashboard in the same
bounded workflow with a single writer. Known task page from the handoff:
`3706bf20bdb481b396b7e38300a3c8bf`; verify its Roadmap ID before writing.
RD-ACCOUNT subtask 1 is the named mirror scope; do not close other umbrella
subtasks or start RD-17/18/19/20/29. Current external status was not fetched or
changed here, so this report does not claim it is already Done in Notion.
No message was sent to another chat or external service.

Rollback remains available from the exact WP2 and WP1 backups recorded above;
restore WP2 before WP1 if reverting both, through reviewed owner-only steps.
No rollback, deployment replay, Git commit/push, Notion/dashboard mutation,
memory update or new feature work is authorized or executed by this QA receipt.

## Owner-assigned status and Git closeout - 2026-10-26

The owner's subsequent direct instruction explicitly assigns Notion update,
dashboard synchronization and commit/push to Codex for this RD-16 task. This is
the exact writer exception under ROADMAP_SOP.md; the previous default-Claude
handoff is superseded for this closeout only. It grants no other task/status,
server/key/deployment or future standing Git authority.

Fetched the known task page and verified Roadmap ID=RD-16. Updated only Status,
Stage, Last Updated and Owner Decision, and appended a concise delivery/QA,
review, evidence and rollback note. Readback confirms Status=Done and the correct
scope. Email remains editable under the handoff default; no open D1 decision
blocks this task. Synchronized the local dashboard RD-16 row and RD-ACCOUNT
subtask 1; other account tasks stay unchanged and the umbrella stays active.

Git isolation: original branch codex/3dp-payout-approval-status at
e510b0284cc291ab3d39a8e0ad5cdd1583d6c2d2 and fetched remote master at
33a36c0fe8caea4f2beb4c330d184553c1049f7a diverged 46/16 commits. No branch
switch, reset, rebase, merge or force-push of the owner's checkout is permitted
or needed. A managed detached worktree at the fetched master holds only the
RD-16 release set; its dashboard receives the same bounded row/subtask change,
without copying unrelated local history or roadmap differences.

Publication includes the accepted deployed WP1 R2 and WP2 runner/owner pairs,
task-scoped diagnostic/collection code and sanitized review/receipt/test summaries,
the source handoff/design boards and this report. Rejected initial WP1 executable
pair, raw live source captures, archives, raw owner source receipts, browser
profiles and unrelated changes are excluded. Exact executable/review evidence
bytes are protected from Git line-ending conversion by RD-16-only attributes.
All selected bytes and the two final deployed pairs are checked before commit.

A malformed duplicated owner-approval quote in this report had expanded into
over a million mojibake characters. Its original bytes are retained privately
under work/rd16-wp2-20261026/report-before-publication-cleanup.md. Two approval
sentences were repaired from the direct owner decisions, with SHA provenance in
report-publication-cleanup.json there. This nonsemantic documentation repair does
not alter reviewed code, results, acceptance meaning or original review records.

No separate model review is required for this expected receipt/status closure
and byte-preserving publication. The original review verdicts remain bound to
their original inputs. Git publication must be confirmed against the remote
master ref; a prepared commit is not itself proof of a successful push.
