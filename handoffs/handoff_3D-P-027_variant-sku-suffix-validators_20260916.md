# Patch Handoff — 3D-P-027: accept variant suffixes in the SKU create-form validators

Date: 2026-09-16 · Notion: `3dd6bf20-bdb4-81a8-903b-d89f42510c20` · follow-up to `3D-P-022` (Done 2026-08-08) · related: `CAT-004`
**Executor: Codex · model=Terra · effort=medium-high** — two single-expression regex edits plus tests,
fully specified below, against files already identified. `Sol/xhigh` is not warranted. Not a small
model either: this is the gate that decides which SKUs can ever enter the catalogue, and a regex that
is too loose silently admits malformed articles. **Owner decides.**

> Not a reopening of `3D-P-022`. That task shipped and its strictness was deliberate. This task changes
> the grammar it enforces, because the canonical convention gained variant suffixes on 2026-09-15.

## 1. Task ID

`3D-P-027`

## 2. Context

`plans/3D-P_sku-naming-convention_20260807.md` ред. 9 (2026-09-15, owner-approved) introduced variant
suffixes: a base article plus one short token per varying characteristic, in a fixed declared order.

```
{BASE}-{TOKEN}[-{TOKEN}…]        e.g.  ACC-3D-ONIX-110-21-BLK
```

The base keeps its existing shape `{PREFIX}-{MNEMONIC}-{NNN}`. Sizes are plain centimetre numbers;
colours are three letters from the closed list in ред. 9 §5 (`BLK WHT GRY RED BLU GRN YEL ORG GLD SLV`).

Read from source on 2026-09-15/16, not inferred:

| Surface | Pattern | Accepts a suffix? | File |
|---|---|---|---|
| Main CRM sync predicate | `/^(?:BR\|FIG\|ACC-3D)-[A-Z0-9][A-Z0-9-]*$/` | **yes** | `crm/apps-script/Code.gs` |
| Main CRM generic guards (×2) | `/^[A-Z0-9][A-Z0-9-]{2,63}$/` | **yes** | same file |
| Dashboard create-form validator | `/^(BR\|FIG\|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$/` | **no** | `dashboard/booster-dashboard.html:1330` |
| 3D-P Apps Script, same validator | identical regex | **no** | `3d-print/apps-script-3dp-api/Code.gs:204` |

The main CRM mirror was proven current for this reading: `crm/apps-script/Code.gs` is byte-identical to
the owner's labelled export **«Версія 181, 15 вер. 2026, 22:52»** after stripping CR. So the main CRM
needs no change at all — a correction to what the ред. 9 text originally assumed, already fixed in that
document.

Two further facts that change the shape of this task versus `3D-P-022`:

1. That handoff recorded "the 3D-P Apps Script API has no SKU-shape validation at all". It does now —
   the same validator exists at `3d-print/apps-script-3dp-api/Code.gs:204`. **Two copies of one rule.**
2. Sealed TCG articles (`PKM-JP-EXSD-STD-GRS` and the rest of `CAT-004`) carry none of the three 3D
   prefixes, never reach this validator, and pass every main-CRM guard. **`CAT-004` does not depend on
   this task.** The two can run in parallel.

Today's consequence: the owner cannot register `ACC-3D-ONIX-110-21-BLK` through the dashboard form, so
3D-printed variant families cannot enter the accounting catalogue. Site-side creation is unaffected.

## 3. Goal

An article written per ред. 9 — base with one or more variant tokens — is accepted by both create-form
validators. Everything the current grammar accepts keeps being accepted. Everything it rejects for
being malformed keeps being rejected.

## 4. What to change

One work package. Two files carry the same rule and must not drift again.

### 4.1 Extend the grammar

Current:

```
^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$
```

Target — the same base, plus zero or more suffix segments:

```
^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}(?:-[A-Z0-9]{1,5})*$
```

Design notes the executor should not silently change:

- **Zero or more**, so every existing article stays valid. This is the regression guard.
- Segment length 1–5 covers a size (`15`, `21`, `7`) and a colour (`BLK`). Verify that range against
  ред. 9 §5 before hardcoding it, exactly as `3D-P-022` required for the mnemonic range.
- Uppercase and digits only — no lowercase, no underscores, no empty segments. `ACC-3D-ONIX-110-`
  must still fail.
- **Widen only, do not validate token membership.** Owner decision 2026-09-15: the form accepts any
  well-formed token rather than checking it against the closed colour list. Keeping those lists inside
  two scripts would create a third place to drift. A typo in a token is caught by review, not here.

### 4.2 Keep the two copies from drifting

The prefix→type coupling check that follows the regex in both files stays unchanged.

Update the user-facing error message in both places to state the new shape with a concrete example
that includes a suffix — the current message names only `ACC-3D-PKM-130`, which no longer describes
what is accepted.

