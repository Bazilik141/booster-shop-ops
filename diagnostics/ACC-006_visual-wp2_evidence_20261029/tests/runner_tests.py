# ACC-006 WP2 runner checks on a fixture public_html (php -n + fixture-only mysqli double).
# The test runner is the production runner with only A6_CACHE, the dependency pins and (one
# case) a failure injection substituted; the script asserts that nothing else differs.
import base64, hashlib, json, os, re, shutil, subprocess, sys
S, REPO = sys.argv[1], sys.argv[2]
W = S + '/wp2'; RT = W + '/rt/cases'; MYSQLI = S + '/wp1/rt/mysqli-double.php'
PROD = open(f'{REPO}/patches/ACC-006-visual-WP2_20261029.php', encoding='utf-8').read()
contract = json.load(open(W + '/contract.json'))
TARGETS = [t['path'] for t in contract['targets']]
DEPS = list(contract['dependencies'])
HOOKS = contract['hooks']
h = lambda b: hashlib.sha256(b).hexdigest()
results = []
def check(name, ok, info=''):
    results.append((name, bool(ok))); print(('ok   ' if ok else 'FAIL ') + name + ('' if ok else '  ' + str(info)[:600]))
def make_case(name, inject=None):
    base = f'{RT}/{name}'; shutil.rmtree(base, ignore_errors=True)
    root = f'{base}/public_html'; stage = f'{base}/private/booster-patches'; cache = f'{base}/storage/cache/'
    os.makedirs(stage); os.makedirs(cache + 'template'); os.makedirs(root + '/image'); os.makedirs(root + '/catalog/view/template')
    for p in TARGETS:
        os.makedirs(os.path.dirname(f'{root}/{p}'), exist_ok=True); shutil.copyfile(f'{W}/before/{p}', f'{root}/{p}')
    deps = {}
    for p in DEPS:
        os.makedirs(os.path.dirname(f'{root}/{p}'), exist_ok=True); body = f'dep:{p}\n'.encode(); open(f'{root}/{p}', 'wb').write(body); deps[p] = h(body)
    open(f'{root}/config.php', 'w').write(f"""<?php
define('DIR_OPENCART', '{root}/'); define('DIR_APPLICATION', '{root}/catalog/'); define('DIR_SYSTEM', '{root}/system/');
define('DIR_TEMPLATE', '{root}/catalog/view/template/'); define('DIR_EXTENSION', '{root}/extension/'); define('DIR_IMAGE', '{root}/image/');
define('DIR_CACHE', '{cache}'); define('DB_HOSTNAME', 'h'); define('DB_USERNAME', 'u'); define('DB_PASSWORD', 'p'); define('DB_DATABASE', 'd'); define('DB_PORT', '3306'); define('DB_PREFIX', 'oc_');
""")
    runner = PROD.replace("const A6_CACHE = '/home2/boosters/ocartdata/storage/cache/template/';", f"const A6_CACHE = '{cache}template/';")
    runner = re.sub(r"function a6_dependencies\(\): array \{ return a6_json\('[^']+'\); \}", lambda m: "function a6_dependencies(): array { return a6_json('" + base64.b64encode(json.dumps(deps).encode()).decode() + "'); }", runner)
    if inject: runner = runner.replace(inject[0], inject[1], 1)
    # Only the declared substitutions differ from production (plus the one injected line, if any).
    check_runner = runner.replace(inject[1], inject[0], 1) if inject else runner
    pl, rl = PROD.split('\n'), check_runner.split('\n')
    allowed = {pl.index("const A6_CACHE = '/home2/boosters/ocartdata/storage/cache/template/';")} | {i for i, l in enumerate(pl) if l.startswith('function a6_dependencies()')}
    assert len(pl) == len(rl) and {i for i, (x, y) in enumerate(zip(pl, rl)) if x != y} <= allowed
    open(f'{stage}/ACC-006-visual-WP2_20261029.php', 'w', newline='\n').write(runner)
    return base, root, stage
EVENTS = [{'trigger': x.split(':', 1)[0], 'action': x.split(':', 1)[1]} for x in HOOKS] + [{'trigger': 'catalog/controller/checkout/cart.add/after', 'action': 'extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogControllerCheckoutCartAddAfter'}, {'trigger': 'catalog/view/account/account/before', 'action': 'extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewAccountAccountBefore'}]
def run(root, stage, *args, fixture=None):
    env = dict(os.environ, A6_FIXTURE=json.dumps(fixture if fixture is not None else {'events': EVENTS}))
    p = subprocess.run(['php', '-n', '-d', 'auto_prepend_file=' + MYSQLI, stage + '/ACC-006-visual-WP2_20261029.php', *args], cwd=root, env=env, capture_output=True, text=True)
    return p.returncode, p.stdout, p.stderr
