# RD-16 WP2 main ready review r1: integrated code, composition and transport

## Verdict: **Review OK, conditional**

There are no mandatory changes. Optional notes follow at the end.

This approval is code and composition readiness only. It does **not** release owner Upload, Verify, Apply or Rollback. Those stay **HELD** until the owner explicitly records the WP2 header-token-only exception (see Gates).

It grants no execution, Git, status, SSH, key, server or deployment authority to me or any agent. It waives no WP2 owner QA.

## Bound artifacts

| Artifact | Bytes | SHA-256 |
|---|---|---|
| `patches\RD-16-WP2_personal-data_20261026.php` | 107,879 | `d03a0e76c63892365ecef4a601456365429828aae8201a1916514f03b989e851` |
| `diagnostics\RD-16-WP2_owner_20261026.ps1` | 11,928 | `04633bf5e023e063d46cbbe3ae3c19dbbbd95ef7c4b0c94b5faa971bcfa31cea` |

I had no tools, so I did not recompute either hash. I checked that the supplied values agree with each other:
- The packet's `$Sha` literal equals the runner hash above.
- `runner-results.json` and `release-facts.json` both bind `d03a0e76…`.
- `packet-checks.json` and `release-facts.json` both bind `04633bf5…`.
- **Packet size reconciles by hand.** Starting from R8's 11,839 bytes:

  | Change | Bytes |
  |---|---|
  | HELD line | +82 |
  | `$Name` and `F=` | −14 |
  | Two backup regexes | −8 |
  | Receipt directory | +2 |
  | "seven" → "five" | −1 |
  | Seven `RD16_` → `RD16_WP2_` markers, including both heredoc delimiters | +28 |
  | **Net** | **+89 = 11,928** |

- The runner's growth comes mostly from the embedded base64 payloads, so I did not reconcile it by hand. I relied on the full template and derivation diff instead.

Before any later release, Codex must confirm that the on-disk bytes match both rows. Any byte change voids this verdict.

## Known CI sidebar intake hold: disposition

**Cleared for WP2 composition on `account/edit` only.** This is a reasoned resolution of intake condition 5 for this module at these exact hashes and metadata. It is not a general waiver of the condition.

**Reasons**
1. **The module has no execution path on the patched page.**
   - The patched `index()` no longer calls `common/column_left`, which is the module's only position.
   - The runner's exact query requires `layout_module` for layout 6 to be exactly `[ciaccountsidebar.account_sidebar, column_left, 0]`, using `LIMIT 2` so a second row is detected.
   - So the still-called `column_right`, `content_top` and `content_bottom` have nothing to render.
   - The CI stylesheet is added only inside the module's `index()`, so it is not loaded either.
   - Nothing is removed unexpectedly. The legacy nav is deliberately replaced by the shell, which is the handoff's "one nav surface" acceptance criterion.
2. **It uses the same mechanism as the accepted, deployed, owner-QA'd hub.**
   - The caller opts in through `account/shell` and blanks `column_left`.
   - No global module, layout or other-route change is made.
3. **Every other route is untouched.**
   - `address`, `order`, `wishlist` and the rest still call `column_left` and still render the CI sidebar.
   - That interim inconsistency already existed after WP1 and is within handoff §5: those pages adopt the shell in their own tasks.
4. **It fails closed on any change.** Owner Verify stops before backup in any of these cases:
   - drift in any of the three CI source pins, `column_left.php` or `.twig`, or `model/design/layout.php`;
   - any `layout_route` result other than `[[0,6,'account/%']]` for hub or edit;
   - any added, removed or moved layout-6 module.

   The CI module's admin config values are not pinned. That does not matter, because the module is never invoked on the edit page.

**Conditions**
- If Verify or Apply returns `account_layout_changed`, `account_layout_modules_changed` or `dependency_source_drift`, the hold is fully reinstated. Stop, preserve receipts, run a new review, and do not adapt blindly.
- Owner QA at 768–991 px must confirm exactly one nav on `account/edit`, and that other account pages are visually unchanged.

## Findings by area

### Protected save path
- **Controller.** Only the single `column_left` call site is replaced.
  - `semantic-proof` shows that removing the fragment reproduces `before` (`91a35858…`).
  - The builder asserts that the `save()` and `saveAcc008()` tail is byte-identical (`375d1f48…`).
  - The redirect-on-invalid-login path is unchanged. OC4's `Response::redirect` exits before the block runs, so `customer_token` use matches the existing breadcrumbs.
- **Protected sources.** Seven are byte-identical and pinned: the guard, the profile helper, three models, `booster-account-profile.js` and `common.js`.
- **No new data path.**
  - No `custom_field` echo, hidden field, action, method or `data-oc-toggle` change.
  - No JavaScript.
  - The language file is append-only, and existing error strings are natively compared.

### Form contract
- All 10 custom-field type branches are retained verbatim apart from wrapper classes, including the ACC-008 checkbox-presence hidden input.
- Each field renders in exactly one fieldset:
  - the split is a `== 'name'` / `!= 'name'` partition over the authoritative `kinds()` map;
  - inactive maps fall into Contacts;
  - integer and string key lookup matches the existing `data-acc009-kind` expression.
- A hidden input as a grid child is `display:none`, so it creates no grid item.
- The ACC-009 hint anchor (`input.parentElement`) and the `#input-*` / `#error-*` mapping in `common.js` are preserved.
- Email stays editable (D1 default). The read-only email hint, the board name `pattern` and the static phone hint are not added. That is correct.

