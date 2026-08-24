# Claude review — 3D-P-007 WP2b: draft queue and article editing

Date: 2026-08-24 | Executor: Codex | Reviewer: Claude (chat)
Handoff: `handoffs/handoff_3D-P-007-WP2b_draft-queue-and-article-editing_20260823.md`
Codex report: `diagnostics/3D-P-007-WP2b_draft-queue-and-article-editing_report_20260823.md`

**Verdict: Deploy OK; є неблокуючі зауваження.**

The seven PHP patch conventions in `AGENTS.md` do not apply here. This is an Apps
Script full-file replacement pasted into the script editor plus an in-place edit
of the dashboard file — neither runs through `php patch.php`.

## What was reviewed and how

| Surface | Method | Result |
|---|---|---|
| `3d-print/apps-script-3dp-api/Code.gs` | Full `diff` against the owner's V29 export, LF-normalised | 13 hunks, 23 lines removed, 52 added. Every hunk is inside `assignNomenclatureSkuAction3dp_`, `nomenclatureKeyHistory3dp_`, `findSkuHistoryRow3dp_`, or the doc comment above the first. Nothing else in the 3748-line file changed. |
| `tests/api.test.mjs` + `tests/role-read-projections.test.mjs` | Executed in the sandbox on a copy | Pass. `role-read-projections` needs a V29/V25/V23 export present in the repository root or it aborts with `Expected a V29, V25, or V23 baseline export`. |
| `dashboard/booster-dashboard.html` | Direct read of the added functions; anchor counts | New code reviewed in full. `ROADMAP_TASKS` still 104 rows. No new token or `/exec` URL. **Not** independently diffed — see finding 4. |

## Verified correct

**Status handling.** `Чернетка` → assign + activate; `Активний` → rename, status
untouched (`if (oldStatus === API_3DP.draftStatus) statusRange.setValue(newStatus)`).
The history line branches too: an active rename writes «Артикул змінено: … → …»
with no status transition, asserted by `assert.doesNotMatch(… /статус: Активний →/)`.

**All seven SKU-keyed locations are checked**, and every sheet constant resolves
against the real `SHEETS_3DP` map. Each blocker is named in the refusal message,
and the test loop sets a key in each of the seven sheets in turn and asserts both
`SKU_HISTORY_EXISTS` and the sheet name in the message.

**The formula-mirror exclusion is correct, verified independently.**
`analyticsFormulaEntries3dp_` writes `Аналітика` column 1 as
`='Номенклатура'!A<row>`, so `storedOnly: true` cannot block on it, and the
mirror follows a rename. Codex's live bounded read shows `Наявність!A2:A4` are
likewise `='Номенклатура'!A<row>`. Both are asserted post-rename in the tests.
The guard still fails closed if a literal key is ever typed into either sheet —
that case is in the seven-location loop.

**Rollback.** Two failure paths are tested: a forced `appendAudit3dp_` throw
restores SKU, status and history; an `ANALYTICS_SCHEMA_NOT_READY` failure rolls
the canonical assignment back to the draft key and leaves the row `Чернетка`.

**Refusals.** Non-owner (`serhiy`) → `FORBIDDEN`; lowercase/non-canonical article
→ `INVALID_SKU`; duplicate → `SKU_DUPLICATE`; generic `3dp_write` on
`Номенклатура!A` → `SPECIALIZED_ACTION_REQUIRED`, unchanged.

**Dashboard.** The client pattern `/^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$/` is
character-identical to `NOMENCLATURE_SKU_PATTERN_3DP`, and the contract test
compares the dashboard's suggestion map against
`NOMENCLATURE_DRAFT_SUGGESTIONS_3DP` by deep equality. The mnemonic is a manual
input with no generation path; the submit button stays disabled until the pattern
matches **and** the confirmation checkbox is ticked; an unmapped mechanic shows
the stop state instead of selecting a nearest category. API errors reach the user
verbatim (`text: e.message`), so `SKU_HISTORY_EXISTS` shows the blocking sheet.
Drafts are excluded from the product selector and from active-SKU helpers;
archived rows get no editor. Draft card DOM keys use `row_number`, which
`rowObject3dp_` always emits and which is unique per row.

## Findings

