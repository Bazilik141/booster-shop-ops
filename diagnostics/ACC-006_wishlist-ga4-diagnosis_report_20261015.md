# ACC-006 — missing GA4 wishlist event, diagnosis only

Date: 2026-10-15
Revision: 2 (post-review evidence wording and supplementary public observation only).
Scope: the owner's request to investigate missing `add_to_wishlist`, after accepting functional QA items 1–5 of the header/media package. No production settings/data/code or patch is changed. ACC-008/009 and the future media handoff do not start.
Independent diagnosis review: `ACC-006_wishlist-ga4-diagnosis_claude-cli-review_20261015.md`, created only after a complete native subscription-CLI response. It is source/evidence review, not production GA4 ingestion proof.

Completed review: **Diagnosis OK; no blockers**, actual Claude Opus 5.5/high through the saved subscription CLI, no paid overage. The original reviewed report is preserved in the evidence folder. This revision adopts the review's non-blocking evidence qualifications; no code, settings or accepted patch has changed.

## Finding

Two independently blocking conditions are supported at different evidence boundaries:

1. **Public live configuration disables the event.** On `/product/One-Piece-Boosters-OP-11`, the rendered inline PS Enhanced Measurement configuration contains `ga4_tracking.add_to_wishlist:false`. Other ecommerce flags such as add-to-cart/view-item are true. There are no `add_to_wishlist_<id>` dataset keys in the rendered inline scripts. This establishes the currently published event flag; no database/admin/account read is needed.
2. **Current custom wishlist success is not connected to analytics.** The public `booster-wishlist.js` adds successfully through its own POST queue, updates count/message/button state, but never calls `ps_dataLayer.onClick`, `pushEventData`, `gtag` or `dataLayer.push`. All six wishlist buttons observed on the live product/related section are `type=button`, with `data-bs-wishlist-add`, but neither `data-ps-track-event` nor `data-ps-track-id`. The installed PS delegated click listener matches only `[data-ps-track-event]`. Thus enabling the flag alone does not bridge this custom action to analytics.

The public JS SHA-256 matches the October 14 source archive exactly, so source analysis is tied to the currently served JS rather than only a retained backup. The header/media runner changed no wishlist or analytics JS, and its locked SHA remains unchanged. The bridge gap is located in the restored custom flow. There is no evidence of when the tracking flag became false, so historical event loss cannot be attributed to the restore or dated from this diagnosis.

## Evidence

- Public read-only IAB navigation on 2026-10-15; guest page only. No wishlist click, login, cart write or analytics/account setting change was performed.
- DOM script text confirms the explicit false flag and zero rendered wishlist dataset keys. Read-only IAB evaluation globals were not usable as main-world runtime evidence (even jQuery was undefined there); no conclusion about missing runtime globals is drawn from that scope.
- Public static assets fetched without cookie/auth/session headers, both HTTP 200:
  - `catalog/view/javascript/booster-wishlist.js?v=acc006-20261013`: 5,291 bytes; SHA-256 `43876e81f4c1b3fd8125fadfefe510c419ebed588d54272e818e1521b153aeb4`.
  - `extension/ps_enhanced_measurement/catalog/view/javascript/ps-enhanced-measurement.js`: 1,915 bytes; SHA-256 `fa103e101401196e647da4e833436c19580bf6d20b20afdda1eb5417485cdb13`.
- Retained October 14 controller/model/template source is identity-bound by the successful October 15 header/media dry-run dependency gate. Native `eventCatalogViewCommonHeaderBefore` reads `analytics_ps_enhanced_measurement_track_add_to_wishlist`. Product/category builders create wishlist datasets only when this flag is enabled. The model's old replacement anchors expect stock submit/formaction buttons, which are absent from current custom wishlist buttons.
- `work/acc006-ga4-diagnosis-20261015/reproduce.mjs` executes both unmodified public JS files in a synthetic Node VM DOM/transport/PS-sink fixture. Its two cases disable/enable a stub sink, not the real module configuration. Each illustrates one successful wishlist POST and UI/count update without a PS analytics call. The button is deliberately unmarked and the delegated stock listener is not exercised; the positive control is called by the harness. This is supporting illustration, not an independent native-module or mutation-sensitive integration test. The stronger bridge conclusion comes from complete JS inspection and the observed actual DOM attributes. No real-browser hit or GA4 delivery is claimed.
- Supplementary public HTML read after review: `public-html-allowlist_followup.json` retains the raw allowlisted `ga4_tracking` configuration and one public button opening tag, with no whole guest HTML, session token or cookie saved. It confirms wishlist=false, cart/view-item=true, one `ps_dataLayer.setData` call, six cart dataset keys, zero wishlist keys and six custom buttons lacking stock attributes. This supports the functioning product-page data-injection path; it does not prove every home/search/module surface has suitable wishlist datasets.

Evidence folder: `ACC-006_wishlist-ga4-diagnosis_evidence_20261015/`. The owner's GA4 report screenshot/metric totals are not copied into any peer-review packet; only sanitized observations and public source/synthetic evidence are provided.

## Report-date limitation

The owner's screenshot is a standard events report ending October 14, while this diagnosis is October 15. The screenshot alone cannot rule out a later event. Google documents 24–48 hour processing for many standard reports and recommends Realtime/DebugView for collection validation:
https://support.google.com/analytics/answer/9333790?hl=en

The live false flag and missing custom bridge are independent code/configuration evidence, so waiting for report processing does not resolve those two findings. No GA4 property access, receipt of a test hit, or historical loss volume is claimed.

## Bounded correction proposal — not implemented

1. Have the owner enable **Add to wishlist / add_to_wishlist** in the installed PS Enhanced Measurement configuration, after the combined source correction is ready and reviewed. Preserve other flags and measurement IDs. No DB-setting write is authorized or included in this diagnostic.
2. Connect the successful custom wishlist add result to the existing PS ecommerce API, using that product's module-generated dataset. Preserve the module tracking flag, implementation mode and consent/tag behavior. Do not add a second standalone GA tag or hardcode measurement IDs/item prices. Review successful guest pending-add behavior, authenticated adds, duplicate/already-listed responses and error semantics before implementation.
3. Trigger exactly once for the accepted successful action; never on a failed request, removal or state refresh. A tracking exception must not turn an already successful wishlist operation into a visible failure. Test primary-product, catalogue and related-card datasets after enabling the module.
   The existing module fills `items[].quantity` from stock quantity in the supplied builders. The future wishlist payload must represent the selected action quantity (normally one), not stock: clone and normalize its item data without mutating shared datasets, and derive value consistently. Require a discriminating real-module/browser fixture with actual gtag/GTM sinks, false/true flags, success/error/duplicate cases and a mutation control. Home/search/extension-module dataset coverage and consent implementation require their actual owning context before implementation.
4. Do not merely append stock tracking attributes to the new button: the installed generic PS listener prevents default, disables the button and redispatches its click via a timer. Reusing that path blindly can change the existing queued request/error flow and counts intent before a confirmed success. A success-path integration should avoid that interception.
5. After the later reviewed owner deploy/configuration change, validate an actual event in DebugView/Realtime and inspect items/currency/value. Google event contract:
https://developers.google.com/analytics/devguides/collection/ga4/reference/events#add_to_wishlist

Any implementation needs the current exact affected source/context, its own guarded runner and automatic subscription-CLI review, followed by owner deployment/QA. The existing accepted runners remain byte-identical. The mobile hero corner request remains pending for the next patch; it is not implemented here.
