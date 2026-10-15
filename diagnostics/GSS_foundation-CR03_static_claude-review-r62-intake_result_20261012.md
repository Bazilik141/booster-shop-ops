# GSS actual Foundation CR03 STATIC intake/design review — partition INTAKE62

Date:2026-10-14. Reviewer:Claude Code2.1.299; model(s):claude-opus-5-5; effort=high.
Exact packet SHA256:96D81AF6711BA9D5FF08C58888DED1D4DA6AE8C93FC383613E4DF1010B495111; 678864 UTF-8 bytes.
Manifest SHA256:30213B3B72CDA702BA3F8F032DAFB8578E88A8FC471B88401F98DBC98279E2F8.
Tools/MCP/hooks disabled; Foundation CR03 static intake/map/design review only; no native replay, target/private restore, pilot completion or owner gate grant.
Capture: complete actual model result below, without paraphrase.
Usable:true; exact inputs unchanged after:true.

---

## Verdict: Review OK
## Scope: GSS FOUNDATION INTAKE62

**What this covers.** Proposed DESIGN63c `6914805411A4…` (46924 bytes), as a DESIGN only. I assessed it against:
- the complete actual61 result, its terminal record and its disposition;
- the escaped-byte A owner decision and its proposal;
- the unchanged 14-value numeric approval;
- full contract58 and the INTAKE58 disposition;
- the accepted62 boundary, actual60, its disposition and basis;
- whole policy62, transform62, supervisor62, bench62 and worker62, plus their current callers, fixtures and reports;
- helper24, the Design43 EXCERPTS, and the review62 checks, builder, verifier, requester and package.

**What it does not cover.** This verdict grants nothing for:
- 63 CODE, which does not exist yet;
- the tests described in the design, which are obligations and not evidence;
- SOURCE, STD or native work;
- private or production use, Git or status changes;
- certification of the deployed product's security.

**Method.** I read the embedded bodies only. I executed nothing and recomputed no hashes. U+0040 is written `\u0040`.

**Context.** The context is sufficient for a DESIGN verdict:
- Every changed owning module, its caller and the review feedback are whole.
- I traced the review62 → 61 → 60 → 59 checker chain. Each path it reads resolves to a full body or an identity record.
- The exception is paths named only inside identity-only receipts and report pins (for example the bench60 joint-profile pins and bench59b pins). I took those from their receipts and did not inspect them. No conclusion below depends on them.
- The six dependencies omitted in review60 are now inside this manifest. The requester's `unchanged()` check therefore covers them for this interval only, not retrospectively.

### Disposition of the actual61 findings

**B1. Resolved by an informed owner choice.**
- The token quota is dropped, and `maxEnvelopeNodes` keeps its "raw values plus keys" meaning.
- The context-free decoded view is correct as specified:
  - for a run of n backslashes it emits ⌊n/2⌋ backslashes, and only an odd run escapes;
  - it decodes once and never recursively;
  - unpaired surrogates become U+FFFD;
  - its output is never longer than its input, so it fits the existing stream caps.
- Marker splicing cannot create a new escape: a marker starts with `<` and ends with `>`, so neither side can complete a backslash escape. By reading, I found no self-induced refusal.
- The third whole-copy loss and the malformed-stdout diagnostic loss are disclosed.

**B2. Resolved.**
- The four-member parent union is closed.
- The worker-only duplicate rule is explicitly superseded.
- Original-side disagreement after a clean SUCCESS maps to PUBLIC_COPY_INVALID, and a raw duplicate keeps phase4 precedence.
- Both journal substages before the physical attempt are enumerated.
- See C3 for one remaining gap.

**B3. Resolved in substance.**
- The independent frozen62 reference, the two baseline decisions and the newly-refused inventory address the common-mode risk.
- See C2 for a needed precision.

**N1–N8. Resolved as design.**
- **N2:** the exact public-only entry is shared by worker, runtime and provenance, and sequenced after private-graph release. See C1.
- **N3:** every finish observes the deadline, the early timer re-arms, and the three `setTimeout` sites are identified.
- **N4:** both large clones are removed, with an honest disclosure that deserialization precedes validation and that backing stores sit outside the heap limit.
- **N5/N6:** reachability ordering and a separate parent profile are specified. The UTF-8 claim holds: all pattern spans are ASCII on the latin1 view, so spliced output from `Buffer.from` is always valid UTF-8.
- **N7/N8:** dispositioned.

**F1–F6 (actual60).** All are carried into concrete design.

### Conditional owner boundary

The boundary still holds at the DESIGN level:
- **Option A only adds refusals.** It does so inside review-artifact capture, and both existing screens are retained.
- **No product controls are touched.** No authorization, access or tenant-isolation path changes.
- **Loss is fail-closed.** A lost response yields no verdict, is consumed, and is never automatically repeated. Review and acceptance gates are therefore not weakened.
- **The new loss is narrow.** JSON_ENVELOPE stdout leaves are still redacted, not refused, so verdicts that discuss escapes survive. The added loss comes only from escaped hits in byte-mode stderr or malformed stdout.

Two caveats:
- This is not evidence of the product's security.
- The owner-facing question disclosed loss of the whole response, including a correct verdict. Diagnostic-category loss is implied by that but was not named separately. `malformedDiagnosticLossDisclosed:true` is therefore the executor's characterization, and later dispositions should word it that way.

### Non-blocking findings (required before or with CODE unless marked optional)

