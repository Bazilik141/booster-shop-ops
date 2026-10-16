# Independent Claude CLI review вЂ” ACC-008 profile confirmation

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `232a984552eb75f83837882f1a94b86c93dc4b8eb387d799825d63504ed5c512`
Packet SHA-256: `99ff17de921004851ffbbb4aef2d9a86b7530de2b8731a18c48f0754df9665d8`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# ACC-008 profile-save confirmation (round 12): independent review verdict

## Verdict: **Return for changes**

There is one mandatory finding, M1. I could not prove it from the packet. It most likely breaks the main goal of the fix: after saving, the customer would land on the login page instead of the edit form.

The rest of the two-file change looks correct:
- the order of commit, session update, one-time message and consumption;
- no cart toast on a successful save;
- no change to how data is written;
- escaping;
- the runner, rollback and composition with r11.

The repair can be narrow. Supply only the corrected controller line, the corrected test stub, a new SHA and rerun outputs. Do not resend the bodies that are reused by identity.

This verdict covers only the CODE of this two-file follow-up. It is not acceptance of apply, deploy, G2 or production. It does not authorise WP3.

---

## Mandatory finding

### M1. The redirect URL is built without the "raw/JS" flag, so the token may break

**The new line:**
```php
$json['redirect'] = $this->url->link('account/edit', 'language=' . … . '&customer_token=' . …);
```

**The sibling line in the same method uses `true`:**
```php
$json['redirect'] = $this->url->link('account/login', 'language=' . …, true);
```
The `index()` redirect also uses `true`.

**Why this matters.** In stock OpenCart 4.0/4.1, `Url::link(string $route, $args = '', bool $js = false)` returns `str_replace('&', '&amp;', $url)` when `$js` is false. The `true` argument returns raw `&`. The rest of this codebase is consistent with that:
- `common.js` explicitly does `action.replaceAll('&amp;', '&')` for the form `action`;
- it does not do the same for `json['redirect']` (`location = json['redirect']`).

**The likely failure on the host:**
1. `location` becomes `…?route=account/edit&amp;language=uk-ua&amp;customer_token=…`.
2. The request carries the keys `amp;language` and `amp;customer_token`.
3. `account/login.validate` fails without a `customer_token`.
4. `index()` redirects to the login page and calls `exit` before the one-time message is consumed.
5. The message therefore stays in the session.

**Why the tests did not catch it.**
- `ConfirmationUrl::link()` ignores the third argument and always returns raw `&`.
- The browser fixture replays that same response.
- Neither test can tell the two behaviours apart.
- `system/library/url.php` is not among the 103 gated identities and is not in the packet. AGENTS.md notes it was modified for ST-2c, so I cannot prove its actual semantics.

**Required in the repair:**
1. Add `, true` to the new `$json['redirect']`, matching the sibling line.
2. Change the test URL stub to stock behaviour: `&amp;` by default and raw `&` only when the third argument is `true`.
3. Add a mutant that removes `true`. It must make `confirmation:redirect_same_form_and_token` fail.
4. Regenerate `confirmation-response.json` and rerun the browser fixture on the corrected response.
5. Prove the semantics of `link()` on this host. Either:
   - supply the code-only body of `system/library/url.php` from the intake; or
   - cite the production-proven JSON redirect line with `true` from the gated `catalog/controller/account/login.php`.

---

## Checked and found correct (apart from M1)

### Order of operations
1. Commit happens in `finish(true)`.
2. The session customer data is updated.
3. The shipping and payment session keys are cleared.
4. Only then is the one-time message set and the redirect added.

If the commit fails, it throws before the message is set. That case is covered by `commit_failure_no_flash`. The validation-error path, the guard-exception path and the unauthorised path do not set the message either. The trailing `finish(!isset(error))` and the `finally rollback()` are no-ops after a commit.

### Session persistence
The session is written by `Session::close()` in a shutdown function. The XHR `success` handler fires only after the response is fully finished. This is the same pattern as the stock JSON login redirect, so the reasoning is sound but has not been proven on the host. Tokens and language come from the session and config; `customer_token` does not change on save.

### No cart toast
In the current `common.js`:
- the response has no `success` key, so `bsToast` is not called;
- `location = json['redirect']` runs;
- the `for (key in json)` loop finds no field named `redirect`.

The diff removes only `$json['success']`.

### Data preservation
The writer logic is byte-identical to r11 in substance:
- validation;
- `$acc008->account()`;
- `assertWritable`;
- `editCustomer`;
- the session refresh and the clearing of shipping/payment keys.

Only one line is removed and the flash/redirect lines are added. The 164 regression roundtrips still pass. The two changed session assertions correctly reflect the seeded token.

### View
`|escape` is present while `autoescape=false`, and a mutant covers its removal. The message is consumed before `load->view`, so it does not show again on reload. If the variable is undefined (for example, a new template with the old controller), Twig treats it as false and renders nothing.

### Runner
`build_followup.py` mechanically changes only:
- the filename;
- the backup prefix;
- the header comment;
- the marker, now `ACC-008-PROFILE-CONFIRMATION`.

