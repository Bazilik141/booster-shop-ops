// Actual application outer source with fake process/filesystem only. No native IO.
import fs from 'node:fs';import path from 'node:path';import vm from 'node:vm';import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';import {EventEmitter} from 'node:events';
import {acceptedPeer} from './GSS_command-admission48_20261007/peer-metadata.mjs';
import {classifyJournalLineage} from './GSS_command-ledger47_20261006/historical-parent-gate.mjs';
const ROOT=process.cwd(),SELF='work/capture-gss-attempt3-adapter48_20261007.mjs',CONFIG='work/GSS_command-attempt3-outer48_20261007.json';
const COMPLETE='diagnostics/GSS_foundation-command-adapter48-complete',BASE='diagnostics/GSS_foundation-command-adapter48-attempt3';
const old='diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json';
const raw=fs.readFileSync(SELF,'utf8'),h=b=>createHash('sha256').update(b).digest('hex').toUpperCase();
const bytes=x=>Buffer.from(JSON.stringify(x,null,2)+'\n'),original=JSON.parse(fs.readFileSync(COMPLETE+'_manifest_20261007.json'));
const files=[SELF,CONFIG,old,'work/GSS_command-ledger47_20261006/two-attempt-prefix.jsonl',...original.inputs.map(r=>r.path),...['manifest_20261007.json','packet_20261007.txt','security-review_20261007.json','test-review_20261007.json'].map(s=>COMPLETE+'_'+s)];
const publicBase=new Map([...new Set(files)].map(p=>[p,fs.readFileSync(p)]));
const body=raw.slice(0,raw.lastIndexOf('main().catch(')).replace(/^import .*;\r?\n/gm,'')+'\nglobalThis.entry=main;';assert(!/^import /m.test(body));
const rows=[];
for(const scenario of ['success','native_failure','old_reservation','review_mismatch','wrong_scope','wrong_budget','wrong_command','private_exists','post_inspection_failure','unknown_closure','output_overflow','secret_output','short_writes','begin_fsync_failure']){
  const map=new Map(publicBase),fds=new Map(),messages=[],timers=new Map();let nextFd=1,timerId=0,spawns=0,ended=false,unref=false;
  const config=JSON.parse(map.get(CONFIG)),attemptRef='e1f82d8d-7d09-442d-8f76-6368dff21212'; // Fake only.
  if(scenario==='wrong_budget')config.outerBudgetMs=299999;if(scenario==='wrong_command')config.actualNativeCommand.argv[1]='rotate';map.set(CONFIG,bytes(config));
  const adapterHash=h(map.get(COMPLETE+'_manifest_20261007.json')),packet=Buffer.from('FAKE distinct application scope only'),bundle=h(packet);
  const m={scope:'SINGLE APPLICATION ATTEMPT3 ADAPTER48',applicationAuthorized:true,secretPatternHits:0,complete47ManifestSha256:'FB022E5EA7089378F95D7B0FAC93B063BAA182A72D6BD9DA9886CEA218FADBB5',freshPhase1Sha256:'A790444A610AF26C9E3A5B3DEB7863DC5CDBBD633A4107DE3D4FE02A31212906',adapterManifestSha256:adapterHash,inputSha256:bundle,beforeAttempts:2,maximumAttempts:3,attemptRef,actualOuterConfig:config,inputs:files.filter((p,i,a)=>a.indexOf(p)===i).map(p=>({path:p,sha256:h(map.get(p))}))};
  map.set(BASE+'_packet_20261007.txt',packet);map.set(BASE+'_manifest_20261007.json',bytes(m));map.set(BASE+'_reviewed_20261007.txt',Buffer.from(bundle+'\n'));const mh=h(map.get(BASE+'_manifest_20261007.json'));
  for(const role of ['security','test']){const r={provider:'OpenAI',transport:'collaboration',agentName:'/root/foundation_'+role+'_review',readOnly:true,completed:true,provenance:'actual subagent final response',scope:m.scope,bundleSha256:bundle,sourceManifestSha256:mh,complete47ManifestSha256:m.complete47ManifestSha256,adapterManifestSha256:adapterHash,freshPhase1Sha256:m.freshPhase1Sha256,attemptRef,applicationAuthorized:true,productApproval:false,result:'## Verdict: Review OK\n## Application scope: SINGLE APPLICATION ATTEMPT3 ADAPTER48\n'};if(scenario==='review_mismatch')r.bundleSha256='0'.repeat(64);if(scenario==='wrong_scope')r.result='## Verdict: Review OK\n## Review scope: COMPLETE ADMISSION ADAPTER48\n';map.set(BASE+'_'+role+'-review_20261007.json',bytes(r));}
  const journal=config.journalPath;map.set(journal,Buffer.from(map.get('work/GSS_command-ledger47_20261006/two-attempt-prefix.jsonl')));
  if(scenario==='old_reservation')map.set(config.outputsMustBeAbsent[1],Buffer.from('{}'));if(scenario==='private_exists')map.set(config.privateExistenceOnly[0],Buffer.from('PRIVATE-SENTINEL'));
  const relative=p=>path.isAbsolute(p)?path.relative(ROOT,p).replaceAll('\\','/'):p;
  const read=p=>{if(ended&&scenario==='post_inspection_failure'&&p===config.outputsMustBeAbsent[0])throw Error('private sentinel');assert(map.has(p),p);return map.get(p);};
  const fakeFs={existsSync:p=>map.has(relative(p)),openSync:(p,flags)=>{p=relative(p);assert.equal(flags,'wx');assert(!map.has(p));map.set(p,Buffer.alloc(0));fds.set(nextFd,p);return nextFd++;},
    writeSync:(fd,b,o,l)=>{const n=scenario==='short_writes'?Math.min(7,l):l,p=fds.get(fd);map.set(p,Buffer.concat([map.get(p),b.subarray(o,o+n)]));return n;},
    fsyncSync:()=>{if(!ended&&scenario==='begin_fsync_failure')throw Error('private sentinel');},closeSync:fd=>fds.delete(fd),writeFileSync:(p,b,opts)=>{p=relative(p);assert.equal(opts.flag,'wx');assert(!map.has(p));map.set(p,Buffer.from(b));}};
  const fakeTimers=(fn,ms)=>{const id=++timerId;timers.set(id,true);if(['unknown_closure','output_overflow'].includes(scenario))setImmediate(()=>{if(timers.get(id))fn();});return id;};
  const spawnFake=(exe,args,opts)=>{assert.equal(exe,process.execPath);assert.equal(JSON.stringify(args),JSON.stringify(config.actualNativeCommand.argv));assert.equal(opts.shell,false);spawns++;
    const child=new EventEmitter();child.pid=45678;child.stdout=new EventEmitter();child.stderr=new EventEmitter();child.stdout.destroy=child.stderr.destroy=()=>{};child.unref=()=>{unref=true;};child.kill=()=>false;
    if(scenario!=='unknown_closure')queueMicrotask(()=>{ended=true;map.set(config.outputsMustBeAbsent[0],Buffer.from('{}'));map.set(journal,Buffer.concat([map.get(journal),Buffer.from(JSON.stringify({attempt:3,attemptRef})+'\n')]));map.set(config.privateExistenceOnly[0],Buffer.from('PRIVATE-SENTINEL'));
      const success={state:'active_exact',fileAction:'retain',admissionEligible:true,automaticRetry:false,ledgerAction:'retain_all',existingSecretsAction:'unchanged'};
      const out=scenario==='secret_output'?'api_key: PRIVATE-SENTINEL':scenario==='native_failure'?'{"state":"refused"}':JSON.stringify(success)+'\n';
      if(scenario==='output_overflow')child.stdout.emit('data',Buffer.alloc(config.maximumOutputBytes+1));else child.stdout.emit('data',Buffer.from(out));
      if(scenario!=='output_overflow')child.emit('close',scenario==='native_failure'?1:0,null);
    });return child;
  };
  const fakeProcess={argv:['node',SELF],execArgv:[],execPath:process.execPath,cwd:()=>ROOT,env:{SystemRoot:'C:\\Windows'},hrtime:process.hrtime,exitCode:0};
  const context=vm.createContext({fs:fakeFs,path,assert,createHash,spawn:spawnFake,spawnSync:()=>{throw Error('private sentinel');},process:fakeProcess,Buffer,Date,Intl,setTimeout:fakeTimers,clearTimeout:id=>timers.delete(id),console:{log:s=>messages.push(s)},readPublicBytes:read,currentJournalLineage:()=>classifyJournalLineage(map.get(journal),map.get('work/GSS_command-ledger47_20261006/two-attempt-prefix.jsonl')),classifyJournalLineage,acceptedPeer,assertCompleteCommandReview:()=>({historicalParents:{attempts:2}}),assertCompleteCommandInputs:()=>({sourceInputsByteVerified:1819})});
  new vm.Script(body).runInContext(context);let refused=false;try{await context.entry();}catch(e){if(scenario==='success')throw e;refused=true;}
  const events=map.has(config.capturePath)?map.get(config.capturePath).toString().trim().split('\n').filter(Boolean).map(s=>JSON.parse(s)):[];
  if(['old_reservation','review_mismatch','wrong_scope','wrong_budget','wrong_command','private_exists','begin_fsync_failure'].includes(scenario)){assert.equal(spawns,0);assert(refused);}
  else{assert.equal(spawns,1,scenario);assert(!refused,scenario);assert.equal(events.length,2,scenario);const end=events[1];assert.equal(end.passed,scenario==='success'||scenario==='short_writes',scenario);assert.equal(end.automaticRetry,false);assert.equal(end.productApproval,false);if(['unknown_closure','output_overflow'].includes(scenario)){assert.equal(end.closeObserved,false);assert(unref);}if(scenario==='post_inspection_failure')assert(end.artifactInspectionRefused);if(scenario==='secret_output')assert.equal(end.safeActiveResult,null);}
  assert(!JSON.stringify(events).includes('PRIVATE-SENTINEL'));assert(!messages.join('').includes('PRIVATE-SENTINEL'));rows.push({scenario,passed:true,mockDispatches:spawns});
}
console.log(JSON.stringify({scope:'Actual adapter48 application outer against fake IO/process only',passed:true,cases:rows,nativeInvocations:0,privateReads:0,connections:0}));
