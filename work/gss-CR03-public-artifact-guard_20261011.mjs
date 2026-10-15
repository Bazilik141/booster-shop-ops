// Public local output hygiene only. Not a cross-process lock or recovery proof.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
const ROOT='C:\\Users\\14bez\\Downloads\\Booster Shop\\booster-shop-ops';
export function assertCaseUniquePaths(proposed,existing=[]){
  const key=p=>{assert.equal(typeof p,'string');assert(/^(work|plans|diagnostics)\/[A-Za-z0-9_.-]+\.(mjs|md|json|txt)$/i.test(p),'Explicit top-level public output required');return p.toLowerCase();};
  const seen=new Set(existing.map(key));
  for(const p of proposed){const k=key(p);assert(!seen.has(k),'Case-insensitive public output collision: '+p);seen.add(k);}
  return true;
}
export function writePublicArtifact(relative,bytes){
  assert.equal(process.cwd(),ROOT);
  assertCaseUniquePaths([relative]);
  const parent=path.dirname(relative),folder=path.join(ROOT,parent);
  assert.equal(fs.realpathSync.native(folder),folder);
  const names=fs.readdirSync(folder).filter(n=>/^[A-Za-z0-9_.-]+\.(mjs|md|json|txt)$/i.test(n)).map(n=>parent+'/'+n);
  assertCaseUniquePaths([relative],names);
  const target=path.join(ROOT,relative),b=Buffer.from(bytes),fd=fs.openSync(target,'wx');
  try{let offset=0;while(offset<b.length){const written=fs.writeSync(fd,b,offset,b.length-offset);assert(written>0);offset+=written;}fs.fsyncSync(fd);}finally{fs.closeSync(fd);}
  assert(fs.readFileSync(target).equals(b));
}
