# RD-18 WP4 integrated final code/composition review: Review OK

**Bound deploy pair (verdict is valid for these exact bytes only):**
- Runner `patches/RD-18-WP4_detail-polish_20261028.php`: **`6e0228de91fa957afca9dc63a908e160633c1fab401ccecdbd52e1ddf7c46e9b`** (661,890 bytes)
- Owner packet `diagnostics/RD-18-WP4_owner_20261028.ps1`: **`b5e92850ebb857afbce0469877f2512ef79e66533a1de058e412780c605719ab`** (11,841 bytes)

**Context bound:**
- report `207cd470…267e3`
- release-facts `e7525ad1…81881`
- targets `199ce377…702e8`
- dependencies `dd14319d…fe38df`
- control-flow excerpt `9f6cac23…502d5`
- bound-check-results `90398120…7697`

If either deploy hash changes, this verdict lapses.

## Review scope and limits

**What I reviewed:** the report, the full packet text, and the runner control-flow excerpt. I also reviewed the full after-bodies of:
- controller, order_info.twig
- both bank language files, account language file
- tracking JS, SVG
- the owned CSS block
- the bank admission function and all test fixtures and results

**What I did not review:**
- The elided base64 target and dependency contracts. I did not decode them, recompute any hash or confirm the excerpt equals the runner bytes. Those rest on the executor's boundaries and file-engine checks.
- The frozen source.diff or any raw bank controller, config, photo or NP bodies.
- Screenshots, host runtime, MySQL, translation-table contents or real NP behaviour.

Reviewing cannot be counted as Verify, Apply or QA.

## Mandatory findings: none

The seven corrections are each implemented within scope:
1. **Invoice row:** it renders only when `invoice_no` is truthy, and the controller still sets `''` when no invoice exists.
2. **Bank phrase:** both UK files change `text_payment` only, and the fixture proves the rest of each file is unchanged. Old comments are not rewritten or substituted at render time. The bank controller is unmodified and hash-pinned.
3. **Compact delivery:** it reads the stored shipping bucket and `bs_np_v1` only. Non-NP orders keep the native format, and unrecognised point descriptions are kept intact. A missing or invalid middle name is not filled in from anywhere else.
4. **Delivery logo:** the logo shows only when `is_np` is true. The SVG has no script, no external references and no remote fetch.
5. **Tracking button:** it is a real `<a>` in the status heading, outside `<details>`. Its target is fixed, it carries `noopener noreferrer`, the number must match `^[0-9]{14}$`, and the URL in the response is ignored.
6. **NP region:** it sits outside the disclosure. There is one same-origin read on load and no retry, polling or re-request when the disclosure opens. The 8 s abort timer is cleared in `finally`, all text is set via `textContent`, and failures leave the rest of the order usable.
7. **Support lines:** three explicit lines with an exact `tel:` link. Centring is done by the component rule only.

**Runner composition is sound:**
- **Write order:** guards and backups come before any write, and the header token is published last.
- **Rollback:** it preflights twice, and the new SVG is deleted only when it matches the expected hash. Partial rollback works.
- **Failure restore:** auto-restore after a post-write fault was exercised in the file-engine test.
- **No-op detection:** the whole-token header no-op leaves later DS appends and tokens alone.
- **Lock:** the same exclusive writer lock is used.
- **Bank admission:** it checks the schema, then runs a prepared COUNT for both routes across all stores and languages. It reads no values and makes no DB writes, and runs inside the read-only transaction on both Verify and Apply.
- **Event allowances:** the new protected triggers are evaluated fail-closed, and only the five exact prior pairs are allowed.

**Packet composition is sound:**
- It binds the runner and packet hashes and keeps one-use state.
- Verify, Apply and rollback are separate owner steps, and Apply can never be replayed.
- Apply is chained `runner && cache cleanup`, followed by a staged-absence check.
- The PHP path is pinned and every remote body is LF-only.

## Optional findings (non-blocking)

| # | Finding | Scope | Closure |
|---|---|---|---|
| O1 | The JS rebuilds the NP URL itself (base64, strip padding, reverse). That this equals the pinned `BoosterOrderTracking::link` output is not proven in this packet. | tracking.js only | Owner QA: click Трекінг on a real TTN and confirm the official page opens; or add a fixture comparing against the helper later. |
| O2 | On orders with no TTN (including non-NP orders), "Перевіряємо стан…" flashes and then hides, a small layout shift. There is also now one endpoint read per detail view, using the unchanged 300/60 s backend cache. | UX only | Accept at QA, or later render the region only when `is_np` is true. |
| O3 | Labels `np_title`, `np_retry` and `np_cache` are still fetched but no longer used. The phone number is hard-coded in Twig rather than in the language file. | cosmetic | Cleanup in a later work package; no behaviour impact. |
| O4 | If the runner fails during Apply or Rollback, bash also prints `packet_error=runner_did_not_self_delete`, a secondary and misleading line. The outcome is still correctly STOP. | receipt wording | When assessing, read the runner's own `error=` line first. |
| O5 | `RollbackUpload` is not refused after `rollbackAttempted`. That would only leave a staged file; `Rollback` itself stays one-use. | packet edge case | Owner should not re-upload after a rollback attempt. |
| O6 | Weight and point-subtype qualifiers in NP labels (for example "(до 30 кг)" or "Пункт…") collapse to "відділення №N". | display wording | Owner QA on real orders. |
| O7 | Non-UK language files (for example en-gb) keep the old bank text. | stated scope | Accept; this work package covers UK files only. |

## Host-dependent STOPs that would be expected, not defects

Verify may legitimately stop with:
- `bank_translation_override`, if a DB translation row exists for `text_payment`;
- `unadmitted_target_event`, if any DB event pattern matches the newly protected `language/…`, translation-model or bank-controller triggers;
- a schema, path or permission refusal.

Each of these needs a targeted assessment. Do not work around them with a DB write, a broader allowlist, a replay or a local reset.

## Gate

1. Release owner Upload → Verify only, then STOP and return the receipt. Expected Verify output: `admission_sha256`, `syntax_gate=ok`, `production_writes=0`, `backup=none`, `done=ok`.
2. After that receipt is accepted, release Apply as a separate step. Expected Apply output:
   - nine target lines and `changed_files=9`
   - `self_deleted=yes`, `done=ok`
   - `template_cache_cleanup=ok`, `staged_runner_absent=yes`
   - `RD18_WP4_APPLY_OK_OWNER_QA_REQUIRED`
3. Owner QA follows Apply, using the report's checklist plus O1, O2 and O6.

Mechanical acceptance of the receipt is not QA, and WP3 remains live until Apply.
