```json
{
  "review_type": "Independent CODE/composition/owner-command ready review (fresh separate Claude request; not the command author; not sole approver)",
  "bindings": {
    "main_runner": {
      "path": "patches\\ACC-009-C1-H1_courier-notifications-schema_20261020.php",
      "sha256": "3403f0f2d15c26e81963db5ed7511cd0d38f06eb37369de4874281a16bea6753",
      "bytes": 1087629,
      "prior_CODE": "d9c3597f-132e-4d65-8d14-18104b73a5ad",
      "reuse": "Exact accepted SHA reused. Business payloads were not re-read. Orchestration excerpt reviewed as supplied."
    },
    "packet": {
      "path": "diagnostics\\ACC-009_C1-H1_apply-recovery_owner-PS_20261021.ps1",
      "sha256": "fc5ceb936983cf6e33a7f735855a4e3304a55d903b69f6e2107a9cb2bf1c65cc",
      "parent_sha256": "ffb828695bc1c4be9e5ced097ab870362280c34f0ca483a71449c260ae5eb36a"
    },
    "context_inputs_read": {
      "apply-author-facts.json": "26233ff08902bf3c00a7c7538f7af2a050a7f8dc3673a3a639a9645781ae0e83",
      "main-owning-functions.excerpt.php.txt": "5a66166b8ab834d8f453af8f310fc4d326fb1ba8bb9e3d7ab3a7e1e74c892601",
      "schema-release-facts.json": "d47e3b9ae75653871640ff83051c001fceb7840d68af574fcacf70ffa5754d16",
      "schema-code-ready-r1-closure.json": "e3bf9039bddc73576e86b6b1751ced6be55c17b77d0645adfc6c7559852c511c",
      "owner-UA-preflight-success_20261021.json": "9bab8633000594aa5b4aa72dc9323e63151d8c208e60566e4ec4e3ced3799c14",
      "owner-browser-runtime-success_20261021.json": "bed479145b124c3f0fda809f213d3937f2762383c31dade21e7dd61a86cd6dc9",
      "local-checks.json": "de5f90b8cfdd57e4b8c716efa71b1e5cd994c708ffc9a9edcc42ff17d4a88ef7",
      "apply-packet-author-r1-composition.json": "a45436560e001d5267077b69e82198fbfcecc24c1e87b2d3fa899f7a22acf520",
      "accepted_preflight_receipt_pinned_in_packet": "78810e1aca1aa1a2df3007a5e21c7c4658a97a69d47dd724d05d6c3253db75e0"
    }
  },
  "verdict": "NO_CODE_DEFECT_FOUND. READY to seek final owner Apply permission only after M1 (owner exception) and M2 (deterministic static closure) are both closed. This is not production authorization and grants no deployment authority.",
  "summary": "The Apply and Rollback routes are complete, guarded, fail-closed paths and are consistent with the supplied main orchestration (b_receipt, h1_change_set, h1_rollback, b_web, entry). The following were checked against the main source text: pre-invocation gates, the single invocation with stdin=/dev/null and HUP/PIPE ignored, the receipt captured before assertions, the CreateNew run record of backup_path rows, separation of the wrapper exit from the PHP exit, and the exact 26-row knownspec format for applied, restored and already_before rows. Also checked: barrier placement between rows 22 and 23, tail ordering, self-delete verified without blind deletion, and refusal to rerun from both local and host receipts. The cache helper performs a full inventory and refuses before any deletion, with bounded counts, regular *.php only, a per-file parent re-check, and no mkdir. No safety or correctness defect was found in the packet code itself. Two gates remain. The first is the acknowledged literal-workflow departure, which needs explicit owner disposition. The second is one receipt-contract dependency that sits in main functions not included in the supplied excerpt.",
  "mandatory": [
    {
      "id": "M1",
      "class": "OWNER_INSTRUCTION_DEPARTURE_not_code_defect",
      "finding": "CODEX_WORKFLOW lines 310-311 specify a literal shared Bash 'patch && cleanup' chain. The packet instead runs cleanup in a second owner SSH call, and only after PowerShell validates the complete source receipt and confirms the runner is absent. Code-safety disposition:\n- The gate is strictly stronger than an && on exit status. Cleanup never runs on source failure, already_applied, receipt mismatch or an unconfirmed runner post-state.\n- New cost 1: if the reconnect or agent fails after source success, the result is an honest exit-3 SOURCE_CHANGED_CACHE_FAILED / do_not_reapply. The packet has no re-run path for this (by design, no CacheOnly mode).\n- New cost 2: the stale-compiled-template window is a few seconds longer.\nThe departure cannot be waived silently.",
      "closure_condition": "Before or together with the final owner Apply permission, the owner records an explicit bounded exception. It must state that, for packet fc5ceb93... with runner 3403f0f2... only, cleanup sequencing is PS-validated conditional with a second SSH call instead of a literal && chain. It must also state the exit-3 consequence: a cleanup failure after source success needs separate assessment and a revised reviewed packet or an owner decision, with no automatic retry or rollback. If the owner declines, the packet must be revised to a literal && chain and re-reviewed."
    },
    {
      "id": "M2",
      "class": "DETERMINISTIC_RECEIPT_CONTRACT_CLOSURE_not_new_evidence_collection",
      "finding": "Test-ApplyStdout depends on --apply stdout from main functions that are not in the supplied excerpt and are not exercised by the accepted dry-run:\n- b_sources, which runs only in --apply.\n- b_composition_barrier, the source of the exact line 'composition_barrier=C1_23_after_H1_3_before_hashes_match'.\n- h1_lint, which must not emit target=/changed_file=/forbidden-prefix lines.\n- h1_log format.\nThe local fixture used the native b_receipt formatter but fictional barrier and other lines. Failure this could conceal: a liveness failure, not a safety failure. A truly successful production Apply would STOP after the source write, with no cache cleanup and no in-packet recovery. Examples are a barrier-string mismatch, a second barrier emission, or extra target=/changed_file= rows from b_sources.",
      "closure_condition": "Codex makes a static, non-executing read of the immutable main bytes, SHA-bound to 3403f0f2... and with no model call. It records a closure file citing exact source lines that establish all of the following:\n(a) b_composition_barrier emits exactly one stdout line equal to $BarrierLine, via h1_log 'key=value\\n' to stdout.\n(b) In the --apply path, no function other than b_receipt and h1_change_set emits stdout lines beginning target=, changed_file=, backup_path=, apply=, host_php_lint=, self_deleted=, done=, mode= or task=.\n(c) Nothing in the --apply path emits lines beginning error=, restored=, restored_file, already_applied= or rollback on success.\nIf all three hold, M2 closes with no packet change and no further review. Any mismatch requires a packet revision and re-review."
    }
  ],
  "optional": [
    {
      "id": "O1",
      "finding": "Some header and comment text is stale. The file header still describes the inherited UA-variation Preflight packet. The Invoke-Proc comment still says 'visible passphrase prompt' although the transport is agent-only with BatchMode=yes. The author's prose says 4 replacements while the composition records 5. These are documentation issues only and not blocking. If changed, the packet SHA changes and needs mechanical re-check."
    },
    {
      "id": "O2",
      "finding": "After a confirmed invocation with CLI_EXIT=0, any receipt-format mismatch in Test-ApplyStdout or Test-RollbackStdout ends with a generic Stop-Draft (exit 2). The RetainNotice does carry do_not_reapply and the backup_path rows. Explicit wording such as 'SOURCE_STATE_LIKELY_CHANGED_RECEIPT_MISMATCH_CACHE_NOT_RUN' would make owner triage clearer. Behaviour is already fail-closed."
    },
    {
      "id": "O3",
      "finding": "The Apply cache cleanup re-admits PHP with glob 8.0.* rather than the exact 8.0.30 used for the Apply run. Pinning 8.0.30 for 'apply-cache' would be marginally tighter. This is not material, because cleanup runs no runner code."
    },
    {
      "id": "O4",
      "finding": "Assert-NoPriorLocalRun deliberately allows a rerun when the only prior receipt is a guard refusal that occurred before invocation. This is correct, but worth stating explicitly in owner instructions so the owner knows such a rerun is allowed."
    }
  ],
  "acceptance_limits": [
    "No production, runtime, QA or deployment proof. All local checks are fictional or syntactic: PS5.1 AST, bash -n, a synthetic filesystem suite and the native b_receipt formatter. Native Unix symlink refusal was skipped on Windows. Production refusal relies on GNU find %y emitting 'l' and on the per-file -L checks.",
    "The Apply re-runs fresh main source, metadata, web, nonce, seal and OPcache guards. Any substantive mismatch is a STOP. The accepted Preflight (receipt 78810e1a...) and browser observation are reused, not repeated.",
    "Up to 26 sequential source writes plus about a 2 s OPcache revalidate window, plus the extra seconds before the separate cleanup SSH. Wait at least 3 s before owner QA. There is no C1/H1 observation window.",
    "Residual compile race: a storefront request that read an old .twig before the write could write a compiled cache file after cleanup. With mtime-based freshness that file could persist. Owner QA must check rendered checkout templates. Re-clearing requires a revised packet or an owner decision, since no CacheOnly mode exists.",
    "Cleanup refuses when more than 5000 template entries exist, or on unsupported entries or links. Such a refusal after source success gives exit 3, needs assessment and is not retried automatically.",
    "Rollback via this packet requires that the backup_path row appears exactly once in a captured local Apply receipt. Example: transport is lost after invocation, the runner continues because HUP is ignored, and the local receipt lacks the row. Recovery then needs assessment of the host ~/private/booster-pulls receipt files and a revised, reviewed binding.",
    "Rollback is source-only for all 26 files and safe with partial states. It cannot undo order, payment, email, CRM or Telegram effects. Roll back ACC-009 before the earlier Mono task, and never mix the /home2/boosters/_patch_backups and public_html/_patch_backups roots.",
    "The history-derived commit-window concurrency residual is accepted. There is no exactly-once, delivery or full-input-range guarantee. The native Mono getOrder missing-row-only behaviour is retained.",
    "The hash-to-execution window inside the 0700 private stage and same-UID TOCTOU in the cache tree remain the same accepted residual class as Preflight."
  ],
  "authority_statement": "This assessment has no deployment authority. Only the owner may execute SSH, SCP or server PHP and give the explicit final Apply go, and only after M1 and M2 are closed. No commit, push, Notion, dashboard or business writes are implied."
}
```
