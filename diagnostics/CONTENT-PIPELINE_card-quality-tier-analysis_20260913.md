# Owner quality tiers vs. measurable card features — analysis

**Date:** 2026-09-13
**Input:** owner archive `Вибірка сторінок товарів по якості власника без урахування СЕО оптимізації.zip`
— 52 saved live product pages, sorted by the owner into four folders:
Якісні (16), Прийнятні (15), Слабкі (11), Провальні (10).
**Status:** pre-roadmap. No Roadmap ID assigned yet. Read-only analysis; nothing on the
site, in the CRM or in Notion was changed.
**Purpose:** establish whether the owner's four tiers can serve as a labelled training /
regression set for the Booster Shop content pipeline (handoff §19 "Golden examples",
§6 "Канон", §20 "Контроль якості системи").

---

## 1. Headline result

**The four tiers are not separable by any measurable feature of the card text.**

Every structural and content metric measured — body word count, h2/h3/p/ul/li/strong counts,
FAQ item count, FAQ word count, attribute count, meta title length, meta description length,
gallery image count, and density of verifiable facts (dates, set codes, card numbers, pull
rates) — either fails to separate the tiers at all, or separates them only through a
product-type confound.

Consequence for the pipeline: **this archive cannot be used as-is as a golden set or as a
regression suite.** A checker trained or calibrated on these labels would learn noise. The
labels need a second pass with explicit, separated axes before they carry signal.

## 2. Method

Each saved page parsed with BeautifulSoup/lxml. The description tab (`#tab-description`) was
split into body and FAQ at `<section|div class="bs-faq-accordion"` — the split documented in
`project_live_card_calibrator`, without which legacy FAQ markup counts as body. Attributes
read from `#tab-specification`. Gallery counted from `.bs-product__gallery`
(`.bs-product__main-img a` + `.bs-product__thumbs a`). JSON-LD parsed from all
`application/ld+json` blocks.

Product class derived from the attribute set, not the folder:
- `sealed` (n=29) — has `Назва сету`
- `3D-P` (n=17) — has `Спосіб виготовлення` / `Рухомі елементи`
- `accessory` (n=6) — neither

`fact_density` = (date mentions + set/card codes + pull-rate expressions) / body words × 100.

## 3. The product-type confound

Tier composition is strongly tied to product class, so any raw tier-to-tier comparison is
really a comparison between sealed cards and 3D-printed cards:

| Class | Якісні | Прийнятні | Слабкі | Провальні |
|---|---:|---:|---:|---:|
| sealed | 12 | 10 | 4 | 3 |
| 3D-P | 3 | 3 | 5 | 6 |
| accessory | 1 | 2 | 2 | 1 |

Raw (uncontrolled) medians therefore show a clean-looking gradient that is mostly the
sealed/3D-P split, not a quality gradient: body words 298 / 250 / 138 / 135, h2 4 / 4 / 1 / 1.

## 4. Within-class medians (confound removed)

### sealed (n=29)

| metric | Якісні (12) | Прийнятні (10) | Слабкі (4) | Провальні (3) |
|---|---:|---:|---:|---:|
| body words | 303 | 315 | 217 | 158 |
| h2 | 4 | 4 | 4 | 3 |
| p | 6.5 | 5 | 5 | 3 |
| ul | 1 | 1.5 | 1 | 2 |
| strong | 12.5 | 9.5 | 10 | 4 |
| FAQ items | 5 | 5 | 4.5 | 4 |
| attributes | 9 | 9 | 9 | 9 |
| gallery images | 2 | 1.5 | 1 | 2 |

Only length separates, and only at the Провальні end (n=3).

### 3D-P (n=17)

| metric | Якісні (3) | Прийнятні (3) | Слабкі (5) | Провальні (6) |
|---|---:|---:|---:|---:|
| body words | 106 | 108 | 89 | 135 |
| h2 | 1 | 1 | 1 | 1 |
| p | 3 | 3 | 3 | 3 |
| ul | 0 | 0 | 0 | 0 |
| strong | 1 | 1 | 1 | 3 |
| FAQ items | 3 | 2 | 2 | 2.5 |
| attributes | 13 | 13 | 13 | 12 |
| fact density | 0.00 | 1.54 | 2.25 | 1.52 |

