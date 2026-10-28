// ACC-006 WP1 browser integration on live public pages with candidate HTML transforms,
// candidate JS/CSS and an in-test fake wishlist server. All non-boostershop hosts are aborted,
// and every wishlist/cart POST is answered locally: nothing reaches production endpoints.
const { chromium } = require('playwright');
const fs = require('fs');
const [,, wp, livehtml, out] = process.argv;
const JS = fs.readFileSync(`${wp}/after/catalog/view/javascript/booster-wishlist.js`);
const CSS = fs.readFileSync(`${wp}/after/catalog/view/stylesheet/boostershop-ds.css`);
const FAV = fs.readFileSync(`${wp}/fixtures/favorites-full.html`, 'utf8');
const FAV_EMPTY = fs.readFileSync(`${wp}/fixtures/favorites-empty.html`, 'utf8');
const crypto = require('crypto');
const CACHE = '/tmp/claude-0/-home-user-booster-shop-ops/f354648f-5410-5be7-8e4b-312c30ff670a/scratchpad/assetcache';
// Each live public asset is fetched once and replayed from disk: test reruns add no production load.
function cacheFile(url) { return `${CACHE}/${crypto.createHash('sha256').update(url).digest('hex')}`; }
async function cached(route) {
  const file = cacheFile(route.request().url());
  if (fs.existsSync(file + '.json')) { const meta = JSON.parse(fs.readFileSync(file + '.json')); return route.fulfill({ status: 200, headers: meta, body: fs.readFileSync(file) }); }
  return route.continue();
}
async function remember(response) {
  try {
    const u = new URL(response.url());
    if (u.hostname !== 'boostershop.website' || response.status() !== 200 || response.request().method() !== 'GET' || response.request().resourceType() === 'document' || u.searchParams.get('route')) return;
    const file = cacheFile(response.url());
    if (fs.existsSync(file + '.json')) return;
    fs.writeFileSync(file, await response.body());
    fs.writeFileSync(file + '.json', JSON.stringify({ 'content-type': response.headers()['content-type'] || 'application/octet-stream' }));
  } catch (e) {}
}
const results = []; const check = (n, ok, extra) => results.push({ n, ok: !!ok, extra });
function transform(html, { ids = '', favorites = null }) {
  html = html.replace(/<button type="button" class="(bs-btn bs-btn-secondary(?: bs-btn-sm w-100)? mt-2)" data-bs-wishlist-add="(\d+)" aria-label="Додати в улюблене: ([^"]*)">В улюблене<\/button>/g,
    (m, cls, id, name) => `<button type="button" class="${cls}" data-bs-wishlist-add="${id}" data-bs-wishlist-name="${name}" aria-pressed="false" aria-label="Додати в обране: ${name}"><span data-bs-wishlist-text>Додати в обране</span></button>`);
  html = html.replace('aria-label="Улюблене">', `\n         data-ids="${ids}" aria-label="Обране">`);
  html = html.replace('<div class="container" data-bs-wishlist-notice role="status" aria-live="polite" hidden></div>', '<div class="bs-wishlist-notice" data-bs-wishlist-notice role="status" aria-live="polite" hidden></div>');
  html = html.replace(/booster-wishlist\.js\?v=[^"]+/, 'booster-wishlist.js?v=acc006-visual-wp1-20261028').replace(/boostershop-ds\.css\?v=[^"]+/, 'boostershop-ds.css?v=acc006-visual-wp1-20261028');
  if (favorites !== null) {
    const imgs = [...html.matchAll(/<img src="(https:\/\/boostershop\.website\/image\/cache\/rd25-fit\/[^"]+)"/g)].map(x => x[1]);
    let i = 0; favorites = favorites.replace(/https:\/\/boostershop\.website\/image\/cache\/rd25-fit\/x\.webp/g, () => imgs[(i++) % imgs.length]);
    html = html.replace(/<main>[\s\S]*<\/main>/, '<main>\n<div class="bs-wishlist-notice" data-bs-wishlist-notice role="status" aria-live="polite" hidden></div>\n' + favorites + '\n</main>');
  }
  return html;
}
async function page(browser, { file, width, mode, ids, favorites }) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 } });
  const server = { members: new Set(ids ? ids.split(',') : []), posts: [], mode };
  await ctx.route('**/*', async route => {
    const u = new URL(route.request().url());
    if (u.hostname !== 'boostershop.website') return route.abort();
    if (u.searchParams.get('route') && u.searchParams.get('route').startsWith('account/wishlist.')) {
      const action = u.searchParams.get('route').split('.')[1];
      const body = new URLSearchParams(route.request().postData() || '');
      server.posts.push(action);
      if (action === 'list') return route.fulfill({ status: 200, contentType: 'text/html', body: '<div data-bs-wishlist-list data-count="' + server.members.size + '"></div>' });
      const id = body.get('product_id'); let added = false;
      if (action === 'add') { added = !server.members.has(id); server.members.add(id); }
      if (action === 'remove') server.members.delete(id);
      const json = { count: server.members.size, ids: [...server.members].map(Number), total: '', wishlist_url: 'https://boostershop.website/index.php?route=account/wishlist', list_url: 'https://boostershop.website/index.php?route=account/wishlist.list' };
      if (action === 'add') Object.assign(json, { product_id: Number(id), added, success: mode === 'guest' ? 'Збережено в цьому браузері до входу' : 'Додано в обране' }, mode === 'guest' ? { requires_login: true, login_url: 'https://boostershop.website/index.php?route=account/login' } : {}, added ? { ps_add_to_wishlist: { ecommerce: { currency: 'UAH', value: 100, items: [{ item_id: id, item_name: 'X', quantity: 1, price: 100 }] } } } : {});
      if (action === 'remove') json.success = 'Прибрано з обраного';
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(json) });
    }
    if (route.request().method() !== 'GET') return route.abort();
    if (u.pathname.endsWith('/booster-wishlist.js')) return route.fulfill({ status: 200, contentType: 'application/javascript', body: JS });
    if (u.pathname.endsWith('/boostershop-ds.css')) return route.fulfill({ status: 200, contentType: 'text/css', body: CSS });
    if (route.request().resourceType() === 'document') return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: transform(fs.readFileSync(`${livehtml}/${file}`, 'utf8'), { ids, favorites }) });
    return cached(route);
  });
  await ctx.addInitScript(() => {
    window.__ga = []; let v;
    Object.defineProperty(window, 'ps_dataLayer', { configurable: true, get() { return v; }, set(x) { v = x; if (x && typeof x.pushEventData === 'function') { const o = x.pushEventData; x.pushEventData = function (n, d) { window.__ga.push(n); try { return o.call(this, n, d); } catch (e) {} }; } } });
  });
  const p = await ctx.newPage(); const errors = [];
  p.on('pageerror', e => errors.push(String(e)));
  p.on('response', remember);
  p.on('response', r => { if (r.status() >= 400 && new URL(r.url()).hostname === 'boostershop.website') errors.push('HTTP ' + r.status() + ' ' + r.url().slice(0, 120)); });
  p.on('requestfailed', r => { if (new URL(r.url()).hostname === 'boostershop.website') errors.push('FAILED ' + r.failure().errorText + ' ' + r.url().slice(0, 120)); });
  await p.goto(`https://boostershop.website/${file}`, { waitUntil: 'load' });
  await p.waitForTimeout(300);
  return { ctx, p, server, errors };
}
(async () => {
  const browser = await chromium.launch();
  for (const width of [390, 768, 1280]) {
    for (const mode of ['logged', 'guest']) {
      // category tiles
      const first = fs.readFileSync(`${livehtml}/cat.html`, 'utf8').match(/data-bs-wishlist-add="(\d+)"/g).map(x => x.match(/\d+/)[0]);
      const pre = first[1];
      const t = await page(browser, { file: 'cat.html', width, mode, ids: pre });
      const st = await t.p.evaluate(pre => { const b = [...document.querySelectorAll(`[data-bs-wishlist-add="${pre}"]`)][0]; return [b.getAttribute('aria-pressed'), b.textContent.trim(), b.getAttribute('aria-label')]; }, pre);
      check(`${width} ${mode} cat: server ids mark tile pressed`, st[0] === 'true' && st[1] === 'В обраному' && st[2].startsWith('Прибрати з обраного: '), st);
      const id = first[0]; const btn = t.p.locator(`[data-bs-wishlist-add="${id}"]`).first();
      await btn.scrollIntoViewIfNeeded();
      const h0 = await btn.evaluate(b => [b.offsetHeight, b.scrollWidth <= b.clientWidth, b.closest('.bs-pcard').offsetHeight]);
      await btn.dblclick(); await t.p.waitForTimeout(400);
      const after = await t.p.evaluate(id => { const b = document.querySelector(`[data-bs-wishlist-add="${id}"]`); const n = document.querySelector('[data-bs-wishlist-notice]'); const r = n.getBoundingClientRect(); return { pressed: b.getAttribute('aria-pressed'), text: b.textContent.trim(), count: document.querySelector('[data-bs-wishlist-count]').textContent, ga: window.__ga.filter(x => x === 'add_to_wishlist').length, notice: n.hidden ? null : n.innerText, links: [...n.querySelectorAll('a')].map(a => a.textContent), inView: r.left >= 0 && r.right <= innerWidth && r.bottom <= innerHeight, h: b.offsetHeight }; }, id);
      check(`${width} ${mode} cat: double-click = one add request`, t.server.posts.filter(x => x === 'add').length === 1, t.server.posts);
      check(`${width} ${mode} cat: pressed + counter 2`, after.pressed === 'true' && after.text === 'В обраному' && after.count === '2', after);
      check(`${width} ${mode} cat: exactly one add_to_wishlist`, after.ga === 1, after.ga);
      check(`${width} ${mode} cat: notice text/links/in viewport`, mode === 'guest' ? (/Збережено в цьому браузері до входу/.test(after.notice) && /Увійдіть, і товар перейде у ваш кабінет\./.test(after.notice) && after.links.join() === 'Увійти') : (/Додано в обране/.test(after.notice) && after.links.join() === 'Переглянути'), after);
      check(`${width} ${mode} cat: notice inside viewport`, after.inView, after);
      check(`${width} ${mode} cat: button one line, no overflow, tile height same`, h0[1] && after.h === h0[0], h0);
      if (mode === 'logged' && width === 390) await t.p.screenshot({ path: `${out}/wp1-cat-${width}-notice.png` });
      await btn.click(); await t.p.waitForTimeout(400);
      const rem = await t.p.evaluate(id => { const b = document.querySelector(`[data-bs-wishlist-add="${id}"]`); return { pressed: b.getAttribute('aria-pressed'), count: document.querySelector('[data-bs-wishlist-count]').textContent, ga: window.__ga.filter(x => x === 'add_to_wishlist').length, notice: document.querySelector('[data-bs-wishlist-notice]').innerText }; }, id);
      check(`${width} ${mode} cat: second tap removes, no GA`, rem.pressed === 'false' && rem.count === '1' && rem.ga === 1 && /Прибрано з обраного/.test(rem.notice) && t.server.posts.filter(x => x === 'remove').length === 1, rem);
      await btn.focus(); const focus = await btn.evaluate(b => getComputedStyle(b).outlineStyle + '|' + (document.activeElement === b));
      check(`${width} ${mode} cat: focusable`, /true$/.test(focus), focus);
      check(`${width} ${mode} cat: no page errors`, t.errors.length === 0, t.errors);
      await t.ctx.close();
      // product page
      const q = await page(browser, { file: 'prod.html', width, mode, ids: '' });
      const pid = fs.readFileSync(`${livehtml}/prod.html`, 'utf8').match(/class="bs-btn bs-btn-secondary mt-2" data-bs-wishlist-add="(\d+)"/)[1];
      const pb = q.p.locator(`.bs-btn.mt-2:not(.bs-btn-sm)[data-bs-wishlist-add="${pid}"]`);
      await pb.scrollIntoViewIfNeeded(); await pb.click(); await q.p.waitForTimeout(400);
      const pr = await q.p.evaluate(pid => ({ all: [...document.querySelectorAll(`[data-bs-wishlist-add="${pid}"]`)].map(b => b.getAttribute('aria-pressed')), ga: window.__ga.filter(x => x === 'add_to_wishlist').length, sticky: !!document.querySelector('.bs-sticky-atc') }), pid);
      check(`${width} ${mode} product: add pressed + one GA + sticky bar present`, pr.all.every(x => x === 'true') && pr.ga === 1 && pr.sticky, pr);
      check(`${width} ${mode} product: no page errors`, q.errors.length === 0, q.errors);
      if (mode === 'logged') await pb.screenshot({ path: `${out}/wp1-product-button-${width}.png` });
      await q.ctx.close();
    }
    // favorites page (logged)
    const ids = '1,2,3,4,5';
    const f = await page(browser, { file: 'cat.html', width, mode: 'logged', ids, favorites: FAV.replace(/^[\s\S]*?(<div id="account-wishlist")/, '$1').replace(/<\/body>[\s\S]*$/, '') });
    const lay = await f.p.evaluate(() => { const cards = [...document.querySelectorAll('.bs-fav-card')]; const c = cards.map(c => { const h = c.querySelector('.bs-fav-heart').getBoundingClientRect(); const m = c.querySelector('.bs-fav-card__media').getBoundingClientRect(); const img = c.querySelector('img'); return { heartInMedia: h.right <= m.right + 0.5 && h.bottom <= m.bottom + 0.5 && h.left >= m.left && h.top >= m.top, heartBR: Math.round(m.right - h.right) + ',' + Math.round(m.bottom - h.bottom), imgOpacity: img ? getComputedStyle(img).opacity : null, heartOpacity: getComputedStyle(c.querySelector('.bs-fav-heart')).opacity, w: Math.round(c.getBoundingClientRect().width), btn: c.querySelector('.bs-fav-card__action .btn').textContent.trim(), btnBg: getComputedStyle(c.querySelector('.bs-fav-card__action .btn')).backgroundColor }; }); return { c, overflow: document.documentElement.scrollWidth > innerWidth, cols: new Set(cards.map(x => Math.round(x.getBoundingClientRect().left))).size, label: document.querySelector('[data-bs-wishlist-count-label]').textContent, navCount: document.querySelector('.bs-account-nav__count') && document.querySelector('.bs-account-nav__count').textContent }; });
    check(`${width} favorites: no horizontal overflow`, !lay.overflow, lay.overflow);
    check(`${width} favorites: heart bottom-right on photo (desktop) / row right (mobile)`, width <= 575 ? lay.c.every(x => !x.heartInMedia) : lay.c.every(x => x.heartInMedia && x.heartBR === '8,8'), lay.c.map(x => x.heartBR));
    check(`${width} favorites: out dimmed .45, heart not dimmed`, lay.c[3].imgOpacity === '0.45' && lay.c[3].heartOpacity === '1', [lay.c[3].imgOpacity, lay.c[3].heartOpacity]);
    check(`${width} favorites: buttons/colours`, lay.c[0].btn === 'Купити' && lay.c[0].btnBg === 'rgb(18, 136, 62)' && lay.c[2].btn === 'Передзамовити' && lay.c[2].btnBg === 'rgb(59, 130, 246)' && lay.c[3].btn === 'Немає в наявності', lay.c.map(x => [x.btn, x.btnBg]));
    check(`${width} favorites: columns`, width <= 575 ? lay.cols === 1 : lay.cols >= 2, lay.cols);
    check(`${width} favorites: count label + nav count`, lay.label === '5 товарів' && lay.navCount === '5', [lay.label, lay.navCount]);
    await f.p.screenshot({ path: `${out}/wp1-favorites-${width}.png`, fullPage: true });
    // remove from page: updates count/label
    await f.p.locator('[data-bs-wishlist-remove="2"]').click(); await f.p.waitForTimeout(400);
    const rm = await f.p.evaluate(() => ({ label: document.querySelector('[data-bs-wishlist-count-label]').textContent, nav: document.querySelector('.bs-account-nav__count').textContent, header: document.querySelector('.bs-header__wishlist [data-bs-wishlist-count]').textContent }));
    check(`${width} favorites: remove updates label/nav/header`, rm.label === '4 товари' && rm.nav === '4' && rm.header === '4' && f.server.posts.join() === 'remove,list', [rm, f.server.posts]);
    check(`${width} favorites: no page errors`, f.errors.length === 0, f.errors);
    await f.ctx.close();
    const e = await page(browser, { file: 'cat.html', width, mode: 'logged', ids: '', favorites: FAV_EMPTY.replace(/^[\s\S]*?(<div id="account-wishlist")/, '$1').replace(/<\/body>[\s\S]*$/, '') });
    const em = await e.p.evaluate(() => { const a = document.querySelector('.bs-fav-empty a'); return { text: a.textContent, bg: getComputedStyle(a).backgroundColor }; });
    check(`${width} favorites empty: «До каталогу» blue`, em.text === 'До каталогу' && em.bg === 'rgb(30, 58, 138)', em);
    await e.p.screenshot({ path: `${out}/wp1-favorites-empty-${width}.png` });
    await e.ctx.close();
  }
  await browser.close();
  const bad = results.filter(r => !r.ok);
  for (const r of results) console.log((r.ok ? 'ok   ' : 'FAIL ') + r.n + (r.ok ? '' : '  ' + JSON.stringify(r.extra)));
  console.log(`browser_checks=${results.length} failed=${bad.length}`);
  fs.writeFileSync(`${out}/browser-results.json`, JSON.stringify(results, null, 1));
  process.exit(bad.length ? 1 : 0);
})();
