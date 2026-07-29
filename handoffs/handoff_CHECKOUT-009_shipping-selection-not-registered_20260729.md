# Codex Handoff — CHECKOUT-009: checkout delivery-readiness architecture audit and consolidation proposal

Date: 2026-07-29 | Parent: ST-2c (shipping/coupon/mini-cart rounds), PAY-001 (credit gate), RD-13 (checkout reskin)
Codex config: model=Sol · effort=xhigh
`ultra` is permitted only if you can demonstrably split the audit into
independent parallel streams (client state machine / server gates / quote
layer / credit gate); otherwise stay at `xhigh`.

## Owner directive (explicit, 2026-07-29 — this overrides the earlier scope)

> Do not look for a way to patch the hole, and do not go back to previous
> checkout versions by sacrificing features added since. Analyse the
> architecture deeply, request every file you need, document all logic,
> mechanics and actions currently implemented that affect this process and
> adjacent ones. Then propose a set of corrections, or if needed one broader
> solution that rewrites the pile of hacks into the right number of proper
> processes — without duplication and without patching patches on top of
> patches.

Consequences for this task:

- **No rollback** of the ST-2c 2026-07-28/29 rounds as a mitigation.
- **No band-aid patch** that only unblocks the symptom.
- **No new override stacked on an existing override.** If the correct fix is to
  delete accumulated compensating logic and replace it with one clear process,
  propose exactly that.
- **No implementation before the owner picks an option** from your proposal.

## Production impact (state it in your report, do not let it drive a shortcut)

Guest customers cannot place an order at all right now. The owner has accepted
an audit-first approach with this known cost. If, during Phase 1, you find a
correction that is both architecturally correct and small, surface it
immediately as an early finding — but do not substitute it for the audit and do
not deploy anything yourself.

## Reported defect (owner evidence, 2026-07-29, Cowork chat + screenshots)

The checkout does not recognise that a delivery address and method are
selected. The owner's own reading — which matches the leading hypothesis — is
that this is **one defect in the "delivery is chosen" determination**, not two
separate bugs:

1. **Logged-in, cart ₴700, saved address prefilled** (`Нова пошта — поштомат`,
   `Дніпро, Поштомат №49489`, receiver name and phone present): the payment row
   `Оплатити частинами` is muted/disabled with the hint `Будь ласка, заповніть
   дані отримувача і адресу доставки`; the summary shows `Заповніть доставку і
   оберіть спосіб оплати, щоб оформити замовлення` and `Підтвердити замовлення`
   is disabled.
2. A **page reload does not clear the state.**
3. **Selecting a different saved address clears it** — a workaround only
   available to a logged-in customer with more than one saved address.
4. **Guest, cart ₴700, delivery filled manually** (`Нова пошта — поштомат`,
   Київська / Київ / `Поштомат "Нова Пошта" №1033`), receiver name, phone,
   e-mail filled, offer checkbox ticked, payment method `За реквізитами на IBAN`
   selected: same summary gate, confirm button stays disabled, no workaround
   exists.

## Phase 1 — evidence gathering (request everything you need)

The owner has explicitly agreed to supply whatever you request. Do not infer
live structure from this repository or from historical patch text — patches
record intent at the time they were written, not the current state of the
files (AGENTS.md).

Minimum inventory to request, extend it as the audit requires:

- the **newest cPanel backup**, with confirmation of whether it was taken
  before or after `ST-2c_minicart_shipping_threshold_alignment_20260729`;
- or a targeted archive, e.g.
  `tar -czf booster-checkout-arch.tar.gz catalog/controller/checkout/ catalog/model/checkout/ catalog/view/template/checkout/ catalog/view/javascript/checkout-state.js catalog/view/javascript/checkout-reskin.js catalog/view/javascript/ catalog/controller/extension/ extension/PintaNovaPoshtaCod/ system/config/ catalog/language/uk-ua/checkout/`;
- a listing of `_patch_backups/` (directory names only) to establish which
  patch variants actually ran and in which order;
- the **theme/template database overrides** for every checkout Twig file — a DB
  override can silently win over a file change (AGENTS.md / project contract);
- browser evidence from a live reproduction, guest and logged-in: console
  output plus the full request/response of every checkout XHR
  (`shipping_address.save`, `shipping_method.*`, `payment_method.*`,
  `coupon.*`, totals/summary, confirm), in first-load order;
- whatever else you need — say what and why.

## Phase 2 — architecture documentation (primary deliverable)

Write `plans/CHECKOUT-009_checkout-architecture-map_20260729.md`. This is the
durable artifact; it must be usable by a future agent with no memory of this
incident. Required contents:

1. **Component inventory.** Every file that participates in the checkout
   readiness/selection flow: controllers, models, Twig templates, JS modules,
   the Pinta Nova Poshta extension, language files, session/cache touchpoints.
   For each: what it owns, what it reads, what it writes.
2. **State model.** Every piece of state that represents "who the customer is,
   where it ships, how it ships, how it pays": client variables, hidden inputs
   (including `#input-shipping-code`, `#input-shipping-display-text` and any
   sibling), session keys, OpenCart order-data keys, cookies, caches. For each:
   who writes it, who clears it, who reads it, and its lifetime.
