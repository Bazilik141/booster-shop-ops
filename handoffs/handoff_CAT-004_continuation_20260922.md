# Handoff — CAT-004 variant families: continuation in a new chat

Date: 2026-09-22 · Author: Claude (chat) · Notion: `3dc6bf20-bdb4-8164-b001-db2191f9f7e6`
Audience: the next Claude chat picking up CAT-004. Read this **before** reading any
other CAT-004 document — several of them are superseded and one is cancelled.

---

## 0 · Confirm before acting

Nothing in §1 is assumed to still be true. At the start of the new chat, confirm
with the owner:

1. **Has `patches/CAT-004_variant-family_20260919.php` been run on production?**
   The repo copy stays in `patches/` either way — the runner self-deletes from
   `public_html`, not from the repository, so the file's presence proves nothing.
2. **Result of the `UI-PCARD-D` check** (§3.2).
3. Whether the owner has completed the TEST-SKU cleanup (§3.3).

Do not restate the review's evidence to the owner. It is in
`diagnostics/CAT-004_variant-family_claude-review_20260921.md`.

---

## 1 · State as of 2026-09-21

### The model (settled, do not reopen)

Owner decision of 2026-09-19, after a live test of the deployed selector: the
native OpenCart master/variant model is **abandoned**. `AGENTS.md` →
"Variant products — canonical rules" was rewritten the same day and is the
authority.

A variant family is a set of **ordinary independent products**. No `master_id`,
no product options, no field inheritance. Membership is one row per product in
the shop's **own table**, edited from its **own tab** on the admin product form.
**Exactly one axis per family** (colour *or* size *or* type — never two).

`master_id` must not be reintroduced. This was decided twice, after two
half-measures the owner rejected in the same words both times.

### Shipped and live

- **`CAT-004` SD-7 — Rare Pack listing badge.** Applied 2026-09-18, passed owner
  QA, **Done**. Lives in `thumb.php` (`bs_is_rare` flag) + `thumb.twig` (markup)
  + a CSS block in `boostershop-ds.css`. Badge: `.bs-badge--rare`, `#4C0519` on
  `#FDE68A`, in the top-left flex slot `.bs-pcard__badge-tl` alongside the
  pre-order and out-of-stock badges.
  Handoff: `handoffs/handoff_CAT-004-SD-7_rare-pack-listing-badge_20260918.md`
  Report: `diagnostics/CAT-004-SD-7_rare-pack-listing-badge_report_20260918.md`

### Deployed, failed, rolled back

- **`patches/CAT-004_variant-selector_20260916.php`** (the master/variant
  selector, reworked 2026-09-18). Deployed 2026-09-18, failed the owner's live
  test — disabling one variant dropped the master out of its own selector,
  because the patch had to *guess* the master's combination. Rolled back
  2026-09-19 from `_patch_backups/CAT-004_variant-selector_20260919_123600`.
  Rollback verified: header token back to `cat004-sd7-20260918`,
  `bs-variant__chip` count 0, `bs-badge--rare` count 1.
  **Its Twig selector block and CSS block survive as artefacts** — they were
  carried byte-for-byte into the new patch and are model-agnostic.

### Reviewed, approved, awaiting the owner's run

- **`patches/CAT-004_variant-family_20260919.php`** — the first implementation of
  the new model.
  Executor report: `diagnostics/CAT-004_variant-family_report_20260919.md`
  Independent review: `diagnostics/CAT-004_variant-family_claude-review_20260921.md`
  **Verdict: approved to run.** Re-verified independently on MariaDB 10.11 (the
  production major) against the real schema, not accepted on the executor's word.

---

## 2 · What the patch does

New table `ocp5_product_variant_family`:

```
product_id   int, primary key
family_key   varchar(64), indexed
axis_label   varchar(64)
value_label  varchar(64)
sort_order   int
```

Engine and collation are read from the live `ocp5_product` table at run time.

Eight files:

```
adminEvhenii/model/catalog/variant_family.php          NEW  (get/set/delete/keys)
adminEvhenii/controller/catalog/product.php            form() + save() + delete() + familyKey()
adminEvhenii/view/template/catalog/product_form.twig   tab «Родина варіантів» after #tab-attribute
catalog/model/catalog/product.php                      getVariantFamilyMembership() + getVariantFamilyMembers()
catalog/controller/product/product.php                 $data['variant_groups']
catalog/view/template/product/product.twig             selector block, carried from the 2026-09-18 patch
catalog/view/stylesheet/boostershop-ds.css             selector CSS, carried from the 2026-09-18 patch
catalog/view/template/common/header.twig               cache-bust → cat004-family-20260919
```

