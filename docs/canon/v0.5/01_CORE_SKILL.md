---
name: booster-shop-product-content-core
description: Global canon for researching, planning and writing Booster Shop product content. Use together with exactly one product-type module. Handles source hierarchy, fact-gap preflight, buyer/marketing planning, semantic and search-intent planning, voice, SEO, FAQ principles, meta, internal links and output discipline. Do not use this file alone to infer product-type composition.
---

# Booster Shop — Product Content Core

**Edition:** 2026-08-29 v11.1 — FAQ value/ranking polish  
**Role:** global writing rules only. Product composition lives in one product-type module. Post-draft auditing lives in `booster-shop-content-qa/SKILL.md`.

## 1. Source hierarchy

Use facts in this order:

1. newest explicit owner instruction in the current task;
2. owner-confirmed product facts, CRM/DB, physical-product photos and package markings;
3. official manufacturer/publisher sources;
4. current task handoff;
5. approved Booster Shop live pages as tone/composition references only;
6. secondary research when primary sources do not answer the question.

Approved live pages can teach tone, useful informational roles and commercial depth. They do **not** automatically re-authorize legacy claims.

## 2. Mandatory fact-gap preflight

Before writing client-facing H1/body/FAQ/meta for a new or fully rewritten product page:

1. gather owner/CRM/photo/package facts;
2. research facts that can be verified reliably without the owner;
3. make a compact fact map: `confirmed / missing / irrelevant`;
4. identify every missing fact that could materially change the buyer decision, search usefulness or quality of the page;
5. ask the owner all blocking questions in one consolidated message;
6. write only after the owner answers or explicitly says the fact is unknown/unavailable.

If a fact remains unknown after preflight:

- omit the claim from client copy;
- do not create a FAQ whose answer is “we have not confirmed this”, “data unavailable”, “need measurements”, etc.;
- keep the gap only in internal notes if downstream work requires it.

A real owner-confirmed limitation or disclaimer **is** valid customer-facing information.

## 3. Research rules

For researched facts:

- prefer official product pages and official card databases;
- keep source URLs/references in an internal source map;
- secondary sources are acceptable only when primary sources are insufficient;
- source confirms the fact, not the wording: never copy source prose into the product card.

Never invent or infer unsupported:

- GTIN/EAN/UPC;
- certification;
- reviews/ratings;
- exact weight/dimensions;
- release date;
- pack/card counts;
- rarity/pull guarantees;
- compatibility;
- materials;
- shrink/sealed state;
- UV/waterproof/acid-free/PVC-free/archival claims;
- stock/sales figures.

Do not manufacture marketing numbers by arithmetic unless the number is both useful and semantically valid.

### 3.1 Search evidence

Do not invent keyword volume, competition or popularity.

When real search evidence is available — Search Console/site-search data, keyword tooling, autocomplete/search patterns, owner data or a dedicated research task — use it to refine intent. When it is not available, work from the product entity, buyer language and plausible search/pre-purchase questions without pretending those phrases have measured demand.

Search research is evidence, not a reason to copy awkward exact-match phrases into client prose.

## 4. Working order

Use this sequence:

**facts → buyer/marketing plan → semantic/search-intent plan → body → editorial pass → FAQ → meta/SEO → QA**

Do not start from a fixed sentence template, a keyword list or QA metrics.

## 5. Buyer/marketing plan before prose

Before writing body, answer internally:

1. **Who is this page mainly for?**
2. **What situation brings them here?**
3. **What friction or desire does this product actually address?**
4. **Why this product rather than doing nothing or choosing the neighbouring format?**
5. **What confirmed facts prove that value?**

A good page should give the shopper an intuitive answer to **“what is this for me?”**, not merely “what specifications does it have?”.

## 6. Semantic + search-intent plan before prose

Before drafting, build one compact internal map. It is a thinking tool, not a client-facing keyword list.

Identify:

- the **primary commercial intent** of the concrete page;
- the canonical product/entity wording a shopper would understand;
- the strongest **supporting intents or questions** that genuinely refine the primary intent;
- the product-specific facts, mechanics, use cases or comparisons that make the page worth finding;
- which useful information belongs in body, FAQ, attributes, Meta or an internal link;
- required disclaimer/legal point, if any;
- any verified cross-language/entity bridge that should appear once;
- facts/phrases that must **not** be repeated merely for SEO.

