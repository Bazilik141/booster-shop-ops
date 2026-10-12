from pathlib import Path
import json, shutil, subprocess, hashlib
import build
w=build.w;repo=build.repo;source=build.source;sha=build.sha
php='C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'
module='extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php'
files=set(build.specs[2])|set(build.specs[3])|{'catalog/model/tool/image.php','system/library/image.php','catalog/controller/product/thumb.php','catalog/view/template/product/thumb.twig'}
names={2:'RD-25-WP2_product-gallery_20261012.php',3:'RD-25-WP3_small-thumbs_20261012.php'}
records=[];runbase=w/'runner-fixtures-delivery';runbase.mkdir(exist_ok=False)
def fixture(label,stage=1):
    root=runbase/label
    root.mkdir(parents=True,exist_ok=False)
    for p in files:
        src=w/f'after-wp{stage}'/p if stage>1 and (w/f'after-wp{stage}'/p).is_file() else source/p
        dest=root/p;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dest)
    dest=root/module;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(w/'ga4'/module,dest)
    return root
def hashes(root):return {p:sha((root/p).read_bytes()) for p in files|{module}}
def check(label,condition):
    if not condition:raise RuntimeError(label)
    records.append({'check':label,'pass':True})
def run(root,stage,args=(),mutate=None,success=True):
    runner=root/names[stage];code=(repo/'patches'/names[stage]).read_text(encoding='utf-8')
    if mutate:code=mutate(code)
    runner.write_text(code,encoding='utf-8')
    result=subprocess.run([php,str(runner),*args],cwd=root,text=True,capture_output=True,encoding='utf-8')
    (runbase/f'{root.name}-wp{stage}-{len(records)}.txt').write_text(result.stdout+result.stderr,encoding='utf-8')
    check(f'{root.name}/WP{stage}/exit', (result.returncode==0)==success)
    return result.stdout
root=fixture('composition');baseline=hashes(root)
out=run(root,2,['--dry-run']);check('WP2 dry run zero target writes',hashes(root)==baseline and (root/names[2]).is_file())
run(root,2);check('WP2 self delete',not (root/names[2]).exists())
for p in build.specs[2]:check('WP2 candidate bytes '+p,(root/p).read_bytes()==(w/'after-wp2'/p).read_bytes())
after2=hashes(root);backup2=next((root/'_patch_backups').glob('RD-25-WP2*'))
out=run(root,2);check('WP2 repeat', 'already_applied=yes' in out and hashes(root)==after2)
run(root,3,['--dry-run']);check('WP3 dry run zero target writes',hashes(root)==after2)
run(root,3);check('WP3 self delete',not (root/names[3]).exists())
for p in build.specs[3]:check('WP3 candidate bytes '+p,(root/p).read_bytes()==(w/'after-wp3'/p).read_bytes())
after3=hashes(root);backup3=next((root/'_patch_backups').glob('RD-25-WP3*'))
out=run(root,2);check('WP2 repeat preserves WP3 token and content','already_applied=yes' in out and hashes(root)==after3)
out=run(root,3);check('WP3 repeat','already_applied=yes' in out and hashes(root)==after3)
run(root,2,['--rollback='+backup2.relative_to(root).as_posix()],success=False);check('WP2 rollback refuses active WP3',hashes(root)==after3)
run(root,3,['--rollback='+backup3.relative_to(root).as_posix()]);check('WP3 rollback byte exact after WP2',hashes(root)==after2)
run(root,2,['--rollback='+backup2.relative_to(root).as_posix()]);check('WP2 rollback byte exact WP1',hashes(root)==baseline)

root=fixture('wp3-before-wp2');before=hashes(root);run(root,3,success=False);check('WP3 refuses WP1 with zero target writes',hashes(root)==before)
for stage in [2,3]:
 for fault in ['source','partial','context','ga4','missing','header']:
    root=fixture(f'wp{stage}-{fault}',stage=1 if stage==2 else 2)
    path=next(p for p in build.specs[stage] if p.endswith('.php'))
    if fault=='source':(root/path).write_bytes((root/path).read_bytes()+b'\n// unrelated change\n')
    elif fault=='partial':(root/path).write_bytes((w/f'after-wp{stage}'/path).read_bytes())
    elif fault=='context':(root/'system/library/image.php').write_bytes(b'<?php // changed\n')
    elif fault=='ga4':(root/module).write_bytes((root/module).read_bytes()+b'\n// changed\n')
    elif fault=='missing':(root/path).unlink()
    elif fault=='header':(root/build.HEADER).write_bytes((root/build.HEADER).read_bytes().replace(b'rd25-wp1-photo-contain-20261011' if stage==2 else b'rd25-wp2-20261012',b'bad token!'))
    before={p:sha((root/p).read_bytes()) for p in files|{module} if (root/p).exists()}
    run(root,stage,success=False)
    check(f'WP{stage} {fault} fails before target writes',before=={p:sha((root/p).read_bytes()) for p in before})
 for fault in ['write','lint']:
    root=fixture(f'wp{stage}-forced-{fault}',stage=1 if stage==2 else 2);before=hashes(root)
    target='catalog/view/template/product/product.twig' if stage==2 else 'catalog/view/template/common/cart.twig'
    if fault=='write':
        def mutate(code):return code.replace("$mode = is_file($path)","static $forced = false; if (!$forced && strpos($path, '"+root.as_posix()+"/"+target+"') === 0 && strpos($content, RD25_MARKER) !== false) { $forced = true; fail('diagnostic_forced_write_failure'); }\n    $mode = is_file($path)",1)
    else:
        def mutate(code):return code.replace("$output = []; $exit = 0;","static $forced = false; if (!$forced && strpos($path, '"+root.as_posix()+"/catalog/controller/') === 0) { $forced = true; fail('diagnostic_forced_target_lint_failure'); }\n    $output = []; $exit = 0;",1)
    out=run(root,stage,mutate=mutate,success=False)
    check(f'WP{stage} forced {fault} restores targets byte exact',hashes(root)==before and 'restore=ok' in out)

root=fixture('wp2-repeat-partial-later',stage=3);(root/'catalog/view/template/common/cart.twig').write_bytes(build.original['catalog/view/template/common/cart.twig']);before=hashes(root);run(root,2,success=False);check('WP2 repeat rejects partial WP3',hashes(root)==before)
root=fixture('rollback-tampered-backup');run(root,2);backup=next((root/'_patch_backups').glob('RD-25-WP2*'));p=backup/'original'/build.CSS;p.write_bytes(p.read_bytes()+b'\n/* tampered */');before=hashes(root);run(root,2,['--rollback='+backup.relative_to(root).as_posix()],success=False);check('Rollback refuses modified backup',hashes(root)==before)
root=fixture('later-token',stage=3);p=root/build.HEADER;p.write_bytes(p.read_bytes().replace(b'rd25-wp3-20261012',b'owner-later-20261012'));before=hashes(root);run(root,2);run(root,3);check('Both repeats preserve arbitrary valid later token',hashes(root)==before)

(w/'runner-checks.json').write_text(json.dumps({'php':subprocess.check_output([php,'-r','echo PHP_VERSION;'],text=True),'checks':records,'local_only':True},indent=2)+'\n')
print(json.dumps({'runner_checks':len(records),'pass':True}))
