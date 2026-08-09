# ROADMAP_SOP.md — Booster Roadmap Operating Procedure

This file is canonical for Booster task status, lifecycle, synchronization,
writer ownership, page-ID routing, and Definition of Done.

- `AGENTS.md` is canonical for general authority, safety, and implementation
  rules.
- `CODEX_WORKFLOW.md` defines the handoff-to-implementation protocol.
- `CLAUDE.md` defines Claude-specific context and review routing.

## 0. Core invariants

> **2026-07-27 amendment (owner-authorized, permanent):** the owner reassigned
> the `ROADMAP_FLOW`/dashboard-mirror writer role from Codex-only to Claude,
> in the same session that created NCRM-18/NCRM-19. This supersedes the
> Codex-only dashboard-writer wording below and in §3/§4/§7 wherever it
> conflicts. Codex retains its existing authority to change `ROADMAP_FLOW`
> when required by its own authorized implementation work (e.g. a migration
> that changes task scope) — this amendment adds Claude as a second authorized
> writer for routine status/property mirroring, it does not remove Codex's.
> Both agents must still avoid writing the same field in the same task at the
> same time; check the dashboard's own diff before editing.
>
> **Known physical limit (2026-07-27, superseded 2026-07-28 — see next
> amendment):** Claude's mounted access was
> `booster-shop-ops/dashboard/booster-dashboard.html` (repository mirror)
> only. The standalone "active dashboard" at
> `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html` (outside the
> mounted folder) was not reachable by Claude, so the owner had to copy
> repo mirror → active dashboard after every Claude-authored edit.
>
> **2026-07-28 amendment (owner-authorized, permanent): single canonical
> dashboard file.** The owner retired the standalone "active dashboard" copy.
> `dashboard/booster-dashboard.html` inside this repository is now the only
> dashboard file — the owner opens it directly (e.g. via its `file://` path)
> instead of a separate outside-repo copy. This supersedes the 2026-07-27
> physical-limit note above, the copy-back requirement it describes, and
> §4/§8 wherever they still describe two files. Claude and Codex both edit
> this one file directly; there is no copy step between two dashboard files
> anymore.

- Notion roadmap is the canonical task-status and priority source.
- Dashboard `ROADMAP_FLOW` is a mirror, not an independent status source.
- `context-index.md` contains task-to-evidence routing and never stores status.
- Claude is the default writer of Booster Notion task properties and status,
  and, per the 2026-07-27 amendment above, of the `ROADMAP_FLOW` dashboard
  mirror.
- Codex owns required `ROADMAP_FLOW` changes in authorized roadmap-affecting
  work.
- Agents do not act as parallel writers. A writer exception requires exact
  owner reassignment.
- The owner controls production deployment, final manual QA, and the decision
  that a risky or legal task is ready to close.
- Claude never commits or pushes. Codex may do so only after direct, explicit,
  one-time owner authorization for the exact active-task scope.
- `scripts/auto_review.py` is the canonical `bsreview` implementation. It never
  changes Notion properties or status.

## 1. Sources of truth

| Information | Canonical source | Purpose |
|---|---|---|
| Task status, priority, and task owner | Notion roadmap | Canonical project state |
| At-a-glance status view | `booster-dashboard.html` → `ROADMAP_FLOW` | Mirror of Notion |
| Scope, logic, and task evidence | `handoffs/` + `diagnostics/` | Implementation context |
| Task ID → handoff/diagnostic/page ID | `context-index.md` | Routing index without status |
| Code, diffs, and patch history | Repository | Implementation history |

Do not store task status in `context-index.md`, filenames, or handoff headers.

Notion roadmap:
`https://www.notion.so/35c3f8572fc54a7896c8af0efd4cf8d4`

Database: `35c3f857-2fc5-4a78-96c8-af0efd4cf8d4`

View: `?v=eebb19b11cfb4066a8a3b1b097775818`

## 2. Status vocabulary

