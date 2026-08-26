# PAY-002 — callback Basic-auth passthrough patch review (pre-run gate)

Date: 2026-08-25
Reviewer: Claude (chat). Author: Codex.

Patch: `patches/PAY-002_pumb-callback-basic-auth-passthrough_20260825.php` (177 lines, read in full)
Report: `diagnostics/PAY-002_pumb-callback-basic-auth-passthrough_report_20260825.md`

## Verdict

**Return for changes.** Two blocking defects, both verified against
`backup-8.24.2026_10-35-09_boosters.tar.gz`. Neither can damage the site — the
runner aborts or auto-restores in both cases — but the run is wasted either way.

The design is sound and the safety architecture is the best of this task so far.
Only two literals are wrong.

## Blocking

| ID | Where | What is wrong | Evidence |
|---|---|---|---|
| B-1 | `:97-100` | `if (!defined('HTTPS_SERVER')) fail('missing_https_server_constant');` — **OpenCart 4 does not define `HTTPS_SERVER`.** The runner aborts before Phase 1 and does nothing | `homedir/public_html/config.php` in today's backup defines exactly one server constant: `define('HTTP_SERVER', 'https://boostershop.website/');`. There is no `HTTPS_SERVER`. Full constant list: `APPLICATION`, `DB_*`, `DIR_*`, `HTTP_SERVER` |
| B-2 | `:159` | Smoke URL `$baseUrl . '/catalog/Pokemon'` — **this path does not exist.** OpenCart 4 SEO URLs are keyword-only; the category resolves at `/Pokemon`. A 404 fails the smoke gate, triggers the automatic `.htaccess` restore, and exits with `htaccess_smoke_check_failed` even when the change was correct | `ocp5_seo_url` row `(1026,0,4,'path','59','Pokemon',0)` — `key='path'`, `keyword='Pokemon'`, no `catalog/` prefix. Contrast the nested form, which is also keyword-only: `('path','66_65','more-tcg/Yu-Gi-Oh')` |

**Fix for B-1:** use `HTTP_SERVER`, keeping the existing scheme/host validation
(it already holds an `https://` URL, so the `https` assertion still passes and
still protects against a misconfigured store).

**Fix for B-2:** use `/Pokemon`, or better, keep two smoke targets where the
second is a real category URL confirmed against the newest backup.

## Verified correct — checked because it is the part that could break the site

- **Anchor is unique and matches.** The live `.htaccess` (115 lines, LF endings,
  no CRLF) contains exactly one occurrence of the three-line sequence
  `Options -Indexes` / `RewriteEngine On` / `RewriteBase /` at lines 51-53.
  `Options -Indexes` appears once in the whole file. The `\R` in the anchor is a
  PCRE escape and is valid in PHP's `preg_*`.
  ⚠ Note there is a **second** `RewriteEngine on` at line 19 (lowercase `on`)
  inside the LiteSpeed cache block. The anchor's capital `On` correctly avoids
  it. Do not relax that casing.
- **The rewrite form is the right choice, and for a better reason than the
  report gives.** The report justifies `RewriteRule … E=HTTP_AUTHORIZATION` over
  `CGIPassAuth On` by "the file already uses mod_rewrite". The stronger reason:
  this host runs **LiteSpeed** — `.htaccess` contains `# BEGIN LSCACHE`,
  `<IfModule LiteSpeed>` and `CacheLookup on`. `CGIPassAuth` is an Apache-only
  directive and would have been silently ignored. LiteSpeed does emulate
  mod_rewrite, so the chosen form works.
- **Home-page smoke will not false-fail.** `CURLOPT_FOLLOWLOCATION => false` plus
  the file's own redirect rule (`RewriteCond %{HTTPS} off [OR] %{HTTP_HOST} ^www\.`)
  — the runner requests `https://boostershop.website/`, which matches neither
  condition, so no 301.
- **Probe file is served, not rewritten.** OpenCart's front-controller rule is
  gated on `!-f`; the probe exists on disk, so it executes as PHP. It is
  randomly named, prints only two booleans, and is unlinked unconditionally
  after the request (`:63`), including on transport failure.
- **Phase gating is strict.** Phase 2 is unreachable unless Phase 1 is both
  `conclusive` **and** `arrived === false`. A mismatched-but-present header is
  treated as inconclusive (`:71`) and stops the run — the right call.
- **Marker integrity** (`:103-106`) rejects an unbalanced or duplicated marker
  before touching anything.
- Backup precedes the write; every subsequent failure path restores it
  (`:152-156`, `:162-166`, `:168-174`).

## Non-blocking

- `:83-87` — if `unlink(__FILE__)` fails after a successful `.htaccess` change,
  the runner exits `1` with `self_delete_failed`. The change is correctly applied
  and stays applied; only the runner file remains. Do not read that exit code as
  "the patch failed".
- The report says "No cache clear is required for `.htaccess` processing" —
  correct for Apache/LiteSpeed rule evaluation, and no OpenCart cache is touched.

## Owner QA after a successful run

Expect `phase1_authorization_header_reached_php=no`, `phase2_required=yes`,
`smoke_home_http=200`, `smoke_category_http=200`,
`phase2_authorization_header_reached_php=yes`,
`conclusion=authorization_passthrough_applied_and_verified`, `self_deleted=yes`.

Then, with test application `19039895` still in `WAITING_STORE_CONFIRM`, ask the
bank to re-push a state change and confirm the callback lands: the log line
should turn from `401` to `200`, and the transaction row's `state` and
`agreement_number` should populate.

## Rollback

`_patch_backups/PAY-002_pumb-callback-basic-auth-passthrough_20260825-<utc>-<rand>/.htaccess.before`.
The runner restores it automatically on any post-write failure. Manual rollback
is a single file copy back over `~/public_html/.htaccess`; no cache refresh
needed.

If the site ever misbehaves after this change, the marked block is delimited by
`# BEGIN PAY-002 callback Authorization passthrough` /
`# END PAY-002 callback Authorization passthrough` and can be removed by hand.
