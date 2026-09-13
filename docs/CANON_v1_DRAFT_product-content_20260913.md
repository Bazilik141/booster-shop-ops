---
name: booster-shop-canon
version: v1-draft
date: 2026-09-13
status: DRAFT — all blocking decisions closed 2026-09-13, Appendix A approved.
        Two non-blocking follow-ups open (§14). Ready to be put in force.
supersedes: 01_CORE_SKILL.md v11.1, 06_3D_SKILL.md v11.1, 08_QA_SKILL.md v11.1 (treated as v0.5)
---

# Booster Shop Content Canon v1 — draft

## 0. What this is and where it came from

Three inputs were merged:

1. **v0.5** — the ChatGPT skills `01_CORE_SKILL.md`, `06_3D_SKILL.md`, `08_QA_SKILL.md`
   (edition 2026-08-29 v11.1). Good principles, weakly enforced: the 52 live cards in the
   owner archive violate them almost uniformly.
2. **Owner re-scoring, 2026-09-13** — free-form judgement of 52 live cards. This is the only
   direct evidence of what "good" means here, and it is what v1 is calibrated against.
3. **The live news pipeline** in `crm/apps-script/Code.gs` (`newsDigest`, `newsAuditTelegramDraft_`,
   `openaiEvaluateEditorialAlignment_`). It already runs the analyse → draft → audit → edit →
   align loop this canon needs, with a working anti-generic validator. v1 reuses its flag
   architecture rather than inventing one.

The previous analysis (`diagnostics/CONTENT-PIPELINE_card-quality-tier-analysis_20260913.md`)
established that no structural metric separates good cards from bad ones. The owner's
re-scoring explains why: **he judges rhetorical function per paragraph, not shape.** Every
rule below follows from that.

---

## 1. The single governing rule

> A paragraph earns its place by doing a job for the buyer. A paragraph that only states
> what the product is made of, how big it is, or how it is constructed has not done a job.

Owner's own words, on the worst cards:

- «нуль інформації про те, навіщо це купити… просто опис характеристик і все»
- «це не опис, це констатація факту… де закриття потреби/болі?»
- «значно ближче до технічного опису чи інструкції до плоскогубців»
- «тупо однаковий тупорилий вступ про те, як побудована картина убиває бажання читати далі»

And on the best:

- «стартовий хук, який задає тон маркетингового тексту, і перетворює подальше перелічення
  характеристик на органічно вмонтований за сенсом текст»
- «характеристики наведені не нудно, а в контексті тон оф войс»

Facts are not the problem and never were. **Unframed facts are the problem.**

## 2. Body composition

### 2.1 Opening

The first sentence is a hook: a reason to keep reading that is specific to this product.
It is never a description of how the object is constructed, and never a restatement of the
heading.

Failing openings from the archive, for reference:
- «Портрет Luffy тут побудований майже повністю з чорних контурів…» — construction-first.
- «Зовні це компактний Poké Ball на чорній основі…» — appearance inventory.
- «Mega Symphonia — сучасний японський сет Pokémon TCG із лінійки Mega Evolution, який
  цінують за повернення Mega-покемонів, колекційний потенціал та рідкісні chase-карти» —
  generic praise; fits any set.

Passing openings:
- «Joey знову ставить усе на ризик — цього разу в BEYOND THE BRAVE» (structure is thin, the
  hook is right)
- «Umbreon kit card легко впізнається ще до того, як починаєш роздивлятися дрібні деталі»
- «Коли хочеться показати PSA-слаб, а не саму підставку»

### 2.1a The heading and the first sentence do different work

A heading introduces the idea; the first sentence develops it. It never restates it in other
words, and it never reuses the heading's own nouns.

Caught in the calibration test: `<h2>Місце, куди нарешті летять ключі, монети й жетони</h2>`
followed by «Ключі, здача, жетони…» — half the opening sentence was the heading again.

### 2.1b An example is used once

The concrete nouns that carry an argument — the objects, the use cases, the comparisons —
belong to one paragraph. Reaching for the same «ключі й монети» two paragraphs later makes the
second paragraph read as a restatement even when its point is new. Pick different examples, or
drop the list and name the property instead.

### 2.2 Every technical fact enters through meaning

A dimension, mass, material, card count or mechanic may appear in body only attached to what
it changes for the buyer. Otherwise it belongs in attributes only.

