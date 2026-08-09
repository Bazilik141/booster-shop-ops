# Codex Report — CRM-005 UI2: stable integrity tile and result copy

Date: 2026-08-09

## Scope

Implemented both handoff work packages in the dashboard only.

1. `#crmIntegrityCard` is always last through CSS grid ordering, not a change
   to asynchronous append order.
2. A `Скопіювати результат` control copies the already received
   `crmIntegrityState.result` JSON. It performs no additional API request.

No Apps Script, `integrity_check` response contract, click-to-run behaviour,
or 3D-P panel code was changed.

## Root cause and override history

`renderCrmIntegrityTile()` appends the card after summary rendering, while
`repeatRateCard` is appended later by the independent `monthly_summary`
branch. Their completion order made the final card unstable.

Searched `patches/`, `dashboard/`, and `tests/` for
`crmIntegrityCard`/integrity-tile overrides before editing. No prior CSS
override for this selector was found.

The source fix is the handoff-prescribed declaration:

```css
#crmIntegrityCard { order: 999; }
```

The `.cards` container is a grid. The selector is limited to the target card,
uses no `!important`, and preserves the existing remove-and-append lifecycle.

## Files touched

```
dashboard/booster-dashboard.html       — CSS grid order; copy helper and control
tests/crm-005-integrity-tile.test.mjs  — clean/problem/copy/fallback/error coverage
```

## Behaviour verified locally

```
node tests\crm-005-integrity-tile.test.mjs
CRM-005 integrity tile tests passed

git diff --check -- dashboard\booster-dashboard.html tests\crm-005-integrity-tile.test.mjs
(no output; whitespace check passed)
```

The test proves all of the following:

- zero `integrity_check` calls on page load and exactly one call site;
- a single idempotent tile survives re-render;
- CSS `order: 999` is present;
- the copy control appears for clean and problem results;
- copied payload includes problem and timing fields;
- non-secure-context `document.execCommand('copy')` fallback works;
- clipboard failure reports an explicit button label.

The two-second acknowledgement uses the named
`CRM_INTEGRITY_COPY_CONFIRM_MS` constant. The fallback textarea uses a fixed,
transparent position only to avoid moving the dashboard layout; both choices
are documented inline. No new unexplained `!important`, timer, or magic pixel
override was added.

## Deployment isolation and rollback

This is a dashboard-only change. It has no Apps Script Web App version and
must be deployed by the owner separately from CRM-006-ORDER.

Rollback is the inverse dashboard-file change: remove the targeted CSS rule,
copy helper, and button markup, then hard-refresh. Do not use a whole-file
rollback if it would discard unrelated current dashboard work.

## Owner QA after the separate deploy

- [ ] At 360 px, 768 px, and desktop width, the integrity card is last on
      first paint; the wrapped header/button remain readable.
- [ ] It remains last after a click, data refresh, and an unavailable
      `monthly_summary` response; the integrity tile still renders.
- [ ] For both a clean and a problem result, copy produces the full JSON
      (`problems`, `truncated`, `coverage`, `elapsed_ms`) and briefly shows
      `Скопійовано ✓`.
- [ ] Verify card and button hover/focus states; the card still responds to
      Enter and Space.
- [ ] Confirm no integrity request occurs before the card is clicked.

## Risk

Low. The only new browser-side side effect is an explicit clipboard write
after the owner clicks the new button. Runtime visual verification remains an
owner deployment check because the protected automation browser cannot open
the local dashboard file.
