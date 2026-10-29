// ACC-006 WP3 browser integration: live public product pages (current WP2 state) transformed to the
// WP3 markup, candidate JS/CSS, in-test wishlist server. Non-boostershop hosts aborted; every POST
// other than the in-test wishlist answer is aborted and counted; public assets replayed from cache.
const { chromium } = require('playwright');
const fs = require('fs'); const crypto = require('crypto');
const [,, wp, livehtml, out, CACHE] = process.argv;
const JS = fs.readFileSync(`${wp}/after/catalog/view/javascript/booster-wishlist.js`);
const CSS = fs.readFileSync(`${wp}/after/catalog/view/stylesheet/boostershop-ds.css`);
const svg = s => `<svg width="${s}" height="${s}" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 17s-7-4.4-7-9a4 4 0 0 1 7-2.4A4 4 0 0 1 17 8c0 4.6-7 9-7 9Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>`;
const results = []; const check = (n, ok, extra) => results.push({ n, ok: !!ok, extra });
function transform(html, { solo = false } = {}) {
  const m = html.match(/\s*<button type="button" class="bs-btn bs-btn-secondary mt-2" data-bs-wishlist-add="(\d+)" data-bs-wishlist-name="([^"]*)" aria-pressed="false" aria-label="Додати в обране: [^"]*"><span data-bs-wishlist-text>Додати в обране<\/span><\/button>/);
  if (!m) throw new Error('WP1 product button not found');
  const attr = `data-bs-wishlist-add="${m[1]}" data-bs-wishlist-name="${m[2]}" aria-pressed="false" aria-label="Додати в обране: ${m[2]}"`;
  html = html.replace(m[0], '');
  if (solo) { const gs = html.indexOf('<div class="bs-product__gallery'); const is = html.indexOf('<div class="bs-product__info"', gs); html = html.slice(0, gs) + html.slice(is); }
  else {
    const g = html.indexOf('data-bs-gallery-main'); const e = html.indexOf('</a>', g) + 4;
    html = html.slice(0, e) + `\n            <button type="button" class="bs-product-heart bs-product-heart--gallery" ${attr}>${svg(20)}</button>` + html.slice(e);
  }
  const c = html.indexOf('id="button-cart"'); const ce = html.indexOf('</button>', c) + 9;
  html = html.slice(0, ce) + `\n                  <button type="button" class="bs-product-heart bs-product-heart--row${solo ? ' bs-product-heart--solo' : ''}" ${attr}>${svg(22)}</button>` + html.slice(ce);
  return html.replace(/booster-wishlist\.js\?v=[^"]+/, 'booster-wishlist.js?v=acc006-visual-wp3-20261029').replace(/boostershop-ds\.css\?v=[^"]+/, 'boostershop-ds.css?v=acc006-visual-wp3-20261029');
}
const cacheFile = url => `${CACHE}/${crypto.createHash('sha256').update(url).digest('hex')}`;
async function remember(response) { try { const u = new URL(response.url()); if (u.hostname !== 'boostershop.website' || response.status() !== 200 || response.request().method() !== 'GET' || response.request().resourceType() === 'document' || u.searchParams.get('route')) return; const f = cacheFile(response.url()); if (fs.existsSync(f + '.json')) return; fs.writeFileSync(f, await response.body()); fs.writeFileSync(f + '.json', JSON.stringify({ 'content-type': response.headers()['content-type'] || 'application/octet-stream' })); } catch (e) {} }
async function open(browser, { file, width, solo = false, raw = false }) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 } });
  const server = { members: new Set(), posts: [], otherPosts: [] };
  await ctx.route('**/*', async route => {
    const u = new URL(route.request().url()); const r = u.searchParams.get('route') || '';
    if (u.hostname !== 'boostershop.website') return route.abort();
    if (r.startsWith('account/wishlist.')) {
      const action = r.split('.')[1]; const id = new URLSearchParams(route.request().postData() || '').get('product_id'); server.posts.push(action + ':' + id);
      let added = false; if (action === 'add') { added = !server.members.has(id); server.members.add(id); } if (action === 'remove') server.members.delete(id);
      const json = { count: server.members.size, ids: [...server.members].map(Number), wishlist_url: 'https://boostershop.website/index.php?route=account/wishlist', list_url: 'https://boostershop.website/index.php?route=account/wishlist.list' };
      if (action === 'add') Object.assign(json, { product_id: Number(id), added, success: 'Додано в обране' }, added ? { ps_add_to_wishlist: { ecommerce: { currency: 'UAH', value: 100, items: [{ item_id: id, item_name: 'X', quantity: 1, price: 100 }] } } } : {});
      if (action === 'remove') json.success = 'Прибрано з обраного';
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(json) });
    }
    if (route.request().method() !== 'GET') { server.otherPosts.push(r || u.pathname); return route.abort(); }
    if (!raw && u.pathname.endsWith('/booster-wishlist.js')) return route.fulfill({ status: 200, contentType: 'application/javascript', body: JS });
    if (!raw && u.pathname.endsWith('/boostershop-ds.css')) return route.fulfill({ status: 200, contentType: 'text/css', body: CSS });
    if (route.request().resourceType() === 'document') { const h = fs.readFileSync(`${livehtml}/${file}`, 'utf8'); return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: raw ? h : transform(h, { solo }) }); }
    const f = cacheFile(route.request().url());
    if (fs.existsSync(f + '.json')) return route.fulfill({ status: 200, headers: JSON.parse(fs.readFileSync(f + '.json')), body: fs.readFileSync(f) });
    return route.continue();
  });
  await ctx.addInitScript(() => { window.__ga = []; let v; Object.defineProperty(window, 'ps_dataLayer', { configurable: true, get() { return v; }, set(x) { v = x; if (x && typeof x.pushEventData === 'function') { const o = x.pushEventData; x.pushEventData = function (n, d) { window.__ga.push(n); try { return o.call(this, n, d); } catch (e) {} }; } } }); });
  const p = await ctx.newPage(); const errors = [];
  p.on('pageerror', e => errors.push(String(e))); p.on('response', remember);
  await p.goto(`https://boostershop.website/${file}`, { waitUntil: 'load' }); await p.waitForTimeout(400);
  return { ctx, p, server, errors };
}
const state = () => {
  const shown = el => { for (let n = el; n; n = n.parentElement) if (getComputedStyle(n).display === 'none') return false; return true; };
  const hearts = [...document.querySelectorAll('.bs-product-heart')].map(h => { const r = h.getBoundingClientRect(); return { cls: h.className, shown: shown(h), w: Math.round(r.width), h: Math.round(r.height), pressed: h.getAttribute('aria-pressed'), fill: getComputedStyle(h.querySelector('path')).fill, border: getComputedStyle(h).borderTopColor }; });
  const row = document.querySelector('.bs-product__info .bs-buy-row'); const cta = document.querySelector('#button-cart'); const rh = document.querySelector('.bs-product-heart--row');
  const c = cta.getBoundingClientRect(), r = rh.getBoundingClientRect();
  const main = document.querySelector('.bs-product__main-img'); const gh = document.querySelector('.bs-product-heart--gallery');
  const m = main && main.getBoundingClientRect(), g = gh && gh.getBoundingClientRect();
  return { hearts, rowParent: rh.parentElement === row, afterCta: !!(cta.compareDocumentPosition(rh) & Node.DOCUMENT_POSITION_FOLLOWING), centred: Math.abs((c.top + c.bottom) / 2 - (r.top + r.bottom) / 2) <= 1, ctaFits: cta.scrollWidth <= cta.clientWidth + 1, ctaWidth: Math.round(c.width), oneLine: r.top < c.bottom && r.bottom > c.top, rowFits: row.scrollWidth <= row.clientWidth + 1, galleryInset: g && g.width ? [Math.round(m.right - g.right), Math.round(g.top - m.top)] : null, sticky: !!document.querySelector('.bs-sticky-atc'), mfp: !!document.querySelector('.mfp-wrap') };
};
(async () => {
  const browser = await chromium.launch();
  for (const file of ['prod.html', 'pre.html']) for (const width of [390, 767, 768, 1024, 1280]) {
    const t = await open(browser, { file, width });
    await t.p.locator('#button-cart').scrollIntoViewIfNeeded();
    const s = await t.p.evaluate(state); const mobile = width <= 767;
    const shown = s.hearts.filter(h => h.shown);
    check(`${file} ${width}: exactly one heart visible/focusable (${mobile ? 'gallery' : 'row'})`, s.hearts.length === 2 && shown.length === 1 && shown[0].cls.includes(mobile ? '--gallery' : '--row'), s.hearts.map(h => [h.cls, h.shown]));
    if (mobile) check(`${file} ${width}: gallery heart 44x44 at 12px top-right`, shown[0].w === 44 && shown[0].h === 44 && s.galleryInset && s.galleryInset.join() === '12,12', [shown[0].w, s.galleryInset]);
    else check(`${file} ${width}: row heart 44x44 after #button-cart in .bs-buy-row, centred, one line, CTA text fits`, shown[0].w === 44 && shown[0].h === 44 && s.rowParent && s.afterCta && s.centred && s.oneLine && s.ctaFits && s.rowFits, s);
    const sel = mobile ? '.bs-product-heart--gallery' : '.bs-product-heart--row';
    await t.p.locator(sel).scrollIntoViewIfNeeded(); await t.p.locator(sel).dblclick(); await t.p.waitForTimeout(400);
    const a = await t.p.evaluate(state);
    const ga = await t.p.evaluate(() => window.__ga.filter(x => x === 'add_to_wishlist').length);
    const vis = a.hearts.find(h => h.shown);
    check(`${file} ${width}: tap = one add, both hearts pressed, visible red fill+border, one GA, no popup, no form submit`, t.server.posts.filter(x => x.startsWith('add:')).length === 1 && a.hearts.every(h => h.pressed === 'true') && vis.fill === 'rgb(220, 38, 38)' && vis.border === 'rgb(220, 38, 38)' && ga === 1 && !a.mfp && t.server.otherPosts.length === 0, [t.server.posts, t.server.otherPosts, vis, ga, a.mfp]);
    await t.p.waitForTimeout(3100);
    const at35 = await t.p.evaluate(() => !document.querySelector('[data-bs-wishlist-notice]').hidden);
    await t.p.waitForTimeout(900);
    const at44 = await t.p.evaluate(() => document.querySelector('[data-bs-wishlist-notice]').hidden);
    check(`${file} ${width}: confirmation visible at 3.5 s, closed by 4.4 s`, at35 && at44, [at35, at44]);
    await t.p.locator(sel).click(); await t.p.waitForTimeout(400);
    const r = await t.p.evaluate(state);
    check(`${file} ${width}: second tap removes, no GA`, r.hearts.every(h => h.pressed === 'false') && (await t.p.evaluate(() => window.__ga.filter(x => x === 'add_to_wishlist').length)) === 1, r.hearts);
    if (mobile) {
      await t.p.locator('[data-bs-gallery-main]').click(); await t.p.waitForTimeout(600);
      check(`${file} ${width}: tapping the photo still opens Magnific`, await t.p.evaluate(() => !!document.querySelector('.mfp-wrap')), '');
      await t.p.keyboard.press('Escape'); await t.p.waitForTimeout(300);
    }
    await t.p.locator(sel).focus();
    check(`${file} ${width}: visible heart keyboard-focusable`, await t.p.evaluate(sel => document.activeElement === document.querySelector(sel), sel), '');
    check(`${file} ${width}: no page errors`, t.errors.length === 0, t.errors);
    if (width === 390 || width === 1280 || width === 768) {
      if (mobile) { await t.p.evaluate(() => scrollTo(0, 0)); await t.p.waitForTimeout(200); await t.p.screenshot({ path: `${out}/wp3-${file.replace('.html', '')}-${width}.png` }); }
      else { await t.p.locator('.bs-buy-wrap').screenshot({ path: `${out}/wp3-${file.replace('.html', '')}-buyrow-${width}.png` }); }
    }
    await t.ctx.close();
    if (width === 390) {
      // sticky ATC bar: same visibility after scrolling as on the untouched live page
      const sticky = [];
      for (const raw of [true, false]) {
        const q = await open(browser, { file, width, raw }); await q.p.evaluate(() => scrollTo(0, document.body.scrollHeight * 0.6)); await q.p.waitForTimeout(600);
        sticky.push(await q.p.evaluate(() => { const b = document.querySelector('.bs-sticky-atc'); return [b.hidden, getComputedStyle(b).display, b.outerHTML.length]; }));
        await q.ctx.close();
      }
      check(`${file} 390: sticky ATC bar behaves as on the live page`, JSON.stringify(sticky[0]) === JSON.stringify(sticky[1]), sticky);
    }
  }
  for (const width of [390, 1280]) {
    const t = await open(browser, { file: 'prod.html', width, solo: true });
    const s = await t.p.evaluate(state);
    check(`no-gallery fallback ${width}: the --solo row heart is the one visible heart`, s.hearts.length === 1 && s.hearts[0].shown && s.hearts[0].cls.includes('--solo'), s.hearts);
    await t.ctx.close();
  }
  await browser.close();
  for (const r of results) console.log((r.ok ? 'ok   ' : 'FAIL ') + r.n + (r.ok ? '' : '  ' + JSON.stringify(r.extra).slice(0, 700)));
  console.log(`browser_checks=${results.length} failed=${results.filter(r => !r.ok).length}`);
  process.exit(results.some(r => !r.ok) ? 1 : 0);
})();
