# R2 scoped confirmation: ACC-009-NP-TRANSPORT M1 delta, composition and hash rebind

**Verdict: REVIEW_OK**

This review is static only. I did not run anything, open a network connection or recompute any hash. Its scope is limited to the M1 delta, the composition it affects and the hash rebind. All R1 assessments not touched by the delta are reused unchanged.

## Bound identities (from release-facts.json, file SHA `367025bf…6572`)

| Artifact | SHA256 | Bytes |
|---|---|---|
| `diagnostics\ACC-009_NP-transport-inspect_20261021.php` | `a80924850d1a40a81acaf6935fedca483cfeb3212c111176c7d09c93bbed5a3c` | 16996 |
| `diagnostics\ACC-009_NP-transport_owner-PS_20261021.ps1` | `080a1be7e0ba56e0ce4451b61ac427d8035c2a91c9f2153ad76ac27a9beb2728` | — |

**Rebind consistency** (as presented in the packet):
- **PHP identity is consistent everywhere.** The PHP SHA and size of 16996 agree across:
  - PS `$Sha`/`$Bytes`;
  - the `pkt_diagnostic_sha256` echo;
  - the `php -r` SHA and size arguments;
  - `parse-capture.sh` (`431e14d0…02cd`);
  - `local-checks.json`;
  - `packet-local-checks.json`.
- **PS identity is consistent.** The PS SHA in `packet-local-checks.json` equals the release-facts value.
- **Test identities match.** The recorded test SHAs equal the headers of `test_local.py` and `test_packet.py`.
- **The helper block is unchanged.** `NPD_HELPERS_SHA` is still `ecd6a39c…feab`, and `collector_helpers.php.txt` carries the same SHA.
- **The entry block matches the PHP.** `collector_entry.php.txt` is textually identical to the PHP tail shown.
- **The source pins are unchanged** and still agree with `Test-CaptureReceipt` and the N3 Apply receipt (`2138e156…7b93`).

Any change to either bound file invalidates this confirmation.

## M1 closure assessment

All five closure conditions are met.

1. **Every cleanup-decision failure becomes STOP with the basename.**
   - `npd_probe_cleanup` wraps every step in `catch(Throwable)`: clearstatcache, lstat, type check, hash, unlink and the absence check.
   - Any failure is rethrown as `NpdProbeCleanupFailure($name,$stage,$primary)`. This includes warnings converted by the CLI error handler, plain false returns when E_WARNING is not reported, and Errors.
   - The outer catch maps it by type, not by message prefix, to `STOP_manual_assessment` plus `probe_basename` and `cleanup_stage`.
   - A failing `fclose` in the finally block is captured separately, cleanup still runs, and the result is classified as STOP with stage `close`.

2. **`ok` and `completed_or_not_created` appear only when justified.**
   - `ok` is reachable only when the try returns and cleanup completes without throwing. That requires a successful owned-byte unlink and a confirmed absence.
   - `completed_or_not_created` is reachable only through a non-`NpdProbeCleanupFailure` exception. That means one of two cases:
     - the failure happened before or at `fopen('x')` (helper identity, random_bytes, or an fopen warning or false), so no probe was created; or
     - the failure happened inside the try, after which the finally cleanup succeeded and absence was confirmed.
   - I found no path that throws from the finally block outside these two classified routes.

3. **The primary error is preserved.**
   - `$primary` is captured in the inner catch before cleanup runs.
   - `primaryError` holds the original fixed token, with `JsonException` mapped to `web_payload_invalid`.
   - The cleanup's own withheld warning does not overwrite it. When the primary path succeeded, `primary_error` is `null`.

4. **The fixture covers the required cases.**
   - Real `E_USER_WARNING` injections are made in the `npd_probe_hash` and `npd_probe_unlink` wrappers, each confirmed to occur exactly once by the `count==1` check.
   - The test asserts the STOP classification, the stage, the exact basename (with the leftover file present in root), preservation of `primary_error == web_collection_transport`, and that the key set is the partial set plus exactly three new fields.
   - `requests_seen` of 15 (6 + 3 + 3 + 3) is consistent with these runs. All requests were loopback HEAD.

