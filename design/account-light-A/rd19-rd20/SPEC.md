# RD-19 + RD-20 — approved design spec (sign-in, password recovery, two-step registration)

Approved by the owner 2026-10-29 on the account canvas
https://claude.ai/artifact/d5fbf671-8451-42ce-b887-4e7904ad81f3, round 4
(version `1793293749-fa9d`). Light variant A, same system as RD-16/17/18.

Files in this package:

| Path | What |
|---|---|
| `../A-*.dc.html` (12 files listed in `../README.md`) | Claude Design sources, exact copies of the approved boards |
| `preview/*.html` | The same boards as standalone static HTML — open in a browser at the board width (1280 desktop, 390 mobile) |
| `screens/*.png` | Full-page renders of the previews (desktop 1×, mobile 2×), Manrope loaded |
| `../../assets/logo/logo-unit-booster-shop-header.svg` | Logo used in the board header (see «Not part of this task») |

How to read a board: everything above the dark footer is the page. The grey area
below the footer, titled «Нижче — не частина сторінки», holds the extra states
and the board notes; it is not page content.

## 1. Pages and boards

| Page | Route (verify) | Desktop | Mobile | Task |
|---|---|---|---|---|
| Вхід | `account/login` | `A-Login` | `A-Mobile-Login` | RD-19 |
| Забули пароль? | `account/forgotten` | `A-Forgotten` | `A-Mobile-Forgotten` | RD-19 |
| Новий пароль | the reset page opened from the e-mail link | `A-Reset` | `A-Mobile-Reset` | RD-19 |
| Реєстрація, крок 1 з 2 | `account/register` | `A-Register` | `A-Mobile-Register` | RD-20 |
| Реєстрація, крок 2 з 2 — адреса | after a successful step 1 | `A-Register-Address` | `A-Mobile-Register-Address` | RD-20 |
| Кабінет після реєстрації (тост) | `account/account` | `A-Register-Done` | `A-Mobile-Register-Done` | RD-20 |

