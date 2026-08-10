# CONTENT-005 — review round 1 of the ChatGPT starter-deck drafts

Date: 2026-08-10
Reviewer: Claude (chat), `bs-content-qa`
Input: `Booster_Shop_CONTENT-005_One_Piece_Starter_Decks_ST32-ST36.csv` (owner upload, 5 rows,
32 columns), drafted against `handoffs/handoff_CONTENT-005_chatgpt-starter-deck-cards_20260810.md`.

**Verdict: `Return for changes`.** No invented facts were found, but several gameplay claims are not
supported by the sources cited beside them, and the batch is heavily templated.

## Verification actually performed

Fetched and read the official Bandai pages directly:

| Page | Read | What it confirmed |
|---|---|---|
| `www.onepiece-cardgame.com/products/st32.html` | yes | JA name, 2026-07-11, contents, rarity, wave of six |
| `asia-en.onepiece-cardgame.com/products/st32.html` | yes | EN name, contents, rarity, strategy line |
| `www.onepiece-cardgame.com/products/st35.html` | yes | JA name 赤黒, contents, rarity incl. Promo 1, leader line |
| `asia-en.onepiece-cardgame.com/products/st36.html` | yes | EN name incl. `"Captain"`, contents, rarity incl. Promo 2 |
| `asia-en.onepiece-cardgame.com/products/st34.html` | yes | EN name, strategy line. **No contents/rarity block on this page** |
| ST-33 (either language) | **no** | ST-33 claims are not independently verified |
| ST-34 JP page | **no** | ST-34 rarity split not independently verified |

### Confirmed correct, quoted from source

- Release date **2026-07-11** for the whole wave. `2026年7月11日[土]発売` / `AVAILABLE JUL. 11, 2026`.
- Contents, identical on every deck: `構築済みデッキ：カード51枚(全15種)、ドン!!カード：10枚、インデックス3枚` /
  `Constructed Deck x 1 (51 cards)(total 15 types), Leader Card x 1, DON!! Cards x 10, Index x 3`.
- The three index cards are real: `基本ルールインデックス3枚` / `3 Basic Rule index cards`. This was the
  claim I most expected to be a hallucination; it is correct.
- ST-32 rarity `リーダー1種、スーパーレア2種、レア3種、アンコモン3種、コモン6種` — draft matches exactly.
- ST-35 rarity `リーダー1種、スーパーレア2種、レア2種、アンコモン3種、コモン6種、プロモーションカード1種` — draft matches exactly.
- ST-36 rarity `Leader Card x 1, Super Rare x 2, Rare x 3, Uncommon x 3, Common x 4, Promotion Card x 2` — draft matches exactly.
- Official names, all five, character-for-character — including the awkward
  `STARTER DECK -Yellow Eustass"Captain"Kid- [ST-36]`.
- **ST-35 is 赤黒 / Red-Black, not black.** The draft is right and the owner's reading of the box tab
  was half of a two-colour leader.
- ST-32 strategy: `緑単色のバトルが得意な高速デッキ`, `斬属性`, `リーダー「ロロノア・ゾロ」の効果で連続攻撃` — draft supported.
- ST-34 strategy: `A mono-purple future-sight control deck` and `Use Leader Charlotte Katakuri's
  effect to check the top card of your opponent's deck` — draft supported almost word for word.
- Six decks launched simultaneously, ST-31 to ST-36 — draft's `launch_note` is correct.
- Meta lengths: every `qa_meta_title_chars` and `qa_meta_description_chars` value recomputed and
  correct. Longest title 58, longest description 143. Within limits.
- No SKU appears in any proposed SEO URL.
- Promo cards were checked by the drafter and correctly **not** presented as exclusive — `P-090`,
  `P-105`, `P-085`, `P-088` all have other official sources. The word `ексклюзивна` appears nowhere.
- Every deck states its contents are fixed and not random. The brand rule that matters most here is
  respected in all five.

## Issues

