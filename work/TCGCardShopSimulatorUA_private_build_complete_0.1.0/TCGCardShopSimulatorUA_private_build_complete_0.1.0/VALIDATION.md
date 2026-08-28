# Validation evidence — 0.1.0

## Dictionary — PASS (static)

Final file:

```text
BepInEx/plugins/Translator/localization_data.txt
```

SHA-256:

```text
ad980b76dca03c38d8578312a8a9aea329fe3b742aff8c3318427a2aa9c99c3e
```

Validator result:

```text
physical_lines=2190
valid_records=2190
malformed_nonempty=0
empty_sources=0
duplicate_source_keys=0
source_equals_target=7
unexpected_source_equals_target=0
placeholder_mismatches=0
cjk_rows=0
```

The seven identical rows were reviewed and intentionally retained:

```text
HP
TCG
1st
2nd
3rd
OMW
OOMW
```

UTF-8 round-trip was verified by reading and rewriting the generated file as UTF-8 and matching the deterministic rebuild output.

### Baseline revision note

The handoff documented 15 duplicate source keys during an earlier audit. The supplied `BepInEx.zip` dictionary used for implementation contains **0 exact duplicate source keys** after the six split records are repaired. No duplicates were silently removed.


## Root loader payload — PASS (static)

The exact four files supplied from the working game root are present in `payload/`.

```text
.doorstop_version  = 4.5.0
doorstop_config.ini: enabled = true
target_assembly=BepInEx\core\BepInEx.Preloader.dll
winhttp.dll: PE32+ x86-64 Windows DLL
version.dll: PE32+ x86-64 Windows DLL
```

SHA-256:

```text
75a2f501000d4fe28f74bc7ee66dae5581959d28b6c44f0c1b0c05cd5ba261e6  .doorstop_version
4d5c6dfa0f771c6a5b1b0c559aca0bd0ece7d08b08fff894708dc3b73ce73cfc  doorstop_config.ini
8c6cdbc38836dee87e3368f5de1994d7c0ccebf29e4ce7aba3c0981f9375412c  winhttp.dll
8c6cdbc38836dee87e3368f5de1994d7c0ccebf29e4ce7aba3c0981f9375412c  version.dll
```

`winhttp.dll` and `version.dll` are byte-identical copies of the same x64 Doorstop proxy supplied by the user, installed under both proxy names used by the inspected baseline. This is recorded rather than "fixed".

## Runtime loader evidence — BASELINE ONLY

The supplied previous-run `BepInEx/LogOutput.log` was inspected but is **not packaged**. It showed:

- BepInEx `5.4.23.5` starting for Card Shop Simulator;
- Unity `2021.3.38.8007589`;
- 64-bit Windows / CLR `4.0.30319.42000`;
- `Translator 1.0.3` loading;
- the plugin reporting that it extended `localization_data.txt` by 601 entries;
- the plugin reporting that the localization file loaded.

This proves the supplied baseline plugin previously loaded. It does **not** prove the final Ukrainian dictionary has been exercised in game.

## Runtime localization smoke test — NOT RUN

Still required on Windows with the real/copy game:

- main menu;
- new game confirmation;
- shop setup/tutorial;
- phone/shop/item/card menus;
- inventory;
- checkout/customer interaction;
- card album;
- events;
- tournaments;
- worker/automation;
- settings;
- save/load;
- controller prompts;
- achievements;
- reload existing save.

After the route, check whether Translator appended any new English source keys.

## Glyph/font verification — NOT RUN

Still required in a representative screen:

```text
Ї ї Є є І і Ґ ґ
```

No font has been bundled pre-emptively.

## Installer execution tests — NOT RUN

The environment has no .NET SDK/Windows runtime, so the source could not be built or executed here. Still required:

- clean install in a copied game folder with no BepInEx;
- update over inspected baseline;
- coexistence with an unrelated BepInEx plugin/config;
- uninstall restoration;
- invalid folder;
- game-running case;
- write denied;
- managed file modified after install.

The source implements guards/manifest logic for these cases, but this document intentionally does not claim execution evidence that does not exist.
