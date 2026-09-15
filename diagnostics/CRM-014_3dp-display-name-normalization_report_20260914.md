# Codex Report — CRM-014: 3D display-name normalization

Date: 2026-09-14

Status: **LIVE SHEET DATA REPAIRED; LOCAL DASHBOARD DISPLAY FIX READY; OWNER QA REMAINS**

## Executive summary

- The stored name paths were reconciled by SKU: main CRM `Товари`, automation `Майстер_Товарів`, and 3D-P `Номенклатура` return the same name for all 72 catalogue records.
- The 38 names backed by the OpenCart backup were already correct and were not changed. The remaining 34 convention-only records were normalized from working labels into unique customer-readable names.
- Post-write data verification returned 72/72 exact cross-source matches, 0 standalone stored `3D-друк` placeholders, 0 duplicate stored names, and 34/34 preserved CRM short-name formulas.
- The first report incorrectly treated stored-value equality as rendered dashboard proof. Owner QA on 2026-09-15 showed that Products still rendered every canonical 3D name as `3D-друк`.
- The local dashboard now preserves a complete canonical name ending in ` — 3D-друк`; the existing TCG brand-prefix shortening remains unchanged.
- No Apps Script source, spreadsheet structure, formula, price, SKU, status, or stock was changed. The only code change is the local dashboard helper plus its regression test.

## Scope

Authorized by the owner on 2026-09-14:

1. identify the exact source of inconsistent 3D names;
2. use the site backup as canonical evidence where an OpenCart SKU exists;
3. normalize the remaining no-live-match working labels using the approved naming convention;
4. write identical names to the canonical literal columns in the main CRM and 3D-P workbook;
5. verify the automation projections;
6. prepare this report for Claude review.

## Root cause

There were three independent defects.

1. The September catalogue migration had two evidence classes:
   - 38 `CHANGE_TO_LIVE_CANONICAL` rows with exact OpenCart backup matches;
   - 34 `APPROVE_CONVENTION_NO_LIVE_MATCH` rows with no separate live OpenCart product.

   The first class received site names. The second class intentionally retained the source workbook's working labels, including mixed language, missing `— 3D-друк` suffixes, informal wording, and one explicitly prohibited internal label.

2. The dashboard does not read both tabs from one name source:
   - Products uses `sku_list`, built from automation `Майстер_Товарів`;
   - 3D Print uses the 3D-P API, built from `Номенклатура`;
   - `sync_3dp_catalog_rrp` synchronizes SKU/RRP, not names.

3. Products passed the correct `sku_list.name` through the shared dashboard helper `skuShort()`. That helper was written for names such as `Pokémon — Set — EN — Booster`: it split on every ` — ` and removed the first segment. For the 3D convention `Brelok ... — 3D-друк`, the same logic returned only the suffix `3D-друк`. The 3D Print selector does not use this helper, which explains why that tab was correct while Products was not.

The previous CRM-014 attempt returned `candidate_count=0` because it looked for literal placeholder candidates in the CRM source. The later Sheet repair corrected the stored values, but its verification still missed the dashboard transformation. Cache was not the cause of the repeated `3D-друк` labels shown in the owner's 2026-09-15 screenshot.

## Canonical evidence

- Site backup: `backup-9.7.2026_20-35-02_boosters.tar.gz`
- Backup SHA-256: `4FC2CE3CD2D55FA4200635807B4125AB3F9FC648B9DC60FF36EAEA9D326D4318`
- OpenCart database inspected: `mysql/boosters_ocart49.sql`, language `4`
- Migration source: `plans/3dp-catalog-reset-20260902/migration-payload.json`
- Migration payload SHA-256: `7E40B96BC46F11562B3F1D2C067BC96CC744A76B1091362F0AFF1AF9019C1CCF`
- Naming policy: `plans/3D-P_sku-naming-convention_20260807.md`
- Backup comparison result: 38 site 3D products, 38 matched migration records, 38 exact name matches, 0 mismatches.

The remaining 34 SKUs have no separate OpenCart product record. Their new names follow the live catalogue's vocabulary and the approved pattern:

`{Product type} {Character/theme} ({Franchise})[, variant] — 3D-друк`

## Live changes

Two bounded batches were applied through the Google Sheets API:

| Spreadsheet | Sheet | Literal target | Writes |
|---|---|---|---:|
| Booster Shop CRM — облік товарів | `Товари` | column `C`, `Повна назва для сайту` | 34 |
| 3D-P_nomenclature-tracker_v6_20260731 | `Номенклатура` | column `B`, `Назва виробу` | 34 |

The automation file was read-only. `Source_CRM_Products` and `Майстер_Товарів` updated from their formulas.

### Exact old-to-new map and rollback values

| SKU | CRM cell | 3D cell | Previous name | New name |
|---|---:|---:|---|---|
| `BR-UMBRE-100` | `Товари!C95` | `Номенклатура!B7` | Брелок Умбреон стоячий | Брелок Umbreon (Pokémon), стоячий — 3D-друк |
| `BR-UMBRE-110` | `Товари!C97` | `Номенклатура!B8` | Брелок Умбреон сидячий | Брелок Umbreon (Pokémon), сидячий — 3D-друк |
| `BR-UMBRE-120` | `Товари!C105` | `Номенклатура!B9` | Брелок Умбреон обдовбаний | Брелок Umbreon (Pokémon), варіант 3 — 3D-друк |
| `BR-OPMUS-100` | `Товари!C107` | `Номенклатура!B11` | Брелок One piece Шляпа вусата | Брелок Капелюх-вуса (One Piece) — 3D-друк |
| `BR-OPSTR-100` | `Товари!C108` | `Номенклатура!B12` | Брелок One piece Шляпа солом'яна | Брелок Солом'яний капелюх (One Piece) — 3D-друк |
| `BR-OPFRT-100` | `Товари!C109` | `Номенклатура!B13` | Брелок One piece фрукт | Брелок Диявольський фрукт (One Piece) — 3D-друк |
| `BR-OP-100` | `Товари!C110` | `Номенклатура!B14` | Брелок One piece "One piece" | Брелок-логотип One Piece — 3D-друк |
| `BR-OPSKL-100` | `Товари!C112` | `Номенклатура!B16` | Брелок One piece череп | Брелок Череп (One Piece) — 3D-друк |
| `BR-PKBL-200` | `Товари!C114` | `Номенклатура!B18` | Брелок клікер покебол | Брелок-клікер Покебол (Pokémon) — 3D-друк |
| `ACC-3D-PKM-201` | `Товари!C119` | `Номенклатура!B23` | Підставка під грейджені CGC (без покеболу) | Підставка для слаба CGC — 3D-друк |
| `ACC-3D-PKM-202` | `Товари!C120` | `Номенклатура!B24` | Підставка під грейджені BGS (без покеболу) | Підставка для слаба BGS — 3D-друк |
| `ACC-3D-PKM-711` | `Товари!C124` | `Номенклатура!B28` | Шестигранна крутяща підставка під грейджені BGS | Обертова підставка для слабів BGS — 3D-друк |
| `ACC-3D-PKM-712` | `Товари!C125` | `Номенклатура!B29` | Шестигранна крутяща підставка під грейджені SGC | Обертова підставка для слабів SGC — 3D-друк |
| `ACC-3D-PKM-800` | `Товари!C126` | `Номенклатура!B30` | Коробка відкрита під картки | Відкрита коробка для карток — 3D-друк |
| `ACC-3D-PKM-610` | `Товари!C127` | `Номенклатура!B31` | Розділювачі для відкритої коробки під картки | Розділювачі для відкритої коробки карток — 3D-друк |
| `ACC-3D-PKM-150` | `Товари!C128` | `Номенклатура!B32` | Підставка випадаюча рамка | Настінна рамка для картки в топлоадері — 3D-друк |
| `FIG-ONIX-200` | `Товари!C129` | `Номенклатура!B33` | Онікс нерухомий | Фігурка Onix (Pokémon), статична — 3D-друк |
| `FIG-ONIX-501` | `Товари!C131` | `Номенклатура!B35` | Онікс рухомий L | Фігурка Onix (Pokémon), L — 3D-друк |
| `FIG-GEOD-500` | `Товари!C132` | `Номенклатура!B36` | Геодуд багатокольоровий M | Фігурка Geodude (Pokémon), багатоколірна M — 3D-друк |
| `FIG-GEOD-501` | `Товари!C133` | `Номенклатура!B37` | Геодуд багатокольоровий L | Фігурка Geodude (Pokémon), багатоколірна L — 3D-друк |
| `FIG-GEOD-510` | `Товари!C134` | `Номенклатура!B38` | Геодуд однокольоровий M | Фігурка Geodude (Pokémon), одноколірна M — 3D-друк |
| `FIG-HNTR-200` | `Товари!C139` | `Номенклатура!B43` | Хантер в полоску | Ниткова фігурка Haunter (Pokémon), одноколірна — 3D-друк |
| `FIG-HNTR-210` | `Товари!C140` | `Номенклатура!B44` | Хантер в полоску кольоровий | Ниткова фігурка Haunter (Pokémon), багатоколірна — 3D-друк |
| `FIG-OPSKL-600` | `Товари!C145` | `Номенклатура!B49` | Клікер Череп ван піс | Фігурка-клікер Череп (One Piece) — 3D-друк |
| `FIG-OP-410` | `Товари!C146` | `Номенклатура!B50` | ONE PIECE команда | Картина з командою One Piece — 3D-друк |
| `FIG-NAMI-201` | `Товари!C148` | `Номенклатура!B52` | Намі L | Фігурка Nami (One Piece), L — 3D-друк |
| `ACC-3D-DITTO-410` | `Товари!C158` | `Номенклатура!B62` | Підставка під телефон Дітто | Підставка під телефон Ditto (Pokémon) — 3D-друк |
| `ACC-3D-OP-500` | `Товари!C161` | `Номенклатура!B65` | Лампа One piece прямокутна | Світильник One Piece, прямокутний — 3D-друк |
| `ACC-3D-LUFFY-500` | `Товари!C162` | `Номенклатура!B66` | Лампа One piece тінь Луффі | Світильник Luffy (One Piece), силует — 3D-друк |
| `ACC-3D-OPFRT-500` | `Товари!C163` | `Номенклатура!B67` | Лампа One piece ягода | Світильник Диявольський фрукт (One Piece) — 3D-друк |
| `ACC-3D-PKM-600` | `Товари!C164` | `Номенклатура!B68` | Розділювач між картками | Розділювач для карток — 3D-друк |
| `ACC-3D-PKBL-401` | `Товари!C166` | `Номенклатура!B70` | Коробка покебол кругла L | Чаша-покебол для дрібниць (Pokémon), L — 3D-друк |
| `ACC-3D-CHARZ-800` | `Товари!C167` | `Номенклатура!B71` | Коробка під картки чарізард | Коробка для карток Charizard (Pokémon) — 3D-друк |
| `ACC-3D-PKBL-810` | `Товари!C168` | `Номенклатура!B72` | Покебол для картриджів nintendo | Кейс-покебол для картриджів Nintendo Switch — 3D-друк |

