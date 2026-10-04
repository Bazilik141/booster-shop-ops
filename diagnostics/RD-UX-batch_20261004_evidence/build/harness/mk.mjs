// node mk.mjs <port> <outdir> <prefix> <evalJs|-> <query1> [query2 ...]   -> prints jobs JSON for widths 390/768/1440
const [port, out, prefix, ev, ...qs] = process.argv.slice(2);
const jobs = [];
for (const q of qs) for (const w of [390, 768, 1440]) {
  const name = q.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '');
  jobs.push({ url: `http://127.0.0.1:${port}/?${q}`, w, h: 900, out: `${out}/${prefix}_${name}_${w}.png`, full: true, ...(ev !== '-' ? { evalOut: ev } : {}) });
}
process.stdout.write(JSON.stringify(jobs));
