# Claude Handoff Report — PAY-003 PROD callback registration and cutover state

Date: 2026-09-09

Executor: Codex · model=gpt-6-astra · effort=high — this report consolidates the existing payment-risk evidence and remaining owner/bank gates; it does not authorize or perform a production cutover.

## Outcome

The store-side PAY-003 implementation and test-contour lifecycle are substantially complete. The remaining external dependency is PROD callback registration on the PUMB side, plus matching store-side PROD callback configuration and one controlled PROD transaction before public enablement.

The owner has explicitly confirmed that the PROD API password is valid and that password rotation is not an open item. Do not carry the earlier rotation request forward.

This report contains no credentials. API OAuth credentials and callback Basic Auth credentials are separate pairs and must not be copied into repository artifacts, screenshots, group chats, or diagnostics.

## Current state

### Owner-deployed code

1. `PAY-003_pumb-final-gates_20260901.php` was run successfully by the owner on 2026-09-01.
   - Backup: `/home2/boosters/public_html/_patch_backups/PAY-003_pumb-final-gates_20260901-20260901-130722-599b2c`
   - Patch output included `assertions=ok`, `final_preflight=code_contract_ok_owner_runtime_qa_required`, `database_touched=no`, `bank_calls=no`, `done=ok`, and `self_delete=ok`.
   - It added safe admin status refresh diagnostics, guarded store cancellation, operational status display, `CREATE_FAILED` visibility, and shipment/refund controls.
   - It changed the customer wait-page label to `Сплата частинами` and removed the customer-facing manual status button while keeping automatic background polling.

2. `PAY-003_pumb-callback-cidr_20260901.php` was run successfully by the owner on 2026-09-01.
   - Backup: `/home2/boosters/public_html/_patch_backups/PAY-003_pumb-callback-cidr_20260901-20260901-133455-e9a35f`
   - Current evidenced controller SHA-256 after that patch: `951dcf43a4f4c2712b1f1a124fbac08ed80b57879a5f9bc91ab5cd9bf80a41dd`
   - Patch output included `assertions=ok`, `settings_changed=no`, `database_touched=no`, `bank_calls=no`, `runtime_callback_ip_policy=exact_ip_or_ipv4_ipv6_cidr`, `done=ok`, and `self_delete=ok`.
   - The callback allowlist now supports exact IPv4/IPv6 addresses and CIDR ranges, uses only `REMOTE_ADDR`, and does not trust forwarded headers.

Important boundary: the CIDR patch changed matching capability only. Its deployment output explicitly says `settings_changed=no`. Therefore the bank range `194.44.66.16/28` must be confirmed in the current PROD callback IP setting before cutover; the PUMB AI's statement that it is already allowed is not runtime evidence from OpenCart.

### Owner-reported functional QA

The following test-contour states/actions were observed during the joint PUMB test sequence:

- application creation returned HTTP 201 with a bank application ID;
- selected installment term reached the application correctly;
- `WAITING_CLIENT`;
- `WAITING_STORE_CONFIRM`;
- goods handover confirmation and subsequent `FUNDED`;
- full refund and `REFUND_FINISHED`;
- `CANCELED_BY_CLIENT`;
- `CANCELED_BY_STORE` through the admin action;
- `FAIL`;
- `CREATE_FAILED`, including visibility in the normal admin order list;
- manual admin status refresh and safe error correlation using HTTP status and `X-Flow-Id`.

Do not claim that the exact `REJECTED` state was observed locally. PUMB reported moving a test case to `REJECTED`, but one displayed result was `CANCELED_BY_CLIENT`; a separate high-value case produced `FAIL`.

Owner QA after the final-gates patch was reported as successful, including `CREATE_FAILED` visibility, admin action state, and the customer waiting page.

## PUMB AI answer received on 2026-09-09

The owner queried PUMB's AI assistant. Its answer states:

1. The callback URL is not sent in `POST /sf-credits` and is not configured by the partner in SmartОплата.
2. For an active or hybrid callback scheme, TEST and PROD callback URLs are separately agreed and registered on the PUMB side.
3. The partner develops the callback endpoint and supplies the callback authorization parameters.
4. If Basic Auth is used, the partner creates the callback login/password and supplies them to PUMB. These are not the bank-issued OAuth credentials used by the store to call PUMB.
5. Basic Auth is optional in the protocol, but the current Booster Shop module expects callback authentication. The intended PROD setup is therefore Basic Auth plus the bank source-IP allowlist.
6. Launching the active/hybrid scheme without separate PROD callback registration is not supported because PUMB cannot derive the callback URL from `POST /sf-credits`.
7. The cited documentation location is `Communication protocol - Digital Installment (active-passive scheme)`, section 3, `Отримання статусу заявки і Листа-гарантії Партнером`.
8. PUMB's AI directed the owner to the responsible PUMB representative for actual registration. Contact identifiers are intentionally omitted from this repository report because the owner already has the active conversation.

