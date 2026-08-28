const number = (value, label) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    throw new Error(`${label} must be a positive number.`);
  }
  return parsed;
};

const nonNegative = (value, label) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed) || parsed < 0) {
    throw new Error(`${label} must be a non-negative number.`);
  }
  return parsed;
};

export function calculateBatchCost(input, settings) {
  const quantity = number(input.quantity, "Batch quantity");
  const totalWeightG = number(input.total_weight_g, "Total product weight");
  const totalTimeHours = number(input.total_print_time_h, "Total print time");
  const spoolWeightG = number(input.spool_weight_g, "Spool weight");
  const spoolPriceUah = number(input.spool_price_uah, "Spool price");
  const printerPowerKw = number(settings.printer_power_kw, "Printer power");
  const electricityPrice = number(settings.electricity_price_uah_per_kwh, "Electricity price");
  const amortizationRate = nonNegative(settings.amortization_uah_per_hour, "Amortization rate");
  const defectRate = nonNegative(settings.planned_defect_fraction, "Planned defect fraction");

  const weightPerUnitG = totalWeightG / quantity;
  const timePerUnitHours = totalTimeHours / quantity;
  const materialUah = (weightPerUnitG / spoolWeightG) * spoolPriceUah;
  const electricityUah = printerPowerKw * timePerUnitHours * electricityPrice;
  const amortizationUah = amortizationRate * timePerUnitHours;

  const baseUah = materialUah + electricityUah + amortizationUah;
  return {
    quantity,
    total_weight_g: totalWeightG,
    total_print_time_h: totalTimeHours,
    spool_weight_g: spoolWeightG,
    spool_price_uah: spoolPriceUah,
    per_unit: {
      weight_g: weightPerUnitG,
      time_hours: timePerUnitHours,
    },
    costs: {
      material_uah: materialUah,
      electricity_uah: electricityUah,
      amortization_uah: amortizationUah,
      base_uah: baseUah,
      defect_adjusted_uah: baseUah * (1 + defectRate),
    },
  };
}

export function settingsFromRange(values) {
  if (!Array.isArray(values) || values.length < 4) {
    throw new Error("3D-P settings block is incomplete.");
  }
  const read = (row, label, allowZero = false) => {
    const value = Number(values[row]?.[0]);
    if (!Number.isFinite(value) || (allowZero ? value < 0 : value <= 0)) throw new Error(`Invalid ${label} in 3D-P settings.`);
    return value;
  };
  return {
    printer_power_kw: read(0, "printer power"),
    electricity_price_uah_per_kwh: read(1, "electricity price"),
    amortization_uah_per_hour: read(2, "amortization rate", true),
    planned_defect_fraction: read(3, "planned defect fraction", true),
  };
}
