# AGENTS.md — Booster Shop ops rules (Claude + Codex)
# Canonical location: booster-shop-ops/AGENTS.md
# If you find another AGENTS.md elsewhere, ignore it — this file wins.

## Project
OpenCart e-commerce: boostershop.website (MTG, Pokemon, One Piece, Yu-Gi-Oh).
Stack: OpenCart (Twig/PHP), custom checkout + NP integration, Google Apps Script CRM, Google Sheets.

## Core authority and writer rules

- Claude never commits or pushes. Claude Code never commits, pushes, or deploys.
- **Patch authorship is shared (2026-08-05, owner decision).** Patches in
  `patches/` may be authored by **Codex or Claude Code**. The owner assigns the
  executor per task. Two agents must never author patches for the same task in
  the same round — that is a parallel-writer violation.
- **Executor recommendation is mandatory.** Before or while preparing a handoff,
  Claude states a recommended executor, model and thinking depth (see
  "Executor, model and effort recommendation"). The owner decides; Claude then
  writes the handoff addressed to the chosen executor. Claude does not assign
  the executor itself.
- Codex may commit or push only after a direct, explicit owner request in the
  active task and only for the exact approved scope. This grants no standing
  permission. Otherwise Codex prepares changes, checks, and a concise diff
  summary only.
- The owner is the only production deployment gate and performs final manual
  QA.
- Claude is the sole default writer of Booster Notion task properties and
  statuses. Neither Codex nor Claude Code changes Notion properties or statuses.
- The assigned patch executor owns `ROADMAP_FLOW` changes required by an
  authorized roadmap-affecting implementation. Exceptions require explicit owner
  reassignment; agents must not compete as parallel status writers.
- **New-task mirroring (2026-08-06, owner decision).** Creating a task in the
  Notion roadmap is not an "implementation", so under the previous wording a new
  row had no defined path into the dashboard mirror and silently never appeared.
  Discovered when four tasks created on 2026-08-06 were missing from the
  dashboard. Therefore: **whoever creates a Notion roadmap row also creates its
  `ROADMAP_FLOW` row in the same session** — in practice Claude (chat).
  This grant is narrow. Claude (chat) writes dashboard rows **only for tasks it
  just created**. Status and progress updates on pre-existing rows remain the
  executor's, unchanged. If one session needs both a creation and a status change
  to an existing row, do the creation and hand off the status change rather than
  becoming a second writer.
- `scripts/auto_review.py` is the canonical implementation behind
  `bs-review.ps1` / `bsreview`. The repository-root `auto_review.py` is a legacy
  duplicate and must not be invoked.
- Use `bsreview --dry-run` for a read-only automated review. A normal
  `bsreview` may save a diagnostic and post a Notion comment, but it must never
  change a Notion property or status.
- Notion search is ranked content search. Prefer a known page ID and direct
  fetch; otherwise search by title or distinctive keywords and verify the
  returned page's Roadmap ID. Do not claim that an exact ID can never match.

## Local paths (owner's machine)
- **Repo (local):** `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\` ← primary working folder
- **GitHub:** `https://github.com/Bazilik141/booster-shop-ops` (branch: master)
- **Dashboard (single canonical file, 2026-07-28):** `dashboard/booster-dashboard.html` inside the repo — edit THIS file directly, commit as usual. The former standalone copy outside the repo is retired; do not recreate it.
- **Dashboard URL:** `file:///C:/Users/14bez/Downloads/Booster%20Shop/booster-shop-ops/dashboard/booster-dashboard.html`

Old paths retired — do not use:
- `E:\Personal Files\...`
- `E:\Program Files\...`

When Codex drops output files to the local machine, target:
`C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\<subfolder>\<filename>`

## Repo structure
```
handoffs/     task briefs (Claude → Codex scope boundary)
patches/      PHP/JS/CSS runners (Codex output)
plans/        roadmaps, audits, content plans
diagnostics/  post-patch reports (Codex output, risky/handoff tasks only)
dashboard/    canonical booster-dashboard.html (single file, edited directly)
templates/    handoff + report templates
```

## Environment
- **Terminal (Claude Code CLI)** — installed. Claude may use it for read-only
  `git diff`/`status`/`log` and shell diagnostics. Claude must never run
  `git commit` or `git push`; it prepares a complete owner-run command block.