Trust boundary: this answers the protocol/configuration questions, but it is not evidence that PUMB has actually registered the PROD URL or credentials.

## Exact message prepared for PUMB

Send the following to the responsible PUMB representative:

> Дякую, отримали роз’яснення.
>
> Просимо зареєструвати для нашої PROD-інтеграції гібридну схему отримання статусів із таким callback URL:
>
> `https://boostershop.website/index.php?route=extension/pumb_credit/payment/pumb_credit.callback`
>
> Авторизація callback: **Basic Auth**. Окремі облікові дані для callback передамо Олексію Берлізову приватним повідомленням.
>
> Приклад виклику:
>
> ```bash
> curl --request POST \
>   --user '<callback_login>:<callback_password>' \
>   --header 'Content-Type: application/json' \
>   --data '<callback payload згідно з протоколом>' \
>   'https://boostershop.website/index.php?route=extension/pumb_credit/payment/pumb_credit.callback'
> ```
>
> Діапазон вихідних IP ПУМБ `194.44.66.16/28` з нашого боку дозволений.
>
> Просимо підтвердити після реєстрації, що PROD callback URL і Basic Auth збережені на стороні ПУМБ. Після підтвердження проведемо контрольну PROD-заявку.

Before sending the sentence that the IP range is allowed, verify the current OpenCart PROD callback IP setting. If it has not yet been saved, replace that sentence with:

> Діапазон вихідних IP ПУМБ `194.44.66.16/28` буде внесений у наш PROD allowlist до контрольної заявки.

## Remaining gates in order

1. The owner creates a new, dedicated PROD callback Basic Auth login/password pair. It must not reuse the PUMB OAuth/API credentials.
2. The owner enters that same pair directly into the PROD callback settings in OpenCart. Do not send or screenshot it to an agent.
3. Verify that `194.44.66.16/28` is saved in the PROD callback IP allowlist. CIDR parsing support is already deployed, but the setting itself was not changed by the patch.
4. Send the callback URL, authorization type, and callback credentials privately to the responsible PUMB representative using the agreed secure channel.
5. Obtain explicit PUMB confirmation that the PROD callback URL and Basic Auth credentials are registered.
6. Run a fresh read-only/redacted OpenCart preflight to verify PROD endpoints, PROD mode/public flags, callback fields present-not-empty, CIDR shape, partner/POS values, and status mappings. Do not print credential values.
7. Keep public availability disabled while performing one controlled, low-risk PROD application.
8. Verify both directions:
   - store to bank: application creation and manual GET status work;
   - bank to store: a real PROD callback reaches the endpoint, authenticates successfully, is accepted from the allowed source IP, and updates the local transaction/order state.
9. Verify the admin operational path through client confirmation and store shipment confirmation. Do not send `goods_shipped=true` until the parcel has actually been handed to the carrier.
10. Enable the payment method for all eligible customers only after the controlled PROD transaction and callback proof pass.

## Risks and stop conditions

- Do not infer callback registration from valid PROD API credentials. They govern the opposite request direction.
- Do not launch publicly on the assumption that manual polling compensates for an unregistered callback. Polling is a fallback/secondary path, not proof of the agreed active/hybrid integration.
- Stop if PUMB has not explicitly confirmed registration, if current callback fields are empty, if the CIDR setting is absent/malformed, or if a controlled callback is rejected.
- Never expose OAuth or callback credentials in Git, diagnostics, screenshots, terminal output, or public/group messages.
- No patch, database write, bank call, deployment, setting change, Notion update, dashboard update, commit, or push was performed while preparing this report.

## Acceptance criteria

- [ ] PROD callback URL is explicitly confirmed as registered by PUMB.
- [ ] Dedicated callback Basic Auth is configured on both sides without exposing it.
- [ ] Current PROD callback allowlist contains `194.44.66.16/28`.
- [ ] Fresh redacted preflight reports the required PROD fields as present and correctly shaped.
- [ ] One controlled PROD application is created successfully.
- [ ] A real bank-to-store PROD callback authenticates and updates the local state.
- [ ] Admin displays the resulting state correctly and guarded shipment action behaves correctly.
- [ ] Owner approves public enablement after the controlled PROD result.

## What Claude should do next

Review this report against the current PUMB conversation and keep the task in a pre-cutover state. Do not mark PAY-003 complete or production-live until the bank-registration confirmation and controlled PROD callback evidence are supplied. After the owner provides those redacted results, prepare the narrow final cutover/QA checklist; do not request or store credential values.
