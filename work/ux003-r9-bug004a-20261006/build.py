from pathlib import Path
import hashlib, re, json

ROOT = Path(__file__).resolve().parents[2]
BASE = Path(__file__).resolve().parent
SRC = BASE / 'source'
P = ROOT / 'patches'
CART = 'catalog/view/template/common/cart.twig'
CSS = 'catalog/view/stylesheet/boostershop-ds.css'
HEADER = 'catalog/view/template/common/header.twig'
CAT = 'catalog/view/template/product/category.twig'
FILTER = 'extension/opencart/catalog/view/template/module/filter.twig'
JS = 'catalog/view/javascript/bs-category-results.js'
state = {str(p.relative_to(SRC)).replace('\\', '/'): p.read_bytes() for p in SRC.rglob('*') if p.is_file()}
libsource = (ROOT/'patches/UX-003_category-heading-fouc_20261005.php').read_text(encoding='utf-8')
lib = libsource[libsource.index('function out('):libsource.index('$root = start_runner();')]

def lf(b): return b.decode('utf-8').replace('\r\n', '\n')
def encode(s, old): return s.replace('\n','\r\n').encode() if b'\r\n' in old else s.encode()
def nowdoc(s):
    tag = 'PAYLOAD_' + hashlib.sha256(s.encode()).hexdigest()[:16].upper()
    return "<<<'"+tag+"'\n"+s+'\n'+tag

