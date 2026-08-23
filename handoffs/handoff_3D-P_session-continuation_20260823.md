# 3D-P session continuation — 2026-08-23

Read this first when resuming 3D-P work. It supersedes
`handoff_3D-P_session-continuation_20260809.md`, which is now history.

## Where the programme stands

`3D-P-007` (Serhiy local server) is the active thread. Its **API half is
finished**: WP1 rev 2, WP1b and WP1c are all deployed and owner-QA'd. What
remains is the user interface on both sides, then installation.

| WP | What | State |
|---|---|---|
| 1 | Server package + local tests | done (2026-08-02) |
| 2 | WP1 rev 2 — role read projections, `Налаштування` grant + journal | done, deployed 2026-08-16 |
| 3 | WP1b — Serhiy writes `Q`/`R`/`S`, stock corrections, payout acknowledgement | done, deployed 2026-08-22 (V25) |
| 4 | WP1c — `Чернетка` status, SKU validator, plus three same-day follow-ups | done, deployed and QA'd 2026-08-23 |
| 5 | **WP2** — Serhiy local-server re-spec | **next**, no handoff yet |
| 6 | **WP2b** — owner dashboard: draft queue + SKU field editing | no handoff yet |
| 7 | **WP3** — install on Serhiy's machine + joint QA | no handoff yet |

Roadmap tasks closed during this run: `3D-P-002`, `3D-P-019`, `3D-P-020`.

## The decision that governs everything downstream

The data boundary was **reversed mid-flight** on 2026-08-16, before anything was
published. Do not re-derive it from older documents — several still describe the
abandoned model.

**Everything inside the 3D line is open to Serhiy. Everything outside it is
closed.** 3D products run on an agreed 50/50 net-profit split, so Serhiy already
knows the owner's share, and he authors the RRP himself. Hiding the 3D margin
from him was incoherent and was dropped.

`SERHIY_FULL_ECONOMICS_VISIBLE_3DP = true`. The only remaining projection is
order and customer identity: `Продажі!N`, `Продажі!T`,
`Маркетингові_плюшки!G`/`!H`. The projection machinery is deliberately retained
even though little is projected, because it makes the boundary explicit and
auditable.

Full decision record, including everything Serhiy may see and write:
`plans/3D-P-007_serhiy-data-scope-decision-list_20260816.md`, revision section.

## What Serhiy can already do against the live API

- read the whole 3D line except order/customer identity;
- edit the four cost parameters in `Налаштування!B2:B5`, journalled;
- write `Номенклатура` `Q` (РРЦ фактична), `R` (Ціна під викуп), `S` (посилання
  на модель), validated and journalled;
- submit stock corrections — the field takes the **actual count**, not a delta
  (3D-P-025 semantics, regression-covered);
- acknowledge a payout twice: agrees with the amount, and money received. Each
  stores a Kyiv timestamp and role, append-once, with a separate correction
  action that preserves history;
- create a product as a `Чернетка` with a `DRAFT-` identifier and no article.

He cannot: create or close a payout period, assign an article, change a row's
status, write `Налаштування` outside `B2:B5`, or see order/customer identity.

## Next work package — WP2

Re-spec `3d-print/serhiy-local-server/` against the projected contract. Three
zones mirroring the owner dashboard: **Калькулятор** (with the ⚙ settings block),
**Вироби**, and an adapted **Інформація**.

The Інформація tab is where the adaptation work is. Of the owner's seven blocks:
sync-with-CRM does not go to Serhiy at all; "потребує уваги" goes with his own
signals (zero stock, defects, missing print time); analytics, all-products,
sales, payouts and the gifts journal go across but projected.

Do **not** rewrite the package. The 2026-08-07 audit confirmed
`lib/calculator.mjs` implements the locked cost formula correctly. What changes
is what the server *shows* and which contract it speaks.

WP2b then adds, on the owner side, a queue of Serhiy's drafts and the ability to
edit the article field on an existing product.

## Traps worth knowing before touching any of this