3. **Event and call graph.** Page bootstrap, address entry/change, saved-address
   selection, shipping-method selection, quote request, coupon apply/remove,
   mini-cart quantity change and removal, payment-method selection, confirm.
   Show the order of operations and every network round trip, including the
   `revision` / token / abort mechanics and where races are possible.
4. **Every readiness gate, enumerated.** The confirm-button gate, the summary
   gate text, the credit-method gate (client and/or server — see
   `patches/PAY-001_phase2c_d4_credit_unavailable_row_20260725.php`, which
   describes a *server-owned* gate hint), and any other gate found. For each:
   exact file/function/line, exact inputs, exact truth condition, and which
   inputs can be empty or stale at first render.
5. **Guest vs logged-in divergence.** Every point where the two paths differ,
   and why.
6. **Patch archaeology.** Map the currently live compensating logic back to the
   patch that introduced it, using the in-source markers
   (`ST-2C-COUPON-SHIPPING-20260728`, `ST-2C-MINICART-SHIPPING-20260728`,
   `ST-2C-MINICART-THRESHOLD-ALIGNMENT-20260729`, RD-13 and PAY-001 markers, and
   any others found live). Identify: duplicated logic, competing writers of the
   same state, overrides stacked on overrides, dead code, `setTimeout`-style
   timing compensation, and magic values without a documented reason.
7. **Adjacent processes touched by the same state.** At minimum: Nova Poshta
   quoting and the ₴2000 free-shipping threshold, coupon/First15 totals, the
   mini-cart, the credit flow (mono live, PUMB disabled), CRM order sync, and
   the order-confirmation path. State how each one depends on the state model
   above, so a consolidation does not break them silently.

## Phase 3 — root cause

Name the exact file, function and line that produces the wrong readiness result
today, for **both** the guest and the logged-in path, expressed in terms of the
documented architecture — not as an isolated line-level observation. Supply
reproduction evidence, not only static reasoning. If the failure is a race or a
first-render ordering problem, demonstrate the ordering.

For orientation only — these are hypotheses to confirm or disprove, not
conclusions:

- the client keeps a visually selected method while no `shipping_method.save`
  round trip occurs, because the resave branch added on 2026-07-28 only saves
  when `options.resaveCurrent && currentQuote` are both truthy;
- the unconditional `$('#input-shipping-display-text').val('')` +
  `clearPaymentState()` at the start of the coupon/mini-cart handlers clears
  readiness state that only a user-driven address change restores;
- the guest path never produces the save event that the logged-in workaround
  triggers.

Do not force-fit the ST-2c timeline. If the evidence points elsewhere, follow
the evidence.

## Phase 4 — proposal (no implementation yet)

Write `plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md`
with at least two genuinely different options, each fully costed:

- **Option A — targeted corrections:** the smallest set of changes that fixes
  the root cause correctly at its source, with each accumulated hack it removes
  or leaves in place named explicitly.
- **Option B — consolidation:** replace the current state layer with a stated
  number of single-responsibility processes (e.g. one owner of "current
  delivery selection", one owner of "server-persisted readiness", one owner of
  "totals/summary rendering", one owner of "payment-method availability"), with
  no duplicated writers. Define the target design before describing the
  migration.
- Optionally **Option C — staged**: correct now in a way that is a strict
  subset of Option B, then consolidate.

For every option state: files touched, what it deletes, blast radius, risk of
regression in each adjacent process, verification strategy, rollback plan,
effort, and how it can be deployed and QA'd by the owner in bounded steps.

Include a **feature-preservation matrix** driven by the behaviour register
below: every currently live checkout behaviour, mapped to how each option
preserves, replaces or removes it. It must cover at least: three Nova Poshta modes; guest and logged-in flows; saved
addresses; ₴2000 free-shipping threshold correctness after coupon apply/remove
and after mini-cart quantity change/removal; Pinta remaining display-only;
coupon/First15; card, COD and IBAN payment methods; the credit method with its
legitimate blocks (minimum amount, and a pre-order item in the cart per
`handoffs/CODEX - PAY-001-ADDENDUM-2.md` §5); order comment, offer checkbox,
newsletter toggle, "save data for next time"; reCAPTCHA; CRM order sync.

Recommend one option and say why. The owner chooses; implementation is a
separate authorized round.

## Feature-preservation double check (mandatory gate — this is live checkout)

This checkout takes real money today. Nothing that currently works may be lost
inside a large patch. Every existing behaviour must end up in exactly one of
three states, each stated explicitly and each backed by evidence: **preserved
as-is**, **replaced by a named new mechanism**, or **deliberately removed with
the owner's approval**. "It probably isn't needed" is not an allowed outcome.

### Stage 1 — behaviour register (built in Phase 2, frozen before any code)

Produce `plans/CHECKOUT-009_checkout-behaviour-register_20260729.md` as a table
with one row per observable behaviour or compensating mechanism, derived from
live source, not from memory or from this repository's patch text. Columns:

| # | Behaviour | Where it lives (file · function · line) | Marker / patch of origin | Why it exists (defect it prevents) | Trigger to observe it | Disposition | Evidence after change |

Build the row set from three independent sweeps, so a gap in one is caught by
another:

1. **Marker sweep** — every in-source marker comment found in the live
   checkout files (`ST-2C-*`, RD-13, PAY-001, CHECKOUT-*, and anything else
   present). Record the complete marker inventory verbatim; it becomes the
   before-image for the drop check below.
2. **Behaviour sweep** — walk the UI states and the event/call graph from
   Phase 2 and enumerate what a customer can actually do and see, for guest and
   logged-in separately.
3. **History sweep** — the repository `patches/` and `diagnostics/` history for
   this checkout, to catch any defensive mechanism whose purpose is invisible
   from the code alone (it exists to prevent a defect that no longer reproduces
   precisely *because* it exists).

Any row you cannot explain — you can see the mechanism but not what it
protects — is flagged `UNKNOWN-PURPOSE` and escalated to the owner. Never
delete an `UNKNOWN-PURPOSE` mechanism silently; either preserve it or get an
explicit owner decision.

### Stage 2 — disposition mapping (part of the Phase 4 proposal)

Every option in the proposal must fill the `Disposition` column for **every**
register row. A per-option summary must state: rows preserved, rows replaced,
rows removed, rows still `UNKNOWN-PURPOSE`. An option that cannot account for
100% of the rows is not ready to be presented.

### Stage 3 — post-implementation self-check (implementation round)

Before handing the patch back, run and report:

1. **Marker drop check** — diff the marker inventory before vs after. Every
   marker that disappears must be named, with the reason and the new mechanism
   that subsumes it. Zero unexplained disappearances.
2. **Line-accounting** — for every deleted or rewritten block in the diff, the
   register row it belongs to and its disposition. Nothing deleted "in passing".
3. **Behaviour replay** — the trigger from each register row exercised in the
   patched code with recorded evidence, guest and logged-in.
4. Standard gates: `php -l` on every changed PHP file, `node --check` on every
   changed JS file, `--dry-run` clean, backups written, idempotent marker,
   rollback path stated.

### Stage 4 — independent review before deploy

Claude reviews the register against the repository history and the diff, as a
second pair of eyes on completeness — specifically hunting for behaviours that
exist in history but are missing from the register. Codex's mechanical checks
are not repeated; the review targets gaps, silent removals and side effects.

### Stage 5 — owner QA

The owner's manual QA checklist is generated **from the register**, not written
from scratch: every row with a customer-visible trigger becomes one check, and
`bs-checkout-smoke` runs on top of it. Deployment stays the owner's gate.

No implementation patch is accepted without Stages 1–3 complete.

## What NOT to touch in this round

- No file writes to the live site, no deployment, no commit, no push. This
  handoff grants no commit/push authority.
- No database changes.
- Bank transport, callbacks and `pumb_credit` internals (PUMB stays disabled;
  PAY-002 is unaffected), Checkbox/fiscalization, and the CRM Apps Script are
  out of scope as *change* targets — document their dependency on checkout
  state, nothing more.

## Acceptance criteria (this round)

- [ ] `plans/CHECKOUT-009_checkout-architecture-map_20260729.md` exists and covers all seven Phase 2 items, with file/function/line references to live source.
- [ ] Every readiness gate in the checkout is enumerated with its exact truth condition and its failure modes at first render.
- [ ] Root cause named for guest and logged-in paths, with reproduction evidence, expressed against the documented architecture.
- [ ] All live compensating logic mapped to the patch that introduced it; duplicated and competing writers listed explicitly.
- [ ] `plans/CHECKOUT-009_checkout-behaviour-register_20260729.md` exists, built from all three sweeps (marker / behaviour / history), with the complete verbatim marker inventory recorded as the before-image.
- [ ] Every register row has an origin and a stated purpose, or is explicitly flagged `UNKNOWN-PURPOSE` and escalated to the owner.
- [ ] `plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md` exists with at least two costed options, a feature-preservation matrix, and one recommendation.
- [ ] Each option fills the `Disposition` column for 100% of register rows, with a preserved / replaced / removed / unknown count per option.
- [ ] Any evidence you could not obtain is stated as an explicit gap, with what you need from the owner — no assumed structure.
- [ ] No production write, no commit, no push performed.

## Stop conditions

Stop and ask the owner if: required source or backup evidence is missing, stale
or contradictory; the live files do not match the markers this repository
records; a checkout Twig file is overridden in the database; or the audit shows
the correct fix crosses into a risky zone outside this scope (payment
transport, fiscalization, CRM, database).

## Risks

Risky zone: **checkout + payment + Nova Poshta**. Production order placement is
currently blocked for guests, so audit duration has a real cost — report early
findings as you get them rather than only at the end. The eventual fix must not
weaken the legitimate credit gates, must not regress the ST-2c free-shipping
threshold behaviour, and must not reintroduce the CHECKOUT-002/ST-2b confirm
pre-loading defect. Deployment and production QA remain the owner's gate.
