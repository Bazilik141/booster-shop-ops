# Claude Code Report — ACC-003: login and registration silently bounce back to the form

Date: 2026-08-22 · Executor: Claude Code (Opus, thinking=high) · Handoff:
`handoffs/handoff_ACC-003_login-register-token-rotation_20260822.md`

## Scope

1:1 with the handoff §4, one work package, one patch file, both controllers. No
deviation in what was changed. One correction to the handoff's own reference
data — see **Handoff line references** below.

## Handoff line references — one correction

`register.php` matched the handoff exactly (mint `:50`, compare `:145`, redirect
`:146`).

`login.php` did **not**. Verified against the same archive the handoff cites,
`backup-8.21.2026_22-06-47_boosters.tar.gz`:

| Statement | Handoff says | Actually |
|---|---|---|
| `$this->session->data['login_token'] = oc_token(26);` | `:96` | `:87` |
| token comparison `if (!isset($this->request->get['login_token']) …` | `:120` | `:124` |
| `$json['redirect'] = … account/login …` | `:121` | `:125` |

This is a miscount in the handoff, not a different file. Checked before
proceeding:

- the archive contains exactly **one** `catalog/controller/account/login.php` —
  no OCMOD copy, no `extension/*/catalog/controller/account/login.php`, no
  `system/storage/modification` override;
- the code at those lines is verbatim what the handoff describes, statement for
  statement, in the same order;
- `login.php` mtime is 2025-03-25 with no Booster comment anywhere in it — stock
  OpenCart 4.1.0.3, matching handoff §6. (`register.php`, mtime 2026-05-18, does
  carry Booster edits: the forced telephone field and the First15 redirect.)

The patch therefore anchors on **unique literal strings, not line numbers**, and
each of the four anchors is asserted to occur exactly once before any write
(C2). Line drift cannot mis-target it; a changed statement stops it. Proceeding
was a judgement call on a wrong number attached to correctly identified code —
had the *code* differed, this report would have stopped at this section.

## Files touched

```
patches/ACC-003_login-register-token-session-stable_20260822.php   — the patch
```

The patch edits, on the server only:

```
catalog/controller/account/login.php      — 2 anchors
catalog/controller/account/register.php   — 2 anchors
```

Nothing in handoff §5 was touched: `common.js`, both Twig templates,
`seo_url.php`, `session.php`, `forgotten.php`, `checkout/*`, `footer.twig`,
`header.twig`, and every protected zone are untouched.

## The change

Per file, `index()` mints the form token only when the session holds none:

```php
if (empty($this->session->data['login_token'])) {
	$this->session->data['login_token'] = oc_token(26);
}
```

and the mismatch branch in `login()` / `register()` answers with a reason
instead of a bare bounce:

```php
$json['error']['warning'] = $this->language->get('error_token');
```

`empty()` rather than `!isset()` so a session carrying an empty-string token
self-heals instead of minting a form the customer can never submit;
`oc_token(26)` never returns an empty string, so nothing valid is discarded.

`login()` already unsets `login_token` on success at `:154`; `register()` does
the same for `register_token` at `:280` (handoff §4.2 asked this be verified —
it does, and it is preserved). The token therefore still rotates once per
successful authentication.

Control flow is unchanged: the mismatch branch previously set `$json['redirect']`
and now sets `$json['error']['warning']`; both are truthy, so every subsequent
`if (!$json)` guard skips exactly as before and no login or registration can
occur on a bad token. `register()`'s captcha block still runs unguarded after
the token check, as it did before.

## Verification

Run locally on PHP 8.3.30 against copies of both controllers extracted from
`backup-8.21.2026_22-06-47_boosters.tar.gz`.

### Patch conventions

