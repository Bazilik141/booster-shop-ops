# ACC-009 NP — sequential N1/N3 source candidates

Date: 2026-10-17 (Europe/Kyiv)
Executor: Codex; actual independent Claude Opus/high subscription review required.
State: LOCAL CODE/CHECKS COMPLETE; SOURCE REVIEW PENDING. No production apply.

## Outcome and exact scope

Owner selected A and both separate source-patch preparations. N1 retains the
full shipping given name and uses only the saved shipping patronymic key; no
key means empty/manual patronymic. N3 constructs the missing helper dependency
after successful lookups. Both affect only
`extension/PintaNovaPoshtaCod/admin/controller/shipping/internet_document.php`.
Models, SQL data, metadata, profile/header inference, validators, guest checkout,
receiver timing, structured courier defaults, JS/Twig/CSS and GA4/wishlist/
header/media/photo files are not patch targets. WP3b/WP3c remain separate.

## Current owner-host admission

Owner result at `2026-10-17T10:02:37+00:00`: production PHP8.0.30 CLI, successful
diagnostic SHA verification/lint, exit0, self_deleted=yes, done=ok. All47 expected
disk identities match. The two pending loader hashes also match their admitted
current archive:49/49 total. No generated OCMOD PHP/Twig, active startup rows or
active theme routes were returned.112 raw events are PHP-truthy;24 register as
admin/system candidates and none directly matches the NP controller/view or
material name/city/street/order-model events under actual prefix-regex matching.

Source result: `diagnostics/ACC-009_NP-code_evidence_20261017/host-runtime-result_20261017.json`.
The config-defined OCMOD/language hooks, loader/startup models and template
adaptor match admitted bodies. Language injection does not replace an existing
formdata key; both Twig loaders share literal autoescape=false. Installed Pinta
code selects its admitted admin template namespace. The common/header captcha
hook is outside the recipient-data route; full page behaviour remains owner QA.
No universal plugin/page acceptance is claimed. A MYSQL_OPT_RECONNECT warning
did not prevent complete collection; no agent DB or NP action was performed.

This establishes the bounded current disk/routing prerequisite for CODE. It
does not prove current web bytecode. The same source runners contain a minimal
read-only HTTP runtime probe; collect fresh web PHP/OPcache evidence before
owner apply. Do not interpret CLI source checks as a passed web execution gate.

## Root cause and implementation

### N1 — recipient snapshot/defaults and view copies

Original makeDefaultFormData at340-348 splits shipping_firstname on spaces and
always treats the final word as patronymic, ignoring rd13_receiver_middlename.
This guesses incorrectly for a compound given name and ignores explicit empty
or separately stored patronymic. The actual Twig has raw double-quoted name
attributes and the admitted adaptor disables autoescape.

Three anchored edits:

1. Replace that split with a private snapshot helper. `array_key_exists` keeps a
   valid string key authoritative, including empty and '0'. Missing container
   means empty; a present invalid container or malformed value produces a fixed
   nonpersonal warning and no inferred patronymic. No raw JSON decoding or
   header/profile fallback. Firstname retains PHP trim semantics; lastname is
   otherwise unchanged.
2. Add complete private defaults/display helpers. Display rejects non-string,
   NUL and invalid UTF8 values and encodes with ENT_QUOTES, UTF-8 and
   double_encode=false. This is defence-in-depth; no demonstrated injection
   from the ordinary ENT_COMPAT request path is claimed.
3. Apply display encoding only to the three recipient-name view copies after
   validation/outbound processing and before copying errors to the view. GET
   invalid data remains manually correctable on POST; raw order/post/outbound
   strings and unknown custom fields are not rewritten.

### N3 — successful Doors helper dependency

Original saveDoorsAddress at1246-1276 uses undefined local PintaCounterparty
after city/street lookup succeeds. Add exactly one local construction plus its
marker immediately before the existing saveAddress call. Keep selected
CounterpartyRef, StreetRef, house/flat, validator, error and return handling.
No lookup normalization or recipient-ref selection change is included.

Current admin city/street methods exist with the expected signatures and row/
null contracts. Known first-city-by-name ambiguity, type-space-street input
expectation and PrivatePerson selected-ref remote semantics remain unchanged.
N3 acceptance is local fatal removal/dependency/argument correctness only.
It does not establish end-to-end Doors creation or remote attribution.

## Separate artifacts and composition

| Stage | Runner | Controller before -> after |
| --- | --- | --- |
| N1 | patches/ACC-009-N1_recipient-snapshot-defaults_20261017.php | 8e07945dacc5b3fab2681266ac4d03811e9b702d22b067008d02c1d71c59d567 -> 43dc466f5332d45acecc5e1cb2e82e8f712268af568d6f16de90809a3dd60da1 |
| N3 | patches/ACC-009-N3_doors-helper-dependency_20261017.php | 43dc466f5332d45acecc5e1cb2e82e8f712268af568d6f16de90809a3dd60da1 -> 2138e15643ce4bced1b49698955c78f443617e4b59877325113261042d1c7b93 |

