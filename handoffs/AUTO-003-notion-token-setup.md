# Handoff — AUTO-003: Notion token and database ID correction

Date: 2026-07-20 (closed technically 2026-07-21)
Status: DONE (technical) — all local blockers resolved and confirmed live; awaiting owner commit/push + Notion status close.

---

## Conclusion

The earlier Private-move plan was invalid for the Roadmap database, which is managed inside Notion Home and is not selectable in the internal-connection content picker. The owner created a personal access token instead. A read-only API request confirmed that the live Roadmap database ID is `35c3f857-2fc5-4a78-96c8-af0efd4cf8d4`.

The former ID `5aef22c3-048d-4dde-a5b1-ad409de9301c` returns `object_not_found`; it was the blocker in AUTO-002/AUTO-003, not a missing Notion permission.

## Scope

- Replace the stale database ID in AUTO-002/AUTO-003 local scripts and operational documentation.
- Keep the owner's personal access token only in ignored `repo-root/.env.review` as `NOTION_TOKEN=...`.
- Do not move the Notion database, change its data, or expose any secret.

## Evidence

```text
GET /v1/databases/5aef22c3-048d-4dde-a5b1-ad409de9301c -> 404 object_not_found
GET /v1/databases/35c3f857-2fc5-4a78-96c8-af0efd4cf8d4 -> 35c3f857-2fc5-4a78-96c8-af0efd4cf8d4
```

## Remaining dependencies

- `bscontent` additionally requires owner-provided `ANTHROPIC_API_KEY` in `.env.review` — still missing (2026-07-21).
- `bsseo --dry-run` additionally requires a one-time Google Search Console OAuth setup: `GSC_CLIENT_ID`, `GSC_CLIENT_SECRET`, `GSC_REFRESH_TOKEN`, and `GSC_SITE_URL` — done 2026-07-21, all four values are in `.env.review`.
- These independent secrets were absent on 2026-07-20; their absence does not invalidate the Notion token or ID correction.

## GSC OAuth setup — completed 2026-07-21 (notes for next time)

- Google Cloud Console no longer allows re-downloading/viewing an existing OAuth client secret ("Viewing and downloading client secrets is no longer available"). If the original `client_secret.json` is lost, the fix is: OAuth client → **+ Add secret** → copy the new value immediately (shown once) → it coexists with old secrets, no need to delete them.
- **Known gotcha (hit twice now — recording so it doesn't repeat):** when Windows Explorer has file extensions hidden and you rename a downloaded `client_secret_<id>.json` to `client_secret.json`, Explorer silently keeps the real extension, producing `client_secret.json.json`. `scripts/gsc_auth.py` then fails with "client_secret.json not found in repository root". Check the actual filename (`dir` in the repo root, or enable "show file extensions" in Explorer) before re-downloading anything.
- `python scripts/gsc_auth.py` ran successfully 2026-07-21 against the new secret; all four `GSC_*` values were added to `.env.review`.

## QA checklist

- [x] Personal access token authenticates to Notion.
- [x] Personal access token reads the live Roadmap database ID.
- [x] `python -m py_compile auto_review.py scripts/auto_review.py scripts/content_pipeline.py scripts/seo_monitor.py` — verified 2026-07-20, no errors.
- [x] `bscontent "тест"` — content file saved 2026-07-21; Notion task created and confirmed on the second run: `https://app.notion.com/p/CONTENT-test-ready-for-review-3a46bf20bdb481bfbe47e8dd839e00a0`.

## Second bug found + fixed 2026-07-21 — wrong Notion property types in AUTO-002/AUTO-003 scripts

`bscontent "тест"` generated content fine but Notion task creation failed with `400 validation_error: Status is expected to be status. Category is expected to be rich_text. Task Type is expected to be rich_text.` — `scripts/content_pipeline.py` and `scripts/seo_monitor.py` were building `Status`/`Category`/`Task Type` as `select`-type payloads, but the real Roadmap schema has `Status` as a `status` property and `Category`/`Task Type` as plain `rich_text` (only `Priority` is actually `select`). Fixed both scripts (`notion_status()`/`notion_text()` helpers added to `content_pipeline.py`, same shapes inlined in `seo_monitor.py`), re-ran, confirmed working live (real task created in the Roadmap DB, link above).

**Cleanup needed:** the confirm run created a real "CONTENT: test — ready for review" task in the live Roadmap DB and two test files in `plans/` (`content-2026-07-21-test-kolektsiinykh-kartok.md`, `content-2026-07-21-test.md`) — owner should delete/archive the Notion test task and decide whether to keep or delete the test content files before committing.
- [x] `bsseo --dry-run` after GSC OAuth setup completes without configuration errors — confirmed 2026-07-21: `checked=52 problems=0 notion_tasks=0 dry_run=yes`, first snapshot saved (`plans/seo-snapshot/2026-06.json`), no baseline yet so no comparison until next month.

## Risks and rollback

- Low risk: only local constants and documentation change; no Notion mutation occurs.
- Rollback: restore the old constants from git only if a fresh read-only API check proves the new ID invalid.
- `GSC_CLIENT_SECRET` and `GSC_REFRESH_TOKEN` values were pasted in plaintext into chat during setup (2026-07-21) — scope is `webmasters.readonly` (read-only Search Console access), not a high-value credential. Owner declined secret rotation; accepted as-is.

## Root cause found 2026-07-21 — wrong domain, not a permission problem

`bsseo --dry-run` failed with `403 User does not have sufficient permission for site 'https://boosterok.com.ua/'`. That domain is simply wrong — the real site is **https://boostershop.website/**. `boosterok.com.ua` was hardcoded (inherited from the original AUTO-003 spec, never verified against the real domain) in `scripts/gsc_auth.py`, `scripts/content_pipeline.py` (the actual content-generation prompt — this could have leaked into generated copy), `scripts/.env.example`, and both AUTO-003 handoffs. All corrected to `boostershop.website` 2026-07-21; `.env.review`'s `GSC_SITE_URL` corrected to match.

Still to confirm: property type in Search Console — URL-prefix (`https://boostershop.website/`) vs Domain (`sc-domain:boostershop.website`) — if `bsseo --dry-run` still 403s after this fix, that's the next thing to check.
