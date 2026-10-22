# Integrated final CODE/composition review: ACC-009-NP-TRANSPORT diagnostic and owner packet

**Verdict: CHANGES_REQUIRED** (one narrow mandatory finding)

**Bound identities**, as declared in release-facts.json (sha256 4b70b3ce…a9). I did not recompute them because no tools were used.

| Artifact | SHA256 | Bytes |
|---|---|---|
| `diagnostics\ACC-009_NP-transport-inspect_20261021.php` | `996795b304f4e424beb36445868c4af62c4ed82851a3dadf825c4af148ed4dbb` | 15449 |
| `diagnostics\ACC-009_NP-transport_owner-PS_20261021.ps1` | `d4e117f1fb018ba2d6e496fc2c7d0e46863001c7d0904519f72eb8d4d58074d4` | — |

**Internal consistency:**
- The PS `$Sha`/`$Bytes`, the embedded `pkt_diagnostic_sha256`, the `php -r` arguments and `parse-capture.sh` all carry the same PHP SHA and size.
- The source pins in the PHP and in `Test-CaptureReceipt` match each other.
- The pin `2138e156…7b93` matches `sha256_after` in the N3 Apply receipt.
- The plaintext helper block in the PHP is textually identical to `collector_helpers.php.txt`. The embedded base64 copy is checked at runtime against `ecd6a39c…feab` before use.

This review is invalidated by any change to either bound file.

---

## Mandatory finding

### M1: Cleanup failure is misclassified and the leftover probe name is lost

**Location:** `npd_web` `finally` block, together with the CLI error handler.

**Mechanism:**
1. The CLI entry installs `set_error_handler(...)`. It throws `runtime_warning_details_withheld` for every warning that `error_reporting()` includes, and E_WARNING is included under any normal production ini.
2. In the `finally` block, a failing `unlink($path)` emits an E_WARNING, for example permission denied or a race. A failing `hash_file()` inside the ownership guard does the same.
3. That warning becomes a `RuntimeException` before `npd_fail('probe_cleanup_failed:'.$name)` (or the `probe_changed_do_not_delete` branch) can run.
4. The outer catch only maps messages with the `probe_cleanup_failed:` or `probe_changed_do_not_delete:` prefixes to `STOP_manual_assessment`.
5. Result: a probe that may still exist in the production webroot is reported as `probe_cleanup: "completed_or_not_created"`, and its basename is never output.

**Impact:**
- After 120 s the probe returns only 404 and contains no secrets, so direct exposure is low.
- However, the receipt's core temporary-endpoint claim becomes false. The assessor loses the exact name needed to find and remove the file.
- Exit 2 still causes `PARTIAL_STOP`, but the saved evidence would actively mislead the manual assessment.

**Closure conditions (all required):**
1. Any warning, error or exception that arises during the cleanup decision (stat, hash or unlink) produces `probe_cleanup = STOP_manual_assessment` and outputs the exact probe basename.
2. `completed_or_not_created` (and `ok`) appear only when:
   - the probe was never created, or
   - absence was confirmed after an owned-byte unlink.
3. The original pre-cleanup failure reason is preserved alongside the cleanup failure, or at minimum is not replaced by a generic withheld message.
4. A local fixture test injects an unlink failure and a hash-read failure in cleanup. It asserts the STOP classification, that the basename is present, and that no other output changes.
5. Both artifacts are regenerated and `release-facts.json` is rebound to the new PHP and PS SHA256s.

A scoped confirmation of only this cleanup and classification delta, plus the hash rebind, closes M1. A full re-review is not needed.

---

## Optional findings (non-blocking)

- **O1: Strict web runtime assertion can discard the most relevant data.**
  - `npd_assert_runtime($j['runtime'], true)` requires sapi `litespeed` and PHP `8.0.x`. Any mismatch throws, and the web samples are dropped entirely.
  - This is a one-shot, no-replay Capture, and web PHP can differ from CLI PHP (for example a MultiPHP handler). If the web SAPI and version are not already pinned by retained N3 host evidence, consider keeping shape-valid web samples and reporting the mismatch as a field.

