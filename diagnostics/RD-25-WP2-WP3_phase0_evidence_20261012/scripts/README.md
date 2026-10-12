# Reproduction

These are Phase 0 instruments, not hosting patch runners. Restore the staged scripts into `work/rd25-wp2-wp3-phase0-20261012/`; the intake script belongs in `work/`. Their default paths depend on that location. Original scripts and fixtures remain there on the owner machine. Input archive is in Downloads and identified by source-gate.json SHA256. Never substitute a stale source snapshot.

Order: intake (new source directory, verifies every archive hash and preserves mtime); prepare; probe measure; probe validate-inventory; finalize measured plan; probe render; visuals; capture; cost on fresh isolated roots; probe schema; deliver. Re-running cost requires fresh root names because its cold-cache state matters. Calling native PHP in a sandbox that reports cache inaccessible is rejected by probe.php; accepted local PHP runs used the permitted filesystem runtime. Scripts do not contact production or a database.

PHP: `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`, memory_limit=1024M. Twig autoload: `work/pay003/tooling/vendor/autoload.php`. Python runtime: bundled codex-primary-runtime Python (Pillow). Node: bundled runtime, Playwright with installed local headless Chrome. Browser requests are fulfilled from local source/fixture files, with all other origins blocked. Auxiliary Manrope font fixture files come from the earlier WP1 tooling snapshot and are not substituted live target code. All source PHP/Twig/CSS under the fresh source directory is read-only in these instruments.

The HTML comparison uses only screenshot files and can be opened directly without the diagnostic route. Individual prototype HTML pages require capture.mjs's local resource routing; they are not stand-alone deployed pages.
