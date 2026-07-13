# Codex Handoff — ACC-002: NP-structured address form in the account (replace stock free-text form)

Date: 2026-07-13. Risk: **MEDIUM-HIGH** — account address data feeds the checkout NP module (saved addresses), so a wrong data shape breaks delivery selection for returning customers → final QA includes `bs-checkout-smoke`. SEO surface: none → `bs-seo-risk-gate` n/a. **Evidence-first: Phase 0 before implementation.**

> Live-file task: account templates and NP module files are owner-server-only; anchor against LIVE files.

## 1. Task ID

ACC-002 — the account address add/edit form (`account/address` add/edit) is stock OpenCart free-text. A customer can type anything into city/address, and the NP module's saved-address logic gets unstructured data. Needed: the same NP selection UX as on checkout — область/місто з довідника, тип доставки (відділення / поштомат / адресна кур'єрська), відділення/поштомат з дропдауну — so saved addresses are structured and the checkout NP logic consumes them correctly.

Directly relevant because of the current post-registration flow: after registering, the customer is redirected to the add-address page (owner's deliberate interim flow, keep it) — so today every new customer is funneled into the *stock* form and produces a free-text address.

## 2. Context

- Checkout already has the full NP field logic (region/city/warehouse pickers, poshtomat, courier address) — the working reference implementation.
- RD-13.1B explicitly did **not** update saved addresses (receiver override is session-only). How the NP module reads a *saved account address* at checkout (which columns/custom fields it expects: `city`, `address_1`, warehouse ref, delivery type) is not established in the repo — **Phase 0 must document the exact expected data shape** before any form work.
- Existing saved addresses are free-text; they will not match the structured shape.

## 3. Goal

Account address add/edit uses NP-structured inputs; a saved address round-trips into checkout correctly (city/warehouse preselected, shipping quote works). Free-text is no longer possible for NP delivery data in the account form. Post-registration redirect flow unchanged, but now lands on the NP-structured form.

## 4. What to change

- **Phase 0 (read-only, deliver before implementation):**
  1. Document how checkout stores an NP address (fields, custom fields, formats) and how the NP module reads a saved address for an authorized customer (`account/address` → checkout preselection path).
  2. Document what the stock `account/address` form saves today and where it diverges from the shape in (1).
  3. Inventory existing saved addresses (count only, no PII in the report) and state the compatibility plan for legacy free-text rows: proposed options with trade-offs (leave as-is + checkout fallback to manual NP selection; or prompt user to re-pick on next use). **Owner decides the option before Phase 1.**
- **Phase 1 (implementation, after owner approves Phase 0):**
  - Rework `account/address` add/edit form to the NP selection UX, reusing the checkout NP components/endpoints where possible (do not fork a second copy of the NP directory logic if the module exposes reusable endpoints — verify).
  - Save in the exact shape Phase 0 documented so checkout consumes it with no checkout-side changes. If a checkout-side change turns out to be required, stop and report — that extends scope.
  - Server-side validation consistent with checkout NP rules; the post-registration redirect keeps working and lands on the new form.
- Codex should verify against actual project files at every step; nothing above about the NP module internals is confirmed in this repo.

## 5. Do not touch

- Checkout files (`checkout.twig`, `checkout-reskin.js`, `confirm.php`, `checkout.php`) — target is the account side. If Phase 0 proves a checkout-side change is unavoidable, stop and report before doing it.
- Order-creation gates (ST-2b6e, ST-2b6d, RD-13.1J), Hutko, Checkbox/fiscalization, totals/coupon/First15.
- Existing `oc_address` schema — no DB migrations without explicit owner approval in the Phase 0 review (if the shape requires a new column vs custom-field reuse, that is an owner decision point).
- Existing saved address rows (no bulk rewrites/deletion; legacy handling only per the option the owner picks).
- CRM payload shape, NP API credentials/config, `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD.

## 6. Likely files / areas (verify against LIVE)

- `catalog/view/template/account/address_form.twig`, `catalog/controller/account/address.php`, `catalog/model/account/address.php` (stock names — verify actual live theme paths).
- NP module frontend/endpoints already used by checkout (reuse target).
- The post-registration redirect implementation (owner-described crutch; find and document it in Phase 0 — it is not documented in this repo).

## 7. Acceptance criteria (measurable)

1. Account → add address: form offers область/місто from the NP directory, delivery type switch (відділення / поштомат / кур'єрська адреса), and the dependent dropdown; free-text city/warehouse is not possible for NP types.
2. Saved structured address → new checkout session as that customer: NP block preselects the saved city + warehouse, shipping quote/flow works, one test COD order completes.
3. Legacy free-text address behaves per the owner-approved option from Phase 0 (no crash, no silent wrong shipment data).
4. New registration → redirect → add address → saved → visible in account and usable in checkout (full new-customer path).
5. Address edit and delete still work; no changes visible in guest checkout.
6. Diff limited to account-side files (+ whatever Phase 0 approved); `confirm.php`/order gates untouched.

## 8. QA / smoke test

After Phase 1 deploy: §7 end-to-end as a real new customer + full 11-step `bs-checkout-smoke` (authorized with saved address: COD + one Hutko sandbox). Record max order ID + draft count before/after.

## 9. Rollback note

Back up every changed file to `_patch_backups/ACC-002_<...>-<timestamp>/...`; restore + clear `cache.*` + `template/*` via `DIR_CACHE`. If a DB change was approved and applied, the patch must print the exact reverse statement in the report before applying (no silent migrations).

## 10. Recommended status after execution

Phase 0 report → **owner decision on legacy-address option (and any DB question)** → Phase 1 → `На перевірці` → owner QA + smoke → `Готово`.
