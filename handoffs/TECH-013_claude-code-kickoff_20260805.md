# TECH-013 — Claude Code kickoff

Paste the block below as the first message in Claude Code, launched from the repo root:

```
C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops
```

Set the model to **Opus** and thinking depth to **high** before sending.

---

## Message 1 — orientation and plan (do not let it write code yet)

```text
You are the assigned executor for Booster Shop task TECH-013 (Mobile Core Web Vitals,
Stage 1). Owner: Raccoon.

Read these first, in this order, and follow them literally:
1. CLAUDE.md and AGENTS.md in this repo — the operating contract. Note the 2026-08-05
   amendment: Claude Code is now an authorized patch author.
2. handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md — Rev. 2026-08-05. This is the
   canonical handoff. Execute from this file only.
3. handoffs/handoff_TECH-013_mobile-core-web-vitals_20260716.md is SUPERSEDED. Read it for
   the 2026-07-16 baseline only. Do not execute from it.

Hard constraints:
- There is NO staging. Every patch lands directly on production.
- You never commit, push, or deploy. The owner uploads a patch to ~/public_html and runs
  `php <patch>.php`. That is the only delivery channel.
- One work package = one PHP patch runner, per the AGENTS.md patch conventions
  (file-exists check, anchor pre-check, backup to _patch_backups/, php -l gate, idempotent
  marker, self-delete). Never bundle work packages.
- There is no local copy of the live theme. Live state comes from the newest cPanel backup
  in the repo root: backup-8.5.2026_10-49-27_boosters.tar.gz. Extract only what you need.
  If a file you need is missing, stop and ask the owner to export it.
- Do not touch anything in section 5 of the handoff. Do not fix unrelated bugs, reformat
  files, or upgrade dependencies.
- The working tree already contains uncommitted owner changes (3d-print, dashboard,
  ROADMAP_SOP.md and others). Do not stash, revert, commit or otherwise disturb them.

Your first task is ORIENTATION ONLY — write no patch code yet. Deliver:
a) confirmation of the active theme name and the real paths of header.twig / footer.twig
   / theme stylesheets, as found in the backup;
b) the current render-blocking inventory as it actually is now, compared against the
   handoff section 2A list from 2026-07-16 — flag every item that has changed;
c) whether a CDN or minifier extension is installed, and where fonts are loaded from;
d) the current state of .htaccess: quote the existing cache/compression directives and the
   sitemap-no-compression block verbatim, so we know exactly what we are appending to;
e) confirmation that width/height attributes from TECH-003 are still present on img tags
   (TECH-003 is now a subtask of this task — verify, do not redo);
f) your proposed order of work packages with a one-line risk note each, and anything in
   the handoff that contradicts what you actually found in the code.

Then stop and wait. Do not start WP1 until I approve the orientation report.
```

---

## Message 2 — after you approve the orientation report

```text
Orientation approved. Proceed with WP1 (render-blocking) only.

Before writing the patch:
- capture `curl -sI` header baselines for /sitemap-full.xml and /robots.txt and save them
  into diagnostics/ — we compare against these after every deploy;
- name the root cause for each change you make (which specific tag/rule/line causes the
  block), per the UI/CSS patch discipline in AGENTS.md. No guess-and-override.

Deliver one patch file: patches/TECH-013_wp1-render-blocking_20260805.php
Then report: what it does in 1-2 sentences, the local path, and the exact run command in
one terminal block. Do not proceed to WP2.
```

Repeat the same pattern for WP2, WP4, then WP3 last.

**WP3 requires a separate explicit owner approval before deploy** — `.htaccess` is a risky
zone. Unblocked as a sequencing matter (owner decision 2026-08-05), still gated on authority.

---

## After each deploy — owner checklist

1. Open the site. Header, footer, mini-cart, product cards, account menu — mobile and desktop.
2. Re-run PSI (mobile + desktop) on all three benchmark URLs; save the numbers.
3. `bs-checkout-smoke` — full 11-step run. Mandatory: header/footer render on checkout too.
4. Compare `/sitemap-full.xml` and `/robots.txt` headers against the saved baseline.
5. Only then deploy the next patch. Never two patches back to back without checking between.

Benchmark URLs (owner-designated, 2026-08-05):

- `https://boostershop.website/`
- `https://boostershop.website/catalog/Pokemon`
- `https://boostershop.website/product/Pokemon-boosters-Mega-Symphonia`

---

## Rollback, if something breaks on the live site

Every patch backs up what it touches to `_patch_backups/<patch>-<timestamp>/` before writing.
Restore that folder's files to their original locations. No DB changes are made in this task,
so a file restore is a complete rollback.

For WP3 specifically: the block is delimited `# BEGIN BS-SPEED-1 cache` / `# END BS-SPEED-1
cache` — delete those lines and everything between them, or restore
`.htaccess.bak-tech013-20260805`.
