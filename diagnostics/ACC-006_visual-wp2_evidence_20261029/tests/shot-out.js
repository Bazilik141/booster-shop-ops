// Screenshot of an out-of-stock related tile (dimmed photo, heart at full opacity).
const { chromium } = require('playwright'); const fs = require('fs'); const crypto = require('crypto');
const [,, wp, livehtml, out, CACHE] = process.argv;
(async () => { const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 390, height: 900 } });
const src = fs.readFileSync(`${wp}/tests/browser.js`, 'utf8');
const transform = eval('(' + src.match(/function transform\(html, ids\) \{[\s\S]*?\n\}/)[0] + ')');
global.transformed = 0; const HEART = src.match(/const HEART = '([^']+)'/)[1]; global.HEART = HEART;
await ctx.route('**/*', async r => { const u = new URL(r.request().url()); if (u.hostname !== 'boostershop.website' || r.request().method() !== 'GET') return r.abort();
  if (u.pathname.endsWith('/boostershop-ds.css')) return r.fulfill({ status: 200, contentType: 'text/css', body: fs.readFileSync(`${wp}/after/catalog/view/stylesheet/boostershop-ds.css`) });
  if (u.pathname.endsWith('/booster-wishlist.js')) return r.fulfill({ status: 200, contentType: 'application/javascript', body: fs.readFileSync(`${wp}/after/catalog/view/javascript/booster-wishlist.js`) });
  if (r.request().resourceType() === 'document') return r.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: transform(fs.readFileSync(`${livehtml}/prod.html`, 'utf8'), '') });
  const f = `${CACHE}/${crypto.createHash('sha256').update(r.request().url()).digest('hex')}`; if (fs.existsSync(f + '.json')) return r.fulfill({ status: 200, headers: JSON.parse(fs.readFileSync(f + '.json')), body: fs.readFileSync(f) }); return r.abort(); });
const p = await ctx.newPage(); await p.goto('https://boostershop.website/prod.html', { waitUntil: 'load' });
const t = p.locator('.bs-pcard--out').first(); await t.scrollIntoViewIfNeeded(); await t.screenshot({ path: `${out}/wp2-out-of-stock-tile-390.png` }); await b.close(); })();
