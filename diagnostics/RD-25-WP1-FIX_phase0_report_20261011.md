# Codex Report — RD-25 WP1-FIX: Phase 0 media trim/classification diagnosis

Date: 2026-10-11
Status: **Phase 0 complete. No patch written. Await owner "go Phase 1".**

## Outcome and scope

The measured proposal fixes D1/D2/D3 within existing CSS geometry: **140 source crop boxes change**, **3 asset classes change**, and **69 assets change rendered content width or height by more than 5%**. Proposed totals: **165 cut / 121 photo** (v3:164/122). This is the 286-source archive corpus, including secondary images and logos, not 286 current product primary rows.

The exact changed-asset set is **143 sources**; the contact sheet covers all 143 plus14 unchanged required references = **157 assets**, four views each (desktop/mobile, before/after), **15 grouped PNG pages** and 4 compact required-case pages. Start with [filterable before/after contact sheet](RD-25-WP1-FIX_phase0_evidence_20261011/contact-sheet/index.html) or [packs](RD-25-WP1-FIX_phase0_evidence_20261011/contact-sheet/required-01.png), [sets and accessories](RD-25-WP1-FIX_phase0_evidence_20261011/contact-sheet/required-02.png), [albums and Pokemon Mystery Boxes](RD-25-WP1-FIX_phase0_evidence_20261011/contact-sheet/required-03.png), [3D / One Piece Mystery Boxes / extra reclassification](RD-25-WP1-FIX_phase0_evidence_20261011/contact-sheet/required-04.png).

Complete asset table: [all 286 assets — human-readable table](RD-25-WP1-FIX_phase0_evidence_20261011/assets-full.md); [CSV table](RD-25-WP1-FIX_phase0_evidence_20261011/assets.csv); [complete proposed metadata and source/slot geometry](RD-25-WP1-FIX_phase0_evidence_20261011/proposed-assets.json). Every row includes source/type, four RGBA corners, v3 reason, alpha and background bbox, proposed v4 reason, crop/trim dimensions, and both slot content W/H before/after. [all threshold measurements](RD-25-WP1-FIX_phase0_evidence_20261011/measurements-raw.json) retains the full per-source sweeps and 256-bin alpha/distance histograms.

## Fresh source gate and provenance

Owner export: `RD-25-WP1-FIX-check-20261011.tar.gz`, **10,410 bytes**, SHA256 `d90a2ce776b282fd68d5063acce5d24832860fe5b86fe4c16733641ba5c131a1`. All4 files match expected installed r3/cache-miss hashes before code analysis; [source-gate.json](RD-25-WP1-FIX_phase0_evidence_20261011/source-gate.json).

| Fresh export file | SHA256 | Gate |
| --- | --- | --- |
| `catalog/model/tool/image.php` | `9d6aaec56eaa44be072e67957e43b7365ca8f115283988d0ab7a3227db885285` | match |
| `system/library/image.php` | `b032345fa128dc867a55dbe4bf49f4070a34a2efa952525a69006fdb706875d9` | match |
| `catalog/controller/product/thumb.php` | `47e56e45e134359074aecdd3a1a50a068e8b19766b40589cb8688e077df5d2eb` | match |
| `catalog/view/template/product/thumb.twig` | `d9237b2c9c0cc21cda73a86ba6cf1ff66b7ad956f0da3c24a8e1add6518037d6` | match |

Images: re-extracted only the 286 classified sources from owner's `RD-25-WP1-followup-20261009.tar.gz`, SHA256 `075d3e77f1a30092633662b74b80585f3d0b963228075639f4b224070e36251a`. Owner confirmed in this session on 2026-10-11 that primary photos were not added/replaced since that export. Thus no newer primary source was reported; no 9/24 backup used. [asset-source-manifest.json](RD-25-WP1-FIX_phase0_evidence_20261011/asset-source-manifest.json) pins every source image's original bytes. Dimensions, actual decoded types, size, corner RGB and normalized alpha match v3 evidence for all 286; v3 classes recompute exactly. Pillow identifies 12 JPEG-compatible originals as MPO containers; the measurement uses their first JPEG frame, as the existing GD/JPEG path does, and keeps the MPO container label in the table.

Four bounded public category fetches via the web tool returned502, and one independent Python fetch of One-Piece was disconnected. This establishes an unavailable retrieval path, not a production outage. No storefront crawl or cache warming performed. Product-ID→primary-image mapping was not exported; category groups follow archive directories with named accessory sources in Other manually grouped into acsesuary, and 3D grouped there. These group labels are contact-sheet organization, not DB membership claims. Product reference labels for Mega Dream EX and Toploader follow the review's matched assets; their file contents/names are shown without inventing a primary mapping.

Read the current handoff and visual-defect diagnostic first. Where they differ, the current handoff governs: **all non-white mixed corners do not automatically mean cut**; Mystery Box banners remain photo. The earlier original RD-25 handoff is absent from this working checkout but readable on master via `git show`; its framing values agree. The named RD-25 entry is not present in current/master `design/DECISIONS.md`; the owner-requested current handoff plus measured installed source supply the values. No design/governance/status file changed.

## Root cause and crop API