For 3D products, size may be given as a comparison to an everyday object (pen, phone, palm)
instead of, or alongside, the millimetres.

### 2.3 Length follows content

No minimum and no maximum. Do not extend a card that has nothing further to say — the owner
rejects padding explicitly: «не маємо тягнути кількість водою лише заради цифри»,
«не збираюсь штучно розтягувати описи 3д товарів лише заради об'єму».

Where a card *can* carry 3–4 strong paragraphs instead of 2, it should.
Reference for a well-sized sealed card: OP-15 (266 words body + 5 FAQ).

### 2.4 Lists are a last resort

`<ul>` is allowed only when scanning genuinely beats prose — practically, only inside a
contents/комплектація statement. Two bulleted lists on one card is a defect.

Owner on Ninja Spinner (Прийнятні): «тут все просто, забагато маркованих списків».
On Lumiose City Mini Tin (Провальні): «трошечки тексту і два маркованих списки… в плані
цікавості та візуального вигляду — фігня».

Exception: on a long booster-box card, a marked chase-cards list is permitted because it
visually breaks up the text — owner on OP-17: «текст об'ємний, тому чейз карти з маркуванням
його візуально розділяють і роблять легшим».

## 3. Trust content ("чому купують у Booster Shop")

**Mandatory in meaning. Forbidden as a block.**

The sourcing, sealed-state, unweighed and packing facts must reach the reader, but woven into
the body where they are relevant. No dedicated closing section, no bulleted list of reasons,
no heading named after the shop.

The five-bullet boilerplate that appears verbatim on 20 of 29 live sealed cards is retired:

> тільки оригінальні … / без зважування та перевідбору / акуратне пакування для збереження
> mint condition / швидка відправка по Україні / безкоштовна доставка Новою Поштою від 2000 грн

**A trust statement that argues its own case is worse than none.** Owner on The Glory of Team
Rocket: «траст блок у такому форматі може викликати більше питань і сумнівів, чим його
відсутність. Тут він більше виправдовується або просить повірити на слово.»

Reference for correct handling — Mega Symphonia, where sourcing and the unweighed fact are
already woven into the opening block: «без зважування (описано не як виправдання чи спроба
переконати, а нативно вплетено в текст опису)». On that card the closing bullet block is
redundant and must be removed.

Second reference — Storm Emeralda: «органічне та просте пояснення звідки ці бокси і чому
варто купляти у нас», integrated, no block.

### 3.1 Whose randomness it is

The no-guarantee clause is mandatory wherever randomness is discussed, but its subject matters.
Written flat — «конкретні карти чи рідкісності не гарантуються» — it reads as the shop hedging
its own promise. Written with the cause named — the distribution is the manufacturer's, the
shop neither sets it nor touches it — the same sentence reads as an explanation of how sealed
product works.

Same fact, better frame: «вміст самих бустерів випадковий, як і завжди, залежить від виробника,
тож конкретні карти чи рідкісності не гарантуються».

This is not softening a disclaimer. The legal meaning is unchanged and must stay unchanged; only
the attribution of the randomness is made accurate.

## 4. Chase cards (sealed only)

Mandatory section, **prose not bullets**, with card names woven into sentences.
Reference: Mega Symphonia — owner names it the good example of this block.
Exception for long box cards: see 2.4.

- Card numbers (`OP08-118`, `118/081`) may appear **inside the chase block only**, when they
  read naturally. Nowhere else on the page — «щоб не стало повсюдним спамом з номерів».
- **Market prices are never published.** Retire the `~¥178,000` pattern from Abyss Eye.
- **Pull rates: no numeric rate is published unless the manufacturer states it.** See §4.1.
- When no chase data is confirmed, replace the block with other confirmed set information —
  do not ship an empty or speculative chase section. **The wrapper itself is the first place to
  look.** Japanese boosters print the full rarity architecture on the back: how many types of
  each rarity, and which of them additionally exist in special finishes. That table is
  manufacturer-stated, sits in the shop's own hands for every SKU it sells, and is usually more
  interesting than a third-party chase list — the BLAZING DOMINION card is built on it
  (`work/CANON_v1_TEST_three-rewrites_20260913.md` §5-bis).

### 4.1 Pull rates — resolved policy

