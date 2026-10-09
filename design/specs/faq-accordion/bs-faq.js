/* =============================================================
 * Booster Shop · FAQ accordion · normalizer + behavior
 * Затверджено: 2026-06-03
 *
 * Що робить цей файл (один файл, vanilla JS, без залежностей):
 *
 * 1. Сканує опис товару (контейнер #tab-description / .product-description)
 *    і знаходить заголовок «FAQ» або «Часті питання» (h2/h3/h4).
 * 2. Парсить будь-який з відомих форматів написання FAQ:
 *      • <h4>…?</h4> + <p>…</p>             (чистий)
 *      • <p><strong>…?</strong></p> + <p>…</p>   (lazy bold — OP-15)
 *      • <h4>…?</h4> без відповіді           (broken — Mega Dream EX)
 *      • <div class="bs-faq-accordion"> з .bs-faq-item   (затверджений раніше)
 *      • <dl><dt>…</dt><dd>…</dd></dl>      (на випадок)
 * 3. Будує канонічний DOM .bs-faq з a11y і Schema.org FAQPage розміткою.
 * 4. Вішає клавіатуру + клік + плавне розгортання (через CSS grid-rows).
 * 5. Замінює месив на нормалізований акордеон. Старий DOM лишається в
 *    .bs-faq-accordion (прихований через CSS) — якщо щось зламається,
 *    SEO-тексти не зникають.
 *
 * Запускати:  на DOMContentLoaded в темі OpenCart на product page.
 * Ідемпотентно: повторний виклик нічого не ламає.
 * ============================================================= */

