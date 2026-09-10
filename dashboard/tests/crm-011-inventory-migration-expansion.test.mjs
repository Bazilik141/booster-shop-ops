import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const html = fs.readFileSync(path.resolve(here, '../booster-dashboard.html'), 'utf8');

test('inventory migration accepts packages and exposes searchable SKU selects', () => {
  assert.match(html, /Упаковка → поштучний SKU/);
  assert.match(html, /id="migrationSplitSourceSearch" type="search"/);
  assert.match(html, /id="migrationSplitTargetSearch" type="search"/);
  assert.ok(html.includes("inventoryMigrationFilterOptions(\\'migrationSplitSourceSearch\\',\\'migrationSplitSource\\')"));
  assert.ok(html.includes("inventoryMigrationFilterOptions(\\'migrationSplitTargetSearch\\',\\'migrationSplitTarget\\')"));
  assert.match(html, /type:isSplit\?'container_to_units':'packs_to_outlet'/);
});

test('toploader package defaults are applied by the UI contract', () => {
  assert.match(html, /source\.fixed_target_sku&&target\)target\.value=source\.fixed_target_sku/);
  assert.match(html, /source\.default_target_qty&&qty\)qty\.value=source\.default_target_qty/);
});

test('dashboard inline script remains syntactically valid', () => {
  const scripts = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)].map(match => match[1]);
  assert.ok(scripts.length > 0);
  for (const source of scripts) new Function(source);
});
