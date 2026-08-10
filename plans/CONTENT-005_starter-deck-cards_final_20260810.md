# CONTENT-005 — final product copy, five One Piece starter decks (round 2)

Date: 2026-08-10 | Task: `CONTENT-005` | Notion: `3b86bf20-bdb4-81d6-acad-dc7d32b55500`

**This file is the source of truth for the publication patch.** Round 1 was drafted by ChatGPT and
reviewed in `diagnostics/CONTENT-005_chatgpt-draft-review-round1_20260810.md`; round 2 below was
edited by Claude on owner instruction 2026-08-10.

> **Independence note, recorded deliberately.** Claude both edited and verified this round, so the
> usual separation between author and reviewer does not apply here. The owner is the remaining gate.
> Every factual claim is quoted from the official Bandai page in §1 and can be re-checked in a minute.

## §1 — Verified facts and their source

All five official product pages were fetched and read on 2026-08-10:
`www.onepiece-cardgame.com/products/st32.html` … `st36.html`, plus the English mirrors
`asia-en.onepiece-cardgame.com/products/st32.html`, `st34.html`, `st36.html`.

Common to all five, quoted:

- Release date `2026年7月11日[土]発売` / `AVAILABLE JUL. 11, 2026`.
- Contents `構築済みデッキ：カード51枚(全15種)、ドン!!カード：10枚、インデックス3枚` /
  `Constructed Deck x 1 (51 cards)(total 15 types), Leader Card x 1, DON!! Cards x 10, Index x 3`.
- The 51 includes the leader: the explainer on the same pages says
  `構築済み50枚デッキとリーダーカード1枚` / `a preconstructed 50-card deck, 1 Leader card`.
- Publisher MSRP `550円(税込)` — **not used in the copy**, kept here only as provenance.
- Six decks, ST-31 to ST-36, launched together: `6色の新スタートデッキが同時発売！`.
- Age marking `対象年齢9才以上` — 9+, read from the owner's photograph of the physical boxes.

Per deck:

| SKU | Official JA | Official EN | Colour | Rarity split (verified verbatim) |
|---|---|---|---|---|
| `OP-JP-ST32-STD` | スタートデッキ 緑 ロロノア・ゾロ【ST-32】 | STARTER DECK -Green Roronoa Zoro- [ST-32] | 緑 green | Leader 1, SR 2, R 3, UC 3, C 6 |
| `OP-JP-ST33-STD` | スタートデッキ 青 クザン【ST-33】 | STARTER DECK -Blue Kuzan- [ST-33] | 青 blue | Leader 1, SR 2, R 3, UC 1, C 8 |
| `OP-JP-ST34-STD` | スタートデッキ 紫 シャーロット・カタクリ【ST-34】 | STARTER DECK -Purple Charlotte Katakuri- [ST-34] | 紫 purple | Leader 1, SR 2, R 3, UC 3, C 5, **Promo 1** |
| `OP-JP-ST35-STD` | スタートデッキ 赤黒 サボ【ST-35】 | STARTER DECK -Red/Black Sabo- [ST-35] | 赤黒 red-black | Leader 1, SR 2, R 2, UC 3, C 6, **Promo 1** |
| `OP-JP-ST36-STD` | スタートデッキ 黄 ユースタス・キッド【ST-36】 | STARTER DECK -Yellow Eustass"Captain"Kid- [ST-36] | 黄 yellow | Leader 1, SR 2, R 3, UC 3, C 4, **Promo 2** |

Strategy lines, quoted, and used only to the extent they say:

- ST-32 `緑単色のバトルが得意な高速デッキ！` · `斬属性の強力なキャラクターとともに` ·
  `リーダー「ロロノア・ゾロ」の効果で連続攻撃をしかけよう！`
- ST-33 `青単色のコントロールデッキ！` · `特徴≪海軍≫のキャラクターのパワーと効果で相手の場を制圧！` ·
  `リーダー「クザン」の効果で手札をガンガン入れ替えながら盤面を制圧！手札を捨てるリスクをドローのメリットに変換できる！`
- ST-34 `紫単色の未来予知コントロールデッキ！` · `見聞色の覇気で相手のデッキの上を予知！` ·
  `リーダー「シャーロット・カタクリ」の効果で相手のデッキの上を確認して優位に動こう！`
