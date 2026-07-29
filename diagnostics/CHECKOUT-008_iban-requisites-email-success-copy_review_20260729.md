# Claude Review — CHECKOUT-008: IBAN requisites email + success copy button

Date: 2026-07-29
Reviewed: `patches/CHECKOUT-008_iban-requisites-email-success-copy_20260729.php` against
`handoffs/handoff_CHECKOUT-008_iban-requisites-email-and-success-copy_20260729.md` and
`diagnostics/CHECKOUT-008_iban-requisites-email-success-copy_report_20260729.md`.

## Verdict

**Review OK; owner QA required.** No repo copy of the live OpenCart files exists (by design for this
task class), so this is a source/logic review of the runner + Codex's reported live evidence, not a
git diff of the deployed files.

## Scope match

- Gates on the exact stored `payment_method.code` (`bank_transfer.bank_transfer`), not the display-name
  heuristic the handoff only allowed as a fallback — better than requested, and confirmed against the
  newest cPanel backup (`backup-7.24.2026_17-02-32_boosters.tar.gz`) rather than assumed.
- Requisites text (recipient, tax ID, IBAN, MFO, bank, purpose) matches the owner-approved block
  verbatim across all three surfaces: email, success-page panel, and the JS clipboard string — same
  source string, so copy/paste will match what's displayed.
- Additive only: `is_iban_bank_transfer` is a new sibling to the existing `is_hutko`/`is_cod` checks;
  no existing Hutko/COD/credit branch is modified. 4 files touched, matches handoff's likely-files list.
- Success-page button uses the existing neutral `bs-btn-secondary` class — does not use the DS's
  purchase-green, consistent with `AGENTS.md` UI/CSS discipline (green reserved for purchase actions).
- Patch conventions satisfied: file-exists check, single-anchor pre-check (fails loudly on 0 or >1
  matches, does not blind-edit), backup before write, `php -l` gate with auto-restore-on-fail,
  idempotency marker, no DB/schema change, self-delete on success. No commit/push attempted.

## Owner-confirmation gate (tax-ID label)

Correctly enforced in code, not just documentation: the runner refuses to apply without an explicit
`--tax-id-label=edrpou|rnokpp` argument (dry-run is exempt). Cannot ship the unresolved
ЄДРПОУ/РНОКПП conflict by accident.

**Operational note:** the idempotency marker does not encode which label was actually applied. If the
owner runs the real apply with the wrong label by mistake, a second run of the same script will report
`already_applied=yes` and self-delete without correcting anything — fixing a wrong label needs a manual
restore from `_patch_backups/` or a fresh patch. Pick the label carefully before running.

## New fact surfaced during diagnosis (not previously tracked anywhere in this repo)

Per the report, the live 2026-07-24 backup **already contains a partial IBAN block in the order
email**, gated by a display-name heuristic (`strpos($name, 'реквізит') !== false || strpos($name,
'iban') !== false`), with the tax line hardcoded as **ЄДРПОУ**. This means some customers who paid by
IBAN transfer may already have received an email with that label before this task existed, via
undocumented logic no handoff in `context-index.md` or `ROADMAP_SOP.md` currently accounts for. Flagging
this to the owner rather than treating it as resolved — it's independent evidence for the label
question, but its origin (who added it, when, why undocumented) is unconfirmed.

## Not verified by this review (owner/Codex live QA required)

- Actual delivery into a real inbox and real clipboard behavior in a production browser.
- That `$order_info['payment_method']['code']` is populated the same way in `mail/order.php`'s context
  as it is in `checkout/success.php` (both now key off the same code string; plausible from the shared
  pattern already in `success.php`, not independently proven here).
- No regression on COD/Hutko/credit — per handoff, requires one test order per method.
