# Changes requested: RD-18 WP1 r3 exact event-admission successor

There is **one mandatory finding (M1)**. It sits in the guard's model-trigger coverage, which r3 inherited unchanged from R2. The r3 delta itself is correct: the fifth exact pair, the five-row multiset, the new identity and the shared lock. Once M1 is closed, I expect the delta to be approvable without other code changes.

## Binding identities

This verdict covers only these bytes. I used no tools and computed no hashes. The values come from the file headers and the supplied facts, cross-checked against each other.

| Artifact | SHA256 | Bytes |
|---|---|---|
| Runner `patches/RD-18-WP1_orders-r3_20261027.php` | `4f567fe712a1fcb98bc264efb3e511bf4f459d3b1c63d5a148e94a9e7ee48d5c` | 163886 |
| Owner packet `diagnostics/RD-18-WP1_owner-r3_20261027.ps1` | `6f4a2ed1b299c35fb5ee6a8c67ffdfc3c31f86d5b88ba1cc2bfc61f984e936b5` | 11852 |

**Cross-checks**
- The packet's `$Sha` literal equals the runner hash.
- `release-facts.json`, the report and the CANDIDATE block all carry the same pair.
- The R2 verdict (request `82f51819…`, response `2dc57a89…`) binds only `f2bd…/2b27…`. It does not certify these bytes.

## Mandatory finding

### M1: guard model triggers can never match the real event names

**What is wrong.** The pinned `system/engine/loader.php` (`ea20…`) builds model proxies like this:

```php
$proxy->{$method} = $this->callback($route . '.' . $method);
// inside callback:
$this->event->trigger('model/' . $trigger . '/before', ...);   // $trigger = 'account/order.getOrder'
```

So the events actually fired are `model/account/order.getOrder/before|after`, with a dot. `rd16_event_check` lists only slash forms, such as `model/account/order/getOrder/before`. Its matching is `fnmatch(pattern, target)` or `fnmatch(pattern.'*', target)`, so an enabled row whose trigger is exactly `catalog/model/account/order.getProducts/after` is classified **not relevant** and admitted silently. The same applies to the `model/account/order.*` pattern.

**What is affected.**
- The report says the runner narrows admission to the order/hub/shell/pager "view, controller and protected-model surfaces". For models, that protection is inert.
- The controller list does include dot forms, so view and controller coverage is unaffected.
- RD18 adds new consumers of this surface: up to 10 extra `getProducts` calls per list page, plus the hub status projection. A hidden model `after` hook would now run in new contexts unnoticed.
- The tests repeat the gap. `test-admission.php` builds its 102 trigger cases from the slash list only.
- The capture repeats the gap. Classifier `48f8…` is presumably the same predicate, so "112 scanned / 5 relevant" does not prove that no dot-form account/order model hooks exist.

**Scope.** This is not introduced by r3; R1 and R2 did not catch it. It is still a correctness defect in the release's fail-closed admission contract, in a risky zone (order status), on exactly the event-matching surface this review was asked to assess.

**Closure: either option.**
- **(a) Fix and re-review.** Add the dot-form model triggers to the target list: `model/account/order.<method>/before|after` for the 11 listed methods. Add a discriminating test that produces the event strings by actually invoking the pinned `Loader::callback` with a recording Event fake, and asserts that each resulting trigger with an unadmitted handler is relevant and rejected. Then send an affected fresh review. A host hook that this exposes is a correct STOP, not grounds to widen the guard.
- **(b) Owner decision.** The owner makes an informed scope decision to accept slash-only model coverage as a recorded known limit. The report and the facts must then stop claiming model-surface protection.

## Assessed and acceptable (no finding)

**Fifth-pair admission is precise.**
- The match is an exact raw `in_array([$pattern,$action], …, true)`. It runs before prefix stripping and before the `|` to `.` rewrite.
- These variants all fall through to the conservative path, are relevant, and are rejected because they are not `event/theme`: a `system/` prefix, the `/after` edge, a `.unreviewed` or other wrong method, a pipe-form action, and whitespace or alias variants.
- Broad patterns `''`, `*`, `catalog/*` and `view/*/before` with a non-theme action are rejected.
- The multiset must equal the five sorted captured strings exactly. Duplicate, missing or extra rows are refused, and tests cover each. A legitimate theme row would still change the multiset, which is a conservative STOP.

**Dispatch.**
- `startup/event` strips `catalog/`.
- `Event::trigger` uses a `^`-anchored, unterminated prefix regex with `preg_quote`. The registered pattern therefore matches only events that begin with `view/account/order_info/before`. No target event extends that string.
- `Action` splits at the last `.`. That gives controller `extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement` and the public method `eventCatalogViewAccountOrderInfoBefore`, called via factory and `is_callable`.

**Four-reference loader arguments.**
- `Loader::view` passes `[&$route, &$data, &$code, &$output]`. The callback's third parameter, named `$output`, therefore binds to the loader's `$code`.
- The loader's own `$output` stays `''`, so the native render path runs on the rewritten `$code`. The test asserts that the override is empty and the code was supplied.
- The theme event changes `$code` only when a theme row exists. Runtime checks confirm zero rows for all seven routes.

