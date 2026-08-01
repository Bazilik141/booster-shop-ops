# Codex Handoff — 3D-P-007: Serhiy local server (reads/writes 3D-P Sheet via the shared API)

Date: 2026-07-31 | Parent: 3D-P-000 · related: 3D-P-005, 3D-P-008
Codex config: model=Terra · effort=high

## Context

Interim architecture (locked 2026-07-28 in 3D-P-005, reconfirmed 2026-07-31): Serhiy's access to the 3D-P
workbook is **not** direct Sheet-editor access — it's a local server running on his own machine, talking to
the shared Apps Script API from `3D-P-008`. This mirrors `plans/3D-P_handoff-chatgpt_v1_20260728.md` §14.1
option 4 (Apps Script Web API with Google auth) applied narrowly: one script, two tokens, two callers.

This task no longer depends on `3D-P-006` (the original handoff serialized them; that dependency was
artificial — Serhiy's server only needs the `3D-P-008` API contract, not the owner's dashboard UI). Build in
parallel with `3D-P-006` once `3D-P-008` is Done.

**2026-08-02: Serhiy's server is the primary data-entry surface for the final cost model — read
`handoff_3D-P-008_apps-script-api-foundation_20260731.md`'s 2026-08-02 addendum first, and `3D-P-006`'s
matching "Calculator scope — FINAL" section for the exact formula.** This task must ship the same batch-entry
UX, not a simplified version: Serhiy picks a SKU from a dropdown, enters the quantity produced in a print
session plus session totals for weight/print time (the server divides by quantity before storing per-unit
values — never store a raw batch total in `Номенклатура`), plus вага котушки/ціна котушки. No plastic-type
field. The three global constants (170 Вт, 4.32 грн/кВт·год, 12 грн/год) are read-only display, sourced from
the shared settings block — Serhiy's server does not let him edit them (owner-only, per `3D-P-008`'s
addendum). His server is also where the post-production `Брак, шт` (defect count) edit happens, and where the
fixture dropdown (from `Фурнітура_довідник`) gets used to attach hardware after printing. If `3D-P-008`'s
2026-08-02 addendum is not yet Done, stop and confirm with the owner before building this task's calculator
UI — same gate as `3D-P-006`.

**Owner decision, 2026-07-31: Serhiy does NOT get direct editor access to the Google Sheet.** His only access
path is this local server hitting the API. Do not provision Sheet-level sharing for him as part of this or any
task.

## Scope (what to change)

- A small standalone server application (language/framework: Codex's choice — match whatever is lightest to
  run reliably on Serhiy's own machine, e.g. a minimal Node/Express or Python/Flask app; confirm with owner if
  genuinely ambiguous) that Serhiy runs locally. It should:
  - Hold its **own** copy of `BOOSTER_3DP_TOKEN` (owner provisions this separately — never the same token
    value used by the dashboard; if `3D-P-008` only supports one token today, that's a gap to flag back to
    `3D-P-008`, not something to work around here by reusing the dashboard's token).
  - Call `3D-P-008`'s read actions (`3dp_get_row`, `3dp_skus`, `3dp_sales`, etc.) to show Serhiy: his relevant
    SKUs, наявність, нараховано/виплати status, and open questions relevant to him (e.g. the Легенда's
    "Відомі відкриті питання" — read via `3dp_get_range` on the specific Легенда range, not the whole tab).
  - Allow Serhiy to **write** his side of the data via `3dp_write`/`3dp_append_row`: Друк-лог entries (what he
    printed, when, session quantity/time/weight, post-production `Брак, шт`), and his manual-input columns in
    Номенклатура (exact letters per `3D-P-008`'s 2026-08-02 addendum — coordinate with its final column
    layout, do not assume the pre-addendum G–L,N letters are still accurate) per the Легенда's existing
    "Хто що заповнює" convention. `3D-P-008` already whitelists these as blue/manual cells — the local server
    should not need its own separate whitelist logic, just call the API and surface its accept/reject
    responses.
  - Simple local UI (a single page is enough — this does not need to match the owner dashboard's visual
    design). Priority is function over polish for v1.

## What NOT to touch

- The `3D-P-008` Apps Script project — this is a pure API consumer, same rule as `3D-P-006`.
- `dashboard/booster-dashboard.html` — entirely unrelated to this task, do not touch it.
- Main CRM Apps Script/token.
- The Google Sheet directly — Serhiy's server never talks to Sheets/Drive APIs directly, only to the
  `3D-P-008` webapp.

## Acceptance criteria

- [ ] Server runs locally on a plain machine with reasonable setup steps documented (a short README is enough,
      not a full ops guide).
- [ ] Uses its own `BOOSTER_3DP_TOKEN`-equivalent credential, distinct from the dashboard's.
- [ ] Read views show real data for at least one SKU end-to-end (via `3D-P-008`, not a direct Sheets call).
- [ ] A test write (e.g. a Друк-лог entry) round-trips: submitted via the local server, confirmed present via
      a `3dp_get_row`/`3dp_get_range` check afterward.
- [ ] Server never receives or stores the master `BOOSTER_CRM_TOKEN` or the dashboard's `BOOSTER_3DP_TOKEN`
      copy — its own credential only.

## QA checklist (owner runs after deploy, ideally with Serhiy present)

- [ ] Serhiy installs/runs the server on his own machine following the README.
- [ ] Serhiy submits one real Друк-лог entry, owner confirms it shows up in the live Sheet.
- [ ] Confirm Serhiy cannot see anything beyond his relevant 3D-P data (no other Booster Shop CRM data is
      reachable from this server — it only ever talks to the `3D-P-008` webapp, which itself only exposes the
      3D-P Sheet).

## Rollback note

- This is a standalone local application — "rollback" means Serhiy stops running it. Zero effect on the
  dashboard, the main CRM, or the Sheet's structure (writes go through the already-guarded `3D-P-008` API).
- If a bad write does land (e.g. wrong value in a manual cell), it's recoverable the same way as any
  `3D-P-008` write — check `_Аудит_API` for the prior value.

## Risks

- New credential in a new environment (Serhiy's own machine, outside Booster Shop's infrastructure) — the
  token must never be logged, committed, or displayed in a way Serhiy could accidentally share (e.g. a
  screenshot). Document this plainly in the README Codex writes for him.
- Do not let this task's UI scope creep toward matching the owner dashboard's design — that's wasted effort
  for a single-user local tool.

## Recommended status after execution

`In progress` until owner + Serhiy jointly confirm the QA checklist → then `Done`.
