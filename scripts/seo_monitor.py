#!/usr/bin/env python3
"""AUTO-003: monthly Google Search Console page-performance monitor.

Usage:
    python scripts/seo_monitor.py
    python scripts/seo_monitor.py --dry-run
"""

from __future__ import annotations

import argparse
import calendar
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import date, datetime, timezone
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parent.parent
SNAPSHOT_DIR = REPO_ROOT / "plans" / "seo-snapshot"
NOTION_DB_ID = "35c3f857-2fc5-4a78-96c8-af0efd4cf8d4"
NOTION_VERSION = "2022-06-28"


def load_dotenv() -> None:
    for env_path in (REPO_ROOT / ".env.review", Path(__file__).parent / ".env"):
        if not env_path.exists():
            continue
        for raw_line in env_path.read_text(encoding="utf-8").splitlines():
            line = raw_line.strip()
            if line and not line.startswith("#") and "=" in line:
                key, _, value = line.partition("=")
                os.environ.setdefault(key.strip(), value.strip())


def require_env(*keys: str) -> dict[str, str]:
    missing = [key for key in keys if not os.environ.get(key)]
    if missing:
        raise RuntimeError("Missing required configuration: " + ", ".join(missing))
    return {key: os.environ[key] for key in keys}


def month_range(year: int, month: int) -> tuple[date, date]:
    last_day = calendar.monthrange(year, month)[1]
    return date(year, month, 1), date(year, month, last_day)


def previous_month(year: int, month: int) -> tuple[int, int]:
    return (year - 1, 12) if month == 1 else (year, month - 1)


def post_form(url: str, data: dict[str, str]) -> dict:
    body = urllib.parse.urlencode(data).encode("utf-8")
    request = urllib.request.Request(url, data=body, headers={"Content-Type": "application/x-www-form-urlencoded"}, method="POST")
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            return json.loads(response.read())
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")[:500]
        raise RuntimeError(f"OAuth token request failed ({exc.code}): {detail}") from exc


def access_token(config: dict[str, str]) -> str:
    data = post_form(
        "https://oauth2.googleapis.com/token",
        {
            "client_id": config["GSC_CLIENT_ID"],
            "client_secret": config["GSC_CLIENT_SECRET"],
            "refresh_token": config["GSC_REFRESH_TOKEN"],
            "grant_type": "refresh_token",
        },
    )
    token = data.get("access_token")
    if not token:
        raise RuntimeError("OAuth response did not include access_token")
    return token


