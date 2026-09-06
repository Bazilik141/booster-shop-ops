#!/usr/bin/env python3
"""Build deterministic CRM and 3D-P migration rows from the reviewed manifest."""

from __future__ import annotations

import argparse
import json
import re
from datetime import datetime, timezone
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_MANIFEST = ROOT / "plans" / "3dp-catalog-reset-20260902" / "import-manifest.json"
DEFAULT_OUTPUT = ROOT / "plans" / "3dp-catalog-reset-20260902" / "migration-payload.json"
DEFAULT_DECISIONS = ROOT / "handoffs" / "handoff_3DP-CATALOG-CANONICAL-DECISIONS_claude-to-codex_20260905.md"

ONE_PIECE_TOKENS = (
    "ONE PIECE", "ЛУФФ", "ЗОРО", "НАМІ", "NAMI", "LUFFY", "ZORO",
    "OPSKL", "OPFRT", "OPMUS", "OPSTR", "OPSHP", "-OP-",
)


def compact_note(record: dict) -> str:
    source = record["source"]
    bits = [
        "CATALOG_IMPORT_V1",
        f"source={source['sheet']}!{source['row']}",
        f"dimensions_mm={record.get('dimensions_source_mm') or 'unknown'}",
        f"test_print={'yes' if record.get('test_print_completed') else 'no'}",
        f"can_print={record.get('can_print_source') or 'unknown'}",
        f"mystery={'yes' if record.get('mystery_box_eligible') else 'no'}",
        f"plan_single_uah={record['single'].get('cost_uah')}",
        f"plan_batch_unit_uah={record['batch'].get('unit_cost_uah')}",
        f"source_sha256={source['sha256']}",
    ]
    return "; ".join(bits)


def franchise_for(sku: str, name: str) -> str:
    haystack = " ".join((sku, name)).upper()
    return "One Piece" if any(token in haystack for token in ONE_PIECE_TOKENS) else "Pokémon"


def build_record(record: dict, decision: dict) -> dict:
    active = bool(decision["active"])
    note = compact_note(record)
    rrp = decision.get("rrp")
    buyout = decision.get("buyout")
    sku = decision["canonical_sku"]
    name = decision["canonical_name"]
    franchise = franchise_for(sku, name)
    return {
        "sku": sku,
        "name": name,
        "active": active,
        "rrp_uah": rrp,
        "buyout_uah": buyout,
        "source": record["source"],
        "source_name": record["source_name"],
        "pre_correction_sku": decision["current_crm_sku"],
        "pre_correction_name": decision["current_crm_name"],
        "pre_correction_active": bool(record["active"]),
        "canonical_decision": {
            "decision": decision["decision"],
            "evidence": decision["evidence"],
            "owner_approval_required_in_handoff": bool(decision.get("owner_approval_required")),
        },
        "3dp": {
            "A": sku,
            "B": name,
            "C": franchise,
            "D": record["broad_type"],
            "E": "Продаж на сайті",
            "F": "Активний" if active else "Знятий з продажу",
            "G": record["single"].get("time_h"),
            "H": record["single"].get("weight_g"),
            "I": None,
            "J": None,
            "L": None,
            "M": note,
            "N": None,
            "O": "Активний" if active else "Архів",
            "P": "Imported from reviewed five-tab draft; opening stock 0; cost basis actual manufactured batch FIFO.",
            "Q": rrp,
            "R": buyout,
            "S": record.get("model_url") or None,
        },
        "crm": {
            "A": sku,
            "C": name,
            "D": franchise,
            "E": "UA",
            "F": "3D-друк",
            "G": "3D аксесуар",
            "H": None,
            "I": None,
            "K": 0,
            "L": "Так" if active else "Ні",
            "M": None,
            "N": note,
            "O": None,
            "rrp_E": rrp,
            "rrp_G": "3D catalogue import; owner-approved draft price" if rrp is not None else "Inactive 3D SKU; RRP unresolved in source",
        },
    }


def load_decisions(path: Path) -> list[dict]:
    text = path.read_text(encoding="utf-8")
    match = re.search(r"```json\s*(\[.*?\])\s*```", text, flags=re.DOTALL)
    if not match:
        raise SystemExit("Canonical 72-row JSON mapping was not found in the decision handoff")
    decisions = json.loads(match.group(1))
    if len(decisions) != 72:
        raise SystemExit(f"Expected exactly 72 canonical decisions, found {len(decisions)}")
    return decisions


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", type=Path, default=DEFAULT_MANIFEST)
    parser.add_argument("--decisions", type=Path, default=DEFAULT_DECISIONS)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    args = parser.parse_args()

    manifest = json.loads(args.manifest.read_text(encoding="utf-8"))
    decisions = load_decisions(args.decisions)
    decision_by_source = {
        (item["source_tab"], int(item["source_row"])): item for item in decisions
    }
    if len(decision_by_source) != 72:
        raise SystemExit("Canonical decision source identities are not unique")
    manifest_keys = {(item["source"]["sheet"], int(item["source"]["row"])) for item in manifest["records"]}
    if manifest_keys != set(decision_by_source):
        raise SystemExit("Canonical decision identities do not exactly match the import manifest")
    records = [
        build_record(item, decision_by_source[(item["source"]["sheet"], int(item["source"]["row"]))])
        for item in manifest["records"]
    ]
    if len(records) != 72 or len({item["sku"] for item in records}) != 72:
        raise SystemExit("Expected exactly 72 unique reviewed records")
    active_count = sum(item["active"] for item in records)
    inactive_count = len(records) - active_count
    if (active_count, inactive_count) != (62, 10):
        raise SystemExit(f"Expected exactly 62 active and 10 inactive records, got {active_count}/{inactive_count}")
    excluded_names = {item["source_name"] for item in manifest["excluded_records"]}
    if any(item["name"] in excluded_names for item in records):
        raise SystemExit("An explicitly excluded product entered the migration payload")
    nami = next(item for item in records if item["sku"] == "FIG-NAMI-201")
    if (nami["rrp_uah"], nami["buyout_uah"]) != (750, 500):
        raise SystemExit("Nami L owner price override is missing")
    for item in records:
        if item["active"] and (item["rrp_uah"] is None or item["buyout_uah"] is None):
            raise SystemExit(f"Active SKU has an unresolved price: {item['sku']}")

    payload = {
        "generated_at_utc": datetime.now(timezone.utc).replace(microsecond=0).isoformat(),
        "source_manifest": str(args.manifest.relative_to(ROOT)).replace("\\", "/"),
        "source_canonical_decisions": str(args.decisions.relative_to(ROOT)).replace("\\", "/"),
        "source_snapshot_sha256": manifest["source_snapshot_sha256"],
        "policy": {
            "opening_stock": 0,
            "cost_basis": "actual_manufactured_batch_fifo",
            "active_count": active_count,
            "inactive_count": inactive_count,
            "analytics_sheet": "Аналітика_SKU",
        },
        "records": records,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"ok": True, "records": len(records), "active": active_count, "inactive": inactive_count, "output": str(args.output)}, ensure_ascii=False))


if __name__ == "__main__":
    main()
