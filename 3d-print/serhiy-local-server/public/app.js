const state = { data: null, lastCalculation: null };
const money = new Intl.NumberFormat("uk-UA", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const number = new Intl.NumberFormat("uk-UA", { maximumFractionDigits: 3 });
const draftKeys = ["quantity", "total_weight_g", "total_print_time_h", "spool_weight_g", "spool_price_uah"];
const printTime = globalThis.BoosterPrintTime;

const byId = (id) => document.getElementById(id);
const status = (message, error = false) => {
  const element = byId("status");
  element.textContent = message;
  element.classList.toggle("error", error);
};

async function request(url, body) {
  const response = await fetch(url, body ? {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  } : undefined);
  const payload = await response.json().catch(() => ({ ok: false, error: "Некоректна відповідь локального сервера." }));
  if (!response.ok || !payload.ok) throw new Error(payload.error || "Запит не виконано.");
  return payload;
}

function formObject(form) {
  return Object.fromEntries(new FormData(form).entries());
}

function printTimeResult(input) {
  if (!printTime) throw new Error("Не завантажився спільний парсер часу друку.");
  return printTime.parse(input.value);
}

function refreshPrintTimeHint(input) {
  const hint = document.querySelector(`[data-print-time-hint="${input.name}"]`);
  if (!hint) return;
  const parsed = printTimeResult(input);
  if (parsed.blank) {
    hint.textContent = "Можна: 1:30, 1 год 30 хв або 1,5.";
    hint.classList.remove("error");
    return;
  }
  hint.textContent = parsed.ok ? `= ${printTime.display(parsed.hours)}` : `⚠ ${parsed.error}`;
  hint.classList.toggle("error", !parsed.ok);
}

function normalisePrintTimeField(form, name) {
  const input = form.elements[name];
  const parsed = printTimeResult(input);
  if (!parsed.ok || parsed.blank || !(parsed.hours > 0)) {
    throw new Error(parsed.error || `${input.closest("label")?.textContent || "Час друку"}: вкажіть значення більше нуля.`);
  }
  input.value = String(parsed.hours);
  refreshPrintTimeHint(input);
  return parsed.hours;
}

function bindPrintTimeInputs() {
  document.querySelectorAll("[data-print-time-input]").forEach((input) => {
    input.addEventListener("input", () => refreshPrintTimeHint(input));
    input.addEventListener("blur", () => {
      const parsed = printTimeResult(input);
      if (parsed.ok && !parsed.blank) input.value = String(parsed.hours);
      refreshPrintTimeHint(input);
    });
    refreshPrintTimeHint(input);
  });
}

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#039;", '"': "&quot;" }[char]));
}

function setSkuOptions() {
  const options = state.data.skus.map((row) => `<option value="${escapeHtml(row.SKU)}">${escapeHtml(row.SKU)} — ${escapeHtml(row["Назва виробу"] || "без назви")}</option>`).join("");
  document.querySelectorAll(".sku-select").forEach((select) => {
    const selected = select.value;
    select.innerHTML = `<option value="">Оберіть SKU</option>${options}`;
    if (state.data.skus.some((row) => String(row.SKU) === selected)) select.value = selected;
  });
}

function setFixtureOptions() {
  const selected = byId("fixture-select").value;
  byId("fixture-select").innerHTML = `<option value="">Без фурнітури / очистити</option>${state.data.fixtures.map((row) => {
    const name = row["Назва фурнітури"];
    const price = Number(row["Ціна, грн/шт"] || 0);
    return `<option value="${escapeHtml(name)}">${escapeHtml(name)} — ${money.format(price)} грн/шт</option>`;
  }).join("")}`;
  if ([...byId("fixture-select").options].some((option) => option.value === selected)) byId("fixture-select").value = selected;
}

