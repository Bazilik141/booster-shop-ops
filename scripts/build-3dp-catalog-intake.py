"""Build a reviewable catalogue manifest from bounded Google Sheets evidence.

Read-only toward external systems. No apply mode. Proposed SKUs are not assignments.
Run from the repository root after refreshing the named evidence files.
"""
from pathlib import Path
from datetime import datetime, timezone
from collections import Counter
import hashlib
import json
import re
import sys

sys.stdout.reconfigure(encoding="utf-8")
ROOT = Path(__file__).resolve().parents[1]
WORK = ROOT / "work/3dp-catalog-reset-20260902"
OUT = ROOT / "plans/3dp-catalog-reset-20260902"

# A leading '?' marks a new proposal that is not a recorded canonical assignment.
# Sequence corresponds to source rows 3..last; exact names and fingerprints remain
# in the output, making subsequent row movement visible before any future apply.
MAPPING = {
    "Брелоки": "?BR-PKM-300 BR-CHARM-100 BR-BULB-100 BR-SQUIR-100 BR-MEW-100 BR-PIKA-100 ?BR-UMBRE-100 ?BR-UMBRE-110 ?BR-UMBRE-120 BR-CHARM-200 BR-OP-300 BR-OPMUS-100 BR-OPSTR-100 BR-OPFRT-100 ?BR-OP-100 BR-OPSHP-100 BR-OPSKL-100 BR-DITTO-400 BR-PKBL-200".split(),
    "Підставки для карток": "ACC-3D-PKM-110 ACC-3D-PKM-120 ACC-3D-PKM-130 ACC-3D-PKM-200 ACC-3D-PKM-201 ACC-3D-PKM-202 ACC-3D-PKM-300 ACC-3D-PKM-700 ACC-3D-PKM-710 ACC-3D-PKM-711 ACC-3D-PKM-712 ?ACC-3D-PKM-800 ?ACC-3D-PKM-610 ?ACC-3D-PKM-150".split(),
    "Фігурки": "FIG-ONIX-200 FIG-ONIX-500 FIG-ONIX-501 FIG-GEOD-500 FIG-GEOD-501 FIG-GEOD-510 FIG-GEOD-511 FIG-LUFFY-500 FIG-LUFFY-400 FIG-LUFFY-410 ?FIG-HAUNT-200 ?FIG-HAUNT-210 FIG-MEW-100 FIG-CHARZ-200 FIG-ZORO-410 FIG-LUFFY-411 ?FIG-OPSKL-600 ?FIG-OP-400 FIG-NAMI-200 FIG-NAMI-201 FIG-PKBL-600".split(),
    "Пластини": "FIG-JIGGL-300 FIG-MEW-300 FIG-UMBRE-300 FIG-GENG-300 FIG-MAGIK-300 FIG-PIKA-300 FIG-SQUIR-300".split(),
    "Аксесуари шо можна юзать": "ACC-3D-OP-600 ACC-3D-DITTO-410 ACC-3D-DITTO-430 ACC-3D-DITTO-420 ACC-3D-OP-500 ?ACC-3D-LUFFY-500 ?ACC-3D-OPFRT-500 ?ACC-3D-PKM-600 ACC-3D-PKBL-400 ACC-3D-PKBL-401 ?ACC-3D-CHARZ-800 ?ACC-3D-PKBL-810 ACC-3D-PKBL-800".split(),
}

# Owner decisions from 2026-09-02. Keep the source evidence immutable; record
# exclusions and overrides separately so a later refresh cannot erase them.
EXCLUDED_ROWS = {
    ("Брелоки", 3): "Owner excluded the three-piece ChBS keychain set",
    ("Брелоки", 13): "Owner excluded the three-piece One Piece keychain set",
}
PRICE_OVERRIDES = {
    ("Фігурки", 22): {"sku": "FIG-NAMI-201", "rrp_uah": 750, "buyout_uah": 500,
                     "authority": "Owner message, 2026-09-02: Nami L RRP 750, buyout 500"},
}


def read(name):
    return json.loads((WORK / name).read_text(encoding="utf-8"))


def fingerprint(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True).encode()).hexdigest()


