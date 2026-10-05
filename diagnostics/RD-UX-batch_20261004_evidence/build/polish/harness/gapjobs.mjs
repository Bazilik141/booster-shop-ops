const [port, ws, extra] = process.argv.slice(2);
const ev = `(() => { const q = s => document.querySelector(s); const card = q('.bs-cat-header'); const list = q('#product-list'); const first = q('#product-list > *'); const img = first && (first.querySelector('.product-thumb, .bs-card, .card') || first);
 const cb = card.getBoundingClientRect(); const lb = list.getBoundingClientRect(); const fb = first.getBoundingClientRect(); const ib = img.getBoundingClientRect(); const cs = getComputedStyle(card); const ls = getComputedStyle(list); const fs = getComputedStyle(first);
 const between = []; let n = card.nextElementSibling; while (n && n !== list && !n.contains(list)) { const b = n.getBoundingClientRect(); between.push(n.tagName + '.' + n.className + ' h=' + Math.round(b.height) + ' mt=' + getComputedStyle(n).marginTop + ' mb=' + getComputedStyle(n).marginBottom); n = n.nextElementSibling; }
 const row = q('.bs-ff-row'), ch = q('.bs-ff-chips'); const rb = row.getBoundingClientRect();
 return { iw: innerWidth, sw: document.documentElement.scrollWidth, cardBottom: Math.round(cb.bottom), cardMB: cs.marginBottom, listTop: Math.round(lb.top), listMT: ls.marginTop, firstTop: Math.round(fb.top), firstPT: fs.paddingTop, firstMT: fs.marginTop, visualTop: Math.round(ib.top), visualGap: Math.round(ib.top - cb.bottom), imgSel: img.className, between, rowH: Math.round(rb.height), rowPB: getComputedStyle(row).paddingBottom, chips: ch ? [Math.round(ch.getBoundingClientRect().width), Math.round(ch.getBoundingClientRect().height), ch.children.length] : null }; })()`;
const jobs = [];
for (const w of ws.split(',').map(Number)) for (const p of ['page=category', 'page=category&nosubs=1', 'page=category&filter=12,21'])
  jobs.push({ url: `http://127.0.0.1:${port}/?${p}${extra || ''}`, w, h: 900, loadWait: 900, evalOut: ev });
process.stdout.write(JSON.stringify(jobs));