- **O2: The full transport path was never exercised natively.**
  - The chain bash → `php -r` → `eval(substr($b,5))` was only syntax-checked. The fixture ran the PHP file directly.
  - A loopback test through the actual `php -r` verifier and eval wrapper would close this small gap: eval-scope constants and functions, exit codes, and `$http_response_header` in function scope.

- **O3: `remoteScriptSha256` is recorded but not compared.**
  - The expected value is deterministic: it should equal the `parse-capture.sh` SHA `8a8fbd89…1369`.
  - An equality field in the record would let byte-identical streaming be checked mechanically instead of by hand.

- **O4: An SSH-level failure consumes the single Capture.**
  - Examples: key not unlocked, or a host-key problem. Both yield `STATE_UNKNOWN` with no remote action, and the receipt blocks any rerun.
  - This is acceptable under the no-replay policy. A one-line owner note would help: remote exit 255 with empty stdout means "no remote action" and is a named missing fact for assessment.

- **O5: Transport fidelity limits should be stated in the interpretation.**
  - The diagnostic forces HTTP/1.1, while the module likely uses the libcurl default (possibly HTTP/2 via ALPN).
  - Unless shown to match, the module's own verify, proxy and HTTP-version options are unconfirmed.

---

## Assessments

| Area | Assessment |
|---|---|
| **Privacy** | Adequate. No app bootstrap, config, DB, `.ssh` or credential reads. Output is limited to versions, errno/class, HTTP code, `ssl_verify_result`, validated peer IP and timings. No raw `curl_error`. Provider body and headers are discarded by callbacks. Version strings are bounded at 160 chars and control characters are rejected. Exception messages could at most reveal known host paths. |
| **Authority** | Preserved. Owner-only packet. No Unlock/Lock or key inspection. BatchMode with publickey only. No forwarding or password fallback. No SCP/uploader. No Git, status or deploy grants implied. |
| **No business effects** | Correct. Six fixed-URL HEAD requests with no key, body or cookies. `FOLLOWLOCATION` off. Verification stays ON (`VERIFYPEER` true, `VERIFYHOST` 2). No POST and no contact, address or TTN path. |
| **Temporary endpoint** | Sound: `x`-mode create, 128-bit random name and nonce, GET only, `hash_equals`, 120 s TTL guard before any helper execution, owned-byte hash check before delete, symlink check. **Except M1.** |
| **Cleanup** | Correct on the normal and transport-failure paths, which are fixture-tested. Misreported on warning-raising cleanup failures (M1). |
| **Streaming** | Sound. Quoted heredoc. The base64 alphabet cannot collide with the delimiter or the `__X__` placeholders. Strict decode, length, SHA and `<?php` prefix are checked before eval. `main </dev/null` prevents stdin consumption. A ~21 KB argv is within limits. Worst-case runtime is about 110 s, under the 180 s kill. |
| **Receipt** | Strict six-line contract. Exact source pins. Each sample is checked for HEAD method, labels and int types. Curl failures do not reject a receipt, which is correct because failures are data. `STARTED` is saved before SSH, and every stdout, stderr and exit outcome is retained. `OBSERVED` is explicitly not integration acceptance. |
| **Fake boundary** | Honest. The fixture adapts only the Windows root and path, the loopback URLs and the `cli-server` SAPI. Real libcurl results were exercised: 405, TLS35 and refused connection. No synthetic remote TLS success. The receipt fixture changes only the SAPI name. Production `npd_abs` and the eval path were not natively exercised (O2). |

---

## Limits of this review

- **Static only.** I did not execute anything, open any network connection or verify any hash.
- **No claims about the runtime environment:**
  - whether the hosting web SAPI is LiteSpeed;
  - whether `boostershop.website` serves this webroot without a CDN challenge or redirect;
  - whether `curl_exec` is enabled.
- **No claims about authenticated NP API behaviour.**
- **What a Capture can and cannot show:**
  - A clean Capture does not disprove intermittent failure.
  - Root-web execution is not the admin bootstrap runtime.
  - The 10 s timeout and the different user agent do not reproduce the module's 30 s behaviour.
- **N3 causation.** The context presents N3 as POST-only and the diff shown supports that. I did not re-derive the claim that N3 cannot affect the form-opening GET path.