- **VS Code (Claude Code extension)** — installed. Use for: viewing/editing repo files, inspecting diffs.

## Roles & boundaries
| Agent | Does | Does NOT |
|-------|------|----------|
| **Claude** (chat/Cowork) | audit, SEO/UX strategy, handoffs, executor recommendation, post-patch review, git diff, Notion status, prepares ready-to-paste commit/push command | write patches, server access, deploy, git commit/push |
| **Codex** | patches (`patches/`), reports (`diagnostics/`), authorized `ROADMAP_FLOW` changes | server access, deploy, Notion properties/status, commit/push without exact active-task approval |
| **Claude Code** (repo agent) | patches (`patches/`), reports (`diagnostics/`), local verification, authorized `ROADMAP_FLOW` changes | server access, deploy, Notion properties/status, commit, push |
| **Owner** | approves scope, assigns the executor, normally runs prepared Git commands, uploads and runs server patches, performs final QA | — |

Only one patch author per task per round. If the owner reassigns mid-task, the
previous executor stops before the new one starts.

## Flow
```
Claude executor recommendation → Owner assigns executor → Claude handoff
→ Codex OR Claude Code patch → drop to C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\patches\
→ Claude review (git diff) → Owner deploy (php patch.php in ~/public_html) → Owner QA
```

## Source of truth
- **Notion roadmap** — canonical task status and priorities.
- **Dashboard `ROADMAP_FLOW`** — mirror of Notion task status.
- **This repo** — implementation history, diffs, patch files
- **Owner cPanel backup drop** — live source for diagnosis (no server access)
- **Roadmap governance** (status, synchronization, Definition of Done, and
  writer roles) — `ROADMAP_SOP.md`.
- Claude writes Booster Notion task properties/status by default. Codex does
  not write Notion properties/status and updates `ROADMAP_FLOW` only when an
  authorized roadmap-affecting implementation requires it.

## Commit / push policy
- Apply the authority rules above. Without exact active-task commit/push
  authorization, show a concise diff summary and prepare one complete owner-run
  PowerShell block: enter the exact repo root, create `.autosync-pause`, stage
  only approved files, validate the staged set, commit/push, and remove the
  sentinel.
- For risky tasks (checkout, payment, schema, DB, `.htaccess`), propose a branch
  and pull request when appropriate.
- Commit message format: `Codex: <TASK-ID> <short description>`
- Do NOT include in the command: `.bak`, `.tar.gz`, `.zip`, `.log`, DB dumps, secrets/tokens.

## Patch conventions (PHP runner)
Each patch must:
1. **File exists check** — fail with clear error if target file not found; never blind-edit
2. **Anchor pre-check** — fail if anchor count != expected
3. **Backup** to `_patch_backups/<patch>-<ts>/` before write
4. **`php -l` gate** — restore-on-fail; no silent failures
5. **Idempotent marker** — `already_applied=yes` on repeat run. Markers live in the files the patch adds content to, never in a shared value another patch also rewrites (see 8)
6. **DB changes** — only with explicit owner approval + rollback SQL in patch header
7. **Self-delete** after success
8. **Asset cache-bust — read the token, never hardcode it.** A patch that must refresh a cached asset does not anchor on the token's current value and does not append to it. It locates the reference by its path prefix (`catalog/view/stylesheet/boostershop-ds.css?v=`), reads whatever token is there, validates its shape, and replaces it wholesale with its own. Patches then stop being coupled to each other's token values and run in any order. This only works together with 5: a patch whose content markers are already present exits `already_applied` without touching the token, which is correct — its CSS is already live, so nothing needs busting. Added 2026-09-18 after `CAT-004` and `CAT-004/SD-7` both wanted the same token and the second one worked around it by appending.

