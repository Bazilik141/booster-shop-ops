# Codex Handoff — TECH-030 + TECH-031
## Noindex fix + "Показати ще" + 404 image

---

## 1. Short conclusion

Три незалежних фікси:
- **A** — прибрати `noindex` з page=2+ (1 рядок у PHP)
- **B** — замінити стандартну пагінацію кнопкою "Показати ще" з AJAX load-more (зміни у twig)
- **C** — виправити 404 для зображення категорії "One Piece" (SQL або admin)

---

## 2. Task type

Technical — PHP controller patch + Twig template change + SQL fix

---

## 3. Executor

**Codex** (фікси A + B) + **Manual/HeidiSQL** (фікс C)

---

## 4. Status

Ready for Codex execution

---

## 5. Next action

1. Codex виконує фікси A + B
2. Owner вручну виконує фікс C через HeidiSQL або OpenCart admin
3. Owner перевіряє QA checklist

---

## 6. Handoff for Codex

### Context

- Site: boostershop.website (OpenCart 4, PHP 8)
- Source of truth: latest backup — `backup-6.3.2026_21-17-59_boosters.tar.gz`
- All files below are relative to `/public_html/`

---

### FIX A — Remove `noindex` from page=2+ category pages

**File:** `catalog/controller/product/category.php`  
**Line:** 518

**Current code:**
```php
$has_duplicate_params = isset($this->request->get['filter']) || isset($this->request->get['sort']) || isset($this->request->get['order']) || isset($this->request->get['page']) || isset($this->request->get['limit']);
```

**Replace with:**
```php
$has_duplicate_params = isset($this->request->get['filter']) || isset($this->request->get['sort']) || isset($this->request->get['order']) || isset($this->request->get['limit']);
```

**What changes:** Removed `|| isset($this->request->get['page'])`.  
**Why:** `?page=N` URLs are canonical paginated pages — they must be `index,follow`. Filters/sort/limit stay noindex (correct).  
**Line 532 stays unchanged:**
```php
$data['robots'] = $has_duplicate_params ? 'noindex,follow' : 'index,follow';
```

---

### FIX B — Replace `{{ pagination }}` with "Показати ще" AJAX button

**File:** `catalog/view/template/product/category.twig`

#### B.1 — Replace pagination block (lines ~113–116)

**Current code:**
```twig
        <div class="row">
          <div class="col-sm-6 text-start">{{ pagination }}</div>
          <div class="col-sm-6 text-end">{{ results }}</div>
        </div>
```

**Replace with:**
```twig
        {# SEO pagination fallback: hidden visually, preserves rel=prev/next in <head> via controller #}
        <noscript>
          <div class="row">
            <div class="col-sm-6 text-start">{{ pagination }}</div>
            <div class="col-sm-6 text-end">{{ results }}</div>
          </div>
        </noscript>

        {# Results count — always visible #}
        <div class="text-end bs-results-count mb-2">{{ results }}</div>

        {# Load More button — JS shows/hides based on rel=next in <head> #}
        <div id="bs-load-more-wrap" class="text-center mt-2 mb-4" style="display:none" aria-live="polite">
          <button id="bs-load-more-btn" type="button" class="bs-btn-load-more">
            <span class="bs-load-more-label">Показати ще</span>
            <span class="bs-load-more-spinner" aria-hidden="true"></span>
          </button>
        </div>
```

#### B.2 — Add CSS inside the existing `<style>` block in category.twig

Find the closing `</style>` tag inside category.twig and **insert before it**:

```css
/* Load More button */
#bs-load-more-wrap { padding: 8px 0 16px; }
.bs-btn-load-more {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 32px;
  background: transparent;
  border: 1.5px solid var(--bs-line, #e0e0e0);
  border-radius: var(--bs-r-sm, 8px);
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  color: var(--bs-ink, #1a1a1a);
  cursor: pointer;
  transition: border-color .15s ease, background .15s ease;
}
.bs-btn-load-more:hover:not(:disabled) {
  border-color: var(--bs-pokemon, #e6400c);
  background: var(--bs-surface, #fafafa);
}
.bs-btn-load-more:disabled {
  opacity: .55;
  cursor: wait;
}
.bs-load-more-spinner {
  display: none;
  width: 16px;
  height: 16px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: bs-spin .6s linear infinite;
}
.bs-btn-load-more.loading .bs-load-more-spinner { display: inline-block; }
.bs-btn-load-more.loading .bs-load-more-label { opacity: .6; }
@keyframes bs-spin { to { transform: rotate(360deg); } }
```

#### B.3 — Add JS before closing `</script>` or before closing `</body>` in category.twig

Add as a new `<script>` block after the existing scripts in category.twig:

