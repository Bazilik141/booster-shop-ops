/**
 * Owner-run read-only post-deployment reconciliation for the live 3D-P workbook.
 * Delete this file from the bound Apps Script project after recording the result.
 */
function catalogFifo3dpLiveReconcile() {
  const result = fifo3dpReconcileAction_(getSpreadsheet3dp_(), {
    role: 'owner',
    identity: 'fifo-live-postdeploy-check',
  });
  console.log('CATALOG_FIFO_3DP_LIVE ' + JSON.stringify(result));
  return result;
}
