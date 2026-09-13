---
name: booster-shop-content-qa
description: Post-draft editorial, search-intent and batch QA for Booster Shop product content. Apply only after product copy has been drafted with Core + one product-type module. This skill audits; it must not be used as a prose-construction template.
---

# Booster Shop — Product Content QA

**Edition:** 2026-08-29 v11.1 — FAQ value/ranking polish

## 1. Critical operating rule

This QA is **post-draft**. Do not use metrics, medians, keyword counts or pattern diagnostics to construct prose.

If QA finds weakness, fix the weakness directly. Do not add filler to improve a count.

The FAQ **2–4 flexible range** is not a quality target to maximize; two strong items beat four padded ones. Heading/paragraph counts are not quality targets either.

## 2. Shopper-first editorial pass

Before checking HTML/SEO, read the page as a shopper.

Ask:

- Do I understand who this product is for?
- Do I understand the situation/desire/problem it addresses?
- Do I get a credible reason to choose or reject it?
- Do the features prove that reason, or are they merely listed?
- Does the page feel like a shop that knows the product rather than a technical catalogue?

If the page merely answers “what specifications exist?”, return it for rewrite even if all facts are correct.

## 3. Marketing-value pass

For each paragraph, identify its function internally:

- hook/use case;
- buyer benefit;
- proof/detail;
- differentiation;
- reassurance/limitation;
- search clarification;
- filler.

Delete or rewrite `filler`.

Flag paragraphs that are only attributes converted into sentences.

### 3.1 Benefit integrity

Every claimed benefit must be supported by confirmed product facts.

Flag:

- invented pain points;
- unsupported superiority;
- generic collector/fan lines that could fit any product;
- emotional language with no product connection.

## 4. Semantic density and repetition

For every paragraph/list/section ask:

- what new idea does this add?
- did the previous block already do the same informational job?
- could this be deleted with no loss?

Read headings + first sentences + list intros/items together and flag:

- heading → first-sentence paraphrases;
- product/set name repeated too close together without purpose;
- paragraph → list repetition;
- same argument in nearby sections;
- closing CTA that re-summarizes the page.

Repeated facts across **different functional surfaces** are not automatically failures. An attribute can record a fact while body/FAQ interprets it. Flag duplicate **informational work**, not mere shared vocabulary.

## 5. Human-language pass

Flag prose that sounds like:

- research notes (`за фото конкретного товару`, `ми не підтверджували`);
- bureaucracy/industrial catalogue;
- literal English documentation;
- over-engineered benefit explanations;
- unnatural Ukrainian/English hybrids outside real terms/proper names;
- generic marketing that could fit any product.

Allow restrained observation/personality when it comes naturally from collector/product context.

Check the current Cyrillic IP bridge rule where applicable.

## 6. Fact/source pass

Check every non-obvious claim against the source map.

Unknown/unsupported facts are removed, not hedged into customer copy.

For own/handled physical products, verify that first-party claims (dimensions, mass, material, movement, assembly, compatibility, capacity, packaging, contents) are grounded in owner data, the current product source table, or clearly confirmed evidence.

## 7. Search-intent coverage audit

This pass checks **missing useful coverage**, not keyword density.

Reconstruct or read the page's semantic/search-intent plan and ask:

1. What is the page's primary commercial intent?
2. Is the exact product/entity unmistakable from H1 + opening/body?
3. What are the strongest product-specific supporting intents or pre-purchase questions supported by confirmed facts?
4. Are any of those strong intents missing from **all** useful surfaces (body, FAQ, attributes + explanation, Meta, internal link)?
5. Does the page expose the product's real differentiator/mechanic/use case, or only its generic category/franchise?
6. Where Booster Shop has first-party evidence, is that evidence used when it would make the result materially more useful?
7. Could the page plausibly satisfy a meaningful query beyond the exact product name, or would a searcher still need another page for the obvious next question?

Do **not** fail a page because an exact long-tail phrase is absent. Do not invent missing keyword demand. Do not add a block solely to place a phrase.

A `search-intent gap` exists only when a **relevant, valuable, factually answerable** user need is omitted.

## 8. FAQ search-intent gate

Evaluate FAQ by **value and ranking inside the existing 2–4 flexible range**, not by how close the page gets to four items.

