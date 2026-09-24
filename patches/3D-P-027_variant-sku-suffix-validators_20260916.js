/*
 * 3D-P-027 — accept revision 9 variant suffixes in the 3D-P SKU validator
 *
 * Paste guide for the bound 3D-P Apps Script project. This is a narrowly
 * scoped source patch, not a runnable script and not a PHP runner.
 *
 * Before changing source: create a named Apps Script version for rollback.
 * In Code.gs, confirm each OLD anchor below occurs exactly once, then replace
 * it with its NEW block. Save, publish a new Web App version, run the owner
 * QA in handoff_3D-P-027_variant-sku-suffix-validators_20260916.md §8, then
 * export the deployed source back to the repository and update SOURCE_STATE.md.
 *
 * This does not change main CRM Code.gs, existing SKU values, sheets, tokens,
 * prefix-to-type coupling, or edit/archive paths.
 */

// 1. Replace the existing pattern declaration with this exact pair of lines.
// OLD: const NOMENCLATURE_SKU_PATTERN_3DP = /^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$/;
// NEW:
// Keep in sync with threeDpSkuTypeError in dashboard/booster-dashboard.html; source: 3D-P SKU convention rev. 9.
const NOMENCLATURE_SKU_PATTERN_3DP = /^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}(?:-[A-Z0-9]{1,5})*$/;

// 2. In canonicalNomenclatureSku3dp_, replace only its INVALID_SKU message.
// OLD:
// throw apiError3dp_('INVALID_SKU', 'SKU must match BR|FIG|ACC-3D + mnemonic (2–5 A-Z/0-9) + three digits.');
// NEW:
throw apiError3dp_('INVALID_SKU', 'SKU must match BR|FIG|ACC-3D + mnemonic (2–5 A-Z/0-9) + three digits, optionally followed by -TOKEN segments (1–5 A-Z/0-9; e.g. ACC-3D-ONIX-110-21-BLK).');

/*
 * Expected validator results:
 * accept: ACC-3D-PKM-130, ACC-3D-DITTO-410, BR-CHARM-100, FIG-CHARM-001,
 *         ACC-3D-ONIX-110-21, ACC-3D-ONIX-110-21-BLK, FIG-ONIX-500-15-WHT
 * reject: ACC-3D-410, ACC-3D-ONIX-110-, ACC-3D-ONIX-110-blk,
 *         ACC-3D-ONIX-110-TOOLONG, ACC-001, PKM-JP-EXSD-STD-GRS
 *
 * Rollback: restore the two OLD anchors and publish a new version. No sheet
 * data is written by this change.
 */
