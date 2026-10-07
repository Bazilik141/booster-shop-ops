// Root post-run PUBLIC evidence verification only. No DB/secret/native dispatch.
import assert from 'node:assert/strict';import fs from 'node:fs';import {createHash} from 'node:crypto';
import {readPublicBytes,currentJournalLineage} from './GSS_command-ledger47_20261006/historical-parent-gate.mjs';
import {assertCompleteCommandInputs} from './GSS_command-ledger47_20261006/source-contract.mjs';
import {acceptedPeer} from './GSS_command-admission48_20261007/peer-metadata.mjs';
const base='diagnostics/GSS_foundation-command-adapter48-attempt3',complete='diagnostics/GSS_foundation-command-adapter48-complete';
const h=b=>createHash('sha256').update(b).digest('hex').toUpperCase(),read=readPublicBytes,json=p=>JSON.parse(read(p));
const evidence=new Map(),remember=p=>{const b=read(p);evidence.set(p,h(b));return b;};
const appManifestPath=base+'_manifest_20261007.json',mb=remember(appManifestPath),m=JSON.parse(mb),mh=h(mb),c=JSON.parse(remember(complete+'_manifest_20261007.json')),ch=evidence.get(complete+'_manifest_20261007.json');
assert.equal(m.scope,'SINGLE APPLICATION ATTEMPT3 ADAPTER48');assert.equal(m.adapterManifestSha256,ch);assert.equal(m.beforeAttempts,2);assert.equal(m.maximumAttempts,3);assert.equal(m.applicationAuthorized,true);assert.equal(m.productApproval,false);
for(const [stem,manifest,manifestHash,isApp] of [[complete,c,ch,false],[base,m,mh,true]]){
  assert.equal(h(remember(stem+'_packet_20261007.txt')),manifest.inputSha256);assert.equal(manifest.secretPatternHits,0);
  for(const row of manifest.inputs){if(row.path===m.actualOuterConfig.journalPath){assert.equal(row.sha256,'9A695871717E690DD4922FCB4624A407C3EF0C8D6A1EA533D466017B6F4EBE74');currentJournalLineage();}else assert.equal(h(read(row.path)),row.sha256);}
  for(const role of ['security','test']){const r=JSON.parse(remember(stem+'_'+role+'-review_20261007.json'));assert(acceptedPeer(r,{agentName:'/root/foundation_'+role+'_review',scope:manifest.scope,bundleSha256:manifest.inputSha256,sourceManifestSha256:manifestHash,complete47ManifestSha256:m.complete47ManifestSha256,adapterManifestSha256:isApp?ch:null,freshPhase1Sha256:isApp?m.freshPhase1Sha256:null,attemptRef:isApp?m.attemptRef:null,applicationAuthorized:isApp,scopeHeading:isApp?'## Application scope: SINGLE APPLICATION ATTEMPT3 ADAPTER48':'## Review scope: COMPLETE ADMISSION ADAPTER48'}));}
}
assert.equal(remember(base+'_reviewed_20261007.txt').toString().trim(),m.inputSha256);
const config=JSON.parse(remember('work/GSS_command-attempt3-outer48_20261007.json'));assert.deepEqual(config,m.actualOuterConfig);
const summary=JSON.parse(remember(config.summaryPath)),events=remember(config.capturePath).toString().trimEnd().split('\n').map(s=>JSON.parse(s));assert.equal(events.length,2);
const [begin,end]=events;assert.equal(begin.event,'begin');assert.equal(end.event,'end');assert.deepEqual(summary,{...begin,...end,capturePath:config.capturePath});
assert.equal(begin.applicationManifestSha256,mh);assert.equal(begin.adapterManifestSha256,ch);assert.equal(begin.complete47ManifestSha256,m.complete47ManifestSha256);assert.equal(begin.freshPhase1Sha256,m.freshPhase1Sha256);assert.equal(begin.attemptRef,m.attemptRef);assert.deepEqual(begin.actualOuterConfig,config);
assert.equal(config.outerBudgetMs,600000);assert.equal(config.shorterWholeRunWatchdog,false);assert.equal(config.maintenanceTransactionMs,180000);assert.equal(config.maintenanceIdleMs,60000);
for(const r of Object.values(begin.before)){assert.equal(r.present,false);assert.equal(r.sha256,null);}assert.equal(begin.journalBefore.sha256,'9A695871717E690DD4922FCB4624A407C3EF0C8D6A1EA533D466017B6F4EBE74');
assert.equal(end.passed,true);assert.equal(end.exitCode,0);assert.equal(end.signal,null);assert.equal(end.closeObserved,true);for(const k of ['spawnError','timedOut','outputOverflow','artifactInspectionRefused'])assert.equal(end[k],false);
assert.equal(end.treeTermination,null);assert(Number.isFinite(end.elapsedMs)&&end.elapsedMs>0&&end.elapsedMs<600000);
const wall=Date.parse(end.actualEndUtc)-Date.parse(begin.actualStartUtc);assert(wall>0&&Math.abs(wall-end.elapsedMs)<2000);assert.equal(begin.ownerTimezone,'Europe/Kyiv');
assert.deepEqual(end.safeActiveResult,{state:'active_exact',fileAction:'retain',admissionEligible:true,automaticRetry:false,ledgerAction:'retain_all',existingSecretsAction:'unchanged'});
assert.equal(end.stderr.bytes,0);assert.equal(end.stderr.sha256,h(Buffer.alloc(0)));
const expectedStdout=Buffer.from(JSON.stringify(end.safeActiveResult)+'\n');assert.equal(end.stdout.bytes,expectedStdout.length);assert.equal(end.stdout.sha256,h(expectedStdout));
const lineage=currentJournalLineage();assert.deepEqual(end.lineage,lineage);assert.equal(lineage.attempts,3);assert.equal(lineage.attempt3Ref,m.attemptRef);assert.equal(end.journalAfter.sha256,h(remember(config.journalPath)));
const rp=config.outputsMustBeAbsent[0],record=JSON.parse(remember(rp));assert.equal(end.after[rp].sha256,evidence.get(rp));assert.equal(record.executorPid,end.runnerPid);assert.equal(record.attemptRef,m.attemptRef);assert.equal(record.scope,m.scope);assert.equal(record.sourceManifestSha256,m.complete47ManifestSha256);assert.equal(record.adapterManifestSha256,ch);assert.equal(record.applicationManifestSha256,mh);assert.equal(record.consumedBeforeTarget,true);assert.equal(record.consumedOnRefusal,true);assert.equal(record.automaticRetry,false);
for(const p of config.outputsMustBeAbsent.slice(1)){assert.equal(end.after[p].present,false);assert(!fs.existsSync(p));}
assert.equal(end.privatePresence[config.privateExistenceOnly[0]],true);assert.equal(end.privatePresence[config.privateExistenceOnly[1]],false);assert(fs.existsSync(config.privateExistenceOnly[0]));assert(!fs.existsSync(config.privateExistenceOnly[1]));
const frozen=JSON.parse(remember('diagnostics/GSS_foundation-command-complete-source47_revision-source-manifest_20261006.json')),inputs=assertCompleteCommandInputs(frozen);assert.equal(inputs.sourceInputsByteVerified,1819);
assert.equal(h(read('gss/db/migrations/0007_private_fixture_command_ledger.sql')),config.archiveSha256);
const receipt={scope:'ACTUAL ATTEMPT3 ADAPTER48 PUBLIC ROOT RECEIPT',passed:true,actualStartUtc:begin.actualStartUtc,actualEndUtc:end.actualEndUtc,elapsedMs:end.elapsedMs,outerBudgetMs:600000,applicationManifestSha256:mh,adapterManifestSha256:ch,complete47ManifestSha256:m.complete47ManifestSha256,freshPhase1Sha256:m.freshPhase1Sha256,attemptRef:m.attemptRef,applyAttempts:3,maximumAttempts:3,invocationConsumed:true,childObservedState:'active_exact',independentOutcomeCheckedWithinReviewedChild:true,hostAuthenticationCheckedWithinReviewedChild:'positive_and_wrongpassword',sourceInputsVerified:1819,publishedArchiveUnchanged:true,privateFileReadByRoot:false,privateFileReadByReviewers:false,automaticRetry:false,productApproval:false,wholeFoundationAccepted:false,phase2Accepted:false,productionChanged:false,evidenceHashes:Object.fromEntries(evidence),valuesWithheld:true};
fs.writeFileSync(base+'_root-receipt_20261007.json',JSON.stringify(receipt,null,2)+'\n',{flag:'wx'});
console.log(JSON.stringify({rootReceiptPassed:true,state:receipt.childObservedState,applyAttempts:3,sourceInputsVerified:1819,automaticRetry:false,productApproval:false,valuesWithheld:true}));
