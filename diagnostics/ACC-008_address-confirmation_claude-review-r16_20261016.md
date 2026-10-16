# Independent Claude CLI review вЂ” ACC-008 address confirmation

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `dfbec3da8b8c2c45ba3e4f1e30cb2fdb7dc5931e1c4de7ee83193a6689296b0e`
Packet SHA-256: `450567b441473da8a12f6a00fbc8704f836c8378f616a269abf255dccff5e15e`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# ACC-008 round 16: one-controller address-edit confirmation, independent CODE/composition review

## Verdict: **Source Review OK**

**What this OK covers.** Only the CODE of this artifact:

- **Patch:** `patches/ACC-008_address-confirmation-r16_20261016.php`
- **SHA-256:** `dfbec3da8b8c2c45ba3e4f1e30cb2fdb7dc5931e1c4de7ee83193a6689296b0e`. This is the value declared in `build.json`, `local-checks.json` and the report. I could not recompute it.
- **Target:** exactly one file, `catalog/controller/account/address.php`.
- **Before identity:** `54e273a2…`, the r11 identity.
- **Context:** the r16 103-source admission, which advances only the two owner-applied profile-r13 identities.

**What this OK does not cover:**

- host execution, apply or owner QA;
- final actual-result review, G2 or production acceptance;
- WP3.

**Mandatory findings:** none. The optional items below do not justify a separate provider round.

**What I read in full:**

- the generated runner;
- the complete generated controller as rendered;
- the unchanged `address_form.twig`;
- `build_followup.py`, `confirmation_tests.php`, `roundtrip_tests.php`, `render_bootstrap.php`, `run_checks.py`, `preflight_contract_tests.php` and `prepare_review_r16.py`;
- all result JSONs;
- the r16 and actual-result reports;
- the r13 and r15 verdicts and closures;
- the r11 closure and owner QA;
- the r13 dry-run and apply receipts;
- the r15 before and after receipts;
- both public-status records.

**What I relied on by exact identity only:**

- the r13 runner, `runtime_admission`, `edit.php` and `edit.twig`;
- `common.js`;
- the r11 sources;
- the guard;
- the payload and manifest JSON files.

The runner bodies that actually execute are rendered inside the patch, and I reviewed them there.

---

## 1. Correctness of the change

**The changed block.** Inside the edit branch, this:

```php
$json['success'] = $this->language->get('text_edit');
```

becomes:

```php
$this->session->data['success'] = $this->language->get('text_edit');
$json['redirect'] = $this->url->link('account/address.form', 'language=' . … . '&customer_token=' . $this->session->data['customer_token'] . '&address_id=' . (int)$this->request->get['address_id'], true);
```

**Confinement.** The builder asserts that the old line occurs exactly once. It also asserts that mechanically reversing the change reproduces the admitted baseline byte for byte. The change is therefore confined to this block.

**Ordering is preserved and correct.** The sequence is:

1. `editAddress`
2. `$acc008->finish(true)` (commit)
3. shipping and payment session refresh and invalidation
4. the flash and redirect

If the commit throws, steps 3 and 4 never run, so there is no flash. The trailing `finish(!isset($json['error']))` and the `finally rollback()` are unchanged from the baseline.

**The flash appears only on a successful save.** All failure paths leave `$json` non-empty before the write block, so the edit branch is never reached. These paths are:

- validation;
- unauthorised (`login.validate`);
- `address_id < 1`, which raises an exception;
- cross-customer access, through the guard's `address()` exception;
- invalid or stale NP data;
- the default-address warning.

**No cart toast.** The edit response no longer carries a `success` key. `common.js` therefore takes only its generic `json.redirect` path. That path is gated by `58605f6d…` and is the same one r13 uses.

**Redirect targeting:**

- **Route:** `account/address.form`, the same form.
- **Address:** the integer `address_id` that was just saved. It was already validated as at least 1, and ownership was enforced by the guard and the `getAddress(customer_id, …)` call.
- **Token:** the session `customer_token`, the same value `login.validate` checks.
- **Language:** `config_language`.

**The `raw=true` contract has three independent proofs:**

- the add-address branch in this same controller already emits `link(…, true)` JSON redirects;
- r13 `edit.php` does the same, and the owner verified it on the host;
- `login.php` uses the same pattern, and its identity is gated.

`system/library/url.php` is still not gated (see optional item 1). The equivalence argument holds regardless of that.

**The flash is consumed once:**

