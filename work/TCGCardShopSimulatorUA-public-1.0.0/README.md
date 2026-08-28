# TCG Card Shop Simulator — Ukrainian Localization

Open-source Ukrainian localization for the Steam game **TCG Card Shop Simulator**.

## Install

1. Close the game.
2. Run `TCGCardShopSimulator-UA-Installer.exe`.
3. Confirm the detected Steam game folder and choose **Install / Update**.
4. If the installer reports an incompatible legacy localization plugin, review the plan and confirm it. The old plugin and its configuration are moved to `TCGCardShopSimulatorUA-backups` in the game folder; they are not deleted.
5. Start the game.

The installer never changes save files or original Unity game assets. Uninstall removes only this localization's managed files. A backup of an incompatible legacy localization plugin is deliberately kept and is not automatically restored, because it conflicts with this localization.

## Version 2.3.0

- Detects and archives an incompatible legacy localization plugin that can overwrite Ukrainian strings with English values.
- Adds 16 exact dynamic-UI overrides for text not registered in the game's I2 table, including grading day labels, wall labels, and Pack Opener Machine names.
- Uses the game's existing Korean localization slot as a carrier for Ukrainian strings, then changes its language-menu label to `Українська`.
- Registers the dictionary synchronously and at the verified I2 `UpdateSources` / `LocalizeAll` boundaries, without depending on Unity frame callbacks.
- Translates exact dynamic UI assignments through the standard TMPro and Unity UI text setters.
- Does not create a non-game I2 language or block manual language switching.

## Translation QA

- Master dictionary: 2,190 mappings.
- Exact duplicate source keys: 0.
- Case-insensitive source-key collisions: 15 pairs / 30 rows.
- Dynamic UI overrides: 16 exact keys, with 0 duplicates and 0 overlaps with the master dictionary.

## Build from source

The project intentionally does not include game DLLs. Use the bundled BepInEx runtime as the build reference and provide only the local game `Managed` folder:

```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -BepInExCoreDir ".\payload\BepInEx\core" `
  -GameManagedDir "C:\path\to\TCG Card Shop Simulator\Card Shop Simulator_Data\Managed"
```

The build writes the self-contained installer and `SHA256SUMS.txt` to `release`.

## License

Own plugin and installer code: [MIT](LICENSE), Copyright (c) 2026 Fresh Raccoon & Arcania.

BepInEx is distributed under its [MIT license](LICENSES/BepInEx-MIT.txt).
