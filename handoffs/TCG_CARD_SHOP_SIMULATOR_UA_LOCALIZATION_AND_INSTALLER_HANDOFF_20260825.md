# TCG Card Shop Simulator — Ukrainian localization and one-file installer

Date: 2026-08-25

## Assignment

Create a complete, maintainable Ukrainian localization package for **TCG Card Shop Simulator** and, if technically practical, a single Windows installer executable for ordinary users.

The installer must let a user select the existing Steam game folder, install the localization and its required loader automatically, and provide a safe uninstall. It must not distribute any original game executable, game assets, save files, or Steam files.

This task is based on a read-only inspection of:

```text
E:\SteamLibrary\steamapps\common\TCG Card Shop Simulator.zip
```

Do not treat the archive as permission to redistribute its contents. Before public release, verify the game/mod policy, Steam publication rules, and licenses for every third-party runtime or font included in the package.

## Verified baseline

The archive is a 1,963,131,512-byte Windows Unity game build. It already has the required loader; users do **not** need a separate manual BepInEx installation before using this mod.

| Item | Verified value |
| --- | --- |
| Game executable | `Card Shop Simulator.exe` |
| Unity runtime | `2021.3.38.8007589` |
| Architecture | 64-bit Windows / Mono CLR `4.0.30319.42000` |
| Loader | BepInEx `5.4.23.5` |
| Loader activation | `winhttp.dll` + `version.dll` + `doorstop_config.ini`, with target `BepInEx\\core\\BepInEx.Preloader.dll` |
| Translation plugin | `BepInEx/plugins/Translator/Translator.dll`, `shaklin.Translator`, version `1.0.3` |
| Translation settings | `BepInEx/config/shaklin.Translator.cfg`, currently `ENABLED = true` |
| Translation dictionary | `BepInEx/plugins/Translator/localization_data.txt` |

The BepInEx log proves that the plugin was loaded and loaded the dictionary. The plugin automatically appended **601** newly discovered localization keys during a previous run, so it can be used as the runtime collection mechanism for strings not yet in the dictionary.

The plugin metadata references `I2.Loc.LocalizationManager` from `Assembly-CSharp.dll`, searches for `.ttf`/`.otf` font files, and has font-replacement code. This suggests a path for fixing missing Cyrillic glyphs, but exact font-loading behavior must be confirmed against the plugin source/decompilation or a runtime test before implementation.

## Localization data audit

`localization_data.txt` is a small, editable UTF-8 text dictionary in this form:

```text
English source text|Український переклад
```

Audit results:

| Check | Result |
| --- | ---: |
| Physical lines | 2,196 |
| Valid one-delimiter mapping lines | 2,190 |
| Distinct source keys | 2,175 |
| Duplicate source keys | 15 |
| Source and target currently identical | 525 |
| Chinese/Japanese characters in dictionary keys or targets | 0 |
| Non-empty malformed lines | 6 |

Therefore do not claim that the current visible “hieroglyphs” come from this dictionary: none are stored here. They are more likely one of these cases, which must be distinguished by a runtime check:

1. an untranslated key that is absent from the file until the relevant screen/gameplay event is opened;
2. a malformed entry that cannot be parsed;
3. a Cyrillic glyph/font failure; or
4. text rendered by a component the current plugin does not intercept.

### Mandatory repair before translation

The following six logical records were split across two physical lines. Replace each preceding short mapping plus its following orphan line with exactly one mapping below. Preserve the English key exactly; remove the orphan line; do not add a literal line break inside a record.

```text
Interact Put Item|Взаємодіяти / Покласти предмет
Items will be lost if checkout is not finished. Are you sure about proceeding to the next day?|Предмети буде втрачено, якщо оформлення покупки не завершено. Ви впевнені, що хочете перейти до наступного дня?
Jump Checkout|Стрибок / Оформлення покупки
There are items in the box. Are you sure about throwing it away?|У коробці є предмети. Ви впевнені, що хочете їх викинути?
The card has high value. Are you sure about throwing it away?|Картка має високу вартість. Ви впевнені, що хочете її викинути?
Canceling will reset the registered players. Are you sure about canceling the tournament?|Скасування скине зареєстрованих гравців. Ви впевнені, що хочете скасувати турнір?
```

The last two are currently still English on both sides. They must remain a single source-to-Ukrainian mapping after the repair.

Do not silently remove the 15 duplicate source keys until actual plugin conflict behavior is confirmed. Preserve placeholders such as `XXX` and `YYY` exactly, preserve source punctuation, and never put an unescaped `|` or a newline into either field.

### Translation work

1. Start with all 525 records where source equals target. Translate normal UI, settings, quests, descriptions, notifications, spells, item names, and gameplay messages into natural Ukrainian.
2. Keep intentionally universal values only after review: short technical tokens such as `HP`/`TCG`, official brand names, and approved proper names may remain unchanged. Do not use this as a blanket excuse to skip the 525 records.
3. Retain all placeholders (`XXX`, `YYY`, numeric forms, `%`, punctuation, and tag-like syntax) byte-for-byte unless a source key demonstrably uses another supported placeholder convention.
4. Do not change the left side of an existing mapping. The plugin matches the exact English source string.
5. Use one UTF-8 text file, one `source|target` record per physical line. Validate the file before packaging: no malformed rows, no empty source keys, no accidental extra separators, and no placeholder losses.
6. Exercise every practical screen and gameplay branch with logging enabled. When the plugin appends unknown English keys, review and translate them, then repeat until the tested route no longer produces new keys.

