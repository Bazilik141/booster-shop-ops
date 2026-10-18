# CAT-005 — Bulk / Bulk Mix: FINAL card copy (3 SKU)

Date: 2026-10-18 · Source: ChatGPT draft `CAT-005_bulk-card-copy_20261018.md`, QA-polished by Claude.
Status: **ready for the Claude Code handoff; not published.** Customer-facing copy is Ukrainian, everything else English.

## Change log vs the ChatGPT draft

Pokémon Bulk 155 text is the owner's last edit; the only edits to it are listed so the owner can revert any of them.

| SKU | Change | Why |
|---|---|---|
| PKM-JP-BULK-155 | body: «під час власних сесій відкриттів паків» → «під час власного відкриття паків» | removes the "сесій … паків" calque |
| PKM-JP-BULK-155 | FAQ 2: added «але це не гарантія» | owner decision: no-repeat is an intention, not a guarantee |
| PKM-JP-BULK-155 | FAQ 4: rewritten, «у магазині» removed | exact sentence «Це не запечатаний продукт виробника.» was repeated across three cards; "відкриття у магазині" implied a place nobody verified |
| OP-JP-BULK-155 | body lead: «місце знаходиться» → «є місце» | Ukrainian wording |
| OP-JP-BULK-155 | body: «які раніше відкривали самі» → «які раніше відкрили самі» | aspect |
| OP-JP-BULK-155 | FAQ 2 / FAQ 3 / FAQ 4 | «але гарантії немає»; grading answer now also says the condition grade is not stated; FAQ 4 shortened |
| PKM-MIX-BULK-100 | body: «які самі раніше дістали» (word order); FAQ 3 «проте обіцяти цього не можемо»; body disclosure sentence reworded | same duplicates rule |
| all | removed the "owner decision overrides quotas" header claim | not verifiable; counts are within the accessory profile anyway |
| all | `self_qa` blocks dropped | self-graded; replaced by the measured checks below |

## Measured checks (recomputed by Claude)


| SKU | Meta title | Meta description | FAQ items | Forbidden terms hit |
|---|---|---|---|---|
| PKM-JP-BULK-155 | 54 / 63 | 125 / 155 | 4 | none |
| OP-JP-BULK-155 | 53 / 63 | 124 / 155 | 4 | none |
| PKM-MIX-BULK-100 | 50 / 63 | 128 / 155 | 4 | none |

Exact duplicate sentences across the 3 cards: **0**. FAQ ids and ARIA wiring resolve both ways on every card; ids are unique.

Forbidden-term list checked: Energy, DON!!, Leader, Mint/NM, «Bulk Box», «Mix Bulk», «100%», «гаранту…», «повторів немає», «офіційн…».

---

## PKM-JP-BULK-155

```yaml
sku: PKM-JP-BULK-155
h1: "Набір Pokémon TCG: Bulk 155 — 150 C/U + 5 R (Японське видання)"
seo_keyword: Pokemon-Bulk-155-JP
meta_title: "Pokémon TCG Bulk 155 JP — 150 C/U + 5 R | Booster Shop"
meta_description: "Bulk-набір Pokémon TCG: 155 японських карток — 150 C/U та 5 R. Оригінальні картки з відкритих бустерів для колекції й обміну."
tags: "Pokémon TCG, Bulk 155, японські картки, набір карток, Common, Uncommon, Rare"
category: "Pokémon → «Набори» (id 64); parent category assigned exactly as the sibling product 77/85 does"
manufacturer: "Pokémon (id 11)"
```

Description HTML (body + FAQ, store exactly as is):

