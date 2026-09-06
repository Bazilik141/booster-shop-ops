# 3DP-CATALOG — canonical OpenCart name and article decisions (Claude → Codex)

Date: 2026-09-05

Author: Claude (chat audit) · model=Opus · thinking=high

Input handoff: `handoffs/handoff_3DP-CATALOG-CANONICAL-AUDIT_claude_20260905.md`
Consumer: `handoffs/handoff_3DP-CATALOG-MIGRATION-CONTINUE_codex_20260905.md`

## 1. Conclusion

`READY_FOR_CODEX_CORRECTION`. Every one of the 72 rows carries an approved article and name. Nothing is withheld.

| Change | Count |
|---|---:|
| Article changes | 3 |
| Name changes | 39 |
| Active/inactive changes | 3 |
| Rows left exactly as they are in CRM | 30 |

All 38 live-matched rows already carry exactly the article the migration proposed, so no live product is renumbered. The three article changes are on rows that do not exist on the site yet.

## 2. Owner decisions taken 2026-09-05

These four rulings were given in the audit session and are the authority for the rows they touch.

1. **Pose goes in the tens digit, size M/L in the units.** `BR-UMBRE-100/110/120` therefore stands as proposed — three poses of one Umbreon model, coded in the tens. Nothing was changed on `FIG-ONIX-200/500/501`: `5__` (wobble) is its own category in the `FIG-` legend and M/L already sit in the units there, so that family already follows this rule. If the owner meant the live `FIG-ONIX-500` should be renumbered, that is a separate decision and this file does not assume it.
2. **A different mnemonic for Haunter**, chosen by delegation: **`HNTR`** — the vowel-drop form named in the convention formula, ≤5 characters, distinct from the registered `GENG` (Gengar), absent from the live catalogue. `FIG-HAUNT-200/210` → `FIG-HNTR-200/210`.
3. **The One Piece crew piece is a картина**, not панно. ред. 8 §1 codes панно as subtype `0` and картина as subtype `1`, so `FIG-OP-400` → **`FIG-OP-410`**. The article is not live, so no renumbering cost applies — unlike `FIG-LUFFY-400`, which the convention deliberately keeps at `400` because it is published.
4. **`BR-BULB-100`, `BR-SQUIR-100`, `BR-PIKA-100` become active** («друкувати можемо»). This supersedes the source flag «Можливість зараз друкувати: Ні» for those three rows and resolves the contradiction with their live, priced, visible product pages (128, 127, 129).

### 2.1 The 59/13 split is now 62/10

Decision 4 changes a number that several artifacts state as fixed. Codex must treat **62 active / 10 inactive** as the target and must not restore 59/13 from any of these:

- `plans/3dp-catalog-reset-20260902/migration-payload.json` — `policy.active_count 59` / `inactive_count 13`;
- `plans/3dp-catalog-reset-20260902/import-manifest.json` — `summary.active 59` / `inactive 13`;
- `plans/3dp-catalog-reset-20260902/import-review.md` line 7;
- `plans/3dp-catalog-reset-20260902/fifo-contract.md` line 10;
- `handoffs/handoff_3DP-CATALOG-MIGRATION-CONTINUE_codex_20260905.md` lines 34 and 200;
- `handoffs/handoff_3DP-CATALOG-CANONICAL-AUDIT_claude_20260905.md` lines 46 and 124;
- the CRM rows already written by `catalogMigrationCrmApplyV2`, whose post-check recorded 59/13. Those three rows now need a status correction as well as a name correction.

⚠ The source spreadsheet still says «Ні» for these three keychains. Unless that cell is updated by whoever owns the sheet, the next re-import will flip them back to inactive.

## 3. Evidence provenance

| Source | What was read | Timestamp / identity |
|---|---|---|
| Live OpenCart database | `ocp5_product`, `ocp5_product_description`, `ocp5_product_code`, `ocp5_seo_url`, `ocp5_product_to_category` | `backup-9.3.2026_21-30-35_boosters.tar.gz` → `mysql/boosters_ocart49.sql`, backup taken **2026-09-03 21:30:35**; owner confirmed 2026-09-05 that it is current for the 3D products |
| Migration inputs | `import-manifest.json` (72 records, `source_snapshot_sha256 89ccccff…`), `import-review.md`, `migration-payload.json`, `fifo-contract.md` | generated 2026-09-04 11:07 UTC |
| Naming canon | `plans/3D-P_sku-naming-convention_20260807.md` incl. ред. 2, 3, 4, 5, 6, 7, 8 | file state 2026-08-31 |
| Intake diagnostic | `diagnostics/CRM-3DP_catalog-reset-intake_report_20260902.md` | refreshed 2026-09-04 |
| Draft spreadsheet | «Розрахунок друку», five product tabs (consumables excluded) | `1gQLHxS-EGxIOwX3k8UhU-1HFRzpgFrlDTZaSelp4Tu4`, last modified 2026-08-31 10:43 UTC |

The live catalogue holds **118 products**, of which **38** are 3D products. No live read was performed against the production server; every live value in this file comes from the backup named above. No repository, CRM, 3D-P, Notion or OpenCart record was modified during this audit.

**Which field is canonical.** Across all 118 live products `model` is populated and unique; `ocp5_product.sku` is empty on **76** of them, including every 3D product created by hand (ids 163–173). `ocp5_product_code.SKU` mirrors `model` on all but two unrelated TCG rows (77 `PKM-JP-MBX-ST` vs `PKM-JP-MIX-MBX`, 85 `OP-JP-MBX-ST` vs `OP-JP-MIX-MBX`). **`model` is the canonical article field for this migration**; `sku` must never be used as the key, and any tool selecting products by article must read both. This confirms the convention §2c (established 2026-08-30) against the current database.

## 4. Canonical mapping — 72 rows

