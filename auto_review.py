#!/usr/bin/env python3
"""
AUTO-002: Codex patch auto-review
Reads diagnostic report + handoff + git diff → calls Claude API → saves review + posts to Notion.

Usage (run from repo root):
  python auto_review.py TASK-ID       # review specific task
  python auto_review.py               # auto-detect from latest diagnostic
  python auto_review.py --dry-run     # print review, skip Notion + file save

Config: create .env.review in repo root (see .env.review.example)
  ANTHROPIC_API_KEY  — from console.anthropic.com
  NOTION_TOKEN       — Personal Access Token from notion.so/profile/integrations
"""

import os
import sys
import glob
import json
import subprocess
from datetime import datetime
from pathlib import Path

REPO_ROOT    = Path(__file__).parent
DIAG_DIR     = REPO_ROOT / "diagnostics"
HANDOFF_DIR  = REPO_ROOT / "handoffs"
NOTION_DB_ID = "35c3f857-2fc5-4a78-96c8-af0efd4cf8d4"


def load_dotenv():
    env_path = REPO_ROOT / ".env.review"
    if env_path.exists():
        for line in env_path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                k, _, v = line.partition("=")
                os.environ.setdefault(k.strip(), v.strip())


def find_diagnostic(task_id=None):
    pattern = str(DIAG_DIR / (f"{task_id}*report*" if task_id else "*report*"))
    files = sorted(glob.glob(pattern + ".md")) + sorted(glob.glob(pattern + ".txt"))
    return Path(files[-1]) if files else None


def find_handoff(task_id):
    for f in sorted(HANDOFF_DIR.glob(f"{task_id}*.md")):
        return f
    for f in sorted(HANDOFF_DIR.glob("*.md")):
        if task_id.lower() in f.name.lower():
            return f
    return None


def git_diff(max_chars=20000):
    try:
        stat = subprocess.run(
            ["git", "diff", "HEAD~1..HEAD", "--stat"],
            capture_output=True, text=True, cwd=REPO_ROOT, timeout=15
        ).stdout
        diff = subprocess.run(
            ["git", "diff", "HEAD~1..HEAD"],
            capture_output=True, text=True, cwd=REPO_ROOT, timeout=15
        ).stdout
        if len(diff) > max_chars:
            diff = diff[:max_chars] + "\n...[diff truncated]"
        return (stat + "\n---\n" + diff) if stat.strip() else diff
    except Exception as exc:
        return f"[git diff unavailable: {exc}]"


def call_claude(task_id, diagnostic, handoff, diff):
    try:
        import anthropic
    except ImportError:
        return "[ERROR] anthropic not installed — run: pip install anthropic"
    api_key = os.environ.get("ANTHROPIC_API_KEY", "")
    if not api_key:
        return "[ERROR] ANTHROPIC_API_KEY not set in .env.review"

    handoff_block = handoff or "[handoff not found — reviewing without scope reference]"
    prompt = f"""You are doing an automated post-Codex review for Booster Shop (OpenCart / PHP site, Ukraine).

TASK: {task_id}

## ORIGINAL HANDOFF (scope):
{handoff_block}

## CODEX DIAGNOSTIC REPORT:
{diagnostic}

## GIT DIFF (last commit):
{diff}

Answer concisely:

### 1. Task solved?
[Yes / Partially / No] — one sentence.

### 2. Side effects
Unexpected changes or risky mutations. "None detected" if clean.

### 3. Acceptance criteria check
For each AC: ✅ met / ⚠️ unclear / ❌ not met. Skip if no handoff.

### 4. Owner manual checks (Ukrainian)
Конкретні кроки для перевірки на живому сайті після деплою.

### 5. Verdict
✅ Ready to deploy / ⚠️ Deploy with caution — [reason] / ❌ Do NOT deploy — [reason]
"""
    client = anthropic.Anthropic(api_key=api_key)
    msg = client.messages.create(
        model="claude-haiku-4-5-20251001",
        max_tokens=1024,
        messages=[{"role": "user", "content": prompt}]
    )
    return msg.content[0].text