function renderOverview() {
  const overview = state.data.overview;
  byId("sku-count").textContent = number.format(overview.sku_count || 0);
  byId("available").textContent = number.format(overview.available || 0);
  byId("printed").textContent = number.format(overview.printed || 0);
  byId("defects").textContent = number.format(overview.defects || 0);
  byId("accrued").textContent = `${money.format(overview.accrued_serhiy_current_month || 0)} грн`;
  const settings = state.data.settings;
  byId("settings").innerHTML = [
    `Потужність: ${number.format(settings.printer_power_kw)} кВт`,
    `Електроенергія: ${money.format(settings.electricity_price_uah_per_kwh)} грн/кВт·год`,
    `Амортизація: ${money.format(settings.amortization_uah_per_hour)} грн/год`,
  ].map((item) => `<span>${item}</span>`).join("");
}

function renderSkuRows() {
  byId("sku-rows").innerHTML = state.data.skus.map((row) => `<tr>
    <td>${escapeHtml(row.SKU)}</td>
    <td>${escapeHtml(row["Назва виробу"] || "—")}</td>
    <td>${escapeHtml(row.Статус || "—")}</td>
    <td>${escapeHtml(row.availability?.["Наявно зараз, шт"] ?? "—")}</td>
    <td>${escapeHtml(row["Фурнітура (ціна-довідка), грн/шт"] ?? "—")}</td>
  </tr>`).join("") || '<tr><td colspan="5">Немає доступних SKU.</td></tr>';
}

function renderPrintLog() {
  byId("print-log-rows").innerHTML = state.data.print_log.map((row) => `<tr data-row="${Number(row.row_number)}">
    <td>${escapeHtml(row.Дата || "—")}</td>
    <td>${escapeHtml(row.SKU || "—")}</td>
    <td>${escapeHtml(row["Надруковано, шт"] ?? "—")}</td>
    <td>${escapeHtml(printTime.display(row["Час друку факт, год"]))}</td>
    <td><input class="defect-input" type="number" min="0" step="1" value="${escapeHtml(row["Брак, шт"] ?? 0)}"></td>
    <td><button class="save-defect" type="button" data-expected="${escapeHtml(row["Брак, шт"] ?? "")}">Зберегти брак</button></td>
  </tr>`).join("") || '<tr><td colspan="6">Активних записів ще немає.</td></tr>';
}

function renderPayouts() {
  const rows = state.data.payouts || [];
  const keys = rows.length ? Object.keys(rows[0]).filter((key) => key !== "row_number") : [];
  byId("payout-head").innerHTML = keys.length ? `<tr>${keys.map((key) => `<th>${escapeHtml(key)}</th>`).join("")}</tr>` : "";
  byId("payout-rows").innerHTML = rows.map((row) => `<tr>${keys.map((key) => `<td>${escapeHtml(row[key] ?? "—")}</td>`).join("")}</tr>`).join("") || '<tr><td>Немає доступних записів виплат.</td></tr>';
}

function renderOpenQuestions() {
  const entries = (state.data.open_questions || []).map((row) => String(row?.[0] ?? "").trim()).filter(Boolean);
  byId("open-questions").innerHTML = entries.map((entry) => `<li>${escapeHtml(entry)}</li>`).join("") || "<li>У вказаному діапазоні немає записів.</li>";
}

function render() {
  setSkuOptions();
  setFixtureOptions();
  renderOverview();
  renderSkuRows();
  renderPrintLog();
  renderPayouts();
  renderOpenQuestions();
}

function renderCalculation(calculation) {
  state.lastCalculation = calculation;
  byId("save-batch").disabled = false;
  const base = calculation.costs.base_uah;
  byId("calculation").classList.remove("empty");
  byId("calculation").innerHTML = `<strong>За одиницю:</strong>
    <ul>
      <li>Вага: ${number.format(calculation.per_unit.weight_g)} г</li>
      <li>Час: ${escapeHtml(printTime.display(calculation.per_unit.time_hours))}</li>
      <li>Матеріал: ${money.format(calculation.costs.material_uah)} грн</li>
      <li>Електроенергія: ${money.format(calculation.costs.electricity_uah)} грн</li>
      <li>Амортизація: ${money.format(calculation.costs.amortization_uah)} грн</li>
      <li><strong>Базова собівартість Сергія: ${money.format(base)} грн</strong></li>
    </ul><p class="muted">Фурнітура додається окремо після друку. Праця та брак у цю формулу не входять.</p>`;
}

