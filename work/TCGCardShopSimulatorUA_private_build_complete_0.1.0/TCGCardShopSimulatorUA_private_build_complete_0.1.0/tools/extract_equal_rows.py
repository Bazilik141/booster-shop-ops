#!/usr/bin/env python3
from __future__ import annotations
import argparse, csv, pathlib
ap=argparse.ArgumentParser(); ap.add_argument("input",type=pathlib.Path); ap.add_argument("output",type=pathlib.Path); a=ap.parse_args()
rows=[]
for n,line in enumerate(a.input.read_text(encoding="utf-8").splitlines(),1):
    if line.count("|")!=1: continue
    s,t=line.split("|",1)
    if s==t: rows.append((n,s,t))
with a.output.open("w",encoding="utf-8-sig",newline="") as f:
    w=csv.writer(f); w.writerow(["line","source","target"]); w.writerows(rows)
print(f"equal_rows={len(rows)} -> {a.output}")
