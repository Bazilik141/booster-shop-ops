from pathlib import Path
import subprocess, shutil, json, re, hashlib
BASE=Path(__file__).resolve().parent
ROOT=BASE.parents[1]
SRC=BASE/'source'
FIX=BASE/'fixture'
TOOLS=ROOT/'work/pay003/tooling/vendor/twig/twig'
PATCHES=['BUG-004A_mobile-cart-badge_20261006.php','UX-003_runner9_filters-no-reload_20261006.php']
FIX.mkdir(exist_ok=True)
shutil.copytree(SRC,FIX,dirs_exist_ok=True)
generated=FIX/'catalog/view/javascript/bs-category-results.js'
if generated.exists(): generated.unlink()
shutil.copytree(TOOLS,FIX/'storage/vendor/twig/twig',dirs_exist_ok=True)
(FIX/'config.php').write_text("<?php define('DIR_STORAGE', '"+str(FIX/'storage').replace('\\','/')+"/');\n",encoding='utf-8')
(FIX/'index.php').write_text('<?php // isolated local fixture\n',encoding='utf-8')
records=[]
def run(name,args,cwd=FIX,success=True):
    r=subprocess.run(args,cwd=cwd,capture_output=True,text=True,encoding='utf-8')
    records.append({'name':name,'exit':r.returncode,'output':r.stdout+r.stderr})
    if (r.returncode==0)!=success: raise RuntimeError(name+'\n'+r.stdout+r.stderr)
    print(name, 'PASS')
    return r
def invoke(patch,mode=[],success=True,label=''):
    shutil.copyfile(ROOT/'patches'/patch,FIX/patch)
    return run(label or patch+str(mode),['php',patch]+mode,success=success)
for p in PATCHES:
    run('lint:'+p,['php','-l',str(ROOT/'patches'/p)],ROOT)
    invoke(p,['--dry-run'])
    before={str(p.relative_to(FIX)):hashlib.sha256(p.read_bytes()).hexdigest() for p in FIX.rglob('*') if p.is_file() and p.suffix in ['.twig','.css','.js']}
    invoke(p,label='apply:'+p)
    assert not (FIX/p).exists(),'Runner did not self-delete'
    applied_snapshot={str(q.relative_to(FIX)):q.read_bytes() for q in FIX.rglob('*') if q.is_file() and q.suffix in ['.twig','.css','.js'] and '_patch_backups' not in str(q)}
    r=invoke(p,label='idempotency:'+p); assert 'already_applied=yes' in r.stdout
    for name,b in applied_snapshot.items(): assert (FIX/name).read_bytes()==b,'Idempotent run changed '+name
    assert not (FIX/p).exists()
for p in (BASE/'expected').rglob('*'):
    if p.is_file(): assert (FIX/p.relative_to(BASE/'expected')).read_bytes()==p.read_bytes(),str(p)
print('expected-output-byte-match PASS')
# Verify independent rollbacks in both orders with sibling-content survival.
applied={str(p.relative_to(FIX)):p.read_bytes() for p in FIX.rglob('*') if p.is_file() and p.suffix in ['.twig','.css','.js'] and '_patch_backups' not in str(p)}
for order in [PATCHES[::-1],PATCHES]:
    for name,b in applied.items(): (FIX/name).write_bytes(b)
    for idx,p in enumerate(order):
        invoke(p,['--rollback'],label='rollback:'+p+':'+str(order))
        if idx==0:
            css=(FIX/'catalog/view/stylesheet/boostershop-ds.css').read_text(encoding='utf-8')
            other='BUG-004A' if p.startswith('UX-003') else 'UX-003-R9'
            assert other in css, 'Sibling CSS was removed'
    for p in SRC.rglob('*'):
        if p.is_file() and p.name!='header.twig': assert (FIX/p.relative_to(SRC)).read_bytes()==p.read_bytes(),str(p)+' rollback mismatch'
    assert not (FIX/'catalog/view/javascript/bs-category-results.js').exists()
print('independent-rollback-both-orders PASS')
for name,b in applied.items(): (FIX/name).write_bytes(b)
# Partial markers and source drift must fail before any target change.
cart=FIX/'catalog/view/template/common/cart.twig'; old=cart.read_bytes()
cart.write_bytes(old.replace(b'BUG-004A',b'NO-MARKER'))
snap={str(p.relative_to(FIX)):hashlib.sha256(p.read_bytes()).hexdigest() for p in FIX.rglob('*') if p.is_file() and '_patch_backups' not in str(p) and p.suffix in ['.twig','.css','.js']}
invoke(PATCHES[0],success=False,label='partial-markers-safe-fail')
for name,h in snap.items(): assert hashlib.sha256((FIX/name).read_bytes()).hexdigest()==h
cart.write_bytes(old)
# Clean source + unexpected bytes: reject before writing any file.
for q in SRC.rglob('*'):
    if q.is_file(): shutil.copyfile(q,FIX/q.relative_to(SRC))
generated=FIX/'catalog/view/javascript/bs-category-results.js'
if generated.exists(): generated.unlink()
cart.write_bytes(cart.read_bytes()+b'\n{# synthetic drift #}\n')
snap={str(q.relative_to(FIX)):q.read_bytes() for q in FIX.rglob('*') if q.is_file() and q.suffix in ['.twig','.css','.js'] and '_patch_backups' not in str(q)}
invoke(PATCHES[0],success=False,label='source-drift-safe-fail')
for name,b in snap.items(): assert (FIX/name).read_bytes()==b
# Inject a fixture-only write failure after the cart write, proving restore-all.
for q in SRC.rglob('*'):
    if q.is_file(): shutil.copyfile(q,FIX/q.relative_to(SRC))
shutil.copyfile(ROOT/'patches'/PATCHES[0],FIX/PATCHES[0])
runner=(FIX/PATCHES[0]).read_text(encoding='utf-8')
runner=runner.replace('function write_checked(string $path, string $bytes): void\n{','function write_checked(string $path, string $bytes): void\n{\n    if (strpos($path, "boostershop-ds.css") !== false) fail("injected_write_failure");')
(FIX/PATCHES[0]).write_text(runner,encoding='utf-8',newline='\n')
r=run('injected-write-failure-restores-all',['php',PATCHES[0]],success=False)
assert 'restore=ok' in r.stdout
for q in SRC.rglob('*'):
    if q.is_file(): assert (FIX/q.relative_to(SRC)).read_bytes()==q.read_bytes(),'Restore-all failed: '+str(q)
# Final fixture restored to the generated candidate for browser verification.
for p in (BASE/'expected').rglob('*'):
    if p.is_file(): shutil.copyfile(p,FIX/p.relative_to(BASE/'expected'))
(BASE/'runner-results.json').write_text(json.dumps(records,ensure_ascii=False,indent=2),encoding='utf-8')
print('runner-matrix COMPLETE')
