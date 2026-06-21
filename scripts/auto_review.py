#!/usr/bin/env python3
"""
AUTO-002: Codex patch auto-review
Reads diagnostic report + handoff + git diff → calls Claude API → saves review + posts to Notion.

Usage:
  python scripts/auto_review.py TASK-ID       # review specific task
  python scripts/auto_review.py               # auto-detect from latest diagnostic
  python scripts/auto_review.py --dry-run     # print review, skip Notion post

Requires env vars (set in scripts/.env or system env):
  ANTHROPIC_API_KEY  — Claude API key
  NOTION_TOKEN       — Notion Internal Integration token (from notion.so/my-integrations)
"""

import os
import sys
import glob
import json
import subprocess
from datetime import datetime
from pathlib import Path

# ── paths ──────────────────────────────────────────────────────────────────
REPO_ROOT   = Path(__file__).parent.parent
DIAG_DIR    = REPO_ROOT / "diagnostics"
HANDOFF_DIR = REPO_ROOT / "handoffs"

# ── notion config ───────────────────────────────────────────────────────────
NOTION_DB_ID = "5aef22c3-048d-4dde-a5b1-ad409de9301c"   # Booster Shop Roadmap

# ── helpers ─────────────────────────────────────────────────────────────────

def load_dotenv():
    """Load scripts/.env if exists (simple KEY=VALUE parser, no external deps)."""
    env_path = Path(__file__).parent / ".env"
    if env_path.exists():
        for line in env_path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                k, _, v = line.partition("=")
                os.environ.setdefault(k.strip(), v.strip())


def find_diagnostic(task_id: str | None) -> Path | None:
    pattern = str(DIAG_DIR / (f"{task_id}*report*" if task_id else "*report*"))
    files = sorted(glob.glob(pattern + ".md")) + sorted(glob.glob(pattern + ".txt"))
    return Path(files[-1]) if files else None


def find_handoff(task_id: str) -> Path | None:
    # exact prefix first
    for f in sorted(HANDOFF_DIR.glob(f"{task_id}*.md")):
        return f
    # case-insensitive fallback
    tid_lower = task_id.lower()
    for f in sorted(HANDOFF_DIR.glob("*.md")):
        if tid_lower in f.name.lower():
            return f
    return None


def git_diff(max_chars: int = 20_000) -> str:
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
            diff = diff[:max_chars] + "\n...[diff truncated — too large]"
        return f"{stat}\n---\n{diff}" if stat.strip() else diff
    except Exception as exc:
        return f"[git diff unavailable: {exc}]"


def call_claude(task_id: str, diagnostic: str, handoff: str | None, diff: str) -> str:
    try:
        import anthropic
    except ImportError:
        return "[ERROR] anthropic package not installed — run: pip install anthropic"

    api_key = os.environ.get("ANTHROPIC_API_KEY", "")
    if not api_key:
        return "[ERROR] ANTHROPIC_API_KEY not set"

    handoff_block = handoff if handoff else "[handoff file not found — reviewing without scope reference]"

    prompt = f"""You are doing an automated post-Codex review for Booster Shop (OpenCart / PHP site, Ukraine).

TASK: {task_id}

## ORIGINAL HANDOFF (scope):
{handoff_block}

## CODEX DIAGNOSTIC REPORT:
{diagnostic}

## GIT DIFF (last commit):
{diff}

Answer concisely in this exact structure:

### 1. Task solved?
[Yes / Partially / No] — one sentence reason.

### 2. Side effects
List any unexpected changes, removed code, or risky mutations. "None detected" if clean.

### 3. Acceptance criteria check
For each AC in the handoff: ✅ met / ⚠️ unclear / ❌ not met.
If no handoff, skip this section.

### 4. Owner manual checks (Ukrainian)
Конкретні кроки, які власник має перевірити вручну на живому сайті після деплою.

### 5. Verdict
One of:
✅ Ready to deploy
⚠️ Deploy with caution — [reason]
❌ Do NOT deploy — [reason]
"""

    client = anthropic.Anthropic(api_key=api_key)
    msg = client.messages.create(
        model="claude-haiku-4-5-20251001",
        max_tokens=1024,
        messages=[{"role": "user", "content": prompt}]
    )
    return msg.content[0].text