**Every structural metric is identical across all four tiers, and the longest cards sit in
Провальні.** Whatever the owner judged in the 3D-P cards, it is not in the description text.

## 5. Fact density does not survive per-card inspection

Aggregated, sealed fact density looks discriminating (1.49 / 1.94 / 0.45 / 0.00). Per card it
collapses — pairs with near-identical scores sit in different tiers, and the single highest-
density Провальні card outscores six Якісні cards:

| fact density | tier | words | card |
|---:|---|---:|---|
| 5.05 | Якісні | 317 | Abyss Eye бустер Pokémon TCG JP |
| 4.66 | Прийнятні | 322 | One Piece OP-13 Carrying On His Will JP |
| 3.07 | **Слабкі** | 228 | OP-16 The Time of Battle бустер One Piece JP |
| 2.30 | **Провальні** | 87 | Бустер Yu-Gi-Oh! OCG Blazing Dominion (JP) |
| 0.49 | **Слабкі** | 205 | Chaos Rising бустер Pokémon TCG EN |
| 0.41 | **Якісні** | 241 | Pokémon Mystery Mix XL JP |
| 0.40 | **Якісні** | 248 | Mega Symphonia бустер Pokémon TCG JP |
| 0.00 | Прийнятні | 312 | Pokémon TCG Outlet Mix бустер JP |
| 0.00 | **Слабкі** | 165 | Yu-Gi-Oh! BEYOND THE BRAVE Booster JP |

