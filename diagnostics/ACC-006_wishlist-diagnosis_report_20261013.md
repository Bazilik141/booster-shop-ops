# ACC-006 — wishlist diagnosis (WP2)

Date: 2026-10-13 (Europe/Kyiv)
Executor: Codex. Authority: read-only WP2 in the ACC-004/ACC-006 handoff.
State: diagnosis complete for the supplied snapshot; **Gate A — await owner `go`**. No WP3 code, deployment, commit/push, DB mutation, Notion or dashboard update.

## 1. Outcome

The wishlist was **partially hidden in the redesigned customer interface, not removed from the backend**. The October 9 backup contains the stock model/controller, table, Ukrainian language file, list/page templates, logged-in database storage, guest session storage, and merge on ordinary login. The account mobile menu and stock account sidebar still link to it. Product-card/product-page add controls and the global header wishlist link/counter are absent from the rendered template source.

The smallest safe restoration reuses that storage and login merge, adds separate functional add/counter controls, repairs the existing wishlist page's remove/counter and out-of-stock behavior, and preserves the current cart/payment/gallery code. No schema migration or runner-time business-data repair is justified by this evidence.

Adding buttons alone is insufficient: stock logged-in add also leaves a pending session entry that removal does not clear; a later logout/login can restore a removed item. A missing/zero remove ID reaches the model's delete-all behavior. Both need narrowly scoped wishlist-controller fixes before exposing the feature.

## 2. Evidence and freshness boundary

Primary source: `backup-10.9.2026_22-22-10_boosters.tar.gz`; database member `mysql/boosters_ocart49.sql`, SHA-256 `cdb669a574c6e01d88d14a520ea254212ae47fa86082c5cb43d2818c72997266`. WP1 verified prefix `ocp5_` and language 4 / `uk-ua`. Relevant files were extracted outside the repository; database evidence is structure/counts only.

Later owner-export provenance exists locally for the **shared product/header files**:

- `diagnostics/RD-25-WP2-WP3_phase0_report_20261012.md:13-26` records October 12 export `RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz`, SHA-256 `6c7723bb5b2ae34af2e605b3a62dee9f68e5b2e9e8beae3a5ff2a6e2af8d36f0`.
- The retained product-card intake copy hashes to `d9237b2c9c0cc21cda73a86ba6cf1ff66b7ad956f0da3c24a8e1add6518037d6`; it still has no wishlist controls.
- `work/rd25-wp2-fix-20261012/source-gate.json` records the later October 12 gallery-fix intake. Its retained `source/catalog/view/template/product/product.twig` matches recorded hash `abfa0d62cc7542a34301f9ed8936203ea497a92a6e4deba52e785c1775ee3aec`; it also has no wishlist controls.
- Those retained input bytes are source evidence. Fixture outputs and generated `after/` files are not independent live evidence. The October 9 product/header bytes must not become WP3 anchors. The required fresh owner export was received and verified later on October 13; see section 8.

No live HTTP wishlist request was made: even its stock list renderer can delete unavailable/deleted-product records. No logged-in browser, host PHP, bank flow, email, or production runtime result is claimed.

## 3. What exists and what is missing

| Surface | Finding | Exact evidence |
|---|---|---|
| Table | Present, zero rows in the October 9 snapshot; not dropped | `CREATE TABLE ocp5_customer_wishlist`: customer/store/product integer IDs, `date_added`; primary key `(customer_id,store_id,product_id)` |
| Model | Stock add/delete/list/count, scoped to current customer and store | `catalog/model/account/wishlist.php:27-29,48-55,73-76,94-97` |
| Controller/routes | `index`, `list`, `add`, `remove` present; list page authenticates through the existing account validation route | `catalog/controller/account/wishlist.php:14-61,69-78,166-212,221-246` |
| Templates | `account/wishlist.twig` and `account/wishlist_list.twig` present; no external Twig include needed | Existing page renders `{{ list }}` from controller; list is returned by `load->view()` |
| Ukrainian strings | Present in the actual language extension, not guessed under the base catalogue | `extension/ukrainian/catalog/language/uk-ua/account/wishlist.php`; count label currently `Закладки (%s)` |
| Header backend | Computes DB count for logged-in customer and session count for guest; builds wishlist URL | `catalog/controller/common/header.php:165-176` |
| Header template | No `wishlist` link/count binding in the redesigned header source | `catalog/view/template/common/header.twig`; account/cart actions exist instead |
| Account mobile hub | Wishlist link already exists | `catalog/controller/account/account.php:49`; `catalog/view/template/account/account.twig:40-42` |
| Account sidebar | Wishlist URL/control exists in stock module | `extension/opencart/catalog/controller/module/account.php:26`; `extension/opencart/catalog/view/template/module/account.twig:14`; layout assignment remains runtime-dependent |
| Product card | Redesigned RD-04/CAT-004 card CTA renders cart form or unavailable text, with no wishlist button | October 9 `catalog/view/template/product/thumb.twig:65-92`; October 12 intake still has zero wishlist markup |
| Product page | Main form/cart, sticky buy and payment controls remain, but no wishlist control | October 9 `catalog/view/template/product/product.twig:229-456`; retained October 12 page still has zero wishlist markup |
| Shared JS | No dedicated wishlist helper is required by this OC4 route style; generic AJAX form handling exists | `catalog/view/javascript/common.js:140-213`; it uses submit button `formaction` and serializes the form |
| Events/theme modifications | No wishlist-disable event or database theme override was identified | WP1: `ocp5_theme=0`, `ocp5_modification=0`; relevant events 81/82 are enhanced-measurement view hooks, not a dropped route/table |

