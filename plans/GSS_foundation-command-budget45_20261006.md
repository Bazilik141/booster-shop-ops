# GSS Foundation — bounded maintenance transaction budget45

Date: 2026-10-06
Executor: Codex; sole implementation, database, Git and status writer.
Independent reviewer: Claude Opus5.5/high; risky last-attempt and privilege scope.

## Scope and accepted lineage

Actual44 accepted source44 and granted one fresh original/S0 Phase1. Its seal
exists, but that native grant has not been invoked. I165 recommends measuring
before Phase1 if a timeout correction may require another COMPLETE review.
All frozen44 inputs, verdict and seal are retained. Source45 changes only the
maintenance transaction timeout from 60000 to 180000 milliseconds. The idle
timeout stays60000; fixture timeouts stay10000; statement/lock stay8000/5000.
The explicit SESSION fence, DDL readback and exact ambient comparison derive
from the same envelope function. No product session duration changes.

D069-D074 and Q1revision2 remain closed. D064/D065 cover this local technical
correction, not production or business data. A new actual COMPLETE45 review
and a newly bound fresh Phase1 are required. No owner-only attempt gate is
inferred. A distinct actual single-attempt review remains mandatory afterwards.

## I165: measured evidence and limits

The original capture36 collector omitted individual elapsedMs. We do not invent
or retroactively insert it. Codex app commandExecution metadata reports16937ms
for the serial apply2-plus-independent-reconcile command. This is a conservative
upper bound for its BEGIN-to-refusal prefix, not a successful apply transaction.
The phase was assigned before whole-candidate inspection began; that inspection
did not finish. The native private-file phase has never run.

Five actual public-byte/readonly-filesystem timing samples (44):

| Operation | Maximum ms | Evidence boundary |
|---|---:|---|
| Complete current2 real-byte admission |1370.200|1458 inputs, real accepted gate|
| RAM3 actual full gate composition |1003.428|1458 actual bytes; synthetic review/journal/proof only in RAM|
| One readonly ACL operation including current2 admission |2784.996|actual metadata path; no private write|
| Four ignored-path checks |209.642|readonly Git queries|
| Ordinary synthetic nonsecret file fsync |3.645|not a private-path write proof|

Use the greater gate sample to plan the heavier admitted chain. The observed
private-write components project to 2*2784.996+209.642+3.645=5783.279ms.
The prefix upper bound plus these samples is22720.279ms. Even without charging
the unfinished candidate inspection, history/final inspection and COMMIT,
3*22720.279=68160.837ms exceeds the old60000 limit.

Planning allocation: 60000ms for the entire apply transaction, including a
16937ms historical prefix allowance, 5783.279ms sampled file components and
37279.721ms explicitly unmeasured tail/variation allowance. New180000 gives
3x this planning allocation. This is a justified bounded engineering allowance,
not a proved worst-case bound or native success prediction. OS/antivirus stalls,
timeout-free Git/fsync/readback and new native tail remain uncertain. Keeping
idle60000 prevents an idle transaction from waiting the full180000.

The independent reviewer must assess this explicit justification. Attempt3 is
still withheld: no rehearsal, new keys, journal append or private-file write is
authorized by this packet. Failed-catalog learning is prohibited.

## I166: rendered admission evidence

Render the actual44 predispatch witness and its complete checker. Both missing
and mutable published-archive variants actually rejected in RAM;15 total cases.
New45 uses the same translated guard and will run the same15 cases before
dispatch. No frozen44 wording or evidence is rewritten.

## Validation and next bounded action

Run the existing source-only91/24/24/11 matrices and static import closure once
against isolated45, plus focused acceptance/rejection of maintenance180/60,
old60000 transaction, disabled timeout, client-source, fixture10 and ambient
readback. Preserve published DAEE112331-byte archive exactly. Rebind all17/122
Phase2 paths/hashes without changing criterion definitions; no Phase2 native.

Only actual ReviewOK with the exact ORIGINAL0006 FRESH PHASE1 source45 heading
permits collecting a real seal and one reserved canonical36 native144/all122
run, bracketed by original/S0, source including DAEE, journal2 and closure.
Canonical36 uses fictional rollback DML; it is not SQL-read-only. A failure is
recorded without retry. This remains preliminary evidence, not Foundation
acceptance. Foundation estimate stays35% +/-5pp.
