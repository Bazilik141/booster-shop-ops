# Booster Shop — Design Canon

Canonical design decisions, rules, tokens and reference mockups for boostershop.website (OpenCart 4).
This folder is the persistent design memory for every executor (Codex, Claude Code, humans). Read it before any UI change.

Snapshot date: 2026-10-09. Sources: Claude Design project "BS" (specs, mockups), the live site on 2026-10-09 (tokens, shipped decisions), the designer's logo package (logo).
Repository home: `design/`. Claude (chat) maintains these documents; the owner commits.

## Folder map

| Path | What it is |
|---|---|
| `BRAND.md` | Fonts, palette, logo, shape, tone. |
| `TOKENS.md` | Full token registry incl. tokens added by later specs, and known drift. |
| `RULES.md` | Global design rules and guardrails that apply to every task. |
| `DECISIONS.md` | Approved decisions per site area, each pointing to its spec and mockup. |
| `tokens/boostershop-ds.css` | Copy of the live site stylesheet, fetched 2026-10-09. |
| `tokens/tokens.css` | Mockup tokens only (old gold and shadows; see `TOKENS.md`). |
| `specs/` | Original implementation handoffs (Ukrainian / English). Full detail: markup, CSS values, acceptance criteria, do-not-touch lists. |
| `reference/` | Final approved mockups (HTML + JSX/CSS). Open `*.html` in a browser. |
| `assets/` | Brand assets used by the site and mockups. `assets/logo/` = canonical logo files (designer package, 2026-10-09). `bs-logo-crop.png` = old raster, superseded. |

## Precedence (when sources disagree)

1. Official bank materials (PUMB, monobank) override anything about payment copy, logos and legal text.
2. `TOKENS.md` + the live `boostershop-ds.css` override hex values written anywhere else. Items marked "owner decision pending" are not settled; ask before changing them.
3. `RULES.md` and `BRAND.md`.
4. `DECISIONS.md`.
5. The newest spec in `specs/` for that area. A spec that says "Supersedes" wins over the one it names.
6. Reference mockup source (JSX/inline CSS). Where a spec says "the JSX wins", it wins over that spec's prose.
7. The live production DOM wins over an older spec about markup/class names (spec must be re-checked against prod before patching).

## Notes for executors

- Mockups are design references, not production code. Recreate in Twig + `boostershop-ds.css` with `bs-*` classes.
- Mockups use React/Babel from unpkg and Google Fonts; open them online.
- Two mockups (`B - плитки категорій ФІНАЛ.html`, `C - підкатегорії mobile у живому контексті.html`) render on top of saved production pages which are not included. They will show empty frames; their shipping CSS is in the inline `<script>` (`FINAL_CSS` etc.) and in the matching spec.
- Some approval boards (`… - варіанти.html`, `… - пропозиції.html`, `D - картка товару - раунд 2.html`, `H1 та Trust Strip - варіанти.html`) show the chosen option next to others. Only the option named in `DECISIONS.md` is canonical.
- Ukrainian UI copy in specs and mockups is approved copy. Use it verbatim.
- Deploy model: patches run directly on production, no staging. Each patch = one file in `patches/`, own backup and rollback.
- Updating this canon: a decision enters `DECISIONS.md` once it is live (marker verified on the site) or explicitly approved by the owner; drafts are listed under "Not canon yet".
