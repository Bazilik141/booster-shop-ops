import { changedSettingWrites, fillSettingsForm } from "./settings-controls.js";
import { calculateBatchCost } from "/calculator.mjs";
import { createOperationRunner, refreshUntilFresh } from "./operation-state.js";
import { draftCategories, nomenclatureTypeForDraftCategory } from "./draft-categories.js";
import { isPrintTimeHeader, matrixToObjects, prepareInformationTable } from "./information-tables.js";

const informationPreferencesKey = "booster_3dp_serhiy_information_tables_v1";
let savedInformationTables = {};
try { savedInformationTables = JSON.parse(localStorage.getItem(informationPreferencesKey) || "{}") || {}; } catch {}
const state = { data: null, lastCalculation: null, settingsJournal: null, draftLoading: false, draftLoadId: 0, informationTables: savedInformationTables };
const operation = createOperationRunner();
const money = new Intl.NumberFormat("uk-UA", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const number = new Intl.NumberFormat("uk-UA", { maximumFractionDigits: 3 });
const draftKeys = ["quantity", "total_weight_g", "total_print_time_h", "spool_weight_g", "spool_price_uah"];
const productHeaders = { Q: "РРЦ фактична, грн", R: "Ціна під викуп, грн", S: "Посилання на модель" };
const acknowledgementHeaders = { amount_agreed: "Згода Сергія із сумою (Київ, роль)", money_received: "Кошти надійшли Сергію (Київ, роль)" };
const printTime = globalThis.BoosterPrintTime;
const byId = (id) => document.getElementById(id);

function feedback(node, message, kind = "success") {
  node.textContent = message;
  node.hidden = !message;
  node.dataset.state = kind;
  node.setAttribute("aria-busy", String(kind === "busy"));
}
function status(message, kind = "success") { feedback(byId("status"), message, kind === true ? "error" : kind); }
function operationFeedback(message, kind, resultId) {
  status(message, kind);
  if (resultId) feedback(byId(resultId), message, kind);
}
function errorText(error) { return error?.code ? `${error.code}: ${error.message}` : String(error?.message || error || "Запит не виконано."); }
async function request(url, body) {
  const response = await fetch(url, body ? { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) } : undefined);
  const payload = await response.json().catch(() => ({ ok: false, code: "LOCAL_INVALID_JSON", error: "Некоректна відповідь локального сервера." }));
  if (!response.ok || !payload.ok) { const error = new Error(payload.error || "Запит не виконано.");error.code = payload.code || "LOCAL_REQUEST_FAILED";throw error; }
  return payload;
}
function formObject(form) { return Object.fromEntries(new FormData(form).entries()); }
function escapeHtml(value) { return String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#039;", '"': "&quot;" }[char])); }
function display(value) { if (value === null || typeof value === "undefined" || value === "") return "—";if (typeof value === "object") return JSON.stringify(value);return String(value); }
function tableDisplay(header, value) { if (isPrintTimeHeader(header) && Number.isFinite(Number(value))) return printTime.human(Number(value));return display(value); }
function sameValue(left, right) { return String(left ?? "") === String(right ?? ""); }
function activeRows() { return (state.data?.skus || []).filter((row) => String(row["API_статус_запису"] || "Активний") === "Активний"); }
function rowForSku(sku) { return (state.data?.skus || []).find((row) => String(row.SKU) === String(sku)); }

function printTimeResult(input) { if (!printTime) throw new Error("Не завантажився спільний парсер часу друку.");return printTime.parse(input.value); }
function refreshPrintTimeHint(input) {
  const error = document.querySelector(`[data-print-time-error="${input.name}"]`);
  if (!error) return;
  const parsed = printTimeResult(input), message = !parsed.blank && !parsed.ok ? parsed.error : "";
  error.textContent = message;
  error.hidden = !message;
  input.setCustomValidity(message);
}
function normalisePrintTimeField(form, name) { const input = form.elements[name],parsed = printTimeResult(input);if (!parsed.ok || parsed.blank || !(parsed.hours > 0)) throw new Error(parsed.error || "Вкажіть час друку більше нуля.");input.value = String(parsed.hours);refreshPrintTimeHint(input);return parsed.hours; }
function bindPrintTimeInputs() { document.querySelectorAll("[data-print-time-input]").forEach((input) => { input.addEventListener("input", () => refreshPrintTimeHint(input));input.addEventListener("blur", () => { const parsed = printTimeResult(input);if (parsed.ok && !parsed.blank) input.value = String(parsed.hours);refreshPrintTimeHint(input); });refreshPrintTimeHint(input); }); }

function tablePreferences(tableId) { return state.informationTables[tableId] || (state.informationTables[tableId] = { visible: {}, type: "all", sort: "", direction: "asc" }); }
function saveInformationPreferences() { localStorage.setItem(informationPreferencesKey, JSON.stringify(state.informationTables)); }
function encoded(value) { return escapeHtml(encodeURIComponent(value)); }
function informationTable(rows, actionCell, tableId) {
  const preferences = tablePreferences(tableId),view = prepareInformationTable(rows, state.data?.skus || [], preferences);
  if (!view.headers.length) return '<table><thead><tr><th>Дані</th></tr></thead><tbody><tr><td class="muted">Немає доступних записів.</td></tr></tbody></table>';
  preferences.sort = view.sortKey;preferences.type = view.selectedType;
  const types = view.types.length ? `<label>Тип виробу<select data-info-type="${encoded(tableId)}"><option value="all">Усі типи</option>${view.types.map((type) => `<option value="${encoded(type)}"${view.selectedType === type ? " selected" : ""}>${escapeHtml(type)}</option>`).join("")}</select></label>` : "";
  const columns = `<details class="column-picker"><summary>Стовпці</summary><div>${view.headers.map((header) => `<label><input type="checkbox" data-info-column="${encoded(header)}" data-info-table="${encoded(tableId)}"${preferences.visible?.[header] === false ? "" : " checked"}> ${escapeHtml(header)}</label>`).join("")}</div></details>`;
  const toolbar = `<div class="table-toolbar">${types}${columns}</div>`;
  const arrow = (header) => view.sortKey === header ? (view.direction === "asc" ? " ▲" : " ▼") : " ↕";
  const head = view.visibleHeaders.map((header) => `<th><button class="table-sort" type="button" data-info-sort="${encoded(header)}" data-info-table="${encoded(tableId)}">${escapeHtml(header)}${arrow(header)}</button></th>`).join("");
  const body = view.records.length ? view.records.map(({ raw, flat }) => `<tr>${view.visibleHeaders.map((header) => `<td>${escapeHtml(tableDisplay(header, flat[header]))}</td>`).join("")}${actionCell ? `<td>${actionCell(raw)}</td>` : ""}</tr>`).join("") : `<tr><td colspan="${Math.max(1, view.visibleHeaders.length + (actionCell ? 1 : 0))}" class="muted">Немає записів за цим фільтром.</td></tr>`;
  return `${toolbar}<table><thead><tr>${head}${actionCell ? "<th>Дія</th>" : ""}</tr></thead><tbody>${body}</tbody></table>`;
}
function objectTable(rows, actionCell, tableId) {
  if (tableId) return informationTable(rows, actionCell, tableId);
  const view = prepareInformationTable(rows, state.data?.skus || [], {});
  if (!view.headers.length) return '<table><thead><tr><th>Дані</th></tr></thead><tbody><tr><td class="muted">Немає доступних записів.</td></tr></tbody></table>';
  return `<table><thead><tr>${view.headers.map((key) => `<th>${escapeHtml(key)}</th>`).join("")}${actionCell ? "<th>Дія</th>" : ""}</tr></thead><tbody>${view.records.map(({ raw, flat }) => `<tr>${view.headers.map((key) => `<td>${escapeHtml(tableDisplay(key, flat[key]))}</td>`).join("")}${actionCell ? `<td>${actionCell(raw)}</td>` : ""}</tr>`).join("")}</tbody></table>`;
}
function matrixTable(matrix, tableId) { const rows = matrixToObjects(matrix);return rows.length ? informationTable(rows, null, tableId) : '<p class="muted">Аналітика порожня.</p>'; }

function skuSearchText(row) { return `${row.SKU || ""} ${row["Назва виробу"] || ""}`.toLocaleLowerCase("uk-UA"); }
function updateSkuOptions(select) {
  const picker = select.closest(".sku-picker"), search = String(picker?.querySelector(".sku-search")?.value || "").trim().toLocaleLowerCase("uk-UA"), selected = select.value;
  const rows = activeRows(), visible = rows.filter((row) => !search || skuSearchText(row).includes(search) || String(row.SKU) === selected);
  select.innerHTML = `<option value="">Оберіть SKU</option>${visible.map((row) => `<option value="${escapeHtml(row.SKU)}">${escapeHtml(row.SKU)} — ${escapeHtml(row["Назва виробу"] || "без назви")}</option>`).join("")}`;
  if (rows.some((row) => String(row.SKU) === selected)) select.value = selected;
}
function setSkuOptions() { document.querySelectorAll(".sku-select").forEach(updateSkuOptions); }
function setFixtureOptions() { const selected = byId("fixture-select").value;byId("fixture-select").innerHTML = `<option value="">Без фурнітури / очистити</option>${(state.data.fixtures || []).map((row) => `<option value="${escapeHtml(row["Назва фурнітури"])}">${escapeHtml(row["Назва фурнітури"])} — ${money.format(Number(row["Ціна, грн/шт"] || 0))} грн/шт</option>`).join("")}`;if ([...byId("fixture-select").options].some((option) => option.value === selected)) byId("fixture-select").value = selected; }
function renderOverview() { const overview = state.data.overview || {};byId("sku-count").textContent = number.format(overview.sku_count || 0);byId("available").textContent = number.format(overview.available || 0);byId("accrued").textContent = `${money.format(overview.accrued_serhiy_current_month || 0)} грн`; }
function fillSettings() { fillSettingsForm(byId("settings-form"), state.data.settings_values || []); }
function fillProductForm() { const form = byId("product-form"),row = rowForSku(form.elements.sku.value);Object.entries(productHeaders).forEach(([column, header]) => { form.elements[column].value = row?.[header] ?? "";form.elements[column].dataset.expected = String(row?.[header] ?? ""); }); }
function fillStockForm() { const form = byId("stock-form"),row = rowForSku(form.elements.sku.value),current = row?.availability?.["Наявно зараз, шт"] ?? "";form.elements.expected_current.value = current;form.elements.new_value.value = current; }

function payoutButtons(row) { const period = row["Період (РРРР-ММ)"] || "",rowNumber = Number(row.row_number),statusValue = String(row.Статус || "");return Object.entries(acknowledgementHeaders).map(([key, header]) => { const value = String(row[header] || "");if (value) return `<span class="recorded">${escapeHtml(value)}</span><button class="small secondary payout-correct" data-row="${rowNumber}" data-period="${escapeHtml(period)}" data-key="${key}" data-current="${escapeHtml(value)}" type="button">Виправити</button>`;if (key === "money_received" && statusValue !== "Виплачено") return '<span class="muted">Після виплати</span>';return `<button class="small payout-ack" data-row="${rowNumber}" data-period="${escapeHtml(period)}" data-key="${key}" type="button">${key === "amount_agreed" ? "Погоджую суму" : "Кошти отримано"}</button>`; }).join(" "); }
function renderAttention() { const entries = [];activeRows().forEach((row) => { const stock = Number(row.availability?.["Наявно зараз, шт"]);if (Number.isFinite(stock) && stock === 0) entries.push(`${row.SKU}: нульова наявність`);if (!(Number(row["Час друку за од., год"]) > 0)) entries.push(`${row.SKU}: не вказано час друку`); });(state.data.print_log || []).filter((row) => Number(row["Брак, шт"]) > 0).forEach((row) => entries.push(`${row.SKU}: у Друк-лозі записано брак ${row["Брак, шт"]} шт`));byId("attention").innerHTML = entries.length ? `<ul>${entries.map((item) => `<li>${escapeHtml(item)}</li>`).join("")}</ul>` : '<p class="muted">Сигналів уваги немає.</p>'; }
function renderInformation() { renderAttention();byId("analytics").innerHTML = matrixTable(state.data.analytics, "analytics");byId("all-products").innerHTML = objectTable(state.data.skus, null, "all-products");byId("sales").innerHTML = objectTable(state.data.sales, null, "sales");byId("payouts").innerHTML = objectTable(state.data.payouts, payoutButtons, "payouts");byId("plyushky").innerHTML = objectTable(state.data.plyushky, null, "plyushky");byId("print-log").innerHTML = objectTable(state.data.print_log); }
function render() { setSkuOptions();setFixtureOptions();renderOverview();fillSettings();fillProductForm();fillStockForm();renderInformation();previewBatch(); }

function renderCalculation(calculation) { state.lastCalculation = calculation;byId("save-batch").disabled = operation.busy || state.draftLoading;byId("manufacture-batch").disabled = operation.busy || state.draftLoading;byId("calculation").classList.remove("empty");const usable=calculation.good_quantity?`${money.format(calculation.actual_usable_unit_uah)} грн/придатну шт.`:"партія повністю бракована — доступного FIFO-залишку не буде";byId("calculation").innerHTML = `<strong>За одиницю:</strong><ul><li>Вага: ${number.format(calculation.per_unit.weight_g)} г</li><li>Час: ${escapeHtml(printTime.human(calculation.per_unit.time_hours))}</li><li>Матеріал: ${money.format(calculation.costs.material_uah)} грн</li><li>Електроенергія: ${money.format(calculation.costs.electricity_uah)} грн</li><li>Амортизація: ${money.format(calculation.costs.amortization_uah)} грн</li><li>Прогноз із плановим браком: ${money.format(calculation.costs.defect_adjusted_uah)} грн/шт</li><li>Фактичний брак: ${number.format(calculation.actual_defects)} шт.; придатно: ${number.format(calculation.good_quantity)} шт.</li><li>Додаткові розхідники Сергія: ${money.format(calculation.serhiy_consumables_uah)} грн/партія</li><li><strong>Фактична партія для FIFO: ${money.format(calculation.actual_batch_total_uah)} грн (${usable})</strong></li></ul>`; }
function clearCalculation() { state.lastCalculation = null;byId("save-batch").disabled = true;byId("manufacture-batch").disabled = true;byId("calculation").classList.add("empty");byId("calculation").textContent = "Заповніть усі дані партії — результат з’явиться автоматично."; }
function batchValuesForCalculation() { const form = byId("batch-form"),values = formObject(form),parsed = printTimeResult(form.elements.total_print_time_h);if (!values.sku || !parsed.ok || parsed.blank || !(parsed.hours > 0)) return null;return { ...values, total_print_time_h: parsed.hours }; }
function previewBatch() { try { const values = batchValuesForCalculation();if (!values || !state.data?.settings) return clearCalculation();renderCalculation(calculateBatchCost(values, state.data.settings)); } catch { clearCalculation(); } }
async function loadDraft(sku) {
  if (operation.busy) return;
  const form = byId("batch-form"), loadId = ++state.draftLoadId;
  // SKU changes invalidate both the previous read and its pending retry key.
  delete form.dataset.requestId;
  draftKeys.forEach((key) => { form.elements[key].value = ""; });
  form.elements.defects.value = "0";
  form.elements.serhiy_consumables_uah.value = "0";
  refreshPrintTimeHint(form.elements.total_print_time_h);
  state.draftLoading = Boolean(sku);
  [...form.elements].forEach((input) => { if (input.name !== "sku") input.disabled = state.draftLoading; });
  byId("reload").disabled = state.draftLoading;
  clearCalculation();
  feedback(byId("batch-feedback"), "");
  if (!sku) { status("Оберіть SKU для розрахунку.", "info");return; }
  operationFeedback("Завантажую розрахунок Сергія…", "busy", "batch-feedback");
  try {
    const payload = await request(`/api/batch-draft?sku=${encodeURIComponent(sku)}`);
    if (loadId !== state.draftLoadId || form.elements.sku.value !== sku) return;
    draftKeys.forEach((key) => { form.elements[key].value = payload.values?.[key] ?? ""; });
    refreshPrintTimeHint(form.elements.total_print_time_h);
    feedback(byId("batch-feedback"), "");
    status(payload.found ? "Розрахунок Сергія завантажено." : "Для SKU ще немає збереженого розрахунку Сергія.", payload.found ? "success" : "info");
  } catch (error) {
    if (loadId === state.draftLoadId) operationFeedback(errorText(error), "error", "batch-feedback");
  } finally {
    if (loadId === state.draftLoadId) {
      state.draftLoading = false;
      [...form.elements].forEach((input) => { input.disabled = false; });
      byId("reload").disabled = false;
      previewBatch();
    }
  }
}
async function loadSettingsJournal() { state.settingsJournal = await request("/api/settings-journal");byId("settings-journal").innerHTML = objectTable(state.settingsJournal.rows); }
async function refreshData() {
  state.data = await request("/api/bootstrap");
  render();
  if (!byId("settings-panel").classList.contains("hidden")) await loadSettingsJournal();
}
async function refreshManufacturedStock(payload, sku, previousStock) {
  const expectsChange = !payload.already_applied && Number(payload.stock_added) !== 0 && previousStock != null;
  if (!expectsChange) return refreshData();
  await refreshUntilFresh({
    refresh: refreshData,
    isFresh: () => !sameValue(rowForSku(sku)?.availability?.["Наявно зараз, шт"], previousStock),
  });
}

async function runOperation({ form, button, resultId, pending, buttonText, execute, success, onConfirmed, afterRefresh, refresh = refreshData }) {
  if (state.draftLoading) return;
  let controls = [], originalButtonText = "";
  return operation.run({
    execute, refresh, onConfirmed,
    onState(phase, { result, error } = {}) {
      if (phase === "pending") {
        controls = [...document.querySelectorAll("form input, form select, form button, #reload, #settings-toggle, .payout-ack, .payout-correct")].map((node) => [node, node.disabled]);
        controls.forEach(([node]) => { node.disabled = true; });
        form?.setAttribute("aria-busy", "true");
        if (button) { originalButtonText = button.textContent;button.textContent = buttonText || pending;button.setAttribute("aria-busy", "true"); }
        operationFeedback(pending, "busy", resultId);
      } else if (phase === "confirmed") {
        operationFeedback(success(result) + (refresh ? " Оновлюю дані…" : ""), refresh ? "busy" : "success", resultId);
      } else if (phase === "success") {
        const final = afterRefresh?.(result);
        operationFeedback(final?.message || success(result), final?.kind || "success", resultId);
      } else if (phase === "refresh-error") {
        operationFeedback(`${success(result)} Не вдалося оновити екран: ${errorText(error)} Натисніть «Оновити дані»; повторно команду не надсилайте.`, "warning", resultId);
      } else if (phase === "error") {
        operationFeedback(`${errorText(error)}${error instanceof TypeError ? " Підтвердження не отримано. Перед повтором перевірте, чи запис уже з’явився." : ""}`, "error", resultId);
      } else if (phase === "idle") {
        controls.forEach(([node, disabled]) => { node.disabled = disabled; });
        form?.setAttribute("aria-busy", "false");
        if (button) { button.textContent = originalButtonText;button.setAttribute("aria-busy", "false"); }
        previewBatch();
      }
    },
  });
}
function reload() {
  return runOperation({ button: byId("reload"), pending: "Оновлюю дані…", buttonText: "Оновлюю…", execute: refreshData, success: () => "Дані отримано через 3D-P API.", refresh: null });
}

document.querySelectorAll(".zone-button").forEach((button) => button.addEventListener("click", () => { document.querySelectorAll(".zone-button").forEach((item) => item.classList.toggle("active", item === button));document.querySelectorAll(".zone").forEach((zone) => zone.classList.toggle("active", zone.id === `zone-${button.dataset.zoneTarget}`)); }));
byId("zone-information").addEventListener("click", (event) => {
  const button = event.target.closest("[data-info-sort]");
  if (!button) return;
  const tableId = decodeURIComponent(button.dataset.infoTable),header = decodeURIComponent(button.dataset.infoSort),preferences = tablePreferences(tableId);
  if (preferences.sort === header) preferences.direction = preferences.direction === "asc" ? "desc" : "asc";
  else { preferences.sort = header;preferences.direction = "asc"; }
  saveInformationPreferences();renderInformation();
});
byId("zone-information").addEventListener("change", (event) => {
  if (event.target.matches("[data-info-type]")) {
    tablePreferences(decodeURIComponent(event.target.dataset.infoType)).type = decodeURIComponent(event.target.value);
  } else if (event.target.matches("[data-info-column]")) {
    tablePreferences(decodeURIComponent(event.target.dataset.infoTable)).visible[decodeURIComponent(event.target.dataset.infoColumn)] = event.target.checked;
  } else return;
  saveInformationPreferences();renderInformation();
});
byId("settings-toggle").addEventListener("click", async () => { const panel = byId("settings-panel"),opening = panel.classList.contains("hidden");panel.classList.toggle("hidden", !opening);byId("settings-toggle").setAttribute("aria-expanded", String(opening));if (opening) try { await loadSettingsJournal(); } catch (error) { status(errorText(error), true); } });
byId("reload").addEventListener("click", () => reload().catch((error) => status(errorText(error), true)));
byId("batch-form").elements.sku.addEventListener("change", (event) => loadDraft(event.target.value).catch((error) => status(errorText(error), true)));
byId("product-form").elements.sku.addEventListener("change", fillProductForm);
byId("stock-form").elements.sku.addEventListener("change", fillStockForm);
document.querySelectorAll(".sku-search").forEach((input) => input.addEventListener("input", () => updateSkuOptions(input.closest(".sku-picker").querySelector(".sku-select"))));

byId("batch-form").addEventListener("input", previewBatch);
byId("batch-form").addEventListener("change", previewBatch);
byId("batch-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  if (operation.busy || state.draftLoading) return;
  try {
    normalisePrintTimeField(form, "total_print_time_h");
    const values = formObject(form);
    await runOperation({ form, button: byId("save-batch"), resultId: "batch-feedback", pending: "Зберігаю розрахунок… Зачекайте на підтвердження.", buttonText: "Зберігаю…", execute: () => request("/api/save-batch", values), success: (payload) => payload.already_current ? "Розрахунок уже актуальний." : "Розрахунок збережено." });
  } catch (error) { operationFeedback(errorText(error), "error", "batch-feedback"); }
});
byId("settings-form").addEventListener("submit", async (event) => {
  event.preventDefault();const form = event.currentTarget, changes = changedSettingWrites(form);
  await runOperation({ form, button: event.submitter, pending: "Зберігаю налаштування…", buttonText: "Зберігаю…", execute: async () => { for (const change of changes) await request("/api/setting", change); }, success: () => "Налаштування збережено з журналом." });
});
byId("product-form").addEventListener("submit", async (event) => {
  event.preventDefault();const form = event.currentTarget, sku = form.elements.sku.value;
  const changes = Object.keys(productHeaders).filter((column) => !sameValue(form.elements[column].value, form.elements[column].dataset.expected)).map((column) => ({ sku, column, value: form.elements[column].value, expected_current: form.elements[column].dataset.expected }));
  await runOperation({ form, button: event.submitter, pending: "Зберігаю поля виробу…", buttonText: "Зберігаю…", execute: async () => { for (const change of changes) await request("/api/product-field", change); }, success: () => "Поля виробу збережено з журналом." });
});
byId("fixture-form").addEventListener("submit", async (event) => {
  event.preventDefault();const form = event.currentTarget, values = formObject(form);
  await runOperation({ form, button: event.submitter, pending: "Зберігаю фурнітуру…", buttonText: "Зберігаю…", execute: () => request("/api/save-fixture", values), success: (payload) => payload.already_current ? "Фурнітура вже актуальна." : "Фурнітуру збережено." });
});
byId("stock-form").addEventListener("submit", async (event) => {
  event.preventDefault();const form = event.currentTarget, values = formObject(form);
  await runOperation({ form, button: event.submitter, pending: "Записую фактичну кількість…", buttonText: "Записую…", execute: () => request("/api/stock-correction", values), success: (payload) => payload.already_applied ? "Фактична кількість уже записана." : `Наявність: ${payload.old_value} → ${payload.new_value}.` });
});

