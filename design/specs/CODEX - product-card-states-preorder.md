# Хендоф для Codex — Картки товару: чистка статусів + новий «Передзамовлення»

**Дата:** 2026-06-01
**Гілка:** `feat/pcard-states-preorder`
**Стек:** OpenCart 4 · тема `catalog/view/template/...` · Twig + `bs-*` дизайн-система (`boostershop-ds.css`)
**Скоуп:** тільки картка товару (всюди, де рендериться `product/thumb`) + один токен кольору. Без змін у кошику, оплаті, фіскалізації.

Затверджено власником на дошках:
- `Картки товару — пропозиції.html` — чистка ряду + статус «Передзамовлення».
- `Колір знижки та ціни — рішення.html` — бейдж знижки лишається **чорним без градації**; колір акційної ціни **#B91C1C → #DC2626**.

---

## 0. TL;DR — що робимо

1. **Токен:** `--bs-danger: #B91C1C → #DC2626` (чистіша акційна ціна). Бейдж знижки **не чіпаємо** — лишається чорний (`--bs-ink`), без тирів за розміром.
2. **Картка, out-of-stock:** прибрати дубль статусу; приглушити фото; замість «мертвого» тексту — кнопка **«Повідомити про наявність»**.
3. **Новий статус `preorder`:** синій бейдж «Передзамовлення» + рядок орієнтовної дати + **синя** кнопка **«Передзамовити»** (не зелена).
4. **Вирівняти кнопки** в ряду (однакова висота карток).

> ⚠️ Головне про архітектуру: у цій темі картка рендериться окремим контролером **`product/thumb`** (`category.php` → `$this->load->controller('product/thumb', $product_data)`, а `category.twig` лише друкує `{{ product }}`). Тому **редагуємо `product/thumb`, а не `category.twig`** — і зміни автоматично підхопляться в категорії, на головній, у пошуку та «схожих».

**Файли:**
| Файл | Зміна |
|---|---|
| `catalog/view/template/.../stylesheet/boostershop-ds.css` *(або де підключено DS)* | токен `--bs-danger`; новий клас `.bs-pcard__eta` |
| `catalog/controller/product/thumb.php` | визначення `state` + `eta`; проброс `quantity`/`date_available` |
| `catalog/view/template/product/thumb.twig` | бейджі + ціна + CTA за станом |
| `catalog/view/javascript/...` (де живе add-to-cart) | хендлер `data-notify` для «Повідомити» |

Бекап і ідемпотентність — як у попередніх патчах (`_patch_backups/<stamp>/`, повторний запуск = no-op).

---

## 1. Токен кольору (1 рядок)

У `boostershop-ds.css`, секція `:root` / Semantic:

```css
/* було */
--bs-danger: #B91C1C;
/* стало */
--bs-danger: #DC2626;
```

Більше нічого не міняти — `.bs-pcard__price-row--sale .bs-pcard__price-new`, `.bs-field-error`, `.bs-input--error` тощо вже посилаються на токен і оновляться самі. **Бейдж знижки `.bs-badge--discount` лишається `background: var(--bs-ink)` (чорний).**

Додати клас рядка дати (нижче бейджів):

```css
/* -- Product card · preorder ETA --------------------------------------- */
.bs-pcard__eta {
  display: flex; align-items: center; gap: 6px;
  font-size: 11.5px; color: var(--bs-ink-3); margin: -2px 0 0;
}
.bs-pcard__eta::before {
  content: ""; width: 5px; height: 5px; border-radius: 999px;
  background: var(--bs-blue-light); flex: 0 0 auto;
}
```

Класи кнопок уже є в DS — **нічого не додавати**: `.bs-btn-preorder` (синя, `--bs-blue-light`, hover `#2563EB`) і `.bs-btn-secondary` (біла з рамкою) присутні в `boostershop-ds.css`.

---

## 2. Контролер — `catalog/controller/product/thumb.php`

