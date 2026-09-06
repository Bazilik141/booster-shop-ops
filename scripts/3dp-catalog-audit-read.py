"""Read-only, bounded 3D-P API audit. Never prints credentials or request URLs."""
import argparse
import json
import os
from pathlib import Path
import sys
import urllib.parse
import urllib.request

sys.stdout.reconfigure(encoding="utf-8")
parser = argparse.ArgumentParser()
parser.add_argument("action", choices=["3dp_get_range", "3dp_skus", "3dp_sales", "3dp_print_log", "3dp_fixtures", "3dp_payouts", "3dp_plyushky", "3dp_batch_draft", "3dp_stock_adjustments"])
parser.add_argument("--sheet")
parser.add_argument("--range")
parser.add_argument("--sku")
parser.add_argument("--out", required=True)
args = parser.parse_args()
token = os.environ.get("BOOSTER_3DP_TOKEN") or os.environ.get("BOOSTER_3DP_SERHIY_TOKEN")
base = os.environ.get("BOOSTER_3DP_URL", "")
if not token or not base:
    sys.exit("API configuration unavailable; no request sent.")
params = {"action": args.action, "token": token}
for key in ("sheet", "range", "sku"):
    if getattr(args, key):
        params[key] = getattr(args, key)
params["include_archived"] = "true"
params["include_drafts"] = "true"
params["limit"] = "100"
try:
    with urllib.request.urlopen(base + "?" + urllib.parse.urlencode(params), timeout=45) as response:
        data = json.load(response)
except Exception as error:
    sys.exit("API read failed: " + type(error).__name__)
path = Path(args.out)
path.parent.mkdir(parents=True, exist_ok=True)
path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
print(json.dumps({"action": args.action, "ok": data.get("ok"), "keys": list(data), "file": str(path)}, ensure_ascii=False))
