# Handoff: SEO-CRIT-001 — Fix Pass (post-QA)

**Date:** 2026-06-07  
**Prepared by:** Claude  
**Reason:** Live QA showed 4 remaining issues after partial Codex patch. This handoff covers only what was NOT fixed.

---

## 1. Task ID

`SEO-CRIT-001-FIX` (sub-pass of SEO-CRIT-001)

---

## 2. Context

Live QA on 2026-06-07 confirmed the previous Codex patch was **partially applied**:
- `/catalog/Pokemon` — ✅ done
- `/catalog/One-Piece` — ✅ done
- `/catalog/Pokemon/pokemon-tcg-nabory` — ✅ done
- FAQ accordion — ✅ working

**Not fixed (this handoff):**
1. `/catalog/Pokemon/Pokemon-booster-box` — forbidden claims in body description (P0)
2. Homepage `/` — H1 missing (SEO-002)
3. `/special` — meta description missing, title generic
4. `/catalog/Pokemon/Pokemon-boosters` — no body description, no FAQ

Latest backup: `backup-6.3.2026_21-17-59_boosters.tar.gz`  
DB: `mysql/boosters_ocart49.sql`  
Webroot: `homedir/public_html`

---

## 3. Goal

Fix the 4 remaining issues. No new scope. No changes to pages/fields already confirmed OK.

---

## 4. What to change

### Issue 1 — CRITICAL (P0): `/catalog/Pokemon/Pokemon-booster-box` forbidden claims

**Table:** `oc_category_description`  
**Condition:** `language_id = <uk-ua id>` WHERE category matches `Pokemon-booster-box` URL alias  
**Field:** `description`

**БУЛО (forbidden text — entire paragraph, locate by content):**
```
Найкращий спосіб отримати гарантовані рідкісні картки. Наші бустер бокси Pokémon TCG постачаються напряму з Японії у оригінальній термоусадковій плівці — це ідеальний вибір для серйозних колекціонерів.
```

**СТАЛО (replace only this paragraph):**
```html
<p>Бустер бокс Pokémon TCG — це стандартне заводське пакування, що включає 30 бустерів одного сету. Він підходить для серйозного колекціонування, sealed-зберігання та повноцінного відкриття. Кожен бустер бокс продається у запечатаному стані. Результат відкриття залежить від заводського розподілу карт усередині сету.</p>
```

> ⚠️ Remove ONLY the forbidden paragraph. Do NOT touch the rest of the description, H1, meta title, meta description, or any other field.

---

### Issue 2 — Homepage H1 missing (SEO-002)

The homepage currently has no `<h1>` tag. First visible heading is `<h2>Booster Shop — від колекціонера для колекціонерів</h2>`.

**Option A — preferred (content/module approach):**  
If the homepage description is stored in `oc_information` or a custom HTML module, prepend `<h1>` before the existing `<h2>`:

**СТАЛО (add before existing H2):**
```html
<h1>Бустер Шоп — оригінальні бустери Pokémon та One Piece TCG</h1>
```

**Option B — Twig fallback:**  
If the homepage has no editable description field and the H2 comes from a Twig template, add `<h1>` in the appropriate layout block.  
Likely file: `catalog/view/theme/<theme>/template/common/home.twig` or equivalent custom layout module.  

> ⚠️ Codex must verify where the existing H2 comes from before editing. Do not add H1 in two places.

---

### Issue 3 — `/special` meta description missing + title generic

**Route:** `product/special`  
**Current title:** "Акції"  
**Current meta desc:** none

**Option A — language file:**  
File: `catalog/language/uk-ua/product/special.php` (or equivalent)  
Look for: `$_['heading_title']`, `$_['text_special']`  
If meta_description key exists → update it.

**Option B — SEO module / admin SEO settings:**  
Check if OpenCart SEO module allows setting meta per route. If yes — set via DB directly:  
Table: `oc_seo_url` or equivalent meta table for system pages.

**СТАЛО:**
```
meta title:  Акції та знижки — Pokémon та One Piece TCG | Booster Shop
meta desc:   Бустери Pokémon та One Piece Card Game зі знижками. Актуальні акційні пропозиції в Booster Shop. Безпечна доставка по Україні.
```

