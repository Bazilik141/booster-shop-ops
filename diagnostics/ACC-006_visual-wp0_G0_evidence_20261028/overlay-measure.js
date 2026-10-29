const { chromium } = require('playwright');
const out = process.argv[2];
const pages = ['https://boostershop.website/catalog/Pokemon','https://boostershop.website/catalog/Pokemon/Pokemon-booster-box','https://boostershop.website/','https://boostershop.website/catalog/acsesuary'];
(async () => {
  const browser = await chromium.launch();
  const result = [];
  for (const w of [390, 768, 1280]) {
    const ctx = await browser.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 1 });
    const page = await ctx.newPage();
    for (const url of pages) {
      const resp = await page.goto(url, { waitUntil: 'load', timeout: 60000 }).catch(e => null);
      if (!resp) { result.push({ w, url, error: 'load' }); continue; }
      await page.evaluate(async () => { for (const i of document.querySelectorAll('.bs-pcard__media img')) { i.loading = 'eager'; } window.scrollTo(0, document.body.scrollHeight); });
      await page.waitForTimeout(1500);
      const tiles = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('.bs-pcard')).slice(0, 12).map(card => {
          const m = card.querySelector('.bs-pcard__media'); const img = m && m.querySelector('img');
          const r = m.getBoundingClientRect();
          let d = null;
          if (img && img.naturalWidth) {
            const b = img.getBoundingClientRect();
            const s = Math.min(b.width / img.naturalWidth, b.height / img.naturalHeight);
            const dw = img.naturalWidth * s, dh = img.naturalHeight * s;
            d = { x0: b.left + (b.width - dw) / 2 - r.left, y0: b.top + (b.height - dh) / 2 - r.top, w: dw, h: dh };
          }
          const H = 44, i = 8;
          const hx0 = r.width - i - H, hy0 = r.height - i - H;
          let ov = null;
          if (d) { const ox = Math.max(0, Math.min(d.x0 + d.w, r.width - i) - Math.max(d.x0, hx0)); const oy = Math.max(0, Math.min(d.y0 + d.h, r.height - i) - Math.max(d.y0, hy0)); ov = { ox: Math.round(ox), oy: Math.round(oy), pct: +(ox * oy / (d.w * d.h) * 100).toFixed(1) }; }
          const tl = card.querySelector('.bs-pcard__badge-tl'), tr = card.querySelector('.bs-pcard__badge-tr');
          const badgeBottom = Math.max(tl ? tl.getBoundingClientRect().bottom - r.top : 0, tr ? tr.getBoundingClientRect().bottom - r.top : 0);
          return { name: (card.querySelector('.bs-pcard__title') || {}).textContent.trim().slice(0, 60), kind: m.className.replace('bs-pcard__media', '').trim(), out: card.classList.contains('bs-pcard--out'), tile: [Math.round(r.width), Math.round(r.height)], img: d && [Math.round(d.x0), Math.round(d.y0), Math.round(d.w), Math.round(d.h)], overlap: ov, badgeBottom: Math.round(badgeBottom), heartTop: Math.round(r.height - 52) };
        });
      });
      const colLeft = await page.evaluate(() => { const c = document.querySelector('#column-left'); return c ? Math.round(c.getBoundingClientRect().width) : 0; });
      result.push({ w, url, status: resp.status(), colLeft, tiles });
      // local-only mock heart for screenshots
      await page.evaluate(() => {
        document.querySelectorAll('.bs-pcard__media').forEach(m => {
          const h = document.createElement('span');
          h.style.cssText = 'position:absolute;right:8px;bottom:8px;width:44px;height:44px;border-radius:50%;background:#fff;border:1px solid #E5E7EB;box-shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.05);display:grid;place-items:center;z-index:2';
          h.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1F2937" stroke-width="1.8"><path d="M12 21 3 12C-2 6 6-1 12 6c6-7 14 0 9 6l-9 9"/></svg>';
          m.appendChild(h);
        });
        window.scrollTo(0, 0);
      });
      const first = await page.$('.bs-pcard');
      if (first) {
        await first.scrollIntoViewIfNeeded();
        const slug = url.replace(/https:\/\/boostershop\.website\/?/, '').replace(/\W+/g, '_') || 'home';
        const grid = await page.evaluateHandle(() => document.querySelector('.bs-pcard').closest('[data-bs-media-grid], .row, .swiper, section') || document.querySelector('.bs-pcard').parentElement.parentElement);
        await grid.asElement().screenshot({ path: `${out}/${slug}_${w}.png` }).catch(() => {});
      }
    }
    await ctx.close();
  }
  await browser.close();
  require('fs').writeFileSync(`${out}/overlay.json`, JSON.stringify(result, null, 1));
})();
