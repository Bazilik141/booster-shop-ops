// UNREVIEWED fixed sole maintenance CLI. NOT executable until COMPLETE actual
// Opus source47 gate exists and pins ALL actual source. No client/config/query export.
import fs from 'node:fs';import path from 'node:path';import {fileURLToPath} from 'node:url';import {randomUUID,randomBytes,createHash} from 'node:crypto';
import pg from '../../gss/node_modules/pg/lib/index.js';import {ROOT,verifiedRunningStack} from '../../gss/tools/local-target.mjs';
import {assertCompleteCommandReview,fixedCommandSource,archiveName,archiveSha256} from './source-contract.mjs';
import {assertFixedCommandProcess} from '../GSS_command-ledger47_20261006/command-process-fences.mjs';
import {commandSessionFenceSQL,commandFenceReadSQL,assertCommandSessionFences} from '../GSS_command-ledger47_20261006/command-session-fences.mjs';
import {commandMaintenanceDDLFenceSQL,commandDDLReadSQL,commandAmbientReadSQL,assertCommandDDLFences,assertCommandAmbientSettings} from '../GSS_command-ledger47_20261006/command-ddl-fences.mjs';
import {commandLeaseSQL,inspectCommandLeases,classifyPostcommitLeases,commandMaintenanceLockSQL,commandMaintenanceUnlockSQL} from '../GSS_command-ledger28_20261005/command-leases.mjs';
import {assertCommandFile,freshCommandClients} from '../GSS_command-ledger28_20261005/command-private-file-format.mjs';
import {inspectCommandPrivatePrivacy,initializeCommandPrivateFolder,readCurrentCommandFile,readNextCommandFile,writeCurrentCommandFile,writeNextCommandFile,
 removeOnlyOwnAbortedCommandFile,quarantineMatchingCurrentCommandFile,quarantineConfirmedAbortedNextCommandFile,promoteMatchingNextCommandFile} from './command-private-files.mjs';