Exact runner SHA values, edits, diffs and source images:
`work/acc009-np-code-20261017/source-patches-manifest.json`, N1/N3-spec.json,
N1/N3.diff and generated/. Original LF bytes outside the edits are preserved.
Initial unreviewed helper-text decoding was corrected to explicit UTF8 before
review; r0 files are retained in unreviewed-encoding-r0, never deployed.

## Runner guards and lifecycle

- Default or --dry-run checks the exact base, anchors,49 source identities,
  no generated OCMOD, unchanged admin/system events, no active startups and
  unchanged installed namespace codes, then native-lints the candidate.
  It does not write the target or run OpenCart/NP. Its private temporary lint
  file is removed. Catalog-only DB events are excluded from admin comparison
  according to the admitted registration filters; no catalog status is changed.
- --apply is an owner-only deployment action after the web-runtime gate. It
  creates a private backup under ../_patch_backups/ACC-009-Nx-TIMESTAMP-RANDOM/,
  verifies it, rechecks the base, atomically replaces one file, runs native lint,
  verifies the after-hash and restores on failure. The runner then self-deletes.
- Idempotence requires both an accepted after-hash and marker. N1 recognizes
  the composed N3 after-image and never removes it. Stale/unknown bytes refuse.
- --rollback=NAME requires the exact private manifest/backup and known current
  after-image. N3 must roll back before N1. It lints, restores the prior bytes
  and restores the after-image if rollback lint fails. No arbitrary paths.
- No cache deletion is needed for controller-only changes. Disk writes do not
  bypass OPcache admission/revalidation requirements.
- HTTP GET with a32-hex nonce returns only patch task, echoed nonce, PHP/SAPI
  and OPcache flags; invalid nonce/method returns404. No config/DB/bootstrap is
  loaded by that branch. It neither applies nor deletes the runner.

## Local evidence

SHA-bound final batch in
`diagnostics/ACC-009_NP-code_evidence_20261017/source-artifact-verification.json`:

- Native PHP8.3 lint of runners, complete modified controllers and fixtures.
- Actual complete controller classes under isolated framework/model/API stubs:
  N1=39 checks, N3=47. Explicit nonempty/empty/zero/space key, missing/malformed
  container, null/scalar/object/NUL/invalid bytes, PHP trim/NBSP, snapshot
  preservation, quote/entity/tag display, actual GET and invalid POST redisplay.
  An outbound-stage spy proves actual createInternetDocument passes raw names
  before view-copy encoding; that fixture deliberately stubs validation/API
  stages, while the invalid-POST fixture uses the actual unchanged validator.
- N3 city/street errors produce zero Address.save calls; success preserves
  exact selected-ref/street/house/flat arguments; API failure remains loud.
- Runner fixture file application/backup/rollback: N1=17, N3=14; sequential
  composition, reverse rollback, stale hashes/anchors, wrong backup paths and
  post-write lint failure with restored original were exercised on fake roots.
- Three discriminating code mutations fail the actual tests: drop middle-name
  assignment, omit display escaping, remove the N3 local dependency.
- Native local CGI exercises six valid/invalid/method probe cases; local CLI8.3
  is refused by the production8.0 entry guard. All candidate hashes remain bound
  before/after checks. No source SQL/NP connection or production write occurred.

Not proven: hosting8.0 candidate execution, actual Twig/browser rendering,
current web OPcache, NP remote name/address acceptance or owner production QA.

## Deployment gates, QA and rollback

After actual source review, first deliver N1 for owner --dry-run and fresh
read-only HTTP runtime evidence. Do not supply an automatic N1&&N3 deployment
block. Review host evidence before owner apply; N3 waits for N1 deploy/QA.
Routine go is not requested again, but required host evidence and owner deploy/
QA remain real sequential dependencies. Operator POST can create NP objects;
routine smoke opens the form only and makes its existing two NP read calls.

Owner QA for N1: correct full name and explicit/nonempty/empty patronymic from
saved snapshots, missing-key manual input, quote/entity display, invalid/manual
correction and no profile/order rewrite. Check desktop/tablet/mobile, long
names, focus/edit/error states and Tier1 public smoke. No real NP contact/address/
shipment creation belongs to routine QA. For N3, only separately authorized
legitimate operator workflow can establish remote behaviour.

Rollback source in reverse order using the exact logged backup basename and
re-uploaded matching runner. File rollback cannot undo external contacts,
addresses or documents created by a later normal operator submission. No runner
DB mutation or rollback SQL is proposed. No Git/Notion/dashboard write made.