- **Display:** `form()` reads `session.success`, removes it, and passes it to the existing `{% if success %}` alert in `address_form.twig`. The template identity `a08cab1d…` is unchanged.
- **Reload:** reloading is a plain GET, so there is no resubmit prompt, and the alert is gone.
- **Message text:** `text_edit` is the same key the baseline toast used. The owner's screenshot already showed the localized address-updated text, so the language key exists in production.

**Repeat submit:**

- A double click before the redirect produces two edits. Both are idempotent. Each holds its own transaction and row lock and writes the same values.
- Both responses set the same single session key, so at most one alert appears.
- The worst case is a stale flash (optional item 2). There is no data hazard.

**Other paths are unchanged by byte equality:**

- add address, including registration-required and First15 with or without a cart;
- delete;
- `npMetadata`, `form()`, `index()` and `list()`;
- all NP validators;
- the guard calls;
- `custom_field` merging;
- the other three controllers;
- all Twig, JS and CSS.

**Template cache cleanup** is correctly not needed, because no Twig file changes.

## 2. Runner, admission and lifecycle

**Runner diff.** The runner equals the r13 runner except for the filename, backup prefix, marker and title. `prepare_review_r16.py` asserts this. `runtime_admission` is byte-identical to the accepted r13 version.

**Pre-write gates.** Before any write, the runner checks:

- all 103 source identities, with the r13 `edit.php`/`edit.twig` "after" identities required;
- that no generated OCMOD is present;
- literal config parsing;
- a non-persistent, single DB target;
- path mapping;
- SELECT-only metadata, events, startups, themes and engines.

Any drift stops the run with `runtime_source_changed`, or the matching error code. There is no bypass.

**Marker gate.** The payload must contain `ACC-008-ADDRESS-CONFIRMATION`, and the target must contain zero occurrences before apply. The r11 address controller does not contain this marker, so the gate is sound.

**Write path.** Writing to a single file uses:

- a private backup outside `public_html`, with a hash-verified copy;
- a manifest;
- a staged temp file in the same directory;
- prewrite lint and a re-check of the source hash;
- an atomic rename;
- post-write lint;
- restore-on-fail;
- idempotent `already_applied`;
- self-delete.

**Rollback:**

- The manifest contains only `address.php`, so the guard-orphan check is correctly not triggered.
- Rollback restores `54e273a2…` and leaves r13 installed.
- The series order r16 → r13 → r11 is enforced fail-closed. While r16 is applied, the r11 rollback would refuse `address.php` with `rollback_target_changed`.

**Lifecycle tests.** All 8 cases discriminate:

- dry-run makes no write;
- apply refuses without the confirmation flag;
- the backup is private;
- repeat runs are idempotent;
- rollback restores the exact bytes;
- drift is refused;
- the host lint refuses before any write;
- staged-lint failure restores the source.

## 3. Test adequacy

**Confirmation cases.** The 14 cases run the actual generated `save()` and `form()` and the real Twig 3.18. They use a URL stub that escapes `&amp;` unless `raw=true` is passed.

**Mutants.** Three mutants each fail on the intended assertion:

| Mutant | First failing assertion |
|---|---|
| restoring `json.success` | `no_cart_success` |
| removing `raw=true` | `raw_same_form_token_and_address` |
| redirecting to the list | `raw_same_form_token_and_address` |

**Ordering.** Moving the flash before the commit would be caught by `commit_failure_no_flash`.

**Regression suites.** The 164 roundtrip cases and 71 admission cases still pass. The only fixture change is a fictional `getZonesByCountryId`. The adaptation of the initial preflight fixture is disclosed, and its failure output is retained.

**Not exercised:** the non-empty-cart First15 branch is untouched. That rests on byte equality, which is acceptable.

## 4. Composition and evidence limits

**R13 is applied.** The receipt shows 18:33:36Z, the exact SHA, two files, lint, `done=ok` and the cache cleared. The owner's QA was positive. The exact raw URL and the reload timing were not captured.

**R11 owner QA and the r15 comparison are retained:**

- The r15 comparison is a final-state equality after the four confirmed forms.
- `bs_np_v1` was preserved on 3 of 3 addresses.
- It does not cover customer keys (0 present), keys that were absent, or individual intermediate writes.
- It ran before r13 and r16. Neither touches the data writer or the guard, and that is the composition basis.

**Owner-host curl:** 8 of 8 final 200, exit 0, with bodies discarded. This is reachability only. The earlier Codex timeouts and tool fetch failures are retained, with cause unknown.

**Not proven:**

