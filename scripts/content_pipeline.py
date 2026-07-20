#!/usr/bin/env python3
"""AUTO-003: generate a reviewed Booster Shop content brief from a topic.

Usage:
    python scripts/content_pipeline.py "покемон бустери"
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import urllib.error
import urllib.request
from datetime import date
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parent.parent
PLANS_DIR = REPO_ROOT / "plans"
PROJECT_CONTEXT = PLANS_DIR / "PROJECT_CONTEXT.md"
NOTION_DB_ID = "5aef22c3-048d-4dde-a5b1-ad409de9301c"
NOTION_VERSION = "2022-06-28"
MODEL = "claude-sonnet-4-6"


def load_dotenv() -> None:
    """Load local secrets without a dotenv dependency; system variables win."""
    for env_path in (REPO_ROOT / ".env.review", Path(__file__).parent / ".env"):
        if not env_path.exists():
            continue
        for raw_line in env_path.read_text(encoding="utf-8").splitlines():
            line = raw_line.strip()
            if line and not line.startswith("#") and "=" in line:
                key, _, value = line.partition("=")
                os.environ.setdefault(key.strip(), value.strip())


def extract_field(markdown: str, label: str) -> str:
    pattern = rf"(?im)^\s*(?:#{{1,6}}\s*)?{re.escape(label)}\s*:\s*(.+?)\s*$"
    match = re.search(pattern, markdown)
    return match.group(1).strip() if match else ""


def validate_content(markdown: str) -> tuple[str, str, str]:
    h1 = extract_field(markdown, "H1")
    meta_title = extract_field(markdown, "Meta title")
    meta_description = extract_field(markdown, "Meta description")
    model_slug = extract_field(markdown, "Slug")
    errors: list[str] = []
    if not h1:
        errors.append("missing H1")
    if not meta_title:
        errors.append("missing Meta title")
    elif len(meta_title) > 60:
        errors.append(f"Meta title is {len(meta_title)} chars (limit: 60)")
    if not meta_description:
        errors.append("missing Meta description")
    elif len(meta_description) > 160:
        errors.append(f"Meta description is {len(meta_description)} chars (limit: 160)")
    if not model_slug:
        errors.append("missing Slug")
    elif not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", model_slug):
        errors.append("Slug must contain only lowercase latin letters, numbers, and hyphens")
    if errors:
        raise ValueError("Claude output did not pass validation: " + "; ".join(errors))
    return h1, meta_title, model_slug


def call_claude(topic: str, context: str) -> str:
    try:
        import anthropic
    except ImportError as exc:
        raise RuntimeError("anthropic package not installed; run: python -m pip install -r scripts/requirements.txt") from exc

    api_key = os.environ.get("ANTHROPIC_API_KEY", "")
    if not api_key:
        raise RuntimeError("ANTHROPIC_API_KEY is not set in .env.review, scripts/.env, or the environment")

    context_block = context if context else "No extra local project context was provided."
    prompt = f"""Ти SEO-копірайтер для українського інтернет-магазину колекційних карток Booster Shop (boosterok.com.ua).

Тематика: {topic}

Локальний контекст проєкту:
{context_block}

Згенеруй Markdown точно з такими полями у цьому порядку:
H1: ...
Meta title: ...
Meta description: ...
Slug: ...
Intro: ...
Основний текст:
...

Вимоги:
- Meta title максимум 60 символів, містить ключове слово та бренд.
- Meta description максимум 160 символів, містить ключове слово і заклик, без крапки наприкінці.
- Slug: лише латиниця, цифри й дефіси.
- Intro: 2–3 речення.
- Основний текст: 400–600 слів, структурований Markdown.
- Мова: українська. Тон: дружній, експертний.
- Не допускай keyword stuffing, вигаданих цін, GTIN, відгуків, рейтингів або неперевірених характеристик товарів.
- Не заявляй «найкращий в Україні» та не давай гарантій, які неможливо перевірити.
"""
    response = anthropic.Anthropic(api_key=api_key).messages.create(
        model=MODEL,
        max_tokens=1800,
        messages=[{"role": "user", "content": prompt}],
    )
    return response.content[0].text.strip()


def notion_select(name: str) -> dict:
    return {"select": {"name": name}}


def post_notion_task(slug: str, date_tag: str) -> str | None:
    token = os.environ.get("NOTION_TOKEN", "")
    if not token:
        return None
    roadmap_id = f"CONTENT-{date_tag.replace('-', '')}-{slug[:20]}"
    payload = {
        "parent": {"database_id": NOTION_DB_ID},
        "properties": {
            "Name": {"title": [{"type": "text", "text": {"content": f"CONTENT: {slug} — ready for review"}}]},
            "Status": notion_select("Not started"),
            "Priority": notion_select("Medium"),
            "Category": notion_select("Content / SEO"),
            "Task Type": notion_select("Claude"),
            "Roadmap ID": {"rich_text": [{"type": "text", "text": {"content": roadmap_id}}]},
        },
    }
    request = urllib.request.Request(
        "https://api.notion.com/v1/pages",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {token}",
            "Notion-Version": NOTION_VERSION,
            "Content-Type": "application/json",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=15) as response:
            return json.loads(response.read()).get("url") or "created"
    except urllib.error.HTTPError as exc:
        detail = exc.read().decode("utf-8", errors="replace")[:500]
        raise RuntimeError(f"Notion task creation failed ({exc.code}): {detail}") from exc


def main() -> int:
    parser = argparse.ArgumentParser(description="Generate a Booster Shop content brief and optional Notion task.")
    parser.add_argument("topic", nargs="+", help="Page topic, in quotes when it contains spaces")
    args = parser.parse_args()
    topic = " ".join(args.topic).strip()
    if not topic:
        parser.error("topic must not be empty")

    load_dotenv()
    context = PROJECT_CONTEXT.read_text(encoding="utf-8")[:12_000] if PROJECT_CONTEXT.exists() else ""
    print(f"[AUTO-003] Topic: {topic}")
    print("[AUTO-003] Calling Claude API...")
    generated = call_claude(topic, context)
    _, _, slug = validate_content(generated)

    date_tag = date.today().isoformat()
    output_path = PLANS_DIR / f"content-{date_tag}-{slug}.md"
    if output_path.exists():
        raise RuntimeError(f"Refusing to overwrite existing plan: {output_path.name}")

    output_path.write_text(
        f"# Content brief — {topic}\n\n"
        f"_Generated by AUTO-003 on {date_tag}; manual factual review is required before publication._\n\n"
        f"{generated}\n",
        encoding="utf-8",
    )
    print(f"[AUTO-003] Saved: {output_path.relative_to(REPO_ROOT)}")

    try:
        notion_result = post_notion_task(slug, date_tag)
        print(f"[AUTO-003] Notion: {notion_result or 'NOTION_TOKEN not set — skipped'}")
    except RuntimeError as exc:
        print(f"[AUTO-003] WARNING: {exc}")
        print("[AUTO-003] Content file was saved; create the review task manually or correct Notion properties.")

    print("[AUTO-003] done=ok")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (RuntimeError, ValueError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise SystemExit(1)
