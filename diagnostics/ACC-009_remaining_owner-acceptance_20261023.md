# ACC-009 remaining three-package scope: owner acceptance

Date: 2026-10-23
Disposition: THREE_PACKAGES_APPLIED_AND_OWNER_QA_ACCEPTED.
This is implementation/owner-acceptance evidence, not a canonical Notion or
dashboard status transition. No Git commit/push in this closure turn.

## Delivered and applied

| Part | Accepted effect | Private server backup |
|---|---|---|
| WP3b |9 source targets, profile/admin contact support; owner account/admin page smoke accepted | /home2/boosters/_patch_backups/ACC-009-WP3b-20261023T165910Z-3a2e46e2 |
| N2 |18 source targets, agreed Ukrainian name policy; owner focused name-input smoke accepted | /home2/boosters/_patch_backups/ACC-009-N2-20261023T170646Z-c746055b |
| WP3c |0 source targets, three optional profile/admin field definitions and service mapping inserted; final owner functional QA accepted | /home2/boosters/_patch_backups/ACC-009-WP3c-20261023T172915Z-df522ee8 |

The actual r5 independent review is request028ece35-43a3-41e8-8357-257e5e366d41,
Claude Opus5-5/high. Exact retained r4 runners plus r5 owner packets are bound by
diagnostics/ACC-009_remaining_repair_r5_evidence_20261023/review-release.json and
diagnostics/ACC-009_remaining_r5_acceptance_20261023.md. No reviewed executable
was renamed/rewritten after acceptance. Release conditions R1/R2 closed there.

All server operations were owner-run. Agents performed no SSH/key/server calls,
deployment, production data writes, dump ingestion or provider calls. Each runner
self-deleted after its successful effect. Preserve all consumed Apply markers,
old receipts and backups; no package needs Upload/Apply again.

## Final owner QA

Owner explicitly reported all four requested checks OK:
1. Profile displays patronymic, Telegram and Viber; empty fields save successfully.
2. Filled values survive profile save/reopen.
3. Admin can read/edit the same fields; its write appears in the account profile.
4. New Telegram/Viber controls are absent from registration and checkout.

The attached registration screenshot corroborates that page's field scope.
Checkout and save/readback results are owner attestations, not agent browser
execution or screenshot proof. No contact values/customer records copied.
Existing delivery patronymic remains in its previously accepted delivery flow.

Record: work/acc009-remaining-repair-r5-20261023/owner-receipts/WP3c/owner-final-QA-accepted.json.
Earlier bounded acceptance records are under WP3b/owner-smoke-accepted.json and
N2/owner-smoke-accepted.json in the same receipt root. Broad source/local tests
and their fake boundaries remain in the r3/r4/r5 reports. No exhaustive all-form,
all-device, crafted HTTP or NP-provider semantics are inferred from this QA.

## WP3c notice-count variance remains truthful history

WP3c server execution succeeded exit0, metadata_applied, fields29/30/31,
setting11390, business_rows_changed0 and self_deleted=true. The local packet
returned STOP solely because the known MYSQL_OPT_RECONNECT notice appeared twice
while Read-Result allowed at most one. Complete assessment:
diagnostics/ACC-009_WP3c_applied-stderr-assessment_20261023.md.

The original STOP receipt/marker is preserved. No Apply-OK receipt was invented,
no remote retry/rollback occurred and no changed bytes received a reused verdict.
Mechanical assessment required exactly two identical already-allowlisted notices,
then checked all remaining result conditions with an explicitly documented audit
projection. Final owner functional QA now independently confirms field operation.
There is no outstanding transaction/activation ambiguity. Future reusable packet
work should address bounded known notice multiplicity with discriminating tests;
do not modify or rerun these frozen consumed packets to repair history.

## Remaining boundaries

No further production apply is required for this three-package scope. Do not
manually alter/delete the mapped Custom Fields through the generic admin UI;
their strict registry is an accepted dependency. Functional profile field values
remain editable in the intended profile/customer forms.

SimpleCheckout retirement remains a separate NOT_IMPLEMENTED proposal at
plans/ACC-009_SimpleCheckout-retirement_scope_20261023.md. New visual design is not
part of these packages. Git/status work retains its explicit authority rules;
this document does not claim that canonical task status was changed or that
unrelated outstanding ACC-009/history scopes were newly accepted.
