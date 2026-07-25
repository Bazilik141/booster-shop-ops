# Codex Report — LEGAL-002 archive live diagnostic

Date: 2026-07-24

## Scope

Read-only investigation of archive 404: it reports the live offer/archive rows, parent information record, `information_to_store` mapping, and archive SEO route. It does not modify data, files, cache, routes or configuration.

## Files touched

```
patches/LEGAL-002_archive_live_diagnostic_20260724.php
diagnostics/LEGAL-002_archive_live_diagnostic_report_20260724.md
```

## Dry-run result

Local PHP syntax validation pending before delivery. The owner must return the concise terminal output; it contains no credentials.

## php -l result

Pending local validation.

## Idempotency

Read-only; a repeated run only reads again and self-deletes on successful completion.

## Rollback

Not applicable: no persistent changes are made.

## Run command (owner)

```bash
cd ~/public_html || exit
php LEGAL-002_archive_live_diagnostic_20260724.php
```

## Post-deploy QA checklist

- [ ] Send the full terminal output to Codex.
- [ ] Do not run another write patch until the diagnostic identifies the exact missing linkage.

## Side effects / risks

None: SELECT/SHOW-only diagnostic.