- ST-35 `赤黒色の武闘派中速デッキ！` · `全体強化と超展開！` ·
  `リーダー「サボ」の効果で自分のキャラクターすべてをパワーアップ！`
- ST-36 `A midrange deck that excels in both offense and defense!` ·
  `Thanks to the Leader effect, any Character can quickly become a Blocker!`

### Removed in round 2 because no source carried them

- ST-35: the condition "коли на полі є персонаж вартістю 8 або більше" — the official page states an
  unconditional buff on all your characters.
- ST-35: "персонажів Revolutionary Army" — inferred from card names on promo images, never stated.
- ST-36: "наприкінці ходу", "зробити активним", and the narrowing to "відповідних персонажів" — the
  official line is that **any** character can quickly become a Blocker.
- ST-36: "персонажів Supernovas" — same problem as Revolutionary Army.

Promo cards are **not** described as exclusive anywhere: `P-090`, `P-105`, `P-085` and `P-088` all
have other official sources. Round 1 established this and round 2 keeps it, now stated openly in the
copy as an honesty point rather than omitted.

## §1b — FAQ accordion: the rule, now proven

The live `bs-faq.js` was extracted from the owner's cPanel backup
(`homedir/public_html/catalog/view/javascript/bs-faq.js`, 11 367 bytes) and read on 2026-08-10.

Its canonical path, quoted from the source:

```
var canonical = root.querySelector('.bs-faq-accordion');
var nodes = canonical.querySelectorAll('.bs-faq-item');
var qEl = nodes[i].querySelector('.bs-faq-q, .bs-faq-toggle, h4, h5, summary, strong');
var aEl = nodes[i].querySelector('.bs-faq-a, .bs-faq-answer, .bs-faq-panel, p, div');
```

**So the rule is: write the canonical `.bs-faq-accordion` markup into the description.** The script
then reads it directly through `.bs-faq-toggle` / `.bs-faq-panel` and rebuilds it into the styled
accordion. The loose formats (`<h4>`, `<strong>?</strong>`, `<dl><dt>`) are only fallbacks for pages
where the copy was written sloppily — new pages must not rely on them.

This also settles the open question from the round-1 review. A plain-text read of the live OP-15 page
showed the four FAQ questions with no answers between them; that is simply because the answers sit in
`<div class="bs-faq-panel" hidden>` and a text extractor skips hidden nodes. **The accordion is not
broken.** No defect to report.

The markup below reproduces exactly the shape the owner supplied from two live product cards
(`prod-80` and `prod-mega-symphonia-box`): a `<section class="bs-faq-accordion">` carrying
`data-bs-faq-accordion` and a unique `data-bs-faq-id`, an `<h2 class="bs-faq-title">FAQ</h2>`, and one
`<div class="bs-faq-item">` per question with paired `button`/`panel` ids and ARIA wiring.

`data-bs-faq-id` values assigned here: `prod-st32`, `prod-st33`, `prod-st34`, `prod-st35`, `prod-st36`.
They must be unique across the catalogue — the executor confirms no collision before insert.

## §1c — Live page structure this copy has to fit, checked 2026-08-10

Read from the live `OP-JP-OP15-BBX` page (`/product/OnePiece-booster-box-OP15`) and the live OP-16 box
page (`/product/One-Piece-OP-16-The-Time-of-Battle-Booster-Box-JP`, ₴5000, button `Передзамовити`):

- Three tabs: **Опис** (`#tab-description`), **Характеристики** (`#tab-specification`), **Відгуки**
  (`#tab-review`). The attribute table renders in the second tab, so the attribute rows below are
  OpenCart attributes, not description HTML.
- Description section headings are `<h2>`; the FAQ block is last.
- Attribute names confirmed live: `Назва сету`, `Мова`, `Тип пакування`, `Кількість бустерів у боксі`,
  `Кількість карток у бустері`, `Стан`, `Виробник`, `Рік випуску`. `Додатковий вміст` was used by the
  June batch. Manufacturer `Bandai` exists and is linked.
- **Owner decision 2026-08-10: only one new attribute may be created — `Кількість карток у колоді`.**
  `Лідер колоди` and `Вікове маркування` were dropped; the leader and the 9+ marking live in the
  description text instead. The attribute list already has too many entries to keep extending it.

## §2 — Shared rules for the patch

