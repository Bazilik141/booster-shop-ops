# Booster Shop automatic independent review — current owner rule

Decisions: BOOSTER-AUTOREVIEW-20261014; owner efficiency adoption 2026-10-19.
The owner selected the installed Claude CLI/subscription, approved the reviewed
efficiency proposal, and confirmed: ordinary successful host receipts need no
new model call; the new procedure also applies to ongoing tasks, including ACC-009.
Durable instructions: English. Owner communication: concise, plain Ukrainian.

## Scope and precedence

This file is canonical for the review process. It replaces conflicting legacy
manual-trigger, repository-wide freeze and automatic actual-result-review wording
in general Booster instructions and procedural task notes, including older versions
of this file. Accepted evidence is retained; ongoing work does not restart.

The owner's explicit current instruction controls. AGENTS.md remains canonical
for authority/safety and ROADMAP_SOP.md for task status. Named owner task gates,
privacy/data grants, writer assignment, business scope, Git permission, deployment
and QA authority remain unchanged. A task's explicit owner-required review is not
waived by this general procedure. Do not confuse an old agent-authored procedural
repeat with an explicit owner gate. Resolve a genuine authority conflict with
one concrete owner question; do not invent blanket stricter-rule precedence that
restores the superseded procedures.

**GSS exception:** the 2026-10-19 efficiency changes do not amend GSS governance,
its frozen-state/no-replay rules, accepted transport identities, readiness gates
or continuity procedure. GSS retains the prior 2026-10-14 shared review requirements
and its specific rules. In particular, its applicable Git freeze and separate
actual-result reviews remain. No GSS task is resumed or grant renewed here.
The superseded 2026-10-14 shared text stays in force for GSS only. It is reproduced verbatim in the appendix "GSS-retained 2026-10-14 shared review text" at the end of this file (source SHA256 4d4fd0a511bfc6918b95880ec178d8a5aee233e20867b32819e9b3df4944dec8 as pinned in changes.json). Where it differs from the sections above, the appendix controls for GSS.

## Required review and proportional stages

Codex automatically initiates actual Claude review at a ready required stage.
The owner need not copy prompts or authorize each routine in-scope review.
The executor handles preparation, complete result capture, checks and scoped
repairs. Subagents are additional internal assistance, not actual Claude verdicts.
The author cannot approve its own work; other-executor assignments retain their
applicable independent-review routing.

Use coherent stages proportional to risk, not a mandatory number of calls:

- Design review when architecture, authority or interactions are new, unresolved
  or contested, or an explicit task gate requires it.
- CODE/composition review of new or changed logic, including every risky-zone
  change, after meaningful local checks and before owner application. Supply the
  intended runner, admission conditions, backup/restore/rollback and receipt
  contract together; avoid a late implementation round merely to remove HELD.
- Model assessment of actual results when there is a substantive deviation,
  ambiguity, partial evidence or a decision not settled by the accepted criteria.
  A complete expected receipt closes mechanically. Owner live QA stays separate.

Reviewer findings are explicitly mandatory or optional, with affected scope and
the condition for closure. The executor cannot downgrade or defer a mandatory
finding without the reviewer's reasoned disposition or the owner's informed scope
decision. Repair substantive findings and interactions as one batch. New logic,
material dependency changes, mandatory behavioral repairs, new risk/authority/data
scope or unresolved result deviations require the applicable fresh review.

Unchanged hashes, report bookkeeping, harmless formatting and reviewer-specified
literal prose corrections need deterministic checks, not a separate model call,
provided they change no behavior, authority, privacy, executable interpretation
or acceptance meaning. If that equivalence is uncertain, use the next substantive
review. The old verdict remains bound to its original bytes; append the exact diff
and mechanical equivalence evidence rather than pretending it reviewed new bytes.
Optional improvements are recorded, not automatic new tasks or provider rounds.

After two consecutive substantive returns in the same interaction class, inspect
the owning interaction map and fixture fidelity before resubmission; tell the
owner what remains. This is escalation, not an arbitrary round cap or permission
to release known defects. Re-baseline expanded business/risky scope concisely.

## Evidence, dependencies and tests

Use one current task report or recovery ledger, not a new parallel status system.
Keep brief sections for delivered/HELD/open work, accepted identities and limits,
dependency/impact set, real-versus-fake test boundaries, material unknowns and one
next action. Append historical results; superseded pointers create no obligations.
Notion is canonical task status; ROADMAP_FLOW is its mirror; context-index is routing.
Small tasks need only a few relevant lines. Do not create separate artifacts for
each section or collect unrelated source merely to populate a checklist.

Before collecting source, map the bounded write/call path. Separate facts needed
for design from host facts needed before application. Batch predictable sources;
a supplement must name the missing fact and why existing evidence cannot answer it.
Use the smallest sufficient complete affected functions/files, callers and material
contracts. Label excerpts and missing context; a diff alone is insufficient when
owning context is material. Reuse accepted context by exact identity/scope and state
what earlier acceptance is relied upon; do not claim omitted bodies were read now.

At dispatch declare the dependency/impact set: packet inputs, owning sources and
shared assets/callers, applicable events/OCMOD/config/schema facts, and tests,
harnesses, adapters and instructions on which acceptance relies. Use file hashes
or approved evidence references, not raw credentials, table contents or broad
config reads. Include live paths, anchors, asset tokens and tables targeted by
patches so interactions outside packet files are discoverable.

Test fidelity matters more than counts. Identify actual native/generated code and
each fake boundary with its reason and limit. Cover one realistic success path
and discriminating failure/transition cases appropriate to the change. UI work
uses its real generated markup, declarations and material style/script injection;
backend/documentation work has no artificial browser requirement. The reviewer
judges material fake boundaries. Rerun checks affected by changes or open findings;
do not repeat unchanged suites or tests that only restate implementation.

