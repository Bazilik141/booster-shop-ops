# Handoff — AUTO-003: Notion token and database ID correction

Date: 2026-07-20
Status: IN PROGRESS — personal access token confirmed; stale database ID corrected locally.

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

- `bscontent` additionally requires owner-provided `ANTHROPIC_API_KEY` in `.env.review`.
- `bsseo --dry-run` additionally requires a one-time Google Search Console OAuth setup: `GSC_CLIENT_ID`, `GSC_CLIENT_SECRET`, `GSC_REFRESH_TOKEN`, and `GSC_SITE_URL`.
- These independent secrets were absent on 2026-07-20; their absence does not invalidate the Notion token or ID correction.

## QA checklist

- [x] Personal access token authenticates to Notion.
- [x] Personal access token reads the live Roadmap database ID.
- [ ] `python -m py_compile auto_review.py scripts/auto_review.py scripts/content_pipeline.py scripts/seo_monitor.py`
- [ ] `bscontent "тест"` after adding `ANTHROPIC_API_KEY` creates a content file and a Roadmap task.
- [ ] `bsseo --dry-run` after GSC OAuth setup completes without configuration errors.

## Risks and rollback

- Low risk: only local constants and documentation change; no Notion mutation occurs.
- Rollback: restore the old constants from git only if a fresh read-only API check proves the new ID invalid.