Researched 2026-09-13. No source meets a "two independent publications with disclosed
methodology" bar for Japanese sets. The best available (tcgtalk.com, which publishes
case-opening guides) states its own data as *"community estimates aggregated from 1,000+ packs
across three cases plus additional boxes"* with no named contributors, no dates and no
verification. Everything else found is either a shop blog selling the same product, an
English-only calculator with no methodology (pullrates.gg states plainly that its numbers are
estimates from rarity odds), or an affiliate aggregator. Publishing any of it as fact would
breach the shop's own rule against implying guaranteed pulls.

Three tiers, in order:

1. **Manufacturer-stated composition and guarantees** — pack counts, box counts, declared
   guaranteed slots. Publishable as fact, sourced to the manufacturer.
2. **Booster Shop's own opening records** — first-party evidence, the strongest source
   available and the one the canon already privileges. Publishable **qualitatively and
   hedged**, never as a rate. The live Munikis Zero card is the reference for correct wording:
   «За нашими спостереженнями, японський бокс Mega Evolution зазвичай дає орієнтовно одну-дві
   карти рівня Super Rare або вище… Це не офіційна гарантія виробника і не стосується окремого
   паку».
3. **Third-party community aggregates** — internal research context only. They may inform
   which cards to treat as chase. They never reach client copy, as a number or as a claim.

`~1 на 45 боксів` on the Abyss Eye card is tier 3 published as tier 1 and is retired.

If Booster Shop starts logging its own box openings in a structured way, tier 2 becomes a real
asset no competitor can copy. That is a separate proposal, not part of this canon.

## 5. Language

### 5.1 Anglicism ban (client-facing prose)

The following do not appear in body or FAQ prose:

`card pool`, `binder collection`, `random booster`, `promo lineup`, `opening-session` /
`опенінг сесія`, `face-карта`, `Basic Energy`, `Pokémon TCG Live code card`.

General rule: an English term is allowed in prose only when it is the official name of a set,
card, rarity, mechanic or product format, or an SEO entity with no natural Ukrainian form.
Everything else goes into natural Ukrainian.

`Basic Energy` and `Pokémon TCG Live code card` remain valid **attribute** values in
`Додатковий вміст`. In prose they become **`базова енергія`** and
**`код-картка для Pokémon TCG Live`** (owner-approved 2026-09-13).

Reference for the failure mode — Promotion Pack Vol.7: «тупо забагато англіцизмів та
непрородньо побудований текст із грубо вставленими неякісними ключами».

### 5.2 Latin-first entity names, one Cyrillic bridge

Primary form of franchise, set, character and card names is the original Latin/Japanese
spelling, because that is what carries search volume.

**Bridge rule (owner-approved 2026-09-13):** the Cyrillic form appears **exactly once per
card, per entity**, in the client-facing HTML body.

Which entities get a bridge:

- the game or franchise — always;
- the **main subject of the card** — the character or object the product is about;
- nothing else. Chase-card names, set names, rarity codes and secondary characters named
  inside the chase block stay Latin-only.

So a `Брелок Pikachu (Pokémon)` card carries `Покемон` once and `Пікачу` once, and a
`Бустер Pokémon TCG: Abyss Eye` card carries `Покемон` once and nothing else in Cyrillic,
because the subject is a set, not a character — the Darkrai cards named in its chase block
stay Latin.

H1, FAQ, attributes and Meta do not count toward the bridge.

**Exception for 3D products and accessories (owner-approved 2026-09-13):** the Latin-first rule
governs the body, not the product name. A 3D or accessory product name may keep the Cyrillic
form where it already reads better — `Чаша-покебол для дрібниць (Pokémon) — 3D-друк` stays as
it is, and no rename wave is needed. Sealed TCG products keep Latin-first names, because the
set and card names there are the search targets.

Approved forms are in **Appendix A**. A name not in Appendix A gets no bridge until it is
added there — the validator cannot check a transliteration it does not have.

This rule may be revised, but only on an argued proposal with evidence — not on preference.

### 5.3 Voice

Store speaks as `ми`. Buyer is addressed as `ви`. No `ти` in client copy.
No internal vocabulary in client copy: `SKU`, `артикул`, `позиція`, `номенклатура`, `хвиля`.

## 6. FAQ

- FAQ is written after the body and must not restate it. Owner on Mystery Mix XL and Outlet
  Mix — both otherwise praised: «кілька питань із FAQ, які фактично повторюють тіло опису»,
  «FAQ слабуваті, місцями просто повтор тіла опису».
- A question earns a slot by resolving a real pre-purchase friction, explaining a term that
  would be awkward in body, or carrying a required disclosure. Search-shaped phrasing alone
  does not earn a slot.
