// Focused actual-source metadata/one-shot matrix; all admission filesystem effects fake.
import fs from 'node:fs';import path from 'node:path';import vm from 'node:vm';
import assert from 'node:assert/strict';import {createHash} from 'node:crypto';
import {acceptedPeer} from './GSS_command-admission48_20261007/peer-metadata.mjs';
import {classifyJournalLineage} from './GSS_command-ledger47_20261006/historical-parent-gate.mjs';
const root=process.cwd(),lane='work/GSS_command-admission48_20261007/',old='work/GSS_command-ledger47_20261006/';
const h=b=>createHash('sha256').update(b).digest('hex').toUpperCase(),rows=[];
// Exact equivalence uses a closed list of allowed translations, not generated expectations.
for(const name of ['maintenance.mjs','command-private-files.mjs']){
  let expected=fs.readFileSync(old+name,'utf8');
  const names=name==='maintenance.mjs'?['command-process-fences.mjs','command-session-fences.mjs','command-ddl-fences.mjs','command-state-inspection.mjs','source-comparison.mjs','inspection-trace.mjs','value-free-refusal.mjs','refusal-diagnostic-writer.mjs']:['command-process-fences.mjs'];
  for(const n of names)expected=expected.split("from './"+n+"'").join("from '../GSS_command-ledger47_20261006/"+n+"'");
  if(name==='maintenance.mjs')expected=expected.replace("!['initialize','apply','reconcile','contain','rotate'].includes(process.argv[2])","process.argv[2]!=='apply'");
  else expected=expected.replace("new URL('./private-command-files.ps1',import.meta.url)","new URL('../GSS_command-ledger47_20261006/private-command-files.ps1',import.meta.url)");
  assert.equal(fs.readFileSync(lane+name,'utf8'),expected);rows.push({case:'exact_copy_'+name,passed:true});
}
const pureExpected={agentName:'/root/foundation_security_review',scope:'SINGLE APPLICATION ATTEMPT3 ADAPTER48',bundleSha256:'A',sourceManifestSha256:'B',complete47ManifestSha256:'C',adapterManifestSha256:'D',freshPhase1Sha256:'E',attemptRef:'F',applicationAuthorized:true,scopeHeading:'## Application scope: SINGLE APPLICATION ATTEMPT3 ADAPTER48'};
const peerBase={...pureExpected,provider:'OpenAI',transport:'collaboration',readOnly:true,completed:true,provenance:'actual subagent final response',productApproval:false,result:'## Verdict: Review OK\n'+pureExpected.scopeHeading+'\n'};
assert(acceptedPeer(peerBase,pureExpected));rows.push({case:'peer_positive',passed:true});
for(const key of ['provider','transport','agentName','scope','bundleSha256','sourceManifestSha256','complete47ManifestSha256','adapterManifestSha256','freshPhase1Sha256','attemptRef','applicationAuthorized','readOnly','completed','provenance','productApproval']){
  const altered={...peerBase,[key]:typeof peerBase[key]==='boolean'?!peerBase[key]:'wrong'};assert(!acceptedPeer(altered,pureExpected));rows.push({case:'peer_wrong_'+key,passed:true});
}
for(const [name,text]of [['generic','## Verdict: Review OK'],['duplicate_verdict',peerBase.result+'## Verdict: Review OK'],['duplicate_scope',peerBase.result+pureExpected.scopeHeading],['changes_requested','## Verdict: Changes requested\n'+pureExpected.scopeHeading],['preface','Preface\n'+peerBase.result]]){assert(!acceptedPeer({...peerBase,result:text},pureExpected));rows.push({case:'peer_'+name,passed:true});}
let touched=false;const getter={...peerBase};Object.defineProperty(getter,'provider',{get(){touched=true;throw Error('sentinel');}});assert(!acceptedPeer(getter,pureExpected));assert(!touched);rows.push({case:'peer_getter_refused',passed:true});

