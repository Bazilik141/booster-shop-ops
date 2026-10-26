# RD-16 WP1 main R2: integrated R7 review

**Verdict: Changes requested.** There is one mandatory finding. The R2 pair below is **not approved** and stays HELD; neither file may be uploaded or run. The owner gates (Upload → Verify → Apply, deployed-WP1 QA, then WP2) are unchanged. This review grants no execution, Git, status, SSH, key, server or deployment authority.

## Bound identities (rejected for this round)

I had no tools, so these are the supplied values, not recomputed. The internal evidence is consistent:
- The packet grew by 9 bytes (11,830 → 11,839). That matches two `_r2` filename literals (+6) and the `-r2` receipt-directory suffix (+3), with an equal-length hash literal swap.
- The runner grew by 4,607 bytes. That fits the admission block, the template guard, the runtime additions and twelve extra base64 dependency entries.

| Artifact | Bytes | SHA-256 | Status |
|---|---|---|---|
| `patches/RD-16-WP1_account-shell-hub_20261025_r2.php` | 87,299 | `a46a3addb8f73d83d1ef3645625cddd9f5b9d4f485d7f6cea81bc2d31911f62e` | **Not accepted** |
| `diagnostics/RD-16-WP1_owner-r2_20261025.ps1` | 11,839 | `de32ed409ccbbf3e8e67894aceb7eddafdd6836399ec419f3992776972e04e7c` | **Not accepted** (it binds the rejected runner hash) |

The old R3 pair (`c8a8cc55…` / `c9976145…`) remains historical. It is superseded and must not be used either.

## What I accept and will not reopen

**1. Reuse of the seven app targets by exact equivalence.** The strongest proof is enforcement, not the attestation:
- The runner refuses unless `sha256(new) === after` for every target, and `before` must match.
- All seven `before`, `after` and `normalized_before` values are identical to the R3-accepted table.
- So the published bytes are identical to what R3 accepted, however the payload is encoded.

R3's acceptance of the following therefore carries over within its original fake boundaries:
- UI, DOM and focus order;
- the five backend cases;
- CSS and cache handling;
- the file engine, backup, rollback, self-delete, no-op and lock behaviour.

**2. Exact-pair admission.**
- `in_array([$pattern,$action], $reviewedHooks, true)` is strict array identity on raw DB strings. Any change to case, whitespace, `|` versus `.`, prefix or suffix falls through to the unchanged conservative predicate, which denies it.
- The startup source registers each admitted pair as an anchored literal (`^view/common/header/before`, `^view/account/account/before`). Each therefore fires on exactly one event.
- Unknown, wildcard and altered rows remain denied. The 19 earlier cases and the 20 new cases discriminate correctly.

**3. Call path and the token-only delta.**
- `Loader::view` passes `[&$route,&$data,&$code,&$output]`. The vendor 3-parameter handlers therefore bind their `$output` to `$code`, and Loader's real `$output` stays `''`. Rendering always goes through the template with the modified code.
- The header views only `str_replace` the `<body>` and `</head>` anchors, which are disjoint from the DS token. The before and after inputs differ only in that token, with identical hook data. So the delta is token-only for every vendor configuration, including enhanced conversion. The six-order test is confirmation, not the only basis.
- The live hub already lacks the newsletter anchor before and after. The session-flag consumption is unchanged, and nothing new is instrumented.
- `account.twig` and `shell.twig` contain no `{% include %}`, so rendering them through the vendor's `ArrayLoader` is safe.

**4. Pins.**
- 32 + 12 = 44 pins.
- All four core files, both controller and model pairs, the live-search controller and the three vendor assets are pinned.
- Pins are checked before any backup and rechecked before publication. The new hook-dependency drift case refuses with zero writes.

**5. Owner packet.**
- It is a literal-only successor with a fresh, isolated `owner-receipts-r2` state.
- The new filename avoids a staging collision with the retained old runner.
- The shared `RD16_ID` lock in the same stage directory serialises the old and new runners.
- The admission hash now also covers `hook_theme_settings_sha256`, so Verify-to-Apply drift fails closed before backup.

**6. Runtime additions.**
- `DIR_SYSTEM` and `DIR_TEMPLATE` are mapped.
- The settings SQL is valid for MySQL and mysqlnd-free, runs inside the READ ONLY transaction, and returns no values.
- The new code is compatible with PHP 8.0.