**C1. Place the public sender correlation in the shared public layer.**
- In 62, `validateArtifactSenderBinding` checked exitCode, closeObserved, observed and retained counts, truncation flags, incompleteReasons, withheld mode and stdout status. 63c folds all of this into the private layer.
- Most of these facts are publicly derivable from the BEFORE projection, which provenance has through the journal. Design43 and contract58 require these copy-versus-senderEvidence bindings at provenance and terminal.
- Required:
  - Define one CONTEXT public predicate over copy facts and the BEFORE projection, used identically by runtime and provenance.
  - Restrict the private layer to genuinely private facts: zero-span original equality, the raw strict decision and span fidelity.
  - Test that runtime and provenance agree on this predicate.

**C2. Make the B3 differential precise about parserPin.**
- Both the copy root and the outcome strict evidence embed the `parserPin` argument.
- "No root differences" is satisfiable only if the frozen62 reference and the successor receive the identical parserPin argument. Otherwise the pin must be listed as an exact permitted substitution.
- State which, so the implementation does not add an unreviewed exclusion.
- Also specify how baseline REFUSAL outcomes (code plus strict evidence) are compared.

**C3. Close the BUILD rows after a clean SUCCESS.**
- Some exceptions after a validated clean transform result fall outside the pure entry but before VALIDATED_ARTIFACT or the expected-pin step. Examples are mechanism assertions, buffer views and base64 decoding done outside the entry.
- These normalize to safe codes such as ERR_ASSERTION, UNSPECIFIED_ERROR or UNRECOGNIZED_CODE. They have no row, so they would close as CONTRADICTORY_OUTPUTS.
- Fix: either place all of this work inside the pure entry (which yields UNRECOGNIZED_CODE), or add an explicit generic NONE/UNUSABLE row.
- Also state the F.errorCode correlation explicitly:
  - when `transformEvidence.errorCode` is non-null, F.errorCode equals it;
  - only a clean SUCCESS may carry the parent-four codes, PUBLIC_COPY_PIN_INVALID, or that generic set.

**C4. Measure the worker's new in-budget cost.**
- The worker now runs the full public validator within its 15 s and 256 MiB budget: copy parse, base64 decode, public stdout parse and leaf screen, and two byte views.
- The 63 profile must report worker elapsed time on the wide/deep, ESCAPE_HEAVY and BYTE_BOTH layouts.
- A timeout here would be a TIMEOUT loss, not success.

**C5. Disclose that worker refusals are unverified.**
- Private validation runs only after SUCCESS. A defective worker's REFUSAL, such as a false RAW_STDOUT_DUPLICATE, is accepted as an observation.
- The result is fail-closed, either loss or phase4 REFUSED, but the limit should be stated.

**C6. Bind the decoder choice.** (Minor.)
- State that the collector and comparator use the same decoder.
- Map a fatal-decoder throw, which would mean a broken invariant, to UNRECOGNIZED_CODE inside the pure entry.

**C7. Optional: avoid the in-worker copy.**
- For copies larger than about 4 KiB, `Buffer.concat` returns a dedicated, exactly-sized buffer.
- After checking `byteOffset` is 0 and `byteLength` equals the size, it can be transferred directly, avoiding a 24 MiB copy inside the worker.

### Discriminating cases to add

- **JSON stdout with a literal `\u0040` in the result text** (exactly what actual61's own verdict contains). It must stay NORMAL. Also, an even run `\\u0040` in byte stderr or stdout must not refuse. Together these catch a byte view wrongly applied to JSON leaves, or recursive decoding.
- **Splice adjacency.** Cover spans preceded by an odd backslash run and followed by hex, plus the `\u`+5 and `ntrbf` start adjustments. Assert zero decoded refusals across the admitted corpus.
- **Special keys.** Add `__proto__` and `constructor` keys, and integer-like keys, in both redacted and unredacted objects, to the planner and differential fixtures.
- **Refusal precedence.**
  - A fidelity mismatch combined with a screen hit must yield PUBLIC_COPY_INVALID.
  - A defective worker that skipped its own screen but returned an honest copy with escaped stderr must yield PUBLIC_COPY_SCREEN_REFUSED, with a completed strict root.
- **Timer edge cases.** Two consecutive early re-arms. A callback arriving after settlement must create no timer.

### Coverage ledger dispositions

| Row | Disposition |
|---|---|
| B1/N1 option A, informed loss and product protection | **Adequate.** The boundary holds; C5 and the disclosure-wording caveat apply. |
| B2/N2/N8 parent, provenance and journal union | **Adequate.** C1 and C3 are required before CODE. |
| B3 frozen62 differential | **Adequate.** C2 is required. |
| N3–N6 deadline, IPC, parent cost, UTF-8, wide fixture | **Adequate as design.** C4 and C7 apply. The fixture is data only. |
| N7/F6 transport, identity, closure | **Adequate as identity and framing.** There is no retrospective interval proof, and the literal version string is not an observation. |

### Material limits

- **Nature of this review.** No 63 code, tests, profile or differential exists. All cases above remain obligations.
- **Fidelity is runtime-only.** Private-original fidelity cannot be re-established later by public provenance.
- **Privacy is finite.** Arbitrary encodings and confusables are outside the policy.
- **Resources are unbounded.** The parent has no deadline, and whole-process memory and peaks are unbounded and unproved.
- **Still open:**
  - OS pipes, Windows EOF, provider, native and forced exit;
  - managed-policy hooks and binary enforcement;
  - N5, receiver bytes, hash/import/execution races, clock, forgery and the frozen17 limits;
  - the full trust registry, the five-pin and three-pin closures, REQUESTER_PRODUCER, physical journals, six-primary provenance, normative30/437 and terminal64.
- **Consumed, no replay:** requesters 29–61, Read51, CR11, D078, D081 and D082.
