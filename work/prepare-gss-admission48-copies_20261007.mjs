// Source-only exact copy/declared import+CLI translation. No native effect.
import fs from 'node:fs';import assert from 'node:assert/strict';import {createHash} from 'node:crypto';
const src='work/GSS_command-ledger47_20261006/',dst='work/GSS_command-admission48_20261007/';
const translated=[];
for(const name of ['maintenance.mjs','command-private-files.mjs']){
  const original=fs.readFileSync(src+name,'utf8');let out=original;
  const rewrites=[];
  for(const match of original.matchAll(/from '(\.\/[^']+)'/g)){
    const p=match[1];if(p==='./source-contract.mjs'||name==='maintenance.mjs'&&['./command-private-files.mjs','./apply-invocation.mjs'].includes(p))continue;
    if(rewrites.some(r=>r.from===p))continue;rewrites.push({from:p,to:'../GSS_command-ledger47_20261006/'+p.slice(2)});
  }
  for(const r of rewrites)out=out.split("from '"+r.from+"'").join("from '"+r.to+"'");
  if(name==='maintenance.mjs'){
    const old="!['initialize','apply','reconcile','contain','rotate'].includes(process.argv[2])";
    assert.equal(out.split(old).length,2);out=out.replace(old,"process.argv[2]!=='apply'");
  }else{
    const old="new URL('./private-command-files.ps1',import.meta.url)";
    assert.equal(out.split(old).length,2);out=out.replace(old,"new URL('../GSS_command-ledger47_20261006/private-command-files.ps1',import.meta.url)");
  }
  fs.writeFileSync(dst+name,out,{flag:'wx'});
  translated.push({name,sourceSha256:createHash('sha256').update(original).digest('hex').toUpperCase(),copiedSha256:createHash('sha256').update(out).digest('hex').toUpperCase(),rewrites,sqlOrRuntimeBehaviorChanged:false});
}
console.log(JSON.stringify({passed:true,scope:'Two exact source copies with declared import/CLI translations only',translated,nativeInvocations:0,privateReads:0}));
