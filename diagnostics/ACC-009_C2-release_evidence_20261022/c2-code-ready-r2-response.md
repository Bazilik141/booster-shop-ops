# ACC-009-C2 integrated final release review (r2, fresh authentication retry)

## Verdict: **REVIEW_OK**

The verdict is bound to these two exact files:

| Artifact | SHA-256 |
|---|---|
| Runner `patches/ACC-009-C2_recipient-address-prefill-private-release_20261022.php` (224549 bytes) | `8645e8f247681529d0a8ec9e42b6a01fa1a656e246fa949a4fb08796206fb9d2` |
| Owner packet `diagnostics/ACC-009-C2_owner-PS_20261022.ps1` | `bc920b06508ee2332ef30120e5f7d8eb715d2ede15d0b01ae216c604dda8a2be` |

I found no mandatory code or composition defects. Three receipt-closure conditions apply (§3); they change no hashes. The prior request `0fda91b7-c2e8-4e44-a523-0317cf216d83` stays FAILED/NOT_REVIEWED (OAuth 401) and is consumed. This verdict does not replace it.

I used no tools and recomputed no hashes. Identity rests on the supplied bindings.

## 1. What was checked and accepted

**Payload (reused).** The C2 R3 CODE acceptance is reused by identity:
- the embedded spec has before `2138…`, after `4d84…` and edits identical to HELD R3;
- the 29 native PHP 8.0.30 controller cases were rerun with fictional effects;
- the 12 Twig/HTML checks are reused, not rerun.

**UI signature scan of C2.diff.** No `!important`, `setTimeout`, `position:absolute/fixed`, magic pixel values, CSS or markup.

**Directory schema guard (new):**
- `acc009c_directory_schema` runs inside `acc009a_metadata`. That call chain is preflight → payload lint → web probe → backup and write, so a missing or wrong column stops before any backup or target write.
- The required columns match exactly the columns C2's `acc009AddressRows` selects:
  - area: ref, description, description_ru;
  - city: ref, description, description_ru, area;
  - street: ref, description, street_type, city.
- Ref and parent-ref columns need at least 36 characters, a textual `DATA_TYPE` and a non-empty collation. A missing table gives empty rows and fails closed.
- It reads only information_schema, through the inherited prepared/`bind_result` helper. It reads no rows and enforces no row uniqueness. C2's `LIMIT 2` / count==1 and hierarchy/round-trip guards keep ambiguity fail-closed.
- `COLLATION_NAME AS collation` uses a non-reserved keyword in MySQL 8 and MariaDB. A prepare error would still fail closed.

**Probe cleanup (repaired):**
- The `finally` block catches any `Throwable`, including warnings that the error handler converts into exceptions, from both handle close and identity/unlink.
- It preserves the generated basename, the stage and a safe primary code. Non-admission exceptions are withheld, so no secrets leak.
- It replaces the primary exception or a successful return with a STOP before any source write.
- It deletes only bytes it owns and verified by hash. Changed or unknown bytes are left in place.
- An absent file is treated as cleaned.
- A partial `fwrite` makes it refuse deletion of its own partial file. That is conservative and acceptable.
- `@file_get_contents` warnings are masked from the handler correctly, because the `@` mask excludes `E_WARNING`.

**Inherited N3 lineage:**
- source admission of 49 pins (the 49 count verified; target pin overridden by current hash; extension/setting pins present, so no `null` rows);
- events, startups and namespace checks;
- private-stage, path and symlink admission;
- backup with manifest;
- atomic replace with pre-rename hash recheck;
- native lint with restore-on-fail;
- rollback-check versus rollback (C2 after AND N3-before backup, reverse C2 first);
- idempotence, self-delete and strict CLI entry.

Receipt line order matches `Test-RunnerReceipt` exactly for dry-run, apply and already_applied.

**Packet:**
- Separate steps with local hash and size check on every invocation.
- Remote `hash_ok` is rechecked before execution.
- `/usr/local/bin/php` 8.0 is pinned.
- Strict host-key checking, `BatchMode`, no forwarding.
- Prerequisites bind runner and packet SHA, plus TTL (Verify 4 h, Preflight 30 min).
- Apply STARTED is written before the remote call, and every later step refuses after any `Apply-*`.
- Upload/Verify stderr must be empty; Preflight/Apply allow only the exact MYSQL deprecation line.
- The wrapper reads the post-run target hash and stage presence:

  | Outcome | Status | Exit |
  |---|---|---|
  | Target still before | STOP | 1 |
  | Target after, postcheck failed | STOP | 4 |
  | Anything else | unknown | 5 |

- No retry, next step or rollback is started.
- The `Substring(12)` offsets are correct for `web_runtime=` and `backup_path=`.
- PS 5.1 `ConvertFrom-Json` keeps `utc` as an ISO string, and the `Parse`/`ToUniversalTime` handling is correct.

