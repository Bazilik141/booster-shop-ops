const [port, outdir, prefix] = process.argv.slice(2);
const ev = `(() => { const p = document.getElementById('bs-ff-panel'); const t = document.getElementById('bs-ff-toggle'); const r = document.querySelector('.bs-ff-tools').getBoundingClientRect(); const pr = p.getBoundingClientRect(); return { w: innerWidth, sw: document.documentElement.scrollWidth, expanded: t.getAttribute('aria-expanded'), panelTop: Math.round(pr.top), toolsBottom: Math.round(r.bottom), panelBelowRow2: pr.top >= r.bottom, focusOutline: getComputedStyle(document.activeElement).outlineStyle + ' ' + getComputedStyle(document.activeElement).outlineColor, active: document.activeElement.id || document.activeElement.className }; })()`;
const jobs = [];
for (const w of [320, 390, 768, 991, 1440]) {
  jobs.push({ url: `http://127.0.0.1:${port}/?page=category&filter=12`, w, h: 900, loadWait: 1200, pre: ["document.getElementById('bs-ff-toggle').click(); true"], evalOut: ev, out: `${outdir}/${prefix}_panel_open_${w}.png` });
  jobs.push({ url: `http://127.0.0.1:${port}/?page=category`, w, h: 500, loadWait: 1200, pre: ["document.body.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab'})); document.getElementById('bs-ff-toggle').focus(); true"], evalOut: ev, out: `${outdir}/${prefix}_focus_filter_${w}.png` });
}
process.stdout.write(JSON.stringify(jobs));
