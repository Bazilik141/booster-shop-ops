import assert from "node:assert/strict";
import test from "node:test";
import { draftCategories, nomenclatureTypes, nomenclatureTypeForDraftCategory } from "../public/draft-categories.js";

test("18 UI categories map only to Nomenclature D dropdown values", () => {
  assert.equal(draftCategories.length, 18);
  assert.deepEqual(nomenclatureTypes, ["Брелок", "Фігурка", "Функціональний аксесуар", "Інше"]);
  assert.equal(nomenclatureTypeForDraftCategory("Брелок-клікер"), "Брелок");
  assert.equal(nomenclatureTypeForDraftCategory("Рухома фігурка"), "Фігурка");
  assert.equal(nomenclatureTypeForDraftCategory("Підставка для карток"), "Функціональний аксесуар");
  assert.equal(nomenclatureTypeForDraftCategory("Кейс / контейнер для зберігання"), "Функціональний аксесуар");
  assert.deepEqual([...new Set(draftCategories.map((item) => item.broadType))].sort(), nomenclatureTypes.slice(0, 3).sort());
  assert.throws(() => nomenclatureTypeForDraftCategory("Не вказано"), /Оберіть механіку/);
});
