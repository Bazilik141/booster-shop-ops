// Operational one-use outer capture for I168. No direct DB or private-file IO.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {spawn,spawnSync} from 'node:child_process';
import {assertCompleteCommandReview,assertCompleteCommandInputs} from './GSS_command-ledger47_20261006/source-contract.mjs';

const ROOT='C:\\Users\\14bez\\Downloads\\Booster Shop\\booster-shop-ops';
const RUNNER='work/run-gss-command-phase1-original47_20261006.mjs';
const MANIFEST='diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json';
const RUNNER_SHA='9AD2F2DB6AE51BAB954833E49930EEED8EE71E58F9340F3A0A471BB02401AF94';
const MANIFEST_SHA='FB022E5EA7089378F95D7B0FAC93B063BAA182A72D6BD9DA9886CEA218FADBB5';
const SELF='work/capture-gss-source47-phase1_20261007.mjs';
const DECISION='plans/GSS_owner-decision-D075_temporary-review_20261007.md';
const DESIGN='plans/GSS_foundation-source47-outer-capture_20261007.md';
const REVIEWS=['diagnostics/GSS_foundation-source47-outer-capture_security-review_20261007.json','diagnostics/GSS_foundation-source47-outer-capture_test-review_20261007.json'];
const CAPTURE='diagnostics/GSS_foundation-source47-phase1_outer-capture_20261007.jsonl';
const SUMMARY='diagnostics/GSS_foundation-source47-phase1_outer-summary_20261007.json';
const OUTPUTS=[
  'diagnostics/GSS_foundation-command-phase1-original47_20261006.json',
  'diagnostics/GSS_foundation-command-phase1-original47_20261006.txt',
  'diagnostics/GSS_foundation-command-phase1-original47_failure_20261006.json',
  'diagnostics/GSS_foundation-command-phase1-original47_invocation-reserved_20261006.json'
];
const APPLY_RESERVATION='diagnostics/GSS_foundation-command-apply47_invocation-reserved_20261006.json';
const h=b=>createHash('sha256').update(b).digest('hex').toUpperCase();
function publicBytes(p) {
  const absolute=path.join(ROOT,...p.split('/'));
  assert.equal(fs.realpathSync.native(absolute),absolute);
  const st=fs.lstatSync(absolute,{bigint:true});
  assert(st.isFile()&&!st.isSymbolicLink()&&st.nlink===1n);
  return fs.readFileSync(absolute);
}
function fileState(p) {
  if(!fs.existsSync(path.join(ROOT,...p.split('/'))))return {present:false,sha256:null};
  return {present:true,sha256:h(publicBytes(p))};
}
function postState(p) {
  try {return fileState(p);}
  catch {return {present:null,sha256:null,captureRefused:true,valuesWithheld:true};}
}
function writeLine(fd,row) {
  const b=Buffer.from(JSON.stringify(row)+'\n');let n=0;
  while(n<b.length){const q=fs.writeSync(fd,b,n,b.length-n);assert(Number.isSafeInteger(q)&&q>0&&q<=b.length-n);n+=q;}
  fs.fsyncSync(fd);
}
const ownerDate=utc=>new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Kyiv',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(utc));

