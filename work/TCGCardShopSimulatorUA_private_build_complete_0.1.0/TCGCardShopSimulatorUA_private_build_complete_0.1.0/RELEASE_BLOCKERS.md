# Remaining release blockers

## Resolved: private installer payload

The four exact root loader files from the working setup are now present under `payload/`:

```text
.doorstop_version
doorstop_config.ini
winhttp.dll
version.dll
```

Static checks are recorded in `ROOT_LOADER_AUDIT.md`. This resolves the previous **payload completeness** blocker for a private/local build.

## 1. `Translator.dll` redistribution permission

Translator 1.0.3 by Shaklin is not under an open redistribution license on its Nexus page. Current permissions state:

- asset use in another mod/file requires permission;
- upload to other sites is prohibited;
- commercial/Donation Points use is prohibited;
- the author explicitly invites translators to edit and share `localization_data.txt` on Nexus.

Therefore:

- the **dictionary** can be prepared as a standalone translation file for Nexus;
- this **private workspace** may use the user-supplied `Translator.dll` locally;
- a **public one-file installer embedding `Translator.dll`** requires permission from Shaklin first.

Source checked during implementation: Nexus Mods page for Translator 1.0.3 by Shaklin.

## 2. Game/mod publication policy

No authoritative developer/Steam policy granting redistribution of a third-party mod bundle was established from the supplied files. Verify this before public release.

## 3. Windows runtime acceptance tests

Must still run the full localization smoke route, glyph test, clean/update/coexistence/uninstall tests, and capture the required screenshots/log evidence.

## 4. Signing

No signing certificate is available in this environment. A locally built executable will be unsigned unless a real code-signing workflow is added.
