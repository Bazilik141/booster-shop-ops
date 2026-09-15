export const settingRows = Object.freeze([2, 3, 4, 5]);

function controlForRow(form, row) {
  const control = form.elements.namedItem(`setting_${row}`);
  if (!control) throw new Error(`Поле налаштування B${row} відсутнє.`);
  return control;
}

export function fillSettingsForm(form, values) {
  settingRows.forEach((row, index) => {
    const input = controlForRow(form, row);
    input.value = values[index] ?? "";
    input.dataset.expected = String(values[index] ?? "");
  });
}

export function changedSettingWrites(form) {
  return settingRows.flatMap((row) => {
    const input = controlForRow(form, row);
    if (String(input.value ?? "") === String(input.dataset.expected ?? "")) return [];
    return [{ row, value: input.value, expected_current: input.dataset.expected }];
  });
}