| Notion `Status` | Dashboard `status` | Meaning |
|---|---|---|
| `Not started` | `todo` | Work has not started |
| `In progress` | `active` | Active work, including a ready handoff waiting for Codex |
| `Done` | `done` | Required implementation, deployment, and QA evidence are complete |

For watch-only monitoring after implementation, use `Done` and add a
`watch-only` note in `Owner Decision` or `Stage`. Do not leave completed work in
`In progress` only because an external system such as Google still needs time.

## 3. Task lifecycle and writers

| Stage | Evidence/action | Notion writer | Dashboard writer | Gate |
|---|---|---|---|---|
| 1. Create | Task row and initial scope | Claude | Claude (2026-07-27 amendment), or Codex when mirror creation is authorized | `Not started` / `todo` |
| 2. Start | Bounded handoff exists | Claude | Claude (2026-07-27 amendment), or Codex when mirror change is authorized | `In progress` / `active` |
| 3. Implement | Patch/source change + diagnostics | — | Codex only if required by scope | Acceptance checks |
| 4. Review | Handoff + diagnostic + bounded diff | — | — | Claude/owner verdict |
| 5. Deploy and QA | Owner runs patch and manual checks | — | — | Owner evidence |
| 6. Close | Owner authorizes closure after DoD | Claude sets `Done` | Claude mirrors `done` (2026-07-27 amendment), or Codex | Both converge |

Notion and dashboard must converge within the same bounded workflow, but one
agent does not write both systems by default. If a required writer is
unavailable, stop and hand off. Do not silently create a competing writer.

## 4. Status read and synchronization procedure

### Read canonical status

1. Prefer a known `page_id` and direct Notion fetch.
2. If no page ID is known, search by task title or distinctive keywords.
3. Fetch the result and verify its `Roadmap ID`.
4. Use `ROADMAP_FLOW` only as the local mirror and drift signal.

Ranked semantic/content search does not guarantee exact-ID recall. Exact-ID
queries are not categorically forbidden; they simply require result
verification.

### Write status

- **Notion:** Claude updates properties/status through the available Notion
  page-update tool.
- **Dashboard:** `dashboard/booster-dashboard.html` is the single canonical
  dashboard file (2026-07-28 amendment, §0) — there is no separate active
  copy anymore. Claude may edit it directly to keep it aligned with Notion.
  Codex edits the same file within its own authorized implementation scope.
  Do not have both agents edit the same task's dashboard entry in the same
  window without checking the other's latest diff first.

If verified implementation or owner QA is newer than Notion, Claude first
updates canonical Notion state and Codex then aligns the dashboard mirror. If
the dashboard disagrees without newer evidence, align it to Notion.

### Drift review

At the start of a roadmap-maintenance task:

1. inspect active/todo dashboard entries;
2. fetch their Notion cards using known page IDs when available;
3. report mismatches;
4. correct only the system assigned to the active writer and authorized scope.

Spot-check completed tasks only when evidence suggests drift.

## 4a. Review automation — `bsreview` (AUTO-002)

`bs-review.ps1` invokes `scripts/auto_review.py`.

- `bsreview [TASK-ID]` finds a diagnostic and handoff, reads the Git diff, calls
  Claude, saves a review diagnostic, and may post one Notion comment when
  `NOTION_TOKEN` is available.
- `bsreview` without an ID uses the newest diagnostic.
- `bsreview --dry-run` may call Claude and print the review but does not save a
  diagnostic or read/write Notion.
- Normal mode may query the roadmap by exact `Roadmap ID` through the Notion
  REST API and post one comment.
- No mode may update a Notion property or status.
- The repository-root `auto_review.py` is legacy and must not be invoked.
- `.env.review` may contain `ANTHROPIC_API_KEY` and `NOTION_TOKEN`; never commit
  or expose it.

Use manual Claude review in addition to automation for risky or complex
changes.

## 5. Notion page-ID registry

### ST series