For every FAQ ask:

1. Would a buyer plausibly type this question into Google/search/AI, **or** is it a real high-friction pre-purchase/legal clarification?
2. Does this question have a **distinct informational purpose** on this page, rather than simply restating body or attributes in question form?
3. Does the answer add explanation, context, decision value or a useful comparison?
4. Is the answer confirmed and product-specific enough to deserve space here?

Search-shaped wording alone is not enough. If body/attributes already give the complete useful answer, either keep that information there or move it to FAQ — do not keep both just to increase FAQ count.

**Attribute overlap alone is not a failure.** A FAQ may reuse the same fact when it answers a genuine question more completely than a table row can.

A mandatory product-type legal/disclosure FAQ may remain even when it is not a strong SEO query; it counts inside the same 2–4 range. Do not mistake that mandatory item for complete search coverage.

When candidate #3 or #4 is materially weaker than the earlier questions, cut it instead of filling the range.

## 9. SEO/meta pass

Check:

- H1 clearly identifies the item/entity;
- primary product core appears naturally in body;
- supporting modifiers/intents are covered through useful content rather than exact-match repetition;
- important product-specific search value is not hidden only in Meta Keywords;
- headings are written for readers, not keyword slots;
- Meta Title ≤63 for new/full rewrites;
- Meta Description ≤155 for new/full rewrites;
- verified cross-language set relationship is accurate where applicable;
- no keyword stuffing, doorway-style variant copy or fake search claims.

SEO never rescues weak copy by repetition. Good SEO should make an already useful page easier for search systems and users to understand.

## 10. FAQ technical contract pass

When FAQ exists, verify:

- wrapper is `section.bs-faq-accordion`;
- `data-bs-faq-accordion=""` exists;
- `data-bs-faq-id` is unique/appropriate;
- every item uses `bs-faq-item`;
- every question uses `h3.bs-faq-question` + `button.bs-faq-toggle` + `data-bs-faq-toggle=""`;
- question text is inside `<span>`;
- every panel uses `bs-faq-panel`, `hidden=""`, `role="region"`;
- button `id` ↔ panel `aria-labelledby` match;
- button `aria-controls` ↔ panel `id` match;
- IDs are unique;
- no legacy FAQ wrapper/markup is introduced;
- no empty FAQ block exists.

## 11. Voice/internal-language pass

Flag:

- missing natural `ми` where Booster Shop performs a store action;
- direct `ти` instead of `ви`;
- client-facing `SKU`, `box-SKU`, `позиція`, `номенклатура`, internal batch language.

## 12. Homogeneous-batch QA

Compare **within the same product type first**.

Check:

- exact duplicate sentences/headings/FAQ answers;
- same rhetorical sequence with only entity substitution;
- generic shared selling paragraphs;
- forced uniqueness/gimmicks created merely to avoid similarity;
- recurring search-intent gaps across the whole batch;
- every page using the same easy FAQ while product-specific questions are ignored.

Repeated factual functions are diagnostic signals, not mathematical failures.

Historical live-page depth is only a clue if a page feels empty. Compare missing **roles/intents**, never raw `h2/p/FAQ` counts.

## 13. Optional diagnostic report

Useful diagnostics may include:

```text
pages:                                  N
exact duplicate sentences:             N + locations
exact duplicate headings:              N + locations
FAQ items total:                        N
ornamental/duplicate FAQ removed:       N
search-intent gaps:                     N + page + omitted intent
attribute-in-prose paragraphs flagged:  N
pages lacking clear buyer/use case:     N
pages lacking product-specific evidence:N
pages with unresolved client claims:    N
internal lexicon hits:                  N + locations
Meta Title >63:                         N
Meta Description >155:                  N
accordion/ARIA/ID errors:               N + locations
```

Do not turn these diagnostics into generation quotas.

## 14. Final acceptance questions

Before approving, ask:

> If all HTML tags, SEO fields and checklist labels disappeared, would this still read like a knowledgeable human wrote a useful product page?

> After reading it, do I have a clearer reason to want or reject this product — or did I merely learn its specifications?

> Does the page answer the strongest realistic search/pre-purchase needs that the confirmed product facts allow, without sounding written for a keyword robot?

If any answer exposes a real weakness, revise the weak part rather than adding generic volume.