| | Check | Result |
|---|---|---|
| C1 | missing target | `target_missing path=…`, no write |
| C2 | anchor drift (`oc_token(26)` → `oc_token(32)` in one target) | `anchor_count_error path=catalog/controller/account/login.php expected=1 found=0`, exit 1, **no backup dir created, no file written, patch not self-deleted** |
| C3 | backup | both files written to `_patch_backups/ACC-003_login-register-token-session-stable_20260822-<ts>/catalog/controller/account/` before any write |
| C4 | `php -l` gate | forced a parse error into the emitted code: `php_l_failed`, exit 1, **both files restored byte-identical to the originals** (`diff -q` clean) |
| C5 | re-run | `already_applied=yes patch=…`, exit 0, no second backup dir |
| C6 | DB | none — no SQL in the patch |
| C7 | self-delete | removed after `done=ok`; retained on both failure paths |

Dry run (`--dry-run`), read-only:

```
dry_run=ok patch=ACC-003_login-register-token-session-stable_20260822 files=2 anchors=4
```

Both targets `diff -q` identical to the originals afterwards.

Full run:

```
done=ok patch=ACC-003_login-register-token-session-stable_20260822 files=2 anchors=4 php_l=ok backup=…\_patch_backups\ACC-003_login-register-token-session-stable_20260822-20260822_192021
```

`php -l` on the patch file itself and on both patched controllers: clean.

### Resulting diff

Four hunks, all additive except the two replaced lines. `login.php` +12/−2,
`register.php` +12/−2. No `!important`, no `setTimeout`, no
`position:absolute/fixed`, no magic numbers — no CSS or JS in this patch at all.

### Behaviour

The four patched code blocks were extracted **verbatim from the patched files**
into a harness with stubbed session/request/language/url and run as real PHP —
so these assertions exercise the shipped text, not a retyped copy of it.

```
PASS  AC1 login_token stable across two renders
PASS  AC2 register_token stable across two renders
PASS  AC3 login submit from first tab survives a second render
PASS  AC4 register submit from first tab survives a second render
PASS  AC6a login stale token returns error.warning
PASS  AC6a login stale token returns NO redirect
PASS  AC6b register stale token returns error.warning
PASS  AC6b register stale token returns NO redirect
PASS  login submit with no token in request is rejected with error.warning
PASS  login submit with a wrong token is rejected with error.warning
PASS  login_token still rotates after a successful login
PASS  register_token still rotates after a successful registration
PASS  tokens are per-session, not global

pass=13 fail=0
```

Negative control — the same harness with the **unpatched** blocks substituted
back in returns `pass=3 fail=10`. The ten failures are the defect; the three
that pass in both are the invariants the patch had to preserve (per-session
rotation on success, and no token sharing between sessions). A harness that
passed on unpatched code would have proved nothing.

Handoff AC5 — "wrong password still returns `json.error.warning`" — is not in
the harness because the patch does not touch that path: `error_login` at
`login.php:146` is unchanged and is reached through the same `if (!$json)`
guard as before. It stays on the owner's QA list at §8 regardless.

Language keys confirmed present, not created:
`extension/ukrainian/catalog/language/uk-ua/account/login.php:22` and
`…/account/register.php:25`. `common.js:136-138` renders `json.error.warning`
into `#alert` as a dismissible red alert.

## Rollback

Backup path is printed by the runner on success:
`_patch_backups/ACC-003_login-register-token-session-stable_20260822-<ts>/`

```bash
cp _patch_backups/ACC-003_login-register-token-session-stable_20260822-<ts>/catalog/controller/account/login.php    catalog/controller/account/login.php
cp _patch_backups/ACC-003_login-register-token-session-stable_20260822-<ts>/catalog/controller/account/register.php catalog/controller/account/register.php
```

No DB change. PHP controllers, no template cache involved — nothing else to
clear.

Rollback trigger, per handoff §9: any login or registration path that worked
before the patch stops working, or `error_token` appears on a first clean
attempt.

## Run command (owner)

Upload the patch to `~/public_html`, then:

```bash
php ACC-003_login-register-token-session-stable_20260822.php
```

Optional read-only check first, leaves both files untouched:

```bash
php ACC-003_login-register-token-session-stable_20260822.php --dry-run
```

## Post-deploy QA

