# ROADMAP_SOP.md — Booster Roadmap Operating Procedure

This file is canonical for Booster task status, lifecycle, synchronization,
writer ownership, page-ID routing, and Definition of Done.

- `AGENTS.md` is canonical for general authority, safety, and implementation
  rules.
- `CODEX_WORKFLOW.md` defines the handoff-to-implementation protocol.
- `CLAUDE.md` defines Claude-specific context and review routing.

## 0. Core invariants

- Notion roadmap is the canonical task-status and priority source.
- Dashboard `ROADMAP_FLOW` is a mirror, not an independent status source.
- `context-index.md` contains task-to-evidence routing and never stores status.
- Claude is the default writer of Booster Notion task properties and status.
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
| 1. Create | Task row and initial scope | Claude | Codex when mirror creation is authorized | `Not started` / `todo` |
| 2. Start | Bounded handoff exists | Claude | Codex when mirror change is authorized | `In progress` / `active` |
| 3. Implement | Patch/source change + diagnostics | — | Codex only if required by scope | Acceptance checks |
| 4. Review | Handoff + diagnostic + bounded diff | — | — | Claude/owner verdict |
| 5. Deploy and QA | Owner runs patch and manual checks | — | — | Owner evidence |
| 6. Close | Owner authorizes closure after DoD | Claude sets `Done` | Codex mirrors `done` | Both converge |

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
- **Dashboard:** Codex edits the active `ROADMAP_FLOW`, then copies it to
  `dashboard/booster-dashboard.html`, only within authorized scope.

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
| CRM-001 | `3876bf20-bdb4-81dc-987d-d119fff4d2e9` | — |
| CRM-002 | `3876bf20-bdb4-8118-9fc7-d7e702832ec4` | — |
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

### MKT-TG series

| Roadmap ID | Notion page_id | Note |
|---|---|---|
| MKT-TG-003 | `38c6bf20-bdb4-8194-ac7b-fe967c7a0849` | — |
| MKT-TG-004 | `38c6bf20-bdb4-8145-b9e6-d1bebf8636ef` | Done; superseded by MKT-TG-005 |
| MKT-TG-005 | `3926bf20-bdb4-810f-958d-eb9b249bb45b` | — |

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

- **Claude:** handoffs, reviews, Notion task properties/status, and drift
  reporting. Never commit, push, or deploy.
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
- Active dashboard:
  `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html`
- Repository dashboard mirror:
  `dashboard/booster-dashboard.html`
- After an authorized dashboard edit, copy active dashboard → repository
  mirror before the scoped commit.
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
