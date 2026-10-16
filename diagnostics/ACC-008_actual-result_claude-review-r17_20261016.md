# Independent Claude CLI review вЂ” ACC-008 WP2 actual-result assessment

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `dfbec3da8b8c2c45ba3e4f1e30cb2fdb7dc5931e1c4de7ee83193a6689296b0e`
Packet SHA-256: `6ef075c4ef7a5ca2c13011e40fe7c2aebc4ad853071e9ec61e5dc54822f76cf3`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent assessment of supplied actual evidence, not direct server execution or universal production acceptance.

---

# ACC-008 WP2, round 17: independent actual-result/evidence review

## Verdict: **Actual-result Review OK** (bounded)

**Mandatory findings:** none. The optional items in section 4 do not justify another round.

**What I assessed:**
- the complete r17 report and the reuse ledger;
- `prepare_review_r17.py`, the policy snapshot and the WP2/WP3 handoff;
- every sanitized host receipt: r11 apply, r13 dry-run and apply, r15 before and after, r16 dry-run and apply;
- the owner QA records for r11, r13 and r16;
- all public-smoke records: r11, r13 direct, r13 secondary channel, r13 owner-host, and the fresh post-r16 pass;
- the r11, r13, r15 and r16 CODE closures and full verdicts;
- the local result JSONs for r16, r13 and r15.

**Relied on by exact identity only:** the code bodies. They were accepted in prior CODE rounds and are not re-reviewed here. I recomputed no hashes and executed nothing.

**What this OK does not grant:**
- universal production correctness;
- Done/whole-project acceptance;
- Git, deploy, Notion, dashboard or status authority;
- any WP3 authority.

---

## 1. Why the composite evidence is sufficient

### 1.1 The deployment chain is coherent and corroborated by the host

| Stage | Host evidence |
|---|---|
| r11 | Applied at 12:09Z: 9 files, 5+5 native PHP 8.0.30 lints, private backup, template cache cleared. |
| r15 | Ran 18:22–18:26Z with `source_variant=r11`. This was correctly before r13. |
| r13 | Applied at 18:33Z: 2 files, `done=ok`, cache cleared. |
| r16 | Dry-run and apply at 19:14Z. |

The r16 admission is the strongest composition evidence in the chain:
- It passed the exact 103-source gate. That gate requires the r13 *after* identities.
- It also requires the unchanged r11 identities: the guard, the other three writer controllers and the Twig templates.
- So at 19:14Z the host independently attested that r13 was still installed and that the r11 preservation payload was byte-unchanged.

The nonpersonal metadata hash `d20615b5…` is identical across the r13 and r16 dry-run/apply pairs. The event, startup and theme inventories are stable.

### 1.2 Live preservation evidence is real but bounded

The r15 helper is CODE-accepted, SELECT-only and HMAC-only. Its after phase shows:
- `result=UNCHANGED`, `existing_keys=3`;
- `customer_name_changed=yes`, `address_rows_name_changed=3`;
- `bs_np_v1` preserved on 3 of 3 rows;
- `cleanup=ok`, `done=ok`.

The owner confirmed saving all four forms and all three addresses in both the storefront and the admin. This **resolves the r15 address-attribution caveat completely**: every keyholding row was renamed and kept its key.

That is genuine production evidence that the NP-owned service key survives all four writers on real rows.

### 1.3 The response-only follow-ups do not invalidate that evidence

- **r13:** confined to the `save()` success tail and the Twig banner. This was verified in its CODE round.
- **r16:** byte-reversal proof to the admitted baseline. Only the edit-branch response block changed. The guard, the merge, the commit order and the NP validators are untouched.

Retaining the r15 result by composition, rather than inventing a new snapshot, is correct.

### 1.4 Owner functional QA covers the requested behaviour

**r11:**
- profile and address recipient names stay independent;
- a temporary checkout recipient does not overwrite the permanent customer;
- admin edits persist and show on the storefront.

**r13:**
- a standard green alert with no cart UI;
- the alert disappears on reload;
- an inline empty-firstname error with no success alert;
- changed-email success.

**r16:** "QA-ok" on the requested scope:
- the same address form;
- the normal alert;
- the data and NP selection kept;
- the alert disappears on reload.

**Indirect proof of the raw redirect.** The owner landed on the same token-gated form (r13, r16), not the login page. If the redirect had been `&amp;`-escaped, the browser would see `amp;customer_token` and `login.validate` would redirect to login. This is reasonable indirect live evidence that the `raw=true` redirect works, even though the URL was not captured.

