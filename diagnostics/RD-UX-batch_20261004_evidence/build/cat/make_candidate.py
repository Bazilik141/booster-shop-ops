# Builds the runner-7 candidate category.twig from the runner-6 output by anchored, count-checked edits.
# python make_candidate.py <runner6 category.twig> <out category.twig>
import sys

src, out = sys.argv[1], sys.argv[2]
t = open(src, encoding='utf-8', newline='').read()
assert '\r' not in t


def rep(old, new, label, count=1):
    global t
    n = t.count(old)
    if n != count:
        raise SystemExit(f'anchor {label}: found {n}, expected {count}')
    t = t.replace(old, new)


def cut(start, end, label, keep_end=False):
    """Remove from start (inclusive) to end (inclusive unless keep_end)."""
    global t
    if t.count(start) != 1 or t.count(end) != 1:
        raise SystemExit(f'cut {label}: start {t.count(start)}, end {t.count(end)}')
    a = t.index(start)
    b = t.index(end)
    if b < a:
        raise SystemExit(f'cut {label}: order')
    if not keep_end:
        b += len(end)
    t = t[:a] + t[b:]


SVG_SLIDERS = '<svg width="17" height="17" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M2 4h7M12 4h2M2 12h2M7 12h7M9 2.5v3M5 10.5v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>'
SVG_CHEV = '<svg class="{cls}" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
SVG_SORT = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 4v16m0 0-3-3m3 3 3-3M17 20V4m0 0-3 3m3-3 3 3"/></svg>'

# ---- 1. toolbar block (desktop toolbar + mobile subcategory grid, or the sort-only toolbar) → one row + panel
toolbar_start = "        {% if sub_categories|length %}\n          {# Desktop: integrated toolbar"
toolbar_end = "        {% endif %}\n\n        {% if active_filters|length %}\n"
new_row = """        {# UX-003-FILTER (2026-10-04): one row — subcategories | «Фільтр» | sort — and the filter panel under it
           (design «етап 3», «Фільтр категорії», variant C). Replaces the desktop toolbar, the mobile subcategory grid
           and the mobile action row. The filter module (column_right) renders inside the panel instead of the side
           column; its aside wrapper is swapped because stylesheet.css hides #column-right below 992px. #}
        <div class="bs-ff-row">
          {% if sub_categories|length %}
            <nav class="bs-ff-chips" aria-label="Підкатегорії">
              {% for sub in sub_categories %}
                <a href="{{ sub.href }}" class="bs-ff-chip{% if sub.active %} is-active{% endif %}"{% if sub.active %} aria-current="page"{% endif %}>{{ sub.name }}{% if sub.product_count %}<span class="bs-ff-chip__count">{{ sub.product_count }}</span>{% endif %}</a>
              {% endfor %}
            </nav>
          {% else %}
            <div class="bs-ff-chips"></div>
          {% endif %}
          <div class="bs-ff-tools">
            {% if column_right %}
              <button type="button" class="bs-ff-btn" id="bs-ff-toggle" aria-expanded="false" aria-controls="bs-ff-panel">
                SLIDERS
                <span class="bs-ff-btn__label">Фільтр</span>
                <span class="bs-ff-count" hidden>0</span>
                CHEVBTN
              </button>
            {% endif %}
            <div class="bs-ff-sort">
              <span class="bs-ff-sort__ic" aria-hidden="true">SORTIC</span>
              <select class="bs-select" aria-label="{{ text_sort }}" onchange="location = this.value;">
                {% for sort_item in sorts %}
                  <option value="{{ sort_item.href }}"{% if sort_item.value == current_sort %} selected{% endif %}>{{ sort_item.text }}</option>
                {% endfor %}
              </select>
            </div>
          </div>
        </div>
        {% if column_right %}
          <div class="bs-ff-panel" id="bs-ff-panel" hidden>
            {{ column_right|replace({'<aside id="column-right" class="col-3 d-none d-md-block">': '<div class="bs-ff-modules">', '</aside>': '</div>'}) }}
            <div class="bs-ff-foot">
              <button type="button" class="bs-ff-reset" data-bs-ff-reset hidden>Скинути</button>
              <button type="button" class="bs-ff-close" data-bs-ff-close>Згорнути CHEVUP</button>
            </div>
          </div>
        {% endif %}

        {% if active_filters|length %}
"""
new_row = (new_row.replace('SLIDERS', SVG_SLIDERS).replace('CHEVBTN', SVG_CHEV.format(cls='bs-ff-btn__chev'))
           .replace('SORTIC', SVG_SORT).replace('CHEVUP', SVG_CHEV.format(cls='bs-ff-close__chev')))
