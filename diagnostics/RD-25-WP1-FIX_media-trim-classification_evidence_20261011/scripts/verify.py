from pathlib import Path
import json,hashlib,subprocess,shutil
W=Path(__file__).resolve().parent; R=W.parents[1]; E=R/'diagnostics/RD-25-WP1-FIX_media-trim-classification_evidence_20261011'
S=R/'work/rd25-wp1-fix-phase0-20261011/source'; M=json.loads((E/'manifest.json').read_text()); P=R/'patches'/M['patch']
PHP='C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'; checks=[]
sha=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
def check(n,b,d=None):
 checks.append({'name':n,'pass':bool(b),'detail':d}); assert b,(n,d)
def cmd(args,cwd=None):
 p=subprocess.run([PHP,*map(str,args)],cwd=cwd,capture_output=True,encoding='utf8'); return p.returncode,p.stdout+p.stderr
def fixture(n):
 f=W/'verify3'/n
 assert not f.exists(),f
 for rel in M['targets']:
  p=f/rel; p.parent.mkdir(parents=True,exist_ok=True); shutil.copyfile(S/rel,p)
 shutil.copyfile(P,f/P.name); return f
def run(f,*args):return cmd([f/P.name,*args],f)
def hashes(f,which):return all(sha(f/r)==t[which+'_sha256'] for r,t in M['targets'].items())
check('runner syntax',cmd(['-l',P])[0]==0)
for r in M['targets']:check('candidate syntax '+r,cmd(['-l',W/'expected'/r])[0]==0)
f=fixture('runner-main'); c,o=run(f,'--dry-run'); (E/'dry-run.txt').write_text(o)
check('dry run no backup or target writes',c==0 and hashes(f,'before') and not (f/'_patch_backups').exists(),o)
c,o=run(f); (E/'apply.txt').write_text(o)
check('apply all three exact candidates, lint and self-delete',c==0 and hashes(f,'after') and o.count('php_lint=ok:')==6 and not (f/P.name).exists(),o)
back=next((f/'_patch_backups').iterdir()); check('verified backups',hashes(back/'original','before'))
shutil.copyfile(P,f/P.name); c,o=run(f); (E/'repeat.txt').write_text(o); check('idempotent repeat',c==0 and 'already_applied=yes' in o and hashes(f,'after') and not (f/P.name).exists())
rel=next(iter(M['targets'])); target=f/rel; target.write_bytes(target.read_bytes()+b'\n/* owner drift */\n'); drift=sha(target)
shutil.copyfile(P,f/P.name); c,o=run(f); check('idempotency refuses modified source',c!=0 and 'applied_source_changed' in o and sha(target)==drift)
c,o=run(f,'--rollback='+back.relative_to(f).as_posix()); check('rollback refuses newer source',c!=0 and 'rollback_source_changed' in o and sha(target)==drift)
shutil.copyfile(W/'expected'/rel,target); c,o=run(f,'--rollback='+back.relative_to(f).as_posix()); (E/'rollback.txt').write_text(o)
check('rollback restores all three exact sources',c==0 and hashes(f,'before') and not (f/P.name).exists())
shutil.copyfile(P,f/P.name); c,o=run(f); check('reapply after rollback',c==0 and hashes(f,'after'))
for kind in ['drift','missing','partial','anchor','bad-lint','write-failure']:
 f=fixture('runner-'+kind); runner=f/P.name
 if kind=='drift': (f/rel).write_bytes((f/rel).read_bytes()+b'\n/* drift */\n')
 if kind=='missing': (f/rel).unlink()
 if kind=='partial': shutil.copyfile(W/'expected'/rel,f/rel)
 if kind=='anchor': runner.write_text(runner.read_text().replace('private const RD25_MEDIA_VERSION = 3;', 'private const RD25_MEDIA_VERSION = 999;',1),newline='\n')
 if kind=='bad-lint':
  valid=(W/'expected'/rel).read_bytes(); invalid=valid.replace(b'$measurement = $this->rd25Measure',b'$measurement = ; $this->rd25Measure')
  runner.write_text(runner.read_text().replace('$measurement = $this->rd25Measure','$measurement = ; $this->rd25Measure').replace(M['targets'][rel]['after_sha256'],hashlib.sha256(invalid).hexdigest()),newline='\n')
 if kind=='write-failure':
  s=runner.read_text(); s=s.replace("$written[] = $relative;\n\t\trd25Lint", "$written[] = $relative;\n\t\trd25Fail('fixture_post_write_failure');\n\t\trd25Lint"); runner.write_text(s,newline='\n')
 before={r:sha(f/r) if (f/r).exists() else None for r in M['targets']}; c,o=run(f)
 check(kind+' rejects/restores without source loss',c!=0 and before=={r:sha(f/r) if (f/r).exists() else None for r in M['targets']},o)
 if kind=='write-failure':check('failure restores written target', 'restored_after_failure='+rel in o and 'restore=ok' in o,o)
 if kind=='bad-lint':check('invalid candidate rejected by lint before write','php_lint_failed' in o,o)