def state(root): return {p: h(open(f'{root}/{p}', 'rb').read()) for p in TARGETS}
before_state = {t['path']: t['before'] for t in contract['targets']}
after_state = {t['path']: t['after'] for t in contract['targets']}
runner_present = lambda stage: os.path.exists(stage + '/ACC-006-visual-WP2_20261029.php')
# 1-5 happy path
base, root, stage = make_case('happy')
c, o, e = run(root, stage, '--check')
adm = re.search(r'^admission_sha256=([a-f0-9]{64})$', o, re.M)
check('check: admission, zero writes, runner kept', c == 0 and adm and 'production_writes=0' in o and state(root) == before_state and runner_present(stage) and not os.path.exists(root + '/_patch_backups'), o + e)
c, o, e = run(root, stage, '--apply', '--admission=' + '0' * 64)
check('apply with wrong admission: refused, no writes', c == 1 and 'error=admission_changed_or_missing' in o and state(root) == before_state, o)
c, o, e = run(root, stage, '--apply', '--admission=' + adm.group(1))
bk = re.search(r'^backup=(.+)$', o, re.M)
check('apply: 4 targets after, changed_files=4, self-deleted', c == 0 and state(root) == after_state and 'changed_files=4' in o and 'self_deleted=yes' in o and o.strip().endswith('done=ok') and not runner_present(stage), o + e)
lines = [l for l in o.split('\n') if l.startswith('target=')]
check('apply: stdout one line per target with before/after/backup', len(lines) == 4 and all(re.match(r'^target=\S+ sha256_before=[a-f0-9]{64} sha256_after=[a-f0-9]{64} backup=' + re.escape(bk.group(1)) + r'/original-\d+$', l) for l in lines), lines[:2])
man = json.load(open(bk.group(1) + '/manifest.json'))
check('backup: 4 originals match before hashes', all(h(open(f'{bk.group(1)}/original-{i}', 'rb').read()) == r['before'] for i, r in enumerate(man['targets'])) and len(man['targets']) == 4, '')
backup = bk.group(1)
def fresh_runner(base, stage):
    # Re-stage this case's own runner bytes (rebuilt deterministically for the case paths).
    name = os.path.basename(base)
    deps = {p: h(f'dep:{p}\n'.encode()) for p in DEPS}
    runner = PROD.replace("const A6_CACHE = '/home2/boosters/ocartdata/storage/cache/template/';", f"const A6_CACHE = '{base}/storage/cache/template/';")
    runner = re.sub(r"function a6_dependencies\(\): array \{ return a6_json\('[^']+'\); \}", lambda m: "function a6_dependencies(): array { return a6_json('" + base64.b64encode(json.dumps(deps).encode()).decode() + "'); }", runner)
    open(stage + '/ACC-006-visual-WP2_20261029.php', 'w', newline='\n').write(runner)