if t.count(toolbar_start) != 1 or t.count(toolbar_end) != 1:
    raise SystemExit('anchor toolbar')
_a = t.index(toolbar_start)
_b = t.index(toolbar_end) + len(toolbar_end)
t = t[:_a] + new_row + t[_b:]

# ---- 2. mobile action row (its «Фільтр» opened the #column-right drawer)
cut("      {# Mobile action row: filter + sort, balanced grid. Hidden on lg+. #}\n",
    "        </select>\n      </div>\n\n      {% if products %}\n", 'action_row', keep_end=True)
rep("        </select>\n      </div>\n\n      {% if products %}\n", "      {% if products %}\n", 'action_row_tail')

# ---- 3. side column
rep("      {{ content_bottom }}\n    </div>\n\n    {{ column_right }}\n  </div>\n</div>\n",
    "      {{ content_bottom }}\n    </div>\n  </div>\n</div>\n", 'column_right_side')

# ---- 4. inline CSS the new row replaces
cut("  /* Desktop integrated toolbar */\n",
    "  .bs-toolbar-sort .bs-select {\n    height: 36px; padding-block: 0; width: auto; min-width: 200px;\n    font-size: 13px; font-weight: 600; color: var(--bs-ink-2);\n  }\n\n", 'css_toolbar')
cut("  /* Desktop V1 · Segmented control */\n",
    "  .bs-cat-header--onepiece .bs-segmented__opt.is-active .bs-segmented__count {\n    color: var(--bs-onepiece);\n    background: var(--bs-blue-soft);\n  }\n\n", 'css_segmented')
cut("  /* UI-FIX-20260903 T9 (Component C · direction C3) — subcategories are the\n",
    "     subcategory; the grid above needs no count at all. */\n\n", 'css_subcat_tabs')
cut("  /* Mobile action row: filter + sort balanced grid */\n",
    "    background-repeat: no-repeat; background-position: right 12px center;\n  }\n\n", 'css_action_row')
rep("  /* Breakpoint switching */\n  @media (min-width: 992px) {\n    .bs-subcat-tabs { display: none; }\n  }\n"
    "  @media (max-width: 991.98px) {\n    .bs-cat-header__toolbar { display: none; }\n    .bs-cat-header__filters { padding: 10px 14px; }\n  }\n",
    "  @media (max-width: 991.98px) {\n    .bs-cat-header__filters { padding: 10px 14px; }\n  }\n", 'css_breakpoints')
rep("    .bs-cat-header__toolbar {\n      display: none !important;\n    }\n\n    .bs-cat-header__hero {\n      padding: 18px 16px 14px;\n    }\n",
    "    .bs-cat-header__hero {\n      padding: 18px 16px 14px;\n    }\n", 'css_r03_toolbar')
cut("    .bs-subcat-tabs {\n      padding: 0 8px;\n      overflow: visible;\n    }\n",
    "    .bs-action-row__sort {\n      width: 100%;\n      min-width: 0;\n    }\n  }\n", 'css_r03_rest', keep_end=True)
rep("    .bs-action-row__sort {\n      width: 100%;\n      min-width: 0;\n    }\n  }\n\n  @media (max-width: 380px) {\n", "  }\n\n  @media (max-width: 380px) {\n", 'css_r03_tail')
cut("  @media (max-width: 380px) {\n", "  /* R-03 mobile subcategory heading cleanup */\n", 'css_380', keep_end=True)
rep("\n    .bs-subcat-tabs {\n      border-top: 0;\n      padding-top: 0;\n    }\n  }\n", "\n  }\n", 'css_640_tabs')

