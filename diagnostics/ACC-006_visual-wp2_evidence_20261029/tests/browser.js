// ACC-006 WP2 browser integration: live public pages (current WP1 state) transformed to the WP2
// tile markup, candidate JS/CSS, an in-test wishlist server. Non-boostershop hosts are aborted and
// wishlist/cart POSTs are answered locally; public assets are replayed from a local cache.
const { chromium } = require('playwright');
const fs = require('fs'); const crypto = require('crypto');
const [,, wp, livehtml, out, CACHE] = process.argv;
const JS = fs.readFileSync(`${wp}/after/catalog/view/javascript/booster-wishlist.js`);
const CSS = fs.readFileSync(`${wp}/after/catalog/view/stylesheet/boostershop-ds.css`);
const HEART = '<svg width="22" height="22" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 17s-7-4.4-7-9a4 4 0 0 1 7-2.4A4 4 0 0 1 17 8c0 4.6-7 9-7 9Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';
const results = []; const check = (n, ok, extra) => results.push({ n, ok: !!ok, extra });
let transformed = 0;
function transform(html, ids) {
  html = html.replace(/<article class="bs-pcard[\s\S]*?<\/article>/g, art => {
    const m = art.match(/\s*<button type="button" class="bs-btn bs-btn-secondary bs-btn-sm w-100 mt-2" data-bs-wishlist-add="(\d+)" data-bs-wishlist-name="([^"]*)" aria-pressed="false" aria-label="Додати в обране: [^"]*"><span data-bs-wishlist-text>Додати в обране<\/span><\/button>/);
    if (!m) throw new Error('WP1 tile button not found');
    art = art.replace(m[0], '');
    const start = art.indexOf('<a class="bs-pcard__media'); const end = art.indexOf('</a>', start) + 4;
    transformed++;
    return art.slice(0, start) + '<div class="bs-pcard__media-wrap">\n  ' + art.slice(start, end) + `\n  <button type="button" class="bs-pcard__heart" data-bs-wishlist-add="${m[1]}" data-bs-wishlist-name="${m[2]}" aria-pressed="false" aria-label="Додати в обране: ${m[2]}">${HEART}</button>\n  </div>` + art.slice(end);
  });
  html = html.replace(/data-ids="[^"]*"/, `data-ids="${ids}"`);
  return html.replace(/booster-wishlist\.js\?v=[^"]+/, 'booster-wishlist.js?v=acc006-visual-wp2-20261029').replace(/boostershop-ds\.css\?v=[^"]+/, 'boostershop-ds.css?v=acc006-visual-wp2-20261029');
}
const cacheFile = url => `${CACHE}/${crypto.createHash('sha256').update(url).digest('hex')}`;
async function remember(response) { try { const u = new URL(response.url()); if (u.hostname !== 'boostershop.website' || response.status() !== 200 || response.request().method() !== 'GET' || response.request().resourceType() === 'document' || u.searchParams.get('route')) return; const f = cacheFile(response.url()); if (fs.existsSync(f + '.json')) return; fs.writeFileSync(f, await response.body()); fs.writeFileSync(f + '.json', JSON.stringify({ 'content-type': response.headers()['content-type'] || 'application/octet-stream' })); } catch (e) {} }
async function open(browser, { file, width, mode = 'logged', ids = '', errorId = null }) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 } });
  const server = { members: new Set(ids ? ids.split(',') : []), posts: [] };
  await ctx.route('**/*', async route => {
    const u = new URL(route.request().url());
    if (u.hostname !== 'boostershop.website') return route.abort();
    const r = u.searchParams.get('route') || '';
    if (r.startsWith('account/wishlist.')) {
      const action = r.split('.')[1]; const body = new URLSearchParams(route.request().postData() || ''); const id = body.get('product_id');
      server.posts.push(action + ':' + id);
      if (id === errorId) return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ error: 'Оновіть сторінку та повторіть дію.' }) });
      let added = false;
      if (action === 'add') { added = !server.members.has(id); server.members.add(id); }
      if (action === 'remove') server.members.delete(id);
      const json = { count: server.members.size, ids: [...server.members].map(Number), wishlist_url: 'https://boostershop.website/index.php?route=account/wishlist', list_url: 'https://boostershop.website/index.php?route=account/wishlist.list' };
      if (action === 'add') Object.assign(json, { product_id: Number(id), added, success: mode === 'guest' ? 'Збережено в цьому браузері до входу' : 'Додано в обране' }, mode === 'guest' ? { requires_login: true, login_url: 'https://boostershop.website/index.php?route=account/login' } : {}, added ? { ps_add_to_wishlist: { ecommerce: { currency: 'UAH', value: 100, items: [{ item_id: id, item_name: 'X', quantity: 1, price: 100 }] } } } : {});
      if (action === 'remove') json.success = 'Прибрано з обраного';
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(json) });
    }
    if (route.request().method() !== 'GET') return route.abort();
    if (u.pathname.endsWith('/booster-wishlist.js')) return route.fulfill({ status: 200, contentType: 'application/javascript', body: JS });
    if (u.pathname.endsWith('/boostershop-ds.css')) return route.fulfill({ status: 200, contentType: 'text/css', body: CSS });
    if (route.request().resourceType() === 'document') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: transform(fs.readFileSync(`${livehtml}/${file}`, 'utf8'), ids) });
    const f = cacheFile(route.request().url());
    if (fs.existsSync(f + '.json')) return route.fulfill({ status: 200, headers: JSON.parse(fs.readFileSync(f + '.json')), body: fs.readFileSync(f) });
    return route.continue();
  });
  await ctx.addInitScript(() => { window.__ga = []; let v; Object.defineProperty(window, 'ps_dataLayer', { configurable: true, get() { return v; }, set(x) { v = x; if (x && typeof x.pushEventData === 'function') { const o = x.pushEventData; x.pushEventData = function (n, d) { window.__ga.push(n); try { return o.call(this, n, d); } catch (e) {} }; } } }); });
  const p = await ctx.newPage(); const errors = [];
  p.on('pageerror', e => errors.push(String(e))); p.on('response', remember);
  await p.goto(`https://boostershop.website/${file}`, { waitUntil: 'load' }); await p.waitForTimeout(300);
  return { ctx, p, server, errors };
}
const geometry = () => [...document.querySelectorAll('.bs-pcard')].map(card => {
  const media = card.querySelector('.bs-pcard__media').getBoundingClientRect(); const heart = card.querySelectorAll('.bs-pcard__heart');
  const h = heart[0].getBoundingClientRect(); const badges = [...card.querySelectorAll('.bs-pcard__badge-tl, .bs-pcard__badge-tr')].map(b => b.getBoundingClientRect().bottom);
  const img = card.querySelector('.bs-pcard__media img'); let dimmed = false;
  for (let n = heart[0]; n && n !== document.body; n = n.parentElement) if (getComputedStyle(n).opacity !== '1') dimmed = true;
  return { hearts: heart.length, textButtons: card.querySelectorAll('.bs-btn[data-bs-wishlist-add]').length, inset: [Math.round(media.right - h.right), Math.round(media.bottom - h.bottom)], size: [Math.round(h.width), Math.round(h.height)], clearBadges: badges.every(b => b <= h.top), out: card.classList.contains('bs-pcard--out'), imgOpacity: img ? getComputedStyle(img).opacity : null, heartDimmed: dimmed, inForm: !!heart[0].closest('form'), inLink: !!heart[0].closest('a'), visible: getComputedStyle(heart[0]).visibility === 'visible' && h.width > 0 };
});
(async () => {
  const browser = await chromium.launch();
  for (const width of [390, 768, 1280]) {
    for (const file of ['cat.html', 'acc.html', 'home.html', 'prod.html']) {
      const ids = (fs.readFileSync(`${livehtml}/${file}`, 'utf8').match(/w-100 mt-2" data-bs-wishlist-add="(\d+)"/g) || []).map(x => x.match(/add="(\d+)"/)[1]);
      const member = ids[1];
      const t = await open(browser, { file, width, ids: member });
      if (file === 'prod.html') await t.p.locator('.bs-pcard').first().scrollIntoViewIfNeeded();
      const g = await t.p.evaluate(geometry);
      check(`${width} ${file}: one heart per tile, no text button`, g.length > 0 && g.every(x => x.hearts === 1 && x.textButtons === 0), g.length);
      check(`${width} ${file}: heart 44x44 at 8px bottom-right of media`, g.every(x => x.size.join() === '44,44' && x.inset.join() === '8,8'), g.map(x => x.inset.join()));
      check(`${width} ${file}: heart clear of top badges`, g.every(x => x.clearBadges), g.filter(x => !x.clearBadges).length);
      check(`${width} ${file}: heart outside link and cart form`, g.every(x => !x.inForm && !x.inLink), '');
      if (g.some(x => x.out)) check(`${width} ${file}: out-of-stock image .45, heart not dimmed`, g.filter(x => x.out).every(x => x.imgOpacity === '0.45' && !x.heartDimmed), g.filter(x => x.out));
      const mem = await t.p.evaluate(id => { const b = document.querySelector(`.bs-pcard__heart[data-bs-wishlist-add="${id}"]`); return b && [b.getAttribute('aria-pressed'), getComputedStyle(b.querySelector('path')).fill, b.getAttribute('aria-label').startsWith('Прибрати з обраного: ')]; }, member);
      check(`${width} ${file}: member heart red filled on load`, mem && mem[0] === 'true' && mem[1] === 'rgb(220, 38, 38)' && mem[2], mem);
      const id = ids[0]; const heart = t.p.locator(`.bs-pcard__heart[data-bs-wishlist-add="${id}"]`).first();
      await heart.scrollIntoViewIfNeeded();
      const off = await heart.evaluate(b => [b.getAttribute('aria-pressed'), getComputedStyle(b.querySelector('path')).fill, getComputedStyle(b).color]);
      check(`${width} ${file}: non-member heart outline ink-2`, off[0] === 'false' && off[1] === 'none' && off[2] === 'rgb(31, 41, 55)', off);
      await heart.dblclick(); await t.p.waitForTimeout(350);
      const on = await heart.evaluate(b => [b.getAttribute('aria-pressed'), getComputedStyle(b.querySelector('path')).fill, window.__ga.filter(x => x === 'add_to_wishlist').length, document.querySelector('[data-bs-wishlist-notice]').innerText]);
      check(`${width} ${file}: tap = one add, red fill, one GA, notice`, t.server.posts.filter(x => x.startsWith('add:')).length === 1 && on[0] === 'true' && on[1] === 'rgb(220, 38, 38)' && on[2] === 1 && /Додано в обране/.test(on[3]), [t.server.posts, on]);
      await heart.click(); await t.p.waitForTimeout(350);
      const rm = await heart.evaluate(b => [b.getAttribute('aria-pressed'), window.__ga.filter(x => x === 'add_to_wishlist').length]);
      check(`${width} ${file}: second tap removes, no GA`, rm[0] === 'false' && rm[1] === 1 && t.server.posts.includes('remove:' + id), rm);
      await heart.focus(); const tab = await t.p.evaluate(() => document.activeElement.className);
      check(`${width} ${file}: heart keyboard-focusable`, tab === 'bs-pcard__heart', tab);
      if (width === 390 && file === 'cat.html') {
        // UX-003 load more and filter paths, same DOM calls as bs-category-results.js
        const appended = await t.p.evaluate(({ html, member }) => {
          const doc = new DOMParser().parseFromString(html, 'text/html');
          const grid = document.querySelector('#product-list');
          doc.querySelectorAll('#product-list > .col').forEach(card => grid.appendChild(document.importNode(card, true)));
          return new Promise(res => setTimeout(() => { const all = [...document.querySelectorAll(`.bs-pcard__heart[data-bs-wishlist-add="${member}"]`)]; res(all.map(b => b.getAttribute('aria-pressed'))); }, 50));
        }, { html: transform(fs.readFileSync(`${livehtml}/cat.html`, 'utf8'), ''), member });
        check('390 load-more append: inserted member tile synced', appended.length === 2 && appended.every(x => x === 'true'), appended);
        const replaced = await t.p.evaluate(({ html, member }) => {
          const doc = new DOMParser().parseFromString(html, 'text/html'); const region = document.querySelector('#bs-category-results'); const next = doc.querySelector('#bs-category-results');
          region.replaceChildren.apply(region, Array.from(next.childNodes).map(n => document.importNode(n, true)));
          return new Promise(res => setTimeout(() => res([...document.querySelectorAll(`.bs-pcard__heart[data-bs-wishlist-add="${member}"]`)].map(b => b.getAttribute('aria-pressed'))), 50));
        }, { html: transform(fs.readFileSync(`${livehtml}/cat.html`, 'utf8'), ''), member });
        check('390 filter replace: replaced member tile synced', replaced.length === 1 && replaced[0] === 'true', replaced);
      }
      check(`${width} ${file}: no page errors`, t.errors.length === 0, t.errors);
      if (file !== 'prod.html') await t.p.screenshot({ path: `${out}/wp2-${file.replace('.html', '')}-${width}.png` });
      else await t.p.locator('.bs-pcard').first().screenshot({ path: `${out}/wp2-prod-related-tile-${width}.png` });
      await t.ctx.close();
    }
  }
  // error notice persists; confirmation closes after 5 s
  const ids = fs.readFileSync(`${livehtml}/cat.html`, 'utf8').match(/data-bs-wishlist-add="(\d+)"/g).map(x => x.match(/\d+/)[0]);
  const e = await open(browser, { file: 'cat.html', width: 1280, errorId: ids[2] });
  await e.p.locator(`.bs-pcard__heart[data-bs-wishlist-add="${ids[2]}"]`).click(); await e.p.waitForTimeout(5600);
  const err = await e.p.evaluate(() => { const n = document.querySelector('[data-bs-wishlist-notice]'); return [n.hidden, n.classList.contains('bs-wishlist-notice--error'), n.innerText]; });
  check('error notice stays visible after 5.6 s', err[0] === false && err[1] && /Оновіть сторінку/.test(err[2]), err);
  await e.p.locator('.bs-wishlist-notice__close').click();
  await e.p.locator(`.bs-pcard__heart[data-bs-wishlist-add="${ids[0]}"]`).click(); await e.p.waitForTimeout(5600);
  check('confirmation closes after 5 s', await e.p.evaluate(() => document.querySelector('[data-bs-wishlist-notice]').hidden), '');
  await e.ctx.close();
  await browser.close();
  for (const r of results) console.log((r.ok ? 'ok   ' : 'FAIL ') + r.n + (r.ok ? '' : '  ' + JSON.stringify(r.extra).slice(0, 500)));
  console.log(`tiles_transformed=${transformed}`);
  console.log(`browser_checks=${results.length} failed=${results.filter(r => !r.ok).length}`);
  process.exit(results.some(r => !r.ok) ? 1 : 0);
})();
