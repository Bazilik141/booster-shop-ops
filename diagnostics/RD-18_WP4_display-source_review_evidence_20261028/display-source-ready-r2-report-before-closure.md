# RD-18 WP4 — source-only prerequisite for seven owner QA changes

Date: 2026-10-28. Executor: Codex. No production change is released here.

WP3 owner Apply was accepted mechanically from the complete local receipt:
`work/rd18-followup-20261027/owner-receipts/Apply-20261028T0658580010198Z.txt`.
Nine targets matched, template cleanup succeeded, runner self-deleted. The owner
reported seven further QA changes; final RD-18 owner QA remains open.

## Approved outcome and architecture choice

Hide the absent-invoice TBC placeholder, use the new future bank-instruction
phrase, show compact stored delivery details and NP logo, move tracking into a
real top-right button, keep compact NP state/TTN visible with history collapsed,
remove the manual refresh/hint/title and split support into three lines with a
phone link. Existing authentication, status visibility/pagination, name rules,
NP API/cache limits, writers and no-DB-write boundary remain protected.

Owner delegated the old-comment decision. Selected: edit the future UK bank
language template only; preserve historical stored comments and their display.
No database rewrite and no rendering-time phrase substitution. This avoids
rewriting audit history or adding permanent compatibility logic to the renderer.

## Missing evidence and owning path

Bank transfer confirm loads `extension/opencart/payment/bank_transfer` and uses
`text_payment` when composing the future notified instruction. The controller
also contains financial requisites: its body is excluded from collection and
external review. Only its SHA-256 is requested. Need current UK translation
file(s), native language loader/startup and optional event source to establish
which translation owns the displayed phrase. Read-only hook metadata includes
both new bank-language before/after edges; unknown callbacks get no admission.

Current `rd18Delivery` honors `shipping_address_format` and does not read the
stored NP labels. Checkout snapshot contracts preserve `bs_np_v1.labels` and
address_1/address_2. A city-only format or incomplete snapshot can explain the
screenshot. One owner-selected order is inspected privately to distinguish
these cases; export only boolean/type/shape flags, no ID or address values.
Final formatter must use the order snapshot, not today's profile/address book.

## Exact bounded collection

Required source: `system/library/language.php`,
`catalog/controller/startup/language.php`,
`catalog/controller/startup/extension.php`.
Optional: `catalog/controller/event/language.php` and `system/config/catalog.php`
if present. The latter is technical pre-action configuration, not the private
root `config.php`. The 2026-10-23 owner-supplied native startup/extension source
confirms language addPath registration there. Fresh source is still required.
If optional pre-action source is absent, do not claim startup order verified;
resolve the actual registration/pre-action chain before implementation release.
Discover up to eight `bank_transfer.php` files under `extension` and
`catalog/language`, depth <=12, storefront `catalog/language/uk-ua` paths only;
admin translations excluded. Total 4..13 files, <=2 MiB each and <=8 MiB total. Discovery errors, links,
missing sources and obvious key/private-key/IBAN signatures stop without values.
The signature screen is conservative and is not proof that arbitrary source is
free of secrets. Inspect collected source locally before any later transfer.

Hash only: five native event/loader routing files; nine current WP3 targets,
ACC-007 map and the bank controller. No raw bank controller/config/order data
is archived. Existing event predicate is reused with two language edges added;
this is source metadata, not a future runtime admission or callback execution.

One mandatory integer `OrderId`, prepared SELECT of only
shipping_address_1/address_2/address_format/custom_field, LIMIT 1 in a read-only
transaction. `bind_result`, integer DB_PORT, PHP8.0, rollback even on failure.
No name, phone, email, comment, payment, total or product fields selected. Only
presence/format placeholders/known NP type and metadata-shape flags exported.
`format_nonempty` distinguishes empty/null native-fallback format from a
city-only explicit format; `format_has_city` is also boolean. Truncated/false
fetch results fail closed, and the statement closes on execute/fetch failure.

Two unique private export files plus up to two private parent directories are
created. Downloads must match remote hashes and manifest before exact two-file
nonrecursive cleanup. Any failure preserves artifacts and refuses replay.
Cleanup guards use explicit exits. A failed tar/drift or interrupted transport
may leave private partial artifacts: preserve them for assessment, and use a
separately reviewed exact cleanup/recovery packet; do not blindly replay.
No source/DB/cache writes, application, NP API requests or agent server access.

## Local evidence and limits

Exact embedded PHP lint and native functions tested on PHP8.0 with fake mysqli
and statements: three delivery types, nested string/map, missing record, invalid
and oversized JSON, unknown type, schema/statement/truncated/false-fetch failure,
row and relevant-event caps, empty/null/city/address formats; rollback and
no-value-output checked (17 cases).
Real Bash/tar/SHA on synthetic files and fake PHP: success, source missing,
drift, existing artifact, key literal, IBAN literal, bank source missing, file
count cap, discovery failure, invalid argument, optional sources/admin exclusion,
private-key/sk-key and spaced-IBAN stops pass (14 cases). Native Unix
symlink execution is NOT certified: Windows MSYS ln copied the file; recorded
NOT_TESTED rather than PASS. Component-link guards remain explicit in code.
Full owner packet runs with SSH and SCP both replaced before execution under
PowerShell5.1 and7: argument substitution/validation, download hashes, manifest,
cleanup, replay refusal pass. Six negative packet cases also pass under each
version: collector hash, receipt, archive hash, manifest hash, manifest line and
cleanup failure. All refuse before inappropriate cleanup/transport continuation.
`run-source-checks.py` copies each actual version result to its named evidence
file; its body is supplied for review. No real network/DB/config in local tests.

NOT_TESTED: real PHP main-block config/runtime/root/argument path against hosting;
real byte-stream SSH process transport; native Unix symlinks. These are owner
runtime gates or prohibited agent server actions, not local proof. Cosmetic
legacy Rd18Photo fixture names, image-root metadata and packet indentation have
no semantic impact and are retained. No claim that every secret form is screened.

Tests establish local behavior, not current hosting files, actual schema,
language resolution, cache behavior or production QA. Integrated independent
Claude review of the collector/command pair is required before owner release.
The later UI/payment-language implementation requires its own coherent final
ready review after current evidence arrives. H1 token-value-only grant remains.

## Owner result contract (held pending review)

Run the owner packet once with `-OrderId 462` (order shown in the owner's QA).
Expected `RD18_WP4_SOURCE_READY`; local drop
`work/rd18-wp4-20261028/owner-display-drop-r1`. No Step/Apply at this gate.
If STOP, preserve the receipt/error and assess; do not retry automatically.

## Source-only review repair

R1 request `cff70f3c-c65c-4755-83bd-2a3df41c8f3e` returned Changes requested:
M1 empty versus city-only format ambiguity. Original 41 frozen inputs were
verified and preserved in `work/rd18-wp4-20261028/reviewed-r1-inputs` before
changes; original response and byte-bound packet remain immutable.
M1 fixed and discriminating tests added. O1 registration + optional pre-action
sources added; O2 admin excluded; O3 spaced IBAN screen; O4 explicit cleanup
checks; O5 strict fetch result; O6 cheap secret/failure tests added and remaining
native/main-block boundaries explicitly NOT_TESTED; O7 partial-artifact recovery
and per-version copying provenance recorded. No server run occurred. R2 must
review both new hashes before owner release. Future UI is still held for source.
