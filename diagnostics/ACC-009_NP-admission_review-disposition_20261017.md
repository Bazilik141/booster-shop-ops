# ACC-009 NP read-only diagnostic — actual review disposition

Date: 2026-10-17 (Europe/Kyiv)
Executor: Codex. State: REVIEW OK; OWNER READ-ONLY HOST EVIDENCE REQUIRED.

Actual Claude Opus/high subscription r1 returned **Diagnostic Review OK** for
the exact diagnostic bytes. The full response was saved and read. Three
mandatory non-code conditions D1-D3 are disposed below; no code change or new
provider round is needed. This is not N1/N3 CODE or effective runtime acceptance.

## Receipt and unchanged closure

- Request `d3a2ab46-1ca4-462f-ac30-857d21388ef6`, consumed; never replay.
- Original native PID21536 / controlled session41984; exit0.
- Actual `claude-opus-5-5`, high, saved subscription, no API-key fallback,
  tools/MCP calls or overage (`isUsingOverage=false`).
- Fourteen frozen review inputs, all47 retained source identities, packet and
  Git HEAD/index unchanged at independent closure.
- Full verdict: `diagnostics/ACC-009_NP-admission_claude-review-r1_20261017.md`.
- Closure: `diagnostics/ACC-009_NP-code_evidence_20261017/review-r1-admission-closure.json`.
- Approved diagnostic: `patches/ACC-009_NP-runtime-admission_20261017.php`.
- SHA-256: `34014f4c00419fde9bc7101483aac100e7dc72c3708af4f0bbc31582cd880dad`.

## D1 — exact artifact binding: satisfied

`work/acc009-np-code-20261017/bind_admission_checks.py` mechanically decoded the
embedded base64: exact compact JSON byte equality to the manifest's47-entry
expected map and object equality. It compared the complete embedded parser to
the reviewed origin span with only the function prefix renamed, and recorded
origin-file/span hashes. It reran lint and the32 existing fixtures against the
approved artifact, recording the same before/after SHA, tool/fixture identities,
exit codes and actual result. This repeat closes the review's artifact-binding
gap; it is not broader or production testing.

Evidence: `diagnostics/ACC-009_NP-code_evidence_20261017/admission-D1-artifact-binding.json`.

## D2 — explicit owner CLI/result/cleanup: satisfied

`work/acc009-np-code-20261017/owner-admission-command.sh`:

- prints and checks `php -v` for PHP8.0 and `(cli)`;
- prefers the private parent of public_html; if uploaded inside public_html,
  verifies the file before moving only this diagnostic beside the webroot;
- verifies exact SHA again, then lints and runs;
- captures stdout and stderr to a unique mktemp output beside public_html,
  under umask077, and records the process exit code;
- requires exit0 plus both exact lines `self_deleted=yes` and `done=ok`;
  a CGI/empty-output false success cannot pass;
- an owner-run EXIT cleanup removes only remaining regular, non-symlink copies
  of this diagnostic whose SHA still matches the approved bytes. It does not
  remove output evidence or any different file.

The shell block is syntax-checked separately; it is not part of the frozen PHP
artifact and does not modify that artifact's reviewed bytes. Successful
collection remains `runtime review required`.

## D3 — mandatory interpretation rules for owner result

1. `active_events` and `active_startups` are status-truthy candidate supersets.
   Apply actual admitted admin startup controller/model filters before calling
   a row an effective handler. Admin startup/event registers admin-prefixed
   triggers after removing that prefix, and system-prefixed triggers as coded;
   catalog-only events do not register for the admin document request. Apply
   startup/action prefix and model SQL filters as well.
2. Config-defined events/pre-actions come from the admitted system/config/admin
   source, separately from DB candidates. Account for handler order and actual
   Event::trigger prefix semantics; no SQL substring classifier proves safety.
3. Empty generated_ocmod cannot distinguish absent from empty directory. Infer
   no active file override only together with the actual admitted reroute path;
   do not certify future OCMOD refresh behavior.
4. Disk hashes do not prove current web OPcache bytecode. Admit web runtime/
   revalidation behavior at the applicable deployment gate; do not claim CLI
   metadata proves currently executing admin bytecode.
5. The two previously pending loader model bodies have now arrived. Compare
   the host's matches=null hashes to this exact admitted drop:
   extension model `67edb7eb7399f35de511db3239815e43c4bf1271771d79bed63cf9f0e58f2dfb`;
   setting model `42315b685d89a26ffcc989c5de999aed736044b3560d9344dd4d8d528ea5a1d7`.
   Mismatch means stale/conflicting evidence, not automatic admission.
6. The later N1 runner must anchor on the exact internet_document.php base hash
   reported here; N3 must compose after the accepted N1 after-image. Any
   intervening relevant deployment/edit invalidates an old runtime admission.

## New public loader-source intake

While the diagnostic review was running, the requested archive arrived:
`ACC-009-NP-admin-loader-current_20261017.tar.gz`, SHA-256
`2efaf5d6a0bd8b72a09f651751598c2052e3642cce1344d2383b813a4055ccf4`.
Exactly2 regular files/19285source bytes were admitted without executing them.
Both passed local PHP8.3 lint. Complete getInstalls/getSettings bodies were
inspected. Evidence: source-intake-admin-loader.json and admin-loader-contracts.json
in the current evidence directory.

getInstalls defaults to every extension_install row ordered by date_added,
without a status filter; the diagnostic's full code inventory covers those
codes. getSettings(0) returns store0 rows for startup/setting; that model does
not replace the already constructed template registry entry. Their current
effective identities remain subject to the owner run and handler analysis.
The frozen review packet/diagnostic was not changed on archive arrival.

## Optional findings disposition and honest limits

No optional-only provider round or diagnostic code change was made. Retain:
explicit OCMOD-dir presence, E_ALL/unlink message refinements, additional
bootstrap-source identities, modification regeneration inventory and more
entry/schema/symlink/error-cleanup fixtures as possible later hardening. Treat
material unexpected runtime evidence as a blocker, not as an optional omission.

Correct the earlier report's broad wording: fixtures cover DB target mismatch
and path traversal, not configured-runtime-path mismatch. They are32 fictional
assertions on PHP8.3; production8.0 and actual application/DB execution remain
unproven. Source-only metadata and a successful collection cannot certify NP
remote attribution or end-to-end Doors documents.

The next action is owner upload/run and bounded output review, with no separate
routine go. After required effective admission is complete, automatically
prepare/test the owner-authorized N1 and separate N3 and submit actual Claude
CODE review. WP3b/WP3c and all existing exclusions remain unchanged.