| Roadmap ID | Notion page_id |
|---|---|
| ST-3.5 | `3896bf20-bdb4-8174-8a50-fe3d19f8c9ba` |
| ST-3.6 | `38a6bf20-bdb4-8184-917c-ef3f6c6ca1b1` |
| ST-3.7 | `38a6bf20-bdb4-8153-8538-db353b2f6a34` |
| ST-2c | `3896bf20-bdb4-8119-a13b-c1dc1e078328` |
| ST-6 | `3896bf20-bdb4-81b0-8c67-cb4300ccba9f` |
| ST-1 | `3896bf20-bdb4-819d-bb33-f1ddcc2dd0de` |
| ST-2b.5 | `3896bf20-bdb4-81af-9a30-f9f1c909338c` |
| ST-2b.1–2b.4 | `3896bf20-bdb4-815f-8762-ec1e16c6e146` |
| ST-2b.6 | `3926bf20-bdb4-81a0-992c-dc6840dc1baf` |

### Frequently used non-ST tasks

| Roadmap ID | Notion page_id | Note |
|---|---|---|
| PAY-001 | `3a16bf20-bdb4-819b-99a7-f8535b0c74d6` | Added 2026-07-18: Monobank "Покупка Частинами" |
| PAY-001-UI | `3a26bf20-bdb4-811f-baf2-ed050b4c78e7` | Added 2026-07-18: design brief |
| PAY-002 | `3aa6bf20-bdb4-812a-b541-ef4d483f3657` | Added 2026-07-27: PUMB «Сплачуйте частинами». The card existed only in the dashboard mirror until this date |
| PAY-003 | `3aa6bf20-bdb4-8187-baa7-df479a24d475` | Added 2026-07-27: shared credit-confirmation intermediate page (mono + PUMB), blockedBy PAY-002 |
| PAY-001-SMOKE | `3aa6bf20-bdb4-8122-86a9-c201c8700185` | Added 2026-07-27: unified final QA gate for mono + PUMB, created immediately after PAY-001 closed |
| CRM-001 | `3876bf20-bdb4-81dc-987d-d119fff4d2e9` | — |
| CRM-002 | `3876bf20-bdb4-8118-9fc7-d7e702832ec4` | — |
| CRM-003 | `3ac6bf20-bdb4-81cb-a1f3-fb09f399c1e7` | Added 2026-07-29: BOOSTER_CRM_TOKEN rotation (hardcoded exposure found in dashboard.html + docs/index.html) |
| OPS-CODEMIRROR | `3b66bf20-bdb4-81f8-8ccb-c58955892365` | Added and completed 2026-08-08: both Apps Script projects mirrored in the repository. `crm/apps-script/Code.gs` + `crm/apps-script/SOURCE_STATE.md` (pull 2026-08-08 11:41, owner-reported V89); `3d-print/apps-script-3dp-api/Code.gs` verified byte-identical to live. Rule in `AGENTS.md` → "Apps Script mirrors" |
| CRM-004 | `3b56bf20-bdb4-812c-99a8-ceb7d3ee89fd` | Added 2026-08-07 from Finding 10 of `diagnostics/3D-P_live-schema-audit_20260803.md`: main-CRM data-validation defects (Паковання dropdown source range overlaps product data; new SKUs trip Недійсне значення). Configuration, not script. Must not be folded into 3D-P tasks |
| TECH-005-DEEP | `3666bf20-bdb4-8175-a429-e48eb7d6ef2d` | — |
| TECH-012 | `3666bf20-bdb4-812e-8975-df8827efdb16` | — |
| TECH-013 | `3a06bf20-bdb4-810c-b914-e518ca5f7188` | — |
| TECH-029 | `3786bf20-bdb4-8116-8f66-c856e04a11df` | — |
| TECH-035 | `3936bf20-bdb4-81d4-a0ee-e21b32119066` | — |
| TECH-042 | `3a06bf20-bdb4-812b-8cd7-dd45932ff09d` | — |
| RD-11 | `3706bf20-bdb4-81a4-b3fa-f35e7610defa` | — |
| CAT-002 | `36f6bf20-bdb4-817e-99ec-eecce853778c` | — |
| CHECKOUT-001 | `3776bf20-bdb4-8130-bcbf-cbb6259d5654` | — |
| CHECKOUT-002 | `3946bf20-bdb4-81bf-9f47-cda9044fd2f2` | — |
| CHECKOUT-004 | `3a16bf20-bdb4-8119-902c-e42e2b56a8bb` | Added 2026-07-18; also covers CHECKOUT-005/006/007/007A |
| CHECKOUT-008 | `3ac6bf20-bdb4-8135-beb9-c7c7cbba4630` | Added 2026-07-29: IBAN requisites in order-confirmation email + checkout success "copy requisites" button, IBAN-only |
| CHECKOUT-009 | `3ac6bf20-bdb4-81ab-b82c-e949bc08c990` | Added 2026-07-29, closed Done 2026-07-29: P0 — checkout did not register the selected delivery; Stage 1 deployed and confirmed by the owner |
| CHECKOUT-010 | `3ac6bf20-bdb4-812b-9403-e41f6b86d77e` | Added 2026-07-29: CHECKOUT-009 Stage 2 checkout-state consolidation + deferred follow-ups; not started, needs owner authorization |
| LEGAL-002 | `3666bf20-bdb4-81ea-8fed-ff4773081cdb` | — |
| R-13.5 | `36c6bf20-bdb4-814c-becb-c451a64b22f8` | — |

