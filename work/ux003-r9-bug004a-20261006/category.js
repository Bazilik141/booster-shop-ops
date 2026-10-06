/* UX-003-R9: one server-backed category request lifecycle. No fetched scripts execute. */
(function () {
  'use strict';
  var host = document.getElementById('product-category');
  var region = document.getElementById('bs-category-results');
  if (!host || !region) return;
  var panel = host.querySelector('#bs-ff-panel');
  var toggle = host.querySelector('#bs-ff-toggle');
  var module = panel && panel.querySelector('[data-bs-filter-action]');
  var chips = host.querySelector('[data-bs-active-filters]');
  var status = host.querySelector('[data-bs-category-status]');
  var sort = host.querySelector('.bs-ff-sort select');
  var sequence = 0, controller = null, busy = false;
  var resultCount = Number(region.dataset.total);
  var pendingPrefix = '';
  function word(n) {
    return n % 10 === 1 && n % 100 !== 11 ? 'товар' :
      n % 10 >= 2 && n % 10 <= 4 && !(n % 100 >= 12 && n % 100 <= 14) ? 'товари' : 'товарів';
  }
  function numberText(n) { return n + ' ' + word(n); }
  function say(message) { if (status) status.textContent = message; }
  function selected() { return panel ? Array.from(panel.querySelectorAll('input[name="filter[]"]:checked')) : []; }
  function resultMessage() { return resultCount ? 'Знайдено ' + numberText(resultCount) + '.' : 'Нічого не знайдено з цими фільтрами.'; }
  function panelState(open) {
    if (!panel || !toggle) return;
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
  }
  function focusGrid() {
    var target = region.querySelector('#product-list') || region;
    target.setAttribute('tabindex', '-1'); target.setAttribute('aria-label', 'Товари');
    target.focus({ preventScroll: true });
    window.scrollTo({ top: Math.max(0, target.getBoundingClientRect().top + window.scrollY - 12), behavior: 'auto' });
  }
  function countsAndChips(focusId) {
    if (!panel || !chips || !toggle) return;
    var values = selected();
    panel.querySelectorAll('[data-bs-ff-group]').forEach(function (group) {
      var badge = group.querySelector('.bs-ff-count');
      var n = group.querySelectorAll('input[name="filter[]"]:checked').length;
      if (badge) { badge.textContent = n; badge.hidden = !n; badge.setAttribute('aria-label', 'вибрано ' + n); }
    });
    var badge = toggle.querySelector('.bs-ff-count');
    if (badge) { badge.textContent = values.length; badge.hidden = !values.length; badge.setAttribute('aria-hidden', 'true'); }
    chips.replaceChildren(); chips.hidden = !values.length;
    if (values.length) {
      var label = document.createElement('span'); label.textContent = 'Фільтр:'; chips.appendChild(label);
      values.forEach(function (input) {
        var name = input.parentElement.querySelector('span').textContent.trim();
        var button = document.createElement('button'); button.type = 'button'; button.className = 'bs-r9-chip';
        button.dataset.removeFilter = input.value; button.setAttribute('aria-label', 'Прибрати фільтр «' + name + '»');
        button.textContent = name + ' ×'; chips.appendChild(button);
      });
      if (values.length >= 2) {
        var reset = document.createElement('button'); reset.type = 'button'; reset.dataset.bsR9Reset = '';
        reset.className = 'bs-r9-reset'; reset.textContent = 'Скинути все'; chips.appendChild(reset);
      }
    }
    if (focusId !== undefined) {
      var candidate = Array.from(chips.querySelectorAll('[data-remove-filter]')).find(function (b) { return b.dataset.removeFilter === focusId; });
      (candidate || toggle).focus();
    }
  }
  function renderResultsState() {
    host.querySelectorAll('[data-bs-r9-found]').forEach(function (el) {
      el.textContent = busy ? 'Оновлюємо…' : resultCount ? 'Знайдено ' + numberText(resultCount) : 'Нічого не знайдено';
    });
    var show = host.querySelector('[data-bs-r9-show]');
    var empty = host.querySelector('[data-bs-r9-zero]');
    if (show) {
      show.hidden = !busy && !resultCount;
      show.textContent = busy ? 'Оновлюємо…' : 'Показати ' + numberText(resultCount);
      show.disabled = busy; show.setAttribute('aria-disabled', String(busy));
    }
    if (empty) empty.hidden = busy || !!resultCount;
    var wrap = region.querySelector('#bs-load-more-wrap');
    if (wrap) {
      var next = document.querySelector('link[rel="next"]');
      wrap.style.display = next ? '' : 'none';
      var shown = region.querySelectorAll('#product-list > .col').length;
      var shownEl = wrap.querySelector('.bs-lm-shown');
      var totalEl = wrap.querySelector('.bs-lm-total');
      var fill = wrap.querySelector('.bs-load-more-progress__fill');
      var count = wrap.querySelector('#bs-load-more-count');
      if (shownEl) shownEl.textContent = shown;
      if (totalEl) totalEl.textContent = resultCount;
      if (fill) fill.style.width = (resultCount ? Math.min(100, shown / resultCount * 100) : 0) + '%';
      if (count) count.textContent = '+' + Math.min(Number(region.dataset.pageSize) || shown, Math.max(0, resultCount - shown));
      var button = wrap.querySelector('#bs-load-more-btn');
      if (button) { button.disabled = busy; button.classList.toggle('loading', busy); }
    }
  }
  function loading(on) {
    busy = on; region.classList.toggle('is-loading', on); region.setAttribute('aria-busy', String(on));
    region.style.minHeight = on ? region.getBoundingClientRect().height + 'px' : '';
    var grid = region.querySelector('#product-list'); if (grid) grid.setAttribute('aria-busy', String(on));
    renderResultsState();
  }
  function invalidate() {
    sequence++; if (controller) controller.abort(); controller = null;
  }
  function sameCategory(url) {
    var parsed = new URL(url, location.href);
    if (parsed.origin !== location.origin) throw new Error('Cross-origin response');
    return parsed;
  }
  function syncHead(doc, append) {
    // Load-more advances the existing next-page cursor; its address and prev/robots stay on the current page.
    (append ? ['link[rel="next"]'] : ['meta[name="robots"]', 'link[rel="prev"]', 'link[rel="next"]']).forEach(function (selector) {
      document.head.querySelectorAll(selector).forEach(function (node) { node.remove(); });
      doc.head.querySelectorAll(selector).forEach(function (node) { document.head.appendChild(document.importNode(node, true)); });
    });
  }
  function syncControls(doc) {
    var nextModule = doc.querySelector('[data-bs-filter-action]');
    if (module && nextModule) module.dataset.bsFilterAction = nextModule.dataset.bsFilterAction;
    if (panel) {
      var checkedValues = new Set(Array.from(doc.querySelectorAll('#bs-ff-panel input[name="filter[]"]:checked')).map(function (i) { return i.value; }));
      panel.querySelectorAll('input[name="filter[]"]').forEach(function (input) { input.checked = checkedValues.has(input.value); });
    }
    var nextSort = doc.querySelector('.bs-ff-sort select');
    if (sort && nextSort) {
      // Keep the live select, IDs, names and labels; server links carry the current filter.
      if (sort.options.length !== nextSort.options.length) throw new Error('Sort option mismatch');
      Array.from(sort.options).forEach(function (option, index) {
        option.value = nextSort.options[index].value; option.selected = nextSort.options[index].selected;
      });
    }
    countsAndChips();
  }
  function canonical(doc) { var node = doc.querySelector('link[rel="canonical"]'); return node ? node.href : ''; }
  async function navigate(url, options) {
    options = options || {};
    if (!window.fetch || !window.AbortController) { window.location.assign(url); return; }
    var prefix = pendingPrefix; pendingPrefix = '';
    invalidate(); var ticket = sequence; controller = new AbortController();
    loading(true);
    try {
      url = sameCategory(url).href;
      var response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
      if (response.status !== 200) throw new Error('HTTP ' + response.status);
      // A redirect to another page must use normal navigation, never a partial category swap.
      if (new URL(response.url).href !== url) throw new Error('Redirected category');
      var doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (ticket !== sequence) return;
      var next = doc.querySelector('#bs-category-results');
      if (!next || canonical(doc) !== canonical(document) || next.dataset.category !== region.dataset.category) throw new Error('Category response mismatch');
      var total = Number(next.dataset.total);
      if (!Number.isSafeInteger(total) || total < 0 || (total > 0 && !next.querySelector('#product-list'))) throw new Error('Missing results');
      if (!!module !== !!doc.querySelector('[data-bs-filter-action]')) throw new Error('Filter module mismatch');
      // Preserve scripts as inert data; no script from a fetch response is executed.
      next.querySelectorAll('script').forEach(function (node) { node.remove(); });
      if (options.append) {
        var grid = region.querySelector('#product-list');
        var cards = next.querySelectorAll('#product-list > .col');
        if (!grid || !cards.length || total !== resultCount) throw new Error('Invalid next page');
        cards.forEach(function (card) { grid.appendChild(document.importNode(card, true)); });
        // Appending leaves the address/current selected page unchanged, as before runner 9.
        syncHead(doc, true); renderResultsState(); say('Додано ' + numberText(cards.length) + '.');
      } else {
        var active = document.activeElement;
        var preserve = active && (panel && panel.contains(active) || active === toggle || active === sort || chips && chips.contains(active));
        var focusFilter = active && chips && chips.contains(active) ? active.dataset.removeFilter : undefined;
        syncControls(doc);
        region.replaceChildren.apply(region, Array.from(next.childNodes).map(function (node) { return document.importNode(node, true); }));
        region.dataset.total = next.dataset.total; region.dataset.pageSize = next.dataset.pageSize;
        resultCount = total; syncHead(doc);
        var headerCount = host.querySelector('.bs-cat-header__title .bs-count');
        if (headerCount) headerCount.textContent = numberText(resultCount);
        if (!options.pop) history.pushState(null, '', url);
        if (focusFilter !== undefined) countsAndChips(focusFilter);
        if (options.focusGrid) focusGrid();
        else if (!preserve && active && !document.contains(active)) (toggle || region).focus();
        say(prefix + (options.sortLabel ? 'Відсортовано: ' + options.sortLabel + '.' : resultMessage()));
      }
    } catch (error) {
      if (ticket !== sequence || error.name === 'AbortError') return;
      window.location.assign(url);
    } finally {
      if (ticket === sequence) { controller = null; loading(false); }
    }
  }
  window.bsCategoryNavigate = navigate;
  function filterUrl() {
    var url = new URL(module.dataset.bsFilterAction, location.href);
    var values = selected().map(function (input) { return input.value; });
    if (values.length) url.searchParams.set('filter', values.join(',')); else url.searchParams.delete('filter');
    url.searchParams.delete('page'); return url.href;
  }
  function reset() {
    if (!module) return;
    module.dispatchEvent(new Event('bs:filter-cancel'));
    panel.querySelectorAll('input[name="filter[]"]').forEach(function (input) { input.checked = false; });
    pendingPrefix = 'Фільтри скинуто. '; countsAndChips(); toggle.focus(); navigate(filterUrl());
  }
  if (panel && toggle) {
    panel.querySelectorAll('[data-bs-ff-group]').forEach(function (group, index) {
      var button = group.querySelector('.bs-ff-gt'); var body = group.querySelector('.bs-ff-checks');
      if (!button || !body) return;
      body.hidden = matchMedia('(max-width:575.98px)').matches && index > 0;
      button.setAttribute('aria-expanded', String(!body.hidden));
      button.addEventListener('click', function () { body.hidden = !body.hidden; button.setAttribute('aria-expanded', String(!body.hidden)); });
    });
    toggle.addEventListener('click', function () { panelState(panel.hidden); });
    panel.addEventListener('change', function (event) {
      if (!event.target.matches('input[name="filter[]"]')) return;
      // Abort immediately, before the module's existing 250 ms debounce expires.
      invalidate(); pendingPrefix = ''; countsAndChips(); loading(true);
    });
  }
  host.addEventListener('change', function (event) {
    if (event.target !== sort) return;
    event.stopImmediatePropagation();
    if (module) {
      // If a checkbox debounce is pending, include that selection in the sort link too.
      var url = new URL(sort.value, location.href), values = selected().map(function (i) { return i.value; });
      if (values.length) url.searchParams.set('filter', values.join(',')); else url.searchParams.delete('filter');
      var cancelEvent = new Event('bs:filter-cancel'); module.dispatchEvent(cancelEvent);
      navigate(url.href, { sortLabel: sort.options[sort.selectedIndex].text });
    } else navigate(sort.value, { sortLabel: sort.options[sort.selectedIndex].text });
  }, true);
  host.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (button && button.dataset.removeFilter !== undefined) {
      var values = selected(), index = values.findIndex(function (i) { return i.value === button.dataset.removeFilter; });
      var input = values[index]; if (!input) return;
      var name = input.parentElement.querySelector('span').textContent.trim(); input.checked = false;
      var next = values[index + 1] || values[index - 1]; countsAndChips(next ? next.value : '');
      pendingPrefix = 'Фільтр «' + name + '» прибрано. ';
      if (module) module.dispatchEvent(new Event('bs:filter-cancel'));
      navigate(filterUrl()); return;
    }
    if (button && button.matches('[data-bs-r9-reset]')) { reset(); return; }
    if (button && button.matches('[data-bs-ff-close]')) { panelState(false); toggle.focus(); return; }
    if (button && button.matches('[data-bs-r9-show]') && !busy) { panelState(false); focusGrid(); return; }
    if (button && button.id === 'bs-load-more-btn') {
      var next = document.querySelector('link[rel="next"]');
      if (next && !busy) navigate(next.href, { append: true }); return;
    }
    var link = event.target.closest('[data-bs-r9-pagination] a');
    if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
      event.preventDefault(); navigate(link.href, { focusGrid: true });
    }
  });
  window.addEventListener('popstate', function () {
    if (module) module.dispatchEvent(new Event('bs:filter-cancel'));
    pendingPrefix = ''; navigate(location.href, { pop: true });
  });
  countsAndChips(); renderResultsState();
  var headerCount = host.querySelector('.bs-cat-header__title .bs-count');
  if (headerCount) headerCount.textContent = numberText(resultCount);
})();