`runtime_admission.php` is copied byte-for-byte. My claim that this matches the r11 gate rests on the identity of the BASE files; I did not reread those bodies.

Other runner behaviour:
- **Template-first order** is safe in both partial states.
- **Admission** is unchanged; any drift stops the run before writes:
  - in the 103 identities, edit.php and edit.twig are admitted only as the r11 result or this patch's result;
  - the guard is pinned to its r11 hash;
  - `common.js` is pinned to the publicly served bytes, and the dry-run proves it against the host file.
- **Rollback** restores exactly the r11 states. The orphan check does not apply here because the guard is not new in this manifest. The order for undoing both patches (this one first, then r11) is documented correctly.

### Tests
The coverage is substantial:
- 12 confirmation cases with actual Twig 3.18;
- 71 preflight cases;
- 9 lifecycle cases (stubbed admission);
- 2 mutants;
- a browser check at 390, 768 and 1280.

The boundary of these tests is stated honestly.

---

## Optional findings (no separate provider round for these)

1. **Stale message.** If navigation does not happen (tab closed or network failure), the message stays in the session. It would then appear on the next visit to the edit page, possibly after logout and login in the same session. It contains only generic text, so the risk is low.
2. **Gate `system/library/url.php` in the identities.** This is worth doing after M1, since the redirect now depends on it.
3. **`common.js` couples this patch to ACC-006 GA4.** If the parallel dialogue deploys a change to `common.js` first, the dry-run will stop. That is the safe outcome, but it needs reassessment, not a bypass.
4. **The same cart-toast symptom on other forms remains outside scope:**
   - the email-exists warning and guard messages on the profile form (red "Не вдалося додати" with a cart button);
   - very likely the password, newsletter and address forms if they return a generic `success`.

   The owner should later choose between per-form fixes and a shared `bsToast` fix. The shared fix touches the shared `common.js` and needs coordination with the other dialogue.
5. **Accessibility nit.** `role="status"` on a freshly loaded page is usually not announced. Consider moving focus to the message or using `role="alert"`.

---

## Evidence boundaries

**Owner runtime evidence from r11 (bounded):**
- the exact r11 SHA was applied;
- 9 files changed;
- 5+5 lints passed;
- the source backup and template cache cleanup were done;
- anonymous smoke checks on 8 pages passed;
- the owner confirmed: profile and address names stay independent, checkout does not overwrite the persistent customer, and admin edits persist.

**Not proven:**
- raw hidden keys survive a profile save: `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename` and unknown keys;
- all NP variants;
- all viewports;
- `customer_activity` rows;
- cross-customer authorisation;
- shared checkout and First15 writers.

**Future acceptance.** G2 requires the owner's QA, including a hidden-key check on a test or own account. Report only key names before and after, never values. WP3 stays closed.

---

## Owner gate (only for the corrected candidate after its OK)

**One chained block is acceptable.**
- `--db-backup-confirmed` re-runs the full exact admission, and every refusal happens before any write.
- Separately returning the dry-run output adds no protection the gate does not already enforce.

**Conditions for the block:**
- a fresh DB dump is taken and stored by the owner **before** the block;
- the full output is saved and returned;
- any stop (non-zero exit) means return the output, with no edits, handler disabling or retries.

```bash
cd ~/public_html && \
echo "<NEW_SHA256>  ../ACC-008_profile-confirmation_20261016.php" | sha256sum -c - && \
php ../ACC-008_profile-confirmation_20261016.php --dry-run && \
php ../ACC-008_profile-confirmation_20261016.php --db-backup-confirmed && \
test -d ../ocartdata/storage/cache/template && \
find ../ocartdata/storage/cache/template -mindepth 1 -delete
```

**Expected dry-run output:**
- `host_payload_lint=catalog/controller/account/edit.php:ok`
- `runtime_sources=exact_reviewed_sources_and_no_generated_ocmod`
- both `*cache_dir_config_relative=../ocartdata/storage/cache`
- `config_admission=…`
- `event_rows_scanned=N`
- `active_startups=[]`
- `metadata=…` with 112 events
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

Any other output means stop and return it for assessment.

**Rollback:**
1. Re-upload the same runner.
2. Run `cd ~/public_html && php ../ACC-008_profile-confirmation_20261016.php --rollback=<printed basename>`.
3. Clean only the `template/` cache.

This restores the exact r11 state, including the old toast. It does not restore data.

**QA (owner only):**
1. Save the profile: you return to the same edit form, the URL contains `customer_token` and you are **not** on the login page. A normal green message appears with no cart heading or buttons, and the fields show the saved values.
2. Reload the page: the message is gone.
3. Change the email: the redirect still works.
4. Leave the first name empty: an inline error appears, with no reload and no message.
5. Check the existing-email warning: the old red toast is expected, a known limitation and not a regression.
6. Check at 390, 768 and 1280.
7. Confirm the address and admin forms behave as before.
8. For G2: on a test account, compare the key lists of `custom_field` before and after a profile save.