### NCRM series

NCRM-04 through NCRM-12 were renumbered/rescoped on 2026-07-11 under
`plans/NCRM-financial-model-v2_technical-contract_20260711.md`.

| Roadmap ID | Notion page_id | Note |
|---|---|---|
| NCRM-00 | `38b6bf20-bdb4-81dc-89ba-ddf3ae182f37` | — |
| NCRM-01 | `38b6bf20-bdb4-8165-b4bb-f9434ee07770` | — |
| NCRM-02 | `38b6bf20-bdb4-8115-b0b3-c8c1e31be4f1` | — |
| NCRM-03 | `38b6bf20-bdb4-8140-ad7e-e6db16fa8984` | — |
| NCRM-04 | `38b6bf20-bdb4-8173-8803-d6fb691df55b` | Inventory ledger foundation; formerly Read screens |
| NCRM-05 | `38b6bf20-bdb4-81de-b682-d0b31c7c4a95` | Mystery fulfillment; formerly Write forms + FIFO COGS |
| NCRM-06 | `38b6bf20-bdb4-81bf-858f-da5fc957be92` | Returns and cost quality; formerly Expenses + P&L + KPI |
| NCRM-07 | `38b6bf20-bdb4-81f4-9cce-c56933b6bdbe` | Reporting, forecast, and KPI; formerly OpenCart pipeline |
| NCRM-07b | `39f6bf20-bdb4-8185-adc2-cf8c29f6e359` | RLS and multi-user role foundation |
| NCRM-08 | `39a6bf20-bdb4-815a-87d9-cd4348f16ddb` | Read screens; former NCRM-04 scope |
| NCRM-09 | `39a6bf20-bdb4-81da-81fc-c3bb866981b4` | Write forms + FIFO COGS; former NCRM-05 scope |
| NCRM-10 | `39a6bf20-bdb4-813c-a5ff-db69193a67e0` | OpenCart pipeline; former NCRM-07 scope |
| NCRM-11 | `38b6bf20-bdb4-8127-b520-ee5775186f78` | Renumbered from NCRM-08; currency rates |
| NCRM-12 | `38b6bf20-bdb4-8126-a49e-d4819f0bc496` | Renumbered from NCRM-09; mobile |
| NCRM-13 | `39f6bf20-bdb4-8170-a4b3-d0c81978b4bf` | Signed inventory adjustment model |
| NCRM-14 | `3a96bf20-bdb4-812c-95e5-f2dd31cf0ffe` | PUMB order-sync mapping and `discount_total` |
| NCRM-15 | `3a96bf20-bdb4-8171-b60b-f6e7c1941324` | Mobile scope split from NCRM-12 |
| NCRM-16 | `3a96bf20-bdb4-81db-9552-ce80397183cb` | Monobazar postpay 2.9% and owner FOP profile |
| NCRM-17 | `3a96bf20-bdb4-81f5-9892-e76ecb5ba061` | Deploy local-only Next.js application |
| NCRM-18 | `3aa6bf20-bdb4-819f-a83a-e4d2018362a1` | Added 2026-07-27: migrate roadmap tracking from Notion into NCRM; deferred to end of current backlog (blocked by NCRM-11–17), not yet scoped |
| NCRM-19 | `3aa6bf20-bdb4-81a1-a1b9-e6863e5fd021` | Added 2026-07-27: Notion↔dashboard sync automation, nearer-term stand-in for NCRM-18; not yet scoped |
| NCRM-20 | `3ac6bf20-bdb4-8120-a055-f0fa0683c53c` | Added 2026-07-29: backfill orders lost during the Mono sync bug (see NCRM-10) from Apps Script/Sheets into Supabase `sales`; owner-approved, not yet scoped/diagnosed |