def make(task, marker, edits, newfiles, markerfiles, summary):
    paths = list(dict.fromkeys([p for p,_,_ in edits]+list(newfiles)))
    expected = {p: hashlib.sha256(state[p]).hexdigest() for p in paths if p in state}
    ops = ',\n'.join('array('+json.dumps(p)+', '+nowdoc(a)+', '+nowdoc(b)+')' for p,a,b in edits)
    news = ',\n'.join(json.dumps(p)+' => '+nowdoc(s) for p,s in newfiles.items())
    header = f'''<?php
declare(strict_types=1);
/**
 * {task}: {summary}
 * PHP 8.0 compatible. Owner execution only, from ~/public_html.
 * Source: ux003-r9-bug004a-live-20261006-223210.tar.gz, post-runner-8b.
 * Order: BUG-004A first, UX-003 runner 9 second.
 * No DB, checkout/payment, server SEO policy, schema, product-card or drawer changes.
 * Backup: _patch_backups/{task}-<timestamp>/, before any target write.
 * --dry-run validates without changes; --rollback surgically undoes owned edits,
 * preserves sibling patch edits and refreshes the current CSS token independently.
 * Rollback trigger: broken category/history/cart UI or new JS/Twig errors.
 * Existing !important cart declarations are consolidated at their source; no new override layer.
 */
const PATCH_ID = '{task}';
const MARKER = '{marker}';
'''
    body = '''
function transact(string $root, array $files, array $updated, bool $dry): void {
    if ($dry) { out('dry_run=yes'); return; }
    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (!mkdir($backup, 0755, true)) fail('backup_directory_failed');
    $manifest = array();
    foreach ($files as $rel => $file) {
        $manifest[$rel] = $file['exists'];
        if (!$file['exists']) continue;
        $dest = $backup . '/' . $rel;
        if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true)) fail('backup_directory_failed=' . $rel);
        if (file_put_contents($dest, $file['raw'], LOCK_EX) !== strlen($file['raw'])) fail('backup_failed=' . $rel);
        if (file_get_contents($dest) !== $file['raw']) fail('backup_verify_failed=' . $rel);
        out('backup=' . substr($dest, strlen($root) + 1));
    }
    file_put_contents($backup . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    try {
        foreach ($updated as $rel => $bytes) {
            if ($bytes === null) { if (!unlink($root . '/' . $rel)) fail('remove_failed=' . $rel); }
            else write_checked($root . '/' . $rel, $bytes);
            out('changed=' . $rel);
        }
        // Lint the exact runner used for this transaction, after writes as well as before.
        php_lint_file(__FILE__);
        out('php_lint_postwrite=passed:runner');
    } catch (Throwable $error) {
        $ok = true;
        foreach ($updated as $rel => $bytes) {
            if ($files[$rel]['exists']) {
                if (file_put_contents($root . '/' . $rel, $files[$rel]['raw'], LOCK_EX) !== strlen($files[$rel]['raw'])) $ok = false;
            } elseif (is_file($root . '/' . $rel) && !unlink($root . '/' . $rel)) $ok = false;
        }
        out('restore=' . ($ok ? 'ok' : 'FAILED:' . $backup)); throw $error;
    }
    out('backup_dir=' . substr($backup, strlen($root) + 1));
}
$root = start_runner();
$dry = in_array('--dry-run', $argv, true);
$rollback = in_array('--rollback', $argv, true);
foreach (array_slice($argv, 1) as $arg) if (!in_array($arg, array('--dry-run', '--rollback'), true)) fail('unknown_argument');
'''
    body += '$ops = array(\n'+ops+'\n);\n$newFiles = array(\n'+news+'\n);\n'
    body += '$expected = '+ 'array('+', '.join(json.dumps(p)+' => '+json.dumps(s) for p,s in expected.items())+');\n'
    body += '$markerFiles = array('+', '.join(json.dumps(p) for p in markerfiles)+');\n'
    body += '''
$files = array();
foreach ($expected as $rel => $sha) { $files[$rel] = load_text($root, $rel); $files[$rel]['exists'] = true; }
foreach ($newFiles as $rel => $text) {
    $files[$rel] = is_file($root . '/' . $rel) ? load_text($root, $rel) : array('raw'=>'', 'text'=>'', 'eol'=>"\n");
    $files[$rel]['exists'] = is_file($root . '/' . $rel);
}
$applied = marker_state($files, $markerFiles, MARKER);
if (!$rollback && $applied) {
    out('already_applied=yes'); out('done=ok'); if (!$dry) @unlink(__FILE__); exit(0);
}
if ($rollback && !$applied) {
    out('already_rolled_back=yes'); out('done=ok'); if (!$dry) @unlink(__FILE__); exit(0);
}
if (!$rollback) {
    sha_guard($files, $expected);
    foreach ($newFiles as $rel => $text) if ($files[$rel]['exists']) fail('new_file_already_exists=' . $rel);
}
php_lint_file(__FILE__); out('php_lint_prewrite=passed:runner');
$candidate = array(); foreach ($files as $rel => $file) $candidate[$rel] = $file['text'];
$ordered = $rollback ? array_reverse($ops) : $ops;
foreach ($ordered as $index => $op) {
    list($rel, $before, $after) = $op;
    if (!$rollback && $before === '') $candidate[$rel] .= $after;
    else $candidate[$rel] = replace_one($candidate[$rel], $rollback ? $after : $before, $rollback ? $before : $after, $rel . ':' . $index);
}
foreach ($newFiles as $rel => $text) {
    if ($rollback) {
        if ($files[$rel]['raw'] !== $text) fail('rollback_new_file_modified=' . $rel);
        $candidate[$rel] = null;
    } else $candidate[$rel] = $text;
}
$header = 'catalog/view/template/common/header.twig';
$candidate[$header] = bust_token($candidate[$header], 'catalog/view/stylesheet/boostershop-ds.css', strtolower(PATCH_ID) . ($rollback ? '-rollback' : ''), 'ds_css');
$templates = array(); $updated = array();
foreach ($candidate as $rel => $text) {
    if ($text !== null && substr($rel, -5) === '.twig') {
        twig_hazard_gate($text, $rel); $templates[$rel] = array($files[$rel]['text'], $text);
    }
    if ($text !== null && substr($rel, -4) === '.css') css_balance_gate($text, $rel);
    $updated[$rel] = $text === null ? null : encode_text($files[$rel], $text);
}
twig_gate($root, $templates);
transact($root, $files, $updated, $dry);
out('operation=' . ($rollback ? 'rollback' : 'apply')); out('done=ok');
if (!$dry) @unlink(__FILE__);
'''
    # Header is always a token target and a real backup target.
    if HEADER not in expected: raise RuntimeError('Header missing')
    runner = header + lib + body
    (P/(task+'.php')).write_text(runner, encoding='utf-8', newline='\n')
    for p,a,b in edits:
        old = state[p]; text = lf(old)
        if a and text.count(a) != 1: raise RuntimeError((p, 'anchor', text.count(a), a[:100]))
        state[p] = encode(text.replace(a,b) if a else text+b,old)
    for p,s in newfiles.items(): state[p] = s.encode()
    # Same token replacement as PHP runner.
    state[HEADER] = encode(re.sub(r'(catalog/view/stylesheet/boostershop-ds\.css)(\?v=[A-Za-z0-9._-]+)?(?=["\'])', r'\1?v='+task.lower(), lf(state[HEADER])),state[HEADER])
    print(task, len(runner), 'bytes')