Zero table rows does not prove nobody tried the feature. Absence of controls is verified; the original historical deletion patch/author was not identified. Shared-template patch history search found RD-25 GA4 injector anchors mentioning stock wishlist buttons; those are preserved search/replacement definitions, not evidence that a real button exists.

## 4. Existing behavior and defects

### Add and guest merge

`wishlist.add` validates product existence, appends the product ID to session wishlist, deduplicates that session array, then writes the logged-in customer's DB row or returns a guest login/register prompt and session count (`wishlist.php:180-208`). Model add deletes the same customer/store/product pair before inserting; the composite key is an additional duplicate guard. No new wishlist data model is necessary.

`catalog/controller/account/login.php:183-189` adds guest session items to the authenticated customer's wishlist and unsets each pending item. Keep this code and ACC-003 token behavior unchanged. The registration controller does not contain this merge; automatic login immediately after registration is a distinct path. Do not promise a registration merge until it is specifically verified/approved; the handoff's normal guest → login case is supported in source.

For logged-in add, the unconditional session append remains after DB insertion. `wishlist.remove` deletes only the DB entry; `account/logout.php:18-29` does not clear session wishlist. Thus the source path is add → remove → logout → ordinary login → session merge restores the item (or imports pending items into another account using the same session). This is a source-derived defect, not a reproduced production incident. Recommended correction: guest-only pending storage; logged-in add removes the same ID from any pending session array, and remove clears the requested pending ID too. Do not change authentication/session identity mechanisms.

### Remove and counter

- Missing/zero `product_id` defaults to 0 in `wishlist.remove` (`:226-240`). Model `deleteWishlist(customer,0)` omits the product predicate and deletes **all** wishlist rows for that customer/store (`model:48-55`). Require a positive explicit product ID and reject invalid/missing input before calling the model.
- Remove checks logged-in state and the model scopes customer/store. Preserve these boundaries; use the established authenticated account validation/customer token mechanism for new mutation controls without editing login/register token logic. A narrowly scoped POST remove control is preferred over exposing a generic GET delete action; its exact route compatibility must be verified in WP3.
- Remove returns success but no new count. `wishlist.twig:38-41` reloads the list and does not update a visible counter. Add returns only the stock formatted label `total`. Return/consume a numeric count consistently after add/remove and reconcile it after list rendering.
- Header count is the raw DB row count. The list filters through `getProduct()` and deletes entries whose product is unavailable to the model (`wishlist.php:107-155`). Read the final count after that filtering; otherwise a counter can differ from the visible list when a product is disabled/deleted. Do not run this cleanup as a WP2/live diagnostic or as a broad migration.

### Out of stock and UI plumbing

- Quantity-zero products are not removed merely because stock is zero: the controller retains the product and derives stock text (`wishlist.php:116-129`). Its template nevertheless always renders an enabled cart button (`wishlist_list.twig:39`). Add an explicit availability flag consistent with the approved unavailable rule; retain sold-out rows and disable only their buy control. Preserve pre-order behavior and existing stock/cart validation.
- Generic `common.js` now turns every successful AJAX form response into a **cart** toast (`:72-94,193-201`). Its action text/links are cart/checkout-specific. Simply adding the stock wishlist submit button to a cart form would show misleading cart text, trigger cart reload, and leave the wishlist counter unchanged.
- `common.js:116` does provide `$.fn.button`; therefore the stock list's loading/reset calls are not an established missing-plugin error. Do not introduce a speculative plugin fix.
- Existing removal reload URL (`wishlist.twig:41`) lacks the customer token although the list route validates the account. `catalog/controller/account/login.php:305-306` explicitly requires matching request/session `customer_token`, so that reload fails account validation in source. Use the controller-supplied authenticated list URL for reload and counter refresh; verify runtime behavior in WP3 owner QA.
- The page already loads common header/columns/footer and is within the same stock account structure as other account pages. Keep that structure/ACC-001 navigation. No RD-16 visual design or extra ArrayLoader `{% include %}` is authorized.

