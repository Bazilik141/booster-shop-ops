import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync('patches/CRM-RRP_site-price-reconcile_20260828.php', 'utf8');

function decide({ price, crm, rows, named = null }) {
  let disable = null;
  const skips = [];
  if (named) {
    const match = rows.find((row) => row.id === named.id);
    if (!match) skips.push(`special_already_disabled:${named.sku}`);
    else if (Math.abs(match.price - named.price) >= 0.005) skips.push(`${named.sku}:named_special_drift`);
    else disable = match;
  } else if (rows.some((row) => row.special === 1 && row.quantity === 1 && crm <= row.price + 0.005)) {
    skips.push('SKU:inverted_special_guard');
    return { updates: [], skips };
  }
  return { updates: Math.abs(price - crm) >= 0.005 || disable ? [{ disable }] : [], skips };
}

assert.deepEqual(
  decide({ price: 400, crm: 300, rows: [{ id: 1, quantity: 1, special: 1, price: 300 }] }),
  { updates: [], skips: ['SKU:inverted_special_guard'] },
);
assert.equal(
  decide({ price: 400, crm: 300, rows: [{ id: 2, quantity: 5, special: 1, price: 300 }] }).updates.length,
  1,
);
assert.deepEqual(
  decide({ price: 400, crm: 300, rows: [], named: { id: 3, sku: 'NAMED', price: 300 } }),
  { updates: [{ disable: null }], skips: ['special_already_disabled:NAMED'] },
);
assert.match(source, /product_discount_id,quantity,special,price,date_start,date_end/);
assert.match(source, /\(int\)\$candidate\['special'\]===1&&\(int\)\$candidate\['quantity'\]===1/);
assert.match(source, /if\(\$guard\).*continue;/);
assert.match(source, /price_update_limit_exceeded/);
assert.doesNotMatch(source, /beforeDiscounts/);

console.log('round2_contract=ok');