Suggested test route: main menu; new-game confirmation; shop setup; tutorial; phone/shop/item/card menus; inventory; checkout; customer interaction; card album; events; tournaments; worker/automation; settings; save/load; controller prompts; achievements; and a fresh session after loading an existing save.

### Font/glyph verification

Before bundling a font, test a translation containing Ukrainian-specific letters: `Ї ї Є є І і Ґ ґ`. Record a screenshot and the relevant BepInEx log lines.

- If these glyphs render correctly, do not add a font merely as a precaution.
- If they show as boxes, garbled symbols, or missing glyphs, use the plugin's existing font-replacement mechanism only after confirming its required folder/name convention. Bundle only a font whose redistribution license permits it, and list the font license in the release.
- If glyphs render correctly but some text remains Chinese/Japanese/garbled, capture the screen and map it to a source key or Unity component before changing the plugin. Do not overwrite Unity assets blindly.

## Desired release: one convenient `.exe`

Build a signed executable only if signing is available; do not imply that an unsigned file is signed. A `.NET` single-file GUI installer is acceptable. It may embed a compressed **mod payload**, but never original game data.

### Installer behavior

1. Show Ukrainian UI with two clear actions: **Install / Update** and **Uninstall**.
2. Locate the game root automatically from Steam libraries when possible, but always offer a folder picker. Validate the selected folder contains both:

   ```text
   Card Shop Simulator.exe
   Card Shop Simulator_Data\
   ```

3. Refuse to modify files while `Card Shop Simulator.exe` is running. Explain how to close the game.
4. If BepInEx is absent, install the verified compatible runtime and Doorstop root files required by this mod:

   ```text
   .doorstop_version
   doorstop_config.ini
   winhttp.dll
   version.dll
   BepInEx\ (required BepInEx 5.4.23.5 files)
   ```

5. Install the Ukrainian plugin payload under unique owned paths:

   ```text
   BepInEx\plugins\Translator\Translator.dll
   BepInEx\plugins\Translator\localization_data.txt
   BepInEx\config\shaklin.Translator.cfg
   ```

6. If another BepInEx/mod setup already exists, preserve unrelated plugins and configuration. Do not blindly replace its whole `BepInEx` tree. Detect incompatible loader/root files, make a timestamped backup of only files this installer will replace, and show the user what will change before writing.
7. Write an ownership manifest for this installer with relative paths, original-file backups/hashes, installed-file hashes, mod version, and install timestamp. Store it outside the game's original assets, for example under `BepInEx\config\TCGCardShopSimulatorUA\`.
8. On uninstall, remove only files recorded in that manifest and restore backups when their current hashes still match the installed payload. If a user changed a managed file after installation, do not delete it silently; show a safe manual-resolution message.
9. Do not need administrator rights by default. If Windows denies access to the selected Steam library, explain that the user must rerun the installer as administrator.
10. Finish with a plain Ukrainian success message: launch the game once; then report the exact log location if the translation does not appear.

### Package/release contents

The final user download may be one `.exe`, but the source/release repository must also contain:

- reproducible installer source and build instructions;
- the mod payload as separate reviewable files;
- `README_UA.md` with install, update, uninstall, compatibility, and troubleshooting steps;
- `LICENSES/` for BepInEx, Translator, and any font;
- `CHANGELOG.md` with game version and mod version;
- checksums for the delivered executable and payload.

Never package `Card Shop Simulator.exe`, `Card Shop Simulator_Data`, Steam DLLs from the game, saves, logs, or the full 1.96 GB archive.

## Validation / acceptance criteria

### Dictionary

- All six broken records above are repaired into valid single lines.
- The parser reports zero malformed non-empty lines.
- Every source-equals-target row was reviewed; normal user-facing English is translated.
- Placeholder parity is exact for every mapping.
- A UTF-8 reload of the file preserves Ukrainian text.

### Runtime

- BepInEx log confirms both BepInEx and `Translator 1.0.3` (or an intentionally upgraded compatible replacement) load successfully.
- No new keys are appended while completing the documented smoke-test route, or every appended key is included in a documented remaining-work list.
- `Ї ї Є є І і Ґ ґ` render correctly in a representative UI screen.
- Screenshots show translated main menu, shop UI, inventory, checkout, a long text dialog, and settings.

### Installer

- Clean-install test in a copied game folder with no BepInEx.
- Update test over the inspected baseline.
- Coexistence test with an unrelated BepInEx plugin/configuration left intact.
- Uninstall test restores the pre-install state for installer-owned files.
- Invalid-folder, running-game, write-denied, and modified-file cases fail safely and explain the next action in Ukrainian.

## Explicit non-goals

- Do not modify or repack original Unity assets unless runtime collection proves that the plugin cannot reach a specific required component and a separately reviewed change is approved.
- Do not modify saves, Steam account data, game executable logic, multiplayer/network behavior, or anti-cheat.
- Do not promise 100% translation without completing the runtime coverage test above.

## Output requested from the implementing chat

Return a reviewable project/package, not only advice:

1. repaired and expanded `localization_data.txt`;
2. source code and built installer executable if the environment can safely build it;
3. exact package tree, hashes, and build command;
4. concise Ukrainian end-user `README` text suitable for a Steam guide;
5. evidence for dictionary validation and clean install/update/uninstall tests;
6. a short list of remaining untranslated or uncollectable text, if any.

If direct execution of the game is unavailable, complete the static dictionary repair and installer source, clearly mark runtime tests as not run, and do not invent their result.
