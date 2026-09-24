# 3D-P-007 Serhiy installation package report

Date: 2026-09-01

## Outcome

A final portable Windows x64 delivery set was prepared for Serhiy. It contains the rebuilt application archive, a separate beginner-facing Ukrainian installation instruction, and a SHA-256 checksum file. The same installation instruction is included inside the archive as `Прочитай мене.txt`.

## Delivery folder

`3d-print/serhiy-local-server/dist/ПЕРЕДАТИ СЕРГІЮ 2026-09-01/`

Contents:

- `Booster-3DP-Serhiy_Node-v24.19.0_20260901.zip`
- `ІНСТРУКЦІЯ ДЛЯ СЕРГІЯ.txt`
- `SHA256.txt`

Archive properties:

- size: 35,826,708 bytes;
- SHA-256: `79DFF557C46BE4357228E61C66379776DB977F2F8B8B0F3C113A9A3F7C592534`;
- portable runtime: Node.js v24.19.0 Windows x64;
- repository runtime copies: zero.

## Installation guidance added

The user-facing instruction now covers:

- extracting the complete ZIP instead of running from inside the archive;
- keeping all packaged files together;
- first-run entry of the owner-provided Web App URL and separate Serhiy token;
- ordinary subsequent launches through `Запустити.bat`;
- creating a desktop shortcut;
- token rotation through `Змінити токен.bat`;
- success criteria and bounded troubleshooting;
- current updates by extracting a new package folder while retaining the Windows user-scoped URL and token.

The instruction explicitly states that no separate Node.js installation is required and that the token must not be sent or photographed.

The owner-facing installation runbook is stored at `docs/3DP-Serhiy-installation.md`.

## Verification

- Package builder completed successfully.
- Portable Node version check: pass (`v24.19.0`).
- Distribution encoding check: pass.
- Source, staging, and ZIP launcher-reference checks: pass.
- The final ZIP was extracted to a new temporary directory.
- The extracted `runtime/node.exe` and `app/server.mjs` were started on temporary port 3109 without restarting the active server on port 3107.
- Extracted package root endpoint: HTTP 200.
- Extracted `/draft-categories.js`: HTTP 200.
- Extracted `Прочитай мене.txt` contains both installation and update sections.
- Temporary QA process was stopped and its exact temporary directory was removed.
- The owner's active local server remained available on port 3107 with HTTP 200.

## Update boundary

This delivery is a portable ZIP package, not an automatic EXE updater. Current upgrades replace the application folder while preserving credentials stored in the Windows user profile. A self-locating EXE updater remains a separate future component and is not claimed as part of this release.

## Boundaries

- No token or Web App URL was added to any deliverable.
- No Apps Script, dashboard, CRM, or Google Sheet change was made in this packaging round.
- No commit, push, deployment, or Notion update was performed.
