// node pixdiff.mjs <dir> : compares before_X.png vs after_X.png, prints diff pixel count + bbox
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
const dir = process.argv[2];
const pairs = readdirSync(dir).filter(f => f.startsWith('after_')).map(f => f.slice(6)).filter(n => readdirSync(dir).includes('before_' + n));
const html = '<html><body><script>window.cmp=async(a,b)=>{const L=s=>new Promise(r=>{const i=new Image();i.onload=()=>r(i);i.src=s});const [A,B]=await Promise.all([L(a),L(b)]);const w=Math.max(A.width,B.width),h=Math.max(A.height,B.height);const g=i=>{const c=document.createElement("canvas");c.width=w;c.height=h;const x=c.getContext("2d");x.drawImage(i,0,0);return x.getImageData(0,0,w,h).data};const da=g(A),db=g(B);let n=0,x0=w,y0=h,x1=-1,y1=-1;for(let y=0;y<h;y++)for(let x=0;x<w;x++){const k=(y*w+x)*4;if(Math.abs(da[k]-db[k])+Math.abs(da[k+1]-db[k+1])+Math.abs(da[k+2]-db[k+2])>24){n++;if(x<x0)x0=x;if(y<y0)y0=y;if(x>x1)x1=x;if(y>y1)y1=y}}return {n,bbox:n?[x0,y0,x1,y1]:null,size:[A.width,A.height,B.width,B.height]}}</script></body></html>';
const jobs = pairs.map(n => ({ url: 'data:text/html;base64,' + Buffer.from(html).toString('base64'), w: 400, h: 300, loadWait: 200,
  evalOut: `cmp("data:image/png;base64,${readFileSync(dir + '/before_' + n).toString('base64')}","data:image/png;base64,${readFileSync(dir + '/after_' + n).toString('base64')}")`, tag: n }));
writeFileSync(dir + '/../jpix.json', JSON.stringify(jobs));