### MKT-TG series

| Roadmap ID | Notion page_id | Note |
|---|---|---|
| MKT-TG-003 | `38c6bf20-bdb4-8194-ac7b-fe967c7a0849` | — |
| MKT-TG-004 | `38c6bf20-bdb4-8145-b9e6-d1bebf8636ef` | Done; superseded by MKT-TG-005 |
| MKT-TG-005 | `3926bf20-bdb4-810f-958d-eb9b249bb45b` | — |

### 3D-P series

> New program added 2026-07-27/28: a friend 3D-prints TCG-themed figurines
> and accessories; two commercial tracks (site-sale revenue share vs.
> marketing-freebie purchase). Scoping plan:
> `plans/3D-P-000_scoping-and-architecture_20260728.md`. Not yet a Codex
> handoff — discovery/planning stage only, no production writes made.

| Roadmap ID | Notion page_id | Note |
|---|---|---|
| 3D-P-000 | `3ab6bf20-bdb4-8135-a58d-fe58e1ceeb27` | Discovery & scoping |
| 3D-P-001 | `3ab6bf20-bdb4-815d-b16e-cdbc5c9350bf` | Nomenclature & cost/RRP tracking workbook (v1 delivered) |
| 3D-P-002 | `3ab6bf20-bdb4-81ad-8432-febbb72cea29` | Catalog placement — Pokémon «Фігурки» subcategory, SEO risk-gated |
| 3D-P-003 | `3ab6bf20-bdb4-814f-8812-c1f540a5f996` | Pricing & sizing market research |
| 3D-P-004 | `3ab6bf20-bdb4-81c9-aa79-f29053013425` | Marketing-freebie sourcing flow |
| 3D-P-005 | `3ab6bf20-bdb4-8140-b62e-e1fd4c8622f0` | Future NCRM module, narrow Friend access (deferred) |
| 3D-P-006 | `3ae6bf20-bdb4-819e-8001-d6927f132f81` | Owner dashboard tab (3D-друк section) — first version shipped 2026-08-02, restructure follow-up is 3D-P-013 |
| 3D-P-007 | `3ae6bf20-bdb4-81f7-9c7b-fefcfe12ffba` | Serhiy local server, consumer of 3D-P-008's API |
| 3D-P-008 | `3af6bf20-bdb4-8189-a3a9-e88a374d01b1` | 3D-P Apps Script API foundation (read+write) + reconciliation; 2026-08-02 schema-correction addendum prepared, not yet deployed |
| 3D-P-010 | `3af6bf20-bdb4-8110-8e88-fdee44316a0d` | Auto-pull packaging + fixture cost from main CRM into 3D-P sheet; Phase 0 investigation blocked on live CRM evidence as of 2026-08-02 |
| 3D-P-011 | `3af6bf20-bdb4-8119-8158-dccb93c0e5b0` | Added 2026-08-06 (date corrected — originally logged as 08-01 in error): PDP characteristic (e.g. size) selector + product-page UI for multi-variant 3D-print items (Onyx 21cm/15cm trigger). Scope confirmed owner-only within 3D-P, not a general catalog feature. Discovery stage, no priority set, no Codex handoff yet. NOTE (pre-existing in this file when checked 2026-08-06, provenance not independently verified): text referencing a 2026-08-02 ID collision with a dashboard-restructure task (now 3D-P-013) was already present here — see also duplicate 3D-P-011/012 rows in context-index.md flagged to owner for reconciliation |
| 3D-P-012 | `3af6bf20-bdb4-819b-bcbd-ff058993dc21` | Added 2026-08-06 (date corrected — originally logged as 08-01 in error): short product videos (~5/10/15s) alongside photos on 3D-print product pages. Owner confirmed independent of 3D-P-011 (no blockedBy). Discovery stage, no priority set. Feasibility pre-check: OC4 core has no native video field; marketplace extensions exist for 4.x; compatibility with custom boostershop-ds theme not yet verified against live backup |
| 3D-P-013 | `3b06bf20-bdb4-81cc-a456-c24c6c557448` | Added 2026-08-02: «3D-друк» dashboard tab restructure (Калькулятор/Вироби/Інформація zones), follow-up to 3D-P-006. Originally misnumbered 3D-P-011, renumbered same day after the collision above was found |
| 3D-P-014 | `3b16bf20-bdb4-81cc-aa44-da48d4df15ac` | Added 2026-08-03: make CRM→3D-P sync failures visible (durable `_Журнал_синхронізації` tab, `3dp_sync_journal` read action, dashboard panel). Owner sequenced this BEFORE further architecture work. Handoff: `handoffs/handoff_3D-P-014_sync-failure-visibility_20260803.md` |
| 3D-P-015 | `3b16bf20-bdb4-8146-9f06-faaf2b54f67d` | Added 2026-08-03: rebuild the price model around a single фактична РРЦ + ціна під викуп; removes the three Аналітика price scenarios and everything derived from them; freezes cost/RRP into sale rows. Supersedes 3D-P-008 Addendum #3. Blocked by 3D-P-014. Handoff: `handoffs/handoff_3D-P-015_price-model-rebuild_20260803.md` |
| 3D-P-016 | `3b56bf20-bdb4-8173-92ad-ca8a9a91d8e8` | Added 2026-08-07 from the gap audit (gap G5): break-even minimum price + discount control. Agreed in V1 §5.4-5.5, never built. Blocked by 3D-P-015 |
| 3D-P-017 | `3b56bf20-bdb4-81e4-863e-f7cddaeb752e` | Added 2026-08-07 from the gap audit (gap G6): returns as a separate financial operation. Agreed in V1 §5.6, never built. Owner rule locked 2026-08-07 |
| 3D-P-018 | `3b56bf20-bdb4-8103-99ff-cea734c92408` | Added 2026-08-07 from the gap audit (gap G9): Виробництво/Друк-лог zone in the owner dashboard. The API actions already exist and are unused |
| 3D-P-019 | `3b56bf20-bdb4-81f6-8f8a-e4c5842ede7e` | Added 2026-08-07 on a new owner requirement: record who paid for each fixture (owner or Serhiy). Affects both tracks. Design note 2026-08-08: `plans/3D-P-019_fixture-payer-model_20260808.md` — found that `Номенклатура!K` already folds the fixture price into Serhiy's production cost with no payer, so the schema half should ship inside 3D-P-015 rather than as a second migration |
| 3D-P-020 | `3b56bf20-bdb4-8182-8982-e795fde4e9dd` | Added 2026-08-07: Track-2 cost must post to the main CRM Marketing expense line. Closes the 3D-P-004 ledger question |
| 3D-P-021 | `3b56bf20-bdb4-8125-8e81-eb68f946b69a` | Added 2026-08-07: delete ПРИКЛАД-001 demo rows across 6 tabs and zero the FIG-CHARM-001 test stock, after a named Sheets version. Run before 3D-P-015 |

