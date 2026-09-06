# 3D catalogue import review

Date: 2026-09-02

Review only; no live writes. Canonical identity decisions were approved on 2026-09-05 and are recorded in `handoffs/handoff_3DP-CATALOG-CANONICAL-DECISIONS_claude-to-codex_20260905.md`.

72 products: 62 active, 10 inactive. Consumables tab excluded. Opening stock: zero.

Owner exclusions: Брелоки row 3 (ChBS three-piece set) and row 13 (One Piece three-piece set). Owner price override: FIG-NAMI-201 / Nami L = RRP 750 UAH, buyout 500 UAH.

Operational cost comes from actual manufactured batches, consumed FIFO. Source D and N are retained as planning estimates; neither creates inventory or determines historical sale cost. Blank inactive-product prices remain unresolved. A proposed SKU requires a collision check before assignment.

The source's A:Q data and fingerprints are retained in import-manifest.json. Owner price overrides preserve the original cells and have separate provenance. Source names below remain matching evidence; canonical names, articles and status overrides are applied only through `migration-payload.json`. Historical issue labels in this table describe the original intake and are resolved by the canonical-decision handoff unless explicitly stated otherwise.

| Source tab / row | Product | Proposed SKU | Active | RRP | Buyout | Single estimate | Batch unit estimate | Issues |
|---|---|---|---|---:|---:|---:|---:|---|
| Брелоки / 4 | Брелок Ч | BR-CHARM-100 | Yes | 30 | 10 | 5.51 | 4.60 | — |
| Брелоки / 5 | Брелок Б | BR-BULB-100 | Yes | 30 | 10 | 5.67 | 4.92 | Owner status override 2026-09-05 |
| Брелоки / 6 | Брелок С | BR-SQUIR-100 | Yes | 30 | 10 | 5.65 | 4.17 | Owner status override 2026-09-05 |
| Брелоки / 7 | Брелок Мью | BR-MEW-100 | Yes | 30 | 15 | 7.32 | 5.70 | — |
| Брелоки / 8 | Брелок Пікачу | BR-PIKA-100 | Yes | 40 | 15 | 8.09 | 6.61 | Owner status override 2026-09-05 |
| Брелоки / 9 | Брелок Умбреон стоячий | BR-UMBRE-100 | Yes | 50 | 20 | 15.32 | 6.87 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Брелоки / 10 | Брелок Умбреон сидячий | BR-UMBRE-110 | Yes | 50 | 20 | 13.68 | 4.75 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Брелоки / 11 | Брелок Умбреон обдовбаний | BR-UMBRE-120 | Yes | 50 | 20 | 13.16 | 6.44 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Брелоки / 12 | Брелок-клікер Чармандер | BR-CHARM-200 | Yes | 120 | 70 | 43.12 | 26.40 | — |
| Брелоки / 14 | Брелок One piece Шляпа вусата | BR-OPMUS-100 | Yes | 50 | 20 | 13.1 | 9.64 | — |
| Брелоки / 15 | Брелок One piece Шляпа солом'яна | BR-OPSTR-100 | Yes | 50 | 20 | 11.7 | 8.24 | — |
| Брелоки / 16 | Брелок One piece фрукт | BR-OPFRT-100 | Yes | 50 | 20 | 16.67 | 13.22 | — |
| Брелоки / 17 | Брелок One piece "One piece" | BR-OP-100 | Yes | 60 | 35 | 23.03 | 19.64 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Брелоки / 18 | Брелок One piece кораблик | BR-OPSHP-100 | Yes | 60 | 40 | 28.09 | 21.60 | — |
| Брелоки / 19 | Брелок One piece череп | BR-OPSKL-100 | Yes | 50 | 20 | 10.87 | 7.96 | — |
| Брелоки / 20 | Брелок Дітто крутиться | BR-DITTO-400 | Yes | 70 | 30 | 18.05 | 16.27 | — |
| Брелоки / 21 | Брелок клікер покебол | BR-PKBL-200 | Yes | 120 | 70 | 36.42 | 29.83 | — |
| Підставки для карток / 3 | Підставка мала | ACC-3D-PKM-110 | Yes | 40 | 25 | 19.73 | 26.73 | — |
| Підставки для карток / 4 | Підставка середня | ACC-3D-PKM-120 | Yes | 70 | 50 | 36.36 | 25.78 | — |
| Підставки для карток / 5 | Підставка велика | ACC-3D-PKM-130 | Yes | 220 | 160 | 110.12 | 90.38 | — |
| Підставки для карток / 6 | Підставка під грейджені PSA (без покеболу) | ACC-3D-PKM-200 | Yes | 50 | 35 | 24.2 | 22.13 | — |
| Підставки для карток / 7 | Підставка під грейджені CGC (без покеболу) | ACC-3D-PKM-201 | Yes | 50 | 35 | 23.79 | 21.64 | — |
| Підставки для карток / 8 | Підставка під грейджені BGC (без покеболу) | ACC-3D-PKM-202 | Yes | 50 | 35 | 26.46 | 23.61 | — |
| Підставки для карток / 9 | Підставка під грейджені PSA на ніжці | ACC-3D-PKM-300 | Yes | 270 | 190 | 143.21 | 137.81 | — |
| Підставки для карток / 10 | Шестигранна крутяща підставка під топлоадери | ACC-3D-PKM-700 | No | — | — | 346.15 | 346.15 | INACTIVE_RRP_MISSING, INACTIVE_BUYOUT_MISSING |
| Підставки для карток / 11 | Шестигранна крутяща підставка під грейджені PSA | ACC-3D-PKM-710 | No | — | — | 356.98 | 356.98 | INACTIVE_RRP_MISSING, INACTIVE_BUYOUT_MISSING |
| Підставки для карток / 12 | Шестигранна крутяща підставка під грейджені BGS | ACC-3D-PKM-711 | No | — | — | 443.1 | 443.10 | INACTIVE_RRP_MISSING, INACTIVE_BUYOUT_MISSING |
| Підставки для карток / 13 | Шестигранна крутяща підставка під грейджені SGC | ACC-3D-PKM-712 | No | — | — | 463.78 | 463.78 | INACTIVE_RRP_MISSING, INACTIVE_BUYOUT_MISSING |
| Підставки для карток / 14 | Коробка відкрита під картки | ACC-3D-PKM-800 | Yes | 420 | 350 | 269.54 | 264.89 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Підставки для карток / 15 | Розділювачі для відкритої коробки під картки | ACC-3D-PKM-610 | Yes | 25 | 20 | 16.38 | 15.04 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Підставки для карток / 16 | Підставка випадаюча рамка | ACC-3D-PKM-150 | No | — | — | 90.29 | 88.58 | INACTIVE_RRP_MISSING, INACTIVE_BUYOUT_MISSING, SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Фігурки / 3 | Онікс нерухомий | FIG-ONIX-200 | Yes | 110 | 90 | 77.64 | 73.44 | — |
| Фігурки / 4 | Онікс рухомий M | FIG-ONIX-500 | Yes | 160 | 120 | 88.62 | 71.04 | — |
| Фігурки / 5 | Онікс рухомий L | FIG-ONIX-501 | Yes | 220 | 170 | 140.69 | 124.06 | — |
| Фігурки / 6 | Геодуд багатокольоровий M | FIG-GEOD-500 | Yes | 90 | 60 | 42.7 | 27.12 | — |
| Фігурки / 7 | Геодуд багатокольоровий L | FIG-GEOD-501 | Yes | 140 | 100 | 68.86 | 48.75 | — |
| Фігурки / 8 | Геодуд однокольоровий M | FIG-GEOD-510 | Yes | 75 | 55 | 39.22 | 26.10 | — |
| Фігурки / 9 | Геодуд однокольоровий L | FIG-GEOD-511 | Yes | 150 | 90 | 61.37 | 48.30 | — |
| Фігурки / 10 | Луффі рухомий | FIG-LUFFY-500 | Yes | 70 | 40 | 29.63 | 27.42 | — |
| Фігурки / 11 | Плаский Луффі силует | FIG-LUFFY-400 | Yes | 150 | 80 | 54.17 | 52.80 | — |
| Фігурки / 12 | Пласка картина Луффі | FIG-LUFFY-410 | Yes | 300 | 220 | 161.87 | 161.87 | — |
| Фігурки / 13 | Хантер в полоску | FIG-HNTR-200 | Yes | 190 | 100 | 77.63 | 75.49 | Canonical mnemonic approved 2026-09-05 |
| Фігурки / 14 | Хантер в полоску кольоровий | FIG-HNTR-210 | Yes | 250 | 180 | 138.29 | 85.33 | Canonical mnemonic approved 2026-09-05 |
| Фігурки / 15 | Покебол із хвостом | FIG-MEW-100 | Yes | 175 | 130 | 90.43 | 83.65 | — |
| Фігурки / 16 | Чарізард однокольоровий | FIG-CHARZ-200 | Yes | 200 | 90 | 58.85 | 54.41 | — |
| Фігурки / 17 | Зоро плаский | FIG-ZORO-410 | Yes | 150 | 80 | 57 | 57.00 | — |
| Фігурки / 18 | Плаский серйозний круглий Луффі | FIG-LUFFY-411 | Yes | 250 | 80 | 57 | 57.00 | — |
| Фігурки / 19 | Клікер Череп ван піс | FIG-OPSKL-600 | Yes | 130 | 80 | 50 | 32.71 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Фігурки / 20 | ONE PIECE команда | FIG-OP-410 | Yes | 150 | 100 | 78 | 78.00 | Canonical subtype approved 2026-09-05 |
| Фігурки / 21 | Намі S | FIG-NAMI-200 | Yes | 200 | 120 | 64 | 53.10 | — |
| Фігурки / 22 | Намі L | FIG-NAMI-201 | Yes | 750 | 500 | 245 | 224.60 | — |
| Фігурки / 23 | Стоячий клікер покебол | FIG-PKBL-600 | Yes | 50 | 30 | 14.79 | 10.24 | — |
| Пластини / 3 | Пластина Джигліпаф | FIG-JIGGL-300 | Yes | 75 | 60 | 47.7 | 33.20 | — |
| Пластини / 4 | Пластина Мью | FIG-MEW-300 | Yes | 75 | 60 | 37.94 | 37.94 | — |
| Пластини / 5 | Пластина Умбреон | FIG-UMBRE-300 | Yes | 90 | 70 | 51.17 | 51.17 | — |
| Пластини / 6 | Пластина Генгар | FIG-GENG-300 | Yes | 120 | 110 | 91.12 | 91.12 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE, DIMENSIONS_SEPARATOR_REQUIRES_REVIEW |
| Пластини / 7 | Пластина Маджикарп | FIG-MAGIK-300 | No | 75 | 60 | 47.72 | 37.48 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Пластини / 8 | Пластина Пікачу | FIG-PIKA-300 | Yes | 75 | 60 | 48.3 | 39.19 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Пластини / 9 | Пластина Сквіртл | FIG-SQUIR-300 | Yes | 75 | 60 | 48.07 | 39.05 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Аксесуари шо можна юзать / 3 | Книжкова закладка One piece | ACC-3D-OP-600 | Yes | 75 | 60 | 45.93 | 41.08 | — |
| Аксесуари шо можна юзать / 4 | Підставка під телефон Дітто | ACC-3D-DITTO-410 | Yes | 100 | 65 | 47.77 | 44.00 | — |
| Аксесуари шо можна юзать / 5 | Стакан під ручки Дітто | ACC-3D-DITTO-430 | No | 450 | 350 | 231.16 | 174.05 | — |
| Аксесуари шо можна юзать / 6 | Цукерниця Дітто | ACC-3D-DITTO-420 | No | 450 | 350 | 224.22 | 224.22 | — |
| Аксесуари шо можна юзать / 7 | Лампа One piece прямокутна | ACC-3D-OP-500 | Yes | 1000 | 600 | 302.17 | 302.17 | — |
| Аксесуари шо можна юзать / 8 | Лампа One piece тінь Луффі | ACC-3D-LUFFY-500 | No | 1000 | 500 | 247.74 | 247.74 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Аксесуари шо можна юзать / 9 | Лампа One piece ягода | ACC-3D-OPFRT-500 | No | — | — | 582.28 | 582.28 | INACTIVE_RRP_MISSING, INACTIVE_BUYOUT_MISSING, SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Аксесуари шо можна юзать / 10 | Розділювач між картками | ACC-3D-PKM-600 | Yes | 15 | 10 | 5.5 | 4.38 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Аксесуари шо можна юзать / 11 | Коробка покебол кругла M | ACC-3D-PKBL-400 | Yes | 350 | 250 | 195.05 | 195.05 | — |
| Аксесуари шо можна юзать / 12 | Коробка покебол кругла L | ACC-3D-PKBL-401 | Yes | 550 | 450 | 363.6 | 363.60 | — |
| Аксесуари шо можна юзать / 13 | Коробка під картки чарізард | ACC-3D-CHARZ-800 | Yes | 1100 | 850 | 639.44 | 639.44 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE |
| Аксесуари шо можна юзать / 14 | Покебол для картриджів nintendo | ACC-3D-PKBL-810 | Yes | 400 | 300 | 203.52 | 203.52 | SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE, DIMENSIONS_UNRESOLVED |
| Аксесуари шо можна юзать / 15 | Коробка покебол під картки квіадратна | ACC-3D-PKBL-800 | Yes | 350 | 250 | 190.51 | 179.46 | — |
