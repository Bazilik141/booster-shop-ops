const [port, outdir, prefix] = process.argv.slice(2);
const P = { home: 'page=home&ct=' + encodeURIComponent('Мій кошик - 23460.00₴'), cat: 'page=category', catnosubs: 'page=category&nosubs=1', catfilter: 'page=category&filter=12,21', catlong: 'page=category&longsub=1',
  product: 'page=product', search: 'page=search&search=pokemon', checkout: 'page=checkoutreal' };
const set = { home: [320, 390, 768, 769, 800, 900, 959, 960, 992, 1440], cat: [320, 360, 390, 576, 768, 900, 991, 992, 1440], catnosubs: [390, 768, 991, 992, 1440], catfilter: [390, 768, 992, 1440],
  catlong: [320, 390], product: [390, 900, 1440], search: [390, 900, 1440], checkout: [390, 900, 1440] };
const jobs = [];
for (const k in set) for (const w of set[k]) jobs.push({ url: `http://127.0.0.1:${port}/?${P[k]}`, w, h: 640, loadWait: 1200, out: `${outdir}/${prefix}_${k}_${w}.png` });
process.stdout.write(JSON.stringify(jobs));
