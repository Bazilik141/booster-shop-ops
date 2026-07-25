# Codex Report — MKT-TG-008: curated digest and OpenAI drafts

Date: 2026-07-21

## Scope

- Select up to six genuinely distinct RSS candidates with fixed quotas: Pokemon 2, One Piece 1, MTG/YGO 1, and TCG Market 2. Three-day news remains primary; only empty quotas may use distinct 4–7-day candidates from an additional PokeBeach, One Piece official, or ICv2 Google News query. All categories reject price top lists, rarest/most-expensive lists, guides, deals, discounts, Amazon offers, reviews, and purchase roundups before scoring. The broad TCG group additionally requires a recognizable TCG signal, blocking unrelated ICv2 comics/news.
- Query coverage now includes the original broad Google News search plus PokeBeach and Pokemon Press; Japanese and English One Piece official sites; DailyMTG and YGOrganization; and ICv2, Dicebreaker, Flesh and Blood, and Disney Lorcana for broader TCG coverage. Official sources receive a ranking boost, while the title/event deduplication remains global.
- Ask Claude for a factual Ukrainian 1–2 sentence teaser for every selected candidate and show it with the original link.
- Send the full post generation from every `news_draft_*` button to OpenAI.
- Allow `/post` to accept either a URL or pasted source text. If URL text extraction fails, the bot asks for pasted article text and then sends that text to OpenAI.
- Report failed RSS sources on a manual no-results digest instead of presenting that state as unexplained emptiness.
- Use a concise, natural OpenAI writing brief: 400–550 characters, 2–3 short paragraphs, neutral factual headline, at most one relevant emoji per paragraph, and a joke only when it follows directly from a source fact. Analogies, clickbait, and synthetic concluding observations remain prohibited.

## Files touched

- `patches/MKT-TG-008_news-curation-and-openai-drafts_20260721.js` — complete Apps Script source-copy based on the current CRM-002 source-copy.

## Validation

- `node --check` — passed.
- Pure curation smoke — passed: a reworded One Piece scalping story is merged; unrelated Pokemon market stories remain separate; a `Top 10` listicle ranks below an actual news event.
- `git diff --no-index --check` — passed (no whitespace errors).
- PHP lint — not applicable: this is Google Apps Script source, not a PHP host patch.

## Dry-run and idempotency

No live Apps Script, Telegram, Claude, OpenAI, trigger, or Sheet call was made during local validation. Event-memory keys are deterministic, refreshed only for candidates actually sent, and pruned after 14 days; repeated digest runs do not re-send the same technical RSS item or a near-duplicate event.

## Rollback

Restore the immediately previous Apps Script version in the Apps Script editor and redeploy it. The new `MKT_TG_008_EVENT_*` Script Properties are harmless if left behind; they expire after 14 days once the new source is active.

## Owner deployment and QA

1. Save the current Apps Script version, replace `Code.gs` with the complete supplied source-copy, then deploy a new Web App version.
2. Run `/digest`: confirm up to six candidates in the 2/1/1/2 Pokemon/One Piece/MTG-YGO/TCG Market mix, a Ukrainian 1–2 sentence teaser and source link under each, and one draft button per candidate. A `🕓` item is allowed only when its category has no usable three-day candidate and must be 4–7 days old, distinct, and unseen.
3. Tap one button: confirm the full post is produced by OpenAI.
4. Use `/post` with a readable URL: confirm an OpenAI draft.
5. Use `/post` with a URL that cannot be read: confirm the bot asks for pasted text; paste at least one full article paragraph and confirm an OpenAI draft.
6. Use `/post` with no argument, then paste article text directly: confirm an OpenAI draft.
7. Run `/digest` when sources are unavailable or inspect Apps Script Executions: confirm RSS errors are distinguishable from a genuine no-news result.
8. Regression: `/orders`, active-order buttons, `/digest`, and existing callback routes continue to work.

## Boundaries

No Google Sheet schema, CRM read/write API, order flow, trigger configuration, secrets, checkout, payment, SEO, or deployment credentials were changed. The source-copy must still be pasted and deployed manually by the owner; local validation is not live Apps Script or Telegram proof.
