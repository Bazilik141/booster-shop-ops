# RD-UX batch — Claude review of runners 3b–7 (2026-10-04)

Reviewer: Claude (chat). Executor: Claude Code. Scope: `RD-UX-qa-followups_20261004.php` (3b),
`UX-009_live-search_20261004.php` (4), `UX-009_search-page_20261004.php` (5),
`UX-003_grid-fluid-991_20261004.php` (6), `UX-003_category-filter-C_20261004.php` (7).

## Verdict

All five: **Deploy OK, with notes**. Nothing blocking. Deploy strictly in order 3b → 4 → 5 → 6 → 7,
one at a time, theme-cache refresh + owner QA after each. Rollback only in reverse order.

## How it was checked (independent of the executor)

- Runners 1–3 in `patches/` are byte-identical to `HEAD` (the deployed versions).
- Independent chain run in Claude's cloud sandbox: live2 pull (`rd-ux-batch-live2-20261004-1036.tar.gz`)
  → runners 1, 2, 3, 3b, 4, 5, 6, 7 on PHP 8.4 CLI with Twig 3.28 source (spl_autoload gate). All eight
  returned `done=ok`, every runner self-deleted, every SHA guard matched the previous runner's output.
- Runner 3b read in full by Claude. Runners 4–7 read in full by four parallel review agents with
  per-step before/after states, diffs, Twig renders and headless-Chromium measurements.
- GA4 `view_item_list` anchor `{% if products %}` is present exactly once in `search.twig` and
  `category.twig` after the chain; `</head>` once in `header.twig`.
- Category JSON-LD, FAQ and load-more scripts: byte-identical before/after runner 7.
- Live-search XSS probe (`<img onerror>` query): inserted via `.text()`, no execution; URL via
  `encodeURIComponent`.

## Findings worth acting on

| ID | Runner | Severity | What | Action |
|---|---|---|---|---|
| R1 | 7 | Owner pre-check | `{{ column_right }}` is moved wholesale into the «Фільтр» panel. Any non-filter module in the Category layout's right column (or a per-category layout override) would end up inside the panel. Layout assignments are not in the pull. | Owner checks Admin → Design → Layouts → Category before runner 7. |
| R2 | 7 | Non-blocking | At ≥992 a category with no sub-/sibling categories shows an empty 8×8 grey `.bs-ff-chips` placeholder. | Polish runner: `.bs-ff-chips:empty{padding:0;background:none}`. |
| R3 | 7 | Owner decision | Every tick reloads the page (unchanged 250 ms apply) and the panel returns closed; UX-004 chips never render (controller never fills `active_filters`), so the badge is the only active-filter signal. | Owner decision on chips; panel-open-after-reload judged in live QA. |
| R4 | 6 | Owner decision | Pre-existing horizontal scroll ~769–905 px (`.bs-header__actions` too wide). Not introduced by runner 6 (scrollWidth identical before/after). | Owner decision: header follow-up or accept. |
| R5 | 6 | Risky-zone note | Checkout gets wider at 576–991 (form column next to the summary grows at 901–991). Markup/JS untouched. | Owner QA on checkout without submitting. |
| R6 | 4 | Non-blocking | On a 320 px phone the error-state button «Шукати на сторінці результатів →» overflows (`white-space:nowrap`). Fits at 360/390. | Polish runner: `white-space:normal;height:auto;max-width:100%` on `.bs-ls-state__btn`. |
| R7 | 5 | Owner check | GA4 `search` event anchor is unknown (module source not in the pull); stock form fragments were rewritten. `view_item_list` anchor kept. | View-source check before and after runner 5. |
| R8 | 7 | Note for future work | The `|replace` swap of `<aside id="column-right" …>` fails silently if `column_right.twig` changes or a DB theme override appears. | Any future runner touching `column_right.twig` must grep `category.twig` for the pair. |
| R9 | 7 | Note | Report claim "cards keep their width" is inaccurate: 4 columns at ≥992 make cards narrower (≈386→330 px at 1440). This is the approved design. | None. |
| R10 | 7 | Note for option (b) | Dead UX-004 block has `onclick="location='{{ f.remove_url }}'"`; if ever filled from request data it needs `|escape('js')` or a data attribute. | Only relevant if chips option (b) is chosen. |

Executor reports omit `cd ~/public_html` before rollback commands; the owner runbook adds it.

## Owner decisions

- R4 header overflow 769–905: **fix** (owner, 2026-10-04) — in the polish runner; icons-only or short cart label at the affected widths so the header fits.
- R3 filter chips: owner (2026-10-04) rejects full-page reload on every tick as unacceptable UX and wants a modern flow. Options a/b/c only change who renders the chips; none removes the reload. Claude recommends a separate package: AJAX filtering without reload (fetch the same filtered category URL, swap #product-list + pagination + results, chips from the checked state, history.pushState + popstate, re-init load-more, mobile panel stays open with a "show products" action, fallback to normal navigation on any error). No PHP, no DB, URLs/canonical unchanged. Known gap: GA4 view_item_list is not re-sent for AJAX-updated lists. **Owner confirmed this option (4) for runner 9, 2026-10-04.**

## Follow-up runners (after runner 7 QA)

Runner 8 (polish): R2, R4 (header fits at 769–905), R6 and any live-QA findings, built on the post-runner-7 state.
Runner 9 (filters without reload + chips): separate work package (owner-confirmed option 4), built on runner 8 output; Claude Design states brief first.
BUG-004 builds on the final state.

## Deploy and owner QA (2026-10-04)

Runners 3b, 4, 5, 6, 7 deployed by the owner. Owner QA passed except:

- Console: Chrome issue "CSP blocks the use of `eval`" (`script-src`). Not introduced by this batch: no runner adds `eval`, `new Function` or string timers; the only `new Function` in pulled files is in `nunjucks-slim.js` (unchanged, not referenced by pulled templates). Follow-up 2026-10-05: production sends no CSP header or meta tag on 7 checked routes (home, category, search, cart, checkout, success, failure); the category page has no iframes or service workers; `eval` runs in the page. The issue cannot come from a site CSP; most likely a browser extension in the owner's Chrome. Owner confirmed 2026-10-05: caused by a built-in VPN browser extension; console clean without it. Closed.
- List/grid toggle: hidden by an earlier owner decision; the QA item is not applicable. Remove it from future QA lists.
- Category header card <992: owner rejects the single swipe row → two rows (runner 8, INDEX section "Deploy log runners 3b–7 and runner 8").
- Mobile gap (~35 px) between the category header card and the grid at 400 px → runner 8.

Runner 8 handoff: INDEX section above. Runner 9 design brief: `handoffs/handoff_UX-003_filters-no-reload_visual-design-brief_20261004.md`.

## Runner 8 review (2026-10-05) — `UX-003-005-009_polish_20261004.php`

Verdict: **Deploy OK.** Independent run on the reconstructed post-runner-7 state (PHP 8.4 CLI, site Twig 3.28): all SHA guards ok, Twig gates passed, `done=ok`, self-deleted; after-SHAs equal the report. Shared runner library byte-identical to runner 3b. Diff limited to the five handoff items plus the ds.css token; no JS, PHP, DB, URL or canonical change. Cart label split (`text_items|split(' - ', 2)`) survives AJAX refreshes because every refresh reloads `common/cart.info`. The owner's "35 px" gap is product-photo whitespace; the box gap is now 16 px at every width.

Notes: `cart.twig` was changed again — BUG-004 must build on runner 8 output. Runner 9 design (Claude Design) should use the post-runner-8 two-row card.
