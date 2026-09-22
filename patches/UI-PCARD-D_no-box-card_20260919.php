<?php
/**
 * UI-PCARD-D — catalog product tile, remove the card container ("без коробки")
 * ROUND 2 — replaces round 1 in place. Round 1 was never deployed (verified
 * live 2026-09-19: still the pre-patch CSS, no UI-PCARD-D marker served).
 *
 * Run from ~/public_html:
 *   php UI-PCARD-D_no-box-card_20260919.php
 *
 * Handoff: handoffs/handoff_UI-PCARD-D_fix-round-2_20260919.md
 * Review that required this round: diagnostics/UI-PCARD-D_no-box-card_review_20260919.md
 * The reference prototype ("D - картка товару - раунд 2.html", built in Claude
 * Design) is now supplied by the owner and is the approved design; this patch
 * matches its `.vD` rules, adapted to the real `.bs-pcard*` selectors.
 *
 * Scope: CSS only, in catalog/view/stylesheet/boostershop-ds.css, plus the
 * matching cache-bust bump in catalog/view/template/common/header.twig. No
 * PHP or Twig markup changes — the existing product-tile DOM
 * (catalog/controller/product/thumb.php + .../template/product/thumb.twig)
 * already carries every class this patch needs. Anchors are the *original*
 * live rules (byte-verified against booster-debug-CAT-004.tar.gz and the live
 * CSSOM, 2026-09-19), not round 1's output — round 1 never shipped.
 *
 * Root cause (AGENTS.md UI/CSS discipline §1): the white-card look comes from
 * boostershop-ds.css — .bs-pcard's own background/border/border-radius/
 * box-shadow, plus .bs-pcard__media img sitting in normal flow inside 12px of
 * padding with its own background/radius. Removing the container means the
 * image must switch to absolute positioning (inset:0) to keep filling its
 * slot, which makes .bs-pcard__media's 12px padding inert dead weight (an
 * absolutely positioned child's containing block is the parent's padding
 * edge, so padding on the parent no longer creates any inset once the child
 * is absolute) — removed for that reason, not requested separately in the
 * handoff. Confirmed on production: all sampled catalog thumbnails are
 * transparent PNG/WebP — the "white columns" are this padding's background,
 * not anything baked into the photos, so removing it cannot leave a white
 * rectangle floating on the page canvas.
 *
 * .bs-pcard__badge-tl/-tr are already position:absolute inside
 * .bs-pcard__media, which stays position:relative before and after this
 * patch — they are NOT relative to the white card today. Verified, left
 * untouched (their 18px inset now reads relative to the image's own edge
 * instead of the old 12px-padded one — still inside the slot).
 *
 * Aspect ratio — round 1 got this wrong and it was blocking (fix F1). The
 * live `aspect-ratio: 1/1` on `.bs-pcard__media img` is dead code:
 * thumb.twig emits `<img width="240" height="240">`, and with no CSS `height`
 * rule that presentational attribute wins, so the browser ignores
 * `aspect-ratio` whenever both width and height are already definite. The
 * slot actually renders a fixed 240px tall at every viewport width (measured
 * on production at 390px and 1280px alike) — i.e. `331/240` at the 1440px
 * case the original handoff eyeballed. Shipping the measured-wrong `1/1`
 * would have grown tiles 15% (desktop) to 28% (mobile) taller — the opposite
 * of the task. Fixed at `.bs-pcard__media { aspect-ratio: 331/240 }`,
 * owner-confirmed against the prototype 2026-09-19.
 *
 * Title `flex: 1` — round 1 added this for button-row alignment (fix F2,
 * reverted). `.bs-pcard__cta { margin-top: auto }` + `.bs-pcard__body
 * { flex: 1 1 auto }` (RD-04 block, untouched by this patch) already pin the
 * buy button to a common baseline — measured identical with and without
 * `flex: 1`. What `flex: 1` on the title actually did: in a row mixing a
 * buyable product with out-of-stock ones (different CTA slot content), it
 * moved the price-row alignment from a measured 0.0px spread to 9.9px — a
 * regression. Removed; `.bs-pcard__title` keeps only its focus rule.
 *
 * Focus ring — round 1's ring lived on the inner <a> with a positive
 * outline-offset, which paints outside that link's own box and was clipped
 * by `.bs-pcard__title`'s `overflow: hidden` (its line-clamp box) on three of
 * four sides — a computed-style read can't catch this, only a rendered check
 * can (fix F3). Moved to `.bs-pcard__title:has(a:focus-visible)`, ringing the
 * title's own box, which nothing clips once `.bs-pcard`'s now-decorative
 * `overflow: hidden` is also removed (it existed only to clip the image to
 * the card's border-radius, both gone).
 *
 * Divider + text inset — round 1 kept `.bs-pcard__body`'s card-era
 * `padding: 4px 14px 14px`, leaving text 14px inset from the now-flush image
 * and the divider's above/below spacing asymmetric (16px/8px) (fix F4). The
 * prototype has the text flush with the image: body padding drops to
 * `0 2px 2px`, the container gap drops from var(--bs-s3) to var(--bs-s2), and
 * the divider's cancelling margin matches the new 2px padding — symmetric
 * 8px/8px on the 4px grid.
 *
 * Cache-bust (AGENTS.md patch convention 8): the current token on
 * boostershop-ds.css?v= is read from header.twig, shape-validated, then
 * replaced wholesale with this patch's own token — not assumed, not
 * appended. header.twig is deliberately excluded from the idempotence marker
 * set: the CSS marker alone decides, so a repeat run that finds it exits
 * already_applied without touching whatever token a later patch has since
 * written. Round 2 uses its own new token value (never assumes round 1's,
 * which never shipped).
 *
 * No PHP file is written by this patch (CSS + Twig only), so the AGENTS.md
 * convention-4 "php -l gate" does not apply to any output file; there is
 * nothing to lint.
 *
 * Explicitly untouched: thumb.php, thumb.twig, badge classes and colors,
 * price row, CTA button, TECH-015 GA4 script, .bs-catcards/.bs-subtiles
 * (different component), the UI-FIX-20260903-TILES block (homepage category
 * tiles), sitemap, robots, redirects, checkout, payments. No Product
 * schema/JSON-LD exists on the tile at all (thumb.twig carries none), so the
 * round-1 handoff's schema gate does not apply to this patch.
 *
 * Files only; no DB writes.
 * Rollback: restore both files from the reported _patch_backups directory and
 * clear the OpenCart theme cache (Dashboard → Developer Settings → Refresh,
 * or delete system/storage/cache/*), then hard-refresh the category page.
 */

