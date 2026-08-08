/*
 * 3D-P-022 — CRM SKU trigger / canonical convention alignment
 *
 * Target: the main CRM Apps Script Code.gs after the 3D-P-014 rev 2 block.
 * This is a paste patch, not a standalone executable. It does not change the
 * 3D-P workbook, existing SKU strings, Script Properties, or any token.
 *
 * Preflight in the live CRM project:
 * 1. Create a named Apps Script version for rollback.
 * 2. Confirm every OLD anchor below occurs exactly once.
 * 3. Apply all three replacements as one change, then publish a new Web App version.
 * 4. Export the deployed Code.gs back to crm/apps-script/Code.gs.
 */

// 1. Replace the existing is3dpPackagingSku_ function exactly with this block.
function is3dpPackagingSku_(value) {
  const sku = String(value || '').trim().toUpperCase();
  return /^(?:BR|FIG|ACC-3D)-[A-Z0-9][A-Z0-9-]*$/.test(sku);
}

function has3dpPackagingSkuPrefix_(value) {
  return /^(?:BR|FIG|ACC-3D)-/.test(String(value || '').trim().toUpperCase());
}

// 2. In crm3dpJournalDetail_ -> details, add this entry next to skipped_no_3dp_sku.
//    skipped_sku_shape: 'A 3D-P SKU has a recognized prefix but an invalid shape.',

// 3. In sync3dpSales_, replace the OLD three-line trigger/no-SKU block with this block.
const crmRows = crm3dpOrderRows_(sales, rowNumbers);
triggerRows = crmRows.filter(function (entry) { return is3dpPackagingSku_(entry.values[5]); });
const malformedSkuEntries = crmRows.filter(function (entry) {
  return has3dpPackagingSkuPrefix_(entry.values[5]) && !is3dpPackagingSku_(entry.values[5]);
});
malformedSkuEntries.forEach(function (entry) {
  const malformedSku = String(entry.values[5] || '').trim();
  crm3dpLogSkip_(sales, journalSource, order, entry, 'skipped_sku_shape',
    '3D-P SKU has an invalid shape: ' + malformedSku);
});
if (!triggerRows.length) {
  if (malformedSkuEntries.length) return { ok: true, skipped: 'sku_shape' };
  crm3dpLogSkip_(sales, journalSource, order, null, 'skipped_no_3dp_sku', 'no 3D-P SKU in CRM order');
  return { ok: true, skipped: 'no_3dp_sku' };
}

/*
 * Expected behaviour:
 * - ACC-3D-DITTO-410, ACC-3D-PKM-130, ACC-3D-410, FIG-CHARM-001 and
 *   BR-CHARM-100 trigger sync.
 * - ACC-3D- does not trigger; its CRM journal row has outcome
 *   skipped_sku_shape and names the SKU in sanitized detail.
 * - ACC-001 and MBX-STD-001 remain non-3D and do not trigger.
 *
 * Rollback: restore the prior predicate, remove has3dpPackagingSkuPrefix_,
 * remove the outcome-map entry, and restore the prior no-trigger block;
 * then publish a new Web App version. No Sheet data is migrated by this patch.
 */
