# GSS working agreement

Date: 2026-09-28
Authority: explicit owner instructions in the GSS planning conversation.
Applies to: GSS series only, currently planned inside the Booster Shop ops repository.

## 1. Priority and scope

This is the GSS-specific working contract referenced by root `AGENTS.md`.
Current explicit owner instructions override it. Within GSS, it overrides
conflicting older Booster role, authorship, Git-authority and sequence rules.
Outside GSS, existing Booster rules remain unchanged. GSS work touching a
shared production integration still needs its existing deployment/data-write
authority; an identifier prefix does not waive that boundary.

The consolidated functional baseline revision 1 was approved under D059 on
2026-09-30; see `../plans/GSS_scope-baseline_20260930.md`. Current work advances
to architecture and decomposition. The earlier 18-package draft is an inventory
of candidates, not an approved implementation backlog. Functional approval does
not settle explicit open rules or authorize code/deployment before task readiness.

### Product access authority

Under GSS-D019, the owner has full access within their organization and can
select a user and grant/revoke access to individual GSS blocks/sections.
Do not hardcode access by named person or fixed role. Configurable roles may
provide reusable setup templates, but must not prevent individual access
configuration. Earlier Darina/Serhiy profiles are examples, not fixed grants.
GSS-D020 additionally confirms configurable actions (view, create, edit, export,
approve) and record scopes (own, assigned or all within the organization).
D054 confirms linked role templates: changes propagate to assigned users,
individual exceptions persist, and explicit denial takes precedence. Show
affected users and grants before saving role changes. Detailed composition,
domain-specific ownership/assignment and revocation still need design.
A section grant alone does not
grant every action or record. Access enforcement must cover underlying operations and data, not only
menu visibility, including search, exports, integrations and agent access.
Full owner access does not imply access to other companies' data.

### Modularity and customization requirement

GSS-D026 (2026-09-29) requires an extensible foundation, business modules,
integration points and native UI composition with minimal unnecessary broad
rewrites. Every block's design must identify its owned data/rules, dependencies,
public operations and extension/customization points, and assess impact on
other modules. Preserve organization isolation and D019/D020 permissions across
extensions. Module availability, user authorization and UI layout are distinct.
GSS-D027 accepts the architectural direction described in
`plans/GSS_modularity-proposal_20260929.md`: small kernel, shell, business
modules, connectors and composable UI, initially one modular application.
Concrete contracts and technical design still follow the agreed review sequence.
This requirement must not delay the first working roadmap behind a universal
plugin marketplace or a no-code builder that the owner has not requested.

### Product security requirement

GSS-D053 requires deliberate security design from the foundation because GSS
may later be sold/distributed beyond Booster Shop. Every affected block must
identify its trust boundaries, sensitive information, abuse/failure cases and
required verification evidence. Assess organization isolation, user and agent
authorization, credential handling, files/exports, integrations/extensions,
auditability, recovery and secure maintenance across the product lifecycle.
Treat this as design work to be specified and verified, not a claim that the
current plan or existing NCRM code is already secure. Concrete controls,
hosting/distribution choices and commercial-release gates remain open.

D060 selects password plus authenticator-app code, with mandatory use configured
by the owner or authorized administrator by role/person. D061 adds optional
owner-controlled IP/browser restrictions, with implementation deferrable after
effort assessment. D062 accepts daily Supabase DB backups for the initial roadmap;
file protection and demonstrated recovery remain separate requirements. These
decisions refine security design without prescribing a fixed role-specific policy.

## 2. Equal agents, one assigned executor

Codex and Claude have equal authority to architect, implement, propose
solutions, review each other and commit/push their assigned GSS work. Claude
may use Claude Code or another available execution surface. Practical tool
limitations do not transfer ownership automatically.

The owner assigns each task. One assigned executor owns its implementation,
task-local evidence and scope accounting. Review does not authorize the
reviewer to overwrite the executor's files concurrently. A handoff/reassignment
must make writer ownership explicit.

D064 (2026-10-03) permits either executor to request peer review directly from
the other, discuss findings and iterate fixes within the approved assigned task.
This authorizes task-specific review communication where tools permit it; it
does not transfer implementation ownership or authorize unrelated messages.

Commit/push permission is a standing owner grant for approved, assigned GSS
scope, not a fresh permission question at every commit. Inspect the diff and
staged set, include only task-owned files/hunks, preserve unrelated changes,
and report what was committed/pushed. Do not force-push or rewrite shared
history under this routine grant. Follow the repository's autosync sentinel
procedure when performing Git writes. Git permission is not deployment or
live business-data write permission.

