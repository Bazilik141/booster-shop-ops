function flatRow(row) {
  const flat = {};
  Object.entries(row || {}).forEach(([key, value]) => {
    if (key === "row_number") return;
    if (value && typeof value === "object" && !Array.isArray(value)) {
      Object.entries(value).forEach(([child, childValue]) => { flat[child] = childValue; });
    } else {
      flat[key] = value;
    }
  });
  return flat;
}

function numericValue(value) {
  if (typeof value === "number" && Number.isFinite(value)) return value;
  const text = String(value ?? "").trim().replace(/\s/g, "").replace(",", ".");
  return /^-?\d+(?:\.\d+)?$/.test(text) ? Number(text) : null;
}

function compareValues(left, right) {
  const leftNumber = numericValue(left), rightNumber = numericValue(right);
  if (leftNumber !== null && rightNumber !== null) return leftNumber - rightNumber;
  return String(left ?? "").localeCompare(String(right ?? ""), "uk", { numeric: true, sensitivity: "base" });
}

export function isPrintTimeHeader(header) {
  const text = String(header || "");
  return /час друку/i.test(text) && /год/i.test(text);
}

export function prepareInformationTable(rows, skuRows, preferences = {}) {
  const records = (rows || []).map((raw, index) => ({ raw, flat: flatRow(raw), index }));
  const headers = [];
  records.forEach(({ flat }) => Object.keys(flat).forEach((key) => {
    if (!headers.includes(key)) headers.push(key);
  }));

  const typeBySku = new Map((skuRows || []).map((row) => [String(row.SKU || ""), String(row["Тип"] || "").trim()]));
  records.forEach((record) => {
    record.type = String(record.flat["Тип"] || typeBySku.get(String(record.flat.SKU || "")) || "").trim();
  });
  const types = [...new Set(records.map((record) => record.type).filter(Boolean))]
    .sort((left, right) => left.localeCompare(right, "uk", { numeric: true, sensitivity: "base" }));
  const selectedType = types.includes(preferences.type) ? preferences.type : "all";
  const sortKey = headers.includes(preferences.sort) ? preferences.sort : (headers[0] || "");
  const direction = preferences.direction === "desc" ? -1 : 1;
  const visibleHeaders = headers.filter((header) => preferences.visible?.[header] !== false);
  const filtered = records.filter((record) => selectedType === "all" || record.type === selectedType);
  if (sortKey) {
    filtered.sort((left, right) => {
      const comparison = compareValues(left.flat[sortKey], right.flat[sortKey]);
      return comparison === 0 ? left.index - right.index : comparison * direction;
    });
  }
  return { headers, visibleHeaders, records: filtered, types, selectedType, sortKey, direction: direction === 1 ? "asc" : "desc" };
}

export function matrixToObjects(matrix) {
  const rows = matrix || [];
  if (!rows.length) return [];
  const indexes = rows[0].map((header, index) => ({ header: String(header || "").trim(), index })).filter(({ header }) => header);
  return rows.slice(1).filter((row) => row.some((value) => value !== "" && value !== null && typeof value !== "undefined")).map((row) => {
    const result = {};
    indexes.forEach(({ header, index }) => { result[header] = row[index]; });
    return result;
  });
}