def raw(cell):
    entry = cell.get("userEnteredValue", {})
    if "numberValue" in entry:
        return entry["numberValue"]
    if "stringValue" in entry:
        return entry["stringValue"]
    return cell.get("formattedValue", "")


def number(value):
    if isinstance(value, (int, float)) and not isinstance(value, bool):
        return value
    return None


def cell_at(values, index):
    return values[index] if index < len(values) else {}


def shown(value):
    return "—" if value is None or value == "" else str(value)


def build():
    source = read("draft-source.json")
    assert fingerprint(source) == "89ccccff3615c8a7e427c116c3d88598235716784efe9c4098c7aaedb5c75d90", "Source snapshot changed. Reconcile product names, row identities, and SKU proposals before rebuilding."
    assert source["spreadsheetId"] == "1gQLHxS-EGxIOwX3k8UhU-1HFRzpgFrlDTZaSelp4Tu4"
    assert {s["properties"]["title"] for s in source["sheets"]} == set(MAPPING)
    canonical_text = (ROOT / "plans/3D-P_sku-naming-convention_20260807.md").read_text(encoding="utf-8-sig")
    records = []
    exclusions = []
    for sheet in source["sheets"]:
        title = sheet["properties"]["title"]
        data = sheet["data"][0]
        assert data.get("startRow", 0) == 0
        rows = [r for r in data["rowData"][2:] if r.get("values") and raw(r["values"][0])]
        assert len(rows) == len(MAPPING[title]), (title, len(rows), len(MAPPING[title]))
        for index, row in enumerate(rows):
            values = row["values"]
            get = lambda col: raw(cell_at(values, col))
            marker = MAPPING[title][index]
            sku = marker.lstrip("?")
            source_key = (title, index + 3)
            if source_key in EXCLUDED_ROWS:
                exclusions.append({"sheet": title, "row": index + 3,
                                   "source_name": get(0), "sku": sku,
                                   "reason": EXCLUDED_ROWS[source_key]})
                continue
            documented = not marker.startswith("?") and sku in canonical_text
            printable = str(get(15)).strip().casefold()
            assert printable in {"так", "ні", "не бажано"}, (title, index + 3, printable)
            assert re.fullmatch(r"(?:BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}", sku), sku
            single_time, batch_time = number(get(2)), number(get(11))
            assert single_time is not None and batch_time is not None
            quantity = number(get(9))
            assert quantity and quantity > 0 and quantity == int(quantity)
            rrp, buyout = number(get(4)), number(get(5))
            price_override = PRICE_OVERRIDES.get(source_key)
            if price_override:
                assert price_override["sku"] == sku
                rrp, buyout = price_override["rrp_uah"], price_override["buyout_uah"]
            active = printable == "так"
            issues = []
            if rrp is None:
                issues.append("ACTIVE_RRP_MISSING" if active else "INACTIVE_RRP_MISSING")
            if buyout is None:
                issues.append("ACTIVE_BUYOUT_MISSING" if active else "INACTIVE_BUYOUT_MISSING")
            if not documented:
                issues.append("SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE")
            if not get(7) or "треба" in str(get(7)).casefold():
                issues.append("DIMENSIONS_UNRESOLVED")
            if ":" in str(get(7)):
                issues.append("DIMENSIONS_SEPARATOR_REQUIRES_REVIEW")
            source_values = [raw(c) for c in values[:17]]
            records.append({
                "source": {"spreadsheet_id": source["spreadsheetId"], "sheet_id": sheet["properties"]["sheetId"], "sheet": title, "row": index + 3, "values_a_q": source_values, "sha256": fingerprint(source_values)},
                "source_name": get(0),
                "sku": sku,
                "sku_status": "documented_proposal" if documented else "new_proposal",
                "broad_type": "Брелок" if sku.startswith("BR-") else "Фігурка" if sku.startswith("FIG-") else "Функціональний аксесуар",
                "active": active,
                "target_3dp_status": "Активний" if active else "Архів",
                "target_crm_active": "Так" if active else "Ні",
                "rrp_uah": rrp,
                "buyout_uah": buyout,
                "raw_rrp": get(4),
                "raw_buyout": get(5),
                "price_override": price_override,
                "model_url": get(6),
                "dimensions_source_mm": get(7),
                "test_print_completed": str(get(14)).strip().casefold() == "так",
                "can_print_source": get(15),
                "mystery_box_eligible": str(get(16)).strip().casefold() == "так",
                "single": {"weight_g": number(get(1)), "time_h": round(single_time * 24, 10), "cost_uah": number(get(3))},
                "batch": {"quantity": quantity, "weight_g": number(get(10)), "time_h": round(batch_time * 24, 10), "cost_uah": number(get(12)), "unit_cost_uah": number(get(12)) / quantity},
                "opening_stock": 0,
                "operational_cost_basis": "actual_manufactured_batch_fifo",
                "source_cost_role": "planning_estimate_only_not_inventory_cost",
                "serhiy_extra_consumables_uah": None,
                "spool_weight_g": None,
                "spool_price_uah": None,
                "issues": issues,
            })
    skus = [r["sku"] for r in records]
    assert len(skus) == len(set(skus)) == 72
    assert len(exclusions) == 2
    assert sum(r["active"] for r in records) == 59
    summary = {"total": len(records), "active": sum(r["active"] for r in records), "inactive": sum(not r["active"] for r in records), "new_sku_proposals": sum(r["sku_status"] == "new_proposal" for r in records), "by_sheet": dict(Counter(r["source"]["sheet"] for r in records)), "issues": dict(Counter(i for r in records for i in r["issues"]))}
    manifest = {"generated_at_utc": datetime.now(timezone.utc).isoformat(), "mode": "REVIEW_ONLY", "apply_ready": False, "source_snapshot_sha256": fingerprint(source), "summary": summary, "policy": {"source_tabs": list(MAPPING), "exclude_consumables_tab": True, "all_current_3dp_business_data_is_test": True, "active_rule": "can_print_source casefold equals Так", "stock_rule": "zero; source batch capacity and past test-print flags do not create stock", "cost_rule": "FIFO from frozen actual manufactured batch costs; D and N are planning estimates only", "sale_rule": "production cost + 50% of net profit", "marketing_rule": "agreed buyout, no 50% share", "serhiy_consumables_rule": "included once in production cost; do not duplicate in CRM fixture usage", "owner_consumables_rule": "existing CRM purchase and usage ledger", "unknown_policy": "null means unresolved, never zero or an invented value"}, "excluded_records": exclusions, "records": records}
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / "import-manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    md = ["# 3D catalogue import review", "", "Date: 2026-09-02", "", "Review only; no live writes. All identifiers below are proposed import keys, not new assignments.", "", f"{summary['total']} products: {summary['active']} active, {summary['inactive']} inactive. Consumables tab excluded. Opening stock: zero.", "", "Owner exclusions: Брелоки row 3 (ChBS three-piece set) and row 13 (One Piece three-piece set). Owner price override: FIG-NAMI-201 / Nami L = RRP 750 UAH, buyout 500 UAH.", "", "Operational cost comes from actual manufactured batches, consumed FIFO. Source D and N are retained as planning estimates; neither creates inventory or determines historical sale cost. Blank inactive-product prices remain unresolved. A proposed SKU requires a collision check before assignment.", "", "The source's A:Q data and fingerprints are retained in import-manifest.json. Owner price overrides preserve the original cells and have separate provenance. Source names are preserved for matching; final display names can follow verified canonical catalogue names without changing the source record.", "", "| Source tab / row | Product | Proposed SKU | Active | RRP | Buyout | Single estimate | Batch unit estimate | Issues |", "|---|---|---|---|---:|---:|---:|---:|---|"]
    for r in records:
        md.append("| " + " | ".join([f"{r['source']['sheet']} / {r['source']['row']}", str(r['source_name']).replace('|','/'), r['sku'], "Yes" if r['active'] else "No", shown(r['rrp_uah']), shown(r['buyout_uah']), shown(r['single']['cost_uah']), f"{r['batch']['unit_cost_uah']:.2f}", ", ".join(r['issues']) or "—"]) + " |")
    (OUT / "import-review.md").write_text("\n".join(md) + "\n", encoding="utf-8")
    print(json.dumps(summary, ensure_ascii=False))


if __name__ == "__main__":
    build()
