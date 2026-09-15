import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here=path.dirname(fileURLToPath(import.meta.url));
const html=fs.readFileSync(path.resolve(here,"../booster-dashboard.html"),"utf8");

function functionSource(name) {
  const source=html.match(new RegExp("function "+name+"\\([^\\n]*\\) \\{[\\s\\S]*?\\n\\}"))?.[0];
  assert.ok(source,"dashboard helper "+name+" is present");
  return source;
}

const context=vm.createContext({});
vm.runInContext(functionSource("skuShort")+"\n"+functionSource("skuName"),context,{filename:"dashboard-sku-name-helpers.js"});

const canonical3dName="Брелок Charmander (Pokémon) — 3D-друк";
assert.equal(
  vm.runInContext("skuName("+JSON.stringify(canonical3dName+"  ")+', "BR-CHARM-100")',context),
  canonical3dName,
  "canonical 3D names stay complete instead of collapsing to the common suffix"
);
assert.equal(
  vm.runInContext("skuName('Pokémon — Prismatic Evolutions — EN — Booster', 'PKM-EN-PREV-BST')",context),
  "Prismatic Evolutions — EN — Booster",
  "existing TCG brand-prefix shortening remains unchanged"
);

console.log("3D SKU display-name tests passed");