**Test fidelity.** The meaningful native boundaries are:
- the real pure validator;
- real filesystem apply, rollback and restore;
- 4 real CGI probe requests;
- full-entry lifecycle;
- warning injection at the real cleanup hash call.

The fictional boundaries are disclosed in the adapter diff.

## 2. Mandatory findings

None.

## 3. Binding receipt-closure conditions (no code change; required for mechanical closure)

**C1. Planned-to-applied pairing.**
- Scope: Preflight and Apply receipts.
- The packet reports `status=OK` even when Apply shows `already_applied=yes` after a Preflight that showed `action=planned`.
- Close mechanically only when both hold:
  - Preflight shows `action=planned`, `current_before=2138…`;
  - Apply shows `action=applied`, exactly one `backup_path=/home2/boosters/_patch_backups/ACC-009-C2-…`, wrapper `current=4d84…` and `stage_runner=absent`.
- Any other pairing is a scoped assessment and has no owned backup. Typical examples: planned → already_applied, or already_applied at Preflight.
- Any future rollback must use only the `backup_path` from the owned Apply receipt.

**C2. Runtime drift.**
- Scope: the `web_runtime` line in the Preflight and Apply receipts.
- Compare it exactly against the retained `{"php":"8.0.30","sapi":"litespeed","opcache_enabled":true,"validate_timestamps":true,"revalidate_freq":2}`.
- A difference inside the admitted bounds is recorded and assessed (bounded), not closed silently.
- Out-of-bounds values cannot occur, because the runner refuses them.

**C3. Probe-cleanup STOP.**
- Scope: any receipt whose stderr contains `web_probe_cleanup_STOP_manual_assessment:<name>`.
- Treat it as STOP.
- Remove the exact named file only through a separately prepared owner step that first hashes it. No wildcard or blanket deletion.
- Exposure while it stays is low: the file needs the nonce and returns 404 after its 60 s TTL.

## 4. Optional findings (record; no further review round)

1. **Cleanup reason lost.** The cleanup `catch` drops the inner reason (`probe_identity_changed`, `probe_unlink_failed` or a warning), and all three report `stage=identity_and_unlink`. Future packets could append the inner admission code, so manual assessment does not need to re-derive it.
2. **Bash precheck failure reported as unknown.** If the Apply remote Bash fails before the runner (for example `hash_ok`), there is no `pkt_current_target_sha256` line, so the result is exit 5 (unknown) rather than 1. This is conservative.
3. **Schema SQL never run against a real database.** The new information_schema SQL has never executed against real MySQL/MariaDB; the lifecycle adapter replaced preflight wholesale. Its first real execution is the owner's Preflight, and any failure is fail-closed.
4. **Pins may be stale.** The runtime pins (49 sources, admin/system events, extension codes) come from the 2026-10-18 R3 spec. If the host drifted since N3's Preflight, Preflight stops with `runtime_source_changed` or an events/namespace error. That needs regeneration and review, not a host-side edit.
5. **Two models not pinned.** C2 calls the area model `getByZoneId` (and, as before, the warehouse model), but `model/module/area.php` and `warehouse.php` are not among the 49 pins. This is pre-existing scope accepted at CODE; noted only.
6. **Unused parameter.** `$name` is unused in `acc009r_probe_cleanup`. Cosmetic.

## 5. Scope and limits

- This is a CODE/composition review only. No execution, host state, live SQL, HTTPS, NP or owner QA is proved.
- The root-web probe proves the LiteSpeed/PHP/OPcache settings only. It does not prove admin bootstrap or NP acceptance.
- Source rollback cannot undo external NP effects.
- The known limits carry over unchanged: duplicate-city fail-closed, legacy ambiguous addresses stay manual, and there is no N2/sender/transport change.
- Nothing here grants agents SSH/key/server access, Git, status or Notion authority.

## 6. Owner next action

Run the packet as four separate invocations, in order, stopping at the first non-OK result. Do not retry Apply.

1. `-Step Upload`
2. `-Step Verify`
3. `-Step Preflight`
4. `-Step Apply`, within 30 minutes of Preflight.

Then:
- return the receipts so they can be closed under C1–C3;
- do the Tier 1 smoke checks;
- do the owner C2 functional and visual QA on the admin courier TTN form: prefill, warning on ambiguity, and one legitimate save.

---

**Коротко для власника:** перевірку пройдено, обов'язкових виправлень немає. Можна запускати пакет: Upload → Verify → Preflight → Apply, кожен крок окремо. При будь-якому статусі, крім OK, зупиніться і не повторюйте Apply. Після застосування надішліть квитанції та перевірте форму накладної для кур'єрського замовлення.
