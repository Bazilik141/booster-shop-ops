# Handoff — CONTENT-007 · Canon v1 model test

**Date:** 2026-09-14 · **Roadmap:** CONTENT-007 (In progress, High)
**Assignee:** Claude, fresh session
**Prerequisite state:** commit `53a74fd` + the roadmap batch of 2026-09-13
**Output:** three generated cards, a blind A/B pack for the owner, and a verdict on whether
Canon v1 is legible to a model. No production changes.

---

## 1. Why this test exists

Canon v1 was validated once, by hand, by the same session that wrote it. That proves only that
the canon is executable by a person with the canon in front of them and the owner's re-scoring
fresh in mind. It does not prove the canon transmits.

Everything downstream — the validator, the writer prompt, the golden set, the Product Master
field list — is aimed at the canon. If a model following it produces materially worse copy, the
fix is the canon, not the code, and finding that out after building the pipeline is the expensive
order.

**The question, stated so it can fail:** does a model that has read only Canon v1 and the verified
facts produce cards the owner rates as equal to the hand-written ones?

## 2. Hard procedural rule

**The generating session must not read `work/CANON_v1_TEST_three-rewrites_20260913.md` before it
finishes writing.** That file contains the hand-written versions. Reading it first turns the test
into an imitation exercise and destroys the result. Read it only at the comparison step, or have a
separate session do the comparison.

Also do not read the owner's re-scoring (it is quoted throughout the canon itself and in
`diagnostics/CONTENT-PIPELINE_card-quality-tier-analysis_20260913.md` §12) beyond what Canon v1
already contains. The canon is supposed to carry that knowledge — that is precisely what is under
test.

## 3. Inputs the generating session gets

**The canon:** `docs/CANON_v1_DRAFT_product-content_20260913.md`. Whole file. Nothing else about
style.

**The three cards, with exactly these verified facts and no more:**

### 3.1 Чаша-покебол для дрібниць (Pokémon) — 3D-друк
Own 3D-printed product. H1 stays as is (3D names are exempt from Latin-first, §5.2).
Attributes: Країна виготовлення Україна · Спосіб виготовлення пошаровий 3D-друк ·
Розміри 167×167×59 мм · Маса орієнтовно 146,44 г · Комплектація 1 чаша · Рухомі елементи немає ·
Вікове позиціонування 14+ · Типовий строк виготовлення при відсутності на складі 1–2 робочих дні ·
Може трапитись у Mystery Box Ні · Тип виробу чаша для дрібниць · Колір червоно-білий ·
Призначення зберігання дрібниць.
Additional confirmed: open bowl, no lid and no opening mechanism; the front button is decorative;
one undivided compartment; red/white/black is fixed; PLA; not for food or liquids.
Neighbouring products in the catalogue for the internal link:
`/product/korobka-dlia-kartok-pokeball-kvadratna-pokemon-3d-druk` (square Poké Ball card box),
`/product/stakan-dlia-ruchok-ditto-pokemon-3d-druk` (Ditto pen cup),
`/product/figurka-kliker-pokeball-pokemon-3d-druk` (Poké Ball clicker).

### 3.2 Фігурка Nami (One Piece) — 3D-друк
Own 3D-printed product.
Attributes: Країна виготовлення Україна · Спосіб виготовлення пошаровий 3D-друк ·
Розміри 65×47×75 мм · Маса орієнтовно 27,99 г · Комплектація 1 фігурка · Рухомі елементи немає ·
Вікове позиціонування 14+ · Строк виготовлення 1–2 робочих дні · Може трапитись у Mystery Box Так ·
Тип виробу фігурка · Призначення декоративний / колекційний виріб.
Additional confirmed: seated cross-legged pose; no drawn face, X-shaped eyes, simplified
features; fully black; arrives assembled; PLA; printed only in black.
Neighbouring products: `/product/figurka-luffy-one-piece-3d-druk`,
`/product/kartyna-zoro-one-piece-3d-druk`, `/product/brelok-going-merry-one-piece-3d-druk`.

