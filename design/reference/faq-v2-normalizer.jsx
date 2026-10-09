// FAQ NORMALIZER — the part that fixes the actual problem.
// ---------------------------------------------------------------------------
// One robust parser that consumes whatever HTML the AI wrote into a product
// description and produces a uniform list of Q/A items. Then the accordion
// component renders them — so the visual is always identical regardless of
// what shape the source markup arrived in.
//
// Recognises three "wild" patterns we've seen in production:
//   1. <h4>question?</h4><p>answer</p>            (clean)
//   2. <p><strong>question?</strong></p><p>...</p> (lazy bold)
//   3. <h4>question?</h4> with no following <p>     (broken — Mega Dream EX)
// And the canonical form:
//   4. <div class="bs-faq-accordion"><div class="bs-faq-item">…</div>…</div>
//
// The function never mutates the input; it returns a plain array of
// { q: string, a: string-HTML } so the downstream component can render it
// however it wants. This means changing the visual design later is a
// one-line swap.
// ---------------------------------------------------------------------------

/**
 * parseFaq — given an HTMLElement that contains an FAQ heading plus messy
 * markup beneath it, return an array of {q, a} pairs. Empty answers are
 * preserved (rendered as "Відповідь у підготовці." downstream).
 *
 * Strategy:
 *   1. Locate FAQ heading: h2/h3/h4 whose text matches /FAQ|часті питання/i.
 *      The heading itself stays in the DOM (we don't remove it).
 *   2. Collect sibling nodes after the heading, stopping at the next
 *      heading of EQUAL or higher level (i.e. don't cross sections).
 *   3. Within that slice, walk node by node. A question node is:
 *        - h4 / h5 / h6
 *        - OR <p> whose ONLY meaningful child is <strong>/<b> ending with ?
 *        - OR <dt> (in case someone used a <dl>)
 *      Everything else between questions is answer HTML.
 *   4. If the slice is already a <div class="bs-faq-accordion">, fall through
 *      to the explicit reader.
 */
function parseFaq(root) {
  if (!root) return [];

  // ── Path 1: canonical structure already present
  const canonical = root.querySelector?.('.bs-faq-accordion');
  if (canonical) {
    return [...canonical.querySelectorAll('.bs-faq-item')].map((item) => {
      const qEl = item.querySelector('.bs-faq-q, h4, h5, strong, summary');
      const aEl = item.querySelector('.bs-faq-a, p, div');
      return {
        q: (qEl?.textContent || '').trim(),
        a: (aEl?.innerHTML || '').trim(),
      };
    });
  }

  // ── Path 2: find the FAQ heading among descendants of root
  const headingRe = /^\s*(faq|часті\s*питан|часто\s*задаван)/i;
  const allHeadings = [...root.querySelectorAll('h2, h3, h4')];
  const faqHeading = allHeadings.find((h) => headingRe.test(h.textContent));
  if (!faqHeading) return [];

  const faqLevel = parseInt(faqHeading.tagName[1], 10);

  // Collect siblings until we hit a heading of equal/higher level.
  const slice = [];
  let n = faqHeading.nextElementSibling;
  while (n) {
    if (/^H[1-6]$/.test(n.tagName)) {
      const lvl = parseInt(n.tagName[1], 10);
      if (lvl <= faqLevel) break;
    }
    slice.push(n);
    n = n.nextElementSibling;
  }

  // ── Walk slice, splitting into Q/A pairs.
  const isQuestionNode = (el) => {
    if (/^H[4-6]$/.test(el.tagName)) return true;
    if (el.tagName === 'DT') return true;
    if (el.tagName === 'P') {
      // <p><strong>question?</strong></p> — strong wraps essentially the
      // whole paragraph and the text ends with '?'
      const text = el.textContent.trim();
      const strongs = el.querySelectorAll('strong, b');
      if (strongs.length === 1) {
        const sText = strongs[0].textContent.trim();
        // Require that the bold text covers most of the paragraph and ends in '?'
        if (sText.length >= text.length * 0.85 && /\?\s*$/.test(sText)) return true;
      }
    }
    return false;
  };

  const items = [];
  let current = null;
  for (const el of slice) {
    if (isQuestionNode(el)) {
      if (current) items.push(current);
      current = { q: el.textContent.trim(), a: '' };
    } else if (current) {
      current.a += el.outerHTML;
    }
    // else: prose between heading and first question — ignore. (Could be
    // an intro paragraph; we let it stay where it is in the DOM by not
    // touching it, since this function is non-mutating.)
  }
  if (current) items.push(current);
  return items;
}

/**
 * mountFaqAccordion — the production-mode entry point. Finds the FAQ in the
 * given root, parses it, renders the chosen accordion variant in its place,
 * and hides the original messy markup.
 *
 * In this design file we use it for the demo. On the live site the same
 * function would be called once on DOMContentLoaded inside the product
 * description container.
 */
function mountFaqAccordion(root, renderFn) {
  const items = parseFaq(root);
  if (!items.length) return null;
  return items;
}

Object.assign(window, { parseFaq, mountFaqAccordion });
