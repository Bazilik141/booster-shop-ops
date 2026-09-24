<?php
declare(strict_types=1);

/**
 * RD-PP-META variant B: reviews/manufacturer row and three-state status card.
 * Design: CODEX - RD-PP-META_status-card_20260924.html, owner-supplied.
 * Source: owner 2026-09-24 export transformed by the two pending 2026-09-23
 * CAT-004 runners. Run after CAT-004_info-column-reflow_20260923.php.
 *
 * Only product.twig, boostershop-ds.css and its header cache token change.
 * The existing review link including its onclick is retained exactly; only
 * its class and child content change. Controller status and price data remain
 * untouched. Old .bs-pp-meta CSS stays because the supplied archive is not a
 * complete theme-usage inventory. The new button CSS is scoped to the anchor:
 * .bs-pp-reviews also names the existing ratings row on reviewed products.
 * The 13px parent column gap is accounted for in the final three spacing
 * rules, giving 13px title/head, 14px head/card and 18px card/price.
 * Rollback: restore the three logged backups, then clear template cache.
 */

const PATCH_ID = 'RD-PP-META_status-card_20260924';
const TWIG = 'catalog/view/template/product/product.twig';
const CSS = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER = 'catalog/view/template/common/header.twig';
const TWIG_MARKER = 'RD-PP-META-STATUS-CARD-20260924';
const CSS_MARKER = 'RD-PP-META-STATUS-CSS-20260924';

function line(string $s): void { echo $s . PHP_EOL; }
function bad(string $s): void { throw new RuntimeException($s); }
function norm(string $s): string { return str_replace(array("\r\n", "\r"), "\n", $s); }
function count_exact(string $s, string $needle, int $want, string $label): void {
    $got = substr_count($s, $needle);
    if ($got !== $want) bad('anchor_count_' . $label . '=' . $got . ',expected=' . $want);
}
function write_file(string $path, string $value): void {
    $tmp = $path . '.' . PATCH_ID . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $value, LOCK_EX) === false) bad('temp_write_failed=' . $path);
    if (!@rename($tmp, $path)) {
        if (!@copy($tmp, $path)) { @unlink($tmp); bad('target_write_failed=' . $path); }
        @unlink($tmp);
    }
    $read = file_get_contents($path);
    if (!is_string($read) || norm($read) !== $value) bad('postwrite_verify_failed=' . $path);
}

set_exception_handler(static function (Throwable $e): void {
    line('error=' . $e->getMessage());
    line('done=failed');
    exit(1);
});

$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), '/\\');
$paths = array(TWIG, CSS, HEADER);
$original = array();
line('patch=' . PATCH_ID);
line('cwd=' . $root);
line('time=' . date('c'));
line('db_changes=none');
foreach ($paths as $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) bad('target_not_found=' . $relative);
    if (!is_writable($path)) bad('target_not_writable=' . $relative);
    $value = file_get_contents($path);
    if (!is_string($value)) bad('target_read_failed=' . $relative);
    $original[$relative] = norm($value);
    line('file_preflight=ok:' . $relative);
}

