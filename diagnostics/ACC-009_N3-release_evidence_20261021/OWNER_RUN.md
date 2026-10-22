# ACC-009 N3 owner run

Reviewed together: actual Claude Opus/high request
`3ffc97a0-5539-4389-a73c-d9bff7289f6a`, REVIEW_OK, no mandatory findings.

Runner: `patches/ACC-009-N3_doors-helper-private-release_20261021.php`
SHA256 `f97f09a82048be1c4efc2fcff875537fa9023c017307153a129f93761b3d68bc`.

Packet: `diagnostics/ACC-009-N3_owner-PS_20261021.ps1`
SHA256 `702dc0d0fb0bca43df30c5ed39c2b0891ab50306bb0d0db55295eaae061bdd93`.

The owner alone executes every command below, in the same normal PowerShell.
Agents never execute this packet or SSH/SCP/key tools. Do not run steps in parallel.
Use the existing reviewed access helper's Status; Unlock only when the key is
not loaded. Keep the passphrase in the native local prompt, never in chat.

```powershell
Set-Location -LiteralPath 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\scripts\booster_ssh_session.ps1' -Action Status
```

If the status confirms the key is absent and the service ready:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\scripts\booster_ssh_session.ps1' -Action Unlock
```

Upload only after the existing key is available:

```powershell
$packet = Join-Path (Get-Location).Path 'diagnostics\ACC-009-N3_owner-PS_20261021.ps1'
if ((Get-FileHash -LiteralPath $packet -Algorithm SHA256).Hash.ToLowerInvariant() -cne '702dc0d0fb0bca43df30c5ed39c2b0891ab50306bb0d0db55295eaae061bdd93') { throw 'Packet SHA256 mismatch: STOP' }
powershell.exe -NoProfile -ExecutionPolicy Bypass -File $packet -Step Upload
```

Only after `step=Upload status=OK exit=0`:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File $packet -Step Verify
```

Only after `step=Verify status=OK exit=0`:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File $packet -Step Preflight
```

Send the complete Preflight technical output for deterministic admission before
Apply, especially its `web_runtime` and `action` lines. Retained expected root
runtime: PHP8.0.30, litespeed, opcache_enabled=true, validate_timestamps=true,
revalidate_freq=2. The exact target before SHA must match the runner.
Any unexpected runtime/action/hash difference is STOP for a scoped assessment.
Expected complete receipt needs no new model review. Do not repeat N1 QA.

After expected Preflight is accepted, within30minutes, as a separate invocation:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File $packet -Step Apply
```

Expect `step=Apply status=OK exit=0`, one changed controller, exact backup path,
source after SHA2138e15643ce4bced1b49698955c78f443617e4b59877325113261042d1c7b93,
successful lint, `self_deleted=yes`, `done=ok`. No DB writes, NP API calls or cache
cleanup. Receipts are saved under `work/acc009-n3-release-20261021/owner-receipts`.
Send complete technical output; do not send customer or account data.
Wait longer than the reported OPcache revalidation interval before later N3 QA.

On any failure: STOP. No automatic Apply retry/re-upload/rollback. The attempted
Apply marker intentionally blocks further packet steps. Preserve the receipts.
Rollback requires separate permission and reviewed commands, exact target+backup
admission; reverse C2 before N3 before N1. File rollback cannot undo remote effects.
C2 remains HELD; do not apply it as part of this packet.

After the owner work period, even if interrupted, explicitly remove only the
Booster identity and verify success; closing PowerShell is not removal proof:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\scripts\booster_ssh_session.ps1' -Action Lock
```
