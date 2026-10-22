# ACC-009 optional courier apartment: checkout refusal investigation

Date: 2026-10-22. Executor: Codex. Scope: reported client checkout confirmation
failure when courier apartment is empty. No mandatory apartment policy is granted.

Current disposition: CLOSED WITHOUT FIX by owner direction, 2026-10-22.
The owner now reports authenticated checkout accepts an empty apartment and the
failure cannot be reproduced. Earlier pending evidence requests below are
historical; no further collection or reproduction is requested.

## Owner evidence

The owner reports courier delivery generally works, with one exception: apartment
is displayed as optional, but leaving it empty prevents order confirmation with
a generic OpenCart warning. Owner explicitly locates this on the customer-facing
site, not the admin TTN save form. Exact warning wording and guest/authenticated
mode have been requested and are not yet supplied. Do not infer those facts.
No customer/order identifiers or real payloads were requested.

The preceding postomat TTN creation was accepted. This new report does not imply
a failed C2 deployment; C2 changes only the admin document controller. Record the
reported courier workflow success within its stated empty-apartment exception,
without inventing individual C2 prefill/viewport/provider-edge passes.

## Verified local and public facts

- Applied checkout template's bsCheckoutNpIsComplete requires a street and house,
  not a flat. The printable-address composer omits the flat suffix when empty.
- The retained register, shipping_address and createAccount address-preparation
  methods permit empty flat and only impose its existing upper length bound.
- The exact parent-applied readiness helper SHA256 is
  69a7d5fdfb9ee77e34f623f3e4ace59fd3553da102e908ff03f98bfa9e3cb9a8.
  Its courier tuple permits empty/missing flat. This identity matches the accepted
  parent release facts, not a new live read of the PHP file.
- Native PHP8.0.30 focused checks pass for guest and authenticated fictional
  sessions, with flat empty, omitted, whitespace, string0 and string02. Actual
  helper assertReady/assertDeclaration/mint/accepts execute; sessions, cart,
  customer and order are fictional. No real endpoint/DB/API/order writes occur.
  This is not a reproduction or a fix of the owner's failure.
- Current unauthenticated public GETs of acc009-checkout.js and checkout-reskin.js
  return200 and exactly match accepted October21 after-images. This does not prove
  owner-browser cached bytes, dynamic template/runtime or the failing endpoint.
- Admin C2 guards and the existing admin validation also permit empty flat. The
  owner-confirmed failure location is the client checkout, so do not modify C2.

Evidence: diagnostics/ACC-009_optional-flat_evidence_20261022/
readiness-local-checks.json and public-static-identities.json.

## Limits and next bounded fact

No root cause is established. An older PAY002 carrier-model snapshot contains a
legacy address parser with weak no-flat handling, but its live identity is not
admitted here and its inspected caller uses only the street label; this is NOT
evidence that it causes the current confirmation refusal. Do not patch it on that
hypothesis. No broad archive, original private CRM/Telegram source, config,
customer/session record or provider request was inspected.

Obtain exact warning text (or a screenshot excluding personal data) and the
guest/authenticated path. Use these to identify the relevant confirmation stage
and decide the minimum additional runtime/source evidence. No speculative source
change, deployment, order/parcel creation, replay or external status write.
Any eventual repair keeps apartment optional and existing house/patronymic,
snapshot/seal and payment safeguards intact. Prepare locally as permitted;
required independent final review currently remains subject to the recorded
subscription quota gate, with no paid fallback or model downgrade.

## Owner closure - 2026-10-22

The owner confirms the affected path was authenticated checkout. On a subsequent
attempt the empty-apartment case passed; the prior warning could not be reproduced
and may have had another cause. The owner explicitly ends this investigation and
will open a separate fix task if the symptom recurs. Do not request another test,
warning capture, source pull or diagnostic run for this closed observation.

No source repair, deployment, review call or configuration change was performed.
Apartment remains optional. Current owner-reported success is accepted within
its scope; neither root cause nor a code fix is established. Guest production
behavior and individual unrelated delivery cases are not inferred.
