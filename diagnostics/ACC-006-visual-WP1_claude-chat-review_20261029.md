# ACC-006 visual WP1 — independent Claude chat review

Date: 2026-10-29. Reviewer: Claude chat (Booster project). Executor: Claude Code (cloud), branch `claude/acc-006-visual-3hr0np`, commit `d529561980051c8435ca3a980588a5fed8b965ac`.
Scope: integrated CODE/composition review of the exact pair below. Review only: no execution, deployment, Git or status change.

| Item | SHA-256 | Checked |
|---|---|---|
| `patches/ACC-006-visual-WP1_20261028.php` | `43f9642e1e6d1f8310e83df5cd5df36cf9ff320d04c2172472237391907b1465` | recomputed from the branch blob; `php -l` OK (PHP 8.3 sandbox) |
| `diagnostics/ACC-006-visual-WP1_owner_20261028.ps1`, CRLF bytes the owner runs | `9d34b33a0b7cfcfc697ba4a4dbe1d84715a52175b018c60eba6434060290d14d` | LF blob `7b31b16e…` recomputed; CRLF conversion reproduces `9d34b33a…` |

**Verdict: Deploy OK with non-blocking notes**, on the condition that the owner grants the header token-only exception before `-Step Apply`.

## Basis
- **Runner, read in full** (structure; payloads checked through the per-target diffs). It satisfies the patch conventions:
  - **Before Apply:** exact target before-hashes (the header compared with both tokens normalized); 44 pinned dependencies; read-only DB admission (columns, zero theme overrides, no alternate theme template, exact reviewed event set); an admission hash; lint of every candidate.
  - **Apply:** a backup with a manifest; a recheck immediately before the first write; atomic renames with a post-hash and lint per file; automatic restore of partial writes; self-delete.
  - **Rollback:** a full preflight of every target and backup, a second preflight, then reverse restore.
  - **Boundaries:** no DB writes, no created files, no wildcard deletion.
  - **Header tokens:** both are located by path prefix and replaced wholesale (convention 8).
- **Packet.** My own diff against the accepted `diagnostics/RD-18-WP4_owner_20261028.ps1` (local copy, `b5e92850…`) equals the executor's `packet-diff.txt`. The only changes are the runner name and hash, the receipts folder, the remote file name, the heredoc delimiter, the markers, the backup regex and `changed_files=16`.
  - The remote bodies, SSH options, staging, hash gates and cache cleanup are unchanged from the accepted packet.
  - Not running it under PowerShell 5.1 is therefore an accepted residual risk.
- **Diffs reviewed:**
  - the wishlist controller (`ids()`, guest pending remove, shell opt-in, RD-25 fit photo);
  - `header.php` and `header.twig`;
  - `shell.php` and `shell.twig`;
  - `booster-wishlist.js`;
  - the wishlist and account language files;
  - `wishlist.twig` and `wishlist_list.twig`;
  - `thumb.twig` and `product.twig` (button texts and attributes only);
  - the DS CSS block.

  All changes are inside the handoff scope as amended by owner decisions D1=A, D2 and D3 (G0, 2026-10-28). The GA4 anchors are byte-exact; `add_to_wishlist` still fires only on `json.added`.
- **Risky zones:** none touched (no checkout, payment, NP, order status, schema, feed or DB write).

## Non-blocking notes
1. **Header token exception.** `common/header.twig` gets `boostershop-ds.css?v=acc006-visual-wp1-20261028` and `booster-wishlist.js?v=acc006-visual-wp1-20261028`. The owner must grant this before Apply. H1 does not cover ACC-006.
2. **Per-user state in the header.** `data-ids` in the header HTML carries per-user wishlist IDs (and guest session-pending IDs). That is correct only while no shared full-page cache serves header HTML across sessions. The existing counter has the same exposure, so this is not a new risk class, but owner QA should include a cross-session check.
3. **Notice timing.** Error notices also auto-close after 5 s; only the login notice persists. This is acceptable, but a persisting error notice would be friendlier. Optional for WP2/WP3.
4. **Routable `ids()`.** `ids()` is a public controller method, so `route=account/wishlist.ids` is reachable. It returns no output: the framework ignores an array return value, and it only reads the caller's own scope, the same pattern as the accepted `total()`. Noted, not a defect.
5. **r2 binding.** The r2 pull reached the executor without its manifest. Its 5 files are bound only by the runner's server-side before-hash gate, which is sufficient for admission.

## Rollback
The owner-only packet runs `-Step RollbackUpload` → `-Step RollbackVerify` → `-Step Rollback`. It uses the saved backup `/home2/boosters/public_html/_patch_backups/ACC-006-visual-WP1_20261028-<UTC>-<nonce>/` (`original-0…15` + `manifest.json`), then clears the template cache. Across packages, roll back WP3 → WP2 → WP1. Source rollback restores all 16 files. The wishlist data written by users after Apply stays, which is expected (no schema change).

## Owner QA
As in the report §9. In addition: after adding items in one browser, open the shop in a private window. The header counter must show 0 and no heart may be pressed.