`BR-UMBRE-120` uses the neutral `варіант 3`. The model source proves that the MakerWorld entry contains three Umbreon keychains, but the available source does not provide a customer-safe pose name for the third silhouette. The prior label was explicitly documented as an internal working word that must not reach a product name.

## Pre-write gates

- Target spreadsheets and sheet IDs were re-resolved from live metadata.
- All 34 SKUs existed exactly once in both target sheets.
- All 68 target cells still contained the expected previous value immediately before the write.
- Every `Товари!B` cell contained its row-local formula `=IF($Arow="";"";$Crow)`.
- Target literal cells had no data validation.
- Proposed names were non-empty and unique: 34/34.
- The write plan did not include formulas, rows, columns, formatting, validation, prices, inventory, or statuses.

## Write result

```text
crm_requests=34
3d_requests=34
crm_spreadsheet_id=1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg
3d_spreadsheet_id=1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo
unique_names=34
```

The CRM batch succeeded before the 3D-P batch. A CRM rollback batch with the previous values was prepared and would have run if the second write failed. The 3D-P batch succeeded, so rollback was not invoked.

## Post-write verification

| Check | Result |
|---|---:|
| Updated CRM `Товари!C` values | 34/34 exact |
| Updated 3D-P `Номенклатура!B` values | 34/34 exact |
| Preserved CRM `Товари!B` formulas | 34/34 exact |
| Automation `Source_CRM_Products` projection | 34/34 exact |
| Automation `Майстер_Товарів` projection | 34/34 exact |
| Site-backed names unchanged | 38/38 exact |
| Full 72-SKU stored-value comparison | 72/72 exact |
| Standalone stored placeholder names | 0 |
| Duplicate stored names among 72 SKUs | 0 |

