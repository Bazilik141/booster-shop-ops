# Codex Report — MKT-TG-005: lean RSS → Telegram digest

Date: 2026-07-03

## Scope

Implemented the handoff as one full Google Apps Script `Code.gs` source:

- four Google News RSS feeds with `1 / 1 / 1 / 2` selection;
- real RFC822 `pubDate` parsing and a 3-day freshness window;
- persistent PropertiesService dedup and 7-day callback-item storage;
- one Telegram digest message with per-item draft buttons;
- on-demand Anthropic Haiku draft generation;
- one isolated `news_draft_*` branch inside the existing callback router.

Protected CRM write paths, `doPost`, `/pick_news`, `/delete_news`, the main-menu keyboard, and `Новини_кандидати` were not modified.

### Implementation notes

- Google News RSS article links no longer redirect to the publisher. Reading `og:image` directly from those pages returns a generic Google News icon. The patch therefore resolves the publisher URL through Google News's signed `garturlreq` response before reading the publisher's `og:image`. If decoding fails, it falls back to the Google News link and sends no image instead of a generic or unrelated image.
- Telegram cannot attach five separate photos to one `sendMessage`. To preserve the acceptance criterion of exactly one digest message, each available publisher image is exposed as a per-item `Фото зі статті` link. An inline five-image gallery would require a media group plus a separate button message.
- Apps Script `UrlFetchApp` has no configurable per-request timeout. Requests use `muteHttpExceptions`, explicit HTTP checks, and safe no-image fallback.

## Source verification

Verified against the connected CRM spreadsheet `Booster Shop CRM — облік товарів`:

- spreadsheet modified: `2026-07-03T08:40:43.901Z`;
- timezone: `Europe/Kiev`;
- live source-copy tab: `Apps_Script_код`, 2874 rows;
- `handleTelegramCallback_()` starts at row 1644;
- existing Telegram/news/order branches and helpers match the latest full runtime-fix source used as the patch base.

The browser extension was unavailable, so no Apps Script deployment or Script Property was changed.

## Files touched

```text
patches/MKT-TG-005_lean-rss-digest_20260703.js
diagnostics/MKT-TG-005_lean-rss-digest_report_20260703.md
```

The `.js` file is an Apps Script-specific exception to the normal PHP runner convention. A PHP runner on `~/public_html` cannot modify a Google Apps Script project.

## Dry-run result

Local Apps Script mocks:

```text
node_check=ok
first_digest_items=5
digest_messages=1
publisher_urls_decoded=5
publisher_og_images=5
second_digest_items=0
anthropic_calls_during_digest=0
anthropic_calls_after_one_tap=1
callback_draft_message=ok
```

Static scope checks:

```text
function_count=201
duplicate_functions=0
callback_branch_count=1
anthropic_endpoint_count=1
```

Live read-only One Piece sample:

```text
title=Round1 and “ONE PIECE” Embark on First-Ever U.S.-Japan Collaboration
pubDate=Wed, 01 Jul 2026 15:55:33 GMT
v8_date_valid=true
publisher_url=https://rafu.com/2026/07/round1-and-one-piece-embark-on-first-ever-u-s-japan-collaboration/
publisher_og_image=https://rafu.com/wp-content/uploads/2026/07/One-Piece-x-Round1-Key-visual-scaled.jpg
visual_check=One Piece image; no Naruto drift
```

## Syntax check

```text
node --check patches/MKT-TG-005_lean-rss-digest_20260703.js
exit=0
```

`php -l` is not applicable because the target runtime is Google Apps Script V8, not PHP.

## Idempotency

- Seen IDs are SHA-256-derived 16-character URL-safe IDs.
- Seen markers persist in Script Properties for 30 days.
- A second same-day mock run sent `0` items and no second digest message.
- Callback item payloads expire after 7 days and are pruned automatically.
- A script lock prevents overlapping manual/trigger runs.

## Secrets and data

- Required new Script Property: `ANTHROPIC_API_KEY`.
- The key is read only at draft-button time and is never logged.
- No Sheet tab, CRM record, token, or existing Script Property is changed.
- No Anthropic call occurs during `newsDigest_()`.

## Rollback

Redeploy the previous Apps Script version. If a daily trigger was created, delete it in Apps Script → Triggers.

No database, Sheet-data, or Script Property rollback is required. The harmless `ANTHROPIC_API_KEY` property may remain.

## Deployment

1. Replace the current `Code.gs` with the full contents of `patches/MKT-TG-005_lean-rss-digest_20260703.js`.
2. Save and deploy a new Web App version.
3. Add `ANTHROPIC_API_KEY` in Project Settings → Script Properties.
4. Run `newsDigest_()` manually.
5. After successful QA, create a daily time-driven trigger for `newsDigest_()` around 10:00. The project timezone is already `Europe/Kiev`.

## Post-deploy QA checklist

- [ ] `newsDigest_()` sends one message with up to five fresh items and matching numbered draft buttons.
- [ ] Execution logs show only parsed dates from the last three days.
- [ ] One Piece item links to the publisher and its `Фото зі статті` is visibly One Piece, not a Google icon or Naruto.
- [ ] A second same-day `newsDigest_()` run sends nothing.
- [ ] One button tap returns one Ukrainian 100–180-word draft.
- [ ] Exactly one Anthropic request occurs for that tap and zero during the digest run.
- [ ] `/pick_news`, `/orders`, and the main menu still work.
- [ ] Daily trigger is created only after manual QA.

## Side effects / risks

- Google News publisher decoding uses an undocumented `batchexecute` response and may change. Failure is contained: the digest still uses the Google News link and omits the image.
- The callback router is shared with order/news flows; the patch adds exactly one branch and does not move existing branches.
- No production deployment or live Telegram send was performed by Codex.