Handoff §8 stands unchanged. `bs-checkout-smoke`, `bs-merchant-schema-qa` and
`bs-seo-risk-gate` are all not required — no checkout, payment, fiscalization,
schema, feed, sitemap, robots, canonical, redirect or `.htaccess` change.

## Side effects / risks

- **A stale form after a server-side session wipe now shows an error instead of
  silently reloading.** That is the intended behaviour and the handoff asked for
  it. The customer must reload the page to get a fresh token; the Ukrainian
  `error_token` string tells them the token is invalid but does not say
  "reload". If the owner sees that message reported by real customers after
  deploy, the follow-up is copy, not code.
- **A session-stable token is a strictly weaker invalidation, not a weaker
  CSRF defence.** The token is still unguessable, still per-session, still
  compared on every submit, and still rotated on success. Session fixation is
  unaffected — the value lives in the session, so an attacker who cannot read
  the victim's session cannot read the token.
- Guest checkout is untouched: it runs `checkout/register.save`, a different
  route with its own token.
- `forgotten.php` was left alone as instructed. Its `reset_token` is a mailed
  one-shot credential and does **not** have this flaw — it is not minted per
  render.

## Not done by this executor

No commit, no push, no deploy, no Notion write, no `ROADMAP_FLOW` edit. Handoff
§10 assigns the status write to Claude (chat) after owner QA.

---

# Round 2

Date: 2026-08-22 · Same patch file, not yet deployed. Two non-blocking review
findings addressed. Nothing in the round-1 verification above is superseded;
the sections below add to it.

## What did not change

The reviewer's protected set was left byte-identical: the four `OLD` anchors,
the four `NEW` replacement blocks, the marker string, the target list, and the
C1/C2/C3/C5/C6/C7 logic. Every hunk in the round-1 → round-2 diff is inside
`restoreFiles()`, the new `phpLintCapability()`, `lintPhp()`, the preflight
call site, the dry-run echo, and the catch block.

Proof rather than assertion: a clean run of the round-2 patch produces
controller files **byte-identical** (`diff -q` clean, both files) to the ones
the round-1 run produced, and the round-1 behavioural harness re-run against
that output still returns `pass=13 fail=0`. The emitted patch content is
provably unchanged.

## N1 — restore-on-fail no longer silent

`restoreFiles()` now returns the relative paths it could **not** restore, and
the catch block prints one explicit line naming them and the backup directory
before `done=error`. The `@` is gone, so the underlying PHP warning surfaces
too.

It went slightly further than "check the return value", because two other ways
of leaving a file modified were equally silent:

1. **What is actually modified is measured, not inferred.** Each target's
   on-disk bytes are compared to the original this runner read at startup. A
   target that still equals its original is skipped and never named — so a
   failure during the *write* loop, where the first file was written and the
   second never was, does not falsely accuse the second. Conversely a file that
   was written but whose backup has vanished is now named, where the old
   `is_file($backup)` guard silently skipped it.
2. **The restore is verified after the copy.** A `copy()` that returns true but
   does not land is treated as a failed restore.

Exit code stays 1, no retry, no cleverness.

### N1 verification

End-to-end, with the real patch and a forced lint failure. The restore of
`register.php` was made impossible by destroying its path mid-run (fault
injected between the write and the lint, at the filesystem level):

```
Warning: copy(): The second argument to copy() function cannot be a directory in …\broken.php on line 99
restore_failed=yes patch=broken files_left_modified=catalog/controller/account/register.php restore_by_hand_from=…\_patch_backups\broken-20260822_195124
done=error patch=broken message=php_l_failed path=…\login.php output= | Parse error: … | Errors parsing …
exit=1
```

`login.php`, whose restore was possible, was restored byte-identical and is
correctly **not** named. Note for the reviewer: dropping `@` means the raw PHP
warning now precedes the `restore_failed` line in the output. That is the
intended trade — the cause is visible, and the parseable line still comes
immediately before `done=error`.

The reviewer's suggested "chmod the target read-only after backup" cannot fire
in the real flow: writes precede the lint, so anything that blocks the restore
also blocks the write, and the run stops at `write_failed` with both files
still original — correctly producing **no** `restore_failed` line. The
injection above is what does reach a genuine written-then-unrestorable state.

