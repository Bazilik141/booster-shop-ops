function skuOf(row) {
  return String(row?.SKU || "").trim();
}

function monthOf(value) {
  const text = String(value || "").trim();
  const match = text.match(/^(\d{4}-\d{2})/);
  return match ? match[1] : (text || "unknown");
}

export function collectAttentionSignals({ skus = [], printLog = [] } = {}) {
  const signals = [];
  (skus || []).forEach((row) => {
    const sku = skuOf(row);
    if (!sku) return;
    const stock = Number(row.availability?.["Наявно зараз, шт"]);
    if (Number.isFinite(stock) && stock < 0) {
      signals.push({ id: `attention:negative-stock:${sku}`, kind: "negative-stock", sku, text: `${sku}: від’ємний залишок ${stock} шт.` });
    }
    if (!(Number(row["Час друку за од., год"]) > 0)) {
      signals.push({ id: `attention:no-print-time:${sku}`, kind: "no-print-time", sku, text: `${sku}: не вказано час друку` });
    }
  });

  const defects = new Map();
  (printLog || []).forEach((row) => {
    const sku = skuOf(row), count = Number(row?.["Брак, шт"]);
    if (!sku || !(count > 0)) return;
    const period = monthOf(row?.Дата);
    const id = `attention:defect:${sku}:${period}`;
    defects.set(id, { id, kind: "defect", sku, period, defects: (defects.get(id)?.defects || 0) + count });
  });
  defects.forEach((signal) => {
    signals.push({ ...signal, text: `${signal.sku}: у Друк-лозі записано брак ${signal.defects} шт.` });
  });
  return signals;
}

export function splitAttention(signals, hiddenIds) {
  const hidden = new Set(hiddenIds || []);
  return (signals || []).reduce((result, signal) => {
    result[hidden.has(signal.id) ? "hidden" : "active"].push(signal);
    return result;
  }, { active: [], hidden: [] });
}

export function reconcileHiddenSignalIds(hiddenIds, signals) {
  const available = new Set((signals || []).map((signal) => signal.id));
  return [...new Set(hiddenIds || [])].filter((id) => available.has(id));
}
