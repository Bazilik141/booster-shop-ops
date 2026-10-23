# ACC-009 WP3c: metadata committed, packet notice-count STOP assessed

Date: 2026-10-23
Disposition: METADATA_APPLIED; OWNER_CONTACT_UI_QA_PENDING.
Original owner packet status remains STOP, not rewritten to OK.

Owner Apply receipt UTC2026-10-23T17:29:13.9057524Z:
work/acc009-remaining-repair-r5-20261023/owner-receipts/WP3c/Apply-STOP-e69d460043a742e29c7e082ae9508076.json.
SHA256 a83d069f5058284829fd202cb998263f9ad91a4aeab88c7508558bfa599c4179.
Exact reviewed runner/packet pair matches actual r5 release. Exit0, structured
runner status OK, action metadata_applied, changed_files0, business_rows_changed0,
db_access METADATA_ONLY_INSERTS, business_API_calls NONE, self_deleted true.

Inserted field identities: patronymic29, telegram30, viber31; setting11390;
customer groups1/2/3. after_metadata_sha256
011f2848e87eb7f97a4e934f5c1f68137a8c78c0db67ec420b4844fef55f63f8.
Backup /home2/boosters/_patch_backups/ACC-009-WP3c-20261023T172915Z-df522ee8.
No customer/order JSON changed by the metadata runner. Source_changed=null is
the generic initializer for this metadata path; changed_files0 is expected.

## Exact variance and why this is not commit uncertainty

stderr consists of exactly two identical lines, and nothing else:
WARNING: MYSQL_OPT_RECONNECT is deprecated and will be removed in a future version.

The reviewed Read-Result already allowlists that exact notice, but permits only
one stderr line. It checks multiplicity before parsing JSON; that alone caused
the client STOP despite remote exit0 and complete successful result.

Retained exact runner4f6b5a180caeb5e72f37eb3df14d7bbc36c401372632f9f98dfe369db460878c:
ar_admit lines402-404 opens/closes its metadata-read connection; ar_metadata_apply
line511 opens a second connection for the metadata transaction. Source-only
Apply/metadata Preflight used only the first. Two identical notices are consistent
with these two intended connections, not a new business error.

ar_metadata_apply validates exact inserted delta and unchanged prior registry,
writes rollback SQL/commit-pending journal, calls COMMIT at543, writes committed
journal at544 and only then returns metadata_applied at545. If commit outcome or
journal write fails, this path emits STOP, not OK. Entry self-delete at582 also
succeeded. Therefore the supplied receipt unambiguously reports completed
metadata execution; this is not a partial/unknown transaction or rollback case.
No separate model actual-result call is necessary to repeat this deterministic
classification. This assessment does not claim a new independent review.

## Mechanical assessment and preservation

assess_WP3c_apply.ps1 extracts the exact native PS5.1 release/result functions,
reproduces the original rejection, requires exactly two copies of the already
allowlisted notice and no other stderr. It explicitly projects only that observed
notice multiplicity to one for the unchanged full remaining result validator.
All other identity/runtime/admission/metadata-effect/backup/self-delete checks
pass. Observed inserted IDs/setting and after hash recorded separately.

The original receipt, stderr, marker and reviewed bytes are untouched. No
Apply-OK.json is fabricated. The assessed copy is Apply-assessment.json with
original_packet_status STOP and explicit normalization scope, not a claim that
the original validator accepted it. No agent transport/server operation occurred.

Do not rerun Upload/Apply, reset the marker or run rollback. Runner self-deleted
after commit. A future substantive packet-template revision should bound this
known notice multiplicity by mode/connection scope, with discriminating tests;
do not alter consumed frozen packets or loosen arbitrary stderr now. The earlier
fixture authored empty remote stderr and did not exercise this notice-count case.

## Remaining owner acceptance

Refresh account edit. Confirm all three optional fields appear, blank save works,
filled values survive save/reopen, and the same customer values display in admin.
Verify new Telegram/Viber controls do not leak into registration/checkout; the
already existing delivery patronymic is intentionally unaffected. No new order,
shipment or copied customer values in chat are needed. Profile/contact functional
acceptance remains pending until owner reports results; no canonical roadmap or
Git status mutation follows from this receipt assessment.
