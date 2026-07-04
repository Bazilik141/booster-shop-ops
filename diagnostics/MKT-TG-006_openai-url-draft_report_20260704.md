# Codex Report — MKT-TG-006: OpenAI URL draft

Date: 2026-07-04

## Scope

Implemented the handoff as scoped:

- added direct `/post <url>` routing plus a two-step button flow that waits 10 minutes for the next URL;
- reused the existing article extraction path;
- added OpenAI Responses API generation with model `gpt-5.5`;
- tuned the OpenAI editorial prompt with the latest owner-approved style example, `low` reasoning, low verbosity, and a 700-token output cap;
- added safe one-time API key setup through the spreadsheet menu;
- preserved the Anthropic RSS digest flow;
- added `/post` to Telegram command registration;
- expanded the `/start` menu with digest and a stateful URL-draft action;
- synchronized and verified the `Apps_Script_код` source-copy tab.

No bound Apps Script deployment and no real OpenAI API request were performed.

## Files touched

```text
patches/MKT-TG-006_openai-url-draft_20260704.js
diagnostics/MKT-TG-006_openai-url-draft_report_20260704.md
Google Sheet: Apps_Script_код
```

## Verification result

```text
node --check: ok
smoke=ok
post_validation=ok
post_success_format=ok
post_thin_article=ok
existing_routes=ok
main_menu_actions=ok
two_step_post_flow=ok
key_setup_helper=ok
key_setup_menu_context=ok
responses_payload=ok
editorial_prompt_calibration=ok
responses_output_parser=ok
```

The Google Sheet source-copy was read back at the exact changed ranges. Confirmed:

- `/post` route and handler;
- `setupOpenAiApiKey()`;
- `NEWS_DIGEST_OPENAI_MODEL = 'gpt-5.5'`;
- `openaiDraftPostFromUrl_()` and full Responses API output parsing;
- the following `newsPruneDigestProperties_()` boundary remained intact.

## API key setup

After replacing the bound Apps Script source with the patch file:

1. Save the Apps Script project.
2. Reload the CRM spreadsheet.
3. Select **Booster CRM → Налаштувати OpenAI ключ**.
4. Approve permissions if Google asks.
5. Paste the existing OpenAI API key into the dialog and confirm.

The helper writes only `OPENAI_API_KEY` to Script Properties. It does not print or store the key in source code or logs.
The function must not be launched directly from the Apps Script editor because that execution context has no spreadsheet UI.

## Idempotency

Running `setupOpenAiApiKey()` again replaces the same `OPENAI_API_KEY` property. Existing Telegram and digest setup functions retain their previous idempotent behavior.

## Rollback

- Keep the previous bound Apps Script version available in Apps Script version history.
- To roll back, restore the previous Apps Script source or paste the previous canonical source file.
- If needed, delete only the `OPENAI_API_KEY` Script Property programmatically; existing digest cache properties are unrelated and should remain.

## Owner run / deploy

This is an Apps Script source update, not a server PHP patch:

1. Paste the complete contents of `patches/MKT-TG-006_openai-url-draft_20260704.js` into the bound Apps Script project.
2. Save and deploy the updated web app version.
3. Reload the CRM spreadsheet and use **Booster CRM → Налаштувати OpenAI ключ**.
4. Run `tgSetupCommands()` to refresh the Telegram command menu.

## Post-deploy QA checklist

- [ ] `setupOpenAiApiKey()` confirms that `OPENAI_API_KEY` was saved.
- [ ] Telegram command menu contains `/post`.
- [ ] `/post` without a URL starts a 10-minute wait for the next URL.
- [ ] The `/start` URL-draft button prompts for a URL, then consumes the next URL message.
- [ ] `/post https://...` returns a Ukrainian draft with a short bold topic.
- [ ] `/orders` and `/digest` still route normally.
- [ ] The scheduled RSS digest continues using its existing Anthropic path.

## Side effects / risks

- OpenAI API usage incurs API cost.
- Some sites may block article extraction; the bot returns a friendly article-unavailable message.
- The Google Sheet is only a source-copy mirror. Live behavior changes only after the owner updates and deploys the bound Apps Script project.