## Mandatory

### M1: the theme-fallback guard is store-0-only, unlike every other admission check

The vendor `getTemplateBuffer()` derives its first lookup directory from the **effective** config:

```
config_theme == 'default' ? theme_default_directory : config_theme
```

OpenCart loads settings as store 0 overlaid by the active store. Store selection also accepts the `store_id` request parameter.

The runner reads `WHERE store_id = 0` only. A secondary store with its own `config_theme` row, and a template at `catalog/view/template/<theme>/template/{account/account,common/header}.twig`, would pass admission. On that store:
- the header hook would read the alternate file, so the DS token never takes effect;
- the hub hook (when the flag is set) would substitute the alternate account template.

This is fail-open against the package's own contract ("disallow alternate vendor fallback Twig paths; STOP on unknown conditions"). It is also inconsistent with the theme-row and `layout_route` checks, which are store-agnostic.

**Required change:**
1. Drop the `store_id` filter.
2. Key rows by `(store_id, key)`, and STOP on duplicates within one store.
3. For store 0 and for every store that has its own rows, compute the effective pair (store-N value overriding store 0). Then apply the guard to each effective pair.
4. Add fixtures:
   - a store-N override with a disallowed value → STOP;
   - a store-N override with an alternate file present → STOP;
   - store 0 only → unchanged pass.
5. Re-run the 11-case file-engine suite and the admission suite. Record the new pair of hashes.

## Optional (strongly recommended in the same revision)

**O1. Likely false STOP on the OpenCart 4 default theme value.**
- I believe stock OC 4.0.x sets `config_theme = 'basic'`; please confirm. The allowlist (`''`/`'default'`) would then STOP Verify with `alternate_hook_theme_setting`. That costs a full revision cycle.
- The composition fixture hard-codes `'default'`/`'default'`, and the live value was deliberately never read. So "standard values" is an assumption, not evidence.
- Recommended replacement for the allowlist:
  - validate the derived theme segment against `^[A-Za-z0-9_-]{0,64}$` (otherwise STOP);
  - derive the directory exactly as the vendor does, with `null` mapping to `''`;
  - normalise `''` to `catalog/view/template/template/` rather than walking a `//` path;
  - require both twigs to be absent in that directory and in `default/template/`.
- Add test cases for:
  - `basic` with the files absent → pass;
  - `basic` with a file present → STOP;
  - an unsafe value such as `../x` → STOP.

**O2. Bind the exact multiset.**
- A duplicated exact-pair row would still be admitted. The before/after delta stays token-only, but the injected head would differ from what was tested.
- Consider requiring that each reviewed pair appears exactly once, or comparing `admitted_hook_sha256` to an expected constant.

**O3. Tighten the runtime constants.**
- Make `DIR_TEMPLATE` required rather than optional.
- STOP if `DIR_CATALOG` is defined.
- STOP if `DIR_MODIFICATION` is defined and is a prefix of the derived template path. `modCheck` redirection is otherwise unbounded.

**O4. Fix the equivalence evidence.**
- `build-main-r2.py`'s `app_targets_unchanged` compares rows with the same `targets.json` they were built from, so it is tautological.
- Record the byte comparison against the old runner's decoded targets blob, or state explicitly that equivalence rests on the enforced after-hashes.

**O5. Retire the old pair explicitly.**
- The old staged runner and packet are retained, and old state is `uploaded` only. The old Verify could pass if hooks later vanish.
- Record the old pair as superseded in the report so the owner never runs the old packet.

**O6. Small test gaps.**
- A cross-pair case (header trigger with the account action) is missing.
- An action spelled with `|` is missing.
- Each is structurally denied today; adding them documents the behaviour.

**O7. Carried over.** R3 optional notes 1–5 and R2 notes 1–5 remain non-blocking.

## Re-review scope for R8

The next review can be narrow:
- the runtime settings and template-guard block;
- its tests;
- the regenerated dependency and target blobs, which must be byte-identical (prove it);
- the packet's runner-hash literal.

Everything else is reused by exact equivalence. Native host PHP 8.0, MySQL, OPcache, the authenticated UI, `common.js` and gtag remain owner Verify/QA items, not local proof. Approval at R8 would open only owner Upload → Verify for the new pair; it would not waive deployment, QA or the WP2 gate.