> Codex should verify the actual mechanism for meta on system pages in this OpenCart install before editing. Do not hardcode meta in Twig if a DB/language-file approach is available.

---

### Issue 4 — `/catalog/Pokemon/Pokemon-boosters` body description + FAQ

**Table:** `oc_category_description`  
**Condition:** `language_id = <uk-ua id>` WHERE category matches `Pokemon-boosters` URL alias  
**Field:** `description`

**СТАЛО (full replacement — field is currently empty or has no body content):**

```html
<h2>Бустери Pokémon TCG в Booster Shop</h2>

<p>У цій категорії зібрані оригінальні <strong>бустери Pokémon TCG</strong>: японські та корейські sealed-паки, unweighed та Outlet Mix бустери. Вони підходять для відкриття, збору колекції, пошуку рідкісних карт, подарунка фанату Pokémon або поповнення binder collection.</p>

<p>Японські бустери Pokémon TCG відрізняються якістю друку, насиченими кольорами та ранніми релізами сетів. Корейські видання зазвичай доступніші за ціною і можуть бути хорошим варіантом для бюджетного відкриття або збору базових карт.</p>

<p>Для кожного товару ми вказуємо мову видання, назву сету, кількість карт у бустері, тип пакування та статус зважування. Якщо бустер продається без зважування — це позначено як <strong>Unweighed</strong>. Якщо товар є форматом <strong>Outlet Mix</strong> — це прямо зазначено в назві та описі.</p>

<p>Booster Shop не обіцяє гарантовані hit-карти або "100% дроп". Ми чесно описуємо формат товару, пакуємо замовлення для безпечної доставки по Україні та допомагаємо обрати бустер під конкретну ціль.</p>

<h2>FAQ — Бустери Pokémon TCG</h2>

<div class="bs-faq">

<div class="bs-faq__item">
<button class="bs-faq__question" aria-expanded="false">Чим японські бустери Pokémon відрізняються від корейських?</button>
<div class="bs-faq__answer">
<p>Японські бустери зазвичай виходять раніше, мають вищий колекційний інтерес і сильну якість друку. Корейські бустери часто доступніші за ціною, тому добре підходять для бюджетного відкриття та збору базових карт.</p>
</div>
</div>

<div class="bs-faq__item">
<button class="bs-faq__question" aria-expanded="false">Що означає sealed-бустер?</button>
<div class="bs-faq__answer">
<p>Sealed-бустер — це бустер у заводському пакуванні. Він не розкривався, не перепаковувався та продається у стані нового нерозпакованого товару.</p>
</div>
</div>

<div class="bs-faq__item">
<button class="bs-faq__question" aria-expanded="false">Що означає unweighed?</button>
<div class="bs-faq__answer">
<p>Unweighed означає, що бустер не зважувався перед продажем і не проходив ручний перевідбір. Це важливо для чесних очікувань від відкриття.</p>
</div>
</div>

<div class="bs-faq__item">
<button class="bs-faq__question" aria-expanded="false">Що таке Outlet Mix?</button>
<div class="bs-faq__answer">
<p><a href="https://boostershop.website/product/Pokemon-Japanese-outlet-booster">Outlet Mix</a> — японські sealed-бустери Pokémon TCG зі змішаних сетів за зниженою ціною. Підходить для недорогого відкриття, збору commons/uncommons або знайомства з різними релізами.</p>
</div>
</div>

<div class="bs-faq__item">
<button class="bs-faq__question" aria-expanded="false">Чи є гарантія рідкісної карти в бустері?</button>
<div class="bs-faq__answer">
<p>Ні. У sealed-бустерах немає гарантії конкретної карти або рідкості. Результат відкриття залежить від заводського розподілу карт усередині сету.</p>
</div>
</div>

</div>
```

> Accordion HTML structure must match the existing `bs-faq` component already deployed on `/catalog/Pokemon` and `/catalog/One-Piece`. Codex should verify the exact class names and markup from those live pages before inserting.

