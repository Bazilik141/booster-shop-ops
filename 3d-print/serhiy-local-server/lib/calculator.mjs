const number = (value, label) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    throw new Error(`${label} must be a positive number.`);
  }
  return parsed;
};

export function calculateBatchCost(input, settings) {
  const quantity = number(input.quantity, "Batch quantity");
  const totalWeightG = number(input.total_weight_g, "Total product weight");
  const totalTimeHours = number(input.total_time_hours, "Total print time");
  const spoolWeightG = number(input.spool_weight_g, "Spool weight");
  const spoolPriceUah = number(input.spool_price_uah, "Spool price");
  const printerPowerKw = number(settings.printer_power_kw, "Printer power");
  const electricityPrice = number(settings.electricity_price_uah_per_kwh, "Electricity price");
  const amortizationRate = number(settings.amortization_uah_per_hour, "Amortization rate");

  const weightPerUnitG = totalWeightG / quantity;
  const timePerUnitHours = totalTimeHours / quantity;
  const materialUah = (weightPerUnitG / spoolWeightG) * spoolPriceUah;
  const electricityUah = printerPowerKw * timePerUnitHours * electricityPrice;
  const amortizationUah = amortizationRate * timePerUnitHours;

  return {
    quantity,
    total_weight_g: totalWeightG,
    total_time_hours: totalTimeHours,
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
      base_uah: materialUah + electricityUah + amortizationUah,
    },
  };
}

export function settingsFromRange(values) {
  if (!Array.isArray(values) || values.length < 4) {
    throw new Error("3D-P settings block is incomplete.");
  }
  const read = (row, label) => {
    const value = Number(values[row]?.[1]);
    if (!Number.isFinite(value) || value <= 0) throw new Error(`Invalid ${label} in 3D-P settings.`);
    return value;
  };
  return {
    printer_power_kw: read(1, "printer power"),
    electricity_price_uah_per_kwh: read(2, "electricity price"),
    amortization_uah_per_hour: read(3, "amortization rate"),
  };
}
