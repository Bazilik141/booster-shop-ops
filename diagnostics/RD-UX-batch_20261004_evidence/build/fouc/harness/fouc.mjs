// node fouc.mjs <outdir> <label:port>... : for each port × width (390, 768), navigates to the category fixture through the
// chunking proxy and, while the second chunk is held back, samples every ~120 ms: the painted frame (Page.captureScreenshot)
// and the H1 state (font-size, line-height, height, which heading spans are displayed, whether the inline block is parsed).
import { spawn } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path'; import { tmpdir } from 'node:os';
const [outdir, ...targets] = process.argv.slice(2); mkdirSync(outdir, { recursive: true });
const port = 9400 + Math.floor(Math.random() * 400);
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${join(tmpdir(), 'bs-fouc-' + port)}`,
  '--no-first-run', '--hide-scrollbars', '--disable-gpu', '--force-device-scale-factor=1', 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let list; for (let i = 0; i < 50; i++) { try { list = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); if (list.length) break; } catch {} await sleep(200); }
const ws = new WebSocket(list.find(t => t.type === 'page').webSocketDebuggerUrl); await new Promise(r => ws.addEventListener('open', r, { once: true }));
let id = 0; const pend = new Map(); ws.addEventListener('message', e => { const m = JSON.parse(e.data); if (m.id && pend.has(m.id)) { pend.get(m.id)(m); pend.delete(m.id); } });
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable'); await send('Network.setCacheDisabled', { cacheDisabled: false });
const EV = `(() => { const h = document.querySelector('.bs-cat-header__title h1'); if (!h) return { h1: false, rs: document.readyState };
  const cs = getComputedStyle(h); const sp = s => { const e = h.querySelector(s); return e ? getComputedStyle(e).display : null; };
  return { h1: true, rs: document.readyState, fontSize: cs.fontSize, lineHeight: cs.lineHeight, color: cs.color, height: Math.round(h.getBoundingClientRect().height),
    full: sp('.bs-heading-full'), mobile: sp('.bs-heading-mobile'), inlineStyles: document.querySelectorAll('body style').length, productList: !!document.getElementById('product-list'),
    loadMore: !!document.getElementById('bs-load-more-btn') }; })()`;
const out = [];
for (const t of targets) { const [label, p] = t.split(':');
  for (const w of [390, 768]) {
    await send('Page.navigate', { url: 'about:blank' }); await sleep(300);
    await send('Emulation.setDeviceMetricsOverride', { width: w, height: 760, deviceScaleFactor: 1, mobile: w < 768 });
    // warm the static assets (CSS/JS/fonts) so only the HTML is slow, as on a real chip tap
    await send('Page.navigate', { url: `http://127.0.0.1:${p}/?page=category&warm=1` }); await sleep(4200);
    await send('Page.navigate', { url: 'about:blank' }); await sleep(300);
    send('Page.navigate', { url: `http://127.0.0.1:${p}/?page=category` });
    const t0 = Date.now(); const samples = []; let n = 0;
    while (Date.now() - t0 < 3600) {
      const st = (await send('Runtime.evaluate', { expression: EV, returnByValue: true })).result?.result?.value;
      const ms = Date.now() - t0;
      if (st && st.h1) { const shot = await send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: w, height: 420, scale: 1 } });
        const f = `${label}_${w}_f${String(n).padStart(2, '0')}_${ms}ms.png`; if (n < 6 || st.rs === 'complete' && !samples.some(s => s.rs === 'complete')) writeFileSync(join(outdir, f), Buffer.from(shot.result.data, 'base64')); n++;
        samples.push({ ms, file: f, ...st }); }
      else samples.push({ ms, ...(st || {}) });
      await sleep(120);
    }
    const painted = samples.filter(s => s.h1);
    const bad = painted.filter(s => w < 992 && (s.full !== 'none' || parseFloat(s.fontSize) > 13));
    out.push({ label, w, firstH1: painted[0], last: painted[painted.length - 1], framesWithH1: painted.length, wrongFrames: bad.length, firstWrong: bad[0] || null, samples });
  } }
writeFileSync(join(outdir, 'fouc_samples.json'), JSON.stringify(out, null, 1));
for (const o of out) console.log(o.label, o.w, 'frames=' + o.framesWithH1, 'wrong=' + o.wrongFrames, 'first=' + JSON.stringify(o.firstH1 && { ms: o.firstH1.ms, rs: o.firstH1.rs, fs: o.firstH1.fontSize, full: o.firstH1.full, mobile: o.firstH1.mobile, h: o.firstH1.height, inline: o.firstH1.inlineStyles, list: o.firstH1.productList, loadMore: o.firstH1.loadMore }), 'last=' + JSON.stringify(o.last && { ms: o.last.ms, rs: o.last.rs, fs: o.last.fontSize, full: o.last.full, mobile: o.last.mobile, h: o.last.height }));
ws.close(); chrome.kill();