| 3D-P-022 | `3b66bf20-bdb4-81b0-ad36-d2e9fb81cb52` | Added 2026-08-08 during 3D-P-014 owner QA: the deployed CRM trigger `is3dpPackagingSku_` expects `ACC-3D-` + three digits, but the canonical convention locked 2026-08-07 is `PREFIX-MNEMONIC-XYZ`. Every `ACC-3D-` SKU in the approved table fails the trigger; `BR-`/`FIG-` are unaffected |

| 3D-P-023 | `3b66bf20-bdb4-81da-8569-f1d54a8d94b1` | Added 2026-08-08 during 3D-P-014 QA: the sync-journal timestamp column is labelled Kyiv but renders UTC, because the written string is auto-parsed by Sheets into a Date. Cosmetic, low priority |
| 3D-P-025 | `3b76bf20-bdb4-81a6-838b-d6a27eff68bc` | Added 2026-08-09 during dashboard QA: the stock-correction field asks for a delta while the owner supplies the actual count, so `99` became `196`. Append-only ledger unchanged; only the input semantics move. Handoff: `handoffs/handoff_3D-P-025_stock-field-actual-count_20260809.md` |
| CRM-005 | `3b76bf20-bdb4-8140-8397-f14d1cc785dd` | Added 2026-08-09 after a repeat CRM breakage on SKU creation: a server-side, read-only integrity check returning a bounded problem list, plus rule `OPS-CRMINTEGRITY` and a new-SKU runbook. Owner constraint: the check must not stream sheet contents to an agent. Handoff: `handoffs/handoff_CRM-005_integrity-check-and-rule_20260809.md` |
| 3D-P-024 | `3b66bf20-bdb4-8132-a8d5-f3078cf95abb` | Added 2026-08-08 during 3D-P-015 live QA: print time is stored as decimal hours everywhere and nothing said so, so `1:39` and `1,39` silently produced wrong costs. Storage unit unchanged; normalisation moved to every entry point. Deployed and live-verified the same day. Handoff: `handoffs/handoff_3D-P-024_print-time-entry-usability_20260808.md` |

