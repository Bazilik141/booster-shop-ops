# RD-25 WP1-FIX — post-deploy photo crop diagnosis

Date: 2026-10-11
Status: diagnosis complete; fresh deployed CSS/header context required before a new patch.
Authority: owner reports r2 deployed and supplies nine annotated screenshots. Owner clarification: the whole product must remain visible; empty space is acceptable.

## Findings

The supplied screenshots contain two different outcomes. Transparent product cutouts fit completely and retain their proportions. Opaque product photographs still use cover and visibly lose product details. Warming the cache cannot change this CSS geometry.

Verified local implementation: `work/rd25-wp1-20261009/expected/catalog/view/stylesheet/boostershop-ds.css:276`, `.bs-pcard__media--photo img`, sets `object-fit: cover` with width/height 100%. Desktop slots are square; mobile slots are 4:5. The accepted r2 model classifies inside-band sources with four non-white/non-transparent corners as photo without an interior scan. Its full-source fit file preserves the photograph, but the browser crops it to the slot.

The previous handoff explicitly required photo=cover and unchanged dark photos. The current owner clarification supplies a different functional priority: preserve the whole product. This diagnosis does not retroactively claim that the accepted r2 changed that rule.

## Confirmed photo defects

Asset IDs below are Phase 0 asset IDs, not database product IDs. Matched original files were opened and visually compared against the supplied screenshots.

| Screenshot | Asset | Original dimensions | Current desktop cover loss | Visible result |
|---|---|---|---|---|
| 8, sleeves | #192 `catalog/Other/Protectory-100sht-generic.jpg` | 2204 x 1600 | About 27.4% of source width, split between sides | Fan of sleeves loses both outer edges |
| 8, acrylic-case stand | #084 `catalog/3D print/Функціональні/pidstavka-slab-tcg-01.jpg.JPG` | 3024 x 3716 | About 18.6% of source height, split top/bottom | Bottom of the orange-edged base is clipped |
| 9, three slab stands | #091 `catalog/3D print/Функціональні/Підставка під слаби PSA, CGC, BGS (без покеболу).webp` | 1024 x 768 | 25% of source width, split between sides | Outer slabs and stands are clipped |
| 9, stand on a leg | #082 `catalog/3D print/Функціональні/pidstavka-dlia-slab-na-nizhtsi-3d-druk.webp` | 1086 x 1448 | 25% of source height, split top/bottom | Base is clipped |

These percentages describe the source canvas excluded by centred cover, not the percentage of the physical product lost. Existing served fit rounding can change them slightly. Mobile cover may exclude a different axis or amount; square photos also lose side regions in a 4:5 slot.

## Cutouts and source composition

- Munikis Zero (#277), White Flare (#252), Mega Brave (#276), Inferno X (#279) are cut with alpha trim. Their measured desktop and mobile content visibility is 1.0. Square-shaped boxes are width-limited inside the 84% x 94% cut box and therefore are lower than narrow packs.
- Premium Trainer Box ex (#285), Beyond the Brave box (#201), Outlet Mix (#235), and Toploader 1 pc (#194) likewise preserve the complete measured content. Their empty space follows the product aspect; forcing equal height would require clipping, stretching, or a wider card.
- Chaos Rising (#263) and Perfect Order multipacks (#231) are already a composition of overlapping packs in the original. The renderer does not hide the lower pack regions independently: those regions are obscured in the source composition itself. A different product photograph is needed if every individual pack must be visible independently.
- The Glory of Team Rocket box photo was explicitly excluded from the earlier handoff as a source-content issue. This diagnosis does not alter that boundary or infer a new original from its screenshot.
- Faded sold-out images follow the existing 0.45 opacity rule, independently of the crop issue.

## Smallest proposed next correction

Change the existing product-photo source rule from cover to contain and align the complete photograph at the bottom of its slot. Preserve the photo classification, source trim rules, fit generation and request budget. Do not relabel product photos as transparent cutouts merely to select a different CSS rule. This removes browser cropping without adding a new image scan or regenerating metadata.

The change affects all product-card photos, including square art/Mystery Box images on mobile. Empty space becomes possible and is consistent with the owner's clarified priority. Category banners and other selectors are outside the proposed correction. Existing source photographs and already hidden/overlapping content are not reconstructed.

Fresh deployed `boostershop-ds.css`, header reference and Thumb/model context must be inspected before creating a runner, because shared CSS/header have also been edited by RD-26 and other tasks. Inspect any photo-rule overrides, edit the source rule directly, and read/replace the current header cache-bust token using the existing patch convention. No override layer or `!important` is proposed. A subsequent runner must be bound to that fresh input and have the ordinary backup/lint/idempotency/rollback gates.

## Evidence and limits

- Owner-supplied nine screenshots, in attachment order, and explicit whole-product priority.
- Original byte-preserved 286-source archive and `RD-25-WP1-FIX_phase0_evidence_20261011/proposed-assets.json` for class/crop geometry.
- Accepted r2 model and unchanged accepted CSS/Twig fixture source inspected locally.
- Public category reads through the web tool returned 502 and did not provide live HTML/CSS evidence. No claim that this proves a shopper-visible outage.
- The live deployment is owner-reported. This diagnosis is supported by screenshots and matching original assets; current server source hashes and computed browser CSS were not obtained.

No patch, original image modification, cache deletion, commit, push, deployment, database write, status update, or WP2/WP3 work was performed.
