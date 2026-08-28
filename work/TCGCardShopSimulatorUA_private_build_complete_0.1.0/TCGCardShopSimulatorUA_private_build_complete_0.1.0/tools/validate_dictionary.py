#!/usr/bin/env python3
from __future__ import annotations
import argparse, collections, pathlib, re

TOKEN_PATTERNS = [
    re.compile(r"XXX|YYY"),
    re.compile(r"\{[^{}]+\}"),
    re.compile(r"%(?:\d+\$)?[-+#0 .]*\d*(?:\.\d+)?[a-zA-Z%]"),
    re.compile(r"<[^<>]+>"),
    re.compile(r"\\[nrt]"),
    # The handoff requires numeric forms to survive byte-for-byte as tokens.
    re.compile(r"\b\d+(?:[.,]\d+)?\b"),
]
CJK_RE = re.compile(r"[\u3400-\u4DBF\u4E00-\u9FFF\u3040-\u30FF\uAC00-\uD7AF]")

def tokens(s: str):
    out=[]
    for p in TOKEN_PATTERNS:
        out.extend(p.findall(s))
    return collections.Counter(out)

def load_allowed_equal(path: pathlib.Path | None) -> set[str]:
    if path is None:
        return set()
    return {
        line.strip() for line in path.read_text(encoding="utf-8").splitlines()
        if line.strip() and not line.lstrip().startswith("#")
    }

def main():
    ap=argparse.ArgumentParser(description="Validate TCG Card Shop Simulator localization_data.txt")
    ap.add_argument("path", type=pathlib.Path)
    ap.add_argument("--expect-min-records", type=int, default=0)
    ap.add_argument("--allowed-equal-file", type=pathlib.Path)
    ap.add_argument("--forbid-cjk", action="store_true")
    args=ap.parse_args()

    try:
        text=args.path.read_text(encoding="utf-8")
    except UnicodeDecodeError as e:
        print(f"FAIL UTF-8: {e}"); return 2

    lines=text.splitlines()
    malformed=[]; empty=[]; mappings=[]; placeholder_errors=[]; cjk=[]
    for n,line in enumerate(lines,1):
        if not line.strip(): continue
        if line.count("|") != 1:
            malformed.append((n,line)); continue
        src,dst=line.split("|",1)
        if not src: empty.append(n)
        mappings.append((n,src,dst))
        if tokens(src) != tokens(dst):
            placeholder_errors.append((n,src,dst,tokens(src),tokens(dst)))
        if CJK_RE.search(src) or CJK_RE.search(dst):
            cjk.append((n,src,dst))

    counts=collections.Counter(src for _,src,_ in mappings)
    dupes={k:v for k,v in counts.items() if v>1}
    equal=[(n,s) for n,s,d in mappings if s==d]
    allowed=load_allowed_equal(args.allowed_equal_file)
    unexpected_equal=[(n,s) for n,s in equal if s not in allowed]
    missing_allowed=sorted(allowed - {s for _,s in equal})

    print(f"physical_lines={len(lines)}")
    print(f"valid_records={len(mappings)}")
    print(f"malformed_nonempty={len(malformed)}")
    print(f"empty_sources={len(empty)}")
    print(f"duplicate_source_keys={len(dupes)}")
    print(f"source_equals_target={len(equal)}")
    print(f"unexpected_source_equals_target={len(unexpected_equal)}")
    print(f"placeholder_mismatches={len(placeholder_errors)}")
    print(f"cjk_rows={len(cjk)}")

    if malformed:
        for n,l in malformed[:20]: print(f"MALFORMED line {n}: {l!r}")
    if placeholder_errors:
        for n,s,d,a,b in placeholder_errors[:20]: print(f"PLACEHOLDER line {n}: {a} != {b} | {s!r} -> {d!r}")
    if unexpected_equal:
        for n,s in unexpected_equal[:20]: print(f"UNREVIEWED EQUAL line {n}: {s!r}")
    if missing_allowed:
        print("NOTE allowed-equal tokens no longer equal: " + ", ".join(repr(x) for x in missing_allowed))
    if cjk:
        for n,s,d in cjk[:20]: print(f"CJK line {n}: {s!r} -> {d!r}")
    if args.expect_min_records and len(mappings) < args.expect_min_records:
        print(f"FAIL: expected at least {args.expect_min_records} valid records")
        return 2

    failed = bool(malformed or empty or placeholder_errors or unexpected_equal)
    if args.forbid_cjk and cjk:
        failed = True
    return 1 if failed else 0

if __name__ == "__main__":
    raise SystemExit(main())
