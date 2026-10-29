# Builds patches/ACC-006-visual-WP2_20261029.php from the WP1 runner template and the WP2 candidate tree.
import base64, hashlib, json, re, sys
S, REPO = sys.argv[1], sys.argv[2]
W = S + '/wp2'
b = lambda p: open(p, 'rb').read(); h = lambda d: hashlib.sha256(d).hexdigest()
enc = lambda o: base64.b64encode(json.dumps(o, ensure_ascii=False, separators=(',', ':')).encode()).decode()
ORDER_PATHS = ['catalog/view/stylesheet/boostershop-ds.css', 'catalog/view/javascript/booster-wishlist.js', 'catalog/view/template/product/thumb.twig', 'catalog/view/template/common/header.twig']
TOKENS = [rb'(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]{1,128})(?=["\x27])', rb'(catalog/view/javascript/booster-wishlist\.js\?v=)([A-Za-z0-9._-]{1,128})(?=["\x27])']
def norm(d):
    for i, p in enumerate(TOKENS):
        d, n = re.subn(p, lambda m: m.group(1) + b'A6_TOKEN_' + str(i).encode(), d); assert n == 1
    return d
wp1 = {t['path']: t['after'] for t in json.load(open(f'{REPO}/diagnostics/ACC-006_visual-wp1_evidence_20261028/hash-contract.json'))['targets']}
targets = []
for path in ORDER_PATHS:
    before, after = b(f'{W}/before/{path}'), b(f'{W}/after/{path}')
    assert before != after and h(before) == wp1[path], path  # WP2 before = accepted WP1 after
    t = {'path': path, 'before': h(before), 'after': h(after), 'payload': base64.b64encode(after).decode()}
    if path.endswith('header.twig'):
        t['normalized_before'] = h(norm(before)); t['normalized_after'] = h(norm(after))
        assert t['normalized_before'] == t['normalized_after']  # token-only change (owner exception)
    if path.endswith('boostershop-ds.css'):
        assert after.startswith(before); t['block'] = base64.b64encode(after[len(before):]).decode()
    targets.append(t)
src = open(f'{REPO}/patches/RD-18-WP5_shipping-display_20261028.php', encoding='utf-8').read()
rd18 = json.loads(base64.b64decode(re.search(r"function rd16_dependencies\(\): array \{ return rd16_json\('([^']+)'\)", src).group(1)))
keep = ['catalog/controller/event/language.php', 'catalog/controller/event/theme.php', 'catalog/controller/event/translation.php', 'catalog/controller/startup/event.php', 'catalog/controller/startup/language.php', 'catalog/controller/startup/setting.php',
 'catalog/model/account/wishlist.php', 'catalog/model/catalog/product.php', 'catalog/model/design/theme.php', 'catalog/model/setting/event.php', 'catalog/model/tool/image.php',
 'catalog/view/javascript/common.js', 'catalog/view/javascript/jquery/jquery-3.7.1.min.js', 'catalog/view/stylesheet/bootstrap.css', 'catalog/view/stylesheet/stylesheet.css',
 'extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php', 'extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php', 'extension/ps_enhanced_measurement/catalog/view/javascript/ps-enhanced-measurement.js',
 'extension/ps_google_recaptcha/catalog/controller/captcha/ps_google_recaptcha.php', 'extension/ps_google_recaptcha/catalog/model/captcha/ps_google_recaptcha.php',
 'extension/ps_live_search/catalog/controller/module/ps_live_search.php', 'extension/ps_live_search/catalog/view/javascript/ps_live_search.js', 'extension/ps_live_search/catalog/view/stylesheet/ps_live_search.css',
 'system/config/catalog.php', 'system/engine/action.php', 'system/engine/event.php', 'system/engine/loader.php', 'system/library/image.php', 'system/library/language.php', 'system/library/template/twig.php']
