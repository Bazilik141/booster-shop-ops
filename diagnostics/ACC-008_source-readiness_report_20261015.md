# ACC-008 WP2 — source readiness and bounded writer inventory

Date: 2026-10-15 (Europe/Kyiv)
Executor: Codex; owner explicitly assigned WP2. WP3 is not authorized to start.
Workspace: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`.
State: **WAIT_FRESH_WP2_SOURCE**. This is a preparation report, not G2 delivery or source acceptance.

## Outcome and blocker

Read the current root `AGENTS.md`, the current automatic independent review policy, both original and continuation handoffs, the accepted ACC-004 architecture basis, and the WP0 request/intake records. Preserve deployed and owner-accepted ACC-007. No ACC-008 patch has been generated against historical source.

The retained `booster-debug-files.tar.gz` is 249,855 bytes, SHA-256 `582dcba5debd9989212b1315ea89c112d2ec13fbaf532696df9f5bd9d449e682`: exactly the October 14 WP0 intake, not a new October 15 drop. Its local last-write timestamp is October 14 07:04:49 UTC. No new `ACC-008-current_20261015.tar.gz` was available at the readiness check. Filesystem dates and retained bytes do not prove current hosting source after intervening deployments. The October 9 full backup is not a freshness fallback.

The owner received the exact minimal WP2 archive command below. Continue safe independent preparation, but defer implementation and qualifying Claude CODE review until the new sources are validated. A premature review of old-source code would not satisfy the assigned stage.

## Historical-source writer inventory

These findings use the actual retained October 14 intake files at the source root recorded in the WP0 evidence JSON. They are verified historical source facts and require refresh against the new package. Line numbers refer to those retained bytes.

| Surface | Controller / caller | Actual writer and root cause |
| --- | --- | --- |
| Storefront profile | `catalog/controller/account/edit.php:99-188`, `save()`; authenticates through `account/login.validate`; active account fields are enumerated for the logged-in group | `catalog/model/account/customer.php:90-91`, `editCustomer()` replaces the complete JSON with the submitted value or empty string. Session custom fields are also assigned from the submitted value at controller line 173. |
| Storefront address | `catalog/controller/account/address.php:561-735`, `save()`; existing-address read is customer-scoped; NP payload is validated server-side before deriving printable text | `catalog/model/account/address.php:41`, `addAddress()`, and `:84`, `editAddress()` serialize the entire supplied JSON. Controller lines 588-605 coerce the submitted container to an array and use unrestricted `array_replace`; reserved/unknown client injection is possible in that merge. The existing malformed-value check operates on decoded data and needs comparison with raw stored JSON. |
| Admin customer | `adminEvhenii/controller/customer/customer.php:669-795`, `save()`; modify permission is checked; active account definitions use the submitted target group | `adminEvhenii/model/customer/customer.php:42`, `addCustomer()`, and `:80`, `editCustomer()` serialize the whole submitted array, defaulting to `[]`. `editCustomer()` can then separately change the password, so preservation errors must be detected before the first write. |
| Admin address | `adminEvhenii/controller/customer/address.php:196-317`, `save()`; customer existence and modify permission are checked; active address definitions use that customer's group | `adminEvhenii/model/customer/customer.php`, `addAddress()` and `editAddress()`; the retained `editAddress()` SQL at line 516 scopes its first update by address ID alone. Ownership must be verified before any write/default mutation. The admin form does not invoke the NP picker/validator. Keeping old NP refs after arbitrary printable-address changes would be inconsistent. |

Material read-only callers confirmed in the retained source:

- Registration calls `account/customer.addCustomer`; this form/auth path is protected.
- `catalog/controller/checkout/register.php:494-499` calls both `addCustomer()` and `editCustomer()`; lines 577-582 and 671-676 call address add/edit.
- `catalog/controller/checkout/payment_address.php:170` calls `account/address.addAddress()`.
- `catalog/controller/checkout/shipping_address.php:326` assigns server-validated NP metadata, then lines 408/421 call address edit/add. A shared-model change must preserve that trusted NP contract without trusting arbitrary client service keys. These checkout files were inspected read-only from the prior intake, not verified as current October 15 source.
- Registered editable field IDs, location, group, type and active state are obtained from the real storefront/admin custom-field models. Existing Twig form names use numeric `custom_field[id]`; checkbox arrays can be entirely omitted by browsers, so omission-versus-clearing needs explicit treatment rather than a blanket empty-array default.

The final patch target list is deliberately undecided until current source is available. Do not silently modify checkout/registration, NP business logic or shared callers to make a preservation fixture pass. If implementation would require a new protected source change, stop for the owner boundary instead.

## Required preservation design and tests

- Reload raw persisted JSON server-side immediately before an authorized update; distinguish malformed nonempty JSON from supported legacy empty shapes. Decode/encode errors must produce a safe response before profile/password/address/default writes.
- Derive the editable allowlist from actual active definitions for the form's location and customer group. Treat omitted input separately from explicit clearing; preserve unrelated registered IDs, unknown keys and nested values. Reject client attempts to overwrite `bs_first15_pending`, `bs_np_v1`, `rd13_receiver_middlename` and other uneditable keys.
- Preserve NP ownership: the existing server validator may replace NP metadata, whereas an ordinary profile/admin submission cannot forge it. Do not retain structured NP references with conflicting printable address text.
- Verify account and address ownership, group changes, session refresh, new and existing admin records, and errors without partial writes.
- Run fictional round trips through the real affected functions with a recording DB fixture. Tests must fail when preservation is removed. Include malformed JSON, scalar/nonempty containers, supported empties, nested unknowns, omitted/cleared fields, reserved-key injection, inactive/wrong-group/wrong-location IDs, cross-customer addresses, and validated NP versus text mismatch.
- Preserve the distinction between local source/fixture evidence, actual independent source review, owner host dry-run/apply, and owner production QA. No production/customer-data tests are authorized here.

## Task-isolated intake tool

`work/acc008-wp2-20261015/intake_sources.py` accepts exactly the 28 requested regular files, caps package/member/total sizes, rejects extra/missing/duplicate members, links, sparse files and path aliases, and validates the full package before writing source. It extracts into a new directory under this chat's authorized visualization scratch, outside the repository. It never executes/imports hosting PHP, Twig or JS. A new evidence JSON records current/baseline hashes and extraction root; existing intake evidence cannot be silently overwritten.

Preparation check: **10/10 synthetic intake security cases passed**, including valid package, missing member, traversal, absolute path, backslash path, credential path, case alias, symbolic link, hard link and duplicate. Python syntax passed; all 28 selected historical source files were rehashed and matched their recorded intake identities. These test the intake boundary only; no ACC-008 implementation behavior or PHP compatibility is claimed. Running intake without the new package correctly returns `WAIT_FRESH_SOURCE`.

## Fresh source command (owner)

In cPanel Terminal, copy the complete block. It reads source and creates an archive one level above the public web directory; it does not change application source or database contents.

```bash
cd ~/public_html || exit
tar -czf ../ACC-008-current_20261015.tar.gz \
  catalog/controller/account/{edit,address}.php \
  catalog/model/account/{customer,address,custom_field}.php \
  catalog/view/template/account/{edit,address,address_list,address_form}.twig \
  adminEvhenii/controller/customer/{customer,address}.php \
  adminEvhenii/model/customer/{customer,custom_field}.php \
  adminEvhenii/view/template/customer/{customer_form,address_form,address_list}.twig \
  extension/ukrainian/catalog/language/uk-ua/account/{edit,address}.php \
  extension/ukrainian/admin/language/uk-ua/customer/customer.php \
  system/framework.php \
  system/config/{default,catalog}.php \
  system/library/db/mysqli.php \
  system/library/template/twig.php \
  system/helper/{utf8,validation}.php \
  catalog/controller/startup/{startup,application}.php &&