# BUG-004A: keep the drawer byte-for-byte; enhance its existing trigger.
cart = lf(state[CART]); start = cart.index('  <button type="button" class="bs-btn bs-btn-primary bs-btn-sm mini-cart-trigger"')
end = cart.index('\n', start); trigger = cart[start:end]
enhanced = trigger.replace('<svg class="bs-cart-icon"', '<span class="bs-cart-symbol"><svg class="bs-cart-icon"')
enhanced = enhanced.replace('</svg><span class="bs-btn-label">', '</svg><span class="bs-cart-badge" aria-hidden="true"{% if not bs_cart_qty %} hidden{% endif %}>{{ bs_cart_qty >= 10 ? \'9+\' : bs_cart_qty }}</span></span><span class="bs-btn-label">')
enhanced = enhanced.replace('data-bs-mini-cart-open aria-label=', 'data-bs-mini-cart-open data-bs-cart-qty="{{ bs_cart_qty }}" data-bs-cart-total="{{ bs_cart_label|length == 2 ? bs_cart_label[1] : \'\' }}" data-bs-cart-label="{{ text_items }}" aria-label=')
enhanced = enhanced.replace("{{ bs_cart_label|length == 2 ? bs_cart_label[1] : '' }}", "{{ (bs_cart_label|length == 2 ? bs_cart_label[1] : '')|escape('html_attr') }}")
enhanced = enhanced.replace('data-bs-cart-label="{{ text_items }}"', 'data-bs-cart-label="{{ text_items|escape(\'html_attr\') }}"')
badgejs = '''{# BUG-004A: server quantity is the sum above; fragment loads rerun the updater. #}
<script>
(function () {
  if (!window.bsCartBadgeUpdate) {
    window.bsCartBadgeUpdate = function () {
      var button = document.querySelector('#cart [data-bs-cart-qty]'); if (!button) return;
      var n = Number(button.dataset.bsCartQty), mobile = matchMedia('(max-width:991.98px)').matches;
      var noun = n % 10 === 1 && n % 100 !== 11 ? 'товар' : n % 10 >= 2 && n % 10 <= 4 && !(n % 100 >= 12 && n % 100 <= 14) ? 'товари' : 'товарів';
      var label = n ? 'Кошик: ' + n + ' ' + noun : 'Кошик порожній';
      if (n && matchMedia('(min-width:576px) and (max-width:991.98px)').matches && button.dataset.bsCartTotal) label += ', ' + button.dataset.bsCartTotal;
      button.setAttribute('aria-label', mobile ? label : button.dataset.bsCartLabel);
    };
    ['(max-width:575.98px)', '(max-width:991.98px)'].forEach(function (query) {
      var media = matchMedia(query);
      if (media.addEventListener) media.addEventListener('change', window.bsCartBadgeUpdate);
      else media.addListener(window.bsCartBadgeUpdate);
    });
    if (window.jQuery) window.jQuery(document).on('bs:cart-updated.bug004a', window.bsCartBadgeUpdate);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.bsCartBadgeUpdate, { once: true });
  }
  window.bsCartBadgeUpdate();
})();
</script>'''
edits = [(CART,trigger,enhanced), (CART,'{% set bs_cart_label = text_items|split(\' - \', 2) %}', '{% set bs_cart_label = text_items|split(\' - \', 2) %}\n'+badgejs)]
# Consolidate cart-only selectors; keep shared ghost/header selectors at their source.
css = lf(state[CSS])
for match in re.finditer(r'([^{}]+)\{([^{}]*)\}',css):
    prefix,props = match.groups(); cleaned = re.sub(r'/\*.*?\*/','',prefix,flags=re.S).strip()
    if cleaned.startswith('@') or not cleaned: continue
    selectors = [s.strip() for s in cleaned.split(',')]
    owned = [s for s in selectors if ('#cart' in s and ('.mini-cart-trigger' in s or '.bs-btn-label__lead' in s))]
    if not owned: continue
    kept = [s for s in selectors if s not in owned]
    # Preserve comments preceding the selector; remove only the owned rule/selector.
    pos = prefix.index(selectors[0])
    replacement = prefix[:pos] + '/* BUG-004A moved cart rule '+str(len(edits))+' into its canonical source. */\n' + (',\n'.join(kept)+' {'+props+'}' if kept else '')
    edits.append((CSS,match.group(0),replacement))