The Mega Symphonia (Якісні, 0.40) / Chaos Rising (Слабкі, 0.49) pair is the sharpest case:
same product class, same section template, same FAQ count, comparable length. Read side by
side, the Слабкі card names four specific chase cards in its opening sentence while the
Якісні card opens on generic praise ("сучасний японський сет, який цінують за повернення
Mega-покемонів, колекційний потенціал та рідкісні chase-карти") and carries a stuffed
audience list ("для відкриття бустерів, пошуку рідкісних карт, колекціонування сучасних
японських сетів, подарунка фанату Pokémon TCG та поповнення sealed-колекції"). On text
evidence alone the ranking inverts.

## 6. The one signal that did hold

Language. All 12 Якісні sealed cards and all 10 Прийнятні sealed cards are Japanese-edition
products. Both English-edition sealed cards in the archive (Chaos Rising, Lumiose City Mini
Tin) landed in Слабкі / Провальні. n=2 on the English side, so this is a hypothesis to
confirm, not a finding — but if it holds it means the tier partly encodes *which product it
is*, not how the card is written.

## 7. Structural canon actually in force (all 52 cards)

These are stable across every tier and can be treated as the de facto Canon v0.5 shape.

**Sealed card section template** — present in 19 of 29 sealed cards:
1. `Оригінальний японський sealed-бустер <SET>` — opening block: set line, release context,
   card count, sourcing/unweighed statement
2. `Чому саме <SET>` — collector/player angle
3. `Chase Cards сету` — 17 of 29 cards
4. `Чому купують у Booster Shop` — 20 of 29 cards; a fixed 5-bullet list, near-verbatim:
   тільки оригінальні … / без зважування та перевідбору / акуратне пакування для збереження
   mint condition / швидка відправка по Україні / безкоштовна доставка Новою Поштою від 2000 грн
   Box variants insert `Чого очікувати від боксу` before it.

**3D-P card template** — uniform across all 17: one h2, three paragraphs, no lists,
2–4 FAQ items, 12–13 attributes. Fixed FAQ closer on 13 of 17 cards:
"Що входить у комплект і чи є <X> офіційним товаром <бренд>?"

**Trust vocabulary** (consistent, matches the project's brand constraints): `sealed`,
`unweighed` / `без зважування`, `без сортування, перевідбору чи ручного втручання`,
`box / case sourced`, `mint condition`, `не гарантується`.

**Tone rules observable in the copy:** Ukrainian body text; product, set, rarity and card
names kept in the original Latin/Japanese script; franchise names appear in both scripts
(`One Piece` / `Ван Піс`, `Pokémon` / `Покемон`); `ви`-form; no exclamation marks;
explicit no-guarantee clause wherever randomness is mentioned.

## 8. Site-wide defects visible in all 52 cards

These are uniform and therefore independent of tier — they belong to the pipeline's automatic
validator layer, not to the writing layer.

- **Zero internal links.** Not one description in the archive contains an `<a>`. No
  card→category, card→related-set or card→blog linking exists.
- **No GTIN in Product JSON-LD** on any card.
- **No `aggregateRating` / `review`** on any card — correct per the project's structured-data
  rule (never invent ratings), but it means the Product schema ships without review signals.
- **h1 ≠ meta title on 38 of 52 cards.** The h1 is the descriptive Ukrainian product name
  ("Бустер Pokémon TCG: Abyss Eye (Японське видання)"), the title tag is a shorter
  keyword-led variant ("Abyss Eye бустер Pokémon TCG JP — sealed | Booster Shop"). This looks
  deliberate; it needs to be stated as a canon rule rather than left as a pattern.
- **Meta description** 111–157 chars across the archive, all populated. Within the range the
  calibrator recorded for the wider catalogue (14 of 64 live cards exceed 155, max 236).
- **`meta keywords` present on 48 of 52 cards** — no SEO value, and it publishes the shop's
  keyword targeting.
- **Gallery is thin everywhere:** median 2 images per card, 15 cards have exactly 1. This is
  uniform across tiers and matches the owner's own statement that photos need work on almost
  every card.
- Every card carries the same JSON-LD bundle (Product, Offer, OfferShippingDetails,
  MerchantReturnPolicy, BreadcrumbList, Organization, WebSite) and a canonical link.

## 9. What this means for the pipeline design

1. The owner's tier labels mix at least three axes that the pipeline must score separately:
   text quality, photo quality, and product/assortment appeal. Until they are separated, the
   archive is unusable as a regression set.
2. A structural checker (h2 count, FAQ count, required sections) would pass and fail these
   cards almost at random with respect to the owner's own judgement. Structure gates are
   necessary but they are not a quality signal — they must not be the pipeline's quality gate.
3. The measurable difference that *does* track the reader's experience, on a manual read, is
   specificity: named cards with numbers, dates, set codes, pull rates and prices versus
   generic praise and stuffed audience lists. That is a plausible canon rule and a plausible
   LLM-reviewer check, but it is not what the folders encode.
4. The 3D-P line needs its own quality definition entirely. Its cards are structurally
   identical to each other and the owner still ranks them across all four tiers.

## 10. Full per-card table

| tier | class | card | words | h2 | p | ul | FAQ | attrs | imgs | fact density |
|---|---|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Якісні | 3D-P | Gengar 3D фігурка-конструктор Pokémon | 110 | 1 | 3 | 0 | 4 | 13 | 4 | 0.0 |
| Якісні | 3D-P | Umbreon 3D фігурка-конструктор Pokémon | 104 | 1 | 3 | 0 | 3 | 13 | 3 | 0.0 |
| Якісні | 3D-P | Підставка для картки в акриловому кейсі | 106 | 1 | 3 | 0 | 2 | 13 | 2 | 0.94 |
| Якісні | accessory | Pokémon Ice Glaceon VSTAR Special Card Set JP | 315 | 4 | 8 | 0 | 3 | 3 | 3 | 1.27 |
| Якісні | sealed | Abyss Eye бустер Pokémon TCG JP — sealed | 317 | 4 | 5 | 2 | 5 | 9 | 2 | 5.05 |
| Якісні | sealed | Adventures in the Forgotten Realms бустер MTG JP | 360 | 4 | 5 | 3 | 5 | 9 | 1 | 0.83 |
| Якісні | sealed | Mega Symphonia бустер Pokémon TCG JP — sealed | 248 | 4 | 8 | 1 | 5 | 9 | 2 | 0.4 |
| Якісні | sealed | OP-08 Two Legends бустер One Piece JP — sealed | 304 | 4 | 5 | 2 | 5 | 9 | 1 | 3.95 |
| Якісні | sealed | OP-15 Пригоди на острові богів пак One Piece JP | 266 | 4 | 8 | 1 | 5 | 9 | 3 | 2.63 |
| Якісні | sealed | OP-16 The Time of Battle бокс One Piece JP | 305 | 5 | 7 | 1 | 5 | 8 | 2 | 2.95 |
| Якісні | sealed | One Piece OP-17 Booster Box | 421 | 4 | 11 | 1 | 5 | 7 | 2 | 2.14 |
| Якісні | sealed | Pokémon Mystery Mix XL JP — 7 бустерів + holo | 241 | 4 | 6 | 2 | 7 | 9 | 1 | 0.41 |
| Якісні | sealed | Storm Emeralda Booster M6 JP | 302 | 3 | 7 | 0 | 4 | 9 | 1 | 0.66 |
| Якісні | sealed | Yu-Gi-Oh! BURST PROTOCOL BPRO JP бустер | 328 | 4 | 10 | 0 | 3 | 9 | 2 | 0.61 |
| Якісні | sealed | Бустер Pokémon TCG_ Mega Dream EX (Японське видання) | 294 | 4 | 5 | 2 | 4 | 9 | 1 | 0.68 |
| Якісні | sealed | Стартова колода One Piece ST-33 Кузан (JP) | 223 | 1 | 4 | 1 | 4 | 8 | 2 | 2.24 |
| Прийнятні | 3D-P | Брелок-спінер Ditto (Pokémon) — 3D-друк | 130 | 1 | 3 | 0 | 2 | 11 | 2 | 1.54 |
| Прийнятні | 3D-P | Настільна картина Luffy (One Piece) — 3D-друк | 108 | 1 | 3 | 0 | 2 | 13 | 1 | 2.78 |
| Прийнятні | 3D-P | Підставка для слаба PSA — 3D-друк | 73 | 1 | 2 | 0 | 2 | 13 | 1 | 0.0 |
| Прийнятні | accessory | Протектори для карток 63×89 мм 100 шт — купити в Укр | 73 | 0 | 2 | 0 | 5 | 7 | 1 | 0.0 |
| Прийнятні | accessory | Топлоадери для карток 35PT 25 шт — жорсткі протектор | 92 | 0 | 3 | 0 | 3 | 5 | 1 | 0.0 |
| Прийнятні | sealed | Inferno X бустер Pokémon TCG JP — sealed | 318 | 4 | 5 | 3 | 5 | 9 | 1 | 2.2 |
| Прийнятні | sealed | MEGA Gallade EX Special Set Pokémon JP — blister | 248 | 4 | 4 | 2 | 5 | 9 | 2 | 1.21 |
| Прийнятні | sealed | One Piece OP-13 Carrying On His Will JP | 322 | 3 | 9 | 0 | 3 | 9 | 1 | 4.66 |
| Прийнятні | sealed | Pokémon TCG Outlet Mix бустер JP — sealed | 312 | 4 | 9 | 0 | 6 | 9 | 2 | 0.0 |
| Прийнятні | sealed | Pokémon Terastal Loudbone ex Starter Set JP | 250 | 3 | 5 | 1 | 3 | 8 | 1 | 0.0 |
| Прийнятні | sealed | Quarter Century Art Collection бустер Yu-Gi-Oh! JP | 324 | 4 | 5 | 2 | 5 | 9 | 1 | 2.16 |
| Прийнятні | sealed | The Glory of Team Rocket Booster SV10 | 468 | 4 | 10 | 0 | 5 | 9 | 2 | 1.71 |
| Прийнятні | sealed | Бустер One Piece Card Game EB-03 (Японія) — купити к | 246 | 4 | 8 | 1 | 5 | 9 | 2 | 2.44 |
| Прийнятні | sealed | Бустер Pokémon TCG_ Black Bolt (Японське видання) | 278 | 4 | 5 | 2 | 4 | 9 | 1 | 0.72 |
| Прийнятні | sealed | Дисплей Pokémon TCG_ Ninja Spinner (Японське видання | 371 | 4 | 5 | 3 | 5 | 9 | 2 | 3.5 |
| Слабкі | 3D-P | Брелок Mew (Pokémon) — 3D-друк | 86 | 1 | 3 | 0 | 2 | 13 | 1 | 2.33 |
| Слабкі | 3D-P | Підставка для картки в топлоадері — 3D-друк | 88 | 1 | 2 | 0 | 2 | 13 | 2 | 2.27 |
| Слабкі | 3D-P | Фігурка Nami (One Piece) — 3D-друк | 89 | 1 | 2 | 0 | 2 | 11 | 2 | 2.25 |
| Слабкі | 3D-P | Фігурка Onix (Pokémon) — 3D-друк | 138 | 1 | 3 | 0 | 4 | 13 | 3 | 2.17 |
| Слабкі | 3D-P | Фігурка-клікер Покебол (Pokémon) — 3D-друк | 141 | 1 | 3 | 0 | 2 | 13 | 1 | 0.71 |
| Слабкі | accessory | Акриловий протектор для картки без магніта | 83 | 0 | 3 | 0 | 4 | 5 | 4 | 0.0 |
| Слабкі | accessory | Жовтий альбом для карток на 360 карток | 91 | 0 | 3 | 0 | 4 | 6 | 3 | 0.0 |
| Слабкі | sealed | Chaos Rising бустер Pokémon TCG EN — sealed | 205 | 4 | 5 | 1 | 5 | 10 | 1 | 0.49 |
| Слабкі | sealed | Munikis Zero бокс Pokémon TCG JP — sealed | 242 | 5 | 5 | 1 | 4 | 9 | 3 | 0.41 |
| Слабкі | sealed | OP-16 The Time of Battle бустер One Piece JP | 228 | 4 | 6 | 1 | 5 | 7 | 1 | 3.07 |
| Слабкі | sealed | Yu-Gi-Oh! BEYOND THE BRAVE Booster JP | 165 | 3 | 3 | 0 | 3 | 9 | 1 | 0.0 |
| Провальні | 3D-P | Брелок Going Merry (One Piece) — 3D-друк | 133 | 1 | 3 | 0 | 2 | 12 | 1 | 1.5 |
| Провальні | 3D-P | Картина Luffy (One Piece) — 3D-друк | 137 | 1 | 3 | 0 | 2 | 13 | 1 | 0.73 |
| Провальні | 3D-P | Картина Luffy (One Piece), кругла — 3D-друк | 139 | 1 | 3 | 0 | 2 | 12 | 2 | 2.16 |
| Провальні | 3D-P | Коробка Poké Ball для карток і топлоадерів | 131 | 1 | 3 | 0 | 3 | 14 | 2 | 1.53 |
| Провальні | 3D-P | Фігурка Charizard (Pokémon) — 3D-друк | 128 | 1 | 3 | 0 | 4 | 11 | 2 | 1.56 |
| Провальні | 3D-P | Чаша-покебол для дрібниць (Pokémon) — 3D-друк | 139 | 1 | 3 | 0 | 3 | 12 | 2 | 1.44 |
| Провальні | accessory | Магнітний кейс для карток 35PT — акриловий захист | 58 | 0 | 2 | 0 | 3 | 5 | 2 | 0.0 |
| Провальні | sealed | Lumiose City Mini Tin Salamence Pokémon TCG EN | 158 | 3 | 3 | 2 | 4 | 9 | 2 | 0.0 |
| Провальні | sealed | One Piece Promotion Pack Vol.7 JP — PROMO | 187 | 4 | 6 | 2 | 5 | 8 | 1 | 0.0 |
| Провальні | sealed | Бустер Yu-Gi-Oh! OCG Blazing Dominion (JP) — купити  | 87 | 0 | 3 | 0 | 4 | 9 | 2 | 2.3 |