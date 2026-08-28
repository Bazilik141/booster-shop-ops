#!/usr/bin/env python3
"""Rebuild the reviewed Ukrainian localization from the inspected Translator baseline.

This script intentionally does not ship the original baseline. Provide the inspected
localization_data.txt as SOURCE. The output is deterministic for that baseline.
"""
from __future__ import annotations
import argparse, collections, hashlib, json, pathlib, re

PLACEHOLDER_FIX = {
    'Drain XXX of damage dealt this turn': 'Поглинайте XXX завданої шкоди цього ходу',
    'Drain XXX of damage dealt this turn, draw YYY': 'Поглинайте XXX завданої шкоди цього ходу, візьміть YYY',
    "Negate XXX of opponent's earth element damage next turn, draw YYY": 'Нівелюйте XXX шкоди від стихії Землі супротивника наступного ходу, візьміть YYY',
    "Negate XXX of opponent's fire element damage next turn, draw YYY": 'Нівелюйте XXX шкоди від стихії Вогню супротивника наступного ходу, візьміть YYY',
    "Negate XXX of opponent's water element damage next turn, draw YYY": 'Нівелюйте XXX шкоди від стихії Води супротивника наступного ходу, візьміть YYY',
    "Negate XXX of opponent's wind element damage next turn, draw YYY": 'Нівелюйте XXX шкоди від стихії Вітру супротивника наступного ходу, візьміть YYY',
    'Toys and figurine might XXX price': 'Іграшки та фігурки можуть коштувати XXX',
    'Select XXXYYY card': 'Виберіть картку XXXYYY',
}

QUALITY_OVERRIDES = {
    '-Playable TCG': '-Можливість грати в TCG',
    'Roadmap/-Playable TCG': '-Можливість грати в TCG',
    'Arcade Claw': 'Аркадна хапайка',
    'NotUsed/Arcade Claw': 'Аркадна хапайка',
    'Rocket Missile': 'Ракетний снаряд',
    'NotUsed/Rocket Missile': 'Ракетний снаряд',
}

TOKEN_RE = re.compile(r'XXX|YYY|%|\{[^{}]+\}|<[^<>]+>')


def load_mapping_file(path: pathlib.Path, separator: str) -> dict[str, str]:
    result: dict[str, str] = {}
    for n, line in enumerate(path.read_text(encoding='utf-8').splitlines(), 1):
        if not line.strip() or line.lstrip().startswith('#'):
            continue
        if separator not in line:
            raise ValueError(f'{path}:{n}: missing separator {separator!r}')
        source, target = line.split(separator, 1)
        if source in result:
            raise ValueError(f'{path}:{n}: duplicate source {source!r}')
        result[source] = target
    return result


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('source', type=pathlib.Path, help='Inspected original localization_data.txt')
    ap.add_argument('output', type=pathlib.Path)
    ap.add_argument('--manual', type=pathlib.Path, required=True)
    ap.add_argument('--residue', type=pathlib.Path, required=True)
    ap.add_argument('--repairs', type=pathlib.Path, required=True)
    ap.add_argument('--report', type=pathlib.Path)
    args = ap.parse_args()

    repairs = load_mapping_file(args.repairs, '|')
    manual = load_mapping_file(args.manual, '\t')
    residue = load_mapping_file(args.residue, '\t')
    raw = args.source.read_text(encoding='utf-8-sig').splitlines()

    repaired: list[str] = []
    repair_hits: list[tuple[int, str]] = []
    i = 0
    while i < len(raw):
        line = raw[i]
        matched = False
        for source, target in repairs.items():
            if line.startswith(source + '|'):
                if i + 1 >= len(raw) or '|' in raw[i + 1]:
                    raise RuntimeError(f'Expected orphan continuation after physical line {i+1} for {source!r}')
                repaired.append(source + '|' + target)
                repair_hits.append((i + 1, source))
                i += 2
                matched = True
                break
        if not matched:
            repaired.append(line)
            i += 1

    if {s for _, s in repair_hits} != set(repairs):
        raise RuntimeError('Repair coverage mismatch')

    rows: list[list[str]] = []
    for n, line in enumerate(repaired, 1):
        if not line.strip():
            continue
        if line.count('|') != 1:
            raise RuntimeError(f'Malformed row after repair at logical line {n}: {line!r}')
        rows.append(list(line.split('|', 1)))

    reuse: dict[str, str] = {}
    for source, target in rows:
        if source == target:
            continue
        for prefix in ('NotUsed/', 'Roadmap/'):
            if source.startswith(prefix):
                reuse.setdefault(source[len(prefix):], target)

    reused = manual_hits = residue_hits = 0
    for row in rows:
        source, target = row
        if source in residue:
            row[1] = residue[source]
            residue_hits += 1
        elif source == target:
            if source in manual:
                row[1] = manual[source]
                manual_hits += 1
            elif source in reuse:
                row[1] = reuse[source]
                reused += 1
            else:
                raise RuntimeError(f'Unreviewed source==target row: {source!r}')

    for row in rows:
        source = row[0]
        if source in repairs:
            row[1] = repairs[source]
        if source in PLACEHOLDER_FIX:
            row[1] = PLACEHOLDER_FIX[source]
        if source in QUALITY_OVERRIDES:
            row[1] = QUALITY_OVERRIDES[source]

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text('\n'.join(s + '|' + t for s, t in rows) + '\n', encoding='utf-8', newline='\n')

    # Lightweight deterministic report; full release validation is validate_dictionary.py.
    pairs = [tuple(row) for row in rows]
    sources = [s for s, _ in pairs]
    equal = [s for s, t in pairs if s == t]
    placeholder_errors = []
    for n, (s, t) in enumerate(pairs, 1):
        if collections.Counter(TOKEN_RE.findall(s)) != collections.Counter(TOKEN_RE.findall(t)):
            placeholder_errors.append(n)
    report = {
        'input_physical_lines': len(raw),
        'output_records': len(pairs),
        'distinct_source_keys': len(set(sources)),
        'repair_count': len(repair_hits),
        'legacy_reuse_count': reused,
        'manual_equal_table_hits': manual_hits,
        'english_residue_table_hits': residue_hits,
        'source_equals_target': equal,
        'placeholder_errors_lightweight': placeholder_errors,
        'sha256': hashlib.sha256(args.output.read_bytes()).hexdigest(),
    }
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