**The admin directory on this installation is `adminEvhenii`, not `admin`.**

Chip states: `''` buyable, `'out'` not buyable, `'none'` unreachable from this
data source but kept in the contract because the Twig branches on it. An `'out'`
chip **stays a link** — a sold-out member's page keeps its search weight and must
remain reachable from every sibling. The pre-order predicate is a line-for-line
mirror of `thumb.php` → block `BoosterShop RD-04f state normalization 20260601`,
the shop's single definition of that state: `quantity <= 0` **and** a stock-status
name containing `передзамов`/`preorder`/`pre-order`, or a future `date_available`.

`copyProduct` is deliberately untouched — a copy starts with no family.

---

## 3 · Open items, in order

### 3.1 · Run the patch

Nothing to restore first. The selector rollback already happened on 2026-09-19;
telling the owner to restore that backup again would undo everything since.
The runner's own `previous_patch_still_applied` guard covers the case where it
somehow did not happen — it refuses before writing anything.

```bash
php CAT-004_variant-family_20260919.php
```

Expect `db_table_state=created`, `header_cache_bust_from=<whatever is live>`,
`done=ok`, `self_delete=ok`. On any run that does not end `done=ok` +
`self_delete=ok`, the runner stays in `public_html` and is publicly executable by
URL — delete it by hand first.

### 3.2 · Did `UI-PCARD-D` survive the selector rollback?

`patches/UI-PCARD-D_no-box-card_20260919.php` rewrites `.bs-pcard*` rules **in
place** in `boostershop-ds.css` and sets the header token to
`ui-pcard-d2-20260919`. The post-rollback check found `cat004-sd7-20260918`,
so either UI-PCARD-D was never applied, or it was applied before the rollback and
the rollback silently reverted it. One command settles it:

```bash
grep -c "UI-PCARD-D" catalog/view/stylesheet/boostershop-ds.css
```

Non-zero → nothing to do. Zero, and the owner expected that work live →
re-upload and re-run `UI-PCARD-D_no-box-card_20260919.php` (it self-deleted).
Order against the family patch does not matter: both read the cache-bust token by
path prefix instead of anchoring on its value (`AGENTS.md` patch convention 8).

**Caveat for the new chat:** RD-11 / RD-12 work landed on 2026-09-21–22 in other
chats and also touches the stylesheet and the header token. Re-check the live
state before asserting anything about either.

### 3.3 · Owner pre-step still owed

The TEST SKUs still carry `master_id` links and product options from the
abandoned experiment. The patch neither reads nor breaks on them, but those
products still inherit fields from their master — the exact behaviour the model
was abandoned for. Strip both before building a real family.

### 3.4 · Owner QA after the run

The list is `diagnostics/CAT-004_variant-family_report_20260919.md` §6. Do not
restate it. The one item that matters most, because it is the model's only silent
failure mode:

> Fill a family, open one member's page, and **count the chips**. A missing chip
> means a mistyped `family_key` or an empty `value_label` on that product. Nothing
> warns.

### 3.5 · Notion

`CAT-004` stays `In progress` until owner QA passes. Status writes are Claude's,
on explicit owner instruction only, and must update **both** Notion and the
`ROADMAP_TASKS` array in `dashboard/booster-dashboard.html` in the same session.
(The array is named `ROADMAP_TASKS`, not `ROADMAP_FLOW` as the governing docs say.)

---

## 4 · Documents: which are current

| document | status |
|---|---|
| `handoffs/handoff_CAT-004_variant-family-extension_20260919.md` | **current spec** |
| `diagnostics/CAT-004_variant-family_report_20260919.md` | current executor report |
| `diagnostics/CAT-004_variant-family_claude-review_20260921.md` | current review, verdict: approved |
| `handoffs/handoff_CAT-004_variant-selector-attributes_20260919.md` | **CANCELLED** — stored family membership in product attributes, which render in the customer-visible specification table |
| `handoffs/handoff_CAT-004_variant-selector_20260916.md` | superseded (master/variant) |
| `handoffs/addendum_CAT-004_variant-selector-rework_20260918.md` | superseded (master/variant) |
| `handoffs/handoff_CAT-004-SD-7_rare-pack-listing-badge_20260918.md` | Done |
| `plans/CAT-004_op-rare-packs_identifier-canon_20260916.md` | current — §6 is why a sold-out member's chip stays a link |