- Exported `catalog/model/tool/image.php::resizeFit()` constructs an Image from the whole source and calls `resizeFit()` without a crop. Library `resizeFit()` at 232..269 computes scale using full source width/height at 237. Transparent/white margins consume the 84%×94% cut box.
- `mediaInfo()` combines outside-aspect, transparent-corner and all-RGB≥240 rules. It cannot distinguish the grey album or mixed white/coloured package corners as required by the new handoff.
- `catalog/controller/product/thumb.php` calculates emitted img dimensions from original metadata width/height. **137 fitted output dimensions change in the proposal**, so controller changes are required; emitted width/height must come from the selected crop dimensions using the same floor/min(1,700/w,875/h) calculation.
- Fresh library has `crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void` at 341..350, with exclusive bottom/right endpoints. It allocates a truecolour target but neither disables alpha blending nor saves alpha. It copies using original source width/height, relying on clipping, and provides no bounds/allocation validation.

Existing-method runtime probe (PHP 8.3.30+GD, unmodified exported library): crop(2,2,6,6) produces 4×4 but alpha 63 red becomes opaque RGB 128/0/0, and transparent background becomes opaque black. **The existing crop is unusable for transparent cut-outs**; [crop-api-probe.json](RD-25-WP1-FIX_phase0_evidence_20261011/crop-api-probe.json). Production remainsPHP8.0; no native8.0 run claimed.

Smallest Phase 1 addition: a new `Image::cropFit(int $left, int $top, int $right, int $bottom): void`, exclusive endpoints, validates positive in-bounds rectangle/allocation, prepares transparent target with blending off/savealpha on for PNG/WebP, copies exactly cropped width/height, updates internal dimensions, and releases the old resource. Leave existing crop() and resize() untouched. Call only before the catalog model's resizeFit for cut assets. This is a proposal, **no method implementation or PHP candidate has been written**.

## Threshold selection

