# Foundation — attempt3 failure and proposed next bounded gate49

Date: 2026-10-07; Europe/Kyiv. Executor: Codex, sole writer.
Review format: D075 remains active until the owner's explicit restoration command.
Status: STATIC CORRECTION PROPOSAL ONLY; no implementation/native grant.

## Observed result and exhausted authority

Genuine source47 Phase1 passed144/all122 on2026-10-07. The truthful additive48
source and DISTINCT application packets were accepted by both actual read-only
OpenAI reviewers. The single apply ran once10:50:29.125Z–10:50:41.197Z,
12077.0441ms, exit1 with closure observed and no timeout/capture fault.

Refusal: whole-candidate-before-history / external_read / externalFunctions /
SQLSTATE42703. The reviewed child reported acknowledged rollback, independently
verified original0006, aborted own transaction, released lock and closed handle.
Private current/next files are absent. No eligible literal comparison capture
was produced because this was a query error, not a source-comparison refusal.
The public root receipt independently checks artifact/hash/lineage consistency;
it performs no new DB query and does not elevate child recovery into a new probe.

The journal preserves the exact241-byte two-attempt prefix and appends only
attempt3 UUIDc0e65978-f8da-470d-9527-fdd14dfc7fd9. Maximum3 is exhausted.
The apply48 reservation and both historical attempts remain consumed/preserved.
No fourth attempt, new budget, journal replacement/reset or retry is authorized.

## Static defect and concrete proposed correction

Frozen source: work/GSS_command-ledger47_20261006/command-external-inventory.mjs,
externalFunctions SELECT at40–46. It declares a computed output alias:

```sql
p.proname||'('||pg_get_function_identity_arguments(p.oid)||')' AS signature
```

The same query orders by `signature COLLATE "C"`. That is an expression using
an output alias, which PostgreSQL17 does not resolve as a standalone output
column label. This static defect is consistent with the observed42703 category.
The value-free runtime evidence does not preserve the server's column-name/error
body, so no broader native error-detail claim is made.

Primary reference: [PostgreSQL17 sorting rules](https://www.postgresql.org/docs/17/queries-order.html)
requires an output label in ORDER BY to stand alone; it cannot appear inside an
expression. [PostgreSQL17 error codes](https://www.postgresql.org/docs/17/errcodes-appendix.html)
maps42703 to undefined_column.

Prepare an additive source copy only after the next owner decision. Keep the
output alias and all predicates/capability checks/row shape/order semantics.
Replace exactly the final sort term with its identical underlying expression:

```diff
- ORDER BY r.rolname COLLATE "C",n.nspname COLLATE "C",signature COLLATE "C"
+ ORDER BY r.rolname COLLATE "C",n.nspname COLLATE "C",(p.proname||'('||pg_get_function_identity_arguments(p.oid)||')') COLLATE "C"
```

Do not edit the frozen47 reader or DAEE archive. A new fixed import closure must
carry the corrected reader and truthful source/operation gates. Never accept
failed-state catalogs as new expected source or weaken the comparison/admission.
This is a correction to the existing exact reader, not a changed GSS outcome.

## Verification gap to close in the new design

Original0006 inspection returns before commandExternalInventory; native144/all122
proves original-state tests, not installed-only SELECT binding. Future review
must verify the actual installed reader SQL independently before granting any
application. Define a strictly bounded source-derived/read-only SQL-binding
witness if authorized; do not invoke application, create command roles or learn
from a failed catalog for this check. Review the remaining installed-reader tail
and the exact correction/import diff. Pure/fake evidence must remain labeled.

No successful native transaction-fit proof, private credential-file admission,
host authentication, installed candidate, Phase2 or whole Foundation acceptance
exists from attempt3. D070-D074 and Q1 revision2 remain closed. The planning
estimate is not advanced.

## Exact owner decision required

Authorize a new explicitly bounded continuation after the exhausted3/3 budget:
source-only corrected lane/design and independent binding verification, followed
by dual COMPLETE review and a DISTINCT one-use local application grant under a
new immutable operational record. The next design must define how that new
record coexists with the permanently exhausted historical three-attempt journal;
it must not alter its maximum, delete history or reuse any existing UUID/grant.

Recommended model: current gpt-6.1-sol / xhigh for executor and inherited peer
reviewers. Rationale: security-sensitive SQL binding, immutable evidence and
new consumed-grant boundary; no paid model escalation. Owner permission does
not replace exact peer review, fresh preconditions or production/owner QA gates.

Until that decision: preserve evidence, complete independent failure reviews
and save a scoped Git checkpoint only. No target/private operation or source fix.
