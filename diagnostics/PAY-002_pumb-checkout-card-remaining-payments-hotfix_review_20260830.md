# PAY-002 WP4 — patch review: PUMB remaining-payments display

Date: 2026-08-30
Reviewer: Claude (chat), read-only on the repository. The runner was executed
only in an isolated sandbox fixture, never against production.
Inputs: `patches/PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830.php`,
`diagnostics/PAY-002_pumb-checkout-card-remaining-payments-hotfix_report_20260830.md`,
WP3 review round 3.

## Verdict

**Technically Deploy OK — with one owner gate before it runs.** The runner is
correct and fully reproduced. What is not established is the fact it encodes: that
PUMB takes nothing from the customer on the purchase date. That claim comes from
two external pages, not from anything in this repository, and it is a statement
about a credit schedule shown to a paying customer. Confirm it with the bank
before deploying — one line from Roman Nazarenko closes it.

## Reproduced in a sandbox

Applied to the WP3 output (the exact state now on production):

| Check | Result |
|---|---|
| `BEFORE_SHA256` vs the WP3 result | `7fde759a…006e` — **match**, so the chain WP3 → WP4 is enforced |
| Runner output | `sha_gate=ok before=7fde759a after=ea4b41df`, `twig_assert=ok`, `done=ok`, `self_delete=ok`, exit 0 |
| Result hash vs `AFTER_SHA256` | `ea4b41df…b56b` — **match** |
| `php -l` on the runner | clean |
| Generated `<script>` blocks | 1, `node --check` exit 0 |

Behaviour of the two changed formulas, evaluated directly:

```
mono_chast 3 -> 2    pumb_credit 3 -> 3
mono_chast 4 -> 3    pumb_credit 4 -> 4
mono_chast 5 -> 4    pumb_credit 5 -> 5
```

Monobank's display is untouched, which is right: the repository documents that
mono's first payment is charged at the moment the store confirms
(`handoff_PAY-001_RESET_checkout-architecture-correction_20260721.md` §6), so
`count - 1` is grounded.

The click-handler formula keys off `data-pay002-provider` on the provider
`<article>`, which WP3 emits — verified present. Display only: `data-pay001-code`
and the term passed to `savePayment()` are unchanged, so nothing about what the
bank receives moves.

Improvement over WP3 worth noting: the restore path now re-hashes the restored
file and fails loudly if it does not match `BEFORE_SHA256`. A silent bad restore
is no longer possible.

## The one thing that is not verified

The report's "Product semantics" section cites `pumb.ua/credit` and a PUMB PDF
for "the customer pays nothing on the purchase date and makes the first payment
one month later". I could not fetch either from this session, and there is no
supporting evidence anywhere in the repository: the protocol revision, the
2026-08-25 bank test-drive result and the round-2 review contain nothing about a
payment schedule or a first payment. The bank lifecycle we proved
(`create → sign → WAITING_STORE_CONFIRM → goods_shipped → FUNDED`) says when the
*merchant* is funded, not when the *customer* first pays.

Both possible errors are real:

- if the premise is right, production currently shows «Платежів до завершення: 4»
  for a 5-payment PUMB term, which understates the customer's obligation;
- if the premise is wrong, this patch introduces that error instead.

This is exactly the class of claim the project contract says never to take on
trust. The owner has the contract and a direct bank contact; one confirmation
settles it either way, and the answer belongs in the PAY-002 plan so no future
session has to re-derive it.

## Non-blocking note

Once both providers are visible in the same drawer, the same label carries two
different meanings side by side: «3 платежі → 2» for monobank and
«3 платежі → 3» for PUMB. Correct in both cases, but to a customer comparing them
it reads as an inconsistency unless something says the first monobank payment is
taken today. Microcopy is not this patch's scope — it belongs with PAY-005, which
already owns the drawer's per-provider messaging.

## Conventions

| Conv. | Status |
|---|---|
| C1 file exists | ok, plus WP3-presence preflight (`data-pay002-provider`, blocked-row guard) |
| C2 anchor pre-check | ok — three exact anchors, each required exactly once, plus the whole-file BEFORE hash |
| C3 backup | ok, timestamped, path printed |
| C4 parse/verify + restore | ok in substance — SHA gate both sides, restore verified by hash |
| C5 idempotent marker | ok, distinct marker name |
| C6 DB | n/a, `database_touched=no` |
| C7 self-delete | ok on success |

Risky zone: checkout rendering only. Display-only change.

## Перед запуском

1. **Confirm with the bank**: does the customer pay anything on the purchase
   date, or is the first payment one month later? Deploy only on "one month
   later"; record the answer in `plans/PAY-002_pumb-protocol-revision_20260727.md`.
2. WP3 must already be live — the BEFORE hash enforces it.
3. Expected output: `sha_gate=ok before=7fde759a after=ea4b41df`, `twig_assert=ok`,
   `done=ok`, `self_delete=ok`, then clear the cache.
4. `live source SHA256 mismatch` means production is not the WP3 result — stop and
   send the full error.

## Rollback

Restore `catalog/view/template/checkout/payment_method.twig` from
`_patch_backups/PAY-002_pumb-checkout-card-remaining-payments-hotfix_20260830-<ts>/`
and clear the cache. That returns the drawer to the WP3 state, not to pre-WP3.

## Смоук після

Open the drawer with the token, select 3/4/5 in each provider and read the
«Платежів до завершення» line in both cards. Then the two checks still outstanding
from earlier rounds: complete an order with a PUMB term and confirm the posted
code is the PUMB one, and run the non-credit half of `bs-checkout-smoke`.

---

## Correction 2026-08-31 — the evidence was in the repository

The owner pointed out that the product fact is already recorded. It is, and my
round-1 search missed it: I grepped only the protocol revision, the bank
test-drive result and the round-2 review, not the decomposition plan.

`plans/PAY_decomposition_mono-pumb-preorder_20260721.md`, line 91:

> **Перший платіж клієнта у ПУМБ — через місяць після видачі** (слова власника з
> розмови з банком), не в момент confirm (відмінність від mono, де confirm одразу
> списує перший платіж). Наслідок для невикупу: клієнт на момент повернення ще
> нічого не платив → банку повертається повна сума.

That is the premise WP4 encodes, recorded on 2026-07-21, and it is internally
consistent with the rest of the file: the non-collection scenario in the same
section states the customer has paid nothing at the point of return, so the bank
gets the full amount back. The contract wording quoted at lines 93 and 196
(«мінус перший внесок клієнта», п. 2.2.4) is the generic contract formula and is
already reconciled by line 91 for our flow.

Provenance is the owner's account of a call with the bank rather than a written
bank document — the strongest source this project holds for it, and the owner is
the authority on his own contract conversation.

**Verdict updated: Deploy OK.** The bank-confirmation gate in "Перед запуском" is
withdrawn; step 1 becomes: nothing to ask, the fact is on file. Steps 2–4 stand
unchanged.

Follow-up worth doing once, not blocking: `plans/PAY-002_pumb-protocol-revision_20260727.md`
is the file a future session reads first for PUMB protocol facts, and it does not
carry this one. A one-line cross-reference to the decomposition plan §91 would
have saved this round.