```html
<p>Коли хочеться поповнити альбом японськими картками Покемон, відкривати бустери один за одним необов’язково. Формат Bulk дає одразу багато карток для колекції, обміну або експериментів із власною колодою.</p>
<h2>Що входить до набору</h2>
<p><strong>155 оригінальних карток Pokémon TCG японською мовою: 150 Common/Uncommon (C/U) та 5 Rare (R).</strong></p>
<p>Картки можуть походити з різних японських сетів, тому склад наборів відрізнятиметься. Це спосіб поповнити свою колекцію, а не полювання на конкретного покемона чи дорогу картку.</p>
<h2>Як ми формуємо набір</h2>
<p>Ми збираємо його з оригінальних карток, які раніше дістали з бустерів під час власного відкриття паків. Це не запечатаний набір виробника. Паковання підбираємо під час комплектації замовлення з урахуванням обсягу набору й захисту карток.</p>
<p>На фото показуємо приклад наповнення — у вашому наборі можуть бути інші картки.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="prod-pkm-jp-bulk-155">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-jp-bulk-155-panel-1" aria-expanded="false" id="bs-faq-prod-pkm-jp-bulk-155-button-1"><span>Що означають C/U та R у наборі Pokémon Bulk?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-jp-bulk-155-button-1" id="bs-faq-prod-pkm-jp-bulk-155-panel-1"><p>Це позначення рідкісності карток у Pokémon TCG: C (Common) — звичайні, U (Uncommon) — незвичайні, R (Rare) — рідкісні. Вони вказують на категорію картки, а не на її ринкову вартість.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-jp-bulk-155-panel-2" aria-expanded="false" id="bs-faq-prod-pkm-jp-bulk-155-button-2"><span>Чи можуть у Bulk-наборі трапитися повтори?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-jp-bulk-155-button-2" id="bs-faq-prod-pkm-jp-bulk-155-panel-2"><p>Ми намагаємося складати набори без повторів, але це не гарантія: випадковий дублікат можливий. Якщо повтор усе ж трапиться, це не вважається браком набору.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-jp-bulk-155-panel-3" aria-expanded="false" id="bs-faq-prod-pkm-jp-bulk-155-button-3"><span>Чи можна з карток Bulk 155 скласти колоду для гри?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-jp-bulk-155-button-3" id="bs-faq-prod-pkm-jp-bulk-155-panel-3"><p>Картки можна використовувати для власних ігрових задумів, але це не готова колода. Потрібні для вашої стратегії картки та їхню відповідність правилам обраного формату доведеться перевірити окремо.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-jp-bulk-155-panel-4" aria-expanded="false" id="bs-faq-prod-pkm-jp-bulk-155-button-4"><span>Це запечатаний набір від виробника?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-jp-bulk-155-button-4" id="bs-faq-prod-pkm-jp-bulk-155-panel-4"><p>Ні. Набір формуємо самі з оригінальних карток Pokémon TCG, які раніше дістали з фабричних бустерів. Він не запечатаний виробником, тому не варто чекати заводського пакування.</p></div>
</div>
</section>
```

Attributes (group 7, language_id 4):

| attribute_id | Name | Value |
|---|---|---|
| 12 | Мова | Японська (Japanese) |
| 13 | Назва сету | Bulk / різні японські сети Pokémon TCG |
| 17 | Стан | Оригінальні картки з розкритих бустерів; ступінь стану не заявляється |
| 18 | Походження товару | Картки з оригінальних бустерів, розкритих магазином |
| 20 | Виробник | The Pokémon Company |
| 21 | Тип пакування | Сформований набір карток; паковання підбирається при комплектації замовлення |
| 24 | Додатковий вміст | Немає; спеціальні рідкості не гарантуються |
| NEW | Кількість карток у наборі | 155 |
| NEW | Склад за рідкістю | 150 C/U + 5 R |

---

## OP-JP-BULK-155

```yaml
sku: OP-JP-BULK-155
h1: "Набір One Piece Card Game: Bulk 155 — 150 C/UC + 5 R (Японське видання)"
seo_keyword: One-Piece-Bulk-155-JP
meta_title: "One Piece Bulk 155 JP — 150 C/UC + 5 R | Booster Shop"
meta_description: "Bulk 155 для One Piece Card Game: 155 японських карток, із них 150 C/UC та 5 R. Для альбому, обмінів і власних ігрових ідей."
tags: "One Piece Card Game, Bulk 155, японські картки, колекція, обмін, Common, Uncommon, Rare"
category: "One Piece → «Набори та бокси One Piece» (id 68); parent category assigned exactly as the sibling product 77/85 does"
manufacturer: "Bandai (id 12)"
```

Description HTML (body + FAQ, store exactly as is):