def find_notion_page(task_id):
    try:
        import urllib.request
        token = os.environ.get("NOTION_TOKEN", "")
        if not token:
            return None
        body = json.dumps({
            "filter": {"property": "Roadmap ID", "rich_text": {"equals": task_id}},
            "page_size": 1
        }).encode()
        req = urllib.request.Request(
            f"https://api.notion.com/v1/databases/{NOTION_DB_ID}/query",
            data=body,
            headers={"Authorization": f"Bearer {token}", "Notion-Version": "2022-06-28", "Content-Type": "application/json"},
            method="POST"
        )
        with urllib.request.urlopen(req, timeout=10) as r:
            results = json.loads(r.read()).get("results", [])
            return results[0]["id"] if results else None
    except Exception as exc:
        print(f"[Notion] search failed: {exc}")
        return None


def post_notion_comment(page_id, text):
    try:
        import urllib.request
        token = os.environ.get("NOTION_TOKEN", "")
        body = json.dumps({
            "parent": {"page_id": page_id},
            "rich_text": [{"type": "text", "text": {"content": text[:2000]}}]
        }).encode()
        req = urllib.request.Request(
            "https://api.notion.com/v1/comments",
            data=body,
            headers={"Authorization": f"Bearer {token}", "Notion-Version": "2022-06-28", "Content-Type": "application/json"},
            method="POST"
        )
        with urllib.request.urlopen(req, timeout=10) as r:
            return r.status == 200
    except Exception as exc:
        print(f"[Notion] post failed: {exc}")
        return False


def save_review(task_id, review):
    date_str = datetime.now().strftime("%Y-%m-%d")
    out = DIAG_DIR / f"{task_id}_auto_review_{date_str}.md"
    out.write_text(
        f"# AUTO-REVIEW: {task_id} — {date_str}\n\n_AUTO-002_\n\n{review}\n",
        encoding="utf-8"
    )
    return out


def main():
    load_dotenv()
    args    = [a for a in sys.argv[1:] if not a.startswith("--")]
    flags   = [a for a in sys.argv[1:] if a.startswith("--")]
    dry_run = "--dry-run" in flags
    task_id = args[0] if args else None

    print(f"[AUTO-002] Searching diagnostic{f' for {task_id}' if task_id else ' (latest)'}...")
    diag_path = find_diagnostic(task_id)
    if not diag_path:
        print("ERROR: No diagnostic report found in diagnostics/")
        sys.exit(1)
    if not task_id:
        task_id = diag_path.stem.split("_")[0]

    print(f"[AUTO-002] Task      : {task_id}")
    print(f"[AUTO-002] Diagnostic: {diag_path.name}")
    diagnostic = diag_path.read_text(encoding="utf-8")

    handoff_path = find_handoff(task_id)
    handoff      = handoff_path.read_text(encoding="utf-8") if handoff_path else None
    print(f"[AUTO-002] Handoff   : {handoff_path.name if handoff_path else 'NOT FOUND'}")

    print("[AUTO-002] git diff...")
    diff = git_diff()

    print("[AUTO-002] Claude API (haiku)...")
    review = call_claude(task_id, diagnostic, handoff, diff)

    if not dry_run:
        print(f"[AUTO-002] Saved     : {save_review(task_id, review).name}")
    else:
        print("[AUTO-002] DRY RUN — no file saved")

    if not dry_run and os.environ.get("NOTION_TOKEN"):
        page_id = find_notion_page(task_id)
        if page_id:
            ts = datetime.now().strftime("%Y-%m-%d %H:%M")
            ok = post_notion_comment(page_id, f"🤖 Auto-review {ts}\n\n{review}")
            print(f"[AUTO-002] Notion    : {'✅ posted' if ok else '⚠️ failed'}")
        else:
            print(f"[AUTO-002] Notion    : ⚠️ {task_id} not found in DB")
    elif not dry_run:
        print("[AUTO-002] NOTION_TOKEN not set — skipping")

    print("\n" + "=" * 60)
    print(review)
    print("=" * 60)


if __name__ == "__main__":
    main()
