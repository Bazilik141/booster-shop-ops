# Review verdict: **Changes requested**

**Bound artifacts (reviewed exactly as supplied):**

| Artifact | SHA-256 | Bytes |
|---|---|---|
| `diagnostics\RD-18-WP4_display-source_collect_20261028.sh` | `e7cd73a8f2a871bf6a535dc42757ef719d51542a616edc9831a5011c1bfa3cf4` | 19401 |
| `diagnostics\RD-18-WP4_display-source_owner_20261028.ps1` | `ebe73b2016a03dd038315752e1f3f9ab1e869606c6df93f88342e3f75058307e` | 7217 |

The packet pins `$CollectorSha` to the collector hash above. Both PS 5.1 and PS 7 result files bind the same pair. The report, test and result files were read as supporting evidence only. This verdict is not reusable for any other bytes.

**Scope:** This is a review of the source-only collector and owner command pair only. It does not certify host files, the actual schema, real language resolution, cache behaviour, production QA, or the future UI/payment-language implementation. That later stage needs its own final ready review, including UI signatures and breakpoints. I used no tools, ran no commands, acquired no native source and took no server action.

---

## Mandatory finding (blocks owner release)

### M1. The address-format flags cannot tell an empty format from a format without `{address_1}`

**Problem**

- `rd18v_order_shape` exports only `format_has_address1` and `format_has_address2`.
- Both flags are `false` for `''` and for a city-only format such as `{city}`. The two cases look identical.
- In stock OpenCart, an empty `shipping_address_format` is common. Many countries ship with an empty `address_format`, and Ukraine may be one of them. Stock OpenCart then falls back to a default format that includes `{address_1}`.
- The report's stated purpose is to tell a "city-only format" apart from an "incomplete snapshot". With the current flags, an empty stored format would be misread as a city-only format. That could steer the future formatter fix in the wrong direction.
- The packet is single-run with no replay. Fixing this afterwards would need a new revision, a new review and a second owner run.
- The test fixture also cannot catch this. It only uses `'{city}'` and asserts `format_has_address1 === false`.

**Closure**

1. Add `format_nonempty` to the exported shape, computed as `is_string($format) && trim($format) !== ''`. Optionally also add `format_has_city`. Keep every value boolean.
2. Add discriminating fixture cases for `''`, `null` and `'{city}'` in `test-source-metadata.php`. Re-run the 11+ PHP cases and the extraction-equality check.
3. Re-run `check-collector.sh` and the PS 5.1 and PS 7 packet tests.
4. Re-pin the new collector hash in the packet. This also changes the packet hash.
5. Re-submit both new hashes for a fresh review.

Nothing has run on the server, so keeping the `r1` artifact names is acceptable.

---

## Optional findings (recommended; fold into the M1 revision if cheap)

**O1. The extension language namespace registration is neither collected nor hashed.**
`Language::load` resolves `extension/opencart/...` through `$this->path[namespace]`. That path is registered by an `addPath()` call in a startup controller. In OC 4.0.2.x this is likely `catalog/controller/startup/extension.php`; confirm the exact file locally against the version. Neither that controller nor the catalog pre-action configuration is pinned. Without it, "which file owns `text_payment`" rests on an upstream assumption.
*Closure:* add hash-only pins (or fixed-path source) for that registration controller, and update the report.

**O2. Discovery also picks up admin language files.**
The pattern `*/language/uk-ua/*` under `extension` also matches `extension/*/admin/language/uk-ua/.../bank_transfer.php`. These files are out of scope and use up the cap of 8.
*Closure:* restrict discovery to catalog trees, or record the admin matches as accepted.

**O3. The IBAN screen only catches unspaced UA IBANs.**
A formatted IBAN such as `UA21 3223 …` passes. The report already says the screen is conservative and that local inspection is required.
*Closure:* optionally widen the pattern to something like `UA[0-9]{2}([ ]?[0-9]){25}`. Keep the local pre-transfer inspection rule either way.

