// node chunkproxy.mjs <listenPort> <upstreamPort> <delayMs> : HTML responses are sent in two chunks — up to and including
// the first `<div id="product-list"` line (after the header card), then the rest after delayMs. Other files pass through.
import http from 'node:http';
const [lp, up, delay] = process.argv.slice(2).map(Number);
http.createServer(async (req, res) => {
  const r = await fetch(`http://127.0.0.1:${up}${req.url}`);
  const buf = Buffer.from(await r.arrayBuffer());
  const type = r.headers.get('content-type') || '';
  if (!type.includes('text/html')) { res.writeHead(r.status, { 'content-type': type }); return res.end(buf); }
  const html = buf.toString('utf8'); const cut = html.indexOf('<div id="product-list"');
  const split = cut < 0 ? html.length : html.indexOf('\n', cut) + 1;
  res.writeHead(r.status, { 'content-type': type, 'cache-control': 'no-store' });
  res.write(html.slice(0, split) + '<!--' + ' '.repeat(4096) + '-->'); // padding so the chunk is not held in a parser buffer
  setTimeout(() => res.end(html.slice(split)), delay);
}).listen(lp, '127.0.0.1', () => console.log('proxy', lp, '->', up));