canonical_cart = '''
/* BUG-004A: single cart-trigger source, consolidating the preceding historic rules.
   Existing !important declarations remain necessary against shared Bootstrap/header rules.
   44px is the approved tap target. Badge offsets and 16px size are design variant A. */
body.bs #cart.bs-header__cart .mini-cart-trigger {
  display: inline-flex !important; align-items: center !important; justify-content: center !important;
  position: relative; margin: 0 !important; height: 44px !important; min-height: 44px !important;
  padding: 0 16px !important; gap: 8px; line-height: 1 !important; white-space: nowrap !important;
  background: var(--bs-buy) !important; border-color: var(--bs-buy) !important;
  border-radius: 8px !important; box-shadow: none !important; font-size: 15px !important;
}
body.bs #cart.bs-header__cart .mini-cart-trigger:hover,
body.bs #cart.bs-header__cart .mini-cart-trigger:focus,
body.bs #cart.bs-header__cart .mini-cart-trigger.show {
  background: var(--bs-buy-hover) !important; border-color: var(--bs-buy-hover) !important; color: #fff !important;
}
body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label {
  display: inline !important; font-size: 16px !important; font-weight: 600 !important; opacity: 1 !important;
}
body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-icon { width: 22px !important; height: 22px !important; flex: 0 0 auto; }
.bs-cart-symbol { display: inline-flex; position: relative; flex: 0 0 auto; }
.bs-cart-badge { display: none; }
@media (max-width:991.98px) {
  .bs-cart-badge {
    display: inline-flex; align-items: center; justify-content: center; position: absolute;
    top: -7px; right: -9px; height: 16px; min-width: 16px; padding: 0 4px;
    border-radius: 999px; background: #fff; color: var(--bs-green-hover);
    font-size: 10.5px; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums;
  }
  .bs-cart-badge[hidden] { display: none; }
  #cart .bs-btn-label__lead { display: none; }
}
@media (min-width:576px) and (max-width:991.98px) {
  body.bs #cart.bs-header__cart .mini-cart-trigger { flex: 0 0 auto !important; width: auto !important; min-width: 44px !important; padding: 0 12px !important; }
  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label { max-width: 12ch; overflow: hidden; text-overflow: ellipsis; } /* Long totals retain their full accessible label. */
}
@media (max-width:575.98px) {
  body.bs #cart.bs-header__cart .mini-cart-trigger { flex: 0 0 44px !important; width: 44px !important; min-width: 44px !important; padding: 0 !important; }
  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label { display: none !important; }
  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-icon { width: 23px !important; height: 23px !important; }
  .bs-cart-symbol { position: static; }
  .bs-cart-badge { top: 3px; right: 3px; }
}
/* /BUG-004A */
'''
# Append via a unique EOF anchor; rollback only removes this owned block.
edits.append((CSS,'',canonical_cart))
edits.append((HEADER,'<!DOCTYPE html>', '<!DOCTYPE html>')) # Read/backup/token target, no content marker.
make('BUG-004A_mobile-cart-badge_20261006','BUG-004A',edits,{},[CART,CSS], 'Mobile cart count, variant A; independent surgical rollback.')

