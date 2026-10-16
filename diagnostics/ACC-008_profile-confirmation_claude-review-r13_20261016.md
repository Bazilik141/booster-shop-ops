# Independent Claude CLI review вЂ” ACC-008 profile confirmation

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `b8758600daece9c37e002e78953bcb22a0a9c6811f89ac34dc4e951321be82a1`
Packet SHA-256: `a30e737b3f224aa24427075f38e78a0d30f519c66911e4b3ffbb1b23235b5c3b`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# ACC-008 profile-save confirmation (round 13, M1 repair): independent review verdict

## Verdict: **Source Review OK**

This OK covers only the CODE of the two-file follow-up:
- **Patch:** `patches/ACC-008_profile-confirmation-r13_20261016.php`
- **SHA-256:** `b8758600daece9c37e002e78953bcb22a0a9c6811f89ac34dc4e951321be82a1`

**What this OK does not cover:**
- It is not acceptance of apply, deploy, owner QA, G2 or production.
- It does not authorise WP3.
- The r12 whole verdict stays Return for changes for r12's rejected artifact (`232a9845…c512`).
- This OK rests on what I actually read here:
  - the full r12 verdict and closure;
  - the repair note;
  - the complete r13 controller, `confirmation_tests.php`, `build_followup.py`, `run_checks.py` and `prepare_review_r13.py`;
  - the three complete diffs;
  - the refreshed result files;
  - the complete gated `catalog/controller/account/login.php`.
- For all other r12 bodies I rely on the exact-identity ledger. I do not claim to have reread them.

---

## M1: closed

### 1. Controller
The success branch now ends with:
```php
$json['redirect'] = $this->url->link('account/edit', 'language=' . … . '&customer_token=' . $this->session->data['customer_token'], true);
```
- It uses the same argument shape as the sibling unauthorised redirect and the `index()` redirect.
- Nothing else in `save()` changed.
- The diff confirms the only generator change is the added `, true`, plus the filename updates.

### 2. Stub
`ConfirmationUrl::link($route, $args = '', $js = false)` now behaves like stock OpenCart:
- by default it returns `&amp;`;
- with `true` it returns raw `&`.

This also makes `index()`'s `save`, `back` and breadcrumb links more realistic. They are now HTML-escaped, which is correct in an attribute when `autoescape=false`. The 12 cases still pass, so nothing relied on the old always-raw behaviour.

### 3. Mutant
`raw_redirect_flag_removed` is set up correctly:
- It targets `…['customer_token'], true);`.
- `count == 1` is asserted in `edit.php`. The only other `true` call in that file is the login redirect, whose text ends in `config_language'), true)`, so it cannot match.

Without the flag:
- The stub yields `route=account/edit&amp;language=…`.
- `no_cart_success` still passes.
- The first failing assertion is exactly `confirmation:redirect_same_form_and_token`.
- `run_checks.py` asserts that exact name in stderr.

The other two mutants remain effective.

### 4. Proving the host semantics (the permitted option chosen)
In the complete `login.php`, `login()` emits the JSON redirect like this:
```php
$json['redirect'] = $this->url->link('account/account', … '&customer_token=' . …, true);
```
- It goes through the same generic AJAX handler: `location = json['redirect']`.
- It goes to a route gated by the same `customer_token` check (`login.validate`).
- The `index()` and `token()` response redirects also use `true`.

The identity of this file is fixed in three places:
- the 103-source manifest (`425b640d…`);
- `prepare_review_r13.py`, which asserts it;
- the host dry-run, which re-checks it before any write.

Customers log in on production; the r11 owner QA was positive while logged in. So `link(…, true)` already works on this host as a token-carrying JSON redirect.

The new line uses the identical contract. This is sufficient proof by equivalence for M1. It holds whatever ST-2c changed in `system/library/url.php`, which is still neither read nor gated. I am not claiming its body.

### 5. Refreshed results
`confirmation-response.json` and `browser-checks.json` are byte-identical to r12. This is expected, not suspicious:
- With `true`, the r13 stub returns exactly the raw URL the r12 stub returned unconditionally.
- The browser record contains no timestamp or hash.

The consequence is that the browser rerun adds no discriminating evidence for M1. That evidence comes from the PHP mutant, which is acceptable.

---

## Affected interactions (rechecked)

- **Order of operations:** unchanged.
  1. `finish(true)`
  2. session refresh
  3. clearing the shipping/payment keys
  4. setting the one-time message and the redirect

  The failure, validation and unauthorised paths still set no message; the tests cover them.