| # | Severity | Where | Issue | Canon |
|---|---|---|---|---|
| 1 | non-blocking | `tests/role-read-projections.test.mjs` | `SKU_STATUS_NOT_EDITABLE` has **no test**. It is the only new branch with zero coverage, and it is the one that stops an `Архів` row being renamed. The logic itself is a two-element `indexOf` and reads correctly. | Handoff §acceptance asked for the new cases to be covered; this one was missed and the report does not list it either. |
| 2 | non-blocking | `Code.gs` `assignNomenclatureSkuAction3dp_` | The optimistic lock is decorative: the guard compares `body.expected_draft_sku` against `body.draft_sku`, both supplied by the same client call, so it can never fire. The dashboard passes the same value twice. **Pre-existing in V29**, not introduced by WP2b. Real protection comes from key-based row resolution (`ROW_NOT_FOUND` if the key moved), the status check and `assertNomenclatureSkuUnused3dp_`. | The Codex report calls it "optimistic locking on both paths", which overstates it. Do not rely on it; do not fix it in this round — that is a separate scope. |
| 3 | non-blocking | Codex report, Owner QA | The checklist asks to inspect the `Вироби` zone "at desktop, tablet, and mobile widths". The dashboard is **PC-only** by owner decision; mobile belongs to NCRM after migration. | Skip that item. The responsive CSS itself is harmless. |
| 4 | needs owner action | `dashboard/booster-dashboard.html` | Reviewed the added functions in full and they are sound, but with no baseline copy of the 574 KB pre-patch file this review cannot prove that **nothing else** in it changed. That file was replaced wholesale twice in August. | Owner runs one `git diff --stat` before publishing — command in the owner section. |
| 5 | expectation, not a defect | `_Чернетки_партій` in the history guard | Any SKU that has ever been through the batch calculator has a stored draft row, so its rename will be refused. In practice active rename is only available on a SKU created through owner quick-create that has not yet been calculated. | This is the specified fail-closed behaviour. It matters for choosing the QA SKU. |
| 6 | carried risk | `assignNomenclatureSkuAction3dp_` | Article assignment still does not call `assertNomenclatureSkuMatchesType3dp_`, while owner quick-create does. The dashboard therefore permits renaming a keychain into a `FIG-` article if the owner picks the wrong category. | Documented out-of-scope in the handoff — tightening it could invalidate existing drafts. The stop state and the confirmation checkbox are the only guards. |
| 7 | unverified claim | `tests/3d-p-013-dashboard-ui-regression.test.mjs` | Codex reports three failing assertions and states all three reproduce against `HEAD` before WP2b. Not independently verifiable here without git history. | Folds into the same owner `git` check as finding 4. |

## Scope

- S1 · Do-not-touch respected. `crm/apps-script/**` untouched; `serhiy-local-server` untouched; `ROADMAP_TASKS` untouched (104 rows before and after).
- S2 · One work package, two authorised surfaces, as the handoff specified.
- S3 · Risky zone: **CRM adjacency**. The 3D-P API cannot see the main CRM, so renaming a 3D-P article does not rename a same-named CRM SKU. Codex named this. It is the sharpest operational risk of the feature.
- S4/S5 · Not applicable — no storefront CSS, no SEO URLs.

## Safety

No destructive data operation, no secret in either file, no unbounded loop, both
files parse (`new vm.Script(code)` on `Code.gs` passes as part of the test run).

## Rollback

- **Apps Script:** create a named Google Sheets version *before* publishing, and keep the previous Web App version. Rollback is republishing that version. Note this patch adds no schema migration, unlike WP1b, so a code rollback is complete.
- **Dashboard:** the repository file is the only copy. Rollback is `git checkout -- dashboard/booster-dashboard.html` plus `Ctrl+F5`. Do that *before* committing if the patch has to be abandoned.

## Post-publication smoke

`bs-deploy-verify` does not apply — nothing here touches `boostershop.website`.
The handoff's own QA checklist plus the corrections in findings 3 and 5 is the
verification set.

---

# Round 2 — re-review after the parallel changes, 2026-08-24 ~14:30 Kyiv

**Verdict: Deploy OK.** Every actionable finding from round 1 is closed and
verified. One owner action remains (finding 4), unchanged.

## Round-1 findings — status

