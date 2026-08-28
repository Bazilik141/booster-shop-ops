# Root loader audit

Static audit of the four files supplied from the working TCG Card Shop Simulator game root.

| File | Size | SHA-256 | Static result |
| --- | ---: | --- | --- |
| `.doorstop_version` | 5 B | `75a2f501000d4fe28f74bc7ee66dae5581959d28b6c44f0c1b0c05cd5ba261e6` | `4.5.0` |
| `doorstop_config.ini` | 1,460 B | `4d5c6dfa0f771c6a5b1b0c559aca0bd0ece7d08b08fff894708dc3b73ce73cfc` | enabled; target is BepInEx Preloader |
| `winhttp.dll` | 26,112 B | `8c6cdbc38836dee87e3368f5de1994d7c0ccebf29e4ce7aba3c0981f9375412c` | PE32+ x86-64 Windows DLL |
| `version.dll` | 26,112 B | `8c6cdbc38836dee87e3368f5de1994d7c0ccebf29e4ce7aba3c0981f9375412c` | PE32+ x86-64 Windows DLL |

Relevant Doorstop configuration:

```ini
[General]
enabled = true
target_assembly=BepInEx\core\BepInEx.Preloader.dll
redirect_output_log = false
ignore_disable_switch = false

[UnityMono]
dll_search_path_override =
debug_enabled = false
debug_address = 127.0.0.1:10000
debug_suspend = false
```

The two proxy DLLs are byte-identical. This matches the supplied working setup and is preserved intentionally. Runtime loading still requires a Windows/game smoke test; this file records only static evidence.