**Closed 2026-08-08 after owner QA:** `3D-P-014` (CRM-local sync journal — four
live cases passed; outage and not-configured cases skipped by owner choice and
covered by mock tests only), `3D-P-022` (SKU trigger aligned with the canonical
convention — `ACC-3D-DITTO-410` verified end to end), `3D-P-021` (demo-data
cleanup — independently confirmed, zero `ПРИКЛАД-001` rows remain in the live
workbook). **The CRM→3D-P sync worked for the first time on 2026-08-08** after
three failed attempts.

**Note 2026-08-07 (supersedes the 2026-08-03 note):** `3D-P-009` was never
issued and has no Notion page. It is referenced only in
`handoffs/handoff_3D-P-010_crm-packaging-cost-pull_20260802.md`. Decision: the
number stays permanently unused — do not recycle it. Numbering continued at
`3D-P-016`; **the next free ID is `3D-P-026`** (`3D-P-022`, `3D-P-023` and
`3D-P-024` were taken on 2026-08-08, `3D-P-025` on 2026-08-09). In the `CRM-`
series the next free ID is **`CRM-006`** (`CRM-005` taken on 2026-08-09).

**Owner decisions locked 2026-08-07** (full context in
`diagnostics/3D-P_gap-register-and-work-plan_20260807.md`):