| # | Status | Evidence |
|---|---|---|
| 1 · `SKU_STATUS_NOT_EDITABLE` untested | **closed** | `tests/role-read-projections.test.mjs` +6 lines: a fresh workbook, `Номенклатура` row 2 status set to `Архів`, `assignNomenclatureSkuAction3dp_` asserted to return `SKU_STATUS_NOT_EDITABLE`. |
| 2 · report overstated the optimistic lock | **closed** | Report §Apps Script now says it "is compared with the same request key used to locate the row… a redundant consistency check, not an independent optimistic lock". The dashboard contract test's assertion message was corrected the same way. Honest and accurate. |
| 3 · QA asked for tablet/mobile widths | **closed** | Checklist now reads "the PC-only `Вироби` zone at the owner's normal desktop width". |
| 4 · dashboard delta not independently verified | **open — owner action** | Still no pre-WP2b baseline available to this review. See below. |
| 5 · `_Чернетки_партій` blocks in practice | unchanged | Expectation, not a defect. Still governs the QA SKU choice. |
| 6 · prefix/type asymmetry | unchanged | Documented out of scope. |
| 7 · stale `3d-p-013` suite | **open — owner action** | Folds into the same git check. |

## What changed since round 1

`3d-print/apps-script-3dp-api/Code.gs` is **byte-identical** to the file reviewed
in round 1 — the 13-hunk diff analysis above stands without re-examination.

`dashboard/booster-dashboard.html` moved 586 645 → 586 936 bytes, 4436 → 4439
lines. Diffed against the exact copy reviewed in round 1: **4 lines added, 1 line
changed, nothing else**, and none of it near the WP2b code:

- three lines of CSS for `.writeoff-line .line-item-grid`;
- `div.className = 'line-item' + (kind === 'writeoff' ? ' writeoff-line' : '')`.

That is the owner's parallel write-off layout work. The WP2b code is untouched by
it and it is untouched by WP2b.

`dashboard/tests/dashboard-contract.test.mjs` gained two assertions covering that
write-off CSS, plus the corrected assertion message from finding 2.

## Verification re-run

All three declared suites executed in the sandbox on a copy of the current files:

```
node 3d-print/apps-script-3dp-api/tests/api.test.mjs        → ok
node dashboard/tests/dashboard-contract.test.mjs            → ok
node dashboard/tests/3dp-sync-journal-static.test.mjs       → ok
```

`api.test.mjs` still requires a V29/V25/V23 export in the repository root or it
aborts before running. The root currently holds V29 and V148 only.

## CRM V148 — independently confirmed

The report's CRM reconciliation claim was checked rather than taken on trust.
Using the normalisation `crm/apps-script/SOURCE_STATE.md` states — BOM removal,
CRLF→LF, drop one terminal newline — the export
`Версія 148, 24 серп. 2026 р., 1306.csv` reduces to **513 365 bytes, 8395 lines**,
SHA-256 `688f7a6476aea597f76fae7f307bf3a5f3a79b465e33b401098fae57317bea57`,
MD5 `09080796f2a7fe818d0fb6e0ef3e9696`, and is byte-for-byte identical to
`crm/apps-script/Code.gs`. Both published hashes reproduce exactly.

Two notes on that record, neither affecting the conclusion:

- the export carries **no BOM**, so that step of the stated normalisation is a
  no-op here — harmless, but the recipe only reproduces if the terminal-newline
  step is applied too. `SOURCE_STATE.md` states it in full; the Codex report
  (§Verification) says only "after newline normalisation", which under the
  obvious reading gives 513 366 bytes and a different hash. Use the
  `SOURCE_STATE.md` wording as the canonical recipe.
- the filename separates the year from `р.` with U+202F (narrow no-break space),
  not a plain space. A path typed with a normal space will not resolve.

## New findings