Both agents may challenge a decision and propose an alternative. Neither may
silently substitute an outcome the owner has approved.

## 3. Owner decisions and changes

Record an owner proposal/approval that changes the initial task direction as
an owner decision. A decision entry contains:

- stable decision ID and date;
- owner instruction or a faithful summary;
- accepted outcome and scope;
- affected blocks/tasks, processes, interfaces, data and acceptance criteria;
- what earlier decision/proposal it supersedes;
- evidence/reference and unresolved follow-ups.

D049 confirms that an authorized agent may record an actual owner approval
with its source/provenance. The owner remains the approver of functional
changes and acceptance. Executor evidence and independent reviewer findings
are separate attestations; an agent cannot approve on the owner's behalf.

D066 requires chat-derived decisions recorded in GSS to remain pending until
the authenticated owner confirms the exact target there. Provide Confirm/Reject
and explicit batch confirmation; agents cannot verify their own pasted evidence.
This governs future GSS records/imports, not the validity of direct owner
instructions during pre-GSS planning. D067 selects an in-app attention counter
initially, with Telegram notifications as the next extension.

Preserve history: an amended decision supersedes an earlier one rather than
erasing why the implementation changed. Distinguish accepted owner decisions
from executor recommendations and unresolved questions.

Changes to final functionality require the owner to be informed and approve
the change before dependent implementation. Routine technical choices inside
the approved behavior, constraints and authority are the executor's work.
When a review exposes a missing functional decision, present the consequence
and a recommended choice; continue unaffected work.

## 4. Autonomy and owner involvement

Executors carry authorized work through source inspection, design, local
implementation, tests, review fixes, documentation and scoped Git operations.
Do not delegate tool-accessible diagnostics or routine commands to the owner
merely for convenience.

Involve the owner for business/product decisions, final interface QA, disputed
tradeoffs affecting outcomes, and operations impossible or disproportionately
costly with available tools. Present a concrete, reviewable proposal and its
effect rather than an open-ended permission request. Existing production
gates remain until expressly changed.

Owner QA is not a substitute for executor testing. Label source inspection,
local tests, integration tests, deployment, live proof and owner acceptance
separately.

## 5. Definition before implementation

D064 confirms that approval of a sufficiently designed block plus assignment
of its executor covers autonomous preparation/execution of its scoped subtasks;
do not request permission for every routine step. Independent research, technical
choices, implementation, testing/fixes, scoped refactoring, documentation and
task updates are included. Owner gates for functional changes, assignment,
cross-block priorities, paid activation/spending increases, production/live-data
changes and applicable owner QA remain. Pause only the dependent work when a
decision is needed. D065 approves block-level autonomy, bounded subagents,
automatic progression through ready assigned work, routine technical subtasks,
review/fix loops, local checks and consolidated reporting. The accepted detailed
policy is `../plans/GSS_autonomy-options_20261003.md`. Cross-block progression
requires an explicitly owner-approved queue; assignment and owner gates persist.

The queue is an immutable ordered version with an executor and permitted phase
per entry and a matching owner-confirmation reference. Modification requires a
new confirmation; readiness never supplies missing queue authority. Current
queue authority is recorded in the working map: D069 approves Q1 revision 2, foundation -> roadmap -> adoption -> Telegram, with Codex execution and Claude review. Adoption includes D048 reconciliation, trial import, restore drill and owner-gated canonical cutover.

The executor proposes optimal continuous-execution segments within each block:
scope, deliverable, dependencies, verification/review, delegation if useful,
natural boundary and stop conditions. The owner should not have to guess these
boundaries. Record them in the working roadmap and explain them in the nub opening.
This adds planning visibility, not repeated approval for routine subtasks.
Subagents inherit scoped authority; avoid competing file/Git/status writers and
retain one accountable primary executor. A subagent review does not replace
required other-executor review or owner QA.

The owner requires two rounds on retained and new functionality: one with
Codex and one with Claude. Both use the current audited system and the same
owner decisions. Record agreements, alternatives, missing processes and
contradictions in one comparison register. The owner settles the functional
boundary before technical implementation starts.

Owner-approved sequence (2026-09-28, GSS-D014; future amendments remain possible):

