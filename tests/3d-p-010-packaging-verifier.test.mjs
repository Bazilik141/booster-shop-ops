import assert from 'node:assert/strict';
import { once } from 'node:events';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { spawn } from 'node:child_process';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repo = path.resolve(here, '..');
const verifier = path.join(repo, 'tests', 'live-3dp010-packaging-verify.ps1');

function json(response, payload) {
  response.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
  response.end(JSON.stringify(payload));
}

test('3D-P-010 verifier uses only GET and proves one first-row packaging target', { timeout: 15000 }, async (context) => {
  const requests = [];
  const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://127.0.0.1');
    requests.push({ path: url.pathname, method: request.method, params: Object.fromEntries(url.searchParams) });
    if (url.pathname === '/crm/exec') return json(response, { ok: true, rows: [{ order_id: '3DP-SMOKE-1', row_index: 81, packaging_type: 'QA bag', packaging_cost: 5 }] });
    if (url.pathname === '/3dp/exec') return json(response, { ok: true, rows: [
      { row_number: 7, '№ замовлення': '3DP-SMOKE-1', 'Витрати BoosterShop за од., грн': 5 },
      { row_number: 9, '№ замовлення': '3DP-SMOKE-1', 'Витрати BoosterShop за од., грн': 0 },
    ] });
    response.writeHead(404); response.end();
  });
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  context.after(() => server.close());
  const port = server.address().port;
  const child = spawn('pwsh', ['-NoProfile', '-File', verifier, '-OrderId', '3DP-SMOKE-1', '-ExpectedPackagingUah', '5', '-WaitSeconds', '0'], {
    cwd: repo,
    env: {
      ...process.env,
      BOOSTER_CRM_URL: `http://127.0.0.1:${port}/crm/exec`,
      BOOSTER_CRM_TOKEN: 'crm-test-token',
      BOOSTER_3DP_URL: `http://127.0.0.1:${port}/3dp/exec`,
      BOOSTER_3DP_TOKEN: 'owner-test-token',
    },
  });
  let stdout = ''; let stderr = '';
  child.stdout.on('data', (chunk) => { stdout += chunk; });
  child.stderr.on('data', (chunk) => { stderr += chunk; });
  const [code] = await once(child, 'exit');
  assert.equal(code, 0, stderr);
  assert.match(stdout, /"no_live_writes":\s*true/);
  assert.equal(requests.length, 2);
  assert.deepEqual(requests.map((item) => item.method), ['GET', 'GET']);
  assert.deepEqual(requests.map((item) => item.params.action), ['recent_sales', '3dp_sales']);
  assert.equal(requests.some((item) => item.params.token === 'owner-test-token'), true);
});