| # | Severity | Where | Issue |
|---|---|---|---|
| 8 | non-blocking | `dashboard/booster-dashboard.html` | The file now carries **two work packages**: WP2b and the write-off layout fix. Rollback granularity is lost — `git checkout -- dashboard/booster-dashboard.html` would revert both. If WP2b has to be abandoned after publication, the write-off change must be re-applied by hand. |
| 9 | non-blocking | Codex report, §Dashboard bounded evidence | The `ROADMAP_TASKS` row metric silently changed between report revisions: 104 → 104 became 217 → 217. Both numbers reproduce (104 = lines matching `^ *id: '…', title:`; 217 = occurrences of `title: '`, which includes subtasks) and both show the block unchanged, so nothing is wrong — but a future before/after comparison must state which rule it used. |
| 10 | non-blocking, scope | `crm/apps-script/SOURCE_STATE.md` | The WP2b handoff put `crm/apps-script/**` on the do-not-touch list, and this round's "Files touched" includes that file. The content it added is correct and independently verified above, so no harm resulted — but that file has a parallel writer, and a documentation edit crossing a stated boundary is the same class of drift the list exists to prevent. |

## Still required before publication

Unchanged from round 1, finding 4. This review can now prove what changed in the
dashboard **after** WP2b, but not what WP2b itself changed, because no pre-WP2b
copy of the 574 000-byte file is available here. One git read closes it, and the
same output settles finding 7.

## Finding 4 — closed by the owner's git read, 2026-08-24

`git diff --numstat -- dashboard/booster-dashboard.html` → **73 insertions,
6 deletions** against `HEAD`.

That reconciles exactly with the reported and measured line counts:
4372 → 4439 is net +67, and 73 − 6 = 67. Of that delta, 4 insertions and 1
deletion are the parallel write-off layout change, which this review diffed
directly; the remaining 69 insertions and 5 deletions are WP2b.

The point of the check was to rule out a wholesale replacement of the
586 KB file. Six deleted lines rules it out. **Finding 4 is closed.**

`git diff --stat` over the whole working tree shows 15 modified paths, all
accounted for: the WP2b surfaces and their tests, both `SOURCE_STATE.md` files,
`context-index.md` and the decision-list bullet (Claude, this session), the CRM
V148 delta and its removed one-off recovery test (parallel session), and three
deleted source exports. Nothing unexplained.

### Two things that turned up in the same output

- **Deleted exports.** The working tree removes the V25 `.txt`, the V140 `.csv`
  and a 4714-line `.txt` export. `tests/role-read-projections.test.mjs` refuses to
  run unless a V29, V25 or V23 export sits in the repository root — with V25 gone,
  the V29 file is now the single dependency keeping that suite runnable. Do not
  clean it out of the root.
- **Two dormant stashes.** `stash@{0}: On master: pre-rebase` and
  `stash@{1}: WIP on master: 6c04032 tg: update ToV v2 + plan status 21.06`, the
  second dating to June. Harmless where they sit; a `git stash pop` on either
  would drop old content over the current working tree. Leave them alone.

### Finding 7 — one command short of closed

The stale suite `tests/3d-p-013-dashboard-ui-regression.test.mjs` is itself
unmodified, so nobody rewrote it to fit. But WP2b does account for 5 of the 6
deleted dashboard lines, and Codex's claim is that two of the three failures are
regexes that are "already absent" — absence caused by a deletion. Whether those
deletions are WP2b's is settled by printing the six removed lines.

## Finding 7 — closed, 2026-08-24

The six deleted dashboard lines, printed with `git diff -U0`:

| Deleted line | Replaced by | Package |
|---|---|---|
| `threeDpActiveSkus()` filtering `threeDpStatus(r) !== 'Архів'` | the same helper filtering `=== 'Активний'` | WP2b |
| `threeDpStatusHtml(row)` with a binary archived/active branch | a three-state branch including `draft` | WP2b |
| `const row=threeDpSku(…),existing=Boolean(row),archived=…` | the same line with drafts resolved to `null` | WP2b |
| `const options=threeDpState.skus.slice().sort(…)` — every SKU | the same list with `Чернетка` rows filtered out | WP2b |
| the product-form `root.innerHTML='…'` line | the same line plus the article editor | WP2b |
| `div.className = 'line-item';` | the `writeoff-line` branch | write-off fix |

None of the six touches an information-render path, anything containing
`adjust_stock`, or the body of `saveThreeDpProduct` — that function appears in the
deleted set only as an `onclick` reference inside the product-form string, and its
definition was never deleted. Codex's three claims about
`tests/3d-p-013-dashboard-ui-regression.test.mjs` therefore hold: those assertions
were already failing before WP2b, and this patch neither caused nor masked them.
**Finding 7 is closed.** The stale suite remains a separate hygiene item.

