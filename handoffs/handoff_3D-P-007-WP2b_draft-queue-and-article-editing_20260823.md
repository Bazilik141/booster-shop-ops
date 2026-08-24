# Codex Handoff — 3D-P-007 WP2b: draft queue and article editing

Date: 2026-08-23 | Parent: `3D-P-007` (Serhiy local server)
Executor: Codex · model=Sol · effort=xhigh — two surfaces in one work package
(live Apps Script + the 574 KB dashboard file), touching the SKU join key.
Owner assigned Codex and chose the single-patch, two-surface split on
2026-08-23.

## Verified state — 2026-08-23, ~20:20 Kyiv

| Surface | State | How it was established |
|---|---|---|
| 3D-P Apps Script | **V29**, published 2026-08-23 16:00 Kyiv. Mirror `3d-print/apps-script-3dp-api/Code.gs` is byte-identical to the owner's labelled export (LF-normalised MD5 `d2f8256c5e21acf14ec442cf4533fff4`, 3718 lines, 182 319 bytes LF). | Direct diff of export against mirror. Recorded in that folder's `SOURCE_STATE.md`. |
| `dashboard/booster-dashboard.html` | Unchanged since 2026-08-23 16:51 Kyiv: **574 000 bytes, 4372 lines**, `ROADMAP_TASKS` declared at line 2414, **104** rows matching `^ *id: '…', title:`. | Direct read. |
| Main CRM Apps Script | **V144**, owner-reported publication 2026-08-23 20:11 Kyiv, after the `CRM-012` cleanup removed the `OC-FOP-0326` and `OC-FOP-0320` recovery helpers. **No V144 export exists**, so the mirror is not byte-verified against live. | `diagnostics/CRM-012_oc-fop-0326-abyss-sku_report_20260823.md`, `crm/apps-script/SOURCE_STATE.md`. |

Before you start, re-take the two dashboard numbers:

```
wc -lc dashboard/booster-dashboard.html
grep -cE "^ *id: '[A-Za-z0-9-]+', title:" dashboard/booster-dashboard.html
```

If they differ from 4372 / 574 000 / 104, another session edited the file after
this handoff was written — reconcile before touching it, and re-check the same
two numbers after your edit so only your own change is visible in the delta.

## Context

Serhiy can already create a product as a `Чернетка` with a generated `DRAFT-`
key and no article (WP1c, live in 3D-P Web App V29). Nothing on the owner side
surfaces those drafts or lets the owner turn one into a canonical article — the
API action exists, the dashboard does not call it. The V29 mirror is a correct
base for a full-file replacement.

`dashboard/booster-dashboard.html` currently calls `3dp_nomenclature_owner_create`
(quick-create), `3dp_nomenclature_archive` / `_restore`, `3dp_manufacture_batch`,
`3dp_batch_draft*`, `3dp_adjust_stock` and the payout actions. It does **not**
call `3dp_nomenclature_assign_sku` — zero occurrences in the file.

## The API gap this package closes

`3dp_write` on `Номенклатура!A` is refused with `SPECIALIZED_ACTION_REQUIRED`,
and `assignNomenclatureSkuAction3dp_` refuses any row whose status is not
`Чернетка` (`DRAFT_REQUIRED`). So there is no path at all to change the article
of an already-created product. The owner's decision
(`plans/3D-P-007_serhiy-data-scope-decision-list_20260816.md`, «Присвоєння
артикула — гібрид») requires that path, bounded by: free while the item has no
history, blocked once it has any.

## Scope (what to change)

### A. `3d-print/apps-script-3dp-api/Code.gs` — full-file replacement

1. **Relax `assignNomenclatureSkuAction3dp_` to two accepted starting states**,
   keeping it owner-only:
   - status `Чернетка` → assign article, set status `Активний` (current
     behaviour, unchanged);
   - status `Активний` → replace the article, **leave the status untouched**.
     The history line must read as an article change, not a status transition.
   Any other status stays refused. Keep `expected_draft_sku` optimistic locking
   and rename the field only if you also keep the old name accepted.
