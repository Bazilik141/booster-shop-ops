const [port] = process.argv.slice(2);
const ev = `(() => ({ iw: innerWidth, sw: document.documentElement.scrollWidth, act: Math.round(document.querySelector('.bs-header__actions').getBoundingClientRect().width), cart: Math.round(document.querySelector('#cart .mini-cart-trigger').getBoundingClientRect().width) }))()`;
const jobs = [];
for (const ct of ['Мій кошик - 0.00₴', 'Мій кошик - 4990.00₴', 'Мій кошик - 23460.00₴', 'Мій кошик - 123460.00₴'])
  for (const lg of [0, 1]) for (const w of (process.env.WS||"769,880,900,920,940,960,980,1000,1023,1024,1040,1060,1100").split(",").map(Number))
    jobs.push({ url: `http://127.0.0.1:${port}/?page=home&logged=${lg}&ct=${encodeURIComponent(ct)}`, w, h: 700, loadWait: 500, evalOut: ev, tag: ct + '|' + lg });
process.stdout.write(JSON.stringify(jobs));