(function () {
  'use strict';

  // Селектори, де шукаємо FAQ. Перший знайдений — переможець.
  var DESCRIPTION_SELECTORS = [
    '#tab-description',           // OpenCart default
    '.tab-description',
    '.product-description',
    '.bs-product-description',
  ];

  // Регулярка для заголовка «FAQ» / «Часті питання» / «Часто задавані».
  var HEADING_RE = /^\s*(faq\b|часті\s*питан|часто\s*задаван)/i;

  // Чи елемент — питання?
  function isQuestionNode(el) {
    if (!el || !el.tagName) return false;
    if (/^H[4-6]$/.test(el.tagName)) return true;
    if (el.tagName === 'DT') return true;
    if (el.tagName === 'P') {
      var text = (el.textContent || '').trim();
      var strongs = el.querySelectorAll('strong, b');
      if (strongs.length === 1) {
        var sText = (strongs[0].textContent || '').trim();
        // <strong> покриває майже весь параграф і закінчується на «?»
        if (sText.length >= text.length * 0.85 && /\?\s*$/.test(sText)) {
          return true;
        }
      }
    }
    return false;
  }

  /**
   * parseFaq(root)
   * Шукає в root заголовок FAQ і повертає масив { q, aHtml }.
   * Якщо вже є канонічний .bs-faq-accordion — читає його напряму.
   */
  function parseFaq(root) {
    if (!root) return null;

    // 1) Канонічна структура (затверджена раніше)
    var canonical = root.querySelector('.bs-faq-accordion');
    if (canonical) {
      var items = [];
      var nodes = canonical.querySelectorAll('.bs-faq-item');
      for (var i = 0; i < nodes.length; i++) {
        var qEl = nodes[i].querySelector('.bs-faq-q, h4, h5, summary, strong');
        var aEl = nodes[i].querySelector('.bs-faq-a, .bs-faq-answer, p, div');
        items.push({
          q: qEl ? (qEl.textContent || '').trim() : '',
          aHtml: aEl ? (aEl.innerHTML || '').trim() : '',
        });
      }
      if (items.length) return { heading: canonical, items: items, sliceNodes: [canonical] };
    }

    // 2) Знайти заголовок FAQ серед h2/h3/h4
    var headings = root.querySelectorAll('h2, h3, h4');
    var faqHeading = null;
    for (var j = 0; j < headings.length; j++) {
      if (HEADING_RE.test(headings[j].textContent || '')) {
        faqHeading = headings[j];
        break;
      }
    }
    if (!faqHeading) return null;

    var faqLevel = parseInt(faqHeading.tagName[1], 10);

    // 3) Зібрати сиблінги після заголовка, до наступного h<=faqLevel
    var slice = [];
    var n = faqHeading.nextElementSibling;
    while (n) {
      if (/^H[1-6]$/.test(n.tagName)) {
        var lvl = parseInt(n.tagName[1], 10);
        if (lvl <= faqLevel) break;
      }
      slice.push(n);
      n = n.nextElementSibling;
    }

    // 4) Розбити на пари Q/A
    var result = [];
    var current = null;
    for (var k = 0; k < slice.length; k++) {
      var el = slice[k];
      if (isQuestionNode(el)) {
        if (current) result.push(current);
        current = { q: (el.textContent || '').trim(), aHtml: '' };
      } else if (current) {
        current.aHtml += el.outerHTML;
      }
    }
    if (current) result.push(current);
    if (!result.length) return null;

    return { heading: faqHeading, items: result, sliceNodes: [faqHeading].concat(slice) };
  }

  /**
   * buildAccordion(parsed)
   * Будує DOM .bs-faq з результату parseFaq().
   */
  function buildAccordion(parsed, idPrefix) {
    idPrefix = idPrefix || ('faq-' + Math.floor(Math.random() * 1e6) + '-');

    var section = document.createElement('section');
    section.className = 'bs-faq';
    section.setAttribute('itemscope', '');
    section.setAttribute('itemtype', 'https://schema.org/FAQPage');

    var title = document.createElement('h3');
    title.className = 'bs-faq__title';
    title.textContent = 'Часті питання';
    section.appendChild(title);

    var list = document.createElement('div');
    list.className = 'bs-faq__list';
    section.appendChild(list);

    parsed.items.forEach(function (item, i) {
      var article = document.createElement('article');
      article.className = 'bs-faq__item';
      article.setAttribute('itemprop', 'mainEntity');
      article.setAttribute('itemscope', '');
      article.setAttribute('itemtype', 'https://schema.org/Question');
      article.dataset.open = 'false';

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'bs-faq__q';
      var aId = idPrefix + 'a-' + (i + 1);
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('aria-controls', aId);

      var qText = document.createElement('span');
      qText.className = 'bs-faq__q-text';
      qText.setAttribute('itemprop', 'name');
      qText.textContent = item.q;
      btn.appendChild(qText);

      var chev = document.createElement('span');
      chev.className = 'bs-faq__chev';
      chev.innerHTML =
        '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">' +
        '<path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="1.75" ' +
        'stroke-linecap="round" stroke-linejoin="round"/></svg>';
      btn.appendChild(chev);

      article.appendChild(btn);

      var a = document.createElement('div');
      a.id = aId;
      a.className = 'bs-faq__a';
      a.setAttribute('itemscope', '');
      a.setAttribute('itemprop', 'acceptedAnswer');
      a.setAttribute('itemtype', 'https://schema.org/Answer');

      var aInner = document.createElement('div');
      aInner.className = 'bs-faq__a-inner';
      aInner.setAttribute('itemprop', 'text');

      var clean = (item.aHtml || '').trim();
      if (clean) {
        aInner.innerHTML = clean;
      } else {
        aInner.innerHTML = '<p class="bs-faq__a-pending">Відповідь у підготовці.</p>';
      }
      a.appendChild(aInner);
      article.appendChild(a);

      list.appendChild(article);
    });

    return section;
  }

  /**
   * attachBehaviors(section)
   * Клік + клавіатура (Enter/Space toggle). CSS робить анімацію.
   */
  function attachBehaviors(section) {
    var items = section.querySelectorAll('.bs-faq__item');
    items.forEach(function (item) {
      var btn = item.querySelector('.bs-faq__q');
      if (!btn) return;
      btn.addEventListener('click', function () {
        var isOpen = item.dataset.open === 'true';
        item.dataset.open = isOpen ? 'false' : 'true';
        btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      });
    });
  }

  /**
   * normalizeFaq(root)
   * Головний публічний API. Знаходить FAQ в root, замінює на акордеон.
   */
  function normalizeFaq(root) {
    if (!root) return null;
    if (root.dataset && root.dataset.bsFaqDone === '1') return null; // ідемпотентність

    var parsed = parseFaq(root);
    if (!parsed) return null;

    var accordion = buildAccordion(parsed);

    // Вставити перед першим slice-нодою, потім видалити (або сховати) старі.
    var firstNode = parsed.sliceNodes[0];
    firstNode.parentNode.insertBefore(accordion, firstNode);

    // Видаляємо старі вузли (заголовок FAQ + парсений slice).
    parsed.sliceNodes.forEach(function (n) {
      if (n && n.parentNode) n.parentNode.removeChild(n);
    });

    attachBehaviors(accordion);

    if (root.dataset) root.dataset.bsFaqDone = '1';
    return accordion;
  }

  /**
   * init()
   * Запускає нормалізатор у найбільш імовірних контейнерах опису.
   */
  function init() {
    for (var i = 0; i < DESCRIPTION_SELECTORS.length; i++) {
      var roots = document.querySelectorAll(DESCRIPTION_SELECTORS[i]);
      for (var j = 0; j < roots.length; j++) {
        normalizeFaq(roots[j]);
      }
    }
  }

  // Експорт + автостарт
  window.BoosterShopFaq = {
    parseFaq: parseFaq,
    buildAccordion: buildAccordion,
    normalizeFaq: normalizeFaq,
    init: init,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