| `source_tab` | `source_row` | `source_name` | `current_crm_sku` | `current_crm_name` | `live_product_id` | `live_model` | `live_sku` | `canonical_sku` | `canonical_name` | `rrp` | `buyout` | `active` | `decision` | `evidence` | `owner_approval_required` |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Брелоки | 4 | Брелок Ч | BR-CHARM-100 | Брелок Ч | 126 | BR-CHARM-100 | BR-CHARM-100 | BR-CHARM-100 | Брелок Charmander (Pokémon) — 3D-друк | 30 | 10 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=126, model=BR-CHARM-100, sku=BR-CHARM-100, product_code.SKU=BR-CHARM-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Брелоки | 5 | Брелок Б | BR-BULB-100 | Брелок Б | 128 | BR-BULB-100 | BR-BULB-100 | BR-BULB-100 | Брелок Bulbasaur (Pokémon) — 3D-друк | 30 | 10 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=128, model=BR-BULB-100, sku=BR-BULB-100, product_code.SKU=BR-BULB-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні» | false |
| Брелоки | 6 | Брелок С | BR-SQUIR-100 | Брелок С | 127 | BR-SQUIR-100 | BR-SQUIR-100 | BR-SQUIR-100 | Брелок Squirtle (Pokémon) — 3D-друк | 30 | 10 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=127, model=BR-SQUIR-100, sku=BR-SQUIR-100, product_code.SKU=BR-SQUIR-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні» | false |
| Брелоки | 7 | Брелок Мью | BR-MEW-100 | Брелок Мью | 125 | BR-MEW-100 | BR-MEW-100 | BR-MEW-100 | Брелок Mew (Pokémon) — 3D-друк | 30 | 15 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=125, model=BR-MEW-100, sku=BR-MEW-100, product_code.SKU=BR-MEW-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Брелоки | 8 | Брелок Пікачу | BR-PIKA-100 | Брелок Пікачу | 129 | BR-PIKA-100 | BR-PIKA-100 | BR-PIKA-100 | Брелок Pikachu (Pokémon) — 3D-друк | 40 | 15 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=129, model=BR-PIKA-100, sku=BR-PIKA-100, product_code.SKU=BR-PIKA-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні» | false |
| Брелоки | 9 | Брелок Умбреон стоячий | BR-UMBRE-100 | Брелок Умбреон стоячий | null | null | null | BR-UMBRE-100 | Брелок Умбреон стоячий | 50 | 20 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; BR- legend 1__ (ordinary pendant); owner decision 2026-09-05 — a pose/form difference is coded in the tens digit, size M/L in the units, so 100/110/120 stands as proposed; no collision | false |
| Брелоки | 10 | Брелок Умбреон сидячий | BR-UMBRE-110 | Брелок Умбреон сидячий | null | null | null | BR-UMBRE-110 | Брелок Умбреон сидячий | 50 | 20 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; BR- legend 1__; owner decision 2026-09-05 — pose in the tens digit; no collision | false |
| Брелоки | 11 | Брелок Умбреон обдовбаний | BR-UMBRE-120 | Брелок Умбреон обдовбаний | null | null | null | BR-UMBRE-120 | Брелок Умбреон обдовбаний | 50 | 20 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; BR- legend 1__; owner decision 2026-09-05 — pose in the tens digit. Article approved; the source label «обдовбаний» is a working word and must not become the card name when this product is created | true |
| Брелоки | 12 | Брелок-клікер Чармандер | BR-CHARM-200 | Брелок-клікер Чармандер | 154 | BR-CHARM-200 | BR-CHARM-200 | BR-CHARM-200 | Брелок-клікер Charmander (Pokémon) — 3D-друк | 120 | 70 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=154, model=BR-CHARM-200, sku=BR-CHARM-200, product_code.SKU=BR-CHARM-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Брелоки | 14 | Брелок One piece Шляпа вусата | BR-OPMUS-100 | Брелок One piece Шляпа вусата | null | null | null | BR-OPMUS-100 | Брелок One piece Шляпа вусата | 50 | 20 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table) | false |
| Брелоки | 15 | Брелок One piece Шляпа солом'яна | BR-OPSTR-100 | Брелок One piece Шляпа солом'яна | null | null | null | BR-OPSTR-100 | Брелок One piece Шляпа солом'яна | 50 | 20 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table) | false |
| Брелоки | 16 | Брелок One piece фрукт | BR-OPFRT-100 | Брелок One piece фрукт | null | null | null | BR-OPFRT-100 | Брелок One piece фрукт | 50 | 20 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table) | false |
| Брелоки | 17 | Брелок One piece "One piece" | BR-OP-100 | Брелок One piece "One piece" | null | null | null | BR-OP-100 | Брелок One piece "One piece" | 60 | 35 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md formula + BR- legend 1__; mnemonic OP registered; identical shape to its four documented BR-OP*-100 siblings; no collision | false |
| Брелоки | 18 | Брелок One piece кораблик | BR-OPSHP-100 | Брелок One piece кораблик | 171 | BR-OPSHP-100 | null | BR-OPSHP-100 | Брелок Going Merry (One Piece) — 3D-друк | 60 | 40 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=171, model=BR-OPSHP-100, sku=EMPTY, product_code.SKU=BR-OPSHP-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Брелоки | 19 | Брелок One piece череп | BR-OPSKL-100 | Брелок One piece череп | null | null | null | BR-OPSKL-100 | Брелок One piece череп | 50 | 20 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table) | false |
| Брелоки | 20 | Брелок Дітто крутиться | BR-DITTO-400 | Брелок Дітто крутиться | 170 | BR-DITTO-400 | null | BR-DITTO-400 | Брелок-спінер Ditto (Pokémon) — 3D-друк | 70 | 30 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=170, model=BR-DITTO-400, sku=EMPTY, product_code.SKU=BR-DITTO-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Брелоки | 21 | Брелок клікер покебол | BR-PKBL-200 | Брелок клікер покебол | null | null | null | BR-PKBL-200 | Брелок клікер покебол | 120 | 70 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table, «Брелок клікер покебол»); BR- legend 2__ | false |
| Підставки для карток | 3 | Підставка мала | ACC-3D-PKM-110 | Підставка мала | 137 | ACC-3D-PKM-110 | ACC-3D-PKM-110 | ACC-3D-PKM-110 | Підставка для картки в протекторі — 3D-друк | 40 | 25 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=137, model=ACC-3D-PKM-110, sku=ACC-3D-PKM-110, product_code.SKU=ACC-3D-PKM-110, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Підставки для карток | 4 | Підставка середня | ACC-3D-PKM-120 | Підставка середня | 138 | ACC-3D-PKM-120 | ACC-3D-PKM-120 | ACC-3D-PKM-120 | Підставка для картки в топлоадері — 3D-друк | 70 | 50 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=138, model=ACC-3D-PKM-120, sku=ACC-3D-PKM-120, product_code.SKU=ACC-3D-PKM-120, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Підставки для карток | 5 | Підставка велика | ACC-3D-PKM-130 | Підставка велика | 139 | ACC-3D-PKM-130 | ACC-3D-PKM-130 | ACC-3D-PKM-130 | Підставка для картки в акриловому кейсі — 3D-друк | 220 | 160 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=139, model=ACC-3D-PKM-130, sku=ACC-3D-PKM-130, product_code.SKU=ACC-3D-PKM-130, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Підставки для карток | 6 | Підставка під грейджені PSA (без покеболу) | ACC-3D-PKM-200 | Підставка під грейджені PSA (без покеболу) | 140 | ACC-3D-PKM-200 | ACC-3D-PKM-200 | ACC-3D-PKM-200 | Підставка для слаба — 3D-друк | 50 | 35 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=140, model=ACC-3D-PKM-200, sku=ACC-3D-PKM-200, product_code.SKU=ACC-3D-PKM-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Підставки для карток | 7 | Підставка під грейджені CGC (без покеболу) | ACC-3D-PKM-201 | Підставка під грейджені CGC (без покеболу) | null | null | null | ACC-3D-PKM-201 | Підставка під грейджені CGC (без покеболу) | 50 | 35 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table, CGC); ред. 4 — CGC returns as a compatibility option on the live ACC-3D-PKM-200 page, so this article is accounting-only | false |
| Підставки для карток | 8 | Підставка під грейджені BGC (без покеболу) | ACC-3D-PKM-202 | Підставка під грейджені BGC (без покеболу) | null | null | null | ACC-3D-PKM-202 | Підставка під грейджені BGS (без покеболу) | 50 | 35 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table); ред. 3 §1 — BGC was an error, correct brand is BGS (Beckett); accounting-only variant of live ACC-3D-PKM-200; name corrected: plans/3D-P_sku-naming-convention_20260807.md ред. 3 §1 — BGC is not a grading company; correct brand is BGS (Beckett) | false |
| Підставки для карток | 9 | Підставка під грейджені PSA на ніжці | ACC-3D-PKM-300 | Підставка під грейджені PSA на ніжці | 141 | ACC-3D-PKM-300 | ACC-3D-PKM-300 | ACC-3D-PKM-300 | Підставка для слаба, на ніжці — 3D-друк | 270 | 190 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=141, model=ACC-3D-PKM-300, sku=ACC-3D-PKM-300, product_code.SKU=ACC-3D-PKM-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Підставки для карток | 10 | Шестигранна крутяща підставка під топлоадери | ACC-3D-PKM-700 | Шестигранна крутяща підставка під топлоадери | 142 | ACC-3D-PKM-700 | ACC-3D-PKM-700 | ACC-3D-PKM-700 | Обертова підставка для карток у топлоадерах — 3D-друк | null | null | false | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=142, model=ACC-3D-PKM-700, sku=ACC-3D-PKM-700, product_code.SKU=ACC-3D-PKM-700, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Підставки для карток | 11 | Шестигранна крутяща підставка під грейджені PSA | ACC-3D-PKM-710 | Шестигранна крутяща підставка під грейджені PSA | 143 | ACC-3D-PKM-710 | ACC-3D-PKM-710 | ACC-3D-PKM-710 | Обертова підставка для слабів — 3D-друк | null | null | false | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=143, model=ACC-3D-PKM-710, sku=ACC-3D-PKM-710, product_code.SKU=ACC-3D-PKM-710, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Підставки для карток | 12 | Шестигранна крутяща підставка під грейджені BGS | ACC-3D-PKM-711 | Шестигранна крутяща підставка під грейджені BGS | null | null | null | ACC-3D-PKM-711 | Шестигранна крутяща підставка під грейджені BGS | null | null | false | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 3 §2 — renumbered 211→711, units digit = slab format (1 = BGS); accounting-only variant of live ACC-3D-PKM-710 | false |
| Підставки для карток | 13 | Шестигранна крутяща підставка під грейджені SGC | ACC-3D-PKM-712 | Шестигранна крутяща підставка під грейджені SGC | null | null | null | ACC-3D-PKM-712 | Шестигранна крутяща підставка під грейджені SGC | null | null | false | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 3 §2 — units digit 2 = SGC; accounting-only variant of live ACC-3D-PKM-710 | false |
| Підставки для карток | 14 | Коробка відкрита під картки | ACC-3D-PKM-800 | Коробка відкрита під картки | null | null | null | ACC-3D-PKM-800 | Коробка відкрита під картки | 420 | 350 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 7 §1 — ACC-3D- 8__ (case/container) opened 2026-08-28; precedent ACC-3D-PKBL-800 | false |
| Підставки для карток | 15 | Розділювачі для відкритої коробки під картки | ACC-3D-PKM-610 | Розділювачі для відкритої коробки під картки | null | null | null | ACC-3D-PKM-610 | Розділювачі для відкритої коробки під картки | 25 | 20 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 6__ (flat plastic accessories); tens digit 1 is a new subtype with no documented precedent | true |
| Підставки для карток | 16 | Підставка випадаюча рамка | ACC-3D-PKM-150 | Підставка випадаюча рамка | null | null | null | ACC-3D-PKM-150 | Підставка випадаюча рамка | null | null | false | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 1__ (card stand); tens digit 5 is a new subtype and skips unused 140 | true |
| Фігурки | 3 | Онікс нерухомий | FIG-ONIX-200 | Онікс нерухомий | null | null | null | FIG-ONIX-200 | Онікс нерухомий | 110 | 90 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); convention states the static Onix is a different model, not a variant of the wobble version | false |
| Фігурки | 4 | Онікс рухомий M | FIG-ONIX-500 | Онікс рухомий M | 130 | FIG-ONIX-500 | FIG-ONIX-500 | FIG-ONIX-500 | Фігурка Onix (Pokémon) — 3D-друк | 160 | 120 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=130, model=FIG-ONIX-500, sku=FIG-ONIX-500, product_code.SKU=FIG-ONIX-500, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 5 | Онікс рухомий L | FIG-ONIX-501 | Онікс рухомий L | null | null | null | FIG-ONIX-501 | Онікс рухомий L | 220 | 170 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); same model/profileId as FIG-ONIX-500, scale variant — accounting-only variant of live product 130 | false |
| Фігурки | 6 | Геодуд багатокольоровий M | FIG-GEOD-500 | Геодуд багатокольоровий M | null | null | null | FIG-GEOD-500 | Геодуд багатокольоровий M | 90 | 60 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); tens = colour, units = size — accounting-only variant of live product 131 | false |
| Фігурки | 7 | Геодуд багатокольоровий L | FIG-GEOD-501 | Геодуд багатокольоровий L | null | null | null | FIG-GEOD-501 | Геодуд багатокольоровий L | 140 | 100 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); accounting-only variant of live product 131 | false |
| Фігурки | 8 | Геодуд однокольоровий M | FIG-GEOD-510 | Геодуд однокольоровий M | null | null | null | FIG-GEOD-510 | Геодуд однокольоровий M | 75 | 55 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); accounting-only variant of live product 131 | false |
| Фігурки | 9 | Геодуд однокольоровий L | FIG-GEOD-511 | Геодуд однокольоровий L | 131 | FIG-GEOD-511 | FIG-GEOD-511 | FIG-GEOD-511 | Фігурка Geodude (Pokémon) — 3D-друк | 150 | 90 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=131, model=FIG-GEOD-511, sku=FIG-GEOD-511, product_code.SKU=FIG-GEOD-511, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 10 | Луффі рухомий | FIG-LUFFY-500 | Луффі рухомий | 134 | FIG-LUFFY-500 | FIG-LUFFY-500 | FIG-LUFFY-500 | Фігурка Luffy (One Piece) — 3D-друк | 70 | 40 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=134, model=FIG-LUFFY-500, sku=FIG-LUFFY-500, product_code.SKU=FIG-LUFFY-500, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 11 | Плаский Луффі силует | FIG-LUFFY-400 | Плаский Луффі силует | 135 | FIG-LUFFY-400 | FIG-LUFFY-400 | FIG-LUFFY-400 | Настільна картина Luffy (One Piece) — 3D-друк | 150 | 80 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=135, model=FIG-LUFFY-400, sku=FIG-LUFFY-400, product_code.SKU=FIG-LUFFY-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 12 | Пласка картина Луффі | FIG-LUFFY-410 | Пласка картина Луффі | 136 | FIG-LUFFY-410 | FIG-LUFFY-410 | FIG-LUFFY-410 | Картина Luffy (One Piece) — 3D-друк | 300 | 220 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=136, model=FIG-LUFFY-410, sku=FIG-LUFFY-410, product_code.SKU=FIG-LUFFY-410, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 13 | Хантер в полоску | FIG-HAUNT-200 | Хантер в полоску | null | null | null | FIG-HNTR-200 | Хантер в полоску | 190 | 100 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; Mnemonic HAUNT was never registered; owner delegated the choice 2026-09-05. HNTR = Haunter by the vowel-drop rule in the formula, ≤5 characters, distinct from the registered GENG (Gengar), and absent from the live catalogue. Article otherwise unchanged: FIG- 2__, tens = colour | false |
| Фігурки | 14 | Хантер в полоску кольоровий | FIG-HAUNT-210 | Хантер в полоску кольоровий | null | null | null | FIG-HNTR-210 | Хантер в полоску кольоровий | 250 | 180 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; Same mnemonic replacement as FIG-HNTR-200; tens digit 1 = colour variant | false |
| Фігурки | 15 | Покебол із хвостом | FIG-MEW-100 | Покебол із хвостом | 132 | FIG-MEW-100 | FIG-MEW-100 | FIG-MEW-100 | Фігурка Mew у покеболі (Pokémon) — 3D-друк | 175 | 130 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=132, model=FIG-MEW-100, sku=FIG-MEW-100, product_code.SKU=FIG-MEW-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 16 | Чарізард однокольоровий | FIG-CHARZ-200 | Чарізард однокольоровий | 167 | FIG-CHARZ-200 | null | FIG-CHARZ-200 | Фігурка Charizard (Pokémon) — 3D-друк | 200 | 90 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=167, model=FIG-CHARZ-200, sku=EMPTY, product_code.SKU=FIG-CHARZ-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 17 | Зоро плаский | FIG-ZORO-410 | Зоро плаский | 168 | FIG-ZORO-410 | null | FIG-ZORO-410 | Картина Zoro (One Piece) — 3D-друк | 150 | 80 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=168, model=FIG-ZORO-410, sku=EMPTY, product_code.SKU=FIG-ZORO-410, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 18 | Плаский серйозний круглий Луффі | FIG-LUFFY-411 | Плаский серйозний круглий Луффі | 169 | FIG-LUFFY-411 | null | FIG-LUFFY-411 | Картина Luffy (One Piece), кругла — 3D-друк | 250 | 80 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=169, model=FIG-LUFFY-411, sku=EMPTY, product_code.SKU=FIG-LUFFY-411, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 19 | Клікер Череп ван піс | FIG-OPSKL-600 | Клікер Череп ван піс | null | null | null | FIG-OPSKL-600 | Клікер Череп ван піс | 130 | 80 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 2 §2 — FIG- 6__ = fidget mechanics (clicker); mnemonic OPSKL registered; no collision | false |
| Фігурки | 20 | ONE PIECE команда | FIG-OP-400 | ONE PIECE команда | null | null | null | FIG-OP-410 | ONE PIECE команда | 150 | 100 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; Owner decision 2026-09-05 — the One Piece crew piece is a картина, not панно. ред. 8 §1 codes панно as subtype 0 and картина as subtype 1, so the article moves to 410. The article is not live, so no renumbering cost applies (unlike FIG-LUFFY-400, kept at 400 because it is live) | false |
| Фігурки | 21 | Намі S | FIG-NAMI-200 | Намі S | 165 | FIG-NAMI-200 | null | FIG-NAMI-200 | Фігурка Nami (One Piece) — 3D-друк | 200 | 120 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=165, model=FIG-NAMI-200, sku=EMPTY, product_code.SKU=FIG-NAMI-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Фігурки | 22 | Намі L | FIG-NAMI-201 | Намі L | null | null | null | FIG-NAMI-201 | Намі L | 750 | 500 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 7 §5 — «Намі L в обліку — FIG-NAMI-201»; size returns as an option on the live FIG-NAMI-200 page; owner price override RRP 750 / buyout 500 (2026-09-02) | false |
| Фігурки | 23 | Стоячий клікер покебол | FIG-PKBL-600 | Стоячий клікер покебол | 133 | FIG-PKBL-600 | FIG-PKBL-600 | FIG-PKBL-600 | Фігурка-клікер Покебол (Pokémon) — 3D-друк | 50 | 30 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=133, model=FIG-PKBL-600, sku=FIG-PKBL-600, product_code.SKU=FIG-PKBL-600, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Пластини | 3 | Пластина Джигліпаф | FIG-JIGGL-300 | Пластина Джигліпаф | 156 | FIG-JIGGL-300 | FIG-JIGGL-300 | FIG-JIGGL-300 | Фігурка-конструктор Jigglypuff (Pokémon) — 3D-друк | 75 | 60 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=156, model=FIG-JIGGL-300, sku=FIG-JIGGL-300, product_code.SKU=FIG-JIGGL-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Пластини | 4 | Пластина Мью | FIG-MEW-300 | Пластина Мью | 157 | FIG-MEW-300 | FIG-MEW-300 | FIG-MEW-300 | Фігурка-конструктор Mew (Pokémon) — 3D-друк | 75 | 60 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=157, model=FIG-MEW-300, sku=FIG-MEW-300, product_code.SKU=FIG-MEW-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Пластини | 5 | Пластина Умбреон | FIG-UMBRE-300 | Пластина Умбреон | 158 | FIG-UMBRE-300 | FIG-UMBRE-300 | FIG-UMBRE-300 | Фігурка-конструктор Umbreon (Pokémon) — 3D-друк | 90 | 70 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=158, model=FIG-UMBRE-300, sku=FIG-UMBRE-300, product_code.SKU=FIG-UMBRE-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Пластини | 6 | Пластина Генгар | FIG-GENG-300 | Пластина Генгар | 159 | FIG-GENG-300 | FIG-GENG-300 | FIG-GENG-300 | Фігурка-конструктор Gengar (Pokémon) — 3D-друк | 120 | 110 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=159, model=FIG-GENG-300, sku=FIG-GENG-300, product_code.SKU=FIG-GENG-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Пластини | 7 | Пластина Маджикарп | FIG-MAGIK-300 | Пластина Маджикарп | 160 | FIG-MAGIK-300 | FIG-MAGIK-300 | FIG-MAGIK-300 | Фігурка-конструктор Magikarp (Pokémon) — 3D-друк | 75 | 60 | false | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=160, model=FIG-MAGIK-300, sku=FIG-MAGIK-300, product_code.SKU=FIG-MAGIK-300, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Пластини | 8 | Пластина Пікачу | FIG-PIKA-300 | Пластина Пікачу | 161 | FIG-PIKA-300 | FIG-PIKA-300 | FIG-PIKA-300 | Фігурка-конструктор Pikachu (Pokémon) — 3D-друк | 75 | 60 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=161, model=FIG-PIKA-300, sku=FIG-PIKA-300, product_code.SKU=FIG-PIKA-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Пластини | 9 | Пластина Сквіртл | FIG-SQUIR-300 | Пластина Сквіртл | 162 | FIG-SQUIR-300 | FIG-SQUIR-300 | FIG-SQUIR-300 | Фігурка-конструктор Squirtle (Pokémon) — 3D-друк | 75 | 60 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=162, model=FIG-SQUIR-300, sku=FIG-SQUIR-300, product_code.SKU=FIG-SQUIR-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Аксесуари шо можна юзать | 3 | Книжкова закладка One piece | ACC-3D-OP-600 | Книжкова закладка One piece | 166 | ACC-3D-OP-600 | null | ACC-3D-OP-600 | Закладка для книг One Piece — 3D-друк | 75 | 60 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=166, model=ACC-3D-OP-600, sku=EMPTY, product_code.SKU=ACC-3D-OP-600, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Аксесуари шо можна юзать | 4 | Підставка під телефон Дітто | ACC-3D-DITTO-410 | Підставка під телефон Дітто | null | null | null | ACC-3D-DITTO-410 | Підставка під телефон Дітто | 100 | 65 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table) | false |
| Аксесуари шо можна юзать | 5 | Стакан під ручки Дітто | ACC-3D-DITTO-430 | Стакан під ручки Дітто | 172 | ACC-3D-DITTO-430 | null | ACC-3D-DITTO-430 | Стакан для ручок Ditto (Pokémon) — 3D-друк | 450 | 350 | false | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=172, model=ACC-3D-DITTO-430, sku=EMPTY, product_code.SKU=ACC-3D-DITTO-430, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Аксесуари шо можна юзать | 6 | Цукерниця Дітто | ACC-3D-DITTO-420 | Цукерниця Дітто | 164 | ACC-3D-DITTO-420 | null | ACC-3D-DITTO-420 | Цукерниця Ditto (Pokémon) — 3D-друк | 450 | 350 | false | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=164, model=ACC-3D-DITTO-420, sku=EMPTY, product_code.SKU=ACC-3D-DITTO-420, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Аксесуари шо можна юзать | 7 | Лампа One piece прямокутна | ACC-3D-OP-500 | Лампа One piece прямокутна | null | null | null | ACC-3D-OP-500 | Лампа One piece прямокутна | 1000 | 600 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table, «Лампа One Piece, прямокутна») | false |
| Аксесуари шо можна юзать | 8 | Лампа One piece тінь Луффі | ACC-3D-LUFFY-500 | Лампа One piece тінь Луффі | null | null | null | ACC-3D-LUFFY-500 | Лампа One piece тінь Луффі | 1000 | 500 | false | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 5__ (lamp); mnemonic LUFFY registered; precedent ACC-3D-OP-500 | false |
| Аксесуари шо можна юзать | 9 | Лампа One piece ягода | ACC-3D-OPFRT-500 | Лампа One piece ягода | null | null | null | ACC-3D-OPFRT-500 | Лампа One piece ягода | null | null | false | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 5__ (lamp); mnemonic OPFRT registered | false |
| Аксесуари шо можна юзать | 10 | Розділювач між картками | ACC-3D-PKM-600 | Розділювач між картками | null | null | null | ACC-3D-PKM-600 | Розділювач між картками | 15 | 10 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 6__ (flat plastic accessories); precedent ACC-3D-OP-600 | false |
| Аксесуари шо можна юзать | 11 | Коробка покебол кругла M | ACC-3D-PKBL-400 | Коробка покебол кругла M | 173 | ACC-3D-PKBL-400 | null | ACC-3D-PKBL-400 | Чаша-покебол для дрібниць (Pokémon) — 3D-друк | 350 | 250 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=173, model=ACC-3D-PKBL-400, sku=EMPTY, product_code.SKU=ACC-3D-PKBL-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |
| Аксесуари шо можна юзать | 12 | Коробка покебол кругла L | ACC-3D-PKBL-401 | Коробка покебол кругла L | null | null | null | ACC-3D-PKBL-401 | Коробка покебол кругла L | 550 | 450 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 7 §2 — «L (ACC-3D-PKBL-401, 222×222×78) повертається опцією на цю саму сторінку»; accounting-only variant of live product 173 | false |
| Аксесуари шо можна юзать | 13 | Коробка під картки чарізард | ACC-3D-CHARZ-800 | Коробка під картки чарізард | null | null | null | ACC-3D-CHARZ-800 | Коробка під картки чарізард | 1100 | 850 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 8__ (ред. 7 §1); mnemonic CHARZ registered ред. 7 | false |
| Аксесуари шо можна юзать | 14 | Покебол для картриджів nintendo | ACC-3D-PKBL-810 | Покебол для картриджів nintendo | null | null | null | ACC-3D-PKBL-810 | Покебол для картриджів nintendo | 400 | 300 | true | APPROVE_CONVENTION_NO_LIVE_MATCH | NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 8__; tens digit 1 is a new subtype (cartridge case) with no documented precedent | true |
| Аксесуари шо можна юзать | 15 | Коробка покебол під картки квіадратна | ACC-3D-PKBL-800 | Коробка покебол під картки квіадратна | 163 | ACC-3D-PKBL-800 | null | ACC-3D-PKBL-800 | Коробка для карток Покебол (Pokémon), квадратна — 3D-друк | 350 | 250 | true | CHANGE_TO_LIVE_CANONICAL | live OpenCart record product_id=163, model=ACC-3D-PKBL-800, sku=EMPTY, product_code.SKU=ACC-3D-PKBL-800, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30 | false |