**O4. The cleanup script relies on errexit for bare `[[ … ]]` checks.**
This works on modern bash. Explicit handling is more robust.
*Closure:* add `|| exit 1` to each check, as the collector already does with `|| stop`.

**O5. `$st->fetch()` truncation is not handled.**
A `MYSQLI_DATA_TRUNCATED` return is truthy and would be treated as found. This is unlikely given the field-length buffers.
*Closure:* treat any result other than `true` or `null` as `shape_fetch`.

**O6. Some code paths have no test coverage.**
- The PHP main block (runtime/config/root checks, PHP-side order-argument check, JSON assembly) is untested. The Bash tests use a fake PHP.
- The real `Invoke-Rd18LfProcess` transport is replaced in every test.
- The generic private-key and `sk-*` signature screens are untested.
- The packet's failure branches are untested: receipt mismatch, archive/manifest hash mismatch, manifest-line mismatch and cleanup failure.

*Closure:* add the cheap ones, or record each as NOT_TESTED.

**O7. Some partial-failure and provenance details are unrecorded.**
- Some STOPs leave a remote archive behind, for example after tar or on drift. The report should say this needs a separately reviewed cleanup.
- The test writes `packet-results.json`, but the evidence shows `packet-PS51-results.json` and `packet-PS7-results.json`. The rename step (`run-source-checks.py`) is supplied as a hash only.
- There is cosmetic leftover code: stray indentation after `$Match`, `Rd18Photo*` naming, and the `image_root_is_expected` field.

---

## Areas reviewed with no defect found

**Read-only SQL**
- `DB_PREFIX` is guarded and both tables are schema-checked.
- Queries run inside `START TRANSACTION READ ONLY`, with `rollback` in `finally`.
- The order query is a single prepared SELECT of exactly four fields with `LIMIT 1`, using `bind_param('i')` and `bind_result`. It does not depend on mysqlnd.
- `DB_PORT` is passed as an integer.
- No ID, name, phone, comment, payment or total values are selected or exported.

**Event metadata**
- Only enabled trigger/action pairs are read, with caps of 1024 rows and 64 relevant rows.
- The new bank-language before/after edges are classified as protected. The relevant-cap test proves this.
- Callbacks are classified only and never invoked. Unknown callbacks are never admitted.

**Filesystem**
- Webroot and private directories are checked for links. Directories are created with mode 700 under umask 077.
- Noclobber (`set -C`) and the pre-existence check prevent replay.
- Every path component of every source file is checked for links.
- Size caps are enforced: 2 MiB per file and 8 MiB total. The discovery path list is capped at 4 KiB, and the file count is limited to 3–11.
- A `find` or `sort` failure fails closed under pipefail.

**Integrity**
- Source files are hashed before and after collection, and each archived member is hashed and checked.
- The archive hash is recorded in the manifest. The receipt pins the manifest hash.
- The owner packet checks the regex receipt, both download hashes and the manifest cross-check before cleanup.
- Cleanup removes exactly two non-recursive files and is bound to their hashes. Any failure preserves the artifacts.

**Packet**
- The collector hash is checked before use.
- `OrderId` is a strict range-checked integer. It is the only substitution, and it is re-validated in both Bash and PHP.
- Native arguments are whitelisted and stdin is written as exact LF bytes.
- SSH and SCP use strict host-key checking, public key only, no forwarding, no password and no agent key-add.
- Only the owner executes the packet.

**Test fidelity**
- The PHP tests run the exact embedded functions and check that the extracted code equals the embedded code.
- The Bash stderr fixtures match the expected STOP reasons.
- The Unix symlink case is honestly recorded as NOT_TESTED.
- The PS mocks replace both transports before invocation, and the results bind the exact pair.

**Release state:** Hold the owner run (`-OrderId 462`) until M1 is closed, a new collector/packet hash pair exists and that pair has a fresh review.