```html
<p>У колекції Ван Піс є місце не лише для рідкісних карток. Якщо хочеться більше персонажів та ілюстрацій для альбому, обміну чи власних ігрових задумів, формат Bulk дозволяє поповнити запас без відкриття нових бустерів.</p>
<h2>Які картки всередині</h2>
<p><strong>155 оригінальних карток One Piece Card Game японською мовою: 150 Common/Uncommon (C/UC) та 5 Rare (R).</strong></p>
<p>Картки можуть походити з різних японських сетів, тому наперед визначеного переліку персонажів та ілюстрацій немає. Це набір для поповнення колекції, а не полювання на конкретну дорогу картку чи особливу рідкісність.</p>
<h2>Як картки потрапляють до набору</h2>
<p>Для таких наборів ми використовуємо оригінальні картки з бустерів, які раніше відкрили самі. Це не запечатаний продукт виробника. Паковання обираємо під час комплектації замовлення, орієнтуючись на кількість карток та необхідний захист.</p>
<p>Фотографії допомагають уявити наповнення, але саме у вашому наборі картки можуть бути іншими.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="prod-op-jp-bulk-155">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-op-jp-bulk-155-panel-1" aria-expanded="false" id="bs-faq-prod-op-jp-bulk-155-button-1"><span>Що означають C, UC та R у One Piece Card Game?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-op-jp-bulk-155-button-1" id="bs-faq-prod-op-jp-bulk-155-panel-1"><p>Це позначення рідкісності: C (Common) — звичайні картки, UC (Uncommon) — незвичайні, R (Rare) — рідкісні. Рідкісність за класифікацією гри не дорівнює ціні картки на ринку.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-op-jp-bulk-155-panel-2" aria-expanded="false" id="bs-faq-prod-op-jp-bulk-155-button-2"><span>Чи можуть бути однакові картки в наборі?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-op-jp-bulk-155-button-2" id="bs-faq-prod-op-jp-bulk-155-panel-2"><p>Під час комплектування ми стараємося уникати повторів, але гарантії немає. Якщо випадково трапиться дублікат, це не означає, що набір бракований.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-op-jp-bulk-155-panel-3" aria-expanded="false" id="bs-faq-prod-op-jp-bulk-155-button-3"><span>Чи мають картки професійну оцінку стану?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-op-jp-bulk-155-button-3" id="bs-faq-prod-op-jp-bulk-155-panel-3"><p>Ні. Ми не заявляємо оцінки за шкалами PSA, BGS або CGC і не вказуємо ступінь стану. Це оригінальні картки з раніше відкритих бустерів.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-op-jp-bulk-155-panel-4" aria-expanded="false" id="bs-faq-prod-op-jp-bulk-155-button-4"><span>One Piece Bulk 155 — це фабричний запечатаний набір?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-op-jp-bulk-155-button-4" id="bs-faq-prod-op-jp-bulk-155-panel-4"><p>Ні. Ми самостійно складаємо набори з карток, які дістали з бустерів під час своїх відкриттів. Це не запечатаний товар від Bandai.</p></div>
</div>
</section>
```

Attributes (group 7, language_id 4):

| attribute_id | Name | Value |
|---|---|---|
| 12 | Мова | Японська (Japanese) |
| 13 | Назва сету | Bulk / різні японські сети One Piece Card Game |
| 17 | Стан | Оригінальні картки з розкритих бустерів; ступінь стану не заявляється |
| 18 | Походження товару | Картки з оригінальних бустерів, розкритих магазином |
| 20 | Виробник | Bandai |
| 21 | Тип пакування | Сформований набір карток; паковання підбирається при комплектації замовлення |
| 24 | Додатковий вміст | Немає; спеціальні рідкості не гарантуються |
| NEW | Кількість карток у наборі | 155 |
| NEW | Склад за рідкістю | 150 C/UC + 5 R |

---

## PKM-MIX-BULK-100

```yaml
sku: PKM-MIX-BULK-100
h1: "Набір Pokémon TCG: Bulk Mix 100 — C/U (Мікс видань: JP / EN / KR)"
seo_keyword: Pokemon-Bulk-Mix-100
meta_title: "Pokémon Bulk Mix 100 — JP / EN / KR | Booster Shop"
meta_description: "Pokémon TCG Bulk Mix: 100 оригінальних карток C/U. Можливі японська, англійська й корейська мови без фіксованого співвідношення."
tags: "Pokémon TCG, Bulk Mix, JP EN KR, різні видання, колекційні картки, Common, Uncommon"
category: "Pokémon → «Набори» (id 64); parent category assigned exactly as the sibling product 77/85 does"
manufacturer: "Pokémon (id 11)"
```

Description HTML (body + FAQ, store exactly as is):

