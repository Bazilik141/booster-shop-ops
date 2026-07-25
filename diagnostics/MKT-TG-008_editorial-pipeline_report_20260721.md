# MKT-TG-008 — Editorial pipeline report

Date: 2026-07-21  
Scope: Telegram news draft generation only. RSS/search, category quotas, Telegram callbacks and publication remain unchanged.

## Implemented flow

1. `openaiAnalyzeNews_` returns strict JSON with a selected angle, `hook_fact`, hook explanation, context facts that must remain visible, angle type, headline candidates, generic angles to avoid and an allow-list of entities.
2. `openaiDraftPostFromAnalysis_` writes from the selected angle and supporting facts only. It receives the relevant `NEWS_EDITORIAL_EXAMPLES` lesson, not merely a technical test fixture.
3. The first draft is checked deterministically for hook preservation/repetition, missing context, weak titles, generic collector copy, English source fragments and unsupported entities; a small structured evaluator checks whether the angle survived.
4. Audit flags route the draft through the editor and execution logs, but cannot by themselves hide a usable draft from Telegram. If the structured analyst/writer fails, a direct one-call OpenAI fallback writes from the supplied article text.

The editorial contract is 400–700 characters, 3–4 short paragraphs, up to four context-appropriate emoji, a maximum of four card/product names and no unsupported market predictions. A numerical hook is explained once; later copy must add context or consequence. Risk flags are internal guardrails, not reader-facing boilerplate. The pipeline logs the analysis JSON, first draft, audit flags, alignment result and final draft; it never logs the API key or complete article text.

Google News RSS redirects are decoded before storage and again before `/post` fetches. If a Google redirect still cannot be resolved, the existing pasted-text fallback remains available. `fetchArticleText_()` never tries to treat a remaining `news.google.com` redirect page as the publisher article.

## Files touched

- `patches/MKT-TG-008_news-curation-and-openai-drafts_20260721.js`
- `diagnostics/MKT-TG-008_editorial-pipeline_report_20260721.md`

## Local checks

- `node --check`: passed.
- `testNewsEditorialAudit()`: passed Gem Pack regression.
- Golden example selection: `number_contrast` is selected by `newsEditorialExamplesForAnalysis_()` and supplied to the writer prompt.
- Approved Gem Pack reference: passed at 461 body characters, three paragraphs and zero flags; `7 серпня`, `Китай` and `Applin` are retained.
- The rejected Gem Pack result is blocked with `weak_title`, `missing_hook_fact`, `missing_context_fact`, `generic_collector_copy`, and `unsupported_entity`; the unsupported entity is `Charizard`.
- Regression checks also block `different cards in the set` with `english_source_fragment` and a second paragraph with `196` as `hook_repetition`.
- `Gem Pack Vol 6: 196 карт із 28 Pokémon` is now a `weak_title`: numbers, colon and dash are only supporting signals. The title must not mechanically retell the hook or follow the `set name + numbers` pattern; it needs a consequence, image or complete editorial thought.
- No OpenAI request was made locally; the key is expected to stay in Apps Script Script Properties as `OPENAI_API_KEY`.

## Gem Pack regression evidence

Analysis JSON used by the test:

```json
{
  "selected_angle": "Невеликий список із 28 Pokémon перетворюється на 196 карт, бо кожен отримує сім версій.",
  "hook_fact": "28 Pokémon × 7 версій кожного = 196 карт.",
  "hook_explanation": "Ця математика показує реальний колекційний масштаб сету.",
  "angle_type": "number_contrast",
  "context_facts": ["7 серпня", "Китай", "Applin"],
  "allowed_entities": ["Gem Pack Vol. 6", "Applin", "Pokémon", "Illustration Rare", "Китай"]
}
```

First draft intentionally rejected by the regression:

> Gem Pack Vol 6 робить ставку на Applin і блиск
>
> Для фанатів яблучної лінійки це чудова нагода пополювати за улюбленцем у різних блискучих версіях. Навіть Charizard позаздрив би такому набору.

It loses `196`, uses generic collector copy and invents `Charizard`, so the next real run regenerates it instead of cosmetically editing it.

Final approved sample:

> Один Pokémon — сім карт: Gem Pack Vol. 6 готується з’їсти місце у ваших альбомах
>
> У наборі буде лише 28 різних Pokémon, але кожен отримає одразу шість пронумерованих holo-варіантів та окрему Illustration Rare. Разом це 196 карт.

## Before / after acceptance pattern

| Check | Previous single-pass draft | New pipeline requirement |
| --- | --- | --- |
| Grounding | Raw article and prompt in one model call | Confirmed facts are separated before writing; source opinions and uncertain claims are excluded from factual copy |
| Editorial idea | Model can retell several details equally | One logged angle and explicit `hook_fact`; generic angles are supplied as avoid-list |
| Tone control | Writer prompt only | Few-shot editorial lesson, deterministic audit, alignment evaluator and conditional editor |
| Length / structure | 400–550 chars, 2–3 paragraphs | 400–700 chars, 3–4 paragraphs; validated against the owner-provided Gem Pack reference |
| Safety | No formal record of what was discarded | Logs selected angle, excluded facts, risk flags and before/after audit flags |

There is deliberately no claimed live “new output” in this report: model calls were not made during local validation. The five real QA outputs after deployment are the acceptance evidence, not offline fixtures.

## Risks and rollback

- A normal draft performs analysis and writing; the alignment check is non-blocking. The LLM editor is conditional. Structured-stage failures fall back to one direct OpenAI writer call, so a strict heuristic cannot make pasted text unusable.
- If the analysis response is malformed or lacks confirmed facts, the draft fails safely with a visible error instead of inventing a post.
- Rollback: restore the previously deployed Apps Script source-copy/revision. No database, Telegram configuration, source list or stored news history is changed.

## Owner deployment and QA

1. Replace the Apps Script source with `patches/MKT-TG-008_news-curation-and-openai-drafts_20260721.js` and deploy the existing web app as a new version.
2. Confirm `OPENAI_API_KEY` is present in Script Properties; do not paste it into code.
3. Run `/post <URL>` for each of: set announcement, card preview, product announcement, tournament, collection update.
4. For each result check: concrete first paragraph, one central angle, correct names/numbers, 400–700 body characters, 3–4 paragraphs, emoji follow paragraph rhythm, no invented forecast, English source fragments, service-language boilerplate or generic AI phrase.
5. Inspect Apps Script execution logs for `News editorial analysis`, `first draft`, `first audit`, `final draft` and `final audit`. For Gem Pack confirm `7 серпня`, `Китай`, `Applin`, one clear `28/6+1/196` explanation and no `Charizard`.
6. Run `/post` once on a Google News RSS redirect and once with a copied full article text. The stored source URL must be the publisher URL when Google decoding succeeds; a structured-audit warning must not produce the generic Telegram failure message.