There is **no target number** of supporting intents, keywords, headings or paragraphs. FAQ uses the existing **2–4 item flexible range**, but that range is not a target to fill. Two strong FAQ items are better than four where the extra questions add little or merely repackage information already handled elsewhere.

Do not make separate pages or separate copy blocks for trivial keyword variants. Search intent is about the user need and entity, not exact-match string coverage.

### 6.1 Search-surface routing

Use each surface for its natural job:

- **H1/product name** → clearly identify the product/entity;
- **body** → explain buyer value, product identity and useful evidence;
- **FAQ** → select the strongest distinct question-shaped or high-friction intents for this specific product. A question does not earn a FAQ slot merely because it can be phrased like a search query; it should add useful information, clarification or decision value beyond what the page already explains;
- **attributes** → record structured facts;
- **Meta** → summarize and disambiguate; never use it as a keyword dump;
- **internal links** → help the shopper move to a genuinely neighbouring choice.

A fact may appear in more than one surface when the **function differs**. Example: an attribute can record a material or dimension while body/FAQ explains why that fact matters. What should be avoided is duplicate informational work, not the mere reappearance of a factual term.

### 6.2 First-party evidence priority

When Booster Shop has direct experience with the physical product, prefer **first-party product evidence** over commodity filler.

Useful first-party evidence includes, when confirmed:

- real dimensions, mass, contents and material;
- assembly state, fit, capacity and compatibility;
- movement, pose, mechanism or tactile behaviour;
- what is visibly/physically different from a neighbouring model;
- production or packaging details that affect the buyer decision;
- observations from the actual product/photos that do not pretend to be unsupported technical claims.

Generic franchise lore or broad category facts can support context, but they must not displace concrete information about the exact product being sold.

### 6.3 Feature → meaning → proof

When a technical feature enters body, it should usually answer at least one of:

- what becomes easier;
- what experience changes;
- what kind of buyer it suits;
- what choice it helps make;
- what concern it resolves.

Do not turn every specification into a benefit sentence. Some facts belong only in attributes.

## 7. Voice and tone

When the store is the subject, speak as **`ми`**. Direct address uses **`ви`**.

Tone:

- natural Ukrainian;
- knowledgeable but alive;
- commercially useful without sales-script clichés;
- specific before persuasive;
- allowed: light observation, familiar collector situations, restrained humour when it grows naturally from product context;
- no marketplace bureaucracy;
- no hype, fake scarcity or investment promises.

Avoid client-facing internal terms:

`SKU`, `box-SKU`, `артикул`, `позиція`, `номенклатура`, internal batch names, `хвиля` as a production-batch label.

### 7.1 Українська мова першою

У звичайному client-facing prose віддавай перевагу природній українській, а не TCG-англіцизмам, якщо англійський термін не є потрібною офіційною назвою.

Залишай оригінальне написання, коли воно справді потрібне: назва карти, сету, рідкості, офіційної механіки/продукту або SEO-сутності.

### 7.2 Один кириличний місток назви IP у HTML body

Для TCG-картки **рівно один раз у клієнтському HTML body** напиши затверджену кириличну форму назви гри/IP, якщо така форма зафіксована в каноні.

Поточні затверджені форми:

- `Pokémon` → `Покемон`;
- `One Piece` → `Ван Піс`;
- `Yu-Gi-Oh!` → `Ю-Гі-О!`.

H1/product name, FAQ, attributes, Meta та keywords у цей count не входять.

## 8. Copy quality principles

### 8.1 Do not write a specification table in sentences

Body is not a prose copy of attributes.

A feature belongs in body when it helps sell, explain, differentiate or reassure. Exact counts/dimensions can still appear in body when they are important proof, but not merely because the field exists in attributes.

Attributes may repeat facts from body as structured data. That is normal. The **rhetorical function** must differ: body interprets; attributes record.

### 8.2 One thesis, one main place

Within body, each meaningful argument gets one main home.

Do not:

- repeat the product/set name in a heading and immediately again in the first clause without adding information;
- explain a mechanic in a paragraph and repeat it in the following list;
- mention format/count in several body sections;
- create a new section solely to restate what the product is.

### 8.3 Headings must advance the reader

