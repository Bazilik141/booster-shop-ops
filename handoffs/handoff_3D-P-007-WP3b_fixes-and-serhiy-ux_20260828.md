# Codex Handoff — 3D-P-007 WP3b: fix the package and rework Serhiy's UI

Date: 2026-08-28 | Parent: `3D-P-007` WP3
Executor: Codex · model=Sol · effort=xhigh — four surfaces (client, local server,
launcher, packaging), three defects already reproduced on live hardware, and a
UI rework driven by the owner's first real use of the page. Owner assigned Codex.

Review this supersedes nothing: `diagnostics/3D-P-007-WP3_patch-review_20260828.md`
carries the evidence for every defect below and stays the reference.

## How this was found

The owner ran the package on his own machine for the first time. Everything in
Part A and Part B is reproduced behaviour, not analysis. `npm test` was green
throughout — the suite drives the API routes against a fake API and never opens
the page in a browser, so none of it was visible until now.

**Add browser evidence to the definition of done for this work package.** A green
suite is no longer sufficient.

---

# Part A — three blocking defects in the shipped package

## A1. `.bat` files have LF line endings

Verified on the owner's copy: 278 bytes, **0 CRLF, 9 bare LF**, no BOM. `cmd.exe`
requires CRLF; with LF it loses line boundaries. Observed:

```
'Policy' is not recognized as an internal or external command
'?тисни' is not recognized as an internal or external command
```

`.gitattributes` already declares `*.bat text eol=crlf`. The WP3 files are
uncommitted, so git never normalised them, and the build script copies the
working tree verbatim.

**Fix:** CRLF on all `.bat`.

## A2. `.bat` files contain non-ASCII after `chcp 65001`

`cmd.exe` tracks its position in a batch file by byte offset and re-decodes after
a codepage change, desynchronising on multi-byte characters. `title Booster Shop
— 3D-друк` and `echo Натисни…` are both after `chcp`.

**Fix:** the `.bat` files must be **ASCII-only**. All Ukrainian text belongs in
the PowerShell scripts, which set `[Console]::OutputEncoding` themselves. A bare
`pause` prints the system-localised prompt and needs no literal text.

## A3. `.ps1` files have no UTF-8 BOM

`Запустити.bat` invokes `powershell.exe` — Windows PowerShell **5.1** — which
reads a BOM-less script in the system ANSI codepage. Every Ukrainian literal
became mojibake and the script failed to parse:

```
$Host.UI.RawUI.WindowTitle = "Booster Shop вЂ” 3D-РґСЂСѓРє"
Unexpected token '3D-РґСЂСѓРє"…'
```

The report's `PowerShell parser: 0 errors` check passed because it ran under
PowerShell 7, which defaults to UTF-8. **The artefact was validated in a
different runtime from the one that runs it.** Switching to `pwsh` is not an
option — PowerShell 7 is not on a stock Windows install.

**Fix:** UTF-8 **with BOM** on every `.ps1`, plus CRLF for consistency with
`.gitattributes`.

## A4. Packaging guard (all three above)

`scripts/build-serhiy-3dp-package.ps1` must **refuse to build** when any file it
is about to copy fails:

- `.bat` — CRLF and ASCII-only;
- `.ps1` — UTF-8 BOM present, CRLF;

and must then re-open the produced zip and re-run the same checks against its
contents. The source tree passing is not evidence that the package does.

---

# Part B — the server and client defects

## B1. The server dies on any 404 — *blocking*

```
Error: Not found.
    at fail (…/app/server.mjs:53:17)
    at serveStatic (…/app/server.mjs:407:31)
    at Server.<anonymous> (…/app/server.mjs:442:42)
  status: 404, code: 'NOT_FOUND'
```

`server.mjs:442` is

```js
if (request.method === "GET") return serveStatic(response, url.pathname);
```

inside the handler's `try`. In an `async` function, `return promise` leaves the
`try` before the promise settles, so the `catch` never sees the rejection — it
becomes an unhandled rejection and Node ≥15 kills the process. The other fourteen
routes are `return json(…, await …)` and are covered; this is the one that is not.

`.ico` is outside `MIME`, so **every browser kills the server by requesting
`/favicon.ico` right after loading the page.** That produced every symptom the
owner saw: page renders, then «Failed to fetch», then `ERR_CONNECTION_REFUSED`.

The line is unchanged since the original 2026-08-01 package.

**Fix:** `return await serveStatic(…)`. Also serve `204` for `/favicon.ico` so a
normal browser request is not an error path, and add
`process.on('unhandledRejection')` logging so a future escape is visible instead
of fatal.

## B2. Settings fields are filled and saved through the wrong controls — *blocking, live-data risk*

