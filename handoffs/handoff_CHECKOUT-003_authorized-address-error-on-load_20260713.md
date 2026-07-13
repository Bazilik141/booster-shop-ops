# Codex Handoff — CHECKOUT-003: address validation error shown on checkout load (mobile, authorized)

Date: 2026-07-13. Parent: checkout UX series (after ST-2b.6e / RD-13.1A/B/J). **HIGH-RISK zone** (checkout page) → `bs-checkout-smoke` mandatory before Done. SEO surface: none → `bs-seo-risk-gate` n/a. **Evidence-first: Phase 0 before any behavior change.**

> Live-file task: repo has no local copy of `checkout.twig` / `checkout-reskin.js` (owner-server-only). Codex must anchor against LIVE files. `checkout.twig` is LF-only since RD-13.1J (see CODEX_WORKFLOW.md → Patch conventions).

## 1. Task ID

CHECKOUT-003 — authorized customer on mobile opens the redesigned checkout and immediately (before touching anything) sees a red banner: «Не вдалося зберегти: Адреса повинна бути від 3 до 128 символів! Назва міста повинна містити від 2 до 128 символів! Будь ласка, виберіть регіон/область». Observed by owner 2026-07-13 on Android Chrome; at that moment the NP block had region pre-filled («Київська»), city empty, warehouse empty.

## 2. Context

- The checkout has an address micro-update/autosave path (history: ST-2b6c fixed the Hutko autoselect it triggered; RD-13.1A synced NP payload duplicates before `register.save` for guests; RD-13.1B added the authorized receiver override + session-only endpoint).
- The banner text is a server-side address validation response («Адреса повинна бути від 3 до 128 символів…» — OpenCart address rules), so something POSTs an incomplete address during page init and the client renders the failure immediately.
- Not established (Phase 0 must answer): which request fires on load for an authorized customer (route, trigger — init code, restored form state, RD-13.1B surface init, or NP module init), why it fires without user interaction, and whether it fires on desktop too (owner saw it on mobile; unverified elsewhere).

## 3. Goal

An authorized customer opening checkout with an empty/partial NP address sees no error banner on load. Validation errors appear only after real user interaction (invalid input / failed save attempt), consistent with NP module logic (city → warehouse selection order). No change to when the address is actually saved server-side unless Phase 0 proves the load-time save request itself is the defect.

## 4. What to change

- **Phase 0 (read-only evidence):** on live, reproduce as authorized user (mobile viewport) and capture: the exact request(s) fired on page load (route, payload, response), the client call site that triggers it, and the call site that renders the banner. Deliver this evidence in the report before any fix. State whether the load-time request is (a) an unwanted autosave of an empty address, or (b) a wanted refresh whose error display is the only defect.
- **Phase 1 (fix, scope depends on Phase 0):**
  - if (a): gate the autosave so it fires only after user interaction with address fields (dirty/touched state), never on init/restore;
  - if (b): keep the request, suppress rendering of validation errors until the user has interacted with the address fields or explicitly attempted to save;
  - either way, keep the interaction-driven validation UX aligned with the NP module flow (region → city → warehouse) and keep showing errors after a genuinely failed user-initiated save.
- Codex should verify against actual project files; do not assume the trigger before Phase 0 evidence.

## 5. Do not touch

- Order-creation gates: ST-2b6e `confirm::index($allow_order_write)`, ST-2b6d trusted-click gate, RD-13.1J POST confirm loader with CAPTCHA payload, ST-2b.1 deferred-confirm flow.
- `confirm.php`, Hutko `buildRequest`/amount/sign, Checkbox/fiscalization, totals/coupon/First15, order statuses, ST-2a.4 void-status net.
- RD-13.1A guest payload sync and RD-13.1B receiver override behavior (they may be *inspected*; changing them requires stopping and reporting first).
- Server-side address validation rules themselves (3–128 chars etc.) — display/trigger logic only.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD, DB schema.

## 6. Likely files / areas (verify against LIVE)

- `catalog/view/template/checkout/checkout.twig` — address block init, autosave triggers, error-banner rendering.
- `catalog/view/javascript/checkout-reskin.js` — RD-13.1B-era surfaces, init code.
- Possibly the NP module frontend assets (read-only unless Phase 0 proves otherwise).
- Codex should verify against actual project files; re-grep live for every caller of the address-save route found in Phase 0.

## 7. Acceptance criteria (measurable)

1. Authorized user, empty/partial saved address, fresh checkout open (mobile viewport and desktop): **no** error banner visible on load; Network shows no failed address-save request on load (or the request exists but its validation errors are not rendered — per chosen Phase 0 path, stated explicitly in the report).
2. Authorized user selects region → city → warehouse: selection saves without error; refresh shows the saved values.
3. Authorized user enters an invalid address / clears city and triggers save: the same validation banner appears (error path still works).
4. Guest checkout behavior unchanged (RD-13.1A payload sync still passes its QA items).
5. No new request to `confirm.confirm` or any order-producing route on page load (re-check the ST-2b6e invariant: refresh ×3 + tab restore = zero new orders).
6. Diff limited to client-side display/trigger logic files listed in the report.

## 8. QA / smoke test

Full 11-step `bs-checkout-smoke` after deploy (authorized COD + one Hutko sandbox), plus §7 checks on a real mobile device (owner) and desktop. Record max order ID + status-0 draft count before/after.

## 9. Rollback note

Patch must back up every changed file to `_patch_backups/CHECKOUT-003_<...>-<timestamp>/...` before writing; restore those files and clear `cache.*` + `template/*` via `DIR_CACHE` to roll back. Preserve each target's current EOL style (`checkout.twig` = LF).

## 10. Recommended status after execution

Phase 0 report → owner review → Phase 1 patch → `На перевірці` → owner mobile+desktop QA + smoke → `Готово`.