| Parameter | Proposed value | Evidence / boundary |
| --- | --- | --- |
| Normalized opacity | content >16 | 137 transparent-corner sources. Alpha 0 includes faint edge artifacts; 115 bboxes differ from >16, two areas by >5%. >24 changes 52 bboxes; >32 changes 73. No clean universal histogram gap: retain the already reviewed >16 threshold rather than raise it. |
| Minimum content / line | 2 pixels per row or column | At 2 pixels, only 13 transparent bboxes move by one edge pixel (13 above-threshold pixels before padding). Counts 3/4/8/16 exclude 69/177/1,081/5,242 pixels before padding. |
| Crop safety padding | 2 source pixels per side, clamped | All above-threshold pixels are retained in this corpus. If filtering would remove any >threshold pixel after padding, expand to the complete strict bbox. One opaque asset (#233 MS2.jpg) needs that fallback. No arbitrary deletion of a detached product detail. |
| Page white | each RGB ≥250; alpha 255; corner channel span ≤8 | Opaque all-corner minimum values have a gap 240..251: largest below is239, smallest in page-white cluster252. Thresholds245/250/252 select the same17 uniform assets; 255 wrongly keeps only 9. 250 lies in this measured gap. Per-corner tests also drive R3. |
| Uniform corner colour | max channel span ≤8 | All17 page-white corner sets span 0..3; yellow Album 400 spans4, multi-album studio source spans5. A conservative8 covers these. Distribution has6/7 absent; 9..12 contains10 other corner sets. It is a conservative limit, not a semantic proof of studio lighting. Only unlocks the bbox safety test. |
| Background distance | max(\|R−bgR\|,\|G−bgG\|,\|B−bgB\|)>12; bg = per-channel median of corners | Sweep measured for every source. Yellow album: tolerance 2/4 catches canvas noise,6 returns x262..1358,8 extends to1230. 10..16 is the foreground plateau; 12 preserves bbox[378,90,1140,952], and raising to16 removes another few edge/shadow pixels. No global colour-distance histogram gap is asserted. |
| Cover safety | 2% of the visible source window on every edge, plus 2 px content-bbox expansion | Both centred 1:1 and 4:5 windows must contain expanded bbox. Album 400 passes; the narrow pack/photo cases fail and stay cut. Values selected from exact measured margins, not a whitelist. |
| Cover band | inclusive0.70..1.45 | Existing conservative band retained. Outside-band photo exception requires opaque uniform non-white corners and the two-slot safety test. |


Distributions and sensitivity: [threshold-distributions.json](RD-25-WP1-FIX_phase0_evidence_20261011/threshold-distributions.json), [background-threshold-sensitivity.json](RD-25-WP1-FIX_phase0_evidence_20261011/background-threshold-sensitivity.json). Their bins are counts of sources or pixels as named; no claim of a statistically unique optimum. The chosen values keep real product details at the existing reviewed opacity threshold, exclude low-opacity halos in bbox discovery, retain 2 px edge slack, and use fail-safe strict bbox expansion if row/column filtering would discard above-threshold pixels. Hard isolated noise cannot be proven distinct from a detached product detail by line counts alone; future ambiguous sources retain the larger box rather than silently lose content.

### Alpha / minimum line-count histograms

| Normalized opacity bin (137 transparent-corner assets) | Pixels |
| --- | --- |
| 0 | 52,984,322 |
| 1-4 | 129,445 |
| 5-8 | 82,804 |
| 9-16 | 104,166 |
| 17-32 | 110,747 |
| 33-64 | 158,068 |
| 65-254 | 5,367,696 |
| 255 | 76,834,538 |

| Minimum content pixels/line | Bboxes changed vs 1 | Pixels outside bbox before 2 px pad |
| --- | --- | --- |
| 1 | 0 | 0 |
| 2 | 13 | 13 |
| 3 | 31 | 69 |
| 4 | 51 | 177 |
| 8 | 87 | 1,081 |
| 16 | 107 | 5,242 |

### Page-white / corner-uniformity evidence

| Opaque minimum-corner-RGB range | Asset count |
| --- | --- |
| 0..234 | 126 |
| 235..239 | 3 |
| 240..249 | 0 |
| 250..251 | 0 |
| 252..254 | 8 |
| 255..255 | 9 |

| Opaque corner channel span | Asset count |
| --- | --- |
| 0..3 | 19 |
| 4..5 | 2 |
| 6..7 | 0 |
| 8..8 | 1 |
| 9..12 | 10 |
| 13..255 | 114 |

### Background-distance histograms / sweep

| Distance-to-corner-median bin (22 uniform opaque-corner sources) | Pixels |
| --- | --- |
| 0-2 | 4,306,946 |
| 3-4 | 901,326 |
| 5-8 | 605,586 |
| 9-12 | 327,178 |
| 13-16 | 315,688 |
| 17-24 | 590,206 |
| 25-32 | 380,402 |
| 33-255 | 8,133,873 |

| Tolerance | Uniform opaque bboxes changed vs 12 | BBox area changes >5% vs 12 | Album 400 yellow bbox |
| --- | --- | --- | --- |
| 2 | 18 | 6 | `[0,0,1536,1024]` |
| 4 | 18 | 5 | `[0,0,1534,1024]` |
| 6 | 16 | 3 | `[262,88,1358,980]` |
| 8 | 16 | 3 | `[378,90,1230,961]` |
| 10 | 10 | 0 | `[378,90,1149,953]` |
| 12 | 0 | 0 | `[378,90,1140,952]` |
| 16 | 17 | 0 | `[379,91,1137,951]` |
| 20 | 19 | 2 | `[379,91,1137,949]` |
| 24 | 20 | 4 | `[379,91,1137,949]` |
| 32 | 20 | 4 | `[379,91,1137,949]` |
| 40 | 21 | 5 | `[379,91,1137,949]` |

## Proposed deterministic rule order

1. Test each corner for page white (opaque255, each channel≥250) or transparency (normalized opacity≤16). At least one but fewer than all 4 background-like corners → **cut**, reason `mixed_page_white_or_transparent_corners`, regardless of aspect. Do not trim arbitrary nonuniform package art by subtracting its corner median.
2. All4 transparent corners → cut, reason `transparent_corners`; alpha bbox. All4 opaque page-white corners with span≤8 → cut, reason `opaque_page_white_uniform_corners`; distance bbox.
3. Outside inclusive0.70..1.45: only a wholly opaque source with all 4 non-white/non-transparent corners and span≤8 can be photo, and only when its distance bbox expanded2 px is inside both centred cover windows inset2%. Reason `uniform_non_white_safe_cover_both_slots`. Otherwise cut, `aspect_outside_cover_band_unsafe_cover`.
4. Remaining within-band non-white/mixed-non-white source → photo, `non_white_or_mixed_non_white_corners`. This preserves the 4 Mystery Box banners and 95 currently-photo3D assets.
5. Cut trim: alpha-based when transparent pixels exist; otherwise use distance bbox only for uniform page-white or eligible uniform non-white backgrounds. Unknown/mixed opaque art keeps full bounds. Remove only outer rows/columns through the final rectangular crop; never rewrite transparency or whiten backgrounds. Photo sources keep full bounds.

Metadata proposal preserves original width/height/stat fields, adds validated crop rectangle/dimensions and trim method, and bumps **RD25_MEDIA_VERSION4**. Fit key prefix becomes **rd25-fit-v2**. Keep v3 metadata/v1 fitted files on disk unused. Invalid crop metadata must cause regeneration through the retained cache-miss guard; preserve lock/atomic-publication behavior. Thumb uses crop dimensions for cut / original dimensions for photo to emit exact served fit dimensions. Existing Twig already consumes those fields, so **no Twig edit is needed**.

### Album 400 safe cover, exact data

Yellow album source:1536×1024; four corners `[240,242,241]`, `[243,243,243]`, `[242,241,242]`, `[240,239,241]`, opacity 255, span 4. These are original pixels; the earlier served-WebP measurement was approximate. Background median `[241,241.5,241.5]`; bbox atdistance12 `[378,90,1140,952]`; expanded bbox `[376,88,1142,954]`.

Desktop centred source window `[256,0,1280,1024]`, safe interior `[276.48,20.48,1259.52,1003.52]`. Mobile window `[358.4,0,1177.6,1024]`, safe interior `[374.784,20.48,1161.216,1003.52]`. Both pass, including left2%-margin clearance1.216 px after the 2 px bbox expansion. Fit rounding700×466 slightly changes the cover ratio; the proposed final content bbox stays wholly inside both slots. This narrow clearance is why exact Phase 1 GD/pixel QA is still required. No filename override used.

## Every reclassification

| ID / source | v3 → proposed v4 | Reason |
| --- | --- | --- |
| #097 `catalog/Accsesuary/albom-dlia-kartok-OnePiece-na-400-slotiv.png` | cut → photo | uniform_non_white_safe_cover_both_slots |
| #186 `catalog/Other/G7D sleeves premium plus (MTG).webp` | photo → cut | mixed_page_white_or_transparent_corners |
| #222 `catalog/Pokemon/Gemini_Generated_Image_c8wi9fc8wi9fc8wi.png` | photo → cut | mixed_page_white_or_transparent_corners |

The extra #222 asset is a Pokemon logo/illustration, visible on required sheet4. Its mixed page-white/grey corners trigger R3. Its product-primary use is unverified. Category tile/logo callers that use legacy resize are unchanged; this classification is used by catalog resizeFit callers only.

## Required-case geometry

Values below are **content W×H as% of the slot**, not content occupancy inside the source file. For photographs with nonuniform backgrounds, the measured content bbox is the entire source; a125% cover width is expected cropping, not segmented object width.

| Reference / asset ID | Class | Trimmed WxH | Desktop before → after W×H% | Mobile before → after W×H% |
| --- | --- | --- | --- | --- |
| OP-13 booster #131 | cut → cut | 472×948 | 43.98 × 88.74 → 46.34 × 93.60 | 54.98 × 88.74 → 57.92 × 93.60 |
| OP-15 booster #143 | cut → cut | 209×418 | 47.00 × 94.00 → 47.00 × 94.00 | 58.75 × 94.00 → 58.75 × 94.00 |
| OP-17 booster #122 | cut → cut | 516×1037 | 35.43 × 71.38 → 46.37 × 93.64 | 35.43 × 57.10 → 57.96 × 93.64 |
| OP-17 booster box #123 | cut → cut | 357×590 | 44.38 × 73.47 → 56.40 × 93.36 | 44.38 × 58.77 → 70.50 × 93.36 |
| EB-02 Rare Pack #135 | cut → cut | 251×491 | 43.23 × 85.22 → 47.29 × 93.23 | 43.23 × 68.18 → 59.11 × 93.23 |
| Hot Wind Arena KR booster #224 | cut → cut | 447×897 | 37.21 × 75.01 → 46.42 × 93.58 | 37.21 × 60.01 → 58.02 × 93.58 |
| Mega Gallade EX #260 | cut → cut | 632×957 | 52.75 × 80.05 → 61.59 × 93.61 | 52.75 × 64.04 → 76.99 × 93.61 |
| Mega Dream EX (review asset) #228 | cut → cut | 598×1148 | 43.57 × 84.01 → 48.55 × 93.67 | 54.46 × 84.01 → 60.69 × 93.67 |
| Outlet Mix #235 | cut → cut | 2276×1554 | 79.59 × 54.25 → 83.85 × 57.09 | 79.59 × 43.40 → 83.85 × 45.67 |
| Toploader 1 pc (review asset) #194 | cut → cut | 723×531 | 75.50 × 55.34 → 83.53 × 61.22 | 75.50 × 44.27 → 83.53 × 48.97 |
| Acrylic card stand #213 | cut → cut | 1319×1515 | 46.06 × 52.88 → 81.59 × 93.75 | 46.06 × 42.31 → 83.75 × 76.98 |
| Album 400 yellow #097 | cut → photo | 1536×1024 | 41.67 × 47.07 → 74.52 × 84.18 | 41.67 × 37.66 → 93.15 × 84.18 |
| Album 360 white #103 | photo → photo | 1254×1254 | 100.00 × 100.00 → 100.00 × 100.00 | 125.00 × 100.00 → 125.00 × 100.00 |
| Album 360 yellow #104 | photo → photo | 1254×1254 | 100.00 × 100.00 → 100.00 × 100.00 | 125.00 × 100.00 → 125.00 × 100.00 |
| G7D sleeves 63.5 x 88 #186 | photo → cut | 362×500 | 100.00 × 138.12 → 68.06 × 94.00 | 100.00 × 110.50 → 84.00 × 92.82 |
| G7D 9-pocket sheet v1 #178 | cut → cut | 428×536 | 66.45 × 84.00 → 74.36 × 94.00 | 66.45 × 67.20 → 83.22 × 84.16 |
| G7D 9-pocket sheet v2 #179 | cut → cut | 348×480 | 60.20 × 84.00 → 67.37 × 94.00 | 60.20 × 67.20 → 83.03 × 92.69 |
| Pokemon Mystery Box Standard #282 | photo → photo | 2194×2194 | 100.00 × 100.00 → 100.00 × 100.00 | 125.00 × 100.00 → 125.00 × 100.00 |
| Pokemon Mystery Box XL #283 | photo → photo | 2194×2194 | 100.00 × 100.00 → 100.00 × 100.00 | 125.00 × 100.00 → 125.00 × 100.00 |
| One Piece Mystery Box Standard #118 | photo → photo | 2194×2194 | 100.00 × 100.00 → 100.00 × 100.00 | 125.00 × 100.00 → 125.00 × 100.00 |
| One Piece Mystery Box XL #119 | photo → photo | 2048×2048 | 100.00 × 100.00 → 100.00 × 100.00 | 125.00 × 100.00 → 125.00 × 100.00 |
| Geodude #062 | cut → cut | 682×1024 | 62.52 × 94.00 → 62.52 × 94.00 | 78.15 × 94.00 → 78.15 × 94.00 |
| Ditto cup #087 | cut → cut | 1024×1535 | 62.63 × 94.00 → 62.63 × 94.00 | 78.29 × 94.00 → 78.29 × 94.00 |
| Dark 3D slab stand photo #084 | photo → photo | 3024×3716 | 100.00 × 122.86 → 100.00 × 122.86 | 101.74 × 100.00 → 101.74 × 100.00 |

OP-13/15/17 content heights after proposal are93.603/94.000/93.637% in both slots; spread0.424% relative to shortest, below±5%. OP-17 box height rises73.469→93.363% desktop,58.775→93.363% mobile. Outlet Mix and Toploader stay width-limited: taller full-box rendering is impossible without cropping or changing the approved geometry. Acrylic stand fills height on desktop and width on mobile.

Geodude and Ditto stay cut, with their complete dark rectangles unchanged; nonuniform corners plus narrow aspect do not meet the new safe-cover exception. They were already cut in v3, so the handoff's "dark3D photos unchanged (photo)" is interpreted for the 95 existing photo assets, not an instruction to crop those2 narrow references. All6 Album 360 archive variations are included to cover the ambiguous "both" references.

Glory of Team Rocket source photo frame remains content: no frame detection/removal is proposed. Source #275 (366×343, matching the visual defect report's fit dimensions despite its filename) retains its opaque light frame; only surrounding alpha padding is trimmed. Source #280 is a separate box image, also retains the frame/product pixels. The owner still replaces the framed/low-resolution source; this fix does not repair it.

## Every rendered-size change above5%

Definition: either content bbox width or height relative to slot changes by >5% **relative to its prior value**, at desktop or mobile; not5 percentage points. Below lists all 69. Negative changes for R3 package/logo sources exchange cover clipping for complete-art display. All cut-to-cut trims preserve every above-threshold content pixel; the relative scale can change slightly between axes from integer fit dimensions.

| ID / source | Desktop content W×H% before → after | Mobile content W×H% before → after |
| --- | --- | --- |
| #097 `catalog/Accsesuary/albom-dlia-kartok-OnePiece-na-400-slotiv.png` | 41.67 × 47.07 → 74.52 × 84.18 | 41.67 × 37.66 → 93.15 × 84.18 |
| #098 `catalog/Accsesuary/albom-dlia-kartok-pokemon-na-400-slotiv.png` | 63.58 × 42.29 → 83.69 × 55.62 | 63.58 × 33.83 → 83.69 × 44.49 |
| #111 `catalog/Accsesuary/Топлоадери для карток 35PT, 25 шт toploaders 2 var.png` | 69.61 × 87.72 → 74.17 × 93.47 | 69.61 × 70.18 → 83.41 × 84.09 |
| #113 `catalog/Accsesuary/Топлоадери для карток 35PT, 25 шт toploaders.png` | 71.23 × 82.97 → 80.39 × 93.65 | 71.23 × 66.38 → 83.63 × 77.95 |
| #114 `catalog/One Piece/Bandai.jpg` | 73.73 × 43.96 → 83.58 × 49.74 | 73.73 × 35.17 → 83.58 × 39.79 |
| #116 `catalog/One Piece/BS Big logo1.jpg` | 77.83 × 70.13 → 83.34 × 75.09 | 77.83 × 56.10 → 83.34 × 60.07 |
| #122 `catalog/One Piece/One Piece Card Game The World's Strongest Warriors [OP-17] - Japanese Version.webp` | 35.43 × 71.38 → 46.37 × 93.64 | 35.43 × 57.10 → 57.96 × 93.64 |
| #123 `catalog/One Piece/One Piece Card Game The World's Strongest Warriors [OP-17] booster box - Japanese Version.webp` | 44.38 × 73.47 → 56.40 × 93.36 | 44.38 × 58.77 → 70.50 × 93.36 |
| #126 `catalog/One Piece/One Piece — OP-09 — JP — Booster box.png` | 52.64 × 87.04 → 56.36 × 93.19 | 65.80 × 87.04 → 70.45 × 93.19 |
| #127 `catalog/One Piece/One Piece — OP-09 — JP — Booster display.png` | 73.50 × 71.40 → 83.32 × 80.94 | 73.50 × 57.12 → 83.32 × 64.75 |
| #128 `catalog/One Piece/One Piece — OP-09 — JP — Booster.png` | 35.49 × 71.40 → 46.56 × 93.72 | 35.49 × 57.12 → 58.20 × 93.72 |
| #129 `catalog/One Piece/One Piece — OP-13 — JP — Booster box.png` | 53.57 × 88.64 → 56.54 × 93.70 | 66.96 × 88.64 → 70.67 × 93.70 |
| #131 `catalog/One Piece/One Piece — OP-13 — JP — Booster.png` | 43.98 × 88.74 → 46.34 × 93.60 | 54.98 × 88.74 → 57.92 × 93.60 |
| #132 `catalog/One Piece/One Piece-Photoroom.png` | 70.58 × 17.78 → 83.14 × 20.95 | 70.58 × 14.22 → 83.14 × 16.76 |
| #134 `catalog/One Piece/One-Piece-Boosters-EB-02 display.png` | 69.96 × 71.27 → 83.37 × 84.94 | 69.96 × 57.02 → 83.37 × 67.95 |
| #135 `catalog/One Piece/One-Piece-Boosters-EB-02.png` | 43.23 × 85.22 → 47.29 × 93.23 | 43.23 × 68.18 → 59.11 × 93.23 |
| #137 `catalog/One Piece/one-piece-eb01-memorial-collection-rare-pack-back.png` | 44.71 × 88.19 → 47.60 × 93.74 | 55.89 × 88.19 → 59.50 × 93.74 |
| #139 `catalog/One Piece/OP JP V7-PROMO.png` | 57.81 × 94.00 → 57.81 × 94.00 | 67.31 × 87.56 → 72.26 × 94.00 |
| #144 `catalog/One Piece/OP-15 Box 2.png` | 44.80 × 74.24 → 56.50 × 93.78 | 44.80 × 59.40 → 70.63 × 93.78 |
| #148 `catalog/One Piece/Rare Pack One Piece OP-04 Kingdoms of Intrigue — зворотна сторона японського бустера.png` | 45.17 × 88.51 → 47.77 × 93.64 | 56.46 × 88.51 → 59.71 × 93.64 |
| #151 `catalog/One Piece/Rare Pack One Piece OP-06 Wings of the Captain — зворотна сторона японського бустера.png` | 43.15 × 84.60 → 47.85 × 93.83 | 53.93 × 84.60 → 59.81 × 93.83 |
| #155 `catalog/One Piece/STARTER DECK -Red Black Sabo- [ST-35] ONE PIECE CARD GAME.png` | 49.80 × 88.09 → 52.77 × 93.34 | 62.25 × 88.09 → 65.97 × 93.34 |
| #159 `catalog/One Piece/STARTER DECK ST-32, 33, 34, 35, 36 ONE PIECE CARD GAME.png` | 77.86 × 87.93 → 82.96 × 93.67 | 80.81 × 73.01 → 83.67 × 75.58 |
| #160 `catalog/One Piece/Бустер One Piece Card Game EB-03 (Японське видання).png` | 46.52 × 86.73 → 50.28 × 93.68 | 58.15 × 86.73 → 62.85 × 93.68 |
| #163 `catalog/One Piece/Бустер One Piece Card Game OP-11 (Японське видання).png` | 41.58 × 83.79 → 46.53 × 93.77 | 41.58 × 67.03 → 58.16 × 93.77 |
| #173 `catalog/One Piece/Дисплей бокс One Piece Card Game OP-11 (Японське видання).png` | 39.83 × 85.15 → 43.81 × 93.66 | 49.78 × 85.15 → 54.76 × 93.66 |
| #176 `catalog/Other/35pt-180pt-magnitni-ramki-dlya-kartok_6l74fhlsgq9cbn63n.jpg` | 40.40 × 73.08 → 51.79 × 93.68 | 40.40 × 58.46 → 64.74 × 93.68 |
| #178 `catalog/Other/arkush-na-9-kyshenok-game-7-days-9-pocket-page..png` | 66.45 × 84.00 → 74.36 × 94.00 | 66.45 × 67.20 → 83.22 × 84.16 |
| #179 `catalog/Other/arkush-na-9-kyshenok-game-7-days-9-pocket-page.png` | 60.20 × 84.00 → 67.37 × 94.00 | 60.20 × 67.20 → 83.03 × 92.69 |
| #183 `catalog/Other/Duel Masters — DM24-RP3 — JP — Booster display.png` | 68.73 × 80.64 → 79.89 × 93.64 | 68.73 × 64.51 → 83.63 × 78.42 |
| #184 `catalog/Other/Duel Masters — DM24-RP3 — JP — Booster.png` | 40.78 × 80.56 → 47.34 × 93.64 | 40.78 × 64.45 → 59.18 × 93.64 |
| #186 `catalog/Other/G7D sleeves premium plus (MTG).webp` | 100.00 × 138.12 → 68.06 × 94.00 | 100.00 × 110.50 → 84.00 × 92.82 |
| #187 `catalog/Other/G7D.jpg` | 74.29 × 84.00 → 83.14 × 94.00 | 74.29 × 67.20 → 83.16 × 75.22 |
| #194 `catalog/Other/Toploader1p (1).webp` | 75.50 × 55.34 → 83.53 × 61.22 | 75.50 × 44.27 → 83.53 × 48.97 |
| #195 `catalog/Other/toploadery-3-x-4.png` | 63.98 × 79.44 → 74.56 × 92.59 | 67.39 × 66.94 → 82.44 × 81.89 |
| #197 `catalog/Other/Yu-Gi-Oh OCG Duel Monsters QUARTER CENTURY ART COLLECTION 25th BOX.png` | 65.96 × 81.20 → 76.06 × 93.64 | 65.96 × 64.96 → 83.70 × 82.44 |
| #198 `catalog/Other/Yu-Gi-Oh OCG Duel Monsters QUARTER CENTURY ART COLLECTION 25th cards.webp` | 79.91 × 23.76 → 83.78 × 24.96 | 79.91 × 19.01 → 83.78 × 19.97 |
| #200 `catalog/Other/Yu-Gi-Oh! OCG Blazing Dominion.png` | 75.60 × 78.12 → 82.77 × 85.53 | 75.60 × 62.50 → 82.77 × 68.43 |
| #201 `catalog/Other/Yu-Gi-Oh! OCG Duel Monsters BEYOND THE BRAVE Booster Box.png` | 64.60 × 56.99 → 83.70 × 73.74 | 77.00 × 54.34 → 83.70 × 58.99 |
| #203 `catalog/Other/Yu-Gi-Oh! OCG Duel Monsters BEYOND THE BRAVE display.png` | 72.03 × 74.34 → 83.51 × 86.19 | 72.03 × 59.47 → 83.51 × 68.95 |
| #204 `catalog/Other/Yu-Gi-Oh! OCG World Premiere Pack 2024 (box).png` | 47.17 × 83.62 → 52.78 × 93.58 | 47.17 × 66.90 → 65.98 × 93.58 |
| #205 `catalog/Other/Yu-Gi-Oh! OCG World Premiere Pack 2024 BB.png` | 75.17 × 90.86 → 77.20 × 93.32 | 78.00 × 75.43 → 83.27 × 80.52 |
| #207 `catalog/Other/Акрилова підставка+кейс.png` | 49.29 × 78.44 → 58.81 × 93.83 | 49.29 × 62.75 → 73.51 × 93.83 |
| #210 `catalog/Other/Магнітний кейс для картки.png` | 47.28 × 70.91 → 62.45 × 93.81 | 47.28 × 56.73 → 78.06 × 93.81 |
| #211 `catalog/Other/Магнітний кейс для карток.png` | 76.61 × 52.11 → 83.85 × 56.97 | 76.61 × 41.69 → 83.85 × 45.57 |
| #212 `catalog/Other/Підставка для карт PSA.png` | 45.71 × 58.41 → 73.26 × 93.78 | 45.71 × 46.73 → 83.74 × 85.76 |
| #213 `catalog/Other/Підставка для оцінених карт PSA.png` | 46.06 × 52.88 → 81.59 × 93.75 | 46.06 × 42.31 → 83.75 × 76.98 |
| #221 `catalog/Pokemon/Display Pokemon TCG Munikis Zero (Японське видання).png` | 78.98 × 92.65 → 79.62 × 93.39 | 79.33 × 74.44 → 83.37 × 78.23 |
| #222 `catalog/Pokemon/Gemini_Generated_Image_c8wi9fc8wi9fc8wi.png` | 122.38 × 100.00 → 84.00 × 68.64 | 152.97 × 100.00 → 84.00 × 54.91 |
| #223 `catalog/Pokemon/Hot Wind Arena Korea BB.png` | 49.04 × 74.86 → 61.36 × 93.81 | 49.04 × 59.89 → 76.70 × 93.81 |
| #224 `catalog/Pokemon/Hot Wind Arena Korea Booster.png` | 37.21 × 75.01 → 46.42 × 93.58 | 37.21 × 60.01 → 58.02 × 93.58 |
| #227 `catalog/Pokemon/Mega Dream EX display.png` | 34.18 × 88.59 → 36.09 × 93.67 | 42.73 × 88.59 → 45.11 × 93.67 |
| #228 `catalog/Pokemon/Mega Dream EX JP Booster BOX.png` | 43.57 × 84.01 → 48.55 × 93.67 | 54.46 × 84.01 → 60.69 × 93.67 |
| #230 `catalog/Pokemon/Mega Evolution — Perfect Order Booster Bundle.png` | 73.85 × 73.99 → 83.68 × 83.80 | 73.85 × 59.19 → 83.68 × 67.04 |
| #231 `catalog/Pokemon/Mega Evolution — Perfect Order Boosters.png` | 58.95 × 86.61 → 63.77 × 93.73 | 73.69 × 86.61 → 79.71 × 93.73 |
| #235 `catalog/Pokemon/Outlet MIX.png` | 79.59 × 54.25 → 83.85 × 57.09 | 79.59 × 43.40 → 83.85 × 45.67 |
| #236 `catalog/Pokemon/PKM-JP-SPIN-BBX.png` | 76.27 × 74.35 → 83.50 × 81.47 | 76.27 × 59.48 → 83.50 × 65.18 |
| #237 `catalog/Pokemon/Pokemon Card Game Scarlet & Violet Starter Set Terrastar Loudbone EX TCG Japan.png` | 60.87 × 82.95 → 68.20 × 92.95 | 76.08 × 82.95 → 82.72 × 90.20 |
| #239 `catalog/Pokemon/Pokemon Mega Dream Ex.webp` | 45.92 × 84.00 → 51.39 × 94.00 | 45.92 × 67.20 → 64.23 × 94.00 |
| #249 `catalog/Pokemon/Pokemon-booster-box-Black-Bolt-display.png` | 70.82 × 80.21 → 82.75 × 93.67 | 70.82 × 64.17 → 83.67 × 75.77 |
| #250 `catalog/Pokemon/Pokemon-booster-box-Black-Bolt.png` | 68.46 × 55.44 → 82.98 × 67.20 | 68.46 × 44.35 → 82.98 × 53.76 |
| #252 `catalog/Pokemon/Pokemon-booster-box-White-Flare.png` | 78.14 × 76.42 → 83.50 × 81.75 | 78.14 × 61.14 → 83.50 × 65.40 |
| #253 `catalog/Pokemon/Pokemon-Ice-Glaceon-VSTAR-Special Card Set.png` | 44.65 × 88.24 → 47.53 × 93.83 | 55.81 × 88.24 → 59.41 × 93.83 |
| #260 `catalog/Pokemon/pokemon_tcg_mega_gallade_ex.png` | 52.75 × 80.05 → 61.59 × 93.61 | 52.75 × 64.04 → 76.99 × 93.61 |
| #263 `catalog/Pokemon/Pokémon TCG Mega Evolution — Chaos Rising Boosters-Photoroom.png` | 73.78 × 73.99 → 83.68 × 83.80 | 73.78 × 59.19 → 83.68 × 67.04 |
| #265 `catalog/Pokemon/Pokémon — Special Card Set Grass Leafeon VSTAR — JP — Collection Set face.png` | 44.65 × 89.06 → 47.10 × 93.83 | 55.81 × 89.06 → 58.88 × 93.83 |
| #276 `catalog/Pokemon/Бустер бокс Pokemon TCG Mega Brave (Японське видання).png` | 76.61 × 75.74 → 83.50 × 82.62 | 76.61 × 60.59 → 83.50 × 66.09 |
| #279 `catalog/Pokemon/Бустер бокс Pokémon TCG Inferno X (Японське видання)-Photoroom.png` | 79.48 × 77.99 → 83.53 × 81.97 | 79.48 × 62.39 → 83.53 × 65.57 |
| #280 `catalog/Pokemon/Бустер бокс Pokémon TCG The Glory of Team Rocket (Японське видання).png` | 73.70 × 86.44 → 79.80 × 93.56 | 79.69 × 74.77 → 83.54 × 78.35 |

All140 trim changes including ≤5% cases, corner colour/alpha, and crop bounds are in [proposed-assets.json](RD-25-WP1-FIX_phase0_evidence_20261011/proposed-assets.json) and [assets-full.md](RD-25-WP1-FIX_phase0_evidence_20261011/assets-full.md); machine subset [render-change-above-5pct.json](RD-25-WP1-FIX_phase0_evidence_20261011/render-change-above-5pct.json). Contact sheet includes every class/trim change regardless of percentage.

## Verification, limits and Phase 1 scope

**21/21 Phase 0 consistency checks passed**; [phase0-checks.json](RD-25-WP1-FIX_phase0_evidence_20261011/phase0-checks.json). These validate source gates, measurement/geometry arithmetic, byte preservation, reference coverage, and complete contact-sheet files. The existing crop method was exercised directly. They are **not runner/regression checks of a new patch**. Visually inspected all 4 compact required-case sheets: packs stay complete, album subject stays within both windows, sleeves show all package text, Mystery Box art/dark photos unchanged. Bulk pages are generated from the same measured geometry; no live/browser interactive QA or deployment proof claimed.

Contact previews use Pillow12.3.0 Lanczos resampling and current slot/framing values. Crop rectangles/fit dimensions follow proposed exact arithmetic; output pixels/encoder are not claimed identical to PHP/GD. Percentages reflect the analytical threshold-defined bbox. Byte-level source-image hashes remain intact. Crop probing used PHP 8.3.30; future runner must lint/check on productionPHP8.0 and compare actual GD crop/fit bytes/dimensions, cold/warm cache warnings, cache-miss handler restoration, composition withF6, idempotency/rollback before owner deployment.

Phase 1 proposed targets, all hash-pinned to the 4-file gate:

| Target | Proposed bounded change |
| --- | --- |
| `catalog/model/tool/image.php` | v4 classification/crop metadata, validation and v2 fit key; crop only cut files before fit; keep cache-miss guard and legacy resize byte-identical |
| `system/library/image.php` | Add cropFit helper for alpha-preserving crop; leave crop()/resize()/resizeFit() bodies intact |
| `catalog/controller/product/thumb.php` | Match img dimensions to crop-selected served fit dimensions; preserve caller-source/F6 behavior |
| `catalog/view/template/product/thumb.twig` | Read/pin for context; no edit required |

No CSS/header edit required: the 84%×94% box, bottom alignment, desktop1:1/mobile4:5 and cover geometry already produce the desired measured outcomes once input bounds/class are corrected. Existing JSON-LD, gallery,700×700 lightbox, feed and all legacy resize callers/URLs stay outside the proposed target scope. WP2/WP3 stay blocked. No DB, source-image, server, schema, SEO, checkout/payment, CRM or status mutation performed.

This session created only Phase 0 report/evidence and local diagnostic scripts under work. Prior r3/F6/cache-miss runners remain byte-identical. **No WP1-FIX patch exists, no commit/push/deploy performed. Stop here; Phase 1 requires the owner's exact "go Phase 1".**

## Evidence and reproducibility

[source-gate.json](RD-25-WP1-FIX_phase0_evidence_20261011/source-gate.json), [asset-source-manifest.json](RD-25-WP1-FIX_phase0_evidence_20261011/asset-source-manifest.json), [crop-api-probe.json](RD-25-WP1-FIX_phase0_evidence_20261011/crop-api-probe.json), [thresholds.json](RD-25-WP1-FIX_phase0_evidence_20261011/thresholds.json), [threshold-distributions.json](RD-25-WP1-FIX_phase0_evidence_20261011/threshold-distributions.json), [background-threshold-sensitivity.json](RD-25-WP1-FIX_phase0_evidence_20261011/background-threshold-sensitivity.json), [measurements-raw.json](RD-25-WP1-FIX_phase0_evidence_20261011/measurements-raw.json), [assets-full.md](RD-25-WP1-FIX_phase0_evidence_20261011/assets-full.md), [assets.csv](RD-25-WP1-FIX_phase0_evidence_20261011/assets.csv), [proposed-assets.json](RD-25-WP1-FIX_phase0_evidence_20261011/proposed-assets.json), [class-changes.json](RD-25-WP1-FIX_phase0_evidence_20261011/class-changes.json), [render-change-above-5pct.json](RD-25-WP1-FIX_phase0_evidence_20261011/render-change-above-5pct.json), [contact-sheet-index.json](RD-25-WP1-FIX_phase0_evidence_20261011/contact-sheet-index.json), [phase0-checks.json](RD-25-WP1-FIX_phase0_evidence_20261011/phase0-checks.json), [seal.json](RD-25-WP1-FIX_phase0_evidence_20261011/seal.json).

Diagnostic source scripts are copied into [evidence scripts](RD-25-WP1-FIX_phase0_evidence_20261011/scripts). They read/extract the owner image export and generate only measurements/preview/report artifacts. They do not alter production sources or generate a patch. For reproduction, run the scripts from their original `work/rd25-wp1-fix-phase0-20261011` location (or restore the evidence copies there), in order: measure.py, propose.py, contact.py, crop-probe.php with the exported source, report.py. Python runtime is Pillow12.3.0/numpy2.3.5, PHP probe is8.3.30+GD. Original image archive stays outside delivery artifacts; raw sources are in `work/rd25-wp1-fix-phase0-20261011/images`.
