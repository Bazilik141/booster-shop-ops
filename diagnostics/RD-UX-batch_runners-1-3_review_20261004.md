# Review — RD-UX batch, runners 1–3 (RD-14, RD-15, UX-003/005 header)

Date: 2026-10-04 · Reviewer: Claude (chat) · Author: Claude Code
Handoffs: `handoffs/handoff_RD-14-15_UX-003-005-009_INDEX_claude-code_20261004.md` and the package handoffs.

**Verdict: Deploy OK, with non-blocking notes.** Deploy strictly in order 1 → 2 → 3, with owner QA after each.

## What was checked

- All three runner files read in full. `php -l` passes on all three (PHP 8.3 lint in the container).
- No PHP 8.1+ syntax. Execution logs show each runner applying cleanly on a local copy of the 2026-10-04 live pull, in chain order: `diagnostics/RD-UX-batch_20261004_evidence/logs/`.
- Conventions:
  - C1–C5 and C7: present in every runner.
  - C6: there are no DB writes.
  - Also present: SHA-256 chain guard on every target, Twig parse gate (control parse first), Twig-hazard scan, CSS balance gate, restore-all on failure.
  - RD-15 lints the language file before and after the write, and loads it to confirm the new text survives `sprintf`.
- RD-14 must-survive items are asserted in code:
  - the GA4 block and push line;
  - the CHECKOUT-008 hooks and copy script (byte-identical);
  - the First15, IBAN, Hutko, COD and history conditions;
  - the IBAN value.
- Header package:
  - z-index 300 keeps the mini-cart drawer above the product buy bar (fixture screenshot `hdr/after_minicart_stack_390.png`);
  - `.bs-co-aside`, both `.bs-cp-toc` rules and the anchor targets are offset;
  - the R07MOB5 overflow root cause is fixed at its source;
  - hooks are asserted unchanged;
  - burger links are root-relative and the logo is in place.
- Screenshots spot-checked against the approved design: RD-14 states b (390) and c (1440), RD-15 (768), burger (390), sticky header on category and checkout (1440). All match.
- Secrets: none in the runners or in the evidence folder. The runners read `config.php` only for `DIR_STORAGE`.

## Non-blocking notes

| ID | Where | Note | Action |
|---|---|---|---|
| N1 | all three | `next=` points to per-package reports that do not exist yet. | Claude Code writes the reports in the next session. The owner QA for runners 1–3 is in this file. |
| N2 | RD-14 ds.css `.bs-success-f15-k` | 13px «Дякуємо за реєстрацію!» in `--bs-buy` on `--bs-green-soft` is 4.32:1, below AA. | Next runner, as a labelled RD-14 follow-up: colour `--bs-buy-hover` (#15803D). Same hue; the design is otherwise unchanged. |
| N3 | UX-003-005 | `category.twig` redefines `--bs-sh-sm`, so the sticky header's shadow is barely visible on category pages. | Fix in package 7, which edits `category.twig`. |
| N4 | UX-003-005 | The changed JS (`patch-mobile-search-menu-redesign.js`) has no syntax gate inside the runner; only the local headless-Chrome test covered it. A JS error would break the burger and mobile search, not the whole page. | Owner QA checks the burger and mobile search right after deploy. For later packages that change JS, run `node --check` on the candidate before building. |
| N5 | RD-15 | The new copy also shows when a COD / IBAN / cheque / free-checkout confirm loses its session (stock OpenCart redirect). | Accepted; routing is `CHECKOUT-012`. |
| N6 | RD-15 | The language file belongs to the Ukrainian language-pack extension; reinstalling the pack reverts the text. | Accepted; recorded here. |

## Owner QA (production; no staging)

Before every runner: upload it to `~/public_html` and run it. After every runner: refresh the OpenCart theme cache and press Ctrl+F5.

1. **RD-14:** the full real-order set, as decided on 2026-10-04:
   - guest + COD;
   - logged-in + Hutko (refund afterwards);
   - a new registration (First15 notice);
   - IBAN, where «Скопіювати реквізити» must copy and show the status line;
   - 6 items with long names;
   - a fresh session opening `index.php?route=checkout/success` (fallback).

   Check at 390 and 1440. GA4 `purchase` must arrive once per order (DebugView). On a phone, «Показати товари» opens and closes the list. Then run `bs-checkout-smoke`. Optional `CHECKOUT-012` evidence: cancel one Hutko payment on the Hutko page and note where the browser lands.
2. **RD-15:** open `index.php?route=checkout/failure` at 390 / 768 / 1440. Check the text, the three links (Telegram, mail, phone) and both buttons.
3. **UX-003/005:**
   - Scroll home, category, product, search, cart and checkout (stop before payment). The header stays on top, and a shadow appears after scrolling.
   - Open the burger, mobile search (also after scrolling down), the mini-cart on a product page with the buy bar, and the add-to-cart toast. Nothing is covered.
   - «Каталог» shows on desktop; mobile search reads «Пошук» and shows a single ×.
   - Both «Фігурки та декор» links open their category.
   - Run steps 1–4 of `bs-checkout-smoke`.

## Rollback

Each runner backs up every file it touches, `common/header.twig` included, to `_patch_backups/<PATCH_ID>-<YYYYMMDD-HHMMSS>/`. To roll back, copy those files back, refresh the theme cache and press Ctrl+F5.

Roll back in reverse order only, and only the last runner applied. Each runner self-deletes after success, so a repeat run needs a fresh upload; an already-applied target reports `already_applied=yes`.