Naming: `patches/<TASK-ID>_<slug>_<YYYYMMDD>.php`
Drop to: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\patches\<same filename>`

After patch is ready, respond with:
- what it does (1-2 sentences)
- local path to the file
- run command: `php <filename>` in `~/public_html`
- one terminal block with the command

## UI/CSS patch discipline
Applies to any patch touching visual/layout CSS, Twig markup styling, or JS that changes visible behavior.

1. **Name the root cause before patching.** State which existing rule/selector/line currently produces the bug (file + line if known, e.g. `boostershop-ds.css:3476`). If unknown, investigate first — do not guess-and-override.
2. **Check override history first.** Before touching a shared/theme selector (`boostershop-ds.css`, `stylesheet.css`, any DS token), `grep` `patches/` and the live file for prior patches touching that selector. State what you found in the patch description.
3. **`!important` / new override requires justification.** Adding `!important` or stacking a new override on existing CSS is allowed only when the patch description states why editing the source rule directly is unsafe or out of scope. No stated reason → do not add it silently.
4. **No easy justification → offer options, don't default.** If a clean fix (edit source rule, remove dead override, refactor selector) is possible but bigger than scope, present two options to the owner: (a) quick override — 1-line trade-off, (b) proper fix at the source — 1-line trade-off + blast radius. Wait for the owner's choice; do not silently pick the cheaper one.
5. **UI acceptance criteria cover more than token values.** For any DS/layout/component patch, verify at minimum: 3 breakpoints (not one mobile width only), real long-content edge cases, and interactive states (hover/focus/active) — not only computed hex/token values.
6. **Shared CSS files are a soft risky zone.** Edits to `boostershop-ds.css`, `stylesheet.css`, or any DS token file affect multiple pages at once — apply the same override-stacking caution as `Risky zones` below, even when no business logic is touched.
7. **Review must scan for these signatures.** Claude's `git diff` review must explicitly check for `!important`, `setTimeout`, `position:absolute/fixed`, and magic pixel values with no comment. Unexplained hits → send back before commit, do not approve silently.

## Diagnostics report
Required for: handoff tasks, risky zones, diagnostic investigations.
Not required for: simple cosmetic patches (report in chat is enough unless owner asks).
Template: `templates/codex-report-template.md`
Naming: `diagnostics/<TASK-ID>_<slug>_report_<YYYYMMDD>.md`

## Live source (diagnosis input)
Live state comes from owner's **cPanel backup drop**.
- Always use the **newest backup** (check timestamp in filename)
- If a needed file is missing from backup, ask owner to run:
  `tar -czf booster-debug-files.tar.gz path/to/file1 path/to/file2`

## Apps Script mirrors (OPS-CODEMIRROR, owner decision 2026-08-08)
Both Apps Script projects are mirrored in the repository so an executor reads real code instead
of guessing a deployed version. This rule exists because three consecutive `3D-P-010` attempts
were planned against an assumed script version.

- **Main CRM:** `crm/apps-script/Code.gs` — state recorded in `crm/apps-script/SOURCE_STATE.md`
- **3D-P:** `3d-print/apps-script-3dp-api/Code.gs`

Rules:
1. Any task that reads, plans against, or patches either script **checks the pull date in
   `SOURCE_STATE.md` first**. If the mirror is older than the change being planned, request a
   fresh owner export before writing a handoff.
2. Whoever changes a live script refreshes the mirror **in the same session**, including the pull
   date and the deployed version.
3. Source is not deployment. Editing a script does not update the published Web App; never infer
   a deployed version number from source alone.
4. A mirror must never contain tokens. Both projects keep secrets in Script Properties; if a token
   appears in an export, stop and tell the owner rather than committing it.

### Temporary maintenance scripts

- Any one-time or task-specific repair/maintenance code must be created as a separate HTML file
  named exactly after the task ID.
- Do not add such code to existing production scripts.
- After successful execution and verification, remove the task action from the Sheet API.

## CRM integrity check (OPS-CRMINTEGRITY, owner decision 2026-08-09)

Any change that alters main-CRM sheet structure, adds or removes a row in
`Товари`, `РРЦ`, `Розхідники`, or `Майстер_Товарів`, or edits a formula column must:

1. run the read-only dashboard **CRM integrity check** before the change and record its bounded output;
2. never write a literal over a formula column;
3. run the same check after the change and include its bounded output in the diagnostic;
4. treat any new problem code as a defect of that change, not as pre-existing noise.

The check runs inside Apps Script and returns a capped problem list; it must not stream sheet
contents to an agent. Its companion runbook is `docs/CRM-new-SKU-runbook.md`.

## Risky zones — extra care + rollback + smoke test required
checkout · payment · Hutko · Checkbox · fiscalization · Nova Poshta · order status ·
Merchant feed · schema/JSON-LD · SEO (sitemap/robots/canonical/.htaccess) · CRM · DB

## Executor, model and effort recommendation

Every handoff carries an executor line immediately after its date:

`Executor: <Codex|Claude Code> · model=<...> · effort/thinking=<...>` — plus one
sentence of justification.

Claude proposes; the owner decides. If the owner overrides the recommendation,
Claude records the override in the handoff without arguing it again.

### Which executor

| Signal | Prefer |
|---|---|
| Task needs live-file discovery across an unfamiliar tree, or heavy local verification (build, test, image processing, measurement) | Claude Code |
| Task is a well-bounded patch against files already identified in the handoff | either — pick by remaining weekly quota |
| Task is long-running, multi-round, and mostly mechanical once specified | Codex |
| The other executor already worked this task this round | keep the same executor — never swap mid-round |

Weekly quota is a legitimate tie-breaker. State it explicitly when it is the
deciding factor, so the choice stays auditable.

### Codex model + effort

| Task type | Model | Effort |
|---|---|---|
| Risky-zone, multi-file, or architecturally ambiguous work | Sol | xhigh |
| Typical feature, bug fix, or tests | Terra | medium; high when multi-step |
| Mechanical copy, formatting, or small CSS/text change | Luna | low |

Use `ultra` only when the task clearly splits into independent parallel work;
it is not the default.

Source: OpenAI GPT-5.6 model guide, July 2026.

### Claude Code model + thinking depth

| Task type | Model | Thinking |
|---|---|---|
| Risky-zone, multi-file, or architecturally ambiguous work | Opus | high |
| Typical feature, bug fix, or tests | Sonnet | medium; high when multi-step |
| Mechanical copy, formatting, or small CSS/text change | Haiku | low |

Do not run risky-zone work on a small model. A weak model on a risky zone is
how unrelated code gets overwritten — this has already happened once on CRM and
3D-table work (owner report, 2026-08-05).

## Token and context efficiency
- For CRM and Google Sheets work, use the Apps Script API or narrow bounded ranges first.
- Do not export or read an entire workbook, large sheet, or session log when a targeted read suffices.
- A full export is allowed only when targeted reads cannot safely complete the task — tell the owner first.
- Default verification budget: one syntax check + one smoke-test pass per scoped change.
- Before structural edits to Apps Script source, read and preserve the complete
  affected function block.
- **3D-Print Sheet (once `3D-P-008` ships):** use the dedicated `BOOSTER_3DP_TOKEN` Apps Script API for any
  read or write against the 3D-P workbook (`3d-print/3D-P_nomenclature-tracker_*.xlsx` /
  `docs.google.com/spreadsheets/d/1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo`) — `3dp_get_row`,
  `3dp_get_range`, `3dp_overview`/`3dp_skus`/`3dp_sales`/`3dp_plyushky`/`3dp_payouts` for reads,
  `3dp_write`/`3dp_append_row` for scoped writes (manual-input cells only, always logged to `_Аудит_API`).
  Do not use a Drive full-document read (`read_file_content` or equivalent) on this workbook except for a
  one-off human-readable audit — it burns tokens re-reading the whole Легенда/Аналітика prose for what a
  narrow API call answers directly, and it cannot write. Until `3D-P-008` ships, a narrow Drive read (specific
  known cells, not the whole doc) is an acceptable fallback — never write to the Sheet by any means other than
  the API once it exists.

## OpenCart SEO URL rules
- Format: `Pokemon-boosters-Set-Name`, `YuGiOh-boosters-Set-Name` (human-readable)
- Box/display → use `booster-box` in URL; single packs → `boosters`
- SKU/article goes ONLY into the SKU field, never into SEO URL
- A variant family member appends its differentiator to the family's URL and leaves the
  plain URL free for the base product (`One-Piece-boosters-OP-01-Romance-Dawn-Rare-Pack`
  keeps `One-Piece-boosters-OP-01-Romance-Dawn` available). Every variant needs its own
  `ocp5_seo_url` row — without one the selector links to a parametric route, which GSC
  files as "alternate page with canonical" and the feed inherits.

## Variant products — canonical rules

Owner decisions of 2026-09-12…19 (`CAT-004`). Applies to every game and product type,
not only 3D-print.

- **Model — owner decision 2026-09-19, supersedes the 2026-09-12 one.** A variant family is
  a set of **ordinary, independent products**. No OpenCart master/variant, no `master_id`, no
  product options. One combination = one real product = one page = one article = one row in
  the accounting catalogue, and it owns its price, stock, photos and SEO outright.
  Membership is declared in three product attributes (below).

  Native master/variant was tried first and rejected on the evidence of a live test
  2026-09-18/19. Three reasons, in order of weight: the master is a template and holds no
  combination of its own, so either it is absent from its own family's selector or the code
  has to guess which combination is "left over" — a guess that collapses the moment one
  member is disabled; a variant inherits every field from its master until that field's
  override switch is on, so one forgotten switch silently overwrites a live product's price
  or stock, on a production shop with no staging; and the option rows carry price / points /
  weight modifiers that exist for a different feature entirely and have no meaning here.

- **Exactly one characteristic per family — owner decision 2026-09-19.** A family varies along
  one axis only: colour, or size, or deck type, or set — never two at once. Two axes were
  considered and dropped: they require combination matching (a colour chip must keep the
  current size), a rule for combinations that do not exist, and a repeating admin UI. If the
  3D-print line ever needs size × colour, that is a new task, not a silent extension of this
  one. Until then, code that assumes one axis is correct, not a shortcut.

- **Family membership lives in the shop's own table, not in product attributes.** Product
  attributes are customer-facing characteristics and render in the specification table on the
  product page; service data does not belong there. Membership is stored in a dedicated table
  written from a dedicated tab on the admin product form.

  | field | role | same across the family? |
  |---|---|---|
  | family key | groups siblings | yes, identical |
  | axis label | the row label above the chips, e.g. `Розмір` | yes, identical |
  | value label | the chip label, e.g. `21 см` | no, unique per member |
  | sort order | chip order inside the row | no |

  Chip order is this table's own `sort_order`, never the product's `sort_order` field, which
  belongs to category listings. Values are never derived from the article: `OP-JP-OP01-RPK`
  and `OP-JP-EB01-RPK` are one family and share no article prefix.

- **Selector.** Options-style chips whose values are ordinary links to the sibling's URL.
  No in-place swapping. A value whose sibling exists is always a link, sold out or not;
  `is-off` is styling only. Only a declared family member with no product renders as a
  non-link.
- **Price in chips is a per-group rule, not per-value.** If any value in a characteristic
  group has a different price, the price shows on every value of that group; if all match,
  it shows on none.
- **One family per game.** A Pokémon product is never a variant of a One Piece product. A
  selector that asks the customer to choose a game is not a characteristic selector.
- **Article suffixes.** Where every member has its own official set code, each gets its own
  full article and no suffix (`OP-JP-ST32-STD`, `OP-JP-OP01-RPK`). Where the members share
  one series and have no per-item official code, the family takes a base article plus one
  closed-list token per varying characteristic (`PKM-JP-EXSD-STD-GRS`;
  `ACC-3D-ONIX-110-21-BLK`). See `plans/3D-P_sku-naming-convention_20260807.md` ред. 9.
- **Names and URLs carry the differentiator** for variant products. The ред. 2 prohibition
  (2026-08-16) applied only while variations were expected to live as options on one page.
- **Listing membership is data, not code.** What appears in a category is controlled by
  `product_to_category` links: for TCG, one chosen member in the parent category and the whole
  family in the subcategory; for every other product type, one card per family. With no master
  to default to, the member shown in the parent category is an explicit editorial choice.
- **The admin trap is gone, and stays gone.** The master/variant override mechanism was the
  single largest data-loss risk in this area: a variant saved with an override off took its
  master's price, stock or images. Independent products have no inheritance, so there is
  nothing to forget. Do not reintroduce `master_id` for a variant family.
- **The failure mode moved, it did not disappear.** A member whose family key is missing or
  mistyped silently drops out of its family, and nothing warns anyone. The admin field
  therefore offers the existing keys as an autocomplete rather than plain free text, and
  entering a family stays a checked step: after entering all members, open one page and
  confirm the row shows every member.
- **A sold-out variant is not deleted.** The page keeps its accumulated search value; its chip
  greys out by the general unavailable rule.

## Product type registry (article type token, 4th segment)

| Token | Meaning |
|---|---|
| `BBX` | Booster box / display |
| `BST` | Single booster pack, normal supply |
| `RPK` | Single booster pack, **Rare Pack line** — old set, limited supply, priced accordingly |
| `STD` | Starter / constructed deck |
| `SET` | Multi-item set |
| `BLR` | Blister |
| `MBX` | Mystery Box |

`RPK` was added 2026-09-16 rather than reusing `BST`, so that a normal pack of the same set
keeps its canonical article when it appears.

**Rare Pack — claim boundary, binding on names, descriptions, attributes and FAQ.** The *set*
is rare, not the pack's contents: a Rare Pack has the same odds as any other pack of that set.
Every card in the line carries that sentence explicitly. Forbidden in this line: «лімітований»,
«ексклюзивний», «гарантований», any promise about contents, and any weighing/sorting vocabulary
outside the purpose-written origin paragraph. Claims about the publisher having stopped printing
a set must be verified per set before publication. Full canon:
`plans/CAT-004_op-rare-packs_identifier-canon_20260916.md`.

## Owner sync helpers
`bspush` / `bsmain` / `bsreview` — PowerShell commit/push helpers

## Claude runtime values

Values Claude's skills look up before producing a runbook, an audit, or a plan.
Filled rows are resolved from this file; `TBD` rows are genuinely unknown — an
honest gap is safe, a guessed value is not.

Read by: `bs-deploy-verify`, `bs-seo-audit`, `bs-competitor-watch`,
`bs-email-sequence`, `bs-campaign-plan`, `bs-keyword-map`.

**Never put passwords, API keys, tokens, or merchant credentials here.** Paths
and public URLs only. If a value cannot be recorded without exposing a secret,
write `owner-held`.

### Production

| Value | Setting |
|---|---|
| Base URL | `https://boostershop.website` |
| Product lines | MTG · Pokemon · One Piece · Yu-Gi-Oh · 3D-printed |
| Production PHP | `8.0` — all PHP code and patches must remain compatible with PHP 8.0 |
| Web root on server | `~/public_html` |
| Deploy command | `php <filename>` in `~/public_html`, run by the owner |
| Staging / test environment | none — patches execute directly on production |
| Server access for Claude | none — live state only via owner's cPanel backup drop |