- **Cart toast:** still none. The response has no `success` key, and `redirect` is not a field name.
- **Runner:** the r13 runner equals the r12 runner except for the executable filename. `prepare_review_r13.py` asserts this.
  - The two filename replacements do not interfere. The r11 name contains `-r11`, and the r13 name contains `-r13`.
  - The backup prefix stays `ACC-008_profile-confirmation_`.
  - Admission, payload structure, template-first order, rollback-to-r11 and the 71-case and 9-case suites are identical in substance to r12.
- **Data writer, guard and the other payloads:** reused by exact identity, with no change in business rules.

---

## Optional findings (no separate provider round for these)

1. Gating `system/library/url.php` in a future identity set is still worthwhile (r12 optional item 2).
2. A browser variant that replays an `&amp;` response could show the failure end-to-end. This is not needed; the PHP mutant suffices.
3. The r12 optional items 1 and 3–5 stand as documented:
   - stale message;
   - coupling to `common.js` / ACC-006;
   - cart toast on other forms;
   - `role="status"` accessibility.

---

## Evidence boundaries

**CODE:** this OK, including the mutant-backed stub and the production caller equivalence.

**Bounded r11 owner results:**
- applied with the exact SHA;
- 9 files, 5+5 lints;
- backup and template cache cleanup;
- 8-page anonymous smoke;
- name independence, checkout not overwriting the persistent customer, admin edits persisting.

**Not proven:**
- raw hidden keys survive (`bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename`, unknown keys);
- all NP variants and viewports;
- `customer_activity`;
- cross-customer authorisation;
- shared checkout and First15 writers;
- the actual `Url::link` body.

---

## Owner gate (r13 candidate only; use the new filename)

**Before you start:**
- Take a fresh DB dump and keep it yourself.
- Upload the file to the folder above `public_html` under exactly the name `ACC-008_profile-confirmation-r13_20261016.php`.
- Save the full output and return it.

**One chained block:**
```bash
cd ~/public_html && \
echo "b8758600daece9c37e002e78953bcb22a0a9c6811f89ac34dc4e951321be82a1  ../ACC-008_profile-confirmation-r13_20261016.php" | sha256sum -c - && \
php ../ACC-008_profile-confirmation-r13_20261016.php --dry-run && \
php ../ACC-008_profile-confirmation-r13_20261016.php --db-backup-confirmed && \
test -d ../ocartdata/storage/cache/template && \
find -P ../ocartdata/storage/cache/template -mindepth 1 -type f -name '*.php' -delete
```
- The cleanup line keeps the earlier safer form: compiled PHP files only, no deletion of directories.
- If your previously reviewed r11 cleanup line differs in text, use that exact reviewed line instead.

**Expected dry-run output:**
- `host_payload_lint=catalog/controller/account/edit.php:ok`
- `runtime_sources=exact_reviewed_sources_and_no_generated_ocmod`
- both cache mappings `=../ocartdata/storage/cache`
- `config_admission=…`
- `event_rows_scanned=N`
- `active_startups=[]`
- `metadata=…` (112 events)
- `db_preflight=read_only_ok`
- `dry_run=ok`
- `files_pending=2`

**Expected apply output:**
- `backup=../_patch_backups/ACC-008_profile-confirmation_…` (keep this name)
- 2×`backup_file`
- `changed_file=…edit.twig`
- `php_lint=…edit.php:ok`
- `files_changed=2`
- `done=ok`

**If anything else appears:**
- Any non-zero exit or unexpected line means stop and return the output.
- Do not make edits, bypass the gate or retry.
- A drift in `common.js` or `login.php` is a reassessment item, not something to bypass.

**Rollback:**
1. Re-upload the same r13 runner.
2. Run `cd ~/public_html && php ../ACC-008_profile-confirmation-r13_20261016.php --rollback=<printed basename>`.
3. Repeat the template-only cache cleanup.

This restores the exact r11 sources, including the old toast. It does not restore data. To undo both patches, roll back this one first, then consider the r11 rollback. Never delete the guard manually.

**QA (owner only):**
1. **Save the profile.** You should return to the same edit form, with `customer_token` in the URL and no `amp;` in it. You should **not** be on the login page. A green standard message appears with no cart heading or buttons, and the fields show the saved values. This step directly confirms M1 on production.
2. **Reload the page.** The message is gone.
3. **Change the email.** The redirect still works.
4. **Leave the first name empty.** An inline error appears, with no reload and no message.
5. **Check the existing-email warning.** The old red toast is the known limitation.
6. **Check widths 390, 768 and 1280.**
7. **Check the address and admin forms.** They behave as before.

**G2 (still open):** on a test account or your own account, compare the `custom_field` **key names** before and after a profile save. Do not share values or customer data.

WP3 stays closed.