## 5. Smallest restore package proposed for WP3

One self-contained runner, `patches/ACC-006_wishlist-restore_<YYYYMMDD>.php`, only after the owner accepts this diagnosis and says `go`.

| File | Bounded proposed change |
|---|---|
| `catalog/controller/account/wishlist.php` | Guest-only pending session behavior; positive-ID remove guard; account validation; numeric counts/authenticated reload URL; availability data. Reuse stock model |
| `catalog/view/template/account/wishlist.twig` | Account-page functional messages, correct list reload and counter coordination |
| `catalog/view/template/account/wishlist_list.twig` | Retain name/price/availability/remove; explicit inactive buy for unavailable products |
| `catalog/view/template/product/thumb.twig` | Separate functional wishlist button for every card state, including sold-out; keep cart form/GA4/media intact |
| `catalog/view/template/product/product.twig` | Separate functional wishlist button outside conflicting main/cart/payment submission behavior; preserve options/gallery/sticky buy and credit controls |
| `catalog/view/template/common/header.twig` | Minimal wishlist link/count hook and one dedicated JS asset reference; no header redesign |
| New `catalog/view/javascript/booster-wishlist.js` | Scoped add/remove/counter handling with feature-specific messages; safe text output and actionable guest login link; no cart toast/helper rewrite |
| `catalog/controller/common/header.php` | Only if needed to supply numeric initial count/safe authenticated URLs instead of parsing a localized label |
| `extension/ukrainian/catalog/language/uk-ua/account/wishlist.php` | Only required functional labels/messages; account page may say `Улюблене`; final visual block naming/button variants remain deferred |

Keep `catalog/model/account/wishlist.php`, login/register/logout, shared `common.js`, DS CSS, payment/checkout/NP, mail/status/SEO/feed/schema untouched. Inspect actual current product-controller route variables before deciding whether a controller change is genuinely needed; do not add it by assumption. Use component-specific data attributes/type=button so existing cart submit and analytics bindings do not mistake a wishlist action for add-to-cart.

**DB impact:** No DDL, no registry/settings/status mutation, no data migration/repair in the runner. Expected feature operation later inserts/deletes the user's own `customer_wishlist` rows through the existing model; that normal application behavior is part of owner QA, not work performed during WP1/WP2. If fresh preflight reveals a missing/different table, stop for a separate explicitly approved DB step and rollback SQL; do not recreate a table automatically.

**CSS/visual impact:** Existing `bs-*` classes and plain controls only. No shared CSS/token edit is presently justified. Root cause is absent controls/bindings and the specific controller/template defects above; do not stack CSS overrides or add `!important`. Global header/common JS are shared high-traffic surfaces; one isolated script avoids changing every AJAX form. Final button designs remain a later owner decision.

**WP1 dependency:** None of the proposed customer custom-field/status changes is needed. Wishlist relates customer/store/product IDs already. Preserve current ownership/authentication and do not bundle ACC-004 implementation with it.

## 6. Blast radius and rollback

- Product-card template is shared by category, home/product modules, search and other listings. Retain media-fit/crop, Rare Pack/preorder/out-of-stock badges, long names, cart options/quantity flow and GA4 behavior.
- Product-page template includes recently changed gallery/media and credit controls. Fresh inputs are mandatory; never anchor on the October 9 source or overwrite the whole page from that backup.
- Header is global; new counter/script must be harmless when no wishlist controls exist. Do not change shared cart toast behavior to solve this feature.
- Stock wishlist page uses the existing image resize path. Reusing it is the smallest scope; adopting RD-25 media presentation everywhere would be a separate decision.
- Runner backs up exact touched files with hashes before writing; existing-file/anchor/source checks, PHP 8.0 syntax gate with restore-on-failure, file-local idempotency markers and self-delete apply. Rollback restores those files, removes only the newly created asset if unchanged, then refreshes compiled templates/cache.
- Source rollback does not delete wishlist choices already saved by users; no rollback SQL is needed for the proposed source-only package. Preserve their data.

## 7. Focused verification and owner QA for the future package

