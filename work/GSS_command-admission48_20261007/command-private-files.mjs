// UNREVIEWED fixed private filesystem lifecycle. No CLI/import-time IO.
// Sole maintenance tool must independently establish DB outcomes before moves.
import fs from 'node:fs';import path from 'node:path';import {execFile,execFileSync} from 'node:child_process';import {fileURLToPath} from 'node:url';
import {assertFixedCommandProcess} from '../GSS_command-ledger47_20261006/command-process-fences.mjs';import {assertCommandFile} from '../GSS_command-ledger28_20261005/command-private-file-format.mjs';
import {assertCompleteCommandReview} from './source-contract.mjs';
const root='C:\\Users\\14bez\\Downloads\\Booster Shop\\booster-shop-ops\\gss\\.local\\command-ledger';
const ps='C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
const script=fileURLToPath(new URL('../GSS_command-ledger47_20261006/private-command-files.ps1',import.meta.url));
const refuse=()=>{throw Error('GSS private command file operation refused;values withheld');};
const uuid=/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/;
const created=new WeakMap();
function privateChildEnvironment(){return Object.fromEntries(['SystemRoot','SYSTEMROOT','WINDIR','PATH','Path','PATHEXT','TEMP','TMP','USERPROFILE','APPDATA','LOCALAPPDATA'].filter(k=>process.env[k]).map(k=>[k,process.env[k]]));}
export function assertCommandPathsIgnored(){
 if(arguments.length)refuse();assertFixedCommandProcess();
 for(const relative of ['gss/.local/command-ledger/','gss/.local/command-ledger/current.json','gss/.local/command-ledger/next.json','gss/.local/command-ledger/quarantine/probe.json']){
  try{execFileSync('C:\\Program Files\\Git\\cmd\\git.exe',['check-ignore','-q','--',relative],{cwd:'C:\\Users\\14bez\\Downloads\\Booster Shop\\booster-shop-ops',env:privateChildEnvironment(),windowsHide:true,stdio:'ignore'});}catch{refuse();}
 }
 return Object.freeze({ignoredPaths:4,valuesWithheld:true});
}
function location(name){
 const p=path.join(root,name);
 if(!p.startsWith(root+path.sep)||!['current.json','next.json'].includes(name))refuse();return p;
}
function exactPath(p,directory=false){
 if(fs.realpathSync.native(p)!==p||fs.lstatSync(p).isSymbolicLink()||fs.statSync(p).isDirectory()!==directory)refuse();
}
async function operation(action,run=''){
 assertFixedCommandProcess();assertCompleteCommandReview();exactPath(script);
 if(action!=='inspect')assertCommandPathsIgnored();
 return new Promise((resolve,reject)=>execFile(ps,['-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',script,'-Action',action,'-RunRef',run],
  {env:privateChildEnvironment(),windowsHide:true,timeout:5000,maxBuffer:1024,encoding:'utf8'},(error,stdout,stderr)=>{
   if(error||stderr){reject(Error('GSS private command file operation refused;values withheld'));return;}
   try{
    const v=JSON.parse(stdout);
    if(Object.keys(v).sort().join(',')!=='directoryEntryFsyncClaim,identityValuesWithheld,ntfsPrivacyVerified,privateFolderPresent,privateFolderProtected,trustedAllowSidCount,trustedOwnerVerified'||
     typeof v.ntfsPrivacyVerified!=='boolean'||v.ntfsPrivacyVerified!==v.privateFolderPresent||v.identityValuesWithheld!==true||v.directoryEntryFsyncClaim!==false||
     v.privateFolderProtected!==v.privateFolderPresent||v.trustedOwnerVerified!==true||v.trustedAllowSidCount!==(v.privateFolderPresent?3:0)||action!=='inspect'&&v.ntfsPrivacyVerified!==true)refuse();
    resolve(Object.freeze(v));
   }catch{reject(Error('GSS private command file operation refused;values withheld'));}
  }));
}
export async function inspectCommandPrivatePrivacy(){if(arguments.length)refuse();return operation('inspect');}
export async function initializeCommandPrivateFolder(){
 if(arguments.length)refuse();await operation('initialize');exactPath(root,true);
 exactPath(path.join(root,'quarantine'),true);
 return Object.freeze({privateFolderReady:true,ntfsPrivacyVerified:true,privateFolderProtected:true,trustedOwnerVerified:true,trustedAllowSidCount:3,directoryEntryFsyncClaim:false});
}
function readFixed(name){
 assertFixedCommandProcess();const p=location(name);
 if(!fs.existsSync(p))return null;exactPath(root,true);exactPath(p);
 const stat=fs.statSync(p,{bigint:true});if(!stat.isFile()||stat.nlink!==1n||stat.size>4096n||stat.size===0n)refuse();
 try{return assertCommandFile(JSON.parse(fs.readFileSync(p,'utf8')));}catch{refuse();}
}
export async function readCurrentCommandFile(){if(arguments.length)refuse();await operation('inspect');return readFixed('current.json');}
export async function readNextCommandFile(){if(arguments.length)refuse();await operation('inspect');return readFixed('next.json');}
async function writeFixed(name,input){
 assertCommandPathsIgnored();
 assertFixedCommandProcess();const privacy=await operation('inspect');if(!privacy.ntfsPrivacyVerified)refuse();exactPath(root,true);
 const value=assertCommandFile(input),p=location(name),bytes=Buffer.from(JSON.stringify(value)+'\n');
 if(bytes.length>4096||name==='current.json'&&value.verb!=='apply'||name==='next.json'&&value.verb!=='rotate')refuse();
 // Sole fixed rotate tool permits an exclusive next slot after an exact
 // installed-state/zero-lease preflight,including missing current directory entry.
 // Existing current,if present,is retained until outcome reconciliation.
 let fd;
 try{fd=fs.openSync(p,'wx');fs.writeFileSync(fd,bytes);fs.fsyncSync(fd);}finally{if(fd!==undefined)fs.closeSync(fd);bytes.fill(0);}
 // Failure retains file;no blind deletion of a possibly durable pending file.
 await operation('inspect');const reread=readFixed(name);
 if(JSON.stringify(reread)!==JSON.stringify(value))refuse();created.set(value,name);return value;
}
export function writeCurrentCommandFile(value){if(arguments.length!==1)refuse();return writeFixed('current.json',value);}
export function writeNextCommandFile(value){if(arguments.length!==1)refuse();return writeFixed('next.json',value);}
export async function removeOnlyOwnAbortedCommandFile(value){
 if(arguments.length!==1)refuse();await operation('inspect');const name=created.get(value);
 if(!name||JSON.stringify(readFixed(name))!==JSON.stringify(value))refuse();
 // The fixed maintenance caller additionally verifies actual abort/original
 // catalog before this call. Resumed objects cannot acquire the WeakMap token.
 fs.unlinkSync(location(name));created.delete(value);await operation('inspect');return Object.freeze({removedOnlyThisRunFile:true});
}
export async function quarantineMatchingCurrentCommandFile(value){
 if(arguments.length!==1)refuse();await operation('inspect');const exact=assertCommandFile(value);
 if(JSON.stringify(readFixed('current.json'))!==JSON.stringify(exact))refuse();
 await operation('quarantine_current',exact.runRef);
 const q=path.join(root,'quarantine',exact.runRef+'.json');exactPath(q);
 let persisted;try{persisted=assertCommandFile(JSON.parse(fs.readFileSync(q,'utf8')));}catch{refuse();}
 if(JSON.stringify(persisted)!==JSON.stringify(exact)||fs.existsSync(location('current.json')))refuse();
 return Object.freeze({quarantined:true,overwritten:false,reusable:false,deleted:false});
}
export async function quarantineConfirmedAbortedNextCommandFile(value){
 if(arguments.length!==1)refuse();await operation('inspect');const exact=assertCommandFile(value);
 if(exact.verb!=='rotate'||JSON.stringify(readFixed('next.json'))!==JSON.stringify(exact))refuse();
 // Only the fixed caller may establish exact installed generation, marker
 // absence and known-aborted/expired XID. Never load or delete this file again.
 await operation('quarantine_next',exact.runRef);
 const q=path.join(root,'quarantine',exact.runRef+'.json');exactPath(q);
 let persisted;try{persisted=assertCommandFile(JSON.parse(fs.readFileSync(q,'utf8')));}catch{refuse();}
 if(JSON.stringify(persisted)!==JSON.stringify(exact)||fs.existsSync(location('next.json')))refuse();
 return Object.freeze({quarantined:true,overwritten:false,reusable:false,deleted:false});
}
export async function promoteMatchingNextCommandFile(value){
 if(arguments.length!==1)refuse();await operation('inspect');const exact=assertCommandFile(value);
 if(!uuid.test(exact.runRef)||fs.existsSync(location('current.json'))||JSON.stringify(readFixed('next.json'))!==JSON.stringify(exact))refuse();
 try{
  await operation('promote_next',exact.runRef);
  if(JSON.stringify(readFixed('current.json'))!==JSON.stringify(exact)||fs.existsSync(location('next.json')))refuse();
  return Object.freeze({state:'promotion_completed',directoryEntryFsyncClaim:false});
 }catch{
  // No automatic cleanup/overwrite:independent reconciliation observes both.
  return Object.freeze({state:'rotation_promotion_failed',directoryEntryFsyncClaim:false,admissionEligible:false});
 }
}
