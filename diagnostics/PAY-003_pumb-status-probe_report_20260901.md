# Codex Report — PAY-003: one-shot PUMB status probe

Date: 2026-09-01

## Scope

Prepared a one-shot CLI diagnostic for independently checking one PUMB TEST application status outside the OpenCart admin button.

## File

```text
patches/PAY-003_pumb-status-probe_20260901.php
```

## Safety

- CLI only.
- Requires `test_mode=1`, `public=0`, and exact `auth.dts.fuib.com` / `api.dts.fuib.com` hosts.
- Accepts only `--status=<numeric cap id>`; there is no create/live mode.
- Performs OAuth and one `GET /sf-credits/{id}` only.
- Makes no database or OpenCart writes.
- Does not print credentials, bearer token, target ID, or raw bank response.
- Prints HTTP code, X-Flow-Id, response size/hash, top-level JSON keys, and an allowlisted state.
- Self-deletes after a completed GET.

## Validation

```text
No syntax errors detected in patches/PAY-003_pumb-status-probe_20260901.php
SHA256 13b9d3156937d6a18f23dc66573072980023f1cfa4837f04dd9af8be075b8a8d
```

## Owner command

```bash
cd ~/public_html || exit
php PAY-003_pumb-status-probe_20260901.php --status=<cap_id>
```

## Interpretation

- `status_http=200` plus an allowlisted `status_state` is a complete independent result.
- `status_http=200` plus `status_state=missing` proves that the bank response omitted a usable top-level `state`; send `status_flow_id` and `status_body_keys` to PUMB support.
- Any non-200 response should be reported with `status_flow_id`; do not retry repeatedly.
