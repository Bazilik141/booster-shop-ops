# Report — RD-UX-qa-followups (runner 3b): owner-QA follow-ups after the runner 1–3 deploy

Date: 2026-10-04 · Executor: Claude Code · Reviewer: Claude (chat), pending
Patch: `patches/RD-UX-qa-followups_20261004.php` · PATCH_ID `RD-UX-qa-followups_20261004` · marker `RD-UX-QA-3B`
Handoff: `handoffs/handoff_RD-14-15_UX-003-005-009_INDEX_claude-code_20261004.md` → «Deploy log and runner 3b» + review note N2.
Build source: `diagnostics/RD-UX-batch_20261004_evidence/build/qa3b/body.php` (assembled with `build/assemble.php`).

## Scope

Exactly the four INDEX items, nothing else:

1. Support links → `https://telegram.me/BoosterShop_Support_bot`: RD-14 footer link and fallback button (`success.twig`, 2×), RD-15 button (`failure.twig`), the `text_message` link (uk-ua `failure.php`). Channel links (header icon, burger, footer) stay on `boostershop_tcg`.
2. Burger: «Набори та бокси One Piece» → `/catalog/One-Piece/one-piece-nabory-ta-boksy`, between «Бустери One Piece» and «Фігурки та декор», `bs-menu__sub`.
3. Mini-cart empty state «До каталогу»: keeps `data-bs-mini-cart-close`, gains `data-bs-mini-cart-catalog`. The new delegated handler in `cart.twig` makes sure the drawer is closed (`setMiniCartDrawerState(false)` restores body position/top and scroll synchronously), moves focus to `#bs-menu-open`, then clicks it on the next animation frame.
4. N2: `#checkout-success .bs-success-f15-k` colour `--bs-buy` → `--bs-buy-hover` (#15803D); ds.css `?v=rdux3b-20261004`.

`cart.twig` is not touched by runners 4–7; BUG-004 builds on this output.

## Files touched (SHA-256)

| File | Before (deployed post-runner-3) | After |
|---|---|---|
| `catalog/view/template/checkout/success.twig` | `8e2f322379430c8997e78bf9cb3a8e12cacf0c9e43579acfffc5028fa5dba95d` | `ba39d7b605aa99c34512efccdaa05a83d568ab1e494decc963ce72909b1fec03` |
| `catalog/view/template/checkout/failure.twig` | `baa1a0d31335e95b29de17768f7b777bb6c9d98e4105a096fbeec84060e99583` | `45c1cdeebfe1528405d01599922e64c96d167803d3b51888c9612ec1c0064379` |
| `extension/ukrainian/catalog/language/uk-ua/checkout/failure.php` | `d3a3f4a0b95669eba9e74f257a5530fd580b7e7245713109b9385d296b64fc21` | `dd5aba8d6cffaa84eab6d2ac3d8debfe8210bb14212cd2e6b569ec9d4ee6df78` |
| `catalog/view/template/common/header.twig` | `6fe54429ede0a524da90f1d62b0882503aa70a656ea7c62b894eae63c387b734` | `8443fdc87ea0410f229dcab606361e86aed6e5920d2774f9a0f6c7d3bf4c22b3` |
| `catalog/view/template/common/cart.twig` | `e3c3b270cce149207955c531f18246712b899410b0b25d5b8d62f3a6153f0032` | `9505b7aa8c57fcac69debbe7637ce80538065809dd51a9e7c836498fcfc8821f` |
| `catalog/view/stylesheet/boostershop-ds.css` | `b45d9871d76f2f4a917c34ca29cb62e55fa1a9f8d24951b20400ea753289006b` | `c0feabc61b0f04407c178e0d6c87ca20343dfeb714a8e92fc4385bd6c9b62ee6` |

Base: `rd-ux-batch-live2-20261004-1036.tar.gz` + runners 1, 2, 3, reconstructed outside the repo; byte-identical to the earlier session's post-runner-3 state.

## Gates (local run, log `evidence/logs/RD-UX-qa-followups_20261004.run1.log`)

- SHA-256 guard ×6: ok. Negative test (one byte appended to ds.css): `sha256_mismatch`, nothing written, no backup dir.
- Language file: `php -l` on the candidate before write: passed. Load check: only `text_message` changed and it survives `sprintf()`. Post-write `php -l`: passed.
- Twig parse gate (site's own `storage/vendor/twig`, spl_autoload, control parse first): success, failure, header, cart: passed.
- Hazard scan on added lines (×4): passed. CSS balance gate: passed.
- Repeat run: `already_applied=yes`. Self-delete: yes.
- Restore-all test (post-write lint forced to fail): `restore=ok`, all six files byte-identical to the base.
- `php -l` (8.3) on the runner: OK. `scripts/check-php-host-compat.php`: nothing newer than 8.0.

## Behaviour checks (headless Chrome, fixture render; `evidence/qa3b/`)

- «До каталогу» at 390 and 1440, page scrolled to 600: drawer closed, `body` position/top cleared, scroll 600 kept, burger open (`aria-expanded=true`, `bs-menu-lock` only). After Esc: body has no lock class, overflow back to normal, page scrolls (600 → 900), focus on «Каталог».
- Telegram links: success (st b, st e) and failure at 390 / 768 / 1440 point to the bot; channel links unchanged.
- N2 computed colour: `rgb(21, 128, 61)` (was `rgb(18, 136, 62)`).
- Burger One Piece list: Усі · Бустери · Набори та бокси · Фігурки та декор.

## Rollback

Only while runners 4–7 are not applied. From `~/public_html`:

```bash
B=$(ls -d _patch_backups/RD-UX-qa-followups_20261004-* | tail -1); (cd "$B" && find . -type f) | while read f; do cp "$B/$f" "$f"; done
```

Then refresh the OpenCart theme cache and press Ctrl+F5.

## Owner QA (production)

Upload to `~/public_html` and run `php RD-UX-qa-followups_20261004.php`. Then refresh the theme cache and press Ctrl+F5.

1. `index.php?route=checkout/failure`: «напишіть у Telegram» and the «Telegram» link in the text open the support bot.
2. `index.php?route=checkout/success` in a fresh session (fallback): the button opens the bot. On the next real order, the footer link «напишіть у Telegram» opens the bot. The «Дякуємо за реєстрацію!» line is a slightly darker green.
3. Burger → One Piece Card Game: «Набори та бокси One Piece» opens its category (HTTP 200).
4. Empty cart, phone and desktop, page scrolled down: open the mini-cart, press «До каталогу». The drawer closes and the burger opens. Close the burger with × and with Esc: the page scrolls normally and stays at the same position.
