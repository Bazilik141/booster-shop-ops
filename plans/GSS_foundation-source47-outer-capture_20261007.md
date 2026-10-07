# Source47 additive Phase1 outer capture

Date: 2026-10-07; owner timezone Europe/Kyiv.
Authority: source47 actual Claude Phase1 grant plus GSS-D075 temporary reviews.
Executor: Codex; reviewers: foundation_security_review, foundation_test_review.

The new capture is operational evidence only. It changes no frozen runner or
manifest, reads no private file and makes no database connection itself. It
launches the exact frozen original47 runner once without extra Node flags,
under the current Windows runtime and a closed environment allowlist. The
runner alone retains its existing local-target and secret/SQL boundaries.

Before launch, validate the real COMPLETE47 gate and all 1819 inputs, exact
manifest/runner/predispatch witness, journal2 and absence of the four native
output files. Refuse any old application reservation or existing outer capture.
Exclusively create a separate outer JSONL record and fsync its start line, then
launch once with a 600-second outer process budget and no automatic retry.
Record actual UTC start/end, Europe/Kyiv date, elapsed milliseconds, child exit
code/signal, output byte counts/hashes, and post-run presence/hashes of all
native outputs. Child text is never printed or copied to the outer record;
the frozen runner writes its already-screened transcript itself.

The outer record is not a success receipt or application grant. A stopped or
failed process consumes this outer invocation; never rerun it automatically.
Timeout recovery attempts termination of the owned process tree, catches all
termination faults and waits at most another 15 seconds for closure (plus at
most 15 seconds for the termination tool). If closure stays unknown, record the
runner PID and closeObserved=false, reject success and stop dependent work.
Post-run artifact inspections are nonthrowing per file: an inspection failure
records unknown presence/hash and captureRefused=true while still persisting
the known process exit/timestamps. Any such failure rejects acceptance.
An uncertain timeout must be inspected before any further action. The prepared
root verifier is run only after exit0 and a success artifact, to establish the
separately recorded native test result. Frozen hard-coded 2026-10-06 date fields
remain historical schema labels; actual execution dates come from the capture.

No attempt3, application, key generation, Phase2, Auth, factor/effect, restore,
UI, production or Foundation acceptance is implied by this capture.