# UX-003: source-level replacements and a dedicated category script.
cat = lf(state[CAT]); edits=[]
panel_script_start=cat.index('<script>\n/* UX-003-FILTER')
panel_script_end=cat.index('</script>',panel_script_start)+len('</script>')
old_panel_script=cat[panel_script_start:panel_script_end]
load_start=cat.index('<script>\n(function () {\n  \'use strict\';\n\n  var wrap = document.getElementById(\'bs-load-more-wrap\')')
load_end=cat.index('</script>',load_start)+len('</script>')
old_load_script=cat[load_start:load_end]
edits += [(CAT,old_panel_script,'{# UX-003-R9: filter panel and request lifecycle live in bs-category-results.js. #}'),(CAT,old_load_script,'<script src="catalog/view/javascript/bs-category-results.js?v=ux003-r9-20261006" defer></script>')]
old_chip_start=cat.index('        {% if active_filters|length %}')
old_chip_end=cat.index('        {% endif %}',old_chip_start)+len('        {% endif %}')
edits.append((CAT,cat[old_chip_start:old_chip_end],'''        <div class="bs-r9-chips" data-bs-active-filters hidden></div>
        <div class="visually-hidden" role="status" aria-live="polite" data-bs-category-status></div>'''))
edits.append((CAT,'<button type="button" class="bs-ff-reset" data-bs-ff-reset hidden>Скинути</button>', '<span data-bs-r9-found>Знайдено {{ product_total }} {{ products_total_label }}</span>'))
edits.append((CAT,'<div id="bs-load-more-wrap" aria-live="polite" style="display:none">', '<div id="bs-load-more-wrap" style="display:none">'))
anchor='''            </div>
          </div>
        {% endif %}

        {% if active_filters|length %}'''
replacement='''            </div>
            <div class="bs-r9-sticky">
              <button type="button" class="bs-r9-show" data-bs-r9-show>Показати {{ product_total }} {{ products_total_label }}</button>
              <div class="bs-r9-zero" data-bs-r9-zero hidden><span>0 товарів з цими фільтрами</span><button type="button" class="bs-r9-reset" data-bs-r9-reset>Скинути</button></div>
            </div>
          </div>
        {% endif %}

        {% if active_filters|length %}'''
# This operation precedes the chip replacement to keep the anchor exact.
edits.insert(0,(CAT,anchor,replacement))
edits.append((CAT,'      {% if products %}\n        <div id="product-list"', '''      <div id="bs-category-results" data-total="{{ product_total }}" data-page-size="{{ products|length }}" data-category="{{ reset_url }}" tabindex="-1" aria-label="Товари" aria-busy="false">
      {% if products %}
        <div id="product-list"'''))
empty_start=cat.index('      {% if not categories and not products %}')
empty_end=cat.index('      {% endif %}',empty_start)+len('      {% endif %}')
edits.append((CAT,cat[empty_start:empty_end],'''      {% if not products %}
        <div class="bs-empty bs-r9-empty">
          <p class="bs-empty__title">Нічого не знайдено з цими фільтрами</p>
          <p class="bs-empty__text">Приберіть один із фільтрів або скиньте всі.</p>
          {% if column_right %}<button type="button" class="bs-r9-reset" data-bs-r9-reset>Скинути фільтри</button>{% endif %}
        </div>
      {% endif %}
      </div>'''))
