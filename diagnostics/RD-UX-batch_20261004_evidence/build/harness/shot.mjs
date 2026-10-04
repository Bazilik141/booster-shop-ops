// Usage: node shot.mjs jobs.json
// jobs: [{url, w, h?, out?, full?, pre?: [js], wait?: ms, evalOut?: js}] — runs sequentially in one headless Chrome.
import { spawn } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';

const jobs = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const port = 9333 + Math.floor(Math.random() * 500);
const profile = join(process.env.SHOT_PROFILE || tmpdir(), 'bs-shot-profile-' + port);
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
  '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--hide-scrollbars',
  '--disable-gpu', '--force-device-scale-factor=1', 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let ver;
for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); if (ver.length) break; } catch {} await sleep(200); }
const ws = new WebSocket(ver.find(t => t.type === 'page').webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r, { once: true }));
let id = 0; const pending = new Map(); const logs = [];
ws.addEventListener('message', ev => {
  const m = JSON.parse(ev.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
  if (m.method === 'Runtime.consoleAPICalled') logs.push(m.params.type + ': ' + m.params.args.map(a => a.value ?? a.description).join(' '));
  if (m.method === 'Runtime.exceptionThrown') logs.push('EXCEPTION: ' + (m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text));
  if (m.method === 'Log.entryAdded' && m.params.entry.level === 'error') logs.push('LOG-ERROR: ' + m.params.entry.text + ' ' + (m.params.entry.url || ''));
});
const send = (method, params = {}) => new Promise(r => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
await send('Page.enable'); await send('Runtime.enable'); await send('Log.enable');
await send('Emulation.setFocusEmulationEnabled', { enabled: true }); // page counts as focused, so focus events fire
const evalJs = async (expr) => { const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true }); return r.result?.result?.value ?? r.result?.exceptionDetails?.exception?.description; };
const results = [];
for (const j of jobs) {
  logs.length = 0;
  const h = j.h || 844;
  await send('Emulation.setDeviceMetricsOverride', { width: j.w, height: h, deviceScaleFactor: 1, mobile: j.w < 768 });
  await send('Emulation.setTouchEmulationEnabled', { enabled: j.w < 768 });
  if (j.offline !== undefined) await send('Network.emulateNetworkConditions', { offline: !!j.offline, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
  await send('Page.navigate', { url: j.url });
  await sleep(j.loadWait ?? 1500);
  for (const p of (j.pre || [])) { const v = await evalJs(p); if (v !== undefined && j.logPre) logs.push('pre: ' + JSON.stringify(v)); await sleep(j.stepWait ?? 400); }
  await sleep(j.wait ?? 300);
  let evalResult;
  if (j.evalOut) evalResult = await evalJs(j.evalOut);
  if (j.out) {
    let clip;
    if (j.full) {
      const dims = await evalJs('JSON.stringify([document.documentElement.scrollWidth, Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)])');
      const [sw, sh] = JSON.parse(dims);
      clip = null; await send("Emulation.setDeviceMetricsOverride", { width: j.w, height: Math.min(sh, 12000), deviceScaleFactor: 1, mobile: j.w < 768 }); await sleep(400);
    }
    const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false, ...(clip ? { clip } : {}) });
    mkdirSync(dirname(j.out), { recursive: true });
    writeFileSync(j.out, Buffer.from(shot.result.data, 'base64'));
  }
  results.push({ url: j.url, w: j.w, out: j.out, eval: evalResult, logs: [...logs] });
}
console.log(JSON.stringify(results, null, 1));
ws.close(); chrome.kill();
