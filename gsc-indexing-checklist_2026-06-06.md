# GSC — чеклист ручної подачі на індексацію (Booster Shop)
_Складено 2026-06-06. Джерело: `/uk-ua/sitemap.xml` (51 URL)._

**Як подавати:** GSC → Перевірка URL → вставити URL → «Перевірити опубліковану версію» → «Запит на індексування».
**Квота:** ~10 URL/день на ресурс. План розбитий по днях.
**Тільки** сторінки, що віддають 200 і не noindex.

---

## ⚠️ Спочатку поправити URL — НЕ подавати, поки не виправлено

### Не-ASCII slug (SEO-005) — 301 на ASCII
- [ ] `https://boostershop.website/product/Pokémon-Trainer-Box-SVP`  → `…/product/pokemon-premium-trainer-box-ex-svp`

### Slug = SKU → перейменувати (мапінг нижче), 301 не потрібен (не в індексі), тоді подати у Дні 2b
- [ ] `YGO-JP-QCAC-BST` → `Yu-Gi-Oh-boosters-Quarter-Century-Art-Collection`
- [ ] `OP-JP-OP08-BST` → `One-Piece-Boosters-OP-08-Two-Legends`
- [ ] `OP-JP-MIX-MBX` → `One-Piece-Mystery-Box`
- [ ] `PKM-JP-INFX-BST` → `Pokemon-boosters-Inferno-X`
- [ ] `PKM-JP-MZERO-BLR` → `Pokemon-Mega-Gallade-EX-Special-Set`
- [ ] `PKM-JP-SVEX-BLR` → `Pokemon-Scarlet-Violet-ex-Special-Set`
- [ ] `YGO-JP-WPP5-BST` → `Yu-Gi-Oh-boosters-World-Premiere-Pack-2024`
- [ ] `MTG-JP-AFRS-BST` → `Magic-the-Gathering-Adventures-in-the-Forgotten-Realms`
- [ ] `PKM-KR-HWA-BST` → `Pokemon-boosters-Hot-Wind-Arena`

### Manufacturer-дублі `?route=…` (TECH-012, генератор sitemap)
- [ ] `https://boostershop.website/pokemon-company?route=product/manufacturer.info`  → `…/pokemon-company`
- [ ] `https://boostershop.website/Bandai?route=product/manufacturer.info`  → `…/Bandai`
- [ ] `https://boostershop.website/Konami?route=product/manufacturer.info`  → `…/Konami`
- [ ] `https://boostershop.website/Wizards-of-the-Coast?route=product/manufacturer.info`  → `…/Wizards-of-the-Coast`

---

## ✅ Уже в індексі / подано — ПРОПУСТИТИ (не витрачати квоту)
- `https://boostershop.website/` (головна)
- `https://boostershop.website/product/Pokemon-boosters-Ninja-Spinner`
- `https://boostershop.website/product/Pokemon-booster-box-Super-Electro-Breaker`
- `https://boostershop.website/product/Pokemon-booster-box-Munics-Zero`
- `https://boostershop.website/product/pokemon-tcg-nabory-MBOX` (вже подано)

---

## День 1 — категорії-хаби (10)
- [ ] `https://boostershop.website/catalog/Pokemon`
- [ ] `https://boostershop.website/catalog/Pokemon/bustery-pokemon`
- [ ] `https://boostershop.website/catalog/Pokemon/Pokemon-booster-box`
- [ ] `https://boostershop.website/catalog/Pokemon/pokemon-tcg-nabory`
- [ ] `https://boostershop.website/catalog/One-Piece`
- [ ] `https://boostershop.website/catalog/One-Piece/One-Piece-Boosters`
- [ ] `https://boostershop.website/catalog/One-Piece/one-piece-nabory-ta-boksy`
- [ ] `https://boostershop.website/catalog/more-tcg`
- [ ] `https://boostershop.website/catalog/more-tcg/Yu-Gi-Oh`
- [ ] `https://boostershop.website/catalog/more-tcg/magic-the-gathering`

## День 2 — топ-товари (10)
- [ ] `https://boostershop.website/product/Pokemon-booster-box-Hot-Wind-Arena`
- [ ] `https://boostershop.website/product/OnePiece-booster-box-OP15`
- [ ] `https://boostershop.website/product/Pokemon-boosters-Black-Bolt`
- [ ] `https://boostershop.website/product/Pokemon-boosters-White-Flare`
- [ ] `https://boostershop.website/product/Pokemon-boosters-Mega-Dream-EX`
- [ ] `https://boostershop.website/product/Pokemon-boosters-Mega-Brave`
- [ ] `https://boostershop.website/product/Pokemon-boosters-Mega-Symphonia`
- [ ] `https://boostershop.website/product/Pokemon-Japanese-outlet-booster`
- [ ] `https://boostershop.website/product/Pokemon-boosters-Munics-Zero`
- [ ] `https://boostershop.website/product/One-Piece-Boosters-Promotion-Pack-V7`

## День 2b — товари з оновленим slug (9) — подати ПІСЛЯ зміни keyword
- [ ] `https://boostershop.website/product/Yu-Gi-Oh-boosters-Quarter-Century-Art-Collection`
- [ ] `https://boostershop.website/product/One-Piece-Boosters-OP-08-Two-Legends`
- [ ] `https://boostershop.website/product/One-Piece-Mystery-Box`
- [ ] `https://boostershop.website/product/Pokemon-boosters-Inferno-X`
- [ ] `https://boostershop.website/product/Pokemon-Mega-Gallade-EX-Special-Set`
- [ ] `https://boostershop.website/product/Pokemon-Scarlet-Violet-ex-Special-Set`
- [ ] `https://boostershop.website/product/Yu-Gi-Oh-boosters-World-Premiere-Pack-2024`
- [ ] `https://boostershop.website/product/Magic-the-Gathering-Adventures-in-the-Forgotten-Realms`
- [ ] `https://boostershop.website/product/Pokemon-boosters-Hot-Wind-Arena`

## День 3 — решта товарів (7) + інфо (3)
- [ ] `https://boostershop.website/product/One-Piece-Boosters-EB-03`
- [ ] `https://boostershop.website/product/One-Piece-Boosters-OP-07`
- [ ] `https://boostershop.website/product/One-Piece-Boosters-OP-10`
- [ ] `https://boostershop.website/product/One-Piece-Boosters-OP-11`
- [ ] `https://boostershop.website/product/One-Piece-Boosters-OP-12`
- [ ] `https://boostershop.website/product/One-Piece-Boosters-OP-14`
- [ ] `https://boostershop.website/product/One-Piece-Boosters-OP-15`
- [ ] `https://boostershop.website/information/pro-nas`
- [ ] `https://boostershop.website/information/oplata-i-dostavka`
- [ ] `https://boostershop.website/information/publichna-oferta`

## День 4 — інфо (2)
- [ ] `https://boostershop.website/information/Obmin-i-povernennya`
- [ ] `https://boostershop.website/information/original-garanty`

---

### Примітки
- Yu-Gi-Oh та Magic-the-Gathering категорії включені, бо в каталозі вже є товари відповідних брендів (YGO-*, MTG-*) — після фіксу їхніх slug вони підсилять ці категорії.
- Інфо-сторінки — низький пріоритет (некомерційні), тому в кінці.
- Після фіксу SKU-/é-slug і manufacturer-URL — додати їх у подачу окремим заходом.
- Подавай у межах денної квоти; якщо GSC ріже — переноси залишок на наступний день.