fresh_runner(base, stage)
c, o, e = run(root, stage, '--check')
check('repeat: already_applied=yes, no writes', c == 0 and o.strip() == 'already_applied=yes' and state(root) == after_state, o)
c, o, e = run(root, stage, '--rollback-check=' + backup)
check('rollback-check: preflight ok, no writes', c == 0 and 'rollback_preflight=ok' in o and state(root) == after_state and runner_present(stage), o + e)
c, o, e = run(root, stage, '--rollback=' + backup)
check('rollback: all 4 restored, self-deleted', c == 0 and 'rollback=ok' in o and 'changed_files=4' in o and state(root) == before_state and not runner_present(stage), o + e)
fresh_runner(base, stage)
c, o, e = run(root, stage, '--check')
check('after rollback: check admits again', c == 0 and 'admission_sha256=' in o, o)
c, o, e = run(root, stage, '--rollback=' + base + '/public_html/_patch_backups/x')
check('rollback: unsafe backup path refused', c == 1 and 'error=unsafe_backup_path' in o, o)
# 6 header token drift tolerated; rollback restores the original token
base, root, stage = make_case('token')
p = root + '/catalog/view/template/common/header.twig'; t = open(p, encoding='utf-8').read().replace('boostershop-ds.css?v=acc006-visual-wp1-20261028', 'boostershop-ds.css?v=later-task-1'); open(p, 'w', encoding='utf-8', newline='').write(t)
ref = h(t.encode())
c, o, e = run(root, stage, '--check'); adm = re.search(r'admission_sha256=([a-f0-9]{64})', o)
c, o, e = run(root, stage, '--apply', '--admission=' + adm.group(1)); bk = re.search(r'^backup=(.+)$', o, re.M).group(1)
check('token drift: apply ok, header gets the package tokens', c == 0 and h(open(p, 'rb').read()) == after_state['catalog/view/template/common/header.twig'], o)
fresh_runner(base, stage); c, o, e = run(root, stage, '--rollback=' + bk)
check('token drift: rollback restores later token bytes', c == 0 and h(open(p, 'rb').read()) == ref, o)
# 7 non-token header drift
base, root, stage = make_case('headerdrift')
p = root + '/catalog/view/template/common/header.twig'; open(p, 'a').write('<!-- x -->')
c, o, e = run(root, stage, '--check')
check('header non-token drift: refused', c == 1 and 'error=header_non_token_drift' in o, o)
# 8 target drift / 9 dependency drift / partial state
base, root, stage = make_case('targetdrift'); open(root + '/catalog/view/template/product/thumb.twig', 'a').write(' ')
c, o, e = run(root, stage, '--check'); check('target drift: target_before_mismatch', c == 1 and 'error=target_before_mismatch' in o, o)
base, root, stage = make_case('depdrift'); open(root + '/catalog/model/tool/image.php', 'a').write(' ')
c, o, e = run(root, stage, '--check'); check('dependency drift: refused', c == 1 and 'error=dependency_source_drift' in o, o)
base, root, stage = make_case('partial'); shutil.copyfile(W + '/after/catalog/view/template/product/thumb.twig', root + '/catalog/view/template/product/thumb.twig')
c, o, e = run(root, stage, '--check'); check('partial state: refused, no writes', c == 1 and 'error=target_before_mismatch' in o, o)
# 10-12 runtime admission
base, root, stage = make_case('theme'); c, o, e = run(root, stage, '--check', fixture={'events': EVENTS, 'theme': {'product/thumb': 1}})
check('theme override: refused', c == 1 and 'error=active_target_theme_override' in o, o)
base, root, stage = make_case('hook'); c, o, e = run(root, stage, '--check', fixture={'events': EVENTS + [{'trigger': 'catalog/view/product/thumb/after', 'action': 'extension/x/module/y.z'}]})
check('extra relevant hook: refused, pairs listed', c == 1 and 'error=reviewed_hook_set_changed' in o and 'relevant_hook=catalog/view/product/thumb/after:extension/x/module/y.z' in o, o)
base, root, stage = make_case('wild'); c, o, e = run(root, stage, '--check', fixture={'events': EVENTS + [{'trigger': 'catalog/view/*/before', 'action': 'extension/x/module/y.z'}]})
check('wildcard hook: refused', c == 1 and 'error=reviewed_hook_set_changed' in o, o)
base, root, stage = make_case('missinghook'); c, o, e = run(root, stage, '--check', fixture={'events': EVENTS[1:]})
check('missing reviewed hook: refused', c == 1 and 'error=reviewed_hook_set_changed' in o, o)
base, root, stage = make_case('alt'); os.makedirs(root + '/catalog/view/template/basic/template/product'); open(root + '/catalog/view/template/basic/template/product/thumb.twig', 'w').write('x')
c, o, e = run(root, stage, '--check'); check('alternate theme template: refused', c == 1 and 'error=alternate_hook_template' in o, o)
# 13 runner inside web root
base, root, stage = make_case('inside'); shutil.copyfile(stage + '/ACC-006-visual-WP2_20261029.php', root + '/r.php')
p = subprocess.run(['php', '-n', '-d', 'auto_prepend_file=' + MYSQLI, root + '/r.php', '--check'], cwd=root, capture_output=True, text=True, env=dict(os.environ, A6_FIXTURE=json.dumps({'events': EVENTS})))
check('runner in web root: refused', p.returncode == 1 and 'error=runner_must_be_private' in p.stdout, p.stdout)
c, o, e = run(root, stage); check('no mode: refused', c == 1 and 'error=explicit_mode_required' in o, o)
# 14 injected write failure after 9 targets: automatic restore
base, root, stage = make_case('inject', inject=("                    a6_lint($path);\n                }\n            } catch (Throwable $writeError) {", "                    a6_lint($path);\n                    if (count($changed) === 2) a6_fail('injected_failure');\n                }\n            } catch (Throwable $writeError) {"))
c, o, e = run(root, stage, '--check'); adm = re.search(r'admission_sha256=([a-f0-9]{64})', o)
c, o, e = run(root, stage, '--apply', '--admission=' + adm.group(1))
check('injected failure: automatic restore, all before, runner kept', c == 1 and 'automatic_restore=ok' in o and 'error=injected_failure' in o and state(root) == before_state and runner_present(stage), o + e)
# 15 rollback refused after a later CSS append
base, root, stage = make_case('later'); c, o, e = run(root, stage, '--check'); adm = re.search(r'admission_sha256=([a-f0-9]{64})', o)
c, o, e = run(root, stage, '--apply', '--admission=' + adm.group(1)); bk = re.search(r'^backup=(.+)$', o, re.M).group(1)
open(root + '/catalog/view/stylesheet/boostershop-ds.css', 'a').write('/* later */\n'); fresh_runner(base, stage)
snap = state(root); c, o, e = run(root, stage, '--rollback-check=' + bk)
check('later CSS append: rollback refused before writes', c == 1 and 'error=rollback_target_drift' in o and state(root) == snap, o)
c, o, e = run(root, stage, '--check')
check('later CSS append: still already_applied', c == 0 and o.strip() == 'already_applied=yes', o)
# no DB write statements in runner source
check('runner source: no DB write statements', not re.search(r'(INSERT|UPDATE|DELETE FROM|REPLACE INTO|ALTER TABLE|DROP|TRUNCATE)\b', PROD.split('function a6_targets')[0] + PROD.split('function a6_hook_templates')[1]), '')
bad = [n for n, ok in results if not ok]
print(f'runner_checks={len(results)} failed={len(bad)}'); sys.exit(1 if bad else 0)
