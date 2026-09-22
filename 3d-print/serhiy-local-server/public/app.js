import { changedSettingWrites, fillSettingsForm } from "./settings-controls.js";
import { calculateBatchCost } from "/calculator.mjs";
import { createOperationRunner, refreshUntilFresh } from "./operation-state.js";
import { draftCategories, nomenclatureTypeForDraftCategory } from "./draft-categories.js";
import { addPrintLogProductNames, isPrintTimeHeader, matrixToObjects, prepareInformationTable } from "./information-tables.js";
import { collectAttentionSignals, reconcileHiddenSignalIds, splitAttention } from "./attention-signals.js";

const informationPreferencesKey = "booster_3dp_serhiy_information_tables_v1";
const attentionHiddenKey = "booster_3dp_serhiy_attention_hidden_v1";
let savedInformationTables = {};
try { savedInformationTables = JSON.parse(localStorage.getItem(informationPreferencesKey) || "{}") || {}; } catch {}
let savedAttentionHidden = [];
try { const value = JSON.parse(localStorage.getItem(attentionHiddenKey) || "[]");savedAttentionHidden = Array.isArray(value) ? value.map(String) : []; } catch {}
const state = { data: null, lastCalculation: null, settingsJournal: null, draftLoading: false, draftLoadId: 0, draftQuantityTimer: null, informationTables: savedInformationTables, attentionHidden: savedAttentionHidden, attentionView: "active" };
const renderedInformationTables = new Map();
const operation = createOperationRunner();
const money = new Intl.NumberFormat("uk-UA", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const number = new Intl.NumberFormat("uk-UA", { maximumFractionDigits: 3 });
const draftKeys = ["quantity", "total_weight_g", "total_print_time_h", "spool_weight_g", "spool_price_uah"];
const productHeaders = { Q: "РРЦ фактична, грн", R: "Ціна під викуп, грн", S: "Посилання на модель" };
const acknowledgementHeaders = { amount_agreed: "Згода Сергія із сумою (Київ, роль)", money_received: "Кошти надійшли Сергію (Київ, роль)" };
const tableOptions = {
  analytics: { reorder: true, excludeHeaders: ["Маржа BoosterShop, %"] },
  "all-products": { reorder: true, excludeHeaders: ["Трек", "Вага виробу за од., г", "Вага котушки, г", "Ціна котушки, грн", "Примітки", "API_статус_запису", "API_історія_змін", "row_number"] },
  sales: { reorder: true, excludeHeaders: ["Статус", "Дохід Booster Shop, грн", "Нараховано Сергію, грн", "Канал", "Параметр знижки", "Погоджено з Сергієм (Так/Ні)", "Період (авто, РРРР-ММ)", "Режим CRM", "Фурнітура власника за од., грн (заморожена)", "Фурнітура Сергія за од., грн (заморожена)"] },
  "print-log": { reorder: true, excludeHeaders: ["API_статус_запису"] },
};
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
function tableDisplay(header, value) { const numeric = Number(value);if (isPrintTimeHeader(header) && Number.isFinite(numeric)) return printTime.human(numeric);if (/^%/.test(header) && Number.isFinite(numeric)) return `${number.format(numeric * 100)}%`;if (/^(Собівартість Сергія|Витрати BoosterShop за од\., грн)/.test(header) && Number.isFinite(numeric)) return `${money.format(numeric)} грн`;return display(value); }
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

function tablePreferences(tableId) { const preferences = state.informationTables[tableId] || (state.informationTables[tableId] = {});preferences.visible ||= {};preferences.order ||= [];preferences.type ||= "all";preferences.sort ||= "";preferences.direction ||= "asc";preferences.page = Number(preferences.page) || 1;return preferences; }
function saveInformationPreferences() { localStorage.setItem(informationPreferencesKey, JSON.stringify(state.informationTables)); }
function saveAttentionHidden() { localStorage.setItem(attentionHiddenKey, JSON.stringify(state.attentionHidden)); }
function encoded(value) { return escapeHtml(encodeURIComponent(value)); }
function informationToolbar(view, tableId) {
  const preferences = tablePreferences(tableId);
  const types = view.types.length ? `<label>Тип виробу<select data-info-type="${encoded(tableId)}"><option value="all">Усі типи</option>${view.types.map((type) => `<option value="${encoded(type)}"${view.selectedType === type ? " selected" : ""}>${escapeHtml(type)}</option>`).join("")}</select></label>` : "";
  const canReorder = tableOptions[tableId]?.reorder === true;
  const columns = `<details class="column-picker"><summary>Стовпці · ${view.visibleHeaders.length} із ${view.headers.length}</summary><fieldset><legend class="visually-hidden">Стовпці таблиці</legend>${view.headers.map((header, index) => `<label class="column-control"><input type="checkbox" data-info-column="${encoded(header)}" data-info-table="${encoded(tableId)}"${preferences.visible[header] === false ? "" : " checked"}><span>${escapeHtml(header)}</span>${canReorder ? `<span class="column-move"><button type="button" class="small secondary" data-info-move="left" data-info-table="${encoded(tableId)}" data-info-header="${encoded(header)}"${index === 0 ? " disabled" : ""} aria-label="Пересунути ${escapeHtml(header)} вліво">←</button><button type="button" class="small secondary" data-info-move="right" data-info-table="${encoded(tableId)}" data-info-header="${encoded(header)}"${index === view.headers.length - 1 ? " disabled" : ""} aria-label="Пересунути ${escapeHtml(header)} вправо">→</button></span>` : ""}</label>`).join("")}</fieldset></details>`;
  return `<div class="table-toolbar">${types}${columns}</div>`;
}
function informationTableMarkup(view, actionCell, tableId) {
  if (!view.headers.length) return '<table><thead><tr><th>Дані</th></tr></thead><tbody><tr><td class="muted">Немає доступних записів.</td></tr></tbody></table>';
  const arrow = (header) => view.sortKey === header ? (view.direction === "asc" ? " ▲" : " ▼") : " ↕";
  const ariaSort = (header) => view.sortKey !== header ? "none" : view.direction === "asc" ? "ascending" : "descending";
  const head = view.visibleHeaders.map((header) => `<th aria-sort="${ariaSort(header)}"><button class="table-sort" type="button" data-info-sort="${encoded(header)}" data-info-table="${encoded(tableId)}">${escapeHtml(header)}${arrow(header)}</button></th>`).join("");
  const body = view.records.length ? view.records.map(({ raw, flat }) => `<tr>${view.visibleHeaders.map((header) => `<td>${escapeHtml(tableDisplay(header, flat[header]))}</td>`).join("")}${actionCell ? `<td>${actionCell(raw)}</td>` : ""}</tr>`).join("") : `<tr><td colspan="${Math.max(1, view.visibleHeaders.length + (actionCell ? 1 : 0))}" class="muted">Немає записів за цим фільтром.</td></tr>`;
  const pager = `<nav class="table-pager" aria-label="Сторінки таблиці"><span aria-live="polite">${view.from}–${view.to} із ${view.total}</span><span><button class="small secondary" type="button" data-info-page="previous" data-info-table="${encoded(tableId)}"${view.page <= 1 ? " disabled" : ""}>Назад</button><span class="table-page">${view.page} / ${view.pageCount}</span><button class="small secondary" type="button" data-info-page="next" data-info-table="${encoded(tableId)}"${view.page >= view.pageCount ? " disabled" : ""}>Далі</button></span></nav>`;
  return `<table><thead><tr>${head}${actionCell ? "<th>Дія</th>" : ""}</tr></thead><tbody>${body}</tbody></table>${pager}`;
}
function informationTable(rows, actionCell, tableId) {
  const preferences = tablePreferences(tableId),view = prepareInformationTable(rows, state.data?.skus || [], preferences, tableOptions[tableId]);
  preferences.sort = view.sortKey;preferences.type = view.selectedType;preferences.page = view.page;
  return `${informationToolbar(view, tableId)}<div class="information-table-content">${informationTableMarkup(view, actionCell, tableId)}</div>`;
}
function renderInformationTable(tableId, focusHeader) {
  const entry = renderedInformationTables.get(tableId);
  if (!entry) return;
  const preferences = tablePreferences(tableId),view = prepareInformationTable(entry.rows, state.data?.skus || [], preferences, tableOptions[tableId]),content = entry.container.querySelector(".information-table-content");
  preferences.sort = view.sortKey;preferences.type = view.selectedType;preferences.page = view.page;saveInformationPreferences();
  if (content) content.innerHTML = informationTableMarkup(view, entry.actionCell, tableId);
  if (focusHeader) [...entry.container.querySelectorAll("[data-info-sort]")].find((button) => decodeURIComponent(button.dataset.infoSort) === focusHeader)?.focus();
}
function renderInformationTableInto(containerId, rows, actionCell, tableId) { const container = byId(containerId);container.innerHTML = informationTable(rows, actionCell, tableId);renderedInformationTables.set(tableId, { container, rows, actionCell }); }
function objectTable(rows, actionCell, tableId) {
  if (tableId) return informationTable(rows, actionCell, tableId);
  const view = prepareInformationTable(rows, state.data?.skus || [], {});
  if (!view.headers.length) return '<table><thead><tr><th>Дані</th></tr></thead><tbody><tr><td class="muted">Немає доступних записів.</td></tr></tbody></table>';
  return `<table><thead><tr>${view.headers.map((key) => `<th>${escapeHtml(key)}</th>`).join("")}${actionCell ? "<th>Дія</th>" : ""}</tr></thead><tbody>${view.records.map(({ raw, flat }) => `<tr>${view.headers.map((key) => `<td>${escapeHtml(tableDisplay(key, flat[key]))}</td>`).join("")}${actionCell ? `<td>${actionCell(raw)}</td>` : ""}</tr>`).join("")}</tbody></table>`;
}
function matrixTable(matrix, tableId) { const rows = matrixToObjects(matrix);return rows.length ? informationTable(rows, null, tableId) : '<p class="muted">Аналітика порожня.</p>'; }

function skuSearchText(row) { return `${row.SKU || ""} ${row["Назва виробу"] || row.Назва || ""}`.toLocaleLowerCase("uk-UA"); }
function skuLabel(row) { return `${row.SKU || ""} — ${row["Назва виробу"] || row.Назва || "без назви"}`; }
function skuPickerParts(select) { const picker = select.closest(".sku-picker"),input = picker?.querySelector(".sku-search");let list = picker?.querySelector(".sku-options");if (picker && !list) { list = document.createElement("div");list.className = "sku-options";list.setAttribute("role", "listbox");list.hidden = true;picker.append(list); }return { picker, input, list }; }
function updateSkuOptions(select) {
  const { picker, input, list } = skuPickerParts(select),selected = select.value,rows = activeRows();
  select.innerHTML = `<option value="">Оберіть SKU</option>${rows.map((row) => `<option value="${escapeHtml(row.SKU)}">${escapeHtml(skuLabel(row))}</option>`).join("")}`;
  if (rows.some((row) => String(row.SKU) === selected)) select.value = selected;
  const query = String(input?.value || "").trim().toLocaleLowerCase("uk-UA"),visible = rows.filter((row) => !query || skuSearchText(row).includes(query));
  if (input && !picker.dataset.editing) input.value = selected ? skuLabel(rows.find((row) => String(row.SKU) === selected)) : "";
  if (list) list.innerHTML = visible.length ? visible.map((row) => `<button type="button" role="option" aria-selected="${String(row.SKU) === selected}" data-sku-option="${encoded(row.SKU)}">${escapeHtml(skuLabel(row))}</button>`).join("") : '<p class="sku-empty">Нічого не знайдено.</p>';
}
function setSkuOptions() { document.querySelectorAll(".sku-select").forEach(updateSkuOptions); }
function openSkuOptions(select) { const { picker, input, list } = skuPickerParts(select);if (!picker || !input || !list) return;picker.dataset.open = "true";input.setAttribute("aria-expanded", "true");list.hidden = false;updateSkuOptions(select); }
function closeSkuOptions(select) { const { picker, input, list } = skuPickerParts(select);if (!picker || !input || !list) return;picker.dataset.open = "";picker.dataset.editing = "";input.setAttribute("aria-expanded", "false");list.hidden = true;updateSkuOptions(select); }
function chooseSku(select, sku) { const row = activeRows().find((item) => String(item.SKU) === String(sku)),{ input } = skuPickerParts(select);if (!row) return;select.value = String(row.SKU);if (input) input.value = skuLabel(row);closeSkuOptions(select);select.dispatchEvent(new Event("change", { bubbles: true })); }
function setFixtureOptions() { const selected = byId("fixture-select").value;byId("fixture-select").innerHTML = `<option value="">Без фурнітури / очистити</option>${(state.data.fixtures || []).map((row) => `<option value="${escapeHtml(row["Назва фурнітури"])}">${escapeHtml(row["Назва фурнітури"])} — ${money.format(Number(row["Ціна, грн/шт"] || 0))} грн/шт</option>`).join("")}`;if ([...byId("fixture-select").options].some((option) => option.value === selected)) byId("fixture-select").value = selected; }
function renderOverview() { const overview = state.data.overview || {};byId("sku-count").textContent = number.format(overview.sku_count || 0);byId("available").textContent = number.format(overview.available || 0);byId("accrued").textContent = `${money.format(overview.accrued_serhiy_current_month || 0)} грн`; }
function fillSettings() { fillSettingsForm(byId("settings-form"), state.data.settings_values || []); }
function fillProductForm() { const form = byId("product-form"),row = rowForSku(form.elements.sku.value);Object.entries(productHeaders).forEach(([column, header]) => { form.elements[column].value = row?.[header] ?? "";form.elements[column].dataset.expected = String(row?.[header] ?? ""); }); }
function fillStockForm() { const form = byId("stock-form"),row = rowForSku(form.elements.sku.value),current = row?.availability?.["Наявно зараз, шт"] ?? "";form.elements.expected_current.value = current;form.elements.new_value.value = current; }

function payoutButtons(row) { const period = row["Період (РРРР-ММ)"] || "",rowNumber = Number(row.row_number),statusValue = String(row.Статус || "");return Object.entries(acknowledgementHeaders).map(([key, header]) => { const value = String(row[header] || "");if (value) return `<span class="recorded">${escapeHtml(value)}</span><button class="small secondary payout-correct" data-row="${rowNumber}" data-period="${escapeHtml(period)}" data-key="${key}" data-current="${escapeHtml(value)}" type="button">Виправити</button>`;if (key === "money_received" && statusValue !== "Виплачено") return '<span class="muted">Після виплати</span>';return `<button class="small payout-ack" data-row="${rowNumber}" data-period="${escapeHtml(period)}" data-key="${key}" type="button">${key === "amount_agreed" ? "Погоджую суму" : "Кошти отримано"}</button>`; }).join(" "); }
function renderAttention() {
  const signals = collectAttentionSignals({ skus: activeRows(), printLog: state.data?.print_log || [] });
  const reconciled = reconcileHiddenSignalIds(state.attentionHidden, signals);
  if (JSON.stringify(reconciled) !== JSON.stringify(state.attentionHidden)) { state.attentionHidden = reconciled;saveAttentionHidden(); }
  const split = splitAttention(signals, state.attentionHidden),visible = state.attentionView === "hidden" ? split.hidden : split.active;
  byId("attention-count").textContent = number.format(split.active.length);
  byId("attention-heading-count").textContent = number.format(split.active.length);
  const empty = state.attentionView === "hidden" ? "Прихованих сигналів немає." : "Актуальних сигналів немає.";
  const items = visible.length ? `<ul class="attention-list">${visible.map((signal) => `<li><span>${escapeHtml(signal.text)}</span><button class="small secondary" type="button" data-attention-toggle="${encoded(signal.id)}">${state.attentionView === "hidden" ? "Повернути" : "Приховати"}</button></li>`).join("")}</ul>` : `<p class="muted">${empty}</p>`;
  byId("attention").innerHTML = `<div class="attention-switch" role="group" aria-label="Видимість сигналів"><button class="small${state.attentionView === "active" ? " active" : " secondary"}" type="button" data-attention-view="active" aria-pressed="${state.attentionView === "active"}">Актуальні · ${number.format(split.active.length)}</button><button class="small${state.attentionView === "hidden" ? " active" : " secondary"}" type="button" data-attention-view="hidden" aria-pressed="${state.attentionView === "hidden"}">Приховані · ${number.format(split.hidden.length)}</button></div>${items}<p class="muted attention-note">Приховування не закриває сигнал: він лишається у списку «Приховані», доки причина не зникне з даних. Після повторної появи умови сигнал повертається в «Актуальні».</p>`;
}
function renderInformation() {
  ["analytics", "all-products", "sales", "payouts", "plyushky", "print-log"].forEach((tableId) => renderedInformationTables.delete(tableId));renderAttention();
  const analytics = matrixToObjects(state.data.analytics);if (analytics.length) renderInformationTableInto("analytics", analytics, null, "analytics");else byId("analytics").innerHTML = '<p class="muted">Аналітика порожня.</p>';
  renderInformationTableInto("all-products", state.data.skus, null, "all-products");
  renderInformationTableInto("sales", state.data.sales, null, "sales");
  renderInformationTableInto("payouts", state.data.payouts, payoutButtons, "payouts");
  renderInformationTableInto("plyushky", state.data.plyushky, null, "plyushky");
  renderInformationTableInto("print-log", addPrintLogProductNames(state.data.print_log, state.data.skus), null, "print-log");
  saveInformationPreferences();
}
function render() { setSkuOptions();setFixtureOptions();renderOverview();fillSettings();fillProductForm();fillStockForm();renderInformation();previewBatch(); }

function renderCalculation(calculation) { state.lastCalculation = calculation;byId("save-batch").disabled = operation.busy || state.draftLoading;byId("manufacture-batch").disabled = operation.busy || state.draftLoading;byId("calculation").classList.remove("empty");const usable=calculation.good_quantity?`${money.format(calculation.actual_usable_unit_uah)} грн/придатну шт.`:"партія повністю бракована — доступного FIFO-залишку не буде";byId("calculation").innerHTML = `<strong>За одиницю:</strong><ul><li>Вага: ${number.format(calculation.per_unit.weight_g)} г</li><li>Час: ${escapeHtml(printTime.human(calculation.per_unit.time_hours))}</li><li>Матеріал: ${money.format(calculation.costs.material_uah)} грн</li><li>Електроенергія: ${money.format(calculation.costs.electricity_uah)} грн</li><li>Амортизація: ${money.format(calculation.costs.amortization_uah)} грн</li><li>Прогноз із плановим браком: ${money.format(calculation.costs.defect_adjusted_uah)} грн/шт</li><li>Фактичний брак: ${number.format(calculation.actual_defects)} шт.; придатно: ${number.format(calculation.good_quantity)} шт.</li><li>Додаткові розхідники Сергія: ${money.format(calculation.serhiy_consumables_per_unit_uah)} грн/шт × ${number.format(calculation.quantity)} шт = ${money.format(calculation.serhiy_consumables_uah)} грн/партія</li><li><strong>Фактична партія для FIFO: ${money.format(calculation.actual_batch_total_uah)} грн (${usable})</strong></li></ul>`; }
function clearCalculation() { state.lastCalculation = null;byId("save-batch").disabled = true;byId("manufacture-batch").disabled = true;byId("calculation").classList.add("empty");byId("calculation").textContent = "Заповніть усі дані партії — результат з’явиться автоматично."; }
function batchValuesForCalculation() { const form = byId("batch-form"),values = formObject(form),parsed = printTimeResult(form.elements.total_print_time_h);if (!values.sku || !parsed.ok || parsed.blank || !(parsed.hours > 0)) return null;return { ...values, total_print_time_h: parsed.hours }; }
function previewBatch() { try { const values = batchValuesForCalculation();if (!values || !state.data?.settings) return clearCalculation();renderCalculation(calculateBatchCost(values, state.data.settings)); } catch { clearCalculation(); } }
function draftQuantity(value) { const quantity = Number(value);return Number.isInteger(quantity) && quantity > 0 ? quantity : null; }
function resetBatchDraftForm(sku) {
  clearTimeout(state.draftQuantityTimer);
  state.draftLoadId += 1;
  const form = byId("batch-form");
  delete form.dataset.requestId;
  draftKeys.forEach((key) => { form.elements[key].value = ""; });
  form.elements.sku.value = sku || "";
  form.elements.defects.value = "0";
  form.elements.serhiy_consumables_uah.value = "0";
  form.elements.manufacture_notes.value = "";
  refreshPrintTimeHint(form.elements.total_print_time_h);
  state.draftLoading = false;
  [...form.elements].forEach((input) => { input.disabled = false; });
  byId("reload").disabled = false;
  feedback(byId("batch-feedback"), "");
  clearCalculation();
}
function queueDraftLoadForQuantity(value) {
  clearTimeout(state.draftQuantityTimer);
  const form = byId("batch-form"),sku = form.elements.sku.value,quantity = draftQuantity(value);
  if (!sku || quantity == null) return;
  state.draftQuantityTimer = setTimeout(() => loadDraft(sku, quantity).catch((error) => status(errorText(error), true)), 350);
}
async function loadDraft(sku, quantityValue) {
  if (operation.busy) return;
  const form = byId("batch-form"), quantity = draftQuantity(quantityValue),loadId = ++state.draftLoadId;
  // SKU or quantity changes invalidate both the previous read and its pending retry key.
  delete form.dataset.requestId;
  if (!sku || quantity == null) {
    resetBatchDraftForm(sku);
    status(!sku ? "Оберіть SKU для розрахунку." : "Вкажіть кількість у партії, щоб завантажити збережений розрахунок.", "info");
    return;
  }
  // Keep the current calculation while checking the new quantity. A missing
  // preset must never destroy values that Serhiy has just entered.
  form.elements.quantity.value = String(quantity);
  state.draftLoading = Boolean(sku && quantity != null);
  [...form.elements].forEach((input) => { if (input.name !== "sku") input.disabled = state.draftLoading; });
  byId("reload").disabled = state.draftLoading;
  feedback(byId("batch-feedback"), "");
  operationFeedback(`Завантажую розрахунок Сергія для ${quantity} шт…`, "busy", "batch-feedback");
  try {
    const payload = await request(`/api/batch-draft?sku=${encodeURIComponent(sku)}&quantity=${encodeURIComponent(quantity)}`);
    if (loadId !== state.draftLoadId || form.elements.sku.value !== sku || form.elements.quantity.value !== String(quantity)) return;
    if (payload.found) {
      draftKeys.forEach((key) => { form.elements[key].value = payload.values?.[key] ?? ""; });
      form.elements.quantity.value = String(quantity);
      refreshPrintTimeHint(form.elements.total_print_time_h);
    }
    feedback(byId("batch-feedback"), "");
    status(payload.found ? `Розрахунок Сергія для ${quantity} шт завантажено.` : `Для ${quantity} шт ще немає збереженого розрахунку Сергія.`, payload.found ? "success" : "info");
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
async function loadSettingsJournal() { state.settingsJournal = await request("/api/settings-journal");renderInformationTableInto("settings-journal", state.settingsJournal.rows, null, "settings-journal"); }
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

document.querySelectorAll(".zone-button").forEach((button) => button.addEventListener("click", () => { document.querySelectorAll(".zone-button").forEach((item) => { const active = item === button;item.classList.toggle("active", active);item.setAttribute("aria-selected", String(active)); });document.querySelectorAll(".zone").forEach((zone) => zone.classList.toggle("active", zone.id === `zone-${button.dataset.zoneTarget}`)); }));
document.addEventListener("click", (event) => {
  const moveButton = event.target.closest("[data-info-move]");
  if (moveButton) {
    const tableId = decodeURIComponent(moveButton.dataset.infoTable),header = decodeURIComponent(moveButton.dataset.infoHeader),entry = renderedInformationTables.get(tableId),preferences = tablePreferences(tableId);
    if (!entry) return;
    const view = prepareInformationTable(entry.rows, state.data?.skus || [], preferences, tableOptions[tableId]);
    const order = [...view.headers],from = order.indexOf(header),to = moveButton.dataset.infoMove === "left" ? from - 1 : from + 1;
    if (from < 0 || to < 0 || to >= order.length) return;
    [order[from], order[to]] = [order[to], order[from]];preferences.order = order;saveInformationPreferences();renderInformationTableInto(tableId === "all-products" ? "all-products" : tableId, entry.rows, entry.actionCell, tableId);
    return;
  }
  const pageButton = event.target.closest("[data-info-page]");
  if (pageButton) {
    const tableId = decodeURIComponent(pageButton.dataset.infoTable),preferences = tablePreferences(tableId);
    preferences.page += pageButton.dataset.infoPage === "next" ? 1 : -1;saveInformationPreferences();renderInformationTable(tableId);
    return;
  }
  const button = event.target.closest("[data-info-sort]");
  if (!button) return;
  const tableId = decodeURIComponent(button.dataset.infoTable),header = decodeURIComponent(button.dataset.infoSort),preferences = tablePreferences(tableId);
  if (preferences.sort === header) preferences.direction = preferences.direction === "asc" ? "desc" : "asc";
  else { preferences.sort = header;preferences.direction = "asc"; }
  preferences.page = 1;saveInformationPreferences();renderInformationTable(tableId, header);
});
document.addEventListener("change", (event) => {
  if (event.target.matches("[data-info-type]")) {
    const tableId = decodeURIComponent(event.target.dataset.infoType),preferences = tablePreferences(tableId);preferences.type = decodeURIComponent(event.target.value);preferences.page = 1;saveInformationPreferences();renderInformationTable(tableId);
  } else if (event.target.matches("[data-info-column]")) {
    const tableId = decodeURIComponent(event.target.dataset.infoTable);tablePreferences(tableId).visible[decodeURIComponent(event.target.dataset.infoColumn)] = event.target.checked;saveInformationPreferences();renderInformationTable(tableId);
  } else return;
});
byId("attention").addEventListener("click", (event) => {
  const viewButton = event.target.closest("[data-attention-view]");
  if (viewButton) { state.attentionView = viewButton.dataset.attentionView;renderAttention();return; }
  const toggle = event.target.closest("[data-attention-toggle]");
  if (!toggle) return;
  const id = decodeURIComponent(toggle.dataset.attentionToggle),hidden = new Set(state.attentionHidden);
  if (hidden.has(id)) hidden.delete(id);else hidden.add(id);
  state.attentionHidden = [...hidden];saveAttentionHidden();renderAttention();
});
byId("settings-toggle").addEventListener("click", async () => { const panel = byId("settings-panel"),opening = panel.classList.contains("hidden");panel.classList.toggle("hidden", !opening);byId("settings-toggle").setAttribute("aria-expanded", String(opening));if (opening) try { await loadSettingsJournal(); } catch (error) { status(errorText(error), true); } });
byId("reload").addEventListener("click", () => reload().catch((error) => status(errorText(error), true)));
byId("batch-form").elements.sku.addEventListener("change", (event) => {
  const sku = event.target.value;
  resetBatchDraftForm(sku);
  status(sku ? "Вкажіть кількість у партії. Новий розрахунок почнеться з порожніх полів." : "Оберіть SKU для розрахунку.", "info");
});
byId("batch-form").elements.quantity.addEventListener("input", (event) => queueDraftLoadForQuantity(event.target.value));
byId("product-form").elements.sku.addEventListener("change", fillProductForm);
byId("stock-form").elements.sku.addEventListener("change", fillStockForm);
document.querySelectorAll(".sku-search").forEach((input, index) => {
  const select = input.closest(".sku-picker").querySelector(".sku-select"),listId = `sku-options-${index + 1}`;
  input.setAttribute("role", "combobox");input.setAttribute("aria-autocomplete", "list");input.setAttribute("aria-expanded", "false");input.setAttribute("aria-controls", listId);
  const { list } = skuPickerParts(select);if (list) list.id = listId;
  input.addEventListener("focus", () => { input.closest(".sku-picker").dataset.editing = "true";openSkuOptions(select); });
  input.addEventListener("input", () => { input.closest(".sku-picker").dataset.editing = "true";openSkuOptions(select); });
  input.addEventListener("keydown", (event) => { if (event.key === "Escape") { closeSkuOptions(select);input.blur(); } else if (event.key === "Enter") { const option = input.closest(".sku-picker").querySelector("[data-sku-option]");if (option) { event.preventDefault();chooseSku(select, decodeURIComponent(option.dataset.skuOption)); } } });
  input.addEventListener("blur", () => setTimeout(() => { if (!input.closest(".sku-picker").contains(document.activeElement)) closeSkuOptions(select); }, 120));
});
document.addEventListener("click", (event) => { const option = event.target.closest("[data-sku-option]");if (!option) return;const select = option.closest(".sku-picker").querySelector(".sku-select");chooseSku(select, decodeURIComponent(option.dataset.skuOption)); });

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
    const calculation = calculateBatchCost(values, state.data.settings);
    const body = { sku: values.sku, printed_quantity: values.quantity, actual_time_hours: values.total_print_time_h, actual_material_g: values.total_weight_g, spool_weight_g: values.spool_weight_g, spool_price_uah: values.spool_price_uah, serhiy_consumables_uah: calculation.serhiy_consumables_uah, defects: values.defects || 0, notes: values.manufacture_notes || "", request_id: stableRequestId(form) };
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