- `availability` = **Передзамовлення** for all five, mirroring the live `OP-JP-OP16-BBX` page.
- Price **₴700.00** each.
- Allowed markup in descriptions: `h2`, `h3`, `p`, `strong`, `ul`, `li`, plus the FAQ accordion block
  exactly as written (`section`, `div`, `button`, `span` with the `bs-faq-*` classes and the ARIA
  attributes). No inline styles, no other classes, no images, no JSON-LD.
- The attribute table is deliberately identical in structure across all five — it is a spec table.
- The fourth internal link points at the live OP-16 booster box page; **Claude Code resolves the real
  slug from the database, it is not written here.**

---

# 1. `OP-JP-ST32-STD` — ST-32 Roronoa Zoro

**SEO URL:** `One-Piece-Starter-Deck-ST-32-Roronoa-Zoro`

**Meta Title:** `Стартова колода One Piece ST-32 Зоро (JP) | Booster Shop`

**Meta Description:** `Зелена стартова колода ST-32 із Зоро: 51 картка, 10 DON!! і 3 індекси. Готова японська колода One Piece Card Game, грати можна одразу. Передзамовлення.`

**Meta Keywords:** `стартова колода One Piece, ST-32, Roronoa Zoro, One Piece Card Game, японське видання, Bandai, зелена колода`

**H1:** `Стартова колода One Piece ST-32 Зоро (Японське видання)`

**Опис — HTML:**

```html
<h2>Зелена швидкісна колода зі Зоро — грати можна одразу</h2>
<p>Bandai зібрала ST-32 під один чіткий стиль: тиснути з перших ходів і не давати суперникові часу. Колода моно-зелена, будується навколо персонажів з атрибутом Slash, а ефект лідера Ророноа Зоро дозволяє атакувати повторно.</p>
<p>Офіційна японська <strong>стартова колода One Piece ST-32 Зоро</strong> має фіксований склад. Це не бустер: випадкових карт усередині немає, усі примірники ST-32 однакові, і ви знаєте вміст ще до покупки.</p>
<h2>Що всередині</h2>
<ul>
<li>51 картка: 50 карт колоди та 1 карта-лідер, усього 15 різних карт</li>
<li>10 карт DON!!</li>
<li>3 картки-індекси з базовими правилами</li>
</ul>
<p>Цього достатньо, щоб сісти й зіграти за офіційними правилами — докуповувати нічого не потрібно. Вікове маркування виробника — 9+. Пізніше колоду можна підсилити картами з бустерів.</p>
<p>ST-32 відкрита для передзамовлення в Booster Shop.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-st32">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st32-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st32-button-1" type="button"><span>Чи можна грати одразу після відкриття?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st32-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-st32-panel-1" role="region">
<p>Так. У коробці 51 картка, 10 карт DON!! і 3 картки-індекси з базовими правилами — цього достатньо для гри за офіційними правилами, докуповувати нічого не треба.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st32-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st32-button-2" type="button"><span>Склад колоди фіксований чи випадковий?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st32-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-st32-panel-2" role="region">
<p>Фіксований. Усі примірники ST-32 мають однаковий вміст. Випадкове наповнення буває в бустерах, у стартовій колоді його немає.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st32-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st32-button-3" type="button"><span>Чим ST-32 відрізняється від інших колод цієї хвилі?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st32-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-st32-panel-3" role="region">
<p>Вона моно-зелена і швидка: будується навколо персонажів з атрибутом Slash, а ефект лідера Ророноа Зоро дозволяє атакувати повторно.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st32-panel-4" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st32-button-4" type="button"><span>Це японське видання?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st32-button-4" class="bs-faq-panel" hidden="" id="bs-faq-prod-st32-panel-4" role="region">
<p>Так. Текст карт японською мовою, це офіційний японський реліз Bandai.</p>
</div>
</div>
</section>
```

**Характеристики (атрибути OpenCart):**

| Атрибут | Значення |
|---|---|
| Назва сету | Стартова колода ST-32 (One Piece Card Game) |
| Мова | Японська (Japanese) |
| Тип пакування | Starter Deck |
| Кількість карток у колоді | 51 (50 карт + 1 лідер), усього 15 різних |
| Стан | Новий, нерозпакований (Sealed) |
| Виробник | Bandai |
| Рік випуску | 2026 |
| Додатковий вміст | 10 карт DON!!, 3 картки-індекси |

