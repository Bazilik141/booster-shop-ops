# Codex Report — <TASK-ID>: <short description>

Date: YYYY-MM-DD

## Scope
What was in the handoff vs what was implemented (1:1 or deviations noted).

## Files touched
```
patches/<TASK-ID>_<slug>_<YYYYMMDD>.php   — main patch
<other files if any>
```

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
Backup at: `_patch_backups/<patch>-<ts>/`
To restore: `cp _patch_backups/.../file.php path/to/file.php`

## Run command (owner)
```bash
# upload patch to ~/public_html, then:
php <TASK-ID>_<slug>_<YYYYMMDD>.php
```

## Post-deploy QA checklist
- [ ] <step 1>
- [ ] <step 2>
- [ ] <step 3>

## Side effects / risks
None identified / <list if any>.