### One positive worth recording

The first deleted line is the polarity fix the handoff demanded. `threeDpActiveSkus`
asked "is this row **not archived**?"; it now asks "is this row **active**?". Under
the old wording a `Чернетка` would have counted as an active SKU throughout the
dashboard — the exact failure mode that made up the bulk of WP1c's work. Codex
applied the rule on the client side without being pointed at this specific helper,
and `threeDpStatusHtml` was widened from two states to three in the same pass.

---

# Round 3 — post-QA changes, 2026-08-24 ~15:40 Kyiv

The owner's QA did not pass first time and he directed fixes to Codex directly.
Publications since round 2: 3D-P **V30** (14:48) and **V31** (15:31), CRM **V149**
(15:30). This section records what changed against the round-2 state, which is the
last state this review had verified.

## What changed

**`3d-print/apps-script-3dp-api/Code.gs` is byte-identical to the reviewed
candidate.** V30 was published from it; V31 carries no source change in the
repository, so it reads as a re-publication alongside the CRM release. The
round-1 diff analysis still covers the whole 3D-P API surface.

**The dashboard article editor was rebuilt** (40 lines removed, 34 added):

- the mnemonic input, the live canonical preview, the `valid`/`invalid` styling,
  the stop-state block and the confirmation checkbox are **gone**;
- the category dropdown remains but is now advisory: it must be chosen from the
  canonical list, and that choice is validated, but it no longer builds the
  article;
- the article is typed whole into a free-text field;
- `THREE_DP_ARTICLE_PATTERN` was dropped from the client. The canon is still
  enforced — `canonicalNomenclatureSku3dp_` rejects a malformed article
  server-side — but the dashboard no longer pre-validates it.

**This change is correct, and round 1 should have caught the flaw it fixes.**
The old form built the article as `prefix + '-' + mnemonic + '-' + category_digits`
where `category_digits` was always a round hundred. The canon's third segment is
`XYZ`: X hundreds = category, **Y tens = subtype, Z units = variant**
(`plans/3D-P_sku-naming-convention_20260807.md`, and its own table maps
`FIG- 400` subtype 1 to «Картина»). The reviewed form could therefore emit only
`X00` articles and could never express a subtype — `FIG-LUFFY-410`, the article
the owner actually needed for «Картина Луффі», was unreachable through it. Round 1
verified the form against the regex and against the suggestion map, but not
against the canon's own digit semantics, and missed this.

**CRM rename propagation was added** — `crm/apps-script/Code.gs` grew by 61 net
lines and was published as V149:

- new `crm3dpCatalogSkuHistory_` locates a SKU across the CRM catalogue sheets;
- `sync_3dp_catalog_rrp` now accepts `previous_sku` and `expected_name`, so an
  active 3D-P rename can carry through to CRM `Товари` / `РРЦ` / `Склад`.

The dashboard drives it in this order: snapshot the CRM row and refuse if the
article is absent there; run CRM `integrity_check` and stop if it reports
problems; rename in 3D-P; then rename and sync RRP in CRM; then re-run
`integrity_check`. The CRM sync path additionally detects an orphaned old article
by matching product name, refuses when more than one candidate matches, and asks
for confirmation when exactly one does.

This addresses the sharpest risk named in round 1 (S3): a 3D-P rename used to
leave a same-named CRM SKU behind.

## Findings

| # | Severity | Issue |
|---|---|---|
| 11 | needs owner decision | `crm/apps-script/**` was on the WP2b do-not-touch list, and CRM code was not only changed but **published to live as V149**. The change is substantively right and closes finding S3, but it entered production without a handoff and without review. Nothing in this document covers the CRM half. |
| 12 | needs owner action | No source export exists for 3D-P V30/V31 or CRM V149, so neither mirror is proven equal to what is running. The 3D-P side is inferable (its file is unchanged from the reviewed candidate); the CRM side is not — its file changed after the V148 comparison. |
| 13 | operational risk | Renaming an active article is now a **two-system write with no atomic guarantee**. If the 3D-P rename succeeds and the CRM step fails, the shop is left with the new article in 3D-P and the old one in CRM. The code detects this and returns an explicit message naming both articles plus a retry path, which is the honest handling available across two separate Apps Script projects — but the owner must act on that message rather than dismiss it. |
| 14 | non-blocking | The stop state for a mechanic with no canonical category is gone from the UI. The owner's 2026-08-16 decision requires stopping and asking rather than picking the nearest category; that obligation now rests entirely on the owner, with no prompt. |

