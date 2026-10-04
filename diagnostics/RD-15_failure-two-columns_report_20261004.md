# Report — RD-15: checkout failure page «Дві колонки» (runner 2, deployed)

Date: 2026-10-04 · Executor: Claude Code · Review: `diagnostics/RD-UX-batch_runners-1-3_review_20261004.md` (Deploy OK)
Patch: `patches/RD-15_failure-two-columns_20261004.php` · marker `RD-15` · ds.css `?v=rd15-20261004`. Written after deploy, to close review note N1.
Status: **deployed on production 2026-10-04; owner QA passed.** The Telegram links moved to the support bot in runner 3b.

## Scope

As the runner header and `handoffs/handoff_RD-15_failure_claude-code_20261004.md`:

- uk-ua `checkout/failure.php`: `heading_title`, `text_failure` and `text_message` rewritten in the owner's copy.
- `failure.twig`: DS card, SVG icon, two secondary actions; the inline R-11b `<style>` is removed.
- ds.css: the RD-15 section.
- `header.twig`: token.
- Accepted notes (review): N5 — the copy also shows on stock confirm redirects (CHECKOUT-012); N6 — a language-pack reinstall reverts the strings.

## Files touched (SHA-256)

| File | Before (runner 1 output) | After |
|---|---|---|
| `catalog/view/template/checkout/failure.twig` | `dde1c82625ce998a9ab3b6eba8bafac34f8dfdf3a50352d2e95f21cb250547aa` | `baa1a0d31335e95b29de17768f7b777bb6c9d98e4105a096fbeec84060e99583` |
| `extension/ukrainian/catalog/language/uk-ua/checkout/failure.php` | `beb30efb7180aec2a83c43c9880c50f55c6ebcd25576c7a7b2aed02e1fad6b32` | `d3a3f4a0b95669eba9e74f257a5530fd580b7e7245713109b9385d296b64fc21` |
| `catalog/view/stylesheet/boostershop-ds.css` | `43ea570f684809915d72caad83095121f1a4dcda6fa52a0218b50feb8d1254f1` | `5ba5157c98dde2f9d472aa71740b5366154a91778666a285990e67887f732d2b` |
| `catalog/view/template/common/header.twig` | `071ddd87e14a1974e85734681fdb3948d0d862c233a2024e67933316bef4552d` | `9c2569524212001498dc7cf72d1283402cd155db4da84d7e18e495c5c6dd5e3d` |

## Gates

Log: `evidence/logs/RD-15_failure-two-columns_20261004.run1.log`. Repeated 2026-10-04 on a fresh base: identical.

- SHA guard ×4.
- `php -l` on the language candidate before the write and on the file after it.
- Load and `sprintf` check of the new strings.
- Hazard scan; CSS balance; Twig parse gate (failure, header): passed.
- Repeat run `already_applied=yes`; self-delete yes. Screenshots: `evidence/rd15/`.

## Rollback

Do not roll back while runners 3–7 are applied. Production backup folder: `_patch_backups/RD-15_failure-two-columns_20261004-<ts>/`.

```bash
B=$(ls -d _patch_backups/RD-15_failure-two-columns_20261004-* | tail -1); (cd "$B" && find . -type f) | while read f; do cp "$B/$f" "$f"; done
```

## Owner QA

Passed on 2026-10-04. The checklist is in the review file.
