/**
 * Booster Shop — Mobile Search + Header Menu Redesign
 * Підключити: <script src=".../patch-mobile-search-menu-redesign.js" defer></script>
 *
 * Охоплює:
 *   B — Slide-in menu: open / close / Esc / category accordions
 *   A — Mobile search: expand on tap, clear, close — no /search redirect
 *       Таргет: #ps-live-search-input (jQuery pslivesearch plugin)
 *               #ps-live-search        (ul dropdown, клас show = відкрито)
 */

(function () {
  'use strict';

  /* ================================================================
     B — SLIDE-IN MENU
     ================================================================ */
  var menu    = document.getElementById('bs-menu');
  var openBtn = document.getElementById('bs-menu-open');

  if (menu && openBtn) {
    var lastFocus = null;

    function openMenu() {
      lastFocus = document.activeElement;
      menu.hidden = false;
      requestAnimationFrame(function () { menu.classList.add('is-open'); });
      openBtn.setAttribute('aria-expanded', 'true');
      document.body.classList.add('bs-menu-lock');
      var first = menu.querySelector('a, button');
      if (first) first.focus();
    }

    function closeMenu() {
      menu.classList.remove('is-open');
      openBtn.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('bs-menu-lock');
      setTimeout(function () { menu.hidden = true; }, 300);
      if (lastFocus) lastFocus.focus();
    }

    openBtn.addEventListener('click', openMenu);

    menu.addEventListener('click', function (e) {
      if (e.target.closest('[data-bs-menu-close]')) closeMenu();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('is-open')) closeMenu();
    });

    // Category accordions
    menu.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-bs-accordion]');
      if (!btn) return;
      var cat    = btn.closest('.bs-menu__cat');
      var subs   = cat.querySelector('.bs-menu__subs');
      var isOpen = cat.classList.toggle('is-open');
      if (subs) subs.hidden = !isOpen;
    });
  }


  /* ================================================================
     A — MOBILE SEARCH EXPAND CONTROLLER
     #ps-live-search-input — jQuery pslivesearch plugin bound to it
     #ps-live-search        — <ul> dropdown, клас .show = open (plugin)
     .bs-msearch            — наш wrapper, клас .is-open = expanded
     ================================================================ */
  var wrap  = document.getElementById('bs-msearch');
  if (!wrap) return;

  var psInput  = document.getElementById('ps-live-search-input');
  var psDropdown = document.getElementById('ps-live-search');
  var clearBtn = wrap.querySelector('[data-bs-search-clear]');
  var backBtn  = wrap.querySelector('.bs-msearch__back');
  var scrim    = wrap.querySelector('[data-bs-search-close]');

  // Scrim — окремий елемент поза формою (якщо є); або використовуємо кліки на backdrop
  var bodyScrim = document.querySelector('.bs-msearch__scrim');

  var isMobile = function () { return window.innerWidth <= 768; };

  function getHeaderH() {
    var h = document.querySelector('.bs-header');
    return h ? h.getBoundingClientRect().bottom : 63;
  }

  function openSearch() {
    wrap.classList.add('is-open');
    if (backBtn) backBtn.hidden = false;
    if (bodyScrim) bodyScrim.hidden = false;
    // Фіксуємо висоту шапки для позиціонування dropdown
    document.documentElement.style.setProperty('--bs-header-h', getHeaderH() + 'px');
  }

  function closeSearch() {
    wrap.classList.remove('is-open');
    if (backBtn) backBtn.hidden = true;
    if (bodyScrim) bodyScrim.hidden = true;
    // Закриваємо ps-live-search dropdown через jQuery plugin API
    if (psInput && window.jQuery) {
      var $el = window.jQuery(psInput);
      if (typeof $el[0].closeDropdown === 'function') $el[0].closeDropdown();
      else $el.trigger('blur');
    }
    if (psInput) { psInput.value = ''; }
    if (clearBtn) clearBtn.hidden = true;
  }

  // Expand на фокус (мобілі тільки)
  if (psInput) {
    psInput.addEventListener('focus', function () {
      if (isMobile()) openSearch();
    });

    // Показуємо/ховаємо кнопку "очистити"
    psInput.addEventListener('input', function () {
      if (clearBtn) clearBtn.hidden = !psInput.value;
    });
  }

  // Кнопка "очистити"
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (psInput) { psInput.value = ''; psInput.focus(); }
      clearBtn.hidden = true;
      if (psDropdown && window.jQuery) {
        var $el = window.jQuery(psInput);
        if ($el[0] && typeof $el[0].closeDropdown === 'function') $el[0].closeDropdown();
      }
    });
  }

  // Закриття: кнопка "Назад", scrim, Esc
  wrap.querySelectorAll('[data-bs-search-close]').forEach(function (b) {
    b.addEventListener('click', closeSearch);
  });
  if (bodyScrim) {
    bodyScrim.addEventListener('click', closeSearch);
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && wrap.classList.contains('is-open')) closeSearch();
  });

  // Мобільний scrim: на desktop ps-live-search-list сам позиціонується absolute —
  // нічого не треба. На мобілі додаємо fixed через CSS (нижче).

})();
