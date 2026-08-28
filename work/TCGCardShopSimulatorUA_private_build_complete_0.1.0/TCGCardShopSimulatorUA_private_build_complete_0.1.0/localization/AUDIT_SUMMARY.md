# Localization audit summary

Inspected baseline `localization_data.txt`:

- 2196 physical lines;
- 2190 rows containing one `|` delimiter;
- 6 orphan physical continuation lines;
- 525 rows where source and target were identical;
- 0 exact duplicate source keys in the supplied revision (the earlier handoff audit reported 15, so the supplied dictionary is a different revision/state).

Final 0.1.0 dictionary:

- 2190 physical lines / 2190 valid mappings;
- 2190 distinct source keys;
- 0 malformed non-empty rows;
- 0 empty source keys;
- 0 duplicate source keys;
- 0 placeholder mismatches under the release validator;
- 0 CJK rows;
- 7 reviewed intentional `source == target` values;
- 610 mapping targets differ from the supplied baseline state.

Work applied:

- 6 mandatory split-record repairs;
- 240 equal rows reused a translated legacy `NotUsed/` / `Roadmap/` equivalent;
- 285 equal rows passed through the manual review table (including intentionally unchanged technical/numeric values);
- 77 additional English/corrupted target rows were repaired;
- 8 pre-existing placeholder parity issues were corrected;
- targeted quality overrides corrected several clearly broken inherited legacy translations.