If the two files can share the rule without introducing a new dependency between the dashboard and the
Apps Script project, prefer that. If they cannot — and they probably cannot, since one is a local HTML
file and the other a deployed script — leave both copies and add a one-line comment in each pointing at
the other, naming ред. 9 as the source of truth.

### 4.3 Tests

Extend the existing dashboard static test for the validator, and whichever test in
`3d-print/apps-script-3dp-api/tests/` covers that script's guards — the executor must check what exists
there rather than assume.

## 5. Do not touch

- `crm/apps-script/Code.gs` — **nothing in the main CRM needs changing.** Touching it here would also
  be a parallel-writer risk against other in-flight CRM work.
- The `skipped_sku_shape` journal outcome and the outcome map from `3D-P-022`.
- The prefix→type coupling check.
- Any SKU string in any sheet. This task renames nothing and migrates nothing.
- `plans/3D-P_sku-naming-convention_20260807.md` — canonical; code aligns to it, never the reverse.
- The non-3D `ACC-0XX` accessory family.
- The edit/archive paths of existing SKUs — the validator runs on create only; confirm that still holds
  in the code you patch.
- Standing protected zones: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout,
  payment, fiscalization, Merchant feed, schema.

## 6. Likely files / areas

- `dashboard/booster-dashboard.html` — `threeDpSkuTypeError`, line ~1330.
- `3d-print/apps-script-3dp-api/Code.gs` — same validator, line ~204.
- Tests under `3d-print/apps-script-3dp-api/tests/` and the dashboard static test.

Line numbers were read on 2026-09-16 and must be re-verified.

**Delivery.** Two deliverables, one work package: a direct edit of `dashboard/booster-dashboard.html`,
which needs no deployment at all (the owner opens the repo file and presses Ctrl+F5), and a paste block
in `patches/` for the 3D-P Apps Script project, which the owner pastes in and publishes as a new
version. The PHP patch-runner flow does not apply here. Per `AGENTS.md` → "Apps Script mirrors",
refresh the repo mirror and `3d-print/apps-script-3dp-api/SOURCE_STATE.md` in the same session. The
executor never commits, pushes or deploys.

## 7. Acceptance criteria

Proven by test, not by inspection:

| SKU | expected |
|---|---|
| `ACC-3D-PKM-130` | accept (unchanged) |
| `ACC-3D-DITTO-410` | accept (unchanged) |
| `BR-CHARM-100` | accept (unchanged) |
| `FIG-CHARM-001` | accept (unchanged) |
| `ACC-3D-ONIX-110-21` | accept (new) |
| `ACC-3D-ONIX-110-21-BLK` | accept (new) |
| `FIG-ONIX-500-15-WHT` | accept (new) |
| `ACC-3D-410` | reject (no mnemonic — unchanged behaviour) |
| `ACC-3D-ONIX-110-` | reject (empty trailing segment) |
| `ACC-3D-ONIX-110-blk` | reject (lowercase) |
| `ACC-3D-ONIX-110-TOOLONG` | reject (segment over 5) |
| `ACC-001` | reject (unchanged) |
| `PKM-JP-EXSD-STD-GRS` | reject by this validator — correct, it is not a 3D article |

Plus:

1. Both files carry the **identical** regex; a test or a grep assertion proves it.
2. The dashboard create form accepts `ACC-3D-ONIX-110-21-BLK` and the error message for a rejected
   value names the new shape with a suffixed example.
3. Existing tests in both suites still pass.
4. The bounded diff touches only the two validators, the two messages and the tests.

## 8. QA / smoke test — owner

Not a checkout, payment or fiscalization change — `bs-checkout-smoke` not required. Not an SEO or
schema change — no gate. Risk is confined to article registration.

1. Create a named pre-deploy version of the 3D-P Apps Script project.
2. Paste the block, publish a new version.
3. Open the repo dashboard file, Ctrl+F5.
4. `3D-друк → Вироби → + Новий SKU`: register a suffixed test article, e.g.
   `ACC-3D-ONIX-110-21-BLK`, type `Функціональний аксесуар`. It must be accepted.
5. Register `ACC-3D-410` and confirm it is still rejected, with a message naming the expected shape.
6. Confirm an existing article can still be edited and archived.
7. Re-export the deployed script into the repo and update `SOURCE_STATE.md`.

## 9. Rollback note

Two single-expression edits plus message strings. Rollback = restore the previous regex and message in
each file; for the Apps Script, publish a new version from the pre-deploy history entry. The dashboard
needs only a file revert. No data is written or migrated, so there is nothing to undo in any sheet.

Asymmetric risk: reverting only widens nothing back — it re-blocks suffixed articles, which is the
state of the world today, so a revert is safe and boring. There is no failure mode where a revert loses
data.

## 10. Recommended status after execution

`In progress` until the owner has run §8 steps 4–6 on the live dashboard and script. Then `Done`.
Notion status is written by Claude (chat); the executor may update the `ROADMAP_FLOW` row for
`3D-P-027` within this authorised implementation.