5. **Both artifacts are regenerated and rebound** (see above).

**Composition effects.** I found no adverse effects:
- The class and typed properties are valid under PHP 8.0 inside the `eval` scope.
- The web probe body is unchanged, because it embeds only the helpers.
- The success-path return value survives a successful finally.
- If cleanup throws during an in-flight exception, PHP replaces that exception (chaining the original as previous), but the output relies only on the explicitly carried fields.
- The owned-byte guard is preserved. A partial or failed write leaves `$owned` unequal to the file contents, which produces STOP instead of a deletion.
- The PS packet does not need to change. Exit 2 still maps to `PARTIAL_STOP`, and stdout, which contains the basename, is retained in the Capture receipt.
- The privacy surface added is a random hex basename, a fixed stage token and an existing fixed error token. There are no new data reads.

## Mandatory findings

None.

## Optional findings (non-blocking)

- **R2-O1: The cleanup reason token is discarded.**
  - Only the stage is emitted. Stage `hash` therefore conflates a hash-read failure with a content mismatch ("probe changed, do not delete"). Stage `stat` similarly conflates a missing file with an unexpected type.
  - Emitting the fixed internal token (for example `probe_changed` or `probe_unknown_type`) would sharpen manual assessment.
  - Both cases already require STOP and manual inspection before any deletion, so this does not block.
- **R2-O2: Two cleanup-failure branches are covered by static reasoning only, not by a fixture.**
  - The success-path failure (`primary_error: null`) and the `close` stage are not exercised.
  - Stage `close` is conservative: it reports STOP even when absence was confirmed afterwards. That is acceptable.
- **Carried from R1:**
  - O1, strict web runtime pins: accepted as an honest partial result.
  - O3: if adopted, the expected `remoteScriptSha256` is now `431e14d0…02cd`, not R1's `8a8fbd89…1369`.
  - O4: an SSH-level failure consumes the single Capture.
- **Closed:**
  - O2 is closed by the `php -r` decode/SHA/size/eval chain run in the positive fixture.
  - O5: I accept the stated module facts (HTTP/1.1 and a 30 s timeout at lines 43/45 of `729c3c6c…b19c`) as declared. That library's source is not in this packet, so I did not verify them. The remaining disclosed differences are HEAD, no key, the 10 s timeout and the user agent.

## Assessments (R1 reused; delta-affected rows updated)

| Area | Assessment |
|---|---|
| Privacy | Unchanged. The added outputs are a random basename, a stage token and a fixed primary token. |
| Authority | Unchanged. Owner-only. Agents get no SSH/key/SCP access, and nothing grants Git, status or deploy. |
| No business effects | Unchanged. Six fixed HEAD requests with no key, body or cookies, verification on, no POST. |
| Temporary endpoint | Sound. M1 is closed, and a leftover probe is now reported with its exact name. Exposure is bounded by the GET + nonce + 120 s TTL 404 guard. |
| Cleanup | Correct on all classified paths. No false `ok` or `completed_or_not_created` follows a cleanup failure. |
| Streaming | Unchanged and now natively exercised (O2 closed). |
| Receipt | Unchanged contract. The extra fields appear only on the exit-2 partial path, and stdout is retained. |
| Fake boundary | Honest. Local-only E_USER_WARNING wrapper injection, loopback real libcurl, and only the web SAPI name adapted in the receipt fixture. |

## Limits

- **Static review only.** No hashes were recomputed and nothing was executed.
- **No claims about the hosting environment**, including the web SAPI, CDN or redirect behaviour, or curl availability.
- **No claims about authenticated NP API behaviour.**
- **A clean Capture does not disprove intermittent failure.** Root-web execution is not admin runtime, and the 10 s timeout differs from the module's 30 s.
- **N3 causation was not independently re-derived.**
- **Diagnose only.** This confirmation authorizes no fix, deploy, retry or repeat Capture.
