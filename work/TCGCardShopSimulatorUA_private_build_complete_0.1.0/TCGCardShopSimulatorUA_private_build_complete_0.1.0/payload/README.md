# Private payload status

Payload is structurally complete for a private/local build.

Present from the user-supplied `BepInEx.zip`:

- BepInEx `5.4.23.5` core files;
- Translator `1.0.3` (`Translator.dll`);
- Ukrainian `localization_data.txt` 0.1.0;
- `shaklin.Translator.cfg` with `ENABLED = true`.

Present from the exact working game root supplied on 2026-08-25:

```text
.doorstop_version
doorstop_config.ini
winhttp.dll
version.dll
```

Static audit: Doorstop `4.5.0`, enabled, target `BepInEx\core\BepInEx.Preloader.dll`, x86-64 proxy DLLs. See `../ROOT_LOADER_AUDIT.md`.

**Do not redistribute this private workspace as-is.** Translator.dll has restrictive Nexus permissions; obtain author permission before including it in a public installer.