1. Codex + owner review the capability catalogue: keep, redesign, add, defer,
   retire; state actor, outcome, inputs/outputs and related processes.
2. Claude reviews and extends that catalogue, explicitly identifying omissions
   and alternative solutions rather than treating the first round as settled.
3. Consolidate both rounds and obtain the owner's overall functional boundary.
4. Work out the system architecture and cross-process contracts; decompose the
   plan into blocks -> tasks -> subtasks with acceptance evidence.
5. Prepare each block and each task deeply before its implementation. Resolve
   material cross-block dependencies early; keep speculative detail out of
   implementation until the relevant decisions are settled.
6. Build the required foundation, then deliver the roadmap as the first working
   feature. Manage later accepted work through that roadmap after its own
   source-of-truth transition is defined and accepted.

GSS-D015 confirms the design cadence: agree the overall system logic and
cross-process relationships in advance, then prepare detailed technical
specifications sequentially before each block. This is not permission to code
an under-specified task or to postpone a dependency decision needed now.

D064/D065 authorize routine scoped other-executor review requests and bounded
subagents without new per-request owner permission. Tool availability must be
verified; task ownership and owner decisions are not transferred by dispatch.
Unrelated collaboration or reassignment remains outside that grant.

GSS-D033 clarifies delivery: provide owner-visible increments for testing and
gradual adoption, not a single unseen all-at-once operational release. Earlier
first-launch language denotes prioritized scope, not an obligation to finish
all those capabilities before owner QA. Keep the foundation/roadmap-first
sequence and explicitly distinguish preview/test readiness from live adoption.
For each live transition, specify the authoritative system and active writers
for the affected data/process; partial delivery must not create two competing
stock, order or financial masters. Production deployment/write gates remain.
Integration priorities follow D030–D032: Nova Poshta first among carriers,
Checkbox strongly desired, Ukrposhta/Meest potentially deferred indefinitely,
and banking low/very low depending on complexity. No fixed dates are implied.

## 6. Block and task readiness

GSS-D035 sets a provisional all-information migration direction. Divide data
into logical migration blocks and obtain a separate owner decision per block
before actual transfer. Prepare source coverage, quality gaps, proposed target
representation, dependencies, reconciliation and rollback for that decision.
Do not treat the general direction as blanket import approval or silently
exclude difficult records. Linked blocks still need a consistent cutoff and
cross-block reconciliation; separate approvals do not make their data independent.

GSS-D034 confirms browser delivery with a full desktop interface and a
phone-adapted interface. Include appropriate desktop/phone acceptance scenarios
in UI-bearing blocks. Separate installable apps and offline operation are
possible later extensions, not prerequisites for initial working increments.

GSS-D024 adds a mandatory owner discussion before coding reservations,
corrections, cancellations, returns or money accounting. Capture the owner's
outstanding questions and desired behavior, work through scenarios and related
stock/order/payment/production/settlement/integration effects, then obtain
approval of the resulting functional rules. Broad catalogue acceptance does
not satisfy this prerequisite. Record it in the affected blocks/tasks and
dependent work; do not embed provisional rules in earlier implementation.
If an unresolved rule affects foundation design, surface that dependency
earlier rather than silently choosing a default. Reservation before payment
and hold/expiry behavior remain proposals until this clarification occurs.

GSS-D045 confirms two stages: settle architecture-shaping D024 questions before
ledger-module design, then detailed rules before each affected block. The
roadmap foundation is not blocked by unrelated financial details.

Every block has a short owner-facing opening summary using the `nub` skill:

> Scope: what this block includes and where its responsibility ends.
> Result: what the owner/staff will be able to do when it is accepted.
> Architecture: in plain Ukrainian, which parts exchange/store information.
> Model: recommended model and reasoning/thinking depth, with a brief reason.

Use the same summary for an individual task when its complexity, risk or new
decision warrants it. Durable specifications may be English; owner summaries
are concise Ukrainian.

Before implementation, the block/task specification must address the relevant:

- user workflows and permission boundaries;
- entities, invariants, state transitions, input/output/API contracts;
- dependencies in both directions and effects on other business processes;
- normal, exceptional, cancelled, repeated, partially failed and reversed cases;
- financial, stock, date/timezone and identity consequences;
- integration failure/retry/concurrency behavior;
- migration, compatibility, observability and rollback;
- concrete acceptance criteria and appropriate automated/manual QA.