### Backup and rollback

| Value | Setting |
|---|---|
| File backup | automatic, by the patch runner, to `_patch_backups/<patch>-<ts>/` before any write (convention C3) |
| Syntax safety net | `php -l` after write, restore-on-fail (convention C4) |
| Repeat run | `already_applied=yes` marker (convention C5) |
| Patch lifecycle | self-deletes after success (C7) — re-running requires re-upload |
| DB backup | **not covered by C3.** Separate dump required before any DB-touching patch: cPanel → Backup → Download a MySQL Database Backup (the OpenCart database). The full cPanel backup also carries a dump under `mysql/` |
| DB rollback | rollback SQL in the patch header (convention C6) |
| Live-state source | newest `backup-*.tar.gz` from the owner's cPanel drop, by filename timestamp |

A logically wrong but syntactically valid write passes C4, reports success and
self-deletes. That failure class is what post-deploy Tier 1 checks exist for.

### Tier 1 smoke URLs

Checked after every deploy, regardless of how small the patch looked.

| Page | URL |
|---|---|
| Home | `https://boostershop.website/` |
| Category — top level | `https://boostershop.website/catalog/Pokemon` |
| Category — nested | `https://boostershop.website/catalog/Pokemon/Pokemon-booster-box` |
| Product — sealed TCG | `https://boostershop.website/product/One-Piece-Boosters-OP-11` |
| Product — 3D-printed | `https://boostershop.website/product/FIG-CHARM-001` |
| Information page | `https://boostershop.website/information/oplata-i-dostavka` |
| Cart | `https://boostershop.website/index.php?route=checkout/cart` |
| Checkout entry | `https://boostershop.website/index.php?route=checkout/checkout` |