2. **Extend the history guard before allowing a rename.** Today
   `nomenclatureKeyHistory3dp_` checks only `Друк-лог` and `Продажі`. SKU is the
   join key for more than that. Before this ships, enumerate every location keyed
   by the SKU string and refuse the rename when any of them holds a row for it:
   - `Друк-лог`, `Продажі` (already covered),
   - `_Чернетки_партій` (batch drafts — `batchDraftStorageKey3dp_` stores the raw
     SKU, optionally actor-prefixed),
   - `_Коригування_наявності` (stock ledger),
   - `Маркетингові_плюшки`,
   - `Аналітика` (`syncActiveNomenclatureAnalytics3dp_` maintains a per-SKU row),
   - `Наявність` — **verify from the live sheet whether `A` is a formula mirror
     of `Номенклатура` or a stored value.** If it is a mirror the rename follows
     automatically; if it is stored, a rename orphans the availability row and
     the stock formula. Do not assume; report what you found.
   Refuse, do not migrate. A cross-sheet key migration is explicitly out of scope
   and returns `SKU_HISTORY_EXISTS` with the blocking location named in the
   message so the owner can see why.
3. Re-run `syncActiveNomenclatureAnalytics3dp_` after a successful rename, as the
   draft path already does, and confirm the old article leaves no orphan
   Аналітика row.
4. Audit and history: one `_Аудит_API` entry with old and new article, and one
   appended `API_історія_змін` line with the actor. Full rollback of every
   changed cell if the audit append fails, matching the existing pattern.
5. Update `3d-print/apps-script-3dp-api/tests/api.test.mjs` with the new cases:
   rename of an active row with empty history succeeds and does not change the
   status; rename is refused for each history location in (2); a non-canonical
   article is refused; a duplicate article is refused; a non-owner caller is
   refused.

**Not in scope for the API:** `assignNomenclatureSkuAction3dp_` does not
currently call `assertNomenclatureSkuMatchesType3dp_`, while
`createNomenclatureOwnerAction3dp_` does. That asymmetry is real; report it, do
not fix it here — tightening it could reject drafts that already exist.

### B. `dashboard/booster-dashboard.html` — draft queue and article control

Both additions belong in the **Вироби** zone (`threeDpProductWorkspace`,
rendered by `renderThreeDpProducts()` at ~line 1079), beside the existing
quick-create form.

1. **Draft queue.** List every `Номенклатура` row with status `Чернетка`,
   newest first, showing the `DRAFT-` key, name, type, and the values Serhiy
   supplied. Source it from the SKU payload with `include_archived=true` and
   filter on status; do not add a new API action for this.
2. **Article assignment form**, per queued draft:
   - the type dropdown drives the deterministic part. `sku_suggestion` comes back
     from `3dp_nomenclature_draft_create` and from
     `3dp_nomenclature_assign_sku`; the same mapping exists server-side as
     `NOMENCLATURE_DRAFT_SUGGESTIONS_3DP` (prefix + category digits + label);
   - the mnemonic segment is typed by the owner. It is **never** generated.
     `JIGGLYPUFF → JIGGL`, `POKEBALL → PKBL` are readability judgements, not
     transliteration. Canon: `plans/3D-P_sku-naming-convention_20260807.md`;
   - show the assembled article as a preview, validated against
     `^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$`, and require an explicit confirm;
   - when the product's mechanic matches no existing category, the canon requires
     stopping and asking the owner. Surface that as a visible state, not a
     silently picked nearest category.
3. **Article editing on an existing product**: an edit control on each product
   row that calls the same action. When the API returns `SKU_HISTORY_EXISTS`,
   show the returned message — the item has history and a rename is a migration,
   not an edit. Do not pre-guess history client-side; the API is the authority.
4. Reuse the existing `call3dpPost` helper, token handling and error surface. Do
   not introduce a second API client or a second token store.

## What NOT to touch

- `ROADMAP_TASKS` rows in `dashboard/booster-dashboard.html`. Claude writes Notion
  status and the mirror in the same pass, after owner QA. Two writers on this file
  in one round is the failure that already happened twice this month.