const originalFiles=[
 'work/GSS_command-ledger47_20261006/two-attempt-prefix.jsonl',
 'diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json','diagnostics/GSS_foundation-command-complete-source47_claude-review-run_20261006.json','diagnostics/GSS_foundation-command-complete-source47_review-ready_20261006.txt','diagnostics/GSS_foundation-command-complete-source47_reviewed_20261006.txt','diagnostics/GSS_foundation-command-gate47-pre-dispatch_20261006.json',
 'plans/GSS_owner-decision-D075_temporary-review_20261007.md','plans/GSS_foundation-temporary-attempt3-admission-design48_20261007.md',
 'diagnostics/GSS_foundation-command-phase1-original47_20261006.json','diagnostics/GSS_foundation-command-phase1-original47_20261006.txt','diagnostics/GSS_foundation-command-phase1-original47_invocation-reserved_20261006.json','diagnostics/GSS_foundation-command-phase1-original47_root-receipt_20261006.json','diagnostics/GSS_foundation-source47-phase1_outer-summary_20261007.json','diagnostics/GSS_foundation-source47-phase1_outer-capture_20261007.jsonl','diagnostics/GSS_foundation-source47-phase1_security-review_20261007.json','diagnostics/GSS_foundation-source47-phase1_test-review_20261007.json','diagnostics/GSS_foundation-command-deparse-attempts29_20261005.jsonl',
 ...['maintenance.mjs','command-private-files.mjs','source-contract.mjs','apply-invocation.mjs','peer-metadata.mjs'].map(n=>lane+n)
];
const publicBase=new Map(originalFiles.map(p=>[p,fs.readFileSync(p)]));
const complete='diagnostics/GSS_foundation-command-adapter48-complete',app='diagnostics/GSS_foundation-command-adapter48-attempt3';
const manifestPath='diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json';
const originalManifestHash=h(publicBase.get(manifestPath)),phaseHash=h(publicBase.get('diagnostics/GSS_foundation-command-phase1-original47_20261006.json'));
const fixtureAttemptRef='7fd79236-8c14-4634-809d-2d6fd7ec7a17'; // Fictional matrix UUID; never native authority.
const strip=s=>s.replace(/^import .*;\r?\n/gm,'').replace(/^export \{[^\n]+\};\r?\n/gm,'').replace(/^export /gm,'');
const gateSource=strip(fs.readFileSync(lane+'source-contract.mjs','utf8'));
const reservationSource=strip(fs.readFileSync(lane+'apply-invocation.mjs','utf8'));
const code=gateSource+'\n'+reservationSource+'\nglobalThis.api={assertCompleteCommandReview,assertFreshApplyAuthority,bindConsumedInvocation,reserveApplyInvocation};';
const outcomes=['normal_2_to_3','cloned_approval','second_reservation','bind_failure_consumed','wrong_pid','altered_reservation','foreign_journal_uuid','new_process_replay','wrong_entry','changed_input','extra_lane_file','short_writes','reused_attempt_uuid'];
for(const scenario of outcomes){
  const attemptRef=scenario==='reused_attempt_uuid'?JSON.parse(publicBase.get(originalFiles[0]).toString().trimEnd().split('\n')[1]).attemptRef:fixtureAttemptRef;
  const map=new Map(publicBase),state={attempts:2,attempt3Ref:null};let fdName=null,activePid=54321,extra=false,bindRefused=false;
  const asBytes=x=>Buffer.from(JSON.stringify(x,null,2)+'\n');
  function installPacket(base,scope,isApp,adapterHash){
    const paths=isApp?[...originalFiles,complete+'_manifest_20261007.json',complete+'_packet_20261007.txt',complete+'_security-review_20261007.json',complete+'_test-review_20261007.json']:originalFiles.filter(p=>p.startsWith(lane)||p.startsWith('plans/')||p===manifestPath);
    const packetBytes=Buffer.from('FAKE source-only packet '+scope),bundleSha256=h(packetBytes);map.set(base+'_packet_20261007.txt',packetBytes);
    const m={scope,secretPatternHits:0,complete47ManifestSha256:originalManifestHash,applicationAuthorized:isApp,inputSha256:bundleSha256,inputs:paths.map(p=>({path:p,sha256:h(map.get(p))})),adapterManifestSha256:adapterHash,freshPhase1Sha256:phaseHash,attemptRef,maximumAttempts:3,beforeAttempts:2,archiveSha256:'DAEEA79A3AB5F53DDE28621905F66BB26FFF089C76ED2DC9B71B9EBB8C00AB8D',outerBudgetMs:600000,shorterWholeRunWatchdog:false};
    const mb=asBytes(m),mh=h(mb);map.set(base+'_manifest_20261007.json',mb);
    for(const role of ['security','test'])map.set(base+'_'+role+'-review_20261007.json',asBytes({provider:'OpenAI',transport:'collaboration',agentName:'/root/foundation_'+role+'_review',readOnly:true,completed:true,provenance:'actual subagent final response',scope,bundleSha256,sourceManifestSha256:mh,complete47ManifestSha256:originalManifestHash,adapterManifestSha256:isApp?adapterHash:null,freshPhase1Sha256:isApp?phaseHash:null,attemptRef:isApp?attemptRef:null,applicationAuthorized:isApp,productApproval:false,result:'## Verdict: Review OK\n'+(isApp?'## Application scope: SINGLE APPLICATION ATTEMPT3 ADAPTER48':'## Review scope: COMPLETE ADMISSION ADAPTER48')+'\n'}));
    return mh;
  }
  const ch=installPacket(complete,'COMPLETE ADMISSION ADAPTER48',false,null);installPacket(app,'SINGLE APPLICATION ATTEMPT3 ADAPTER48',true,ch);
  const relative=p=>path.isAbsolute(p)?path.relative(root,p).replaceAll('\\','/'):p;
  const fakeFs={existsSync:p=>map.has(relative(p)),realpathSync:{native:p=>p},lstatSync:()=>({isDirectory:()=>true}),readdirSync:()=>['maintenance.mjs','command-private-files.mjs','apply-invocation.mjs','source-contract.mjs','peer-metadata.mjs',...(extra?['unexpected.mjs']:[])],
    openSync:(p,flags)=>{assert.equal(flags,'wx');p=relative(p);assert(!map.has(p));fdName=p;map.set(p,Buffer.alloc(0));return 1;},
    writeSync:(fd,b,o,l)=>{const n=scenario==='short_writes'?Math.min(5,l):l;map.set(fdName,Buffer.concat([map.get(fdName),b.subarray(o,o+n)]));return n;},fsyncSync:()=>{},closeSync:()=>{if(scenario==='bind_failure_consumed')bindRefused=true;}
  };
  const fakeProcess={argv:['node',path.join(root,...(lane+'maintenance.mjs').split('/')),'apply'],get pid(){return activePid;}};
  if(scenario==='wrong_entry')fakeProcess.argv[2]='rotate';
  if(scenario==='changed_input')map.set(lane+'maintenance.mjs',Buffer.from('changed'));
  if(scenario==='extra_lane_file')extra=true;
  const context=vm.createContext({fs:fakeFs,path,createHash,process:fakeProcess,Buffer,classifyJournalLineage,assertFixedCommandProcess:()=>{},assertHistoricalCommandParents:()=>({...state}),currentJournalLineage:()=>({...state}),readPublicBytes:p=>{if(bindRefused&&p.includes('apply48_invocation'))throw Error('fixed refusal');assert(map.has(p),p);return map.get(p);},actualReviewMetadataAccepted:()=>true,assertCompleteCommandInputs:()=>({sourceInputsByteVerified:1819}),acceptedPeer,fixedCommandSource:()=>{},archiveName:'0007_private_fixture_command_ledger.sql',archiveSha256:'daeea79a3ab5f53dde28621905f66bb26fff089c76ed2dc9b71b9ebb8c00ab8d'});
  new vm.Script(code).runInContext(context);
  if(['wrong_entry','changed_input','extra_lane_file','reused_attempt_uuid'].includes(scenario)){
    assert.throws(()=>context.api.assertCompleteCommandReview());
    assert(!map.has('diagnostics/GSS_foundation-command-apply48_invocation-reserved_20261007.json'));assert.equal(state.attempts,2);
  }
  else {
    const approval=context.api.assertCompleteCommandReview();
    if(scenario==='cloned_approval')assert.throws(()=>context.api.reserveApplyInvocation({...approval}));
    else if(scenario==='bind_failure_consumed'){assert.throws(()=>context.api.reserveApplyInvocation(approval));assert(map.has('diagnostics/GSS_foundation-command-apply48_invocation-reserved_20261007.json'));assert.throws(()=>context.api.assertCompleteCommandReview());}
    else {
      context.api.reserveApplyInvocation(approval);assert.equal(context.api.assertCompleteCommandReview().attemptRef,attemptRef);
      state.attempts=3;state.attempt3Ref=attemptRef;assert.equal(context.api.assertCompleteCommandReview().attemptRef,attemptRef);
      if(scenario==='second_reservation')assert.throws(()=>context.api.reserveApplyInvocation(approval));
      if(scenario==='wrong_pid'){activePid++;assert.throws(()=>context.api.assertCompleteCommandReview());}
      if(scenario==='altered_reservation'){map.set('diagnostics/GSS_foundation-command-apply48_invocation-reserved_20261007.json',Buffer.from('{}'));assert.throws(()=>context.api.assertCompleteCommandReview());}
      if(scenario==='foreign_journal_uuid'){state.attempt3Ref='00000000-0000-4000-8000-000000000000';assert.throws(()=>context.api.assertCompleteCommandReview());}
      if(scenario==='new_process_replay'){const fresh=vm.createContext({...context});new vm.Script(code).runInContext(fresh);assert.throws(()=>fresh.api.assertCompleteCommandReview());}
    }
  }
  rows.push({case:scenario,passed:true});
}
console.log(JSON.stringify({scope:'Exact copy, pure actual peer classifier and actual gate/reservation source against fake IO only',passed:true,cases:rows,nativeInvocations:0,privateReads:0,connections:0}));
