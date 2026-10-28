# RD-18 WP2: owner-rejected detail visual; repair and photo-source intake

Date: 2026-10-27. Executor: owner-assigned Codex.
Status: OWNER_QA_REJECTED_LOCAL_VISUAL_REPAIR_PHOTO_SOURCE_PENDING.
RD17 remains accepted. RD18 r3 is deployed but final owner QA is rejected.
No production repair, rollback, new deployment, Notion/dashboard or Git action.

## Confirmed cause and reference identity

The owner supplied desktop and mobile order-detail HTML exports. Their x-dc
visual markup is unchanged from the registered A-Order/A-Mobile-Order boards.
Only the exported token preamble and local React dependencies differ. This is
an implementation fidelity defect, not a newly changed design specification.
Reference archive SHA256:
- desktop47b849e4c88f2e0d64345969495c58b972c32a2465905b8689d2ce9c9e47cc5d;
- mobile7e17331211a9f18da63b764916d7ce10a9e618a9e21b809746816fe3d8908563.
Full provenance/diffs: work/rd18-visual-20261027/reference-intake.json and
desktop-reference.diff/mobile-reference.diff. Board notes are reference data;
the owner's D2 mapping and accepted G0 functional exceptions still govern.

Owning source defects in the deployed r3 payload:
- order_info.twig stacks history and products, then puts summary/delivery in
  a separate bottom grid. The desktop board puts summary/delivery beside them.
  DS7607's bs-order-detail-grid styles only that bottom pair; it cannot create
  the approved two-column body.
- The heading's created date sits under h1 with a space-between badge at the
  far edge, unlike the compact desktop heading and mobile two-line arrangement.
- bs-order-current is an expanded duplicate state card before the timeline.
  DS7594-7595 uses a thin rail/12px hollow dots and status pills in every row,
  rather than the board's22px recorded-event markers and plain event titles.
- Products have no thumbnail slot. Generic bs-account-link imposes44px on
  product titles; mobile DS7634 moves quantity/price below the description.
  Large repeated action buttons and verbose metadata further inflate cards.
- Panels use10px radius/border, rather than the14px/shadow board cards.

Prior override search found one owned RD18 source block and its generated
runner contracts. Repair edits that block in place, removes superseded detail
rules and keeps hub/list/shared-shell source bytes intact. No !important,
timer, absolute/fixed positioning or second corrective override block added.
Page-local shell spacing/current-chip modifiers are necessary to avoid changing
the accepted RD16 hub/list mechanism. New dimensions are annotated from the
board or44px control target. All used CSS variables exist live; the board8px
corner uses existing --bs-s2, not an invented missing --bs-r-btn token.

## Local visual candidate and boundaries

Known deployed r3 after-payloads are the base, confirmed by the exact accepted
owner Apply receipt. All future repair targets/dependencies still require fresh
host Verify before writes. This local source is not a new live export.

Candidate work/rd18-visual-20261027/tree-after restores:
- desktop primary history/products and300px secondary summary/delivery columns;
- natural responsive wrapping;390px cards in history/items/summary/delivery order;
- mobile24px heading,14px cards,64/56px thumbnail frame and22px timeline markers;
- quantity below the title and line total aligned at right;
- native per-product cart/return as44px labelled icon controls beside each total;
- all native hidden form fields, complete inline native handlers, protected
  reads/pagination/status data and stored recipient/totals unchanged;
- current state/date/message and payment/invoice in accessible disclosures;
  actual payment method remains visible in its summary. Option/subscription
  feedback stays outside collapsed elements; notified history comments remain
  visible and escaped. Current state is not falsely assigned to a history row.

Accepted functional deviations from illustrative boards remain: no invented
future step, no new bulk reorder, D2 completed dark with check/no green status
badges, recorded actual comments/totals/snapshot/technical links preserved.
Purchase controls retain the DS purchase color. Existing shell extras and real
content can affect height; no pixel-perfect or live authenticated claim.

