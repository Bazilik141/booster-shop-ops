# Remaining untranslated / uncollectable text

## Known dictionary

No normal user-facing `source == target` English rows remain in the inspected 2190-key dictionary.

Seven reviewed values intentionally remain identical:

```text
HP
TCG
1st
2nd
3rd
OMW
OOMW
```

`HP`/`TCG` are standard technical abbreviations. `1st`/`2nd`/`3rd` are preserved literal numeric forms as required by the handoff. `OMW`/`OOMW` are tournament tie-break metric acronyms and are retained as technical labels.

## Not yet collectable in this environment

The final dictionary has not been run through every game screen. Translator 1.0.3 can append newly encountered source keys at runtime, so additional strings may still exist in branches not represented by the supplied dictionary.

Any new keys found during the required smoke route should be appended to the translation table, translated, validated, and the route repeated until no new keys appear (or the remainder is documented here).

Text rendered by a Unity component not intercepted by Translator cannot be classified statically from this package. Capture the screen and relevant BepInEx log before considering a plugin or asset-level change.