def fetch_pages(site_url: str, token: str, start: date, end: date) -> dict[str, dict[str, float]]:
    endpoint = "https://www.googleapis.com/webmasters/v3/sites/" + urllib.parse.quote(site_url, safe="") + "/searchAnalytics/query"
    payload = json.dumps({
        "startDate": start.isoformat(),
        "endDate": end.isoformat(),
        "dimensions": ["page"],
        "type": "web",
        "rowLimit": 25_000,
    }).encode("utf-8")
    request = urllib.request.Request(
        endpoint,
        data=payload,
        headers={"Authorization": f"Bearer {token}", "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=45) as response:
            data = json.loads(response.read())
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")[:500]
        raise RuntimeError(f"Search Console query failed ({exc.code}): {detail}") from exc

    pages: dict[str, dict[str, float]] = {}
    for row in data.get("rows", []):
        keys = row.get("keys", [])
        if not keys:
            continue
        pages[keys[0]] = {
            "clicks": float(row.get("clicks", 0)),
            "impressions": float(row.get("impressions", 0)),
            "position": float(row.get("position", 0)),
        }
    return pages


def load_snapshot(path: Path) -> dict:
    if not path.exists():
        return {}
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Snapshot is invalid JSON: {path.name}: {exc}") from exc


def detect_drops(previous: dict[str, dict[str, float]], current: dict[str, dict[str, float]]) -> list[dict]:
    issues: list[dict] = []
    for url, old in previous.items():
        new = current.get(url, {"clicks": 0.0, "impressions": 0.0, "position": None})
        old_position = old["position"]
        new_position = new["position"]
        position_drop = old_position and new_position is not None and (new_position - old_position) > 5
        click_drop = old["clicks"] > 10 and new["clicks"] < old["clicks"] * 0.7
        if not (position_drop or click_drop):
            continue
        issues.append({
            "url": url,
            "old": old,
            "new": new,
            "position_drop": round((new_position - old_position), 2) if position_drop else 0,
            "click_drop_pct": round((1 - new["clicks"] / old["clicks"]) * 100, 1) if old["clicks"] else 0,
            "priority": "High" if position_drop and (new_position - old_position) > 10 else "Medium",
        })
    return sorted(issues, key=lambda issue: (issue["priority"] != "High", -issue["position_drop"], -issue["click_drop_pct"]))


def notion_task(issue: dict, period_tag: str) -> str | None:
    token = os.environ.get("NOTION_TOKEN", "")
    if not token:
        return None
    old_position = issue["old"]["position"]
    new_position = issue["new"]["position"]
    position_text = f"{old_position:.1f}→{new_position:.1f}" if new_position is not None else f"{old_position:.1f}→no data"
    url = issue["url"]
    slug = urllib.parse.quote(url, safe="")[-28:]
    payload = {
        "parent": {"database_id": NOTION_DB_ID},
        "properties": {
            "Name": {"title": [{"type": "text", "text": {"content": f"SEO drop: {url} (pos {position_text})"[:1900]}}]},
            "Status": {"status": {"name": "Not started"}},
            "Priority": {"select": {"name": issue["priority"]}},
            "Category": {"rich_text": [{"type": "text", "text": {"content": "SEO"}}]},
            "Roadmap ID": {"rich_text": [{"type": "text", "text": {"content": f"SEO-{period_tag}-{slug}"}}]},
        },
    }
    request = urllib.request.Request(
        "https://api.notion.com/v1/pages",
        data=json.dumps(payload).encode("utf-8"),
        headers={"Authorization": f"Bearer {token}", "Notion-Version": NOTION_VERSION, "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=15) as response:
            return json.loads(response.read()).get("url") or "created"
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")[:500]
        raise RuntimeError(f"Notion task creation failed ({exc.code}): {detail}") from exc


def main() -> int:
    parser = argparse.ArgumentParser(description="Compare monthly Search Console page metrics and create SEO review tasks.")
    parser.add_argument("--dry-run", action="store_true", help="Do not create Notion tasks; snapshot is still saved.")
    args = parser.parse_args()
    load_dotenv()
    config = require_env("GSC_CLIENT_ID", "GSC_CLIENT_SECRET", "GSC_REFRESH_TOKEN", "GSC_SITE_URL")

    # Run once per month against fully completed months. This avoids false drops
    # caused by Search Console's normal two-day reporting delay and partial MTD data.
    today = date.today()
    current_year, current_month_number = previous_month(today.year, today.month)
    current_start, current_end = month_range(current_year, current_month_number)
    previous_year, previous_month_number = previous_month(current_year, current_month_number)
    previous_start, previous_end = month_range(previous_year, previous_month_number)
    current_tag = f"{current_year:04d}-{current_month_number:02d}"
    previous_tag = f"{previous_year:04d}-{previous_month_number:02d}"
    current_snapshot_path = SNAPSHOT_DIR / f"{current_tag}.json"
    previous_snapshot_path = SNAPSHOT_DIR / f"{previous_tag}.json"
    previous_snapshot = load_snapshot(previous_snapshot_path)
    current_snapshot = load_snapshot(current_snapshot_path)

    print(f"[AUTO-003] GSC period: {current_start}..{current_end}; comparator: {previous_start}..{previous_end}")
    token = access_token(config)
    current_pages = fetch_pages(config["GSC_SITE_URL"], token, current_start, current_end)
    previous_pages = fetch_pages(config["GSC_SITE_URL"], token, previous_start, previous_end)
    print(f"[AUTO-003] Pages: current={len(current_pages)}, previous={len(previous_pages)}")

    baseline_exists = bool(previous_snapshot)
    issues = detect_drops(previous_pages, current_pages) if baseline_exists else []
    if not baseline_exists:
        print(f"[AUTO-003] Baseline: {previous_snapshot_path.relative_to(REPO_ROOT)} not found — snapshot only, no tasks created")

    prior_created = set(current_snapshot.get("notion_task_urls", []))
    created_urls = set(prior_created)
    created_count = 0
    for issue in issues:
        old = issue["old"]
        new = issue["new"]
        print(f"[AUTO-003] DROP {issue['priority']}: {issue['url']} | pos {old['position']:.1f}->{new['position'] if new['position'] is not None else 'no data'} | clicks {old['clicks']:.0f}->{new['clicks']:.0f}")
        if args.dry_run:
            print("[AUTO-003] DRY RUN — Notion task skipped")
            continue
        if issue["url"] in prior_created:
            print("[AUTO-003] Notion task already recorded for this period — skipped")
            continue
        result = notion_task(issue, current_tag)
        if result:
            created_urls.add(issue["url"])
            created_count += 1
            print(f"[AUTO-003] Notion: {result}")
        else:
            print("[AUTO-003] NOTION_TOKEN not set — task skipped")

    SNAPSHOT_DIR.mkdir(parents=True, exist_ok=True)
    snapshot = {
        "schema_version": 1,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "site_url": config["GSC_SITE_URL"],
        "period": {"start": current_start.isoformat(), "end": current_end.isoformat()},
        "comparison_period": {"start": previous_start.isoformat(), "end": previous_end.isoformat()},
        "pages": current_pages,
        "notion_task_urls": sorted(created_urls),
    }
    current_snapshot_path.write_text(json.dumps(snapshot, ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(f"[AUTO-003] Snapshot: {current_snapshot_path.relative_to(REPO_ROOT)}")
    print(f"[AUTO-003] Summary: checked={len(current_pages)} problems={len(issues)} notion_tasks={created_count} dry_run={'yes' if args.dry_run else 'no'}")
    print("[AUTO-003] done=ok")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except RuntimeError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise SystemExit(1)