### 3.3 Бустер Yu-Gi-Oh! OCG: BEYOND THE BRAVE (Японське видання)
Sealed booster pack.
Attributes: Назва сету BEYOND THE BRAVE · Мова Японська · Тип пакування Sealed Booster Pack ·
Кількість карток у бустері 5 · Зважування Без зважування · Стан Новий, нерозпакований ·
Походження товару Box / Case sourced · Виробник Konami · Рік випуску 2026.
Verified set facts: set code BETB; OCG Japan release **18 July 2026**; 198 yen per pack; 5 cards
per pack; 30 packs per display; **80 card types** in the main set; Overframe printing continues;
**24 cards have Prismatic Secret Rare versions, four of them in Overframe** (two independent
sources agree); themes **Dark Time Wizard** (new, time-magic — *not* Joey Wheeler's archetype),
**Red-Eyes** support led by Red-Eyes Black Dragon Exceed, **Destiny HERO**, **Atlantis**, and the
new Synchro archetype **Ashtra**; Joey Wheeler is the cover character alongside Red-Eyes Black
Dragon Exceed. Singles packs are opened from a whole factory-sealed box the shop opens itself.
**Not verified, must not appear:** any claim that a key card offers a safe-or-risky choice; the
Ultimate Rare / Secret Rare counts from the single shop source; any pull rate.
First-edition boxes carry a +1 Expansion Pack, but **Booster Shop's stock does not** — owner
checked 2026-09-13. Do not mention it.
Neighbouring product: `/product/YuGiOh-booster-box-Beyond-the-Brave`.

## 4. What to produce per card

H1 · Meta Title · Meta Description · body HTML · FAQ HTML in the §6 accordion contract. Plus the
§6 compliance self-check. Same shape as the existing test file so the two are comparable.

## 5. Comparison and verdict

1. Run the §11 code-checkable flags from the canon over both versions. A canon that a model
   cannot satisfy mechanically fails before any taste question.
2. Pair each generated card with the hand-written one, **strip all labels, randomise which is A
   and which is B**, and give the owner the three pairs. Ask one question per pair: which reads
   better, and what specifically is missing in the other.
3. Do not tell him which is which until he has answered all three.

**Success:** he splits roughly evenly, or cannot tell, or prefers the generated one. Canon v1
transmits. Proceed to the validator and the pipeline port.

**Failure:** he prefers the hand-written one in most pairs **and names a consistent reason**.
That reason is the thing the canon fails to carry. Fix the canon, re-run, do not start building.

**Inconclusive:** he prefers hand-written but for different reasons each time, or the difference
is small and he shrugs. Treat as a weak pass, note it, and proceed — but keep the generated cards
as the baseline for the next regression.

## 6. What not to do in that session

- Do not publish anything. Nothing here touches production.
- Do not edit Canon v1 while generating. If the canon turns out ambiguous mid-write, **write the
  ambiguity down and keep going** — an ambiguity discovered by a model following the canon is the
  most valuable output of this whole exercise, and patching it silently destroys the evidence.
- Do not fix the Blazing Dominion or Lumiose cards here. That is CONTENT-008, a separate wave.

## 7. Open context the new session should know

- `CONTENT-008` is blocked on one fact: which two expansions are inside the Lumiose City Mini Tin.
  The tin's own label says "Booster packs vary by product" and names nothing legible. Everything
  else on that card is now confirmed from the package.
- `SEO-008` (keyword map) is the open prerequisite for the canon's internal-linking and
  cannibalization rules. It does not block this test.
- `device_bash` has been dead on this machine since the 2026-09-08 Windows update. Read repo files
  via `device_stage_files`, not a shell.
- Architecture for the pipeline is not to be designed from scratch: `crm/apps-script/Code.gs`
  already runs analyse → draft → audit → edit → align with a working anti-generic validator. See
  the `news-pipeline-architecture` project memory.