**Alt (фото спереду):** `Стартова колода One Piece Card Game ST-32 із Ророноа Зоро, японське видання — коробка спереду`
**Alt (фото ззаду):** `Зворотний бік коробки ST-32 Зоро: перелік вмісту японського видання`

**Внутрішні посилання:** категорія One Piece · ST-33 Кузан · бустери One Piece · бустер-бокс OP-16 (слаг підставляє виконавець)

---

# 2. `OP-JP-ST33-STD` — ST-33 Kuzan

**SEO URL:** `One-Piece-Starter-Deck-ST-33-Kuzan`

**Meta Title:** `Стартова колода One Piece ST-33 Кузан (JP) | Booster Shop`

**Meta Description:** `Синя контрольна колода ST-33 із Кузаном: 51 картка, 10 DON!! і 3 індекси. Японське видання One Piece Card Game, готове до гри. Передзамовлення.`

**Meta Keywords:** `стартова колода One Piece, ST-33, Kuzan, Кузан, One Piece Card Game, японське видання, синя колода`

**H1:** `Стартова колода One Piece ST-33 Кузан (Японське видання)`

**Опис — HTML:**

```html
<h2>Синій контроль: тримати поле, а не бігти вперед</h2>
<p>ST-33 грає інакше, ніж агресивні колоди. Вона моно-синя й контрольна: персонажі з характеристикою «Морський дозор» тиснуть на поле суперника силою та ефектами, а лідер Кузан дозволяє активно міняти руку — ризик скинути карту перетворюється на можливість добрати.</p>
<p>Такий стиль підійде тому, хто любить не поспішати й вигравати рахунком, а не темпом. Офіційна японська <strong>стартова колода One Piece ST-33 Кузан</strong> має незмінний склад: у кожному примірнику ті самі карти, жодної випадковості.</p>
<ul>
<li>51 картка: 50 карт колоди та 1 карта-лідер, усього 15 різних карт</li>
<li>10 карт DON!!</li>
<li>3 картки-індекси з базовими правилами</li>
</ul>
<p>Комплект повний — сідайте й грайте за офіційними правилами без жодних докупівель. Вікове маркування виробника — 9+.</p>
<p>ST-33 відкрита для передзамовлення в Booster Shop.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-st33">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st33-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st33-button-1" type="button"><span>Скільки карт у колоді?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st33-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-st33-panel-1" role="region">
<p>51 картка: 50 карт колоди та 1 карта-лідер, усього 15 різних карт. Окремо йдуть 10 карт DON!! і 3 картки-індекси.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st33-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st33-button-2" type="button"><span>Чи підійде ST-33 новачкові?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st33-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-st33-panel-2" role="region">
<p>Так, колода зібрана й готова до гри. Але стиль у неї контрольний: вона нагороджує терпіння більше, ніж швидкий натиск.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st33-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st33-button-3" type="button"><span>Що робить ефект лідера Кузана?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st33-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-st33-panel-3" role="region">
<p>Він дозволяє активно оновлювати руку — скидання карт перетворюється на можливість добрати нові.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st33-panel-4" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st33-button-4" type="button"><span>Чи потрібні додаткові карти, щоб грати?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st33-button-4" class="bs-faq-panel" hidden="" id="bs-faq-prod-st33-panel-4" role="region">
<p>Ні. Комплект повний. Пізніше колоду можна підсилити картами з бустерів, але це вже за бажанням.</p>
</div>
</div>
</section>
```

**Характеристики (атрибути OpenCart):**

| Атрибут | Значення |
|---|---|
| Назва сету | Стартова колода ST-33 (One Piece Card Game) |
| Мова | Японська (Japanese) |
| Тип пакування | Starter Deck |
| Кількість карток у колоді | 51 (50 карт + 1 лідер), усього 15 різних |
| Стан | Новий, нерозпакований (Sealed) |
| Виробник | Bandai |
| Рік випуску | 2026 |
| Додатковий вміст | 10 карт DON!!, 3 картки-індекси |

**Alt (фото спереду):** `Стартова колода One Piece Card Game ST-33 із Кузаном, японське видання — коробка спереду`
**Alt (фото ззаду):** `Зворотний бік коробки ST-33 Кузан: перелік вмісту японського видання`

