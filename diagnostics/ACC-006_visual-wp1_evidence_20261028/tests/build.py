# Builds patches/ACC-006-visual-WP1_20261028.php from the reviewed candidate tree.
import base64, hashlib, json, re, sys
S, REPO = sys.argv[1], sys.argv[2]
W = S + '/wp1'
def b(path): return open(path, 'rb').read()
def h(data): return hashlib.sha256(data).hexdigest()
def enc(obj): return base64.b64encode(json.dumps(obj, ensure_ascii=False, separators=(',', ':')).encode()).decode()
ORDER_PATHS = [
 'extension/ukrainian/catalog/language/uk-ua/account/wishlist.php',
 'extension/ukrainian/catalog/language/uk-ua/account/account.php',
 'extension/ukrainian/catalog/language/uk-ua/account/address.php',
 'extension/ukrainian/catalog/language/uk-ua/account/edit.php',
 'extension/ukrainian/catalog/language/uk-ua/account/order.php',
 'catalog/controller/account/wishlist.php',
 'catalog/controller/account/shell.php',
 'catalog/controller/common/header.php',
 'catalog/view/stylesheet/boostershop-ds.css',
 'catalog/view/javascript/booster-wishlist.js',
 'catalog/view/template/account/wishlist_list.twig',
 'catalog/view/template/account/wishlist.twig',
 'catalog/view/template/account/shell.twig',
 'catalog/view/template/product/thumb.twig',
 'catalog/view/template/product/product.twig',
 'catalog/view/template/common/header.twig',
]
TOKENS = [rb'(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]{1,128})(?=["\x27])', rb'(catalog/view/javascript/booster-wishlist\.js\?v=)([A-Za-z0-9._-]{1,128})(?=["\x27])']
def norm(data):
    for i, p in enumerate(TOKENS):
        data, n = re.subn(p, lambda m: m.group(1) + b'A6_TOKEN_' + str(i).encode(), data)
        assert n == 1
    return data
targets = []
for path in ORDER_PATHS:
    before, after = b(f'{W}/before/{path}'), b(f'{W}/after/{path}')
    assert before != after, path
    t = {'path': path, 'before': h(before), 'after': h(after), 'payload': base64.b64encode(after).decode()}
    if path == 'catalog/view/template/common/header.twig':
        t['normalized_before'] = h(norm(before)); t['normalized_after'] = h(norm(after))
    if path == 'catalog/view/stylesheet/boostershop-ds.css':
        assert after.startswith(before)
        t['block'] = base64.b64encode(after[len(before):]).decode()
    targets.append(t)
# dependencies: accepted RD-18-WP5 pins (current 2026-10-28) + fresh r1/r2 pulls; never a target.
src = open(f'{REPO}/patches/RD-18-WP5_shipping-display_20261028.php', encoding='utf-8').read()
rd18 = json.loads(base64.b64decode(re.search(r"function rd16_dependencies\(\): array \{ return rd16_json\('([^']+)'\)", src).group(1)))
keep = ['catalog/controller/account/account.php', 'catalog/controller/account/address.php', 'catalog/controller/account/edit.php', 'catalog/controller/account/login.php', 'catalog/controller/account/logout.php',
 'catalog/controller/event/language.php', 'catalog/controller/event/theme.php', 'catalog/controller/event/translation.php', 'catalog/controller/startup/event.php', 'catalog/controller/startup/language.php', 'catalog/controller/startup/setting.php',
 'catalog/model/account/wishlist.php', 'catalog/model/catalog/product.php', 'catalog/model/design/theme.php', 'catalog/model/design/translation.php', 'catalog/model/setting/event.php', 'catalog/model/tool/image.php',
 'catalog/view/javascript/common.js', 'catalog/view/javascript/jquery/jquery-3.7.1.min.js', 'catalog/view/stylesheet/bootstrap.css', 'catalog/view/stylesheet/stylesheet.css',
 'catalog/view/template/account/account.twig', 'catalog/view/template/account/address.twig', 'catalog/view/template/account/edit.twig', 'catalog/view/template/account/order_list.twig',
 'extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php', 'extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php', 'extension/ps_enhanced_measurement/catalog/view/javascript/ps-enhanced-measurement.js',
 'extension/ps_google_recaptcha/catalog/controller/captcha/ps_google_recaptcha.php', 'extension/ps_google_recaptcha/catalog/model/captcha/ps_google_recaptcha.php',
 'extension/ps_live_search/catalog/controller/module/ps_live_search.php', 'extension/ps_live_search/catalog/view/javascript/ps_live_search.js', 'extension/ps_live_search/catalog/view/stylesheet/ps_live_search.css',
 'system/config/catalog.php', 'system/engine/action.php', 'system/engine/event.php', 'system/engine/loader.php', 'system/library/image.php', 'system/library/language.php', 'system/library/template/twig.php', 'system/library/cart/customer.php']