declare(strict_types=1);

const UIPCARDD_CSS_MARKER = 'UI-PCARD-D';
const UIPCARDD_CACHE_TOKEN = 'ui-pcard-d2-20260919';
const UIPCARDD_CSS_HREF_PREFIX = 'catalog/view/stylesheet/boostershop-ds.css?v=';

function uipcardd_out(string $key, string $value): void {
	echo $key . '=' . $value . PHP_EOL;
}

function uipcardd_fail(string $message): void {
	throw new RuntimeException($message);
}

function uipcardd_replace_once(string $source, string $needle, string $replacement, string $name): string {
	if (substr_count($source, $needle) !== 1) {
		uipcardd_fail('anchor_count_invalid:' . $name);
	}

	return str_replace($needle, $replacement, $source);
}

function uipcardd_backup(string $backup_dir, string $relative_path): void {
	$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $relative_path;
	$backup_parent = dirname($backup_path);

	if (!is_dir($backup_parent) && !mkdir($backup_parent, 0755, true) && !is_dir($backup_parent)) {
		uipcardd_fail('backup_directory_create_failed:' . $relative_path);
	}

	if (!copy($relative_path, $backup_path)) {
		uipcardd_fail('backup_copy_failed:' . $relative_path);
	}

	clearstatcache(true, $backup_path);

	$original = file_get_contents($relative_path);
	$copied = file_get_contents($backup_path);

	if ($original === false || $copied === false || $original !== $copied) {
		uipcardd_fail('backup_verify_failed:' . $relative_path);
	}
}

/**
 * Restore every backed-up file and report a verified per-file result.
 *
 * @param array<int, string> $files
 */
function uipcardd_restore(array $files, string $backup_dir): void {
	foreach ($files as $relative_path) {
		$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $relative_path;

		if (!is_file($backup_path)) {
			uipcardd_out('restore:' . $relative_path, 'no_backup');
			continue;
		}

		if (!@copy($backup_path, $relative_path)) {
			uipcardd_out('restore:' . $relative_path, 'copy_failed');
			continue;
		}

		clearstatcache(true, $relative_path);

		$expected = @file_get_contents($backup_path);
		$actual = @file_get_contents($relative_path);

		if ($expected === false || $actual === false) {
			uipcardd_out('restore:' . $relative_path, 'unverified');
			continue;
		}

		uipcardd_out('restore:' . $relative_path, $expected === $actual ? 'ok' : 'mismatch');
	}
}

$files = [
	'catalog/view/stylesheet/boostershop-ds.css',
	'catalog/view/template/common/header.twig'
];
$backup_dir = '';
$written = false;

