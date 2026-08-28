#!/usr/bin/env python3
from __future__ import annotations
import argparse, hashlib, pathlib
ap=argparse.ArgumentParser(); ap.add_argument("paths",nargs="+",type=pathlib.Path); ap.add_argument("-o","--output",type=pathlib.Path,default=pathlib.Path("SHA256SUMS.txt")); a=ap.parse_args()
rows=[]
for p in a.paths:
    if p.is_dir(): files=sorted(x for x in p.rglob("*") if x.is_file())
    else: files=[p]
    for f in files:
        h=hashlib.sha256(f.read_bytes()).hexdigest(); rows.append(f"{h}  {f.as_posix()}")
a.output.write_text("\n".join(rows)+"\n",encoding="utf-8"); print(a.output)