function stableRequestId(form) { if (!form.dataset.requestId) { const raw = globalThis.crypto?.randomUUID?.() || `${Date.now()}_${Math.random().toString(16).slice(2)}`;form.dataset.requestId = `serhiy_${raw.replace(/[^A-Za-z0-9_-]/g, "_")}`.slice(0, 80); }return form.dataset.requestId; }
byId("manufacture-batch").addEventListener("click", async () => {
  const form = byId("batch-form");
  if (operation.busy || state.draftLoading) return;
  try {
    const values = batchValuesForCalculation();
    if (!values) throw new Error("Спочатку заповніть коректні дані партії.");
    const previousStock = rowForSku(values.sku)?.availability?.["Наявно зараз, шт"];
    const body = { sku: values.sku, printed_quantity: values.quantity, actual_time_hours: values.total_print_time_h, actual_material_g: values.total_weight_g, spool_weight_g: values.spool_weight_g, spool_price_uah: values.spool_price_uah, serhiy_consumables_uah: values.serhiy_consumables_uah || 0, defects: values.defects || 0, notes: values.manufacture_notes || "", request_id: stableRequestId(form) };
    const success = (payload) => payload.already_applied ? "Цю партію вже було записано; дубль не створено." : `Вироблену партію записано, рядок ${payload.row}.`;
    await runOperation({
      form, button: byId("manufacture-batch"), resultId: "batch-feedback", pending: "Записую виготовлену партію… Не натискайте повторно.", buttonText: "Записую партію…",
      execute: () => request("/api/print-log", body), success,
      onConfirmed: () => { delete form.dataset.requestId; },
      refresh: (payload) => refreshManufacturedStock(payload, values.sku, previousStock),
      afterRefresh: (payload) => {
        const currentStock = rowForSku(values.sku)?.availability?.["Наявно зараз, шт"];
        if (!payload.already_applied && previousStock != null && sameValue(previousStock, currentStock)) {
          return { kind: "warning", message: `${success(payload)} Але залишок ${values.sku} у 3D-таблиці не змінився (${number.format(Number(currentStock))} шт.). Перевірте формулу наявності; повторно партію не записуйте.` };
        }
        return { message: success(payload) + (currentStock != null ? ` Наявність ${values.sku}: ${number.format(Number(currentStock))} шт.` : "") };
      },
    });
  } catch (error) { operationFeedback(errorText(error), "error", "batch-feedback"); }
});

