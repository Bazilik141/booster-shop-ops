import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {spawnSync} from 'node:child_process';
const root=process.cwd(),base=path.join(root,'work/pay003');
const php='C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
const name='PAY-003_credit-wait-recovery_20260831.php';
const runner=fs.readFileSync(path.join(root,'patches',name),'utf8');
const spec=JSON.parse(fs.readFileSync(path.join(base,'manifest.json')));
const hash=s=>crypto.createHash('sha256').update(s).digest('hex');
const check=(ok,msg)=>{if(!ok)throw Error(msg);};
function fixture(label){
 const dir=fs.mkdtempSync(path.join(base,label+'-'));
 for(const f of [...Object.keys(spec.guards),...Object.keys(spec.files).filter(f=>spec.files[f].before!==null)]){
  fs.mkdirSync(path.dirname(path.join(dir,f)),{recursive:true});fs.copyFileSync(path.join(base,'source',f),path.join(dir,f));
 }
 fs.writeFileSync(path.join(dir,'config.php'),'<?php // synthetic config, never executed\n');
 fs.writeFileSync(path.join(dir,name),runner);return dir;
}
function run(dir,file=name,args=[]){const r=spawnSync(php,[...(file.endsWith('.test.php')?['-n']:[]),file,...args],{cwd:dir,encoding:'utf8'});if(r.error)throw r.error;return r;}
const dir=fixture('runner-clean');let r=run(dir);check(r.status===0,r.stdout+r.stderr);
for(const [f,e]of Object.entries(spec.files))check(hash(fs.readFileSync(path.join(dir,f)))===e.after,'after hash '+f);
check(!fs.existsSync(path.join(dir,name)),'self delete');
const backup=path.join(dir,'_patch_backups',fs.readdirSync(path.join(dir,'_patch_backups'))[0]);
for(const[f,e]of Object.entries(spec.files))if(e.before!==null)check(hash(fs.readFileSync(path.join(backup,'original',f)))===e.before,'backup hash');
r=run(dir,path.join(root,'scripts/tests/pay003.test.php'),[dir]);check(r.status===0,r.stdout+r.stderr);console.log(r.stdout.trim());
fs.writeFileSync(path.join(dir,name),runner);r=run(dir);check(r.status===0&&r.stdout.includes('already_applied=yes'),'idempotence');
r=run(dir,path.join(backup,'rollback.php'));check(r.status===0,r.stdout+r.stderr);
for(const[f,e]of Object.entries(spec.files))check(e.before===null?!fs.existsSync(path.join(dir,f)):hash(fs.readFileSync(path.join(dir,f)))===e.before,'rollback '+f);
console.log('PASS apply hashes, originals, repeat, exact explicit rollback and actual generated-code tests');
const drift=fixture('runner-drift'),first=Object.keys(spec.files)[0];fs.appendFileSync(path.join(drift,first),'\n// owner change\n');const h=hash(fs.readFileSync(path.join(drift,first)));r=run(drift);
check(r.status!==0&&hash(fs.readFileSync(path.join(drift,first)))===h&&!fs.existsSync(path.join(drift,'_patch_backups')),'drift fails before writes');
const existing=fixture('runner-existing'),added=Object.keys(spec.files).find(f=>spec.files[f].before===null);fs.mkdirSync(path.dirname(path.join(existing,added)),{recursive:true});fs.writeFileSync(path.join(existing,added),'owner-created');r=run(existing);check(r.status!==0&&fs.readFileSync(path.join(existing,added),'utf8')==='owner-created','new path conflict');
const bad=fixture('runner-lint'),encoded=runner.match(/base64_decode\('([^']+)'/)[1],fault=structuredClone(spec);
fault.files[first].edits.push({before:'<?php',after:'<?php !!! syntax fault'});let text=fs.readFileSync(path.join(bad,first),'utf8');for(const e of fault.files[first].edits)text=text.replace(e.before,e.after);fault.files[first].after=hash(text);
fs.writeFileSync(path.join(bad,name),runner.replace(encoded,Buffer.from(JSON.stringify(fault)).toString('base64')));r=run(bad);check(r.status!==0&&r.stderr.includes('PHP lint failed'),'candidate lint fails');check(hash(fs.readFileSync(path.join(bad,first)))===spec.files[first].before,'lint leaves source untouched');
const partial=fixture('runner-partial');fs.writeFileSync(path.join(partial,name),runner.replace('pay003Write($target,$candidates[$file]); pay003Lint($target);','pay003Write($target,$candidates[$file]); pay003Lint($target); if (count($written) === 7) throw new RuntimeException("injected partial-write fault");'));r=run(partial);check(r.status!==0&&r.stderr.includes('source restored'),'partial restore');for(const[f,e]of Object.entries(spec.files))check(e.before===null?!fs.existsSync(path.join(partial,f)):hash(fs.readFileSync(path.join(partial,f)))===e.before,'partial rollback '+f);
console.log('PASS source drift, new-file conflict, lint refusal, injected partial-write restores old/removes new files');
console.log(JSON.stringify({result:'ok',clean:dir,backup}));
