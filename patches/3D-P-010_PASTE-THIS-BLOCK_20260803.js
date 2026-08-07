// BEGIN 3D-P-010 helper block — insert before apiAddSale_ in main CRM Code.gs
const CRM_3DP_SYNC_URL_PROPERTY_ = 'BOOSTER_3DP_URL';
const CRM_3DP_SYNC_TOKEN_PROPERTY_ = 'BOOSTER_3DP_SYNC_TOKEN';
const CRM_3DP_SALES_SHEET_ = 'Продажі';
const CRM_3DP_ORDER_HEADER_ = '№ замовлення';
const CRM_3DP_CRM_ROW_HEADER_ = 'CRM row number';
const CRM_3DP_EXPENSE_HEADER_ = 'Витрати BoosterShop за од., грн';
const CRM_3DP_STOCK_HEADER_ = 'Наявно зараз, шт';

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

function crm3dpDate_(value) {
  if (value instanceof Date && !isNaN(value.getTime())) return value.toISOString().slice(0, 10);
  return String(value || '').trim();
}

function crm3dpLogSkip_(orderId, reason) {
  Logger.log('3D-P sale sync skipped for ' + String(orderId || '') + ': ' + String(reason || 'unknown'));
}

function crm3dpLogWarning_(orderId, reason) {
  Logger.log('3D-P sale sync WARNING for ' + String(orderId || '') + ': ' + String(reason || 'unknown'));
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

function crm3dpGet_(config, params) {
  const query = Object.keys(params).map(function (key) {
    return encodeURIComponent(key) + '=' + encodeURIComponent(String(params[key]));
  }).join('&');
  return crm3dpFetchJson_(config.url + '?' + query + '&token=' + encodeURIComponent(config.token), {
    method: 'get',
    headers: { Accept: 'application/json' },
  });
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

function crm3dpSaleRows_(config) {
  const schema = crm3dpGet_(config, { action: '3dp_get_range', sheet: CRM_3DP_SALES_SHEET_, range: 'T1:T1' });
  if (!schema.values || !schema.values[0] || String(schema.values[0][0] || '').trim() !== CRM_3DP_CRM_ROW_HEADER_) {
    throw new Error('3D-P Продажі!T schema is not ready');
  }
  const list = crm3dpGet_(config, { action: '3dp_sales' });
  return Array.isArray(list.rows) ? list.rows : [];
}

function crm3dpSaleMatches_(rows, order, crmRow) {
  return (rows || []).filter(function (row) {
    return String(row[CRM_3DP_ORDER_HEADER_] || '').trim() === order &&
      Math.floor(crm3dpNumber_(row[CRM_3DP_CRM_ROW_HEADER_])) === crmRow;
  }).sort(function (left, right) {
    return crm3dpNumber_(left.row_number) - crm3dpNumber_(right.row_number);
  });
}

function crm3dpSaleAppendValues_(entry, order) {
  const values = entry.values || [];
  const quantity = crm3dpNumber_(values[7]);
  const linePrice = crm3dpNumber_(values[8]);
  const lineDiscount = crm3dpNumber_(values[9]);
  return {
    A: crm3dpDate_(values[2]),
    B: String(values[5] || '').trim(),
    D: quantity,
    E: crm3dpRound2_(quantity ? linePrice - lineDiscount / quantity : linePrice),
    G: 0,
    M: String(values[1] || '').trim(),
    N: order,
    T: entry.row,
  };
}

function crm3dpEnsureStock_(config, order, entry) {
  const sku = String(entry.values[5] || '').trim();
  const quantity = crm3dpNumber_(entry.values[7]);
  if (!sku || quantity <= 0 || !Number.isInteger(quantity)) {
    crm3dpLogSkip_(order, 'invalid whole stock quantity for CRM row ' + entry.row);
    return { ok: false, skipped: 'invalid_stock_quantity' };
  }
  const reason = 'auto: CRM order ' + order + ' row ' + entry.row;
  const ledger = crm3dpGet_(config, { action: '3dp_stock_adjustments', sku: sku, reason: reason, limit: 1 });
  if ((ledger.rows || []).some(function (row) { return String(row['Причина'] || '').indexOf(reason) === 0; })) {
    return { ok: true, already_applied: true };
  }
  const stock = crm3dpGet_(config, { action: '3dp_get_row', sheet: 'Наявність', sku: sku });
  const expected = stock && stock.row ? stock.row[CRM_3DP_STOCK_HEADER_] : null;
  const adjustment = crm3dpFetchJson_(config.url, {
    method: 'post',
    contentType: 'text/plain;charset=utf-8',
    payload: JSON.stringify({
      action: '3dp_adjust_stock',
      token: config.token,
      sku: sku,
      expected_current: expected,
      delta: -quantity,
      reason: reason,
    }),
  });
  if (adjustment.warning || crm3dpNumber_(adjustment.new_value) < 0) {
    crm3dpLogWarning_(order, 'insufficient stock for ' + sku + ' row ' + entry.row + '; new=' + String(adjustment.new_value));
  }
  return adjustment;
}

function sync3dpSales_(sales, orderId, rowNumbers) {
  const order = String(orderId || '').trim();
  if (!order) return { ok: false, skipped: 'missing_order_id' };
  try {
    const crmRows = crm3dpOrderRows_(sales, rowNumbers);
    const triggerRows = crmRows.filter(function (entry) { return is3dpPackagingSku_(entry.values[5]); });
    if (!triggerRows.length) return { ok: true, skipped: 'no_3dp_sku' };
    const packagingTotal = crm3dpRound2_(crmRows.reduce(function (sum, entry) {
      return sum + crm3dpNumber_(entry.values[15]);
    }, 0));
    const config = crm3dpConfig_();
    if (!config) {
      crm3dpLogSkip_(order, '3D-P sync properties are not configured');
      return { ok: false, skipped: 'not_configured' };
    }

    const existingRows = crm3dpSaleRows_(config);
    const created = [];
    const matches = [];
    triggerRows.forEach(function (entry) {
      const found = crm3dpSaleMatches_(existingRows, order, entry.row);
      if (found.length > 1) crm3dpLogWarning_(order, 'duplicate 3D-P key for CRM row ' + entry.row);
      if (found.length) {
        matches.push({ entry: entry, row: found[0] });
        return;
      }
      const appended = crm3dpFetchJson_(config.url, {
        method: 'post',
        contentType: 'text/plain;charset=utf-8',
        payload: JSON.stringify({
          action: '3dp_append_row',
          token: config.token,
          sheet: CRM_3DP_SALES_SHEET_,
          values: crm3dpSaleAppendValues_(entry, order),
        }),
      });
      const createdSale = { row_number: appended.row };
      createdSale[CRM_3DP_ORDER_HEADER_] = order;
      createdSale[CRM_3DP_CRM_ROW_HEADER_] = entry.row;
      createdSale[CRM_3DP_EXPENSE_HEADER_] = 0;
      existingRows.push(createdSale);
      matches.push({ entry: entry, row: createdSale });
      created.push(createdSale);
      crm3dpEnsureStock_(config, order, entry);
    });

    const first = matches.slice().sort(function (left, right) {
      return crm3dpNumber_(left.row.row_number) - crm3dpNumber_(right.row.row_number);
    })[0];
    const currentExpense = first.row[CRM_3DP_EXPENSE_HEADER_];
    if (Math.abs(crm3dpNumber_(currentExpense) - packagingTotal) >= 0.005) {
      crm3dpFetchJson_(config.url, {
        method: 'post',
        contentType: 'text/plain;charset=utf-8',
        payload: JSON.stringify({
          action: '3dp_write',
          token: config.token,
          sheet: CRM_3DP_SALES_SHEET_,
          sku_or_row: first.row.row_number,
          column: 'G',
          value: packagingTotal,
          expected_current: currentExpense,
        }),
      });
    }
    matches.forEach(function (match) {
      if (!created.some(function (row) { return row.row_number === match.row.row_number; })) {
        crm3dpEnsureStock_(config, order, match.entry);
      }
    });
    return { ok: true, order: order, created: created.length, matched: matches.length, packaging: packagingTotal };
  } catch (error) {
    crm3dpLogSkip_(order, String(error && error.message ? error.message : error));
    return { ok: false, skipped: '3dp_unavailable' };
  }
}

// Compatibility name retained for the V87 helper callers/tests.
function sync3dpPackagingCost_(sales, orderId, rowNumbers) {
  return sync3dpSales_(sales, orderId, rowNumbers);
}
// END 3D-P-010 helper block