### 1.5 Public reachability is current

- **Post-r16 Codex pass:** all 8 Tier 1 URLs return final HTTP 200 with uncapped HTML and no basic PHP-error signature. This includes the current Charizard 3D URL.
- **Owner-host curl after r13:** 8 of 8 HTTP 200, exit 0.
- **Earlier Codex timeouts and tool 502s:** retained and correctly not explained.

Given two later successful routes, they are not a demonstrated defect.

---

## 2. Precise acceptance scope

This OK accepts **ACC-008 WP2 technical actual-result for G2 assessment** for:

**The deployed set:**
- r11 (`0b062801…`): guard plus the four authenticated writers;
- r13 (`b8758600…`): profile success confirmation;
- r16 (`dfbec3da…`): existing-address success confirmation.

**Data preservation on production:**
- server-side preservation of the **present** `bs_np_v1` key;
- covering three real address rows;
- across the owner-confirmed storefront profile, storefront address, admin customer and admin address saves;
- final-state equality only.

**Functional behaviour (owner QA):**
- name independence between profile and address;
- a checkout recipient does not overwrite the persistent customer;
- admin persistence;
- profile and existing-address saves show a standard same-form confirmation with no cart UI, disappearing on reload.

**Execution:** native PHP 8.0.30 lint and execution for all three runners.

**Unrepresented cases:** these rest on accepted fictional, adversarial and mutant evidence, not live proof:
- 164 real-function roundtrips;
- 14 + 12 confirmation cases;
- 71 admission cases;
- 8–9 lifecycle cases;
- the discriminating mutants.

---

## 3. Residual limits (not passed, not defects)

**1. Live responsive QA at 390/768/1280: DEFERRED by owner.**
- It is never passed and is not re-requested.
- The local profile fixture width checks remain separate. No local width fixture exists for the address alert.

**2. No live coverage for these keys:**
- customer-row keys (0 present);
- `bs_first15_pending`, `rd13_receiver_middlename` and unknown keys (absent);
- NP variants other than those present;
- intermediate per-save states;
- concurrency.

This is absence of live data, not a defect. Seeding stays prohibited.

**3. Not proven live:**
- cross-customer authorization refusal (covered by local fixtures and the guard);
- `customer_activity` rows;
- the admin NP warning;
- the shared checkout and First15 writers, which may still rewrite stale JSON;
- nonempty checkout, order, payment and NP API.

**4. Not separately reported after r16:**
- empty-firstname behaviour;
- raw URL syntax.

The validation path is byte-unchanged and covered locally. Note that 1.4 gives indirect raw-URL evidence.

**5. Not independently verified:**
- that runner self-deletion succeeded;
- that the owner-held DB dumps exist.

The `Url::link` body is still ungated.

**6. Rollback:**
- Rollback restores source only, in the order r16 → r13 → r11.
- It never recovers customer JSON.
- Never delete the guard manually.

---

## 4. Optional (retained, no dedicated round)

**Code-level items:**
- a future `system/library/url.php` identity gate;
- a stale one-use flash if the redirect is interrupted;
- delete and warning toasts remain cart-styled, including the existing-email red toast;
- the native address alert has no close button and no `role="status"`;
- extra r15 mutants, and the empty private directory r15 left behind.

**Owner hygiene:** at their convenience, the owner may confirm that no ACC-008 runner or helper files remain above `public_html`.

---

## 5. Next owner gate

**G2 decision (owner only).** Decide whether to record ACC-008 as live and QA-passed within the bounded scope above, with responsive QA recorded as deferred. Then decide whether to explicitly permit progression to **WP3a**: the read-only NP apostrophe and admin-autocomplete preflight.

Until that explicit permission, WP3 stays unstarted. This review writes no status.

**Retain:**
- the three backup directories;
- the owner-held dumps, per the owner's own retention.

---

### Коротко для власника
**Actual-result Review OK.** Обов'язкових виправлень немає.

Що підтверджено:
- три патчі розгорнуто з перевіреними SHA;
- r16 dry-run на хості підтвердив, що r11 і r13 стоять без змін;
- `bs_np_v1` зберігся на 3/3 адресах після всіх чотирьох форм;
- ваш QA для профілю й адреси позитивний;
- 8 публічних сторінок відповідають 200.

Що не перевірено наживо:
- адаптивна перевірка ширин — відкладена вами, не пройдена;
- ключі, яких на акаунті не було;
- чужа адреса;
- First15 і непорожній checkout.

Наступний крок за вами: зафіксувати G2 і окремо дозволити WP3a.