Apply proportional depth: a small task can inherit an established block
contract and identify its exact delta. Do not skip analysis of affected
processes, but do not repeatedly reopen unrelated settled systems.

## 7. Working roadmap requirements

GSS-D047 requires a dedicated naming/numbering/hierarchy design before roadmap
implementation. Distinguish stable identity, parent-child structure, dependency
links and visual order; explicitly address insertion, reparenting, splits,
aliases/reference continuity and concurrent user/agent creation. Do not grow
unbounded letter suffixes or infer a task's relationship from creation order.
D068 (2026-10-04) selects short non-sequential codes such as GSS-K7Q2; exact alphabet/length require technical sizing, and no live task IDs are allocated here.
D052 accepts a permanent short identifier separate from parent/block,
ordering and dependencies. Inserting or moving work must not rename other
tasks or require growing letter suffixes.

D051 makes the repository canonical for detailed technical specifications.
GSS shows the nub summary and access to the specific approved version; avoid
independently editable specification copies. Preserve access restrictions.

D050 requires task-type readiness profiles: design, implementation, peer
review, owner QA, deployment and live adoption where applicable. Documents
need no deployment gate. Define applicability and transitions during detailed
design; all applicable gates need evidence before completion.

Use hierarchy: block -> task -> subtask. Maintain stable identities and explicit
dependency links. Record implementation evidence and owner decisions alongside
scope, rather than burying them in chat or a single overloaded status field.

For every block, the current working roadmap must show:

- intended result and scope, with dependencies and acceptance criteria;
- assigned executor when the owner has chosen one; reviewer when designated;
- recommended AI model and reasoning/thinking depth, plus brief rationale;
- relevant owner decision IDs, design/readiness evidence and remaining gates.

The model field is a recommendation, not permission to choose the task's
executor or silently change the running session's model. Verify current model
availability/capabilities at the time of recommendation; do not copy the old
July model table as a permanent GSS default. When either executor could take
a block, recommendations may give an option for each platform until assigned.

Until GSS roadmap acceptance and an explicit authority transition, the existing
Notion roadmap remains canonical and the owner HTML remains its mirror under
the current repository rules, with this owner-approved writer exception:
the assigned executor, Codex or Claude, creates and updates its approved GSS
tasks/properties/statuses in both places. Keep one writer per task, avoid
colliding IDs, preserve unrelated records and record closure evidence. Required
owner QA/acceptance is not replaced by a status write. No permanent two-master
task sync is implied.

The first GSS roadmap manages GSS development only. Other Booster Shop tasks
move later under a separately designed migration. Define an explicit per-scope
source-of-truth transition when the GSS roadmap is accepted; do not leave
Notion, the old mirror and GSS as competing editable masters.

D048 requires factual review of every candidate task before the interim GSS
roadmap migration: compare original and current scope, actual deliverables,
verification/acceptance evidence and remaining relevance/work. Preserve the
source status as history rather than using it as proof. Resolve uncertain
status/disposition, retain old-to-new references and decisions/evidence, and
avoid reintroducing completed or superseded work as an active backlog.
Archive old GSS records with pointers only after verified cutover. Legacy
NCRM/CRM/3D history remains linked under D017, not blindly imported.

## 8. First working feature: roadmap

The owner has moved the roadmap from the old optional final phase to the first
working feature after the necessary foundation. It receives the same deep
product/technical preparation as every other block.

Foundation scope must be enough for a real, usable roadmap: app/runtime,
database/migrations, authentication and organization/access boundaries,
auditable operations and a reliable development/test/release path. Do not use
the foundation as a reason to finish the entire sales/stock ERP first.

Confirmed coverage: GSS tasks initially, other Booster tasks later.
GSS-D016/D017 also confirm an owner interface plus controlled agent task access
in the first release, and links to prior NCRM/CRM/3D decisions rather than full
legacy history copying. Detailed agent operations and safeguards remain to be
specified before implementation.
GSS-D018 confirms three further first-release capabilities: a whole-plan tree
and lists of ready/blocked work with reasons; in-task comments, review findings
and executor replies; separate design, code, peer-review and owner-QA readiness.
A Done label cannot conceal outstanding required gates.
The detailed design must connect these capabilities with hierarchy,
dependencies, assignments, model/depth recommendations, owner decisions and
review/QA evidence. Remaining proposals still require owner scope approval.
Full old-history import is not included. All-company task coverage is
explicitly deferred until after the initial GSS scope.