filter_text=lf(state[FILTER])
edits.append((FILTER,'<div class="bs-ff-module">','<div class="bs-ff-module" data-bs-filter-action="{{ action }}">'))
edits.append((FILTER,"    var url = new URL('{{ action|escape('js') }}');", "    var module = button && button.closest('[data-bs-filter-action]');\n    var url = new URL(module ? module.dataset.bsFilterAction : '{{ action|escape('js') }}');"))
edits.append((FILTER,'    window.location.href = url.toString();', '    if (window.bsCategoryNavigate) window.bsCategoryNavigate(url.toString());\n    else window.location.href = url.toString();'))
edits.append((FILTER,'  var applyTimer = null;', "  var applyTimer = null;\n  var module = button && button.closest('[data-bs-filter-action]');\n  if (module) module.addEventListener('bs:filter-cancel', function () { clearTimeout(applyTimer); });\n  /* UX-003-R9: the original server action and debounce retain a navigation fallback. */"))
css=lf(state[CSS])
edits.append((CSS,'.bs-cat-header {\n  background: var(--bs-paper);\n  border: 1px solid var(--bs-line);\n  border-radius: var(--bs-r);\n  overflow: hidden;', '.bs-cat-header {\n  background: var(--bs-paper);\n  border: 1px solid var(--bs-line);\n  border-radius: var(--bs-r);\n  overflow: clip; /* UX-003-R9: preserve rounded clipping without creating a scroll container that traps the sticky CTA. */'))
edits.append((CSS,'''  main,
  #common-home,
  #content {
    max-width: 100vw !important;
    overflow-x: hidden !important;
  }''','''  main,
  #common-home,
  #content {
    max-width: 100vw !important;
  }
  /* UX-003-R9: mutually exclusive source rules. Category ancestors clip horizontally
     without becoming scroll containers; other page types retain hidden overflow. */
  main:not(:has(> #product-category)),
  #common-home,
  #content:not(#product-category #content) {
    overflow-x: hidden !important;
  }
  main:has(> #product-category),
  #product-category #content {
    overflow-x: clip !important;
  }'''))
