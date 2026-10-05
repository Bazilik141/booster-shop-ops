const [port, outdir, prefix] = process.argv.slice(2);
const type = q => `(()=>{const i=document.getElementById('ps-live-search-input');i.focus();i.value=${JSON.stringify(q)};i.dispatchEvent(new Event('input',{bubbles:true}));return true})()`;
const ev = `(() => { const b = document.querySelector('#ps-live-search .bs-ls-state__btn'); const l = document.getElementById('ps-live-search'); if (!b) return { btn: null }; const r = b.getBoundingClientRect(), lr = l.getBoundingClientRect(); return { iw: innerWidth, sw: document.documentElement.scrollWidth, btn: [Math.round(r.left), Math.round(r.right), Math.round(r.width), Math.round(r.height)], list: [Math.round(lr.left), Math.round(lr.right)], listScroll: l.scrollWidth - l.clientWidth, ws: getComputedStyle(b).whiteSpace }; })()`;
const jobs = [];
for (const w of [320, 360, 390, 768, 1440]) jobs.push({ url: `http://127.0.0.1:${port}/?page=category`, w, h: 800, pre: [type('err')], wait: 1200, evalOut: ev, out: `${outdir}/${prefix}_ls_error_${w}.png` });
process.stdout.write(JSON.stringify(jobs));