def post_notion_comment(page_id: str, text: str) -> bool:
    try:
        import urllib.request
        token = os.environ.get("NOTION_TOKEN", "")
        if not token:
            return False
        body = json.dumps({
            "parent": {"page_id": page_id},
            "rich_text": [{"type": "text", "text": {"content": text[:2000]}}]
        }).encode()
        req = urllib.request.Request(
            "https://api.notion.com/v1/comments",
            data=body,
            headers={
                "Authorization": f"Bearer {token}",
                "Notion-Version": "2022-06-28",
                "Content-Type": "application/json",
            },
            method="POST"
        )
        with urllib.request.urlopen(req, timeout=10) as r:
            return r.status == 200
    except Exception as exc:
        print(f"[Notion] post failed: {exc}")
        return False


def find_notion_page(task_id: str) -> str | None:
    try:
        import urllib.request
        token = os.environ.get("NOTION_TOKEN", "")
        if not token:
            return None
        body = json.dumps({
            "filter": {
                "property": "Roadmap ID",
                "rich_text": {"equals": task_id}
            },
            "page_size": 1
        }).encode()
        req = urllib.request.Request(
            f"https://api.notion.com/v1/databases/{NOTION_DB_ID}/query",
            data=body,
            headers={
                "Authorization": f"Bearer {token}",
                "Notion-Version": "2022-06-28",
                "Content-Type": "application/json",
            },
            method="POST"
        )
        with urllib.request.urlopen(req, timeout=10) as r:
            data = json.loads(r.read())
            results = data.get("results", [])
            return results[0]["id"] if results else None
    except Exception as exc:
        print(f"[Notion] search failed: {exc}")
        return None


def save_review(task_id: str, review: str) -> Path:
    date_str = datetime.now().strftime("%Y-%m-%d")
    out = DIAG_DIR / f"{task_id}_auto_review_{date_str}.md"
    out.write_text(
        f"# AUTO-REVIEW: {task_id} — {date_str}\n\n"
        f"_Generated by AUTO-002 (auto_review.py)_\n\n"
        f"{review}\n",
        encoding="utf-8"
    )
    return out


# ── main ────────────────────────────────────────────────────────────────────

def main():
    load_dotenv()

    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    flags = [a for a in sys.argv[1:] if a.startswith("--")]
    dry_run = "--dry-run" in flags

    task_id: str | None = args[0] if args else None

    # 1. find diagnostic
    print(f"[AUTO-002] Searching diagnostic{f' for {task_id}' if task_id else ' (latest)'}...")
    diag_path = find_diagnostic(task_id)
    if not diag_path:
        print("ERROR: No diagnostic report found in diagnostics/")
        print("       Run: python scripts/auto_review.py TASK-ID")
        sys.exit(1)

    # infer task_id from filename if not given
    if not task_id:
        task_id = diag_path.stem.split("_")[0]

    print(f"[AUTO-002] Task     : {task_id}")
    print(f"[AUTO-002] Diagnostic: {diag_path.name}")

    diagnostic = diag_path.read_text(encoding="utf-8")

    # 2. find handoff
    handoff_path = find_handoff(task_id)
    handoff = handoff_path.read_text(encoding="utf-8") if handoff_path else None
    if handoff_path:
        print(f"[AUTO-002] Handoff  : {handoff_path.name}")
    else:
        print(f"[AUTO-002] WARNING  : no handoff found for {task_id}")

    # 3. git diff
    print("[AUTO-002] Fetching git diff...")
    diff = git_diff()

    # 4. Claude review
    print("[AUTO-002] Calling Claude API (haiku)...")
    review = call_claude(task_id, diagnostic, handoff, diff)

    # 5. save locally
    if not dry_run:
        review_path = save_review(task_id, review)
        print(f"[AUTO-002] Saved    : {review_path.name}")
    else:
        print("[AUTO-002] DRY RUN — skipping file save")

    # 6. post to Notion
    if not dry_run and os.environ.get("NOTION_TOKEN"):
        print("[AUTO-002] Posting to Notion...")
        page_id = find_notion_page(task_id)
        if page_id:
            ts = datetime.now().strftime("%Y-%m-%d %H:%M")
            comment = f"🤖 Auto-review {ts}\n\n{review}"
            ok = post_notion_comment(page_id, comment)
            print(f"[AUTO-002] Notion   : {'✅ posted' if ok else '⚠️ failed'}")
        else:
            print(f"[AUTO-002] Notion   : ⚠️ task {task_id} not found in roadmap DB")
    elif dry_run:
        print("[AUTO-002] DRY RUN — skipping Notion post")
    else:
        print("[AUTO-002] NOTION_TOKEN not set — skipping Notion post")

    # 7. print review
    print()
    print("=" * 60)
    print(review)
    print("=" * 60)


if __name__ == "__main__":
    main()
