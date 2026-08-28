import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const appPath = process.env.DRAFT_TYPE_APP_PATH
  ? path.resolve(process.env.DRAFT_TYPE_APP_PATH)
  : path.resolve(here, "../public/app.js");
const apiPath = path.resolve(here, "../../apps-script-3dp-api/Code.gs");

test("draft type labels match the 3D-P API mapping by value and order", () => {
  const appCode = fs.readFileSync(appPath, "utf8");
  const apiCode = fs.readFileSync(apiPath, "utf8");

  const draftTypeSource = appCode.match(/const draftTypes = (\[[^\n]+\]);/)?.[1];
  assert.ok(draftTypeSource, "public/app.js contains the draftTypes list");
  const clientLabels = JSON.parse(draftTypeSource);

  const apiContext = vm.createContext({});
  vm.runInContext(apiCode, apiContext, { filename: "Code.gs" });
  const apiSuggestions = JSON.parse(
    vm.runInContext("JSON.stringify(NOMENCLATURE_DRAFT_SUGGESTIONS_3DP)", apiContext),
  );

  assert.deepEqual(
    clientLabels,
    Object.keys(apiSuggestions),
    "Serhiy draft types stay aligned with the API mapping",
  );
});
