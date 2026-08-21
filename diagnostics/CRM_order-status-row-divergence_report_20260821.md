# Codex Diagnostic — CRM preorder status update result

Date: 2026-08-21

## Scope

Diagnosis only for the owner-reported `nothing changed` response when changing
`OC-FOP-0323` to `Передзамовлення` in the dashboard accounting editor. No CRM
row, Apps Script source, dashboard source, deployment, or order status was
changed by this investigation.

## Owner evidence

The owner reports CRM Web App V139 deployed at 09:47 Kyiv with accessory-SKU
QA and integrity check OK. The supplied dashboard screenshot shows
`OC-FOP-0323`, current status `В обробці`, selected new status
`Передзамовлення`, and response `Помилка: nothing changed`.

## Live readback

Read-only Google Sheets API search of the live `Продажі` tab, bounded to
`A3:AF452`, found exactly one matching record:

- row 287: order `OC-FOP-0323`, status `Передзамовлення`.

No second row for that order was found, so the proposed multi-row divergence
signature is absent.

## Conclusion

The final saved status is correct. The response shown in the screenshot did
not cause a data loss and no repair is warranted from this event alone. The
overview preorder filter is not involved: it is read-only dashboard rendering
and never calls the order-update API.

The earlier local V137 code-path hypothesis was conditional on a multi-line
order with diverged statuses. The live row count falsifies that condition for
`OC-FOP-0323`; without a repeatable failing request or a fresh V139 source
export, its cause remains unproven.

## Follow-up threshold

No code change is prepared for order status. Reopen only if the same error
recurs and the target status is not saved; then preserve the request time and
make one bounded live readback before changing CRM logic.
