# Report — UX-003 / UX-005: sticky header and burger (runner 3, deployed)

Date: 2026-10-04 · Executor: Claude Code · Review: `diagnostics/RD-UX-batch_runners-1-3_review_20261004.md` (Deploy OK)
Patch: `patches/UX-003-005_header-burger_20261004.php` · marker `UX-003-HEADER` · ds.css and menu-JS `?v=ux003hdr-20261004`. Written after deploy, to close review note N1.
Status: **deployed on production 2026-10-04; owner QA passed.** The «Набори та бокси One Piece» link and the mini-cart «До каталогу» behaviour followed in runner 3b. N3 (weak sticky-header shadow on category pages) is fixed in runner 7.

## Scope

As the runner header and `handoffs/handoff_UX-003-005_header-burger_claude-code_20261004.md`:

1. Sticky header at z-index 300, with a shadow after scrolling.
2. The R07MOB5 overflow root cause is fixed at its source.
3. `--bs-header-sticky-h` offsets for the checkout aside, the content-page TOC and the anchors.
4. «Каталог» label from 1024 px.
5. «Пошук» placeholder at ≤768.
6. A single × in mobile search, with a 44 px target.
7. «Фігурки та декор» in both burger groups.
8. The logo image as the burger brand.

## Files touched (SHA-256)

| File | Before (runner 2 output) | After |
|---|---|---|
| `catalog/view/stylesheet/boostershop-ds.css` | `5ba5157c98dde2f9d472aa71740b5366154a91778666a285990e67887f732d2b` | `b45d9871d76f2f4a917c34ca29cb62e55fa1a9f8d24951b20400ea753289006b` |
| `catalog/view/template/common/header.twig` | `9c2569524212001498dc7cf72d1283402cd155db4da84d7e18e495c5c6dd5e3d` | `6fe54429ede0a524da90f1d62b0882503aa70a656ea7c62b894eae63c387b734` |
| `catalog/view/javascript/patch-mobile-search-menu-redesign.js` | `feeabb5033bf5a893737058936e61019ef103c6251be990a1a401de552ee9b2a` | `ab1ff4677332f77b96e51d06ff6d9444aa36e7f901fe19d3a31ba554bed270c3` |

## Gates

Log: `evidence/logs/UX-003-005_header-burger_20261004.run1.log`. Repeated 2026-10-04 on a fresh base: identical.

- SHA guard ×3; hooks asserted unchanged.
- Hazard scan; CSS balance; Twig parse gate (header): passed.
- The JS had no syntax gate inside the runner (review N4). It was covered by the headless test. Runners 4–7 change no `.js` file; runner 4's inline init passed `node --check`.
- Repeat run `already_applied=yes`; self-delete yes.
- Measurements and screenshots: `evidence/hdr/` (`hdr_before.json`, `hdr_after.json`).

## Rollback

Do not roll back while runners 3b–7 are applied. Production backup folder: `_patch_backups/UX-003-005_header-burger_20261004-<ts>/`.

```bash
B=$(ls -d _patch_backups/UX-003-005_header-burger_20261004-* | tail -1); (cd "$B" && find . -type f) | while read f; do cp "$B/$f" "$f"; done
```

## Owner QA

Passed on 2026-10-04. The checklist is in the review file. Follow-ups went to runner 3b.