**Checkout runs the stock `checkout/checkout` controller** (modified during the
redesign). Corrected 2026-08-16 on owner confirmation; the previous wording here
sent every executor to the wrong file for weeks.

Evidence, today's backup `backup-8.16.2026_08-03-55_boosters.tar.gz`:

- `public_html/system/library/url.php` no longer rewrites `checkout/checkout`.
  Line 62 is only a comment: *"ST-2c cutover: stock checkout is default;
  SimpleCheckout remains installed."* The rewrite described in
  `diagnostics/CHECKOUT-001_phase0_audit_20260703.md` (`url.php:61-65`) is gone.
- The `SimpleCheckout` extension is nonetheless **still installed and enabled**:
  `ocp5_extension` id 60 (`SimpleCheckout` / `module` / `pinta_simple_checkout`),
  `ocp5_extension_install` id 15 (Pinta Webware 1.5.2, status 1), and
  `module_pinta_simple_checkout_status = 1`.

So: it is out of the request path but not removed. Do **not** treat its presence
in the file tree, the extension list, or the settings table as evidence that it
serves checkout — that inference was made on 2026-08-16 and was wrong. Equally,
do not assume it is inert before checking; disabling or deleting it is its own
task, with its own smoke test.

Reading older diagnostics: `CHECKOUT-001` (early July) describes the
SimpleCheckout-era request path and is history, not a map of current code. From
`CHECKOUT-004` (2026-07-15) onward the reports already say "new checkout" and
"old/legacy SimpleCheckout" — that wording is current and correct.