```html
<p>Для альбому з картками Покемон зовсім не обов’язково обирати лише одну мову видання. Bulk Mix — варіант для тих, кому цікаві картки різних локалізацій і хто хоче поповнити колекцію або підготувати більше карток для обміну.</p>
<h2>Склад Bulk Mix 100</h2>
<p><strong>100 оригінальних карток Pokémon TCG рідкісностей Common/Uncommon (C/U).</strong></p>
<p>У наборі можуть бути японські, англійські й корейські картки (JP / EN / KR), але їхнє співвідношення щоразу може змінюватися. Так само не закріплено конкретні сети, персонажів або ілюстрації: це добірка для колекції, а не обіцянка окремої особливої чи дорогої картки.</p>
<h2>Як з’являється така добірка</h2>
<p>Ми складаємо Bulk Mix із карток, які самі раніше дістали з оригінальних бустерів. Це не продукція, запечатана виробником. Паковання підбираємо під час комплектування замовлення, щоб урахувати обсяг і захист карток.</p>
<p>На знімках — приклади можливих карток, а не точний склад набору, який отримаєте ви.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion data-bs-faq-id="prod-pkm-mix-bulk-100">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-mix-bulk-100-panel-1" aria-expanded="false" id="bs-faq-prod-pkm-mix-bulk-100-button-1"><span>Чи будуть у Bulk Mix картки всіма трьома мовами?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-mix-bulk-100-button-1" id="bs-faq-prod-pkm-mix-bulk-100-panel-1"><p>Не обов’язково. Можуть трапитися картки японською, англійською або корейською, але ми не фіксуємо частку кожної мови й не обіцяємо присутність усіх трьох в одному наборі.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-mix-bulk-100-panel-2" aria-expanded="false" id="bs-faq-prod-pkm-mix-bulk-100-button-2"><span>Що означає C/U у Pokémon TCG?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-mix-bulk-100-button-2" id="bs-faq-prod-pkm-mix-bulk-100-panel-2"><p>C (Common) — звичайна рідкісність, U (Uncommon) — незвичайна. Це категорії карток у грі, а не оцінка їхньої колекційної чи ринкової вартості.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-mix-bulk-100-panel-3" aria-expanded="false" id="bs-faq-prod-pkm-mix-bulk-100-button-3"><span>Чи трапляються в Pokémon Bulk Mix повтори?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-mix-bulk-100-button-3" id="bs-faq-prod-pkm-mix-bulk-100-panel-3"><p>Під час складання ми прагнемо уникати однакових карток, проте обіцяти цього не можемо. Випадковий дублікат сам по собі не є браком.</p></div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button type="button" class="bs-faq-toggle" data-bs-faq-toggle aria-controls="bs-faq-prod-pkm-mix-bulk-100-panel-4" aria-expanded="false" id="bs-faq-prod-pkm-mix-bulk-100-button-4"><span>Це новий запечатаний набір Pokémon від виробника?</span></button></h3>
<div class="bs-faq-panel" hidden role="region" aria-labelledby="bs-faq-prod-pkm-mix-bulk-100-button-4" id="bs-faq-prod-pkm-mix-bulk-100-panel-4"><p>Ні. Bulk Mix ми формуємо власноруч з оригінальних карток, які раніше вийняли з відкритих бустерів. Це не запечатаний виробником набір.</p></div>
</div>
</section>
```

Attributes (group 7, language_id 4):

| attribute_id | Name | Value |
|---|---|---|
| 12 | Мова | Японська, англійська, корейська (JP / EN / KR) |
| 13 | Назва сету | Bulk Mix / різні сети Pokémon TCG (JP / EN / KR) |
| 17 | Стан | Оригінальні картки з розкритих бустерів; ступінь стану не заявляється |
| 18 | Походження товару | Картки з оригінальних бустерів, розкритих магазином |
| 20 | Виробник | The Pokémon Company |
| 21 | Тип пакування | Сформований набір карток; паковання підбирається при комплектації замовлення |
| 24 | Додатковий вміст | Немає; спеціальні рідкості не гарантуються |
| NEW | Кількість карток у наборі | 100 |
| NEW | Склад за рідкістю | 100 C/U |

---

## Notes for the executor

- Attribute ids are from the 2026-08 backup mapping; `NEW` rows need the two new attributes created first (see the handoff).
- Photos are not delivered yet: no image file names or ALT texts are defined here. The owner supplies ≥3 real photos per card; none may imply a factory-sealed product.
- Weights and dimensions are entered by the owner; do not set them.
- No internal links inside the body (category URLs were not verified).
