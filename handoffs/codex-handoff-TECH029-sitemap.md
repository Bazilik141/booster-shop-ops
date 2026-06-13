# Codex Handoff — TECH-029
## Sitemap audit & fix

---

## 1. Short conclusion

Screaming Frog (baseline crawl 2026-06-07) не знайшов жодного URL у sitemap сайту. SF перевіряє `/sitemap.xml`, `/sitemap_index.xml` та `Sitemap:` у `robots.txt` — всі три варіанти дали 0 результатів. Є окрема історія по sitemap в іншому діалозі — цей хендоф фіксує технічний стан і кроки для Codex.

---

## 2. Task type

Technical SEO — sitemap generation / registration / robots.txt fix

---

## 3. Executor

**Mixed**: Codex (файлові зміни) + Owner manual (GSC реєстрація)

---

## 4. Status

Not started → потрібна діагностика перед патчем

---

## 5. Next action

Codex виконує **Phase 1 — Diagnosis** (read-only), повертає звіт. Потім Phase 2 (fix) залежно від результатів.

---

## 6. Handoff for Codex

### Context

- Site: boostershop.website (OpenCart 4, PHP 8)
- Root: `/home2/boosters/public_html/`
- DB prefix: `ocp5_`
- Language: `uk` (Ukrainian), URL prefix `/ua/`

---

### PHASE 1 — Diagnosis (read-only, завжди виконувати першим)

Codex збирає факти і повертає звіт. Ніяких змін на цьому етапі.

#### 1.1 Перевірити наявність sitemap файлів

```bash
ls -la /home2/boosters/public_html/sitemap*.xml 2>/dev/null || echo "NO_SITEMAP_FILES"
ls -la /home2/boosters/public_html/sitemap_index.xml 2>/dev/null || echo "NO_SITEMAP_INDEX"
```

#### 1.2 Перевірити robots.txt на Sitemap: директиву

```bash
cat /home2/boosters/public_html/robots.txt
```

Шукаємо рядок `Sitemap:`. Якщо відсутній — потрібно додати.

#### 1.3 Перевірити налаштування sitemap в OpenCart DB

```sql
SELECT `key`, `value` 
FROM ocp5_setting 
WHERE `key` LIKE '%sitemap%' 
ORDER BY `key`;
```

#### 1.4 Перевірити наявність sitemap extension/module

```sql
SELECT extension_id, type, code, version, status
FROM ocp5_extension
WHERE code LIKE '%sitemap%' OR code LIKE '%xml%';
```

#### 1.5 Перевірити OpenCart XML sitemap controller

```bash
ls /home2/boosters/public_html/catalog/controller/extension/feed/ 2>/dev/null
ls /home2/boosters/public_html/catalog/controller/feed/ 2>/dev/null
```

OpenCart 4 має вбудований XML sitemap за адресою `/index.php?route=extension/feed/google_sitemap` або `/index.php?route=feed/google_sitemap`.

#### 1.6 Перевірити .htaccess на sitemap rewrite

```bash
grep -i "sitemap" /home2/boosters/public_html/.htaccess
```

---

### PHASE 2 — Fix (виконувати після звіту Phase 1)

Залежно від результатів Phase 1 — один з варіантів:

---

#### Варіант A: OpenCart XML Feed існує але не активований

Якщо `ocp5_extension` має запис з кодом `google_sitemap` і `status=0`:

```sql
UPDATE ocp5_extension 
SET status = 1 
WHERE code = 'google_sitemap';

-- Активуємо також у налаштуваннях
UPDATE ocp5_setting 
SET `value` = '1' 
WHERE `key` = 'feed_google_sitemap_status';
```

Потім додаємо rewrite у `.htaccess` (перед `# --START--` або аналогічним маркером):

```apache
# XML Sitemap
RewriteRule ^sitemap\.xml$ index.php?route=extension/feed/google_sitemap [L]
RewriteRule ^sitemap_index\.xml$ index.php?route=extension/feed/google_sitemap [L]
```

---

#### Варіант B: Sitemap файл існує але не підключений у robots.txt

