# RD-17 WP1A r3: owner Upload/Verify handoff

Date: 2026-10-26. Executor: Codex, already assigned by owner.
Actual independent reviewer: claude-opus-5-5 / high.
Request: f7518901-281c-4d55-a1ea-a69fa9aa1092.
Verdict: Review OK for owner Upload/Verify only; no mandatory findings.

Exact runner `patches/RD-17-WP1A_profile-receiver-r3_20261026.php`, SHA256
`ef0d7a7d87c58340b73cfdbb0fc26a497920adb7c38eeceaf8913dbe803f8e85`.
Exact owner packet `diagnostics/RD-17-WP1A_owner-r3_20261026.ps1`, SHA256
`0a2b8c0935a56197398268ba549857bf07badeae722f7b8c5c5ac1bfd6912712`.

The successor preserves all seven reviewed payloads and all guards. It admits
the exact owner-applied/current Mono diagnostic instead of the stale old pin.
New private stage and local state isolate it from the old uploaded pair.
Use only this new packet. The existing old runner/state remain preserved.

## Current owner action

In PowerShell, run the tested Windows PowerShell5.1 interpreter:

```powershell
$Rd17Packet = 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-17-WP1A_owner-r3_20261026.ps1'
& 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' -NoProfile -File $Rd17Packet -Step Upload
if ($LASTEXITCODE -eq 0) {
    & 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' -NoProfile -File $Rd17Packet -Step Verify
}
```

Expect RD17_WP1A_UPLOAD_OK then successful read-only Verify with admission SHA,
syntax_gate=ok, production_writes=0, backup=none, done=ok, RD17_WP1A_VERIFY_OK.
Return complete output before Apply. Any STOP/error/incomplete output is HELD;
do not retry, edit pins or run Apply. No stage/cache/key cleanup is required.
Upload/Verify do not deploy the seven production targets. Backup/rollback and
all target before/after hashes remain in the existing WP1A report.

## Required Mono lifecycle sequencing note (review O-1)

Keep the current exact Mono diagnostic installed through WP1A Upload/Verify/
Apply. This task does not remove/rollback it. A change before Apply produces a
new admission STOP; reassess it, never bypass the hash check.

After WP1A Apply, the old Mono removal/rollback/recovery packets' exact parent
pins for Confirm/checkout.twig/readiness are stale. Do not invoke them manually.
A later owner-authorized Mono cleanup needs a reviewed compatible successor
against the post-WP1A hashes, or can follow an explicitly selected reviewed WP1A
rollback. No automatic WP1A rollback or Mono cleanup is proposed or authorized.
Record this dependency in any later Mono handoff. Source rollback here never
rewrites Mono, profile/customer data or past order snapshots.

Owner Apply/receipt/QA, address WP1B and all RD18 work remain pending. No agent
SSH/key/server, Git, Notion/dashboard or live business write is authorized.


## Owner Upload/Verify closure; next single Apply

Complete owner Upload/Verify receipts accepted mechanically on 2026-10-26.
PHP8.0.30, unchanged reviewed pair, syntax_gate=ok, production_writes=0,
backup=none, done=ok, and uploaded/verified state agree. Admission digest:
4c0d695d63f276d126c56ff0477ddae477f12d445a7cf4c22cc4b8d732eb3ef1.
No deployment or owner QA yet. Do not repeat the Upload/Verify commands above.

Next owner command (writes the admitted seven targets; backup/cache ordering
and one-use replay guard are already reviewed):

```powershell
& 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' -NoProfile -File 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-17-WP1A_owner-r3_20261026.ps1' -Step Apply
```

Run once and return full stdout. Expected final marker is
RD17_WP1A_APPLY_OK_OWNER_QA_REQUIRED, with backup path, seven changed files,
self-deletion and template-cache confirmations. A failure/incomplete receipt
is unresolved: do not rerun, automatically rollback or edit state/pins. No real
order/payment/TTN or future Mono cleanup is authorized by this command. Owner
UI QA follows receipt acceptance; address WP1B/RD18 still wait.


## Apply receipt accepted; do not rerun commands above

Complete owner Apply accepted mechanically: seven exact afterhashes and backups,
same admission, PHP8.0.30/lint, self-delete, done=ok, 81 compiled templates
removed, cache cleanup and staged-r3 absence confirmed by packet. State records
applied=true and applyAttempted=true. Exact emitted backup directory:
`/home2/boosters/public_html/_patch_backups/RD-17-WP1A_profile-receiver_20261026-20261026T180939Z-9883d4aa4c3b`.
Source deployment is accepted from the owner receipt; authenticated live UI/FPM/
provider behavior and owner QA are pending. See the current WP1A report for
four numbered manual checks. No repeat Apply, Upload/Verify, cache cleanup,
automatic rollback or old Mono recovery wrapper. Address WP1B and all RD18 work
still wait for the applicable owner QA. No extra real order/payment/TTN is needed.