### 6.1 Quota and mix (owner-approved 2026-09-13)

The range counts **content questions only: 2–4 for 3D products and accessories, 3–5 for
sealed.** An editorial window, never a target.

**The mandatory legal/IP disclosure item has its own slot and does not consume the range.** A
3D card with four content questions plus the disclosure is compliant, not over-length. This is
a change from v0.5, which counted the disclosure inside the 2–4.

**Within the range, split the content questions roughly evenly between two kinds:**

- **search-shaped** — what a person actually types into Google or an AI: «Скільки карт у
  бустері X?», «Коли вийшов X?», «Що таке Overframe?», «Чим OCG відрізняється від TCG?». These
  exist to be found.
- **product-shaped** — the real pre-purchase friction of this specific item: «Фігурка стоїть
  сама, без підставки?», «Звідки у вас поштучні паки?». These exist to be read.

With an odd count, one extra of either kind is fine. A card made entirely of one kind is not:
all-search reads like a keyword robot, all-product is invisible to search.
- **IP disclosure FAQ is mandatory on every own 3D product tied to a franchise** — unofficial,
  Booster Shop is not a licensee, exact contents, items in photos not included. Reworded per
  product, never copy-pasted, legal meaning never weakened. Not required for non-franchise 3D
  accessories (card stands, slab stands).
- Markup contract is unchanged from v0.5 §9.1: `section.bs-faq-accordion`,
  `data-bs-faq-accordion=""`, unique `data-bs-faq-id`, `bs-faq-item` / `h3.bs-faq-question` /
  `button.bs-faq-toggle` / `bs-faq-panel`, question text inside `<span>`, `hidden=""` on every
  panel, two-way id ↔ aria linkage. Legacy `div.bs-faq-accordion` is never emitted.

## 7. Product-type profiles

### 7.1 Sealed booster pack

Sections: hook + set identity and contents → why this set → chase cards (prose).
Trust facts woven in, no closing block.
For Japanese, Korean and Chinese **Pokémon** sets: one natural mention of the verified English
counterpart. Owner calls this a mandatory hook and names Storm Emeralda the reference —
«обов'язковий хук з проведенням аналогії до англомовного відповідника цьому сету». Omit only
when the mapping is genuinely uncertain.

### 7.2 Sealed booster box

As 7.1, plus what a whole box changes versus buying singles, and the factory-seal statement.
The no-guarantee clause is mandatory wherever box outcomes are discussed.
Reference: OP-17 Booster Box.

### 7.3 Fixed / special sets, starter decks, mini tins, promo packs

Do not reuse the booster template. These need their own composition: what it is, what is
inside, and — for a deck — how it plays and who it suits.
Reference: ST-33 Kuzan — «гарний самобутній опис, який не повторює сліпо структуру паків або
боксів». Anti-reference: Promotion Pack Vol.7, Lumiose City Mini Tin.

For a **fixed** pack, the fixed-contents fact must be unmistakable. Never present a fixed pack
as random.

### 7.4 Mystery / outlet products

Explain the unfamiliar format plainly, give the motivation, state honestly what is not
guaranteed. Outlet sourcing stays neutral — never framed as leftovers or as worse pull quality
without evidence. References: Mystery Mix XL, Outlet Mix.

### 7.5 Own 3D-printed products

Shape: **2–4 paragraphs, 2–4 sentences each.**

1. Hook that sets the marketing tone — the object as something a person wants, not an object
   that was manufactured.
2. Middle paragraph(s): what it does, how it is used or displayed, what makes it different
   from the neighbouring model. Size may enter here as an everyday-object comparison.
   **One internal link to a genuinely neighbouring product belongs here** (see §8).
3. Closing paragraph: the 3D-print disclosure in an attractive form — made in-house, and when
   it is not in stock the typical lead time is 1–2 working days.

Owner's own illustration of the target register, against the failing Mew card:

> «Цей маленький Mew брелок завжди буде твоїм вірним другом. Чіпляй його на… А ось на великому
> рюкзаку з цим упізнаваним покемоном може позмагатися [перелінковка на більший брелок чи
> брелок-клікер]»

versus what the live card actually does:

> «цей Mew надрукований у такому форматі, ось його розмір, він може висіти на ключах» —
> «бо це тупо лажа і перелік характеристик».

