# Codex Handoff — CRM-003: Rotate BOOSTER_CRM_TOKEN (hardcoded exposure found)

Date: 2026-07-29 | Parent: none (surfaced during 3D-P-000 review, see `plans/3D-P-000_scoping-and-architecture_20260728.md` §11.2)
Codex config: model=<owner to pick> · effort=medium
Risk zone: **CRM** (per `AGENTS.md` risky-zone list) — read the current handoff and evidence below before touching anything.

## 1. Task ID
CRM-003

## 2. Context
`BOOSTER_CRM_TOKEN` is hardcoded (identical literal value) in two repo files: `dashboard/booster-dashboard.html` (client-side `const TOKEN`) and `docs/index.html` (a GitHub-Pages-published duplicate of the dashboard, committed 2026-07-xx "deploy: add dashboard to docs/ for GitHub Pages"). The live server side stores the same value as `private const SECRET_TOKEN` in `system/library/booster_crm_sync.php` (production, not in this repo — see `live-snapshots/20260719_checkout002-silent-failure/booster_crm_sync.php` for the last-pulled, properly redacted mirror). The Apps Script backend already stores it correctly in Script Properties (confirmed by owner screenshot 2026-07-29) — that side is not hardcoded and is not part of this handoff's file changes, only its manual update step.

A rotation tool already exists and has been used before: `patches/booster_crm_sync_token_replay_20260616.php`. It reads a new token from an env var or a one-time `.booster_crm_token` file in `public_html` (never printed, deleted after read), backs up `booster_crm_sync.php`, regex-replaces `SECRET_TOKEN`, lints with `php -l` (auto-rollback on failure), then re-signs and replays any queued order-sync payloads with the new token so nothing is lost during the swap. Do not write a new rotation mechanism — verify and reuse this one.

Note: this same script also bundles an unrelated timeout-constant change (`TIMEOUT_SECONDS`/`CONNECT_TIMEOUT_MS`/`TIMEOUT_MS`, lines ~316-317) left over from its original 2026-06-16 purpose. Codex must check whether that change is already live (no-op if so) before re-running — do not silently reapply a value the owner may have already tuned differently since.

## 3. Goal
Rotate the CRM API token end-to-end so the old, exposed value stops working everywhere, with zero order-sync data loss and zero disruption to the Telegram bot (confirmed separate credential, `module_telegram_notify_token`, not in scope) or the MKT-TG news-digest pipeline (confirmed separate credentials, `ANTHROPIC_API_KEY`/`OPENAI_API_KEY` in Script Properties, not in scope).

## 4. What to change
- `system/library/booster_crm_sync.php` (production server, not in this git repo) — new `SECRET_TOKEN` value, via the existing `patches/booster_crm_sync_token_replay_20260616.php` script. Codex verifies the script's regex assumptions (`private const SECRET_TOKEN = '...'`, `private const WEB_APP_URL = '...'`) still match the current live file (pull a fresh snapshot first, same way `live-snapshots/20260719_checkout002-silent-failure/` was pulled, before assuming the pattern still holds) and confirms the bundled timeout change is a no-op or flags it if not.
- `dashboard/booster-dashboard.html` — the `const TOKEN` line: replace the literal old value with the new one. This is a plain repo file; Codex or owner can edit it directly, but **the actual new token value must never be typed into a Codex prompt, commit message, or diagnostics file** — owner pastes it in directly, or Codex is given the value out-of-band by the owner at execution time only.
- `docs/index.html` — recommended: delete this file outright. It is a stale, redundant GitHub-Pages copy of the dashboard; `dashboard/booster-dashboard.html` has been the single canonical dashboard since 2026-07-28 (see `ROADMAP_SOP.md` §0, §8). If the owner prefers to keep it instead of deleting, apply the same token edit as above and flag GitHub Settings → Pages status back to the owner either way (see §7 below).
- Google Apps Script Script Properties (`BOOSTER_CRM_TOKEN`) — **owner-only manual step**, not a repo file, not something Codex touches. Owner updates the value directly in script.google.com.