1. `3D-P-015` new business columns are appended **after** the technical `O`/`P`
   block, becoming `Q`, `R`, `S`. No shift, no migration, no whitelist change.
2. `3D-P-017` returns: open period reduces the current accrual, an already paid
   period gets a negative correction next period; the sale row is never deleted.
3. `3D-P-020` Track-2 cost posts to the **main CRM** Marketing expense line.
4. `3D-P-019` fixture payer must be recorded per fixture, both tracks.
5. `3D-P-021` full cleanup, named Sheets version first, stock corrections only
   through the Addendum #2 ledger.
6. Build order: workbook + CRM backend → owner dashboard QA → Serhiy's server.

If a task is absent, search by title or distinctive keywords, verify its
`Roadmap ID`, then add the page ID here. Older completed ST tasks
(`ST-0`, `ST-2`, and `ST-2a*`) may be backfilled when needed.

## 6. Definition of Done

- **Default:** approved scope implemented, bounded diff reviewed, required
  checks pass, Notion status is correct, and `ROADMAP_FLOW` mirrors it.
- **Checkout, payment, Hutko, Checkbox, Nova Poshta, or order flow:**
  `bs-checkout-smoke` passes and the owner completes payment/fiscal/CRM manual
  QA before `Done`.
- **SEO, sitemap, robots, or canonical:** server work is complete and GSC
  evidence is recorded where applicable. If Google processing remains, use
  `Done` plus a watch-only note.
- **CRM, dashboard, or Apps Script:** the new version is deployed and read back
  through the Apps Script API (`action=summary`/`orders`) or a narrow Sheets
  range.
- **Content or legal:** prepared text is not `Done` until owner publication;
  legal work also requires legal review and verified real business details.

Local checks, source-copy checks, and dry-runs are not deployment or production
proof.

## 7. Writer and authority summary

- **Claude:** handoffs, reviews, Notion task properties/status, drift
  reporting, and (2026-07-27 amendment, §0) the `ROADMAP_FLOW` dashboard
  mirror at `dashboard/booster-dashboard.html`. Never commit, push, or
  deploy.
- **Codex:** implementation, diagnostics, and authorized `ROADMAP_FLOW`
  changes. Commit/push only with exact one-time owner authority. Never deploy.
- **Owner:** scope decisions, deployment, risky/legal closure decision, and
  final manual QA. Normally runs prepared Git commands.

If a required writer is unavailable, hand off. Do not use `bsreview` or another
agent as a status-writer workaround.

## 8. Constraints and safeguards

- Notion MCP bulk-read capabilities may be unavailable because of plan limits.
  Use per-card direct fetch or verified search results.
- Direct Notion REST review automation may query the database by exact
  `Roadmap ID` when `NOTION_TOKEN` is configured.
- Use `.autosync-pause` around authorized Git writes. The hardened autosync
  script skips pulls on a dirty tree and can recover stale index state, but
  that safety net does not replace the sentinel.
- Dashboard (single canonical file, 2026-07-28 amendment, §0):
  `dashboard/booster-dashboard.html` inside this repository. The former
  standalone copy at `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html`
  is retired — do not recreate it or copy into it.
- Canonical local Booster parent:
  `C:\Users\14bez\Downloads\Booster Shop`
- `E:\Personal Files\...` and `E:\Program Files\...` are retired.

## 9. Document precedence

For roadmap status, synchronization, writer ownership, page-ID routing, and
Definition of Done, this file is canonical.

For general authority, project routing, risky zones, patch conventions, and
mutation safety, `AGENTS.md` is canonical.

For Codex handoff, patch, diagnostic, and owner-deployment mechanics,
`CODEX_WORKFLOW.md` is canonical.

For Claude-specific context and review behavior, `CLAUDE.md` is canonical.

When two documents appear to conflict, apply the rule from the file that owns
that subject above. Current explicit owner instructions still take priority.
