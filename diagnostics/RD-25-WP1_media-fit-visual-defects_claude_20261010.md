# RD-25 WP1 — post-deploy visual defects in catalog media slots

Date: 2026-10-10 · Source: owner screenshots (desktop, 4-column grid) + live measurement of the served `image/cache/rd25-fit/*-700x875-fit.webp` files in the built-in browser (canvas scan, step 2 px; "content" = alpha > 16 and not RGB ≥ 235 on all channels).

WP1 r3 + F6 are live. Classification and CSS behave as specified; the defects come from three gaps in the spec itself.

## D1 — Cut-outs keep the source file's own margins (main defect)

`resizeFit()` scales the whole source canvas, including its transparent/white padding. The CSS then fits that canvas into the 84 % × 94 % box. Products whose source has wide side or top padding become width-limited and render much smaller and lower than their neighbours. This reverses my r1 acceptance of F3 ("no source trimming") — that acceptance was wrong.

| Product (category) | Fit file | Product content inside file (w × h) |
|---|---|---|
| OP-17 booster (One-Piece) | 700×615 | 42 % × 97 % |
| OP-17 booster box (One-Piece) | 670×670 | 52 % × 87 % |
| Rare Pack EB-02 (One-Piece) | 480×502 | 51 % × 97 % |
| Hot Wind Arena KR booster (Pokemon) | 700×700 | 44 % × 89 % |
| Special Card Set MEGA Gallade EX (Pokemon) | 700×700 | 63 % × 95 % |
| Mega Dream EX box (Pokemon) | 553×875 | 73 % × 89 % |
| Outlet Mix (Pokemon) | 700×700 | 94 % × 65 % |
| Toploader 35PT 1 pc (acsesuary) | 700×700 | 90 % × 65 %, top offset 22 % |
| Acrylic card stand (acsesuary) | 700×700 | 54 % × 69 %, top offset 19 % |

Reference (correct): OP-13 booster 482×875, content 85 % × 94 %.

Required: for `cut` sources, crop to the content bounding box (alpha > 16 for transparent sources; non-background for white-background sources) before building the fit image, then fit. Bump the metadata/fit version so all fit files regenerate.

## D2 — Light-grey studio photo classified `cut`

Album 400 cards, yellow (acsesuary): fit 700×466, uniform corners RGB 242–245, content 52 % × 85 %. It passes both the near-white test (≥ 240) and the aspect guard (1.50 > 1.45) → `cut` → rendered as a grey rectangle shrunk inside the slot, unlike the sibling album photos (corners 184–223 → `photo`, cover). Same rectangle on the product page (gallery is WP2 scope, legacy resize).

Required: "white background" must mean page-white (all corners ≥ 250, near-uniform), not ≥ 240. For an opaque source with a uniform background, allow `photo` (cover) outside the aspect band when the content bounding box, measured against the corner colour, fits inside the cover crop window for both 1:1 and 4:5; otherwise `cut`.

## D3 — Edge-to-edge package art with mixed corners classified `photo`

Games 7 Days Premium+ sleeves 63.5×88 (acsesuary): fit 362×500 (0.72, inside the cover band), corners: top two 255,255,255, bottom-left 33,186,109, bottom-right 231,234,231 → not all white → `photo` → cover crops the package to its top text.

Required: `photo` only when the four corners agree with each other (uniform background, tolerance to be set by the executor and reported); disagreeing corners mean edge-to-edge art → `cut`. Executor must list every reclassification (expect the two Mystery Box banners to move to `cut`; in 1:1 they render identically, in 4:5 they show the full banner instead of cropping its sides — acceptable).

## Not code

- Glory of Team Rocket booster box (Pokemon): visible light frame around the box; the fit file is 366×343 with content 96 % × 95 %, i.e. the frame is in the source image itself, and the source is low-resolution. Replace the photo.
- Album 400 product page rectangle: WP2 (gallery) scope; apply the D2 rule there too.

## Scope and sequencing

Follow-up to WP1 (r4 or WP1-fix). Touches `catalog/model/tool/image.php` (classification + fit generation) and possibly `system/library/image.php` only if crop support needs a helper; CSS unchanged. Do not run in parallel with any other open change to these files.
