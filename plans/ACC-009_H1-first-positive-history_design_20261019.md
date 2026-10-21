# ACC-009 H1 — first-positive-history notification design

Date: 2026-10-19.
Executor: Codex · model=Sol · effort=xhigh. Multi-file checkout/history/CRM semantics
require the assigned strong executor and actual subscription Claude Opus/high review.
This is a coherent DESIGN request, not production implementation or apply release.

## Required result and actual owning facts

Suppress CRM/NCRM/Telegram work caused solely by an unsubmitted draft. The first
actual positive-status history must retain the new-order classification even if
zero-status draft rows already exist. Preserve native history/status, fraud, stock,
subscription, totals, mail/event effects and legitimate later status handling.
Positive means the existing Telegram rule's `new_status_id > 0`, not paid-only.
C1 already requires `config_void_status_id == 0`; retain that admission condition.

The newly owner-approved, SHA-bound technical excerpts establish:

- Native editOrder first calls addHistory with config_void_status_id.
- addHistory currently counts **all** order_history rows before insertion, then
  chooses order_add only for total==0 and order_status otherwise.
- ncrmOrderSync only registers order_add, and both CRM wrappers register shutdown
  functions that subsequently load the order from the database.
- Telegram requires enabled status, new_status_id>0 and **all-history total==1**.

These are current captured-source facts, not restored guesses from historical
reports. SOURCE-S2 matches all five input identities; bounded candidate coverage
has no new paths. It is not global effective-runtime/no-bypass proof.

## Bounded change and unchanged contracts

Proposed targets:
1. `catalog/model/checkout/order.php`: change only notification classification,
   capture the newly inserted history ID and forward it to the existing Telegram
   controller invocation. Preserve the existing private route bytes mechanically.
2. `extension/telegram_notify/catalog/controller/event/telegram.php`: accept an
   optional history-ID argument and qualify the call against the first positive
   history; keep message formatting, enabled switch and sending code unchanged.
3. New `system/library/booster_order_notification_guard.php`: small source-owned,
   PHP8.0-compatible read-only decision helper used by both owners.

No sender, queue, worker, marker, CRM receiver, configuration, DDL or data migration
change is proposed. Sender suppression/dedup/retry behavior remains an explicit
constraint, not new permission to rewrite it. No historical affected order replay.

Existing C1 R5's23 modified paths do not include these three paths. Its existing
order-model source identity is0324c221…; H1 changes that read dependency, so final
C1/H1 composition must explicitly assess compatibility, not claim old C1 review
already covers the changed model. Native addOrder/editOrder signatures and normal
history writes remain. C2/N1/N3 separate gates remain; do not wait on unrelated
browser/NP preparation merely to design H1.

## Decision algorithm

After the original history INSERT, immediately capture the native DB last-insert
ID before further controller/helper calls. Query the smallest positive history ID
for this order using an integer-bound order ID and the existing DB_PREFIX:

`SELECT MIN(order_history_id) AS first_positive_history_id FROM <prefix>order_history WHERE order_id = <id> AND order_status_id > 0`.

Helper results distinguish unavailable/error, no-positive, first-positive and later
submitted-order history. No missing value is silently coerced into a new-order
decision. Preserve the original history INSERT and every non-notification statement.

| New history | First positive history | Action |
|---|---|---|
| zero, none positive | absent | Native write/cache effects continue; no CRM/NCRM/Telegram registration |
| positive, its ID is the minimum | this row | Event order_add; existing CRM/NCRM wrappers; Telegram with this history ID |
| positive, an earlier positive exists | earlier row | Event order_status; original later CRM path; no new-order NCRM/Telegram |
| zero, an earlier positive exists | earlier row | Event order_status; preserve the original later CRM path, including void/cancel intent; no new-order NCRM/Telegram |
| ID/lookup invalid or failed | unavailable | Do not invent a first-order result; record a generic no-PII diagnostic; native order/history processing continues |

Keep notification call ordering where applicable: Telegram, CRM, NCRM. Do not wrap
native stock/history code in a new transaction or swallow its existing errors.
Contain only failures introduced by the new eligibility/helper lookup; existing
sender/controller error behavior should not be casually widened.

The existing shutdown wrappers still load final order data after request work.
Add a fresh positive-history eligibility check before invoking each sender, to
avoid invoking it solely for a rolled-back/deleted history. Do not use current
status==0 as an unconditional draft test after a real submission: a legitimate
later void/cancel must retain its existing path. Existing sender guards still
control missing orders, skip lists, markers and queued payloads.