---

## 5. Do not touch

- `sitemap.xml`, `robots.txt`, `.htaccess`
- Canonical tags on any page
- URL slugs / SEO URL aliases
- Product schema (JSON-LD), Merchant feed
- Checkout, payment, fiscalization modules
- Any page NOT listed in section 4: already-OK pages (`/catalog/Pokemon`, `/catalog/One-Piece`, `/catalog/Pokemon/pokemon-tcg-nabory`)
- Prices, availability, stock data
- Any `oc_product_description` rows (this pass is categories + homepage only)
- FAQ accordion JS/CSS — reuse existing component, do not modify `bs-faq.js` or related CSS unless there is a confirmed bug

---

## 6. Likely files / areas

| Area | Likely location | Confidence |
|---|---|---|
| Category body descriptions | `oc_category_description.description` (DB) | High |
| Homepage H1 | Custom HTML module or `oc_information` — verify first | Medium |
| Homepage H1 fallback | `catalog/view/theme/.../template/common/home.twig` | Fallback only |
| `/special` meta | `catalog/language/uk-ua/product/special.php` | Medium |
| `/special` meta fallback | SEO module DB table | Fallback |
| FAQ HTML structure reference | Live `/catalog/Pokemon` page source | High |

---

## 7. Acceptance criteria

| # | Check | Expected |
|---|---|---|
| 1 | `GET /catalog/Pokemon/Pokemon-booster-box` — scan body for "гарантовані рідкісні картки" | NOT found |
| 2 | `GET /catalog/Pokemon/Pokemon-booster-box` — scan body for "напряму з Японії" | NOT found |
| 3 | `GET /catalog/Pokemon/Pokemon-booster-box` — H1 | "Бустер бокси Pokémon" (unchanged) |
| 4 | `GET /` — page source contains `<h1>` | `<h1>` present, comes before any `<h2>` |
| 5 | `GET /special` — `<meta name="description">` | Non-empty, contains "Pokémon" або "One Piece" |
| 6 | `GET /special` — `<title>` | Contains "Акції" + brand ("Booster Shop") |
| 7 | `GET /catalog/Pokemon/Pokemon-boosters` — body has `<h2>` + `<p>` content | Present below product grid |
| 8 | `GET /catalog/Pokemon/Pokemon-boosters` — FAQ `<div class="bs-faq">` | Present |
| 9 | Already-OK pages (`/catalog/Pokemon`, `/catalog/One-Piece`, `/catalog/Pokemon/pokemon-tcg-nabory`) | Content unchanged |
| 10 | Product schema on any product page | Not broken (spot-check 1 product) |

---

## 8. QA / smoke test

1. Open `/catalog/Pokemon/Pokemon-booster-box` — scroll to bottom of page — forbidden text absent.
2. View source `/catalog/Pokemon/Pokemon-booster-box` — confirm no "гарантовані рідкісні" in HTML.
3. Open `/` (homepage) — view source — `<h1>` present before `<h2>`.
4. Open `/special` — browser tab shows optimized title, right-click Inspect → meta description not empty.
5. Open `/catalog/Pokemon/Pokemon-boosters` — description text visible below grid, FAQ items visible and accordion working (click to expand).
6. Spot-check `/catalog/Pokemon` — description and FAQ unchanged.
7. Spot-check 1 product page — price, availability, schema not broken.

---

## 9. Rollback note

Backup: `backup-6.3.2026_21-17-59_boosters.tar.gz`

For DB changes:
- Save original `oc_category_description.description` for `Pokemon-booster-box` and `Pokemon-boosters` categories before update.
- Provide rollback SQL with original values.

For file changes (homepage Twig / language file):
- Keep `.bak` copy of every modified file alongside the change.

---

## 10. Recommended status after execution

- Run QA checklist (section 8) — all 10 acceptance criteria pass → mark `SEO-CRIT-001` as **Done** in Notion.
- If any criterion fails → leave **In progress**, note specific failure.

---

*Scope: content/meta only. No URL, schema, feed, checkout, or payment changes.*