byId("draft-type").innerHTML = `<option value="">Оберіть механіку / категорію</option>${draftCategories.map(({ label }) => `<option value="${escapeHtml(label)}">${escapeHtml(label)}</option>`).join("")}`;
function localDateIso() { const now = new Date(),pad = (value) => String(value).padStart(2, "0");return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`; }
byId("draft-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget, button = event.submitter;
  // Unknown optional C/E/F values stay blank; invented placeholders can violate
  // the workbook's dropdown validation after owner promotion of the draft.
  const values = { ...formObject(form), L: localDateIso() };
  values.D = nomenclatureTypeForDraftCategory(values.D);
  await runOperation({ form, button, resultId: "draft-result", pending: "Створюю чернетку виробу… Зачекайте на підтвердження.", buttonText: "Створюю…", execute: () => request("/api/draft", { values }), success: () => "Чернетку виробу створено.", onConfirmed: () => form.reset() });
});

byId("payouts").addEventListener("click", async (event) => { const button = event.target.closest(".payout-ack,.payout-correct");if (!button) return;try { const body = { row_number: Number(button.dataset.row), expected_period: button.dataset.period, acknowledgement: button.dataset.key };if (button.classList.contains("payout-correct")) { const reason = globalThis.prompt("Причина виправлення підтвердження:", "");if (!reason) return;body.expected_current = button.dataset.current;body.reason = reason;await request("/api/payout-acknowledgement-correct", body); } else await request("/api/payout-acknowledge", body);status("Підтвердження виплати записано.");await reload(); } catch (error) { status(errorText(error), true); } });

bindPrintTimeInputs();
const heartbeat = () => fetch("/api/heartbeat", { method: "POST" }).catch(() => {});
heartbeat();
globalThis.setInterval(heartbeat, 30000);
reload().catch((error) => status(errorText(error), true));