## Freeze, composition and mechanical closure (non-GSS)

Freeze declared input/dependency content for the review interval. One accountable
writer; no competing code, Git or status writers. Branch/release-wide certification
requires its explicitly declared broader freeze; normal artifact review does not
pause every unrelated chat's Git work.

After the run compare declared identities and record changed repository paths:

- Changed behavior or a material/uncertain change inside the set makes the verdict
  inapplicable to that changed scope until reviewed. Nonsemantic changes use the
  narrowly defined mechanical path above, with the original assessment preserved.
- Unrelated outside-set changes do not invalidate an unchanged content verdict.
  Check their targets/impact against the reviewed patch, including other runners
  editing the same live path, anchor, token or table. If disjoint, record and close;
  if overlapping, block release for a composition check and review substantive
  interaction changes. Unexplained relevance blocks a positive composition claim.
- Repository identity does not establish current host state. Before application,
  host target/source/metadata facts must satisfy the reviewed admission pins.
  A mismatch is STOP: reassess/regenerate and review affected logic, never blindly
  adapt the runner on production. Matching fresh host pins need no duplicate review.

Mechanical closure records pre/post input/dependency identities, actual reviewer
model/effort/transport, full response/failure, verdict and unresolved findings.
Save the original response and exit before post-checks. Reconcile a stale wrapper
with an appended record; never erase a failure or fabricate success. Identity is
not universal correctness, execution, integration or production proof.

## Owner evidence and privacy

Validate the complete machine payload strictly against the reviewed receipt
contract. Permitted wrapper/echo normalization must be deterministic and recorded;
never repair, omit, join or reinterpret missing/contradictory machine rows. Distinguish
a valid receipt from proof of byte-identical command execution. If byte identity
is material to the claim and absent, record that limit or request the needed fact.

Repeat or supplement an owner action only for a named material missing fact,
incomplete/contradictory/invalid payload, or relevant host change since capture.
Do not repeat solely for harmless echo formatting, local tool/report changes or
unrelated Git drift. Prefer one narrow missing fact over rerunning a broad command.
Owner deployment/QA remain required; expected output does not substitute for QA.

Automatic review may disclose necessary approved internal code, sanitized technical
diagnostics and fictional tests through the subscription. Never disclose credentials,
account/config secrets, customer/payment/business records, bulk backups or unapproved
live payloads. Source privacy is not waived by a generic review grant.
Use reviewed bounded capture/sanitizer utilities. Embedded literals remain redacted
unless on an owner-approved allowlist of exact paths/patterns/technical values;
ordinary public syntax/symbols are not a new data grant. Review material sanitizer
or allowlist changes, not identical executions. Current exact-subset permissions
remain exact; this policy grants no new raw-body/literal access. GSS data gates remain.

## Subscription transport and context economy

For ordinary non-GSS work, follow `docs/BOOSTER_LOCAL_ACCESS.md`: reuse the saved
user login and resolve the installed native CLI with the read-only shared helper.
A new chat, missing PATH command, quota event or sandbox error is not a login
failure. Diagnose the actual default config/auth status before owner renewal;
never create per-task credential stores or reset keys. This routing does not
replace reviewed exchange adapters or alter GSS's accepted transport/freeze rules.

Owner amendment 2026-10-19: Codex exchanges task, command-drafting and review
packets with actual Claude through the installed subscription CLI; no owner
relay to Claude chat. For owner-run PowerShell, Claude CLI drafts and Codex
independently checks the commands; drafting and required ready-stage review
are separate requests. The command author cannot be its sole approver. Bind
the final patch and command-packet hashes in the ready-stage record. This
replaces the former Claude-chat author requirement, not any privacy, GSS,
SSH/key, deployment or QA gate.

Use the installed, verified Claude CLI/saved subscription, with model and effort
recorded, tools/MCP/hooks disabled and no API-key or paid-provider fallback. Risky
design/CODE/security, checkout/payment/CRM/DB/SEO/schema/authority/recovery work
retains actual Opus/high; low-risk work uses its approved routing. No silent downgrade.
Check executable/adapter compatibility and saved subscription auth without logging
private account state. Strip provider/auth override variables from the child only.
Never load secret files, enable overage, upgrade unchecked or probe entitlement by
commissioning a review. Unavailable review blocks dispatch, not independent preparation.

Save a fresh dispatch identity before launch, keep the original controlled handle,
capture complete output/failure and read the full actual answer. Never silently
replay a consumed request; a substantive corrected stage uses a fresh request with
history retained. The reviewer cannot deploy, commit, publish, write external status
or send messages under this review grant. No automatic future execution is promised.

Legacy bsreview uses scripts/auto_review.py and a paid API, including --dry-run;
it is not this subscription route. The repository-root duplicate remains forbidden.
Do not invoke it as a no-cost preflight or authorize its optional external comments
through this policy. No general launcher/active adapter is installed or rewritten here.

Validate packet syntax, paths, sizes, privacy and required context locally before
dispatch. Keep stable context byte-identical and first where useful; measure actual
cache reads rather than assuming identity reuse creates provider cache hits. A large
packet needs a dependency justification, not an arbitrary universal token threshold.
Batch related repairs; do not truncate material context merely to hit a size target.

Record observed calls, outcomes, context/output/cache usage and time where available.
Usage is workload, not avoidable waste, subscription debit or money charged. Quantified
waste claims need per-request stage/verdict/mandatory-findings evidence; unknowns stay
unknown. Do not launch synthetic cache or review probes solely to claim savings.
No procedure guarantees first-deploy success. Preserve rollback and honest limits.

## Appendix — GSS-retained 2026-10-14 shared review text (GSS only; not in force for other Booster series)

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

