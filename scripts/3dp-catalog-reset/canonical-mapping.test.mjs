import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const handoff = fs.readFileSync(path.join(root, 'handoffs', 'handoff_3DP-CATALOG-CANONICAL-DECISIONS_claude-to-codex_20260905.md'), 'utf8');
const match = handoff.match(/```json\s*(\[.*?\])\s*```/s);
assert.ok(match, 'canonical JSON mapping is present');
const mapping = JSON.parse(match[1]);
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'plans', '3dp-catalog-reset-20260902', 'import-manifest.json'), 'utf8'));
const payload = JSON.parse(fs.readFileSync(path.join(root, 'plans', '3dp-catalog-reset-20260902', 'migration-payload.json'), 'utf8'));
const key = row => `${row.source_tab ?? row.source.sheet}\u0001${row.source_row ?? row.source.row}`;

assert.equal(mapping.length, 72);
assert.equal(new Set(mapping.map(key)).size, 72);
assert.deepEqual(new Set(mapping.map(key)), new Set(manifest.records.map(key)));
assert.equal(new Set(mapping.map(row => row.canonical_sku)).size, 72);
assert.equal(mapping.filter(row => row.active).length, 62);
assert.equal(mapping.filter(row => !row.active).length, 10);
assert.equal(mapping.filter(row => row.current_crm_sku !== row.canonical_sku).length, 3);
assert.equal(mapping.filter(row => row.current_crm_name !== row.canonical_name).length, 39);
const manifestByKey = new Map(manifest.records.map(row => [key(row), row]));
assert.equal(mapping.filter(row => Boolean(manifestByKey.get(key(row)).active) !== Boolean(row.active)).length, 3);
assert.equal(payload.records.length, 72);
assert.equal(payload.policy.active_count, 62);
assert.equal(payload.policy.inactive_count, 10);
assert.deepEqual(payload.records.map(row => row.sku), mapping.map(row => row.canonical_sku));
assert.deepEqual(payload.records.map(row => row.name), mapping.map(row => row.canonical_name));
assert.deepEqual(payload.records.map(row => row.active), mapping.map(row => row.active));
assert.ok(payload.records.some(row => row.sku === 'FIG-HNTR-200'));
assert.ok(payload.records.some(row => row.sku === 'FIG-HNTR-210'));
assert.ok(payload.records.some(row => row.sku === 'FIG-OP-410'));
assert.ok(!payload.records.some(row => ['FIG-HAUNT-200', 'FIG-HAUNT-210', 'FIG-OP-400'].includes(row.sku)));
assert.ok(payload.records.every(row => typeof row['3dp'].G === 'number' && row['3dp'].G >= 0.02 && row['3dp'].G <= 100));
const nami = payload.records.find(row => row.sku === 'FIG-NAMI-201');
assert.deepEqual([nami.rrp_uah, nami.buyout_uah], [750, 500]);

const crmWrapper = fs.readFileSync(path.join(root, 'scripts', '3dp-catalog-reset', 'TemporaryCrmCatalogCanonicalCorrection.gs'), 'utf8');
const threeDpWrapper = fs.readFileSync(path.join(root, 'scripts', '3dp-catalog-reset', 'Temporary3dpCatalogMigration.gs'), 'utf8');
for (const name of ['catalogCanonicalCrmPreview', 'catalogCanonicalCrmIntegrityCheck', 'catalogCanonicalCrmRehearsal', 'catalogCanonicalCrmApply']) assert.match(crmWrapper, new RegExp(`function ${name}\\(`));
assert.match(threeDpWrapper, /function catalogMigration3dpRehearsalV2\(/);
assert.match(threeDpWrapper, /setNumberFormat\(PRINT_TIME_ENTRY_3DP\.numberFormat\)/);
assert.match(threeDpWrapper, /numberFormats: range\.getNumberFormats\(\)/);

console.log(JSON.stringify({ ok: true, rows: 72, active: 62, inactive: 10, article_changes: 3, name_changes: 39, status_changes: 3 }));