deps = {k: rd18[k] for k in keep}
deps['catalog/controller/product/thumb.php'] = h(b(f'{S}/live/catalog/controller/product/thumb.php'))
deps['catalog/controller/product/product.php'] = h(b(f'{S}/live/catalog/controller/product/product.php'))
# WP1 behaviour the hearts rely on (accepted WP1 after-states): IDs, toggle endpoints, interim product button, «Обране» list.
for p in ['catalog/controller/common/header.php', 'catalog/controller/account/wishlist.php', 'catalog/view/template/product/product.twig', 'catalog/view/template/account/wishlist_list.twig', 'extension/ukrainian/catalog/language/uk-ua/account/wishlist.php']:
    deps[p] = wp1[p]
assert not set(deps) & set(ORDER_PATHS)
hooks = ['catalog/view/common/header/before:extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewCommonHeaderBefore',
 'catalog/view/common/header/before:extension/ps_google_recaptcha/captcha/ps_google_recaptcha.eventCatalogViewCommonHeaderBefore',
 'catalog/view/common/header/before:extension/ps_live_search/module/ps_live_search.eventCatalogViewCommonHeaderBefore',
 'catalog/view/product/thumb/before:extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewProductThumbBefore']
views = ['product/thumb', 'common/header']
triggers = [f'view/{v}/{w}' for v in views for w in ('before', 'after')] + [f'controller/{c}/{w}' for c in ['product/thumb', 'product/thumb.index', 'common/header', 'common/header.index'] for w in ('before', 'after')]
php = open(f'{S}/wp1/runner-template.php', encoding='utf-8').read()
reps = [
 ("/** ACC-006 visual WP1: «Обране» names, heart toggle state, notices, «Обране» page in the RD-16 shell.\n", "/** ACC-006 visual WP2: catalog tile heart (presentation), tile-insert state sync and persistent error notice.\n"),
 ("const A6_ID = 'ACC-006-visual-WP1_20261028';", "const A6_ID = 'ACC-006-visual-WP2_20261029';"),
 (" * Backup: cwd/_patch_backups/ACC-006-visual-WP1_20261028-<UTC>-<nonce>/ (original-<i> + manifest.json).", " * Backup: cwd/_patch_backups/ACC-006-visual-WP2_20261029-<UTC>-<nonce>/ (original-<i> + manifest.json)."),
 (" * convention 8) and replaced wholesale; the owner grants the token change per package.", " * convention 8) and replaced wholesale; owner token-only exception for WP2 (2026-10-29): no other header byte."),
 ("const A6_CSS_MARKER = '/* ACC006_VISUAL_WP1_20261028: «Обране» page, shell count, wishlist toggle and notice.';", "const A6_CSS_MARKER = '/* ACC006_VISUAL_WP2_20261029: catalog tile heart, variant A (owner 2026-10-28), every width.';"),
 ("                // Languages and controllers first, then CSS/JS assets and views; the header publishes the tokens last.", "                // CSS and JS assets first, then the tile view; the header publishes the tokens last."),
 ("            // Reverse of the apply order: the header (asset tokens) first, languages last.", "            // Reverse of the apply order: the header (asset tokens) first, CSS last."),
]
for old, new in reps:
    assert php.count(old) == 1, old; php = php.replace(old, new)
css_after = b(f'{W}/after/catalog/view/stylesheet/boostershop-ds.css').decode('utf-8')
assert css_after.count('/* ACC006_VISUAL_WP2_20261029: catalog tile heart, variant A (owner 2026-10-28), every width.') == 1
phparr = lambda xs: '[' + ', '.join("'" + x + "'" for x in xs) + ']'
php = (php.replace('__TARGETS__', enc(targets)).replace('__DEPENDENCIES__', enc(deps)).replace('__HOOKS__', enc(hooks))
       .replace('__TRIGGERS__', phparr(triggers)).replace('__VIEWS__', phparr(views)).replace('__ORDER__', '[' + ', '.join(str(i) for i in range(len(ORDER_PATHS))) + ']'))
assert '__' not in re.sub(r'__(DIR|FILE)__', '', php)
out = f'{REPO}/patches/ACC-006-visual-WP2_20261029.php'
open(out, 'w', encoding='utf-8', newline='\n').write(php)
json.dump({'targets': [{k: v for k, v in t.items() if k not in ('payload', 'block')} for t in targets], 'dependencies': deps, 'hooks': hooks}, open(f'{W}/contract.json', 'w'), indent=1)
print(out, h(php.encode()), len(php))