import {classifyInstallationRecovery,classifyCredentialLifecycle} from '../GSS_command-ledger28_20261005/maintenance-recovery-model.mjs';
import {inspectCommandState,commandTransactionStamp,assertSameCommandTransaction,commandXidOutcome,commandPrivateProvenance} from '../GSS_command-ledger47_20261006/command-state-inspection.mjs';
import {releaseCaptureAfterConfirmedRollback,comparisonTokenForRefusal} from '../GSS_command-ledger47_20261006/source-comparison.mjs';
import {withInspectionTrace,setInspectionStage,makeInspectionRefusal,inspectionSnapshot,recordConnectionCloseFailure,connectionCloseFailureRecorded} from '../GSS_command-ledger47_20261006/inspection-trace.mjs';
import {recognizedSqlState,snapshotRefusal,classifyRefusal} from '../GSS_command-ledger47_20261006/value-free-refusal.mjs';
import {writeAttemptRefusal} from '../GSS_command-ledger47_20261006/refusal-diagnostic-writer.mjs';
import {reserveApplyInvocation} from './apply-invocation.mjs';
const refuse=()=>{throw makeInspectionRefusal('maintenance','precondition');},roles=['gss_command_runtime','gss_command_fixture_admitter'];
// Append-only NONSECRET attempt journal. It is operational state,not source or
// an owner task/GitHub ledger. Created zero-state is reviewed before first apply.
const journal='diagnostics/GSS_foundation-command-deparse-attempts29_20261005.jsonl';
function attempts(){
 const absolute=path.resolve(journal);if(fs.realpathSync.native(absolute)!==absolute||fs.lstatSync(absolute).isSymbolicLink()||!fs.statSync(absolute).isFile()||fs.statSync(absolute,{bigint:true}).nlink!==1n)refuse();
 const b=fs.readFileSync(absolute);if(b.length>32768||b.at(-1)!==10)refuse();
 const rows=b.toString('utf8').trimEnd().split('\n').map(s=>JSON.parse(s));
 if(JSON.stringify(rows[0])!==JSON.stringify({format:'gss.command.deparse.attempts.v1',maximum:3,initialAttempts:0,cluster:'7693110423095439394'}))refuse();
 const runs=new Set();for(let i=1;i<rows.length;i++){
  const r=rows[i];if(Object.keys(r).sort().join(',')!=='attempt,attemptRef'||r.attempt!==i||i>3||!/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(r.attemptRef)||runs.has(r.attemptRef))refuse();runs.add(r.attemptRef);
 }
 return rows.length-1;
}
function reserveAttempt(attemptRef){
 const previous=attempts();if(previous>=3)refuse();const fd=fs.openSync(journal,'a');
 try{fs.writeSync(fd,Buffer.from(JSON.stringify({attempt:previous+1,attemptRef})+'\n'));fs.fsyncSync(fd);}finally{fs.closeSync(fd);}
 if(attempts()!==previous+1)refuse();return previous+1;
}
function bootSecret(){
 const p=path.join(ROOT,'.local','secrets.json');if(fs.realpathSync.native(p)!==p||!fs.lstatSync(p).isFile()||fs.statSync(p,{bigint:true}).nlink!==1n)refuse();
 const s=JSON.parse(fs.readFileSync(p,'utf8'));if(Object.keys(s).sort().join(',')!=='jwt,password'||!/^[a-f0-9]{64}$/.test(s.password)||!/^[a-f0-9]{96}$/.test(s.jwt))refuse();return s.password;
}
async function publishFixedSourceArchive(c){
 const folder=path.join(ROOT,'db','migrations'),file=path.join(folder,archiveName);
 if(fs.realpathSync.native(folder)!==folder||!fs.lstatSync(folder).isDirectory())refuse();
 const source=fs.readFileSync(path.resolve('work/GSS_command-ledger47_20261006/'+archiveName));
 if(createHash('sha256').update(source).digest('hex')!==archiveSha256)refuse();
 if(fs.existsSync(file)){
  if(fs.realpathSync.native(file)!==file||!fs.lstatSync(file).isFile()||fs.statSync(file,{bigint:true}).nlink!==1n)refuse();
  const actual=createHash('sha256').update(fs.readFileSync(file)).digest('hex');if(actual===archiveSha256)return;
  if(attempts()===3)throw makeInspectionRefusal('maintenance','source_archive');
  if(actual!=='7c524dbbbefa2dafeb5aa4227cec5ee8bd55782c4767ed55044065c8c939d8e3'||attempts()!==2)refuse();
  const outcome=(await c.query("SELECT pg_xact_status('1893'::xid8) AS outcome,(SELECT count(*)::int FROM pg_prepared_xacts) AS prepared")).rows[0];
  if(outcome.outcome!=='aborted'||outcome.prepared!==0)refuse();
  const privateFolder=path.join(ROOT,'.local','command-ledger');
  if(JSON.stringify(fs.readdirSync(privateFolder))!=='["quarantine"]'||fs.readdirSync(path.join(privateFolder,'quarantine')).length)refuse();
  const next=file+'.source33-next';if(fs.existsSync(next))refuse();const fd=fs.openSync(next,'wx');
  try{fs.writeFileSync(fd,source);fs.fsyncSync(fd);}finally{fs.closeSync(fd);}
  if(fs.realpathSync.native(next)!==next||!fs.lstatSync(next).isFile()||fs.statSync(next,{bigint:true}).nlink!==1n||createHash('sha256').update(fs.readFileSync(next)).digest('hex')!==archiveSha256)refuse();
  fs.renameSync(next,file);
  if(createHash('sha256').update(fs.readFileSync(file)).digest('hex')!==archiveSha256)refuse();return;
 }
 if(attempts()>=2)throw makeInspectionRefusal('maintenance','source_archive');
 const fd=fs.openSync(file,'wx');try{fs.writeFileSync(fd,source);fs.fsyncSync(fd);}finally{fs.closeSync(fd);}
 // Reviewed immutable source only;NEVER execute this whole archive. All legacy
 // migrators refuse its 0007 name before external operations. Retain on abort.
}
function config(user,password){
 // Literal WRITABLE config;PG* / defaults / NODE startup were refused first.
 return {host:'127.0.0.1',port:55322,database:'postgres',user,password,ssl:false,
 options:'-c default_transaction_read_only=on',application_name:'gss-command-maintenance',connectionTimeoutMillis:2000,
 query_timeout:12000,statement_timeout:8000,lock_timeout:5000,idle_in_transaction_session_timeout:10000,keepAlive:true,keepAliveInitialDelayMillis:0};
}
async function fences(c,phase='boot_initial',transactionMode='outside'){
 setInspectionStage('session','logging');const logging=(await c.query(commandFenceReadSQL)).rows;assertCommandSessionFences(logging);
 setInspectionStage('session','ddl');assertCommandDDLFences((await c.query(commandDDLReadSQL)).rows,'maintenance');setInspectionStage('session','ambient');assertCommandAmbientSettings((await c.query(commandAmbientReadSQL)).rows,logging,phase,transactionMode,'maintenance');
}
async function connectBoot(verification=false){
 if(typeof verification!=='boolean')refuse();
 assertFixedCommandProcess();const c=new pg.Client(config('supabase_admin',bootSecret()));c.on('error',()=>{});
 try{setInspectionStage('maintenance','credentials');await c.connect();setInspectionStage('session','logging');await c.query(commandSessionFenceSQL);setInspectionStage('session','ddl');await c.query(commandMaintenanceDDLFenceSQL);await fences(c);return c;}catch(e){const snapshot=snapshotRefusal(e,inspectionSnapshot());try{await c.end();}catch{if(!verification)recordConnectionCloseFailure();}throw snapshot;}
}
async function lock(c){const r=(await c.query(commandMaintenanceLockSQL)).rows;if(r.length!==1||r[0].acquired!==true)refuse();}
async function unlock(c){const r=(await c.query(commandMaintenanceUnlockSQL)).rows;if(r.length!==1||r[0].released!==true)refuse();}
async function twoLeases(c){
 setInspectionStage('maintenance','lease');
 const first=inspectCommandLeases((await c.query(commandLeaseSQL)).rows),second=inspectCommandLeases((await c.query(commandLeaseSQL)).rows);
 return classifyPostcommitLeases(first,second).state;
}
async function noLeases(c){setInspectionStage('maintenance','lease');if(!inspectCommandLeases((await c.query(commandLeaseSQL)).rows).zero)refuse();}
async function readonlyIndependent(source){
 const c=await connectBoot(true);let firstFailure=null;try{
  setInspectionStage('maintenance','transaction');
  await c.query('BEGIN ISOLATION LEVEL READ COMMITTED READ ONLY');const n=(await c.query("SELECT count(*)::int AS n FROM pg_roles WHERE left(rolname,12)='gss_command_'")).rows[0].n;
  if(n!==0&&n!==3)refuse();const state=await inspectCommandState(c,source,{original:n===0});
  setInspectionStage('maintenance','transaction');if((await c.query('COMMIT')).command!=='COMMIT')refuse();return state;
 }catch(e){firstFailure=snapshotRefusal(e,inspectionSnapshot());throw firstFailure;}
 finally{setInspectionStage('maintenance','transaction');try{await c.end();}catch(e){if(!firstFailure)throw snapshotRefusal(e,inspectionSnapshot());}}
}
async function inspectBound(c,source,file,state,transactionMode='read_only'){
 // These are independent/read-only state facts;never a failed-write client.
 const provenance=await commandPrivateProvenance(c,file,state,transactionMode,'maintenance'),xid=await commandXidOutcome(c,file);
 return {provenance,xid};
}
async function positiveAndWrongPassword(file,staleFile=null){
 for(const role of roles){
  let positive,negative;try{
   positive=new pg.Client(config(role,file.roles[role].password));positive.on('error',()=>{});await positive.connect();
   const r=(await positive.query(`SELECT session_user AS original_role,current_user AS current_role,current_database() AS database,
    inet_server_port() AS port,inet_client_addr()::text AS client_address,current_setting('server_version_num') AS version,
    rolsuper,rolbypassrls,rolcreatedb,rolcreaterole,rolinherit,rolreplication FROM pg_roles WHERE rolname=session_user`)).rows[0];
   if(r.original_role!==role||r.current_role!==role||r.database!=='postgres'||r.port!==5432||r.client_address!=='172.20.0.1/32'||r.version!=='170011'||
    ['rolsuper','rolbypassrls','rolcreatedb','rolcreaterole','rolinherit','rolreplication'].some(k=>r[k]!==false))refuse();
   await positive.end();positive=null;let wrong=randomBytes(32).toString('hex');while(wrong===file.roles[role].password)wrong=randomBytes(32).toString('hex');
   negative=new pg.Client(config(role,wrong));negative.on('error',()=>{});let code=null,connected=false;
   try{await negative.connect();connected=true;}catch(e){code=recognizedSqlState(e);}
   if(connected||code!=='28P01')refuse();await negative.end().catch(()=>{});negative=null;
   if(staleFile){
    if(staleFile.roles[role].password===file.roles[role].password)refuse();
    negative=new pg.Client(config(role,staleFile.roles[role].password));negative.on('error',()=>{});code=null;connected=false;
    try{await negative.connect();connected=true;}catch(e){code=recognizedSqlState(e);}
    if(connected||code!=='28P01')refuse();
   }
  }finally{await positive?.end().catch(()=>{});await negative?.end().catch(()=>{});}
 }
 return 'positive_and_wrongpassword';
}
async function requireCredentialMessage(c){
 // Must be BOOT ORIGINAL and CURRENT. Readbacks immediately BEFORE combined
 // secret message. Its closing backstop+verifiers+gate share one transport.
 const r=(await c.query("SELECT session_user AS s,current_user AS c,current_setting('role') AS r")).rows[0];
 if(r.s!=='supabase_admin'||r.c!=='supabase_admin'||r.r!=='supabase_admin')refuse();await fences(c,'boot','read_write');
}
function credentialSQL(file){
 const f=assertCommandFile(file);return roles.map(role=>`ALTER ROLE ${role} LOGIN PASSWORD '${f.roles[role].verifier}';`).join('\n');
}
async function appendMarker(c,verb,generation){
 const rows=(await c.query(`INSERT INTO gss_meta.command_maintenance_runs(generation,verb,source_archive_sha256,cluster_id)
 VALUES($1::uuid,$2,$3,'7693110423095439394') RETURNING run_ref::text,generation::text,top_xid::text,verb,source_archive_sha256,cluster_id,credential_generation::text,original_role::text`,
 [generation,verb,archiveSha256])).rows;
 if(rows.length!==1||rows[0].generation!==generation||rows[0].verb!==verb||rows[0].source_archive_sha256!==archiveSha256||rows[0].cluster_id!=='7693110423095439394'||rows[0].original_role!=='supabase_admin')refuse();return rows[0];
}
function privateFor(marker,clients){return assertCommandFile({format:'gss.command.private.credentials.v1',cluster:marker.cluster_id,sourceArchiveSha256:marker.source_archive_sha256,
 runRef:marker.run_ref,ledgerGeneration:marker.generation,topXid:marker.top_xid,credentialGeneration:marker.credential_generation,verb:marker.verb,roles:clients});}
