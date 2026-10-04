# Report — UX-009: live search suggestions (runner 4)

Date: 2026-10-04 · Executor: Claude Code · Reviewer: Claude (chat), pending
Patch: `patches/UX-009_live-search_20261004.php` · marker `UX-009-LS` · ds.css `?v=ux009ls-20261004`
Handoff: `handoffs/handoff_UX-009_live-search_claude-code_20261004.md` (+ INDEX). Build: `evidence/build/ls/` (`body.php`, `section.css`).

## Scope

As handoff §4 plus the review corrections. The description is hidden in the suggestions (owner-confirmed fix for the over-tall mobile list). Module files (`extension/ps_live_search/**`) are not touched. N2 is no longer carried (shipped in 3b).

- `boostershop-ds.css`: the new section `UX-009-LS`. All its selectors are scoped to `#ps-live-search`, because the module's own stylesheet loads after ds.css.
- `boostershop-ds.css`, mobile source rule (`.bs-msearch.is-open #ps-live-search…show`): the shadow is removed; border 0, padding 0 0 12px.
- `header.twig`, init only:
  - `$.ajax` gets `timeout: 8000` and an `error` handler that ignores `abort`.
  - A reply is used only while the list is open and the input still holds its query.
  - States:
    - error / timeout / unusable JSON → one `role="alert"` row with a button that submits the existing search form;
    - no item at all → one `role="status"` row; the query is inserted with `.text()`.
  - Row classes are set after the module renders; the arrow goes on «Усі результати»; the loading row gets `role="status"` and a visually hidden «Завантаження…».

## Files touched (SHA-256)

| File | Before (3b output) | After |
|---|---|---|
| `catalog/view/stylesheet/boostershop-ds.css` | `c0feabc61b0f04407c178e0d6c87ca20343dfeb714a8e92fc4385bd6c9b62ee6` | `04b82391310bd504121897bbbcc39a10950e6fc4929479455287d78d5e211a53` |
| `catalog/view/template/common/header.twig` | `8443fdc87ea0410f229dcab606361e86aed6e5920d2774f9a0f6c7d3bf4c22b3` | `c3da822af55bde668cb88fd0c299375ecaef9c1dcc568d032bdce1b86b8e2b21` |

## Gates

- SHA guard ×2; marker; hooks asserted unchanged:
  - the input, list and container ids/classes and `data-live-search-target`;
  - the form action and hidden route/language;
  - `#bs-msearch` and `[data-bs-search-clear]`;
  - the translations.
- Hazard scan (header diff, CSS section); CSS balance; Twig parse gate on header.twig: passed. Log: `evidence/logs/UX-009_live-search_20261004.run1.log`.
- `node --check` on the extracted init script (review N4): passed.
- Repeat run `already_applied=yes`; self-delete yes. `php -l` and 8.0 compatibility scan: OK.

## Behaviour checks (`evidence/ls/`, before = 3b output)

The fixture serves module-shaped JSON. Harness note: CDP focus emulation is enabled so the site's mobile-search `focus` handler fires; without it headless Chrome never opens the overlay.

| Check | 390 | 768 | 1440 |
|---|---|---|---|
| «pokemon»: products fully visible (before → after) | 2 → 5 | 5 → 5 | 4 → 5 |
| Row height (before → after) | 255 → 74 | 134 → 68 | 161 → 74 |
| Description shown | no | no | no |
| Name lines | ≤2 | ≤2 | ≤2 |
| Font Awesome visible | 0 | 0 | 0 |
| Spinner colour | `--bs-blue` | `--bs-blue` | `--bs-blue` |
| Discounted price colour | `--bs-danger` | `--bs-danger` | `--bs-danger` |

- «zzz»: a single row reads «Нічого не знайдено за «zzz»». No per-section texts and no «Усі результати» (before: 3 texts plus a dead «Усі результати»).
- HTTP 500 and the 12 s hang (8 s timeout): both show the error row, `role="alert"`. Its button navigates to `?route=product/search&language=…&search=err`.
- Out-of-order replies (`$.ajax` stubbed; the PHP test server is single-threaded):
  - a late timeout for an old query does not replace newer results;
  - a late success for an old query does not replace the newer «nothing found»;
  - an `abort` leaves the spinner.
- Keyboard: ArrowDown focuses the first product and Enter opens it. Esc closes the list (and the mobile overlay). Category clicks and «Усі результати» hrefs are unchanged. No console errors.

## Rollback

Only while runners 5–7 are not applied:

```bash
B=$(ls -d _patch_backups/UX-009_live-search_20261004-* | tail -1); (cd "$B" && find . -type f) | while read f; do cp "$B/$f" "$f"; done
```

Then refresh the theme cache and press Ctrl+F5.

## Owner QA (production)

Run `php UX-009_live-search_20261004.php` in `~/public_html`, refresh the theme cache, press Ctrl+F5.

1. Phone: tap search and type «pokemon». At least 4 products are visible, with no description, the name on 2 lines max and the price under it. A discounted product shows two prices.
2. «zzz»: one message, no «Усі результати».
3. DevTools → Network → Offline, then type: «Не вдалося завантажити підказки» appears within 8 s. The button opens the results page.
4. Slow 3G profile: a blue spinner appears.
5. Clicks on a product, a category and «Усі результати» go where they did before. Tab, arrows and Esc work. The console shows no new errors.
6. Desktop 1440: the list sits 6 px under the field, rounded, with a shadow; it scrolls inside itself if long.