| # | Де | Що не так | Категорія | Що має бути |
|---|---|---|---|---|
| 1 | усі 5 | Опис починається з малої літери: `<strong>стартова колода One Piece ST-32 Зоро</strong> — японське видання…`. Ключ вставлений дослівно в позицію першого слова речення | мова | Перебудувати перше речення так, щоб воно починалося з великої літери й читалося природно. Ключ може стояти далі в реченні |
| 2 | усі 5 | Близько 70 % тексту однакове: meta description за одним шаблоном, ідентичний список із 4 пунктів, ідентичний передостанній абзац, ідентичний CTA, шаблонні alt. Унікальний лише другий абзац | повтор | Таблиця атрибутів **має** лишатися однаковою. А прозу треба розвести: різна структура абзаців, різні формулювання спільних фактів, різні alt |
| 3 | ST-35 | «ефект лідера для посилення своїх персонажів **коли на полі є персонаж вартістю 8 або більше**». Офіційна сторінка каже лише `リーダー「サボ」の効果で自分のキャラクターすべてをパワーアップ` — жодної умови | факт | Прибрати умову або дати посилання на текст самої карти-лідера, а не на сторінку товару |
| 4 | ST-36 | «ефект лідера **наприкінці ходу** може зробити **одного з відповідних** персонажів **активним** та надати йому Blocker». Офіційно: `any Character can quickly become a Blocker` — без «наприкінці ходу», без «активним», і не «відповідних», а «будь-якого» | факт | Або цитувати текст карти-лідера з картлиста, або звузити до того, що написано на сторінці товару |
| 5 | ST-35, ST-36 | «персонажів Revolutionary Army» і «персонажів Supernovas» — жодна з цитованих сторінок цих слів не містить. Це висновок із імен карт на промо-зображеннях | факт | Підтвердити картлистом або прибрати |
| 6 | ST-33 | Твердження «фокус на персонажах Navy», «скидання карт може перетворюватися на добір», «ефекти колоди допомагають стримувати поле суперника» я **не перевіряв** — сторінку ST-33 не відкривав | факт | Перевірити на рев'ю раунду 2 разом із виправленнями |
| 7 | ST-34 | Розподіл рідкостей (Promo 1) не перевірений напряму: англійська сторінка ST-34 не має блоку `PRODUCT DETAILS`, японську я не відкривав. Сума 15 сходиться, патерн збігається з рештою | факт | Перевірити на JP-сторінці в раунді 2 |
| 8 | усі 5 | `51 картка (50 карт основної колоди + 1 карта-лідер)` — прийнято, але джерела формулюють по-різному: японська сторінка каже `カード51枚(全15種)` (лідер уже всередині), англійська перелічує `51 cards` **і окремо** `Leader Card x 1`, а пояснювач обома мовами каже `50-card deck, 1 Leader card` | факт | Лишити як є — це єдине прочитання, що узгоджує всі три. Але власник має знати про розбіжність, якщо покупець спитає |
| 9 | усі 5 | Внутрішні посилання не ведуть на сторінку бокса OP-16, хоча вона жива й це найближчий супутній товар | структура | Додати посилання на OP-16 як четверте; слаг підставляє Claude Code на етапі патча |
| 10 | усі 5 | SEO URL з новим сегментом `Starter-Deck` (`One-Piece-Starter-Deck-ST-32-Roronoa-Zoro`). Живий канон — `One-Piece-Boosters-OP-11`, `One-Piece-Mystery-Box` | структура | Прийнятно і послідовно, але фіксується остаточно на етапі патча, а не в чернетці |

## Потрібні дані

Нічого від власника. Усе, чого бракує, — це доперевірка самим ChatGPT у раунді 2: тексти карт-лідерів
ST-33, ST-35, ST-36 з картлиста, і японська сторінка ST-34 для рідкостей.

## Побічне спостереження, не дефект

Хвиля містить шість колод: `ST-31 赤 モンキー・D・ルフィ` куплена не була. Якщо в лоті її не було свідомо —
питань немає; якщо ні, це очевидний кандидат на дозакупівлю, бо Луффі в наборі для початківців
продається сам собою.

## Що НЕ входило в це рев'ю

JSON-LD і Merchant-фід — чернетка їх не містить, за задумом. Вони проходять
`bs-merchant-schema-qa` на етапі патча. Питання канонікалів, сайтмапа й редиректів — `bs-seo-risk-gate`
там само.
