(function () {
  'use strict';
  var root = document.getElementById('pay003-credit');
  if (!root || !root.dataset.stateUrl) return;
  var button = document.getElementById('pay003-refresh');
  var note = document.getElementById('pay003-network');
  var busy = false, timer = null, attempts = 0, stopped = root.dataset.poll !== '1';
  var bankAt = Date.now() + 30000;
  function localUrl(value) {
    var url = new URL(value, window.location.href);
    if (url.origin !== window.location.origin) throw new Error('origin');
    return url.href;
  }
  function schedule(delay) {
    clearTimeout(timer);
    if (!stopped && attempts < 24 && !document.hidden) timer = setTimeout(function () { check(false); }, delay);
  }
  async function check(manual) {
    if (busy || (!manual && document.hidden)) return;
    busy = true; button.disabled = true;
    var controller = new AbortController();
    // Request deadline, not an artificial UI delay; server also throttles bank GETs.
    var deadline = setTimeout(function () { controller.abort(); }, 25000);
    var refresh = !stopped && Date.now() >= bankAt;
    if (refresh) bankAt = Date.now() + 30000;
    try {
      var options = {credentials: 'same-origin', cache: 'no-store', signal: controller.signal};
      if (refresh) {
        options.method = 'POST';
        options.headers = {'Content-Type': 'application/x-www-form-urlencoded'};
        options.body = new URLSearchParams({csrf: root.dataset.csrf}).toString();
      }
      var response = await fetch(localUrl(root.dataset.stateUrl), options);
      if (response.status === 403 || response.status === 404) {
        stopped = true;
        note.textContent = 'Доступ до заявки завершився. Відкрийте замовлення зі свого акаунта або зверніться до підтримки.';
        return;
      }
      if (!response.ok) throw new Error('network');
      var data = await response.json();
      if (typeof data.title !== 'string' || typeof data.message !== 'string' || typeof data.poll !== 'boolean') throw new Error('format');
      document.getElementById('pay003-title').textContent = data.title;
      document.getElementById('pay003-message').textContent = data.message;
      stopped = !data.poll;
      note.textContent = data.offline ? 'Зв’язок із банком тимчасово недоступний. Показано останній відомий стан; повторно оформлювати заявку не потрібно.' : '';
      if (data.confirmed && data.redirect) { stopped = true; window.location.assign(localUrl(data.redirect)); }
    } catch (_) {
      note.textContent = 'Не вдалося оновити статус. Заявка не створюється повторно. Перевірте з’єднання та натисніть «Перевірити статус».';
    } finally {
      clearTimeout(deadline); busy = false; button.disabled = false; attempts++;
      if (attempts >= 24 && !stopped) note.textContent = 'Автоматичну перевірку призупинено. Можна перевірити статус кнопкою або повернутися пізніше.';
      // DB-only checks back off; bank fallback has a separate >=30s server gate.
      schedule(attempts < 6 ? 5000 : 30000);
    }
  }
  button.addEventListener('click', function () { check(true); });
  document.addEventListener('visibilitychange', function () { if (document.hidden) clearTimeout(timer); else schedule(1000); });
  window.addEventListener('pagehide', function () { clearTimeout(timer); });
  if (root.dataset.confirmed === '1') window.location.assign(localUrl(root.dataset.completeUrl));
  else schedule(5000);
}());