The Telegram controller validates order/history identity and positive status,
then requires the supplied history ID to equal the minimum positive history ID.
For existing two-argument callers, decide explicitly in review between retaining
their exact old eligibility as a compatibility fallback and fail-closed suppression.
Recommended: retain the old two-argument branch unchanged; the repaired native
three-argument path uses the new decision. Its scope must be reported honestly;
the S2 inventory is not proof that no dynamic legacy caller exists.

## Concurrency, ordering and existing-data limits

The minimum-row approach avoids the deterministic count==1 race in which two
committed positive rows make both callers skip. It must be tested under declared
native autocommit/read visibility, not asserted for unknown ambient transactions.
Overlays/events/transaction wrapping are admission facts if they affect the proof.
Do not add advisory locks, storage choices or a new delivery/outbox system here.

Each sink's first-positive invocation and the order-history classifier can be
tested. The unchanged queue/sender can still have marker, pending/working, crash,
retry and cross-request ordering limitations. A later CRM order_status callback
may race a first order_add callback through the existing per-order dedup; review
whether this weakens the proposed functional guarantee or needs a narrowly scoped
caller adjustment. Do not infer receiver behavior or exactly-once delivery.

Existing sent markers and queued drafts are not cleared. An order already affected
before deployment is not automatically recovered. The intended guarantee covers
new execution through the repaired owners; no retrospective resend is authorized.

## Test fidelity and discriminating cases

Implementation must execute the actual new helper and actual generated notification
decision/call region on fictional histories, not a permissive reimplementation.
Use the real relevant loader invocation/signature contract where available. Keep
DB, queue, shutdown execution and external receivers explicitly fake/local, with
no application bootstrap or sender/worker/config execution. Private route/literal
bytes remain opaque and unchanged; never print or embed unapproved source in Git.

Cover: no-history/new zero; several zeros then positive; later positive; positive
to zero to positive; disabled Telegram; nonexistent order; missing/invalid last ID;
lookup failure; per-order separation; two committed positives in both scheduling
orders; independent pending transactions as an explicitly unsupported/admission
case; multiple callbacks within a request; deferred final payload; unchanged native
history/cache path and existing later CRM calls. Include a broken all-row count
mutant and a dropped-history-ID-forwarding mutant. No check-count target.

## Complete runner, rollback and host acceptance contract

Prepare one self-contained PHP runner with exact baseline SHA pins for both owners
and absence-or-exact-marker handling for the new helper. Use structural edits
under pinned function/token identities so private existing route/source bytes need
not be embedded into the published patch. In-memory transformation may verify an
after-SHA; raw full source/afterimages must not enter model output, Git or diagnostics.
Reversal of only the declared edit recipes must reproduce baseline bytes exactly.

Back up all originals and record created files before writing; add helper before
dependants, lint all affected PHP with native8.0 and restore the whole source set on
failure. Test idempotency and hash-guarded rollback; remove a newly created helper
only if its exact after-SHA still matches. Self-delete after confirmed success.
Do not provide apply instructions until CODE/composition and runtime gates pass.

Host dry-run/admission must verify current source pins, PHP/web opcode assumptions,
the effective two touched owners (no unreviewed overriding modification/event),
config_void_status_id==0, the loader argument contract and required order_history
columns. The proposed MIN-ID logic requires a positive unique auto-increment
order_history_id and appropriate DB visibility; validate those facts instead of
assuming schema from generic OpenCart. No customer rows, config secrets or payload
data are output. A schema/runtime mismatch is STOP, not an in-place live rewrite.
Required host facts will be batched in this runner's read-only admission, not a
new chain of ad hoc exports. Matching expected evidence closes mechanically under
the adopted rules. Owner application, focused QA and Tier1 smoke remain separate.

## Requested actual Claude disposition

Assess this coherent architecture and the admitted technical-role evidence together.
Return Design Review OK (with concrete implementation conditions) or Return for
changes. Specifically resolve: caller-only sufficiency with unchanged senders,
the recommended legacy two-argument Telegram fallback, minimum-ID/visibility and
shutdown timing, and the existing CRM event/dedup ordering constraint. Distinguish
facts needed now from later host admission. Do not reopen accepted source/formatting
checks or request whole-shop proof. No new owner business choice is implied by a
routine technical repair inside this accepted scope.