Картці потрібні `quantity` і `date_available`, щоб вивести стан. Переконатися, що `category.php` (і решта викликів `product/thumb`) кладуть їх у `$product_data` — поле `quantity` уже приходить у `$result` (`+ $result`), тож зазвичай доступне; `date_available` додати в SELECT/масив за потреби.

У `thumb.php`, перед передачею в шаблон, обчислити `state` і `eta`:

```php
// --- BoosterShop product state -------------------------------------------
$today   = date('Y-m-d');
$qty     = isset($data['quantity']) ? (int)$data['quantity'] : 0;
$avail   = !empty($data['date_available']) ? $data['date_available'] : null;

// Передзамовлення: дата доступності в майбутньому (товар ще не вийшов).
$is_preorder = $avail && $avail > $today;

// Немає в наявності: нуль/менше і це НЕ передзамовлення.
$is_out = !$is_preorder && $qty <= 0;

if ($is_preorder)      { $data['bs_state'] = 'preorder'; }
elseif ($is_out)       { $data['bs_state'] = 'out'; }
else                   { $data['bs_state'] = ''; }   // sealed = дефолт, без бейджа

// Орієнтовна дата для передзамовлення — «Місяць РІК» укр.
$data['bs_eta'] = '';
if ($is_preorder) {
    static $months = [1=>'січень',2=>'лютий',3=>'березень',4=>'квітень',5=>'травень',6=>'червень',
                      7=>'липень',8=>'серпень',9=>'вересень',10=>'жовтень',11=>'листопад',12=>'грудень'];
    $ts = strtotime($avail);
    $data['bs_eta'] = 'Орієнтовно: ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}
```

> Якщо для «передзамовлення» вже використовується інший сигнал (тег/атрибут/спец-статус) — заміни умову `$is_preorder` на цей сигнал, логіка шаблону не зміниться. `date_available` обрано бо це нативне поле OpenCart і адмінка ним уже керує.

---

## 3. Шаблон — `catalog/view/template/product/thumb.twig`

Знайти існуючий блок бейджів, ціни та кнопки «Купити» / cart і привести до структури нижче. Зберегти наявні OpenCart-атрибути (`onclick="cart.add(...)"` або `data-*`, що вже використовуються) — міняємо лише **обгортку/класи/текст і логіку за станом**.

```twig
{# state: '' (в наявності) | 'preorder' | 'out' #}
{% set is_out = bs_state == 'out' %}
{% set is_pre = bs_state == 'preorder' %}
{% set discount = special and price and special < price
   ? ((1 - (special|replace({'₴':'','\u00a0':'',' ':'',',':'.'})|number_format)
        / (price|replace({'₴':'','\u00a0':'',' ':'',',':'.'})|number_format)) * 100)|round
   : 0 %}
{# ↑ якщо discount уже рахується деінде числом — використай готове, не парси рядок ціни #}

<article class="bs-pcard{% if is_out %} bs-pcard--out{% endif %}">
  <a class="bs-pcard__media" href="{{ href }}">
    <img src="{{ thumb }}" alt="{{ name }}" loading="lazy">

    {# знижка — праворуч, чорний бейдж, тільки якщо не out #}
    {% if discount > 0 and not is_out %}
      <span class="bs-pcard__badge-tr">
        <span class="bs-badge bs-badge--discount">−{{ discount }}%</span>
      </span>
    {% endif %}

    {# статус — ліворуч, рівно ОДИН #}
    {% if is_pre %}
      <span class="bs-pcard__badge-tl"><span class="bs-badge bs-badge--preorder">Передзамовлення</span></span>
    {% elseif is_out %}
      <span class="bs-pcard__badge-tl"><span class="bs-badge bs-badge--out">Немає в наявності</span></span>
    {% endif %}
  </a>

  <div class="bs-pcard__body">
    <h4 class="bs-pcard__title"><a href="{{ href }}">{{ name }}</a></h4>

    {% if is_pre and bs_eta %}
      <div class="bs-pcard__eta">{{ bs_eta }}</div>
    {% endif %}

    <div class="bs-pcard__price-row{% if special %} bs-pcard__price-row--sale{% endif %}">
      {% if special %}
        <span class="bs-pcard__price-new">{{ special }}</span>
        <span class="bs-pcard__price-old">{{ price }}</span>
      {% else %}
        <span class="bs-pcard__price-new">{{ price }}</span>
      {% endif %}
    </div>

    {% if is_out %}
      <button type="button" class="bs-btn bs-btn-secondary" data-notify="{{ product_id }}">
        Повідомити про наявність
      </button>
    {% elseif is_pre %}
      <button type="button" class="bs-btn bs-btn-preorder" onclick="cart.add('{{ product_id }}', '{{ minimum }}');">
        Передзамовити
      </button>
    {% else %}
      <button type="button" class="bs-btn bs-btn-primary" onclick="cart.add('{{ product_id }}', '{{ minimum }}');">
        Купити
      </button>
    {% endif %}
  </div>
</article>
```