`live_sku` is `null` wherever the live `ocp5_product.sku` column is empty — that is the real state of the record, not missing evidence. `active` already carries the 2026-09-05 owner override for the three keychains.

## 5. Machine-readable mapping

```json
[
 {
  "source_tab": "Брелоки",
  "source_row": 4,
  "source_name": "Брелок Ч",
  "current_crm_sku": "BR-CHARM-100",
  "current_crm_name": "Брелок Ч",
  "live_product_id": 126,
  "live_model": "BR-CHARM-100",
  "live_sku": "BR-CHARM-100",
  "canonical_sku": "BR-CHARM-100",
  "canonical_name": "Брелок Charmander (Pokémon) — 3D-друк",
  "rrp": 30,
  "buyout": 10,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=126, model=BR-CHARM-100, sku=BR-CHARM-100, product_code.SKU=BR-CHARM-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 5,
  "source_name": "Брелок Б",
  "current_crm_sku": "BR-BULB-100",
  "current_crm_name": "Брелок Б",
  "live_product_id": 128,
  "live_model": "BR-BULB-100",
  "live_sku": "BR-BULB-100",
  "canonical_sku": "BR-BULB-100",
  "canonical_name": "Брелок Bulbasaur (Pokémon) — 3D-друк",
  "rrp": 30,
  "buyout": 10,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=128, model=BR-BULB-100, sku=BR-BULB-100, product_code.SKU=BR-BULB-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні»",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 6,
  "source_name": "Брелок С",
  "current_crm_sku": "BR-SQUIR-100",
  "current_crm_name": "Брелок С",
  "live_product_id": 127,
  "live_model": "BR-SQUIR-100",
  "live_sku": "BR-SQUIR-100",
  "canonical_sku": "BR-SQUIR-100",
  "canonical_name": "Брелок Squirtle (Pokémon) — 3D-друк",
  "rrp": 30,
  "buyout": 10,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=127, model=BR-SQUIR-100, sku=BR-SQUIR-100, product_code.SKU=BR-SQUIR-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні»",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 7,
  "source_name": "Брелок Мью",
  "current_crm_sku": "BR-MEW-100",
  "current_crm_name": "Брелок Мью",
  "live_product_id": 125,
  "live_model": "BR-MEW-100",
  "live_sku": "BR-MEW-100",
  "canonical_sku": "BR-MEW-100",
  "canonical_name": "Брелок Mew (Pokémon) — 3D-друк",
  "rrp": 30,
  "buyout": 15,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=125, model=BR-MEW-100, sku=BR-MEW-100, product_code.SKU=BR-MEW-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 8,
  "source_name": "Брелок Пікачу",
  "current_crm_sku": "BR-PIKA-100",
  "current_crm_name": "Брелок Пікачу",
  "live_product_id": 129,
  "live_model": "BR-PIKA-100",
  "live_sku": "BR-PIKA-100",
  "canonical_sku": "BR-PIKA-100",
  "canonical_name": "Брелок Pikachu (Pokémon) — 3D-друк",
  "rrp": 40,
  "buyout": 15,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=129, model=BR-PIKA-100, sku=BR-PIKA-100, product_code.SKU=BR-PIKA-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні»",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 9,
  "source_name": "Брелок Умбреон стоячий",
  "current_crm_sku": "BR-UMBRE-100",
  "current_crm_name": "Брелок Умбреон стоячий",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-UMBRE-100",
  "canonical_name": "Брелок Умбреон стоячий",
  "rrp": 50,
  "buyout": 20,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; BR- legend 1__ (ordinary pendant); owner decision 2026-09-05 — a pose/form difference is coded in the tens digit, size M/L in the units, so 100/110/120 stands as proposed; no collision",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 10,
  "source_name": "Брелок Умбреон сидячий",
  "current_crm_sku": "BR-UMBRE-110",
  "current_crm_name": "Брелок Умбреон сидячий",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-UMBRE-110",
  "canonical_name": "Брелок Умбреон сидячий",
  "rrp": 50,
  "buyout": 20,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; BR- legend 1__; owner decision 2026-09-05 — pose in the tens digit; no collision",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 11,
  "source_name": "Брелок Умбреон обдовбаний",
  "current_crm_sku": "BR-UMBRE-120",
  "current_crm_name": "Брелок Умбреон обдовбаний",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-UMBRE-120",
  "canonical_name": "Брелок Умбреон обдовбаний",
  "rrp": 50,
  "buyout": 20,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; BR- legend 1__; owner decision 2026-09-05 — pose in the tens digit. Article approved; the source label «обдовбаний» is a working word and must not become the card name when this product is created",
  "owner_approval_required": true
 },
 {
  "source_tab": "Брелоки",
  "source_row": 12,
  "source_name": "Брелок-клікер Чармандер",
  "current_crm_sku": "BR-CHARM-200",
  "current_crm_name": "Брелок-клікер Чармандер",
  "live_product_id": 154,
  "live_model": "BR-CHARM-200",
  "live_sku": "BR-CHARM-200",
  "canonical_sku": "BR-CHARM-200",
  "canonical_name": "Брелок-клікер Charmander (Pokémon) — 3D-друк",
  "rrp": 120,
  "buyout": 70,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=154, model=BR-CHARM-200, sku=BR-CHARM-200, product_code.SKU=BR-CHARM-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 14,
  "source_name": "Брелок One piece Шляпа вусата",
  "current_crm_sku": "BR-OPMUS-100",
  "current_crm_name": "Брелок One piece Шляпа вусата",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-OPMUS-100",
  "canonical_name": "Брелок One piece Шляпа вусата",
  "rrp": 50,
  "buyout": 20,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 15,
  "source_name": "Брелок One piece Шляпа солом'яна",
  "current_crm_sku": "BR-OPSTR-100",
  "current_crm_name": "Брелок One piece Шляпа солом'яна",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-OPSTR-100",
  "canonical_name": "Брелок One piece Шляпа солом'яна",
  "rrp": 50,
  "buyout": 20,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 16,
  "source_name": "Брелок One piece фрукт",
  "current_crm_sku": "BR-OPFRT-100",
  "current_crm_name": "Брелок One piece фрукт",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-OPFRT-100",
  "canonical_name": "Брелок One piece фрукт",
  "rrp": 50,
  "buyout": 20,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 17,
  "source_name": "Брелок One piece \"One piece\"",
  "current_crm_sku": "BR-OP-100",
  "current_crm_name": "Брелок One piece \"One piece\"",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-OP-100",
  "canonical_name": "Брелок One piece \"One piece\"",
  "rrp": 60,
  "buyout": 35,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md formula + BR- legend 1__; mnemonic OP registered; identical shape to its four documented BR-OP*-100 siblings; no collision",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 18,
  "source_name": "Брелок One piece кораблик",
  "current_crm_sku": "BR-OPSHP-100",
  "current_crm_name": "Брелок One piece кораблик",
  "live_product_id": 171,
  "live_model": "BR-OPSHP-100",
  "live_sku": null,
  "canonical_sku": "BR-OPSHP-100",
  "canonical_name": "Брелок Going Merry (One Piece) — 3D-друк",
  "rrp": 60,
  "buyout": 40,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=171, model=BR-OPSHP-100, sku=EMPTY, product_code.SKU=BR-OPSHP-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 19,
  "source_name": "Брелок One piece череп",
  "current_crm_sku": "BR-OPSKL-100",
  "current_crm_name": "Брелок One piece череп",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-OPSKL-100",
  "canonical_name": "Брелок One piece череп",
  "rrp": 50,
  "buyout": 20,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 20,
  "source_name": "Брелок Дітто крутиться",
  "current_crm_sku": "BR-DITTO-400",
  "current_crm_name": "Брелок Дітто крутиться",
  "live_product_id": 170,
  "live_model": "BR-DITTO-400",
  "live_sku": null,
  "canonical_sku": "BR-DITTO-400",
  "canonical_name": "Брелок-спінер Ditto (Pokémon) — 3D-друк",
  "rrp": 70,
  "buyout": 30,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=170, model=BR-DITTO-400, sku=EMPTY, product_code.SKU=BR-DITTO-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Брелоки",
  "source_row": 21,
  "source_name": "Брелок клікер покебол",
  "current_crm_sku": "BR-PKBL-200",
  "current_crm_name": "Брелок клікер покебол",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "BR-PKBL-200",
  "canonical_name": "Брелок клікер покебол",
  "rrp": 120,
  "buyout": 70,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (BR- table, «Брелок клікер покебол»); BR- legend 2__",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 3,
  "source_name": "Підставка мала",
  "current_crm_sku": "ACC-3D-PKM-110",
  "current_crm_name": "Підставка мала",
  "live_product_id": 137,
  "live_model": "ACC-3D-PKM-110",
  "live_sku": "ACC-3D-PKM-110",
  "canonical_sku": "ACC-3D-PKM-110",
  "canonical_name": "Підставка для картки в протекторі — 3D-друк",
  "rrp": 40,
  "buyout": 25,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=137, model=ACC-3D-PKM-110, sku=ACC-3D-PKM-110, product_code.SKU=ACC-3D-PKM-110, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 4,
  "source_name": "Підставка середня",
  "current_crm_sku": "ACC-3D-PKM-120",
  "current_crm_name": "Підставка середня",
  "live_product_id": 138,
  "live_model": "ACC-3D-PKM-120",
  "live_sku": "ACC-3D-PKM-120",
  "canonical_sku": "ACC-3D-PKM-120",
  "canonical_name": "Підставка для картки в топлоадері — 3D-друк",
  "rrp": 70,
  "buyout": 50,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=138, model=ACC-3D-PKM-120, sku=ACC-3D-PKM-120, product_code.SKU=ACC-3D-PKM-120, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 5,
  "source_name": "Підставка велика",
  "current_crm_sku": "ACC-3D-PKM-130",
  "current_crm_name": "Підставка велика",
  "live_product_id": 139,
  "live_model": "ACC-3D-PKM-130",
  "live_sku": "ACC-3D-PKM-130",
  "canonical_sku": "ACC-3D-PKM-130",
  "canonical_name": "Підставка для картки в акриловому кейсі — 3D-друк",
  "rrp": 220,
  "buyout": 160,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=139, model=ACC-3D-PKM-130, sku=ACC-3D-PKM-130, product_code.SKU=ACC-3D-PKM-130, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 6,
  "source_name": "Підставка під грейджені PSA (без покеболу)",
  "current_crm_sku": "ACC-3D-PKM-200",
  "current_crm_name": "Підставка під грейджені PSA (без покеболу)",
  "live_product_id": 140,
  "live_model": "ACC-3D-PKM-200",
  "live_sku": "ACC-3D-PKM-200",
  "canonical_sku": "ACC-3D-PKM-200",
  "canonical_name": "Підставка для слаба — 3D-друк",
  "rrp": 50,
  "buyout": 35,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=140, model=ACC-3D-PKM-200, sku=ACC-3D-PKM-200, product_code.SKU=ACC-3D-PKM-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 7,
  "source_name": "Підставка під грейджені CGC (без покеболу)",
  "current_crm_sku": "ACC-3D-PKM-201",
  "current_crm_name": "Підставка під грейджені CGC (без покеболу)",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKM-201",
  "canonical_name": "Підставка під грейджені CGC (без покеболу)",
  "rrp": 50,
  "buyout": 35,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table, CGC); ред. 4 — CGC returns as a compatibility option on the live ACC-3D-PKM-200 page, so this article is accounting-only",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 8,
  "source_name": "Підставка під грейджені BGC (без покеболу)",
  "current_crm_sku": "ACC-3D-PKM-202",
  "current_crm_name": "Підставка під грейджені BGC (без покеболу)",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKM-202",
  "canonical_name": "Підставка під грейджені BGS (без покеболу)",
  "rrp": 50,
  "buyout": 35,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table); ред. 3 §1 — BGC was an error, correct brand is BGS (Beckett); accounting-only variant of live ACC-3D-PKM-200; name corrected: plans/3D-P_sku-naming-convention_20260807.md ред. 3 §1 — BGC is not a grading company; correct brand is BGS (Beckett)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 9,
  "source_name": "Підставка під грейджені PSA на ніжці",
  "current_crm_sku": "ACC-3D-PKM-300",
  "current_crm_name": "Підставка під грейджені PSA на ніжці",
  "live_product_id": 141,
  "live_model": "ACC-3D-PKM-300",
  "live_sku": "ACC-3D-PKM-300",
  "canonical_sku": "ACC-3D-PKM-300",
  "canonical_name": "Підставка для слаба, на ніжці — 3D-друк",
  "rrp": 270,
  "buyout": 190,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=141, model=ACC-3D-PKM-300, sku=ACC-3D-PKM-300, product_code.SKU=ACC-3D-PKM-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 10,
  "source_name": "Шестигранна крутяща підставка під топлоадери",
  "current_crm_sku": "ACC-3D-PKM-700",
  "current_crm_name": "Шестигранна крутяща підставка під топлоадери",
  "live_product_id": 142,
  "live_model": "ACC-3D-PKM-700",
  "live_sku": "ACC-3D-PKM-700",
  "canonical_sku": "ACC-3D-PKM-700",
  "canonical_name": "Обертова підставка для карток у топлоадерах — 3D-друк",
  "rrp": null,
  "buyout": null,
  "active": false,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=142, model=ACC-3D-PKM-700, sku=ACC-3D-PKM-700, product_code.SKU=ACC-3D-PKM-700, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 11,
  "source_name": "Шестигранна крутяща підставка під грейджені PSA",
  "current_crm_sku": "ACC-3D-PKM-710",
  "current_crm_name": "Шестигранна крутяща підставка під грейджені PSA",
  "live_product_id": 143,
  "live_model": "ACC-3D-PKM-710",
  "live_sku": "ACC-3D-PKM-710",
  "canonical_sku": "ACC-3D-PKM-710",
  "canonical_name": "Обертова підставка для слабів — 3D-друк",
  "rrp": null,
  "buyout": null,
  "active": false,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=143, model=ACC-3D-PKM-710, sku=ACC-3D-PKM-710, product_code.SKU=ACC-3D-PKM-710, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 12,
  "source_name": "Шестигранна крутяща підставка під грейджені BGS",
  "current_crm_sku": "ACC-3D-PKM-711",
  "current_crm_name": "Шестигранна крутяща підставка під грейджені BGS",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKM-711",
  "canonical_name": "Шестигранна крутяща підставка під грейджені BGS",
  "rrp": null,
  "buyout": null,
  "active": false,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 3 §2 — renumbered 211→711, units digit = slab format (1 = BGS); accounting-only variant of live ACC-3D-PKM-710",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 13,
  "source_name": "Шестигранна крутяща підставка під грейджені SGC",
  "current_crm_sku": "ACC-3D-PKM-712",
  "current_crm_name": "Шестигранна крутяща підставка під грейджені SGC",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKM-712",
  "canonical_name": "Шестигранна крутяща підставка під грейджені SGC",
  "rrp": null,
  "buyout": null,
  "active": false,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 3 §2 — units digit 2 = SGC; accounting-only variant of live ACC-3D-PKM-710",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 14,
  "source_name": "Коробка відкрита під картки",
  "current_crm_sku": "ACC-3D-PKM-800",
  "current_crm_name": "Коробка відкрита під картки",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKM-800",
  "canonical_name": "Коробка відкрита під картки",
  "rrp": 420,
  "buyout": 350,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 7 §1 — ACC-3D- 8__ (case/container) opened 2026-08-28; precedent ACC-3D-PKBL-800",
  "owner_approval_required": false
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 15,
  "source_name": "Розділювачі для відкритої коробки під картки",
  "current_crm_sku": "ACC-3D-PKM-610",
  "current_crm_name": "Розділювачі для відкритої коробки під картки",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKM-610",
  "canonical_name": "Розділювачі для відкритої коробки під картки",
  "rrp": 25,
  "buyout": 20,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 6__ (flat plastic accessories); tens digit 1 is a new subtype with no documented precedent",
  "owner_approval_required": true
 },
 {
  "source_tab": "Підставки для карток",
  "source_row": 16,
  "source_name": "Підставка випадаюча рамка",
  "current_crm_sku": "ACC-3D-PKM-150",
  "current_crm_name": "Підставка випадаюча рамка",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKM-150",
  "canonical_name": "Підставка випадаюча рамка",
  "rrp": null,
  "buyout": null,
  "active": false,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 1__ (card stand); tens digit 5 is a new subtype and skips unused 140",
  "owner_approval_required": true
 },
 {
  "source_tab": "Фігурки",
  "source_row": 3,
  "source_name": "Онікс нерухомий",
  "current_crm_sku": "FIG-ONIX-200",
  "current_crm_name": "Онікс нерухомий",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-ONIX-200",
  "canonical_name": "Онікс нерухомий",
  "rrp": 110,
  "buyout": 90,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); convention states the static Onix is a different model, not a variant of the wobble version",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 4,
  "source_name": "Онікс рухомий M",
  "current_crm_sku": "FIG-ONIX-500",
  "current_crm_name": "Онікс рухомий M",
  "live_product_id": 130,
  "live_model": "FIG-ONIX-500",
  "live_sku": "FIG-ONIX-500",
  "canonical_sku": "FIG-ONIX-500",
  "canonical_name": "Фігурка Onix (Pokémon) — 3D-друк",
  "rrp": 160,
  "buyout": 120,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=130, model=FIG-ONIX-500, sku=FIG-ONIX-500, product_code.SKU=FIG-ONIX-500, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 5,
  "source_name": "Онікс рухомий L",
  "current_crm_sku": "FIG-ONIX-501",
  "current_crm_name": "Онікс рухомий L",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-ONIX-501",
  "canonical_name": "Онікс рухомий L",
  "rrp": 220,
  "buyout": 170,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); same model/profileId as FIG-ONIX-500, scale variant — accounting-only variant of live product 130",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 6,
  "source_name": "Геодуд багатокольоровий M",
  "current_crm_sku": "FIG-GEOD-500",
  "current_crm_name": "Геодуд багатокольоровий M",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-GEOD-500",
  "canonical_name": "Геодуд багатокольоровий M",
  "rrp": 90,
  "buyout": 60,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); tens = colour, units = size — accounting-only variant of live product 131",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 7,
  "source_name": "Геодуд багатокольоровий L",
  "current_crm_sku": "FIG-GEOD-501",
  "current_crm_name": "Геодуд багатокольоровий L",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-GEOD-501",
  "canonical_name": "Геодуд багатокольоровий L",
  "rrp": 140,
  "buyout": 100,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); accounting-only variant of live product 131",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 8,
  "source_name": "Геодуд однокольоровий M",
  "current_crm_sku": "FIG-GEOD-510",
  "current_crm_name": "Геодуд однокольоровий M",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-GEOD-510",
  "canonical_name": "Геодуд однокольоровий M",
  "rrp": 75,
  "buyout": 55,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (FIG- table); accounting-only variant of live product 131",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 9,
  "source_name": "Геодуд однокольоровий L",
  "current_crm_sku": "FIG-GEOD-511",
  "current_crm_name": "Геодуд однокольоровий L",
  "live_product_id": 131,
  "live_model": "FIG-GEOD-511",
  "live_sku": "FIG-GEOD-511",
  "canonical_sku": "FIG-GEOD-511",
  "canonical_name": "Фігурка Geodude (Pokémon) — 3D-друк",
  "rrp": 150,
  "buyout": 90,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=131, model=FIG-GEOD-511, sku=FIG-GEOD-511, product_code.SKU=FIG-GEOD-511, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 10,
  "source_name": "Луффі рухомий",
  "current_crm_sku": "FIG-LUFFY-500",
  "current_crm_name": "Луффі рухомий",
  "live_product_id": 134,
  "live_model": "FIG-LUFFY-500",
  "live_sku": "FIG-LUFFY-500",
  "canonical_sku": "FIG-LUFFY-500",
  "canonical_name": "Фігурка Luffy (One Piece) — 3D-друк",
  "rrp": 70,
  "buyout": 40,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=134, model=FIG-LUFFY-500, sku=FIG-LUFFY-500, product_code.SKU=FIG-LUFFY-500, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 11,
  "source_name": "Плаский Луффі силует",
  "current_crm_sku": "FIG-LUFFY-400",
  "current_crm_name": "Плаский Луффі силует",
  "live_product_id": 135,
  "live_model": "FIG-LUFFY-400",
  "live_sku": "FIG-LUFFY-400",
  "canonical_sku": "FIG-LUFFY-400",
  "canonical_name": "Настільна картина Luffy (One Piece) — 3D-друк",
  "rrp": 150,
  "buyout": 80,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=135, model=FIG-LUFFY-400, sku=FIG-LUFFY-400, product_code.SKU=FIG-LUFFY-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 12,
  "source_name": "Пласка картина Луффі",
  "current_crm_sku": "FIG-LUFFY-410",
  "current_crm_name": "Пласка картина Луффі",
  "live_product_id": 136,
  "live_model": "FIG-LUFFY-410",
  "live_sku": "FIG-LUFFY-410",
  "canonical_sku": "FIG-LUFFY-410",
  "canonical_name": "Картина Luffy (One Piece) — 3D-друк",
  "rrp": 300,
  "buyout": 220,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=136, model=FIG-LUFFY-410, sku=FIG-LUFFY-410, product_code.SKU=FIG-LUFFY-410, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 13,
  "source_name": "Хантер в полоску",
  "current_crm_sku": "FIG-HAUNT-200",
  "current_crm_name": "Хантер в полоску",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-HNTR-200",
  "canonical_name": "Хантер в полоску",
  "rrp": 190,
  "buyout": 100,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; Mnemonic HAUNT was never registered; owner delegated the choice 2026-09-05. HNTR = Haunter by the vowel-drop rule in the formula, ≤5 characters, distinct from the registered GENG (Gengar), and absent from the live catalogue. Article otherwise unchanged: FIG- 2__, tens = colour",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 14,
  "source_name": "Хантер в полоску кольоровий",
  "current_crm_sku": "FIG-HAUNT-210",
  "current_crm_name": "Хантер в полоску кольоровий",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-HNTR-210",
  "canonical_name": "Хантер в полоску кольоровий",
  "rrp": 250,
  "buyout": 180,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; Same mnemonic replacement as FIG-HNTR-200; tens digit 1 = colour variant",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 15,
  "source_name": "Покебол із хвостом",
  "current_crm_sku": "FIG-MEW-100",
  "current_crm_name": "Покебол із хвостом",
  "live_product_id": 132,
  "live_model": "FIG-MEW-100",
  "live_sku": "FIG-MEW-100",
  "canonical_sku": "FIG-MEW-100",
  "canonical_name": "Фігурка Mew у покеболі (Pokémon) — 3D-друк",
  "rrp": 175,
  "buyout": 130,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=132, model=FIG-MEW-100, sku=FIG-MEW-100, product_code.SKU=FIG-MEW-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 16,
  "source_name": "Чарізард однокольоровий",
  "current_crm_sku": "FIG-CHARZ-200",
  "current_crm_name": "Чарізард однокольоровий",
  "live_product_id": 167,
  "live_model": "FIG-CHARZ-200",
  "live_sku": null,
  "canonical_sku": "FIG-CHARZ-200",
  "canonical_name": "Фігурка Charizard (Pokémon) — 3D-друк",
  "rrp": 200,
  "buyout": 90,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=167, model=FIG-CHARZ-200, sku=EMPTY, product_code.SKU=FIG-CHARZ-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 17,
  "source_name": "Зоро плаский",
  "current_crm_sku": "FIG-ZORO-410",
  "current_crm_name": "Зоро плаский",
  "live_product_id": 168,
  "live_model": "FIG-ZORO-410",
  "live_sku": null,
  "canonical_sku": "FIG-ZORO-410",
  "canonical_name": "Картина Zoro (One Piece) — 3D-друк",
  "rrp": 150,
  "buyout": 80,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=168, model=FIG-ZORO-410, sku=EMPTY, product_code.SKU=FIG-ZORO-410, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 18,
  "source_name": "Плаский серйозний круглий Луффі",
  "current_crm_sku": "FIG-LUFFY-411",
  "current_crm_name": "Плаский серйозний круглий Луффі",
  "live_product_id": 169,
  "live_model": "FIG-LUFFY-411",
  "live_sku": null,
  "canonical_sku": "FIG-LUFFY-411",
  "canonical_name": "Картина Luffy (One Piece), кругла — 3D-друк",
  "rrp": 250,
  "buyout": 80,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=169, model=FIG-LUFFY-411, sku=EMPTY, product_code.SKU=FIG-LUFFY-411, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 19,
  "source_name": "Клікер Череп ван піс",
  "current_crm_sku": "FIG-OPSKL-600",
  "current_crm_name": "Клікер Череп ван піс",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-OPSKL-600",
  "canonical_name": "Клікер Череп ван піс",
  "rrp": 130,
  "buyout": 80,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 2 §2 — FIG- 6__ = fidget mechanics (clicker); mnemonic OPSKL registered; no collision",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 20,
  "source_name": "ONE PIECE команда",
  "current_crm_sku": "FIG-OP-400",
  "current_crm_name": "ONE PIECE команда",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-OP-410",
  "canonical_name": "ONE PIECE команда",
  "rrp": 150,
  "buyout": 100,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; Owner decision 2026-09-05 — the One Piece crew piece is a картина, not панно. ред. 8 §1 codes панно as subtype 0 and картина as subtype 1, so the article moves to 410. The article is not live, so no renumbering cost applies (unlike FIG-LUFFY-400, kept at 400 because it is live)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 21,
  "source_name": "Намі S",
  "current_crm_sku": "FIG-NAMI-200",
  "current_crm_name": "Намі S",
  "live_product_id": 165,
  "live_model": "FIG-NAMI-200",
  "live_sku": null,
  "canonical_sku": "FIG-NAMI-200",
  "canonical_name": "Фігурка Nami (One Piece) — 3D-друк",
  "rrp": 200,
  "buyout": 120,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=165, model=FIG-NAMI-200, sku=EMPTY, product_code.SKU=FIG-NAMI-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 22,
  "source_name": "Намі L",
  "current_crm_sku": "FIG-NAMI-201",
  "current_crm_name": "Намі L",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "FIG-NAMI-201",
  "canonical_name": "Намі L",
  "rrp": 750,
  "buyout": 500,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 7 §5 — «Намі L в обліку — FIG-NAMI-201»; size returns as an option on the live FIG-NAMI-200 page; owner price override RRP 750 / buyout 500 (2026-09-02)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Фігурки",
  "source_row": 23,
  "source_name": "Стоячий клікер покебол",
  "current_crm_sku": "FIG-PKBL-600",
  "current_crm_name": "Стоячий клікер покебол",
  "live_product_id": 133,
  "live_model": "FIG-PKBL-600",
  "live_sku": "FIG-PKBL-600",
  "canonical_sku": "FIG-PKBL-600",
  "canonical_name": "Фігурка-клікер Покебол (Pokémon) — 3D-друк",
  "rrp": 50,
  "buyout": 30,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=133, model=FIG-PKBL-600, sku=FIG-PKBL-600, product_code.SKU=FIG-PKBL-600, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Пластини",
  "source_row": 3,
  "source_name": "Пластина Джигліпаф",
  "current_crm_sku": "FIG-JIGGL-300",
  "current_crm_name": "Пластина Джигліпаф",
  "live_product_id": 156,
  "live_model": "FIG-JIGGL-300",
  "live_sku": "FIG-JIGGL-300",
  "canonical_sku": "FIG-JIGGL-300",
  "canonical_name": "Фігурка-конструктор Jigglypuff (Pokémon) — 3D-друк",
  "rrp": 75,
  "buyout": 60,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=156, model=FIG-JIGGL-300, sku=FIG-JIGGL-300, product_code.SKU=FIG-JIGGL-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Пластини",
  "source_row": 4,
  "source_name": "Пластина Мью",
  "current_crm_sku": "FIG-MEW-300",
  "current_crm_name": "Пластина Мью",
  "live_product_id": 157,
  "live_model": "FIG-MEW-300",
  "live_sku": "FIG-MEW-300",
  "canonical_sku": "FIG-MEW-300",
  "canonical_name": "Фігурка-конструктор Mew (Pokémon) — 3D-друк",
  "rrp": 75,
  "buyout": 60,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=157, model=FIG-MEW-300, sku=FIG-MEW-300, product_code.SKU=FIG-MEW-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Пластини",
  "source_row": 5,
  "source_name": "Пластина Умбреон",
  "current_crm_sku": "FIG-UMBRE-300",
  "current_crm_name": "Пластина Умбреон",
  "live_product_id": 158,
  "live_model": "FIG-UMBRE-300",
  "live_sku": "FIG-UMBRE-300",
  "canonical_sku": "FIG-UMBRE-300",
  "canonical_name": "Фігурка-конструктор Umbreon (Pokémon) — 3D-друк",
  "rrp": 90,
  "buyout": 70,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=158, model=FIG-UMBRE-300, sku=FIG-UMBRE-300, product_code.SKU=FIG-UMBRE-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Пластини",
  "source_row": 6,
  "source_name": "Пластина Генгар",
  "current_crm_sku": "FIG-GENG-300",
  "current_crm_name": "Пластина Генгар",
  "live_product_id": 159,
  "live_model": "FIG-GENG-300",
  "live_sku": "FIG-GENG-300",
  "canonical_sku": "FIG-GENG-300",
  "canonical_name": "Фігурка-конструктор Gengar (Pokémon) — 3D-друк",
  "rrp": 120,
  "buyout": 110,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=159, model=FIG-GENG-300, sku=FIG-GENG-300, product_code.SKU=FIG-GENG-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Пластини",
  "source_row": 7,
  "source_name": "Пластина Маджикарп",
  "current_crm_sku": "FIG-MAGIK-300",
  "current_crm_name": "Пластина Маджикарп",
  "live_product_id": 160,
  "live_model": "FIG-MAGIK-300",
  "live_sku": "FIG-MAGIK-300",
  "canonical_sku": "FIG-MAGIK-300",
  "canonical_name": "Фігурка-конструктор Magikarp (Pokémon) — 3D-друк",
  "rrp": 75,
  "buyout": 60,
  "active": false,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=160, model=FIG-MAGIK-300, sku=FIG-MAGIK-300, product_code.SKU=FIG-MAGIK-300, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Пластини",
  "source_row": 8,
  "source_name": "Пластина Пікачу",
  "current_crm_sku": "FIG-PIKA-300",
  "current_crm_name": "Пластина Пікачу",
  "live_product_id": 161,
  "live_model": "FIG-PIKA-300",
  "live_sku": "FIG-PIKA-300",
  "canonical_sku": "FIG-PIKA-300",
  "canonical_name": "Фігурка-конструктор Pikachu (Pokémon) — 3D-друк",
  "rrp": 75,
  "buyout": 60,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=161, model=FIG-PIKA-300, sku=FIG-PIKA-300, product_code.SKU=FIG-PIKA-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Пластини",
  "source_row": 9,
  "source_name": "Пластина Сквіртл",
  "current_crm_sku": "FIG-SQUIR-300",
  "current_crm_name": "Пластина Сквіртл",
  "live_product_id": 162,
  "live_model": "FIG-SQUIR-300",
  "live_sku": "FIG-SQUIR-300",
  "canonical_sku": "FIG-SQUIR-300",
  "canonical_name": "Фігурка-конструктор Squirtle (Pokémon) — 3D-друк",
  "rrp": 75,
  "buyout": 60,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=162, model=FIG-SQUIR-300, sku=FIG-SQUIR-300, product_code.SKU=FIG-SQUIR-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 3,
  "source_name": "Книжкова закладка One piece",
  "current_crm_sku": "ACC-3D-OP-600",
  "current_crm_name": "Книжкова закладка One piece",
  "live_product_id": 166,
  "live_model": "ACC-3D-OP-600",
  "live_sku": null,
  "canonical_sku": "ACC-3D-OP-600",
  "canonical_name": "Закладка для книг One Piece — 3D-друк",
  "rrp": 75,
  "buyout": 60,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=166, model=ACC-3D-OP-600, sku=EMPTY, product_code.SKU=ACC-3D-OP-600, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 4,
  "source_name": "Підставка під телефон Дітто",
  "current_crm_sku": "ACC-3D-DITTO-410",
  "current_crm_name": "Підставка під телефон Дітто",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-DITTO-410",
  "canonical_name": "Підставка під телефон Дітто",
  "rrp": 100,
  "buyout": 65,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 5,
  "source_name": "Стакан під ручки Дітто",
  "current_crm_sku": "ACC-3D-DITTO-430",
  "current_crm_name": "Стакан під ручки Дітто",
  "live_product_id": 172,
  "live_model": "ACC-3D-DITTO-430",
  "live_sku": null,
  "canonical_sku": "ACC-3D-DITTO-430",
  "canonical_name": "Стакан для ручок Ditto (Pokémon) — 3D-друк",
  "rrp": 450,
  "buyout": 350,
  "active": false,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=172, model=ACC-3D-DITTO-430, sku=EMPTY, product_code.SKU=ACC-3D-DITTO-430, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 6,
  "source_name": "Цукерниця Дітто",
  "current_crm_sku": "ACC-3D-DITTO-420",
  "current_crm_name": "Цукерниця Дітто",
  "live_product_id": 164,
  "live_model": "ACC-3D-DITTO-420",
  "live_sku": null,
  "canonical_sku": "ACC-3D-DITTO-420",
  "canonical_name": "Цукерниця Ditto (Pokémon) — 3D-друк",
  "rrp": 450,
  "buyout": 350,
  "active": false,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=164, model=ACC-3D-DITTO-420, sku=EMPTY, product_code.SKU=ACC-3D-DITTO-420, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 7,
  "source_name": "Лампа One piece прямокутна",
  "current_crm_sku": "ACC-3D-OP-500",
  "current_crm_name": "Лампа One piece прямокутна",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-OP-500",
  "canonical_name": "Лампа One piece прямокутна",
  "rrp": 1000,
  "buyout": 600,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table, «Лампа One Piece, прямокутна»)",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 8,
  "source_name": "Лампа One piece тінь Луффі",
  "current_crm_sku": "ACC-3D-LUFFY-500",
  "current_crm_name": "Лампа One piece тінь Луффі",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-LUFFY-500",
  "canonical_name": "Лампа One piece тінь Луффі",
  "rrp": 1000,
  "buyout": 500,
  "active": false,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 5__ (lamp); mnemonic LUFFY registered; precedent ACC-3D-OP-500",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 9,
  "source_name": "Лампа One piece ягода",
  "current_crm_sku": "ACC-3D-OPFRT-500",
  "current_crm_name": "Лампа One piece ягода",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-OPFRT-500",
  "canonical_name": "Лампа One piece ягода",
  "rrp": null,
  "buyout": null,
  "active": false,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 5__ (lamp); mnemonic OPFRT registered",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 10,
  "source_name": "Розділювач між картками",
  "current_crm_sku": "ACC-3D-PKM-600",
  "current_crm_name": "Розділювач між картками",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKM-600",
  "canonical_name": "Розділювач між картками",
  "rrp": 15,
  "buyout": 10,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 6__ (flat plastic accessories); precedent ACC-3D-OP-600",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 11,
  "source_name": "Коробка покебол кругла M",
  "current_crm_sku": "ACC-3D-PKBL-400",
  "current_crm_name": "Коробка покебол кругла M",
  "live_product_id": 173,
  "live_model": "ACC-3D-PKBL-400",
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKBL-400",
  "canonical_name": "Чаша-покебол для дрібниць (Pokémon) — 3D-друк",
  "rrp": 350,
  "buyout": 250,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=173, model=ACC-3D-PKBL-400, sku=EMPTY, product_code.SKU=ACC-3D-PKBL-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 12,
  "source_name": "Коробка покебол кругла L",
  "current_crm_sku": "ACC-3D-PKBL-401",
  "current_crm_name": "Коробка покебол кругла L",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKBL-401",
  "canonical_name": "Коробка покебол кругла L",
  "rrp": 550,
  "buyout": 450,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ред. 7 §2 — «L (ACC-3D-PKBL-401, 222×222×78) повертається опцією на цю саму сторінку»; accounting-only variant of live product 173",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 13,
  "source_name": "Коробка під картки чарізард",
  "current_crm_sku": "ACC-3D-CHARZ-800",
  "current_crm_name": "Коробка під картки чарізард",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-CHARZ-800",
  "canonical_name": "Коробка під картки чарізард",
  "rrp": 1100,
  "buyout": 850,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 8__ (ред. 7 §1); mnemonic CHARZ registered ред. 7",
  "owner_approval_required": false
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 14,
  "source_name": "Покебол для картриджів nintendo",
  "current_crm_sku": "ACC-3D-PKBL-810",
  "current_crm_name": "Покебол для картриджів nintendo",
  "live_product_id": null,
  "live_model": null,
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKBL-810",
  "canonical_name": "Покебол для картриджів nintendo",
  "rrp": 400,
  "buyout": 300,
  "active": true,
  "decision": "APPROVE_CONVENTION_NO_LIVE_MATCH",
  "evidence": "NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md ACC-3D- legend 8__; tens digit 1 is a new subtype (cartridge case) with no documented precedent",
  "owner_approval_required": true
 },
 {
  "source_tab": "Аксесуари шо можна юзать",
  "source_row": 15,
  "source_name": "Коробка покебол під картки квіадратна",
  "current_crm_sku": "ACC-3D-PKBL-800",
  "current_crm_name": "Коробка покебол під картки квіадратна",
  "live_product_id": 163,
  "live_model": "ACC-3D-PKBL-800",
  "live_sku": null,
  "canonical_sku": "ACC-3D-PKBL-800",
  "canonical_name": "Коробка для карток Покебол (Pokémon), квадратна — 3D-друк",
  "rrp": 350,
  "buyout": 250,
  "active": true,
  "decision": "CHANGE_TO_LIVE_CANONICAL",
  "evidence": "live OpenCart record product_id=163, model=ACC-3D-PKBL-800, sku=EMPTY, product_code.SKU=ACC-3D-PKBL-800, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30",
  "owner_approval_required": false
 }
]
```