**Внутрішні посилання:** категорія One Piece · ST-34 Катакурі · бустери One Piece · бустер-бокс OP-16 (слаг підставляє виконавець)

---

# 3. `OP-JP-ST34-STD` — ST-34 Charlotte Katakuri

**SEO URL:** `One-Piece-Starter-Deck-ST-34-Charlotte-Katakuri`

**Meta Title:** `Стартова колода One Piece ST-34 Катакурі (JP) | Booster Shop`

**Meta Description:** `Фіолетова колода ST-34 із Катакурі: лідер бачить верхню карту суперника. 51 картка, 10 DON!!, 3 індекси, японське видання. Передзамовлення.`

**Meta Keywords:** `стартова колода One Piece, ST-34, Charlotte Katakuri, Катакурі, One Piece Card Game, японське видання, фіолетова колода`

**H1:** `Стартова колода One Piece ST-34 Катакурі (Японське видання)`

**Опис — HTML:**

```html
<h2>Бачити хід суперника наперед</h2>
<p>Уся ST-34 побудована навколо однієї ідеї — передбачення. Ефект лідера Шарлотти Катакурі дозволяє подивитися верхню карту колоди суперника й діяти, знаючи більше за нього. Колода моно-фіолетова й контрольна.</p>
<p>Офіційна японська <strong>стартова колода One Piece ST-34 Катакурі</strong> продається зібраною і з фіксованим складом — усі примірники однакові, випадкових карт немає.</p>
<h2>Що всередині</h2>
<ul>
<li>51 картка: 50 карт колоди та 1 карта-лідер, усього 15 різних карт</li>
<li>10 карт DON!!</li>
<li>3 картки-індекси з базовими правилами</li>
</ul>
<p>Серед 15 різних карт одна має позначку Promo. Bandai не подає її як ексклюзив колоди: ця сама карта доступна й з інших офіційних джерел, тому ми не називаємо її унікальною. Вікове маркування виробника — 9+.</p>
<p>ST-34 відкрита для передзамовлення в Booster Shop.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-st34">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st34-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st34-button-1" type="button"><span>Що означає «передбачення» в цій колоді?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st34-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-st34-panel-1" role="region">
<p>Ефект лідера Шарлотти Катакурі дозволяє подивитися верхню карту колоди суперника. Ви ухвалюєте рішення, знаючи більше за нього.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st34-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st34-button-2" type="button"><span>Що таке карта з позначкою Promo і чи вона ексклюзивна?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st34-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-st34-panel-2" role="region">
<p>Одна з 15 різних карт колоди має позначку Promo. Ексклюзивною вона не є: ця сама карта доступна й з інших офіційних джерел, тому ми не називаємо її унікальною для ST-34.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st34-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st34-button-3" type="button"><span>Скільки різних карт у колоді?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st34-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-st34-panel-3" role="region">
<p>15 різних карт, усього 51 картка разом із лідером. Частина карт повторюється — це нормально для зібраної колоди.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st34-panel-4" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st34-button-4" type="button"><span>Якою мовою карти?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st34-button-4" class="bs-faq-panel" hidden="" id="bs-faq-prod-st34-panel-4" role="region">
<p>Японською. Це офіційне японське видання Bandai.</p>
</div>
</div>
</section>
```

**Характеристики (атрибути OpenCart):**

| Атрибут | Значення |
|---|---|
| Назва сету | Стартова колода ST-34 (One Piece Card Game) |
| Мова | Японська (Japanese) |
| Тип пакування | Starter Deck |
| Кількість карток у колоді | 51 (50 карт + 1 лідер), усього 15 різних |
| Стан | Новий, нерозпакований (Sealed) |
| Виробник | Bandai |
| Рік випуску | 2026 |
| Додатковий вміст | 10 карт DON!!, 3 картки-індекси, 1 карта Promo |

**Alt (фото спереду):** `Стартова колода One Piece Card Game ST-34 із Шарлоттою Катакурі, японське видання — коробка спереду`
**Alt (фото ззаду):** `Зворотний бік коробки ST-34 Катакурі: перелік вмісту японського видання`

**Внутрішні посилання:** категорія One Piece · ST-35 Сабо · бустери One Piece · бустер-бокс OP-16 (слаг підставляє виконавець)

---

# 4. `OP-JP-ST35-STD` — ST-35 Sabo

