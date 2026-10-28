# Review verdict: **Review OK**

## Bound artifacts

This verdict applies only to these exact bytes. It cannot be reused for any other revision.

| Artifact | SHA-256 | Bytes |
|---|---|---|
| `diagnostics\RD-18-WP4_display-source_collect_20261028.sh` | `b47cc0be5e9f9d8bfd71df8fc83012c373c3814552bb25308631e9d58a43b0f4` | 19898 |
| `diagnostics\RD-18-WP4_display-source_owner_20261028.ps1` | `1881285ee80e8d888a2407e4ca01d83036c0120da5ca6f3bd45a0d5cb76c580f` | 7268 |

**How the binding was checked**
- I used no tools, so I did not recompute these hashes. They are bound as supplied.
- The packet's `$CollectorSha` equals the collector hash above.
- The PS 5.1 and PS 7 packet results both bind the same pair.
- The PS 5.1 and PS 7 negative results bind the same packet hash.
- `source-facts.json` binds both hashes.
- The report, test, result and fixture files were read as supporting evidence only.

**Scope**
- Covered: the source-only collector and owner command pair.
- Not covered: host files, the actual schema, real language resolution, cache behaviour, production QA, or the later UI/payment-language implementation. That stage needs its own final ready review.
- I took no server, SSH or key action.

---

## Mandatory findings

**None.**

### M1 (R1) is closed

1. **Code.** `format_nonempty` is computed as `is_string && trim !== ''` through `$present`. `format_has_city` is also added. All exported values remain boolean.
2. **Fixtures.** The four format cases discriminate correctly:

   | Case | `format_nonempty` | `format_has_city` | `format_has_address1` / `format_has_address2` |
   |---|---|---|---|
   | `''` | false | false | false |
   | `null` | false | false | false |
   | `{city}` | true | true | false |
   | `{city}, {address_1}, {address_2}` | true | true | true |

   The happy-path cases still assert `format_has_address1 === false` for the default `{city}`.
3. **Extraction equality.** The tested functions are checked to be byte-equal to the embedded heredoc.
4. **Re-runs.** There are 17 PHP cases and 14 real Bash cases. `linked` is honestly marked NOT_TESTED. The PS 5.1 and PS 7 happy-path and negative runs are against the re-pinned pair.
5. **Re-pin.** Both hashes changed and were re-pinned in the packet.

### R1 optional findings, verified in code

| R1 item | Closure confirmed in code |
|---|---|
| **O1** | `catalog/controller/startup/extension.php` is now a required source, so it fails closed if missing. The optional `event/language.php` and `system/config/catalog.php` are link-checked and then component-walked. The report correctly refuses to claim startup order is verified if the optional pre-action source is absent. |
| **O2** | Both `find -path` and the post-filter require the storefront path `catalog/language/uk-ua`. The fixture contains an admin decoy and asserts that no `/admin/` member is archived. The file range is 3 required + 0–2 optional + 1–8 discovered = 4..13, which matches the receipt regex `(?:[4-9]\|1[0-3])`. |
| **O3** | The pattern `UA[0-9]{2}([[:space:]]?[0-9]){25}` catches both grouped spacing (4 and 5) and unspaced IBANs. The spaced fixture stops as expected. |
| **O4** | Every cleanup `[[ … ]]` check now ends in `\|\| exit 1`. A bare `cd` under errexit is acceptable. |
| **O5** | Only `true` or `null` from `fetch` is accepted, and `errno` is checked. The `1` and `false` fixtures both stop with `shape_fetch`. The statement closes in `finally`. |
| **O6** | The private-key, `sk-proj` and six packet failure branches are now tested. The expected call counts are correct: 0 for the collector-hash case, 1 for receipt/archive/manifest/line, and 2 for cleanup. Remaining gaps are recorded as NOT_TESTED. |
| **O7** | The `run-source-checks.py` body shows the per-version copying. Partial-artifact recovery requires a separately reviewed packet with no replay. Cosmetic leftovers are retained and do not affect semantics. |

### Re-verified with no defect found
- **Read-only database access**
  - Two schema checks run before any query.
  - Reads run inside `START TRANSACTION READ ONLY`, with rollback in `finally`.
  - There is one prepared four-field `LIMIT 1` query using `bind_param('i')` and `bind_result`, with no mysqlnd dependency.
  - No ID or field values are exported. `np_type` is exported only when it is a known value.
- **Event metadata**
  - Caps are 1024 rows and 64 relevant rows.
  - The two bank-language edges are protected and never admitted.
- **Single owner argument**
  - It is the only substitution.
  - It is re-validated in both Bash and PHP.
- **Filesystem and integrity guards**
  - Noclobber with a pre-existence check prevents replay.
  - Every path component is checked for links.
  - Size caps are enforced and discovery fails closed under pipefail.
  - Source files are hashed before and after collection, and archived members are hashed.
  - The packet verifies the receipt, archive and manifest before an exact two-file, hash-bound cleanup.
- **Transport**
  - Native arguments are whitelisted and stdin is written as exact LF bytes.
  - SSH uses strict host-key checking with public-key authentication only.
  - stdout is read asynchronously before stdin is written.

---

## Optional findings (non-blocking)

**N1. Some test evidence is not hash-bound.**
- `metadata-results.json` and the Bash fixture outputs do not record the collector SHA. The PHP equality check runs at runtime, but the result is not written into the evidence.
- This is not blocking:
  - The `optional_sources`, `spaced_financial` and `format_*` cases can only pass on post-repair code.
  - The PS results bind the pair.
- **Recommendation:** record the collector hash in future result files.

**N2. The negative packet test has no root-locator guard.**
- `test-packet-failures.ps1` lacks the `Contains($RootLine)` guard that the happy-path test has.
- The passing `collector_hash` case proves the substitution took effect, so this is not blocking.
- The packet refuses to run if a real drop directory exists, which is safe either way.

**N3. The privacy screen has known gaps.**
- Card numbers, EDRPOU codes and recipient names in a customized UA `bank_transfer.php` would not be caught.
- The archive only reaches the owner's machine.
- **Keep the local-inspection rule** before any external transfer. Optionally add a card-number screen in a later revision.

**N4. `trim` differs slightly from native OpenCart truthiness.**
- Native OpenCart uses `if ($format)`.
- A whitespace-only format, or the string `"0"`, would be classified differently.
- This is negligible, but keep it in mind when interpreting the result.

**N5. `find` does not follow symlinked directories.**
- Without `-L`, `find` does not descend symlinked directories.
- A translation file behind such a directory would be missed silently. A wholly missed set would still stop with `bank_language_source_missing`.
- Treat the discovered list as bounded evidence, not proof of completeness.

---

## Release state

This exact pair may go to a single owner run (`-OrderId 462`), with the expected result `RD18_WP4_SOURCE_READY`.

- On any STOP, preserve the receipt and artifacts and do not retry.
- The owner transport, runtime and QA gates remain open.
- Collected source must be inspected locally before any later transfer.
- The future UI/payment-language implementation requires its own final ready review.
