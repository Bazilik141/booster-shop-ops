# Booster Shop automatic independent review — current owner rule

Date: 2026-10-14. Decision: BOOSTER-AUTOREVIEW-20261014.
Authority: direct owner instruction and the explicit subscription-CLI choice in
`plans/BOOSTER_review-optimization_owner-decision_20261014.json`.
Applies to every Booster Shop task series in this workspace: OpenCart, CRM,
dashboard, 3D-print, GSS and related operations. Durable instructions: English;
owner communication: concise Ukrainian.

This amendment supersedes older manual Codex-to-Claude handoff/review-trigger
wording in AGENTS.md, CODEX_WORKFLOW.md and CLAUDE.md. Scope assignment,
implementation ownership, Git authority, Notion/dashboard writers, deployment,
business-data permissions and owner QA remain governed by the active project.
GSS-specific authority and stronger readiness/no-replay requirements still apply.
Do not transfer GSS runtime/resource limits or native grants to other tasks.

## Trigger and responsibility

Codex automatically initiates review by actual Claude when the required assigned
stage is ready. The owner does not have to copy a prompt, invoke bsmain/bsreview,
or approve each routine review of an already assigned scope. This is the active
agent's workflow, not a background watcher or a promise to run after the chat stops.

The readiness boundary is explicit: design review before implementation when
the task requires it; CODE/composition review after meaningful local checks;
actual-result review separately when runtime/live evidence is required. Related
changes and their interactions form one coherent stage; optional nits do not
receive a separate provider round. Do not skip a required design or interaction
review to reduce calls. Scale context/checks to the task's risk.

Codex owns preparation, dispatch, original-process tracking, complete result
capture, verification, disposition and technical repair loops. Actual Claude
independently assesses adequacy and correctness, including unexpected defects.
Codex or its subagents cannot supply Claude's verdict. A Claude-authored task
requires its applicable independent reviewer; this Codex-to-Claude rule does
not authorize self-review or reassign that task.

## Approved transport and disclosure scope

The owner explicitly selected the installed Claude CLI through the existing
subscription. The phrase "through API" in the first instruction meant automated
review; the subsequent direct answer selects CLI. No separate Anthropic/OpenAI
paid API, API-key discovery, paid fallback or purchase is approved here.

Automatic review may disclose necessary internal code, project rules, sanitized
technical diagnostics and generated fictional tests to Anthropic through that
subscription. Do not include credentials, account/config secrets, real customer
or payment data, private business records, bulk backups or unapproved live/pilot
payloads. Existing task-specific external-data gates remain; this rule does not
waive GSS D6 or the paired pilot-payload gate. Use bounded public source evidence;
missing material context blocks a positive verdict.

Use a task-scoped verified CLI adapter with model/effort recorded, tools/MCP/hooks
disabled and no paid-provider environment fallback. Risky design/CODE/security,
checkout/payment/CRM/DB/SEO/schema/authority/recovery work retains actual
Opus/high. GSS uses its accepted exact transport19 identity/admission and fresh
same-review request identities; its frozen requesters and BASE rules are not a
general Booster launcher. Mechanical low-risk review can follow the applicable
approved reviewer-routing rules, without silently weakening a risky review.

Check the actual executable/adapter identity and relevant compatibility before
dispatch under that adapter's rules. A changed/missing adapter or unavailable
reviewer blocks dispatch, not unaffected preparation. Preserve the reason and
resolve it through the applicable transport procedure. No entitlement-probe
review, automatic replay of a consumed request, unchecked upgrade or fabricated
verdict. A substantive corrected stage uses a fresh request identity and retains
prior failures; a lost one-shot result does not become unused authority.

`scripts/auto_review.py` remains the canonical implementation of the legacy
bsreview command; the root duplicate remains forbidden. Its current paid-API,
Haiku and optional Notion-comment behavior is NOT this subscription workflow.
Even --dry-run calls Claude API. Do not use it as a no-cost preflight or the
default qualifying review under this decision. This instruction update does not
rewrite that program or install a general Booster CLI adapter. The automatic
handoff requirement is active; a task must still use an available verified
adapter, and must report a missing adapter rather than invent execution.

## Readiness, context and evidence

Before dispatch, complete necessary local checks and mechanically validate the
packet's syntax/schema, required bodies/edges, declared sizes, privacy screen,
input identities and scope. Estimate size before rendering. Missing paths or
virtual outputs must not trigger broad reads or noisy retries. Adapter-specific
limits remain explicit; do not impose GSS's numeric controls on unrelated tasks.

Render complete affected functions/files, callers and material contracts once.
Supply acceptance criteria, focused positive/adversarial evidence, unresolved
findings and rollback/side effects where applicable. Use a concise coverage
ledger proportional to the task. Diffs alone do not replace necessary owning
context. Prior accepted bodies may be reused by exact identity/scope with their
limitations; excerpts/omitted bodies are labelled honestly. Expand context if a
changed dependency makes the old acceptance insufficient.

Freeze the reviewed inputs and Git state for the dispatch interval. Preserve
unrelated changes. Do not let another chat/subagent commit, edit an input or alter
shared status during a review requiring unchanged Git. Recovery pointers may
advance only when excluded from frozen inputs under their established procedure.
Use parallel reads/tests on independent inputs where they do not compete for
files, resources or state. Do not create competing implementation/status writers.

Save the dispatch before one launch and retain the original controlled handle.
Save the full result/failure, requested/resolved reviewer identity, exact inputs,
closure and post-run unchanged checks. Read the complete actual response before
recording acceptance. Metadata/hash equality establishes identity, not universal
correctness or source/execution/production proof.

If Changes requested, resolve substantive findings and affected interactions
inside approved scope, strengthen discriminating tests, save and send one fresh
coherent repair stage. If a finding changes functional/business behavior, present
the concrete owner choice and continue independent work. Review OK applies only
to its exact design/CODE/result boundary; unresolved mandatory follow-ups remain.

The reviewer does not deploy, commit, publish, change Notion properties/status or
post external comments under this review-only grant. Owner QA, production/live
gates and applicable Git authority remain separate. Do not treat a stage verdict
as Done or whole-project acceptance.

## Permanent measured optimizations

Keep coherent review batches, early preflight, exact reuse of accepted context,
one accountable writer, strong independent review, bounded meaningful tests and
regular stage saves. Automate repeated deterministic checks/report fields within
scope; test a changed checker/adapter before relying on it. Keep stable context
byte-identical where useful and measure actual cache reuse rather than assuming it.

Record preparation, implementation/checks, reviewer wait and repair time where
observable; packet bytes/tokens/cache metadata, calls/fix rounds, local preparation
failures, missed defects/context gaps and integration overhead. Unknown active
time/subscription debit stays unknown. Smaller packets or unrelated stages do
not prove causal speedup, quota saving or unchanged quality.

Use the active project's recovery system. GSS additionally follows CURRENT and
its <=10-minute controllable active-work save cadence. Preserve historical
violations; local disk saves are not off-device or zero-loss guarantees.

