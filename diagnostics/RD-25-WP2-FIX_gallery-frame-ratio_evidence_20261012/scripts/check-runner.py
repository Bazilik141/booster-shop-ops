from pathlib import Path
import subprocess,json,hashlib,shutil
w=Path(__file__).resolve().parent;repo=w.parent.parent;spec=json.loads((w/'spec.json').read_text());old=repo/'work/rd25-wp2-wp3-phase0-20261012/source';sha=lambda b:hashlib.sha256(b).hexdigest();php='C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';name='RD-25-WP2-FIX_gallery-frame-ratio_20261012.php';base=w/'runner-fixtures-final';base.mkdir(exist_ok=False);records=[]
files=set(spec['targets'])|set(spec['context'])|{a['template'] for a in spec['ga4_anchors']}
def fixture(label,after=False):
 r=base/label;r.mkdir()
 for p in files:
  s=w/('after' if after else 'source')/p
  if not s.exists():s=old/p
  f=r/p;f.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(s,f)
 return r
def hashes(r):return{p:sha((r/p).read_bytes()) for p in files if (r/p).exists()}
def check(label,value):
 if not value:raise RuntimeError(label)
 records.append({'check':label,'pass':True})
def run(r,args=(),success=True,mutate=None):
 code=(repo/'patches'/name).read_text(encoding='utf-8');code=mutate(code) if mutate else code;(r/name).write_text(code,encoding='utf-8');p=subprocess.run([php,str(r/name),*args],cwd=r,capture_output=True,text=True,encoding='utf-8');(base/f'{r.name}-{len(records)}.txt').write_text(p.stdout+p.stderr,encoding='utf-8');check(r.name+'/exit',(p.returncode==0)==success);return p.stdout
r=fixture('apply-repeat-rollback');before=hashes(r);run(r,['--dry-run']);check('Dry run zero writes',hashes(r)==before and(r/name).exists());run(r);check('Self delete',not(r/name).exists());after=hashes(r)
for p in spec['targets']:check('Candidate byte parity '+p,(r/p).read_bytes()==(w/'after'/p).read_bytes())
check('Repeat','already_applied=yes' in run(r) and hashes(r)==after);p=r/'catalog/view/template/common/header.twig';p.write_bytes(p.read_bytes().replace(b'rd25-wp2-fix-20261012',b'later-owner-token'));later=hashes(r);check('Repeat preserves later token','already_applied=yes' in run(r) and hashes(r)==later);backup=next((r/'_patch_backups').glob('RD-25-WP2-FIX*'));run(r,['--rollback='+backup.relative_to(r).as_posix()],False);check('Rollback refuses changed header',hashes(r)==later);p.write_bytes((w/'after/catalog/view/template/common/header.twig').read_bytes());run(r,['--rollback='+backup.relative_to(r).as_posix()]);check('Rollback exact WP2+WP3',hashes(r)==before)
for kind in ['source','partial','missing','ga4','context','header']:
 r=fixture(kind);p='catalog/controller/product/product.php'
 if kind=='source':(r/p).write_bytes((r/p).read_bytes()+b'\n// drift\n')
 if kind=='partial':(r/p).write_bytes((w/'after'/p).read_bytes())
 if kind=='missing':(r/p).unlink()
 if kind=='ga4':(r/'extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php').write_bytes(b'<?php // drift')
 if kind=='context':(r/'system/library/image.php').write_bytes(b'<?php // drift')
 if kind=='header':
  p=r/'catalog/view/template/common/header.twig';p.write_bytes(p.read_bytes().replace(b'rd25-wp3-20261012',b'invalid token'))
 before=hashes(r);run(r,success=False);check(kind+' fails before writes',hashes(r)==before)
for kind in ['write','lint']:
 r=fixture('forced-'+kind);before=hashes(r)
 def mutate(code):
  if kind=='write':return code.replace('$mode = is_file($path)',"static $f=false; if (!$f && strpos($path, '"+r.as_posix()+"/catalog/view/template/product/product.twig')===0 && strpos($content,RD25_MARKER)!==false) {$f=true;fail('fixture_write_failure');}\n    $mode = is_file($path)",1)
  return code.replace('$output = []; $exit = 0;',"static $f=false; if (!$f && strpos($path, '"+r.as_posix()+"/catalog/controller/product/product.php')===0) {$f=true;fail('fixture_target_lint_failure');}\n    $output = []; $exit = 0;",1)
 out=run(r,success=False,mutate=mutate);check('Forced '+kind+' restores byte exact',hashes(r)==before and 'restore=ok' in out)
(w/'runner-checks.json').write_text(json.dumps({'php':'8.3.30','checks':records,'local_fixtures_only':True},indent=2)+'\n');print('runner_checks='+str(len(records)))