## 5. Do not touch
- `module_telegram_notify_token` / any Telegram bot credential or code path — confirmed separate, unrelated to this rotation.
- `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `CRM_DOGET_CACHE_VERSION`, `CRM_ORDERS_CACHE_VERSION`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ALLOWED_CHAT_ID`, or any other Script Property not named `BOOSTER_CRM_TOKEN`.
- The MKT-TG news-digest logic (`newsDigest`, `newsPruneDigestProperties_`, TTL constants) — unrelated cleanup happened this session already, do not re-touch.
- `sitemap.xml`, `robots.txt`, redirects, canonical tags, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema — none of these are in scope; do not modify.
- Git history rewrite (`git filter-repo`/BFG) — out of scope for this handoff. Only worth doing as a separate, explicitly-approved follow-up if GitHub Pages is confirmed to have publicly served the old token (see §7).

## 6. Likely files / areas
- `system/library/booster_crm_sync.php` (production; confirm via fresh pull, likely area only)
- `patches/booster_crm_sync_token_replay_20260616.php` (repo; verify/reuse, likely still valid)
- `dashboard/booster-dashboard.html` (repo; confirmed, line with `const TOKEN`)
- `docs/index.html` (repo; confirmed, same literal value)
- Codex should verify against actual project files before assuming any of the above still matches — the production file in particular may have drifted since the 2026-07-19 snapshot.

## 7. Acceptance criteria
- [ ] `system/library/booster_crm_sync.php` on production contains the new `SECRET_TOKEN` value (verify via a fresh pull/snapshot after the change, not assumption).
- [ ] A GET request to the CRM API (`action=summary`) with the **new** token returns HTTP 200 with expected JSON; the same request with the **old** token returns an auth failure (401/403 or the API's documented equivalent).
- [ ] `php -l system/library/booster_crm_sync.php` passes (the replay script already gates on this, but confirm in the log).
- [ ] The replay-script log shows `replay_failed=0` (or every failure individually explained) — no queued order-sync payload silently dropped.
- [ ] `grep` for the old literal token value across `dashboard/booster-dashboard.html` and `docs/index.html` (or its deletion) returns zero matches in the repo's working tree.
- [ ] Apps Script Script Properties `BOOSTER_CRM_TOKEN` updated (owner confirms directly, Codex does not have access to verify this one).
- [ ] Owner has checked GitHub → Settings → Pages to confirm whether `docs/` is/was being served publicly; result recorded in the diagnostic regardless of outcome.

## 8. QA / smoke test (owner runs after deploy)
- [ ] Dashboard tab: GET `summary`/`orders`/`stock_alerts`/`sku_list` all return data with the new token.
- [ ] Place or use one existing test order end-to-end; confirm `doPost` order-sync still fires and the order appears in the CRM sheet.
- [ ] Confirm the Telegram order-notification bot still sends its message for that same test order (separate credential — should be unaffected, but verify since it shares the same OpenCart module family).
- [ ] If the owner wants the fuller regression pass rather than a single test order, run `bs-checkout-smoke` — optional, this handoff's blast radius is CRM sync, not checkout/payment UI itself, but the order-sync path is adjacent.

## 9. Rollback note
The replay script auto-backs up `booster_crm_sync.php` to `_boostershop_patch_backups/booster_crm_sync_token_20260616/` (timestamped) before writing, and auto-restores from that backup if `php -l` fails. If a problem surfaces after a clean lint pass (e.g., the new token doesn't validate against Apps Script for an unrelated reason), restore the most recent `.bak` file from that directory and revert the Apps Script Script Property to the prior value. For the two repo files, `git checkout` the prior committed version of `dashboard/booster-dashboard.html` (and restore `docs/index.html` from git history if it was deleted) — normal git revert, no data loss risk since these are static client files.

## 10. Recommended status after execution
`In progress` until the owner has personally: (a) confirmed the production token swap + replay log, (b) run the QA smoke test in §8, and (c) checked GitHub Pages status per §7. Only move to `Done` after all three are owner-confirmed — Codex should not self-mark this Done, per `ROADMAP_SOP.md` writer rules (Claude is the default Notion status writer for non-Codex-implementation tasks; Codex updates `ROADMAP_FLOW` only within its own authorized implementation).
