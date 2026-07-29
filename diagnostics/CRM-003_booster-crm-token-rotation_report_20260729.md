# Codex Report — CRM-003: BOOSTER_CRM_TOKEN rotation preparation

Date: 2026-07-29
Codex config: model=Sol · effort=xhigh (recommended by AGENTS.md; runtime selection not independently verified)

## Scope

Implemented the repository and local-source preparation for the CRM token rotation without receiving or recording a new token.

- Removed the stale `docs/index.html` GitHub Pages duplicate as recommended by the handoff.
- Replaced the dashboard's committed token literal with a per-session `sessionStorage`/prompt flow. The owner supplies the current token locally at dashboard open; it is not written to Git.
- Removed the literal fallback from the owner-provided Apps Script source copy. `getBoosterCrmToken_()` now reads only `BOOSTER_CRM_TOKEN` from Script Properties and returns an empty value when it is absent.
- Reused and hardened the existing server replay runner with an idempotency guard: when the target already has the supplied token, it reports `already_applied=yes` and exits before backup/write/replay.

No production deployment, Apps Script property write, Notion/status change, commit, or push was performed.

## Files touched

```text
patches/booster_crm_sync_token_replay_20260616.php
    Added the idempotency guard to the existing rotation/replay runner.

dashboard/booster-dashboard.html
    Removed the committed CRM token literal; prompts once per browser session
    and stores only in sessionStorage.

docs/index.html
    Deleted stale GitHub Pages duplicate.

Booster Shop CRM - Apps_Script_код 29.07.2026.csv
    Owner-provided local source copy; removed hardcoded fallback only.
    Do not stage or commit this file.
```

## Freshest available live-source preflight

The newest owner-supplied cPanel backup available locally was
`C:\Users\14bez\Downloads\Booster Shop\backup-7.24.2026_17-02-32_boosters.tar.gz`.

Read-only extraction of `homedir/public_html/system/library/booster_crm_sync.php` found:

```text
SECRET_TOKEN anchor count=1
WEB_APP_URL anchor count=1
TIMEOUT_SECONDS=15
CONNECT_TIMEOUT_MS=3000
TIMEOUT_MS=15000
```

Therefore the runner's bundled legacy timeout conversion is a no-op for this backup. This is not proof of the production file on 2026-07-29; the runner still anchor-checks the live target and must be run only after the owner obtains a fresh narrow snapshot or accepts its safe-fail preflight on the host.

## Local checks

```text
php -l patches/booster_crm_sync_token_replay_20260616.php
No syntax errors detected

dashboard inline JavaScript: node --check passed
git diff --check for CRM-003 paths: passed
old Apps Script token remains in edited source: false
old dashboard token remains in edited dashboard: false
docs/index.html exists: false
```

### Apps Script source-copy limitation

A Node syntax check of the supplied Apps Script source failed at source line 90 / file line 99 with `SyntaxError: Unexpected token ')'`. The same failure occurs against the temporary pre-change copy, so CRM-003 did not introduce it. The export must not be pasted wholesale into live `Code.gs` until that separate syntax issue is investigated. The two token-source edits remain a local source-copy hardening only.

## Idempotency

The replay runner now compares the supplied token to the current `SECRET_TOKEN` before creating a backup or writing. Equal values return:

```text
already_applied=yes
```

A first-time production run remains unverified locally because it needs the owner-provided new token and host queue/API access.

## Rollback

- Server runner: its existing production backup mechanism writes a timestamped copy under `_boostershop_patch_backups/booster_crm_sync_token_20260616/` before a first-time write; lint failure restores it automatically.
- Local CRM-003 preparation: pre-change copies were retained outside the repository at `C:\tmp\crm003_token_rotation_20260729\` for this session only.
- Dashboard/docs: restore the prior committed version through a normal Git revert if the owner rejects the new session-token flow.
- Apps Script property: restore the prior `BOOSTER_CRM_TOKEN` value only if the server file is restored too.

## Owner deployment sequence

1. Generate a new token locally. Do not send it in chat, commit it, or save it in a diagnostic.
2. In Apps Script Script Properties, replace only `BOOSTER_CRM_TOKEN` with the new value.
3. Obtain a fresh narrow source archive of `system/library/booster_crm_sync.php` and recheck the two anchors; do not assume the 2026-07-24 backup is still current.
4. Upload `patches/booster_crm_sync_token_replay_20260616.php` to `~/public_html` and put the new value in a one-time `~/public_html/.booster_crm_token` file.
5. Run the command below. The runner deletes the one-time token file after reading it, performs a backup and `php -l`, then re-signs/replays its queue. Check `replay_failed=0`.
6. Open the local dashboard and enter the new token when prompted. It remains only for that browser session.
7. Run the post-deploy API and order-sync QA below. Check GitHub Settings -> Pages and record whether the retired `docs/` directory had ever been served.

```bash
cd ~/public_html
php booster_crm_sync_token_replay_20260616.php
```

## Post-deploy QA checklist

- [ ] Host output has `php_lint=` success and `replay_failed=0`.
- [ ] `action=summary` with the new token returns HTTP 200 and expected JSON.
- [ ] The same API request with the old token is rejected.
- [ ] Dashboard `summary`, `orders`, `stock_alerts`, and `sku_list` work after the session prompt.
- [ ] One test/existing order reaches the CRM sheet through `doPost`.
- [ ] Telegram order notification still arrives for that order.
- [ ] Owner confirms the Apps Script Script Property update.
- [ ] Owner records GitHub Pages status for the deleted duplicate.

## Side effects / unresolved risks

- Dashboard is intentionally not usable until its operator supplies a valid token for that browser session. This prevents storing a replacement secret in the repository but changes first-open behavior.
- The Apps Script export has a pre-existing syntax failure and is not deployment-ready as a complete source replacement.
- The available cPanel snapshot is five days old; local anchor validation is not current production proof.
- The dashboard had unrelated pre-existing working-tree hunks in the roadmap section. CRM-003 changed the separate token area only; do not blindly stage the whole dashboard file.