References: Umbreon and Gengar kit cards. Anti-references: Nami, Onix, Poké Ball clicker,
Charizard, both Luffy pictures, Poké Ball bowl.

Prohibited positioning is unchanged from v0.5 §7: `офіційний`, `ліцензійний`,
`оригінальний товар Pokémon/One Piece`, `у співпраці з`, `сертифікований`, `іграшка`,
`для дітей`, `гарантовано`, `100%`, `унікальний у світі`.

### 7.6 Non-3D accessories (sleeves, toploaders, albums, cases)

Same discipline as 3D: 2 paragraphs when that is all there is, 3–4 when real content exists.
Compatibility, capacity and fit are the buyer questions — answer them as decisions, not as a
table in sentences.

Owner on the sleeves card (Прийнятні): the copy is fine but too short; it should carry a few
more thematic sentences with broad keywords — that these sleeves fit inside toploaders, that
they are thin and suited to good cards that are not expensive, naming an example card.

Anti-references: жовтий альбом на 360 карток, магнітний кейс 35PT.

## 8. Internal links

Every card carries at least one internal link to a genuinely neighbouring product or category.

This is a change of kind, not degree: **all 52 live cards contain zero links in their
descriptions.** v0.5 §16 asked only for anchor suggestions without hrefs, which is why nothing
was ever linked.

v1 requires a real `href`. The pipeline must therefore resolve live URLs from a catalogue
index.

**Resolved 2026-09-13: the index is generated, not maintained.** `ocp5_seo_url` in the site
database holds `product_id → keyword` for all 122 products at `language_id = 4`, and
`ocp5_product_description` holds the names. A generated `catalog-index.json`
(`product_id, SKU, name, slug, category, live URL, status`) is therefore a build artefact
refreshed from the newest backup, with no manual upkeep and no new place for the data to drift
out of sync.

Links are never invented. If a destination cannot be resolved from the index, the card ships
without the link and the gap is reported, not guessed. An index older than the newest backup
is itself a reportable condition.

## 9. Meta and page identity

- **H1** — full descriptive Ukrainian product name, including the edition qualifier:
  `Бустер Pokémon TCG: Abyss Eye (Японське видання)`.
- **Meta Title** — shorter, key-led, ≤63 characters, ending ` | Booster Shop`:
  `Abyss Eye бустер Pokémon TCG JP — sealed | Booster Shop`.
  H1 and Title deliberately differ; both definitions above are owner-approved 2026-09-13.
  The validator checks that H1 ≠ Title, that H1 carries the edition qualifier, and that Title
  ends with the shop suffix and stays within 63 characters including it.
- **Meta Description** — ≤155 characters, product + strongest differentiator, no filler.
- **Meta Keywords** — **removed.** Owner confirmed. Currently populated on 48 of 52 cards,
  where it carries no ranking value and publishes the shop's targeting.
- **GTIN, aggregateRating, review** — never invented. Unchanged.

## 10. Images

- Minimum 3 per card, target 5–6 from different angles.
- The three required shots are **front, back, contents**.
- Real photographs of the real product. AI-generated imagery is for banners, Telegram, blog
  and ads — never for a product card.
- Current state: median 2 images per card, 15 of 52 have exactly one.

---

## 11. Validator flag set

Split the way the news pipeline already splits it. Code answers yes/no; the LLM reviewer
answers "is this any good". The names below mirror `newsAuditTelegramDraft_` so the two
pipelines can share one vocabulary.

### 11.1 Code-checkable — blocking

| flag | condition |
|---|---|
| `missing_required_field` | H1, Title, Meta Description, body or attribute schema missing |
| `title_too_long` | Meta Title > 63 chars |
| `meta_desc_too_long` | Meta Description > 155 chars |
| `meta_keywords_present` | keywords field populated |
| `anglicism_hit` | any term from §5.1 in body or FAQ prose |
| `prohibited_claim` | any term from §7.5 / v0.5 §13 claim list |
| `trust_block_present` | a heading matching `Чому купують` or the retired five-bullet list |
| `list_heavy` | more than one `<ul>` in body (exception: box chase list per §2.4) |
| `card_number_outside_chase` | card-code pattern outside the chase section |
| `market_price_present` | currency + price figure in body or FAQ |
| `no_internal_link` | zero `<a href>` in body |
| `missing_cyrillic_bridge` | a known IP or character name present in Latin with no Cyrillic form anywhere in body |
| `cyrillic_bridge_repeated` | the same Cyrillic form more than once in body |
| `faq_markup_invalid` | any violation of the §6 markup contract |
| `faq_count_out_of_range` | content questions outside 2–4 (3D, accessory) or 3–5 (sealed); the disclosure item is excluded from the count (§6.1) |
| `missing_ip_disclosure_faq` | franchise-tied 3D product without the disclosure item |
| `attribute_schema_mismatch` | attribute names not in the confirmed catalogue schema |
| `image_count_low` | fewer than 3 images |

