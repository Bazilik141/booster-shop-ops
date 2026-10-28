# RD-18 WP0 source collection ready package

Date: 2026-10-27 (Europe/Kyiv). Executor: owner-assigned Codex.
Root: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`.
Gate: LOCAL_CHECKS_PASSED_INDEPENDENT_REVIEW_PENDING. No owner command released.
RD-17 deployed and owner-QA-passed before this package was created; exact
receipts/acceptance and public availability are in `RD-17_closeout_report_20261027.md`.

## Scope and exact files

One owner-run narrow pull; source/DB/cache writes zero. Exactly 30 regular
files relative to `~/public_html`; no directory/glob, config, logs, sessions,
uploads, customer/order records or database dump is exported.

| Purpose | Paths |
|---|---|
| Existing orders presentation and actions | `catalog/controller/account/order.php`; `catalog/view/template/account/order_list.twig`; `catalog/view/template/account/order_info.twig`; `catalog/view/template/account/order_history.twig`; `extension/ukrainian/catalog/language/uk-ua/account/order.php` |
| Protected read-only contracts | `system/library/acc007_order_status.php`; `catalog/model/account/order.php` |
| Shell and D2 hub badge context | `catalog/controller/account/shell.php`; `catalog/view/template/account/shell.twig`; `extension/ukrainian/catalog/language/uk-ua/account/account.php`; `catalog/controller/account/account.php`; `catalog/view/template/account/account.twig` |
| Current assets/cascade and token | `catalog/view/stylesheet/boostershop-ds.css`; `catalog/view/template/common/header.twig`; `catalog/view/javascript/common.js`; `catalog/view/stylesheet/stylesheet.css`; `catalog/view/stylesheet/bootstrap.css` |
| Template and route/layout admission | `system/library/template/twig.php`; `system/engine/loader.php`; `catalog/controller/event/theme.php`; `catalog/model/design/theme.php`; `catalog/model/design/layout.php` |
| Current legacy menu caller/module | `catalog/controller/common/column_left.php`; `catalog/view/template/common/column_left.twig`; `extension/opencart/catalog/controller/module/account.php`; `extension/opencart/catalog/view/template/module/account.twig` |
| Injection and native pagination | `catalog/controller/common/header.php`; `catalog/view/template/common/footer.twig`; `catalog/controller/common/pagination.php`; `catalog/view/template/common/pagination.twig` |

The accepted RD16 fresh controller identifies all three order templates and
the common/pagination caller. This identifies names, not current RD18 bytes.
Old source is used only for collection discovery/lineage, never implementation.

Technical metadata is a fixed owner-run PHP8.0 read-only transaction:
schema checked first, int DB_PORT, prepared statements/bind_result, aggregate
all-store theme counts for order/list/info/history/shell/hub/header/footer/pager,
bounded matching order/info layout candidates and their module placements.
No business rows, raw template code from DB or customer/order identifiers.
Config is loaded on host only; all errors are generic. Enabled overrides block
template planning. These counts alone do not establish event/OCMOD absence;
later implementation admission must establish relevant effective runtime.

## Freshness and G0

Fresh capture and drift are NOT yet available. After intake compare against
ACC007 accepted sources/receipts and later RD16/RD17 lineage. Current expected
shared DS after WP1C is
`f1411862e5aed5da5e0da32b41e49e22e78f4ca8d297a7367bcac7b7be89ba67`;
header `dfeb11467afd43eb760bc95ea1cb0f38a0640e85ce3a90c77f5439fb7a2f33a2`
(separately normalize only token). Shell expected controller
`b39077e4b62f540a4ec47fc02021447ea961fbb8b2423d4027688f45c14aa3e1`,
view `f61496ef34e56f813a17ace4795962ff450ed71ec88f1413a62a0e486f85ca16`.
Drift is evidence to assess, never a blind pin replacement or automatic replay.

STOP at G0 after source intake, before implementation:
1. First-product summary: establish existing model reads and exact bounded
   extra cost if needed (page size x one read); do not change protected queries.
2. Keep native ACC007 server pagination; recommend styled pager or a plain next
   link after inspecting current flow. No AJAX/infinite scroll/count changes.
3. Show next expected timeline step only if current map supplies a sequence;
   otherwise report recorded history only. No inferred future status.
4. Preserve live reorder route/behavior and other actions if present; no new
   purchase endpoint. Verify exact action and styling at G0.

D2 is already decided: in_progress gold-soft/warning-fg; in_transit blue-soft/
blue; completed ink2/white/check; cancelled line2/ink3. Present the full group
mapping, including truthful fallback, after fresh source. Same classes on hub,
list and detail; no green. ACC007 map/visibility/order/count/history/comment
logic and model queries stay unchanged. Stored delivery names come from order
snapshots, never current profile. H1 permits only existing DS token value per
convention8; no new permission question. No RD29 work.

## Packet, safeguards and checks

Collector: `diagnostics/RD-18_wp0_collect_20261027.sh`, SHA256
`18348636391bc60acc48f2556f135d1e3663264355b999592d17117744207744`.
Owner packet: `diagnostics/RD-18_wp0_owner-pull_20261027.ps1`, SHA256
`fe60983a3c2765b3005dfb14ea3c86217f4858630590b94488918251fc6a74b8`.
Derived from reviewed, owner-executed RD17 WP0 transport; names, count, exact
file allowlist and metadata route filters changed. No deployment or cleanup of
production source/cache is present. One writer; existing shared files untouched.

Only two task-unique exports are created in `~/private/booster-pulls/`:
`RD-18-source-20261027-r1.tar.gz` and its `.manifest.txt`. Symlink/path checks,
2MiB per file/8MiB total caps, obvious-secret screen (not universal guarantee),
source hashes before/after, archive member hash binding, UTC manifest, exclusive
artifact creation/no replay. Downloads go to
`work/rd18-wp0-20261027/owner-drop-r1/`. Both downloads must match remote hashes
and manifest before exact two-file hash-guarded private export cleanup. On
failure preserve evidence; no retry/delete/reset to bypass the guard.

Local checks: actual Bash syntax/tar/SHA with 30 fictional source files and
mocked metadata PHP; success/missing/drift/existing-artifact cases pass.
Actual native PHP8.0.30 metadata syntax passes. PowerShell5.1 AST plus extracted
exact transport/receipt regex/cleanup body pass LF/CRLF fixtures and failure
stdout/bare-CR tests, using local Git Bash and mocked PHP. The whole owner
packet, SSH/SCP, real metadata/MySQL, keys and production were never invoked.
Default sandbox blocked MSYS IPC before execution; scoped local fixture launch
outside that boundary passed, with first failed fixture retained separately.
No claim of host runtime/metadata or current source acceptance follows.

Expected owner success: `RD-18_SOURCE_READY` and the local drop path. Then
validate exact receipt/manifest/source hashes, screen private source locally,
assess drift and stop at G0. No patch/Apply or business-data mutation occurs.
Independent actual subscription Claude must review this coherent ready source
collector/owner packet and exact hashes before its owner command is released.
No Notion/dashboard/Git write, deployment, agent SSH/key/server access or DB
write is authorized or performed. No extra review round is needed for a full
expected receipt; material deviations follow canonical policy.
