/*
 * 3D-P-010 — main CRM V86 packaging → 3D-P sales pull
 * Source anchor: Booster Shop CRM — Apps Script код 29.07.2026.csv
 * Confirmed owner baseline: CRM Auto V86, deployed 2026-07-29 13:07.
 *
 * This is a source patch for the main CRM Code.gs, not a standalone script.
 * Add the helper block once, then make the three exact V86 edits below.
 *
 * Required main-CRM Script Properties (set only by the owner after review):
 *   BOOSTER_3DP_URL        deployed 3D-P Apps Script URL ending in /exec
 *   BOOSTER_3DP_SYNC_TOKEN owner-role 3D-P token; never log or expose it
 *
 * Failure model: all 3D-P network/auth/matching failures return a skip and
 * are logged without a token. apiAddSale_/apiUpdateSale_ continue normally.
 */

// BEGIN 3D-P-010 helper block — insert before apiAddSale_ in main CRM Code.gs
const CRM_3DP_SYNC_URL_PROPERTY_ = 'BOOSTER_3DP_URL';
const CRM_3DP_SYNC_TOKEN_PROPERTY_ = 'BOOSTER_3DP_SYNC_TOKEN';
const CRM_3DP_SALES_SHEET_ = 'Продажі';
const CRM_3DP_ORDER_HEADER_ = '№ замовлення';
const CRM_3DP_EXPENSE_HEADER_ = 'Витрати BoosterShop за од., грн';

function is3dpPackagingSku_(value) {
  const sku = String(value || '').trim().toUpperCase();
  return /^(?:BR-[A-Z0-9][A-Z0-9-]*|FIG-[A-Z0-9][A-Z0-9-]*|ACC-3D-\d{3}(?:-[A-Z0-9]+)*)$/.test(sku);
}

function crm3dpNumber_(value) {
  const parsed = Number(String(value == null ? '' : value).replace(',', '.').replace(/[^0-9.-]/g, ''));
  return Number.isFinite(parsed) ? parsed : 0;
}

function crm3dpRound2_(value) {
  return Math.round((crm3dpNumber_(value) + Number.EPSILON) * 100) / 100;
}

function crm3dpLogSkip_(orderId, reason) {
  Logger.log('3D-P packaging sync skipped for ' + String(orderId || '') + ': ' + String(reason || 'unknown'));
}

function crm3dpConfig_() {
  const properties = PropertiesService.getScriptProperties();
  const url = String(properties.getProperty(CRM_3DP_SYNC_URL_PROPERTY_) || '').trim();
  const token = String(properties.getProperty(CRM_3DP_SYNC_TOKEN_PROPERTY_) || '');
  if (!url || !/\/exec(?:\?|$)/.test(url)) return null;
  if (!token) return null;
  return { url: url, token: token };
}

function crm3dpFetchJson_(url, options) {
  const response = UrlFetchApp.fetch(url, Object.assign({ muteHttpExceptions: true }, options || {}));
  const status = response.getResponseCode();
  let payload = null;
  try {
    payload = JSON.parse(response.getContentText() || '{}');
  } catch (error) {
    throw new Error('3D-P returned invalid JSON (' + status + ')');
  }
  if (status < 200 || status >= 300 || !payload || payload.ok !== true) {
    throw new Error('3D-P request failed (' + status + '): ' + String((payload && (payload.code || payload.error)) || 'unknown'));
  }
  return payload;
}

function crm3dpOrderRows_(sales, rowNumbers) {
  const unique = {};
  return (rowNumbers || []).map(function (value) { return Math.floor(crm3dpNumber_(value)); })
    .filter(function (row) { return row >= 3 && !unique[row] && (unique[row] = true); })
    .sort(function (left, right) { return left - right; })
    .map(function (row) {
      return { row: row, values: sales.getRange(row, 1, 1, 16).getValues()[0] };
    });
}

function sync3dpPackagingCost_(sales, orderId, rowNumbers) {
  const order = String(orderId || '').trim();
  if (!order) return { ok: false, skipped: 'missing_order_id' };
  try {
    const crmRows = crm3dpOrderRows_(sales, rowNumbers);
    if (!crmRows.length) {
      crm3dpLogSkip_(order, 'no main-CRM rows');
      return { ok: false, skipped: 'no_crm_rows' };
    }
    if (!crmRows.some(function (entry) { return is3dpPackagingSku_(entry.values[5]); })) {
      return { ok: true, skipped: 'no_3dp_sku' };
    }

    const packagingTotal = crm3dpRound2_(crmRows.reduce(function (sum, entry) {
      return sum + crm3dpNumber_(entry.values[15]); // main CRM Продажі!P
    }, 0));
    const config = crm3dpConfig_();
    if (!config) {
      crm3dpLogSkip_(order, '3D-P sync properties are not configured');
      return { ok: false, skipped: 'not_configured' };
    }

    const joiner = config.url.indexOf('?') === -1 ? '?' : '&';
    const list = crm3dpFetchJson_(config.url + joiner + 'action=3dp_sales&token=' + encodeURIComponent(config.token), {
      method: 'get',
      headers: { Accept: 'application/json' },
    });
    const target = (list.rows || []).filter(function (row) {
      return String(row[CRM_3DP_ORDER_HEADER_] || '').trim() === order;
    }).sort(function (left, right) {
      return crm3dpNumber_(left.row_number) - crm3dpNumber_(right.row_number);
    })[0];
    if (!target || !target.row_number) {
      crm3dpLogSkip_(order, 'no matching 3D-P sales row');
      return { ok: false, skipped: 'no_3dp_row' };
    }

    const expected = target[CRM_3DP_EXPENSE_HEADER_];
    if (Math.abs(crm3dpNumber_(expected) - packagingTotal) < 0.005) {
      return { ok: true, skipped: 'already_current', row: target.row_number, value: packagingTotal };
    }
    const written = crm3dpFetchJson_(config.url, {
      method: 'post',
      contentType: 'text/plain;charset=utf-8',
      payload: JSON.stringify({
        action: '3dp_write',
        token: config.token,
        sheet: CRM_3DP_SALES_SHEET_,
        sku_or_row: target.row_number,
        column: 'G',
        value: packagingTotal,
        expected_current: expected,
      }),
    });
    return { ok: true, row: target.row_number, value: packagingTotal, result: written };
  } catch (error) {
    crm3dpLogSkip_(order, String(error && error.message ? error.message : error));
    return { ok: false, skipped: '3dp_unavailable' };
  }
}
// END 3D-P-010 helper block

/*
 * V86 edit 1 — apiAddSale_:
 *
 * A. After:
 *   const costRunState = {};
 * add:
 *   const addedRows = [];
 *
 * B. Immediately after:
 *   const row = firstRow + index;
 * add:
 *   addedRows.push(row);
 *
 * C. Immediately before:
 *   updateSkuCurrentCost_(ss);
 * add:
 *   sync3dpPackagingCost_(sales, operation, addedRows);
 *
 * V86 edit 2 — apiUpdateSale_:
 * Immediately before:
 *   invalidateDoGetCache_();
 * add:
 *   sync3dpPackagingCost_(sales, order, rows);
 *
 * Do not modify doPost, main CRM sheet values, packaging calculation,
 * getPackagingCost_(), or any storefront code. Fixture is deliberately absent.
 */