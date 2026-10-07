// D075 one-use outer. Public evidence only; secrets/DB are confined to fixed child.
import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';import {spawn,spawnSync} from 'node:child_process';
import {assertCompleteCommandReview,assertCompleteCommandInputs} from './GSS_command-ledger47_20261006/source-contract.mjs';
import {readPublicBytes,currentJournalLineage,classifyJournalLineage} from './GSS_command-ledger47_20261006/historical-parent-gate.mjs';
import {acceptedPeer} from './GSS_command-admission48_20261007/peer-metadata.mjs';
const ROOT='C:\\Users\\14bez\\Downloads\\Booster Shop\\booster-shop-ops';
const SELF='work/capture-gss-attempt3-adapter48_20261007.mjs',CONFIG='work/GSS_command-attempt3-outer48_20261007.json';
const BASE='diagnostics/GSS_foundation-command-adapter48-attempt3',COMPLETE='diagnostics/GSS_foundation-command-adapter48-complete';
const OLD='diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json';
const COMPLETE47='FB022E5EA7089378F95D7B0FAC93B063BAA182A72D6BD9DA9886CEA218FADBB5';
const PHASE1='A790444A610AF26C9E3A5B3DEB7863DC5CDBBD633A4107DE3D4FE02A31212906';
const read=readPublicBytes,h=b=>createHash('sha256').update(b).digest('hex').toUpperCase(),json=p=>JSON.parse(read(p));
const absolute=p=>path.join(ROOT,...p.split('/'));
const ownerDate=utc=>new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Kyiv',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(utc));
function state(p){if(!fs.existsSync(absolute(p)))return {present:false,sha256:null};return {present:true,sha256:h(read(p))};}
function post(p){try{return state(p);}catch{return {present:null,sha256:null,captureRefused:true,valuesWithheld:true};}}
function line(fd,row){const b=Buffer.from(JSON.stringify(row)+'\n');let n=0;while(n<b.length){const q=fs.writeSync(fd,b,n,b.length-n);assert(Number.isSafeInteger(q)&&q>0&&q<=b.length-n);n+=q;}fs.fsyncSync(fd);}
function pins(base,isApp,adapterHash){
  const b=read(base+'_manifest_20261007.json'),m=JSON.parse(b),mh=h(b),seen=new Set();
  assert.equal(m.scope,isApp?'SINGLE APPLICATION ATTEMPT3 ADAPTER48':'COMPLETE ADMISSION ADAPTER48');assert.equal(m.applicationAuthorized,isApp);
  assert.equal(m.complete47ManifestSha256,COMPLETE47);assert.equal(m.secretPatternHits,0);assert.equal(h(read(base+'_packet_20261007.txt')),m.inputSha256);
  if(isApp)assert.equal(read(base+'_reviewed_20261007.txt').toString().trim(),m.inputSha256);
  for(const r of m.inputs){assert(!seen.has(r.path));seen.add(r.path);assert.equal(h(read(r.path)),r.sha256);}
  for(const role of ['security','test'])assert(acceptedPeer(json(base+'_'+role+'-review_20261007.json'),{
    agentName:'/root/foundation_'+role+'_review',scope:m.scope,bundleSha256:m.inputSha256,sourceManifestSha256:mh,complete47ManifestSha256:COMPLETE47,
    adapterManifestSha256:isApp?adapterHash:null,freshPhase1Sha256:isApp?PHASE1:null,attemptRef:isApp?m.attemptRef:null,applicationAuthorized:isApp,
    scopeHeading:isApp?'## Application scope: SINGLE APPLICATION ATTEMPT3 ADAPTER48':'## Review scope: COMPLETE ADMISSION ADAPTER48'}));
  return {m,mh,seen};
}
function safeResult(out){
  // Only the exact known active-success object is released, never arbitrary stdout.
  const expected={state:'active_exact',fileAction:'retain',admissionEligible:true,automaticRetry:false,ledgerAction:'retain_all',existingSecretsAction:'unchanged'};
  try{assert.deepEqual(JSON.parse(out.toString('utf8')),expected);return expected;}catch{return null;}
}
async function main(){
  assert.equal(process.argv.length,2);assert.equal(process.execArgv.length,0);assert.equal(process.cwd(),ROOT);
  assert.equal(h(read(OLD)),COMPLETE47);const old=assertCompleteCommandReview();assert.equal(old.historicalParents.attempts,2);
  assert.equal(assertCompleteCommandInputs(json(OLD)).sourceInputsByteVerified,1819);
  const c=pins(COMPLETE,false,null),a=pins(BASE,true,c.mh),m=a.m;
  for(const p of [SELF,CONFIG])assert(a.seen.has(p));
  assert.equal(m.adapterManifestSha256,c.mh);assert.equal(m.freshPhase1Sha256,PHASE1);assert.equal(m.beforeAttempts,2);assert.equal(m.maximumAttempts,3);
  const prefix=read('work/GSS_command-ledger47_20261006/two-attempt-prefix.jsonl');
  assert.equal(classifyJournalLineage(Buffer.concat([prefix,Buffer.from(JSON.stringify({attempt:3,attemptRef:m.attemptRef})+'\n')]),prefix).attempt3Ref,m.attemptRef);
  const config=json(CONFIG);assert.deepEqual(config,m.actualOuterConfig);
  assert.equal(config.outerBudgetMs,600000);assert.equal(config.shorterWholeRunWatchdog,false);
  assert.equal(config.terminationMs,15000);assert.equal(config.closureRecoveryMs,15000);assert.equal(config.maximumOutputBytes,1048576);
  const command=config.actualNativeCommand;
  assert.equal(command.executable,process.execPath);assert.deepEqual(command.argv,['work/GSS_command-admission48_20261007/maintenance.mjs','apply']);
  assert.equal(command.cwd,ROOT);assert.equal(command.shell,false);assert.equal(command.windowsHide,true);assert.deepEqual(command.nodeFlags,[]);
  assert.equal(config.capturePath,BASE+'_outer-capture_20261007.jsonl');assert.equal(config.summaryPath,BASE+'_outer-summary_20261007.json');
  assert.deepEqual(config.outputsMustBeAbsent,['diagnostics/GSS_foundation-command-apply48_invocation-reserved_20261007.json','diagnostics/GSS_foundation-command-apply47_invocation-reserved_20261006.json','diagnostics/GSS_foundation-command-refusal47-attempt3_20261006.json','diagnostics/GSS_foundation-command-deparse-refusal29-3_20261005.json']);
  assert.deepEqual(config.privateExistenceOnly,['gss/.local/command-ledger/current.json','gss/.local/command-ledger/next.json']);
  assert.equal(config.journalPath,'diagnostics/GSS_foundation-command-deparse-attempts29_20261005.jsonl');
  const absent=[config.capturePath,config.summaryPath,...config.outputsMustBeAbsent,...config.privateExistenceOnly];
  for(const p of absent)assert(!fs.existsSync(absolute(p)));assert.equal(currentJournalLineage().attempts,2);
  const envKeys=['SystemRoot','SYSTEMROOT','WINDIR','PATH','Path','PATHEXT','TEMP','TMP','USERPROFILE','APPDATA','LOCALAPPDATA','USERNAME','USER'];
  assert.deepEqual(command.environmentAllowlist,envKeys);
  const env=Object.fromEntries(envKeys.filter(k=>typeof process.env[k]==='string').map(k=>[k,process.env[k]]));
  const before=Object.fromEntries(config.outputsMustBeAbsent.map(p=>[p,state(p)])),journalBefore=state(config.journalPath);
  const fd=fs.openSync(absolute(config.capturePath),'wx'),startUtc=new Date().toISOString(),startNs=process.hrtime.bigint();
  const begin={event:'begin',scope:m.scope,actualStartUtc:startUtc,ownerDate:ownerDate(startUtc),ownerTimezone:'Europe/Kyiv',actualOuterConfig:config,applicationManifestSha256:a.mh,adapterManifestSha256:c.mh,complete47ManifestSha256:COMPLETE47,freshPhase1Sha256:PHASE1,attemptRef:m.attemptRef,before,journalBefore,automaticRetry:false,productApproval:false,valuesWithheld:true};
  try{
    line(fd,begin);
    for(const p of [...config.outputsMustBeAbsent,...config.privateExistenceOnly])assert(!fs.existsSync(absolute(p)));
    assert.equal(currentJournalLineage().attempts,2);
    let result;
    try{result=await new Promise(resolve=>{
      const child=spawn(command.executable,command.argv,{cwd:ROOT,env,shell:false,windowsHide:true});
      let out=Buffer.alloc(0),err=Buffer.alloc(0),spawnError=false,timedOut=false,outputOverflow=false,treeTermination=null,settled=false,closureTimer;
      const finish=(exitCode,signal,closeObserved)=>{
        if(settled)return;settled=true;clearTimeout(timer);clearTimeout(closureTimer);
        if(!closeObserved){child.stdout.destroy();child.stderr.destroy();child.unref();}
        resolve({exitCode,signal,spawnError,timedOut,outputOverflow,treeTermination,closeObserved,runnerPid:Number.isSafeInteger(child.pid)?child.pid:null,
          stdout:{bytes:out.length,sha256:h(out)},stderr:{bytes:err.length,sha256:h(err)},safeActiveResult:safeResult(out)});
      };
      const stop=()=>{
        if(treeTermination!==null)return;treeTermination={attempted:false,exitCode:null,signal:null,errorPresent:false,fallbackKill:false};
        try{if(Number.isSafeInteger(child.pid)&&child.pid>0){treeTermination.attempted=true;const k=spawnSync('C:\\Windows\\System32\\taskkill.exe',['/PID',String(child.pid),'/T','/F'],{windowsHide:true,shell:false,encoding:'utf8',timeout:config.terminationMs});treeTermination={...treeTermination,exitCode:k.status,signal:k.signal,errorPresent:!!k.error};if(k.status!==0||k.error)treeTermination.fallbackKill=child.kill();}}
        catch{treeTermination.errorPresent=true;try{treeTermination.fallbackKill=child.kill();}catch{}}
        closureTimer=setTimeout(()=>finish(null,null,false),config.closureRecoveryMs);
      };
      const timer=setTimeout(()=>{timedOut=true;stop();},config.outerBudgetMs);
      const collect=(kind,b)=>{if(outputOverflow)return;if(out.length+err.length+b.length>config.maximumOutputBytes){outputOverflow=true;stop();return;}if(kind==='stdout')out=Buffer.concat([out,b]);else err=Buffer.concat([err,b]);};
      child.stdout.on('data',b=>collect('stdout',b));child.stderr.on('data',b=>collect('stderr',b));child.on('error',()=>{spawnError=true;});child.on('close',(code,signal)=>finish(code,signal,true));
    });}catch{result={exitCode:null,signal:null,spawnError:true,timedOut:false,outputOverflow:false,treeTermination:null,closeObserved:false,safeActiveResult:null,valuesWithheld:true};}
    const endUtc=new Date().toISOString(),after=Object.fromEntries(config.outputsMustBeAbsent.map(p=>[p,post(p)])),journalAfter=post(config.journalPath);
    let lineage=null;try{lineage=currentJournalLineage();}catch{}
    const privatePresence=Object.fromEntries(config.privateExistenceOnly.map(p=>[p,fs.existsSync(absolute(p))]));
    const artifactInspectionRefused=[...Object.values(after),journalAfter].some(r=>r.captureRefused===true)||lineage===null;
    const passed=result.exitCode===0&&result.closeObserved&&!result.spawnError&&!result.timedOut&&!result.outputOverflow&&!artifactInspectionRefused&&result.safeActiveResult!==null&&
      lineage?.attempts===3&&lineage.attempt3Ref===m.attemptRef&&after[config.outputsMustBeAbsent[0]].present===true&&after[config.outputsMustBeAbsent[1]].present===false&&after[config.outputsMustBeAbsent[2]].present===false&&after[config.outputsMustBeAbsent[3]].present===false&&privatePresence[config.privateExistenceOnly[0]]===true&&privatePresence[config.privateExistenceOnly[1]]===false;
    const end={event:'end',actualEndUtc:endUtc,ownerDate:ownerDate(endUtc),elapsedMs:Number(process.hrtime.bigint()-startNs)/1e6,...result,after,journalAfter,lineage,privatePresence,artifactInspectionRefused,passed,automaticRetry:false,productApproval:false,valuesWithheld:true};
    line(fd,end);fs.writeFileSync(absolute(config.summaryPath),JSON.stringify({...begin,...end,capturePath:config.capturePath},null,2)+'\n',{flag:'wx'});
    console.log(JSON.stringify({captureComplete:true,passed,exitCode:result.exitCode,elapsedMs:end.elapsedMs,journalAttempts:lineage?.attempts??null,automaticRetry:false,productApproval:false,valuesWithheld:true}));if(!passed)process.exitCode=1;
  }finally{fs.closeSync(fd);}
}
main().catch(()=>{console.error(JSON.stringify({captureRefused:true,automaticRetry:false,productApproval:false,valuesWithheld:true}));process.exitCode=1;});