Remaining branches were driven at unit level against `restoreFiles()` extracted
**verbatim** from the patch:

```
PASS  untouched file is not reported
PASS  modified file with good backup is restored
PASS  modified file with no backup is named
PASS  modified file with no backup dir at all is named
PASS  both unrestorable files are named
PASS  copy that does not land is still reported

restore_unit pass=6 fail=0
```

The two-file case, printed through the patch's own format string. The relative
paths here are the unit test's fixture names, not controller paths — what is
being shown is that both entries appear, comma-separated, alongside the backup
directory:

```
restore_failed=yes patch=ACC-003_login-register-token-session-stable_20260822 files_left_modified=b\y.php,b\z.php restore_by_hand_from=/home2/boosters/public_html/_patch_backups/ACC-003_login-register-token-session-stable_20260822-20260822_201500
```

In a real run those two entries would read
`catalog/controller/account/login.php,catalog/controller/account/register.php`,
as the single-file end-to-end output above shows for `register.php`.

Re-run of the round-1 case — forced lint failure with a writable backup: both
files restored byte-identical, `diff -q` clean, and **no** `restore_failed`
line, which is the correct output when nothing is left modified.

## N2 — `php -l` capability checked before any write

New `phpLintCapability()` tests both `function_exists('exec')` and whether
`exec` appears in the `disable_functions` ini list, since some SAPIs keep the
function defined while the ini forbids it. It is called as a preflight inside
the `try` block and again from `lintPhp()`.

Placement: after the C5 idempotency check, before the first `replaceOnce()`.
That is before any write and is reached by `--dry-run`, as required. It sits
after the idempotency check deliberately, so an already-applied re-run on an
exec-disabled host still reports `already_applied=yes` instead of erroring
about a capability it does not need.

### N2 verification

Dry run, exec available:

```
dry_run=ok patch=ACC-003_login-register-token-session-stable_20260822 files=2 anchors=4 php_l=available
exit=0
```

Targets `diff -q` clean afterwards, no backup dir, patch retained.

Simulated host without exec (`php -d disable_functions=exec …`), both dry run
and full run:

```
done=error patch=ACC-003_login-register-token-session-stable_20260822 message=php_l_unavailable exec_disabled=yes
exit=1
```

No backup dir created, neither file written, patch not self-deleted — matching
the expected output in the review note exactly.

PHP removes disabled functions from the symbol table, so `-d disable_functions`
exercises the `function_exists` branch rather than the ini branch. The ini
branch was therefore driven separately against the function body extracted
**verbatim** from the patch, with `ini_get()` shadowed through namespace
fallback:

```
PASS  disable_functions=''                           -> available=true
PASS  disable_functions='system,passthru'            -> available=true
PASS  disable_functions='execute_thing,myexec'       -> available=true
PASS  disable_functions='passthru,exec,shell_exec'   -> available=false reason=exec_disabled=yes
PASS  disable_functions=' EXEC , system '            -> available=false reason=exec_disabled=yes
PASS  disable_functions='exec'                       -> available=false reason=exec_disabled=yes

cap_parser fail=0
```

`execute_thing,myexec` is the case that matters: the check tokenises the list
and compares whole entries, so a function whose name merely contains `exec`
does not disable the patch.

## Full-run output shape, unchanged

```
done=ok patch=ACC-003_login-register-token-session-stable_20260822 files=2 anchors=4 php_l=ok backup=…\_patch_backups\ACC-003_login-register-token-session-stable_20260822-20260822_194817
exit=0
```

`files=2` and `anchors=4` are both still present and still correct. Idempotent
re-run after it: `already_applied=yes`, exit 0, no second backup dir.

`php -l` on the patch file: clean.

## Round 2 — out of scope, untouched

`header.twig`, `common.js`, every Twig template, the `#alert` placement,
`forgotten.php`, `checkout/*` and the rest of handoff §5 were not opened for
editing. No commit, no push, no deploy, no Notion write, no `ROADMAP_FLOW` edit.
