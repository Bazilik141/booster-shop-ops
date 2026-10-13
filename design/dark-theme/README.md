# Dark theme — direction C «Колекційний сейф» (FUTURE, not canon)

Status: **reference only. Not live. Not part of the current design canon.**
Roadmap task: **RD-29** — Dark version of the site theme (Notion: https://app.notion.com/p/3f86bf20bdb481f48898e4e3548b1399).
Saved: 2026-10-13, owner decision.

## Why these files exist

On 2026-10-13 the account-area redesign (RD-16/RD-17/RD-18) was explored in three directions for the hub and personal-data pages:

| Direction | Character | Outcome |
|---|---|---|
| A · Світла колекція | light, white cards on warm ground, gold accent | **chosen for the account redesign** |
| B · Глибокий синій | solid blue structure | not used |
| **C · Колекційний сейф** | dark, gold foil accents, serif display headings | **kept for the future dark theme of the whole site** |

The owner chose A for the light system and asked to keep C as the visual source for the dark theme. This folder preserves C so RD-29 does not have to rediscover it.

## What is here

| File | Content |
|---|---|
| `dark-C_desktop-account-hub.*` | account hub, desktop (1280 wide) |
| `dark-C_desktop-personal-data.*` | personal data form, desktop |
| `dark-C_mobile-account-hub.*` | account hub, mobile (390 wide) |
| `dark-C_mobile-personal-data.*` | personal data form, mobile |
| `TOKENS-dark.md` | the dark palette, type and component rules extracted from the boards |

Each board comes in two files:
- `*.preview.html` — static page, **open this in a browser** (needs internet for Google Fonts). Links between boards are inert.
- `*.dc.html` — the original Claude Design source. It needs the Claude Design runtime (`support.js`) and does not render on its own; keep it only so the canvas can be rebuilt.

Live canvas (source of truth while it exists): https://claude.ai/artifact/d5fbf671-8451-42ce-b887-4e7904ad81f3 — boards `C-Hub`, `C-Edit`, `C-Mobile-Hub`, `C-Mobile-Edit`.

## How to use

- Take **tokens and rules** from `TOKENS-dark.md`. Do not copy the markup: it is inline-styled mock code, not Twig, and does not use `bs-*` classes.
- Only the account hub and personal data were drawn. Other pages (catalog, product, cart, checkout, content pages, 404) have no dark mockup yet; derive them from the tokens and get owner approval per page group.
- The boards show placeholder data (names, order number, sums). Do not treat them as content.
- Before any code: read RD-29 for the architecture questions (switching mechanism, storage, flash-of-wrong-theme, token layering) and `design/RULES.md`.

## Related

- Light variant A carries a theme switcher «Світла / Темна», blurred and disabled with a «Скоро» badge, on the account hub (desktop + mobile). RD-29 enables it.
- Image rules for dark (RD-25 `cut` vs `photo`) and logo (RD-28) are open and must be decided in RD-29.