async function loadDraft(sku) {
  if (!sku) return;
  status("Завантажую збережену чернетку партії…");
  const payload = await request(`/api/batch-draft?sku=${encodeURIComponent(sku)}`);
  const form = byId("batch-form");
  draftKeys.forEach((key) => { form.elements[key].value = payload.values?.[key] ?? ""; });
  refreshPrintTimeHint(form.elements.total_print_time_h);
  state.lastCalculation = null;
  byId("save-batch").disabled = true;
  status(payload.found ? "Чернетку партії завантажено. Перевірте й розрахуйте." : "Для SKU ще немає чернетки. Введіть дані партії.");
}

async function reload() {
  status("Оновлюю дані…");
  state.data = await request("/api/bootstrap");
  render();
  status("Дані отримано через 3D-P API.");
}

byId("reload").addEventListener("click", () => reload().catch((error) => status(error.message, true)));

byId("batch-form").elements.sku.addEventListener("change", (event) => {
  loadDraft(event.target.value).catch((error) => status(error.message, true));
});

byId("batch-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  try {
    status("Розраховую за поточними глобальними налаштуваннями…");
    normalisePrintTimeField(event.currentTarget, "total_print_time_h");
    const payload = await request("/api/calculate", formObject(event.currentTarget));
    renderCalculation(payload.calculation);
    status("Розрахунок готовий. Перевірте значення перед збереженням.");
  } catch (error) { status(error.message, true); }
});

byId("save-batch").addEventListener("click", async () => {
  try {
    const form = byId("batch-form");
    status("Зберігаю raw-чернетку й per-unit значення у SKU…");
    normalisePrintTimeField(form, "total_print_time_h");
    const payload = await request("/api/save-batch", formObject(form));
    const detail = payload.already_current ? "Чернетка і SKU вже актуальні." : `Чернетка збережена; оновлено SKU: ${payload.cells_updated.join(", ") || "без змін"}.`;
    status(detail);
    await reload();
  } catch (error) { status(error.message, true); }
});

byId("fixture-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  try {
    status("Зберігаю фурнітуру…");
    const payload = await request("/api/save-fixture", formObject(event.currentTarget));
    status(payload.already_current ? "Фурнітура вже актуальна." : `Фурнітуру збережено: ${payload.price_uah || 0} грн/шт.`);
    await reload();
  } catch (error) { status(error.message, true); }
});

byId("print-log-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  try {
    status("Додаю сесію у Друк-лог…");
    normalisePrintTimeField(event.currentTarget, "actual_time_hours");
    const payload = await request("/api/print-log", formObject(event.currentTarget));
    status(`Друк-лог: додано рядок ${payload.row}.`);
    event.currentTarget.reset();
    byId("print-log-form").elements.printer.value = "Сергій";
    byId("print-log-form").elements.date.value = new Date().toISOString().slice(0, 10);
    refreshPrintTimeHint(byId("print-log-form").elements.actual_time_hours);
    await reload();
  } catch (error) { status(error.message, true); }
});

byId("print-log-rows").addEventListener("click", async (event) => {
  const button = event.target.closest(".save-defect");
  if (!button) return;
  const row = button.closest("tr");
  try {
    status("Оновлюю брак з історією змін…");
    await request("/api/defect", {
      row: Number(row.dataset.row),
      defects: row.querySelector(".defect-input").value,
      expected_current: button.dataset.expected,
    });
    status("Брак оновлено; API додало запис в історію.");
    await reload();
  } catch (error) { status(error.message, true); }
});

byId("print-log-form").elements.date.value = new Date().toISOString().slice(0, 10);
bindPrintTimeInputs();
reload().catch((error) => status(error.message, true));