async function committedFacts(c,source,current,next){
 await c.query('BEGIN ISOLATION LEVEL READ COMMITTED READ ONLY');const state=await inspectCommandState(c,source);
 const currentFacts=current?await inspectBound(c,source,current,state):null,nextFacts=next?await inspectBound(c,source,next,state):null;
 if((await c.query('COMMIT')).command!=='COMMIT')refuse();
 const leases=await twoLeases(c);return {state,currentFacts,nextFacts,leases};
}
async function reconcileLocked(c,source){
 const current=await readCurrentCommandFile(),next=await readNextCommandFile();
 await c.query('BEGIN ISOLATION LEVEL READ COMMITTED READ ONLY');const n=(await c.query("SELECT count(*)::int AS n FROM pg_roles WHERE left(rolname,12)='gss_command_'")).rows[0].n;
 if(n!==0&&n!==3)refuse();const state=await inspectCommandState(c,source,{original:n===0});
 const cf=current?await inspectBound(c,source,current,state):null,nf=next?await inspectBound(c,source,next,state):null;
 if((await c.query('COMMIT')).command!=='COMMIT')refuse();
 if(state.state==='original_0006'){
  if(next)refuse();if(!current)return Object.freeze({state:'fresh_preconditions_required',admissionEligible:false});
  const r=classifyInstallationRecovery({catalog:'original_0006',file:'matching',xid:cf.xid,marker:cf.provenance.marker,createdByThisRun:false});
  if(r.fileAction==='quarantine_matching_old_file'){await quarantineMatchingCurrentCommandFile(current);return Object.freeze({state:'fresh_preconditions_required',quarantined:true,admissionEligible:false});}
  return r;
 }
 if(cf&&(cf.provenance.marker!=='matching'||cf.xid==='aborted')||nf&&(nf.provenance.marker==='contradictory'||nf.provenance.marker==='matching'&&nf.xid==='aborted'))refuse();
 // A resumed pre-COMMIT rotation is not an unrecoverable secret-file dead end.
 // Full installed source/generation + marker absence + non-progress XID prove
 // the new key was never committed. Quarantine only; no overwrite/deletion or
 // automatic rotate/admission. The next explicit verb reruns fresh preflight.
 if(next&&['aborted','expired'].includes(nf.xid)&&nf.provenance.marker==='absent'){
  if(next.verb!=='rotate'||next.ledgerGeneration!==state.generation)refuse();
  await quarantineConfirmedAbortedNextCommandFile(next);
  return Object.freeze({state:'fresh_preconditions_required',quarantined:true,admissionEligible:false,automaticRetry:false});
 }
 const eligibleFile=nf?.provenance.credential==='current_generation'?next:cf?.provenance.credential==='current_generation'?current:null;
 const durable=(facts)=>facts&&['committed','expired'].includes(facts.xid)&&facts.provenance.marker==='matching';
 if(cf&&cf.xid==='in_progress'||nf&&nf.xid==='in_progress')return Object.freeze({state:'reconcile_required',admissionEligible:false});
 if(eligibleFile&&(eligibleFile===current&&!durable(cf)||eligibleFile===next&&!durable(nf)))refuse();
 const leases=await twoLeases(c);let auth='unobserved';
 if(state.state==='active_exact'&&eligibleFile&&leases==='zero_twice')auth=await positiveAndWrongPassword(eligibleFile,eligibleFile===next?current:null);
 const currentFile=current?cf.provenance.credential==='current_generation'?'current_generation':'stale_generation':'absent';
 const nextFile=next?nf.provenance.credential==='current_generation'?'current_generation':nf.xid==='in_progress'?'pending_generation':'foreign_or_invalid':'absent';
 const classified=classifyCredentialLifecycle({catalog:state.state,currentFile,nextFile,leases:leases==='zero_twice'?'zero_twice':'nonzero',auth,promotion:'unattempted'});
 if(classified.fileAction==='promote_matching_next_file'){
  if(current){if(!durable(cf))refuse();await quarantineMatchingCurrentCommandFile(current);}
  const promoted=await promoteMatchingNextCommandFile(next);if(promoted.state!=='promotion_completed')return promoted;
  const after=await committedFacts(c,source,await readCurrentCommandFile(),await readNextCommandFile());
  if(after.state.state!=='active_exact'||after.currentFacts?.provenance.credential!=='current_generation'||!durable(after.currentFacts)||after.nextFacts||after.leases!=='zero_twice')refuse();
  return Object.freeze({state:'active_exact',admissionEligible:true,promotionCompleted:true,hostAuthenticationProved:true,directoryEntryFsyncClaim:false});
 }
 // Authentication creates its own leases,allended before a fresh TWO checks.
 if(classified.admissionEligible&&await twoLeases(c)!=='zero_twice')return Object.freeze({state:'committed_incomplete',admissionEligible:false});
 return classified;
}
const containSQL=`ALTER ROLE gss_command_runtime NOLOGIN;ALTER ROLE gss_command_fixture_admitter NOLOGIN;
 REVOKE EXECUTE ON FUNCTION gss.fixture_open_command(uuid,uuid,uuid,text,integer,text,text,uuid,uuid,text),
 gss.fixture_issue_action_proof(uuid,uuid,uuid,text,integer,text,text,uuid,uuid,text,integer) FROM gss_command_fixture_admitter;
 REVOKE EXECUTE ON FUNCTION gss.claim_command_receipt(),gss.consume_action_proof(uuid),gss.append_command_audit(),gss.finalize_command_receipt(uuid) FROM gss_command_runtime;
 REVOKE INSERT(organization_id,principal_id,replay_key,command,version,definition_digest,public_digest,epoch) ON gss.command_receipts FROM gss_command_runtime;`;