## Dashboard correction on 2026-09-15

Files changed locally:

- `dashboard/booster-dashboard.html`
- `dashboard/tests/dashboard-contract.test.mjs`

The fix adds one narrow condition to `skuShort()`: a normalized name ending in ` — 3D-друк` is returned intact before the generic brand-prefix shortening runs. This affects Products, Stock, and overview/attention UI call sites that use `skuName()` and prevents the same collapse everywhere. Non-3D shortening behavior is unchanged.

Regression coverage evaluates the helper directly and requires both results:

- `Брелок Charmander (Pokémon) — 3D-друк` remains complete, including when source text has trailing spaces;
- `Pokémon — Prismatic Evolutions — EN — Booster` remains `Prismatic Evolutions — EN — Booster`.

Verification results:

```text
node dashboard/tests/sku-name-display.test.mjs
3D SKU display-name tests passed

live_3d_rows=72
complete_suffix_names=72
display_exact_source=72
collapsed_to_suffix=0
```

The live 72-row `Майстер_Товарів!A:C` projection was passed through the corrected helper logic during verification. All 72 rendered strings remained equal to their source names. The longest checked name was `FIG-HNTR-210`: `Ниткова фігурка Haunter (Pokémon), багатоколірна — 3D-друк`.

The broad `dashboard/tests/dashboard-contract.test.mjs` compiled every inline dashboard script, then stopped on a pre-existing unrelated catalogue-contract drift: the local 3D API contains the category `Кейс / контейнер для зберігання`, while the dashboard category array does not. This task does not repair or hide that separate failure.

## Rollback

The previous values and exact target cells are recorded in the mapping table above. A rollback must restore each listed previous value to both its CRM and 3D-P cell. Google Sheets version history is the second recovery path.

Do not roll back only one spreadsheet: both name sources must move together.

## Remaining owner QA

Reload the local dashboard so the browser loads the corrected HTML, then use `↻ Оновити дані` and verify:

- [ ] Products search `3d` shows full names for `BR-CHARM-100`, `BR-BULB-100`, `BR-SQUIR-100`, `BR-MEW-100`, `BR-PIKA-100`, and `BR-CHARM-200`.
- [ ] 3D Print selector shows the normalized names for representative no-live SKUs: `BR-UMBRE-120`, `FIG-HNTR-200`, `FIG-OP-410`, `ACC-3D-PKM-150`, and `ACC-3D-PKBL-810`.
- [ ] The same SKU has the same name in Products and 3D Print.

Runtime visual QA remains owner-gated: the Codex in-app browser is blocked by policy from opening the local `file:///.../dashboard/booster-dashboard.html` URL.

## Claude review checklist

- [ ] Confirm the 38/34 evidence split against `migration-payload.json` and the OpenCart backup evidence.
- [ ] Review the 34 old-to-new decisions, especially the neutral `BR-UMBRE-120` label.
- [ ] Confirm that only `Товари!C` and `Номенклатура!B` were written live.
- [ ] Confirm the 34/34 formula and projection checks and the 72/72 stored-value reconciliation.
- [ ] Review the `skuShort()` guard and the two direct regression assertions.
- [ ] Confirm the focused test pass and treat the broad category-list failure as unrelated existing drift.
- [ ] Keep dashboard runtime QA open until the owner refreshes the corrected local page.
- [ ] Decide whether name propagation should become a separate permanent action alongside `sync_3dp_catalog_rrp`; this repair does not modify production automation code.

## Side effects and risks

- Existing open dashboard tabs keep the old helper until the HTML page is reloaded.
- The helper fix changes all dashboard call sites that use `skuName()` for a name ending in ` — 3D-друк`; they now show the complete canonical name rather than the suffix.
- Future manual changes can diverge again because the permanent SKU/RRP sync path still does not synchronize names. That is a separate automation change and was not silently added here.
- No deployment, Apps Script publication, Notion status update, commit, or push was performed.
