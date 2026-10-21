# Continuation handoff — ACC-008 WP2 and ACC-009 WP3

Date: 2026-10-15
Executor: Codex, continuing the owner's existing assignment; retain the original risky-zone model/effort recommendation, Sol/xhigh. This is customer/address data-preservation work followed by separately gated validation and metadata changes.
Workspace: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`.

## Authority and first action

The owner explicitly requested moving WP2/3 to a new dialogue while the original dialogue prepares the ACC-006 GA4 wishlist correction. This supersedes the original handoff's single-dialogue requirement only. It does not waive sequential package, review, deployment, DB-backup or owner gates.

Read the current root `AGENTS.md`, `docs/BOOSTER_AUTOMATIC_PEER_REVIEW_20261014.md`, the original `handoffs/handoff_ACC-007-ACC-008-ACC-009_account-data-foundation_20261013.md`, and this continuation. Use the independently accepted `diagnostics/ACC-004_customer-data-architecture_report_20261013.md` as the architecture basis. Direct read-only Notion scope fetches, if needed, must use the task IDs/URLs in the original handoff and verify the Roadmap ID; no Notion writes.

Start with **WP2 / ACC-008 only**. First inventory the actual customer/address writers and verify current source freshness. Do not ask the owner again whether WP2 may start: that assignment is explicit. Request missing evidence immediately with one exact minimal hosting command and continue independent preparation while waiting.

## Completed state to preserve

ACC-007 WP1, including its follow-up comment behavior, is deployed and owner-QA accepted. Do not restart it or revise its historical runners/reports. Accepted implementation artifacts:

| Runner | SHA-256 |
| --- | --- |
| `patches/ACC-007_order-status-presentation_20261014.php` | `73d292554bf3f49cca5c13c10551a6b1124390ad527ce71f41012d8ef1fd2221` |
| `patches/ACC-007_notified-history-comments_20261014.php` | `f089e31d3b482c107b2f6a719bf1466ab29b224eb79cfcc76947bb242207e9eb` |
| `patches/ACC-007_history-comment-priority_20261014.php` | `0af604ca61123655c3cc54c054cf9f5d3a82fc8b51b00ac312a742fdeff93de6` |

Later owner decisions supersede the original blanket ban on customer-visible raw comments: display a nonempty owner comment only for a notified history row (`notify=1`); that comment **replaces** the generic status explanation. For `notify=0`, hide the comment. Without an eligible comment, show the standard explanation. Preserve escaping/safety, status/date/pagination behavior and stored order snapshots. The owner confirmed the final priority behavior works. Do not reopen that decision.

The following separate packages are also frozen:

- `patches/ACC-006_wishlist-restore_20261013.php`: `01363fafc078fe7d3c247cf3e3ed1410c5733d54fdc6a55b40fd1c849b40a995`.
- `patches/ACC-006-RD-25_header-media-centering_20261014.php`: `34f2f2d9075e6bb929ea7f9078a69fd1cdfd46dacdfb021afb58c39f6b56e0a7`. Its functional QA items 1–5 are owner-accepted. GA4 is a separate open integration, owned by the original dialogue. Do not infer missing apply/backup output or independent live measurements from owner visual acceptance.

WP2 and WP3 have **not** been implemented or accepted. No Notion/dashboard transition is implied by this handoff.

## Source intake

Use these bounded records first:

- `diagnostics/ACC-007-ACC-008-ACC-009_wp0_source-request_report_20261013.md`: exact package file lists, shared dependencies and the verified 74-file union.
- `diagnostics/ACC-007-ACC-008-ACC-009_wp0_source-intake_report_20261014.md` and its JSON evidence sidecar: 74/74 source files; source archive `booster-debug-files.tar.gz`, SHA-256 `582dcba5debd9989212b1315ea89c112d2ec13fbaf532696df9f5bd9d449e682` at that intake. Verify the actual archive before reuse; the owner can replace it.
- `diagnostics/ACC-004_customer-data-architecture_report_20261013.md`: writer inventory and JSON/snapshot risks.

The October 14 intake is historical source evidence, not proof of October 15 hosting bytes. Several intervening account/header/media patches were deployed. Do not fall back to the October 9 full backup as current source. Compare exact relevant current files; if freshness cannot be established, give the owner one minimal `tar` command for WP2. Extract source safely outside the repository, reject traversal/links/duplicates, and do not execute archive contents. Never request credential-bearing root/admin config files, SQL dumps, logs or customer rows in a source drop.

WP2's 19 candidate source paths and 12 shared read-only dependencies are already enumerated in the source-request report. Confirm actual owning functions and callers before choosing the smaller patch target set. The retained archive does not prove current DB theme/event/custom-field settings; obtain bounded nonpersonal metadata where correctness requires it. A dump required for deployment stays owner-held; do not ingest customer data.

## WP2 — ACC-008, then stop at G2

Prepare one guarded source-only PHP runner and diagnostic report. Reload the persisted `custom_field` JSON **server-side**, then merge only the fields that the particular authenticated form is authorized to edit. Cover storefront profile/address and admin customer/address writer paths. Do not trust client-submitted hidden copies of service metadata.

Preserve `bs_first15_pending`, NP-owned `bs_np_v1`, `rd13_receiver_middlename`, unrelated registered fields and unknown keys. Follow the existing NP ownership/validation contract; preserving JSON must not silently make structured references inconsistent with edited printable address text. Fail safely on malformed nonempty JSON. Distinguish omitted fields from explicit clearing; reject attempts to overwrite reserved keys. No bulk normalization, backfill, DDL or runner DB mutation.

Use fictional round-trip fixtures through the real affected functions and meaningful adversarial cases: profile/admin changes, address edits, unrelated and nested keys, omission versus clearing, reserved-key injection, invalid nonempty JSON, accepted legacy empty shapes and errors without partial writes. Use discriminating tests that fail if preservation is removed. Record source-only/local evidence separately from host/production evidence.

Before delivery, obtain actual independent Claude review automatically through the subscription CLI. Then **stop at G2** with patch/report, exact owner dry-run/apply/cache and rollback instructions, and bounded QA. The owner performs production deploy and QA. Require an owner-held fresh DB dump before applying WP2: restoring source cannot recover JSON data overwritten after deployment. Do not start WP3 until the owner explicitly confirms ACC-008 is live and QA-passed and permits progression.

## WP3 — ACC-009, separate sequential gates

After G2 is satisfied:

1. **WP3a:** read-only preflight and report. Trace ASCII apostrophe handling end-to-end in actual Nova Poshta/checkout consumers before name validation is implemented; source transport review is not live NP acceptance. Do not create NP counterparties, shipments or external messages. Include the admin list/autocomplete raw-JSON union defect at `adminEvhenii/model/customer/customer.php:317` and its actual consumers. Stop for the owner's G3 decision.
2. **WP3b:** one separately reviewed source patch for profile/admin optional fields, controlled rendering and normalization/validation. Keep new fields absent from registration/checkout without changing those protected forms under an inferred grant. Verify actual field enumeration and use an approved bounded design if broader edits would be required. Preserve the ACC-008 merge safeguards.
3. **WP3c:** a distinct metadata-only patch after its own review, a fresh owner-held DB dump and explicit owner `go`. Insert only approved new custom-field metadata/group/language/mapping rows. Never reuse assumed numeric IDs or reactivate sample fields. Record exact inserted identities and rollback SQL; preserve saved contact JSON. No customer/order DDL or historical rewrite.

Do not bundle 3b and 3c. Do not cross any review/deploy/QA gate on the strength of a local test or a prior package's verdict.

## Already approved field policy

Do not re-ask original section 2 owner decisions. Three optional profile/admin fields: patronymic, Telegram and Viber. Registration and checkout do not gain these fields. Existing invalid names remain usable until edited; historical orders never change. Future display uses the order's stored snapshot, not today's customer values. Profile patronymic and delivery-recipient patronymic remain distinct.

- Names: trim; collapse repeated spaces; normalize ASCII `'`, `ʼ`, `’` to ASCII `'`; Ukrainian letters `А–Щ Ь Ю Я І Ї Є Ґ` and lowercase only; apostrophe, hyphen and space only singly between letters; reject Latin, digits and Russian-only `Ы Э Ё Ъ`; preserve case. First/last 1–32, optional patronymic at most 32 after normalization. NP compatibility remains WP3a's prerequisite.
- Telegram: optional; trim, accept an omitted initial `@`, store exactly one `@` plus 5–32 ASCII letters/digits/underscores. Reject `t.me/…`; syntax does not prove account existence or message permission.
- Viber: optional exact `+380` followed by nine digits, from the accepted architecture basis. Do not silently synchronize the main telephone field or claim account verification. Verify the current task scope matches this basis before implementation.

## Parallel ownership and exclusions

The original dialogue owns **ACC-006 GA4 wishlist**. Its known source contract gap and disabled PS event flag are recorded in `diagnostics/ACC-006_wishlist-ga4-diagnosis_report_20261015.md`; that diagnosis is independently accepted, not an implemented fix. Do not edit wishlist/analytics/header/media files, the original dialogue's reports, review packets, scripts or evidence directories.

The owner assigns **new product-photo preparation/upload** to Claude Code. The related context is `handoffs/handoff_RD-27-RD-28_media-standard-and-logo_20261012.md`. Neither this account dialogue nor this handoff assigns its execution. The prior sequencing after current WP1/2/3 remains unless the owner separately changes it. All products are expected eventually to receive transparent primary photos; additional photos can remain photographs. Do not start RD-27/RD-28 or revive old bottom-alignment rules. The pending mobile hero corner improvement belongs to the original UI follow-up, not ACC-008's preservation patch.

Use task-specific work/evidence folders and new runner/report names. Do not mutate a shared review helper or frozen input in another dialogue. Before any cross-cutting dependency is edited or held frozen for review, coordinate ownership through the owner; read-only access does not grant a competing writer. Preserve unrelated local changes.

Never touch protected payment/mail/fiscalization/NP business logic, order status definitions/IDs, processing settings, direct SQL status writers, auth/registration tokens, SEO/Merchant/schema, `.htaccess` or shared CSS unless separately authorized. Do not commit, push, deploy, publish, write Notion/dashboard statuses or run live business-data mutations.

## Review and delivery requirements

Read the current automatic-review policy; use actual installed Claude CLI with saved Claude.ai subscription auth. Never use direct paid API, `.env.review`, `scripts/auto_review.py`/`bsreview`, an API-key fallback or `--bare`. Verify executable/adapter identity, subscription/no API-key auth, stripped provider environment, tools/MCP/hooks disabled, and reject paid overage. Required risky design/CODE review uses actual Opus/high. Supply sanitized code and fictional tests, never private customer/business/payment data.

The completed prior task-scoped adapter `work/acc006-ga4-diagnosis-20261015/send-cli-review.py` documents verified reuse of `work/header-media-centering-20261014/send-claude-cli-review.py`. These consumed task packets/outputs are references, not reusable dispatches. Build and verify your own task-scoped context adapter; preserve auth/launch controls and fresh request identities, freeze only your declared inputs, capture the original process/full verdict, read it completely and verify unchanged inputs afterward. Do not overwrite another dialogue's transport or imply a diagnosis verdict accepts new preservation code.

All runners must remain PHP 8.0.30-compatible, with file/anchor gates, per-file idempotence, backups, lint/restore, rollback and self-delete. No mysqlnd-only APIs (`get_result`, `fetch_all`). No new Twig include without actual ArrayLoader proof. Cache locations come from config, never a guessed path. Existing CSS tokens, if ever authorized, are read/validated then replaced wholesale, not appended or hardcoded as a prerequisite.

UI acceptance, if changed: mobile/tablet/desktop, long text, hover/focus/active, profile/admin and account/auth/address paths. Required Tier 1 URLs use the current verified list; the owner says `FIG-CHARM-001` is retired, so select a currently valid 3D product from the live catalogue instead of claiming that URL passed. Open checkout entry without placing or paying for an order.

End the active package at its gate with the outcome, local artifact links, actual review/test boundaries, owner commands and concise QA. Local source tests, independent review, production deployment and owner acceptance are distinct claims.