The owner's screenshot shows printer power `0,11` in the **Амортизація** field
and electricity `4,32` in the **Плановий брак** field, with the first two fields
empty, plus the banner «Cannot set properties of undefined (setting 'value')».

Cause: the settings inputs are named `"2"`, `"3"`, `"4"`, `"5"` and the code does
`form.elements[String(row)]`. **`HTMLFormControlsCollection` resolves a
numeric-looking string as an index, not a name.** So:

| requested row | resolves to | result |
|---|---|---|
| `"2"` | index 2 → Амортизація | gets `values[0]` = power |
| `"3"` | index 3 → Плановий брак | gets `values[1]` = electricity |
| `"4"` | index 4 → the submit **button** | silently sets `button.value` |
| `"5"` | index 5 → **undefined** | throws |

The throw happens inside `fillSettings()`, which `render()` calls **before**
`fillProductForm()`, `fillStockForm()` and `renderInformation()` — so none of
those ever run. **This one bug is the owner's items 3, 5 and 7 together**, and it
is why the tiles have data (`renderOverview()` runs earlier) while every
Інформація block is empty.

⚠ **It is also a live-write hazard.** The save handler reads through the same
mis-resolved elements: typing a value into «Плановий брак» and pressing save
sends it as **row 3, electricity price**. `SETTINGS_VALUE_BOUNDS_3DP` catches
grossly out-of-range values but not plausible ones, and these four constants
multiply into `Номенклатура!K` and therefore into Serhiy's accrual.

**Fix:** rename the controls to non-numeric names (`setting_2` …) or select with
`form.querySelector('[name="2"]')`. Fix both `fillSettings()` and the submit
handler. Add a test that fills a fake settings payload and asserts each of the
four values lands in its own field, and that a change in one field sends that
field's own row.

## B3. Fixture dropdown is empty

Answering the owner's question directly: the fixture list is **not** per-person.
It comes from `Фурнітура_довідник` through `3dp_information_bootstrap` — one
shared reference sheet, the same rows for owner and Serhiy. `setFixtureOptions()`
runs before the B2 crash, so the crash is not the reason it is empty.

**Investigate and report:** whether `Фурнітура_довідник` currently holds rows at
all, and whether the projection returns them under the Serhiy credential. Do not
change the API. If the sheet is empty, say so — that is an owner data task, not a
code fix.

---

# Part C — owner change requests

All UI text is Ukrainian and faces Serhiy.

## C1. Header

- «ЛОКАЛЬНИЙ ДОСТУП СЕРГІЯ» → **«Зроблено індусами для Сергія»**.
  ⚠ The owner wrote «Зрорблено» — treated as a typo and corrected. If the typo is
  deliberate, he will say so; do not ship it unasked.
- Remove the heading «Booster 3D-друк».
- Remove the line «Тільки проєктовані дані 3D-P API. Без CRM і прямого доступу до
  Google Sheets.»

## C2. Tiles

- Remove **Надруковано** and **Брак** (and their lines in `renderOverview`).
- **Наявно** → **«Товару на складі шт.»**
- Keep Активні SKU and Нараховано цього місяця.

## C3. Calculator zone

- Remove the **Розрахувати** button. The per-unit figures and the cost recalculate
  **live** as fields change, the way the owner dashboard does it.
  **Do not duplicate the formula in the browser.** Serve `lib/calculator.mjs` as a
  static module and `import` it in `public/app.js`, so one implementation feeds
  both the client preview and the server. A second copy of the cost formula is
  exactly the drift the draft-type contract test was added to prevent.
- **Зберегти чернетку і per-unit** → **«Зберегти розрахунок»**.
- Remove the sentence «У G/H/I/J записуються лише per-unit значення.»
- **Move the whole «Вироблена партія» block into the calculator** as a
  «Виготовити партію» action. The two forms already ask for nearly the same
  inputs; the calculator's quantity, total weight and total time feed the
  manufacture call.
- **Remove the «Брак, шт» input.** Owner decision: planned defect is already in
  the cost fraction, and a real miscount is handled through the stock correction.
  ⚠ Consequence to state in the report: actual defective units stop being written
  to `Друк-лог!E`, so `Наявність` «Брак всього» stays at zero and stock accuracy
  now depends entirely on manual corrections. That is the owner's call; record it.
- Keep the idempotent `request_id` behaviour exactly as it is.

## C4. Settings block

Remove «Межі перевіряє 3D-P API. Кожен запис використовує очікуване поточне
значення.» Fix B2 before touching anything else here.

## C5. Products zone

- «Поля виробу» → **«Встановити ціну»**.
- Button «Зберегти Q/R/S» → **«Зберегти»**.
- «Фактична наявність» → **«Редагувати залишки»**.
- «Поточна кількість» → **«Записано на складі»**.
- «Фактично пораховано, шт» → **«Фактична кількість»**.
- «Причина» → **«Причина коригування складу»**.

