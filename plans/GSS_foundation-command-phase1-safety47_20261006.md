# GSS Foundation — Phase1 setup safety47 and day-end boundary

Date: 2026-10-06
Executor: Codex; sole implementation, database, Git and status writer.
Independent reviewer: Claude Opus5.5/high for secret handling and the native gate.

Actual45 single native Phase1 failed143/144 on the stale frozen36 archive digest.
Transcript/reservation/root refusal remain preserved;no native retry or attempt3.
Unissued46 corrected precisely one7C52-to-already-reviewed-DAEE test literal,
selected16 unchanged frozen36 files plus one isolated copy, and checked source
compatibility before reservation/target/secret. All144 tests/122 original names
remain;no behavior or legacy0007 refusal removed. Unissued46 packet is retained;
it was never sent to Claude, sealed or run natively.

The supplemental read-only Codex audit found two issues before46 dispatch:
secret JSON/shape validation remained outside the runner's inner try, allowing
an uncaught assertion/parser error to expose values;the new failure writer used
wx without fsync despite a durability claim. These findings are independent
supplemental evidence,not Claude approval.

47 preserves all94 lane files exactly under fixed lane/output translation from
unissued46, including the one corrected test. Maintenance behavior equals
accepted45 (180s transaction/60s idle,10s fixture,8s statement/5s lock).
Only the top-level Phase1 orchestration changes: all runtime setup, secret
validation and native work now run inside async runPhase1 with an outer catch
that discards the raw error and emits a fixed value-free passed=false record.
The inner native failure catch remains and uses the shared fixed writer.
That writer exclusively opens the fixed failure path, writes all bytes, fsyncs,
closes and catches every writer/close fault without changing refusal or granting
retry/application. No directory-entry fsync claim is made.

Source-only tests extract the actual async boundary/catch/writer through the
byte-pinned parser and use fake malformed JSON/password/JWT sentinels and IO.
They check that no sensitive sentinel reaches output, including writer failures,
plus short writes/open/write/fsync/close faults and the unchanged exact17-file
selection. Existing91/24/24/11/19 matrices and full static closure remain required.
No private/real secret reads or native connections occur in this preparation.

The next actual reviewer must explicitly assess the preserved45 failure, the
corrected suite and this secret boundary before a NEW source-bound Phase1 grant.
No source-only approval is attempt3 authorization. A future successful fresh
144/all122 must precede a distinct actual attempt3 review with UUID/budget/I167
watchdog evidence. No failed-catalog learning, automatic retry or model fallback.

Owner asked for a logical end today. Finish actual47 source review/capture and
its real seal only if accepted;then stop before any native47 invocation. Record
the actual result, unused or withheld grant, source pins and journal2, preserve
all unrelated changes and make a narrowly scoped GSS evidence commit/push.
If Claude quota fails,preserve actual failure/reset and stop without retry.
No running CLI/DB/native process is left at the end. Next-day native work starts
only from the current actual verdict/seal and fresh preconditions.

D069-D074/Q1revision2 remain closed; D064/D065 cover local technical correction
and scoped Git. Foundation estimate stays35% +/-5pp. Application attempts2 of3;
Phase2/Auth/factors/effects/restore/ownerQA/production/Foundation remain unproved.