**SEO URL:** `One-Piece-Starter-Deck-ST-35-Sabo`

**Meta Title:** `Стартова колода One Piece ST-35 Сабо (JP) | Booster Shop`

**Meta Description:** `Червоно-чорна колода ST-35 із Сабо: лідер підсилює всіх ваших персонажів. 51 картка, 10 DON!!, 3 індекси, японське видання. Передзамовлення.`

**Meta Keywords:** `стартова колода One Piece, ST-35, Sabo, Сабо, One Piece Card Game, японське видання, червоно-чорна колода`

**H1:** `Стартова колода One Piece ST-35 Сабо (Японське видання)`

**Опис — HTML:**

```html
<h2>Двоколірна колода: червоний і чорний разом</h2>
<p>ST-35 — єдина в цій хвилі двоколірна: її лідер Сабо поєднує червоний і чорний. Темп середній, ставка на бійців і на широке поле, а ефект лідера підсилює всіх ваших персонажів одразу.</p>
<p>Офіційна японська <strong>стартова колода One Piece ST-35 Сабо</strong> має незмінний склад. Усередині немає випадкових карт — це зібрана колода, а не бустер.</p>
<ul>
<li>51 картка: 50 карт колоди та 1 карта-лідер, усього 15 різних карт</li>
<li>10 карт DON!!</li>
<li>3 картки-індекси з базовими правилами</li>
</ul>
<p>Серед 15 різних карт одна має позначку Promo. Вона доступна й з інших офіційних джерел, тому ексклюзивом колоди ми її не називаємо.</p>
<p>Грати можна одразу після відкриття, за офіційними правилами й без докупівель. Вікове маркування виробника — 9+.</p>
<p>ST-35 відкрита для передзамовлення в Booster Shop.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-st35">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st35-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st35-button-1" type="button"><span>Чому ST-35 двоколірна?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st35-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-st35-panel-1" role="region">
<p>Її лідер Сабо поєднує червоний і чорний кольори. У цій хвилі стартових колод це єдиний двоколірний лідер — решта моноколірні.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st35-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st35-button-2" type="button"><span>Що дає ефект лідера Сабо?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st35-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-st35-panel-2" role="region">
<p>Він підсилює ваших персонажів. Офіційний опис колоди формулює це як загальне підсилення поля, без додаткових умов.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st35-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st35-button-3" type="button"><span>Чи є в колоді Promo-карта?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st35-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-st35-panel-3" role="region">
<p>Так, одна з 15 різних карт має позначку Promo. Вона доступна й з інших офіційних джерел, тому ексклюзивом саме цієї колоди не є.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st35-panel-4" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st35-button-4" type="button"><span>Чи можна грати одразу?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st35-button-4" class="bs-faq-panel" hidden="" id="bs-faq-prod-st35-panel-4" role="region">
<p>Так. 51 картка, 10 карт DON!! і 3 картки-індекси — повний комплект для гри за офіційними правилами.</p>
</div>
</div>
</section>
```

**Характеристики (атрибути OpenCart):**

| Атрибут | Значення |
|---|---|
| Назва сету | Стартова колода ST-35 (One Piece Card Game) |
| Мова | Японська (Japanese) |
| Тип пакування | Starter Deck |
| Кількість карток у колоді | 51 (50 карт + 1 лідер), усього 15 різних |
| Стан | Новий, нерозпакований (Sealed) |
| Виробник | Bandai |
| Рік випуску | 2026 |
| Додатковий вміст | 10 карт DON!!, 3 картки-індекси, 1 карта Promo |

**Alt (фото спереду):** `Стартова колода One Piece Card Game ST-35 із Сабо, японське видання — коробка спереду`
**Alt (фото ззаду):** `Зворотний бік коробки ST-35 Сабо: перелік вмісту японського видання`

**Внутрішні посилання:** категорія One Piece · ST-36 Кід · бустери One Piece · бустер-бокс OP-16 (слаг підставляє виконавець)

---

# 5. `OP-JP-ST36-STD` — ST-36 Eustass Kid

**SEO URL:** `One-Piece-Starter-Deck-ST-36-Eustass-Kid`

**Meta Title:** `Стартова колода One Piece ST-36 Кід (JP) | Booster Shop`

