# Codex Handoff — TECH-032 + TECH-033 + TECH-034
## Robots.txt fix + Structured data verify + Image compression

> **Context:** Продовження сесії після TECH-029 (sitemap). Виконувати після того як TECH-029 Phase 1 завершено — бо robots.txt зачіпається в обох задачах.

---

---

# TECH-032 — Robots.txt: block internal search & params

## Short conclusion
`robots.txt` не блокує внутрішній пошук і технічні URL OpenCart. Crawl-бюджет витрачається на непотрібні сторінки. Також потрібна `Sitemap:` директива (якщо TECH-029 ще не додав її).

## Task type
Technical SEO — robots.txt edit

## Executor
Codex (файлова зміна) — **HIGH RISK для SEO**, обережно

---

### Поточний стан (Codex читає першим)

```bash
cat /home2/boosters/public_html/robots.txt
```

Зберегти вивід — він потрібен для точкового патчу.

---

### Зміни у robots.txt

**Додати блоки** (якщо їх ще немає) — вставити після існуючих `Disallow:` рядків:

```
# Internal search — no indexing value
Disallow: /index.php?route=common/search
Disallow: /*?search=
Disallow: /*?filter_name=

# OpenCart technical routes
Disallow: /index.php?route=account/
Disallow: /index.php?route=checkout/
Disallow: /index.php?route=common/
Disallow: /index.php?route=error/

# Pagination & sort params (noindex via meta, but save crawl budget)
Disallow: /*?sort=
Disallow: /*?order=
Disallow: /*?limit=
```

**Sitemap директива** — додати в кінець файлу (пропустити якщо TECH-029 вже додав):

```
Sitemap: https://boostershop.website/sitemap.xml
```

---

### ⚠️ СТОП-умови — не вносити зміни якщо:
- `robots.txt` містить `Disallow: /` — ескалювати до owner, сайт може бути повністю закритий
- Будь-який `Disallow:` що покриває `/ua/` або кореневі категорії — ескалювати

---

### QA checklist
- [ ] GET `https://boostershop.website/robots.txt` → 200 OK
- [ ] Рядок `Disallow: /*?search=` присутній
- [ ] Рядок `Disallow: /index.php?route=account/` присутній
- [ ] Рядок `Sitemap:` присутній (або підтвердити що TECH-029 вже додав)
- [ ] Головна сторінка, категорії, товари — НЕ заблоковані
- [ ] GSC → URL Inspection → `https://boostershop.website/ua/kartky-pokemon` → не blocked by robots

### Risks
| Ризик | Рівень |
|---|---|
| Надто широкий `Disallow: /*?` може блокувати легітимні URL | High |
| Дублювання з TECH-029 `Sitemap:` директивою | Low |

---

---

# TECH-033 — Structured data: verify TECH-009 deployment

## Short conclusion
SF baseline crawl (2026-06-07) знайшов **0 сторінок зі structured data**, попри те що TECH-009 (Product schema) позначено як Done. Або schema не задеплоєна на boostershop.website, або рендериться JS і SF не бачить. Потрібна верифікація.

## Task type
Technical SEO — structured data audit + deploy verify

## Executor
Codex (read + fix якщо потрібно) + Owner (Google Rich Results Test)

---

### PHASE 1 — Verify (read-only)

#### 1.1 Перевірити наявність schema у twig-шаблоні товару

```bash
grep -n "schema\|application/ld+json\|@type.*Product\|breadcrumb" \
  /home2/boosters/public_html/catalog/view/template/product/product.twig
```

#### 1.2 Перевірити category.twig (BreadcrumbList вже є з попереднього патчу?)

```bash
grep -n "application/ld+json\|BreadcrumbList\|@context" \
  /home2/boosters/public_html/catalog/view/template/product/category.twig
```

#### 1.3 Перевірити чи є окремий файл schema/structured data

```bash
find /home2/boosters/public_html/catalog/view/template/ -name "*.twig" | \
  xargs grep -l "application/ld+json" 2>/dev/null
```

#### 1.4 Перевірити controller product.php на передачу schema-даних

```bash
grep -n "schema\|structured\|json_ld\|ld.json" \
  /home2/boosters/public_html/catalog/controller/product/product.php
```

---

### PHASE 2 — Fix (якщо schema відсутня у product.twig)

Якщо у `product.twig` немає `application/ld+json` — додати Product schema перед `{{ footer }}`.

