# 3D-P-007 WP1 rev 2 — pre-deployment review addendum

Date: 2026-08-16

## Fixed

1. The role-read test now finds exactly one repository-root V23 export matching
   `Версія 23*.txt`. Missing or ambiguous evidence fails the test; there is no
   zero-comparison green path. `node --test` completed all 13 owner-response
   comparisons successfully.
2. `Продажі!Примітки` is no longer in Serhiy's projection. It is asserted
   absent when `SERHIY_FULL_ECONOMICS_VISIBLE_3DP` is both `true` and `false`,
   and direct read of column `O` fails with `RANGE_NOT_PROJECTED`.

## Verification

```text
node --test 3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
pass 1, fail 0
v23_owner_responses_compared: 13

node 3d-print/apps-script-3dp-api/tests/api.test.mjs
{"ok":true,"active_cleanup_route":true,"archived_setup_routes_removed":5,"preview3dpApiSetup_retained":true}

Code.gs syntax ok
git diff --check: no output
```

Updated patch copy SHA-256:
`C5EB5035E6DCF384C7AA631E5BF2706F10E332FDDA47F91161EB51E74BDAF976`.

No publish, deploy, live Sheet write, commit, or push was performed.