echo 'archive_created=~/ACC-008-current_20261015.tar.gz'
```

Download to `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\ACC-008-current_20261015.tar.gz`. On an error, send the bounded error instead of a partial archive. The requested set is the 19 WP2 candidate files plus 9 material runtime dependencies. The three shared CSS files are excluded because this package does not propose a visual/CSS change. No root/admin credential config, logs, customer rows or SQL dumps are requested.

## Independent review and later gate

Read-only inspection confirmed the referenced subscription transport source still matches `f95f2edb0537a197cc191a4d19bbd98e23aac4808a02cfd6b1695220c3ffb78e`. This is adapter-source identity only: no ACC-008 launch, authentication check, entitlement probe or review verdict has occurred. The separate GA4 dialogue's adapter/packets/evidence were read as references and not modified. Build a fresh ACC-008 adapter/request after current implementation and meaningful checks are ready; reverify actual CLI/auth/controls and reject paid fallback/overage then. Required risky CODE review remains actual Claude Opus/high, automatic under the existing owner assignment.

Bounded current custom-field/event/theme metadata remains unverified. Select the smallest read-only hosting preflight once the patch ownership design is concrete; do not ingest private rows or an owner-held dump. A fresh owner-held DB dump is mandatory before applying ACC-008 even though its runner changes source only: source rollback cannot recover customer JSON lost in later application writes.

After a separately guarded PHP 8.0-compatible patch, real-function fictional fixtures and actual Claude review, deliver at G2 with dry-run/apply/cache/rollback and bounded owner QA. Then stop. WP3 requires explicit owner confirmation that ACC-008 is deployed and QA-passed plus permission to continue.

No source patch, hosting/DB mutation, commit/push/deploy, Notion/dashboard write, GA4/wishlist/media change or WP3 work was performed in this preparation stage. Existing unrelated working-tree changes were preserved.