try {
	uipcardd_out('cwd', (string)getcwd());
	uipcardd_out('time', gmdate('c'));

	foreach ($files as $relative_path) {
		if (!is_file($relative_path) || !is_readable($relative_path) || !is_writable($relative_path)) {
			uipcardd_fail('target_unavailable:' . $relative_path);
		}
	}

	$sources = [];
	foreach ($files as $relative_path) {
		$contents = file_get_contents($relative_path);

		if ($contents === false) {
			uipcardd_fail('target_read_failed:' . $relative_path);
		}

		$sources[$relative_path] = $contents;
	}

	// Single content marker: header.twig's cache-bust token is a shared value
	// other patches rewrite too, so it can never stand as this patch's
	// idempotence marker (AGENTS.md patch conventions 5 and 8).
	if (strpos($sources['catalog/view/stylesheet/boostershop-ds.css'], UIPCARDD_CSS_MARKER) !== false) {
		uipcardd_out('already_applied', 'yes');
		uipcardd_out('done', 'ok');
		uipcardd_out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
		exit(0);
	}

	// --- 1/2 · catalog/view/stylesheet/boostershop-ds.css -------------------

	$css = $sources['catalog/view/stylesheet/boostershop-ds.css'];

	// (a) container: strip background/border/border-radius/box-shadow, add the
	// hover/focus-within lift. F3: overflow:hidden also removed — it only ever
	// clipped the image to the card's border-radius, both gone, and it would
	// otherwise be able to clip the title's focus ring (see (d)). F4: gap
	// moves from var(--bs-s3) to var(--bs-s2) so the divider sits with equal
	// space above and below. display:flex/flex-direction:column and the
	// height:100% rule further down (RD-04) are untouched.
	$css = uipcardd_replace_once(
		$css,
		<<<'CSSBLOCK'
.bs-pcard {
  background: var(--bs-paper);
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  box-shadow: var(--bs-sh-sm);
  overflow: hidden;
  display: flex; flex-direction: column;
}
CSSBLOCK,
		<<<'CSSBLOCK'
/* UI-PCARD-D: container decoration removed — tile sits on the page canvas */
.bs-pcard {
  display: flex; flex-direction: column;
  gap: var(--bs-s2);
  transition: transform .18s ease;
}
.bs-pcard:hover, .bs-pcard:focus-within { transform: translateY(-3px); }
CSSBLOCK,
		'pcard_container'
	);

	// (b) image slot: the img moves to absolute/inset:0, so .bs-pcard__media
	// must carry the aspect-ratio instead. F1: 331/240, not 1/1 — the live
	// `1/1` is dead code (thumb.twig's width/height="240" attributes win over
	// it whenever both are definite, per CSS aspect-ratio precedence), and the
	// slot actually renders a fixed 240px tall at every width. 331/240 is
	// that measured shape, owner-confirmed against the prototype. No
	// plate/radius under the image.
	$css = uipcardd_replace_once(
		$css,
		<<<'CSSBLOCK'
.bs-pcard__media {
  position: relative; padding: 12px;
}
.bs-pcard__media img {
  width: 100%; aspect-ratio: 1/1; object-fit: contain;
  border-radius: var(--bs-r-sm);
  background: var(--bs-bg);
}
CSSBLOCK,
		<<<'CSSBLOCK'
.bs-pcard__media {
  position: relative; aspect-ratio: 331/240;
}
.bs-pcard__media img {
  position: absolute; inset: 0;
  width: 100%; height: 100%; object-fit: contain;
}
CSSBLOCK,
		'pcard_media'
	);

	// (c) text block gap moves onto the 4px grid (var(--bs-s2) = 8px, was a
	// hardcoded 10px), plus the divider between image and title. order:-1
	// renders it first in the flex column without touching the Twig markup.
	// F4: padding drops from the card-era 4px 14px 14px to 0 2px 2px so the
	// text sits flush with the now edge-to-edge image (prototype match,
	// owner-confirmed), and the divider's cancelling margin follows it to
	// -2px so both sides stay symmetric at var(--bs-s2).
	$css = uipcardd_replace_once(
		$css,
		'.bs-pcard__body { padding: 4px 14px 14px; display: flex; flex-direction: column; gap: 10px; }',
		<<<'CSSBLOCK'
.bs-pcard__body { padding: 0 2px 2px; display: flex; flex-direction: column; gap: var(--bs-s2); }
.bs-pcard__body::before {
  content: ""; order: -1;
  display: block; height: 1px; margin: 0 -2px;
  background: var(--bs-line);
}
CSSBLOCK,
		'pcard_body_gap'
	);

	// (d) F2: no flex:1 here — RD-04's .bs-pcard__cta{margin-top:auto} and
	// .bs-pcard__body{flex:1 1 auto} already put buy buttons on a common
	// baseline (measured identical with/without flex:1 here), and adding it
	// detached the price row in mixed buyable/out-of-stock rows (measured 0px
	// spread -> 9.9px). Title keeps every existing declaration unchanged.
	// F3: the focus ring lives on the title box itself, not the inner <a>
	// with an outward offset — the <a> version painted outside its own box
	// and .bs-pcard__title's overflow:hidden (its line-clamp) clipped three
	// of its four sides. :has() rings the (unclipped, once .bs-pcard's own
	// overflow:hidden is gone too) title box instead.
	$css = uipcardd_replace_once(
		$css,
		<<<'CSSBLOCK'
.bs-pcard__title {
  font-size: 14px; font-weight: 600; line-height: 1.4;
  color: var(--bs-ink); letter-spacing: -0.005em;
  min-height: 40px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden; margin: 0;
}
CSSBLOCK,
		<<<'CSSBLOCK'
.bs-pcard__title {
  font-size: 14px; font-weight: 600; line-height: 1.4;
  color: var(--bs-ink); letter-spacing: -0.005em;
  min-height: 40px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden; margin: 0;
}
.bs-pcard__title:has(a:focus-visible) { outline: 2px solid var(--bs-blue); outline-offset: 2px; }
CSSBLOCK,
		'pcard_title_focus'
	);

	// (e) no-image placeholder mirrors the same absolute/inset:0 treatment.
	$css = uipcardd_replace_once(
		$css,
		<<<'CSSBLOCK'
.bs-pcard__img-placeholder {
  display: block;
  width: 100%;
  aspect-ratio: 1/1;
  background: var(--bs-bg);
  border-radius: var(--bs-r-sm);
}
CSSBLOCK,
		<<<'CSSBLOCK'
.bs-pcard__img-placeholder {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  background: transparent;
}
CSSBLOCK,
		'pcard_placeholder'
	);

	$sources['catalog/view/stylesheet/boostershop-ds.css'] = $css;

	// --- 2/2 · catalog/view/template/common/header.twig ---------------------

	$header_source = $sources['catalog/view/template/common/header.twig'];

	if (substr_count($header_source, UIPCARDD_CSS_HREF_PREFIX) !== 1) {
		uipcardd_fail('anchor_count_invalid:header_css_cache_bust');
	}

	$header_token_offset = strpos($header_source, UIPCARDD_CSS_HREF_PREFIX) + strlen(UIPCARDD_CSS_HREF_PREFIX);
	$header_token = substr($header_source, $header_token_offset, strcspn($header_source, "\"'?& \t\r\n<>", $header_token_offset));

	if ($header_token === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $header_token)) {
		uipcardd_fail('header_cache_bust_token_invalid');
	}

	uipcardd_out('header_cache_bust_from', $header_token);
	uipcardd_out('header_cache_bust_to', UIPCARDD_CACHE_TOKEN);

	$sources['catalog/view/template/common/header.twig'] = uipcardd_replace_once(
		$header_source,
		UIPCARDD_CSS_HREF_PREFIX . $header_token,
		UIPCARDD_CSS_HREF_PREFIX . UIPCARDD_CACHE_TOKEN,
		'header_css_cache_bust'
	);

	// --- backup, write, verify ---------------------------------------------

	$backup_dir = getcwd() . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . 'UI-PCARD-D_no-box-card_' . gmdate('Ymd_His');
	if (!mkdir($backup_dir, 0755, true) && !is_dir($backup_dir)) {
		uipcardd_fail('backup_root_create_failed');
	}

	foreach ($files as $relative_path) {
		uipcardd_backup($backup_dir, $relative_path);
	}
	uipcardd_out('backup', $backup_dir);

	foreach ($files as $relative_path) {
		if (file_put_contents($relative_path, $sources[$relative_path]) === false) {
			uipcardd_fail('target_write_failed:' . $relative_path);
		}

		$written = true;
		clearstatcache(true, $relative_path);

		$readback = file_get_contents($relative_path);

		if ($readback === false || $readback !== $sources[$relative_path]) {
			uipcardd_fail('target_write_verify_failed:' . $relative_path);
		}

		uipcardd_out('changed', $relative_path);
	}

	uipcardd_out('done', 'ok');
	uipcardd_out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
} catch (Throwable $exception) {
	if ($written && $backup_dir !== '') {
		uipcardd_restore($files, $backup_dir);
	}

	fwrite(STDERR, 'ERROR=' . $exception->getMessage() . PHP_EOL);
	exit(1);
}
