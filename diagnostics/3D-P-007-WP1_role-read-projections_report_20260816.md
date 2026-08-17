# Codex Report — 3D-P-007 WP1 rev 2: role-read projections

Date: 2026-08-16

## Outcome

WP1 rev 2 is ready locally. `SERHIY_FULL_ECONOMICS_VISIBLE_3DP` now defaults
to `true`: Serhiy receives the shared 3D economics, including shop costs and
margins. The read-projection machinery is still active and header-resolved, so
external order/customer links remain absent rather than being blanked or
trusted to a client-side filter.

No Apps Script version was published, no live Sheet cell was written, and no
commit or push was made.

## Governing revision and scope

This reworks the unpublished WP1 rev 1 local implementation against the
governing `⚠ REVISION 2026-08-16 (rev 2)` handoff block. It is not a migration.

- Changed locally: 3D-P Apps Script mirror, its role-read contract test, the
  paste-ready patch copy, and this report.
- Did not change the main CRM script, dashboard, Serhiy local server, any
  live Sheet, or any production deployment.
- The live column mapping was not re-read. The already-verified header
  evidence was reused to revise only the read boundary.

## Read boundary after rev 2

`SERHIY_READ_PROJECTION_3DP` is an explicit per-sheet header-name allowlist.
It is separated into the prior baseline and an economics subset controlled by
the single `SERHIY_FULL_ECONOMICS_VISIBLE_3DP` switch. At its delivered default
of `true`, Serhiy sees all current 3D-line headers except the fields below.
The projection is applied on every approved Serhiy read path; an unlisted read
action fails closed with `READ_PROJECTION_FORBIDDEN`.

| Sheet | Delivered Serhiy view | Not returned |
|---|---|---|
| `Продажі` | All current 3D economics, including `Витрати BoosterShop за од., грн`, `Вартість фурнітури за од., грн (заморожена)`, and `Фурнітура власника за од., грн (заморожена)` | `№ замовлення` (live `N`), `Примітки` (live `O`), `CRM row number` (live `T`) |
| `Маркетингові_плюшки` | `Дата`, `SKU`, the purchase quantity/cost fields, and `Видано як бонус, шт` | `До замовлення №` (live `G`), `Примітки` (live `H`) |
| `Аналітика` | `Витрати BoosterShop (фурнітура), грн`, `Маржа BoosterShop, грн`, and `Маржа BoosterShop, %` are now included with the existing 3D metrics | `РРЦ рекомендована` only, because the live field is an unimplemented placeholder rather than a secret |

`3dp_get_range` rejects a range touching any withheld `Продажі` column,
either withheld `Маркетингові_плюшки` column, or `Аналітика!РРЦ рекомендована`.
It never trims such a request silently. Projected bootstrap matrices retain
the live source-column order after excluded headers are removed.

The existing, unchanged safeguards remain in force:

- `Налаштування!B2:B5` is the only Serhiy settings read/write grant; bounds
  and Ukrainian decimal-comma normalization are unchanged.
- Every accepted settings write appends Kyiv time, role, parameter, old value,
  and new value to hidden `_Журнал_налаштувань_3DP`. Serhiy only reads his own
  journal rows; the owner reads all rows.
- `Номенклатура!J` material-price changes append to the existing per-SKU
  history trail in `Номенклатура!P`.
- Serhiy batch drafts stay namespaced and private. The raw stock-adjustment
  ledger remains an unprojected, owner-only action.

## Source and compatibility evidence

- Repository baseline: `749a922` on `master`.
- Owner-supplied 3D-P V23 export: 2026-08-13 20:17 Europe/Kyiv. Its normalized
  source match is recorded in `3d-print/apps-script-3dp-api/SOURCE_STATE.md`.
- The test compares 13 owner read responses from the updated source with V23
  against the same local fake workbook. Every compared owner response is
  byte-identical.

## Local verification

```text
node 3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs "Версія 23, 13 серп. 2026 р., 2017.txt"
{"ok":true,"owner_paths_preserved":9,"v23_owner_responses_compared":13,"serhiy_projection_checks":34,"settings_journal_checks":8,"full_economics_checks":7}

new Function(Code.gs)
Code.gs syntax ok

git diff --check
no output
```

The role-read test proves the delivered default exposes `Продажі!G/V/Y` and all
three newly-opened analytics metrics; blocks `Продажі!N/T` and
`Продажі!O`, `Маркетингові_плюшки!G/H`; verifies direct-range rejection, placeholder RRP
exclusion, settings journaling, material-price history, private drafts, and
the restrictive fallback when the switch is set to `false`.

This is local contract evidence only, not publication or production proof.

## Deliverable and remaining gate

Patch file: `patches/3D-P-007-WP1_role-read-projections_20260816.js`

It is a byte-identical copy of the updated local `Code.gs`.
SHA-256: `C5EB5035E6DCF384C7AA631E5BF2706F10E332FDDA47F91161EB51E74BDAF976`.
If the owner elects to deploy,
they must paste this file into the **3D-P bound Apps Script project only**,
save it, create a new version of the existing Web App, and run the handoff QA.
Do not publish the main CRM script and do not publish rev 1.

Owner publication and production QA are the only remaining gates.

## Addendum — pre-deployment review fixes

- The role-read harness now locates exactly one repository-root V23 export
  matching `Версія 23*.txt` and fails if the file is absent or ambiguous.
  `node --test` therefore cannot report a green result with zero owner
  comparisons; its required result is now `v23_owner_responses_compared: 13`.
- `Продажі!Примітки` is removed from Serhiy's projection. It is rejected for a
  direct range request and asserted absent with both values of
  `SERHIY_FULL_ECONOMICS_VISIBLE_3DP`.