`attribute_schema_mismatch` is not optional: drafting chats invent attribute names, so the
whole wave is diffed against `ocp5_attribute_description` before any handoff.

### 11.2 LLM-reviewer — blocking

Independent model, separate from the writer. Returns a JSON verdict, not a rewrite.

| flag | what it catches |
|---|---|
| `spec_dump` | a paragraph that lists properties without a buyer job (§1) |
| `weak_hook` | opening describes construction, appearance or is generic praise (§2.1) |
| `generic_copy` | the paragraph would fit any product in the category |
| `trust_defensive` | trust content that justifies itself or asks to be believed (§3) |
| `faq_duplicates_body` | a FAQ item whose answer is already fully in the body (§6) |
| `faq_type_imbalance` | search-shaped and product-shaped counts differ by more than one (§6.1) |
| `heading_paraphrased` | the first sentence restates the heading or reuses its nouns (§2.1a) |
| `example_reused` | the same concrete examples carry an argument in two paragraphs (§2.1b) |
| `template_clone` | same rhetorical sequence as a sibling card with entities swapped |
| `unsupported_claim` | a claim with no confirmed source |
| `missing_no_guarantee` | randomness discussed without the no-guarantee clause |
| `fixed_presented_as_random` | fixed pack described as a random pull |

### 11.3 Scored, not flagged

Reuse the shape already proven in `openaiEvaluateEditorialAlignment_`: integers 0–5 for
`specificity_score`, `hook_strength_score`, `generic_copy_score`, plus a short `reason`.
Thresholds are calibrated against the golden set in §12 before they gate anything.

### 11.4 What must NOT become a gate

Heading counts, paragraph counts, word counts, FAQ counts as a target, `strong` counts.
The tier analysis showed these separate nothing; a gate built on them would pass and fail
cards at random against the owner's own judgement.

---

## 12. Golden set (from the owner's 2026-09-13 re-scoring)

Positive references, with the specific quality each one demonstrates:

| card | demonstrates |
|---|---|
| Storm Emeralda M6 | best sealed reference overall — set flavour, chase woven in, EN-counterpart hook, native trust |
| ST-33 Kuzan | type-specific composition instead of the booster template |
| Mystery Mix XL | making an unclear product understandable and desirable |
| OP-17 Booster Box | box-format reference; chase list justified by length; native trust before FAQ |
| Mega Symphonia | correct chase block; trust woven into the opening |
| Umbreon / Gengar kit cards | 3D reference — hook first, specs carried by tone |
| OP-15 | reference for correct overall size (body + FAQ) |

Negative references, with the defect each one isolates:

| card | isolates |
|---|---|
| Чаша-покебол | pure spec dump, no reason to buy |
| Фігурка Nami | empty copy where the product itself is strong |
| Підставка в топлоадері | statement of fact, no need addressed |
| Картина Luffy (both) | construction-first opening, then a spec list |
| Фігурка Charizard | acceptable opening destroyed by the next two paragraphs |
| Promotion Pack Vol.7 | anglicism overload, unnatural keyword insertion |
| Lumiose City Mini Tin | too little text, two bulleted lists |
| BEYOND THE BRAVE | right voice, too thin |
| Onix / Poké Ball clicker | technical-manual register |
| Ninja Spinner | list overload |
| The Glory of Team Rocket | defensive trust block |

Known limitation: this set is the owner's judgement of **text**, on cards whose photos and
product appeal vary independently. Do not treat it as a joint label for photo or product
quality.

---

## 13. Decision log

All seven decisions closed 2026-09-13.

