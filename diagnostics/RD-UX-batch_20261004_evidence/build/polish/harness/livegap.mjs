const ev = `(async () => { const q = s => document.querySelector(s); const card = q('.bs-cat-header'); const list = q('#product-list'); const img = list.querySelector('img'); await img.decode().catch(()=>{});
 const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; const x = c.getContext('2d'); x.drawImage(img, 0, 0); let firstRow = -1;
 try { const d = x.getImageData(0, 0, c.width, c.height).data; outer: for (let y = 0; y < c.height; y++) for (let i = 0; i < c.width; i++) { const k = (y * c.width + i) * 4; if (d[k+3] > 20 && (d[k] < 235 || d[k+1] < 235 || d[k+2] < 235)) { firstRow = y; break outer; } } } catch (e) { firstRow = 'tainted'; }
 const ib = img.getBoundingClientRect(); const fit = getComputedStyle(img).objectFit; const scale = Math.min(ib.width / img.naturalWidth, ib.height / img.naturalHeight); const offY = fit === 'contain' ? (ib.height - img.naturalHeight * scale) / 2 : 0;
 const cb = card.getBoundingClientRect();
 return { iw: innerWidth, cardBottom: Math.round(cb.bottom), boxGap: Math.round(ib.top - cb.bottom), img: [Math.round(ib.width), Math.round(ib.height)], fit, firstRow, visualGap: typeof firstRow === 'number' ? Math.round(ib.top + offY + firstRow * scale - cb.bottom) : null, src: img.currentSrc.slice(-60) }; })()`;
const jobs = [];
for (const u of (process.env.URLS||'').split(' '))
  for (const w of (process.env.WS||'400,1440').split(',').map(Number)) jobs.push({ url: u, w, h: 900, loadWait: 3500, evalOut: ev });
process.stdout.write(JSON.stringify(jobs));