deps = {k: rd18[k] for k in keep}
fresh = {'catalog/controller/product/thumb.php': S + '/live', 'catalog/controller/product/product.php': S + '/live', 'catalog/controller/account/order.php': S + '/live2'}
for k, root in fresh.items(): deps[k] = h(b(f'{root}/{k}'))
# fresh pulls agree with the RD-18 pins where both exist
for k in ['catalog/controller/account/account.php', 'catalog/model/account/wishlist.php', 'catalog/view/template/account/account.twig', 'extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php', 'extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php']:
    assert deps[k] == h(b(f'{S}/live/{k}')), k
assert deps['catalog/view/template/account/order_list.twig'] == h(b(f'{S}/live2/catalog/view/template/account/order_list.twig'))
assert not set(deps) & set(ORDER_PATHS)
hooks = ['catalog/view/account/wishlist_list/before:extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewAccountWishlistListBefore',
 'catalog/view/account/wishlist/before:extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewAccountWishlistBefore',
 'catalog/view/common/header/before:extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewCommonHeaderBefore',
 'catalog/view/common/header/before:extension/ps_google_recaptcha/captcha/ps_google_recaptcha.eventCatalogViewCommonHeaderBefore',
 'catalog/view/common/header/before:extension/ps_live_search/module/ps_live_search.eventCatalogViewCommonHeaderBefore',
 'catalog/view/product/product/before:extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewProductProductBefore',
 'catalog/view/product/thumb/before:extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewProductThumbBefore']
views = ['product/thumb', 'product/product', 'account/wishlist', 'account/wishlist_list', 'account/shell', 'common/header']
triggers = [f'view/{v}/{w}' for v in views for w in ('before', 'after')]
for c in ['account/wishlist', 'account/wishlist.index', 'account/wishlist.list', 'account/wishlist.add', 'account/wishlist.remove', 'account/wishlist.state', 'account/wishlist.total', 'account/wishlist.ids', 'account/shell', 'account/shell.index', 'common/header', 'common/header.index']:
    triggers += [f'controller/{c}/before', f'controller/{c}/after']
php = open(f'{W}/runner-template.php', encoding='utf-8').read()
def phparr(xs): return '[' + ', '.join("'" + x + "'" for x in xs) + ']'
php = (php.replace('__TARGETS__', enc(targets)).replace('__DEPENDENCIES__', enc(deps)).replace('__HOOKS__', enc(hooks))
       .replace('__TRIGGERS__', phparr(triggers)).replace('__VIEWS__', phparr(views)).replace('__ORDER__', '[' + ', '.join(str(i) for i in range(len(ORDER_PATHS))) + ']'))
assert '__' not in re.sub(r'__(DIR|FILE)__', '', php)
out = f'{REPO}/patches/ACC-006-visual-WP1_20261028.php'
open(out, 'w', encoding='utf-8', newline='\n').write(php)
json.dump({'targets': [{k: v for k, v in t.items() if k not in ('payload', 'block')} for t in targets], 'dependencies': deps, 'hooks': hooks}, open(f'{W}/contract.json', 'w'), indent=1)
print(out, h(php.encode()), len(php))
