# Codex Handoff — RD-13.1J: restore guest CAPTCHA confirm payload in checkout.twig (guest checkout 422)

Date: 2026-07-13. Parent: RD-13.1 (guest checkout hardening). **HIGH-RISK** (checkout / order creation) → `bs-checkout-smoke` mandatory before Done. SEO surface: none → `bs-seo-risk-gate` n/a.

> Live-file task: this repo has no local copy of `checkout.twig` (owner-server-only). Codex must anchor against the LIVE file, not backups. `confirm.php` must NOT be modified — it already carries RD-13.1C server validation + ST-2b.6e write gate.

## 1. Task ID

RD-13.1J — guest checkout is broken on live: the place-order click sends the old **GET** `.load('index.php?route=checkout/confirm.confirm...')` without the RD-13.1C CAPTCHA payload. Server-side RD-13.1C validation in `confirm.php` correctly rejects it with **422 Unprocessable Entity**, the user sees «Не вдалося створити замовлення. Оновіть сторінку і спробуйте ще раз», and the reCAPTCHA widget expires («Час перевірки сплив»). Confirmed by owner on live 2026-07-13 ~16:34 (DevTools: GET confirm.confirm, X-Requested-With: XMLHttpRequest, 422; guest, COD selected, captcha solved).

## 2. Context

- RD-13.1C (2026-07-12) patched BOTH halves of the guest CAPTCHA gate:
  - `checkout.twig`: replaced the GET `.load()` confirm loader with `$.ajax POST` sending `#form-register` `serializeArray()` + explicit `g-recaptcha-response`, plus a client pre-check («Підтвердьте, що ви не робот...») and a `data-bs-captcha-error` handler on error responses.
  - `confirm.php`: `confirm()` validates the register-page captcha for guests and returns 422 + `<div ... data-bs-captcha-error="1">` on failure.
- ST-2b.6e (2026-07-12, deployed and owner-QA-passed 2026-07-13) additionally made `confirm::index()` read-only by default; only `confirm()` calls `index(true)`. The 2b6e report already flagged this exact mismatch from the fresh backup: `confirm.php` contains RD-13.1C validation, live `checkout.twig` does not contain the RD-13.1C POST payload.
- The server half is therefore live and correct; the client half is missing. Which later action overwrote the RD-13.1C twig change (visual patches RD-13.1D–I of the same day also touch `checkout.twig`, or a restore from an older backup) is NOT established. The exact RD-13.1C twig insert is preserved in this repo: `patches/RD-13.1C_guest_captcha_final_gate_20260712.php` (`$twigAnchor` / `$twigInsert`).

## 3. Goal

Guest checkout works again end-to-end: the trusted place-order click sends the CAPTCHA token via POST to `confirm.confirm`, server validation passes, exactly one order is created. Restore client/server parity with the RD-13.1C contract — no new validation logic, no server changes.

## 4. What to change

- **Phase 0 (evidence, read-only):** on live, capture the current confirm-loader block in `checkout.twig` and compare with the RD-13.1C `$twigInsert`. Inspect `_patch_backups/` timestamps (RD-13.1C vs RD-13.1D–I vs any manual restores) and state in the report which action most plausibly reverted the twig half — or record it as undetermined. Verify whether RD-13.1D–I visual changes and the ST-2b.6d trusted-click gate are currently present in the live twig, so re-applying the loader does not clobber them and vice versa.
- **Phase 1 (fix):** re-apply the RD-13.1C client half to the live `checkout.twig` confirm loader: POST `$.ajax` with `#form-register` payload + `g-recaptcha-response`, client pre-check for empty captcha, `data-bs-captcha-error` detection in the error handler. Reuse the RD-13.1C insert as the contract; adapt anchors to the CURRENT live twig (it has drifted since RD-13.1C was written — do not assume the old anchor matches).
- **Error UX (in-contract polish, no scope creep):** on a captcha-rejected 422, the user must get the captcha-specific message and be able to re-solve the widget and retry WITHOUT reloading the page (reset/expire handling of the widget state). The current dead-end «Оновіть сторінку» message on captcha failure is part of the bug being fixed.
- Patch must follow repo conventions: unique anchors with fail-on-≠1, marker for idempotency, backup before write, self-delete, and template/`cache.*` cleanup in the run command (twig change requires cache clear, unlike 2b6e).

## 5. Do not touch

- `catalog/controller/checkout/confirm.php` — RD-13.1C validation + ST-2b.6e `index($allow_order_write)` gate stay exactly as deployed. If Codex believes a server change is required, stop and report instead.
- `catalog/controller/checkout/checkout.php`, ST-2b.6d trusted-click gate, ST-2b.1 deferred-confirm flow, `bsCheckoutConfirmSubmitting` guard semantics.
- `register.save` autosave path — it stays CAPTCHA-free by design (RD-13.1C decision); do not add captcha there.
- RD-13.1D–I visual/CSS changes in `checkout.twig` — preserve as-is.
- Hutko `buildRequest`/amount/sign, Checkbox/fiscalization, Nova Poshta, totals/coupon/First15, order statuses, ST-2a.4 void-status net, CRM payload, DB schema.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD.

## 6. Likely files / areas (verify against LIVE)

- `catalog/view/template/checkout/checkout.twig` — confirm loader inside the deferred place-order handler (likely `bsCheckoutLoadConfirmAndSubmit`-area; verify current name/shape on live).
- Reference only (do not modify): `patches/RD-13.1C_guest_captcha_final_gate_20260712.php` in this repo — exact prior twig insert and confirm.php contract.
- Codex should verify against actual project files; re-grep the live twig for every `confirm.confirm` caller before assuming a single call site.

## 7. Acceptance criteria (measurable)

1. Guest + captcha solved + real click: DevTools shows **POST** `index.php?route=checkout/confirm.confirm&language=...` with `g-recaptcha-response` non-empty in payload → **200**, payment/confirm markup rendered, exactly **one** new order row, success page reached (COD).
2. Guest + captcha NOT solved: client blocks with «Підтвердьте, що ви не робот...», **no** `confirm.confirm` request in Network, no order row.
3. Guest + expired token (wait >2 min after solving, then click): server 422 is surfaced as the captcha-specific message, widget can be re-solved and a retry click succeeds **without page reload**; failed attempt creates no order row.
4. Logged-in customer: flow unchanged, one click → one order (captcha validation not triggered server-side).
5. ST-2b.6e invariant intact: with a complete guest session, 3× refresh + tab close/reopen create **zero** order rows (re-run of the 2b6e QA repro).
6. `confirm.php` diff is empty; only `checkout.twig` changed.

## 8. QA / smoke test

Full 11-step `bs-checkout-smoke` after deploy, plus the guest-specific checks in §7 (COD once, Hutko sandbox once as guest: one order, correct amount, redirect, fiscal behavior). Record max order ID + status-0 draft count before/after each run.

## 9. Rollback note

Patch must back up `checkout.twig` to `_patch_backups/RD-13.1J_<...>-<timestamp>/catalog/view/template/checkout/checkout.twig` before writing. Manual rollback: restore that file, then clear `cache.*` + `template/*` via `DIR_CACHE` (same command pattern as RD-13.1C/ST-2b.6d run commands). Note: rollback returns live to the current broken-guest state, not to a working one.

## 10. Recommended status after execution

`На перевірці` → owner runs §7/§8 manually on live → only then `Готово`. If Phase 0 shows the RD-13.1C twig half was overwritten by one of RD-13.1D–I, file that as a process note (patch-ordering hazard on same-file same-day patches) in the report.
