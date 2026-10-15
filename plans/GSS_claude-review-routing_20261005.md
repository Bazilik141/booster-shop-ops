# GSS — owner-selected Claude review routing

Date:2026-10-05
Authority:direct owner steering during the assigned Foundation execution, not a new functional decision or task reassignment. Supersedes the executor's earlier suggested Sonnet/medium and Haiku/low routing. Codex remains executor; Claude independent reviewer.

| Review scope | Requested model and effort | Reason / limit |
|---|---|---|
| Truly small corrections and documentation verification | claude-sonnet-5-5 / xhigh | Owner requested Sonnet5.5/extra; extra-high is CLI xhigh. Applies only when underlying content is not a security/architecture outcome. |
| Mechanical changes | claude-sonnet-5-5 / high | Owner requested Sonnet5.5/high; no Haiku or low/medium reduction under this steering. |
| Security, access/tenant isolation, admission/recovery, durable-data or ambiguous multi-file architecture | Opus / high | Preserve the current risk-appropriate independent gate. Record the concrete reason for each dispatch. |

Select per task/subtask, not by filename: a document that defines authorization still warrants Opus/high. A short typo fix does not reopen the parent's review. Supply changed code plus the smallest sufficient reviewed context; do not omit relevant dependencies for packet size. Requested/resolved model and effort must be recorded in each actual run. No guarantee of equal quality or fixed token savings is asserted; preserve focused executor validation and required peer gates.

Local Claude Code2.1.283 --help confirms effort low/medium/high/xhigh/max and explicit model names. Official [Sonnet5.5 migration guide](https://platform.claude.com/docs/en/models/sonnet-5-5/migration-guide) confirms claude-sonnet-5-5 and xhigh support. Subscription entitlement/resolved model is verified only on an actual appropriate request; do not spend a separate model-test run or silently substitute another model if refused. Use existing background authenticated subscription transport with tools/MCP/hooks/UI disabled; no paid-provider override.

Current I22 review stays Opus/high: its source split controls whether fictional access definitions can enter a release graph, with a generic private registry and callback identity validation. Future same-snapshot read/TEMP/pool/permission reviews likewise retain security depth.

Actual appropriate use now verified:synthetic read/pool implementation review12 used claude-opus-5-5/high and accepted code, with two test-discrimination repairs requested. Focused small-repair review13 resolved claude-sonnet-5-5/xhigh,99984ms,Review OK for I34/I35a only. No dummy/model-entitlement test, fallback or paid API override. Smaller packet supplied changed test/test-root sources and new evidence while hash-reusing the accepted Opus parent. See diagnostics/GSS_foundation-read-gate13_20261005.md; no exact token/quota savings or entire security re-audit quality claim. This metadata entry is added after review13; it is not relabelled as part of that sealed input.
