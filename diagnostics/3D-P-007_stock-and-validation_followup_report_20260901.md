# 3D-P-007 stock and validation follow-up report

Date: 2026-09-01

## Outcome

- Restored the local Serhiy server on `http://127.0.0.1:3107/` and verified an HTTP 200 response plus a real-browser load of the rebuilt extracted package.
- Repaired the Ditto stock formula in the live 3D-P workbook. `ACC-3D-DITTO-410` now has 10 printed units, a net stock adjustment of +2, and 12 available units.
- Removed the known invalid dropdown values from the new draft row and expanded the Nomenclature type validator to include the canonical `Брелок` type.
- Changed the Serhiy draft form so its 17 detailed mechanics/categories are mapped to one of the four broad values accepted by Nomenclature column D. Future Serhiy drafts no longer write detailed category labels into the broad type column.

## Root causes

### Ditto stock

`Наявність!C2` contained the literal value `99` instead of the print-log aggregation formula. The surrounding stock columns still contained formulas, so the workbook calculated `101` available units from a false printed count.

Bounded API evidence for `ACC-3D-DITTO-410`:

- active print-log quantities: `4 + 4 + 1 + 1 = 10`;
- stock adjustments: `-97 + 99 = +2`;
- correct availability: `10 + 2 = 12`.

The dashboard and Serhiy server print actions were writing append-only print-log records correctly. The defect was the historical literal value in the derived stock cell, not the current append workflow.

### Nomenclature validation

The live validation rules were read from the bounded `Номенклатура!C:F` range before editing:

- C, franchise: `Pokémon`, `One Piece`, `MTG`, `Yu-Gi-Oh!`, `Універсальний`, `Інше`;
- D, broad type: `Фігурка`, `Функціональний аксесуар`, `Інше`;
- E, track: `Продаж на сайті`, `Маркетингова плюшка`, `Тестовий зразок`;
- F, status: `У розробці`, `Тестовий друк`, `Готовий до продажу`, `Активний`, `Знятий з продажу`, `Референс`.

Two independent problems caused invalid-value warnings:

1. Column D omitted the already-used canonical type `Брелок`.
2. The Serhiy form displayed 17 detailed SKU categories and wrote the selected detailed label directly into D, even though D is a four-value broad type field.

The promoted test row `FIG-123-500` also contained invented placeholder values `Не вказано` in C, E and F, which are not members of those dropdown lists.

## Live workbook changes

The following narrow changes were applied through Google Sheets and immediately re-read:

1. `Наявність!C2` was restored to:

   ```text
   =IF(A2="";"";SUMIFS('Друк-лог'!$C:$C;'Друк-лог'!$B:$B;A2;'Друк-лог'!$J:$J;"<>Архів"))
   ```

   Effective result: `10`. Dependent `Наявність!G2`: `12`.

2. `Номенклатура!C9:F9` for `FIG-123-500`:

   - C: `Не вказано` -> blank;
   - D: `Функціональний аксесуар` -> `Фігурка`;
   - E: `Не вказано` -> blank;
   - F: `Не вказано` -> blank.

   Unknown optional values were left blank instead of being guessed.

3. The strict D2:D992 validator now accepts exactly:

   - `Брелок`;
   - `Фігурка`;
   - `Функціональний аксесуар`;
   - `Інше`.

Post-write reads confirmed the restored formula, results 10 and 12, the cleaned row, and the four-value strict validator. Existing `Брелок` rows now conform to the validator.

The total availability tile changed from 211 to 122 because the false Ditto printed count of 99 was removed. This is an intentional correction, not lost print-log data.

## Local source changes

- `public/draft-categories.js`: central 17-category definition and explicit mapping to the four broad Nomenclature types.
- `public/app.js`: renders the 17 detailed options but sends the mapped broad type to `/api/draft`; unknown optional C/E/F values remain omitted.
- `public/index.html`: labels the selector `Механіка / категорія`.
- Tests cover the exact 17-label API contract, all category-to-type mappings, rejection of unknown placeholders, and the updated UI label/fixture route.

Mapping policy:

- the four BR mechanics -> `Брелок`;
- the six FIG mechanics -> `Фігурка`;
- the seven ACC mechanics -> `Функціональний аксесуар`.

## Verification

- `node --check public/app.js`: pass.
- `node --check public/draft-categories.js`: pass.
- `npm test`: 15 passed, 0 failed.
- Scoped `git diff --check`: pass.
- Local server: HTTP 200 on port 3107; process observed as PID 21452 at verification time.
- Extracted-package browser QA: live data loaded, stock tile showed 122, the draft form showed `Механіка / категорія` with all 17 options, and the browser console had no errors or warnings. No live draft was submitted during QA.
- Protected-source hashes remained unchanged during this follow-up:
  - Apps Script 3D-P API: `FDF22BFB2B6C659F6E727BE85447049826CB5355278C4ADC61CB417C633BEDF9`;
  - dashboard: `6C28F3C6848EC0B5004A4F715D5F92EB01E5596551ECF147F1B60347BACB90A1`;
  - CRM Apps Script: `37B0F62A07A0EE8214DE278D324A39E10814F8C120FC9F8CE21C74B9AD46C08F`.

The repository's 3D-P Apps Script mirror reports an older source snapshot, so no Apps Script source change was inferred or made in this round.

## Rebuilt package

- Archive: `3d-print/serhiy-local-server/dist/Booster-3DP-Serhiy_Node-v24.19.0_20260901.zip`
- Size: 35,825,820 bytes
- SHA-256: `2D4954BDBC70F2E8FAADF1A5F5F2C3D835D5C8793F9F55CEF2AC1261CD1180AE`
- Extracted test root: `C:\Users\14bez\AppData\Local\Temp\Booster3DP-ValidationFix-20260901-7534e1e4341f4610bcff2ff03a1b8292\Booster-3DP-Serhiy_Node-v24.19.0_20260901`
- Launch path tested: extracted `Запустити.bat`.

## Rollback and boundaries

- Google Sheets version history is the safest rollback for the three bounded workbook edits. Restoring the old literal 99 or the old three-value D validator would intentionally reintroduce the diagnosed defects.
- No Apps Script, dashboard, or CRM source was edited.
- No deployment, commit, push, Notion update, or CRM write was performed.