A heading introduces the next idea. The first sentence develops it rather than paraphrasing it.

### 8.4 Marketing without hallucination

Good marketing is **selection and framing of true facts**, not invented superiority.

Allowed:

- a plausible use case based on confirmed properties;
- explaining why a feature matters;
- positioning a product for a real buyer segment;
- a vivid but ordinary collector scenario.

Not allowed:

- invented frustration that the product supposedly solves;
- unsupported “кращий”, “преміальний”, “професійний”, “ідеальний”;
- claims that a box/pack improves pull odds unless sourced;
- fake emotional stakes.

### 8.5 Do not write toward a target length

No minimum number of paragraphs, headings or lists is used during generation.

For FAQ, use the established **2–4 item flexible range**. Treat it as an editorial window, not a completion target: rank candidate questions by buyer/search value, keep the strongest ones, and stop when the remaining candidates mostly repeat body/attributes or add only marginal value. Do not add a third or fourth question simply because the range allows it.

A block exists only when it adds a new buyer-relevant function. If removing it loses nothing, remove it.

Product-type modules may define a mandatory FAQ role (for example, legal/contents disclosure); that item counts within the same 2–4 range rather than creating a separate quota.

### 8.6 Natural variation over forced uniqueness

Sibling pages may share clear factual language. Avoid boilerplate, but do not invent awkward metaphors or artificial angles merely to evade similarity.

## 9. HTML body basics

Allowed body tags by default:

`h2`, `h3`, `p`, `strong`, `ul`, `li`, `br`.

No inline styles, scripts, images, JSON-LD or unrelated technical classes in the body draft.

`<strong>` is for real reading emphasis, not SEO stuffing. Use lists only when scanning is better than prose.

### 9.1 Canonical FAQ accordion markup

For any new or updated FAQ, use the live-site accordion structure:

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="<unique-card-id>">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-<faq-id>-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-<faq-id>-button-1" type="button"><span>Питання</span></button></h3>