Native PHP8/Twig/current controllers17before/after contexts generated. Real
Chrome48order+15hub layouts at390/768/1280 pass, plus exact FormData5pairs,
three loopback fake cart POSTs and native history page2. Geometry assertions
check desktop summary aligned beside history, mobile card order, thumbnail
sizes, mobile heading, card radius and collapsed duplicate-state disclosure.
Fixtures use fictional rows/registry/auth/models/header; no real cart/provider
or customer write. Actual attached DC runtime/React rendered locally; all
external HTTP blocked except locally fulfilled font CSS. Screenshots inspected
by executor, reference at390/1280 plus interpolation checks at768. Initial
prototype authoring hit the Windows default text encoding; shared files were
restored and UTF8 byte-preservation/form/script checks passed before testing.
No affected production file was edited by those local operations.

## Owner photo decision and minimal missing sources

Owner explicitly chose current catalog photos, placeholder for removed items.
No stored-order image field is invented and no current catalog name/price may
replace stored order data. Current local candidate uses the placeholder only;
photo data plumbing is not yet implemented or reviewed for deployment.

Needed fixed source files:
1. catalog/model/catalog/product.php;
2. catalog/model/tool/image.php;
3. system/library/image.php.

These are missing from the current admitted source set. The collector also
reads enabled technical event pairs through the corrected r3 predicate plus
native catalog/product.getProduct and tool/image.resize before/after forms;
it hashes the five existing routing core files and records only an image-root
mapping boolean. This anticipates getter/image hooks without assuming they are
absent. It executes no callback or catalog/image method and reads no customer,
order, product or provider rows. Any newly exposed handler needs assessment,
not an automatic allowlist. Source bodies are checked for obvious secrets;
that screen is not a guarantee that arbitrary source has no secret.

Source-only pair:
- diagnostics/RD-18-WP2_photo-source_collect_20261027.sh;
- diagnostics/RD-18-WP2_photo-source_owner_20261027.ps1.

Exact identities are recorded in work/rd18-visual-20261027/photo-source-facts.json.
Creates only two private task-unique archive/manifest files outside webroot;
downloads/hash-checks them, then removes only those two exact exports. No
source/DB/template/image-cache write or deployment. Existing local drop refuses
replay. Collector captures source pre/post hashes and archived-file hashes.
Transport is the accepted WP0 transport with exact names/list/hash/count changes.

Local checks: native PHP8metadata lint; exact extracted metadata helper with
fake mysqli/event rows and5synthetic core files, new dot/legacy/wildcard model
hooks, redaction/caps/finallyrollback; real Bash/tar/source/archive hashes on3
synthetic files, missing/drift/existing-export stops; full packet mocked before
any SSH/SCP invocation under PS5.1+7, verified downloads/manifest/cleanup and
existing-drop refusal. Bash required normal local execution because the Windows
sandbox blocked MSYS named objects; only local fixtures ran, no transport/DB.

## Next gate

Integrated actual subscription Claude review of this coherent source-only
collector/owner packet/checks/receipt/private cleanup contract before owner use.
This is a required ready-stage review of the new evidence collector, not an
extra early design or actual-result review. It grants no repair/deployment.
After owner source intake, complete actual photo path and guarded UI repair,
run affected interaction checks and one integrated ready review of the final
patch plus exact owner packet before deployment and owner visual QA.

## External review prelaunch block

Both launches were rejected by automatic approval review before any native/provider request. Initial concern: private server code/related artifacts external transfer. Materially reduced successor excludes internal access/policy/handoff bodies and any actual server file body;58155bytes contain only new diagnostic/owner commands and synthetic tests. Inventory: work/rd18-visual-20261027/review-transfer-inventory-r2.json. The second rejection nevertheless requires specific explicit owner permission for this exact transfer. No bypass/indirect execution or further attempt. Exact review bundle SHA256 b242e4cab8b2b0fef19fb8d83769dc5f46c114584739005e44c4cde06689d8d5.

Collector is NOT ReviewOK; no owner-use command released. No real source collection or production repair occurred. Local visual/source checks preserved; comparison work/rd18-visual-20261027/comparison.html. NEXT: exact owner external-transfer decision, then fresh request identity if granted. After source-review acceptance, owner collects3files; final photo/UIrepair receives its integrated ready review before deployment.