**Meta Description:** `Жовта колода ST-36 із Юстассом Кідом: атака й захист водночас, персонаж швидко стає блокером. 51 картка, 10 DON!!, 3 індекси. Передзамовлення.`

**Meta Keywords:** `стартова колода One Piece, ST-36, Eustass Kid, Кід, One Piece Card Game, японське видання, жовта колода`

**H1:** `Стартова колода One Piece ST-36 Кід (Японське видання)`

**Опис — HTML:**

```html
<h2>Жовта колода, яка однаково добре б'є і тримає удар</h2>
<p>ST-36 — колода середнього темпу з ухилом і в атаку, і в захист. Завдяки ефекту лідера Юстасса Кіда персонаж швидко отримує Blocker, тобто може прийняти атаку на себе. Це дає час перехопити ініціативу й зламати план суперника.</p>
<p>Офіційна японська <strong>стартова колода One Piece ST-36 Кід</strong> зібрана заздалегідь, склад однаковий у кожному примірнику — жодних випадкових карт.</p>
<h2>Що всередині</h2>
<ul>
<li>51 картка: 50 карт колоди та 1 карта-лідер, усього 15 різних карт</li>
<li>10 карт DON!!</li>
<li>3 картки-індекси з базовими правилами</li>
</ul>
<p>Серед 15 різних карт дві мають позначку Promo. Обидві доступні й з інших офіційних джерел, тому ексклюзивом колоди ми їх не називаємо. Вікове маркування виробника — 9+.</p>
<p>ST-36 відкрита для передзамовлення в Booster Shop.</p>
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-st36">
<h2 class="bs-faq-title">FAQ</h2>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st36-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st36-button-1" type="button"><span>Чим ST-36 відрізняється від суто агресивних колод?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st36-button-1" class="bs-faq-panel" hidden="" id="bs-faq-prod-st36-panel-1" role="region">
<p>Вона грає в обидва боки: тисне, але й уміє тримати удар. Офіційний опис називає її колодою середнього темпу, сильною і в атаці, і в захисті.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st36-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st36-button-2" type="button"><span>Що таке Blocker?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st36-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-st36-panel-2" role="region">
<p>Це властивість, завдяки якій персонаж може прийняти атаку на себе. За офіційним описом ST-36, ефект лідера Юстасса Кіда дозволяє персонажеві швидко стати блокером.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st36-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st36-button-3" type="button"><span>Скільки Promo-карт у колоді?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st36-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-st36-panel-3" role="region">
<p>Дві з 15 різних карт мають позначку Promo. Обидві доступні й з інших офіційних джерел, тому ексклюзивом колоди вони не є.</p>
</div>
</div>
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-st36-panel-4" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-st36-button-4" type="button"><span>Що входить у комплект?</span></button></h3>
<div aria-labelledby="bs-faq-prod-st36-button-4" class="bs-faq-panel" hidden="" id="bs-faq-prod-st36-panel-4" role="region">
<p>51 картка (50 карт колоди та 1 карта-лідер), 10 карт DON!! і 3 картки-індекси з базовими правилами.</p>
</div>
</div>
</section>
```

**Характеристики (атрибути OpenCart):**

| Атрибут | Значення |
|---|---|
| Назва сету | Стартова колода ST-36 (One Piece Card Game) |
| Мова | Японська (Japanese) |
| Тип пакування | Starter Deck |
| Кількість карток у колоді | 51 (50 карт + 1 лідер), усього 15 різних |
| Стан | Новий, нерозпакований (Sealed) |
| Виробник | Bandai |
| Рік випуску | 2026 |
| Додатковий вміст | 10 карт DON!!, 3 картки-індекси, 2 карти Promo |

**Alt (фото спереду):** `Стартова колода One Piece Card Game ST-36 із Юстассом Кідом, японське видання — коробка спереду`
**Alt (фото ззаду):** `Зворотний бік коробки ST-36 Кід: перелік вмісту японського видання`

**Внутрішні посилання:** категорія One Piece · ST-32 Зоро · бустери One Piece · бустер-бокс OP-16 (слаг підставляє виконавець)

---

## §3 — Owner note, not part of the copy

The wave contains six decks; `ST-31 赤 モンキー・D・ルフィ` (Monkey D. Luffy, red) was not in lot
`yskh293`. If that was not deliberate, it is the obvious next purchase — a Luffy starter deck sells
itself to beginners.