async function main() {
  assert.equal(process.argv.length,2);assert.equal(process.cwd(),ROOT);
  assert.equal(h(publicBytes(RUNNER)),RUNNER_SHA);assert.equal(h(publicBytes(MANIFEST)),MANIFEST_SHA);
  const mb=publicBytes(MANIFEST),manifest=JSON.parse(mb);
  const gate=assertCompleteCommandReview(),inputs=assertCompleteCommandInputs(manifest);
  assert.equal(inputs.sourceInputsByteVerified,1819);assert.equal(gate.historicalParents.attempts,2);
  const witnessPath='diagnostics/GSS_foundation-command-gate47-pre-dispatch_20261006.json';
  const witness=JSON.parse(publicBytes(witnessPath)),actualReview=JSON.parse(publicBytes('diagnostics/GSS_foundation-command-complete-source47_claude-review-run_20261006.json'));
  assert.equal(witness.passed,true);assert.equal(witness.cases.length,15);
  assert.equal(witness.manifestSha256,MANIFEST_SHA);assert.equal(witness.bundleSha256,manifest.inputSha256);
  assert.equal(actualReview.preDispatchGate.actualWitnessSha256,h(publicBytes(witnessPath)));
  assert.equal(actualReview.nativePhase1Authorized,true);
  const currentHashes=Object.fromEntries([SELF,DECISION,DESIGN].map(p=>[p,h(publicBytes(p))]));
  const names=[];
  for(const p of REVIEWS) {
    const r=JSON.parse(publicBytes(p));
    assert.equal(r.provider,'OpenAI');assert.equal(r.scope,'SOURCE47 OUTER CAPTURE48');
    assert.equal(r.verdict,'Review OK');assert.equal(r.readOnly,true);assert.equal(r.applicationAuthorized,false);
    assert.deepEqual(r.reviewedHashes,currentHashes);names.push(r.agentName);
  }
  assert.deepEqual(names,['/root/foundation_security_review','/root/foundation_test_review']);
  assert(!fs.existsSync(CAPTURE)&&!fs.existsSync(SUMMARY)&&!fs.existsSync(APPLY_RESERVATION));
  const envKeys=['SystemRoot','SYSTEMROOT','WINDIR','PATH','Path','PATHEXT','TEMP','TMP','USERPROFILE','APPDATA','LOCALAPPDATA','USERNAME','USER'];
  const childEnv=Object.fromEntries(envKeys.filter(k=>typeof process.env[k]==='string').map(k=>[k,process.env[k]]));
  const before=Object.fromEntries(OUTPUTS.map(p=>[p,fileState(p)]));
  assert(Object.values(before).every(r=>!r.present));
  const fd=fs.openSync(CAPTURE,'wx');
  const startUtc=new Date().toISOString(),startNs=process.hrtime.bigint();
  const start={event:'begin',scope:'One-use source47 Phase1 outer capture;not application authority',actualStartUtc:startUtc,ownerDate:ownerDate(startUtc),ownerTimezone:'Europe/Kyiv',outerBudgetMs:600000,runnerSha256:RUNNER_SHA,sourceManifestSha256:MANIFEST_SHA,sourceInputsVerified:1819,reviewedHashes:currentHashes,reviewers:names,before,automaticRetry:false,applicationAuthorized:false,valuesWithheld:true};
  try {
    writeLine(fd,start);
    // Recheck immediately before dispatch, after the durable begin record.
    for(const p of [...OUTPUTS,APPLY_RESERVATION])assert(!fs.existsSync(p));
    let processResult;
    try {
      processResult=await new Promise(resolve=>{
        const child=spawn(process.execPath,[RUNNER],{cwd:ROOT,env:childEnv,shell:false,windowsHide:true});
        let out=Buffer.alloc(0),err=Buffer.alloc(0),spawnError=false,timedOut=false,outputOverflow=false,treeTermination=null,settled=false,closureTimer;
        const finish=(exitCode,signal,closeObserved)=>{
          if(settled)return;settled=true;clearTimeout(timer);clearTimeout(closureTimer);
          if(!closeObserved){child.stdout.destroy();child.stderr.destroy();child.unref();}
          resolve({exitCode,signal,spawnError,timedOut,outputOverflow,treeTermination,closeObserved,runnerPid:Number.isSafeInteger(child.pid)?child.pid:null,stdout:{bytes:out.length,sha256:h(out)},stderr:{bytes:err.length,sha256:h(err)}});
        };
        const stopTree=()=>{
          if(treeTermination!==null)return;
          treeTermination={attempted:false,exitCode:null,signal:null,errorPresent:false,fallbackKill:false};
          try {
            if(Number.isSafeInteger(child.pid)&&child.pid>0){
              treeTermination.attempted=true;
              const k=spawnSync('C:\\Windows\\System32\\taskkill.exe',['/PID',String(child.pid),'/T','/F'],{windowsHide:true,shell:false,encoding:'utf8',timeout:15000});
              treeTermination={...treeTermination,exitCode:k.status,signal:k.signal,errorPresent:!!k.error};
              if(k.status!==0||k.error)treeTermination.fallbackKill=child.kill();
            }
          } catch {treeTermination.errorPresent=true;try{treeTermination.fallbackKill=child.kill();}catch{}}
          // If closure remains unknown, persist that fact and reject success.
          // This bounds capture recovery; it never licenses another invocation.
          closureTimer=setTimeout(()=>finish(null,null,false),15000);
        };
        const timer=setTimeout(()=>{timedOut=true;stopTree();},600000);
        const collect=(kind,b)=>{
          if(outputOverflow)return;
          if(out.length+err.length+b.length>1024*1024){outputOverflow=true;stopTree();return;}
          if(kind==='stdout')out=Buffer.concat([out,b]);else err=Buffer.concat([err,b]);
        };
        child.stdout.on('data',b=>collect('stdout',b));child.stderr.on('data',b=>collect('stderr',b));
        child.on('error',()=>{spawnError=true;});
        child.on('close',(exitCode,signal)=>finish(exitCode,signal,true));
      });
    } catch {
      processResult={exitCode:null,signal:null,spawnError:true,timedOut:false,outputOverflow:false,treeTermination:null,closeObserved:false,valuesWithheld:true};
    }
    const endUtc=new Date().toISOString();
    const after=Object.fromEntries(OUTPUTS.map(p=>[p,postState(p)])),applicationReservation=postState(APPLY_RESERVATION);
    const artifactInspectionRefused=[...Object.values(after),applicationReservation].some(r=>r.captureRefused===true);
    const end={event:'end',actualEndUtc:endUtc,ownerDate:ownerDate(endUtc),elapsedMs:Number(process.hrtime.bigint()-startNs)/1e6,...processResult,after,applicationReservation,artifactInspectionRefused,automaticRetry:false,applicationAuthorized:false,valuesWithheld:true};
    writeLine(fd,end);
    const summary={...start,...end,capturePath:CAPTURE,nativeReceiptVerified:false};
    fs.writeFileSync(SUMMARY,JSON.stringify(summary,null,2)+'\n',{flag:'wx'});
    console.log(JSON.stringify({captureComplete:true,exitCode:processResult.exitCode,elapsedMs:end.elapsedMs,successArtifact:after[OUTPUTS[0]].present,reservation:after[OUTPUTS[3]].present,failure:after[OUTPUTS[2]].present,automaticRetry:false,applicationAuthorized:false}));
    if(processResult.exitCode!==0||!processResult.closeObserved||processResult.spawnError||processResult.timedOut||processResult.outputOverflow||artifactInspectionRefused)process.exitCode=1;
  } finally { fs.closeSync(fd); }
}
main().catch(()=>{console.error(JSON.stringify({captureRefused:true,automaticRetry:false,applicationAuthorized:false,valuesWithheld:true}));process.exitCode=1;});