## C6. Draft form

Owner decision, 2026-08-28: **keep the «Тип» dropdown, hide the rest.**

- Hide from the form: Франшиза (C), Трек (E), Статус/етап (F), Дата оновлення (L).
- **Keep «Тип» (D) visible.** It is required by the API and it is what produces
  the prefix/category suggestion the owner relies on when he assigns the article —
  the hybrid-assignment decision of 2026-08-16.
- Fill the hidden fields automatically: `L` = today's date from the system; the
  others a neutral placeholder the owner's dashboard overwrites when he promotes
  the draft to an active SKU. State in the report exactly what placeholder you
  used for each.
- «Фурнітура, грн/шт» → **«Додати вартість розхідника грн»**.
- Remove both explanatory texts: «Артикул тут не присвоюється…» and «Чернетка
  отримає технічний ключ DRAFT-…».
- The result still must never present a complete article as assigned. With the
  explanatory text gone, keep the suggestion labelled as a suggestion.

## C7. Batch draft ownership

Owner decision, 2026-08-28: **leave the storage as it is.** The API deliberately
keys the owner's draft as `SKU` and Serhiy's as `serhiy::SKU`; they are separate
by design and no API change is authorised.

Client-side only: label the loaded draft so it is obvious it is Serhiy's own, and
say plainly when none exists yet for that SKU. The owner's draft is not visible
here and that is intended.

## C8. No console window — owner request, answered

The owner asked whether the window can be gone entirely, with the session living
until the PC shuts down or he closes the browser. **Yes, with one caveat.**

- **Normal launch: no window at all.** Use a `.vbs` shim
  (`WScript.Shell.Run …, 0, False`) rather than `-WindowStyle Hidden`, so there is
  not even a console flash.
- **First run is the exception**: the credential prompt needs a visible window.
  If either variable is missing, show a window, prompt, save, then continue
  hidden. If both are present, never show anything.
- **Already running:** `Запустити.bat` must detect port 3107 in use and simply
  open the browser, instead of the current error-and-stop.
- **Lives until shutdown** by default.
- **Closing the browser:** there is no reliable way for a local server to observe
  a closed tab. Implement a heartbeat — the page pings a lightweight endpoint
  every 30 s, the server exits after ~5 minutes with no ping. That satisfies
  "until he closes the browser" approximately, not instantly. Consequence to put
  in Serhiy's instructions: if he closes the page and comes back later, he
  double-clicks the launcher again.
- `Змінити токен.bat` and `Перевірити заборони.bat` **keep** their visible
  windows — they exist to show output.
- Update `Прочитай мене.txt`: no black window any more, and how to stop/restart.

---

## What NOT to touch

- `3d-print/apps-script-3dp-api/**`. No API change is authorised in this package —
  C7 was explicitly declined. If something here appears to need one, stop and report.
- `dashboard/booster-dashboard.html`, `crm/apps-script/**`.
- `ROADMAP_TASKS`. Claude writes Notion status and the mirror after owner QA.
- The write semantics already reviewed and accepted: `new_value` for stock,
  `printed_by` pinned to `Сергій`, the `B2:B5` identity check in the launcher,
  the append-once payout behaviour, `expected_current` on every write.
- `.env`, `.env.review`, `scripts/.env`, `client_secret.json`. Do not add a `.env`
  reader; drop `.env.example` from the shipped package (carried finding).

## Acceptance criteria

- [ ] `npm test` green, including the new settings-mapping test.
- [ ] **The package is built, extracted, launched, and a real browser loads the page with data in it.** Paste the evidence. This is the criterion the previous round lacked.
- [ ] The server survives a `/favicon.ico` request and any other 404, and keeps serving afterwards.
- [ ] Each of the four settings values appears in its own field; changing one sends that one's row.
- [ ] Every Інформація block renders its table.
- [ ] No console window on a launch where the credentials already exist; a window only on first run.
- [ ] Relaunching while already running opens the browser instead of erroring.
- [ ] Every text change in Part C is applied exactly as written.
- [ ] The build refuses a package whose `.bat`/`.ps1` fail the encoding checks, and the check is re-run against the finished zip.
- [ ] No credential in any file, log line, console echo or commit.

## Risks

- **The settings save path can write a wrong value to a live row.** Until B2 is fixed nobody should press «Зберегти змінені». Those four constants feed `Номенклатура!K` and Serhiy's accrual.
- Removing the defect input changes what the workbook records — see C3.
- Live workbook, no staging. Any QA write is a production write; use a designated history-free test SKU and not `FIG-LUFFY-410`.
- Report as `diagnostics/3D-P-007-WP3b_fixes-and-serhiy-ux_report_20260828.md`. Do not commit, push, deploy, or write Notion.