1. **SKU is a join key.** It links `Номенклатура`, `Продажі`, the CRM trigger and
   `Наявність`. Editing the article field is a plain write only while the item
   has no sales and no print-log history. After that a rename is a migration, not
   an edit.
2. **Article assignment is never automatic.** Prefix and category are
   deterministic and may be *suggested*; the mnemonic is a readability judgement
   (`JIGGLYPUFF → JIGGL`, `POKEBALL → PKBL`) and stays with the owner. The canon
   also requires stopping and asking when an item's mechanic matches no existing
   category. Canonical source: `plans/3D-P_sku-naming-convention_20260807.md`.
3. **A new status value is a polarity problem, not an addition.** WP1c's real
   work was rewriting every `=== archivedStatus` guard that actually meant "only
   active rows proceed". Any future status faces the same sweep.
4. **The dashboard has no deploy step** — it is the repository file opened over
   `file://`. `Ctrl+F5` is the whole release. It is PC-only; mobile belongs to
   NCRM after migration.
5. **Parallel writers on the dashboard are real.** During this run the file
   gained rows from another session mid-work, and Codex replaced it wholesale
   twice. Re-parse `ROADMAP_TASKS` after any edit and confirm the row count.
6. **Two dashboard patch files exist from 2026-08-23.** The correct one is
   `crm-rrp-sync-dashboard`; the earlier `owner-quick-create-dashboard` reverts
   changes.
7. **Apps Script "patches" here are full-file replacements**, pasted into the
   script editor and published as a new Web App version. The seven PHP patch
   conventions in `AGENTS.md` do not apply to them.

## Evidence provenance — read before planning against live source

- 3D-P: last independently compared export is **V23** (2026-08-13). V25 was
  published 2026-08-22 but **no export was supplied for it**.
- Main CRM: an export was supplied 2026-08-23 but it **carries no trustworthy
  version label**, so no deployed version is claimed.
- Both limitations are recorded in the two `SOURCE_STATE.md` files. Ask the owner
  for a fresh labelled export before any task that plans against live source.

## Open items carried forward

- `3D-P-015` cannot close until WP3 joint QA. The gate was **rewritten** on
  2026-08-16: the old wording required proving Serhiy *cannot* write `Q`/`R`/`S`,
  which is now by-design behaviour. Current wording: prove those writes are
  journalled with an author, and that payout period creation/closure,
  `Налаштування` outside `B2:B5`, and order/customer identity stay closed.
- `FIG-LUFFY-500` still carries the deliberate test value
  `Ціна під викуп = 999`. Owner decided 2026-08-23 to leave it for now. Serhiy
  can see it and it is the price the shop pays him — revisit before real Track-2
  trade on that SKU.
- `SEO-008` (semantic core) blocks scaling `3D-P-CARDCONTENT` beyond the current
  batches. Roughly 27 of ~40 canonical SKUs still have no card; none are
  published.
- `CHECKOUT-011` (preorder for made-to-order 3D items at zero stock) is High and
  directly adjacent to Serhiy's print-to-order flow. It sits in the `CHECKOUT`
  series, so an ID search on `3D-P` will miss it.
- Merchant feed inclusion for 3D SKUs is still an open owner decision, carried
  out of `3D-P-002`. No GTIN exists for these items and none may be invented.
- A photo of the finished **assembled** keychain is still required before the
  anchor product moves from Disabled to Enabled.
- Housekeeping: `AGENTS.md` has uncommitted modifications of unknown origin, and
  `pumb-settings.txt` is untracked and unreviewed — the name suggests payment
  settings, so check it before it lands in the repository.

## Routing for the next session

1. `AGENTS.md`, then `CLAUDE.md`.
2. This file.
3. `plans/3D-P-007_serhiy-data-scope-decision-list_20260816.md` — the decisions.
4. `context-index.md` for anything else by task ID.
5. Notion for canonical status; `ROADMAP_TASKS` in the dashboard is its mirror
   and is updated in the same pass as any Notion status write (owner rule,
   2026-08-16).