## Not re-verified in this round

The rebuilt dashboard editor and the CRM rename path have not been run through
their test suites here, and the updated Codex report (11 517 bytes) has not been
read line by line. Both are reviewable on request.

## Round 3 addendum — V149 / V31 verification, 2026-08-24

Finding 12 is withdrawn. The owner's flow is repository → live: he pastes
`crm/apps-script/Code.gs` and `3d-print/apps-script-3dp-api/Code.gs` into the
script editors and publishes. Those files therefore **are** the published source
for CRM V149 (15:30) and 3D-P V31 (15:31); an export would only re-test paste
fidelity. Recorded as owner-reported publication from these exact files.

3D-P V30 and V31 were both published from a file byte-identical to the reviewed
candidate, so the round-1 analysis covers what is live.

Checks run on the current files:

- `new vm.Script()` on `crm/apps-script/Code.gs` — parses.
- All **20** CRM Apps Script test files — pass. (Two of them read
  `3d-print/apps-script-3dp-api/Code.gs` and `dashboard/booster-dashboard.html`,
  so the suite only runs from the repository root with all three trees present.)
- `3d-print/apps-script-3dp-api/tests/api.test.mjs` — passes.

### New finding

| # | Severity | Issue |
|---|---|---|
| 15 | needs a fix, not blocking live | **Two dashboard test files now fail against the code that is already live**, because the article-editor rebuild did not carry them along. `dashboard/tests/dashboard-contract.test.mjs` still asserts on `THREE_DP_ARTICLE_SUGGESTIONS`, which was renamed to `THREE_DP_ARTICLE_CATEGORIES` and reshaped from an object to an array — the suite dies at its first assertion. `dashboard/tests/3dp-sync-journal-static.test.mjs` still expects the button label «Синхронізувати РРЦ / додати CRM», now «Синхронізувати артикул / РРЦ з CRM». |

The product itself is unaffected — these are static string assertions over the
dashboard file, not runtime behaviour. What is lost is the check that mattered
most in this package: `dashboard-contract.test.mjs` was the only thing verifying
that the dashboard's category list still equals the server's
`NOMENCLATURE_DRAFT_SUGGESTIONS_3DP`. That comparison was therefore made by hand
here instead: **17 entries on each side, identical values in identical order.**
Consistent today, but nothing automated will catch the next drift until the two
test files are brought up to date.

Note also that the Codex report's Verification block still lists both dashboard
suites as passing. That was true of the round-2 state; it is not true of the code
now in the repository and on the owner's screen.

## Finding 15 — closed, 2026-08-24

Both test files were updated and the whole tree is green, verified here on a copy
of the current working tree:

- `dashboard/tests/dashboard-contract.test.mjs` — passes;
- `dashboard/tests/3dp-sync-journal-static.test.mjs` — passes;
- `3d-print/apps-script-3dp-api/tests/api.test.mjs` — passes;
- all **20** CRM Apps Script suites — pass.

The rewritten contract check is stronger than the one it replaces. It no longer
compares the dashboard against a copy of the mapping: it evaluates
`NOMENCLATURE_DRAFT_SUGGESTIONS_3DP` out of the live `Code.gs` in a VM context,
parses `THREE_DP_ARTICLE_CATEGORIES` out of the dashboard, and asserts the
category names **and their order** match, then the prefix and the three category
digits for each of the 17.

That was confirmed by a negative test rather than taken on trust: changing a
single category code in the dashboard copy (`Панно FIG 400` → `450`) makes the
suite fail, and restoring it makes it pass again. The drift this package could
have introduced is now actually guarded.

**All findings from rounds 1–3 are now closed or accepted, except the standing
items 11, 13 and 14**, which are decisions and operating risks rather than
defects: the CRM half went live without a handoff or review; an active rename is
a two-system write with no atomic guarantee; and the stop-state prompt for an
uncategorised mechanic no longer exists in the UI.
