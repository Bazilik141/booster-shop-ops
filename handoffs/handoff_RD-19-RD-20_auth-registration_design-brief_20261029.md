# Design brief — RD-19 + RD-20: sign-in, password recovery, two-step registration

Date: 2026-10-29
Designer: Claude Design (owner assignment). Implementation afterwards: Claude Code, both tasks in one package set.
Notion: RD-19 https://app.notion.com/p/3706bf20bdb481768f6ef9cd80cbbb9c · RD-20 https://app.notion.com/p/3706bf20bdb481e197cce7b6b2f71fb0
Output of this brief: approved boards + a short spec note. No code, no patch.

## 1. Context
The logged-in account area is finished in **light variant A**:
- RD-16: shell, hub, personal data;
- RD-17: addresses;
- RD-18: orders;
- ACC-006: «Обране».

The pages a visitor sees **before** signing in still look stock OpenCart: sidebar «Особистий кабінет», small Bootstrap fields, cyan buttons, reCAPTCHA label in English. Those pages are:
- sign-in: `account/login`;
- password recovery: `account/forgotten`, plus the page opened from the e-mail link;
- registration: `account/register`.

This brief covers those pages only.

Live pages to look at first (public, no login needed):
- https://boostershop.website/index.php?route=account/login
- https://boostershop.website/index.php?route=account/forgotten
- https://boostershop.website/index.php?route=account/register

## 2. Visual source
- **Design system:** «Booster Shop» (claude.ai artifact V3HEwfnKu11YimW4JHjzFc). Its README is the rule book: tokens, buttons, green only for purchase, focus ring.
- **Account look to match:** the light-A boards in the account canvas https://claude.ai/artifact/d5fbf671-8451-42ce-b887-4e7904ad81f3, copied in the repo under `design/account-light-A/`:
  - `A-Edit` / `A-Mobile-Edit` for form fields, labels, hints and buttons;
  - `A-Addresses-Open` for the address form.
- **Recommended:** add the new boards to that same canvas as round 4 (`A-Login`, `A-Mobile-Login`, …), so the account system stays in one place.

## 3. What to draw
Desktop 1280 and mobile 390 for every page. Header and footer are «без змін» placeholders, as on the A boards. A visitor is not signed in, so there is **no account nav**. Use a calm centered layout: one card, or card + side card on desktop.

### RD-19 — sign-in and recovery
1. **Вхід** (`A-Login`, `A-Mobile-Login`)
   - Fields: E-mail, Пароль (with a show/hide eye), «Забули пароль?» link.
   - reCAPTCHA block.
   - Primary «Увійти».
   - Side card for new visitors: «Ще немає акаунта?» + 2–3 honest benefits, see §4. Secondary or blue action «Зареєструватися».
   - Help line: «Не вдається увійти? Напишіть нам» → Telegram support bot https://t.me/BoosterShop_Support_bot.
   - States to show: default; error «Неправильний e-mail або пароль»; account temporarily locked after too many attempts (OpenCart has this limit; the wording is in §4).
2. **Забули пароль?** (`A-Forgotten`, `A-Mobile-Forgotten`)
   - E-mail field, reCAPTCHA, primary action, «Повернутися до входу».
   - State after submit: «Перевірте пошту». The text must not reveal whether the e-mail exists.
3. **Новий пароль** (`A-Reset`, `A-Mobile-Reset`)
   - The page opened from the e-mail link: new password + repeat, with the rule hint.
   - States: success → «Пароль змінено, увійдіть»; link expired/invalid → offer to request a new one.

### RD-20 — registration in two steps
4. **Крок 1 з 2 — Контакти** (`A-Register`, `A-Mobile-Register`)
   - Progress indicator «Крок 1 з 2».
   - Fields in this order: Імʼя, Прізвище, E-mail, Телефон, Пароль (with eye and rule hint).
   - Checkbox «Підписатися на новини та надходження» (newsletter, optional).
   - Required consent checkbox with a link to «Публічна оферта».
   - reCAPTCHA, primary «Створити акаунт».
   - Link «Вже є акаунт? Увійти».
   - Discount block for newcomers, see §4 and §6 question Q1.
   - States: validation errors under fields; the name hint behaves as on the A boards (hidden until a forbidden character is typed); e-mail already registered, with a «Увійти» link.
