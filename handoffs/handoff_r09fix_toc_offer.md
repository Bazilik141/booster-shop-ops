# HANDOFF TO CODEX — R-09-FIX + Оферта
_TOC anchor bug fix + Публічна оферта редакція 26.05.2026_
_Підготовлено: 2026-05-26_

---

## ЧАСТИНА A — TOC BUG FIX

### Task ID
R-09-FIX (баг після R-09 патчу)

### Проблема
Клік на TOC-навігацію в `information.twig` перекидує на головну замість скролу до секції.

**Причина:** OpenCart 4 має `<base href="https://boostershop.website/">` у `<head>`. JS створює `<a href="#cp-0">` — браузер резолвить через base tag. Якщо відповідний `id` не знайдено, переходить на `base_url + "#cp-0"` = головна.

### Що змінити

У файлі `catalog/view/template/common/information.twig` знайти рядок:

```js
a.href = '#' + id;
```

Замінити на:

```js
a.href = window.location.href.replace(/#.*$/, '') + '#' + id;
```

**Лише ця одна рядка.** Нічого більше не чіпати.

### Що НЕ чіпати
Весь інший JS і HTML `information.twig`. Жодні інші файли.

### QA
1. Відкрити будь-яку інфо-сторінку з h2-заголовками (наприклад, Публічна оферта).
2. Клікнути на пункт TOC.
3. Сторінка має проскролити до відповідної секції, а не перейти на головну.
4. Перевірити на desktop + mobile (кнопка навігації браузера «Назад» після кліку — не повинна вести на головну).

### Rollback
Відновити попереднє значення `a.href = '#' + id;` у тому ж файлі.

---

## ЧАСТИНА B — ПУБЛІЧНА ОФЕРТА

### Task
Оновити вміст сторінки «Публічна оферта» в OpenCart до нової редакції від 26.05.2026.

### Кроки для Codex

**Крок 1.** Знайти `information_id` сторінки «Публічна оферта» в БД:

```sql
SELECT information_id, title
FROM ocp5_information_description
WHERE language_id = 4
ORDER BY information_id;
```

Визначити `information_id` для рядка з title «Публічна оферта» (або подібним). Позначити як `[OFFER_ID]`.

**Крок 2.** Зробити бекап поточного вмісту перед заміною:

```sql
SELECT information_id, title, description
FROM ocp5_information_description
WHERE information_id = [OFFER_ID] AND language_id = 4;
```

Зберегти результат (на випадок rollback).

**Крок 3.** Оновити поле `title` (якщо поточний відрізняється):

```sql
UPDATE ocp5_information_description
SET title = 'Публічна оферта'
WHERE information_id = [OFFER_ID] AND language_id = 4;
```

**Крок 4.** Оновити `description` новим HTML (вставити вміст з файлу `offer_html_20260526.html` з папки проєкту).

```sql
UPDATE ocp5_information_description
SET description = '[HTML з offer_html_20260526.html]'
WHERE information_id = [OFFER_ID] AND language_id = 4;
```

> **Примітка:** HTML зберігається у полі `description` як є (не entity-encoded). Information pages в OC4 зберігають сирий HTML на відміну від product descriptions.

**Крок 5.** Очистити OC4 кеш після оновлення (Admin → System → Cache → Clear).

### Що НЕ чіпати
- Поле `sort_order`, `status`, `bottom` у таблиці `ocp5_information` — не змінювати.
- Інші `information_id` — не чіпати.
- `language_id = 1` (English) — якщо є, не чіпати (або дублювати оновлення, якщо потрібно).
- `sitemap.xml`, `robots.txt`, будь-які інші файли — поза scope.

### Файл з HTML
`offer_html_20260526.html` — у папці проєкту (Booster Shop). Готовий HTML для вставки в `description`.

### QA
1. Відкрити сторінку Публічна оферта на сайті.
2. Перевірити: заголовки h2 з 1 по 18, всі секції присутні.
3. TOC генерується і показує 18 пунктів (після фіксу з Частини A).
4. Кліки по TOC — скролять до секцій (не редирект).
5. Редакція оферти внизу: «26 травня 2026 року».
6. Перевірити секцію 8 (Передзамовлення) — всі підпункти 8.1–8.7 на місці.
7. Перевірити що немає «оскільки до цього моменту...» у тексті (ця фраза видалена).

### Rollback
Відновити `description` зі збереженого бекапу (Крок 2) через той самий SQL UPDATE.

### Рекомендований статус після виконання
| Поле | Значення |
|---|---|
| Задача | R-09-FIX + Оферта |
| Після live-QA власника | Done |
| Next | R-10 і R-11 (контент інших інфо-сторінок) |
