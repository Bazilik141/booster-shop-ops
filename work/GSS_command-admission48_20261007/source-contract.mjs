// Additive D075 gate; immutable COMPLETE47 remains genuinely Claude-reviewed.
// No import-time IO, client, private-file read or caller-selected path.
import fs from 'node:fs';import path from 'node:path';import {createHash} from 'node:crypto';
import {assertFixedCommandProcess} from '../GSS_command-ledger47_20261006/command-process-fences.mjs';
import {assertCompleteCommandInputs,fixedCommandSource,archiveName,archiveSha256} from '../GSS_command-ledger47_20261006/source-contract.mjs';
import {assertHistoricalCommandParents,currentJournalLineage,classifyJournalLineage,readPublicBytes} from '../GSS_command-ledger47_20261006/historical-parent-gate.mjs';
import {actualReviewMetadataAccepted} from '../GSS_command-premises40_20261006/review-metadata.mjs';
import {acceptedPeer} from './peer-metadata.mjs';
export {fixedCommandSource,archiveName,archiveSha256};
const ROOT='C:\\Users\\14bez\\Downloads\\Booster Shop\\booster-shop-ops';
const ENTRY='work/GSS_command-admission48_20261007/maintenance.mjs';
export const reservationPath='diagnostics/GSS_foundation-command-apply48_invocation-reserved_20261007.json';
const OLD_RESERVATION='diagnostics/GSS_foundation-command-apply47_invocation-reserved_20261006.json';
const COMPLETE47='FB022E5EA7089378F95D7B0FAC93B063BAA182A72D6BD9DA9886CEA218FADBB5';
const PACKET47='C87652F157A7E35F408165E96848EF9EF4F4AFC471D32A99A9CED481CAF9D87E';
const PREFIX='9A695871717E690DD4922FCB4624A407C3EF0C8D6A1EA533D466017B6F4EBE74';
const PHASE1='A790444A610AF26C9E3A5B3DEB7863DC5CDBBD633A4107DE3D4FE02A31212906';
const D075='324341C1BB02C854BF782470FDE024F75A9BEADA42CEAFFFF666C2097EF1C9B0';
const design='4608E365FC30279268ECBE156B57263D21F2665E4F635DE74041200C856C74A2';
const h=b=>createHash('sha256').update(b).digest('hex').toUpperCase();
const read=readPublicBytes,json=p=>JSON.parse(read(p));
const refuse=()=>{throw Error('GSS temporary admission48 refused;values withheld');};
const issued=new WeakSet();let active=null;
function assertEntry() {
  assertFixedCommandProcess();
  if(process.argv.length!==3||process.argv[2]!=='apply'||path.resolve(process.argv[1])!==path.join(ROOT,...ENTRY.split('/')))refuse();
}
function originalAcceptance() {
  const parents=assertHistoricalCommandParents(),base='diagnostics/GSS_foundation-command-complete-source47';
  const mb=read(base+'_revision-source-manifest_20261006.json'),m=JSON.parse(mb),r=json(base+'_claude-review-run_20261006.json');
  if(h(mb)!==COMPLETE47||m.inputSha256!==PACKET47||m.secretPatternHits!==0||
    h(read(base+'_claude-review-run_20261006.json'))!=='87ACD575982037CC2552913F6D42C8A78F8E31DAFD76280CD9E1CACEB75A55EA'||
    h(read(base+'_reviewed_20261006.txt'))!=='73F372C54D07C466AB942124646DE7161C5B272F17B9CFF9E8260108C08F34AA'||
    !actualReviewMetadataAccepted(r,{scope:'COMPLETE PREAPPLICATION source47',bundleSha256:PACKET47,sourceManifestSha256:COMPLETE47,nativeWitnessAuthorized:false})||
    h(read(base+'_review-ready_20261006.txt'))!==PACKET47||read(base+'_reviewed_20261006.txt').toString().trim()!==PACKET47)refuse();
  const inputGate=assertCompleteCommandInputs(m);if(inputGate.sourceInputsByteVerified!==1819)refuse();
  const wpath='diagnostics/GSS_foundation-command-gate47-pre-dispatch_20261006.json',w=json(wpath);
  if(w.passed!==true||w.cases.length!==15||w.manifestSha256!==COMPLETE47||w.bundleSha256!==PACKET47||r.preDispatchGate.actualWitnessSha256!==h(read(wpath)))refuse();
  if(h(read('plans/GSS_owner-decision-D075_temporary-review_20261007.md'))!==D075||h(read('plans/GSS_foundation-temporary-attempt3-admission-design48_20261007.md'))!==design)refuse();
  return parents;
}
function packet(base,scope,applicationAuthorized,adapterManifestSha256) {
  const mb=read(base+'_manifest_20261007.json'),m=JSON.parse(mb),mh=h(mb);
  if(m.scope!==scope||m.secretPatternHits!==0||m.complete47ManifestSha256!==COMPLETE47||m.applicationAuthorized!==applicationAuthorized||
    !Array.isArray(m.inputs)||m.inputs.length<8||h(read(base+'_packet_20261007.txt'))!==m.inputSha256)refuse();
  const seen=new Set();for(const row of m.inputs){
    if(seen.has(row.path))refuse();seen.add(row.path);
    if(row.path==='diagnostics/GSS_foundation-command-deparse-attempts29_20261005.jsonl'){if(row.sha256!==PREFIX)refuse();currentJournalLineage();}
    else if(h(read(row.path))!==row.sha256)refuse();
  }
  for(const p of [ENTRY,'work/GSS_command-admission48_20261007/source-contract.mjs','work/GSS_command-admission48_20261007/peer-metadata.mjs','work/GSS_command-admission48_20261007/apply-invocation.mjs','work/GSS_command-admission48_20261007/command-private-files.mjs',
    'plans/GSS_owner-decision-D075_temporary-review_20261007.md','plans/GSS_foundation-temporary-attempt3-admission-design48_20261007.md',
    'diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json'])if(!seen.has(p))refuse();
  const lane=path.join(ROOT,'work','GSS_command-admission48_20261007');
  if(fs.realpathSync.native(lane)!==lane||!fs.lstatSync(lane).isDirectory())refuse();
  for(const name of fs.readdirSync(lane))if(!['maintenance.mjs','command-private-files.mjs','apply-invocation.mjs','source-contract.mjs','peer-metadata.mjs'].includes(name))refuse();
  const scopeHeading=applicationAuthorized?'## Application scope: SINGLE APPLICATION ATTEMPT3 ADAPTER48':'## Review scope: COMPLETE ADMISSION ADAPTER48';
  for(const role of ['security','test']){
    const r=json(base+'_'+role+'-review_20261007.json');
    if(!acceptedPeer(r,{agentName:'/root/foundation_'+role+'_review',scope,bundleSha256:m.inputSha256,sourceManifestSha256:mh,complete47ManifestSha256:COMPLETE47,
      adapterManifestSha256:applicationAuthorized?adapterManifestSha256:null,freshPhase1Sha256:applicationAuthorized?PHASE1:null,attemptRef:applicationAuthorized?m.attemptRef:null,applicationAuthorized,scopeHeading}))refuse();
  }
  return {m,manifestSha256:mh,seen};
}
function freshEvidence() {
  const p='diagnostics/GSS_foundation-command-phase1-original47_20261006.json',pb=read(p),r=JSON.parse(pb);
  if(h(pb)!==PHASE1||r.passed!==true||r.sourceManifestSha256!==COMPLETE47||r.originalStateExactBeforeAndAfter!==true||r.sourceHashesUnchanged!==true||r.applyAttempts!==2||r.newKeys!==false||r.newRoles!==0||r.counts.tests!==144||r.counts.pass!==144||['fail','cancelled','skipped','todo'].some(k=>r.counts[k]!==0)||r.criteria.length!==122||r.criteria.some(c=>c.passed!==true))refuse();
  for(const s of [r.before,r.after])if(s.state!=='original_0006'||s.fixtureMode!=='S0'||s.closure.rollback!=='acknowledged'||s.closure.connection!=='closed')refuse();
  if(h(read(r.transcript.path))!==r.transcript.sha256)refuse();
  const root=json('diagnostics/GSS_foundation-command-phase1-original47_root-receipt_20261006.json');
  if(root.passed!==true||root.nativePhase1Sha256!==PHASE1||root.applicationAuthorized!==false||root.tests!==144||root.originalCriteria!==122||root.applyAttempts!==2)refuse();
  const outer=json('diagnostics/GSS_foundation-source47-phase1_outer-summary_20261007.json');
  if(outer.exitCode!==0||outer.closeObserved!==true||outer.spawnError!==false||outer.timedOut!==false||outer.outputOverflow!==false||outer.artifactInspectionRefused!==false)refuse();
  for(const role of ['security','test']){const v=json('diagnostics/GSS_foundation-source47-phase1_'+role+'-review_20261007.json');if(v.provider!=='OpenAI'||v.agentName!=='/root/foundation_'+role+'_review'||v.scope!=='ACTUAL SOURCE47 PHASE1 EVIDENCE48'||v.verdict!=='Review OK'||v.readOnly!==true||v.I168Closed!==true||v.applicationAuthorized!==false||v.reviewedEvidenceHashes[p]!==PHASE1)refuse();}
}
function authority() {
  const complete=packet('diagnostics/GSS_foundation-command-adapter48-complete','COMPLETE ADMISSION ADAPTER48',false,null);
  const application=packet('diagnostics/GSS_foundation-command-adapter48-attempt3','SINGLE APPLICATION ATTEMPT3 ADAPTER48',true,complete.manifestSha256),m=application.m;
  if(m.adapterManifestSha256!==complete.manifestSha256||m.freshPhase1Sha256!==PHASE1||typeof m.attemptRef!=='string'||!/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(m.attemptRef)||m.maximumAttempts!==3||m.beforeAttempts!==2||m.archiveSha256!==archiveSha256.toUpperCase()||m.outerBudgetMs!==600000||m.shorterWholeRunWatchdog!==false)refuse();
  // Pure candidate validation BEFORE consumption or target/private access.
  // The frozen classifier also rejects UUIDs used in either reviewed attempt.
  const prefix=read('work/GSS_command-ledger47_20261006/two-attempt-prefix.jsonl');
  const candidate=classifyJournalLineage(Buffer.concat([prefix,Buffer.from(JSON.stringify({attempt:3,attemptRef:m.attemptRef})+'\n')]),prefix);
  if(candidate.attempts!==3||candidate.attempt3Ref!==m.attemptRef)refuse();
  for(const p of ['diagnostics/GSS_foundation-command-phase1-original47_20261006.json','diagnostics/GSS_foundation-command-phase1-original47_20261006.txt','diagnostics/GSS_foundation-command-phase1-original47_invocation-reserved_20261006.json','diagnostics/GSS_foundation-command-phase1-original47_root-receipt_20261006.json','diagnostics/GSS_foundation-source47-phase1_outer-summary_20261007.json','diagnostics/GSS_foundation-source47-phase1_outer-capture_20261007.jsonl','diagnostics/GSS_foundation-source47-phase1_security-review_20261007.json','diagnostics/GSS_foundation-source47-phase1_test-review_20261007.json','diagnostics/GSS_foundation-command-deparse-attempts29_20261005.jsonl',
    'diagnostics/GSS_foundation-command-adapter48-complete_manifest_20261007.json','diagnostics/GSS_foundation-command-adapter48-complete_packet_20261007.txt','diagnostics/GSS_foundation-command-adapter48-complete_security-review_20261007.json','diagnostics/GSS_foundation-command-adapter48-complete_test-review_20261007.json'])if(!application.seen.has(p))refuse();
  freshEvidence();
  return {attemptRef:m.attemptRef,adapterManifestSha256:complete.manifestSha256,applicationManifestSha256:application.manifestSha256};
}
export function assertCompleteCommandReview() {
  if(arguments.length)refuse();assertEntry();const parents=originalAcceptance(),a=authority();
  if(fs.existsSync(OLD_RESERVATION))refuse();
  if(active===null){if(parents.attempts!==2||fs.existsSync(reservationPath))refuse();}
  else if(active.pid!==process.pid||active.attemptRef!==a.attemptRef||active.applicationManifestSha256!==a.applicationManifestSha256||h(read(reservationPath))!==active.reservationSha256||parents.attempts===3&&parents.attempt3Ref!==a.attemptRef)refuse();
  const approval=Object.freeze({completeSourcePeerAccepted:true,sourceManifestSha256:COMPLETE47,...a,applicationAuthorized:true,valuesWithheld:true});issued.add(approval);return approval;
}
export function assertFreshApplyAuthority(approval) {
  if(arguments.length!==1||!issued.has(approval)||active!==null)refuse();assertEntry();
  const a=authority();if(currentJournalLineage().attempts!==2||fs.existsSync(OLD_RESERVATION)||fs.existsSync(reservationPath)||a.attemptRef!==approval.attemptRef||a.applicationManifestSha256!==approval.applicationManifestSha256)refuse();
  return Object.freeze(a);
}
export function bindConsumedInvocation(approval,reservationBytes) {
  if(arguments.length!==2||!issued.has(approval)||active!==null||!Buffer.isBuffer(reservationBytes))refuse();assertEntry();
  const actual=read(reservationPath),r=JSON.parse(actual);
  if(!actual.equals(reservationBytes)||r.scope!=='SINGLE APPLICATION ATTEMPT3 ADAPTER48'||r.operation!=='apply'||r.executorPid!==process.pid||r.attemptRef!==approval.attemptRef||r.sourceManifestSha256!==COMPLETE47||r.adapterManifestSha256!==approval.adapterManifestSha256||r.applicationManifestSha256!==approval.applicationManifestSha256||r.consumedBeforeTarget!==true||r.consumedOnRefusal!==true||r.automaticRetry!==false||currentJournalLineage().attempts!==2)refuse();
  active=Object.freeze({pid:process.pid,attemptRef:r.attemptRef,applicationManifestSha256:r.applicationManifestSha256,reservationSha256:h(actual)});
}
