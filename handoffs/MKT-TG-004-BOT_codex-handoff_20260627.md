# Codex Handoff — MKT-TG-004-BOT (Telegram review bot: /pick_news, /delete_news, candidate sheet)

## 1. Task ID
MKT-TG-004-BOT (sub-task of roadmap **MKT-TG-004** — TG content automation Phase 2). Plan: `plans/tg-content-automation-phase2-plan_2026-06-27.md`.

## 2. Context
The Make scenario (MKT-TG-003, fixed) generates Ukrainian TG posts. Phase 2 changes it to **not** auto-post; instead Make writes candidate posts (text + source + image links) into a new Google Sheet tab. The owner reviews them in the existing CRM Telegram bot and posts 1-2/day to the group by hand.

The bot lives in the **existing CRM Apps Script** (single Web App `doPost` handling both the token API and Telegram updates). Confirmed existing structure (reference export: `Booster Shop CRM — облік товарів - Apps_Script_код.csv` in the parent working folder):
- `doPost(e)` → if `payload.message || payload.callback_query` → `handleTelegramUpdate_(payload)`; else token-guarded JSON API.
- `handleTelegramUpdate_` routes `/debug_chat`, `/orders`, else `tgShowMainMenu_`. Gate: `tgIsAllowedChat_(chatId)`.
- `handleTelegramCallback_` switches on `data` prefixes: `main_menu`, `orders_list`/`back_orders`, `order_sel_`, `upd_delivery_`, `upd_payment_`, `upd_all_`.
- Helpers: `tgSendMessage_(chatId,text,keyboard)`, `tgEditMessage_(chatId,messageId,text,keyboard)`, `tgAnswerCallback_`, `tgBotApi_(method,payload)`, `tgEscapeHtml_`, `tgShowMainMenu_`.
- Secrets in Script Properties: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ALLOWED_CHAT_ID`.

## 3. Goal
Add a news-review surface to the bot **without touching CRM/order logic**:
- `/pick_news` → list current candidates as inline buttons (like the orders list); tap → send the post text (copyable) + 2-3 image links.
- `/delete_news` → archive candidates older than 3 days (sheet status flip, NOT chat-message deletion).
- `setMyCommands` → commands appear in the bot's "/" dropdown.
- Create the candidate sheet tab + a reader.

## 4. What to change (Codex writes the code; do not paste final code here)
All additions are **additive** to the existing Apps Script.

**(a) New sheet tab + setup function**
- `setupNewsSheet_()` — one-time/idempotent: if tab `Новини_кандидати` is missing, create it and write the header row exactly:
  `id | created_at | game | title | post_text | source_url | image1 | image2 | image3 | status | guid`
  (columns A–K). Owner runs this once from the Apps Script editor.

**(b) Candidate reader**
- `crmGetNewsCandidates_(maxDays)` — read `Новини_кандидати`; return rows where `status === 'new'` AND `created_at >= now - maxDays days` (default 3). Return array of `{rowIndex, id, game, title, post_text, source_url, urls:[image1..image3 non-empty]}`, newest first. Cap at ~20 (mirror `crmGetOrders_` limit style).

**(c) Command routing** — in `handleTelegramUpdate_`, AFTER the `tgIsAllowedChat_` check, BEFORE the `tgShowMainMenu_` fallback:
- `if (text.indexOf('/pick_news') === 0) { tgCommandNews_(chatId); return; }`
- `if (text.indexOf('/delete_news') === 0) { tgCleanNews_(chatId); return; }`

**(d) Main menu buttons** — in `tgShowMainMenu_`, add to the keyboard:
- `[{ text: 'Новини', callback_data: 'news_list' }]`
- `[{ text: 'Очистити новини (>3д)', callback_data: 'news_clean' }]`

**(e) Callback routing** — in `handleTelegramCallback_`, add branches (follow existing prefix style like `order_sel_`):
- `data === 'news_list'` → `tgAnswerCallback_(callbackId,''); tgCommandNews_(chatId, messageId);`
- `data.indexOf('news_pick_') === 0` → `tgAnswerCallback_(callbackId,''); tgShowNewsPost_(chatId, data.substring('news_pick_'.length));`
- `data === 'news_clean'` → `tgAnswerCallback_(callbackId,'Чищу...'); tgCleanNews_(chatId, messageId);`
- `data.indexOf('news_done_') === 0` → mark candidate status=`posted`, answer "Позначено".

**(f) New bot functions** (reuse existing helpers, do not modify them):
- `tgCommandNews_(chatId, messageId)` — text `'<b>Підібрані пости: '+N+'</b>'` (or "Немає підібраних постів"); keyboard = one button per candidate `{text: game+' · '+shortTitle(≤40 chars), callback_data: 'news_pick_'+id}` + `[{text:'Назад', callback_data:'main_menu'}]`. Use `tgEditMessage_` if `messageId` else `tgSendMessage_` (mirror `tgCommandOrders_`).
- `tgShowNewsPost_(chatId, id)` — look up candidate; send the **post text as a normal message** (`tgSendMessage_`, HTML-escaped, NO inline keyboard) so the owner can long-press → Copy; then send a SECOND message with `Джерело: <source_url>` + each image link on its own line, plus an inline button `[{text:'✓ Опубліковано', callback_data:'news_done_'+id}]`. (Do NOT use a Telegram `copy_text` button for the post body — it is capped at 256 chars; a `copy_text` button MAY be used for the source URL only.)
- `tgCleanNews_(chatId, messageId)` — set `status='archived'` for rows where `status==='new'` AND `created_at < now-3d`; reply/edit `'Архівовано: '+M`. Must NOT call Telegram deleteMessage (bots can't delete >48h messages).
- `tgSetupCommands_()` — call `tgBotApi_('setMyCommands', { commands:[{command:'pick_news',description:'Підібрані пости'},{command:'delete_news',description:'Очистити старі (>3д)'}], scope:{ type:'chat', chat_id: <TELEGRAM_ALLOWED_CHAT_ID value> } })`. Owner runs once.

## 5. Do not touch
- `doPost` token/JSON-API branch and ALL `api*_` functions (`apiAddSale_`, `apiAddPurchase_`, `apiUpdateSale_`, `apiUpdatePurchase_`, `apiAddWriteOff_`, `apiSummary_`, recent/sku endpoints, `upsertOpenCartOrder_`, etc.).
- Order/sale/purchase/write-off logic and cost recalculation (`fixSaleCostForRow_`, `recalculateMysteryBoxOrderCost_`, `updateSkuCurrentCost_`, …).
- Existing Telegram order flow: `tgCommandOrders_`, `tgShowOrderDetails_`, `tgCommandUpdate_`, `tgUpdateOrderStatus_`, and callback prefixes `order_sel_`, `upd_delivery_`, `upd_payment_`, `upd_all_`, `main_menu`, `orders_list`, `back_orders`.
- Shared helpers internals: `tgBotApi_`, `tgSendMessage_`, `tgEditMessage_`, `tgAnswerCallback_`, `tgIsAllowedChat_`, `tgEscapeHtml_` (reuse, don't change signatures).
- Script Properties and token handling (`getBoosterCrmToken_`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ALLOWED_CHAT_ID`).
- Sheets `Продажі`, `Закупки`, `Списання`, `Витрати`, `РРЦ` and any CRM tabs (only ADD the new `Новини_кандидати` tab).
- Standard protected zones (n/a for this task but must stay untouched): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema.

