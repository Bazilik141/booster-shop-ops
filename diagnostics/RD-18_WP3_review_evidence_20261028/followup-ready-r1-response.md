# RD-18 WP3 owner follow-up: final code/composition review

## Outcome

**Review OK.** I found no mandatory code or composition change. This approval covers only the exact pair below:

| Artifact | Path | SHA-256 | Bytes |
|---|---|---|---|
| Runner | `patches\RD-18-WP3_order-followup_20261027.php` | `dfe35673ae48b678b2483e39cc78f6d486c98ac6056ce18c0cf1c0a0a5cf43b7` | 686314 |
| Owner packet | `diagnostics\RD-18-WP3_owner_20261027.ps1` | `9829523685372e716acb287e33020b708607cb1c53bfc5cd598b3d300154d309` | 11850 |

I did not recompute these hashes. They are bound by declaration and checked for internal consistency:
- The packet's `$Sha` literal equals the runner hash.
- All 14 entries in `bound-check-results.json` show identical before/after pairs.
- The packet is 6 bytes larger than WP2 (11844 → 11850). That matches the builder's substitutions: four ID occurrences at +1 byte each, plus +2 for the receipts directory. The other substitutions keep their length.
- The header shrinks by 1 byte, which matches the shorter token.

Changing either file voids this review.

This is not visual acceptance, host certification or owner QA. Owner Upload → Verify (send the full receipt) → separate Apply → final QA remains mandatory.

## Mandatory findings

**None blocking.**

The following owner-side gates already exist in the packet and plan and stay mandatory:
- **Verify must show:** `php_version=8.0.x`, a 64-hex admission, `syntax_gate=ok`, `production_writes=0`, `backup=none`, `done=ok` and `RD18_WP3_VERIFY_OK`.
- **Verify STOPs if:** cURL is missing, the private `DIR_CACHE` is wrong or not writable, the four Pinta TTN columns are missing, or any unknown hook on the new tracking routes is enabled.
- **Apply must show** all nine target rows plus `changed_files=9`, `self_deleted=yes`, `template_cache_cleanup=ok`, `staged_runner_absent=yes` and `RD18_WP3_APPLY_OK_OWNER_QA_REQUIRED`.
- **Never replay a step or force a rollback.** A STOP means preserve the receipts and send them.

## What I verified in the supplied text

**Authority and privacy of the tracking endpoint**
- `tracking()` is GET-only.
- Native `login.validate` (customer login plus `customer_token`) runs before any model load.
- Native `getOrder` (owner, store and public scope) runs before the TTN lookup.
- `getTrackingNumber` applies `publicOrderScope()` again in its join and returns only a 14-digit number.
- Every failure path returns a generic `unavailable`. Responses are private, no-store, `Vary: Cookie` and nosniff. The phone, name and key never reach the client.
- `test-tracking.php` exercises the actual controller with the native login body across 11 authority cases.

**Carrier helper (`booster_order_tracking.php`)**
- Fixed HTTPS host and one method (`TrackingDocument`/`getStatusDocuments`), no redirects.
- TLS peer and host verification, 2 s connect, 5 s total, 64 KiB write-callback cap.
- Accepts exactly one successful data row with a bounded, valid UTF-8 `Status`. An optional `Number` echo must match exactly.
- Only state, status and `checked_at` are cached, in 0600 files under the existing private `DIR_CACHE`, with no mkdir.
- The cache lock is non-blocking. Success is cached 300 s and failure 60 s. Every error path fails soft.
- The test asserts the cache contains no TTN, key or private fields.

**Client script**
- No request on page load. One request on first open, then manual retry only; no polling.
- Same-origin check, credentials same-origin, no-store.
- Output goes through `textContent`. The link uses a fixed origin and path and ignores the server's `tracking_url`. The link has `noreferrer`.
- The single 8 s `AbortController` timer is documented and cleared in `finally`, which also releases the retry button.

**Model**
- Only the two `ORDER BY` directions change, plus one read-only method. `test-history-order.py` asserts the byte equality.
- SQLite runs prove newest-first order across three pages, tie and NULL handling, counts, visibility, store, owner and status-0 exclusion, and notify-only comments.

**Runner control diff against WP2**
- New identity and token; the CSS token value only.
- Eight new protective trigger edges (102 total); the five admitted hook pairs and multiset are unchanged.
- cURL, private-cache and Pinta-schema checks run on Check/Apply only, not rollback.
- Apply order is 3,6,7,8,5,0,2,1,4: model before controller, controller before views, header last. Rollback order is 1,2,0,5,6,3,8,7,4: views and controller restored before the helper and JS are removed. Both orders are consistent with the dependencies.
- New files get mode 0644, no backup, and are removed on rollback. Automatic restore uses the manifest-preflighted plan.