Якщо `sitemap.xml` або `sitemap_index.xml` є на диску — лише додаємо в `robots.txt`.

**Файл:** `/home2/boosters/public_html/robots.txt`

Додати в кінець:
```
Sitemap: https://boostershop.website/sitemap.xml
```

або якщо `sitemap_index.xml`:
```
Sitemap: https://boostershop.website/sitemap_index.xml
```

---

#### Варіант C: Sitemap відсутній повністю

Якщо немає ні файлів, ні активного feed — генеруємо статичний `sitemap.xml`.

**Файл:** `/home2/boosters/public_html/sitemap.xml`

Структура (Codex генерує на основі реальних даних з DB):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">

  <!-- Головна -->
  <url>
    <loc>https://boostershop.website/ua/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Категорії (тільки indexable, без path=65/MTG/Інші TCG поки немає навігації) -->
  <!-- Codex генерує з ocp5_category + ocp5_category_description WHERE status=1 -->

  <!-- Товари (тільки indexable, status=1, quantity>0 або pre-order) -->
  <!-- Codex генерує з ocp5_product + ocp5_url_alias -->

  <!-- Статичні сторінки -->
  <url>
    <loc>https://boostershop.website/ua/pro-nas</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

</urlset>
```

**SQL для категорій (приклад):**
```sql
SELECT CONCAT('https://boostershop.website/ua/', ua.keyword) AS url
FROM ocp5_category c
JOIN ocp5_url_alias ua ON ua.query = CONCAT('path=', c.category_id)
WHERE c.status = 1
  AND c.category_id NOT IN (65) -- навмисно без навігації
ORDER BY c.sort_order;
```

**SQL для товарів:**
```sql
SELECT CONCAT('https://boostershop.website/ua/', ua.keyword) AS url
FROM ocp5_product p
JOIN ocp5_url_alias ua ON ua.query = CONCAT('product_id=', p.product_id)
WHERE p.status = 1
ORDER BY p.date_modified DESC;
```

---

#### Завжди (всі варіанти): оновити robots.txt

Переконатись що в `/home2/boosters/public_html/robots.txt` є:

```
Sitemap: https://boostershop.website/sitemap.xml
```

Якщо рядок вже є — нічого не змінювати.

---

## 7. QA Checklist (Owner виконує після деплою)

- [ ] GET `https://boostershop.website/sitemap.xml` → 200 OK, XML-вміст у браузері
- [ ] XML містить URL категорій і товарів (не порожній)
- [ ] Жоден URL у sitemap не має `noindex` мета-тегу (перехресна перевірка з SF)
- [ ] GET `https://boostershop.website/robots.txt` → є рядок `Sitemap: https://...`
- [ ] GSC → Search Console → Sitemaps → Submit → `https://boostershop.website/sitemap.xml` → статус `Success`
- [ ] SF re-crawl: поле `URLs in Sitemap > 0`
- [ ] Всі URL у sitemap без `?page=`, `?sort=`, `?filter=` параметрів

---

## 8. Risks

| Ризик | Рівень | Дія |
|---|---|---|
| Sitemap включає noindex сторінки (категорії з параметрами) | High | Фільтрувати при генерації — тільки canonical URL без параметрів |
| URL у sitemap без `/ua/` мовного префіксу | Medium | Перевірити що всі URL через `ocp5_url_alias` з мовним prefix |
| Категорії без навігації (path=65, MTG, Інші TCG) потрапляють у sitemap | Medium | Явно виключити з WHERE або перевірити чи є в sitemap |
| Дублі `/` vs без slash | Low | Canonical вже вказує на чисту URL, але в sitemap має бути одна форма |
| robots.txt вже має Disallow для `/` — sitemap не допоможе | Critical | Перевірити robots.txt ПЕРШИМ у Phase 1 |

---

## Acceptance criteria

- `sitemap.xml` доступний за прямим URL (200 OK, валідний XML)
- `robots.txt` містить `Sitemap:` директиву
- GSC → Sitemaps → Success
- SF re-crawl: `URLs in Sitemap > 0`
- Жодна noindex сторінка не потрапила у sitemap