$twig = $original[TWIG];
$css = $original[CSS];
$header = $original[HEADER];
$twig_done = strpos($twig, TWIG_MARKER) !== false;
$css_done = strpos($css, CSS_MARKER) !== false;
if ($twig_done !== $css_done) bad('partial_patch_marker_state');
if ($twig_done) {
    count_exact($twig, TWIG_MARKER, 1, 'twig_marker_final');
    count_exact($css, CSS_MARKER, 1, 'css_marker_final');
    count_exact($twig, 'bs-pp-status--pre', 1, 'preorder_card_final');
    line('already_applied=yes');
    line('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$start_anchor = "          {% if review_status %}\n";
$meta_anchor = "          <div class=\"bs-pp-meta\">\n";
$price_anchor = "          {% if price %}\n";
count_exact($twig, $start_anchor, 1, 'review_start');
count_exact($twig, $meta_anchor, 1, 'old_meta_start');
count_exact($twig, $price_anchor, 1, 'price_after_meta');
$start = strpos($twig, $start_anchor);
$meta = strpos($twig, $meta_anchor, $start);
$price = strpos($twig, $price_anchor, $meta);
if ($start === false || $meta === false || $price === false || !($start < $meta && $meta < $price)) bad('meta_anchor_order_invalid');
$reviews = substr($twig, $start, $meta - $start);
count_exact($reviews, 'class="bs-pp-reviews__olx"', 1, 'review_link_class');
count_exact($reviews, 'bs-tab-review-link', 1, 'review_link_target');
$reviews = str_replace('class="bs-pp-reviews__olx"', 'class="bs-pp-reviews__olx bs-pp-reviews"', $reviews);
$review_onclick_before = 'onclick="var n=document.getElementById(\'bs-tab-review-link\');if(!n)return true;if(window.bootstrap&&window.bootstrap.Tab){window.bootstrap.Tab.getOrCreateInstance(n).show();}else if(window.jQuery){window.jQuery(n).tab(\'show\');}var r=n.closest(\'.nav-tabs\')||n;r.scrollIntoView({behavior:\'smooth\',block:\'start\'});return false;"';
count_exact($reviews, $review_onclick_before, 1, 'review_onclick_preserved');
$old_review_inner = <<<'TWIG'
                <svg width="12" height="12" viewBox="0 0 14 14" fill="#F59E0B" aria-hidden="true">
                  <path d="M7 1.5l1.7 3.6L12.5 6 9.7 8.5l.8 4L7 10.5 3.5 12.5l.8-4L1.5 6l3.8-.9L7 1.5z"/>
                </svg>
                Відгуки про нас →
TWIG;
$new_review_inner = <<<'TWIG'
                <span class="bs-pp-reviews__ic"><svg width="13" height="13" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true"><path d="M7 1.5l1.7 3.6L12.5 6 9.7 8.5l.8 4L7 10.5 3.5 12.5l.8-4L1.5 6l3.8-.9L7 1.5z"/></svg></span>
                <span>Відгуки про нас</span>
                <svg class="bs-pp-reviews__arr" width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
TWIG;
count_exact($reviews, $old_review_inner, 1, 'review_inner');
$reviews = str_replace($old_review_inner, $new_review_inner, $reviews);

$status = <<<'TWIG'
          {% if review_status or manufacturer %}
          <div class="bs-pp-head">
%REVIEWS%            {% if manufacturer %}
              <div class="bs-pp-mf">
                <span class="bs-pp-mf__label">Виробник</span>
                <a href="{{ manufacturers }}" class="bs-pp-mf__link">{{ manufacturer }}</a>
              </div>
            {% endif %}
          </div>
          {% endif %}

          {% if _is_preorder %}
            <div class="bs-pp-status bs-pp-status--pre">
              <span class="bs-pp-status__ic"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10" cy="10" r="7.25"/><path d="M10 6v4.2l2.8 1.8"/></svg></span>
              <div class="bs-pp-status__body">
                <div class="bs-pp-status__title">Передзамовлення</div>
                <div class="bs-pp-status__sub">Доставка орієнтовно <strong>3–4 тижні</strong></div>
              </div>
            </div>
          {% elseif _is_out %}
            <div class="bs-pp-status bs-pp-status--out">
              <span class="bs-pp-status__ic"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l8 8M14 6l-8 8"/></svg></span>
              <div class="bs-pp-status__body">
                <div class="bs-pp-status__title">Закінчився</div>
                <div class="bs-pp-status__sub">Товару немає в наявності</div>
              </div>
            </div>
          {% else %}
            <div class="bs-pp-status bs-pp-status--in">
              <span class="bs-pp-status__ic"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg></span>
              <div class="bs-pp-status__body">
                <div class="bs-pp-status__title">В наявності</div>
                {% if _stock_text matches '/^[0-9]+$/' %}<div class="bs-pp-status__sub"><strong>{{ _stock_text }} шт</strong> на складі</div>{% endif %}
              </div>
            </div>
          {% endif %}

TWIG;
$status = str_replace('%REVIEWS%', $reviews, $status);
$new_block = '          {# ' . TWIG_MARKER . ' #}' . "\n" . $status;
$twig = substr($twig, 0, $start) . $new_block . substr($twig, $price);
count_exact($twig, 'class="bs-pp-meta"', 0, 'old_meta_removed');
count_exact($twig, $review_onclick_before, 1, 'onclick_final');
count_exact($twig, TWIG_MARKER, 1, 'twig_marker_new');

$new_css = <<<'CSS'

/* RD-PP-META-STATUS-CSS-20260924: product info variant B. */
.bs-pp-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:12px}
/* Scope the handoff's pill to the OLX anchor: reviewed products use .bs-pp-reviews for their rating row. */
.bs-pp-head > a.bs-pp-reviews{display:inline-flex;align-items:center;gap:8px;padding:4px 10px 4px 4px;border:1px solid transparent;border-radius:999px;background:var(--bs-bg,#F7F7F5);color:var(--bs-ink-2,#1F2937);font-size:13px;font-weight:600;line-height:1.2;text-decoration:none;transition:background .15s,border-color .15s,color .15s}
.bs-pp-head > a.bs-pp-reviews:hover,.bs-pp-head > a.bs-pp-reviews:focus-visible{background:var(--bs-paper,#fff);border-color:var(--bs-line,#E5E7EB);color:var(--bs-ink,#111827);text-decoration:none}
.bs-pp-head > a.bs-pp-reviews:focus-visible{outline:2px solid var(--bs-blue,#1E3A8A);outline-offset:2px}
.bs-pp-reviews__ic{width:26px;height:26px;border-radius:999px;background:var(--bs-gold-soft,#FBF4DC);color:var(--bs-gold,#D4A017);display:grid;place-items:center;flex:0 0 auto}
.bs-pp-reviews__arr{color:var(--bs-ink-4,#9CA3AF);transition:transform .15s,color .15s;flex:0 0 auto}
.bs-pp-head > a.bs-pp-reviews:hover .bs-pp-reviews__arr{transform:translateX(2px);color:var(--bs-ink-2,#1F2937)}
.bs-pp-mf{display:inline-flex;align-items:center;gap:8px;font-size:13px}
.bs-pp-mf__label{color:var(--bs-ink-3,#6B7280)}
.bs-pp-mf__link{color:var(--bs-blue,#1E3A8A);font-size:13.5px;font-weight:600;text-decoration:none}
.bs-pp-mf__link:hover{color:var(--bs-onepiece,#1E40AF);text-decoration:underline;text-underline-offset:3px}
.bs-pp-status{display:flex;align-items:center;gap:12px;margin-top:14px;padding:12px 14px;border:1px solid var(--bs-line,#E5E7EB);border-radius:var(--bs-r,10px);background:var(--bs-bg,#F7F7F5)}
.bs-pp-status__ic{width:36px;height:36px;border-radius:999px;background:#fff;display:grid;place-items:center;flex:0 0 auto}
.bs-pp-status__body{min-width:0}
.bs-pp-status__title{font-size:14.5px;font-weight:700;line-height:1.3}
.bs-pp-status__sub{margin-top:2px;font-size:13px;line-height:1.4;color:var(--bs-ink-3,#6B7280)}
.bs-pp-status__sub strong{color:var(--bs-ink-2,#1F2937);font-weight:700}
.bs-pp-status--in{background:var(--bs-green-soft,#F3FBF6);border-color:#CDEBD8}
.bs-pp-status--in .bs-pp-status__ic{color:var(--bs-green,#16A34A)}
.bs-pp-status--in .bs-pp-status__title{color:var(--bs-green-d,#15803D)}
.bs-pp-status--pre{background:var(--bs-warning-bg,#FFFBEA);border-color:var(--bs-warning-line,#FCD34D)}
.bs-pp-status--pre .bs-pp-status__ic,.bs-pp-status--pre .bs-pp-status__title{color:var(--bs-warning-fg,#92400E)}
.bs-pp-status--out .bs-pp-status__ic{background:var(--bs-line-2,#EEF0F2);color:var(--bs-ink-3,#6B7280)}
.bs-pp-status--out .bs-pp-status__title{color:var(--bs-ink-3,#6B7280)}
.bs-pp-status + .bs-price-block{margin-top:18px}
@media (max-width:767.98px){
.bs-pp-head > a.bs-pp-reviews{padding:7px 12px 7px 7px}
}
/* The existing product column has a 13px flex gap. Keep the handoff's visible spacing. */
.bs-product__info > .bs-pp-head{margin-top:0}
.bs-product__info > .bs-pp-status{margin-top:1px}
.bs-product__info > .bs-pp-status + .bs-price-block{margin-top:5px}
CSS;
count_exact($css, CSS_MARKER, 0, 'css_marker_before');
$css = rtrim($css, "\n") . "\n" . $new_css . "\n";

$pattern = '~(catalog/view/stylesheet/boostershop-ds\.css\?v=)([A-Za-z0-9._-]+)~';
$token_count = preg_match_all($pattern, $header);
if ($token_count !== 1) bad('anchor_count_header_css_token=' . (string)$token_count . ',expected=1');
$header = preg_replace_callback($pattern, static function (array $m): string {
    return $m[1] . 'rd-pp-meta-20260924';
}, $header, 1);
if (!is_string($header)) bad('header_css_token_replace_failed');

$updated = array(TWIG => $twig, CSS => $css, HEADER => $header);
$backup_dir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . getmypid();
if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) bad('backup_dir_failed');
$backups = array();
foreach ($paths as $relative) {
    $backup = $backup_dir . '/' . basename($relative);
    if (file_put_contents($backup, $original[$relative], LOCK_EX) === false) bad('backup_write_failed=' . $relative);
    $backups[$relative] = $backup;
    line('backup=' . $backup);
}
try {
    foreach ($paths as $relative) {
        write_file($root . '/' . $relative, $updated[$relative]);
        line('changed=' . $relative);
    }
} catch (Throwable $e) {
    $restored = true;
    foreach ($paths as $relative) {
        if (!@copy($backups[$relative], $root . '/' . $relative)) $restored = false;
    }
    line('restore=' . ($restored ? 'ok' : 'failed'));
    throw $e;
}
line('php_lint=not_applicable(no PHP targets)');
line('done=ok');
@unlink(__FILE__);