# ---- 5. review N3: the inline :root redefined --bs-sh-sm for the whole page (sticky header shadow)
rep("    --bs-r-pill: 999px;\n    --bs-sh-sm: 0 1px 0 rgba(17,24,39,0.03);\n  }\n", "    --bs-r-pill: 999px;\n  }\n", 'n3_root')
rep("    box-shadow: var(--bs-sh-sm);\n    transition: background .18s ease, border-color .18s ease, color .18s ease, transform .1s ease;\n",
    "    box-shadow: 0 1px 0 rgba(17,24,39,0.03); /* UX-003-FILTER (review N3): was var(--bs-sh-sm), which the :root above redefined page-wide */\n"
    "    transition: background .18s ease, border-color .18s ease, color .18s ease, transform .1s ease;\n", 'n3_load_more')

# ---- 6. JS: the drawer toggle goes with its button; the rest of that script is untouched
rep("  var toggle = document.getElementById('mobileFilterToggle');\n  var sidebar = document.getElementById('column-right');\n  var content = document.getElementById('content');\n",
    "  var content = document.getElementById('content');\n", 'js_vars')
cut("  if (toggle && sidebar) {\n", "  function setMobileView(mode) {\n", 'js_toggle', keep_end=True)

panel_js = """<script>
/* UX-003-FILTER (2026-10-04): filter panel — open/close, per-group collapse, selection counters, «Скинути».
   Applying a filter stays with the filter module's own script (a checkbox change applies it after 250 ms). */
(function () {
  var toggle = document.getElementById('bs-ff-toggle');
  var panel = document.getElementById('bs-ff-panel');
  if (!toggle || !panel) return;

  var closeButton = panel.querySelector('[data-bs-ff-close]');
  var resetButton = panel.querySelector('[data-bs-ff-reset]');
  var groups = panel.querySelectorAll('[data-bs-ff-group]');
  var narrow = window.matchMedia ? window.matchMedia('(max-width: 575.98px)').matches : false;

  function setPanel(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function setGroup(button, body, open) {
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    body.hidden = !open;
  }

  function checked(scope) {
    return scope.querySelectorAll('input[name="filter[]"]:checked');
  }

  function syncCounts() {
    Array.prototype.forEach.call(groups, function (group) {
      var badge = group.querySelector('.bs-ff-gt .bs-ff-count');
      var n = checked(group).length;
      if (badge) {
        badge.textContent = String(n);
        badge.hidden = n === 0;
      }
    });
    var total = checked(panel).length;
    var mainBadge = toggle.querySelector('.bs-ff-count');
    if (mainBadge) {
      mainBadge.textContent = String(total);
      mainBadge.hidden = total === 0;
    }
    if (resetButton) resetButton.hidden = total === 0;
  }

  Array.prototype.forEach.call(groups, function (group, index) {
    var button = group.querySelector('.bs-ff-gt');
    var body = group.querySelector('.bs-ff-checks');
    if (!button || !body) return;
    setGroup(button, body, !narrow || index === 0);
    button.addEventListener('click', function () {
      setGroup(button, body, body.hidden);
    });
  });

  toggle.addEventListener('click', function () {
    setPanel(panel.hidden);
  });

  if (closeButton) {
    closeButton.addEventListener('click', function () {
      setPanel(false);
      toggle.focus();
    });
  }

  if (resetButton) {
    resetButton.addEventListener('click', function () {
      Array.prototype.forEach.call(checked(panel), function (input) {
        input.checked = false;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  }

  panel.addEventListener('change', syncCounts);
  syncCounts();
})();
</script>
"""
rep("</script>\n<script type=\"application/ld+json\">\n", "</script>\n" + panel_js + "<script type=\"application/ld+json\">\n", 'panel_js')

# ---- final checks
for gone in ('mobileFilterToggle', 'bs-action-row', 'bs-segmented', 'bs-subcat-tab', 'bs-cat-header__toolbar', 'bs-toolbar-',
             "getElementById('column-right')", '--bs-sh-sm:'):
    if gone in t:
        raise SystemExit('still present: ' + gone)
open(out, 'w', encoding='utf-8', newline='').write(t)
print('candidate written', len(t))
