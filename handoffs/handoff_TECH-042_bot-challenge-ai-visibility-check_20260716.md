# Handoff — TECH-042: Bot-challenge / AI-visibility diagnostic check

Date: 2026-07-16 | Parent: none (spin-off з TECH-005-DEEP спостереження) | Prepared by: Claude · Recipient: Owner (+ Claude hands-on, за потреби Codex)

Статус: **Заплановано / беклог, Medium, не терміново.** Google indexation працює нормально (TECH-005-DEEP closed 2026-07-06, 52/57 проіндексовано) — це НЕ реанімація тієї задачі, а окреме легке read-only спостереження.

---

## SEO / RISK GATE (preflight)
- **Risk: LOW.** Read-only діагностика, жодних змін на сайті/сервері/WAF на цьому етапі.
- Якщо діагностика знайде реальний блок — фікс іде окремим handoff зі своїм risk-gate (потенційно .htaccess/WAF risky zone).
- sitemap/robots/canonical — не чіпати.

---

## 1. Task ID
**TECH-042** — перевірити, чи shared-хостинг сайту показує bot-challenge/verification екран ("Please wait while your request is being verified…") для певних User-Agent/IP-класів, і якщо так — для кого саме.

## 2. Context

Власник (17.07.2026) поділився: коли ChatGPT намагався щось перевірити на сайті напряму, у відповідь прийшло "Please wait while your request is being verified…" замість контенту магазину — типовий текст challenge-сторінки anti-bot захисту.

Що вже відомо з TECH-005-DEEP (закрито 2026-07-06, watch-only):
- Домен **не за Cloudflare** — A-запис веде напряму на hosting IP 45.94.156.222, NS hostiq.ua, заголовки без `cf-ray`/`cf-cache-status` (перевірено 2026-06-06).
- На хостингу підтверджено активний **ModSecurity WAF**; фінальний A/B-тест 3 липня показав, що на Google-InspectionTool він **не впливає** ("WAF conclusively not involved").
- Claude зробив живий фетч `https://boostershop.website/` 16.07.2026 — отримав повний нормальний HTML, жодного challenge-екрану.

**Робоча гіпотеза:** це не Cloudflare і, ймовірно, не той самий механізм, що досліджували в TECH-005-DEEP. Shared cPanel-хостинги часто мають додатковий шар (напр. Imunify360 "Herald"-подібний JS-challenge, або окреме правило ModSecurity/анти-DDoS), який вибірково челенджить нерозпізнані/датацентрові UA чи IP (включно з AI browsing agents), при цьому маючи allowlist для верифікованих Googlebot IP — це пояснювало б, чому GSC indexation в нормі, а зовнішній AI-агент побачив challenge. Це не підтверджено, лише гіпотеза, яку варто перевірити фактами, а не здогадом.

**Чому це важливо, навіть якщо Google не постраждав:** зростаюча тема AI-visibility/GEO (генеративні пошуковики — ChatGPT, Perplexity тощо, дедалі частіше приводять трафік і цитують сайти напряму). Якщо їхні краулери/browsing-агенти систематично отримують challenge замість контенту — це втрачена видимість у каналі, який Google-орієнтовані метрики (GSC) взагалі не бачать.

## 3. Goal
Read-only відповідь на два питання:
1. Чи справді існує challenge-сторінка на сайті (відтворити стабільно, не одноразовий глюк)?
2. Якщо так — для яких умов (конкретний UA, клас IP, частота запитів, HTTP-версія) вона з'являється, а для яких (Googlebot, звичайний браузер) — ні?

Без жодної зміни конфігурації на цьому етапі.

## 4. What to change
Нічого на сайті/сервері. Тільки спостереження:

- Запустити curl-матрицю (за зразком Block D/E з TECH-005-DEEP runbook) з різними UA: default curl, `Mozilla/5.0` звичайний браузер, `Googlebot/2.1`, `GPTBot`, `ChatGPT-User`, `PerplexityBot`, `ClaudeBot` — на `/` і 1-2 товарні сторінки.
- Перевірити заголовки відповіді на сигнатури WAF/bot-protection: `server`, `x-litespeed-*`, будь-що з `imunify`/`sucuri`/`incapsula`/`bitninja` в тілі чи заголовках challenge-сторінки (якщо вона з'явиться).
- Якщо є доступ до cPanel → перевірити панель Security/Imunify360 (якщо є) на предмет активних bot-challenge правил і логів спрацювань за останні дні.
- Порівняти: чи корелює час репорту власника з якимось спрацюванням у логах.

## 5. Do not touch
- Жодних змін у WAF/Imunify/ModSecurity правилах, `.htaccess`, sitemap, robots, redirects — це чисто спостереження.
- Якщо в процесі знайдеться конкретне правило-винуватець — не вимикати й не редагувати на місці; зафіксувати і завести окремий handoff з власним risk-gate.

## 6. Likely files / areas
Немає файлових змін. Сервер-side: cPanel Security панель (потрібен доступ власника), access logs, evtl. лист у хостинг-підтримку за зразком шаблону з TECH-005-DEEP (якщо знайдеться конкретне правило).

## 7. Acceptance criteria
- Таблиця "UA/умова → challenge чи ні" мінімум для 5-6 UA.
- Якщо challenge відтворено — назва конкретного механізму/правила (наскільки видно з панелі/логів) або чіткий висновок "не вдалося ідентифікувати джерело, потрібен тікет хостингу".
- Якщо НЕ відтворено жодного разу — фіксуємо як "не підтверджено, можливо транзієнтний глюк на боці ChatGPT", закриваємо без подальших дій.

## 8. QA / smoke test
Не застосовується — read-only, змін немає.

## 9. Rollback note
Не потрібен — жодних змін не вноситься.

## 10. Recommended status after execution
- Зараз: **Заплановано / беклог**, Medium, без дедлайну.
- Після прогону curl-матриці → або "Закрито, не підтверджено", або новий handoff на конкретний фікс (з власним risk-gate, ймовірно .htaccess/WAF risky zone).

---
_References: TECH-005-DEEP (closed, watch-only — контекст і precedent по WAF/хостингу), bs-seo-risk-gate (LOW на цьому етапі). Пов'язана тема: AI visibility / GEO — окремий напрям, не Google-SEO._
