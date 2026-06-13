# ST-2a.8 Guest Autosave Ref Gate - Codex Report

Date: 2026-06-13

## Baseline

- Handoff: `handoffs/handoff_ST-2a8_guest-autosave-ref-gate_2026-06-13.md`
- Backup inspected: `backup-6.13.2026_16-15-57_boosters.tar.gz`
- Backup SHA256: `55EBF4AD5C9A8F53192BB0340F2F39B371AD302F6DF4BB5274BF965E842ED6F3`
- Target: `catalog/view/template/checkout/checkout.twig`

## Patch

- File: `patches/st2a8_guest_autosave_ref_gate_20260613.php`
- Live target: `catalog/view/template/checkout/checkout.twig`
- Scope: client JS only.
- DB changes: none.
- Server/controller changes: none.

## Behavior

- `fieldRef()` now treats the canonical hidden `shipping_novaposhta_*_ref` values as fallback when visible `data-ref` is empty.
- On checkout init, hidden NP refs are hydrated back into visible `data-ref` attributes.
- If hydrated refs make NP address complete, register autosave is scheduled once so shipping methods can load after reload/session prefill.
- Existing edit/clear behavior is preserved because `setHiddenRef(id, '')` is still called by the dirty/clear paths.

## Validation

- Patch file syntax: `php -l` ok.
- Dry-run against extracted fresh backup: `done=ok`.
- Repeat run on patched copy: `already_applied=yes`.
- Post-patch grep: ST-2a.8 marker, `hiddenRef()`, `hydrateNpRefsFromHidden()`, and hydration autosave trigger present.

## Run Command

```bash
cd ~/public_html || exit
php st2a8_guest_autosave_ref_gate_20260613.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## QA

- Guest fresh dropdown selection: area -> city -> warehouse, then email/phone; `checkout/register.save` returns success and shipping methods load.
- Reload checkout with hidden refs present but visible `data-ref` empty; shipping autosaves/loads without manual edit.
- Edit/clear warehouse; autosave must stop until a valid dropdown ref is selected again.
- Logged-in saved-address flow unchanged.
