# Codex Handoff — ST-2a.8b: NP dropdown CLICK-select does not trigger autosave (only ENTER does)

Date: 2026-06-13. Parent: ST-2 Path B, follow-up to ST-2a.8. Base: live (st2a8 applied) / fresh backup `backup-6.13.2026_16-15-57`. Client JS only.

## 1. Task ID
ST-2a.8b — after ST-2a.8, selecting NP area/city/warehouse by CLICKING a dropdown suggestion fills the value but does NOT load shipping/payment. Pressing ENTER in the field afterwards triggers a micro-update and shipping+payment appear. So autosave fires on ENTER but not on mouse click-select.

## 2. Context — root cause (owner-confirmed empirically + code-traced)
- Owner test: pick відділення/поштомат from dropdown by mouse → nothing loads; clicking other fields doesn't help; press ENTER → shipping + payment appear.
- Dropdown is rendered/handled by Pinta: `extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig`.
  - Click handler (`.off/.on('click.pintaNovaPoshtaDropdown', '.pinta-dropdown li')`, ~line 215): `input.val(label); hidePintaDropdown(input); window.bsCheckoutConfirmNpDropdown(input[0], ref);`
- `bsCheckoutConfirmNpDropdown` (checkout.twig ~383) sets data-ref + hidden ref and calls `bsCheckoutNpFieldChanged` (~674) → schedules `triggerRegisterAutosave` (350 ms).
- **But** `input.val(label)` does NOT dispatch `input`/`change`/`keyup`, so the checkout.twig delegated autosave handler `$(document).on('change keyup blur input', '#form-register input ...')` (~line 771) — the one ENTER triggers via keyup — never runs on click. The only autosave path on click is the direct `bsCheckoutNpFieldChanged` from confirm, and in practice it does not produce a saved address on click while ENTER does.

## 3. Goal
Selecting area/city/warehouse (and doors) by mouse CLICK reliably triggers the same autosave + shipping/payment load as ENTER — no extra keypress.

## 4. What to change
**Primary (targeted, safe):** in the Pinta click handler (`js_checkout_shipping_address_form.twig`, `.pinta-dropdown li` click), AFTER `bsCheckoutConfirmNpDropdown(input[0], ref)`, dispatch the same signal ENTER produces, e.g. `$(input).trigger('change');` so the checkout.twig delegated autosave handler runs identically to the keyup path. Keep the existing `bsCheckoutConfirmNpDropdown` call.
**Root-cause check (don't just paper over):** confirm WHY the direct `bsCheckoutNpFieldChanged → triggerRegisterAutosave` (350 ms) doesn't save on click while ENTER does. Check:
- is `#form-register` `bsSaving` data-flag stuck `true` (not reset) blocking later autosaves?
- is the 350 ms `bsRegisterTimer` cleared by a trailing blur/focus after the click?
- is `registerIsComplete()` false at the 350 ms mark for a reason ENTER later resolves?
Fix the actual cause so one click-select (with contacts already filled) saves.

## 5. Do not touch
Server / `register.save` / captcha (ST-2a.7) / ST-2a.8 ref logic (keep it) / Hutko / Checkbox / NP model / DB / POST field names / SimpleCheckout / url.php.

## 6. Likely files
`extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig` (click handler ~205-230). Possibly `catalog/view/template/checkout/checkout.twig` (`bsCheckoutNpFieldChanged` ~674, `triggerRegisterAutosave` ~650, `bsSaving` reset) if root cause is there. Verify against live; clear template cache after (twig).

## 7. Acceptance criteria
1. Guest: pick area→city→warehouse purely by MOUSE CLICK + fill email/phone → shipping methods AND payment load WITHOUT pressing ENTER.
2. Poshtomat: same.
3. ENTER path still works (no regression).
4. Editing/clearing a field invalidates completeness until re-selected.
5. Logged-in unchanged.

## 8. QA / smoke
bs-checkout-smoke. Guest відділення + поштомат + адресна via click-only; ST-2a.8 reload case still loads; order reaches admin.

## 9. Rollback
Single twig file → backup to `_patch_backups/st2a8b-*`; restore + clear template cache.

## 10. Status after
Guest blocker fully closed (ST-2a.7 + 2a.8 + 2a.8b). Update Notion R-13.5.