## 6. Changes against the current migration payload

Format: `source_tab/source_row: OLD_SKU -> NEW_SKU; OLD_NAME -> NEW_NAME; evidence`.

- `Брелоки/4`: BR-CHARM-100 (unchanged); Брелок Ч -> Брелок Charmander (Pokémon) — 3D-друк; live OpenCart record product_id=126, model=BR-CHARM-100, sku=BR-CHARM-100, product_code.SKU=BR-CHARM-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Брелоки/5`: BR-BULB-100 (unchanged); Брелок Б -> Брелок Bulbasaur (Pokémon) — 3D-друк; live OpenCart record product_id=128, model=BR-BULB-100, sku=BR-BULB-100, product_code.SKU=BR-BULB-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні»
- `Брелоки/6`: BR-SQUIR-100 (unchanged); Брелок С -> Брелок Squirtle (Pokémon) — 3D-друк; live OpenCart record product_id=127, model=BR-SQUIR-100, sku=BR-SQUIR-100, product_code.SKU=BR-SQUIR-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні»
- `Брелоки/7`: BR-MEW-100 (unchanged); Брелок Мью -> Брелок Mew (Pokémon) — 3D-друк; live OpenCart record product_id=125, model=BR-MEW-100, sku=BR-MEW-100, product_code.SKU=BR-MEW-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Брелоки/8`: BR-PIKA-100 (unchanged); Брелок Пікачу -> Брелок Pikachu (Pokémon) — 3D-друк; live OpenCart record product_id=129, model=BR-PIKA-100, sku=BR-PIKA-100, product_code.SKU=BR-PIKA-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30; owner decision 2026-09-05 — «друкувати можемо», product moves to active; supersedes the source «Можливість зараз друкувати: Ні»
- `Брелоки/12`: BR-CHARM-200 (unchanged); Брелок-клікер Чармандер -> Брелок-клікер Charmander (Pokémon) — 3D-друк; live OpenCart record product_id=154, model=BR-CHARM-200, sku=BR-CHARM-200, product_code.SKU=BR-CHARM-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Брелоки/18`: BR-OPSHP-100 (unchanged); Брелок One piece кораблик -> Брелок Going Merry (One Piece) — 3D-друк; live OpenCart record product_id=171, model=BR-OPSHP-100, sku=EMPTY, product_code.SKU=BR-OPSHP-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Брелоки/20`: BR-DITTO-400 (unchanged); Брелок Дітто крутиться -> Брелок-спінер Ditto (Pokémon) — 3D-друк; live OpenCart record product_id=170, model=BR-DITTO-400, sku=EMPTY, product_code.SKU=BR-DITTO-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Підставки для карток/3`: ACC-3D-PKM-110 (unchanged); Підставка мала -> Підставка для картки в протекторі — 3D-друк; live OpenCart record product_id=137, model=ACC-3D-PKM-110, sku=ACC-3D-PKM-110, product_code.SKU=ACC-3D-PKM-110, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Підставки для карток/4`: ACC-3D-PKM-120 (unchanged); Підставка середня -> Підставка для картки в топлоадері — 3D-друк; live OpenCart record product_id=138, model=ACC-3D-PKM-120, sku=ACC-3D-PKM-120, product_code.SKU=ACC-3D-PKM-120, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Підставки для карток/5`: ACC-3D-PKM-130 (unchanged); Підставка велика -> Підставка для картки в акриловому кейсі — 3D-друк; live OpenCart record product_id=139, model=ACC-3D-PKM-130, sku=ACC-3D-PKM-130, product_code.SKU=ACC-3D-PKM-130, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Підставки для карток/6`: ACC-3D-PKM-200 (unchanged); Підставка під грейджені PSA (без покеболу) -> Підставка для слаба — 3D-друк; live OpenCart record product_id=140, model=ACC-3D-PKM-200, sku=ACC-3D-PKM-200, product_code.SKU=ACC-3D-PKM-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Підставки для карток/8`: ACC-3D-PKM-202 (unchanged); Підставка під грейджені BGC (без покеболу) -> Підставка під грейджені BGS (без покеболу); NO_LIVE_MATCH; plans/3D-P_sku-naming-convention_20260807.md §Застосування 2026-08-07 (ACC-3D- table); ред. 3 §1 — BGC was an error, correct brand is BGS (Beckett); accounting-only variant of live ACC-3D-PKM-200; name corrected: plans/3D-P_sku-naming-convention_20260807.md ред. 3 §1 — BGC is not a grading company; correct brand is BGS (Beckett)
- `Підставки для карток/9`: ACC-3D-PKM-300 (unchanged); Підставка під грейджені PSA на ніжці -> Підставка для слаба, на ніжці — 3D-друк; live OpenCart record product_id=141, model=ACC-3D-PKM-300, sku=ACC-3D-PKM-300, product_code.SKU=ACC-3D-PKM-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Підставки для карток/10`: ACC-3D-PKM-700 (unchanged); Шестигранна крутяща підставка під топлоадери -> Обертова підставка для карток у топлоадерах — 3D-друк; live OpenCart record product_id=142, model=ACC-3D-PKM-700, sku=ACC-3D-PKM-700, product_code.SKU=ACC-3D-PKM-700, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Підставки для карток/11`: ACC-3D-PKM-710 (unchanged); Шестигранна крутяща підставка під грейджені PSA -> Обертова підставка для слабів — 3D-друк; live OpenCart record product_id=143, model=ACC-3D-PKM-710, sku=ACC-3D-PKM-710, product_code.SKU=ACC-3D-PKM-710, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/4`: FIG-ONIX-500 (unchanged); Онікс рухомий M -> Фігурка Onix (Pokémon) — 3D-друк; live OpenCart record product_id=130, model=FIG-ONIX-500, sku=FIG-ONIX-500, product_code.SKU=FIG-ONIX-500, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/9`: FIG-GEOD-511 (unchanged); Геодуд однокольоровий L -> Фігурка Geodude (Pokémon) — 3D-друк; live OpenCart record product_id=131, model=FIG-GEOD-511, sku=FIG-GEOD-511, product_code.SKU=FIG-GEOD-511, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/10`: FIG-LUFFY-500 (unchanged); Луффі рухомий -> Фігурка Luffy (One Piece) — 3D-друк; live OpenCart record product_id=134, model=FIG-LUFFY-500, sku=FIG-LUFFY-500, product_code.SKU=FIG-LUFFY-500, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/11`: FIG-LUFFY-400 (unchanged); Плаский Луффі силует -> Настільна картина Luffy (One Piece) — 3D-друк; live OpenCart record product_id=135, model=FIG-LUFFY-400, sku=FIG-LUFFY-400, product_code.SKU=FIG-LUFFY-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/12`: FIG-LUFFY-410 (unchanged); Пласка картина Луффі -> Картина Luffy (One Piece) — 3D-друк; live OpenCart record product_id=136, model=FIG-LUFFY-410, sku=FIG-LUFFY-410, product_code.SKU=FIG-LUFFY-410, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/13`: FIG-HAUNT-200 -> FIG-HNTR-200; Хантер в полоску (unchanged); NO_LIVE_MATCH; Mnemonic HAUNT was never registered; owner delegated the choice 2026-09-05. HNTR = Haunter by the vowel-drop rule in the formula, ≤5 characters, distinct from the registered GENG (Gengar), and absent from the live catalogue. Article otherwise unchanged: FIG- 2__, tens = colour
- `Фігурки/14`: FIG-HAUNT-210 -> FIG-HNTR-210; Хантер в полоску кольоровий (unchanged); NO_LIVE_MATCH; Same mnemonic replacement as FIG-HNTR-200; tens digit 1 = colour variant
- `Фігурки/15`: FIG-MEW-100 (unchanged); Покебол із хвостом -> Фігурка Mew у покеболі (Pokémon) — 3D-друк; live OpenCart record product_id=132, model=FIG-MEW-100, sku=FIG-MEW-100, product_code.SKU=FIG-MEW-100, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/16`: FIG-CHARZ-200 (unchanged); Чарізард однокольоровий -> Фігурка Charizard (Pokémon) — 3D-друк; live OpenCart record product_id=167, model=FIG-CHARZ-200, sku=EMPTY, product_code.SKU=FIG-CHARZ-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/17`: FIG-ZORO-410 (unchanged); Зоро плаский -> Картина Zoro (One Piece) — 3D-друк; live OpenCart record product_id=168, model=FIG-ZORO-410, sku=EMPTY, product_code.SKU=FIG-ZORO-410, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/18`: FIG-LUFFY-411 (unchanged); Плаский серйозний круглий Луффі -> Картина Luffy (One Piece), кругла — 3D-друк; live OpenCart record product_id=169, model=FIG-LUFFY-411, sku=EMPTY, product_code.SKU=FIG-LUFFY-411, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/20`: FIG-OP-400 -> FIG-OP-410; ONE PIECE команда (unchanged); NO_LIVE_MATCH; Owner decision 2026-09-05 — the One Piece crew piece is a картина, not панно. ред. 8 §1 codes панно as subtype 0 and картина as subtype 1, so the article moves to 410. The article is not live, so no renumbering cost applies (unlike FIG-LUFFY-400, kept at 400 because it is live)
- `Фігурки/21`: FIG-NAMI-200 (unchanged); Намі S -> Фігурка Nami (One Piece) — 3D-друк; live OpenCart record product_id=165, model=FIG-NAMI-200, sku=EMPTY, product_code.SKU=FIG-NAMI-200, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Фігурки/23`: FIG-PKBL-600 (unchanged); Стоячий клікер покебол -> Фігурка-клікер Покебол (Pokémon) — 3D-друк; live OpenCart record product_id=133, model=FIG-PKBL-600, sku=FIG-PKBL-600, product_code.SKU=FIG-PKBL-600, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Пластини/3`: FIG-JIGGL-300 (unchanged); Пластина Джигліпаф -> Фігурка-конструктор Jigglypuff (Pokémon) — 3D-друк; live OpenCart record product_id=156, model=FIG-JIGGL-300, sku=FIG-JIGGL-300, product_code.SKU=FIG-JIGGL-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Пластини/4`: FIG-MEW-300 (unchanged); Пластина Мью -> Фігурка-конструктор Mew (Pokémon) — 3D-друк; live OpenCart record product_id=157, model=FIG-MEW-300, sku=FIG-MEW-300, product_code.SKU=FIG-MEW-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Пластини/5`: FIG-UMBRE-300 (unchanged); Пластина Умбреон -> Фігурка-конструктор Umbreon (Pokémon) — 3D-друк; live OpenCart record product_id=158, model=FIG-UMBRE-300, sku=FIG-UMBRE-300, product_code.SKU=FIG-UMBRE-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Пластини/6`: FIG-GENG-300 (unchanged); Пластина Генгар -> Фігурка-конструктор Gengar (Pokémon) — 3D-друк; live OpenCart record product_id=159, model=FIG-GENG-300, sku=FIG-GENG-300, product_code.SKU=FIG-GENG-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Пластини/7`: FIG-MAGIK-300 (unchanged); Пластина Маджикарп -> Фігурка-конструктор Magikarp (Pokémon) — 3D-друк; live OpenCart record product_id=160, model=FIG-MAGIK-300, sku=FIG-MAGIK-300, product_code.SKU=FIG-MAGIK-300, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Пластини/8`: FIG-PIKA-300 (unchanged); Пластина Пікачу -> Фігурка-конструктор Pikachu (Pokémon) — 3D-друк; live OpenCart record product_id=161, model=FIG-PIKA-300, sku=FIG-PIKA-300, product_code.SKU=FIG-PIKA-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Пластини/9`: FIG-SQUIR-300 (unchanged); Пластина Сквіртл -> Фігурка-конструктор Squirtle (Pokémon) — 3D-друк; live OpenCart record product_id=162, model=FIG-SQUIR-300, sku=FIG-SQUIR-300, product_code.SKU=FIG-SQUIR-300, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Аксесуари шо можна юзать/3`: ACC-3D-OP-600 (unchanged); Книжкова закладка One piece -> Закладка для книг One Piece — 3D-друк; live OpenCart record product_id=166, model=ACC-3D-OP-600, sku=EMPTY, product_code.SKU=ACC-3D-OP-600, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Аксесуари шо можна юзать/5`: ACC-3D-DITTO-430 (unchanged); Стакан під ручки Дітто -> Стакан для ручок Ditto (Pokémon) — 3D-друк; live OpenCart record product_id=172, model=ACC-3D-DITTO-430, sku=EMPTY, product_code.SKU=ACC-3D-DITTO-430, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Аксесуари шо можна юзать/6`: ACC-3D-DITTO-420 (unchanged); Цукерниця Дітто -> Цукерниця Ditto (Pokémon) — 3D-друк; live OpenCart record product_id=164, model=ACC-3D-DITTO-420, sku=EMPTY, product_code.SKU=ACC-3D-DITTO-420, status=0; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Аксесуари шо можна юзать/11`: ACC-3D-PKBL-400 (unchanged); Коробка покебол кругла M -> Чаша-покебол для дрібниць (Pokémon) — 3D-друк; live OpenCart record product_id=173, model=ACC-3D-PKBL-400, sku=EMPTY, product_code.SKU=ACC-3D-PKBL-400, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30
- `Аксесуари шо можна юзать/15`: ACC-3D-PKBL-800 (unchanged); Коробка покебол під картки квіадратна -> Коробка для карток Покебол (Pokémon), квадратна — 3D-друк; live OpenCart record product_id=163, model=ACC-3D-PKBL-800, sku=EMPTY, product_code.SKU=ACC-3D-PKBL-800, status=1; backup boosters_ocart49.sql, 2026-09-03 21:30

Status changes, which the list above does not carry:

- `Брелоки/5`: `BR-BULB-100` inactive -> active;
- `Брелоки/6`: `BR-SQUIR-100` inactive -> active;
- `Брелоки/8`: `BR-PIKA-100` inactive -> active.

The remaining 30 rows keep their current article, name and status.

## 7. Required follow-ups outside this mapping

1. **Register `HNTR` in the convention** (`plans/3D-P_sku-naming-convention_20260807.md`, «Мнемоніки, вже використані») and record the owner rule from §2.1 — pose in the tens digit, size in the units. Claude (chat) did not edit that canonical document; it needs the assigned executor and an owner sign-off.
2. **Update the source spreadsheet flag** for the three keychains, or the next import will undo decision 4.
3. `ACC-3D-PKM-610`, `ACC-3D-PKM-150` and `ACC-3D-PKBL-810` each open a new tens-digit subtype with no documented precedent (`ACC-3D-PKM-150` also skips the unused `140`). They conform to the formula, collide with nothing and are already in CRM, so they are approved here — but the owner may want to renumber them before the catalogue grows.
4. The internal names of `ACC-3D-PKM-201`, `-202`, `-711`, `-712` still contain «грейджені», which ред. 6 bans in product names, Meta fields, keywords and attribute values. Harmless as a CRM row label; it must not reach a product card. The same applies to the working word «обдовбаний» in `BR-UMBRE-120`.
5. 33 of the 38 live 3D products carry a non-zero OpenCart quantity (2–15 units) while this migration sets opening stock to zero in CRM and 3D-P. A sale of such a unit will have no costed FIFO batch behind it — `fifo-contract.md` requires an explicit insufficient-costed-stock result rather than a fabricated cost. Out of scope for the mapping; it belongs to the migration gate.

## 8. Collision check

Every canonical article was checked against **all 118 live products** across `model`, `sku` and `product_code.SKU` — not only within the 72 rows.

- Duplicate canonical articles inside the 72: **none** (72 unique values).
- A canonical article carried by an unrelated live product: **none**.
- The three newly assigned articles `FIG-HNTR-200`, `FIG-HNTR-210`, `FIG-OP-410`: absent from the live catalogue and from the other 69 rows.
- Duplicate `model` values anywhere in the live catalogue: **none**. Duplicate `product_code.SKU`: **none**.
- 3D-looking live products outside the 72: **none**. The test record `FIG-CHARM-001` (product 118), still present in the 2026-08-28 export, is absent from the 2026-09-03 database — the owner deleted it as the convention said would happen.
- Both excluded three-piece sets (`BR-PKM-300`, `BR-OP-300`) are absent from the 72 and from the live catalogue.

## 9. Count reconciliation

| Gate | Result |
|---|---|
| Source identities equal to `import-manifest.json` | 72 / 72, matched by `source_tab` + `source_row` |
| Active / inactive | **62 / 10** after the 2026-09-05 owner decision (was 59 / 13) |
| Live matches | 38 (all by exact article; `product_id`, `model`, `sku`, name recorded) |
| `NO_LIVE_MATCH` | 34, of which 10 are accounting-only variants of an existing live page |
| The 20 previously unverified proposals | 4 confirmed by live records, 13 approved from the convention, 3 reassigned by owner decision (`FIG-HNTR-200/210`, `FIG-OP-410`) |
| `FIG-ZORO-410` vs `400` | `FIG-ZORO-410` — ред. 8 §1 and live product 168 |
| `FIG-PKBL-600` vs `100` | `FIG-PKBL-600` — ред. 2 §2 renumbering and live product 133 |
| `BGC` vs `BGS` | `BGS` — ред. 3 §1; `ACC-3D-PKM-202` name corrected accordingly |
| `FIG-NAMI-201` | RRP 750, buyout 500 (owner override 2026-09-02) preserved |
| Excluded three-piece sets | absent |
| Duplicate canonical articles | 0 |
| Rows still requiring an owner decision | 0 |
| Live or repository mutation during the audit | none |

## 10. Rules this file follows

- A product that exists live takes its name and article from the live record. The site is the authority for anything already published.
- A row with no live record keeps its current name, unless the convention states an approved correction (only `ACC-3D-PKM-202`) or the owner ruled otherwise on 2026-09-05. No name was normalised, translated or restyled for consistency alone.
- Ten of the `NO_LIVE_MATCH` rows are variants that ред. 2 §1 keeps as accounting articles behind one site page: `ACC-3D-PKM-201`, `-202`, `-711`, `-712`, `ACC-3D-PKBL-401`, `FIG-GEOD-500`, `-501`, `-510`, `FIG-ONIX-501`, `FIG-NAMI-201`. They are not missing product pages, and none should be created as one. `FIG-ONIX-200` is the opposite case — the convention states it is a different model, not a variant.
- Unknown is `null`. Nothing in this file is a recommendation presented as fact.