### UI and accessibility
- **Layout:** labels sit above fields; there are two legends with an `aria-hidden` dot; Save is the primary action and Cancel the secondary.
- **Contrast:** white on `--bs-blue` and ink on paper both pass AA. There is no green.
- **Nav:** "Налаштування" is not focusable, and `aria-current` is on the edit item.
- **CSS:** every rule is prefixed `.bs-account-edit`. There is no `!important`, positioning layer or timer, and every magic value is commented.
- **Invalid state:** `.form-control.is-invalid` is correctly re-raised to specificity 0,3,0, so the scoped base rule cannot mask Bootstrap's red border.
- **Focus:** the input focus indicator meets AA through the 1 px navy border. It is weak, though (see O1).

### Header
- The change is token-only:
  - exactly one anchored match;
  - the normalized body is identical (`54879a99…`);
  - the `after` bytes are independent of whichever token is present before;
  - an already-applied state is never retouched.
- The token bump is genuinely required. Without it, stale DS CSS would leave the new form markup unstyled.

### Runner
- The diff against the accepted R8 template is limited to:
  - the identifiers;
  - 26 event targets;
  - `edit.twig` in the alternate-template gate;
  - `account/edit` in the theme check;
  - counting theme rows regardless of `status`, which is stricter and closes intake O1;
  - the 5-target order and count.
- **Apply order** (labels, CSS, header, controller, then view) and **rollback order** (view, controller, header, CSS, labels) are both safe at every intermediate step.
  - Old view with new controller: `column_left` is empty and the stock form renders.
  - New view with old controller cannot occur during apply.
- These are unchanged from R8: the double preflight, private linted restore candidates, lock, self-delete and fault restore.
- There is no SQL write and no customer read.

### 52 pins
- Later origins override earlier ones, so values from the fresh 33-file capture win.
- Pins taken only from WP1 or WP0 trees (the CI sources, `column_left`, the hub controller and template) are fail-closed: any drift is a STOP, never a pass.

### Owner packet
- It is the R8 protocol with a new task identity and a new state directory, `owner-receipts-main`. No intake state is imported.
- The `&&` cache sequencing, the one-use Apply and Rollback, the saved-PHP pin and the strict SSH options are all unchanged.
- Placing a comment before `#requires` and `param` is valid PowerShell.

### Tests
The boundaries are honestly stated: a native fragment, shell and Twig render; Chromium with the real `common.js` serializer; the current guard; and the actual file engine with only runtime admission replaced. Host PHP 8.0, MySQL, OPcache, the host Twig vendor version and an authenticated save remain owner Verify and QA items.

## Optional (non-blocking; any adoption requires r2 and a rebind)

- **O1. Focus indicator.** The `outline` uses `--bs-blue-soft`, which is about 1.15:1 on white and effectively invisible. Prefer `outline:2px solid var(--bs-blue)` per the board. The test's `outlineStyle!=='none'` check does not measure visibility.
- **O2. iOS zoom.** `font:inherit` on `.form-control` replaces Bootstrap's 1rem. If the body text is under 16 px, iOS Safari zooms on focus. Use `font-family:inherit` instead, or assert a computed size of at least 16 px at 390 px.
- **O3. Pressed state.** `.btn:first-child:active` (Bootstrap, 0,3,0) likely beats the scoped Save and Cancel backgrounds, giving a non-DS pressed color. The test checks text color only. Add scoped `:active` rules that use tokens.
- **O4. Mobile main column.** The mobile `.bs-account-main{display:flex}` relies on WP1's base rule for `flex-direction:column`. State it explicitly.
- **O5. Small fidelity points.**
  - The dot uses `--bs-warning-fg` (brown) where the board shows gold. `--bs-gold` is acceptable for a decorative, `aria-hidden` element.
  - There is no "необовʼязково" suffix and no `autocomplete` attributes (WCAG 1.3.5). The old form lacked both, so this is not a regression.
- **O6. Admission gaps.**
  - `account/shell.twig` is not in the alternate-template list. This was inherited from WP1.
  - `language/*` hooks are not classified (intake O4). The new labels could be overridden by such a hook; owner QA would surface that.
  - Non-uk-ua storefront languages would show raw `rd16_*` keys, the same as the WP1 hub.
- **O7. Cosmetic.**
  - The controller block is space-indented in a tab-indented file.
  - The runner comment still says "twelve added dependency hashes".
  - The `<title>` (`heading_title`) and the H1 ("Особисті дані") differ.
- **Carried forward:** R8 N1 (the `DIR_MODIFICATION` check is nominal), N2, N4 and N5, and intake O5–O7.

## Gates

1. **No owner Upload, Verify, Apply or Rollback until the owner explicitly grants the WP2 exception.** The scope Codex should present to the owner is:
   - the DS stylesheet `?v=` token in `common/header.twig` becomes `rd16-wp2-20261026`;
   - normalized-before `54879a99…` and after `66b626a4…`;
   - rollback restoring the backed-up token;
   - no other header byte changes.

   The HELD line is a procedural hold, not a mechanical one. Because granting the exception changes no bytes, the bound hashes survive. Codex must not hand over the packet before the exception is recorded.
2. **If the owner declines,** this pair is not releasable. The alternative is a separate edit-only stylesheet added through `document->addStyle`, with no header change, as a new reviewed revision.
3. **After the exception,** run Upload, then Verify, then Apply as separate owner actions with this pair only. On any STOP, preserve receipts and do not replay.
4. **WP2 owner QA per handoff §8 is mandatory:**
   - real save and reopen;
   - clearing the optional fields;
   - the Latin-name error;
   - 390, 768 and 1280 px, including the 991/992 boundary;
   - a single nav on edit;
   - the success notice;
   - the Tier 1 smoke URLs.

   RD-16 is not Done until it passes.
