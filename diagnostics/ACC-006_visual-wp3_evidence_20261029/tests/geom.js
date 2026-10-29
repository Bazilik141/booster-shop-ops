const { chromium } = require('playwright'); const fs = require('fs');
const src = fs.readFileSync(__dirname + '/browser.js', 'utf8');
const [,, wp, livehtml, out, CACHE] = process.argv;
eval(src.replace(/\(async \(\) => \{\n  const browser[\s\S]*$/, '') + `
(async () => { const b = await chromium.launch(); for (const raw of [true, false]) { const t = await open(b, { file: 'prod.html', width: 1280, raw }); await t.p.locator('#button-cart').scrollIntoViewIfNeeded();
console.log(raw ? 'LIVE' : 'WP3', JSON.stringify(await t.p.evaluate(() => [...document.querySelector('.bs-product__info .bs-buy-row').children].filter(e => getComputedStyle(e).display !== 'none').map(e => { const r = e.getBoundingClientRect(); return [e.id || e.className.slice(0, 30), Math.round(r.top), Math.round(r.height), Math.round(r.width)]; }))));
await t.ctx.close(); } await b.close(); })();`);
