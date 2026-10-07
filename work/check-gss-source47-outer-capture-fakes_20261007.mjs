// Focused actual-source fault matrix. All filesystem/process/gate effects are fakes.
// No imported project modules, database, secrets, native child or real output writes.
import fs from 'node:fs';import path from 'node:path';import vm from 'node:vm';
import assert from 'node:assert/strict';import {createHash} from 'node:crypto';
import {EventEmitter} from 'node:events';
const root=process.cwd(),self='work/capture-gss-source47-phase1_20261007.mjs',raw=fs.readFileSync(self,'utf8');
assert.equal(raw.split('main().catch(').length,2);
const body=raw.slice(0,raw.lastIndexOf('main().catch(')).replace(/^import .*;\r?\n/gm,'')+'\nglobalThis.entry=main;';
assert(!/^import /m.test(body));
const hash=b=>createHash('sha256').update(b).digest('hex').toUpperCase();
const files=[self,'plans/GSS_owner-decision-D075_temporary-review_20261007.md','plans/GSS_foundation-source47-outer-capture_20261007.md','work/run-gss-command-phase1-original47_20261006.mjs','diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json','diagnostics/GSS_foundation-command-gate47-pre-dispatch_20261006.json','diagnostics/GSS_foundation-command-complete-source47_claude-review-run_20261006.json'];
const base=new Map(files.map(p=>[path.join(root,...p.split('/')),fs.readFileSync(p)]));
const reviewedHashes=Object.fromEntries(files.slice(0,3).map(p=>[p,hash(base.get(path.join(root,...p.split('/'))))]));
const capture=path.join(root,'diagnostics/GSS_foundation-source47-phase1_outer-capture_20261007.jsonl');
const native='diagnostics/GSS_foundation-command-phase1-original47';
const nativePaths=[native+'_20261006.json',native+'_20261006.txt',native+'_failure_20261006.json',native+'_invocation-reserved_20261006.json'].map(p=>path.join(root,...p.split('/')));
const rows=[];
for(const scenario of ['success','native_failure','prior_failure','review_mismatch','post_inspection_failure','termination_throw','termination_nonzero','secret_output','short_writes','begin_fsync_failure']) {
  const map=new Map(base),fdMap=new Map(),messages=[];let nextFd=1,spawns=0,ended=false,unref=false;
  const absolute=p=>path.isAbsolute(p)?p:path.join(root,...p.split('/'));
  for(const [name,file] of [['/root/foundation_security_review','security'],['/root/foundation_test_review','test']]) {
    const record={provider:'OpenAI',scope:'SOURCE47 OUTER CAPTURE48',verdict:'Review OK',readOnly:true,applicationAuthorized:false,reviewedHashes:{...reviewedHashes},agentName:name};
    if(scenario==='review_mismatch')record.reviewedHashes[self]='0'.repeat(64);
    map.set(path.join(root,'diagnostics',`GSS_foundation-source47-outer-capture_${file}-review_20261007.json`),Buffer.from(JSON.stringify(record)));
  }
  if(scenario==='prior_failure')map.set(nativePaths[2],Buffer.from('{}'));
  const fakeFs={
    existsSync:p=>map.has(absolute(p)),
    realpathSync:{native:p=>{if(ended&&scenario==='post_inspection_failure'&&p===nativePaths[3])throw Error('private sentinel');return p;}},
    lstatSync:()=>({isFile:()=>true,isSymbolicLink:()=>false,nlink:1n}),
    readFileSync:p=>{assert(map.has(absolute(p)));return map.get(absolute(p));},
    openSync:(p,flag)=>{assert.equal(flag,'wx');p=absolute(p);assert(!map.has(p));map.set(p,Buffer.alloc(0));fdMap.set(nextFd,p);return nextFd++;},
    writeSync:(fd,b,offset,length)=>{const n=scenario==='short_writes'?Math.min(7,length):length,p=fdMap.get(fd);map.set(p,Buffer.concat([map.get(p),b.subarray(offset,offset+n)]));return n;},
    fsyncSync:()=>{if(scenario==='begin_fsync_failure'&&!ended)throw Error('private sentinel');},
    closeSync:fd=>fdMap.delete(fd),
    writeFileSync:(p,b,opts)=>{assert.equal(opts.flag,'wx');p=absolute(p);assert(!map.has(p));map.set(p,Buffer.from(b));}
  };
  const timers=new Map();let timerId=0;
  const fakeTimers=(fn,ms)=>{const id=++timerId;timers.set(id,true);if(['termination_throw','termination_nonzero'].includes(scenario))setImmediate(()=>{if(timers.get(id))fn();});return id;};
  const fakeSpawn=()=>{
    spawns++;const child=new EventEmitter();child.pid=45678;child.stdout=new EventEmitter();child.stderr=new EventEmitter();child.stdout.destroy=child.stderr.destroy=()=>{};child.unref=()=>{unref=true;};child.kill=()=>false;
    if(!scenario.startsWith('termination_'))queueMicrotask(()=>{
      ended=true;map.set(nativePaths[3],Buffer.from('{}'));
      if(scenario==='native_failure')map.set(nativePaths[2],Buffer.from('{}'));
      else {map.set(nativePaths[0],Buffer.from('{}'));map.set(nativePaths[1],Buffer.from('native transcript'));}
      child.stdout.emit('data',Buffer.from(scenario==='secret_output'?'api_key: private-sentinel-DO-NOT-EXPOSE':'safe summary'));
      child.emit('close',scenario==='native_failure'?1:0,null);
    });
    return child;
  };
  const fakeProcess={argv:['node',self],cwd:()=>root,execPath:process.execPath,env:{SystemRoot:'C:\\Windows'},hrtime:process.hrtime,exitCode:0};
  const context=vm.createContext({fs:fakeFs,path,assert,createHash,spawn:fakeSpawn,spawnSync:()=>{if(scenario==='termination_throw')throw Error('private sentinel');return {status:1,signal:null,error:null};},process:fakeProcess,Buffer,Intl,Date,console:{log:s=>messages.push(s)},setTimeout:fakeTimers,clearTimeout:id=>timers.delete(id),assertCompleteCommandReview:()=>({historicalParents:{attempts:2}}),assertCompleteCommandInputs:()=>({sourceInputsByteVerified:1819})});
  new vm.Script(body,{filename:self}).runInContext(context);
  let refused=false;try{await context.entry();}catch{refused=true;}
  const events=map.has(capture)?map.get(capture).toString().trim().split('\n').filter(Boolean).map(s=>JSON.parse(s)):[];
  if(['prior_failure','review_mismatch','begin_fsync_failure'].includes(scenario)){assert.equal(spawns,0);assert.equal(refused,true);}
  else {
    assert.equal(spawns,1);assert.equal(refused,false);assert.equal(events.length,2);const end=events[1];
    assert.match(events[0].actualStartUtc,/Z$/);assert.match(end.actualEndUtc,/Z$/);assert.equal(end.applicationAuthorized,false);assert.equal(end.automaticRetry,false);
    if(scenario==='post_inspection_failure'){assert.equal(end.exitCode,0);assert.equal(end.artifactInspectionRefused,true);assert.equal(end.after[native+'_invocation-reserved_20261006.json'].captureRefused,true);assert.equal(fakeProcess.exitCode,1);}
    if(scenario.startsWith('termination_')){assert.equal(end.closeObserved,false);assert.equal(end.timedOut,true);assert.equal(fakeProcess.exitCode,1);assert.equal(unref,true);}
    if(scenario==='native_failure'){assert.equal(end.exitCode,1);assert.equal(end.after[native+'_failure_20261006.json'].present,true);assert.equal(fakeProcess.exitCode,1);}
  }
  assert(!JSON.stringify(events).includes('private-sentinel-DO-NOT-EXPOSE'));assert(!messages.join('').includes('private-sentinel-DO-NOT-EXPOSE'));
  rows.push({scenario,passed:true,nativeChildSpawns:0,mockDispatches:spawns});
}
console.log(JSON.stringify({scope:'Actual additive outer source against fake IO/process/gates only',passed:true,sourceSha256:hash(Buffer.from(raw)),cases:rows,nativeConnections:0,privateReads:0,nativeInvocations:0}));
