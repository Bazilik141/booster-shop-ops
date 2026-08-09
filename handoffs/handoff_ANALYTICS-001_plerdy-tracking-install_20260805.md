# Codex Handoff — ANALYTICS-001: Install Plerdy tracking snippet

Date: 2026-08-05 | Parent: none — owner declined Notion roadmap card, executing manually
Execution: **owner, manually via cPanel File Manager** (not routed to Codex).
This file is kept as the scope/rollback record. See chat for the exact
step-by-step owner walkthrough (2026-08-05).

## Context
Owner wants on-site behavior analytics (Plerdy — heatmaps/click tracking, similar
class of tool to Hotjar) to see where visitors hesitate or drop off. Plerdy's
setup step 2 provides a standard async `<script>` snippet that must be pasted
on every page immediately before the closing `</body>` tag. Confirmed via
today's cPanel backup (`backup-8.5.2026_10-49-27_boosters.tar.gz`) that
`catalog/view/template/common/footer.twig` is the single shared footer
rendered on every page, ends cleanly with the back-to-top `<script>` block
followed directly by `</body></html>`, and currently contains no analytics
scripts (GA4/gtag lives in a separate extension model, not this file — see
`patches/st2a10_gtag_guard_20260613.php`). No prior Plerdy work exists in this
repo.

Exact snippet to insert (owner-provided, do not alter):

```html
<!-- BEGIN PLERDY CODE -->
<script data-plerdy_code='1'>
(function(w,d){
  if(w.__plerdyCode)return;
  w.__plerdyCode=1;
  w._protocol=w.location.protocol=="https:"?"https://":"http://";
  w._site_hash_code="3319dcc3c099efec8295a2e945a0154c";
  w._suid=79605;
  var s=d.createElement("script");
  s.async=true;
  s.referrerPolicy="strict-origin-when-cross-origin";
  s.src="https://a.plerdy.com/public/js/click/main.js?v="+Math.random();
  d.head.appendChild(s);
})(window,document);
</script>
<!-- END PLERDY CODE -->
```

## Scope (what to change)
- `catalog/view/template/common/footer.twig` — insert the Plerdy snippet
  above verbatim, placed immediately after the existing back-to-top
  `<script>...</script>` block and immediately before `</body></html>`.
  Insert as a literal inline `<script>` block (no `{% include %}` of a new
  partial) — this repo's OC4 `ArrayLoader` cannot reliably resolve new
  third-party Twig partial includes, so inline insertion is the only proven-safe
  approach for this file.

## What NOT to touch
- `sitemap.xml`, `robots.txt`, redirects, canonical tags, `.htaccess` — out of scope.
- Checkout, payment, Hutko/Checkbox fiscalization flow — out of scope, do not touch.
- Merchant feed / Product schema/JSON-LD — out of scope.
- `extension/Ps_enhanced_measurement/...` (GA4/gtag model) — separate system, do not edit.
- Any other file under `catalog/view/template/` besides `footer.twig`.

## Acceptance criteria
- [ ] `catalog/view/template/common/footer.twig` renders the Plerdy block on
      every page, immediately before `</body></html>`.
- [ ] Homepage, one category page, one product page, and the cart page all
      still render normally with no PHP fatal/warning and no new browser
      console errors after the change.
- [ ] `php -l` passes on the patched file before write (per standard patch
      convention).
- [ ] Plerdy admin "Перевірити встановлення" check (step 2,
      https://a.plerdy.com/admin/second_page?id=79605) returns success after
      deploy and a hard cache refresh.

## QA checklist (owner runs after deploy)
- [ ] View source on boostershop.website homepage — confirm
      `<!-- BEGIN PLERDY CODE -->` block is present right before `</body>`.
- [ ] In Plerdy admin, click "Перевірити встановлення" — confirm it turns green
      (currently red ✗, per owner screenshot 2026-08-05).
- [ ] Hard-refresh home, one category, one product, and cart page — confirm
      normal layout/behavior, no visible breakage.
- [ ] Quick load check on checkout page (load only, not a full order) —
      confirm it still loads normally, since this is a shared sitewide
      template.

## Rollback note
Standard patch convention applies: timestamped backup of `footer.twig` to
`_patch_backups/<patch>-<ts>/` before write, `php -l` gate with restore-on-fail,
idempotent marker so a repeat run no-ops. To roll back manually: restore
`footer.twig` from the pre-patch backup in `_patch_backups/`.

## Risks
Not a listed risky zone (checkout/payment/fiscalization/NP/order
status/Merchant feed/schema/SEO-sitemap/CRM/DB) — this is a sitewide shared
template, so apply normal patch discipline (anchor pre-check, backup, `php -l`
gate) rather than a skipped/blind edit. No `bs-checkout-smoke` or
`bs-merchant-schema-qa` needed since neither checkout nor schema/feed data is
touched.

## Recommended status after execution
Move to "In Review" (Claude) pending owner QA above; owner closes to "Done"
after confirming the green check in Plerdy admin.