## 6. Likely files / areas
- The CRM Apps Script project (the same project that serves the Web App / `doPost`). Reference export: `Booster Shop CRM — облік товарів - Apps_Script_код.csv`.
- New tab `Новини_кандидати` in the CRM spreadsheet.
- Codex should verify exact current function bodies against the live Apps Script before inserting (the CSV is a reference export, may lag the deployed version).

## 7. Acceptance criteria (measurable)
- Running `setupNewsSheet_()` once creates tab `Новини_кандидати` with header row A1:K1 exactly as in §4(a).
- With ≥1 row `status=new` (created ≤3d), `/pick_news` (or "Новини" button) returns a message `Підібрані пости: N` and N inline buttons; tapping a button delivers exactly 2 messages: (1) the post text, (2) source + image links + "✓ Опубліковано" button.
- Tapping "✓ Опубліковано" sets that row `status=posted` (verify in sheet).
- `/delete_news` (or "Очистити новини" button) sets `status=archived` for new rows older than 3 days and replies `Архівовано: M` (M correct vs sheet).
- After `tgSetupCommands_()`, the Telegram "/" menu in the owner's private chat lists `pick_news` and `delete_news`.
- Regression: `/start` → main menu now shows "Активні замовлення" + "Новини" + "Очистити новини"; order list, order details, and the three update buttons still work exactly as before.
- Non-allowed chat: news commands and callbacks are rejected by `tgIsAllowedChat_` (no data leak).

## 8. QA / smoke test
1. Run `setupNewsSheet_()` → confirm tab + headers.
2. Manually add 2 test rows (status=new, created_at=now, image1 set) → `/pick_news` lists 2 → tap each → text + links arrive.
3. Set one test row `created_at` to 4 days ago → `/delete_news` → that row → `archived`; fresh row stays `new`.
4. Run `tgSetupCommands_()` → reopen chat → "/" dropdown shows the commands.
5. Order regression: open `/start` → Активні замовлення → select an order → "Оновити оплату" → confirm status updates as before.
6. Check Apps Script execution logs for errors on each step.

## 9. Rollback note
Changes are additive. Rollback = in the Apps Script editor, **Deploy → Manage deployments / Versions**, redeploy the previous version; OR remove the 6 new functions (`setupNewsSheet_`, `crmGetNewsCandidates_`, `tgCommandNews_`, `tgShowNewsPost_`, `tgCleanNews_`, `tgSetupCommands_`), the 2 routing inserts in `handleTelegramUpdate_`, the 2 menu buttons in `tgShowMainMenu_`, and the 4 callback branches in `handleTelegramCallback_`. The `Новини_кандидати` tab can be left (unused) or deleted. No CRM data is modified by this change.

## 10. Recommended status after execution
`In progress` (MKT-TG-004 stays In progress until Make Phase 1 writes real candidates AND the two schedules run). Mark the bot sub-part done in the execution report; flip MKT-TG-004 to Done only after the full 10:00/18:00 flow is verified end-to-end.

---
**Risk note:** Editing the live CRM/order Apps Script — main risk is regression to the order bot. Not SEO/checkout/payment/schema/Merchant, so `bs-seo-risk-gate` / `bs-checkout-smoke` / `bs-merchant-schema-qa` do not apply. Enforce §5 Do-not-touch and the order-flow regression check in §7/§8.