const reactivateFunctionGrantsSQL=`GRANT EXECUTE ON FUNCTION gss.fixture_open_command(uuid,uuid,uuid,text,integer,text,text,uuid,uuid,text),
 gss.fixture_issue_action_proof(uuid,uuid,uuid,text,integer,text,text,uuid,uuid,text,integer) TO gss_command_fixture_admitter;
 GRANT EXECUTE ON FUNCTION gss.claim_command_receipt(),gss.consume_action_proof(uuid),gss.append_command_audit(),gss.finalize_command_receipt(uuid) TO gss_command_runtime;`;
async function maintain(verb){
 assertFixedCommandProcess();const approval=assertCompleteCommandReview(),authority=verb==='apply'?reserveApplyInvocation(approval):null;const source=fixedCommandSource();await verifiedRunningStack();
 let c,held=false,transaction=false,commitIssued=false,committed=false,ownFile=null,ownFileWritten=null,ownStamp=null,attempt=null,phase='connect',ownFileWriteAttempted=false;
 let failureSnapshot=null,failureStage=null,elapsedMs=0;const started=performance.now();
 const recovery={rollback:'not_attempted',independentRead:'not_checked',xid:'not_checked',capture:'not_checked',cleanup:'not_needed',unlock:'not_held',connection:'not_open'};
 const query=async(...args)=>{setInspectionStage('maintenance',phase==='credential-message'?'credentials':'transaction');return c.query(...args);};
 async function operation(){try{
  c=await connectBoot();await lock(c);held=true;
  if(verb==='initialize'){
   phase='initialize-original-preflight';await query('BEGIN ISOLATION LEVEL READ COMMITTED READ ONLY');
   const rolesPresent=(await query("SELECT count(*)::int AS n FROM pg_roles WHERE left(rolname,12)='gss_command_'")).rows[0].n;
   if(rolesPresent!==0&&rolesPresent!==3)refuse();await inspectCommandState(c,source,{original:rolesPresent===0});await noLeases(c);
   if((await query('COMMIT')).command!=='COMMIT')refuse();
   // Empty protected folder initialization is explicit and OUTSIDE a DB
   // transaction,attempt reservation and any key generation.
   const privacy=await initializeCommandPrivateFolder();
   return Object.freeze({state:'private_folder_ready',...privacy,newCredentialsCreated:false,sqlApplied:false});
  }
  if(verb==='reconcile')return await reconcileLocked(c,source);
  phase='files';const current=await readCurrentCommandFile(),next=await readNextCommandFile();
  if(verb==='apply'){
   const rolesPresent=(await query("SELECT count(*)::int AS count FROM pg_roles WHERE left(rolname,12)='gss_command_'")).rows[0].count;
   if(rolesPresent!==0&&rolesPresent!==3)refuse();
   if(current||next||rolesPresent===3){
    const reconciled=await reconcileLocked(c,source);
    return Object.freeze({...reconciled,alreadyExact:reconciled.state==='active_exact'&&reconciled.admissionEligible===true,applied:[]});
   }
  }else if(next)refuse();
  if(['apply','rotate'].includes(verb)){
   phase='private-privacy-preflight';const privacy=await inspectCommandPrivatePrivacy();if(!privacy.privateFolderPresent)refuse();
  }
  phase='begin';setInspectionStage('maintenance','transaction');await query('BEGIN ISOLATION LEVEL READ COMMITTED READ WRITE');transaction=true;ownStamp=await commandTransactionStamp(c,'supabase_admin');
  phase='original-or-installed-preflight';const before=await inspectCommandState(c,source,{original:verb==='apply'});if(verb!=='contain')await noLeases(c);
  if(verb==='apply'){
   phase='reserve-bounded-attempt';attempt=reserveAttempt(authority.attemptRef);
   phase='publish-reviewed-source-archive';await publishFixedSourceArchive(c);
   const clients=freshCommandClients();
   // All role phases fixed and source-hashed. FinalBOOT phases below use ONE
   // simple-query transport. No arbitrary SQL/archive execution accepted.
   for(let i=0;i<11;i++){
    const stage=source.phases[i];phase='apply-phase-'+String(i+1);await query('SET LOCAL ROLE '+stage.currentRole);await assertSameCommandTransaction(c,ownStamp,stage.currentRole);
    await query(stage.sql);await assertSameCommandTransaction(c,ownStamp,stage.currentRole);
   }
   await query('SET LOCAL ROLE supabase_admin');
   const generation=(await query('SELECT generation::text FROM gss_meta.command_ledger_generation WHERE id')).rows[0]?.generation;
   const marker=await appendMarker(c,'apply',generation);if(marker.top_xid!==ownStamp.top_xid)refuse();ownFile=privateFor(marker,clients);
   phase='credential-message-preflight';await requireCredentialMessage(c);phase='credential-message';
   await query(source.phases[11].sql+'\n'+credentialSQL(ownFile)+'\n'+source.phases[12].sql);
   await assertSameCommandTransaction(c,ownStamp,'supabase_admin');await query('SET LOCAL ROLE postgres');phase='final-assertion-fragment';await query(source.phases[13].sql);
   await assertSameCommandTransaction(c,ownStamp,'postgres');phase='whole-candidate-before-history';await inspectCommandState(c,source,{historyInserted:false});
   phase='private-file-fsync';setInspectionStage('maintenance','private_file');ownFileWriteAttempted=true;ownFileWritten=await writeCurrentCommandFile(ownFile);ownFile=ownFileWritten;
   phase='history';await query('SET LOCAL ROLE postgres');await query('INSERT INTO gss_meta.migrations(version,sha256) VALUES($1,$2)',[archiveName,archiveSha256]);
  }else{
   const cf=current?await inspectBound(c,source,current,before,'read_write'):null;
   if(current&&(!['committed','expired'].includes(cf.xid)||cf.provenance.marker!=='matching'))refuse();
   if(verb==='contain'){
    phase='contain';await query('SET LOCAL ROLE supabase_admin');await query(containSQL);await appendMarker(c,'contain',before.generation);
   }else{
    // Explicit rotate may recover missing/stale current credentials only after
    // exact installed source/provenance/zero leases. It never overwrites either
    // pathname and never treats reconcile as permission to generate a secret.
    phase='rotate-marker';await query('SET LOCAL ROLE supabase_admin');const marker=await appendMarker(c,'rotate',before.generation);
    if(marker.top_xid!==ownStamp.top_xid)refuse();ownFile=privateFor(marker,freshCommandClients());
    // Regrant with the original declared GRANTOR owner,not BOOT. Contains the
    // same entry/column capabilities;full active candidate catches grantor drift.
    await query('SET LOCAL ROLE gss_command_guard');await query(reactivateFunctionGrantsSQL);
    await query('SET LOCAL ROLE gss_migration_owner');await query('GRANT INSERT(organization_id,principal_id,replay_key,command,version,definition_digest,public_digest,epoch) ON gss.command_receipts TO gss_command_runtime;');
    await query('SET LOCAL ROLE supabase_admin');phase='credential-message-preflight';await requireCredentialMessage(c);phase='credential-message';
    // ClosingREVOKE transientedge is idempotent but can emit harmless NOTICE;
    // source gate must explicitly review the rotate transport. No notice body.
    await query(source.phases[11].sql+'\n'+credentialSQL(ownFile)+'\n'+source.phases[12].sql);
    phase='next-file-fsync';setInspectionStage('maintenance','private_file');ownFileWriteAttempted=true;ownFileWritten=await writeNextCommandFile(ownFile);ownFile=ownFileWritten;
   }
  }
  phase='final-whole-candidate';const final=await inspectCommandState(c,source);await assertSameCommandTransaction(c,ownStamp,'supabase_admin');
  if(verb==='contain'&&final.state!=='contained_exact'||verb!=='contain'&&final.state!=='active_exact')refuse();if(verb!=='contain')await noLeases(c);
  phase='commit';setInspectionStage('maintenance','transaction');commitIssued=true;const tag=await query('COMMIT');transaction=false;if(tag.command!=='COMMIT')refuse();committed=true;
  phase='independent-outcome';const independent=await readonlyIndependent(source);if(independent.state!==final.state)refuse();
  phase='postcommit-leases';if(await twoLeases(c)!=='zero_twice')return Object.freeze({state:'committed_incomplete',admissionEligible:false});
  phase='credential-reconcile';return await reconcileLocked(c,source);
 }catch(e){
  failureStage=inspectionSnapshot();failureSnapshot=snapshotRefusal(e,failureStage);elapsedMs=performance.now()-started;
  recovery.cleanup=ownFileWritten?'own_file_retained':ownFileWriteAttempted?'refused':'not_needed';
  if(commitIssued)recovery.rollback='commit_already_issued';
  else if(transaction){try{const r=await query('ROLLBACK');recovery.rollback=r.command==='ROLLBACK'?'acknowledged':'failed';if(recovery.rollback==='acknowledged')transaction=false;}catch{recovery.rollback='failed';}}
  if(recovery.rollback==='acknowledged'&&ownStamp){
   let independent=null;try{independent=await readonlyIndependent(source);recovery.independentRead=independent.state==='original_0006'?'original':'other';}catch{recovery.independentRead='failed';}
   if(independent){
    let v;try{v=await connectBoot(true);await v.query('BEGIN ISOLATION LEVEL READ COMMITTED READ ONLY');recovery.xid=await commandXidOutcome(v,{topXid:ownStamp.top_xid});if((await v.query('COMMIT')).command!=='COMMIT')recovery.xid='failed';}
    catch{recovery.xid='failed';}finally{if(v)try{await v.end();}catch{recovery.xid='failed';}}
    const abortedOriginal=recovery.xid==='aborted'&&independent.state==='original_0006'&&verb==='apply';
    const abortedRotation=recovery.xid==='aborted'&&['active_exact','contained_exact'].includes(independent.state)&&verb==='rotate';
    if((abortedOriginal||abortedRotation)&&ownFileWritten){try{await removeOnlyOwnAbortedCommandFile(ownFileWritten);recovery.cleanup='own_aborted_file_removed';}catch{recovery.cleanup='refused';}}
    else if(ownFileWritten)recovery.cleanup='own_file_retained';
    const token=comparisonTokenForRefusal(e);
    if(abortedOriginal&&token){try{
      const capture=releaseCaptureAfterConfirmedRollback(token,{rollbackTag:'ROLLBACK',independentOriginalExact:true,xidOutcome:'aborted'});
      const p='diagnostics/GSS_foundation-command-deparse-refusal29-'+attempt+'_20261005.json';
      const fd=fs.openSync(p,'wx');try{fs.writeFileSync(fd,JSON.stringify({attempt,maximum:3,sourceArchiveSha256:archiveSha256,capture,rollbackAcknowledged:true,originalStateExact:true,retryApproved:false},null,2));fs.fsyncSync(fd);}finally{fs.closeSync(fd);}
      recovery.capture='written';
    }catch{recovery.capture='failed';}}
    else recovery.capture='not_eligible';
   }
  }
  const classified=classifyRefusal(failureSnapshot,failureStage),safe={state:commitIssued&&!committed?'reconcile_required':committed?'committed_incomplete':'refused',phase,
   sqlState:classified.sqlState,admissionEligible:false,valuesWithheld:true,automaticRetry:false};
  if(ownStamp)safe.topXid=ownStamp.top_xid;return Object.freeze(safe);
 }finally{
  if(c){
   if(transaction&&!commitIssued){try{const r=await query('ROLLBACK');if(recovery.rollback!=='acknowledged')recovery.rollback=r.command==='ROLLBACK'?'acknowledged':'failed';}catch{recovery.rollback='failed';}}
   if(held)try{await unlock(c);recovery.unlock='released';}catch{recovery.unlock='failed';}
   try{await c.end();recovery.connection='closed';}catch{recovery.connection='failed';recordConnectionCloseFailure();}
  }
  if(connectionCloseFailureRecorded())recovery.connection='failed';
 }}
 const result=await operation();
 // Snapshot was taken before recovery; the record is written only after the
 // terminal primary-handle cleanup. No attempt is invented for preflight or
 // contain/rotate failures. Writer failure never modifies this result.
 if(failureSnapshot&&attempt!==null)writeAttemptRefusal(failureSnapshot,{attempt,operation:verb,maintenancePhase:phase,stage:failureStage,elapsedMs,recovery});
 if((recovery.unlock==='failed'||recovery.connection==='failed')&&!['refused','reconcile_required','committed_incomplete','rotation_promotion_failed'].includes(result.state))return Object.freeze({...result,state:commitIssued?'committed_incomplete':'refused',admissionEligible:false,closureUnproved:true,valuesWithheld:true,automaticRetry:false});
 return result;
}

if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
 if(process.argv.length!==3||process.argv[2]!=='apply'){console.error('GSS command maintenance arguments refused;values withheld');process.exitCode=1;}
 else withInspectionTrace(()=>maintain(process.argv[2])).then(r=>{console.log(JSON.stringify(r));if(['refused','reconcile_required','committed_incomplete','rotation_promotion_failed'].includes(r.state))process.exitCode=1;})
 .catch(()=>{console.error('GSS command maintenance source/target gate refused;values withheld');process.exitCode=1;});
}