Примітки:
- **Ціна:** OpenCart уже віддає `special` (рядок з валютою) лише коли є акція. Не вводимо власний червоний — колір дає токен `--bs-danger` через `--sale`. Стара ціна — приглушена закреслена.
- **Дубль статусу прибрано:** статус живе тільки в бейджі; знизу — завжди дієва кнопка.
- **Передзамовлення = покупка**, тому та сама механіка `cart.add` що й «Купити», лише клас (синій) і текст інші. Якщо для передзамовлення потрібна окрема логіка кошика — підставити її тут, але дефолт: звичайне додавання.
- **`product_id`/`minimum`** беруться з даних thumb (вони вже є для існуючої кнопки) — підстав так, як зараз у файлі.

---

## 4. JS — кнопка «Повідомити про наявність» (`data-notify`)

Для out-of-stock кнопка веде на збір контакту (lead). Мінімальний хендлер поряд із наявним кодом кошика:

```js
document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-notify]');
  if (!btn) return;
  e.preventDefault();
  var productId = btn.getAttribute('data-notify');
  // TODO власника: відкрити модалку «лишити email/телефон» або вести в Telegram-бота.
  // Поки що — заглушка-перехід, узгодити фінальний канал:
  // window.bsNotifyOpen(productId);
});
```

> Фінальний канал «повідомити» (email-форма / Telegram / OLX) — окреме рішення власника. У цьому патчі достатньо коректної кнопки + хука; не блокувати реліз чисткою карток через інтеграцію нотифікацій.

---

## 5. QA-чеклист

- [ ] **Акційна ціна** тепер `#DC2626` (категорія, головна, пошук, схожі). Бейдж `−16%` — **чорний**, не червоний.
- [ ] Бейджів статусу **не більше одного** на картці; знизу немає дубль-тексту «Немає в наявності».
- [ ] Out-of-stock: фото приглушене (`opacity .45`), кнопка **«Повідомити про наявність»** (біла з рамкою), клік ловиться `data-notify`.
- [ ] Preorder: синій бейдж «Передзамовлення», рядок «Орієнтовно: …», **синя** кнопка «Передзамовити»; `cart.add` працює.
- [ ] In-stock: зелена «Купити» без змін.
- [ ] **Кнопки в ряду на одній лінії** (різні стани, різна довжина назв).
- [ ] Перевірити стани: товар з `quantity=0` без `date_available` → `out`; товар з `date_available` у майбутньому → `preorder`; товар у наявності → дефолт.
- [ ] Мобільна сітка (2 колонки) не ламається; бейджі не накладаються на кут фото.
- [ ] Lighthouse/lazy-load зображень не зламано.

## 6. Не робити (out of scope)

- ❌ Не вводити тири/градацію кольору для бейджа знижки — лишається один чорний.
- ❌ Не чіпати кошик/оплату/фіскалізацію, лише розмітку картки.
- ❌ Не додавати червоний бейдж знижки (рішення: червоний живе на ціні, бейдж чорний).
- ❌ Не міняти `category.twig` для картки — редагуємо `product/thumb`.
```