### Logs

| Value | Setting |
|---|---|
| OpenCart error log | `/home2/boosters/ocartdata/storage/logs/` (`DIR_LOGS`, outside the web root) |
| Access log | `/home2/boosters/logs/boostershop.website` |
| SSL access log | `/home2/boosters/logs/boostershop.website-ssl_log` |
| Rotated monthly archives | `/home2/boosters/logs/*-<Mon>-<YYYY>.gz` |
| Order-sync log | `/home2/boosters/logs/booster-async-order-sync.log` |
| Sitemap regen log | `/home2/boosters/logs/sitemap-regen.log` |
| How the owner reads them | cPanel File Manager or Errors view |

`public_html/php.ini` sets no `error_log` directive and has `display_errors`
commented out, so PHP notices surface in the OpenCart log above, not on the page.

### SEO

| Value | Setting |
|---|---|
| Sitemap URL | `https://boostershop.website/sitemap_index.xml` (declared in `robots.txt`) |
| Secondary sitemap file | `public_html/sitemap-full.xml` |
| `robots.txt` URL | `https://boostershop.website/robots.txt` |
| `robots.txt` policy | allows all; disallows faceted params `page$`, `sort`, `order`, `limit`, `filter_name`, `filter_sub_category`, `filter_description`, `filter_group` |
| Category URL pattern | `/catalog/<Category>` and `/catalog/<Category>/<Subcategory>` |
| Product URL pattern | `/product/<seo-name>` |
| Information URL pattern | `/information/<seo-name>` |
| SEO URL format | human-readable, e.g. `Pokemon-boosters-Set-Name` |
| Box / display URLs | use `booster-box` |
| Single pack URLs | use `boosters` |
| SKU in SEO URL | never — SKU lives only in the SKU field |
| Languages served | one storefront language; no language prefix in URLs and no `hreflang` alternates in the sitemap |
| Merchant feed | `public_html/merchant-feed.tsv` |
| Search Console access | `TBD` |
| Merchant Center access | `TBD` |
| Keyword map location | `TBD` — recommend `plans/keyword-map_<YYYYMMDD>.md`, single file |

### Email

| Value | Setting |
|---|---|
| Mail platform | `TBD` |
| Marketing opt-in at registration / checkout | `TBD` |
| Unsubscribe mechanism | `TBD` |
| Approximate list size | `TBD` |
| Consent basis on record | `TBD` |

### Competitors

Owner-supplied list for `bs-competitor-watch`. Claude never assembles this from
general knowledge of the market.

| # | Shop | URL |
|---|---|---|
| 1 | `TBD` | `TBD` |
| 2 | `TBD` | `TBD` |
| 3 | `TBD` | `TBD` |

### Maintenance

When a value changes, update it here and nowhere else. Skills read this section
live; a value copied into a handoff, a plan, or a skill file goes stale silently
and gets trusted anyway.
