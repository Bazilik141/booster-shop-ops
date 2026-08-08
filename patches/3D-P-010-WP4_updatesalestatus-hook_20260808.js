/*
 * 3D-P-010 WP4 — hook the in-Sheet updateSaleStatus() write path
 *
 * Target: main CRM Apps Script Code.gs, V92 baseline (2026-08-08).
 * This is a paste patch, not a standalone executable. It changes no schema,
 * token, API helper, stock calculation, sale matching, or 3D-P workbook code.
 *
 * Preflight in the live CRM project:
 * 1. Create a named Apps Script version for rollback.
 * 2. Search the exact OLD anchor below. It must occur exactly once.
 * 3. Replace it exactly once, then publish a new Web App version.
 * 4. Export the deployed Code.gs back to crm/apps-script/Code.gs.
 */

// OLD anchor in updateSaleStatus(), after the rows.forEach(...) loop:
// invalidateDoGetCache_(); clearSaleUpdateForm();

// NEW line — paste in its place:
invalidateDoGetCache_(); sync3dpPackagingCost_(sales, order, rows, 'updateSaleStatus'); clearSaleUpdateForm();

/*
 * Placement contract:
 * - all sales writes and fixSaleCostForRow_ have completed;
 * - cache invalidation completes before the 3D-P call, whose source is
 *   recorded in the 3D-P-014 journal as updateSaleStatus;
 * - the form clears and one existing success alert appears only after the
 *   fail-open wrapper returns;
 * - deliberately no extra try/catch and no change to the alert text.
 *
 * Rollback: restore the OLD anchor above and publish a new Web App version.
 * Data already created by the existing wrapper remains valid; never delete a
 * 3D-P row directly — archive/counter-adjust through the approved 3D-P API.
 */
