/**
 * Owner-run read-only CRM smoke check for the deployed 3D-P FIFO endpoint.
 * Delete this file from the bound CRM Apps Script project after recording the result.
 */
function catalogFifoCrmRemoteLiveCheck() {
  const config = crm3dpConfig_();
  if (!config) throw new Error('CRM_3DP_CONFIG_MISSING');
  const result = crm3dpGet_(config, { action: '3dp_fifo_reconcile' });
  console.log('CATALOG_FIFO_CRM_REMOTE_LIVE ' + JSON.stringify(result));
  return result;
}
