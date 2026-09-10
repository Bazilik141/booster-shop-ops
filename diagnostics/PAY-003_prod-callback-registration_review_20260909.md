# Claude Review — PAY-003 PROD callback registration handoff (2026-09-09)

Reviewed artifact: `diagnostics/PAY-003_prod-callback-registration_handoff_20260909.md`
Reviewer: Claude (Opus 5) · read-only · no patch, no deploy, no setting change, no Notion/dashboard write.

## Verdict

**Accepted with corrections. Pre-cutover state confirmed. Do not cut over.**

The handoff's central claim — that PROD callback registration on the PUMB side is the remaining external dependency — is correct and is now backed by runtime evidence, not only by protocol reasoning. Three corrections below change the gate list: one gate is already satisfied, one blocker is now positively evidenced, and one cutover risk is missing from the handoff entirely.

## Evidence base

All facts below were read from the owner-provided backup `backup-9.7.2026_20-35-02_boosters.tar.gz` (dump completed 2026-09-07 20:35:05), i.e. state **after** both 2026-09-01 patches. No credential values were read out, printed, or stored; only presence/absence and lengths were checked.

| Item | Evidence |
|---|---|
| Live callback controller | `homedir/public_html/extension/pumb_credit/catalog/controller/payment/pumb_credit.php`, SHA-256 `951dcf43a4f4c2712b1f1a124fbac08ed80b57879a5f9bc91ab5cd9bf80a41dd` — **matches the SHA claimed in the handoff** |
| Callback route | `public function callback()` → `index.php?route=extension/pumb_credit/payment/pumb_credit.callback` — the URL in the handoff and in the Telegram thread is correct |
| Separate TEST route | `public function callbackTest()` → `...pumb_credit.callbackTest` (distinct endpoint, distinct `test_` settings) |
| Basic Auth | `validBasicAuth()` returns `false` when the configured user **or** password is empty; callback then answers `401 unauthorized` before any payload parsing |
| IP gate | `allowedIp()` + `ipMatches()`: exact IPv4/IPv6 and CIDR supported, `REMOTE_ADDR` only, **empty list = allow all (fail-open)** |
| Availability gate | line 37: method is offered when `payment_pumb_credit_public` is truthy **or** the session holds the preview token — a controlled PROD purchase is possible with `public=0` |
| Settings, `ocp5_setting` | `callback_ips` = 194.44.66.16 … 194.44.66.31 (all 16 addresses of the /28, enumerated); `callback_user` and `callback_password` **empty**; `test_callback_user`/`test_callback_password` present; `test_mode=1`; `public=0`; `status=1`; `terms=[3,4,5]`; `api_base=https://api.dts.fuib.com/ext-oic/galadriel/v1`; `oauth_url=https://auth.dts.fuib.com/auth/realms/pumb_ext/protocol/openid-connect/token` |

## C1 — Gate 3 is already satisfied (downgrade, not a blocker)

The handoff requires verifying that `194.44.66.16/28` is present in the PROD callback allowlist. It **is**: the PROD list contains all 16 addresses of that range as explicit entries. CIDR notation is not required for this to work; the 2026-09-01 CIDR patch is redundant-but-harmless here.

Action: move gate 3 from "blocking" to "re-confirm in the pre-cutover preflight" (the setting is owner-editable and could drift).

Note for the preflight: because an empty list is fail-open, "callback accepted" alone never proves the IP gate is active. The preflight must assert the list is non-empty, not merely that callbacks succeed.

## C2 — Missing from the handoff: PROD cutover destroys the TEST configuration

The module has **one** slot each for `api_base`, `oauth_url`, `oauth_username`, `oauth_password`. `test_mode` switches transaction flagging, status application and the token cache file — it does **not** select a different endpoint or a different OAuth credential pair. Only the callback settings (`callback_user`/`callback_password`/`callback_ips`) exist in both a TEST and a PROD variant.

Current stored endpoints are the **TEST** ones (`api.dts.fuib.com`, `auth.dts.fuib.com`).

Consequences the handoff does not state:

1. Cutover = overwriting `api_base`, `oauth_url`, `oauth_username`, `oauth_password` with PROD values. The TEST contour stops working at that moment.
2. Before overwriting, the owner must record the four current TEST values somewhere safe outside the repository, or the ability to re-run the joint test sequence is lost.
3. Setting `test_mode=0` while the endpoints still point at `dts.fuib.com` produces a half-state: real order statuses applied from a test bank environment. The two changes must be made in one admin save, endpoints first.

## C3 — The blocker is now positively evidenced, not inferred

PROD `callback_user` and `callback_password` are empty as of 2026-09-07. With `validBasicAuth()` failing closed on empty credentials, **every** PROD callback PUMB might send today answers `401` — before the IP gate, before payload parsing. Therefore:

- The registration question cannot be answered empirically until the store side has PROD callback credentials saved.
- A `401` observed before that point proves nothing about whether PUMB registered the URL.
- Handoff gates 1 and 2 (owner creates a dedicated callback pair, enters it in OpenCart) are strictly prerequisite to gate 5's confirmation being testable, not parallel to it.

## C4 — Minor corrections to the drafted PUMB message

- The conditional fallback sentence ("буде внесений у наш PROD allowlist до контрольної заявки") is no longer needed; C1 supports the affirmative sentence as written.
- The message must name the PROD route explicitly and must not be read as also registering `pumb_credit.callbackTest`; the TEST callback URL registered earlier stays as it is.
- Question 2 of the owner's 2026-09-02 message (who creates the Basic Auth credentials) is closed by the PUMB AI answer and by the module's own contract: the partner creates them. Do not re-ask it.

Corrected text to send, replacing the version in the handoff:

> Дякую за відповіді.
>
> Питання про облікові дані закрите — Basic Auth для callback створюємо ми і передамо приватно.
>
> Лишається одне: просимо **зареєструвати на боці ПУМБ наш PROD callback URL** для гібридної схеми:
>
> `https://boostershop.website/index.php?route=extension/pumb_credit/payment/pumb_credit.callback`
>
> Авторизація: Basic Auth, логін і пароль передамо приватно Олексію Берлізову. Тестовий callback URL, зареєстрований раніше, лишається без змін.
>
> Діапазон вихідних IP ПУМБ `194.44.66.16/28` з нашого боку вже дозволений.
>
> Просимо письмово підтвердити, що PROD callback URL і Basic Auth збережені на вашій стороні. Після підтвердження проведемо одну контрольну PROD-заявку.

## Revised gate order

The handoff's list stands, with these edits:

- gate 3 → moved into gate 6 (preflight re-check), no longer a standalone blocker;
- new gate 0, before everything: record the four current TEST endpoint/OAuth values outside the repository (C2);
- new gate 5a, between confirmation and preflight: switch `api_base`, `oauth_url`, `oauth_username`, `oauth_password` to PROD and `test_mode` to 0 in one admin save (C2);
- gates 1–2 explicitly precede gate 5, since confirmation is untestable without them (C3);
- gate 7 stands as written and is supported by the code: `public=0` plus the preview token is sufficient for a controlled PROD purchase.

## Trust boundary, unchanged

Valid PROD OAuth credentials govern store → bank. They are not evidence of bank → store callback registration. The PUMB representative's 2026-09-09 reply ("облікові на Стейдж та прод… з нашого боку теж все готово") speaks to the OAuth direction and does not answer the callback-registration question. Keep PAY-003 pre-cutover until written registration confirmation plus a real PROD callback that authenticates and updates local state are both in hand.