---

## 5 · Known and accepted — not defects, do not "fix"

- `axis_label` is stored per product, and the row heading uses the **currently
  open** product's value. Different labels across members give a heading that
  changes depending on which page you stand on. A data error, not a code error.
- Nothing stops two members carrying the same `value_label`. Same class.
- The future-`date_available` branch of the pre-order predicate is unreachable
  from this source: the model's `date_available <= NOW()` filter drops such a
  product from the family first. That is the standard catalogue rule — such a
  product cannot render its own page either.
- Opening the admin "add variant" form (`&master_id=N`) prefills the master's
  family row, because core sets `$product_id = master_id` on that path. Dead end
  only for the abandoned variant feature.
- The new table lacks `ROW_FORMAT=DYNAMIC`, which `ocp5_product` has. Engine and
  collation are copied; row format is not. No functional effect.

---

## 6 · Carried-over items from this thread, not part of CAT-004

- **`bs-content-qa` skill update** — proposed via the skill review card on
  2026-09-16 (new §9 "Variant families", §10 "Rare Pack line (RPK)", plus rules
  about sourced facts and never re-deriving a canonical identifier). The owner has
  not confirmed saving it. Ask once; do not re-propose unprompted.
- **Content for 13 products** (9 Pokémon ex Start Decks + 4 One Piece Rare Packs)
  and the offer amendment `LEGAL-003` are with ChatGPT. Brief:
  `handoffs/handoff_CAT-004_starter-decks-and-rare-packs_chatgpt_20260916.md`.
  When the copy comes back, QA it through `bs-content-qa`.
- **3D-P-027** (CRM SKU suffix validators) — closed 2026-09-19. Main CRM at V185,
  3D-P API at V34, both owner-confirmed deployed.
- **`plans/3D-P_sku-naming-convention_20260807.md` ред. 10** — mnemonic `TCG`
  registered; a product with no franchise gets **no brackets at all**; first
  article `ACC-3D-TCG-600` / «Розділювач для колекційних карток — 3D-друк».
- **Open, no task filed:** the admin draft-assign field («Вставити новий артикул»)
  does not uppercase on client or server.
- **Open, recorded, not fixed:** the dashboard category label for `ACC-3D 600`
  («Плаский пластиковий аксесуар») disagrees with the canon
  («Закладка / розділювач для карток»).
- **Uncommitted, unverified.** A commit covering `dashboard/booster-dashboard.html`,
  two test files, `3d-print/apps-script-3dp-api/SOURCE_STATE.md`, the 3D-P-027
  follow-up report, `plans/3D-P_sku-naming-convention_20260807.md`, `AGENTS.md`
  and the two CAT-004 handoffs was handed to the owner on 2026-09-19; whether he
  ran it was never confirmed. `3d-print/apps-script-3dp-api/Code.gs` and
  `crm/apps-script/Code.gs` are deliberately excluded (third-party uncommitted
  work). **Do not run `git` against the mounted repo from the Linux sandbox** — it
  leaves an `index.lock` the sandbox cannot delete. Ask the owner instead.

---

## 7 · Standing constraints

- Reply to the owner in Ukrainian; durable agent-facing artefacts in English.
- In reports and reviews write **only what needs a decision or a question**.
  Skip everything that is fine. Plain, non-expert language. Evidence goes in the
  repo artefact, not in chat.
- Claude never commits, pushes, deploys, uploads, or touches the server. The
  owner is the only production gate.
- Never invent facts, prices, specifications, status, evidence, runtime results
  or external-system state. Name what is missing, stale or unverified.
- Roadmap writes (Notion + dashboard mirror) only on explicit owner instruction.
- Production is PHP 8.0.30, MariaDB 10.11.19, mysqli **without mysqlnd**
  (`get_result()` and `fetch_all()` are fatal). DB prefix `ocp5_`, single
  catalogue language `language_id = 4`.
- The owner's pasted admin URLs contain a live `user_token`. Never echo one.
