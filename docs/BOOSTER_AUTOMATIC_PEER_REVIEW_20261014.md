# Booster Shop automatic independent review — current owner rule

Decisions: BOOSTER-AUTOREVIEW-20261014; owner efficiency adoption 2026-10-19;
owner review-point reduction and CRM/dashboard exception 2026-10-21. For non-GSS
tasks assigned to Codex, the default is one integrated independent final review
of the completed package, except routine bounded work on the existing main CRM
Google Sheets workbook and canonical dashboard, which has no Claude review by
default.
Codex prepares the patch and its owner-run apply packet where applicable. Early
design, domain-exception and actual-result reviews require the owner's route
choice. This applies to ongoing tasks, including ACC-009. Ordinary expected host
receipts still close mechanically.
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

**GSS exception:** the 2026-10-19 and 2026-10-21 non-GSS efficiency and review-point
changes do not amend GSS governance, its frozen-state/no-replay rules, accepted
transport identities, readiness gates or continuity procedure. GSS retains the prior 2026-10-14 shared review requirements
and its specific rules. In particular, its applicable Git freeze and separate
actual-result reviews remain. No GSS task is resumed or grant renewed here.
The superseded 2026-10-14 shared text stays in force for GSS only. It is reproduced verbatim in the appendix "GSS-retained 2026-10-14 shared review text" at the end of this file (source SHA256 4d4fd0a511bfc6918b95880ec178d8a5aee233e20867b32819e9b3df4944dec8 as pinned in changes.json). Where it differs from the sections above, the appendix controls for GSS.

## Default review and owner-selected exceptions (non-GSS)

For a non-GSS implementation assigned to Codex, the standard route is one
integrated independent Claude CODE/composition review after meaningful local
checks and before owner application. Codex owns routine design decisions and
prepares the patch plus the exact owner-run apply packet. The final review covers
the concise design rationale, changed logic, runner, admission conditions,
backup/restore/rollback, receipt contract and both exact hashes together.
Risky-zone code remains in this final review when the task is routed to review.
Owner application and live QA stay separate. A task explicitly assigned to
another executor retains its applicable independent-review routing; no author may
approve their own work. When this package is ready and this policy requires review,
Codex automatically initiates the single final review through the installed
subscription CLI; the owner need not authorize each routine final review separately.

### Routine main CRM Sheets and dashboard work — no Claude review by default

For Codex-executed non-GSS work confined to the existing main CRM Google Sheets
workbook and canonical `dashboard/booster-dashboard.html`, skip a separate Claude
review by default when the change is bounded, the relevant schema/data contracts
and existing workflow are known, and no exception trigger below applies. This
covers read-only diagnosis, bounded corrections to existing rows/cells or
formulas, routine dashboard calculation/presentation fixes, and narrow bug fixes
in an existing CRM/dashboard path. It does not authorize a live write, deployment,
new data access, or a broader scope.

The CRM risky-zone safeguards still apply: verify the source and affected cells
or code path, preserve formulas and dependencies, record bounded evidence and
rollback facts, and run one focused local/syntax/smoke check when code is changed.
For any main-CRM structural change, add/remove in `Товари`, `РРЦ`, `Розхідники`
or `Майстер_Товарів`, or formula-column edit, run the required read-only CRM
integrity check before and after and record its bounded output. The owner retains
approval for business values and live mutations, deployment where applicable,
and final QA. No-Claude routing changes only the independent review call.

Propose an exception when work changes sheet structure or formula architecture;
adds an integration, authentication or permission path; materially changes
accounting, stock or order logic; performs broad/destructive mutations; or has
material unresolved cross-system dependencies or high-impact ambiguity. An
explicit owner request for independent Claude review also routes the work to
review. Do not infer an exception solely from the CRM risky-zone label. Before a
separate exception review, present the owner with the exception card and route
choices below. If the owner chooses no separate call or a narrow subagent check,
record the block for special attention in the general review/report as described
there; unresolved blocking decisions remain open. Do not claim Claude approval
when no Claude review occurred.

Do not create a separate early design review merely because a choice is new,
complex or risky. Codex normally resolves bounded design choices, records the
rationale and presents the finished result in the integrated final review. An
early design review is an exception only when an explicit owner gate requires a
pre-code review, the owner requests independent Claude input before a decision,
or a materially consequential architecture/authority/data interaction remains
unresolved and cannot be safely closed by reviewing the implementation before
application. A Claude design review does not replace the final CODE/composition
review.

