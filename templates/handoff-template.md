# Codex Handoff — <TASK-ID>: <short title>

Date: YYYY-MM-DD | Parent: <parent task if any>
Codex config: model=<Sol/Terra/Luna> · effort=<low/medium/high/xhigh/ultra>

## Context
<!-- 2-3 sentences: current state, what triggered this task. No workflow repetition — see AGENTS.md. -->

## Scope (what to change)
<!-- Bullet list of concrete changes. Each item = one file + one action. -->
- `path/to/file.php` — <what exactly to change>
- `path/to/file.twig` — <what exactly to change>

## What NOT to touch
<!-- Explicit exclusions to prevent scope creep. -->
- <file or module> — reason

## Acceptance criteria
- [ ] <observable outcome 1>
- [ ] <observable outcome 2>
- [ ] The executor's report includes the exact patch SHA-256, per-target
      before/after hashes or `absent` states, backup path or `none`, stdout
      contract and rollback facts. Claude chat composes the owner-run PowerShell
      packet; its SHA-256 is independently checked alongside the patch hash.

## QA checklist (owner runs after deploy)
- [ ] <step>
- [ ] <step>

## Risks
<!-- Only if relevant: risky zone flag, smoke test reminder. -->
<!-- If checkout/payment/schema affected → run checkout smoke test. -->