<div aria-labelledby="bs-faq-<faq-id>-button-1" class="bs-faq-panel" hidden="" id="bs-faq-<faq-id>-panel-1" role="region">
<p>Відповідь.</p>
</div>
</div>
</section>
```

Required:

- `section.bs-faq-accordion`;
- `data-bs-faq-accordion=""`;
- unique `data-bs-faq-id`;
- `bs-faq-item`, `bs-faq-question`, `bs-faq-toggle`, `bs-faq-panel`;
- question text inside `<span>`;
- `hidden=""` on every panel;
- two-way `id` / `aria-controls` / `aria-labelledby` linkage;
- unique IDs.

Do not output legacy `<section class="bs-faq">`, legacy `<div class="bs-faq-accordion">`, or an empty FAQ wrapper.

## 10. SEO intent: coverage over repetition

Each product page has one primary commercial intent. Supporting long-tail value comes from **useful coverage of the concrete product**, not from repeating one exact keyword.

The page should make clear, naturally:

- what exact product/entity this is;
- the product type/category that matters to the buyer;
- the strongest real differentiator, format, mechanic, use case or compatibility detail;
- direct answers to worthwhile related questions when confirmed.

The main product core should appear naturally in H1/product name, body and Meta Title. Meta Description may reinforce it when useful.

Supporting terms do not need exact-match repetition. Use natural language and semantic variants where they improve clarity.

Do not create extra headings or FAQ merely to place secondary keywords. Do not use Meta Keywords as a hiding place for search intents missing from the actual page.

Product pages target the concrete product. Category pages target the broader class.

## 11. Cross-language Pokémon set bridge

For **Japanese, Korean and Chinese Pokémon Booster Packs / Booster Boxes**, include **one natural body mention** of the verified English-language counterpart/relationship when a reliable mapping exists.

Rules:

- one mention per page is enough;
- use natural wording containing the English set name;
- verify the relationship;
- if the English release combines multiple Asian sets or has a broader/different card pool, say so accurately;
- do not duplicate the counterpart in multiple sections or manufacture a FAQ purely for the keyword;
- if mapping is uncertain, omit it.

## 12. Meta

### Meta Title

- hard ceiling for new/full rewrites: **63 characters**;
- identify the product clearly;
- do not stuff synonyms/language variants.

### Meta Description

- hard ceiling for new/full rewrites: **155 characters**;
- summarize product + strongest differentiator/use case;
- no unresolved claims or filler.

### Meta Keywords

Only if downstream still uses the field:

- product-specific variants;
- sensible transliterations/English terms;
- no giant keyword dump;
- never treat the field as a substitute for body/FAQ search coverage.

### SEO URL

Do not invent a global slug convention until the owner provides one.

## 13. Claims, comparisons and ALT

Compare only against a concrete neighbouring product and only on a confirmed attribute.

Avoid catalog-dependent superlatives such as `найменший у лінійці`, `єдиний у магазині`, `найлегший у каталозі`.

Without reliable support, do not use claims such as:

`100% оригінал`, `тільки у нас`, `найкраща ціна`, `гарантований хіт`, `інвестиційний потенціал`, `офіційний магазин`.

Do not separately draft ALT for normal product-card images when the current OpenCart theme uses the product name as alt.

## 14. FAQ: long-tail utility without quotas

FAQ is written **after body**. It is one of the main surfaces for **question-shaped long-tail and high-friction pre-purchase intents**, but it is not mandatory for SEO by itself.

A useful FAQ should normally satisfy at least one of these:

1. it matches a plausible question a buyer may type into search/AI;
2. it resolves a meaningful pre-purchase objection, compatibility issue or comparison;
3. it explains a specialist term/mechanic whose answer would be awkward inside body;
4. it creates a useful internal-link bridge to a genuinely neighbouring product/category;
5. a product-type module requires a legal/disclosure FAQ.

A FAQ item must also:

- have a confirmed answer;
- add explanation, decision value or context;
- sound like a real human question, not a heading converted into interrogative form.

**Attribute overlap is not automatically duplication.** A FAQ may use a fact also present in attributes when the FAQ answers a meaningful search/pre-purchase question and adds explanation/context. A FAQ that merely restates an attribute value is redundant.

If body already gives the complete direct answer and FAQ adds nothing, do not repeat it. If the question itself is valuable, decide which surface should carry the detailed answer.

Do not create FAQ for symmetry, quota or “SEO volume”. Zero FAQ is valid when no worthwhile question remains, except where a product-type module requires a disclosure/legal item.

A mandatory legal/disclosure FAQ does **not** by itself prove that the page has good long-tail coverage.

## 15. Attributes

Use only confirmed product-type schema.

- attributes are structured source-of-truth facts;
- copy follows facts, not the other way around;
- do not add irrelevant rows for symmetry;
- unknown values stay internal rather than guessed.

If a confirmed fact materially affects the buying decision or a meaningful search intent, do not hide the **whole answer** exclusively in attributes. Use body or FAQ where explanation is useful.

## 16. Internal links

For a full deliverable, suggest useful internal-link targets when such destinations exist or are planned.

Do not invent URLs. If a slug is not confirmed, provide anchor + destination purpose without href.

## 17. CTA

A natural CTA is optional.

It should not repeat the previous paragraph, invent scarcity, or re-summarize the page. If the body already closes with a purchase-relevant thought, no separate CTA is needed.

## 18. Internal deliverable blocks

Keep these outside client-facing HTML unless explicitly requested:

### Sources
List source URLs/references for researched facts; owner/CRM/photo facts can be marked as confirmations.

### Unresolved
Only facts still needed downstream. Unknown facts the owner has chosen not to provide do not leak into customer copy.

## 19. Generation workflow

For one product or homogeneous batch:

1. determine product type;
2. load Core + exactly one product-type module;
3. perform research/fact-gap preflight;
4. resolve blocking questions;
5. make buyer/marketing plan;
6. make semantic/search-intent plan;
7. draft body **without QA metrics, fixed templates or keyword stuffing**;
8. reread body as a shopper and improve commercial usefulness;
9. route remaining worthwhile question-shaped intents into FAQ where appropriate;
10. write Meta / internal links / Sources / Unresolved;
11. run post-draft QA, including search-coverage QA.

## 20. Mixed batches

Do not generate prose for unrelated product types in one copywriting pass unless explicitly requested.

Research may be shared. Prose is grouped by type:

- accessories;
- booster boxes;
- booster packs;
- fixed sets;
- 3D.

Box + Pack of the same set may share a factual research sheet but receive separate writing passes because buyer intent differs.