| # | decision | outcome |
|---|---|---|
| 1 | FAQ range | 2–4 for 3D and accessories, 3–5 for sealed (§6) |
| 2 | `Basic Energy` / `code card` in prose | `базова енергія`, `код-картка для Pokémon TCG Live` (§5.1) |
| 3 | Cyrillic bridge scope | once per entity per card; game + main subject only; forms in Appendix A (§5.2) |
| 4 | Pull-rate sources | no numeric rate unless manufacturer-stated; own openings hedged; third-party never published (§4.1) |
| 5 | Catalogue index | generated from `ocp5_seo_url` + `ocp5_product_description`, not maintained by hand (§8) |
| 6 | H1 vs Meta Title | both defined and validator-checkable (§9) |
| 7 | Retroactivity | new cards only; migration of existing cards is a separate gated wave, ordered by traffic |

**Retroactivity detail.** 20 live cards carry the retired trust block and 48 carry meta
keywords. Rewriting them is a content wave straight against production with no staging, so it
does not ride along with v1. Sequence when it happens: meta keywords first (removal only, zero
copy risk), then trust blocks, then full rewrites ordered by traffic.

## 14. Open — raised by closing the seven

**Closed 2026-09-13 · Starter-deck naming.** The five One Piece starter decks
(ST-32 … ST-36) are being renamed to Latin character names by the owner, by hand, bringing
them in line with §5.2. URL slugs are unaffected — they are already Latin
(`One-Piece-Starter-Deck-ST-32-Roronoa-Zoro`).

**Closed 2026-09-13 · `Poké Ball`.** Takes a Cyrillic bridge like a character (`Покебол`).
In Latin, **`Poké Ball` and `Poke Ball` are both valid spellings** and the validator treats
them as the same entity — neither is an error, and the unaccented form is not a typo to fix.

**OPEN-C · Appendix A coverage.** The table below covers every entity currently in the
catalogue. New products bring new names; each needs its Cyrillic form approved before the
card is written, or the card ships with no bridge for its subject.

**OPEN-D · Accented spellings generally.** The `Poké Ball` / `Poke Ball` ruling raises the same
question for `Pokémon` / `Pokemon`, which the catalogue already writes both ways — `Pokémon` in
product names, `Pokemon` in URL slugs. Extended by default to match: both Latin spellings valid,
treated as one entity by the validator, accented form preferred in prose. Correct this if the
intent was narrower.

---

## Appendix A · Approved Cyrillic bridge forms

Compiled 2026-09-13 from all 122 product names in `ocp5_product_description` (language_id 4)
in `boosters_ocart49.sql.gz`. **Owner-approved 2026-09-13.** Forms already live in the
catalogue are marked `[live]`.

Governing principle for this table: a bridge exists only for a name a Ukrainian buyer would
plausibly type in Cyrillic. For everything else the Cyrillic form adds nothing to search and
reads awkwardly in prose, so **no bridge is written at all** — the name stays Latin-only and
`missing_cyrillic_bridge` does not fire.

### Games

| Latin | Cyrillic |
|---|---|
| Pokémon | Покемон [live] |
| One Piece | Ван Піс [live] |
| Yu-Gi-Oh! | Ю-Гі-О! [live] |

Magic: The Gathering — **no bridge.** `Магік` is colloquial and not a search target.

### Pokémon — card subjects

| Latin | Cyrillic |
|---|---|
| Pikachu | Пікачу |
| Charizard | Чарізард |
| Charmander | Чармандер |
| Squirtle | Сквіртл |
| Bulbasaur | Бульбазавр |
| Mew | Мью |
| Onix | Онікс [live] |
| Umbreon | Амбреон |
| Gengar | Генгар |
| Magikarp | Магікарп |
| Ditto | Дітто |
| Slowpoke | Слоупок |

### One Piece — card subjects

| Latin | Cyrillic |
|---|---|
| Luffy | Луффі |
| Zoro | Зоро |
| Nami | Намі |
| Kuzan | Кузан |
| Katakuri | Катакурі |
| Sabo | Сабо |
| Kid | Кід |

### Objects

| Latin | Cyrillic |
|---|---|
| Poké Ball / Poke Ball | Покебол [live] |

### No bridge — Latin only

Geodude, Jigglypuff, Gallade, Glaceon, Salamence, Litleo, Goomy, Going Merry, and every name
that appears only inside a chase block (Darkrai, Gardevoir, Zygarde, Greninja, Rayquaza,
Rayleigh, Xebec, Kaido, Shanks and the rest).

The Zoro / Kuzan / Katakuri / Sabo / Kid rows were `[live]` in the five starter-deck product
names until 2026-09-13, when those names were switched to Latin per §14. The Cyrillic forms
remain approved for use as in-body bridges.