**Templates and CSS**
- One order-wide support link pointing to the existing bot. Statuses 12 and 24 render a disabled button with no href. The per-item return link is removed.
- Payment is plain text. The order comment and notified history comments get the gold-soft note style. The back link and heading share a 4 px group. Cart wheels are explicit circles.
- The width-1200 rule is removed rather than overridden. Native forms and inline scripts are proven byte-identical.
- The disabled return style comes later in the cascade than `.bs-order-return:hover` at equal specificity, so it wins.

**Packet**
- Same control flow as the accepted WP2 packet, with a new receipts directory, new name, hash and labels.
- One-use apply and rollback markers, saved PHP path and admission checked against regexes, cache cleanup only after the patch succeeds (`&&`).

## Optional findings and closure

| # | Finding | Closure |
|---|---|---|
| O1 | The ACC007 current-status public message (`bs_status_message`) is still computed but no longer rendered outside the expanded history. The newest history row shows its message only when it carries no notified comment. The report does not disclose this. | Add to owner QA as an explicit item. Restore it in a future WP only if the owner wants it. No packet change. |
| O2 | NP state appears only after the status disclosure is expanded. This matches "у блоці статусів" and avoids an on-load request, but the owner should confirm it is the intended placement. | Owner QA confirmation. |
| O3 | `ORDER BY np.date_time DESC` is only correct if `date_time` is a DATE/DATETIME column. Verify checks that the column exists, not its type. This only matters for orders with several TTNs; ties are deterministic by `ref`. | Owner QA on a multi-TTN order if one exists, or a later check of the hash-only Pinta schema. |
| O4 | `Language:'UK'`, keyless public access, the config key name `shipping_pinta_nova_poshta_api_key`, and NP's "number not found" rows (returned as `success` with a status text) are unverified live. All fail soft. | Live owner QA: one real TTN, one forced failure, and the link click. |
| O5 | If the page origin differs from `config_url` (www/bare host, http/https), the script exits silently and the NP block stays hidden with no "unavailable" text. | QA on the canonical host. A visible fallback state could come in a future WP. |
| O6 | Accessibility of the disabled return button: it cannot be focused, its reason exists only in `title` and `aria-label`, and that `aria-label` does not contain the visible text (label-in-name). | Owner-chosen behavior is acceptable. A visible muted reason could come in a future WP. |
| O7 | The movement link uses the NP SPA's internal hash route, derived from a hash-bound public bundle. It may change upstream. | Click it at owner QA. A future fallback could point to the tracking root. |
| O8 | `rd18-np-*.json` files are not matched by OpenCart's `cache.*` cleanup. Growth is bounded at about 100 B per viewed TTN order and the files are retained on rollback (disclosed). | Accept, or add housekeeping later. |
| O9 | Cosmetic: the "History" docblock now sits above `tracking()`, the twig has a trailing blank line, the CSS block may mix line endings, `rd18_return` and `rd18.current` are unused, and a theoretical `json_encode` false would hit `setOutput(string)`. | No action. |

## Evidence limits

- I used no tools and executed, decoded and recomputed nothing. All results are the executor's recorded claims, bound to the declared hashes.
- The runner excerpt omits both compiled blobs: targets (encoded 641396 bytes, decoded SHA `ea134f55…1e731371`) and dependencies (encoded 12300 bytes, decoded SHA `c7a43a15…5e13b`). I did not see the payload bytes. Equality to the supplied after-files rests on the executor's `prove-source-boundaries.py` and file-engine claims.
- Hash-only, not seen: the seven acquired native NP sources, the three native photo sources, the official public SPA body (except the supplied decoder snippet), the other CSS and header bytes, configs, auth values, real rows and owner screenshots. I infer no consent to transfer raw acquired bodies.
- What the tests used, and therefore what they do not prove:
  - The render tests sort history inside the fixture; the DESC order is proven only on SQLite, not MySQL.
  - Carrier tests used intercepted cURL and fake JSON.
  - Packet tests mocked SSH and SCP.
  - Runtime metadata was faked in the file engine.
  - Bare Twig `ArrayLoader`, not the production wrapper.
- Not certified: the host, MySQL, Unix permissions and same-user race safety, the live NP API, the production Twig wrapper and cache, GA4, real photos, native SSH, visual layout and final owner QA. I did not see the screenshots.
