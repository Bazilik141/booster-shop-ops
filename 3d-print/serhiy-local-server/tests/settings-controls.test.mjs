import assert from "node:assert/strict";
import test from "node:test";
import fs from "node:fs";
import { changedSettingWrites, fillSettingsForm } from "../public/settings-controls.js";

test("shipped HTML retains each projected setting name exactly once", () => {
  const html = fs.readFileSync(new URL("../public/index.html", import.meta.url), "utf8");
  for (const row of [2, 3, 4, 5]) assert.equal(html.split(`name="setting_${row}"`).length - 1, 1);
  assert.match(html, /id="batch-time-help"[^>]*>Можна: 1:30, 1 год 30 хв або 1,5\./);
  assert.match(html, /data-print-time-error="total_print_time_h"/);
  assert.match(html, /class="actions batch-actions"/);
  assert.match(html, /Механіка \/ категорія/);
  assert.doesNotMatch(html, /batch-draft-owner|Після створення тут з’явиться підказка/);
  for (const id of ["status", "batch-feedback", "draft-result"]) assert.match(html, new RegExp(`id="${id}"[^>]*role="status"`));
});

function fakeSettingsForm() {
  const controls = Object.fromEntries([2, 3, 4, 5].map((row) => [
    `setting_${row}`,
    { value: "", dataset: {} },
  ]));
  return {
    controls,
    form: { elements: { namedItem: (name) => controls[name] || null } },
  };
}

test("each projected settings value fills and submits its own sheet row", () => {
  const { form, controls } = fakeSettingsForm();
  fillSettingsForm(form, [0.17, 4.32, 12, 0.08]);

  assert.deepEqual(
    Object.fromEntries(Object.entries(controls).map(([name, input]) => [name, input.value])),
    { setting_2: 0.17, setting_3: 4.32, setting_4: 12, setting_5: 0.08 },
  );
  assert.deepEqual(changedSettingWrites(form), []);

  controls.setting_4.value = "13";
  assert.deepEqual(changedSettingWrites(form), [
    { row: 4, value: "13", expected_current: "12" },
  ]);
});