Local verification after `go`: use fresh copied source, validate exact before/after diff, PHP 8.0-compatible syntax and JavaScript, runner first apply/repeat/safe-fail/rollback, and meaningful synthetic wishlist flows. No real customer data or live form submit is needed for local proof.

Owner production QA after review/deploy:

1. Logged-in add from card and page → one row/list item; repeat add does not duplicate; counter equals the rendered list.
2. Remove one item → only that item gone; count changes; removing with missing/zero ID is rejected; add/remove → logout/login does not restore it.
3. Guest add → clear login action/session item; ordinary login merges it once with existing wishlist. Switching accounts in the same browser must not inherit another logged-in customer's pending item.
4. Quantity-zero/out-of-stock item stays listed with inactive buy; in-stock/preorder/optioned products retain their normal purchase behavior.
5. Long names, sold-out, discounted and Rare Pack cards; visible/focus/active/loading states at at least 3 breakpoints (390/1024/1440); repeated AJAX navigation does not bind duplicate handlers.
6. Existing gallery/main/sticky cart and credit-open controls remain unchanged. All eight Tier 1 URLs from the handoff return normally; checkout entry starts. Inspect only the bounded QA-time OpenCart error log window for new errors. No test order/payment is required by this wishlist scope.

Local static evidence is not owner production acceptance. ACC-006 is not Done until functional owner QA and the deferred visual work is completed or explicitly split.

## 8. Fresh source request before runner authoring

The required October 13 narrow refresh has been received: **`ACC-006-current-files-20261013.tar.gz`**, 68,799 bytes, SHA-256 `41e702bc7d601574d83b23ebfff4d802f229a645f41ae67a83779e92761c078c`.

All **20/20** requested regular files are present, with no unexpected members, duplicates or unsafe extraction paths. They were extracted outside the repository. Compared with the October 9 diagnosis, 13 previously hashed files are byte-identical, three shared templates changed (`product/thumb.twig`, `product/product.twig`, `common/header.twig`), and four additional requested dependencies are included. The fresh templates still contain no wishlist controls/header binding; the unchanged account files retain the stock routes/model, ordinary-login merge and ACC-003 stable tokens. The source-refresh prerequisite is now satisfied; use this fresh export for any later runner.

Receiving the archive did **not** open Gate A/B or start WP3. No PHP runner or feature code was created. Database/theme/event metadata remains the October 9 snapshot and still requires a narrow read-only hosting preflight before any later target writes.

The collection command below is retained for provenance, not as a pending repeat request. It deliberately excludes config.php, secrets, logs, SQL and images.

Owner command in cPanel Terminal:

```bash
cd ~/public_html && tar -czf ../ACC-006-current-files-20261013.tar.gz catalog/controller/account/wishlist.php catalog/model/account/wishlist.php catalog/controller/account/login.php catalog/controller/account/register.php catalog/controller/account/logout.php catalog/controller/common/header.php catalog/controller/product/thumb.php catalog/controller/product/product.php catalog/model/catalog/product.php catalog/view/template/account/wishlist.twig catalog/view/template/account/wishlist_list.twig catalog/view/template/account/account.twig catalog/view/template/product/thumb.twig catalog/view/template/product/product.twig catalog/view/template/common/header.twig catalog/view/javascript/common.js extension/opencart/catalog/controller/module/account.php extension/opencart/catalog/view/template/module/account.twig extension/ukrainian/catalog/language/uk-ua/account/wishlist.php extension/ps_enhanced_measurement/catalog/controller/analytics/ps_enhanced_measurement.php
```

Download `/home2/boosters/ACC-006-current-files-20261013.tar.gz` and put it in the repository root. If tar reports a missing path, stop and send that error; do not substitute a guessed file. A source-only archive does not refresh DB metadata: WP3 must also use a narrow read-only host preflight of the wishlist table and relevant theme/event metadata before target writes. The future runner can fail closed on mismatch; no full DB export is requested here.

## 9. Gate and status recommendation

WP1 report: `diagnostics/ACC-004_customer-data-architecture_report_20261013.md`.
WP2 report: this file. Privacy-safe hash/metadata companion: `diagnostics/ACC-006_wishlist-diagnosis_evidence_20261013.json`.

**Gate A:** stop after both reports; await owner's explicit `go`. **Gate B:** WP3 requires acceptance of this diagnosis and approval of any DB step. The proposed path has no DB migration; if fresh evidence changes that, stop again before implementing it. Receiving a file alone is not `go`.

Recommended status for Claude only: ACC-006 In progress after WP2; closure requires owner QA and the later visual step/explicit split. No status was written.
