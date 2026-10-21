# ACC-009 admin courier address-prefill CODE candidate C2

Date: 2026-10-18
Executor: Codex, inherited model/effort. Risk: Nova Poshta, order snapshot, external shipment creation. Local candidate only, no CODE verdict or production release.

The owner authorized preparation of this separate fix. It composes only on the exact N3 after-image SHA2138e15643ce4bced1b49698955c78f443617e4b59877325113261042d1c7b93. Current hosting is N1; N1 complete QA and N3 recipient AND sender effects acknowledgement/apply/QA remain prerequisites. C2 does not implement/release N3 itself. No agent hosting/NP/DB/Git/Notion/dashboard writes occurred.

## Cause and change

makeDefaultFormData currently parses shipping_address_1 for courier street/house/flat. That column is a service placeholder for structured checkout addresses; the actual tuple is bs_np_v1, with the printable address in shipping_address_2. The current OpenCart textarea also follows a shipping_address_format that can omit address_2 entirely.

C2 uses valid version1 courier metadata first. Bounded private SELECTs (LIMIT2) resolve area/city/street refs and hierarchy against existing local directory tables, and display current raw directory labels. Present malformed/stale metadata produces a fixed warning, with no fuzzy legacy fallback. Metadata-free legacy addresses use only the known address_2 grammar and unique city/area/street resolution; unresolvable data stay manual. House/flat remain strings including007/02/0. Flat is optional. The summary explicitly uses known order address columns including address_2, rather than an arbitrary omission format.

Recipient POST area/city/street/house/flat and summary decode Request-cleaned leaves once. New lookup/display copies are raw, then properly HTML-encoded once with double_encode=true; N1's separate name-display convention remains unchanged. Explicit POST corrections, including an empty flat, remain authoritative. Sender input normalization and names policy are unchanged.

The existing N3 name lookup still picks city rows[0]. C2 does not repair that model or sender lookup. It may display a typed ref's duplicate-name city with a fixed warning, but a private uniqueness/hierarchy/round-trip guard runs before ANY external sender/recipient preparation and refuses ambiguous courier POSTs. A duplicate name cannot be made safe by merely pressing Save on the prefilled form; correction of the underlying lookup is a separate scope. All new warnings are fixed strings without customer/location interpolation. No extra remote API calls, directory/model writes or order edits are added.

## Exact verification

`diagnostics/ACC-009_admin-prefill-code_evidence_20261018/verification.json` binds source/after-image/harness/runner evidence and records54 local assertions:28 actual controller helper/whole GET/guarded POST cases,12 actual admitted Twig3.18 template/HTML parser checks including quotes/literal entities/textarea injection, and14 backup/transform/idempotence/lint/restore/rollback helper fixture checks. All SQL, API reads/writes and service effects are fictional. No full production CSS/browser or hosting PHP8.0 proof is claimed.

Only existing createInternetDocument and makeDefaultFormData bodies differ; eleven protected existing methods remain byte-identical after method extraction, including sender/recipient preparation, API payloads, N3 saveDoorsAddress, validation and N1 name defaults/display. New private helpers supply the limited read-only address contract. The private SQL table/column names are supported by admitted area/city/street sources; actual schema/runtime admission and current admin model identities remain release gates.

## Candidate and gates

`patches/ACC-009-C2_recipient-address-prefill-HELD_20261018.php` reuses the verified N3 runner/admission helper lineage with a new exact N3-only whole-file anchor and C2 payload. ALL mutation modes, including rollback, are explicitly HELD at the CLI entry. Its existing internal rollback helpers are exercised only in a fictional sandbox. No owner run/apply instruction is released. Source-only dry-run cannot grant N3 QA, effective runtime, database schema or shipment safety acceptance. Fresh host admission, actual Claude CODE/result review, owner sequence/QA and a separately reviewed release edition precede apply.

Actual automatic CODE C1 review request144d9970-a6c1-43c6-a885-e19e4b44837d failed before model evaluation with first-party401 expired subscription OAuth. It is consumed and must not be replayed. Owner reauthentication via existing Claude Code /login is pending. C2 is ready for inclusion in a fresh coherent independent CODE request once authentication is restored; no fabricated verdict, API-key fallback or paid overage.