- `3d-print/serhiy-local-server/**` — WP2 is running against it in parallel.
- `crm/apps-script/**`. That project moved to V144 in the same window as this
  handoff and its mirror is **not** byte-verified against live, so it is neither a
  safe authority nor a safe target right now. If the 3D-P side appears to need a
  CRM change, stop and report.
- The `Продажі` frozen-header contract and the CRM sync filter.
- `.env`, `.env.review`, `scripts/.env`, `client_secret.json`.
- The `/exec` URL and both tokens: never in a file, a test fixture, a log line, a
  report or a commit message.

## Delivery format

- Apps Script: a complete `Code.gs` replacement. The seven PHP patch conventions
  in `AGENTS.md` do not apply — this is pasted into the script editor and
  published as a new Web App version by the owner.
- Dashboard: edit `dashboard/booster-dashboard.html` **in place** in the
  repository. It is the single canonical file (`AGENTS.md`, 2026-07-28).
  If you deliver a full-file drop instead, base it on the file as it stands at
  delivery time — `patches/3D-P-007-WP1c_owner-quick-create-dashboard_20260823.html`
  reverted live changes because it was based on an older copy, which is why
  `patches/3D-P-007-WP1c_crm-rrp-sync-dashboard_20260823.html` is the correct one
  of that pair. After editing, re-parse `ROADMAP_TASKS` and confirm the row count
  is unchanged.
- Report: `diagnostics/3D-P-007-WP2b_draft-queue-and-article-editing_report_20260823.md`.
- Do not commit, push, deploy, or write Notion.

## Acceptance criteria

- [ ] `node 3d-print/apps-script-3dp-api/tests/api.test.mjs` passes, including
      every new case.
- [ ] A `Чернетка` row still assigns exactly as it does today; the regression
      case is in the test file.
- [ ] An active row with no history accepts a new article and keeps status
      `Активний`.
- [ ] A rename is refused for every SKU-keyed location enumerated above, and the
      error names the blocking location.
- [ ] The `Наявність!A` question is answered from the live sheet and the answer
      is in the report.
- [ ] `Номенклатура!A` remains unreachable through generic `3dp_write`.
- [ ] The dashboard lists every `Чернетка` row and never shows a generated
      mnemonic.
- [ ] The dashboard task-row count is still 104 after the patch, and the report
      states the before/after line count and byte size.
- [ ] No secret in any delivered file.

## QA checklist (owner runs)

There is no staging for the 3D-P workbook. Publishing the new Web App version
puts this on the live sheet immediately.

- [ ] Create a named Google Sheets version before publishing.
- [ ] Publish the new Web App version, then `Ctrl+F5` the dashboard (that is the
      whole dashboard release — no deploy step).
- [ ] Have Serhiy — or the Serhiy token — create one throwaway draft. Confirm it
      appears in the queue with its `DRAFT-` key and no article.
- [ ] Assign an article to it: confirm the prefix and category are suggested, the
      mnemonic field is empty until typed, and the row becomes `Активний`.
- [ ] On a **fresh test SKU with no history**, change the article. Confirm the
      status stays `Активний`, the change appears in `API_історія_змін` with the
      actor, and `Аналітика` follows the new article with no orphan row.
- [ ] On a SKU that has a sale or a print-log entry, attempt a rename and confirm
      it is refused with the blocking location named.
- [ ] Under the Serhiy token, confirm article assignment is still refused.
- [ ] Export the new version as a labelled source file and hand it over, so the
      repository mirror and `SOURCE_STATE.md` can be brought to the published
      version.

## Risks

- **SKU is the join key.** This is the whole risk of the package. A rename that
  slips past the guard silently detaches sales, print history, stock and
  analytics from the product. When in doubt, refuse the rename.
- **Live workbook, live dashboard.** Use a designated test SKU. The dashboard has
  no rollback other than the repository copy.
- **Parallel writers on the dashboard file.** It gained rows from another session
  mid-work during the previous run, and was replaced wholesale twice. Re-parse
  and re-count after every edit.
- **A new status value is a polarity problem.** This package adds no status, but
  if a guard is touched it must ask "is this row active?", never "is this row
  archived?".
