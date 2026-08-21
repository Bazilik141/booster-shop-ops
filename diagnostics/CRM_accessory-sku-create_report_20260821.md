# Codex Report — CRM accessory SKU creation

Date: 2026-08-21

## Scope

Owner-requested local fix for the main-CRM “New SKU” dashboard form. The previous
TCG-only requirement for language and set prevented creation of accessories such
as card albums. No live Sheet, Apps Script deployment, dashboard publication,
Notion property, or roadmap status was changed.

## Root cause

Both the dashboard and `apiAddSku_` required `brand`, `language`, `set`, and
`format`. The default short-name formula also depends on every one of those
fields. Removing the required marks in the UI alone would therefore still fail
at the API boundary and would not make a usable short name.

## Files touched

```text
dashboard/booster-dashboard.html
dashboard/tests/dashboard-contract.test.mjs
crm/apps-script/Code.gs
crm/apps-script/tests/catalog-sku-create.test.mjs
```

## Local change

- The dashboard now has `TCG товар` (unchanged default) and `Аксесуар` modes.
- Accessory mode requires SKU, full name, brand, format, and RRP. Language and
  line/series are optional; booster-specific fields are hidden.
- `catalog_kind=accessory` is an explicit API contract. It permits blank
  language/set only in that mode; all existing callers still default to the
  unchanged TCG requirement.
- Blank fields are not inserted into CRM option lists. The accessory short-name
  cell remains a formula which returns the full product name.
- `Товари!E:F` stay genuinely blank for an accessory with no applicable values;
  no `N/A` or other synthetic catalogue value is stored.

## Local verification

```text
20/20 CRM Apps Script tests passed
2/2 dashboard tests passed
git diff --check passed
```

The new API regression creates `ACC-ALBUM-001` with blank language/set,
asserts the full-name short-name formula and confirms that only real brand and
format values are added to option lists. It also confirms that the normal TCG
path still rejects blank language/set.

## Live deployment and owner QA required

1. In the dashboard, run `Перевірити CRM` and record the bounded result.
2. Do **not** paste the whole repository `crm/apps-script/Code.gs`: its mirror
   also carries an unrelated unpublished CRM-012 follow-up. Starting from the
   current bound-script source, apply only this change's four `apiAddSku_`
   hunks from the reviewed diff, save, and publish a new Web App version. If an
   anchor does not match, stop and export the current bound source rather than
   guessing. The repo mirror is not a deployment target.
3. Refresh the local dashboard file, choose `Товари` → `Додати SKU` →
   `Аксесуар`, and create one approved test/real accessory only.
4. Confirm `Товари` has blank `Мова` and `Сет`, the short name equals the full
   name, `РРЦ` is populated, and the SKU appears once in `Майстер_Товарів`.
5. Run `Перевірити CRM` again. Any new problem code is a defect of this change.

## Risks and rollback

Risk is limited to the main CRM catalogue creation path. Existing TCG and 3D
payloads retain their prior validation. If owner QA fails, restore the prior
Apps Script editor version and replace the dashboard file with the prior Git
revision; no migration or existing-row cleanup is needed because this change
does not alter the workbook schema.

## Visual QA caveat

The local `file://` dashboard was blocked by the available browser security
policy, so three breakpoint screenshots could not be captured here. The change
uses the existing form grid and only conditionally hides two booster-only
fields; owner QA should check desktop, narrow mobile, and focus/selection state
when selecting `Аксесуар`.