**Template source path.**
- `getTemplateBuffer` with an empty buffer resolves, in order: `<theme>/template/`, then `default/template/`, then base.
- The runner mirrors this per store, with store-N settings overlaid on store 0, and requires the first two to be absent.
- `modCheck` can only redirect a template path that starts with `DIR_MODIFICATION`; the runner's modification-prefix STOP covers that.
- An empty theme yields `template//template/` in the vendor code versus `template/template/` in the runner. On POSIX these are the same path.

**Callback reach and before/after equivalence.**
- `$args['products'][$index]` is modified only for `special`, `product_id` and `ps_has_options`.
- The RD18 controller builds products with no skipping, in the same `getProducts` order, so indices align. New template fields (`rd18_*`) are untouched.
- Replacements: the two legacy anchors match 0 in both templates; `{{ footer }}` matches 1 in both. Injected scripts are identical. The browser evidence shows 0 tracked attributes, identical FormData and one native reorder POST each.
- The removed-catalog `product_id=null` behaviour is pre-existing and preserved.
- `$order_id` comes from the request without a customer filter. The view renders only after the controller's owner-scoped `getOrder`, so there is no new disclosure.

**Data boundary.**
- Helper SQL is SELECT only.
- The session write to `ps_item_list_info` is retained and stated honestly.
- No purchase, order, remote or provider writer is called.

**Packet.**
- Identity, receipt directory and banners are swapped consistently. The backup regex equals the runner's naming. `--rollback` refuses old-ID backups.
- The fixed lock `RD-18-WP1_orders_20261027.lock` is the same file the old runner creates with `xb`, so the two pairs are mutually exclusive.
- Old state and stage are untouched. The old pair cannot pass Verify while the fifth hook is enabled.

**Host Verify ordering.** Runtime and event admission run first, then all 66 dependency hashes, then private-stage candidates. All of this happens before any webroot write. Apply recomputes admission and rechecks dependencies before writing.

**UI audit.**
- The guard and packet add no `!important`, `setTimeout`, absolute/fixed positioning or magic values.
- Payloads are byte-identical to R2, so the R2 UI verdict and the 63-layout and 17-native-context proof apply.
- The only `setTimeout` in scope is pre-existing pinned third-party analytics JS. It is not authored here and not bound on this page, since 0 elements carry `data-ps-track-event`.

## Optional findings (non-blocking)

**O1: other unpinned or unguarded surfaces.**
- The callback also calls `catalog/product.getProduct`, `catalog/manufacturer.getManufacturer` and the extension language file. None of these is among the 66 pins or supplied as a body; the tests fake them.
- Language events (`language/account/order/*`) and other model routes used by RD18 controllers are also not guarded.
- Impact is limited to analytics output and labels.
- Closure: pin them or record them as a limit.

**O2: the R2-O3 gap recurs for r3.** None of the r3 result files embeds the runner or packet hash it tested: `runner-results.json`, `admission-results.json`, `packet-PS51/PS7-results.json` and the hook results. Closure: bracket each result with the exact pair hashes before and after, as was done for R2.

**O3: stale comment.** The comment in `rd16_event_check` still says "twelve added dependency hashes".

**O4: old pair remains runnable.** The old staged runner and uploaded-only state remain. If the order-info analytics event were later disabled, the old pair's 4-row multiset would match and it could Verify and Apply. The output would be the same bytes, with only receipts and backups differing; the lock prevents concurrency. Closure: the owner records the old pair as superseded and never run. Any cleanup is a separate owner decision.

**O5: test render path differs from production.** The native test renders through a bare Twig `ArrayLoader`, not the pinned `twig.php` library path.

## Evidence limits

- **Not decoded or executed.** I did not decode the base64 payloads. I relied on the guard-only diff, the `build-successor.py` contract-equality assertion and the runtime payload hash guard. I executed no tests and saw no screenshots.
- **Live callback not admitted.** Callback, controller, model and JS bodies are the accepted RD16 artifacts reused by hash. Fresh capture proved only the four core hashes. Host Verify must prove all 66 before any write; drift means STOP, never re-pin.
- **Not certified.** Real MySQL and event metadata, store theme settings, Unix ownership, native SSH/SCP, real GA4 delivery and real catalog data are not certified. Data was fictional only.
- **Not supplied here.** The `account/order` model body (pinned `2862…`) was not re-read in this packet. Its SELECT-only nature rests on the earlier accepted review.

## Next steps (no grant)

1. Close M1 by option (a), which needs an affected fresh review, or by option (b), the owner's informed scope decision.
2. After closure and acceptance, run only the new packet: Upload, then Verify, expecting `RD18_WP1_R3_VERIFY_OK`, an admission hash, `production_writes=0` and `backup=none`. Apply is a separate step.

This review grants no deployment, SSH, key, server, Git, Notion or dashboard action. It makes no production or QA claim.