f6=R/'patches/RD-25-WP1_featured-image-payload_20261010.php'; fr='extension/opencart/catalog/controller/module/featured.php'
for first in ['fix','f6']:
 f=fixture('compose-'+first); (f/fr).parent.mkdir(parents=True); shutil.copyfile(R/'work/rd25-featured-20261010/source'/fr,f/fr); shutil.copyfile(f6,f/f6.name)
 for p in ([P,f6] if first=='fix' else [f6,P]):c,o=cmd([f/p.name],f);check('composition '+first+' '+p.name,c==0,o)
 check('composition '+first+' exact four files',hashes(f,'after') and (f/fr).read_bytes()==(R/'work/rd25-featured-20261010/expected'/fr).read_bytes())
# Actual printing/throwing handlers, warm decode count, race and handler restoration.
probe=(R/'work/rd25-cache-miss-20261010/cold-probe.php').read_text().replace('rd25-media-v3-','rd25-media-v4-'); (W/'cold-probe.php').write_text(probe)
for mode in ['printing','throwing']:
 f=fixture('cold-'+mode)
 for r in M['targets']:shutil.copyfile(W/'expected'/r,f/r)
 c,o=cmd([W/'cold-probe.php',f,mode]); check('cold probe completes '+mode,c==0,o[-500:]); data=json.loads(o); (E/('cold-'+mode+'.json')).write_text(json.dumps(data,indent=2))
 check('cold and warm warning-free '+mode,not data['cold']['warnings'] and not data['cold']['exception'] and not data['cold']['output'] and not data['warm']['warnings'])
 check('warm decodes zero '+mode,data['warm_GD_decodes']==0 and data['cold']['value']==data['warm']['value'])
 check('handler restored '+mode,data['site_handler_restored'] and data['error_reporting_mask_unchanged'])
 for n in ['disappears_after_guard','unreadable_guard','directory_not_metadata']:
  d=data['extra'][n];check(n+' '+mode,d['value']==[] and not d['warnings'] and not d['output'] and not d['exception'])
 check('unrelated warning remains visible '+mode,len(data['unrelated_warning']['warnings'])==1)
# F6 full payload mock uses actual changed Thumb controller and the approved companion.
probe=(R/'work/rd25-featured-20261010/payload-probe.php').read_text().replace("dirname(__DIR__).'/rd25-wp1-20261009/expected/catalog/controller/product/thumb.php'","__DIR__.'/expected/catalog/controller/product/thumb.php'").replace("'width'=>500,'height'=>1000,'kind'=>'cut'","'width'=>500,'height'=>1000,'crop_width'=>500,'crop_height'=>1000,'kind'=>'cut'")
(W/'payload-probe.php').write_text(probe)
payloads={}
for case in ['normal','duplicates','empty','mixed','missing','hidden','invalid','vertical','no-products']:
 c,o=cmd([W/'payload-probe.php',R/'work/rd25-featured-20261010/expected'/fr,case]);check('F6 payload '+case,c==0,o[-500:]); d=json.loads(o); payloads[case]=d
 if case=='normal':check('Featured passes image without additional getProduct calls',d['first_thumb_reads']==0 and d['first_featured_reads']==24)
(E/'f6-payload.json').write_text(json.dumps(payloads,indent=2,ensure_ascii=False),encoding='utf8')
(E/'runner-checks.json').write_text(json.dumps({'checks':checks,'passed':sum(c['pass'] for c in checks),'total':len(checks)},indent=2)); print(json.dumps({'passed':len(checks),'total':len(checks)}))