5. **Крок 2 з 2 — Адреса доставки** (`A-Register-Address`, `A-Mobile-Register-Address`)
   - The live Nova Poshta address form from RD-17, restyled as on `A-Addresses-Open`, with the profile name prefilled.
   - Primary «Зберегти адресу»; secondary «Пропустити, додам пізніше».
   - Success → the account hub.
6. **Готово** (optional small state): welcome message after step 2 or after skipping, with links to the catalog and the account. Draw it only if it does not duplicate the hub.

## 4. Copy (Ukrainian, honest, calm)
- **Benefits of an account.** Use only what the site really does:
  - збережені адреси й контакти для швидшого оформлення;
  - історія та статуси замовлень;
  - «Обране».

  **Do not** say «купувати в один клік». The current live text claims this; it is not true and must go.
- **Recovery.** OpenCart 4 e-mails a reset **link**, not a password. The live text «отримати пароль електронною поштою» is wrong. Write «Надішлемо посилання для зміни пароля».
- **reCAPTCHA label.** The live label is in English («Please complete the captcha validation below»); Ukrainian is needed, e.g. «Підтвердіть, що ви не робот». The reCAPTCHA widget itself stays as is.
- **Password rule.** The live rule is «від 4 до 40 символів, латинські літери та цифри». Show it as a hint; the designer does not change the rule.
- **Lock message.** Proposed: «Забагато спроб. Спробуйте через годину або змініть пароль». The executor will match the live limit.
- **Buttons are verbs**, with no exclamation marks and no hype.

## 5. Rules and limits (do not design around them)
- **Green only for purchase.** Sign-in, registration and save actions are **blue** (`--bs-blue`) or neutral, never green.
- **Name fields** follow the live Ukrainian-only policy: 3–32 characters, Ukrainian letters, apostrophe or hyphen between letters. Do not copy the old `pattern` from the A boards.
- **Registration does not get patronymic, Telegram or Viber.** By owner decision these live only in the profile and admin.
- **Behind-the-scenes logic stays as is; the designer changes only the look:**
  - the form tokens that fixed the «silent bounce» bug (ACC-003);
  - the newcomer discount mechanism (First15);
  - the save logic and the NP form behaviour.

  Draw nothing that needs new backend behaviour (no social login, no SMS codes, no one-click login) without flagging it as a proposal.
- **Accessibility:**
  - real labels above inputs;
  - 44–48px controls;
  - visible focus;
  - errors tied to the field, not only red colour;
  - mobile at 390 without horizontal scroll.

## 6. Questions for the owner (designer: draw the default, mark the alternative)
- **Q1 — newcomer discount on the registration page.** Earlier decisions record «Знижка −15% для нових — не чіпати». How this discount works for registration via `account/register`, as opposed to checkout registration (where it applies to the **next** order), must be verified by the executor before any promise is shown.
  - Default: draw the block with neutral text «Для нових покупців діє знижка — деталі після реєстрації» **and** a variant without the block.
  - The owner chooses; the exact wording is fixed only after the executor confirms the live behaviour.
- **Q2 — desktop layout.** One centered card, or card + side card for login. Draw the side-card version as the default.
- **Q3 — step 2.** The default is skippable. Draw it as such.

## 7. Delivery
- **Boards:**
  - on the account canvas (round 4), named as in §3, desktop + mobile, with key states;
  - each board gets a short note listing anything that needs owner confirmation.
- **Spec note:** at the end, one short note on the canvas: field order, copy table, states list, open questions.
- **Not in scope:** no Twig, no code. Claude chat copies the approved boards to `design/account-light-A/` and writes the Claude Code handoff after owner approval.
