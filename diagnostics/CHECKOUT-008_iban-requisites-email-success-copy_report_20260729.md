# Codex Report — CHECKOUT-008: exact-code-gated IBAN requisites

Date: 2026-07-29

## Scope

Prepared a self-contained server runner for the order-created customer email and the checkout success page. It changes neither checkout order writing nor payment provider logic, order statuses, database schema, or payment configuration.

The runner renders the IBAN block only when the stored OpenCart payment option code is exactly `bank_transfer.bank_transfer`. It uses the existing neutral `bs-btn-secondary` class and existing card layout; no shared CSS or new styling override is added.

## Live-source evidence

Newest cPanel backup inspected: `backup-7.24.2026_17-02-32_boosters.tar.gz`.

- `extension/opencart/catalog/model/payment/bank_transfer.php` returns option code `bank_transfer.bank_transfer`.
- `catalog/controller/mail/order.php` is the customer-email event controller: the live `ocp5_event` row maps `catalog/model/checkout/order.addHistory/before` to `mail/order`.
- The 2026-07-24 live files already contain a partial IBAN email block, but gate it by payment-method display name. The runner replaces that heuristic with the exact stored code and aligns copy with this handoff.
- `catalog/controller/checkout/success.php` already reads the just-created order safely and exposes payment code to `checkout/success.twig`; the runner adds one additive boolean only.

## Files touched

```
patches/CHECKOUT-008_iban-requisites-email-success-copy_20260729.php
diagnostics/CHECKOUT-008_iban-requisites-email-success-copy_report_20260729.md
```

Server files that the runner changes after preflight:

```
catalog/controller/mail/order.php
catalog/view/template/mail/order_add.twig
catalog/controller/checkout/success.php
catalog/view/template/checkout/success.twig
```

## Owner confirmation gate

The handoff identifies a legal-label conflict: `ЄДРПОУ` vs `РНОКПП` for the same tax ID. The runner cannot apply without an explicit argument, preventing accidental shipment of unconfirmed customer-facing copy.

After the owner confirms the label, use exactly one of:

```bash
php CHECKOUT-008_iban-requisites-email-success-copy_20260729.php --tax-id-label=edrpou
```

```bash
php CHECKOUT-008_iban-requisites-email-success-copy_20260729.php --tax-id-label=rnokpp
```

## Dry-run result

Not run locally because this repository intentionally has no live target files. The runner has a server-side dry run; it checks file existence, all four exact anchors, and partial markers without writing:

```bash
php CHECKOUT-008_iban-requisites-email-success-copy_20260729.php --dry-run
```

Expected success includes:

```
mode=dry-run
owner_confirmation_required=yes
preflight=ok
changed_files=4
done=ok
```

## Isolated backup smoke result

The runner was executed in a temporary copy of the four target files extracted from the 2026-07-24 backup, not against hosting or the repository source.

```text
--dry-run: exit 0, preflight=ok, changed_files=4
apply --tax-id-label=rnokpp: exit 0, changed_files=4
php -l catalog/controller/mail/order.php: passed
php -l catalog/controller/checkout/success.php: passed
CHECKOUT-008 markers: 4/4
exact code gates: 2/2
requisites surfaces with test label: 2/2
```

`rnokpp` was used only to exercise the runner's explicit confirmation gate in this isolated test. It is not an owner confirmation for production copy.

## PHP syntax result

```text
No syntax errors detected in patches\\CHECKOUT-008_iban-requisites-email-success-copy_20260729.php
git diff --check: clean for both CHECKOUT-008 artifacts
```

Server apply also lints both changed PHP files and restores every written file on lint failure.

## Idempotency

After a successful apply, all four markers are present and a retained runner prints:

```
already_applied=yes
done=ok
```

The runner self-deletes only after a successful apply or successful already-applied exit.

## Rollback

The runner creates:

```
_patch_backups/CHECKOUT-008_iban-requisites-email-success-copy_20260729-<timestamp>/
```

Restore every backed-up relative path into `~/public_html`, then clear OpenCart cache. No database rollback is needed.

## Post-deploy QA checklist

- [ ] Owner confirms `ЄДРПОУ` or `РНОКПП` before applying the patch.
- [ ] Run the server dry-run, then apply with the confirmed label and execute the cache-clear command.
- [ ] Run full `bs-checkout-smoke`.
- [ ] Place an IBAN test order: received order-created email contains the exact block; success page displays the panel; copy/paste matches it exactly.
- [ ] Place COD and Hutko test orders (and enabled credit flows): no IBAN block/button; their emails and success pages remain unchanged.
- [ ] Confirm browser console has no new errors on success page.

## Side effects / risks

High-risk checkout/payment surface. Local source and syntax checks do not prove delivery into a real inbox, clipboard access in the production browser, or other payment-method regression. Owner deployment and live QA remain required.