- native execution of r16;
- the browser JS redirect for this route;
- live NP behaviour;
- InnoDB behaviour under concurrency;
- the body of `Url::link`.

**Live responsive QA (390/768/1280):** *deferred by owner*. It is not passed, and it is not requested here.

---

## Optional findings (no separate round)

1. **Gate `system/library/url.php`** in a future identity set. This carries over from r13.
2. **Stale flash.** If the JS redirect is interrupted, for example by navigating away, closing the tab or using two tabs, the next account page that consumes `session.success` could show the address-updated text there. This is the same class of issue as r13 and is low impact.
3. **Delete and warning toasts.** Delete still returns `json.success`, which renders the cart-styled toast. Error and warning toasts (default-address, guard and NP warnings) also use the old global handler. Both are outside the requested scope and are known limitations. Fixing delete would need a different design.
4. **Accessibility markup.** The alert has the `alert-dismissible` class but no close button, and no `role="status"`. The template is unchanged; this is cosmetic.
5. **Typo.** `build.json` has a typo in its basis text: "at2026…".

---

## Owner gate (this SHA only; owner alone executes)

**Before you start:**

- Take a fresh DB dump and keep it yourself. Do not send it.
- Upload the runner to the folder above `public_html` under exactly the name `ACC-008_address-confirmation-r16_20261016.php`.
- Save the full output and return it.

**One chained block. It has no Twig cache step.**

```bash
cd ~/public_html && \
echo "dfbec3da8b8c2c45ba3e4f1e30cb2fdb7dc5931e1c4de7ee83193a6689296b0e  ../ACC-008_address-confirmation-r16_20261016.php" | sha256sum -c - && \
php ../ACC-008_address-confirmation-r16_20261016.php --dry-run && \
php ../ACC-008_address-confirmation-r16_20261016.php --db-backup-confirmed
```

**Expected dry-run output.** The known `MYSQL_OPT_RECONNECT` deprecation warning is acceptable. Then:

- `host_payload_lint=catalog/controller/account/address.php:ok`
- `runtime_sources=exact_reviewed_sources_and_no_generated_ocmod`
- both cache mappings `=../ocartdata/storage/cache`
- `config_admission=literal_nonpersistent_same_target_and_paths`
- `event_rows_scanned=112`
- `active_startups=[]`
- `metadata=…`
- `db_preflight=read_only_ok`
- `dry_run=ok`
- `files_pending=1`
- `done=ok`

**Expected apply output.** The same admission lines appear again, then:

- `backup=../_patch_backups/ACC-008_address-confirmation_<stamp>-<hex>` (keep this name)
- `backup_file=catalog/controller/account/address.php`
- `php_lint=catalog/controller/account/address.php:ok`
- `changed_file=catalog/controller/account/address.php`
- `files_changed=1`
- `done=ok`

**If anything else appears:** any `error=` line, a non-zero exit, `files_pending` other than 1, or any other unexpected line means stop and return the output. Do not retry, edit or bypass.

In particular, `runtime_source_changed:*` means the host no longer matches the r13 state. That is a reassessment item.

**Rollback (source only):**

1. Re-upload the same runner.
2. Run:
   ```bash
   cd ~/public_html && php ../ACC-008_address-confirmation-r16_20261016.php --rollback=<printed basename>
   ```
3. Expect `rollback=source_only_ok` and `done=ok`.

This restores the r11 address controller, which brings back the old toast. R13 stays installed. Rollback does not restore data and needs no cache step. For the whole series, roll back in the order r16, then r13, then r11. Never delete the guard manually.

**Focused QA (owner, own or test account, existing saved address):**

1. **Edit and save.** Change only the recipient name, keep the NP selection, and save. You should land on the same address's edit form; the URL contains `customer_token` and that `address_id` with no `amp;`, and you are not on the login page. A standard green address-updated alert appears, with no cart heading or buttons. The fields show the saved values, and the NP selection is still present.
2. **Reload the page.** The alert is gone and the form remains.
3. **Leave the first name empty and save.** An inline error appears, with no redirect and no green alert.
4. **Optional: add a new address.** The existing flow should still behave as before.

Delete and warning toasts are known to remain cart-styled.

If the first save after apply still shows the cart toast, wait a few seconds and repeat once, to allow OPcache timestamp revalidation. Then report the result.

**Open after this stage:**

- the r16 apply receipt and QA;
- the separate coherent actual-result/G2 review;
- live responsive QA, which stays recorded as deferred by owner.

WP3 stays closed.
