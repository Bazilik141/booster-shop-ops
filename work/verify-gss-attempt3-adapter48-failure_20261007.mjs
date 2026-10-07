// Verify PUBLIC failure evidence; never invoke/query target or read private contents.
import fs from 'node:fs';import assert from 'node:assert/strict';import {createHash} from 'node:crypto';
import {readPublicBytes,currentJournalLineage} from './GSS_command-ledger47_20261006/historical-parent-gate.mjs';
import {assertCompleteCommandInputs} from './GSS_command-ledger47_20261006/source-contract.mjs';
import {acceptedPeer} from './GSS_command-admission48_20261007/peer-metadata.mjs';
const base='diagnostics/GSS_foundation-command-adapter48-attempt3',complete='diagnostics/GSS_foundation-command-adapter48-complete';
const h=b=>createHash('sha256').update(b).digest('hex').toUpperCase(),hashes={};
const read=p=>{const b=readPublicBytes(p);hashes[p]=h(b);return b;},json=p=>JSON.parse(read(p));
const m=json(base+'_manifest_20261007.json'),mh=hashes[base+'_manifest_20261007.json'],cm=json(complete+'_manifest_20261007.json'),ch=hashes[complete+'_manifest_20261007.json'];
assert.equal(mh,'6F1CFD129769031CAE6FB06D221909B3CBAD033B550C6D8789DFD5DDFC7A5C8F');assert.equal(m.adapterManifestSha256,ch);assert.equal(m.attemptRef,'c0e65978-f8da-470d-9527-fdd14dfc7fd9');
for(const [stem,manifest,manifestHash,isApp] of [[complete,cm,ch,false],[base,m,mh,true]]){
  assert.equal(h(read(stem+'_packet_20261007.txt')),manifest.inputSha256);assert.equal(manifest.secretPatternHits,0);
  for(const r of manifest.inputs){if(r.path===m.actualOuterConfig.journalPath){assert.equal(r.sha256,'9A695871717E690DD4922FCB4624A407C3EF0C8D6A1EA533D466017B6F4EBE74');currentJournalLineage();}else assert.equal(h(readPublicBytes(r.path)),r.sha256);}
  for(const role of ['security','test'])assert(acceptedPeer(json(stem+'_'+role+'-review_20261007.json'),{agentName:'/root/foundation_'+role+'_review',scope:manifest.scope,bundleSha256:manifest.inputSha256,sourceManifestSha256:manifestHash,complete47ManifestSha256:m.complete47ManifestSha256,adapterManifestSha256:isApp?ch:null,freshPhase1Sha256:isApp?m.freshPhase1Sha256:null,attemptRef:isApp?m.attemptRef:null,applicationAuthorized:isApp,scopeHeading:isApp?'## Application scope: SINGLE APPLICATION ATTEMPT3 ADAPTER48':'## Review scope: COMPLETE ADMISSION ADAPTER48'}));
}
assert.equal(read(base+'_reviewed_20261007.txt').toString().trim(),m.inputSha256);
const config=json('work/GSS_command-attempt3-outer48_20261007.json');assert.deepEqual(config,m.actualOuterConfig);
const summary=json(config.summaryPath),events=read(config.capturePath).toString().trimEnd().split('\n').map(s=>JSON.parse(s));assert.equal(events.length,2);
const [begin,end]=events;assert.equal(begin.event,'begin');assert.equal(end.event,'end');assert.deepEqual(summary,{...begin,...end,capturePath:config.capturePath});
assert.equal(begin.applicationManifestSha256,mh);assert.equal(begin.adapterManifestSha256,ch);assert.equal(begin.attemptRef,m.attemptRef);assert.deepEqual(begin.actualOuterConfig,config);
for(const r of Object.values(begin.before)){assert.equal(r.present,false);assert.equal(r.sha256,null);}assert.equal(begin.journalBefore.sha256,'9A695871717E690DD4922FCB4624A407C3EF0C8D6A1EA533D466017B6F4EBE74');
assert.equal(end.passed,false);assert.equal(end.exitCode,1);assert.equal(end.signal,null);assert.equal(end.closeObserved,true);assert.equal(end.safeActiveResult,null);
for(const k of ['spawnError','timedOut','outputOverflow','artifactInspectionRefused'])assert.equal(end[k],false);assert.equal(end.treeTermination,null);assert.equal(end.stderr.bytes,0);assert.equal(end.stderr.sha256,h(Buffer.alloc(0)));
assert.equal(end.automaticRetry,false);assert.equal(end.productApproval,false);assert.equal(config.outerBudgetMs,600000);assert(end.elapsedMs>0&&end.elapsedMs<600000);
assert(Math.abs(Date.parse(end.actualEndUtc)-Date.parse(begin.actualStartUtc)-end.elapsedMs)<2000);
const lineage=currentJournalLineage();assert.deepEqual(end.lineage,lineage);assert.equal(lineage.attempts,3);assert.equal(lineage.attempt3Ref,m.attemptRef);assert.equal(h(read(config.journalPath)),end.journalAfter.sha256);
const [reservation,oldReservation,refusal,capture]=config.outputsMustBeAbsent,r=json(reservation);assert.equal(hashes[reservation],end.after[reservation].sha256);assert.equal(r.executorPid,end.runnerPid);assert.equal(r.attemptRef,m.attemptRef);assert.equal(r.applicationManifestSha256,mh);assert.equal(r.adapterManifestSha256,ch);assert.equal(r.sourceManifestSha256,m.complete47ManifestSha256);assert.equal(r.consumedBeforeTarget,true);assert.equal(r.consumedOnRefusal,true);assert.equal(r.automaticRetry,false);
const failure=json(refusal);assert.equal(hashes[refusal],end.after[refusal].sha256);
assert.deepEqual(failure,{format:'gss.command.refusal.v1',attempt:3,operation:'apply',maintenancePhase:'whole-candidate-before-history',family:'untagged_sqlstate',stage:'external_read',category:'externalFunctions',comparisonState:'none',literalKind:'unknown',identityCount:'unknown',sqlState:'42703',elapsedBucket:'5_to_14s',recovery:{rollback:'acknowledged',independentRead:'original',xid:'aborted',capture:'not_eligible',cleanup:'not_needed',unlock:'released',connection:'closed'},valuesWithheld:true,automaticRetry:false});
for(const p of [oldReservation,capture]){assert.equal(end.after[p].present,false);assert(!fs.existsSync(p));}
for(const p of config.privateExistenceOnly){assert.equal(end.privatePresence[p],false);assert(!fs.existsSync(p));}
const old=json('diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json'),source=assertCompleteCommandInputs(old);assert.equal(source.sourceInputsByteVerified,1819);
assert.equal(h(read('gss/db/migrations/0007_private_fixture_command_ledger.sql')),config.archiveSha256);
const receipt={scope:'ACTUAL ATTEMPT3 ADAPTER48 FAILURE PUBLIC ROOT RECEIPT',evidenceVerified:true,applicationSucceeded:false,actualStartUtc:begin.actualStartUtc,actualEndUtc:end.actualEndUtc,elapsedMs:end.elapsedMs,exitCode:1,closeObserved:true,timedOut:false,attemptRef:m.attemptRef,applyAttempts:3,maximumAttempts:3,grantConsumed:true,retryAuthorized:false,applicationAuthorized:false,refusal:{stage:failure.stage,category:failure.category,maintenancePhase:failure.maintenancePhase,sqlState:failure.sqlState},recoveryReportedByReviewedChild:failure.recovery,newDatabaseQueryByRoot:false,privateContentsReadByRoot:false,currentAndNextAbsent:true,sourceInputsVerified:1819,journalMutation:'exact reviewed prefix plus authorized attempt3 row only',publishedArchiveUnchanged:true,productApproval:false,phase2Accepted:false,wholeFoundationAccepted:false,productionChanged:false,evidenceHashes:hashes,valuesWithheld:true};
fs.writeFileSync(base+'_failure-root-receipt_20261007.json',JSON.stringify(receipt,null,2)+'\n',{flag:'wx'});
console.log(JSON.stringify({failureEvidenceVerified:true,applicationSucceeded:false,applyAttempts:3,recoveryReported:'rollback/original/aborted/unlocked/closed',sourceInputsVerified:1819,retryAuthorized:false,productApproval:false,valuesWithheld:true}));