An actual-result review is also an exception, not a routine stage. A complete
expected receipt and outcomes mechanically covered by accepted criteria close
without a model call. Propose a result-review exception only when an unexpected
material outcome is ambiguous or partial and could change a continue/stop,
rollback, acceptance, security or business decision. Known fail-closed outcomes
are recorded as such; they do not need Claude merely to repeat the classification.
Owner deployment and live QA remain separate.

Before any separate early-design, CRM/dashboard-domain-exception or actual-result
review, Codex sends the owner a short exception card: stage and evidence; why the
default route (including the no-review CRM/dashboard route, when applicable) or
mechanical criteria are insufficient; the consequence of deferring; and a
recommended route. The owner chooses one:

1. **Separate Claude review.** Send one bounded actual Claude request for that
   question. An early design review is followed by the normal final CODE review.
2. **No separate review.** Put the block in the general review package for
   special attention. For design, this is the final CODE/composition review.
   For actual results, include it in the next task-level general review; do not
   silently create an extra narrow call. If no later general review is planned,
   state that it remains unreviewed in the outcome report and keep material
   unresolved decisions open/HELD.
3. **Narrow subagent check.** The executor may launch a subagent only for the
   stated question and bounded evidence. Record its conclusion and limits, then
   flag the block for special attention in the general Claude review. A subagent
   is internal assistance, not a Claude verdict, and cannot waive a required
   final review, owner decision, deployment gate or QA.

The route choice controls review routing only. It does not authorize scope,
data access, production action or substitute for an owner decision. If the owner
chooses no separate review, do not label the block as Claude-approved. If
implementation depends on the unresolved design or result, pause that dependent
work until the owner resolves it or the required evidence is available. If a
separate review is selected, do not initiate it before the owner's choice.

Repair substantive reviewer findings and their interactions as one batch.
Material behavior changes, new logic/dependencies, new risk/authority/data scope
or unresolved result deviations require the applicable fresh review. Do not
review each finding separately. A complete expected receipt closes mechanically.

Reviewer findings remain explicitly mandatory or optional, with affected scope
and a closure condition. The executor cannot downgrade or defer a mandatory
finding without the reviewer's reasoned disposition or the owner's informed
scope decision. Optional improvements are recorded, not automatic new tasks or
provider rounds.

Unchanged hashes, report bookkeeping, harmless formatting and reviewer-specified
literal prose corrections need deterministic checks, not another model call,
provided they change no behavior, authority, privacy, executable interpretation
or acceptance meaning. If equivalence is uncertain, use the next substantive
review. Preserve the original verdict's exact byte binding and append the exact
diff and mechanical-equivalence evidence; do not imply that the old verdict
reviewed new bytes.

After two consecutive substantive returns in the same interaction class, inspect
the owning interaction map and fixture fidelity before resubmission, then tell
the owner what remains. This is an escalation check, not a round cap or a reason
to release known defects. Re-baseline expanded business or risky scope concisely.

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

Owner amendment 2026-10-21: For Codex-executed non-GSS work routed to review,
Codex prepares the owner-run apply packet from verified task facts and performs
local syntax, path, hash, privacy and receipt-contract checks. Do not spend a
separate Claude CLI request drafting that packet by default. Actual Claude
independently reviews the final patch and packet together in the single ready
CODE/composition review; bind both exact SHA-256 values in that review record.
The packet author cannot be its sole approver. Owner-selected exception routes
are defined above. Routine existing CRM/dashboard work follows the specific
no-review default above. This changes command authorship and review staging only;
it grants no SSH/key access, production authority, paid API fallback, data
permission or owner-QA waiver.

Use the installed, verified Claude CLI/saved subscription, with model and effort
recorded, tools/MCP/hooks disabled and no API-key or paid-provider fallback. For
tasks routed to review, risky design/CODE/security, checkout/payment/CRM/DB/SEO/
schema/authority/recovery work retains actual Opus/high; low-risk work uses its
approved routing. No silent downgrade.
Actual checked subscription exchanges follow the native profile/refresh execution
boundary in `docs/BOOSTER_LOCAL_ACCESS.md`: scoped approved normal-user launch,
with Claude tools/MCP/hooks still disabled. This is not a global sandbox bypass
or SSH/key/production grant. Read-only status alone cannot prove refresh access.
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

