/* Run in the checkout page's DevTools Console after loading has finished.
 * Read-only: outputs timings and route names, never cookies, query values,
 * address/customer fields, request bodies, response bodies, or payment codes.
 */
(() => {
  const round = n => Math.round(Number(n) || 0);
  const nav = performance.getEntriesByType('navigation')[0];
  const rows = [];
  for (const entry of performance.getEntriesByType('resource')) {
    const url = new URL(entry.name, location.href);
    const sameOrigin = url.origin === location.origin;
    const route = sameOrigin ? (url.searchParams.get('route') || '') : '';
    const safeRoute = /^(checkout\/[a-z_]+(?:\.[a-zA-Z]+)?|account\/address\.npMetadata|common\/cart\.info)$/.test(route);
    if (!safeRoute && entry.initiatorType !== 'script') continue;
    rows.push({
      resource: safeRoute ? route : sameOrigin ? url.pathname : url.hostname + '/[external-script]',
      type: entry.initiatorType,
      start_ms: round(entry.startTime),
      end_ms: round(entry.responseEnd),
      duration_ms: round(entry.duration),
      wait_ms: entry.requestStart > 0 ? round(entry.responseStart - entry.requestStart) : null,
      render_blocking: entry.renderBlockingStatus || 'unknown'
    });
  }
  const report = {
    diagnostic: 'PAY-002-checkout-timing-v1',
    unit: 'milliseconds since this page navigation started',
    navigation: nav ? {
      response_start: round(nav.responseStart),
      response_end: round(nav.responseEnd),
      dom_interactive: round(nav.domInteractive),
      dom_content_loaded_start: round(nav.domContentLoadedEventStart),
      dom_content_loaded_end: round(nav.domContentLoadedEventEnd),
      load_end: round(nav.loadEventEnd)
    } : null,
    ready_state: document.readyState,
    payment_options_visible_in_dom: document.querySelectorAll('#bs-payment-methods input[type="radio"]').length,
    rows: rows.sort((a, b) => a.start_ms - b.start_ms).slice(0, 120)
  };
  console.log(JSON.stringify(report, null, 2));
  return report;
})();
