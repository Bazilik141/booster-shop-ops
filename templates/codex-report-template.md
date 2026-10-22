# Codex Report — <TASK-ID>: <short description>

Date: YYYY-MM-DD

## Scope
What was in the handoff vs what was implemented (1:1 or deviations noted).

## Files touched
```
patches/<TASK-ID>_<slug>_<YYYYMMDD>.php   — main patch
<other files if any>
```

## Patch identity and expected host receipt
- Patch SHA-256: `<sha256>`
- Review route: `<required by policy | owner-selected exception | not required under routine CRM/dashboard scope>`
- PHP CLI required: `8.0.x`
- Targets: `<target> | sha256_before=<hash|absent> | sha256_after=<hash|absent>`
- Expected stdout: `target=...`, `sha256_before=...`, `backup=<full path|none>`,
  `syntax_gate=...`, `sha256_after=...`, `changed_file(s)=...`, `done=ok`
- No-op: `already_applied=yes`; failure: `error=<reason>` with non-zero exit
- Executor-prepared owner-run PowerShell packet: exact paths, expected values,
  and separate upload, verify/lint, run, post-run and rollback actions.
- Local packet checks: syntax/parse, paths, privacy, hashes and receipt contract.
- When the canonical policy routes the task to review, the final review binds
  this exact packet SHA-256 with the patch SHA-256 in one integrated independent
  Claude review. If review is not required, record that route without implying a
  Claude verdict.

## Dry-run result
<!-- paste key output lines, trim noise -->
```
...
```

## php -l result
```
No syntax errors detected in ...
```

## Idempotency
Re-running returns: `already_applied=yes` / describe behaviour.

## Rollback
Backup at: `<exact full path printed in the host receipt, or none for a new file>`
Rollback table: `<target> | backup path|none | sha256_before hash|absent | sha256_after hash|absent>`
Rollback facts: `<all expected targets and exact state transitions for safe application or review>`
The rollback block preflights every target and every needed backup hash before
any write: current=after means restore its checked backup or delete a created
file; current=before means skip; anything else stops all rollback non-zero.
`absent` is a valid state. Restore permissions and ownership for existing files,
even after a partial `error=`. For Twig/OCMOD targets, record the owning
application and verified `DIR_CACHE` template-cache directory; clear that
directory after rollback, never a guessed filename. The owner then refreshes
Modifications in the admin UI where applicable and checks prior behavior;
do not restore a stale mtime that could keep compiled output.
File rollback cannot reverse DB changes; record approved SQL
rollback separately when the patch changes the database.

## Owner-run command packet
The executor prepares the exact task-specific PowerShell blocks from these
facts. For Codex-executed work, Codex locally validates the packet; when the
canonical policy routes the task to review, actual Claude reviews it with the
patch in one final independent review. Stage outside `public_html`, verify the
hash and PHP 8.0.x, then run with `~/public_html` as the working directory.

## Review exception blocks (include only when raised)
`<stage | evidence | why the default route is insufficient | owner route: separate Claude / no separate call with general-review flag / narrow subagent | unresolved limit>`

## Post-deploy QA checklist
- [ ] <step 1>
- [ ] <step 2>
- [ ] <step 3>

## Side effects / risks
None identified / <list if any>.