**Файл:** `catalog/view/template/product/product.twig`  
**Вставити перед** `{{ footer }}`:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "{{ heading_title|e('js') }}",
  "description": "{{ description|striptags|slice(0, 500)|e('js') }}",
  {% if image %}
  "image": ["{{ image }}"],
  {% endif %}
  "brand": {
    "@type": "Brand",
    "name": "{{ manufacturer ? manufacturer : 'Booster Shop' }}"
  },
  {% if sku %}
  "sku": "{{ sku|e('js') }}",
  {% endif %}
  {% if model %}
  "mpn": "{{ model|e('js') }}",
  {% endif %}
  "offers": {
    "@type": "Offer",
    "url": "{{ canonical }}",
    "priceCurrency": "UAH",
    "price": "{{ price_without_currency is defined ? price_without_currency : price|replace({' грн':'',' UAH':''}) }}",
    "availability": "{% if stock_status is defined and quantity > 0 %}https://schema.org/InStock{% else %}https://schema.org/OutOfStock{% endif %}",
    "seller": {
      "@type": "Organization",
      "name": "Booster Shop"
    }
  }
}
</script>
```

> **Примітка:** Перед вставкою Codex має перевірити які саме змінні доступні у `product.twig` — `{{ price_without_currency }}`, `{{ quantity }}`, `{{ canonical }}` тощо. Якщо змінна недоступна — замінити на те що є або пропустити поле.

**Перевірити доступні змінні:**
```bash
grep -n "\$data\['" \
  /home2/boosters/public_html/catalog/controller/product/product.php | \
  grep -i "price\|stock\|quantity\|sku\|model\|manufacturer\|canonical" | head -20
```

---

### QA checklist
- [ ] View source на сторінці товару → `<script type="application/ld+json">` присутній
- [ ] JSON валідний (перевірити через jsonlint.com)
- [ ] Google Rich Results Test → `https://boostershop.website/ua/[будь-який-товар]` → Product знайдено
- [ ] Поля `name`, `price`, `availability`, `image` заповнені
- [ ] SF re-crawl: Structured Data > 0

### Risks
| Ризик | Рівень |
|---|---|
| Невірна ціна у schema (з символами валюти) | High |
| `availability` InStock при реально 0 qty | High |
| Дублювання якщо TECH-009 вже є але в іншому місці | Medium |

---

---

# TECH-034 — Image compression (31 images > 100kB)

## Short conclusion
SF baseline crawl знайшов **31 зображення > 100kB** що завантажується браузером. Це впливає на Core Web Vitals (LCP) і Page Speed. Потрібна компресія або конвертація у WebP.

## Task type
Performance — image optimization

## Executor
Codex (bash script на сервері)

---

### PHASE 1 — Audit (read-only)

Знайти всі великі зображення у `image/catalog/`:

```bash
find /home2/boosters/public_html/image/catalog/ -type f \
  \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" \) \
  -size +100k \
  -exec ls -lh {} \; | sort -k5 -rh | head -40
```

Зберегти список — він потрібен для Phase 2.

---

### PHASE 2 — Compress

#### Перевірити наявність утиліт

```bash
which jpegoptim && jpegoptim --version
which optipng && optipng --version
which cwebp && cwebp -version
```

#### Варіант A: jpegoptim + optipng (compression in-place)

```bash
# JPEG — зберегти якість 85%, зменшить розмір на ~30-50%
find /home2/boosters/public_html/image/catalog/ -type f \
  \( -iname "*.jpg" -o -iname "*.jpeg" \) \
  -size +100k \
  -exec jpegoptim --max=85 --strip-all {} \;

# PNG
find /home2/boosters/public_html/image/catalog/ -type f \
  -iname "*.png" -size +100k \
  -exec optipng -o2 {} \;
```

#### Варіант B: якщо jpegoptim недоступний — PHP fallback

Codex генерує PHP-скрипт який використовує GD або ImageMagick (є на більшості shared hosting):

```bash
# Перевірити що є
php -r "echo extension_loaded('gd') ? 'GD OK' : 'GD missing'; echo PHP_EOL;"
php -r "echo class_exists('Imagick') ? 'Imagick OK' : 'Imagick missing'; echo PHP_EOL;"
```

---

#### Очистити OC image cache після компресії

```bash
find /home2/boosters/public_html/image/cache/ -type f -delete
echo "Image cache cleared"
```

OpenCart автоматично перегенерує кеш при наступних запитах до сторінок.

> ⚠️ **Попередження:** очищення cache directory може тимчасово збільшити навантаження на сервер (масова регенерація thumbnails). Краще робити у неактивний час.

---

### QA checklist
- [ ] Перевірити розміри після компресії: `find ... -size +100k` → менше 31 файлу
- [ ] Відкрити кілька сторінок товарів → зображення відображаються коректно
- [ ] PageSpeed Insights до/після — Score має вирости
- [ ] Жодне зображення не деградувало до неприйнятної якості

### Risks
| Ризик | Рівень |
|---|---|
| Перекомпресія → артефакти у зображеннях | Medium |
| Cache очищення → навантаження сервера | Medium |
| TECH-030 patch додав "Показати ще" — більше товарів на сторінці → LCP може погіршитись якщо зображення не стиснуті | Medium |

---

---

## Загальний QA порядок для цих трьох задач

1. **TECH-032** (robots.txt) → перевірити одразу в браузері + GSC URL inspection
2. **TECH-033** (schema) → Rich Results Test на 2-3 товарах
3. **TECH-034** (images) → PageSpeed Insights до/після на сторінці категорії

## Залежності між задачами

```
TECH-029 (sitemap) ─── Sitemap: директива ──→ TECH-032 (robots.txt)
                                               (координація: не дублювати)

TECH-030 (noindex fix) ──────────────────────→ TECH-033 (schema)
                                               (page=2+ тепер indexable →
                                                schema має бути на всіх товарах)
```