Common to all six pages:
- Live header, breadcrumbs and footer stay as they are. The boards redraw them from the live site (2026-10-29) only for context.
- Visitor is signed out (except the post-registration hub): no account navigation.
- One white card (14px radius, `--bs-sh-sm`-like shadow) on the `--bs-bg` page ground; desktop content column max 1040px, card max 520–720px; mobile 16px gutters.
- Primary actions are blue `--bs-blue` (#1E3A8A), secondary are outline or neutral. **No green** on these pages (the header cart button is the only green element, unchanged).
- Fields 48px tall, labels above inputs, 44px minimum tap targets, visible 2px blue focus ring, errors tied to the field (`aria-invalid`, `aria-describedby`) with an icon and text, never colour alone.
- Breadcrumb current item: «Вхід», «Забули пароль?», «Новий пароль», «Реєстрація» (live login currently says «Авторизація»).

## 2. Field order

**Вхід.** E-mail → Пароль (show/hide eye; «Забули пароль?» link right of the label on desktop, under the field on mobile) → reCAPTCHA block with the label «Підтвердіть, що ви не робот» → «Увійти» (full width). Help line under the form: «Не вдається увійти? Напишіть нам у Telegram.» → https://t.me/BoosterShop_Support_bot. Desktop side card (mobile: card under the form): «Ще немає акаунта?», three benefits, outline button «Зареєструватися».

**Забули пароль?** «‹ Повернутися до входу» → H1 → lead → E-mail → reCAPTCHA → «Надіслати посилання».

**Новий пароль.** Новий пароль (eye, rule hint) → Повторіть пароль (eye) → «Зберегти пароль».

**Реєстрація, крок 1 з 2.** Progress «1. Контакти / 2. Адреса доставки» + «Крок 1 з 2» → H1 «Реєстрація» → Імʼя | Прізвище (one row on desktop) → E-mail (hint) → Телефон (hint) → Telegram (необовʼязково) | Viber (необовʼязково) (one row on desktop) → Пароль (eye, rule hint) → checkbox «Підписатися на новини» (необовʼязково) → required consent checkbox with the «Публічної оферти» link → reCAPTCHA → «Створити акаунт» → «Вже є акаунт? Увійти». Desktop side card «Що дає акаунт» with three benefits and the newcomer discount block; on mobile the discount block sits under the H1, before the fields, and the benefits card goes under the form.

No patronymic on step 1.

**Реєстрація, крок 2 з 2 — адреса доставки.** Info notice «Акаунт створено. Залишилося додати адресу доставки.» → progress (step 1 ticked) + «Крок 2 з 2» → H1 «Адреса доставки» → lead → «Отримувач: <імʼя прізвище з кроку 1>» (text, no fields) → Область | Місто (one row on desktop, Область first) → Відділення / поштомат → bordered block «Адресна доставка» with hint «Необовʼязково, якщо обрано відділення або поштомат.»: Вулиця → Будинок | Квартира (необовʼязково) (one row on both widths) → По батькові (необовʼязково), hint «Нова пошта може попросити його для адресної доставки.» → checkbox «Зробити основною адресою» (checked) → «Зберегти адресу» (blue) + «Пропустити, додам пізніше» (neutral). No «Країна» field, no name fields.

**Кабінет після реєстрації.** The approved hub (`A-Hub` / `A-Mobile-Hub`) plus a one-time toast:
- desktop: 300px white card under the header at the right, green-soft check icon, title «Акаунт створено», text «Ласкаво просимо до Booster Shop.», no close button, auto-hides after 4 s (same component as the RD-12 «Товар у кошику» card);
- mobile: full-width strip under the header, text «Акаунт створено. Ласкаво просимо до Booster Shop.», close button 44px, auto-hides after 4 s (same component as the RD-12 mobile toast; board uses #15803D for 5:1 contrast — use the live RD-12 colour if it differs);
- shown once after registration (after «Зберегти адресу» or «Пропустити»), never on later visits.

## 3. Copy table (verbatim)

| Key / place | Text |
|---|---|
| Login H1 / lead | «Вхід» / «Увійдіть, щоб бачити свої замовлення, адреси та обране.» |
| Login labels | «E-mail», «Пароль», «Забули пароль?» |
| reCAPTCHA label (all forms) | «Підтвердіть, що ви не робот» (the Google widget itself unchanged) |
| Login button | «Увійти» |
| Login help | «Не вдається увійти? Напишіть нам у Telegram.» («Напишіть нам» is the link) |
| Side card title | «Ще немає акаунта?» |
| Benefits (login and register) | «Збережені адреси й контакти — оформлення замовлення швидше» · «Історія та статуси замовлень в одному місці» · `«Обране» — щоб повернутися до товарів пізніше` |
| Side card button | «Зареєструватися» |
| Login error | «Неправильний e-mail або пароль.» (do not say which field is wrong; e-mail kept, password cleared) |
| Login lock | «Забагато спроб. Спробуйте через годину або змініть пароль.» + outline button «Змінити пароль» → forgotten. «через годину» only if the live lock really is one hour; otherwise state the live period |
| After reset (on login) | «Пароль змінено. Увійдіть з новим паролем.» |
| Forgotten H1 / lead | «Забули пароль?» / «Вкажіть e-mail, з яким ви реєструвалися. Надішлемо посилання для зміни пароля.» |
| Forgotten button / back | «Надіслати посилання» / «Повернутися до входу» |
| Forgotten result (any e-mail) | «Перевірте пошту» · «Якщо акаунт з адресою <e-mail> існує, ми надіслали на неї посилання для зміни пароля.» · «Лист може надійти за кілька хвилин. Не бачите його — перевірте папку «Спам» або спробуйте ще раз.» · button «Повернутися до входу» |
| Reset H1 / lead | «Новий пароль» / «Придумайте новий пароль для акаунта <e-mail>.» (mobile and fallback: «Придумайте новий пароль для свого акаунта.») |
| Reset labels / button | «Новий пароль», «Повторіть пароль» / «Зберегти пароль» |
| Password rule hint | «Від 4 до 40 символів: латинські літери та цифри.» (live rule; do not change the rule) |
| Password error | «Пароль має містити від 4 до 40 символів: латинські літери та цифри.» |
| Confirm error | «Паролі не збігаються.» |
| Reset success | «Пароль змінено» · «Тепер увійдіть з новим паролем.» · button «Увійти» |
| Reset link invalid | «Посилання недійсне» · «Термін дії посилання минув, або ним уже скористалися. Запросіть нове — це займе хвилину.» · button «Надіслати нове посилання» |
| Register progress | «1. Контакти» / «2. Адреса доставки» (mobile «2. Адреса»), eyebrow «Крок 1 з 2» / «Крок 2 з 2» |
| Register H1 | «Реєстрація» |
| Register labels | «Імʼя *», «Прізвище *», «E-mail *», «Телефон *», «Telegram необовʼязково», «Viber необовʼязково», «Пароль *» |
| Register hints | E-mail: «Це ваш логін. Сюди надходитимуть листи про замовлення.» · Телефон: «Формат: +380 XX XXX XX XX» · Telegram placeholder «@username» · Viber placeholder «+380 XX XXX XX XX» |
| Name hint / error (hidden until a forbidden character, as on the A boards) | «3–32 символи: українські літери; апостроф або дефіс — лише між літерами.» |
| Newsletter | «Підписатися на новини» + «необовʼязково» |
| Consent | «Я погоджуюся з умовами Публічної оферти *» (link to the live «Публічна оферта» page) |
| Consent error | «Щоб створити акаунт, погодьтеся з умовами оферти.» |
| E-mail taken | «Цей e-mail уже зареєстровано. Увійти або відновити пароль.» (both are links) |
| Phone error | «Вкажіть номер у форматі +380 XX XXX XX XX.» |
| Register button / link | «Створити акаунт» / «Вже є акаунт? Увійти» |
| Newcomer discount block | «Для нових покупців діє знижка — 15% на перше замовлення після реєстрації.» (blue-soft block with tag icon; owner text) |
| Step 2 notice | «Акаунт створено. Залишилося додати адресу доставки.» |
| Step 2 H1 / lead | «Адреса доставки» / «Збережемо її, щоб наступне замовлення оформлювалося швидше. Можна пропустити й додати пізніше в кабінеті.» (mobile: «Збережемо її для швидшого оформлення. Можна пропустити й додати пізніше.») |
| Step 2 recipient | «Отримувач: <імʼя прізвище>» |
| Step 2 labels | «Область *», «Місто *» (placeholder «Почніть вводити назву»), «Відділення / поштомат *» («Спочатку оберіть місто»), legend «Адресна доставка», «Вулиця», «Будинок», «Квартира необовʼязково», «По батькові необовʼязково», «Зробити основною адресою» |
| Step 2 hints | «Необовʼязково, якщо обрано відділення або поштомат.» · «Нова пошта може попросити його для адресної доставки.» |
| Step 2 errors | «Оберіть місто зі списку.» · «Оберіть відділення або поштомат.» (or the live NP form messages if they differ) |
| Step 2 buttons | «Зберегти адресу» / «Пропустити, додам пізніше» |
| Hub toast | «Акаунт створено» + «Ласкаво просимо до Booster Shop.» |

Live language-file wording that must disappear: «купувати в один клік», «отримати пароль електронною поштою», «Please complete the captcha validation below», «Зареєстрований клієнт», «Продовжити» on the register teaser.

## 4. States per page

- **Вхід:** default · wrong e-mail or password · temporarily locked · message after password change.
- **Забули пароль?:** default · «Перевірте пошту» — identical for existing and unknown e-mails (owner decision Q4; this is a behaviour change).
- **Новий пароль:** default · field errors (rule, mismatch) · password changed · link invalid or used.
- **Реєстрація 1:** default with the discount block · field errors (name, phone, password, consent) · e-mail already registered.
- **Реєстрація 2:** default · required NP fields missing (Місто, Відділення). Skippable.
- **Кабінет:** one-time toast after step 2.

## 5. Owner decisions (2026-10-29)

1. Newcomer discount text: «15% на перше замовлення після реєстрації» (shown only if the live First15 flow really grants it after `account/register`).
2. Login layout: card + side card.
3. Address step is skippable. QA must include a registration without an address.
4. Password recovery answers the same for any e-mail.
5. No separate «Готово» page: one-time toast on the hub.
6. The section name is «Обране».
7. Step 2: no name fields (taken from step 1); Область before Місто; no «Країна»; address delivery split into Вулиця / Будинок / Квартира; optional По батькові inside the address block.
8. Step 1: optional Telegram and Viber after the phone.

## 6. Not part of this task

- Header, footer and the logo shown on the boards. The boards already show the new canonical logo (`design/BRAND.md`), but the site logo migration is a separate task.
- The approved round-3 account boards that still say «Улюблене».