edits.append((CSS,'.bs-ff-ck {\n  display: flex;\n  align-items: center;\n  gap: 10px;\n  min-height: 36px;', '.bs-ff-ck {\n  display: flex;\n  align-items: center;\n  gap: 10px;\n  min-height: 44px;'))
edits.append((CSS,'.bs-ff-ck input {\n  appearance: none;\n  flex: 0 0 auto;\n  width: 18px;\n  height: 18px;', '.bs-ff-ck input {\n  appearance: none;\n  flex: 0 0 auto;\n  width: 20px;\n  height: 20px;'))
edits.append((CSS,'@media (min-width: 768px) {\n  .bs-ff-groups {', '@media (min-width: 576px) {\n  .bs-ff-groups {'))
edits.append((CSS,'.bs-ff-g {\n  border-bottom:', '.bs-ff-g {\n  min-width: 0;\n  border-bottom:'))
edits.append((CSS,'.bs-ff-ck input {', '.bs-ff-ck > span { min-width: 0; overflow-wrap: anywhere; } /* Long filter labels stay inside their column. */\n.bs-ff-ck input {'))
edits.append((CSS,'.bs-ff-foot {\n  display: flex;', '.bs-ff-foot {\n  display: none;'))
edits.append((CSS,'  .bs-ff-foot {\n    margin-top: 4px;', '  .bs-ff-foot {\n    display: flex;\n    justify-content: space-between;\n    margin-top: 4px;'))
edits.append((CSS,'  .bs-ff-groups {\n    grid-template-columns: repeat(4, minmax(0, 1fr));\n  }','  .bs-ff-groups {\n    grid-template-columns: repeat(4, minmax(0, 1fr));\n  }\n  .bs-ff-ck { min-height: 36px; }\n  .bs-ff-ck input { width: 18px; height: 18px; }'))
# Correct the stale source comment rather than adding a contradictory override.
edits.append((CSS,'/* Sort: the native select, unchanged behaviour (onchange → location). */', '/* Sort: native select; UX-003-R9 enhances navigation, inline onchange remains the no-enhancement fallback. */'))
r9css='''
/* UX-003-R9: approved chips/loading/empty states. 44px targets, 3px progress,
   48px sticky CTA and chip heights/offsets follow the runner 9 design note. */
.bs-r9-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 0 14px 16px; color: var(--bs-ink-3); font-size: 13px; }
.bs-r9-chips[hidden] { display: none; }
.bs-r9-chip { position: relative; display: inline-flex; align-items: center; min-width: 0; max-width: 100%; min-height: 36px; padding: 6px 10px; border: 1px solid var(--bs-blue); border-radius: var(--bs-r-sm); color: var(--bs-blue); background: var(--bs-blue-soft); font: inherit; overflow-wrap: anywhere; text-align: left; cursor: pointer; }
.bs-r9-chip::before { content: ''; position: absolute; inset: -4px 0; }
.bs-r9-reset { min-height: 44px; padding: 8px 12px; border: 1px solid var(--bs-blue); border-radius: var(--bs-r-sm); color: var(--bs-blue); background: var(--bs-paper); font: inherit; font-weight: 700; cursor: pointer; }
.bs-r9-chip:hover, .bs-r9-reset:hover { background: var(--bs-blue-soft); }
.bs-r9-chip:active, .bs-r9-reset:active { color: var(--bs-ink); }
.bs-r9-chip:focus-visible, .bs-r9-reset:focus-visible, .bs-r9-show:focus-visible, #bs-category-results:focus-visible, #product-list:focus-visible { outline: 2px solid var(--bs-blue); outline-offset: 2px; }
#bs-category-results { position: relative; }
#bs-category-results.is-loading > * { opacity: .45; pointer-events: none; }
#bs-category-results.is-loading::before { content: ''; position: absolute; top: 0; left: 0; width: 30%; height: 3px; background: var(--bs-blue); animation: bs-r9-progress 1s ease-in-out infinite alternate; z-index: 1; }
@keyframes bs-r9-progress { to { transform: translateX(230%); } }
.bs-r9-sticky { position: sticky; bottom: 0; z-index: 2; padding: 12px 0; border-top: 1px solid var(--bs-line); background: var(--bs-paper); }
.bs-r9-show { display: block; width: 100%; min-height: 48px; padding: 10px 14px; border: 0; border-radius: var(--bs-r-sm); color: var(--bs-paper); background: var(--bs-blue); font: inherit; font-size: 15px; font-weight: 800; cursor: pointer; }
.bs-r9-show[hidden], .bs-r9-zero[hidden] { display: none; }
.bs-r9-show[aria-disabled="true"] { cursor: wait; }
.bs-r9-show[aria-disabled="true"]::after { content: ''; display: inline-block; width: 14px; height: 14px; margin-left: 8px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: bs-r9-spin .8s linear infinite; }
@keyframes bs-r9-spin { to { transform: rotate(360deg); } }
.bs-r9-show:hover { filter: brightness(.95); }
.bs-r9-show:active { filter: brightness(.9); }
.bs-r9-zero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
.bs-r9-empty { padding: 24px; border: 1px solid var(--bs-line); border-radius: var(--bs-r); background: var(--bs-paper); }
@media (min-width:641px) { .bs-r9-chips { padding-inline: 16px; } }
@media (min-width:992px) {
  .bs-r9-chips { padding-inline: 22px; }
  .bs-r9-chip { min-height: 32px; }
  .bs-r9-chip::before { display: none; }
  .bs-r9-sticky { display: none; }
}
@media (prefers-reduced-motion:reduce) { #bs-category-results.is-loading::before, .bs-r9-show[aria-disabled="true"]::after { animation: none; } }
/* /UX-003-R9 */
'''
edits.append((CSS,'',r9css))
edits.append((HEADER,'<!DOCTYPE html>','<!DOCTYPE html>'))
make('UX-003_runner9_filters-no-reload_20261006','UX-003-R9',edits,{JS:(BASE/'category.js').read_text(encoding='utf-8')},[CAT,FILTER,CSS,JS], 'Category fetch/history/chips; exact server robots and pagination metadata.')
# Expected final files for validation only, never production edits.
for p,b in state.items():
    out=BASE/'expected'/p; out.parent.mkdir(parents=True,exist_ok=True); out.write_bytes(b)
(BASE/'manifest.json').write_text(json.dumps({p:hashlib.sha256(b).hexdigest() for p,b in state.items()},indent=2),encoding='utf-8')