```html
<script>
(function () {
  'use strict';
  var wrap = document.getElementById('bs-load-more-wrap');
  var btn  = document.getElementById('bs-load-more-btn');
  var list = document.getElementById('product-list');
  if (!wrap || !btn || !list) return;

  // Read next URL from <link rel="next"> in <head> (set by controller)
  function getNextUrl() {
    var el = document.querySelector('link[rel="next"]');
    return el ? el.href : null;
  }

  // Update or remove <link rel="next"> in <head>
  function setNextUrl(url) {
    var el = document.querySelector('link[rel="next"]');
    if (url) {
      if (el) { el.href = url; }
      else {
        var l = document.createElement('link');
        l.rel = 'next'; l.href = url;
        document.head.appendChild(l);
      }
    } else {
      if (el) el.parentNode.removeChild(el);
    }
  }

  var nextUrl = getNextUrl();
  if (nextUrl) wrap.style.display = '';

  btn.addEventListener('click', function () {
    if (!nextUrl || btn.disabled) return;
    btn.disabled = true;
    btn.classList.add('loading');

    fetch(nextUrl, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function (html) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');

        // Append new product cards
        var newCols = doc.querySelectorAll('#product-list > .col');
        newCols.forEach(function (col) { list.appendChild(col); });

        // Advance next URL
        var newNext = doc.querySelector('link[rel="next"]');
        nextUrl = newNext ? newNext.href : null;
        setNextUrl(nextUrl);

        btn.disabled = false;
        btn.classList.remove('loading');
        if (!nextUrl) wrap.style.display = 'none';
      })
      .catch(function (err) {
        console.error('[bs-load-more] fetch error:', err);
        btn.disabled = false;
        btn.classList.remove('loading');
      });
  });
})();
</script>
```

---

### FIX C — 404 image for "One Piece" category (Manual / SQL)

**Problem:** DB stores `catalog/One Piece/One PieceC.png` but the file doesn't exist.  
**Correct file on server:** `image/catalog/One Piece/One Piece-Photoroom.png`

**Option 1 — SQL via HeidiSQL (fastest):**
```sql
UPDATE `oc_category`
SET `image` = 'catalog/One Piece/One Piece-Photoroom.png'
WHERE `image` = 'catalog/One Piece/One PieceC.png';
```
Run on the boostershop DB. Verify: `SELECT category_id, image FROM oc_category WHERE image LIKE 'catalog/One Piece%';`

**Option 2 — OpenCart Admin (no SQL access):**
Admin → Catalog → Categories → find "One Piece" → Edit → Image field → upload / select `One Piece-Photoroom.png` → Save

**Codex: do NOT touch this file** — owner handles manually.

---

## 7. QA Checklist

After Codex executes fixes A + B, and owner executes fix C:

**Fix A — noindex:**
- [ ] Open `https://boostershop.website/ua/kartky-pokemon?page=2` in browser
- [ ] View page source → `<meta name="robots">` must be `index,follow` (not `noindex`)
- [ ] Check page=3, page=4 — same result
- [ ] `?sort=p.price&order=ASC` (without page param) → must still be `noindex,follow`
- [ ] `?filter_something=1` → must still be `noindex,follow`

**Fix B — Load More:**
- [ ] Open any category with >16 products (e.g., Pokemon)
- [ ] Standard pagination NOT visible on screen
- [ ] "Показати ще" button visible below product grid
- [ ] Click button → products append without page reload
- [ ] Spinner appears during fetch
- [ ] Click again → more products append (if more pages exist)
- [ ] After last page → button disappears
- [ ] View source: `<link rel="next">` present in `<head>` on page=1 with 2+ pages
- [ ] `<noscript>` block contains standard pagination (verify with DevTools → disable JS)
- [ ] Category with ≤16 products → "Показати ще" NOT visible

**Fix C — One Piece image:**
- [ ] Open `https://boostershop.website/ua/kartky-one-piece` (or category listing page)
- [ ] Category tile image loads without 404
- [ ] Check DevTools Network tab: no 404 for `/image/catalog/One Piece/` requests

---

## 8. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| `rel="next"` not generated for page=1 if only 1 page | Low | Controller line 528: `ceil($product_total / $limit) > $page` — won't generate next on single-page categories. JS hides button correctly. |
| AJAX fetches cached page without current sort/filter state | Medium | The `?page=N` URL inherits the base category URL. If user applies sort THEN clicks "show more" — the next-page fetch will use the sorted URL since `rel="next"` is generated with the full URL including sort params. **Verify with sort applied.** |
| `#product-list > .col` selector breaks if server returns error page | Low | JS `.catch()` handles network errors. Graceful — button re-enables. |
| OpenCart cache serves old `noindex` after PHP change | Medium | After deploy: Admin → Dashboard → clear theme/twig cache, or SSH: `rm -rf /public_html/system/storage/cache/*` |
| Fix C SQL UPDATE matches wrong category | Low | Query uses exact `image` value match. Run SELECT first to verify row count before UPDATE. |

---

## Acceptance Criteria

- All `?page=N` URLs on category/subcategory pages return `<meta name="robots" content="index,follow">`
- URL-based pagination replaced by append-in-place Load More on category pages
- No JS errors in browser console during Load More interaction
- `<noscript>` fallback contains full pagination for SEO bots without JS
- One Piece category tile image loads with HTTP 200
